-- ═══════════════════════════════════════════════════════════════════════════
-- سجلُّ تعريفات الشاشات — screen_about   (قرار المالك 2026-08-09)
-- ───────────────────────────────────────────────────────────────────────────
-- «نصٌّ يشرح ما هي الشاشة وما فيها، كدليلِ مستخدمٍ لمن يفتحها» — بلا ذكرِ
-- الأدوار ولا المهامِّ ولا الصلاحيات.
--
-- ◆ لماذا جدولٌ لا نصٌّ في كلِّ ملف: التعريفاتُ **محتوًى يُحرَّر** لا شيفرةٌ
--   تُنشر. فتنقيحُ عبارةٍ لا يحتاج لمسَ ملفِّ شاشةٍ ولا إعادةَ نشر، ويمكن
--   عرضُها ومراجعتُها جملةً. والمصدرُ (`source`) معلَنٌ لكلِّ صفٍّ فيُعرف
--   المكتوبُ بيدٍ من المشتقِّ من مصادر النظام — «لا يُصدَّق نصٌّ بلا مصدر».
--
-- ◆ جدولٌ **عامٌّ غيرُ مستأجَر**: التعريفُ خاصيةُ الشاشة لا بياناتُ شركة،
--   فيُصنَّف في TenantRegistry ضمن المراجع العامة كـ`modules`/`nav_items`.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `screen_about` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `screen_path` VARCHAR(190) NOT NULL COMMENT 'المسار النسبي للشاشة — مفتاح المطابقة',
  `title_ar`    VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'اسم الشاشة كما يُعرَف',
  `description` TEXT         NOT NULL COMMENT 'النص التعريفي — فقرة أو فقرتان',
  `source`      ENUM('authored','composed','derived') NOT NULL DEFAULT 'derived'
                COMMENT 'authored=مكتوب بيد · composed=مركَّب من مصادر النظام · derived=اسمٌ وإدارةٌ فقط',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_screen_about_path` (`screen_path`),
  KEY `ix_screen_about_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='تعريفات الشاشات لبطاقة «عن الشاشة» — محتوًى يُحرَّر لا شيفرة';
