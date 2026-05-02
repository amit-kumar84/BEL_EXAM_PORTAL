<?php
require_once __DIR__ . '/config.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $to): void { header('Location: ' . $to); exit; }

function flash(?string $msg = null, string $type = 'info') {
    if ($msg !== null) {
        $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
        return;
    }
    $m = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $m;
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_input(): string { return '<input type="hidden" name="_csrf" value="' . h(csrf()) . '">'; }
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(400); die('CSRF validation failed');
    }
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function require_login(string $role): array {
    $u = current_user();
    if (!$u || $u['role'] !== $role) {
        redirect(url($role === 'admin' ? 'admin/login.php' : 'student/login.php'));
    }
    return $u;
}

function gen_password(int $len = 10): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $out;
}

function gen_username(string $name): string {
    $base = preg_replace('/[^a-z0-9]/', '', strtolower($name));
    $base = substr($base ?: 'stu', 0, 8);
    return $base . random_int(1000, 9999);
}

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Subfolder detection: if served from /bel-exam/... use that
    // Only apply prefix if app is NOT at root and is in a deployment folder like /bel-exam
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $prefix = '';
    // Check if there's a deployment folder (e.g., /bel-exam/ but NOT /admin/ or /student/)
    if (preg_match('#^(/bel-exam)/#', $script, $m)) {
        $prefix = $m[1];
    }
    return $scheme . '://' . $host . $prefix;
}

function url(string $path): string {
    return base_url() . '/' . ltrim($path, '/');
}

function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function back_with_error(string $msg): void {
    flash($msg, 'error');
    redirect($_SERVER['HTTP_REFERER'] ?? url('index.php'));
}

function fmt_dt($dt): string {
    if (!$dt) return '—';
    return date('d M Y, H:i', strtotime($dt));
}

function exam_status(array $e): string {
    $now = time();
    if ($now < strtotime($e['start_time'])) return 'upcoming';
    if ($now > strtotime($e['end_time']))   return 'closed';
    return 'active';
}

// Save uploaded candidate photo to /uploads/photos. Returns relative URL path or null.
function save_candidate_photo(array $file, string $rollSlug): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) throw new Exception('Upload error code ' . $file['error']);
    if (($file['size'] ?? 0) > MAX_PHOTO_SIZE) throw new Exception('Photo exceeds 2 MB limit');
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) throw new Exception('Only JPG, PNG, WEBP photos allowed');
    if (!is_dir(PHOTO_DIR)) @mkdir(PHOTO_DIR, 0775, true);
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $rollSlug ?: 'cand');
    $fname = $slug . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = PHOTO_DIR . '/' . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('Failed to save photo');
    @chmod($dest, 0644);
    return PHOTO_URL_PREFIX . $fname;
}

// Return list of exam IDs assigned to a student
function assigned_exam_ids(int $userId): array {
    if (!table_exists('exam_assignments')) {
        return array_map('intval', array_column(db()->query('SELECT id FROM exams ORDER BY start_time DESC')->fetchAll(), 'id'));
    }
    $s = db()->prepare('SELECT exam_id FROM exam_assignments WHERE user_id=?');
    $s->execute([$userId]);
    return array_map('intval', array_column($s->fetchAll(), 'exam_id'));
}

function is_exam_assigned(int $userId, int $examId): bool {
    if (!table_exists('exam_assignments')) return true;
    $s = db()->prepare('SELECT 1 FROM exam_assignments WHERE user_id=? AND exam_id=? LIMIT 1');
    $s->execute([$userId, $examId]);
    return (bool)$s->fetchColumn();
}

function set_exam_assignments(int $userId, array $examIds): void {
    ensure_exam_assignments_table();
    $pdo = db();
    $pdo->prepare('DELETE FROM exam_assignments WHERE user_id=?')->execute([$userId]);
    if (!$examIds) return;
    $ins = $pdo->prepare('INSERT IGNORE INTO exam_assignments (user_id, exam_id) VALUES (?, ?)');
    foreach ($examIds as $eid) {
        $eid = (int)$eid;
        if ($eid > 0) $ins->execute([$userId, $eid]);
    }
}

function ensure_exam_assignments_table(): void {
    if (table_exists('exam_assignments')) return;
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS exam_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  exam_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_user_exam (user_id, exam_id),
  KEY idx_exam (exam_id),
  KEY idx_user (user_id)
);
SQL;
    db()->exec($sql);
}

// Ensure exams table has an exam_code column and helper to resolve codes
function ensure_exam_code_column(): void {
    $cols = [];
    foreach (db()->query('SHOW COLUMNS FROM exams')->fetchAll() as $col) $cols[$col['Field']] = true;
    if (empty($cols['exam_code'])) {
        db()->exec("ALTER TABLE exams ADD COLUMN exam_code VARCHAR(80) NULL AFTER exam_name");
        // populate existing rows with a unique code if empty
        $rows = db()->query('SELECT id FROM exams')->fetchAll();
        $upd = db()->prepare('UPDATE exams SET exam_code=? WHERE id=?');
        foreach ($rows as $r) {
            $code = 'EXAM' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT);
            $upd->execute([$code, $r['id']]);
        }
        // add unique index
        try {
            db()->exec('ALTER TABLE exams ADD UNIQUE KEY uq_exam_code (exam_code)');
        } catch (Throwable $e) {
            // ignore if index creation fails
        }
    }
}

// Convert an array/list of exam codes to exam IDs. Throws if any code not found.
function exam_ids_from_codes(array $codes): array {
    $out = [];
    $q = db()->prepare('SELECT id FROM exams WHERE exam_code = ? LIMIT 1');
    foreach ($codes as $c) {
        $c = trim((string)$c);
        if ($c === '') continue;
        $q->execute([$c]);
        $r = $q->fetch();
        if (!$r) throw new Exception("Exam code not found: $c");
        $out[] = (int)$r['id'];
    }
    return $out;
}

function table_exists(string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = db()->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1');
        $stmt->execute([DB_NAME, $table]);
        $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

// ---------------------------------------------------------------------------
// Ensure bilingual (English + Hindi) columns exist on questions / options /
// exams. This runs once per request and is a no-op after the columns exist,
// so it is safe to call on every admin/student page load. Works entirely
// offline (no external API) so it is compatible with intranet deployments.
// ---------------------------------------------------------------------------
function ensure_bilingual_columns(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo = db();

        $qCols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM questions')->fetchAll() as $c) $qCols[$c['Field']] = true;
        if (empty($qCols['question_text_hi'])) {
            $pdo->exec("ALTER TABLE questions ADD COLUMN question_text_hi TEXT NULL AFTER question_text");
        }
        if (empty($qCols['correct_text_hi'])) {
            $pdo->exec("ALTER TABLE questions ADD COLUMN correct_text_hi TEXT NULL AFTER correct_text");
        }

        $oCols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM question_options')->fetchAll() as $c) $oCols[$c['Field']] = true;
        if (empty($oCols['opt_text_hi'])) {
            $pdo->exec("ALTER TABLE question_options ADD COLUMN opt_text_hi TEXT NULL AFTER opt_text");
        }

        $eCols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM exams')->fetchAll() as $c) $eCols[$c['Field']] = true;
        if (empty($eCols['instructions_hi'])) {
            $pdo->exec("ALTER TABLE exams ADD COLUMN instructions_hi TEXT NULL AFTER instructions");
        }
    } catch (Throwable $e) {
        // Never block the main flow; log silently.
    }
}

// ---------------------------------------------------------------------------
// Soft-delete + permissions migration. Adds deleted_at/deleted_by columns to
// users/exams/questions/question_options/attempts and creates the
// admin_permissions table.  Idempotent — safe to call on every page load.
// ---------------------------------------------------------------------------
function ensure_softdelete_and_permissions(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo = db();
        $addDel = function(string $table) use ($pdo) {
            $cols = [];
            foreach ($pdo->query("SHOW COLUMNS FROM $table")->fetchAll() as $c) $cols[$c['Field']] = true;
            if (empty($cols['deleted_at']))      $pdo->exec("ALTER TABLE $table ADD COLUMN deleted_at DATETIME NULL");
            if (empty($cols['deleted_by']))      $pdo->exec("ALTER TABLE $table ADD COLUMN deleted_by INT NULL");
            if (empty($cols['deleted_by_name'])) $pdo->exec("ALTER TABLE $table ADD COLUMN deleted_by_name VARCHAR(150) NULL");
            if (empty($cols['deleted_by_email']))$pdo->exec("ALTER TABLE $table ADD COLUMN deleted_by_email VARCHAR(150) NULL");
        };
        foreach (['users', 'exams', 'questions', 'question_options', 'attempts'] as $t) {
            if (table_exists($t)) $addDel($t);
        }
        if (!table_exists('admin_permissions')) {
            $pdo->exec("CREATE TABLE admin_permissions (
                admin_id INT PRIMARY KEY,
                perms TEXT NULL,
                view_all_exams TINYINT(1) NOT NULL DEFAULT 0,
                view_all_students TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_ap_u FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }
    } catch (Throwable $e) {
        // never block main flow
    }
}

// Default permissions for a newly-created (non-super) admin.
// Super admin is ALWAYS unlimited regardless of this row.
// ---------------------------------------------------------------------------
// Phase-3 migrations: per-exam violation config, exam-access grants, indexes.
// Idempotent — safe on every page load.
// ---------------------------------------------------------------------------
function ensure_phase3_migrations(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        $pdo = db();
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM exams')->fetchAll() as $c) $cols[$c['Field']] = true;
        if (empty($cols['violation_config'])) $pdo->exec("ALTER TABLE exams ADD COLUMN violation_config TEXT NULL");
        if (empty($cols['force_fullscreen'])) $pdo->exec("ALTER TABLE exams ADD COLUMN force_fullscreen TINYINT(1) NOT NULL DEFAULT 1");
        if (empty($cols['max_violations']))   $pdo->exec("ALTER TABLE exams ADD COLUMN max_violations INT NOT NULL DEFAULT 5");

        if (!table_exists('exam_admin_access')) {
            $pdo->exec("CREATE TABLE exam_admin_access (
                id INT AUTO_INCREMENT PRIMARY KEY,
                exam_id INT NOT NULL,
                admin_id INT NOT NULL,
                access_level ENUM('view','edit','full') NOT NULL DEFAULT 'view',
                granted_by INT NULL,
                granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_exam_admin (exam_id, admin_id),
                KEY idx_admin (admin_id),
                KEY idx_exam (exam_id),
                CONSTRAINT fk_eaa_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
                CONSTRAINT fk_eaa_adm FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        // Indexes for robustness on govt-scale record retention (idempotent via try/catch)
        @$pdo->exec("CREATE INDEX idx_exams_active ON exams (start_time, end_time, deleted_at)");
        @$pdo->exec("CREATE INDEX idx_attempts_status ON attempts (exam_id, status)");
        @$pdo->exec("CREATE INDEX idx_assignments_exam ON exam_assignments (exam_id)");
        @$pdo->exec("CREATE INDEX idx_violations_attempt ON violations (attempt_id, event_time)");
    } catch (Throwable $e) { /* silent */ }
}

// Default ON for every violation check — super admin can disable per-exam.
function default_violation_config(): array {
    return [
        'camera'              => 1,  // webcam proctoring
        'tab_switch'          => 1,  // visibilitychange + blur
        'right_click'         => 1,
        'copy_paste'          => 1,
        'fullscreen_force'    => 1,  // auto re-enter fullscreen on exit
        'fullscreen_overlay'  => 1,  // show blocking overlay while auto-returning from fullscreen exit
        'keyboard_shortcuts'  => 1,  // F12, F11, Escape, Ctrl+Shift+I, Ctrl+U, Ctrl+P, Ctrl+W
        'escape_f11_block'    => 1,  // Escape / F11 blockers
        'devtools_block'      => 1,
        'windows_key_block'   => 1,  // block Windows/Super key
        'screenshot_block'    => 1,  // block Print Screen + Mac/Linux screenshot key combos
        'window_blur'         => 1,
        // Complete-lockdown toggles (ON by default, admin can turn off):
        'mac_shortcuts_block' => 1,  // Cmd+Tab, Cmd+Q, Cmd+H, Cmd+M, Cmd+Space, Cmd+W, Cmd+N, Cmd+T
        'alt_shortcuts_block' => 1,  // Alt+Tab, Alt+F4, Alt+Space, Alt+Left/Right (browser nav)
        'all_function_keys_block' => 1, // F1..F12 all hard-blocked
        'extension_overlay_block' => 1, // MutationObserver removes injected iframes / AI overlays / extension UI
        'clipboard_api_block' => 1,  // block navigator.clipboard reads, drag-drop
        'screen_sharing_block' => 1, // detect screen-sharing / remote access (RDP, AnyDesk, TeamViewer heuristics)
        'remote_access_block'  => 1, // aggressive remote-desktop heuristics (multi-display, mouse-latency)
        // Future-proof toggles — default OFF (can be enabled when client-side hooks ship):
        'second_display'      => 0,
        'screen_recording'    => 0,
        'virtual_machine'     => 0,
        'copy_text_select'    => 0,
    ];
}

function exam_violation_config(array $exam): array {
    $def = default_violation_config();
    if (empty($exam['violation_config'])) return $def;
    $c = json_decode($exam['violation_config'], true);
    return is_array($c) ? array_replace($def, $c) : $def;
}

// Does the given admin have access to view/edit/manage a specific exam?
// - Owner or super-admin or view-all-exams grants always return true at 'full'.
// - Otherwise check exam_admin_access row.
function exam_access_for(int $examId, array $user): ?string {
    if (!empty($user['is_super'])) return 'full';
    // Ownership
    $own = db()->prepare('SELECT created_by FROM exams WHERE id=? AND deleted_at IS NULL');
    $own->execute([$examId]);
    $owner = (int)$own->fetchColumn();
    if ($owner && $owner === (int)$user['id']) return 'full';
    if (can_view_all('exams', $user)) return 'full';
    // Specific grant
    $g = db()->prepare('SELECT access_level FROM exam_admin_access WHERE exam_id=? AND admin_id=?');
    $g->execute([$examId, (int)$user['id']]);
    $lvl = $g->fetchColumn();
    return $lvl ?: null;
}

// List of exam IDs accessible to an admin (owned + granted + view_all + super).
function accessible_exam_ids(array $user): array {
    $pdo = db();
    if (!empty($user['is_super']) || can_view_all('exams', $user)) {
        return array_map('intval', $pdo->query("SELECT id FROM exams WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN));
    }
    $ids = [];
    $o = $pdo->prepare("SELECT id FROM exams WHERE created_by=? AND deleted_at IS NULL");
    $o->execute([(int)$user['id']]);
    foreach ($o->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[(int)$id] = true;
    $g = $pdo->prepare("SELECT exam_id FROM exam_admin_access WHERE admin_id=?");
    $g->execute([(int)$user['id']]);
    foreach ($g->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[(int)$id] = true;
    return array_keys($ids);
}

// Normalises question text so duplicates can be detected regardless of whitespace/case.
function normalise_question_text(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}

// Find duplicate question within the same exam (and optionally across system).
// Returns the duplicate question row (id, exam_id, exam_name) or null.
function find_duplicate_question(int $examId, string $questionText, ?int $ignoreId = null, bool $sameExamOnly = true): ?array {
    $norm = normalise_question_text($questionText);
    if ($norm === '') return null;
    $pdo = db();
    $sql = "SELECT q.id, q.exam_id, e.exam_name
              FROM questions q JOIN exams e ON e.id=q.exam_id
             WHERE q.deleted_at IS NULL AND LOWER(TRIM(q.question_text))=?";
    $params = [$norm];
    if ($sameExamOnly) { $sql .= " AND q.exam_id=?"; $params[] = $examId; }
    if ($ignoreId)     { $sql .= " AND q.id<>?";      $params[] = $ignoreId; }
    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql); $st->execute($params);
    $r = $st->fetch();
    return $r ?: null;
}

function default_admin_perms(): array {
    return [
        'students'  => ['create'=>1,'edit'=>1,'delete'=>1],   // non-super admins can manage their own
        'exams'     => ['create'=>1,'edit'=>1,'delete'=>1],
        'questions' => ['create'=>1,'edit'=>1,'delete'=>1],
        'results'   => ['view'=>1],                            // always at least see own
    ];
}

function load_admin_perms(int $adminId): array {
    static $cache = [];
    if (isset($cache[$adminId])) return $cache[$adminId];
    ensure_softdelete_and_permissions();
    $stmt = db()->prepare('SELECT perms, view_all_exams, view_all_students FROM admin_permissions WHERE admin_id=?');
    $stmt->execute([$adminId]);
    $row = $stmt->fetch();
    $perms = default_admin_perms();
    if ($row && !empty($row['perms'])) {
        $decoded = json_decode($row['perms'], true);
        if (is_array($decoded)) $perms = array_replace_recursive($perms, $decoded);
    }
    $cache[$adminId] = [
        'perms' => $perms,
        'view_all_exams'    => (int)($row['view_all_exams']    ?? 0),
        'view_all_students' => (int)($row['view_all_students'] ?? 0),
    ];
    return $cache[$adminId];
}

// can('exams','create') etc. Super admin is always true.
function can(string $section, string $action, ?array $user = null): bool {
    $u = $user ?? current_user();
    if (!$u || ($u['role'] ?? '') !== 'admin') return false;
    if (!empty($u['is_super'])) return true;
    $p = load_admin_perms((int)$u['id'])['perms'];
    return !empty($p[$section][$action]);
}

function can_view_all(string $section, ?array $user = null): bool {
    $u = $user ?? current_user();
    if (!$u || ($u['role'] ?? '') !== 'admin') return false;
    if (!empty($u['is_super'])) return true;
    $p = load_admin_perms((int)$u['id']);
    return $section === 'exams' ? (bool)$p['view_all_exams']
         : ($section === 'students' ? (bool)$p['view_all_students'] : false);
}

// Convenience: SQL WHERE fragment to restrict rows to ones the admin owns
// (created_by = me). Returns empty string for super/can_view_all.
function ownership_sql_clause(string $section, string $tableAlias, array $user): array {
    if (!empty($user['is_super']) || can_view_all($section, $user)) return ['', []];
    return [" AND $tableAlias.created_by = ?", [(int)$user['id']]];
}

// Perform a soft delete (records who + when + name + email) — safe no-op if
// the target row doesn't exist. `$extraWhere` can further restrict (e.g. role).
function soft_delete(string $table, int $id, array $actor, string $extraWhere = ''): bool {
    $sql = "UPDATE $table SET deleted_at=NOW(), deleted_by=?, deleted_by_name=?, deleted_by_email=? WHERE id=? AND deleted_at IS NULL $extraWhere";
    $stmt = db()->prepare($sql);
    $stmt->execute([(int)$actor['id'], (string)($actor['name'] ?? ''), (string)($actor['email'] ?? ''), $id]);
    return $stmt->rowCount() > 0;
}

function ensure_admin_activity_logs_table(): void {
    if (table_exists('admin_activity_logs')) return;
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS admin_activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NULL,
  admin_name VARCHAR(150) NOT NULL,
  admin_email VARCHAR(150) NOT NULL,
  action VARCHAR(80) NOT NULL,
  details TEXT NULL,
    page VARCHAR(255) NULL,
  request_method VARCHAR(12) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
    payload TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin (admin_id),
  KEY idx_action (action),
  KEY idx_created (created_at)
) ENGINE=InnoDB;
SQL;
    db()->exec($sql);
}

// Ensure existing table has payload column (for upgrades)
function ensure_admin_activity_logs_payload_column(): void {
        if (!table_exists('admin_activity_logs')) return;
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM admin_activity_logs')->fetchAll() as $c) $cols[$c['Field']] = true;
        if (empty($cols['payload'])) {
                db()->exec("ALTER TABLE admin_activity_logs ADD COLUMN payload TEXT NULL AFTER user_agent");
        }
}

// Return the real client IP address, accounting for common proxy headers.
function get_client_ip(): ?string {
    $keys = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];
    foreach ($keys as $k) {
        if (empty($_SERVER[$k])) continue;
        $val = $_SERVER[$k];
        // X-Forwarded-For may contain multiple IPs, take the first one
        if (strpos($val, ',') !== false) {
            $parts = array_map('trim', explode(',', $val));
            foreach ($parts as $p) {
                if (filter_var($p, FILTER_VALIDATE_IP)) return $p;
            }
        } else {
            if (filter_var($val, FILTER_VALIDATE_IP)) return $val;
        }
    }
    return null;
}

function log_admin_activity(string $action, string $details = '', ?array $actor = null, ?string $page = null, $payload = null): void {
    try {
        ensure_admin_activity_logs_table();
        ensure_admin_activity_logs_payload_column();
        $u = $actor ?? current_user();
        if (!$u || ($u['role'] ?? null) !== 'admin') return;
        $stmt = db()->prepare('INSERT INTO admin_activity_logs (admin_id, admin_name, admin_email, action, details, page, request_method, ip_address, user_agent, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $payload_json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $stmt->execute([
            $u['id'] ?? null,
            $u['name'] ?? 'Unknown',
            $u['email'] ?? '',
            $action,
            $details !== '' ? $details : null,
            $page,
            $_SERVER['REQUEST_METHOD'] ?? null,
            get_client_ip() ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $payload_json,
        ]);
    } catch (Throwable $e) {
        // Audit logging must never block the main action.
    }
}
