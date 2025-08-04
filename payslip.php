<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get the logged-in user's user_id
$user_id = $_SESSION['user_id'];

// Map user_id to employee_id
$emp_id = null;
$emp_sql = "SELECT id, name, department, designation, hourly_rate FROM employees WHERE user_id = ? LIMIT 1";
$emp_stmt = $conn->prepare($emp_sql);
$emp_stmt->bind_param('i', $user_id);
$emp_stmt->execute();
$emp_stmt->bind_result($emp_id, $emp_name, $emp_department, $emp_designation, $hourly_rate);
$emp_stmt->fetch();
$emp_stmt->close();

if (!$emp_id) {
    echo 'Employee record not found.';
    exit();
}

// PDF download logic
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    require_once('fpdf.php');
    $payroll_id = isset($_GET['payroll_id']) ? intval($_GET['payroll_id']) : null;
    if ($payroll_id) {
        $sql = "SELECT * FROM employee_payroll WHERE id = ? AND employee_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $payroll_id, $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payslip = $result->fetch_assoc();
        $stmt->close();
        if (!$payslip) {
            echo 'Payslip not found for this payroll ID and your account.';
            exit();
        }
    } else {
        echo 'Invalid payslip request.';
        exit();
    }

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'Payslip',0,1,'C');
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,8,"Name: $emp_name",0,1);
    $pdf->Cell(0,8,"Designation: $emp_designation",0,1);
    $pdf->Cell(0,8,"Department: $emp_department",0,1);
    $pdf->Cell(0,8,"Pay Period: {$payslip['pay_period_start']} to {$payslip['pay_period_end']}",0,1);
    $pdf->Ln(4);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(80,8,'Description',1);
    $pdf->Cell(40,8,'Amount (KES)',1,1);
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(80,8,'Total Hours Worked',1);
    $pdf->Cell(40,8,$payslip['total_hours'],1,1);
    $pdf->Cell(80,8,'Hourly Rate',1);
    $pdf->Cell(40,8,number_format($payslip['hourly_rate'],2),1,1);
    $pdf->Cell(80,8,'Gross Pay',1);
    $pdf->Cell(40,8,number_format($payslip['gross_pay'],2),1,1);
    $pdf->Cell(80,8,'Bonuses',1);
    $pdf->Cell(40,8,number_format($payslip['bonus'],2),1,1);
    $pdf->Cell(80,8,'PAYE (Tax)',1);
    $pdf->Cell(40,8,number_format($payslip['paye'],2),1,1);
    $pdf->Cell(80,8,'NHIF',1);
    $pdf->Cell(40,8,number_format($payslip['nhif'],2),1,1);
    $pdf->Cell(80,8,'NSSF Tier I',1);
    $pdf->Cell(40,8,number_format($payslip['nssf_tier1'],2),1,1);
    $pdf->Cell(80,8,'NSSF Tier II',1);
    $pdf->Cell(40,8,number_format($payslip['nssf_tier2'],2),1,1);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(80,8,'Net Pay',1);
    $pdf->Cell(40,8,number_format($payslip['net_pay'],2),1,1);
    $pdf->Output('D', 'Payslip.pdf');
    exit();
}

// Fetch latest payroll record for this employee
$sql = "SELECT * FROM employee_payroll WHERE employee_id = ? ORDER BY pay_period_end DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$payslip = $result->fetch_assoc();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .payslip-container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 32px; }
        .payslip-header { text-align: center; margin-bottom: 24px; }
        .payslip-header h2 { margin-bottom: 8px; }
        .payslip-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .payslip-table th, .payslip-table td { padding: 10px 8px; border-bottom: 1px solid #eee; text-align: left; }
        .payslip-table th { background: #f6f8fa; }
        .payslip-summary { font-size: 1.1em; margin-bottom: 16px; }
        .download-btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background 0.2s; }
        .download-btn:hover { background: #667eea; }
        .no-payslip { text-align: center; color: #888; margin: 40px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Your Payslip</h1>
        <p>View your latest salary details and deductions</p>
    </div>
    <div class="nav">
        <a href="employee.php">🏠 Dashboard</a>
        <a href="attendance.php">⏰ Attendance</a>
        <a href="leaverequest.php">🗓️ Leave</a>
        <a href="payslip.php">💰 Payslip</a>
        <a href="employee_reports.php">📄 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container payslip-container">
        <?php if ($payslip): ?>
            <div class="payslip-header">
                <h2>Payslip for <?php echo htmlspecialchars($emp_name); ?></h2>
                <div><?php echo htmlspecialchars($emp_designation); ?>, <?php echo htmlspecialchars($emp_department); ?></div>
                <div>Pay Period: <?php echo htmlspecialchars($payslip['pay_period_start']); ?> to <?php echo htmlspecialchars($payslip['pay_period_end']); ?></div>
            </div>
            <table class="payslip-table">
                <tr><th>Description</th><th>Amount (KES)</th></tr>
                <tr><td>Total Hours Worked</td><td><?php echo htmlspecialchars($payslip['total_hours']); ?></td></tr>
                <tr><td>Hourly Rate</td><td><?php echo number_format($payslip['hourly_rate'],2); ?></td></tr>
                <tr><td><strong>Gross Pay</strong></td><td><strong><?php echo number_format($payslip['gross_pay'],2); ?></strong></td></tr>
                <tr><td>Bonuses</td><td><?php echo number_format($payslip['bonus'],2); ?></td></tr>
                <tr><td>PAYE (Tax)</td><td><?php echo number_format($payslip['paye'],2); ?></td></tr>
                <tr><td>NHIF</td><td><?php echo number_format($payslip['nhif'],2); ?></td></tr>
                <tr><td>NSSF Tier I</td><td><?php echo number_format($payslip['nssf_tier1'],2); ?></td></tr>
                <tr><td>NSSF Tier II</td><td><?php echo number_format($payslip['nssf_tier2'],2); ?></td></tr>
                <tr><th>Net Pay</th><th><?php echo number_format($payslip['net_pay'],2); ?></th></tr>
            </table>
            <div class="payslip-summary">
                <strong>Net Pay: KES <?php echo number_format($payslip['net_pay'],2); ?></strong>
            </div>
            <a href="payslip.php?download=pdf" class="download-btn">Download as PDF</a>
        <?php else: ?>
            <div class="no-payslip">No payslip available for the current pay period.</div>
        <?php endif; ?>
    </div>
</body>
</html> 