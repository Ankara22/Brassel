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
    echo '';
    exit();
}

// Fetch recent attendance records (latest 5)
$sql = "SELECT clock_in, clock_out, hours_worked FROM employee_attendance WHERE employee_id = ? ORDER BY clock_in DESC LIMIT 5";

$output = '';

if (isset($conn)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $stmt->bind_result($clock_in, $clock_out, $duration);
    while ($stmt->fetch()) {
        $clockInObj = new DateTime($clock_in);
        $clockOutObj = $clock_out ? new DateTime($clock_out) : null;
        $date = $clockInObj->format('D, M d');
        $timeIn = $clockInObj->format('H:i');
        $timeOut = $clockOutObj ? $clockOutObj->format('H:i') : 'N/A';
        if ($duration !== null) {
            $hoursWorked = number_format($duration, 2) . ' h';
        } else {
            $hoursWorked = 'N/A';
        }
        $output .= '<tr>';
        $output .= '<td>' . $date . '</td>';
        $output .= '<td>' . $timeIn . '</td>';
        $output .= '<td>' . $timeOut . '</td>';
        $output .= '<td>' . $hoursWorked . '</td>';
        $output .= '</tr>';
    }
    $stmt->close();
} else {
    // fallback for PDO
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employee_id]);
    $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($attendanceRecords as $record) {
        $clockInObj = new DateTime($record['clock_in']);
        $clockOutObj = $record['clock_out'] ? new DateTime($record['clock_out']) : null;
        $date = $clockInObj->format('D, M d');
        $timeIn = $clockInObj->format('H:i');
        $timeOut = $clockOutObj ? $clockOutObj->format('H:i') : 'N/A';
        if ($record['duration'] !== null) {
            $hoursWorked = number_format($record['duration'], 2) . ' h';
        } else {
            $hoursWorked = 'N/A';
        }
        $output .= '<tr>';
        $output .= '<td>' . $date . '</td>';
        $output .= '<td>' . $timeIn . '</td>';
        $output .= '<td>' . $timeOut . '</td>';
        $output .= '<td>' . $hoursWorked . '</td>';
        $output .= '</tr>';
    }
}
echo $output;
?>