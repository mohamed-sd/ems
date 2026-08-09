-- update0013 · البندان ⑥ و⑦ — التصنيفُ الرباعيُّ للحقولِ والتوريثُ
-- ═══════════════════════════════════════════════════════════════════════════
-- المصدر: FIN-OBL-01 §٤-١٧ (OBL-0052..0057) · §٤-٢١ (IN-01..IN-08)
--         · PROP-01 §٤-١ البندان ٥ و٦
--
-- ⑥ التصنيفُ الرباعي — OBL-0052:
--   «كلُّ حقلٍ في المنصةِ يُوسَم بصنفٍ واحدٍ على الأقلِّ من أربعة … **والحقلُ بلا
--    صنفٍ لا يُدرَج في شاشةٍ حاكمة**.»
--   OBL-0057: «◆ والصنفُ يحدد من يُنشئ ومن يقرأ ومن يعدّل — **لا اسمُ المستخدم**.»
--     DC-1 تشغيليُّ الإدارة   · مالكُه الإدارةُ المالكة — تُنشئه وتعدّله قبلَ الاعتماد
--     DC-2 ماليُّ الأثر        · مالكُه محاسبُ التخصصِ ورئيسُ الحسابات — الإدارةُ
--                                تقترحه ولا تعدّله
--     DC-3 للمراجعةِ القانونية · مالكُه المستشارُ القانونيُّ والحوكمة — قراءةٌ،
--                                والتعديلُ **بملحقٍ موقَّعٍ لا بتحريرِ حقل**
--     DC-4 للمراجعةِ الائتمانية · مالكُه المديرُ الماليُّ والتمويل — ولا يُعدَّل
--                                حدُّ ائتمانٍ إلا بقرارٍ ماليٍّ معتمد
--   الشاهد: PROP-01 §٧-٢ ⑤ «صفرُ حقلٍ في شاشةٍ حاكمةٍ بلا صنفٍ من الأربعة».
--
-- ⑦ التوريثُ — IN-01: «◆ لا يُعاد إدخالُ بيانٍ موجودٍ في مرجعٍ أب: فكلُّ حقلٍ له
--   مصدرٌ واحدٌ ويُوَرَّث للمستنداتِ التابعةِ **للقراءةِ فقط** · ومحاولةُ تعديلِ
--   حقلٍ موروثٍ تُرفض برمزٍ **يبيّن مصدرَه**.»
--   IN-03: «تغييرُ الأصلِ يُحدِّث الموروثَ في غيرِ المعتمدِ **ويُنبِّه** في المعتمد
--     — فالمعتمدُ لا رجعيةَ فيه والتصحيحُ حركةٌ جديدةٌ بمرجعها.»
--   الشاهد: PROP-01 §٧-٢ ⑥ «صفرُ حقلٍ يُدخَل مرتين في المنصةِ كلِّها».
--
-- idempotent: CREATE TABLE IF NOT EXISTS.

-- ═══ ① أصنافُ البياناتِ الأربعة (DC-1..DC-4) ═════════════════════════════
CREATE TABLE IF NOT EXISTS `gov_data_classes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code`        VARCHAR(6)   NOT NULL COMMENT 'DC-1..DC-4',
  `title`       VARCHAR(120) NOT NULL,
  `name_en`     VARCHAR(120) NOT NULL DEFAULT '',
  `meaning`     VARCHAR(400) NOT NULL DEFAULT '',
  `examples`    VARCHAR(700) NOT NULL DEFAULT '',
  `owner_label` VARCHAR(200) NOT NULL DEFAULT '',
  -- OBL-0057: الصنفُ يحدد من يُنشئ ويقرأ ويعدّل — لا اسمُ المستخدم
  `create_roles` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'فارغٌ = الإدارةُ المالكةُ للمستند',
  `edit_roles`   VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'فارغٌ = لا أحدَ يعدّل مباشرةً',
  `read_roles`   VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'فارغٌ = بحسبِ صلاحيةِ الشاشة',
  `edit_mode`    ENUM('direct','proposal','amendment_only','decision_only') NOT NULL DEFAULT 'direct'
                 COMMENT 'كيف يتغير: مباشرةً · اقتراحًا · بملحقٍ موقَّع · بقرارٍ معتمد',
  `doc_ref`     VARCHAR(24)  NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_dc` (`company_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-17 — التصنيفُ الرباعيُّ للبيانات';

-- ═══ ② وسمُ كلِّ حقلٍ في كلِّ شاشةٍ حاكمة ═════════════════════════════════
CREATE TABLE IF NOT EXISTS `gov_field_class` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `screen_code` VARCHAR(80)  NOT NULL COMMENT 'رمزُ الشاشةِ الحاكمة',
  `field_key`   VARCHAR(80)  NOT NULL COMMENT 'مفتاحُ الحقلِ في الشاشة',
  `label_ar`    VARCHAR(160) NOT NULL DEFAULT '',
  `dc_code`     VARCHAR(6)   NOT NULL COMMENT 'DC-1..DC-4 — ولا حقلَ بلا صنف',
  `is_sensitive` TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'يحتاج منحًا فرديًّا ويُسجَّل الاطّلاع',
  `doc_ref`     VARCHAR(24)  NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_field` (`company_id`, `screen_code`, `field_key`),
  KEY `ix_dc` (`dc_code`, `active`),
  KEY `ix_screen` (`screen_code`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PROP-01 §7-2 ⑤ — صفرُ حقلٍ في شاشةٍ حاكمةٍ بلا صنف';

-- ═══ ③ سجلُّ الشاشاتِ الحاكمة — ما يخضع لشرطِ التصنيف ════════════════════
-- «الشاشةُ الحاكمة» ليست كلَّ شاشة: هي ما يحمل عقدًا أو التزامًا أو استحقاقًا
-- أو اعتمادًا — أي ما يُنتج أثرًا ماليًّا أو قانونيًّا أو ائتمانيًّا.
CREATE TABLE IF NOT EXISTS `gov_governing_screens` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `screen_code` VARCHAR(80)  NOT NULL,
  `title_ar`    VARCHAR(200) NOT NULL DEFAULT '',
  `file_path`   VARCHAR(160) NOT NULL DEFAULT '',
  `why_governing` VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'لماذا عُدَّت حاكمة',
  `owner_doc`   VARCHAR(40)  NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_scr` (`company_id`, `screen_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PROP-01 §4-1 ⑤ — سجلُّ الشاشاتِ الحاكمةِ الخاضعةِ لشرطِ التصنيف';

-- ═══ ④ قواعدُ التوريث (IN-01..IN-08) ═════════════════════════════════════
CREATE TABLE IF NOT EXISTS `gov_field_inheritance` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `child_entity`  VARCHAR(60)  NOT NULL COMMENT 'المستندُ التابع: accrual · obligation · invoice · timesheet',
  `child_field`   VARCHAR(80)  NOT NULL,
  `parent_entity` VARCHAR(60)  NOT NULL COMMENT 'المرجعُ الأب',
  `parent_field`  VARCHAR(80)  NOT NULL,
  `label_ar`      VARCHAR(160) NOT NULL DEFAULT '',
  `readonly`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'IN-01 — الموروثُ للقراءةِ فقط',
  -- IN-03: يُحدَّث في غيرِ المعتمدِ ويُنبِّه في المعتمد
  `on_parent_change` ENUM('cascade_if_draft','notify_only') NOT NULL DEFAULT 'cascade_if_draft',
  `doc_ref`       VARCHAR(24)  NOT NULL DEFAULT '',
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inh` (`company_id`, `child_entity`, `child_field`),
  KEY `ix_parent` (`parent_entity`, `parent_field`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-21 — التوريثُ ومنعُ إعادةِ الإدخال';

-- ═══ ⑤ سجلُّ محاولاتِ تعديلِ الموروثِ المرفوضة ══════════════════════════
-- IN-01: «ومحاولةُ تعديلِ حقلٍ موروثٍ تُرفض برمزٍ **يبيّن مصدرَه**» —
--   والرفضُ يُسجَّل ليُقاس، فالقيدُ غيرُ المقيسِ ادعاء.
CREATE TABLE IF NOT EXISTS `gov_inheritance_denials` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `child_entity` VARCHAR(60)  NOT NULL,
  `child_ref`    VARCHAR(120) NOT NULL DEFAULT '',
  `child_field`  VARCHAR(80)  NOT NULL,
  `source_shown` VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'المصدرُ الذي بُيِّن للمستخدم',
  `attempted_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `denied_at`    DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_field` (`company_id`, `child_entity`, `child_field`, `denied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 IN-01 — سجلُّ رفضِ تعديلِ الموروث';
