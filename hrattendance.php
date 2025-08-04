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

// Fetch all employees with user_id
$employees = [];
$sql = "SELECT id, name, department, user_id FROM employees";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $employees[$row['id']] = $row;
}

// Fetch today's attendance (latest record per employee)
$today = date('Y-m-d');
$attendance = [];
foreach ($employees as $emp_id => $emp) {
    // Use employee_id for attendance lookup
    $sql = "SELECT clock_in, clock_out FROM employee_attendance WHERE employee_id = ? AND DATE(clock_in) = ? ORDER BY clock_in DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $emp_id, $today);
    $stmt->execute();
    $stmt->bind_result($clock_in, $clock_out);
    if ($stmt->fetch()) {
        $attendance[$emp_id] = [
            'clock_in' => $clock_in,
            'clock_out' => $clock_out
        ];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Attendance - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>HR Attendance</h1>
        <p>View and manage employee attendance records</p>
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
            <h2>Employee Attendance</h2>
            <p>Monitor real-time employee attendance and clock in/out records</p>
            <table class="table-container">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Clock In</th>
                  <th>Clock Out</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($employees as $id => $emp): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($emp['name']); ?></td>
                    <td><?php echo htmlspecialchars($emp['department']); ?></td>
                    <td><?php echo isset($attendance[$id]) ? date('H:i', strtotime($attendance[$id]['clock_in'])) : '--'; ?></td>
                    <td><?php echo (isset($attendance[$id]) && $attendance[$id]['clock_out']) ? date('H:i', strtotime($attendance[$id]['clock_out'])) : '--'; ?></td>
                    <td>
                      <?php if (isset($attendance[$id])): ?>
                        <span class="status-badge status-present">Present</span>
                      <?php else: ?>
                        <span class="status-badge status-absent">Absent</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
        </div>
    </div>
</body>
</html>