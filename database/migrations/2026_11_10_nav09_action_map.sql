-- NAV-09 ⓪-6 — خريطةُ أفعال الورقة 97 (حكم ١٤: «ربطُ الأحداث من الورقة 97»)
-- الكودُ القانونيُّ هويةُ الفعل · وlive_code تنفيذُه القائم إن وُجد:
--   alias   — فعلٌ قائمٌ عندنا بكودٍ آخر (المواءمة)
--   pending — عقدُه محفوظٌ هنا وينتظر بناءَ شاشته فيُرقّى إلى سجل الأفعال الحي
-- (المعلقُ لا يدخل actions كي لا يكسر فحوصَ المعالج والاتصال)
CREATE TABLE IF NOT EXISTS nav09_action_map (
  canonical_code VARCHAR(60) NOT NULL,
  label_ar VARCHAR(190) NOT NULL,
  screen_title VARCHAR(190) NOT NULL,
  canonical_file VARCHAR(80) NULL,
  actor_ar VARCHAR(120) NULL,
  writes_text VARCHAR(255) NULL,
  event_name VARCHAR(80) NULL,
  consumers_text VARCHAR(255) NULL,
  effect_text VARCHAR(500) NULL,
  reverse_text VARCHAR(255) NULL,
  live_code VARCHAR(80) NULL,
  state ENUM('alias','pending') NOT NULL DEFAULT 'pending',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (canonical_code),
  KEY ix_n9a_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
