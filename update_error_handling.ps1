# PowerShell script to update error handling in all API endpoints

$files = @(
    "api/admin/services/update.php",
    "api/admin/services/delete.php",
    "api/admin/services/toggle_active.php",
    "api/admin/services/list.php",
    "api/admin/staff/update.php",
    "api/admin/staff/delete.php",
    "api/admin/staff/toggle_active.php",
    "api/admin/staff/list.php",
    "api/admin/bookings/details.php",
    "api/admin/analytics/idle_hours.php",
    "api/admin/analytics/booking_trends.php"
)

foreach ($file in $files) {
    Write-Host "Updating $file..."
    
    $content = Get-Content $file -Raw
    
    # Add error handler include if not present
    if ($content -notmatch "require_once.*error_handler\.php") {
        $content = $content -replace "(require_once.*auth_check\.php';)", "`$1`nrequire_once '../../../admin/includes/error_handler.php';"
    }
    
    # Replace authentication checks
    $content = $content -replace "if \(!isAdminAuthenticated\(\)\) \{[^}]+\}", "if (!isAdminAuthenticated()) {`n    ErrorHandler::handleAuthError();`n}"
    
    # Replace session timeout checks
    $content = $content -replace "if \(!checkSessionTimeout\(\)\) \{[^}]+\}", "if (!checkSessionTimeout()) {`n    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);`n}"
    
    # Replace method not allowed checks
    $content = $content -replace "if \(\$_SERVER\['REQUEST_METHOD'\] !== '(GET|POST|PUT|DELETE)'\) \{[^}]+\}", "if (`$_SERVER['REQUEST_METHOD'] !== '`$1') {`n    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only `$1 requests are allowed', null, 405);`n}"
    
    # Replace catch blocks
    $oldCatch = "} catch \(Exception \`$e\) \{[^}]+error_log\(json_encode\(\[[^\]]+\]\), 3, '[^']+'\);[^}]+http_response_code\(500\);[^}]+echo json_encode\(\[[^\]]+\]\);[^}]+\}"
    $content = $content -replace $oldCatch, "} catch (Exception `$e) {`n    ErrorHandler::handleDatabaseError(`$e, 'database operation');`n}"
    
    Set-Content -Path $file -Value $content
}

Write-Host "Error handling update complete!"
