<?php
/**
 * tools/fix_inj0027_tests.php — INJ-0027: أيمنع الحارسُ المنشئَ ويُمرّر غيرَه؟
 * ═══════════════════════════════════════════════════════════════════════════
 * الحكم: «منشئُ الإشعار لا يستطيع إجازتَه؛ ومستخدمٌ آخرُ بصلاحية المالية يجيزه
 *         **فتتحرك الذمّةُ مرةً واحدة**.»
 *
 * ◆ الحكمُ شرطان لا شرط: منعٌ **و** مرور. وفاحصٌ يقيس الأولَ وحدَه يُجيز حارسًا
 *   يمنع الجميع — وهو عطلٌ يُقرأ أمانًا. فيُقاس الاتجاهان معًا، ويُقاس أثرُ
 *   الذمّةِ **رقمًا قبلَ وبعد** لا نجاحًا مُعلَنًا.
 *
 * ◆ والإجازةُ فعلٌ يحرّك مالًا، فالعطالةُ جزءٌ من صحته: إجازتان تُنتجان حركةً
 *   واحدة. تُقاس بإعادةِ النداء لا بقراءةِ الشيفرة.
 *
 * ◆ ما يُصنَع هنا يُمحى في `register_shutdown_function` — بذرةُ النظامِ لا تُمَسّ.
 *
 * التشغيل: php tools/fix_inj0027_tests.php
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }
function one($sql) { $r = $GLOBALS['conn']->query($sql); return $r ? $r->fetch_row()[0] : null; }

echo "══════════════════════════════════════════════════════════════════════\n";
echo " INJ-0027 — منشئُ الإشعار لا يجيزه · " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n";

/* ══ البذرةُ المِسبارية ═══════════════════════════════════════════════════
   ثلاثةُ مستخدمين: منشئٌ · رافعٌ · مُجيزٌ ثالث. والحكمُ يميز بينهم. */
$U_CREATOR = 5; $U_SUBMITTER = 6; $U_APPROVER = 7;
$CO = 4;

$claim = $conn->query("SELECT id, claim_no, company_id, client_id, contract_id, project_id, receivable_id
                         FROM claims WHERE state='invoiced' AND company_id={$CO} LIMIT 1");
$claim = $claim ? $claim->fetch_assoc() : null;
if (!$claim) { exit("لا مستخلصَ مفوترًا للجسّ — يتعذّر الاختبار\n"); }
$CLAIM_ID   = (int) $claim['id'];
$ORIG_RECV  = ($claim['receivable_id'] !== null) ? (int) $claim['receivable_id'] : null;

/* ذمّةٌ مِسبارية — لأن لا مستخلصَ في البذرةِ مربوطٌ بذمّة.
   ◆ **درسٌ مدفوعُ الثمن**: بعد `INJ-0036` لا تُدرَج ذمّةٌ بلا `source_doc_id`
     (القيد `chk_recv_source_doc`). فسقطت هذه البذرةُ صامتةً (`insert_id = 0`)
     وسقط معها ثلاثةُ فحوصٍ — **وشاهدُ INJ-0027 توقّف عن العمل بقيدٍ أضفتُه
     أنا لاحقًا**. أصلحتُ ستَّ بذورِ اختبارٍ يومَها ونسيتُ بذرتي.
     ودرسُه: **إغلاقُ بندٍ لا يحرس شاهدَه من بندٍ تالٍ — البوابةُ وحدَها تفعل.** */
require_once dirname(__DIR__) . '/tests/_source_doc_seed.php';
$TI = seed_source_invoice($conn, $CO, 'PROBE-INJ27-RECV', (int) $claim['client_id'],
                          1000.00, 'SDG', $CLAIM_ID);
$conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
                source_doc_id, project_id, amount, currency, collected, due_date, state, is_deleted, created_by)
              VALUES ({$CO}, " . (int) $claim['client_id'] . ", 'invoice', 'PROBE-INJ27-RECV', {$TI},
                      " . ((int) $claim['project_id'] ?: 'NULL') . ", 1000.00, 'SDG', 0, CURDATE(), 'open', 0, 0)");
$RECV_ID = (int) $conn->insert_id;
if ($RECV_ID <= 0) { echo '  ✘ تعذّر بذرُ الذمّة: ' . $conn->error . "\n"; }
$conn->query("UPDATE claims SET receivable_id={$RECV_ID} WHERE id={$CLAIM_ID}");

register_shutdown_function(static function () use ($conn, $CLAIM_ID, $RECV_ID, $ORIG_RECV) {
    $conn->query("DELETE FROM fin_event_links WHERE event_id IN
                    (SELECT id FROM ems_business_events WHERE idempotency_key LIKE 'cdnote:%'
                       AND source_ref LIKE 'PROBE-INJ27%')");
    $conn->query("DELETE FROM ems_business_events WHERE source_ref LIKE 'PROBE-INJ27%'");
    $conn->query("UPDATE claims SET receivable_id = "
        . ($ORIG_RECV !== null ? (int) $ORIG_RECV : 'NULL') . " WHERE id={$CLAIM_ID}");
    $conn->query("DELETE FROM credit_debit_notes WHERE note_no LIKE 'PROBE-INJ27%'");
    $conn->query("DELETE FROM fin_receivables WHERE id={$RECV_ID}");
});
check($RECV_ID > 0, "ذمّةٌ مِسباريةٌ #{$RECV_ID} بمبلغ 1000.00 مربوطةٌ بالمستخلص #{$CLAIM_ID}");

/* البوابةُ تُبنى بمُهيِّئها العام — سياقُ الجلسةِ لا معاملاتٌ خام */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$_SESSION['user'] = array('id' => $U_APPROVER, 'role' => '19', 'company_id' => $CO, 'name' => 'فاحصُ الإشعارات');
require_once $ROOT . '/app/Core/TenantDb.php';
require_once $ROOT . '/Contracts/note_helpers.php';
$gate = ems_tenant_db();

/* إشعارٌ مدينٌ بـ150 — أنشأه 5 ورفعه 6 */
$conn->query("INSERT INTO credit_debit_notes (company_id, note_no, note_kind, claim_id, currency,
                amount, reason, doc_ref, state, prepared_by, submitted_by, submitted_at,
                created_by, invoice_no)
              VALUES ({$CO}, 'PROBE-INJ27-1', 'debit', {$CLAIM_ID}, 'SDG', 150.00,
                      'جسُّ INJ-0027', 'DOC-INJ27', 'review', {$U_CREATOR}, {$U_SUBMITTER}, NOW(),
                      {$U_CREATOR}, 'INV-PROBE')");
$NOTE = (int) $conn->insert_id;
check($NOTE > 0, "إشعارٌ مدينٌ #{$NOTE} بـ150.00 — أنشأه {$U_CREATOR} ورفعه {$U_SUBMITTER}"
    . ($NOTE ? '' : ' — ' . mb_substr($conn->error, 0, 90)));

$before = (float) one("SELECT amount FROM fin_receivables WHERE id={$RECV_ID}");

/* ══ ① المنعُ: المنشئُ لا يجيز ══════════════════════════════════════════ */
head('① منشئُ الإشعار — الاتجاهُ المانع');
$r = cdnote_approve($conn, $gate, $NOTE, $U_CREATOR);
check($r['code'] === 403, 'رُدَّ 403 — ' . mb_substr((string) $r['reason'], 0, 60));
check((string) one("SELECT state FROM credit_debit_notes WHERE id={$NOTE}") === 'review',
    'والحالةُ بقيت `review` — المنعُ منعٌ لا رسالةٌ فوق فعلٍ وقع');
check(abs((float) one("SELECT amount FROM fin_receivables WHERE id={$RECV_ID}") - $before) < 0.005,
    'والذمّةُ لم تتحرك — ' . number_format($before, 2));

/* ══ ② اليدُ الثانية: الرافعُ كذلك ══════════════════════════════════════ */
head('② رافعُ الإشعارِ للمالية — يدٌ أعدّت فلا تُجيز');
$r = cdnote_approve($conn, $gate, $NOTE, $U_SUBMITTER);
check($r['code'] === 403, 'رُدَّ 403 — ' . mb_substr((string) $r['reason'], 0, 60));
check(abs((float) one("SELECT amount FROM fin_receivables WHERE id={$RECV_ID}") - $before) < 0.005,
    'والذمّةُ ساكنة');

/* ══ ③ المرور: يدٌ ثالثةٌ تُجيز والذمّةُ تتحرك بمقدارِ الإشعارِ لا غير ═══ */
head('③ يدٌ ثالثة — الاتجاهُ المُمرِّر (وبه يُعلم أن الحارسَ ليس مانعًا للكل)');
$r = cdnote_approve($conn, $gate, $NOTE, $U_APPROVER);
check(!empty($r['ok']) && $r['code'] === 200,
    'أُجيز 200' . (empty($r['ok']) ? ' — لكنه رُدَّ: ' . $r['code'] . ' ' . mb_substr((string) $r['reason'], 0, 70) : ''));
$after1 = (float) one("SELECT amount FROM fin_receivables WHERE id={$RECV_ID}");
check(abs($after1 - ($before + 150.00)) < 0.005,
    'والذمّةُ تحركت **بمقدارِ الإشعارِ بالضبط**: ' . number_format($before, 2) . ' ← ' . number_format($after1, 2));
check((string) one("SELECT state FROM credit_debit_notes WHERE id={$NOTE}") === 'approved',
    'والحالةُ صارت `approved`');
check((int) one("SELECT approved_by FROM credit_debit_notes WHERE id={$NOTE}") === $U_APPROVER,
    'واليدُ المُجيزةُ مدوَّنةٌ في الصف — فالفصلُ قابلٌ للمراجعةِ بعد حين');
check((int) one("SELECT COUNT(*) FROM ems_business_events WHERE source_ref='PROBE-INJ27-1'") === 1,
    'وحقيقةٌ واحدةٌ في الجذر المحايد');

/* ══ ④ العطالة: إجازتان حركةٌ واحدة ═════════════════════════════════════ */
head('④ العطالة — «مرةً واحدة» تُقاس بإعادةِ النداءِ لا بالنية');
$r2 = cdnote_approve($conn, $gate, $NOTE, $U_APPROVER);
check(!empty($r2['ok']) && !empty($r2['existing']), 'النداءُ الثاني رجع «معتمدٌ سلفًا»');
$after2 = (float) one("SELECT amount FROM fin_receivables WHERE id={$RECV_ID}");
check(abs($after2 - $after1) < 0.005,
    'والذمّةُ **لم تتحرك ثانيةً**: ' . number_format($after2, 2) . ' — وهذا معنى «مرةً واحدة»');
check((int) one("SELECT COUNT(*) FROM ems_business_events WHERE source_ref='PROBE-INJ27-1'") === 1,
    'ولا حقيقةَ ثانيةٌ في الجذر');

/* ══ ⑤ الإشعارُ الدائنُ يتحرك عكسًا — الاتجاهُ لا المقدارُ وحدَه ════════ */
head('⑤ الدائنُ ينقص الذمّةَ — والاتجاهُ جزءٌ من الصواب');
$conn->query("INSERT INTO credit_debit_notes (company_id, note_no, note_kind, claim_id, currency,
                amount, reason, doc_ref, state, prepared_by, submitted_by, submitted_at,
                created_by, invoice_no)
              VALUES ({$CO}, 'PROBE-INJ27-2', 'credit', {$CLAIM_ID}, 'SDG', 40.00,
                      'جسُّ INJ-0027 دائن', 'DOC-INJ27B', 'review', {$U_CREATOR}, {$U_CREATOR}, NOW(),
                      {$U_CREATOR}, 'INV-PROBE')");
$NOTE2 = (int) $conn->insert_id;
$r3 = cdnote_approve($conn, $gate, $NOTE2, $U_APPROVER);
$after3 = (float) one("SELECT amount FROM fin_receivables WHERE id={$RECV_ID}");
check(!empty($r3['ok']) && abs($after3 - ($after2 - 40.00)) < 0.005,
    'نقصت 40.00: ' . number_format($after2, 2) . ' ← ' . number_format($after3, 2));

/* ══ ⑥ لا بابَ خلفيّ: صفرُ مستخدمٍ ليس «يدًا أخرى» ═════════════════════ */
head('⑥ المجهولُ ليس يدًا ثانية');
$conn->query("INSERT INTO credit_debit_notes (company_id, note_no, note_kind, claim_id, currency,
                amount, reason, doc_ref, state, prepared_by, submitted_by, submitted_at,
                created_by, invoice_no)
              VALUES ({$CO}, 'PROBE-INJ27-3', 'debit', {$CLAIM_ID}, 'SDG', 10.00,
                      'جسُّ INJ-0027 مجهول', 'DOC-INJ27C', 'review', 0, 0, NOW(), 0, 'INV-PROBE')");
$NOTE3 = (int) $conn->insert_id;
$before3 = (float) one("SELECT amount FROM fin_receivables WHERE id={$RECV_ID}");
$r4 = cdnote_approve($conn, $gate, $NOTE3, 0);
$after4 = (float) one("SELECT amount FROM fin_receivables WHERE id={$RECV_ID}");
check($r4['code'] === 403 || abs($after4 - $before3) < 0.005,
    'إشعارٌ بلا يدٍ معلومةٍ لا يمرُّ بيدٍ مجهولة — الرمز ' . $r4['code']
    . ' · الذمّة ' . number_format($after4, 2));

/* ══ ⑦ الحارسُ في الخدمةِ لا في الشاشةِ وحدَها ══════════════════════════ */
head('⑦ موضعُ الحارس');
$src = (string) file_get_contents($ROOT . '/Contracts/note_helpers.php');
check(strpos($src, 'ems_no_self_approval') !== false,
    'النداءُ داخل `cdnote_approve` — فكلُّ نداءٍ للدالةِ يمرُّ به، لا مَن دخل من الشاشةِ فقط');
$scr = @file_get_contents($ROOT . '/Contracts/cdnote_action.php');
check($scr === false || strpos((string) $scr, 'cdnote_approve') !== false,
    'وشاشةُ الفعلِ تنادي الدالةَ ولا تكرّر منطقَ الإجازة');

echo "\n" . str_repeat('═', 70) . "\n";
printf("ناجحٌ: %d · فاشلٌ: %d\n", $PASS, $FAIL);
echo "◆ الحكمُ مُقاسٌ في اتجاهيه: المنشئُ يُردّ، والثالثُ يمرّ، والذمّةُ تتحرك مرةً.\n";
echo str_repeat('═', 70) . "\n";
exit($FAIL === 0 ? 0 : 1);
