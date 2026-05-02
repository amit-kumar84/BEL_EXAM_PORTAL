<?php
require_once __DIR__ . '/../includes/helpers.php';
$u = current_user();
if (!$u || $u['role'] !== 'student') json_out(['error' => 'unauthorized'], 401);

$aid = (int)($_POST['attempt_id'] ?? 0);
$type = substr((string)($_POST['event_type'] ?? ''), 0, 64);
$desc = substr((string)($_POST['description'] ?? ''), 0, 255);

$chk = db()->prepare('SELECT id FROM attempts WHERE id=? AND user_id=?');
$chk->execute([$aid, $u['id']]);
if (!$chk->fetch()) json_out(['error' => 'attempt not found'], 404);

db()->prepare('INSERT INTO violations (attempt_id, user_id, event_type, description) VALUES (?, ?, ?, ?)')
    ->execute([$aid, $u['id'], $type, $desc]);
json_out(['ok' => true]);
