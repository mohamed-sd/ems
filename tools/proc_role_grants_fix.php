<?php
/**
 * tools/proc_role_grants_fix.php — منحُ دور المشتريات (16) تعديلَ دورة RFQ
 * ═══════════════════════════════════════════════════════════════════════════
 * المقيس (جولة حل مشاكل الدور 2026-08-06): موديول Suppliers/rfq_requests.php
 * (#171) ممنوحٌ للدور 16 عرضًا وإضافةً **بلا تعديل** — وأفعالُ الدورة كلُّها
 * (تسجيل عرض · إرسال · إقفال · ترسية · متعاقَد) خلف can_edit؛ فمديرُ المشتريات
 * يفتح الطلبَ ثم يقف. المنحة: can_edit=1 (والحذفُ يبقى 0 — لا حذفَ في الدورة).
 *
 * php tools/proc_role_grants_fix.php --apply   (بلا وسيطةٍ: عرضٌ فقط)
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/env.php';

$APPLY = in_array('--apply', $argv, true);
$conn = new mysqli(ems_env('DB_HOST', 'localhost'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), (int) ems_env('DB_PORT', 3306));
$conn->set_charset('utf8mb4');

$r = $conn->query("SELECT rp.id, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
                     FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                    WHERE m.code = 'Suppliers/rfq_requests.php' AND rp.role_id = 16 LIMIT 1");
$row = $r ? $r->fetch_assoc() : null;
if (!$row) { fwrite(STDOUT, "لا صفَّ صلاحياتٍ — لا شيءَ يُعدَّل\n"); exit(1); }
fwrite(STDOUT, 'الحالي: ' . json_encode($row) . "\n");
if (intval($row['can_edit']) === 1) { fwrite(STDOUT, "can_edit ممنوحةٌ سلفًا ✓\n"); exit(0); }
if ($APPLY) {
    $conn->query('UPDATE role_permissions SET can_edit = 1 WHERE id = ' . intval($row['id']));
    fwrite(STDOUT, "✔ مُنحت can_edit للدور 16 على RFQ (#171)\n");
} else {
    fwrite(STDOUT, "— dry-run: أضف --apply للتنفيذ\n");
}
