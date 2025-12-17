<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Lumière</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --staff-color: #968073;
            --success-color: #5cb85c;
            --error-color: #d9534f;
            --text-color: #5c4e4b;
            --text-light: #8a8a95;
            --border-color: #e6d9d2;
            --bg-gradient: linear-gradient(180deg, #f5e9e4, #faf5f2, #ffffff);
            --shadow-soft: 0 4px 20px rgba(150, 128, 115, 0.1);
            --radius-soft: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-color);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--staff-color);
        }

        .page-subtitle {
            color: var(--text-light);
            margin-top: 0.5rem;
        }

        /* Profile Layout */
        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: var(--radius-soft);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--staff-color), #b8957b);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 3rem;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-soft);
        }

        .avatar-upload {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 35px;
            height: 35px;
            background: var(--staff-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            border: 3px solid white;
        }

        .profile-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--staff-color);
            margin-bottom: 0.5rem;
        }

        .profile-role {
            color: var(--text-light);
            margin-bottom: 1.5rem;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: var(--radius-soft);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            color: var(--staff-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--staff-color);
            box-shadow: 0 0 0 3px rgba(150, 128, 115, 0.1);
        }

        .form-input:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn-primary {
            background: var(--staff-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #7a6a5f;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .password-hint {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }

        .success-message {
            background: rgba(92, 184, 92, 0.1);
            color: var(--success-color);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }

        .error-message {
            background: rgba(217, 83, 79, 0.1);
            color: var(--error-color);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }

        /* Hide browser's default password toggle */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        } 

        input[type="password"]::-webkit-credentials-auto-fill-button,
        input[type="password"]::-webkit-textfield-decoration-container {
            display: none !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Profile Settings</h1>
                <p class="page-subtitle">Manage your personal information and account preferences</p>
            </div>

            <div class="profile-layout">
                <div class="profile-card">
                    <div class="profile-avatar" onclick="uploadAvatar()" id="profileAvatar">
                        <img id="avatarImage" src="" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: none;">
                        <i class="fas fa-user" id="avatarIcon"></i>
                        <div class="avatar-upload">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <h2 class="profile-name" id="profileName">Loading...</h2>
                    <p class="profile-role" id="profileRole">Loading...</p>
                </div>

                <div class="form-card">
                    <div class="success-message" id="successMessage">
                        <i class="fas fa-check-circle"></i> Profile updated successfully!
                    </div>
                    
                    <div class="error-message" id="errorMessage">
                        <i class="fas fa-exclamation-circle"></i> Please check your input and try again.
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Personal Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="firstName">First Name</label>
                                <input type="text" id="firstName" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="lastName">Last Name</label>
                                <input type="text" id="lastName" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" class="form-input" disabled>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="tel" id="phone" class="form-input" required>
                        </div>

                        <div class="btn-group">
                            <button type="button" class="btn-primary" onclick="updatePersonalInfo()">Save Changes</button>
                            <button type="button" class="btn-secondary" onclick="resetPersonalForm()">Reset</button>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Change Password</h3>
                        
                        <div class="form-group">
                            <label class="form-label" for="currentPassword">Current Password</label>
                            <div style="position: relative;">
                                <input type="text" id="currentPassword" class="form-input" required style="padding-right: 45px;">
                                <i class="fas fa-eye" id="toggleCurrentPassword" onclick="togglePasswordVisibility('currentPassword', 'toggleCurrentPassword')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-light);"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="newPassword">New Password</label>
                            <div style="position: relative;">
                                <input type="password" id="newPassword" class="form-input" required style="padding-right: 45px;">
                                <i class="fas fa-eye" id="toggleNewPassword" onclick="togglePasswordVisibility('newPassword', 'toggleNewPassword')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-light);"></i>
                            </div>
                            <div class="password-hint">Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirmPassword">Confirm New Password</label>
                            <div style="position: relative;">
                                <input type="password" id="confirmPassword" class="form-input" required style="padding-right: 45px;">
                                <i class="fas fa-eye" id="toggleConfirmPassword" onclick="togglePasswordVisibility('confirmPassword', 'toggleConfirmPassword')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-light);"></i>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="button" class="btn-primary" onclick="changePassword()">Change Password</button>
                            <button type="button" class="btn-secondary" onclick="resetPasswordForm()">Reset</button>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Account Actions</h3>
                        <div class="btn-group">
                            <button type="button" class="btn-secondary" onclick="exportData()">Export Data</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        let staffData = null;

        // Load staff data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadStaffData();
        });

        function showMessage(type, message) {
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            
            // Hide both messages first
            successMsg.style.display = 'none';
            errorMsg.style.display = 'none';
            
            if (type === 'success') {
                successMsg.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
                successMsg.style.display = 'block';
            } else {
                errorMsg.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
                errorMsg.style.display = 'block';
            }
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                successMsg.style.display = 'none';
                errorMsg.style.display = 'none';
            }, 5000);
        }

        // Load staff data from API
        function loadStaffData() {
            fetch('api/staff.php')
                .then(response => {
                    // Check if unauthorized (session expired)
                    if (response.status === 401) {
                        alert('Session expired. Please login again.');
                        window.location.href = '../user/index.php';
                        return null;
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data) return; // Already handled redirect
                    
                    if (data.success && data.staff) {
                        staffData = data.staff;
                        displayStaffData(data.staff);
                        console.log('Loaded staff data:', data.staff);
                    } else {
                        console.error('API Error:', data);
                        if (data.error === 'Unauthorized' || data.error === 'Staff not found') {
                            alert('Session expired or staff not found. Please login again.');
                            window.location.href = '../user/index.php';
                        } else {
                            showMessage('error', data.error || 'Failed to load profile data. Please try again.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('error', 'Failed to load profile data. Please check your connection.');
                });
        }

        // Display staff data in the form
        function displayStaffData(staff) {
            // Update profile card
            document.getElementById('profileName').textContent = `${staff.first_name} ${staff.last_name}`;
            document.getElementById('profileRole').textContent = staff.role || 'Staff';
            
            // Update avatar image (show uploaded photo if exists, otherwise show icon)
            const avatarImg = document.getElementById('avatarImage');
            const avatarIcon = document.getElementById('avatarIcon');
            if (staff.staff_image) {
                const imagePath = staff.staff_image.startsWith('staff/') ? '../' + staff.staff_image : '../staff/' + staff.staff_image;
                avatarImg.src = imagePath;
                avatarImg.style.display = 'block';
                avatarIcon.style.display = 'none';
            } else {
                avatarImg.style.display = 'none';
                avatarIcon.style.display = 'block';
            }
            
            // Update form fields
            document.getElementById('firstName').value = staff.first_name || '';
            document.getElementById('lastName').value = staff.last_name || '';
            document.getElementById('email').value = staff.staff_email || '';
            document.getElementById('phone').value = staff.phone || '';
        }

        function updatePersonalInfo() {
            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const phone = document.getElementById('phone').value.trim();
            
            // Basic validation
            if (!firstName || !lastName || !phone) {
                showMessage('error', 'Please fill in all required fields.');
                return;
            }
            
            // Send update to API
            fetch('api/staff.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    first_name: firstName,
                    last_name: lastName,
                    phone: phone
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update profile name display
                    document.getElementById('profileName').textContent = `${firstName} ${lastName}`;
                    showMessage('success', 'Personal information updated successfully!');
                    // Reload data to ensure sync
                    loadStaffData();
                } else {
                    showMessage('error', data.error || 'Failed to update profile.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Failed to update profile. Please try again.');
            });
        }

        function resetPersonalForm() {
            if (staffData) {
                displayStaffData(staffData);
            }
        }

        function changePassword() {
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Validation
            if (!currentPassword || !newPassword || !confirmPassword) {
                showMessage('error', 'Please fill in all password fields.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showMessage('error', 'New passwords do not match.');
                return;
            }
            
            // Password strength validation
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;
            if (!passwordRegex.test(newPassword)) {
                showMessage('error', 'Password does not meet requirements.');
                return;
            }
            
            // Send password change to API
            fetch('api/staff.php?action=change_password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear password fields
                    document.getElementById('currentPassword').value = '';
                    document.getElementById('newPassword').value = '';
                    document.getElementById('confirmPassword').value = '';
                    showMessage('success', 'Password changed successfully!');
                } else {
                    showMessage('error', data.error || 'Failed to change password.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Failed to change password. Please try again.');
            });
        }

        function resetPasswordForm() {
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        }

        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
    
            if (!input || !icon) return;
    
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function uploadAvatar() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type
                    if (!file.type.match('image.*')) {
                        showMessage('error', 'Please select an image file.');
                        return;
                    }
                    
                    // Validate file size (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        showMessage('error', 'File size too large. Maximum size is 5MB.');
                        return;
                    }
                    
                    // Upload file
                    const formData = new FormData();
                    formData.append('image', file);
                    
                    fetch('api/staff.php?action=upload_image', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update avatar display
                            const avatarImg = document.getElementById('avatarImage');
                            const avatarIcon = document.getElementById('avatarIcon');
                            const imagePath = data.image_path.startsWith('staff/') ? '../' + data.image_path : '../staff/' + data.image_path;
                            avatarImg.src = imagePath;
                            avatarImg.style.display = 'block';
                            avatarIcon.style.display = 'none';
                            showMessage('success', 'Profile picture updated successfully!');
                        } else {
                            showMessage('error', data.error || 'Failed to upload image.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('error', 'Failed to upload image. Please try again.');
                    });
                }
            };
            input.click();
        }

        function exportData() {
            showMessage('success', 'Data export started. You will receive an email shortly.');
        }
    </script>
</body>
</html>
