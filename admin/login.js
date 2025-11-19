/* SLIDE TOGGLE */
let goLogin = document.getElementById('goLogin');
let goRegister = document.getElementById('goRegister');
let container = document.querySelector('.container');
let loginBox = document.querySelector('.login-box');
let registerBox = document.querySelector('.register-box');
let emailBubble = document.getElementById("emailBubble");
let phoneBubble = document.getElementById("phoneBubble");
let passwordBubble = document.getElementById("regPasswordBubble");
let confirmBubble = document.getElementById("confirmBubble");
let emailOK = false;
let phoneOK = false;
let passwordOK = false;
let confirmOK = false;
passwordOK = true;
confirmOK = true;

goRegister.addEventListener('click', () => {
    container.classList.add('show-register');
    loginBox.classList.add('hidden');
    registerBox.classList.remove('hidden');

    hideRegisterBubbles();   // ← Add this
});

goLogin.addEventListener('click', () => {
    container.classList.remove('show-register');
    registerBox.classList.add('hidden');
    loginBox.classList.remove('hidden');

    hideRegisterBubbles();   // ← Add this
});

// LOGIN PHONE VALIDATION
document.getElementById("loginPhone").addEventListener("input", function () {
    this.value = this.value.replace(/[^0-9]/g, "");

    const msg = document.getElementById("loginPhoneMsg");
    const pattern = /^(01\d{7,8}|011\d{8}|60\d{8,9})$/;

    if (!pattern.test(this.value)) {
        msg.textContent = "❌ Invalid Malaysia phone number";
        msg.classList.remove("success");
    } else {
        msg.textContent = "✔ Valid phone number";
        msg.classList.add("success");
    }
});

/* PASSWORD EYE */
function togglePassword(id, eye) {
    let input = document.getElementById(id);

    let openIcon = eye.querySelector(".eye-open");
    let closedIcon = eye.querySelector(".eye-closed");

    if (input.type === "password") {
        input.type = "text";
        openIcon.classList.add("hidden");
        closedIcon.classList.remove("hidden");
    } else {
        input.type = "password";
        closedIcon.classList.add("hidden");
        openIcon.classList.remove("hidden");
    }
}


/* EMAIL VALIDATION */
document.getElementById("regEmail").addEventListener("input", function () {
    const email = this.value;
    const bubble = document.getElementById("emailBubble");

    const valid = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email);

    if (!valid) {
        bubble.className = "bubble";
        bubble.innerHTML = "❌ Invalid email format";
        bubble.classList.remove("hidden");
        emailOK = false;
    } else {
        bubble.className = "bubble success";
        bubble.innerHTML = "✔ Valid email";
        bubble.classList.remove("hidden");
        emailOK = true;
    }
});

/* MALAYSIA PHONE VALIDATION */
document.getElementById("regPhone").addEventListener("input", function () {
    const phone = this.value.replace(/[^0-9]/g, "");
    this.value = phone;
    const bubble = document.getElementById("phoneBubble");

    const pattern = /^(01\d{7,8}|011\d{8}|60\d{8,9})$/;

    if (phone === "") {
        bubble.classList.add("hidden");
        return;
    }

    if (!pattern.test(phone)) {
        bubble.className = "bubble";
        bubble.innerHTML = "❌ Invalid Malaysia phone number";
        bubble.classList.remove("hidden");
        phoneOK = false;
    } else {
        bubble.className = "bubble success";
        bubble.innerHTML = "✔ Valid phone number";
        bubble.classList.remove("hidden");
        phoneOK = true;
    }
});

/* PASSWORD VALIDATION BUBBLE */
document.getElementById("regPassword").addEventListener("input", function () {
    const pass = this.value;
    const bubble = document.getElementById("regPasswordBubble");;

    // Hide bubble when empty
    if (pass.trim() === "") {
        bubble.classList.add("hidden");
        return;
    }

    // Password strength rules
    const hasUpper = /[A-Z]/.test(pass);
    const hasNumber = /[0-9]/.test(pass);
    const hasSymbol = /[._\-?@#$%^]/.test(pass);

    let strength = "";

    if (pass.length < 8) {
        strength = "❌ Weak password";
        bubble.className = "bubble";
    } 
    else if (!hasUpper || !hasNumber || !hasSymbol) {
        strength = "⚠ Medium password";
        bubble.className = "bubble medium";
    } 
    else {
        strength = "✔ Strong password";
        bubble.className = "bubble success";
    }

    bubble.innerHTML = strength;
    bubble.classList.remove("hidden");
});

/* CONFIRM PASSWORD */
document.getElementById("regConfirm").addEventListener("input", function () {
    const pass = document.getElementById("regPassword").value;
    const confirm = this.value;
    const bubble = document.getElementById("confirmBubble");

    if (confirm !== pass) {
        bubble.classList.remove("success", "hidden");
        bubble.textContent = "❌ Passwords do not match";
    } else {
        bubble.classList.add("success");
        bubble.classList.remove("hidden");
        bubble.textContent = "✔ Passwords match";
    }
});

/* CAPTCHA */
function generateCaptcha() {
    let text = "";
    const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";

    for (let i = 0; i < 5; i++)
        text += chars[Math.floor(Math.random() * chars.length)];

    document.getElementById("captchaText").textContent = text;
}
generateCaptcha();

function hideRegisterBubbles() {
    document.getElementById("emailBubble").classList.add("hidden");
    document.getElementById("phoneBubble").classList.add("hidden");
    document.getElementById("regPasswordBubble").classList.add("hidden");
    document.getElementById("confirmBubble").classList.add("hidden");
}

/* FINAL LOGIN SUBMIT CHECK */
document.getElementById("loginSubmit").addEventListener("click", function(e) {
    e.preventDefault();

    let phoneMsg = document.getElementById("loginPhoneMsg");

    if (!phoneMsg.classList.contains("success")) {
        alert("❌ Invalid login credentials.");
        return;
    }

    alert("✔ Login successful!");
});

/* FINAL REGISTER SUBMIT CHECK */
document.getElementById("regSubmit").addEventListener("click", function (e) {
    e.preventDefault(); // STOP form from submitting first

    let emailValid = !emailBubble.classList.contains("hidden") && emailBubble.classList.contains("success");
    let phoneValid = !phoneBubble.classList.contains("hidden") && phoneBubble.classList.contains("success");
    let passValid = !passwordBubble.classList.contains("hidden") && passwordBubble.classList.contains("success");
    let confirmValid = !confirmBubble.classList.contains("hidden") && confirmBubble.classList.contains("success");

    let captchaInput = document.getElementById("captchaInput").value.trim();
    let captchaReal = document.getElementById("captchaText").textContent.trim();
    let captchaMsg = document.getElementById("captchaMsg");

    let allValid = emailValid && phoneValid && passValid && confirmValid;

    // ❌ If any field invalid → stop
    if (!allValid) {
        alert("❌ Please fix the errors before registering.");
        return;
    }

    // ❌ CAPTCHA WRONG
    if (captchaInput !== captchaReal) {
        captchaMsg.textContent = "❌ Captcha incorrect";
        captchaMsg.classList.remove("success");

        generateCaptcha(); // AUTO refresh captcha
        document.getElementById("captchaInput").value = ""; // clear input
        return;
    }

    // ✔ CAPTCHA OK
    captchaMsg.textContent = "✔ Captcha correct";
    captchaMsg.classList.add("success");

    alert("🎉 Registration Successful!");
});