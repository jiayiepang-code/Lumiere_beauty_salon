document.addEventListener("DOMContentLoaded", function () {

    /* ===============================
       PROFILE PANEL
    =============================== */
    const profileToggle = document.getElementById("profileToggle");
    const profilePanel = document.getElementById("profilePanel");
    const panelClose = document.getElementById("panelClose");

    if (profileToggle && profilePanel) {
        profileToggle.addEventListener("click", (e) => {
            e.stopPropagation();
            profilePanel.classList.add("open");
        });
    }

    if (panelClose) {
        panelClose.addEventListener("click", () => {
            profilePanel.classList.remove("open");
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
    const profileToggle = document.getElementById("profileToggle");
    const profilePanel  = document.getElementById("profilePanel");
    const panelClose    = document.getElementById("panelClose");

    if (profileToggle && profilePanel) {
        profileToggle.addEventListener("click", (e) => {
            e.stopPropagation();
            profilePanel.classList.add("open");
        });
    }

    if (panelClose && profilePanel) {
        panelClose.addEventListener("click", () => {
            profilePanel.classList.remove("open");
        });
    }

    document.addEventListener("click", (e) => {
        if (!profilePanel || !profileToggle) return;

        if (
            profilePanel.classList.contains("open") &&
            !profilePanel.contains(e.target) &&
            !profileToggle.contains(e.target)
        ) {
            profilePanel.classList.remove("open");
        }
    });

    // … rest of your JS (hero scroll, slider, etc.)
});


    /* ===============================
       HERO BUTTON SCROLL
    =============================== */
    const exploreBtn = document.getElementById("exploreBtn");
    const servicesSection = document.getElementById("services");

    if (exploreBtn && servicesSection) {
        exploreBtn.addEventListener("click", () => {
            servicesSection.scrollIntoView({ behavior: "smooth" });
        });
    }

    /* ===============================
       FACILITY SLIDER (FIXED)
    =============================== */
    let facSlides = document.querySelectorAll(".facility-slide");
    let facIndex = 0;

    function showFacSlide(newIndex) {
        facSlides[facIndex].classList.remove("active");
        facIndex = newIndex;
        facSlides[facIndex].classList.add("active");
    }

    const facNext = document.getElementById("facNext");
    const facPrev = document.getElementById("facPrev");

    if (facNext) {
        facNext.addEventListener("click", () => {
            showFacSlide((facIndex + 1) % facSlides.length);
        });
    }

    if (facPrev) {
        facPrev.addEventListener("click", () => {
            showFacSlide((facIndex - 1 + facSlides.length) % facSlides.length);
        });
    }

    // Autoplay every 5 seconds
    setInterval(() => {
        showFacSlide((facIndex + 1) % facSlides.length);
    }, 5000);

    /* ===============================
       SCROLL REVEAL EFFECT
    =============================== */
    const revealElements = document.querySelectorAll(".reveal, .reveal-left, .reveal-right");

    function revealOnScroll() {
        let windowHeight = window.innerHeight;

        revealElements.forEach(el => {
            let elementTop = el.getBoundingClientRect().top;

            if (elementTop < windowHeight - 100) {
                el.classList.add("active");
            }
        });
    }

    window.addEventListener("scroll", revealOnScroll);
    window.addEventListener("load", revealOnScroll);

    /* ===============================
       STAGGER EFFECT
    =============================== */
    const staggerElements = document.querySelectorAll(".stagger");

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("show");
            }
        });
    }, { threshold: 0.3 });

    staggerElements.forEach(el => observer.observe(el));

    /* ===============================
       HERO TYPING EFFECT
    =============================== */
    const text = "Where your beauty shines.";
    let i = 0;

    function typeEffect() {
        if (i < text.length) {
            const typingText = document.getElementById("typingText");
            if (typingText) typingText.innerHTML += text.charAt(i);
            i++;
            setTimeout(typeEffect, 80);
        }
    }

    window.addEventListener("load", typeEffect);

});
