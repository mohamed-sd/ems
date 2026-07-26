-- ═══════════════════════════════════════════════════════════════════════════
-- مجموعات الروابط (Link Groups) — طبقة تجميعٍ فوق جدول الشاشات
-- ───────────────────────────────────────────────────────────────────────────
-- المبدأ: السايدبار يعرض اسم المجموعة، وروابطُها تحته في قائمةٍ قابلة للطيّ.
-- ولمّا كانت صفوف `modules` مفصولةً أصلًا لكل دور (نفس الكود يتكرّر كصفٍّ
-- مستقل لكل owner_role_id — 118 صف رابط مقابل 105 كود فقط)، فإن عمود
-- `group_id` على الصف يعطي «لكل دورٍ أسماء مجموعاته الخاصة» تلقائيًّا،
-- بلا جدول ربطٍ ثلاثيّ ولا ازدواج مصدر حقيقة.
--
-- التوافق الرجعي كامل: group_id = NULL يعني الرابط يظهر في المستوى الأعلى
-- تمامًا كما هو اليوم. فلا شيء يتغيّر قبل إنشاء أوّل مجموعة.
--
-- النمط الحارس (SET @ddl + PREPARE) لا الإجراءات المخزَّنة: DELIMITER توجيهٌ
-- خاصٌّ بعميل mysql لا يقبله المُشغِّل عبر multi_query — والدرس مدفوعٌ سلفًا
-- في 2026_05_11_add_reports_performance_indexes.sql.
--
-- التطبيق بعميل utf8mb4 عبر database/migrate.php حصرًا (لا DDL يدوي).
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── ① جدول المجموعات ───────────────────────────────────────────────────────
-- مرجعٌ نظاميٌّ عام أسوةً بـ`modules` و`roles`: يُقرأ للجميع ويُكتب من كونسول
-- المزوّد وحده. لذلك بلا company_id وبلا حذفٍ ناعم — مسجَّل T_GLOBAL في
-- TenantRegistry (وبدون ذلك التسجيل ترفضه البوابة fail-closed).
CREATE TABLE IF NOT EXISTS `link_groups` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL COMMENT 'اسم المجموعة كما يظهر في السايدبار',
  `owner_role_id` INT NULL COMMENT 'الدور المالك — نفس دلالة modules.owner_role_id',
  `icon`          VARCHAR(50) NOT NULL DEFAULT 'fa fa-folder',
  `display_order` INT NOT NULL DEFAULT 0 COMMENT 'الأصغر يظهر أولاً',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `ix_owner_role` (`owner_role_id`),
  KEY `ix_display_order` (`display_order`),
  CONSTRAINT `link_groups_role_fk` FOREIGN KEY (`owner_role_id`)
    REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='مجموعات روابط السايدبار — لكل دورٍ مجموعاته';

-- ── ② عمود الربط على الشاشة ────────────────────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='modules' AND COLUMN_NAME='group_id'),
    'ALTER TABLE `modules` ADD COLUMN `group_id` INT NULL DEFAULT NULL AFTER `owner_role_id`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ③ فهرس العمود ──────────────────────────────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='modules' AND INDEX_NAME='ix_modules_group'),
    'ALTER TABLE `modules` ADD KEY `ix_modules_group` (`group_id`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ④ قيد المرجعية ─────────────────────────────────────────────────────────
-- ON DELETE SET NULL: حذف مجموعةٍ يُعيد روابطها للمستوى الأعلى ولا يُفقد شاشة.
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='modules'
                   AND CONSTRAINT_NAME='modules_group_fk'),
    'ALTER TABLE `modules` ADD CONSTRAINT `modules_group_fk` FOREIGN KEY (`group_id`) REFERENCES `link_groups` (`id`) ON DELETE SET NULL',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;
