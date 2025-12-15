# Archive Files Script
# Moves non-essential files to archive folder structure

Write-Host "Starting file archival process..." -ForegroundColor Green

# Create archive directory structure
$archiveBase = "archive"
$folders = @(
    "$archiveBase/old_connections/php",
    "$archiveBase/test_files/admin/includes",
    "$archiveBase/duplicate_apis/api/admin/staff",
    "$archiveBase/duplicate_apis/api/admin/services",
    "$archiveBase/setup_files/admin/includes",
    "$archiveBase/old_scripts",
    "$archiveBase/docs/admin/includes"
)

foreach ($folder in $folders) {
    if (-not (Test-Path $folder)) {
        New-Item -ItemType Directory -Path $folder -Force | Out-Null
        Write-Host "Created directory: $folder" -ForegroundColor Cyan
    }
}

Write-Host "`nMoving files..." -ForegroundColor Yellow

# 1. Old/Replaced Database Connection Files
Write-Host "`n1. Moving old database connection files..." -ForegroundColor Cyan
$oldConnections = @(
    @{ Source = "php/connection.php"; Destination = "$archiveBase/old_connections/php/connection.php" },
    @{ Source = "php/db.php"; Destination = "$archiveBase/old_connections/php/db.php" }
)

foreach ($file in $oldConnections) {
    if (Test-Path $file.Source) {
        Move-Item -Path $file.Source -Destination $file.Destination -Force
        Write-Host "  Moved: $($file.Source) -> $($file.Destination)" -ForegroundColor Green
    } else {
        Write-Host "  Not found: $($file.Source)" -ForegroundColor Yellow
    }
}

# 2. Test/Debug Files
Write-Host "`n2. Moving test/debug files..." -ForegroundColor Cyan
$testFiles = @(
    @{ Source = "test_login_debug.php"; Destination = "$archiveBase/test_files/test_login_debug.php" },
    @{ Source = "admin/test_admin_login.php"; Destination = "$archiveBase/test_files/admin/test_admin_login.php" },
    @{ Source = "admin/debug_login.html"; Destination = "$archiveBase/test_files/admin/debug_login.html" },
    @{ Source = "admin/test-responsive.html"; Destination = "$archiveBase/test_files/admin/test-responsive.html" },
    @{ Source = "admin/test-validation.html"; Destination = "$archiveBase/test_files/admin/test-validation.html" },
    @{ Source = "check_admin.php"; Destination = "$archiveBase/test_files/check_admin.php" },
    @{ Source = "check_table_structure.php"; Destination = "$archiveBase/test_files/check_table_structure.php" },
    @{ Source = "verify_admin.php"; Destination = "$archiveBase/test_files/verify_admin.php" },
    @{ Source = "admin/includes/test_auth.php"; Destination = "$archiveBase/test_files/admin/includes/test_auth.php" }
)

foreach ($file in $testFiles) {
    if (Test-Path $file.Source) {
        $destDir = Split-Path -Path $file.Destination -Parent
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        Move-Item -Path $file.Source -Destination $file.Destination -Force
        Write-Host "  Moved: $($file.Source) -> $($file.Destination)" -ForegroundColor Green
    } else {
        Write-Host "  Not found: $($file.Source)" -ForegroundColor Yellow
    }
}

# 3. Duplicate/Redundant API Files
Write-Host "`n3. Moving duplicate/redundant API files..." -ForegroundColor Cyan
$duplicateApis = @(
    @{ Source = "api/admin/staff/crud.php"; Destination = "$archiveBase/duplicate_apis/api/admin/staff/crud.php" },
    @{ Source = "api/admin/services/crud.php"; Destination = "$archiveBase/duplicate_apis/api/admin/services/crud.php" },
    @{ Source = "api/admin/staff/toggle_active.php"; Destination = "$archiveBase/duplicate_apis/api/admin/staff/toggle_active.php" },
    @{ Source = "api/admin/services/toggle_active.php"; Destination = "$archiveBase/duplicate_apis/api/admin/services/toggle_active.php" }
)

foreach ($file in $duplicateApis) {
    if (Test-Path $file.Source) {
        $destDir = Split-Path -Path $file.Destination -Parent
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        Move-Item -Path $file.Source -Destination $file.Destination -Force
        Write-Host "  Moved: $($file.Source) -> $($file.Destination)" -ForegroundColor Green
    } else {
        Write-Host "  Not found: $($file.Source)" -ForegroundColor Yellow
    }
}

# 4. One-Time Setup Files
Write-Host "`n4. Moving setup files..." -ForegroundColor Cyan
$setupFiles = @(
    @{ Source = "admin/includes/setup_auth.php"; Destination = "$archiveBase/setup_files/admin/includes/setup_auth.php" },
    @{ Source = "admin/includes/setup_auth_tables.sql"; Destination = "$archiveBase/setup_files/admin/includes/setup_auth_tables.sql" },
    @{ Source = "admin/includes/hash_password.php"; Destination = "$archiveBase/setup_files/admin/includes/hash_password.php" },
    @{ Source = "setup_admin_auth.php"; Destination = "$archiveBase/setup_files/setup_admin_auth.php" }
)

foreach ($file in $setupFiles) {
    if (Test-Path $file.Source) {
        $destDir = Split-Path -Path $file.Destination -Parent
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        Move-Item -Path $file.Source -Destination $file.Destination -Force
        Write-Host "  Moved: $($file.Source) -> $($file.Destination)" -ForegroundColor Green
    } else {
        Write-Host "  Not found: $($file.Source)" -ForegroundColor Yellow
    }
}

# 5. Old Scripts
Write-Host "`n5. Moving old scripts..." -ForegroundColor Cyan
$oldScripts = @(
    @{ Source = "send_reminder_script.php"; Destination = "$archiveBase/old_scripts/send_reminder_script.php" }
)

foreach ($file in $oldScripts) {
    if (Test-Path $file.Source) {
        Move-Item -Path $file.Source -Destination $file.Destination -Force
        Write-Host "  Moved: $($file.Source) -> $($file.Destination)" -ForegroundColor Green
    } else {
        Write-Host "  Not found: $($file.Source)" -ForegroundColor Yellow
    }
}

# 6. Documentation Files
Write-Host "`n6. Moving documentation files..." -ForegroundColor Cyan
$docFiles = @(
    @{ Source = "admin/LOGIN_FIX_SUMMARY.md"; Destination = "$archiveBase/docs/admin/LOGIN_FIX_SUMMARY.md" },
    @{ Source = "admin/RESPONSIVE_DESIGN_GUIDE.md"; Destination = "$archiveBase/docs/admin/RESPONSIVE_DESIGN_GUIDE.md" },
    @{ Source = "admin/UI_IMPROVEMENTS_SUMMARY.md"; Destination = "$archiveBase/docs/admin/UI_IMPROVEMENTS_SUMMARY.md" },
    @{ Source = "admin/includes/AUTH_SETUP_README.md"; Destination = "$archiveBase/docs/admin/includes/AUTH_SETUP_README.md" },
    @{ Source = "admin/includes/ERROR_HANDLING_GUIDE.md"; Destination = "$archiveBase/docs/admin/includes/ERROR_HANDLING_GUIDE.md" },
    @{ Source = "admin/includes/IMPLEMENTATION_SUMMARY.md"; Destination = "$archiveBase/docs/admin/includes/IMPLEMENTATION_SUMMARY.md" },
    @{ Source = "admin/includes/PERFORMANCE_SECURITY_GUIDE.md"; Destination = "$archiveBase/docs/admin/includes/PERFORMANCE_SECURITY_GUIDE.md" }
)

foreach ($file in $docFiles) {
    if (Test-Path $file.Source) {
        $destDir = Split-Path -Path $file.Destination -Parent
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        Move-Item -Path $file.Source -Destination $file.Destination -Force
        Write-Host "  Moved: $($file.Source) -> $($file.Destination)" -ForegroundColor Green
    } else {
        Write-Host "  Not found: $($file.Source)" -ForegroundColor Yellow
    }
}

Write-Host "`nArchival process completed!" -ForegroundColor Green
Write-Host "All files have been moved to the 'archive' folder." -ForegroundColor Green
Write-Host "`nArchive structure:" -ForegroundColor Cyan
Get-ChildItem -Path $archiveBase -Recurse -Directory | ForEach-Object {
    Write-Host "  $($_.FullName.Replace((Get-Location).Path + '\', ''))" -ForegroundColor White
}


