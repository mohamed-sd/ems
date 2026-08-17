<?php
/**
 * tests/two_axis_negative_test.php — اختبارٌ سلبيٌّ يُثبت أن المحورَين **بنيةٌ** لا تسمية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تصحيحُ المالك (2026-08-19 · رابعًا): «‏«الصمتُ لا يرقّي» لا يكفي إثباتًا أن
 *   البنيةَ محوران لا تعدادٌ واحدٌ غُيّرت أسماؤه».
 *
 * ◆ فالإثباتُ ثلاثةُ ادعاءاتٍ **تُختبر بالمحاولةِ الفاشلة** لا بالقراءة:
 *   ① استحالةُ `OWNER_REVIEW_OVERDUE ⇐ APPROVED` بلا فاعلٍ حقيقيّ.
 *   ② **إمكانُ اجتماعِ** `decision_state = OWNER_REVIEW_OVERDUE` مع
 *      `application_state = PROVISIONALLY_APPLIED_NO_OBJECTION` و`decided_by = NULL`
 *      في صفٍّ واحدٍ بلا اختلاط — وهو ما يستحيل في تعدادٍ واحد.
 *   ③ استحالةُ التطبيقِ المؤقَّتِ في المجالاتِ الستةِ المحظورة — **بقيدٍ يشتقُّ
 *      المنعَ من مجالِ الصفِّ** لا من عَلَمٍ يضبطه الكاتب.
 *
 * ◆ ويعمل على **صفِّ اختبارٍ يُنشئه ويحذفه** — لا يمسُّ صفًّا حيًّا.
 *
 * التشغيل: php tests/two_axis_negative_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

$pass = 0; $fail = 0;
function ok($cond, $msg, $detail = '') {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✔ {$msg}\n"; }
    else { $fail++; echo "  ✘ FAIL: {$msg}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

$TEST_ROUTE = '__two_axis_negative_test__.php';
$conn->query("DELETE FROM nav_canonical WHERE route = '{$TEST_ROUTE}'");

echo "════ اختبارٌ سلبيّ: المحوران بنيةٌ لا تسمية ════\n\n";

/* ── تهيئة: صفُّ اختبارٍ في مجالِ التنقلِ المسموح ── */
$ins = $conn->prepare("INSERT INTO nav_canonical
    (route, canonical_ar, level_no, level_name, group_name, sort_no, status,
     decision_state, application_state, decided_by, decision_source,
     provisional_reversible, policy_domain)
    VALUES (?, 'صفُّ اختبارٍ سلبيّ', 2, 'العمليات', 'اختبار', 999, 'PENDING_OWNER',
            'PENDING_OWNER', 'CURRENT', NULL, NULL, 1, 'NAVIGATION_NAMING_POSITION')");
$ins->bind_param('s', $TEST_ROUTE);
if (!$ins->execute()) { exit("تعذّر إنشاءُ صفِّ الاختبار: {$ins->error}\n"); }
echo "▐ صفُّ اختبارٍ أُنشئ (مجالُه التنقلُ المسموح)\n\n";

$esc = $conn->real_escape_string($TEST_ROUTE);

/* ══ ① الصمتُ لا يُرقّي: OWNER_REVIEW_OVERDUE ⇐ APPROVED بلا فاعل ══ */
echo "▐ ① استحالةُ الحسمِ بلا فاعلٍ حقيقيّ\n";
$conn->query("UPDATE nav_canonical SET decision_state='OWNER_REVIEW_OVERDUE' WHERE route='{$esc}'");
$r = @$conn->query("UPDATE nav_canonical SET decision_state='APPROVED', decided_by=NULL, decision_source=NULL
                     WHERE route='{$esc}'");
ok($r === false, 'الترقيةُ إلى APPROVED بلا فاعلٍ ولا مصدرٍ **مرفوضة**', $r ? 'مرَّت — عيب!' : '');
echo "      رسالةُ القيد: " . mb_substr((string) $conn->error, 0, 90) . "\n";

$row = $conn->query("SELECT decision_state, decided_by FROM nav_canonical WHERE route='{$esc}'")->fetch_assoc();
ok($row['decision_state'] === 'OWNER_REVIEW_OVERDUE', 'الحالةُ بقيت OWNER_REVIEW_OVERDUE بعد الرفض', $row['decision_state']);
ok($row['decided_by'] === null, '`decided_by` بقي NULL — لا يُنسب قرارٌ لأحد');

/* وبفاعلٍ حقيقيٍّ يمرُّ — فالقيدُ يمنع الصمتَ لا الحسمَ المشروع */
$realUid = 0;
$q = $conn->query("SELECT MIN(id) i FROM users WHERE company_id = 4");
if ($q && ($x = $q->fetch_assoc())) { $realUid = (int) $x['i']; }
$r2 = @$conn->query("UPDATE nav_canonical SET decision_state='APPROVED', decided_by={$realUid}
                      WHERE route='{$esc}'");
ok($r2 !== false, 'الحسمُ **بفاعلٍ حقيقيٍّ يمرُّ** — القيدُ يمنع الصمتَ لا العمل', $conn->error);
$conn->query("UPDATE nav_canonical SET decision_state='OWNER_REVIEW_OVERDUE', decided_by=NULL WHERE route='{$esc}'");

/* ══ ② اجتماعُ المحورَين في صفٍّ واحدٍ بلا اختلاط ══ */
echo "\n▐ ② المحوران يجتمعان مستقلَّين في صفٍّ واحد\n";
$r3 = @$conn->query("UPDATE nav_canonical
                        SET application_state='PROVISIONALLY_APPLIED_NO_OBJECTION', provisional_since=NOW()
                      WHERE route='{$esc}'");
ok($r3 !== false, 'التطبيقُ المؤقَّتُ **مسموحٌ** والقرارُ غيرُ محسوم', $conn->error);
$row = $conn->query("SELECT decision_state, application_state, decided_by FROM nav_canonical WHERE route='{$esc}'")->fetch_assoc();
ok($row['decision_state'] === 'OWNER_REVIEW_OVERDUE'
   && $row['application_state'] === 'PROVISIONALLY_APPLIED_NO_OBJECTION'
   && $row['decided_by'] === null,
   'الثلاثةُ اجتمعت: قرار=OWNER_REVIEW_OVERDUE · تطبيق=PROVISIONALLY_APPLIED · فاعل=NULL',
   json_encode($row, JSON_UNESCAPED_UNICODE));
echo "      ◆ وهذا **يستحيل في تعدادٍ واحد**: قيمةٌ واحدةٌ لا تقول «لم يُحسم» و«يعمل الآن» معًا.\n";

/* والعكسُ متاح: يُعكس التطبيقُ ولا يُمَسُّ القرار */
$r4 = @$conn->query("UPDATE nav_canonical SET application_state='ROLLED_BACK', provisional_since=NULL WHERE route='{$esc}'");
$row = $conn->query("SELECT decision_state, application_state FROM nav_canonical WHERE route='{$esc}'")->fetch_assoc();
ok($r4 !== false && $row['application_state'] === 'ROLLED_BACK' && $row['decision_state'] === 'OWNER_REVIEW_OVERDUE',
   'العكسُ يمسُّ محورَ التطبيقِ وحدَه — والقرارُ كما هو');

/* ══ ③ القيدُ المجاليّ: التطبيقُ المؤقَّتُ محظورٌ في الستة ══ */
echo "\n▐ ③ القيدُ المجاليُّ — يشتقُّ المنعَ من مجالِ الصفِّ لا من عَلَم\n";
$DOMAINS = array('PERMISSIONS', 'APPROVAL_LADDERS', 'FINANCIAL_CAPS',
                 'SEGREGATION_OF_DUTIES', 'LEGAL_OBLIGATIONS', 'FINANCIAL_DECISIONS');
$blockedAll = true;
foreach ($DOMAINS as $d) {
    $conn->query("UPDATE nav_canonical SET application_state='CURRENT', policy_domain='{$d}',
                         provisional_reversible=1 WHERE route='{$esc}'");
    $rr = @$conn->query("UPDATE nav_canonical SET application_state='PROVISIONALLY_APPLIED_NO_OBJECTION'
                          WHERE route='{$esc}'");
    if ($rr !== false) { $blockedAll = false; echo "      ✘ {$d}: مرَّ!\n"; }
    else { echo "      ✔ {$d}: مُنع\n"; }
}
ok($blockedAll, 'المجالاتُ الستةُ كلُّها **ترفض** التطبيقَ المؤقَّتَ ولو رُفع العَلَم');
echo "      ◆ والعَلَمُ كان مرفوعًا (provisional_reversible=1) في كلِّ محاولة —\n";
echo "        فالمنعُ من **المجالِ** لا من إقرارِ الكاتب.\n";

/* ── التنظيف ── */
$conn->query("DELETE FROM nav_canonical WHERE route='{$esc}'");
$left = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical WHERE route='{$esc}'")->fetch_assoc()['c'];
ok($left === 0, 'صفُّ الاختبارِ حُذف — لا أثرَ في السجلِّ الحاكم');

echo "\n══════════════════════════════════════\n";
echo "  النتيجة: {$pass} نجاح · {$fail} فشل\n";
exit($fail === 0 ? 0 : 1);
