<?php
include 'db_connect.php';
include 'security_config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role_name'];
$username = $_SESSION['username'];

// Get dashboard statistics based on user role
$stats = [];
$recent_activities = [];
$quick_actions = [];

// Get basic statistics
$sql = "SELECT COUNT(*) as total_employees FROM employees WHERE is_active = 1";
$result = $conn->query($sql);
$stats['total_employees'] = $result->fetch_assoc()['total_employees'];

$sql = "SELECT COUNT(*) as total_attendance FROM employee_attendance WHERE work_date = CURRENT_DATE()";
$result = $conn->query($sql);
$stats['today_attendance'] = $result->fetch_assoc()['total_attendance'];

$sql = "SELECT COUNT(*) as pending_leaves FROM leave_requests WHERE status = 'pending'";
$result = $conn->query($sql);
$stats['pending_leaves'] = $result->fetch_assoc()['pending_leaves'] ?? 0;

$sql = "SELECT SUM(net_pay) as monthly_payroll FROM payroll_batch_items pbi 
        JOIN payroll_batches pb ON pbi.batch_id = pb.id 
        WHERE MONTH(pb.created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(pb.created_at) = YEAR(CURRENT_DATE())
        AND pb.status IN ('approved', 'paid')";
$result = $conn->query($sql);
$stats['monthly_payroll'] = $result->fetch_assoc()['monthly_payroll'] ?? 0;

// Get recent activities
$sql = "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($sql);
$recent_activities = $result->fetch_all(MYSQLI_ASSOC);

// Set quick actions based on role
switch ($role) {
    case 'Admin':
        $quick_actions = [
            ['title' => 'Manage Users', 'url' => 'usermgnt.php', 'icon' => '👥'],
            ['title' => 'System Settings', 'url' => 'settings.php', 'icon' => '⚙️'],
            ['title' => 'Enhanced Reports', 'url' => 'enhanced_reports.php', 'icon' => '📊'],
            ['title' => 'Activity Logs', 'url' => 'activity.php', 'icon' => '📋']
        ];
        break;
    case 'Finance Officer':
        $quick_actions = [
            ['title' => 'Payroll Management', 'url' => 'finpayroll.php', 'icon' => '💰'],
            ['title' => 'Payment Processing', 'url' => 'finpayments.php', 'icon' => '💳'],
            ['title' => 'Financial Reports', 'url' => 'finreports.php', 'icon' => '📈'],
            ['title' => 'Enhanced Analytics', 'url' => 'enhanced_reports.php', 'icon' => '📊']
        ];
        break;
    case 'HR Officer':
        $quick_actions = [
            ['title' => 'Employee Management', 'url' => 'hremployee.php', 'icon' => '👤'],
            ['title' => 'Attendance Tracking', 'url' => 'hrattendance.php', 'icon' => '⏰'],
            ['title' => 'Leave Management', 'url' => 'hrleave.php', 'icon' => '📅'],
            ['title' => 'HR Reports', 'url' => 'enhanced_reports.php', 'icon' => '📊']
        ];
        break;
    case 'Supervisor':
        $quick_actions = [
            ['title' => 'Team Attendance', 'url' => 'supattendance.php', 'icon' => '⏰'],
            ['title' => 'Leave Approvals', 'url' => 'supleave.php', 'icon' => '📅'],
            ['title' => 'Team Reports', 'url' => 'enhanced_reports.php', 'icon' => '📊']
        ];
        break;
    case 'Employee':
        $quick_actions = [
            ['title' => 'My Attendance', 'url' => 'attendance.php', 'icon' => '⏰'],
            ['title' => 'Leave Request', 'url' => 'leaverequest.php', 'icon' => '📅'],
            ['title' => 'My Payslip', 'url' => 'employee.php', 'icon' => '💰'],
            ['title' => 'Profile Settings', 'url' => 'settings.php', 'icon' => '👤']
        ];
        break;
}

// Log dashboard access
logActivity($_SESSION['user_id'], $username, 'dashboard_access', 'Accessed enhanced dashboard', $conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Dashboard - HR System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-role {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .welcome-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .welcome-title {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .welcome-subtitle {
            color: #7f8c8d;
            font-size: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-card .change {
            font-size: 12px;
            color: #27ae60;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .action-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
            display: block;
        }

        .action-card h3 {
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .action-card p {
            color: #7f8c8d;
            font-size: 12px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .main-content {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
            font-size: 14px;
        }

        .activity-content h4 {
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .activity-content p {
            color: #7f8c8d;
            font-size: 12px;
        }

        .chart-container {
            height: 300px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .actions-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

        .notification {
            background: #3498db;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification.success {
            background: #27ae60;
        }

        .notification.warning {
            background: #f39c12;
        }

        .notification.error {
            background: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">HR System Dashboard</div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
                <div class="user-role"><?php echo htmlspecialchars($role); ?></div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($username); ?>!</h1>
            <p class="welcome-subtitle">Here's what's happening in your HR system today.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Employees</h3>
                <div class="value"><?php echo number_format($stats['total_employees']); ?></div>
                <div class="change">Active workforce</div>
            </div>
            <div class="stat-card">
                <h3>Today's Attendance</h3>
                <div class="value"><?php echo number_format($stats['today_attendance']); ?></div>
                <div class="change">Present today</div>
            </div>
            <div class="stat-card">
                <h3>Pending Leave Requests</h3>
                <div class="value"><?php echo number_format($stats['pending_leaves']); ?></div>
                <div class="change">Awaiting approval</div>
            </div>
            <div class="stat-card">
                <h3>Monthly Payroll</h3>
                <div class="value">$<?php echo number_format($stats['monthly_payroll'], 2); ?></div>
                <div class="change">This month's total</div>
            </div>
        </div>

        <div class="actions-grid">
            <?php foreach ($quick_actions as $action): ?>
                <a href="<?php echo $action['url']; ?>" class="action-card">
                    <span class="icon"><?php echo $action['icon']; ?></span>
                    <h3><?php echo $action['title']; ?></h3>
                    <p>Access this feature</p>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <h2 class="section-title">System Overview</h2>
                
                <?php if ($role === 'Admin' || $role === 'Finance Officer'): ?>
                    <div class="chart-container">
                        <canvas id="payrollChart"></canvas>
                    </div>
                <?php endif; ?>

                <?php if ($role === 'HR Officer' || $role === 'Supervisor'): ?>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sidebar">
                <h2 class="section-title">Recent Activity</h2>
                
                <?php if (!empty($recent_activities)): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php 
                                switch ($activity['action']) {
                                    case 'login':
                                        echo '🔐';
                                        break;
                                    case 'logout':
                                        echo '🚪';
                                        break;
                                    case 'payroll_access':
                                        echo '💰';
                                        break;
                                    case 'employee_add':
                                        echo '👤';
                                        break;
                                    case 'attendance_mark':
                                        echo '⏰';
                                        break;
                                    default:
                                        echo '📝';
                                }
                                ?>
                            </div>
                            <div class="activity-content">
                                <h4><?php echo htmlspecialchars($activity['username']); ?></h4>
                                <p><?php echo htmlspecialchars($activity['details']); ?></p>
                                <small><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #7f8c8d; text-align: center; padding: 20px;">No recent activity</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Initialize charts based on user role
        <?php if ($role === 'Admin' || $role === 'Finance Officer'): ?>
        const ctx = document.getElementById('payrollChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Monthly Payroll',
                    data: [<?php echo $stats['monthly_payroll']; ?>, 45000, 52000, 48000, 55000, <?php echo $stats['monthly_payroll']; ?>],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Payroll Trend (Last 6 Months)'
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($role === 'HR Officer' || $role === 'Supervisor'): ?>
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    data: [<?php echo $stats['today_attendance']; ?>, 5, 2],
                    backgroundColor: ['#27ae60', '#e74c3c', '#f39c12']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Today\'s Attendance'
                    }
                }
            }
        });
        <?php endif; ?>

        // Add some interactivity
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    </script>
</body>
</html> 