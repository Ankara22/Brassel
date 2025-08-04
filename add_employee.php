<?php
session_start();
include 'db_connect.php';

// Fetch supervisors for dropdown (always run this)
$supervisors = [];
$sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role_id = 2";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $supervisors[] = $row;
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $department = $_POST['department'];
    $designation = $_POST['designation'];

    // Validate inputs (basic validation)
    if (empty($name) || empty($department) || empty($designation)) {
        die("All fields are required.");
    }

    // Insert into the database
    $sql = "INSERT INTO employees (name, department, designation) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $name, $department, $designation);

    if ($stmt->execute()) {
        header("Location: hremployee.php");
        exit();
    } else {
        die("Error: " . $stmt->error);
    }
}

// Display the form
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add Employee</title>
  <link rel="stylesheet" href="../brassel/css/hremployee.css" />
  <script src="https://kit.fontawesome.com/6c6d3ac6f6.js " crossorigin="anonymous"></script>
</head>
<body>
  <header class="header">
    <div class="container">
      <div class="header-left">
        <h1 class="branding"><span class="logo">Brassel</span> HR Dashboard</h1>
      </div>
      <div class="header-right">
        <span style="margin-right: 20px;">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role_name']); ?>)</span>
        <a href="../logout.php" class="logout-btn">Logout →</a>
      </div>
    </div>
  </header>

  <main class="content">
    <h2>Add New Employee</h2>
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
      <label for="name">Name:</label>
      <input type="text" id="name" name="name" required><br><br>

      <label for="department">Department:</label>
      <input type="text" id="department" name="department" required><br><br>

      <label for="designation">Designation:</label>
      <input type="text" id="designation" name="designation" required><br><br>

      <label for="supervisor_id">Supervisor</label>
      <select name="supervisor_id" id="supervisor_id" required>
        <option value="">Select Supervisor</option>
        <?php foreach ($supervisors as $sup): ?>
          <option value="<?php echo $sup['user_id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Employee</button>
    </form>
  </main>
</body>
</html>