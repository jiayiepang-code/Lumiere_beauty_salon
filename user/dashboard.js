function switchSection(section, link) {
    // Hide all sections
    document.querySelectorAll('.dash-section').forEach(s => s.classList.remove('active'));

    // Show target section
    const targetSection = document.getElementById('section-' + section);
    if (targetSection) {
        targetSection.classList.add('active');
    }

    // Remove active from all links
    document.querySelectorAll('.dash-nav a').forEach(a => a.classList.remove('active'));

    // Activate clicked link if provided
    if (link) {
        link.classList.add('active');
    } else {
        // Find and activate the corresponding nav link
        const navLinks = document.querySelectorAll('.dash-nav a');
        navLinks.forEach(a => {
            if (a.getAttribute('onclick') && a.getAttribute('onclick').includes("'" + section + "'")) {
                a.classList.add('active');
            }
        });
    }

    // Update UI controls related to view all / show fewer
    if (typeof updateViewAllUI === 'function') updateViewAllUI();
    // Update booking list visibility if needed
    if (typeof updateBookingItemsVisibility === 'function') updateBookingItemsVisibility();
    // Update booking history visibility if needed
    if (typeof updateHistoryItemsVisibility === 'function') updateHistoryItemsVisibility();
}

// Profile Edit Functionality
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const section = urlParams.get('section');
    
    if (section) {
        // Small delay to ensure DOM is ready
        setTimeout(function() {
            switchSection(section, null);
        }, 100);
    } else {
        // Default to overview section
        const overviewSection = document.getElementById('section-overview');
        if (overviewSection) {
            overviewSection.classList.add('active');
        }
    }
    
    // Profile Edit
    const editBtn = document.getElementById('edit-profile-btn');
    const saveBtn = document.getElementById('save-profile-btn');
    const cancelBtn = document.getElementById('cancel-profile-btn');
    const firstNameInput = document.getElementById('profile-firstname');
    const lastNameInput = document.getElementById('profile-lastname');
    const emailInput = document.getElementById('profile-email');
    const profileMessage = document.getElementById('profile-message');
    
    let originalValues = {};
    
    if (editBtn) {
        editBtn.addEventListener('click', function() {
            originalValues = {
                firstname: firstNameInput.value,
                lastname: lastNameInput.value,
                email: emailInput.value
            };
            
            firstNameInput.removeAttribute('readonly');
            lastNameInput.removeAttribute('readonly');
            emailInput.removeAttribute('readonly');
            
            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-block';
            cancelBtn.style.display = 'inline-block';
            profileMessage.innerHTML = '';
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            firstNameInput.value = originalValues.firstname;
            lastNameInput.value = originalValues.lastname;
            emailInput.value = originalValues.email;
            
            firstNameInput.setAttribute('readonly', 'readonly');
            lastNameInput.setAttribute('readonly', 'readonly');
            emailInput.setAttribute('readonly', 'readonly');
            
            editBtn.style.display = 'inline-block';
            saveBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
            profileMessage.innerHTML = '';
        });
    }
    
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            const data = {
                firstName: firstNameInput.value.trim(),
                lastName: lastNameInput.value.trim(),
                email: emailInput.value.trim()
            };
            
            if (!data.firstName || !data.lastName) {
                profileMessage.innerHTML = '<p style="color: red;">First name and last name are required</p>';
                return;
            }
            
            fetch('../update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    profileMessage.innerHTML = '<p style="color: green;">Profile updated successfully!</p>';
                    firstNameInput.setAttribute('readonly', 'readonly');
                    lastNameInput.setAttribute('readonly', 'readonly');
                    emailInput.setAttribute('readonly', 'readonly');
                    editBtn.style.display = 'inline-block';
                    saveBtn.style.display = 'none';
                    cancelBtn.style.display = 'none';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    profileMessage.innerHTML = '<p style="color: red;">' + data.message + '</p>';
                }
            })
            .catch(err => {
                profileMessage.innerHTML = '<p style="color: red;">Error updating profile</p>';
            });
        });
    }
    
    // Password Update
    const updatePasswordBtn = document.getElementById('update-password-btn');
    const passwordMessage = document.getElementById('password-message');
    
    if (updatePasswordBtn) {
        updatePasswordBtn.addEventListener('click', function() {
            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                passwordMessage.innerHTML = '<p style="color: red;">All fields are required</p>';
                return;
            }
            
            if (newPassword !== confirmPassword) {
                passwordMessage.innerHTML = '<p style="color: red;">New passwords do not match</p>';
                return;
            }
            
            if (newPassword.length < 6) {
                passwordMessage.innerHTML = '<p style="color: red;">Password must be at least 6 characters</p>';
                return;
            }
            
            fetch('../update_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    currentPassword: currentPassword,
                    newPassword: newPassword,
                    confirmPassword: confirmPassword
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    passwordMessage.innerHTML = '<p style="color: green;">Password updated successfully!</p>';
                    document.getElementById('password-form').reset();
                    setTimeout(() => passwordMessage.innerHTML = '', 3000);
                } else {
                    passwordMessage.innerHTML = '<p style="color: red;">' + data.message + '</p>';
                }
            })
            .catch(err => {
                passwordMessage.innerHTML = '<p style="color: red;">Error updating password</p>';
            });
        });
    }
});

// View Booking Details - Make sure it's globally accessible
window.viewBookingDetails = function(bookingId) {
    console.log('viewBookingDetails called with bookingId:', bookingId);
    
    if (!bookingId) {
        console.error('Booking ID is required');
        alert('Error: Booking ID is missing');
        return;
    }
    
    const modalElement = document.getElementById('bookingModal');
    if (!modalElement) {
        console.error('Modal element not found');
        alert('Error: Modal not found. Please refresh the page.');
        return;
    }
    
    const contentDiv = document.getElementById('bookingDetailsContent');
    if (!contentDiv) {
        console.error('Content div not found');
        return;
    }
    
    // Show loading state
    contentDiv.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading booking details...</p></div>';
    
    // Show modal using Bootstrap 5
    let modal;
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            // Bootstrap 5
            modal = new bootstrap.Modal(modalElement, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
            modal.show();
        } else {
            console.error('Bootstrap is not loaded');
            alert('Error: Bootstrap is not loaded. Please refresh the page.');
            return;
        }
    } catch (error) {
        console.error('Error initializing modal:', error);
        alert('Error opening modal: ' + error.message);
        return;
    }
    
    // Load booking details
    fetch('../get_booking_details.php?booking_id=' + encodeURIComponent(bookingId))
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text();
        })
        .then(html => {
            contentDiv.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading booking details:', error);
            contentDiv.innerHTML = '<div class="alert alert-danger m-3"><strong>Error:</strong> Failed to load booking details. Please try again.<br><small>' + error.message + '</small></div>';
        });
};

// Backwards-compatible alias used by some buttons - Make sure it's globally accessible
window.viewDetails = function(bookingId) {
    console.log('viewDetails called with bookingId:', bookingId);
    if (typeof window.viewBookingDetails === 'function') {
        window.viewBookingDetails(bookingId);
    } else {
        console.error('viewBookingDetails function not found');
        alert('Error: Booking details function not loaded. Please refresh the page.');
    }
};

// Open Comment Modal for Completed Bookings
window.openCommentModal = function(bookingId) {
    console.log('openCommentModal called with bookingId:', bookingId);
    
    if (!bookingId) {
        alert('Error: Booking ID is missing');
        return;
    }
    
    const modalElement = document.getElementById('commentModal');
    if (!modalElement) {
        console.error('Comment modal element not found');
        alert('Error: Comment modal not found. Please refresh the page.');
        return;
    }
    
    // Set booking ID in the form
    document.getElementById('commentBookingId').value = bookingId;
    document.getElementById('commentText').value = '';
    document.getElementById('commentMessage').innerHTML = '';
    
    // Show modal using Bootstrap 5
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = new bootstrap.Modal(modalElement, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
            modal.show();
        } else {
            console.error('Bootstrap is not loaded');
            alert('Error: Bootstrap is not loaded. Please refresh the page.');
            return;
        }
    } catch (error) {
        console.error('Error initializing comment modal:', error);
        alert('Error opening comment modal: ' + error.message);
        return;
    }
};

// Submit Comment
window.submitComment = function() {
    const bookingId = document.getElementById('commentBookingId').value;
    const commentText = document.getElementById('commentText').value.trim();
    const messageDiv = document.getElementById('commentMessage');
    
    if (!commentText) {
        messageDiv.innerHTML = '<div class="alert alert-warning">Please enter a comment.</div>';
        return;
    }
    
    // Show loading state
    messageDiv.innerHTML = '<div class="alert alert-info">Submitting comment...</div>';
    
    // Disable submit button
    const submitBtn = document.querySelector('#commentModal .btn-primary');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    
    fetch('../submit_booking_comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            booking_id: bookingId,
            comment: commentText
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageDiv.innerHTML = '<div class="alert alert-success">' + (data.message || 'Comment submitted successfully!') + '</div>';
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('commentModal'));
                if (modal) {
                    modal.hide();
                }
                // Reload page to show updated comment
                location.reload();
            }, 1500);
        } else {
            messageDiv.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed to submit comment. Please try again.') + '</div>';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Comment';
        }
    })
    .catch(error => {
        console.error('Error submitting comment:', error);
        messageDiv.innerHTML = '<div class="alert alert-danger">Error submitting comment. Please try again.</div>';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Comment';
    });
};

// Cancel Booking
function cancelBooking(bookingId) {
    if (!confirm('Do you want to cancel this booking?')) {
        return;
    }
    
    fetch('../cancel_booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ booking_id: bookingId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Booking cancelled successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to cancel booking'));
        }
    })
    .catch(err => {
        alert('Error cancelling booking');
    });
}

// Remove Favorite Staff
function removeFavorite(staffEmail, buttonElement) {
    if (!confirm('Do you want to remove this staff member from your favourites?')) {
        return;
    }
    
    fetch('../remove_favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ staff_email: staffEmail })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Remove the card from the DOM
            const card = buttonElement.closest('.staff-favourite-card');
            if (card) {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    card.remove();
                    // Check if no more favorites, reload to show empty state
                    const remainingCards = document.querySelectorAll('.staff-favourite-card');
                    if (remainingCards.length === 0) {
                        location.reload();
                    }
                }, 300);
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to remove favorite'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error removing favorite');
    });
}

function addToFavourites(staffEmail) {
    fetch('../add_favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ staff_email: staffEmail })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Added to favourites ❤️');
        } else {
            alert(data.message || 'Already in favourites');
        }
    })
    .catch(() => alert('Failed to add favourite'));
}

// FAQ Toggle Function
function toggleFaq(element) {
    const faqItem = element.closest('.faq-item');
    const isActive = faqItem.classList.contains('active');
    
    // Close all FAQ items
    document.querySelectorAll('.faq-item').forEach(item => {
        item.classList.remove('active');
        const answer = item.querySelector('.faq-answer');
        if (answer) {
            answer.classList.remove('active');
        }
    });
    
    // Toggle current item if it wasn't active
    if (!isActive) {
        faqItem.classList.add('active');
        const answer = faqItem.querySelector('.faq-answer');
        if (answer) {
            answer.classList.add('active');
        }
    }
}

// Help Contact Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const helpForm = document.getElementById('helpContactForm');
    if (helpForm) {
        helpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = {
                name: document.getElementById('help-name').value,
                email: document.getElementById('help-email').value,
                phone: document.getElementById('help-phone').value,
                subject: document.getElementById('help-subject').value,
                message: document.getElementById('help-message').value
            };
            
            // Here you would typically send the data to a server
            // For now, we'll just show a success message
            alert('Thank you for your message! We will get back to you soon.');
            
            // Reset form
            helpForm.reset();
        });
    }
    
    // Initialize first FAQ item as active
    const firstFaqItem = document.querySelector('.faq-item');
    if (firstFaqItem) {
        firstFaqItem.classList.add('active');
        const firstAnswer = firstFaqItem.querySelector('.faq-answer');
        if (firstAnswer) {
            firstAnswer.classList.add('active');
        }
    }

    // Initialize bookings view (show only first 3 by default)
    // Note: click handling is done via inline onclick in the HTML to avoid double-toggling
    if (typeof updateBookingItemsVisibility === 'function') updateBookingItemsVisibility();
    
    // Initialize booking history view (show only first 3 by default)
    // Note: click handling is done via inline onclick in the HTML to avoid double-toggling
    if (typeof updateHistoryItemsVisibility === 'function') updateHistoryItemsVisibility();
});

// Toggle / preference helpers for "View all" control and "Show fewer"
function hideViewAllPref(e) {
    if (e) e.preventDefault();
    try {
        localStorage.setItem('hideViewAll', '1');
    } catch (err) { /* ignore */ }
    document.querySelectorAll('.view-all-action').forEach(el => el.style.display = 'none');
    // previous separate show-fewer control removed; toggle is handled by #toggle-view-all-link
}

function updateViewAllUI() {
    const hide = (function(){ try { return localStorage.getItem('hideViewAll') === '1'; } catch(e){ return false; } })();
    document.querySelectorAll('.view-all-action').forEach(el => el.style.display = hide ? 'none' : 'inline-flex');
    const bookingsSection = document.getElementById('section-bookings');
    // single toggle link '#toggle-view-all-link' handles show/hide behavior now
}

// Booking list toggle: show only first 3 items by default, toggle to show all
function updateBookingItemsVisibility() {
    const grid = document.querySelector('#section-bookings .bookings-grid');
    if (!grid) return;

    // Use direct children to be extra-safe and ensure we include all cards
    const items = Array.from(grid.children);
    const toggle = document.getElementById('toggle-view-all-link');

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
            sessionId:'debug-session',
            runId:'pre-fix',
            hypothesisId:'H1',
            location:'dashboard.js:updateBookingItemsVisibility',
            message:'Upcoming visibility update',
            data:{
                itemCount:items.length,
                hasExpandedClass:grid.classList.contains('expanded')
            },
            timestamp:Date.now()
        })
    }).catch(()=>{});
    // #endregion

    // If 3 or fewer items, always show all and hide the toggle link
    if (items.length <= 3) {
        items.forEach(item => {
            item.style.display = 'flex';
        });
        if (toggle) {
            toggle.textContent = 'View all';
            toggle.style.display = 'none';
        }
        return;
    }

    const expanded = grid.classList.contains('expanded');

    items.forEach((item, idx) => {
        if (!expanded && idx >= 3) {
            item.style.display = 'none';
        } else {
            item.style.display = 'flex';
        }
    });

    if (toggle) {
        toggle.style.display = 'inline-flex';
        toggle.textContent = expanded ? 'Show fewer' : 'View all';
    }
}

function toggleViewAllBookings(e) {
    if (e) e.preventDefault();
    const grid = document.querySelector('#section-bookings .bookings-grid');
    if (!grid) return;

    const before = grid.classList.contains('expanded');

    grid.classList.toggle('expanded');
    updateBookingItemsVisibility();

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
            sessionId:'debug-session',
            runId:'pre-fix',
            hypothesisId:'H2',
            location:'dashboard.js:toggleViewAllBookings',
            message:'Toggle upcoming view-all clicked',
            data:{
                wasExpandedBefore:before,
                isExpandedAfter:grid.classList.contains('expanded')
            },
            timestamp:Date.now()
        })
    }).catch(()=>{});
    // #endregion
}

// Booking history toggle: show only first 3 items by default, toggle to show all
function updateHistoryItemsVisibility() {
    const grid = document.querySelector('#section-bookings .booking-history-grid');
    if (!grid) return;

    const items = Array.from(grid.children);
    const toggle = document.getElementById('toggle-history-view-all-link');

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
            sessionId:'debug-session',
            runId:'pre-fix',
            hypothesisId:'H3',
            location:'dashboard.js:updateHistoryItemsVisibility',
            message:'History visibility update',
            data:{
                itemCount:items.length,
                hasExpandedClass:grid.classList.contains('expanded')
            },
            timestamp:Date.now()
        })
    }).catch(()=>{});
    // #endregion

    if (items.length <= 3) {
        items.forEach(item => {
            item.style.display = 'flex';
        });
        if (toggle) {
            toggle.textContent = 'View all';
            toggle.style.display = 'none';
        }
        return;
    }

    const expanded = grid.classList.contains('expanded');

    items.forEach((item, idx) => {
        if (!expanded && idx >= 3) {
            item.style.display = 'none';
        } else {
            item.style.display = 'flex';
        }
    });

    if (toggle) {
        toggle.style.display = 'inline-flex';
        toggle.textContent = expanded ? 'Show fewer' : 'View all';
    }
}

function toggleHistoryViewAll(e) {
    if (e) e.preventDefault();
    const grid = document.querySelector('#section-bookings .booking-history-grid');
    if (!grid) return;

    const before = grid.classList.contains('expanded');

    grid.classList.toggle('expanded');
    updateHistoryItemsVisibility();

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
            sessionId:'debug-session',
            runId:'pre-fix',
            hypothesisId:'H4',
            location:'dashboard.js:toggleHistoryViewAll',
            message:'Toggle history view-all clicked',
            data:{
                wasExpandedBefore:before,
                isExpandedAfter:grid.classList.contains('expanded')
            },
            timestamp:Date.now()
        })
    }).catch(()=>{});
    // #endregion
}

// Ensure initial state for view-all controls on load
document.addEventListener('DOMContentLoaded', function() {
    updateViewAllUI();
    updateHistoryItemsVisibility();
});

const faqs = [
    {
      question: "How do I cancel or reschedule my appointment?",
      answer: "To make changes to your booking, you need to cancel your current appointment and create a new one. Go to 'My Bookings', click 'Cancel' on the appointment, then book a new slot."
    },
    {
      question: "What is the cancellation policy?",
      answer: "You can cancel your appointment anytime through the dashboard. However, we appreciate at least 24 hours notice to allow other clients to book the slot."
    },
    {
      question: "What payment methods do you accept?",
      answer: "We accept cash and card payments at the salon. Payment is required when you arrive for your appointment."
    },
    {
      question: "Can I book multiple services at once?",
      answer: "Yes! You can select multiple services when making a booking. The system will calculate the total duration and price for you."
    },
    {
      question: "How will I receive appointment reminders?",
      answer: "If you've enabled email notifications in your profile settings, you'll receive booking confirmations and reminders via email."
    },
    {
      question: "Can I request a specific staff member?",
      answer: "Absolutely! When booking, you can choose your preferred staff member. You can also save favorite staff in the 'Favourite Staff' section for quick access."
    }
  ];

  document.querySelectorAll('.faq-question').forEach(button => {
    button.addEventListener('click', () => {
        const item = button.closest('.faq-item');
        const answer = item.querySelector('.faq-answer');
        const icon = button.querySelector('.faq-toggle');
        const isOpen = item.classList.contains('active');

        // Close all items
        document.querySelectorAll('.faq-item').forEach(i => {
            i.classList.remove('active');
            i.querySelector('.faq-answer').style.maxHeight = null;
            i.querySelector('.faq-toggle').textContent = '+';
        });

        // Open clicked item
        if (!isOpen) {
            item.classList.add('active');
            answer.style.maxHeight = answer.scrollHeight + 'px';
            icon.textContent = '−';
        }
    });
});

