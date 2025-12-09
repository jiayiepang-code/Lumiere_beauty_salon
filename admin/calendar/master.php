<?php
// Include authentication check
require_once '../includes/auth_check.php';

// Require admin authentication
requireAdminAuth();

// Set page title
$page_title = 'Master Calendar';
$base_path = '../..';

// Include header
include '../includes/header.php';
?>

<style>
.calendar-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    gap: 16px;
    flex-wrap: wrap;
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.view-switcher {
    display: flex;
    gap: 8px;
    background: #f0f0f0;
    padding: 4px;
    border-radius: 8px;
}

.view-btn {
    padding: 8px 16px;
    border: none;
    background: transparent;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #666;
    transition: all 0.2s;
}

.view-btn.active {
    background: white;
    color: #667eea;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.calendar-filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filter-group label {
    font-size: 12px;
    color: #666;
    font-weight: 500;
}

.filter-input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    background: white;
}

.date-navigation {
    display: flex;
    align-items: center;
    gap: 12px;
}

.nav-btn {
    padding: 8px 12px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.nav-btn:hover {
    background: #f8f9fa;
    border-color: #667eea;
}

.current-date {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    min-width: 200px;
    text-align: center;
}

.calendar-container {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    min-height: 600px;
}

.calendar-grid {
    display: grid;
    gap: 16px;
}

.calendar-day-view {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.time-slot {
    display: grid;
    grid-template-columns: 80px 1fr;
    gap: 16px;
    min-height: 60px;
}

.time-label {
    font-size: 13px;
    color: #666;
    font-weight: 500;
    padding-top: 4px;
}

.slot-bookings {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.booking-card {
    padding: 12px;
    border-radius: 8px;
    border-left: 4px solid;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
}

.booking-card:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.booking-card.status-confirmed {
    border-left-color: #4CAF50;
    background: #f1f8f4;
}

.booking-card.status-completed {
    border-left-color: #2196F3;
    background: #e3f2fd;
}

.booking-card.status-cancelled,
.booking-card.status-no-show {
    border-left-color: #F44336;
    background: #ffebee;
}

.booking-card.status-available {
    border-left-color: #9E9E9E;
    background: #f5f5f5;
}

.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 8px;
}

.booking-customer {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.booking-time {
    font-size: 12px;
    color: #666;
}

.booking-services {
    font-size: 13px;
    color: #666;
    margin-bottom: 4px;
}

.booking-staff {
    font-size: 12px;
    color: #999;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.confirmed {
    background: #4CAF50;
    color: white;
}

.status-badge.completed {
    background: #2196F3;
    color: white;
}

.status-badge.cancelled,
.status-badge.no-show {
    background: #F44336;
    color: white;
}

.staff-schedule-section {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 2px solid #f0f0f0;
}

.staff-schedule-section h3 {
    margin-bottom: 16px;
    color: #333;
    font-size: 18px;
}

.staff-schedule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 16px;
}

.staff-schedule-card {
    padding: 16px;
    border-radius: 8px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
}

.staff-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.schedule-time {
    font-size: 14px;
    color: #666;
    display: flex;
    align-items: center;
    gap: 8px;
}

.schedule-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    margin-top: 8px;
}

.schedule-status.working {
    background: #d4edda;
    color: #155724;
}

.schedule-status.off {
    background: #f8d7da;
    color: #721c24;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.loading {
    text-align: center;
    padding: 40px;
    color: #999;
}

/* Legend */
.calendar-legend {
    display: flex;
    gap: 24px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .calendar-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .calendar-filters {
        flex-direction: column;
        width: 100%;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-input {
        width: 100%;
    }
    
    .date-navigation {
        justify-content: space-between;
    }
    
    .staff-schedule-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="calendar-controls">
    <div class="view-switcher">
        <button class="view-btn active" data-view="day" onclick="switchView('day')">Day</button>
        <button class="view-btn" data-view="week" onclick="switchView('week')">Week</button>
        <button class="view-btn" data-view="month" onclick="switchView('month')">Month</button>
    </div>
    
    <div class="date-navigation">
        <button class="nav-btn" onclick="navigateDate('prev')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
        <div class="current-date" id="currentDate">Today</div>
        <button class="nav-btn" onclick="navigateDate('next')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>
        <button class="nav-btn" onclick="navigateDate('today')">Today</button>
    </div>
    
    <div class="calendar-filters">
        <div class="filter-group">
            <label>Date</label>
            <input type="date" id="dateFilter" class="filter-input" onchange="applyFilters()">
        </div>
        <div class="filter-group">
            <label>Staff Member</label>
            <select id="staffFilter" class="filter-input" onchange="applyFilters()">
                <option value="">All Staff</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select id="statusFilter" class="filter-input" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="no-show">No Show</option>
            </select>
        </div>
    </div>
</div>

<div class="calendar-container">
    <div class="calendar-legend">
        <div class="legend-item">
            <div class="legend-color" style="background: #4CAF50;"></div>
            <span>Confirmed</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: #2196F3;"></div>
            <span>Completed</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: #F44336;"></div>
            <span>Cancelled/No-show</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: #9E9E9E;"></div>
            <span>Available</span>
        </div>
    </div>
    
    <div id="loadingState" class="loading">
        Loading calendar...
    </div>
    
    <div id="emptyState" class="empty-state" style="display: none;">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <p>No bookings found for this date</p>
    </div>
    
    <div id="calendarView" style="display: none;">
        <!-- Calendar content will be dynamically generated -->
    </div>
    
    <div class="staff-schedule-section" id="staffScheduleSection" style="display: none;">
        <h3>Staff Schedules</h3>
        <div class="staff-schedule-grid" id="staffScheduleGrid">
            <!-- Staff schedules will be dynamically generated -->
        </div>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="bookingModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Booking Details</h3>
            <button class="modal-close" onclick="closeBookingModal()">&times;</button>
        </div>
        <div id="bookingDetailsContent" class="modal-body">
            <!-- Booking details will be loaded here -->
        </div>
    </div>
</div>

<script src="master.js"></script>

<?php
// Include footer
include '../includes/footer.php';
?>
