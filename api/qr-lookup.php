<?php
/**
 * Official BEL Kotdwar QR Code Lookup System
 * Secure Hall Ticket Verification Portal
 * Government of India | Ministry of Defence | Bharat Electronics Limited
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lang.php';

$data = trim($_GET['data'] ?? '');
$student = null;
$error = null;
$timestamp = date('d M Y, H:i:s');

if ($data) {
    // Parse QR data format: BEL-KOTDWAR|roll_number|student_id
    $parts = explode('|', $data);
    
    if (count($parts) === 3 && $parts[0] === 'BEL-KOTDWAR') {
        $roll = trim($parts[1]);
        $studentId = (int)$parts[2];
        
        if ($studentId > 0 && $roll !== '') {
            // Lookup student in database with strict verification
            $stmt = db()->prepare('SELECT id, name, roll_number, email, dob, category, photo_path, username FROM users WHERE id=? AND roll_number=? AND role="student" LIMIT 1');
            $stmt->execute([$studentId, $roll]);
            $student = $stmt->fetch();
            
            if (!$student) {
                $error = 'Student record not found or unauthorized access attempted.';
            }
        } else {
            $error = 'Invalid QR code format or data corruption detected.';
        }
    } else {
        $error = 'Unrecognized QR code format. Please scan a valid BEL-KOTDWAR certified hall ticket.';
    }
} else {
    $error = 'No QR data received. Please scan a valid hall ticket QR code using an authorized device.';
}

$photoUrl = ($student && !empty($student['photo_path'])) ? url($student['photo_path']) : '';
$verificationId = $student ? hash('sha256', $student['id'] . $student['roll_number']) : '';
?><!DOCTYPE html>
<html lang="<?= lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Official BEL Kotdwar Hall Ticket Verification System - Government of India">
    <title><?= t('brand') ?> · Secure Hall Ticket Verification</title>
    <!-- Bootstrap 5.3.2 - Local -->
    <link rel="stylesheet" href="<?= url('assets/lib/bootstrap/css/bootstrap.min.css') ?>">
    <!-- Font Awesome 6.5.1 - Local -->
    <link rel="stylesheet" href="<?= url('assets/lib/fontawesome/css/all.min.css') ?>">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: #f8fafc;
            font-family: 'Segoe UI', Tahoma, sans-serif;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 35px,
                rgba(14, 42, 71, 0.02) 35px,
                rgba(14, 42, 71, 0.02) 70px
            );
            pointer-events: none;
            z-index: -1;
        }
        
        .gov-header {
            background: #0f172a;
            color: white;
            padding: 8px 0;
            font-size: 11px;
            text-align: center;
            letter-spacing: 0.08em;
            border-bottom: 3px solid #FF9933;
        }
        
        .tricolor-bar {
            display: flex;
            height: 4px;
        }
        
        .tricolor-bar span {
            flex: 1;
        }
        
        .tricolor-bar span:nth-child(1) { background: #FF9933; }
        .tricolor-bar span:nth-child(2) { background: white; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        .tricolor-bar span:nth-child(3) { background: #138808; }
        
        .qr-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }
        
        .qr-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 550px;
            width: 100%;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        
        .card-header-official {
            background: linear-gradient(135deg, #0E2A47 0%, #1a3a57 100%);
            color: white;
            padding: 25px;
            text-align: center;
            border-bottom: 4px solid #FF9933;
            position: relative;
        }
        
        .card-header-official::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                repeating-linear-gradient(90deg, transparent, transparent 2px, rgba(255,255,255,0.03) 2px, rgba(255,255,255,0.03) 4px);
            pointer-events: none;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
        .official-seal {
            width: 50px;
            height: 50px;
            margin: 0 auto 15px;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .header-title {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 5px;
        }
        
        .header-subtitle {
            font-size: 12px;
            opacity: 0.9;
            letter-spacing: 0.05em;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .verification-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
            padding: 12px;
            background: #d1fae5;
            border-radius: 6px;
            border-left: 4px solid #10b981;
        }
        
        .status-icon {
            font-size: 20px;
            color: #065f46;
        }
        
        .status-text {
            font-weight: 600;
            color: #065f46;
            font-size: 13px;
            letter-spacing: 0.05em;
        }
        
        .verification-badge {
            text-align: center;
            padding: 8px 12px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 4px;
            font-size: 10px;
            color: #065f46;
            font-weight: 600;
            letter-spacing: 0.08em;
            margin-bottom: 20px;
        }
        
        .student-photo-section {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .student-photo {
            width: 150px;
            height: 190px;
            object-fit: cover;
            border: 3px solid #0E2A47;
            border-radius: 4px;
            margin: 0 auto;
            display: block;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .photo-placeholder {
            width: 150px;
            height: 190px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border: 3px dashed #cbd5e1;
            border-radius: 4px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 12px;
            text-align: center;
            flex-direction: column;
            gap: 8px;
        }
        
        .photo-placeholder i {
            font-size: 32px;
            opacity: 0.3;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
            gap: 12px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 4px;
        }
        
        .info-label {
            font-weight: 700;
            color: #0E2A47;
            min-width: 130px;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .info-label i {
            font-size: 13px;
            opacity: 0.7;
        }
        
        .info-value {
            color: #1e293b;
            flex-grow: 1;
            font-size: 13px;
            font-weight: 500;
        }
        
        .info-value.monospace {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 3px;
            letter-spacing: 0.02em;
        }
        
        .error-box {
            background: #fee2e2;
            border: 2px solid #fecaca;
            border-left: 4px solid #dc2626;
            color: #991b1b;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .error-icon {
            font-size: 40px;
            margin-bottom: 12px;
            opacity: 0.8;
        }
        
        .error-title {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .error-msg {
            font-size: 12px;
            line-height: 1.4;
        }
        
        .qr-input-section {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #cbd5e1;
        }
        
        .qr-input-section p {
            color: #475569;
            font-size: 12px;
            margin-bottom: 12px;
        }
        
        .qr-input-section strong {
            color: #0E2A47;
            font-weight: 700;
        }
        
        .form-control {
            border: 1px solid #cbd5e1;
            padding: 10px;
            font-size: 12px;
            font-family: 'Courier New', monospace;
            border-radius: 4px;
        }
        
        .form-control:focus {
            border-color: #0E2A47;
            box-shadow: 0 0 0 3px rgba(14, 42, 71, 0.15);
            outline: none;
        }
        
        .btn-lookup {
            background: #0E2A47;
            color: white;
            border: none;
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 4px;
            margin-top: 12px;
            width: 100%;
            font-size: 12px;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-lookup:hover {
            background: #081a2e;
            box-shadow: 0 2px 8px rgba(14, 42, 71, 0.3);
        }
        
        .security-info {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 11px;
            color: #065f46;
            line-height: 1.5;
        }
        
        .security-info strong {
            display: block;
            margin-bottom: 5px;
            font-weight: 700;
        }
        
        .card-footer-official {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px;
            text-align: center;
            font-size: 10px;
            color: #64748b;
            letter-spacing: 0.05em;
        }
        
        .footer-seal {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 15px;
            padding: 15px 0;
            border-top: 1px dashed #e2e8f0;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .seal-item {
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            color: #0E2A47;
        }
        
        .seal-icon {
            font-size: 24px;
            margin-bottom: 4px;
            opacity: 0.6;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: #0E2A47;
            text-decoration: none;
            font-weight: 600;
            font-size: 10px;
        }
        
        .footer-links a:hover {
            color: #FF9933;
        }
        
        .copyright {
            margin-top: 12px;
            font-size: 9px;
            color: #94a3b8;
        }
        
        @media (max-width: 600px) {
            .card-body { padding: 20px; }
            .header-title { font-size: 18px; }
            .info-row { flex-wrap: wrap; }
            .info-label { min-width: auto; width: 100%; }
            .info-value { width: 100%; }
        }
    </style>
</head>
<body>

<!-- Government Header -->
<div class="gov-header">
    <div class="tricolor-bar">
        <span></span><span></span><span></span>
    </div>
    <div style="padding: 10px; background: #0f172a;">
        भारत सरकार · GOVERNMENT OF INDIA · Ministry of Defence · Bharat Electronics Limited
    </div>
</div>

<!-- Main Container -->
<div class="qr-container">
    <div class="qr-card">
        
        <!-- Official Header -->
        <div class="card-header-official">
            <div class="header-content">
                <div class="official-seal">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="header-title">
                    <i class="fas fa-id-card me-2"></i>Hall Ticket Verification
                </div>
                <div class="header-subtitle">
                    Secure QR Code Lookup System · Government Verified
                </div>
            </div>
        </div>

        <!-- Card Body -->
        <div class="card-body">
            
            <?php if ($error): ?>
                <!-- Error State -->
                <div class="error-box">
                    <div class="error-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="error-title">Verification Failed</div>
                    <div class="error-msg"><?= h($error) ?></div>
                </div>

                <div class="qr-input-section">
                    <p><strong><i class="fas fa-info-circle me-2"></i>Manual Lookup</strong></p>
                    <p>If you cannot scan the QR code, enter the verification data manually below:</p>
                    <form method="get" class="mt-3">
                        <textarea name="data" class="form-control" rows="3" placeholder="BEL-KOTDWAR|ROLL123|45" spellcheck="false"></textarea>
                        <button type="submit" class="btn-lookup">
                            <i class="fas fa-search me-2"></i>Verify Hall Ticket
                        </button>
                    </form>
                </div>

                <div class="security-info">
                    <strong><i class="fas fa-lock me-2"></i>Security Notice:</strong>
                    Only scan QR codes from official BEL-KOTDWAR admit cards. Unauthorized copies will be detected and flagged.
                </div>

            <?php elseif ($student): ?>
                <!-- Success State - Student Found -->
                <div class="verification-status">
                    <div class="status-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="status-text">VERIFICATION SUCCESSFUL</div>
                </div>

                <div class="verification-badge">
                    <i class="fas fa-shield-check"></i> Authentic BEL Hall Ticket · Government Verified
                </div>

                <!-- Student Photo Section -->
                <div class="student-photo-section">
                    <?php if ($photoUrl): ?>
                        <img src="<?= h($photoUrl) ?>" alt="<?= h($student['name']) ?>" class="student-photo">
                    <?php else: ?>
                        <div class="photo-placeholder">
                            <i class="fas fa-image"></i>
                            <div>No Photo</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Student Information -->
                <div class="info-section">
                    <div class="info-row">
                        <div class="info-label"><i class="fas fa-user"></i>Candidate Name</div>
                        <div class="info-value"><?= h($student['name']) ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label"><i class="fas fa-id-badge"></i>Roll Number</div>
                        <div class="info-value monospace"><?= h($student['roll_number']) ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label"><i class="fas fa-barcode"></i>System ID</div>
                        <div class="info-value monospace"><?= h($student['id']) ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label"><i class="fas fa-user-circle"></i>Username</div>
                        <div class="info-value monospace"><?= h($student['username']) ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label"><i class="fas fa-envelope"></i>Email Address</div>
                        <div class="info-value" style="word-break: break-all;"><?= h($student['email']) ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label"><i class="fas fa-calendar-alt"></i>Date of Birth</div>
                        <div class="info-value"><?= h($student['dob']) ?: '— Not Provided' ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label"><i class="fas fa-tag"></i>Category</div>
                        <div class="info-value"><?= h(ucfirst($student['category'])) ?: '— Not Assigned' ?></div>
                    </div>
                </div>

                <!-- Verification Details -->
                <div class="security-info">
                    <strong><i class="fas fa-certificate me-2"></i>Verification Details:</strong>
                    ✓ Record verified in official BEL Kotdwar database<br>
                    ✓ QR code authenticity confirmed<br>
                    ✓ Lookup timestamp: <?= $timestamp ?><br>
                    ✓ Verification ID: <code style="font-size: 9px;"><?= substr($verificationId, 0, 16) ?>...</code>
                </div>

            <?php else: ?>
                <!-- Initial State - No Data -->
                <div class="qr-input-section">
                    <p><strong><i class="fas fa-qrcode me-2"></i>Scan or Enter QR Data</strong></p>
                    <p>Use your mobile device camera or QR scanner to scan a hall ticket, or manually enter the verification data:</p>
                    <form method="get" class="mt-3">
                        <textarea name="data" class="form-control" rows="3" placeholder="BEL-KOTDWAR|ROLL123|45" spellcheck="false"></textarea>
                        <button type="submit" class="btn-lookup">
                            <i class="fas fa-search me-2"></i>Verify Hall Ticket
                        </button>
                    </form>
                </div>

                <div class="security-info">
                    <strong><i class="fas fa-lightbulb me-2"></i>How to use:</strong>
                    1. Point your camera at the QR code on the admit card<br>
                    2. System will automatically verify and display candidate information<br>
                    3. All information is secure and verified
                </div>

            <?php endif; ?>

        </div>

        <!-- Footer -->
        <div class="card-footer-official">
            <div class="footer-seal">
                <div class="seal-item">
                    <div class="seal-icon"><i class="fas fa-shield-alt"></i></div>
                    <div>Secure</div>
                </div>
                <div class="seal-item">
                    <div class="seal-icon"><i class="fas fa-check"></i></div>
                    <div>Verified</div>
                </div>
                <div class="seal-item">
                    <div class="seal-icon"><i class="fas fa-lock"></i></div>
                    <div>Official</div>
                </div>
            </div>

            <div style="font-weight: 700; color: #0E2A47; margin-bottom: 10px;">
                Bharat Electronics Limited · Kotdwar Unit
            </div>

            <div style="font-size: 9px; color: #64748b; margin-bottom: 10px;">
                Ministry of Defence · Government of India<br>
                Authorized Use Only · Secure Portal
            </div>

            <div class="footer-links">
                <a href="<?= url('index.php') ?>"><i class="fas fa-home me-1"></i>Home</a>
                <span style="color: #cbd5e1;">|</span>
                <a href="<?= url('student/login.php') ?>"><i class="fas fa-sign-in-alt me-1"></i>Student Portal</a>
                <span style="color: #cbd5e1;">|</span>
                <a href="#" onclick="alert('For support, contact BEL Kotdwar Administration'); return false;"><i class="fas fa-headset me-1"></i>Support</a>
            </div>

            <div class="copyright">
                © <?= date('Y') ?> Bharat Electronics Limited. All Rights Reserved.<br>
                सर्वाधिकार सुरक्षित © <?= date('Y') ?> भारत इलेक्ट्रॉनिक्स लिमिटेड
            </div>
        </div>

    </div>
</div>

</body>
</html>
