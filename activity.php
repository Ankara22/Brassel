<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role_name'];

// Fetch latest 30 activity log entries
$logs = [];
$sql = "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 30";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Log - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>Activity Log</h1>
        <p>View system and user activity logs</p>
    </div>
    <div class="nav">
        <a href="admin.php">🏠 Dashboard</a>
        <a href="usermgnt.php">👤 User Management</a>
        <a href="roles.php">🛡️ Roles</a>
        <a href="activity.php">📜 Activity Log</a>
        <a href="settings.php">⚙️ Settings</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
        <div class="card">
            <!-- Main activity log content goes here -->
            <?php if (count($logs) === 0): ?>
              <div class="no-logs">No activity logs found.</div>
            <?php else: ?>
            <table class="log-table">
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>User</th>
                  <th>Action</th>
                  <th>Details</th>
                  <th>IP Address</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $log): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($log['username'] ?: 'System'); ?></td>
                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 