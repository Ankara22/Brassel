<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role_name'];

// Fetch all roles
$roles = [];
$result = $conn->query('SELECT role_id, role_name FROM roles');
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $role_id = intval($_POST['role_id']);
    $password = $_POST['password'];
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    if (!$new_username || !$first_name || !$last_name || !$email || !$role_id || !$password) {
        $error = 'All fields are required.';
    } else {
        // Check for duplicate username or email
        $check = $conn->prepare('SELECT COUNT(*) FROM users WHERE username=? OR email=?');
        $check->bind_param('ss', $new_username, $email);
        $check->execute();
        $check->bind_result($exists);
        $check->fetch();
        $check->close();
        if ($exists > 0) {
            $error = 'Username or email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            // Get role name for this role_id
            $role_name = '';
            foreach ($roles as $r) {
                if ($r['role_id'] == $role_id) {
                    $role_name = $r['role_name'];
                    break;
                }
            }
            $insert = $conn->prepare('INSERT INTO users (username, first_name, last_name, email, role_id, password, status) VALUES (?, ?, ?, ?, ?, ?, "active")');
            $insert->bind_param('ssssis', $new_username, $first_name, $last_name, $email, $role_id, $hashed);
            $insert->execute();
            $user_id = $conn->insert_id;
            $insert->close();
            // If Employee, insert into employees table
            if (strtolower($role_name) === 'employee') {
                $full_name = $first_name . ' ' . $last_name;
                // Fetch leave defaults from settings
                $annual = 21; $sick = 7; $personal = 5;
                $res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('annual_leave','sick_leave','personal_leave')");
                while ($row = $res->fetch_assoc()) {
                    if ($row['setting_key'] === 'annual_leave') $annual = (int)$row['setting_value'];
                    if ($row['setting_key'] === 'sick_leave') $sick = (int)$row['setting_value'];
                    if ($row['setting_key'] === 'personal_leave') $personal = (int)$row['setting_value'];
                }
                $emp_stmt = $conn->prepare('INSERT INTO employees (user_id, name, department, designation, annual_leave, sick_leave, personal_leave) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $emp_stmt->bind_param('isssiii', $user_id, $full_name, $department, $designation, $annual, $sick, $personal);
                $emp_stmt->execute();
                $emp_stmt->close();
            }
            $success = 'User added successfully!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add User - Brassel Admin</title>
  <link rel="stylesheet" href="css/employee.css" />
  <script src="https://kit.fontawesome.com/6c6d3ac6f6.js" crossorigin="anonymous"></script>
  <style>
    .admin-layout { display: flex; min-height: 100vh; }
    .sidebar { width: 220px; background: #f8f9fa; border-right: 1px solid #eee; padding: 30px 0 0 0; }
    .sidebar ul { list-style: none; padding: 0; }
    .sidebar li { margin-bottom: 18px; }
    .sidebar a { display: block; padding: 12px 30px; color: #333; font-weight: 600; text-decoration: none; border-left: 4px solid transparent; transition: background 0.2s, border 0.2s; }
    .sidebar a.active, .sidebar a:hover { background: #eaf6ff; border-left: 4px solid #3498db; color: #217dbb; }
    .main-content { flex: 1; padding: 40px 40px 40px 40px; background: #f4f7fb; min-height: 100vh; }
    .card { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px #eee; padding: 30px 30px 20px 30px; margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto; }
    .section-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 18px; }
    .add-user-form label { display: block; margin-top: 15px; font-weight: 600; }
    .add-user-form input, .add-user-form select { width: 100%; padding: 8px 10px; margin-top: 5px; border-radius: 5px; border: 1px solid #ccc; }
    .add-user-form button { margin-top: 22px; background: #3498db; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; font-weight: 600; cursor: pointer; }
    .add-user-form button:hover { background: #217dbb; }
    .add-user-form .cancel-link { margin-left: 18px; color: #888; text-decoration: underline; }
    .error-msg { color: #e74c3c; margin-top: 10px; font-weight: 600; }
    .success-msg { color: #27ae60; margin-top: 10px; font-weight: 600; }
  </style>
</head>
<body>
  <!-- Header -->
  <header class="header">
    <div class="container">
      <div class="header-left">
        <h1 class="branding"><span class="logo">Brassel</span> Admin Portal</h1>
      </div>
      <div class="header-right">
        <span style="margin-right: 20px;">Welcome, <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
        <a href="logout.php" class="logout-btn">Logout →</a>
      </div>
    </div>
  </header>
  <div class="admin-layout">
    <!-- Sidebar Navigation -->
    <nav class="sidebar">
      <ul>
        <li><a href="admin.php"><i class="fas fa-cog"></i> Overview</a></li>
        <li><a href="usermgnt.php" class="active"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="roles.php"><i class="fas fa-user-shield"></i> Roles</a></li>
        <li><a href="activity.php"><i class="fas fa-history"></i> Activity Log</a></li>
        <li><a href="settings.php"><i class="fas fa-cogs"></i> Settings</a></li>
      </ul>
    </nav>
    <!-- Main Content Area -->
    <main class="main-content">
      <div class="card">
        <div class="section-title">Add New User</div>
        <?php if ($error): ?>
          <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form class="add-user-form" method="POST">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required />
          <label for="first_name">First Name</label>
          <input type="text" id="first_name" name="first_name" required />
          <label for="last_name">Last Name</label>
          <input type="text" id="last_name" name="last_name" required />
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required />
          <label for="role_id">Role</label>
          <select id="role_id" name="role_id" required onchange="toggleEmployeeFields()">
            <option value="">Select Role</option>
            <?php foreach ($roles as $role): ?>
              <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
            <?php endforeach; ?>
          </select>
          <div id="employee-fields" style="display:none;">
            <label for="department">Department</label>
            <input type="text" id="department" name="department" />
            <label for="designation">Designation</label>
            <input type="text" id="designation" name="designation" />
          </div>
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required />
          <button type="submit">Add User</button>
          <a href="usermgnt.php" class="cancel-link">Cancel</a>
        </form>
        <?php if ($success): ?>
          <div class="success-msg" style="color:#27ae60; margin-top:18px; font-weight:600;"> <?php echo htmlspecialchars($success); ?> </div>
          <script>
            setTimeout(function(){ window.location.href = 'usermgnt.php'; }, 1500);
          </script>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script>
    function toggleEmployeeFields() {
      var roleSelect = document.getElementById('role_id');
      var empFields = document.getElementById('employee-fields');
      var selected = roleSelect.options[roleSelect.selectedIndex].text.toLowerCase();
      if (selected === 'employee') {
        empFields.style.display = '';
        document.getElementById('department').required = true;
        document.getElementById('designation').required = true;
      } else {
        empFields.style.display = 'none';
        document.getElementById('department').required = false;
        document.getElementById('designation').required = false;
      }
    }
    document.addEventListener('DOMContentLoaded', function() {
      toggleEmployeeFields();
    });
  </script>
</body>
</html> 