<?php
session_start();
include 'db_connect.php';

// Redirect to login if not authenticated or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role_name'];
$section = isset($_GET['section']) ? $_GET['section'] : 'overview';

// Quick Stats
$total_users = $active_users = $inactive_users = $total_roles = 0;
$sql = "SELECT COUNT(*) as total FROM users";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) $total_users = $row['total'];
$sql = "SELECT COUNT(*) as active FROM users WHERE status = 'active' OR status IS NULL";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) $active_users = $row['active'];
$sql = "SELECT COUNT(*) as inactive FROM users WHERE status = 'inactive'";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) $inactive_users = $row['inactive'];
$sql = "SELECT COUNT(*) as roles FROM roles";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) $total_roles = $row['roles'];

// System Health Metrics
$db_status = 'Connected';
$db_error = '';
try {
    $conn->ping();
} catch (Exception $e) {
    $db_status = 'Error';
    $db_error = $e->getMessage();
}

$php_version = PHP_VERSION;
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$memory_limit = ini_get('memory_limit');
$max_execution_time = ini_get('max_execution_time');
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');

// Get system uptime (simplified)
$uptime = 'Online';
$last_restart = date('Y-m-d H:i:s', strtotime('-7 days')); // Placeholder

// User Management Table
$users = [];
$sql = "SELECT u.user_id, u.username, CONCAT(u.first_name, ' ', u.last_name) as name, u.email, r.role_name, IFNULL(u.status, 'active') as status FROM users u JOIN roles r ON u.role_id = r.role_id";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
// Handle user deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_user_id'])) {
    $deactivate_id = intval($_POST['deactivate_user_id']);
    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
    $stmt->bind_param('i', $deactivate_id);
    $stmt->execute();
    $stmt->close();
    header('Location: admin.php?section=users');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>Admin Dashboard</h1>
        <p>Manage users, settings, and system operations</p>
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
            <!-- Main admin content goes here -->
            <?php if ($section === 'overview'): ?>
                <div class="section-title">Overview</div>
                <div class="quick-stats-health">
                  <div class="quick-stats">
                    <h3 style="margin-bottom:18px;">Quick Stats</h3>
                    <div class="box">
                        <h3>Total Users</h3>
                        <p><?php echo $total_users; ?></p>
                    </div>
                    <div class="box">
                        <h3>Active Users</h3>
                        <p style="color:#2ecc71;"><?php echo $active_users; ?></p>
                    </div>
                    <div class="box">
                        <h3>Inactive Users</h3>
                        <p style="color:#e74c3c;"><?php echo $inactive_users; ?></p>
                    </div>
                    <div class="box">
                        <h3>Roles</h3>
                        <p style="color:#9b59b6;"><?php echo $total_roles; ?></p>
                    </div>
                  </div>
                  <div class="health-grid">
                    <h3 style="margin-bottom:18px;">System Health</h3>
                    <div class="health-item status-ok">
                        <h4>Database Status</h4>
                        <p><?php echo htmlspecialchars($db_status); ?></p>
                        <span class="status-badge ok">Connected</span>
                    </div>
                    <div class="health-item status-ok">
                        <h4>System Status</h4>
                        <p><?php echo htmlspecialchars($uptime); ?></p>
                        <span class="status-badge ok">Online</span>
                    </div>
                    <div class="health-item status-ok">
                        <h4>PHP Version</h4>
                        <p><?php echo htmlspecialchars($php_version); ?></p>
                    </div>
                    <div class="health-item status-ok">
                        <h4>Server Software</h4>
                        <p><?php echo htmlspecialchars($server_software); ?></p>
                    </div>
                    <div class="health-item status-ok">
                        <h4>Memory Limit</h4>
                        <p><?php echo htmlspecialchars($memory_limit); ?></p>
                    </div>
                    <div class="health-item status-ok">
                        <h4>Max Execution Time</h4>
                        <p><?php echo htmlspecialchars($max_execution_time); ?> seconds</p>
                    </div>
                    <div class="health-item status-ok">
                        <h4>Upload Max Size</h4>
                        <p><?php echo htmlspecialchars($upload_max_filesize); ?></p>
                    </div>
                    <div class="health-item status-ok">
                        <h4>Post Max Size</h4>
                        <p><?php echo htmlspecialchars($post_max_size); ?></p>
                    </div>
                    <div class="health-item status-ok">
                        <h4>Last Restart</h4>
                        <p><?php echo htmlspecialchars($last_restart); ?></p>
                    </div>
                  </div>
                </div>
            <?php elseif ($section === 'users'): ?>
                <div class="section-title">User Management</div>
                <table class="table-container">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $user['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="edit-btn" data-id="<?php echo $user['user_id']; ?>">Edit</button>
                                    <form method="POST" action="admin.php?section=users" style="display:inline;">
                                        <input type="hidden" name="deactivate_user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" class="terminate-btn" onclick="return confirm('Are you sure you want to deactivate this user?');">Deactivate</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($section === 'roles'): ?>
                <div class="section-title">Role Management</div>
                <div class="placeholder">List, add, edit, and delete roles here. (Coming soon)</div>
            <?php elseif ($section === 'activity'): ?>
                <div class="section-title">Activity Log</div>
                <div class="placeholder">Audit log of user/system actions will appear here. (Coming soon)</div>
            <?php elseif ($section === 'settings'): ?>
                <div class="section-title">System Settings</div>
                <div class="placeholder">System settings and configuration options. (Coming soon)</div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        // Edit button redirect
        const editButtons = document.querySelectorAll('.edit-btn');
        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = btn.getAttribute('data-id');
                window.location.href = `edit_user.php?user_id=${userId}`;
            });
        });
    </script>
</body>
</html>