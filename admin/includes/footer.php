            </div>
        </main>
    </div>

    <script>
        // Store CSRF token for API requests
        const csrfToken = '<?php echo $csrf_token; ?>';
        
        // Mobile menu toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                hamburgerBtn.classList.toggle('active');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                        sidebar.classList.remove('active');
                        hamburgerBtn.classList.remove('active');
                    }
                }
            });
        }
        
        // Logout handler
        async function handleLogout() {
            if (!confirm('Are you sure you want to logout?')) {
                return;
            }
            
            try {
                const basePath = '<?php echo isset($base_path) ? $base_path : ".."; ?>';
                const response = await fetch(`${basePath}/api/admin/auth/logout.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = data.redirect || `${basePath}/admin/login.html`;
                } else {
                    alert('Logout failed. Please try again.');
                }
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = `${basePath}/admin/login.html`;
            }
        }
        
        // Session timeout warning (28 minutes - 2 minutes before actual timeout)
        setTimeout(() => {
            if (confirm('Your session will expire in 2 minutes. Do you want to stay logged in?')) {
                window.location.reload();
            }
        }, 28 * 60 * 1000);
    </script>
</body>
</html>
