# Installation & Setup Guide

## System Requirements

- **PHP**: 8.2+
- **MySQL**: 5.7+
- **Server**: Apache with mod_rewrite enabled
- **Disk Space**: 500 MB (including libraries)
- **RAM**: 1 GB minimum

---

## 1. Fresh Installation

### Windows (XAMPP)

```bash
1. Download XAMPP from https://www.apachefriends.org/
2. Install to C:\xampp
3. Extract application to C:\xampp\htdocs
4. Start Apache and MySQL from XAMPP Control Panel
5. Open phpMyAdmin: http://localhost/phpmyadmin
6. Import database schema (see Database Setup below)
```

### Linux

```bash
1. Install Apache, PHP 8.2, MySQL:
   sudo apt-get install apache2 php mysql-server libapache2-mod-php

2. Enable mod_rewrite:
   sudo a2enmod rewrite

3. Copy files to /var/www/html:
   sudo cp -r . /var/www/html/

4. Set permissions:
   sudo chown -R www-data:www-data /var/www/html

5. Restart Apache:
   sudo systemctl restart apache2
```

---

## 2. Database Setup

### Method 1: phpMyAdmin (Easiest)

```
1. Open http://localhost/phpmyadmin
2. Click "Import" tab
3. Select file: schema.sql
4. Click "Execute"
5. Done! ✅
```

### Method 2: MySQL CLI

```bash
mysql -u root -p < schema.sql
```

### Method 3: Automated Setup (Windows)

```bash
# Run the setup script
OFFLINE_SETUP.bat

# Follow prompts and enter:
# - MySQL root password
# - Database name (default: bel_exam_portal)
```

---

## 3. Configuration

### Edit `includes/config.php`

```php
// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');  // Change this!
define('DB_NAME', 'bel_exam_portal');

// Application URL
define('APP_URL', 'http://localhost');

// File upload path
define('UPLOAD_DIR', '/uploads/');
```

---

## 4. Initial Login

After setup, access:

```
Admin Panel:    http://localhost/admin/login.php
Email:          admin@belkotdwar.in
Password:       Admin@123
```

### First Time Setup Steps:
1. ✅ Login with above credentials
2. ✅ Change password (Security > Settings)
3. ✅ Create exams (Admin > Exams)
4. ✅ Import students (Admin > Students > Bulk Upload)
5. ✅ Add questions (Admin > Questions > Bulk Upload)
6. ✅ Assign exams to students
7. ✅ Generate admit cards (QR tickets)

---

## 5. Offline/Intranet Deployment

### Step 1: Verify Offline Files

```
assets/lib/
├── bootstrap/          ✓ Downloaded
├── fontawesome/        ✓ Downloaded
├── qrcode/            ✓ Downloaded
└── setup.php          ✓ Verification script
```

### Step 2: Test Offline

1. Disconnect from internet (or test on isolated network)
2. Open http://localhost
3. Verify all pages load correctly
4. Check that all icons and styling display
5. Generate QR code (create admit card)

### Step 3: Deploy to Intranet

```bash
# Copy to intranet server
scp -r . user@intranet-server:/var/www/html

# Or use Windows file sharing
\\intranet-server\www\html
```

---

## 6. File Permissions

### Linux

```bash
# Make uploads writable
chmod 755 uploads/
chmod 755 uploads/photos/

# If needed, make QR cache writable
mkdir -p uploads/.qrcache
chmod 777 uploads/.qrcache
```

### Windows

```
Right-click uploads/ → Properties
→ Security → Edit → Select Users → Full Control → Apply
```

---

## 7. Verify Installation

Open: `http://localhost/OFFLINE_TEST.php`

This will check:
- ✓ PHP version
- ✓ MySQL connection
- ✓ Required extensions
- ✓ File permissions
- ✓ Offline libraries

---

## 8. Troubleshooting

### "Database connection failed"
```
→ Check MySQL is running
→ Verify credentials in includes/config.php
→ Ensure schema.sql was imported
```

### "Blank white page"
```
→ Check PHP error log: tail -f /var/log/php-errors.log
→ Enable debugging: Set DEBUG=true in config.php
→ Open debug_report.php
```

### "Icons not showing"
```
→ Check assets/lib/fontawesome/ folder exists
→ Verify CSS path in includes/header.php
→ Clear browser cache (Ctrl+Shift+Del)
```

### "QR codes not generating"
```
→ Check uploads/ is writable
→ Create mkdir uploads/.qrcache
→ Set chmod 777 uploads/.qrcache
```

---

## 9. Production Checklist

- [ ] Change admin password
- [ ] Enable HTTPS (SSL certificate)
- [ ] Update APP_URL to production domain
- [ ] Configure email (for password resets)
- [ ] Set appropriate error_reporting in config.php
- [ ] Regular database backups scheduled
- [ ] Test exam lockdown on target devices
- [ ] Generate admin backups regularly

---

## 10. Backup & Recovery

### Backup Database

```bash
mysqldump -u root -p bel_exam_portal > backup.sql
```

### Restore Database

```bash
mysql -u root -p bel_exam_portal < backup.sql
```

### Backup Application

```bash
tar -czf backup-$(date +%Y%m%d).tar.gz .
```

---

## Support

For installation issues, check:
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [Testing Report](TESTING.md)
- [Offline Setup](OFFLINE_SETUP.md)
