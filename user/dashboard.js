document.addEventListener("DOMContentLoaded", function () {
    // 1. Simulate User Data (Replace with real data later)
    const user = {
        firstName: "Wong",
        lastName: "Li Hua",
        phone: "+60 165756288",
        email: "wonglh@gmail.com",
        lastVisit: "20 Nov 2025"
    };

    // 2. Initialize Dashboard
    initDashboard(user);
});

function initDashboard(user) {
    // Set Text
    document.getElementById("dashUserName").innerText = `${user.firstName} ${user.lastName}`;
    
    // Set Form Values
    document.getElementById("profileFirst").value = user.firstName;
    document.getElementById("profileLast").value = user.lastName;
    document.getElementById("profilePhone").value = user.phone;
    document.getElementById("profileEmail").value = user.email;

    // Set Initials (e.g., "Wong" -> "WO")
    let initials = user.firstName.substring(0, 2).toUpperCase();
    document.getElementById("dashAvatar").innerText = initials;
}

// --- TAB SWITCHER ---
function switchTab(tabId, linkElement) {
    // 1. Hide all sections
    document.querySelectorAll('.dash-section').forEach(el => el.style.display = 'none');
    
    // 2. Remove active class from all links
    document.querySelectorAll('.dash-nav a').forEach(el => el.classList.remove('active'));

    // 3. Show target section & Activate Link
    document.getElementById(tabId).style.display = 'block';
    linkElement.classList.add('active');
}

// --- EDIT PROFILE LOGIC ---
function enableEdit() {
    // Enable inputs
    document.querySelectorAll('.dash-input').forEach(input => input.disabled = false);
    
    // Show Save/Cancel, Hide Edit button
    document.getElementById('editActions').style.display = 'flex';
}

function cancelEdit() {
    // Disable inputs
    document.querySelectorAll('.dash-input').forEach(input => input.disabled = true);
    
    // Hide Save/Cancel
    document.getElementById('editActions').style.display = 'none';
    
    // Optional: Reset values to original here
}

// --- BOOKING SUB-TABS ---
function filterBookings(type) {
    // 1. Toggle Buttons
    document.querySelectorAll('.sub-tab').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active'); // The clicked button

    // 2. Toggle Lists
    const upcomingList = document.getElementById('list-upcoming');
    const historyList = document.getElementById('list-history');

    if (type === 'upcoming') {
        upcomingList.style.display = 'block';
        historyList.style.display = 'none';
    } else {
        upcomingList.style.display = 'none';
        historyList.style.display = 'block';
    }
}