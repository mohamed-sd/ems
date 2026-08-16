<?php
/**
 * ra03b_perm_surface.php — سطحُ الصلاحياتِ والتسجيلِ والتنقّل (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * يقيس التطابقَ الثلاثيَّ: ملفٌّ على القرص ⇆ صفُّ modules ⇆ منحةُ دورٍ ⇆ رابطُ تنقّل.
 *  ① ملفاتٌ حيّةٌ غيرُ مسجَّلة  — بعد قلبِ fail-closed صارت «ميتة» لا «مفتوحة»
 *  ② تسجيلاتٌ بلا ملف          — أشباحُ سجلّ
 *  ③ وحداتٌ بلا أيِّ منحة       — مبنيٌّ لا يبلغه أحد
 *  ④ روابطُ تنقّلٍ إلى غيرِ مسجَّلٍ أو مفقود
 * المخرَج: evidence/perm_surface.json
 */
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$db = mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');

/* صفوف modules التي رمزُها مسار */
$mods = [];
$r = $db->query("SELECT id, code FROM modules WHERE code LIKE '%.php'");
while ($x = $r->fetch_assoc()) { $mods[str_replace('\\', '/', $x['code'])] = (int) $x['id']; }

/* منح لكل موديول */
$granted = [];
$r = $db->query('SELECT DISTINCT module_id FROM role_permissions WHERE can_view=1');
while ($x = $r->fetch_row()) { $granted[(int) $x[0]] = true; }

/* ملفات القرص في مجلدات الواجهة (نفس نطاق ra03a) */
$dirs = ['main' => 1];
$r = $db->query("SELECT DISTINCT SUBSTRING_INDEX(code,'/',1) d FROM modules WHERE code LIKE '%/%'");
while ($x = $r->fetch_row()) { $dirs[$x[0]] = 1; }
unset($dirs['includes'], $dirs['app'], $dirs['tools'], $dirs['tests'], $dirs['database'], $dirs['assets'], $dirs['vendor']);
$disk = [];
foreach (array_keys($dirs) as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $b = basename($f);
        if ($b[0] === '_') { continue; }
        $disk[$d . '/' . $b] = 1;
    }
}

/* ①/② التقاطعات */
$unregistered = array_values(array_diff(array_keys($disk), array_keys($mods)));
/* المعالجاتُ والنقاطُ المحروسةُ مركزيًّا ليست شاشاتِ سجلٍّ — تُفصل */
$unregScreens = array_values(array_filter($unregistered, fn($p) =>
    !preg_match('~/(get_|ajax_)|_handler\.php$|_ajax\.php$|/cron_|/login\.php|/logout|/forgot_|/reset_|/setup_~', $p)));
$ghost = array_values(array_diff(array_keys($mods), array_keys($disk)));
$ghost = array_values(array_filter($ghost, fn($p) => !is_file($ROOT . '/' . $p))); /* تأكيد */

/* ③ وحداتٌ بلا منحة */
$noGrant = [];
foreach ($mods as $code => $id) { if (!isset($granted[$id])) { $noGrant[] = $code; } }

/* ④ روابطُ التنقّل */
$navBad = ['missing_file' => [], 'unregistered' => []];
$r = $db->query("SELECT DISTINCT route FROM nav_items WHERE active=1 AND route LIKE '%.php%'");
while ($x = $r->fetch_row()) {
    $route = preg_replace('/[?#].*$/', '', str_replace(['../', './'], '', $x[0]));
    if ($route === '' || strpos($route, 'http') === 0) { continue; }
    if (!is_file($ROOT . '/' . $route)) { $navBad['missing_file'][] = $route; continue; }
    if (!isset($mods[$route]) && !preg_match('~excel\.php|dashboard~', $route)) { $navBad['unregistered'][] = $route; }
}

$out = [
    'disk_screen_files' => count($disk),
    'module_path_rows'  => count($mods),
    'unregistered_live_total' => count($unregistered),
    'unregistered_live_screens' => $unregScreens,
    'ghost_registrations' => $ghost,
    'modules_without_view_grant' => $noGrant,
    'nav_missing_file' => array_values(array_unique($navBad['missing_file'])),
    'nav_unregistered' => array_values(array_unique($navBad['unregistered'])),
];
file_put_contents($EV . '/perm_surface.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("قرص: %d · مسجَّل: %d\n", $out['disk_screen_files'], $out['module_path_rows']);
printf("① حيٌّ غيرُ مسجَّل: %d (منها شاشاتُ عرضٍ: %d)\n", $out['unregistered_live_total'], count($unregScreens));
printf("② شبحُ تسجيلٍ بلا ملف: %d\n", count($ghost));
printf("③ مسجَّلٌ بلا أيِّ منحةِ عرض: %d\n", count($noGrant));
printf("④ روابطُ تنقّلٍ لملفٍّ مفقود: %d · لغيرِ مسجَّل: %d\n", count($out['nav_missing_file']), count($out['nav_unregistered']));
foreach (array_slice($unregScreens, 0, 10) as $u) { echo "   غير مسجَّل⇐ $u\n"; }
foreach (array_slice($out['nav_missing_file'], 0, 6) as $u) { echo "   رابط مكسور⇐ $u\n"; }
