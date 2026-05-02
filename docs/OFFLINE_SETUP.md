# Offline Deployment Guide

Run the BEL Exam Portal on **intranet/offline networks** without internet connection.

---

## Why Offline?

✅ **No Internet Dependency** - Works on isolated networks  
✅ **Enhanced Security** - No external API calls  
✅ **Faster Performance** - Local assets, no CDN delays  
✅ **Government Compliance** - Secure intranet deployment  
✅ **Reliability** - No external service failures  

---

## What's Included (Offline)

### 1. Bootstrap 5.3.2 (Local)
- Location: `assets/lib/bootstrap/`
- CSS + JS bundle (~160 KB)
- All UI components functional

### 2. Font Awesome 6.5.1 (Local)
- Location: `assets/lib/fontawesome/`
- 7000+ icons available
- WOFF2/TTF fonts (~660 KB)

### 3. QRCode.js 1.0.0 (Local)
- Location: `assets/lib/qrcode/`
- Client-side QR generation
- No API calls needed

### 4. Database (MySQL)
- Complete offline data storage
- No cloud synchronization
- Full CRUD operations

**Total Size**: ~2.5 MB (including all libraries)

---

## Deployment Steps

### Step 1: Prepare Server

```bash
# Linux server (no internet)
sudo apt-get install apache2 php mysql-server libapache2-mod-php

# Enable required Apache modules
sudo a2enmod rewrite
sudo a2enmod php8.2

# Restart Apache
sudo systemctl restart apache2
```

### Step 2: Copy Application

```bash
# Via USB/Network Share
cp -r /media/usb/htdocs /var/www/html/

# Or via network
scp -r local_path/ user@intranet-server:/var/www/html
```

### Step 3: Setup Database

```bash
# Import schema
mysql -u root -p < schema.sql

# Or via script
./OFFLINE_SETUP.sh
```

### Step 4: Configure

Edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'bel_exam_portal');

// Intranet URL
define('APP_URL', 'http://intranet-server');
```

### Step 5: Verify

```bash
# Test offline test suite
curl http://intranet-server/OFFLINE_TEST.php

# Or open in browser
http://intranet-server/OFFLINE_TEST.php
```

---

## Offline Features Working

| Feature | Status | Notes |
|---------|--------|-------|
| Admin Panel | ✅ | Full management |
| Student Exams | ✅ | Complete exam experience |
| QR Generation | ✅ | Local client-side |
| Admit Cards | ✅ | Printable with QR |
| Results | ✅ | Real-time analytics |
| File Uploads | ✅ | Student photos |
| Lockdown Mode | ✅ | Anti-cheating measures |
| Email Notifications | ⚠️ | Optional, requires mail server |

---

## Network Architecture

```
┌─────────────────────────────────────────┐
│         Intranet Network (Offline)      │
├─────────────────────────────────────────┤
│                                         │
│  ┌──────────────────────────────────┐  │
│  │   Apache Web Server              │  │
│  │   (Port 80)                      │  │
│  │                                  │  │
│  │   ├─ PHP Application            │  │
│  │   ├─ Bootstrap (Local)          │  │
│  │   ├─ Font Awesome (Local)       │  │
│  │   └─ QRCode.js (Local)          │  │
│  └──────────────────────────────────┘  │
│           ↑           ↓                 │
│  ┌──────────────────────────────────┐  │
│  │   MySQL Database                 │  │
│  │   (Port 3306)                    │  │
│  │                                  │  │
│  │   ├─ users                       │  │
│  │   ├─ exams                       │  │
│  │   ├─ questions                   │  │
│  │   ├─ attempts                    │  │
│  │   └─ results                     │  │
│  └──────────────────────────────────┘  │
│                                         │
│  ┌──────────────────────────────────┐  │
│  │   Client Machines                │  │
│  │   (Browsers: Edge, Chrome, FF)   │  │
│  └──────────────────────────────────┘  │
│                                         │
└─────────────────────────────────────────┘
         🚫 No Internet Access
```

---

## Browser Compatibility (Offline)

| Browser | Support | Notes |
|---------|---------|-------|
| Chrome | ✅ | Full support |
| Edge | ✅ | Full support |
| Firefox | ✅ | Full support |
| Safari | ✅ | Full support |
| Mobile Chrome | ✅ | Full support |
| Mobile Safari | ✅ | Full support |

---

## Performance Optimization

### 1. Cache Strategy

```
Browser → Local Cache (CSS/JS/Fonts)
          ↓
       Disk Cache (QR codes at uploads/.qrcache)
          ↓
       Database (MySQL query cache)
```

### 2. Disable Unnecessary Features

```php
// In includes/config.php
define('ENABLE_EMAIL_NOTIFICATIONS', false);  // No internet mail
define('ENABLE_ANALYTICS', true);             // Local analytics OK
define('ENABLE_QR_CACHE', true);              // Cache QR locally
```

### 3. Monitor Performance

```bash
# Check page load time
curl -w "@curl-format.txt" -o /dev/null -s http://intranet-server

# Monitor MySQL
mysqladmin -u root -p processlist
```

---

## Maintenance

### Daily Tasks
```bash
# Monitor error logs
tail -f /var/log/apache2/error.log

# Check disk space
df -h
```

### Weekly Tasks
```bash
# Backup database
mysqldump -u root -p bel_exam_portal > backup-$(date +%Y%m%d).sql

# Clean old QR cache
find uploads/.qrcache -mtime +30 -delete
```

### Monthly Tasks
```bash
# Full system backup
tar -czf backup-$(date +%Y%m%d).tar.gz /var/www/html

# Update admin credentials
# (via Admin Panel)
```

---

## Troubleshooting Offline

### Issue: "Connection Refused"

```bash
# Check if server is running
systemctl status apache2

# Check MySQL
systemctl status mysql
```

### Issue: "No Internet" Warning

```php
// Edit: includes/config.php
// Disable any external checks:
define('CHECK_INTERNET', false);
```

### Issue: Fonts/Icons Not Loading

```bash
# Check fontawesome folder
ls -la assets/lib/fontawesome/webfonts/

# Verify permissions
chmod -R 755 assets/lib/
```

### Issue: QR Code Generation Fails

```bash
# Create QR cache directory
mkdir -p uploads/.qrcache
chmod 777 uploads/.qrcache

# Test QR generation
php assets/lib/qrcode/test.php
```

---

## Verification Checklist

- [ ] Apache running on port 80
- [ ] MySQL running on port 3306
- [ ] Database schema imported
- [ ] File permissions set (755/777)
- [ ] Bootstrap CSS loading (check browser DevTools)
- [ ] Font Awesome icons visible
- [ ] QR code generating (test admit card)
- [ ] Admin login working
- [ ] Student exams functional
- [ ] No external HTTP requests (test with offline)

---

## Backup Offline Server

```bash
# Create monthly backup
tar -czf bel-portal-backup-$(date +%Y%m%d).tar.gz \
  /var/www/html \
  /var/lib/mysql/bel_exam_portal

# Store on external drive
cp *.tar.gz /mnt/backup-drive/
```

---

## Deployment Completion

Once verified, the system is ready for:
- ✅ Government examination use
- ✅ Offline/intranet-only networks
- ✅ Restricted access environments
- ✅ Production exams with full security

---

## Additional Resources

- [Installation Guide](INSTALLATION.md) - Full setup
- [Troubleshooting](TROUBLESHOOTING.md) - Common issues
- [Testing Report](TESTING.md) - Verification tests
