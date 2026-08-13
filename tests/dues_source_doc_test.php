<?php
/**
 * tests/dues_source_doc_test.php — M-11
 * ═══════════════════════════════════════════════════════════════════════════
 * مصدريةُ التحميلات (ENT-02 §3).
 *
 * ما يُثبته:
 *   ① **قيدٌ بنيويٌّ لا فحصٌ تطبيقي**: خصمٌ بلا مصدرٍ **ترفضه القاعدةُ نفسُها**
 *      (`ck_dues_debit_source`) — فلا يلتفّ عليه مسارٌ جديدٌ نُسي فيه المصدر.
 *   ② **والاستحقاقُ حرٌّ منه**: مصدرُه حدثُ المروحة، وإلزامُه بمستندٍ يعطّل محرِّكًا يعمل.
 *   ③ **الردمُ دقيقٌ لا تخميني**: الجزاءان نُسبا لاحتسابهما بمطابقة الحدث،
 *      والصفُّ التاريخيُّ وُسم `legacy_no_ref` — **ولم يُمسّ ولم يُخفَ**.
 *   ④ **الفجوةُ المعلَنة**: `pending_source` تُقبل للسلف والخصومات وحدَها —
 *      وما له مستندٌ مبنيٌّ يُرفض بدونه (فلا تصير بابًا خلفيًّا).
 *   ⑤ **الكتّابُ يمرّرون مصدرَهم**: الجزاءُ والتسويةُ يكتبان `source_doc_*`.
 *   ⑥ **كلُّ رقمٍ في التسوية ينقر إلى مستنده** — والوسمُ بلغة المهمة.
 *
 * التشغيل: php tests/dues_source_doc_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
  * ⇐ شواهدُ أحكامٍ: INJ-0175 · INJ-0192
 * (رُبطت بمراجعةٍ خصمٍ 2026-08-12 — الحجّةُ وسببُ قبولِها في docs/fix_progress/BINDINGS.md)
*/

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'dues source test');
require_once dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$CO = 4;
$MARK = 'DSRC' . getmypid();

$cleanup = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM fin_dues WHERE period_ref LIKE '{$MARK}%'");
};
$cleanup();
register_shutdown_function($cleanup);

/** إدراجٌ خامٌّ يُرجع رسالةَ الخطأ بدل أن يرمي — لاختبار القيد البنيوي. */
function tryInsert($conn, $sql) {
    try { return $conn->query($sql) ? '' : $conn->error; }
    catch (\Throwable $t) { return $t->getMessage(); }
}

fwrite(STDOUT, "\n══ M-11 — مصدريةُ التحميلات ══\n");

// ═══ ① القيدُ البنيوي ═══
head('① قيدٌ بنيويٌّ لا فحصٌ تطبيقي');
$e = tryInsert($conn, "INSERT INTO fin_dues (company_id,party_type,party_ref,due_type,direction,
        amount,currency,period_ref) VALUES ({$CO},'supplier',1,'fuel','debit',99,'SDG','{$MARK}-1')");
check($e !== '' && stripos($e, 'ck_dues_debit_source') !== false,
    'خصمٌ بلا مصدرٍ ترفضه **القاعدة**: ' . mb_substr($e, 0, 70));
$n = (int) $conn->query("SELECT COUNT(*) c FROM fin_dues WHERE period_ref='{$MARK}-1'")->fetch_assoc()['c'];
check($n === 0, "ولا صفَّ كُتب: {$n}");

$e2 = tryInsert($conn, "INSERT INTO fin_dues (company_id,party_type,party_ref,due_type,direction,
        amount,currency,period_ref,source_doc_type,source_doc_id)
        VALUES ({$CO},'supplier',1,'parts','debit',99,'SDG','{$MARK}-2','proc_issue',7)");
check($e2 === '', 'وبمصدره يمرّ');

// ═══ ② الاستحقاقُ حرٌّ منه ═══
head('② الاستحقاقُ (credit) لا يُطالَب بمستند');
$e3 = tryInsert($conn, "INSERT INTO fin_dues (company_id,party_type,party_ref,due_type,direction,
        amount,currency,period_ref) VALUES ({$CO},'supplier',1,'hours','credit',99,'SDG','{$MARK}-3')");
check($e3 === '', 'يمرّ بلا مصدر — مصدرُه حدثُ المروحة');

// ═══ ③ الردمُ دقيق ═══
head('③ الردمُ — دقيقٌ لا تخميني، ولا صفَّ مُسّ');
$pen = $conn->query("SELECT d.id, d.source_doc_type, d.source_doc_id, d.event_id, a.id AS aid
                       FROM fin_dues d
                       LEFT JOIN contract_penalty_assessments a ON a.event_id = d.event_id
                      WHERE d.due_type='penalty' AND d.direction='debit'")->fetch_all(MYSQLI_ASSOC);
check(count($pen) === 2, 'صفّا الجزاء حاضران: ' . count($pen));
$okPen = true;
foreach ($pen as $p) {
    if ($p['source_doc_type'] !== 'penalty_assessment'
        || intval($p['source_doc_id']) !== intval($p['aid'])) { $okPen = false; }
}
check($okPen, 'وكلٌّ منسوبٌ لاحتسابه بمطابقة الحدث (35/36) لا بتخمين المبلغ');

$leg = $conn->query("SELECT id, amount, source_doc_type, source_doc_id, is_deleted
                       FROM fin_dues WHERE due_type='fuel' AND direction='debit'
                         AND amount=120000.00")->fetch_assoc();
check($leg !== null, 'والصفُّ التاريخيُّ (وقود 120,000) **قائمٌ لم يُمسح**');
check($leg && $leg['source_doc_type'] === 'legacy_no_ref' && $leg['source_doc_id'] === null,
    'ووُسم «موروثٌ بلا مرجع» بلا رقمِ مستندٍ مخترَع');
check($leg && (int) $leg['is_deleted'] === 0, 'ولم يُخفَ (is_deleted=0)');
check((float) $leg['amount'] == 120000.00, 'ومبلغُه كما هو: ' . $leg['amount']);

$nulls = (int) $conn->query("SELECT COUNT(*) c FROM fin_dues
                              WHERE direction='debit' AND source_doc_type IS NULL")->fetch_assoc()['c'];
check($nulls === 0, "وصفرُ خصمٍ بلا وسمٍ في القاعدة كلِّها: {$nulls}");

// ═══ ④ الفجوةُ المعلَنة ═══
head('④ `pending_source` — فجوةٌ معلَنةٌ لا بابٌ خلفي');
$e4 = tryInsert($conn, "INSERT INTO fin_dues (company_id,party_type,party_ref,due_type,direction,
        amount,currency,period_ref,source_doc_type)
        VALUES ({$CO},'employee',3,'advance','debit',50,'SDG','{$MARK}-4','pending_source')");
check($e4 === '', 'سلفةُ موظفٍ تُقبل بـ`pending_source` — فلا تُمنع الموارد البشرية من تسجيل خصمٍ وقع');
$row = $conn->query("SELECT source_doc_type, source_doc_id FROM fin_dues
                      WHERE period_ref='{$MARK}-4'")->fetch_assoc();
check($row && $row['source_doc_type'] === 'pending_source' && $row['source_doc_id'] === null,
    'ومعلَّمةٌ بها بلا رقمٍ مخترَع');
// والحارسُ التطبيقيُّ يمنع استعمالَها لما له مستند — يُفحص في الشاشة
$screen = file_get_contents(dirname(__DIR__) . '/Finance/dues_fin.php');
check(mb_strpos($screen, '$PENDING_OK') !== false
      && mb_strpos($screen, "'advance', 'deduction'") !== false,
    'والشاشةُ تقصرها على السلف والخصومات');
check(mb_strpos($screen, 'له مستندٌ مبنيٌّ فاختره') !== false,
    'وترفضها لما له مستندٌ مبنيّ');
check(mb_strpos($screen, 'فتُعلَن الفجوةُ ولا تُخبَّأ') !== false,
    'وتشرح للمستخدم لماذا — لا رمزٌ صامت');

// ═══ ⑤ الكتّابُ يمرّرون مصدرَهم ═══
head('⑤ الكتّابُ يمرّرون مصدرَهم');
$pens = file_get_contents(dirname(__DIR__) . '/app/Services/Contract/PenaltyService.php');
check(mb_strpos($pens, "'source_doc_type' => 'penalty_assessment'") !== false,
    '`PenaltyService` يكتب مصدرَه');
$sets = file_get_contents(dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php');
check(mb_strpos($sets, "'source_doc_type' => 'settlement'") !== false,
    'و`SettlementService` كذلك');
check(mb_strpos($screen, "'source_doc_type' =>") !== false, 'والشاشةُ اليدوية كذلك');

// ═══ ⑥ الرقمُ ينقر إلى مستنده ═══
head('⑥ كلُّ رقمٍ في التسوية ينقر إلى مستنده');
check(mb_strpos($sets, 'd.source_doc_type, d.source_doc_id') !== false,
    'التسويةُ تقرأ المصدرَ مع البند');
check(mb_strpos($sets, 'private static function sourceLabel') !== false, 'ولها وسمٌ يصيّره');
foreach (array('سند صرف' => 'proc_issue', 'أمر صيانة' => 'mnt_order',
               'أمر نقل' => 'transfer_order', 'احتساب جزاء' => 'penalty_assessment') as $ar => $en) {
    check(mb_strpos($sets, "'{$en}'") !== false && mb_strpos($sets, $ar) !== false,
        "  ‹{$en}› بلغة المهمة: «{$ar}»");
}
check(mb_strpos($sets, 'موروثٌ بلا مرجع') !== false && mb_strpos($sets, 'بلا مصدرٍ مستنديٍّ بعد') !== false,
    'والفجوتان تُقالان باسمهما — صمتُهما أخطرُ من إعلانهما');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
