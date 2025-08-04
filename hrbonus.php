<?php
session_start();
include 'db_connect.php';

// Redirect to login if not authenticated or not HR
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'HR Officer') {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role_name'];
$username = $_SESSION['username'];

// Handle bonus approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bonus_id'], $_POST['action'])) {
    $bonus_id = intval($_POST['bonus_id']);
    $action = $_POST['action'];
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    $hr_id = $_SESSION['user_id'];
    $now = date('Y-m-d H:i:s');
    
    $update_sql = "UPDATE bonuses SET status = ?, processed_by = ?, processed_at = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('sisi', $status, $hr_id, $now, $bonus_id);
    
    if ($update_stmt->execute()) {
        $success = "Bonus " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully.";
    } else {
        $error = "Error processing bonus request.";
    }
    $update_stmt->close();
}

// Fetch pending bonus allocations
$pending_bonuses = [];
$sql = "SELECT b.*, e.name as employee_name, e.department, u.username as supervisor_name 
        FROM bonuses b 
        JOIN employees e ON b.employee_id = e.id 
        JOIN users u ON b.allocated_by = u.user_id 
        WHERE b.status = 'pending' 
        ORDER BY b.allocated_by DESC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $pending_bonuses[] = $row;
}

// Fetch recently processed bonuses
$processed_bonuses = [];
$sql = "SELECT b.*, e.name as employee_name, e.department, u.username as supervisor_name, 
        u2.username as hr_name 
        FROM bonuses b 
        JOIN employees e ON b.employee_id = e.id 
        JOIN users u ON b.allocated_by = u.user_id 
        LEFT JOIN users u2 ON b.processed_by = u2.user_id 
        WHERE b.status IN ('approved', 'rejected') 
        ORDER BY b.processed_at DESC 
        LIMIT 20";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $processed_bonuses[] = $row;
}

// Calculate statistics
$total_pending = count($pending_bonuses);
$total_approved = 0;
$total_rejected = 0;
$total_amount_pending = 0;
$total_amount_approved = 0;

foreach ($pending_bonuses as $bonus) {
    $total_amount_pending += $bonus['bonus_amount'];
}

foreach ($processed_bonuses as $bonus) {
    if ($bonus['status'] === 'approved') {
        $total_approved++;
        $total_amount_approved += $bonus['bonus_amount'];
    } else {
        $total_rejected++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Bonus Management - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; }
        .stat-value { font-size: 2em; font-weight: bold; margin: 10px 0; }
        .stat-label { color: #666; font-size: 0.9em; }
        .bonus-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .bonus-table th, .bonus-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .bonus-table th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 500; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .action-buttons { display: flex; gap: 10px; }
        .btn-approve { background: #28a745; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8em; }
        .btn-reject { background: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8em; }
        .btn-approve:hover { background: #218838; }
        .btn-reject:hover { background: #c82333; }
        .section-title { font-size: 1.5em; font-weight: 600; margin-bottom: 20px; color: #333; }
        .success-msg { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .error-msg { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HR Bonus Management</h1>
        <p>Review and approve bonus allocations from supervisors</p>
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
    <div class="container" style="max-width:1200px; margin:40px auto; padding:40px 24px; background:rgba(255,255,255,0.95); border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,0.1);">
        <?php if (isset($success)): ?>
            <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" style="color: #ffc107;"><?php echo $total_pending; ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #28a745;"><?php echo $total_approved; ?></div>
                <div class="stat-label">Approved This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #dc3545;"><?php echo $total_rejected; ?></div>
                <div class="stat-label">Rejected This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #17a2b8;">KES <?php echo number_format($total_amount_pending, 2); ?></div>
                <div class="stat-label">Pending Amount</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #6f42c1;">KES <?php echo number_format($total_amount_approved, 2); ?></div>
                <div class="stat-label">Approved Amount</div>
            </div>
        </div>

        <!-- Pending Bonus Allocations -->
        <?php if (!empty($pending_bonuses)): ?>
        <div class="card">
            <h2 class="section-title">Pending Bonus Approvals</h2>
            <div style="overflow-x:auto; max-width:100%;">
            <table class="bonus-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Pay Period</th>
                        <th>Requested By</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (
                        $pending_bonuses as $bonus): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($bonus['employee_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($bonus['department']); ?></td>
                        <td><strong>KES <?php echo number_format($bonus['bonus_amount'], 2); ?></strong></td>
                        <td><?php echo htmlspecialchars($bonus['reason']); ?></td>
                        <td><?php echo date('M j', strtotime($bonus['pay_period_start'])); ?> - <?php echo date('M j, Y', strtotime($bonus['pay_period_end'])); ?></td>
                        <td><?php echo htmlspecialchars($bonus['supervisor_name']); ?></td>
                        <td><?php echo isset($bonus['allocated_at']) ? date('M j, Y', strtotime($bonus['allocated_at'])) : '<span style="color:#888">N/A</span>'; ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="bonus_id" value="<?php echo $bonus['id']; ?>">
                                <div class="action-buttons">
                                    <button type="submit" name="action" value="approve" class="btn-approve">✓ Approve</button>
                                    <button type="submit" name="action" value="reject" class="btn-reject">✗ Reject</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div style="text-align: center; padding: 40px; color: #666;">
                <p>No pending bonus approvals.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recently Processed Bonuses -->
        <?php if (!empty($processed_bonuses)): ?>
        <div class="card">
            <h2 class="section-title">Recently Processed Bonuses</h2>
            <table class="bonus-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Pay Period</th>
                        <th>Requested By</th>
                        <th>Status</th>
                        <th>Processed By</th>
                        <th>Date</th>
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
                        <td><strong>KES <?php echo number_format($bonus['bonus_amount'], 2); ?></strong></td>
                        <td><?php echo htmlspecialchars($bonus['reason']); ?></td>
                        <td><?php echo date('M j', strtotime($bonus['pay_period_start'])); ?> - <?php echo date('M j, Y', strtotime($bonus['pay_period_end'])); ?></td>
                        <td><?php echo htmlspecialchars($bonus['supervisor_name']); ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                        <td><?php echo htmlspecialchars($bonus['hr_name']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($bonus['processed_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 