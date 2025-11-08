<?php
require_once "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'lesson_types') {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO lesson_types (name) VALUES (?)");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $stmt->close();
        header('Location: lesson_types.php');
        exit;
    } else {
        $error = 'Введите название';
    }
}
// Обработка удаления
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($delId > 0) {
        $dstmt = $conn->prepare('DELETE FROM lesson_types WHERE id = ?');
        $dstmt->bind_param('i', $delId);
        $dstmt->execute();
        $dstmt->close();
    }
    header('Location: lesson_types.php');
    exit;
}

$sql = "SELECT id, name FROM lesson_types";
$result = $conn->query($sql);
// Показывать форму, если в GET есть show_form или есть ошибка
$show_form = isset($_GET['show_form']) || !empty($error ?? null);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Типы занятий</title>
        <link rel="stylesheet" href="styles/theme-red-white.css">
</head>
<body class="centered">
    <div class="container">
    <div class="card" style="max-width:700px;margin:0 auto 12px;text-align:center;">
            <h2>Типы занятий</h2>
                    <div class="form-row" style="margin-bottom:12px">
                        <a href="?show_form=1" class="button" id="add-button">Добавить тип занятия</a>
                    </div>

                    <form method="post" id="add-form" class="card" style="max-width:700px;margin:0 auto 12px;text-align:left;display: <?= $show_form ? 'block' : 'none' ?>">
                        <input type="hidden" name="form_type" value="lesson_types">
                        <?php if (!empty($error)): ?><div class="notice" style="margin-bottom:8px"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                        <div class="form-row">
                            <input class="input" name="name" placeholder="Название" required>
                        </div>
                        <div class="form-row" style="margin-top:10px">
                            <button class="button" type="submit">Добавить</button>
                            <a href="lesson_types.php" class="button secondary cancel-button">Отмена</a>
                        </div>
                    </form>

                    <table class="table table-center compact">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row["id"] ?></td>
                        <td><?= htmlspecialchars($row["name"]) ?></td>
                        <td style="white-space:nowrap"><a href="?delete=<?= $row["id"] ?>" class="button" onclick="return confirm('Удалить запись #<?= $row["id"] ?> ?')">Удал.</a></td>
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

