-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ㉑ · NFR-13 — مخزن الجلسات المشترك
-- «نقل الجلسات من الملفات إلى مخزن مشترك — فقفل ملف الجلسة لا يجعل طلبات
-- المستخدم تنتظر بعضها». التفعيل بعلم EMS_SESSION_STORE=db (الافتراض files).
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS ems_sessions (
  sess_id    VARCHAR(128) NOT NULL,
  sess_data  MEDIUMBLOB NULL,
  expires_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (sess_id),
  KEY idx_sess_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
  COMMENT='NFR-13: قراءة/كتابة صف لا قفل ملف — والكنس بالدورية';
