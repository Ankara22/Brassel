<?php
include 'db_connect.php';
include 'security_config.php';

// Check session timeout
checkSessionTimeout();

// Initialize variables
$error = '';
$success = '';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        // Get and sanitize form data
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $email = sanitizeInput($_POST['email']);
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = sanitizeInput($_POST['role']);
        $department = sanitizeInput($_POST['department'] ?? '');
        $designation = sanitizeInput($_POST['designation'] ?? '');

        // Validate inputs
        if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Validate password strength
            $password_errors = validatePasswordStrength($password);
            if (!empty($password_errors)) {
                $error = "Password requirements not met:<br>" . implode("<br>", $password_errors);
            } else {
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Please enter a valid email address.";
                } else {
                    $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("ss", $username, $email);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();

                    if ($result->num_rows > 0) {
                        $error = "Username or Email already exists.";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        $role_sql = "SELECT role_id FROM roles WHERE role_name = ?";
                        $role_stmt = $conn->prepare($role_sql);
                        $role_stmt->bind_param("s", $role);
                        $role_stmt->execute();
                        $role_result = $role_stmt->get_result();

                        if ($role_result->num_rows > 0) {
                            $role_row = $role_result->fetch_assoc();
                            $role_id = $role_row['role_id'];

                            $insert_sql = "INSERT INTO users (first_name, last_name, email, username, password_hash, role_id) VALUES (?, ?, ?, ?, ?, ?)";
                            $insert_stmt = $conn->prepare($insert_sql);
                            $insert_stmt->bind_param("sssssi", $first_name, $last_name, $email, $username, $hashed_password, $role_id);

                            if ($insert_stmt->execute()) {
                                $user_id = $conn->insert_id;
                                
                                // Log the account creation
                                logActivity($user_id, $username, 'account_created', 'New account created successfully', $conn);
                                
                                // If role is Employee, insert into employees table
                                if (strcasecmp(trim($role), 'Employee') === 0) {
                                    $full_name = $first_name . ' ' . $last_name;
                                    // Fetch leave defaults from settings
                                    $annual = 21; $sick = 7; $personal = 5;
                                    $res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('annual_leave','sick_leave','personal_leave')");
                                    while ($row = $res->fetch_assoc()) {
                                        if ($row['setting_key'] === 'annual_leave') $annual = (int)$row['setting_value'];
                                        if ($row['setting_key'] === 'sick_leave') $sick = (int)$row['setting_value'];
                                        if ($row['setting_key'] === 'personal_leave') $personal = (int)$row['setting_value'];
                                    }
                                    $emp_stmt = $conn->prepare("INSERT INTO employees (user_id, name, department, designation, annual_leave, sick_leave, personal_leave) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                    $emp_stmt->bind_param("isssiii", $user_id, $full_name, $department, $designation, $annual, $sick, $personal);
                                    $emp_stmt->execute();
                                    if ($emp_stmt->error) {
                                        die("Employee insert error: " . $emp_stmt->error);
                                    }
                                    $emp_stmt->close();
                                }
                                $success = "Account created successfully! You can now login.";
                            } else {
                                $error = "Error creating account. Please try again.";
                            }
                        } else {
                            $error = "Invalid role selected.";
                        }
                    }
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
    <title>HR System - Sign Up</title>
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
            width: 100%;
            max-width: 500px;
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
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }
        input, select {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        input:focus, select:focus {
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
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
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
        .employee-fields {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
            border: 2px solid #e1e8ed;
        }
        .password-strength {
            margin-top: 10px;
            height: 4px;
            background-color: #e1e8ed;
            border-radius: 2px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
            width: 0%;
        }
        .strength-weak { background-color: #e74c3c; }
        .strength-medium { background-color: #f39c12; }
        .strength-strong { background-color: #27ae60; }
        .strength-very-strong { background-color: #2ecc71; }
        
        @media (max-width: 768px) {
            .container {
                margin: 20px;
                padding: 30px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Your HR Account</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="signup.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="role">Select Role</label>
                <select id="role" name="role" required onchange="toggleEmployeeFields()">
                    <option value="" disabled selected>Select your role</option>
                    <option value="Employee">Employee</option>
                    <option value="Supervisor">Supervisor</option>
                    <option value="HR Officer">HR Officer</option>
                    <option value="Finance Officer">Finance Officer</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            
            <div id="employee-fields" class="employee-fields" style="display:none;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department">
                    </div>
                    
                    <div class="form-group">
                        <label for="designation">Designation</label>
                        <input type="text" id="designation" name="designation">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required onkeyup="checkPasswordStrength()">
                <div class="password-strength">
                    <div class="password-strength-bar" id="strength-bar"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required onkeyup="checkPasswordMatch()">
            </div>
            
            <button type="submit">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Log in</a>
        </div>
        
        <div class="security-info">
            <strong>Secure Registration:</strong> Your account is protected with enhanced security measures.
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

    <script>
        function toggleEmployeeFields() {
            var role = document.getElementById('role').value;
            var empFields = document.getElementById('employee-fields');
            var department = document.getElementById('department');
            var designation = document.getElementById('designation');
            
            if (role === 'Employee') {
                empFields.style.display = 'block';
                department.required = true;
                designation.required = true;
            } else {
                empFields.style.display = 'none';
                department.required = false;
                designation.required = false;
            }
        }
        
        function checkPasswordStrength() {
            var password = document.getElementById('password').value;
            var strengthBar = document.getElementById('strength-bar');
            var strength = 0;
            
            // Check length
            if (password.length >= 8) strength += 25;
            
            // Check for uppercase
            if (/[A-Z]/.test(password)) strength += 25;
            
            // Check for lowercase
            if (/[a-z]/.test(password)) strength += 25;
            
            // Check for numbers
            if (/[0-9]/.test(password)) strength += 25;
            
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            // Update strength bar
            strengthBar.style.width = Math.min(strength, 100) + '%';
            
            // Update color based on strength
            strengthBar.className = 'password-strength-bar';
            if (strength < 50) {
                strengthBar.classList.add('strength-weak');
            } else if (strength < 75) {
                strengthBar.classList.add('strength-medium');
            } else if (strength < 100) {
                strengthBar.classList.add('strength-strong');
            } else {
                strengthBar.classList.add('strength-very-strong');
            }
        }
        
        function checkPasswordMatch() {
            var password = document.getElementById('password').value;
            var confirmPassword = document.getElementById('confirm_password').value;
            var confirmField = document.getElementById('confirm_password');
            
            if (confirmPassword && password !== confirmPassword) {
                confirmField.style.borderColor = '#e74c3c';
            } else if (confirmPassword) {
                confirmField.style.borderColor = '#27ae60';
            } else {
                confirmField.style.borderColor = '#e1e8ed';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleEmployeeFields();
        });
    </script>
</body>
</html>


