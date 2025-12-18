            </div>
        </main>
    </div>

    <script>
        // Store CSRF token for API requests
        const csrfToken = '<?php echo $csrf_token; ?>';
        
        // Mobile menu toggle - consolidated handler
        (function() {
            function setupHamburgerMenu() {
                const hamburgerBtn = document.getElementById('hamburgerBtn');
                const sidebar = document.getElementById('sidebar');
                
                if (!hamburgerBtn || !sidebar) {
                    return;
                }
                
                // Remove any existing listeners by cloning the button
                const newBtn = hamburgerBtn.cloneNode(true);
                hamburgerBtn.parentNode.replaceChild(newBtn, hamburgerBtn);
                
                // Add click handler
                newBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                    newBtn.classList.toggle('active');
                });
                
                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768) {
                        if (sidebar.classList.contains('active') && 
                            !sidebar.contains(e.target) && 
                            !newBtn.contains(e.target)) {
                            sidebar.classList.remove('active');
                            newBtn.classList.remove('active');
                        }
                    }
                });
            }
            
            // Setup when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', setupHamburgerMenu);
            } else {
                setupHamburgerMenu();
            }
        })();
        
        // Logout handler with SweetAlert2 confirmation
        async function handleLogout() {
            const basePath = '<?php echo isset($base_path) ? $base_path : ".."; ?>';
            
            const result = await Swal.fire({
                title: 'Logout?',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#c29076',
                cancelButtonColor: '#6C757D',
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel'
            });
            
            if (!result.isConfirmed) {
                return;
            }
            
            try {
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'footer.php:72', message: 'Initiating logout request', data: {basePath: basePath, url: `${basePath}/api/admin/auth/logout.php`}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H2'})}).catch(() => {});
                // #endregion

                const response = await fetch(`${basePath}/api/admin/auth/logout.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'footer.php:82', message: 'Logout response received', data: {status: response.status, statusText: response.statusText, contentType: response.headers.get('content-type'), ok: response.ok}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H2'})}).catch(() => {});
                // #endregion

                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    // #region agent log
                    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'footer.php:88', message: 'Non-JSON response from logout API', data: {status: response.status, contentType: contentType, responseText: text.substring(0, 200)}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H2'})}).catch(() => {});
                    // #endregion
                    throw new Error('Server returned non-JSON response');
                }
                
                const data = await response.json();
                
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'footer.php:95', message: 'Logout data parsed', data: {success: data.success, hasRedirect: !!data.redirect, redirect: data.redirect, message: data.message}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H2'})}).catch(() => {});
                // #endregion
                
                if (data.success) {
                    Swal.fire({
                        title: 'Logged out!',
                        text: 'You have been successfully logged out.',
                        icon: 'success',
                        confirmButtonColor: '#c29076',
                        timer: 1500,
                        timerProgressBar: true
                    }).then(() => {
                        // Redirect to user index.php (homepage)
                        // Construct absolute path using basePath to avoid relative path issues
                        const redirectUrl = data.redirect ? 
                            (data.redirect.startsWith('/') ? data.redirect : `${basePath}/${data.redirect.replace(/^\.\.\//g, '')}`) :
                            `${basePath}/user/index.php`;
                        // #region agent log
                        fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'footer.php:107', message: 'Redirecting after successful logout', data: {redirectUrl: redirectUrl, apiRedirect: data.redirect, basePath: basePath, currentPath: window.location.pathname}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H2'})}).catch(() => {});
                        // #endregion
                        window.location.href = redirectUrl;
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Logout failed. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#c29076'
                    }).then(() => {
                        // Still redirect to homepage even on error
                        window.location.href = data.redirect || `${basePath}/user/index.php`;
                    });
                }
            } catch (error) {
                console.error('Logout error:', error);
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'footer.php:118', message: 'Logout exception caught', data: {errorMessage: error.message, errorStack: error.stack?.substring(0, 200)}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H2'})}).catch(() => {});
                // #endregion
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred during logout. Redirecting...',
                    icon: 'error',
                    confirmButtonColor: '#c29076',
                    timer: 2000,
                    timerProgressBar: true
                }).then(() => {
                    // Redirect to user homepage instead of admin login
                    window.location.href = `${basePath}/user/index.php`;
                });
            }
        }
        
        // Make handleLogout globally accessible
        window.handleLogout = handleLogout;
        
        // Session timeout warning (28 minutes - 2 minutes before actual timeout)
        setTimeout(() => {
            if (confirm('Your session will expire in 2 minutes. Do you want to stay logged in?')) {
                window.location.reload();
            }
        }, 28 * 60 * 1000);
    </script>
</body>
</html>
