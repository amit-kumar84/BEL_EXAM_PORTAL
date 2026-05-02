<?php
/**
 * Test Login - Login Testing Utility
 * Access: http://localhost/debug/test_login.php
 * 
 * Simulates login and provides test credentials
 */

session_start();
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bel_exam_portal');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->query("USE " . DB_NAME);
    $db_ok = true;
} catch (Exception $e) {
    $db_ok = false;
    $db_error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>🧪 Test Login - BEL Exam Portal</title>
    <link href="../assets/lib/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 20px; }
        .test-container { max-width: 800px; margin: 0 auto; }
        .card { border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .card-header { background: #0E2A47; color: white; font-weight: bold; padding: 20px; }
        .test-row { padding: 15px; border-bottom: 1px solid #e5e7eb; }
        .test-row:last-child { border-bottom: none; }
        .test-label { font-weight: 600; color: #0E2A47; margin-bottom: 5px; }
        .test-value { background: #f3f4f6; padding: 8px 12px; border-radius: 4px; font-family: monospace; margin-bottom: 8px; }
        .copy-btn { cursor: pointer; padding: 4px 8px; background: #0E2A47; color: white; border: none; border-radius: 3px; font-size: 12px; }
        .status-ok { color: #10b981; font-weight: bold; }
        .status-error { color: #ef4444; font-weight: bold; }
        .btn-group-custom { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
    </style>
</head>
<body>

<div class="test-container">
    <h1 style="color: white; margin-bottom: 30px; text-align: center;">🧪 Test Login Credentials</h1>

    <?php if (!$db_ok): ?>
        <div class="alert alert-danger">
            <strong>❌ Database Connection Error:</strong><br>
            <?= $db_error ?>
        </div>
    <?php endif; ?>

    <!-- ADMIN LOGIN -->
    <div class="card mb-4">
        <div class="card-header">
            🔐 Admin Account
        </div>
        <div class="card-body">
            <div class="test-row">
                <div class="test-label">Email:</div>
                <div class="test-value" onclick="copyToClipboard(this)">admin@belkotdwar.in</div>
                <small>Click to copy</small>
            </div>
            <div class="test-row">
                <div class="test-label">Password:</div>
                <div class="test-value" onclick="copyToClipboard(this)">Admin@123</div>
                <small>Click to copy</small>
            </div>
            <div class="test-row">
                <div class="test-label">Access:</div>
                <div class="test-value">Full admin access, all features</div>
            </div>
            <div class="btn-group-custom">
                <a href="/admin/login.php" class="btn btn-primary btn-sm">→ Go to Admin Login</a>
            </div>
        </div>
    </div>

    <!-- STUDENT LOGIN -->
    <div class="card mb-4">
        <div class="card-header">
            👨‍🎓 Student Account
        </div>
        <div class="card-body">
            <div class="test-row">
                <div class="test-label">Roll Number:</div>
                <div class="test-value" onclick="copyToClipboard(this)">BEL-KOT-001</div>
                <small>Click to copy</small>
            </div>
            <div class="test-row">
                <div class="test-label">Password:</div>
                <div class="test-value" onclick="copyToClipboard(this)">Welcome@123</div>
                <small>Click to copy</small>
            </div>
            <div class="test-row">
                <div class="test-label">Access:</div>
                <div class="test-value">Student exam access, can take exams</div>
            </div>
            <div class="btn-group-custom">
                <a href="/student/login.php" class="btn btn-primary btn-sm">→ Go to Student Login</a>
            </div>
        </div>
    </div>

    <!-- DATABASE INFO -->
    <?php if ($db_ok): ?>
    <div class="card mb-4">
        <div class="card-header">
            💾 Database Information
        </div>
        <div class="card-body">
            <?php
            try {
                // Get user count
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' as admin_count");
                $adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                $studentCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
                $examCount = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
                $questionCount = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
                
                echo '<div class="test-row">';
                echo '<div class="test-label">Admin Users:</div>';
                echo '<div class="test-value">' . $adminCount . ' (should be 1)</div>';
                echo '</div>';
                
                echo '<div class="test-row">';
                echo '<div class="test-label">Student Users:</div>';
                echo '<div class="test-value">' . $studentCount . '</div>';
                echo '</div>';
                
                echo '<div class="test-row">';
                echo '<div class="test-label">Exams:</div>';
                echo '<div class="test-value">' . $examCount . '</div>';
                echo '</div>';
                
                echo '<div class="test-row">';
                echo '<div class="test-label">Questions:</div>';
                echo '<div class="test-value">' . $questionCount . '</div>';
                echo '</div>';
            } catch (Exception $e) {
                echo '<div class="alert alert-warning">Error reading database: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- QUICK TEST -->
    <div class="card mb-4">
        <div class="card-header">
            ⚡ Quick Tests
        </div>
        <div class="card-body">
            <div class="btn-group-custom">
                <a href="/debug/debug_report.php" class="btn btn-info btn-sm">🔍 Debug Report</a>
                <a href="/debug/offline_test.php" class="btn btn-info btn-sm">📊 Offline Test</a>
                <a href="/debug/phpinfo.php" class="btn btn-secondary btn-sm">ℹ️ PHP Info</a>
            </div>
        </div>
    </div>

    <!-- NOTES -->
    <div class="alert alert-info">
        <strong>ℹ️ Notes:</strong>
        <ul style="margin-bottom: 0;">
            <li>These are test credentials for development/debugging</li>
            <li>Change admin password on first login in production</li>
            <li>Add more student users via Admin > Students panel</li>
            <li>Never share these credentials in production</li>
        </ul>
    </div>

</div>

<script>
function copyToClipboard(element) {
    const text = element.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const original = element.textContent;
        element.textContent = '✓ Copied!';
        setTimeout(() => {
            element.textContent = original;
        }, 2000);
    });
}
</script>

<script src="../assets/lib/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
