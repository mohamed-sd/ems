<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-52 — اختبار قبول: أمرُ الترحيل بتعرفته مصدرًا لتحميل النقل (ENT-02 §3-④)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/transfer_tariff_test.php
 *
 * ما يُثبته:
 *   ① **والمسلَّمُ وحدَه يُسعَّر**: مرحلةٌ دون الوصول ⇒ **423**.
 *   ② **ولا تحميلَ بلا تعرفةٍ مكتوبة**: بلا تعرفةٍ منطبقةٍ ⇒ **422 بسببه** —
 *      و`actual_cost_usd` **لا تُستعمل بديلًا** (تلك تكلفتُنا لا تعرفتُه).
 *   ③ **النماذجُ الأربعة بأرقامها**: نقلةٌ · كيلومترٌ (**وبلا مسافةٍ 422**) ·
 *      طنٌّ · معدة — محسوبةً يدويًّا.
 *   ④ **والأخصُّ يغلب**: تعرفةُ موردٍ بعينه تغلب تعرفةَ المسار وتلك تغلب الأعمّ.
 *   ⑤ **والحدّان يقصّان** ويُعلَن قصُّهما في بيان السطر.
 *   ⑥ **ولا يُسعَّر مرتين** (409) — وإعادةُ التسعير **بحجّةٍ مكتوبة** تُسجَّل.
 *   ⑦ **والوصلُ حيّ**: تسويةُ المورد تحمل **سطرَ نقلٍ برابط أمره وتعرفته**.
 *   ⑧ **و`CHECK` يرفض مبلغًا مسعَّرًا بلا مرجع تعرفة** — بنيويًّا لا بفحص.
 *
 * البذرُ معزول: مواقعُ وأمرٌ ومورّدٌ بوسمٍ فريد — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '23', 'company_id' => 4, 'name' => 'M52 tariff test');

require_once dirname(__DIR__) . '/app/Services/Transport/TransferTariffService.php';
require_once dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php';

use App\Services\Transport\TransferTariffService as TTS;
use App\Services\Settlement\SettlementService as SS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999521;
$MARK  = 'M52T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE l FROM settlement_lines l JOIN settlements s ON s.id = l.settlement_id
                   WHERE s.note LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM settlements WHERE note LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM transfer_lines l JOIN transfer_orders o ON o.id = l.order_id
                   WHERE o.notes LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM transfer_orders WHERE notes LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM transfer_tariffs WHERE note LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM transfer_types WHERE code LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM trs_locations WHERE code LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-52 — أمرُ الترحيل بتعرفته ══\n");

head('البذر — موردٌ وموقعان ونوعٌ وأمرُ ترحيلٍ مسلَّم');

$conn->query("INSERT INTO suppliers (company_id, name, phone, created_at)
              VALUES ({$CO}, 'موردُ {$MARK}', '0100000000', NOW())");
if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); }
$SUP = intval($conn->insert_id);

$conn->query("INSERT INTO trs_locations (company_id, code, name, location_type, created_at, updated_at)
              VALUES ({$CO}, 'F-{$MARK}', 'مبدأُ {$MARK}', 'base', NOW(), NOW())");
$LF = intval($conn->insert_id);
$conn->query("INSERT INTO trs_locations (company_id, code, name, location_type, created_at, updated_at)
              VALUES ({$CO}, 'T-{$MARK}', 'منتهى {$MARK}', 'project', NOW(), NOW())");
$LT = intval($conn->insert_id);

$conn->query("INSERT INTO transfer_types (company_id, code, name, operational_category,
              default_bearer, active, created_at, updated_at)
              VALUES ({$CO}, 'TY-{$MARK}', 'نوعُ {$MARK}', 'equipment_transfer', 'company', 1, NOW(), NOW())");
$TY = intval($conn->insert_id);

$mkOrder = function ($stage, $suffix) use ($conn, $CO, $SUP, $LF, $LT, $TY, $MARK) {
    $arr = ($stage === 'arrived' || $stage === 'closed') ? "'2091-05-10 12:00:00'" : 'NULL';
    $conn->query("INSERT INTO transfer_orders
        (company_id, order_no, transfer_type_id, direction, source_module, project_id,
         from_location_id, to_location_id, request_date, planned_date, arrival_datetime,
         cost_bearer, charge_supplier_id, priority, stage, notes, actual_cost_usd, created_at, updated_at)
        VALUES ({$CO}, 'TO-{$MARK}-{$suffix}', {$TY}, 'mob', 'fleet', NULL,
                {$LF}, {$LT}, '2091-05-01', '2091-05-09', {$arr},
                'company', {$SUP}, 'normal', '{$stage}', 'أمرُ {$MARK}', 999.99, NOW(), NOW())");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$mkTariff = function ($model, $rate, $args = array()) use ($conn, $CO, $MARK) {
    $sup = isset($args['supplier_id']) ? (int) $args['supplier_id'] : 'NULL';
    $ty  = isset($args['transfer_type_id']) ? (int) $args['transfer_type_id'] : 'NULL';
    $lf  = isset($args['from_location_id']) ? (int) $args['from_location_id'] : 'NULL';
    $lt  = isset($args['to_location_id']) ? (int) $args['to_location_id'] : 'NULL';
    $mn  = isset($args['min_amount']) ? (float) $args['min_amount'] : 'NULL';
    $mx  = isset($args['max_amount']) ? (float) $args['max_amount'] : 'NULL';
    $conn->query("INSERT INTO transfer_tariffs
        (company_id, supplier_id, transfer_type_id, from_location_id, to_location_id,
         pricing_model, rate, currency, min_amount, max_amount, effective_from, state, note, created_at)
        VALUES ({$CO}, {$sup}, {$ty}, {$lf}, {$lt}, '{$model}', {$rate}, 'SDG',
                {$mn}, {$mx}, '2091-01-01', 'active', 'تعرفةُ {$MARK}', NOW())");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};

$O_PLAN = $mkOrder('planned', 'P');
$O1     = $mkOrder('arrived', 'A');
check($SUP > 0 && $LF > 0 && $LT > 0 && $TY > 0 && $O1 > 0 && $O_PLAN > 0,
      "موردٌ #{$SUP} · مساران · نوعٌ · وأمران (مخططٌ ومسلَّم)");

// بندُ معدةٍ واحدٌ + مادةٌ 12 طنًّا على الأمر المسلَّم
$conn->query("INSERT INTO transfer_lines (company_id, order_id, item_type, quantity, note)
              VALUES ({$CO}, {$O1}, 'equipment', 1, 'معدةُ {$MARK}')");
$conn->query("INSERT INTO transfer_lines (company_id, order_id, item_type, quantity, note)
              VALUES ({$CO}, {$O1}, 'material', 12, 'مادةُ {$MARK}')");

// ═══ ① المسلَّمُ وحدَه يُسعَّر ═══
head('① **والمسلَّمُ وحدَه يُسعَّر** (§3-④)');
$r = TTS::priceOrder($conn, $gate, $CO, $O_PLAN, $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && mb_strpos($r['reason'], 'المسلَّم') !== false,
      '★ أمرٌ في «planned» ⇒ **423** — «وأمرٌ لم يصل ليس مستندَ تحميل»');

// ═══ ② لا تحميلَ بلا تعرفة ═══
head('② **ولا تحميلَ بلا تعرفةٍ مكتوبة**');
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'لا تعرفةَ مكتوبةً منطبقة') !== false,
      '★★ بلا تعرفةٍ ⇒ **422 بسببه** — و`actual_cost_usd`=999.99 **لم تُستعمل بديلًا**');
$q = $conn->query("SELECT tariff_amount FROM transfer_orders WHERE id={$O1}")->fetch_assoc();
check($q['tariff_amount'] === null, 'ولا رقمَ كُتب على الأمر');

// ═══ ③ النماذجُ بأرقامها ═══
head('③ **النماذجُ الأربعة بأرقامها**');

$T_TRIP = $mkTariff('per_trip', 700);
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR);
check($r['ok'] && abs($r['amount'] - 700.0) < 0.005,
      '★ **بالنقلة**: 1 × 700 = **700**');

$T_TON = $mkTariff('per_ton', 25, array('transfer_type_id' => $TY));
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR, 'اختبارُ نموذج الطن');
check($r['ok'] && abs($r['amount'] - 300.0) < 0.005,
      '★★ **بالطن**: 12 طنًّا × 25 = **300** (والأخصُّ بالنوع غلب الأعمّ)');

$T_EQP = $mkTariff('per_equipment', 450, array('transfer_type_id' => $TY,
                                               'from_location_id' => $LF, 'to_location_id' => $LT));
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR, 'اختبارُ نموذج المعدة');
check($r['ok'] && abs($r['amount'] - 450.0) < 0.005,
      '★★ **بالمعدة**: بندُ معدةٍ واحدٌ × 450 = **450** (وتعرفةُ المسار غلبت تعرفةَ النوع)');

// ── الكيلومتر: بلا مسافةٍ لا تسعير ──
$T_KM = $mkTariff('per_km', 8, array('supplier_id' => $SUP));
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR, 'اختبارُ الكيلومتر');
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'بلا مسافةٍ مكتوبة') !== false,
      '★★ **بالكيلومتر وبلا مسافةٍ ⇒ 422** — ولا تُقدَّر مسافة');
$conn->query("UPDATE transfer_orders SET distance_km=150 WHERE id={$O1}");
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR, 'اختبارُ الكيلومتر بمسافته');
check($r['ok'] && abs($r['amount'] - 1200.0) < 0.005,
      '★★ وبمسافة 150 كم × 8 = **1200**');

// ═══ ④ الأخصُّ يغلب ═══
head('④ **والأخصُّ يغلب** — موردٌ > مسارٌ > نوعٌ > الأعمّ');
$res = TTS::resolve($gate, TTS::orderOf($gate, $O1));
check($res['ok'] && (int) $res['tariff']['id'] === $T_KM && $res['candidates'] === 4,
      '★★ أربعُ تعرفاتٍ منطبقةٍ و**تعرفةُ المورد (#' . $T_KM . ') وحدَها تفوز**');

// ═══ ⑤ الحدّان يقصّان ═══
head('⑤ **والحدّان يقصّان ويُعلن قصُّهما**');
$conn->query("UPDATE transfer_tariffs SET max_amount=500 WHERE id={$T_KM}");
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR, 'اختبارُ السقف');
check($r['ok'] && abs($r['amount'] - 500.0) < 0.005
      && mb_strpos($r['note'], 'قُصّ بالحدّ الأقصى') !== false,
      '★★ 1200 **مقصوصةٌ إلى 500** والبيانُ يقول ذلك نصًّا');
$conn->query("UPDATE transfer_tariffs SET max_amount=NULL, min_amount=2000 WHERE id={$T_KM}");
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR, 'اختبارُ الأرضية');
check($r['ok'] && abs($r['amount'] - 2000.0) < 0.005
      && mb_strpos($r['note'], 'قُصّ بالحدّ الأدنى') !== false,
      '★ و1200 **مرفوعةٌ إلى 2000** بالحدّ الأدنى — معلَنًا');
$conn->query("UPDATE transfer_tariffs SET min_amount=NULL WHERE id={$T_KM}");
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR, 'إعادةٌ إلى الأصل');
check($r['ok'] && abs($r['amount'] - 1200.0) < 0.005, 'وبلا حدَّين تعود 1200');

// ═══ ⑥ ولا يُسعَّر مرتين ═══
head('⑥ **ولا يُسعَّر مرتين**');
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR);
check(!$r['ok'] && $r['code'] === 409 && abs($r['amount'] - 1200.0) < 0.005
      && mb_strpos($r['reason'], 'حجّةٌ مكتوبة') !== false,
      '★★ تسعيرٌ ثانٍ بلا حجّةٍ ⇒ **409 بمبلغه ومرجعه**');
$r = TTS::priceOrder($conn, $gate, $CO, $O1, $ACTOR, 'صُحّحت المسافةُ بعد القياس');
check($r['ok'] && mb_strpos($r['note'], 'أُعيد التسعيرُ') !== false,
      '★ وبحجّةٍ مكتوبةٍ يقع **والحجّةُ تُسجَّل في البيان**');

// ═══ ⑦ الوصلُ الحي ═══
head('⑦ **والوصلُ حيّ** — سطرُ النقل في تسوية المورد');
$lines = TTS::chargeLines($gate, $SUP, '2091-05-01', '2091-05-31');
check(count($lines) === 1 && (string) $lines[0]['charge_type'] === 'transport'
      && (string) $lines[0]['source_kind'] === 'transfer_order'
      && (int) $lines[0]['source_ref'] === $O1
      && abs((float) $lines[0]['amount'] - 1200.0) < 0.005,
      '★★ سطرٌ واحدٌ **`transport` برابط أمره** بمبلغ 1200');
check(mb_strpos((string) $lines[0]['description'], 'TO-' . $MARK) !== false
      && mb_strpos((string) $lines[0]['description'], 'تعرفة #') !== false,
      '★ والبيانُ يحمل **رقمَ الأمر وتعرفتَه** — كلُّ رقمٍ ينقر لمصدره');

$g = SS::generate($gate, $conn, 'supplier', $SUP, '2091-05-01', '2091-05-31', $ACTOR);
check($g['ok'], 'وتوليدُ التسوية: ' . (isset($g['reason']) ? $g['reason'] : ''));
if ($g['ok']) {
    // وسمُ الرأس ليكنسه التعقيب (الخدمةُ لا تستقبل ملاحظةً في توقيعها)
    $conn->query("UPDATE settlements SET note = CONCAT(COALESCE(note,''), ' تسويةُ {$MARK}')
                   WHERE id=" . intval($g['settlement_id']));
}
if ($g['ok']) {
    $sl = $conn->query("SELECT charge_type, source_kind, source_ref, amount FROM settlement_lines
                         WHERE settlement_id=" . intval($g['settlement_id'])
                       . " AND charge_type='transport'")->fetch_assoc();
    check($sl && (string) $sl['source_kind'] === 'transfer_order'
          && abs((float) $sl['amount'] - 1200.0) < 0.005,
          '★★ والتسويةُ المولَّدةُ تحمل **سطرَ النقل 1200 برابط أمره** — المصدرُ السادس حيّ');
}

// ═══ ⑧ CHECK المصدر ═══
head('⑧ **و`CHECK` يرفض مبلغًا مسعَّرًا بلا مرجع تعرفة**');
$conn->query("UPDATE transfer_orders SET tariff_id=NULL, tariff_currency=NULL WHERE id={$O1}");
$q = $conn->query("SELECT tariff_id, tariff_amount FROM transfer_orders WHERE id={$O1}")->fetch_assoc();
check($q['tariff_id'] !== null && $q['tariff_amount'] !== null,
      '★★ محوُ مرجع التعرفة عن مبلغٍ مسعَّرٍ **يرفضه CHECK** — «المبلغُ يُقرأ من مصدره» بنيويًّا');

// ═══ العزل ═══
head('العزلُ محفوظ');
$_SESSION['user']['company_id'] = 1;
$otherGate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::fromSession());
$leak = $otherGate->selectOne('transfer_tariffs', array('where' => array('id' => $T_KM)));
check($leak === null, '★ تعرفةُ شركةٍ لا تُقرأ من نطاقٍ آخر — صفرُ تسريب');
$_SESSION['user']['company_id'] = $CO;

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
