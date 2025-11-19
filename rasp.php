<?php
session_start();
require_once __DIR__ . '/db.php';

// Отладка (удалите/измените в проде)
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Проверка подключения БД
if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "Ошибка: подключение к БД не найдено. Проверьте db.php";
    exit;
}

// Проверка доступа: только администратор
if (empty($_SESSION['is_admin'])) {
    header('Location: indexuser.html');
    exit;
}

// Логаут
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: indexuser.html');
    exit;
}

// Инициализация переменных
$error = '';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Фильтр: ?day=1..7 (1 — Пн). Если не задан — показываем все даты.
$day = isset($_GET['day']) ? (int)$_GET['day'] : 0;
if ($day < 0 || $day > 7) $day = 0;
$weekdayIndex = $day > 0 ? $day - 1 : null;

$dayNames = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
$fullDayNames = ['Понедельник','Вторник','Среда','Четверг','Пятница','Суббота','Воскресенье'];

$lessonTimes = [
    1 => '08:30–09:50',
    2 => '10:00–11:20',
    3 => '11:30–12:50',
    4 => '13:20–14:40',
    5 => '14:50–16:10',
    6 => '16:20–17:40',
    7 => '17:50–19:10',
];

$groupId = 0; // фильтр по группе через селектор удалён; оставляем переменную для админ-формы
$groups = [];
try {
    $gr = $conn->query("SELECT id, name FROM `groups` ORDER BY name");
    if ($gr) {
        while ($g = $gr->fetch_assoc()) $groups[] = $g;
    }
} catch (mysqli_sql_exception $e) {
    // не фатально — оставим пустой список
}

// preserve date range filter params for redirects/links
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
// Если не заданы даты — устанавливаем текущую неделю (пн-вс)
if (empty($filterDateFrom)) {
    $today = strtotime(date('Y-m-d'));
    $dayOfWeek = (int)date('w', $today); // 0=вс, 1=пн, ..., 6=сб
    // Смещаемся на понедельник текущей недели
    $mondayOffset = ($dayOfWeek === 0) ? -6 : 1 - $dayOfWeek;
    $filterDateFrom = date('Y-m-d', strtotime($mondayOffset . ' days', $today));
}
if (empty($filterDateTo)) {
    $today = strtotime(date('Y-m-d'));
    $dayOfWeek = (int)date('w', $today); // 0=вс, 1=пн, ..., 6=сб
    // Смещаемся на воскресенье текущей недели
    $sundayOffset = ($dayOfWeek === 0) ? 0 : 7 - $dayOfWeek;
    $filterDateTo = date('Y-m-d', strtotime($sundayOffset . ' days', $today));
}
$filterGroupName = isset($_GET['group_name']) ? trim($_GET['group_name']) : '';
$filterTeacherName = isset($_GET['teacher_name']) ? trim($_GET['teacher_name']) : '';

$urlParamsArr = [];
if ($filterDateFrom) $urlParamsArr[] = 'date_from=' . urlencode($filterDateFrom);
if ($filterDateTo) $urlParamsArr[] = 'date_to=' . urlencode($filterDateTo);
if ($filterGroupName) $urlParamsArr[] = 'group_name=' . urlencode($filterGroupName);
if ($filterTeacherName) $urlParamsArr[] = 'teacher_name=' . urlencode($filterTeacherName);
// фильтр по преподавателю
$teacherFilter = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;
if ($teacherFilter > 0) $urlParamsArr[] = 'teacher=' . $teacherFilter;
$queryStart = $urlParamsArr ? '?' . implode('&', $urlParamsArr) : '';
$querySuffix = $urlParamsArr ? '&' . implode('&', $urlParamsArr) : '';

// Helper: build url preserving current GET params and applying overrides
function build_url(array $overrides = []){
    $params = $_GET;
    unset($params['edit'], $params['delete']);
    foreach($overrides as $k => $v){
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $qs = http_build_query($params);
    return 'rasp.php' . ($qs ? ('?' . $qs) : '');
}

// --- Админ: справочники для формы редактирования/добавления ---
$teachers = [];
$disciplines = [];
$rooms = [];
$lesson_types = [];
try {
    $res = $conn->query("SELECT id, fullname FROM teachers ORDER BY fullname");
    if ($res) while ($r = $res->fetch_assoc()) $teachers[] = $r;
    $res = $conn->query("SELECT id, name FROM disciplines ORDER BY name");
    if ($res) while ($r = $res->fetch_assoc()) $disciplines[] = $r;
    $res = $conn->query("SELECT id, number FROM rooms ORDER BY number");
    if ($res) while ($r = $res->fetch_assoc()) $rooms[] = $r;
    $res = $conn->query("SELECT id, name FROM lesson_types ORDER BY id");
    if ($res) while ($r = $res->fetch_assoc()) $lesson_types[] = $r;
} catch (mysqli_sql_exception $e) {
    // игнорируем, форма сможет работать частично
}

// Обработка удаления записи (GET ?delete=ID)
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($delId > 0) {
        try {
            $dstmt = $conn->prepare('DELETE FROM lessons WHERE id = ?');
            $dstmt->bind_param('i', $delId);
            $dstmt->execute();
            $dstmt->close();
        } catch (mysqli_sql_exception $e) {
            // можно вывести ошибку, но продолжим
        }
    }
    // redirect чтобы избежать повторного удаления — сохраняем фильтры
    $loc = 'rasp.php' . ($queryStart ? $queryStart : '');
    if ($day) {
        $loc .= ($queryStart ? '&' : '?') . 'day=' . $day;
    }
    header('Location: ' . $loc);
    exit;
}

// Обработка POST для добавления/редактирования (form_type = schedule)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'schedule') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $p_group = (int)($_POST['group_id'] ?? 0);
    $p_teacher = (int)($_POST['teacher_id'] ?? 0);
    $p_discipline = (int)($_POST['discipline_id'] ?? 0);
    $p_room = (int)($_POST['room_id'] ?? 0);
    $p_type = (int)($_POST['lesson_type_id'] ?? 0);
    $p_date = trim($_POST['date'] ?? '');
    $p_num = (int)($_POST['lesson_num'] ?? 0);

    // Валидация: проверка обязательных полей
    $validation_errors = [];
    
    if ($p_group <= 0) {
        $validation_errors[] = 'Выберите группу';
    }
    if (empty($p_date)) {
        $validation_errors[] = 'Выберите дату';
    } else {
        // Проверяем формат даты
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $p_date)) {
            $validation_errors[] = 'Неверный формат даты';
        } else {
            // Проверяем, является ли это валидной датой
            $date_parts = explode('-', $p_date);
            if (!checkdate((int)$date_parts[1], (int)$date_parts[2], (int)$date_parts[0])) {
                $validation_errors[] = 'Дата не существует';
            }
        }
    }
    if ($p_num <= 0 || $p_num > 10) {
        $validation_errors[] = 'Номер пары должен быть от 1 до 10';
    }
    if ($p_teacher > 0 && $p_discipline <= 0) {
        $validation_errors[] = 'Если выбран преподаватель, выберите также дисциплину';
    }

    if (!empty($validation_errors)) {
        $error = 'Ошибки при заполнении:\n- ' . implode('\n- ', $validation_errors);
    } else {
        try {
            if ($id > 0) {
                $ust = $conn->prepare('UPDATE lessons SET group_id=?, teacher_id=?, discipline_id=?, room_id=?, lesson_type_id=?, date=?, lesson_num=? WHERE id=?');
                $ust->bind_param('iiiiisii', $p_group, $p_teacher, $p_discipline, $p_room, $p_type, $p_date, $p_num, $id);
                $ust->execute();
                $ust->close();
            } else {
                $ist = $conn->prepare('INSERT INTO lessons (group_id, teacher_id, discipline_id, room_id, lesson_type_id, date, lesson_num) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $ist->bind_param('iiiiisi', $p_group, $p_teacher, $p_discipline, $p_room, $p_type, $p_date, $p_num);
                $ist->execute();
                $ist->close();
            }
        } catch (mysqli_sql_exception $e) {
            $error = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }

    // после сохранения перенаправим на ту же фильтрацию
    if (empty($error)) {
        $loc = 'rasp.php' . ($queryStart ? $queryStart : '');
        if ($day) {
            $loc .= ($queryStart ? '&' : '?') . 'day=' . $day;
        }
        header('Location: ' . $loc);
        exit;
    }
}

// при редактировании подгрузим запись
$editRow = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    if ($eid > 0) {
        try {
            $ers = $conn->prepare('SELECT * FROM lessons WHERE id = ?');
            $ers->bind_param('i', $eid);
            $ers->execute();
            $res = $ers->get_result();
            $editRow = $res->fetch_assoc();
            $ers->close();
        } catch (mysqli_sql_exception $e) {
            // ignore
        }
    }
}

// Запрос: используем таблицу schedule (создана в дампе) и LEFT JOINs для связанных сущностей
$sql = "
    SELECT
        l.id,
        l.date,
        l.lesson_num,
        COALESCE(g.name, '-') AS group_name,
        COALESCE(d.name, '-') AS discipline,
    COALESCE(t.fullname, '-') AS teacher_name,
    COALESCE(r.number, '-') AS room,
        COALESCE(lt.name, '-') AS lesson_type
    FROM lessons l
    LEFT JOIN `groups` g ON l.group_id = g.id
    LEFT JOIN teachers t ON l.teacher_id = t.id
    LEFT JOIN disciplines d ON l.discipline_id = d.id
    LEFT JOIN rooms r ON l.room_id = r.id
    LEFT JOIN lesson_types lt ON l.lesson_type_id = lt.id
";

// build preserve params (group/date and range/name) for links and redirects
$filterDate = isset($_GET['date']) ? $_GET['date'] : '';
$urlParamsArr = [];
if ($groupId > 0) $urlParamsArr[] = 'group=' . $groupId;
if ($filterDate) $urlParamsArr[] = 'date=' . urlencode($filterDate);
if ($filterDateFrom) $urlParamsArr[] = 'date_from=' . urlencode($filterDateFrom);
if ($filterDateTo) $urlParamsArr[] = 'date_to=' . urlencode($filterDateTo);
if ($filterGroupName) $urlParamsArr[] = 'group_name=' . urlencode($filterGroupName);
$queryStart = $urlParamsArr ? '?' . implode('&', $urlParamsArr) : '';
$querySuffix = $urlParamsArr ? '&' . implode('&', $urlParamsArr) : '';
// Если не заданы фильтры — не показываем всё подряд
if ($weekdayIndex === null && $groupId === 0 && empty($filterDateFrom) && empty($filterDateTo)) {
    $showAll = false;
} else {
    $showAll = true;
}

// Подготовим WHERE клаузу и параметры (поддержка day и group)
$where = [];
$types = '';
$values = [];
if ($weekdayIndex !== null) {
    $where[] = 'WEEKDAY(l.date) = ?';
    $types .= 'i';
    $values[] = $weekdayIndex;
}
// фильтрация по преподавателю
if ($teacherFilter > 0) {
    $where[] = 'l.teacher_id = ?';
    $types .= 'i';
    $values[] = $teacherFilter;
}
// если id преподавателя не задан, допускаем фильтрацию по части ФИО
if ($teacherFilter === 0 && $filterTeacherName) {
    $where[] = 't.fullname LIKE ?';
    $types .= 's';
    $values[] = '%' . $filterTeacherName . '%';
}
if ($groupId > 0) {
    $where[] = 'l.group_id = ?';
    $types .= 'i';
    $values[] = $groupId;
}
// если group не выбран, можно фильтровать по части названия группы
if ($groupId === 0 && $filterGroupName) {
    $where[] = 'g.name LIKE ?';
    $types .= 's';
    $values[] = '%' . $filterGroupName . '%';
}
if ($filterDateFrom && $filterDateTo) {
    $where[] = 'l.date BETWEEN ? AND ?';
    $types .= 'ss';
    $values[] = $filterDateFrom;
    $values[] = $filterDateTo;
} elseif ($filterDateFrom) {
    $where[] = 'l.date >= ?';
    $types .= 's';
    $values[] = $filterDateFrom;
} elseif ($filterDateTo) {
    $where[] = 'l.date <= ?';
    $types .= 's';
    $values[] = $filterDateTo;
}
$whereClause = '';
if (count($where) > 0) {
    $whereClause = ' WHERE ' . implode(' AND ', $where);
    $sql .= $whereClause;
} elseif (!$showAll) {
    // если фильтров нет — принудительно вернём пустой результат
    $sql .= " WHERE 1=0";
}
$sql .= ' ORDER BY l.date, l.lesson_num, l.id';

try {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        // bind_param требует ссылки — подготовим массив ссылок
        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($values); $i++) {
            $bind_name = 'param' . $i;
            $$bind_name = $values[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} catch (mysqli_sql_exception $e) {
    echo '<div style="color:red;padding:12px;">SQL ошибка: ' . h($e->getMessage()) . '</div>';
    exit;
}

// Группируем по дате для удобного вывода
$schedule = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        $schedule[$date][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Расписание</title>
    <link rel="stylesheet" href="styles/theme-red-white.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo">
            <div>
                <div class="title">Расписание</div>
                <div style="font-size:13px;color:#666">Админ панель</div>
            </div>
        </div>
        <div style="margin-left:auto;align-self:center;font-size:13px;color:#222;">
          <a href="rasp.php?logout=1" class="button secondary" style="padding: 8px 14px;">Выйти</a>
        </div>
    </div>

    <div class="card">
        <h2>Расписание<?php echo $weekdayIndex !== null ? ' - ' . h($fullDayNames[$weekdayIndex]) : ''; ?></h2>

        <div style="margin:12px 0 8px;">
            <!-- Админ форма: расположена над кнопками дней -->
            <div id="admin-form" style="display: <?php echo $editRow ? 'block' : 'none'; ?>; max-width:800px;margin:0 auto 10px;padding:12px;border-radius:8px;background:#fff;border:1px solid #ddd;">
                <h3 style="margin-top:0;margin-bottom:12px;color:#333;">Добавить/редактировать занятие</h3>
                <form method="post">
                    <input type="hidden" name="form_type" value="schedule">
                    <input type="hidden" name="id" value="<?php echo h($editRow['id'] ?? 0); ?>">
                    <?php if (!empty($error)): ?>
                        <div style="margin-bottom:12px;padding:10px;border-radius:6px;background:#ffe6e6;border-left:4px solid #c8102e;color:#c00;">
                            <strong>❌ Ошибка:</strong><br>
                            <?php echo nl2br(h($error)); ?>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:160px;">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;">Группа <span style="color:red;">*</span></label>
                            <select name="group_id" required style="width:100%;padding:6px;border-radius:4px;border:1px solid #ddd;">
                                <option value="">— Выберите группу —</option>
                                <?php foreach($groups as $g): ?>
                                    <option value="<?php echo h($g['id']); ?>" <?php echo (isset($editRow['group_id']) && $editRow['group_id']==$g['id']) ? 'selected' : ''; ?>><?php echo h($g['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1;min-width:160px;">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;">Преподаватель</label>
                            <select name="teacher_id" style="width:100%;padding:6px;border-radius:4px;border:1px solid #ddd;">
                                <option value="">— Не выбран —</option>
                                <?php foreach($teachers as $t): ?>
                                    <option value="<?php echo h($t['id']); ?>" <?php echo (isset($editRow['teacher_id']) && $editRow['teacher_id']==$t['id']) ? 'selected' : ''; ?>><?php echo h($t['fullname']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1;min-width:160px;">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;">Дисциплина</label>
                            <select name="discipline_id" style="width:100%;padding:6px;border-radius:4px;border:1px solid #ddd;">
                                <option value="">— Не выбрана —</option>
                                <?php foreach($disciplines as $d): ?>
                                    <option value="<?php echo h($d['id']); ?>" <?php echo (isset($editRow['discipline_id']) && $editRow['discipline_id']==$d['id']) ? 'selected' : ''; ?>><?php echo h($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1;min-width:120px;">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;">Аудитория</label>
                            <select name="room_id" style="width:100%;padding:6px;border-radius:4px;border:1px solid #ddd;">
                                <option value="">— Не выбрана —</option>
                                <?php foreach($rooms as $r): ?>
                                    <option value="<?php echo h($r['id']); ?>" <?php echo (isset($editRow['room_id']) && $editRow['room_id']==$r['id']) ? 'selected' : ''; ?>><?php echo h($r['number']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1;min-width:120px;">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;">Тип занятия</label>
                            <select name="lesson_type_id" style="width:100%;padding:6px;border-radius:4px;border:1px solid #ddd;">
                                <option value="">— Не выбран —</option>
                                <?php foreach($lesson_types as $lt): ?>
                                    <option value="<?php echo h($lt['id']); ?>" <?php echo (isset($editRow['lesson_type_id']) && $editRow['lesson_type_id']==$lt['id']) ? 'selected' : ''; ?>><?php echo h($lt['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1;min-width:140px;">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;">Дата <span style="color:red;">*</span></label>
                            <input type="date" name="date" value="<?php echo h($editRow['date'] ?? ''); ?>" required style="width:100%;padding:6px;border-radius:4px;border:1px solid #ddd;">
                        </div>
                        <div style="flex:1;min-width:100px;">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;">Пара <span style="color:red;">*</span></label>
                            <input type="number" name="lesson_num" value="<?php echo h($editRow['lesson_num'] ?? ''); ?>" placeholder="1-10" min="1" max="10" required style="width:100%;padding:6px;border-radius:4px;border:1px solid #ddd;">
                        </div>
                    </div>
                    <div style="margin-top:12px;display:flex;gap:8px;">
                        <button class="button" type="submit" style="padding:8px 16px;">Сохранить</button>
                        <a href="<?php echo h(build_url()); ?>" class="button secondary" style="padding:8px 16px;">Отмена</a>
                    </div>
                </form>
            </div>

            <form method="get" style="margin-bottom:8px;display:flex;gap:8px;align-items:center;">
                <label style="font-size:13px;color:#444;">Группа:</label>
                <input id="group_name" type="text" name="group_name" value="<?php echo h($filterGroupName); ?>" placeholder="часть названия" style="padding:6px 8px;border-radius:6px;min-width:200px;">
                <!-- преподаватель: переносим текстовый фильтр вниз -->
                <label style="font-size:13px;color:#444;">Дата от:</label>
                <input type="date" name="date_from" value="<?php echo h($filterDateFrom); ?>" style="padding:6px 8px;border-radius:6px;" onchange="this.form.submit()">
                <label style="font-size:13px;color:#444;">до:</label>
                <input type="date" name="date_to" value="<?php echo h($filterDateTo); ?>" style="padding:6px 8px;border-radius:6px;" onchange="this.form.submit()">
                <label style="font-size:13px;color:#444;">преподаватель:</label>
                <input id="teacher_name" type="text" name="teacher_name" value="<?php echo h(isset($_GET['teacher_name'])?$_GET['teacher_name']:''); ?>" placeholder="часть ФИО" style="padding:6px 8px;border-radius:6px;min-width:200px;">
                <?php if ($weekdayIndex !== null): ?>
                    <input type="hidden" name="day" value="<?php echo h($day); ?>">
                <?php endif; ?>
            </form>

            <?php foreach ($dayNames as $i => $short): $d = $i + 1; ?>
                <a href="<?php echo h(build_url(['day' => $d, 'date_from' => ($filterDateFrom?:null), 'date_to' => ($filterDateTo?:null), 'group_name' => ($filterGroupName?:null)])); ?>" class="button <?php echo ($day === $d) ? '' : 'secondary'; ?>" style="min-width:56px;padding:6px 8px;margin-right:6px;">
                    <?php echo h($short); ?>
                </a>
            <?php endforeach; ?>
            <a href="#" id="show-add" class="button" style="margin-left:8px;">Добавить занятие</a>
        </div>

        <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;">
            <div></div>
        </div>

        <?php if ($schedule): ?>
            <?php foreach ($schedule as $date => $rows): ?>
                <h3 style="margin-top:18px;margin-bottom:6px;font-size:16px;color:#b22222;"><?php echo h(date('d.m.Y', strtotime($date))); ?></h3>
                <table class="table table-center" style="width:100%;margin-top:8px;">
                    <colgroup>
                        <col style="width:14%;">
                        <col style="width:8%;">
                        <col style="width:12%;">
                        <col style="width:28%;">
                        <col style="width:18%;">
                        <col style="width:12%;">
                        <col style="width:12%;">
                        <col style="width:6%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Время</th>
                            <th>Пара</th>
                            <th>Группа</th>
                            <th>Дисциплина</th>
                            <th>Преподаватель</th>
                            <th>Аудитория</th>
                            <th>Тип</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row):
                        $num = (int)$row['lesson_num'];
                    ?>
                        <tr>
                            <td><?php echo h($lessonTimes[$num] ?? '-'); ?></td>
                            <td><?php echo h($num); ?></td>
                            <td><?php echo h($row['group_name']); ?></td>
                            <td><?php echo h($row['discipline']); ?></td>
                            <td><?php echo h($row['teacher_name']); ?></td>
                            <td><?php echo h($row['room']); ?></td>
                            <td><?php echo h($row['lesson_type']); ?></td>
                            <td style="white-space:nowrap;">
                                <a href="<?php echo h(build_url(['edit' => $row['id'], 'date_from' => ($filterDateFrom?:null), 'date_to' => ($filterDateTo?:null), 'group_name' => ($filterGroupName?:null)])); ?>" class="button secondary">Ред.</a>
                                <a href="<?php echo h(build_url(['delete' => $row['id'], 'date_from' => ($filterDateFrom?:null), 'date_to' => ($filterDateTo?:null), 'group_name' => ($filterGroupName?:null)])); ?>" class="button" onclick="return confirm('Удалить занятие?');">Удал.</a>
                            </td>
                        </tr>
                <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#666;margin-top:12px;">Записей расписания не найдено.</p>
        <?php endif; ?>

        <div class="form-row" style="margin-top:14px">
            <a href="index.php" class="button secondary">Назад</a>
        </div>
    </div>
</div>
</body>
</html>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var show = document.getElementById('show-add');
    if(!show) return;
    var currentGroup = <?php echo (int)$groupId; ?>;
    show.addEventListener('click', function(e){
        e.preventDefault();
        var form = document.getElementById('admin-form');
        if(!form) return;
        // Сброс полей (для добавления нового)
        var idField = form.querySelector('input[name="id"]');
        if(idField) idField.value = '0';
        var inputs = ['teacher_id','discipline_id','room_id','lesson_type_id','date','lesson_num'];
        inputs.forEach(function(name){
            var el = form.querySelector('[name="'+name+'"]');
            if(!el) return;
            if(el.tagName === 'SELECT' || el.tagName === 'INPUT') el.value = '';
        });
        // Установим группу на текущую в фильтре, если выбрана
        var gsel = form.querySelector('[name="group_id"]');
        if (gsel) gsel.value = currentGroup ? String(currentGroup) : '';
        form.style.display = 'block';
        window.scrollTo(0, form.offsetTop - 20);
    });
});
// debounce автосабмит для фильтров
;(function(){
    function debounce(fn, ms){
        var t;
        return function(){
            var args = arguments, self = this;
            clearTimeout(t);
            t = setTimeout(function(){ fn.apply(self, args); }, ms);
        };
    }
    var g = document.getElementById('group_name');
    var t = document.getElementById('teacher_name');
    function submitForm(){
        var f = g && g.form ? g.form : (t && t.form ? t.form : null);
    console.log('autosubmit called, form found:', !!f);
    if(f) f.submit();
    }
    if(g) g.addEventListener('input', debounce(submitForm, 500));
    if(t) t.addEventListener('input', debounce(submitForm, 500));
})();
</script>

<!-- duplicate admin form removed (kept primary form above buttons) -->
<?php
if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
$conn->close();
?>
