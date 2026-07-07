<?php
/**
 * فهرس ثوابت الأدوار — المصدر الوحيد المعتمد لأرقام الأدوار (ADR-07 · المرحلة 0)
 * ───────────────────────────────────────────────────────────────────────────
 * القاعدة (قائمة التجميد · EQUIP-ARC-R02 §5): يُمنع أي رقم دورٍ سحريٍّ جديدٍ
 * في الكود — الاستخدام عبر هذه الثوابت حصريًا:
 *
 *     if ($current_role === EMS_ROLE_MAINTENANCE_MGR) { ... }
 *     if (in_array($role, EMS_ROLES_FINANCE, true)) { ... }
 *
 * القيم نصوص (لا أعداد) لأن الجلسة تحمل الدور نصًا وكل المقارنات القائمة
 * صارمة (===). الأسماء العربية المتوقعة في EMS_ROLE_NAMES تُطابَق دوريًا مع
 * جدول roles الحي عبر ems_roles_verify_against_db() (مرة لكل جلسة من
 * config.php) — أي انحرافٍ (إعادة تسمية/حذف/تغيير رقم) يُسجَّل فورًا في
 * security.log بحدث role_constant_mismatch، فلا يتكرر ما حدث للدور 15
 * (أُعيدت تسميته بالخطأ وظل التوثيق يخالف الواقع أسابيع).
 *
 * ملاحظات بنيوية:
 * - الأرقام هي المفتاح المستقر (تُشير إليها users.role وrole_permissions
 *   وmodules.owner_role_id)؛ الأسماء قابلة للتعديل من شاشة الأدوار — لذلك
 *   الثوابت بالأرقام والأسماء وسيلةُ تحققٍ لا وسيلةُ ربط.
 * - الدور 9 غير موجود في القاعدة الحية (فجوة ترقيم تاريخية) — لا ثابت له.
 * - «المنصب» (جسر ADR-07 الكامل) يُبنى فوق هذا الفهرس في المرحلة 1.
 */

// ── النظام والإدارة ──────────────────────────────────────────────────────────
const EMS_ROLE_SUPER_ADMIN = '-1';            // الأدمن الأعلى (ليس صفًا في roles)
const EMS_ROLE_PERMISSIONS_MGR = '15';        // مدير الصلاحيات

// ── التشغيل والحركة ─────────────────────────────────────────────────────────
const EMS_ROLE_OPERATIONS_MGR = '1';          // ادارة التشغيل
const EMS_ROLE_SITE_MGR = '5';                // مدير الموقع (نطاق mine)
const EMS_ROLE_MOVEMENT_MGR = '6';            // مدير حركة وتشغيل (نطاق mine)
const EMS_ROLE_PROJECTS_SUPERVISOR = '7';     // مشرف - مشاريع

// ── الموردون والأسطول ────────────────────────────────────────────────────────
const EMS_ROLE_SUPPLIERS_MGR = '2';           // ادارة الموردين
const EMS_ROLE_FLEET_MGR = '3';               // ادارة الاسطول
const EMS_ROLE_SUPPLIERS_SUPERVISOR = '8';    // مشرف موردين
const EMS_ROLE_FLEET_SUPERVISOR = '10';       // مشرف اسطول
const EMS_ROLE_FLEET_OPERATOR = '11';         // مشغل اسطول

// ── الأقسام المتخصصة ────────────────────────────────────────────────────────
const EMS_ROLE_HR_MGR = '4';                  // ادارة الموارد البشرية
const EMS_ROLE_SALES_MGR = '12';              // ادارة المبيعات
const EMS_ROLE_MAINTENANCE_MGR = '13';        // ادارة الصيانة
const EMS_ROLE_MAINTENANCE_SUPERVISOR = '14'; // مشرف صيانة
const EMS_ROLE_PROCUREMENT_MGR = '16';        // مسؤول المشتريات
const EMS_ROLE_TRANSPORT_MGR = '23';          // مدير النقل والترحيل

// ── الإدارة المالية (D04) ────────────────────────────────────────────────────
const EMS_ROLE_CFO = '17';                    // المدير المالي
const EMS_ROLE_FIN_ACCOUNTANT = '18';         // محاسب الإدارة المالية
const EMS_ROLE_FIN_DEPT_MGR = '19';           // مدير الإدارة المالية
const EMS_ROLE_FIN_AUDITOR = '20';            // المراجع والمدقق المالي
const EMS_ROLE_FIN_TREASURER = '21';          // أمين الخزينة
const EMS_ROLE_FIN_READER = '22';             // قارئ مالي

// ── مجموعات جاهزة (بديل مصفوفات allowed_roles المبعثرة) ─────────────────────
const EMS_ROLES_FINANCE = array(
    EMS_ROLE_CFO, EMS_ROLE_FIN_ACCOUNTANT, EMS_ROLE_FIN_DEPT_MGR,
    EMS_ROLE_FIN_AUDITOR, EMS_ROLE_FIN_TREASURER, EMS_ROLE_FIN_READER,
);
const EMS_ROLES_MAINTENANCE = array(
    EMS_ROLE_MAINTENANCE_MGR, EMS_ROLE_MAINTENANCE_SUPERVISOR,
);
// سلسلة اعتماد الساعات الرباعية + من يُسمح له بدخول شاشاتها.
const EMS_ROLES_HOURS_APPROVAL_CHAIN = array(
    EMS_ROLE_OPERATIONS_MGR, EMS_ROLE_SUPPLIERS_MGR, EMS_ROLE_FLEET_MGR, EMS_ROLE_HR_MGR,
);
const EMS_ROLES_HOURS_APPROVAL_ACCESS = array(
    EMS_ROLE_SUPER_ADMIN, EMS_ROLE_OPERATIONS_MGR, EMS_ROLE_SUPPLIERS_MGR,
    EMS_ROLE_FLEET_MGR, EMS_ROLE_HR_MGR, EMS_ROLE_SITE_MGR,
);

// ── خريطة التحقق: الاسم المتوقع لكل رقم (مطابقة القاعدة الحية) ────────────────
const EMS_ROLE_NAMES = array(
    '1'  => 'ادارة التشغيل',
    '2'  => 'ادارة الموردين',
    '3'  => 'ادارة الاسطول',
    '4'  => 'ادارة الموارد البشرية',
    '5'  => 'مدير الموقع',
    '6'  => 'مدير حركة وتشغيل',
    '7'  => 'مشرف - مشاريع',
    '8'  => 'مشرف موردين',
    '10' => 'مشرف اسطول',
    '11' => 'مشغل اسطول',
    '12' => 'ادارة المبيعات',
    '13' => 'ادارة الصيانة',
    '14' => 'مشرف صيانة',
    '15' => 'مدير الصلاحيات',
    '16' => 'مسؤول المشتريات',
    '17' => 'المدير المالي',
    '18' => 'محاسب الإدارة المالية',
    '19' => 'مدير الإدارة المالية',
    '20' => 'المراجع والمدقق المالي',
    '21' => 'أمين الخزينة',
    '22' => 'قارئ مالي',
    '23' => 'مدير النقل والترحيل',
);

/**
 * حارس المطابقة: يقارن الفهرس بجدول roles الحي ويسجّل أي انحراف.
 * يُستدعى من config.php مرة لكل جلسة (استعلام واحد خفيف على 22 صفًا).
 * لا يكسر شيئًا أبدًا — التسجيل للرصد، والحسم قرار بشري.
 */
function ems_roles_verify_against_db($conn)
{
    if (!($conn instanceof mysqli)) {
        return;
    }

    $live = array();
    $res = @mysqli_query($conn, 'SELECT id, name FROM roles');
    if (!$res) {
        return; // جدول الأدوار غير متاح — لا رصد ولا ضجيج.
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $live[strval($row['id'])] = trim((string) $row['name']);
    }
    mysqli_free_result($res);

    $issues = array();
    foreach (EMS_ROLE_NAMES as $id => $expected) {
        if (!isset($live[$id])) {
            $issues[] = "role {$id} missing (expected «{$expected}»)";
        } elseif ($live[$id] !== $expected) {
            $issues[] = "role {$id} renamed: expected «{$expected}» found «{$live[$id]}»";
        }
    }
    foreach ($live as $id => $name) {
        if (!isset(EMS_ROLE_NAMES[$id])) {
            $issues[] = "role {$id} «{$name}» not in constants index (includes/roles.php)";
        }
    }

    if (!empty($issues) && function_exists('log_security_event')) {
        log_security_event('role_constant_mismatch', implode(' | ', $issues));
    }
}
