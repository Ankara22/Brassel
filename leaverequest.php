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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Leave Requests - Brassel System</title>
  <link rel="stylesheet" href="css/index.css" />
  <script src="https://kit.fontawesome.com/6c6d3ac6f6.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="header">
        <h1>Leave Requests</h1>
        <p>Submit and view your leave requests</p>
    </div>
    <div class="nav">
        <a href="employee.php">🏠 Dashboard</a>
        <a href="attendance.php">⏰ Attendance</a>
        <a href="leaverequest.php">🗓️ Leave</a>
        <a href="payslip.php">💰 Payslip</a>
        <a href="employee_reports.php">📄 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
      <div class="leave-dashboard-flex">
        <div class="card request-form">
          <div class="icon-container">
            <i class="far fa-calendar-alt"></i>
          </div>
          <h2>Request Leave</h2>
          <form action="submit_leave.php" method="POST">
            <label for="leave-type">Leave Type</label>
            <select id="leave-type" name="leave_type" required>
              <option value="">Select leave type</option>
              <option value="annual">Annual Leave</option>
              <option value="sick">Sick Leave</option>
              <option value="personal">Personal Leave</option>
            </select>
            <label for="start-date">Start Date</label>
            <input type="date" id="start-date" name="start_date" required />
            <label for="end-date">End Date</label>
            <input type="date" id="end-date" name="end_date" required />
            <label for="reason">Reason</label>
            <textarea id="reason" name="reason" rows="4" placeholder="Please provide a reason for your leave request..." required></textarea>
            <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
          </form>
        </div>
        <div class="card leave-history">
          <div class="icon-container">
            <i class="far fa-clock"></i>
          </div>
          <h2>Leave History</h2>
          <?php
          $user_id = $_SESSION['user_id'];
          $sql = "SELECT leave_type, start_date, end_date, reason, status FROM leave_requests WHERE user_id = ? ORDER BY submitted_at DESC";
          if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->bind_result($leave_type, $start_date, $end_date, $reason, $status);
            $hasResults = false;
            while ($stmt->fetch()) {
              $hasResults = true;
              $badgeClass = 'pending';
              if ($status === 'supervisor_approved') $badgeClass = 'forwarded';
              if ($status === 'supervisor_rejected') $badgeClass = 'rejected';
              if ($status === 'hr_approved') $badgeClass = 'approved';
              if ($status === 'hr_rejected') $badgeClass = 'rejected';
              $statusLabel = ucfirst(str_replace('_', ' ', $status));
              echo '<div class="history-item">';
              echo '<div class="status-badge ' . $badgeClass . '">' . $statusLabel . '</div>';
              echo '<h3>' . htmlspecialchars(ucwords(str_replace('_', ' ', $leave_type))) . '</h3>';
              echo '<p>' . htmlspecialchars(date('n/j/Y', strtotime($start_date))) . ' - ' . htmlspecialchars(date('n/j/Y', strtotime($end_date))) . '</p>';
              echo '<p>' . htmlspecialchars($reason) . '</p>';
              echo '</div>';
            }
            if (!$hasResults) {
              echo '<p>No leave requests found.</p>';
            }
            $stmt->close();
          } else {
            echo '<p>Error loading leave history.</p>';
          }
          ?>
        </div>
      </div>
    </div>
</body>
</html>