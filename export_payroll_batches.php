<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Not authorized');
}

$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';

// Fetch all payroll batches
$sql = "SELECT b.*, u.username AS created_by_name, u2.username AS approved_by_name FROM payroll_batches b LEFT JOIN users u ON b.created_by = u.user_id LEFT JOIN users u2 ON b.approved_by = u2.user_id ORDER BY b.created_at DESC";
$result = $conn->query($sql);

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$filename = 'payroll_batches_' . date('Ymd_His');
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    $sep = "\t";
} else {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $sep = ",";
}

// Output header
$header = ['Batch ID', 'Period', 'Status', 'Created By', 'Created At', 'Approved By', 'Approved/Paid/Transferred At'];
echo implode($sep, $header) . "\n";
foreach ($rows as $batch) {
    $line = [
        '#' . $batch['id'],
        $batch['period_start'] . ' to ' . $batch['period_end'],
        ucfirst($batch['status']),
        $batch['created_by_name'],
        $batch['created_at'],
        $batch['approved_by_name'] ? $batch['approved_by_name'] : '-',
        $batch['approved_at'] ? $batch['approved_at'] : '-',
    ];
    echo implode($sep, $line) . "\n";
}
exit; 