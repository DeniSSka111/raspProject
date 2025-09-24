<?php
$host = "localhost";
$user = "root";       
$pass = "";            
$db   = "rasp";        

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}
$conn->set_charset("utf8");
?>
