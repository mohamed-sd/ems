-- NAV-09: هويةُ الظهور هي (الملفُّ القانوني × الإدارةُ العارضة) لا الاسم —
-- فالاسمان قد يتطابقان لملفّين والملفُّ الواحدُ لا يظهر لإدارةٍ مرتين.
DROP PROCEDURE IF EXISTS ems_svr_fix_uq;
CREATE PROCEDURE ems_svr_fix_uq()
BEGIN
  IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'screen_view_rows' AND INDEX_NAME = 'uq_svr') THEN
    ALTER TABLE screen_view_rows DROP INDEX uq_svr;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'screen_view_rows' AND INDEX_NAME = 'uq_svr_canonical') THEN
    ALTER TABLE screen_view_rows ADD UNIQUE KEY uq_svr_canonical (canonical_file, dept);
  END IF;
END;
CALL ems_svr_fix_uq();
DROP PROCEDURE IF EXISTS ems_svr_fix_uq;
