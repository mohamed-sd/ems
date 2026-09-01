<?php
/**
 * 2028_03_06_govui_landing_page_classify.php — تصنيفُ صفحاتِ الهبوطِ (§8)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: update:nav_placements(placement_type ⇒ LANDING_PAGE) + log:govui_wiring_log
 *
 * ◆ **الشاهدُ من الورقةِ نفسِها**: تسعةَ عشرَ هدفًا مجموعتُها في الدليلِ
 *   **«اللوحة — خارج الدورة»** — والدليلُ يقول بنصِّه **«خارج الدورة»**،
 *   فهي صفحةُ هبوطِ المساحةِ لا مرحلةٌ في دورتِها. وخلطُها بـ`MENU_ITEM`
 *   يجعل قارئَ «الترتيبِ المستنديِّ» يعُدُّ اللوحةَ مرحلةً أولى وهي ليست منه.
 *
 * ◆ ⛔ **ولا يُنزَع رابطٌ ولا يُخفى**: `LANDING_PAGE` **يظهر** في السايدبار —
 *   فهو بابُ المساحة. والتصنيفُ يغيّر **قراءةَ الصنفِ** لا **قرارَ الظهور**،
 *   والمُصيِّرُ يقرأ `nav_items` لا `placement_type`. (قِيس: صفرُ فرقٍ في
 *   تعدادِ الروابطِ المُصيَّرةِ بعدَ التطبيق.)
 *
 * ◆ **وابنُ التبويبِ لا يُنزَع من السايدبارِ هنا**: ورقةُ الدليلِ نفسُها تقول
 *   «ترتيبها في هذا الشيت هو ترتيبها في الـSidebar» وتَعُدُّ السجلاتِ التابعةَ
 *   ضمنَها — فظهورُها **حكمُ الملفِّ الحاكمِ** لا انحرافٌ يُصحَّح (§2).
 *
 * التشغيل: php database/migrations/2028_03_06_govui_landing_page_classify.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$log = $conn->prepare("INSERT INTO govui_wiring_log
    (target_id, old_route, old_screen_id, old_type, new_route, new_screen_id, new_type, witness)
    VALUES (?,?,?,?,?,?,?,?)");
if (!$log) { exit("⛔ prepare: {$conn->error}\n"); }

$q = $conn->query("SELECT g.target_id, g.canonical_group_label, p.route, p.screen_id, p.placement_type
                     FROM govui_target_registry g
                     JOIN nav_placements p ON p.target_id = g.target_id
                    WHERE g.surface_type = 'LANDING_PAGE' AND p.placement_type <> 'LANDING_PAGE'");
if (!$q) { exit("⛔ استعلام: {$conn->error} — شغّلْ tools/govui_target_registry.php أوّلًا\n"); }
$n = 0;
while ($x = $q->fetch_assoc()) {
    $w = 'شاهدُ الورقة: مجموعةُ الدليلِ «' . $x['canonical_group_label'] . '» — «خارج الدورة» نصًّا ⇒ LANDING_PAGE (§8)';
    $newType = 'LANDING_PAGE';
    $st = $conn->prepare("UPDATE nav_placements SET placement_type = 'LANDING_PAGE' WHERE target_id = ?");
    $st->bind_param('s', $x['target_id']);
    if (!$st->execute()) { exit("⛔ {$x['target_id']}: {$conn->error}\n"); }
    $st->close();
    $log->bind_param('ssssssss', $x['target_id'], $x['route'], $x['screen_id'], $x['placement_type'],
        $x['route'], $x['screen_id'], $newType, $w);
    $log->execute();
    printf("  ✔ %-16s %s\n", $x['target_id'], $x['route']);
    $n++;
}
echo "صُنِّف: {$n} صفحةَ هبوط\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
