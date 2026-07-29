-- ═══════════════════════════════════════════════════════════════════════════
-- تسجيلُ شاشة تسويات الموظفين (E-02) — 2026-07-29
-- ───────────────────────────────────────────────────────────────────────────
-- توأمُ الوحدة 144 «تسويات الموردين» بالخدمة نفسِها (`SettlementService`) —
-- الفرقُ `party_type='employee'`. الوحدةُ 148 (بعد 147) — رقمٌ صريحٌ لا MAX+1.
--
-- **الملكيةُ والصلاحيات — فصلُ اليدين في طبقة المنح** (نمطُ 2026_07_28_settlements_screen):
--   · الموارد البشرية (4)       → can_add  = **الإعدادُ والتوليدُ والاعتراض**
--   · مديرُ الإدارة المالية (19) → can_edit = **الإجازة**
--   · المديرُ المالي (17) ومحاسبُها (18) والتشغيل (1) → عرضًا
-- والشاشةُ تقرأ الأعمدةَ بهذا المعنى حرفيًّا، فلا يجتمعان لدورٍ واحد.
-- والخدمةُ تمنع فوقه اعتمادَ المرءِ ما أعدّ (`prepared_by ≠ approver`) — حاجزان.
--
-- ولماذا الموارد البشرية (4) لا الموردون (2): الملكيةُ تتبع الطرفَ لا المستند —
-- دفترُ الموظف عند مَن يديره. (قاعدةُ الملف: «التبعية تحدد القائمة والصلاحية ترشّح».)
--
-- **الباب**: السجلات الرئيسية (REC) — التسويةُ مستندُ دورةٍ لا إعدادٌ ولا تقرير.
-- **لا عدّاد**: العدّادُ لما ينتظر قرارَك، ويُضاف حين تُبنى لوحةُ الموارد البشرية.
--
-- **الشاشةُ القديمة `Workforce/worker_settlement.php` (الوحدة 54)**: مسارُ كتابةٍ
-- ثانٍ بإدخالٍ يدويٍّ حرٍّ للمبالغ — ومخالفٌ لمبدأ «الإدخالُ مرةً واحدةً في
-- المنبع». تُحال هنا إلى **العرض** بسحب `can_add`/`can_edit`/`can_delete`
-- وإبقاء `can_view`. والمخاطرةُ صفر: **صفرُ صفٍّ في `worker_settlement`** (مقيسٌ
-- 2026-07-29) — فلا عملَ قائمًا يُقطع، ولا صفَّ يُمسّ (الجدولُ يبقى بحاله).
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 148, 'تسويات الموظفين', 'Workforce/employee_settlements.php', 4, 0, 0, 'fa fa-file-invoice-dollar', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Workforce/employee_settlements.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 148, 1, r.a, r.e, 0
  FROM (SELECT 4  AS rid, 1 AS a, 0 AS e      -- الموارد البشرية: تُعدّ ولا تُجيز
        UNION ALL SELECT 19, 0, 1              -- مدير المالية: يُجيز ولا يُعدّ
        UNION ALL SELECT 17, 0, 0
        UNION ALL SELECT 18, 0, 0
        UNION ALL SELECT 1,  0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 148);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 148, 'تسويات الموظفين', 'Workforce/employee_settlements.php',
       'fa fa-file-invoice-dollar', 47, NULL, 'Workforce/employee_settlements.php', 1
  FROM (SELECT 4 AS rid UNION ALL SELECT 19 UNION ALL SELECT 17
        UNION ALL SELECT 18 UNION ALL SELECT 1) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Workforce/employee_settlements.php');

-- ── إحالةُ المسار اليدوي القديم إلى العرض (لا حذفَ ولا إخفاء) ───────────────
UPDATE `role_permissions` rp
   JOIN `modules` m ON m.`id` = rp.`module_id`
    SET rp.`can_add` = 0, rp.`can_edit` = 0, rp.`can_delete` = 0
  WHERE m.`code` = 'Workforce/worker_settlement.php';
