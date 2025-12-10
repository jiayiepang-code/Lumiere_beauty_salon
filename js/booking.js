let currentStep = 1;
let selectedServices = [];
let selectedStaff = {};
let selectedDate = '';
let selectedTime = '';
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

// --- CONFIGURATION ---
const SHOP_OPEN_HOUR = 10; 
const SHOP_CLOSE_HOUR = 22; 

// --- SMART STAFF FILTER KEYWORDS (FIXED) ---
const roleKeywords = {
    // 1. Hair Stylist
    'hair_stylist': ['hair', 'cut', 'styling', 'wash', 'blow', 'colouring', 'color', 'root', 'rebonding', 'perm', 'keratin', 'scalp', 'treatment'],
    
    // 2. Beautician
    'beautician': ['facial', 'face', 'anti-aging', 'cleansing', 'hydrating', 'brightening', 'acne', 'glow'],
    
    // 3. Nail Technician (REMOVED 'colour' to prevent conflict with Hair Colouring)
    'nail_technician': ['nail', 'manicure', 'pedicure', 'polish', 'gel', 'chrome', 'cat eyes', 'french', '3d', 'matte', 'acrylic', 'extension', 'tip', 'infill', 'removal', 'scrub', 'cuticle'],
    
    // 4. Massage Therapist
    'massage_therapist': ['massage', 'spa', 'swedish', 'borneo', 'traditional', 'aromatherapy', 'hot stone', 'reflexology', 'body']
};

$(document).ready(function() {
    updateStepIndicator();
    renderCalendar(); 
    
    // CLICK HANDLER
    $('.service-card').click(function(e) {
        if ($(e.target).is('select') || $(e.target).is('option')) return;

        let serviceId;
        const selectBox = $(this).find('.service-variant-select');
        
        if (selectBox.length > 0) {
            serviceId = selectBox.val();
            selectBox.find('option').each(function() {
                const optId = $(this).val();
                if (selectedServices.includes(optId) && optId != serviceId) {
                    removeService(optId);
                }
            });
        } else {
            serviceId = $(this).data('service-id');
        }

        if($(this).hasClass('selected')) {
            removeService(serviceId); 
        } else {
            $(this).addClass('selected');
            selectedServices.push(serviceId);
            selectedStaff[serviceId] = 0; 
            updateSummary();
            if(selectedDate) loadTimeSlots(); 
        }
    });
    
    $('#booking-date').change(function() {
        selectedDate = $(this).val();
        currentMonth = new Date(selectedDate).getMonth();
        currentYear = new Date(selectedDate).getFullYear();
        renderCalendar();
        loadTimeSlots();
        updateSummary();
    });
    
    $('#prev-month').click(() => changeMonth(-1));
    $('#next-month').click(() => changeMonth(1));
    $('#next-btn').click(() => { if(validateCurrentStep()) nextStep(); });
    $('#prev-btn').click(prevStep);
});

// --- SMART STAFF FILTER (FIXED FOR HAND SCRUB) ---
function getRolesForService(categoryName, serviceName) {
    const cat = (categoryName || '').toLowerCase();
    const svc = (serviceName || '').toLowerCase();
    const combined = cat + ' ' + svc;

    // *** FIX: FORCE HAND SCRUB TO NAIL TECH ***
    if (svc.includes('hand scrub')) {
        return ['nail_technician'];
    }

    let allowedRoles = [];
    for (const [role, keywords] of Object.entries(roleKeywords)) {
        if (keywords.some(k => combined.includes(k))) allowedRoles.push(role);
    }
    return allowedRoles.length === 0 ? Object.keys(roleKeywords) : allowedRoles;
}

function updateCardPrice(selectElement) {
    const option = $(selectElement).find(':selected');
    const price = option.data('price');
    const card = $(selectElement).closest('.card');
    card.find('.price-badge').text('RM ' + parseInt(price));
    if (card.hasClass('selected')) {
        card.click(); card.click(); 
    }
}

function removeService(serviceId) {
    selectedServices = selectedServices.filter(id => id != serviceId);
    delete selectedStaff[serviceId];
    
    $('.service-card').each(function() {
        if($(this).data('service-id') == serviceId) $(this).removeClass('selected');
        const select = $(this).find('select');
        if(select.length > 0 && select.val() == serviceId) $(this).closest('.card').removeClass('selected');
    });

    loadStaffSelection(); 
    updateStaffHeader();
    if(selectedDate) loadTimeSlots(); 
    updateSummary();      
}

function toggleEditMode(serviceId) {
    const viewMode = $(`#view-mode-${serviceId}`);
    const editMode = $(`#edit-mode-${serviceId}`);
    if (viewMode.hasClass('d-none')) {
        viewMode.removeClass('d-none'); editMode.addClass('d-none');
    } else {
        viewMode.addClass('d-none'); editMode.removeClass('d-none').addClass('d-flex');
    }
}

// --- SUMMARY WITH PERFECT ALIGNMENT ---
function updateSummary() {
    const container = $('#summary-content');
    const sData = window.allServicesData;
    
    if(selectedServices.length === 0) { container.html('<p class="text-muted">Select services to see summary</p>'); return; }
    
    let html = '';
    
    // Date Header
    if(selectedDate) {
        const dStr = new Date(selectedDate).toLocaleDateString('en-US', {weekday:'long', day:'numeric', month:'long'});
        html += `<div class="mb-3 p-3 bg-light rounded border" style="border-left: 4px solid var(--accent-gold);"><div class="fw-bold text-dark"><i class="fas fa-calendar-alt me-2"></i>${dStr}</div>`;
        if(selectedTime) {
            let totDur = 0; selectedServices.forEach(id => { if(sData[id]) totDur += parseInt(sData[id].duration); });
            const end = calculateEndTime(selectedTime, totDur);
            html += `<div class="text-primary mt-1"><i class="fas fa-clock me-2"></i>${selectedTime} - ${end} <span class="text-muted small">(${totDur} mins)</span></div>`;
        } else { html += `<div class="text-muted small mt-1">Select a time</div>`; }
        html += `</div>`;
    }

    html += '<div class="summary-list mb-3">';
    let currentStart = selectedTime; 
    let totalP = 0;
    
    selectedServices.forEach((sid, idx) => {
        const s = sData[sid];
        const dur = parseInt(s.duration);
        let timeDisplay = `${dur} min`;
        
        if(currentStart) {
            let end = calculateEndTime(currentStart, dur);
            timeDisplay = `<span class="badge bg-white text-dark border">${currentStart} - ${end}</span>`;
            currentStart = end; 
        }

        const stid = selectedStaff[sid] || 0;
        let sName = '<span class="text-muted small">No Preference</span>';
        if(stid > 0 && window.staffData) {
            const all = Object.values(window.staffData).flat();
            const found = all.find(st => st.staff_id == stid);
            if(found) sName = `<span class="text-primary small"><i class="fas fa-user-circle me-1"></i>${found.first_name}</span>`;
        }

        // GRID LAYOUT FOR ALIGNMENT
        html += `
            <div class="d-flex flex-column mb-2 border-bottom pb-2">
                <div id="view-mode-${sid}" class="w-100">
                    <div class="row g-0">
                        <div class="col-8">
                            <div class="d-flex align-items-center mb-1">
                                <span class="fw-bold text-dark me-2">${idx+1}. ${s.name}</span>
                                <a href="javascript:void(0)" onclick="toggleEditMode(${sid})" class="text-secondary" title="Edit"><i class="fas fa-pen small" style="font-size: 0.8em;"></i></a>
                            </div>
                            <div class="mb-1">${sName}</div>
                            <div>${timeDisplay}</div>
                        </div>
                        <div class="col-4 text-end">
                            <span class="fw-bold text-dark">RM ${parseFloat(s.price).toFixed(2)}</span>
                        </div>
                    </div>
                </div>

                <div id="edit-mode-${sid}" class="d-none align-items-center justify-content-between w-100 bg-light p-2 rounded mt-1">
                    <div class="d-flex gap-3">
                        <a href="javascript:void(0)" onclick="removeService(${sid})" class="text-danger text-decoration-none fw-bold small"><i class="fas fa-trash-alt me-1"></i> Delete</a>
                        <a href="javascript:void(0)" onclick="openStaffModal(${sid})" class="text-primary text-decoration-none fw-bold small"><i class="fas fa-user-edit me-1"></i> Staff</a>
                    </div>
                    <a href="javascript:void(0)" onclick="toggleEditMode(${sid})" class="text-success" title="Done"><i class="fas fa-check-circle"></i></a>
                </div>
            </div>`;
        totalP += parseFloat(s.price);
    });
    html += '</div>';

    const sst = totalP * 0.06;
    html += `
        <div class="mt-auto">
            <div class="d-flex justify-content-between mb-1"><span>Subtotal:</span><span>RM ${totalP.toFixed(2)}</span></div>
            <div class="d-flex justify-content-between mb-2"><span>SST (6%):</span><span>RM ${sst.toFixed(2)}</span></div>
            <div class="d-flex justify-content-between border-top pt-3"><span class="h5">Total</span><span class="h5" style="color: var(--accent-gold);">RM ${(totalP+sst).toFixed(2)}</span></div>
        </div>`;
    
    container.html(html);
}

function calculateEndTime(start, mins) {
    if(!start) return '';
    let [h, m] = start.split(':').map(Number);
    let date = new Date(); date.setHours(h); date.setMinutes(m + mins);
    return `${String(date.getHours()).padStart(2,'0')}:${String(date.getMinutes()).padStart(2,'0')}`;
}

// CALENDAR & TIME
function renderCalendar() {
    const grid = $('#calendar-grid');
    $('#current-month-year').text(new Date(currentYear, currentMonth).toLocaleDateString('en-US', {month:'long', year:'numeric'}));
    grid.empty();
    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const today = new Date(); today.setHours(0,0,0,0);
    for(let i=0; i<firstDay; i++) grid.append(`<div></div>`);
    for(let i=1; i<=daysInMonth; i++) {
        const dateStr = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
        const checkDate = new Date(dateStr); checkDate.setHours(0,0,0,0);
        let classes = 'calendar-date';
        if(checkDate < today) classes += ' disabled';
        if(dateStr === selectedDate) classes += ' selected';
        const dayEl = $(`<div class="${classes}">${i}</div>`);
        if(!classes.includes('disabled')) {
            dayEl.click(function() {
                $('.calendar-date').removeClass('selected'); $(this).addClass('selected');
                selectedDate = dateStr; $('#booking-date').val(selectedDate);
                loadTimeSlots(); updateSummary();
            });
        }
        grid.append(dayEl);
    }
}
function changeMonth(dir) {
    currentMonth += dir;
    if(currentMonth < 0) { currentMonth = 11; currentYear--; }
    if(currentMonth > 11) { currentMonth = 0; currentYear++; }
    renderCalendar();
}

function loadTimeSlots() {
    const container = $('#time-slots');
    container.html('<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></div>');
    let totalDuration = 0;
    const serviceData = window.allServicesData || {};
    selectedServices.forEach(id => { if(serviceData[id]) totalDuration += parseInt(serviceData[id].duration); });
    $('#total-duration-display').text(totalDuration);

    setTimeout(() => {
        container.empty(); $('#estimated-time-container').remove(); let hasDisabled = false;
        for(let h = SHOP_OPEN_HOUR; h < SHOP_CLOSE_HOUR; h++) {
            for(let m = 0; m < 60; m += 30) {
                const timeStr = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
                let startMins = h*60 + m; let endMins = startMins + totalDuration;
                const now = new Date(); const slotDate = new Date(selectedDate + 'T' + timeStr);
                let isPast = (new Date(selectedDate).setHours(0,0,0,0) === now.setHours(0,0,0,0)) && slotDate < now;
                let exceeds = endMins > (SHOP_CLOSE_HOUR * 60);
                let classes = (isPast || exceeds) ? 'time-slot disabled' : 'time-slot';
                if(exceeds) hasDisabled = true;
                container.append(`<div class="${classes}" data-time="${timeStr}" data-minutes="${startMins}">${timeStr}</div>`);
            }
        }
        container.after('<div id="estimated-time-container"></div>');
        $('#closing-warning').toggle(hasDisabled);
        $('.time-slot:not(.disabled)').click(function() {
            $('.time-slot').removeClass('selected duration-occupied'); $(this).addClass('selected');
            selectedTime = $(this).attr('data-time');
            const start = parseInt($(this).attr('data-minutes')); const end = start + totalDuration;
            $('.time-slot').each(function() {
                const curr = parseInt($(this).attr('data-minutes'));
                if(curr > start && curr < end) $(this).addClass('duration-occupied');
            });
            updateEstimatedTimeDisplay(selectedTime, totalDuration); updateSummary();
        });
    }, 300);
}

function updateEstimatedTimeDisplay(start, duration) {
    if(!start) return;
    const end = calculateEndTime(start, duration);
    $('#estimated-time-container').html(`<div class="estimated-time-message"><i class="fas fa-clock me-2"></i><strong>${start} - ${end}</strong><br><small class="text-muted">Total: ${duration} mins</small></div>`);
}

// STAFF SELECTION
function loadStaffSelection() {
    const container = $('#staff-selection'); container.empty();
    const sData = window.allServicesData || {}; const staffData = window.staffData || {};
    selectedServices.forEach(sid => {
        const service = sData[sid]; if(!service) return;
        const curStaff = selectedStaff[sid] || 0;
        let sName = "No Preference"; let btnText = "Select Staff"; let style = "border: 2px solid var(--secondary-beige);";
        if(curStaff != 0) {
            btnText = "Change"; style = "border: 2px solid var(--accent-gold); background: rgba(212, 175, 55, 0.05);";
            const all = Object.values(staffData).flat();
            const found = all.find(s => s.staff_id == curStaff);
            if(found) sName = found.first_name + " " + found.last_name;
        }
        container.append(`<div class="col-md-6 mb-3"><div class="card h-100 shadow-sm" style="cursor: pointer; ${style}" onclick="openStaffModal(${sid})"><div class="card-body d-flex justify-content-between align-items-center p-4"><div><h6 class="text-muted mb-1">${service.name}</h6><h5 class="mb-0 fw-bold" style="color: var(--warm-brown);"><i class="fas fa-user-check me-2"></i>${sName}</h5></div><div><span class="badge rounded-pill bg-light text-dark border">${btnText}</span></div></div></div></div>`);
    });
}

function openStaffModal(sid) {
    const sData = window.allServicesData; const staffData = window.staffData;
    $('#modal-service-name').text(sData[sid].name);
    const list = $('#modal-staff-list'); list.empty();
    const isNo = (selectedStaff[sid] == 0);
    list.append(`<div class="d-flex justify-content-between p-3 border rounded mb-2 bg-light" onclick="selectStaff(${sid}, 0)" style="cursor: pointer; ${isNo ? 'border-color: var(--accent-gold) !important;' : ''}"><span class="fw-bold">No Preference</span><button class="btn btn-sm ${isNo ? 'btn-warning' : 'btn-outline-secondary'} rounded-pill">${isNo ? 'Selected' : 'Select'}</button></div>`);
    const allowedRoles = getRolesForService(sData[sid].category, sData[sid].name);
    if(staffData) {
        Object.keys(staffData).forEach(dbRole => {
            if (allowedRoles.includes(dbRole)) {
                staffData[dbRole].forEach(member => {
                    const isSel = (selectedStaff[sid] == member.staff_id);
                    list.append(`<div class="d-flex justify-content-between p-3 border rounded mb-2 bg-white" onclick="selectStaff(${sid}, ${member.staff_id})" style="cursor: pointer; ${isSel ? 'border-color: var(--accent-gold) !important;' : ''}"><div><div class="fw-bold">${member.first_name} ${member.last_name}</div><small class="text-muted">${dbRole.replace('_',' ')}</small></div><button class="btn btn-sm ${isSel ? 'btn-warning' : 'btn-outline-secondary'} rounded-pill">${isSel ? 'Selected' : 'Select'}</button></div>`);
                });
            }
        });
    }
    new bootstrap.Modal(document.getElementById('staffModal')).show();
}

function selectStaff(sid, stid) {
    selectedStaff[sid] = stid; loadStaffSelection(); updateSummary();
    bootstrap.Modal.getInstance(document.getElementById('staffModal')).hide();
}

function updateStaffHeader() {
    const disp = $('#staff-display-name'); const icon = $('#staff-icon-container');
    const uStaff = [];
    Object.values(selectedStaff).forEach(id => {
        if(id!=0) {
            const all = Object.values(window.staffData).flat();
            const f = all.find(s => s.staff_id == id);
            if(f && !uStaff.includes(f.first_name)) uStaff.push(f.first_name);
        }
    });
    if(uStaff.length===0) { disp.text("No Preference / Any Staff"); icon.html('<i class="fas fa-users text-muted"></i>'); }
    else if(uStaff.length===1) { disp.text(uStaff[0]); icon.html('<i class="fas fa-user text-primary"></i>'); }
    else { disp.text(`Multiple Staff (${uStaff.join(", ")})`); icon.html('<div class="d-flex"><i class="fas fa-user text-primary"></i><i class="fas fa-user text-success ms-n1"></i></div>'); }
}

function validateCurrentStep() {
    if(currentStep===1 && selectedServices.length===0) { alert('Select a service'); return false; }
    if(currentStep===3 && (!selectedDate || !selectedTime)) { alert('Select date & time'); return false; }
    if(currentStep===4 && !$('#payment-method').val()) { alert('Select payment'); return false; }
    return true;
}
function nextStep() {
    if(currentStep<4) {
        $(`#step-${currentStep}`).hide(); currentStep++; $(`#step-${currentStep}`).show();
        if(currentStep===2) loadStaffSelection();
        if(currentStep===3) { updateStaffHeader(); renderCalendar(); }
        updateStepIndicator(); updateNavigationButtons();
    } else submitBooking();
}
function prevStep() { if(currentStep>1) { $(`#step-${currentStep}`).hide(); currentStep--; $(`#step-${currentStep}`).show(); updateStepIndicator(); updateNavigationButtons(); } }
function updateStepIndicator() { $('.step').removeClass('active completed'); for(let i=1; i<=4; i++) { if(i<currentStep) $(`#step-${i}-indicator`).addClass('completed'); else if(i===currentStep) $(`#step-${i}-indicator`).addClass('active'); } }
function updateNavigationButtons() { $('#prev-btn').toggle(currentStep>1); $('#next-btn').html(currentStep===4 ? 'Confirm' : 'Next'); }
function submitBooking() {
    const data = { services: selectedServices, staff: selectedStaff, date: selectedDate, time: selectedTime, specialRequests: $('#special-requests').val(), paymentMethod: $('#payment-method').val() };
    $.ajax({ url: 'process_booking.php', method: 'POST', data: JSON.stringify(data), contentType: 'application/json', success: res => { if(res.success) window.location.href='booking_confirmation.php?reference='+res.reference_id; else alert(res.message); }, error: () => alert('Error') });
}