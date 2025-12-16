/**
 * Admin Dashboard Dynamic Content Loader
 * Loads and renders: Today's Appointments, Recent Activity, Top Services Today
 */

// Get base path from global variable set in PHP
const API_BASE_PATH = typeof DASHBOARD_BASE_PATH !== 'undefined' ? DASHBOARD_BASE_PATH : '../..';

document.addEventListener('DOMContentLoaded', function() {
    loadTodaysAppointments();
    loadRecentActivity();
    loadTopServicesToday();
});

/**
 * Load and render today's appointments
 */
async function loadTodaysAppointments() {
    const container = document.getElementById('appointments-list-container');
    if (!container) return;

    // Show loading state
    container.innerHTML = '<div class="loading-spinner">Loading appointments...</div>';

    try {
        // Get today's date in YYYY-MM-DD format
        const today = new Date();
        const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/6202d6bb-cc4f-49c4-b278-16d6d5c17837',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'dashboard.js:30','message':'Fetching appointments API','data':{url:`${API_BASE_PATH}/api/admin/bookings/list.php?date=${dateStr}`},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'A'})}).catch(()=>{});
        // #endregion
        const response = await fetch(`${API_BASE_PATH}/api/admin/bookings/list.php?date=${dateStr}`);
        // #region agent log
        const responseText = await response.text();
        fetch('http://127.0.0.1:7242/ingest/6202d6bb-cc4f-49c4-b278-16d6d5c17837',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'dashboard.js:34','message':'API response received','data':{status:response.status,statusText:response.statusText,contentType:response.headers.get('content-type'),responseLength:responseText.length,responsePreview:responseText.substring(0,200),isJson:responseText.trim().startsWith('{')},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'A'})}).catch(()=>{});
        // #endregion
        const data = JSON.parse(responseText);

        if (!response.ok) {
            throw new Error(data.error?.message || 'Failed to load appointments');
        }

        if (data.success && data.bookings && data.bookings.length > 0) {
            renderAppointments(data.bookings);
        } else {
            container.innerHTML = '<div class="empty-state">No appointments scheduled for today.</div>';
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
        container.innerHTML = '<div class="error-state">Unable to load appointments. Please try again later.</div>';
    }
}

/**
 * Render appointment items
 */
function renderAppointments(bookings) {
    const container = document.getElementById('appointments-list-container');
    if (!container) return;

    // Sort by start_time
    bookings.sort((a, b) => {
        const timeA = a.start_time || '00:00:00';
        const timeB = b.start_time || '00:00:00';
        return timeA.localeCompare(timeB);
    });

    let html = '';
    bookings.forEach(booking => {
        const customerName = booking.customer_name || 
            `${booking.customer_first_name || ''} ${booking.customer_last_name || ''}`.trim() || 
            'Unknown Customer';
        
        // Generate initials for avatar
        const initials = getInitials(customerName);
        
        // Format time range
        const startTime = formatTime(booking.start_time);
        const endTime = formatTime(booking.expected_finish_time);
        const timeRange = startTime && endTime ? `${startTime} - ${endTime}` : 'Time TBD';
        
        // Get service names
        const services = booking.services || [];
        const serviceNames = services.map(s => s.service_name).join(', ') || 'No services';
        
        // Get staff names (unique)
        const staffNames = [...new Set(services.map(s => s.staff_name).filter(Boolean))].join(', ') || 'Unassigned';
        
        // Status badge class
        const statusClass = booking.status || 'confirmed';
        
        html += `
            <div class="appointment-item">
                <div class="appointment-avatar">${initials}</div>
                <div class="appointment-info">
                    <h4>${escapeHtml(customerName)}</h4>
                    <p>${escapeHtml(serviceNames)}</p>
                </div>
                <div class="appointment-time">
                    <span class="time">${timeRange}</span>
                    <span class="staff-name">${escapeHtml(staffNames)}</span>
                </div>
                <span class="status-badge ${statusClass}">${statusClass}</span>
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * Load and render recent activity
 */
async function loadRecentActivity() {
    const container = document.getElementById('activity-list-container');
    if (!container) return;

    // Show loading state
    container.innerHTML = '<div class="loading-spinner">Loading activity...</div>';

    try {
        // Get bookings from last 7 days
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(startDate.getDate() - 7);
        
        const startDateStr = formatDate(startDate);
        const endDateStr = formatDate(endDate);

        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/6202d6bb-cc4f-49c4-b278-16d6d5c17837',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'dashboard.js:124','message':'Fetching activity API','data':{url:`${API_BASE_PATH}/api/admin/bookings/list.php?start_date=${startDateStr}&end_date=${endDateStr}`},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'A'})}).catch(()=>{});
        // #endregion
        const response = await fetch(`${API_BASE_PATH}/api/admin/bookings/list.php?start_date=${startDateStr}&end_date=${endDateStr}`);
        // #region agent log
        const responseText = await response.text();
        fetch('http://127.0.0.1:7242/ingest/6202d6bb-cc4f-49c4-b278-16d6d5c17837',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'dashboard.js:128','message':'Activity API response received','data':{status:response.status,contentType:response.headers.get('content-type'),responseLength:responseText.length,responsePreview:responseText.substring(0,200),isJson:responseText.trim().startsWith('{')},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'A'})}).catch(()=>{});
        // #endregion
        const data = JSON.parse(responseText);

        if (!response.ok) {
            throw new Error(data.error?.message || 'Failed to load activity');
        }

        if (data.success && data.bookings && data.bookings.length > 0) {
            renderActivity(data.bookings);
        } else {
            container.innerHTML = '<div class="empty-state">No recent activity.</div>';
        }
    } catch (error) {
        console.error('Error loading activity:', error);
        container.innerHTML = '<div class="error-state">Unable to load activity. Please try again later.</div>';
    }
}

/**
 * Render activity items
 */
function renderActivity(bookings) {
    const container = document.getElementById('activity-list-container');
    if (!container) return;

    // Sort by most recent (created_at or updated_at)
    bookings.sort((a, b) => {
        const dateA = new Date(a.updated_at || a.created_at || 0);
        const dateB = new Date(b.updated_at || b.created_at || 0);
        return dateB - dateA;
    });

    // Limit to 15 most recent
    const recentBookings = bookings.slice(0, 15);

    let html = '';
    recentBookings.forEach(booking => {
        const customerName = booking.customer_name || 
            `${booking.customer_first_name || ''} ${booking.customer_last_name || ''}`.trim() || 
            'Unknown Customer';
        
        // Get first service name
        const services = booking.services || [];
        const firstService = services.length > 0 ? services[0].service_name : 'No services';
        
        // Determine activity type
        const activityType = determineActivityType(booking);
        
        // Calculate relative time
        const activityDate = new Date(booking.updated_at || booking.created_at);
        const relativeTime = getRelativeTime(activityDate);
        
        html += `
            <div class="activity-item">
                <div class="activity-dot"></div>
                <div class="activity-content">
                    <h5>${escapeHtml(activityType)}</h5>
                    <p>${escapeHtml(customerName)} - ${escapeHtml(firstService)}</p>
                </div>
                <span class="activity-time">${relativeTime}</span>
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * Load and render top services today
 */
async function loadTopServicesToday() {
    const container = document.getElementById('services-list-container');
    if (!container) return;

    // Show loading state
    container.innerHTML = '<div class="loading-spinner">Loading services...</div>';

    try {
        // Get today's date
        const today = new Date();
        const dateStr = formatDate(today);

        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/6202d6bb-cc4f-49c4-b278-16d6d5c17837',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'dashboard.js:206','message':'Fetching services API','data':{url:`${API_BASE_PATH}/api/admin/bookings/list.php?date=${dateStr}`},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'A'})}).catch(()=>{});
        // #endregion
        const response = await fetch(`${API_BASE_PATH}/api/admin/bookings/list.php?date=${dateStr}`);
        // #region agent log
        const responseText = await response.text();
        fetch('http://127.0.0.1:7242/ingest/6202d6bb-cc4f-49c4-b278-16d6d5c17837',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'dashboard.js:210','message':'Services API response received','data':{status:response.status,contentType:response.headers.get('content-type'),responseLength:responseText.length,responsePreview:responseText.substring(0,200),isJson:responseText.trim().startsWith('{')},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'A'})}).catch(()=>{});
        // #endregion
        const data = JSON.parse(responseText);

        if (!response.ok) {
            throw new Error(data.error?.message || 'Failed to load services');
        }

        if (data.success && data.bookings && data.bookings.length > 0) {
            renderTopServices(data.bookings);
        } else {
            container.innerHTML = '<div class="empty-state">No services booked today.</div>';
        }
    } catch (error) {
        console.error('Error loading services:', error);
        container.innerHTML = '<div class="error-state">Unable to load services. Please try again later.</div>';
    }
}

/**
 * Render top services with counts and percentages
 */
function renderTopServices(bookings) {
    const container = document.getElementById('services-list-container');
    if (!container) return;

    // Count services
    const serviceCounts = {};
    let totalServiceBookings = 0;

    bookings.forEach(booking => {
        const services = booking.services || [];
        services.forEach(service => {
            const serviceName = service.service_name || 'Unknown Service';
            if (!serviceCounts[serviceName]) {
                serviceCounts[serviceName] = 0;
            }
            serviceCounts[serviceName]++;
            totalServiceBookings++;
        });
    });

    // Convert to array and sort by count
    const serviceArray = Object.entries(serviceCounts)
        .map(([name, count]) => ({ name, count }))
        .sort((a, b) => b.count - a.count)
        .slice(0, 10); // Top 10

    if (serviceArray.length === 0) {
        container.innerHTML = '<div class="empty-state">No services booked today.</div>';
        return;
    }

    // Find max count for percentage calculation
    const maxCount = serviceArray[0].count;

    let html = '';
    serviceArray.forEach(service => {
        // Calculate percentage based on max count (for visual bar)
        const percentage = maxCount > 0 ? (service.count / maxCount) * 100 : 0;
        
        html += `
            <div class="service-item">
                <h5>${escapeHtml(service.name)}</h5>
                <div class="service-bar">
                    <div class="bar-fill" style="width: ${percentage}%;"></div>
                </div>
                <span class="service-count">${service.count}</span>
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * Helper: Get initials from name
 */
function getInitials(name) {
    if (!name) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
}

/**
 * Helper: Format time from HH:MM:SS to HH:MM
 */
function formatTime(timeStr) {
    if (!timeStr) return null;
    return timeStr.substring(0, 5); // HH:MM
}

/**
 * Helper: Format date to YYYY-MM-DD
 */
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Helper: Determine activity type from booking
 */
function determineActivityType(booking) {
    const status = booking.status || '';
    const created = new Date(booking.created_at);
    const updated = new Date(booking.updated_at || booking.created_at);
    const isNew = created.getTime() === updated.getTime();

    if (isNew) {
        return 'New booking';
    } else if (status === 'completed') {
        return 'Completed';
    } else if (status === 'cancelled') {
        return 'Cancellation';
    } else {
        return 'Status changed';
    }
}

/**
 * Helper: Get relative time string (e.g., "10 mins ago")
 */
function getRelativeTime(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) {
        return 'Just now';
    } else if (diffMins < 60) {
        return `${diffMins} ${diffMins === 1 ? 'min' : 'mins'} ago`;
    } else if (diffHours < 24) {
        return `${diffHours} ${diffHours === 1 ? 'hour' : 'hours'} ago`;
    } else if (diffDays < 7) {
        return `${diffDays} ${diffDays === 1 ? 'day' : 'days'} ago`;
    } else {
        return date.toLocaleDateString();
    }
}

/**
 * Helper: Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

