<?php
session_start();
header('Content-Type: application/json');

$response = [
    'is_admin' => !empty($_SESSION['is_admin']),
    'user_type' => $_SESSION['user_type'] ?? null
];

echo json_encode($response);
?>
