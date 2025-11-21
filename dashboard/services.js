// Service data
const serviceData = {
    haircut: {
        title: "Haircut",
        description: "Maintain a polished and sophisticated look by getting your regular quality haircut service from our salon or let our skilled hair stylists create the hairstyle that complements your personality.",
        image: "../images/haircut.jpg",
        prices: [
            { name: "Basic Haircut", price: "RM25" },
            { name: "Wash + Cut + Blow", price: "RM45" },
            { name: "Hair Treatment", price: "RM60" }
        ]
    },
    facial: {
        title: "Facial",
        description: "Pamper your skin with our range of facials designed to refresh, rejuvenate, and enhance your natural glow.",
        image: "../images/facial.jpg",
        prices: [
            { name: "Classic Facial", price: "RM70" },
            { name: "Deep Cleansing Facial", price: "RM120" }
        ]
    },
    manicure: {
        title: "Manicure",
        description: "Indulge in a luxurious manicure to keep your nails looking fresh and polished.",
        image: "../images/manicure.jpg",
        prices: [
            { name: "Basic Manicure", price: "RM30" },
            { name: "Gel Manicure", price: "RM55" }
        ]
    },
    massage: {
        title: "Massage",
        description: "Relax and unwind with our therapeutic massage services, designed to ease tension and restore relaxation.",
        image: "../images/massage.jpg",
        prices: [
            { name: "Aroma Massage", price: "RM90" },
            { name: "Full Body Massage", price: "RM120" }
        ]
    }
};

// Function to load the service details dynamically
function selectCategory(category) {
    const service = serviceData[category];
    const serviceDetails = document.getElementById('serviceDetails');
    
    serviceDetails.innerHTML = `
        <div class="service-detail">
            <img src="${service.image}" alt="${service.title}">
            <h2>${service.title}</h2>
            <p>${service.description}</p>
            <div class="service-prices">
                ${service.prices.map(item => `<p>${item.name}: ${item.price}</p>`).join('')}
            </div>
        </div>
    `;
    
    // Highlight active category button
    document.querySelectorAll(".service-cat").forEach(btn => btn.classList.remove("active"));
    document.querySelector(`[onclick="selectCategory('${category}')"]`).classList.add("active");
}

// Load default category if any (from URL)
const params = new URLSearchParams(window.location.search);
const category = params.get("category") || "haircut";
selectCategory(category);
