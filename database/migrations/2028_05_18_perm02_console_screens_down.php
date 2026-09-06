<?php
/**
 * 2028_05_18_perm02_console_screens_down.php — عكسُ تسجيلِ شاشتَي الكونسول
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والعكسُ لا يقلب دورةَ الحياةِ إلى الوراء**: القالبُ الجديدُ (`*-V2`) نفذ
 *   وحمله موظّفون، وإسقاطُه يقطعهم عن النظامِ كلِّه (مغلقٌ افتراضيًّا). فالعكسُ
 *   هنا **ينزع الموضعَ والهويّةَ والتصريحَ** ويترك القالبَ قائمًا — ومن أراد
 *   ردَّ الإذنِ فبسحبِ البندَين من شاشةِ البناءِ بأثرٍ مسجَّل، لا بهجرة.
 *
 * ◆ **ويُنزع البندُ من القالبِ النافذِ بالاستبعادِ لا بالحذف** إن طُلب: ذاك فعلٌ
 *   يمرُّ بالمنفذِ المحروسِ ويترك سطرَه، وليس شأنَ ملفِّ عكس.
 *
 * التشغيل: php database/migrations/2028_05_18_perm02_console_screens_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$CODES = array('Governance/auth_profile_edit.php', 'Governance/perm_audit_log.php');
$TARGETS = array('NT-DEP-08-044', 'NT-DEP-08-045');

echo "══ عكسُ PERM-02 · نزعُ الموضعِ والهويّةِ والتصريح ═════════════════════\n";
$n = array('nav_workspace_placements' => 0, 'nav_placements' => 0, 'nav_targets' => 0,
           'nav_items' => 0, 'role_permissions' => 0, 'modules' => 0);

foreach ($CODES as $code) {
    $lower   = strtolower(str_replace('.php', '', $code));
    $withPhp = strtolower($code);
    $e = function ($s) use ($conn) { return $conn->real_escape_string($s); };

    $conn->query("DELETE FROM nav_workspace_placements WHERE route = '" . $e($lower) . "'");
    $n['nav_workspace_placements'] += $conn->affected_rows;
    $conn->query("DELETE FROM nav_placements WHERE route = '" . $e($withPhp) . "'");
    $n['nav_placements'] += $conn->affected_rows;
    $conn->query("DELETE FROM nav_items WHERE route = '" . $e($code) . "'");
    $n['nav_items'] += $conn->affected_rows;

    $mid = $one("SELECT id FROM modules WHERE code = '" . $e($code) . "' LIMIT 1");
    if ($mid > 0) {
        $conn->query("DELETE FROM role_permissions WHERE module_id = {$mid}");
        $n['role_permissions'] += $conn->affected_rows;
        $conn->query("DELETE FROM modules WHERE id = {$mid}");
        $n['modules'] += $conn->affected_rows;
    }
}
foreach ($TARGETS as $t) {
    $conn->query("DELETE FROM nav_targets WHERE target_id = '" . $conn->real_escape_string($t) . "'");
    $n['nav_targets'] += $conn->affected_rows;
}

foreach ($n as $t => $c) { printf("   %-28s صفوفٌ منزوعة: %d\n", $t, $c); }

$left = 0;
foreach ($CODES as $code) {
    $left += $one("SELECT COUNT(*) FROM gov_profile_items WHERE item_ref = '"
        . $conn->real_escape_string($code) . "' AND allow = 1");
}
printf("\n   ⚠ بنودُ القوالبِ الباقيةُ على الشاشتَين: %d\n", $left);
echo "     وهي تُنزع من شاشةِ البناءِ بالاستبعادِ لا بهجرةٍ، فيبقى لكلِّ نزعٍ سطرُه.\n";

require_once __DIR__ . '/_ledger.php';
if (function_exists('ems_migration_reverted')) { ems_migration_reverted(__FILE__, $conn); }
echo "\n✔ عُكس التسجيل — ودورةُ الحياةِ لم تُقلَب.\n";
