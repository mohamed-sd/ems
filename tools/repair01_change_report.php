<?php
/**
 * tools/repair01_change_report.php — تقريرُ تغييراتِ الشاشاتِ والسايدبارِ في حملةِ REPAIR01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مقيسٌ من السجلِّ لا مسرودٌ من الذاكرة**: كلُّ صفٍّ هنا مصدرُه جدولٌ حيٌّ —
 *   `repair01_screen_registry` (النموّ) · `nav_canonical` (الاسمُ المعياريّ) ·
 *   `gov_nav_hidden_log` (الإخفاءُ والدمج) · `repair01_w{8,9}_fixes` (الإصلاح).
 *
 * ◆ **وما سبق الحملةَ يُفصَل**: سجلُّ الإخفاءِ يحمل ١١٥ صفًّا بتاريخ 2026-08-22
 *   من حملةِ `INJ-SAL-ALIGN-01` — وعدُّها ضمنَ REPAIR01 يضخّم الرقمَ بلا وجه.
 *   فالفصلُ **بالتاريخِ ورمزِ الوثيقةِ معًا** لا بأحدِهما.
 *
 * التشغيل: php tools/repair01_change_report.php [--html]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

/** بدايةُ الحملة — ما قبلها ليس منها */
const RPR_START = '2026-08-23';

$rows = array();   /* اسمُ الصفحة · الإدارة · التغيير */
$add = function ($page, $dept, $change, $wave) use (&$rows) {
    $rows[] = array('page' => $page, 'dept' => $dept, 'change' => $change, 'wave' => $wave);
};

/** قيمةٌ مفردةٌ آمنةٌ — والاستعلامُ بلا صفٍّ يعيد فراغًا لا تحذيرًا */
$scalar = function ($sql) use ($conn) {
    $r = @$conn->query($sql);
    if (!$r) { return ''; }
    $x = $r->fetch_row();
    return $x ? (string) $x[0] : '';
};

/* اسمُ الإدارةِ من الرمزِ المعياريِّ عبرَ الجسر */
$deptOf = function ($code) use ($conn) {
    static $m = null;
    if ($m === null) {
        $m = array();
        $r = $conn->query("SELECT canonical_code, legacy_name FROM repair01_dept_crosswalk");
        while ($r && $x = $r->fetch_assoc()) {
            if (!isset($m[$x['canonical_code']])) { $m[$x['canonical_code']] = $x['legacy_name']; }
        }
    }
    if (isset($m[$code])) { return $m[$code]; }
    /* رمزٌ بلا جسرٍ أو سطحٌ خارجَ سجلِّ الشاشات — يُعلَن ولا يُترك فراغًا */
    return (string) $code !== '' ? (string) $code : 'غير مسندة';
};

/* ── ① شاشاتٌ جديدةٌ مبنيّةٌ في الحملة ──────────────────────────────────── */
$r = $conn->query("SELECT g.screen_id, g.route, g.owner_code, g.origin,
                          COALESCE(n.canonical_ar,'') nm, COALESCE(n.group_name,'') grp
                     FROM repair01_screen_registry g
                     LEFT JOIN nav_canonical n ON n.route = g.route
                    WHERE g.origin REGEXP '^W[0-9]{2}$'
                    ORDER BY g.origin, g.route");
$newN = 0;
while ($r && $x = $r->fetch_assoc()) {
    $newN++;
    $add($x['nm'] !== '' ? $x['nm'] : basename($x['route']),
         $deptOf($x['owner_code']),
         'شاشة جديدة بنيت في الموجة ' . $x['origin'] . ' وربطت بمجموعة ' . ($x['grp'] !== '' ? $x['grp'] : 'غير مسماة'),
         $x['origin']);
}

/* ── ② سطحٌ قائمٌ رُبط بالسجلِّ المعياريِّ لأوّلِ مرّة ───────────────────── */
$r = $conn->query("SELECT n.route, n.canonical_ar, n.group_name, n.decision_source,
                          COALESCE(g.owner_code,'') oc
                     FROM nav_canonical n
                     LEFT JOIN repair01_screen_registry g ON g.route = n.route
                    WHERE n.decision_source LIKE 'RPR-%'
                      AND n.decision_source LIKE '%ربط سطح%'
                    ORDER BY n.route");
$linkN = 0;
while ($r && $x = $r->fetch_assoc()) {
    $linkN++;
    $add($x['canonical_ar'], $deptOf($x['oc']),
         'سطح قائم ربط بالسجل المعياري لاول مرة فصار له اسم ومجموعة وترتيب', 'W09');
}

/* ── ③ إعادةُ تسميةٍ مسجَّلةٌ في W06 — الفاصلُ `·` وسمُ الحملة ───────────── */
$r = $conn->query("SELECT route, canonical_ar, old_names, COALESCE(owner_dept,'') od
                     FROM nav_canonical
                    WHERE old_names LIKE '%·%' ORDER BY route");
$renN = 0;
while ($r && $x = $r->fetch_assoc()) {
    $renN++;
    $olds = array_filter(array_map('trim', explode('·', (string) $x['old_names'])));
    $prev = count($olds) ? $olds[0] : '';
    $add($x['canonical_ar'], $x['od'] !== '' ? $x['od'] : 'غير مسندة',
         'اعادة تسمية سجلت في الموجة W06 والاسم السابق ' . $prev, 'W06');
}

/* ── ④ إخفاءٌ أو دمجٌ في تبويبٍ داخلَ الحملة ────────────────────────────── */
$r = $conn->query("SELECT h.route, h.label_ar, h.doc_code, h.reachable, h.role_id,
                          COALESCE(g.owner_code,'') oc
                     FROM gov_nav_hidden_log h
                     LEFT JOIN repair01_screen_registry g ON g.route = h.route
                    WHERE DATE(h.hidden_at) >= '" . RPR_START . "'
                    ORDER BY h.hidden_at, h.route");
$hidN = 0; $seenHid = array();
while ($r && $x = $r->fetch_assoc()) {
    $k = $x['route'] . '|' . $x['reachable'];
    if (isset($seenHid[$k])) { continue; }
    $seenHid[$k] = true;
    $hidN++;
    $wave = (strpos((string) $x['doc_code'], 'RPR-') === 0) ? substr($x['doc_code'], 4) : 'W02';
    $add($x['label_ar'], $deptOf($x['oc']),
         'بند سايدبار عطل بعذر مكتوب في السجل والسبب ' . $x['reachable']
         . ' ومرجع الوثيقة ' . $x['doc_code'], $wave);
}

/* ── ⑤ شاشةٌ حيّةٌ أُصلحت ووُصلت بكيانِ متطلَّبِها ───────────────────────── */
foreach (array('repair01_w8_fixes' => 'W08', 'repair01_w9_fixes' => 'W09') as $t => $w) {
    $q = @$conn->query("SELECT fix_key, kind, target, what, revealed_by FROM `$t`
                         WHERE kind IN ('CODE','SCHEMA','REGISTRY') ORDER BY fix_key");
    while ($q && $x = $q->fetch_assoc()) {
        if (strpos((string) $x['target'], '.php') === false) { continue; }
        $route = trim(explode(' ', (string) $x['target'])[0]);
        /* ⛔ **الشاشةُ ما كان في سجلِّ الشاشات** — لا كلُّ ملفٍّ ينتهي بـ`.php`.
             فـ`app/Core/TenantRegistry.php` و`tools/lib/…` عُدَّةٌ لا سطح،
             وعدُّها شاشاتٍ يضخّم رقمَ «الشاشاتِ المُصلَحة» بلا وجه. */
        $isScreen = (int) $scalar("SELECT COUNT(*) FROM repair01_screen_registry
                                    WHERE route = '" . $conn->real_escape_string($route) . "'");
        if ($isScreen === 0) { continue; }
        $nm = $scalar("SELECT COALESCE(canonical_ar,'') FROM nav_canonical WHERE route = '" . $conn->real_escape_string($route) . "' LIMIT 1");
        $oc = $scalar("SELECT COALESCE(owner_code,'') FROM repair01_screen_registry
                        WHERE route = '" . $conn->real_escape_string($route) . "' LIMIT 1");
        $add($nm !== '' ? $nm : basename($route), $deptOf($oc),
             'شاشة قائمة اصلحت في ' . $w . ' والكاشف ' . $x['revealed_by'] . ' والعمل ' . $x['what'], $w);
    }
}

/* شاشاتُ W07 المُصلَحةُ مُعلَنةٌ في وثيقةِ إغلاقِها لا في جدولِ إصلاحات */
foreach (array(
    array('Maintenance/return_to_service.php', 'MNT-14',
          'كانت ميتة تستعلم عمودا لا وجود له فتقول لا اوامر منجزة دائما'),
    array('Transport/transfer_permits.php', 'TRP-05',
          'كانت تعرض مخزنا غير الذي يمنع المغادرة فالتصريح المنتهي يعرض ساريا'),
    array('Transport/transfer_in_transit.php', 'TRP-07',
          'وصلت بمراحل الرحلة الجديدة'),
) as $f) {
    $nm = $scalar("SELECT COALESCE(canonical_ar,'') FROM nav_canonical
                    WHERE route = '" . $conn->real_escape_string($f[0]) . "' LIMIT 1");
    $oc = $scalar("SELECT COALESCE(owner_code,'') FROM repair01_screen_registry
                    WHERE route = '" . $conn->real_escape_string($f[0]) . "' LIMIT 1");
    $add($nm !== '' ? $nm : basename($f[0]), $deptOf($oc),
         'شاشة قائمة اصلحت في W07 والكاشف ' . $f[1] . ' والعطب ' . $f[2], 'W07');
}

/* ═══════════════════════ المخرَج ═══════════════════════ */
$byWave = array();
foreach ($rows as $x) { $byWave[$x['wave']] = isset($byWave[$x['wave']]) ? $byWave[$x['wave']] + 1 : 1; }
ksort($byWave);

echo "══ تقريرُ تغييراتِ الشاشاتِ والسايدبارِ — حملةُ REPAIR01 ══\n\n";
printf("  شاشاتٌ جديدة ................ %d\n", $newN);
printf("  سطحٌ قائمٌ رُبط بالسجلّ ....... %d\n", $linkN);
printf("  إعادةُ تسميةٍ مسجَّلة ......... %d\n", $renN);
printf("  بندُ سايدبارٍ عُطِّل بعذر ...... %d\n", $hidN);
printf("  الإجمالي .................... %d صفًّا\n\n", count($rows));
echo "  بالموجة: ";
foreach ($byWave as $w => $n) { echo "$w=$n  "; }
echo "\n\n";

$out = $ROOT . '/docs/REPAIR01_20260823/CHANGE_REPORT.md';
$md = "# تقرير تغييرات الشاشات والسايدبار — حملة REPAIR01\n\n";
$md .= "> ⛔ **مولد من السجل الحي — لا يحرر يدويا**: `php tools/repair01_change_report.php`\n\n";
$md .= "**المدى:** من بداية الحملة 2026-08-23 حتى اليوم. وما سبق هذا التاريخ ليس منها "
     . "ولا يعد فيها — سجل الاخفاء وحده يحمل 115 صفا سابقا من حملة اخرى.\n\n";
$md .= "| البند | العدد |\n|---|---:|\n";
$md .= "| شاشات جديدة بنيت | **$newN** |\n";
$md .= "| سطح قائم ربط بالسجل المعياري | **$linkN** |\n";
$md .= "| اعادة تسمية مسجلة | **$renN** |\n";
$md .= "| بند سايدبار عطل بعذر مكتوب | **$hidN** |\n";
$md .= "| شاشات قائمة اصلحت | **" . (count($rows) - $newN - $linkN - $renN - $hidN) . "** |\n";
$md .= "| **الاجمالي** | **" . count($rows) . "** |\n\n";
$md .= "---\n\n## الجدول الكامل\n\n";
$md .= "| اسم الصفحة | الادارة | التغيير |\n|---|---|---|\n";
foreach ($rows as $x) {
    $md .= '| ' . str_replace('|', '¦', $x['page']) . ' | ' . str_replace('|', '¦', $x['dept'])
         . ' | ' . str_replace('|', '¦', $x['change']) . " |\n";
}
file_put_contents($out, $md);
echo "  ✔ كُتب: docs/REPAIR01_20260823/CHANGE_REPORT.md\n";

/* JSON للاستهلاكِ الخارجيّ */
file_put_contents($ROOT . '/docs/REPAIR01_20260823/CHANGE_REPORT.json',
    json_encode(array('summary' => array('new' => $newN, 'linked' => $linkN, 'renamed' => $renN,
        'hidden' => $hidN, 'fixed' => count($rows) - $newN - $linkN - $renN - $hidN,
        'total' => count($rows)), 'byWave' => $byWave, 'rows' => $rows),
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "  ✔ كُتب: docs/REPAIR01_20260823/CHANGE_REPORT.json\n";
