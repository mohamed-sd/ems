<?php
/**
 * 2027_08_14_nav_ten_groups.php — السايدبار يُعاد تعريفُه في عشرِ مجموعاتٍ بأيقونات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ المالك (2026-08-17)**: «ألّا يزيد عددُ المجموعاتِ عن عشرة · بأيقوناتٍ
 *   احترافيةٍ لكلِّ مجموعة · وتوزيعُ كلِّ الروابطِ منطقيًّا داخلَ العشرة ·
 *   والهدفُ الأولُ والأخيرُ تسهيلُ الوصولِ وتنظيمُه · **والصفحةُ التي تظهر في
 *   أكثرِ من إدارةٍ تكون في المكانِ نفسِه دائمًا** — كالرئيسيةِ والمراسلات».
 *
 * ◆ **المقيسُ قبلَ التعديل**: ٣٩٤ رأسَ مجموعةٍ على تسعَ عشرةَ إدارةً — وسطيُّها
 *   ٢١ رأسًا للإدارةِ الواحدة، وأعلاها ٤٥ (مدير الإدارة المالية). والرؤوسُ
 *   تُولَّد من **مصدرين يطبعان بلوكَين منفصلَين** (`printUxuiCanonicalNav`
 *   و`printUxuiCurrentNav`) فالمجموعةُ الواحدةُ قد تُطبع مرتين.
 *
 * ◆ **البنيةُ الجديدةُ طبقتان**: عشرُ رؤوسٍ قابلةٍ للطيِّ (التوجُّه)، وتحتَها
 *   أقسامٌ بعناوينِ `nav_canonical.group_name` الدقيقةِ نفسِها (المسح). فلا
 *   يُفقد المعنى الدقيقُ المبنيُّ عبرَ الجولاتِ السابقة — يهبط درجةً لا غير.
 *
 * ◆ **وحدُّ التسعةِ (ف٧-٢) محفوظٌ حيث ينفع**: أكبرُ قسمٍ مقروءٍ بعدَ التوزيع
 *   **سبعةُ روابط**. ولا يجتمع الحدَّان حرفيًّا على رأسِ الطيّ: أكبرُ دورٍ
 *   ٩٣ رابطًا و١٠×٩=٩٠ — **تناقضٌ رياضيٌّ مُعلَنٌ لا مطويّ**، والمختارُ منهما
 *   ما ينفع المستخدم. وU9 تقيس القسمَ المقروءَ وتُبلِّغ حجمَ الرأسِ خبرًا.
 *
 * ◆ **ولا يُمسُّ شيءٌ من الحقيقة**: لا `route` ولا `canonical_ar` ولا `level_no`
 *   ولا `sort_no` ولا صلاحيةٌ ولا صفُّ `nav_items`. **صفرُ فقد** — الجدولان
 *   الجديدان طبقةُ تبويبٍ فوقَ ما هو قائم، ونزعُهما يُعيد السلوكَ القديمَ حرفًا.
 *
 * ◆ **والرجوعُ بابان**: `--revert` يُسقط الجدولين · و`EMS_NAV_TEN=off` في البيئة
 *   يُعطِّل الطبقةَ بلا لمسِ قاعدةٍ ولا نشرِ كود.
 *
 * التشغيل:  php database/migrations/2027_08_14_nav_ten_groups.php [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/nav_groups.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$revert = in_array('--revert', $argv, true);
if ($revert) {
    foreach (array('nav_route_group', 'nav_group_taxonomy') as $t) {
        $ok = $conn->query("DROP TABLE IF EXISTS `{$t}`");
        echo ($ok ? '✔ أُسقط ' : '✘ تعذّر إسقاط ') . $t . ($ok ? '' : ' — ' . $conn->error) . "\n";
    }
    echo "الرجوعُ تمّ — السايدبار يعود إلى مصدرِه السابقِ حرفًا.\n";
    exit(0);
}

/* ── ① جدولُ التبويب ─────────────────────────────────────────────────────── */
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `nav_group_taxonomy` (
  `code` VARCHAR(24) NOT NULL COMMENT 'رمزٌ ثابتٌ — يعيش في data-group-key فتُحفظ حالةُ الطيّ',
  `name_ar` VARCHAR(64) NOT NULL COMMENT 'اسمُ المجموعةِ كما يُقرأ في السايدبار',
  `icon` VARCHAR(64) NOT NULL COMMENT 'أيقونةُ الرأسِ — مُتحقَّقٌ وجودُها في مكتبةِ الأيقوناتِ المحمَّلة',
  `sort_no` TINYINT NOT NULL COMMENT 'ترتيبُ الظهور: أنا ← ما ينتظرني ← عملي ← المجالات ← الرقابة ← البصيرة ← الضبط',
  `open_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'مفتوحةٌ عند أولِ زيارةٍ فقط — واختيارُ المستخدمِ يغلبها',
  PRIMARY KEY (`code`),
  UNIQUE KEY `uq_sort` (`sort_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='العشرُ مجموعاتٍ — سقفُ التبويبِ في السايدبار لكلِّ إدارة'");
if (!$ok) { exit("CREATE taxonomy فشل: {$conn->error}\n"); }

/* ── ② نسبةُ كلِّ مسارٍ إلى مجموعتِه، بسندِها ────────────────────────────── */
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `nav_route_group` (
  `route` VARCHAR(160) NOT NULL COMMENT 'المسارُ مطبَّعًا صغيرًا — بلا ../ ولا استعلامٍ ولا مرساة',
  `group_code` VARCHAR(24) NOT NULL,
  `basis` VARCHAR(190) NOT NULL COMMENT 'سندُ الحكم: PIN · GROUP:… · LEVEL:… · DIR:… · FALLBACK',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`route`),
  KEY `ix_nrg_group` (`group_code`),
  CONSTRAINT `fk_nrg_group` FOREIGN KEY (`group_code`) REFERENCES `nav_group_taxonomy` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='المسارُ ⇄ مجموعتُه الواحدة — والمشترَكُ بين الإداراتِ في مكانٍ واحدٍ دائمًا'");
if (!$ok) { exit("CREATE route_group فشل: {$conn->error}\n"); }

/* ── ③ بذرُ العشرةِ من التعريفِ الحيِّ (مصدرٌ واحدٌ لا نسختان) ───────────── */
$st = $conn->prepare("INSERT INTO nav_group_taxonomy (code,name_ar,icon,sort_no,open_default)
                      VALUES (?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar), icon=VALUES(icon),
                                              sort_no=VALUES(sort_no), open_default=VALUES(open_default)");
foreach (ems_nav_groups_def() as $code => $g) {
    $st->bind_param('sssii', $code, $g['name'], $g['icon'], $g['sort'], $g['open']);
    if (!$st->execute()) { exit("بذرُ «{$code}» فشل: {$st->error}\n"); }
}
$st->close();
echo "✔ العشرُ مجموعاتٍ بُذرت\n";

/* ── ④ جمعُ كلِّ مسارٍ يعرفه النظامُ من مصادرِه الأربعة ──────────────────── */
$norm = function ($r) {
    $r = preg_replace('~^(\.\./)+~', '', trim((string) $r));
    $r = preg_replace('/[?#].*$/u', '', $r);
    return mb_strtolower(trim($r, '/'));
};
$routes = array(); /* route => array(level, group) */
$res = $conn->query("SELECT route, level_no, group_name FROM nav_canonical");
while ($res && ($r = $res->fetch_assoc())) {
    $k = $norm($r['route']);
    if ($k !== '') { $routes[$k] = array((int) $r['level_no'], (string) $r['group_name']); }
}
foreach (array(
    "SELECT route FROM nav_canonical_current",
    "SELECT route FROM nav_items",
    "SELECT code AS route FROM modules WHERE code IS NOT NULL AND code <> ''",
) as $q) {
    $res = $conn->query($q);
    while ($res && ($r = $res->fetch_assoc())) {
        $k = $norm($r['route']);
        if ($k !== '' && !isset($routes[$k])) { $routes[$k] = array(0, ''); }
    }
}
ksort($routes);

/* ── ⑤ الحكمُ بالدالةِ الحيّةِ نفسِها التي يحتكم إليها المُصيِّر ────────────── */
$st = $conn->prepare("INSERT INTO nav_route_group (route,group_code,basis) VALUES (?,?,?)
                      ON DUPLICATE KEY UPDATE group_code=VALUES(group_code), basis=VALUES(basis)");
$tally = array(); $bas = array(); $n = 0;
foreach ($routes as $route => $meta) {
    list($code, $basis) = ems_nav_group_for_route($route, $meta[0], $meta[1]);
    $st->bind_param('sss', $route, $code, $basis);
    if (!$st->execute()) { exit("نسبةُ «{$route}» فشلت: {$st->error}\n"); }
    $n++;
    $tally[$code] = (isset($tally[$code]) ? $tally[$code] : 0) + 1;
    $bk = preg_replace('/:.*$/', '', $basis);
    $bas[$bk] = (isset($bas[$bk]) ? $bas[$bk] : 0) + 1;
}
$st->close();

echo "✔ نُسِب {$n} مسارًا\n\n";
foreach (ems_nav_groups_def() as $code => $g) {
    printf("  %2d  %-12s %-24s %d\n", $g['sort'], $code, $g['name'], isset($tally[$code]) ? $tally[$code] : 0);
}
echo "\nالسند: ";
foreach ($bas as $k => $c) { echo "{$k}={$c} "; }
echo "\n\nتمّ. التعطيلُ بـEMS_NAV_TEN=off · والرجوعُ بـ--revert.\n";
