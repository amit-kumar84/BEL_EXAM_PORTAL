<?php
// api/exam-grants.php — super-admin only; list existing grants for an exam.
require_once __DIR__ . '/../includes/helpers.php';
$me = current_user();
if (!$me || ($me['role'] ?? '') !== 'admin' || empty($me['is_super'])) json_out(['ok'=>false,'error'=>'forbidden'], 403);
ensure_phase3_migrations();
$eid = (int)($_GET['exam_id'] ?? 0);
$stmt = db()->prepare('SELECT g.id, g.exam_id, g.admin_id, g.access_level, g.granted_at, u.name AS admin_name, u.email AS admin_email FROM exam_admin_access g JOIN users u ON u.id=g.admin_id WHERE g.exam_id=? ORDER BY g.granted_at DESC');
$stmt->execute([$eid]);
$grants = $stmt->fetchAll();
foreach ($grants as &$g) $g['granted_at'] = date('d M Y, H:i', strtotime($g['granted_at']));
json_out(['ok'=>true, 'grants'=>$grants]);
