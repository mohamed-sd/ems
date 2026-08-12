<?php
/**
 * tests/maintenance_cost_publish_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حارس «أثرُ تكلفة الصيانة يُنشر من منبعه» (UX-04 §8.2 · FES §7 و§8).
 *
 * كان الأثرُ لا يبلغ الدفترَ إلا بزرِّ سحبٍ يضغطه موظفُ المالية — فصار الإقفالُ
 * نفسُه ينشره. والحرّاسُ الأربعة:
 *   ① الإقفالُ ينشر مرةً واحدة بمفتاح (mnt:order:{id}) وبأبعاده (معدة/مشروع)
 *   ② التكرارُ لا يضاعف: إقفالٌ ثانٍ أو زرُّ الاستيراد بعده → duplicate بلا صفٍّ جديد
 *   ③ أمرٌ بلا تكلفةٍ لا يُنشر له حدثٌ صفريّ (قاعدة عدم التلفيق)
 *   ④ تعريفُ الحدث واحدٌ: شاشةُ الاستيراد تستدعي ناشرَ الصيانة لا نسخةً ثانية
 *
 * يبذر أمرًا في نافذةٍ معزولة ويكنسه في النهاية — لا يمسّ بياناتٍ قائمة.
 * التشغيل: php tests/maintenance_cost_publish_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/Maintenance/mnt_helpers.php';

use App\Core\TenantContext;
use App\Core\TenantDb;

while (ob_get_level() > 0) { ob_end_clean(); }   // config.php يفتح مخزنَ CSRF

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$CO = 4; $ACTOR = 1;
$MARK = 'MNTCOST_' . getmypid();
$ctx  = TenantContext::forSystem($CO, $ACTOR, 'maintenance cost publish test', true);
$gate = new TenantDb($conn, $ctx);
$GLOBALS['__ems_tenant_db_override'] = $gate;   // لا يُستعمل — البوابة تُبنى من الجلسة
/* ══ **والفاحصُ كان يعترف بذلك ثم لا يفعل شيئًا حياله.**
     `mnt_publish_order_cost` تنادي `ems_tenant_db()` داخلَها، وهي تُبنى من
     **`TenantContext::fromSession()`** وتُخزَّن ساكنًا (config.php:537-549).
     فبلا جلسةٍ تفشل البوابةُ **بحقّ**: `TenantGate: no tenant in context
     (fail-closed)` — وترجع الدالةُ `'failed'` فتسقط ثمانيةُ فحوصٍ تقيس النشرَ
     والعطالةَ، **والحارسُ سليمٌ والبيئةُ ناقصة**. (مقيسٌ من سجلِّ الأخطاء:
     ثلاثةُ أسطرٍ «no tenant in context» لأوامرَ 207 و208 و99999999.)
   ⇒ تُبذر جلسةٌ كما تفعل سائرُ الفواحصِ التي تمرُّ ببوابةِ المستأجر — **قبلَ**
     أولِ نداء، لأن البوابةَ تُخزَّن ساكنًا فأولُ بناءٍ يحكم الجولةَ كلَّها. */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$_SESSION['user'] = array('id' => $ACTOR, 'role' => '13', 'company_id' => $CO,
                          'name' => 'maintenance cost publish test');

$scalar = function ($sql, $params = array()) use ($conn) {
    $st = $conn->prepare($sql);
    if (!$st) { return 0; }
    if ($params) { $st->bind_param(str_repeat('s', count($params)), ...$params); }
    $st->execute();
    $v = $st->get_result()->fetch_row();
    $st->close();
    return $v ? $v[0] : 0;
};

// ── بذرُ أمرين: أحدهما بتكلفة والآخر صفريّ ────────────────────────────────
$mk = function ($code, $cost) use ($conn, $CO, $ACTOR) {
    $st = $conn->prepare(
        "INSERT INTO mnt_order (company_id, code, equipment_id, project_id, maint_type, priority,
             state, external_cost, total_cost, created_by, created_at)
         VALUES (?, ?, 9, 4, 'علاجية', 'عادية', 'تنفيذ', ?, ?, ?, NOW())");
    $st->bind_param('isddi', $CO, $code, $cost, $cost, $ACTOR);
    $st->execute();
    $id = $conn->insert_id;
    $st->close();
    return $id;
};
$paidCode = 'TST-' . $MARK . '-A';
$zeroCode = 'TST-' . $MARK . '-B';
$paidId = $mk($paidCode, 137.50);
$zeroId = $mk($zeroCode, 0.00);

$cleanup = function () use ($conn, $paidId, $zeroId) {
    foreach (array($paidId, $zeroId) as $oid) {
        $conn->query("DELETE FROM fin_financial_events WHERE entity_type='mnt_order' AND entity_id=" . intval($oid));
        $conn->query("DELETE FROM ems_business_events WHERE entity_type='mnt_order' AND entity_id=" . intval($oid));
        $conn->query("DELETE FROM mnt_order WHERE id=" . intval($oid));
    }
};
register_shutdown_function($cleanup);

$ledger0 = (int) $scalar("SELECT COUNT(*) FROM fin_financial_events");
$roots0  = (int) $scalar("SELECT COUNT(*) FROM ems_business_events");

// ═══ ① النشر من الإقفال ═══════════════════════════════════════════════════
head('① الإقفال ينشر الأثر');

$r1 = mnt_publish_order_cost($conn, $paidId, $ACTOR, 'close');
check($r1 === 'published', "أمرٌ بتكلفة 137.50 → نُشر أثرُه ({$r1})");

$ev = $conn->query("SELECT * FROM fin_financial_events WHERE entity_type='mnt_order' AND entity_id=" . intval($paidId))->fetch_assoc();
check($ev !== null, 'صفُّ الدفتر موجود');
if ($ev) {
    check(abs((float)$ev['amount'] - 137.50) < 0.01, 'المبلغُ هو تكلفةُ الأمر بحرفها (137.50)');
    check((string)$ev['source_ref'] === $paidCode, 'المرجعُ كودُ الأمر — الخيطُ إلى مستنده لا ينقطع');
    check((string)$ev['source_module'] === 'maintenance', 'المصدرُ إدارةُ الصيانة');
    check(intval($ev['equipment_id']) === 9 && intval($ev['project_id']) === 4,
        'أبعادُه محفوظة: المعدة والمشروع (فتصل تكلفةُ ساعة المعدة وربحيةُ المشروع)');
    check(!empty($ev['root_event_id']), 'للصفِّ جذرٌ في سجل الحقائق (لا صفَّ يتيم)');
}
$root = $conn->query("SELECT * FROM ems_business_events WHERE entity_type='mnt_order' AND entity_id=" . intval($paidId))->fetch_assoc();
check($root !== null && (string)$root['idempotency_key'] === 'mnt:order:' . $paidId,
    'مفتاحُ العطالة هو مفتاحُ الاستيراد نفسُه (mnt:order:{id})');

// ═══ ② لا ازدواج ══════════════════════════════════════════════════════════
head('② التكرار لا يضاعف المبلغ');

$ledger1 = (int) $scalar("SELECT COUNT(*) FROM fin_financial_events");
$r2 = mnt_publish_order_cost($conn, $paidId, $ACTOR, 'close');
check($r2 === 'duplicate', "إقفالٌ ثانٍ للأمر نفسه → duplicate ({$r2})");
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $ledger1, 'صفر صفِّ دفترٍ جديد');

$r3 = mnt_publish_order_cost($conn, $paidId, $ACTOR, 'import_events_fin');
check($r3 === 'duplicate', 'زرُّ الاستيراد بعد النشر الآلي → duplicate لا صفٌّ ثانٍ');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $ledger1, 'وصفر صفٍّ جديد أيضًا');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='mnt_order' AND entity_id=?",
    array((string)$paidId)) === 1, 'صفٌّ واحدٌ للأمر في الدفتر بعد ثلاث محاولات');

// ═══ ③ الصفريُّ لا يُنشر ═══════════════════════════════════════════════════
head('③ أمرٌ بلا تكلفة');

$r4 = mnt_publish_order_cost($conn, $zeroId, $ACTOR, 'close');
check($r4 === 'skipped', "أمرٌ بتكلفة صفر → لا يُنشر ({$r4})");
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='mnt_order' AND entity_id=?",
    array((string)$zeroId)) === 0, 'صفر صفِّ دفترٍ للأمر الصفري (لا حدثَ ملفَّق)');

check(mnt_publish_order_cost($conn, 0, $ACTOR, 'close') === 'skipped', 'معرّفٌ غيرُ صالحٍ → skipped بلا رمي');
check(mnt_publish_order_cost($conn, 99999999, $ACTOR, 'close') === 'skipped', 'أمرٌ غيرُ موجودٍ → skipped بلا رمي');

// ═══ ④ تعريفٌ واحدٌ للحدث ═══════════════════════════════════════════════════
head('④ الوحدة المالكة تعرّف حدثها');

$impSrc = file_get_contents(dirname(__DIR__) . '/Finance/import_events_fin.php');
check(strpos($impSrc, 'mnt_publish_order_cost') !== false,
    'شاشةُ الاستيراد تستدعي ناشرَ الصيانة (لا نسخةً ثانيةً من العقد)');
check(preg_match("/'event_key'\s*=>\s*'expense\.maintenance\.recorded'/u", $impSrc) === 0,
    'ولم يبقَ تعريفٌ ثانٍ لمفتاح حدث الصيانة في شاشة الاستيراد');

$ordSrc = file_get_contents(dirname(__DIR__) . '/Maintenance/orders.php');
check(preg_match('/\$closing_now\s*\)\s*\{\s*\n\s*mnt_publish_order_cost/u', $ordSrc) === 1
   || strpos($ordSrc, 'mnt_publish_order_cost') !== false,
    'شاشةُ الأوامر تنشر لحظةَ الإقفال');
check(strpos($ordSrc, 'mnt_recalc_order_totals') < strpos($ordSrc, 'mnt_publish_order_cost'),
    'والنشرُ بعد إعادة احتساب المجاميع — فالمبلغُ نهائيٌّ لا وسيط');

// النظافة: لا صفوفَ زائدةٌ خارج ما بذرناه
$cleanup();
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $ledger0,
    'بعد الكنس: الدفترُ كما كان قبل الاختبار');
check((int) $scalar("SELECT COUNT(*) FROM ems_business_events") === $roots0,
    'وسجلُّ الحقائق كما كان');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
