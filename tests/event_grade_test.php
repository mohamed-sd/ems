<?php
/**
 * tests/event_grade_test.php — رباعية AC-E01-05 «لا إقفالَ لمبدئي»
 * سماح: حدث بلا وسم = نهائي ضمنًا لا يمنع · منع: الموسوم مبدئيًّا يمنع إقفال
 * فترته باسمه · ترقية: الترقية لنهائي تفك المنع وتُختم · عطالة: إعادة الوسم
 * لا تضاعف الصفوف (مفتاح الحدث فريد).
 * التشغيل: php tests/event_grade_test.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
require_once __DIR__ . '/../includes/period_guard.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$pass = 0; $fail = 0;
function ok($c, $l) { global $pass, $fail; $c ? $pass++ : $fail++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ ') . $l . "\n"); }

/* فترة اختبار معزولة زمنيًّا (2027-10) + حدثان تجريبيان داخلها */
$conn->query("DELETE FROM fin_event_grades WHERE reason LIKE 'FEG-TEST%'");
$conn->query("DELETE FROM fin_financial_events WHERE event_no LIKE 'FEG-TEST%'");
$conn->query("DELETE FROM fin_financial_periods WHERE company_id = 4 AND period_type='month' AND start_date = '2027-10-01'");
$conn->query("INSERT INTO fin_financial_periods (company_id, period_type, start_date, end_date, state, posting_allowed)
              VALUES (4, 'month', '2027-10-01', '2027-10-31', 'open', 1)");
$pid = intval($conn->insert_id);

/* الأعمدة الملزمة فعلًا: event_no · event_type · source_module · amount
   (كان الاختبار يفترض عمود description فيفشل الإدراج صامتًا ويسرق insert_id
   من إدراج الفترة السابق — درس: افحص المُرجَع لا العداد) */
$mk = function ($no) use ($conn) {
    $r = $conn->query("INSERT INTO fin_financial_events
                  (company_id, event_no, event_type, source_module, amount, occurred_at, state)
                  VALUES (4, '{$no}', 'expense', 'system', 100.00, '2027-10-15 10:00:00', 'draft')");
    return $r ? intval($conn->insert_id) : 0;
};
$e1 = $mk('FEG-TEST-A');
$e2 = $mk('FEG-TEST-B');
ok($e1 > 0 && $e2 > 0 && $e1 !== $pid, 'حدثان تجريبيان في فترة 2027-10 (إدراج مُتحقَّق لا عدّاد مسروق)');

/* ① بلا وسم: نهائي ضمنًا — لا مانع درجة */
$blk = ems_period_close_blockers($conn, 4, $pid);
$hasGradeBlock = false;
foreach ($blk as $b) { if (mb_strpos($b['label'], 'AC-E01-05') !== false) { $hasGradeBlock = true; } }
ok(!$hasGradeBlock, 'بلا وسم = نهائي ضمنًا (لا مانع درجة)');

/* ② الوسم المبدئي يمنع الإقفال باسمه */
ok(ems_event_grade_set($conn, 4, $e1, 'provisional', 'FEG-TEST تقديري بانتظار المستند', 881), 'وُسم الحدث مبدئيًّا');
$blk = ems_period_close_blockers($conn, 4, $pid);
$n = 0;
foreach ($blk as $b) { if (mb_strpos($b['label'], 'AC-E01-05') !== false) { $n = $b['count']; } }
ok($n === 1, "المانع حاضر باسم AC-E01-05 وعدّه 1 (وُجد: {$n})");

/* ③ الترقية إلى نهائي تفك المنع وتُختم */
ok(ems_event_grade_set($conn, 4, $e1, 'final', 'FEG-TEST اكتمل مستنده', 881), 'رُقّي إلى نهائي');
$g = $conn->query("SELECT grade, finalized_at, finalized_by FROM fin_event_grades WHERE event_id = {$e1}")->fetch_assoc();
ok($g['grade'] === 'final' && $g['finalized_at'] !== null && intval($g['finalized_by']) === 881,
   'الترقية مختومة بلحظتها وفاعلها');
$blk = ems_period_close_blockers($conn, 4, $pid);
$hasGradeBlock = false;
foreach ($blk as $b) { if (mb_strpos($b['label'], 'AC-E01-05') !== false) { $hasGradeBlock = true; } }
ok(!$hasGradeBlock, 'المنع انفك بعد الترقية');

/* ④ عطالة: صف واحد لكل حدث مهما تكرر الوسم */
ems_event_grade_set($conn, 4, $e2, 'provisional', 'FEG-TEST-2', 881);
ems_event_grade_set($conn, 4, $e2, 'provisional', 'FEG-TEST-2 مكرر', 881);
$c = intval($conn->query("SELECT COUNT(*) FROM fin_event_grades WHERE event_id = {$e2}")->fetch_row()[0]);
ok($c === 1, 'عطالة: صف درجة واحد للحدث');

/* تنظيف آثار الاختبار وحدها */
$conn->query("DELETE FROM fin_event_grades WHERE event_id IN ({$e1},{$e2})");
$conn->query("DELETE FROM fin_financial_events WHERE id IN ({$e1},{$e2})");
$conn->query("DELETE FROM fin_financial_periods WHERE id = {$pid}");
fwrite(STDOUT, "\n══ النتيجة: {$pass} ناجحة · {$fail} فاشلة ══\n");
exit($fail ? 1 : 0);
