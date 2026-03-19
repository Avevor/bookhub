// Login form submission handler
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginform');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('login.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect based on role
                    if (data.role == 1) {
                        window.location.href = '../admin/admin_dashboard.php';
                    } else if (data.role == 2) {
                        window.location.href = '../sales-user/sales_dashboard.php';
                    } else {
                        window.location.href = '../pages/dashboard.php';
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    }

    // Toggle password visibility
    const togglePassword = document.querySelector('.toggle-password');
    const passwordInput = document.querySelector('input[name="password"]');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    }

    // Toggle login illustration background
    const illustration = document.querySelector('.login-illustration');
    if (illustration) {
        let isSolid = true;
        illustration.classList.add('solid-bg');

        setInterval(() => {
            if (isSolid) {
                illustration.classList.remove('solid-bg');
                illustration.classList.add('image-bg');
            } else {
                illustration.classList.remove('image-bg');
                illustration.classList.add('solid-bg');
            }
            isSolid = !isSolid;
        }, 15000);
    }
});
