-- ═══════════════════════════════════════════════════════════════════════════
-- EMS — مخطّط التثبيت الكامل (بنية فقط، بلا بيانات)
-- ─────────────────────────────────────────────────────────────────────────
-- المصدر: equipation_manage · التوليد: 2026-08-02 00:13:38
-- الجداول: 354 · المناظير: 6
-- يُستورد على قاعدةٍ فارغة عبر المُثبِّت. FOREIGN_KEY_CHECKS مُطفأٌ داخل
-- الملف لأن الجداول مرتّبةٌ أبجديًّا لا حسب تبعية المفاتيح الأجنبية.
-- مولَّدٌ آليًّا بـ `php database/migrate.php dump-schema` — لا يُحرَّر بيد.
-- ═══════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Table: achievement_certificates ──
CREATE TABLE `achievement_certificates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `eval_id` int unsigned DEFAULT NULL,
  `snap_id` int unsigned NOT NULL,
  `serial_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verify_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issued_by` int NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pdf_ref` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cert_serial` (`serial_no`),
  UNIQUE KEY `uq_cert_verify` (`verify_code`),
  UNIQUE KEY `uq_cert_snap` (`snap_id`),
  KEY `fk_cert_eval` (`eval_id`),
  CONSTRAINT `fk_cert_eval` FOREIGN KEY (`eval_id`) REFERENCES `evaluations` (`id`),
  CONSTRAINT `fk_cert_snap` FOREIGN KEY (`snap_id`) REFERENCES `achievement_snapshots` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='USR-01 §7-⑤ — الشهادةُ تُولَّد من الأرقام المقاسة ولا تُصدَر مرتين';

-- ── Table: achievement_snapshots ──
CREATE TABLE `achievement_snapshots` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `person_id` int DEFAULT NULL,
  `capacity_id` int unsigned NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `metrics_json` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'المؤشراتُ السبعةُ بأرقامها — و«لا ينطبق» يُعلَن لا صفرًا',
  `computed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'بصمةُ المصادر لحظةَ الحساب',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_snap` (`capacity_id`,`period_from`,`period_to`),
  KEY `ix_snap_person` (`person_id`),
  CONSTRAINT `fk_snap_capacity` FOREIGN KEY (`capacity_id`) REFERENCES `user_capacities` (`id`),
  CONSTRAINT `ck_snap_window` CHECK ((`period_to` >= `period_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='USR-01 §6/§9.1 — قياسُ الإنجاز بين تاريخين لكل صفة';

-- ── Table: activities ──
CREATE TABLE `activities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `activity_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_type` enum('زيارة عميل','اجتماع موقع','افتراضي','هاتفي','تفاوضي','زيارة مناجم') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` enum('opportunity','client','contract') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client',
  `entity_id` int unsigned DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activity_date` date DEFAULT NULL,
  `assigned_user_id` int DEFAULT NULL,
  `outcome` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_negotiation` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_activities_company_code` (`company_id`,`activity_code`),
  KEY `idx_act_scope` (`company_id`,`is_deleted`),
  KEY `idx_act_entity` (`entity_type`,`entity_id`),
  KEY `idx_act_type` (`activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: activity_logs ──
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `contract_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL COMMENT 'لقطة الموظف الفاعل وقت الحدث',
  `role_id` bigint unsigned DEFAULT NULL,
  `role_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `screen_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `button_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` bigint unsigned DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `http_method` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `response_status` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company_created` (`company_id`,`created_at`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_role_created` (`role_id`,`created_at`),
  KEY `idx_action_created` (`action_type`,`created_at`),
  KEY `idx_module_screen_created` (`module_name`,`screen_name`,`created_at`),
  KEY `idx_record_module` (`record_id`,`module_name`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_screen_name` (`screen_name`),
  KEY `idx_module_name` (`module_name`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_record_id` (`record_id`),
  KEY `idx_employee_created` (`employee_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: admin_audit_log ──
CREATE TABLE `admin_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL COMMENT 'super_admins.id',
  `action_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'create|update|delete|approve|reject|suspend|activate|login|logout',
  `target_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'human-readable target (company name, plan name, etc.)',
  `target_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_audit_admin` (`admin_id`),
  KEY `idx_admin_audit_action` (`action_type`),
  KEY `idx_admin_audit_date` (`created_at`),
  CONSTRAINT `fk_admin_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `super_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: admin_companies ──
CREATE TABLE `admin_companies` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد',
  `company_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم الشركة',
  `commercial_registration` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'السجل التجاري',
  `sector` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'القطاع',
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'البلد',
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'المدينة',
  `tax_number` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الرقم الضريبي',
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'البريد',
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم الهاتف',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'العنوان',
  `postal_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'العنوان البريدي',
  `logo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الشعار',
  `plan_id` int DEFAULT NULL COMMENT 'خطة الاشتراك',
  `modules_enabled` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الاسم',
  `company_name_ar` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم الشركة عربي',
  `company_name_en` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم الشركة انحليزي',
  `status` enum('pending','active','suspended','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'الحالة',
  `subscription_start` date DEFAULT NULL COMMENT 'بداية الاشتراك',
  `subscription_end` date DEFAULT NULL COMMENT 'نهاية الاشتراك',
  `users_count` int NOT NULL DEFAULT '0' COMMENT 'عدد المستخدمين',
  `max_users` int NOT NULL DEFAULT '0' COMMENT 'المستخدمين',
  `max_equipments` int NOT NULL DEFAULT '0' COMMENT 'المعدات',
  `max_projects` int NOT NULL DEFAULT '0' COMMENT 'المشاريع',
  `currency` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SAR' COMMENT 'العملة',
  `timezone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Riyadh' COMMENT 'المنطقة الزمنية',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'الملاحظات',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'الانشاء',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'التعديل',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_companies_email` (`email`),
  UNIQUE KEY `uq_admin_companies_commercial_registration` (`commercial_registration`),
  KEY `idx_admin_companies_plan` (`plan_id`),
  KEY `idx_admin_companies_status` (`status`),
  CONSTRAINT `fk_admin_companies_plan` FOREIGN KEY (`plan_id`) REFERENCES `admin_subscription_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: admin_subscription_plans ──
CREATE TABLE `admin_subscription_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plan_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم الخطة',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'السعر',
  `max_users` int NOT NULL DEFAULT '0' COMMENT '0 = unlimited المستخدمين',
  `max_projects` int NOT NULL DEFAULT '0' COMMENT 'المشاريع',
  `max_equipments` int NOT NULL DEFAULT '0' COMMENT 'المعدات',
  `features` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'المميزات',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT 'الترتيب',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'نشط',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'الانشاء',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'التعديل',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: admin_subscription_requests ──
CREATE TABLE `admin_subscription_requests` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد',
  `company_id` int DEFAULT NULL COMMENT 'null if company not  created yet رقم الشركة',
  `company_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم الشركة',
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'البريد',
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الهاتف',
  `plan_id` int DEFAULT NULL COMMENT 'خطة الاشتراك',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'message from the requesting company جميع بيانات الشركة ',
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'الحالة',
  `reviewed_by` int DEFAULT NULL COMMENT 'super_admins.id المراجع',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'زمن المراجعه',
  `review_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'الملاحظات',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'الانشاء',
  PRIMARY KEY (`id`),
  KEY `idx_admin_sub_req_status` (`status`),
  KEY `idx_admin_sub_req_plan` (`plan_id`),
  KEY `fk_admin_sub_req_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_admin_sub_req_plan` FOREIGN KEY (`plan_id`) REFERENCES `admin_subscription_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_admin_sub_req_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `super_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: api_tokens ──
CREATE TABLE `api_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sha256 hex ┘ä┘äÏ¬┘ê┘â┘å Ïº┘äÏ«Ïº┘à',
  `device` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '┘êÏÁ┘ü ÏºÏ«Ï¬┘èÏºÏ▒┘è ┘ä┘äÏ¼┘çÏºÏ▓/Ïº┘äÏ¬ÏÀÏ¿┘è┘é',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_user` (`user_id`),
  KEY `idx_active` (`revoked`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: approval_chains ──
CREATE TABLE `approval_chains` (
  `chain_id` int unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` int unsigned NOT NULL,
  `seq_no` tinyint unsigned NOT NULL,
  `approver_role` enum('site','operations','suppliers','workforce','finance') COLLATE utf8mb4_unicode_ci NOT NULL,
  `periodicity` enum('daily','weekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weekly' COMMENT 'الدورية تُختار بالسياسة — لا افتراضية صامتة',
  `sla_hours` int unsigned DEFAULT NULL COMMENT 'المهلة المعلنة — تجاوزها تصعيد لا إغلاق',
  `skip_if_not_applicable` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`chain_id`),
  UNIQUE KEY `uq_ac_seq` (`policy_id`,`seq_no`),
  CONSTRAINT `fk_ac_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §4: سلسلة الاعتماد — لا تُفتح حلقة قبل سابقتها';

-- ── Table: approval_requests ──
CREATE TABLE `approval_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by` int NOT NULL,
  `current_step` int DEFAULT '1',
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_approval_entity` (`entity_type`,`entity_id`),
  KEY `idx_approval_status` (`status`),
  KEY `idx_approval_user` (`requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: approval_signatures ──
CREATE TABLE `approval_signatures` (
  `sig_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `document_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_id` bigint unsigned NOT NULL,
  `step` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approve' COMMENT 'الخطوة/الحلقة — فلا يُسجَّل توقيع مرتين لخطوة',
  `person_id` int NOT NULL,
  `capacity_id` int DEFAULT NULL,
  `auth_id` int unsigned DEFAULT NULL COMMENT 'مرجع التفويض — NULL فقط لما قبل تفعيل الحارس',
  `org_asg_id` int unsigned DEFAULT NULL COMMENT 'مرجع التكليف التنظيمي المعتمِد — ORG-01 O8',
  `amount` decimal(18,2) DEFAULT NULL COMMENT 'المبلغ الذي اعتُمد تحته',
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result` enum('signed','denied') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'signed',
  PRIMARY KEY (`sig_id`),
  UNIQUE KEY `uq_sig_step` (`document_type`,`document_id`,`person_id`,`step`),
  KEY `ix_sig_person` (`person_id`,`at`),
  KEY `fk_sig_auth` (`auth_id`),
  KEY `idx_sig_org_asg` (`org_asg_id`),
  CONSTRAINT `fk_sig_auth` FOREIGN KEY (`auth_id`) REFERENCES `signing_authorities` (`auth_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §6-③: الاعتماد توقيع — Insert-only ولا تعديل ولا حذف؛ يلف الاعتمادات القائمة لا يوازيها';

-- ── Table: approval_steps ──
CREATE TABLE `approval_steps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `role_required` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `step_order` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_approval_steps_request` (`request_id`),
  KEY `idx_approval_steps_status` (`status`),
  KEY `idx_approval_steps_order` (`step_order`),
  CONSTRAINT `fk_approval_steps_request` FOREIGN KEY (`request_id`) REFERENCES `approval_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: approval_workflow_rules ──
CREATE TABLE `approval_workflow_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_required` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `step_order` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_workflow_rule` (`entity_type`,`action`,`step_order`),
  KEY `idx_workflow_rule_lookup` (`entity_type`,`action`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: asset_hour_reconciliations ──
CREATE TABLE `asset_hour_reconciliations` (
  `rec_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `equipment_id` int NOT NULL,
  `period` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `register_hours` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'ساعات سجل الأصول (فرق العدّادات في الفترة)',
  `timesheet_hours` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'ساعات التايم شيت المعتمدة',
  `diff_hours` decimal(12,2) GENERATED ALWAYS AS ((`register_hours` - `timesheet_hours`)) STORED,
  `depreciation_amount` decimal(18,2) DEFAULT NULL COMMENT 'إهلاك الفترة المحتسب للأصل',
  `depreciation_per_hour` decimal(18,4) DEFAULT NULL COMMENT 'معدل الإهلاك بالساعة — من الفعلي لا التقدير',
  `undepreciated_flag` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'معدة عملت ولم تُهلك — تشوه تكلفة المشروع',
  `state` enum('open','explained') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `explanation` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `explained_by` int DEFAULT NULL,
  `explained_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rec_id`),
  UNIQUE KEY `uq_ahr` (`company_id`,`equipment_id`,`period`),
  CONSTRAINT `ck_ahr_explained` CHECK (((`state` <> _utf8mb4'explained') or ((`explanation` is not null) and (`explained_by` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-17: مطابقة ساعات السجل بالتايم شيت — لا فرق بلا سبب (CHECK بنيوي)';

-- ── Table: asset_ownership_shares ──
CREATE TABLE `asset_ownership_shares` (
  `share_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `asset_id` int NOT NULL,
  `asset_kind` enum('fin_asset','equipment') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'equipment',
  `financier_entity_id` int unsigned NOT NULL,
  `op_id` int unsigned DEFAULT NULL,
  `model_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percent` decimal(5,2) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `capital` decimal(18,2) DEFAULT NULL,
  `share_valuation` decimal(18,2) DEFAULT NULL,
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مستند الحصة — والبيع بلا مستند يُرفض (الخدمة)',
  `recorded_percent` decimal(5,2) DEFAULT NULL COMMENT 'التصحيح الموثق: المسجَّلة',
  `corrected_percent` decimal(5,2) DEFAULT NULL,
  `correction_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_percent` decimal(5,2) DEFAULT NULL COMMENT 'الحكم المعتمد',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`share_id`),
  KEY `ix_aos_asset` (`company_id`,`asset_kind`,`asset_id`,`valid_from`),
  KEY `ix_aos_financier` (`financier_entity_id`),
  CONSTRAINT `fk_aos_financier` FOREIGN KEY (`financier_entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_aos_pct` CHECK (((`percent` > 0) and (`percent` <= 100)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-01 §5: حصص الملكية عبر الزمن — Σ النشطة = 100.00 بالضبط (تحرسه الخدمة معاملةً) ولا تداخل لنفس (الأصل×الممول)';

-- ── Table: assignment_audit ──
CREATE TABLE `assignment_audit` (
  `log_id` int unsigned NOT NULL AUTO_INCREMENT,
  `asg_id` int unsigned NOT NULL,
  `action` enum('created','amended','suspended','transferred','ended','delegated') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `before_json` json DEFAULT NULL,
  `after_json` json DEFAULT NULL,
  `by_person_id` int NOT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_asg` (`asg_id`,`at`),
  CONSTRAINT `fk_audit_asg` FOREIGN KEY (`asg_id`) REFERENCES `org_assignments` (`asg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §2⑧: سجلُّ التعديلات والاعتمادات — للإدراج فقط لا يُعدَّل ولا يُحذف';

-- ── Table: assignment_capabilities ──
CREATE TABLE `assignment_capabilities` (
  `cap_id` int unsigned NOT NULL AUTO_INCREMENT,
  `asg_id` int unsigned NOT NULL,
  `capability_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_limit_json` json DEFAULT NULL COMMENT 'المواقعُ والمشاريعُ المسموحة — السقفُ التشغيليُّ نطاقيّ',
  `amount_cap` decimal(18,2) DEFAULT NULL COMMENT 'NULL للتشغيلي — والسقفُ الماليُّ نقدي',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`cap_id`),
  UNIQUE KEY `uq_cap_per_asg` (`asg_id`,`capability_code`),
  CONSTRAINT `fk_cap_asg` FOREIGN KEY (`asg_id`) REFERENCES `org_assignments` (`asg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: صلاحياتُ التكليف — السقفُ التشغيليُّ نطاقيٌّ والماليُّ نقدي (DEC-01 ①)';

-- ── Table: assignment_reporting_lines ──
CREATE TABLE `assignment_reporting_lines` (
  `line_id` int unsigned NOT NULL AUTO_INCREMENT,
  `asg_id` int unsigned NOT NULL,
  `line_type` enum('operational','functional') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reports_to_assignment_id` int unsigned NOT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  PRIMARY KEY (`line_id`),
  UNIQUE KEY `uq_line_per_asg` (`asg_id`,`line_type`),
  KEY `idx_line_reports_to` (`reports_to_assignment_id`),
  CONSTRAINT `fk_line_asg` FOREIGN KEY (`asg_id`) REFERENCES `org_assignments` (`asg_id`),
  CONSTRAINT `fk_line_target` FOREIGN KEY (`reports_to_assignment_id`) REFERENCES `org_assignments` (`asg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §2⑦: التبعيةُ المزدوجة — وقيدُ «الموقعيُّ له خطّان» يحرسه AssignmentService بـ422';

-- ── Table: attendance_days ──
CREATE TABLE `attendance_days` (
  `att_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `person_id` int NOT NULL,
  `att_date` date NOT NULL,
  `status_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'من قاموس payroll_absence_types.code حصرًا (تحرسه الخدمة)',
  `policy_id` int unsigned DEFAULT NULL,
  `reference_doc` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stop_reason_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'لحالة ST — الفوترة والاستحقاق يُقرآن من الإسناد',
  `classified_by` int DEFAULT NULL,
  `classified_at` datetime DEFAULT NULL,
  `auto_reclassified` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = صُنّف A2 آليًّا بعد 48 ساعة وإشعار',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`att_id`),
  UNIQUE KEY `uq_att_day` (`person_id`,`att_date`),
  KEY `ix_att_company` (`company_id`,`att_date`,`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §3: سجل اليوم — يشير إلى القاموس ولا يوازيه';

-- ── Table: attendance_policies ──
CREATE TABLE `attendance_policies` (
  `policy_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `name_ar` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applies_to_json` json NOT NULL COMMENT 'محددات §1: نوع الموظف · مقر/مشروع · العقد · نمط الوردية · الوظيفة · الموقع',
  `grace_minutes` int unsigned DEFAULT NULL COMMENT 'سماح المقر (8:15) — NULL للمشاريع (لا تأخر مكتبي)',
  `missing_punch_rule` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'half_day_unless_corrected للمقر · NULL للمشاريع (الإثبات بكشف الموقع)',
  `late_rule` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'monthly_total للمقر — بإجمالي زمن التأخير لا بعدد المرات',
  `partial_permission_limit` tinyint unsigned DEFAULT NULL COMMENT 'الإذن الجزئي: مرتان شهريًّا',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`policy_id`),
  UNIQUE KEY `uq_ap_name` (`company_id`,`name_ar`,`valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §1: سياستان لا سياسة واحدة — ولا سياسة افتراضية صامتة (بلا مطابقة → 422)';

-- ── Table: attendance_sweep_notices ──
CREATE TABLE `attendance_sweep_notices` (
  `notice_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `person_id` int NOT NULL,
  `att_date` date NOT NULL,
  `notified_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notice_id`),
  UNIQUE KEY `uq_asn_person_date` (`company_id`,`person_id`,`att_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DEC-01 ④: إشعار ما قبل A2 — لا يصير A2 بصمت ولا بلا مهلة إضافية (48+24)';

-- ── Table: audit_logs ──
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `action_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user_id` (`user_id`),
  KEY `idx_audit_logs_company_id` (`company_id`),
  KEY `idx_audit_logs_action_type` (`action_type`),
  KEY `idx_audit_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: bank_recon_matches ──
CREATE TABLE `bank_recon_matches` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `statement_line_id` int unsigned NOT NULL,
  `payment_id` int DEFAULT NULL COMMENT 'سطرُ النظام (fin_payments) — NULL = بلا نظير',
  `match_kind` enum('auto','manual','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto' COMMENT '«المضاهاةُ الآلية بقاعدتها» — واليدويةُ تُوسم فيُعرف من قرّر',
  `rule_note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'القاعدةُ التي طابقت: مرجعٌ أو (مبلغ + تاريخ ± أيام)',
  `bank_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `system_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `difference` decimal(18,2) GENERATED ALWAYS AS (round((`bank_amount` - `system_amount`),2)) STORED COMMENT '**مولَّدٌ لا يُكتب** — فلا ينحرف الفرقُ عن طرفيه',
  `state` enum('matched','open_difference','resolved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'matched',
  `difference_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '«فتحُ فرقٍ **بسبب**»',
  `adjustment_event_id` int DEFAULT NULL COMMENT '«قيدُ تسويةٍ **بمرجع الفرق**»',
  `decided_by` int unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recon_line` (`statement_line_id`) COMMENT 'مضاهاةٌ واحدةٌ لكل سطرِ بنك — ولا سطرَ يُطابَق مرتين',
  KEY `ix_recon_payment` (`company_id`,`payment_id`),
  KEY `ix_recon_state` (`company_id`,`state`),
  CONSTRAINT `fk_recon_line` FOREIGN KEY (`statement_line_id`) REFERENCES `bank_statement_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_recon_decided` CHECK (((`state` not in (_utf8mb4'resolved',_utf8mb4'rejected')) or (`decided_by` is not null))),
  CONSTRAINT `ck_recon_diff_reason` CHECK (((`state` <> _utf8mb4'open_difference') or ((`difference_reason` is not null) and (`difference_reason` <> _utf8mb4''))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: bank_statement_lines ──
CREATE TABLE `bank_statement_lines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `statement_id` int unsigned NOT NULL,
  `line_no` int NOT NULL COMMENT 'ترتيبُ السطر في الكشف كما ورد',
  `txn_date` date NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direction` enum('deposit','withdrawal') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `running_balance` decimal(18,2) DEFAULT NULL COMMENT 'الرصيدُ كما ورد في الكشف',
  `bank_ref` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'المرجعُ البنكيُّ للحركة — **جزءُ مفتاح السطر**',
  `line_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'بصمةُ السطر (كشف × مرجع × تاريخ × اتجاه × مبلغ) — «Idempotent بمفتاح السطر»',
  `match_state` enum('unmatched','matched','difference','no_counterpart') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unmatched',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_line_key` (`company_id`,`line_key`) COMMENT 'إعادةُ استيراد الملف نفسِه **لا تُنشئ سطرًا ثانيًا**',
  KEY `ix_bank_line_stmt` (`statement_id`,`line_no`),
  KEY `ix_bank_line_match` (`company_id`,`match_state`,`txn_date`),
  CONSTRAINT `fk_bank_line_stmt` FOREIGN KEY (`statement_id`) REFERENCES `bank_statements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_bank_line_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: bank_statements ──
CREATE TABLE `bank_statements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `bank_account_id` int unsigned NOT NULL,
  `statement_ref` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مرجعُ الكشف من البنك — جزءُ مفتاح العطالة',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `opening_balance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `closing_balance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `lines_count` int NOT NULL DEFAULT '0',
  `state` enum('imported','matching','reconciled','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imported',
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int unsigned DEFAULT NULL,
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_statement` (`company_id`,`bank_account_id`,`statement_ref`) COMMENT 'كشفٌ واحدٌ لمرجعه في الحساب — إعادةُ الاستيراد تُعيده لا تُكرره',
  KEY `ix_stmt_period` (`company_id`,`bank_account_id`,`period_from`,`period_to`),
  CONSTRAINT `ck_stmt_closed` CHECK (((`state` <> _utf8mb4'closed') or ((`closed_at` is not null) and (`closed_by` is not null)))),
  CONSTRAINT `ck_stmt_span` CHECK ((`period_to` >= `period_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SPEC-01 #19 — رأسُ كشف البنك: مرجعُه ومداه ورصيداه';

-- ── Table: chain_objections ──
CREATE TABLE `chain_objections` (
  `obj_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `unit_id` bigint unsigned NOT NULL,
  `line_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `domain` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'من decision_reasons حصرًا',
  `policy_id` int unsigned DEFAULT NULL COMMENT 'سياسة السلسلة المعنية — مرجع الرجوع الآلي',
  `site_id` int DEFAULT NULL,
  `person_id` int NOT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`obj_id`),
  KEY `ix_co_policy` (`policy_id`,`at`),
  KEY `ix_co_company` (`company_id`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DEC-01 ⑥: رصد الاعتراضات — اعتراضان في شهر أو نزاع → دورية يومية آليًّا (Insert-only)';

-- ── Table: change_approvals ──
CREATE TABLE `change_approvals` (
  `step_id` int unsigned NOT NULL AUTO_INCREMENT,
  `chg_id` int unsigned NOT NULL,
  `seq_no` tinyint unsigned NOT NULL COMMENT '1=مدير الحركة · 2=الإدارة المعنية · 3=المالية · 4=الإدارة العامة',
  `approver_person_id` int NOT NULL,
  `role` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auth_id` int unsigned DEFAULT NULL,
  `decision` enum('approve','reject') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`step_id`),
  UNIQUE KEY `uq_ca_seq` (`chg_id`,`seq_no`),
  CONSTRAINT `fk_ca_chg` FOREIGN KEY (`chg_id`) REFERENCES `unit_state_changes` (`chg_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §6-④: سلّم الموافقات الرباعي — لا تُفتح خطوة قبل اكتمال ما قبلها';

-- ── Table: claim_lines ──
CREATE TABLE `claim_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `claim_id` int NOT NULL,
  `source_kind` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'timesheet' COMMENT 'مصدر الواقعة: timesheet · unit_entry',
  `source_ref` int NOT NULL COMMENT 'معرّف الواقعة في مصدرها — رابطُ الأصل',
  `contract_line_id` int unsigned DEFAULT NULL COMMENT 'بندُ البيع المفوتَر (P-02)',
  `plan_period_id` int unsigned DEFAULT NULL COMMENT 'شهرُ الخطة (P-03)',
  `operational_site_id` int unsigned DEFAULT NULL COMMENT 'نطاقُ العقد التشغيلي (P-01)',
  `event_id` int unsigned DEFAULT NULL COMMENT 'قيدُ الإيراد المعترَف به من المروحة — البندُ مرجعٌ له لا منشئٌ لإيرادٍ ثانٍ',
  `work_date` date DEFAULT NULL,
  `equipment_ref` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'المعدة كما في سجل التشغيل',
  `unit_type` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'hour·ton·meter — وحدةُ العقد',
  `qty` decimal(18,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'من سطر معدة العقد — لا يُدخل',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'محسوبٌ = الكمية × السعر',
  `dispute_flag` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'بندٌ متنازَعٌ عليه — يقف وحده ولا يجمّد البقية',
  `dispute_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dispute_doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مستندُ الاعتراض — «بسببٍ **ومستند**» (§3-⑤)',
  `disputed_by` int DEFAULT NULL,
  `disputed_at` datetime DEFAULT NULL,
  `dispute_state` enum('none','open','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT 'حالُ النزاع — والحسمُ قرارٌ يُسجَّل لا وسمٌ يُمحى',
  `resolution` enum('upheld','rejected') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'upheld = أُقرَّ اعتراضُ العميل (البندُ يسقط) · rejected = رُدَّ (البندُ يعود محتسَبًا)',
  `resolution_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_by` int DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_claim_line_src` (`claim_id`,`source_kind`,`source_ref`) COMMENT 'لا وحدةَ تتكرر داخل المستخلص الواحد',
  KEY `ix_cl_claim` (`claim_id`),
  KEY `ix_cl_source` (`source_kind`,`source_ref`) COMMENT 'يكشف أي وحدةٍ استُخلصت في أكثر من مستخلص (حارسٌ في الاختبار)',
  KEY `ix_claim_lines_event` (`event_id`),
  KEY `ix_cl_plan_keys` (`contract_line_id`,`plan_period_id`),
  CONSTRAINT `fk_claim_line_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_dispute_evidence` CHECK (((`dispute_state` = _utf8mb4'none') or ((`dispute_reason` is not null) and (`dispute_reason` <> _utf8mb4'') and (`dispute_doc_ref` is not null) and (`dispute_doc_ref` <> _utf8mb4'')))),
  CONSTRAINT `ck_dispute_flag_mirror` CHECK ((`dispute_flag` = (case when (`dispute_state` = _utf8mb4'open') then 1 when ((`dispute_state` = _utf8mb4'resolved') and (`resolution` = _utf8mb4'upheld')) then 1 else 0 end))),
  CONSTRAINT `ck_dispute_resolution` CHECK (((`dispute_state` <> _utf8mb4'resolved') or ((`resolution` is not null) and (`resolved_by` is not null) and (`resolution_note` is not null) and (`resolution_note` <> _utf8mb4''))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='بنود المستخلص — سطرٌ لكل واقعةٍ معتمدةٍ برابط أصلها';

-- ── Table: claims ──
CREATE TABLE `claims` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل المستأجر',
  `claim_no` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'رقم المستخلص التسلسلي CLM-سنة-رقم',
  `contract_id` int NOT NULL COMMENT 'العقد — مفتاحُ المستخلص (UX-08 §8.1)',
  `client_id` int DEFAULT NULL COMMENT 'العميل مشتقًّا من مشروع العقد — لا يُدخل',
  `project_id` int DEFAULT NULL COMMENT 'مشروع العقد',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `currency` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG' COMMENT 'عملة العقد',
  `gross_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'إجمالي البنود قبل الاستقطاع',
  `retention_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'الاستقطاعات التعاقدية (يدويةٌ بسطرها في النسخة الأولى)',
  `retention_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجعُ الاستقطاع وسببه',
  `net_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'الصافي = الإجمالي − الاستقطاعات',
  `tax_code` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'كود الضريبة من fin_tax_codes',
  `tax_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `invoice_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم الفاتورة الضريبية المولَّدة من المستخلص المعتمد',
  `invoice_date` date DEFAULT NULL,
  `state` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'حالاتُ §4 — ومنها **partially_collected** (M-05)',
  `submitted_by` int unsigned DEFAULT NULL COMMENT 'من رفعه للمالية (المبيعات) — ولا يعتمد المرءُ ما رفع',
  `submitted_at` datetime DEFAULT NULL COMMENT 'لحظةُ الرفع للمالية (draft → review)',
  `event_id` int DEFAULT NULL COMMENT 'حدث الإيراد المنشور — قراءةً بمرجعه',
  `receivable_id` int DEFAULT NULL COMMENT 'صفّ الذمّة المدينة المولَّد',
  `version` int NOT NULL DEFAULT '1' COMMENT 'قفلُ النسخة عند الاعتماد',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_claim_no` (`company_id`,`claim_no`),
  UNIQUE KEY `uq_claim_period` (`company_id`,`contract_id`,`period_from`,`period_to`) COMMENT 'مستخلصٌ واحدٌ لكل (عقد × فترة) — إعادةُ التوليد ترفض بمرجع القائم',
  KEY `ix_claim_state` (`state`),
  KEY `ix_claim_client` (`client_id`),
  KEY `ix_claim_period` (`period_from`,`period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='المستخلص — مطالبةُ الفترة من الوحدات المعتمدة (UX-08 §5.2)';

-- ── Table: client_contract_lines ──
CREATE TABLE `client_contract_lines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `contract_id` int NOT NULL,
  `line_no` int NOT NULL,
  `pricing_model` enum('hour','ton','trip','meter','cbm','day','shift','lump_sum','standby') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نموذجُ التسعير — و`lump_sum` مقطوعٌ بكميةٍ 1',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty_contracted` decimal(16,2) NOT NULL COMMENT 'الكميةُ المتعاقَد عليها لهذا البند',
  `qty_planned_total` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'Σ أشهر النسخة النافذة — يُحرَس بـCHECK فلا يتجاوز المتعاقَد',
  `plan_sealed_version` int DEFAULT NULL COMMENT 'رقمُ النسخة المختومة — والختمُ يشترط Σ = المتعاقَد بالضبط',
  `resource_share_total` decimal(9,3) NOT NULL DEFAULT '0.000' COMMENT 'Σ حصص خطة الموارد النافذة — يُحرَس بـCHECK فلا يتجاوز 100',
  `unit_price` decimal(14,4) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG' COMMENT 'لا تُجمع عملتان في رقم',
  `valid_from` date NOT NULL COMMENT 'السريان — «ملحقٌ يغيّر السعر ⇒ نسختان»',
  `valid_to` date DEFAULT NULL,
  `tax_status` enum('taxable','exempt','zero_rated','reverse_charge') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'taxable',
  `tax_code_id` int DEFAULT NULL COMMENT 'من `fin_tax_codes` — «الضريبةُ سطرٌ بمرجعها»',
  `source_commitment_id` int unsigned DEFAULT NULL COMMENT 'الالتزامُ الذي اشتُق منه — **الكمياتُ وحدَها**، ولا يقبل التزامَ طاقة',
  `supersedes_line_id` int unsigned DEFAULT NULL COMMENT 'البندُ الذي أخلفه — للمقارنة التاريخية',
  `state` enum('draft','active','superseded','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ccl_line_no` (`company_id`,`contract_id`,`line_no`),
  UNIQUE KEY `uq_ccl_source` (`contract_id`,`source_commitment_id`,`valid_from`) COMMENT 'التزامٌ واحدٌ بسريانٍ واحد — «نسختان لا تكديس»',
  KEY `ix_ccl_lookup` (`company_id`,`contract_id`,`state`,`valid_from`,`valid_to`),
  CONSTRAINT `ck_ccl_planned` CHECK (((`qty_planned_total` >= 0) and (`qty_planned_total` <= `qty_contracted`))),
  CONSTRAINT `ck_ccl_price` CHECK ((`unit_price` > 0)),
  CONSTRAINT `ck_ccl_qty` CHECK ((`qty_contracted` > 0)),
  CONSTRAINT `ck_ccl_share` CHECK (((`resource_share_total` >= 0) and (`resource_share_total` <= 100))),
  CONSTRAINT `ck_ccl_span` CHECK (((`valid_to` is null) or (`valid_to` >= `valid_from`))),
  CONSTRAINT `ck_ccl_tax_ref` CHECK (((`tax_status` <> _utf8mb4'taxable') or (`tax_code_id` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PLAN-03 §2 — بندُ بيع عقد العميل: **الجدولُ الوحيدُ الذي يحمل القيمة**';

-- ── Table: clients ──
CREATE TABLE `clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `client_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'كود العميل',
  `client_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم العميل',
  `entity_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع الكيان',
  `sector_category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'تصنيف القطاع',
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم الهاتف',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `whatsapp` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم الواتساب',
  `status` enum('نشط','متوقف') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'نشط' COMMENT 'حالة العميل',
  `created_by` int DEFAULT NULL COMMENT 'معرف المستخدم الذي أضاف العميل',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإضافة',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clients_company_code` (`company_id`,`client_code`),
  KEY `idx_client_name` (`client_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول العملاء';

-- ── Table: commercial_risks ──
CREATE TABLE `commercial_risks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `risk_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `risk_type` enum('عميل','موقع','تمويل','تحصيل','تشغيل','موردون') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'عميل',
  `severity` enum('منخفضة','متوسطة','عالية') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'متوسطة',
  `mitigation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `owner_user_id` int DEFAULT NULL,
  `state` enum('مفتوح','تحت المعالجة','مغلق') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مفتوح',
  `entity_type` enum('opportunity','contract') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'opportunity',
  `entity_id` int unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_commercial_risks_company_code` (`company_id`,`risk_code`),
  KEY `idx_risk_scope` (`company_id`,`is_deleted`),
  KEY `idx_risk_entity` (`entity_type`,`entity_id`),
  KEY `idx_risk_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: company_user_password_resets ──
CREATE TABLE `company_user_password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_user_password_resets_token_hash` (`token_hash`),
  KEY `idx_company_user_password_resets_user_id` (`user_id`),
  CONSTRAINT `fk_company_user_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: container_consumption ──
CREATE TABLE `container_consumption` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `container_id` int unsigned NOT NULL COMMENT 'الحاويةُ الورقية (مستوى المشغّل غالبًا)',
  `source_kind` enum('unit_entry','timesheet','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit_entry',
  `source_ref` int unsigned NOT NULL COMMENT 'الواقعةُ التي استهلكت',
  `qty` decimal(16,2) NOT NULL COMMENT 'موجبٌ استهلاكًا · سالبٌ ردًّا (عكسٌ موثَّق)',
  `unit_type` enum('hour','ton','meter','cbm','day','shift','trip') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hour',
  `consumed_on` date NOT NULL,
  `idem_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مفتاحُ العطالة — يمنع تكرارَ الاستهلاك',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_consumption_idem` (`company_id`,`idem_key`),
  KEY `ix_container` (`company_id`,`container_id`,`consumed_on`),
  KEY `ix_source` (`company_id`,`source_kind`,`source_ref`),
  KEY `fk_consumption_container` (`container_id`),
  CONSTRAINT `fk_consumption_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='H-01 §4 — دفترُ استهلاك الحاويات؛ الخصمُ الذريُّ يُسجَّل هنا';

-- ── Table: container_swaps ──
CREATE TABLE `container_swaps` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `container_id` int unsigned NOT NULL COMMENT 'الحاويةُ التي وقع فيها التبديل',
  `swap_kind` enum('معدة','مشغّل') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `out_ref` int unsigned DEFAULT NULL COMMENT 'الخارج (معدة/موظف)',
  `in_ref` int unsigned DEFAULT NULL COMMENT 'الداخل',
  `moved_qty` decimal(16,2) DEFAULT NULL COMMENT 'الرصيدُ المنقول (متبقي الخارجة) — حركةُ الاستبدال لا وصفُه',
  `to_container_id` int unsigned DEFAULT NULL COMMENT 'الحاويةُ البديلة (وليدةً أو مفعَّلةً)',
  `effective_from` date NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'إلزام — لا تبديلَ بلا سبب',
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_container_swap` (`company_id`,`container_id`,`effective_from`),
  KEY `fk_swap_container` (`container_id`),
  KEY `fk_swap_to_container` (`to_container_id`),
  CONSTRAINT `fk_swap_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_swap_to_container` FOREIGN KEY (`to_container_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `ck_swap_differs` CHECK (((`out_ref` is null) or (`in_ref` is null) or (`out_ref` <> `in_ref`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='H-01 §4 — تبديلُ معدةٍ أو مشغّلٍ داخل حاوية، بسببه ومستنده';

-- ── Table: contract_advances ──
CREATE TABLE `contract_advances` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `contract_id` int unsigned NOT NULL,
  `advance_no` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ADV-سنة-تسلسل — ترقيمٌ خادميٌّ لكل شركة',
  `amount` decimal(18,2) NOT NULL COMMENT 'المقبوضُ فعلًا — موجبٌ دائمًا. لا يُشتق من نسبةٍ ولا يُقدَّر (قاعدةُ عدم التلفيق)',
  `currency` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `received_date` date NOT NULL COMMENT 'تاريخُ القبض الفعلي',
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مرجعُ سند القبض — إلزام: لا سلفةَ بلا مستند',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('recorded','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'recorded' COMMENT 'القبضُ واقعةٌ لا دورةُ اعتماد — والإلغاءُ حالةٌ لا حذف',
  `event_id` int DEFAULT NULL COMMENT 'حقيقةُ القبض في الجذر المحايد (publishFact — لا قيدَ إيراد)',
  `recorded_by` int unsigned DEFAULT NULL,
  `recorded_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_advance_no` (`company_id`,`advance_no`),
  UNIQUE KEY `uq_advance_doc` (`company_id`,`contract_id`,`doc_ref`),
  KEY `ix_contract` (`company_id`,`contract_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-01 — دفعاتٌ مقدَّمةٌ مقبوضةٌ فعلًا؛ الاستهلاكُ في claim_lines';

-- ── Table: contract_amendments ──
CREATE TABLE `contract_amendments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `amendment_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_id` int DEFAULT NULL,
  `amend_type` enum('تجديد','تمديد','زيادة نطاق','تخفيض نطاق','تغيير أسعار','إضافة معدات','إضافة خدمات','إيقاف','استئناف','إنهاء','انتهاء','دمج','تغيير التزامات') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'تجديد' COMMENT 'نوعُ الملحق — و«تغيير التزامات» تُوثّق تعديلَ مصفوفة §4 بسريانٍ لا رجعيّ',
  `amend_date` date DEFAULT NULL,
  `effective_from` date DEFAULT NULL COMMENT 'تاريخُ نفاذ الملحق — NULL أي غيرُ محدد (لا يُشتق من amend_date)',
  `requested_by` int DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `old_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effect_price` decimal(14,2) DEFAULT NULL,
  `effect_qty` decimal(14,2) DEFAULT NULL,
  `effect_duration` int DEFAULT NULL,
  `effect_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_amendments_company_code` (`company_id`,`amendment_code`),
  KEY `idx_amd_scope` (`company_id`,`is_deleted`),
  KEY `idx_amd_contract` (`contract_id`),
  KEY `idx_amd_type` (`amend_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_baseline ──
CREATE TABLE `contract_baseline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `contract_id` int NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  `state` enum('draft','reviewed','approved','locked','amended','superseded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `state_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `locked_by` int unsigned DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `comp_lines` int NOT NULL DEFAULT '0',
  `comp_plan_months` int NOT NULL DEFAULT '0',
  `comp_plan_sealed` int NOT NULL DEFAULT '0',
  `comp_resource_rows` int NOT NULL DEFAULT '0',
  `comp_payment_rows` int NOT NULL DEFAULT '0',
  `comp_sites` int NOT NULL DEFAULT '0',
  `fingerprint` char(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'sha1 لحالة المكوّنات وقتَ القفل — **فيُعرف إن تغيّر شيءٌ بعده**',
  `amendment_id` int DEFAULT NULL,
  `supersedes_baseline_id` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cb_version` (`contract_id`,`version`),
  KEY `ix_cb_state` (`company_id`,`state`),
  CONSTRAINT `ck_cb_actors` CHECK ((((`state` <> _utf8mb4'reviewed') or ((`reviewed_by` is not null) and (`reviewed_at` is not null))) and ((`state` not in (_utf8mb4'approved',_utf8mb4'locked')) or ((`approved_by` is not null) and (`approved_at` is not null))) and ((`state` <> _utf8mb4'locked') or ((`locked_by` is not null) and (`locked_at` is not null) and (`fingerprint` is not null))))),
  CONSTRAINT `ck_cb_counts` CHECK (((`comp_lines` >= 0) and (`comp_plan_months` >= 0) and (`comp_plan_sealed` >= 0) and (`comp_resource_rows` >= 0) and (`comp_payment_rows` >= 0) and (`comp_sites` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PLAN-03 §3.6 — خطُّ الأساس بحالته: ومن القفل فقط تبدأ الفوترة';

-- ── Table: contract_commitments ──
CREATE TABLE `contract_commitments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `commitment_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_scope` enum('client','supplier') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client',
  `contract_ref` int NOT NULL,
  `commitment_type` enum('equipment_count','daily_availability_hours','period_hours','min_guaranteed','period_qty','total_qty','capacity_support') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'total_qty',
  `unit_type` enum('hour','ton','meter','cbm','day','shift','trip') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `period` enum('daily','monthly','contract') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `obliged_party` enum('company','client','supplier') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'company',
  `shortfall_rule` enum('invoice_actual','penalty','carry_over','extend_term','waive_if_client','negotiate') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invoice_actual',
  `surplus_rule` enum('same_price','different_price','pre_approval','open','not_billable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'same_price',
  `note` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_commit_company_code` (`company_id`,`commitment_code`),
  KEY `idx_commit_scope` (`company_id`,`is_deleted`),
  KEY `idx_commit_contract` (`contract_ref`),
  KEY `idx_commit_type` (`commitment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='INJAZ-S05 §ت.2 — التزامات العقد: نوعٌ ووحدةٌ وكميةٌ ودوريةٌ وطرفٌ ملتزم وحكما العجز والزيادة';

-- ── Table: contract_events ──
CREATE TABLE `contract_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `event_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_id` int DEFAULT NULL,
  `event_date` datetime DEFAULT NULL,
  `event_type` enum('انخفاض إنتاج','تأخر اعتماد العميل','نقص معدات','تأخر موردين','قوة قاهرة','أمر تغيير','مطالبة إضافية','تمديد محتمل','خلاف تشغيلي','إخلال طرف') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'أمر تغيير',
  `party` enum('الشركة','العميل','المورد') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `state` enum('مفتوح','قيد المتابعة','مغلق') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مفتوح',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_events_company_code` (`company_id`,`event_code`),
  KEY `idx_evt_scope` (`company_id`,`is_deleted`),
  KEY `idx_evt_contract` (`contract_id`),
  KEY `idx_evt_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_guarantees ──
CREATE TABLE `contract_guarantees` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `contract_id` int NOT NULL,
  `kind` enum('cash_retention','bank_guarantee','insurance','surety','pledge','other') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'محتجزٌ نقديّ · خطابُ ضمانٍ بنكي · تأمين · كفالة · رهن · أخرى',
  `nature` enum('asset','off_balance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'off_balance' COMMENT 'أصلٌ لدى العميل · أو التزامٌ محتملٌ خارج الميزانية',
  `deductible_from_claim` tinyint(1) NOT NULL DEFAULT '0',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'قيمةُ الأداة — وللمحتجَز النقديِّ **سقفٌ متعاقَدٌ عليه لا رصيدٌ**',
  `percent_value` decimal(7,3) DEFAULT NULL COMMENT 'نسبتُه من قيمة العقد إن كان بنسبة',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issuer` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'البنكُ المُصدر أو شركةُ التأمين أو الكفيل',
  `instrument_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقمُ الخطاب/الوثيقة',
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL COMMENT 'انتهاءُ سريان الأداة — إلزاميٌّ لغير المحتجَز',
  `due_release_date` date DEFAULT NULL COMMENT 'تاريخُ ردِّ المحتجَز — إلزاميٌّ له',
  `release_condition` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('draft','active','expired','released','called') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `state_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_at` date DEFAULT NULL,
  `source_text` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نصُّ `contracts.guarantees` الذي جاءت منه — **والنصُّ لا يُمحى**',
  `needs_review` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'صُنّفت آليًّا من نثرٍ فتنتظر إقرارَ المالك',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cg_lookup` (`company_id`,`contract_id`,`state`),
  KEY `ix_cg_expiry` (`expiry_date`),
  CONSTRAINT `ck_cg_amount` CHECK ((`amount` >= 0)),
  CONSTRAINT `ck_cg_dates` CHECK ((((`kind` = _utf8mb4'cash_retention') and ((`due_release_date` is not null) or (`release_condition` is not null))) or ((`kind` <> _utf8mb4'cash_retention') and (`expiry_date` is not null)))),
  CONSTRAINT `ck_cg_deduct` CHECK (((`deductible_from_claim` = 0) or (`kind` = _utf8mb4'cash_retention'))),
  CONSTRAINT `ck_cg_nature` CHECK ((((`kind` = _utf8mb4'cash_retention') and (`nature` = _utf8mb4'asset')) or ((`kind` <> _utf8mb4'cash_retention') and (`nature` = _utf8mb4'off_balance')))),
  CONSTRAINT `ck_cg_percent` CHECK (((`percent_value` is null) or ((`percent_value` >= 0) and (`percent_value` <= 100)))),
  CONSTRAINT `ck_cg_state_reason` CHECK (((`state` not in (_utf8mb4'released',_utf8mb4'called',_utf8mb4'expired')) or (`state_reason` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PLAN-03 §3.1 — سجلُّ الضمانات: الأصلُ والالتزامُ المحتمل لا يختلطان';

-- ── Table: contract_hour_policies ──
CREATE TABLE `contract_hour_policies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `party_scope` enum('client','supplier','operator') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_ref` int unsigned DEFAULT NULL COMMENT 'NULL = السياسة الافتراضية للشركة (تُنسخ عند إنشاء العقد)',
  `operator_id` int unsigned DEFAULT NULL COMMENT 'المشغّل (employees) — وضعُ سياسة المشغّل؛ NULL في وضع حكم الساعة',
  `work_model` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '§15.2-ج: hour·ton·trip·meter',
  `pay_basis` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '§15.2-ج: actual·standby·attendance·ton·trip·meter·composite',
  `rate` decimal(14,4) DEFAULT NULL COMMENT 'معدلُ الاستحقاق لوحدة الأساس (§8.2) — عمودٌ مستقلٌّ لأن pct(5,2) يبتر فوق 999.99',
  `min_amount` decimal(18,2) DEFAULT NULL COMMENT '§15.2-ج: الحد الأدنى اليومي',
  `max_amount` decimal(18,2) DEFAULT NULL COMMENT '§15.2-ج: الحد الأقصى اليومي — قيدُ min ≤ max يُفرض بالتطبيق',
  `scope_type` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '§15.2-ج: project·equip_type — NULL = سياسةٌ افتراضية',
  `scope_id` int unsigned DEFAULT NULL COMMENT '§15.2-ج: معرّفُ النطاق المقابل لـscope_type',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '§15.2-ج: عملةُ المعدّل — لا جمعَ عملتين',
  `deductions_note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '§8.2 القيم والحدود: الخصومات — توثيقٌ يقرؤه المخلِّص',
  `exceptions_note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '§8.2: الاستثناءات — توثيقٌ يقرؤه المخلِّص',
  `approved_at` datetime DEFAULT NULL COMMENT '§8.2 الهوية والسريان: تاريخ اعتماد السياسة',
  `approved_by` int unsigned DEFAULT NULL,
  `is_trial` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'سياسةٌ تجريبيةُ البذر — تُستبدل قيمُها قبل الاستعمال الحقيقي',
  `policy_state` enum('draft','active','superseded','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'UX-06 §8.2: Draft→Active→Superseded→Expired — والمسودةُ لا تُقرأ في أي احتساب',
  `superseded_by` int unsigned DEFAULT NULL COMMENT 'السياسةُ الأحدثُ التي أخلفتها — «Superseded بسياسةٍ أحدث» بمرجعها لا بالدعوى',
  `state_changed_at` datetime DEFAULT NULL,
  `state_changed_by` int unsigned DEFAULT NULL,
  `state_note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سببُ الانتقال — إلزاميٌّ عند الإنهاء',
  `ops_state` enum('actual_work','standby','tech_breakdown','supplier_stop','operator_stop','client_stop','fuel_logistics_stop','planned_stop','force_majeure','pending_approval','other','unlogged') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'حالةُ الساعة (وضع client/supplier) — NULL لصفوف المشغّل. وأُضيف unlogged لتوحيد القاموس',
  `obligation_type` enum('fuel','access_road','loading_equipment','equipment_readiness','operators','permits_safety','utilities','catering_camp','force_majeure') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'بندُ الالتزام (CON-02 §4) — المحورُ الثاني للحكم. NULL = قاعدةٌ عامةٌ للحالة (عُرفُ الجدول: NULL أي الأعمّ)',
  `ruling` enum('full','pct','none','pending','case_by_case') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pct` decimal(5,2) DEFAULT NULL COMMENT 'عند ruling=pct — نسبةٌ من الكمية لا من السعر',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'بند العقد أو سببُ الحكم',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'عقدُ البوابة الثلاثي (is_deleted/deleted_at/deleted_by)',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL COMMENT 'حذفٌ ناعم — شرط بوابة المستأجر',
  `deleted_by` int unsigned DEFAULT NULL,
  `policy_key` varchar(80) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (if((`operator_id` is null),concat_ws(_utf8mb4'|',ifnull(cast(`contract_ref` as char charset utf8mb4),_utf8mb4'*'),ifnull(cast(`ops_state` as char charset utf8mb4),_utf8mb4'*'),ifnull(cast(`obligation_type` as char charset utf8mb4),_utf8mb4'*'),ifnull(cast(`effective_from` as char charset utf8mb4),_utf8mb4'*')),NULL)) STORED COMMENT 'بصمةُ قاعدة حكم الساعة بقيمٍ حارسةٍ بديلةٍ عن NULL — وNULL لصفوف المشغّل فتُستثنى (مفتاحُها uq_operator_policy)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operator_policy` (`company_id`,`operator_id`,`work_model`,`pay_basis`,`effective_from`),
  UNIQUE KEY `uq_policy_scope_key` (`company_id`,`party_scope`,`policy_key`),
  KEY `ix_lookup` (`company_id`,`party_scope`,`contract_ref`,`ops_state`),
  KEY `ix_operator_lookup` (`company_id`,`operator_id`,`effective_from`,`effective_to`),
  KEY `ix_lookup_obligation` (`company_id`,`party_scope`,`contract_ref`,`obligation_type`,`ops_state`),
  KEY `ix_policy_state` (`company_id`,`party_scope`,`policy_state`),
  KEY `ix_policy_superseded` (`superseded_by`),
  CONSTRAINT `ck_chp_expired_note` CHECK (((`policy_state` <> _utf8mb4'expired') or ((`state_note` is not null) and (`state_note` <> _utf8mb4'')))),
  CONSTRAINT `ck_chp_superseded` CHECK (((`policy_state` <> _utf8mb4'superseded') or (`superseded_by` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D02 §3.8 — سياسة استحقاق عقد الساعة لكل طرفٍ وحالةٍ بإصداراتها';

-- ── Table: contract_lifecycle_events ──
CREATE TABLE `contract_lifecycle_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `contract_id` int NOT NULL,
  `state` enum('extension','renewal','suspension','natural_end','client_fault_end','our_fault_end','pre_start_cancel','dispute') COLLATE utf8mb4_unicode_ci NOT NULL,
  `effect_date` date NOT NULL COMMENT 'تاريخُ الأثر — وما قبله بحكمه القديم',
  `decision_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجعُ القرار — إلزاميٌّ للإنهاء والإلغاء',
  `advance_effect` enum('continue','settle_and_new','pause_recovery','consume_then_refund','refund_all_after_offset','refund_after_dues','refund_full','freeze') COLLATE utf8mb4_unicode_ci NOT NULL,
  `retention_effect` enum('hold','release_after_grace','release','may_forfeit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `unbilled_effect` enum('bill_cycle','final_claim_old','bill_before_pause','final_claim','bill_all','bill_accepted_only','none','freeze_disputed_bill_rest') COLLATE utf8mb4_unicode_ci NOT NULL,
  `penalty_effect` enum('continue','close_old_start_new','pause_time_not_performance','accrue_to_effect_date','company_claims_compensation','breach_penalties_capped','mobilization_cost_if_article','suspend_until_resolution') COLLATE utf8mb4_unicode_ci NOT NULL,
  `container_effect` enum('extend','new_tree','suspend','close_readonly','close_with_ref','close','cancel') COLLATE utf8mb4_unicode_ci NOT NULL,
  `claim_amount` decimal(18,2) DEFAULT NULL COMMENT 'تعويضٌ أو غرامةٌ — موجبٌ لنا وسالبٌ علينا',
  `claim_currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_article` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مادةُ العقد الحاكمة — **إلزاميةٌ مع أيِّ مبلغ**',
  `claim_doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مستندُ الحساب الموثَّق',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cle_event` (`contract_id`,`state`,`effect_date`),
  KEY `ix_cle_lookup` (`company_id`,`state`,`effect_date`),
  CONSTRAINT `ck_cle_cancel_tree` CHECK (((`container_effect` <> _utf8mb4'cancel') or (`state` = _utf8mb4'pre_start_cancel'))),
  CONSTRAINT `ck_cle_claim_article` CHECK (((`claim_amount` is null) or ((`contract_article` is not null) and (`claim_doc_ref` is not null) and (`claim_currency` is not null) and (`claim_amount` <> 0)))),
  CONSTRAINT `ck_cle_decision` CHECK (((`state` not in (_utf8mb4'natural_end',_utf8mb4'client_fault_end',_utf8mb4'our_fault_end',_utf8mb4'pre_start_cancel')) or (`decision_ref` is not null))),
  CONSTRAINT `ck_cle_effects` CHECK ((((`state` = _utf8mb4'extension') and (`advance_effect` = _utf8mb4'continue') and (`retention_effect` = _utf8mb4'hold') and (`unbilled_effect` = _utf8mb4'bill_cycle') and (`penalty_effect` = _utf8mb4'continue') and (`container_effect` = _utf8mb4'extend')) or ((`state` = _utf8mb4'renewal') and (`advance_effect` = _utf8mb4'settle_and_new') and (`retention_effect` = _utf8mb4'release_after_grace') and (`unbilled_effect` = _utf8mb4'final_claim_old') and (`penalty_effect` = _utf8mb4'close_old_start_new') and (`container_effect` = _utf8mb4'new_tree')) or ((`state` = _utf8mb4'suspension') and (`advance_effect` = _utf8mb4'pause_recovery') and (`retention_effect` = _utf8mb4'hold') and (`unbilled_effect` = _utf8mb4'bill_before_pause') and (`penalty_effect` = _utf8mb4'pause_time_not_performance') and (`container_effect` = _utf8mb4'suspend')) or ((`state` = _utf8mb4'natural_end') and (`advance_effect` = _utf8mb4'consume_then_refund') and (`retention_effect` = _utf8mb4'release_after_grace') and (`unbilled_effect` = _utf8mb4'final_claim') and (`penalty_effect` = _utf8mb4'accrue_to_effect_date') and (`container_effect` = _utf8mb4'close_readonly')) or ((`state` = _utf8mb4'client_fault_end') and (`advance_effect` = _utf8mb4'refund_all_after_offset') and (`retention_effect` = _utf8mb4'release') and (`unbilled_effect` = _utf8mb4'bill_all') and (`penalty_effect` = _utf8mb4'company_claims_compensation') and (`container_effect` = _utf8mb4'close_with_ref')) or ((`state` = _utf8mb4'our_fault_end') and (`advance_effect` = _utf8mb4'refund_after_dues') and (`retention_effect` = _utf8mb4'may_forfeit') and (`unbilled_effect` = _utf8mb4'bill_accepted_only') and (`penalty_effect` = _utf8mb4'breach_penalties_capped') and (`container_effect` = _utf8mb4'close')) or ((`state` = _utf8mb4'pre_start_cancel') and (`advance_effect` = _utf8mb4'refund_full') and (`retention_effect` = _utf8mb4'release') and (`unbilled_effect` = _utf8mb4'none') and (`penalty_effect` = _utf8mb4'mobilization_cost_if_article') and (`container_effect` = _utf8mb4'cancel')) or ((`state` = _utf8mb4'dispute') and (`advance_effect` = _utf8mb4'freeze') and (`retention_effect` = _utf8mb4'hold') and (`unbilled_effect` = _utf8mb4'freeze_disputed_bill_rest') and (`penalty_effect` = _utf8mb4'suspend_until_resolution') and (`container_effect` = _utf8mb4'suspend'))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PLAN-03 §6 — اقتصادُ دورة الحياة: الأثرُ محكومٌ بالحالة لا يُختار';

-- ── Table: contract_monthly_plan ──
CREATE TABLE `contract_monthly_plan` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `contract_id` int NOT NULL,
  `line_id` int unsigned NOT NULL COMMENT 'بندُ البيع (P-02) — والجدولُ يقسّم كميتَه',
  `plan_version` int NOT NULL DEFAULT '1' COMMENT 'نسخةُ الخطة — والملحقُ يفتح نسخةً لا يعدّل',
  `effective_from` date NOT NULL COMMENT 'سريانُ النسخة — «أثرُ ما قبله بالنسخة السابقة»',
  `period_month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM',
  `qty_planned` decimal(16,2) NOT NULL COMMENT 'كميةُ الشهر — **وصفرٌ يعني توقفًا معلَنًا لا غيابَ بيان**',
  `month_kind` enum('normal','mobilization','ramp_up','shutdown','maintenance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT 'طبيعةُ الشهر — «شهرُ تعبئةٍ وشهرُ توقف» بأسمائهما',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cmp_month` (`line_id`,`plan_version`,`period_month`) COMMENT 'شهرٌ واحدٌ لكل (بند × نسخة) — لا تكديسَ ولا ازدواج',
  KEY `ix_cmp_lookup` (`company_id`,`contract_id`,`plan_version`,`period_month`),
  CONSTRAINT `fk_cmp_line` FOREIGN KEY (`line_id`) REFERENCES `client_contract_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_cmp_month_fmt` CHECK (regexp_like(`period_month`,_utf8mb4'^[0-9]{4}-[0-9]{2}$')),
  CONSTRAINT `ck_cmp_qty` CHECK ((`qty_planned` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PLAN-03 §2 — الجدولُ الشهريُّ لبند البيع بنسخه: شهرُ تعبئةٍ وشهرُ توقف';

-- ── Table: contract_notes ──
CREATE TABLE `contract_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `contract_id` int NOT NULL,
  `note` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `fk_contract_notes_contract` (`contract_id`),
  KEY `fk_contract_notes_created_by` (`created_by`),
  CONSTRAINT `fk_contract_notes_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_contract_notes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_contract_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_obligations ──
CREATE TABLE `contract_obligations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل المستأجر',
  `client_contract_id` int NOT NULL COMMENT 'عقدُ العميل — contracts.id (FK حقيقيّ · قرارُ المالك ③)',
  `obligation_type` enum('fuel','access_road','loading_equipment','equipment_readiness','operators','permits_safety','utilities','catering_camp','force_majeure') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'بنودُ §4 التسعة: الوقود · الطريق · معدات التحميل · جاهزية المعدة · المشغّلون · التصاريح · المرافق · الإعاشة · القاهرة',
  `obligor` enum('client','company','supplier','operator','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'company' COMMENT 'الطرفُ الملتزم. الافتراضُ company تنفيذًا لقاعدة §4 «ما لم يُنص عليه يُعدُّ التزامَ الشركة» · و none للقاهرة (لا طرفَ ملتزمًا — قرارُ المالك ②)',
  `effect_on_billing` enum('billable_standby','non_billable','per_clause') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'per_clause' COMMENT 'أثرُ الإخلال على الفوترة. الافتراضُ per_clause أي «اقرأ البند» — لا حكمَ مشتقًّا صامتًا',
  `approval_state` enum('draft','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'مسودةٌ يملؤها 12 · ومُجازةٌ يعتمدها 19 — والمحلِّلُ لا يقرأ إلا المُجاز (ق-18)',
  `approved_by` int DEFAULT NULL COMMENT 'مَن أجاز — الدور 19 حصرًا (تفرضه المنحُ والشاشة)',
  `approved_at` datetime DEFAULT NULL COMMENT 'لحظةُ الإجازة — وبها يصير الصفُّ نافذًا وغيرَ قابلٍ للتعديل',
  `penalty_rule_id` int DEFAULT NULL COMMENT 'قاعدةُ الجزاء — بلا هدفٍ حتى تُبنى contract_penalty_rules (§6 · T-07)؛ فلا FK اليوم',
  `valid_from` date NOT NULL COMMENT 'بدءُ السريان — NOT NULL عمدًا: المفتاحُ الفريد أدناه يشمله، وMySQL تعدّ NULLات متمايزةً فتمرّ التكراراتُ صامتةً',
  `valid_to` date DEFAULT NULL COMMENT 'نهايةُ السريان — NULL أي مفتوح',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obligation_contract_type_from` (`client_contract_id`,`obligation_type`,`valid_from`) COMMENT 'بندٌ واحدٌ لكل (عقد × نوع × تاريخ سريان) — وتغييرُ الملتزم صفٌّ جديدٌ بسريانه لا تعديلُ الماضي (§6 الملاحق: لا رجعية)',
  KEY `ix_obligation_scope` (`company_id`,`is_deleted`),
  KEY `ix_obligation_contract` (`client_contract_id`),
  KEY `ix_obligation_validity` (`valid_from`,`valid_to`),
  KEY `fk_obligation_penalty_rule` (`penalty_rule_id`),
  KEY `ix_obligation_effective` (`client_contract_id`,`approval_state`,`valid_from`,`valid_to`),
  CONSTRAINT `fk_contract_obligations_contract` FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_obligation_penalty_rule` FOREIGN KEY (`penalty_rule_id`) REFERENCES `contract_penalty_rules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CON-02 §4/§8 — مصفوفةُ التزامات عقد العميل: منها يُشتق المسؤولُ لا من حالة الساعة';

-- ── Table: contract_operational_sites ──
CREATE TABLE `contract_operational_sites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `contract_id` int NOT NULL,
  `site_id` int NOT NULL COMMENT 'الموقع/المنجم من `sites` (H-05) — الكيانُ المستقل',
  `scope_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسمُ النطاق داخل العقد — قد يخالف اسمَ الموقع',
  `start_date` date DEFAULT NULL COMMENT 'NULL = من بداية العقد',
  `end_date` date DEFAULT NULL COMMENT 'NULL = إلى نهايته',
  `state` enum('planned','active','paused','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'النطاقُ الرئيسيُّ للعقد — واحدٌ على الأكثر',
  `primary_flag` tinyint(1) GENERATED ALWAYS AS (if((`is_primary` = 1),1,NULL)) STORED COMMENT 'حيلةُ الفريد: NULL لغير الرئيسي — وMySQL لا تقيّد الـNULLات، فينتج «رئيسٌ واحدٌ على الأكثر»',
  `close_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cos_contract_site` (`company_id`,`contract_id`,`site_id`) COMMENT 'الموقعُ مرةً واحدةً في العقد — فلا نطاقان لموقعٍ واحد',
  UNIQUE KEY `uq_cos_primary` (`contract_id`,`primary_flag`) COMMENT '«رئيسٌ واحدٌ على الأكثر» بنيويًّا',
  KEY `ix_cos_lookup` (`company_id`,`contract_id`,`state`),
  KEY `ix_cos_site` (`company_id`,`site_id`),
  KEY `fk_cos_site` (`site_id`),
  CONSTRAINT `fk_cos_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  CONSTRAINT `ck_cos_closed` CHECK (((`state` <> _utf8mb4'closed') or ((`close_reason` is not null) and (`close_reason` <> _utf8mb4'')))),
  CONSTRAINT `ck_cos_name` CHECK ((`scope_name` <> _utf8mb4'')),
  CONSTRAINT `ck_cos_span` CHECK (((`start_date` is null) or (`end_date` is null) or (`end_date` >= `start_date`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PLAN-03 §2.1 — نطاقُ العقد التشغيلي: الموقعُ داخل العقد باسمه ومدته';

-- ── Table: contract_payment_schedule ──
CREATE TABLE `contract_payment_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `contract_id` int NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL COMMENT 'NULL = النسخةُ النافذة · والقديمةُ تُختم ولا تُمحى',
  `amendment_id` int DEFAULT NULL COMMENT 'الملحقُ الذي فتح النسخة',
  `seq` int NOT NULL COMMENT 'ترتيبُ السطر داخل النسخة',
  `pattern` enum('single_payment','advance_then_monthly','partial_advance','advance_installments','milestone_payments','monthly_claim','final_payment','retention_release') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly_claim',
  `payment_kind` enum('advance','monthly_settlement','milestone','final','retention_release','single') COLLATE utf8mb4_unicode_ci NOT NULL,
  `advance_type` enum('recoverable','mobilization','non_refundable_booking','milestone_earned') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `treatment` enum('liability','revenue') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'المعالجةُ المحاسبية — محكومةٌ بالنوع إلا في التعبئة فبنص العقد',
  `treatment_basis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نصُّ العقد الذي حكم معالجةَ التعبئة — إلزاميٌّ لها وحدَها',
  `amount_basis` enum('percent','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `percent_value` decimal(7,3) DEFAULT NULL,
  `amount_expected` decimal(18,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `due_date` date DEFAULT NULL,
  `due_condition` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'شرطُ الاستحقاق حين لا تاريخَ ثابت',
  `period_month` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'شهرُ الجدول (P-03) الذي وُلد منه السطر',
  `line_id` int unsigned DEFAULT NULL COMMENT 'بندُ البيع إن كان السطرُ لبندٍ بعينه',
  `received_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `remaining_amount` decimal(18,2) GENERATED ALWAYS AS ((`amount_expected` - `received_amount`)) STORED,
  `state` enum('not_due','due','partial','completed','overdue') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_due',
  `collection_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `advance_id` int unsigned DEFAULT NULL COMMENT 'صفُّ القبض في contract_advances (M-01) — **للالتزام وحدَه**',
  `source` enum('generated','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generated' COMMENT '«تُولَّد آليًّا … ولا تُدخل كلُّها يدويًّا»',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cps_seq` (`contract_id`,`version`,`seq`),
  KEY `ix_cps_lookup` (`company_id`,`contract_id`,`state`,`due_date`),
  KEY `ix_cps_live` (`contract_id`,`effective_to`),
  CONSTRAINT `ck_cps_advance_link` CHECK (((`advance_id` is null) or (`treatment` = _utf8mb4'liability'))),
  CONSTRAINT `ck_cps_advance_type` CHECK ((((`payment_kind` <> _utf8mb4'advance') or (`advance_type` is not null)) and ((`payment_kind` = _utf8mb4'advance') or (`advance_type` is null)))),
  CONSTRAINT `ck_cps_amounts` CHECK (((`amount_expected` >= 0) and (`received_amount` >= 0) and (`received_amount` <= `amount_expected`))),
  CONSTRAINT `ck_cps_due` CHECK (((`due_date` is not null) or (`due_condition` is not null))),
  CONSTRAINT `ck_cps_month_fmt` CHECK (((`period_month` is null) or regexp_like(`period_month`,_utf8mb4'^[0-9]{4}-[0-9]{2}$'))),
  CONSTRAINT `ck_cps_percent` CHECK ((((`amount_basis` <> _utf8mb4'percent') or ((`percent_value` is not null) and (`percent_value` > 0) and (`percent_value` <= 100))) and ((`percent_value` is null) or ((`percent_value` >= 0) and (`percent_value` <= 100))))),
  CONSTRAINT `ck_cps_treatment` CHECK ((((`advance_type` is null) and (`treatment` is null)) or ((`advance_type` = _utf8mb4'recoverable') and (`treatment` = _utf8mb4'liability')) or ((`advance_type` = _utf8mb4'non_refundable_booking') and (`treatment` = _utf8mb4'revenue')) or ((`advance_type` = _utf8mb4'milestone_earned') and (`treatment` = _utf8mb4'revenue')) or ((`advance_type` = _utf8mb4'mobilization') and (`treatment` is not null) and (`treatment_basis` is not null)))),
  CONSTRAINT `ck_cps_window` CHECK (((`effective_to` is null) or (`effective_to` >= `effective_from`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PLAN-03 §3.5 — خطةُ الدفع بأنماطها الثمانية وأنواعِ المقدم الأربعة';

-- ── Table: contract_penalty_assessments ──
CREATE TABLE `contract_penalty_assessments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل المستأجر',
  `client_contract_id` int NOT NULL COMMENT 'عقدُ العميل',
  `rule_id` int DEFAULT NULL COMMENT 'قاعدةُ الجزاء المطبَّقة — NULL للحد الأدنى المضمون (مصدرُه contract_commitments لا قاعدةَ جزاء)',
  `commitment_ref` int unsigned DEFAULT NULL COMMENT 'البندُ الملتزَمُ المرساة',
  `kind` enum('penalty','incentive','min_guarantee') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'غرامةٌ تُخصم · حافزٌ يُضاف · حدٌّ أدنى يُكمَّل — وثلاثتُها بنودٌ ظاهرةٌ لا خصمٌ صامت (§6)',
  `rule_kind` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'لقطةُ نوع القاعدة وقتَ الاحتساب — للتدقيق بعد تغيّر القاعدة',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `periodicity` enum('daily','monthly','contract') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `committed_qty` decimal(18,4) DEFAULT NULL COMMENT 'الكميةُ الملتزمُ بها في الفترة',
  `actual_qty` decimal(18,4) DEFAULT NULL COMMENT 'المنفَّذُ فعلًا (من قيود الإيراد لا من تقديرٍ)',
  `gap_qty` decimal(18,4) DEFAULT NULL COMMENT 'الفارق — موجبٌ عجزًا وسالبٌ تجاوزًا',
  `readiness_pct` decimal(6,2) DEFAULT NULL COMMENT 'ساعاتُ العمل ÷ ساعاتِ الوردية — لـreadiness_min',
  `unit_price` decimal(18,4) DEFAULT NULL COMMENT 'سعرُ الوحدة المستعمل — لقطةٌ لا اشتقاقٌ لاحق',
  `base_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'قيمةُ البند الملتزَم في الفترة (ق-12) — أساسُ السقف',
  `raw_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'المبلغُ قبل السقف',
  `cap_amount` decimal(18,2) DEFAULT NULL COMMENT 'السقفُ المطبَّق — NULL أي بلا سقف',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'المبلغُ النهائي (موجبٌ دائمًا) — والاتجاهُ من kind لا من الإشارة',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `state` enum('computed','reviewed','approved','waived','posted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'computed' COMMENT 'دورةُ ق-13: النظامُ يحتسب · 12 يراجع · 19 يُجيز أو يُعفي · ثم يُنشر القيد',
  `waive_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سببُ الإعفاء — **إلزاميٌّ** عند waived (تفرضه الخدمة)',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'المعيارُ اليدويُّ لـbonus_fixed (الجودةُ والسلامة · ق-10)',
  `event_id` int DEFAULT NULL COMMENT 'قيدُ الدفتر المولَّد عند الإجازة — **نتيجةٌ لا مُدخَل** (ق-7)',
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `rule_key` varchar(24) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (concat(ifnull(cast(`rule_id` as char charset utf8mb4),_utf8mb4'*'),_utf8mb4':',ifnull(cast(`commitment_ref` as char charset utf8mb4),_utf8mb4'*'))) STORED COMMENT 'مرساةُ الاحتساب للمفتاح الفريد — * أي بلا قاعدةٍ/بند',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assessment_period` (`client_contract_id`,`kind`,`rule_key`,`period_from`,`period_to`) COMMENT 'احتسابٌ واحدٌ لكل (عقد × نوع × مرساة × فترة) — إعادةُ التشغيل تُحدّث ولا تُضاعف (ق-11)',
  KEY `ix_assessment_scope` (`company_id`,`is_deleted`),
  KEY `ix_assessment_state` (`state`),
  KEY `ix_assessment_period` (`client_contract_id`,`period_from`,`period_to`),
  KEY `fk_assessment_rule` (`rule_id`),
  CONSTRAINT `fk_assessment_contract` FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_assessment_rule` FOREIGN KEY (`rule_id`) REFERENCES `contract_penalty_rules` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CON-02 §6 — احتسابُ الجزاء والحافز والحد الأدنى لفترةٍ بعينها بدورة اعتماده';

-- ── Table: contract_penalty_rules ──
CREATE TABLE `contract_penalty_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل المستأجر',
  `client_contract_id` int NOT NULL COMMENT 'عقدُ العميل — contracts.id (FK حقيقيّ)',
  `rule_kind` enum('shortfall_pct','readiness_min','bonus_qty_pct','bonus_fixed') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نوعا جزاءٍ ونوعا حافزٍ — قائمةٌ مغلقةٌ عمدًا (ق-9): لا توسيعَ فوق الأربعة',
  `commitment_ref` int unsigned DEFAULT NULL COMMENT 'البندُ الملتزَمُ المرساة (contract_commitments.id) — NULL أي قاعدةٌ على مستوى العقد كلِّه',
  `rate` decimal(6,3) DEFAULT NULL COMMENT 'نسبةُ الغرامة/الحافز: من قيمة الفارق (shortfall_pct) أو من قيمة الفترة (readiness_min)',
  `min_readiness_pct` decimal(5,2) DEFAULT NULL COMMENT 'عتبةُ الجاهزية — لـreadiness_min وحدَها. الجاهزيةُ = ساعاتُ العمل ÷ ساعاتِ الوردية',
  `fixed_amount` decimal(16,2) DEFAULT NULL COMMENT 'المبلغُ المقطوع — لـbonus_fixed وحدَه (الجودةُ والسلامةُ بمعيارٍ يدويٍّ معتمد · ق-10)',
  `cap_percent` decimal(5,2) DEFAULT NULL COMMENT 'السقفُ نسبةً من قيمة البند الملتزَم في الفترة (ق-12) — الأساسُ والسقفُ من جنسٍ واحد',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'عملةُ المبلغ المقطوع — NULL أي عملةُ العقد',
  `periodicity` enum('daily','monthly','contract') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly' COMMENT 'دوريةُ الاحتساب — ويُؤجَّل حتى تكتمل الدورية ولا يُحتسب نسبيًّا (ق-11)',
  `valid_from` date NOT NULL COMMENT 'بدءُ السريان — NOT NULL عمدًا: يشمله المفتاحُ الفريد، وNULL تُمرّر التكراراتِ صامتةً',
  `valid_to` date DEFAULT NULL COMMENT 'نهايةُ السريان — NULL أي مفتوح',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'بندُ العقد أو مرجعُ القاعدة',
  `commitment_key` varchar(16) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (ifnull(cast(`commitment_ref` as char charset utf8mb4),_utf8mb4'*')) STORED COMMENT 'مرساةُ القاعدة للمفتاح الفريد — * أي على مستوى العقد',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_penalty_rule` (`client_contract_id`,`rule_kind`,`commitment_key`,`valid_from`) COMMENT 'قاعدةٌ واحدةٌ لكل (عقد × نوع × مرساة × تاريخ سريان) — والتعديلُ صفٌّ جديدٌ بسريانه (لا رجعية)',
  KEY `ix_penalty_scope` (`company_id`,`is_deleted`),
  KEY `ix_penalty_contract` (`client_contract_id`,`valid_from`,`valid_to`),
  KEY `fk_penalty_rule_commitment` (`commitment_ref`),
  CONSTRAINT `fk_penalty_rule_commitment` FOREIGN KEY (`commitment_ref`) REFERENCES `contract_commitments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_penalty_rule_contract` FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CON-02 §6/§8 — قواعدُ الجزاء والحافز: نوعان لكلٍّ، بسقفٍ ومرساةٍ وسريان';

-- ── Table: contract_price_index_readings ──
CREATE TABLE `contract_price_index_readings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `index_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reading_date` date NOT NULL,
  `value` decimal(20,8) NOT NULL,
  `source_ref` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مرجعُ المستند — إلزاميٌّ بنيويًّا',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_index_reading` (`company_id`,`index_code`,`reading_date`),
  CONSTRAINT `ck_price_index_ref` CHECK ((char_length(trim(`source_ref`)) > 0)),
  CONSTRAINT `ck_price_index_value` CHECK ((`value` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_price_revisions ──
CREATE TABLE `contract_price_revisions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `term_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `contract_item_id` int NOT NULL COMMENT 'سطرُ contractequipments المتأثر — صفٌّ لكل بندٍ ولو كان الشرطُ عقديًّا',
  `period_key` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مفتاحُ الدورة (2026-07 · 2026-Q3 · 2026-H1 · 2026)',
  `as_of_date` date NOT NULL,
  `effective_from` date NOT NULL COMMENT 'من هنا يسري السعرُ الجديد — ولا رجعيةَ قبله',
  `index_value` decimal(20,8) DEFAULT NULL COMMENT 'NULL = لا قراءةَ (مُعلَنٌ لا مخترع)',
  `index_source` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delta_percent` decimal(10,4) DEFAULT NULL COMMENT 'فارقُ المؤشر عن أساسه',
  `applied_percent` decimal(10,4) DEFAULT NULL COMMENT 'بعد التمرير والسقف',
  `old_price` decimal(14,4) DEFAULT NULL,
  `new_price` decimal(14,4) DEFAULT NULL,
  `outcome` enum('amended','below_threshold','capped','no_reading','no_base_price') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amendment_id` int unsigned DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_revision_period_item` (`term_id`,`period_key`,`contract_item_id`),
  KEY `ix_price_revision_live` (`company_id`,`contract_id`,`effective_from`),
  KEY `fk_price_revision_amd` (`amendment_id`),
  KEY `ix_price_revision_term` (`term_id`),
  CONSTRAINT `fk_price_revision_amd` FOREIGN KEY (`amendment_id`) REFERENCES `contract_amendments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_price_revision_term` FOREIGN KEY (`term_id`) REFERENCES `contract_price_terms` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_price_terms ──
CREATE TABLE `contract_price_terms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contract_id` int NOT NULL COMMENT 'عقدُ العميل — منبعُ التسعير (CON-02 §1)',
  `contract_item_id` int NOT NULL DEFAULT '0' COMMENT 'سطرُ contractequipments — **0 = كلُّ بنود العقد** (لا NULL: المفتاحُ الفريد لا يراه)',
  `trigger_kind` enum('fuel','inflation','fx') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'وقودٌ · تضخمٌ · صرف — قائمةُ §2-③ نصًّا',
  `index_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'رمزُ المؤشر — وللصرف رمزُ العملة (المصدرُ fin_fx_rates)',
  `base_index` decimal(20,8) NOT NULL COMMENT 'القيمةُ المرجعيةُ يومَ التعاقد',
  `base_date` date DEFAULT NULL,
  `threshold_percent` decimal(6,3) NOT NULL DEFAULT '0.000' COMMENT 'عتبةُ التفعيل — دونها لا تعديل',
  `pass_through_percent` decimal(6,3) NOT NULL DEFAULT '100.000' COMMENT 'كم من تغيّر المؤشر يدخل السعر',
  `cap_percent` decimal(6,3) DEFAULT NULL COMMENT 'سقفُ المراجعة الواحدة — NULL = بلا سقفٍ مكتوب',
  `periodicity` enum('monthly','quarterly','semiannual','annual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'quarterly',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_term_scope` (`contract_id`,`contract_item_id`,`trigger_kind`,`valid_from`),
  KEY `ix_price_term_co` (`company_id`,`contract_id`,`state`),
  CONSTRAINT `fk_price_term_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_price_term_base` CHECK ((`base_index` > 0)),
  CONSTRAINT `ck_price_term_cap` CHECK (((`cap_percent` is null) or (`cap_percent` > 0))),
  CONSTRAINT `ck_price_term_pass` CHECK (((`pass_through_percent` > 0) and (`pass_through_percent` <= 100))),
  CONSTRAINT `ck_price_term_threshold` CHECK ((`threshold_percent` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_resource_plan ──
CREATE TABLE `contract_resource_plan` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `contract_id` int NOT NULL,
  `line_id` int unsigned NOT NULL COMMENT 'بندُ البيع (P-02) — والخطةُ تقول كيف تُنتَج كميتُه',
  `equipment_type_id` int NOT NULL COMMENT 'نوعُ المعدة (equipments_types) — لا معدةٌ بعينها: الخطةُ نوعٌ لا أصل',
  `equipment_size` int DEFAULT NULL COMMENT 'الحجمُ/السعةُ التصنيفية كما في العقد',
  `count_basic` int NOT NULL DEFAULT '0' COMMENT 'الأساسيةُ — هي التي تُنتج',
  `count_backup` int NOT NULL DEFAULT '0' COMMENT 'الاحتياطيةُ — جاهزيةٌ لا إنتاجٌ مخطَّط',
  `shifts_per_day` tinyint unsigned NOT NULL DEFAULT '1',
  `hours_per_shift` decimal(5,2) NOT NULL DEFAULT '0.00',
  `operators_count` int NOT NULL DEFAULT '0' COMMENT 'طلبُ عمالةٍ مخطَّط — **لا استحقاقَ ولا كلفة**',
  `supervisors_count` int NOT NULL DEFAULT '0',
  `technicians_count` int NOT NULL DEFAULT '0',
  `assistants_count` int NOT NULL DEFAULT '0',
  `capacity_share_percent` decimal(6,3) NOT NULL DEFAULT '0.000' COMMENT 'حصةُ هذا النوع من كمية البند — Σ الحصص = 100 عند الاكتمال',
  `share_kind` enum('productive','backup_only','support') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'productive' COMMENT 'المنتجُ يحمل حصةً · والاحتياطيُّ والمساندُ صفرًا **معلَنًا**',
  `operational_site_id` int unsigned DEFAULT NULL COMMENT 'نطاقُ العقد (P-01) إن خُصّصت الخطةُ لموقع',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('draft','active','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'المنتهيةُ تبقى للتاريخ — والتعديلُ إنهاءٌ وإضافةٌ لا محو',
  `end_reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_contract_equipment_id` int DEFAULT NULL COMMENT 'أصلُها في contractequipments إن جاءت من القديم — والقديمُ لا يُمَس',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int unsigned DEFAULT NULL,
  `live_type_key` int GENERATED ALWAYS AS (if(((`state` = _utf8mb4'ended') or (`is_deleted` = 1)),NULL,`equipment_type_id`)) STORED,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crp_live_type` (`line_id`,`live_type_key`) COMMENT 'نوعٌ واحدٌ نافذٌ لكل بند — ولا صفَّان يتنازعان الحصةَ نفسَها',
  KEY `ix_crp_lookup` (`company_id`,`contract_id`,`state`),
  KEY `ix_crp_type` (`equipment_type_id`),
  CONSTRAINT `fk_crp_line` FOREIGN KEY (`line_id`) REFERENCES `client_contract_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_crp_type` FOREIGN KEY (`equipment_type_id`) REFERENCES `equipments_types` (`id`),
  CONSTRAINT `ck_crp_counts` CHECK (((`count_basic` >= 0) and (`count_backup` >= 0) and (`shifts_per_day` >= 1) and (`shifts_per_day` <= 4) and (`hours_per_shift` >= 0) and (`hours_per_shift` <= 24) and (`operators_count` >= 0) and (`supervisors_count` >= 0) and (`technicians_count` >= 0) and (`assistants_count` >= 0))),
  CONSTRAINT `ck_crp_ended` CHECK (((`state` <> _utf8mb4'ended') or (`end_reason` is not null))),
  CONSTRAINT `ck_crp_productive` CHECK (((`share_kind` <> _utf8mb4'productive') or (`capacity_share_percent` > 0))),
  CONSTRAINT `ck_crp_share` CHECK (((`capacity_share_percent` >= 0) and (`capacity_share_percent` <= 100))),
  CONSTRAINT `ck_crp_window` CHECK (((`valid_to` is null) or (`valid_to` >= `valid_from`))),
  CONSTRAINT `ck_crp_zero_share` CHECK (((`share_kind` = _utf8mb4'productive') or (`capacity_share_percent` = 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PLAN-03 §2 — خطةُ الموارد: حصصُ الأنواع تغذّي الحاويات **ولا تحمل سعرًا**';

-- ── Table: contract_snapshots ──
CREATE TABLE `contract_snapshots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `as_of_date` date NOT NULL COMMENT 'تاريخُ الاحتساب الذي أُخذت له اللقطة',
  `snapshot_json` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'المضمونُ القانوني: الرأسُ + المكوّناتُ + القواعدُ بتوزيعها + التحمّل — فرزٌ ثابت',
  `fingerprint` char(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sha1 من المضمون القانوني — كشفُ التلاعب بالمقارنة',
  `amendment_ref` int DEFAULT NULL COMMENT 'آخرُ ملحقٍ ساري — NULL معلَنًا حتى تُبنى H-10 (لا اختراع)',
  `valid` tinyint NOT NULL DEFAULT '1',
  `invalidated_at` datetime DEFAULT NULL,
  `invalidated_from` date DEFAULT NULL COMMENT 'تاريخُ سريان الإبطال — ما قبله يبقى محكومًا بلقطته',
  `invalidation_reason` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cs_contract_asof` (`contract_id`,`as_of_date`,`valid`),
  KEY `ix_cs_company` (`company_id`),
  KEY `ix_cs_fingerprint` (`fingerprint`),
  CONSTRAINT `fk_cs_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contractequipments ──
CREATE TABLE `contractequipments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `contract_id` int NOT NULL COMMENT 'رقم العقد',
  `equip_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نوع المعدة',
  `equip_size` int DEFAULT NULL COMMENT 'حجم المعدة',
  `equip_count` int DEFAULT NULL COMMENT 'عدد المعدات',
  `equip_count_basic` int DEFAULT '0' COMMENT 'عدد المعدات الأساسية',
  `equip_count_backup` int DEFAULT '0' COMMENT 'عدد المعدات الاحتياطية',
  `equip_shifts` int DEFAULT '0' COMMENT 'عدد الورديات',
  `equip_unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ساعة' COMMENT 'الوحدة',
  `shift1_start` time DEFAULT NULL COMMENT 'وقت بداية الوردية الأولى',
  `shift1_end` time DEFAULT NULL COMMENT 'وقت نهاية الوردية الأولى',
  `shift2_start` time DEFAULT NULL COMMENT 'وقت بداية الوردية الثانية',
  `shift2_end` time DEFAULT NULL COMMENT 'وقت نهاية الوردية الثانية',
  `shift_hours` int DEFAULT '0' COMMENT 'إجمالي ساعات الوردية',
  `equip_total_month` int DEFAULT NULL COMMENT 'إجمالي الساعات اليومية ',
  `equip_monthly_target` int DEFAULT '0' COMMENT 'وحدات العمل في الشهر',
  `equip_total_contract` int DEFAULT NULL COMMENT 'إجمالي ساعات العقد',
  `equip_price` decimal(10,2) DEFAULT '0.00' COMMENT 'السعر',
  `equip_operators` int DEFAULT '0' COMMENT 'المشغلين',
  `equip_supervisors` int DEFAULT '0' COMMENT 'المشرفين',
  `equip_technicians` int DEFAULT '0' COMMENT 'الفنيين',
  `equip_assistants` int DEFAULT '0' COMMENT 'المساعدين',
  `equip_price_currency` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'تمييز السعر',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`),
  CONSTRAINT `fk_contractequipments_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contracts ──
CREATE TABLE `contracts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int DEFAULT '0',
  `contract_duration_months` int DEFAULT '0',
  `contract_duration_days` int NOT NULL DEFAULT '0',
  `equip_shifts_contract` int DEFAULT '0' COMMENT 'عدد الورديات للعقد',
  `shift_contract` int DEFAULT '0' COMMENT 'ساعات الوردية للعقد',
  `equip_total_contract_daily` int DEFAULT '0' COMMENT 'إجمالي الوحدات يومياً للعقد',
  `total_contract_permonth` int DEFAULT '0' COMMENT 'وحدات العمل في الشهر للعقد',
  `total_contract_units` int DEFAULT '0' COMMENT 'إجمالي وحدات العقد',
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `transportation` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `accommodation` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `place_for_living` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `workshop` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hours_monthly_target` int DEFAULT '0',
  `forecasted_contracted_hours` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `daily_work_hours` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `daily_operators` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_party` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_party` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_one` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_two` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_currency_contract` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'عملة العقد',
  `paid_contract` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'المبلغ المدفوع',
  `payment_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)',
  `guarantees` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'الضمانات',
  `retention_pct` decimal(5,2) DEFAULT NULL COMMENT 'نسبةُ ضمان حسن التنفيذ المحتجزةُ من كل مستخلص — NULL أي لا احتجاز',
  `advance_recovery_pct` decimal(5,2) DEFAULT NULL COMMENT 'نسبةُ استهلاك الدفعة المقدمة من كل مستخلص — NULL أي لا استهلاك',
  `payment_date` date DEFAULT NULL COMMENT 'تاريخ الدفع',
  `contract_status` enum('مسودة','تفاوض','معتمد','موقَّع','نافذ','قيد التنفيذ','معلَّق','معدَّل','مجدَّد','منتهٍ','مقفل','مصفّى') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'H-02 · OPM-01 §3: آلةُ حالات العقد. الانتقالُ يُحرَس في ContractStateMachine لا في الشاشة. «نافذ» = شرطُ فتح الحاويات (H-01)',
  `pause_state_before` enum('مسودة','تفاوض','معتمد','موقَّع','نافذ','قيد التنفيذ','معلَّق','معدَّل','مجدَّد','منتهٍ','مقفل','مصفّى') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'H-02: الحالةُ قبل التعليق — يعود إليها بالاستئناف. NULL = عُلّق قبل الآلة فلا يُخمَّن',
  `pause_reason` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pause_date` date DEFAULT NULL COMMENT 'تاريخ إيقاف العقد',
  `resume_date` date DEFAULT NULL COMMENT 'تاريخ استئناف العقد',
  `termination_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `termination_reason` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `merged_with` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1' COMMENT '1=نشط, 0=موقوف',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `project_id` int NOT NULL COMMENT 'PLAN-03 §2.1: لا عقدَ بلا مشروع — بنيويًّا لا رجاءً',
  `site_id` int DEFAULT NULL COMMENT '⚠ مرآةٌ موروثةٌ (P-01) — المصدرُ `contract_operational_sites`. لا يُكتب ولا يُقرأ في حسابٍ جديد، ويبقى لأن الحذفَ ممنوع (§0-④)',
  `readiness_state` enum('لم يبدأ','جارٍ','مجتاز') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'لم يبدأ' COMMENT 'INJAZ-S05 §6.6 — محسوبٌ من readiness_lines (عرضٌ لا إنفاذ)',
  PRIMARY KEY (`id`),
  KEY `fk_contracts_merged` (`merged_with`),
  KEY `idx_contracts_project_id` (`project_id`),
  KEY `idx_contracts_signing_date` (`contract_signing_date`),
  KEY `idx_contracts_status_contract_status` (`status`,`contract_status`),
  KEY `ix_contract_state` (`company_id`,`contract_status`),
  KEY `ix_contracts_site` (`site_id`),
  CONSTRAINT `fk_contracts_merged` FOREIGN KEY (`merged_with`) REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_contracts_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`),
  CONSTRAINT `fk_contracts_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: cost_bearers ──
CREATE TABLE `cost_bearers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `owner_type` enum('component','rule') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'المالك: مكوّنُ أجرٍ أو قاعدةُ حافز (§7.1)',
  `owner_id` int NOT NULL,
  `bearer_type` enum('project','client_contract','dept','company') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'جهاتُ §3.3 الأربع: مشروعٌ · عقدُ عميل · إدارةٌ داخلية · كيانُ الشركة',
  `bearer_id` int DEFAULT NULL COMMENT 'NULL لجهة company (صاحبُ العمل نفسُه)',
  `percent` decimal(5,2) NOT NULL,
  `created_by` int DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cb_owner` (`owner_type`,`owner_id`),
  KEY `ix_cb_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: credit_debit_notes ──
CREATE TABLE `credit_debit_notes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `note_no` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CDN-سنة-تسلسل — ترقيمٌ خادميٌّ لكل شركة',
  `note_kind` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'credit=يُنقص ذمّةَ العميل · debit=يزيدها. المبلغُ موجبٌ دائمًا والاتجاهُ يحمل الإشارة',
  `claim_id` int unsigned NOT NULL COMMENT 'المستخلصُ الأصلي — مرجعٌ لا يُمسّ',
  `claim_line_id` int unsigned DEFAULT NULL COMMENT 'سطرُه بعينه إن كان الإشعارُ على سطر — NULL = على المستخلص كلِّه',
  `receivable_id` int DEFAULT NULL COMMENT 'الذمّةُ التي يتحرك بها — تُملأ عند الإجازة',
  `invoice_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقمُ الفاتورة الأصلية — نسخةٌ للقراءة',
  `currency` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL COMMENT 'موجبٌ دائمًا — الاتجاهُ في note_kind',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'سببُ الإشعار — إلزام',
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مرجعُ المستند المؤيِّد — إلزام',
  `state` enum('draft','review','approved','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `idem_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مفتاحُ العطالة من المنادي — يمنع إصدارَ الإشعار نفسِه مرتين',
  `prepared_by` int unsigned DEFAULT NULL,
  `submitted_by` int unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `event_id` int DEFAULT NULL COMMENT 'حقيقةُ الإشعار في الجذر المحايد',
  `version` int NOT NULL DEFAULT '1' COMMENT 'قفلٌ تفاؤليّ — نظيرُ claims.version',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_note_no` (`company_id`,`note_no`),
  UNIQUE KEY `uq_note_idem` (`company_id`,`claim_id`,`note_kind`,`idem_key`),
  KEY `ix_claim` (`company_id`,`claim_id`),
  KEY `ix_state` (`company_id`,`state`),
  KEY `ix_receivable` (`company_id`,`receivable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-02 — إشعاراتٌ دائنة/مدينة تصحّح فاتورةً صادرةً بلا أن تمسّها';

-- ── Table: daily_plan_lines ──
CREATE TABLE `daily_plan_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `equipment_container_id` int unsigned NOT NULL COMMENT 'حاويةُ المعدة — مصدرُ الاحتياج (OPM-01 §4)',
  `equipment_id` int unsigned DEFAULT NULL,
  `shift_no` tinyint unsigned NOT NULL DEFAULT '1',
  `operator_employee_id` int DEFAULT NULL,
  `operator_container_id` int unsigned DEFAULT NULL COMMENT '«لا تخصيصَ خارج حاوية» — حاويةُ المشغّل من سلسلة معدته حصرًا',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dpl_need` (`plan_id`,`equipment_container_id`,`shift_no`) COMMENT 'احتياجُ (معدة×وردية) لا يتكرر',
  KEY `ix_dpl_company` (`company_id`),
  KEY `ix_dpl_operator` (`operator_employee_id`),
  KEY `ix_dpl_equipment` (`equipment_id`,`shift_no`),
  KEY `fk_dpl_eq_container` (`equipment_container_id`),
  KEY `fk_dpl_op_container` (`operator_container_id`),
  CONSTRAINT `fk_dpl_eq_container` FOREIGN KEY (`equipment_container_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `fk_dpl_op_container` FOREIGN KEY (`operator_container_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `fk_dpl_plan` FOREIGN KEY (`plan_id`) REFERENCES `daily_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: daily_plans ──
CREATE TABLE `daily_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `project_id` int NOT NULL,
  `plan_date` date NOT NULL,
  `state` enum('draft','approved','opened','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'الدورة: توزيعٌ (draft) ← اعتمادُ الحركة ← فتحُ الغد ← إقفالُ يومه',
  `reopen_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dp_project_date` (`project_id`,`plan_date`) COMMENT 'خطةٌ واحدةٌ ليومِ المشروع',
  KEY `ix_dp_company` (`company_id`),
  KEY `ix_dp_state_date` (`state`,`plan_date`),
  CONSTRAINT `fk_dp_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: decision_reasons ──
CREATE TABLE `decision_reasons` (
  `reason_id` int unsigned NOT NULL AUTO_INCREMENT,
  `domain` enum('sales','suppliers','financiers','workforce','fleet','maintenance','procurement','treasury','operations') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_kind` enum('return','reject','state_change','exception') COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_ar` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requires_document` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`reason_id`),
  UNIQUE KEY `uq_dr_code` (`domain`,`reason_kind`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §10: أسباب القرار قائمة محكومة لا نص حر — فتُقاس ويُبنى عليها تقرير';

-- ── Table: deduction_proposals ──
CREATE TABLE `deduction_proposals` (
  `ded_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `person_id` int NOT NULL,
  `period` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` enum('late','missing_punch','leave_no_balance','unexcused','penalty','advance_installment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'المستند/اليوم المصدر — لا خصم بلا مصدر (M-11)',
  `proposed_amount` decimal(14,2) NOT NULL,
  `is_voluntary` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'الاستقطاعات الاختيارية (سلف · نيابة) تخضع لحد ثلث الصافي — والجزاءات والغياب خارجه (DEC ②)',
  `state` enum('Proposed','Reviewed','Approved','Posted','Waived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Proposed',
  `reviewed_by` int DEFAULT NULL,
  `approvals_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع سلّم GOV-01',
  `posted_run_id` int DEFAULT NULL,
  `waiver_ref` int DEFAULT NULL COMMENT 'قرار الإعفاء المستقل (waivers_reversals) — والأصل باقٍ',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ded_id`),
  UNIQUE KEY `uq_dp_source` (`person_id`,`period`,`source`,`source_ref`),
  KEY `ix_dp_state` (`company_id`,`period`,`state`),
  CONSTRAINT `ck_dp_posted_needs_approval` CHECK (((`state` <> _utf8mb4'Posted') or (`approvals_ref` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §6: لا خصم يُرحَّل مباشرة — Proposed ثم سلّم GOV-01 ثم Posted (CHECK بنيوي)';

-- ── Table: deduction_types ──
CREATE TABLE `deduction_types` (
  `ded_id` int unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` int unsigned NOT NULL,
  `ded_kind` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `formula_json` json DEFAULT NULL,
  `cap` decimal(18,4) DEFAULT NULL,
  `auto_propose` tinyint(1) NOT NULL DEFAULT '1',
  `requires_approval` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'دائمًا 1 — لا خصم آلي الترحيل في أي إدارة',
  PRIMARY KEY (`ded_id`),
  KEY `ix_dt_policy` (`policy_id`),
  CONSTRAINT `fk_dt_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_dt_approval` CHECK ((`requires_approval` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §9: أنواع الخصم — يُقترح ويُعتمد، ولا ترحيل مباشرًا بنيويًّا (CHECK)';

-- ── Table: dept_policies ──
CREATE TABLE `dept_policies` (
  `policy_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `domain` enum('sales','suppliers','financiers','workforce','fleet','maintenance','procurement','treasury') COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_type` enum('department','project','contract','employee_type','asset_type') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'department',
  `scope_id` int unsigned NOT NULL DEFAULT '0' COMMENT '0 = الإدارة كلها',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `state` enum('draft','active','superseded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`policy_id`),
  UNIQUE KEY `uq_dp_scope` (`company_id`,`domain`,`scope_type`,`scope_id`,`valid_from`),
  KEY `ix_dp_domain` (`company_id`,`domain`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §2: هوية السياسة ونطاقها — ولا سياسة بلا نطاق ومدة، ولا تشغيل لإدارة بلا سياسة نافذة';

-- ── Table: driver_contract_notes ──
CREATE TABLE `driver_contract_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `contract_id` int NOT NULL COMMENT 'معرف عقد السائق',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الملاحظة أو الإجراء المتخذ',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإضافة',
  PRIMARY KEY (`id`),
  KEY `idx_driver_contract_notes_contract_id` (`contract_id`),
  CONSTRAINT `fk_driver_contract_notes_contract` FOREIGN KEY (`contract_id`) REFERENCES `drivercontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل التدقيق لإجراءات عقود السائقين';

-- ── Table: drivercontractequipments ──
CREATE TABLE `drivercontractequipments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `contract_id` int NOT NULL COMMENT 'معرف عقد السائق من جدول drivercontracts',
  `equip_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع المعدة (حفار، قلاب، خرامة)',
  `equip_size` int DEFAULT NULL COMMENT 'حجم المعدة',
  `equip_count` int DEFAULT NULL COMMENT 'عدد المعدات',
  `equip_count_basic` int DEFAULT '0' COMMENT 'عدد المعدات الأساسية',
  `equip_count_backup` int DEFAULT '0' COMMENT 'عدد المعدات الاحتياطية',
  `equip_shifts` int DEFAULT NULL COMMENT 'عدد الورديات',
  `equip_unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'وحدة القياس (ساعة، طن، متر)',
  `shift1_start` time DEFAULT NULL COMMENT 'بداية الوردية الأولى',
  `shift1_end` time DEFAULT NULL COMMENT 'نهاية الوردية الأولى',
  `shift2_start` time DEFAULT NULL COMMENT 'بداية الوردية الثانية',
  `shift2_end` time DEFAULT NULL COMMENT 'نهاية الوردية الثانية',
  `shift_hours` decimal(10,2) DEFAULT NULL COMMENT 'ساعات الوردية',
  `equip_total_month` decimal(10,2) DEFAULT NULL COMMENT 'إجمالي الوحدات يومياً',
  `equip_monthly_target` decimal(10,2) DEFAULT NULL COMMENT 'وحدات العمل في الشهر',
  `equip_total_contract` decimal(10,2) DEFAULT NULL COMMENT 'إجمالي وحدات العقد',
  `equip_price` decimal(10,2) DEFAULT NULL COMMENT 'السعر للوحدة',
  `equip_price_currency` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'العملة (دولار، جنيه)',
  `equip_operators` int DEFAULT NULL COMMENT 'عدد المشغلين',
  `equip_supervisors` int DEFAULT NULL COMMENT 'عدد المشرفين',
  `equip_technicians` int DEFAULT NULL COMMENT 'عدد الفنيين',
  `equip_assistants` int DEFAULT NULL COMMENT 'عدد المساعدين',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`),
  CONSTRAINT `fk_drivercontractequipments_contract` FOREIGN KEY (`contract_id`) REFERENCES `drivercontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='معدات عقود السائقين';

-- ── Table: drivercontracts ──
CREATE TABLE `drivercontracts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int DEFAULT '0',
  `contract_duration_months` int DEFAULT '0',
  `contract_duration_days` int DEFAULT '0',
  `equip_shifts_contract` int DEFAULT '0' COMMENT 'عدد ورديات المعدات في العقد',
  `shift_contract` int DEFAULT '0' COMMENT 'الوردية',
  `equip_total_contract_daily` decimal(10,2) DEFAULT '0.00' COMMENT 'إجمالي الوحدات اليومية للعقد',
  `total_contract_permonth` decimal(10,2) DEFAULT '0.00' COMMENT 'إجمالي وحدات العمل في الشهر',
  `total_contract_units` decimal(10,2) DEFAULT '0.00' COMMENT 'إجمالي وحدات العمل للعقد',
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `transportation` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `accommodation` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `place_for_living` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `workshop` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `equip_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equip_size` int DEFAULT NULL,
  `equip_count` int DEFAULT '0',
  `equip_target_per_month` int DEFAULT '0',
  `equip_total_month` int DEFAULT '0',
  `equip_total_contract` int DEFAULT '0',
  `mach_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mach_size` int DEFAULT NULL,
  `mach_count` int DEFAULT '0',
  `mach_target_per_month` int DEFAULT '0',
  `mach_total_month` int DEFAULT '0',
  `mach_total_contract` int DEFAULT '0',
  `hours_monthly_target` int DEFAULT '0',
  `forecasted_contracted_hours` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `daily_work_hours` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `daily_operators` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_party` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_party` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_one` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_two` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_currency_contract` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'عملة العقد',
  `paid_contract` decimal(10,2) DEFAULT '0.00' COMMENT 'المبلغ المدفوع',
  `payment_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)',
  `guarantees` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'الضمانات',
  `payment_date` date DEFAULT NULL COMMENT 'تاريخ الدفع',
  `pause_reason` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'سبب الإيقاف',
  `pause_date` date DEFAULT NULL COMMENT 'تاريخ الإيقاف',
  `resume_date` date DEFAULT NULL COMMENT 'تاريخ الاستئناف',
  `termination_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع الإنهاء',
  `termination_reason` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'سبب الإنهاء',
  `merged_with` int DEFAULT NULL COMMENT 'دمج مع عقد آخر',
  `project_id` int NOT NULL DEFAULT '0',
  `project_contract_id` int DEFAULT NULL COMMENT 'معرف عقد المشروع',
  `status` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_drivercontracts_project_contract_id` (`project_contract_id`),
  KEY `fk_drivercontracts_driver` (`employee_id`),
  KEY `fk_drivercontracts_project` (`project_id`),
  KEY `fk_drivercontracts_merged` (`merged_with`),
  KEY `idx_dc_status_signing` (`status`,`contract_signing_date`),
  CONSTRAINT `fk_drivercontracts_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_drivercontracts_merged` FOREIGN KEY (`merged_with`) REFERENCES `drivercontracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_drivercontracts_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_drivercontracts_project_contract` FOREIGN KEY (`project_contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: effective_permissions ──
CREATE TABLE `effective_permissions` (
  `ep_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `person_id` int NOT NULL,
  `permission_code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_rule` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_cap` decimal(18,2) DEFAULT NULL,
  `source_kind` enum('relation','family','level','title','assignment','exception','grant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `computed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ep_id`),
  KEY `idx_ep_person` (`company_id`,`person_id`,`permission_code`),
  KEY `idx_ep_code` (`permission_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: جدول مشتق — ومنه يُجاب «لماذا يملكها؟»';

-- ── Table: employee_advances ──
CREATE TABLE `employee_advances` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `person_id` int NOT NULL COMMENT 'employees.id — المستفيد',
  `advance_type` enum('cash','on_behalf','charged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash' COMMENT 'نقديةٌ · دفعٌ نيابةً عنه (علاجٌ · تذاكرُ · رسوم) · مصروفٌ محمَّلٌ عليه',
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مستندُ الصرف — إلزاميٌّ بنيويًّا',
  `issued_date` date NOT NULL,
  `installments_count` int NOT NULL DEFAULT '1' COMMENT 'عددُ أقساط الاسترداد',
  `installment_amount` decimal(18,2) NOT NULL COMMENT 'قسطُ الفترة الواحدة',
  `first_deduction_period` date DEFAULT NULL COMMENT 'أولُ فترةٍ يبدأ منها الخصم',
  `recovered` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'المستردُّ فعلًا — تُحرّكه المقاصّة',
  `balance` decimal(18,2) GENERATED ALWAYS AS ((`amount` - `recovered`)) STORED COMMENT '**مولَّد** — لا يُكتب ولا ينحرف عن حركته',
  `state` enum('draft','approved','active','settled','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_adv_person_state` (`person_id`,`state`),
  KEY `ix_adv_co` (`company_id`,`state`),
  CONSTRAINT `ck_adv_amount` CHECK ((`amount` > 0)),
  CONSTRAINT `ck_adv_doc` CHECK ((char_length(trim(`doc_ref`)) > 0)),
  CONSTRAINT `ck_adv_inst` CHECK (((`installments_count` >= 1) and (`installment_amount` > 0))),
  CONSTRAINT `ck_adv_recovered` CHECK (((`recovered` >= 0) and (`recovered` <= `amount`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employee_contract_amendments ──
CREATE TABLE `employee_contract_amendments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `amend_type` enum('pay_change','duration_change','location_change','scope_change','other') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'أنواعُ §4: «تغييرُ أجرٍ أو مدةٍ أو موقعٍ أو نطاق» + مخرجُ سلامة',
  `effective_from` date NOT NULL COMMENT '«ملحقٌ معتمَدٌ بسريان» — والقراءةُ تأخذ الأحدثَ سريانًا قبل تاريخ الاحتساب',
  `changes_json` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '«ما يغيّره حقلًا حقلًا (قبل/بعد)» — و«قبل» يُلتقط من الواقع الحي',
  `state` enum('draft','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `reject_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eca_contract_eff_type` (`contract_id`,`effective_from`,`amend_type`),
  KEY `ix_eca_company` (`company_id`),
  CONSTRAINT `fk_eca_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employee_contracts ──
CREATE TABLE `employee_contracts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'صاحبُ العمل — عزلُ المستأجر (TenantRegistry)',
  `employee_id` int NOT NULL COMMENT 'سجلُّ الأشخاص القائم — «العقدُ يشير إليه ولا ينسخ»',
  `category` enum('permanent','project','operator','supplier_worker') COLLATE utf8mb4_unicode_ci NOT NULL,
  `relation_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'طبيعةُ الارتباط — يحمل نوعَ الموروث نصًّا عند الترحيل',
  `project_id` int DEFAULT NULL COMMENT 'فئةُ «مشروع» مرتبطةٌ بمشروع عميلٍ ومدتِه (CON-01 §2)',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `probation_end` date DEFAULT NULL,
  `pay_model_id` int NOT NULL COMMENT '«اختيارٌ مستقلٌّ لا يُشتق من الوظيفة» — من الكتالوج المحكوم حصرًا',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NULL حيث لم يسجَّل — لا تلفيق',
  `eos_days_per_year` decimal(5,2) DEFAULT NULL COMMENT 'أيامُ نهاية الخدمة لكل سنةِ خدمة — NULL = لم تُكتب فلا تُحتسب (تُعلَن)',
  `leave_days_per_year` decimal(5,2) DEFAULT NULL COMMENT 'أيامُ الإجازة المستحقة لكل سنة — NULL = لم تُكتب فلا تُحتسب',
  `state` enum('draft','completed','validated','approved','rejected','accepted','declined','signed','active','confirmed','amended','suspended','seconded','expired','terminated','settled','closed','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `state_before_hold` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ما قبل التعليق/الإعارة — العودةُ إلى حيث كان لا إلى حالةٍ مفترضة (قياسُ pause_state_before)',
  `hold_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_file_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'النسخةُ الموقَّعة — ثابتةٌ لا تُستبدل (إلزامُها مع H-10)',
  `version` int NOT NULL DEFAULT '1' COMMENT 'قفلٌ تفاؤلي — 409 عند التزاحم',
  `source_table` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الترحيلُ قراءةً: مصدرُ الصف — الكتابةُ تبقى فيه حتى إقفال القديم بمطابقةٍ (N-04)',
  `source_id` int DEFAULT NULL COMMENT 'معرّفُ الصف في مصدره (لرؤوس سياسات المشغّلين: معرّفُ المشغّل — إسقاطُ مجموعة)',
  `created_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ec_person_company_start` (`employee_id`,`company_id`,`start_date`),
  UNIQUE KEY `uq_ec_source` (`source_table`,`source_id`,`company_id`) COMMENT 'عطالةُ الترحيل — والشركةُ في المفتاح لأن رأسَ سياسات المشغّل معرّفُه معرّفُ المشغّل داخل شركته',
  KEY `ix_ec_state_end` (`state`,`end_date`) COMMENT 'فهرسُ التنبيه (state, end_date) — CON-01 §7.1',
  KEY `ix_ec_company` (`company_id`),
  KEY `fk_ec_project` (`project_id`),
  KEY `fk_ec_pay_model` (`pay_model_id`),
  CONSTRAINT `fk_ec_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_ec_pay_model` FOREIGN KEY (`pay_model_id`) REFERENCES `pay_models` (`id`),
  CONSTRAINT `fk_ec_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`),
  CONSTRAINT `ck_ec_eos_days` CHECK (((`eos_days_per_year` is null) or (`eos_days_per_year` > 0))),
  CONSTRAINT `ck_ec_leave_days` CHECK (((`leave_days_per_year` is null) or (`leave_days_per_year` > 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employee_final_settlement_lines ──
CREATE TABLE `employee_final_settlement_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `settlement_id` int NOT NULL,
  `line_type` enum('dues','leave','eos','advance_offset') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(12,3) DEFAULT NULL COMMENT 'أيامٌ أو سنواتٌ بحسب البند',
  `rate` decimal(18,2) DEFAULT NULL COMMENT 'الأجرُ اليوميُّ المحسوبُ من الأساس',
  `amount` decimal(18,2) NOT NULL,
  `computable` tinyint NOT NULL DEFAULT '1' COMMENT '0 = بلا قاعدةٍ مكتوبةٍ — يُعلَن ولا يُقدَّر',
  `source_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fs_line` (`settlement_id`,`line_type`),
  CONSTRAINT `fk_fs_line` FOREIGN KEY (`settlement_id`) REFERENCES `employee_final_settlements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employee_final_settlements ──
CREATE TABLE `employee_final_settlements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `effective_date` date NOT NULL COMMENT 'تاريخُ الأثر — «المستحقُّ **حتى تاريخ الأثر**»',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `service_years` decimal(6,3) NOT NULL DEFAULT '0.000',
  `dues_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `leave_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `eos_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `advances_offset` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'موجبٌ دائمًا — ويُطرح في الصافي',
  `advances_remaining` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'ما لم تسعه المقاصّة — «لا يُقاصّ أكثرُ من المستحق» ويبقى رصيدًا مفتوحًا يُعلَن',
  `net_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT '**محسوبٌ لا مُدخَل**: المستحقُّ + الإجازةُ + نهايةُ الخدمة − السلف',
  `recognized_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'ما تعترف به التصفيةُ **جديدًا** (إجازةٌ + نهايةُ خدمة) — والمستحقُّ السابقُ اعتُرف به في مصدره',
  `snapshot_id` int DEFAULT NULL COMMENT 'لقطةُ العقد التي احتُسب منها (H-11) — «من اللقطة» إسنادًا لا دعوى',
  `snapshot_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'بصمتُها ساعةَ الاحتساب — يُكشف أيُّ تلاعبٍ بمقارنتها',
  `net_due_ref` int DEFAULT NULL COMMENT 'مرجعُ الحدث المالي الواحد (fin_dues) — «لا يتكرر»',
  `basis_json` text COLLATE utf8mb4_unicode_ci COMMENT 'لقطةُ القواعد والأسس لحظةَ الاحتساب — لا اشتقاقٌ لاحق',
  `state` enum('draft','approved','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `clearance_doc` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرفقُ الإخلاء (§6)',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prepared_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_final_settlement` (`contract_id`) COMMENT '«بمفتاح (العقد × التصفية) لا يتكرر»',
  KEY `ix_final_settlement` (`company_id`,`employee_id`,`state`),
  CONSTRAINT `fk_fs_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_fs_approved` CHECK (((`state` <> _utf8mb4'approved') or ((`approved_by` is not null) and (`clearance_doc` is not null) and (`clearance_doc` <> _utf8mb4'')))),
  CONSTRAINT `ck_fs_cancel` CHECK (((`state` <> _utf8mb4'cancelled') or ((`cancel_reason` is not null) and (`cancel_reason` <> _utf8mb4'')))),
  CONSTRAINT `ck_fs_hands` CHECK (((`approved_by` is null) or (`prepared_by` is null) or (`approved_by` <> `prepared_by`))),
  CONSTRAINT `ck_fs_net` CHECK ((`net_amount` >= 0)),
  CONSTRAINT `ck_fs_offset` CHECK (((`advances_offset` >= 0) and (`advances_remaining` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employee_roles ──
CREATE TABLE `employee_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emprole_company_name` (`company_id`,`name`),
  KEY `idx_emprole_company` (`company_id`),
  KEY `idx_emprole_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employees ──
CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'سائق/مشغّل',
  `company_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `employee_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم الشهرة/الكنية',
  `identity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع الهوية',
  `identity_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم الهوية',
  `identity_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الهوية',
  `employee_photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم رخصة القيادة',
  `license_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع رخصة القيادة',
  `license_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء رخصة القيادة',
  `license_issuer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'جهة إصدار الرخصة',
  `specialized_equipment` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'نوع المعدة المتخصص فيها (متعدد)',
  `years_in_field` int DEFAULT NULL COMMENT 'سنوات العمل في المجال',
  `years_on_equipment` int DEFAULT NULL COMMENT 'سنوات العمل على هذا النوع من المعدات',
  `skill_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مستوى الكفاءة المهنية',
  `certificates` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'الشهادات والتدريبات',
  `owner_supervisor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم المالك/المشرف المباشر',
  `supplier_id` int DEFAULT NULL COMMENT 'المورد الذي يعمل معه',
  `employment_affiliation` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'تبعية المشغل',
  `salary_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع الراتب/الأجر',
  `monthly_salary` decimal(10,2) DEFAULT NULL COMMENT 'المبلغ الشهري التقريبي',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'العنوان',
  `performance_rating` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'تقييم الكفاءة التشغيلية',
  `behavior_record` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سجل السلوك والانضباط',
  `accident_record` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سجل الحوادث والأعطال',
  `health_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الحالة الصحية',
  `health_issues` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'المشاكل الصحية المعروفة',
  `vaccinations_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'التطعيمات والفحوصات',
  `previous_employer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم جهة التوظيف السابقة',
  `employment_duration` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مدة العمل معهم',
  `reference_contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع للاتصال',
  `general_notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ملاحظات عامة',
  `employee_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'نشط',
  `employment_classification` enum('مرشح','متدرب','مقبول','مستقيل','مفصول') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مسار التوظيف — مستقل عن employee_status التشغيلية',
  `start_date` date DEFAULT NULL COMMENT 'تاريخ البدء الفعلي',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ التسجيل في النظام',
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_alternative` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم هاتف بديل',
  `status` tinyint(1) DEFAULT '1',
  `birth_date` date DEFAULT NULL,
  `nationality` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blood_type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_relation` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_issue_date` date DEFAULT NULL,
  `license_grade` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medical_report_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_title_id` int DEFAULT NULL,
  `employee_role_id` int DEFAULT NULL,
  `is_workforce` tinyint(1) NOT NULL DEFAULT '0',
  `worker_category` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` enum('شركة','مورد','مقاول') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workforce_class` enum('أساسي','احتياطي','بديل مؤقت','تغطية إجازة','تجاري مؤقت') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_grade` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workforce_state` enum('مرشّح','مسجّل','مؤهّل','متعاقد','مخصّص','في إجازة','منتهٍ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medical_fitness_status` enum('لائق للعمل','لائق بشروط','موقوف طبيًّا','يحتاج إعادة تقييم') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fitness_conditions` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_backup_id` int DEFAULT NULL,
  `is_replaceable` tinyint(1) DEFAULT '1',
  `worker_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_driver_code` (`employee_code`),
  KEY `idx_driver_name` (`name`),
  KEY `idx_driver_status` (`employee_status`),
  KEY `idx_supplier_id` (`supplier_id`),
  KEY `idx_drivers_project_id` (`project_id`),
  KEY `idx_employees_type` (`employee_type`),
  KEY `idx_emp_job_title` (`job_title_id`),
  KEY `idx_emp_role` (`employee_role_id`),
  KEY `idx_emp_is_workforce` (`is_workforce`),
  KEY `ix_employment_classification` (`employment_classification`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ems_business_events ──
CREATE TABLE `ems_business_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `event_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'BE-nnnn خادمي لكل شركة (ems_sequences — نطاق ems_business_events:BE:{company})',
  `event_uuid` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ULID يُسَكّ عند النشر (مواصفة ADR-15)؛ الردم القديم LEGACY+id',
  `event_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'domain.entity.action — نفس مفردات عقد §9',
  `category` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'التصنيفات السبعة (عقد §9)',
  `source_module` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'VARCHAR لا ENUM — إدارة جديدة بلا DDL (درس ENUM الدفتر)',
  `source_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `quantity` decimal(18,4) DEFAULT NULL,
  `unit` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(16,2) DEFAULT NULL COMMENT 'قيمة الحقيقة إن كانت نقدية — وصفٌ لا قرار مالي',
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fx_rate` decimal(20,8) DEFAULT NULL COMMENT 'سعرُ الصرف لحظةَ الحدث (FES-01 §3.1) — NULL أي لا سعرَ لفترته بعد',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'المعادلُ بعملة الأساس = ROUND(amount × fx_rate, 2) — NULL أي بانتظار سعر',
  `project_id` int DEFAULT NULL,
  `contract_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `supplier_entity_id` int DEFAULT NULL,
  `customer_entity_id` int DEFAULT NULL,
  `operator_employee_id` int DEFAULT NULL,
  `event_status` enum('recorded','corrected','reversed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'recorded' COMMENT 'ADR-18: العكسي = نفس الأثر بكميةٍ سالبة',
  `reverses_event_id` bigint unsigned DEFAULT NULL COMMENT 'ADR-18: id الحقيقة المنقوضة',
  `occurred_at` datetime NOT NULL COMMENT 'لحظة الوقوع الفعلي UTC',
  `payload` json DEFAULT NULL,
  `correlation_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سلسلة الأثر طرفًا لطرف — المشتق يرث الجذر',
  `idempotency_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'عطالة المصدر — نفس مفتاح الإسقاط المالي (كتابة مزدوجة متسقة)',
  `schema_version` smallint unsigned DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ebe_no` (`company_id`,`event_no`),
  UNIQUE KEY `uq_ebe_uuid` (`event_uuid`),
  UNIQUE KEY `uq_ebe_idempotency` (`idempotency_key`),
  KEY `ix_company_id` (`company_id`,`id`),
  KEY `ix_ebe_key` (`company_id`,`event_key`),
  KEY `ix_ebe_entity` (`entity_type`,`entity_id`),
  KEY `ix_ebe_module` (`company_id`,`source_module`),
  KEY `ix_ebe_corr` (`correlation_id`),
  KEY `ix_ebe_occurred` (`company_id`,`occurred_at`),
  KEY `ix_ebe_reverses` (`reverses_event_id`),
  KEY `fk_be_currency` (`company_id`,`currency`),
  CONSTRAINT `fk_be_currency` FOREIGN KEY (`company_id`, `currency`) REFERENCES `fin_currencies` (`company_id`, `code`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ADR-15: الجذر المحايد — سجل الحقائق المؤسسي append-only؛ القناة: EventPublisher حصرًا؛ الدفتر المالي إسقاطه الأول';

-- ── Table: ems_event_consumers ──
CREATE TABLE `ems_event_consumers` (
  `consumer` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم المستهلك المسجَّل (finance, analytics, …)',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `cursor_event_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'آخر حدثٍ عولج بنجاح/تُجووز — مستقل لكل مستهلك',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`consumer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K4: سجلّ مستهلكي الناقل المؤسسي — Cursor مستقل لكل مستهلك';

-- ── Table: ems_event_dead_letter ──
CREATE TABLE `ems_event_dead_letter` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `consumer` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_id` bigint unsigned NOT NULL,
  `attempts` int unsigned NOT NULL,
  `last_error` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dlq_consumer_event` (`consumer`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K4: الحدث السام يُعزل هنا بعد استنفاد المحاولات — الطابور لا يتجمّد خلفه';

-- ── Table: ems_event_deliveries ──
CREATE TABLE `ems_event_deliveries` (
  `consumer` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_id` bigint unsigned NOT NULL,
  `attempts` int unsigned NOT NULL DEFAULT '1',
  `last_error` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_retry_at` datetime DEFAULT NULL COMMENT 'N-06: موعد المحاولة التالية (تصاعد 2^attempts دقيقة) — NULL = مستحقة الآن',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`consumer`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K4: محاولات تسليمٍ جارية (تُحذف عند النجاح أو تنتقل للرسائل الميتة)';

-- ── Table: ems_processed_events ──
CREATE TABLE `ems_processed_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `consumer` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم المستهلك (يوافق ems_event_consumers.consumer)',
  `event_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'معرّف الحدث الكوني المُستهلَك',
  `processed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_processed` (`consumer`,`event_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='عطالة استهلاك موزّعة (عقد قابلية التوزيع) — يُفعَّل عند تعدّد الموزّعات';

-- ── Table: ems_sequences ──
CREATE TABLE `ems_sequences` (
  `scope` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نطاق المتتالية، مثال: fin_financial_events:EV:4',
  `next_val` bigint unsigned NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K8: متتاليات ذرّية للترقيم الخادمي (ServerId::nextNo)';

-- ── Table: ems_state_transitions ──
CREATE TABLE `ems_state_transitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم تعريف سير العمل',
  `entity_table` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `company_id` int DEFAULT NULL,
  `from_state` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'أكواد لاتينية (ADR-08 — الجديد فقط)',
  `to_state` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_user_id` int NOT NULL,
  `actor_role` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الدور الفعّال لحظة الانتقال (من جسر K6)',
  `actor_source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'role' COMMENT 'role|position — مصدر الدور الفعّال',
  `note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_st_entity` (`entity_table`,`entity_id`),
  KEY `idx_st_workflow_time` (`workflow`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K7: سجل انتقالات محرك الحالات — append-only، لا يُعدَّل ولا يُحذف منه';

-- ── Table: entity_licenses ──
CREATE TABLE `entity_licenses` (
  `lic_id` int unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int unsigned NOT NULL,
  `lic_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issuer` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lic_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `alert_days` int unsigned NOT NULL DEFAULT '30',
  `file_ref` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('active','expired','renewed','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`lic_id`),
  KEY `ix_el_expiry` (`expiry_date`,`state`),
  KEY `fk_el_entity` (`entity_id`),
  CONSTRAINT `fk_el_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §5: التراخيص بتواريخ انتهائها وتنبيهاتها';

-- ── Table: entity_ownership ──
CREATE TABLE `entity_ownership` (
  `own_id` int unsigned NOT NULL AUTO_INCREMENT,
  `owner_type` enum('person','entity') COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` int NOT NULL COMMENT 'users.id للشخص أو legal_entities.entity_id للكيان',
  `owned_entity_id` int unsigned NOT NULL,
  `percent` decimal(5,2) NOT NULL,
  `ownership_kind` enum('shares','stocks','partnership') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shares',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_percent` decimal(5,2) DEFAULT NULL,
  `corrected_percent` decimal(5,2) DEFAULT NULL,
  `correction_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`own_id`),
  KEY `ix_eo_owned` (`owned_entity_id`,`valid_from`),
  KEY `ix_eo_owner` (`owner_type`,`owner_id`),
  CONSTRAINT `fk_eo_owned` FOREIGN KEY (`owned_entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_eo_pct` CHECK (((`percent` > 0) and (`percent` <= 100)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §3: علاقات الملكية بنسبة ومدة — Σ=100 عند ownership_completeness=full وحده (الخدمة) ولا تعديل بأثر رجعي';

-- ── Table: entity_roles ──
CREATE TABLE `entity_roles` (
  `role_id` int unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int unsigned NOT NULL,
  `role` enum('holding','operating','project','client','supplier','financier','government') COLLATE utf8mb4_unicode_ci NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_er_entity_role` (`entity_id`,`role`,`valid_from`),
  KEY `ix_er_role` (`role`,`valid_to`),
  CONSTRAINT `fk_er_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §2-②: صفات الكيان جدول علاقة مؤرَّخ — لا حقل نصي';

-- ── Table: equipment_documents ──
CREATE TABLE `equipment_documents` (
  `doc_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `subject_type` enum('equipment','operator','supplier') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'equipment' COMMENT 'محورُ الوثيقة — والموردُ محورٌ ثالثٌ (M-19) لا جدولٌ ثانٍ',
  `subject_id` int unsigned NOT NULL COMMENT 'equipments.id أو employees.id بحسب subject_type — مرجعٌ مرن',
  `doc_type` enum('استمارة','تأمين','فحص دوري','رخصة قيادة','رخصة تشغيل','تصريح','هوية','جواز سفر','عقد عمل','سجل تجاري','شهادة ضريبية','شهادة بنكية','أخرى') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'UX-10 §8.1 + وثائقُ الأفراد + **وثائقُ المورد النظامية** (UX-05 §5.1-①)',
  `doc_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issuer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'جهةُ الإصدار',
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL COMMENT 'NULL = وثيقةٌ لا تنتهي (نادر — تُعلَن)',
  `alert_days` smallint unsigned NOT NULL DEFAULT '30' COMMENT 'التنبيهُ قبل الانتهاء بهذه المدة (§8.1)',
  `file_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مسارُ المرفق',
  `status` enum('سارية','منتهية','قيد التجديد','ملغاة') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'سارية' COMMENT 'حالةٌ يديرها البشر — والانتهاءُ الفعلي يُحسب من expiry_date لا منها',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migrated_from` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نسبُ الترحيل: equipments.license / equipment_operators.license — NULL للجديد',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`doc_id`),
  UNIQUE KEY `uq_doc` (`company_id`,`subject_type`,`subject_id`,`doc_type`,`doc_no`),
  KEY `ix_expiry` (`company_id`,`expiry_date`) COMMENT 'فهرسُ التنبيه الآلي (§8.1)',
  KEY `ix_subject` (`company_id`,`subject_type`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='UX-10 §8.1 — وثائقُ المعدة والمشغّل بتواريخ انتهائها وتنبيهها';

-- ── Table: equipment_drivers ──
CREATE TABLE `equipment_drivers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `equipment_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `start_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `end_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift_type` enum('D','N','B') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'B',
  `status` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_equipment_drivers_equipment` (`equipment_id`),
  KEY `fk_equipment_drivers_driver` (`employee_id`),
  CONSTRAINT `fk_equipment_drivers_driver` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_drivers_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: equipment_operators ──
CREATE TABLE `equipment_operators` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `license_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_grade` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_issuer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_issue_date` date DEFAULT NULL,
  `license_expiry_date` date DEFAULT NULL,
  `license_photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operating_categories` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `driving_authorizations` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medical_report_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operator_employee` (`employee_id`),
  KEY `idx_operator_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: equipment_ownership_registry ──
CREATE TABLE `equipment_ownership_registry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `equipment_id` int NOT NULL,
  `actual_owner_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_type` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_phone` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_supplier_relation` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operational_source` enum('financed','supplier_external') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N-19: قيمتان لا ثالثة — واردة عبر التمويل (لنا) أو عبر مورد خارجي؛ NULL = غير محددة (حالة نقص تُغلق)',
  `purchase_value` decimal(18,2) DEFAULT NULL COMMENT 'قيمة الشراء — أشد الحقول سرية',
  `purchase_currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migrated_from` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'equipments',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `source_decided_by` int DEFAULT NULL COMMENT 'N-19: قرار الإقفال لكل معدة',
  `source_decided_at` datetime DEFAULT NULL,
  `source_decision_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eor_equipment` (`company_id`,`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-21: المجال المقيَّد لملكية المعدات — لا يُستعلم منه إلا عبر OwnershipDomainGuard';

-- ── Table: equipments ──
CREATE TABLE `equipments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `created_by` int DEFAULT NULL COMMENT 'منشئ المعدة',
  `created_at` datetime DEFAULT NULL COMMENT 'تاريخ إضافة المعدة',
  `suppliers` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم المعدة/الرقم التسلسلي',
  `chassis_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم الهيكل/الهيكل الأساسي',
  `machine_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم الماكينة أو المحرك',
  `manufacturer` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الماركة/الشركة المصنعة',
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الموديل/الطراز',
  `model_id` int DEFAULT NULL,
  `manufacturing_year` int DEFAULT NULL COMMENT 'سنة الصنع',
  `import_year` int DEFAULT NULL COMMENT 'سنة الاستيراد/البدء',
  `equipment_condition` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'في حالة جيدة' COMMENT 'حالة المعدة',
  `operating_hours` int DEFAULT NULL COMMENT 'ساعات التشغيل',
  `engine_condition` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'جيدة' COMMENT 'حالة المحرك',
  `tires_condition` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'N/A' COMMENT 'حالة الإطارات',
  `actual_owner_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم المالك الفعلي',
  `owner_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع المالك',
  `owner_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم هاتف المالك',
  `owner_supplier_relation` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'علاقة المالك بالمورد',
  `license_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم الترخيص/التسجيل',
  `license_authority` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'جهة الترخيص',
  `document_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع الوثيقة',
  `license_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الترخيص',
  `inspection_certificate_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم شهادة الفحص',
  `last_inspection_date` date DEFAULT NULL COMMENT 'تاريخ آخر فحص',
  `current_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الموقع الحالي',
  `site_supervisor_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم المهندس أو المشرف في الموقع',
  `site_supervisor_contact` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'بيانات الاتصال بالمشرف في الموقع',
  `availability_state` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'متوفرة' COMMENT 'التوفر: متوفرة أو غير متوفرة',
  `availability_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'متاحة للعمل' COMMENT 'حالة التوفر',
  `estimated_value` decimal(15,2) DEFAULT NULL COMMENT 'القيمة المقدرة للمعدة',
  `daily_rental_price` decimal(10,2) DEFAULT NULL COMMENT 'سعر التأجير اليومي',
  `monthly_rental_price` decimal(10,2) DEFAULT NULL COMMENT 'سعر التأجير الشهري',
  `insurance_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'التأمين/الضمان',
  `general_notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ملاحظات عامة',
  `last_maintenance_date` date DEFAULT NULL COMMENT 'تاريخ آخر صيانة',
  `status` tinyint(1) DEFAULT '1',
  `operating_category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `engine_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` decimal(12,2) DEFAULT NULL,
  `capacity_uom` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dimensions` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entry_date` date DEFAULT NULL,
  `acquisition_cost` decimal(15,2) DEFAULT NULL,
  `acquisition_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opening_meter` decimal(12,2) DEFAULT NULL,
  `meter_uom` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ساعات',
  `meter_source` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_state` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `card_approved_by` int DEFAULT NULL,
  `card_approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_serial_number` (`serial_number`),
  KEY `idx_chassis_number` (`chassis_number`),
  KEY `idx_manufacturer` (`manufacturer`),
  KEY `idx_availability_status` (`availability_status`),
  KEY `idx_equipments_model_id` (`model_id`),
  KEY `idx_equipments_card_state` (`card_state`),
  KEY `idx_equipments_supplier_status_type` (`suppliers`,`status`,`type`),
  KEY `idx_equipments_company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: equipments_types ──
CREATE TABLE `equipments_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `form` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','','') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: evaluations ──
CREATE TABLE `evaluations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `capacity_id` int unsigned NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `self_scores_json` text COLLATE utf8mb4_unicode_ci,
  `self_closed_at` datetime DEFAULT NULL,
  `mgr_scores_json` text COLLATE utf8mb4_unicode_ci,
  `mgr_by` int DEFAULT NULL,
  `mgr_comment` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'إلزاميٌّ عند فارقٍ ≥ درجتين',
  `discussion_notes` text COLLATE utf8mb4_unicode_ci,
  `final_score` decimal(5,2) DEFAULT NULL,
  `state` enum('SelfDraft','SelfClosed','MgrDraft','Discussed','Approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SelfDraft',
  `version` int NOT NULL DEFAULT '1',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eval` (`capacity_id`,`period_from`,`period_to`),
  CONSTRAINT `fk_eval_capacity` FOREIGN KEY (`capacity_id`) REFERENCES `user_capacities` (`id`),
  CONSTRAINT `ck_eval_approved` CHECK (((`state` <> _utf8mb4'Approved') or ((`approved_by` is not null) and (`approved_at` is not null) and (`final_score` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='USR-01 §7 — التقييمُ الثنائي: ذاتيٌّ ثم مديرٌ ثم مناقشةٌ فاعتماد';

-- ── Table: exception_approvals ──
CREATE TABLE `exception_approvals` (
  `app_id` int unsigned NOT NULL AUTO_INCREMENT,
  `req_id` int unsigned NOT NULL,
  `approver_person_id` int NOT NULL,
  `approver_role` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الدور — لا دور يتكرر في طلب واحد (تحرسه الخدمة 409)',
  `auth_id` int unsigned DEFAULT NULL COMMENT 'مرجع التفويض (LEG-01)',
  `seq_no` tinyint unsigned NOT NULL,
  `decision` enum('approve','reject') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`app_id`),
  UNIQUE KEY `uq_exa_seq` (`req_id`,`seq_no`),
  CONSTRAINT `fk_exa_req` FOREIGN KEY (`req_id`) REFERENCES `exception_requests` (`req_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §7: موافقات الاستثناء بالتسلسل — approver ≠ requester ولا دور مكرر';

-- ── Table: exception_requests ──
CREATE TABLE `exception_requests` (
  `req_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `guard_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_person_id` int NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `risk_level` enum('normal','operational','financial','high','legal_forbidden') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'محسوب — يُرفع لا يُخفض إلا بقرار',
  `scope_type` enum('person','operation','equipment','contract','period') COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL COMMENT 'إلزامي — لا استثناء مفتوح المدة',
  `one_time` tinyint(1) NOT NULL DEFAULT '0',
  `documents_json` json DEFAULT NULL,
  `expected_impact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('Draft','Pending','Approved','Rejected','Active','Expired','Revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `usage_count` int unsigned NOT NULL DEFAULT '0',
  `closed_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`req_id`),
  KEY `ix_exr_guard` (`guard_code`,`state`,`valid_to`),
  KEY `ix_exr_company` (`company_id`,`state`),
  CONSTRAINT `fk_exr_guard` FOREIGN KEY (`guard_code`) REFERENCES `guard_policies` (`guard_code`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §7: طلبات الاستثناء — بمدة ونطاق وسبب ومستندات، ولا استثناء عام';

-- ── Table: exception_usages ──
CREATE TABLE `exception_usages` (
  `usage_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `req_id` int unsigned NOT NULL,
  `operation_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `person_id` int NOT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usage_id`),
  KEY `ix_exu_req` (`req_id`,`at`),
  CONSTRAINT `fk_exu_req` FOREIGN KEY (`req_id`) REFERENCES `exception_requests` (`req_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §7-⑤: كل عبور باستثناء يُسجَّل — Insert-only';

-- ── Table: failure_codes ──
CREATE TABLE `failure_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `equipment_type` tinyint(1) NOT NULL COMMENT '1=حفار, 2=قلاب, 3=خرامة',
  `event_type_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'كود نوع الحدث: EQF,MNT,DEP,CST,MST,HRF,MKF',
  `event_type_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم نوع الحدث بالعربي',
  `main_category_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'كود الفئة الرئيسية: MEC,HYD,ELE,COL...',
  `main_category_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم الفئة الرئيسية',
  `sub_category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الفئة الفرعية (الجزء المعطل)',
  `failure_detail` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'تفصيل العطل',
  `full_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الكود الكامل مثل EX-EQF-MEC-01-01',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_equipment_type` (`equipment_type`),
  KEY `idx_event_type` (`equipment_type`,`event_type_code`),
  KEY `idx_main_cat` (`equipment_type`,`event_type_code`,`main_category_code`),
  KEY `idx_sub_cat` (`equipment_type`,`event_type_code`,`main_category_code`,`sub_category`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تصنيفات أعطال المعدات - مرجع موحد';

-- ── Table: fin_accountants ──
CREATE TABLE `fin_accountants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL COMMENT 'employees.id (مرجع مرن)',
  `admin_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الإدارة المتبوعة إداريّاً (المصدر التشغيلي)',
  `finance_unit_id` int NOT NULL COMMENT 'fin_units.id (الوحدة المتبوعة فنيّاً)',
  `specialization` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review_limit_usd` decimal(14,2) DEFAULT NULL COMMENT 'حدّ المراجعة الأوّلية (لا الاعتماد المالي)',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_acct` (`company_id`,`employee_id`,`admin_module`),
  KEY `ix_fin_acct_module` (`company_id`,`admin_module`),
  KEY `ix_fin_acct_deleted` (`is_deleted`),
  KEY `fk_fin_acct_unit` (`finance_unit_id`),
  CONSTRAINT `fk_fin_acct_unit` FOREIGN KEY (`finance_unit_id`) REFERENCES `fin_units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_approval_matrix ──
CREATE TABLE `fin_approval_matrix` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `event_type` enum('revenue','expense','payable','receivable','payroll','settlement','funding','any') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'any',
  `min_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `max_amount` decimal(16,2) DEFAULT NULL COMMENT 'NULL = بلا حد أعلى',
  `required_level` enum('dept_accountant','dept_manager','finance_manager','executive','board') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence` int NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_am_band` (`company_id`,`event_type`,`min_amount`),
  KEY `ix_fin_am_level` (`company_id`,`required_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_approvals ──
CREATE TABLE `fin_approvals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `entity_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'financial_event',
  `entity_id` int NOT NULL,
  `from_state` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_state` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` enum('advance','reject','post','settle') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'advance',
  `level` enum('dept_accountant','dept_manager','finance_reviewer','auditor','finance_manager') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_id` int DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_appr_entity` (`company_id`,`entity_type`,`entity_id`),
  KEY `ix_fin_appr_created` (`company_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_assets ──
CREATE TABLE `fin_assets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_id` int DEFAULT NULL COMMENT 'equipments.id (مرجع مرن، اختياري)',
  `acquisition_date` date DEFAULT NULL,
  `acquisition_cost` decimal(16,2) NOT NULL DEFAULT '0.00',
  `salvage_value` decimal(16,2) NOT NULL DEFAULT '0.00',
  `useful_life_months` int NOT NULL DEFAULT '60',
  `method` enum('straight_line') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'straight_line',
  `accumulated_depreciation` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'يزيد مع كل احتساب',
  `book_value` decimal(16,2) GENERATED ALWAYS AS ((`acquisition_cost` - `accumulated_depreciation`)) STORED,
  `state` enum('active','fully_depreciated','disposed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_asset_code` (`company_id`,`code`),
  KEY `ix_fin_asset_state` (`company_id`,`state`),
  KEY `ix_fin_asset_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_bank_accounts ──
CREATE TABLE `fin_bank_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `opening_balance` decimal(16,2) NOT NULL DEFAULT '0.00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_bank_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_bank_statement_lines ──
CREATE TABLE `fin_bank_statement_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `bank_account_id` int NOT NULL,
  `txn_date` date NOT NULL,
  `description` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direction` enum('deposit','withdrawal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'deposit=إيداع، withdrawal=سحب',
  `amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `matched_payment_id` int DEFAULT NULL COMMENT 'fin_payments (soft)',
  `reconciled` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_bsl_acct` (`company_id`,`bank_account_id`),
  KEY `ix_fin_bsl_rec` (`company_id`,`reconciled`),
  KEY `fk_fin_bsl_acct` (`bank_account_id`),
  CONSTRAINT `fk_fin_bsl_acct` FOREIGN KEY (`bank_account_id`) REFERENCES `fin_bank_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_budget_lines ──
CREATE TABLE `fin_budget_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `budget_id` int NOT NULL,
  `line_kind` enum('revenue','expense') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('salaries','fuel','maintenance','procurement','catering','transport','operational_need','capacity_need','revenue','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` int DEFAULT NULL COMMENT 'fin_chart_of_accounts.id',
  `planned_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `actual_amount` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'يُغذّى من القيود المرحّلة',
  `variance` decimal(16,2) GENERATED ALWAYS AS ((`actual_amount` - `planned_amount`)) STORED,
  `variance_pct` decimal(9,2) GENERATED ALWAYS AS ((case when (`planned_amount` = 0) then NULL else (((`actual_amount` - `planned_amount`) / `planned_amount`) * 100) end)) STORED,
  `cause` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `corrective_action` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsible_id` int DEFAULT NULL,
  `var_state` enum('open','in_progress','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_bl_budget` (`company_id`,`budget_id`),
  KEY `fk_fin_bl_budget` (`budget_id`),
  KEY `fk_fin_bl_acc` (`account_id`),
  CONSTRAINT `fk_fin_bl_acc` FOREIGN KEY (`account_id`) REFERENCES `fin_chart_of_accounts` (`id`),
  CONSTRAINT `fk_fin_bl_budget` FOREIGN KEY (`budget_id`) REFERENCES `fin_budgets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_budgets ──
CREATE TABLE `fin_budgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `budget_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dept_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','general','sites','movement','transport','tickets','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_type` enum('annual','quarterly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fiscal_year` int NOT NULL,
  `period_no` int DEFAULT NULL COMMENT 'ربع 1-4 أو شهر 1-12',
  `total_revenue` decimal(16,2) NOT NULL DEFAULT '0.00',
  `total_expense` decimal(16,2) NOT NULL DEFAULT '0.00',
  `state` enum('draft','submitted','returned','approved','active','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'مسودة → مقدَّمة → (معادة بسبب | معتمدة) → نشطة → مقفلة',
  `submitted_by` int DEFAULT NULL COMMENT 'مديرُ الإدارة الذي رفعها',
  `submitted_at` datetime DEFAULT NULL COMMENT 'لحظةُ الرفع',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL COMMENT 'لحظةُ الإجازة',
  `returned_by` int DEFAULT NULL COMMENT 'من أعادها',
  `returned_at` datetime DEFAULT NULL,
  `return_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سببُ الإعادة — بارزٌ للإدارة (الدستور §4.3: «أُعيد إليك لاستكمال: السبب»)',
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_budget` (`company_id`,`dept_module`,`period_type`,`fiscal_year`,`period_no`),
  KEY `ix_fin_budget_state` (`company_id`,`state`),
  KEY `ix_fin_budget_deleted` (`is_deleted`),
  KEY `ix_fin_budget_dept_state` (`company_id`,`dept_module`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_cash_forecasts ──
CREATE TABLE `fin_cash_forecasts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `forecast_date` date NOT NULL,
  `horizon_type` enum('daily','weekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `opening_cash` decimal(16,2) NOT NULL DEFAULT '0.00',
  `expected_inflow` decimal(16,2) NOT NULL DEFAULT '0.00',
  `expected_outflow` decimal(16,2) NOT NULL DEFAULT '0.00',
  `expected_position` decimal(16,2) GENERATED ALWAYS AS (((`opening_cash` + `expected_inflow`) - `expected_outflow`)) STORED,
  `min_required` decimal(16,2) DEFAULT NULL,
  `funding_gap` decimal(16,2) DEFAULT NULL COMMENT 'إن نقص الوضع عن الحد الأدنى',
  `cash_priority` enum('critical','high','normal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` enum('receivables','payables','payroll','funding','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_cf_date` (`company_id`,`forecast_date`,`horizon_type`),
  KEY `ix_fin_cf_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_chart_of_accounts ──
CREATE TABLE `fin_chart_of_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'رقم الحساب',
  `name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` enum('asset','liability','equity','revenue','expense') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int DEFAULT NULL COMMENT 'مرجع ذاتي — أب الحساب',
  `is_postable` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'هل يقبل قيداً مباشراً',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_acc_code` (`company_id`,`code`),
  KEY `ix_fin_acc_type` (`company_id`,`account_type`),
  KEY `ix_fin_acc_parent` (`parent_id`),
  CONSTRAINT `fk_fin_coa_parent` FOREIGN KEY (`parent_id`) REFERENCES `fin_chart_of_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_closing_items ──
CREATE TABLE `fin_closing_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `period_id` int NOT NULL,
  `step` enum('reconcile_bank','reconcile_ar','reconcile_ap','post_accruals','post_depreciation','settle_supplier','payroll_posted','variance_reviewed','intercompany_settled','reports_issued') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT '1',
  `item_state` enum('pending','done','na') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `done_by` int DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_ci_period` (`company_id`,`period_id`),
  KEY `fk_fin_ci_period` (`period_id`),
  CONSTRAINT `fk_fin_ci_period` FOREIGN KEY (`period_id`) REFERENCES `fin_financial_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_collection_allocations ──
CREATE TABLE `fin_collection_allocations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `payment_id` int NOT NULL,
  `receivable_id` int DEFAULT NULL COMMENT 'ذمّةُ الفاتورة — NULL لغير الفاتورة (والمفتاحُ الأجنبيُّ يقبل NULL)',
  `target_kind` enum('advance','invoice','milestone','retention','final') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invoice' COMMENT 'هدفُ التخصيص — والفاتورةُ واحدٌ من خمسةٍ لا الوحيد',
  `target_ref` int NOT NULL DEFAULT '0' COMMENT 'معرّفُ الهدف: fin_receivables للفاتورة · contract_payment_schedule لغيرها',
  `amount` decimal(18,2) NOT NULL,
  `pay_currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'عملةُ السداد (settlement)',
  `target_currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'عملةُ الهدف (contract غالبًا)',
  `amount_target` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT '**المعادلُ الذي أُطفئت به الذمّة** بعملة الهدف',
  `fx_rate_pay` decimal(20,8) DEFAULT NULL,
  `fx_rate_target` decimal(20,8) DEFAULT NULL,
  `base_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'قيمةُ المقبوض بالعملة الوظيفية',
  `fx_diff_base` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT '**فرقُ الصرف المحقق** بالعملة الوظيفية — بسطره لا مبتلعًا في المبلغ',
  `basis` enum('explicit','oldest_first') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'oldest_first' COMMENT 'أساسُ التخصيص: مرجعٌ صريحٌ من العميل · أو **أقدمُ فاتورةٍ أولًا** (§4)',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alloc_target` (`payment_id`,`target_kind`,`target_ref`),
  UNIQUE KEY `uq_alloc` (`payment_id`,`receivable_id`),
  KEY `ix_alloc_recv` (`company_id`,`receivable_id`),
  KEY `fk_alloc_receivable` (`receivable_id`),
  CONSTRAINT `fk_alloc_payment` FOREIGN KEY (`payment_id`) REFERENCES `fin_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_receivable` FOREIGN KEY (`receivable_id`) REFERENCES `fin_receivables` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_alloc_amount` CHECK ((`amount` > 0)),
  CONSTRAINT `ck_alloc_fx` CHECK (((`amount_target` >= 0) and (`base_amount` >= 0))),
  CONSTRAINT `ck_alloc_target` CHECK (((`target_ref` > 0) and (((`target_kind` = _utf8mb4'invoice') and (`receivable_id` is not null) and (`target_ref` = `receivable_id`)) or ((`target_kind` <> _utf8mb4'invoice') and (`receivable_id` is null)))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_cost_centers ──
CREATE TABLE `fin_cost_centers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `center_type` enum('cost','profit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cost',
  `parent_id` int DEFAULT NULL COMMENT 'مرجع ذاتي (شجرة)',
  `owner_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','general') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_cc_code` (`company_id`,`code`),
  KEY `ix_fin_cc_parent` (`company_id`,`parent_id`),
  KEY `ix_fin_cc_deleted` (`is_deleted`),
  KEY `fk_fin_cc_parent` (`parent_id`),
  CONSTRAINT `fk_fin_cc_parent` FOREIGN KEY (`parent_id`) REFERENCES `fin_cost_centers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_cost_records ──
CREATE TABLE `fin_cost_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `cost_type` enum('equipment','project','hour','ton','meter','fuel','maintenance','workforce') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `equipment_id` int DEFAULT NULL COMMENT 'equipments.id (مرجع مرن)',
  `project_id` int DEFAULT NULL COMMENT 'project.id (مرجع مرن)',
  `period_ref` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(14,2) DEFAULT NULL,
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ساعة/طن/متر/لتر',
  `unit_cost` decimal(14,4) DEFAULT NULL,
  `total_cost` decimal(16,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG' COMMENT 'عملة العقد مصدر التكلفة كما وقعت — لا تحويل عند التسجيل',
  `revenue` decimal(16,2) DEFAULT NULL,
  `profit` decimal(16,2) GENERATED ALWAYS AS ((coalesce(`revenue`,0) - `total_cost`)) STORED,
  `event_id` int DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_cost_type` (`company_id`,`cost_type`),
  KEY `ix_fin_cost_project` (`project_id`),
  KEY `ix_fin_cost_equip` (`equipment_id`),
  KEY `ix_fin_cost_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_currencies ──
CREATE TABLE `fin_currencies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل المستأجر',
  `code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'رمز العملة ISO — USD · SDG',
  `name_ar` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الاسم كما يظهر للمستخدم',
  `symbol` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الرمز المختصر للعرض ($ · ج.س)',
  `decimals` tinyint NOT NULL DEFAULT '2' COMMENT 'خاناتُ الكسر عند العرض',
  `is_base` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'عملةُ الأساس — واحدةٌ لكل شركة، مشتقّةٌ من admin_companies.currency',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_currency_code` (`company_id`,`code`),
  KEY `ix_currency_base` (`company_id`,`is_base`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجلُّ العملات — عملةُ الأساس وما يُقاس بها (FES-01 §3.3)';

-- ── Table: fin_depreciation ──
CREATE TABLE `fin_depreciation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `asset_id` int NOT NULL,
  `period_ref` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM',
  `depreciation_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `run_date` date NOT NULL,
  `journal_entry_id` int DEFAULT NULL COMMENT 'fin_journal_entries (soft)',
  `event_id` int DEFAULT NULL COMMENT 'الحدثُ المالي المنشور (fin_financial_events) — «كلُّ حدثٍ يُقرأ بالاتجاهين»',
  `method` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'طريقةُ الإهلاك ساعةَ الاحتساب — من إعداد الأصل لا من اجتهاد',
  `basis_json` text COLLATE utf8mb4_unicode_ci COMMENT 'لقطةُ الأساس: التكلفةُ والخردةُ والعمرُ والمجمّعُ قبلَه — لا اشتقاقٌ لاحق',
  `source` enum('screen','cron','legacy') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'screen' COMMENT 'من أوقعه — والقديمُ يُصرَّح legacy لا يُدَّعى أنه من الخدمة',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_dep` (`company_id`,`asset_id`,`period_ref`),
  KEY `ix_fin_dep_asset` (`company_id`,`asset_id`),
  KEY `fk_fin_dep_asset` (`asset_id`),
  KEY `ix_fin_dep_event` (`event_id`),
  CONSTRAINT `fk_fin_dep_asset` FOREIGN KEY (`asset_id`) REFERENCES `fin_assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_dues ──
CREATE TABLE `fin_dues` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `party_type` enum('supplier','employee','proc_supplier') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'supplier=مورد الآليات (suppliers) · employee=عامل · proc_supplier=مورد المشتريات (proc_supplier) — سجلّان مختلفان لا يُخلطان',
  `party_ref` int NOT NULL COMMENT 'suppliers.id / employees.id (مرجع مرن)',
  `due_type` enum('hours','tons','meters','advance','discount','penalty','purchase','fuel','parts','catering','water','transport','salary','allowance','overtime','deduction','custody','settlement','end_of_service','guarantee_release','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('credit','debit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'credit' COMMENT 'credit=له، debit=عليه',
  `amount` decimal(16,2) NOT NULL,
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `fx_rate` decimal(20,8) DEFAULT NULL COMMENT 'سعرُ الصرف بتاريخ نشوء الذمّة',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'المعادلُ بعملة الأساس — عليه تُجمع تسويةُ الطرف متعددِ العملات',
  `period_ref` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_id` int DEFAULT NULL,
  `source_doc_type` enum('proc_issue','mnt_order','transfer_order','penalty_assessment','settlement','supplier_closure','employee_closure','legacy_no_ref','pending_source') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_doc_id` int unsigned DEFAULT NULL COMMENT 'معرّفُ المستند في جدوله — NULL مع legacy_no_ref وحدَها',
  `settlement_state` enum('pending','settled','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `pre_settlement_legacy` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'صفٌّ دُفِع قبل سريان قاعدة «لا دفعَ بلا تسوية» — مستثنًى صراحةً لا ملفَّقٌ له مستند',
  `settlement_id` int DEFAULT NULL COMMENT 'التسويةُ التي احتسبت هذا الصفَّ — فلا يُحتسب في تسويتين',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_dues_party` (`company_id`,`party_type`,`party_ref`),
  KEY `ix_fin_dues_settle` (`company_id`,`settlement_state`),
  KEY `ix_fin_dues_deleted` (`is_deleted`),
  KEY `fk_dues_currency` (`company_id`,`currency`),
  KEY `ix_dues_source_doc` (`company_id`,`source_doc_type`,`source_doc_id`),
  CONSTRAINT `fk_dues_currency` FOREIGN KEY (`company_id`, `currency`) REFERENCES `fin_currencies` (`company_id`, `code`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_dues_debit_source` CHECK (((`direction` <> _utf8mb4'debit') or (`source_doc_type` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_effect_map ──
CREATE TABLE `fin_effect_map` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `source_kind` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نوع المصدر: unit_record (وحدة معتمدة) — يتوسّع لاحقًا',
  `effect_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'يوافق fin_event_links.effect_type',
  `effect_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'التسمية المعروضة في شجرة الأثر',
  `target_table` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الجدول الذي يُكتب فيه الأثر',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 = معلن غير متاح/معطّل — يُسجَّل ولا يُلفَّق',
  `param_value` decimal(14,4) DEFAULT NULL COMMENT 'معامل الأثر إن لزم (مثال: مخصّص الصيانة للوحدة)',
  `unavailable_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سبب التعطيل — يظهر للمستخدم بدل الصمت',
  `display_order` smallint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_effect_map` (`company_id`,`source_kind`,`effect_type`),
  KEY `ix_effect_source` (`company_id`,`source_kind`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D05 §6.1: خريطة تفريع الأثر — قواعد المروحة بياناتٍ لا كودًا';

-- ── Table: fin_event_effects ──
CREATE TABLE `fin_event_effects` (
  `effect_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `event_id` int NOT NULL,
  `effect_type` enum('client_receivable','supplier_accrual','operator_due','project_cost','equip_cost','payment','receipt','settlement','depreciation','tax_return','finance_installment','adjustment_reversal') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FES §4.1: القيمُ الحصرية الاثنتا عشرة',
  `party_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'الطرف — فارغٌ = أثرٌ بلا طرفٍ (تكلفة) · جزءٌ من المفتاح الفريد فلا NULL',
  `party_id` int NOT NULL DEFAULT '0' COMMENT 'معرّفُ الطرف — 0 = بلا طرف · جزءٌ من المفتاح الفريد فلا NULL',
  `contract_line_id` int NOT NULL DEFAULT '0' COMMENT 'بندُ العقد — 0 = بلا بند · جزءٌ من المفتاح الفريد فلا NULL',
  `amount` decimal(18,2) NOT NULL,
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'المعادلُ الموحّد — NULL = سعرٌ غيرُ مُدخَل (معلَن)',
  `status` enum('active','reversed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'الأثرُ يُبطل بعكس حدثه لا بمحوه',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`effect_id`),
  UNIQUE KEY `uq_effect` (`event_id`,`effect_type`,`party_type`,`party_id`,`contract_line_id`),
  KEY `ix_eff_company_party` (`company_id`,`party_type`,`party_id`),
  KEY `ix_eff_type` (`company_id`,`effect_type`),
  CONSTRAINT `fk_eff_event` FOREIGN KEY (`event_id`) REFERENCES `fin_financial_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='H-12 (FES §3.2): آثارُ الحدث — الحدثُ الواحد قد يولّد آثارًا لعدة أطراف';

-- ── Table: fin_event_links ──
CREATE TABLE `fin_event_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `parent_kind` enum('request','unit_record','event','timesheet') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_ref` int unsigned NOT NULL,
  `effect_type` enum('revenue_event','supplier_due','employee_due','cost_record','receivable','journal_entry','payment','metric_update','budget_consumption','party_award') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_table` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int unsigned DEFAULT NULL,
  `event_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_link_parent_effect` (`company_id`,`parent_kind`,`parent_ref`,`effect_type`),
  KEY `ix_parent` (`company_id`,`parent_kind`,`parent_ref`),
  KEY `ix_target` (`company_id`,`target_table`,`target_id`),
  KEY `ix_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_financial_events ──
CREATE TABLE `fin_financial_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `event_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'يُسنِده الخادم',
  `event_type` enum('revenue','expense','payable','receivable','payroll','settlement','enterprise') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'قديم متوافق؛ أحداث العقد = enterprise والدلالة الكاملة في event_key/category — لا توسّع آخر (الحوكمة في سجل الأنواع)',
  `event_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Event Type المنقط domain.entity.action (عقد §9) — يحوكم بسجل الأنواع لا بتوسيع ENUM',
  `category` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Event Category (عقد §9): operational/financial/hr/fleet/maintenance/commercial/analytics',
  `source_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','movement','finance','transport','system','sites','tickets','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'فاتورة/أمر/مستخلص',
  `entity_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Entity (عقد §9 إلزامي): نوع الكيان الموضوع timesheet/mnt_order/… — يفرضه الناشر',
  `entity_id` bigint unsigned DEFAULT NULL COMMENT 'Entity ID (عقد §9 إلزامي): معرّف رقمي حصرًا — لا مفاتيح نصية (ADR-09)',
  `source_line_id` int NOT NULL DEFAULT '0' COMMENT 'H-12 (FES §3.1): سطرُ المستند المصدر — 0 لمستندٍ بلا سطور',
  `source_doc_version` int NOT NULL DEFAULT '1' COMMENT 'H-12: نسخةُ المستند المصدر — النسخةُ الأحدث تُنشئ حدثًا وتعلّم القديمَ Superseded',
  `amount` decimal(16,2) NOT NULL,
  `quantity` decimal(18,4) DEFAULT NULL COMMENT 'الكمية إن كان الحدث كميًا (عقد §9)',
  `unit` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'وحدة القياس اللاتينية hour/ton/meter/km/liter/unit (عقد §9)',
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `fx_rate` decimal(14,6) DEFAULT NULL COMMENT 'إلى الدولار',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'M-40 (FES §3.3): المعادلُ الموحّد = ROUND(amount × fx_rate, 2) — NULL = سعرٌ غيرُ مُدخَل لتاريخه (معلَن)',
  `equipment_id` int DEFAULT NULL COMMENT 'equipments.id (مرجع مرن)',
  `project_id` int DEFAULT NULL COMMENT 'project.id (مرجع مرن)',
  `contract_id` int DEFAULT NULL COMMENT 'contracts.id (مرجع مرن)',
  `contract_line_id` int DEFAULT NULL COMMENT 'بندُ البيع (P-02 · `client_contract_lines`) — **وُصل مرجعُه في P-09 بعد أن كان وعدًا فارغًا**',
  `supplier_entity_id` int DEFAULT NULL COMMENT 'suppliers.id (مرجع مرن)',
  `customer_entity_id` int DEFAULT NULL COMMENT 'clients.id (مرجع مرن)',
  `operator_employee_id` int DEFAULT NULL COMMENT 'Operator (عقد §9 سياقي): مرجع رقمي إلى employees.id — SSOT الأشخاص',
  `party_type` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'H-12 (FES §4.1): الطرفُ الموحّد — customer·supplier·operator·employee·owner_dept',
  `party_id` int DEFAULT NULL COMMENT 'H-12: معرّفُ الطرف في جدوله بحسب party_type',
  `cost_center` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accountant_id` int DEFAULT NULL COMMENT 'fin_accountants.id (مرجع مرن)',
  `state` enum('draft','dept_review','dept_approved','fin_review','audited','approved','posted','settled','rejected','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `event_status` enum('active','reversed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'محور دورة حياة الناقل (منفصلٌ عن state سير المالية): active افتراضًا · reversed إن نقضه حدثٌ معوِّض',
  `fes_status` enum('Draft','Published','ValidationFailed','UnderReview','ReturnedToSource','Rejected','Approved','PostingFailed','RetryPending','Posted','Reversed','Superseded','CancelledBeforePosting','Closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft' COMMENT 'H-12 (FES §7.2): آلةُ حالات الحدث الأربعَ عشرة — يحكمها EventStateMachine حصرًا',
  `reverses_event_id` int DEFAULT NULL COMMENT 'إن كان هذا الحدث معوِّضًا: id الحدث الذي ينقضه (عقد C6 — المنطق مؤجَّل)',
  `occurred_at` datetime DEFAULT NULL COMMENT 'Occurred At (عقد §9 إلزامي): لحظة الوقوع الفعلي UTC — تُميَّز عن created_at',
  `fiscal_period_id` int DEFAULT NULL COMMENT 'H-12: الفترةُ المالية للحدث — تُختم عند النشر، ولا نشرَ في فترةٍ مقفلة (إنفاذُه في M-39)',
  `due_date` date DEFAULT NULL COMMENT 'H-12 (FES §3.1): تاريخُ الاستحقاق — فهرسُ أعمار الذمم',
  `journal_entry_id` int DEFAULT NULL COMMENT 'soft ref (المرحلة 3)',
  `notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT (uuid()) COMMENT 'معرّف الحدث الكوني (توزيع/تتبّع) — default قاعدي UUID() يُضبط أدناه',
  `root_event_id` bigint unsigned DEFAULT NULL COMMENT 'ADR-15: الحقيقة الأم في ems_business_events — NULL = يدوي/سابق للجذر',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL COMMENT 'H-12 (FES §3.1): معتمِدُ الحدث — تدقيقُ الفاعلين',
  `approved_at` datetime DEFAULT NULL,
  `posted_by` int DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `correlation_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'معرّف سلسلة الأثر طرفًا لطرف (عقد §9) — يكتبه الناشر K3',
  `causation_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'H-12 (FES §3.1): معرّفُ الحدث المسبِّب — خيطُ السببية (بجانب correlation_id خيطِ الترابط)',
  `idempotency_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مفتاح عطالة الأثر — فريد لكل عملية مصدرية (عقد §9)؛ NULL للصفوف السابقة للعقد',
  `schema_version` smallint unsigned DEFAULT NULL COMMENT 'إصدار مخطط الحدث (عقد §9) — يكتبه الناشر K3',
  `event_version` int NOT NULL DEFAULT '1' COMMENT 'H-12 (FES §7.3): قفلٌ تفاؤلي — كلُّ انتقالٍ يفحصها ويرفعها، والمتزامنان: الأولُ يمضي والثاني Conflict',
  `payload` json DEFAULT NULL COMMENT 'Payload (عقد §9 إلزامي): الحمولة التفصيلية JSON — يفرضها الناشر',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_event_no` (`company_id`,`event_no`),
  UNIQUE KEY `uq_ffe_idempotency` (`idempotency_key`),
  UNIQUE KEY `uq_ffe_event_uuid` (`event_uuid`),
  KEY `ix_fin_event_state` (`company_id`,`state`),
  KEY `ix_fin_event_source` (`company_id`,`source_module`),
  KEY `ix_fin_event_type` (`company_id`,`event_type`),
  KEY `ix_fin_event_deleted` (`is_deleted`),
  KEY `idx_ffe_correlation` (`correlation_id`),
  KEY `idx_ffe_event_key` (`event_key`),
  KEY `idx_ffe_entity` (`entity_type`,`entity_id`),
  KEY `idx_ffe_reverses` (`reverses_event_id`),
  KEY `ix_ffe_root` (`root_event_id`),
  KEY `ix_ffe_fes_status` (`company_id`,`fes_status`),
  KEY `ix_ffe_party` (`party_type`,`party_id`),
  KEY `ix_ffe_due` (`company_id`,`due_date`),
  KEY `ix_ffe_causation` (`causation_id`),
  KEY `ix_ffe_source_line` (`company_id`,`entity_type`,`entity_id`,`source_line_id`,`source_doc_version`),
  KEY `fk_ffe_period` (`fiscal_period_id`),
  CONSTRAINT `fk_ffe_period` FOREIGN KEY (`fiscal_period_id`) REFERENCES `fin_financial_periods` (`id`),
  CONSTRAINT `fk_ffe_root` FOREIGN KEY (`root_event_id`) REFERENCES `ems_business_events` (`id`),
  CONSTRAINT `ck_ffe_fx_pair` CHECK ((((`fx_rate` is null) and (`base_amount` is null)) or ((`fx_rate` is not null) and (`base_amount` = round((`amount` * `fx_rate`),2)))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_financial_periods ──
CREATE TABLE `fin_financial_periods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `fiscal_year` int NOT NULL,
  `period_type` enum('year','month') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_no` int DEFAULT NULL COMMENT 'شهر 1-12 (NULL لصف السنة)',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `state` enum('planned','open','soft_closed','closed','locked','reopened') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `posting_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `soft_closed_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `reopen_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reopened_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_period` (`company_id`,`fiscal_year`,`period_type`,`period_no`),
  KEY `ix_fin_period_state` (`company_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_funding_facilities ──
CREATE TABLE `fin_funding_facilities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `facility_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `facility_type` enum('loan','murabaha','lease','bank_guarantee','letter_of_credit','operating_finance') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` enum('equipment','supplier','operational','general') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lender_entity_id` int DEFAULT NULL COMMENT 'suppliers/entities.id (مرجع مرن)',
  `lender_name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'لقطة نصية للممول',
  `principal` decimal(16,2) NOT NULL,
  `profit_rate` decimal(9,4) DEFAULT NULL COMMENT '% أو هامش',
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `state` enum('draft','approved','active','settled','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_fac_no` (`company_id`,`facility_no`),
  KEY `ix_fin_fac_type` (`company_id`,`facility_type`),
  KEY `ix_fin_fac_state` (`company_id`,`state`),
  KEY `ix_fin_fac_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_funding_schedules ──
CREATE TABLE `fin_funding_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `facility_id` int NOT NULL,
  `installment_no` int NOT NULL,
  `due_date` date NOT NULL,
  `principal_due` decimal(16,2) NOT NULL DEFAULT '0.00',
  `profit_due` decimal(16,2) NOT NULL DEFAULT '0.00',
  `total_due` decimal(16,2) GENERATED ALWAYS AS ((`principal_due` + `profit_due`)) STORED,
  `paid_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `state` enum('due','partial','paid','overdue') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'due',
  `event_id` int DEFAULT NULL COMMENT 'حدثُ استحقاق القسط — «أقساطٌ آليةٌ بمرجع الجدول لحظةَ استحقاقها»',
  `accrued_at` datetime DEFAULT NULL COMMENT 'لحظةُ الاعتراف بالاستحقاق — لا تُكتب إلا مع الحدث',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_funding_installment` (`company_id`,`facility_id`,`installment_no`),
  KEY `ix_fin_fs_fac` (`company_id`,`facility_id`),
  KEY `ix_fin_fs_due` (`company_id`,`due_date`),
  KEY `fk_fin_fs_fac` (`facility_id`),
  KEY `ix_funding_due` (`company_id`,`due_date`,`state`),
  CONSTRAINT `fk_fin_fs_fac` FOREIGN KEY (`facility_id`) REFERENCES `fin_funding_facilities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_fx_differences ──
CREATE TABLE `fin_fx_differences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `kind` enum('realized','unrealized') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_kind` enum('allocation','revaluation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_ref` int NOT NULL COMMENT 'سطرُ التخصيص أو الذمّةُ المُعاد تقييمُها',
  `party_ref` int DEFAULT NULL COMMENT 'العميلُ إن عُرف',
  `from_currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'العملةُ التي نشأ منها الفرق',
  `functional_currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '**العملةُ الوظيفية** — وفيها وحدَها يُقاس الفرق',
  `amount` decimal(18,2) NOT NULL COMMENT 'موجبٌ ربحُ صرفٍ · سالبٌ خسارتُه',
  `rate_from` decimal(20,8) DEFAULT NULL,
  `rate_to` decimal(20,8) DEFAULT NULL,
  `occurred_on` date NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_id` int DEFAULT NULL COMMENT 'وصلُ الدفتر — **مؤجَّلٌ إلى H-09**',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fxd_source` (`kind`,`source_kind`,`source_ref`),
  KEY `ix_fxd_lookup` (`company_id`,`kind`,`occurred_on`),
  CONSTRAINT `ck_fxd_amount` CHECK ((`amount` <> 0)),
  CONSTRAINT `ck_fxd_currency` CHECK (((`functional_currency` <> _utf8mb4'') and (`from_currency` <> _utf8mb4'')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PLAN-03 §3.8 — فروقُ الصرف: المحقَّقُ وغيرُ المحقَّق، ولكلٍّ بابُه';

-- ── Table: fin_fx_rates ──
CREATE TABLE `fin_fx_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل المستأجر',
  `currency_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'العملة المُسعَّرة',
  `rate_to_base` decimal(20,8) NOT NULL COMMENT 'كم وحدةَ أساسٍ يساوي واحدٌ منها — base = ROUND(amount × rate, 2)',
  `effective_from` date NOT NULL COMMENT 'أولُ يومٍ يسري فيه — والسعرُ النافذ آخرُ سعرٍ سابقٍ للتاريخ أو مساوٍ',
  `source` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مصدرُ السعر: system · بنك مركزي · قرارٌ إداري',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fx_currency_date` (`company_id`,`currency_code`,`effective_from`) COMMENT 'سعرٌ واحدٌ لكل (عملة × تاريخ سريان) — التصحيحُ تعديلُ الصفّ لا صفٌّ ثانٍ',
  KEY `ix_fx_lookup` (`company_id`,`currency_code`,`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أسعارُ الصرف بتواريخها — السعرُ لحظةَ الحدث (FES-01 §3.1)';

-- ── Table: fin_internal_allocations ──
CREATE TABLE `fin_internal_allocations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `alloc_type` enum('internal_allocation','intercompany_settlement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_center_id` int DEFAULT NULL COMMENT 'fin_cost_centers',
  `to_center_id` int DEFAULT NULL COMMENT 'fin_cost_centers',
  `from_entity_id` int DEFAULT NULL COMMENT 'entities (مرجع مرن)',
  `to_entity_id` int DEFAULT NULL COMMENT 'entities (مرجع مرن)',
  `basis` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ساعات/استخدام/عدد',
  `amount` decimal(16,2) NOT NULL,
  `period_id` int DEFAULT NULL COMMENT 'fin_financial_periods (soft)',
  `event_id` int DEFAULT NULL,
  `state` enum('draft','approved','posted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_ia_type` (`company_id`,`alloc_type`),
  KEY `ix_fin_ia_deleted` (`is_deleted`),
  KEY `fk_fin_ia_from` (`from_center_id`),
  KEY `fk_fin_ia_to` (`to_center_id`),
  CONSTRAINT `fk_fin_ia_from` FOREIGN KEY (`from_center_id`) REFERENCES `fin_cost_centers` (`id`),
  CONSTRAINT `fk_fin_ia_to` FOREIGN KEY (`to_center_id`) REFERENCES `fin_cost_centers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_journal_entries ──
CREATE TABLE `fin_journal_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `entry_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'يُسنِده الخادم',
  `event_id` int DEFAULT NULL COMMENT 'fin_financial_events.id (مرجع مرن)',
  `posting_date` date NOT NULL,
  `txn_date` date NOT NULL DEFAULT (curdate()) COMMENT 'M-38: تاريخُ الحركة الفعلي (بجانب posting_date تاريخِ الترحيل)',
  `request_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'M-38: خيطُ الطلب — رقمُ الطلب المالي المولِّد إن وُجد',
  `request_owner` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'M-38: صاحبُ الطلب (اسمُ الرافع لحظةَ التوليد — لقطة)',
  `request_group` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'M-38: مجموعةُ الطلب (request_type)',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG' COMMENT 'M-38: عملةُ القيد (افتراضُ SDG يطابق نمطَ fin_financial_events)',
  `fx_rate` decimal(18,6) DEFAULT NULL COMMENT 'M-38: سعرُ الصرف إلى عملة الأساس يومَ الحركة — NULL = سعرٌ غيرُ مُدخَل (فجوةٌ معلَنة)',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'M-38: المعادلُ الموحّد بعملة الأساس = ROUND(total_debit × fx_rate, 2)',
  `total_debit` decimal(16,2) NOT NULL DEFAULT '0.00',
  `total_credit` decimal(16,2) NOT NULL DEFAULT '0.00',
  `memo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('draft','posted','reversed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `posted_by` int DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_entry_no` (`company_id`,`entry_no`),
  KEY `ix_fin_entry_state` (`company_id`,`state`),
  KEY `ix_fin_entry_event` (`event_id`),
  KEY `ix_fin_entry_deleted` (`is_deleted`),
  KEY `ix_je_txn_date` (`company_id`,`txn_date`),
  KEY `ix_je_request_no` (`company_id`,`request_no`),
  CONSTRAINT `ck_je_balanced` CHECK ((round(`total_debit`,2) = round(`total_credit`,2))),
  CONSTRAINT `ck_je_fx_pair` CHECK ((((`fx_rate` is null) and (`base_amount` is null)) or ((`fx_rate` is not null) and (`base_amount` = round((`total_debit` * `fx_rate`),2)))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_journal_lines ──
CREATE TABLE `fin_journal_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `entry_id` int NOT NULL,
  `account_id` int NOT NULL COMMENT 'fin_chart_of_accounts.id',
  `debit` decimal(16,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(16,2) NOT NULL DEFAULT '0.00',
  `project_id` int DEFAULT NULL COMMENT 'project.id (بُعد، مرجع مرن)',
  `equipment_id` int DEFAULT NULL COMMENT 'equipments.id (بُعد، مرجع مرن)',
  `cost_center_id` int DEFAULT NULL COMMENT 'M-38: مركزُ التكلفة من الدليل — بديلُ النص الحر cost_center',
  `cost_center` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `memo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_jl_entry` (`company_id`,`entry_id`),
  KEY `ix_fin_jl_account` (`company_id`,`account_id`),
  KEY `fk_fin_jl_entry` (`entry_id`),
  KEY `fk_fin_jl_acc` (`account_id`),
  KEY `ix_jl_cost_center` (`company_id`,`cost_center_id`),
  KEY `fk_fin_jl_cc` (`cost_center_id`),
  CONSTRAINT `fk_fin_jl_acc` FOREIGN KEY (`account_id`) REFERENCES `fin_chart_of_accounts` (`id`),
  CONSTRAINT `fk_fin_jl_cc` FOREIGN KEY (`cost_center_id`) REFERENCES `fin_cost_centers` (`id`),
  CONSTRAINT `fk_fin_jl_entry` FOREIGN KEY (`entry_id`) REFERENCES `fin_journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_maint_provision_rules ──
CREATE TABLE `fin_maint_provision_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `equipment_id` int unsigned DEFAULT NULL COMMENT 'معدةٌ بعينها — NULL = القاعدةُ لنوعها أو الأعمّ',
  `equipment_type` int unsigned DEFAULT NULL COMMENT 'نوعُ المعدة — NULL مع NULL أعلاه = الأعمّ',
  `basis` enum('hour','unit') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '«أساسُ المخصص (ساعة/وحدة)» — نصُّ #23',
  `rate` decimal(14,4) NOT NULL COMMENT 'معدلُ المخصص لوحدة الأساس',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG' COMMENT 'لا جمعَ عملتين في رقم',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `state` enum('active','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mprov_rule` (`company_id`,`equipment_id`,`equipment_type`,`basis`,`effective_from`),
  KEY `ix_mprov_rule_lookup` (`company_id`,`state`,`effective_from`,`effective_to`),
  CONSTRAINT `ck_mprov_rate` CHECK ((`rate` > 0)),
  CONSTRAINT `ck_mprov_span` CHECK (((`effective_to` is null) or (`effective_to` >= `effective_from`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SPEC-01 #23 — قاعدةُ مخصص الصيانة: الأساسُ والمعدلُ والسريان';

-- ── Table: fin_maint_provisions ──
CREATE TABLE `fin_maint_provisions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `equipment_id` int unsigned NOT NULL,
  `period_ref` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM',
  `rule_id` int unsigned DEFAULT NULL COMMENT 'القاعدةُ التي احتُسب بها — «لا كتابةَ يدويةً»',
  `basis` enum('hour','unit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'من **وحدات المعدة المعتمدة** في الفترة',
  `rate` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT '**محسوبٌ لا مُدخَل**: الكميةُ × المعدل',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `event_id` int DEFAULT NULL,
  `basis_json` text COLLATE utf8mb4_unicode_ci,
  `source` enum('screen','cron') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'screen',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_maint_provision` (`company_id`,`equipment_id`,`period_ref`) COMMENT '«بمفتاح (المعدة × الفترة)» بنيويًّا',
  KEY `ix_mprov_period` (`company_id`,`period_ref`),
  KEY `ix_mprov_event` (`event_id`),
  CONSTRAINT `ck_mprov_amount` CHECK ((`amount` >= 0)),
  CONSTRAINT `ck_mprov_rule_src` CHECK (((`amount` = 0) or (`rule_id` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_notifications ──
CREATE TABLE `fin_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `target_level` enum('dept_accountant','dept_manager','finance_reviewer','finance_manager','treasurer','reader','all') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all' COMMENT 'المستوى المستهدف (فصل الواجبات)',
  `target_user_id` int DEFAULT NULL COMMENT 'مستخدم بعينه (اختياري)',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'شاشة الفتح',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_ntf_target` (`company_id`,`target_level`,`is_read`),
  KEY `ix_fin_ntf_created` (`company_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_operator_pay ──
CREATE TABLE `fin_operator_pay` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `employee_id` int unsigned NOT NULL,
  `pay_mode` enum('salary','due') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'salary',
  `note` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_op_pay` (`company_id`,`employee_id`),
  KEY `ix_mode` (`company_id`,`pay_mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_payments ──
CREATE TABLE `fin_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `payment_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('disbursement','collection') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_type` enum('supplier','customer','employee','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_ref` int DEFAULT NULL,
  `method` enum('cash','bank','transfer','cheque') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bank',
  `bank_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'المرجعُ البنكيُّ أو السند — إلزاميٌّ للتحصيل (ENT-03 §4)',
  `received_on` date DEFAULT NULL COMMENT 'تاريخُ القبض — جزءُ مفتاح منع الازدواج',
  `amount` decimal(16,2) NOT NULL,
  `allocated_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'Σ التخصيصات — يُحرَس بـCHECK فلا يتجاوز مبلغ السند',
  `unallocated_amount` decimal(18,2) GENERATED ALWAYS AS ((`amount` - `allocated_amount`)) STORED COMMENT '**رصيدٌ ظاهر** — لا رقمٌ في رسالةٍ تختفي',
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `fx_rate` decimal(18,6) DEFAULT NULL COMMENT 'M-40: سعرُ الصرف النافذ يومَ الدفع',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'M-40: المعادلُ الموحّد للدفعة',
  `event_id` int DEFAULT NULL,
  `due_id` int DEFAULT NULL,
  `receivable_id` int DEFAULT NULL,
  `memo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `state` enum('draft','approved','executed','reconciled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `executed_by` int DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_pay_no` (`company_id`,`payment_no`),
  UNIQUE KEY `uq_collection_ref` (`company_id`,`bank_ref`,`amount`,`received_on`),
  KEY `ix_fin_pay_dir` (`company_id`,`direction`),
  KEY `ix_fin_pay_state` (`company_id`,`state`),
  KEY `ix_fin_pay_deleted` (`is_deleted`),
  CONSTRAINT `ck_collection_bank_ref` CHECK (((`direction` <> _utf8mb4'collection') or ((`bank_ref` is not null) and (`bank_ref` <> _utf8mb4'')))),
  CONSTRAINT `ck_fp_allocated` CHECK (((`allocated_amount` >= 0) and (`allocated_amount` <= `amount`))),
  CONSTRAINT `ck_pay_fx_pair` CHECK ((((`fx_rate` is null) and (`base_amount` is null)) or ((`fx_rate` is not null) and (`base_amount` = round((`amount` * `fx_rate`),2)))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_receivables ──
CREATE TABLE `fin_receivables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `customer_entity_id` int NOT NULL COMMENT 'clients.id (مرجع مرن)',
  `doc_type` enum('invoice','statement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `amount` decimal(16,2) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'عملةُ الذمّة — كانت مجهولةً قبل P-08',
  `fx_rate_recognized` decimal(20,8) DEFAULT NULL COMMENT 'سعرُ الصرف يومَ الاعتراف — **مجمَّدٌ** فلا يتغيّر الماضي بتغيّر السعر',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'القيمةُ بالعملة الوظيفية يومَ الاعتراف',
  `collected` decimal(16,2) NOT NULL DEFAULT '0.00',
  `outstanding` decimal(16,2) GENERATED ALWAYS AS ((`amount` - `collected`)) STORED,
  `due_date` date DEFAULT NULL,
  `event_id` int DEFAULT NULL,
  `state` enum('open','partial','collected','overdue') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_recv_customer` (`company_id`,`customer_entity_id`),
  KEY `ix_fin_recv_state` (`company_id`,`state`),
  KEY `ix_fin_recv_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_request_documents ──
CREATE TABLE `fin_request_documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `request_id` int unsigned NOT NULL,
  `doc_type` enum('quote','invoice','statement','contract','receipt','delivery_note','photo','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_ref` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_kind` enum('original','copy','electronic') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'electronic',
  `retention_years` tinyint NOT NULL DEFAULT '10',
  `confidentiality` enum('normal','restricted','confidential') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `note` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` int unsigned DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_req` (`company_id`,`request_id`),
  KEY `fk_frd_req` (`request_id`),
  CONSTRAINT `fk_frd_req` FOREIGN KEY (`request_id`) REFERENCES `fin_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_request_events ──
CREATE TABLE `fin_request_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `request_id` int unsigned NOT NULL,
  `event_type` enum('create','attach','submit','dept_review','dept_approve','acct_review','verify','fin_approve','reject','return','resubmit','post','pay','collect','settle','close','archive','withdraw','cancel','suspend','resume','expire','merge','duplicate_check','escalate','exception','publish','edit','note','system','exception_requested','exception_denied','exception_overdue') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_user_id` int unsigned DEFAULT NULL,
  `on_behalf_of` int unsigned DEFAULT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `old_value` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_req` (`company_id`,`request_id`,`created_at`),
  KEY `ix_type` (`company_id`,`event_type`),
  KEY `fk_fre_req` (`request_id`),
  CONSTRAINT `fk_fre_req` FOREIGN KEY (`request_id`) REFERENCES `fin_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_request_lines ──
CREATE TABLE `fin_request_lines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `request_id` int unsigned NOT NULL,
  `item` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT '1.00',
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(16,2) GENERATED ALWAYS AS ((`qty` * `unit_price`)) STORED,
  `note` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_req` (`company_id`,`request_id`),
  KEY `fk_frl_req` (`request_id`),
  CONSTRAINT `fk_frl_req` FOREIGN KEY (`request_id`) REFERENCES `fin_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_request_routing ──
CREATE TABLE `fin_request_routing` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `source_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','general','sites','movement','transport','tickets','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_roles` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reviewer_role_id` int NOT NULL,
  `manager_role_id` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_frr` (`company_id`,`source_module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_requests ──
CREATE TABLE `fin_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `request_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_type` enum('purchase','disbursement','advance','supplier_payment','employee_payment','transfer','settlement','refund','discount','collection','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','general','sites','movement','transport','tickets','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_id` int unsigned DEFAULT NULL,
  `beneficiary_type` enum('supplier','employee','customer','internal','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `beneficiary_ref` int unsigned DEFAULT NULL,
  `beneficiary_name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(16,2) NOT NULL,
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `payment_method` enum('cash','bank','transfer','cheque') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `justification` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settlement_id` int DEFAULT NULL COMMENT 'التسويةُ المعتمدة التي وُلِّد عنها هذا الطلب — إلزاميٌّ لدفعات الأطراف (UX-02 §15.4)',
  `project_id` int unsigned DEFAULT NULL,
  `equipment_id` int unsigned DEFAULT NULL,
  `contract_id` int unsigned DEFAULT NULL,
  `cost_center` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` int unsigned DEFAULT NULL,
  `needed_by` date DEFAULT NULL,
  `priority` enum('normal','high','critical') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `need_class` enum('planned','unplanned','urgent','emergency') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `budget_line_id` int unsigned DEFAULT NULL,
  `sla_due_at` datetime DEFAULT NULL,
  `escalation_level` tinyint NOT NULL DEFAULT '0',
  `is_exception` tinyint(1) NOT NULL DEFAULT '0',
  `exception_type` enum('urgent_bypass','emergency_execute') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exception_approved_by` int unsigned DEFAULT NULL,
  `merged_into_id` int unsigned DEFAULT NULL,
  `parent_request_id` int unsigned DEFAULT NULL COMMENT 'المسار المركب §6.2: الطلب الأصل الذي تفرّع عنه هذا الطلب — NULL = أصلٌ أو مستقل',
  `duplicate_flag` tinyint(1) NOT NULL DEFAULT '0',
  `rejection_class` enum('incomplete_docs','no_budget','policy_violation','duplicate','not_justified','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('draft','under_review','pending_approval','approved','rejected','returned','posted','paid','collected','closed','archived','withdrawn','cancelled','suspended','expired','merged') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `event_id` int unsigned DEFAULT NULL,
  `decided_by` int unsigned DEFAULT NULL,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_req_no` (`company_id`,`request_no`),
  UNIQUE KEY `uq_sync` (`company_id`,`sync_uuid`),
  KEY `ix_state` (`company_id`,`state`),
  KEY `ix_type` (`company_id`,`request_type`),
  KEY `ix_module` (`company_id`,`source_module`),
  KEY `ix_event` (`event_id`),
  KEY `ix_req_parent` (`parent_request_id`),
  KEY `ix_req_settlement` (`settlement_id`),
  CONSTRAINT `fk_req_parent` FOREIGN KEY (`parent_request_id`) REFERENCES `fin_requests` (`id`),
  CONSTRAINT `fk_req_settlement` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_party_payment_needs_settlement` CHECK (((`request_type` not in (_utf8mb4'supplier_payment',_utf8mb4'employee_payment',_utf8mb4'settlement')) or (`settlement_id` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_tax_codes ──
CREATE TABLE `fin_tax_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'النسبة %',
  `tax_type` enum('output','input','both') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `account_id` int DEFAULT NULL COMMENT 'fin_chart_of_accounts (حساب الضريبة، soft)',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_tax_code` (`company_id`,`code`),
  KEY `ix_fin_tax_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_tax_returns ──
CREATE TABLE `fin_tax_returns` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `period_ref` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM',
  `taxable_sales` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'المبيعاتُ الخاضعة (وعاءُ المخرجات)',
  `output_tax` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'ضريبةُ المخرجات',
  `taxable_purchases` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'المشتريات (وعاءُ المدخلات)',
  `input_tax` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'ضريبةُ المدخلات',
  `net_tax` decimal(18,2) GENERATED ALWAYS AS (round((`output_tax` - `input_tax`),2)) STORED COMMENT '«الصافي» — **عمودٌ مولَّدٌ لا يُكتب** فلا ينحرف عن طرفيه',
  `lines_count` int NOT NULL DEFAULT '0' COMMENT 'عددُ الحركات المشتقّ منها — الصفرُ يُعلَن ولا يُخفى',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `state` enum('draft','filed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `event_id` int DEFAULT NULL,
  `basis_json` text COLLATE utf8mb4_unicode_ci,
  `filed_at` datetime DEFAULT NULL,
  `filed_by` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_return` (`company_id`,`period_ref`) COMMENT '«بمفتاح الفترة»',
  KEY `ix_tax_return_state` (`company_id`,`state`),
  CONSTRAINT `ck_taxret_filed` CHECK (((`state` <> _utf8mb4'filed') or (`filed_at` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SPEC-01 #22 — الإقرارُ الضريبيُّ الدوريُّ بمفتاح الفترة';

-- ── Table: fin_tax_transactions ──
CREATE TABLE `fin_tax_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `tax_code_id` int DEFAULT NULL,
  `direction` enum('output','input') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'output=ضريبة مبيعات، input=ضريبة مشتريات',
  `base_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'لقطة النسبة وقت الإدخال',
  `tax_amount` decimal(16,2) GENERATED ALWAYS AS (round(((`base_amount` * `tax_rate`) / 100),2)) STORED,
  `source_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_id` int DEFAULT NULL,
  `period_ref` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM',
  `state` enum('draft','filed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fin_taxtr_period` (`company_id`,`period_ref`),
  KEY `ix_fin_taxtr_dir` (`company_id`,`direction`),
  KEY `ix_fin_taxtr_deleted` (`is_deleted`),
  KEY `fk_fin_taxtr_code` (`tax_code_id`),
  CONSTRAINT `fk_fin_taxtr_code` FOREIGN KEY (`tax_code_id`) REFERENCES `fin_tax_codes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_unit_records ──
CREATE TABLE `fin_unit_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `record_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'يُسنِده الخادم',
  `record_date` date NOT NULL,
  `project_id` int NOT NULL COMMENT 'project.id (مرجع مرن)',
  `equipment_id` int DEFAULT NULL COMMENT 'equipments.id (مرجع مرن)',
  `supplier_entity_id` int DEFAULT NULL COMMENT 'suppliers.id — شريك الإنتاج (مرجع مرن)',
  `work_model` enum('hour','ton','meter') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ops_qty` decimal(14,2) NOT NULL COMMENT 'كمية كشوف التشغيل',
  `client_qty` decimal(14,2) DEFAULT NULL COMMENT 'كمية مصادقة العميل',
  `supplier_qty` decimal(14,2) DEFAULT NULL COMMENT 'كمية حساب المورد',
  `approved_qty` decimal(14,2) DEFAULT NULL COMMENT 'المعتمدة بعد التطابق',
  `client_unit_price` decimal(14,2) DEFAULT NULL COMMENT 'سعر وحدة عقد العميل (لقطة)',
  `supplier_unit_price` decimal(14,2) DEFAULT NULL COMMENT 'سعر وحدة عقد المورد (لقطة)',
  `unit_margin` decimal(16,2) GENERATED ALWAYS AS (round(((coalesce(`client_unit_price`,0) - coalesce(`supplier_unit_price`,0)) * coalesce(`approved_qty`,0)),2)) STORED COMMENT 'هامش الوحدة = (سعر العميل − سعر المورد) × المعتمد',
  `match_state` enum('pending','matched','variance','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `variance_note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'توثيق الفرق قبل الفوترة',
  `downtime_hours` decimal(8,2) DEFAULT NULL,
  `downtime_cause` enum('breakdown','standby','operator_shortage','mobilization','client') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'إلزامي إن وُجد توقف (قاعدة السبب)',
  `source_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'كشف تشغيل / تذكرة وزن / محضر قياس',
  `revenue_event_id` int DEFAULT NULL COMMENT 'fin_financial_events (التوأم الأول، soft)',
  `supplier_due_id` int DEFAULT NULL COMMENT 'fin_dues (التوأم الثاني، soft)',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_ur_no` (`company_id`,`record_no`),
  KEY `ix_fin_ur_date` (`company_id`,`record_date`),
  KEY `ix_fin_ur_project` (`project_id`),
  KEY `ix_fin_ur_match` (`company_id`,`match_state`),
  KEY `ix_fin_ur_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_units ──
CREATE TABLE `fin_units` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'gl / ar / ap / revenue / treasury ...',
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `head_position_id` int DEFAULT NULL COMMENT 'job_titles/roles.id (مرجع مرن)',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_unit_code` (`company_id`,`code`),
  KEY `ix_fin_unit_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: financed_assets ──
CREATE TABLE `financed_assets` (
  `fa_id` int unsigned NOT NULL AUTO_INCREMENT,
  `op_id` int unsigned NOT NULL,
  `asset_id` int NOT NULL COMMENT 'fin_assets.id أو equipments.id بحسب التقاطع',
  `asset_kind` enum('fin_asset','equipment') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'equipment',
  `purchase_value` decimal(18,2) DEFAULT NULL,
  `in_fleet` tinyint(1) NOT NULL DEFAULT '0',
  `in_asset_register` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`fa_id`),
  UNIQUE KEY `uq_fa` (`op_id`,`asset_kind`,`asset_id`),
  CONSTRAINT `fk_fa_op` FOREIGN KEY (`op_id`) REFERENCES `financing_operations` (`op_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-01 §4-②: أعيان العملية — فحص تقاطع الأسطول وسجل الأصول';

-- ── Table: financing_deviations ──
CREATE TABLE `financing_deviations` (
  `dev_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `dev_type` enum('no_ledger','payment_gap','unrecorded_exit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` enum('low','normal','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `required_doc` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `decision` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'القرار المتخذ — ولا يُغلق صف بلا قرار ومستند (الخدمة)',
  `decision_doc_ref` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`dev_id`),
  UNIQUE KEY `uq_fd_subject` (`company_id`,`dev_type`,`subject_ref`),
  KEY `ix_fd_state` (`company_id`,`state`,`priority`),
  CONSTRAINT `ck_fd_close_needs_decision` CHECK (((`state` <> _utf8mb4'closed') or ((`decision` is not null) and (`decision_doc_ref` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-01 §7: أوراق الانحراف الثلاث — Insert-only للرصد والقرار يُضاف (CHECK بنيوي)';

-- ── Table: financing_installments ──
CREATE TABLE `financing_installments` (
  `inst_id` int unsigned NOT NULL AUTO_INCREMENT,
  `op_id` int unsigned NOT NULL,
  `seq_no` int unsigned NOT NULL,
  `due_date` date NOT NULL,
  `amount_principal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `amount_profit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `amount_total` decimal(18,2) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fx_rate_at_payment` decimal(16,8) DEFAULT NULL COMMENT 'سعر يوم السداد — فرق محقق بسطره (PLAN-03 §7.2)',
  `functional_equivalent` decimal(18,2) DEFAULT NULL,
  `paid_date` date DEFAULT NULL,
  `payment_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('scheduled','due','paid','overdue','rescheduled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`inst_id`),
  UNIQUE KEY `uq_fi_seq` (`op_id`,`seq_no`) COMMENT 'يمنع تكرار القسط — وحدث الاستحقاق بمفتاح (العملية×القسط)',
  KEY `ix_fi_due` (`due_date`,`state`),
  CONSTRAINT `fk_fi_op` FOREIGN KEY (`op_id`) REFERENCES `financing_operations` (`op_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-01 §6: الأقساط تولَّد من العملية ولا تُدخل يدويًّا';

-- ── Table: financing_models ──
CREATE TABLE `financing_models` (
  `model_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `legal_owner_effect` enum('transfers','stays','shared','none') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '① المالك القانوني',
  `economic_beneficiary` enum('us','financier','shared') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '② المنتفع الاقتصادي',
  `accounting_recognition` enum('owned_asset','right_of_use','liability_only') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '③ الاعتراف — لا يُستنتج من الاسم',
  `depreciation_bearer` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '④ حامل الإهلاك',
  `security_interest_holder` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '⑤ مرتهن الضمان',
  `policy_doc_ref` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'سياسة محاسبية مكتوبة معتمدة — إلزامية قبل الاستعمال',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`model_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-01 §2: قاموس نماذج التمويل بمحاوره الخمسة — يُضاف إليه بقرار لا بكود';

-- ── Table: financing_operations ──
CREATE TABLE `financing_operations` (
  `op_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `op_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `financier_entity_id` int unsigned NOT NULL COMMENT 'كيان بصفة ممول (LEG-01) — لا سجل موازيًا',
  `model_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_date` date DEFAULT NULL,
  `capital` decimal(18,2) NOT NULL DEFAULT '0.00',
  `capital_source` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_value` decimal(18,2) DEFAULT NULL COMMENT 'قيمة شراء العين — أشد الحقول سرية',
  `down_payment` decimal(18,2) NOT NULL DEFAULT '0.00',
  `fees_admin` decimal(18,2) NOT NULL DEFAULT '0.00',
  `fees_insurance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `extra_costs` decimal(18,2) NOT NULL DEFAULT '0.00',
  `profit_rate` decimal(8,4) DEFAULT NULL,
  `profit_amount` decimal(18,2) DEFAULT NULL,
  `apr` decimal(8,4) DEFAULT NULL,
  `installments_no` int unsigned NOT NULL DEFAULT '0',
  `installment_amount` decimal(18,2) DEFAULT NULL,
  `outstanding_balance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `maturity_date` date DEFAULT NULL,
  `state` enum('draft','negotiation','approved','signed','active','paying','settled','closed','defaulted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`op_id`),
  UNIQUE KEY `uq_fo_code` (`company_id`,`op_code`),
  KEY `ix_fo_financier` (`financier_entity_id`,`state`),
  KEY `fk_fo_model` (`model_code`),
  CONSTRAINT `fk_fo_financier` FOREIGN KEY (`financier_entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fo_model` FOREIGN KEY (`model_code`) REFERENCES `financing_models` (`model_code`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-01 §4: عمليات التمويل بدورة حياتها — ولا عملية بلا نموذج ومعالجة';

-- ── Table: fleet_depreciation_profile ──
CREATE TABLE `fleet_depreciation_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `asset_category` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `brand` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `model_id` int DEFAULT NULL,
  `method` enum('uop','sl') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'uop',
  `useful_life` decimal(12,2) NOT NULL,
  `salvage_pct` decimal(5,4) NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `state` enum('draft','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fdp_company_code` (`company_id`,`code`),
  KEY `idx_fdp_company` (`company_id`),
  KEY `idx_fdp_model` (`model_id`),
  KEY `idx_fdp_state` (`state`,`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_depreciation_profile_audit ──
CREATE TABLE `fleet_depreciation_profile_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `company_id` int DEFAULT NULL,
  `action` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `changed_by` int DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `old_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `new_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fdpa_profile` (`profile_id`),
  KEY `idx_fdpa_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_equipment_compliance ──
CREATE TABLE `fleet_equipment_compliance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `equipment_id` int NOT NULL,
  `doc_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_critical` tinyint(1) NOT NULL DEFAULT '0',
  `attachment_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fec_equipment` (`equipment_id`),
  KEY `idx_fec_company` (`company_id`),
  KEY `idx_fec_expiry` (`expiry_date`),
  CONSTRAINT `fk_fec_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_equipment_component ──
CREATE TABLE `fleet_equipment_component` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `equipment_id` int NOT NULL,
  `component_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `serial_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `install_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '1',
  `replace_date` date DEFAULT NULL,
  `component_hours` decimal(12,2) DEFAULT NULL,
  `replace_count` int DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fecmp_equipment` (`equipment_id`),
  KEY `idx_fecmp_company` (`company_id`),
  CONSTRAINT `fk_fecmp_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_equipment_history ──
CREATE TABLE `fleet_equipment_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `equipment_id` int NOT NULL,
  `event_date` datetime NOT NULL,
  `event_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reference_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `site_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `in_out_date` date DEFAULT NULL,
  `work_hours` decimal(12,2) DEFAULT NULL,
  `down_hours` decimal(12,2) DEFAULT NULL,
  `maintenance_cost` decimal(12,2) DEFAULT NULL,
  `transfer_cost` decimal(12,2) DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `from_value` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `to_value` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `operation_id` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_feh_equipment` (`equipment_id`),
  KEY `idx_feh_company` (`company_id`),
  KEY `idx_feh_date` (`event_date`),
  KEY `idx_feh_equipment_date` (`equipment_id`,`event_date`),
  CONSTRAINT `fk_feh_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_equipment_protection ──
CREATE TABLE `fleet_equipment_protection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `equipment_id` int NOT NULL,
  `protection_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `cost` decimal(12,2) DEFAULT NULL,
  `state` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `renewal_date` date DEFAULT NULL,
  `partner_id` int DEFAULT NULL,
  `partner_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `compliance_id` int DEFAULT NULL,
  `attachment_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fep_equipment` (`equipment_id`),
  KEY `idx_fep_company` (`company_id`),
  KEY `idx_fep_compliance` (`compliance_id`),
  CONSTRAINT `fk_fep_compliance` FOREIGN KEY (`compliance_id`) REFERENCES `fleet_equipment_compliance` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_fep_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_model ──
CREATE TABLE `fleet_model` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `manufacturer` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `model_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `equipment_type_id` int DEFAULT NULL,
  `operating_category` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fuel_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `std_capacity` decimal(14,2) DEFAULT NULL,
  `std_capacity_uom` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tech_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `default_supplier_id` int DEFAULT NULL,
  `default_supplier_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `depreciation_profile_id` int DEFAULT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fleet_model_company` (`company_id`),
  KEY `idx_fleet_model_type` (`equipment_type_id`),
  KEY `idx_fleet_model_supplier` (`default_supplier_id`),
  KEY `idx_fleet_model_status` (`status`,`is_deleted`),
  KEY `idx_fleet_model_company_code` (`company_id`,`code`),
  KEY `idx_fleet_model_dep_profile` (`depreciation_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_model_service_spec ──
CREATE TABLE `fleet_model_service_spec` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `company_id` int DEFAULT NULL,
  `item_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `recommended_ref` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `qty` decimal(12,2) DEFAULT NULL,
  `uom` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alt_ref` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `photo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fmss_model` (`model_id`),
  KEY `idx_fmss_company` (`company_id`),
  CONSTRAINT `fk_fmss_model` FOREIGN KEY (`model_id`) REFERENCES `fleet_model` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: founding_mode ──
CREATE TABLE `founding_mode` (
  `mode_id` int unsigned NOT NULL AUTO_INCREMENT,
  `mode` enum('discovery','permission_test') COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `started_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL COMMENT 'إلزامي عند التفعيل — لا وضع تأسيس مفتوح المدة',
  `banner_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closure_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`mode_id`),
  UNIQUE KEY `uq_fm_mode` (`mode`),
  CONSTRAINT `chk_fm_ends` CHECK (((`enabled` = 0) or (`ends_at` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §7: التوسيع في discovery وحده — والحراس لا يُعطَّلون مهما اتسع التأسيس';

-- ── Table: governance_flags ──
CREATE TABLE `governance_flags` (
  `flag_id` int unsigned NOT NULL AUTO_INCREMENT,
  `element_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'external_accounts · signing_caps · joint_signing · guarantees · licenses …',
  `scope_type` enum('entity','contract') COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_id` int unsigned NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `set_by` int DEFAULT NULL,
  `set_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`flag_id`),
  UNIQUE KEY `uq_gf_element_scope` (`element_code`,`scope_type`,`scope_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §7: أعلام التفعيل لكل عنصر على الكيان والعقد — الافتراض النمط ① (كله مطفأ)';

-- ── Table: guarantees ──
CREATE TABLE `guarantees` (
  `gtee_id` int unsigned NOT NULL AUTO_INCREMENT,
  `direction` enum('issued','received') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'صادرة منا (التزام محتمل) · واردة إلينا (حق محتمل)',
  `entity_id` int unsigned NOT NULL,
  `counterparty_id` int unsigned DEFAULT NULL,
  `gtee_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `alert_days` int unsigned NOT NULL DEFAULT '30',
  `auto_renew` tinyint(1) NOT NULL DEFAULT '0',
  `fees` decimal(18,2) DEFAULT NULL,
  `state` enum('active','released','called','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`gtee_id`),
  KEY `ix_g_expiry` (`expiry_date`,`state`),
  KEY `fk_g_entity` (`entity_id`),
  CONSTRAINT `fk_g_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §5: الكفالات وخطابات الضمان — التزام/حق محتمل خارج الميزانية، مفصول عن المحتجَز النقدي (P-06)';

-- ── Table: guard_denials ──
CREATE TABLE `guard_denials` (
  `deny_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL DEFAULT '0',
  `guard_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `person_id` int NOT NULL,
  `attempted_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason_code` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`deny_id`),
  KEY `ix_gd_guard` (`guard_code`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §9: سجل المنع — مقياس ملاءمة الحماية لا سجل مخالفات المستخدمين';

-- ── Table: guard_override_policies ──
CREATE TABLE `guard_override_policies` (
  `guard_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `overridable` enum('never','break_glass_only','with_compensating_control') COLLATE utf8mb4_unicode_ci NOT NULL,
  `environments_json` json DEFAULT NULL COMMENT 'بيئات السريان — production·founding·test',
  PRIMARY KEY (`guard_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §7.2: الاسم يصف السياسة لا النتيجة — ويقرؤها كسر الزجاج فلا يتجاوز never';

-- ── Table: guard_policies ──
CREATE TABLE `guard_policies` (
  `guard_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_doc` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'وثيقة البيت',
  `guard_class` enum('absolute','exception_allowed','advisory') COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_risk` enum('normal','operational','financial','high','legal_forbidden') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `env_flag_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم العلم في .env',
  `classified_by` int DEFAULT NULL,
  `classified_at` datetime DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سبب إلزامي لأي تغيير صنف',
  PRIMARY KEY (`guard_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §10: قاموس تصنيف الحمايات — الصنف يتغير بقرار حوكمة لا بتعديل إعداد';

-- ── Table: housing_unit ──
CREATE TABLE `housing_unit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_id` int DEFAULT NULL COMMENT 'بالقيمة إلى project.id',
  `capacity` int DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hu_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: hr_dictionaries ──
CREATE TABLE `hr_dictionaries` (
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `layer` enum('relation','family','level') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rank` int DEFAULT NULL COMMENT 'للمستوى — درجة السلطة تصاعديًّا',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: تُضاف قيمها بصف لا بكود';

-- ── Table: impact_matrix ──
CREATE TABLE `impact_matrix` (
  `mx_id` int unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` int unsigned NOT NULL,
  `state_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'حالة الإدارة',
  `party_type` enum('client','supplier','operator','company','financier') COLLATE utf8mb4_unicode_ci NOT NULL,
  `effect` enum('billable','countable','payable','penalized','none') COLLATE utf8mb4_unicode_ci NOT NULL,
  `derived_from` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع المصفوفة الأم (CON-02 §5) إن اشتُقت',
  PRIMARY KEY (`mx_id`),
  UNIQUE KEY `uq_mx` (`policy_id`,`state_code`,`party_type`),
  CONSTRAINT `fk_mx_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §8: مصفوفة الأثر — لا حالة بلا أثر معلن لكل طرف، ولا أثر يُستنتج';

-- ── Table: incentive_allocations ──
CREATE TABLE `incentive_allocations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `rule_id` int NOT NULL,
  `beneficiary_type` enum('employee','job_title') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'شخصٌ بعينه أو صفةٌ تُحل وقتَ الاحتساب («مشغّلٌ ومساعدٌ ومشرف»)',
  `beneficiary_id` int NOT NULL,
  `percent` decimal(5,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ia_beneficiary` (`rule_id`,`beneficiary_type`,`beneficiary_id`),
  KEY `ix_ia_rule` (`rule_id`),
  KEY `ix_ia_company` (`company_id`),
  CONSTRAINT `fk_ia_rule` FOREIGN KEY (`rule_id`) REFERENCES `incentive_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: incentive_rules ──
CREATE TABLE `incentive_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `incentive_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسمُ الحافز من الاتفاق — لا قائمةَ مثبَّتةً في الكود',
  `basis` enum('unit','threshold','quality','readiness','safety','fuel','tier') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'أسسُ §3.3 السبعة: وحدةٌ منفَّذة · تجاوزُ عتبة · جودة · جاهزية · التزامُ سلامة · توفيرُ وقود · شرائح',
  `rate` decimal(14,4) DEFAULT NULL,
  `threshold` decimal(18,2) DEFAULT NULL,
  `cap` decimal(18,2) DEFAULT NULL COMMENT 'السقف — بنص الشريحة §5.2-③',
  `floor` decimal(18,2) DEFAULT NULL COMMENT 'الحدُّ الأدنى',
  `periodicity` enum('monthly','periodic','once') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `condition_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'شرطُ الاستحقاق نصًّا',
  `scope_type` enum('project','equipment_type','site') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نطاقُ §3.3',
  `scope_id` int DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','replaced','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ir_contract` (`contract_id`),
  KEY `ix_ir_company` (`company_id`),
  CONSTRAINT `fk_ir_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: intercompany_dues ──
CREATE TABLE `intercompany_dues` (
  `due_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `loan_id` int unsigned NOT NULL,
  `period` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creditor_entity_id` int unsigned NOT NULL,
  `debtor_entity_id` int unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` enum('accrued','settled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'accrued',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`due_id`),
  UNIQUE KEY `uq_icd` (`loan_id`,`period`,`creditor_entity_id`),
  CONSTRAINT `fk_icd_loan` FOREIGN KEY (`loan_id`) REFERENCES `intercompany_loans` (`loan_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-09: المستحق المتبادل المسجَّل بين الكيانين — بنسب التحمل';

-- ── Table: intercompany_loans ──
CREATE TABLE `intercompany_loans` (
  `loan_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `equipment_id` int NOT NULL,
  `lender_entity_id` int unsigned NOT NULL COMMENT 'الكيان المعير — داخلي (is_tenant/داخل المجموعة)',
  `borrower_entity_id` int unsigned NOT NULL COMMENT 'الكيان المستعير — داخلي',
  `date_from` date NOT NULL,
  `date_to` date DEFAULT NULL,
  `monthly_value` decimal(18,2) NOT NULL COMMENT 'القيمة المحاسبية الشهرية للإعارة',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `bearing_split_json` json NOT NULL COMMENT 'نسب التحمل بين الكيانين — Σ = 100 (تحرسه الخدمة)',
  `internal_transaction` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'علامة معاملة بين كيانين داخليين — قيد ⑥',
  `state` enum('active','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`loan_id`),
  KEY `ix_icl_equipment` (`company_id`,`equipment_id`,`state`),
  KEY `fk_icl_lender` (`lender_entity_id`),
  KEY `fk_icl_borrower` (`borrower_entity_id`),
  CONSTRAINT `fk_icl_borrower` FOREIGN KEY (`borrower_entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_icl_lender` FOREIGN KEY (`lender_entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_icl_not_self` CHECK ((`lender_entity_id` <> `borrower_entity_id`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-09: إعارة المعدات بين كيانين داخليين — النمط ② في LEG-01';

-- ── Table: job_titles ──
CREATE TABLE `job_titles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SEC-01 §12: الكود المعتمد',
  `company_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `family_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'العائلة — hr_dictionaries',
  `level_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `org_unit_id` int unsigned DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duties_json` json DEFAULT NULL,
  `default_manager_position_id` int unsigned DEFAULT NULL,
  `functional_line_unit_id` int unsigned DEFAULT NULL,
  `operational_line_unit_id` int unsigned DEFAULT NULL,
  `template_id` int unsigned DEFAULT NULL,
  `allowed_scopes_json` json DEFAULT NULL,
  `amount_cap` decimal(18,2) DEFAULT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prohibitions_json` json DEFAULT NULL,
  `qualifications_json` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_operator` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jobtitle_company_name` (`company_id`,`name`),
  UNIQUE KEY `uq_jt_code` (`title_code`),
  KEY `idx_jobtitle_company` (`company_id`),
  KEY `idx_jobtitle_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: legal_entities ──
CREATE TABLE `legal_entities` (
  `entity_id` int unsigned NOT NULL AUTO_INCREMENT,
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `legal_form` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SD',
  `registry_authority` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'السجل التجاري',
  `commercial_reg` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_no` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG' COMMENT 'عملة الدفاتر (functional_currency)',
  `is_tenant` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'كيانات المجموعة المستأجرة — حد العزل من tenants حصرًا',
  `ownership_completeness` enum('full','partial','unknown') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown' COMMENT 'قيد المئة يُفرض عند full وحده',
  `state` enum('active','suspended','liquidation','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `registered_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `founded_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`entity_id`),
  UNIQUE KEY `uq_le_registry` (`country`,`registry_authority`,`commercial_reg`) COMMENT 'الفرادة بالثلاثة معًا — الرقم قد يتكرر في دولتين',
  KEY `ix_le_tenant` (`is_tenant`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §2: الكيانات القانونية — سجل واحد لا يتكرر، ولا عمود صفات نصي ولا JSON';

-- ── Table: link_groups ──
CREATE TABLE `link_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم المجموعة كما يظهر في السايدبار',
  `group_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NAV-01 §4: g1..g8 — المجموعات القياسية',
  `owner_role_id` int DEFAULT NULL COMMENT 'الدور المالك — نفس دلالة modules.owner_role_id',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fa fa-folder',
  `display_order` int NOT NULL DEFAULT '0' COMMENT 'الأصغر يظهر أولاً',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `ix_owner_role` (`owner_role_id`),
  KEY `ix_display_order` (`display_order`),
  KEY `idx_lg_code` (`owner_role_id`,`group_code`),
  CONSTRAINT `link_groups_role_fk` FOREIGN KEY (`owner_role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مجموعات روابط السايدبار — لكل دورٍ مجموعاته';

-- ── Table: messages ──
CREATE TABLE `messages` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'المعرف الفريد',
  `company_id` int NOT NULL COMMENT 'رقم الشركة - لعزل الرسائل بين الشركات',
  `sender_id` int NOT NULL COMMENT 'رقم المرسل (users.id)',
  `receiver_id` int NOT NULL COMMENT 'رقم المستلم (users.id)',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نص الرسالة',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=غير مقروءة، 1=مقروءة',
  `read_at` datetime DEFAULT NULL COMMENT 'وقت القراءة',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'وقت الإرسال',
  `is_deleted_sender` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'حُذفت من قِبل المرسل',
  `is_deleted_receiver` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'حُذفت من قِبل المستلم',
  PRIMARY KEY (`id`),
  KEY `idx_msg_sender` (`sender_id`),
  KEY `idx_msg_receiver` (`receiver_id`),
  KEY `idx_msg_company` (`company_id`),
  KEY `idx_msg_read` (`is_read`),
  KEY `idx_msg_created` (`created_at`),
  KEY `idx_msg_conversation` (`sender_id`,`receiver_id`,`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='الرسائل الداخلية بين مستخدمي الشركة';

-- ── Table: meter_readings ──
CREATE TABLE `meter_readings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `equipment_id` int NOT NULL,
  `meter_type` enum('hour','km') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hour' COMMENT 'UX-10 §8 نصًّا — لا ثالثَ لهما',
  `chain_no` int NOT NULL DEFAULT '1' COMMENT 'سلسلةُ العدّاد — التصفيرُ الموثَّق يزيدها',
  `reading_date` date NOT NULL,
  `value` decimal(18,2) NOT NULL,
  `delta` decimal(18,2) DEFAULT NULL COMMENT 'الفارقُ عن سابقتها في السلسلة — NULL لأولها',
  `source` enum('manual','inspection','timesheet','reset') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `source_ref` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجعُ الواقعة: TS-‹id› · INS-‹id›',
  `is_reset` tinyint NOT NULL DEFAULT '0',
  `reset_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مستندُ قرار التصفير — إلزاميٌّ متى صُفّر',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by` int DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meter_reading_day` (`equipment_id`,`meter_type`,`reading_date`),
  KEY `ix_meter_latest` (`equipment_id`,`meter_type`,`chain_no`,`reading_date`),
  KEY `ix_meter_co` (`company_id`,`reading_date`),
  CONSTRAINT `fk_meter_reading_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_meter_reset_doc` CHECK (((`is_reset` = 0) or ((`reset_doc_ref` is not null) and (char_length(trim(`reset_doc_ref`)) > 0)))),
  CONSTRAINT `ck_meter_value` CHECK ((`value` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_breakdown ──
CREATE TABLE `mnt_breakdown` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل الشركة (إجباري)',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع البلاغ، مثل BR-2026-0001',
  `equipment_id` int DEFAULT NULL COMMENT 'FK→equipments.id (ربط رقمي)',
  `project_id` int DEFAULT NULL COMMENT 'FK→project.id',
  `reported_by` int DEFAULT NULL COMMENT 'FK→users.id (المُبلِّغ)',
  `reporter_dept` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'القسم المُبلِّغ',
  `target_role` int DEFAULT NULL,
  `report_datetime` datetime DEFAULT NULL,
  `failure_code_id` int DEFAULT NULL COMMENT 'FK→failure_codes.id (إعادة استخدام دون تعديل)',
  `severity` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'منخفضة/متوسطة/عالية/حرجة',
  `is_stopped` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'هل المعدة متوقفة',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attachment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_id` int DEFAULT NULL COMMENT 'FK→mnt_order.id بعد التحويل لأمر',
  `state` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'جديد' COMMENT 'جديد/قيد التقييم/محوّل/مغلق',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_breakdown_eq_company_state` (`equipment_id`,`company_id`,`state`),
  KEY `idx_breakdown_company_state` (`company_id`,`state`),
  KEY `idx_breakdown_order` (`order_id`),
  KEY `idx_breakdown_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_inspection ──
CREATE TABLE `mnt_inspection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل الشركة (إجباري)',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع التفتيش، مثل INS-2026-0001',
  `inspection_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'دوري' COMMENT 'دوري/زيارة ميدانية/استلام/بعد حادث',
  `template_id` int DEFAULT NULL COMMENT 'FK→mnt_inspection_template.id',
  `equipment_id` int DEFAULT NULL COMMENT 'FK→equipments.id',
  `supplier_id` int DEFAULT NULL COMMENT 'FK→suppliers.id',
  `external_equipment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'وصف معدة خارجية',
  `project_id` int DEFAULT NULL COMMENT 'FK→project.id',
  `inspector_id` int DEFAULT NULL COMMENT 'FK→users.id (الفاحص)',
  `scheduled_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `score` int DEFAULT NULL,
  `overall_result` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tech_readiness_state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الجاهزية الفنية',
  `equipment_condition` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'تُكتب لكرت المعدة عند الإكمال + تُخزّن',
  `engine_condition` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'تُكتب لكرت المعدة عند الإكمال + تُخزّن',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `state` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'جديد' COMMENT 'جديد/مجدول/قيد التنفيذ/مكتمل/مغلق',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inspection_equipment` (`equipment_id`),
  KEY `idx_inspection_company_state` (`company_id`,`state`),
  KEY `idx_inspection_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_inspection_line ──
CREATE TABLE `mnt_inspection_line` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `inspection_id` int NOT NULL COMMENT 'FK→mnt_inspection.id',
  `template_line_id` int DEFAULT NULL COMMENT 'مصدر البند من القالب',
  `component` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'المنظومة',
  `applies_to` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ينطبق على',
  `check_method` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'طريقة الفحص',
  `measured_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'القيمة المقاسة/الحد',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'الملاحظة',
  `seq` int NOT NULL DEFAULT '0',
  `is_template` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=بند قالب (لا يُحذف)',
  `condition_state` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سليم/ملاحظة/حرج',
  `recommendation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `photo_ref` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'M-34: مرجعُ صورة البند',
  `converted_ticket_id` int DEFAULT NULL COMMENT 'M-34: بلاغُ NoteConverted — ولا يتكرر',
  PRIMARY KEY (`id`),
  KEY `idx_inspline_inspection` (`inspection_id`),
  CONSTRAINT `fk_inspline_inspection` FOREIGN KEY (`inspection_id`) REFERENCES `mnt_inspection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_inspection_template ──
CREATE TABLE `mnt_inspection_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL COMMENT 'NULL = قالب عام مشترك لكل الشركات',
  `type_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'EQUIP-MNT-DLY ...',
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم الاستمارة',
  `inspection_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'قيمة mnt_inspection.inspection_type',
  `header_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'equipment' COMMENT 'equipment/supplier/external',
  `condition_scale` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default' COMMENT 'default/accident/overhaul',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tpl_company_code` (`company_id`,`type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_inspection_template_line ──
CREATE TABLE `mnt_inspection_template_line` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `section` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'المنظومة/المجموعة',
  `seq` int NOT NULL DEFAULT '0',
  `item` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'البند',
  `applies_to` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ينطبق على: عام/حفّار/قلّاب/دريل/لودر',
  `check_method` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'طريقة الفحص',
  `reference_limit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'القيمة المقاسة / الحد المرجعي',
  PRIMARY KEY (`id`),
  KEY `idx_tplline_template` (`template_id`),
  CONSTRAINT `fk_tplline_template` FOREIGN KEY (`template_id`) REFERENCES `mnt_inspection_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_lookup ──
CREATE TABLE `mnt_lookup` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل الشركة (إجباري)',
  `type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'سبب عطل/سبب توقّف/نوع مهمة/ورشة',
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `extra` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lookup_company_type` (`company_id`,`type`),
  KEY `idx_lookup_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_order ──
CREATE TABLE `mnt_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل الشركة (إجباري)',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع الأمر، مثل MNT-2026-0001',
  `breakdown_id` int DEFAULT NULL COMMENT 'FK→mnt_breakdown.id (مصدر بلاغ)',
  `plan_id` int DEFAULT NULL COMMENT 'FK→mnt_plan.id (مصدر وقائي)',
  `inspection_id` int DEFAULT NULL COMMENT 'FK→mnt_inspection.id (مصدر تفتيش)',
  `equipment_id` int DEFAULT NULL COMMENT 'FK→equipments.id',
  `project_id` int DEFAULT NULL COMMENT 'FK→project.id',
  `source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'بلاغ' COMMENT 'بلاغ/وقائي/تفتيش',
  `is_auto` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Ïú┘àÏ▒ ÏÁ┘èÏº┘åÏ® Ïú┘Å┘åÏ┤Ïª Ï¬┘ä┘éÏºÏª┘è┘ïÏº ┘à┘å ÏÁ┘üÏ¡Ï® Ïº┘äÏ¡Ï▒┘âÏ®',
  `maint_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع الصيانة',
  `priority` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_party` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'جهة التكلفة: داخلي/خارجي',
  `charge_supplier_id` int DEFAULT NULL COMMENT 'مورّدُ الآليات الذي تُحمَّل عليه تكلفةُ الأمر — يُفعّل حين cost_party=خارجي',
  `vendor_id` int DEFAULT NULL COMMENT 'FK→suppliers.id (ورشة خارجية)',
  `workshop` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technician_id` int DEFAULT NULL COMMENT 'FK→users.id (الفني)',
  `supervisor_id` int DEFAULT NULL COMMENT 'FK→users.id (المشرف)',
  `failure_code_id` int DEFAULT NULL COMMENT 'FK→failure_codes.id',
  `diagnosis` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `root_cause_id` int DEFAULT NULL COMMENT 'FK→mnt_lookup.id (سبب جذري)',
  `actions_taken` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `work_start` datetime DEFAULT NULL,
  `work_end` datetime DEFAULT NULL,
  `downtime_hours` decimal(10,2) NOT NULL DEFAULT '0.00',
  `labor_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `parts_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `external_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `inspection_result` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ناجح/راسب',
  `state` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'بلاغ' COMMENT 'بلاغ/تنفيذ/فحص/إغلاق/ملغى',
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `waiting_part_since` date DEFAULT NULL COMMENT 'M-32: تاريخُ دخول WaitingPart — العدّادُ يُحسب منه',
  `pm_cycle_key` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'M-36: plan:{id}:eq:{id}:due:{date} — يمنع توليدَ الدورة مرتين',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mnt_pm_cycle` (`pm_cycle_key`),
  KEY `idx_order_eq_company_state` (`equipment_id`,`company_id`,`state`),
  KEY `idx_order_company_state` (`company_id`,`state`),
  KEY `idx_order_breakdown` (`breakdown_id`),
  KEY `idx_order_plan` (`plan_id`),
  KEY `idx_order_inspection` (`inspection_id`),
  KEY `idx_order_deleted` (`is_deleted`),
  KEY `idx_mnt_order_auto_open` (`company_id`,`equipment_id`,`project_id`,`is_auto`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_order_labor ──
CREATE TABLE `mnt_order_labor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `order_id` int NOT NULL COMMENT 'FK→mnt_order.id',
  `employee_id` int DEFAULT NULL COMMENT 'FK→users.id (اختياري)',
  `role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hours` decimal(8,2) NOT NULL DEFAULT '0.00',
  `hourly_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_labor_order` (`order_id`),
  CONSTRAINT `fk_labor_order` FOREIGN KEY (`order_id`) REFERENCES `mnt_order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_order_part ──
CREATE TABLE `mnt_order_part` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `order_id` int NOT NULL COMMENT 'FK→mnt_order.id',
  `part_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_major_component` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_part_order` (`order_id`),
  CONSTRAINT `fk_part_order` FOREIGN KEY (`order_id`) REFERENCES `mnt_order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_plan ──
CREATE TABLE `mnt_plan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل الشركة (إجباري)',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع الخطة، مثل PLN-2026-0001',
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'معدة/فئة',
  `equipment_id` int DEFAULT NULL COMMENT 'FK→equipments.id',
  `category_id` int DEFAULT NULL COMMENT 'FK→equipments_types.id',
  `trigger_basis` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ساعات' COMMENT 'ساعات/زمن',
  `interval_value` int DEFAULT NULL COMMENT 'الفاصل (ساعات أو أيام)',
  `tolerance` int DEFAULT NULL,
  `last_done_date` date DEFAULT NULL,
  `last_done_meter` decimal(12,2) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `next_due_meter` decimal(12,2) DEFAULT NULL,
  `state` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'نشطة' COMMENT 'نشطة/متوقفة',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plan_eq_due` (`equipment_id`,`next_due_date`),
  KEY `idx_plan_company_state` (`company_id`,`state`),
  KEY `idx_plan_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_plan_task ──
CREATE TABLE `mnt_plan_task` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `plan_id` int NOT NULL COMMENT 'FK→mnt_plan.id',
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_type` int DEFAULT NULL COMMENT 'FK→mnt_lookup.id (نوع مهمة)',
  `component` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `est_hours` decimal(8,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plantask_plan` (`plan_id`),
  CONSTRAINT `fk_plantask_plan` FOREIGN KEY (`plan_id`) REFERENCES `mnt_plan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: modules ──
CREATE TABLE `modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_role_id` int DEFAULT NULL,
  `group_id` int DEFAULT NULL,
  `is_link` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `is_quick` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'تظهر في روابط الوصول السريع بلوحة التحكم',
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int DEFAULT '0' COMMENT 'ترتيب العرض في القوائم',
  PRIMARY KEY (`id`),
  KEY `owner_role_id` (`owner_role_id`),
  KEY `idx_display_order` (`display_order`),
  KEY `ix_modules_group` (`group_id`),
  CONSTRAINT `modules_group_fk` FOREIGN KEY (`group_id`) REFERENCES `link_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`owner_role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: monthly_performance ──
CREATE TABLE `monthly_performance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `contract_id` int unsigned NOT NULL,
  `container_id` int unsigned NOT NULL COMMENT 'حاوية المقعد (op_containers · level=معدة · seat_no)',
  `period` char(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM',
  `contract_hours` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'التعاقدية (من contract_hours_monthly للمقعد)',
  `executed_hours` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'المنفَّذة — مجمَّعة من container_consumption',
  `executed_base_hours` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'الأساسية المنفَّذة (دون الإضافي)',
  `standby_hours` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'الاستعداد',
  `available_hours` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'المتاحة',
  `shortfall_hours` decimal(10,2) GENERATED ALWAYS AS (greatest(((`contract_hours` - `executed_hours`) - `standby_hours`),0)) STORED COMMENT 'العجز عن التعاقد — محسوب',
  `completion_pct` decimal(6,2) GENERATED ALWAYS AS (if((`contract_hours` > 0),round(((`executed_hours` / `contract_hours`) * 100),2),NULL)) STORED COMMENT 'نسبة الإنجاز — محسوبة',
  `trips` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tons` decimal(14,2) NOT NULL DEFAULT '0.00',
  `meters` decimal(14,2) NOT NULL DEFAULT '0.00',
  `fuel_consumed` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'وقود مستهلك',
  `state` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `closed_by` int unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mp_seat_period` (`company_id`,`container_id`,`period`),
  KEY `ix_mp_contract` (`company_id`,`contract_id`,`period`),
  KEY `fk_mp_container` (`container_id`),
  CONSTRAINT `fk_mp_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_mp_hours` CHECK (((`contract_hours` >= 0) and (`executed_hours` >= 0) and (`standby_hours` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-12: سجل الأداء الشهري (مقعد × شهر) — مشتق مجمَّع، ليس مصدر كمية الفوترة (PLAN-04 §2.2)';

-- ── Table: monthly_performance_downtime ──
CREATE TABLE `monthly_performance_downtime` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `perf_id` int unsigned NOT NULL,
  `reason_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'من stop_reason_codes حصرًا',
  `hours` decimal(10,2) NOT NULL,
  `obligation_id` int NOT NULL COMMENT 'بند الالتزام المقابل — إلزامي (سبب بلا بند لا يُقبل)',
  `bearer_party` enum('client','company','supplier','operator','none') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الطرف المتحمل — مُشتق من البند وقت التسجيل، لا يُكتب حرًّا',
  `effect_on_billing` enum('billable_standby','non_billable','per_clause') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'per_clause' COMMENT 'لقطة أثر البند على الفوترة',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mpd_reason` (`perf_id`,`reason_code`),
  KEY `ix_mpd_company` (`company_id`,`perf_id`),
  KEY `fk_mpd_reason` (`reason_code`),
  KEY `fk_mpd_obligation` (`obligation_id`),
  CONSTRAINT `fk_mpd_obligation` FOREIGN KEY (`obligation_id`) REFERENCES `contract_obligations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mpd_perf` FOREIGN KEY (`perf_id`) REFERENCES `monthly_performance` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mpd_reason` FOREIGN KEY (`reason_code`) REFERENCES `stop_reason_codes` (`code`) ON DELETE RESTRICT,
  CONSTRAINT `ck_mpd_hours` CHECK ((`hours` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-12: ساعات التعطل بسببها وبندها وطرفها المتحمل — الإسناد بالساعات لا بالعلامة';

-- ── Table: nav_items ──
CREATE TABLE `nav_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL COMMENT 'الدور المالك لهذا العنصر في قائمته',
  `door` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'HOME·DAILY·APPR·REC·REP·SET — الأبواب الستة',
  `group_id` int DEFAULT NULL COMMENT 'link_groups — مجموعةٌ قابلةٌ للطيّ داخل الباب؛ NULL = مباشرةً تحته',
  `module_id` int DEFAULT NULL COMMENT 'modules.id حين يكون العنصر شاشةً مسجَّلة — مرجعُ الصلاحية والاسم',
  `label_ar` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم العرض؛ يُفحص خلوّه من المحظور المعماري عند الحفظ',
  `route` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'المسار كما في سجل الشاشات',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0' COMMENT 'الترتيب داخل الباب/المجموعة',
  `counter_source` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مُعرِّف العدّاد من سجل العدّادات — عدّادٌ واحدٌ بقيمةٍ واحدة',
  `permission_code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'كود الشاشة لفحص can_view؛ NULL = ظهورٌ بلا فحص (ثوابت)',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nav_role_route` (`role_id`,`route`),
  KEY `ix_nav_role_door` (`role_id`,`door`,`sort_order`),
  KEY `ix_nav_group` (`group_id`),
  KEY `ix_nav_module` (`module_id`),
  CONSTRAINT `chk_nav_door` CHECK ((`door` in (_utf8mb4'HOME',_utf8mb4'DAILY',_utf8mb4'APPR',_utf8mb4'REC',_utf8mb4'REP',_utf8mb4'SET',_utf8mb4'GOV',_utf8mb4'FIN')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='المصدر الموحّد لعناصر السايدبار — UX-01 §10.2';

-- ── Table: nav_redirects ──
CREATE TABLE `nav_redirects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `old_route` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_route` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `hits` int NOT NULL DEFAULT '0' COMMENT 'عدّادُ استعمالٍ يقيس أمان الحذف لاحقًا',
  `last_hit_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_navred_old` (`old_route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تحويلُ المسارات القديمة — UX-01 §10.2';

-- ── Table: op_containers ──
CREATE TABLE `op_containers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `container_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CNT-سنة-تسلسل — ترقيمٌ خادمي',
  `level` enum('رئيسية','مورد','نوع','معدة','مشغّل') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int unsigned DEFAULT NULL COMMENT 'NULL للرئيسية حصرًا — يحرسه ck_container_parent',
  `contract_id` int unsigned NOT NULL,
  `contract_item_id` int unsigned DEFAULT NULL COMMENT 'contractequipments.id — مصدرُ سقف الرئيسية',
  `resource_plan_id` int unsigned DEFAULT NULL COMMENT 'صفُّ خطة الموارد الذي بُذرت منه الحاوية (P-04) — والقديمُ يبقى على contract_item_id',
  `unit_type` enum('hour','ton','meter','cbm','day','shift','trip') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hour' COMMENT 'وحدةُ البند — والسقفُ والمستهلَكُ بها',
  `work_model` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نموذجُ العمل كما في البند',
  `cap_qty` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'السقف — لا يُتجاوز',
  `allocated_qty` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'Σ ما وُزّع على الأبناء — قيدُ Σ البنيوي',
  `consumed_qty` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'Σ ما استُهلك فعلًا',
  `remaining_qty` decimal(16,2) GENERATED ALWAYS AS ((`cap_qty` - `consumed_qty`)) STORED COMMENT 'مولَّدٌ لا يُكتب — فلا مصدرانِ للرقم الواحد',
  `supplier_id` int unsigned DEFAULT NULL,
  `equipment_id` int unsigned DEFAULT NULL,
  `operator_employee_id` int unsigned DEFAULT NULL,
  `project_id` int unsigned DEFAULT NULL COMMENT 'الموقع — مفتاحُ الحجب المرحليّ (المرحلة ③)',
  `role_kind` enum('أساسية','احتياطية','أساسي','بديل أول','بديل ثانٍ','مشترك') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seat_no` smallint unsigned DEFAULT NULL COMMENT 'N-11: رقم المقعد التعاقدي — فريد داخل العقد لمستوى معدة',
  `seat_kind` enum('contractual_seat','operational_resource_slot','supplier_allocation') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N-11: نوع المقعد — يُشتق من فصل بند البيع عن خطة الموارد (PLAN-03 §4) لا يُصنَّف مستقلًّا',
  `seat_equipment_type_id` int unsigned DEFAULT NULL COMMENT 'N-11: نوع المعدة المطلوب للمقعد (equipments_types.id)',
  `contract_hours_monthly` decimal(10,2) DEFAULT NULL COMMENT 'N-11: الساعات التعاقدية الشهرية للمقعد',
  `seat_unit_price` decimal(14,4) DEFAULT NULL COMMENT 'N-11: سعر وحدة المقعد',
  `seat_currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N-11: عملة سعر المقعد',
  `shift_no` tinyint unsigned DEFAULT NULL COMMENT 'نوبةُ المشغّل',
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('نشطة','معلَّقة','مقفلة') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'نشطة',
  `origin` enum('عقد','مشتقّة') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'عقد' COMMENT 'H-01 ②: منشأُ الرقم — «مشتقّة» تنتظر إقرارَ الإدارة ولا تُقدَّم متفقًا عليها',
  `origin_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مِن أين اشتُقّت بالضبط — فيُدقَّق الاستنتاجُ لا يُصدَّق',
  `origin_ack_by` int unsigned DEFAULT NULL COMMENT 'مَن أقرّ الحصةَ المشتقّة — NULL = لم تُقرّ بعد',
  `origin_ack_at` datetime DEFAULT NULL,
  `close_reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_container_no` (`company_id`,`container_no`),
  UNIQUE KEY `uq_main_per_item` (`company_id`,`contract_item_id`,`level`),
  UNIQUE KEY `uq_seat_no` (`company_id`,`contract_id`,`seat_no`),
  KEY `ix_parent` (`company_id`,`parent_id`),
  KEY `ix_contract` (`company_id`,`contract_id`,`level`),
  KEY `ix_site` (`company_id`,`project_id`,`state`),
  KEY `fk_container_parent` (`parent_id`),
  KEY `ix_container_origin` (`company_id`,`origin`,`origin_ack_by`),
  KEY `ix_oc_resource_plan` (`resource_plan_id`),
  CONSTRAINT `fk_container_parent` FOREIGN KEY (`parent_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_container_alloc` CHECK (((`allocated_qty` >= 0) and (`allocated_qty` <= `cap_qty`))),
  CONSTRAINT `ck_container_cap` CHECK ((`cap_qty` >= 0)),
  CONSTRAINT `ck_container_consumed` CHECK (((`consumed_qty` >= 0) and (`consumed_qty` <= `cap_qty`))),
  CONSTRAINT `ck_container_parent` CHECK ((((`level` = _utf8mb4'رئيسية') and (`parent_id` is null)) or ((`level` <> _utf8mb4'رئيسية') and (`parent_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='H-01 §4 — حاوياتُ العقد بمستوياتها الأربعة وقيدِ Σ البنيوي';

-- ── Table: operations ──
CREATE TABLE `operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `equipment` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `equipment_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `equipment_category` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prev_equipment_category` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `end` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `days` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_equipment_hours` decimal(10,2) DEFAULT '0.00' COMMENT 'إجمالي ساعات العمل الكلية للآلية',
  `shift_hours` decimal(10,2) DEFAULT '0.00' COMMENT 'عدد ساعات الوردية للمعدة',
  `target_daily_hours` decimal(10,2) DEFAULT NULL COMMENT 'الساعات اليومية المستهدفة للآلية (مرجع للمقارنة منفّذ/مستهدف)',
  `shift_type` enum('D','N','B') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'B',
  `status` tinyint(1) DEFAULT '1',
  `op_state` enum('تعمل','جاهزة','معطلة') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'جاهزة' COMMENT 'حالة الآلية النشطة — تُدار من صفحة الحركة فقط',
  `equipment_health` enum('سليمة','معطلة') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'سليمة' COMMENT 'الصحة الفنية للمعدة (مستقلة عن status التشغيلي)',
  `health_reason` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سبب العطل، مثل: صيانة',
  `health_updated_at` datetime DEFAULT NULL,
  `health_updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_total_equipment_hours` (`total_equipment_hours`),
  KEY `idx_shift_hours` (`shift_hours`),
  KEY `idx_operations_equipment_health` (`equipment_health`),
  KEY `idx_operations_project` (`project_id`),
  KEY `idx_operations_supplier` (`supplier_id`),
  KEY `idx_operations_equipment` (`equipment`),
  KEY `idx_operations_start` (`start`),
  KEY `idx_operations_company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: operator_rotations ──
CREATE TABLE `operator_rotations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `container_id` int unsigned NOT NULL COMMENT 'حاويةُ المشغّل',
  `operator_employee_id` int unsigned NOT NULL,
  `cycle_on_days` smallint unsigned NOT NULL COMMENT 'أيامُ العمل في الدورة',
  `cycle_off_days` smallint unsigned NOT NULL COMMENT 'أيامُ الراحة',
  `cycle_start` date NOT NULL COMMENT 'مبدأُ الدورة — منه يُحسب المناوب',
  `shift_no` tinyint unsigned DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rotation` (`company_id`,`container_id`,`operator_employee_id`,`cycle_start`),
  KEY `ix_rotation_op` (`company_id`,`operator_employee_id`),
  KEY `fk_rotation_container` (`container_id`),
  CONSTRAINT `fk_rotation_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_rotation_cycle` CHECK ((`cycle_on_days` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='H-01 §4 — دوراتُ تناوب المشغّلين داخل حاوياتهم';

-- ── Table: opportunities ──
CREATE TABLE `opportunities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `opp_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `client_id` int DEFAULT NULL,
  `source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sector_category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `state_region` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `revenue_model` enum('hourly','ton','meter','mixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expected_revenue` decimal(14,2) NOT NULL DEFAULT '0.00',
  `currency` enum('USD','SDG') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'USD',
  `probability` decimal(5,2) NOT NULL DEFAULT '0.00',
  `stage` enum('جديدة','قيد الدراسة','مؤهلة','عرض مقدم','تفاوض','فوز','خسارة','مستبعدة') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'جديدة',
  `attractiveness` enum('منخفضة','متوسطة','عالية') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `strategy_fit` enum('منخفض','متوسط','عالي') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `capacity_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `requirements_json` text COLLATE utf8mb4_general_ci COMMENT 'INJAZ-S05 — المتطلبات المبدئية المُهيكلة (معدات بالنوع + عددا مشغّلين/موردين) JSON؛ capacity_summary مشتقٌّ منه',
  `funding_needed` decimal(14,2) NOT NULL DEFAULT '0.00',
  `study_decision` enum('متابعة','تعليق','استبعاد') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expected_close_date` date DEFAULT NULL,
  `lost_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `win_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `review_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_opportunities_company_code` (`company_id`,`opp_code`),
  KEY `idx_opp_company` (`company_id`),
  KEY `idx_opp_client` (`client_id`),
  KEY `idx_opp_stage` (`stage`),
  KEY `idx_opp_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: org_assignment_types ──
CREATE TABLE `org_assignment_types` (
  `type_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` enum('central','site') COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_capabilities_json` json DEFAULT NULL,
  `requires_functional_line` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'الموقعيُّ كلُّه =1: خطّان تشغيليٌّ وفنيٌّ لا خطٌّ واحد (§2⑦)',
  `is_unit_head` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'نوعٌ يجعل صاحبَه رأسَ وحدته — يغذي اشتقاق v_org_unit_heads',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: أنواعُ التكليف — يُضاف نوعٌ جديدٌ بصفٍّ لا بتعديل برمجة';

-- ── Table: org_assignments ──
CREATE TABLE `org_assignments` (
  `asg_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `person_id` int NOT NULL COMMENT 'users.id — كنمط signing_authorities.person_id',
  `assignment_type_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `org_unit_id` int unsigned NOT NULL,
  `scope_type` enum('project','site','site_group') COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_id` int NOT NULL COMMENT 'المشروعُ أو الموقعُ أو مجموعةُ المواقع — ولا تكليفَ مفتوحُ النطاق',
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL COMMENT 'إلزاميٌّ — لا تكليفَ مفتوحُ المدة، وتمديدُه قرارٌ جديد',
  `decided_by_person_id` int NOT NULL COMMENT 'مصدرُ القرار: مديرُ التشغيل أو المديرُ التنفيذي',
  `decision_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deputy_person_id` int DEFAULT NULL COMMENT 'النائبُ المعتمَد — ولا نيابةَ شفويةٌ ولا مفتوحةُ المدة',
  `state` enum('active','suspended','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `active_site_mgr_key` varchar(80) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (if(((`assignment_type_code` = _utf8mb4'site_movement_mgr') and (`state` = _utf8mb4'active')),concat(`company_id`,_utf8mb4':',`scope_type`,_utf8mb4':',`scope_id`),NULL)) STORED COMMENT 'حيلةُ الفريد المشروط: NULL لغير مدير الحركة النشط — فينتج «واحدٌ نشطٌ لكل موقع»',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`asg_id`),
  UNIQUE KEY `uq_asg_natural` (`company_id`,`assignment_type_code`,`scope_type`,`scope_id`,`valid_from`),
  UNIQUE KEY `uq_one_active_movement_mgr` (`active_site_mgr_key`),
  KEY `idx_asg_person` (`person_id`,`state`),
  KEY `idx_asg_scope` (`company_id`,`scope_type`,`scope_id`,`state`),
  KEY `idx_asg_validity` (`state`,`valid_to`),
  KEY `fk_asg_type` (`assignment_type_code`),
  KEY `fk_asg_unit` (`org_unit_id`),
  CONSTRAINT `fk_asg_type` FOREIGN KEY (`assignment_type_code`) REFERENCES `org_assignment_types` (`type_code`),
  CONSTRAINT `fk_asg_unit` FOREIGN KEY (`org_unit_id`) REFERENCES `org_units` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §2/§7: التكليفُ سجلٌّ تنظيميٌّ بنطاقٍ ومدةٍ وسقفٍ ونائبٍ — ويسقط آليًّا بانتهائه';

-- ── Table: org_units ──
CREATE TABLE `org_units` (
  `unit_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `unit_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'رمزٌ ثابتٌ للوحدة تُخاطَب به برمجيًّا',
  `name_ar` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `layer` enum('operational','parallel','oversight') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الطبقة: تشغيليةٌ تحت مدير التشغيل · موازيةٌ تحت التنفيذي · رقابية',
  `parent_unit_id` int unsigned DEFAULT NULL,
  `owner_doc` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الوثيقةُ الحاكمة للوحدة',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`),
  UNIQUE KEY `uq_org_units_scope` (`company_id`,`unit_code`),
  KEY `idx_org_units_parent` (`parent_unit_id`),
  CONSTRAINT `fk_org_units_parent` FOREIGN KEY (`parent_unit_id`) REFERENCES `org_units` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: الوحداتُ التنظيمية — head_person_id مشتقٌّ من org_assignments (v_org_unit_heads) ولا يُكتب';

-- ── Table: ownership_access_grants ──
CREATE TABLE `ownership_access_grants` (
  `grant_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `person_id` int NOT NULL,
  `permission_code` enum('ownership.owner_view','ownership.finance_terms','ownership.purchase_value') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `granted_by` int NOT NULL,
  `state` enum('active','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `revoked_by` int DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`grant_id`),
  KEY `ix_oag_person` (`company_id`,`person_id`,`permission_code`,`state`),
  CONSTRAINT `ck_oag_value_strict` CHECK (((`permission_code` <> _utf8mb4'ownership.purchase_value') or ((`reason` is not null) and (`valid_from` is not null) and (`valid_to` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-21: الرؤية بأكواد فردية لا بالعضوية — وأشدها بمدة وسبب';

-- ── Table: pay_components ──
CREATE TABLE `pay_components` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزلٌ مباشر (سابقة claim_lines: يُقرأ مجمَّعًا بلا JOIN أبيه)',
  `contract_id` int NOT NULL,
  `component_type` enum('basic','cost_of_living','housing','transport','food','site','hazard','work_nature','shift','night','responsibility','supervision','assignment','travel','mission','communication','medical','fixed_bonus','other_allowance','custom') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'قائمةُ §3.2 العشرون نصًّا — لاتينيةً (گوتشا الترميز) والتعريبُ في الخدمة',
  `calc_method` enum('fixed_amount','pct_reference','pct_basic','pct_gross','per_day','per_shift','per_hour','per_unit','tiers','custom_formula') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'طرقُ الاحتساب العشر — §3.2',
  `value` decimal(18,2) DEFAULT NULL,
  `rate` decimal(12,2) DEFAULT NULL,
  `in_insurance` tinyint NOT NULL DEFAULT '0' COMMENT 'يدخل التأمينات؟',
  `in_tax` tinyint NOT NULL DEFAULT '0' COMMENT 'يدخل الضريبة؟',
  `in_leave_pay` tinyint NOT NULL DEFAULT '0' COMMENT 'يدخل أجرَ الإجازة؟',
  `in_eos` tinyint NOT NULL DEFAULT '0' COMMENT 'يدخل نهايةَ الخدمة؟',
  `in_hour_base` tinyint NOT NULL DEFAULT '0' COMMENT 'يدخل حسابَ الساعة؟',
  `in_overtime` tinyint NOT NULL DEFAULT '0' COMMENT 'يدخل العملَ الإضافي؟',
  `in_incentive_base` tinyint NOT NULL DEFAULT '0' COMMENT 'يدخل وعاءَ الحافز؟',
  `is_variable` tinyint NOT NULL DEFAULT '0' COMMENT 'ثابتٌ أم متغير',
  `periodicity` enum('monthly','periodic','once') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `cost_bearer_type` enum('project','client_contract','dept','company') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'إشارةُ المكوّن المفردة — شجرةُ Σ=100 (cost_bearers) بيتُها الشريحة ④',
  `cost_bearer_id` int DEFAULT NULL,
  `cost_center_id` int DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','replaced','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'حالاتُ سياسة الأجر — التصريحُ الكامل مع E-24/H-10',
  `created_by` int DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pc_contract` (`contract_id`),
  KEY `ix_pc_company` (`company_id`),
  KEY `fk_pc_cost_center` (`cost_center_id`),
  CONSTRAINT `fk_pc_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`),
  CONSTRAINT `fk_pc_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `fin_cost_centers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: pay_models ──
CREATE TABLE `pay_models` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_ar` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `calc_path` enum('time','production','mixed','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `is_active` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_model_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_absence_types ──
CREATE TABLE `payroll_absence_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `event_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'يطابق worker_leave_absence.event_type حرفيًّا',
  `code` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'WRK-01 §3: رمز الحالة (1·0·10·11·ST·S·M·A1·A2·EM·UP)',
  `deducts` tinyint NOT NULL DEFAULT '0' COMMENT '1 = غيابٌ يُخصم · 0 = إجازةٌ مدفوعة',
  `deduct_percent` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT 'نسبةُ الخصم من أجر اليوم',
  `label_ar` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `pay_effect` enum('full','none','per_contract','per_policy','stops_accrual','per_hr','deduct_daily') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'أثر الراتب',
  `incentive_base` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'يدخل أساس الحافز؟',
  `presence` enum('site','off','transit','mission') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'التواجد',
  `billable` enum('yes','no','by_attribution') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الفوترة — ST بالإسناد',
  `supplier_due` enum('yes','no','by_attribution','per_contract') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'استحقاق المورد',
  `conduct_violation` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'A2: مخالفة سلوكية تُسجَّل — أثر ثانٍ مستقل',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_absence_type` (`company_id`,`event_type`),
  UNIQUE KEY `uq_absence_code` (`company_id`,`code`),
  CONSTRAINT `ck_absence_pct` CHECK (((`deduct_percent` >= 0) and (`deduct_percent` <= 100)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_deductions ──
CREATE TABLE `payroll_deductions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `run_id` int NOT NULL,
  `person_id` int NOT NULL,
  `source_type` enum('advance','on_behalf','penalty','absence','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` int NOT NULL COMMENT 'مرجعُ المصدر — 0 مرفوضٌ بالقيد',
  `amount` decimal(18,2) NOT NULL COMMENT 'المخصومُ فعلًا في هذه الدورة (موجبٌ)',
  `requested_amount` decimal(18,2) DEFAULT NULL COMMENT 'القسطُ المستحقُّ قبل حدِّ الحماية',
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '«ولا خصمَ بلا مستند» — إلزاميٌّ بنيويًّا',
  `rescheduled` tinyint NOT NULL DEFAULT '0' COMMENT '1 = قُصّ بحدِّ الحماية ورُحّل باقيه',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_deduction` (`run_id`,`person_id`,`source_type`,`source_id`),
  KEY `ix_deduction_run` (`run_id`),
  CONSTRAINT `fk_deduction_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_deduction_amount` CHECK ((`amount` >= 0)),
  CONSTRAINT `ck_deduction_doc` CHECK ((char_length(trim(`doc_ref`)) > 0)),
  CONSTRAINT `ck_deduction_src` CHECK ((`source_id` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_lines ──
CREATE TABLE `payroll_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `run_id` int NOT NULL,
  `person_id` int NOT NULL COMMENT 'employees.id — «العقدُ يشير إلى سجل الأشخاص»',
  `contract_id` int NOT NULL,
  `snapshot_id` int NOT NULL COMMENT '**البوابة**: لا سطرَ احتسابٍ بلا لقطته (ENT-01 §2)',
  `path` enum('institutional','project') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'institutional' COMMENT 'مسارا §3',
  `component_ref` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'component#N أو rule#N — مرجعُه داخل اللقطة',
  `line_kind` enum('component','overtime','absence_deduction','production','incentive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'component' COMMENT 'نوعُ السطر — production/incentive مولَّدا المسار الإنتاجي (H-09-③)',
  `component_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calc_method` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(18,2) DEFAULT NULL,
  `entitled_days` decimal(6,2) DEFAULT NULL COMMENT 'أيامُ الاستحقاق في الفترة',
  `period_days` decimal(6,2) DEFAULT NULL COMMENT 'أيامُ الفترة كاملةً',
  `rate` decimal(18,4) DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT NULL COMMENT 'NULL = لم يُحتسب بعد (بحالته وسببه) — لا صفرَ ملفَّق',
  `unit_record_id` int DEFAULT NULL COMMENT 'للمسار التشغيلي — الشريحة ③',
  `bearer_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'جهةُ التحمّل من اللقطة',
  `bearer_id` int DEFAULT NULL,
  `percent` decimal(6,2) DEFAULT NULL COMMENT 'نسبةُ الجهة — Σ لكل مكوّنٍ = 100',
  `calc_state` enum('computed','pending_slice','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'computed',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_payroll_line_run_person` (`run_id`,`person_id`),
  KEY `ix_payroll_line_snapshot` (`snapshot_id`),
  CONSTRAINT `fk_payroll_line_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_line_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `contract_snapshots` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_run_blocks ──
CREATE TABLE `payroll_run_blocks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `run_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `person_id` int DEFAULT NULL,
  `kind` enum('excluded','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blocked' COMMENT 'excluded = خارج النطاق بسببٍ مكتوب · blocked = عطبٌ يوقف الدورة',
  `block_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'snapshot_missing · contract_not_readable · bearer_sum_invalid …',
  `block_http` smallint NOT NULL DEFAULT '422',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_block` (`run_id`,`contract_id`,`block_code`),
  KEY `ix_payroll_block_run` (`run_id`),
  CONSTRAINT `fk_payroll_block_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_runs ──
CREATE TABLE `payroll_runs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `category_filter` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all' COMMENT 'فئةُ CON-01 §2 أو all — جزءٌ من المفتاح الفريد فلا تُخلط الدورات',
  `project_filter` int DEFAULT NULL,
  `state` enum('Open','Calculated','Blocked','Review','Approved','Paid','Closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open' COMMENT 'دورةُ ENT-01 §8 السباعية نصًّا',
  `persons_count` int NOT NULL DEFAULT '0',
  `lines_count` int NOT NULL DEFAULT '0',
  `blocked_count` int NOT NULL DEFAULT '0',
  `gross_total` decimal(18,2) DEFAULT NULL COMMENT 'NULL = لم يكتمل الاحتساب (الشريحتان ②③)',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int NOT NULL DEFAULT '1',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_run_key` (`company_id`,`period_from`,`period_to`,`category_filter`),
  KEY `ix_payroll_run_state` (`company_id`,`state`),
  CONSTRAINT `ck_payroll_run_period` CHECK ((`period_to` >= `period_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_settings ──
CREATE TABLE `payroll_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `protection_percent` decimal(5,2) DEFAULT NULL COMMENT 'أدنى نسبةٍ من الإجمالي تبقى للعامل — NULL = لم يُقرَّر بعد',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_settings_co` (`company_id`),
  CONSTRAINT `ck_protection_pct` CHECK (((`protection_percent` is null) or ((`protection_percent` >= 0) and (`protection_percent` <= 100))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_time_inputs ──
CREATE TABLE `payroll_time_inputs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `run_id` int NOT NULL,
  `person_id` int NOT NULL,
  `kind` enum('overtime_hours','unpaid_days','night_shifts') COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مرجعُ المستند — إلزاميٌّ بنيويًّا',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_time_input` (`run_id`,`person_id`,`kind`),
  KEY `ix_time_input_co` (`company_id`,`run_id`),
  CONSTRAINT `fk_time_input_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_time_input_doc` CHECK ((char_length(trim(`doc_ref`)) > 0)),
  CONSTRAINT `ck_time_input_qty` CHECK ((`qty` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: perm_shadow_diffs ──
CREATE TABLE `perm_shadow_diffs` (
  `diff_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `user_id` int NOT NULL,
  `module_code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_rule` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `legacy_decision` tinyint(1) NOT NULL,
  `derived_decision` tinyint(1) NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'حُقق وأُصلح سببه (قالب أو تحويل)',
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`diff_id`),
  KEY `idx_psd_at` (`at`),
  KEY `idx_psd_user` (`company_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §13 المرحلة ③: كل فرق سماح/منع/نطاق/سقف صف — والحد صفر لا نسبة';

-- ── Table: permission_approval_steps ──
CREATE TABLE `permission_approval_steps` (
  `st_id` int unsigned NOT NULL AUTO_INCREMENT,
  `req_id` int unsigned NOT NULL,
  `seq_no` int NOT NULL,
  `approver_rule` enum('hr','functional_owner','requester_department_manager','finance_owner_if_financial','security_manager','executive') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'قاعدة ديناميكية لا دور ثابت — functional_owner يُحل من ORG-01 بحسب المجال والنطاق والتاريخ',
  `mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `approver_person_id` int DEFAULT NULL COMMENT 'يُحل لحظة الفتح',
  `auth_id` int unsigned DEFAULT NULL,
  `decision` enum('approve','reject') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `at` datetime DEFAULT NULL,
  PRIMARY KEY (`st_id`),
  UNIQUE KEY `uq_step` (`req_id`,`seq_no`),
  CONSTRAINT `fk_step_req` FOREIGN KEY (`req_id`) REFERENCES `permission_change_requests` (`req_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: لا تُفتح خطوة قبل سابقتها — يحرسه PermissionChangeWorkflow';

-- ── Table: permission_audit_events ──
CREATE TABLE `permission_audit_events` (
  `ev_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `event_type` enum('granted','elevated','reduced','revoked','expired','suspended','break_glass') COLLATE utf8mb4_unicode_ci NOT NULL,
  `person_id` int NOT NULL,
  `permission_code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_rule` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `before_json` json DEFAULT NULL,
  `after_json` json DEFAULT NULL,
  `requested_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `executed_by` int DEFAULT NULL,
  `request_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` enum('template','assignment','exception','grant','break_glass') COLLATE utf8mb4_unicode_ci NOT NULL,
  `founding_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'وسم أفعال التأسيس §7-④',
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ev_id`),
  KEY `idx_pae_person` (`company_id`,`person_id`,`at`),
  KEY `idx_pae_type` (`event_type`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: لا يُعدَّل ولا يُحذف — ولا يُخلط بمراجعة المدير الدورية';

-- ── Table: permission_change_requests ──
CREATE TABLE `permission_change_requests` (
  `req_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `person_id` int NOT NULL,
  `change_kind` enum('within_role','supervisor','section_mgr','dept_mgr_or_high') COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_json` json DEFAULT NULL,
  `to_json` json DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risk_level` enum('low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'محسوب من النوع',
  `state` enum('draft','pending','approved','rejected','applied') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`req_id`),
  KEY `idx_pcr_state` (`company_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §8: الموافقات بدرجة المخاطرة لا بمسار واحد';

-- ── Table: permission_exceptions ──
CREATE TABLE `permission_exceptions` (
  `ex_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `person_id` int NOT NULL,
  `permission_code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_rule` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `effect` enum('grant','deny') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'grant',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valid_from` datetime NOT NULL,
  `valid_to` datetime NOT NULL COMMENT 'إلزامي — ويسقط آليًّا',
  `is_break_glass` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'كسر الزجاج: مدة ≤ 24 ساعة بمراجعة لاحقة إلزامية',
  `approvals_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('active','expired','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ex_id`),
  KEY `idx_ex_person` (`company_id`,`person_id`,`state`),
  KEY `idx_ex_expiry` (`state`,`valid_to`),
  CONSTRAINT `chk_bg_24h` CHECK (((`is_break_glass` = 0) or (timestampdiff(HOUR,`valid_from`,`valid_to`) <= 24)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §8⑥⑦ — والسلفان exception_requests/approvals يبقيان لمسار GOV-01 §7 (0 صف فلا ترحيل)';

-- ── Table: permission_review_cycles ──
CREATE TABLE `permission_review_cycles` (
  `cycle_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `org_unit_id` int unsigned NOT NULL,
  `period` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مثال 2026-H2',
  `manager_person_id` int NOT NULL,
  `due_at` date NOT NULL,
  `state` enum('open','signed','escalated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `signed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`cycle_id`),
  UNIQUE KEY `uq_prc` (`org_unit_id`,`period`),
  KEY `idx_prc_due` (`state`,`due_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §10⑥: ما لم يُوقَّع خلال مهلته يُصعَّد للإدارة العامة';

-- ── Table: permission_review_lines ──
CREATE TABLE `permission_review_lines` (
  `line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` int unsigned NOT NULL,
  `person_id` int NOT NULL,
  `permission_code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_rule` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision` enum('confirm','reduce','revoke') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`line_id`),
  KEY `idx_prl_cycle` (`cycle_id`,`person_id`),
  CONSTRAINT `fk_prl_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `permission_review_cycles` (`cycle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: سطر لكل (موظف × صلاحية) — Insert-only';

-- ── Table: permission_template_versions ──
CREATE TABLE `permission_template_versions` (
  `ver_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tpl_id` int unsigned NOT NULL,
  `version` int NOT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `state` enum('draft','tested','published','superseded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approval_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `impact_preview_json` json DEFAULT NULL COMMENT 'أثر التغيير قبل النشر: كم مستخدمًا وأي صلاحية',
  `superseded_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ver_id`),
  UNIQUE KEY `uq_ver` (`tpl_id`,`version`),
  CONSTRAINT `fk_ver_tpl` FOREIGN KEY (`tpl_id`) REFERENCES `permission_templates` (`tpl_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §4⑥: لا يُعدل إصدار نافذ بأثر رجعي — النشر إصدار جديد بسريان مستقبلي';

-- ── Table: permission_templates ──
CREATE TABLE `permission_templates` (
  `tpl_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tpl_kind` enum('relation','family','level','title','assignment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_ceiling` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'لقوالب العلاقة: سقف لا أرضية',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`tpl_id`),
  UNIQUE KEY `uq_tpl_kind_key` (`tpl_kind`,`key_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §4: القالب جدول يُعدل بقرار لا كود يُبرمج';

-- ── Table: permit_approval_actions ──
CREATE TABLE `permit_approval_actions` (
  `act_id` int unsigned NOT NULL AUTO_INCREMENT,
  `req_id` int unsigned NOT NULL,
  `rq_id` int unsigned NOT NULL,
  `approver_person_id` int NOT NULL,
  `auth_id` int unsigned DEFAULT NULL COMMENT 'مرجعُ التفويض signing_authorities — LEG-01 §4',
  `decision` enum('approve','reject') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`act_id`),
  UNIQUE KEY `uq_act_step` (`req_id`,`rq_id`),
  KEY `fk_permit_act_rq` (`rq_id`),
  CONSTRAINT `fk_permit_act_req` FOREIGN KEY (`req_id`) REFERENCES `permit_requests` (`req_id`),
  CONSTRAINT `fk_permit_act_rq` FOREIGN KEY (`rq_id`) REFERENCES `permit_required_approvals` (`rq_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: قيدُ التسلسل «لا تُفتح خطوةٌ قبل اكتمال ما قبلها» يحرسه PermitGate بـ409';

-- ── Table: permit_requests ──
CREATE TABLE `permit_requests` (
  `req_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `permit_type_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مرجعُ الموضوع: معدةٌ أو مادةٌ أو شخصٌ أو فني',
  `site_id` int NOT NULL,
  `requested_by` int NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('draft','pending','approved','rejected','expired','used') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `valid_until` datetime DEFAULT NULL COMMENT 'يُحسب من validity_hours عند اكتمال الموافقات — بساعة القاعدة',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`req_id`),
  KEY `idx_permit_state_site` (`state`,`site_id`),
  KEY `idx_permit_company` (`company_id`,`state`),
  KEY `fk_preq_type` (`permit_type_code`),
  CONSTRAINT `fk_preq_type` FOREIGN KEY (`permit_type_code`) REFERENCES `permit_types` (`permit_type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: طلبُ الإذن — يمرّ بصندوق الاعتماد الجامع بندًا واحدًا لكل موافقٍ في دوره';

-- ── Table: permit_required_approvals ──
CREATE TABLE `permit_required_approvals` (
  `rq_id` int unsigned NOT NULL AUTO_INCREMENT,
  `permit_type_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seq_no` int NOT NULL,
  `approver_role` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'المجالُ الوظيفيُّ الموافق — يحلُّه PermitGate من التكليفات النافذة',
  `mandatory` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`rq_id`),
  UNIQUE KEY `uq_rq_seq` (`permit_type_code`,`seq_no`),
  CONSTRAINT `fk_permit_rq_type` FOREIGN KEY (`permit_type_code`) REFERENCES `permit_types` (`permit_type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §5/§7: مصفوفةُ الموافقات المشتركة — يُقرأ منها من يوافق وبأي ترتيب';

-- ── Table: permit_status_history ──
CREATE TABLE `permit_status_history` (
  `hist_id` int unsigned NOT NULL AUTO_INCREMENT,
  `req_id` int unsigned NOT NULL,
  `from_state` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_state` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `by_person_id` int NOT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`hist_id`),
  KEY `idx_hist_req` (`req_id`,`at`),
  CONSTRAINT `fk_permit_hist_req` FOREIGN KEY (`req_id`) REFERENCES `permit_requests` (`req_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: تاريخُ حالات الإذن — للإدراج فقط';

-- ── Table: permit_types ──
CREATE TABLE `permit_types` (
  `permit_type_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_kind` enum('equipment','material','person','technician') COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('in','out','activate','deactivate') COLLATE utf8mb4_unicode_ci NOT NULL,
  `validity_hours` int NOT NULL DEFAULT '24',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`permit_type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §5/§7: الأنواعُ التسعةُ صفوفٌ هنا لا كودًا';

-- ── Table: person_positions ──
CREATE TABLE `person_positions` (
  `p_id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `company_id` int NOT NULL,
  `relation_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `family_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ولا موظف بلا عائلة (DEC-SEC-F)',
  `level_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'job_titles.title_code',
  `org_unit_id` int unsigned DEFAULT NULL,
  `manager_person_id` int unsigned DEFAULT NULL,
  `scope_type` enum('company','department','section','unit','project','site','site_group','shift','own_records') COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_id` int NOT NULL COMMENT 'قيد: لا صف بلا نطاق — الصلاحية بلا نطاق مرفوضة بنيويًّا',
  `is_primary` tinyint(1) NOT NULL DEFAULT '1',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','suspended','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`p_id`),
  UNIQUE KEY `uq_pp_natural` (`person_id`,`title_code`,`scope_type`,`scope_id`,`valid_from`),
  KEY `idx_pp_person` (`person_id`,`state`),
  KEY `idx_pp_company` (`company_id`,`state`),
  KEY `fk_pp_relation` (`relation_code`),
  KEY `fk_pp_family` (`family_code`),
  KEY `fk_pp_level` (`level_code`),
  CONSTRAINT `fk_pp_family` FOREIGN KEY (`family_code`) REFERENCES `hr_dictionaries` (`code`),
  CONSTRAINT `fk_pp_level` FOREIGN KEY (`level_code`) REFERENCES `hr_dictionaries` (`code`),
  CONSTRAINT `fk_pp_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`person_id`),
  CONSTRAINT `fk_pp_relation` FOREIGN KEY (`relation_code`) REFERENCES `hr_dictionaries` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: منع تداخل فترتين لنفس (المسمى×النطاق) يحرسه PositionService — ومركزان مشروعان يبدآن معًا مقبولان';

-- ── Table: person_relationships ──
CREATE TABLE `person_relationships` (
  `rel_id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `company_id` int NOT NULL COMMENT 'الكيان — والعزل يمنع تسرب كيان إلى آخر ولو كان الشخص واحدًا',
  `relation_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'hr_dictionaries layer=relation',
  `employee_id` int DEFAULT NULL COMMENT 'جسر صفوف employees — تبقى بياناتِ الموظف الإدارية لا الهوية',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL COMMENT 'NULL = علاقة قائمة (الدائم) — والمؤقتة بنهاية',
  `state` enum('active','suspended','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rel_id`),
  KEY `idx_prel_person` (`person_id`,`state`),
  KEY `idx_prel_company` (`company_id`,`relation_code`,`state`),
  CONSTRAINT `fk_prel_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §14②: موظف المورد لا يُنشأ له موظف داخلي وهمي';

-- ── Table: persons ──
CREATE TABLE `persons` (
  `person_id` int unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_ref` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع هوية — معرّف دائم لا يُعاد استعماله',
  `contact_json` json DEFAULT NULL,
  `docs_json` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`person_id`),
  UNIQUE KEY `uq_persons_national` (`national_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §14: سجل الإنسان — حساب واحد عبر المنصة والصفات متعددة';

-- ── Table: policy_rules ──
CREATE TABLE `policy_rules` (
  `rule_id` int unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` int unsigned NOT NULL,
  `rule_kind` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `formula_json` json DEFAULT NULL,
  `threshold` decimal(18,4) DEFAULT NULL,
  `cap` decimal(18,4) DEFAULT NULL,
  `periodicity` enum('daily','weekly','monthly') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  PRIMARY KEY (`rule_id`),
  KEY `ix_pr_policy` (`policy_id`,`rule_kind`),
  CONSTRAINT `fk_pr_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §2-②: قواعد الإدارة بمعادلاتها وسقوفها';

-- ── Table: portal_activity_log ──
CREATE TABLE `portal_activity_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `account_id` int NOT NULL,
  `capacity_id` int unsigned DEFAULT NULL,
  `action_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result` enum('ok','denied') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ok',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pal_account` (`account_id`,`at`),
  KEY `ix_pal_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='USR-01 §5 — سجلُّ النشاط: يراه صاحبُه والمدقّقُ وHR ولا يُعدَّل ولا يُحذف';

-- ── Table: portal_elements ──
CREATE TABLE `portal_elements` (
  `element_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_ar` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_doc` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الوثيقةُ المالكة (USR-01 · WSP-01 …)',
  `sensitivity` enum('normal','sensitive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `default_mode` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'closed',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`element_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ADM-01 §2 — قاموسُ عناصر البوابة: ما ليس فيه لا يُصيَّر أصلًا';

-- ── Table: positions ──
CREATE TABLE `positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم المنصب التنظيمي (مثال: مدير حركة موقع MB1)',
  `role_id` int NOT NULL COMMENT 'دور النظام الذي يمنحه المنصب — جسرٌ إلى roles.id لا بديل عنه',
  `job_title_id` int DEFAULT NULL COMMENT 'ربط اختياري بالمسمى الوظيفي HR (job_titles.id)',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_positions_company` (`company_id`),
  KEY `idx_positions_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K6/ADR-07: المناصب — طبقة الصلاحية على المنصب فوق الأدوار';

-- ── Table: pricelists ──
CREATE TABLE `pricelists` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `pricelist_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` enum('USD','SDG') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `revenue_model` enum('hourly','ton','meter') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `distance_factor` decimal(6,3) DEFAULT NULL,
  `shift_factor` decimal(6,3) DEFAULT NULL,
  `volume_factor` decimal(6,3) DEFAULT NULL,
  `duration_factor` decimal(6,3) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pricelists_company_code` (`company_id`,`pricelist_code`),
  KEY `idx_pl_scope` (`company_id`,`is_deleted`),
  KEY `idx_pl_model` (`revenue_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_custody ──
CREATE TABLE `proc_custody` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `issue_id` int DEFAULT NULL COMMENT 'proc_issue.id',
  `issue_line_id` int DEFAULT NULL COMMENT 'proc_issue_line.id',
  `item_id` int DEFAULT NULL,
  `item_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `holder_id` int DEFAULT NULL,
  `holder_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `maintenance_order_id` int DEFAULT NULL,
  `qty_issued` decimal(12,2) NOT NULL DEFAULT '0.00',
  `qty_returned` decimal(12,2) NOT NULL DEFAULT '0.00',
  `qty_consumed` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'المصروفة - المرتجعة',
  `state` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مصروفة' COMMENT 'مصروفة/إرجاع جزئي/مستهلكة/مُقفلة',
  `notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_custody_company_state` (`company_id`,`state`),
  KEY `idx_proc_custody_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_issue ──
CREATE TABLE `proc_issue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` int DEFAULT NULL,
  `holder_id` int DEFAULT NULL COMMENT 'المستلِم',
  `holder_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `equipment_id` int DEFAULT NULL COMMENT 'بُعد تكلفة',
  `project_id` int DEFAULT NULL COMMENT 'بُعد تكلفة',
  `maintenance_order_id` int DEFAULT NULL COMMENT 'mnt_order.id (بلا FK)',
  `maint_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'وقائية/تصحيحية/رأسمالية',
  `contract_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `charge_supplier_id` int DEFAULT NULL COMMENT 'مورّدُ الآليات (suppliers.id) الذي يُحمَّل ثمنَ الصرف — فارغٌ أي على الشركة',
  `total_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `state` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مسودة' COMMENT 'مسودة/محجوز/مصروف/محمَّل التكلفة',
  `notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_issue_company_state` (`company_id`,`state`),
  KEY `idx_proc_issue_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_issue_line ──
CREATE TABLE `proc_issue_line` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `issue_id` int NOT NULL,
  `item_id` int DEFAULT NULL,
  `item_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT '1.00',
  `unit_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_issline_issue` (`issue_id`),
  CONSTRAINT `fk_proc_issline_iss` FOREIGN KEY (`issue_id`) REFERENCES `proc_issue` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_item ──
CREATE TABLE `proc_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'فلاتر/زيوت وشحوم/إسبيرات/بطاريات/أسنان جردل/سيور',
  `material_nature` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'قابل للتخزين' COMMENT 'قابل للتخزين / غير قابل للتخزين / خدمة ومصنعيات',
  `uom` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'قطعة' COMMENT 'قطعة/لتر/كجم',
  `is_critical` tinyint(1) NOT NULL DEFAULT '0',
  `min_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `max_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `lead_time_days` int NOT NULL DEFAULT '0',
  `safety_stock` decimal(12,2) NOT NULL DEFAULT '0.00',
  `served_equipment_id` int DEFAULT NULL COMMENT 'equipments.id (بلا FK)',
  `served_category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_item_company` (`company_id`),
  KEY `idx_proc_item_critical` (`company_id`,`is_critical`),
  KEY `idx_proc_item_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_lookup ──
CREATE TABLE `proc_lookup` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'فئة صنف / وحدة قياس / نوع مخزن / طبيعة مادة',
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `extra` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_lookup_company_type` (`company_id`,`type`),
  KEY `idx_proc_lookup_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_order ──
CREATE TABLE `proc_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` int DEFAULT NULL COMMENT 'proc_supplier.id',
  `project_id` int DEFAULT NULL COMMENT 'البُعد المفقود — يُشتق من طلب الشراء (FES: المشروع إلزامي)',
  `request_id` int DEFAULT NULL COMMENT 'proc_request.id',
  `fin_approval_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع الاعتماد المالي (شرط الإصدار)',
  `op_classification` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'استهلاكية',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG' COMMENT 'SDG/USD',
  `fx_rate` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `payment_time` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'فوري' COMMENT 'فوري/مؤجل/آجل 30/60/90',
  `expected_receipt_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مخزن' COMMENT 'مخزن/مباشر للمعدة/مشروع/ورشة',
  `expected_delivery_date` date DEFAULT NULL COMMENT 'موعد التوريد المتفق — الإلزامي الثالث (§5.1)',
  `sent_at` datetime DEFAULT NULL COMMENT 'لحظة الإرسال للمورد (Approved→Sent §8.2)',
  `sent_by` int DEFAULT NULL COMMENT 'مُرسِل الأمر',
  `late_alerted_at` datetime DEFAULT NULL COMMENT 'آخر إنذار تأخّر توريد (Late بعدّاده §8.2)',
  `received_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'نسبة المستلَم — 100 = اكتمال',
  `first_receipt_at` datetime DEFAULT NULL COMMENT 'أول استلام (PartialReceived)',
  `final_receipt_at` datetime DEFAULT NULL COMMENT 'الاستلام النهائي — زنادُ الأثر المالي',
  `closed_at` datetime DEFAULT NULL COMMENT 'إقفال الأمر',
  `closed_by` int DEFAULT NULL COMMENT 'مُقفِل الأمر',
  `invoice_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم فاتورة المورد',
  `invoice_date` date DEFAULT NULL COMMENT 'تاريخ الفاتورة',
  `invoice_amount` decimal(18,2) DEFAULT NULL COMMENT 'قيمة الفاتورة (لمضاهاة الفرق)',
  `match_state` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unmatched' COMMENT 'unmatched·matched·var_pending·rejected (§8.2)',
  `matched_at` datetime DEFAULT NULL COMMENT 'لحظة المطابقة',
  `matched_by` int DEFAULT NULL COMMENT 'من طابق',
  `total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'المعادل الموحّد = total_amount × fx_rate (FES §3.3)',
  `tax_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'الضريبة — متطلبٌ نظامي (الدستور §8)',
  `due_date` date DEFAULT NULL COMMENT 'تاريخ استحقاق السداد (FES §3.1 فهرس الاستحقاق)',
  `event_id` int DEFAULT NULL COMMENT 'مرجع الحدث المالي المنشور — قراءةً بمرجعه (§5.1-③)',
  `state` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مسودة' COMMENT 'مسودة/مؤكَّد/استلام أولي/استلام نهائي/مطابَق/مغلق',
  `notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_order_company_state` (`company_id`,`state`),
  KEY `idx_proc_order_deleted` (`is_deleted`),
  KEY `ix_po_receipt` (`state`,`final_receipt_at`),
  KEY `ix_po_project` (`project_id`),
  KEY `ix_po_due` (`due_date`),
  KEY `ix_po_match` (`match_state`),
  KEY `ix_po_event` (`event_id`),
  KEY `ix_po_expected` (`expected_delivery_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_order_line ──
CREATE TABLE `proc_order_line` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `order_id` int NOT NULL,
  `item_id` int DEFAULT NULL,
  `item_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `op_classification` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_ordline_order` (`order_id`),
  CONSTRAINT `fk_proc_ordline_ord` FOREIGN KEY (`order_id`) REFERENCES `proc_order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_orderpoint ──
CREATE TABLE `proc_orderpoint` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `item_id` int NOT NULL COMMENT 'proc_item.id',
  `warehouse_id` int DEFAULT NULL COMMENT 'proc_warehouse.id',
  `min_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `max_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `trigger_qty` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'ROP - نقطة إعادة الطلب',
  `safety_stock` decimal(12,2) NOT NULL DEFAULT '0.00',
  `mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'يدوي' COMMENT 'تلقائي / يدوي',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_orderpoint_company` (`company_id`),
  KEY `idx_proc_orderpoint_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_receipt_custody ──
CREATE TABLE `proc_receipt_custody` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `holder_id` int DEFAULT NULL COMMENT 'المستلِم (users/employees.id بلا FK)',
  `holder_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'لقطة نصية للمستلِم',
  `receipt_date` date DEFAULT NULL,
  `supplier_id` int DEFAULT NULL COMMENT 'proc_supplier.id',
  `order_id` int DEFAULT NULL COMMENT 'proc_order.id',
  `receipt_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'عطبرة/موقع المورد/…',
  `expected_destination` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مخزن' COMMENT 'مخزن/ورشة/مشروع/معدة',
  `state` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مستلَمة' COMMENT 'مستلَمة/قيد الترحيل/مسلَّمة للوجهة',
  `notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_rc_company_state` (`company_id`,`state`),
  KEY `idx_proc_rc_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_receipt_line ──
CREATE TABLE `proc_receipt_line` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `custody_id` int NOT NULL,
  `item_id` int DEFAULT NULL,
  `item_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT '1.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_rcline_custody` (`custody_id`),
  CONSTRAINT `fk_proc_rcline_custody` FOREIGN KEY (`custody_id`) REFERENCES `proc_receipt_custody` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_request ──
CREATE TABLE `proc_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `need_source` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'نقص مخزون' COMMENT 'خطة وقائية/أمر صيانة/نقص مخزون/إعادة طلب',
  `source_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع المصدر (خطة/أمر/نقطة طلب)',
  `op_classification` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'استهلاكية' COMMENT 'وقائية/تصحيحية/رأسمالية/استهلاكية',
  `requesting_dept` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `priority` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'عادي' COMMENT 'عادي/عاجل/حرج',
  `fin_approval_state` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'بانتظار' COMMENT 'بانتظار/معتمد مالياً/مرفوض',
  `state` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مسودة' COMMENT 'مسودة/مقدَّم/اعتماد المشتريات/مراجعة مالية/معتمد مالياً/محوَّل لأمر شراء/مغلق/مرفوض',
  `notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_request_company_state` (`company_id`,`state`),
  KEY `idx_proc_request_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_request_line ──
CREATE TABLE `proc_request_line` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `request_id` int NOT NULL,
  `item_id` int DEFAULT NULL COMMENT 'proc_item.id (اختياري)',
  `item_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'لقطة نصية للصنف',
  `qty` decimal(12,2) NOT NULL DEFAULT '1.00',
  `op_classification` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'تصنيف على مستوى البند',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_reqline_request` (`request_id`),
  CONSTRAINT `fk_proc_reqline_req` FOREIGN KEY (`request_id`) REFERENCES `proc_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_stock_move ──
CREATE TABLE `proc_stock_move` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int DEFAULT NULL,
  `move_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'استلام/صرف/إرجاع/تحويل',
  `qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ref_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'proc_order/proc_issue/proc_receipt/يدوي',
  `ref_id` int DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moved_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_move_company` (`company_id`),
  KEY `idx_proc_move_item_wh` (`item_id`,`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_supplier ──
CREATE TABLE `proc_supplier` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `supply_role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'تشغيلي' COMMENT 'تشغيلي دائماً في هذه الوحدة',
  `dealing_nature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'قطع/زيوت/فلاتر/خدمات إصلاح',
  `contact_person` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payment_terms` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_supplier_company` (`company_id`),
  KEY `idx_proc_supplier_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_warehouse ──
CREATE TABLE `proc_warehouse` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مخزن' COMMENT 'مخزن / ورشة / مباشر للآلية',
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_wh_company` (`company_id`),
  KEY `idx_proc_wh_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: processed_operations ──
CREATE TABLE `processed_operations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `consumer` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم المستهلك (يوافق ems_event_consumers.consumer)',
  `doc_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نوع المستند المصدر (fin_unit_record · claim · …)',
  `doc_id` bigint unsigned NOT NULL COMMENT 'معرّف المستند المصدر',
  `effect_kind` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نوع الأثر المعالَج (revenue · supplier_due · …)',
  `event_id` bigint unsigned DEFAULT NULL COMMENT 'الحدث الذي حمل المعالجة (تتبع لا مفتاح)',
  `processed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_processed_op` (`consumer`,`doc_type`,`doc_id`,`effect_kind`),
  KEY `ix_po_doc` (`doc_type`,`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-06 ركن ③: عطالة المستهلك على مستوى (المستند × الأثر) — Insert-only';

-- ── Table: products ──
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `product_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_type` enum('خدمة','معدة','مادة') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'خدمة',
  `revenue_model` enum('hourly','ton','meter') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_uom` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `standard_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `currency` enum('USD','SDG') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_company_code` (`company_id`,`product_code`),
  KEY `idx_prod_scope` (`company_id`,`is_deleted`),
  KEY `idx_prod_model` (`revenue_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: project ──
CREATE TABLE `project` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `client_id` int DEFAULT NULL COMMENT '┘àÏ╣Ï▒┘ü Ïº┘äÏ╣┘à┘è┘ä ┘à┘å Ï¼Ï»┘ê┘ä clients',
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'كود المشروع',
  `mine_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'كود المنجم',
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الفئة',
  `sub_sector` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'القطاع الفرعي',
  `state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الولاية',
  `region` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'المنطقة',
  `nearest_market` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'أقرب سوق',
  `latitude` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'خط العرض',
  `longitude` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'خط الطول',
  `total` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL COMMENT 'معرف المستخدم المنشئ',
  `create_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_project_created_by` (`created_by`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_mine_code` (`mine_code`),
  KEY `idx_project_status_deleted` (`status`,`is_deleted`),
  CONSTRAINT `fk_project_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_project_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: quotations ──
CREATE TABLE `quotations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `quotation_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` int DEFAULT NULL,
  `opportunity_id` int unsigned DEFAULT NULL,
  `currency` enum('USD','SDG') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `amount_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `validity_date` date DEFAULT NULL,
  `payment_terms` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('مسودة','مقدم','مقبول','مرفوض') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مسودة',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quotations_company_code` (`company_id`,`quotation_code`),
  KEY `idx_quo_scope` (`company_id`,`is_deleted`),
  KEY `idx_quo_opp` (`opportunity_id`),
  KEY `idx_quo_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: readiness_lines ──
CREATE TABLE `readiness_lines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `readiness_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_ref` int NOT NULL,
  `name` enum('جاهزية الأسطول','جاهزية الموردين','جاهزية القوى','جاهزية التمويل','جاهزية الصيانة','جاهزية الموقع') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'جاهزية الأسطول',
  `source_ref` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `required` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `available` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('مجتاز','فجوة','قيد المعالجة') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'قيد المعالجة',
  `gap_note` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rdl_company_code` (`company_id`,`readiness_code`),
  KEY `idx_rdl_scope` (`company_id`,`is_deleted`),
  KEY `idx_rdl_contract` (`contract_ref`),
  KEY `idx_rdl_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='INJAZ-S05 §6.12 — بنود فحص الجاهزية الستة بحالاتها لكل عقد';

-- ── Table: report_role_permissions ──
CREATE TABLE `report_role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `report_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_report` (`role_id`,`report_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: rfq_awards ──
CREATE TABLE `rfq_awards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `rfq_id` int unsigned NOT NULL,
  `line_id` int unsigned NOT NULL,
  `supplier_id` int unsigned NOT NULL,
  `quote_id` int unsigned DEFAULT NULL COMMENT 'العرضُ الذي رُسي عليه — والسعرُ يُقرأ منه',
  `qty_awarded` decimal(16,2) NOT NULL,
  `unit_price` decimal(14,4) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'حجّةُ الاختيار حين لا يكون الأرخص',
  `awarded_by` int unsigned DEFAULT NULL,
  `awarded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_award` (`line_id`,`supplier_id`) COMMENT 'ترسيةٌ واحدةٌ لكل (بند × مورد)',
  KEY `ix_rfq_award` (`company_id`,`rfq_id`),
  CONSTRAINT `fk_rfq_award_line` FOREIGN KEY (`line_id`) REFERENCES `rfq_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_rfq_award_qty` CHECK ((`qty_awarded` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: rfq_lines ──
CREATE TABLE `rfq_lines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `rfq_id` int unsigned NOT NULL,
  `commitment_id` int unsigned NOT NULL COMMENT 'مصدرُ البند — «من الالتزامات اشتقاقًا»',
  `line_no` int NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_type` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_required` decimal(16,2) NOT NULL COMMENT 'من الالتزام — لا يُكتب بيد',
  `qty_awarded` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'عدّادُ المرسى — يُحرَس بـCHECK',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_line` (`rfq_id`,`commitment_id`) COMMENT 'التزامٌ واحدٌ = بندٌ واحدٌ في الطلب — لا اشتقاقَ مضاعف',
  KEY `ix_rfq_line` (`company_id`,`rfq_id`),
  CONSTRAINT `fk_rfq_line_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `supplier_rfqs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_rfq_line_award` CHECK (((`qty_awarded` >= 0) and (`qty_awarded` <= `qty_required`))),
  CONSTRAINT `ck_rfq_line_qty` CHECK ((`qty_required` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: rfq_quotes ──
CREATE TABLE `rfq_quotes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `rfq_id` int unsigned NOT NULL,
  `line_id` int unsigned NOT NULL,
  `supplier_id` int unsigned NOT NULL,
  `unit_price` decimal(14,4) NOT NULL COMMENT 'المعيارُ الأول: السعر',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `qty_offered` decimal(16,2) NOT NULL COMMENT 'ما يقدر عليه — قد يكون جزءًا من المطلوب',
  `readiness_days` int DEFAULT NULL COMMENT 'المعيارُ الثاني: الجاهزية (أيامًا)',
  `record_rating` decimal(4,2) DEFAULT NULL COMMENT 'المعيارُ الثالث: السجل — من M-17 لا من رأي',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_quote` (`line_id`,`supplier_id`) COMMENT 'عرضٌ واحدٌ لكل (بند × مورد) — والتعديلُ استبدالٌ لا تكديس',
  KEY `ix_rfq_quote` (`company_id`,`rfq_id`,`supplier_id`),
  CONSTRAINT `fk_rfq_quote_line` FOREIGN KEY (`line_id`) REFERENCES `rfq_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_rfq_quote_price` CHECK (((`unit_price` > 0) and (`qty_offered` > 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: role_permissions ──
CREATE TABLE `role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `module_id` int NOT NULL,
  `can_view` tinyint(1) DEFAULT '0',
  `can_add` tinyint(1) DEFAULT '0',
  `can_edit` tinyint(1) DEFAULT '0',
  `can_delete` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_id` (`role_id`,`module_id`),
  KEY `module_id` (`module_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: roles ──
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_role_id` int DEFAULT NULL,
  `level` int DEFAULT '1',
  `role_scope` enum('gloable','mine') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gloable',
  `status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_role_id` (`parent_role_id`),
  CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`parent_role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: schema_migrations ──
CREATE TABLE `schema_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum` char(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-1 لمحتوى الملف وقت التطبيق',
  `status` enum('applied','baseline','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` datetime NOT NULL,
  `execution_ms` int NOT NULL DEFAULT '0',
  `applied_by` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `error_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schema_migrations_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: seat_assignments ──
CREATE TABLE `seat_assignments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `container_id` int unsigned NOT NULL COMMENT 'حاوية المقعد (op_containers.level=معدة بseat_no)',
  `equipment_id` int unsigned NOT NULL COMMENT 'المعدة الفعلية الجالسة في المقعد',
  `date_from` date NOT NULL,
  `date_to` date DEFAULT NULL COMMENT 'NULL = جالسة حتى الآن',
  `replace_reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سبب الاستبدال — إلزامي لغير الأول (تحرسه الخدمة)',
  `assignment_role` enum('أساسي','احتياطي','مؤقت') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'أساسي' COMMENT 'صفة الإسناد',
  `drivers_count` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'عدد السائقين على المعدة في هذا المقعد',
  `drivers_json` json DEFAULT NULL COMMENT 'قائمة employee_id للسائقين — مراجع لا نسخ',
  `state` enum('active','ended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_sa_seat` (`company_id`,`container_id`,`date_from`),
  KEY `ix_sa_equipment` (`company_id`,`equipment_id`,`date_from`),
  KEY `fk_sa_container` (`container_id`),
  CONSTRAINT `fk_sa_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_sa_dates` CHECK (((`date_to` is null) or (`date_to` >= `date_from`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-11: تعاقب المعدات على المقعد التعاقدي — لا تداخل فترتين لمعدتين في مقعد (تحرسه الخدمة 409)';

-- ── Table: sensitive_access_grants ──
CREATE TABLE `sensitive_access_grants` (
  `gr_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `person_id` int NOT NULL,
  `domain` enum('ownership','financing','payroll','bank','medical','pricing') COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_rule` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'إلزامي',
  `approvals_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `granted_from` date NOT NULL,
  `review_due_at` date DEFAULT NULL,
  `renewal_policy` enum('periodic','on_role_change','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'periodic',
  `state` enum('active','suspended','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`gr_id`),
  KEY `idx_sag_person` (`company_id`,`person_id`,`state`),
  KEY `idx_sag_domain` (`domain`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §1.1②: دائم ما دامت الوظيفة قائمة · كل قراءة به تُسجَّل · ويُعرض في المراجعة الدورية';

-- ── Table: sensitive_field_policies ──
CREATE TABLE `sensitive_field_policies` (
  `pol_id` int unsigned NOT NULL AUTO_INCREMENT,
  `field_code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classification` enum('payroll','bank','medical','personal','ownership','pricing') COLLATE utf8mb4_unicode_ci NOT NULL,
  `masking_rule` enum('full','partial','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `allowed_roles_json` json DEFAULT NULL,
  PRIMARY KEY (`pol_id`),
  UNIQUE KEY `uq_sfp_field` (`field_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §10⑦: الحقل الذي لا يُملك لا يُجلب أصلًا';

-- ── Table: sensitive_read_log ──
CREATE TABLE `sensitive_read_log` (
  `read_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL COMMENT 'SEC-21: شركة السياق',
  `person_id` int NOT NULL,
  `element_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result` enum('allowed','denied') COLLATE utf8mb4_unicode_ci NOT NULL,
  `grant_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجع المنح المسوِّغ (GR-… · policy:…)',
  `context` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الشاشة أو الخدمة',
  PRIMARY KEY (`read_id`),
  KEY `ix_srl_person` (`person_id`,`at`),
  KEY `ix_srl_subject` (`subject_type`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §9: سجل اطلاع على الحقول الحساسة — Insert-only';

-- ── Table: settlement_lines ──
CREATE TABLE `settlement_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `settlement_id` int NOT NULL,
  `line_kind` enum('entitlement','charge') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'entitlement=مستحقٌّ له · charge=تحميلٌ عليه',
  `charge_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'للتحميل: fuel · parts · maintenance · transport · advance · penalty',
  `source_kind` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مصدرُ البند: due (دفتر الطرف) · parts (صرف) · maintenance (أمر)',
  `source_ref` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'معرّفُ الأصل — به يُفتح المستندُ الأصلي',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_date` date DEFAULT NULL COMMENT 'تاريخُ الواقعة — به يُختار سعرُ الصرف',
  `amount` decimal(18,2) NOT NULL COMMENT 'المبلغ بعملته (موجبٌ دائمًا — والاتجاهُ من line_kind)',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fx_rate` decimal(20,8) DEFAULT NULL,
  `base_amount` decimal(18,2) DEFAULT NULL,
  `objected` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'اعتراضُ الطرف — والتسويةُ لا تتجمد (§15.3)',
  `objection_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'السببُ إلزاميٌّ عند الاعتراض',
  `objected_by` int DEFAULT NULL,
  `objected_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL COMMENT 'حسمُ الاعتراض — بعده يعود البندُ محتسبًا',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_line_source` (`settlement_id`,`source_kind`,`source_ref`) COMMENT 'لا يُحمَّل مصدرٌ مرتين في التسوية الواحدة',
  KEY `ix_line_settlement` (`settlement_id`),
  KEY `ix_line_objected` (`objected`),
  CONSTRAINT `fk_line_settlement` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='بنودُ التسوية — كلُّ بندٍ برابط أصله (UX-05 §5.2)';

-- ── Table: settlements ──
CREATE TABLE `settlements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزل المستأجر',
  `settlement_no` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'STL-سنة-رقم',
  `party_type` enum('supplier','employee') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الخدمةُ واحدةٌ للطرفين (UX-02 §15.3) — والعاملُ توأمُ المورد',
  `party_ref` int NOT NULL COMMENT 'suppliers.id أو employees.id بحسب النوع',
  `party_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'لقطةُ الاسم وقتَ التوليد — للكشف التاريخي',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'عملةُ التسوية — كلُّ بنودها بها',
  `fx_rate` decimal(20,8) DEFAULT NULL COMMENT 'سعرُ الصرف لعملة الأساس (FES §3.3)',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'صافي التسوية بعملة الأساس — NULL أي بانتظار سعر',
  `gross_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'الاستحقاق الأولي (Σ البنود المستحقة)',
  `charges_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'الσ التحميلات (موجبةً)',
  `net_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT 'الصافي = الأولي − التحميلات (قد يكون سالبًا)',
  `net_direction` enum('payable','receivable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'payable' COMMENT 'payable=له علينا · receivable=علينا له دَينٌ (الصافي سالب — قرارُ المالك ①)',
  `state` enum('draft','review','approved','payment_requested','invoiced','paid','closed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'دورةُ ENT-02 §4 — وInvoiced/Closed أُضيفتا في M-13',
  `open_objections` int NOT NULL DEFAULT '0' COMMENT 'عدّادُ البنود المعترَض عليها المفتوحة (§15.3)',
  `payment_request_id` int DEFAULT NULL COMMENT 'طلبُ الدفع المولَّد آليًّا عند الاعتماد (§15.3)',
  `receivable_due_id` int DEFAULT NULL COMMENT 'الذمّةُ المدينة المولَّدة حين الصافي سالب',
  `event_id` int DEFAULT NULL COMMENT 'حدثُ FES بمفتاح رقمها (§15.3)',
  `prepared_by` int DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `invoice_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقمُ فاتورة المورد — مستندٌ ضريبيٌّ يُطابَق به لا مصدرُ اعتراف',
  `invoice_date` date DEFAULT NULL,
  `invoice_amount` decimal(18,2) DEFAULT NULL COMMENT 'مبلغُ الفاتورة كما ورد — لا يُعدَّل ولا يُعدِّل الصافي',
  `invoice_currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_diff` decimal(18,2) DEFAULT NULL COMMENT 'الفاتورة − الصافي المعتمد (موجبٌ = زيادةُ المورد)',
  `invoice_diff_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '**إلزاميٌّ متى وُجد فرق** — «فرقٌ بقرارٍ لا تعديلًا صامتًا»',
  `invoice_diff_doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoiced_by` int DEFAULT NULL,
  `invoiced_at` datetime DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settlement_no` (`company_id`,`settlement_no`),
  UNIQUE KEY `uq_settlement_party_period` (`company_id`,`party_type`,`party_ref`,`period_from`,`period_to`) COMMENT 'تسويةٌ واحدةٌ لكل (طرف × فترة) — إعادةُ التوليد ترجع 409 بمرجع القائم (§15.4)',
  KEY `ix_settlement_state` (`state`),
  KEY `ix_settlement_party` (`party_type`,`party_ref`),
  KEY `ix_settlement_invoice` (`company_id`,`party_ref`,`invoice_no`),
  CONSTRAINT `ck_settlement_invoice_diff` CHECK (((`invoice_diff` is null) or (abs(`invoice_diff`) < 0.005) or ((`invoice_diff_reason` is not null) and (char_length(trim(`invoice_diff_reason`)) > 0) and (`invoice_diff_doc_ref` is not null) and (char_length(trim(`invoice_diff_doc_ref`)) > 0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تسويةُ الطرف: الاستحقاق الأولي ← التحميلات ← الصافي (UX-02 §15.3 · UX-05 §2.2)';

-- ── Table: shift_patterns ──
CREATE TABLE `shift_patterns` (
  `pattern_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `name_ar` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shifts_per_day` tinyint unsigned NOT NULL DEFAULT '1',
  `base_hours` decimal(5,2) NOT NULL,
  `overtime_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `crosses_midnight` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`pattern_id`),
  UNIQUE KEY `uq_sp_name` (`company_id`,`name_ar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §2: أنماط الورديات — قاموس يُضاف إليه بقرار، والمواعيد معرَّفة لا مثبَّتة في الكود';

-- ── Table: shift_period_defs ──
CREATE TABLE `shift_period_defs` (
  `def_id` int unsigned NOT NULL AUTO_INCREMENT,
  `pattern_id` int unsigned NOT NULL,
  `shift_no` tinyint unsigned NOT NULL,
  `period_no` tinyint unsigned NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `base_hours` decimal(5,2) NOT NULL,
  `overtime_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`def_id`),
  UNIQUE KEY `uq_spd` (`pattern_id`,`shift_no`,`period_no`),
  CONSTRAINT `fk_spd_pattern` FOREIGN KEY (`pattern_id`) REFERENCES `shift_patterns` (`pattern_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §2.1: فترات النمط بمواعيدها وساعاتها الأساسية والإضافية';

-- ── Table: shift_period_logs ──
CREATE TABLE `shift_period_logs` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `work_date` date NOT NULL,
  `equipment_id` int NOT NULL,
  `shift_no` tinyint unsigned NOT NULL,
  `period_no` tinyint unsigned NOT NULL,
  `operator_person_id` int NOT NULL COMMENT 'مشغّل واحد لكل فترة إلزامًا — NOT NULL بنيوي',
  `qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `unit` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ton',
  `run_minutes` int unsigned NOT NULL DEFAULT '0',
  `standby_minutes` int unsigned NOT NULL DEFAULT '0',
  `stop_minutes` int unsigned NOT NULL DEFAULT '0',
  `stop_reason_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'من stop_reason_codes (N-12) — توقف بلا سبب 422 في الخدمة',
  `site_id` int DEFAULT NULL,
  `state` enum('logged','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'logged',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `synced_late` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'DEC-01 ⑨: مزامنة بعد أكثر من يوم من تاريخ العمل — يدخل السلسلة كأي صف ولا يُعتمد آليًّا',
  PRIMARY KEY (`log_id`),
  UNIQUE KEY `uq_spl_key` (`work_date`,`equipment_id`,`shift_no`,`period_no`) COMMENT 'مفتاح (معدة×تاريخ×وردية×فترة) — يمنع تكرار المزامنة (وشرط N-08)',
  KEY `ix_spl_operator` (`operator_person_id`,`work_date`),
  KEY `ix_spl_company` (`company_id`,`work_date`),
  KEY `fk_spl_reason` (`stop_reason_code`),
  CONSTRAINT `fk_spl_reason` FOREIGN KEY (`stop_reason_code`) REFERENCES `stop_reason_codes` (`code`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §2.1: سجل الفترة — وحدة الحقيقة؛ المعدة ثابتة للوردية والمشغّل يتغير بالفترة';

-- ── Table: signing_authorities ──
CREATE TABLE `signing_authorities` (
  `auth_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `person_id` int NOT NULL COMMENT 'users.id',
  `entity_id` int unsigned NOT NULL COMMENT 'الكيان المفوِّض — التفويض بالصفة والكيان معًا',
  `capacity_id` int DEFAULT NULL COMMENT 'user_capacities.id — الصفة (H-15)',
  `auth_type` enum('general','financial','contractual','banking','operational') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `amount_cap` decimal(18,2) DEFAULT NULL COMMENT 'السقف المالي — NULL = بلا سقف (تفويض عام بقرار)',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'project · department · doc_type',
  `scope_id` int DEFAULT NULL,
  `joint_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'التوقيع المشترك — مطفأ في النمط ①',
  `delegated_from_auth_id` int unsigned DEFAULT NULL COMMENT 'DEC-01 ①: نيابة — مرجع تفويض الأصيل؛ النائب بمدة مكتوبة إلزامًا (تحرسه الخدمة)',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL COMMENT 'ينتهي بانتهاء مدته آليًّا — الحارس يقرأ التاريخ',
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('active','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`auth_id`),
  KEY `ix_sa_person` (`person_id`,`entity_id`,`state`),
  KEY `ix_sa_expiry` (`valid_to`),
  KEY `fk_sa_entity` (`entity_id`),
  KEY `ix_sa_delegated` (`delegated_from_auth_id`),
  CONSTRAINT `fk_sa_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §4: التفويض بالتوقيع — لا اعتماد بلا تفويض نافذ ساري';

-- ── Table: sites ──
CREATE TABLE `sites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `project_id` int NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `site_kind` enum('mine','site') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'site' COMMENT 'H-05: «المنجمُ حالةٌ من الموقع لا فرقَ في المعالجة» — تمييزٌ عرضيٌّ؛ التعريب في الشاشة',
  `responsible_employee_id` int DEFAULT NULL COMMENT 'مسؤولُ الموقع — مدخلُ E-07/H-03',
  `location_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `is_default` tinyint NOT NULL DEFAULT '0' COMMENT 'موقعُ الترحيل الرجعي: المشروعُ كان الموقعَ ضمنًا',
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_name` (`company_id`,`project_id`,`name`),
  KEY `ix_sites_project` (`project_id`),
  KEY `ix_sites_company` (`company_id`),
  KEY `fk_sites_resp` (`responsible_employee_id`),
  CONSTRAINT `fk_sites_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`),
  CONSTRAINT `fk_sites_resp` FOREIGN KEY (`responsible_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: sod_conflicts ──
CREATE TABLE `sod_conflicts` (
  `sod_id` int unsigned NOT NULL AUTO_INCREMENT,
  `conflict_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_a` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_b` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'high',
  `compensating_control` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الاستثناء بموافقة التنفيذي ورقابة تعويضية معلنة — ولا يُمنح صامتًا',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`sod_id`),
  UNIQUE KEY `uq_sod_code` (`conflict_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §5: الثمانية صفوف هنا — 409 مع عرض التعارض';

-- ── Table: stop_reason_codes ──
CREATE TABLE `stop_reason_codes` (
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `obligation_type` enum('fuel','access_road','loading_equipment','equipment_readiness','operators','permits_safety','utilities','catering_camp','force_majeure') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'بند الالتزام المقابل الافتراضي — NULL لسبب «أخرى» فيُلزم ببند صريح عند الإدخال',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-12: أسباب التعطل الستة — قائمة محكومة لا نص حر، وكل سبب ببنده المقابل';

-- ── Table: super_admin_password_resets ──
CREATE TABLE `super_admin_password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `super_admin_id` int NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_super_admin_password_resets_token_hash` (`token_hash`),
  KEY `idx_super_admin_password_resets_admin_id` (`super_admin_id`),
  CONSTRAINT `fk_super_admin_password_resets_admin` FOREIGN KEY (`super_admin_id`) REFERENCES `super_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: super_admins ──
CREATE TABLE `super_admins` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الإسم',
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'البريد ',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'كلمة المرور',
  `is_active` tinyint NOT NULL DEFAULT '1' COMMENT 'نشط',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT 'آخر دخول',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'انشاء في',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'تعديل في',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_advance_recoveries ──
CREATE TABLE `supplier_advance_recoveries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `advance_id` int NOT NULL,
  `settlement_id` int NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'يرث سندَ سلفته — لا استردادَ يتيم',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sadv_recovery` (`advance_id`,`settlement_id`),
  KEY `ix_sadv_rec_settlement` (`settlement_id`),
  CONSTRAINT `fk_sadv_rec_advance` FOREIGN KEY (`advance_id`) REFERENCES `supplier_advance_requests` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_sadv_rec_amount` CHECK ((`amount` > 0)),
  CONSTRAINT `ck_sadv_rec_doc` CHECK ((char_length(trim(`doc_ref`)) > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_advance_requests ──
CREATE TABLE `supplier_advance_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `supplier_contract_id` int DEFAULT NULL COMMENT 'عقدُ المورد إن خُصّصت به (H-07)',
  `advance_type` enum('cash','on_behalf','custody') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash' COMMENT 'نقدًا · نيابةً عنه · **عهدةً** — قائمةُ §3 نصًّا',
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'سندُ الصرف — إلزاميٌّ بنيويًّا («ما لا مستندَ له لا يُحمَّل»)',
  `issued_date` date NOT NULL,
  `installments_count` int NOT NULL DEFAULT '1',
  `installment_amount` decimal(18,2) NOT NULL COMMENT 'قسطُ التصفية الواحدة',
  `first_recovery_period` date DEFAULT NULL,
  `recovered` decimal(18,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(18,2) GENERATED ALWAYS AS ((`amount` - `recovered`)) STORED COMMENT '**مولَّد** — «ورصيدُها ظاهرٌ في بطاقته دائمًا» بلا انحراف',
  `state` enum('draft','approved','active','settled','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_sadv_supplier_state` (`supplier_id`,`state`),
  KEY `ix_sadv_co` (`company_id`,`state`),
  CONSTRAINT `fk_sadv_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_sadv_amount` CHECK ((`amount` > 0)),
  CONSTRAINT `ck_sadv_doc` CHECK ((char_length(trim(`doc_ref`)) > 0)),
  CONSTRAINT `ck_sadv_inst` CHECK (((`installments_count` >= 1) and (`installment_amount` > 0))),
  CONSTRAINT `ck_sadv_recovered` CHECK (((`recovered` >= 0) and (`recovered` <= `amount`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_capacity ──
CREATE TABLE `supplier_capacity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contract_id` int NOT NULL COMMENT 'عقدُ المورد (H-07) — «تُثبَّت في العقد»',
  `equipment_id` int NOT NULL,
  `work_model` enum('hour','ton','trip','meter') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hour' COMMENT 'نموذجُ الطاقة — «طاقةٌ نظريةٌ يوميةٌ **بنموذجها**»',
  `theoretical_daily` decimal(18,2) NOT NULL COMMENT 'الطاقةُ النظريةُ اليومية — ومنها يُقاس الأداءُ لا من تقديرٍ لاحق',
  `min_readiness_percent` decimal(5,2) DEFAULT NULL COMMENT 'الحدُّ التعاقديُّ للجاهزية — NULL = لم يُشترط (يُعلَن ولا يُفترض)',
  `replace_hours` int DEFAULT NULL COMMENT 'مهلةُ الإحلال بالساعات — وتجاوزُها يحوّل التوقفَ إلى عجزِ تغطية',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_capacity` (`contract_id`,`equipment_id`,`valid_from`),
  KEY `ix_sup_capacity_eq` (`company_id`,`equipment_id`,`state`),
  KEY `fk_sup_capacity_equipment` (`equipment_id`),
  CONSTRAINT `fk_sup_capacity_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sup_capacity_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_sup_capacity_daily` CHECK ((`theoretical_daily` > 0)),
  CONSTRAINT `ck_sup_capacity_readiness` CHECK (((`min_readiness_percent` is null) or ((`min_readiness_percent` > 0) and (`min_readiness_percent` <= 100)))),
  CONSTRAINT `ck_sup_capacity_replace` CHECK (((`replace_hours` is null) or (`replace_hours` > 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_charge_rules ──
CREATE TABLE `supplier_charge_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contract_id` int NOT NULL COMMENT 'عقدُ المورد الحديث (H-07)',
  `charge_type` enum('fuel','spares','maintenance','transport','operator_payroll','advance') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'التحميلاتُ الستُّ في §2-⑥ نصًّا',
  `pricing` enum('cost','cost_plus','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cost' COMMENT 'بسعر التكلفة · تكلفةٌ مضافةٌ بنسبتها · مبلغٌ ثابت',
  `rate` decimal(10,3) DEFAULT NULL COMMENT 'cost_plus = نسبةٌ مئوية · fixed = مبلغٌ للوحدة/الحدث',
  `cap` decimal(18,2) DEFAULT NULL COMMENT 'سقفُ التحميل الواحد — NULL = بلا سقفٍ مكتوب',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_charge_rule` (`contract_id`,`charge_type`,`valid_from`),
  KEY `ix_charge_rule_co` (`company_id`,`contract_id`,`state`),
  CONSTRAINT `fk_charge_rule_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_charge_rule_cap` CHECK (((`cap` is null) or (`cap` > 0))),
  CONSTRAINT `ck_charge_rule_rate` CHECK (((`pricing` = _utf8mb4'cost') or ((`rate` is not null) and (`rate` > 0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_contract_closures ──
CREATE TABLE `supplier_contract_closures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `state` enum('open','cleared','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `quota_open_count` int NOT NULL DEFAULT '0' COMMENT 'حاوياتٌ مفتوحةٌ عند آخر قياس',
  `quota_closed_at` datetime DEFAULT NULL,
  `quota_close_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سببُ إقفال حصةٍ لم تُستهلك — «ولا تجاوزَ صامتًا للسقف» ولا إقفالَ صامتٌ دونه',
  `advances_balance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `advances_settled_at` datetime DEFAULT NULL,
  `guarantee_amount` decimal(18,2) DEFAULT NULL COMMENT 'لقطةٌ من العقد وقت فتح التصفية',
  `guarantee_currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guarantee_due_date` date DEFAULT NULL COMMENT 'نهايةُ العقد + مهلةُ الردّ',
  `guarantee_released_at` datetime DEFAULT NULL,
  `guarantee_due_ref` int DEFAULT NULL COMMENT 'الذمّةُ الدائنةُ التي وُلّدت بالردّ — أثرٌ لا وعد',
  `clearance_doc` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجعُ شهادة الإخلاء الموثَّقة',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opened_by` int DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_closure` (`contract_id`) COMMENT 'تصفيةٌ واحدةٌ للعقد — «بمفتاح (العقد × التصفية)»',
  KEY `ix_sup_closure` (`company_id`,`supplier_id`,`state`),
  CONSTRAINT `fk_sup_closure_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_closure_doc` CHECK (((`state` <> _utf8mb4'closed') or ((`clearance_doc` is not null) and (`clearance_doc` <> _utf8mb4'')))),
  CONSTRAINT `ck_sup_closure_release` CHECK (((`guarantee_released_at` is null) or (`guarantee_due_ref` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_contract_lines ──
CREATE TABLE `supplier_contract_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contract_id` int NOT NULL COMMENT 'رأسُ عقد المورد — البندُ ابنُه',
  `work_model` enum('hour','ton','trip','meter') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نماذجُ §2-② الأربعة — ما خرج عنها 422',
  `unit` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'تسميةُ الوحدة كما يقرؤها محرّكُ الفوترة',
  `unit_price` decimal(18,2) NOT NULL COMMENT 'سعرُ الوحدة — ≤ 0 مرفوضٌ 422',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'عملةُ البند — الفارغُ يرتدّ لعملة الرأس (تناظرُ الموروث)',
  `standby_basis` enum('none','rate','percent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT '«أساسُ احتساب الاستعداد إن استُحق» — none = لا استعدادَ مشترطًا',
  `standby_rate` decimal(18,4) DEFAULT NULL COMMENT 'rate = معدلُ الساعة · percent = نسبةٌ من unit_price',
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','replaced','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `source_table` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` int DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_line_model_unit` (`contract_id`,`work_model`,`unit`),
  KEY `ix_sup_line_co` (`company_id`,`contract_id`),
  CONSTRAINT `fk_sup_line_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_line_price` CHECK ((`unit_price` > 0)),
  CONSTRAINT `ck_sup_line_standby` CHECK ((((`standby_basis` = _utf8mb4'none') and (`standby_rate` is null)) or ((`standby_basis` <> _utf8mb4'none') and (`standby_rate` is not null) and (`standby_rate` > 0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_contract_notes ──
CREATE TABLE `supplier_contract_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `contract_id` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`),
  KEY `fk_supplier_contract_notes_created_by` (`created_by`),
  CONSTRAINT `fk_supplier_contract_notes_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplierscontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_supplier_contract_notes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_contracts ──
CREATE TABLE `supplier_contracts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL COMMENT 'عزلُ المستأجر (TenantRegistry)',
  `supplier_id` int NOT NULL COMMENT 'الموردُ — شريكُ الطاقة داخل هرم الحصص',
  `client_contract_id` int DEFAULT NULL COMMENT 'وصلةُ L1 — عقدُ العميل الذي تُقتطع منه الحصة (CON-03 §1)',
  `project_id` int DEFAULT NULL COMMENT 'المشروعُ المشمول — يُقرأ ولا يُملك هنا',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رمزٌ لاتيني (USD·SDG·EUR·SAR) — التسميةُ العربية تبقى في المصدر',
  `performance_guarantee` decimal(18,2) DEFAULT NULL COMMENT 'ضمانُ الأداء — NULL = لم يُشترط (يُعلَن ولا يُفترض)',
  `guarantee_retention_days` int DEFAULT NULL COMMENT 'مهلةُ ردّ الضمان بالأيام بعد الانتهاء — «ردُّ الضمان **بعد مهلته**»',
  `advance_payment` decimal(18,2) DEFAULT NULL COMMENT 'الدفعةُ المقدمة — تُستهلك استقطاعًا في التصفية الدورية',
  `state` enum('مسودة','تفاوض','معتمد','موقَّع','نافذ','قيد التنفيذ','معلَّق','معدَّل','مجدَّد','منتهٍ','مقفل','مصفّى') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مسودة' COMMENT 'مفرداتُ ContractStateMachine نفسُها — لا قاموسَ ثانٍ',
  `version` int NOT NULL DEFAULT '1' COMMENT 'قفلٌ تفاؤلي — 409 عند الانحراف',
  `source_table` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'وصلةُ الترحيل — غيرُ الفارغ = مرحَّلٌ محصَّنٌ 423',
  `source_id` int DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0' COMMENT 'إخفاءٌ ناعم — لا حذفَ صلب',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_contract_party` (`supplier_id`,`client_contract_id`,`start_date`),
  UNIQUE KEY `uq_sup_contract_source` (`source_table`,`source_id`,`company_id`),
  KEY `ix_sup_contract_co_state` (`company_id`,`state`),
  KEY `ix_sup_contract_client` (`client_contract_id`),
  CONSTRAINT `fk_sup_contract_client` FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sup_contract_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_sup_advance_payment` CHECK (((`advance_payment` is null) or (`advance_payment` > 0))),
  CONSTRAINT `ck_sup_guarantee_amount` CHECK (((`performance_guarantee` is null) or (`performance_guarantee` > 0))),
  CONSTRAINT `ck_sup_guarantee_days` CHECK (((`performance_guarantee` is null) or ((`guarantee_retention_days` is not null) and (`guarantee_retention_days` > 0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_evaluation_lines ──
CREATE TABLE `supplier_evaluation_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `evaluation_id` int NOT NULL,
  `indicator` enum('readiness','coverage','attributed_stops','operator_quality','incidents') COLLATE utf8mb4_unicode_ci NOT NULL,
  `measurable` tinyint NOT NULL DEFAULT '1' COMMENT '0 = بلا مصدرٍ في الفترة — يُعلَن ولا يُقدَّر',
  `measured_value` decimal(18,2) DEFAULT NULL COMMENT 'القياسُ الخام كما قُرئ من السجل',
  `basis_value` decimal(18,2) DEFAULT NULL COMMENT 'الأساسُ الذي قُسم عليه (زمنٌ مخططٌ · مقياسٌ مكتوب)',
  `ratio` decimal(6,4) DEFAULT NULL COMMENT 'نسبةُ الإجادة (0..1) — الأعلى أفضل',
  `weight` decimal(5,2) NOT NULL,
  `earned` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'الوزنُ × النسبة',
  `source_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مصدرُ الرقم بلغة المهمة — لا رقمَ بلا مصدر',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_eval_line` (`evaluation_id`,`indicator`),
  CONSTRAINT `fk_sup_eval_line` FOREIGN KEY (`evaluation_id`) REFERENCES `supplier_evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_eval_ratio` CHECK (((`ratio` is null) or ((`ratio` >= 0) and (`ratio` <= 1))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_evaluation_weights ──
CREATE TABLE `supplier_evaluation_weights` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `indicator` enum('readiness','coverage','attributed_stops','operator_quality','incidents') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مؤشراتُ §4-التقييم الخمسةُ نصًّا',
  `weight` decimal(5,2) NOT NULL COMMENT 'وزنُ المؤشر — وΣ الأوزان = 100 (تفرضه الخدمة)',
  `scale_max` decimal(10,2) DEFAULT NULL COMMENT 'مقياسُ المؤشرات العددية (الحوادث): العددُ الذي تبلغ عنده النتيجةُ صفرًا — NULL = بلا مقياسٍ مكتوب فلا يُقاس',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_eval_weight` (`company_id`,`indicator`),
  CONSTRAINT `ck_sup_eval_scale` CHECK (((`scale_max` is null) or (`scale_max` > 0))),
  CONSTRAINT `ck_sup_eval_weight` CHECK (((`weight` > 0) and (`weight` <= 100)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_evaluations ──
CREATE TABLE `supplier_evaluations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `contract_id` int DEFAULT NULL COMMENT 'عقدُ المورد إن قُصد بعينه — والتقييمُ للمورد أصلًا',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `score` decimal(5,2) DEFAULT NULL COMMENT 'النتيجةُ من 100 — **محسوبةٌ من المؤشرات** ولا تُكتب يدًا (§4: لا انطباعًا)',
  `weight_measured` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'مجموعُ أوزان المؤشرات **المقيسة فعلًا** — التغطيةُ تُعلَن ولا تُخفى خلف نسبةٍ مطبَّعة',
  `state` enum('draft','decided') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `renewal_flag` enum('eligible','conditional','not_eligible') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'أثرُ النتيجة على التجديد — «ونتيجتُه **شرطٌ في التجديد**»',
  `decision_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generated_by` int DEFAULT NULL,
  `decided_by` int DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_eval_period` (`supplier_id`,`period_from`,`period_to`),
  KEY `ix_sup_eval` (`company_id`,`supplier_id`,`state`,`period_to`),
  CONSTRAINT `fk_sup_eval_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_eval_decided` CHECK (((`state` <> _utf8mb4'decided') or ((`renewal_flag` is not null) and (`decided_by` is not null)))),
  CONSTRAINT `ck_sup_eval_period` CHECK ((`period_to` >= `period_from`)),
  CONSTRAINT `ck_sup_eval_reason` CHECK (((`renewal_flag` is null) or (`renewal_flag` <> _utf8mb4'not_eligible') or ((`decision_note` is not null) and (`decision_note` <> _utf8mb4''))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_penalty_rules ──
CREATE TABLE `supplier_penalty_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `kind` enum('shortfall','readiness','coverage','delay') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'عجزٌ · جاهزيةٌ · تغطيةٌ · تأخر — قائمةُ §6 نصًّا',
  `threshold` decimal(12,3) DEFAULT NULL COMMENT 'الحدُّ الذي دونه يُفعَّل الجزاء (نسبةُ جاهزيةٍ دنيا · ساعاتُ إحلال …)',
  `rate` decimal(12,3) NOT NULL COMMENT 'معدلُ الجزاء لكل وحدةِ عجزٍ أو نقطةِ نقص',
  `rate_basis` enum('per_unit','percent_of_base') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'per_unit',
  `cap_percent` decimal(5,2) DEFAULT NULL COMMENT 'سقفُ الجزاء كنسبةٍ من الأساس — NULL = بلا سقفٍ مكتوب (يُعلَن)',
  `periodicity` enum('daily','monthly','contract') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `inherits_attribution` tinyint NOT NULL DEFAULT '1' COMMENT '1 = يرث إسنادَ CON-02 · 0 يلزمه سببٌ مكتوب (§4: يشدّد لا يعكس)',
  `override_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formula_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'توثيقُ الصيغة نصًّا — **لا يُقيَّم**: الحسابُ من الأعمدة المحكومة',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_penalty_rule` (`contract_id`,`kind`,`valid_from`),
  KEY `ix_penalty_rule_co` (`company_id`,`contract_id`,`state`),
  CONSTRAINT `fk_sup_penalty_rule_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_penalty_rule_cap` CHECK (((`cap_percent` is null) or ((`cap_percent` > 0) and (`cap_percent` <= 100)))),
  CONSTRAINT `ck_penalty_rule_override` CHECK (((`inherits_attribution` = 1) or ((`override_reason` is not null) and (char_length(trim(`override_reason`)) > 0)))),
  CONSTRAINT `ck_penalty_rule_rate` CHECK ((`rate` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_rfqs ──
CREATE TABLE `supplier_rfqs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `rfq_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_contract_id` int unsigned NOT NULL COMMENT 'العقدُ الذي اشتُقت منه البنود',
  `title` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_date` date NOT NULL COMMENT 'موعدُ الإقفال — «عرضٌ بعد الإقفال 423»',
  `state` enum('draft','sent','closed','awarded','contracted','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'UX-05 §8.2: Awarded → Contracted → ContainersAllocated',
  `sent_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `awarded_at` datetime DEFAULT NULL,
  `awarded_by` int unsigned DEFAULT NULL,
  `cancel_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_no` (`company_id`,`rfq_no`),
  KEY `ix_rfq_contract` (`company_id`,`client_contract_id`,`state`),
  CONSTRAINT `ck_rfq_awarded` CHECK (((`state` not in (_utf8mb4'awarded',_utf8mb4'contracted')) or (`awarded_by` is not null))),
  CONSTRAINT `ck_rfq_cancel` CHECK (((`state` <> _utf8mb4'cancelled') or ((`cancel_reason` is not null) and (`cancel_reason` <> _utf8mb4''))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='UX-05 §2.1 — طلبُ عروض الموردين: بنودُه من التزامات عقد العميل';

-- ── Table: suppliercontractequipments ──
CREATE TABLE `suppliercontractequipments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `contract_id` int NOT NULL COMMENT 'معرف عقد المورد من جدول supplierscontracts',
  `equip_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع المعدة (حفار، قلاب، خرامة)',
  `equip_size` int DEFAULT NULL COMMENT 'حجم المعدة',
  `equip_count` int DEFAULT NULL COMMENT 'عدد المعدات',
  `equip_count_basic` int DEFAULT '0' COMMENT 'عدد المعدات الأساسية',
  `equip_count_backup` int DEFAULT '0' COMMENT 'عدد المعدات الاحتياطية',
  `equip_shifts` int DEFAULT NULL COMMENT 'عدد الورديات',
  `equip_unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'وحدة القياس (ساعة، طن، متر)',
  `shift1_start` time DEFAULT NULL COMMENT 'بداية الوردية الأولى',
  `shift1_end` time DEFAULT NULL COMMENT 'نهاية الوردية الأولى',
  `shift2_start` time DEFAULT NULL COMMENT 'بداية الوردية الثانية',
  `shift2_end` time DEFAULT NULL COMMENT 'نهاية الوردية الثانية',
  `shift_hours` decimal(10,2) DEFAULT NULL COMMENT 'ساعات الوردية',
  `equip_total_month` decimal(10,2) DEFAULT NULL COMMENT 'إجمالي الوحدات يومياً',
  `equip_monthly_target` decimal(10,2) DEFAULT NULL COMMENT 'وحدات العمل في الشهر',
  `equip_total_contract` decimal(10,2) DEFAULT NULL COMMENT 'إجمالي وحدات العقد',
  `equip_price` decimal(10,2) DEFAULT NULL COMMENT 'السعر للوحدة',
  `equip_price_currency` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'العملة (دولار، جنيه)',
  `equip_operators` int DEFAULT NULL COMMENT 'عدد المشغلين',
  `equip_supervisors` int DEFAULT NULL COMMENT 'عدد المشرفين',
  `equip_technicians` int DEFAULT NULL COMMENT 'عدد الفنيين',
  `equip_assistants` int DEFAULT NULL COMMENT 'عدد المساعدين',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`),
  CONSTRAINT `fk_suppliercontractequipments_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplierscontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='معدات عقود الموردين';

-- ── Table: suppliers ──
CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الرمز/الكود للمورد',
  `supplier_type` enum('فرد','شركة','وسيط','مالك','جهة حكومية') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع المورد',
  `dealing_nature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'طبيعة التعامل',
  `equipment_types` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'أنواع المعدات (مفصولة بفواصل)',
  `commercial_registration` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم التسجيل التجاري/الرخصة',
  `tax_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الرقمُ الضريبي — حقلٌ نظاميٌّ واجب (UX-05 §5.1-①)',
  `bank_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_no` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_iban` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مستندُ التوثيق (شهادةٌ بنكيةٌ أو شيكٌ ملغًى) — **توثيقٌ بلا مستندٍ دعوى**',
  `bank_verified_at` datetime DEFAULT NULL,
  `bank_verified_by` int DEFAULT NULL,
  `identity_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع الهوية',
  `identity_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم الهوية/التسجيل',
  `identity_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الهوية',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `phone_alternative` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم هاتف بديل',
  `full_address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'العنوان الكامل',
  `contact_person_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم جهة الاتصال الأساسية',
  `contact_person_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'هاتف جهة الاتصال',
  `financial_registration_status` enum('مسجل رسميا','غير مسجل','تحت التسجيل','معفى من التسجيل') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'حالة التسجيل المالي',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `phone` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_suppliers_is_deleted` (`is_deleted`),
  CONSTRAINT `ck_sup_bank_verified` CHECK (((`bank_verified_at` is null) or ((`bank_account_no` is not null) and (`bank_account_no` <> _utf8mb4'') and (`bank_doc_ref` is not null) and (`bank_doc_ref` <> _utf8mb4''))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplierscontracts ──
CREATE TABLE `supplierscontracts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `supplier_id` int NOT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int DEFAULT '0',
  `contract_duration_months` int DEFAULT '0',
  `contract_duration_days` int DEFAULT '0',
  `equip_shifts_contract` int DEFAULT '0' COMMENT 'عدد الورديات في العقد',
  `shift_contract` int DEFAULT '0' COMMENT 'ساعات الوردية للعقد',
  `equip_total_contract_daily` int DEFAULT '0' COMMENT 'إجمالي العقد اليومي',
  `total_contract_permonth` int DEFAULT '0' COMMENT 'إجمالي العقد شهرياً',
  `total_contract_units` int DEFAULT '0' COMMENT 'إجمالي وحدات العقد',
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `transportation` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `accommodation` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `place_for_living` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `workshop` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `equip_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equip_size` int DEFAULT NULL,
  `equip_count` int DEFAULT '0',
  `equip_target_per_month` int DEFAULT '0',
  `equip_total_month` int DEFAULT '0',
  `equip_total_contract` int DEFAULT '0',
  `mach_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mach_size` int DEFAULT NULL,
  `mach_count` int DEFAULT '0',
  `mach_target_per_month` int DEFAULT '0',
  `mach_total_month` int DEFAULT '0',
  `mach_total_contract` int DEFAULT '0',
  `hours_monthly_target` int DEFAULT '0',
  `forecasted_contracted_hours` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `daily_work_hours` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `daily_operators` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_party` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_party` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_one` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_two` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_currency_contract` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'عملة العقد (دولار/جنيه)',
  `paid_contract` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'المبلغ المدفوع',
  `payment_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)',
  `guarantees` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'الضمانات',
  `payment_date` date DEFAULT NULL COMMENT 'تاريخ الدفع',
  `project_id` int NOT NULL DEFAULT '0',
  `project_contract_id` int DEFAULT NULL COMMENT 'معرف عقد المشروع المرتبط',
  `status` tinyint(1) DEFAULT '1' COMMENT '1=نشط, 0=موقوف',
  `pause_reason` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pause_date` date DEFAULT NULL COMMENT 'تاريخ إيقاف العقد',
  `resume_date` date DEFAULT NULL COMMENT 'تاريخ استئناف العقد',
  `termination_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'amicable أو hardship',
  `termination_reason` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `merged_with` int DEFAULT NULL COMMENT 'معرف العقد المدموج معه',
  PRIMARY KEY (`id`),
  KEY `idx_project_contract` (`project_contract_id`),
  KEY `fk_supplierscontracts_supplier` (`supplier_id`),
  KEY `fk_supplierscontracts_project` (`project_id`),
  KEY `fk_supplierscontracts_merged` (`merged_with`),
  KEY `idx_sc_status_signing` (`status`,`contract_signing_date`),
  CONSTRAINT `fk_supplierscontracts_merged` FOREIGN KEY (`merged_with`) REFERENCES `supplierscontracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_supplierscontracts_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_supplierscontracts_project_contract` FOREIGN KEY (`project_contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_supplierscontracts_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: tax_invoices ──
CREATE TABLE `tax_invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `claim_id` int NOT NULL COMMENT '«ولا صفَّ بلا claim_id» — ولا فاتورةَ بلا مستخلص',
  `client_id` int NOT NULL,
  `serial_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الرقمُ التسلسليُّ النظامي INV-{سنة}-{تسلسل}',
  `serial_year` smallint NOT NULL,
  `serial_seq` int NOT NULL COMMENT 'تسلسلٌ متصلٌ لكل (شركة × سنة) — والثغرةُ تُرى',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `net_amount` decimal(18,2) NOT NULL COMMENT 'صافي المستخلص كما اعتُمد — **لا يُكتب يدًا**',
  `tax_code` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT NULL,
  `tax_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT '«والضريبةُ سطرٌ مستقلٌّ بمرجعها» (§5)',
  `total_amount` decimal(18,2) NOT NULL COMMENT 'الصافي + الضريبة',
  `tax_fields_json` text COLLATE utf8mb4_unicode_ci COMMENT 'الحقولُ النظامية لحظةَ الإصدار — لقطةٌ لا اشتقاق',
  `state` enum('issued','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'issued',
  `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `issued_by` int DEFAULT NULL,
  `cancel_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_serial` (`company_id`,`serial_no`),
  UNIQUE KEY `uq_tax_seq` (`company_id`,`serial_year`,`serial_seq`),
  KEY `ix_tax_claim` (`claim_id`),
  KEY `ix_tax_client` (`company_id`,`client_id`,`state`),
  CONSTRAINT `fk_tax_invoice_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_tax_cancel` CHECK (((`state` <> _utf8mb4'cancelled') or ((`cancel_reason` is not null) and (`cancel_reason` <> _utf8mb4'')))),
  CONSTRAINT `ck_tax_ref` CHECK (((`tax_amount` = 0) or ((`tax_code` is not null) and (`tax_code` <> _utf8mb4'') and (`tax_rate` is not null)))),
  CONSTRAINT `ck_tax_total` CHECK ((`total_amount` = (`net_amount` + `tax_amount`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: template_permissions ──
CREATE TABLE `template_permissions` (
  `tp_id` int unsigned NOT NULL AUTO_INCREMENT,
  `template_version_id` int unsigned NOT NULL COMMENT 'FK للنسخة لا للقالب — فمحتوى القديمة لا يتغير عند نشر جديدة',
  `dimension` enum('visibility','action','approval','scope') COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_rule` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_cap` decimal(18,2) DEFAULT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effect` enum('grant','deny') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'grant',
  PRIMARY KEY (`tp_id`),
  KEY `idx_tp_ver` (`template_version_id`,`dimension`),
  CONSTRAINT `fk_tp_ver` FOREIGN KEY (`template_version_id`) REFERENCES `permission_template_versions` (`ver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: الأبعاد الأربعة — وdeny يغلب grant دائمًا';

-- ── Table: tenants ──
CREATE TABLE `tenants` (
  `tenant_id` int unsigned NOT NULL COMMENT '= company_id القائم (حد العزل) — يقابل entity_id للمستأجرة وحدها',
  `entity_id` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  UNIQUE KEY `uq_tenants_entity` (`entity_id`),
  CONSTRAINT `fk_tenants_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §2-②-ب: حد العزل يُقرأ من هنا حصرًا — ولا يُشتق من أي صفة أخرى';

-- ── Table: tenders ──
CREATE TABLE `tenders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `tender_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `authority_id` int DEFAULT NULL,
  `opportunity_id` int unsigned DEFAULT NULL,
  `closing_date` date DEFAULT NULL,
  `participation_state` enum('إعداد','مقدمة','مسحوبة') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'إعداد',
  `result` enum('قيد التقييم','فوز','خسارة','إلغاء') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'قيد التقييم',
  `result_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenders_company_code` (`company_id`,`tender_code`),
  KEY `idx_tender_scope` (`company_id`,`is_deleted`),
  KEY `idx_tender_opp` (`opportunity_id`),
  KEY `idx_tender_state` (`participation_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_attachments ──
CREATE TABLE `ticket_attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `ticket_id` int unsigned NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` enum('photo','signature','document') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'photo',
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `captured_at` datetime DEFAULT NULL,
  `uploaded_by` int unsigned DEFAULT NULL,
  `sync_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ticket` (`company_id`,`ticket_id`),
  KEY `fk_at_ticket` (`ticket_id`),
  CONSTRAINT `fk_at_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_categories ──
CREATE TABLE `ticket_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned DEFAULT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applies_to` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `failure_main_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'M-31: وصلةُ التصنيف الموحد — main_category_code؛ NULL = موروثٌ بلا مقابلٍ يُعلَن',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_code` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_communications ──
CREATE TABLE `ticket_communications` (
  `cm_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tk_id` int unsigned NOT NULL,
  `person_id` int NOT NULL,
  `channel` enum('system','phone','field') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cm_id`),
  KEY `idx_tc_ticket` (`tk_id`,`at`),
  CONSTRAINT `fk_tktc_ticket` FOREIGN KEY (`tk_id`) REFERENCES `tickets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تواصل مركز البلاغات يسجَّل فيبقى أثره (§10-③)';

-- ── Table: ticket_effects ──
CREATE TABLE `ticket_effects` (
  `lnk_id` int unsigned NOT NULL AUTO_INCREMENT,
  `ws_id` int unsigned NOT NULL,
  `effect_type` enum('inspection_request','work_order','issue_request','purchase_request','stoppage_attribution','decision','reply','acknowledge','info_added','no_action') COLLATE utf8mb4_unicode_ci NOT NULL,
  `effect_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_provisional` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'للإسناد قبل اعتماد الأثر — الخطوات الأربع §7',
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`lnk_id`),
  KEY `idx_te_ws` (`ws_id`),
  CONSTRAINT `fk_tkte_ws` FOREIGN KEY (`ws_id`) REFERENCES `ticket_workstreams` (`ws_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ولا يُغلق مسار بلا سطر هنا (عدا الإغلاق الإداري)';

-- ── Table: ticket_escalation_rules ──
CREATE TABLE `ticket_escalation_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level_no` tinyint NOT NULL,
  `escalate_after_hours` decimal(6,2) NOT NULL,
  `escalate_to_role` enum('responsible','dept_head','dept_manager','ops_manager','top_mgmt') COLLATE utf8mb4_unicode_ci NOT NULL,
  `notify_channel` enum('in_app','email','both') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_app',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_level` (`company_id`,`level_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_escalations ──
CREATE TABLE `ticket_escalations` (
  `esc_id` int unsigned NOT NULL AUTO_INCREMENT,
  `ws_id` int unsigned NOT NULL,
  `level` enum('mgr','ops_mgr','exec') COLLATE utf8mb4_unicode_ci NOT NULL,
  `triggered_by` enum('sla_breach','reopen_threshold','safety','hold_overdue') COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_person_id` int DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`esc_id`),
  KEY `idx_esc_ws` (`ws_id`,`at`),
  CONSTRAINT `fk_esc_ws` FOREIGN KEY (`ws_id`) REFERENCES `ticket_workstreams` (`ws_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Insert-only — ولا تصعيد يدوي يسجَّل هنا (§6: آلي لا بطلب)';

-- ── Table: ticket_events ──
CREATE TABLE `ticket_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `ticket_id` int unsigned NOT NULL,
  `event_type` enum('note','communication','status_change','transfer','escalation','attachment','reminder','system') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'note',
  `actor_user_id` int unsigned DEFAULT NULL,
  `actor_role_id` int unsigned DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `old_value` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ticket_time` (`company_id`,`ticket_id`,`created_at`),
  KEY `fk_ev_ticket` (`ticket_id`),
  CONSTRAINT `fk_ev_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_holds ──
CREATE TABLE `ticket_holds` (
  `hold_id` int unsigned NOT NULL AUTO_INCREMENT,
  `ws_id` int unsigned NOT NULL COMMENT 'على المسار لا الرأس — فالمهلة تتوقف لمسار ولا توقف الباقي',
  `reason_code` enum('awaiting_part','awaiting_approval','awaiting_technician','awaiting_reporter','awaiting_external') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'قائمة محكومة لا نص حر — وإلا صار التعليق بابًا للتهرب',
  `expected_until` datetime NOT NULL COMMENT 'ولا تعليق بلا مدة متوقعة — وتجاوزها يصعد التعليق نفسه',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`hold_id`),
  KEY `idx_holds_open` (`ws_id`,`ended_at`),
  CONSTRAINT `fk_hold_ws` FOREIGN KEY (`ws_id`) REFERENCES `ticket_workstreams` (`ws_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_participants ──
CREATE TABLE `ticket_participants` (
  `p_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tk_id` int unsigned NOT NULL,
  `person_id` int NOT NULL,
  `role` enum('reporter','assignee','watcher','duplicate_reporter') COLLATE utf8mb4_unicode_ci NOT NULL,
  `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`p_id`),
  UNIQUE KEY `uq_tp` (`tk_id`,`person_id`,`role`),
  CONSTRAINT `fk_tp_ticket` FOREIGN KEY (`tk_id`) REFERENCES `tickets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ومبلغ المكرر يضاف متابعًا للأصل فلا يُفقد أنه أبلغ (§9)';

-- ── Table: ticket_recurrence_templates ──
CREATE TABLE `ticket_recurrence_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_type_id` int unsigned NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `equipment_id` int unsigned DEFAULT NULL,
  `recurrence_interval` int NOT NULL DEFAULT '1',
  `recurrence_unit` enum('day','week','month','year') COLLATE utf8mb4_unicode_ci NOT NULL,
  `next_occurrence_date` date NOT NULL,
  `lead_time_days` int NOT NULL DEFAULT '0',
  `default_owner_role_id` int unsigned DEFAULT NULL,
  `default_priority` enum('normal','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_next` (`company_id`,`active`,`next_occurrence_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_responses ──
CREATE TABLE `ticket_responses` (
  `rd_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tk_id` int unsigned NOT NULL,
  `ws_id` int unsigned DEFAULT NULL COMMENT 'إلزامي لردود المسار وفارغ للرد المركزي على الرأس',
  `person_id` int NOT NULL,
  `response_type` enum('reply','acknowledge','info_added','no_action_decision') COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rd_id`),
  KEY `idx_tr_ticket` (`tk_id`,`at`),
  CONSTRAINT `fk_tktr_ticket` FOREIGN KEY (`tk_id`) REFERENCES `tickets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_sla_policies ──
CREATE TABLE `ticket_sla_policies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_type_id` int unsigned DEFAULT NULL,
  `priority` enum('normal','high','critical') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_impact` enum('production_critical','revenue','safety','admin') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_hours` decimal(6,2) NOT NULL,
  `resolution_hours` decimal(6,2) NOT NULL,
  `remind_before_hours` decimal(6,2) DEFAULT NULL,
  `escalation_rule_id` int unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_match` (`company_id`,`ticket_type_id`,`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_transfers ──
CREATE TABLE `ticket_transfers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `ticket_id` int unsigned NOT NULL,
  `from_role_id` int unsigned NOT NULL,
  `to_role_id` int unsigned NOT NULL,
  `from_user_id` int unsigned DEFAULT NULL,
  `to_user_id` int unsigned DEFAULT NULL,
  `transfer_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `transferred_by` int unsigned DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `sync_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ticket` (`company_id`,`ticket_id`),
  KEY `fk_tr_ticket` (`ticket_id`),
  CONSTRAINT `fk_tr_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_type_workstreams ──
CREATE TABLE `ticket_type_workstreams` (
  `ws_def_id` int unsigned NOT NULL AUTO_INCREMENT,
  `ticket_type_id` int unsigned NOT NULL,
  `workstream_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'maintenance·movement·operators·warehouse·procurement·hr·governance·support…',
  `seq_no` int NOT NULL DEFAULT '1',
  `target_org_unit_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'org_units.unit_code — والمكلف يُحل من ORG-01 لا من شخص ثابت',
  `target_role` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'دور الحل في PermitGate/TicketRouter (movement·maintenance·…)',
  `mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `activation_mode` enum('immediate','conditional') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'immediate',
  `trigger_event` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مثال StockUnavailable — الشرطي يفتح بوقوعه لا بالإنشاء',
  `depends_on_workstream_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_sla_minutes` int DEFAULT NULL,
  `resolve_sla_minutes` int DEFAULT NULL,
  `sla_clock` enum('absolute','business') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'absolute' COMMENT '§6: الحرج مطلق وما دونه بساعات العمل',
  PRIMARY KEY (`ws_def_id`),
  UNIQUE KEY `uq_ttws` (`ticket_type_id`,`workstream_type`,`seq_no`),
  CONSTRAINT `fk_ttws_type` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TKT-01 §12: فمسار المشتريات يُفتح عند إعلان نفاد القطعة لا عند إنشاء البلاغ';

-- ── Table: ticket_types ──
CREATE TABLE `ticket_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned DEFAULT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_role_id` int unsigned NOT NULL,
  `default_nature` enum('request','incident','recurring') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'request',
  `nature` enum('incident','problem','request','complaint','information','risk','emergency','suggestion') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'TKT-01 §3: الطبيعة غير المجال — تحدد الدورة والسرية والإغلاق',
  `category` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '§4: المجال — منه تشتق الإدارة المختصة',
  `default_confidentiality` enum('normal','protected','secret') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `closure_policy` enum('reporter_confirm','owner_approve','auto','admin_only','committee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reporter_confirm' COMMENT '§5-⑥: ولا إغلاق آلي للسلامة والحوادث وشكاوى العاملين',
  `allow_anonymous` tinyint(1) NOT NULL DEFAULT '0',
  `default_priority` enum('normal','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `ref_table` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_sla_id` int unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_code` (`company_id`,`code`),
  KEY `ix_owner_role` (`owner_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_watchers ──
CREATE TABLE `ticket_watchers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `ticket_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role_id` int unsigned DEFAULT NULL,
  `watch_reason` enum('reporter','owner','manager','subscribed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'subscribed',
  `notify` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_watch` (`company_id`,`ticket_id`,`user_id`),
  KEY `fk_wt_ticket` (`ticket_id`),
  CONSTRAINT `fk_wt_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_workstreams ──
CREATE TABLE `ticket_workstreams` (
  `ws_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tk_id` int unsigned NOT NULL,
  `workstream_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seq_no` int NOT NULL DEFAULT '1',
  `org_unit_id` int unsigned DEFAULT NULL,
  `assignee_person_id` int DEFAULT NULL COMMENT 'يُحل من تكليفات ORG-01 النافذة لا من جدول النوع',
  `mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `state` enum('new','received','in_progress','on_hold','done_pending','closed','reopened','admin_closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `activation_state` enum('pending','opened','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'opened' COMMENT 'الشرطي pending حتى حدث تفعيله',
  `response_due_at` datetime DEFAULT NULL,
  `resolve_due_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `reopen_count` int NOT NULL DEFAULT '0' COMMENT 'ظاهر — وثلاث إعادات ترفعه للمركز',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ws_id`),
  UNIQUE KEY `uq_tws` (`tk_id`,`workstream_type`,`seq_no`),
  KEY `idx_tws_assignee` (`assignee_person_id`,`state`),
  KEY `idx_tws_due` (`state`,`response_due_at`),
  CONSTRAINT `fk_tws_ticket` FOREIGN KEY (`tk_id`) REFERENCES `tickets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TKT-01 §12: UQ على (البلاغ×نوع المسار×التسلسل) — فللإدارة الواحدة مساران مختلفان';

-- ── Table: tickets ──
CREATE TABLE `tickets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `ticket_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_type_id` int unsigned NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `stage` enum('new','classified','routed','in_progress','waiting','follow_up','done','closed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `head_state` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open' COMMENT 'ذاكرة مشتقة لا مصدر حقيقة — لا يكتبها إلا معيد الحساب (TicketStateService)',
  `ticket_nature` enum('request','incident','recurring') COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('normal','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `confidentiality` enum('normal','protected','secret') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `business_impact` enum('production_critical','revenue','safety','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `production_critical` tinyint(1) NOT NULL DEFAULT '0',
  `project_weight` enum('strategic','main','normal') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `call_date` date NOT NULL,
  `call_time` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reporting_person` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reporter_contact` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reporter_entity_id` int unsigned DEFAULT NULL,
  `reporter_user_id` int unsigned DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT '0' COMMENT '§8-④: الهوية محفوظة للحوكمة',
  `project_id` int unsigned DEFAULT NULL,
  `site_id` int DEFAULT NULL,
  `contract_id` int DEFAULT NULL,
  `shift_no` int DEFAULT NULL,
  `period_no` int DEFAULT NULL,
  `equipment_id` int unsigned DEFAULT NULL,
  `machine_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `machine_condition` enum('running','stopped') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meter_reading` decimal(12,2) DEFAULT NULL,
  `complaint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `operational_summary` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'يراه الجميع — الفصل البنيوي §8',
  `private_details` text COLLATE utf8mb4_unicode_ci COMMENT 'خلف ConfidentialityGuard — لا يُجلب بلا صلاحية',
  `source_screen` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '§2: السياق محمول لا مُدخل',
  `source_entity_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_entity_id` bigint unsigned DEFAULT NULL,
  `driver_id` int unsigned DEFAULT NULL,
  `helper_id` int unsigned DEFAULT NULL,
  `shift` enum('morning','evening') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_role_id` int unsigned NOT NULL,
  `assigned_user_id` int unsigned DEFAULT NULL,
  `service_team` enum('internal','external_workshop') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_status` text COLLATE utf8mb4_unicode_ci,
  `parent_id` int unsigned DEFAULT NULL,
  `duplicate_of_ticket_id` int unsigned DEFAULT NULL,
  `related_ticket_id` int unsigned DEFAULT NULL,
  `recurrence_group_id` int unsigned DEFAULT NULL,
  `is_parent` tinyint(1) NOT NULL DEFAULT '0',
  `ticket_role` enum('parent','child','standalone') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standalone',
  `sla_policy_id` int unsigned DEFAULT NULL,
  `first_action_at` datetime DEFAULT NULL,
  `response_due_at` datetime DEFAULT NULL,
  `resolution_due_at` datetime DEFAULT NULL,
  `close_date` date DEFAULT NULL,
  `close_time` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closed_by` int unsigned DEFAULT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT '0',
  `recurrence_template_id` int unsigned DEFAULT NULL,
  `linked_ref_table` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linked_ref_id` int unsigned DEFAULT NULL,
  `sync_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `escalation_level` tinyint NOT NULL DEFAULT '0' COMMENT 'E-14: أعلى مستوًى صُعّد إليه — كان المفتاحُ يوميًّا فيتكرر غدًا',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_no` (`company_id`,`ticket_no`),
  UNIQUE KEY `uq_sync` (`company_id`,`sync_uuid`),
  KEY `ix_stage` (`company_id`,`stage`),
  KEY `ix_owner` (`company_id`,`owner_role_id`),
  KEY `ix_due` (`company_id`,`resolution_due_at`),
  KEY `ix_type` (`ticket_type_id`),
  KEY `ix_equip` (`equipment_id`),
  KEY `ix_parent` (`parent_id`),
  KEY `fk_tk_cat` (`category_id`),
  KEY `fk_tk_sla` (`sla_policy_id`),
  KEY `idx_tickets_head` (`head_state`,`priority`,`created_at`),
  KEY `idx_tickets_dup` (`duplicate_of_ticket_id`),
  CONSTRAINT `fk_tk_cat` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tk_parent` FOREIGN KEY (`parent_id`) REFERENCES `tickets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tk_sla` FOREIGN KEY (`sla_policy_id`) REFERENCES `ticket_sla_policies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tk_type` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: timesheet ──
CREATE TABLE `timesheet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `operator` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `employee_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `shift_hours` float DEFAULT '0',
  `executed_hours` float DEFAULT '0',
  `bucket_hours` float DEFAULT '0',
  `jackhammer_hours` float DEFAULT '0',
  `extra_hours` float DEFAULT '0',
  `extra_hours_total` float DEFAULT '0',
  `standby_hours` float DEFAULT '0',
  `dependence_hours` float DEFAULT '0',
  `total_work_hours` float DEFAULT '0',
  `work_notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hr_fault` float DEFAULT '0',
  `maintenance_fault` float DEFAULT '0',
  `marketing_fault` float DEFAULT '0',
  `approval_fault` float DEFAULT '0',
  `other_fault_hours` float DEFAULT '0',
  `ts_supplier_stop_hours` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'D02 §3.5 ⑤ توقف على المورد — لا مستحقَ له وقد ينقلب خصمًا',
  `ts_planned_stop_hours` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'D02 §3.5 ⑭ توقف مخطط — يحسمه جدول سياسة العقد',
  `ts_force_majeure_hours` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'D02 §3.5 ⑮ قوة قاهرة — تسويةٌ حالةً بحالة وفق بند العقد',
  `total_fault_hours` float DEFAULT '0',
  `fault_notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_seconds` int DEFAULT '0',
  `start_minutes` int DEFAULT '0',
  `start_hours` int DEFAULT '0',
  `end_seconds` int DEFAULT '0',
  `end_minutes` int DEFAULT '0',
  `end_hours` int DEFAULT '0',
  `counter_diff` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `fault_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fault_department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fault_part` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fault_details` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `general_notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `operator_hours` float DEFAULT '0',
  `machine_standby_hours` float DEFAULT '0',
  `jackhammer_standby_hours` float DEFAULT '0',
  `bucket_standby_hours` float DEFAULT '0',
  `extra_operator_hours` float DEFAULT '0',
  `operator_standby_hours` float DEFAULT '0',
  `operator_notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tons_count` decimal(10,2) DEFAULT '0.00' COMMENT 'عدد الأطنان - للنوع 2 (القلاب)',
  `trips_count` int DEFAULT '0' COMMENT 'عدد النقلات - للنوع 2 (القلاب)',
  `transport_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meters_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع الأمتار - للنوع 3 (الخرمات)',
  `meters_count` decimal(10,2) DEFAULT '0.00' COMMENT 'عدد الأمتار - للنوع 3 (الخرمات)',
  `drilling_holes_count` int DEFAULT '0',
  `drilling_depth` decimal(10,2) DEFAULT '0.00',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL DEFAULT '0',
  `time_notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `client_uuid` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_timesheet_client_uuid` (`client_uuid`),
  KEY `idx_timesheet_updated_at` (`updated_at`),
  KEY `idx_timesheet_date` (`date`),
  KEY `idx_timesheet_operator` (`operator`),
  KEY `idx_timesheet_date_id` (`date`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: timesheet_approval_notes ──
CREATE TABLE `timesheet_approval_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `timesheet_id` int NOT NULL COMMENT 'FK → timesheet.id',
  `company_id` int DEFAULT NULL,
  `column_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم العمود التقني',
  `column_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'عنوان العمود بالعربية',
  `note_text` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int NOT NULL COMMENT 'FK → users.id',
  `created_by_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_ts_id` (`timesheet_id`),
  KEY `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ملاحظات اعتماد ساعات العمل';

-- ── Table: timesheet_approvals ──
CREATE TABLE `timesheet_approvals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `timesheet_id` int NOT NULL COMMENT 'FK → timesheet.id',
  `company_id` int DEFAULT NULL,
  `approval_level` tinyint(1) NOT NULL COMMENT '1..4',
  `approved_by` int NOT NULL COMMENT 'FK → users.id',
  `approved_by_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `approved_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=اعتمد, 0=رُفض',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ts_level` (`timesheet_id`,`approval_level`),
  KEY `idx_ts_id` (`timesheet_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_level` (`approval_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='اعتمادات ساعات العمل الهرمية';

-- ── Table: timesheet_failure_hours ──
CREATE TABLE `timesheet_failure_hours` (
  `id` int NOT NULL AUTO_INCREMENT,
  `timesheet_id` int NOT NULL,
  `operation_id` int NOT NULL,
  `equipment_id` int NOT NULL DEFAULT '0',
  `failure_code_id` int NOT NULL,
  `equipment_type` tinyint(1) NOT NULL COMMENT '1=حفار,2=قلاب,3=خرامة',
  `event_type_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `main_category_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `main_category_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_category` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failure_detail` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `timesheet_date` date NOT NULL,
  `company_id` int NOT NULL DEFAULT '0',
  `created_by` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_timesheet_id` (`timesheet_id`),
  KEY `idx_operation_id` (`operation_id`),
  KEY `idx_equipment_id` (`equipment_id`),
  KEY `idx_failure_code_id` (`failure_code_id`),
  KEY `idx_full_code` (`full_code`),
  KEY `idx_timesheet_date` (`timesheet_date`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_lookup_report` (`company_id`,`timesheet_date`,`equipment_id`,`failure_code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: tkt_notifications ──
CREATE TABLE `tkt_notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `ticket_id` int unsigned DEFAULT NULL,
  `notif_type` enum('due_soon','overdue','escalation','recurring_created') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_role` int unsigned DEFAULT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_url` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `dedupe_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dedupe` (`company_id`,`dedupe_key`),
  KEY `ix_company_read` (`company_id`,`is_read`),
  KEY `ix_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_attachments ──
CREATE TABLE `transfer_attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `order_id` int unsigned NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` enum('departure_proof','arrival_proof','permit','signature','photo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'photo',
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `captured_at` datetime DEFAULT NULL,
  `uploaded_by` int unsigned DEFAULT NULL,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_order` (`company_id`,`order_id`),
  KEY `fk_at_order` (`order_id`),
  CONSTRAINT `fk_at_order` FOREIGN KEY (`order_id`) REFERENCES `transfer_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_cost_lines ──
CREATE TABLE `transfer_cost_lines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `order_id` int unsigned NOT NULL,
  `cost_type` enum('fuel','labor','contractor','misc','permit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_local` decimal(14,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG',
  `fx_rate` decimal(14,6) DEFAULT NULL,
  `amount_usd` decimal(14,2) NOT NULL DEFAULT '0.00',
  `cost_bearer` enum('client','company','new_client') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `analytic_cost_center` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_order` (`company_id`,`order_id`),
  KEY `fk_cl_order` (`order_id`),
  CONSTRAINT `fk_cl_order` FOREIGN KEY (`order_id`) REFERENCES `transfer_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_cost_rules ──
CREATE TABLE `transfer_cost_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `movement_type` enum('mob','demob','direct','internal','admin','spare_parts','travel') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_operator` enum('any','lt','gte') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'any',
  `duration_threshold_days` int DEFAULT NULL,
  `default_bearer` enum('client','company','new_client') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `basis_note` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_rule` (`company_id`,`movement_type`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_events ──
CREATE TABLE `transfer_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `order_id` int unsigned NOT NULL,
  `event_type` enum('note','communication','status_change','alert','attachment','system') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'note',
  `actor_user_id` int unsigned DEFAULT NULL,
  `actor_dept` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `old_value` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_order_time` (`company_id`,`order_id`,`created_at`),
  KEY `fk_ev_order` (`order_id`),
  CONSTRAINT `fk_ev_order` FOREIGN KEY (`order_id`) REFERENCES `transfer_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_lines ──
CREATE TABLE `transfer_lines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `order_id` int unsigned NOT NULL,
  `item_type` enum('equipment','attachment','material','person') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `equipment_id` int unsigned DEFAULT NULL,
  `attachment_ref` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` int unsigned DEFAULT NULL,
  `employee_id` int unsigned DEFAULT NULL,
  `quantity` decimal(12,2) DEFAULT NULL,
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_order` (`company_id`,`order_id`),
  KEY `fk_ln_order` (`order_id`),
  CONSTRAINT `fk_ln_order` FOREIGN KEY (`order_id`) REFERENCES `transfer_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_orders ──
CREATE TABLE `transfer_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `order_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_id` int unsigned DEFAULT NULL,
  `transfer_type_id` int unsigned NOT NULL,
  `direction` enum('mob','demob','direct','internal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_module` enum('operations','fleet','maintenance','workforce','procurement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by_user_id` int unsigned DEFAULT NULL,
  `project_id` int unsigned DEFAULT NULL,
  `from_location_id` int unsigned NOT NULL,
  `to_location_id` int unsigned NOT NULL,
  `request_date` date NOT NULL,
  `planned_date` date DEFAULT NULL,
  `departure_datetime` datetime DEFAULT NULL,
  `arrival_datetime` datetime DEFAULT NULL,
  `vehicle_id` int unsigned DEFAULT NULL,
  `carrier_type` enum('internal','contractor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_entity_id` int unsigned DEFAULT NULL,
  `driver_id` int unsigned DEFAULT NULL,
  `route` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_cost_usd` decimal(12,2) DEFAULT NULL,
  `actual_cost_usd` decimal(12,2) DEFAULT NULL,
  `cost_bearer` enum('client','company','new_client') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `charge_supplier_id` int unsigned DEFAULT NULL COMMENT 'المورد الذي يُحمَّل بتعرفة هذا الأمر (ENT-02 §3-④) — NULL = لا تحميلَ على مورد',
  `tariff_id` int unsigned DEFAULT NULL COMMENT 'التعرفةُ التي سُعّر بها — «المبلغُ يُقرأ من مصدره»',
  `tariff_amount` decimal(18,2) DEFAULT NULL COMMENT '**محسوبٌ لا مُدخَل**: كميةُ نموذج التعرفة × معدلها مقصوصةً بحدَّيها',
  `tariff_currency` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tariff_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'بيانُ الاحتساب: النموذجُ والكميةُ والمعدل وقصُّ الحدّ إن وقع',
  `distance_km` decimal(12,2) DEFAULT NULL COMMENT 'مسافةُ المسار — لازمةٌ لنموذج per_km وبلا قيمةٍ لا تسعير',
  `priced_at` datetime DEFAULT NULL,
  `priced_by` int unsigned DEFAULT NULL,
  `analytic_cost_center` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_days` int DEFAULT NULL,
  `priority` enum('normal','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `stage` enum('request','planned','ready','in_transit','arrived','closed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'request',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_no` (`company_id`,`order_no`),
  UNIQUE KEY `uq_sync` (`company_id`,`sync_uuid`),
  KEY `ix_stage` (`company_id`,`stage`),
  KEY `ix_type` (`transfer_type_id`),
  KEY `ix_project` (`project_id`),
  KEY `ix_planned` (`company_id`,`planned_date`),
  KEY `fk_to_req` (`request_id`),
  KEY `fk_to_from` (`from_location_id`),
  KEY `fk_to_to` (`to_location_id`),
  KEY `ix_order_charge_supplier` (`company_id`,`charge_supplier_id`,`stage`),
  CONSTRAINT `fk_to_from` FOREIGN KEY (`from_location_id`) REFERENCES `trs_locations` (`id`),
  CONSTRAINT `fk_to_req` FOREIGN KEY (`request_id`) REFERENCES `transfer_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_to_to` FOREIGN KEY (`to_location_id`) REFERENCES `trs_locations` (`id`),
  CONSTRAINT `fk_to_type` FOREIGN KEY (`transfer_type_id`) REFERENCES `transfer_types` (`id`),
  CONSTRAINT `ck_order_tariff_source` CHECK (((`tariff_amount` is null) or ((`tariff_id` is not null) and (`tariff_currency` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_permits ──
CREATE TABLE `transfer_permits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `order_id` int unsigned NOT NULL,
  `permit_type` enum('route','load','transit','safety') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `authority` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `state` enum('valid','expired','issuing') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'issuing',
  `document_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_order` (`company_id`,`order_id`),
  KEY `ix_expiry` (`company_id`,`expiry_date`),
  KEY `fk_pm_order` (`order_id`),
  CONSTRAINT `fk_pm_order` FOREIGN KEY (`order_id`) REFERENCES `transfer_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_requests ──
CREATE TABLE `transfer_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer_type_id` int unsigned NOT NULL,
  `source_module` enum('operations','fleet','maintenance','workforce','procurement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by_user_id` int unsigned DEFAULT NULL,
  `project_id` int unsigned DEFAULT NULL,
  `from_location_id` int unsigned DEFAULT NULL,
  `to_location_id` int unsigned DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('normal','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `state` enum('submitted','approved','converted','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `order_id` int unsigned DEFAULT NULL,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_req_code` (`company_id`,`code`),
  KEY `ix_state` (`company_id`,`state`),
  KEY `fk_rq_type` (`transfer_type_id`),
  CONSTRAINT `fk_rq_type` FOREIGN KEY (`transfer_type_id`) REFERENCES `transfer_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_tariffs ──
CREATE TABLE `transfer_tariffs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `supplier_id` int unsigned DEFAULT NULL COMMENT 'المورد المحمَّل — NULL = تعرفةٌ لا تخصُّ موردًا بعينه (الأعمّ)',
  `transfer_type_id` int unsigned DEFAULT NULL COMMENT 'نوعُ الترحيل — NULL = أي نوع',
  `from_location_id` int unsigned DEFAULT NULL COMMENT 'مبدأُ المسار — NULL = أي مبدأ',
  `to_location_id` int unsigned DEFAULT NULL COMMENT 'منتهاه — NULL = أي منتهى',
  `pricing_model` enum('per_trip','per_km','per_ton','per_equipment') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نموذجُ التسعير — والكميةُ تُقرأ من الأمر بحسبه',
  `rate` decimal(14,4) NOT NULL COMMENT 'معدلُ الوحدة — عمودٌ مستقلٌّ بدقّته (گوتشا M-15: pct(5,2) يبتر)',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SDG' COMMENT 'لا جمعَ عملتين في رقم',
  `min_amount` decimal(18,2) DEFAULT NULL,
  `max_amount` decimal(18,2) DEFAULT NULL COMMENT 'سقفٌ يقصّ **ويُعلن قصَّه**',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL COMMENT 'NULL = مفتوحةُ الطرف',
  `state` enum('active','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'بندُ العقد أو مرجعُ التعرفة',
  `created_by` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transfer_tariff` (`company_id`,`supplier_id`,`transfer_type_id`,`from_location_id`,`to_location_id`,`pricing_model`,`effective_from`) COMMENT 'تعرفةٌ واحدةٌ لمفتاحها في تاريخها — والجديدُ بسريانٍ جديد',
  KEY `ix_tariff_lookup` (`company_id`,`state`,`effective_from`,`effective_to`),
  CONSTRAINT `ck_tariff_limits` CHECK (((`min_amount` is null) or (`max_amount` is null) or (`min_amount` <= `max_amount`))),
  CONSTRAINT `ck_tariff_rate` CHECK ((`rate` > 0)),
  CONSTRAINT `ck_tariff_span` CHECK (((`effective_to` is null) or (`effective_to` >= `effective_from`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ENT-02 §3-④ — تعرفةُ الترحيل: السعرُ المكتوب الذي يُحمَّل به المورد';

-- ── Table: transfer_types ──
CREATE TABLE `transfer_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `operational_category` enum('equipment_transfer','parts_transfer','personnel_move','equipment_plus_move') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_bearer` enum('client','company','new_client','by_rule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'by_rule',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_code` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: trs_locations ──
CREATE TABLE `trs_locations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_type` enum('base','project','workshop','office') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_id` int unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loc_code` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: trs_notifications ──
CREATE TABLE `trs_notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `order_id` int unsigned DEFAULT NULL,
  `notif_type` enum('delayed','no_arrival','permit_expiry','sixty_day') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_role` int unsigned DEFAULT NULL,
  `title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_url` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `dedupe_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dedupe` (`company_id`,`dedupe_key`),
  KEY `ix_company_read` (`company_id`,`is_read`),
  KEY `ix_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: unit_approvals ──
CREATE TABLE `unit_approvals` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `entry_id` int unsigned NOT NULL COMMENT 'unit_entries',
  `round_no` smallint unsigned NOT NULL DEFAULT '1' COMMENT 'جولة السلسلة — كل إعادةٍ تفتح جولةً جديدة (UX-03 §8.2)',
  `stage` enum('site','supplier','operator','supervisor','fleet','sales','finance') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `decision` enum('approved','returned','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` int unsigned NOT NULL COMMENT 'users',
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decided_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stage_once_per_round` (`company_id`,`entry_id`,`round_no`,`stage`) COMMENT 'قرارٌ واحدٌ لكل مرحلةٍ في الجولة',
  KEY `ix_entry` (`company_id`,`entry_id`),
  KEY `ix_stage` (`company_id`,`stage`,`decided_at`),
  KEY `fk_ua_entry` (`entry_id`),
  CONSTRAINT `fk_ua_entry` FOREIGN KEY (`entry_id`) REFERENCES `unit_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D02 §4.2 — سلسلة الاعتماد الخماسية: سطرٌ إلحاقيٌّ لكل قرار';

-- ── Table: unit_capacity_flags ──
CREATE TABLE `unit_capacity_flags` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `entry_id` int unsigned NOT NULL COMMENT 'الواقعة التي رُفع عليها العلم',
  `subject` enum('equipment','operator') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_ref` int unsigned NOT NULL COMMENT 'معرّف المعدة أو الموظف',
  `flag_date` date NOT NULL,
  `measured_hours` decimal(8,2) NOT NULL COMMENT 'مجموع اليوم المقيس شاملًا هذه الواقعة',
  `capacity_hours` decimal(8,2) NOT NULL COMMENT 'الطاقة النافذة وقت الرفع — لقطةٌ لا مرجع',
  `overlap_found` tinyint(1) DEFAULT NULL COMMENT 'تداخلُ ورديات — نتيجة الفحص',
  `duplicate_found` tinyint(1) DEFAULT NULL COMMENT 'تكرارٌ مشتبهٌ به — نتيجة الفحص',
  `second_operator_present` tinyint(1) DEFAULT NULL COMMENT 'هل وُجد مشغّلٌ ثانٍ؟ (إعلانٌ صريح)',
  `cause_note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سبب التجاوز — إلزامٌ قبل التخليص',
  `cleared_by` int unsigned DEFAULT NULL COMMENT 'المسؤول المعتمِد — users',
  `cleared_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_flag` (`company_id`,`entry_id`,`subject`),
  KEY `ix_open` (`company_id`,`cleared_at`),
  KEY `ix_subject` (`company_id`,`subject`,`subject_ref`,`flag_date`),
  KEY `fk_ucf_entry` (`entry_id`),
  CONSTRAINT `fk_ucf_entry` FOREIGN KEY (`entry_id`) REFERENCES `unit_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D02 §3.10 — أعلام تجاوز الطاقة وتخليصها: لا اعتمادَ موقعٍ قبل الحسم';

-- ── Table: unit_effects ──
CREATE TABLE `unit_effects` (
  `pe_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `source_unit_id` bigint unsigned NOT NULL COMMENT 'الوحدة المصدر (fin_unit_records.id أو سجل الوحدة)',
  `domain` enum('sales','suppliers','workforce','fleet','financiers','maintenance') COLLATE utf8mb4_unicode_ci NOT NULL,
  `effect_kind` enum('production','container_consumption','hours','depreciation','charge','incentive_base') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `stage` enum('primary','financial') COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` enum('Applied','Proposed','Approved','Posted','Reversed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `fin_event_ref` bigint unsigned DEFAULT NULL COMMENT 'حدث FES عند بوابة الاستحقاق — الخيط متصل ولا جدول مال ثانٍ',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pe_id`),
  UNIQUE KEY `uq_ue_effect` (`company_id`,`source_unit_id`,`domain`,`effect_kind`,`stage`),
  KEY `ix_ue_stage` (`company_id`,`stage`,`state`,`period`),
  CONSTRAINT `ck_ue_financial_posted` CHECK (((`stage` <> _utf8mb4'financial') or (`state` <> _utf8mb4'Posted') or ((`approved_by` is not null) and (`fin_event_ref` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §12: طبقة التدرّج التشغيلية — الأولي يكتب في الجداول القائمة وهذا سجل تتبع؛ ولا financial/Posted إلا باعتماد الإدارة والمالية (CHECK)';

-- ── Table: unit_entries ──
CREATE TABLE `unit_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `entry_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'server-assigned — هويةٌ ثابتة',
  `entry_date` date NOT NULL,
  `project_id` int unsigned NOT NULL COMMENT 'ops_projects (D01) — مرجعٌ مرن',
  `contract_id` int unsigned DEFAULT NULL COMMENT 'sales contract (S05) — مرجعٌ مرن',
  `contract_line_id` int unsigned DEFAULT NULL COMMENT 'بندُ البيع المنفَّذ (P-02) — NULL = غيرُ موصولٍ بعد',
  `plan_period_id` int unsigned DEFAULT NULL COMMENT 'شهرُ الخطة (P-03) الذي تخصّه',
  `operational_site_id` int unsigned DEFAULT NULL COMMENT 'نطاقُ العقد التشغيلي (P-01)',
  `equipment_id` int unsigned DEFAULT NULL COMMENT 'fleet (S03) — مرجعٌ مرن',
  `operator_employee_id` int unsigned DEFAULT NULL COMMENT 'employees (ADM) — مرجعٌ مرن',
  `supplier_entity_id` int unsigned DEFAULT NULL COMMENT 'entities (S02) — مرجعٌ مرن',
  `supervisor_id` int unsigned DEFAULT NULL COMMENT 'employees — عند وجود مشرف',
  `unit_type` enum('hour','ton','meter','cbm','day','shift','trip') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'dictionary; finance-enabled by contract',
  `qty` decimal(14,2) NOT NULL COMMENT 'كمية الواقعة المسجّلة',
  `record_basis` enum('contract','analytical') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'contract',
  `capacity_flag` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'over daily capacity (§3.10)',
  `qty_billable` tinyint(1) DEFAULT NULL COMMENT 'M-24 ①: هل الكميةُ نفسُها مفوترةٌ للعميل؟ NULL=لم يُحكم (مفوترة) · 0=لا (إعادةُ تنفيذٍ لعيب) · 1=نعم صراحةً',
  `qty_ruling_note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سببُ الحكم — إلزامٌ عند qty_billable=0',
  `qty_decided_by` int unsigned DEFAULT NULL COMMENT 'مَن حكم — الحكمُ باسم صاحبه',
  `qty_decided_at` datetime DEFAULT NULL,
  `shift` enum('day','night') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'field sheet / meter / weigh ticket',
  `txn_ref` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'غلاف العملية التشغيلية (§2.4)',
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('draft','submitted','site_approved','parties_review','parties_approved','sales_approved','on_hold','converted','superseded','reversed','returned','rejected','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `revision_no` smallint NOT NULL DEFAULT '0' COMMENT '0 = الأصل',
  `current_round` smallint unsigned NOT NULL DEFAULT '1' COMMENT 'الجولة الجارية — تزيد مع كل إعادةٍ للموقع (UX-03 §8.2)',
  `revises_entry_id` int unsigned DEFAULT NULL COMMENT 'الواقعة التي تصحّحها هذه المراجعة',
  `revision_kind` enum('adjustment','reversal','split','merge') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `superseded_by_id` int unsigned DEFAULT NULL COMMENT 'مؤشرٌ أماميٌّ إلى المراجعة الخالفة',
  `converted_at` datetime DEFAULT NULL COMMENT 'لحظة التحوّل المالي',
  `event_id` int unsigned DEFAULT NULL COMMENT 'الحدث الجذري (D04) — مرجعٌ مرن',
  `entered_by` int unsigned DEFAULT NULL,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entry_no` (`company_id`,`entry_no`),
  UNIQUE KEY `uq_sync` (`company_id`,`sync_uuid`),
  KEY `ix_date` (`company_id`,`entry_date`),
  KEY `ix_project` (`company_id`,`project_id`,`state`),
  KEY `ix_state` (`company_id`,`state`),
  KEY `ix_parties` (`company_id`,`supplier_entity_id`,`operator_employee_id`),
  KEY `ix_qty_billable` (`company_id`,`qty_billable`),
  KEY `ix_ue_plan_keys` (`contract_line_id`,`plan_period_id`),
  KEY `ix_ue_site` (`operational_site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D02 §3.1 — سجلّ الواقعة: مصدر الحقيقة الوحيد للوحدة التشغيلية';

-- ── Table: unit_party_awards ──
CREATE TABLE `unit_party_awards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `source_kind` enum('timesheet','unit_record') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_ref` int unsigned NOT NULL COMMENT 'معرّف الصف في جدول المصدر',
  `party` enum('client','supplier','operator','supervisor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_ref` int unsigned DEFAULT NULL COMMENT 'العميل/المورد/الموظف حسب الطرف',
  `contract_ref` int unsigned DEFAULT NULL COMMENT 'عقد هذا الطرف — أو سياسة حافزه',
  `award_unit_type` enum('hour','ton','meter','cbm','day','shift','trip') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `award_qty` decimal(14,2) NOT NULL COMMENT 'الكمية بوحدة عقد هذا الطرف',
  `entitlement_state` enum('due','partial','not_due','pending','rejected','settlement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'due',
  `entitlement_pct` decimal(5,2) NOT NULL DEFAULT '100.00',
  `qty_due` decimal(14,2) GENERATED ALWAYS AS (round(((`award_qty` * `entitlement_pct`) / 100),2)) STORED COMMENT 'محسوبٌ خادميًّا — لا يُكتب يدويًّا',
  `unit_price` decimal(14,2) DEFAULT NULL COMMENT 'سعر وحدة عقد هذا الطرف',
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'عملة عقده — لا تُجمع فوق غيرها',
  `policy_rule` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم البند المطبَّق',
  `policy_snapshot` json DEFAULT NULL COMMENT 'لقطة القاعدة وقت الحكم — للتدقيق الرجعي',
  `unavailable_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سبب التعذّر معلنًا — لا رقمَ ملفَّق',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL COMMENT 'حذفٌ ناعم — شرط بوابة المستأجر',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_party_award` (`company_id`,`source_kind`,`source_ref`,`party`),
  KEY `ix_state` (`company_id`,`party`,`entitlement_state`),
  KEY `ix_source` (`company_id`,`source_kind`,`source_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D02 §3.7 — أحكام استحقاق الأطراف: لكل طرفٍ وحدتُه وكميتُه ونسبتُه';

-- ── Table: unit_state_changes ──
CREATE TABLE `unit_state_changes` (
  `chg_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `scope_type` enum('unit','equipment','site','contract') COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_id` int unsigned NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `field_changed` enum('time_state','responsible_party','quantity','classification') COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_before` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_after` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'المستند المؤيد إلزامي',
  `estimated_impact_json` json NOT NULL COMMENT 'الأثر المقدَّر لكل طرف — قبل الإرسال',
  `state` enum('Draft','Pending','Approved','Rejected','Applied','Reversed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `requested_by` int NOT NULL,
  `applied_at` datetime DEFAULT NULL,
  `reversal_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chg_id`),
  KEY `ix_usc_scope` (`company_id`,`scope_type`,`scope_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §6: تغيير حالة الوحدات — ولا Applied إلا من Approved، والمقيَّد يُعكس لا يُعدَّل';

-- ── Table: unit_time_log ──
CREATE TABLE `unit_time_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `log_date` date NOT NULL,
  `shift` enum('day','night') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` int unsigned NOT NULL,
  `equipment_id` int unsigned NOT NULL,
  `operator_employee_id` int unsigned DEFAULT NULL,
  `supplier_entity_id` int unsigned DEFAULT NULL,
  `time_from` time DEFAULT NULL,
  `time_to` time DEFAULT NULL,
  `hours` decimal(6,2) NOT NULL COMMENT 'مدّة الفترة — زمنٌ لا وحدةُ فوترة',
  `ops_state` enum('actual_work','standby','tech_breakdown','supplier_stop','operator_stop','client_stop','fuel_logistics_stop','planned_stop','force_majeure','unlogged') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cause_note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_party` enum('company','supplier','operator','client','planned','force_majeure','none') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `obligation_type` enum('fuel','access_road','loading_equipment','equipment_readiness','operators','permits_safety','utilities','catering_camp','force_majeure') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'بندُ الالتزام المسؤول (نفسُ قاموس contract_obligations) — NULL مشروعٌ لـactual_work وحدَه (هـ-1 · يفرضه الحارس)',
  `billable` tinyint(1) DEFAULT NULL COMMENT 'حكمُ الفوترة: أيُفوتر هذا الزمنُ على العميل؟ لقطةٌ لا اشتقاق (هـ-3)',
  `supplier_countable` tinyint(1) DEFAULT NULL COMMENT 'حكمُ المورد: أيُحتسب هذا الزمنُ في استحقاقه؟ لقطةٌ لا اشتقاق',
  `operator_countable` tinyint(1) DEFAULT NULL COMMENT 'حكمُ المشغّل: أيُحتسب هذا الزمنُ في استحقاقه؟ لقطةٌ لا اشتقاق',
  `decided_by` int unsigned DEFAULT NULL COMMENT 'مَن اعتمد الإسناد (المشرف · ق-4). NULL أي سطرٌ ما قبل المصفوفة — لا يُملأ رجعيًّا',
  `decided_at` datetime DEFAULT NULL COMMENT 'لحظةُ اعتماد الإسناد — وغيابُه وسمُ «ما قبل المصفوفة» بنيويًّا',
  `objection_state` enum('none','objected','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT 'الاعتراضُ المصغَّر (ق-25) — والبندُ المعترَضُ عليه لا يجمّد بقيةَ الواقعة',
  `objection_ref` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'مرجعُ الاعتراض — مستندٌ أو محضرٌ يحسمه الدور 19',
  `objection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'سببُ الاعتراض — إلزاميٌّ عند الاعتراض (يفرضه التطبيق)',
  `entry_id` int unsigned DEFAULT NULL COMMENT 'سطر unit_entries المشتقّ (نموذج الساعة)',
  `entered_by` int unsigned DEFAULT NULL,
  `sync_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sync` (`company_id`,`sync_uuid`),
  KEY `ix_day` (`company_id`,`log_date`,`equipment_id`),
  KEY `ix_state` (`company_id`,`ops_state`),
  KEY `ix_resp` (`company_id`,`resp_party`),
  KEY `ix_attribution` (`company_id`,`obligation_type`,`decided_at`),
  KEY `ix_objection` (`company_id`,`objection_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D02 §3.3 — سجلّ الزمن التشغيلي: ماذا حدث لكل ساعةٍ من الوقت المتاح';

-- ── Table: units_of_measure ──
CREATE TABLE `units_of_measure` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `uom_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` enum('زمن','وزن','طول','حجم','عدد') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'عدد',
  `factor` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_of_measure_company_code` (`company_id`,`uom_code`),
  KEY `idx_uom_scope` (`company_id`,`is_deleted`),
  KEY `idx_uom_cat` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: user_capacities ──
CREATE TABLE `user_capacities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `person_id` int DEFAULT NULL COMMENT 'employees.id — NULL للخارجي بلا سجل موظف',
  `account_id` int NOT NULL COMMENT 'users.id — حسابُ دخولٍ واحدٌ لكل الصفات',
  `capacity_type` enum('employee','project_employee','operator','technician','shift_supervisor','project_manager','supplier_supervisor','client_rep','auditor','executive') COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'حزمةُ الصلاحيات المرتبطة بالصفة (roles.id)',
  `scope_type` enum('company','project','site','supplier','client') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'company',
  `scope_id` int DEFAULT NULL COMMENT 'معرّفُ النطاق — إلزاميٌّ لغير company',
  `source_type` enum('contract','delegation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` int DEFAULT NULL COMMENT 'مرجعُ المصدر — إلزاميٌّ للعقد',
  `source_note` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'إعلانُ التفويض الموروث ونحوه',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','frozen','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `state_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_uc_capacity` (`account_id`,`capacity_type`,`scope_type`,`scope_id`,`valid_from`),
  KEY `ix_uc_account_state` (`account_id`,`state`),
  KEY `ix_uc_person` (`person_id`),
  KEY `ix_uc_company` (`company_id`),
  KEY `ix_uc_scope` (`scope_type`,`scope_id`),
  CONSTRAINT `ck_uc_scope` CHECK (((`scope_type` = _utf8mb4'company') or (`scope_id` is not null))),
  CONSTRAINT `ck_uc_source` CHECK (((`source_type` <> _utf8mb4'contract') or (`source_id` is not null))),
  CONSTRAINT `ck_uc_state` CHECK (((`state` = _utf8mb4'active') or ((`state_reason` is not null) and (`state_at` is not null)))),
  CONSTRAINT `ck_uc_window` CHECK (((`valid_to` is null) or (`valid_to` >= `valid_from`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='H-15 · USR-01 §2/§9.1 — طبقةُ الصفات: تعددٌ وتزامنٌ وانتهاءٌ آلي';

-- ── Table: users ──
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الاسم الثلاثي',
  `username` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم المستخدم',
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'البريد',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'كلمة المرور',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم الهاتف',
  `role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'رقم الصلاحية',
  `company_id` int DEFAULT NULL COMMENT 'رقم الشركة',
  `employee_id` int DEFAULT NULL COMMENT 'الموظف المرتبط بهذا الحساب',
  `supplier_entity_id` int DEFAULT NULL COMMENT 'H-20: موردُ هذا الحساب — إلزامٌ وظيفيٌّ لدور مشرف الموردين (8)، والحارسُ يقرؤه حصرًا',
  `role_id` int DEFAULT NULL COMMENT 'رقم الصلاحية',
  `position_id` int DEFAULT NULL COMMENT 'جسر المنصب (ADR-07/K6) — nullable: NULL = السلوك القائم عبر role كما هو',
  `status` enum('active','inactive','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'الحالة',
  `force_password_change` tinyint(1) NOT NULL DEFAULT '0',
  `temp_password_set_at` timestamp NULL DEFAULT NULL,
  `project_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT 'المشروع',
  `contract_id` int DEFAULT '0' COMMENT 'العقد',
  `parent_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT 'المستخدم الاب',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'انشئ في',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'عدل في',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT 'اخر دخول',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'محذوف',
  `deleted_at` datetime DEFAULT NULL COMMENT 'وقت الحذف',
  `deleted_by` int DEFAULT NULL COMMENT 'الحاذف',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_employee` (`employee_id`),
  KEY `idx_contract_id` (`contract_id`),
  KEY `idx_users_company_id` (`company_id`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_is_deleted` (`is_deleted`),
  KEY `idx_users_position` (`position_id`),
  KEY `ix_users_supplier` (`supplier_entity_id`),
  CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_supplier` FOREIGN KEY (`supplier_entity_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: visibility_audit_log ──
CREATE TABLE `visibility_audit_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `element_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_mode` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_mode` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'open·closed·inherit·grant_expired·denied_self',
  `actor` int NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `affected_count` int NOT NULL DEFAULT '0',
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_val_element` (`element_code`),
  KEY `ix_val_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ADM-01 §2 — «لا تغييرَ صامت»: كلُّ فتحٍ وإغلاقٍ بفاعله وسببه ومدته';

-- ── Table: visibility_keys ──
CREATE TABLE `visibility_keys` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `element_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_type` enum('account','capacity_type','department','project','supplier','client') COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'معرّفُ النطاق — رقمٌ أو كودُ فئة',
  `mode` enum('open','closed','inherit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'إلزاميٌّ لغير inherit (CHECK)',
  `granted_by` int NOT NULL,
  `granted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL COMMENT 'إلزاميٌّ لفتح الحساس (حارسُ الخدمة)',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vk_key` (`company_id`,`element_code`,`scope_type`,`scope_id`),
  KEY `ix_vk_element` (`element_code`),
  KEY `ix_vk_scope` (`scope_type`,`scope_id`),
  CONSTRAINT `fk_vk_element` FOREIGN KEY (`element_code`) REFERENCES `portal_elements` (`element_code`),
  CONSTRAINT `ck_vk_reason` CHECK (((`mode` = _utf8mb4'inherit') or (`reason` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ADM-01 §2 — مفاتيحُ الظهور بنطاقاتها الستة وأولويتها المحسومة';

-- ── Table: waivers_reversals ──
CREATE TABLE `waivers_reversals` (
  `ovr_id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `action` enum('waive','reverse','suspend','reduce') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مرجع الأصل — إلزامي',
  `source_id` bigint unsigned NOT NULL,
  `amount_before` decimal(18,2) DEFAULT NULL,
  `amount_after` decimal(18,2) DEFAULT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approvals_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ovr_id`),
  KEY `ix_wr_source` (`source_type`,`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §8: الإعفاء والعكس والتعليق والتخفيض — Insert-only ولا حذف للأصل أبدًا';

-- ── Table: worker_backup ──
CREATE TABLE `worker_backup` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `backup_employee_id` int NOT NULL,
  `backup_type` enum('احتياطي','مؤقت') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'احتياطي',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backup` (`employee_id`,`backup_employee_id`,`backup_type`),
  KEY `idx_wb_company` (`company_id`),
  KEY `idx_wb_backup` (`backup_employee_id`),
  CONSTRAINT `fk_wb_backup_emp` FOREIGN KEY (`backup_employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_wb_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_contract ──
CREATE TABLE `worker_contract` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'كود العقد — يدوي (قرار 12)',
  `contract_type` enum('سنوي','غير محدّد','مشروع','موسمي','مؤقت','بالساعة','بالإنتاج','استشاري/إشرافي','احتياطي','تغطية مؤقتة','تجاري مؤقت') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `wage` decimal(12,2) DEFAULT NULL COMMENT 'مالي — إدخال يدوي',
  `wage_finance_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'تعليق مرجعي للإدارة المالية مستقبلاً',
  `wage_method` enum('شهري','بالساعة','بالوردية/اليوم','بالإنتاج','مقطوع') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'شهري',
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `state` enum('مسودة','نافذ','منتهٍ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مسودة',
  `rotation_pattern` enum('بلا','شهران+شهر','ثلاثة أشهر+15 يوم','مخصّص') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'بلا',
  `work_days` int DEFAULT NULL,
  `leave_days` int DEFAULT NULL,
  `next_rotation_date` date DEFAULT NULL,
  `planned_backup_id` int DEFAULT NULL COMMENT '→ worker_profile.id',
  `monthly_hours_base` int DEFAULT NULL COMMENT 'أساس توزيع المتغيّر (مثال 300)',
  `fixed_wage_ratio` decimal(5,2) DEFAULT NULL COMMENT 'نسبة الأجر الثابت % (مثال 30)',
  `billable_downtime` enum('استعداد العميل','+ عطل الصيانة','حسب الحدث') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_housing` decimal(12,2) DEFAULT NULL,
  `allow_food` decimal(12,2) DEFAULT NULL,
  `allow_site` decimal(12,2) DEFAULT NULL,
  `allow_transport` decimal(12,2) DEFAULT NULL,
  `allow_finance_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'تعليق مرجعي للبدلات — للمالية مستقبلاً',
  `leave_terms` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coverage_terms` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `termination_terms` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wc_worker` (`employee_id`),
  KEY `idx_wc_company` (`company_id`),
  KEY `idx_wc_state` (`state`),
  KEY `idx_wc_planned_backup` (`planned_backup_id`),
  CONSTRAINT `fk_wc_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_evaluation ──
CREATE TABLE `worker_evaluation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `period` date DEFAULT NULL,
  `score` decimal(6,2) DEFAULT NULL COMMENT 'محسوبٌ مبدئياً يدوي',
  `incentive_penalty_type` enum('بلا','حافز','جزاء') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'بلا',
  `amount` decimal(12,2) DEFAULT NULL COMMENT 'مالي — يدوي',
  `amount_finance_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'تعليق مرجعي للمالية لاحقاً',
  `operating_hours` decimal(10,2) DEFAULT NULL,
  `attendance_rate` decimal(5,2) DEFAULT NULL,
  `productivity` decimal(10,2) DEFAULT NULL,
  `misuse_faults` int DEFAULT NULL,
  `fuel_consumption` decimal(10,2) DEFAULT NULL,
  `safety_score` decimal(5,2) DEFAULT NULL,
  `state` enum('مسودة','معتمد','مرحّل') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مسودة',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_we_worker` (`employee_id`),
  KEY `idx_we_company` (`company_id`),
  KEY `idx_we_state` (`state`),
  CONSTRAINT `fk_we_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_evaluation_kpi ──
CREATE TABLE `worker_evaluation_kpi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `evaluation_id` int NOT NULL,
  `kpi_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `score` decimal(6,2) DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wek_eval` (`evaluation_id`),
  CONSTRAINT `fk_wek_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `worker_evaluation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: worker_leave_absence ──
CREATE TABLE `worker_leave_absence` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `event_class` enum('مخطّط','طارئ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مخطّط' COMMENT 'مخطّط=إجازة/تناوب · طارئ=غياب',
  `event_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'تبادلية·اعتيادية·مأمورية | غياب مفاجئ·انقطاع·هروب·مرض·إصابة·أسري·وفاة',
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `substitute_id` int DEFAULT NULL COMMENT '→ worker_profile.id',
  `rotation_pattern` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `coverage_impact` enum('مغطًّى','فجوة جزئية','فجوة حرجة') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` enum('عودة للعمل','تحويل لإجازة','إنهاء وتسوية') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('مطلوب','معتمد','مفتوح','مُغطًّى','منتهٍ','مغلق') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مطلوب',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wla_worker` (`employee_id`),
  KEY `idx_wla_company` (`company_id`),
  KEY `idx_wla_state` (`state`),
  KEY `idx_wla_dates` (`date_from`,`date_to`),
  CONSTRAINT `fk_wla_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_movement ──
CREATE TABLE `worker_movement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `direction` enum('التحاق أول','عودة من إجازة','مغادرة لإجازة/مأمورية','نقل بين مشاريع','مغادرة نهائية') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `allocation_id` int DEFAULT NULL COMMENT '→ worker_allocation.id (قيمة)',
  `origin` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_state` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_city` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_project_id` int DEFAULT NULL COMMENT 'بالقيمة إلى project.id',
  `destination_state` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_city` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transport_mode` enum('بري','جوي','ترتيب مورد') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departure_date` date DEFAULT NULL,
  `expected_arrival` date DEFAULT NULL,
  `actual_arrival` date DEFAULT NULL,
  `received_by` int DEFAULT NULL COMMENT 'بالقيمة إلى employees.id (مشرف الموقع)',
  `housing_unit_id` int DEFAULT NULL COMMENT '→ housing_unit.id',
  `site_zone` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `safety_kit_received` tinyint(1) DEFAULT '0',
  `custody_received` tinyint(1) DEFAULT NULL COMMENT 'مؤجّل (S09) — يبقى فارغاً الآن',
  `ready_date` date DEFAULT NULL,
  `transfer_type` enum('مؤقت','دائم','إعادة تخصيص') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'للنقل بين المشاريع',
  `from_project_id` int DEFAULT NULL,
  `to_project_id` int DEFAULT NULL,
  `state` enum('مسودة','أمرٌ صادر','في الطريق','وصل','مستلَم بالموقع','جاهزٌ للعمل','ملغى') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مسودة',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wm_worker` (`employee_id`),
  KEY `idx_wm_company` (`company_id`),
  KEY `idx_wm_state` (`state`),
  CONSTRAINT `fk_wm_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_qualification ──
CREATE TABLE `worker_qualification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `record_type` enum('مؤهل','رخصة','خبرة','ترقية') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم الشهادة/الرخصة/الدرجة',
  `issuer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'نوع المعدة المرتبط بالرخصة',
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `accreditation_category` enum('مهارة معدة','اعتماد فني','دورة','شهادة','سلامة','فحص طبي','اعتماد موقع','تصريح') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proficiency_level` enum('مبتدئ','متوسط','متقدم','خبير') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_critical` tinyint(1) DEFAULT '0' COMMENT 'يمنع التخصيص عند انتهائه',
  `alert_lead_days` int DEFAULT '30',
  `document` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_ref` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'قرار الترقية/التدرّج وتاريخه',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wq_worker` (`employee_id`),
  KEY `idx_wq_company` (`company_id`),
  KEY `idx_wq_expiry` (`expiry_date`),
  KEY `idx_wq_critical` (`is_critical`),
  CONSTRAINT `fk_wq_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_restricted_site ──
CREATE TABLE `worker_restricted_site` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `project_id` int NOT NULL COMMENT 'بالقيمة إلى project.id',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_restricted` (`employee_id`,`project_id`),
  KEY `idx_wrs_company` (`company_id`),
  CONSTRAINT `fk_wrs_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_settlement ──
CREATE TABLE `worker_settlement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `worker_contract_id` int DEFAULT NULL COMMENT 'بالقيمة إلى worker_contract.id',
  `source_type` enum('شركة','مورد','مقاول') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settlement_party` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الجهة (شركة/مورد/مقاول) — نصّي الآن',
  `settlement_basis` enum('عمالة شركة','فاتورة مورد','مستخلص مقاول') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `net_amount` decimal(12,2) DEFAULT NULL COMMENT 'مالي — محسوبٌ من البنود/يدوي',
  `net_finance_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` enum('محتسب','معتمد','مدفوع') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'محتسب',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ws_worker` (`employee_id`),
  KEY `idx_ws_company` (`company_id`),
  KEY `idx_ws_state` (`state`),
  CONSTRAINT `fk_ws_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_settlement_line ──
CREATE TABLE `worker_settlement_line` (
  `id` int NOT NULL AUTO_INCREMENT,
  `settlement_id` int NOT NULL,
  `line_type` enum('مستحق','خصم') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wsl_set` (`settlement_id`),
  CONSTRAINT `fk_wsl_set` FOREIGN KEY (`settlement_id`) REFERENCES `worker_settlement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: workforce_requirement ──
CREATE TABLE `workforce_requirement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL COMMENT 'بالقيمة إلى project.id',
  `worker_category` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `required_qty` int NOT NULL DEFAULT '0',
  `available_qty` int DEFAULT '0',
  `shortage_qty` int DEFAULT '0',
  `surplus_qty` int DEFAULT '0',
  `is_critical` tinyint(1) DEFAULT '0',
  `priority` enum('عادية','عالية','حرجة') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'عادية',
  `need_date` date DEFAULT NULL,
  `fulfillment_stage` enum('مفتوح','استقطاب','ترشيح واعتماد','تعاقد','تحرّك','مُلبّى') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مفتوح',
  `state` enum('مخطّط','متوازن','عجز','فائض') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'مخطّط',
  `candidates_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'مرشّحون — إدخال يدوي (قرار 6)',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wr_company` (`company_id`),
  KEY `idx_wr_project` (`project_id`),
  KEY `idx_wr_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: workspace_cards ──
CREATE TABLE `workspace_cards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_ar` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_doc` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_service` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'الخدمةُ المالكةُ للحساب — لا تحسب اللوحة',
  `permission_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `counter_source` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cache_ttl` int NOT NULL DEFAULT '0' COMMENT '0 = حيٌّ بلا كاش (عدّاداتُ الانتظار)',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wc_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WSP-01 §7 — قاموسُ بطاقات المساحات بمالكيها';

-- ── Table: workspace_layouts ──
CREATE TABLE `workspace_layouts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` enum('department','project','supplier','client','equipment','person') COLLATE utf8mb4_unicode_ci NOT NULL,
  `layout_json` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'البطاقاتُ وترتيبُها لهذا النوع',
  `version` int NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wl` (`entity_type`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WSP-01 §7 — التخطيطُ بالنوع لا بالكيان (قاموسٌ عالمي)';

-- ── Table: workspace_navigation_log ──
CREATE TABLE `workspace_navigation_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `account_id` int NOT NULL,
  `from_layer` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_layer` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_ref` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result` enum('ok','denied') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ok',
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_wnl_account` (`account_id`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WSP-01 §7 — WorkspaceOpened · LayerSwitched · 403 مسجَّلة';

-- ── Table: workspace_prefs ──
CREATE TABLE `workspace_prefs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `account_id` int NOT NULL,
  `entity_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pinned_cards_json` text COLLATE utf8mb4_unicode_ci,
  `default_period` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'today',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wp` (`account_id`,`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── View: client_contracts ──
SET collation_connection = 'utf8mb4_unicode_ci';
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `client_contracts` AS select `c`.`id` AS `id`,`c`.`company_id` AS `company_id`,`c`.`contract_signing_date` AS `contract_signing_date`,`c`.`grace_period_days` AS `grace_period_days`,`c`.`contract_duration_months` AS `contract_duration_months`,`c`.`contract_duration_days` AS `contract_duration_days`,`c`.`equip_shifts_contract` AS `equip_shifts_contract`,`c`.`shift_contract` AS `shift_contract`,`c`.`equip_total_contract_daily` AS `equip_total_contract_daily`,`c`.`total_contract_permonth` AS `total_contract_permonth`,`c`.`total_contract_units` AS `total_contract_units`,`c`.`actual_start` AS `actual_start`,`c`.`actual_end` AS `actual_end`,`c`.`transportation` AS `transportation`,`c`.`accommodation` AS `accommodation`,`c`.`place_for_living` AS `place_for_living`,`c`.`workshop` AS `workshop`,`c`.`hours_monthly_target` AS `hours_monthly_target`,`c`.`forecasted_contracted_hours` AS `forecasted_contracted_hours`,`c`.`created_at` AS `created_at`,`c`.`updated_at` AS `updated_at`,`c`.`daily_work_hours` AS `daily_work_hours`,`c`.`daily_operators` AS `daily_operators`,`c`.`first_party` AS `first_party`,`c`.`second_party` AS `second_party`,`c`.`witness_one` AS `witness_one`,`c`.`witness_two` AS `witness_two`,`c`.`price_currency_contract` AS `price_currency_contract`,`c`.`paid_contract` AS `paid_contract`,`c`.`payment_time` AS `payment_time`,`c`.`guarantees` AS `guarantees`,`c`.`retention_pct` AS `retention_pct`,`c`.`advance_recovery_pct` AS `advance_recovery_pct`,`c`.`payment_date` AS `payment_date`,`c`.`contract_status` AS `contract_status`,`c`.`pause_state_before` AS `pause_state_before`,`c`.`pause_reason` AS `pause_reason`,`c`.`pause_date` AS `pause_date`,`c`.`resume_date` AS `resume_date`,`c`.`termination_type` AS `termination_type`,`c`.`termination_reason` AS `termination_reason`,`c`.`merged_with` AS `merged_with`,`c`.`status` AS `status`,`c`.`is_deleted` AS `is_deleted`,`c`.`deleted_at` AS `deleted_at`,`c`.`deleted_by` AS `deleted_by`,`c`.`project_id` AS `project_id`,`c`.`site_id` AS `site_id`,`c`.`readiness_state` AS `readiness_state`,`cos`.`id` AS `primary_scope_id`,`cos`.`site_id` AS `primary_site_id`,`cos`.`scope_name` AS `primary_scope_name` from (`contracts` `c` left join `contract_operational_sites` `cos` on(((`cos`.`contract_id` = `c`.`id`) and (`cos`.`is_primary` = 1) and (coalesce(`cos`.`is_deleted`,0) = 0))));

-- ── View: unified_fault_taxonomy ──
SET collation_connection = 'utf8mb4_unicode_ci';
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `unified_fault_taxonomy` AS select distinct `fc`.`main_category_code` AS `code`,`fc`.`main_category_name` AS `name`,`fc`.`equipment_type` AS `equipment_type`,'failure_codes' AS `source` from `failure_codes` `fc` where ((`fc`.`main_category_code` is not null) and (`fc`.`main_category_code` <> ''));

-- ── View: v_org_unit_heads ──
SET collation_connection = 'utf8mb4_unicode_ci';
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_org_unit_heads` AS select `u`.`unit_id` AS `unit_id`,`u`.`company_id` AS `company_id`,`u`.`unit_code` AS `unit_code`,`u`.`name_ar` AS `name_ar`,`a`.`person_id` AS `head_person_id`,`a`.`asg_id` AS `head_assignment_id`,`a`.`scope_type` AS `head_scope_type`,`a`.`scope_id` AS `head_scope_id` from (`org_units` `u` left join `org_assignments` `a` on(((`a`.`org_unit_id` = `u`.`unit_id`) and (`a`.`state` = 'active') and (curdate() between `a`.`valid_from` and `a`.`valid_to`) and `a`.`assignment_type_code` in (select `t`.`type_code` from `org_assignment_types` `t` where (`t`.`is_unit_head` = 1)))));

-- ── View: v_worker_billable_hours ──
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_worker_billable_hours` AS select `wp`.`id` AS `employee_id`,`t`.`date` AS `work_date`,cast(`t`.`operator` as unsigned) AS `operation_id`,coalesce(sum(`t`.`executed_hours`),0) AS `productive_hours`,coalesce(sum(`t`.`standby_hours`),0) AS `standby_hours`,coalesce(sum(`t`.`hr_fault`),0) AS `worker_downtime`,coalesce(sum(`t`.`maintenance_fault`),0) AS `maintenance_downtime`,greatest(((coalesce(sum(`t`.`executed_hours`),0) + coalesce(sum(`t`.`standby_hours`),0)) - coalesce(sum(`t`.`hr_fault`),0)),0) AS `billable_baseline` from (`employees` `wp` join `timesheet` `t` on((cast(`t`.`employee_id` as unsigned) = `wp`.`id`))) group by `wp`.`id`,`t`.`date`,cast(`t`.`operator` as unsigned);

-- ── View: v_worker_presence ──
SET collation_connection = 'utf8mb4_0900_ai_ci';
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_worker_presence` AS select `wp`.`id` AS `employee_id`,(case when (`wp`.`workforce_state` = 'منتهٍ') then 'منتهٍ' when exists(select 1 from `worker_leave_absence` `la` where ((`la`.`employee_id` = `wp`.`id`) and (`la`.`state` in ('معتمد','مفتوح','مُغطًّى')) and ((`la`.`date_from` is null) or (`la`.`date_from` <= curdate())) and ((`la`.`date_to` is null) or (`la`.`date_to` >= curdate())))) then 'خارج الموقع/إجازة' when exists(select 1 from `worker_movement` `m` where ((`m`.`employee_id` = `wp`.`id`) and (`m`.`state` in ('أمرٌ صادر','في الطريق')))) then 'في الطريق' when exists(select 1 from `equipment_drivers` `ed` where ((`ed`.`employee_id` = `wp`.`id`) and (`ed`.`status` = 1))) then 'داخل الموقع' else 'بانتظار التخصيص' end) AS `presence_state` from `employees` `wp`;

-- ── View: v_worker_worklog ──
SET collation_connection = 'utf8mb4_unicode_ci';
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_worker_worklog` AS select `wp`.`id` AS `employee_id`,`wp`.`name` AS `worker_name`,coalesce(`wp`.`worker_category`,'موظف') AS `worker_category`,coalesce(`wp`.`workforce_state`,'-') AS `worker_state`,(select count(distinct `o`.`id`) from (`equipment_drivers` `ed` join `operations` `o` on((`o`.`equipment` = `ed`.`equipment_id`))) where ((`ed`.`employee_id` = `wp`.`id`) and (`ed`.`status` = 1))) AS `operations_count`,(select coalesce(sum(`b`.`billable_baseline`),0) from `v_worker_billable_hours` `b` where (`b`.`employee_id` = `wp`.`id`)) AS `total_billable_hours`,(select count(0) from `worker_leave_absence` `la` where (`la`.`employee_id` = `wp`.`id`)) AS `leave_absence_count`,(select count(0) from `worker_movement` `m` where (`m`.`employee_id` = `wp`.`id`)) AS `movement_count`,(select count(0) from `worker_evaluation` `ev` where (`ev`.`employee_id` = `wp`.`id`)) AS `evaluation_count`,(select coalesce(sum(`ev`.`amount`),0) from `worker_evaluation` `ev` where ((`ev`.`employee_id` = `wp`.`id`) and (`ev`.`incentive_penalty_type` = 'حافز'))) AS `incentive_total`,(select coalesce(sum(`ev`.`amount`),0) from `worker_evaluation` `ev` where ((`ev`.`employee_id` = `wp`.`id`) and (`ev`.`incentive_penalty_type` = 'جزاء'))) AS `penalty_total` from `employees` `wp`;

SET FOREIGN_KEY_CHECKS = 1;
