-- ═══════════════════════════════════════════════════════════════════════════
-- H-01 · المرحلة ② — وسمُ المنشأ + تسجيلُ شاشة الحاويات — 2026-07-29
-- ───────────────────────────────────────────────────────────────────────────
-- **① وسمُ المنشأ — لماذا عمودٌ لا اجتهاد**
-- التوليدُ الرجعيُّ يستنتج الحصصَ من `operations` و`unit_entries`، و**الحصةُ قرارٌ
-- تجاريٌّ لا اشتقاقٌ حسابي**: كم ساعةً لهذا المورد؟ سؤالٌ تجيبه الإدارةُ لا
-- الجمعُ. فما يُستنتج **تقديرٌ مفيدٌ لا حقيقةٌ متفقٌ عليها**.
--
-- وقاعدةُ عدم التلفيق في وجهها الثاني: **ما اشتُقّ اشتقاقًا يُوسَم مشتقًّا** ولا
-- يُقدَّم كأنه مُقرّ. فالوسمُ عمودٌ **يُقرأ ويُعرض** لا تعليقٌ في الكود:
--   `origin='عقد'`     الحاويةُ من بند العقد بسقفه — رقمٌ متفقٌ عليه
--   `origin='مشتقّة'`  استُنتجت من صفوف التشغيل — **تنتظر إقرارَ الإدارة**
-- و`origin_note` يحمل **مِن أين** اشتُقّت بالضبط، فيُدقَّق الاستنتاجُ لا يُصدَّق.
--
-- ولمَ لا يكفي `close_reason`؟ لأنه سببُ إقفال — تحميلُه معنًى ثانيًا يجعل
-- تقريرًا يسأل «كم حاويةً أُقفلت ولماذا؟» يخلط المشتقَّ بالمقفل.
--
-- **② الشاشةُ**: الوحدة 149 (بعد 148 «تسويات الموظفين») — رقمٌ صريحٌ لا MAX+1.
-- الملكيةُ لإدارة التشغيل (1): التوزيعُ قرارُ تشغيلٍ لا مالٍ ولا مبيعات.
-- والأسطولُ (3) والموقعُ (5) والمبيعاتُ (12) والماليةُ (17) **عرضًا** — كلٌّ يقرأ
-- ما يخصّه ولا يوزّع.
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE `op_containers`
  ADD COLUMN `origin` ENUM('عقد','مشتقّة')
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
      NOT NULL DEFAULT 'عقد'
      COMMENT 'H-01 ②: منشأُ الرقم — «مشتقّة» تنتظر إقرارَ الإدارة ولا تُقدَّم متفقًا عليها'
      AFTER `state`,
  ADD COLUMN `origin_note` VARCHAR(255) DEFAULT NULL
      COMMENT 'مِن أين اشتُقّت بالضبط — فيُدقَّق الاستنتاجُ لا يُصدَّق'
      AFTER `origin`,
  ADD COLUMN `origin_ack_by` INT UNSIGNED DEFAULT NULL
      COMMENT 'مَن أقرّ الحصةَ المشتقّة — NULL = لم تُقرّ بعد'
      AFTER `origin_note`,
  ADD COLUMN `origin_ack_at` DATETIME DEFAULT NULL AFTER `origin_ack_by`;

-- سؤالُ تقرير المطابقة: «كم حاويةً مشتقّةً تنتظر الإقرار؟»
ALTER TABLE `op_containers`
  ADD INDEX `ix_container_origin` (`company_id`, `origin`, `origin_ack_by`);

-- ── تسجيلُ الشاشة ─────────────────────────────────────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 149, 'حاويات العقود', 'Operations/containers.php', 1, 0, 0, 'fa fa-boxes-stacked', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Operations/containers.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 149, 1, r.a, r.e, 0
  FROM (SELECT 1  AS rid, 1 AS a, 0 AS e      -- التشغيل: يولّد ويوزّع ويبدّل
        UNION ALL SELECT 3,  0, 0             -- الأسطول: عرضًا
        UNION ALL SELECT 5,  0, 0             -- الموقع: عرضًا
        UNION ALL SELECT 12, 0, 0             -- المبيعات: عرضًا
        UNION ALL SELECT 17, 0, 0) r          -- المالية: عرضًا
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 149);

-- رابطُ التنقّل لمالكها وتابعيه — «التبعيةُ تحدد القائمة والصلاحيةُ ترشّح».
-- الأسطولُ والموقعُ تابعان للتشغيل فيَرِثان الرابط؛ والمبيعاتُ والماليةُ تصلانها
-- من مرجعٍ مباشرٍ بصلاحيتهما (كنمط `Suppliers/settlements.php` مع الدور 1).
INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 149, 'حاويات العقود', 'Operations/containers.php',
       'fa fa-boxes-stacked', 48, NULL, 'Operations/containers.php', 1
  FROM (SELECT 1 AS rid UNION ALL SELECT 3 UNION ALL SELECT 5) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Operations/containers.php');
