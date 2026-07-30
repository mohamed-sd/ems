-- ═══════════════════════════════════════════════════════════════════════════
-- H-05 · تسجيلُ شاشة «مواقع التنفيذ» — 2026-07-30
-- الوحدة 150 (بعد 149 «حاويات العقود») — رقمٌ صريحٌ لا MAX+1.
-- الملكيةُ لإدارة المشاريع (1): الموقعُ ابنُ المشروع في الهرم (OPM-01 §2-③).
-- والتشغيلُ (3) والموقعُ (5) عرضًا — يقرآن ولا يعرّفان (النص الحاكم §2).
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 150, 'مواقع التنفيذ', 'Projects/sites.php', 1, 0, 0, 'fa fa-map-location-dot', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Projects/sites.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 150, 1, r.a, r.e, 0
  FROM (SELECT 1  AS rid, 1 AS a, 1 AS e      -- المشاريع: تُنشئ وتعدّل
        UNION ALL SELECT 3,  0, 0             -- التشغيل: عرضًا
        UNION ALL SELECT 5,  0, 0) r          -- مدير الموقع: عرضًا
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 150);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 150, 'مواقع التنفيذ', 'Projects/sites.php',
       'fa fa-map-location-dot', 49, NULL, 'Projects/sites.php', 1
  FROM (SELECT 1 AS rid UNION ALL SELECT 3 UNION ALL SELECT 5) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Projects/sites.php');
