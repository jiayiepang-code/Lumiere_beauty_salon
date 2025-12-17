<?php
session_start();

// Generate CAPTCHA if not exists
if (!isset($_SESSION['register_captcha'])) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $_SESSION['register_captcha'] = substr(str_shuffle($chars), 0, 5);
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lumière Login</title>
    <link rel="stylesheet" href="../css/login.css" />
  </head>
  <body>
    <!-- FLOATING BUTTONS: STAFF + ADMIN -->
    <div class="floating-btn-group">
      <!-- STAFF -->
      <a
        href="../staff/login.html"
        class="float-wrapper staff-btn-container"
        style="text-decoration: none"
      >
        <div class="icon-circle">
          <img src="../images/79.png" alt="Staff" />
        </div>
        <span class="float-text">Staff Login</span>
      </a>

      <!-- ADMIN -->
      <a
        href="../admin/login.html"
        class="float-wrapper admin-btn-container"
        style="text-decoration: none"
      >
        <div class="icon-circle">
          <img src="../images/80.png" alt="Admin" />
        </div>
        <span class="float-text">Admin Login</span>
      </a>
    </div>
    <!-- ================= LEFT SIDEBAR ================= -->
    <div class="auth-container">
      <div class="auth-sidebar">
        <div>
          <h2>Welcome to</h2>
          <div class="logo-container">
            <img
              src="../images/16.png"
              class="sidebar-logo"
              alt="Lumière Logo"
            />
          </div>
          <p>Access your account and manage your bookings.</p>
        </div>
        <p>Lumière Beauty Salon</p>
      </div>

      <!-- ================= RIGHT SIDE (REGISTER PAGE) ================= -->
      <div class="auth-main">
        <!-- REGISTER FORM (4-step) -->
        <form
          class="auth-form"
          id="authForm"
          onsubmit="event.preventDefault();"
        >
          <div class="form-header">
            <h1 id="formTitle">Register</h1>
            <p>Step <span id="stepCount">1</span> of 4</p>
          </div>

          <!-- STEPPER -->
          <div class="register-stepper">
            <div class="step step-active" id="circle-1">
              <span class="step-number">1</span>
            </div>
            <div class="step" id="circle-2">
              <span class="step-number">2</span>
            </div>
            <div class="step" id="circle-3">
              <span class="step-number">3</span>
            </div>
            <div class="step" id="circle-4">
              <span class="step-number">4</span>
            </div>
          </div>

          <!-- STEP 1 -->
          <div id="step-group-1" class="form-step active-step">
            <div class="name-row">
              <div class="form-group half-width">
                <input
                  type="text"
                  class="form-control"
                  id="firstName"
                  placeholder="First name"
                  required
                />
              </div>
              <div class="form-group half-width">
                <input
                  type="text"
                  class="form-control"
                  id="lastName"
                  placeholder="Last name"
                  required
                />
              </div>
            </div>

            <div class="form-group">
              <div class="phone-wrapper">
                <span class="input-prefix">+60</span>
                <input
                  type="tel"
                  class="form-control phone-input"
                  id="phone"
                  placeholder="12 345 6789"
                  maxlength="13"
                  oninput="formatPhoneNumber(this)"
                  required
                />
              </div>
            </div>

            <div class="error-message" id="step1Error"></div>

            <button type="button" class="submit-btn" onclick="validateStep1()">
              Next Step
            </button>

            <div class="switch-form">
              Already have an account?
              <a href="#" onclick="showLogin()">Login</a>
            </div>
          </div>

          <!-- STEP 2 -->
          <div id="step-group-2" class="form-step">
            <div class="form-group">
              <div class="input-wrapper">
                <img src="../images/72.png" class="input-icon" />
                <input
                  type="email"
                  class="form-control indent-icon"
                  id="email"
                  placeholder="Email address"
                  required
                />
              </div>
            </div>

            <div class="form-group">
              <div class="input-wrapper">
                <img src="../images/75.png" class="input-icon" />
                <input
                  type="password"
                  class="form-control indent-icon"
                  id="password"
                  placeholder="Password"
                  required
                  oninput="checkPasswordRules()"
                />
                <img
                  src="../images/74.png"
                  class="password-toggle"
                  id="passwordToggle"
                  onclick="togglePassword()"
                />
              </div>

              <div class="password-strength">
                <div class="password-strength-bar" id="strengthBar"></div>
              </div>
              <!-- Password Hint Rules -->
              <div class="hints-popup" id="passwordHints">
                <p>Password must contain:</p>
                <ul>
                  <li class="rule-item" id="ruleLength">
                    ✔ At least 8 characters
                  </li>
                  <li class="rule-item" id="ruleUpper">
                    ✔ One uppercase letter (A-Z)
                  </li>
                  <li class="rule-item" id="ruleLower">
                    ✔ One lowercase letter (a-z)
                  </li>
                  <li class="rule-item" id="ruleNumber">✔ One number (0-9)</li>
                  <li class="rule-item" id="ruleSpecial">
                    ✔ One symbol (!@#$%…)
                  </li>
                </ul>
              </div>
              <div class="strength-text" id="strengthText"></div>
            </div>

            <div class="form-group">
              <div class="input-wrapper">
                <img src="../images/75.png" class="input-icon" />
                <input
                  type="password"
                  class="form-control indent-icon"
                  id="confirmPassword"
                  placeholder="Confirm Password"
                  required
                />
                <img
                  src="../images/74.png"
                  class="password-toggle"
                  id="confirmPasswordToggle"
                  onclick="toggleConfirmPassword()"
                />
              </div>
            </div>

            <div class="checkbox-container">
              <div
                class="custom-checkbox"
                id="rememberMe"
                onclick="toggleCheckbox(this)"
              ></div>
              <label>Remember me</label>
            </div>

            <div class="error-message" id="step2Error"></div>

            <button type="button" class="submit-btn" onclick="validateStep2()">
              Next Step
            </button>

            <div class="switch-form">
              <a href="#" onclick="goToStep(1)">Back</a>
            </div>
          </div>

          <!-- STEP 3 -->
          <div id="step-group-3" class="form-step">
            <div class="otp-container">
              <p>Please enter the characters shown below to verify you're not a robot.</p>
              <div class="otp-inputs captcha-wrapper">
                <div id="registerCaptchaCode" class="captcha-box"
                     data-code="<?= htmlspecialchars($_SESSION['register_captcha']); ?>">
                    <?= htmlspecialchars($_SESSION['register_captcha']); ?>
                </div>
                <input type="text" maxlength="10" class="form-control"
                       id="registerCaptchaInput" placeholder="Enter CAPTCHA here">
                <button type="button" class="captcha-refresh" onclick="refreshRegisterCaptcha(event)">
                    Refresh CAPTCHA
                </button>
              </div>
            </div>

            <div class="error-message" id="step3Error"></div>
            <button type="button" class="submit-btn" onclick="validateStep3()">
              Verify & Register
            </button>

            <div class="switch-form">
              <a href="#" onclick="goToStep(2)">Back</a>
            </div>
          </div>

          <!-- STEP 4 SUCCESS -->
          <div id="step-group-4" class="form-step success-view">
            <i class="fas fa-check-circle success-icon"></i>
            <h2>Registration Successful!</h2>
            <p>Your account has been verified.</p>
          </div>
        </form>

        <!-- ========== LOGIN PAGE ========== -->
        <form class="auth-form login-form" id="loginForm" style="display: none">
          <div class="form-header"><h1>Login</h1></div>

          <div class="form-group">
            <div class="phone-wrapper">
              <span class="input-prefix">+60</span>
              <input
                type="tel"
                class="form-control phone-input"
                id="loginPhone"
                placeholder="12 345 6789"
                maxlength="13"
                oninput="formatPhoneNumber(this)"
              />
            </div>
          </div>

          <div class="form-group">
            <div class="input-wrapper">
              <img src="../images/75.png" class="input-icon" />
              <input
                type="password"
                class="form-control indent-icon"
                id="loginPassword"
                placeholder="Password"
              />
              <img
                src="../images/74.png"
                class="password-toggle"
                id="loginPasswordToggle"
                onclick="toggleLoginPassword()"
              />
            </div>
          </div>

          <div class="error-message" id="loginError" style="display: none;"></div>

          <button
            type="button"
            class="submit-btn"
            onclick="validateCustomerLogin()"
          >
            LOGIN
          </button>

          <div class="switch-form">
            Don’t have an account?
            <a href="#" onclick="showRegister()">Register</a>
          </div>
        </form>
      </div>
    </div>

    <script src="../js/login.js"></script>
  </body>
</html>
