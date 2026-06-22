-- =====================================================================
--  أرشفة جدول drivers + VIEW انتقالي (شبكة أمان) — الموجة 1
--  بعد تحويل كل المراجع إلى employees والتحقق منها.
--  الجدول الأصلي يُحفَظ (drivers_legacy_backup) لا يُحذف. (لا git)
-- =====================================================================
SET NAMES utf8mb4;
RENAME TABLE `drivers` TO `drivers_legacy_backup`;
CREATE OR REPLACE VIEW `drivers` AS SELECT `id`, `company_id`, `project_id`, `name`, `driver_code`, `nickname`, `identity_type`, `identity_number`, `identity_expiry_date`, `driver_photo`, `identity_photo`, `license_number`, `license_type`, `license_expiry_date`, `license_issuer`, `specialized_equipment`, `years_in_field`, `years_on_equipment`, `skill_level`, `certificates`, `owner_supervisor`, `supplier_id`, `employment_affiliation`, `salary_type`, `monthly_salary`, `email`, `address`, `performance_rating`, `behavior_record`, `accident_record`, `health_status`, `health_issues`, `vaccinations_status`, `previous_employer`, `employment_duration`, `reference_contact`, `general_notes`, `driver_status`, `start_date`, `created_at`, `phone`, `phone_alternative`, `status` FROM `employees`;
