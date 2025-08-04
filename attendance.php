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
  <title>Attendance - Brassel System</title>
  <link rel="stylesheet" href="css/index.css" />
  <script src="https://kit.fontawesome.com/6c6d3ac6f6.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="header">
        <h1>Attendance</h1>
        <p>Clock in/out and view your attendance history</p>
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
      <div class="attendance-dashboard-flex">
        <div class="card time-clock">
          <div class="icon-container">
            <i class="far fa-clock"></i>
          </div>
          <h2>Time Clock</h2>
          <div id="time-display">
            <h1 id="current-time">00:00:00</h1>
            <p id="current-date">Monday, January 1, 2025</p>
          </div>
          <div class="status">
            <span class="dot"></span>
            <span class="text">Not Clocked In</span>
          </div>
          <button id="clock-btn" class="btn-primary">Clock In</button>
        </div>
        <div class="card recent-attendance">
          <div class="icon-container">
            <i class="far fa-calendar-alt"></i>
          </div>
          <h2>Recent Attendance</h2>
          <div class="attendance-table-wrapper">
            <table class="attendance-table" id="attendance-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Clock In</th>
                  <th>Clock Out</th>
                  <th>Duration</th>
                </tr>
              </thead>
              <tbody id="attendance-table-body">
                <!-- Attendance records will be dynamically loaded here -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <script src="js/clock.js"></script>
    <script src="js/clockbtn.js"></script>
    <script src="js/recent_attendance.js"></script>
</body>
</html>