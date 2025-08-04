<?php
// Set your admin/support email here:
$admin_email = 'bradleyjuma1@gmail.com';

include 'db_connect.php';

// PHPMailer includes and namespace use statements at the top
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (!$name || !$email || !$subject || !$message) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Store in database
        $conn->query("CREATE TABLE IF NOT EXISTS contact_messages (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), email VARCHAR(255), subject VARCHAR(255), message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $stmt = $conn->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $name, $email, $subject, $message);
        $stmt->execute();
        $stmt->close();
        // Send email to admin and confirmation to user using PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'bradleyjuma1@gmail.com';
            $mail->Password   = 'prsoqjtqdscxgznr';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->setFrom('bradleyjuma1@gmail.com', 'Brassel Contact Form');
            $mail->addAddress($admin_email, 'Admin');
            $mail->addReplyTo($email, $name);
            $mail->isHTML(true);
            $mail->Subject = "[Contact Form] $subject";
            $mail->Body    = "<h2>New Contact Form Message</h2>"
                . "<p><strong>Name:</strong> $name</p>"
                . "<p><strong>Email:</strong> $email</p>"
                . "<p><strong>Subject:</strong> $subject</p>"
                . "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>";
            $mail->send();
            // Send confirmation to user
            $mail2 = new PHPMailer(true);
            $mail2->isSMTP();
            $mail2->Host       = 'smtp.gmail.com';
            $mail2->SMTPAuth   = true;
            $mail2->Username   = 'bradleyjuma1@gmail.com';
            $mail2->Password   = 'prsoqjtqdscxgznr';
            $mail2->SMTPSecure = 'tls';
            $mail2->Port       = 587;
            $mail2->setFrom('bradleyjuma1@gmail.com', 'Brassel Support');
            $mail2->addAddress($email, $name);
            $mail2->isHTML(true);
            $mail2->Subject = 'We have received your message';
            $mail2->Body    = "<h2>Thank you for contacting Brassel!</h2>"
                . "<p>Dear $name,</p>"
                . "<p>We have received your message and will get back to you as soon as possible.</p>"
                . "<hr>"
                . "<p><strong>Your Message:</strong></p>"
                . "<p><strong>Subject:</strong> $subject</p>"
                . "<p>" . nl2br(htmlspecialchars($message)) . "</p>"
                . "<hr>"
                . "<p>Best regards,<br>Brassel Support Team</p>";
            $mail2->send();
            $success = 'Thank you for contacting us! We have received your message.';
        } catch (Exception $e) {
            $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Us - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .card {
            max-width: 520px;
            margin: 48px auto;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(102,126,234,0.10);
            padding: 0 0 32px 0;
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            display: block;
            height: 8px;
            width: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            margin-bottom: 24px;
        }
        .card h2 {
            text-align: center;
            color: #4a00e0;
            margin-bottom: 18px;
        }
        .form-group {
            margin: 0 32px 22px 32px;
            display: flex;
            flex-direction: column;
        }
        label {
            font-weight: 700;
            color: #333;
            margin-bottom: 7px;
            letter-spacing: 0.5px;
        }
        input, textarea {
            padding: 13px 14px;
            border: 1.5px solid #e0e7ff;
            border-radius: 8px;
            font-size: 1rem;
            background: #f7f8fa;
            transition: border 0.2s;
            margin-bottom: 2px;
        }
        input:focus, textarea:focus {
            border: 1.5px solid #667eea;
            outline: none;
            background: #fff;
        }
        textarea {
            min-height: 90px;
            resize: vertical;
        }
        button[type="submit"] {
            display: block;
            width: calc(100% - 64px);
            margin: 18px 32px 0 32px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            border: none;
            border-radius: 8px;
            padding: 14px 0;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(102,126,234,0.08);
            transition: background 0.2s;
        }
        button[type="submit"]:hover {
            background: linear-gradient(90deg, #764ba2 0%, #667eea 100%);
        }
        .error, .success {
            margin: 0 32px 18px 32px;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
        }
        .error { background: #ffe6e6; color: #e74c3c; }
        .success { background: #e6ffed; color: #27ae60; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Contact Us</h1>
        <p>We'd love to hear from you! Fill out the form below to get in touch.</p>
    </div>
    <div class="nav">
        <a href="index.php">🏠 Home</a>
        <a href="about.php">ℹ️ About</a>
        <a href="contact.php">📞 Contact</a>
        <a href="login.php">🔐 Login</a>
    </div>
    <div class="container">
        <div class="card">
            <h2>Contact Form</h2>
            <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
            <?php if (!$success): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="name">Your Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Your Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" required></textarea>
                </div>
                <button type="submit">Send Message</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>