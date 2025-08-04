// Function to update the clock status
function updateClockStatus(isClockedIn) {
  const statusText = document.querySelector('.status .text');
  const clockBtn = document.getElementById('clock-btn');
  const statusDot = document.querySelector('.status .dot');
  const locationElement = document.querySelector('.location');

  if (isClockedIn) {
    statusText.textContent = 'Clocked In';
    clockBtn.textContent = 'Clock Out';
    statusDot.classList.add('active');
    if (locationElement) {
      locationElement.innerHTML = '<i class="fas fa-map-marker-alt"></i> Location not available';
    }
  } else {
    statusText.textContent = 'Not Clocked In';
    clockBtn.textContent = 'Clock In';
    statusDot.classList.remove('active');
    if (locationElement) {
      locationElement.innerHTML = '<i class="fas fa-map-marker-alt"></i> Location not available';
    }
  }
}

// Function to check clock in status on page load
function checkClockStatus() {
  fetch('check_status.php')
    .then(response => response.json())
    .then(data => {
      updateClockStatus(data.isClockedIn);
    });
}

// Function to toggle clock in/out
async function toggleClock() {
  const clockBtn = document.getElementById('clock-btn');
  const action = clockBtn.textContent === 'Clock In' ? 'clock_in' : 'clock_out';

  const formData = new FormData();
  formData.append('action', action);

  try {
    const response = await fetch('clock_action.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    if (data.success) {
      updateClockStatus(action === 'clock_in');
      if (typeof loadRecentAttendance === 'function') {
        loadRecentAttendance(); // Refresh recent attendance if function exists
      }
    } else {
      alert(data.error || 'An error occurred.');
    }
  } catch (error) {
    console.error(error);
    alert('An error occurred.');
  }
}

document.addEventListener('DOMContentLoaded', function () {
  checkClockStatus();
  document.getElementById('clock-btn').addEventListener('click', toggleClock);
});