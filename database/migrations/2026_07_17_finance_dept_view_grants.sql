-- ═══════════════════════════════════════════════════════════════════════════
-- صلاحيات العرض المالي للإدارات التشغيلية (قرار المستخدم 2026-07-17)
-- ───────────────────────────────────────────────────────────────────────────
-- القاعدة: كل إدارةٍ تُمنح شاشات المالية التي **تخصّها فقط**، عرضًا حصرًا
-- (can_view=1 · صفر إضافة/تعديل/حذف) — وداخل الشاشة ترى كل محتوى شركتها،
-- **إلا الدورين 5 (مدير الموقع) و6 (مدير الحركة والتشغيل)**: مقيّدان
-- بمشروعهما عبر fin_project_scope (fail-closed) في الشاشات المشروعية الثلاث.
--
-- الشاشات الـ13 المالية الخالصة (دليل الحسابات/القيود/القوائم/الضرائب/…)
-- تبقى مغلقةً على أدوار المالية 17-22 — لا مالكَ تشغيليًا لها.
-- التراجع: DELETE للصفوف المدرجة هنا يعيد الوضع كما كان.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT g.role_id, m.id, 1, 0, 0, 0
FROM modules m
JOIN (
    -- 📦 الموردون (2 إدارة · 8 مشرف): كشف حساب المورد + المدفوعات
    SELECT 'Finance/supplier_statement_fin.php' AS code, 2 AS role_id UNION ALL
    SELECT 'Finance/supplier_statement_fin.php', 8 UNION ALL
    SELECT 'Finance/payments_fin.php', 2 UNION ALL
    SELECT 'Finance/payments_fin.php', 8 UNION ALL
    -- 🛒 المشتريات (16): المدفوعات + الأحداث + الميزانيات
    SELECT 'Finance/payments_fin.php', 16 UNION ALL
    SELECT 'Finance/events_list_fin.php', 16 UNION ALL
    SELECT 'Finance/budget_form_fin.php', 16 UNION ALL
    -- 🚜 الأسطول (3 إدارة · 10 مشرف · 11 مشغل): الأصول + التكاليف + الوحدات
    SELECT 'Finance/assets_fin.php', 3 UNION ALL
    SELECT 'Finance/assets_fin.php', 10 UNION ALL
    SELECT 'Finance/cost_report_fin.php', 3 UNION ALL
    SELECT 'Finance/cost_report_fin.php', 10 UNION ALL
    SELECT 'Finance/cost_report_fin.php', 11 UNION ALL
    SELECT 'Finance/unit_records_fin.php', 3 UNION ALL
    SELECT 'Finance/unit_records_fin.php', 10 UNION ALL
    SELECT 'Finance/unit_records_fin.php', 11 UNION ALL
    -- 🏗️ التشغيل (1) ومشرف المشاريع (7): الوحدات + التكاليف + الأحداث + الميزانيات
    SELECT 'Finance/unit_records_fin.php', 1 UNION ALL
    SELECT 'Finance/unit_records_fin.php', 7 UNION ALL
    SELECT 'Finance/cost_report_fin.php', 1 UNION ALL
    SELECT 'Finance/cost_report_fin.php', 7 UNION ALL
    SELECT 'Finance/events_list_fin.php', 1 UNION ALL
    SELECT 'Finance/events_list_fin.php', 7 UNION ALL
    SELECT 'Finance/budget_form_fin.php', 1 UNION ALL
    SELECT 'Finance/budget_form_fin.php', 7 UNION ALL
    -- 📍 الموقع (5) و🔄 الحركة (6): الشاشات المشروعية الثلاث — **مقيّدةٌ بمشروعهما**
    SELECT 'Finance/unit_records_fin.php', 5 UNION ALL
    SELECT 'Finance/unit_records_fin.php', 6 UNION ALL
    SELECT 'Finance/cost_report_fin.php', 5 UNION ALL
    SELECT 'Finance/cost_report_fin.php', 6 UNION ALL
    SELECT 'Finance/events_list_fin.php', 5 UNION ALL
    SELECT 'Finance/events_list_fin.php', 6 UNION ALL
    -- 🔧 الصيانة (13 إدارة · 14 مشرف): التكاليف + الأحداث + الميزانيات
    SELECT 'Finance/cost_report_fin.php', 13 UNION ALL
    SELECT 'Finance/cost_report_fin.php', 14 UNION ALL
    SELECT 'Finance/events_list_fin.php', 13 UNION ALL
    SELECT 'Finance/events_list_fin.php', 14 UNION ALL
    SELECT 'Finance/budget_form_fin.php', 13 UNION ALL
    SELECT 'Finance/budget_form_fin.php', 14 UNION ALL
    -- 💼 المبيعات (12): الأحداث + الميزانيات
    SELECT 'Finance/events_list_fin.php', 12 UNION ALL
    SELECT 'Finance/budget_form_fin.php', 12 UNION ALL
    -- 👷 الموارد البشرية (4): الذمم والمستحقات + الميزانيات
    SELECT 'Finance/dues_fin.php', 4 UNION ALL
    SELECT 'Finance/budget_form_fin.php', 4 UNION ALL
    -- 🚛 النقل والترحيل (23): التكاليف + الأحداث + الميزانيات
    SELECT 'Finance/cost_report_fin.php', 23 UNION ALL
    SELECT 'Finance/events_list_fin.php', 23 UNION ALL
    SELECT 'Finance/budget_form_fin.php', 23
) g ON g.code = m.code
WHERE NOT EXISTS (
    SELECT 1 FROM role_permissions rp
    WHERE rp.role_id = g.role_id AND rp.module_id = m.id
);
