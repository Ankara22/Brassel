// Toggle password visibility
document.querySelector('.toggle-password').addEventListener('click', function () {
  const passwordInput = document.getElementById('password');
  const icon = this.querySelector('i');

  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  } else {
    passwordInput.type = 'password';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  }
});

// role redirect
    document.getElementById('login-form').addEventListener('submit', function (event) {
      event.preventDefault();

      const role = document.getElementById('role').value;

      if (!role) {
        alert('Please select a role.');
        return;
      }

      // Redirect based on the selected role
      switch (role) {
        case 'Employee':
          window.location.href = 'employee.html';
          break;
        case 'Supervisor':
          window.location.href = 'supervisor.html';
          break;
        case 'Admin':
          window.location.href = 'admin.html';
          break;
          case 'Finance Officer ':
          window.location.href = 'admin.html';
          break;
          case 'HR Officer':
          window.location.href = 'admin.html';
          break;
        default:
          alert('Invalid role selection.');
      }
    });
