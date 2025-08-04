<?php
include 'db_connect.php';
include 'security_config.php';

// PHPMailer includes and namespace use statements at the top
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    if (!$email) {
        $error = 'Please enter your email address.';
    } else {
        $stmt = $conn->prepare('SELECT user_id, username, is_active FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($user_id, $username, $is_active);
        if ($stmt->fetch() && $is_active) {
            $stmt->close();
            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
            // Store token in password_resets table
            $conn->query("CREATE TABLE IF NOT EXISTS password_resets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), token VARCHAR(100), expires_at DATETIME, used TINYINT DEFAULT 0)");
            $ins = $conn->prepare('INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)');
            $ins->bind_param('isss', $user_id, $email, $token, $expires);
            $ins->execute();
            $ins->close();

            // Send real email using PHPMailer
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'bradleyjuma1@gmail.com'; // Your Gmail address
                $mail->Password   = 'prsoqjtqdscxgznr';       // App password (no spaces)
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('bradleyjuma1@gmail.com', 'Brassel HR System');
                $mail->addAddress($email, $username);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "<h2>Password Reset Request</h2>"
                    . "<p>Hello $username,</p>"
                    . "<p>Click the link below to reset your password. This link will expire in 1 hour.</p>"
                    . "<p><a href='$reset_link'>$reset_link</a></p>"
                    . "<p>If you did not request a password reset, you can ignore this email.</p>";

                $mail->send();
                $success = "A password reset link has been sent to your email.";
            } catch (Exception $e) {
                $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = 'No active account found with that email.';
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - HR System</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Forgot Password</h1>
        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
        <?php if (!$success): ?>
        <form method="POST">
            <div class="form-group">
                <label for="email">Enter your active email address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit">Send Reset Link</button>
        </form>
        <?php endif; ?>
        <div class="back-link"><a href="login.php">Back to Login</a></div>
    </div>
</body>
</html>