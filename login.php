<?php
session_start();

// Выход
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: indexuser.html');
    exit;
}

$login_error = '';

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['form_type']) && $_POST['form_type'] === 'login') {
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';

    // Администратор
    if ($login === 'admin' && $password === 'admin123') {
        $_SESSION['is_admin'] = true;
        $_SESSION['user_type'] = 'admin';
        header('Location: index.php');
        exit;
    } 
    // Обычный пользователь
    elseif ($login === 'user' && $password === 'user123') {
        $_SESSION['is_admin'] = false;
        $_SESSION['user_type'] = 'user';
        header('Location: indexuser.html');
        exit;
    }
    // Ошибка входа
    else {
        $login_error = 'Неверный логин или пароль';
    }
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Расписание</title>
    <link rel="stylesheet" href="styles/login.css">
</head>
<body class="login-page">
<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <h1>Расписание</h1>
        </div>
        
        <?php if ($login_error): ?>
            <div class="error-message">
                ❌ <?php echo h($login_error); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="login-form">
            <input type="hidden" name="form_type" value="login">
            
            <div class="form-group">
                <label for="login">Логин</label>
                <input 
                    type="text" 
                    id="login"
                    name="login" 
                    placeholder="Введите логин" 
                    required 
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Пароль</label>
                <input 
                    type="password" 
                    id="password"
                    name="password" 
                    placeholder="Введите пароль" 
                    required
                >
            </div>

            <button type="submit" class="login-btn">Войти</button>
        </form>

        <div class="login-info">
            <strong>Демо учётные данные</strong>
            <div style="margin-top: 12px;">
                <p style="margin: 6px 0;"><strong>Администратор:</strong></p>
                <p>Логин: <code>admin</code></p>
                <p>Пароль: <code>admin123</code></p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
