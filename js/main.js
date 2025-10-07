// main.js - Enhanced Landing Page Scripts with Validation

// Initialize Bootstrap modals
let registerModal, loginModal;

document.addEventListener('DOMContentLoaded', () => {
    registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
    loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                document.querySelector(href)?.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Add parallax effect to hero section
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const heroSection = document.querySelector('.hero-section');
        if (heroSection) {
            heroSection.style.transform = `translateY(${scrolled * 0.5}px)`;
        }
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Show notification using Bootstrap Toast
function showNotification(message, type = 'success') {
    const toastEl = document.getElementById('notificationToast');
    const toastBody = toastEl.querySelector('.toast-body');
    const toastHeader = toastEl.querySelector('.toast-header');
    
    // Set message
    toastBody.innerHTML = message;
    
    // Set color based on type
    if (type === 'success') {
        toastHeader.style.background = 'linear-gradient(135deg, #11998e, #38ef7d)';
        toastHeader.style.color = 'white';
    } else if (type === 'error') {
        toastHeader.style.background = 'linear-gradient(135deg, #eb3349, #f45c43)';
        toastHeader.style.color = 'white';
    } else if (type === 'warning') {
        toastHeader.style.background = 'linear-gradient(135deg, #f093fb, #f5576c)';
        toastHeader.style.color = 'white';
    }
    
    const toast = new bootstrap.Toast(toastEl, {
        autohide: true,
        delay: 4000
    });
    
    toast.show();
}

// Show/Hide loading overlay
function showLoading(show = true) {
    const overlay = document.getElementById('loadingOverlay');
    overlay.classList.toggle('active', show);
}

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        button.classList.remove('fa-eye');
        button.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        button.classList.remove('fa-eye-slash');
        button.classList.add('fa-eye');
    }
}

// Switch between login and register modals
function switchToLogin() {
    registerModal.hide();
    setTimeout(() => loginModal.show(), 300);
}

function switchToRegister() {
    loginModal.hide();
    setTimeout(() => registerModal.show(), 300);
}

// Open modals
document.getElementById('registerBtn').addEventListener('click', () => {
    registerModal.show();
});

document.getElementById('loginBtn').addEventListener('click', () => {
    loginModal.show();
});

// Email validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Password strength checker
function checkPasswordStrength(password) {
    let strength = 0;
    const feedback = [];
    
    if (password.length >= 8) strength++;
    else feedback.push('At least 8 characters');
    
    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('One uppercase letter');
    
    if (/[a-z]/.test(password)) strength++;
    else feedback.push('One lowercase letter');
    
    if (/[0-9]/.test(password)) strength++;
    else feedback.push('One number');
    
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    else feedback.push('One special character');
    
    return { strength, feedback };
}

// Real-time password strength indicator
document.getElementById('regPassword')?.addEventListener('input', (e) => {
    const password = e.target.value;
    const { strength, feedback } = checkPasswordStrength(password);
    const feedbackEl = e.target.nextElementSibling.nextElementSibling;
    
    if (password.length > 0) {
        if (strength < 5) {
            feedbackEl.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>Missing: ${feedback.join(', ')}`;
            feedbackEl.style.color = '#f5576c';
        } else {
            feedbackEl.innerHTML = `<i class="fas fa-check-circle me-1"></i>Strong password!`;
            feedbackEl.style.color = '#38ef7d';
        }
    }
});

// Clear validation on input
function clearValidation(inputId) {
    const input = document.getElementById(inputId);
    input.classList.remove('is-invalid', 'is-valid');
    const feedback = input.parentElement.querySelector('.invalid-feedback');
    if (feedback) feedback.textContent = '';
}

// Set field validation state
function setFieldValidation(inputId, isValid, message = '') {
    const input = document.getElementById(inputId);
    const feedback = input.parentElement.querySelector('.invalid-feedback') || 
                     input.nextElementSibling;
    
    if (isValid) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        if (feedback) feedback.textContent = '';
    } else {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        if (feedback) feedback.textContent = message;
    }
}

// Handle Registration Form
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;
    
    // Reset validation
    clearValidation('regEmail');
    clearValidation('regPassword');
    clearValidation('regConfirmPassword');
    
    // Client-side validation
    let isValid = true;
    
    if (!email) {
        setFieldValidation('regEmail', false, 'Email is required');
        isValid = false;
    } else if (!validateEmail(email)) {
        setFieldValidation('regEmail', false, 'Please enter a valid email');
        isValid = false;
    }
    
    if (!password) {
        setFieldValidation('regPassword', false, 'Password is required');
        isValid = false;
    } else {
        const { strength } = checkPasswordStrength(password);
        if (strength < 5) {
            setFieldValidation('regPassword', false, 'Password does not meet requirements');
            isValid = false;
        }
    }
    
    if (!confirmPassword) {
        setFieldValidation('regConfirmPassword', false, 'Please confirm your password');
        isValid = false;
    } else if (password !== confirmPassword) {
        setFieldValidation('regConfirmPassword', false, 'Passwords do not match');
        isValid = false;
    }
    
    if (!isValid) return;
    
    // Show loading
    showLoading(true);
    
    try {
        const response = await fetch('php/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email,
                password: password,
                confirm_password: confirmPassword
            })
        });
        
        const data = await response.json();
        
        showLoading(false);
        
        if (data.success) {
            showNotification(data.message, 'success');
            registerModal.hide();
            document.getElementById('registerForm').reset();
            
            // Clear validation states
            document.querySelectorAll('#registerForm .form-control').forEach(input => {
                input.classList.remove('is-valid', 'is-invalid');
            });
            
            // Auto open login modal after 2 seconds
            setTimeout(() => {
                loginModal.show();
            }, 2000);
        } else {
            showNotification(data.message, 'error');
            
            // Highlight specific field if provided
            if (data.field) {
                if (data.field === 'email') {
                    setFieldValidation('regEmail', false, data.message);
                } else if (data.field === 'password') {
                    setFieldValidation('regPassword', false, data.message);
                } else if (data.field === 'confirm_password') {
                    setFieldValidation('regConfirmPassword', false, data.message);
                }
            }
        }
    } catch (error) {
        showLoading(false);
        showNotification('Network error. Please check your connection and try again.', 'error');
        console.error('Registration error:', error);
    }
});

// Handle Login Form
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    
    // Reset validation
    clearValidation('loginEmail');
    clearValidation('loginPassword');
    
    // Client-side validation
    let isValid = true;
    
    if (!email) {
        setFieldValidation('loginEmail', false, 'Email is required');
        isValid = false;
    } else if (!validateEmail(email)) {
        setFieldValidation('loginEmail', false, 'Please enter a valid email');
        isValid = false;
    }
    
    if (!password) {
        setFieldValidation('loginPassword', false, 'Password is required');
        isValid = false;
    }
    
    if (!isValid) return;
    
    // Show loading
    showLoading(true);
    
    try {
        const response = await fetch('php/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email,
                password: password
            })
        });
        
        const data = await response.json();
        
        showLoading(false);
        
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Store user info in localStorage
            localStorage.setItem('user_id', data.user_id);
            localStorage.setItem('user_email', data.email);
            localStorage.setItem('session_token', data.token);
            
            // Add success animation
            loginModal.hide();
            
            // Redirect to dashboard
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);
        } else {
            // Handle rate limiting
            if (data.locked_out) {
                showNotification(data.message, 'error');
                const submitBtn = document.querySelector('#loginForm button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-lock me-2"></i>Locked Out';
                
                // Re-enable after time
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard';
                }, data.time_remaining * 1000);
            } else {
                showNotification(data.message, 'error');
                
                // Show warning if attempts are low
                if (data.warning) {
                    const warningDiv = document.getElementById('attemptsWarning');
                    const warningText = document.getElementById('warningText');
                    warningText.textContent = data.warning;
                    warningDiv.classList.remove('d-none');
                    
                    setTimeout(() => {
                        warningDiv.classList.add('d-none');
                    }, 5000);
                }
                
                // Highlight fields
                setFieldValidation('loginEmail', false, '');
                setFieldValidation('loginPassword', false, '');
            }
        }
    } catch (error) {
        showLoading(false);
        showNotification('Network error. Please check your connection and try again.', 'error');
        console.error('Login error:', error);
    }
});

// Add input event listeners to clear validation on typing
['regEmail', 'regPassword', 'regConfirmPassword', 'loginEmail', 'loginPassword'].forEach(id => {
    const element = document.getElementById(id);
    if (element) {
        element.addEventListener('input', () => {
            element.classList.remove('is-invalid', 'is-valid');
        });
    }
});

// Add floating animation to career badges on hover
document.querySelectorAll('.career-badge').forEach((badge, index) => {
    badge.style.animationDelay = `${index * 0.1}s`;
    
    badge.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-10px) scale(1.1)';
    });
    
    badge.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
});

// Add ripple effect to buttons
document.querySelectorAll('.btn').forEach(button => {
    button.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple');
        
        this.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
    });
});

// Add CSS for ripple effect dynamically
const style = document.createElement('style');
style.textContent = `
    .btn {
        position: relative;
        overflow: hidden;
    }
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    }
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + K to open login
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        loginModal.show();
    }
    
    // Ctrl/Cmd + Shift + K to open register
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'K') {
        e.preventDefault();
        registerModal.show();
    }
});

// Add scroll animation to navbar
let lastScroll = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;
    
    if (currentScroll > 100) {
        navbar.style.background = 'rgba(15, 12, 41, 0.95)';
        navbar.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.3)';
    } else {
        navbar.style.background = 'rgba(15, 12, 41, 0.8)';
        navbar.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
    }
    
    lastScroll = currentScroll;
});

// Console easter egg
console.log('%c🎓 Career Copilot', 'font-size: 20px; font-weight: bold; color: #667eea;');
console.log('%cBuilt with ❤️ for aspiring professionals', 'font-size: 12px; color: #764ba2;');
console.log('%c\nKeyboard Shortcuts:\n• Ctrl+K: Open Login\n• Ctrl+Shift+K: Open Register', 'font-size: 11px; color: #999;');