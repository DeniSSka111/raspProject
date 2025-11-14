<?php
require_once __DIR__ . '/db.php'; // db.php должен создавать $conn

// Отладка (удалите/измените в проде)
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "Ошибка: подключение к БД не найдено. Проверьте db.php";
    exit;
}

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

$groupId = 0;
$groups = [];
try {
    $gr = $conn->query("SELECT id, name FROM `groups` ORDER BY name");
    if ($gr) {
        while ($g = $gr->fetch_assoc()) $groups[] = $g;
    }
} catch (mysqli_sql_exception $e) {
    // не фатально — оставим пустой список
}
// Список преподавателей для фильтра
$teachers = [];
try {
    $tr = $conn->query("SELECT id, fullname FROM teachers ORDER BY fullname");
    if ($tr) while ($t = $tr->fetch_assoc()) $teachers[] = $t;
} catch (mysqli_sql_exception $e) {
    // игнорируем
}

// preserve date range filter params for redirects/links
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : (isset($_POST['date_from']) ? $_POST['date_from'] : '');
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : (isset($_POST['date_to']) ? $_POST['date_to'] : '');
// текстовый фильтр по названию группы (используется, если конкретная группа не выбрана)
$filterGroupName = isset($_GET['group_name']) ? trim($_GET['group_name']) : (isset($_POST['group_name']) ? trim($_POST['group_name']) : '');
// текстовый фильтр по ФИО преподавателя
$filterTeacherName = isset($_GET['teacher_name']) ? trim($_GET['teacher_name']) : (isset($_POST['teacher_name']) ? trim($_POST['teacher_name']) : '');
$urlParamsArr = [];
if ($filterDateFrom) $urlParamsArr[] = 'date_from=' . urlencode($filterDateFrom);
if ($filterDateTo) $urlParamsArr[] = 'date_to=' . urlencode($filterDateTo);
if ($filterGroupName) $urlParamsArr[] = 'group_name=' . urlencode($filterGroupName);
if ($filterTeacherName) $urlParamsArr[] = 'teacher_name=' . urlencode($filterTeacherName);
// фильтр по преподавателю
$teacherFilter = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;
if ($teacherFilter > 0) $urlParamsArr[] = 'teacher=' . $teacherFilter;
// фильтр по преподавателю
$teacherFilter = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;
if ($teacherFilter > 0) $urlParamsArr[] = 'teacher=' . $teacherFilter;
$queryStart = $urlParamsArr ? '?' . implode('&', $urlParamsArr) : '';
$querySuffix = $urlParamsArr ? '&' . implode('&', $urlParamsArr) : '';

// Helper: build url preserving current GET params and applying overrides
function build_url(array $overrides = []){
    $params = $_GET;
    // remove action params that shouldn't persist
    unset($params['edit'], $params['delete']);
    foreach($overrides as $k => $v){
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $qs = http_build_query($params);
    return 'raspuser.php' . ($qs ? ('?' . $qs) : '');
}

// Инициализация переменных для фильтров
$groupId = 0;
$teacherFilter = 0;

// Запрос данных о расписании
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
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Расписание<?php echo $weekdayIndex !== null ? ' - ' . h($fullDayNames[$weekdayIndex]) : ''; ?></h2>

        <div style="margin:12px 0 8px;">
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
                <a href="<?php echo h(build_url(['day' => $d, 'date_from' => ($filterDateFrom?:null), 'date_to' => ($filterDateTo?:null), 'group_name' => ($filterGroupName?:null), 'teacher_name' => ($filterTeacherName?:null)])); ?>" class="button <?php echo ($day === $d) ? '' : 'secondary'; ?>" style="min-width:56px;padding:6px 8px;margin-right:6px;">
                    <?php echo h($short); ?>
                </a>
            <?php endforeach; ?>
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
                        <col style="width:20%;">
                        <col style="width:20%;">
                        <col style="width:16%;">
                        <col style="width:16%;">
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
                        </tr>
                <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#666;margin-top:12px;">Записей расписания не найдено.</p>
        <?php endif; ?>

        <div class="form-row" style="margin-top:14px">
            <a href="indexuser.html" class="button secondary">Назад</a>
        </div>
    </div>
</div>
</body>
</html>
<script src="scripts/autosearch.js"></script>
<?php
if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
$conn->close();
?>
