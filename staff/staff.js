// Set Current Date
const dateElement = document.getElementById('currentDate');
const options = { weekday: 'short', day: 'numeric', month: 'short' };
const today = new Date();
dateElement.innerText = today.toLocaleDateString('en-US', options);

// Logout Function
function logout() {
    if(confirm("Are you sure you want to logout?")) {
        // Redirect back to the main login page (Up one folder)
        window.location.href = "../home.html";
    }
}

// Optional: Simulate Loading Data
console.log("Staff Dashboard Loaded Successfully");