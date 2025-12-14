<?php
// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_path', '/Lumiere-beauty-salon/');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .info { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        h2 { color: #c29076; }
        pre { background: #f9f9f9; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .status { padding: 5px 10px; border-radius: 4px; display: inline-block; margin: 5px 0; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>Session Debug Information</h1>
    
    <div class="info">
        <h2>Session Status</h2>
        <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
        <p><strong>Session Status:</strong> 
            <?php 
            $status = session_status();
            if ($status === PHP_SESSION_ACTIVE) {
                echo '<span class="status success">ACTIVE</span>';
            } else if ($status === PHP_SESSION_NONE) {
                echo '<span class="status error">NONE</span>';
            } else {
                echo '<span class="status error">DISABLED</span>';
            }
            ?>
        </p>
    </div>
    
    <div class="info">
        <h2>Session Data</h2>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>
    
    <div class="info">
        <h2>Admin Authentication Check</h2>
        <?php
        require_once 'includes/auth_check.php';
        $isAuth = isAdminAuthenticated();
        ?>
        <p><strong>Is Authenticated:</strong> 
            <span class="status <?php echo $isAuth ? 'success' : 'error'; ?>">
                <?php echo $isAuth ? 'YES' : 'NO'; ?>
            </span>
        </p>
        
        <?php if (isset($_SESSION['admin'])): ?>
            <h3>Admin Data:</h3>
            <pre><?php print_r($_SESSION['admin']); ?></pre>
        <?php else: ?>
            <p class="status error">No admin session data found</p>
        <?php endif; ?>
    </div>
    
    <div class="info">
        <h2>Cookie Information</h2>
        <pre><?php print_r($_COOKIE); ?></pre>
    </div>
    
    <div class="info">
        <h2>Session Configuration</h2>
        <p><strong>Cookie Path:</strong> <?php echo ini_get('session.cookie_path'); ?></p>
        <p><strong>Cookie Domain:</strong> <?php echo ini_get('session.cookie_domain'); ?></p>
        <p><strong>Cookie HttpOnly:</strong> <?php echo ini_get('session.cookie_httponly'); ?></p>
        <p><strong>Cookie Secure:</strong> <?php echo ini_get('session.cookie_secure'); ?></p>
    </div>
    
    <div class="info">
        <h2>Test API Call</h2>
        <button onclick="testAPI()">Test Customer List API</button>
        <pre id="apiResult"></pre>
    </div>
    
    <script>
        async function testAPI() {
            const resultEl = document.getElementById('apiResult');
            resultEl.textContent = 'Loading...';
            
            try {
                const response = await fetch('../api/admin/customers/list.php', {
                    credentials: 'same-origin'
                });
                
                const text = await response.text();
                resultEl.textContent = 'Status: ' + response.status + '\n\n' + text;
            } catch (error) {
                resultEl.textContent = 'Error: ' + error.message;
            }
        }
    </script>
    
    <p><a href="index.php">Back to Admin Dashboard</a></p>
</body>
</html>
