-- NAV-09 ⓪-2 — أساس نظام الشاشات النافذ (آمن الإعادة):
-- ① قاموسُ المواءمة الثلاثي (live/mapped/soon) — الاسمُ القانونيُّ هويةٌ والمسارُ تنفيذ
-- ② الرباعيةُ لكل ظهور (حكم ٥): المسموح/المحجوب يكملان النطاقَ والزاوية
-- ③ مستوى المرحلة فوق المجموعة (حكم ١٣)
-- الإضافات الشرطية بإجراء مؤقت لأن MySQL لا يعرف ADD COLUMN IF NOT EXISTS

CREATE TABLE IF NOT EXISTS nav09_file_map (
  canonical_file VARCHAR(80) NOT NULL,
  title_ar VARCHAR(190) NOT NULL,
  owner_dept VARCHAR(64) NOT NULL,
  state ENUM('live','mapped','soon') NOT NULL DEFAULT 'soon',
  real_path VARCHAR(190) NULL,
  note VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (canonical_file),
  KEY ix_n9m_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS ems_nav09_addcols;
CREATE PROCEDURE ems_nav09_addcols()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'screen_view_rows' AND COLUMN_NAME = 'canonical_file') THEN
    ALTER TABLE screen_view_rows ADD COLUMN canonical_file VARCHAR(80) NULL AFTER screen_name,
                                 ADD KEY ix_svr_canonical (canonical_file);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'screen_view_rows' AND COLUMN_NAME = 'allowed_text') THEN
    ALTER TABLE screen_view_rows ADD COLUMN allowed_text VARCHAR(255) NULL AFTER filters_text,
                                 ADD COLUMN blocked_text VARCHAR(255) NULL AFTER allowed_text;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'link_groups' AND COLUMN_NAME = 'stage_no') THEN
    ALTER TABLE link_groups ADD COLUMN stage_no TINYINT NULL AFTER display_order,
                            ADD COLUMN stage_title VARCHAR(190) NULL AFTER stage_no;
  END IF;
END;
CALL ems_nav09_addcols();
DROP PROCEDURE IF EXISTS ems_nav09_addcols;
