<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role_name'];

// Handle password reset
$reset_success = '';
$reset_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!$new_password || !$confirm_password) {
        $reset_error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $reset_error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $reset_error = 'Password must be at least 6 characters long.';
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $stmt->bind_param('si', $new_hash, $user_id);
        if ($stmt->execute()) {
            $reset_success = 'Password reset successfully!';
            // Log the password reset activity
            $admin_id = $_SESSION['user_id'];
            $log_stmt = $conn->prepare('INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
            $action = 'Password Reset';
            $details = "Admin reset password for user ID: $user_id";
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param('isss', $admin_id, $action, $details, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $reset_error = 'Failed to reset password. Please try again.';
        }
        $stmt->close();
    }
}

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
    header('Location: usermgnt.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            margin-top: -10px;
        }
        .close:hover {
            color: #000;
        }
        .reset-btn {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            margin-right: 6px;
            transition: all 0.3s ease;
        }
        .reset-btn:hover {
            background: #f39c12;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
        }
        .modal-form {
            margin-top: 20px;
        }
        .modal-form .form-group {
            margin-bottom: 20px;
        }
        .modal-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        .modal-form input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
        }
        .modal-form input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        .modal-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }
        .modal-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .modal-btn.secondary {
            background: #e1e8ed;
            color: #2c3e50;
        }
        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }
        .action-buttons button,
        .action-buttons form {
            margin: 0;
        }
        .action-buttons .edit-btn,
        .action-buttons .reset-btn,
        .action-buttons .terminate-btn {
            margin: 0;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>User Management</h1>
        <p>Manage system users and roles</p>
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
        <?php if ($reset_success): ?>
            <div class="success-msg"><?php echo htmlspecialchars($reset_success); ?></div>
        <?php endif; ?>
        <?php if ($reset_error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($reset_error); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="section-title">User Management</div>
            <button class="add-btn" onclick="window.location.href='add_user.php'">
              <i class="fas fa-plus"></i> Add User
            </button>
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
                      <div class="action-buttons">
                        <button class="edit-btn" data-id="<?php echo $user['user_id']; ?>">Edit</button>
                        <button class="reset-btn" onclick="openResetModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">Reset Password</button>
                        <form method="POST" action="usermgnt.php" style="display:inline;">
                          <input type="hidden" name="deactivate_user_id" value="<?php echo $user['user_id']; ?>">
                          <button type="submit" class="terminate-btn" onclick="return confirm('Are you sure you want to deactivate this user?');">Deactivate</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
        </div>
    </div>

    <!-- Password Reset Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeResetModal()">&times;</span>
            <h2>Reset User Password</h2>
            <p id="resetUserInfo">Reset password for user: <strong id="resetUsername"></strong></p>
            <form class="modal-form" method="POST" action="usermgnt.php">
                <input type="hidden" name="user_id" id="resetUserId">
                <input type="hidden" name="reset_password" value="1">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn secondary" onclick="closeResetModal()">Cancel</button>
                    <button type="submit" class="modal-btn primary">Reset Password</button>
                </div>
            </form>
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

      // Modal functions
      function openResetModal(userId, username) {
        document.getElementById('resetModal').style.display = 'block';
        document.getElementById('resetUserId').value = userId;
        document.getElementById('resetUsername').textContent = username;
        document.getElementById('new_password').focus();
      }

      function closeResetModal() {
        document.getElementById('resetModal').style.display = 'none';
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
      }

      // Close modal when clicking outside
      window.onclick = function(event) {
        const modal = document.getElementById('resetModal');
        if (event.target === modal) {
          closeResetModal();
        }
      }
    </script>
</body>
</html>