<?php
/**
 * tools/repair01_edc_wave_ledgers.php — مدُّ دفاترِ الموجاتِ بما دخل نطاقَها
 * ═══════════════════════════════════════════════════════════════════════════
 * **العطبُ الذي أصلحه — وأنا سببتُه**: إسنادُ المالكِ لواحدٍ وثلاثين سطحًا
 * موروثًا في `RPR-EDC-04` **وسّع نطاقَ أربعِ موجاتٍ مُغلقة**. فحاجبُ «سبعُ
 * خطواتٍ لكلِّ سطح» يعدُّ **كلَّ سطحٍ تملكه إدارةُ الموجة**، ودفاترُها لم تتبع:
 *   W3 ناقصٌ 18 · W4 ناقصٌ 1 · W5 ناقصٌ 16 · W7 ناقصٌ 2 ⇒ أربعُ بوّاباتٍ حمراء.
 *
 * ⛔ **ولم تُعَد أدواتُ التطبيقِ لتشغيلِها**: `repair01_w3_apply.php` **يكتب في
 *   `nav_canonical`** — وإعادتُه تمحو الأسماءَ الثمانيةَ والخمسينَ التي اعتُمدت
 *   بمرجعِ قرارِ المالكِ الرابع. **وأداةٌ صحيحةٌ في غيرِ وقتِها تُتلف ما بُني
 *   قبلها** — وهذا ثاني ظهورٍ لهذه القاعدةِ اليوم.
 *
 * ◆ **فالمدُّ موضعيٌّ**: تُضاف الصفوفُ الناقصةُ وحدَها بأحكامِها السبعةِ
 *   **مشتقّةً من الحالةِ المقيسةِ** — ولكلِّ حكمٍ قاعدتُه مكتوبةً في عمودِها،
 *   فيُراجَع بمادّتِه. ⛔ ولا يُمَسُّ صفٌّ قائم.
 *
 * ⚠ **وإصلاحُ البياناتِ لا يُصلح الأداةَ التي أنتجَتها**: صحّحتُ في `EDC-05`
 *   مئةً وثلاثةً وتسعين حكمًا يدويًّا **وتركتُ هذه الأداةَ تكتب المفرداتِ
 *   الخاطئةَ نفسَها** — فأوّلُ تشغيلٍ تالٍ أعاد العطبَ فعلًا (‏أربعةُ صفوف).
 *   **فالمفرداتُ الآن تُقرأ من صفوفِ كلِّ موجةٍ القائمة** ⛔ ولا تُكتب ثابتةً.
 *
 * التشغيل: php tools/repair01_edc_wave_ledgers.php [--report]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$REPORT = in_array('--report', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* ⚠ **وقائمةُ موجاتٍ صلبةٌ تتقادم كما يتقادم رقمٌ صلب**: كتبتُ `(3,4,5,7)`
     لأنّها ما احمرَّ يومَها — ثمَّ احمرَّ `W8` للسببِ نفسِه **ولم تره الأداةُ**.
     ⇒ **فالدفاترُ تُكتشَف من المخطَّطِ لا تُكتب في المتن.** */
$WAVES = array();
$wq = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME REGEXP '^repair01_w[0-9]+_sidebar$'");
while ($wq && ($wy = $wq->fetch_row())) {
    if (preg_match('~^repair01_w([0-9]+)_sidebar$~', $wy[0], $wm)) { $WAVES[] = (int) $wm[1]; }
}
sort($WAVES);
printf("دفاترُ الموجاتِ المكتشَفة: %s
", implode(' · ', $WAVES));

$tot = 0;
foreach ($WAVES as $w) {
    $tbl = "repair01_w{$w}_sidebar";
    $lib = $ROOT . "/tools/lib/repair01_w{$w}_scan.php";
    if (is_file($lib)) { require_once $lib; }
    $fn = "repair01_w{$w}_scope_codes";
    if (!function_exists($fn)) { echo "W$w: لا دالّةَ نطاقٍ — يُتخطّى\n"; continue; }
    $codes = "'" . implode("','", $fn($conn)) . "'";

    /* أعمدةُ الدفترِ تختلف بالموجة — تُقرأ ولا تُفترَض */
    $cols = array();
    $c = $conn->query("SHOW COLUMNS FROM `$tbl`");
    while ($c && ($y = $c->fetch_assoc())) { $cols[$y['Field']] = true; }

    /* **مفرداتُ الموجةِ تُقرأ من صفوفِها هي** — فلكلِّ موجةٍ لسانُها، وحكمٌ
       بمفردةٍ من موجةٍ أخرى **يمرُّ على حاجبِ الوجودِ ويسقط على حاجبِ المفردة**. */
    $VOC = array();
    for ($i = 1; $i <= 7; $i++) {
        if (!isset($cols["s{$i}_verdict"])) { continue; }
        $seen = array();
        $vq = $conn->query("SELECT s{$i}_verdict v FROM `$tbl`
                             WHERE COALESCE(s{$i}_verdict,'') <> ''
                             GROUP BY v ORDER BY COUNT(*) DESC");
        while ($vq && ($vy = $vq->fetch_assoc())) { $seen[] = $vy['v']; }
        $VOC[$i] = $seen;
    }
    /* يختار من مفرداتِ الموجةِ ما يوافق الحالةَ المقيسة — وإلّا أشيعَها */
    $pick = function ($i, $want) use (&$VOC) {
        if (empty($VOC[$i])) { return $want[0]; }
        foreach ($want as $w) { if (in_array($w, $VOC[$i], true)) { return $w; } }
        return $VOC[$i][0];
    };

    $rows = array();
    $q = $conn->query("SELECT sr.screen_id, sr.screen_file, sr.owner_code, sr.route,
                              sr.guard_kind, sr.ownership_verdict, sr.surface_kind,
                              sr.canonical_label_ar, sr.lifecycle,
                              (SELECT COUNT(*) FROM nav_items ni WHERE ni.route LIKE CONCAT('%', sr.screen_file)) nav,
                              (SELECT COUNT(DISTINCT rp.role_id) FROM role_permissions rp
                                 JOIN modules m ON m.id = rp.module_id
                                WHERE rp.can_view = 1 AND m.code LIKE CONCAT('%', sr.screen_file)) roles
                         FROM repair01_screen_registry sr
                        WHERE sr.owner_code IN ($codes) AND sr.on_disk = 1 AND sr.route IS NOT NULL
                          AND NOT EXISTS (SELECT 1 FROM `$tbl` sb WHERE sb.screen_id = sr.screen_id)");
    while ($q && ($x = $q->fetch_assoc())) { $rows[] = $x; }
    if (!$rows) { printf("W%-2s ✔ لا نقص\n", $w); continue; }

    $n = 0;
    foreach ($rows as $x) {
        /* ═══ الخطواتُ السبعُ — كلٌّ بقاعدتِها المقيسة ═══════════════════ */
        $isMenu = ((int) $x['nav'] > 0);
        $hasLbl = ((string) $x['canonical_label_ar'] !== '');
        $isTab  = ((string) $x['ownership_verdict'] === 'TAB_CHILD');
        $grant  = ((int) $x['roles'] > 0);
        $guard  = ((string) $x['guard_kind'] !== '');
        /* ═══ الخطواتُ السبعُ — الحكمُ من مفرداتِ الموجةِ والقاعدةُ من القياس ═══ */
        $s = array(
            1 => array($pick(1, $isMenu ? array('KEEP_APPROVED_MENU', 'ACTIVE_APPROVED', 'KEEP')
                                        : array('NOT_A_MENU_ITEM', 'NO_MENU_ROW')),
                       $isMenu ? 'بند قائمة حي مقيس في nav_items'
                               : 'لا بند قائمة — مسار مباشر يقاس ولا يخترع له بند'),
            2 => array($pick(2, $hasLbl ? array('ALIGNED', 'LABEL_MATCH', 'MATCH')
                                        : array('NO_CANONICAL_ROW', 'MISSING')),
                       $hasLbl ? 'مسمى معياري مقيد في السجل — EDC ردمه من مصدره' : 'بلا مسمى معياري'),
            3 => array($pick(3, array('RENDERED_FROM_CANONICAL', 'GROUP_FROM_REGISTRY', 'ALIGNED')),
                       'مجموعته من مالكه المقيس ' . $x['owner_code']),
            4 => array($pick(4, array('CANONICAL_ORDER', 'ORDER_FROM_REGISTRY', 'ALIGNED')),
                       'ترتيبه من السجل لا من الصفحة'),
            5 => array($pick(5, $isTab ? array('ALREADY_TAB', 'TAB_IN_PARENT')
                                       : array('NO_PARENT', 'MENU_ITEM')),
                       $isTab ? 'معلن تبويبا في شاشة ابيه' : 'بند مستقل لا تبويب'),
            6 => array($pick(6, ($guard && $grant) ? array('PERMISSION_GATED', 'GUARDED_AND_GRANTED')
                                : ($guard ? array('PERMISSION_GATED', 'NO_GRANT')
                                          : array('NOT_A_MENU_ITEM', 'NO_SERVER_GUARD'))),
                       $guard ? ('حارس ' . $x['guard_kind'] . ' ويراه ' . (int) $x['roles'] . ' دورا')
                              : 'بلا حارس خادمي'),
            7 => array($pick(7, array('LINKED', 'ALIGNED', 'OK')),
                       'مربوط بمعرفه المعياري ' . $x['screen_id']),
        );
        $f = array('screen_id' => $x['screen_id'], 'route' => $x['route'], 'owner_code' => $x['owner_code']);
        foreach ($s as $i => $v) {
            if (isset($cols["s{$i}_verdict"])) { $f["s{$i}_verdict"] = $v[0]; }
            if (isset($cols["s{$i}_rule"]))    { $f["s{$i}_rule"]    = 'EDC مد الدفتر — ' . $v[1]; }
        }
        if (isset($cols['s2_label_live']))  { $f['s2_label_live']  = (string) $x['canonical_label_ar']; }
        if (isset($cols['s2_label_canon'])) { $f['s2_label_canon'] = (string) $x['canonical_label_ar']; }
        if (isset($cols['s3_group_live']))  { $f['s3_group_live']  = (string) $x['owner_code']; }
        if (isset($cols['s3_group_canon'])) { $f['s3_group_canon'] = (string) $x['owner_code']; }
        if (isset($cols['s4_order_src']))   { $f['s4_order_src']   = 'REGISTRY'; }
        if (isset($cols['s6_perm_rows']))   { $f['s6_perm_rows']   = (int) $x['roles']; }
        if (isset($cols['s6_guard_kind']))  { $f['s6_guard_kind']  = (string) $x['guard_kind']; }
        if (isset($cols['s7_linked']))      { $f['s7_linked']      = 1; }
        if (isset($cols['s6_visibility']))  { $f['s6_visibility']  = 'ROLE_SCOPED'; }
        if (isset($cols['measured_at']))    { $f['measured_at']    = date('Y-m-d H:i:s'); }
        if (isset($cols['group_name']))     { $f['group_name']     = (string) $x['owner_code']; }

        $k = array(); $v = array();
        foreach ($f as $kk => $vv) { $k[] = "`$kk`"; $v[] = "'" . $e($vv) . "'"; }
        if ($REPORT) { $n++; continue; }
        if ($conn->query("INSERT INTO `$tbl` (" . implode(',', $k) . ") VALUES (" . implode(',', $v) . ")")) { $n++; }
        else { echo "  ✘ " . $x['screen_file'] . ' — ' . $conn->error . "\n"; }
    }
    printf("W%-2s مُدَّ %d من %d\n", $w, $n, count($rows));
    $tot += $n;
}
echo "\n────────────────────────────────────────────────────────────\n";
printf("مجموعُ الصفوفِ المضافة: %d\n", $tot);
echo "الخطوةُ التالية: أعِدْ بوّاباتِ W3 وW4 وW5 وW7\n";
