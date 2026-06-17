-- =====================================================================
--  Fleet & Readiness — Phase 0 / Step 3 : تحويل equipments إلى «كرت المعدة»
--  Equipment Card — Basic Identity Fields ONLY (no child tables)
-- ---------------------------------------------------------------------
--  كل الأعمدة جديدة و NULL (عدا card_state) — إضافية غير كاسرة.
--  ملاحظة قرار: card_state DEFAULT 'active' (وليس 'draft') حتى لا تظهر
--  المعدات الحالية فجأةً كـ«مسودة»؛ والكروت الجديدة المُنشأة من النموذج
--  تُحفظ صراحةً بـ 'draft' (السلوك المطلوب «الكرت الجديد = مسودة» مضمون
--  عبر النموذج، بينما الصفوف القائمة/المستوردة تبقى 'active').
--
--  آمن للتشغيل المتكرر (idempotent) — MariaDB 10.4+ · DB: equipation_manage
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `equipments`
    ADD COLUMN IF NOT EXISTS `model_id`             INT(11)        NULL DEFAULT NULL,                 -- FK منطقي → fleet_model.id (قد يكون أُضيف بالخطوة 1)
    ADD COLUMN IF NOT EXISTS `operating_category`   VARCHAR(50)    NULL DEFAULT NULL,                 -- موروث من الموديل
    ADD COLUMN IF NOT EXISTS `origin_country`       VARCHAR(100)   NULL DEFAULT NULL,                 -- بلد الصنع
    ADD COLUMN IF NOT EXISTS `engine_no`            VARCHAR(100)   NULL DEFAULT NULL,                 -- رقم الموتور
    ADD COLUMN IF NOT EXISTS `plate_no`             VARCHAR(50)    NULL DEFAULT NULL,                 -- رقم اللوحة
    ADD COLUMN IF NOT EXISTS `capacity`             DECIMAL(12,2)  NULL DEFAULT NULL,                 -- السعة/القدرة/الحمولة
    ADD COLUMN IF NOT EXISTS `capacity_uom`         VARCHAR(20)    NULL DEFAULT NULL,                 -- وحدة السعة
    ADD COLUMN IF NOT EXISTS `dimensions`           VARCHAR(200)   NULL DEFAULT NULL,                 -- المقاسات الفنية
    ADD COLUMN IF NOT EXISTS `source_type`          VARCHAR(30)    NULL DEFAULT NULL,                 -- ملك/مموَّل/حق استخدام/خدمة
    ADD COLUMN IF NOT EXISTS `entry_date`           DATE           NULL DEFAULT NULL,                 -- تاريخ دخول المعدة
    ADD COLUMN IF NOT EXISTS `acquisition_cost`     DECIMAL(15,2)  NULL DEFAULT NULL,                 -- تكلفة الشراء
    ADD COLUMN IF NOT EXISTS `acquisition_currency` VARCHAR(10)    NULL DEFAULT NULL,                 -- عملة التكلفة
    ADD COLUMN IF NOT EXISTS `opening_meter`        DECIMAL(12,2)  NULL DEFAULT NULL,                 -- العداد الافتتاحي
    ADD COLUMN IF NOT EXISTS `meter_uom`            VARCHAR(20)    NULL DEFAULT 'ساعات',              -- وحدة العدّاد
    ADD COLUMN IF NOT EXISTS `meter_source`         VARCHAR(30)    NULL DEFAULT NULL,                 -- مصدر العدّاد
    ADD COLUMN IF NOT EXISTS `card_state`           VARCHAR(20)    NOT NULL DEFAULT 'active',         -- حالة الكرت: draft/active
    ADD COLUMN IF NOT EXISTS `card_approved_by`     INT(11)        NULL DEFAULT NULL,                 -- FK منطقي → users.id
    ADD COLUMN IF NOT EXISTS `card_approved_at`     DATETIME       NULL DEFAULT NULL;

-- فهارس مساعدة (غير حرجة)
ALTER TABLE `equipments`
    ADD INDEX IF NOT EXISTS `idx_equipments_model_id`   (`model_id`),
    ADD INDEX IF NOT EXISTS `idx_equipments_card_state` (`card_state`);

-- =====================================================================
--  نهاية السكربت — لا جداول أبناء، لا حساب إهلاك (مرحلة لاحقة)
-- =====================================================================
