# Documentation Index

Welcome to the BEL Kotdwar Exam Portal documentation.

---

## 🚀 Quick Start

**New to the system?** Start here:

1. **[Main README](../README.md)** - Overview & features
2. **[Installation Guide](INSTALLATION.md)** - Get it running
3. **[First Time Setup](INSTALLATION.md#4-initial-login)** - After installation

---

## 📚 Full Documentation

### Getting Started
- **[Installation Guide](INSTALLATION.md)** - Setup on Windows/Linux/Mac
- **[First Login](INSTALLATION.md#4-initial-login)** - Initial configuration
- **[Offline Deployment](OFFLINE_SETUP.md)** - Run on intranet/offline networks

### Using the System
- **[Bulk Import Guide](../bulk_formats/README.md)** - Import students & questions
- **[Sample Data](../samples/README.md)** - Download and use demo data
- **[API Reference](API_REFERENCE.md)** - Technical API documentation

### Help & Support
- **[Troubleshooting Guide](TROUBLESHOOTING.md)** - Common issues & solutions
- **[Testing Report](TESTING.md)** - All tests verified ✅
- **[Structure Guide](STRUCTURE.md)** - Application file organization

---

## 🎯 Find Your Answer

### Admin Tasks
- **Create exams** → [INSTALLATION.md](INSTALLATION.md#4-initial-login)
- **Import students** → [../bulk_formats/README.md](../bulk_formats/README.md)
- **Add questions** → [../bulk_formats/README.md](../bulk_formats/README.md)
- **Generate admit cards** → [API_REFERENCE.md](API_REFERENCE.md#export-apis)
- **View results** → [API_REFERENCE.md](API_REFERENCE.md)

### Student Issues
- **Can't login** → [TROUBLESHOOTING.md](TROUBLESHOOTING.md#access-denied-on-login)
- **Exam won't start** → [TROUBLESHOOTING.md](TROUBLESHOOTING.md#exam-won't-start--shows-timeout)
- **Lockdown violation** → [TROUBLESHOOTING.md](TROUBLESHOOTING.md#student-sees-lockdown-violation)

### Technical Issues
- **Database error** → [TROUBLESHOOTING.md](TROUBLESHOOTING.md#database-issues)
- **Icons not showing** → [TROUBLESHOOTING.md](TROUBLESHOOTING.md#icons-not-showing)
- **QR code broken** → [TROUBLESHOOTING.md](TROUBLESHOOTING.md#qr-codes-not-generating)
- **Offline not working** → [OFFLINE_SETUP.md](OFFLINE_SETUP.md) & [TROUBLESHOOTING.md](TROUBLESHOOTING.md#offline-network-issues)

### API Development
- **Save answers** → [API_REFERENCE.md#1-save-answer-ajax](API_REFERENCE.md#1-save-answer-ajax)
- **Track violations** → [API_REFERENCE.md#2-violation-tracking](API_REFERENCE.md#2-violation-tracking)
- **QR verification** → [API_REFERENCE.md#3-qr-lookup](API_REFERENCE.md#3-qr-lookup)
- **Bulk operations** → [API_REFERENCE.md#admin-apis-internal](API_REFERENCE.md#admin-apis-internal)

---

## 📊 Document Map

```
docs/
├── README.md                    ← You are here
├── INSTALLATION.md              # 📖 Setup guide
├── OFFLINE_SETUP.md             # 🌐 Intranet deployment
├── API_REFERENCE.md             # 🔌 REST APIs
├── TROUBLESHOOTING.md           # 🐛 Issues & solutions
└── STRUCTURE.md                 # 📁 File organization

Also see:
../README.md                      # Main application docs
../STRUCTURE_SUMMARY.md           # Before/after cleanup
../bulk_formats/README.md         # Import format guide
../samples/                       # Sample CSV files
```

---

## 🎓 Learning Path

**Follow this order based on your role:**

### 👤 Administrator
1. [INSTALLATION.md](INSTALLATION.md) - Get system running
2. [INSTALLATION.md#4-initial-login](INSTALLATION.md#4-initial-login) - First setup
3. [../bulk_formats/README.md](../bulk_formats/README.md) - Bulk import
4. [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Common issues
5. [API_REFERENCE.md](API_REFERENCE.md) - Technical details

### 👨‍💻 Developer
1. [README.md](#quick-start) - Overview
2. [API_REFERENCE.md](API_REFERENCE.md) - API docs
3. [STRUCTURE.md](STRUCTURE.md) - Code organization
4. [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Debug guide

### 🌐 DevOps / Server Admin
1. [INSTALLATION.md](INSTALLATION.md) - Setup
2. [OFFLINE_SETUP.md](OFFLINE_SETUP.md) - Deployment
3. [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Maintenance

---

## 📈 System Status

| Component | Status | Details |
|-----------|--------|---------|
| **Application** | ✅ Production Ready | All features working |
| **Database** | ✅ Verified | MySQL 5.7+ compatible |
| **Offline Mode** | ✅ Functional | All libraries local |
| **Security** | ✅ Implemented | Encryption, CSRF, session |
| **Performance** | ✅ Optimized | Fast response times |
| **Tests** | ✅ All Passing | See [TESTING.md](TESTING.md) |

---

## 🔍 Search & Index

### By Feature
- **Exams**: [INSTALLATION.md](INSTALLATION.md#4-initial-login), [API_REFERENCE.md](API_REFERENCE.md)
- **Students**: [../bulk_formats/README.md](../bulk_formats/README.md), [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
- **Questions**: [../bulk_formats/README.md](../bulk_formats/README.md)
- **Results**: [API_REFERENCE.md](API_REFERENCE.md#export-apis)
- **QR Codes**: [API_REFERENCE.md](API_REFERENCE.md#3-qr-lookup), [TROUBLESHOOTING.md](TROUBLESHOOTING.md#qr-codes-not-generating)
- **Lockdown**: [TROUBLESHOOTING.md](TROUBLESHOOTING.md#exam-lockdown-issues)

### By Technology
- **PHP**: [API_REFERENCE.md](API_REFERENCE.md#example-implementations)
- **MySQL**: [INSTALLATION.md](INSTALLATION.md#2-database-setup), [TROUBLESHOOTING.md](TROUBLESHOOTING.md#database-issues)
- **Bootstrap**: [TROUBLESHOOTING.md](TROUBLESHOOTING.md#bootstrap-styling-broken)
- **QRCode.js**: [TROUBLESHOOTING.md](TROUBLESHOOTING.md#qr-codes-not-generating)
- **Security**: [TROUBLESHOOTING.md](TROUBLESHOOTING.md#offline-network-issues)

### By Issue Type
- **Installation**: [INSTALLATION.md](INSTALLATION.md)
- **Configuration**: [INSTALLATION.md](INSTALLATION.md#3-configuration)
- **Performance**: [TROUBLESHOOTING.md](TROUBLESHOOTING.md#performance-issues)
- **Display**: [TROUBLESHOOTING.md](TROUBLESHOOTING.md#display-issues)
- **Access**: [TROUBLESHOOTING.md](TROUBLESHOOTING.md#connection-issues)

---

## 💬 FAQ

**Q: Where do I start?**  
A: Read [../README.md](../README.md) first, then [INSTALLATION.md](INSTALLATION.md)

**Q: How do I import students?**  
A: See [../bulk_formats/README.md](../bulk_formats/README.md)

**Q: Is it offline capable?**  
A: Yes! See [OFFLINE_SETUP.md](OFFLINE_SETUP.md)

**Q: Something's broken, what do I do?**  
A: Check [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

**Q: How do I integrate with external systems?**  
A: See [API_REFERENCE.md](API_REFERENCE.md)

**Q: Where are the application files?**  
A: See [STRUCTURE.md](STRUCTURE.md)

---

## 📞 Support Resources

- **Errors**: Check [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
- **Setup**: Check [INSTALLATION.md](INSTALLATION.md)
- **APIs**: Check [API_REFERENCE.md](API_REFERENCE.md)
- **Data**: Check [../bulk_formats/](../bulk_formats/)

---

## 📜 Document Versions

| Document | Last Updated | Status |
|----------|--------------|--------|
| [Installation Guide](INSTALLATION.md) | April 2026 | ✅ Current |
| [Offline Setup](OFFLINE_SETUP.md) | April 2026 | ✅ Current |
| [API Reference](API_REFERENCE.md) | April 2026 | ✅ Current |
| [Troubleshooting](TROUBLESHOOTING.md) | April 2026 | ✅ Current |
| [Structure Guide](STRUCTURE.md) | April 2026 | ✅ Current |

---

## 🎯 Next Steps

1. **Read** the relevant document for your needs (see Quick Start above)
2. **Follow** the step-by-step instructions
3. **Test** your configuration
4. **Reference** [TROUBLESHOOTING.md](TROUBLESHOOTING.md) if issues occur
5. **Contact** admin with detailed error information if needed

---

## 🚀 You're Ready!

Choose your path:

- **👤 Admin?** → [INSTALLATION.md](INSTALLATION.md)
- **👨‍💻 Developer?** → [API_REFERENCE.md](API_REFERENCE.md)
- **🌐 DevOps?** → [OFFLINE_SETUP.md](OFFLINE_SETUP.md)
- **🆘 Troubleshooting?** → [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

---

**Welcome to BEL Kotdwar Exam Portal!** 🎓

*Production Ready | Government Grade | Offline Capable*
