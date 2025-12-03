// ==================================
// PROFILE PANEL TOGGLE
// ==================================
const profileToggle = document.getElementById("profileToggle");
const profilePanel = document.getElementById("profilePanel");
const panelClose = document.getElementById("panelClose");

profileToggle.addEventListener("click", () => {
    profilePanel.classList.toggle("open");
});

panelClose.addEventListener("click", () => {
    profilePanel.classList.remove("open");
});

// Click outside to close
document.addEventListener("click", (e) => {
    if (
        !profilePanel.contains(e.target) &&
        !profileToggle.contains(e.target)
    ) {
        profilePanel.classList.remove("open");
    }
});

// ======================
// SERVICE DATA STRUCTURE
// ======================
const serviceData = {
    haircut: {
        title: "Haircut",
        description: "Maintain a polished and sophisticated look by getting your regular quality haircut service from our salon or let our skilled hair stylists create the hairstyle that complements your personality.",
        image: "../images/28.png",
        prices: [
            { name: "Men Haircut", price: "RM25++", duration: 30 },
            { name: "Women Haircut", price: "RM35++", duration: 45 },
            { name: "Wash + Cut + Blow", price: "RM50", duration: 60 },
            { name: "Wash Only", price: "RM20", duration: 20 },
        ],

        // SUBSECTIONS
        subsections: [
            {
                subtitle: "Styling",
                description: "Professional styling service to suit your character, outfit, or special occasion.",
                image: "../images/29.png",
                prices: [
                    { name: "Basic Styling", price: "RM30++", duration: 30 },
                    { name: "Rebonding (Straighten)", price: "RM200", duration: 180 },
                    { name: "Perming (Curling)", price: "RM250", duration: 180 },
                ]
            },
            {
                subtitle: "Hair Colouring",
                description: "Transform your look with natural shades or bold new colors.",
                image: "../images/30.png",
                prices: [
                    { name: "Short Hair", price: "RM150", duration: 120 },
                    { name: "Medium Hair", price: "RM180", duration: 150 },
                    { name: "Long Hair", price: "RM200", duration: 180 },
                    { name: "Root Touch Up", price: "RM70", duration: 90 },
                ]
            },
            {
                subtitle: "Treatment",
                description: "Nourish and repair damaged hair with our premium hair treatments.",
                image: "../images/31.png",
                prices: [
                    { name: "Scalp Treatment", price: "RM150", duration: 45 },
                    { name: "Keratin Treatment", price: "RM300", duration: 120 },
                ]
            }
        ]
    },

    facial: {
    title: "Anti-Aging Facial",
    description: "Reduce fine lines, improve skin firmness and boost collagen production.",
    image: "../images/33.png",
    prices: [
        { name: "Anti-Aging Facial", price: "RM250", duration: 90 },
    ],

    subsections: [
        {
            subtitle: "Deep Cleansing Facial",
            description: "Intensive extraction and detox treatment suitable for congested or acne-prone skin.",
            image: "../images/32.png",
            prices: [
                { name: "Deep Cleansing Facial", price: "RM200", duration: 75 },
            ],
        },
        {
            subtitle: "Hydrating Facial",
            description: "Hydrating and soothing treatment that restores moisture using hyaluronic-rich formulas.",
            image: "../images/34.png",
            prices: [
                { name: "Hydrating Facial", price: "RM150", duration: 60 },
            ]
        },
        {
            subtitle: "Brightening Facial",
            description: "Targets dark spots and uneven tone with vitamin C and AHA peel for a glowing complexion.",
            image: "../images/35.png",
            prices: [
                { name: "Brightening Facial", price: "RM150", duration: 60 },
            ]
        }
    ]
},

    manicure: {
    title: "Classic Manicure",
    description: "Indulge in a luxurious manicure to keep your nails looking fresh, polished, and beautiful.",
    image: "../images/43.png",
    prices: [
                { name: "Basic Manicure", price: "RM70", duration: 45 },
                { name: "Just Colour", price: "RM40", duration: 20 },
                { name: "Nail Care", price: "RM48", duration: 30 },
    ],

    subsections: [
        {
            subtitle: "Nail Gelish",
            description: "Long-lasting gel polish cured under UV light for a shiny, durable finish.",
            image: "../images/47.png",
            prices: [
                { name: "Gel Manicure", price: "RM138", duration: 75 },
                { name: "Gel Remove", price: "RM20", duration: 20 },
            ]
        },

        {
            subtitle: "Nail Art Design (Per Nail)",
            description: "Creative and customizable designs for stylish, unique nails.",
            image: "../images/42.png",
            prices: [
                { name: "Chrome", price: "RM10", duration: 10 },
                { name: "Cat Eyes", price: "RM7", duration: 10 },
                { name: "French Design", price: "RM10", duration: 15 },
                { name: "3D Nail Art", price: "RM15", duration: 20 },
                { name: "Matte Top Coat", price: "RM10", duration: 10 },
            ]
        },
        {
            subtitle: "Nail Extensions",
            description: "Enhance nail length and shape with professional extensions.",
            image: "../images/41.png",
            prices: [
                { name: "Acrylic Extensions", price: "RM150", duration: 120 },
                { name: "Tip Extensions", price: "RM100", duration: 90 },
                { name: "Infill Extension", price: "RM60", duration: 60 },
                { name: "Extension Remove", price: "RM50", duration: 30 },
            ]
        },  

        {
            subtitle: "Add-ons",
            description: "Optional extra services or hand treatments that enhance the look or comfort of the manicure.",
            image: "../images/45.png",
            prices: [
                { name: "Hand Scrub + Massage", price: "RM50", duration: 20 },
                { name: "Cuticle Oil Massage", price: "RM20", duration: 10 },
            ]
        },
    ]
},

    massage: {
    title: "Body Massage",
    description: "Relax and unwind with our therapeutic massage services, designed to ease tension and restore relaxation.",
    image: "../images/5.png",
    prices: [
        { name: "Body Massage (60 mins)", price: "RM80", duration: 60 },
        { name: "Body Massage (90 mins)", price: "RM120", duration: 90 },
        { name: "Body Massage (120 mins)", price: "RM150", duration: 120 },
    ],
    subsections: [
        {
            subtitle: "Swedish Massage",
            description: "A gentle, relaxing full-body massage using long, smooth strokes. Perfect for stress relief.",
            image: "../images/36.png",
            prices: [
                { name: "Swedish Massage (60 min)", price: "RM90", duration: 60 },
                { name: "Swedish Massage (120 min)", price: "RM120", duration: 120 },
            ]
        },
        {
            subtitle: "Traditional Massage",
            description: "Targets deeper muscles to relieve stiffness, tension, and chronic body aches.",
            image: "../images/38.png",
            prices: [
                { name: "Borneo Massage (60 min)", price: "RM90", duration: 60 },
                { name: "Borneo Massage (120 min)", price: "RM180", duration: 120 },
            ]
        },
        {
            subtitle: "Aromatherapy Massage",
            description: "Relaxing massage combined with essential oils like lavender, eucalyptus, or citrus.",
            image: "../images/37.png",
            prices: [
                { name: "Aromatherapy Massage (60 mins)", price: "RM120", duration: 60 },
                { name: "Aromatherapy Massage (120 mins)", price: "RM150", duration: 120 },
            ]
        },
        {
            subtitle: "Hot Stone Massage",
            description: "Heated stones placed on key points help melt muscle tension and improve blood flow.",
            image: "../images/39.png",
            prices: [
                { name: "Hot Stone Massage (90 mins)", price: "RM228", duration: 90 },
                { name: "Hot Stone Massage (120 mins)", price: "RM278", duration: 120 },
            ]
        },
    ]
},
};


// ======================
// LOAD CATEGORY CONTENT
// ======================
function selectCategory(category) {
    const service = serviceData[category];
    const container = document.getElementById("serviceDetails");

    // Main Section Box
    let html = `
        <div class="service-section-box">
            <div class="service-image-box">
                <img src="${service.image}" class="service-square-img">
            </div>
            <div class="service-text-box">
                <h2>${service.title}</h2>
                <p>${service.description}</p>
                <div class="service-prices">
                 <h3 class="price-list-header">Price List</h3>
                 ${service.prices.map(item => `<p>${item.name}: ${item.price}</p>`).join("")}
                </div>
            </div>
        </div>
    `;

    // Generate Subsections
    service.subsections.forEach(sub => {
        html += `
            <div class="service-section-box">
                <div class="service-image-box">
                    <img src="${sub.image}" class="service-square-img">
                </div>
                <div class="service-text-box">
                    <h2>${sub.subtitle}</h2>
                    <p>${sub.description}</p>
                    <div class="service-prices">
                    <h3 class="price-list-header">Price List</h3>
                    ${sub.prices.map(item => `<p>${item.name}: ${item.price}</p>`).join("")}
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;

    // Highlight active category
    document.querySelectorAll(".service-cat").forEach(btn => btn.classList.remove("active"));
    document.querySelector(`[onclick="selectCategory('${category}')"]`).classList.add("active");
}

// Load default category
const params = new URLSearchParams(window.location.search);
selectCategory(params.get("category") || "haircut");