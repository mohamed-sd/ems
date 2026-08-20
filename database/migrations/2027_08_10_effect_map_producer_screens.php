<?php
/**
 * 2027_08_10_effect_map_producer_screens.php — المنتِجُ يُشتقُّ من الشاشةِ لا من الوحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العلّةُ التي يُصلحها**: خريطةُ الأثرِ نسبت الواقعةَ إلى مساحةٍ **بجدولِ
 *   ترجمةٍ من `source_module`** — وحدةٌ واحدةٌ لمساحةٍ واحدة. والواقعُ أن
 *   **الوحدةَ تخدم مساحاتٍ عدة**: `projects` و`movement` تُنتجان لمساحتَي
 *   التشغيلِ **والموقعِ** معًا. فظهرت ثلاثُ مساحاتٍ (الموقع · الموارد البشرية ·
 *   أمين المستودع) **بلا واقعةٍ واحدة** — وهو ما لا يصدُق على إدارةٍ تعمل.
 *
 * ◆ **والاشتقاقُ الصادق: مَن ينشر الحدثَ يملك واقعتَه.** فتُمسح الشيفرةُ الحيةُ
 *   عن نداءاتِ `publish(` و`publishFact(` ومفاتيحِ الأحداثِ المصاحبةِ لها،
 *   ثم يُنسب المفتاحُ إلى **المساحةِ المالكةِ للشاشةِ الناشرة** من اللقطة.
 *   وهي قاعدةُ المالكِ حرفًا: «**من يُنتج الواقعةَ يملك شاشتَها**».
 *
 * ◆ **ولا يُمحى ما قِيس أولًا**: الصفوفُ القديمةُ تبقى، ويُضاف إليها المنتِجُ
 *   المشتقُّ من الشاشةِ **صفًّا مستقلًّا** بمصدرِه المكتوب. فمقارنةُ النسبتَين
 *   ممكنةٌ، **ورقمٌ يحلُّ محلَّ رقمٍ بلا أثرٍ للأولِ يُخفي تصحيحَه**.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* مفاتيحُ الأحداثِ الحيةُ — فلا يُنسب مفتاحٌ لا وجودَ له */
$KEYS = array();
$r = $conn->query("SELECT DISTINCT event_key FROM ems_business_events");
while ($r && ($x = $r->fetch_row())) { $KEYS[$x[0]] = 1; }

/* المسارُ ⇒ مساحاتُه المالكة (المساحةُ = المالكُ بعدَ حسمِ المِلكية) */
function nd($s) {
    $s = trim((string) $s);
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
    $s = preg_replace('/^(ا?دارة|قسم|مكتب)\s+/u', '', $s);
    return preg_replace('/\s+/u', ' ', $s);
}
$owns = array();   // route_lc => space
$r = $conn->query("SELECT space_ar, route, owner_dept_ar FROM gov_space_appearances");
while ($r && ($x = $r->fetch_assoc())) {
    $a = nd($x['space_ar']); $b = nd($x['owner_dept_ar']);
    $match = ($a === $b);
    if (!$match) {
        $short = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $long  = mb_strlen($a) <= mb_strlen($b) ? $b : $a;
        $match = (mb_strlen($short) >= 4 && mb_strpos($long, $short) !== false);
    }
    if ($match) { $owns[mb_strtolower($x['route'])] = $x['space_ar']; }
}

/* مسحُ الشيفرةِ الحية: أيُّ شاشةٍ تنشر أيَّ مفتاح */
$screens = array();
$r = $conn->query("SELECT DISTINCT route FROM gov_space_appearances");
while ($r && ($x = $r->fetch_row())) { $screens[] = $x[0]; }

function src_of($path, $ROOT, $depth = 2, &$seen = null) {
    if ($seen === null) { $seen = array(); }
    $real = realpath($path);
    if ($real === false || isset($seen[$real]) || $depth < 0) { return ''; }
    $seen[$real] = 1;
    $s = (string) @file_get_contents($real);
    if ($s === '') { return ''; }
    $all = $s; $dir = dirname($real);
    if (preg_match_all('~(?:require|include)(?:_once)?\s*\(?\s*[\'"]([^\'"]+\.php)[\'"]~i', $s, $m)) {
        foreach ($m[1] as $i) {
            $c = (strpos($i, '/') === 0) ? $ROOT . $i : $dir . '/' . $i;
            $all .= "\n" . src_of($c, $ROOT, $depth - 1, $seen);
        }
    }
    if (preg_match_all('~__DIR__\s*\.\s*[\'"]([^\'"]+\.php)[\'"]~', $s, $m)) {
        foreach ($m[1] as $i) { $all .= "\n" . src_of($dir . '/' . $i, $ROOT, $depth - 1, $seen); }
    }
    if (preg_match_all('~\\\\?App\\\\Services\\\\([A-Za-z0-9_\\\\]+)\s*::~', $s, $m)) {
        foreach ($m[1] as $c) {
            $all .= "\n" . src_of($ROOT . '/app/Services/' . str_replace('\\', '/', $c) . '.php', $ROOT, $depth - 1, $seen);
        }
    }
    return $all;
}

$found = array();   // event_key => [space => 1]
$scanned = 0;
foreach ($screens as $rt) {
    $f = $ROOT . '/' . $rt;
    if (!is_file($f)) { continue; }
    $sn = array();
    $src = src_of($f, $ROOT, 2, $sn);
    if ($src === '' || strpos($src, 'publish') === false) { continue; }
    $scanned++;
    $space = isset($owns[mb_strtolower($rt)]) ? $owns[mb_strtolower($rt)] : '';
    if ($space === '') { continue; }
    foreach (array_keys($KEYS) as $k) {
        if (strpos($src, "'" . $k . "'") !== false || strpos($src, '"' . $k . '"') !== false) {
            $found[$k][$space] = 1;
        }
    }
}

echo "══ المنتِجُ مشتقًّا من الشاشةِ الناشرة ══\n";
echo "  شاشاتٌ فُحصت وفيها نشرٌ: {$scanned}\n";
echo "  مفاتيحُ نُسبت إلى مساحةٍ مالكة: " . count($found) . "\n";

/* المستهلِكون كما هم — تُنسخ صفوفُ المفتاحِ بمنتِجٍ جديدٍ ومصدرٍ مكتوب */
$ins = $conn->prepare(
    "INSERT INTO gov_effect_map
        (event_key, producer_mod, producer_space, fact_rows, consumer_key, consumer_space,
         consumer_doc, evidence, evidence_n, note)
     SELECT event_key, 'screen_derived', ?, fact_rows, consumer_key, consumer_space,
            consumer_doc, evidence, evidence_n,
            CONCAT('منتِجٌ مشتقٌّ من الشاشةِ الناشرة — ', note)
       FROM gov_effect_map WHERE event_key = ? AND producer_mod <> 'screen_derived'
      LIMIT 20
     ON DUPLICATE KEY UPDATE producer_space = VALUES(producer_space)");
if (!$ins) { exit("تعذّر التحضير: {$conn->error}\n"); }

$added = 0;
foreach ($found as $k => $spaces) {
    foreach (array_keys($spaces) as $sp) {
        /* المفتاحُ منسوبٌ سلفًا لهذه المساحةِ؟ لا يُكرَّر */
        $st = $conn->prepare("SELECT 1 FROM gov_effect_map WHERE event_key=? AND producer_space=? LIMIT 1");
        $st->bind_param('ss', $k, $sp); $st->execute();
        $has = $st->get_result()->num_rows; $st->close();
        if ($has) { continue; }
        $ins->bind_param('ss', $sp, $k);
        if ($ins->execute()) { $added += $conn->affected_rows; }
    }
}
$ins->close();
echo "  أُضيف: {$added} صفَّ نسبةٍ جديدة (**والقديمُ باقٍ للمقارنة**)\n";

echo "\n  ┌ الوقائعُ بحسبِ المساحةِ المنتِجةِ بعدَ التصحيح\n";
$q = $conn->query("SELECT producer_space, COUNT(DISTINCT event_key) k, SUM(evidence='MEASURED') m
                     FROM gov_effect_map GROUP BY producer_space ORDER BY k DESC");
while ($q && ($x = $q->fetch_assoc())) {
    printf("  │ %-24s وقائع=%-3d أثرٌ مقيس=%d\n", $x['producer_space'], $x['k'], $x['m']);
}
echo "  └────────────────────────────────────────\n";
exit(0);
