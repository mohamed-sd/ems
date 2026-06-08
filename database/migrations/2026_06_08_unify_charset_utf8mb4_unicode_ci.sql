-- ═══════════════════════════════════════════════════════════════════════════
-- EMS · توحيد ترميز وترتيب قاعدة البيانات إلى utf8mb4 / utf8mb4_unicode_ci
-- @date 2026-06-08
--
-- المشكلة: خليط ترميزات وترتيبات:
--   - 14 جدولاً على utf8 (3 بايت) / utf8_general_ci  (الجداول الأساسية: contracts,
--     drivers, equipments, operations, project, suppliers, timesheet, users…)
--   - 6 جداول على utf8mb4_general_ci · 21 جدولاً على utf8mb4_unicode_ci
--   - أعمدة: 187 utf8_general_ci · 101 utf8mb4_unicode_ci · 15 utf8mb4_general_ci · 3 utf8mb4_bin
-- الأثر: خطأ «Illegal mix of collations» عند ربط جدولين بترتيبين مختلفين، وفرز
--   عربي غير متّسق، وعجز utf8 القديم عن تخزين الرموز رباعية البايت (إيموجي…).
--
-- الحل: توحيد كل الجداول والأعمدة على utf8mb4 + ترتيب واحد = utf8mb4_unicode_ci
--   (الأكثرية الحالية، وفرزه Unicode أنسب للعربية من general_ci). جملة CONVERT TO
--   تحوّل افتراضي الجدول وكل أعمدته وتُعيد ترميز البيانات فعلياً.
--
-- فحوص الأمان (تم التحقق على البيئة المحلية 2026-06-08):
--   ✓ كل الجداول ROW_FORMAT=Dynamic ⇒ حدّ الفهرس 3072 بايت، فلا خطر طول فهرس مع utf8mb4.
--   ✓ لا مفاتيح أجنبية على أعمدة نصّية (الـ33 FK كلها على أرقام) ⇒ CONVERT لن يُحظَر.
--   ✓ الأعمدة الثلاثة utf8mb4_bin في activity_logs (JSON longtext غير مفهرسة) ⇒ تحويلها آمن.
--   ✓ اتصال التطبيق يستخدم utf8mb4 أصلاً (config.php: $conn->set_charset("utf8mb4")) ⇒ لا تعديل كود.
--
-- ┌─ قبل التطبيق (وخاصةً على الإنتاج u359449619_ems) ──────────────────────────┐
-- │ خذ نسخة احتياطية كاملة:                                                     │
-- │   mysqldump -uUSER -p --single-transaction --default-character-set=utf8mb4 \│
-- │     DBNAME > backup_full_2026_06_08.sql                                     │
-- │ ثم طبّق هذا الملف. (CONVERT يعيد بناء الجداول؛ نفّذه في نافذة صيانة.)        │
-- └───────────────────────────────────────────────────────────────────────────┘
-- ═══════════════════════════════════════════════════════════════════════════

-- افتراضي القاعدة للجداول المستقبلية:
ALTER DATABASE `equipation_manage` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 0;

-- تحويل كل الجداول (41 جدولاً) ────────────────────────────────────────────────
ALTER TABLE `activity_logs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `admin_audit_log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `admin_companies` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `admin_subscription_plans` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `admin_subscription_requests` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `admin_subscription_requests_test_probe` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `api_tokens` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `approval_requests` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `approval_steps` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `approval_workflow_rules` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `audit_logs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `clients` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `company_user_password_resets` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `contractequipments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `contracts` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `contract_notes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `drivercontractequipments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `drivercontracts` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `drivers` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `driver_contract_notes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `equipments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `equipments_types` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `equipment_drivers` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `failure_codes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `messages` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `modules` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `operations` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `project` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `report_role_permissions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `roles` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `role_permissions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `super_admins` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `suppliercontractequipments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `suppliers` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `supplierscontracts` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `supplier_contract_notes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `timesheet` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `timesheet_approvals` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `timesheet_approval_notes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `timesheet_failure_hours` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════════
-- التحقّق بعد التطبيق — يجب أن تكون النتيجة 0 (لا جدول ولا عمود خارج الترتيب الموحّد):
--   SELECT COUNT(*) FROM information_schema.TABLES
--    WHERE TABLE_SCHEMA='equipation_manage' AND TABLE_TYPE='BASE TABLE'
--      AND TABLE_COLLATION <> 'utf8mb4_unicode_ci';
--   SELECT COUNT(*) FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA='equipation_manage' AND COLLATION_NAME IS NOT NULL
--      AND COLLATION_NAME <> 'utf8mb4_unicode_ci';
--
-- ملاحظات:
--  • activity_logs.(old_value/new_value/request_payload) كانت utf8mb4_bin وستصبح
--    utf8mb4_unicode_ci. حقول JSON غير مفهرسة، فلا أثر وظيفي. إن أردت إبقاءها ثنائية
--    أعِدها فردياً بعد الترحيل: MODIFY <col> LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin.
--  • admin_subscription_requests_test_probe جدول اختبار مؤقّت — مرشّح للحذف (DROP TABLE) بدل تحويله.
--  • لا حاجة لأي تعديل في كود التطبيق (الاتصال utf8mb4 أصلاً).
-- ═══════════════════════════════════════════════════════════════════════════
