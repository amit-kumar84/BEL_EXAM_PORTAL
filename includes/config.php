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

// ----- Super-admin self-edit verification (developer-set) -----
// Super admin must answer this question correctly to change their own email/password.
// Only the developer can change these values — edit this file directly.
define('SUPER_VERIFY_QUESTION', 'In which city was BEL Kotdwar unit established?');
// Store only the SHA-256 hash of the lowercase-trimmed answer. Never the plaintext.
// Developer workflow: run `php -r "echo hash('sha256', strtolower(trim('your-answer')));"` and paste below.
// Default answer (developer should change this): "kotdwar"
define('SUPER_VERIFY_ANSWER_HASH', '3b3d64d2ebb382eda36ab86f370627f79560a0f680639a604a92acd3a7861aa6');

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

// ----- Seed super-admin ONCE if missing; never force-reset password after that -----
// Super admin can later self-edit email/password via admin/admins.php (with developer verification).
function ensureSuperAdmin(): void {
    try {
        $pdo = db();
        // Look for ANY super admin (is_super=1) — not just the seed email.
        // Super admin may have changed their own email after seeding; that's fine.
        $any = $pdo->query('SELECT id FROM users WHERE role="admin" AND is_super=1 LIMIT 1')->fetch();
        if ($any) return; // Already have a super admin — do not touch.
        // No super admin yet → seed using config values.
        $ins = $pdo->prepare('INSERT INTO users (role,name,email,username,password_hash,is_super)
                               VALUES ("admin", ?, ?, "superadmin", ?, 1)');
        $ins->execute([ADMIN_NAME, ADMIN_EMAIL, password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT)]);
    } catch (Throwable $e) { /* DB not ready yet */ }
}
ensureSuperAdmin();
