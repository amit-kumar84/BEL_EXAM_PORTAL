# BEL Kotdwar Exam Portal
## Online Examination System

**Status**: ✅ Production Ready | Offline-First | Government Grade

---

## 🚀 Quick Start

### Admin Access
- **URL**: `http://localhost/admin/login.php`
- **Email**: `admin@belkotdwar.in`
- **Password**: `Admin@123`

### Student Access
- **URL**: `http://localhost/student/login.php`
- **Demo Roll**: BEL-KOT-001
- **Demo Password**: Welcome@123

---

## 📁 Application Structure

```
htdocs/
├── 📄 index.php                 # Home page
├── 📄 logout.php                # Logout handler
├── 📄 forgot.php                # Password reset
│
├── 🗂️ admin/                    # Admin panel
│   ├── login.php                # Admin login
│   ├── dashboard.php            # Analytics dashboard
│   ├── students.php             # Student management
│   ├── exams.php                # Exam management
│   ├── questions.php            # Question management (bulk upload)
│   ├── results.php              # Results & analytics
│   ├── admins.php               # Admin accounts
│   ├── attempt.php              # View attempt details
│   ├── admit-card.php           # Hall ticket generation (QR)
│   ├── export-attempt.php       # Export attempt data
│   ├── export-results.php       # Export results
│   ├── _shell_top.php           # Header template
│   └── _shell_bottom.php        # Footer template
│
├── 🗂️ student/                  # Student panel
│   ├── login.php                # Student login
│   ├── dashboard.php            # Dashboard
│   ├── instructions.php         # Exam instructions
│   ├── take-exam.php            # Exam interface
│   ├── results.php              # Results view
│   └── submit.php               # Exam submission
│
├── 🗂️ api/                      # Backend APIs
│   ├── save-answer.php          # Save answer AJAX
│   ├── violation.php            # Lockdown violation tracking
│   └── qr-lookup.php            # Hall ticket QR verification
│
├── 🗂️ includes/                 # Core includes
│   ├── config.php               # Database & settings
│   ├── helpers.php              # Utility functions
│   ├── lang.php                 # Bilingual support
│   ├── header.php               # Global header
│   ├── footer.php               # Global footer
│   └── mailer.php               # Email functions
│
├── 🗂️ assets/                   # Static assets
│   ├── css/app.css              # Custom styles
│   ├── js/lockdown.js           # Exam lockdown script
│   ├── icons/                   # Icon files
│   └── lib/                     # Offline libraries
│       ├── bootstrap/           # Bootstrap 5.3.2 local
│       ├── fontawesome/         # Font Awesome 6.5.1 local
│       └── qrcode/              # QRCode.js 1.0.0 local
│
├── 🗂️ uploads/                  # User uploads
│   ├── photos/                  # Student photos
│   └── .qrcache/                # Cached QR codes
│
├── 🗂️ bulk_formats/             # Bulk import templates
│   ├── students_bulk_format.csv
│   ├── questions_mcq_format.csv
│   ├── questions_multiselect_format.csv
│   ├── questions_true_false_format.csv
│   ├── questions_short_answer_format.csv
│   ├── questions_numeric_format.csv
│   ├── questions_mixed_format.csv
│   └── README.md                # Template documentation
│
├── 🗂️ samples/                  # Sample data & CSV files
│   ├── cyber_security_questions_100.csv       # 100 cybersecurity MCQs
│   ├── ssc_gd_mix_questions_100.csv           # 100 mixed-type questions
│   └── students_bulk_sample.csv               # 10 sample students
│
├── 🗂️ docs/                     # Documentation
│   ├── README.md                # Main documentation
│   ├── INSTALLATION.md          # Setup instructions
│   ├── OFFLINE_SETUP.md         # Offline deployment
│   ├── API_REFERENCE.md         # API documentation
│   ├── TESTING.md               # Test reports
│   └── TROUBLESHOOTING.md       # Common issues
│
├── 📊 Database
│   ├── schema.sql               # Complete database schema
│   └── schema_upgrade.sql       # Database migrations
│
├── ⚙️ Setup
│   ├── OFFLINE_SETUP.bat        # Windows setup script
│   ├── OFFLINE_SETUP.sh         # Linux/Mac setup script
│   └── .htaccess                # Apache rewrite rules
│
└── 🧪 Tests (Optional)
    ├── OFFLINE_TEST.php         # Offline test suite
    ├── test_login.php           # Login tests
    └── debug_report.php         # Debug information
```

---

## 🎯 Key Features

✅ **Offline-First Architecture** - Works without internet  
✅ **Lockdown Mode** - Prevents cheating (tab switching, screenshots)  
✅ **QR-Based Hall Tickets** - Verify students via QR codes  
✅ **Multiple Question Types** - MCQ, Multi-Select, True/False, Short Answer, Numeric  
✅ **Bulk Import** - Upload students and questions via CSV  
✅ **Bilingual Support** - English & Hindi interface  
✅ **Government Grade** - Professional BEL branding  
✅ **Mobile Responsive** - Works on tablets & phones  
✅ **Analytics Dashboard** - Real-time exam statistics  

---

## 💾 Database Setup

```bash
# Option 1: Via phpMyAdmin
1. Open http://localhost/phpmyadmin
2. Import tab → Select schema.sql → Execute

# Option 2: Via MySQL CLI
mysql -u root -p < schema.sql

# Option 3: Auto-setup (if enabled)
1. Run OFFLINE_SETUP.bat (Windows)
2. Run OFFLINE_SETUP.sh (Linux/Mac)
```

---

## 📝 Bulk Import Data

### Students
Copy from: `bulk_formats/students_bulk_format.csv`  
Go to: Admin > Students > "Bulk Upload Students"

### Questions
1. **MCQ Only**: Use `bulk_formats/questions_mcq_format.csv`
2. **Mixed Types**: Use `bulk_formats/questions_mixed_format.csv`
3. Go to: Admin > Exams > Edit > Questions > "Bulk Upload"

### Sample Data
- `samples/cyber_security_questions_100.csv` - Ready to import
- `samples/ssc_gd_mix_questions_100.csv` - Ready to import
- `samples/students_bulk_sample.csv` - 10 sample students

---

## 🔒 Security Features

- **Password Hashing**: bcrypt (cost: 10)
- **CSRF Protection**: Token validation on all forms
- **Prepared Statements**: SQL injection prevention
- **Session Security**: HTTPOnly cookies, secure flags
- **Exam Lockdown**: Prevents browser shortcuts, tab switching
- **QR Verification**: Secure student identification

---

## 🛠️ Technologies

| Component | Technology | Version |
|-----------|-----------|---------|
| **Server** | PHP | 8.2.12 |
| **Database** | MySQL | 5.7+ |
| **Frontend** | Bootstrap | 5.3.2 (Offline) |
| **Icons** | Font Awesome | 6.5.1 (Offline) |
| **QR Code** | QRCode.js | 1.0.0 (Offline) |

---

## 📚 Documentation

- **[Installation Guide](docs/INSTALLATION.md)** - Setup on Windows/Linux/Mac
- **[Offline Deployment](docs/OFFLINE_SETUP.md)** - Run on Intranet
- **[API Reference](docs/API_REFERENCE.md)** - REST endpoints
- **[Testing Report](docs/TESTING.md)** - All tests passed
- **[Troubleshooting](docs/TROUBLESHOOTING.md)** - Common issues

---

## 🐛 Troubleshooting

**Q: Admin login not working?**  
A: Clear browser cache or try incognito mode. Default: `admin@belkotdwar.in` / `Admin@123`

**Q: Database connection error?**  
A: Ensure MySQL is running and credentials in `includes/config.php` are correct.

**Q: QR codes not generating?**  
A: Check `uploads/` folder permissions. Should be writable.

For more help, see [Troubleshooting Guide](docs/TROUBLESHOOTING.md)

---

## 📞 Support

For issues or feature requests, contact BEL Kotdwar Administration.

**System Status**: ✅ Production Ready

---

*Last Updated: April 2026 | Version: 2.0*
