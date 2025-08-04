<?php
session_start();
include 'db_connect.php';

// Only HR Officers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'HR Officer') {
    header('Location: login.php');
    exit();
}

// Handle supervisor assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'], $_POST['supervisor_id'])) {
    $employee_id = intval($_POST['employee_id']);
    $supervisor_id = intval($_POST['supervisor_id']);
    $stmt = $conn->prepare('UPDATE employees SET supervisor_id = ? WHERE id = ?');
    $stmt->bind_param('ii', $supervisor_id, $employee_id);
    $stmt->execute();
    $stmt->close();
    $success = 'Supervisor updated successfully!';
}

// Fetch all employees
$employees = [];
$sql = 'SELECT e.id, e.name, e.department, e.designation, e.supervisor_id, u2.first_name AS sup_first, u2.last_name AS sup_last FROM employees e LEFT JOIN users u2 ON e.supervisor_id = u2.user_id';
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

// Fetch all supervisors
$supervisors = [];
$sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = 'Supervisor' LIMIT 1)";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $supervisors[] = $row;
}

$username = $_SESSION['username'];
$role = $_SESSION['role_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Assign Supervisors - HR Portal</title>
  <link rel="stylesheet" href="css/index.css">
  <script src="https://kit.fontawesome.com/6c6d3ac6f6.js" crossorigin="anonymous"></script>
  <style>
    .assign-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 30px; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px #eee; overflow: hidden; }
    .assign-table th, .assign-table td { padding: 16px 14px; text-align: left; }
    .assign-table th { background: #f7faff; font-size: 1rem; font-weight: 700; border-bottom: 1px solid #e0e0e0; }
    .assign-table tr { border-bottom: 1px solid #f0f0f0; }
    .assign-table tr:last-child { border-bottom: none; }
    .assign-table tr:nth-child(even) { background: #fafbfc; }
    .assign-form { display: flex; gap: 10px; align-items: center; }
    .assign-form select { padding: 6px 10px; border-radius: 5px; border: 1px solid #ccc; font-size: 1rem; }
    .assign-form button { background: #3498db; color: #fff; border: none; padding: 7px 18px; border-radius: 4px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
    .assign-form button:hover { background: #217dbb; }
    .success-msg { color: #27ae60; font-weight: 600; margin-bottom: 15px; }
    .current-sup { color: #555; font-weight: 500; }
    .no-sup { color: #aaa; font-style: italic; }
    @media (max-width: 900px) {
      .assign-table th, .assign-table td { padding: 10px 6px; font-size: 0.95rem; }
      .card { padding: 10px !important; }
    }
  </style>
</head>
<body>
    <div class="header">
        <h1>Assign Supervisors</h1>
        <p>Assign or update supervisors for employees</p>
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
            <section class="assign-supervisors-section">
                <h2>Assign Supervisors to Employees</h2>
                <?php if (!empty($success)): ?><div class="success-msg"><?php echo $success; ?></div><?php endif; ?>
                <div style="overflow-x:auto;">
                <table class="assign-table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Department</th>
                      <th>Designation</th>
                      <th>Current Supervisor</th>
                      <th>Assign Supervisor</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($emp['name']); ?></td>
                      <td><?php echo htmlspecialchars($emp['department']); ?></td>
                      <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                      <td>
                        <?php if ($emp['supervisor_id']): ?>
                          <span class="current-sup"><?php echo htmlspecialchars($emp['sup_first'] . ' ' . $emp['sup_last']); ?></span>
                        <?php else: ?>
                          <span class="no-sup">None</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <form method="POST" class="assign-form">
                          <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>" />
                          <select name="supervisor_id" required>
                            <option value="">Select Supervisor</option>
                            <?php foreach ($supervisors as $sup): ?>
                              <option value="<?php echo $sup['user_id']; ?>" <?php if ($emp['supervisor_id'] == $sup['user_id']) echo 'selected'; ?>><?php echo htmlspecialchars($sup['name']); ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit"><i class="fas fa-save"></i> Save</button>
                        </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                </div>
            </section>
        </div>
    </div>
</body>
</html> 