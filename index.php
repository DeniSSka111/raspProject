<?php
session_start();

// Проверка: только админ может быть здесь
if (empty($_SESSION['is_admin'])) {
    header('Location: indexuser.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <title>Админ панель</title>
    <link rel="stylesheet" href="styles/theme-red-white.css" />
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
        <div style="margin-left: auto; display: flex; gap: 8px;">
            <a href="login.php?logout=1" class="button secondary">Выйти</a>
        </div>
    </div>

      <div class="card">
        <h2>Добро пожаловать в админ панель</h2>
        <p>Выберите раздел для управления расписанием</p>
        <div class="form-row" style="margin-top: 12px">
          <a href="rasp.php" class="button">Расписание</a>
          <a href="teachers.php" class="button secondary">Преподаватели</a>
          <a href="groups.php" class="button">Группы</a>
          <a href="disciplines.php" class="button secondary">Дисциплины</a>
          <a href="rooms.php" class="button">Аудитории</a>
          <a href="lesson_types.php" class="button secondary">Типы занятий</a>
          
        </div>
      </div>
    </div>
  </body>
</html>
