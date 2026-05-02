<?php
// One-shot test seeder — creates test data for Phase-2 feature validation.
// Run: php /app/bel_exam_portal/seed_test.php
require_once __DIR__ . '/includes/helpers.php';
ensure_softdelete_and_permissions();
ensure_bilingual_columns();

$pdo = db();
ensure_exam_code_column();

// 1) super admin already exists from schema.sql (admin@belkotdwar.in / Admin@123)
// 2) Create a regular (non-super) admin
$regAdminEmail = 'rakesh@belkotdwar.in';
$chk = $pdo->prepare('SELECT id FROM users WHERE email=?');
$chk->execute([$regAdminEmail]);
if (!$chk->fetchColumn()) {
    $pdo->prepare('INSERT INTO users (role,name,email,username,password_hash,plain_password,is_super) VALUES ("admin",?,?,?,?,?,0)')
        ->execute(['Rakesh Sharma', $regAdminEmail, 'rakesh', password_hash('Rakesh@123', PASSWORD_BCRYPT), 'Rakesh@123']);
    echo "Created regular admin rakesh@belkotdwar.in / Rakesh@123\n";
}
$regAdminId = (int)$pdo->query("SELECT id FROM users WHERE email='$regAdminEmail'")->fetchColumn();
$superAdminId = (int)$pdo->query("SELECT id FROM users WHERE email='admin@belkotdwar.in'")->fetchColumn();

// 3) Create an active exam hosted by Rakesh (regular admin)
$pdo->prepare("DELETE FROM exams WHERE exam_name LIKE 'Math Aptitude%' AND deleted_at IS NULL")->execute();
$start = date('Y-m-d H:i:s', time() - 600);   // 10 min ago
$end   = date('Y-m-d H:i:s', time() + 3600);  // 60 min later
$pdo->prepare("INSERT INTO exams (exam_name, exam_code, duration_minutes, max_attempts, start_time, end_time, total_marks, instructions, created_by) VALUES ('Math Aptitude Test 2026','MAT2026A',60,1,?,?,10,'Answer all questions.',?)")
    ->execute([$start, $end, $regAdminId]);
$examId = (int)$pdo->lastInsertId();
echo "Created exam #$examId 'Math Aptitude Test 2026' hosted by Rakesh (id=$regAdminId)\n";

// 4) 2 MCQ questions (bilingual)
$q1 = $pdo->prepare("INSERT INTO questions (exam_id,question_type,question_text,question_text_hi,marks,negative_marks) VALUES (?,?,?,?,?,?)");
$q1->execute([$examId,'mcq','What is 15% of 200?','200 का 15% क्या है?',2,0.5]);
$qid1 = (int)$pdo->lastInsertId();
$oStmt = $pdo->prepare("INSERT INTO question_options (question_id,opt_order,opt_text,opt_text_hi,is_correct) VALUES (?,?,?,?,?)");
$oStmt->execute([$qid1,1,'20','बीस',0]);
$oStmt->execute([$qid1,2,'30','तीस',1]);
$oStmt->execute([$qid1,3,'40','चालीस',0]);
$oStmt->execute([$qid1,4,'50','पचास',0]);

$q1->execute([$examId,'mcq','Which is the capital of India?','भारत की राजधानी कौन सी है?',2,0]);
$qid2 = (int)$pdo->lastInsertId();
$oStmt->execute([$qid2,1,'Mumbai','मुंबई',0]);
$oStmt->execute([$qid2,2,'New Delhi','नई दिल्ली',1]);
$oStmt->execute([$qid2,3,'Kolkata','कोलकाता',0]);
$oStmt->execute([$qid2,4,'Chennai','चेन्नई',0]);
echo "Added 2 bilingual MCQ questions\n";

// 5) Create 4 students (mix of writing / submitted / absent) — hosted by Rakesh
$students = [
  ['Amit Kumar',   'BEL1001', '1998-04-12', 'amit@bel.in',   'writing'],
  ['Priya Sharma', 'BEL1002', '2000-08-21', 'priya@bel.in',  'writing'],
  ['Suresh Patel', 'BEL1003', '1997-01-15', 'suresh@bel.in', 'submitted'],
  ['Neha Singh',   'BEL1004', '2001-11-05', 'neha@bel.in',   'absent'],
];
foreach ($students as $s) {
    $pdo->prepare('DELETE FROM users WHERE email=? AND role="student"')->execute([$s[3]]);
    $pdo->prepare('INSERT INTO users (role,name,email,username,password_hash,plain_password,roll_number,dob,category,created_by) VALUES ("student",?,?,?,?,?,?,?,?,?)')
        ->execute([$s[0], $s[3], strtolower(str_replace(' ','',$s[0])), password_hash('Pass@123', PASSWORD_BCRYPT), 'Pass@123', $s[1], $s[2], 'internal', $regAdminId]);
    $uid = (int)$pdo->lastInsertId();
    // assign exam
    $pdo->prepare('INSERT INTO exam_assignments (user_id, exam_id) VALUES (?,?)')->execute([$uid, $examId]);
    // create attempts based on status
    if ($s[4] === 'writing' || $s[4] === 'submitted') {
        $dbStatus = $s[4] === 'writing' ? 'in_progress' : 'submitted';
        $ends = date('Y-m-d H:i:s', time() + 3000);
        $pdo->prepare('INSERT INTO attempts (user_id,exam_id,attempt_no,started_at,ends_at,status,score,total,submitted_at) VALUES (?,?,1,?,?,?,?,?,?)')
            ->execute([$uid, $examId, date('Y-m-d H:i:s', time()-300), $ends, $dbStatus, $s[4]==='submitted'?3:null, $s[4]==='submitted'?4:null, $s[4]==='submitted'?date('Y-m-d H:i:s'):null]);
        $attId = (int)$pdo->lastInsertId();
        if ($s[4] === 'submitted') {
            $pdo->prepare('INSERT INTO attempt_answers (attempt_id,question_id,selected_json,is_correct) VALUES (?,?,?,?)')
                ->execute([$attId, $qid1, '{"selected":[2]}', 1]);
            $pdo->prepare('INSERT INTO attempt_answers (attempt_id,question_id,selected_json,is_correct) VALUES (?,?,?,?)')
                ->execute([$attId, $qid2, '{"selected":[1]}', 0]);
        }
        // Give Amit one violation for demo
        if ($s[0] === 'Amit Kumar') {
            $pdo->prepare('INSERT INTO violations (attempt_id,user_id,event_type,description,event_time) VALUES (?,?,?,?,NOW())')
                ->execute([$attId, $uid, 'tab_switch', 'Student switched browser tab']);
        }
    }
    echo "Student {$s[0]} ({$s[1]}) — status: {$s[4]}\n";
}

// 6) Create a second exam hosted by super admin to test ownership isolation
$pdo->prepare("DELETE FROM exams WHERE exam_name LIKE 'GK Quiz%' AND deleted_at IS NULL")->execute();
$pdo->prepare("INSERT INTO exams (exam_name, exam_code, duration_minutes, max_attempts, start_time, end_time, total_marks, created_by) VALUES ('GK Quiz 2026','GK2026A',30,1,?,?,5,?)")
    ->execute([$start, $end, $superAdminId]);
$gkId = (int)$pdo->lastInsertId();
echo "Created exam #$gkId 'GK Quiz 2026' hosted by Super Admin\n";

echo "\nSEED COMPLETE.\n";
echo "Super admin: admin@belkotdwar.in / Admin@123\n";
echo "Regular admin: $regAdminEmail / Rakesh@123\n";
