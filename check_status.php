<?php
session_start();
include 'db_connect.php';

// Get user ID from session
$userId = $_SESSION['user_id'];

// Map user_id to employee_id
$stmt = $conn->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($employee_id);
$stmt->fetch();
$stmt->close();

if (!$employee_id) {
    echo json_encode(['isClockedIn' => false, 'error' => 'Employee record not found.']);
    exit();
}

// Check if the user is clocked in
$sql = "SELECT COUNT(*) as count FROM employee_attendance WHERE employee_id = ? AND clock_out IS NULL";
// Use mysqli if that's what db_connect.php provides
if (isset($conn)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    $isClockedIn = $count > 0;
} else {
    // fallback for PDO
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employee_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $isClockedIn = $result['count'] > 0;
}

echo json_encode(['isClockedIn' => $isClockedIn]);
?>