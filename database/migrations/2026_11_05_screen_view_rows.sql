-- ═══════════════════════════════════════════════════════════════════════════
-- NAV-01 v6 §6 · العرضُ المتعدد — مصفوفةُ العرض — 2026-08-02 (update0007 T-02)
-- «الشاشةُ مالكُها واحدٌ وعارضوها متعددون بصفوفٍ معلنة — ولا عارضَ بلا صفّ»
-- تُبذر من TARGET_ORDER.xlsx ورقة «مصفوفة العرض» (333 صفًّا) وتقرؤها
-- الفحوصُ ⑪⑫ وحارسُ النطاق.
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `screen_view_rows` (
  `svr_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `screen_name` VARCHAR(120) NOT NULL COMMENT 'الاسمُ المستهدفُ للشاشة (مفتاحُ المصفوفة)',
  `route`       VARCHAR(190) NULL COMMENT 'المسارُ التقنيُّ إن حُسم — والجديدُ ★ قد لا مسارَ له بعد',
  `dept`        VARCHAR(80)  NOT NULL COMMENT 'الإدارةُ الناظرة',
  `role_id`     INT NULL COMMENT 'دورُها المالكُ في النظام — يُحلّ من target_dept_role',
  `role_kind`   ENUM('owner','viewer') NOT NULL COMMENT 'مالك/عارض',
  `scope_text`  VARCHAR(120) NOT NULL COMMENT 'النطاق: الشركة · نطاقُ الإدارة · موقعُه · مورديه · عقودُه · سجلاتُه',
  `angle`       VARCHAR(160) NULL COMMENT 'الزاوية — تحدد الأعمدةَ والفلاتر',
  `columns_text` VARCHAR(255) NULL COMMENT 'الأعمدةُ المعروضةُ لهذا العارض',
  `filters_text` VARCHAR(255) NULL COMMENT 'الفلاترُ الافتراضية',
  `nav_group`   VARCHAR(80) NULL COMMENT 'المجموعةُ في قائمة هذا الناظر',
  `active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`svr_id`),
  UNIQUE KEY `uq_svr` (`screen_name`, `dept`),
  KEY `ix_svr_role` (`role_id`, `role_kind`, `active`),
  KEY `ix_svr_route` (`route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='NAV-01 v6 §6: صفوفُ العرض — النطاقُ والزاويةُ والأفعالُ معلنةٌ لكل ناظر';
