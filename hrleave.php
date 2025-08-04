<?php
session_start();
include 'db_connect.php';

// Redirect to login if not authenticated or not HR
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'HR Officer') {
    header('Location: login.php');
    exit();
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $status = ($action === 'approve') ? 'hr_approved' : 'hr_rejected';
    $hr_id = $_SESSION['user_id'];
    $stmt = $conn->prepare('UPDATE leave_requests SET status = ?, hr_id = ? WHERE id = ?');
    $stmt->bind_param('sii', $status, $hr_id, $request_id);
    $stmt->execute();
    $stmt->close();

    // If approved, update leave balance
    if ($status === 'hr_approved') {
        // Get leave type, user_id, start_date, end_date
        $info_stmt = $conn->prepare('SELECT user_id, leave_type, start_date, end_date FROM leave_requests WHERE id = ?');
        $info_stmt->bind_param('i', $request_id);
        $info_stmt->execute();
        $info_stmt->bind_result($user_id, $leave_type, $start_date, $end_date);
        if ($info_stmt->fetch()) {
            $days = (strtotime($end_date) - strtotime($start_date)) / (60*60*24) + 1;
            $type = strtolower(trim($leave_type));
            $column = '';
            if ($type === 'annual' || $type === 'annual leave') $column = 'annual_leave';
            if ($type === 'sick' || $type === 'sick leave') $column = 'sick_leave';
            if ($type === 'personal' || $type === 'personal leave') $column = 'personal_leave';
            $info_stmt->close(); // Close before running the next query
            if ($column) {
                $update_stmt = $conn->prepare("UPDATE employees SET $column = GREATEST($column - ?, 0) WHERE user_id = ?");
                $update_stmt->bind_param('ii', $days, $user_id);
                $update_stmt->execute();
                if ($update_stmt->error) {
                    error_log('Leave balance update error: ' . $update_stmt->error);
                }
                $update_stmt->close();
            }
        } else {
            $info_stmt->close();
        }
    }
    // Log HR action
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $log_action = ($action === 'approve') ? 'leave_approved_hr' : 'leave_rejected_hr';
    $details = (($action === 'approve') ? 'HR approved' : 'HR rejected') . ' leave request ID ' . $request_id;
    $log_stmt = $conn->prepare('INSERT INTO activity_log (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    $log_stmt->bind_param('issss', $user_id, $username, $log_action, $details, $ip);
    $log_stmt->execute();
    $log_stmt->close();
    header('Location: hrleave.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Leave - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>HR Leave Management</h1>
        <p>Manage and review employee leave requests</p>
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
        <div class="card">
            <!-- Main HR leave content goes here -->
            <div class="icon-container">
              <i class="far fa-calendar-alt"></i>
            </div>
            <h2>Supervisor-Approved Leave Requests</h2>
            <?php
            $sql = "SELECT lr.id, u.username, lr.leave_type, lr.start_date, lr.end_date, lr.reason FROM leave_requests lr JOIN users u ON lr.user_id = u.user_id WHERE lr.status = 'supervisor_approved' ORDER BY lr.submitted_at ASC";
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                echo '<div class="history-item" style="background:#f8faff; border-radius:14px; box-shadow:0 2px 12px rgba(102,126,234,0.08); margin-bottom:22px; padding:22px 26px; border-left:6px solid #4a00e0; position:relative;">';
                echo '<div class="status-badge forwarded" style="background:#6366f1; color:#fff; border-radius:6px; padding:4px 14px; font-size:0.98em; position:absolute; top:18px; right:18px;">Forwarded</div>';
                echo '<h3 style="color:#4a00e0; margin-bottom:6px;">' . htmlspecialchars(ucwords(str_replace('_', ' ', $row['leave_type']))) . ' - ' . htmlspecialchars($row['username']) . '</h3>';
                echo '<p style="font-weight:500; color:#222; margin-bottom:2px;">' . htmlspecialchars(date('n/j/Y', strtotime($row['start_date']))) . ' - ' . htmlspecialchars(date('n/j/Y', strtotime($row['end_date']))) . '</p>';
                echo '<p style="color:#555; margin-bottom:12px;">' . htmlspecialchars($row['reason']) . '</p>';
                echo '<form method="POST" style="display:inline-block;margin-right:10px;">';
                echo '<input type="hidden" name="request_id" value="' . intval($row['id']) . '">';
                echo '<button type="submit" name="action" value="approve" class="btn-primary" style="background:#34d399; color:#fff; border:none; border-radius:6px; padding:8px 18px; font-weight:600; margin-right:8px;">Approve</button>';
                echo '</form>';
                echo '<form method="POST" style="display:inline-block;">';
                echo '<input type="hidden" name="request_id" value="' . intval($row['id']) . '">';
                echo '<button type="submit" name="action" value="reject" class="btn-danger" style="background:#f87171; color:#fff; border:none; border-radius:6px; padding:8px 18px; font-weight:600;">Reject</button>';
                echo '</form>';
                echo '</div>';
              }
            } else {
              echo '<p>No supervisor-approved leave requests.</p>';
            }
            ?>
        </div>
    </div>
</body>
</html>