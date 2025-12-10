<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal - Lumière</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="staff-page">

<div class="auth-container">
        
    <div class="auth-sidebar" style="background: linear-gradient(135deg, #aa9385 0%, #968073 50%, #6b5b52 100%);">
        <div>
            <h2>Staff Portal</h2>
            <div class="logo-container">
                <img src="../images/16.png" class="sidebar-logo" alt="Lumière Logo">
            </div>
            <p>Access your schedule and client list.</p>
        </div>
        <div>
            <p>Lumière Beauty Salon</p>
        </div>
    </div>

    <div class="auth-main">
        <form class="auth-form staff-theme" id="staffForm" onsubmit="event.preventDefault();">
            
            <div class="form-header">
                <h1 style="color: #968073;">Staff Login</h1>
                <p>Authorized Personnel Only</p>
            </div>

            <div class="form-group">
                <div class="phone-wrapper">
                    <span class="input-prefix">+60</span>
                    <input type="tel"
                           class="form-control phone-input"
                           id="staffId"
                           placeholder="12 345 6789"
                           maxlength="13"
                           oninput="formatPhoneNumber(this)"
                           required>
                </div>
            </div>

            <div class="form-group">
                <div class="input-wrapper">
                    <img src="../images/75.png" class="input-icon" alt="Lock">
                    <input type="password" class="form-control indent-icon" id="staffPass" placeholder="Password" required>
                    <img src="../images/74.png" class="password-toggle" onclick="togglePass('staffPass')" alt="Show">
                </div>
            </div>

            <button type="button" class="submit-btn" style="background: #968073;" onclick="validateStaffLogin()">STAFF LOGIN</button>
            
            <div class="switch-form">
                <a href="../user/login.php" style="color: #968073;">Back to Customer Site</a>
            </div>

        </form>
    </div>
</div>

<script src="../js/script.js"></script>
</body>
</html>
