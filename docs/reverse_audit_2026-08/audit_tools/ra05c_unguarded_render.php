<?php
/**
 * ra05c_unguarded_render.php — الشاشاتُ المسجَّلةُ التي تُصيَّر بلا حارسِ صلاحية
 * ═══════════════════════════════════════════════════════════════════════════
 * الاكتشافُ الحيّ: شاشاتٌ **مسجَّلةٌ** في modules صُيِّرت (200 + قشرة) للقارئِ
 * الضيّقِ (الدور 22) الذي **لا منحةَ له عليها** — لأنها لا تنادي
 * enforce_current_page_view_permission ولا تُحَلُّ إلى null (فلا يمسكها صمّامُ
 * fail-closed). فتنجو من الشبكتين.
 *
 * ◆ تُقصى الإعفاءاتُ المصرَّحُ بها في enforce (dashboard · chats · breakdowns ·
 *   tickets_list · ticket_form) — فتلك مفتوحةٌ بقرار.
 * المخرَج: evidence/unguarded_render.json
 */
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$db = mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);

$exempt = ['main/dashboard.php', 'chats/', 'Maintenance/breakdowns.php',
           'Tickets/tickets_list.php', 'Tickets/ticket_form.php', 'emsreports/'];
$isExempt = function (string $p) use ($exempt): bool {
    foreach ($exempt as $e) { if (strpos($p, $e) !== false) { return true; } }
    return false;
};

/* الشاشاتُ المسجَّلة */
$mods = [];
$r = $db->query("SELECT code FROM modules WHERE code LIKE '%.php'");
while ($x = $r->fetch_row()) { $mods[str_replace('\\', '/', $x[0])] = true; }

$out = ['by_role' => [], 'unguarded_registered_files' => []];

/* أولًا: أيُّ ملفٍّ مسجَّلٍ لا ينادي الحارسَ نهائيًّا؟ (تحليلٌ ساكن) */
$noGuardFiles = [];
foreach (array_keys($mods) as $p) {
    $abs = $ROOT . '/' . $p;
    if (!is_file($abs)) { continue; }
    $src = file_get_contents($abs);
    $callsGuard = strpos($src, 'enforce_current_page_view_permission') !== false
               || preg_match('/ems_guard_handler|AuthorityGuard::sign|ems_require_permission|super_admin_require_login/', $src);
    /* insidebar يستدعي الحارسَ في سطره 13 — تضمينُه حراسة */
    $inclSidebar = (bool) preg_match('~include[^;]*insidebar\.php~', $src);
    if (!$callsGuard && !$inclSidebar) { $noGuardFiles[] = $p; }
}
$out['unguarded_registered_files'] = $noGuardFiles;

/* ثانيًا: من هذه، أيُّها صُيِّر حيًّا (200+قشرة) للقارئِ بلا منحة؟ */
foreach (['reader' => 22, 'admin' => 1] as $tag => $roleId) {
    $grant = [];
    $rr = $db->query("SELECT m.code FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                      WHERE rp.role_id={$roleId} AND rp.can_view=1 AND m.code LIKE '%.php'");
    while ($x = $rr->fetch_row()) { $grant[str_replace('\\', '/', $x[0])] = true; }

    $rendered = [];
    foreach (file($EV . '/live_http_' . $tag . '.jsonl', FILE_IGNORE_NEW_LINES) as $l) {
        $rec = json_decode($l, true);
        if ($rec && $rec['code'] === 200 && !empty($rec['shell'])) { $rendered[$rec['path']] = true; }
    }

    $leak = [];
    foreach (array_keys($rendered) as $p) {
        if ($isExempt($p)) { continue; }          /* مفتوحةٌ بقرار */
        if (isset($grant[$p])) { continue; }        /* ممنوحةٌ فلا تسريب */
        if (!isset($mods[$p])) { continue; }        /* غيرُ مسجَّلةٍ: عيبٌ آخر (fail-closed) */
        $leak[] = $p;
    }
    sort($leak);
    $out['by_role'][$tag] = ['role_id' => $roleId, 'grants' => count($grant),
                             'leaked_screens' => $leak, 'leak_count' => count($leak)];
}

file_put_contents($EV . '/unguarded_render.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("ملفاتٌ مسجَّلةٌ بلا نداءِ حارسٍ ولا insidebar: %d\n", count($noGuardFiles));
foreach ($out['by_role'] as $tag => $a) {
    printf("\n═ %s (دور %d · %d منحة): تسريبُ %d شاشةٍ مسجَّلةٍ بلا منحةٍ ولا إعفاء\n",
        $tag, $a['role_id'], $a['grants'], $a['leak_count']);
    foreach ($a['leaked_screens'] as $p) { echo "   ⚠ $p\n"; }
}
