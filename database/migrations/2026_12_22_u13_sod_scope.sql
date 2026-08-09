-- update0013 · البند ④ — موضعُ إنفاذِ كلِّ زوجٍ من أزواجِ فصلِ الواجبات
-- ═══════════════════════════════════════════════════════════════════════════
-- المصدر: FIN-ACC-01 §٤-٩ (FACC-0058..FACC-0070) — والأزواجُ الثلاثةَ عشرَ
--         نفسُها في FIN-CTRL-01 و FIN-MGR-01 و FIN-TRE-01 و IAF-01.
-- الشاهد: PROP-01 §٧-٢ ⑩ «صفرُ حسابٍ يجمع زوجًا من أزواجِ فصلِ الواجبات —
--         فحصٌ بنيويٌّ يرفض التكليف».
--
-- ◆ لماذا عمودُ `scope`:
--   الأزواجُ الثلاثةَ عشرَ ليست على درجةٍ واحدةٍ في موضعِ إنفاذها:
--     • منها ما يُنفَّذ **عند التكليف** لأن طرفيه مسمّيان قائمان — مثل
--       «منفِّذُ الدفع» ✕ «مُعِدُّ المطابقةِ البنكية» (دوران 34 و35).
--     • ومنها ما يُنفَّذ **على المستند** لأن طرفيه صفتان في معاملةٍ بعينِها لا
--       مسمّيان — مثل «مُعِدُّ القيد» ✕ «معتمِدُ القيد»: الشخصُ نفسُه قد يُعِدُّ
--       قيدًا ويعتمد قيدًا آخرَ بلا خرقٍ، والخرقُ أن يجمعهما **على القيدِ نفسِه**.
--   وخلطُ الموضعين يُنتج أحدَ خطأين: إما قيدٌ يرفض تكليفًا مشروعًا، وإما زوجٌ
--   يُعلَن مُنفَذًا وهو غيرُ مفحوصٍ حيث يقع فعلًا. فالتصريحُ بالموضعِ شرطُ صدقِ
--   الادعاء.
--   والمُنفِذُ لكلٍّ: `AssignmentGate::checkConflicts()` للتكليف ·
--   و`ApprovalGate::record()` للمستند.

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sec_sod_pairs' AND COLUMN_NAME = 'scope') = 0,
  'ALTER TABLE `sec_sod_pairs` ADD COLUMN `scope` ENUM(''role'',''document'') NOT NULL DEFAULT ''role''
     COMMENT ''role = يُفحص عند التكليف · document = يُفحص على المستندِ الواحد'' AFTER `severity`',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sec_sod_pairs' AND COLUMN_NAME = 'enforced_by') = 0,
  'ALTER TABLE `sec_sod_pairs` ADD COLUMN `enforced_by` VARCHAR(120) NOT NULL DEFAULT ''''
     COMMENT ''الخدمةُ التي تُنفذ هذا الزوجَ فعلًا — ولا زوجَ بلا مُنفِذ'' AFTER `scope`',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- سجلُّ محاولاتِ الجمعِ المرفوضة — الشاهدُ على أن القيدَ بنيويٌّ يعمل لا مكتوب.
CREATE TABLE IF NOT EXISTS `sec_sod_denials` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `pair_code`   VARCHAR(12)  NOT NULL,
  `scope`       ENUM('role','document') NOT NULL,
  `subject_user_id` INT UNSIGNED NOT NULL,
  `role_id`     INT UNSIGNED NULL COMMENT 'المسمّى المطلوبُ عند رفضِ التكليف',
  `source_kind` VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'المستندُ عند رفضِ الاعتماد',
  `source_ref`  VARCHAR(120) NOT NULL DEFAULT '',
  `detail`      VARCHAR(600) NOT NULL DEFAULT '',
  `denied_at`   DATETIME     NOT NULL,
  `attempted_by` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_pair` (`company_id`, `pair_code`, `denied_at`),
  KEY `ix_subject` (`subject_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PROP-01 §7-2 ⑩ — سجلُّ رفضِ الجمعِ بين وظيفتين لا تُجمعان';
