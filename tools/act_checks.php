<?php
/**
 * A-04 · فحوصُ الاتصال الأحدَ عشرَ — ACT-01 §5
 * ─────────────────────────────────────────────
 * «الفحصُ يمنع الدمجَ ولا يُنبّه بعده» — وضعان:
 *   php tools/act_checks.php            → رصدٌ: تقريرُ الأرقام الأحدَ عشرَ فقط.
 *   php tools/act_checks.php --enforce  → منعٌ: exit(1) إن كان رقمٌ حاكمٌ غيرَ صفر.
 *
 * الفحوص (§5): ① أزرارٌ يتيمة · ② أفعالٌ بلا معالج · ③ كتابةٌ بلا حارس ·
 * ④ أحداثٌ يتيمة · ⑤ مستهلكون صامتون (تنبيهٌ لا منع) · ⑥ خطواتٌ بلا شاشة ·
 * ⑦ أفعالٌ بلا عكس · ⑧ روابطُ مكسورةٌ ويتيمة · ⑨ مساراتُ التحويل ·
 * ⑩ سطحُ البلاغات · ⑪ ملكيةُ الشاشة (الغريبُ غيرُ المصنَّف).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';

$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$enforce = in_array('--enforce', $argv ?? array(), true);
$root = dirname(__DIR__);
$out = array();          // [رقم الفحص، الاسم، العدد، حاكم؟، تفاصيل[]]

function q($conn, $sql) { $r = mysqli_query($conn, $sql); $rows = array();
    if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x; return $rows; }

/* ── ① أزرارٌ يتيمة: أزرارُ AJAX في القوالب بلا صفٍّ في actions ──────────── */
$registered = array();
foreach (q($conn, "SELECT handler_path FROM actions WHERE handler_path IS NOT NULL") as $r)
    $registered[strtolower($r['handler_path'])] = 1;
$orphanButtons = array();
// المسحُ على أدلة الشاشات المعروفة — استدعاءاتُ fetch/ajax إلى معالجات php
$dirs = array_filter(glob($root . '/*'), 'is_dir');
foreach ($dirs as $d) {
    $base = basename($d);
    if (in_array($base, array('vendor','node_modules','database','docs','tools','logs','app','tests','.git','.claude','.ssdiff'))) continue;
    foreach (glob($d . '/*.php') as $f) {
        $src = @file_get_contents($f); if ($src === false) continue;
        if (preg_match_all('~["\'](?:\.\./)?([A-Za-z_][\w/]*/(?:[\w]*handler[\w]*|get_[\w]+)\.php)~u', $src, $m)) {
            foreach (array_unique($m[1]) as $target) {
                $key = strtolower($target);
                if (!isset($registered[$key])) $orphanButtons[$key] = basename($f);
            }
        }
    }
}
$out[] = array('①','أزرارٌ يتيمة (استدعاءٌ بلا فعلٍ مسجَّل)', count($orphanButtons), true, array_slice(array_keys($orphanButtons),0,15));

/* ── ② أفعالٌ بلا معالجٍ موجودٍ فعلًا ────────────────────────────────────── */
$noHandler = array();
foreach (q($conn, "SELECT action_code, handler_class, handler_method, handler_path FROM actions WHERE active=1") as $a) {
    if ($a['handler_class']) {
        // أصنافُ الخدمات تُحمَّل بمساراتها الاصطلاحية app/Services/...
        $file = $root . '/app/' . str_replace(array('App\\','\\'), array('','/'), $a['handler_class']) . '.php';
        if (!is_file($file)) { $noHandler[] = $a['action_code'].' ← '.$a['handler_class']; continue; }
        $src = file_get_contents($file);
        if ($a['handler_method'] && strpos($src, 'function ' . $a['handler_method']) === false)
            $noHandler[] = $a['action_code'].' ← ::'.$a['handler_method'];
    } elseif ($a['handler_path']) {
        if (!is_file($root . '/' . $a['handler_path'])) $noHandler[] = $a['action_code'].' ← '.$a['handler_path'];
    } else $noHandler[] = $a['action_code'].' ← بلا معالج';
}
$out[] = array('②','أفعالٌ بلا معالج', count($noHandler), true, array_slice($noHandler,0,15));

/* ── ③ كتابةٌ بلا حارس ───────────────────────────────────────────────────── */
$rows = q($conn, "SELECT action_code FROM actions WHERE active=1 AND is_write=1
                  AND (guards_json IS NULL OR guards_json='' OR guards_json='[]')");
$out[] = array('③','كتابةٌ بلا حارس', count($rows), true, array_column($rows,'action_code'));

/* ── ④ أحداثٌ يتيمة: في العقود بلا مستهلك ───────────────────────────────── */
$rows = q($conn, "SELECT ae.event_name FROM action_events ae
                  LEFT JOIN event_consumers ec ON ec.event_name=ae.event_name AND ec.active=1
                  WHERE ec.c_id IS NULL GROUP BY ae.event_name");
$out[] = array('④','أحداثٌ يتيمةٌ بلا مستهلك', count($rows), true, array_column($rows,'event_name'));

/* ── ⑤ مستهلكون صامتون — تنبيهٌ يُراجَع لا منع ──────────────────────────── */
$rows = q($conn, "SELECT CONCAT(event_name,'←',consumer_class) x FROM event_consumers
                  WHERE active=1 AND produces NOT IN ('write','notify','dashboard_refresh')");
$out[] = array('⑤','مستهلكون صامتون (تنبيه)', count($rows), false, array_column($rows,'x'));

/* ── ⑥ خطواتٌ بلا شاشة: حلقاتُ سلسلة الاعتماد لها شاشةٌ تصلها في التنقل ── */
$chainScreens = q($conn, "SELECT COUNT(*) c FROM nav_items WHERE active=1
                          AND (route LIKE '%approval%' OR route LIKE 'Approvals/%')");
$missing = ((int)($chainScreens[0]['c'] ?? 0) === 0) ? 1 : 0;
$out[] = array('⑥','خطواتُ سلسلةٍ بلا شاشة', $missing, true, $missing ? array('لا شاشةَ اعتمادٍ في التنقل') : array());

/* ── ⑦ أفعالٌ ماليةٌ بلا عكس ─────────────────────────────────────────────── */
$rows = q($conn, "SELECT action_code FROM actions WHERE active=1 AND is_financial=1
                  AND (reverse_action_code IS NULL OR reverse_action_code='')");
$out[] = array('⑦','ماليٌّ بلا عكس', count($rows), true, array_column($rows,'action_code'));

/* ── ⑧ روابطُ مكسورة: nav_items يشير إلى ملفٍّ غيرِ موجود ─────────────────── */
$broken = array();
foreach (q($conn, "SELECT route FROM nav_items WHERE active=1 GROUP BY route") as $r) {
    $p = preg_replace('~[?#].*$~', '', $r['route']);
    if ($p === '' || $p === '#') continue;
    if (!is_file($root . '/' . ltrim($p, '/'))) $broken[] = $r['route'];
}
$out[] = array('⑧','روابطُ تنقلٍ مكسورة', count($broken), true, array_slice($broken,0,15));

/* ── ⑨ مساراتُ التحويل: كلُّ redirect نشطٍ وجهتُه موجودة ─────────────────── */
$bad = array();
foreach (q($conn, "SELECT old_route,new_route FROM nav_redirects WHERE active=1") as $r) {
    $p = preg_replace('~[?#].*$~', '', $r['new_route']);
    if (!is_file($root . '/' . ltrim($p, '/'))) $bad[] = $r['old_route'].' → '.$r['new_route'];
}
$out[] = array('⑨','تحويلاتٌ وجهتُها مكسورة', count($bad), true, $bad);

/* ── ⑩ سطحُ البلاغات: كلُّ دورٍ له «بلاغاتُ إدارتي» ─────────────────────── */
$rolesAll = array_column(q($conn, "SELECT DISTINCT role_id FROM nav_items WHERE active=1"), 'role_id');
$rolesWith = array_column(q($conn, "SELECT DISTINCT role_id FROM nav_items WHERE active=1
              AND (label_ar LIKE '%بلاغات إدارتي%' OR route LIKE '%dept_inbox%')"), 'role_id');
$noSurface = array_values(array_diff($rolesAll, $rolesWith));
$out[] = array('⑩','إداراتٌ (أدوارٌ) بلا سطح بلاغات', count($noSurface), true, array_map(fn($r)=>"دور $r", $noSurface));

/* ── ⑪ صفُّ العرض (ACT-01 v6): ظهورٌ لغير المالك بلا صفٍّ في المصفوفة ──── */
// خريطةُ الدور ← إدارتُه المستهدفة (أدوارُ الإدارات المالكة)
$roleDept = array(1=>'التشغيل',6=>'إدارة الموقع',13=>'الصيانة',23=>'النقل والترحيل',
 16=>'سلسلة الإمداد — المشتريات',25=>'سلسلة الإمداد — المخازن',12=>'المبيعات والعقود',
 17=>'المالية',26=>'التمويل والملكية',3=>'الأسطول',2=>'الموردون',4=>'الموارد البشرية',
 15=>'الحوكمة والالتزام',24=>'مركز البلاغات',27=>'المشغّلون والقوى التشغيلية');
$svr = array();      // route → [dept => role_kind]
foreach (q($conn, "SELECT route, dept, role_kind FROM screen_view_rows WHERE active=1 AND route IS NOT NULL") as $v)
    $svr[strtolower(trim($v['route']))][$v['dept']] = $v['role_kind'];
$noViewRow = array();
foreach (q($conn, "SELECT n.route, n.role_id FROM nav_items n WHERE n.active=1") as $n) {
    $rid = intval($n['role_id']);
    if (!isset($roleDept[$rid])) continue;            // الأدوارُ الفرعيةُ تتبع مالكها
    $rt = strtolower(trim($n['route']));
    if ($rt === 'main/my_workspace.php') continue;    // حاويةُ مساحة عملي — فوق الإدارات لا فيها
    if (!isset($svr[$rt])) continue;                  // شاشةٌ خارج مصفوفة العرض (ثوابت/جديدة)
    $dept = $roleDept[$rid];
    $kinds = $svr[$rt];
    if (isset($kinds['مساحة عملي'])) continue;        // عنصرُ مساحةِ عملي — لا صفَّ إدارةٍ له
    if (isset($kinds[$dept])) continue;               // له صفٌّ (مالكًا أو عارضًا)
    if (in_array('owner', $kinds, true) && count($kinds) === 1
        && array_key_first($kinds) === $dept) continue;
    // ظهورٌ عند إدارةٍ ليست مالكةً وبلا صفِّ عرضٍ معلن
    if (!in_array('owner', array(($kinds[$dept] ?? '')), true) && !isset($kinds[$dept]))
        $noViewRow[] = $n['route'] . ' @ ' . $dept;
}
$noViewRow = array_unique($noViewRow);
$out[] = array('⑪','ظهورٌ لغير المالك بلا صفِّ عرض', count($noViewRow), true, array_slice($noViewRow,0,12));

/* ── ⑫ لا احتكار (ACT-01 v6): ذو مصلحةٍ معلَنٍ بلا ظهورٍ حي — تنبيه ─────── */
$deptRole = array_flip($roleDept);
$liveRoleRoutes = array();
foreach (q($conn, "SELECT DISTINCT role_id, route FROM nav_items WHERE active=1") as $n)
    $liveRoleRoutes[intval($n['role_id'])][strtolower(trim($n['route']))] = 1;
$monopoly = array();
foreach (q($conn, "SELECT route, dept FROM screen_view_rows WHERE active=1 AND role_kind='viewer' AND route IS NOT NULL") as $v) {
    $rid = $deptRole[$v['dept']] ?? null;
    if ($rid === null) continue;
    $rt = strtolower(trim($v['route']));
    if (!isset($liveRoleRoutes[$rid][$rt])) $monopoly[] = $v['route'] . ' ← ' . $v['dept'];
}
$out[] = array('⑫','ذو مصلحةٍ معلَنٍ بلا ظهورٍ حي (تنبيه)', count($monopoly), false, array_slice($monopoly,0,10));

/* ── ⑬ إنجازي وبوابتي (ACT-01 v6): لكل حسابٍ بلا استثناء ────────────────── */
$wsrc = @file_get_contents($root . '/main/my_workspace.php') ?: '';
$missA = (mb_strpos($wsrc, 'إنجازي') === false) ? 1 : 0;
$missG = (mb_strpos($wsrc, 'بوابتي') === false) ? 1 : 0;
$out[] = array('⑬','إنجازي وبوابتي غائبان عن مساحة عملي', $missA + $missG, true,
               array_filter(array($missA ? 'إنجازي غائب' : '', $missG ? 'بوابتي غائب' : '')));

/* ── التقرير — بمقاماتِ تغطيةٍ موجبة (ACT-01 v6 §5.1) ───────────────────── */
$fail = 0;
echo "════ فحوصُ الاتصال الثلاثةَ عشرَ — ACT-01 v6 §5 ════\n";
// «صفرُ الأزرار المستخرجة يعني أن الماسحَ لم يعمل لا أن النظامَ نظيف»
$denomButtons = 0;
foreach ($dirs as $d) {
    $base = basename($d);
    if (in_array($base, array('vendor','node_modules','database','docs','tools','logs','app','tests','.git','.claude','.ssdiff'))) continue;
    foreach (glob($d . '/*.php') as $f) {
        $src = @file_get_contents($f); if ($src === false) continue;
        if (preg_match_all('~["\'](?:\.\./)?[A-Za-z_][\w/]*/(?:[\w]*handler[\w]*|get_[\w]+)\.php~u', $src, $mm)) $denomButtons += count($mm[0]);
    }
}
$denomActions = count(q($conn, "SELECT action_code FROM actions WHERE active=1"));
$denomNav     = count(q($conn, "SELECT id FROM nav_items WHERE active=1"));
$denomSvr     = count(q($conn, "SELECT svr_id FROM screen_view_rows WHERE active=1"));
printf("مقاماتُ التغطية (موجبةٌ لزامًا): استدعاءاتُ أزرار=%d · أفعال=%d · روابط=%d · صفوفُ عرض=%d\n",
       $denomButtons, $denomActions, $denomNav, $denomSvr);
if ($denomButtons === 0 || $denomActions === 0 || $denomNav === 0)
    { echo "✘ مقامٌ صفري — الماسحُ لم يعمل\n"; $fail++; }
foreach ($out as $o) {
    list($n, $name, $cnt, $blocking, $details) = $o;
    $mark = $cnt === 0 ? '✔' : ($blocking ? '✘' : '⚠');
    printf("%s %s %-42s : %d\n", $mark, $n, $name, $cnt);
    if ($cnt > 0 && $details) foreach ($details as $d) echo "     · $d\n";
    if ($cnt > 0 && $blocking) $fail++;
}
echo str_repeat('─', 60) . "\n";
echo $fail === 0 ? "الحكم: ✔ صفرٌ في الحاكمة — الدمجُ مسموح\n"
                 : "الحكم: ✘ $fail فحصًا حاكمًا غيرَ صفري" . ($enforce ? " — الدمجُ ممنوع\n" : " (وضعُ الرصد — لا منع)\n");
exit($enforce && $fail > 0 ? 1 : 0);
