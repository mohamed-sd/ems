<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-09 — اختبار قبول: شروطُ تعديل السعر (وقودٌ · تضخمٌ · صرف) — CON-02 §2-③/§6
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/price_adjustment_test.php
 *
 * ما يُثبته:
 *   ① حراسُ الشرط: محفِّزٌ خارج الثلاثة · مرجعٌ ≤ 0 · تمريرٌ خارج (0،100] ·
 *      سريانٌ إلزامي · بندٌ أجنبيٌّ عن العقد · مكررٌ 409.
 *   ② **قراءةُ مؤشرٍ بلا مرجعِ مستندٍ مرفوضة** (خدمةً وبنيةً معًا).
 *   ③ الحسابُ بأعمدة العقد: **دون العتبة لا تعديل** · التمريرُ يمرّر ·
 *      **السقفُ يقصّ** · وغيابُ القراءة `no_reading` **معلَنٌ لا مخترَع**.
 *   ④ **الملحقُ بسريانه**: كلُّ ملحقٍ مولَّدٍ يحمل `effective_from` — وهو ما
 *      تفتقده السبعةُ الموروثة (يُعلَن في تقرير المطابقة ولا يُخمَّن له تاريخ).
 *   ⑤ **العطالة**: إعادةُ المراجعة للدورة نفسِها لا تولّد ملحقًا ثانيًا.
 *   ⑥ **من أنشأ لا يعتمد** → 403 · وغيرُ المعتمَدة لا تحرّك سعرًا.
 *   ⑦ **لا رجعية عدديًّا**: واقعةُ ما قبل السريان بالسعر القديم وما بعدها
 *      بالجديد — في الاستدعاء نفسِه.
 *   ⑧ **صفرُ انحدار**: بلا شرطٍ ولا اعتمادٍ يعود `effectivePrice` الأساسَ حرفيًّا.
 *   ⑨ `fx` يقرأ `fin_fx_rates` — مصدرَ الحقيقة القائم لا نسخةً ثانية.
 *
 * البذرُ معزول: عقدٌ صوريٌّ M09T ببندٍ واحد ومؤشرٌ M09T_IDX وتواريخُ 2043.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/PriceAdjustmentService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\PriceAdjustmentService as PAS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $AUTHOR = 999909; $APPROVER = 999910;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $AUTHOR, '', true));

$teardown = function () use ($conn) {
    $ids = array();
    $r = $conn->query("SELECT id FROM contracts WHERE first_party LIKE 'M09T%'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM contract_price_revisions WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM contract_price_terms WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM contract_amendments WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM contractequipments WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM contracts WHERE id = {$cid}");
    }
    $conn->query("DELETE FROM contract_price_index_readings WHERE index_code LIKE 'M09T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-09 — شروطُ تعديل السعر (وقودٌ · تضخمٌ · صرف) ══\n");

// ═══ البذر ═══
head('البذر — عقدٌ صوريٌّ ببندِ ساعةٍ سعرُه 100');
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, first_party, contract_status,
              actual_start, actual_end) VALUES ({$CO}, '2043-01-01', 'M09T', 'قيد التنفيذ', '2043-01-01', '2043-12-31')");
$CID = intval($conn->insert_id);
$conn->query("INSERT INTO contractequipments (company_id, contract_id, equip_type, equip_unit, equip_price,
              equip_price_currency) VALUES ({$CO}, {$CID}, '1', 'ساعة', 100.00, 'دولار')");
$ITEM = intval($conn->insert_id);
check($CID > 0 && $ITEM > 0, "العقد #{$CID} وبندُه #{$ITEM} (سعرٌ أساسٌ 100)");

// ═══ ① حراسُ الشرط ═══
head('① حراسُ الشرط');
$r = PAS::saveTerm($conn, $gate, $CO, $CID, array(
    'trigger_kind' => 'gold', 'index_code' => 'X', 'base_index' => 1, 'valid_from' => '2043-01-01'), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], '§2-③') !== false,
      'محفِّزٌ خارج الثلاثة المسمّاة → 422 بنص المصدر');

$r = PAS::saveTerm($conn, $gate, $CO, $CID, array(
    'trigger_kind' => 'fuel', 'index_code' => '', 'base_index' => 1, 'valid_from' => '2043-01-01'), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422, 'رمزُ مؤشرٍ فارغ → 422');

$r = PAS::saveTerm($conn, $gate, $CO, $CID, array(
    'trigger_kind' => 'fuel', 'index_code' => 'M09T_IDX', 'base_index' => 0, 'valid_from' => '2043-01-01'), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422, 'قيمةٌ مرجعيةٌ صفرية → 422 (لا قسمةَ على صفر)');

$r = PAS::saveTerm($conn, $gate, $CO, $CID, array(
    'trigger_kind' => 'fuel', 'index_code' => 'M09T_IDX', 'base_index' => 100,
    'pass_through_percent' => 140, 'valid_from' => '2043-01-01'), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422, 'نسبةُ تمريرٍ > 100٪ → 422');

$r = PAS::saveTerm($conn, $gate, $CO, $CID, array(
    'trigger_kind' => 'fuel', 'index_code' => 'M09T_IDX', 'base_index' => 100, 'valid_from' => ''), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422, 'شرطٌ بلا سريان → 422 («ملحقٌ بسريان» يبدأ من شرطٍ بسريان)');

$foreignItem = $conn->query("SELECT id FROM contractequipments WHERE contract_id <> {$CID} LIMIT 1")->fetch_assoc();
$r = PAS::saveTerm($conn, $gate, $CO, $CID, array(
    'trigger_kind' => 'fuel', 'index_code' => 'M09T_IDX', 'base_index' => 100,
    'contract_item_id' => intval($foreignItem['id']), 'valid_from' => '2043-01-01'), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422, 'بندُ تسعيرٍ من عقدٍ آخر → 422');

// ── الشرطُ السليم: وقودٌ · مرجع 100 · عتبة 5٪ · تمرير 50٪ · سقف 10٪ · ربع سنوي
$r = PAS::saveTerm($conn, $gate, $CO, $CID, array(
    'trigger_kind' => 'fuel', 'index_code' => 'M09T_IDX', 'base_index' => 100, 'base_date' => '2043-01-01',
    'threshold_percent' => 5, 'pass_through_percent' => 50, 'cap_percent' => 10,
    'periodicity' => 'quarterly', 'valid_from' => '2043-01-01', 'note' => 'M09T'), $AUTHOR);
check($r['ok'], 'الشرطُ حُفظ (عتبة 5٪ · تمرير 50٪ · سقف 10٪)');
$TERM = intval($r['term_id']);

$r = PAS::saveTerm($conn, $gate, $CO, $CID, array(
    'trigger_kind' => 'fuel', 'index_code' => 'M09T_IDX', 'base_index' => 100, 'valid_from' => '2043-01-01'), $AUTHOR);
check(!$r['ok'] && $r['code'] === 409, 'شرطٌ مكررٌ على (عقد × بند × محفِّز × سريان) → 409');

// ═══ ② القراءةُ بمرجعها ═══
head('② «لا رقمَ بلا مرجع»');
$r = PAS::recordIndexReading($conn, $gate, $CO, array(
    'index_code' => 'M09T_IDX', 'reading_date' => '2043-02-01', 'value' => 103, 'source_ref' => ''), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422, 'قراءةٌ بلا مرجعِ مستندٍ → 422 خدميًّا');

$conn->query("INSERT INTO contract_price_index_readings (company_id, index_code, reading_date, value, source_ref)
              VALUES ({$CO}, 'M09T_IDX_RAW', '2043-02-01', 103, '   ')");
$rawLeak = intval($conn->query("SELECT COUNT(*) n FROM contract_price_index_readings
                                 WHERE index_code='M09T_IDX_RAW'")->fetch_assoc()['n']);
check($rawLeak === 0, 'وكتابةٌ مباشرةٌ بمرجعٍ فارغٍ يرفضها CHECK بنيويًّا');

// ═══ ③ الحساب ═══
head('③ الحسابُ بأعمدة العقد لا بصيغةٍ حرة');
// (أ) لا قراءةَ بعد
$props = PAS::evaluate($conn, $gate, $CO, $CID, '2043-02-15');
check(count($props) === 1 && $props[0]['outcome'] === 'no_reading' && $props[0]['index_value'] === null,
      'بلا قراءةِ مؤشرٍ → `no_reading` **معلَنٌ** وقيمةٌ null لا رقمٌ مخترع');

// (ب) دون العتبة: 103 مقابل 100 = 3٪ < 5٪
$r = PAS::recordIndexReading($conn, $gate, $CO, array(
    'index_code' => 'M09T_IDX', 'reading_date' => '2043-02-01', 'value' => 103,
    'source_ref' => 'نشرةٌ رسمية 2043/02'), $AUTHOR);
check($r['ok'], 'قراءةُ فبراير (103) سُجّلت بمرجعها');
$props = PAS::evaluate($conn, $gate, $CO, $CID, '2043-02-15');
check($props[0]['outcome'] === 'below_threshold' && abs($props[0]['delta_percent'] - 3.0) < 0.001
      && abs($props[0]['new_price'] - 100.0) < 0.001,
      'فارقٌ 3٪ دون عتبة 5٪ → لا تعديل والسعرُ كما هو');

// (ج) التمرير: 120 مقابل 100 = 20٪ × 50٪ = 10٪ — وهو **حدُّ السقف** تمامًا
$r = PAS::recordIndexReading($conn, $gate, $CO, array(
    'index_code' => 'M09T_IDX', 'reading_date' => '2043-05-01', 'value' => 120,
    'source_ref' => 'نشرةٌ رسمية 2043/05'), $AUTHOR);
$props = PAS::evaluate($conn, $gate, $CO, $CID, '2043-05-15');
check($props[0]['outcome'] === 'amended' && abs($props[0]['delta_percent'] - 20.0) < 0.001
      && abs($props[0]['applied_percent'] - 10.0) < 0.001 && abs($props[0]['new_price'] - 110.0) < 0.001,
      'فارقٌ 20٪ × تمرير 50٪ = 10٪ ⇒ السعرُ 110 (على حدّ السقف لا فوقه)');
check($props[0]['effective_from'] === '2043-07-01',
      'السريانُ بدايةُ الدورة التالية (Q2 ⇒ 2043-07-01) — لا تُعاد فوترةُ فترةٍ جرت');

// (د) السقفُ يقصّ: 160 = 60٪ × 50٪ = 30٪ ← مقصوصٌ إلى 10٪
$r = PAS::recordIndexReading($conn, $gate, $CO, array(
    'index_code' => 'M09T_IDX', 'reading_date' => '2043-08-01', 'value' => 160,
    'source_ref' => 'نشرةٌ رسمية 2043/08'), $AUTHOR);
$props = PAS::evaluate($conn, $gate, $CO, $CID, '2043-08-15');
check($props[0]['outcome'] === 'capped' && abs($props[0]['applied_percent'] - 10.0) < 0.001,
      'فارقٌ 60٪ × 50٪ = 30٪ → **مقصوصٌ بالسقف** إلى 10٪ بنتيجةٍ باسمها');

// ═══ ④ الملحقُ بسريانه ═══
head('④ التوليد: ملحقٌ **بسريان** لا ملحقٌ بلا تاريخ');
$r = PAS::applyDue($conn, $gate, $CO, $CID, '2043-05-15', $AUTHOR);
check($r['created'] === 1 && $r['rows'][0]['outcome'] === 'amended', 'وُلّدت مراجعةٌ واحدة');
$REV = intval($r['rows'][0]['revision_id']);
$AMD = intval($r['rows'][0]['amendment_id']);
$amd = $conn->query("SELECT amend_type, effective_from, old_value, new_value FROM contract_amendments WHERE id={$AMD}")->fetch_assoc();
check($amd && $amd['amend_type'] === 'تغيير أسعار' && $amd['effective_from'] === '2043-07-01',
      'الملحقُ من نوع «تغيير أسعار» **وبسريان 2043-07-01**');
check($amd['old_value'] === '100' && $amd['new_value'] === '110', 'وبقيمتَي قبل/بعد صادقتين (100 ⇐ 110)');

$basePrice = floatval($conn->query("SELECT equip_price FROM contractequipments WHERE id={$ITEM}")->fetch_assoc()['equip_price']);
check(abs($basePrice - 100.0) < 0.001, '**السعرُ الأساسيُّ لم يُمسّ** — التعديلُ طبقةٌ فوقه لا محوٌ له');

// ═══ ⑤ العطالة ═══
head('⑤ العطالة — إعادةُ المراجعة للدورة نفسِها');
$amdBefore = intval($conn->query("SELECT COUNT(*) n FROM contract_amendments WHERE contract_id={$CID}")->fetch_assoc()['n']);
$r2 = PAS::applyDue($conn, $gate, $CO, $CID, '2043-06-20', $AUTHOR);   // Q2 نفسُها
$amdAfter = intval($conn->query("SELECT COUNT(*) n FROM contract_amendments WHERE contract_id={$CID}")->fetch_assoc()['n']);
check($r2['created'] === 0 && $r2['skipped'] === 1 && $amdAfter === $amdBefore,
      'تاريخٌ آخر داخل Q2 → صفرُ توليدٍ وصفرُ ملحقٍ ثانٍ (UQ شرط×دورة×بند)');

// ═══ ⑥ الاعتماد ═══
head('⑥ «من أنشأ لا يعتمد» — وغيرُ المعتمَدة لا تحرّك سعرًا');
$p = PAS::effectivePrice($conn, $CO, $CID, $ITEM, '2043-09-01', 100.0);
check(abs($p - 100.0) < 0.001, 'قبل الاعتماد: السعرُ الفعليُّ ما زال 100');

$r = PAS::approve($conn, $gate, $CO, $REV, $AUTHOR);
check(!$r['ok'] && $r['code'] === 403, 'منشئُ المراجعة يعتمدها → **403** (الفصلُ بنيويٌّ)');

$r = PAS::approve($conn, $gate, $CO, $REV, $APPROVER);
check($r['ok'], 'معتمِدٌ آخر: الاعتمادُ يمرّ');

$r = PAS::approve($conn, $gate, $CO, $REV, $APPROVER);
check(!$r['ok'] && $r['code'] === 422, 'اعتمادٌ ثانٍ للمراجعة نفسِها → 422');

// ═══ ⑦ لا رجعية ═══
head('⑦ **لا رجعية** عدديًّا — في الاستدعاء نفسِه');
$before = PAS::effectivePrice($conn, $CO, $CID, $ITEM, '2043-06-30', 100.0);
$after  = PAS::effectivePrice($conn, $CO, $CID, $ITEM, '2043-07-01', 100.0);
check(abs($before - 100.0) < 0.001, 'واقعةُ 2043-06-30 (عشيةَ السريان) بالسعر القديم 100');
check(abs($after - 110.0) < 0.001,  'وواقعةُ 2043-07-01 (يومَ السريان) بالجديد 110');
$far = PAS::effectivePrice($conn, $CO, $CID, $ITEM, '2043-12-31', 100.0);
check(abs($far - 110.0) < 0.001, 'وما بعدَه يبقى بالجديد حتى مراجعةٍ معتمَدةٍ لاحقة');

// ═══ ⑧ صفرُ انحدار ═══
head('⑧ صفرُ انحدارٍ على ما لا شرطَ له');
$otherItem = $conn->query("SELECT ce.id, ce.contract_id, ce.equip_price FROM contractequipments ce
                            WHERE ce.contract_id <> {$CID} AND ce.equip_price > 0 LIMIT 1")->fetch_assoc();
$op = PAS::effectivePrice($conn, $CO, intval($otherItem['contract_id']), intval($otherItem['id']),
                          '2026-07-30', floatval($otherItem['equip_price']));
check(abs($op - floatval($otherItem['equip_price'])) < 0.001,
      'بندٌ بلا شرطٍ ولا مراجعةٍ يعود أساسَه حرفيًّا (' . $otherItem['equip_price'] . ')');
$liveRevisions = intval($conn->query("SELECT COUNT(*) n FROM contract_price_revisions r
    JOIN contracts c ON c.id = r.contract_id WHERE c.first_party NOT LIKE 'M09T%'
       OR c.first_party IS NULL")->fetch_assoc()['n']);
check($liveRevisions === 0, 'صفرُ مراجعةٍ على أي عقدٍ حيٍّ ⇒ صفرُ سعرٍ تغيّر في الإنتاج');

// ═══ ⑨ الصرفُ من مصدره ═══
head('⑨ `fx` يقرأ fin_fx_rates — مصدرَ الحقيقة الواحد');
$idx = PAS::resolveIndex($conn, $CO, 'fx', 'SDG', '2026-07-30');
check(!empty($idx['ok']) && $idx['value'] > 0 && strpos($idx['source'], 'fin_fx_rates') === 0,
      'سعرُ صرفِ SDG يُقرأ من fin_fx_rates بسريانه (' . $idx['value'] . ')');
$idx = PAS::resolveIndex($conn, $CO, 'fx', 'JPY', '2026-07-30');
check(empty($idx['ok']) && strpos($idx['reason'], 'لا يُخترع') !== false,
      'وعملةٌ بلا سعرٍ مسجَّل → **يُعلَن ولا يُخترع**');

// ═══ ⑩ العلمُ والكرونُ والشاشة ═══
head('⑩ العلَمُ قائمةُ نطاقٍ · الكرونُ · الشاشة');
check(PAS::autoAdjustEnabledFor($CID) === false, 'العلمُ فارغٌ ⇒ لا تشغيلَ دوريًّا (فارغٌ = مطفأ)');
$env = file_get_contents(dirname(__DIR__) . '/.env');
check(strpos($env, 'EMS_PRICE_ADJUST=') !== false, 'العلمُ معلَنٌ في .env بسببِ فراغه');
$cron = file_get_contents(dirname(__DIR__) . '/Contracts/cron_price_adjustment.php');
check(strpos($cron, 'applyDue') !== false && strpos($cron, "PHP_SAPI !== 'cli'") !== false,
      'الكرونُ قائمٌ بنمطه (CLI حصرًا · بوابةُ النظام بشركة العقد)');
$mod = $conn->query("SELECT id, owner_role_id FROM modules WHERE code='Contracts/price_terms.php'")->fetch_assoc();
check($mod && intval($mod['id']) === 154 && intval($mod['owner_role_id']) === 12,
      'الوحدة 154 مسجَّلةٌ لمالك العقود (12)');
$src = file_get_contents(dirname(__DIR__) . '/Contracts/price_terms.php');
check(strpos($src, 'PAS::saveTerm') !== false && strpos($src, "update('contractequipments'") === false,
      'الشاشةُ لا تمسّ السعرَ الأساسيَّ ولا تكتب خامًا — كلُّ فعلٍ عبر الخدمة');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
