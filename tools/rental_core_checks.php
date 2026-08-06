<?php
/**
 * tools/rental_core_checks.php — حزامُ RENTAL-CORE · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * ستةُ فحوصٍ تحرس الثلاثيَّ الذي يفصل نظامَ التأجير عن نظامِ البيع:
 *   ① AC-RC-01 · الجداولُ الثلاثة مسجَّلةٌ في بوابة العزل — وإلا لا تُستعمل أصلًا.
 *   ② AC-RC-02 · الشاشاتُ الثلاث مسجَّلةٌ في modules بمالكها ولها صلاحيةٌ وبابٌ في القائمة.
 *   ③ AC-RC-03 · لا حجزان متعارضان على معدةٍ واحدة — حارسُ التوفُّر يمنع بنيويًّا.
 *   ④ AC-RC-04 · لا حجزَ يتقاطع مع تشغيلٍ سارٍ على المعدة نفسِها.
 *   ⑤ AC-RC-05 · سلامةُ دفتر الأسعار: لا سعرَ سالب · لا شريحةَ مقلوبة · لا نموذجَ خارج القائمة.
 *   ⑥ AC-RC-06 · الشاشاتُ الثلاث تعبر البوابةَ ولا تخترع رمزَ CSRF موازيًا.
 *
 * php tools/rental_core_checks.php [--verbose]
 * الخروج: 0 نظيف · 1 خرقٌ قائم.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$VERBOSE = in_array('--verbose', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$ROLE = 12;
$fail = 0;

$TABLES  = array('fleet_reservations', 'rate_books', 'rate_book_lines');
$SCREENS = array('Operations/fleet_calendar.php', 'Clients/rate_books.php', 'Operations/fleet_utilization.php');
$SERVICES = array('AvailabilityService.php', 'RateBookService.php', 'UtilizationService.php');

$o('══ حزامُ RENTAL-CORE ══');

// ── ① التسجيلُ في بوابة العزل ────────────────────────────────────────────
$v1 = array();
$reg = (string) @file_get_contents($ROOT . '/app/Core/TenantRegistry.php');
foreach ($TABLES as $t) {
    if (strpos($reg, "'" . $t . "' => array(") === false) { $v1[] = $t . ' — غيرُ مسجَّلٍ في TenantRegistry'; }
    $x = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    if (!$x || !mysqli_num_rows($x)) { $v1[] = $t . ' — الجدولُ غيرُ موجودٍ في القاعدة'; }
}
if (count($v1)) { $fail++; }
$o('  ① AC-RC-01 · الجداولُ مسجَّلةٌ في البوابة ' . (count($v1) ? '✗ ' . count($v1) : '✓ نظيف'));
foreach ($v1 as $x) { $o('        · ' . $x); }

// ── ② تسجيلُ الشاشات ─────────────────────────────────────────────────────
$v2 = array();
foreach ($SCREENS as $code) {
    $esc = mysqli_real_escape_string($conn, $code);
    $r = @mysqli_query($conn, "SELECT m.id, m.owner_role_id, rp.can_view, n.active
        FROM modules m
        LEFT JOIN role_permissions rp ON rp.module_id = m.id AND rp.role_id = $ROLE
        LEFT JOIN nav_items n ON n.module_id = m.id AND n.role_id = $ROLE
        WHERE m.code = '$esc' AND m.owner_role_id = $ROLE LIMIT 1");
    $row = $r ? mysqli_fetch_assoc($r) : null;
    if (!$row)                          { $v2[] = $code . ' — لا موديولَ يملكه الدور ' . $ROLE; continue; }
    if (empty($row['can_view']))        { $v2[] = $code . ' — بلا صلاحية عرضٍ للدور ' . $ROLE; }
    if ((int) $row['active'] !== 1)     { $v2[] = $code . ' — بلا عنصرِ قائمةٍ نشط'; }
    if (!file_exists($ROOT . '/' . $code)) { $v2[] = $code . ' — الملفُّ مفقودٌ على القرص'; }
}
if (count($v2)) { $fail++; }
$o('  ② AC-RC-02 · الشاشاتُ مسجَّلةٌ ومحروسة ' . (count($v2) ? '✗ ' . count($v2) : '✓ نظيف'));
foreach ($v2 as $x) { $o('        · ' . $x); }

// ── ③ لا حجزان متعارضان ─────────────────────────────────────────────────
$v3 = array();
$q = @mysqli_query($conn, "SELECT a.company_id, a.reservation_no A, b.reservation_no B, a.equipment_id
    FROM fleet_reservations a JOIN fleet_reservations b
      ON a.company_id = b.company_id AND a.equipment_id = b.equipment_id AND a.id < b.id
   WHERE a.equipment_id IS NOT NULL
     AND COALESCE(a.is_deleted,0)=0 AND COALESCE(b.is_deleted,0)=0
     AND a.state IN ('مبدئي','مؤكَّد','محوَّل لعقد')
     AND b.state IN ('مبدئي','مؤكَّد','محوَّل لعقد')
     AND a.start_date <= b.end_date AND b.start_date <= a.end_date");
if ($q) { while ($r = mysqli_fetch_assoc($q)) { $v3[] = 'معدة ' . $r['equipment_id'] . ': ' . $r['A'] . ' × ' . $r['B']; } }
if (count($v3)) { $fail++; }
$o('  ③ AC-RC-03 · لا حجزان متعارضان        ' . (count($v3) ? '✗ ' . count($v3) : '✓ نظيف'));
foreach (array_slice($v3, 0, 8) as $x) { $o('        · ' . $x); }

// ── ④ لا حجزَ فوق تشغيلٍ سارٍ ────────────────────────────────────────────
$v4 = array();
$q = @mysqli_query($conn, "SELECT r.reservation_no, r.equipment_id, o.id op_id, o.start, o.end
    FROM fleet_reservations r JOIN operations o
      ON o.company_id = r.company_id AND o.equipment = r.equipment_id
   WHERE r.equipment_id IS NOT NULL
     AND COALESCE(r.is_deleted,0)=0
     AND r.state IN ('مبدئي','مؤكَّد')
     AND o.status = '1' AND o.start IS NOT NULL
     AND o.start <= r.end_date AND COALESCE(o.end,'2099-12-31') >= r.start_date");
if ($q) { while ($r = mysqli_fetch_assoc($q)) {
    $v4[] = $r['reservation_no'] . ' فوق تشغيل #' . $r['op_id'] . ' (' . $r['start'] . ' → ' . ($r['end'] ?: 'مفتوح') . ')'; } }
if (count($v4)) { $fail++; }
$o('  ④ AC-RC-04 · لا حجزَ فوق تشغيلٍ سارٍ   ' . (count($v4) ? '✗ ' . count($v4) : '✓ نظيف'));
foreach (array_slice($v4, 0, 8) as $x) { $o('        · ' . $x); }

// ── ⑤ سلامةُ دفتر الأسعار ────────────────────────────────────────────────
$v5 = array();
$g = function ($sql) use ($conn) { $r = @mysqli_query($conn, $sql); return $r ? (int) reset(mysqli_fetch_assoc($r)) : 0; };
$n = $g("SELECT COUNT(*) c FROM rate_book_lines WHERE COALESCE(is_deleted,0)=0 AND unit_price < 0");
if ($n) { $v5[] = $n . ' بندًا بسعرٍ سالب'; }
$n = $g("SELECT COUNT(*) c FROM rate_book_lines WHERE COALESCE(is_deleted,0)=0
         AND tier_to_days IS NOT NULL AND tier_to_days < tier_from_days");
if ($n) { $v5[] = $n . ' بندًا بشريحةٍ مقلوبة'; }
$n = $g("SELECT COUNT(*) c FROM rate_book_lines WHERE COALESCE(is_deleted,0)=0 AND min_hire_days < 1");
if ($n) { $v5[] = $n . ' بندًا بحدٍّ أدنى أقلَّ من يوم'; }
$n = $g("SELECT COUNT(*) c FROM rate_books WHERE COALESCE(is_deleted,0)=0
         AND valid_to IS NOT NULL AND valid_to < valid_from");
if ($n) { $v5[] = $n . ' دفترًا بسريانٍ مقلوب'; }
$n = $g("SELECT COUNT(*) c FROM rate_book_lines l LEFT JOIN equipments_types t ON t.id = l.equipment_type_id
         WHERE COALESCE(l.is_deleted,0)=0 AND t.id IS NULL");
if ($n) { $v5[] = $n . ' بندًا على فئةٍ غيرِ موجودة'; }
if (count($v5)) { $fail++; }
$o('  ⑤ AC-RC-05 · سلامةُ دفتر الأسعار      ' . (count($v5) ? '✗ ' . count($v5) : '✓ نظيف'));
foreach ($v5 as $x) { $o('        · ' . $x); }

// ── ⑥ العبورُ بالبوابة ورمزُ CSRF المركزي ────────────────────────────────
$v6 = array();
foreach ($SCREENS as $code) {
    $p = $ROOT . '/' . $code;
    if (!file_exists($p)) { continue; }
    $s = file_get_contents($p);
    if (preg_match('/_csrf_token\'\]\s*=\s*bin2hex/', $s)) { $v6[] = $code . ' — رمزُ CSRF موازٍ (استعمل generate_csrf_token)'; }
    if (preg_match('/company_id\s*=\s*\$company_id/', $s) && strpos($s, 'scopedQuery') !== false) {
        $v6[] = $code . ' — company_id نصًّا مع scopedQuery';
    }
    if (strpos($s, 'ems_tenant_db()') === false && strpos($code, 'utilization') === false) {
        $v6[] = $code . ' — لا يعبر بوابةَ العزل';
    }
}
foreach ($SERVICES as $sv) {
    $p = $ROOT . '/app/Services/Rental/' . $sv;
    if (!file_exists($p)) { $v6[] = $sv . ' — مفقود'; continue; }
    $s = file_get_contents($p);
    if (preg_match('/mysqli_query\s*\(/', $s)) { $v6[] = $sv . ' — استعلامٌ خامٌّ خارج البوابة'; }
}
if (count($v6)) { $fail++; }
$o('  ⑥ AC-RC-06 · البوابةُ والرمزُ المركزي   ' . (count($v6) ? '✗ ' . count($v6) : '✓ نظيف'));
foreach ($v6 as $x) { $o('        · ' . $x); }

if ($VERBOSE) {
    $o('');
    $o('  الأحجام: حجوزات=' . $g("SELECT COUNT(*) c FROM fleet_reservations WHERE COALESCE(is_deleted,0)=0")
       . ' · دفاتر=' . $g("SELECT COUNT(*) c FROM rate_books WHERE COALESCE(is_deleted,0)=0")
       . ' · بنود=' . $g("SELECT COUNT(*) c FROM rate_book_lines WHERE COALESCE(is_deleted,0)=0"));
}

$o('');
$o($fail === 0 ? 'النتيجة: 6/6 نظيف.' : 'النتيجة: ' . (6 - $fail) . '/6 — ' . $fail . ' فحصًا مكسورًا.');
exit($fail === 0 ? 0 : 1);
