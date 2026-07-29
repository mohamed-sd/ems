-- ═══════════════════════════════════════════════════════════════════════════
-- تسجيلُ شاشة تسويات الموردين — 2026-07-28
-- ───────────────────────────────────────────────────────────────────────────
-- الوحدةُ 144 (بعد 143 «العملات وأسعار الصرف») — رقمٌ صريحٌ لا MAX+1.
--
-- **الملكيةُ والصلاحيات — قرارُ المالك 2026-07-28 «الموردون يُعدّون · المالية تُجيز»:**
--   · إدارةُ الموردين (2)      → can_add = **الإعدادُ والتوليدُ والاعتراض**
--   · مديرُ الإدارة المالية (19) → can_edit = **الإجازة**
--   · المديرُ المالي (17) ومحاسبُها (18) والتشغيل (1) → عرضًا
-- والشاشةُ تقرأ الأعمدةَ بهذا المعنى حرفيًّا (can_add=إعداد · can_edit=إجازة)،
-- فلا يجتمعان لدورٍ واحدٍ — وهو **فصلُ اليدين في طبقة المنح** لا في الكود وحده.
-- والخدمةُ تمنع فوقه اعتمادَ المرءِ ما أعدّ (`prepared_by ≠ approver`) — حاجزان.
--
-- **الباب**: السجلات الرئيسية (REC) — التسويةُ مستندُ دورةٍ لا إعدادٌ ولا تقرير.
-- **لا عدّاد**: العدّادُ لما ينتظر قرارَك، وسيُضاف حين تُبنى لوحةُ الموردين.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 144, 'تسويات الموردين', 'Suppliers/settlements.php', 2, 0, 0, 'fa fa-file-invoice-dollar', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Suppliers/settlements.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 144, 1, r.a, r.e, 0
  FROM (SELECT 2  AS rid, 1 AS a, 0 AS e      -- الموردون: يُعدّون ولا يُجيزون
        UNION ALL SELECT 19, 0, 1              -- مدير المالية: يُجيز ولا يُعدّ
        UNION ALL SELECT 17, 0, 0
        UNION ALL SELECT 18, 0, 0
        UNION ALL SELECT 1,  0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 144);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 144, 'تسويات الموردين', 'Suppliers/settlements.php',
       'fa fa-file-invoice-dollar', 46, NULL, 'Suppliers/settlements.php', 1
  FROM (SELECT 2 AS rid UNION ALL SELECT 19 UNION ALL SELECT 17
        UNION ALL SELECT 18 UNION ALL SELECT 1) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Suppliers/settlements.php');
