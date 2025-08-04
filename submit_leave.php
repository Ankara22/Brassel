<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';

    // Basic validation
    if ($leave_type && $start_date && $end_date && $reason) {
        $stmt = $conn->prepare('INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, "pending")');
        $stmt->bind_param('issss', $user_id, $leave_type, $start_date, $end_date, $reason);
        if ($stmt->execute()) {
            // Log leave request
            $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
            $ip = $_SERVER['REMOTE_ADDR'];
            $action = 'leave_request';
            $details = 'Leave request submitted: ' . $leave_type . ' from ' . $start_date . ' to ' . $end_date;
            $log_stmt = $conn->prepare('INSERT INTO activity_log (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)');
            $log_stmt->bind_param('issss', $user_id, $username, $action, $details, $ip);
            $log_stmt->execute();
            $log_stmt->close();
            $stmt->close();
            header('Location: leaverequest.php?success=1');
            exit();
        } else {
            $stmt->close();
            header('Location: leaverequest.php?error=1');
            exit();
        }
    } else {
        header('Location: leaverequest.php?error=1');
        exit();
    }
} else {
    header('Location: leaverequest.php');
    exit();
} 