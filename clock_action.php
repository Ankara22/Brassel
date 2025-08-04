<?php
session_start();
date_default_timezone_set('Africa/Nairobi'); // Set to your local timezone
header('Content-Type: application/json');
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Map user_id to employee_id
$stmt = $conn->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($employee_id);
$stmt->fetch();
$stmt->close();

if (!$employee_id) {
    echo json_encode(['success' => false, 'error' => 'Employee record not found.']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'clock_in') {
    // Check if already clocked in (no open attendance record)
    $stmt = $conn->prepare('SELECT id FROM employee_attendance WHERE employee_id = ? AND clock_out IS NULL');
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Already clocked in']);
        exit();
    }
    $stmt->close();
    // Insert new attendance record
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('INSERT INTO employee_attendance (employee_id, clock_in) VALUES (?, ?)');
    $stmt->bind_param('is', $employee_id, $now);
    $success = $stmt->execute();
    $stmt->close();
    // Log clock in
    if ($success) {
        $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt = $conn->prepare('INSERT INTO activity_log (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)');
        $action_str = 'clock_in';
        $details = 'User clocked in at ' . $now;
        $log_stmt->bind_param('issss', $user_id, $username, $action_str, $details, $ip);
        $log_stmt->execute();
        $log_stmt->close();
    }
    echo json_encode(['success' => $success, 'status' => 'clocked_in', 'clock_in' => $now]);
    exit();
}

if ($action === 'clock_out') {
    // Find open attendance record
    $stmt = $conn->prepare('SELECT id, clock_in FROM employee_attendance WHERE employee_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $stmt->bind_result($attendance_id, $clock_in);
    if ($stmt->fetch()) {
        $stmt->close();
        $now = date('Y-m-d H:i:s');
        $duration = strtotime($now) - strtotime($clock_in); // in seconds
        $hours_worked = round(($duration / 3600), 2); // convert to hours
        $stmt2 = $conn->prepare('UPDATE employee_attendance SET clock_out = ?, hours_worked = ? WHERE id = ?');
        $stmt2->bind_param('sdi', $now, $hours_worked, $attendance_id);
        $success = $stmt2->execute();
        if (!$success) {
            echo json_encode(['success' => false, 'error' => $stmt2->error]);
            $stmt2->close();
            exit();
        }
        $stmt2->close();
        // Log clock out
        if ($success) {
            $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt = $conn->prepare('INSERT INTO activity_log (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)');
            $action_str = 'clock_out';
            $details = 'User clocked out at ' . $now;
            $log_stmt->bind_param('issss', $user_id, $username, $action_str, $details, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        }
        echo json_encode(['success' => $success, 'status' => 'clocked_out', 'clock_out' => $now, 'hours_worked' => $hours_worked]);
        exit();
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'No open attendance record']);
        exit();
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit(); 