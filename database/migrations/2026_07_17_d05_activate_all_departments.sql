-- D05 — التفعيل الشامل: كل الإدارات وكل الأدوار تصل إلى المالية عبر البوابة الموحّدة
-- ═══════════════════════════════════════════════════════════════════════════
-- المبدأ (§3.1): المنشئ ← الرئيس المباشر (مراجعة) ← مدير الإدارة (اعتماد) ←
-- محاسب الإدارة (تصنيف وولادة الحدث). الإدارات ذات البنية المسطّحة (بلا دور
-- مشرفٍ مستقل) يكون رئيسها هو المراجع والمعتمد معًا — والضبط الحقيقي عندها
-- يقع على المحاسب المستقل وبوابة المالية، وكلُّ خطوةٍ مدوَّنة في السجل الإلحاقي.
-- يُبذر لكل شركةٍ لها مستخدمون (قابلٌ لإعادة التشغيل — يتخطى الموجود).
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO fin_request_routing
    (company_id, source_module, module_label, requester_roles, reviewer_role_id, manager_role_id, is_active)
SELECT c.company_id, t.source_module, t.module_label, t.requester_roles,
       t.reviewer_role_id, t.manager_role_id, 1
FROM (SELECT DISTINCT company_id FROM users WHERE company_id > 0) c
CROSS JOIN (
    -- الإدارة | التسمية | أدوار الإنشاء | المراجع | المعتمد
    SELECT 'suppliers'   AS source_module, 'الموردون'                  AS module_label, '2,8'      AS requester_roles,  8 AS reviewer_role_id,  2 AS manager_role_id UNION ALL
    SELECT 'workforce',   'الموارد البشرية والقوى التشغيلية',           '4',                        4,                   4 UNION ALL
    SELECT 'procurement', 'المشتريات',                                  '16',                      16,                  16 UNION ALL
    SELECT 'warehouse',   'المخازن',                                    '16',                      16,                  16 UNION ALL
    SELECT 'projects',    'المشاريع والتشغيل',                          '1,5,7',                    7,                   1 UNION ALL
    SELECT 'assets',      'الأسطول والمعدات',                           '3,10,11',                 10,                   3 UNION ALL
    SELECT 'sales',       'المبيعات',                                   '12',                      12,                  12 UNION ALL
    SELECT 'revenue',     'الإيرادات والتحصيل',                         '12',                      12,                  17 UNION ALL
    SELECT 'treasury',    'الخزينة',                                    '21',                      21,                  17 UNION ALL
    SELECT 'general',     'طلبات عامة (النقل والحركة والمواقع)',        '1,5,6,15,23',              1,                  17
) t
WHERE NOT EXISTS (
    SELECT 1 FROM fin_request_routing r
    WHERE r.company_id = c.company_id AND r.source_module = t.source_module
);

-- الصيانة (رأس الحربة) مفعّلة مسبقًا — يُضمن تفعيلها لكل شركةٍ أيضًا
INSERT INTO fin_request_routing
    (company_id, source_module, module_label, requester_roles, reviewer_role_id, manager_role_id, is_active)
SELECT c.company_id, 'maintenance', 'الصيانة', '13,14', 14, 13, 1
FROM (SELECT DISTINCT company_id FROM users WHERE company_id > 0) c
WHERE NOT EXISTS (
    SELECT 1 FROM fin_request_routing r
    WHERE r.company_id = c.company_id AND r.source_module = 'maintenance'
);
