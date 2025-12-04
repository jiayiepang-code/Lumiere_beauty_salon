/* ========================
   1. FORM SWITCHING & NAVIGATION
   ======================== */
let currentStep = 1;

// Switch to Customer Login
function showLogin() {
    // Hide register form
    document.getElementById('authForm').style.display = 'none';

    // Show user login form
    document.getElementById('loginForm').style.display = 'block';

    // Show the floating buttons (optional)
    document.querySelector('.floating-btn-group').style.display = 'flex';
}

// Switch to Customer Register
function showRegister() {
    hideAllForms();
    document.getElementById('authForm').style.display = 'block';
    document.querySelector('.floating-btn-group').style.display = 'flex';
    goToStep(1);
}

// Switch to Staff Portal
function showStaffLogin() {
    hideAllForms();
    document.getElementById('staffForm').style.display = 'block';
    document.querySelector('.floating-btn-group').style.display = 'none';
}

// Switch to Admin Portal
function showAdminLogin() {
    hideAllForms();
    document.getElementById('adminForm').style.display = 'block';
    document.querySelector('.floating-btn-group').style.display = 'none';
}

// Return Button Logic
function returnToCustomer() { showRegister(); }

// Hide everything
function hideAllForms() {
    document.getElementById('authForm').style.display = 'none';
    document.getElementById('loginForm').style.display = 'none';
}
// Step navigation
function goToStep(step) {
    document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active-step'));
    document.getElementById(`step-group-${step}`).classList.add('active-step');
    document.getElementById('stepCount').innerText = step;
    updateStepperVisuals(step);
    currentStep = step;
}

function updateStepperVisuals(step) {
    for (let i = 1; i <= 4; i++) {
        const circle = document.getElementById(`circle-${i}`);
        circle.classList.remove('step-active', 'step-done');
        circle.innerHTML = `<span class="step-number">${i}</span>`;

        if (i < step) {
            circle.classList.add('step-done');
            circle.innerHTML = `<i class="fas fa-check"></i>`;
        } else if (i === step) {
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
        errorBox.innerText = "Invalid phone number length. Please enter valid digits.";
        return;
    }

    // Success -> Go to Step 2
    errorBox.style.display = 'none';
    goToStep(2);
}

// STEP 2
function validateStep2() {
    const email = document.getElementById('email');
    const pass = document.getElementById('password');
    const errorBox = document.getElementById('step2Error');
    const strengthText = document.getElementById('strengthText').innerText;

    if (!email.checkValidity()) return email.reportValidity();
    if (pass.value === "") return pass.reportValidity();

    if (strengthText !== "Strong") {
        errorBox.style.display = 'block';
        errorBox.innerText = "Password requirements not met.";
        return;
    }

    errorBox.style.display = 'none';

    const btn = document.querySelector('#step-group-2 .submit-btn');
    const oldText = btn.innerText;
    btn.innerText = "Sending OTP...";

    setTimeout(() => {
        btn.innerText = oldText;
        goToStep(3);
        setTimeout(() => document.getElementById('otp1').focus(), 400);
    }, 1000);
}

// STEP 3: OTP Verification & Finalize Register
function validateStep3() {
    const loader = document.getElementById('loader');
    loader.style.display = 'block';

    // Simulate OTP Network Request
    setTimeout(() => {
        loader.style.display = 'none';
        goToStep(4); // Show "Registration Successful" screen

        // AFTER 2 SECONDS, SWITCH TO LOGIN FORM
        setTimeout(() => {
            // 1. Get the phone number they just registered with
            const registeredPhone = document.getElementById('phone').value;
            
            // 2. Pre-fill the Login Phone field for them (Better UX)
            document.getElementById('loginPhone').value = registeredPhone;

            // 3. Switch to the Login View
            showLogin(); 
            
            // Optional: Alert them
            // alert("Registration complete! Please log in.");
        }, 2000);
    }, 1500);
}

/* ========================
   3. PASSWORD SYSTEM
   ======================== */

// eye toggle for user
function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('passwordToggle');

    if (input.type === "password") {
        input.type = "text";
        icon.src = "../images/73.png";
    } else {
        input.type = "password";
        icon.src = "../images/74.png";
    }
}

// Admin & staff login password eye icon
function togglePass(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.parentElement.querySelector('.password-toggle');

    if (input.type === "password") {
        // 1. Show Password
        input.type = "text";
        
        // 2. Change Icon to Eye Slash (73.png)
        // This clever trick replaces only the filename, keeping the path perfect
        icon.src = icon.src.replace('74.png', '73.png'); 
        
    } else {
        // 1. Hide Password
        input.type = "password";
        
        // 2. Change Icon back to Eye (74.png)
        icon.src = icon.src.replace('73.png', '74.png'); 
    }
}

// Popup Logic
const passwordInput = document.getElementById("password");
const passwordHints = document.getElementById("passwordHints");

passwordInput.addEventListener("focus", () => {
    passwordHints.style.display = "block";
});

document.addEventListener("click", (e) => {
    if (!passwordInput.contains(e.target) && !passwordHints.contains(e.target)) {
        passwordHints.style.display = "none";
    }
});

// Strength Updates
function checkPasswordRules() {
    const password = passwordInput.value;
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');

    // 🔥 Always show the bar & strength label when typing
    document.querySelector('.password-strength').style.display = "block";
    text.style.display = "block";

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

    ruleLength.classList.toggle("valid", hasLength);
    ruleUpper.classList.toggle("valid", hasUpper);
    ruleLower.classList.toggle("valid", hasLower);
    ruleNumber.classList.toggle("valid", hasNumber);
    ruleSpecial.classList.toggle("valid", hasSpecial);

    let validCount = hasLength + hasUpper + hasLower + hasNumber + hasSpecial;

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

/* ========================
   4. UTILITIES
   ======================== */

// --- PHONE NUMBER FORMATTER ---
function formatPhoneNumber(input) {
    // 1. Remove all non-numbers and remove leading '0'
    let value = input.value.replace(/\D/g, '').replace(/^0+/, '');

    // 2. Limit to 11 digits (max Malaysian mobile length without 0)
    if (value.length > 11) {
        value = value.substring(0, 11);
    }

    // 3. Apply Formatting (XX XXX XXXX)
    let formattedValue = value;
    
    if (value.length > 5) {
        // If long enough, format as: 12 345 6789
        formattedValue = value.substring(0, 2) + " " + value.substring(2, 5) + " " + value.substring(5);
    } else if (value.length > 2) {
        // If medium length, format as: 12 345
        formattedValue = value.substring(0, 2) + " " + value.substring(2);
    }

    // 4. Update Input
    input.value = formattedValue;
}

function toggleLoginPassword() {
    const input = document.getElementById("loginPassword");
    input.type = input.type === "password" ? "text" : "password";
}

function toggleStaffPassword() {
    const input = document.getElementById("staffPass");
    input.type = input.type === "password" ? "text" : "password";
}

function toggleAdminPassword() {
    const input = document.getElementById("adminPass");
    input.type = input.type === "password" ? "text" : "password";
}

function toggleCheckbox(el) { el.classList.toggle('checked'); }

function handleOtpInput(input, nextId) {
    if (input.value.length === 1 && nextId) {
        document.getElementById(nextId).focus();
    }
}

// CUSTOMER LOGIN LOGIC
function validateCustomerLogin() {
    const phone = document.getElementById('loginPhone').value;
    const pass = document.getElementById('loginPassword').value;

    if (!phone || !pass) {
        alert("Please enter your phone and password.");
        return;
    }

    // 1. Simulate Login Check (You can add real logic later)
    
    // 2. REDIRECT to the Dashboard folder
    // Make sure you have the folder "user" and file "dashboard.html" created!
    window.location.href = "user/dashboard.html"; 
}

function validateStaffLogin() {
    const id = document.getElementById('staffId').value;
    const pass = document.getElementById('staffPass').value;

    if (id === "" || pass === "") {
        alert("Please enter Staff ID and Password.");
        return;
    }

    // Change this to your homepage
    window.location.href = "home.html"; 
}

function validateAdminLogin() {
    const id = document.getElementById("adminId").value;
    const pass = document.getElementById("adminPass").value;

    if (!id || !pass) {
        alert("Please enter Admin ID and Password.");
        return;
    }

    // Change this to your homepage
    window.location.href = "home.html";
}