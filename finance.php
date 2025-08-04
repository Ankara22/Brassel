<?php
session_start();
include 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role_name'];
$username = $_SESSION['username'];

// Fetch Monthly Payroll (latest batch)
$monthly_payroll = 0;
$monthly_period = '';
$latest_batch = $conn->query("SELECT id, period_start, period_end FROM payroll_batches WHERE status IN ('approved','paid','transferred') ORDER BY period_end DESC, created_at DESC LIMIT 1");
if ($latest = $latest_batch->fetch_assoc()) {
    $monthly_period = date('F Y', strtotime($latest['period_end']));
    $stmt = $conn->prepare("SELECT SUM(net_pay) as total FROM payroll_batch_items WHERE batch_id = ?");
    $stmt->bind_param('i', $latest['id']);
    $stmt->execute();
    $stmt->bind_result($monthly_payroll);
    $stmt->fetch();
    $stmt->close();
}
// Fetch YTD Expenses (sum of all payrolls this year)
$ytd_expenses = 0;
$year = date('Y');
$stmt = $conn->prepare("SELECT SUM(i.net_pay) as total FROM payroll_batch_items i JOIN payroll_batches b ON i.batch_id = b.id WHERE YEAR(b.period_end) = ? AND b.status IN ('approved','paid','transferred')");
$stmt->bind_param('i', $year);
$stmt->execute();
$stmt->bind_result($ytd_expenses);
$stmt->fetch();
$stmt->close();
// Fetch Pending Payments (batches marked as 'paid' but not 'transferred')
$pending_payments = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM payroll_batches WHERE status = 'paid'");
if ($row = $res->fetch_assoc()) {
    $pending_payments = $row['cnt'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Finance Dashboard - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="header">
        <h1>Finance Dashboard</h1>
        <p>Manage payments, payroll, and financial reports</p>
    </div>
    <div class="nav">
        <a href="finance.php">🏠 Dashboard</a>
        <a href="finpayroll.php">💰 Payroll</a>
        <a href="finpayments.php">💸 Payments</a>
        <a href="finreports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
        <div class="metrics-flex-row">
            <div class="card card-metric metric-blue">
                <i class="fa-solid fa-sack-dollar"></i>
                <h3>Monthly Payroll</h3>
                <p class="metric-value">$<?php echo number_format($monthly_payroll,2); ?></p>
                <p class="subtext"><?php echo $monthly_period ? $monthly_period : date('F Y'); ?></p>
            </div>
            <div class="card card-metric metric-green">
                <i class="fas fa-chart-line"></i>
                <h3>YTD Expenses</h3>
                <p class="metric-value">$<?php echo number_format($ytd_expenses,2); ?></p>
                <p class="subtext"><?php echo date('Y'); ?></p>
            </div>
            <div class="card card-metric metric-orange">
                <i class="far fa-file-alt"></i>
                <h3>Pending Payments</h3>
                <p class="metric-value"><?php echo $pending_payments; ?></p>
                <p class="subtext">Awaiting processing</p>
            </div>
        </div>
    </div>
</body>
</html>