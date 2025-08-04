<?php
include 'db_connect.php';
include 'security_config.php';

// Check authentication and role
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role_name'];
$username = $_SESSION['username'];

// Only allow Admin, Finance Officer, and HR Officer to access reports
if (!in_array($role, ['Admin', 'Finance Officer', 'HR Officer'])) {
    header("Location: dashboard.php");
    exit();
}

// Get report type from URL parameter
$report_type = $_GET['type'] ?? 'overview';

// Initialize variables
$report_data = [];
$chart_data = [];
$summary_stats = [];

// Generate different reports based on type
switch ($report_type) {
    case 'payroll_summary':
        generatePayrollSummary($conn, $report_data, $chart_data, $summary_stats);
        break;
    case 'department_analytics':
        generateDepartmentAnalytics($conn, $report_data, $chart_data, $summary_stats);
        break;
    case 'budget_vs_actual':
        generateBudgetVsActual($conn, $report_data, $chart_data, $summary_stats);
        break;
    case 'attendance_analysis':
        generateAttendanceAnalysis($conn, $report_data, $chart_data, $summary_stats);
        break;
    case 'tax_summary':
        generateTaxSummary($conn, $report_data, $chart_data, $summary_stats);
        break;
    case 'audit_trail':
        generateAuditTrail($conn, $report_data, $chart_data, $summary_stats);
        break;
    default:
        generateOverviewReport($conn, $report_data, $chart_data, $summary_stats);
        break;
}

// Log report access
logActivity($_SESSION['user_id'], $username, 'report_access', "Accessed $report_type report", $conn);

function generateOverviewReport($conn, &$report_data, &$chart_data, &$summary_stats) {
    // Total employees
    $sql = "SELECT COUNT(*) as total_employees FROM employees WHERE is_active = 1";
    $result = $conn->query($sql);
    $summary_stats['total_employees'] = $result->fetch_assoc()['total_employees'];
    
    // Total payroll this month
    $sql = "SELECT SUM(net_pay) as total_payroll FROM payroll_batch_items pbi 
            JOIN payroll_batches pb ON pbi.batch_id = pb.id 
            WHERE MONTH(pb.created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(pb.created_at) = YEAR(CURRENT_DATE())
            AND pb.status IN ('approved', 'paid')";
    $result = $conn->query($sql);
    $summary_stats['total_payroll'] = $result->fetch_assoc()['total_payroll'] ?? 0;
    
    // Average salary
    $sql = "SELECT AVG(base_salary) as avg_salary FROM employees WHERE is_active = 1";
    $result = $conn->query($sql);
    $summary_stats['avg_salary'] = $result->fetch_assoc()['avg_salary'] ?? 0;
    
    // Recent activity
    $sql = "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10";
    $result = $conn->query($sql);
    $report_data['recent_activity'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Monthly payroll trend (last 6 months)
    $sql = "SELECT DATE_FORMAT(pb.created_at, '%Y-%m') as month, 
            SUM(pbi.net_pay) as total_payroll 
            FROM payroll_batches pb 
            JOIN payroll_batch_items pbi ON pb.id = pbi.batch_id 
            WHERE pb.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
            AND pb.status IN ('approved', 'paid')
            GROUP BY DATE_FORMAT(pb.created_at, '%Y-%m')
            ORDER BY month";
    $result = $conn->query($sql);
    $chart_data['payroll_trend'] = $result->fetch_all(MYSQLI_ASSOC);
}

function generatePayrollSummary($conn, &$report_data, &$chart_data, &$summary_stats) {
    // Get current month payroll data
    $sql = "SELECT e.first_name, e.last_name, e.base_salary, 
            pbi.bonuses, pbi.deductions, pbi.net_pay,
            pb.status, pb.created_at
            FROM payroll_batch_items pbi
            JOIN employees e ON pbi.employee_id = e.id
            JOIN payroll_batches pb ON pbi.batch_id = pb.id
            WHERE MONTH(pb.created_at) = MONTH(CURRENT_DATE())
            AND YEAR(pb.created_at) = YEAR(CURRENT_DATE())
            ORDER BY pbi.net_pay DESC";
    $result = $conn->query($sql);
    $report_data['payroll_details'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate summary statistics
    $total_salary = 0;
    $total_bonuses = 0;
    $total_deductions = 0;
    $total_net_pay = 0;
    
    foreach ($report_data['payroll_details'] as $row) {
        $total_salary += $row['base_salary'];
        $total_bonuses += $row['bonuses'];
        $total_deductions += $row['deductions'];
        $total_net_pay += $row['net_pay'];
    }
    
    $summary_stats['total_salary'] = $total_salary;
    $summary_stats['total_bonuses'] = $total_bonuses;
    $summary_stats['total_deductions'] = $total_deductions;
    $summary_stats['total_net_pay'] = $total_net_pay;
    $summary_stats['avg_salary'] = count($report_data['payroll_details']) > 0 ? $total_salary / count($report_data['payroll_details']) : 0;
}

function generateDepartmentAnalytics($conn, &$report_data, &$chart_data, &$summary_stats) {
    // Department-wise salary analysis
    $sql = "SELECT e.department, 
            COUNT(e.id) as employee_count,
            AVG(e.base_salary) as avg_salary,
            SUM(e.base_salary) as total_salary
            FROM employees e
            WHERE e.is_active = 1
            GROUP BY e.department
            ORDER BY total_salary DESC";
    $result = $conn->query($sql);
    $report_data['department_analytics'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Department payroll for current month
    $sql = "SELECT e.department,
            SUM(pbi.net_pay) as total_payroll,
            AVG(pbi.net_pay) as avg_payroll
            FROM payroll_batch_items pbi
            JOIN employees e ON pbi.employee_id = e.id
            JOIN payroll_batches pb ON pbi.batch_id = pb.id
            WHERE MONTH(pb.created_at) = MONTH(CURRENT_DATE())
            AND YEAR(pb.created_at) = YEAR(CURRENT_DATE())
            AND pb.status IN ('approved', 'paid')
            GROUP BY e.department
            ORDER BY total_payroll DESC";
    $result = $conn->query($sql);
    $chart_data['department_payroll'] = $result->fetch_all(MYSQLI_ASSOC);
}

function generateBudgetVsActual($conn, &$report_data, &$chart_data, &$summary_stats) {
    // This would typically compare against budget data
    // For now, we'll show current month vs previous month
    $sql = "SELECT 
            'Current Month' as period,
            SUM(pbi.net_pay) as total_payroll,
            COUNT(DISTINCT pbi.employee_id) as employee_count
            FROM payroll_batch_items pbi
            JOIN payroll_batches pb ON pbi.batch_id = pb.id
            WHERE MONTH(pb.created_at) = MONTH(CURRENT_DATE())
            AND YEAR(pb.created_at) = YEAR(CURRENT_DATE())
            AND pb.status IN ('approved', 'paid')
            UNION ALL
            SELECT 
            'Previous Month' as period,
            SUM(pbi.net_pay) as total_payroll,
            COUNT(DISTINCT pbi.employee_id) as employee_count
            FROM payroll_batch_items pbi
            JOIN payroll_batches pb ON pbi.batch_id = pb.id
            WHERE MONTH(pb.created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
            AND YEAR(pb.created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
            AND pb.status IN ('approved', 'paid')";
    $result = $conn->query($sql);
    $report_data['budget_comparison'] = $result->fetch_all(MYSQLI_ASSOC);
}

function generateAttendanceAnalysis($conn, &$report_data, &$chart_data, &$summary_stats) {
    // Attendance summary for current month
    $sql = "SELECT e.first_name, e.last_name, e.department,
            COUNT(a.id) as days_present,
            AVG(TIME_TO_SEC(a.clock_out) - TIME_TO_SEC(a.clock_in)) / 3600 as avg_hours
            FROM employees e
            LEFT JOIN employee_attendance a ON e.id = a.employee_id
            AND MONTH(a.work_date) = MONTH(CURRENT_DATE())
            AND YEAR(a.work_date) = YEAR(CURRENT_DATE())
            WHERE e.is_active = 1
            GROUP BY e.id, e.first_name, e.last_name, e.department
            ORDER BY days_present DESC";
    $result = $conn->query($sql);
    $report_data['attendance_summary'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Department attendance averages
    $sql = "SELECT e.department,
            AVG(attendance_count.days_present) as avg_days_present,
            AVG(attendance_count.avg_hours) as avg_hours_per_day
            FROM employees e
            LEFT JOIN (
                SELECT a.employee_id,
                COUNT(a.id) as days_present,
                AVG(TIME_TO_SEC(a.clock_out) - TIME_TO_SEC(a.clock_in)) / 3600 as avg_hours
                FROM employee_attendance a
                WHERE MONTH(a.work_date) = MONTH(CURRENT_DATE())
                AND YEAR(a.work_date) = YEAR(CURRENT_DATE())
                GROUP BY a.employee_id
            ) attendance_count ON e.id = attendance_count.employee_id
            WHERE e.is_active = 1
            GROUP BY e.department";
    $result = $conn->query($sql);
    $chart_data['department_attendance'] = $result->fetch_all(MYSQLI_ASSOC);
}

function generateTaxSummary($conn, &$report_data, &$chart_data, &$summary_stats) {
    // Tax calculations (simplified - in real system would have tax tables)
    $sql = "SELECT e.first_name, e.last_name, e.base_salary,
            pbi.deductions as tax_deductions,
            (e.base_salary * 0.3) as estimated_tax,
            pbi.net_pay
            FROM employees e
            LEFT JOIN payroll_batch_items pbi ON e.id = pbi.employee_id
            LEFT JOIN payroll_batches pb ON pbi.batch_id = pb.id
            WHERE MONTH(pb.created_at) = MONTH(CURRENT_DATE())
            AND YEAR(pb.created_at) = YEAR(CURRENT_DATE())
            AND pb.status IN ('approved', 'paid')
            ORDER BY e.base_salary DESC";
    $result = $conn->query($sql);
    $report_data['tax_summary'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate total tax
    $total_tax = 0;
    foreach ($report_data['tax_summary'] as $row) {
        $total_tax += $row['tax_deductions'];
    }
    $summary_stats['total_tax'] = $total_tax;
}

function generateAuditTrail($conn, &$report_data, &$chart_data, &$summary_stats) {
    // Recent system activities
    $sql = "SELECT al.username, al.action, al.details, al.ip_address, al.created_at
            FROM activity_log al
            ORDER BY al.created_at DESC
            LIMIT 50";
    $result = $conn->query($sql);
    $report_data['audit_trail'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Activity summary by type
    $sql = "SELECT action, COUNT(*) as count
            FROM activity_log
            WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
            GROUP BY action
            ORDER BY count DESC";
    $result = $conn->query($sql);
    $chart_data['activity_summary'] = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Reports - HR System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .nav-tabs {
            display: flex;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .nav-tab {
            padding: 15px 25px;
            text-decoration: none;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .nav-tab:hover {
            background-color: #f8f9fa;
            color: #667eea;
        }
        .nav-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background-color: #f8f9fa;
        }
        .report-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        .report-content {
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
        }
        .chart-container {
            margin: 20px 0;
            height: 400px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .data-table tr:hover {
            background-color: #f8f9fa;
        }
        .export-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
        }
        .export-btn:hover {
            background: #218838;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        .summary-card h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .summary-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Enhanced Reports Dashboard</h1>
        <p>Comprehensive analytics and reporting system</p>
    </div>

    <div class="container">
        <div class="nav-tabs">
            <a href="?type=overview" class="nav-tab <?php echo $report_type === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="?type=payroll_summary" class="nav-tab <?php echo $report_type === 'payroll_summary' ? 'active' : ''; ?>">Payroll Summary</a>
            <a href="?type=department_analytics" class="nav-tab <?php echo $report_type === 'department_analytics' ? 'active' : ''; ?>">Department Analytics</a>
            <a href="?type=budget_vs_actual" class="nav-tab <?php echo $report_type === 'budget_vs_actual' ? 'active' : ''; ?>">Budget vs Actual</a>
            <a href="?type=attendance_analysis" class="nav-tab <?php echo $report_type === 'attendance_analysis' ? 'active' : ''; ?>">Attendance Analysis</a>
            <a href="?type=tax_summary" class="nav-tab <?php echo $report_type === 'tax_summary' ? 'active' : ''; ?>">Tax Summary</a>
            <a href="?type=audit_trail" class="nav-tab <?php echo $report_type === 'audit_trail' ? 'active' : ''; ?>">Audit Trail</a>
        </div>

        <?php if ($report_type === 'overview'): ?>
            <div class="report-section">
                <div class="report-header">
                    <h2>System Overview</h2>
                </div>
                <div class="report-content">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Total Employees</h3>
                            <div class="value"><?php echo number_format($summary_stats['total_employees']); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Monthly Payroll</h3>
                            <div class="value">$<?php echo number_format($summary_stats['total_payroll'], 2); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Average Salary</h3>
                            <div class="value">$<?php echo number_format($summary_stats['avg_salary'], 2); ?></div>
                        </div>
                    </div>

                    <div class="chart-container">
                        <canvas id="payrollTrendChart"></canvas>
                    </div>

                    <h3>Recent Activity</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['recent_activity'] as $activity): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['details']); ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($report_type === 'payroll_summary'): ?>
            <div class="report-section">
                <div class="report-header">
                    <h2>Payroll Summary - <?php echo date('F Y'); ?></h2>
                </div>
                <div class="report-content">
                    <div class="summary-cards">
                        <div class="summary-card">
                            <h3>Total Salary</h3>
                            <div class="value">$<?php echo number_format($summary_stats['total_salary'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <h3>Total Bonuses</h3>
                            <div class="value">$<?php echo number_format($summary_stats['total_bonuses'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <h3>Total Deductions</h3>
                            <div class="value">$<?php echo number_format($summary_stats['total_deductions'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <h3>Net Pay</h3>
                            <div class="value">$<?php echo number_format($summary_stats['total_net_pay'], 2); ?></div>
                        </div>
                    </div>

                    <button class="export-btn" onclick="exportToCSV()">Export to CSV</button>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Base Salary</th>
                                <th>Bonuses</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['payroll_details'] as $payroll): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></td>
                                    <td>$<?php echo number_format($payroll['base_salary'], 2); ?></td>
                                    <td>$<?php echo number_format($payroll['bonuses'], 2); ?></td>
                                    <td>$<?php echo number_format($payroll['deductions'], 2); ?></td>
                                    <td>$<?php echo number_format($payroll['net_pay'], 2); ?></td>
                                    <td><?php echo ucfirst($payroll['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($report_type === 'department_analytics'): ?>
            <div class="report-section">
                <div class="report-header">
                    <h2>Department Analytics</h2>
                </div>
                <div class="report-content">
                    <div class="chart-container">
                        <canvas id="departmentChart"></canvas>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Employees</th>
                                <th>Avg Salary</th>
                                <th>Total Salary</th>
                                <th>Current Month Payroll</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['department_analytics'] as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                    <td><?php echo $dept['employee_count']; ?></td>
                                    <td>$<?php echo number_format($dept['avg_salary'], 2); ?></td>
                                    <td>$<?php echo number_format($dept['total_salary'], 2); ?></td>
                                    <td>$<?php echo number_format($dept['total_payroll'] ?? 0, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($report_type === 'audit_trail'): ?>
            <div class="report-section">
                <div class="report-header">
                    <h2>System Audit Trail</h2>
                </div>
                <div class="report-content">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['audit_trail'] as $audit): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($audit['username']); ?></td>
                                    <td><?php echo htmlspecialchars($audit['action']); ?></td>
                                    <td><?php echo htmlspecialchars($audit['details']); ?></td>
                                    <td><?php echo htmlspecialchars($audit['ip_address']); ?></td>
                                    <td><?php echo date('M j, Y H:i:s', strtotime($audit['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Initialize charts based on report type
        <?php if ($report_type === 'overview' && !empty($chart_data['payroll_trend'])): ?>
        const ctx = document.getElementById('payrollTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($chart_data['payroll_trend'], 'month')); ?>,
                datasets: [{
                    label: 'Monthly Payroll',
                    data: <?php echo json_encode(array_column($chart_data['payroll_trend'], 'total_payroll')); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Payroll Trend (Last 6 Months)'
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($report_type === 'department_analytics' && !empty($chart_data['department_payroll'])): ?>
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($chart_data['department_payroll'], 'department')); ?>,
                datasets: [{
                    label: 'Department Payroll',
                    data: <?php echo json_encode(array_column($chart_data['department_payroll'], 'total_payroll')); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Department Payroll Distribution'
                    }
                }
            }
        });
        <?php endif; ?>

        function exportToCSV() {
            // Implementation for CSV export
            alert('Export functionality will be implemented');
        }
    </script>
</body>
</html> 