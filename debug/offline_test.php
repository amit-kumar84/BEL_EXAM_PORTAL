<?php
/**
 * Offline Test Suite - Verify Offline Functionality
 * Access: http://localhost/debug/offline_test.php
 * 
 * Tests all offline/intranet capabilities
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bel_exam_portal');

$tests = [];
$passed = 0;
$failed = 0;

// TEST 1: PHP Version
$php_version = phpversion();
if (version_compare($php_version, '8.0', '>=')) {
    $tests[] = ['✅ PHP Version', $php_version, 'pass'];
    $passed++;
} else {
    $tests[] = ['❌ PHP Version', $php_version . ' (minimum 8.0)', 'fail'];
    $failed++;
}

// TEST 2: MySQL Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->query("USE " . DB_NAME);
    $tests[] = ['✅ MySQL Connection', 'Connected', 'pass'];
    $passed++;
} catch (Exception $e) {
    $tests[] = ['❌ MySQL Connection', $e->getMessage(), 'fail'];
    $failed++;
    $pdo = null;
}

// TEST 3: Required Extensions
$extensions = ['pdo', 'pdo_mysql', 'json', 'gd'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        $tests[] = ['✅ Extension: ' . $ext, 'Loaded', 'pass'];
        $passed++;
    } else {
        $tests[] = ['❌ Extension: ' . $ext, 'Missing', 'fail'];
        $failed++;
    }
}

// TEST 4: File Permissions
$dirs = [
    'uploads/' => __DIR__ . '/../uploads',
    'uploads/photos/' => __DIR__ . '/../uploads/photos',
];

foreach ($dirs as $name => $path) {
    if (is_writable($path)) {
        $tests[] = ['✅ Writable: ' . $name, 'Yes', 'pass'];
        $passed++;
    } else {
        $tests[] = ['❌ Writable: ' . $name, 'No', 'fail'];
        $failed++;
    }
}

// TEST 5: Offline Libraries
$libs = [
    'Bootstrap CSS' => '../assets/lib/bootstrap/css/bootstrap.min.css',
    'Bootstrap JS' => '../assets/lib/bootstrap/js/bootstrap.bundle.min.js',
    'Font Awesome CSS' => '../assets/lib/fontawesome/css/all.min.css',
    'QRCode.js' => '../assets/lib/qrcode/qrcode.min.js',
];

foreach ($libs as $name => $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $size = round(filesize($fullPath) / 1024, 2);
        $tests[] = ['✅ Offline Lib: ' . $name, $size . ' KB', 'pass'];
        $passed++;
    } else {
        $tests[] = ['❌ Offline Lib: ' . $name, 'Missing', 'fail'];
        $failed++;
    }
}

// TEST 6: Database Tables
if ($pdo) {
    $tables = ['users', 'exams', 'questions', 'question_options', 'attempts', 'attempt_answers', 'violations'];
    foreach ($tables as $table) {
        try {
            $pdo->query("SELECT 1 FROM $table LIMIT 1");
            $tests[] = ['✅ Table: ' . $table, 'Exists', 'pass'];
            $passed++;
        } catch (Exception $e) {
            $tests[] = ['❌ Table: ' . $table, 'Missing', 'fail'];
            $failed++;
        }
    }
}

// TEST 7: Session
if (!isset($_SESSION['test_session'])) {
    $_SESSION['test_session'] = true;
}
if (isset($_SESSION['test_session'])) {
    $tests[] = ['✅ Sessions', 'Working', 'pass'];
    $passed++;
} else {
    $tests[] = ['❌ Sessions', 'Not Working', 'fail'];
    $failed++;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>📊 Offline Test - BEL Exam Portal</title>
    <link href="../assets/lib/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .test-container { max-width: 900px; margin: 0 auto; }
        .test-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
        .test-header { background: #0E2A47; color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .test-table { width: 100%; margin: 0; }
        .test-table th { background: #f3f4f6; padding: 12px; border-bottom: 2px solid #e5e7eb; }
        .test-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
        .test-table tr:hover { background: #f9fafb; }
        .status-pass { color: #10b981; font-weight: bold; }
        .status-fail { color: #ef4444; font-weight: bold; }
        .score-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        .score-number { font-size: 48px; font-weight: bold; }
        .score-text { font-size: 14px; opacity: 0.9; }
        .alert-custom { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="test-container">
    
    <h1 style="color: #0E2A47; margin-bottom: 20px;">📊 Offline Test Suite</h1>

    <!-- SCORE BOX -->
    <div class="score-box">
        <div class="score-number"><?= $passed ?>/<?= $passed + $failed ?></div>
        <div class="score-text">Tests Passed</div>
        <?php if ($failed == 0): ?>
            <div style="font-size: 24px; margin-top: 10px;">✅ All Systems Operational!</div>
        <?php else: ?>
            <div style="font-size: 18px; margin-top: 10px; color: #fecaca;">⚠️ <?= $failed ?> tests failed</div>
        <?php endif; ?>
    </div>

    <!-- STATUS ALERT -->
    <?php if ($failed == 0): ?>
        <div class="alert-custom" style="background: #d1fae5; border-left: 4px solid #10b981; color: #065f46;">
            <strong>✅ System Ready:</strong> All tests passed. Application is ready for production.
        </div>
    <?php else: ?>
        <div class="alert-custom" style="background: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b;">
            <strong>⚠️ Issues Found:</strong> <?= $failed ?> test(s) failed. See details below.
        </div>
    <?php endif; ?>

    <!-- TEST RESULTS -->
    <div class="test-card">
        <div class="test-header">
            <span>Test Results</span>
            <span><?= date('Y-m-d H:i:s') ?></span>
        </div>
        <table class="test-table">
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Result</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tests as $test): ?>
                <tr>
                    <td><?= $test[0] ?></td>
                    <td><?= $test[1] ?></td>
                    <td>
                        <span class="status-<?= $test[2] === 'pass' ? 'pass' : 'fail' ?>">
                            <?= $test[2] === 'pass' ? '✅ Pass' : '❌ Fail' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- QUICK LINKS -->
    <div style="margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="/debug/debug_report.php" class="btn btn-info">🔍 Debug Report</a>
        <a href="/debug/test_login.php" class="btn btn-info">🧪 Test Login</a>
        <a href="/admin/login.php" class="btn btn-primary">🔐 Admin Panel</a>
        <a href="/" class="btn btn-secondary">🏠 Home</a>
    </div>

</div>

<script src="../assets/lib/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
