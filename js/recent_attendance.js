function loadRecentAttendance() {
    fetch('recent_attendance.php')
        .then(response => response.text())
        .then(html => {
            document.getElementById('attendance-table-body').innerHTML = html;
        })
        .catch(error => console.error('Error fetching attendance:', error));
}

// Load recent attendance on page load
loadRecentAttendance();