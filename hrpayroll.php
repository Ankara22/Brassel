<?php
session_start();
include 'db_connect.php';

// Only allow HR or Payroll Officer
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'], ['HR Officer', 'Payroll Officer', 'Admin'])) {
    header('Location: login.php');
    exit();
}

function calculatePAYE($gross) {
    // 2024 KRA PAYE bands (example, update as needed)
    $bands = [
        [0, 24000, 0.1],
        [24001, 32333, 0.25],
        [32334, PHP_INT_MAX, 0.3]
    ];
    $tax = 0;
    $remaining = $gross;
    foreach ($bands as $band) {
        $lower = $band[0]; $upper = $band[1]; $rate = $band[2];
        if ($gross > $lower) {
            $taxable = min($gross, $upper) - $lower;
            $tax += $taxable * $rate;
        }
    }
    return max(0, round($tax,2));
}

function calculateNHIF($gross) {
    // 2024 NHIF rates 
    $nhif_table = [
        [0, 5999, 150], [6000, 7999, 300], [8000, 11999, 400], [12000, 14999, 500],
        [15000, 19999, 600], [20000, 24999, 750], [25000, 29999, 850], [30000, 34999, 900],
        [35000, 39999, 950], [40000, 44999, 1000], [45000, 49999, 1100], [50000, 59999, 1200],
        [60000, 69999, 1300], [70000, 79999, 1400], [80000, 89999, 1500], [90000, 99999, 1600],
        [100000, PHP_INT_MAX, 1700]
    ];
    foreach ($nhif_table as $row) {
        if ($gross >= $row[0] && $gross <= $row[1]) return $row[2];
    }
    return 0;
}

function calculateNSSF($gross) {
    // NSSF Act 2013: Tier I (first 6,000), Tier II (next 12,000)
    $tier1 = min(6000, $gross) * 0.06;
    $tier2 = max(0, min(12000, $gross - 6000)) * 0.06;
    return [round($tier1,2), round($tier2,2)];
}

// Handle payroll calculation
$success = $error = '';
$payroll_breakdown = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_period_start'], $_POST['pay_period_end'])) {
    $start = $_POST['pay_period_start'];
    $end = $_POST['pay_period_end'];
    $user_id = $_SESSION['user_id'];
    // Create a new payroll batch
    $batch_sql = "INSERT INTO payroll_batches (period_start, period_end, created_by, created_at, status) VALUES (?, ?, ?, NOW(), 'pending')";
    $batch_stmt = $conn->prepare($batch_sql);
    $batch_stmt->bind_param('ssi', $start, $end, $user_id);
    if ($batch_stmt->execute()) {
        $batch_id = $batch_stmt->insert_id;
        $batch_stmt->close();
        // Get all employees
        $emp_sql = "SELECT id, user_id, hourly_rate, name FROM employees";
        $emps = $conn->query($emp_sql);
        $count = 0;
        while ($emp = $emps->fetch_assoc()) {
            $emp_id = $emp['id'];
            $user_id = $emp['user_id'];
            $hourly_rate = $emp['hourly_rate'];
            $emp_name = $emp['name'];
            // Sum hours worked in period
            $att_sql = "SELECT SUM(hours_worked) as total_hours FROM employee_attendance WHERE employee_id = ? AND work_date BETWEEN ? AND ?";
            $att_stmt = $conn->prepare($att_sql);
            $att_stmt->bind_param('iss', $emp_id, $start, $end);
            $att_stmt->execute();
            $att_stmt->bind_result($total_hours);
            $att_stmt->fetch();
            $att_stmt->close();
            $total_hours = $total_hours ?: 0;
            // Bonuses
            $bonus_sql = "SELECT SUM(bonus_amount) FROM bonuses WHERE employee_id = ? AND status = 'approved' AND pay_period_start <= ? AND pay_period_end >= ?";
            $bonus_stmt = $conn->prepare($bonus_sql);
            $bonus_stmt->bind_param('iss', $emp_id, $end, $start);
            $bonus_stmt->execute();
            $bonus_stmt->bind_result($bonus);
            $bonus_stmt->fetch();
            $bonus_stmt->close();
            $bonus = $bonus ?: 0;
            // Gross pay
            $base_salary = $total_hours * $hourly_rate;
            $gross = $base_salary + $bonus;
            // Deductions
            $paye = calculatePAYE($gross);
            $nhif = calculateNHIF($gross);
            list($nssf1, $nssf2) = calculateNSSF($gross);
            $deductions = $paye + $nhif + $nssf1 + $nssf2;
            $net = $gross - $deductions;
            // Insert into payroll_batch_items
            $item_sql = "INSERT INTO payroll_batch_items (batch_id, employee_id, base_salary, bonuses, deductions, net_pay) VALUES (?, ?, ?, ?, ?, ?)";
            $item_stmt = $conn->prepare($item_sql);
            $item_stmt->bind_param('iidddd', $batch_id, $emp_id, $gross, $bonus, $deductions, $net);
            $item_stmt->execute();
            $item_stmt->close();
            $count++;
            // Add to breakdown array
            $payroll_breakdown[] = [
                'name' => $emp_name,
                'total_hours' => $total_hours,
                'hourly_rate' => $hourly_rate,
                'base_salary' => $base_salary,
                'bonuses' => $bonus,
                'gross' => $gross,
                'paye' => $paye,
                'nhif' => $nhif,
                'nssf1' => $nssf1,
                'nssf2' => $nssf2,
                'deductions' => $deductions,
                'net' => $net
            ];
        }
        $success = "Payroll batch created and forwarded to Finance for $count employees for period $start to $end.";
    } else {
        $error = "Failed to create payroll batch.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Payroll Management - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .payroll-container { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 32px; }
        .payroll-header { text-align: center; margin-bottom: 24px; }
        .form-group { margin-bottom: 20px; }
        label { font-weight: 600; }
        input[type="date"] { padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; }
        button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        button:hover { background: #667eea; }
        .success { color: #27ae60; margin-bottom: 20px; text-align: center; }
        .error { color: #e74c3c; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HR Payroll Management</h1>
        <p>Calculate and manage payroll for all employees</p>
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
    <div class="container payroll-container">
        <div class="payroll-header">
            <h2>Calculate Payroll</h2>
            <p>Select a pay period and calculate payroll for all employees.</p>
        </div>
        <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="pay_period_start">Pay Period Start:</label>
                <input type="date" name="pay_period_start" id="pay_period_start" required>
            </div>
            <div class="form-group">
                <label for="pay_period_end">Pay Period End:</label>
                <input type="date" name="pay_period_end" id="pay_period_end" required>
            </div>
            <button type="submit">Calculate Payroll</button>
        </form>
    </div>
    <?php if (!empty($payroll_breakdown)): ?>
    <div class="container payroll-container" style="margin-top: 32px; max-width: 1200px;">
        <h3>Payroll Calculation Breakdown</h3>
        <div style="overflow-x:auto;">
        <table class="payroll-breakdown-table" style="width:100%; border-collapse:collapse; background:#fff; border-radius:8px; font-size:1.15em; border: 2px solid #b3b3b3;">
            <thead>
                <tr style="background:#e0e7ff; color:#222; font-size:1.18em; font-weight:bold;">
                    <th>Employee</th>
                    <th>Total Hours</th>
                    <th>Hourly Rate</th>
                    <th>Base Salary</th>
                    <th>Bonuses</th>
                    <th>Gross Pay</th>
                    <th>PAYE</th>
                    <th>NHIF</th>
                    <th>NSSF Tier I</th>
                    <th>NSSF Tier II</th>
                    <th>Total Deductions</th>
                    <th>Net Pay</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payroll_breakdown as $row): ?>
                <tr style="font-size:1.13em;">
                    <td style="border:1px solid #b3b3b3;"><?php echo htmlspecialchars($row['name']); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['total_hours'],2); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['hourly_rate'],2); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['base_salary'],2); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['bonuses'],2); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['gross'],2); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['paye'],2); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['nhif'],2); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['nssf1'],2); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['nssf2'],2); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['deductions'],2); ?></td>
                    <td style="border:1px solid #b3b3b3;"><?php echo number_format($row['net'],2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</body>
</html> 