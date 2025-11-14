<?php
require_once "db.php";

$sql = "SELECT id, fullname, mts_link FROM teachers ORDER BY fullname";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Преподаватели</title>
        <link rel="stylesheet" href="styles/theme-red-white.css">
</head>
<body>
    <div class="container">
        
    <div class="card">
            <h2>Преподаватели</h2>

            <table class="table table-center compact">
                <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Ссылка (MTS Link)</th>
                    </tr>
                </thead>
                <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row["fullname"]) ?></td>
            <td>
                <?php if (!empty($row["mts_link"])): ?>
                    <a href="<?= htmlspecialchars($row["mts_link"]) ?>" target="_blank" class="button secondary">Перейти</a>
                <?php else: ?>
                    <span style="color:#999;">-</span>
                <?php endif; ?>
            </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <div class="form-row" style="margin-top:14px">
                <a href="indexuser.html" class="button secondary">Назад</a>
            </div>
        </div>
    </div>
</body>
</html>
