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
        
        <!-- Staff Roster Section -->
        <div class="staff-roster-section" id="staffRosterSection" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h2 style="margin: 0; font-size: 24px; font-weight: 700; color: #333; display: flex; align-items: center; gap: 12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        Staff Roster
                    </h2>
                    <p id="staffRosterSubtitle" style="margin: 8px 0 0 0; font-size: 14px; color: #666;">
                        Today's schedule &amp; availability
                    </p>
                </div>
                <div class="roster-legend" style="display: flex; gap: 16px; flex-wrap: wrap;">
                    <div class="legend-item" style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 10px; height: 10px; border-radius: 50%; background: #4CAF50;"></div>
                        <span style="font-size: 12px; color: #666;">Available</span>
                    </div>
                    <div class="legend-item" style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 10px; height: 10px; border-radius: 50%; background: #9E9E9E;"></div>
                        <span style="font-size: 12px; color: #666;">Off Duty</span>
                    </div>
                    <div class="legend-item" style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 10px; height: 10px; border-radius: 50%; background: #c29076;"></div>
                        <span style="font-size: 12px; color: #666;">With Customer</span>
                    </div>
                    <div class="legend-item" style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 10px; height: 10px; border-radius: 50%; background: #ff6b6b;"></div>
                        <span style="font-size: 12px; color: #666;">Leave</span>
                    </div>
                </div>
            </div>
            <div id="staffRosterViewLabel" style="margin-bottom: 12px; font-size: 13px; color: #888;">
                Showing <strong>today's</strong> roster. Switch the main Day / Week / Month view above to change how the roster is displayed.
            </div>
            <div id="staffRosterLoading" class="loading" style="text-align: center; padding: 40px; color: #999;">
                Loading staff roster...
            </div>
            <!-- This container is reused for card, weekly, and monthly layouts -->
            <div id="staffRosterGrid" class="staff-roster-grid" style="display: none;"></div>
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
/* ========== STAFF ROSTER TABLE STYLES ========== */
.roster-table-wrapper {
    background: white;
    border-radius: 12px;
    padding: 20px 20px 20px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #f0f0f0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.roster-table {
    width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
    font-size: 14px;
    min-width: 600px;
}

/* Table Header */
.roster-table thead {
    background: #fafafa;
    border-bottom: 2px solid #e0e0e0;
}

.roster-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.roster-staff-header {
    min-width: 180px;
    width: 180px;
    position: sticky;
    left: 0;
    z-index: 10;
    background: #fafafa;
    border-right: 2px solid #e0e0e0;
    vertical-align: middle;
    text-align: left;
    padding: 12px 12px 12px 12px;
}

.roster-day-header {
    min-width: 100px;
    width: 100px;
    text-align: center;
    padding: 12px 8px;
}

.roster-day-header.today {
    background: #fff9e6;
    color: #c29076;
    font-weight: 700;
}

.roster-weekday {
    display: block;
    font-size: 12px;
    margin-bottom: 4px;
}

.roster-daynum {
    display: block;
    font-size: 16px;
    font-weight: 700;
}

/* Table Body */
.roster-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.roster-table tbody tr:hover {
    background-color: #fafafa;
}

.roster-table tbody tr:last-child {
    border-bottom: none;
}

.roster-staff-cell {
    min-width: 180px;
    width: 180px;
    padding: 16px 12px 16px 12px;
    font-weight: 500;
    color: #333;
    background: #fafafa;
    border-right: 2px solid #e0e0e0;
    position: sticky;
    left: 0;
    z-index: 5;
    white-space: nowrap;
    vertical-align: middle;
    text-align: left;
}

.roster-day-cell {
    min-width: 100px;
    width: 100px;
    padding: 8px;
    text-align: center;
    vertical-align: middle;
    border-right: 1px solid #f0f0f0;
}

.roster-day-cell.today {
    background: #fff9e6;
}

/* Shift Blocks */
.roster-shift {
    border-radius: 6px;
    padding: 8px 6px;
    font-size: 11px;
    line-height: 1.4;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 4px;
}

.roster-shift:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.shift-label {
    font-weight: 600;
    font-size: 11px;
    display: block;
}

.shift-time {
    font-size: 9px;
    opacity: 0.9;
    display: block;
}

/* Shift Type Colors */
.roster-shift.shift-full {
    background: #4CAF50;
    color: #ffffff;
}

.roster-shift.shift-morning {
    background: #2196F3;
    color: #ffffff;
}

.roster-shift.shift-afternoon {
    background: #FF9800;
    color: #ffffff;
}

.roster-shift.shift-off {
    background: #EEEEEE;
    color: #999999;
}

.roster-shift.shift-with-client {
    background: #c29076;
    color: #ffffff;
}

.roster-shift.shift-leave {
    background: #ff6b6b;
    color: #ffffff;
}

/* Monthly View - Compact Blocks */
.roster-table-month .roster-shift {
    min-height: 32px;
    padding: 4px;
}

.roster-table-month .shift-label,
.roster-table-month .shift-time {
    display: none;
}

.roster-table-month .roster-day-header {
    min-width: 50px;
    width: 50px;
    padding: 8px 4px;
}

.roster-table-month .roster-day-cell {
    min-width: 50px;
    width: 50px;
    padding: 4px;
}

.roster-table-month .roster-shift {
    min-height: 24px;
    border-radius: 4px;
}

/* Empty State */
.roster-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
    font-size: 14px;
}

/* Scrollbar Styling */
.roster-table-wrapper::-webkit-scrollbar {
    height: 8px;
}

.roster-table-wrapper::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.roster-table-wrapper::-webkit-scrollbar-thumb {
    background: #D4A574;
    border-radius: 4px;
}

.roster-table-wrapper::-webkit-scrollbar-thumb:hover {
    background: #C4956A;
}

/* Responsive Design - Mobile */
@media (max-width: 768px) {
    .roster-table-wrapper {
        padding: 12px 12px 12px 0;
        margin: 0 -12px;
        border-radius: 0;
    }
    
    .roster-staff-header,
    .roster-staff-cell {
        min-width: 140px;
        width: 140px;
        font-size: 13px;
        padding: 12px 10px 12px 10px;
        vertical-align: middle;
        text-align: left;
    }
    
    .roster-day-header {
        min-width: 70px;
        width: 70px;
        padding: 10px 6px;
    }
    
    .roster-day-cell {
        min-width: 70px;
        width: 70px;
        padding: 6px 4px;
    }
    
    .roster-shift {
        min-height: 36px;
        padding: 6px 4px;
        font-size: 10px;
    }
    
    .shift-label {
        font-size: 10px;
    }
    
    .shift-time {
        font-size: 8px;
    }
    
    .roster-table-month .roster-day-header {
        min-width: 40px;
        width: 40px;
        padding: 6px 2px;
    }
    
    .roster-table-month .roster-day-cell {
        min-width: 40px;
        width: 40px;
        padding: 2px;
    }
    
    .roster-table-month .roster-shift {
        min-height: 20px;
    }
    
    .roster-weekday {
        font-size: 10px;
    }
    
    .roster-daynum {
        font-size: 14px;
    }
}

/* Print Styles */
@media print {
    .roster-table-wrapper {
        overflow: visible;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .roster-staff-header,
    .roster-staff-cell {
        position: static;
    }
}
</style>

<script src="master.js"></script>

<?php
// Include footer
include '../includes/footer.php';
?>
