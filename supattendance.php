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

// Get date filter
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$today = date('Y-m-d');

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

// Fetch attendance for selected date
$attendance = [];
if (count($team) > 0) {
    $emp_ids = implode(',', array_map('intval', array_keys($team)));
    $sql = "SELECT employee_id, clock_in, clock_out, hours_worked FROM employee_attendance WHERE DATE(clock_in) = ? AND employee_id IN ($emp_ids) ORDER BY clock_in DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance[$row['employee_id']] = $row;
    }
    $stmt->close();
}

// Calculate attendance statistics
$total_team = count($team);
$present_count = 0;
$late_count = 0;
$absent_count = 0;
$total_hours = 0;

foreach ($team as $emp_id => $emp) {
    if (isset($attendance[$emp_id])) {
        $present_count++;
        $clock_in_time = date('H:i', strtotime($attendance[$emp_id]['clock_in']));
        if ($clock_in_time > '09:00') {
            $late_count++;
        }
        if ($attendance[$emp_id]['hours_worked']) {
            $total_hours += $attendance[$emp_id]['hours_worked']; // Already in hours
        }
    } else {
        $absent_count++;
    }
}

$attendance_percentage = $total_team > 0 ? round(($present_count / $total_team) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supervisor Attendance - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .attendance-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; }
        .stat-value { font-size: 2em; font-weight: bold; margin: 10px 0; }
        .stat-label { color: #666; font-size: 0.9em; }
        .date-filter { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .attendance-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .attendance-table th, .attendance-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .attendance-table th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 500; }
        .status-present { background: #d4edda; color: #155724; }
        .status-late { background: #fff3cd; color: #856404; }
        .status-absent { background: #f8d7da; color: #721c24; }
        .export-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-left: 10px; }
        .export-btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Team Attendance Management</h1>
        <p>Monitor and track your team's attendance</p>
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
        <!-- Date Filter -->
        <div class="date-filter">
            <form method="GET" style="display: flex; align-items: center; gap: 15px;">
                <label for="date" style="font-weight: 600;">Select Date:</label>
                <input type="date" id="date" name="date" value="<?php echo $selected_date; ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                <button type="submit" class="btn-primary">View Attendance</button>
                <a href="supattendance_report.php?date=<?php echo $selected_date; ?>" class="export-btn">📊 Detailed Report</a>
            </form>
        </div>

        <!-- Attendance Statistics -->
        <div class="attendance-stats">
            <div class="stat-card">
                <div class="stat-value" style="color: #28a745;"><?php echo $present_count; ?></div>
                <div class="stat-label">Present Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #ffc107;"><?php echo $late_count; ?></div>
                <div class="stat-label">Late Arrivals</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #dc3545;"><?php echo $absent_count; ?></div>
                <div class="stat-label">Absent</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #17a2b8;"><?php echo $attendance_percentage; ?>%</div>
                <div class="stat-label">Attendance Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #6f42c1;">
                    <?php echo number_format($total_hours, 2); ?> h
                </div>
                <div class="stat-label">Total Hours Worked</div>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="card">
            <h2>Team Attendance for <?php echo date('F j, Y', strtotime($selected_date)); ?></h2>
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours Worked</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team as $emp_id => $emp): 
                        $attendance_record = isset($attendance[$emp_id]) ? $attendance[$emp_id] : null;
                        $status = 'Absent';
                        $status_class = 'status-absent';
                        $clock_in_display = '--';
                        $clock_out_display = '--';
                        $hours_worked = '--';
                        
                        if ($attendance_record) {
                            $clock_in_time = date('H:i', strtotime($attendance_record['clock_in']));
                            $clock_in_display = date('h:i A', strtotime($attendance_record['clock_in']));
                            
                            if ($clock_in_time <= '09:00') {
                                $status = 'Present';
                                $status_class = 'status-present';
                            } else {
                                $status = 'Late';
                                $status_class = 'status-late';
                            }
                            
                            if ($attendance_record['clock_out']) {
                                $clock_out_display = date('h:i A', strtotime($attendance_record['clock_out']));
                            }
                            
                            if ($attendance_record['hours_worked']) {
                                $hours_worked = number_format($attendance_record['hours_worked'], 2) . ' h';
                            }
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($emp['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($emp['department']); ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                        <td><?php echo $clock_in_display; ?></td>
                        <td><?php echo $clock_out_display; ?></td>
                        <td><?php echo $hours_worked; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($team)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>No team members assigned to you.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

  