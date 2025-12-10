<?php
/**
 * Create Admin Account Script
 * File: create_admin.php (place in root directory)
 * 
 * Use this to create a new admin account
 * Access: http://localhost/lumiere/create_admin.php
 * DELETE THIS FILE AFTER USE!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
require_once 'config/utils.php';

$success = false;
$error = '';
$admin_created = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    // Validate inputs
    if (empty($email) || empty($phone) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = 'All fields are required!';
    } else {
        try {
            $conn = getDBConnection();
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT staff_email FROM Staff WHERE staff_email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Email already exists!';
            } else {
                // Sanitize phone
                $phone_clean = sanitizePhone($phone);
                
                // Check if phone already exists
                $stmt = $conn->prepare("SELECT phone FROM Staff WHERE phone = ?");
                $stmt->bind_param("s", $phone_clean);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = 'Phone number already exists!';
                } else {
                    // Hash password
                    $password_hash = hashPassword($password);
                    
                    // Insert admin account
                    $stmt = $conn->prepare("
                        INSERT INTO Staff (staff_email, phone, password, first_name, last_name, role, is_active, created_at)
                        VALUES (?, ?, ?, ?, ?, 'admin', 1, NOW())
                    ");
                    
                    $stmt->bind_param("sssss", $email, $phone_clean, $password_hash, $first_name, $last_name);
                    
                    if ($stmt->execute()) {
                        $success = true;
                        $admin_created = [
                            'email' => $email,
                            'phone' => $phone_clean,
                            'name' => $first_name . ' ' . $last_name
                        ];
                    } else {
                        $error = 'Failed to create admin account: ' . $stmt->error;
                    }
                }
            }
            
            $stmt->close();
            $conn->close();
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #ac7c6e 0%, #a26e60 50%, #6d4236 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            color: #a26e60;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #a26e60;
        }
        
        .phone-hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: #a26e60;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #8a5d52;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .success-box h2 {
            color: #155724;
            margin-bottom: 15px;
        }
        
        .success-box p {
            margin: 8px 0;
        }
        
        .success-box code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .link-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #a26e60;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .link-btn:hover {
            background: #8a5d52;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .warning strong {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Admin Account</h1>
        <p class="subtitle">Lumière Beauty Salon Admin Portal</p>
        
        <?php if ($success && $admin_created): ?>
            <div class="success-box">
                <h2>✅ Admin Account Created Successfully!</h2>
                <p><strong>Email:</strong> <code><?php echo htmlspecialchars($admin_created['email']); ?></code></p>
                <p><strong>Phone:</strong> <code><?php echo htmlspecialchars($admin_created['phone']); ?></code></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($admin_created['name']); ?></p>
                <p style="margin-top: 15px;">
                    <a href="admin/login.html" class="link-btn">Go to Login Page</a>
                </p>
            </div>
            
            <div class="warning">
                <strong>⚠️ SECURITY WARNING</strong>
                Delete this file (create_admin.php) immediately for security!
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error-box">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="admin@lumiere.com"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input 
                        type="text" 
                        id="first_name" 
                        name="first_name" 
                        placeholder="John"
                        required
                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input 
                        type="text" 
                        id="last_name" 
                        name="last_name" 
                        placeholder="Doe"
                        required
                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        placeholder="12 345 6789 or 0123456789"
                        required
                        value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                    >
                    <p class="phone-hint">Enter Malaysian mobile number (will be saved as +60123456789)</p>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Min. 8 characters"
                        required
                        minlength="8"
                    >
                </div>
                
                <button type="submit" class="btn">Create Admin Account</button>
            </form>
            
            <div class="warning" style="margin-top: 30px;">
                <strong>⚠️ SECURITY NOTE</strong>
                After creating your admin account, delete this file immediately!
            </div>
        <?php endif; ?>
    </div>
</body>
</html>