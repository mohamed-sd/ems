-- 2026_07_07_drop_stray_test_probe.sql — تنظيف: جدول اختبارٍ شاردٌ فارغ
-- (admin_subscription_requests_test_probe) خلّفته جلسة فحصٍ سابقة — ليس من
-- المخطط المعتمد ولا يشير إليه أي كود. idempotent.
SET NAMES utf8mb4;
DROP TABLE IF EXISTS `admin_subscription_requests_test_probe`;
