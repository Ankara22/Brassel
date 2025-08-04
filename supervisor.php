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

// 1. Team Members (direct reports under this supervisor)
$team_members = 0;
$sql = "SELECT COUNT(*) as team_count FROM employees WHERE supervisor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $supervisor_id);
$stmt->execute();
$stmt->bind_result($team_members);
$stmt->fetch();
$stmt->close();

// 2. Present Today (team members who clocked in today)
$present_today = 0;
$attendance_percentage = 0;
$today = date('Y-m-d');
$sql = "SELECT COUNT(DISTINCT a.employee_id) as present FROM employee_attendance a 
        JOIN employees e ON a.employee_id = e.user_id 
        WHERE DATE(a.work_date) = ? AND e.supervisor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $today, $supervisor_id);
$stmt->execute();
$stmt->bind_result($present_today);
$stmt->fetch();
$stmt->close();

if ($team_members > 0) {
    $attendance_percentage = round(($present_today / $team_members) * 100, 1);
}

// 3. Leave Requests (pending supervisor approval)
$pending_leaves = 0;
$sql = "SELECT COUNT(*) as pending FROM leave_requests lr 
        JOIN employees e ON lr.user_id = e.user_id 
        WHERE lr.status = 'pending' AND e.supervisor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $supervisor_id);
$stmt->execute();
$stmt->bind_result($pending_leaves);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supervisor Dashboard - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>Supervisor Dashboard</h1>
        <p>Manage team attendance, leave, and performance</p>
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
        <div class="card">
            <!-- Main supervisor content goes here -->
            <div class="overview-section" style="display: flex; gap: 32px; justify-content: center; margin-top: 32px;">
                <div class="metric-card" style="flex:1; background: linear-gradient(135deg, #f8fafc 0%, #a1c4fd 100%); border-radius: 16px; box-shadow: 0 4px 24px rgba(102,126,234,0.08); padding: 32px; text-align: center;">
                    <i class="fas fa-users" style="font-size: 2em; color: #764ba2;"></i>
                    <h3 style="margin: 12px 0 6px 0; font-size: 1.2em; color: #764ba2;">Team Members</h3>
                    <p class="metric-value" style="font-size: 2.2em; font-weight: 700; color: #333; margin-bottom: 6px;"><?php echo $team_members; ?></p>
                    <p class="subtext" style="color: #888; font-size: 1em;">Direct reports</p>
                </div>
                <div class="metric-card" style="flex:1; background: linear-gradient(135deg, #e0eafc 0%, #a8edea 100%); border-radius: 16px; box-shadow: 0 4px 24px rgba(39,174,96,0.08); padding: 32px; text-align: center;">
                    <i class="far fa-clock" style="font-size: 2em; color: #27ae60;"></i>
                    <h3 style="margin: 12px 0 6px 0; font-size: 1.2em; color: #27ae60;">Present Today</h3>
                    <p class="metric-value" style="font-size: 2.2em; font-weight: 700; color: #333; margin-bottom: 6px;"><?php echo $present_today; ?></p>
                    <p class="subtext" style="color: #888; font-size: 1em;"><?php echo $attendance_percentage; ?>% attendance</p>
                </div>
                <div class="metric-card" style="flex:1; background: linear-gradient(135deg, #fdf6e3 0%, #fcb69f 100%); border-radius: 16px; box-shadow: 0 4px 24px rgba(230,126,34,0.08); padding: 32px; text-align: center;">
                    <i class="far fa-calendar-alt" style="font-size: 2em; color: #e67e22;"></i>
                    <h3 style="margin: 12px 0 6px 0; font-size: 1.2em; color: #e67e22;">Leave Requests</h3>
                    <p class="metric-value" style="font-size: 2.2em; font-weight: 700; color: #333; margin-bottom: 6px;"><?php echo $pending_leaves; ?></p>
                    <p class="subtext" style="color: #888; font-size: 1em;">Pending approval</p>
                </div>
            </div>
        </div>
    </div>
    <style>
        @media (max-width: 900px) {
            .overview-section { flex-direction: column !important; gap: 20px !important; }
        }
    </style>
</body>
</html>