<?php
session_start();
include 'db_connect.php';

// Only allow HR/Admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'], ['HR', 'HR Officer', 'Admin'])) {
    http_response_code(403);
    exit('Forbidden');
}

$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$download = isset($_GET['download']) && $_GET['download'] == '1';

$params = [];
$where = "WHERE MONTH(a.work_date) = ? AND YEAR(a.work_date) = ?";
$params[] = $month;
$params[] = $year;
if ($department !== '') {
    $where .= " AND e.department = ?";
    $params[] = $department;
}

$sql = "SELECT e.name, e.department, DATE(a.work_date) as date, TIME(a.clock_in) as clock_in, TIME(a.clock_out) as clock_out
        FROM employee_attendance a
        JOIN employees e ON a.employee_id = e.id
        $where
        ORDER BY e.name, a.work_date";

$stmt = $conn->prepare($sql);
if ($department !== '') {
    $stmt->bind_param('iis', $params[0], $params[1], $params[2]);
} else {
    $stmt->bind_param('ii', $params[0], $params[1]);
}
$stmt->execute();
$stmt->bind_result($name, $dept, $date, $clock_in, $clock_out);

$rows = [];
while ($stmt->fetch()) {
    $rows[] = [
        'name' => $name,
        'department' => $dept,
        'date' => $date,
        'clock_in' => $clock_in,
        'clock_out' => $clock_out
    ];
}
$stmt->close();

if ($download) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Employee', 'Department', 'Date', 'Clock In', 'Clock Out']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['name'], $row['department'], $row['date'], $row['clock_in'], $row['clock_out']]);
    }
    fclose($out);
    exit();
} else {
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
} 