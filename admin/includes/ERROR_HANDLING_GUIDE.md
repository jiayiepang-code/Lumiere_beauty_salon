# Error Handling & Validation Guide

## Overview

The Admin Module implements a comprehensive error handling and validation system with consistent error responses, server-side validation, client-side validation, and centralized error logging.

## Components

### 1. ErrorHandler Class (`error_handler.php`)

Centralized error handling with consistent response format and logging.

#### Error Codes

- `AUTH_REQUIRED` - Authentication required
- `AUTH_FAILED` - Invalid credentials
- `SESSION_EXPIRED` - Session has expired
- `PERMISSION_DENIED` - Insufficient privileges
- `VALIDATION_ERROR` - Input validation failed
- `NOT_FOUND` - Resource not found
- `DUPLICATE_ENTRY` - Unique constraint violation
- `DATABASE_ERROR` - Database operation failed
- `FILE_UPLOAD_ERROR` - Image upload failed
- `RATE_LIMIT_EXCEEDED` - Too many requests
- `INVALID_JSON` - Invalid JSON data
- `INVALID_CSRF_TOKEN` - Invalid CSRF token
- `METHOD_NOT_ALLOWED` - HTTP method not allowed
- `ACCOUNT_INACTIVE` - Account is inactive

#### Usage Examples

```php
// Include the error handler
require_once '../../../admin/includes/error_handler.php';

// Handle authentication error
if (!isAdminAuthenticated()) {
    ErrorHandler::handleAuthError();
}

// Handle validation errors
$errors = validateData($input);
if (!empty($errors)) {
    ErrorHandler::handleValidationError($errors);
}

// Handle database errors
try {
    // Database operations
} catch (Exception $e) {
    ErrorHandler::handleDatabaseError($e, 'operation description');
}

// Handle duplicate entry
ErrorHandler::handleDuplicateEntry('email', 'Email already exists');

// Handle not found
ErrorHandler::handleNotFound('Service');

// Send custom error
ErrorHandler::sendError(
    ErrorHandler::VALIDATION_ERROR,
    'Invalid input',
    ['field' => 'error message'],
    400
);
```

#### Error Response Format

All errors return a consistent JSON format:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid input data",
    "details": {
      "field_name": "Error message for this field"
    }
  }
}
```

### 2. Validator Class (`validator.php`)

Centralized validation library with reusable validation functions.

#### Available Validation Methods

- `required($value, $fieldName)` - Check if field is not empty
- `email($email)` - Validate email format
- `length($value, $min, $max, $fieldName)` - Validate string length
- `range($value, $min, $max, $fieldName)` - Validate numeric range
- `phoneNumber($phone)` - Validate Malaysia phone format
- `passwordStrength($password)` - Validate password strength
- `enum($value, $allowedValues, $fieldName)` - Validate enum value
- `fileUpload($file, $allowedTypes, $maxSize)` - Validate file upload
- `date($date, $format)` - Validate date format
- `time($time, $format)` - Validate time format
- `decimal($value, $precision, $scale, $fieldName)` - Validate decimal number
- `sanitize($value)` - Sanitize string for output

#### Usage Examples

```php
// Include the validator
require_once '../../../admin/includes/validator.php';

// Define validation rules
$rules = [
    'service_name' => [
        'required' => true,
        'length' => ['min' => null, 'max' => 100]
    ],
    'current_price' => [
        'required' => true,
        'range' => ['min' => 0.01, 'max' => 99999999.99]
    ],
    'staff_email' => [
        'required' => true,
        'email' => true
    ],
    'phone' => [
        'required' => true,
        'phone' => true
    ],
    'password' => [
        'required' => true,
        'password' => true
    ],
    'role' => [
        'required' => true,
        'enum' => ['values' => ['staff', 'admin']]
    ]
];

// Validate data
$validation = Validator::validate($input, $rules);

if (!$validation['valid']) {
    ErrorHandler::handleValidationError($validation['errors']);
}

// Individual validation
$error = Validator::required($value, 'Field name');
if ($error) {
    // Handle error
}

// Sanitize output
$safe_output = Validator::sanitize($user_input);
```

### 3. Client-Side Validation (`validation.js`)

JavaScript validation library for real-time form validation.

#### Usage Examples

```javascript
// Include the validation script
<script src="/admin/js/validation.js"></script>;

// Define validation rules
const rules = {
  service_name: {
    required: { label: "Service name" },
    length: { min: null, max: 100, label: "Service name" },
  },
  current_price: {
    required: { label: "Price" },
    range: { min: 0.01, max: 99999999.99, label: "Price" },
  },
  staff_email: {
    required: { label: "Email" },
    email: true,
  },
  phone: {
    required: { label: "Phone" },
    phone: true,
  },
  password: {
    required: { label: "Password" },
    password: true,
  },
};

// Validate form data
const formData = {
  service_name: document.getElementById("service_name").value,
  current_price: document.getElementById("current_price").value,
};

const validation = Validation.validateForm(formData, rules);

if (!validation.valid) {
  Validation.showErrors(validation.errors);
  return false;
}

// Add real-time validation to field
Validation.addFieldValidation("email", (value) => {
  return Validation.email(value);
});

// Handle API error response
fetch("/api/admin/services/create.php", {
  method: "POST",
  body: JSON.stringify(data),
})
  .then((response) => response.json())
  .then((data) => {
    if (!data.success) {
      Validation.handleApiError(data.error, "myForm");
    } else {
      Validation.showSuccess("Service created successfully!", "myForm");
    }
  });

// Clear all errors
Validation.clearAllErrors("myForm");

// Show/clear individual error
Validation.showError("field_id", "Error message");
Validation.clearError("field_id");
```

## Error Styling

Error styles are defined in `admin/css/admin-style.css`:

### CSS Classes

- `.error` - Applied to input fields with errors (red border)
- `.error-message` - Error message text below fields
- `.alert-error` - Error alert container
- `.alert-success` - Success alert container
- `.alert-warning` - Warning alert container
- `.alert-info` - Info alert container

### HTML Structure

```html
<!-- Form with error container -->
<form id="myForm">
  <div id="error-container"></div>

  <div class="form-group">
    <label for="service_name"
      >Service Name <span class="required-indicator">*</span></label
    >
    <input type="text" id="service_name" name="service_name" />
    <!-- Error message will be inserted here by validation.js -->
  </div>

  <button type="submit">Submit</button>
</form>
```

## Error Logging

All errors are logged to `logs/admin_errors.log` with the following information:

```json
{
  "timestamp": "2024-12-08 10:30:00",
  "user": "admin@lumiere.com",
  "message": "Error message",
  "file": "/path/to/file.php",
  "line": 123,
  "trace": "Stack trace...",
  "context": {
    "operation": "service creation"
  }
}
```

### Log File Location

- Development: `logs/admin_errors.log`
- The logs directory is automatically created if it doesn't exist

### Viewing Logs

```bash
# View recent errors
tail -f logs/admin_errors.log

# Search for specific errors
grep "VALIDATION_ERROR" logs/admin_errors.log

# View errors for specific user
grep "admin@lumiere.com" logs/admin_errors.log
```

## API Endpoint Template

Here's a template for implementing error handling in API endpoints:

```php
<?php
// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
session_start();

header('Content-Type: application/json');

// Include required files
require_once '../../../php/connection.php';
require_once '../../../admin/includes/auth_check.php';
require_once '../../../admin/includes/error_handler.php';
require_once '../../../admin/includes/validator.php';

// Check authentication
if (!isAdminAuthenticated()) {
    ErrorHandler::handleAuthError();
}

// Check session timeout
if (!checkSessionTimeout()) {
    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);
}

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only POST requests are allowed', null, 405);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        ErrorHandler::sendError(ErrorHandler::INVALID_JSON, 'Invalid JSON data');
    }

    // Validate CSRF token
    if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        ErrorHandler::sendError(ErrorHandler::INVALID_CSRF_TOKEN, 'Invalid CSRF token', null, 403);
    }

    // Define validation rules
    $rules = [
        'field_name' => [
            'required' => true,
            'length' => ['min' => null, 'max' => 100]
        ]
    ];

    // Validate input data
    $validation = Validator::validate($input, $rules);

    if (!$validation['valid']) {
        ErrorHandler::handleValidationError($validation['errors']);
    }

    // Perform database operations
    // ...

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Operation completed successfully'
    ]);

} catch (Exception $e) {
    ErrorHandler::handleDatabaseError($e, 'operation description');
}
```

## Best Practices

1. **Always validate on server-side** - Never trust client-side validation alone
2. **Use prepared statements** - Prevent SQL injection
3. **Sanitize output** - Use `Validator::sanitize()` or `htmlspecialchars()`
4. **Log all errors** - Use ErrorHandler for consistent logging
5. **Return consistent responses** - Use ErrorHandler methods
6. **Provide specific error messages** - Help users understand what went wrong
7. **Don't expose sensitive information** - Generic messages for security errors
8. **Validate CSRF tokens** - Prevent cross-site request forgery
9. **Check authentication and session** - On every protected endpoint
10. **Use appropriate HTTP status codes** - 400 for validation, 401 for auth, 500 for server errors

## Testing Error Handling

### Test Cases

1. **Authentication Errors**

   - Access endpoint without authentication
   - Access with expired session
   - Access with invalid credentials

2. **Validation Errors**

   - Submit form with missing required fields
   - Submit form with invalid data types
   - Submit form with out-of-range values
   - Submit form with invalid formats

3. **Database Errors**

   - Duplicate entry (unique constraint)
   - Foreign key constraint violation
   - Connection failure

4. **File Upload Errors**

   - Invalid file type
   - File size exceeds limit
   - No file uploaded

5. **CSRF Errors**
   - Submit form without CSRF token
   - Submit form with invalid CSRF token

### Example Test Script

```javascript
// Test validation error
fetch("/api/admin/services/create.php", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    csrf_token: "valid_token",
    service_name: "", // Missing required field
    current_price: -10, // Invalid value
  }),
})
  .then((response) => response.json())
  .then((data) => {
    console.log("Validation Error Response:", data);
    // Expected: { success: false, error: { code: 'VALIDATION_ERROR', ... } }
  });

// Test authentication error
fetch("/api/admin/services/list.php")
  .then((response) => response.json())
  .then((data) => {
    console.log("Auth Error Response:", data);
    // Expected: { success: false, error: { code: 'AUTH_REQUIRED', ... } }
  });
```

## Troubleshooting

### Common Issues

1. **Errors not logging**

   - Check logs directory exists and is writable
   - Verify error_log path in ErrorHandler::$log_file

2. **Validation not working**

   - Ensure Validator class is included
   - Check validation rules syntax
   - Verify field names match input data

3. **Client-side validation not displaying**

   - Check validation.js is loaded
   - Verify field IDs match
   - Check browser console for JavaScript errors
   - Ensure CSS styles are loaded

4. **CSRF token errors**
   - Verify CSRF token is generated in session
   - Check token is included in form submission
   - Ensure validateCSRFToken() function exists

## Security Considerations

1. **Never expose stack traces** - Log them server-side only
2. **Sanitize all user input** - Before storing or displaying
3. **Use HTTPS in production** - Protect data in transit
4. **Implement rate limiting** - Prevent brute force attacks
5. **Validate file uploads** - Check type, size, and content
6. **Use secure session settings** - httponly, secure flags
7. **Hash passwords** - Use password_hash() with PASSWORD_BCRYPT
8. **Validate CSRF tokens** - On all state-changing operations
9. **Log security events** - Track failed login attempts
10. **Keep error messages generic** - Don't reveal system details

## Maintenance

### Regular Tasks

1. **Monitor error logs** - Check daily for recurring issues
2. **Rotate log files** - Prevent logs from growing too large
3. **Review validation rules** - Update as requirements change
4. **Test error handling** - After code changes
5. **Update error messages** - Keep them user-friendly and accurate

### Log Rotation

```bash
# Rotate logs monthly
mv logs/admin_errors.log logs/admin_errors_$(date +%Y%m).log
touch logs/admin_errors.log
chmod 644 logs/admin_errors.log
```

## Support

For issues or questions about error handling:

1. Check this guide first
2. Review error logs for details
3. Test with browser developer tools
4. Check PHP error logs
5. Verify database connectivity
