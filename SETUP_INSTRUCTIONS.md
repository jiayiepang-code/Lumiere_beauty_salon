# Quick Setup Instructions for Admin Authentication

## Step 1: Run the Setup Script

1. Make sure XAMPP is running (Apache and MySQL)
2. Open your browser and go to:
   ```
   http://localhost/Lumiere-beauty-salon/setup_admin_auth.php
   ```
3. The script will automatically:
   - Create the `Login_Attempts` table
   - Create the `Admin_Login_Log` table
   - Update the `Staff` table password column
   - Create/update the default admin account

## Step 2: Test the Login

1. After successful setup, go to:

   ```
   http://localhost/Lumiere-beauty-salon/admin/login.html
   ```

2. Enter the default credentials:
   - **Phone:** `12 345 6789` (or `60123456789`)
   - **Password:** `Admin@123`

3. Click "ADMIN LOGIN"

4. You should be redirected to the admin dashboard

## Step 3: Security

After successful login:

1. **Delete the setup file** for security:
   - Delete `setup_admin_auth.php` from your root directory

2. **Change the default password** using the hash utility:
   - Go to: `http://localhost/Lumiere-beauty-salon/admin/includes/hash_password.php`
   - Enter your new password
   - Copy the generated hash
   - Update the database:
     ```sql
     UPDATE Staff
     SET password = 'your_generated_hash'
     WHERE staff_email = 'admin@lumiere.com';
     ```

## Troubleshooting

### If login doesn't work:

1. **Check if tables were created:**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Select database: `sql12810487`
   - Verify these tables exist:
     - `Login_Attempts`
     - `Admin_Login_Log`
     - `Staff`

2. **Check if admin account exists:**

   ```sql
   SELECT * FROM Staff WHERE phone = '60123456789';
   ```

3. **Check browser console for errors:**
   - Press F12 in your browser
   - Go to Console tab
   - Look for any error messages

4. **Verify file paths:**
   - Make sure all files are in the correct locations
   - Check that `api/admin/auth/login.php` exists

### Common Issues:

**"404 Not Found" error:**

- Make sure you're accessing the correct URL
- Verify XAMPP is running
- Check that files are in the `htdocs/Lumiere-beauty-salon` directory

**"Connection failed" error:**

- Check MySQL is running in XAMPP
- Verify database name is `sql12810487`
- Check credentials in `php/connection.php`

**"Invalid credentials" error:**

- Make sure you're using the correct phone format
- Try both `12 345 6789` and `60123456789`
- Verify password is exactly `Admin@123` (case-sensitive)

**"Too many login attempts" error:**

- Wait 15 minutes, or
- Clear attempts manually in phpMyAdmin:
  ```sql
  DELETE FROM Login_Attempts WHERE phone = '60123456789';
  ```

## File Structure

Your project should have these files:

```
Lumiere-beauty-salon/
├── setup_admin_auth.php          ← Run this first
├── admin/
│   ├── login.html                ← Login page
│   ├── login.js                  ← Login logic
│   ├── index.php                 ← Dashboard (protected)
│   └── includes/
│       ├── auth_check.php        ← Session management
│       ├── hash_password.php     ← Password utility
│       └── test_auth.php         ← Test script
├── api/
│   └── admin/
│       ├── auth/
│       │   ├── login.php         ← Login API
│       │   └── logout.php        ← Logout API
│       └── includes/
│           └── csrf_validation.php
└── php/
    └── connection.php            ← Database config
```

## Next Steps After Login

Once you successfully log in:

1. Explore the admin dashboard
2. Change the default password
3. Delete the setup script
4. Start implementing other admin features (Service Management, Staff Management, etc.)

## Need Help?

If you encounter any issues:

1. Check the browser console (F12)
2. Check PHP error logs in XAMPP
3. Verify all files are in the correct locations
4. Make sure database tables were created successfully
