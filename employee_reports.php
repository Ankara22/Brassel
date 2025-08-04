<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all payroll records for this employee
$sql = "SELECT * FROM employee_payroll WHERE employee_id = ? ORDER BY pay_period_end DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$payslips = [];
$total_gross = $total_paye = $total_nhif = $total_nssf1 = $total_nssf2 = $total_net = 0;
while ($row = $result->fetch_assoc()) {
    $payslips[] = $row;
    $total_gross += $row['gross_pay'];
    $total_paye += $row['paye'];
    $total_nhif += $row['nhif'];
    $total_nssf1 += $row['nssf_tier1'];
    $total_nssf2 += $row['nssf_tier2'];
    $total_net += $row['net_pay'];
}
$stmt->close();

if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payroll_history.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Pay Period', 'Gross Pay', 'Bonuses', 'PAYE', 'NHIF', 'NSSF I', 'NSSF II', 'Net Pay']);
    foreach ($payslips as $row) {
        fputcsv($output, [
            $row['pay_period_start'] . ' to ' . $row['pay_period_end'],
            $row['gross_pay'],
            $row['bonus'],
            $row['paye'],
            $row['nhif'],
            $row['nssf_tier1'],
            $row['nssf_tier2'],
            $row['net_pay'],
        ]);
    }
    fclose($output);
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Reports - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .reports-container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 32px; }
        .reports-header { text-align: center; margin-bottom: 24px; }
        .reports-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .reports-table th, .reports-table td { padding: 10px 8px; border-bottom: 1px solid #eee; text-align: left; }
        .reports-table th { background: #f6f8fa; }
        .summary-table { width: 100%; margin-bottom: 24px; }
        .summary-table th, .summary-table td { padding: 8px 8px; text-align: left; }
        .download-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; transition: background 0.2s; }
        .download-btn:hover { background: #667eea; }
        .no-records { text-align: center; color: #888; margin: 40px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Payroll & Tax Reports</h1>
        <p>View and download your payroll and tax summaries</p>
    </div>
    <div class="nav">
        <a href="employee.php">🏠 Dashboard</a>
        <a href="attendance.php">⏰ Attendance</a>
        <a href="leaverequest.php">🗓️ Leave</a>
        <a href="payslip.php">💰 Payslip</a>
        <a href="employee_reports.php">📄 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container reports-container">
        <div class="reports-header">
            <h2>Your Payroll History</h2>
            <p>All your payslips and tax deductions for the year</p>
            <?php if (count($payslips) > 0): ?>
            <a href="employee_reports.php?download=csv" class="download-btn" style="margin-bottom:12px; display:inline-block;">Download CSV</a>
            <?php endif; ?>
        </div>
        <?php if (count($payslips) > 0): ?>
        <table class="summary-table">
            <tr><th>Total Gross Pay:</th><td>KES <?php echo number_format($total_gross,2); ?></td></tr>
            <tr><th>Total PAYE:</th><td>KES <?php echo number_format($total_paye,2); ?></td></tr>
            <tr><th>Total NHIF:</th><td>KES <?php echo number_format($total_nhif,2); ?></td></tr>
            <tr><th>Total NSSF Tier I:</th><td>KES <?php echo number_format($total_nssf1,2); ?></td></tr>
            <tr><th>Total NSSF Tier II:</th><td>KES <?php echo number_format($total_nssf2,2); ?></td></tr>
            <tr><th><strong>Total Net Pay:</strong></th><td><strong>KES <?php echo number_format($total_net,2); ?></strong></td></tr>
        </table>
        <table class="reports-table">
            <tr>
                <th>Pay Period</th>
                <th>Gross Pay</th>
                <th>Bonuses</th>
                <th>PAYE</th>
                <th>NHIF</th>
                <th>NSSF I</th>
                <th>NSSF II</th>
                <th>Net Pay</th>
            </tr>
            <?php foreach ($payslips as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['pay_period_start']) . ' to ' . htmlspecialchars($row['pay_period_end']); ?></td>
                <td><?php echo number_format($row['gross_pay'],2); ?></td>
                <td><?php echo number_format($row['bonus'],2); ?></td>
                <td><?php echo number_format($row['paye'],2); ?></td>
                <td><?php echo number_format($row['nhif'],2); ?></td>
                <td><?php echo number_format($row['nssf_tier1'],2); ?></td>
                <td><?php echo number_format($row['nssf_tier2'],2); ?></td>
                <td><?php echo number_format($row['net_pay'],2); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
            <div class="no-records">No payroll records found.</div>
        <?php endif; ?>
    </div>
</body>
</html> 