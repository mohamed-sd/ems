-- update0013 · البند ① — مصفوفةُ التوجيهِ لمحاسبي التخصصات
-- ═══════════════════════════════════════════════════════════════════════════
-- المصدر: FIN-OBL-01 §٤-١ · §٤-٢ · §٤-٣ · §٤-١٥  ·  FIN-ACC-01 §٤-١
-- الحكمُ الحاكم: «لا يختار المُطلِقُ إلى من يذهب طلبُه — والتوجيهُ آليٌّ بخمسةٍ
--   وثلاثين مسارًا · ولا واقعةٌ ماليةٌ تصل الخزينةَ قبلَ مرورِها بمحاسبِ
--   تخصصِها ورئيسِ الحسابات.»  (OBL-0001 · OBL-0020)
-- والحكمُ الثاني: «ولكلِّ ما يُرسَل إلى الماليةِ مرتجَعٌ مقابلٌ إلى مصدرِه —
--   فالانتظارُ الصامتُ أسوأُ من الرفض.»  (OBL-0285 · BR-01)
--
-- ◆ البذرُ ليس هنا: الصفوفُ تُبذر من `tools/u13_seed.php` قراءةً من
--   `docs/update0013/spec.json` المستخرَجِ من الوثائقِ نفسِها — فلا يدَ تنسخ
--   رقمًا من وثيقةٍ إلى SQL. هذا الملفُّ يبني البنيةَ وحدَها.
--
-- idempotent: الحارسُ عبر information_schema + PREPARE (فـMySQL لا تدعم
--   ADD COLUMN IF NOT EXISTS) — وCREATE TABLE IF NOT EXISTS للجداولِ الجديدة.

-- ═══ ① التخصصاتُ المحاسبيةُ العشرة (ACC-01..ACC-10) ═══════════════════════
-- FACC-0001: «لا يبقى النظامُ عند مسمًّى عامٍّ واحدٍ للمحاسب — بل عشرةُ
--   تخصصاتٍ بنطاقٍ محدَّدٍ لكلٍّ · ويجوز أن يجمع شخصٌ أكثرَ من تخصصٍ بشرطِ
--   عدمِ تعارضِ الواجبات.»
CREATE TABLE IF NOT EXISTS `fin_acc_specializations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code`        VARCHAR(8)   NOT NULL COMMENT 'ACC-01..ACC-10',
  `name_ar`     VARCHAR(120) NOT NULL,
  `name_en`     VARCHAR(160) NOT NULL DEFAULT '',
  `accounts`    VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'نطاقُ الحساباتِ من دليلِ الحسابات',
  `scope`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'نطاقُ المسؤولية',
  `dims`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'الأبعادُ الإلزامية D1..D9',
  `limit_rule`  VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'حدُّه — ما لا يملكه',
  `doc_ref`     VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'معرّفُ المتطلبِ الذريِّ المصدر',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_spec` (`company_id`, `code`),
  KEY `ix_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-ACC-01 §4-1 — التخصصاتُ المحاسبيةُ العشرة';

-- ═══ ② مصفوفةُ التوجيه (RT-01..RT-35) ════════════════════════════════════
-- OBL-0001: التوجيهُ آليٌّ بالمصفوفةِ لا باختيارِ المُطلِق.
-- OBL-0020 (RT-17) هو «الحكمُ الجامع» — قاعدةٌ احتياطيةٌ (kind='fallback')
--   وجهتُها «محاسبُ التخصصِ المسنَدُ للإدارةِ ونوعِ الواقعة» لا رمزُ ACC ثابت.
CREATE TABLE IF NOT EXISTS `fin_routing_matrix` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code`          VARCHAR(8)   NOT NULL COMMENT 'RT-01..RT-35',
  `kind`          ENUM('route','fallback') NOT NULL DEFAULT 'route',
  `trigger_ar`    VARCHAR(200) NOT NULL COMMENT 'المُطلِق',
  `trigger_key`   VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'مفتاحُ الحدثِ المنشورِ الذي يشغّل المسار',
  `source_dept`   VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'الإدارةُ المصدر',
  `launch_cond`   VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'شرطُ الإطلاق',
  `target_spec`   VARCHAR(8)   NOT NULL DEFAULT '' COMMENT 'ACC-xx — فارغٌ في الاحتياطية',
  `target_label`  VARCHAR(200) NOT NULL DEFAULT '',
  `accounts`      VARCHAR(255) NOT NULL DEFAULT '',
  `dims`          VARCHAR(64)  NOT NULL DEFAULT '',
  `chain`         VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'سلسلةُ المرور — آخرُها الخزينةُ إن وُجدت',
  `guard_rule`    VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'الحكمُ الحارسُ للمسار',
  `accept_test`   VARCHAR(400) NOT NULL DEFAULT '',
  `doc_ref`       VARCHAR(24)  NOT NULL DEFAULT '',
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_route` (`company_id`, `code`),
  KEY `ix_trigger` (`trigger_key`),
  KEY `ix_spec` (`target_spec`),
  KEY `ix_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-15 + §4-1 — مصفوفةُ التوجيهِ بخمسةٍ وثلاثين مسارًا';

-- ═══ ③ المرتجَعُ الماليُّ للإدارات (BF-01..BF-15) ═════════════════════════
CREATE TABLE IF NOT EXISTS `fin_backflow_notices` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `code`        VARCHAR(8)   NOT NULL COMMENT 'BF-01..BF-15',
  `title`       VARCHAR(200) NOT NULL,
  `fires_when`  VARCHAR(300) NOT NULL DEFAULT '',
  `destination` VARCHAR(300) NOT NULL DEFAULT '',
  `rule_text`   VARCHAR(500) NOT NULL DEFAULT '',
  `needs_action` TINYINT(1)  NOT NULL DEFAULT 1 COMMENT 'BR-02 — ما يستوجب فعلًا يولّد مهمة',
  `doc_ref`     VARCHAR(24)  NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bf` (`company_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-2 — المرتجَعُ الماليُّ الخمسةَ عشرَ';

-- ═══ ④ قواعدُ المرتجَع (BR-01..BR-06) ════════════════════════════════════
CREATE TABLE IF NOT EXISTS `fin_backflow_rules` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `code`        VARCHAR(8)   NOT NULL COMMENT 'BR-01..BR-06',
  `rule_text`   VARCHAR(600) NOT NULL,
  `accept_test` VARCHAR(400) NOT NULL DEFAULT '',
  `doc_ref`     VARCHAR(24)  NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_br` (`company_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-3 — قواعدُ المرتجَعِ الست';

-- ═══ ⑤ سجلُّ التوجيهِ الحي ═══════════════════════════════════════════════
-- الشاهدُ على «صفرُ واقعةٍ ماليةٍ تصل الخزينةَ بلا محاسبِ تخصصها»: كلُّ واقعةٍ
-- مُوجَّهةٍ لها صفٌّ هنا بمسارِها وتخصصِها ومحاسبِها ووقتِها.
CREATE TABLE IF NOT EXISTS `fin_routing_log` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `route_code`    VARCHAR(8)   NOT NULL,
  `trigger_key`   VARCHAR(80)  NOT NULL DEFAULT '',
  `source_kind`   VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'نوعُ المستندِ المصدر',
  `source_ref`    VARCHAR(120) NOT NULL COMMENT 'مرجعُ المستندِ المصدر',
  `source_dept`   VARCHAR(160) NOT NULL DEFAULT '',
  `target_spec`   VARCHAR(8)   NOT NULL DEFAULT '',
  `accountant_id` INT UNSIGNED NULL COMMENT 'users.id لمحاسبِ التخصصِ المستلِم',
  `work_item_id`  BIGINT UNSIGNED NULL COMMENT 'المهمةُ المولَّدةُ في مساحةِ عمله',
  `event_ref`     VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'مرجعُ الحدثِ المنشور',
  `resolved_by`   ENUM('matrix','fallback','manual') NOT NULL DEFAULT 'matrix',
  `manual_reason` VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'اليدويُّ استثناءٌ مسجَّل — WF-07',
  `routed_at`     DATETIME     NOT NULL,
  `created_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_source_route` (`company_id`, `source_kind`, `source_ref`, `route_code`),
  KEY `ix_spec_time` (`company_id`, `target_spec`, `routed_at`),
  KEY `ix_acc` (`accountant_id`),
  KEY `ix_src` (`source_kind`, `source_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-15 — سجلُّ التوجيهِ الحيُّ وشاهدُ عدمِ التخطي';

-- ═══ ⑥ سجلُّ المرتجَعِ الحي ══════════════════════════════════════════════
-- BR-01: صفرُ طلبٍ مُرسَلٍ بلا إشعارِ نتيجةٍ إلى مصدرِه.
-- BR-04: المرتجَعُ يحمل مرجعَ الطلبِ الأصليِّ ولا ينشأ منفصلًا.
-- BR-06: لا يُلغى مرتجَعٌ بإلغاءِ الطلبِ — بل يُغلق بسببِ الإلغاء.
CREATE TABLE IF NOT EXISTS `fin_backflow_log` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `notice_code`    VARCHAR(8)   NOT NULL COMMENT 'BF-01..BF-15',
  `source_kind`    VARCHAR(40)  NOT NULL DEFAULT '',
  `source_ref`     VARCHAR(120) NOT NULL COMMENT 'BR-04 — مرجعُ الطلبِ الأصلي',
  `source_stage`   VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'مرحلتُه عند الإطلاق',
  `to_user_id`     INT UNSIGNED NULL,
  `to_role_id`     INT UNSIGNED NULL,
  `to_label`       VARCHAR(200) NOT NULL DEFAULT '',
  `reason_code`    VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'BR-03 — رمزٌ محكومٌ لا نصٌّ حر',
  `reason_note`    VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'زيادةٌ على الرمزِ لا بديلٌ عنه',
  `work_item_id`   BIGINT UNSIGNED NULL COMMENT 'BR-02 — المهمةُ إن استوجب فعلًا',
  `state`          ENUM('open','acted','closed_cancelled','closed_done') NOT NULL DEFAULT 'open',
  `close_reason`   VARCHAR(300) NOT NULL DEFAULT '',
  `fired_at`       DATETIME     NOT NULL,
  `closed_at`      DATETIME     NULL,
  `created_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_src` (`company_id`, `source_kind`, `source_ref`),
  KEY `ix_state` (`company_id`, `state`, `fired_at`),
  KEY `ix_to` (`to_user_id`),
  KEY `ix_code` (`notice_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-2/§4-3 — سجلُّ المرتجَعِ الحيُّ وشاهدُ عدمِ الصمت';

-- ═══ ⑦ رموزُ أسبابِ الرفضِ المحكومة (BR-03) ══════════════════════════════
-- «سببُ الرفضِ برمزٍ محكومٍ لا بنصٍّ حر: فالنصُّ الحرُّ لا يُصنَّف ولا يُقاس
--   ولا يُنتج تقريرَ أسبابٍ متكررة.»
CREATE TABLE IF NOT EXISTS `fin_reason_codes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `code`        VARCHAR(60)  NOT NULL,
  `text_ar`     VARCHAR(200) NOT NULL,
  `kind`        ENUM('reject','missing_doc','budget','credit','variance','audit','other') NOT NULL DEFAULT 'reject',
  `needs_doc`   TINYINT(1)   NOT NULL DEFAULT 0,
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reason` (`company_id`, `code`),
  KEY `ix_kind` (`kind`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 BR-03 — رموزُ الأسبابِ المحكومة';

-- ═══ ⑧ نسبُ المحاسبِ إلى تخصصِه ══════════════════════════════════════════
-- FACC-0001: «كلُّ محاسبٍ منسوبٌ لتخصصٍ من العشرةِ بنطاقٍ معلَن».
-- الجدولُ `fin_accountants` قائمٌ منذ M-10 بعمودِ `admin_module` (الإدارةُ التي
-- يخدمها) و`specialization` نصًّا حرًّا. والتخصصُ المحكومُ عمودٌ جديدٌ بجانبِهما
-- — فلا يُحذف عمودٌ قائمٌ ولا يُفقد ما فيه.
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_accountants' AND COLUMN_NAME = 'spec_code') = 0,
  'ALTER TABLE `fin_accountants` ADD COLUMN `spec_code` VARCHAR(8) NOT NULL DEFAULT '''' COMMENT ''ACC-01..ACC-10 — FACC-0001'' AFTER `specialization`',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_accountants' AND INDEX_NAME = 'ix_spec_code') = 0,
  'ALTER TABLE `fin_accountants` ADD KEY `ix_spec_code` (`company_id`, `spec_code`, `active`)',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- سقفُ المراجعةِ قائمٌ (`review_limit_usd`) — ويُضاف نطاقُ المحاسبِ داخلَ تخصصِه
-- (مشروعٌ أو مركزُ تكلفةٍ أو كيان) ليصحَّ «يرى نطاقَه ولا يرى غيرَه».
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_accountants' AND COLUMN_NAME = 'scope_note') = 0,
  'ALTER TABLE `fin_accountants` ADD COLUMN `scope_note` VARCHAR(200) NOT NULL DEFAULT '''' COMMENT ''نطاقُ المحاسبِ المعلَن داخلَ تخصصِه'' AFTER `spec_code`',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
