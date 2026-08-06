<?php
/**
 * tools/rental_core_seed.php — بذرُ RENTAL-CORE التجريبي · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * بياناتٌ تجريبيةٌ **واقعيةٌ** لا عشوائية: الشرائحُ من توزيع مددهم الفعلي
 * (متوسطُ التشغيل 74 يومًا)، والفئاتُ من `equipments_types` الحقيقية، والحجوزاتُ
 * على معداتٍ متاحةٍ فعلًا يتحقق منها الحارسُ نفسُه قبل الإدراج.
 *
 * php tools/rental_core_seed.php --apply    → تنفيذ
 * php tools/rental_core_seed.php --revert   → تراجع
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Services/Rental/AvailabilityService.php';
require_once __DIR__ . '/../app/Services/Rental/RateBookService.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

use App\Services\Rental\AvailabilityService as AV;
use App\Services\Rental\RateBookService as RB;

$APPLY  = in_array('--apply', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$CO = 4; $UID = 13;
$BOOK_CODE = 'RB-' . date('Y') . '-001';

$_SESSION['user'] = array('id' => $UID, 'role' => 12, 'company_id' => $CO);
$gate = ems_tenant_db();

$o('══ بذرُ RENTAL-CORE ══');

if ($REVERT) {
    $b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM rate_books WHERE company_id=$CO AND book_code='$BOOK_CODE'"));
    if ($b) {
        mysqli_query($conn, "DELETE FROM rate_book_lines WHERE book_id=" . (int) $b['id']);
        mysqli_query($conn, "DELETE FROM rate_books WHERE id=" . (int) $b['id']);
        $o('  حُذف الدفتر وبنودُه');
    }
    mysqli_query($conn, "DELETE FROM fleet_reservations WHERE company_id=$CO AND note='بذرٌ تجريبي — RENTAL-CORE'");
    $o('  حُذفت الحجوزات: ' . mysqli_affected_rows($conn));
    exit(0);
}

// ── الفئاتُ الحقيقيةُ المستعملة فعلًا في الأسطول ───────────────────────────
$types = array();
$q = mysqli_query($conn, "SELECT t.id, t.type, COUNT(e.id) n
    FROM equipments_types t JOIN equipments e ON e.type = t.id AND e.company_id=$CO
    GROUP BY t.id ORDER BY n DESC");
while ($r = mysqli_fetch_assoc($q)) { $types[] = $r; }
$o('  فئاتٌ مستعملةٌ في الأسطول: ' . count($types));
foreach ($types as $t) { $o('    #' . str_pad($t['id'], 4) . ' ' . str_pad($t['type'], 14) . $t['n'] . ' معدة'); }

if (!$APPLY) { $o(''); $o('  (تجريبٌ — أعِد التشغيل بـ --apply)'); exit(0); }

// ── ① دفترُ الأسعار ───────────────────────────────────────────────────────
$o('');
$o('── دفترُ الأسعار');
$b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM rate_books WHERE company_id=$CO AND book_code='$BOOK_CODE'"));
if ($b) { $bid = (int) $b['id']; $o('  الدفترُ قائمٌ #' . $bid); }
else {
    $bid = (int) $gate->insert('rate_books', array(
        'book_code' => $BOOK_CODE, 'name' => 'تسعيرةُ ' . date('Y') . ' — الأسطولُ العام',
        'currency' => 'USD', 'client_id' => null,
        'valid_from' => date('Y-01-01'), 'valid_to' => null, 'state' => 'معتمد',
        'approved_by' => $UID, 'approved_at' => date('Y-m-d H:i:s'),
        'note' => 'بذرٌ تجريبي — RENTAL-CORE', 'is_deleted' => 0,
        'created_by' => $UID, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ));
    $o('  + الدفتر #' . $bid . ' (' . $BOOK_CODE . ') معتمدٌ وساري');
}

/** سعرُ الأساس بالساعة لكل فئة (USD) — تقديرٌ سوقيٌّ معقولٌ للتجربة. */
$BASE = array('حفار' => 45.00, 'قلاب' => 28.00, 'خرامة' => 65.00, 'دوزر' => 55.00,
              'قريدر' => 50.00, 'لودر' => 35.00);
/** التدرُّجُ بالشريحة: الأطولُ أرخص — عرفُ التأجير. */
$TIERS = array(
    array(1, 7, 1.00, 3),      // ≤ أسبوع: السعرُ الكامل · حدٌّ أدنى 3 أيام
    array(8, 30, 0.92, 7),     // شهرٌ فأقل: −8٪
    array(31, 90, 0.85, 15),   // ربع: −15٪
    array(91, 180, 0.78, 30),  // نصفُ سنة: −22٪
    array(181, null, 0.72, 30),// ما فوق: −28٪
);

$added = 0;
foreach ($types as $t) {
    $tid = (int) $t['id'];
    $base = isset($BASE[$t['type']]) ? $BASE[$t['type']] : 40.00;
    foreach ($TIERS as $tr) {
        list($tf, $tt, $mult, $minDays) = $tr;
        $dup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM rate_book_lines
            WHERE company_id=$CO AND book_id=$bid AND equipment_type_id=$tid AND work_model='hour' AND tier_from_days=$tf"));
        if ($dup) { continue; }
        $gate->insert('rate_book_lines', array(
            'book_id' => $bid, 'equipment_type_id' => $tid, 'work_model' => 'hour',
            'tier_from_days' => $tf, 'tier_to_days' => $tt,
            'unit_price' => round($base * $mult, 2),
            'min_hire_days' => $minDays, 'min_hours_per_day' => 8.0,
            'mobilization_fee' => ($t['type'] === 'خرامة' ? 1500.00 : 800.00),
            'operator_included' => 1, 'fuel_included' => 0,
            'note' => 'بذرٌ تجريبي', 'is_deleted' => 0,
            'created_by' => $UID, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ));
        $added++;
    }
    // بندٌ شهريٌّ للقلابات والحفارات — نموذجُ عملٍ ثانٍ
    if (in_array($t['type'], array('حفار', 'قلاب'), true)) {
        $dup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM rate_book_lines
            WHERE company_id=$CO AND book_id=$bid AND equipment_type_id=$tid AND work_model='month' AND tier_from_days=31"));
        if (!$dup) {
            $gate->insert('rate_book_lines', array(
                'book_id' => $bid, 'equipment_type_id' => $tid, 'work_model' => 'month',
                'tier_from_days' => 31, 'tier_to_days' => null,
                'unit_price' => round($base * 8 * 26 * 0.85, 2),
                'min_hire_days' => 30, 'min_hours_per_day' => null,
                'mobilization_fee' => 800.00, 'operator_included' => 1, 'fuel_included' => 0,
                'note' => 'بذرٌ تجريبي — شهري', 'is_deleted' => 0,
                'created_by' => $UID, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ));
            $added++;
        }
    }
}
$o('  + بنودٌ أُضيفت: ' . $added);

// ── ② الحجوزات — على معداتٍ متاحةٍ فعلًا، والحارسُ يتحقق ─────────────────
$o('');
$o('── الحجوزات');
$clients = array();
$q = mysqli_query($conn, "SELECT id, client_name FROM clients WHERE company_id=$CO AND COALESCE(is_deleted,0)=0 AND status='نشط' LIMIT 4");
while ($r = mysqli_fetch_assoc($q)) { $clients[] = $r; }

$windows = array(
    array('+7 days',  '+45 days',  'مؤكَّد',  'منجم الطلحة — توسعةُ الحفر'),
    array('+20 days', '+110 days', 'مبدئي', 'مشروعُ الطريق الشرقي'),
    array('+60 days', '+240 days', 'مؤكَّد',  'عقدُ نقلٍ سنويٌّ — نهر النيل'),
    array('+3 days',  '+33 days',  'مبدئي', 'زيارةُ تقييمٍ — عميلٌ محتمل'),
);

$made = 0; $skipped = 0; $ci = 0;
foreach ($windows as $wi => $w) {
    list($s, $e, $state, $purpose) = $w;
    $sd = date('Y-m-d', strtotime($s));
    $ed = date('Y-m-d', strtotime($e));
    // العطالةُ بالغرض لا بالمعدة: قائمةُ المتاح تتغيّر بين التشغيلين فتُنتقى معدةٌ
    // أخرى ويتكرر البذر. الغرضُ ثابتٌ لكل نافذة — فهو مفتاحُ العطالة الصحيح.
    $pesc = mysqli_real_escape_string($conn, $purpose);
    $dupP = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM fleet_reservations
        WHERE company_id=$CO AND purpose='$pesc' AND COALESCE(is_deleted,0)=0 LIMIT 1"));
    if ($dupP) { $skipped++; continue; }
    // ابحث عن أول معدةٍ متاحةٍ فعلًا في هذه النافذة
    $free = AV::freeEquipment($gate, $sd, $ed, 0, 60);
    if (!count($free)) { $skipped++; $o('  — نافذةٌ بلا معدةٍ متاحة: ' . $sd . ' → ' . $ed); continue; }
    $pick = $free[$wi % count($free)];
    $eqId = (int) $pick['id'];
    if (!AV::isFree($gate, $eqId, $sd, $ed)) { $skipped++; continue; }

    $dup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM fleet_reservations
        WHERE company_id=$CO AND equipment_id=$eqId AND start_date='$sd' AND end_date='$ed'"));
    if ($dup) { $skipped++; continue; }

    $cl = count($clients) ? $clients[$ci++ % count($clients)] : null;
    $gate->insert('fleet_reservations', array(
        'reservation_no' => AV::nextReservationNo($gate, $CO),
        'equipment_id' => $eqId, 'equipment_type_id' => null, 'qty' => 1,
        'client_id' => $cl ? (int) $cl['id'] : null,
        'start_date' => $sd, 'end_date' => $ed, 'state' => $state,
        'purpose' => $purpose, 'note' => 'بذرٌ تجريبي — RENTAL-CORE', 'is_deleted' => 0,
        'created_by' => $UID, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ));
    $made++;
    $o('  + ' . $pick['code'] . ' · ' . $sd . ' → ' . $ed . ' · ' . $state . ' · ' . ($cl ? $cl['client_name'] : 'بلا عميل'));
}
// حجزٌ بالفئة (بلا معدةٍ بعينها) — يُظهر النمطَ الثاني
$dupT = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM fleet_reservations
    WHERE company_id=$CO AND equipment_type_id IS NOT NULL AND note='بذرٌ تجريبي — RENTAL-CORE'"));
if (!$dupT && count($types)) {
    $gate->insert('fleet_reservations', array(
        'reservation_no' => AV::nextReservationNo($gate, $CO),
        'equipment_id' => null, 'equipment_type_id' => (int) $types[0]['id'], 'qty' => 6,
        'client_id' => count($clients) ? (int) $clients[0]['id'] : null,
        'start_date' => date('Y-m-d', strtotime('+90 days')),
        'end_date' => date('Y-m-d', strtotime('+270 days')),
        'state' => 'مبدئي', 'purpose' => 'مناقصةٌ قيد التقديم — حجزُ سعةٍ بالفئة',
        'note' => 'بذرٌ تجريبي — RENTAL-CORE', 'is_deleted' => 0,
        'created_by' => $UID, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ));
    $made++;
    $o('  + حجزُ فئةٍ: ' . $types[0]['type'] . ' × 6');
}
$o('  الحجوزات: أُنشئت=' . $made . ' · تُخطّيت=' . $skipped);

// ── التحقق ────────────────────────────────────────────────────────────────
$o('');
$o('── التحقق');
$g = function ($sql) use ($conn) { $r = @mysqli_query($conn, $sql); return $r ? (int) reset(mysqli_fetch_assoc($r)) : -1; };
$o('  دفاتر: ' . $g("SELECT COUNT(*) c FROM rate_books WHERE company_id=$CO AND COALESCE(is_deleted,0)=0"));
$o('  بنود : ' . $g("SELECT COUNT(*) c FROM rate_book_lines WHERE company_id=$CO AND COALESCE(is_deleted,0)=0"));
$o('  حجوزات: ' . $g("SELECT COUNT(*) c FROM fleet_reservations WHERE company_id=$CO AND COALESCE(is_deleted,0)=0"));
$o('');
$o('  اختبارُ أفضل سعر (حفار · ساعة · 45 يومًا):');
$tid = 0; foreach ($types as $t) { if ($t['type'] === 'حفار') { $tid = (int) $t['id']; } }
if ($tid) {
    $best = RB::bestRate($gate, $tid, 'hour', 45);
    $o('    ' . ($best === null ? '✗ لا سعر'
        : ($best['unit_price'] . ' ' . $best['currency'] . ' · شريحة '
           . RB::tierLabel($best['tier_from_days'], $best['tier_to_days'])
           . ' · أيامٌ مفوترة ' . $best['billable_days'])));
    $best2 = RB::bestRate($gate, $tid, 'hour', 5);
    $o('    (5 أيام): ' . ($best2 === null ? '✗' : $best2['unit_price'] . ' · مفوترة '
        . $best2['billable_days'] . (!empty($best2['min_applied']) ? ' ← رُفعت بالحد الأدنى' : '')));
}
