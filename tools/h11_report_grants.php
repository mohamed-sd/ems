<?php
/**
 * tools/h11_report_grants.php — بذرُ صلاحيات مركز التقارير (ح-11) · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * المشكلة: 21 دورًا يحمل في قائمته رابطًا حيًّا إلى «مركز التقارير»، بينما
 * `report_role_permissions` مبذورةٌ لثلاثة أدوار فقط (1 · 13 · 14) — فالرابطُ
 * يُعيد صاحبَه إلى اللوحة بلا تفسير.
 *
 * الحكمُ ولماذا المنحُ لا الإخفاء:
 *   ① النيّةُ موثَّقةٌ في `emsreports/setup_permissions.php`: `$roles = [-1..9]`
 *      ونصُّه «تم منح جميع الأدوار وصولاً كاملاً لكل التقارير». السكربتُ كُتب
 *      قبل وجود الأدوار 10–27 فبقيت بلا بذر — نقصُ بذرٍ لا منعٌ مقصود.
 *   ② وثيقةُ القوائم (NAV) هي مصدرُ الحقيقة والقوائمُ تُولَّد منها؛ وهي وضعت
 *      الرابطَ لهذه الأدوار. فإخفاؤه يخالف المصدر، ومنحُه يتبعه.
 *   ③ تقاريرُ المركز **قراءةٌ وتصديرٌ فقط** (لا كتابة) — فالمنحُ لا يفتح فعلًا.
 *
 * وخلافًا لسكربت 2024 لا نمنح الكلَّ للكل: لكلِّ دورٍ تقاريرُ مجالِه بحسب
 * بادئة الرمز — انسجامًا مع «الصلاحيةُ تتبع الباب» المشدودة في SEC-GOV.
 *
 * php tools/h11_report_grants.php            → تجريب
 * php tools/h11_report_grants.php --apply    → تنفيذ
 * php tools/h11_report_grants.php --revert   → نزعُ ما مُنح بهذه الأداة
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$APPLY  = in_array('--apply', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$LEDGER = __DIR__ . '/../storage/backups/h11_report_grants.json';

/** مجالُ كلِّ دورٍ من كتالوج الرموز الأربعة والعشرين. */
$DOMAIN = array(
    2  => array('supplier_', 'contracts_'),                       // الموردون
    3  => array('fleet_', 'timesheet_by_equipment'),              // الأسطول
    4  => array('drivers_', 'timesheet_by_driver'),               // الموارد البشرية
    5  => array('timesheet_', 'operations_'),                     // الموقع (قديم)
    6  => array('timesheet_', 'operations_', 'fleet_operations'), // الموقع
    7  => array('project_', 'contracts_', 'timesheet_by_project'),// مشرف مشاريع
    8  => array('supplier_'),                                     // مشرف موردين
    9  => array('_summary'),                                      // التنفيذية — الملخصات
    10 => array('fleet_'),                                        // مشرف أسطول
    11 => array('fleet_', 'timesheet_by_equipment'),              // مشغل أسطول
    12 => array('contracts_', 'project_', 'timesheet_by_project'),// المبيعات
    13 => array('maintenance_', 'fleet_equipment_'),              // الصيانة
    14 => array('maintenance_', 'fleet_equipment_'),              // مشرف صيانة
    15 => array('_summary'),                                      // الصلاحيات — الملخصات
    16 => array('supplier_', '_summary'),                         // المشتريات
    17 => array('contracts_', 'project_', '_summary'),            // المالية
    18 => array('contracts_', '_summary'),
    19 => array('contracts_', 'project_', '_summary'),
    20 => array('_summary', '_detailed'),                         // المراجع — قراءةٌ واسعة
    21 => array('_summary'),
    22 => array('_summary'),
    23 => array('fleet_', 'operations_'),                         // النقل
    24 => array('_summary'),                                      // البلاغات
    25 => array('supplier_', '_summary'),                         // المستودع
    26 => array('contracts_', '_summary'),                        // التمويل
    27 => array('drivers_', 'timesheet_by_driver'),               // القوى التشغيلية
);

// كتالوجُ الرموز من الجدول نفسِه (لا قائمةٌ مثبَّتةٌ في الكود)
$codes = array();
$q = mysqli_query($conn, "SELECT DISTINCT report_code FROM report_role_permissions ORDER BY report_code");
while ($r = mysqli_fetch_assoc($q)) { $codes[] = $r['report_code']; }
$o('══ بذرُ صلاحيات مركز التقارير (ح-11) ══');
$o('  كتالوجُ الرموز: ' . count($codes));

if ($REVERT) {
    if (!file_exists($LEDGER)) { $o('  لا سجلَّ منحٍ سابق.'); exit(1); }
    $rows = json_decode(file_get_contents($LEDGER), true);
    $n = 0;
    foreach ((array) $rows as $g) {
        $st = $conn->prepare("DELETE FROM report_role_permissions WHERE role_id = ? AND report_code = ?");
        if (!$st) { continue; }
        $rid = intval($g['role_id']); $rc = $g['report_code'];
        $st->bind_param('is', $rid, $rc);
        $st->execute(); $n += $st->affected_rows; $st->close();
    }
    $o('  نُزع: ' . $n . ' منحًا');
    exit(0);
}

// الأدوارُ التي لها رابطٌ حيٌّ وليس لها أي صفّ
$targets = array();
$q = mysqli_query($conn, "SELECT DISTINCT n.role_id FROM nav_items n
     WHERE n.active = 1 AND n.route LIKE '%emsreports%'
       AND NOT EXISTS (SELECT 1 FROM report_role_permissions p WHERE p.role_id = n.role_id)
     ORDER BY n.role_id");
while ($r = mysqli_fetch_assoc($q)) { $targets[] = intval($r['role_id']); }
$o('  أدوارٌ برابطٍ حيٍّ بلا صلاحية: ' . count($targets));

$plan = array();
foreach ($targets as $rid) {
    $pref = isset($DOMAIN[$rid]) ? $DOMAIN[$rid] : array('_summary');
    foreach ($codes as $c) {
        foreach ($pref as $p) {
            if (strpos($c, $p) !== false) { $plan[] = array('role_id' => $rid, 'report_code' => $c); break; }
        }
    }
}
$byRole = array();
foreach ($plan as $g) { $byRole[$g['role_id']] = (isset($byRole[$g['role_id']]) ? $byRole[$g['role_id']] : 0) + 1; }
foreach ($byRole as $rid => $n) { $o(sprintf('    دور %-4s → %s تقريرًا', $rid, $n)); }
$o('  إجماليُّ المنح المزمعة: ' . count($plan));

if (!$APPLY) { $o('  (تجريبٌ — أعِد التشغيل بـ --apply)'); exit(0); }

$dir = dirname($LEDGER);
if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
file_put_contents($LEDGER, json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$ins = 0;
foreach ($plan as $g) {
    $st = $conn->prepare("INSERT IGNORE INTO report_role_permissions (role_id, report_code) VALUES (?, ?)");
    if (!$st) { continue; }
    $rid = $g['role_id']; $rc = $g['report_code'];
    $st->bind_param('is', $rid, $rc);
    $st->execute(); $ins += $st->affected_rows; $st->close();
}
$o('  مُنح: ' . $ins . ' صفًّا · السجلُّ في ' . basename($LEDGER));

$q = mysqli_query($conn, "SELECT COUNT(DISTINCT n.role_id) c FROM nav_items n
     WHERE n.active = 1 AND n.route LIKE '%emsreports%'
       AND NOT EXISTS (SELECT 1 FROM report_role_permissions p WHERE p.role_id = n.role_id)");
$left = intval(mysqli_fetch_assoc($q)['c']);
$o('  روابطُ ميتةٌ متبقية: ' . $left . ($left === 0 ? '  ✓' : '  ✗'));
exit($left === 0 ? 0 : 1);
