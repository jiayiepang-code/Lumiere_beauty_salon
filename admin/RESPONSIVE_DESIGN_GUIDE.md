# Responsive Design Implementation Guide

## Overview

This document describes the responsive design implementation for the Lumière Beauty Salon Admin Module, ensuring optimal user experience across desktop, tablet, and mobile devices.

## Breakpoints

### Mobile First Approach

The design follows a mobile-first approach with the following breakpoints:

- **Small Mobile**: 320px - 374px
- **Mobile**: 375px - 768px
- **Tablet**: 769px - 1023px
- **Desktop**: 1024px and above

## Key Features

### 1. Mobile Navigation

- **Hamburger Menu**: Accessible button with animated icon
- **Slide-out Sidebar**: Smooth transition with overlay
- **Touch-friendly**: All navigation items are minimum 44x44px
- **Auto-close**: Sidebar closes when clicking outside

### 2. Table to Card Conversion

On mobile devices (≤768px), all data tables automatically convert to card layouts:

```html
<td data-label="Service Name">Haircut</td>
```

The `data-label` attribute creates labels for each field in the card view.

### 3. Touch Target Optimization

All interactive elements meet the minimum 44x44px touch target size:

- Buttons
- Links
- Form inputs
- Toggle switches
- Action icons

### 4. Form Optimization

- **Stacked Layout**: Form rows stack vertically on mobile
- **Full Width Inputs**: All inputs expand to full width
- **16px Font Size**: Prevents iOS zoom on focus
- **Larger Touch Targets**: Checkboxes and radio buttons are 20x20px with 12px margin

### 5. Modal Improvements

- **Bottom Sheet Style**: Modals slide up from bottom on mobile
- **Full Screen**: Modals take full width on mobile
- **Swipe Gesture**: Visual indication for dismissal
- **Stacked Actions**: Buttons stack vertically

### 6. Calendar Optimization

- **Day View Only**: Mobile shows only day view (week/month hidden)
- **Simplified Layout**: Reduced time slot width (60px)
- **Touch-friendly Cards**: Booking cards are easier to tap
- **Stacked Filters**: All filters stack vertically

## CSS Files

### admin-style.css

Main stylesheet with responsive breakpoints integrated.

### responsive-mobile.css (Optional Enhancement)

Additional mobile-specific utilities and enhancements:

- Mobile utility classes
- Enhanced touch targets
- Mobile card layouts
- Accessibility improvements
- Performance optimizations

## Testing

### Test File

Use `admin/test-responsive.html` to verify responsive behavior:

```bash
# Open in browser
http://localhost/admin/test-responsive.html
```

### Testing Checklist

#### Mobile (≤768px)

- [ ] Hamburger menu appears and functions
- [ ] Sidebar slides in/out smoothly
- [ ] Tables convert to card layout
- [ ] All buttons are full width
- [ ] Forms stack vertically
- [ ] Modals slide from bottom
- [ ] Touch targets are minimum 44x44px
- [ ] Calendar shows day view only
- [ ] No horizontal scrolling

#### Tablet (769px - 1023px)

- [ ] Sidebar remains visible
- [ ] Tables remain in table format
- [ ] Forms use 2-column layout
- [ ] Calendar shows week view
- [ ] Content is properly spaced

#### Desktop (≥1024px)

- [ ] Full sidebar navigation
- [ ] Multi-column layouts
- [ ] Hover states work properly
- [ ] All features accessible

### Browser Testing

Test on the following browsers:

- Chrome (latest)
- Firefox (latest)
- Edge (latest)
- Safari (latest on macOS/iOS)

### Device Testing

Test on the following devices:

- iPhone (various models)
- Android phones (various models)
- iPad
- Android tablets

## Implementation Details

### Adding Responsive Tables

To make a table responsive, add `data-label` attributes:

```javascript
<td data-label="Service Name">${serviceName}</td>
<td data-label="Price">RM ${price}</td>
<td data-label="Actions">
    <button class="btn-icon">Edit</button>
</td>
```

### Mobile-Specific Styles

Use media queries for mobile-specific styles:

```css
@media (max-width: 768px) {
  .your-element {
    /* Mobile styles */
  }
}
```

### Touch Target Guidelines

Ensure all interactive elements meet minimum size:

```css
.btn-icon {
  min-width: 44px;
  min-height: 44px;
  padding: 10px;
}
```

### Preventing iOS Zoom

Use 16px font size for inputs:

```css
input,
select,
textarea {
  font-size: 16px !important;
}
```

## Accessibility

### ARIA Labels

All interactive elements include proper ARIA labels:

```html
<button aria-label="Edit service" class="btn-icon">
  <svg>...</svg>
</button>
```

### Keyboard Navigation

- All interactive elements are keyboard accessible
- Focus states are clearly visible
- Tab order is logical

### Screen Reader Support

- Semantic HTML structure
- Proper heading hierarchy
- Descriptive labels for form inputs

## Performance

### Mobile Optimizations

- Reduced animations on mobile
- Optimized images
- Minimal JavaScript
- CSS-only transitions
- Hardware-accelerated transforms

### Loading States

- Skeleton loaders for better perceived performance
- Progressive enhancement
- Lazy loading for images

## Common Issues and Solutions

### Issue: Horizontal Scrolling on Mobile

**Solution**: Ensure all elements have max-width: 100%

```css
body,
.admin-layout {
  overflow-x: hidden;
}
```

### Issue: iOS Input Zoom

**Solution**: Use 16px font size for inputs

```css
input {
  font-size: 16px !important;
}
```

### Issue: Sidebar Not Closing

**Solution**: Check click outside handler

```javascript
document.addEventListener("click", (e) => {
  if (window.innerWidth <= 768) {
    if (!sidebar.contains(e.target) && !hamburgerBtn.contains(e.target)) {
      sidebar.classList.remove("active");
    }
  }
});
```

### Issue: Touch Targets Too Small

**Solution**: Increase padding and min-width/height

```css
.btn-icon {
  min-width: 44px;
  min-height: 44px;
}
```

## Best Practices

### 1. Mobile First

Start with mobile styles and enhance for larger screens.

### 2. Progressive Enhancement

Ensure core functionality works without JavaScript.

### 3. Touch-Friendly

All interactive elements should be easy to tap.

### 4. Performance

Minimize animations and optimize assets for mobile.

### 5. Testing

Test on real devices, not just browser emulators.

### 6. Accessibility

Ensure all users can access and use the interface.

## Future Enhancements

### Planned Improvements

- [ ] Swipe gestures for navigation
- [ ] Pull-to-refresh functionality
- [ ] Offline support with service workers
- [ ] Native app-like experience (PWA)
- [ ] Dark mode support
- [ ] Landscape mode optimizations

## Resources

### Documentation

- [MDN Responsive Design](https://developer.mozilla.org/en-US/docs/Learn/CSS/CSS_layout/Responsive_Design)
- [Web.dev Mobile UX](https://web.dev/mobile-ux/)
- [Apple Human Interface Guidelines](https://developer.apple.com/design/human-interface-guidelines/)
- [Material Design](https://material.io/design)

### Tools

- Chrome DevTools Device Mode
- Firefox Responsive Design Mode
- BrowserStack for cross-browser testing
- Lighthouse for performance auditing

## Support

For issues or questions about responsive design implementation, refer to:

- Design document: `.kiro/specs/admin-module/design.md`
- Requirements: `.kiro/specs/admin-module/requirements.md`
- Tasks: `.kiro/specs/admin-module/tasks.md`
