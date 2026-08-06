<?php
/**
 * tools/report_grant_procurement_summary.php — بذرُ صلاحية تقرير «ملخص المشتريات»
 * ═══════════════════════════════════════════════════════════════════════════
 * الرمزُ الجديد procurement_summary لا يلتقطه h11 (كتالوجُه من الجدول نفسِه) —
 * فيُبذر هنا بنفس فلسفته «الصلاحيةُ تتبع الباب»: يُمنح لكل دورٍ تطابق أنماطُه
 * الرمزَ في خريطة h11 (بادئة procurement_ أو لاحقة _summary) + الدور 1.
 * idempotent — الصفُّ القائم لا يُكرر.
 *
 * php tools/report_grant_procurement_summary.php --apply   (بلا وسيطة: عرض)
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/env.php';

$APPLY = in_array('--apply', $argv, true);
$conn = new mysqli(ems_env('DB_HOST', 'localhost'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), (int) ems_env('DB_PORT', 3306));
$conn->set_charset('utf8mb4');
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$CODE = 'procurement_summary';
// خريطةُ h11 نفسُها (النسخةُ المصدر في tools/h11_report_grants.php)
$map = array(
    1  => array('*'),
    9  => array('_summary'),
    15 => array('_summary'),
    16 => array('supplier_', 'procurement_', '_summary'),
    17 => array('contracts_', 'project_', '_summary'),
    18 => array('contracts_', '_summary'),
    19 => array('contracts_', 'project_', '_summary'),
    20 => array('_summary', '_detailed'),
    21 => array('_summary'),
    22 => array('_summary'),
    24 => array('_summary'),
    25 => array('supplier_', '_summary'),
    26 => array('contracts_', '_summary'),
);
$matches = function (array $pats) use ($CODE) {
    foreach ($pats as $p) {
        if ($p === '*') { return true; }
        if (substr($p, 0, 1) === '_') { if (substr($CODE, -strlen($p)) === $p) { return true; } }
        elseif (strpos($CODE, $p) === 0) { return true; }
    }
    return false;
};

$granted = 0;
foreach ($map as $rid => $pats) {
    if (!$matches($pats)) { continue; }
    $r = $conn->query("SELECT id FROM report_role_permissions WHERE role_id = $rid AND report_code = '$CODE'");
    if ($r && $r->num_rows) { $o("الدور $rid: ممنوحٌ سلفًا ✓"); continue; }
    $o("الدور $rid: سيُمنح $CODE");
    if ($APPLY) {
        $conn->query("INSERT INTO report_role_permissions (role_id, report_code) VALUES ($rid, '$CODE')");
        $granted++;
    }
}
$o($APPLY ? "✔ مُنح لـ $granted دورًا" : '— dry-run: أضف --apply');
