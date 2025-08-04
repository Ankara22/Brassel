<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role_name'];

// Handle add role
$role_error = '';
$role_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $role_name = trim($_POST['role_name']);
    $role_desc = trim($_POST['role_desc']);
    if (!$role_name) {
        $role_error = 'Role name is required.';
    } else {
        // Check for duplicate
        $check = $conn->prepare('SELECT COUNT(*) FROM roles WHERE role_name = ?');
        $check->bind_param('s', $role_name);
        $check->execute();
        $check->bind_result($exists);
        $check->fetch();
        $check->close();
        if ($exists > 0) {
            $role_error = 'Role name already exists.';
        } else {
            $insert = $conn->prepare('INSERT INTO roles (role_name, description) VALUES (?, ?)');
            $insert->bind_param('ss', $role_name, $role_desc);
            $insert->execute();
            $insert->close();
            $role_success = 'Role added successfully!';
        }
    }
}

// Handle edit role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_role_id'])) {
    $edit_id = intval($_POST['edit_role_id']);
    $edit_name = trim($_POST['edit_role_name']);
    $edit_desc = trim($_POST['edit_role_desc']);
    if (!$edit_name) {
        $role_error = 'Role name is required.';
    } else {
        // Check for duplicate name (exclude self)
        $check = $conn->prepare('SELECT COUNT(*) FROM roles WHERE role_name = ? AND role_id != ?');
        $check->bind_param('si', $edit_name, $edit_id);
        $check->execute();
        $check->bind_result($exists);
        $check->fetch();
        $check->close();
        if ($exists > 0) {
            $role_error = 'Role name already exists.';
        } else {
            $update = $conn->prepare('UPDATE roles SET role_name = ?, description = ? WHERE role_id = ?');
            $update->bind_param('ssi', $edit_name, $edit_desc, $edit_id);
            $update->execute();
            $update->close();
            $role_success = 'Role updated successfully!';
        }
    }
}
// Handle delete role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role_id'])) {
    $delete_id = intval($_POST['delete_role_id']);
    // Check if any users are assigned
    $check = $conn->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
    $check->bind_param('i', $delete_id);
    $check->execute();
    $check->bind_result($user_count);
    $check->fetch();
    $check->close();
    if ($user_count > 0) {
        $role_error = 'Cannot delete: users are assigned to this role.';
    } else {
        $del = $conn->prepare('DELETE FROM roles WHERE role_id = ?');
        $del->bind_param('i', $delete_id);
        $del->execute();
        $del->close();
        $role_success = 'Role deleted successfully!';
    }
}

// Fetch all roles and user counts
$roles = [];
$sql = 'SELECT r.role_id, r.role_name, r.description, COUNT(u.user_id) as user_count FROM roles r LEFT JOIN users u ON r.role_id = u.role_id GROUP BY r.role_id';
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}
// Initialize $edit_row to avoid undefined variable warning
$edit_row = isset($_POST['edit_btn']) ? intval($_POST['edit_btn']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roles - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>Roles Management</h1>
        <p>Manage user roles and permissions</p>
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
            <div class="section-title">Role Management</div>
            <form class="add-role-form" method="POST" style="max-width:400px; margin-bottom:30px;">
                <label for="role_name">Role Name</label>
                <input type="text" id="role_name" name="role_name" required />
                <label for="role_desc">Description</label>
                <textarea id="role_desc" name="role_desc" rows="2"></textarea>
                <button type="submit" name="add_role">Add Role</button>
            </form>
            <?php if ($role_success): ?>
                <div class="success-msg"><?php echo htmlspecialchars($role_success); ?></div>
            <?php endif; ?>
            <?php if ($role_error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($role_error); ?></div>
            <?php endif; ?>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Users</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $r): ?>
                        <tr>
                            <?php if ($edit_row === intval($r['role_id'])): ?>
                                <form method="POST">
                                    <td><input type="text" name="edit_role_name" value="<?php echo htmlspecialchars($r['role_name']); ?>" required style="width:95%"></td>
                                    <td><input type="text" name="edit_role_desc" value="<?php echo htmlspecialchars($r['description']); ?>" style="width:95%"></td>
                                    <td><?php echo $r['user_count']; ?></td>
                                    <td>
                                        <input type="hidden" name="edit_role_id" value="<?php echo $r['role_id']; ?>">
                                        <button type="submit" class="role-action-btn edit">Save</button>
                                        <a href="roles.php" class="role-action-btn" style="background:#888;">Cancel</a>
                                    </td>
                                </form>
                            <?php else: ?>
                                <td><?php echo htmlspecialchars($r['role_name']); ?></td>
                                <td><?php echo htmlspecialchars($r['description']); ?></td>
                                <td><?php echo $r['user_count']; ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="edit_btn" value="<?php echo $r['role_id']; ?>">
                                        <button type="submit" class="role-action-btn edit">Edit</button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this role?');">
                                        <input type="hidden" name="delete_role_id" value="<?php echo $r['role_id']; ?>">
                                        <button type="submit" class="role-action-btn delete" <?php if($r['user_count']>0) echo 'disabled title="Cannot delete: users assigned"'; ?>>Delete</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 