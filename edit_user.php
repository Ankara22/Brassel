<?php
session_start();
include 'db_connect.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

// Get user_id from GET
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    echo '<p>User not found.</p>';
    exit();
}
$user_id = intval($_GET['user_id']);

// Fetch user data
$stmt = $conn->prepare('SELECT username, first_name, last_name, email, role_id FROM users WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $first_name, $last_name, $email, $role_id);
if (!$stmt->fetch()) {
    echo '<p>User not found.</p>';
    $stmt->close();
    exit();
}
$stmt->close();

// Fetch all roles
$roles = [];
$result = $conn->query('SELECT role_id, role_name FROM roles');
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $new_first = trim($_POST['first_name']);
    $new_last = trim($_POST['last_name']);
    $new_email = trim($_POST['email']);
    $new_role = intval($_POST['role_id']);
    $update = $conn->prepare('UPDATE users SET username=?, first_name=?, last_name=?, email=?, role_id=? WHERE user_id=?');
    $update->bind_param('ssssii', $new_username, $new_first, $new_last, $new_email, $new_role, $user_id);
    $update->execute();
    $update->close();
    header('Location: admin.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit User</title>
  <link rel="stylesheet" href="css/employee.css" />
  <style>
    .edit-form-container { max-width: 500px; margin: 40px auto; background: #fff; padding: 30px 40px; border-radius: 10px; box-shadow: 0 2px 8px #eee; }
    .edit-form-container h2 { margin-bottom: 20px; }
    .edit-form-container label { display: block; margin-top: 15px; font-weight: 600; }
    .edit-form-container input, .edit-form-container select { width: 100%; padding: 8px 10px; margin-top: 5px; border-radius: 5px; border: 1px solid #ccc; }
    .edit-form-container button { margin-top: 20px; background: #3498db; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; font-weight: 600; cursor: pointer; }
    .edit-form-container button:hover { background: #217dbb; }
  </style>
</head>
<body>
  <div class="edit-form-container">
    <h2>Edit User</h2>
    <form method="POST">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required />
      <label for="first_name">First Name</label>
      <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required />
      <label for="last_name">Last Name</label>
      <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required />
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required />
      <label for="role_id">Role</label>
      <select id="role_id" name="role_id" required>
        <?php foreach ($roles as $role): ?>
          <option value="<?php echo $role['role_id']; ?>" <?php if ($role['role_id'] == $role_id) echo 'selected'; ?>><?php echo htmlspecialchars($role['role_name']); ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Save Changes</button>
      <a href="admin.php" style="margin-left:15px; color:#888; text-decoration:underline;">Cancel</a>
    </form>
  </div>
</body>
</html> 