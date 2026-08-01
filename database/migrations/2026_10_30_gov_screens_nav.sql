-- ═══════════════════════════════════════════════════════════════════════════
-- شاشات الحوكمة (203–205) — البوابة ④ (GOV-01 §9 · SCR-02 §4)
-- ① تقرير الاستثناءات (مركز التقارير — قائم يُوسَّع) · ② تقرير المنع (كذلك)
-- ③ تصنيف الحمايات — **الشاشة الجديدة الوحيدة في GOV-01** (باب الإعدادات والتدقيق
--   خلف الصلاحية 1/19) — لا باب جديد صامتًا: توصية §12-⑩ DEC-PENDING تُنفَّذ
--   تبويبًا في الإعدادات حتى قرار المالك على البابين.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT * FROM (
    SELECT 203 i, 'تقرير الاستثناءات' n, 'Reports/exceptions_report.php'    c, 1 r, 0 l, 1 q, 'fa fa-shield-halved' ic, 0 d UNION ALL
    SELECT 204,   'تقرير المنع',         'Reports/guard_denials_report.php',   1,   0,   1,   'fa fa-ban',              0   UNION ALL
    SELECT 205,   'تصنيف الحمايات',      'Settings/guard_classification.php',  1,   0,   1,   'fa fa-shield',           0
) m
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) x WHERE x.`code` = m.c);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT p.rid, p.mid, 1, 0, p.ed, 0
  FROM (
    SELECT 1 rid, 203 mid, 0 ed UNION ALL SELECT 19, 203, 0 UNION ALL SELECT 17, 203, 0 UNION ALL
    SELECT 1, 204, 0 UNION ALL SELECT 19, 204, 0 UNION ALL SELECT 17, 204, 0 UNION ALL
    SELECT 1, 205, 1 UNION ALL SELECT 19, 205, 1
  ) p
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = p.rid AND rp.`module_id` = p.mid);
