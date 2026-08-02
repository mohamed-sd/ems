<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0005 · الموجة ② — الدفترُ والتغطيةُ البديلة (CAP-01 §6/§7/§13 · CAP-07→12)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/cap_w2_ledger_test.php
 *
 * ما يُثبته:
 *   ① البنية: الجداولُ الأربعةُ بأسمائها الحرفية (DEC-CAP-C) بمفاتيحها.
 *   ② C24/C25: السطرُ يُكتب مرةً — إعادةُ الإرسال 409 بمرجع السطر القائم
 *      وصفرُ خصمٍ ثانٍ.
 *   ③ C26: العكسُ سطرٌ عاكسٌ بمرجع الأصل والأصلُ باقٍ · تكرارُ العكس 409 ·
 *      عكسُ العكس 422 · عكسٌ بلا مرجعٍ 422.
 *   ④ الحصانةُ البنيوية: تعديلُ سطرِ دفترٍ أو حذفُه عبر البوابة → رفضٌ
 *      (immutable_key — Insert-only).
 *   ⑤ CAP-08: الربطُ الماليُّ Append-only بUQ(led,fin).
 *   ⑥ §6.1: لا تغطيةَ مفتوحةَ المدة (NOT NULL بنيوي) · ولا بسببٍ خارج القائمة
 *      المحكومة · ولا بنهايةٍ قبل بدايتها (CHECK).
 *   ⑦ CAP-12: الاستهلاكُ الموروثُ (entry:2410:r1) له سطرُه في الدفتر.
 *
 * البذرُ معزول: unit_record_id=99942042 صوريٌّ — يُكنس قبل وبعد.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/CapacityLedgerService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Core\TenantGateException;
use App\Services\Capacity\CapacityLedgerService as LED;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999906; $REC = 99942042;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn, $REC) {
    $conn->query("DELETE FROM capacity_financial_event_links
                   WHERE led_id IN (SELECT led_id FROM (SELECT led_id FROM capacity_consumption_ledger
                                     WHERE unit_record_id = {$REC}) x)");
    $conn->query("DELETE FROM coverage_settlement_lines WHERE note LIKE 'CAPW2T%'");
    $conn->query("DELETE FROM substitute_coverages WHERE note LIKE 'CAPW2T%'");
    $conn->query("DELETE FROM capacity_consumption_ledger WHERE unit_record_id = {$REC} AND reverses_led_id IS NOT NULL");
    $conn->query("DELETE FROM capacity_consumption_ledger WHERE unit_record_id = {$REC}");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0005 · الموجة ② — دفترُ الاستهلاك والتغطيةُ البديلة ══\n");

// ═══ ① البنية ═══
head('① الجداولُ الأربعةُ بأسمائها الحرفية (DEC-CAP-C)');
foreach (array('capacity_consumption_ledger', 'capacity_financial_event_links',
               'substitute_coverages', 'coverage_settlement_lines') as $t) {
    $n = intval($conn->query("SELECT COUNT(*) n FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = '{$t}'")->fetch_assoc()['n']);
    check($n === 1, "الجدول {$t} قائم");
}
$uq = intval($conn->query("SELECT COUNT(*) n FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'capacity_consumption_ledger'
      AND index_name = 'uq_ledger_no_double' AND non_unique = 0")->fetch_assoc()['n']);
check($uq === 5, 'مفتاحُ منع الخصم مرتين خماسيُّ الأعمدة (وحدةٌ × نسخةٌ × أثرٌ × طرفٌ × مرجع)');

// ═══ ② C24/C25 ═══
head('② C24/C25 — السطرُ يُكتب مرةً وإعادةُ الإرسال 409');
$line = array(
    'unit_record_id' => $REC, 'unit_record_version' => 1,
    'effect_type' => 'client_obligation', 'effect_target_type' => 'client',
    'effect_target_ref' => 'contract:7', 'measure_code' => 'hour',
    'qty' => 9.5, 'period' => '2042-03', 'role_snapshot' => 'primary',
);
$r1 = LED::appendLine($gate, $line, $ACTOR);
check($r1['ok'] && $r1['led_id'] > 0, 'سطرُ التزام العميل قُيّد: ' . $r1['reason']);
$L1 = intval($r1['led_id']);
$r2 = LED::appendLine($gate, array_merge($line, array('effect_type' => 'supplier_share',
    'effect_target_type' => 'supplier', 'effect_target_ref' => 'sup:9')), $ACTOR);
check($r2['ok'], 'سطرُ حصة المورد لنفس الوحدة قُيّد — ثلاثةُ أحكامٍ مستقلةٌ لواقعةٍ واحدة (§13.1)');
$rDup = LED::appendLine($gate, $line, $ACTOR);
check(!$rDup['ok'] && intval($rDup['code']) === 409 && intval($rDup['existing_led_id']) === $L1,
      'إعادةُ الإرسال → 409 بمرجع السطر القائم led#' . $rDup['existing_led_id'] . ' (C25)');
$cnt = intval($conn->query("SELECT COUNT(*) n FROM capacity_consumption_ledger
    WHERE unit_record_id = {$REC} AND effect_type = 'client_obligation'")->fetch_assoc()['n']);
check($cnt === 1, 'صفرُ خصمٍ ثانٍ — السطرُ واحدٌ في القاعدة');
$rBadMeasure = LED::appendLine($gate, array_merge($line, array('measure_code' => 'cbm',
    'effect_target_ref' => 'contract:8')), $ACTOR);
check(!$rBadMeasure['ok'] && intval($rBadMeasure['code']) === 422, 'مقياسٌ خارج الأربعة → 422 (C30 بابُه)');

// ═══ ③ C26 ═══
head('③ C26 — العكسُ سطرٌ عاكسٌ والأصلُ باقٍ');
$rv = LED::reverse($gate, $L1, $ACTOR);
check($rv['ok'] && $rv['led_id'] > 0, 'العكسُ قُيّد: ' . $rv['reason']);
$RV = intval($rv['led_id']);
$row = $conn->query("SELECT effect_type, reverses_led_id, qty FROM capacity_consumption_ledger WHERE led_id = {$RV}")->fetch_assoc();
check($row && $row['effect_type'] === 'reversal' && intval($row['reverses_led_id']) === $L1,
      'السطرُ العاكسُ بمرجع الأصل — reversal→led#' . $L1);
$orig = $conn->query("SELECT COUNT(*) n FROM capacity_consumption_ledger WHERE led_id = {$L1}")->fetch_assoc();
check(intval($orig['n']) === 1, 'الأصلُ باقٍ لم يُمسّ (C26)');
$rv2 = LED::reverse($gate, $L1, $ACTOR);
check(!$rv2['ok'] && intval($rv2['code']) === 409 && intval($rv2['existing_led_id']) === $RV,
      'تكرارُ العكس → 409 بمرجع العكس القائم');
$rv3 = LED::reverse($gate, $RV, $ACTOR);
check(!$rv3['ok'] && intval($rv3['code']) === 422, 'عكسُ سطرِ عكسٍ → 422 — التصحيحُ نسخةٌ جديدة');
$rv4 = LED::reverse($gate, 0, $ACTOR);
check(!$rv4['ok'] && intval($rv4['code']) === 422, 'عكسٌ بلا مرجعِ سطرٍ أصلي → 422');

// ═══ ④ الحصانةُ البنيوية ═══
head('④ Insert-only — البوابةُ ترفض التعديلَ والحذف');
$refused = false;
try { $gate->update('capacity_consumption_ledger', array('qty' => 999), array('led_id' => $L1)); }
catch (TenantGateException $e) { $refused = true; }
check($refused, 'تعديلُ سطرِ دفترٍ عبر البوابة → رفضٌ بنيوي (immutable_key)');
$still = floatval($conn->query("SELECT qty FROM capacity_consumption_ledger WHERE led_id = {$L1}")->fetch_assoc()['qty']);
check(abs($still - 9.5) < 0.001, 'الكميةُ كما كانت — 9.500');

// ═══ ⑤ CAP-08 ═══
head('⑤ الربطُ الماليُّ Append-only');
$lk = LED::linkFinancialEvent($gate, $L1, 424242, 'JV-TEST');
check($lk['ok'], 'رُبط سطرُ الدفتر بحدثٍ مالي: ' . $lk['reason']);
$lk2 = LED::linkFinancialEvent($gate, $L1, 424242);
check(!$lk2['ok'] && intval($lk2['code']) === 409, 'الربطُ نفسُه ثانيةً → 409 — UQ(led,fin)');
$conn->query("DELETE FROM capacity_financial_event_links WHERE fin_event_id = 424242");

// ═══ ⑥ §6.1 — قيودُ التغطية ═══
head('⑥ §6.1 — لا تغطيةَ مفتوحةً ولا بلا سببٍ محكوم');
$SEAT = intval($conn->query("SELECT id FROM op_containers WHERE company_id={$CO} AND level='معدة'
                             ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$open = $conn->query("INSERT INTO substitute_coverages
    (company_id, level, covered_seat_id, covering_supplier_id, reason_code, valid_from, valid_to, note)
    VALUES ({$CO}, 'own_standby', {$SEAT}, {$SUP}, 'breakdown', '2042-01-01', NULL, 'CAPW2T مفتوحة')");
check(!$open, 'تغطيةٌ بلا نهايةٍ → مرفوضةٌ بنيويًّا (NOT NULL): ' . $conn->error);
$badReason = $conn->query("INSERT INTO substitute_coverages
    (company_id, level, covered_seat_id, covering_supplier_id, reason_code, valid_from, valid_to, note)
    VALUES ({$CO}, 'own_standby', {$SEAT}, {$SUP}, 'mood', '2042-01-01', '2042-01-05', 'CAPW2T سبب حر')");
check(!$badReason, 'سببٌ خارج القائمة المحكومة → مرفوض (ENUM): ' . $conn->error);
$badDates = $conn->query("INSERT INTO substitute_coverages
    (company_id, level, covered_seat_id, covering_supplier_id, reason_code, valid_from, valid_to, note)
    VALUES ({$CO}, 'own_standby', {$SEAT}, {$SUP}, 'breakdown', '2042-01-10', '2042-01-05', 'CAPW2T معكوسة')");
check(!$badDates, 'نهايةٌ قبل البداية → CHECK يرفض');
$good = $conn->query("INSERT INTO substitute_coverages
    (company_id, level, covered_seat_id, covering_supplier_id, reason_code, reason_ref, valid_from, valid_to, estimated_hours, note)
    VALUES ({$CO}, 'own_standby', {$SEAT}, {$SUP}, 'breakdown', 'TKT-100', '2042-01-01', '2042-01-05', 40, 'CAPW2T سليمة')");
check($good, 'تغطيةٌ بسببٍ محكومٍ ومدةٍ مغلقةٍ وأثرٍ مقدَّرٍ تُقبل');
$COV = intval($conn->insert_id);
$csl = $conn->query("INSERT INTO coverage_settlement_lines
    (company_id, cov_id, party, effect, qty, measure_code, note)
    VALUES ({$CO}, {$COV}, 'failed_supplier', 'gap_kept', 100, 'hour', 'CAPW2T عجزٌ باقٍ'),
           ({$CO}, {$COV}, 'covering_supplier', 'exceptional_line', 100, 'hour', 'CAPW2T بندٌ مستقل'),
           ({$CO}, {$COV}, 'client', 'billable', 100, 'hour', 'CAPW2T يُفوتر'),
           ({$CO}, {$COV}, 'operator', 'entitlement', 100, 'hour', 'CAPW2T بعقده')");
check($csl, 'بنودُ التسوية الأربعةُ (طرفٌ × أثر) قُيّدت ظاهرةً لا مدموجة (§7)');

// ═══ ⑦ CAP-12 ═══
head('⑦ الخرطُ الموروث — صفرُ استهلاكٍ بلا سطرِ دفتر');
$legacy = intval($conn->query("SELECT COUNT(*) n FROM capacity_consumption_ledger
    WHERE unit_record_id = 2410 AND unit_record_version = 1")->fetch_assoc()['n']);
check($legacy === 1, 'صفُّ container_consumption الموروث (entry:2410:r1) له سطرُه في الدفتر');
$orphans = intval($conn->query("SELECT COUNT(*) n FROM container_consumption cc
    WHERE cc.qty >= 0 AND cc.source_kind = 'unit_entry'
      AND cc.unit_type IN ('hour','ton','trip','meter')
      AND NOT EXISTS (SELECT 1 FROM capacity_consumption_ledger l
                       WHERE l.unit_record_id = cc.source_ref)")->fetch_assoc()['n']);
check($orphans === 0, 'صفرُ استهلاكٍ موروثٍ بلا سطرٍ في الدفتر');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
