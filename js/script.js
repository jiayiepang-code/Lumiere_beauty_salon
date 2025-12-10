/* ========================
   1. FORM SWITCHING & NAVIGATION
   ======================== */

let currentStep = 1;

// Switch to Customer Login
function showLogin() {
    // 1. Show Login Form, Hide others
    document.getElementById('authForm').style.display = 'none';
    document.getElementById('loginForm').style.display = 'block';

    // 2. Update Tabs (The Underline)
    // NOTE: Ensure your HTML <a> tags have id="tab-register" and id="tab-login"
    const tabReg = document.getElementById('tab-register');
    const tabLog = document.getElementById('tab-login');
    
    if (tabReg) tabReg.classList.remove('active');
    if (tabLog) tabLog.classList.add('active');

    // 3. Show floating buttons (Staff/Admin)
    const floatGroup = document.querySelector('.floating-btn-group');
    if (floatGroup) floatGroup.style.display = 'flex';
}

// Switch to Customer Register
function showRegister() {
    // 1. Show Register Form, Hide others
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('authForm').style.display = 'block';

    // 2. Update Tabs (The Underline)
    const tabReg = document.getElementById('tab-register');
    const tabLog = document.getElementById('tab-login');

    if (tabLog) tabLog.classList.remove('active');
    if (tabReg) tabReg.classList.add('active');

    // 3. Show floating buttons
    const floatGroup = document.querySelector('.floating-btn-group');
    if (floatGroup) floatGroup.style.display = 'flex';
    
    // 4. Reset to Step 1
    goToStep(1);
}

// Switch to Staff Portal
function showStaffLogin() {
    // Navigate to separate Staff Login page
    window.location.href = "../staff/login.php";
}

// Switch to Admin Portal
function showAdminLogin() {
    // Navigate to separate Admin Login page
    window.location.href = "../admin/login.php";
}

// Step navigation for Register Stepper
function goToStep(step) {
    // Remove active class from all steps
    document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active-step'));
    
    // Add active class to target step
    const targetStep = document.getElementById(`step-group-${step}`);
    if (targetStep) targetStep.classList.add('active-step');
    
    // Update text "Step X of 4"
    const stepCount = document.getElementById('stepCount');
    if (stepCount) stepCount.innerText = step;
    
    updateStepperVisuals(step);
    currentStep = step;
}

// Updates the circles (1-2-3-4) at the top
function updateStepperVisuals(step) {
    for (let i = 1; i <= 4; i++) {
        const circle = document.getElementById(`circle-${i}`);
        if (!circle) continue;

        circle.classList.remove('step-active', 'step-done');
        // Reset to number
        circle.innerHTML = `<span class="step-number">${i}</span>`;

        if (i < step) {
            // Completed steps get a checkmark
            circle.classList.add('step-done');
            circle.innerHTML = `✔`; // You can replace with <i class="fas fa-check"></i> if using FontAwesome
        } else if (i === step) {
            // Current step gets highlighted
            circle.classList.add('step-active');
        }
    }
}

/* ========================
   2. VALIDATION LOGIC
   ======================== */

// STEP 1: Personal Info (Names & Phone)
function validateStep1() {
    const fname = document.getElementById('firstName').value;
    const lname = document.getElementById('lastName').value;
    const phone = document.getElementById('phone').value;
    const errorBox = document.getElementById('step1Error');

    // 1. Check Empty Fields
    if(!fname || !lname || !phone) {
        errorBox.style.display = 'block';
        errorBox.innerText = "Please fill in all fields.";
        return;
    }

    // 2. Validate Malaysia Phone
    // Remove any spaces just in case
    const cleanPhone = phone.replace(/[\s-]/g, '');
    // Check if length is valid (9 to 12 digits)
    if (cleanPhone.length < 9 || cleanPhone.length > 12) {
        errorBox.style.display = 'block';
        errorBox.innerText = "Invalid phone number length.";
        return;
    }

    // Success -> Go to Step 2
    errorBox.style.display = 'none';
    goToStep(2);
}

// STEP 2: Email & Password
function validateStep2() {
    const email = document.getElementById('email');
    const pass = document.getElementById('password');
    const confirmPass = document.getElementById('confirmPassword');
    const errorBox = document.getElementById('step2Error');
    
    // Browser default validation check
    if (!email.checkValidity()) {
        return email.reportValidity();
    }
    if (pass.value === "") return pass.reportValidity();
    if (confirmPass.value === "") return confirmPass.reportValidity();

    // Check if passwords match
    if (pass.value !== confirmPass.value) {
        errorBox.style.display = 'block';
        errorBox.innerText = "Password and Confirm Password do not match.";
        return;
    }

    errorBox.style.display = 'none';
    goToStep(3);

    // Focus on CAPTCHA input if exists
    setTimeout(() => {
        const c = document.getElementById('registerCaptchaInput');
        if (c) c.focus();
    }, 300);
}

// STEP 3: CAPTCHA Verification & Register
function validateStep3() {
    // 1. Get all values from the HTML inputs
    var firstName = document.getElementById('firstName').value;
    var lastName  = document.getElementById('lastName').value;
    var phone     = document.getElementById('phone').value;
    var email     = document.getElementById('email').value;
    var password  = document.getElementById('password').value;
    var captcha   = document.getElementById('registerCaptchaInput').value;

    // 2. Package them into FormData
    var formData = new FormData();
    formData.append('firstName', firstName);
    formData.append('lastName', lastName);
    formData.append('phone', phone);
    formData.append('email', email);
    formData.append('password', password);
    formData.append('captcha', captcha);

    // 3. Send to PHP
    fetch('register.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check if response is ok
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        // Try to parse as JSON
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch(e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid response from server: ' + text.substring(0, 100));
            }
        });
    })
    .then(data => {
        if (data.status === 'success') {
            // Registration successful - redirect immediately to login page
            // Preserve redirect parameter if it exists in URL
            const urlParams = new URLSearchParams(window.location.search);
            const redirect = urlParams.get('redirect');
            const loginUrl = redirect ? 'login.php?redirect=' + encodeURIComponent(redirect) : 'login.php';
            window.location.href = loginUrl;
        } else {
            // Show error message (e.g., "Email already registered")
            document.getElementById('step3Error').innerText = data.message || 'An error occurred. Please try again.';
            document.getElementById('step3Error').style.display = 'block';
            
            // Refresh CAPTCHA because the old one is now invalid
            refreshRegisterCaptcha(); 
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('step3Error').innerText = 'Connection error. Please check your internet connection and try again.';
        document.getElementById('step3Error').style.display = 'block';
    });
}
/* ========================
   3. PASSWORD SYSTEM
   ======================== */

// Register Password Toggle
function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('passwordToggle');
    toggleInputType(input, icon);
}

// Confirm Password Toggle
function toggleConfirmPassword() {
    const input = document.getElementById("confirmPassword");
    const icon = document.getElementById("confirmPasswordToggle");
    toggleInputType(input, icon);
}

// Login Password Toggle
function toggleLoginPassword() {
    const input = document.getElementById("loginPassword");
    const icon = document.getElementById("loginPasswordToggle");
    toggleInputType(input, icon);
}

// Helper to switch type and icon
function toggleInputType(input, icon) {
    if (!input) return;
    
    if (input.type === "password") {
        input.type = "text";
        if (icon) icon.src = "images/73.png"; // Change to 'Eye Slash' or Open Eye
    } else {
        input.type = "password";
        if (icon) icon.src = "images/74.png"; // Change to 'Eye' or Closed Eye
    }
}

// Password Hints Popup Logic
const passwordInput = document.getElementById("password");
const passwordHints = document.getElementById("passwordHints");

if (passwordInput && passwordHints) {
    passwordInput.addEventListener("focus", () => {
        passwordHints.style.display = "block";
    });

    document.addEventListener("click", (e) => {
        // Hide hints if clicking outside input and hints box
        if (!passwordInput.contains(e.target) && !passwordHints.contains(e.target)) {
            passwordHints.style.display = "none";
        }
    });
}

// Password Strength Checker
function checkPasswordRules() {
    const password = passwordInput.value;
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    const strengthContainer = document.querySelector('.password-strength');

    // Show bars
    if (strengthContainer) strengthContainer.style.display = "block";
    if (text) text.style.display = "block";

    // Rules
    const ruleLength = document.getElementById("ruleLength");
    const ruleUpper = document.getElementById("ruleUpper");
    const ruleLower = document.getElementById("ruleLower");
    const ruleNumber = document.getElementById("ruleNumber");
    const ruleSpecial = document.getElementById("ruleSpecial");

    let hasLength = password.length >= 8;
    let hasUpper = /[A-Z]/.test(password);
    let hasLower = /[a-z]/.test(password);
    let hasNumber = /\d/.test(password);
    let hasSpecial = /[@$!%*?&_]/.test(password);

    // Toggle checkmark/color classes
    if(ruleLength) ruleLength.classList.toggle("valid", hasLength);
    if(ruleUpper) ruleUpper.classList.toggle("valid", hasUpper);
    if(ruleLower) ruleLower.classList.toggle("valid", hasLower);
    if(ruleNumber) ruleNumber.classList.toggle("valid", hasNumber);
    if(ruleSpecial) ruleSpecial.classList.toggle("valid", hasSpecial);

    let validCount = hasLength + hasUpper + hasLower + hasNumber + hasSpecial;

    // Reset Classes
    bar.className = 'password-strength-bar';

    if (validCount <= 1) {
        bar.style.width = '25%';
        bar.classList.add('strength-weak');
        text.innerText = "Weak";
        text.style.color = "#d9534f";
    } else if (validCount === 2) {
        bar.style.width = '50%';
        bar.classList.add('strength-fair');
        text.innerText = "Fair";
        text.style.color = "#f0ad4e";
    } else if (validCount === 3) {
        bar.style.width = '75%';
        bar.classList.add('strength-good');
        text.innerText = "Good";
        text.style.color = "#5bc0de";
    } else if (validCount >= 4) {
        bar.style.width = '100%';
        bar.classList.add('strength-strong');
        text.innerText = "Strong";
        text.style.color = "#5cb85c";
    }
}

// Remember Me Checkbox
function toggleCheckbox(box) {
    box.classList.toggle("checked");
}

/* ========================
   4. UTILITIES & LOGINS
   ======================== */

// --- PHONE NUMBER FORMATTER ---
function formatPhoneNumber(input) {
    // Strip non-digits
    let value = input.value.replace(/\D/g, '');

    // Remove leading zeros and country code 60 if user pasted it
    if (value.startsWith('60')) {
        value = value.slice(2);
    }
    value = value.replace(/^0+/, '');

    // Cap to 11 digits (max Malaysia local length)
    if (value.length > 11) {
        value = value.substring(0, 11);
    }

    // Apply formatting: 2-3-4 spacing
    if (value.length > 2 && value.length <= 5) {
        value = value.slice(0, 2) + " " + value.slice(2);
    } else if (value.length > 5) {
        value = value.slice(0, 2) + " " + value.slice(2, 5) + " " + value.slice(5);
    }

    input.value = value;
}

// CUSTOMER LOGIN LOGIC
function validateCustomerLogin() {
    var phone = document.getElementById('loginPhone').value;
    var password = document.getElementById('loginPassword').value;

    // Create standard form data (like a normal HTML form)
    var formData = new FormData();
    formData.append('phone', phone);
    formData.append('password', password);

    fetch('login.php', {
        method: 'POST',
        body: formData 
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid response from server: ' + text);
            }
        });
    })
    .then(data => {
        if (data.status === 'success') {
            const redirectField = document.getElementById('redirectUrl');
            const fallback = 'user/index.php';
            const target = redirectField && redirectField.value ? redirectField.value : fallback;
            window.location.href = target;
        } else {
            alert(data.message || 'Login failed.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert("System error. Please try again.");
    });
}

// CAPTCHA Refresh
function refreshRegisterCaptcha(event) {
    event.preventDefault(); // ⛔ stop page reload

    // Refresh CAPTCHA from root-level script
    fetch("refresh_captcha.php")
        .then(response => response.text())
        .then(newCode => {
            let captchaBox = document.getElementById("registerCaptchaCode");
            captchaBox.textContent = newCode;
            captchaBox.dataset.code = newCode;
        });
}