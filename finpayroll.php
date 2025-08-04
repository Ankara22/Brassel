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

// Only allow Finance Officer or Admin to approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($role, ['Finance Officer', 'Admin'])) {
    if (isset($_POST['approve_batch_id'])) {
        $batch_id = intval($_POST['approve_batch_id']);
        $approved_by = $_SESSION['user_id'];
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE payroll_batches SET status='approved', approved_by=?, approved_at=? WHERE id=?");
        $stmt->bind_param('isi', $approved_by, $now, $batch_id);
        $stmt->execute();
        $stmt->close();
        $success = "Payroll batch #$batch_id approved.";
    } elseif (isset($_POST['reject_batch_id'])) {
        $batch_id = intval($_POST['reject_batch_id']);
        $stmt = $conn->prepare("UPDATE payroll_batches SET status='rejected' WHERE id=?");
        $stmt->bind_param('i', $batch_id);
        $stmt->execute();
        $stmt->close();
        $success = "Payroll batch #$batch_id rejected.";
    } elseif (isset($_POST['pay_batch_id'])) {
        $batch_id = intval($_POST['pay_batch_id']);
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE payroll_batches SET status='paid', approved_at=? WHERE id=?");
        $stmt->bind_param('si', $now, $batch_id);
        $stmt->execute();
        $stmt->close();
        $success = "Payroll batch #$batch_id marked as paid.";
    }
}

// Fetch all payroll batches
$payroll_batches = [];
$sql = "SELECT b.*, u.username AS created_by_name, u2.username AS approved_by_name FROM payroll_batches b LEFT JOIN users u ON b.created_by = u.user_id LEFT JOIN users u2 ON b.approved_by = u2.user_id ORDER BY b.created_at DESC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $payroll_batches[] = $row;
}

function get_batch_items($conn, $batch_id) {
    $items = [];
    $sql = "SELECT i.*, e.name FROM payroll_batch_items i LEFT JOIN employees e ON i.employee_id = e.id WHERE i.batch_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Finance Payroll - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>Finance Payroll</h1>
        <p>Review and process payroll</p>
    </div>
    <div class="nav">
       <a href="finance.php">🏠 Dashboard</a>
        <a href="finpayroll.php">💰 Payroll</a>
        <a href="finpayments.php">💸 Payments</a>
        <a href="finreports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
        <div class="card">
            <h2>Payroll Management</h2>
            <p class="subtext">Process and manage employee payroll</p>
            <?php if (!empty($success)): ?><div class="success-msg"><?php echo $success; ?></div><?php endif; ?>
            <?php foreach ($payroll_batches as $batch): ?>
            <?php if (in_array($batch['status'], ['pending', 'approved'])): ?>
            <div class="batch-card" style="background:#f8faff; border-radius:14px; box-shadow:0 2px 12px rgba(102,126,234,0.08); margin-bottom:28px; padding:24px 20px; border-left:6px solid <?php echo $batch['status']==='pending' ? '#fbbf24' : '#34d399'; ?>;">
              <div class="batch-title" style="font-size:1.2em; font-weight:600; color:#4a00e0; margin-bottom:6px;">Batch #<?php echo $batch['id']; ?> (<?php echo htmlspecialchars($batch['period_start']); ?> to <?php echo htmlspecialchars($batch['period_end']); ?>)</div>
              <div class="batch-meta" style="font-size:0.98em; color:#555; margin-bottom:10px;">
                Created by: <span style="font-weight:500; color:#222;"><?php echo htmlspecialchars($batch['created_by_name']); ?></span> |
                Status: <span class="badge" style="background:<?php echo $batch['status']==='pending' ? '#fbbf24' : '#34d399'; ?>; color:#fff; border-radius:6px; padding:2px 10px; font-size:0.95em; margin:0 4px; text-transform:capitalize; letter-spacing:0.5px;"> <?php echo htmlspecialchars($batch['status']); ?> </span> |
                Created: <?php echo htmlspecialchars($batch['created_at']); ?>
              </div>
              <div style="overflow-x:auto; max-width:100%; margin-bottom:10px;">
              <table class="batch-table" style="width:100%; border-collapse:collapse; background:#fff; border-radius:8px;">
                <thead>
                  <tr style="background:#e0e7ff; color:#222;">
                    <th>Employee</th>
                    <th>Base Salary</th>
                    <th>Bonuses</th>
                    <th>Deductions</th>
                    <th>Net Pay</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (get_batch_items($conn, $batch['id']) as $item): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo number_format($item['base_salary'],2); ?></td>
                    <td><?php echo number_format($item['bonuses'],2); ?></td>
                    <td><?php echo number_format($item['deductions'],2); ?></td>
                    <td><?php echo number_format($item['net_pay'],2); ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              </div>
              <?php if ($batch['status'] === 'pending' && in_array($role, ['Finance Officer', 'Admin'])): ?>
              <form method="POST" style="margin-top:10px;display:inline-block;" data-confirm="Are you sure you want to approve this payroll batch?">
                <input type="hidden" name="approve_batch_id" value="<?php echo $batch['id']; ?>">
                <button type="submit" class="approve-btn" style="background:#34d399; color:#fff; border:none; border-radius:6px; padding:8px 18px; font-weight:600; margin-right:8px;">Approve</button>
              </form>
              <form method="POST" style="margin-top:10px;display:inline-block;" data-confirm="Are you sure you want to reject this payroll batch?">
                <input type="hidden" name="reject_batch_id" value="<?php echo $batch['id']; ?>">
                <button type="submit" class="reject-btn" style="background:#f87171; color:#fff; border:none; border-radius:6px; padding:8px 18px; font-weight:600;">Reject</button>
              </form>
              <?php elseif ($batch['status'] === 'approved' && in_array($role, ['Finance Officer', 'Admin'])): ?>
              <form method="POST" style="margin-top:10px;display:inline-block;" data-confirm="Mark this batch as paid?">
                <input type="hidden" name="pay_batch_id" value="<?php echo $batch['id']; ?>">
                <button type="submit" class="pay-btn" style="background:#6366f1; color:#fff; border:none; border-radius:6px; padding:8px 18px; font-weight:600;">Mark as Paid</button>
              </form>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
function confirmAction(form, message) {
  if (confirm(message)) {
    form.submit();
  } else {
    return false;
  }
}
document.addEventListener('DOMContentLoaded', function() {
  // Attach confirmation to all relevant forms
  document.querySelectorAll('form[data-confirm]').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      var msg = form.getAttribute('data-confirm');
      confirmAction(form, msg);
    });
  });
});
</script>
</body>
</html>