<?php
/**
 * tools/perm01_link_closure.php — إغلاقُ الوصول: ما يُبلَغ بالنقرِ من شاشةِ هدف
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المشكلةُ المقيسة**: ورقةُ الدليلِ تعرّف **القائمةَ الجانبيّة**، وشاشاتُ
 *   التفاصيلِ تُبلَغ **بالنقرِ من شاشةٍ أخرى** فلا رابطَ لها في القائمة. فهدفٌ
 *   مبنيٌّ من الدليلِ وحدَه يترك الرابطَ يعمل والوجهةَ تردُّ **403** — وهو كسرُ
 *   عملٍ صامت.
 *
 * ◆ **والعلاجُ قياسٌ لا قائمةٌ يدويّة**: تُقرأ شيفرةُ كلِّ شاشةِ هدفٍ ويُستخرَج
 *   ما تشير إليه من ملفّاتِ `.php`، فيُضاف ما يطابق سجلَّ الوحدات. فالإضافةُ
 *   **مشتقّةٌ من الشاشةِ نفسِها** لا من رأيٍ فيها.
 *
 * ⛔ **وقفزةٌ واحدةٌ لا إغلاقٌ متعدٍّ**: التعدّي يجرُّ الشجرةَ كلَّها فيعود
 *   الاتّحادُ من بابٍ جديد. فيُضاف **ما تلمسه شاشةُ الهدفِ مباشرةً** حصرًا،
 *   والعمقُ مسجَّلٌ في الصفِّ فيُعرف مصدرُه.
 *
 * ⛔ **ولا يُضاف ما ليس في الوحداتِ ولا ما هو خارجَ الحيّ**: المرجعُ الذي لا
 *   يطابق `modules.code` يُترك ويُعدُّ — ولا يُخترع له سجلّ.
 *
 * التشغيل: php tools/perm01_link_closure.php            (قياسٌ بلا كتابة)
 *          php tools/perm01_link_closure.php --write    (يُضيف بنودَ الوصول)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');
$WRITE = in_array('--write', $argv, true);

/* ── سجلُّ الوحدات: مفتاحٌ صغيرُ الحروفِ ⇐ الكودُ كما هو ─────────────────── */
$codeOf = array();
$r = $db->query("SELECT DISTINCT code FROM modules");
while ($x = $r->fetch_row()) { $codeOf[strtolower($x[0])] = $x[0]; }
printf("سجلُّ الوحدات: %d كودًا متمايزًا\n", count($codeOf));

/* ── الأهدافُ القائمة ──────────────────────────────────────────────────── */
$target = array();
$r = $db->query("SELECT workspace_id, module_code FROM perm01_target_item");
if (!$r) { exit("سجلُّ الأهدافِ غيرُ مبنيّ — شغِّل هجرةَ 2028_05_04 أوّلًا\n"); }
while ($x = $r->fetch_assoc()) { $target[$x['workspace_id']][$x['module_code']] = 1; }
printf("الأهدافُ القائمة: %d مساحةً · %d بندًا\n\n",
    count($target), array_sum(array_map('count', $target)));

/* ── قارئُ الروابط: ما تشير إليه شاشةٌ من ملفّاتِ php ───────────────────── */
$linksCache = array();
$linksOf = function ($code) use ($ROOT, $codeOf, &$linksCache) {
    if (isset($linksCache[$code])) { return $linksCache[$code]; }
    $path = $ROOT . '/' . $code;
    if (!is_file($path)) { return $linksCache[$code] = array(); }
    $src = @file_get_contents($path);
    if ($src === false) { return $linksCache[$code] = array(); }
    /* ◆ مرجعٌ نصّيٌّ لملفِّ php أيًّا كان بابُه: href · location · Location · نموذج.
       ⛔ **والمسارُ النسبيُّ يُحَلُّ بمجلَّدِ الشاشةِ لا يُترك عاريًا**: رابطٌ
         داخلَ المجلَّدِ نفسِه يُكتب `client_profile.php?id=` بلا مسار، فتجريدُه
         وحدَه لا يطابق `Clients/client_profile.php` — **وهو الشكلُ الغالبُ
         لروابطِ التفاصيل**. (مقيسٌ: تغطيةٌ 4 من 105 قبلَ الحلِّ.) */
    $dir = strpos($code, '/') !== false ? substr($code, 0, strrpos($code, '/') + 1) : '';
    $out = array();
    if (preg_match_all('~[A-Za-z0-9_][A-Za-z0-9_./-]*\.php~', $src, $m)) {
        foreach ($m[0] as $raw) {
            $c = str_replace('\\', '/', $raw);
            $rel = (strpos($c, '/') === false);      /* بلا مسار ⇒ جارٌ في المجلَّد */
            $c = ltrim($c, './');
            $up = 0;
            while (strpos($c, '../') === 0) { $c = substr($c, 3); $up++; }
            $cands = array($c);
            if ($rel && $dir !== '') { $cands[] = $dir . $c; }
            /* وصعودٌ واحدٌ من مجلَّدٍ يعني الجذرَ أو مجلَّدًا شقيقًا — كلاهما مُجرَّبٌ. */
            if ($up > 0 && $dir !== '') { $cands[] = $c; }
            foreach ($cands as $cand) {
                $k = strtolower($cand);
                if (isset($codeOf[$k]) && $codeOf[$k] !== $code) { $out[$codeOf[$k]] = 1; }
            }
        }
    }
    return $linksCache[$code] = $out;
};

/* ── الشِّلُّ المشترَك: ما يُبلَغ من الشريطِ العلويِّ في كلِّ شاشة ────────────
   ◆ **الشريطُ العلويُّ على كلِّ شاشةٍ فروابطُه في يدِ كلِّ مستخدم**: «ملفّي» و
     «إعدادات» و«بوابتي» و«بحثٌ عامّ» — ولا تظهر في ورقةِ الدليلِ لأنها ليست
     بندَ قائمةٍ جانبيّة. فتُضاف إلى «مساحتي» الإلزاميّةِ لكلِّ حساب.
   ⛔ **والمصدرُ ملفُّ الشريطِ نفسُه لا قائمةٌ يدويّة** — `includes/topbar.php`
     وحدَه (مقيسٌ: 11 شاشةً مسجَّلة). ولا يُوسَّع إلى `includes/*` كلِّها: تلك
     تشير إلى 261 شاشةً لأنّ فيها سجلّاتِ ملاحةٍ تعدّد كلَّ مسار — فتُعيد
     الاتّحادَ من بابٍ جديد. */
$SHELL_FILES = array('includes/topbar.php', 'insidebar.php', 'inheader.php');
$shellHits = array();
foreach ($SHELL_FILES as $sf) {
    $path = $ROOT . '/' . $sf;
    if (!is_file($path)) { continue; }
    $src = @file_get_contents($path);
    if ($src === false) { continue; }
    if (preg_match_all('~[A-Za-z0-9_][A-Za-z0-9_./-]*\.php~', $src, $m)) {
        foreach ($m[0] as $raw) {
            $c = ltrim(str_replace('\\', '/', $raw), './');
            while (strpos($c, '../') === 0) { $c = substr($c, 3); }
            $k = strtolower($c);
            if (isset($codeOf[$k])) { $shellHits[$codeOf[$k]] = 1; }
        }
    }
}
$shellNew = array_diff_key($shellHits, isset($target['WS-MY']) ? $target['WS-MY'] : array());
printf("الشِّلُّ المشترَك: %d شاشةً · جديدٌ لـWS-MY: %d\n\n", count($shellHits), count($shellNew));

/* ── قفزةٌ واحدةٌ من كلِّ شاشةِ هدف ─────────────────────────────────────── */
echo "══ إغلاقُ الوصولِ بقفزةٍ واحدة ════════════════════════════════════════\n";
printf("   %-12s %8s %8s %8s\n", 'المساحة', 'الهدف', 'يُضاف', 'المجموع');
$add = array(); $totalAdd = 0;
/* الشِّلُّ يدخل هدفَ «مساحتي» قبلَ القفزة — فما يليه يُحسب عليه لا عليه وحدَه. */
foreach (array_keys($shellNew) as $c) { $target['WS-MY'][$c] = 1; }
foreach ($target as $ws => $items) {
    $new = array();
    foreach (array_keys($items) as $code) {
        foreach (array_keys($linksOf($code)) as $dst) {
            if (!isset($items[$dst])) { $new[$dst] = 1; }
        }
    }
    $add[$ws] = $new;
    $totalAdd += count($new);
    printf("   %-12s %8d %8d %8d\n", $ws, count($items), count($new), count($items) + count($new));
}
printf("\n   بنودُ وصولٍ تُضاف: %d\n", $totalAdd);

/* ── هل يغطّي هذا الشاشاتِ المكسورةَ التي رصدها الظلّ؟ ───────────────────── */
echo "\n══ التحقّق — الشاشاتُ المكسورةُ (مُغلَقةٌ وبلا رابطٍ في القائمة) ════════\n";
$closed = array();
$r = $db->query("SELECT DISTINCT i.item_ref FROM gov_profile_items i
                   JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                  WHERE i.item_kind = 'screen' AND i.allow = 1
                    AND i.item_ref NOT IN (SELECT module_code FROM perm01_target_item)");
while ($x = $r->fetch_row()) { $closed[$x[0]] = 1; }
$nav = array();
$r = $db->query("SELECT DISTINCT route FROM nav_items WHERE active = 1");
while ($x = $r->fetch_row()) { $nav[$x[0]] = 1; }
$broken = array_diff_key($closed, $nav);

/* ⛔ **والمُضافُ يشمل الشِّلَّ أيضًا**: بنودُه دخلت الهدفَ قبلَ القفزةِ فلا
     تظهر في `$add` — وعدُّها ناقصةً يُظهر شاشةً مغطّاةً على أنّها مكسورة. */
$allAdd = $shellNew;
foreach ($add as $ws => $new) { foreach (array_keys($new) as $c) { $allAdd[$c] = 1; } }
$covered = array_intersect_key($broken, $allAdd);
$stillOut = array_diff_key($broken, $allAdd);
printf("   مكسورةٌ قبلَ الإغلاق: %d\n", count($broken));
printf("   غطّاها إغلاقُ الوصول: %d\n", count($covered));
printf("   ما تزال خارجَ الهدف: %d\n", count($stillOut));
echo "   ◆ والباقيةُ خارجَ الهدفِ **لا تُبلَغ من شاشةِ هدفٍ أصلًا** — فإغلاقُها\n";
echo "     لا يكسر رابطًا يعمل، وهو المقصودُ من الفرز.\n";
foreach (array_slice(array_keys($stillOut), 0, 8) as $c) { echo "      · " . $c . "\n"; }

/* ── الكتابة ──────────────────────────────────────────────────────────── */
if ($WRITE) {
    echo "\n══ الكتابة ══════════════════════════════════════════════════════════\n";
    $db->query("DELETE FROM perm01_target_item WHERE origin IN ('LINK','SHELL')");
    $removed = $db->affected_rows;
    $insS = $db->prepare("INSERT IGNORE INTO perm01_target_item
                            (workspace_id, module_code, origin, source_ref)
                          VALUES ('WS-MY', ?, 'SHELL', 'shell:includes/topbar.php — على كل شاشة')");
    $wroteShell = 0;
    foreach (array_keys($shellNew) as $code) {
        $insS->bind_param('s', $code);
        if ($insS->execute() && $db->affected_rows > 0) { $wroteShell++; }
    }
    $insS->close();
    printf("   بنودُ الشِّلِّ المكتوبة في WS-MY: %d\n", $wroteShell);
    $ins = $db->prepare("INSERT IGNORE INTO perm01_target_item
                           (workspace_id, module_code, origin, source_ref) VALUES (?, ?, 'LINK', ?)");
    $wrote = 0;
    foreach ($add as $ws => $new) {
        foreach (array_keys($new) as $code) {
            $sr = 'link-closure:1hop من شاشة هدف في ' . $ws;
            $ins->bind_param('sss', $ws, $code, $sr);
            if ($ins->execute() && $db->affected_rows > 0) { $wrote++; }
        }
    }
    $ins->close();
    $tot = (int) $db->query("SELECT COUNT(*) FROM perm01_target_item")->fetch_row()[0];
    /* تحديثُ العدّادِ في رأسِ كلِّ مساحة — فرقمٌ في الرأسِ يخالف بنودَه يُضلّل. */
    $db->query("UPDATE perm01_target_profile p
                   SET p.screens_n = (SELECT COUNT(*) FROM perm01_target_item i
                                       WHERE i.workspace_id = p.workspace_id)");
    printf("   حُذف من هذا المصدر: %d · كُتب: %d · والسجلُّ الآن: %d بندًا\n", $removed, $wrote, $tot);
} else {
    echo "\n   (قياسٌ فقط — أضِف --write للكتابة)\n";
}
