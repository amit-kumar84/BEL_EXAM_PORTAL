<?php
require_once __DIR__ . '/../includes/helpers.php'; require_login('admin');

$examId = (int)($_GET['exam_id'] ?? 0);
$fnSuffix = $examId ? '_exam_' . $examId : '';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=results_'.date('Y-m-d').$fnSuffix.'.csv');
$out = fopen('php://output', 'w');
fputcsv($out, ['Student','Email','Roll','Exam','Exam Code','Attempt','Score','Total','Submitted At']);
$sql = 'SELECT u.name, u.email, u.roll_number, e.exam_name, e.exam_code, a.attempt_no, a.score, a.total, a.submitted_at FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id WHERE a.status="submitted"';
if ($examId) $sql .= ' AND a.exam_id=' . $examId;
$sql .= ' ORDER BY a.submitted_at DESC';
$rows = db()->query($sql)->fetchAll();
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);
