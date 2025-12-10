const selectedServices = [
    { name: "Women Haircut", duration: 45 },
    { name: "Deep Cleansing Facial", duration: 75 }
];

const totalDuration = selectedServices.reduce((sum, s) => sum + s.duration, 0); // 120 mins
document.getElementById("totalDurationText").textContent = totalDuration + " mins";

const timeSlotList = document.getElementById("timeSlotList");
const chosenTimeText = document.getElementById("chosenTimeText");
const endTimeText = document.getElementById("endTimeText");

const SALON_OPEN_HOUR = 10; // 10:00
const SALON_CLOSE_HOUR = 21; // 21:00 (9pm)
const SLOT_INTERVAL = 30; // 30 mins

// Generate time slots between 10:00 and 21:00
function generateTimeSlots() {
    timeSlotList.innerHTML = "";
    const slots = [];
    for (let hour = SALON_OPEN_HOUR; hour < SALON_CLOSE_HOUR; hour++) {
        for (let min of [0, 30]) {
            slots.push({ hour, min });
        }
    }

    slots.forEach(slot => {
        const div = document.createElement("div");
        div.className = "time-slot";
        const label = formatTime(slot.hour, slot.min);
        div.textContent = label;
        div.dataset.hour = slot.hour;
        div.dataset.min = slot.min;
        div.addEventListener("click", () => onSelectTime(slot.hour, slot.min));
        timeSlotList.appendChild(div);
    });
}

function formatTime(hour, min) {
    const ampm = hour >= 12 ? "PM" : "AM";
    const displayHour = hour % 12 === 0 ? 12 : hour % 12;
    const displayMin = min.toString().padStart(2, "0");
    return `${displayHour}:${displayMin} ${ampm}`;
}

function onSelectTime(startHour, startMin) {
    // Clear previous selected + blocked
    document.querySelectorAll(".time-slot").forEach(s => {
        s.classList.remove("selected");
        s.classList.remove("blocked");
    });

    // Mark selected
    const allSlots = Array.from(document.querySelectorAll(".time-slot"));
    let selectedIndex = -1;
    allSlots.forEach((el, idx) => {
        if (parseInt(el.dataset.hour) === startHour && parseInt(el.dataset.min) === startMin) {
            selectedIndex = idx;
            el.classList.add("selected");
        }
    });

    // Compute end time based on totalDuration (in mins)
    const startTotalMinutes = startHour * 60 + startMin;
    const endTotalMinutes = startTotalMinutes + totalDuration;

    const endHour = Math.floor(endTotalMinutes / 60);
    const endMin = endTotalMinutes % 60;

    chosenTimeText.textContent = formatTime(startHour, startMin);
    endTimeText.textContent = "Estimated end time: " + formatTime(endHour, endMin);

    // Block time slots that are inside this booking window
    allSlots.forEach((el, idx) => {
        const h = parseInt(el.dataset.hour);
        const m = parseInt(el.dataset.min);
        const t = h * 60 + m;
        if (idx !== selectedIndex && t > startTotalMinutes && t < endTotalMinutes) {
            el.classList.add("blocked");
        }
    });
}

// Date selection
const dateBoxes = document.querySelectorAll(".date-box");
dateBoxes.forEach(box => {
    box.addEventListener("click", () => {
        dateBoxes.forEach(b => b.classList.remove("selected"));
        box.classList.add("selected");
        // Optional: reset selected time when date changes
        chosenTimeText.textContent = "–";
        endTimeText.textContent = "Estimated end time: –";
        generateTimeSlots();
    });
});

// init
generateTimeSlots();