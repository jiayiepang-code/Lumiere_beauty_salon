document.addEventListener("DOMContentLoaded", function () {
    
    // --- 1. SIMULATE LOGGED-IN USER ---
    // In the future, this data will come from your Database/PHP.
    const currentUser = {
        firstName: "Wong", 
        lastName: "Yi",
        isLoggedIn: true
    };

    // --- 2. INITIALIZE DASHBOARD ---
    if (currentUser.isLoggedIn) {
        updateProfileUI(currentUser);
    } else {
        // Fallback for guest
        document.getElementById("panelUserName").innerText = "Guest";
        document.getElementById("dynamicAvatar").innerText = "?";
    }
});

// --- FUNCTION TO UPDATE UI (Name & Initials) ---
function updateProfileUI(user) {
    // A. Update Name Text
    const fullName = `${user.firstName} ${user.lastName}`;
    document.getElementById("panelUserName").innerText = fullName;

    // B. Generate Initials (First 2 letters of First Name)
    // Example: "Wong" -> "WO"
    let initials = "";
    if (user.firstName) {
        initials = user.firstName.substring(0, 2).toUpperCase();
    } else {
        initials = "US"; 
    }

    // C. Update Avatar Circle
    const avatarEl = document.getElementById("dynamicAvatar");
    if(avatarEl) {
        avatarEl.innerText = initials;
    }
}

// --- 3. LOGOUT FUNCTION ---
function handleLogout() {
    if(confirm("Are you sure you want to log out?")) {
        console.log("User logged out");
        // Reload page to simulate logout
        window.location.href = "home.html"; 
    }
}

// ==================================
// 4. PROFILE PANEL TOGGLE (Your Old Logic)
// ==================================
const profileToggle = document.getElementById("profileToggle");
const profilePanel = document.getElementById("profilePanel");
const panelClose = document.getElementById("panelClose");

// Open Sidebar
if(profileToggle) {
    profileToggle.addEventListener("click", (e) => {
        // Stop the click from bubbling to the document (prevents immediate closing)
        e.stopPropagation(); 
        profilePanel.classList.add("open");
    });
}

// Close Sidebar Button
if(panelClose) {
    panelClose.addEventListener("click", () => {
        profilePanel.classList.remove("open");
    });
}

// Click Outside to Close
document.addEventListener("click", (e) => {
    // If the panel is open AND we clicked OUTSIDE the panel AND OUTSIDE the toggle button
    if (profilePanel.classList.contains("open") && 
        !profilePanel.contains(e.target) && 
        !profileToggle.contains(e.target)) {
        
        profilePanel.classList.remove("open");
    }   
});

// ==================================
// TOP SERVICES SLIDER
// ==================================
let currentServiceIndex = 0; // Separate index for services

const services = [
    {
        title: "Facial Time!",
        desc: "Deep cleansing & glow facial to refresh your skin."
    },
    {
        title: "Aromatic Massage",
        desc: "Relaxing full-body massage with essential oils."
    },
    {
        title: "Glow-Up Package",
        desc: "Facial + mask + shoulder massage in one session."
    }
];

const serviceTitle = document.getElementById("serviceTitle");
const serviceDesc = document.getElementById("serviceDesc");
const prevServiceBtn = document.getElementById("prevService");
const nextServiceBtn = document.getElementById("nextService");
const dotsContainer = document.getElementById("serviceDots");

function renderDots() {
    dotsContainer.innerHTML = "";
    services.forEach((_, idx) => {
        const dot = document.createElement("span");
        dot.className = "slider-dot" + (idx === currentServiceIndex ? " active" : "");
        dotsContainer.appendChild(dot);
    });
}

function renderService() {
    const s = services[currentServiceIndex];
    serviceTitle.textContent = s.title;
    serviceDesc.textContent = s.desc;
    renderDots();
}

prevServiceBtn.addEventListener("click", () => {
    currentServiceIndex = (currentServiceIndex - 1 + services.length) % services.length;
    renderService();
});

nextServiceBtn.addEventListener("click", () => {
    currentServiceIndex = (currentServiceIndex + 1) % services.length;
    renderService();
});

renderService(); // initial service load


// ==================================
// HERO TEXT TYPING ANIMATION
// ==================================
const heroTitle = document.querySelector(".hero-title");

if (heroTitle) {
    const fullText = heroTitle.textContent.trim();
    heroTitle.textContent = "";

    let charIndex = 0;

    function typeNextChar() {
        if (charIndex <= fullText.length) {
            heroTitle.textContent = fullText.slice(0, charIndex);
            charIndex++;
            setTimeout(typeNextChar, 70); // typing speed
        }
    }

    window.addEventListener("load", () => {
        setTimeout(typeNextChar, 300); // small delay
    });
}

// ==================================
// HERO BUTTON SMOOTH SCROLL
// ==================================
const exploreBtn = document.getElementById("exploreBtn");
const servicesSection = document.getElementById("services");

if (exploreBtn && servicesSection) {
    exploreBtn.addEventListener("click", () => {
        servicesSection.scrollIntoView({ behavior: "smooth" });
    });
}

const bookNowBtn = document.querySelector(".book-btn");
const bookingSection = document.getElementById("booking");

if (bookNowBtn && bookingSection) {
    bookNowBtn.addEventListener("click", () => {
        bookingSection.scrollIntoView({ behavior: "smooth" });
    });
}

// ============================
// MEET THE TEAM FILTER SYSTEM
// ============================

const teamProfilesData = {
    hair: [
        { name: "Sophia Tan", role: "Senior Hair Stylist", photo: "images/team/hair1.jpg" },
        { name: "Mira Lee", role: "Hair Artist", photo: "images/team/hair2.jpg" }
    ],
    beautician: [
        { name: "Alicia Wong", role: "Beautician", photo: "images/team/beauty1.jpg" },
        { name: "Grace Lim", role: "Skin Specialist", photo: "images/team/beauty2.jpg" }
    ],
    massage: [
        { name: "Hana Yusuf", role: "Massage Therapist", photo: "images/team/massage1.jpg" }
    ],
    nail: [
        { name: "Jia Mei", role: "Manicurist", photo: "images/team/nail1.jpg" },
        { name: "Bella Ching", role: "Nail Artist", photo: "images/team/nail2.jpg" }
    ]
};

const teamButtons = document.querySelectorAll(".team-cat");
const teamProfilesDiv = document.getElementById("teamProfiles");

function loadProfiles(category) {
    teamProfilesDiv.innerHTML = "";
    teamProfilesData[category].forEach(person => {
        const card = `
            <div class="team-card">
                <img src="${person.photo}" class="team-photo" alt="${person.name}">
                <h3>${person.name}</h3>
                <p>${person.role}</p>
            </div>
        `;
        teamProfilesDiv.innerHTML += card;
    });
}

teamButtons.forEach(btn => {
    btn.addEventListener("click", () => {
        teamButtons.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        loadProfiles(btn.dataset.category);
    });
});

// ============================
// INTRODUCING AWARD SECTION
// ============================
loadProfiles("hair");
document.querySelector('[data-category="hair"]').classList.add("active");

// DRAG TO SCROLL for facility slider
const facilitySlider = document.getElementById("facilitySlider");

let isDownFacility = false;
let startXFacility;
let scrollLeftFacility;

facilitySlider.addEventListener("mousedown", (e) => {
    e.preventDefault(); // <--- 新增: 阻止默认行为（如图片拖放）
    isDownFacility = true;
    startXFacility = e.pageX - facilitySlider.offsetLeft;
    scrollLeftFacility = facilitySlider.scrollLeft;
});

facilitySlider.addEventListener("mouseleave", () => {
    isDownFacility = false;
});

facilitySlider.addEventListener("mouseup", () => {
    isDownFacility = false;
});

facilitySlider.addEventListener("mousemove", (e) => {
    if (!isDownFacility) return;
    e.preventDefault();
    const x = e.pageX - facilitySlider.offsetLeft;
    const walk = (x - startXFacility) * 1.5;
    facilitySlider.scrollLeft = scrollLeftFacility - walk;
});

// ============================
// COMMENT SECTION
// ============================
let currentFeedbackIndex = 0; // Separate index for customer feedback

const fbPrevBtn = document.getElementById('fbPrev');
const fbNextBtn = document.getElementById('fbNext');
const feedbackCardsContainer = document.getElementById('feedbackCardsContainer');

// Get the total number of feedback cards
const totalFeedbackCards = document.querySelectorAll('.feedback-card').length;

// The number of visible feedback cards at once (this is set to 3 in this case)
const cardsPerSlide = 3;

fbNextBtn.addEventListener('click', () => {
    if (currentFeedbackIndex < totalFeedbackCards - cardsPerSlide) {
        currentFeedbackIndex++;
        feedbackCardsContainer.style.transform = `translateX(-${currentFeedbackIndex * (100 / cardsPerSlide)}%)`;
    }
});

fbPrevBtn.addEventListener('click', () => {
    if (currentFeedbackIndex > 0) {
        currentFeedbackIndex--;
        feedbackCardsContainer.style.transform = `translateX(-${currentFeedbackIndex * (100 / cardsPerSlide)}%)`;
    }
});
