<?php
/**
 * tools/tenant_leak_probe.php — اختبار تسرب العزل الشامل (AC-E06-05)
 * ───────────────────────────────────────────────────────────────────────────
 * «العزل يُختبر آليًّا» كان جزئيًّا (TenantDb fail-closed + nfr) بلا مسح شامل.
 * هذا يجس **كل** جدول مستأجرٍ في عقد TenantRegistry:
 *   ① العمود قائم فعلًا (عقدٌ لا يكذب على المخطط)
 *   ② صفر صفوف يتيمة العزل (company_id NULL/0) — كل يتيمٍ سطحُ تسربٍ محتمل
 *   ③ صفر صفوف لكيانٍ غير مسجل في admin_companies (كيان شبح)
 * والاستثناء الوحيد معلَن: جداول soft-linked المصنفة كذلك في العقد نفسه.
 * التشغيل: php tools/tenant_leak_probe.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
require_once __DIR__ . '/../app/Core/TenantRegistry.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$ref = new ReflectionClass('App\\Core\\TenantRegistry');
$tables = $ref->getConstant('TABLES');
if (!$tables) { // قد تكون خاصية ساكنة لا ثابتة
    $props = $ref->getStaticProperties();
    foreach ($props as $p) { if (is_array($p) && isset($p['users'])) { $tables = $p; break; } }
}
if (!$tables) {
    // قراءة مباشرة من المصدر (المصفوفة معرفة heredoc-style)
    fwrite(STDOUT, "تعذر عكس العقد — جسّ نصي\n");
    exit(2);
}

$cos = array();
$r = mysqli_query($conn, "SELECT id FROM admin_companies");
while ($r && ($x = mysqli_fetch_row($r))) { $cos[] = intval($x[0]); }
$coIn = implode(',', $cos ?: array(0));

$checked = 0; $missingCol = array(); $orphans = array(); $ghosts = array(); $absent = 0;
foreach ($tables as $t => $def) {
    if (!is_array($def) || ($def['type'] ?? '') !== 'tenant') { continue; }
    $ex = mysqli_query($conn, "SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    if (!$ex || !mysqli_num_rows($ex)) { $absent++; continue; }
    // المناظير إسقاطات — عزلها من جداولها الأم (جسّها ازدواج عدّ)
    $tt = mysqli_query($conn, "SELECT TABLE_TYPE FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $conn->real_escape_string($t) . "'");
    if ($tt && ($ty = mysqli_fetch_row($tt)) && stripos((string) $ty[0], 'VIEW') !== false) { continue; }
    $c = mysqli_query($conn, "SHOW COLUMNS FROM `{$t}` LIKE 'company_id'");
    if (!$c || !mysqli_num_rows($c)) { $missingCol[] = $t; continue; }
    $checked++;
    $soft = !empty($def['soft']);
    $r = mysqli_query($conn, "SELECT COUNT(*) FROM `{$t}` WHERE company_id IS NULL OR company_id = 0");
    $n = $r ? intval(mysqli_fetch_row($r)[0]) : 0;
    if ($n > 0 && !$soft) { $orphans[] = "{$t}: {$n} يتيم العزل"; }
    $r = mysqli_query($conn, "SELECT COUNT(*) FROM `{$t}` WHERE company_id IS NOT NULL AND company_id > 0
                              AND company_id NOT IN ({$coIn})");
    $n = $r ? intval(mysqli_fetch_row($r)[0]) : 0;
    if ($n > 0) { $ghosts[] = "{$t}: {$n} لكيانٍ شبح"; }
}

fwrite(STDOUT, "جُس: {$checked} جدول مستأجر · غائب عن القاعدة (بيئة أخرى): {$absent}\n");
$fail = 0;
if ($missingCol) { $fail++; fwrite(STDOUT, "✘ عقدٌ يكذب على المخطط (" . count($missingCol) . "): " . implode('، ', $missingCol) . "\n"); }
else { fwrite(STDOUT, "✔ ① كل جداول العقد تحمل عمود العزل فعلًا\n"); }
if ($orphans) { $fail++; fwrite(STDOUT, "✘ يتامى العزل:\n"); foreach ($orphans as $o) { fwrite(STDOUT, "  · {$o}\n"); } }
else { fwrite(STDOUT, "✔ ② صفر صفوف يتيمة العزل (خارج soft المعلنة)\n"); }
if ($ghosts) { $fail++; fwrite(STDOUT, "✘ كيانات شبح:\n"); foreach ($ghosts as $g) { fwrite(STDOUT, "  · {$g}\n"); } }
else { fwrite(STDOUT, "✔ ③ صفر صفوف لكيانٍ غير مسجل\n"); }
fwrite(STDOUT, $fail ? "الحكم: ✘ {$fail} فئة تسرب\n" : "الحكم: ✔ العزل مجسوس شاملًا — صفر تسرب\n");
exit($fail ? 1 : 0);
