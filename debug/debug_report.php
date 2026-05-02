<?php
/**
 * Debug Report - System & Application Diagnostics
 * Access: http://localhost/debug/debug_report.php
 * 
 * This file provides comprehensive system information for troubleshooting
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Change if needed
define('DB_NAME', 'bel_exam_portal');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>🔍 Debug Report - BEL Exam Portal</title>
    <link href="../assets/lib/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .debug-card { background: white; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .debug-header { background: #0E2A47; color: white; padding: 15px; border-radius: 8px 8px 0 0; font-weight: bold; font-size: 18px; }
        .debug-body { padding: 15px; }
        .status-ok { color: #10b981; font-weight: bold; }
        .status-error { color: #ef4444; font-weight: bold; }
        .status-warning { color: #f59e0b; font-weight: bold; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
        pre { background: #1f2937; color: #10b981; padding: 15px; border-radius: 6px; overflow-x: auto; }
        table { width: 100%; margin-top: 10px; }
        table th { background: #f3f4f6; padding: 10px; text-align: left; }
        table td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>

<div class="container-lg">
    <h1 style="color: #0E2A47; margin-bottom: 30px;">🔍 Debug Report</h1>

    <!-- PHP INFO -->
    <div class="debug-card">
        <div class="debug-header">PHP Configuration</div>
        <div class="debug-body">
            <table>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>PHP Version</td>
                    <td><span class="status-ok"><?= phpversion() ?></span></td>
                </tr>
                <tr>
                    <td>Server API</td>
                    <td><?= php_sapi_name() ?></td>
                </tr>
                <tr>
                    <td>OS</td>
                    <td><?= PHP_OS . ' (' . PHP_OS_FAMILY . ')' ?></td>
                </tr>
                <tr>
                    <td>Max Upload Size</td>
                    <td><?= ini_get('upload_max_filesize') ?></td>
                </tr>
                <tr>
                    <td>Max POST Size</td>
                    <td><?= ini_get('post_max_size') ?></td>
                </tr>
                <tr>
                    <td>Memory Limit</td>
                    <td><?= ini_get('memory_limit') ?></td>
                </tr>
                <tr>
                    <td>Max Execution Time</td>
                    <td><?= ini_get('max_execution_time') ?> seconds</td>
                </tr>
                <tr>
                    <td>Session Save Path</td>
                    <td><code><?= ini_get('session.save_path') ?></code></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- EXTENSIONS -->
    <div class="debug-card">
        <div class="debug-header">Required Extensions</div>
        <div class="debug-body">
            <table>
                <tr>
                    <th>Extension</th>
                    <th>Status</th>
                </tr>
                <?php
                $extensions = ['pdo', 'pdo_mysql', 'json', 'gd', 'mbstring', 'openssl', 'curl', 'spl', 'hash'];
                foreach ($extensions as $ext):
                    $loaded = extension_loaded($ext) ? '✅ Loaded' : '❌ Missing';
                    $class = extension_loaded($ext) ? 'status-ok' : 'status-error';
                ?>
                <tr>
                    <td><?= ucfirst($ext) ?></td>
                    <td><span class="<?= $class ?>"><?= $loaded ?></span></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- DATABASE -->
    <div class="debug-card">
        <div class="debug-header">Database Connection</div>
        <div class="debug-body">
            <?php
            try {
                $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo '<p><span class="status-ok">✅ Connected</span></p>';
                
                // Database info
                $result = $pdo->query("SELECT VERSION() as version");
                $version = $result->fetch(PDO::FETCH_ASSOC);
                echo '<table>';
                echo '<tr><th>Property</th><th>Value</th></tr>';
                echo '<tr><td>Host</td><td>' . DB_HOST . '</td></tr>';
                echo '<tr><td>Database</td><td>' . DB_NAME . '</td></tr>';
                echo '<tr><td>MySQL Version</td><td>' . $version['version'] . '</td></tr>';
                
                // Table count
                $tables = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetch(PDO::FETCH_ASSOC);
                echo '<tr><td>Tables in Database</td><td>' . $tables['count'] . '</td></tr>';
                echo '</table>';
                
                // List tables
                $tableList = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                echo '<p style="margin-top: 15px;"><strong>Tables:</strong> ' . implode(', ', $tableList) . '</p>';
                
            } catch (PDOException $e) {
                echo '<p><span class="status-error">❌ Connection Failed</span></p>';
                echo '<p><strong>Error:</strong> ' . $e->getMessage() . '</p>';
                echo '<p><strong>Check:</strong></p>';
                echo '<ul>';
                echo '<li>MySQL server is running (port 3306)</li>';
                echo '<li>Database exists: ' . DB_NAME . '</li>';
                echo '<li>Credentials: ' . DB_USER . ' @ ' . DB_HOST . '</li>';
                echo '</ul>';
            }
            ?>
        </div>
    </div>

    <!-- FILE SYSTEM -->
    <div class="debug-card">
        <div class="debug-header">File System Permissions</div>
        <div class="debug-body">
            <table>
                <tr>
                    <th>Directory</th>
                    <th>Status</th>
                    <th>Writable</th>
                </tr>
                <?php
                $dirs = [
                    'uploads/' => __DIR__ . '/../uploads',
                    'uploads/photos/' => __DIR__ . '/../uploads/photos',
                    'uploads/.qrcache/' => __DIR__ . '/../uploads/.qrcache',
                    'assets/lib/' => __DIR__ . '/../assets/lib',
                ];
                
                foreach ($dirs as $name => $path):
                    $exists = is_dir($path) ? '✅ Exists' : '❌ Missing';
                    $writable = is_writable($path) ? '✅ Yes' : '❌ No';
                    $class = is_dir($path) ? 'status-ok' : 'status-error';
                ?>
                <tr>
                    <td><?= $name ?></td>
                    <td><span class="<?= $class ?>"><?= $exists ?></span></td>
                    <td><?= $writable ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- OFFLINE LIBRARIES -->
    <div class="debug-card">
        <div class="debug-header">Offline Libraries Status</div>
        <div class="debug-body">
            <table>
                <tr>
                    <th>Library</th>
                    <th>Version</th>
                    <th>Status</th>
                </tr>
                <?php
                $libs = [
                    'Bootstrap' => '../assets/lib/bootstrap/css/bootstrap.min.css',
                    'Font Awesome' => '../assets/lib/fontawesome/css/all.min.css',
                    'QRCode.js' => '../assets/lib/qrcode/qrcode.min.js',
                ];
                
                foreach ($libs as $name => $file):
                    $fullPath = __DIR__ . '/' . $file;
                    $exists = file_exists($fullPath) ? '✅ Present' : '❌ Missing';
                    $class = file_exists($fullPath) ? 'status-ok' : 'status-error';
                    $size = file_exists($fullPath) ? round(filesize($fullPath) / 1024, 2) . ' KB' : 'N/A';
                ?>
                <tr>
                    <td><?= $name ?></td>
                    <td><?= $size ?></td>
                    <td><span class="<?= $class ?>"><?= $exists ?></span></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- SYSTEM INFO -->
    <div class="debug-card">
        <div class="debug-header">Server Information</div>
        <div class="debug-body">
            <table>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Server Name</td>
                    <td><?= $_SERVER['SERVER_NAME'] ?? 'localhost' ?></td>
                </tr>
                <tr>
                    <td>Server Port</td>
                    <td><?= $_SERVER['SERVER_PORT'] ?? '80' ?></td>
                </tr>
                <tr>
                    <td>Server Software</td>
                    <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td>
                </tr>
                <tr>
                    <td>Document Root</td>
                    <td><code><?= $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' ?></code></td>
                </tr>
                <tr>
                    <td>Current File</td>
                    <td><code><?= __FILE__ ?></code></td>
                </tr>
                <tr>
                    <td>Script URL</td>
                    <td><code><?= $_SERVER['REQUEST_URI'] ?? 'Unknown' ?></code></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- QUICK LINKS -->
    <div class="debug-card">
        <div class="debug-header">Quick Links</div>
        <div class="debug-body">
            <p>
                <a href="/" class="btn btn-primary btn-sm">🏠 Home</a>
                <a href="/admin/login.php" class="btn btn-info btn-sm">🔐 Admin Login</a>
                <a href="/student/login.php" class="btn btn-info btn-sm">👨‍🎓 Student Login</a>
                <a href="test_login.php" class="btn btn-warning btn-sm">🧪 Test Login</a>
                <a href="offline_test.php" class="btn btn-warning btn-sm">📊 Offline Test</a>
                <a href="phpinfo.php" class="btn btn-secondary btn-sm">ℹ️ PHP Info</a>
            </p>
        </div>
    </div>

</div>

<script src="../assets/lib/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
