<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php';

// 1. Total Employees
$total_employees = 0;
$added_this_month = 0;
$sql = "SELECT COUNT(*) as total FROM employees";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $total_employees = $row['total'];
}
$first_of_month = date('Y-m-01');
$sql = "SELECT COUNT(*) as added FROM employees WHERE DATE(created_at) >= ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $first_of_month);
$stmt->execute();
$stmt->bind_result($added_this_month);
$stmt->fetch();
$stmt->close();

// 2. Pending Leaves (awaiting HR approval)
$pending_leaves = 0;
$sql = "SELECT COUNT(*) as pending FROM leave_requests WHERE status = 'supervisor_approved'";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $pending_leaves = $row['pending'];
}

// 3. Present Today
$present_today = 0;
$attendance_percentage = 0;
$today = date('Y-m-d');
$sql = "SELECT COUNT(DISTINCT employee_id) as present FROM employee_attendance WHERE DATE(clock_in) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $today);
$stmt->execute();
$stmt->bind_result($present_today);
$stmt->fetch();
$stmt->close();
if ($total_employees > 0) {
    $attendance_percentage = round(($present_today / $total_employees) * 100, 1);
}

// 4. New Hires (this quarter)
$new_hires = 0;
$month = date('n');
$year = date('Y');
// Calculate the first month of the current quarter
$quarter_start_month = (floor(($month - 1) / 3) * 3) + 1;
$quarter_start = date('Y-m-d', strtotime("$year-$quarter_start_month-01"));
$sql = "SELECT COUNT(*) as hires FROM employees WHERE DATE(created_at) >= ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $quarter_start);
$stmt->execute();
$stmt->bind_result($new_hires);
$stmt->fetch();
$stmt->close();

// Fetch all payroll batches for history
$payroll_batches = [];
$sql = "SELECT b.*, u.username AS created_by_name, u2.username AS approved_by_name FROM payroll_batches b LEFT JOIN users u ON b.created_by = u.user_id LEFT JOIN users u2 ON b.approved_by = u2.user_id ORDER BY b.created_at DESC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $payroll_batches[] = $row;
}
?>
<main class="content">
  <div class="dashboard-container">
    <!-- Metrics Cards -->
    <div class="metrics-container" style="display: flex; gap: 32px; justify-content: center; margin-top: 32px; flex-wrap: wrap;">
      <div class="metric-card" style="flex:1; min-width:220px; background: linear-gradient(135deg, #f8fafc 0%, #a1c4fd 100%); border-radius: 16px; box-shadow: 0 4px 24px rgba(102,126,234,0.08); padding: 32px; text-align: center;">
        <i class="fas fa-users" style="font-size: 2em; color: #764ba2;"></i>
        <h3 style="margin: 12px 0 6px 0; font-size: 1.2em; color: #764ba2;">Total Employees</h3>
        <p class="value" style="font-size: 2.2em; font-weight: 700; color: #333; margin-bottom: 6px;"><?php echo $total_employees; ?></p>
        <p class="subtext" style="color: #888; font-size: 1em;">+<?php echo $added_this_month; ?> this month</p>
      </div>
      <div class="metric-card" style="flex:1; min-width:220px; background: linear-gradient(135deg, #fdf6e3 0%, #fcb69f 100%); border-radius: 16px; box-shadow: 0 4px 24px rgba(230,126,34,0.08); padding: 32px; text-align: center;">
        <i class="far fa-calendar-check" style="font-size: 2em; color: #e67e22;"></i>
        <h3 style="margin: 12px 0 6px 0; font-size: 1.2em; color: #e67e22;">Pending Leaves</h3>
        <p class="value orange" style="font-size: 2.2em; font-weight: 700; color: #e67e22; margin-bottom: 6px;"><?php echo $pending_leaves; ?></p>
        <p class="subtext" style="color: #888; font-size: 1em;">Awaiting approval</p>
      </div>
      <div class="metric-card" style="flex:1; min-width:220px; background: linear-gradient(135deg, #e0eafc 0%, #a8edea 100%); border-radius: 16px; box-shadow: 0 4px 24px rgba(39,174,96,0.08); padding: 32px; text-align: center;">
        <i class="far fa-clock" style="font-size: 2em; color: #27ae60;"></i>
        <h3 style="margin: 12px 0 6px 0; font-size: 1.2em; color: #27ae60;">Present Today</h3>
        <p class="value green" style="font-size: 2.2em; font-weight: 700; color: #27ae60; margin-bottom: 6px;"><?php echo $present_today; ?></p>
        <p class="subtext" style="color: #888; font-size: 1em;"><?php echo $attendance_percentage; ?>% attendance</p>
      </div>
      <div class="metric-card" style="flex:1; min-width:220px; background: linear-gradient(135deg, #f8fafc 0%, #a8edea 100%); border-radius: 16px; box-shadow: 0 4px 24px rgba(118,75,162,0.08); padding: 32px; text-align: center;">
        <i class="fas fa-user-plus" style="font-size: 2em; color: #764ba2;"></i>
        <h3 style="margin: 12px 0 6px 0; font-size: 1.2em; color: #764ba2;">New Hires</h3>
        <p class="value purple" style="font-size: 2.2em; font-weight: 700; color: #764ba2; margin-bottom: 6px;"><?php echo $new_hires; ?></p>
        <p class="subtext" style="color: #888; font-size: 1em;">This quarter</p>
      </div>
    </div>
    <!-- Payroll Batch Status & History -->
    <div class="payroll-batch-history" style="margin-top:40px;">
      <h2 style="margin-bottom:12px;">Payroll Batch Status & History</h2>
      <div style="margin-bottom:16px;">
        <a href="export_payroll_batches.php?format=csv" class="export-btn" style="display:inline-block;padding:8px 18px;margin-right:10px;background:#4a90e2;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">Export CSV</a>
        <a href="export_payroll_batches.php?format=excel" class="export-btn" style="display:inline-block;padding:8px 18px;background:#43e97b;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">Export Excel</a>
      </div>
      <table style="width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(44,0,80,0.07);">
        <thead style="background:#f5f6fa;">
          <tr>
            <th style="padding:10px 8px; text-align:left;">Batch ID</th>
            <th style="padding:10px 8px; text-align:left;">Period</th>
            <th style="padding:10px 8px; text-align:left;">Status</th>
            <th style="padding:10px 8px; text-align:left;">Created By</th>
            <th style="padding:10px 8px; text-align:left;">Created At</th>
            <th style="padding:10px 8px; text-align:left;">Approved By</th>
            <th style="padding:10px 8px; text-align:left;">Approved/Paid/Transferred At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payroll_batches as $batch): ?>
          <tr>
            <td style="padding:8px 8px;">#<?php echo $batch['id']; ?></td>
            <td style="padding:8px 8px;"><?php echo htmlspecialchars($batch['period_start']) . ' to ' . htmlspecialchars($batch['period_end']); ?></td>
            <td style="padding:8px 8px;"><span style="font-weight:600; color:
              <?php
                if ($batch['status'] === 'pending') echo '#e67e22';
                elseif ($batch['status'] === 'approved') echo '#27ae60';
                elseif ($batch['status'] === 'paid') echo '#2980b9';
                elseif ($batch['status'] === 'transferred') echo '#8e44ad';
                else echo '#888';
              ?>;">
              <?php echo ucfirst($batch['status']); ?>
            </span></td>
            <td style="padding:8px 8px;"> <?php echo htmlspecialchars($batch['created_by_name']); ?> </td>
            <td style="padding:8px 8px;"> <?php echo htmlspecialchars($batch['created_at']); ?> </td>
            <td style="padding:8px 8px;"> <?php echo $batch['approved_by_name'] ? htmlspecialchars($batch['approved_by_name']) : '-'; ?> </td>
            <td style="padding:8px 8px;"> <?php echo $batch['approved_at'] ? htmlspecialchars($batch['approved_at']) : '-'; ?> </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<style>
@media (max-width: 900px) {
  .metrics-container { flex-direction: column !important; gap: 20px !important; }
}
</style> 