<?php
session_start();
include 'db_connect.php';

// CSV Export logic
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $type . '_report.csv"');
    $out = fopen('php://output', 'w');
    if ($type === 'employees') {
        fputcsv($out, ['Name', 'Department', 'Designation', 'Status']);
        $sql = "SELECT name, department, designation, status FROM employees";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            fputcsv($out, [$row['name'], $row['department'], $row['designation'], isset($row['status']) && $row['status'] === 'inactive' ? 'Inactive' : 'Active']);
        }
    } elseif ($type === 'attendance') {
        fputcsv($out, ['Name', 'Department', 'Clock In', 'Clock Out']);
        $today = date('Y-m-d');
        $sql = "SELECT e.name, e.department, a.clock_in, a.clock_out FROM employee_attendance a JOIN employees e ON a.employee_id = e.id WHERE a.work_date = ? ORDER BY a.clock_in DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $stmt->bind_result($name, $department, $clock_in, $clock_out);
        while ($stmt->fetch()) {
            fputcsv($out, [$name, $department, $clock_in, $clock_out]);
        }
        $stmt->close();
    } elseif ($type === 'leaves') {
        fputcsv($out, ['User', 'Type', 'Start', 'End', 'Reason', 'Status']);
        $sql = "SELECT u.username, lr.leave_type, lr.start_date, lr.end_date, lr.reason, lr.status FROM leave_requests lr JOIN users u ON lr.user_id = u.user_id ORDER BY lr.submitted_at DESC";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            fputcsv($out, [$row['username'], $row['leave_type'], $row['start_date'], $row['end_date'], $row['reason'], ucfirst(str_replace('_',' ',$row['status']))]);
        }
    } elseif ($type === 'payroll') {
        fputcsv($out, ['Name', 'Base Salary', 'Bonuses', 'Deductions', 'Net Pay']);
        $latest_batch = $conn->query("SELECT id FROM payroll_batches ORDER BY period_end DESC, created_at DESC LIMIT 1");
        if ($latest = $latest_batch->fetch_assoc()) {
            $stmt = $conn->prepare("SELECT e.name, i.base_salary, i.bonuses, i.deductions, i.net_pay FROM payroll_batch_items i LEFT JOIN employees e ON i.employee_id = e.id WHERE i.batch_id = ?");
            $stmt->bind_param('i', $latest['id']);
            $stmt->execute();
            $stmt->bind_result($name, $base_salary, $bonuses, $deductions, $net_pay);
            while ($stmt->fetch()) {
                fputcsv($out, [$name, $base_salary, $bonuses, $deductions, $net_pay]);
            }
            $stmt->close();
        }
    }
    fclose($out);
    exit();
}

// 1. Total Employees
$total_employees = 0;
$added_this_month = 0;
$sql = "SELECT COUNT(*) as total FROM employees";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $total_employees = $row['total'];
}
$first_of_month = date('Y-m-01');
$sql = "SELECT COUNT(*) as added FROM employees WHERE DATE(created_at) >= ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $first_of_month);
$stmt->execute();
$stmt->bind_result($added_this_month);
$stmt->fetch();
$stmt->close();

// 2. Pending Leaves (awaiting HR approval)
$pending_leaves = 0;
$sql = "SELECT COUNT(*) as pending FROM leave_requests WHERE status = 'supervisor_approved'";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $pending_leaves = $row['pending'];
}

// 3. Present Today
$present_today = 0;
$attendance_percentage = 0;
$today = date('Y-m-d');
$sql = "SELECT COUNT(DISTINCT employee_id) as present FROM employee_attendance WHERE DATE(clock_in) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $today);
$stmt->execute();
$stmt->bind_result($present_today);
$stmt->fetch();
$stmt->close();
if ($total_employees > 0) {
    $attendance_percentage = round(($present_today / $total_employees) * 100, 1);
}

// 4. New Hires (this quarter)
$new_hires = 0;
$month = date('n');
$year = date('Y');
$quarter_start_month = (floor(($month - 1) / 3) * 3) + 1;
$quarter_start = date('Y-m-d', strtotime("$year-$quarter_start_month-01"));
$sql = "SELECT COUNT(*) as hires FROM employees WHERE DATE(created_at) >= ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $quarter_start);
$stmt->execute();
$stmt->bind_result($new_hires);
$stmt->fetch();
$stmt->close();

// Employees Table
$employees = [];
$sql = "SELECT * FROM employees";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

// Attendance Table (today)
$attendance = [];
$sql = "SELECT e.name, e.department, a.clock_in, a.clock_out FROM employee_attendance a JOIN employees e ON a.employee_id = e.id WHERE DATE(a.clock_in) = ? ORDER BY a.clock_in DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $today);
$stmt->execute();
$stmt->bind_result($name, $department, $clock_in, $clock_out);
while ($stmt->fetch()) {
    $attendance[] = [
        'name' => $name,
        'department' => $department,
        'clock_in' => $clock_in,
        'clock_out' => $clock_out
    ];
}
$stmt->close();

// Leave Requests Table
$leave_requests = [];
$sql = "SELECT lr.id, u.username, lr.leave_type, lr.start_date, lr.end_date, lr.reason, lr.status FROM leave_requests lr JOIN users u ON lr.user_id = u.user_id ORDER BY lr.submitted_at DESC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $leave_requests[] = $row;
}

// Payroll Table (latest batch)
$payroll = [];
$latest_batch = $conn->query("SELECT id, period_start, period_end FROM payroll_batches ORDER BY period_end DESC, created_at DESC LIMIT 1");
if ($latest = $latest_batch->fetch_assoc()) {
    $stmt = $conn->prepare("SELECT e.name, i.base_salary, i.bonuses, i.deductions, i.net_pay FROM payroll_batch_items i LEFT JOIN employees e ON i.employee_id = e.id WHERE i.batch_id = ?");
    $stmt->bind_param('i', $latest['id']);
    $stmt->execute();
    $stmt->bind_result($name, $base_salary, $bonuses, $deductions, $net_pay);
    while ($stmt->fetch()) {
        $payroll[] = [
            'name' => $name,
            'base_salary' => $base_salary,
            'bonuses' => $bonuses,
            'deductions' => $deductions,
            'net_pay' => $net_pay
        ];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Reports - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .metrics-container { display: flex; gap: 32px; margin-bottom: 32px; }
        .metric-card { flex: 1; background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%); border-radius: 16px; box-shadow: 0 4px 24px rgba(102,126,234,0.08); padding: 32px; text-align: center; }
        .metric-card h3 { font-size: 1.2em; color: #764ba2; margin-bottom: 10px; }
        .metric-card .value { font-size: 2.2em; font-weight: 700; margin-bottom: 6px; }
        .metric-card .subtext { color: #888; font-size: 1em; }
        .metric-card .orange { color: #e67e22; }
        .metric-card .green { color: #27ae60; }
        .metric-card .purple { color: #764ba2; }
        .report-table { width: 100%; border-collapse: collapse; margin-bottom: 32px; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        .report-table th, .report-table td { border: 1px solid #d1d5db; padding: 14px 12px; text-align: left; }
        .report-table th { background: #f6f8fa; color: #333; font-weight: 700; font-size: 1.05em; }
        .report-table tr:nth-child(even) { background: #f9f9ff; }
        .report-table tr:hover { background: #eaf6ff; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HR Reports</h1>
        <p>View and generate HR analytics and reports</p>
    </div>
    <div class="nav">
        <a href="hrofficer.php">🏠 Dashboard</a>
        <a href="assign_supervisors.php">🧑‍💼 Assign Supervisors</a>
        <a href="hremployee.php">👥 Employees</a>
        <a href="hrattendance.php">⏰ Attendance</a>
        <a href="hrleave.php">🗓️ Leave</a>
        <a href="hrpayroll.php">💰 Payroll</a>
        <a href="hrbonus.php">🎁 Bonuses</a>
        <a href="hrreports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
        <div class="metrics-container">
            <div class="metric-card">
                <h3>Total Employees</h3>
                <p class="value"><?php echo $total_employees; ?></p>
                <p class="subtext">+<?php echo $added_this_month; ?> this month</p>
            </div>
            <div class="metric-card">
                <h3>Pending Leaves</h3>
                <p class="value orange"><?php echo $pending_leaves; ?></p>
                <p class="subtext">Awaiting approval</p>
            </div>
            <div class="metric-card">
                <h3>Present Today</h3>
                <p class="value green"><?php echo $present_today; ?></p>
                <p class="subtext"><?php echo $attendance_percentage; ?>% attendance</p>
            </div>
            <div class="metric-card">
                <h3>New Hires</h3>
                <p class="value purple"><?php echo $new_hires; ?></p>
                <p class="subtext">This quarter</p>
            </div>
        </div>
        <div class="card">
            <h2>Employee List <a href="?export=employees" class="export-btn" style="float:right; font-size:0.9em; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 8px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">Download CSV</a></h2>
            <table class="report-table">
                <thead>
                    <tr><th>Name</th><th>Department</th><th>Designation</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['name']); ?></td>
                        <td><?php echo htmlspecialchars($emp['department']); ?></td>
                        <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                        <td><?php echo (isset($emp['status']) && $emp['status'] === 'inactive') ? 'Inactive' : 'Active'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Today's Attendance Table -->
        <div class="report-section" style="margin-bottom: 32px;">
          <h2 style="margin-bottom: 12px;">Today's Attendance</h2>
          <a href="hrreports.php?export=attendance" class="export-btn" style="float:right; margin-top:-8px; margin-right:12px; background:#764ba2; color:#fff; padding: 10px 28px; border-radius: 8px; font-size: 1.1em; font-weight: 600; text-decoration: none;">Download CSV</a>
          <table class="report-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Department</th>
                <th>Clock In</th>
                <th>Clock Out</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($attendance) > 0): ?>
                <?php foreach ($attendance as $row): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                    <td><?php echo $row['clock_in'] ? date('h:i A', strtotime($row['clock_in'])) : '--'; ?></td>
                    <td><?php echo $row['clock_out'] ? date('h:i A', strtotime($row['clock_out'])) : '--'; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" style="text-align:center; color:#888;">No attendance records for today.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card">
            <h2>Leave Requests <a href="?export=leaves" class="export-btn" style="float:right; font-size:0.9em; background: linear-gradient(135deg, #e67e22 0%, #fcb69f 100%); color: #fff; padding: 8px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">Download CSV</a></h2>
            <table class="report-table">
                <thead>
                    <tr><th>User</th><th>Type</th><th>Start</th><th>End</th><th>Reason</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($leave_requests as $req): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($req['username']); ?></td>
                        <td><?php echo htmlspecialchars($req['leave_type']); ?></td>
                        <td><?php echo htmlspecialchars($req['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($req['end_date']); ?></td>
                        <td><?php echo htmlspecialchars($req['reason']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$req['status']))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <h2>Latest Payroll <a href="?export=payroll" class="export-btn" style="float:right; font-size:0.9em; background: linear-gradient(135deg, #764ba2 0%, #667eea 100%); color: #fff; padding: 8px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">Download CSV</a></h2>
            <table class="report-table">
                <thead>
                    <tr><th>Name</th><th>Base Salary</th><th>Bonuses</th><th>Deductions</th><th>Net Pay</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($payroll as $row): ?>
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
    </div>
</body>
</html> 