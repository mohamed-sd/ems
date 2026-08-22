<?php
/**
 * 2027_10_28_injfrd66_w2_positions.php
 *   الموجةُ ② — تصحيحُ مواضعِ الشاشاتِ القائمة (SAL-03 · SAL-21 · SUP-05 · SUP-11)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **أربعةُ مواضعَ يطلبها المرجعُ نصًّا**، وكلُّها **نقلُ مسارٍ بين رؤوسِ
 *   التصنيف** — وهو ممكنٌ اليومَ لأن `nav_route_group` مفتاحُه المسارُ
 *   (بخلافِ «المجموعةِ لكلِّ دور» التي عجز عنها المُصيِّرُ وحُملت على القسم).
 *
 *   | المتطلب | المسار | من ← إلى | نصُّ المرجع |
 *   |---|---|---|---|
 *   | SAL-03 | projects/projects.php            | DAILY → COMMERCIAL | «ينتقل من التشغيل اليومي إلى إدارة العملاء والفرص» |
 *   | SUP-05 | contracts/contract_coverage.php  | REPORTS → SUPPLY   | «لا تحت التقارير» |
 *   | SUP-11 | suppliers/shares_coverage.php    | REPORTS → SUPPLY   | «سجلٌّ تشغيليٌّ … لا تحت التقارير» |
 *   | SAL-21 | clients/products.php             | COMMERCIAL → REPORTS | «ولا تسكن داخل مجموعة العقود» |
 *
 * ◆ **ولماذا REPORTS للبيانات المرجعية؟** لأن `gov_target_nav` — المصدرَ
 *   الحاكمَ — يقرنها بالتقاريرِ بنفسِه: مجموعتُه الخامسةُ للدورِ ١٢ اسمُها
 *   «البيانات المرجعية والتقارير» وتضمُّ `products.php` و`reports.php` معًا.
 *   فالوجهةُ **مشتقّةٌ من الجدولِ لا مختارةٌ بالذوق**، والمطلوبُ نصًّا هو
 *   الخروجُ من مجموعةِ العقودِ وقد تحقّق.
 *
 * ◆ **وفخُّ الدبوس**: `projects/projects.php` أساسُ مجموعتِه `PIN` — مثبَّتٌ
 *   في `ems_nav_group_pins()` بالشفرة. وقاعدةُ المستودع: **`nav_route_group`
 *   (القاعدة) يغلب الدبوسَ (الشفرة)** — فالصفُّ حاضرٌ ويكفي تعديلُه، ولا
 *   تُمسُّ الشفرةُ وإلا تغيَّر الدبوسُ للأدوارِ كلِّها.
 *
 * ◆ **ولا يُنشأ صفٌّ ولا يُحذف** — تعديلُ `group_code` فقط، و«لا حذفَ مسار».
 *
 * التشغيل:  php database/migrations/2027_10_28_injfrd66_w2_positions.php
 * الرجوع :  php database/migrations/2027_10_28_injfrd66_w2_positions.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$MOVES = array(
    array('req' => 'SAL-03', 'route' => 'projects/projects.php',           'to' => 'COMMERCIAL'),
    array('req' => 'SUP-05', 'route' => 'contracts/contract_coverage.php', 'to' => 'SUPPLY'),
    array('req' => 'SUP-11', 'route' => 'suppliers/shares_coverage.php',   'to' => 'SUPPLY'),
    array('req' => 'SAL-21', 'route' => 'clients/products.php',            'to' => 'REPORTS'),
);

$conn->query("CREATE TABLE IF NOT EXISTS `injfrd66_w2_backup` (
    `route` VARCHAR(160) NOT NULL PRIMARY KEY,
    `group_code` VARCHAR(40) NOT NULL,
    `basis` VARCHAR(120) NULL,
    `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if (in_array('--revert', $argv, true)) {
    $n = 0;
    $res = $conn->query("SELECT * FROM `injfrd66_w2_backup`");
    while ($res && ($r = $res->fetch_assoc())) {
        $st = $conn->prepare("UPDATE `nav_route_group` SET `group_code`=?, `basis`=? WHERE LOWER(`route`)=LOWER(?)");
        $st->bind_param('sss', $r['group_code'], $r['basis'], $r['route']);
        $st->execute(); $n += $st->affected_rows; $st->close();
    }
    $conn->query("TRUNCATE `injfrd66_w2_backup`");
    echo "↺ أُعيد {$n} مسارًا إلى مجموعتِه السابقة\n";
    exit(0);
}

/* التصنيفُ المتاحُ — الوجهةُ تُتحقَّق منه ولا تُفترض */
$valid = array();
$q = $conn->query("SELECT DISTINCT group_code FROM `nav_route_group`");
while ($q && ($x = $q->fetch_assoc())) { $valid[$x['group_code']] = true; }

$done = 0; $skip = 0;
foreach ($MOVES as $m) {
    if (!isset($valid[$m['to']])) {
        printf("   ✘ %s — رمزُ مجموعةٍ مجهول «%s» · أُوقف\n", $m['req'], $m['to']);
        exit(1);
    }
    $q = $conn->query("SELECT route, group_code, basis FROM `nav_route_group`
                        WHERE LOWER(route)=LOWER('" . $conn->real_escape_string($m['route']) . "')");
    $cur = $q ? $q->fetch_assoc() : null;
    if (!$cur) { printf("   ⚠ %s — لا صفَّ للمسارِ «%s» · تُخطّى\n", $m['req'], $m['route']); $skip++; continue; }
    if ($cur['group_code'] === $m['to']) { printf("   ○ %s — «%s» في «%s» سلفًا\n", $m['req'], $m['route'], $m['to']); $skip++; continue; }

    $st = $conn->prepare("INSERT IGNORE INTO `injfrd66_w2_backup` (`route`,`group_code`,`basis`) VALUES (?,?,?)");
    $st->bind_param('sss', $cur['route'], $cur['group_code'], $cur['basis']);
    $st->execute(); $st->close();

    $basis = "INJ-FRD-01 · {$m['req']}";
    $st = $conn->prepare("UPDATE `nav_route_group` SET `group_code`=?, `basis`=? WHERE LOWER(`route`)=LOWER(?)");
    $st->bind_param('sss', $m['to'], $basis, $m['route']);
    $st->execute(); $st->close();
    printf("   ✔ %s  %-38s %-10s ← %s\n", $m['req'], $m['route'], $m['to'], $cur['group_code']);
    $done++;
}

printf("\nالحصيلة: %d منقولًا · %d متخطًّى\n", $done, $skip);
ems_migration_recorded(__FILE__, $conn, 0);
echo "✔ اكتمل تصحيحُ المواضع — والقيمُ السابقةُ في injfrd66_w2_backup للرجوع\n";
