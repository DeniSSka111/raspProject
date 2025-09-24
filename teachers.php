<?php
require_once "db.php";

// Обработка отправки формы добавления преподавателя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'teachers') {
    $fullname = trim($_POST['fullname'] ?? '');
    $mts_link = trim($_POST['mts_link'] ?? '');
    if ($fullname !== '') {
        $stmt = $conn->prepare("INSERT INTO teachers (fullname, mts_link) VALUES (?, ?)");
        $stmt->bind_param('ss', $fullname, $mts_link);
        $stmt->execute();
        $stmt->close();
        header('Location: teachers.php');
        exit;
    } else {
        $error = 'Введите ФИО';
    }
}
$sql = "SELECT id, fullname, mts_link FROM teachers";
$result = $conn->query($sql);
// Показывать форму, если в GET есть show_form или есть ошибка
$show_form = isset($_GET['show_form']) || !empty($error ?? null);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Преподаватели</title>
        <link rel="stylesheet" href="styles/theme-red-white.css">
</head>
<body class="centered">
    <div class="container">
        <div class="card">
            <h2>Преподаватели</h2>
                    <div class="form-row" style="margin-bottom:12px">
                        <a href="?show_form=1" class="button" id="add-button">Добавить преподавателя</a>
                    </div>

                    <!-- Форма всегда рендерится, но по умолчанию скрыта при отсутствии show_form -->
                    <form method="post" id="add-form" class="card" style="max-width:700px;margin:0 auto 12px;text-align:left;display: <?= $show_form ? 'block' : 'none' ?>">
                        <input type="hidden" name="form_type" value="teachers">
                        <?php if (!empty($error)): ?><div class="notice" style="margin-bottom:8px"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                        <div class="form-row">
                            <input class="input" name="fullname" placeholder="ФИО" required>
                            <input class="input" name="mts_link" placeholder="MTS Link">
                        </div>
                        <div class="form-row" style="margin-top:10px">
                            <button class="button" type="submit">Добавить</button>
                            <a href="teachers.php" class="button secondary cancel-button">Отмена</a>
                        </div>
                    </form>


                    <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Ссылка (MTS Link)</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row["id"] ?></td>
                        <td><?= $row["fullname"] ?></td>
                        <td><a href="<?= $row["mts_link"] ?>" target="_blank" class="button secondary">Перейти</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <div class="form-row" style="margin-top:14px">
                <a href="index.html" class="button secondary">Назад</a>
            </div>
        </div>
    </div>
    <script src="scripts/form-toggle.js"></script>
</body>
</html>
