-- ══════════════════════════════════════════════════════════════════════════════
-- EQUIP-INJAZ-S05-EMS · المتطلبات المبدئية المُهيكلة للفرصة (requirements_json)
-- عمودٌ واحدٌ على opportunities يحمل تقدير الموارد كـ JSON:
--   { "equipment":[{"type_id":1,"type_label":"حفار","qty":3}, …],
--     "operators": <int>, "suppliers": <int> }
-- هو مصدر الحقيقة المُهيكل؛ ويبقى capacity_summary نصًّا مشتقًّا مقروءًا (توافقٌ
-- رجعيٌّ: كل ما يقرأ capacity_summary اليوم يظل يعمل). Additive · idempotent.
-- لا ENUM عربي هنا (المحتوى JSON نصّي) لكن نلتزم utf8mb4 اتساقًا مع بقية الترحيلات.
-- التنفيذ: php database/migrate.php up   (أو mysql --default-character-set=utf8mb4)
-- ══════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- إضافةٌ آمنة idempotent (MySQL 8.4 لا يدعم ADD COLUMN IF NOT EXISTS): حارس
-- information_schema عبر PREPARE — يتطلّبه المُشغِّل (إعادة التطبيق بلا خطأ).
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opportunities' AND COLUMN_NAME = 'requirements_json');
SET @ddl := IF(@col_exists = 0,
  "ALTER TABLE `opportunities` ADD COLUMN `requirements_json` TEXT NULL COMMENT 'INJAZ-S05 — المتطلبات المبدئية المُهيكلة (معدات بالنوع + عددا مشغّلين/موردين) JSON؛ capacity_summary مشتقٌّ منه' AFTER `capacity_summary`",
  "DO 0");
PREPARE _opp_alter FROM @ddl; EXECUTE _opp_alter; DEALLOCATE PREPARE _opp_alter;

-- ══════════════════════════════════════════════════════════════════════════════
-- ROLLBACK (نفّذ فقط عند الطلب):
--   ALTER TABLE opportunities DROP COLUMN requirements_json;
-- ══════════════════════════════════════════════════════════════════════════════
