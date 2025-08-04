<?php
session_start();
include 'db_connect.php';

// Fetch all employees from the database
$sql = "SELECT * FROM employees";
$result = $conn->query($sql);

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role_name'];
$username = $_SESSION['username'];

// At the top of the file, before HTML output, handle termination:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terminate_id'])) {
    $terminate_id = intval($_POST['terminate_id']);
    // Set status to inactive (add status column if not exists)
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'");
    $stmt = $conn->prepare("UPDATE employees SET status = 'inactive' WHERE id = ?");
    $stmt->bind_param('i', $terminate_id);
    $stmt->execute();
    $stmt->close();
    // Optionally, delete from database:
    // $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
    // $stmt->bind_param('i', $terminate_id);
    // $stmt->execute();
    // $stmt->close();
    header('Location: hremployee.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Employees - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>HR Employees</h1>
        <p>Manage employee records and profiles</p>
    </div>
    <div class="nav">
        <a href="hrofficer.php">🏠 Dashboard</a>
        <a href="assign_supervisors.php">🧑‍💼 Assign Supervisors</a>
        <a href="hremployee.php">👥 Employees</a>
        <a href="hrattendance.php">⏰ Attendance</a>
        <a href="hrleave.php">🗓️ Leave</a>
        <a href="hrpayroll.php">💰 Payroll</a>
        <a href="hrbonus.php">🎁 Bonuses</a>
        <a href="hrreports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
        <div class="card">
            <!-- Main HR employee content goes here -->
            <section class="employee-management">
                <h2>Employee Management</h2>
                <p>Manage employee records and information</p>
                <div class="actions">
                    <button id="add-employee-btn" class="btn-primary"><i class="fas fa-plus"></i> Add Employee</button>
                </div>
                <div class="employee-list">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($employee = $result->fetch_assoc()): ?>
                            <div class="employee-card">
                                <div class="employee-info">
                                    <h3><?php echo htmlspecialchars($employee['name']); ?></h3>
                                    <p><?php echo htmlspecialchars($employee['department']); ?> - <?php echo htmlspecialchars($employee['designation']); ?></p>
                                </div>
                                <div class="employee-status">
                                    <span class="status-badge <?php echo (isset($employee['status']) && $employee['status'] === 'inactive') ? 'inactive' : 'active'; ?>">
                                        <?php echo (isset($employee['status']) && $employee['status'] === 'inactive') ? 'Inactive' : 'Active'; ?>
                                    </span>
                                    <button class="edit-btn" data-id="<?php echo $employee['id']; ?>">Edit</button>
                                    <form method="POST" action="hremployee.php" style="display:inline;">
                                        <input type="hidden" name="terminate_id" value="<?php echo $employee['id']; ?>">
                                        <button type="submit" class="terminate-btn" onclick="return confirm('Are you sure you want to terminate this employee?');">Terminate</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No employees found.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

<script>
  // Redirect to the add employee page when the "Add Employee" button is clicked
  document.getElementById('add-employee-btn').addEventListener('click', function() {
    window.location.href = 'add_employee.php';
  });

  // Redirect to the edit employee page when an "Edit" button is clicked
  const editButtons = document.querySelectorAll('.edit-btn');
  editButtons.forEach(button => {
    button.addEventListener('click', function() {
      const employeeId = button.getAttribute('data-id');
      window.location.href = `edit_employee.php?id=${employeeId}`;
    });
  });
</script>

<style>
.terminate-btn {
  background: #dc3545;
  color: #fff;
  border: none;
  padding: 8px 18px;
  border-radius: 4px;
  font-weight: 600;
  margin-left: 5px;
  cursor: pointer;
  transition: background 0.2s;
}
.terminate-btn:hover {
  background: #c82333;
}
.status-badge.inactive {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}
</style>

</body>
</html>