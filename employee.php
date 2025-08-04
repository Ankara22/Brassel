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

// Fetch employee details from employees table
$emp_name = $emp_department = $emp_designation = $emp_email = '';
$annual_leave = $sick_leave = $personal_leave = 0;
$user_id = $_SESSION['user_id'];
$sql = "SELECT name, department, designation, annual_leave, sick_leave, personal_leave FROM employees WHERE user_id = ? LIMIT 1";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($emp_name, $emp_department, $emp_designation, $annual_leave, $sick_leave, $personal_leave);
    $stmt->fetch();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Brassel - Employee Dashboard</title>
  <link rel="stylesheet" href="css/index.css" />
  <script src="https://kit.fontawesome.com/6c6d3ac6f6.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="header">
        <h1>Employee Dashboard</h1>
        <p>Welcome to your Brassel System employee portal</p>
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
      <div class="employee-dashboard-flex">
        <div class="card">
          <h2>Profile Information</h2>
          <ul>
            <li><strong>Name:</strong> <?php echo htmlspecialchars($emp_name); ?></li>
            <li><strong>Department:</strong> <?php echo htmlspecialchars($emp_department); ?></li>
            <li><strong>Designation:</strong> <?php echo htmlspecialchars($emp_designation); ?></li>
          </ul>
        </div>
        <div class="card quick-actions">
          <h2>Quick Actions</h2>
          <div class="quick-actions-grid">
            <div class="action-btn">
              <ul><li><a href="attendance.php"><i class="far fa-clock"></i> Clock In/Out</a></li></ul>
            </div>
            <div class="action-btn">
              <ul><li><a href="payslip.php"><i class="fas fa-money-check-alt"></i> View Salary / Payroll</a></li></ul>
            </div>
            <div class="action-btn">
              <ul><li><a href="employee_reports.php"><i class="fas fa-file-download"></i> Download Payslip / Reports</a></li></ul>
            </div>
            <div class="action-btn">
              <ul><li><a href="leaverequest.php"><i class="far fa-calendar-alt"></i> Request Leave</a></li></ul>
            </div>
          </div>
        </div>
        <div class="card leave-balance">
          <h2>Leave Balance</h2>
          <ul>
            <li>
              <span>Annual Leave</span>
              <span class="balance green"><?php echo htmlspecialchars($annual_leave); ?> days</span>
            </li>
            <li>
              <span>Sick Leave</span>
              <span class="balance blue"><?php echo htmlspecialchars($sick_leave); ?> days</span>
            </li>
            <li>
              <span>Personal Leave</span>
              <span class="balance purple"><?php echo htmlspecialchars($personal_leave); ?> days</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
</body>
</html>