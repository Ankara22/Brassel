<?php
session_start();
include 'db_connect.php';

echo "<pre>";
if (!isset($_SESSION['user_id'])) {
    die('Not logged in.');
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
    die('Employee record not found.');
}
// Find open attendance records
$stmt = $conn->prepare('SELECT id, clock_in FROM employee_attendance WHERE employee_id = ? AND clock_out IS NULL');
$stmt->bind_param('i', $employee_id);
$stmt->execute();
$stmt->bind_result($attendance_id, $clock_in);

$open_records = [];
while ($stmt->fetch()) {
    $open_records[] = ['id' => $attendance_id, 'clock_in' => $clock_in];
}
$stmt->close();

if (count($open_records) === 0) {
    echo "No open attendance records found for your account.\n";
} else {
    foreach ($open_records as $rec) {
        $now = date('Y-m-d H:i:s');
        $duration = strtotime($now) - strtotime($rec['clock_in']);
        $stmt2 = $conn->prepare('UPDATE employee_attendance SET clock_out = ?, hours_worked = ? WHERE id = ?');
        $stmt2->bind_param('sii', $now, $duration, $rec['id']);
        $stmt2->execute();
        $stmt2->close();
        echo "Closed open attendance record ID {$rec['id']}.\n";
    }
}
echo "</pre>";