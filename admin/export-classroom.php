<?php
// admin/export-classroom.php — CSV export of a classroom roster (with summary header block).
require_once __DIR__ . '/../includes/helpers.php';
$me = require_login('admin');
ensure_phase3_migrations();

$eid = (int)($_GET['exam_id'] ?? 0);
$ex = db()->prepare('SELECT e.*, u.name AS host_name, u.email AS host_email FROM exams e LEFT JOIN users u ON u.id=e.created_by WHERE e.id=? AND e.deleted_at IS NULL');
$ex->execute([$eid]);
$exam = $ex->fetch();
if (!$exam) die('Exam not found');
$access = exam_access_for($eid, $me);
if (!$access) die('Forbidden — you do not have access to this exam');

$st = db()->prepare(
  'SELECT u.name, u.roll_number, u.dob, u.email, u.category,
          a.status, a.started_at, a.submitted_at, a.score, a.total,
          (SELECT COUNT(*) FROM violations WHERE user_id=u.id AND attempt_id=a.id) AS violations
     FROM users u
     JOIN exam_assignments ea ON ea.user_id=u.id
     LEFT JOIN attempts a ON a.user_id=u.id AND a.exam_id=? AND a.id=(SELECT MAX(id) FROM attempts WHERE user_id=u.id AND exam_id=?)
    WHERE ea.exam_id=? AND u.deleted_at IS NULL
    ORDER BY u.name');
$st->execute([$eid, $eid, $eid]);
$rows = $st->fetchAll();

$filename = 'Classroom_' . preg_replace('/[^A-Za-z0-9]+/', '_', $exam['exam_name']) . '_' . date('Ymd_Hi') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');
// Unicode BOM so Excel handles हिंदी correctly.
fwrite($out, "\xEF\xBB\xBF");

// Summary header block
fputcsv($out, ['BEL Kotdwar Exam Portal — Classroom Export']);
fputcsv($out, ['Exam', $exam['exam_name']]);
fputcsv($out, ['Code', $exam['exam_code']]);
fputcsv($out, ['Hosted By', ($exam['host_name'] ?: '') . ($exam['host_email'] ? ' <'.$exam['host_email'].'>' : '')]);
fputcsv($out, ['Window', $exam['start_time'] . '  →  ' . $exam['end_time']]);
fputcsv($out, ['Duration', $exam['duration_minutes'] . ' min']);
fputcsv($out, ['Generated', date('Y-m-d H:i:s') . '  by  ' . ($me['name'] . ' <' . $me['email'] . '>')]);
$counts = ['registered'=>count($rows), 'writing'=>0, 'submitted'=>0, 'absent'=>0, 'violations'=>0];
foreach ($rows as $r) {
    if ($r['status'] === 'in_progress') $counts['writing']++;
    elseif ($r['status'] === 'submitted') $counts['submitted']++;
    else $counts['absent']++;
    $counts['violations'] += (int)$r['violations'];
}
fputcsv($out, ['Summary', "Registered={$counts['registered']} | Writing={$counts['writing']} | Submitted={$counts['submitted']} | Absent={$counts['absent']} | Violations={$counts['violations']}"]);
fputcsv($out, []);

// Per-student roster
fputcsv($out, ['#','Name','Roll/Staff ID','DOB','Email','Category','Status','Started','Submitted','Score','Violations']);
foreach ($rows as $i => $r) {
    $status = $r['status'] === 'in_progress' ? 'WRITING' : ($r['status'] === 'submitted' ? 'SUBMITTED' : 'ABSENT');
    fputcsv($out, [
        $i+1,
        $r['name'],
        $r['roll_number'],
        $r['dob'],
        $r['email'],
        strtoupper($r['category']),
        $status,
        $r['started_at'] ?: '',
        $r['submitted_at'] ?: '',
        ($r['score'] !== null && $r['total'] !== null) ? ($r['score'].'/'.$r['total']) : '',
        (int)$r['violations'],
    ]);
}
fclose($out);
exit;
