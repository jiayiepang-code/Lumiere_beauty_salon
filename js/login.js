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
    
    // If moving to step 2, disable Next Step button until password is strong
    if (step === 2) {
        const nextStepBtn = document.querySelector('#step-group-2 .submit-btn');
        if (nextStepBtn) {
            nextStepBtn.disabled = true;
            nextStepBtn.style.opacity = '0.5';
            nextStepBtn.style.cursor = 'not-allowed';
            // Reset password strength check
            isPasswordStrong = false;
            passwordStrength = 'weak';
        }
    }
    
    updateStepperVisuals(step);
    currentStep = step;
}

// Updates the circles (1-2-3-4) at the top
function updateStepperVisuals(step) {
    for (let i = 1; i <= 4; i++) {
        const circle = document.getElementById(`circle-${i}`);
        if (!circle) continue;

        circle.classList.remove('step-active', 'step-done');
        
        // Get or create the step-number span
        let stepNumber = circle.querySelector('.step-number');
        if (!stepNumber) {
            stepNumber = document.createElement('span');
            stepNumber.className = 'step-number';
            circle.appendChild(stepNumber);
        }

        if (i < step) {
            // Completed steps get a checkmark
            circle.classList.add('step-done');
            stepNumber.textContent = '✔';
        } else if (i === step) {
            // Current step gets highlighted
            circle.classList.add('step-active');
            stepNumber.textContent = i;
        } else {
            // Future steps show number
            stepNumber.textContent = i;
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
    if (pass.value === "") {
        errorBox.style.display = 'block';
        errorBox.innerText = "Please enter a password.";
        return;
    }
    if (confirmPass.value === "") {
        errorBox.style.display = 'block';
        errorBox.innerText = "Please confirm your password.";
        return;
    }

    // Check password strength - must be STRONG (all 5 rules met)
    if (!isPasswordStrong || passwordStrength !== 'strong') {
        errorBox.style.display = 'block';
        errorBox.innerText = "Password must be STRONG. Please ensure all password requirements are met: at least 8 characters, one uppercase letter, one lowercase letter, one number, and one symbol.";
        return;
    }

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
    const captchaInput = document.getElementById('registerCaptchaInput');
    const errorBox = document.getElementById('step3Error');
    const codeEl = document.getElementById('registerCaptchaCode');

    if (!captchaInput || !codeEl) {
        // Fallback: if elements missing, just move on
        goToStep(4);
        return;
    }

    // Case-sensitive CAPTCHA validation
    const expected = String(codeEl.dataset.code || '').trim();
    const entered = captchaInput.value.trim();

    if (entered === '') {
        errorBox.style.display = 'block';
        errorBox.innerText = "Please enter the CAPTCHA.";
        return;
    }

    if (entered !== expected) {
        errorBox.style.display = 'block';
        errorBox.innerText = "Incorrect CAPTCHA. Please check the case of letters.";
        return;
    }

    errorBox.style.display = 'none';

    // ✅ SIMULATE REGISTRATION SUCCESS
    goToStep(4); // Show "Registration Successful" view

    // AFTER 2 SECONDS, SWITCH TO LOGIN FORM with prefilled phone
    setTimeout(() => {
        const registeredPhone = document.getElementById('phone').value;
        const loginPhone = document.getElementById('loginPhone');
        if (loginPhone) {
            loginPhone.value = registeredPhone;
        }
        showLogin();
    }, 2000);
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
        if (icon) icon.src = "../images/73.png"; // Change to 'Eye Slash' or Open Eye
    } else {
        input.type = "password";
        if (icon) icon.src = "../images/74.png"; // Change to 'Eye' or Closed Eye
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

// Global variable to store password strength
let passwordStrength = 'weak';
let isPasswordStrong = false;

// Password Strength Checker
function checkPasswordRules() {
    const password = passwordInput.value;
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    const strengthContainer = document.querySelector('.password-strength');
    const nextStepBtn = document.querySelector('#step-group-2 .submit-btn');

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

    // Check if ALL 5 rules are met (strong password)
    isPasswordStrong = validCount === 5;

    if (validCount <= 1) {
        bar.style.width = '25%';
        bar.classList.add('strength-weak');
        text.innerText = "Weak";
        text.style.color = "#d9534f";
        passwordStrength = 'weak';
    } else if (validCount === 2) {
        bar.style.width = '50%';
        bar.classList.add('strength-fair');
        text.innerText = "Fair";
        text.style.color = "#f0ad4e";
        passwordStrength = 'fair';
    } else if (validCount === 3) {
        bar.style.width = '75%';
        bar.classList.add('strength-good');
        text.innerText = "Good";
        text.style.color = "#5bc0de";
        passwordStrength = 'good';
    } else if (validCount === 4) {
        bar.style.width = '90%';
        bar.classList.add('strength-good');
        text.innerText = "Good";
        text.style.color = "#5bc0de";
        passwordStrength = 'good';
    } else if (validCount === 5) {
        bar.style.width = '100%';
        bar.classList.add('strength-strong');
        text.innerText = "Strong";
        text.style.color = "#5cb85c";
        passwordStrength = 'strong';
    }

    // Enable/disable Next Step button based on password strength
    if (nextStepBtn) {
        if (isPasswordStrong) {
            nextStepBtn.disabled = false;
            nextStepBtn.style.opacity = '1';
            nextStepBtn.style.cursor = 'pointer';
        } else {
            nextStepBtn.disabled = true;
            nextStepBtn.style.opacity = '0.5';
            nextStepBtn.style.cursor = 'not-allowed';
        }
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
    // 1. Remove all non-numbers and remove leading '0'
    let value = input.value.replace(/\D/g, '').replace(/^0+/, '');

    // 2. Limit to 11 digits
    if (value.length > 11) {
        value = value.substring(0, 11);
    }

    // 3. Apply Formatting (XX XXX XXXX)
    let formattedValue = value;
    if (value.length > 5) {
        formattedValue = value.substring(0, 2) + " " + value.substring(2, 5) + " " + value.substring(5);
    } else if (value.length > 2) {
        formattedValue = value.substring(0, 2) + " " + value.substring(2);
    }

    // 4. Update Input
    input.value = formattedValue;
}

// CUSTOMER LOGIN LOGIC
function validateCustomerLogin() {
    const phone = document.getElementById('loginPhone').value.trim();
    const pass = document.getElementById('loginPassword').value.trim();

    // 1. Basic validation
    if (!phone || !pass) {
        alert("Please enter your phone and password.");
        return;
    }

    // 2. Get redirect target from hidden input
    const redirectInput = document.getElementById('redirectUrl');
    let targetUrl = "../user/home.php"; // fallback

    if (redirectInput && redirectInput.value) {
        targetUrl = redirectInput.value;
    }

    // 3. A successful login
    fetch("login-handler.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `phone=${phone}&password=${pass}`
})
.then(res => res.json())
.then(data => {
    if (data.success) {
        window.location.href = targetUrl;
    } else {
        alert(data.message);
    }
});
}

// CAPTCHA Refresh
function refreshRegisterCaptcha(event) {
    if (event) {
        event.preventDefault();     // Stop form submit
        event.stopPropagation();    
    }
    // Reload page to generate new CAPTCHA via PHP
    window.location.reload();
}