<?php
include 'db_connect.php';
include 'security_config.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Check session timeout
checkSessionTimeout();

// Initialize variables
$error = '';
$success = '';

// Handle timeout message
if (isset($_GET['msg']) && $_GET['msg'] === 'timeout') {
    $error = "Your session has expired. Please log in again.";
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];

        // Validate inputs
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            // Check login attempts
            if (!checkLoginAttempts($username, $conn)) {
                $error = "Too many login attempts. Please try again in 15 minutes.";
            } else {
                // Prepare SQL statement to prevent SQL injection
                $sql = "SELECT u.user_id, u.username, u.password_hash, u.role_id, u.is_active, r.role_name 
                        FROM users u 
                        JOIN roles r ON u.role_id = r.role_id 
                        WHERE username = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    // Check if user is active
                    if (!$user['is_active']) {
                        $error = "Account is deactivated. Please contact administrator.";
                        recordLoginAttempt($username, $conn);
                    } else {
                        // Verify password
                        if (password_verify($password, $user['password_hash'])) {
                            // Set session variables
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role_name'] = $user['role_name'];
                            $_SESSION['last_activity'] = time();
                            
                            // Log successful login
                            logActivity($user['user_id'], $user['username'], 'login', 'User logged in successfully', $conn);
                            
                            // Clear login attempts for this user
                            $clear_sql = "DELETE FROM login_attempts WHERE username = ?";
                            $clear_stmt = $conn->prepare($clear_sql);
                            $clear_stmt->bind_param("s", $username);
                            $clear_stmt->execute();
                            
                            // Redirect to appropriate dashboard based on role
                            switch ($user['role_name']) {
                                case 'Admin':
                                    header("Location: admin.php");
                                    break;
                                case 'Finance Officer':
                                    header("Location: finance.php");
                                    break;
                                case 'HR Officer':
                                    header("Location: hrofficer.php");
                                    break;
                                case 'Supervisor':
                                    header("Location: supervisor.php");
                                    break;
                                case 'Employee':
                                    header("Location: employee.php");
                                    break;
                                default:
                                    header("Location: dashboard.php");
                                    break;
                            }
                            exit();
                        } else {
                            $error = "Invalid username or password.";
                            recordLoginAttempt($username, $conn);
                        }
                    }
                } else {
                    $error = "Invalid username or password.";
                    recordLoginAttempt($username, $conn);
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR System - Login</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 400px;
            position: relative;
            overflow: hidden;
        }
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            font-weight: 600;
            transition: transform 0.2s ease;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .error {
            color: #e74c3c;
            margin-bottom: 20px;
            text-align: center;
            padding: 12px;
            background-color: #fdf2f2;
            border-radius: 6px;
            border-left: 4px solid #e74c3c;
        }
        .success {
            color: #27ae60;
            margin-bottom: 20px;
            text-align: center;
            padding: 12px;
            background-color: #f0f9ff;
            border-radius: 6px;
            border-left: 4px solid #27ae60;
        }
        .signup-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
        }
        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .signup-link a:hover {
            text-decoration: underline;
        }
        .security-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
        }
        .password-requirements {
            margin-top: 15px;
            padding: 15px;
            background-color: #fff3cd;
            border-radius: 6px;
            font-size: 12px;
            color: #856404;
        }
        .password-requirements h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        .password-requirements li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>HR System Login</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Log In</button>
        </form>
        
        <div style="text-align:center; margin-top:10px;">
            <a href="forgot_password.php" style="color:#667eea; text-decoration:underline; font-size:14px;">Forgot Password?</a>
        </div>
        
        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign up</a>
        </div>
        
        <div class="security-info">
            <strong>Secure Login:</strong> Your session is protected with enhanced security measures.
        </div>
        
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
    </div>
</body>
</html>
