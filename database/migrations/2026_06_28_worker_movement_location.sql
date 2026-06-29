-- Migration: نقطة الانطلاق/الوجهة كولاية+مدينة في أمر التحرّك — 2026-06-28
-- يضيف أعمدة الموقع المنظَّمة لـ worker_movement (الانطلاق والوجهة: ولاية + مدينة).
-- زمن الرحلة محسوبٌ عند العرض = DATEDIFF(actual_arrival, departure_date) (لا يُخزَّن).
SET NAMES utf8mb4;
ALTER TABLE `worker_movement`
  ADD COLUMN `origin_state`      VARCHAR(150) NULL AFTER `origin`,
  ADD COLUMN `origin_city`       VARCHAR(150) NULL AFTER `origin_state`,
  ADD COLUMN `destination_state` VARCHAR(150) NULL AFTER `destination_project_id`,
  ADD COLUMN `destination_city`  VARCHAR(150) NULL AFTER `destination_state`;
