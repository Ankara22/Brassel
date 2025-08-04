<?php
session_start();
include 'db_connect.php';

// Get employee ID from session
$employeeId = $_SESSION['user_id'];

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    // Clock In
    if ($action === 'clock_in') {
        // Insert clock-in time into the attendance table (legacy)
        // $stmt = $pdo->prepare("INSERT INTO attendance (id, clock_in) VALUES (?, NOW())");
        // $stmt->execute([$employeeId]);
        // Insert clock-in time into employee_attendance
        $work_date = date('Y-m-d');
        $clock_in = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO employee_attendance (employee_id, work_date, clock_in) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $employeeId, $work_date, $clock_in);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Clocked in successfully']);
    }

    // Clock Out
    elseif ($action === 'clock_out') {
        // Find the latest clock-in record for today without a clock_out
        $work_date = date('Y-m-d');
        $stmt = $conn->prepare("SELECT id, clock_in FROM employee_attendance WHERE employee_id = ? AND work_date = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
        $stmt->bind_param('is', $employeeId, $work_date);
        $stmt->execute();
        $stmt->bind_result($att_id, $clock_in);
        if ($stmt->fetch()) {
            $stmt->close();
            $clock_out = date('Y-m-d H:i:s');
            $in = new DateTime($clock_in);
            $out = new DateTime($clock_out);
            $interval = $out->diff($in);
            $hours = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
            $hours_worked = round($hours, 2);
            // Update the record with clock_out and hours_worked
            $update = $conn->prepare("UPDATE employee_attendance SET clock_out = ?, hours_worked = ? WHERE id = ?");
            $update->bind_param('sdi', $clock_out, $hours_worked, $att_id);
            $update->execute();
            $update->close();
            echo json_encode(['status' => 'success', 'message' => 'Clocked out successfully']);
        } else {
            $stmt->close();
            echo json_encode(['status' => 'error', 'message' => 'No clock-in record found for today.']);
        }
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>