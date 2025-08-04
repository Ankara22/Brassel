<?php
session_start();
include 'db_connect.php';

// Get employee id from URL query parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Employee ID not provided.");
}

// Fetch employee details from the database
$sql = "SELECT * FROM employees WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Employee not found.");
}

$employee = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = $_POST['name'];
    $department = $_POST['department'];
    $designation = $_POST['designation'];

    // Update the database
    $updateSql = "UPDATE employees SET name = ?, department = ?, designation = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sssi", $newName, $department, $designation, $id);

    if ($updateStmt->execute()) {
        header("Location: hremployee.php");
        exit();
    } else {
        die("Error: " . $updateStmt->error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Employee</title>
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
    <h2>Edit Employee Details</h2>
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $id); ?>">
      <label for="name">Name:</label>
      <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($employee['name']); ?>" required><br><br>

      <label for="department">Department:</label>
      <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>" required><br><br>

      <label for="designation">Designation:</label>
      <input type="text" id="designation" name="designation" value="<?php echo htmlspecialchars($employee['designation']); ?>" required><br><br>

      <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
    </form>
  </main>
</body>
</html>