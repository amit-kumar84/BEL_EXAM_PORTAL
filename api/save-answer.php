<?php
require_once __DIR__ . '/../includes/helpers.php';
$u = current_user();
if (!$u || $u['role'] !== 'student') json_out(['error' => 'unauthorized'], 401);

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$aid = (int)($body['attempt_id'] ?? 0);
$qid = (int)($body['question_id'] ?? 0);
$ans = $body['answer'] ?? null;
$mark = !empty($body['marked_review']) ? 1 : 0;
$mark_only = !empty($body['mark_only']);

// verify ownership
$chk = db()->prepare('SELECT id FROM attempts WHERE id=? AND user_id=? AND status="in_progress"');
$chk->execute([$aid, $u['id']]);
if (!$chk->fetch()) json_out(['error' => 'attempt not found'], 404);

if ($mark_only) {
    db()->prepare('INSERT INTO attempt_answers (attempt_id, question_id, marked_review) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE marked_review=VALUES(marked_review)')
        ->execute([$aid, $qid, $mark]);
} else {
    $j = $ans !== null ? json_encode($ans, JSON_UNESCAPED_UNICODE) : null;
    db()->prepare('INSERT INTO attempt_answers (attempt_id, question_id, selected_json, marked_review) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE selected_json=VALUES(selected_json), marked_review=VALUES(marked_review)')
        ->execute([$aid, $qid, $j, $mark]);
}
json_out(['ok' => true]);
