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
    <meta charset="UTF-8">
    <title>Dashboard - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>Dashboard</h1>
        <p>Welcome to your Brassel System dashboard</p>
    </div>
    <div class="nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="employee.php">👤 Employee</a>
        <a href="attendance.php">⏰ Attendance</a>
        <a href="leaverequest.php">🗓️ Leave</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
        <div class="card">
            <!-- Main dashboard content goes here -->
            <h2>Welcome to your Dashboard</h2>
            
            <div class="user-info">
                <div class="info-box">
                    <h3>Your Role</h3>
                    <p><?php echo htmlspecialchars($role); ?></p>
                </div>
                <div class="info-box">
                    <h3>Your Username</h3>
                    <p><?php echo htmlspecialchars($username); ?></p>
                </div>
            </div>
            
            <div class="actions">
                <?php if ($role === 'HR Officer'): ?>
                    <div class="action-box">
                        <h3>Manage Users</h3>
                        <p>Add, edit or remove system users</p>
                        <a href="manage_users.php">Go to Users</a>
                    </div>
                <?php endif; ?>

                <?php if ($role === 'Admin' || $role === 'HR Officer'): ?>
                    <div class="action-box">
                        <h3>Manage Users</h3>
                        <p>Add, edit or remove system users</p>
                        <a href="manage_users.php">Go to Users</a>
                    </div>
                <?php endif; ?>

                <?php if ($role === 'Admin' || $role === 'HR Officer'): ?>
                    <div class="action-box">
                        <h3>Manage Users</h3>
                        <p>Add, edit or remove system users</p>
                        <a href="manage_users.php">Go to Users</a>
                    </div>
                <?php endif; ?>
                
                <?php if ($role === 'Employee' || $role === 'Supervisor' || $role === 'HR Officer' || $role === 'Admin'): ?>
                    <div class="action-box">
                        <h3>Attendance</h3>
                        <p>View and manage your attendance</p>
                        <a href="attendance.php">Go to Attendance</a>
                    </div>
                <?php endif; ?>
                
                <?php if ($role === 'Employee' || $role === 'Supervisor' || $role === 'HR Officer'): ?>
                    <div class="action-box">
                        <h3>Leave Management</h3>
                        <p>Request or approve leave</p>
                        <a href="leaverequest.php">Go to Leave</a>
                    </div>
                <?php endif; ?>

                 <?php if ($role === 'Employee' || $role === 'Supervisor' || $role === 'HR Officer'): ?>
                    <div class="action-box">
                        <h3>Pay Slip</h3>
                        <p>View payslip details</p>
                        <a href="payslip.php">Go to payslip</a>
                    </div>
                <?php endif; ?>
                
                <?php if ($role === 'Finance Officer' || $role === 'Admin'): ?>
                    <div class="action-box">
                        <h3>Payroll</h3>
                        <p>Process and manage payroll</p>
                        <a href="payroll.php">Go to Payroll</a>
                    </div>
                <?php endif; ?>
                
                <?php if ($role === 'Admin' || $role === 'HR Officer' || $role === 'Supervisor'): ?>
                    <div class="action-box">
                        <h3>Reports</h3>
                        <p>Generate system reports</p>
                        <a href="reports.php">Go to Reports</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
