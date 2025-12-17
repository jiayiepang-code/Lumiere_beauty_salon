// Admin Login JavaScript - Handles authentication with PHP backend

// Format phone number input
function formatPhoneNumber(input) {
  let value = input.value.replace(/[^0-9]/g, "");
  if (value.length > 10) {
    value = value.substring(0, 10);
  }
  if (value.length > 6) {
    value =
      value.substring(0, 2) +
      " " +
      value.substring(2, 5) +
      " " +
      value.substring(5);
  } else if (value.length > 2) {
    value = value.substring(0, 2) + " " + value.substring(2);
  }
  input.value = value;
}

// Toggle password visibility
function togglePass(inputId) {
  const input = document.getElementById(inputId);
  if (input.type === "password") {
    input.type = "text";
  } else {
    input.type = "password";
  }
}

// Validate Malaysia phone number
function validatePhoneNumber(phone) {
  const cleaned = phone.replace(/[^0-9]/g, "");
  // Accept formats: 01X-XXXXXXX (9-11 digits) or 60X-XXXXXXXX (10-12 digits)
  // More flexible to handle various Malaysian phone formats
  if (cleaned.length < 9 || cleaned.length > 12) {
    return false;
  }
  // Must start with 01 or 60, or just digits (will be normalized)
  return (
    cleaned.startsWith("01") ||
    cleaned.startsWith("60") ||
    cleaned.startsWith("1")
  );
}

// Show error message
function showError(message) {
  let errorDiv = document.getElementById("loginError");
  if (!errorDiv) {
    errorDiv = document.createElement("div");
    errorDiv.id = "loginError";
    errorDiv.style.cssText =
      "background:#fee;color:#c33;padding:12px;border-radius:8px;margin-bottom:15px;border:1px solid #fcc;font-size:14px;";
    const form = document.getElementById("adminForm");
    form.insertBefore(errorDiv, form.firstChild);
  }
  errorDiv.textContent = message;
  errorDiv.style.display = "block";
  setTimeout(() => {
    errorDiv.style.display = "none";
  }, 5000);
}

// Show success message
function showSuccess(message) {
  let successDiv = document.getElementById("loginSuccess");
  if (!successDiv) {
    successDiv = document.createElement("div");
    successDiv.id = "loginSuccess";
    successDiv.style.cssText =
      "background:#efe;color:#3c3;padding:12px;border-radius:8px;margin-bottom:15px;border:1px solid #cfc;font-size:14px;";
    const form = document.getElementById("adminForm");
    form.insertBefore(successDiv, form.firstChild);
  }
  successDiv.textContent = message;
  successDiv.style.display = "block";
}

// Handle admin login
async function validateAdminLogin() {
  const phoneInput = document.getElementById("loginPhone");
  const passwordInput = document.getElementById("adminPass");
  const submitButton = event.target;

  const phoneDisplay = phoneInput.value.trim();
  const password = passwordInput.value.trim();

  if (!phoneDisplay || !password) {
    showError("Please enter both phone number and password");
    return;
  }

  const phone = phoneDisplay.replace(/[^0-9]/g, "");

  if (!validatePhoneNumber(phone)) {
    showError("Please enter a valid Malaysia phone number");
    return;
  }

  let normalizedPhone = phone;
  if (phone.startsWith("0")) {
    normalizedPhone = "60" + phone.substring(1);
  } else if (!phone.startsWith("60") && phone.length >= 9) {
    // Handle numbers entered without country code or leading 0
    normalizedPhone = "60" + phone;
  }

  submitButton.disabled = true;
  submitButton.textContent = "LOGGING IN...";

  try {
    const response = await fetch("../api/admin/auth/login.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ phone: normalizedPhone, password: password }),
    });

    const data = await response.json();

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'admin/login.js:120',message:'API response received',data:{success:data.success,redirect:data.redirect,dataRedirect:data.data?.redirect,currentUrl:window.location.href,pathname:window.location.pathname},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
    // #endregion

    if (data.success) {
      if (data.csrf_token) {
        sessionStorage.setItem("csrf_token", data.csrf_token);
      }
      showSuccess("Login successful! Redirecting...");
      setTimeout(() => {
        // Try multiple possible redirect locations
        let redirectUrl = data.redirect || data.data?.redirect;
        
        // If no redirect URL provided, use relative path
        if (!redirectUrl) {
          redirectUrl = "index.php";
        }
        
        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'admin/login.js:133',message:'Before redirect',data:{redirectUrl:redirectUrl,currentUrl:window.location.href,resolvedUrl:new URL(redirectUrl,window.location.href).href},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'B'})}).catch(()=>{});
        // #endregion
        
        console.log("Redirecting to:", redirectUrl);
        console.log("Full response data:", data);
        
        // Use window.location.replace to prevent back button issues
        window.location.replace(redirectUrl);
      }, 500); // Reduced timeout for faster redirect
    } else {
      const errorMessage =
        data.error?.message || "Login failed. Please try again.";
      showError(errorMessage);
      submitButton.disabled = false;
      submitButton.textContent = "ADMIN LOGIN";
    }
  } catch (error) {
    console.error("Login error:", error);
    showError("An error occurred. Please try again.");
    submitButton.disabled = false;
    submitButton.textContent = "ADMIN LOGIN";
  }
}

// Check for session timeout message
window.addEventListener("DOMContentLoaded", () => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("timeout") === "1") {
    showError("Your session has expired. Please login again.");
  }
});
