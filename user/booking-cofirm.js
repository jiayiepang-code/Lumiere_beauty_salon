// Simulate Login Status
// Set to 'false' to see the warning box
const isLoggedIn = true; 

document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in
    if (!isLoggedIn) {
        const alertBox = document.getElementById('login-alert');
        if(alertBox) {
            alertBox.style.display = 'block';
        }
    }
});

function confirmBooking() {
    // 1. You could add logic here to check if they filled out required fields
    
    // 2. Redirect logic
    if(!isLoggedIn) {
        // If strict, you can force them to login first
        // alert("Please login to complete your booking.");
        // window.location.href = 'login.html';
        
        // OR allow guest checkout and just go to success
        window.location.href = 'booking-success.html';
    } else {
        // Logged in success
        window.location.href = 'booking-success.html';
    }
}