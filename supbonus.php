<?php
session_start();
include 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role_name'];
$username = $_SESSION['username'];
$supervisor_id = $_SESSION['user_id'];

// Handle bonus allocation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_bonus'])) {
    $employee_id = intval($_POST['employee_id']);
    $bonus_amount = floatval($_POST['bonus_amount']);
    $reason = trim($_POST['bonus_reason']);
    $pay_period_start = $_POST['pay_period_start'];
    $pay_period_end = $_POST['pay_period_end'];
    
    // Validate inputs
    if ($bonus_amount <= 0 || empty($reason)) {
        $error = "Please provide valid bonus amount and reason.";
    } else {
        // Check if employee is under this supervisor
        $check_sql = "SELECT id FROM employees WHERE id = ? AND supervisor_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('ii', $employee_id, $supervisor_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            // Insert bonus allocation
            $insert_sql = "INSERT INTO bonuses (employee_id, bonus_amount, reason, pay_period_start, pay_period_end, allocated_by, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param('idsssi', $employee_id, $bonus_amount, $reason, $pay_period_start, $pay_period_end, $supervisor_id);
            
            if ($insert_stmt->execute()) {
                $success = "Bonus allocation submitted successfully! Awaiting HR approval.";
            } else {
                $error = "Error submitting bonus allocation. Please try again.";
            }
            $insert_stmt->close();
        } else {
            $error = "Invalid employee selected.";
        }
        $check_stmt->close();
    }
}

// Fetch team members
$team = [];
$sql = "SELECT e.id, e.name, e.department, e.designation FROM employees e WHERE e.supervisor_id = ? ORDER BY e.name";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $supervisor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $team[] = $row;
}
$stmt->close();

// Fetch pending bonus allocations
$pending_bonuses = [];
$sql = "SELECT b.*, e.name as employee_name, e.department 
        FROM bonuses b 
        JOIN employees e ON b.employee_id = e.id 
        WHERE b.allocated_by = ? AND b.status = 'pending' 
        ORDER BY b.allocated_by DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $supervisor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pending_bonuses[] = $row;
}
$stmt->close();

// Fetch approved/rejected bonuses
$processed_bonuses = [];
$sql = "SELECT b.*, e.name as employee_name, e.department 
        FROM bonuses b 
        JOIN employees e ON b.employee_id = e.id 
        WHERE b.allocated_by = ? AND b.status IN ('approved', 'rejected') 
        ORDER BY b.processed_at DESC 
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $supervisor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $processed_bonuses[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bonus Allocation - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .bonus-form { background: #fff; padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .form-group textarea { height: 100px; resize: vertical; }
        .bonus-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .bonus-table th, .bonus-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .bonus-table th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 500; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .section-title { font-size: 1.5em; font-weight: 600; margin-bottom: 20px; color: #333; }
        .error-msg { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .success-msg { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bonus Allocation Management</h1>
        <p>Allocate bonuses to your team members</p>
    </div>
    <div class="nav">
        <a href="supervisor.php">🏠 Dashboard</a>
        <a href="supattendance.php">⏰ Attendance</a>
        <a href="supattendance_report.php">📊 Reports</a>
        <a href="supbonus.php">💰 Bonuses</a>
        <a href="supleave.php">🗓️ Leave</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Bonus Allocation Form -->
        <div class="bonus-form">
            <h2 class="section-title">Allocate New Bonus</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="employee_id">Select Employee:</label>
                    <select name="employee_id" id="employee_id" required>
                        <option value="">Choose an employee...</option>
                        <?php foreach ($team as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['name']); ?> - <?php echo htmlspecialchars($member['department']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bonus_amount">Bonus Amount (KES):</label>
                    <input type="number" name="bonus_amount" id="bonus_amount" step="0.01" min="0" required placeholder="Enter bonus amount">
                </div>
                
                <div class="form-group">
                    <label for="bonus_reason">Reason for Bonus:</label>
                    <textarea name="bonus_reason" id="bonus_reason" required placeholder="Explain the reason for this bonus allocation..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="pay_period_start">Pay Period Start:</label>
                    <input type="date" name="pay_period_start" id="pay_period_start" required>
                </div>
                
                <div class="form-group">
                    <label for="pay_period_end">Pay Period End:</label>
                    <input type="date" name="pay_period_end" id="pay_period_end" required>
                </div>
                
                <button type="submit" name="allocate_bonus" class="btn-primary">Submit for HR Approval</button>
            </form>
        </div>

        <!-- Pending Bonus Allocations -->
        <?php if (!empty($pending_bonuses)): ?>
        <div class="card">
            <h2 class="section-title">Pending Bonus Allocations</h2>
            <table class="bonus-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Pay Period</th>
                        <th>Submitted</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_bonuses as $bonus): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($bonus['employee_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($bonus['department']); ?></td>
                        <td>KES <?php echo number_format($bonus['bonus_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($bonus['reason']); ?></td>
                        <td><?php echo date('M j', strtotime($bonus['pay_period_start'])); ?> - <?php echo date('M j, Y', strtotime($bonus['pay_period_end'])); ?></td>
                        <td><?php echo date('M j, Y', strtotime($bonus['allocated_at'])); ?></td>
                        <td><span class="status-badge status-pending">Pending HR Review</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Processed Bonus Allocations -->
        <?php if (!empty($processed_bonuses)): ?>
        <div class="card">
            <h2 class="section-title">Recent Bonus Decisions</h2>
            <table class="bonus-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Pay Period</th>
                        <th>Status</th>
                        <th>Processed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processed_bonuses as $bonus): 
                        $status_class = $bonus['status'] === 'approved' ? 'status-approved' : 'status-rejected';
                        $status_text = $bonus['status'] === 'approved' ? 'Approved' : 'Rejected';
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($bonus['employee_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($bonus['department']); ?></td>
                        <td>KES <?php echo number_format($bonus['bonus_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($bonus['reason']); ?></td>
                        <td><?php echo date('M j', strtotime($bonus['pay_period_start'])); ?> - <?php echo date('M j, Y', strtotime($bonus['pay_period_end'])); ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                        <td><?php echo date('M j, Y', strtotime($bonus['processed_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (empty($pending_bonuses) && empty($processed_bonuses)): ?>
        <div class="card">
            <div style="text-align: center; padding: 40px; color: #666;">
                <p>No bonus allocations found.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Set default pay period dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            document.getElementById('pay_period_start').value = startOfMonth.toISOString().split('T')[0];
            document.getElementById('pay_period_end').value = endOfMonth.toISOString().split('T')[0];
        });
    </script>
</body>
</html> 