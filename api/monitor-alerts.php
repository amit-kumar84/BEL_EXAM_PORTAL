<?php
// api/monitor-alerts.php — returns new violations for an exam since a timestamp (ms)
// Query: ?exam_id=<id>&since=<ms>
require_once __DIR__ . '/../includes/helpers.php';
$me = current_user();
if (!$me || $me['role'] !== 'admin') json_out(['ok'=>false,'error'=>'unauthorized'], 401);
ensure_softdelete_and_permissions();

$eid = (int)($_GET['exam_id'] ?? 0);
$since = (int)($_GET['since'] ?? 0);
$pdo = db();

$exam = $pdo->prepare('SELECT id, created_by FROM exams WHERE id=? AND deleted_at IS NULL');
$exam->execute([$eid]);
$ex = $exam->fetch();
if (!$ex) json_out(['ok'=>false,'error'=>'exam not found'], 404);
if (empty($me['is_super']) && (int)$ex['created_by'] !== (int)$me['id']) {
    json_out(['ok'=>false,'error'=>'forbidden'], 403);
}

// MySQL DATETIME (seconds resolution) — convert incoming ms to seconds
$sinceSec = (int)floor($since / 1000);
$sinceDt = $sinceSec > 0 ? date('Y-m-d H:i:s', $sinceSec) : '1970-01-01 00:00:00';

$stmt = $pdo->prepare(
  'SELECT v.id, v.event_type, v.description, UNIX_TIMESTAMP(v.event_time)*1000 AS ts,
          v.user_id, u.name, u.roll_number AS roll, u.dob, u.photo_path
     FROM violations v
     JOIN attempts a ON a.id = v.attempt_id
     JOIN users u ON u.id = v.user_id
    WHERE a.exam_id = ? AND v.event_time > ?
    ORDER BY v.event_time ASC
    LIMIT 50');
$stmt->execute([$eid, $sinceDt]);
$alerts = [];
foreach ($stmt->fetchAll() as $r) {
    $alerts[] = [
        'id'          => (int)$r['id'],
        'user_id'     => (int)$r['user_id'],
        'name'        => $r['name'],
        'roll'        => $r['roll'],
        'dob'         => $r['dob'],
        'photo_url'   => !empty($r['photo_path']) ? url($r['photo_path']) : null,
        'event_type'  => $r['event_type'],
        'description' => $r['description'],
        'ts'          => (int)$r['ts'],
    ];
}

json_out(['ok' => true, 'alerts' => $alerts, 'server_ts' => time()*1000]);
