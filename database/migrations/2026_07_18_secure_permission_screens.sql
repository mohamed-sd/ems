-- ═══════════════════════════════════════════════════════════════════════════
-- تحصين شاشتَي إدارة الصلاحيات (ح-1) — تسجيلٌ في modules + منحٌ للمخوَّلين
-- ───────────────────────────────────────────────────────────────────────────
-- الخلل (مؤكد بتنفيذ حقيقي): Settings/role_permissions.php و Settings/roles.php
-- غير مسجَّلتين في modules، ومعالجات POST فيهما تسبق insidebar (الحارس المركزي)،
-- والحاجز الوحيد isset($_SESSION['user']) ⇒ أي مستخدمٍ مسجَّل يمنح أي دورٍ أيَّ
-- صلاحية بطلب POST واحد (أُثبت: مستخدم «صيانة» دور 13 قلب صلاحيات الدور 1).
--
-- القرار (المستخدم 2026-07-18): المخوَّل = الدور 15 (مدير الصلاحيات) + الدور 1
-- (إدارة التشغيل). الطريقة = تسجيل modules + منح role_permissions، ويُرفَق بفحصٍ
-- صريح في أعلى الملفين (قبل معالجات POST) لأن insidebar يأتي بعدها.
--
-- عاطل التكرار: WHERE NOT EXISTS على كلا الإدراجين.
-- لا يمسّ: أي صف modules أو role_permissions قائم · أي شاشة أخرى.
-- التراجع: (بما أن الحذف ممنوع) عطّل الفحص في الكود؛ صفوف modules/role_permissions
--   الجديدة حميدة (شاشتان جديدتان بصلاحيةٍ لدورين فقط).
-- ═══════════════════════════════════════════════════════════════════════════

-- (1) تسجيل الشاشتين كوحدتين مملوكتين للدور 15 (مدير الصلاحيات)
INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
SELECT 'إدارة صلاحيات الأدوار', 'Settings/role_permissions.php', 15, '0', 0, 'fa fa-user-shield', 310
WHERE NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = 'Settings/role_permissions.php');

INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
SELECT 'إدارة الأدوار', 'Settings/roles.php', 15, '0', 0, 'fa fa-users-cog', 311
WHERE NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = 'Settings/roles.php');

-- (2) منح المخوَّلَين (15 مدير الصلاحيات · 1 التشغيل) الصلاحيات الكاملة عليهما
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT r.role_id, m.id, 1, 1, 1, 1
FROM (SELECT 15 AS role_id UNION ALL SELECT 1) r
JOIN modules m ON m.code IN ('Settings/role_permissions.php', 'Settings/roles.php')
WHERE NOT EXISTS (
    SELECT 1 FROM role_permissions rp
    WHERE rp.role_id = r.role_id AND rp.module_id = m.id
);
