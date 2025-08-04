// Function to update the current time
function updateTime() {
  const now = new Date();

  // Format time (HH:MM:SS)
  const hours = String(now.getHours()).padStart(2, '0');
  const minutes = String(now.getMinutes()).padStart(2, '0');
  const seconds = String(now.getSeconds()).padStart(2, '0');
  const formattedTime = `${hours}:${minutes}:${seconds}`;

  // Format date (Day, Month Date, Year)
  const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

  const dayName = days[now.getDay()];
  const monthName = months[now.getMonth()];
  const date = now.getDate();
  const year = now.getFullYear();
  const formattedDate = `${dayName}, ${monthName} ${date}, ${year}`;

  // Update the DOM elements
  document.getElementById('current-time').textContent = formattedTime;
  document.getElementById('current-date').textContent = formattedDate;
}

// Update time every second
setInterval(updateTime, 1000);

// Initial call to set the time
updateTime();