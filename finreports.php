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

// Fetch latest payroll batch for Monthly Payroll Report and Expense/Tax
$latest_batch = $conn->query("SELECT id, period_start, period_end FROM payroll_batches WHERE status IN ('approved','paid','transferred') ORDER BY period_end DESC, created_at DESC LIMIT 1");
$payroll_report = [];
$payroll_period = '';
$expense_totals = ['base_salary'=>0,'bonuses'=>0,'deductions'=>0,'net_pay'=>0];
$tax_summary = [];
if ($latest = $latest_batch->fetch_assoc()) {
    $payroll_period = date('F Y', strtotime($latest['period_end']));
    $stmt = $conn->prepare("SELECT e.name, i.base_salary, i.bonuses, i.deductions, i.net_pay FROM payroll_batch_items i LEFT JOIN employees e ON i.employee_id = e.id WHERE i.batch_id = ?");
    $stmt->bind_param('i', $latest['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $payroll_report[] = $row;
        $expense_totals['base_salary'] += $row['base_salary'];
        $expense_totals['bonuses'] += $row['bonuses'];
        $expense_totals['deductions'] += $row['deductions'];
        $expense_totals['net_pay'] += $row['net_pay'];
        $tax_summary[] = ['name'=>$row['name'], 'deductions'=>$row['deductions']];
    }
    $stmt->close();
}
// Payroll Audit: fetch all batches with totals
$audit_batches = [];
$sql = "SELECT b.id, b.period_start, b.period_end, b.status, SUM(i.net_pay) as total_net_pay FROM payroll_batches b LEFT JOIN payroll_batch_items i ON b.id = i.batch_id GROUP BY b.id ORDER BY b.period_end DESC, b.created_at DESC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $audit_batches[] = $row;
}

// CSV Export logic for each report
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv');
    $filename = $type . '_report_' . date('Ymd') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    if ($type === 'payroll') {
        fputcsv($out, ['Employee', 'Base Salary', 'Bonuses', 'Deductions', 'Net Pay']);
        foreach ($payroll_report as $row) {
            fputcsv($out, [$row['name'], $row['base_salary'], $row['bonuses'], $row['deductions'], $row['net_pay']]);
        }
    } elseif ($type === 'expense') {
        fputcsv($out, ['Total Base Salary', 'Total Bonuses', 'Total Deductions', 'Total Net Pay']);
        fputcsv($out, [
            $expense_totals['base_salary'],
            $expense_totals['bonuses'],
            $expense_totals['deductions'],
            $expense_totals['net_pay']
        ]);
    } elseif ($type === 'tax') {
        fputcsv($out, ['Employee', 'Deductions (Tax)']);
        $total_tax = 0;
        foreach ($tax_summary as $row) {
            fputcsv($out, [$row['name'], $row['deductions']]);
            $total_tax += $row['deductions'];
        }
        fputcsv($out, ['Total', $total_tax]);
    } elseif ($type === 'audit') {
        fputcsv($out, ['Batch ID', 'Period', 'Status', 'Total Net Pay']);
        foreach ($audit_batches as $batch) {
            fputcsv($out, [
                $batch['id'],
                $batch['period_start'] . ' to ' . $batch['period_end'],
                ucfirst($batch['status']),
                $batch['total_net_pay']
            ]);
        }
    }
    fclose($out);
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Finance Reports - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="header">
        <h1>Finance Reports</h1>
        <p>View and generate financial reports</p>
    </div>
    <div class="nav">
        <a href="finance.php">🏠 Dashboard</a>
        <a href="finpayroll.php">💰 Payroll</a>
        <a href="finpayments.php">💸 Payments</a>
        <a href="finreports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
        <div class="card">
            <!-- Main finance reports content goes here -->
            <section class="reports-section">
                <h2>Financial Reports</h2>
                <p class="subtext">Generate and download financial reports</p>
                <div class="reports-grid-2x2">
                    <div class="reports-row">
                        <div class="report-card accent-blue" id="payroll-report-card" style="cursor:pointer;">
                            <i class="far fa-file-alt"></i>
                            <p class="report-title">Monthly Payroll Report</p>
                        </div>
                        <div class="report-card accent-green" id="expense-analysis-card" style="cursor:pointer;">
                            <i class="fas fa-chart-line"></i>
                            <p class="report-title">Expense Analysis</p>
                        </div>
                    </div>
                    <div class="reports-row">
                        <div class="report-card accent-orange" id="tax-summary-card" style="cursor:pointer;">
                            <i class="fas fa-dollar-sign"></i>
                            <p class="report-title">Tax Summary</p>
                        </div>
                        <div class="report-card accent-grey" id="payroll-audit-card" style="cursor:pointer;">
                            <i class="fas fa-calculator"></i>
                            <p class="report-title">Payroll Audit</p>
                        </div>
                    </div>
                </div>
                <!-- Monthly Payroll Report Table (hidden by default, shown on card click) -->
                <div id="payroll-report-table-container" class="report-table-container" style="display:none; margin-top:32px;">
                    <h3>Monthly Payroll Report (<?php echo $payroll_period ? $payroll_period : date('F Y'); ?>)
                        <a href="?export=payroll" class="export-btn" style="float:right; font-size:0.9em; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 8px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">Download CSV</a>
                    </h3>
                    <table class="batch-table accent-blue-header" style="width:100%;margin-top:10px;">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Base Salary</th>
                                <th>Bonuses</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payroll_report as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo number_format($row['base_salary'],2); ?></td>
                                <td><?php echo number_format($row['bonuses'],2); ?></td>
                                <td><?php echo number_format($row['deductions'],2); ?></td>
                                <td><?php echo number_format($row['net_pay'],2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Expense Analysis Table -->
                <div id="expense-analysis-table-container" class="report-table-container" style="display:none; margin-top:32px;">
                    <h3>Expense Analysis (<?php echo $payroll_period ? $payroll_period : date('F Y'); ?>)
                        <a href="?export=expense" class="export-btn" style="float:right; font-size:0.9em; background: linear-gradient(135deg, #43cea2 0%, #27ae60 100%); color: #fff; padding: 8px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">Download CSV</a>
                    </h3>
                    <table class="batch-table accent-green-header" style="width:100%;margin-top:10px;">
                        <thead>
                            <tr>
                                <th>Total Base Salary</th>
                                <th>Total Bonuses</th>
                                <th>Total Deductions</th>
                                <th>Total Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo number_format($expense_totals['base_salary'],2); ?></td>
                                <td><?php echo number_format($expense_totals['bonuses'],2); ?></td>
                                <td><?php echo number_format($expense_totals['deductions'],2); ?></td>
                                <td><?php echo number_format($expense_totals['net_pay'],2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Tax Summary Table -->
                <div id="tax-summary-table-container" class="report-table-container" style="display:none; margin-top:32px;">
                    <h3>Tax Summary (<?php echo $payroll_period ? $payroll_period : date('F Y'); ?>)
                        <a href="?export=tax" class="export-btn" style="float:right; font-size:0.9em; background: linear-gradient(135deg, #fcb69f 0%, #e67e22 100%); color: #fff; padding: 8px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">Download CSV</a>
                    </h3>
                    <table class="batch-table accent-orange-header" style="width:100%;margin-top:10px;">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Deductions (Tax)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $total_tax = 0; foreach ($tax_summary as $row): $total_tax += $row['deductions']; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo number_format($row['deductions'],2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:bold;background:#f7f7fa;">
                                <td>Total</td>
                                <td><?php echo number_format($total_tax,2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Payroll Audit Table -->
                <div id="payroll-audit-table-container" class="report-table-container" style="display:none; margin-top:32px;">
                    <h3>Payroll Audit (All Batches)
                        <a href="?export=audit" class="export-btn" style="float:right; font-size:0.9em; background: linear-gradient(135deg, #a8edea 0%, #e0eafc 100%); color: #333; padding: 8px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">Download CSV</a>
                    </h3>
                    <table class="batch-table accent-grey-header" style="width:100%;margin-top:10px;">
                        <thead>
                            <tr>
                                <th>Batch ID</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Total Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($audit_batches as $batch): ?>
                            <tr>
                                <td><?php echo $batch['id']; ?></td>
                                <td><?php echo htmlspecialchars($batch['period_start']) . ' to ' . htmlspecialchars($batch['period_end']); ?></td>
                                <td><?php echo ucfirst($batch['status']); ?></td>
                                <td><?php echo number_format($batch['total_net_pay'],2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var cardTableMap = [
                {card: 'payroll-report-card', table: 'payroll-report-table-container'},
                {card: 'expense-analysis-card', table: 'expense-analysis-table-container'},
                {card: 'tax-summary-card', table: 'tax-summary-table-container'},
                {card: 'payroll-audit-card', table: 'payroll-audit-table-container'}
            ];
            cardTableMap.forEach(function(pair) {
                var card = document.getElementById(pair.card);
                var table = document.getElementById(pair.table);
                if(card && table) {
                    card.addEventListener('click', function() {
                        // Hide all tables first
                        cardTableMap.forEach(function(other) {
                            var t = document.getElementById(other.table);
                            if(t) t.style.display = 'none';
                        });
                        // Show the selected table
                        table.style.display = 'block';
                    });
                }
            });
        });
    </script>
</body>
</html>