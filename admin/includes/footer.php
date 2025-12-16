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
                const response = await fetch(`${basePath}/api/admin/auth/logout.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        title: 'Logged out!',
                        text: 'You have been successfully logged out.',
                        icon: 'success',
                        confirmButtonColor: '#c29076',
                        timer: 1500,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = data.redirect || `${basePath}/admin/login.html`;
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Logout failed. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#c29076'
                    });
                }
            } catch (error) {
                console.error('Logout error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred during logout. Redirecting...',
                    icon: 'error',
                    confirmButtonColor: '#c29076',
                    timer: 2000,
                    timerProgressBar: true
                }).then(() => {
                    window.location.href = `${basePath}/admin/login.html`;
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
