<?php
session_start();
include 'db_connect.php';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $action = 'logout';
    $details = 'User logged out';
    $log_stmt = $conn->prepare('INSERT INTO activity_log (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    $log_stmt->bind_param('issss', $user_id, $username, $action, $details, $ip);
    $log_stmt->execute();
    $log_stmt->close();
}
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>

