<?php
// ============================================================================
// BEL Kotdwar Exam Portal — Central Config
// ============================================================================

// ----- Database (XAMPP defaults) -----
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'bel_exam_portal');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP default is empty

// ----- App -----
define('APP_NAME', 'BEL Kotdwar — Examination Portal');
define('ADMIN_EMAIL', 'admin@belkotdwar.in');
define('ADMIN_PASSWORD', 'Admin@123');     // initial seed password
define('ADMIN_NAME', 'BEL Kotdwar Super Admin');
define('APP_TIMEZONE', 'Asia/Kolkata');
define('MAX_VIOLATIONS', 5);
define('SESSION_NAME', 'bel_exam');

// ----- SMTP (PHPMailer-style) -----
// Leave SMTP_HOST blank to disable email. Common settings:
//   Gmail:  host=smtp.gmail.com port=587 secure=tls user=youraddr@gmail.com pass=<App Password>
//   BEL relay: host=smtp.bel.local port=25 secure='' user='' pass=''
define('SMTP_HOST',         '');                                 // e.g. 'smtp.gmail.com'
define('SMTP_PORT',         587);
define('SMTP_SECURE',       'tls');                              // '', 'tls', or 'ssl'
define('SMTP_USER',         '');
define('SMTP_PASS',         '');
define('SMTP_FROM_EMAIL',   'no-reply@belkotdwar.in');
define('SMTP_FROM_NAME',    'BEL Kotdwar Exam Portal');
define('SMTP_FROM_DOMAIN',  'belkotdwar.in');

// ----- Photo uploads -----
define('PHOTO_DIR', __DIR__ . '/../uploads/photos');
define('PHOTO_URL_PREFIX', 'uploads/photos/');
define('MAX_PHOTO_SIZE', 1024 * 1024 * 2);  // 2 MB

// ----- Boot -----
date_default_timezone_set(APP_TIMEZONE);
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// ----- PDO connection -----
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:2em;background:#fee;color:#900;border:2px solid #c00;margin:2em">
                <h2>Database Connection Failed</h2>
                <p>Could not connect to MySQL database <code>' . DB_NAME . '</code>.</p>
                <p><b>Steps:</b></p>
                <ol>
                  <li>Open XAMPP Control Panel → Start <b>Apache</b> and <b>MySQL</b></li>
                  <li>Open <a href="http://localhost/phpmyadmin" target="_blank">phpMyAdmin</a></li>
                  <li>Import <code>htdocs/schema.sql</code> to create the <code>bel_exam_portal</code> database</li>
                  <li>Verify credentials in <code>htdocs/includes/config.php</code></li>
                </ol>
                <p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>
                </div>');
        }
    }
    return $pdo;
}

// ----- Auto reseed admin password to match config if out-of-sync -----
function ensureSuperAdmin(): void {
    try {
        $pdo = db();
        $row = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? AND role = "admin"');
        $row->execute([ADMIN_EMAIL]);
        $u = $row->fetch();
        if (!$u) {
            $ins = $pdo->prepare('INSERT INTO users (role,name,email,username,password_hash,is_super)
                                   VALUES ("admin", ?, ?, "superadmin", ?, 1)');
            $ins->execute([ADMIN_NAME, ADMIN_EMAIL, password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT)]);
        } elseif (!password_verify(ADMIN_PASSWORD, $u['password_hash'])) {
            $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upd->execute([password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT), $u['id']]);
        }
    } catch (Throwable $e) { /* DB not ready yet */ }
}
ensureSuperAdmin();
