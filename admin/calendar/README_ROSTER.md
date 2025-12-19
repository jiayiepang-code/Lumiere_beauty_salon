# Staff Roster Table - Redesign Documentation

## Overview

The Staff Roster view has been redesigned with clean semantic HTML structure, reusable CSS, and improved responsive behavior for both Weekly and Monthly schedule views.

## Features

### ✅ Semantic HTML Structure
- Uses proper `<table>`, `<thead>`, `<tbody>`, `<th>`, and `<td>` elements
- Includes ARIA labels for accessibility
- Proper scope attributes for screen readers

### ✅ Responsive Design
- **Desktop**: Full table display with all columns visible
- **Mobile**: Horizontal scroll with fixed staff names column
- Smooth scrolling with `-webkit-overflow-scrolling: touch`

### ✅ Reusable CSS
- CSS Grid/Table-based layout
- Modular class structure
- Easy to customize colors and spacing

## File Structure

```
admin/calendar/
├── master.php          # Main PHP file with inline styles
├── master.js           # JavaScript rendering functions
├── StaffRosterTable.tsx        # React component (optional)
├── StaffRosterTable.module.css # CSS module for React (optional)
└── README_ROSTER.md    # This file
```

## Usage

### Vanilla JavaScript (Current Implementation)

The roster is automatically rendered when switching between Day/Week/Month views in the calendar. The JavaScript functions `renderStaffRosterWeek()` and `renderStaffRosterMonth()` handle the rendering.

### React/Next.js (Optional)

If you want to use the React component version:

```tsx
import StaffRosterTable from './StaffRosterTable';
import styles from './StaffRosterTable.module.css';

// In your component
<StaffRosterTable
  view="week" // or "month"
  staffMatrix={staffMatrix}
  dates={dateObjects}
  dateKeys={dateKeys}
/>
```

## CSS Classes

### Main Container
- `.roster-table-wrapper` - Wrapper with scroll behavior
- `.roster-table` - Main table element
- `.roster-table-week` - Weekly view modifier
- `.roster-table-month` - Monthly view modifier

### Header
- `.roster-staff-header` - Staff name column header (sticky on mobile)
- `.roster-day-header` - Day column header
- `.roster-weekday` - Weekday label
- `.roster-daynum` - Day number

### Body
- `.roster-staff-cell` - Staff name cell (sticky on mobile)
- `.roster-day-cell` - Day cell
- `.roster-shift` - Shift block container

### Shift Types
- `.shift-full` - Full day shift (green)
- `.shift-morning` - Morning shift (blue)
- `.shift-afternoon` - Afternoon shift (orange)
- `.shift-off` - Off duty (gray)
- `.shift-with-client` - With customer (brown)
- `.shift-leave` - On leave (red)

## Responsive Breakpoints

- **Desktop (> 768px)**: Full table, all columns visible
- **Mobile (≤ 768px)**: 
  - Horizontal scroll enabled
  - Staff names column fixed (sticky)
  - Reduced cell sizes
  - Compact monthly view blocks

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers with touch scrolling support
- Print-friendly styles included

## Customization

### Changing Colors

Edit the shift type colors in the CSS:

```css
.roster-shift.shift-full {
  background: #4CAF50; /* Change to your color */
  color: #ffffff;
}
```

### Adjusting Column Widths

Modify the `min-width` and `width` properties:

```css
.roster-staff-header {
  min-width: 180px; /* Adjust as needed */
  width: 180px;
}
```

### Mobile Breakpoint

Change the breakpoint in the media query:

```css
@media (max-width: 768px) {
  /* Mobile styles */
}
```

## Accessibility

- Semantic HTML structure
- ARIA labels on table
- Proper scope attributes
- Keyboard navigation support
- Screen reader friendly

## Performance

- Efficient table rendering
- CSS transforms for smooth scrolling
- Minimal reflows on resize
- Optimized for large datasets

## Future Enhancements

- [ ] Add drag-and-drop for shift editing
- [ ] Export to PDF functionality
- [ ] Print optimization
- [ ] Dark mode support
- [ ] Shift tooltips with details
- [ ] Filter by staff member
- [ ] Search functionality

