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

// Step navigation
function goToStep(step) {
    document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active-step'));
    document.getElementById(`step-group-${step}`).classList.add('active-step');
    document.getElementById('stepCount').innerText = step;
    updateStepperVisuals(step);
    currentStep = step;
    
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
}

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
            circle.classList.add('step-done');
            stepNumber.textContent = '✓';
        } else if (i === step) {
            circle.classList.add('step-active');
            stepNumber.textContent = i;
        } else {
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

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'js/script.js:validateStep3',message:'Registration submission started',data:{firstName:firstName,lastName:lastName,phone:phone,email:email,hasPassword:!!password,hasCaptcha:!!captcha},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'E'})}).catch(()=>{});
    // #endregion agent log
    
    // 3. Send to PHP
    fetch('register.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'js/script.js:validateStep3',message:'Registration fetch response received',data:{ok:response.ok,status:response.status,statusText:response.statusText,contentType:response.headers.get('content-type')},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'F'})}).catch(()=>{});
        // #endregion agent log
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText);
        }
        // Try to parse as JSON
        return response.text().then(text => {
            // #region agent log
            fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'js/script.js:validateStep3',message:'Registration response text received',data:{textLength:text.length,textPreview:text.substring(0,200),isJSON:false},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'G'})}).catch(()=>{});
            // #endregion agent log
            
            try {
                const parsed = JSON.parse(text);
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'js/script.js:validateStep3',message:'Registration response parsed as JSON',data:{status:parsed.status,hasMessage:!!parsed.message},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'H'})}).catch(()=>{});
                // #endregion agent log
                return parsed;
            } catch(e) {
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'js/script.js:validateStep3',message:'JSON parse error',data:{errorMessage:e.message,textPreview:text.substring(0,200)},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'I'})}).catch(()=>{});
                // #endregion agent log
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
            if (typeof refreshRegisterCaptcha === 'function') {
                refreshRegisterCaptcha(null);
            }
        }
    })
    .catch(error => {
        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'js/script.js:validateStep3',message:'Registration submission error',data:{errorMessage:error.message,errorStack:error.stack},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'J'})}).catch(()=>{});
        // #endregion agent log
        console.error('Error:', error);
        document.getElementById('step3Error').innerText = 'Error: ' + (error.message || 'Connection error. Please check your internet connection and try again.');
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

// Staff Password Toggle
function togglePass(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const icon = input.parentElement.querySelector('.password-toggle');
    if (!icon) return;
    
    if (input.type === "password") {
        // Show password - change to open eye icon
        input.type = "text";
        icon.src = icon.src.replace('74.png', '73.png');
    } else {
        // Hide password - change to closed eye icon
        input.type = "password";
        icon.src = icon.src.replace('73.png', '74.png');
    }
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

// Show Password Hints Function (called from onfocus attribute)
function showPasswordHints() {
    const hints = document.getElementById("passwordHints");
    if (hints) {
        hints.style.display = "block";
    }
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

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            sessionId: 'debug-session',
            runId: 'pre-fix',
            hypothesisId: 'A',
            location: 'js/script.js:checkPasswordRules:before-display',
            message: 'Password strength DOM before layout changes',
            data: {
                hasStrengthContainer: !!strengthContainer,
                hasTextEl: !!text
            },
            timestamp: Date.now()
        })
    }).catch(()=>{});
    // #endregion

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

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            sessionId: 'debug-session',
            runId: 'pre-fix',
            hypothesisId: 'B',
            location: 'js/script.js:checkPasswordRules:after-calc',
            message: 'Password rule counts and strength classification',
            data: {
                validCount,
                isPasswordStrong,
                passwordStrengthBefore: passwordStrength
            },
            timestamp: Date.now()
        })
    }).catch(()=>{});
    // #endregion

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

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            sessionId: 'debug-session',
            runId: 'pre-fix',
            hypothesisId: 'C',
            location: 'js/script.js:checkPasswordRules:end',
            message: 'Password strength UI final state',
            data: {
                passwordStrength,
                nextStepDisabled: nextStepBtn ? nextStepBtn.disabled : null
            },
            timestamp: Date.now()
        })
    }).catch(()=>{});
    // #endregion
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

// STAFF LOGIN LOGIC
function validateStaffLogin() {
    var phone = document.getElementById('staffId').value;
    var password = document.getElementById('staffPass').value;

    // Basic validation
    if (!phone || !password) {
        alert("Please enter phone and password.");
        return;
    }

    // Create standard form data
    var formData = new FormData();
    formData.append('phone', phone);
    formData.append('password', password);

    // Post to the current page (staff/login.php handles both GET and POST)
    fetch(window.location.pathname, {
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
            // Redirect to staff dashboard
            window.location.href = 'dashboard.html';
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
    if (event) event.preventDefault(); // ⛔ stop page reload

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'js/script.js:refreshRegisterCaptcha',message:'CAPTCHA refresh initiated',data:{currentUrl:window.location.href},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'K'})}).catch(()=>{});
    // #endregion agent log

    // Refresh CAPTCHA from root-level script
    fetch("refresh_captcha.php")
        .then(response => {
            // #region agent log
            fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'js/script.js:refreshRegisterCaptcha',message:'CAPTCHA fetch response',data:{ok:response.ok,status:response.status,statusText:response.statusText},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'L'})}).catch(()=>{});
            // #endregion agent log
            if (!response.ok) {
                throw new Error('Failed to refresh CAPTCHA: ' + response.statusText);
            }
            return response.text();
        })
        .then(newCode => {
            // #region agent log
            fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'js/script.js:refreshRegisterCaptcha',message:'CAPTCHA code received',data:{codeLength:newCode.length,codePreview:newCode.substring(0,5)},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'M'})}).catch(()=>{});
            // #endregion agent log
            let captchaBox = document.getElementById("registerCaptchaCode");
            if (captchaBox) {
                captchaBox.textContent = newCode;
                captchaBox.dataset.code = newCode;
            }
        })
        .catch(error => {
            // #region agent log
            fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'js/script.js:refreshRegisterCaptcha',message:'CAPTCHA refresh error',data:{errorMessage:error.message,errorStack:error.stack},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'N'})}).catch(()=>{});
            // #endregion agent log
            console.error('Error refreshing CAPTCHA:', error);
            alert('Failed to refresh CAPTCHA. Please try again.');
        });
}