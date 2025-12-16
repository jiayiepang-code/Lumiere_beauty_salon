// 1. Phone Input Validation - Only numbers, first digit cannot be 0
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('staffId');
    
    // Prevent non-numeric input
    phoneInput.addEventListener('input', function(e) {
        // Remove any non-numeric characters
        let value = e.target.value.replace(/\D/g, '');
        
        // Prevent first digit from being 0
        if (value.length > 0 && value[0] === '0') {
            value = value.substring(1); // Remove leading 0
        }
        
        e.target.value = value;
    });
    
    // Prevent paste of non-numeric content
    phoneInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text');
        const numbersOnly = paste.replace(/\D/g, '');
        
        // Remove leading 0 if present
        let cleanedValue = numbersOnly.replace(/^0+/, '');
        
        // Get current value and combine
        let currentValue = e.target.value.replace(/\D/g, '');
        let newValue = currentValue + cleanedValue;
        
        // Remove leading 0 from final value
        if (newValue.length > 0 && newValue[0] === '0') {
            newValue = newValue.substring(1);
        }
        
        e.target.value = newValue;
    });
    
    // Prevent typing non-numeric keys
    phoneInput.addEventListener('keypress', function(e) {
        // Allow: backspace, delete, tab, escape, enter
        if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
            // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            (e.keyCode === 65 && e.ctrlKey === true) ||
            (e.keyCode === 67 && e.ctrlKey === true) ||
            (e.keyCode === 86 && e.ctrlKey === true) ||
            (e.keyCode === 88 && e.ctrlKey === true)) {
            return;
        }
        
        // Ensure that it is a number and stop the keypress
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
            e.preventDefault();
        }
        
        // Prevent 0 as first digit
        if (e.target.value.length === 0 && e.keyCode === 48) {
            e.preventDefault();
        }
    });
});

// 2. Listen for the Submit Event
document.getElementById('staffForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Stop the page from reloading
    validateStaffLogin();
});

// 3. Main Login Function
function validateStaffLogin() {
    let id = document.getElementById("staffId").value.trim();
    const pass = document.getElementById("staffPass").value.trim();
    const submitBtn = document.querySelector('.submit-btn');
    const errorBox = document.getElementById('error-alert');

    // Clear previous errors
    errorBox.style.display = 'none';
    errorBox.textContent = '';

    if(!id || !pass) { 
        showError("Please enter both phone number and password."); 
        return; 
    }

    // Normalize phone number: Remove spaces and ensure +60 prefix
    // Remove all spaces
    id = id.replace(/\s+/g, '');
    
    // If it doesn't start with +60, add it
    if (!id.startsWith('+60')) {
        // Remove leading 0 if present
        id = id.replace(/^0+/, '');
        // Add +60 prefix
        id = '+60' + id;
    }

    // Disable button to prevent double-click
    submitBtn.disabled = true;
    submitBtn.innerText = "Verifying...";

    // Prepare data
    const formData = new FormData();
    formData.append('phone', id);
    formData.append('password', pass);

    // Send to PHP
    fetch('login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check if response is actually JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error('Server returned non-JSON response: ' + text.substring(0, 100));
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Login Successful -> Redirect to Dashboard
            window.location.href = "dashboard.html"; // Renamed index.html
        } else {
            // Login Failed -> Show Error
            showError(data.error || "Login failed.");
            resetButton(submitBtn);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError("System error. Check connection.");
        resetButton(submitBtn);
    });
}

// Helper: Show Error Message
function showError(msg) {
    const errorBox = document.getElementById('error-alert');
    errorBox.textContent = msg;
    errorBox.style.display = 'block';
}

// Helper: Reset Button
function resetButton(btn) {
    btn.disabled = false;
    btn.innerText = "STAFF LOGIN";
}

// 4. Password Toggle Logic
function togglePass(id) {
    const input = document.getElementById(id);
    const icon = input.nextElementSibling; // The image next to input

    if (input.type === "password") {
        input.type = "text";
        icon.src = "../images/73.png"; 
    } else {
        input.type = "password";
        icon.src = "../images/74.png"; 
    }
}