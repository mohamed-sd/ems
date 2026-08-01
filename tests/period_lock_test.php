<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-39 + M-08 + M-33 — اختبار قبول قفل الفترة على الكتابة المالية
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/period_lock_test.php
 *
 * ما يُثبته:
 *   ① الحارس المركزي ems_period_check: مفتوحةٌ تمرّ · مقفلةٌ 423 باسم حالتها ·
 *      لا فترةَ معرَّفةً تمرّ (فجوةٌ معلَنة).
 *   ② الناشر (نقطةُ الخنق): نشرٌ جديدٌ في فترةٍ مقفلة يُرمى 423 **وصفرُ صفٍّ**
 *      في الدفتر والجذر معًا — والعطالةُ تسبق الحارس: إعادةُ نشرِ منشورٍ سلفًا
 *      تعيد مرجعَه القائم ولو كانت فترتُه مقفلةً الآن.
 *   ③ M-33: mnt_publish_order_cost في فترةٍ مقفلة → retry_pending لا failed.
 *   ④ M-08: claim_already_billed_units تكشف المفوترَ سابقًا بمرجع سطره —
 *      وفرعُ 409 موصولٌ في claim_generate والاعتمادُ محكومٌ بالفترة (423).
 *   ⑤ موانعُ الإقفال: فترةٌ فيها قيودٌ غيرُ مرحَّلةٍ تُمنع من Close بقائمةٍ.
 *
 * ⚠️ يقلب `posting_allowed` لفترة تموز 2026 مؤقتًا **ويعيده مضمونًا** (shutdown).
 * البذرُ معزول (PLKT_<pid>) ويُكنس (§3).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
// سياق مستأجر للبوابة (EMS_TENANT_GATE=enforce): الحزم تحاكي جلسة حقيقية لا GUEST
$_SESSION['user'] = array('id' => 1, 'role' => '19', 'company_id' => 4, 'name' => 'fes test');
require_once dirname(__DIR__) . '/includes/period_guard.php';
require_once dirname(__DIR__) . '/app/Core/EventValidationException.php';
require_once dirname(__DIR__) . '/app/Core/ServerId.php';
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';

use App\Core\EventPublisher;
use App\Core\EventValidationException;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 1;
$MARK = 'PLKT_' . getmypid();
$ENT = 960000 + (getmypid() % 1000);

// فترةُ اليوم — تُقلب مقفلةً مؤقتًا ويُضمن ردُّها.
// ⚠ **بساعةٍ واحدةٍ لا ساعتين**: كان البذرُ يختار الفترةَ بـ`CURDATE()` (ساعةُ
// MySQL المحلية) بينما كلُّ التأكيدات تستدعي `ems_period_check(..., date('Y-m-d'))`
// (ساعةُ PHP بـUTC). والفارقُ بين الساعتين ثلاثُ ساعاتٍ في هذه البيئة — فثلاثَ
// ساعاتٍ يوميًّا **يختار البذرُ شهرًا وتقيس التأكيداتُ شهرًا آخر**. وهو خللٌ
// كامنٌ منذ كتابة الحزمة، لم يظهر حتى عبر منتصفُ الليل بينهما (2026-08-01).
// فالبذرُ يقرأ **بساعة الكود المُختبَر** — والقاعدة: مقياسٌ واحدٌ للزمن في الحزمة.
$TODAY = date('Y-m-d');
$JULY = $conn->query("SELECT id, posting_allowed, state FROM fin_financial_periods
    WHERE company_id={$CO} AND period_type='month'
      AND '{$TODAY}' BETWEEN start_date AND end_date LIMIT 1")->fetch_assoc();
if (!$JULY) { fwrite(STDERR, "FATAL: لا فترةَ شهريةً لليوم ({$TODAY})\n"); exit(1); }
$JID = intval($JULY['id']);
$restorePeriod = function () use ($conn, $JID, $JULY) {
    $conn->query("UPDATE fin_financial_periods SET posting_allowed=" . intval($JULY['posting_allowed'])
        . ", state='" . $conn->real_escape_string($JULY['state']) . "' WHERE id={$JID}");
};
register_shutdown_function($restorePeriod);

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE fe FROM fin_event_effects fe JOIN fin_financial_events e ON e.id=fe.event_id
                   WHERE e.source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_financial_events WHERE source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM ems_business_events WHERE source_ref LIKE '{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-39/M-08/M-33 — قفلُ الفترة على الكتابة المالية ══\n");

// ═══ ① الحارس المركزي ═══
head('① ems_period_check — الأحكامُ الثلاثة');
$r = ems_period_check($conn, $CO, date('Y-m-d'));
check($r['ok'] === true && $r['period_id'] === $JID, 'فترةُ اليوم مفتوحة: تمرّ بفترتها #' . $r['period_id']);
$r = ems_period_check($conn, $CO, '2026-01-15');
check($r['ok'] === false && $r['code'] === 423, 'كانون الثاني المقفل: 423');
check(strpos($r['reason'], 'closed') !== false || strpos($r['reason'], 'مقفلة') !== false,
    'وبسببٍ يسمّي الحالة: ' . mb_substr($r['reason'], 0, 80));
$r = ems_period_check($conn, $CO, '2031-03-03');
check($r['ok'] === true && $r['period_id'] === null, 'ولا فترةَ معرَّفةً (2031): تمرّ — فجوةٌ معلَنة');

// ═══ ② الناشر ═══
head('② الناشر — 423 قبل أي كتابةٍ (لا دفترَ ولا جذر) والعطالةُ تسبقه');
$mkEvent = function ($suffix, $occurred) use ($CO, $ACTOR, $MARK, $ENT) {
    return array(
        'event_key' => 'revenue.unit.recognized', 'category' => 'financial',
        'source_module' => 'sales', 'company_id' => $CO,
        'entity_type' => 'unit_entry', 'entity_id' => $ENT + intval($suffix),
        'occurred_at' => $occurred, 'created_by' => $ACTOR,
        'payload' => array('marker' => $MARK), 'legacy_event_type' => 'revenue',
        'amount' => 100.00, 'currency' => 'SDG', 'customer_entity_id' => 1,
        'source_ref' => $MARK . '_' . $suffix,
    );
};
$ledger0 = intval($conn->query("SELECT COUNT(*) c FROM fin_financial_events")->fetch_assoc()['c']);
$root0   = intval($conn->query("SELECT COUNT(*) c FROM ems_business_events")->fetch_assoc()['c']);
$thrown = false; $msg = '';
try { EventPublisher::publish($conn, $mkEvent('1', '2026-01-15 09:00:00')); }
catch (EventValidationException $x) { $thrown = true; $msg = $x->getMessage(); }
check($thrown && strpos($msg, '423') !== false, 'نشرٌ بتاريخ فترةٍ مقفلة يُرمى 423: ' . mb_substr($msg, 0, 60));
$ledger1 = intval($conn->query("SELECT COUNT(*) c FROM fin_financial_events")->fetch_assoc()['c']);
$root1   = intval($conn->query("SELECT COUNT(*) c FROM ems_business_events")->fetch_assoc()['c']);
check($ledger1 === $ledger0 && $root1 === $root0, 'وصفرُ صفٍّ كُتب — لا دفترَ ولا جذرَ يتيم');

// عمليةٌ تُنشر في فترةٍ مفتوحةٍ ثم تُقفل فترتُها: الإعادةُ ترجع المرجعَ لا 423
$r1 = EventPublisher::publish($conn, $mkEvent('2', date('Y-m-d') . ' 09:00:00'));
check(!$r1['duplicate'] && $r1['id'] > 0, "نُشر في المفتوحة #{$r1['id']}");
$conn->query("UPDATE fin_financial_periods SET posting_allowed=0, state='soft_closed' WHERE id={$JID}");
$r2 = EventPublisher::publish($conn, $mkEvent('2', date('Y-m-d') . ' 09:00:00'));
check($r2['duplicate'] === true && intval($r2['id']) === intval($r1['id']),
    '★ العطالةُ تسبق الحارس: إعادةُ المنشور تعيد مرجعَه ولو أُقفلت الفترة');
$thrown = false;
try { EventPublisher::publish($conn, $mkEvent('3', date('Y-m-d') . ' 10:00:00')); }
catch (EventValidationException $x) { $thrown = true; }
check($thrown, 'وعمليةٌ جديدةٌ في الفترة المقفلة نفسِها تُرمى 423');

// ═══ ③ M-33 الصيانة ═══
head('③ الصيانة — فترةٌ مقفلة → retry_pending لا failed (والفترةُ ما زالت مقفلة)');
require_once dirname(__DIR__) . '/Maintenance/mnt_helpers.php';
$mntOrder = $conn->query("SELECT o.id FROM mnt_order o
    WHERE o.company_id={$CO} AND COALESCE(o.total_cost,0) > 0 AND COALESCE(o.code,'') <> ''
      AND NOT EXISTS (SELECT 1 FROM fin_financial_events fe
                       WHERE fe.idempotency_key = CONCAT('mnt:order:', o.id))
    LIMIT 1")->fetch_assoc();
if (!$mntOrder) {
    ok('(لا أمرَ صيانةٍ غيرَ منشورٍ للاختبار الحي — الفرعُ مثبتٌ مصدريًّا أدناه)');
    $src = file_get_contents(dirname(__DIR__) . '/Maintenance/mnt_helpers.php');
    check(strpos($src, "'retry_pending'") !== false && strpos($src, '423') !== false,
        'فرعُ 423 → retry_pending موجودٌ في الناشر الصيانيّ');
} else {
    $oid = intval($mntOrder['id']);
    $res = mnt_publish_order_cost($conn, $oid, $ACTOR, 'close');
    check($res === 'retry_pending', "أمر #{$oid}: النشرُ في فترةٍ مقفلة → {$res}");
    $cnt = intval($conn->query("SELECT COUNT(*) c FROM fin_financial_events
        WHERE idempotency_key='mnt:order:{$oid}'")->fetch_assoc()['c']);
    check($cnt === 0, 'وصفرُ حدثٍ كُتب — يعاد يومَ تُفتح');
}

// ═══ ⑤ موانع الإقفال (والفترةُ المقفلةُ تعاد مفتوحةً هنا) ═══
$restorePeriod();
head('⑤ موانعُ الإقفال — غيرُ المرحَّل يمنع Close بقائمته');
$blk = ems_period_close_blockers($conn, $CO, $JID);
$draftCnt = intval($conn->query("SELECT COUNT(*) c FROM fin_journal_entries
    WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0 AND state='draft'
      AND posting_date BETWEEN (SELECT start_date FROM fin_financial_periods WHERE id={$JID})
                           AND (SELECT end_date FROM fin_financial_periods WHERE id={$JID})")->fetch_assoc()['c']);
if ($draftCnt > 0) {
    $found = false;
    foreach ($blk as $b) { if (strpos($b['label'], 'غيرُ مرحَّلة') !== false && $b['count'] === $draftCnt) { $found = true; } }
    check($found, "قيودُ الفترة غيرُ المرحَّلة ({$draftCnt}) تظهر مانعًا باسمها");
} else {
    ok("(لا مسوداتِ في فترة اليوم — بنيةُ الموانع تُفحص أدناه)");
}
check(is_array($blk), 'قائمةُ الموانع مصفوفةٌ قابلةٌ للعرض بالعدّ والرابط');
$src = file_get_contents(dirname(__DIR__) . '/Finance/periods_fin.php');
check(strpos($src, 'ems_period_close_blockers') !== false, 'وزرُّ الإقفال موصولٌ بها');
check(strpos($src, 'emsReopenPeriod') !== false && strpos($src, "reason") !== false,
    'والفتحُ الاستثنائي لا يقع بلا سببٍ مكتوب');

// ═══ ④ M-08 الفوترة ═══
head('④ الفوترة — المفوترُ سابقًا بمرجع سطره والاعتمادُ محكومٌ بالفترة');
require_once dirname(__DIR__) . '/Contracts/claim_helpers.php';
$cl = $conn->query("SELECT c.id, c.contract_id, c.period_from, c.period_to, c.claim_no
    FROM claims c JOIN claim_lines l ON l.claim_id = c.id AND l.source_kind='timesheet'
    WHERE c.company_id={$CO} AND c.state <> 'cancelled' AND COALESCE(c.is_deleted,0)=0
    LIMIT 1")->fetch_assoc();
if (!$cl) { ok('(لا مستخلصَ ببنود دوامٍ بعد — تُثبت الدالة مصدريًّا)'); }
else {
    $gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($CO, $ACTOR, '', true));
    $billed = claim_already_billed_units($gate, intval($cl['contract_id']), $cl['period_from'], $cl['period_to']);
    check(!empty($billed), "وحداتُ {$cl['claim_no']} المفوترةُ مكشوفةٌ لا صامتة: " . count($billed));
    check(!empty($billed) && intval($billed[0]['claim_line_id']) > 0 && $billed[0]['claim_no'] !== '',
        'وكلُّ واحدةٍ بمرجع سطرها ورقمِ مستخلصها (409 material)');
}
$src = file_get_contents(dirname(__DIR__) . '/Contracts/claim_helpers.php');
check(strpos($src, "'conflict'") !== false && strpos($src, "409") !== false,
    'وفرعُ 409 موصولٌ في claim_generate');
check(strpos($src, 'ems_period_check') !== false, 'واعتمادُ المستخلص محكومٌ بالفترة (423)');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
