<?php
/**
 * 2027_09_03_nav_reference_standard_bridge.php
 *   المعيارُ المرجعيُّ للترتيبِ وجسرُه — INJ-FIX-01 · GAP-19 موسَّعةً بـ NF-04
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يُبنى هنا**: سجلُّ المعيارِ المرجعيِّ (٣ طبقاتٍ × ١٧ مرحلةً × ٥ رتبٍ
 *   مستندية) — وهو **جدولُ الجسرِ** الذي يطلبه NF-04 — ثم **مطابقةُ مراحلِ
 *   دفترِ الدورةِ الـ١١١ به**، وما لا يُطابق يُوسَم `UNMAPPED` صراحةً.
 *
 * ◆ **وما لا يُبنى هنا — وسببُه مقيس**: NF-04 يشترط أن **يُشتقَّ** ترتيبُ
 *   السايدبارِ ولا يُكتب، و«أيُّ رقمِ ترتيبٍ يُكتب بيدٍ يُرسِّب الفحص». والاشتقاقُ
 *   **غيرُ ممكنٍ اليوم**: من ١٦٢٩ رابطًا حيًّا **٢٥٥ فقط (١٥٫٧٪)** له صفُّ دورةٍ
 *   واحدُ الطبقةِ والمرحلة · و٣٥٦ (٢١٫٩٪) له صفٌّ بطبقاتٍ متضاربة ·
 *   و**١٠١٨ (٦٢٫٥٪) بلا صفِّ دورةٍ أصلًا**.
 *   ⇒ **فدفترُ الدورةِ والسايدبارُ الحيُّ مجتمعان مختلفان** يتقاطعان في الثلث.
 *   واشتقاقُ ترتيبِ ١٥٫٧٪ وكتابةُ ٨٤٫٣٪ بيدٍ **يُرسِّب بوابةَ NF-04 نفسَها**.
 *
 * ◆ **فالشرطُ السابقُ يُعلَن**: لا يُفعَّل الاشتقاقُ قبلَ أن تبلغ التغطيةُ عتبتَها —
 *   وهو عملُ NF-03 (عقدُ المعرِّفات) و NF-01 (البناءُ الناقص) لا عملُ هذه الهجرة.
 *
 * ◆ ولا يُمَسُّ `nav_items.sort_order` — ولا رابطٌ واحدٌ يتغيّر موضعُه.
 *
 * التشغيل:  php database/migrations/2027_09_03_nav_reference_standard_bridge.php
 * الرجوع :  php database/migrations/2027_09_03_nav_reference_standard_bridge.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_nav_stage_bridge`");
    $conn->query("DROP TABLE IF EXISTS `gov_nav_reference_standard`");
    echo "↺ أُسقط سجلُّ المعيارِ المرجعيِّ وجسرُه\n";
    exit(0);
}

/* ══ ① سجلُّ المعيارِ المرجعيّ — من دفترِ الملحقِ INJ-FIX-02 ورقة ٧ ═══════ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_nav_reference_standard` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `layer_no`    TINYINT UNSIGNED NOT NULL COMMENT '1 مركز العمل · 2 دورة الإدارة · 3 المرجع والإدارة',
    `layer_name`  VARCHAR(60)  NOT NULL,
    `stage_order` TINYINT UNSIGNED NOT NULL,
    `stage_name`  VARCHAR(160) NOT NULL,
    UNIQUE KEY `uq_stage` (`layer_no`,`stage_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='INJ-FIX-02 NF-04 — المعيارُ المرجعيُّ للترتيب: طبقةٌ ثم مرحلةٌ ثم رتبةٌ مستندية'");

$STD = array(
    array(1, 'مركز العمل', 1, 'الاعتمادات الواردة'),
    array(1, 'مركز العمل', 2, 'البلاغات والمهل'),
    array(1, 'مركز العمل', 3, 'لوحة الإدارة'),
    array(2, 'دورة الإدارة', 1, 'تأسيس السجل أو الكيان'),
    array(2, 'دورة الإدارة', 2, 'التأهيل والوثائق'),
    array(2, 'دورة الإدارة', 3, 'التعاقد والتغطية'),
    array(2, 'دورة الإدارة', 4, 'التخطيط والتخصيص'),
    array(2, 'دورة الإدارة', 5, 'التنفيذ اليومي'),
    array(2, 'دورة الإدارة', 6, 'الاعتماد'),
    array(2, 'دورة الإدارة', 7, 'الأثر المالي والتسوية'),
    array(2, 'دورة الإدارة', 8, 'الفوترة أو الصرف'),
    array(2, 'دورة الإدارة', 9, 'المطابقة والانحراف'),
    array(2, 'دورة الإدارة', 10, 'الإقفال والتقييم'),
    array(3, 'المرجع والإدارة', 1, 'البيانات المرجعية والإعدادات'),
    array(3, 'المرجع والإدارة', 2, 'الميزانية ومؤشرات الأداء'),
    array(3, 'المرجع والإدارة', 3, 'الحوكمة والضوابط'),
    array(3, 'المرجع والإدارة', 4, 'التقارير والتحليلات'),
);
$st = $conn->prepare("INSERT INTO `gov_nav_reference_standard`
        (`layer_no`,`layer_name`,`stage_order`,`stage_name`) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE `layer_name`=VALUES(`layer_name`), `stage_name`=VALUES(`stage_name`)");
foreach ($STD as $s) { $st->bind_param('isis', $s[0], $s[1], $s[2], $s[3]); $st->execute(); }
$st->close();
echo "① سجلُّ المعيارِ المرجعيّ: " . count($STD) . " مرحلةً في 3 طبقات\n";

/* ══ ② جسرُ مراحلِ الدفترِ بالمعيار ══════════════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_nav_stage_bridge` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `cycle_layer`   VARCHAR(60)  NOT NULL,
    `cycle_stage`   VARCHAR(160) NOT NULL,
    `screens`       SMALLINT UNSIGNED NOT NULL,
    `std_layer_no`  TINYINT UNSIGNED NULL,
    `std_stage_order` TINYINT UNSIGNED NULL,
    `std_stage_name`  VARCHAR(160) NULL,
    `match_kind`    VARCHAR(24)  NOT NULL COMMENT 'EXACT | TOKEN | LAYER_ONLY | UNMAPPED',
    UNIQUE KEY `uq_cycle` (`cycle_layer`,`cycle_stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='INJ-FIX-02 NF-04 — جسرُ مراحلِ دفترِ الدورةِ برؤوسِ المعيارِ المرجعيّ'");

/** رموزُ المعنى العربيِّ — تُنزع أل التعريفِ ويُوحَّد رسمُ ا/ه/ي */
function nb_toks($s)
{
    $s = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}]/u', '', (string) $s);
    $s = strtr($s, array('أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي'));
    $s = preg_replace('/[^\p{Arabic}\s]+/u', ' ', $s);
    $out = array();
    foreach (preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) as $w) {
        $w = preg_replace('/^(وال|بال|فال|كال|لل|ال)/u', '', $w);
        if (mb_strlen($w) >= 3 && !in_array($w, array('في', 'من', 'علي', 'عن', 'او'), true)) { $out[$w] = 1; }
    }
    return array_keys($out);
}
$stdToks = array();
foreach ($STD as $s) { $stdToks[] = array('l' => $s[0], 'o' => $s[2], 'n' => $s[3], 't' => nb_toks($s[3])); }
$layerNo = array('مركز العمل' => 1, 'دورة الإدارة' => 2, 'المرجع والإدارة' => 3);

$rows = array();
$q = $conn->query("SELECT layer_name, stage_name, COUNT(*) n FROM `gov_screen_cycle`
                    WHERE COALESCE(stage_name,'') <> '' GROUP BY layer_name, stage_name");
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }

$st = $conn->prepare("INSERT INTO `gov_nav_stage_bridge`
        (`cycle_layer`,`cycle_stage`,`screens`,`std_layer_no`,`std_stage_order`,`std_stage_name`,`match_kind`)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE `screens`=VALUES(`screens`), `std_layer_no`=VALUES(`std_layer_no`),
            `std_stage_order`=VALUES(`std_stage_order`), `std_stage_name`=VALUES(`std_stage_name`),
            `match_kind`=VALUES(`match_kind`)");
$kind = array('EXACT' => 0, 'TOKEN' => 0, 'LAYER_ONLY' => 0, 'UNMAPPED' => 0);
$screensBy = array('EXACT' => 0, 'TOKEN' => 0, 'LAYER_ONLY' => 0, 'UNMAPPED' => 0);

foreach ($rows as $r) {
    $L  = (string) $r['layer_name'];
    $S  = (string) $r['stage_name'];
    $n  = (int) $r['n'];
    $ln = $layerNo[$L] ?? null;
    $mLayer = null; $mOrder = null; $mName = null; $mk = 'UNMAPPED';

    foreach ($stdToks as $s) {                       /* تطابقٌ حرفيّ */
        if ($s['n'] === $S) { $mLayer = $s['l']; $mOrder = $s['o']; $mName = $s['n']; $mk = 'EXACT'; break; }
    }
    if ($mk === 'UNMAPPED') {                        /* تداخلُ معنًى داخلَ الطبقةِ نفسِها */
        $ct = nb_toks($S); $best = null; $bn = 0;
        foreach ($stdToks as $s) {
            if ($ln !== null && $s['l'] !== $ln) { continue; }
            $c = count(array_intersect($ct, $s['t']));
            if ($c > $bn) { $bn = $c; $best = $s; }
        }
        if ($best !== null && $bn >= 2) {
            $mLayer = $best['l']; $mOrder = $best['o']; $mName = $best['n']; $mk = 'TOKEN';
        } elseif ($ln !== null) {
            $mLayer = $ln; $mk = 'LAYER_ONLY';        /* الطبقةُ معروفةٌ والمرحلةُ لا */
        }
    }
    $st->bind_param('ssiiiss', $L, $S, $n, $mLayer, $mOrder, $mName, $mk);
    if (!$st->execute()) { echo "  ✘ {$S}: {$st->error}\n"; continue; }
    $kind[$mk]++; $screensBy[$mk] += $n;
}
$st->close();

echo "② الجسر: " . count($rows) . " مرحلةً في الدفتر ⇐ المعيار\n";
foreach ($kind as $k => $v) {
    printf("     %-12s %3d مرحلةً · %4d شاشةً\n", $k, $v, $screensBy[$k]);
}

/* ══ ③ تغطيةُ الاشتقاقِ — الشرطُ السابقُ لتفعيلِه ═════════════════════════ */
function bnn($r) { return mb_strtolower(basename(preg_replace('/[?\#].*$/', '', (string) $r))); }
$cyc = array();
$q = $conn->query("SELECT screen_file, layer_name, stage_name FROM `gov_screen_cycle`");
while ($q && $x = $q->fetch_assoc()) { $b = bnn($x['screen_file']); if ($b !== '') { $cyc[$b][] = $x; } }
$tot = 0; $hit = 0;
$q = $conn->query("SELECT route FROM `nav_items` WHERE active = 1 AND COALESCE(route,'') <> ''");
while ($q && $x = $q->fetch_row()) {
    $tot++;
    $b = bnn($x[0]);
    if (!isset($cyc[$b])) { continue; }
    $L = array(); $S = array();
    foreach ($cyc[$b] as $r) { $L[$r['layer_name']] = 1; $S[$r['stage_name']] = 1; }
    if (count($L) === 1 && count($S) === 1) { $hit++; }
}
echo "───────────────────────────────────────────────────────────────\n";
printf("③ **تغطيةُ الاشتقاق: %d من %d رابطًا = %.1f%%**\n", $hit, $tot, $tot ? 100 * $hit / $tot : 0);
echo "◆ ولا يُفعَّل الاشتقاقُ عند هذه التغطية: بوابةُ NF-04 تُرسِّب أيَّ رقمِ ترتيبٍ\n";
echo "  يُكتب بيد — واشتقاقُ السُّبعِ وكتابةُ الباقي بيدٍ يُرسِّبها بنفسِه.\n";
echo "◆ **والشرطُ السابقُ عملُ NF-03 و NF-01 لا عملُ هذه الهجرة** — ولم يُمَسَّ\n";
echo "  `nav_items.sort_order`: صفرُ رابطٍ تغيّر موضعُه.\n";
