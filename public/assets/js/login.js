/**
 * Admin Login Page JavaScript
 * Handles login form submission and authentication
 */

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');

    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const errorDiv = document.getElementById('error');
            const successDiv = document.getElementById('success');
            const loadingDiv = document.getElementById('loading');
            const loginBtn = document.getElementById('loginBtn');

            const formData = new FormData(this);
            const data = {
                username: formData.get('username'),
                password: formData.get('password')
            };

            // Hide previous messages
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            loadingDiv.style.display = 'block';
            loginBtn.disabled = true;

            try {
                const response = await fetch('/admin/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    successDiv.textContent = result.message;
                    successDiv.style.display = 'block';
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1000);
                } else {
                    errorDiv.textContent = result.error?.message || result.message || 'Login failed';
                    errorDiv.style.display = 'block';
                }
            } catch (error) {
                errorDiv.textContent = 'Network error: ' + error.message;
                errorDiv.style.display = 'block';
            } finally {
                loadingDiv.style.display = 'none';
                loginBtn.disabled = false;
            }
        });
    }
});