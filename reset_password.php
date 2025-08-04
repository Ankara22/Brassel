<?php
include 'db_connect.php';
include 'security_config.php';

$error = '';
$success = '';
$show_form = false;
$token = $_GET['token'] ?? '';

if ($token) {
    // Check if token is valid and not expired/used
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), token VARCHAR(100), expires_at DATETIME, used TINYINT DEFAULT 0)");
    $stmt = $conn->prepare('SELECT user_id, email, expires_at, used FROM password_resets WHERE token = ?');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->bind_result($user_id, $email, $expires_at, $used);
    if ($stmt->fetch()) {
        if ($used) {
            $error = 'This reset link has already been used.';
        } elseif (strtotime($expires_at) < time()) {
            $error = 'This reset link has expired.';
        } else {
            $show_form = true;
        }
    } else {
        $error = 'Invalid or expired reset link.';
    }
    $stmt->close();
} else {
    $error = 'No reset token provided.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'], $_POST['password'], $_POST['confirm_password'])) {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    // Validate token again
    $stmt = $conn->prepare('SELECT user_id, email, expires_at, used FROM password_resets WHERE token = ?');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->bind_result($user_id, $email, $expires_at, $used);
    if ($stmt->fetch() && !$used && strtotime($expires_at) > time()) {
        $stmt->close();
        // Validate password
        $password_errors = validatePasswordStrength($password);
        if ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
            $show_form = true;
        } elseif (!empty($password_errors)) {
            $error = 'Password requirements not met:<br>' . implode('<br>', $password_errors);
            $show_form = true;
        } else {
            // Update password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $up = $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
            $up->bind_param('si', $hashed, $user_id);
            $up->execute();
            $up->close();
            // Mark token as used
            $conn->query("UPDATE password_resets SET used = 1 WHERE token = '" . $conn->real_escape_string($token) . "'");
            $success = 'Your password has been reset successfully! <a href="login.php">Log in</a>';
        }
    } else {
        $error = 'Invalid or expired reset link.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - HR System</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); padding: 40px; width: 100%; max-width: 400px; }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #555; font-weight: 600; }
        input { width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 16px; }
        button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-size: 16px; width: 100%; font-weight: 600; }
        button:hover { background: #667eea; }
        .error { color: #e74c3c; margin-bottom: 20px; text-align: center; }
        .success { color: #27ae60; margin-bottom: 20px; text-align: center; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #667eea; text-decoration: underline; }
        .password-requirements { margin-top: 15px; padding: 15px; background-color: #fff3cd; border-radius: 6px; font-size: 12px; color: #856404; }
        .password-requirements h4 { margin: 0 0 10px 0; font-size: 14px; }
        .password-requirements ul { margin: 0; padding-left: 20px; }
        .password-requirements li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Password</h1>
        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($show_form): ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Reset Password</button>
        </form>
        <div class="password-requirements">
            <h4>Password Requirements:</h4>
            <ul>
                <li>Minimum 8 characters</li>
                <li>At least one uppercase letter</li>
                <li>At least one lowercase letter</li>
                <li>At least one number</li>
                <li>At least one special character</li>
            </ul>
        </div>
        <?php endif; ?>
        <div class="back-link"><a href="login.php">Back to Login</a></div>
    </div>
</body>
</html> 