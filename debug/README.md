# 🔍 Debug & Testing Utilities

This folder contains tools for debugging, testing, and troubleshooting the BEL Exam Portal.

**Access**: `http://localhost/debug/`

---

## 📁 Files in This Folder

### 1. **debug_report.php** ⭐ Main Diagnostic Tool

Comprehensive system and application diagnostics.

**Access**: `http://localhost/debug/debug_report.php`

**What it checks:**
- ✅ PHP version and configuration
- ✅ Required extensions (PDO, MySQL, JSON, etc.)
- ✅ MySQL/Database connection
- ✅ Database tables and structure
- ✅ File system permissions
- ✅ Offline libraries status (Bootstrap, Font Awesome, QRCode.js)
- ✅ Server information

**Use when:**
- System won't start
- Database connection fails
- Icons/CSS not loading
- QR codes not generating
- File upload not working
- Need complete system status

---

### 2. **test_login.php** 🧪 Login Testing

Test credentials and login functionality testing.

**Access**: `http://localhost/debug/test_login.php`

**What it provides:**
- ✅ Admin test credentials
- ✅ Student test credentials
- ✅ Database statistics (user count, exams, questions)
- ✅ Quick login links
- ✅ Copy-to-clipboard for credentials

**Test Credentials:**
```
Admin:
  Email: admin@belkotdwar.in
  Password: Admin@123

Student:
  Roll: BEL-KOT-001
  Password: Welcome@123
```

**Use when:**
- Testing admin panel
- Testing student exam interface
- Adding more test users
- Verifying database data

---

### 3. **offline_test.php** 📊 Offline Test Suite

Automated test suite for offline/intranet functionality.

**Access**: `http://localhost/debug/offline_test.php`

**Tests:**
- ✅ PHP version (8.0+)
- ✅ MySQL connection
- ✅ Required PHP extensions
- ✅ File permissions (uploads, photos)
- ✅ Offline libraries present (Bootstrap, FontAwesome, QRCode)
- ✅ Database tables exist
- ✅ Session functionality

**Result:**
- Shows pass/fail for each test
- Overall score (X/Y tests passed)
- Status indicator (green = ready, red = issues)

**Use when:**
- Setting up new server
- Verifying offline deployment
- Before going live
- Troubleshooting startup issues

---

### 4. **phpinfo.php** ℹ️ PHP Configuration

Complete PHP information page.

**Access**: `http://localhost/debug/phpinfo.php`

**Shows:**
- PHP version and build
- Loaded extensions
- Configuration directives
- Environment variables
- Installed modules

**Use when:**
- Checking specific PHP settings
- Verifying extensions installed
- Comparing PHP versions
- Advanced troubleshooting

---

## 🚀 Quick Start

### First Time Setup?
1. Open: `http://localhost/debug/offline_test.php`
2. Verify all tests pass ✅
3. If any fail, open: `http://localhost/debug/debug_report.php`
4. Check specific section that failed

### After Installation?
1. Run: `http://localhost/debug/offline_test.php`
2. If all pass: Ready to use!
3. If fails: Use debug_report.php to identify issue

### Need Test Data?
1. Open: `http://localhost/debug/test_login.php`
2. Copy credentials
3. Login and test features

### Troubleshooting?
1. Open: `http://localhost/debug/debug_report.php`
2. Find the failing section
3. Check recommendations
4. Fix issue
5. Reload page to verify

---

## 🎯 Troubleshooting Guide by Issue

### "Can't connect to database"
→ Check: `debug_report.php` → Database Connection section

### "Icons not showing / styling broken"
→ Check: `debug_report.php` → Offline Libraries Status

### "Can't login"
→ Use: `test_login.php` → Get credentials → Try login
→ Check: `debug_report.php` → Database section

### "File upload not working"
→ Check: `debug_report.php` → File System Permissions

### "Application won't start"
→ Run: `offline_test.php` → See which test fails
→ Check: `debug_report.php` → Investigate failed section

### "QR codes not generating"
→ Check: `debug_report.php` → File System Permissions
→ Ensure: `uploads/.qrcache/` exists and is writable

### "Unsure what's wrong"
→ Run: `offline_test.php` first
→ Then open: `debug_report.php` for detailed analysis

---

## 📊 Test Expectations

### offline_test.php Should Show:
```
✅ PHP Version           → 8.0 or higher
✅ MySQL Connection      → Connected
✅ Extension: pdo        → Loaded
✅ Extension: pdo_mysql  → Loaded
✅ Extension: json       → Loaded
✅ Extension: gd         → Loaded
✅ Writable: uploads/    → Yes
✅ Offline Lib: Bootstrap → XX.XX KB
✅ Offline Lib: FontAwesome → XX.XX KB
✅ Offline Lib: QRCode.js → XX.XX KB
✅ Table: users          → Exists
✅ Table: exams          → Exists
✅ Sessions              → Working

Score: 14+/14 ✅ All Systems Operational!
```

If any test fails, see debug_report.php for details.

---

## 🔐 Security Notes

⚠️ **Important:**
- These debug files expose system information
- **DO NOT** leave them accessible in production
- Before production deployment:
  1. Delete or password-protect this folder
  2. Or move to `/admin/debug/` with admin-only access
  3. Or remove entirely (optional for production)

For production, you can:

**Option 1: Delete debug folder**
```bash
rm -rf debug/
```

**Option 2: Move to admin folder (recommended)**
```bash
mv debug/ admin/
# Then access: http://localhost/admin/debug/debug_report.php
```

**Option 3: Password protect (Apache)**
```apache
<Directory /var/www/html/debug>
    AuthType Basic
    AuthName "Debug Access"
    AuthUserFile /etc/apache2/.htpasswd
    Require valid-user
</Directory>
```

---

## 💡 Pro Tips

1. **Bookmark debug_report.php** - Great for quick diagnostics
2. **Use offline_test.php** - Before any major update
3. **Test login credentials** - Available in test_login.php
4. **Check permissions** - If files aren't uploading
5. **Verify offline libs** - If styling/icons broken

---

## 📞 When to Use Each File

| Problem | File | Access |
|---------|------|--------|
| System overview | offline_test.php | http://localhost/debug/offline_test.php |
| Specific issues | debug_report.php | http://localhost/debug/debug_report.php |
| Test login | test_login.php | http://localhost/debug/test_login.php |
| PHP settings | phpinfo.php | http://localhost/debug/phpinfo.php |

---

## ✨ Debug Folder Benefits

✅ **Everything in one place** - All debug tools organized  
✅ **Easy access** - Simple URLs, no confusion  
✅ **Comprehensive** - Tests system, DB, files, libraries  
✅ **User-friendly** - Clear results and recommendations  
✅ **Development-focused** - Perfect for troubleshooting  

---

## 🚀 Summary

The debug folder provides **complete diagnostic and testing capabilities** for the BEL Exam Portal.

**Use it to:**
- ✅ Diagnose system issues
- ✅ Test login functionality
- ✅ Verify offline capability
- ✅ Check PHP configuration
- ✅ Troubleshoot problems
- ✅ Validate deployments

**Remember:**
- Keep for development/testing
- Remove/protect for production
- Always run tests after updates

---

**Happy debugging!** 🔍

For more help, see [../docs/TROUBLESHOOTING.md](../docs/TROUBLESHOOTING.md)
