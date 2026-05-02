# Troubleshooting Guide

Solutions for common issues.

---

## Connection Issues

### "MySQL Connection Failed"

**Error**: `Connection refused` or `Unknown database`

**Solutions**:

```bash
# 1. Check MySQL is running
sudo systemctl status mysql

# 2. Verify credentials in config.php
cat includes/config.php | grep DB_

# 3. Test connection
mysql -u root -p -e "SELECT 1"

# 4. Restart MySQL
sudo systemctl restart mysql
```

**If still failing**:
- Check MySQL port (usually 3306): `netstat -tlnp | grep mysql`
- Verify user permissions: `mysql -u root -p -e "SHOW GRANTS FOR 'root'@'localhost'"`
- Check error log: `/var/log/mysql/error.log`

---

### "Access Denied" on Login

**Issue**: Can't login as admin

**Solutions**:

```bash
# 1. Reset admin password (MySQL)
mysql -u root -p bel_exam_portal

# In MySQL:
UPDATE users SET password_hash = '$2y$10$rLUYXqsA6Z5yrG1vQvQZruP0LhXh3x4fJOVt.bEEpJqqnl4ZsW2QW' 
WHERE email = 'admin@belkotdwar.in';
```

**Default credentials after reset**:
- Email: `admin@belkotdwar.in`
- Password: `Admin@123`

**Or programmatically**:

```php
// Create reset.php temporarily
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'bel_exam_portal');

$pdo = new PDO("mysql:host=".DB_HOST, DB_USER, DB_PASS);
$hash = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 10]);
$pdo->exec("UPDATE bel_exam_portal.users SET password_hash = '$hash' WHERE email = 'admin@belkotdwar.in'");
echo "Password reset. Use: admin@belkotdwar.in / Admin@123";
?>

// Then delete reset.php
```

---

## Display Issues

### Icons Not Showing

**Problem**: Font Awesome icons appear as blank boxes

**Solutions**:

```bash
# 1. Verify fontawesome folder
ls -la assets/lib/fontawesome/

# Output should show:
# - css/all.min.css
# - webfonts/ (with .woff2, .woff, .ttf files)
```

**Browser check**:

```javascript
// Open browser console (F12)
// Check Network tab for failed resources
// Look for 404 errors on fontawesome files

// If missing, CSS paths may be wrong
// Check includes/header.php line with:
// <link href="<?= url('assets/lib/fontawesome/css/all.min.css') ?>" ...>
```

**Fix**:

```bash
# Re-download Font Awesome
cd assets/lib/
rm -rf fontawesome/
# Download fresh copy from https://fontawesome.com/download
# Or re-run: OFFLINE_SETUP.bat (Windows)
```

---

### Bootstrap Styling Broken

**Problem**: Page layout looks broken, no styling

**Solutions**:

```bash
# 1. Check Bootstrap file exists
ls -la assets/lib/bootstrap/

# Should have:
# - css/bootstrap.min.css (~227 KB)
# - js/bootstrap.bundle.min.js (~78 KB)
```

**In browser DevTools**:

```javascript
// F12 → Network tab
// Reload page
// Look for red/404 errors on bootstrap files

// Check CSS is applied:
// DevTools → Elements → Inspect element → Styles
```

**Fix broken CSS link**:

```php
// Edit: includes/header.php
// Should be:
<link href="<?= url('assets/lib/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">

// NOT:
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" ...>
```

---

## Database Issues

### "Table doesn't exist"

**Error**: `Table 'bel_exam_portal.users' doesn't exist`

**Solution**:

```bash
# Re-import schema
mysql -u root -p bel_exam_portal < schema.sql

# Or verify via phpMyAdmin
# http://localhost/phpmyadmin
# Check: bel_exam_portal database
# Should have 8 tables: users, exams, questions, etc.
```

---

### "Duplicate Entry" on Student Upload

**Error**: `Duplicate entry 'xxx' for key 'email'` or `'roll_number'`

**Causes**: 
- Student already exists
- Email/roll_number not unique

**Solution**:

```bash
# Option 1: Delete duplicate and re-import
DELETE FROM users WHERE email = 'duplicate@mail.com';

# Option 2: Check existing students
SELECT COUNT(*) as total FROM users WHERE role = 'student';

# Option 3: Use different email/roll in CSV
```

---

## File Upload Issues

### Photos Not Uploading

**Problem**: Student photos don't save

**Solutions**:

```bash
# 1. Check uploads folder exists and is writable
ls -la uploads/
ls -la uploads/photos/

# 2. Make writable (Linux)
chmod 755 uploads/
chmod 755 uploads/photos/
chown www-data:www-data uploads/

# 3. Check file size limit in PHP
php -r "echo ini_get('upload_max_filesize');"
# Should be at least 2M
```

**Edit php.ini if too small**:

```ini
; /etc/php/8.2/apache2/php.ini
upload_max_filesize = 10M
post_max_size = 50M
```

Then restart: `sudo systemctl restart apache2`

---

### QR Codes Not Generating

**Error**: Admit card shows blank or missing QR

**Solutions**:

```bash
# 1. Create QR cache directory
mkdir -p uploads/.qrcache
chmod 777 uploads/.qrcache

# 2. Test QR generation
php -r "
require 'includes/config.php';
\$data = 'BEL-KOTDWAR|TEST|001';
\$qr_file = 'uploads/.qrcache/' . md5(\$data) . '.svg';
echo 'QR file: ' . \$qr_file;
"

# 3. Check qrcode library
ls -la assets/lib/qrcode/

# Should have: qrcode.min.js
```

**Browser console error**:

```javascript
// F12 → Console tab
// Look for JavaScript errors like:
// "Cannot read property 'QRCode' of undefined"

// Fix: Check assets/lib/qrcode/qrcode.min.js is loaded
// In page source: <script src="/assets/lib/qrcode/qrcode.min.js"></script>
```

---

## Performance Issues

### "Page loads very slowly"

**Solutions**:

```bash
# 1. Check database query performance
# Enable query logging in config.php:
define('DEBUG', true);
define('LOG_QUERIES', true);

# Check logs/queries.log for slow queries

# 2. Monitor system resources
top -b -n 1 | head -n 20
free -h
df -h

# 3. Check Apache logs for errors
tail -f /var/log/apache2/error.log
tail -f /var/log/apache2/access.log
```

**Optimization**:

```bash
# 1. Enable browser caching (in .htaccess)
<FilesMatch "\\.(jpg|jpeg|png|gif|css|js|ico)$">
  Header set Cache-Control "max-age=31536000"
</FilesMatch>

# 2. Enable gzip compression
a2enmod deflate

# 3. Reduce database queries (enable MySQL query cache)
# Edit /etc/mysql/mysql.conf.d/mysqld.cnf:
query_cache_type = 1
query_cache_size = 256M
```

---

## Exam/Lockdown Issues

### "Exam won't start / shows timeout"

**Problem**: Student clicks "Start Exam" but nothing happens

**Solutions**:

```javascript
// Check browser console (F12)
// Look for JavaScript errors
// Common issues:
// - "Exam lockdown failed to initialize"
// - "Session expired"

// Test exam lockdown:
// Open browser console:
console.log(window.examLockdown);  // Should show object
```

**If lockdown not loading**:

```bash
# Check lockdown.js exists
ls -la assets/js/lockdown.js

# Verify in student/take-exam.php:
# <script src="<?= url('assets/js/lockdown.js') ?>"></script>
```

---

### Student Sees "Lockdown Violation"

**Problem**: Exam closes unexpectedly

**Causes**:
- Tab switching detected
- Window minimized
- Lost focus
- Browser DevTools opened

**Student actions**:
1. Accept violation warning if shown
2. Re-start exam
3. Stay in exam tab during entire exam
4. Contact admin if continues

**Admin view**:
```bash
# Check violations in database
mysql -u root -p bel_exam_portal

SELECT * FROM violations WHERE user_id = 123;
```

---

## Offline/Network Issues

### "Application not working offline"

**Check**:

```bash
# 1. Verify all offline libraries present
ls -la assets/lib/bootstrap/
ls -la assets/lib/fontawesome/
ls -la assets/lib/qrcode/

# 2. Disconnect internet/unplug network
# 3. Test pages load
# 4. Check browser console for failed requests to CDNs
```

**If failing**:

```bash
# Reinstall offline libraries
./OFFLINE_SETUP.bat  # Windows

# Or manually verify in app.css, header.php, footer.php
# Should NOT contain any CDN URLs like:
# - https://cdn.jsdelivr.net
# - https://cdnjs.cloudflare.com
# - https://fonts.googleapis.com
```

---

## Email/Notification Issues

### Emails not sending

**Problem**: Password reset emails not received

**Check**:

```bash
# 1. Verify mail server running
sendmail -v -t test@example.com < /var/mail/test

# 2. Check mail configuration
php -r "echo ini_get('SMTP');"
php -r "echo ini_get('smtp_port');"

# 3. Check mail logs
tail -f /var/log/mail.log
```

**For offline/intranet**:

```php
// In includes/config.php
// Disable email if no mail server:
define('ENABLE_EMAIL', false);
```

---

## Browser-Specific Issues

### Exam not working in Safari

**Solutions**:

```javascript
// Safari may block cookies or have strict policies
// In admin panel, check:
// Settings → Browser Compatibility

// Enable for Safari:
// - Allow Cross-Site Tracking
// - Allow Pop-ups & Redirects
```

### Mobile display issues

**Problem**: Exam looks broken on phone

**Check**:

```javascript
// Verify meta viewport tag in header.php:
// <meta name="viewport" content="width=device-width, initial-scale=1">

// Test responsive design in browser:
// F12 → Toggle Device Toolbar (Ctrl+Shift+M)
```

---

## Getting Help

### Collect Debug Information

```bash
# 1. System information
uname -a
php -v
mysql -V

# 2. Error logs
cat /var/log/apache2/error.log | tail -20
cat /var/log/mysql/error.log | tail -20

# 3. Application debug
php debug_report.php

# 4. Test page
php OFFLINE_TEST.php
```

### Report Issues

Include:
- [ ] Error message (screenshot)
- [ ] System info (OS, PHP, MySQL versions)
- [ ] Error logs (last 50 lines)
- [ ] Steps to reproduce
- [ ] What you expected to happen

---

## Emergency Recovery

### Complete Reset

```bash
# Backup first!
mysqldump -u root -p bel_exam_portal > backup-emergency.sql

# Delete & recreate database
mysql -u root -p -e "DROP DATABASE bel_exam_portal;"
mysql -u root -p < schema.sql

# Clear application cache
rm -rf uploads/.qrcache/*
rm -rf assets/cache/*

# Restart services
sudo systemctl restart apache2 mysql

# Re-login
# http://localhost/admin/login.php
# admin@belkotdwar.in / Admin@123
```

### Restore from Backup

```bash
mysql -u root -p bel_exam_portal < backup.sql
```

---

## Still Having Issues?

1. Check [INSTALLATION.md](INSTALLATION.md) for setup steps
2. Review [TESTING.md](TESTING.md) for verification
3. Check [API_REFERENCE.md](API_REFERENCE.md) for specific features
4. Run `php OFFLINE_TEST.php` for diagnostics
