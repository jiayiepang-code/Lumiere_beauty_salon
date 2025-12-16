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
    // Ensure step 1 is visible and active
    $('.booking-step').removeClass('show-step');
    $('#step-1').addClass('show-step');
    currentStep = 1;
    updateStepIndicator();
    updateNavigationButtons();
    renderCalendar();
    
    // Add event delegation for edit pencil icons
    $(document).on('click', '.edit-service-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const serviceId = $(this).data('service-id') || $(this).attr('data-service-id');
        if(serviceId) {
            toggleEditMode(String(serviceId));
        }
    });
    
    // Add event delegation for edit action buttons (Delete, Staff, Close)
    $(document).on('click', '.edit-action-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const action = $(this).data('action') || $(this).attr('data-action');
        const serviceId = $(this).data('service-id') || $(this).attr('data-service-id');
        
        if (!serviceId) {
            console.error('Service ID not found for edit action:', action);
            return;
        }
        
        const serviceIdStr = String(serviceId);
        
        if (action === 'delete') {
            removeService(serviceIdStr);
        } else if (action === 'staff') {
            toggleEditMode(serviceIdStr);
            // Small delay to ensure edit mode closes before modal opens
            setTimeout(function() {
                openStaffModal(serviceIdStr);
            }, 100);
        } else if (action === 'close') {
            toggleEditMode(serviceIdStr);
        }
    }); 
    
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

        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:93',message:'Service selection clicked',data:{serviceId:serviceId,serviceIdType:typeof serviceId,dataAttr:$(this).data('service-id'),isSelected:$(this).hasClass('selected')},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'B'})}).catch(()=>{});
        // #endregion
        
        if($(this).hasClass('selected')) {
            removeService(serviceId); 
        } else {
            $(this).addClass('selected');
            selectedServices.push(serviceId);
            selectedStaff[serviceId] = 0;
            
            // #region agent log
            fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:105',message:'Service added to selection',data:{serviceId:serviceId,selectedServicesLength:selectedServices.length,lastAdded:selectedServices[selectedServices.length-1],allSelected:selectedServices},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'B'})}).catch(()=>{});
            // #endregion 
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

// --- SMART STAFF FILTER (CATEGORY-BASED) ---
function getRolesForService(categoryName, serviceName) {
    const cat = (categoryName || '').toLowerCase();
    const svc = (serviceName || '').toLowerCase();
    const combined = cat + ' ' + svc;

    // *** FIX: FORCE HAND SCRUB TO NAIL TECH ***
    if (svc.includes('hand scrub')) {
        return ['nail_technician'];
    }

    // Category-based mapping (strict matching)
    const categoryRoleMap = {
        'facial': ['beautician'],
        'massage': ['massage_therapist'],
        'haircut': ['hair_stylist'],
        'hair': ['hair_stylist'],
        'manicure': ['nail_technician'],
        'nail': ['nail_technician']
    };

    // First, check category mapping (strict)
    if (categoryRoleMap[cat]) {
        return categoryRoleMap[cat];
    }

    // Fallback to keyword matching if category doesn't match
    let allowedRoles = [];
    for (const [role, keywords] of Object.entries(roleKeywords)) {
        if (keywords.some(k => combined.includes(k))) allowedRoles.push(role);
    }
    
    // Only return matched roles, don't show all if no match
    return allowedRoles;
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
    // Close edit mode if open
    const editMode = $(`#edit-mode-${serviceId}`);
    const viewMode = $(`#view-mode-${serviceId}`);
    if(editMode.length > 0 && !editMode.hasClass('d-none')) {
        editMode.addClass('d-none').removeClass('d-flex');
        if(viewMode.length > 0) {
            viewMode.removeClass('d-none');
        }
    }
    
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
    const serviceIdStr = String(serviceId);
    const viewMode = $(`#view-mode-${serviceIdStr}`);
    const editMode = $(`#edit-mode-${serviceIdStr}`);
    
    if (viewMode.length === 0 || editMode.length === 0) {
        console.error('Edit mode elements not found for service:', serviceIdStr);
        console.log('View mode element:', viewMode.length, 'Edit mode element:', editMode.length);
        return;
    }
    
    if (viewMode.hasClass('d-none')) {
        // Show view mode, hide edit mode
        viewMode.removeClass('d-none');
        editMode.addClass('d-none').removeClass('d-flex');
    } else {
        // Hide view mode, show edit mode
        viewMode.addClass('d-none');
        editMode.removeClass('d-none').addClass('d-flex');
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
            html += `<div class="mt-1" style="color: #c29076; font-family: 'Segoe UI', sans-serif;"><i class="fas fa-clock me-2"></i>${selectedTime} - ${end} <span class="text-muted small">(${totDur} mins)</span></div>`;
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
            timeDisplay = `<span class="badge bg-white text-dark border" style="font-family: 'Segoe UI', sans-serif;">${currentStart} - ${end}</span>`;
            currentStart = end; 
        }

        const stid = selectedStaff[sid] || 0;
        let sName = '<span class="text-muted small">No Preference</span>';
        if(stid != 0 && stid != '0' && window.staffData) {
            const all = Object.values(window.staffData).flat();
            // Match by both staff_id and staff_email (since staff_id is actually email)
            const found = all.find(st => {
                if(!st) return false;
                const staffIdStr = String(st.staff_id || '');
                const staffEmailStr = String(st.staff_email || '');
                const selectedIdStr = String(stid || '');
                return staffIdStr === selectedIdStr || 
                       staffEmailStr === selectedIdStr ||
                       staffIdStr.toLowerCase() === selectedIdStr.toLowerCase() ||
                       staffEmailStr.toLowerCase() === selectedIdStr.toLowerCase();
            });
            if(found) {
                const displayName = found.first_name + (found.last_name ? ' ' + found.last_name : '');
                sName = `<span class="small" style="color: #c29076;"><i class="fas fa-user-circle me-1"></i>${found.first_name}</span>`;
            }
        }

        // Format service display name: show "SubCategory (ServiceName)" if sub_category exists, otherwise just service name
        let serviceDisplayName = s.name;
        if (s.sub_category && s.sub_category.trim() !== '' && s.sub_category.toLowerCase() !== 'general') {
            serviceDisplayName = `${s.sub_category} (${s.name})`;
            
            // #region agent log
            fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:279',message:'Service display formatted with sub_category',data:{serviceId:sid,serviceName:s.name,subCategory:s.sub_category,displayName:serviceDisplayName},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix',hypothesisId:'display'})}).catch(()=>{});
            // #endregion
        } else {
            // #region agent log
            fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:285',message:'Service display using service name only',data:{serviceId:sid,serviceName:s.name,subCategory:s.sub_category || 'none',displayName:serviceDisplayName},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix',hypothesisId:'display'})}).catch(()=>{});
            // #endregion
        }
        
        // GRID LAYOUT FOR ALIGNMENT
        html += `
            <div class="d-flex flex-column mb-2 border-bottom pb-2">
                <div id="view-mode-${sid}" class="w-100">
                    <div class="row g-0">
                        <div class="col-8">
                            <div class="d-flex align-items-center mb-1">
                                <span class="fw-bold text-dark me-2"><span style="font-family: 'Segoe UI', sans-serif;">${idx+1}.</span> ${serviceDisplayName}</span>
                                <a href="javascript:void(0)" class="text-secondary edit-service-btn" data-service-id="${sid}" title="Edit service" style="cursor: pointer; padding: 4px 8px; border-radius: 4px; transition: all 0.2s;">
                                    <i class="fas fa-pen small" style="font-size: 0.8em;"></i>
                                </a>
                            </div>
                            <div class="mb-1">${sName}</div>
                            <div>${timeDisplay}</div>
                        </div>
                        <div class="col-4 text-end">
                            <span class="fw-bold text-dark" style="font-family: 'Segoe UI', sans-serif;">RM ${parseFloat(s.price).toFixed(2)}</span>
                        </div>
                    </div>
                </div>

                <div id="edit-mode-${sid}" class="d-none d-flex align-items-center justify-content-between w-100 bg-light p-2 rounded mt-1">
                    <div class="d-flex gap-3">
                        <a href="javascript:void(0)" class="text-danger text-decoration-none fw-bold small edit-action-btn" data-action="delete" data-service-id="${sid}" title="Delete this service" style="cursor: pointer;">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </a>
                        <a href="javascript:void(0)" class="text-decoration-none fw-bold small edit-action-btn" data-action="staff" data-service-id="${sid}" title="Change staff" style="cursor: pointer; color: #c29076;">
                            <i class="fas fa-user-edit me-1"></i> Staff
                        </a>
                    </div>
                    <a href="javascript:void(0)" class="text-success edit-action-btn" data-action="close" data-service-id="${sid}" title="Done editing" style="cursor: pointer;">
                        <i class="fas fa-check-circle"></i>
                    </a>
                </div>
            </div>`;
        totalP += parseFloat(s.price);
    });
    html += '</div>';

    html += `
        <div class="mt-auto">
            <div class="d-flex justify-content-between border-top pt-3"><span class="h5">Total</span><span class="h5" style="color: var(--accent-gold);">RM ${totalP.toFixed(2)}</span></div>
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
    
    // Calculate maximum booking date (30 days from today)
    const maxDate = new Date(today);
    maxDate.setDate(today.getDate() + 30);
    maxDate.setHours(23,59,59,999);
    
    for(let i=0; i<firstDay; i++) grid.append(`<div></div>`);
    for(let i=1; i<=daysInMonth; i++) {
        const dateStr = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
        const checkDate = new Date(dateStr); checkDate.setHours(0,0,0,0);
        let classes = 'calendar-date';
        if(checkDate < today) classes += ' disabled';
        if(checkDate > maxDate) classes += ' disabled'; // Disable dates beyond 30 days
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
    const today = new Date(); today.setHours(0,0,0,0);
    const maxDate = new Date(today);
    maxDate.setDate(today.getDate() + 30);
    
    currentMonth += dir;
    if(currentMonth < 0) { currentMonth = 11; currentYear--; }
    if(currentMonth > 11) { currentMonth = 0; currentYear++; }
    
    // Check if the month we're navigating to is beyond 30 days
    const targetMonthStart = new Date(currentYear, currentMonth, 1);
    if(targetMonthStart > maxDate) {
        // Revert the change
        currentMonth -= dir;
        if(currentMonth < 0) { currentMonth = 11; currentYear--; }
        if(currentMonth > 11) { currentMonth = 0; currentYear++; }
        return; // Don't render if we can't go that far
    }
    
    renderCalendar();
}

function loadTimeSlots() {
    const container = $('#time-slots');
    container.html('<div class="text-center py-3"><div class="spinner-border" role="status" style="color: #c29076;"></div></div>');
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
        if(curStaff != 0 && curStaff != '0') {
            btnText = "Change"; style = "border: 2px solid var(--accent-gold); background: rgba(212, 175, 55, 0.05);";
            const all = Object.values(staffData).flat();
            const found = all.find(s => {
                if(!s) return false;
                const staffIdStr = String(s.staff_id || '');
                const staffEmailStr = String(s.staff_email || '');
                const curStaffStr = String(curStaff || '');
                return staffIdStr === curStaffStr || 
                       staffEmailStr === curStaffStr ||
                       staffIdStr.toLowerCase() === curStaffStr.toLowerCase() ||
                       staffEmailStr.toLowerCase() === curStaffStr.toLowerCase();
            });
            if(found) sName = found.first_name + (found.last_name ? " " + found.last_name : "");
        }
        // Use data attribute and event delegation for better reliability
        const serviceId = typeof sid === 'string' ? `'${sid}'` : sid;
        container.append(`
            <div class="col-md-6 mb-3">
                <div class="card h-100 shadow-sm staff-selection-card" 
                     data-service-id="${sid}" 
                     style="cursor: pointer; ${style}">
                    <div class="card-body d-flex justify-content-between align-items-center" style="padding: 1rem 1.25rem; min-height: 100%; display: flex; align-items: center;">
                        <div style="flex: 1;">
                            <h6 class="text-muted mb-1" style="margin-bottom: 0.5rem;">${service.name}</h6>
                            <h5 class="mb-0 fw-bold" style="color: var(--warm-brown); margin-bottom: 0;">
                                <i class="fas fa-user-check me-2"></i>${sName}
                            </h5>
                        </div>
                        <div>
                            <span class="badge rounded-pill bg-light text-dark border">${btnText}</span>
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
    
    // Use event delegation for better reliability
    $(document).off('click', '.staff-selection-card').on('click', '.staff-selection-card', function(e) {
        e.stopPropagation();
        const serviceId = $(this).data('service-id');
        if(serviceId) {
            openStaffModal(serviceId);
        }
    });
}

function openStaffModal(sid) {
    // Ensure sid is treated as the correct type
    if(!sid) {
        console.error('openStaffModal: No service ID provided');
        return;
    }
    
    // Close any open edit mode when opening staff modal
    const editMode = $(`#edit-mode-${sid}`);
    const viewMode = $(`#view-mode-${sid}`);
    if(editMode.length > 0 && !editMode.hasClass('d-none')) {
        editMode.addClass('d-none').removeClass('d-flex');
        if(viewMode.length > 0) {
            viewMode.removeClass('d-none');
        }
    }
    
    const sData = window.allServicesData; 
    const staffData = window.staffData;
    const staffServiceMap = window.staffServiceMap || {};
    
    // Check if service exists
    if(!sData || !sData[sid]) {
        console.error('openStaffModal: Service not found for ID:', sid);
        alert('Service not found. Please refresh the page.');
        return;
    }
    
    const service = sData[sid];
    $('#modal-service-name').text(service.name);
    const list = $('#modal-staff-list'); 
    list.empty();
    
    // Convert service ID to string for matching (staffServiceMap uses string keys)
    const serviceIdStr = String(sid);
    
    // Get suggested staff emails for this service (check both string and number keys)
    const suggestedStaffEmails = staffServiceMap[serviceIdStr] || staffServiceMap[sid] || [];
    
    // Add "No Preference" option
    const isNo = (selectedStaff[sid] == 0);
    list.append(`<div class="d-flex justify-content-between p-3 border rounded mb-2 bg-light staff-option" data-service-id="${sid}" data-staff-id="0" style="cursor: pointer; ${isNo ? 'border-color: var(--accent-gold) !important;' : ''}"><span class="fw-bold">No Preference</span><button class="btn btn-sm ${isNo ? 'btn-warning' : 'btn-outline-secondary'} rounded-pill staff-select-btn" data-service-id="${sid}" data-staff-id="0" onclick="event.stopPropagation(); selectStaff('${sid}', 0);">${isNo ? 'Selected' : 'Select'}</button></div>`);
    
    // Get allowed roles for this service (for role-based matching)
    const allowedRoles = getRolesForService(service.category, service.name);
    
    // Service keywords for matching
    const serviceText = (service.category + ' ' + service.name).toLowerCase();
    const categoryLower = (service.category || '').toLowerCase();
    
    // Collect all matching staff with their suggested status
    const staffList = [];
    
    // Service category to role keyword mapping
    const categoryToRole = {
        'facial': 'beautician',
        'massage': 'massage_therapist',
        'haircut': 'hair_stylist',
        'hair': 'hair_stylist',
        'manicure': 'nail_technician',
        'nail': 'nail_technician'
    };
    
    // Determine expected role keyword from category
    const expectedRoleKeyword = categoryToRole[categoryLower] || allowedRoles[0];
    
    // Role keyword patterns for matching
    const rolePatterns = {
        'beautician': ['beaut', 'facial'],
        'massage_therapist': ['massage'],
        'hair_stylist': ['hair', 'stylist', 'cut'],
        'nail_technician': ['nail', 'manicure']
    };
    
    if(staffData) {
        // Get all staff from all roles
        const allStaffMembers = [];
        Object.keys(staffData).forEach(dbRole => {
            staffData[dbRole].forEach(member => {
                allStaffMembers.push({
                    member: member,
                    dbRole: dbRole
                });
            });
        });
        
        // Filter staff based on service matching
        allStaffMembers.forEach(({member, dbRole}) => {
            let shouldInclude = false;
            const dbRoleLower = dbRole.toLowerCase();
            
            // Method 1: Check if staff has this service in their primary services (staffServiceMap)
            // This is the most reliable - if they have the service, always include them
            const hasService = suggestedStaffEmails.includes(member.staff_id) || 
                              suggestedStaffEmails.includes(member.staff_email) ||
                              suggestedStaffEmails.some(email => 
                                  String(email).toLowerCase() === String(member.staff_id).toLowerCase() ||
                                  String(email).toLowerCase() === String(member.staff_email).toLowerCase()
                              );
            
            if (hasService) {
                shouldInclude = true;
            }
            // Method 2: Role-based matching by category
            else if (expectedRoleKeyword && rolePatterns[expectedRoleKeyword]) {
                const keywords = rolePatterns[expectedRoleKeyword];
                if (keywords.some(kw => dbRoleLower.includes(kw))) {
                    shouldInclude = true;
                }
            }
            // Method 3: Service name keyword matching (fallback - match any role that has relevant keywords)
            else {
                // Match by service text keywords
                if (serviceText.includes('facial') || serviceText.includes('anti-aging') || serviceText.includes('cleansing')) {
                    if (dbRoleLower.includes('beaut') || dbRoleLower.includes('facial')) {
                        shouldInclude = true;
                    }
                } else if (serviceText.includes('massage') || serviceText.includes('hot stone') || serviceText.includes('aromatherapy')) {
                    if (dbRoleLower.includes('massage')) {
                        shouldInclude = true;
                    }
                } else if (serviceText.includes('hair') || serviceText.includes('cut') || serviceText.includes('styling')) {
                    if (dbRoleLower.includes('hair') || dbRoleLower.includes('stylist')) {
                        shouldInclude = true;
                    }
                } else if (serviceText.includes('nail') || serviceText.includes('manicure') || serviceText.includes('pedicure') || serviceText.includes('gel')) {
                    if (dbRoleLower.includes('nail') || dbRoleLower.includes('manicure')) {
                        shouldInclude = true;
                    }
                }
            }
            
            if (shouldInclude) {
                // Check if this staff member should be suggested for this service
                let isSuggested = false;
                
                // PRIMARY METHOD: Use staffServiceMap (service_id based mapping) - this is the most accurate
                // This matches staff to services based on the database staff_service table using service_id
                if (suggestedStaffEmails.length > 0) {
                    isSuggested = suggestedStaffEmails.includes(member.staff_id) || 
                                 suggestedStaffEmails.includes(member.staff_email) ||
                                 suggestedStaffEmails.some(email => 
                                     String(email).toLowerCase() === String(member.staff_id).toLowerCase() ||
                                     String(email).toLowerCase() === String(member.staff_email).toLowerCase()
                                 );
                    
                    // #region agent log
                    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:598',message:'Using staffServiceMap (service_id match)',data:{staffName:member.first_name,staffId:member.staff_id,staffEmail:member.staff_email,serviceId:sid,isSuggested:isSuggested,suggestedStaffEmails:suggestedStaffEmails},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix',hypothesisId:'E'})}).catch(()=>{});
                    // #endregion
                }
                
                // FALLBACK METHOD: Only use primary_services string matching if staffServiceMap is not available
                // This uses exact service name matching with word boundaries to prevent false positives
                if (!isSuggested) {
                    const staffPrimaryServices = member.primary_services || '';
                    
                    if (staffPrimaryServices && service.name) {
                        // Normalize service name for comparison (remove extra spaces, lowercase)
                        const serviceNameNormalized = service.name.toLowerCase().trim();
                        
                        // Normalize primary services string
                        const primaryServicesLower = staffPrimaryServices.toLowerCase().trim();
                        
                        // Split primary_services by common separators (&, comma, etc.) and check each part
                        const serviceParts = primaryServicesLower.split(/[&,]/).map(s => s.trim()).filter(s => s.length > 0);
                        
                        // Check if service name matches any of the primary service parts
                        // Only suggest if the exact service name is found in primary_services (strict matching)
                        isSuggested = serviceParts.some(part => {
                            const partTrimmed = part.trim();
                            
                            // Exact match (e.g., "anti-aging facial" === "anti-aging facial")
                            if (partTrimmed === serviceNameNormalized) {
                                // #region agent log
                                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:625',message:'Fallback: Exact match found',data:{part:partTrimmed,serviceNameNormalized:serviceNameNormalized,staffName:member.first_name},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix',hypothesisId:'E'})}).catch(()=>{});
                                // #endregion
                                return true;
                            }
                            
                            // Check if part contains the full service name as a complete phrase with word boundaries
                            // This handles cases like "Anti-Aging Facial" in "Anti-Aging Facial Treatment"
                            // But NOT "Facial" matching "Anti-Aging Facial" (prevents false positives)
                            const index = partTrimmed.indexOf(serviceNameNormalized);
                            if (index !== -1) {
                                // Check word boundaries - make sure it's not a partial word match
                                const beforeChar = index > 0 ? partTrimmed[index - 1] : ' ';
                                const afterIndex = index + serviceNameNormalized.length;
                                const afterChar = afterIndex < partTrimmed.length ? partTrimmed[afterIndex] : ' ';
                                
                                // Only match if surrounded by word boundaries (space, dash, or start/end)
                                if ((beforeChar === ' ' || beforeChar === '-' || index === 0) &&
                                    (afterChar === ' ' || afterChar === '-' || afterChar === ',' || afterIndex === partTrimmed.length)) {
                                    // #region agent log
                                    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:642',message:'Fallback: Phrase match with word boundaries',data:{part:partTrimmed,serviceNameNormalized:serviceNameNormalized,staffName:member.first_name},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix',hypothesisId:'E'})}).catch(()=>{});
                                    // #endregion
                                    return true;
                                }
                            }
                            
                            return false;
                        });
                        
                        // #region agent log
                        fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:658',message:'Fallback string matching result',data:{isSuggested:isSuggested,staffName:member.first_name,serviceName:service.name,serviceId:sid},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix',hypothesisId:'E'})}).catch(()=>{});
                        // #endregion
                    }
                }
                
                staffList.push({
                    member: member,
                    role: dbRole,
                    isSuggested: isSuggested
                });
            }
        });
    }
    
    // Sort: suggested staff first, then others
    staffList.sort((a, b) => {
        if (a.isSuggested && !b.isSuggested) return -1;
        if (!a.isSuggested && b.isSuggested) return 1;
        return 0;
    });
    
    // Show message if no staff found (no fallback - strict filtering)
    if(staffList.length === 0) {
        list.append(`
            <div class="alert alert-info mb-2">
                <i class="fas fa-info-circle me-2"></i>
                No staff members available for this service category. Please select "No Preference" to let us assign a specialist.
            </div>
        `);
    }
    
    // Render staff list
    staffList.forEach(item => {
        const member = item.member;
        const dbRole = item.role;
        const isSuggested = item.isSuggested;
        const isSel = (selectedStaff[sid] == member.staff_id);
        
        // Enhanced "Suggested" badge with better styling
        const suggestedBadge = isSuggested 
            ? '<span class="badge bg-warning text-dark ms-2 px-2 py-1" style="font-size: 0.75rem; font-weight: 600;"><i class="fas fa-star me-1"></i>Suggested</span>' 
            : '';
        
        const cardStyle = isSel 
            ? 'border-color: var(--accent-gold) !important; background: rgba(212, 175, 55, 0.05) !important;' 
            : isSuggested 
                ? 'border-color: #ffc107 !important; background: rgba(255, 193, 7, 0.08) !important; box-shadow: 0 2px 4px rgba(255, 193, 7, 0.2) !important;' 
                : '';
        
        // Use data attributes for better reliability
        const staffIdEscaped = member.staff_id.replace(/"/g, '&quot;');
        list.append(`
            <div class="d-flex justify-content-between p-3 border rounded mb-2 bg-white staff-option" 
                 data-service-id="${sid}" 
                 data-staff-id="${staffIdEscaped}"
                 style="cursor: pointer; ${cardStyle}">
                <div class="flex-grow-1">
                    <div class="fw-bold d-flex align-items-center flex-wrap">
                        ${member.first_name} ${member.last_name}
                        ${suggestedBadge}
                    </div>
                    <small class="text-muted d-block mt-1">${dbRole.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase())}</small>
                </div>
                <div class="d-flex align-items-center">
                    <button class="btn btn-sm ${isSel ? 'btn-warning' : 'btn-outline-secondary'} rounded-pill staff-select-btn" 
                            data-service-id="${sid}" 
                            data-staff-id="${staffIdEscaped}"
                            onclick="event.stopPropagation(); selectStaff('${sid}', '${staffIdEscaped}');">
                        ${isSel ? 'Selected' : 'Select'}
                    </button>
                </div>
            </div>
        `);
    });
    
    // Add event delegation for staff options in modal (clicking the card)
    $(document).off('click', '.staff-option').on('click', '.staff-option', function(e) {
        // Don't trigger if clicking the button (button has its own handler)
        if ($(e.target).closest('.staff-select-btn').length > 0) {
            return;
        }
        e.stopPropagation();
        const serviceId = $(this).data('service-id');
        const staffId = $(this).data('staff-id');
        if(serviceId !== undefined) {
            selectStaff(serviceId, staffId);
        }
    });
    
    // Add event delegation for the Select button specifically
    $(document).off('click', '.staff-select-btn').on('click', '.staff-select-btn', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const serviceId = $(this).data('service-id');
        const staffId = $(this).data('staff-id');
        if(serviceId !== undefined) {
            selectStaff(serviceId, staffId);
        }
    });
    
    // Show the modal using Bootstrap
    const modalElement = document.getElementById('staffModal');
    if(modalElement) {
        // Dispose of any existing modal instance
        const existingModal = bootstrap.Modal.getInstance(modalElement);
        if(existingModal) {
            existingModal.dispose();
        }
        // Create and show new modal
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        console.error('Modal element not found');
        alert('Unable to open staff selection. Please refresh the page.');
    }
}

function selectStaff(sid, stid) {
    // Handle both string and number IDs
    const serviceId = typeof sid === 'string' ? sid : String(sid);
    const staffId = stid === 0 || stid === '0' ? 0 : stid;
    
    // Toggle selection: if clicking the same staff again, deselect (set to No Preference)
    const currentSelection = selectedStaff[serviceId];
    if (currentSelection == staffId && staffId != 0) {
        // Deselect: set to No Preference
        selectedStaff[serviceId] = 0;
    } else {
        // Select the staff
        selectedStaff[serviceId] = staffId;
    }
    
    // Update the UI
    loadStaffSelection();
    updateSummary();
    
    // Update the modal content to reflect the new selection (keep modal open)
    const list = $('#modal-staff-list');
    if(list.length > 0) {
        // Update all staff option buttons and styles
        list.find('.staff-option').each(function() {
            const $option = $(this);
            const optionStaffId = $option.data('staff-id');
            const optionServiceId = $option.data('service-id');
            
            // Check if this option is selected
            let isSel = false;
            if(optionStaffId == '0' || optionStaffId == 0) {
                isSel = (selectedStaff[optionServiceId] == 0);
            } else {
                isSel = (selectedStaff[optionServiceId] == optionStaffId);
            }
            
            // Update button
            const $btn = $option.find('button');
            $btn.removeClass('btn-warning btn-outline-secondary')
                .addClass(isSel ? 'btn-warning' : 'btn-outline-secondary')
                .text(isSel ? 'Selected' : 'Select');
            
            // Update card style
            if(isSel) {
                $option.css({
                    'border-color': 'var(--accent-gold)',
                    'background': 'rgba(212, 175, 55, 0.05)'
                });
            } else {
                const isSuggested = $option.find('.badge').length > 0;
                if(isSuggested) {
                    $option.css({
                        'border-color': '#ffc107',
                        'background': 'rgba(255, 193, 7, 0.08)'
                    });
                } else {
                    $option.css({
                        'border-color': '',
                        'background': ''
                    });
                }
            }
        });
    }
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
    else if(uStaff.length===1) { disp.text(uStaff[0]); icon.html('<i class="fas fa-user" style="color: #c29076;"></i>'); }
    else { disp.text(`Multiple Staff (${uStaff.join(", ")})`); icon.html('<div class="d-flex"><i class="fas fa-user" style="color: #c29076;"></i><i class="fas fa-user text-success ms-n1"></i></div>'); }
}

function validateCurrentStep() {
    if(currentStep===1 && selectedServices.length===0) { alert('Select a service'); return false; }
    if(currentStep===3 && (!selectedDate || !selectedTime)) { alert('Select date & time'); return false; }
    if(currentStep===4 && !$('#payment-method').val()) { alert('Select payment'); return false; }
    return true;
}
function nextStep() {
    if(currentStep<4) {
        $(`#step-${currentStep}`).removeClass('show-step'); 
        currentStep++; 
        $(`#step-${currentStep}`).addClass('show-step');
        if(currentStep===2) loadStaffSelection();
        if(currentStep===3) { updateStaffHeader(); renderCalendar(); }
        updateStepIndicator(); updateNavigationButtons();
    } else submitBooking();
}
function prevStep() { 
    if(currentStep>1) { 
        $(`#step-${currentStep}`).removeClass('show-step'); 
        currentStep--; 
        $(`#step-${currentStep}`).addClass('show-step'); 
        updateStepIndicator(); 
        updateNavigationButtons(); 
    } 
}
function updateStepIndicator() { $('.step').removeClass('active completed'); for(let i=1; i<=4; i++) { if(i<currentStep) $(`#step-${i}-indicator`).addClass('completed'); else if(i===currentStep) $(`#step-${i}-indicator`).addClass('active'); } }
function updateNavigationButtons() { $('#prev-btn').toggle(currentStep>1); $('#next-btn').html(currentStep===4 ? 'Confirm' : 'Next'); }
function submitBooking() {
    // Validate before submitting
    if(selectedServices.length === 0) {
        if (typeof showErrorModal === 'function') {
            showErrorModal('Please select at least one service.');
        } else {
            alert('Please select at least one service.');
        }
        return;
    }
    
    if(!selectedDate || !selectedTime) {
        if (typeof showErrorModal === 'function') {
            showErrorModal('Please select a date and time for your booking.');
        } else {
            alert('Please select a date and time for your booking.');
        }
        return;
    }
    
    // Disable the confirm button to prevent double submission
    const confirmBtn = $('#next-btn');
    const originalText = confirmBtn.html();
    confirmBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
    
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:841',message:'Before normalization',data:{selectedServices:selectedServices,selectedServicesType:typeof selectedServices[0],selectedServicesLength:selectedServices.length},timestamp:Date.now(),sessionId:'debug-session',runId:'run2',hypothesisId:'B'})}).catch(()=>{});
    // #endregion
    
    // Service IDs are alphanumeric strings (e.g., "FC04", "MC04"), NOT integers
    // Keep them as strings, only filter out null/undefined/empty values
    const normalizedServices = selectedServices.filter(id => id !== null && id !== undefined && id !== '');
    
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:857',message:'After normalization',data:{normalizedServices:normalizedServices,normalizedLength:normalizedServices.length},timestamp:Date.now(),sessionId:'debug-session',runId:'run2',hypothesisId:'B'})}).catch(()=>{});
    // #endregion
    
    // Normalize staff IDs - service IDs are strings, staff IDs are emails (strings) or 0
    const normalizedStaff = {};
    Object.keys(selectedStaff).forEach(serviceId => {
        const staffValue = selectedStaff[serviceId];
        // Keep service ID as string, keep staff as-is (email string or 0)
        normalizedStaff[serviceId] = (staffValue === 0 || staffValue == '0') ? 0 : staffValue;
    });
    
    const data = { 
        services: normalizedServices, 
        staff: normalizedStaff, 
        date: selectedDate, 
        time: selectedTime, 
        specialRequests: $('#special-requests').val() || '', 
        paymentMethod: $('#payment-method').val() || 'pay_at_salon' 
    };
    
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'booking.js:862',message:'Final data being sent',data:{services:data.services,servicesLength:data.services.length,firstService:data.services[0],staff:data.staff},timestamp:Date.now(),sessionId:'debug-session',runId:'run2',hypothesisId:'B'})}).catch(()=>{});
    // #endregion
    
    // Log data being sent (for debugging)
    console.log('Submitting booking with data:', data);
    console.log('Selected services count:', normalizedServices.length);
    console.log('Selected staff:', normalizedStaff);
    
    $.ajax({ 
        url: 'process_booking.php', 
        method: 'POST', 
        data: JSON.stringify(data), 
        contentType: 'application/json', 
        success: function(res) { 
            console.log('Server response:', res);
            if(res.success) {
                // Get booking summary data
                const allServicesData = window.allServicesData || {};
                const serviceNames = selectedServices.map(sid => {
                    const service = allServicesData[sid];
                    return service ? service.name : 'Service';
                });
                
                // Calculate totals
                let subtotal = 0;
                selectedServices.forEach(sid => {
                    const service = allServicesData[sid];
                    if(service) subtotal += parseFloat(service.price || 0);
                });
                
                // Format date
                const dateObj = new Date(selectedDate);
                const formattedDate = dateObj.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
                
                // Format time
                const timeParts = selectedTime.split(':');
                const hour = parseInt(timeParts[0]);
                const minute = timeParts[1];
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                const formattedTime = `${displayHour}:${minute} ${ampm}`;
                
                // Show confirmation modal
                if (typeof showBookingConfirmation === 'function') {
                    showBookingConfirmation({
                        booking_id: res.booking_id,
                        date: formattedDate,
                        time: formattedTime,
                        services: res.services || serviceNames.join(', '), // Use services array from response if available
                        subtotal: subtotal.toFixed(2)
                    });
                } else {
                    // Fallback to alert if function not available
                    alert('Booking Successful! Booking ID: ' + res.booking_id);
                    window.location.href = 'user/dashboard.php?section=bookings';
                }
            } else {
                confirmBtn.prop('disabled', false).html(originalText);
                console.error('Booking failed. Full server response:', JSON.stringify(res, null, 2));
                let errorMsg = res.message || 'Booking failed. Please try again.';
                
                // Include error details if available (for debugging - can remove in production)
                if (res.error) {
                    console.error('Server error details:', res.error);
                    // For development: show error details in console
                    if (res.debug) {
                        console.error('Debug info:', res.debug);
                    }
                }
                
                if (typeof showErrorModal === 'function') {
                    showErrorModal(errorMsg);
                } else {
                    alert(errorMsg);
                }
            }
        }, 
        error: function(xhr, status, error) {
            confirmBtn.prop('disabled', false).html(originalText);
            let errorMessage = 'An error occurred. Please try again.';
            
            // Try to parse error response
            if (xhr.responseText) {
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    if (errorData.message) {
                        errorMessage = errorData.message;
                    }
                    if (errorData.error) {
                        console.error('Server error details:', errorData.error);
                        // Include error details in message for debugging (remove in production)
                        if (errorData.error) {
                            errorMessage += '\n\nDetails: ' + errorData.error;
                        }
                    }
                } catch (e) {
                    console.error('Raw response (not JSON):', xhr.responseText);
                    errorMessage = 'Server returned an invalid response. Please try again.';
                }
            }
            
            // Log full error details
            console.error('Booking AJAX error:', {
                error: error,
                status: status,
                statusCode: xhr.status,
                responseText: xhr.responseText
            });
            
            if (typeof showErrorModal === 'function') {
                showErrorModal(errorMessage);
            } else {
                alert(errorMessage);
            }
        }
    });
}

