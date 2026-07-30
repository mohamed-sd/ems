<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-06 — اختبار قبول: فعلُ النزاع على بند المستخلص (ENT-03 §3-⑤ · §4)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/claim_dispute_test.php
 *
 * ما يُثبته:
 *   ① **بسببٍ ومستند**: 422 بأحدهما ناقصًا · و**`CHECK` يرفض** الكتابةَ المباشرة.
 *   ② **والبقيةُ تمضي**: البندُ المتنازَعُ عليه **يخرج من الصافي وحدَه**
 *      والمستخلصُ **لا يُجمَّد** — و**عدّادُ النزاع** يُقرأ (§4).
 *   ③ **الحسمُ قرارٌ يُسجَّل**: بلا سببٍ **422**، و`rejected` **يعيد البندَ
 *      محتسَبًا**، و`upheld` **يُسقطه نهائيًّا** — والصافي يتبع القرارَ لا الوسم.
 *   ④ **والعَلَمُ مرآةُ الحالة**: كتابةٌ تخالفها **يرفضها `CHECK`**.
 *   ⑤ **ولا نزاعَ على مفوتَر**: بعد صدور الفاتورة **423 «التصحيح بإشعار»**.
 *   ⑥ **ولا رفعَ مرتين**: نزاعٌ مفتوحٌ ⇒ 409 · وحسمٌ بلا نزاعٍ ⇒ 409.
 *
 * البذرُ معزول: عميلٌ ومستخلصٌ ببنديه في 2100 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'M06 dispute test');

require_once dirname(__DIR__) . '/app/Services/Revenue/ClaimDisputeService.php';
require_once dirname(__DIR__) . '/app/Services/Revenue/TaxInvoiceService.php';

use App\Services\Revenue\ClaimDisputeService as DIS;
use App\Services\Revenue\TaxInvoiceService as TIS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999901;
$MARK  = 'M06T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE t FROM tax_invoices t JOIN claims c ON c.id = t.claim_id
                   WHERE c.claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE l FROM claim_lines l JOIN claims c ON c.id = l.claim_id
                   WHERE c.claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM clients WHERE client_name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-06 — فعلُ النزاع على بند المستخلص ══\n");

head('البذر — مستخلصٌ ببندين (600 + 400)');

$conn->query("INSERT INTO clients (company_id, client_code, client_name, created_at)
              VALUES ({$CO}, 'C-{$MARK}', 'عميلُ {$MARK}', NOW())");
$CLI = intval($conn->insert_id);

$conn->query("INSERT INTO claims (company_id, claim_no, client_id, period_from, period_to,
              currency, gross_amount, retention_amount, net_amount, state, version, created_at)
              VALUES ({$CO}, '{$MARK}-CL', {$CLI}, '2100-01-01', '2100-01-31',
                      'USD', 1000, 0, 1000, 'draft', 1, NOW())");
$CLM = intval($conn->insert_id);

// `uq_claim_line_src` = (مستخلص × نوعُ الأصل × مرجعه) — **لكلِّ بندٍ أصلُه**،
// والقيدُ القائمُ يُحترم لا يُلتفّ عليه.
$src = 0;
$mkLine = function ($amount) use ($conn, $CO, $CLM, &$src) {
    $src++;
    $ok = $conn->query("INSERT INTO claim_lines (company_id, claim_id, qty, unit_price, amount,
                        source_kind, source_ref, dispute_flag, created_at)
                        VALUES ({$CO}, {$CLM}, 1, {$amount}, {$amount}, 'timesheet', {$src}, 0, NOW())");
    if (!$ok) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$L1 = $mkLine(600);
$L2 = $mkLine(400);
check($CLI > 0 && $CLM > 0 && $L1 > 0 && $L2 > 0, 'عميلٌ ومستخلصٌ وبندان');

require_once dirname(__DIR__) . '/Contracts/claim_helpers.php';
check(abs(claim_recalc($gate, $CLM) - 1000.0) < 0.005, 'والصافي 1000 قبل أي نزاع');

// ═══ ① بسببٍ ومستند ═══
head('① **بسببٍ ومستند** (§3-⑤)');

$r = DIS::raise($conn, $gate, $CO, $L1, array('reason' => 'كميةٌ محلَّ خلاف'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'رأيٌ لا نزاع') !== false,
      '★ اعتراضٌ بلا مستندٍ ⇒ **422** — «واعتراضٌ بلا بيّنةٍ رأيٌ لا نزاع»');
$r = DIS::raise($conn, $gate, $CO, $L1, array('doc_ref' => 'DOC-1'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'وبلا سببٍ ⇒ 422');

$leak = $conn->query("UPDATE claim_lines SET dispute_state='open', dispute_flag=1,
                      dispute_reason=NULL, dispute_doc_ref=NULL WHERE id={$L2}");
$st = $conn->query("SELECT dispute_state FROM claim_lines WHERE id={$L2}")->fetch_assoc();
check((string) $st['dispute_state'] === 'none',
      '★ ونزاعٌ مكتوبٌ مباشرةً بلا بيّنة **يرفضه CHECK** — بنيويًّا لا بفحصٍ يُنسى');

// ═══ ② والبقيةُ تمضي ═══
head('② **والبقيةُ تمضي** — ولا يُجمَّد المستخلصُ كلُّه');

$r = DIS::raise($conn, $gate, $CO, $L1,
    array('reason' => 'كميةُ يوم 12 محلَّ خلاف', 'doc_ref' => 'محضر 2100/1'), $ACTOR);
check($r['ok'] && $r['open_count'] === 1, 'رُفع النزاعُ على البند 600 (عدّادٌ 1)');

$net = claim_recalc($gate, $CLM);
check(abs($net - 400.0) < 0.005,
      '★★ والصافي **400** — البندُ المتنازَعُ عليه خرج **وحدَه** والبقيةُ مضت');
$c = $conn->query("SELECT state FROM claims WHERE id={$CLM}")->fetch_assoc();
check((string) $c['state'] === 'draft', 'والمستخلصُ **لم يتجمّد** — حالتُه كما كانت');

$r2 = DIS::raise($conn, $gate, $CO, $L1,
    array('reason' => 'ثانٍ', 'doc_ref' => 'DOC-2'), $ACTOR);
check(!$r2['ok'] && $r2['code'] === 409, '⑥ ورفعٌ ثانٍ على المتنازَع عليه ⇒ **409**');

// ═══ ③ الحسمُ قرارٌ يُسجَّل ═══
head('③ **الحسمُ قرارٌ يُسجَّل لا وسمٌ يُمحى**');

$r = DIS::resolve($conn, $gate, $CO, $L1, 'rejected', '', $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'لا يكون صامتًا') !== false,
      'حسمٌ بلا سببٍ ⇒ **422**');

$r = DIS::resolve($conn, $gate, $CO, $L1, 'rejected', 'المحضرُ يثبت الكمية — الاعتراضُ مردود', $ACTOR);
check($r['ok'] && abs($r['net'] - 1000.0) < 0.005 && $r['open_count'] === 0,
      '★★ و`rejected` **يعيد البندَ محتسَبًا**: الصافي عاد **1000** والعدّادُ صفر');
$l = $conn->query("SELECT dispute_state, resolution, dispute_flag, resolution_note
                     FROM claim_lines WHERE id={$L1}")->fetch_assoc();
check((string) $l['dispute_state'] === 'resolved' && (string) $l['resolution'] === 'rejected'
      && intval($l['dispute_flag']) === 0 && (string) $l['resolution_note'] !== '',
      'والقرارُ **محفوظٌ بسببه وحاسمه** — لا وسمٌ مُحي');

$r = DIS::resolve($conn, $gate, $CO, $L1, 'upheld', 'x', $ACTOR);
check(!$r['ok'] && $r['code'] === 409, '⑥ وحسمٌ ثانٍ بلا نزاعٍ مفتوح ⇒ **409**');

// ── و`upheld` يُسقط البند ──
$r = DIS::raise($conn, $gate, $CO, $L2,
    array('reason' => 'بندٌ لا يخصُّ الفترة', 'doc_ref' => 'خطاب 2100/2'), $ACTOR);
check($r['ok'], 'ونزاعٌ على البند 400');
$r = DIS::resolve($conn, $gate, $CO, $L2, 'upheld', 'صحَّ اعتراضُ العميل — البندُ يسقط', $ACTOR);
check($r['ok'] && abs($r['net'] - 600.0) < 0.005,
      '★★ و`upheld` **يُسقط البندَ نهائيًّا**: الصافي **600** — والقرارُ لا الوسمُ هو الحاكم');
$l = $conn->query("SELECT dispute_flag FROM claim_lines WHERE id={$L2}")->fetch_assoc();
check(intval($l['dispute_flag']) === 1, 'والعَلَمُ يبقى مرفوعًا — البندُ ساقطٌ بقرار');

// ═══ ④ العَلَمُ مرآةُ الحالة ═══
head('④ **والعَلَمُ مرآةُ الحالة** — لا رأيٌ ثانٍ');

$bad = $conn->query("UPDATE claim_lines SET dispute_flag = 1 WHERE id={$L1}");
$l = $conn->query("SELECT dispute_flag FROM claim_lines WHERE id={$L1}")->fetch_assoc();
check(intval($l['dispute_flag']) === 0,
      '★ رفعُ العَلَم على بندٍ **حُسم بالردّ** يرفضه CHECK — والحسابُ لا يُخدَع بوسم');

// ═══ ⑤ لا نزاعَ على مفوتَر ═══
head('⑤ **ولا نزاعَ على مفوتَر** — «التصحيح بإشعار» (§3-⑥)');

$conn->query("UPDATE claims SET state='approved' WHERE id={$CLM}");
$inv = TIS::issueForClaim($conn, $gate, $CO, $CLM, array(), $ACTOR);
check($inv['ok'], 'صدرت فاتورةُ المستخلص ' . $inv['serial_no']);

$r = DIS::raise($conn, $gate, $CO, $L1,
    array('reason' => 'بعد الفوترة', 'doc_ref' => 'DOC-9'), $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && mb_strpos($r['reason'], 'بإشعارٍ دائنٍ أو مدين') !== false,
      '★★ رفعُ نزاعٍ بعد الفوترة ⇒ **423** ونصُّه يسمّي طريقَ التصحيح');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
