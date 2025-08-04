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
$supervisor_id = $_SESSION['user_id'];

// Get date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fetch team members
$team = [];
$sql = "SELECT e.id, e.name, e.department, e.user_id FROM employees e WHERE e.supervisor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $supervisor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $team[$row['id']] = $row; // Use employee_id as key
}
$stmt->close();

// Fetch attendance data for date range
$attendance_data = [];
$summary_stats = [];

if (count($team) > 0) {
    $emp_ids = implode(',', array_map('intval', array_keys($team)));
    // Get detailed attendance records
    $sql = "SELECT a.*, e.name, e.department 
            FROM employee_attendance a 
            JOIN employees e ON a.employee_id = e.id 
            WHERE a.employee_id IN ($emp_ids) 
            AND DATE(a.clock_in) BETWEEN ? AND ? 
            ORDER BY a.clock_in DESC, e.name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_data[] = $row;
    }
    $stmt->close();
    // Calculate summary statistics
    $total_days = (strtotime($end_date) - strtotime($start_date)) / (60*60*24) + 1;
    $total_employees = count($team);
    $total_possible_attendance = $total_days * $total_employees;
    $total_present = 0;
    $total_late = 0;
    $total_absent = $total_possible_attendance - count($attendance_data);
    $total_hours = 0;
    foreach ($attendance_data as $record) {
        $clock_in_time = date('H:i', strtotime($record['clock_in']));
        if ($clock_in_time <= '09:00') {
            $total_present++;
        } else {
            $total_late++;
        }
        if ($record['hours_worked']) {
            $total_hours += $record['hours_worked']; // Already in hours
        }
    }
    $summary_stats = [
        'total_days' => $total_days,
        'total_employees' => $total_employees,
        'total_present' => $total_present,
        'total_late' => $total_late,
        'total_absent' => $total_absent,
        'total_hours' => round($total_hours, 1),
        'attendance_rate' => $total_possible_attendance > 0 ? round(($total_present / $total_possible_attendance) * 100, 1) : 0
    ];
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $start_date . '_to_' . $end_date . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee', 'Department', 'Date', 'Clock In', 'Clock Out', 'Hours Worked', 'Status']);
    
    foreach ($attendance_data as $record) {
        $clock_in_time = date('H:i', strtotime($record['clock_in']));
        $status = $clock_in_time <= '09:00' ? 'Present' : 'Late';
        
        $hours_worked = '';
        if ($record['hours_worked']) {
            $hours_worked = number_format($record['hours_worked'], 2) . ' h';
        }
        
        fputcsv($output, [
            $record['name'],
            $record['department'],
            $record['work_date'],
            date('h:i A', strtotime($record['clock_in'])),
            $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : '--',
            $hours_worked,
            $status
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
    <title>Attendance Report - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .report-header { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .date-range-form { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .summary-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; }
        .stat-value { font-size: 2em; font-weight: bold; margin: 10px 0; }
        .stat-label { color: #666; font-size: 0.9em; }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .report-table th, .report-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .report-table th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 500; }
        .status-present { background: #d4edda; color: #155724; }
        .status-late { background: #fff3cd; color: #856404; }
        .export-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-left: 10px; }
        .export-btn:hover { opacity: 0.9; }
        .no-data { text-align: center; padding: 40px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Detailed Attendance Report</h1>
        <p>Comprehensive attendance analysis for your team</p>
    </div>
    <div class="nav">
        <a href="supervisor.php">🏠 Dashboard</a>
        <a href="supattendance.php">⏰ Attendance</a>
        <a href="supattendance_report.php">📊 Reports</a>
        <a href="supbonus.php">💰 Bonuses</a>
        <a href="supleave.php">🗓️ Leave</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
        <!-- Date Range Filter -->
        <div class="report-header">
            <form method="GET" class="date-range-form">
                <label for="start_date" style="font-weight: 600;">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                
                <label for="end_date" style="font-weight: 600;">End Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                
                <button type="submit" class="btn-primary">Generate Report</button>
                <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=csv" class="export-btn">📥 Export CSV</a>
            </form>
        </div>

        <?php if (!empty($summary_stats)): ?>
        <!-- Summary Statistics -->
        <div class="summary-stats">
            <div class="stat-card">
                <div class="stat-value" style="color: #28a745;"><?php echo $summary_stats['total_present']; ?></div>
                <div class="stat-label">Present Days</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #ffc107;"><?php echo $summary_stats['total_late']; ?></div>
                <div class="stat-label">Late Arrivals</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #dc3545;"><?php echo $summary_stats['total_absent']; ?></div>
                <div class="stat-label">Absent Days</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #17a2b8;"><?php echo $summary_stats['attendance_rate']; ?>%</div>
                <div class="stat-label">Attendance Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #6f42c1;">
                    <?php echo number_format($summary_stats['total_hours'], 2); ?> h
                </div>
                <div class="stat-label">Total Hours Worked</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #fd7e14;"><?php echo $summary_stats['total_days']; ?></div>
                <div class="stat-label">Days in Period</div>
            </div>
        </div>

        <!-- Detailed Report Table -->
        <div class="card" style="background: #f8faff; border-radius: 16px; box-shadow: 0 4px 24px rgba(102,126,234,0.10); padding: 32px 24px; margin-top: 32px;">
            <h2 style="color: #4a00e0; margin-bottom: 18px;">Detailed Attendance Report (<?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)</h2>
            <table class="report-table" style="background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(102,126,234,0.06);">
                <thead>
                    <tr style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); color: #fff;">
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours Worked</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowAlt = false; foreach ($attendance_data as $record): 
                        $clock_in_time = date('H:i', strtotime($record['clock_in']));
                        $status = $clock_in_time <= '09:00' ? 'Present' : 'Late';
                        $status_class = $clock_in_time <= '09:00' ? 'status-present' : 'status-late';
                        $hours_worked = '';
                        if ($record['hours_worked']) {
                            $hours_worked = number_format($record['hours_worked'], 2) . ' h';
                        }
                    ?>
                    <tr style="background: <?php echo $rowAlt ? '#f0f4ff' : '#fff'; ?>; transition: background 0.2s;">
                        <td><strong><?php echo htmlspecialchars($record['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($record['department']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($record['work_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($record['clock_in'])); ?></td>
                        <td><?php echo $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : '--'; ?></td>
                        <td><?php echo $hours_worked ?: '--'; ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>" style="font-weight:600; padding:6px 16px; font-size:1em; letter-spacing:0.5px; <?php echo $status_class==='status-present' ? 'background:#34d399;color:#fff;' : 'background:#fbbf24;color:#fff;'; ?>"><?php echo $status; ?></span></td>
                    </tr>
                    <?php $rowAlt = !$rowAlt; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="no-data">
            <p>No attendance data found for the selected date range.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 