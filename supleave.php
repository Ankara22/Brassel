<?php
session_start();
include 'db_connect.php';

// Redirect to login if not authenticated or not supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    header('Location: login.php');
    exit();
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $status = ($action === 'approve') ? 'supervisor_approved' : 'supervisor_rejected';
    $supervisor_id = $_SESSION['user_id'];
    $stmt = $conn->prepare('UPDATE leave_requests SET status = ?, supervisor_id = ? WHERE id = ?');
    $stmt->bind_param('sii', $status, $supervisor_id, $request_id);
    $stmt->execute();
    $stmt->close();
    // Log supervisor action
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $log_action = ($action === 'approve') ? 'leave_approved_supervisor' : 'leave_rejected_supervisor';
    $details = (($action === 'approve') ? 'Supervisor approved' : 'Supervisor rejected') . ' leave request ID ' . $request_id;
    $log_stmt = $conn->prepare('INSERT INTO activity_log (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    $log_stmt->bind_param('issss', $user_id, $username, $log_action, $details, $ip);
    $log_stmt->execute();
    $log_stmt->close();
    header('Location: supleave.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supervisor Leave - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
      .leave-history {
        margin-top: 20px;
      }
      .history-item {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(134,142,177,0.08);
        padding: 18px 22px;
        margin-bottom: 18px;
        border-left: 6px solid #4a00e0;
        position: relative;
      }
      .status-badge {
        display: inline-block;
        padding: 5px 14px;
        border-radius: 5px;
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 8px;
      }
      .pending { background: #fff7d4; color: #ff9f43; }
      .supervisor_approved { background: #e6ffed; color: #2ecc71; }
      .supervisor_rejected { background: #ffe6e6; color: #e74c3c; }
      .btn-approve, .btn-reject {
        font-size: 1rem;
        font-weight: 600;
        border: none;
        border-radius: 5px;
        padding: 8px 18px;
        margin-right: 8px;
        cursor: pointer;
        transition: background 0.2s;
      }
      .btn-approve { background: #28a745; color: #fff; }
      .btn-approve:hover { background: #218838; }
      .btn-reject { background: #dc3545; color: #fff; }
      .btn-reject:hover { background: #c82333; }
      .icon-container i {
        font-size: 44px;
        color: #4a00e0;
        border: 2px solid #4a00e0;
        border-radius: 50%;
        padding: 12px;
        background: #fff;
        margin-bottom: 10px;
      }
    </style>
</head>
<body>
    <div class="header">
        <h1>Supervisor Leave</h1>
        <p>Review and approve team leave requests</p>
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
        <div class="card" style="max-width: 600px; margin: 40px auto; box-shadow: 0 4px 16px rgba(134,142,177,0.10); border-radius: 16px; padding: 36px 32px; border: 1px solid #ececec;">
            <div class="icon-container" style="text-align:center; margin-bottom: 18px; font-size: 44px;">
              🗓️
            </div>
            <h2 style="text-align:center; font-size: 2rem; margin-bottom: 18px;">Pending Leave Requests</h2>
            <?php
            $sql = "SELECT lr.id, u.username, lr.leave_type, lr.start_date, lr.end_date, lr.reason FROM leave_requests lr JOIN users u ON lr.user_id = u.user_id WHERE lr.status = 'pending' ORDER BY lr.submitted_at ASC";
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                echo '<div class="history-item" style="margin-bottom: 24px; padding: 18px 20px; border-radius: 10px; background: #fafaff; box-shadow: 0 2px 8px rgba(134,142,177,0.06); border-left: 4px solid #4a00e0;">';
                echo '<div class="status-badge pending" style="background:#fff7d4;color:#ff9f43;display:inline-block;padding:5px 14px;border-radius:5px;font-size:0.9rem;font-weight:600;margin-bottom:8px;">Pending</div>';
                echo '<h3 style="margin: 10px 0 6px 0; font-size: 1.2rem;"><b>' . htmlspecialchars(ucwords(str_replace('_', ' ', $row['leave_type']))) . ' - ' . htmlspecialchars($row['username']) . '</b></h3>';
                echo '<p style="margin: 0 0 4px 0;"><b>Dates:</b> ' . htmlspecialchars(date('n/j/Y', strtotime($row['start_date']))) . ' - ' . htmlspecialchars(date('n/j/Y', strtotime($row['end_date']))) . '</p>';
                echo '<p style="margin: 0 0 10px 0;"><b>Reason:</b> ' . htmlspecialchars($row['reason']) . '</p>';
                echo '<form method="POST" style="display:inline-block;margin-right:10px;" onsubmit="return confirm(\'Are you sure you want to approve and forward this leave request?\');">';
                echo '<input type="hidden" name="request_id" value="' . intval($row['id']) . '">';
                echo '<button type="submit" name="action" value="approve" class="btn-approve"><i class="fas fa-check-circle"></i> Approve and Forward</button>';
                echo '</form>';
                echo '<form method="POST" style="display:inline-block;" onsubmit="return confirm(\'Are you sure you want to reject this leave request?\');">';
                echo '<input type="hidden" name="request_id" value="' . intval($row['id']) . '">';
                echo '<button type="submit" name="action" value="reject" class="btn-reject"><i class="fas fa-times-circle"></i> Reject</button>';
                echo '</form>';
                echo '</div>';
              }
            } else {
              echo '<p style="text-align:center; color:#888;">No pending leave requests.</p>';
            }
            ?>
        </div>
    </div>
</body>
</html>