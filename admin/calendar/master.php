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

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div class="view-switcher" style="display: flex; gap: 8px; background: #f0f0f0; padding: 4px; border-radius: 8px;">
            <button class="btn btn-sm active" id="viewDay" onclick="switchView('day')" style="background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">Day</button>
            <button class="btn btn-sm" id="viewWeek" onclick="switchView('week')" style="background: transparent; border: none;">Week</button>
            <button class="btn btn-sm" id="viewMonth" onclick="switchView('month')" style="background: transparent; border: none;">Month</button>
        </div>
        
        <div class="date-controls" style="display: flex; align-items: center; gap: 16px;">
            <button class="btn btn-icon" onclick="changeDate(-1)" style="padding: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <h3 id="currentDateDisplay" style="margin: 0; min-width: 200px; text-align: center;">December 12, 2025</h3>
            <button class="btn btn-icon" onclick="changeDate(1)" style="padding: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <button class="btn btn-secondary btn-sm" onclick="goToToday()">Today</button>
        </div>
        
        <div class="filters" style="display: flex; gap: 12px;">
            <select id="staffFilter" class="form-control" style="width: auto;">
                <option value="">All Staff</option>
            </select>
            <select id="statusFilter" class="form-control" style="width: auto;">
                <option value="">All Statuses</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="no-show">No Show</option>
            </select>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="calendar-legend" style="display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; justify-content: center;">
            <div class="legend-item" style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 12px; height: 12px; border-radius: 50%; background: #4CAF50;"></div>
                <span style="font-size: 13px; color: #666;">Confirmed</span>
            </div>
            <div class="legend-item" style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 12px; height: 12px; border-radius: 50%; background: #2196F3;"></div>
                <span style="font-size: 13px; color: #666;">Completed</span>
            </div>
            <div class="legend-item" style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 12px; height: 12px; border-radius: 50%; background: #D0021B;"></div>
                <span style="font-size: 13px; color: #666;">Cancelled</span>
            </div>
            <div class="legend-item" style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 12px; height: 12px; border-radius: 50%; background: #9E9E9E;"></div>
                <span style="font-size: 13px; color: #666;">No-show</span>
            </div>
        </div>
        
        <div id="loadingState" class="loading" style="text-align: center; padding: 40px; color: #999;">
            Loading calendar...
        </div>
        
        <div id="emptyState" class="empty-state" style="display: none; text-align: center; padding: 60px 20px; color: #999;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 16px; opacity: 0.5; display: block;">
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
        
        <!-- Updated Staff Schedule Section -->
        <div class="staff-schedule-section" id="staffScheduleSection" style="display: none; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
            <h3 style="margin-bottom: 15px;">Staff Schedules</h3>
            <div class="staff-schedule-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px;">
                <!-- Cards will be dynamically generated -->
            </div>
        </div>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="bookingModal" class="modal">
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

<style>
/* ========== STAFF SCHEDULE TIMELINE VIEW STYLES ========== */
.staff-schedule-grid {
    display: block;
}

.staff-schedule-timeline {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #f0f0f0;
    overflow-x: auto;
}

.timeline-header {
    display: flex;
    border-bottom: 2px solid #e0e0e0;
    margin-bottom: 12px;
    position: sticky;
    left: 0;
    background: white;
    z-index: 10;
}

.timeline-staff-label {
    min-width: 150px;
    max-width: 150px;
    padding: 12px 16px;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    color: #666;
    background: #fafafa;
    border-right: 1px solid #e0e0e0;
    position: sticky;
    left: 0;
    z-index: 11;
}

.timeline-days {
    display: flex;
    flex: 1;
}

.timeline-day-header {
    min-width: 50px;
    width: 50px;
    padding: 12px 8px;
    text-align: center;
    font-weight: 600;
    font-size: 12px;
    color: #666;
    background: #fafafa;
    border-right: 1px solid #e0e0e0;
}

.timeline-row {
    display: flex;
    border-bottom: 1px solid #f0f0f0;
    min-height: 60px;
}

.timeline-row:last-child {
    border-bottom: none;
}

.timeline-staff-name {
    min-width: 150px;
    max-width: 150px;
    padding: 16px;
    font-weight: 500;
    font-size: 14px;
    color: #333;
    background: #fafafa;
    border-right: 1px solid #e0e0e0;
    display: flex;
    align-items: center;
    position: sticky;
    left: 0;
    z-index: 5;
}

.timeline-day-cell {
    min-width: 50px;
    width: 50px;
    padding: 4px;
    border-right: 1px solid #f0f0f0;
    display: flex;
    flex-direction: column;
    gap: 2px;
    position: relative;
}

.timeline-day-cell.today {
    background: #fff9e6;
}

.timeline-block {
    border-radius: 4px;
    padding: 4px 6px;
    font-size: 10px;
    line-height: 1.2;
    cursor: pointer;
    transition: opacity 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-height: 20px;
}

.timeline-block:hover {
    opacity: 0.8;
}

.timeline-block.timeline-working {
    background: linear-gradient(135deg, #D4A574, #C4956A);
    color: white;
    font-weight: 500;
}

.timeline-block.timeline-leave {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
    font-weight: 500;
}

.timeline-block.timeline-empty {
    background: transparent;
    min-height: 20px;
}

.timeline-block-time {
    font-size: 9px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.timeline-block-label {
    font-size: 9px;
    font-weight: 600;
    text-align: center;
}

/* Scrollbar styling for timeline */
.staff-schedule-timeline::-webkit-scrollbar {
    height: 8px;
}

.staff-schedule-timeline::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.staff-schedule-timeline::-webkit-scrollbar-thumb {
    background: #D4A574;
    border-radius: 4px;
}

.staff-schedule-timeline::-webkit-scrollbar-thumb:hover {
    background: #C4956A;
}

@media (max-width: 768px) {
    .timeline-staff-label,
    .timeline-staff-name {
        min-width: 120px;
        max-width: 120px;
        font-size: 12px;
    }
    
    .timeline-day-header,
    .timeline-day-cell {
        min-width: 40px;
        width: 40px;
    }
    
    .timeline-block-time {
        font-size: 8px;
    }
    
    .timeline-block-label {
        font-size: 8px;
    }
}
</style>

<script src="master.js"></script>

<?php
// Include footer
include '../includes/footer.php';
?>
