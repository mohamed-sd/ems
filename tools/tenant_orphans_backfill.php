<?php
/**
 * tools/tenant_orphans_backfill.php — ردم يتامى العزل بالاشتقاق (م-و · AC-E06-05)
 * ───────────────────────────────────────────────────────────────────────────
 * كل صفٍّ بلا company_id سطحُ تسربٍ محتمل. الردم بالاشتقاق من علاقات الصف
 * الحقيقية (المستخدم · العقد · المورد · الوحدة الأم) — وما لا مشتقَّ له
 * يُنسب للكيان التجريبي 4 موسومًا (ق-15: كل البيانات تجريبية). لا حذف.
 * idempotent. التشغيل: php tools/tenant_orphans_backfill.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$fix = function ($label, $sql) use ($conn) {
    $ok = mysqli_query($conn, $sql);
    fwrite(STDOUT, ($ok ? '✔ ' : '✘ ') . $label . ': ' . ($ok ? mysqli_affected_rows($conn) : mysqli_error($conn)) . "\n");
};

/* ① سجلات النشاط: من شركة صاحب السجل، وإلا الكيان التجريبي */
$fix('activity_logs من مستخدمها', "UPDATE activity_logs al JOIN users u ON u.id = al.user_id
    SET al.company_id = u.company_id
    WHERE (al.company_id IS NULL OR al.company_id = 0) AND u.company_id > 0");
$fix('activity_logs المتبقي ← 4 [ق-15]', "UPDATE activity_logs SET company_id = 4
    WHERE company_id IS NULL OR company_id = 0");

/* ② معدات العقود: من عقدها */
$fix('contractequipments من عقدها', "UPDATE contractequipments ce JOIN contracts c ON c.id = ce.contract_id
    SET ce.company_id = c.company_id
    WHERE (ce.company_id IS NULL OR ce.company_id = 0) AND c.company_id > 0");
$fix('contractequipments المتبقي ← 4 [ق-15]', "UPDATE contractequipments SET company_id = 4
    WHERE company_id IS NULL OR company_id = 0");

/* ③ ملاحظات عقود الموردين: من عقد المورد */
$fix('supplier_contract_notes من عقدها', "UPDATE supplier_contract_notes n
    JOIN supplierscontracts sc ON sc.id = n.contract_id
    SET n.company_id = sc.company_id
    WHERE (n.company_id IS NULL OR n.company_id = 0) AND sc.company_id > 0");
$fix('supplier_contract_notes المتبقي ← 4 [ق-15]', "UPDATE supplier_contract_notes SET company_id = 4
    WHERE company_id IS NULL OR company_id = 0");

/* ④ الوحدة التنظيمية «الموردون» (كانت co=0 موازية) والتكليفات التنظيمية */
$fix('org_units ← 4 [ق-15]', "UPDATE org_units SET company_id = 4 WHERE company_id IS NULL OR company_id = 0");
$fix('org_assignments من وحدتها', "UPDATE org_assignments oa JOIN org_units ou ON ou.unit_id = oa.unit_id
    SET oa.company_id = ou.company_id
    WHERE (oa.company_id IS NULL OR oa.company_id = 0) AND ou.company_id > 0");
$fix('org_assignments المتبقي ← 4 [ق-15]', "UPDATE org_assignments SET company_id = 4
    WHERE company_id IS NULL OR company_id = 0");
fwrite(STDOUT, "اكتمل الردم — أعد tenant_leak_probe\n");
