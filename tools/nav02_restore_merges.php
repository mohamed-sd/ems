<?php
/**
 * update0006-b · E-01 — إرجاعُ الروابط الاثنتين والعشرين
 * ────────────────────────────────────────────────────────
 * المصفوفةُ تقول «دمجٌ في ملف العقد/المورد → **تصير تبويباتٍ**». وقد أُطفئت
 * الروابطُ في الموجة ③ **بلا بناء التبويبات**، وحُوّلت إلى `Contracts/contracts.php`
 * و`Suppliers/suppliers.php` — **وهما قائمتان لا ملفّان أمّان**.
 *
 * الحكم: يُرجَع الوصولُ حتى تُبنى التبويبات — «إخراجُ شاشةٍ قبل بناء بديلها
 * خسارةٌ صافية». وتُحذف التحويلاتُ المضلِّلة (الشاشةُ حيةٌ فلا تُحوَّل).
 * والدمجُ يبقى قرارًا معلَّقًا في `docs/nav02/PENDING_MERGES.md`.
 *
 * التشغيل: php tools/nav02_restore_merges.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$merged = array(
    // → ملفُّ عقد المشروع (15)
    'Clients/contract_amendments.php', 'Clients/contract_commitments.php', 'Clients/contract_events.php',
    'Contracts/contract_baseline.php', 'Contracts/contract_guarantees.php', 'Contracts/contract_lifecycle.php',
    'Contracts/contract_lines.php', 'Contracts/contract_monthly_plan.php', 'Contracts/contract_obligations.php',
    'Contracts/contract_payment_schedule.php', 'Contracts/contract_resource_plan.php', 'Contracts/contract_sites.php',
    'Contracts/penalties.php', 'Contracts/plan_actual_link.php', 'Contracts/price_terms.php',
    // → ملفُّ المورد (7)
    'Finance/supplier_statement_fin.php', 'Suppliers/supplier_capacity.php', 'Suppliers/supplier_closure.php',
    'Suppliers/supplier_contract_lines.php', 'Suppliers/supplier_documents.php', 'Suppliers/supplier_evaluation.php',
    'Suppliers/supplier_rules.php',
);

$on = 0; $rm = 0;
foreach ($merged as $r) {
    $e = mysqli_real_escape_string($conn, $r);
    mysqli_query($conn, "UPDATE nav_items SET active = 1 WHERE route = '$e' AND active = 0");
    $on += mysqli_affected_rows($conn);
    // التحويلُ مضلِّلٌ ما دامت الشاشةُ حيةً في القائمة
    mysqli_query($conn, "DELETE FROM nav_redirects WHERE old_route = '$e' AND hits = 0");
    $rm += mysqli_affected_rows($conn);
}
echo "أُرجع: $on رابطًا · حُذف: $rm تحويلًا مضلِّلًا\n";
$r = mysqli_query($conn, "SELECT COUNT(*) c FROM nav_items WHERE active = 1");
echo "الروابطُ الحيةُ الآن: " . mysqli_fetch_assoc($r)['c'] . "\n";
