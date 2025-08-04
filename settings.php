<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role_name'];

// Handle password change
$pw_success = '';
$pw_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    if (!$current || !$new || !$confirm) {
        $pw_error = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $pw_error = 'New passwords do not match.';
    } else {
        $stmt = $conn->prepare('SELECT password FROM users WHERE user_id = ?');
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($hash);
        if ($stmt->fetch() && password_verify($current, $hash)) {
            $stmt->close();
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $update->bind_param('si', $new_hash, $_SESSION['user_id']);
            $update->execute();
            $update->close();
            $pw_success = 'Password changed successfully!';
        } else {
            $pw_error = 'Current password is incorrect.';
            $stmt->close();
        }
    }
}

// Ensure settings table exists
$conn->query("CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value VARCHAR(255))");

// Handle system preferences
$pref_success = '';
$pref_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    $site_name = trim($_POST['site_name']);
    $time_zone = trim($_POST['time_zone']);
    $annual_leave = trim($_POST['annual_leave']);
    $sick_leave = trim($_POST['sick_leave']);
    $personal_leave = trim($_POST['personal_leave']);
    if (!$site_name || !$time_zone || $annual_leave === '' || $sick_leave === '' || $personal_leave === '') {
        $pref_error = 'All fields are required.';
    } else {
        $stmt = $conn->prepare('REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)');
        $stmt->bind_param('ss', $key, $val);
        $key = 'site_name'; $val = $site_name; $stmt->execute();
        $key = 'time_zone'; $val = $time_zone; $stmt->execute();
        $key = 'annual_leave'; $val = $annual_leave; $stmt->execute();
        $key = 'sick_leave'; $val = $sick_leave; $stmt->execute();
        $key = 'personal_leave'; $val = $personal_leave; $stmt->execute();
        $stmt->close();
        $pref_success = 'Preferences saved!';
    }
}
// Load preferences
$site_name = 'Brassel HR System';
$time_zone = 'Africa/Nairobi';
$annual_leave = 21;
$sick_leave = 7;
$personal_leave = 5;
$res = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $res->fetch_assoc()) {
    if ($row['setting_key'] === 'site_name' && $row['setting_value'] !== null) $site_name = $row['setting_value'];
    if ($row['setting_key'] === 'time_zone' && $row['setting_value'] !== null) $time_zone = $row['setting_value'];
    if ($row['setting_key'] === 'annual_leave' && $row['setting_value'] !== null) $annual_leave = $row['setting_value'];
    if ($row['setting_key'] === 'sick_leave' && $row['setting_value'] !== null) $sick_leave = $row['setting_value'];
    if ($row['setting_key'] === 'personal_leave' && $row['setting_value'] !== null) $personal_leave = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - Brassel System</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>Settings</h1>
        <p>Manage system and user settings</p>
    </div>
    <div class="nav">
        <a href="admin.php">🏠 Dashboard</a>
        <a href="usermgnt.php">👤 User Management</a>
        <a href="roles.php">🛡️ Roles</a>
        <a href="activity.php">📜 Activity Log</a>
        <a href="settings.php">⚙️ Settings</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
    <div class="container">
        <div class="settings-flex">
            <div class="card settings-card">
                <div class="section-title">Change Password</div>
                <?php if ($pw_success): ?><div class="success-msg"><?php echo htmlspecialchars($pw_success); ?></div><?php endif; ?>
                <?php if ($pw_error): ?><div class="error-msg"><?php echo htmlspecialchars($pw_error); ?></div><?php endif; ?>
                <form class="settings-form" method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required />
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required />
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required />
                    </div>
                    <button type="submit" name="change_password">Change Password</button>
                </form>
            </div>
            <div class="card settings-card">
                <div class="section-title">System Preferences</div>
                <?php if ($pref_success): ?><div class="success-msg"><?php echo htmlspecialchars($pref_success); ?></div><?php endif; ?>
                <?php if ($pref_error): ?><div class="error-msg"><?php echo htmlspecialchars($pref_error); ?></div><?php endif; ?>
                <form class="settings-form" method="POST">
                    <div class="form-group">
                        <label for="site_name">Site Name</label>
                        <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" required />
                    </div>
                    <div class="form-group">
                        <label for="time_zone">Time Zone</label>
                        <select id="time_zone" name="time_zone" required>
                            <?php
                            $zones = DateTimeZone::listIdentifiers();
                            foreach ($zones as $zone) {
                                $sel = ($zone === $time_zone) ? 'selected' : '';
                                echo "<option value=\"$zone\" $sel>$zone</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="annual_leave">Default Annual Leave Days</label>
                        <input type="number" id="annual_leave" name="annual_leave" min="0" value="<?php echo htmlspecialchars($annual_leave); ?>" required />
                    </div>
                    <div class="form-group">
                        <label for="sick_leave">Default Sick Leave Days</label>
                        <input type="number" id="sick_leave" name="sick_leave" min="0" value="<?php echo htmlspecialchars($sick_leave); ?>" required />
                    </div>
                    <div class="form-group">
                        <label for="personal_leave">Default Personal Leave Days</label>
                        <input type="number" id="personal_leave" name="personal_leave" min="0" value="<?php echo htmlspecialchars($personal_leave); ?>" required />
                    </div>
                    <button type="submit" name="save_preferences">Save Preferences</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>