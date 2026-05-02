<?php $ADMIN_TITLE = 'Manage Questions';
require_once __DIR__ . '/../includes/helpers.php'; $me = require_login('admin');
ensure_bilingual_columns();
ensure_softdelete_and_permissions();
ensure_phase3_migrations();
$eid = (int)($_GET['exam_id'] ?? 0);
// Ownership check honours exam-access grants (edit/full) for non-super admins
$examOwn = db()->prepare('SELECT created_by, exam_name FROM exams WHERE id=? AND deleted_at IS NULL');
$examOwn->execute([$eid]);
$examOwnRow = $examOwn->fetch();
if (!$examOwnRow) { flash('Exam not found','error'); redirect(url('admin/exams.php')); }
$examAccess = exam_access_for($eid, $me); // 'view' | 'edit' | 'full' | null (also 'full' for owner/super)
if (!$examAccess) {
  flash('You do not have access to this exam.','error');
  redirect(url('admin/exams.php'));
}
$canEditQuestions = in_array($examAccess, ['edit','full'], true);
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  if (!$canEditQuestions) { flash('You have view-only access on this exam.','error'); redirect(url('admin/questions.php?exam_id=' . $eid)); }
  $a = $_POST['action'];
  if ($a === 'add') {
    $type = $_POST['type'];
    $text = trim($_POST['text']);
    $textHi = trim((string)($_POST['text_hi'] ?? ''));
    if ($textHi === '') $textHi = null;
    $marks = (float)$_POST['marks']; $neg = (float)($_POST['neg'] ?? 0);
    // ---- Duplicate detection ----
    // If the same question text already exists in this exam (case-insensitive,
    // whitespace-normalised) AND the form does NOT carry the override flag,
    // bounce back with a warning so the admin can confirm.
    $force = !empty($_POST['force_save']);
    if (!$force) {
      $dup = find_duplicate_question($eid, $text, null, true);
      if ($dup) {
        // Persist the form into a flash so the admin doesn't lose what they typed.
        $_SESSION['__dup_form'] = $_POST;
        flash('A question with the same text already exists in this exam (Q-id #' . (int)$dup['id'] . '). Click "Save Anyway" to confirm.', 'error');
        redirect(url('admin/questions.php?exam_id=' . $eid . '&duplicate=' . (int)$dup['id']));
      }
    }
    $ct = null; $ctHi = null; $cn = null; $cb = null;
    if ($type === 'short_answer') {
      $ct = trim($_POST['correct_text']);
      $ctHi = trim((string)($_POST['correct_text_hi'] ?? ''));
      if ($ctHi === '') $ctHi = null;
    }
    elseif ($type === 'numeric') $cn = (float)$_POST['correct_numeric'];
    elseif ($type === 'true_false') $cb = $_POST['correct_bool']==='true' ? 1 : 0;
    db()->prepare('INSERT INTO questions (exam_id,question_type,question_text,question_text_hi,correct_text,correct_text_hi,correct_numeric,correct_bool,marks,negative_marks) VALUES (?,?,?,?,?,?,?,?,?,?)')
      ->execute([$eid,$type,$text,$textHi,$ct,$ctHi,$cn,$cb,$marks,$neg]);
    $qid = db()->lastInsertId();
    $options = [];
    if (in_array($type,['mcq','multi_select'])) {
      $opts = $_POST['options'] ?? [];
      $optsHi = $_POST['options_hi'] ?? [];
      $correctOpts = $_POST['correct_opts'] ?? [];
      // Handle both array (from checkboxes) and single value (from radio button)
      if (!is_array($correctOpts)) {
        $correctOpts = [$correctOpts];
      }
      $correct = array_map('intval', $correctOpts);
      foreach ($opts as $i => $o) {
        $o = trim($o); if (!$o) continue;
        $oHi = trim((string)($optsHi[$i] ?? ''));
        $oHi = $oHi !== '' ? $oHi : null;
        $isCorrect = in_array($i+1,$correct)?1:0;
        db()->prepare('INSERT INTO question_options (question_id,opt_order,opt_text,opt_text_hi,is_correct) VALUES (?,?,?,?,?)')
          ->execute([$qid, $i+1, $o, $oHi, $isCorrect]);
        $options[] = ['opt_order' => $i+1, 'opt_text' => $o, 'opt_text_hi' => $oHi, 'is_correct' => $isCorrect];
      }
    }
    $payload = ['table' => 'questions', 'after' => ['id' => $qid, 'exam_id' => $eid, 'question_type' => $type, 'question_text' => $text, 'question_text_hi' => $textHi, 'marks' => $marks, 'negative_marks' => $neg], 'options' => $options];
    log_admin_activity('question_add', 'Added question to exam id ' . $eid, current_user(), 'admin/questions.php?exam_id=' . $eid, $payload);
    flash('Question added','success');
    } elseif ($a === 'edit') {
      $qid = (int)$_POST['question_id'];
      $exists = db()->prepare('SELECT id FROM questions WHERE id=? AND exam_id=? AND deleted_at IS NULL');
      $exists->execute([$qid,$eid]);
      if (!$exists->fetch()) {
        flash('Question not found','error');
        redirect(url('admin/questions.php?exam_id='.$eid));
      }
      $type = $_POST['type'];
      $text = trim($_POST['text']);
      $textHi = trim((string)($_POST['text_hi'] ?? ''));
      if ($textHi === '') $textHi = null;
      $marks = (float)$_POST['marks']; $neg = (float)($_POST['neg'] ?? 0);
      $ct = null; $ctHi = null; $cn = null; $cb = null;
      if ($type === 'short_answer') {
        $ct = trim($_POST['correct_text']);
        $ctHi = trim((string)($_POST['correct_text_hi'] ?? ''));
        if ($ctHi === '') $ctHi = null;
      }
      elseif ($type === 'numeric') $cn = (float)$_POST['correct_numeric'];
      elseif ($type === 'true_false') $cb = $_POST['correct_bool']==='true' ? 1 : 0;
      db()->prepare('UPDATE questions SET question_type=?, question_text=?, question_text_hi=?, correct_text=?, correct_text_hi=?, correct_numeric=?, correct_bool=?, marks=?, negative_marks=? WHERE id=?')
        ->execute([$type,$text,$textHi,$ct,$ctHi,$cn,$cb,$marks,$neg,$qid]);
      db()->prepare('DELETE FROM question_options WHERE question_id=?')->execute([$qid]);
      $options = [];
      if (in_array($type,['mcq','multi_select'])) {
        $opts = $_POST['options'] ?? [];
        $optsHi = $_POST['options_hi'] ?? [];
        $correctOpts = $_POST['correct_opts'] ?? [];
        if (!is_array($correctOpts)) {
          $correctOpts = [$correctOpts];
        }
        $correct = array_map('intval', $correctOpts);
        foreach ($opts as $i => $o) {
          $o = trim($o); if (!$o) continue;
          $oHi = trim((string)($optsHi[$i] ?? ''));
          $oHi = $oHi !== '' ? $oHi : null;
          $isCorrect = in_array($i+1,$correct, true) ? 1 : 0;
          db()->prepare('INSERT INTO question_options (question_id,opt_order,opt_text,opt_text_hi,is_correct) VALUES (?,?,?,?,?)')
            ->execute([$qid, $i+1, $o, $oHi, $isCorrect]);
          $options[] = ['opt_order' => $i+1, 'opt_text' => $o, 'opt_text_hi' => $oHi, 'is_correct' => $isCorrect];
        }
      }
      $payload = ['table' => 'questions', 'id' => $qid, 'exam_id' => $eid, 'question_type' => $type, 'question_text' => $text, 'question_text_hi' => $textHi, 'marks' => $marks, 'negative_marks' => $neg, 'options' => $options];
      log_admin_activity('question_edit', 'Updated question id ' . $qid, current_user(), 'admin/questions.php?exam_id=' . $eid, $payload);
      flash('Question saved','success');
    } elseif ($a === 'delete') {
      if (!can('questions','delete',$me)) { flash('Permission denied: delete questions.','error'); redirect(url('admin/questions.php?exam_id='.$eid)); }
      $qid = (int)$_POST['id'];
      $del = db()->prepare('SELECT * FROM questions WHERE id=? AND deleted_at IS NULL'); $del->execute([$qid]); $delRow = $del->fetch() ?: [];
      $opts = db()->prepare('SELECT * FROM question_options WHERE question_id=? ORDER BY opt_order'); $opts->execute([$qid]); $options = $opts->fetchAll();
      soft_delete('questions', $qid, $me);
      $payload = ['table' => 'questions', 'before' => array_slice($delRow, 0, 8), 'options' => array_map(fn($o) => array_slice($o, 0, 4), $options), 'soft_delete' => true];
      log_admin_activity('question_delete', 'Soft-deleted question id ' . $qid, $me, 'admin/questions.php?exam_id=' . $eid, $payload);
      flash('Moved to Trash','success');
    }
  elseif ($a === 'bulk') {
    $csv = trim($_POST['csv']); 
    if (!$csv) { flash('CSV is empty','danger'); } else {
      $lines = preg_split('/\r\n|\n|\r/', $csv);
      $headerRow = trim((string)array_shift($lines));
      if ($headerRow === '') {
        flash('CSV header is empty','danger');
      } else {
        $h = array_map(fn($v) => strtolower(trim($v)), str_getcsv($headerRow));
        $hasTypeColumn = in_array('question_type', $h, true);
        $required = $hasTypeColumn
          ? ['question_type','question','option1','option2','option3','option4','correct']
          : ['question','option1','option2','option3','option4','correct'];
        $missing = array_diff($required, $h);
        if ($missing) { flash('Missing required columns: '.implode(', ',$missing),'danger'); } else {
          $normalizeType = function (string $type): string {
            $type = strtolower(trim($type));
            $type = str_replace(['-', ' '], '_', $type);
            if ($type === 'multiselect') $type = 'multi_select';
            return in_array($type, ['mcq','multi_select','true_false','short_answer','numeric'], true) ? $type : '';
          };
          $parseList = function (string $value): array {
            $parts = preg_split('/\s*[|;,]\s*/', trim($value));
            $parts = array_filter(array_map('trim', $parts), fn($v) => $v !== '');
            return array_values(array_unique($parts));
          };
          $parseBool = function (string $value): ?int {
            $value = strtolower(trim($value));
            if (in_array($value, ['1','true','yes','y'], true)) return 1;
            if (in_array($value, ['0','false','no','n'], true)) return 0;
            return null;
          };
          $c = 0; $skipped = 0; $errors = [];
          foreach ($lines as $rowNum => $ln) {
            $lineNo = $rowNum + 2;
            if (!trim($ln)) continue;
            $data = str_getcsv($ln);
            if (count($data) !== count($h)) {
              $skipped++;
              if (count($errors) < 8) $errors[] = "Row $lineNo: column count mismatch";
              continue;
            }
            $r = array_combine($h, $data);
            $type = $hasTypeColumn ? $normalizeType((string)($r['question_type'] ?? '')) : 'mcq';
            if ($hasTypeColumn && $type === '') {
              $skipped++;
              if (count($errors) < 8) $errors[] = "Row $lineNo: invalid question_type";
              continue;
            }
            $qt = trim($r['question'] ?? '');
            $m = (float)($r['marks'] ?? 1);
            $n = (float)($r['negative'] ?? 0);
            if ($qt === '' || $m <= 0) {
              $skipped++;
              if (count($errors) < 8) $errors[] = "Row $lineNo: question text or marks is invalid";
              continue;
            }
            // Skip duplicate questions silently in bulk (govt audit-friendly: log skip count).
            if (find_duplicate_question($eid, $qt, null, true)) {
              $skipped++;
              if (count($errors) < 8) $errors[] = "Row $lineNo: duplicate (already exists in this exam) — skipped";
              continue;
            }

            $correctRaw = trim((string)($r['correct'] ?? ''));
            $correctText = null;
            $correctTextHi = null;
            $correctNumeric = null;
            $correctBool = null;
            $opts = [trim($r['option1'] ?? ''), trim($r['option2'] ?? ''), trim($r['option3'] ?? ''), trim($r['option4'] ?? '')];
            $optsHi = [trim((string)($r['option1_hi'] ?? '')), trim((string)($r['option2_hi'] ?? '')), trim((string)($r['option3_hi'] ?? '')), trim((string)($r['option4_hi'] ?? ''))];
            $qtHi = trim((string)($r['question_hi'] ?? ''));
            if ($qtHi === '') $qtHi = null;

            if ($type === 'mcq' || $type === 'multi_select') {
              if (!$opts[0] || !$opts[1] || !$opts[2] || !$opts[3] || $correctRaw === '') {
                $skipped++;
                if (count($errors) < 8) $errors[] = "Row $lineNo: MCQ options and correct answer are required";
                continue;
              }
              $selected = array_map('intval', $parseList($correctRaw));
              $selected = array_values(array_unique(array_filter($selected, fn($v) => $v >= 1 && $v <= 4)));
              if (!$selected || ($type === 'mcq' && count($selected) !== 1)) {
                $skipped++;
                if (count($errors) < 8) $errors[] = "Row $lineNo: correct must be 1-4 for MCQ or 1|3 style for multi_select";
                continue;
              }
            } elseif ($type === 'true_false') {
              $correctBool = $parseBool($correctRaw);
              if ($correctBool === null) {
                $skipped++;
                if (count($errors) < 8) $errors[] = "Row $lineNo: correct must be true or false for true_false";
                continue;
              }
            } elseif ($type === 'short_answer') {
              $correctText = $correctRaw;
              $correctTextHi = trim((string)($r['correct_hi'] ?? ''));
              if ($correctTextHi === '') $correctTextHi = null;
              if ($correctText === '') {
                $skipped++;
                if (count($errors) < 8) $errors[] = "Row $lineNo: correct text is required for short_answer";
                continue;
              }
            } elseif ($type === 'numeric') {
              if ($correctRaw === '' || !is_numeric($correctRaw)) {
                $skipped++;
                if (count($errors) < 8) $errors[] = "Row $lineNo: correct numeric value is required for numeric";
                continue;
              }
              $correctNumeric = (float)$correctRaw;
            }

            db()->prepare('INSERT INTO questions (exam_id,question_type,question_text,question_text_hi,correct_text,correct_text_hi,correct_numeric,correct_bool,marks,negative_marks) VALUES (?,?,?,?,?,?,?,?,?,?)')
              ->execute([$eid,$type,$qt,$qtHi,$correctText,$correctTextHi,$correctNumeric,$correctBool,$m,$n]);
            $qid = db()->lastInsertId();

            if ($type === 'mcq' || $type === 'multi_select') {
              $selected = array_map('intval', $parseList($correctRaw));
              foreach ($opts as $i => $o) {
                if ($o === '') continue;
                $oHi = $optsHi[$i] ?? '';
                $oHi = $oHi !== '' ? $oHi : null;
                db()->prepare('INSERT INTO question_options (question_id,opt_order,opt_text,opt_text_hi,is_correct) VALUES (?,?,?,?,?)')
                  ->execute([$qid, $i + 1, $o, $oHi, in_array($i + 1, $selected, true) ? 1 : 0]);
              }
            }

            $c++;
          }
          log_admin_activity('question_bulk_import', 'Bulk imported questions for exam id ' . $eid, current_user(), 'admin/questions.php?exam_id=' . $eid, ['table' => 'questions', 'count' => $c]);
          $msg = "Imported $c question" . ($c === 1 ? '' : 's');
          if ($skipped) $msg .= "; $skipped row" . ($skipped === 1 ? '' : 's') . " skipped";
          if ($errors) $msg .= ". First issues: " . implode(' | ', $errors);
          flash($msg, $c > 0 ? 'success' : 'danger');
        }
      }
    }
  }
  redirect(url('admin/questions.php?exam_id='.$eid));
}
require __DIR__ . '/_shell_top.php';
$exam = db()->prepare('SELECT exam_name FROM exams WHERE id=?'); $exam->execute([$eid]); $ex = $exam->fetch();
$qs = db()->prepare('SELECT * FROM questions WHERE exam_id=? AND deleted_at IS NULL ORDER BY id'); $qs->execute([$eid]); $rows = $qs->fetchAll();
$optsMap = [];
if ($rows) { $ids = array_column($rows,'id'); $in = str_repeat('?,',count($ids)-1).'?';
  $o = db()->prepare("SELECT * FROM question_options WHERE question_id IN ($in) AND deleted_at IS NULL ORDER BY opt_order"); $o->execute($ids);
  foreach ($o->fetchAll() as $r) $optsMap[$r['question_id']][] = $r; }
?>
<?php
// Duplicate-detection state — restore the form values when admin came back via redirect
$dupId = (int)($_GET['duplicate'] ?? 0);
$dupForm = $_SESSION['__dup_form'] ?? null;
unset($_SESSION['__dup_form']);
$dupQ = null;
if ($dupId) {
  $stmt = db()->prepare('SELECT id, question_text, question_text_hi FROM questions WHERE id=? AND deleted_at IS NULL');
  $stmt->execute([$dupId]);
  $dupQ = $stmt->fetch();
}
$questionChunks = $rows ? array_chunk($rows, (int)ceil(count($rows) / 2)) : [];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <a href="<?= url('admin/exams.php') ?>" class="text-secondary small"><i class="fas fa-arrow-left"></i> Back — <b><?= h($ex['exam_name']??'') ?></b></a>
  <?php if ($canEditQuestions): ?>
    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
      <button type="button" class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#addQuestionModal" onclick="prepareQuestionModalForAdd()">
        <i class="fas fa-plus me-1"></i>Add Question
      </button>
      <button class="btn btn-sm btn-outline-navy" data-bs-toggle="modal" data-bs-target="#bulk">Bulk MCQ Upload</button>
    </div>
  <?php else: ?>
    <span class="badge bg-secondary"><i class="fas fa-eye me-1"></i>View-only access on this exam</span>
  <?php endif; ?>
</div>
<?php if ($dupQ): ?>
<div class="alert alert-warning mb-3" role="alert">
  <h6 class="fw-bold mb-1"><i class="fas fa-triangle-exclamation me-1"></i>Duplicate Question Detected</h6>
  <div>A question with the <b>same text</b> already exists in this exam (Q-id <code>#<?= (int)$dupQ['id'] ?></code>):</div>
  <div class="mt-2 p-2 bg-white border rounded small"><?= h(mb_substr($dupQ['question_text'], 0, 200)) ?></div>
  <div class="mt-2 small">Click <b>Save Anyway</b> below to add this as a new question regardless. To edit the existing one, scroll the question list on the right.</div>
</div>
<?php endif; ?>
<div class="row g-3">
  <?php if ($questionChunks): ?>
    <?php $leftCount = count($questionChunks[0]); ?>
    <?php foreach ($questionChunks as $chunkIndex => $chunk): ?>
      <div class="col-lg-6">
        <div class="exam-card h-100">
          <h6 class="fw-bold mb-3">Questions <?= $chunkIndex === 0 ? '(1 - ' . $leftCount . ')' : '(' . ($leftCount + 1) . ' - ' . count($rows) . ')' ?></h6>
          <?php foreach ($chunk as $i => $q): ?>
            <?php $displayNo = $chunkIndex === 0 ? ($i + 1) : ($leftCount + $i + 1); ?>
            <div class="border p-2 mb-2">
              <div class="d-flex justify-content-between"><div class="small text-muted text-uppercase"><?= $displayNo ?> · <?= str_replace('_',' ',$q['question_type']) ?> · <?= $q['marks'] ?>m</div>
              <div class="d-flex gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary border-0" title="Edit question" onclick='editQuestion(<?= json_encode($q, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($optsMap[$q['id']] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'><i class="fas fa-edit"></i></button>
                <a href="<?= url('admin/preview-question.php?id='.$q['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary border-0" title="Preview exactly as student sees"><i class="fas fa-eye"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Move this question to Trash?')"><?= csrf_input() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $q['id'] ?>"><button class="btn btn-sm text-danger border-0"><i class="fas fa-trash"></i></button></form>
              </div></div>
              <div class="fw-medium mt-1"><?= h($q['question_text']) ?></div>
              <?php if (!empty($q['question_text_hi'])): ?>
                <div class="small mt-1" lang="hi" style="color:#1e3a8a; background:#eef2ff; padding:4px 8px; border-radius:3px; display:inline-block">
                  <i class="fas fa-language me-1"></i><?= h($q['question_text_hi']) ?>
                </div>
              <?php endif; ?>
              <?php if (in_array($q['question_type'],['mcq','multi_select'])): ?><ul class="small mb-0 mt-1"><?php foreach ($optsMap[$q['id']]??[] as $o): ?>
                <li class="<?= $o['is_correct']?'text-success fw-bold':'' ?>">
                  <?= h($o['opt_text']) ?><?php if (!empty($o['opt_text_hi'])): ?> <span class="text-muted" lang="hi" style="font-weight:400">· <?= h($o['opt_text_hi']) ?></span><?php endif; ?>
                </li>
              <?php endforeach; ?></ul>
              <?php elseif ($q['question_type']==='true_false'): ?><small class="text-success">Correct: <?= $q['correct_bool']?'True':'False' ?></small>
              <?php elseif ($q['question_type']==='short_answer'): ?><small class="text-success">Correct: <?= h($q['correct_text']) ?><?php if (!empty($q['correct_text_hi'])): ?> <span class="text-muted" lang="hi">· <?= h($q['correct_text_hi']) ?></span><?php endif; ?></small>
              <?php else: ?><small class="text-success">Correct: <?= h($q['correct_numeric']) ?></small><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="col-12"><div class="exam-card text-center py-4"><p class="text-muted mb-0">No questions yet</p></div></div>
  <?php endif; ?>
</div>
<div class="modal fade" id="addQuestionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="modalTitle">Add Question</h5>
          <div class="small text-muted" id="modalSubtitle">Create a new question for this exam</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="errors" class="alert alert-danger d-none" role="alert"></div>
        <form method="post" id="addQForm" onsubmit="return validateForm(event)"><?= csrf_input() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="question_id" id="question_id" value="">
          <?php if ($dupQ): ?><input type="hidden" name="force_save" value="1"><?php endif; ?>
          <?php if ($dupForm): ?>
          <script>window.__DUP_FORM = <?= json_encode($dupForm, JSON_UNESCAPED_UNICODE) ?>;</script>
          <?php endif; ?>
          <div class="mb-2">
            <label class="form-label-xs">Question Type</label>
            <select id="qtype" name="type" class="form-select" onchange="onType()">
              <option value="mcq">MCQ (single)</option><option value="multi_select">Multi Select</option>
              <option value="true_false">True / False</option><option value="short_answer">Short Answer</option><option value="numeric">Numeric</option>
            </select>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="addHindiToggle" onchange="toggleHindiFields()">
            <label class="form-check-label" for="addHindiToggle">Add Hindi entry fields</label>
          </div>
          <div class="mb-2">
            <label class="form-label-xs">Question Text (English) <span class="text-danger">*</span></label>
            <textarea id="qtext" name="text" class="form-control" rows="3" placeholder="Question text (English)"></textarea>
            <small class="text-muted" id="err-qtext"></small>
          </div>
          <div class="mb-2 d-none" id="qtext-hi-wrap">
            <label class="form-label-xs">प्रश्न (हिंदी) <span class="text-muted" style="font-weight:400; text-transform:none">— optional, shown when student switches to Hindi</span></label>
            <textarea id="qtext_hi" name="text_hi" class="form-control" rows="3" placeholder="प्रश्न हिंदी में (वैकल्पिक)" lang="hi"></textarea>
          </div>
          <div id="opts-wrap">
            <label class="form-label-xs">Options <span class="text-danger">*</span> <span class="text-muted" style="font-weight:400; text-transform:none">— English (required) and हिंदी (optional)</span></label>
            <?php for ($i=0; $i<4; $i++): ?>
              <div class="input-group mb-1">
                <span class="input-group-text" style="background-color: #fff; border-right: none;">
                  <input type="checkbox" class="co form-check-input mt-0" name="correct_opts[]" value="<?= $i+1 ?>" onchange="highlightCorrect(this)">
                </span>
                <input name="options[]" class="form-control opt-field" placeholder="Option <?= $i+1 ?> (English)">
              </div>
              <div class="input-group mb-2 d-none hindi-field-wrap">
                <span class="input-group-text" style="background-color: #f8fafc; border-right: none; font-size:11px; color:#64748b">हिं</span>
                <input name="options_hi[]" class="form-control hindi-field" lang="hi" placeholder="विकल्प <?= $i+1 ?> (हिंदी — वैकल्पिक)">
              </div>
            <?php endfor; ?>
            <small class="text-muted" id="err-opts"></small>
          </div>
          <div id="tf-wrap" class="d-none mb-2">
            <label class="form-label-xs">Correct Answer <span class="text-danger">*</span></label>
            <select name="correct_bool" class="form-select"><option value="true">Correct: True</option><option value="false">Correct: False</option></select>
          </div>
          <div id="sa-wrap" class="d-none mb-2">
            <label class="form-label-xs">Correct Answer (English) <span class="text-danger">*</span></label>
            <input id="sa-field" name="correct_text" class="form-control" placeholder="Correct answer (case-insensitive)">
            <small class="text-muted" id="err-sa"></small>
            <div class="d-none hindi-field-wrap mt-2">
              <label class="form-label-xs">सही उत्तर (हिंदी)</label>
              <input name="correct_text_hi" class="form-control hindi-field" lang="hi" placeholder="सही उत्तर हिंदी में (वैकल्पिक)">
            </div>
          </div>
          <div id="nu-wrap" class="d-none mb-2">
            <label class="form-label-xs">Correct Numeric <span class="text-danger">*</span></label>
            <input id="nu-field" name="correct_numeric" type="number" step="any" class="form-control" placeholder="Correct numeric">
            <small class="text-muted" id="err-nu"></small>
          </div>
          <div class="row g-2">
            <div class="col">
              <label class="form-label-xs">Marks <span class="text-danger">*</span></label>
              <input name="marks" type="number" step="0.5" value="1" class="form-control">
              <small class="text-muted" id="err-marks"></small>
            </div>
            <div class="col">
              <label class="form-label-xs">Negative</label>
              <input name="neg" type="number" step="0.25" value="0" class="form-control">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="addQForm" id="primarySubmitButton" class="btn btn-navy">Add Question</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="bulk"><div class="modal-dialog modal-lg"><form method="post" id="bulkForm" onsubmit="return validateBulkForm(event)" class="modal-content"><?= csrf_input() ?>
  <input type="hidden" name="action" value="bulk">
  <div class="modal-header"><h5 class="modal-title">Bulk MCQ Upload (CSV)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div id="bulk-errors" class="alert alert-danger d-none" role="alert"></div>
    <p class="small text-muted">Header: <span style="color:#e83e8c"><code>question_type,question,option1,option2,option3,option4,correct,marks,negative</code></span></p>
    <p class="small text-muted"><strong>Bilingual (optional):</strong> Add columns <code>question_hi</code>, <code>option1_hi</code>, <code>option2_hi</code>, <code>option3_hi</code>, <code>option4_hi</code>, <code>correct_hi</code> to include the हिंदी version. Students can switch between English and हिंदी during the exam — works fully offline on intranet.</p>
    <p class="small"><strong>Supported:</strong> <code>mcq</code>, <code>multi_select</code>, <code>true_false</code>, <code>short_answer</code>, <code>numeric</code>. Leave option columns blank for non-option questions.</p>
    <p class="small"><strong>Correct field:</strong> MCQ = <code>1</code> to <code>4</code>, multi_select = <code>1|3</code>, true_false = <code>true</code>/<code>false</code>, short_answer = answer text, numeric = number.</p>
    <textarea id="csvInput" name="csv" rows="10" class="form-control font-monospace small" placeholder="question,option1,option2,option3,option4,correct,marks,negative
question_type,question,option1,option2,option3,option4,correct,marks,negative
mcq,What is 2+2?,2,3,4,5,3,1,0.25
multi_select,Which are cyber security controls?,Firewall,Backup,Antivirus,Wallpaper,1|2|3,1,0.25
true_false,Phishing is a social engineering attack.,,,,,true,1,0.25
short_answer,What does VPN stand for?,,,,,Virtual Private Network,1,0.25
numeric,What is 10 divided by 2?,,,,,5,1,0.25"></textarea>
  </div>
  <div class="modal-footer"><button type="submit" class="btn btn-navy">Upload</button></div>
</form></div></div>
<script>
function parseCsvLine(line) {
  const values = [];
  let value = '';
  let inQuotes = false;

  for (let i = 0; i < line.length; i++) {
    const char = line[i];
    if (char === '"') {
      if (inQuotes && line[i + 1] === '"') {
        value += '"';
        i++;
      } else {
        inQuotes = !inQuotes;
      }
    } else if (char === ',' && !inQuotes) {
      values.push(value.trim());
      value = '';
    } else {
      value += char;
    }
  }

  values.push(value.trim());
  return values;
}

function normalizeBulkType(type) {
  type = (type || '').trim().toLowerCase().replace(/[- ]+/g, '_');
  if (type === 'multiselect') type = 'multi_select';
  return ['mcq','multi_select','true_false','short_answer','numeric'].includes(type) ? type : '';
}

function onType(){
  const t = document.getElementById('qtype').value;
  document.getElementById('opts-wrap').classList.toggle('d-none', !['mcq','multi_select'].includes(t));
  document.getElementById('tf-wrap').classList.toggle('d-none', t!=='true_false');
  document.getElementById('sa-wrap').classList.toggle('d-none', t!=='short_answer');
  document.getElementById('nu-wrap').classList.toggle('d-none', t!=='numeric');
  document.querySelectorAll('.co').forEach(c=>{
    c.type = t==='mcq'?'radio':'checkbox'; 
    c.name = 'correct_opts[]';
  });
  toggleHindiFields();
  clearErrors();
}

function toggleHindiFields() {
  const enabled = document.getElementById('addHindiToggle')?.checked || false;
  const questionWrap = document.getElementById('qtext-hi-wrap');
  if (questionWrap) {
    questionWrap.classList.toggle('d-none', !enabled);
  }
  document.querySelectorAll('.hindi-field-wrap').forEach(wrap => {
    wrap.classList.toggle('d-none', !enabled);
  });
  document.querySelectorAll('.hindi-field').forEach(field => {
    field.disabled = !enabled;
  });
}

function highlightCorrect(checkbox) {
  const inputGroup = checkbox.closest('.input-group');
  const input = inputGroup.querySelector('.opt-field');
  const type = document.getElementById('qtype').value;
  
  // Reset all highlights
  document.querySelectorAll('.opt-field').forEach(opt => {
    opt.style.borderColor = '';
    opt.style.backgroundColor = '';
  });
  
  // Highlight selected option
  if (checkbox.checked) {
    input.style.borderColor = '#198754';
    input.style.backgroundColor = '#f0fdf4';
  }
}

function prepareQuestionModalForAdd() {
  const form = document.getElementById('addQForm');
  if (!form) return;
  form.reset();
  form.querySelector('input[name="action"]').value = 'add';
  document.getElementById('question_id').value = '';
  document.getElementById('modalTitle').textContent = 'Add Question';
  document.getElementById('modalSubtitle').textContent = 'Create a new question for this exam';
  const btn = document.getElementById('primarySubmitButton');
  if (btn) btn.textContent = 'Add Question';
  clearErrors();
  onType();
}

function editQuestion(question, options) {
  const form = document.getElementById('addQForm');
  if (!form) return false;
  prepareQuestionModalForAdd();
  form.querySelector('input[name="action"]').value = 'edit';
  document.getElementById('question_id').value = question.id || '';
  document.getElementById('qtype').value = question.question_type || 'mcq';
  onType();
  document.getElementById('qtext').value = question.question_text || '';
  document.getElementById('qtext_hi').value = question.question_text_hi || '';
  document.querySelector('input[name="marks"]').value = question.marks ?? '1';
  document.querySelector('input[name="neg"]').value = question.negative_marks ?? '0';
  if (question.question_type === 'short_answer') {
    document.getElementById('sa-field').value = question.correct_text || '';
    const hiField = document.querySelector('input[name="correct_text_hi"]');
    if (hiField) hiField.value = question.correct_text_hi || '';
  }
  if (question.question_type === 'numeric') {
    document.getElementById('nu-field').value = question.correct_numeric ?? '';
  }
  if (question.question_type === 'true_false') {
    const tfSelect = document.querySelector('select[name="correct_bool"]');
    if (tfSelect) tfSelect.value = question.correct_bool ? 'true' : 'false';
  }
  const optionEls = Array.from(document.querySelectorAll('input[name="options[]"]'));
  const optionHiEls = Array.from(document.querySelectorAll('input[name="options_hi[]"]'));
  const correctEls = Array.from(document.querySelectorAll('input[name="correct_opts[]"]'));
  if (!Array.isArray(options)) options = [];
  optionEls.forEach((optEl, idx) => {
    const opt = options[idx] || {};
    optEl.value = opt.opt_text || '';
    if (optionHiEls[idx]) optionHiEls[idx].value = opt.opt_text_hi || '';
    if (correctEls[idx]) correctEls[idx].checked = !!opt.is_correct;
  });
  correctEls.forEach(cb => {
    if (cb.checked) {
      highlightCorrect(cb);
    }
  });
  const hasHindi = Boolean(
    (question.question_text_hi || '').toString().trim() ||
    (question.correct_text_hi || '').toString().trim() ||
    options.some(opt => (opt.opt_text_hi || '').toString().trim())
  );
  const hindiToggle = document.getElementById('addHindiToggle');
  if (hindiToggle) {
    hindiToggle.checked = hasHindi;
    toggleHindiFields();
  }
  const btn = document.getElementById('primarySubmitButton');
  if (btn) btn.textContent = 'Save Changes';
  document.getElementById('modalTitle').textContent = 'Edit Question';
  document.getElementById('modalSubtitle').textContent = 'Update the question text, answers, and options.';
  const modalEl = document.getElementById('addQuestionModal');
  if (modalEl && window.bootstrap) {
    new bootstrap.Modal(modalEl).show();
  }
  return false;
}

function clearErrors() {
  document.getElementById('errors').classList.add('d-none');
  document.querySelectorAll('[id^="err-"]').forEach(e => e.textContent = '');
}

function validateForm(e) {
  e.preventDefault();
  clearErrors();
  const errors = [];
  const type = document.getElementById('qtype').value;
  const qtext = document.getElementById('qtext').value.trim();
  const marks = document.querySelector('input[name="marks"]').value;
  
  // Validate question text
  if (!qtext) {
    errors.push('Question text is required');
    document.getElementById('err-qtext').textContent = '⚠ Required';
  }
  
  // Validate marks
  if (!marks || parseFloat(marks) <= 0) {
    errors.push('Marks must be greater than 0');
    document.getElementById('err-marks').textContent = '⚠ Must be > 0';
  }
  
  // Type-specific validation
  if (['mcq', 'multi_select'].includes(type)) {
    const options = Array.from(document.querySelectorAll('input[name="options[]"]')).map(o => o.value.trim());
    const correctOpts = Array.from(document.querySelectorAll('input[name="correct_opts[]"]')).filter(o => o.checked);
    
    // Check all 4 options are filled
    const emptyOpts = options.filter(o => !o).length;
    if (emptyOpts > 0) {
      errors.push(`All 4 options are required (${emptyOpts} empty)`);
      document.getElementById('err-opts').textContent = `⚠ ${emptyOpts} option(s) empty`;
    }
    
    // Check correct option selected
    if (correctOpts.length === 0) {
      errors.push(`Mark the correct option (at least 1 required)`);
      document.getElementById('err-opts').innerHTML += '<br>⚠ Select correct answer';
    }
  } else if (type === 'true_false') {
    // true_false always has a value
  } else if (type === 'short_answer') {
    const saField = document.getElementById('sa-field').value.trim();
    if (!saField) {
      errors.push('Correct answer is required');
      document.getElementById('err-sa').textContent = '⚠ Required';
    }
  } else if (type === 'numeric') {
    const nuField = document.getElementById('nu-field').value.trim();
    if (!nuField || isNaN(nuField)) {
      errors.push('Correct numeric value is required');
      document.getElementById('err-nu').textContent = '⚠ Required';
    }
  }
  
  // Show errors if any
  if (errors.length > 0) {
    const errorDiv = document.getElementById('errors');
    errorDiv.innerHTML = '<strong>Please fix the following:</strong><ul class="mb-0">' + 
      errors.map(e => '<li>' + e + '</li>').join('') + 
      '</ul>';
    errorDiv.classList.remove('d-none');
    return false;
  }
  
  // Form is valid, submit
  document.getElementById('addQForm').submit();
  return false;
}

function validateBulkForm(e) {
  e.preventDefault();
  const csv = document.getElementById('csvInput').value.trim();
  
  if (!csv) {
    showBulkError('CSV content is required');
    return false;
  }
  
  const lines = csv.split(/\r\n|\n|\r/);
  const header = parseCsvLine(lines[0]).map(h => h.trim().toLowerCase());
  
  const errors = [];
  
  // Check required columns
  const hasTypeColumn = header.includes('question_type');
  const required = hasTypeColumn
    ? ['question_type','question','option1','option2','option3','option4','correct']
    : ['question','option1','option2','option3','option4','correct'];
  const missing = required.filter(r => !header.includes(r));
  if (missing.length > 0) {
    errors.push('Missing required columns: ' + missing.join(', '));
  }
  
  // Validate data rows
  for (let i = 1; i < lines.length; i++) {
    if (!lines[i].trim()) continue;
    const data = parseCsvLine(lines[i]);
    
    if (data.length !== header.length) {
      errors.push(`Row ${i}: Column count mismatch`);
      continue;
    }
    
    const row = {};
    header.forEach((h, idx) => row[h] = data[idx]);

    const type = hasTypeColumn ? normalizeBulkType(row['question_type']) : 'mcq';
    
    if (!row['question']) errors.push(`Row ${i}: Question is empty`);

    if (hasTypeColumn && !type) {
      errors.push(`Row ${i}: Invalid question_type (use mcq, multi_select, true_false, short_answer, numeric)`);
      continue;
    }

    const correct = (row['correct'] || '').trim();

    if (type === 'mcq' || type === 'multi_select') {
      if (!row['option1']) errors.push(`Row ${i}: Option 1 is empty`);
      if (!row['option2']) errors.push(`Row ${i}: Option 2 is empty`);
      if (!row['option3']) errors.push(`Row ${i}: Option 3 is empty`);
      if (!row['option4']) errors.push(`Row ${i}: Option 4 is empty`);

      const selected = correct.split(/[|;,]+/).map(v => parseInt(v.trim())).filter(v => !isNaN(v) && v >= 1 && v <= 4);
      if (selected.length === 0) {
        errors.push(`Row ${i}: Correct must be 1-4 or 1|3 style (got: ${row['correct']})`);
      }
      if (type === 'mcq' && selected.length !== 1) {
        errors.push(`Row ${i}: MCQ must have exactly one correct option`);
      }
    } else if (type === 'true_false') {
      if (!['true','false','1','0','yes','no'].includes(correct.toLowerCase())) {
        errors.push(`Row ${i}: Correct must be true or false for true_false`);
      }
    } else if (type === 'short_answer') {
      if (!correct) errors.push(`Row ${i}: Correct text is empty for short_answer`);
    } else if (type === 'numeric') {
      if (!correct || isNaN(correct)) errors.push(`Row ${i}: Correct numeric value is invalid for numeric`);
    } else {
      if (!row['option1']) errors.push(`Row ${i}: Option 1 is empty`);
      if (!row['option2']) errors.push(`Row ${i}: Option 2 is empty`);
      if (!row['option3']) errors.push(`Row ${i}: Option 3 is empty`);
      if (!row['option4']) errors.push(`Row ${i}: Option 4 is empty`);
      const selected = correct.split(/[|;,]+/).map(v => parseInt(v.trim())).filter(v => !isNaN(v) && v >= 1 && v <= 4);
      if (selected.length === 0) {
        errors.push(`Row ${i}: Correct must be 1, 2, 3, or 4 (got: ${row['correct']})`);
      }
    }
    
    const marks = parseFloat(row['marks'] || 1);
    if (isNaN(marks) || marks <= 0) {
      errors.push(`Row ${i}: Marks must be a valid number > 0`);
    }
  }
  
  if (errors.length > 0) {
    showBulkError(errors);
    return false;
  }
  
  // Form is valid, submit
  document.getElementById('bulkForm').submit();
  return false;
}

function showBulkError(errors) {
  const errorDiv = document.getElementById('bulk-errors');
  if (typeof errors === 'string') {
    errorDiv.innerHTML = '<strong>' + errors + '</strong>';
  } else {
    errorDiv.innerHTML = '<strong>Please fix the following errors:</strong><ul class="mb-0">' + 
      errors.map(e => '<li>' + e + '</li>').join('') + 
      '</ul>';
  }
  errorDiv.classList.remove('d-none');
}

onType();
// Restore typed values if user came back via duplicate-warning redirect
if (window.__DUP_FORM) {
  const f = window.__DUP_FORM;
  const form = document.getElementById('addQForm');
  if (form) {
    for (const [k, v] of Object.entries(f)) {
      if (k === 'action' || k === '_csrf' || k === 'force_save') continue;
      if (Array.isArray(v)) {
        const els = form.querySelectorAll(`[name="${k}[]"]`);
        v.forEach((val, i) => { if (els[i]) els[i].value = val; });
      } else {
        const el = form.querySelector(`[name="${k}"]`);
        if (el) el.value = v;
      }
    }
    // Re-tick correct-option checkboxes
    if (Array.isArray(f.correct_opts)) {
      f.correct_opts.forEach(idx => {
        const cb = form.querySelector(`input[type="checkbox"][name="correct_opts[]"][value="${idx}"]`);
        if (cb) { cb.checked = true; highlightCorrect(cb); }
      });
    }
    // Update primary submit button label
    const btn = form.querySelector('button[type="submit"], button:not([type])');
    if (btn) { btn.classList.remove('btn-success','btn-navy'); btn.classList.add('btn-warning'); btn.innerHTML = '<i class="fas fa-triangle-exclamation me-1"></i>Save Anyway (duplicate confirmed)'; }
  }
  const hindiToggle = document.getElementById('addHindiToggle');
  if (hindiToggle) {
    const hasHindi = !!(String(f.text_hi || '').trim() || String(f.correct_text_hi || '').trim() || (Array.isArray(f.options_hi) && f.options_hi.some(v => String(v || '').trim())));
    hindiToggle.checked = hasHindi;
    toggleHindiFields();
  }
  const modalEl = document.getElementById('addQuestionModal');
  if (modalEl && window.bootstrap) {
    new bootstrap.Modal(modalEl).show();
  }
}
</script>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
