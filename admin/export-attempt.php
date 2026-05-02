<?php
require_once __DIR__ . '/../includes/helpers.php'; require_login('admin');
$aid = (int)($_GET['id'] ?? 0);
$a = db()->prepare('SELECT a.*, u.name AS sname, u.email AS semail, u.username AS suname, u.roll_number, u.dob, u.category, e.exam_name, e.exam_code FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id WHERE a.id=?');
$a->execute([$aid]); $att = $a->fetch();
if (!$att) die('Not found');
$qs = db()->prepare('SELECT * FROM questions WHERE exam_id=? ORDER BY id'); $qs->execute([$att['exam_id']]);
$questions = $qs->fetchAll();
$optsMap = [];
if ($questions) { $ids=array_column($questions,'id'); $in=str_repeat('?,',count($ids)-1).'?';
  $o = db()->prepare("SELECT * FROM question_options WHERE question_id IN ($in) ORDER BY opt_order"); $o->execute($ids);
  foreach ($o->fetchAll() as $r) $optsMap[$r['question_id']][] = $r; }
$ans = []; $ax = db()->prepare('SELECT * FROM attempt_answers WHERE attempt_id=?'); $ax->execute([$aid]);
foreach ($ax->fetchAll() as $r) $ans[$r['question_id']] = $r;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=attempt_'.$aid.'.csv');
$out = fopen('php://output', 'w');

// Student / attempt summary block at the top of CSV
fputcsv($out, ['Attempt Summary']);
fputcsv($out, ['Student Name', $att['sname']]);
fputcsv($out, ['Username', $att['suname']]);
fputcsv($out, ['Email', $att['semail']]);
fputcsv($out, ['Roll / Staff ID', $att['roll_number']]);
fputcsv($out, ['DOB', $att['dob']]);
fputcsv($out, ['Category', $att['category']]);
fputcsv($out, ['Exam', $att['exam_name']]);
fputcsv($out, ['Exam Code', $att['exam_code']]);
fputcsv($out, ['Attempt No', $att['attempt_no']]);
fputcsv($out, ['Score', $att['score']]);
fputcsv($out, ['Total', $att['total']]);
fputcsv($out, ['Started At', $att['started_at']]);
fputcsv($out, ['Submitted At', $att['submitted_at']]);
fputcsv($out, []);

fputcsv($out, ['#','Question','Type','Selected','Correct','Marks','Result']);
foreach ($questions as $i => $q) {
    $a = $ans[$q['id']] ?? null;
    $sel = $a && $a['selected_json'] ? json_decode($a['selected_json'], true) : null;
    $opts = $optsMap[$q['id']] ?? [];
    if (in_array($q['question_type'], ['mcq','multi_select'])) {
        $selTxt = implode(',', array_map(fn($o)=>$o['opt_text'], array_filter($opts, fn($o)=>in_array((int)$o['opt_order'], array_map('intval',$sel['selected']??[])))));
        $corrTxt = implode(',', array_map(fn($o)=>$o['opt_text'], array_filter($opts, fn($o)=>$o['is_correct'])));
    } elseif ($q['question_type']==='true_false') { $selTxt = $sel ? (!empty($sel['bool'])?'True':'False') : ''; $corrTxt = $q['correct_bool']?'True':'False'; }
    elseif ($q['question_type']==='short_answer') { $selTxt = $sel['text'] ?? ''; $corrTxt = $q['correct_text']; }
    else { $selTxt = $sel['numeric'] ?? ''; $corrTxt = $q['correct_numeric']; }
    $result = !$a||!$a['selected_json'] ? 'Skipped' : ($a['is_correct']?'Correct':'Wrong');
    fputcsv($out, [$i+1, $q['question_text'], $q['question_type'], $selTxt, $corrTxt, $q['marks'], $result]);
}
fclose($out);
