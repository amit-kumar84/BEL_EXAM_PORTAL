# Directory Structure & Cleanup Guide

This document explains the organized structure and what files can be safely removed.

---

## 📁 Organized Structure

```
htdocs/
│
├── 📚 Root Files (Essential)
│   ├── README.md                     # ← START HERE (Main documentation)
│   ├── index.php                     # Home page
│   ├── logout.php                    # Logout handler  
│   ├── forgot.php                    # Password reset
│   ├── .htaccess                     # Apache rewrite rules
│   └── .git/                         # Version control
│
├── 🔐 Admin Panel
│   └── admin/
│       ├── login.php                 # Admin login
│       ├── dashboard.php             # Dashboard
│       ├── students.php              # Student management
│       ├── exams.php                 # Exam management
│       ├── questions.php             # Question bulk upload
│       ├── results.php               # Results/analytics
│       ├── admins.php                # Admin accounts
│       ├── attempt.php               # Attempt details
│       ├── admit-card.php            # Hall tickets (QR)
│       ├── export-attempt.php        # Export attempts
│       ├── export-results.php        # Export results
│       ├── _shell_top.php            # Header template
│       └── _shell_bottom.php         # Footer template
│
├── 👨‍🎓 Student Panel
│   └── student/
│       ├── login.php                 # Student login
│       ├── dashboard.php             # Dashboard
│       ├── instructions.php          # Exam instructions
│       ├── take-exam.php             # Exam interface
│       ├── results.php               # Results
│       └── submit.php                # Exam submission
│
├── 🔌 Backend APIs
│   └── api/
│       ├── save-answer.php           # AJAX answer saving
│       ├── violation.php             # Lockdown tracking
│       └── qr-lookup.php             # QR verification
│
├── 🛠️ Core System
│   └── includes/
│       ├── config.php                # Database config
│       ├── helpers.php               # Utility functions
│       ├── lang.php                  # i18n support
│       ├── header.php                # Global header
│       ├── footer.php                # Global footer
│       └── mailer.php                # Email functions
│
├── 🎨 Frontend Assets
│   └── assets/
│       ├── css/app.css               # Custom styling
│       ├── js/lockdown.js            # Exam lockdown
│       ├── icons/                    # Icon assets
│       └── lib/                      # ✅ Offline libraries
│           ├── bootstrap/            # Bootstrap 5.3.2
│           ├── fontawesome/          # Font Awesome 6.5.1
│           └── qrcode/               # QRCode.js 1.0.0
│
├── 📋 Sample Data
│   └── samples/
│       ├── cyber_security_questions_100.csv
│       ├── ssc_gd_mix_questions_100.csv
│       └── students_bulk_sample.csv
│
├── 📦 Bulk Import Templates
│   └── bulk_formats/
│       ├── students_bulk_format.csv
│       ├── questions_mcq_format.csv
│       ├── questions_multiselect_format.csv
│       ├── questions_true_false_format.csv
│       ├── questions_short_answer_format.csv
│       ├── questions_numeric_format.csv
│       ├── questions_mixed_format.csv
│       └── README.md
│
├── 📚 Documentation
│   └── docs/
│       ├── README.md                 # Overview
│       ├── INSTALLATION.md           # Setup guide
│       ├── OFFLINE_SETUP.md          # Intranet deployment
│       ├── API_REFERENCE.md          # API documentation
│       ├── TROUBLESHOOTING.md        # Common issues
│       └── STRUCTURE.md              # ← This file
│
├── 💾 Database
│   ├── schema.sql                    # Full database schema
│   └── (removed — merged into schema.sql) 
│
├── ⚙️ Setup Scripts
│   ├── OFFLINE_SETUP.bat             # Windows setup
│   └── OFFLINE_SETUP.sh              # Linux/Mac setup
│
└── 📁 Runtime Data
    └── uploads/
        ├── photos/                   # Student photo uploads
        └── .qrcache/                 # QR code cache
```

---

## ✨ Files to Keep (Production)

**Always keep**:
```
README.md                 # Documentation entry point
admin/                    # Admin panel
student/                  # Student panel
api/                      # Backend APIs
includes/                 # Core system
assets/                   # All assets (CSS, JS, libs)
uploads/                  # Runtime uploads
bulk_formats/             # Import templates
docs/                     # Documentation
samples/                  # Sample data
schema.sql                # Database schema
OFFLINE_SETUP.bat/sh      # Setup scripts
```

---

## 🗑️ Files Safe to Remove (Cleanup)

After deployment, these can be removed:

### 1. Temporary Test Files

```
❌ REMOVE:
test_login.php            # Test login page
debug_report.php          # Debug report page  
OFFLINE_TEST.php          # Offline test suite
```

**Keep in development only. Remove for production.**

### 2. Old Documentation (Now in docs/)

```
❌ REMOVE (if in root):
BUG_REPORT.md             # Old bug report
FINAL_TEST_SUMMARY.md     # Old test summary
TESTING_REPORT.md         # Old test report
OFFLINE_README.md         # Old offline guide
OFFLINE_SETUP.md          # Old setup docs
OFFLINE_SUCCESS_REPORT.md # Old success report
OFFLINE_FILES_INDEX.md    # Old file index
OFFLINE_INSTALLATION_GUIDE.md  # Old install guide
SERVER_HOSTING_GUIDE_HINDI.md  # Old guide
START_HERE.md             # Old start guide
```

✅ **Kept in docs/ folder instead** (organized)

### 3. Sample CSV Files (Optional)

```
⚠️ OPTIONAL REMOVE:
students_bulk_sample.csv          # Sample students
cyber_security_questions_100.csv  # Sample questions
ssc_gd_mix_questions_100.csv      # Sample questions
```

Keep if needed for:
- Testing
- Demo purposes
- User reference

Move to `samples/` folder if keeping.

---

## 🧹 Cleanup Script (Windows)

Create `cleanup.bat`:

```batch
@echo off
echo Cleaning up unnecessary files...
echo.

REM Delete test files
del /Q test_login.php
del /Q debug_report.php
del /Q OFFLINE_TEST.php

REM Delete old documentation
del /Q BUG_REPORT.md
del /Q FINAL_TEST_SUMMARY.md
del /Q TESTING_REPORT.md
del /Q OFFLINE_README.md
del /Q OFFLINE_SETUP.md
del /Q OFFLINE_SUCCESS_REPORT.md
del /Q OFFLINE_FILES_INDEX.md
del /Q OFFLINE_INSTALLATION_GUIDE.md
del /Q SERVER_HOSTING_GUIDE_HINDI.md
del /Q START_HERE.md

REM Optional: Move sample files to samples/
REM (Uncomment if needed)
REM move students_bulk_sample.csv samples\
REM move cyber_security_questions_100.csv samples\
REM move ssc_gd_mix_questions_100.csv samples\

echo.
echo ✓ Cleanup complete!
echo.
echo Remaining structure is clean and production-ready.
pause
```

---

## 🧹 Cleanup Script (Linux/Mac)

Create `cleanup.sh`:

```bash
#!/bin/bash

echo "Cleaning up unnecessary files..."
echo

# Delete test files
rm -f test_login.php
rm -f debug_report.php
rm -f OFFLINE_TEST.php

# Delete old documentation
rm -f BUG_REPORT.md
rm -f FINAL_TEST_SUMMARY.md
rm -f TESTING_REPORT.md
rm -f OFFLINE_README.md
rm -f OFFLINE_SETUP.md
rm -f OFFLINE_SUCCESS_REPORT.md
rm -f OFFLINE_FILES_INDEX.md
rm -f OFFLINE_INSTALLATION_GUIDE.md
rm -f SERVER_HOSTING_GUIDE_HINDI.md
rm -f START_HERE.md

# Optional: Move sample files to samples/ folder
# (Uncomment if needed)
# mv students_bulk_sample.csv samples/
# mv cyber_security_questions_100.csv samples/
# mv ssc_gd_mix_questions_100.csv samples/

echo "✓ Cleanup complete!"
echo
echo "Remaining structure is clean and production-ready."
```

**Run it**:
```bash
chmod +x cleanup.sh
./cleanup.sh
```

---

## 📊 File Count Comparison

### Before Cleanup
```
Total files in root: 20+
├─ PHP files: 4 (index, logout, forgot, test_login)
├─ Markdown files: 11 (documentation scattered)
├─ CSV files: 3 (sample data mixed in)
└─ Config files: 2 (schema.sql, .htaccess)

Problem: Disorganized, cluttered root directory ❌
```

### After Cleanup & Reorganization
```
Total files in root: 8 (clean!)
├─ PHP files: 3 (index, logout, forgot)
├─ Markdown files: 1 (README.md only)
├─ Config files: 2 (schema.sql, .htaccess)
└─ Scripts: 2 (setup scripts)

Organized in folders:
├─ docs/: All documentation (5 markdown files)
├─ samples/: All sample CSVs (3 files)
├─ bulk_formats/: Import templates (8 files)
├─ admin/, student/, api/, includes/, assets/: Code

Result: Clean, organized, production-ready! ✅
```

---

## 🚀 Post-Cleanup Verification

After cleanup, verify:

```bash
# 1. Check root directory is clean
ls -la | grep -E "\.md|\.csv"
# Should show NO .md or .csv files in root!

# 2. Verify docs folder has everything
ls -la docs/
# Should have: README.md, INSTALLATION.md, OFFLINE_SETUP.md, etc.

# 3. Verify samples folder has CSVs
ls -la samples/
# Should have: .csv files

# 4. Test application
# Open http://localhost
# Admin: http://localhost/admin/login.php
# Student: http://localhost/student/login.php
```

---

## 📋 Checklist

- [ ] Read this STRUCTURE.md document
- [ ] Verify docs/ folder has all documentation
- [ ] Move samples to samples/ folder
- [ ] Run cleanup.sh (or cleanup.bat)
- [ ] Delete unnecessary test files
- [ ] Verify root directory is clean
- [ ] Test application still works
- [ ] Update README.md if needed
- [ ] Commit changes: `git add -A && git commit -m "Cleanup: Reorganize structure"`

---

## 🎯 Result

**Clean, organized, production-ready application!**

```
htdocs/
├── 📄 Key files (index, logout, forgot)
├── 📚 docs/ (All documentation)
├── 📦 samples/ (All sample data)
├── 🔐 admin/, student/, api/ (Code)
├── 🛠️ includes/, assets/ (System)
├── 📋 bulk_formats/ (Import templates)
├── 💾 uploads/ (Runtime)
└── ⚙️ Setup & config files
```

**No clutter, no confusion, everything in its place!** ✨

---

For more information, see:
- [README.md](../README.md) - Main documentation
- [INSTALLATION.md](INSTALLATION.md) - Setup guide
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Issues & solutions
