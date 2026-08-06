<?php
/**
 * tools/proc_nav_module_fix.php — إصلاحات القائمة/الموديولات للمشتريات (جولة 2026-08-06)
 * ═══════════════════════════════════════════════════════════════════════════
 * ثلاث عملياتٍ idempotent بقرار المالك «حلّ المهام الحرجة»:
 *   ① تسجيل موديول Procurement/po_match.php (كان غير مسجَّل — فحارس M-14
 *      يرفضه فور تحويل الشاشة إلى proc_page_perms) + صلاحيات الدور 16 كاملة.
 *   ② ربط صفّ القائمة 6086 (مطابقة الفاتورة) بالموديول الوليد permission_code.
 *   ③ تفعيل صفوف القائمة القائمة والمعطَّلة للشاشات الثلاث اليتيمة:
 *      issue_proc (الصرف — عمليةٌ جوهرية) · reordering_proc · suppliers_proc
 *      (كانت active=0 في مجموعة «خارج الوثيقة» فلا سبيلَ إليها إلا بالرابط).
 *
 * php tools/proc_nav_module_fix.php            # dry-run (عرض فقط)
 * php tools/proc_nav_module_fix.php --apply    # تنفيذ
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/env.php';

$APPLY = in_array('--apply', $argv, true);
$conn = new mysqli(ems_env('DB_HOST', 'localhost'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), (int) ems_env('DB_PORT', 3306));
$conn->set_charset('utf8mb4');
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$o('══ proc_nav_module_fix — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' ══');

$conn->begin_transaction();
try {
    // ① موديول po_match
    $mid = null;
    $r = $conn->query("SELECT id FROM modules WHERE code = 'Procurement/po_match.php'");
    if ($r && $r->num_rows) {
        $mid = intval($r->fetch_assoc()['id']);
        $o("① موديول po_match مسجَّل سلفًا: #$mid");
    } else {
        $o('① سيُسجَّل موديول Procurement/po_match.php (owner_role_id=16)');
        if ($APPLY) {
            $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, group_id, is_link, is_quick, icon, display_order)
                                  VALUES ('مطابقة الفاتورة بالأمر والاستلام', 'Procurement/po_match.php', 16, NULL, 1, 0, 'fa fa-scale-balanced', 101)");
            $st->execute();
            $mid = intval($conn->insert_id);
            $o("   → أُدرج #$mid");
        }
    }

    if ($mid) {
        $r = $conn->query("SELECT id FROM role_permissions WHERE role_id = 16 AND module_id = $mid");
        if ($r && $r->num_rows) {
            $o('   صلاحيات الدور 16 قائمة');
        } else {
            $o('   ستُمنح صلاحيات الدور 16 كاملةً عليه');
            if ($APPLY) {
                $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                              VALUES (16, $mid, 1, 1, 1, 1)");
            }
        }
        // ② ربط صف القائمة
        $o('② ربط nav_items#6086 بالموديول وpermission_code');
        if ($APPLY) {
            $conn->query("UPDATE nav_items SET module_id = $mid, permission_code = 'Procurement/po_match.php' WHERE id = 6086");
            $o('   → صفوف: ' . $conn->affected_rows);
        }
    }

    // ③ تفعيل اليتيمات الثلاث
    $r = $conn->query("SELECT id, label_ar, active FROM nav_items WHERE id IN (200, 201, 202)");
    while ($row = $r->fetch_assoc()) {
        $o('③ nav#' . $row['id'] . ' «' . $row['label_ar'] . '» active=' . $row['active'] . ($row['active'] ? ' (لا حاجة)' : ' → 1'));
    }
    if ($APPLY) {
        $conn->query("UPDATE nav_items SET active = 1 WHERE id IN (200, 201, 202) AND active = 0");
        $o('   → فُعِّل: ' . $conn->affected_rows);
    }

    if ($APPLY) { $conn->commit(); $o('✔ COMMITTED'); }
    else        { $conn->rollback(); $o('— dry-run: لا تغيير'); }
} catch (\Throwable $t) {
    $conn->rollback();
    $o('✘ ROLLED BACK: ' . $t->getMessage());
    exit(1);
}
