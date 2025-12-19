/**
 * Staff Roster Table Component
 * 
 * A reusable React component for displaying staff schedules in Weekly and Monthly views.
 * Features:
 * - Semantic HTML table structure
 * - Responsive design with fixed staff names on mobile
 * - Horizontal scroll on mobile devices
 * - Accessible with proper ARIA labels
 * 
 * Usage:
 * ```tsx
 * <StaffRosterTable
 *   view="week" // or "month"
 *   staffMatrix={staffMatrix}
 *   dates={dateObjects}
 *   dateKeys={dateKeys}
 * />
 * ```
 */

import React from 'react';

interface ShiftInfo {
  type: 'full' | 'morning' | 'afternoon' | 'off' | 'leave' | 'with-client';
  label: string;
  timeLabel?: string;
}

interface StaffEntry {
  staff_email: string;
  staff_name: string;
  role?: string;
  days: Record<string, ShiftInfo>;
}

interface StaffRosterTableProps {
  view: 'week' | 'month';
  staffMatrix: Record<string, StaffEntry>;
  dates: Date[];
  dateKeys: string[];
}

const StaffRosterTable: React.FC<StaffRosterTableProps> = ({
  view,
  staffMatrix,
  dates,
  dateKeys,
}) => {
  const staffEntries = Object.values(staffMatrix);

  if (staffEntries.length === 0) {
    return (
      <div className="roster-empty-state">
        No staff roster data found for this {view === 'week' ? 'week' : 'month'}
      </div>
    );
  }

  const isToday = (date: Date): boolean => {
    const today = new Date();
    return date.toDateString() === today.toDateString();
  };

  const formatWeekday = (date: Date): string => {
    return date.toLocaleDateString('en-US', { weekday: 'short' });
  };

  const getShiftClass = (info: ShiftInfo): string => {
    const classMap = {
      full: 'shift-full',
      morning: 'shift-morning',
      afternoon: 'shift-afternoon',
      'with-client': 'shift-with-client',
      leave: 'shift-leave',
      off: 'shift-off',
    };
    return classMap[info.type] || 'shift-off';
  };

  const getShiftTitle = (info: ShiftInfo): string => {
    return info.timeLabel ? `${info.label} - ${info.timeLabel}` : info.label;
  };

  return (
    <div className="roster-table-wrapper">
      <table
        className={`roster-table roster-table-${view}`}
        role="table"
        aria-label={`${view === 'week' ? 'Weekly' : 'Monthly'} Staff Roster`}
      >
        <thead>
          <tr>
            <th className="roster-staff-header" scope="col">
              Staff Member
            </th>
            {dates.map((date, idx) => (
              <th
                key={dateKeys[idx]}
                className={`roster-day-header${isToday(date) ? ' today' : ''}`}
                scope="col"
              >
                <span className="roster-weekday">
                  {view === 'week' ? formatWeekday(date) : formatWeekday(date).slice(0, 1)}
                </span>
                <span className="roster-daynum">{date.getDate()}</span>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {staffEntries.map((staff) => (
            <tr key={staff.staff_email}>
              <td className="roster-staff-cell" scope="row">
                {staff.staff_name}
              </td>
              {dateKeys.map((key, idx) => {
                const date = dates[idx];
                const info = staff.days[key] || { type: 'off', label: 'Off' };
                const shiftClass = getShiftClass(info);
                const shiftTitle = getShiftTitle(info);

                return (
                  <td
                    key={key}
                    className={`roster-day-cell${isToday(date) ? ' today' : ''}`}
                  >
                    <div
                      className={`roster-shift ${shiftClass}`}
                      title={shiftTitle}
                    >
                      {view === 'week' && (
                        <>
                          <span className="shift-label">{info.label}</span>
                          {info.timeLabel && (
                            <span className="shift-time">{info.timeLabel}</span>
                          )}
                        </>
                      )}
                    </div>
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default StaffRosterTable;



