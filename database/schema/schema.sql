-- ═══════════════════════════════════════════════════════════════════════════
-- EMS — مخطّط التثبيت الكامل (بنية فقط، بلا بيانات)
-- ─────────────────────────────────────────────────────────────────────────
-- المصدر: equipation_manage · التوليد: 2026-08-16 10:27:53
-- الجداول: 555 · المناظير: 7
-- يُستورد على قاعدةٍ فارغة عبر المُثبِّت. FOREIGN_KEY_CHECKS مُطفأٌ داخل
-- الملف لأن الجداول مرتّبةٌ أبجديًّا لا حسب تبعية المفاتيح الأجنبية.
-- مولَّدٌ آليًّا بـ `php database/migrate.php dump-schema` — لا يُحرَّر بيد.
-- ═══════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Table: achievement_attributions ──
CREATE TABLE `achievement_attributions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `work_item_ref` varchar(60) NOT NULL,
  `person_user_id` int(10) unsigned NOT NULL,
  `share_pct` decimal(5,2) NOT NULL,
  `share_kind` varchar(12) NOT NULL DEFAULT 'executive',
  `decided_by` int(10) unsigned NOT NULL COMMENT 'المكلِّف يقررها عند الإغلاق',
  `decided_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aa` (`work_item_ref`,`person_user_id`,`share_kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: achievement_certificates ──
CREATE TABLE `achievement_certificates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `eval_id` int(10) unsigned DEFAULT NULL,
  `snap_id` int(10) unsigned NOT NULL,
  `serial_no` varchar(40) NOT NULL,
  `verify_code` varchar(40) NOT NULL,
  `issued_by` int(11) NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `pdf_ref` varchar(190) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cert_serial` (`serial_no`),
  UNIQUE KEY `uq_cert_verify` (`verify_code`),
  UNIQUE KEY `uq_cert_snap` (`snap_id`),
  KEY `fk_cert_eval` (`eval_id`),
  CONSTRAINT `fk_cert_eval` FOREIGN KEY (`eval_id`) REFERENCES `evaluations` (`id`),
  CONSTRAINT `fk_cert_snap` FOREIGN KEY (`snap_id`) REFERENCES `achievement_snapshots` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='USR-01 §7-⑤ — الشهادةُ تُولَّد من الأرقام المقاسة ولا تُصدَر مرتين';

-- ── Table: achievement_records ──
CREATE TABLE `achievement_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `source_kind` varchar(24) NOT NULL COMMENT 'task|request|approval|work_order|unit|claim|ticket|corrective — الثمانية حصرًا',
  `source_ref` varchar(60) NOT NULL,
  `person_user_id` int(10) unsigned NOT NULL,
  `attribution` varchar(12) NOT NULL DEFAULT 'executive' COMMENT 'executive|supervisory|decision',
  `weight_pct` decimal(5,2) NOT NULL DEFAULT 100.00,
  `title` varchar(300) NOT NULL,
  `evidence_ref` varchar(200) NOT NULL COMMENT 'صفر إنجاز بلا دليل — AC-WFM-05',
  `recognized_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reversed_at` datetime DEFAULT NULL COMMENT 'يُعكس آليًّا إن عُكس أصله — AC-WFM-14',
  `reverse_reason` varchar(300) DEFAULT NULL,
  `event_ref` varchar(60) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'محرّك الإنجاز — لا إدخال يدوي',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ach` (`source_kind`,`source_ref`,`person_user_id`,`attribution`),
  KEY `ix_ach_person` (`company_id`,`person_user_id`,`recognized_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WF-03: منع التضاعف بنيوي بالمفتاح الفريد';

-- ── Table: achievement_snapshots ──
CREATE TABLE `achievement_snapshots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `person_id` int(11) DEFAULT NULL,
  `capacity_id` int(10) unsigned NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `metrics_json` text NOT NULL COMMENT 'المؤشراتُ السبعةُ بأرقامها — و«لا ينطبق» يُعلَن لا صفرًا',
  `computed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `source_fingerprint` varchar(64) NOT NULL COMMENT 'بصمةُ المصادر لحظةَ الحساب',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_snap` (`capacity_id`,`period_from`,`period_to`),
  KEY `ix_snap_person` (`person_id`),
  CONSTRAINT `fk_snap_capacity` FOREIGN KEY (`capacity_id`) REFERENCES `user_capacities` (`id`),
  CONSTRAINT `ck_snap_window` CHECK (`period_to` >= `period_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: action_events ──
CREATE TABLE `action_events` (
  `e_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `action_code` varchar(80) NOT NULL,
  `event_name` varchar(120) NOT NULL,
  `is_conditional` tinyint(1) NOT NULL DEFAULT 0,
  `condition_expr` varchar(255) DEFAULT NULL,
  `no_event_reason` varchar(255) DEFAULT NULL COMMENT 'فعلُ كتابةٍ بلا حدثٍ يحتاج تعليلًا مكتوبًا',
  PRIMARY KEY (`e_id`),
  UNIQUE KEY `uq_ae` (`action_code`,`event_name`),
  CONSTRAINT `fk_ae_action` FOREIGN KEY (`action_code`) REFERENCES `actions` (`action_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: action_execution_log ──
CREATE TABLE `action_execution_log` (
  `r_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `action_code` varchar(80) NOT NULL,
  `person_id` int(11) DEFAULT NULL,
  `subject_ref` varchar(120) DEFAULT NULL,
  `result` enum('allowed','denied') NOT NULL,
  `denied_by_guard` varchar(60) DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`r_id`),
  KEY `ix_ael_action` (`action_code`,`result`,`at`),
  KEY `ix_ael_company` (`company_id`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ACT-01 §8: Insert-only — لا تعديلَ ولا حذف';

-- ── Table: action_impact_log ──
CREATE TABLE `action_impact_log` (
  `il_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `action_code` varchar(80) NOT NULL,
  `impacted_type` enum('org_unit','person','party','screen') NOT NULL,
  `impacted_ref` varchar(64) NOT NULL,
  `effect` enum('notify','counter','data_change','state_change') NOT NULL,
  `subject_ref` varchar(120) DEFAULT NULL,
  `actor_person_id` int(11) DEFAULT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`il_id`),
  KEY `ix_ail_target` (`company_id`,`impacted_type`,`impacted_ref`,`seen`),
  KEY `ix_ail_action` (`action_code`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: action_impacts ──
CREATE TABLE `action_impacts` (
  `i_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `action_code` varchar(80) NOT NULL,
  `impacted_type` enum('org_unit','person','party','screen') NOT NULL,
  `impacted_ref` varchar(120) NOT NULL,
  `effect` enum('notify','counter','data_change','state_change') NOT NULL,
  `latency` enum('sync','async') NOT NULL DEFAULT 'async',
  PRIMARY KEY (`i_id`),
  KEY `ix_ai_action` (`action_code`),
  CONSTRAINT `fk_ai_action` FOREIGN KEY (`action_code`) REFERENCES `actions` (`action_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: action_writes ──
CREATE TABLE `action_writes` (
  `w_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `action_code` varchar(80) NOT NULL,
  `table_name` varchar(80) NOT NULL,
  `operation` enum('insert','update','delete','none') NOT NULL DEFAULT 'update',
  PRIMARY KEY (`w_id`),
  UNIQUE KEY `uq_aw` (`action_code`,`table_name`,`operation`),
  CONSTRAINT `fk_aw_action` FOREIGN KEY (`action_code`) REFERENCES `actions` (`action_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: actions ──
CREATE TABLE `actions` (
  `action_code` varchar(80) NOT NULL COMMENT 'كودٌ فريدٌ للفعل — مفتاحُ كل ما بعده',
  `name_ar` varchar(160) NOT NULL,
  `module_id` int(11) DEFAULT NULL COMMENT 'modules.id — الشاشةُ الأم (NULL لفعلٍ عابرٍ للشاشات)',
  `placement` enum('header','row','tab','bulk','context') NOT NULL DEFAULT 'row',
  `handler_class` varchar(160) DEFAULT NULL COMMENT 'الخدمةُ المنفِّذة — ولا فعلَ ينفّذ منطقًا في الشاشة',
  `handler_method` varchar(120) DEFAULT NULL,
  `handler_path` varchar(190) DEFAULT NULL COMMENT 'مسارُ المعالج الإجرائي (المستخرَجُ من action_guard) حين لا صنفَ له',
  `is_write` tinyint(1) NOT NULL DEFAULT 0,
  `guards_json` text DEFAULT NULL COMMENT 'الحرّاسُ بترتيب الفحص المعلن — وفعلُ كتابةٍ بلا حرّاس يُرفض',
  `precondition_expr` varchar(255) DEFAULT NULL COMMENT 'الشرطُ المسبق — يُفحص في الخادم لا بإخفاء الزر',
  `reverse_action_code` varchar(80) DEFAULT NULL COMMENT 'فعلُ العكس — إلزاميٌّ لكل فعلٍ ماليٍّ أو تعاقدي',
  `is_financial` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ماليٌّ أو تعاقديٌّ — يستوجب عكسًا',
  `owner_doc` varchar(40) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`action_code`),
  KEY `ix_act_module` (`module_id`),
  KEY `ix_act_write` (`is_write`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ACT-01 §8: سجلُّ الأفعال — ولا زرَّ في واجهةٍ بلا صفٍّ هنا';

-- ── Table: activities ──
CREATE TABLE `activities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `activity_code` varchar(50) NOT NULL,
  `activity_type` enum('زيارة عميل','اجتماع موقع','افتراضي','هاتفي','تفاوضي','زيارة مناجم') NOT NULL,
  `entity_type` enum('opportunity','client','contract') NOT NULL DEFAULT 'client',
  `entity_id` int(10) unsigned DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `activity_date` date DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `outcome` text DEFAULT NULL,
  `is_negotiation` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_activities_company_code` (`company_id`,`activity_code`),
  KEY `idx_act_scope` (`company_id`,`is_deleted`),
  KEY `idx_act_entity` (`entity_type`,`entity_id`),
  KEY `idx_act_type` (`activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: activity_logs ──
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `contract_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL COMMENT 'لقطة الموظف الفاعل وقت الحدث',
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `role_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `screen_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `button_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` bigint(20) unsigned DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `http_method` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_status` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL COMMENT 'super_admins.id',
  `action_type` varchar(50) NOT NULL COMMENT 'create|update|delete|approve|reject|suspend|activate|login|logout',
  `target_name` varchar(200) DEFAULT NULL COMMENT 'human-readable target (company name, plan name, etc.)',
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_audit_admin` (`admin_id`),
  KEY `idx_admin_audit_action` (`action_type`),
  KEY `idx_admin_audit_date` (`created_at`),
  CONSTRAINT `fk_admin_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `super_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: admin_companies ──
CREATE TABLE `admin_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد',
  `company_name` varchar(200) NOT NULL COMMENT 'اسم الشركة',
  `commercial_registration` varchar(120) DEFAULT NULL COMMENT 'السجل التجاري',
  `sector` varchar(100) DEFAULT NULL COMMENT 'القطاع',
  `country` varchar(100) DEFAULT NULL COMMENT 'البلد',
  `city` varchar(100) DEFAULT NULL COMMENT 'المدينة',
  `tax_number` varchar(120) DEFAULT NULL COMMENT 'الرقم الضريبي',
  `email` varchar(150) NOT NULL COMMENT 'البريد',
  `phone` varchar(30) DEFAULT NULL COMMENT 'رقم الهاتف',
  `address` text DEFAULT NULL COMMENT 'العنوان',
  `postal_address` text DEFAULT NULL COMMENT 'العنوان البريدي',
  `logo_path` varchar(255) DEFAULT NULL COMMENT 'الشعار',
  `plan_id` int(11) DEFAULT NULL COMMENT 'خطة الاشتراك',
  `modules_enabled` text DEFAULT NULL,
  `name` varchar(200) DEFAULT NULL COMMENT 'الاسم',
  `company_name_ar` varchar(200) DEFAULT NULL COMMENT 'اسم الشركة عربي',
  `company_name_en` varchar(200) DEFAULT NULL COMMENT 'اسم الشركة انحليزي',
  `status` enum('pending','active','suspended','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'الحالة',
  `subscription_start` date DEFAULT NULL COMMENT 'بداية الاشتراك',
  `subscription_end` date DEFAULT NULL COMMENT 'نهاية الاشتراك',
  `users_count` int(11) NOT NULL DEFAULT 0 COMMENT 'عدد المستخدمين',
  `max_users` int(11) NOT NULL DEFAULT 0 COMMENT 'المستخدمين',
  `max_equipments` int(11) NOT NULL DEFAULT 0 COMMENT 'المعدات',
  `max_projects` int(11) NOT NULL DEFAULT 0 COMMENT 'المشاريع',
  `currency` varchar(20) NOT NULL DEFAULT 'SAR' COMMENT 'العملة',
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Riyadh' COMMENT 'المنطقة الزمنية',
  `notes` text DEFAULT NULL COMMENT 'الملاحظات',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'الانشاء',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'التعديل',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_companies_email` (`email`),
  UNIQUE KEY `uq_admin_companies_commercial_registration` (`commercial_registration`),
  KEY `idx_admin_companies_plan` (`plan_id`),
  KEY `idx_admin_companies_status` (`status`),
  CONSTRAINT `fk_admin_companies_plan` FOREIGN KEY (`plan_id`) REFERENCES `admin_subscription_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: admin_subscription_plans ──
CREATE TABLE `admin_subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_name` varchar(100) NOT NULL COMMENT 'اسم الخطة',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'السعر',
  `max_users` int(11) NOT NULL DEFAULT 0 COMMENT '0 = unlimited المستخدمين',
  `max_projects` int(11) NOT NULL DEFAULT 0 COMMENT 'المشاريع',
  `max_equipments` int(11) NOT NULL DEFAULT 0 COMMENT 'المعدات',
  `features` text DEFAULT NULL COMMENT 'المميزات',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'الترتيب',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'نشط',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'الانشاء',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'التعديل',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: admin_subscription_requests ──
CREATE TABLE `admin_subscription_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد',
  `company_id` int(11) DEFAULT NULL COMMENT 'null if company not  created yet رقم الشركة',
  `company_name` varchar(200) NOT NULL COMMENT 'اسم الشركة',
  `email` varchar(150) NOT NULL COMMENT 'البريد',
  `phone` varchar(30) DEFAULT NULL COMMENT 'الهاتف',
  `plan_id` int(11) DEFAULT NULL COMMENT 'خطة الاشتراك',
  `message` text DEFAULT NULL COMMENT 'message from the requesting company جميع بيانات الشركة ',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT 'الحالة',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'super_admins.id المراجع',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'زمن المراجعه',
  `review_note` text DEFAULT NULL COMMENT 'الملاحظات',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'الانشاء',
  PRIMARY KEY (`id`),
  KEY `idx_admin_sub_req_status` (`status`),
  KEY `idx_admin_sub_req_plan` (`plan_id`),
  KEY `fk_admin_sub_req_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_admin_sub_req_plan` FOREIGN KEY (`plan_id`) REFERENCES `admin_subscription_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_admin_sub_req_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `super_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: api_tokens ──
CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL COMMENT 'sha256 hex ┘ä┘äÏ¬┘ê┘â┘å Ïº┘äÏ«Ïº┘à',
  `device` varchar(150) DEFAULT NULL COMMENT '┘êÏÁ┘ü ÏºÏ«Ï¬┘èÏºÏ▒┘è ┘ä┘äÏ¼┘çÏºÏ▓/Ïº┘äÏ¬ÏÀÏ¿┘è┘é',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_user` (`user_id`),
  KEY `idx_active` (`revoked`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: approval_chains ──
CREATE TABLE `approval_chains` (
  `chain_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` int(10) unsigned NOT NULL,
  `seq_no` tinyint(3) unsigned NOT NULL,
  `approver_role` enum('site','operations','suppliers','workforce','finance') NOT NULL,
  `periodicity` enum('daily','weekly','monthly') NOT NULL DEFAULT 'weekly' COMMENT 'الدورية تُختار بالسياسة — لا افتراضية صامتة',
  `sla_hours` int(10) unsigned DEFAULT NULL COMMENT 'المهلة المعلنة — تجاوزها تصعيد لا إغلاق',
  `skip_if_not_applicable` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`chain_id`),
  UNIQUE KEY `uq_ac_seq` (`policy_id`,`seq_no`),
  CONSTRAINT `fk_ac_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §4: سلسلة الاعتماد — لا تُفتح حلقة قبل سابقتها';

-- ── Table: approval_links ──
CREATE TABLE `approval_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `source_kind` varchar(30) NOT NULL COMMENT 'request|document|…',
  `source_ref` varchar(60) NOT NULL,
  `action_code` varchar(60) NOT NULL COMMENT 'رمز فعل الاعتماد — الورقة 09',
  `step_no` smallint(5) unsigned NOT NULL DEFAULT 1,
  `approver_user_id` int(10) unsigned DEFAULT NULL,
  `approver_role` varchar(120) DEFAULT NULL COMMENT 'أو دور مستقبِل يُحل وقت العرض',
  `status` varchar(16) NOT NULL DEFAULT 'pending' COMMENT 'pending|approved|returned|rejected|withdrawn',
  `sla_due_at` datetime DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_note` varchar(400) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'المنشئ',
  `created_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المنشئ لحظة الفعل',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(10) unsigned DEFAULT NULL COMMENT 'المعتمِد',
  `approved_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المعتمِد',
  `approved_at` datetime DEFAULT NULL,
  `delegation_ref` varchar(60) DEFAULT NULL COMMENT 'مرجع التفويض إن اعتُمد به',
  `parent_ref` varchar(60) DEFAULT NULL COMMENT 'المرجع الأب',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_al` (`source_kind`,`source_ref`,`action_code`,`step_no`),
  KEY `ix_al_approver` (`company_id`,`approver_user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WFM: صفر موافقة بلا صلاحية ونطاق — تُقرأ من E-04';

-- ── Table: approval_requests ──
CREATE TABLE `approval_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `payload` longtext NOT NULL,
  `requested_by` int(11) NOT NULL,
  `current_step` int(11) DEFAULT 1,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_approval_entity` (`entity_type`,`entity_id`),
  KEY `idx_approval_status` (`status`),
  KEY `idx_approval_user` (`requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: approval_signatures ──
CREATE TABLE `approval_signatures` (
  `sig_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `document_type` varchar(60) NOT NULL,
  `document_id` bigint(20) unsigned NOT NULL,
  `step` varchar(40) NOT NULL DEFAULT 'approve' COMMENT 'الخطوة/الحلقة — فلا يُسجَّل توقيع مرتين لخطوة',
  `person_id` int(11) NOT NULL,
  `capacity_id` int(11) DEFAULT NULL,
  `auth_id` int(10) unsigned DEFAULT NULL COMMENT 'مرجع التفويض — NULL فقط لما قبل تفعيل الحارس',
  `org_asg_id` int(10) unsigned DEFAULT NULL COMMENT 'مرجع التكليف التنظيمي المعتمِد — ORG-01 O8',
  `amount` decimal(18,2) DEFAULT NULL COMMENT 'المبلغ الذي اعتُمد تحته',
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  `result` enum('signed','denied') NOT NULL DEFAULT 'signed',
  PRIMARY KEY (`sig_id`),
  UNIQUE KEY `uq_sig_step` (`document_type`,`document_id`,`person_id`,`step`),
  KEY `ix_sig_person` (`person_id`,`at`),
  KEY `fk_sig_auth` (`auth_id`),
  KEY `idx_sig_org_asg` (`org_asg_id`),
  CONSTRAINT `fk_sig_auth` FOREIGN KEY (`auth_id`) REFERENCES `signing_authorities` (`auth_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §6-③: الاعتماد توقيع — Insert-only ولا تعديل ولا حذف؛ يلف الاعتمادات القائمة لا يوازيها';

-- ── Table: approval_steps ──
CREATE TABLE `approval_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `role_required` varchar(100) NOT NULL,
  `step_order` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_approval_steps_request` (`request_id`),
  KEY `idx_approval_steps_status` (`status`),
  KEY `idx_approval_steps_order` (`step_order`),
  CONSTRAINT `fk_approval_steps_request` FOREIGN KEY (`request_id`) REFERENCES `approval_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: approval_workflow_rules ──
CREATE TABLE `approval_workflow_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `role_required` varchar(100) NOT NULL,
  `step_order` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_workflow_rule` (`entity_type`,`action`,`step_order`),
  KEY `idx_workflow_rule_lookup` (`entity_type`,`action`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: asset_hour_reconciliations ──
CREATE TABLE `asset_hour_reconciliations` (
  `rec_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `period` char(7) NOT NULL,
  `register_hours` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ساعات سجل الأصول (فرق العدّادات في الفترة)',
  `timesheet_hours` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ساعات التايم شيت المعتمدة',
  `diff_hours` decimal(12,2) GENERATED ALWAYS AS (`register_hours` - `timesheet_hours`) STORED,
  `depreciation_amount` decimal(18,2) DEFAULT NULL COMMENT 'إهلاك الفترة المحتسب للأصل',
  `depreciation_per_hour` decimal(18,4) DEFAULT NULL COMMENT 'معدل الإهلاك بالساعة — من الفعلي لا التقدير',
  `undepreciated_flag` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'معدة عملت ولم تُهلك — تشوه تكلفة المشروع',
  `state` enum('open','explained') NOT NULL DEFAULT 'open',
  `explanation` varchar(500) DEFAULT NULL,
  `explained_by` int(11) DEFAULT NULL,
  `explained_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`rec_id`),
  UNIQUE KEY `uq_ahr` (`company_id`,`equipment_id`,`period`),
  CONSTRAINT `ck_ahr_explained` CHECK (`state` <> _utf8mb4'explained' or `explanation` is not null and `explained_by` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: asset_ownership_shares ──
CREATE TABLE `asset_ownership_shares` (
  `share_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `asset_id` int(11) NOT NULL,
  `asset_kind` enum('fin_asset','equipment') NOT NULL DEFAULT 'equipment',
  `financier_entity_id` int(10) unsigned NOT NULL,
  `op_id` int(10) unsigned DEFAULT NULL,
  `model_code` varchar(32) DEFAULT NULL,
  `percent` decimal(5,2) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `capital` decimal(18,2) DEFAULT NULL,
  `share_valuation` decimal(18,2) DEFAULT NULL,
  `doc_ref` varchar(120) DEFAULT NULL COMMENT 'مستند الحصة — والبيع بلا مستند يُرفض (الخدمة)',
  `recorded_percent` decimal(5,2) DEFAULT NULL COMMENT 'التصحيح الموثق: المسجَّلة',
  `corrected_percent` decimal(5,2) DEFAULT NULL,
  `correction_reason` varchar(255) DEFAULT NULL,
  `approved_percent` decimal(5,2) DEFAULT NULL COMMENT 'الحكم المعتمد',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`share_id`),
  KEY `ix_aos_asset` (`company_id`,`asset_kind`,`asset_id`,`valid_from`),
  KEY `ix_aos_financier` (`financier_entity_id`),
  CONSTRAINT `fk_aos_financier` FOREIGN KEY (`financier_entity_id`) REFERENCES `legal_entities` (`entity_id`),
  CONSTRAINT `ck_aos_pct` CHECK (`percent` > 0 and `percent` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: assignment_audit ──
CREATE TABLE `assignment_audit` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asg_id` int(10) unsigned NOT NULL,
  `action` enum('created','amended','suspended','transferred','ended','delegated') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_json`)),
  `by_person_id` int(11) NOT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من org_assignments.asg_id',
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_asg` (`asg_id`,`at`),
  KEY `ix_asgaud_co` (`company_id`),
  CONSTRAINT `fk_audit_asg` FOREIGN KEY (`asg_id`) REFERENCES `org_assignments` (`asg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §2⑧: سجلُّ التعديلات والاعتمادات — للإدراج فقط لا يُعدَّل ولا يُحذف';

-- ── Table: assignment_capabilities ──
CREATE TABLE `assignment_capabilities` (
  `cap_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asg_id` int(10) unsigned NOT NULL,
  `capability_code` varchar(80) NOT NULL,
  `scope_limit_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'المواقعُ والمشاريعُ المسموحة — السقفُ التشغيليُّ نطاقيّ' CHECK (json_valid(`scope_limit_json`)),
  `amount_cap` decimal(18,2) DEFAULT NULL COMMENT 'NULL للتشغيلي — والسقفُ الماليُّ نقدي',
  `currency` varchar(8) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من org_assignments.asg_id',
  PRIMARY KEY (`cap_id`),
  UNIQUE KEY `uq_cap_per_asg` (`asg_id`,`capability_code`),
  KEY `ix_asgcap_co` (`company_id`),
  CONSTRAINT `fk_cap_asg` FOREIGN KEY (`asg_id`) REFERENCES `org_assignments` (`asg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: صلاحياتُ التكليف — السقفُ التشغيليُّ نطاقيٌّ والماليُّ نقدي (DEC-01 ①)';

-- ── Table: assignment_reporting_lines ──
CREATE TABLE `assignment_reporting_lines` (
  `line_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asg_id` int(10) unsigned NOT NULL,
  `line_type` enum('operational','functional') NOT NULL,
  `reports_to_assignment_id` int(10) unsigned NOT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من org_assignments.asg_id',
  PRIMARY KEY (`line_id`),
  UNIQUE KEY `uq_line_per_asg` (`asg_id`,`line_type`),
  KEY `idx_line_reports_to` (`reports_to_assignment_id`),
  KEY `ix_asgrl_co` (`company_id`),
  CONSTRAINT `fk_line_asg` FOREIGN KEY (`asg_id`) REFERENCES `org_assignments` (`asg_id`),
  CONSTRAINT `fk_line_target` FOREIGN KEY (`reports_to_assignment_id`) REFERENCES `org_assignments` (`asg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §2⑦: التبعيةُ المزدوجة — وقيدُ «الموقعيُّ له خطّان» يحرسه AssignmentService بـ422';

-- ── Table: attendance_days ──
CREATE TABLE `attendance_days` (
  `att_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `person_id` int(11) NOT NULL,
  `att_date` date NOT NULL,
  `status_code` varchar(4) NOT NULL COMMENT 'من قاموس payroll_absence_types.code حصرًا (تحرسه الخدمة)',
  `policy_id` int(10) unsigned DEFAULT NULL,
  `reference_doc` varchar(120) DEFAULT NULL,
  `stop_reason_code` varchar(40) DEFAULT NULL COMMENT 'لحالة ST — الفوترة والاستحقاق يُقرآن من الإسناد',
  `classified_by` int(11) DEFAULT NULL,
  `classified_at` datetime DEFAULT NULL,
  `auto_reclassified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = صُنّف A2 آليًّا بعد 48 ساعة وإشعار',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`att_id`),
  UNIQUE KEY `uq_att_day` (`person_id`,`att_date`),
  KEY `ix_att_company` (`company_id`,`att_date`,`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §3: سجل اليوم — يشير إلى القاموس ولا يوازيه';

-- ── Table: attendance_policies ──
CREATE TABLE `attendance_policies` (
  `policy_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `name_ar` varchar(120) NOT NULL,
  `applies_to_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'محددات §1: نوع الموظف · مقر/مشروع · العقد · نمط الوردية · الوظيفة · الموقع' CHECK (json_valid(`applies_to_json`)),
  `grace_minutes` int(10) unsigned DEFAULT NULL COMMENT 'سماح المقر (8:15) — NULL للمشاريع (لا تأخر مكتبي)',
  `missing_punch_rule` varchar(60) DEFAULT NULL COMMENT 'half_day_unless_corrected للمقر · NULL للمشاريع (الإثبات بكشف الموقع)',
  `late_rule` varchar(60) DEFAULT NULL COMMENT 'monthly_total للمقر — بإجمالي زمن التأخير لا بعدد المرات',
  `partial_permission_limit` tinyint(3) unsigned DEFAULT NULL COMMENT 'الإذن الجزئي: مرتان شهريًّا',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`policy_id`),
  UNIQUE KEY `uq_ap_name` (`company_id`,`name_ar`,`valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §1: سياستان لا سياسة واحدة — ولا سياسة افتراضية صامتة (بلا مطابقة → 422)';

-- ── Table: attendance_sweep_notices ──
CREATE TABLE `attendance_sweep_notices` (
  `notice_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `person_id` int(11) NOT NULL,
  `att_date` date NOT NULL,
  `notified_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notice_id`),
  UNIQUE KEY `uq_asn_person_date` (`company_id`,`person_id`,`att_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DEC-01 ④: إشعار ما قبل A2 — لا يصير A2 بصمت ولا بلا مهلة إضافية (48+24)';

-- ── Table: audit_logs ──
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `action_type` varchar(80) NOT NULL,
  `target_name` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user_id` (`user_id`),
  KEY `idx_audit_logs_company_id` (`company_id`),
  KEY `idx_audit_logs_action_type` (`action_type`),
  KEY `idx_audit_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: bank_recon_matches ──
CREATE TABLE `bank_recon_matches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `statement_line_id` int(10) unsigned NOT NULL,
  `payment_id` int(11) DEFAULT NULL COMMENT 'سطرُ النظام (fin_payments) — NULL = بلا نظير',
  `match_kind` enum('auto','manual','none') NOT NULL DEFAULT 'auto' COMMENT '«المضاهاةُ الآلية بقاعدتها» — واليدويةُ تُوسم فيُعرف من قرّر',
  `rule_note` varchar(200) DEFAULT NULL COMMENT 'القاعدةُ التي طابقت: مرجعٌ أو (مبلغ + تاريخ ± أيام)',
  `bank_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `system_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `difference` decimal(18,2) GENERATED ALWAYS AS (round(`bank_amount` - `system_amount`,2)) STORED COMMENT '**مولَّدٌ لا يُكتب** — فلا ينحرف الفرقُ عن طرفيه',
  `state` enum('matched','open_difference','resolved','rejected') NOT NULL DEFAULT 'matched',
  `difference_reason` varchar(255) DEFAULT NULL COMMENT '«فتحُ فرقٍ **بسبب**»',
  `adjustment_event_id` int(11) DEFAULT NULL COMMENT '«قيدُ تسويةٍ **بمرجع الفرق**»',
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recon_line` (`statement_line_id`) COMMENT 'مضاهاةٌ واحدةٌ لكل سطرِ بنك — ولا سطرَ يُطابَق مرتين',
  KEY `ix_recon_payment` (`company_id`,`payment_id`),
  KEY `ix_recon_state` (`company_id`,`state`),
  CONSTRAINT `fk_recon_line` FOREIGN KEY (`statement_line_id`) REFERENCES `bank_statement_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_recon_decided` CHECK (`state` not in (_utf8mb4'resolved',_utf8mb4'rejected') or `decided_by` is not null),
  CONSTRAINT `ck_recon_diff_reason` CHECK (`state` <> _utf8mb4'open_difference' or `difference_reason` is not null and `difference_reason` <> _utf8mb4'')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: bank_statement_lines ──
CREATE TABLE `bank_statement_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `statement_id` int(10) unsigned NOT NULL,
  `line_no` int(11) NOT NULL COMMENT 'ترتيبُ السطر في الكشف كما ورد',
  `txn_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `direction` enum('deposit','withdrawal') NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `running_balance` decimal(18,2) DEFAULT NULL COMMENT 'الرصيدُ كما ورد في الكشف',
  `bank_ref` varchar(80) NOT NULL COMMENT 'المرجعُ البنكيُّ للحركة — **جزءُ مفتاح السطر**',
  `line_key` varchar(64) NOT NULL COMMENT 'بصمةُ السطر (كشف × مرجع × تاريخ × اتجاه × مبلغ) — «Idempotent بمفتاح السطر»',
  `match_state` enum('unmatched','matched','difference','no_counterpart') NOT NULL DEFAULT 'unmatched',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_line_key` (`company_id`,`line_key`) COMMENT 'إعادةُ استيراد الملف نفسِه **لا تُنشئ سطرًا ثانيًا**',
  KEY `ix_bank_line_stmt` (`statement_id`,`line_no`),
  KEY `ix_bank_line_match` (`company_id`,`match_state`,`txn_date`),
  CONSTRAINT `fk_bank_line_stmt` FOREIGN KEY (`statement_id`) REFERENCES `bank_statements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_bank_line_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: bank_statements ──
CREATE TABLE `bank_statements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `bank_account_id` int(10) unsigned NOT NULL,
  `statement_ref` varchar(60) NOT NULL COMMENT 'مرجعُ الكشف من البنك — جزءُ مفتاح العطالة',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `opening_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `closing_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `lines_count` int(11) NOT NULL DEFAULT 0,
  `state` enum('imported','matching','reconciled','closed') NOT NULL DEFAULT 'imported',
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `note` varchar(200) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_statement` (`company_id`,`bank_account_id`,`statement_ref`) COMMENT 'كشفٌ واحدٌ لمرجعه في الحساب — إعادةُ الاستيراد تُعيده لا تُكرره',
  KEY `ix_stmt_period` (`company_id`,`bank_account_id`,`period_from`,`period_to`),
  CONSTRAINT `ck_stmt_closed` CHECK (`state` <> _utf8mb4'closed' or `closed_at` is not null and `closed_by` is not null),
  CONSTRAINT `ck_stmt_span` CHECK (`period_to` >= `period_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: capacity_consumption_ledger ──
CREATE TABLE `capacity_consumption_ledger` (
  `led_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `unit_record_id` int(11) NOT NULL COMMENT 'سجلُّ الوحدة القانوني — unit_entries.id (§13.2)',
  `unit_record_version` smallint(6) NOT NULL COMMENT 'النسخة — unit_entries.revision_no؛ التصحيحُ نسخةٌ جديدةٌ بأسطرها',
  `contract_obligation_id` int(10) unsigned DEFAULT NULL COMMENT 'التزامُ نوع المعدة — contract_commitments.id (الهجين DEC-CAP-C)',
  `supplier_share_id` int(10) unsigned DEFAULT NULL COMMENT 'حصةُ المورد — op_containers.id درجة «مورد»',
  `contract_seat_id` int(10) unsigned DEFAULT NULL COMMENT 'المقعدُ التعاقدي — op_containers.id درجة «معدة» بseat_no',
  `equipment_assignment_id` int(10) unsigned DEFAULT NULL COMMENT 'فترةُ إسناد المعدة — seat_assignments.id',
  `supplier_contract_line_id` int(11) DEFAULT NULL COMMENT 'بندُ عقد المورد الذي يُحتسب به — supplier_contract_lines.id',
  `operator_assignment_id` int(10) unsigned DEFAULT NULL COMMENT 'تكليفُ المشغّل — unit_party_awards.id',
  `coverage_id` bigint(20) unsigned DEFAULT NULL COMMENT 'إن كانت تغطيةً بديلة — substitute_coverages.cov_id (§12.1-⑦)',
  `effect_target_type` enum('client','supplier','operator') NOT NULL COMMENT 'طرفُ الأثر (§13.2)',
  `effect_target_ref` varchar(60) NOT NULL COMMENT 'مرجعُ الطرف — لا يكون فارغًا فالمفتاحُ عليه',
  `measure_code` enum('hour','ton','trip','meter') NOT NULL COMMENT 'المقياس — فلا يُخصم الطنُّ من حصة ساعات (C30)',
  `qty` decimal(18,3) NOT NULL COMMENT 'الكميةُ بمقياسها — موجبةٌ دائمًا والعكسُ بسطرِ effect_type=reversal',
  `operational_hours` decimal(18,3) DEFAULT NULL COMMENT 'زمنُ التشغيل مستقلًّا — للجاهزية والتكلفة في عقود الكمية (C30)',
  `analytical_output_qty` decimal(18,3) DEFAULT NULL COMMENT 'الإنتاجُ التحليليُّ مستقلًّا',
  `effect_type` enum('client_obligation','supplier_share','operator_entitlement','exceptional_coverage','reversal') NOT NULL,
  `role_snapshot` enum('primary','standby') DEFAULT NULL COMMENT 'دورُ المعدة لحظةَ الواقعة — لقطةٌ لا إحالة (§12.1-⑥)',
  `unit_decision_snapshot_id` int(10) unsigned DEFAULT NULL COMMENT 'سلسلةُ القرارات كاملةً — unit_approvals سلسلة round_no للنسخة',
  `period` char(7) NOT NULL COMMENT 'YYYY-MM — فترةُ الاستهلاك',
  `reverses_led_id` bigint(20) unsigned DEFAULT NULL COMMENT 'مرجعُ السطر المعكوس — والأصلُ باقٍ (C26)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`led_id`),
  UNIQUE KEY `uq_ledger_no_double` (`unit_record_id`,`unit_record_version`,`effect_type`,`effect_target_type`,`effect_target_ref`),
  KEY `ix_led_share_period` (`supplier_share_id`,`period`),
  KEY `ix_led_obl_period` (`contract_obligation_id`,`period`),
  KEY `ix_led_company_period` (`company_id`,`period`),
  KEY `ix_led_coverage` (`coverage_id`),
  KEY `ix_led_reverses` (`reverses_led_id`),
  CONSTRAINT `fk_led_reverses` FOREIGN KEY (`reverses_led_id`) REFERENCES `capacity_consumption_ledger` (`led_id`),
  CONSTRAINT `ck_led_qty_positive` CHECK (`qty` >= 0),
  CONSTRAINT `ck_led_enums_not_empty` CHECK (`effect_type` <> _utf8mb4'' and `effect_target_type` <> _utf8mb4'' and `measure_code` <> _utf8mb4''),
  CONSTRAINT `ck_led_reversal_ref` CHECK (`effect_type` = _utf8mb4'reversal' and `reverses_led_id` is not null or `effect_type` <> _utf8mb4'reversal' and `reverses_led_id` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: capacity_financial_event_links ──
CREATE TABLE `capacity_financial_event_links` (
  `lnk_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `led_id` bigint(20) unsigned NOT NULL COMMENT 'سطرُ الدفتر',
  `fin_event_id` int(11) NOT NULL COMMENT 'fin_financial_events.id — الحدثُ الماليُّ المولَّد بعد النشر',
  `journal_ref` varchar(60) DEFAULT NULL COMMENT 'مرجعُ القيد إن رُحِّل',
  `linked_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`lnk_id`),
  UNIQUE KEY `uq_led_fin` (`led_id`,`fin_event_id`),
  KEY `ix_lnk_fin` (`fin_event_id`),
  CONSTRAINT `fk_lnk_led` FOREIGN KEY (`led_id`) REFERENCES `capacity_consumption_ledger` (`led_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CAP-01 §13.2 — جدولُ ربطٍ Append-only بين سطر الدفتر والحدث المالي؛ UQ(led,fin) يمنع الربطَ مرتين';

-- ── Table: capacity_gap_watch ──
CREATE TABLE `capacity_gap_watch` (
  `gap_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `obl_id` int(10) unsigned NOT NULL COMMENT 'التزامُ نوع المعدة — contract_commitments.id',
  `gap_units` smallint(6) NOT NULL COMMENT 'الوحداتُ غيرُ المغطاة',
  `gap_hours` decimal(14,2) NOT NULL COMMENT 'الفجوةُ بالساعات لا بالعدد فقط (§10-①/C13)',
  `measure_code` enum('hour','ton','trip','meter') NOT NULL DEFAULT 'hour',
  `opened_on` date NOT NULL COMMENT 'يومُ أول رصدٍ — بساعة القاعدة',
  `last_seen_on` date NOT NULL COMMENT 'آخرُ يومٍ رُصدت فيه — المرقبُ يوميٌّ لا شهري',
  `escalate_after_days` smallint(6) NOT NULL DEFAULT 3 COMMENT 'مهلةُ المعالجة المعلنةُ قبل التصعيد',
  `escalated_ops_at` datetime DEFAULT NULL COMMENT 'تصعيدٌ آليٌّ لمدير التشغيل',
  `escalated_gm_at` datetime DEFAULT NULL COMMENT 'ثم للإدارة العامة',
  `closed_on` date DEFAULT NULL,
  `state` enum('open','escalated_ops','escalated_gm','closed') NOT NULL DEFAULT 'open',
  `open_key` varchar(40) GENERATED ALWAYS AS (if(`closed_on` is null,concat(`company_id`,_utf8mb4':',`obl_id`),NULL)) STORED COMMENT 'صفٌّ مفتوحٌ واحدٌ لكل التزام — فريدٌ مشروطٌ على عمودٍ مولَّد',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`gap_id`),
  UNIQUE KEY `uq_gap_open` (`open_key`),
  KEY `ix_gap_state` (`company_id`,`state`,`last_seen_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CAP-01 §10 — مرقبُ الفجوة اليومي بالساعات: الفجوةُ التي تُكتشف آخرَ الشهر خسارةٌ وقعت';

-- ── Table: capacity_outbox ──
CREATE TABLE `capacity_outbox` (
  `obx_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `event_key` varchar(60) NOT NULL COMMENT 'أحد أحداث مجال القدرات الستة (§14)',
  `entity_type` varchar(40) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `quantity` decimal(18,3) DEFAULT NULL,
  `unit` varchar(16) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `idempotency_key` varchar(64) NOT NULL COMMENT 'مفتاحُ منع التكرار عبر الطبقات (CAP-30) — يمرّ إلى publishFact نفسِه',
  `state` enum('pending','published','failed') NOT NULL DEFAULT 'pending',
  `attempts` smallint(6) NOT NULL DEFAULT 0,
  `next_attempt_at` datetime DEFAULT NULL COMMENT 'إعادةُ المحاولة التصاعدية — بساعة القاعدة',
  `published_event_id` int(11) DEFAULT NULL COMMENT 'ems_business_events.id بعد النشر',
  `last_error` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `published_at` datetime DEFAULT NULL,
  PRIMARY KEY (`obx_id`),
  UNIQUE KEY `uq_obx_idem` (`idempotency_key`),
  KEY `ix_obx_pending` (`company_id`,`state`,`next_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CAP-01 §14 · DEC-CAP-B — صادرُ مجال القدرات: صفٌّ داخل المعاملة والنشرُ بعد COMMIT؛ الفشلُ يُعاد تصاعديًّا بلا استهلاكٍ ثانٍ (C28)';

-- ── Table: capacity_shadow_diffs ──
CREATE TABLE `capacity_shadow_diffs` (
  `diff_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `container_id` int(10) unsigned NOT NULL,
  `stored_consumed` decimal(16,2) NOT NULL COMMENT 'العمودُ المخزَّن لحظةَ القياس',
  `ledger_consumed` decimal(16,2) NOT NULL COMMENT 'المحسوبُ من الدفتر والإعكاسات',
  `diff_qty` decimal(16,2) NOT NULL COMMENT 'الفرق — والحدُّ صفرٌ لا نسبة',
  `noted_on` date NOT NULL COMMENT 'يومُ الرصد بساعة القاعدة',
  `detail` varchar(200) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`diff_id`),
  UNIQUE KEY `uq_shadow_daily` (`container_id`,`noted_on`),
  KEY `ix_shadow_day` (`company_id`,`noted_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CAP-01 · EMS_CAPACITY_SOURCE: فروقُ الظل بين العمود المخزَّن والدفتر — لا قلبَ قبل صفرِ فرقٍ ١٤ يومًا متصلة (نمطُ EMS_PERM_SOURCE)';

-- ── Table: chain_objections ──
CREATE TABLE `chain_objections` (
  `obj_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `line_ref` varchar(120) NOT NULL,
  `domain` varchar(20) NOT NULL,
  `reason_code` varchar(60) NOT NULL COMMENT 'من decision_reasons حصرًا',
  `policy_id` int(10) unsigned DEFAULT NULL COMMENT 'سياسة السلسلة المعنية — مرجع الرجوع الآلي',
  `site_id` int(11) DEFAULT NULL,
  `person_id` int(11) NOT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`obj_id`),
  KEY `ix_co_policy` (`policy_id`,`at`),
  KEY `ix_co_company` (`company_id`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DEC-01 ⑥: رصد الاعتراضات — اعتراضان في شهر أو نزاع → دورية يومية آليًّا (Insert-only)';

-- ── Table: change_approvals ──
CREATE TABLE `change_approvals` (
  `step_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chg_id` int(10) unsigned NOT NULL,
  `seq_no` tinyint(3) unsigned NOT NULL COMMENT '1=مدير الحركة · 2=الإدارة المعنية · 3=المالية · 4=الإدارة العامة',
  `approver_person_id` int(11) NOT NULL,
  `role` varchar(60) NOT NULL,
  `auth_id` int(10) unsigned DEFAULT NULL,
  `decision` enum('approve','reject') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`step_id`),
  UNIQUE KEY `uq_ca_seq` (`chg_id`,`seq_no`),
  CONSTRAINT `fk_ca_chg` FOREIGN KEY (`chg_id`) REFERENCES `unit_state_changes` (`chg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §6-④: سلّم الموافقات الرباعي — لا تُفتح خطوة قبل اكتمال ما قبلها';

-- ── Table: claim_lines ──
CREATE TABLE `claim_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `source_kind` varchar(24) NOT NULL DEFAULT 'timesheet' COMMENT 'مصدر الواقعة: timesheet · unit_entry',
  `source_ref` int(11) NOT NULL COMMENT 'معرّف الواقعة في مصدرها — رابطُ الأصل',
  `contract_line_id` int(10) unsigned DEFAULT NULL COMMENT 'بندُ البيع المفوتَر (P-02)',
  `plan_period_id` int(10) unsigned DEFAULT NULL COMMENT 'شهرُ الخطة (P-03)',
  `operational_site_id` int(10) unsigned DEFAULT NULL COMMENT 'نطاقُ العقد التشغيلي (P-01)',
  `event_id` int(10) unsigned DEFAULT NULL COMMENT 'قيدُ الإيراد المعترَف به من المروحة — البندُ مرجعٌ له لا منشئٌ لإيرادٍ ثانٍ',
  `work_date` date DEFAULT NULL,
  `equipment_ref` varchar(64) DEFAULT NULL COMMENT 'المعدة كما في سجل التشغيل',
  `unit_type` varchar(16) DEFAULT NULL COMMENT 'hour·ton·meter — وحدةُ العقد',
  `qty` decimal(18,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'من سطر معدة العقد — لا يُدخل',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'محسوبٌ = الكمية × السعر',
  `dispute_flag` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بندٌ متنازَعٌ عليه — يقف وحده ولا يجمّد البقية',
  `dispute_reason` varchar(255) DEFAULT NULL,
  `dispute_doc_ref` varchar(120) DEFAULT NULL COMMENT 'مستندُ الاعتراض — «بسببٍ **ومستند**» (§3-⑤)',
  `disputed_by` int(11) DEFAULT NULL,
  `disputed_at` datetime DEFAULT NULL,
  `dispute_state` enum('none','open','resolved') NOT NULL DEFAULT 'none' COMMENT 'حالُ النزاع — والحسمُ قرارٌ يُسجَّل لا وسمٌ يُمحى',
  `resolution` enum('upheld','rejected') DEFAULT NULL COMMENT 'upheld = أُقرَّ اعتراضُ العميل (البندُ يسقط) · rejected = رُدَّ (البندُ يعود محتسَبًا)',
  `resolution_note` varchar(255) DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_claim_line_src` (`claim_id`,`source_kind`,`source_ref`) COMMENT 'لا وحدةَ تتكرر داخل المستخلص الواحد',
  KEY `ix_cl_claim` (`claim_id`),
  KEY `ix_cl_source` (`source_kind`,`source_ref`) COMMENT 'يكشف أي وحدةٍ استُخلصت في أكثر من مستخلص (حارسٌ في الاختبار)',
  KEY `ix_claim_lines_event` (`event_id`),
  KEY `ix_cl_plan_keys` (`contract_line_id`,`plan_period_id`),
  CONSTRAINT `fk_claim_line_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`),
  CONSTRAINT `ck_dispute_evidence` CHECK (`dispute_state` = _utf8mb4'none' or `dispute_reason` is not null and `dispute_reason` <> _utf8mb4'' and `dispute_doc_ref` is not null and `dispute_doc_ref` <> _utf8mb4''),
  CONSTRAINT `ck_dispute_flag_mirror` CHECK (`dispute_flag` = case when `dispute_state` = _utf8mb4'open' then 1 when `dispute_state` = _utf8mb4'resolved' and `resolution` = _utf8mb4'upheld' then 1 else 0 end),
  CONSTRAINT `ck_dispute_resolution` CHECK (`dispute_state` <> _utf8mb4'resolved' or `resolution` is not null and `resolved_by` is not null and `resolution_note` is not null and `resolution_note` <> _utf8mb4'')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: claims ──
CREATE TABLE `claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل المستأجر',
  `claim_no` varchar(32) NOT NULL COMMENT 'رقم المستخلص التسلسلي CLM-سنة-رقم',
  `contract_id` int(11) NOT NULL COMMENT 'العقد — مفتاحُ المستخلص (UX-08 §8.1)',
  `client_id` int(11) DEFAULT NULL COMMENT 'العميل مشتقًّا من مشروع العقد — لا يُدخل',
  `project_id` int(11) DEFAULT NULL COMMENT 'مشروع العقد',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `currency` varchar(16) NOT NULL DEFAULT 'SDG' COMMENT 'عملة العقد',
  `gross_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'إجمالي البنود قبل الاستقطاع',
  `retention_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الاستقطاعات التعاقدية (يدويةٌ بسطرها في النسخة الأولى)',
  `retention_note` varchar(255) DEFAULT NULL COMMENT 'مرجعُ الاستقطاع وسببه',
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الصافي = الإجمالي − الاستقطاعات',
  `tax_code` varchar(16) DEFAULT NULL COMMENT 'كود الضريبة من fin_tax_codes',
  `tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `invoice_no` varchar(64) DEFAULT NULL COMMENT 'رقم الفاتورة الضريبية المولَّدة من المستخلص المعتمد',
  `invoice_date` date DEFAULT NULL,
  `state` varchar(24) NOT NULL DEFAULT 'draft' COMMENT 'حالاتُ §4 — ومنها **partially_collected** (M-05)',
  `submitted_by` int(10) unsigned DEFAULT NULL COMMENT 'من رفعه للمالية (المبيعات) — ولا يعتمد المرءُ ما رفع',
  `submitted_at` datetime DEFAULT NULL COMMENT 'لحظةُ الرفع للمالية (draft → review)',
  `event_id` int(11) DEFAULT NULL COMMENT 'حدث الإيراد المنشور — قراءةً بمرجعه',
  `receivable_id` int(11) DEFAULT NULL COMMENT 'صفّ الذمّة المدينة المولَّد',
  `version` int(11) NOT NULL DEFAULT 1 COMMENT 'قفلُ النسخة عند الاعتماد',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_claim_no` (`company_id`,`claim_no`),
  UNIQUE KEY `uq_claim_period` (`company_id`,`contract_id`,`period_from`,`period_to`) COMMENT 'مستخلصٌ واحدٌ لكل (عقد × فترة) — إعادةُ التوليد ترفض بمرجع القائم',
  KEY `ix_claim_state` (`state`),
  KEY `ix_claim_client` (`client_id`),
  KEY `ix_claim_period` (`period_from`,`period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='المستخلص — مطالبةُ الفترة من الوحدات المعتمدة (UX-08 §5.2)';

-- ── Table: client_contract_lines ──
CREATE TABLE `client_contract_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_id` int(11) NOT NULL,
  `line_no` int(11) NOT NULL,
  `pricing_model` enum('hour','ton','trip','meter','cbm','day','shift','lump_sum','standby') NOT NULL COMMENT 'نموذجُ التسعير — و`lump_sum` مقطوعٌ بكميةٍ 1',
  `description` varchar(255) NOT NULL,
  `qty_contracted` decimal(16,2) NOT NULL COMMENT 'الكميةُ المتعاقَد عليها لهذا البند',
  `qty_planned_total` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT 'Σ أشهر النسخة النافذة — يُحرَس بـCHECK فلا يتجاوز المتعاقَد',
  `plan_sealed_version` int(11) DEFAULT NULL COMMENT 'رقمُ النسخة المختومة — والختمُ يشترط Σ = المتعاقَد بالضبط',
  `resource_share_total` decimal(9,3) NOT NULL DEFAULT 0.000 COMMENT 'Σ حصص خطة الموارد النافذة — يُحرَس بـCHECK فلا يتجاوز 100',
  `unit_price` decimal(14,4) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG' COMMENT 'لا تُجمع عملتان في رقم',
  `valid_from` date NOT NULL COMMENT 'السريان — «ملحقٌ يغيّر السعر ⇒ نسختان»',
  `valid_to` date DEFAULT NULL,
  `tax_status` enum('taxable','exempt','zero_rated','reverse_charge') NOT NULL DEFAULT 'taxable',
  `tax_code_id` int(11) DEFAULT NULL COMMENT 'من `fin_tax_codes` — «الضريبةُ سطرٌ بمرجعها»',
  `source_commitment_id` int(10) unsigned DEFAULT NULL COMMENT 'الالتزامُ الذي اشتُق منه — **الكمياتُ وحدَها**، ولا يقبل التزامَ طاقة',
  `supersedes_line_id` int(10) unsigned DEFAULT NULL COMMENT 'البندُ الذي أخلفه — للمقارنة التاريخية',
  `state` enum('draft','active','superseded','ended') NOT NULL DEFAULT 'draft',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ccl_line_no` (`company_id`,`contract_id`,`line_no`),
  UNIQUE KEY `uq_ccl_source` (`contract_id`,`source_commitment_id`,`valid_from`) COMMENT 'التزامٌ واحدٌ بسريانٍ واحد — «نسختان لا تكديس»',
  KEY `ix_ccl_lookup` (`company_id`,`contract_id`,`state`,`valid_from`,`valid_to`),
  CONSTRAINT `ck_ccl_planned` CHECK (`qty_planned_total` >= 0 and `qty_planned_total` <= `qty_contracted`),
  CONSTRAINT `ck_ccl_price` CHECK (`unit_price` > 0),
  CONSTRAINT `ck_ccl_qty` CHECK (`qty_contracted` > 0),
  CONSTRAINT `ck_ccl_share` CHECK (`resource_share_total` >= 0 and `resource_share_total` <= 100),
  CONSTRAINT `ck_ccl_span` CHECK (`valid_to` is null or `valid_to` >= `valid_from`),
  CONSTRAINT `ck_ccl_tax_ref` CHECK (`tax_status` <> _utf8mb4'taxable' or `tax_code_id` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: clients ──
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `client_code` varchar(50) NOT NULL COMMENT 'كود العميل',
  `client_name` varchar(255) NOT NULL COMMENT 'اسم العميل',
  `entity_type` varchar(100) DEFAULT NULL COMMENT 'نوع الكيان',
  `sector_category` varchar(100) DEFAULT NULL COMMENT 'تصنيف القطاع',
  `phone` varchar(50) DEFAULT NULL COMMENT 'رقم الهاتف',
  `email` varchar(100) DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `whatsapp` varchar(50) DEFAULT NULL COMMENT 'رقم الواتساب',
  `status` enum('نشط','متوقف') NOT NULL DEFAULT 'نشط' COMMENT 'حالة العميل',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أضاف العميل',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإضافة',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clients_company_code` (`company_id`,`client_code`),
  KEY `idx_client_name` (`client_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول العملاء';

-- ── Table: cmp03_idempotency ──
CREATE TABLE `cmp03_idempotency` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزلُ الشركات — المفتاحُ لا يعبر الكيانات',
  `idem_key` char(40) NOT NULL COMMENT 'sha1 للفاعلِ والشاشةِ والحمولةِ المعنوية',
  `canonical_file` varchar(80) NOT NULL COMMENT 'الشاشةُ التي كتبت',
  `target_table` varchar(64) NOT NULL COMMENT 'جدولُ الأثر',
  `row_id` bigint(20) unsigned DEFAULT NULL COMMENT 'مرجعُ الأثرِ الأولِ — يُعاد عند التكرار',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cmp03_idem` (`company_id`,`idem_key`),
  KEY `ix_cmp03_idem_row` (`target_table`,`row_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='INJ-0252: مفتاحُ منعِ تكرارِ كتابةِ شاشاتِ CMP-03 — قيدٌ لا فحصٌ في التطبيق';

-- ── Table: cmp03_screen_rows ──
CREATE TABLE `cmp03_screen_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد',
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — عزل المستأجر',
  `canonical_file` varchar(80) NOT NULL COMMENT 'الشاشة القانونية (nav09_file_map)',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'قيم الأعمدة معنونةً بأسماء المستند الحرفية' CHECK (json_valid(`payload`)),
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'الحالة',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'صف بذرة تجريبية (يعاد بذره بأمان)',
  `created_by` int(11) DEFAULT NULL COMMENT 'المنشئ users.id',
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'اسم المنشئ وصفته لحظة الإدخال',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'لحظة الإنشاء',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_cmp03_screen` (`company_id`,`canonical_file`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03: صفوف الشاشات الوليدة حتى تولد جداولها الأصلية';

-- ── Table: commercial_risks ──
CREATE TABLE `commercial_risks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `risk_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `risk_type` enum('عميل','موقع','تمويل','تحصيل','تشغيل','موردون') NOT NULL DEFAULT 'عميل',
  `severity` enum('منخفضة','متوسطة','عالية') NOT NULL DEFAULT 'متوسطة',
  `mitigation` text DEFAULT NULL,
  `owner_user_id` int(11) DEFAULT NULL,
  `state` enum('مفتوح','تحت المعالجة','مغلق') NOT NULL DEFAULT 'مفتوح',
  `entity_type` enum('opportunity','contract') NOT NULL DEFAULT 'opportunity',
  `entity_id` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_commercial_risks_company_code` (`company_id`,`risk_code`),
  KEY `idx_risk_scope` (`company_id`,`is_deleted`),
  KEY `idx_risk_entity` (`entity_type`,`entity_id`),
  KEY `idx_risk_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: company_user_password_resets ──
CREATE TABLE `company_user_password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_user_password_resets_token_hash` (`token_hash`),
  KEY `idx_company_user_password_resets_user_id` (`user_id`),
  CONSTRAINT `fk_company_user_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: container_consumption ──
CREATE TABLE `container_consumption` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `container_id` int(10) unsigned NOT NULL COMMENT 'الحاويةُ الورقية (مستوى المشغّل غالبًا)',
  `source_kind` enum('unit_entry','timesheet','manual') NOT NULL DEFAULT 'unit_entry',
  `source_ref` int(10) unsigned NOT NULL COMMENT 'الواقعةُ التي استهلكت',
  `qty` decimal(16,2) NOT NULL COMMENT 'موجبٌ استهلاكًا · سالبٌ ردًّا (عكسٌ موثَّق)',
  `unit_type` enum('hour','ton','meter','cbm','day','shift','trip') NOT NULL DEFAULT 'hour',
  `consumed_on` date NOT NULL,
  `idem_key` varchar(80) NOT NULL COMMENT 'مفتاحُ العطالة — يمنع تكرارَ الاستهلاك',
  `note` varchar(200) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_consumption_idem` (`company_id`,`idem_key`),
  KEY `ix_container` (`company_id`,`container_id`,`consumed_on`),
  KEY `ix_source` (`company_id`,`source_kind`,`source_ref`),
  KEY `fk_consumption_container` (`container_id`),
  CONSTRAINT `fk_consumption_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='H-01 §4 — دفترُ استهلاك الحاويات؛ الخصمُ الذريُّ يُسجَّل هنا';

-- ── Table: container_swaps ──
CREATE TABLE `container_swaps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `container_id` int(10) unsigned NOT NULL COMMENT 'الحاويةُ التي وقع فيها التبديل',
  `swap_kind` enum('معدة','مشغّل') NOT NULL,
  `out_ref` int(10) unsigned DEFAULT NULL COMMENT 'الخارج (معدة/موظف)',
  `in_ref` int(10) unsigned DEFAULT NULL COMMENT 'الداخل',
  `moved_qty` decimal(16,2) DEFAULT NULL COMMENT 'الرصيدُ المنقول (متبقي الخارجة) — حركةُ الاستبدال لا وصفُه',
  `to_container_id` int(10) unsigned DEFAULT NULL COMMENT 'الحاويةُ البديلة (وليدةً أو مفعَّلةً)',
  `effective_from` date NOT NULL,
  `reason` varchar(255) NOT NULL COMMENT 'إلزام — لا تبديلَ بلا سبب',
  `doc_ref` varchar(120) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_container_swap` (`company_id`,`container_id`,`effective_from`),
  KEY `fk_swap_container` (`container_id`),
  KEY `fk_swap_to_container` (`to_container_id`),
  CONSTRAINT `fk_swap_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `fk_swap_to_container` FOREIGN KEY (`to_container_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `ck_swap_differs` CHECK (`out_ref` is null or `in_ref` is null or `out_ref` <> `in_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_advances ──
CREATE TABLE `contract_advances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_id` int(10) unsigned NOT NULL,
  `advance_no` varchar(32) NOT NULL COMMENT 'ADV-سنة-تسلسل — ترقيمٌ خادميٌّ لكل شركة',
  `amount` decimal(18,2) NOT NULL COMMENT 'المقبوضُ فعلًا — موجبٌ دائمًا. لا يُشتق من نسبةٍ ولا يُقدَّر (قاعدةُ عدم التلفيق)',
  `currency` varchar(16) NOT NULL,
  `received_date` date NOT NULL COMMENT 'تاريخُ القبض الفعلي',
  `doc_ref` varchar(120) NOT NULL COMMENT 'مرجعُ سند القبض — إلزام: لا سلفةَ بلا مستند',
  `note` varchar(255) DEFAULT NULL,
  `state` enum('recorded','cancelled') NOT NULL DEFAULT 'recorded' COMMENT 'القبضُ واقعةٌ لا دورةُ اعتماد — والإلغاءُ حالةٌ لا حذف',
  `event_id` int(11) DEFAULT NULL COMMENT 'حقيقةُ القبض في الجذر المحايد (publishFact — لا قيدَ إيراد)',
  `recorded_by` int(10) unsigned DEFAULT NULL,
  `recorded_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_advance_no` (`company_id`,`advance_no`),
  UNIQUE KEY `uq_advance_doc` (`company_id`,`contract_id`,`doc_ref`),
  KEY `ix_contract` (`company_id`,`contract_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-01 — دفعاتٌ مقدَّمةٌ مقبوضةٌ فعلًا؛ الاستهلاكُ في claim_lines';

-- ── Table: contract_amendments ──
CREATE TABLE `contract_amendments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `amendment_code` varchar(50) NOT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `amend_type` enum('تجديد','تمديد','زيادة نطاق','تخفيض نطاق','تغيير أسعار','إضافة معدات','إضافة خدمات','إيقاف','استئناف','إنهاء','انتهاء','دمج','تغيير التزامات') NOT NULL DEFAULT 'تجديد' COMMENT 'نوعُ الملحق — و«تغيير التزامات» تُوثّق تعديلَ مصفوفة §4 بسريانٍ لا رجعيّ',
  `amend_date` date DEFAULT NULL,
  `effective_from` date DEFAULT NULL COMMENT 'تاريخُ نفاذ الملحق — NULL أي غيرُ محدد (لا يُشتق من amend_date)',
  `requested_by` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `effect_price` decimal(14,2) DEFAULT NULL,
  `effect_qty` decimal(14,2) DEFAULT NULL,
  `effect_duration` int(11) DEFAULT NULL,
  `effect_summary` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_amendments_company_code` (`company_id`,`amendment_code`),
  KEY `idx_amd_scope` (`company_id`,`is_deleted`),
  KEY `idx_amd_contract` (`contract_id`),
  KEY `idx_amd_type` (`amend_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_baseline ──
CREATE TABLE `contract_baseline` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_id` int(11) NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `state` enum('draft','reviewed','approved','locked','amended','superseded') NOT NULL DEFAULT 'draft',
  `state_note` varchar(255) DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `locked_by` int(10) unsigned DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `comp_lines` int(11) NOT NULL DEFAULT 0,
  `comp_plan_months` int(11) NOT NULL DEFAULT 0,
  `comp_plan_sealed` int(11) NOT NULL DEFAULT 0,
  `comp_resource_rows` int(11) NOT NULL DEFAULT 0,
  `comp_payment_rows` int(11) NOT NULL DEFAULT 0,
  `comp_sites` int(11) NOT NULL DEFAULT 0,
  `fingerprint` char(40) DEFAULT NULL COMMENT 'sha1 لحالة المكوّنات وقتَ القفل — **فيُعرف إن تغيّر شيءٌ بعده**',
  `amendment_id` int(11) DEFAULT NULL,
  `supersedes_baseline_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cb_version` (`contract_id`,`version`),
  KEY `ix_cb_state` (`company_id`,`state`),
  CONSTRAINT `ck_cb_actors` CHECK ((`state` <> _utf8mb4'reviewed' or `reviewed_by` is not null and `reviewed_at` is not null) and (`state` not in (_utf8mb4'approved',_utf8mb4'locked') or `approved_by` is not null and `approved_at` is not null) and (`state` <> _utf8mb4'locked' or `locked_by` is not null and `locked_at` is not null and `fingerprint` is not null)),
  CONSTRAINT `ck_cb_counts` CHECK (`comp_lines` >= 0 and `comp_plan_months` >= 0 and `comp_plan_sealed` >= 0 and `comp_resource_rows` >= 0 and `comp_payment_rows` >= 0 and `comp_sites` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_commitments ──
CREATE TABLE `contract_commitments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `commitment_code` varchar(50) NOT NULL,
  `party_scope` enum('client','supplier') NOT NULL DEFAULT 'client',
  `contract_ref` int(11) NOT NULL,
  `commitment_type` enum('equipment_count','daily_availability_hours','period_hours','min_guaranteed','period_qty','total_qty','capacity_support') NOT NULL DEFAULT 'total_qty',
  `equipment_type_code` varchar(40) DEFAULT NULL COMMENT 'CAP-01 §8.1: نوعُ المعدة — الصفُّ ذو القيمة التزامُ نوعٍ خاضعٌ لمفتاح UQ',
  `primary_units_contracted` smallint(5) unsigned DEFAULT NULL COMMENT 'CAP-01 §8.1: عددُ الأساسية المتعاقد عليها — وحدَه يدخل Σ الالتزام',
  `standby_units_required` smallint(5) unsigned DEFAULT NULL COMMENT 'CAP-01 §8.1: الاحتياطياتُ التي ألزم العميلُ بها — التزامٌ لا خيار',
  `standby_units_allowed` smallint(5) unsigned DEFAULT NULL COMMENT 'CAP-01 §8.1: السقفُ الأقصى المسموح — وعليه يُقاس (StandbyCapService)',
  `qty_per_primary_unit_month` decimal(14,2) DEFAULT NULL COMMENT 'CAP-01 §8.1: كميةُ الوحدة الأساسية شهريًّا بمقياسها — ومنها تُشتق الكمياتُ كلُّها',
  `measure_code` enum('hour','ton','trip','meter') DEFAULT NULL COMMENT 'CAP-01 §16: مقياسُ الكمية — فلا يُخصم الطنُّ من حصة ساعات (C30)',
  `standby_compensation_type` enum('none','fixed_allowance','readiness_allowance','billed_on_activation') DEFAULT NULL COMMENT 'CAP-01 §8.1: مقابلُ الاحتياطي — NULL = لم يُنَصَّ، ولا يُفترض (DEC-CAP-A)',
  `standby_activation_rule` varchar(255) DEFAULT NULL COMMENT 'CAP-01 §8.1: متى يُفعَّل الاحتياطيُّ وبإذن من ولأي مدة',
  `standby_hours_treatment` enum('within_obligation','separate_line') DEFAULT NULL COMMENT 'CAP-01 §8.1: ساعاتُ الاحتياطي المفعَّل — ضمن الالتزام أم بندًا مستقلًّا',
  `plan_state` enum('draft','partial','submitted','approved') NOT NULL DEFAULT 'draft' COMMENT 'CAP-01 §5-②: حالةُ خطة التغطية — المسودةُ والجزئيةُ Σ≤ والمعتمدةُ Σ= أو استثناءٌ موقَّع',
  `sigma_exception_ref` varchar(120) DEFAULT NULL COMMENT 'CAP-01 §5-②: مرجعُ قرار الاستثناء الموقَّع — إلزاميٌّ لاعتمادٍ بفجوةٍ ظاهرة (C16)',
  `valid_from` date DEFAULT NULL COMMENT 'CAP-01 §5-④: الالتزامُ مؤرَّخ — والتعديلُ فترةٌ جديدةٌ لا مسٌّ بالماضي',
  `valid_to` date DEFAULT NULL,
  `unit_type` enum('hour','ton','meter','cbm','day','shift','trip') DEFAULT NULL,
  `qty` decimal(14,2) NOT NULL DEFAULT 0.00,
  `period` enum('daily','monthly','contract') NOT NULL DEFAULT 'monthly',
  `obliged_party` enum('company','client','supplier') NOT NULL DEFAULT 'company',
  `shortfall_rule` enum('invoice_actual','penalty','carry_over','extend_term','waive_if_client','negotiate') NOT NULL DEFAULT 'invoice_actual',
  `surplus_rule` enum('same_price','different_price','pre_approval','open','not_billable') NOT NULL DEFAULT 'same_price',
  `note` varchar(160) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `obl_type_uq_key` varchar(130) GENERATED ALWAYS AS (if(`equipment_type_code` is not null and `is_deleted` = 0,concat(`company_id`,_utf8mb4':',`contract_ref`,_utf8mb4':',`equipment_type_code`,_utf8mb4':',ifnull(cast(`valid_from` as char charset utf8mb4),_utf8mb4'open')),NULL)) STORED COMMENT 'CAP-01: فهرسٌ فريدٌ مشروطٌ على عمودٍ مولَّد — UQ(contract, equipment_type_code, valid_from) للأحياء ذوي النوع (DEC-CAP-C) · CAST لا DATE_FORMAT لقابلية MariaDB',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_commit_company_code` (`company_id`,`commitment_code`),
  UNIQUE KEY `uq_obl_type_from` (`obl_type_uq_key`),
  KEY `idx_commit_scope` (`company_id`,`is_deleted`),
  KEY `idx_commit_contract` (`contract_ref`),
  KEY `idx_commit_type` (`commitment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='INJAZ-S05 §ت.2 — التزامات العقد: نوعٌ ووحدةٌ وكميةٌ ودوريةٌ وطرفٌ ملتزم وحكما العجز والزيادة';

-- ── Table: contract_events ──
CREATE TABLE `contract_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `event_code` varchar(50) NOT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `event_date` datetime DEFAULT NULL,
  `event_type` enum('انخفاض إنتاج','تأخر اعتماد العميل','نقص معدات','تأخر موردين','قوة قاهرة','أمر تغيير','مطالبة إضافية','تمديد محتمل','خلاف تشغيلي','إخلال طرف') NOT NULL DEFAULT 'أمر تغيير',
  `party` enum('الشركة','العميل','المورد') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `state` enum('مفتوح','قيد المتابعة','مغلق') NOT NULL DEFAULT 'مفتوح',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_events_company_code` (`company_id`,`event_code`),
  KEY `idx_evt_scope` (`company_id`,`is_deleted`),
  KEY `idx_evt_contract` (`contract_id`),
  KEY `idx_evt_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_guarantees ──
CREATE TABLE `contract_guarantees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_id` int(11) NOT NULL,
  `kind` enum('cash_retention','bank_guarantee','insurance','surety','pledge','other') NOT NULL COMMENT 'محتجزٌ نقديّ · خطابُ ضمانٍ بنكي · تأمين · كفالة · رهن · أخرى',
  `nature` enum('asset','off_balance') NOT NULL DEFAULT 'off_balance' COMMENT 'أصلٌ لدى العميل · أو التزامٌ محتملٌ خارج الميزانية',
  `deductible_from_claim` tinyint(1) NOT NULL DEFAULT 0,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'قيمةُ الأداة — وللمحتجَز النقديِّ **سقفٌ متعاقَدٌ عليه لا رصيدٌ**',
  `percent_value` decimal(7,3) DEFAULT NULL COMMENT 'نسبتُه من قيمة العقد إن كان بنسبة',
  `currency` varchar(8) NOT NULL,
  `issuer` varchar(190) DEFAULT NULL COMMENT 'البنكُ المُصدر أو شركةُ التأمين أو الكفيل',
  `instrument_ref` varchar(120) DEFAULT NULL COMMENT 'رقمُ الخطاب/الوثيقة',
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL COMMENT 'انتهاءُ سريان الأداة — إلزاميٌّ لغير المحتجَز',
  `due_release_date` date DEFAULT NULL COMMENT 'تاريخُ ردِّ المحتجَز — إلزاميٌّ له',
  `release_condition` varchar(200) DEFAULT NULL,
  `state` enum('draft','active','expired','released','called') NOT NULL DEFAULT 'draft',
  `state_reason` varchar(255) DEFAULT NULL,
  `state_at` date DEFAULT NULL,
  `source_text` varchar(500) DEFAULT NULL COMMENT 'نصُّ `contracts.guarantees` الذي جاءت منه — **والنصُّ لا يُمحى**',
  `needs_review` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'صُنّفت آليًّا من نثرٍ فتنتظر إقرارَ المالك',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_cg_lookup` (`company_id`,`contract_id`,`state`),
  KEY `ix_cg_expiry` (`expiry_date`),
  CONSTRAINT `ck_cg_nature` CHECK (`kind` = _utf8mb4'cash_retention' and `nature` = _utf8mb4'asset' or `kind` <> _utf8mb4'cash_retention' and `nature` = _utf8mb4'off_balance'),
  CONSTRAINT `ck_cg_deduct` CHECK (`deductible_from_claim` = 0 or `kind` = _utf8mb4'cash_retention'),
  CONSTRAINT `ck_cg_dates` CHECK (`kind` = _utf8mb4'cash_retention' and (`due_release_date` is not null or `release_condition` is not null) or `kind` <> _utf8mb4'cash_retention' and `expiry_date` is not null),
  CONSTRAINT `ck_cg_state_reason` CHECK (`state` not in (_utf8mb4'released',_utf8mb4'called',_utf8mb4'expired') or `state_reason` is not null),
  CONSTRAINT `ck_cg_amount` CHECK (`amount` >= 0),
  CONSTRAINT `ck_cg_percent` CHECK (`percent_value` is null or `percent_value` >= 0 and `percent_value` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_hour_policies ──
CREATE TABLE `contract_hour_policies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `party_scope` enum('client','supplier','operator') NOT NULL,
  `contract_ref` int(10) unsigned DEFAULT NULL COMMENT 'NULL = السياسة الافتراضية للشركة (تُنسخ عند إنشاء العقد)',
  `operator_id` int(10) unsigned DEFAULT NULL COMMENT 'المشغّل (employees) — وضعُ سياسة المشغّل؛ NULL في وضع حكم الساعة',
  `work_model` varchar(16) DEFAULT NULL COMMENT '§15.2-ج: hour·ton·trip·meter',
  `pay_basis` varchar(16) DEFAULT NULL COMMENT '§15.2-ج: actual·standby·attendance·ton·trip·meter·composite',
  `rate` decimal(14,4) DEFAULT NULL COMMENT 'معدلُ الاستحقاق لوحدة الأساس (§8.2) — عمودٌ مستقلٌّ لأن pct(5,2) يبتر فوق 999.99',
  `min_amount` decimal(18,2) DEFAULT NULL COMMENT '§15.2-ج: الحد الأدنى اليومي',
  `max_amount` decimal(18,2) DEFAULT NULL COMMENT '§15.2-ج: الحد الأقصى اليومي — قيدُ min ≤ max يُفرض بالتطبيق',
  `scope_type` varchar(16) DEFAULT NULL COMMENT '§15.2-ج: project·equip_type — NULL = سياسةٌ افتراضية',
  `scope_id` int(10) unsigned DEFAULT NULL COMMENT '§15.2-ج: معرّفُ النطاق المقابل لـscope_type',
  `currency` varchar(8) DEFAULT NULL COMMENT '§15.2-ج: عملةُ المعدّل — لا جمعَ عملتين',
  `deductions_note` varchar(200) DEFAULT NULL COMMENT '§8.2 القيم والحدود: الخصومات — توثيقٌ يقرؤه المخلِّص',
  `exceptions_note` varchar(200) DEFAULT NULL COMMENT '§8.2: الاستثناءات — توثيقٌ يقرؤه المخلِّص',
  `approved_at` datetime DEFAULT NULL COMMENT '§8.2 الهوية والسريان: تاريخ اعتماد السياسة',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `is_trial` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'سياسةٌ تجريبيةُ البذر — تُستبدل قيمُها قبل الاستعمال الحقيقي',
  `policy_state` enum('draft','active','superseded','expired') NOT NULL DEFAULT 'draft' COMMENT 'UX-06 §8.2: Draft→Active→Superseded→Expired — والمسودةُ لا تُقرأ في أي احتساب',
  `superseded_by` int(10) unsigned DEFAULT NULL COMMENT 'السياسةُ الأحدثُ التي أخلفتها — «Superseded بسياسةٍ أحدث» بمرجعها لا بالدعوى',
  `state_changed_at` datetime DEFAULT NULL,
  `state_changed_by` int(10) unsigned DEFAULT NULL,
  `state_note` varchar(200) DEFAULT NULL COMMENT 'سببُ الانتقال — إلزاميٌّ عند الإنهاء',
  `ops_state` enum('actual_work','standby','tech_breakdown','supplier_stop','operator_stop','client_stop','fuel_logistics_stop','planned_stop','force_majeure','pending_approval','other','unlogged') DEFAULT NULL COMMENT 'حالةُ الساعة (وضع client/supplier) — NULL لصفوف المشغّل. وأُضيف unlogged لتوحيد القاموس',
  `obligation_type` enum('fuel','access_road','loading_equipment','equipment_readiness','operators','permits_safety','utilities','catering_camp','force_majeure') DEFAULT NULL COMMENT 'بندُ الالتزام (CON-02 §4) — المحورُ الثاني للحكم. NULL = قاعدةٌ عامةٌ للحالة (عُرفُ الجدول: NULL أي الأعمّ)',
  `ruling` enum('full','pct','none','pending','case_by_case') NOT NULL,
  `pct` decimal(5,2) DEFAULT NULL COMMENT 'عند ruling=pct — نسبةٌ من الكمية لا من السعر',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `note` varchar(200) DEFAULT NULL COMMENT 'بند العقد أو سببُ الحكم',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'عقدُ البوابة الثلاثي (is_deleted/deleted_at/deleted_by)',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'حذفٌ ناعم — شرط بوابة المستأجر',
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `policy_key` varchar(80) GENERATED ALWAYS AS (if(`operator_id` is null,concat_ws(_utf8mb4'|',ifnull(cast(`contract_ref` as char charset utf8mb4),_utf8mb4'*'),ifnull(cast(`ops_state` as char charset utf8mb4),_utf8mb4'*'),ifnull(cast(`obligation_type` as char charset utf8mb4),_utf8mb4'*'),ifnull(cast(`effective_from` as char charset utf8mb4),_utf8mb4'*')),NULL)) STORED COMMENT 'بصمةُ قاعدة حكم الساعة بقيمٍ حارسةٍ بديلةٍ عن NULL — وNULL لصفوف المشغّل فتُستثنى (مفتاحُها uq_operator_policy)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operator_policy` (`company_id`,`operator_id`,`work_model`,`pay_basis`,`effective_from`),
  UNIQUE KEY `uq_policy_scope_key` (`company_id`,`party_scope`,`policy_key`),
  KEY `ix_lookup` (`company_id`,`party_scope`,`contract_ref`,`ops_state`),
  KEY `ix_operator_lookup` (`company_id`,`operator_id`,`effective_from`,`effective_to`),
  KEY `ix_lookup_obligation` (`company_id`,`party_scope`,`contract_ref`,`obligation_type`,`ops_state`),
  KEY `ix_policy_state` (`company_id`,`party_scope`,`policy_state`),
  KEY `ix_policy_superseded` (`superseded_by`),
  CONSTRAINT `ck_chp_superseded` CHECK (`policy_state` <> _utf8mb4'superseded' or `superseded_by` is not null),
  CONSTRAINT `ck_chp_expired_note` CHECK (`policy_state` <> _utf8mb4'expired' or `state_note` is not null and `state_note` <> _utf8mb4'')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_lifecycle_events ──
CREATE TABLE `contract_lifecycle_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_id` int(11) NOT NULL,
  `state` enum('extension','renewal','suspension','natural_end','client_fault_end','our_fault_end','pre_start_cancel','dispute') NOT NULL,
  `effect_date` date NOT NULL COMMENT 'تاريخُ الأثر — وما قبله بحكمه القديم',
  `decision_ref` varchar(120) DEFAULT NULL COMMENT 'مرجعُ القرار — إلزاميٌّ للإنهاء والإلغاء',
  `advance_effect` enum('continue','settle_and_new','pause_recovery','consume_then_refund','refund_all_after_offset','refund_after_dues','refund_full','freeze') NOT NULL,
  `retention_effect` enum('hold','release_after_grace','release','may_forfeit') NOT NULL,
  `unbilled_effect` enum('bill_cycle','final_claim_old','bill_before_pause','final_claim','bill_all','bill_accepted_only','none','freeze_disputed_bill_rest') NOT NULL,
  `penalty_effect` enum('continue','close_old_start_new','pause_time_not_performance','accrue_to_effect_date','company_claims_compensation','breach_penalties_capped','mobilization_cost_if_article','suspend_until_resolution') NOT NULL,
  `container_effect` enum('extend','new_tree','suspend','close_readonly','close_with_ref','close','cancel') NOT NULL,
  `claim_amount` decimal(18,2) DEFAULT NULL COMMENT 'تعويضٌ أو غرامةٌ — موجبٌ لنا وسالبٌ علينا',
  `claim_currency` varchar(8) DEFAULT NULL,
  `contract_article` varchar(200) DEFAULT NULL COMMENT 'مادةُ العقد الحاكمة — **إلزاميةٌ مع أيِّ مبلغ**',
  `claim_doc_ref` varchar(120) DEFAULT NULL COMMENT 'مستندُ الحساب الموثَّق',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cle_event` (`contract_id`,`state`,`effect_date`),
  KEY `ix_cle_lookup` (`company_id`,`state`,`effect_date`),
  CONSTRAINT `ck_cle_effects` CHECK (`state` = _utf8mb4'extension' and `advance_effect` = _utf8mb4'continue' and `retention_effect` = _utf8mb4'hold' and `unbilled_effect` = _utf8mb4'bill_cycle' and `penalty_effect` = _utf8mb4'continue' and `container_effect` = _utf8mb4'extend' or `state` = _utf8mb4'renewal' and `advance_effect` = _utf8mb4'settle_and_new' and `retention_effect` = _utf8mb4'release_after_grace' and `unbilled_effect` = _utf8mb4'final_claim_old' and `penalty_effect` = _utf8mb4'close_old_start_new' and `container_effect` = _utf8mb4'new_tree' or `state` = _utf8mb4'suspension' and `advance_effect` = _utf8mb4'pause_recovery' and `retention_effect` = _utf8mb4'hold' and `unbilled_effect` = _utf8mb4'bill_before_pause' and `penalty_effect` = _utf8mb4'pause_time_not_performance' and `container_effect` = _utf8mb4'suspend' or `state` = _utf8mb4'natural_end' and `advance_effect` = _utf8mb4'consume_then_refund' and `retention_effect` = _utf8mb4'release_after_grace' and `unbilled_effect` = _utf8mb4'final_claim' and `penalty_effect` = _utf8mb4'accrue_to_effect_date' and `container_effect` = _utf8mb4'close_readonly' or `state` = _utf8mb4'client_fault_end' and `advance_effect` = _utf8mb4'refund_all_after_offset' and `retention_effect` = _utf8mb4'release' and `unbilled_effect` = _utf8mb4'bill_all' and `penalty_effect` = _utf8mb4'company_claims_compensation' and `container_effect` = _utf8mb4'close_with_ref' or `state` = _utf8mb4'our_fault_end' and `advance_effect` = _utf8mb4'refund_after_dues' and `retention_effect` = _utf8mb4'may_forfeit' and `unbilled_effect` = _utf8mb4'bill_accepted_only' and `penalty_effect` = _utf8mb4'breach_penalties_capped' and `container_effect` = _utf8mb4'close' or `state` = _utf8mb4'pre_start_cancel' and `advance_effect` = _utf8mb4'refund_full' and `retention_effect` = _utf8mb4'release' and `unbilled_effect` = _utf8mb4'none' and `penalty_effect` = _utf8mb4'mobilization_cost_if_article' and `container_effect` = _utf8mb4'cancel' or `state` = _utf8mb4'dispute' and `advance_effect` = _utf8mb4'freeze' and `retention_effect` = _utf8mb4'hold' and `unbilled_effect` = _utf8mb4'freeze_disputed_bill_rest' and `penalty_effect` = _utf8mb4'suspend_until_resolution' and `container_effect` = _utf8mb4'suspend'),
  CONSTRAINT `ck_cle_claim_article` CHECK (`claim_amount` is null or `contract_article` is not null and `claim_doc_ref` is not null and `claim_currency` is not null and `claim_amount` <> 0),
  CONSTRAINT `ck_cle_decision` CHECK (`state` not in (_utf8mb4'natural_end',_utf8mb4'client_fault_end',_utf8mb4'our_fault_end',_utf8mb4'pre_start_cancel') or `decision_ref` is not null),
  CONSTRAINT `ck_cle_cancel_tree` CHECK (`container_effect` <> _utf8mb4'cancel' or `state` = _utf8mb4'pre_start_cancel')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_monthly_plan ──
CREATE TABLE `contract_monthly_plan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_id` int(11) NOT NULL,
  `line_id` int(10) unsigned NOT NULL COMMENT 'بندُ البيع (P-02) — والجدولُ يقسّم كميتَه',
  `plan_version` int(11) NOT NULL DEFAULT 1 COMMENT 'نسخةُ الخطة — والملحقُ يفتح نسخةً لا يعدّل',
  `effective_from` date NOT NULL COMMENT 'سريانُ النسخة — «أثرُ ما قبله بالنسخة السابقة»',
  `period_month` varchar(7) NOT NULL COMMENT 'YYYY-MM',
  `qty_planned` decimal(16,2) NOT NULL COMMENT 'كميةُ الشهر — **وصفرٌ يعني توقفًا معلَنًا لا غيابَ بيان**',
  `month_kind` enum('normal','mobilization','ramp_up','shutdown','maintenance') NOT NULL DEFAULT 'normal' COMMENT 'طبيعةُ الشهر — «شهرُ تعبئةٍ وشهرُ توقف» بأسمائهما',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cmp_month` (`line_id`,`plan_version`,`period_month`) COMMENT 'شهرٌ واحدٌ لكل (بند × نسخة) — لا تكديسَ ولا ازدواج',
  KEY `ix_cmp_lookup` (`company_id`,`contract_id`,`plan_version`,`period_month`),
  CONSTRAINT `fk_cmp_line` FOREIGN KEY (`line_id`) REFERENCES `client_contract_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_cmp_qty` CHECK (`qty_planned` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_notes ──
CREATE TABLE `contract_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `contract_id` int(11) NOT NULL,
  `note` mediumtext NOT NULL,
  `user_id` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `severity` enum('normal','critical') NOT NULL DEFAULT 'normal',
  `note_state` enum('open','closed') NOT NULL DEFAULT 'open',
  `closure_doc_ref` varchar(160) DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `fk_contract_notes_contract` (`contract_id`),
  KEY `fk_contract_notes_created_by` (`created_by`),
  KEY `ix_cnote_block` (`contract_id`,`severity`,`note_state`),
  CONSTRAINT `fk_contract_notes_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_contract_notes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_contract_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_obligations ──
CREATE TABLE `contract_obligations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل المستأجر',
  `client_contract_id` int(11) NOT NULL COMMENT 'عقدُ العميل — contracts.id (FK حقيقيّ · قرارُ المالك ③)',
  `obligation_type` enum('fuel','access_road','loading_equipment','equipment_readiness','operators','permits_safety','utilities','catering_camp','force_majeure') NOT NULL COMMENT 'بنودُ §4 التسعة: الوقود · الطريق · معدات التحميل · جاهزية المعدة · المشغّلون · التصاريح · المرافق · الإعاشة · القاهرة',
  `obligor` enum('client','company','supplier','operator','none') NOT NULL DEFAULT 'company' COMMENT 'الطرفُ الملتزم. الافتراضُ company تنفيذًا لقاعدة §4 «ما لم يُنص عليه يُعدُّ التزامَ الشركة» · و none للقاهرة (لا طرفَ ملتزمًا — قرارُ المالك ②)',
  `effect_on_billing` enum('billable_standby','non_billable','per_clause') NOT NULL DEFAULT 'per_clause' COMMENT 'أثرُ الإخلال على الفوترة. الافتراضُ per_clause أي «اقرأ البند» — لا حكمَ مشتقًّا صامتًا',
  `approval_state` enum('draft','approved') NOT NULL DEFAULT 'draft' COMMENT 'مسودةٌ يملؤها 12 · ومُجازةٌ يعتمدها 19 — والمحلِّلُ لا يقرأ إلا المُجاز (ق-18)',
  `approved_by` int(11) DEFAULT NULL COMMENT 'مَن أجاز — الدور 19 حصرًا (تفرضه المنحُ والشاشة)',
  `approved_at` datetime DEFAULT NULL COMMENT 'لحظةُ الإجازة — وبها يصير الصفُّ نافذًا وغيرَ قابلٍ للتعديل',
  `penalty_rule_id` int(11) DEFAULT NULL COMMENT 'قاعدةُ الجزاء — بلا هدفٍ حتى تُبنى contract_penalty_rules (§6 · T-07)؛ فلا FK اليوم',
  `valid_from` date NOT NULL COMMENT 'بدءُ السريان — NOT NULL عمدًا: المفتاحُ الفريد أدناه يشمله، وMySQL تعدّ NULLات متمايزةً فتمرّ التكراراتُ صامتةً',
  `valid_to` date DEFAULT NULL COMMENT 'نهايةُ السريان — NULL أي مفتوح',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obligation_contract_type_from` (`client_contract_id`,`obligation_type`,`valid_from`) COMMENT 'بندٌ واحدٌ لكل (عقد × نوع × تاريخ سريان) — وتغييرُ الملتزم صفٌّ جديدٌ بسريانه لا تعديلُ الماضي (§6 الملاحق: لا رجعية)',
  KEY `ix_obligation_scope` (`company_id`,`is_deleted`),
  KEY `ix_obligation_contract` (`client_contract_id`),
  KEY `ix_obligation_validity` (`valid_from`,`valid_to`),
  KEY `fk_obligation_penalty_rule` (`penalty_rule_id`),
  KEY `ix_obligation_effective` (`client_contract_id`,`approval_state`,`valid_from`,`valid_to`),
  CONSTRAINT `fk_contract_obligations_contract` FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_obligation_penalty_rule` FOREIGN KEY (`penalty_rule_id`) REFERENCES `contract_penalty_rules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CON-02 §4/§8 — مصفوفةُ التزامات عقد العميل: منها يُشتق المسؤولُ لا من حالة الساعة';

-- ── Table: contract_operational_sites ──
CREATE TABLE `contract_operational_sites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL COMMENT 'الموقع/المنجم من `sites` (H-05) — الكيانُ المستقل',
  `scope_name` varchar(190) NOT NULL COMMENT 'اسمُ النطاق داخل العقد — قد يخالف اسمَ الموقع',
  `start_date` date DEFAULT NULL COMMENT 'NULL = من بداية العقد',
  `end_date` date DEFAULT NULL COMMENT 'NULL = إلى نهايته',
  `state` enum('planned','active','paused','closed') NOT NULL DEFAULT 'active',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'النطاقُ الرئيسيُّ للعقد — واحدٌ على الأكثر',
  `primary_flag` tinyint(1) GENERATED ALWAYS AS (if(`is_primary` = 1,1,NULL)) STORED COMMENT 'حيلةُ الفريد: NULL لغير الرئيسي — وMySQL لا تقيّد الـNULLات، فينتج «رئيسٌ واحدٌ على الأكثر»',
  `close_reason` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cos_contract_site` (`company_id`,`contract_id`,`site_id`) COMMENT 'الموقعُ مرةً واحدةً في العقد — فلا نطاقان لموقعٍ واحد',
  UNIQUE KEY `uq_cos_primary` (`contract_id`,`primary_flag`) COMMENT '«رئيسٌ واحدٌ على الأكثر» بنيويًّا',
  KEY `ix_cos_lookup` (`company_id`,`contract_id`,`state`),
  KEY `ix_cos_site` (`company_id`,`site_id`),
  KEY `fk_cos_site` (`site_id`),
  CONSTRAINT `fk_cos_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  CONSTRAINT `ck_cos_closed` CHECK (`state` <> _utf8mb4'closed' or `close_reason` is not null and `close_reason` <> _utf8mb4''),
  CONSTRAINT `ck_cos_name` CHECK (`scope_name` <> _utf8mb4''),
  CONSTRAINT `ck_cos_span` CHECK (`start_date` is null or `end_date` is null or `end_date` >= `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_payment_schedule ──
CREATE TABLE `contract_payment_schedule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_id` int(11) NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL COMMENT 'NULL = النسخةُ النافذة · والقديمةُ تُختم ولا تُمحى',
  `amendment_id` int(11) DEFAULT NULL COMMENT 'الملحقُ الذي فتح النسخة',
  `seq` int(11) NOT NULL COMMENT 'ترتيبُ السطر داخل النسخة',
  `pattern` enum('single_payment','advance_then_monthly','partial_advance','advance_installments','milestone_payments','monthly_claim','final_payment','retention_release') NOT NULL DEFAULT 'monthly_claim',
  `payment_kind` enum('advance','monthly_settlement','milestone','final','retention_release','single') NOT NULL,
  `advance_type` enum('recoverable','mobilization','non_refundable_booking','milestone_earned') DEFAULT NULL,
  `treatment` enum('liability','revenue') DEFAULT NULL COMMENT 'المعالجةُ المحاسبية — محكومةٌ بالنوع إلا في التعبئة فبنص العقد',
  `treatment_basis` varchar(255) DEFAULT NULL COMMENT 'نصُّ العقد الذي حكم معالجةَ التعبئة — إلزاميٌّ لها وحدَها',
  `amount_basis` enum('percent','fixed') NOT NULL DEFAULT 'fixed',
  `percent_value` decimal(7,3) DEFAULT NULL,
  `amount_expected` decimal(18,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL,
  `due_date` date DEFAULT NULL,
  `due_condition` varchar(200) DEFAULT NULL COMMENT 'شرطُ الاستحقاق حين لا تاريخَ ثابت',
  `period_month` varchar(7) DEFAULT NULL COMMENT 'شهرُ الجدول (P-03) الذي وُلد منه السطر',
  `line_id` int(10) unsigned DEFAULT NULL COMMENT 'بندُ البيع إن كان السطرُ لبندٍ بعينه',
  `received_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(18,2) GENERATED ALWAYS AS (`amount_expected` - `received_amount`) STORED,
  `state` enum('not_due','due','partial','completed','overdue') NOT NULL DEFAULT 'not_due',
  `collection_ref` varchar(120) DEFAULT NULL,
  `advance_id` int(10) unsigned DEFAULT NULL COMMENT 'صفُّ القبض في contract_advances (M-01) — **للالتزام وحدَه**',
  `source` enum('generated','manual') NOT NULL DEFAULT 'generated' COMMENT '«تُولَّد آليًّا … ولا تُدخل كلُّها يدويًّا»',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cps_seq` (`contract_id`,`version`,`seq`),
  KEY `ix_cps_lookup` (`company_id`,`contract_id`,`state`,`due_date`),
  KEY `ix_cps_live` (`contract_id`,`effective_to`),
  CONSTRAINT `ck_cps_treatment` CHECK (`advance_type` is null and `treatment` is null or `advance_type` = _utf8mb4'recoverable' and `treatment` = _utf8mb4'liability' or `advance_type` = _utf8mb4'non_refundable_booking' and `treatment` = _utf8mb4'revenue' or `advance_type` = _utf8mb4'milestone_earned' and `treatment` = _utf8mb4'revenue' or `advance_type` = _utf8mb4'mobilization' and `treatment` is not null and `treatment_basis` is not null),
  CONSTRAINT `ck_cps_advance_link` CHECK (`advance_id` is null or `treatment` = _utf8mb4'liability'),
  CONSTRAINT `ck_cps_advance_type` CHECK ((`payment_kind` <> _utf8mb4'advance' or `advance_type` is not null) and (`payment_kind` = _utf8mb4'advance' or `advance_type` is null)),
  CONSTRAINT `ck_cps_amounts` CHECK (`amount_expected` >= 0 and `received_amount` >= 0 and `received_amount` <= `amount_expected`),
  CONSTRAINT `ck_cps_due` CHECK (`due_date` is not null or `due_condition` is not null),
  CONSTRAINT `ck_cps_percent` CHECK ((`amount_basis` <> _utf8mb4'percent' or `percent_value` is not null and `percent_value` > 0 and `percent_value` <= 100) and (`percent_value` is null or `percent_value` >= 0 and `percent_value` <= 100)),
  CONSTRAINT `ck_cps_window` CHECK (`effective_to` is null or `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_penalty_assessments ──
CREATE TABLE `contract_penalty_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل المستأجر',
  `client_contract_id` int(11) NOT NULL COMMENT 'عقدُ العميل',
  `rule_id` int(11) DEFAULT NULL COMMENT 'قاعدةُ الجزاء المطبَّقة — NULL للحد الأدنى المضمون (مصدرُه contract_commitments لا قاعدةَ جزاء)',
  `commitment_ref` int(10) unsigned DEFAULT NULL COMMENT 'البندُ الملتزَمُ المرساة',
  `kind` enum('penalty','incentive','min_guarantee') NOT NULL COMMENT 'غرامةٌ تُخصم · حافزٌ يُضاف · حدٌّ أدنى يُكمَّل — وثلاثتُها بنودٌ ظاهرةٌ لا خصمٌ صامت (§6)',
  `rule_kind` varchar(24) DEFAULT NULL COMMENT 'لقطةُ نوع القاعدة وقتَ الاحتساب — للتدقيق بعد تغيّر القاعدة',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `periodicity` enum('daily','monthly','contract') NOT NULL DEFAULT 'monthly',
  `committed_qty` decimal(18,4) DEFAULT NULL COMMENT 'الكميةُ الملتزمُ بها في الفترة',
  `actual_qty` decimal(18,4) DEFAULT NULL COMMENT 'المنفَّذُ فعلًا (من قيود الإيراد لا من تقديرٍ)',
  `gap_qty` decimal(18,4) DEFAULT NULL COMMENT 'الفارق — موجبٌ عجزًا وسالبٌ تجاوزًا',
  `readiness_pct` decimal(6,2) DEFAULT NULL COMMENT 'ساعاتُ العمل ÷ ساعاتِ الوردية — لـreadiness_min',
  `unit_price` decimal(18,4) DEFAULT NULL COMMENT 'سعرُ الوحدة المستعمل — لقطةٌ لا اشتقاقٌ لاحق',
  `base_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'قيمةُ البند الملتزَم في الفترة (ق-12) — أساسُ السقف',
  `raw_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'المبلغُ قبل السقف',
  `cap_amount` decimal(18,2) DEFAULT NULL COMMENT 'السقفُ المطبَّق — NULL أي بلا سقف',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'المبلغُ النهائي (موجبٌ دائمًا) — والاتجاهُ من kind لا من الإشارة',
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `state` enum('computed','reviewed','approved','waived','posted') NOT NULL DEFAULT 'computed' COMMENT 'دورةُ ق-13: النظامُ يحتسب · 12 يراجع · 19 يُجيز أو يُعفي · ثم يُنشر القيد',
  `waive_reason` varchar(255) DEFAULT NULL COMMENT 'سببُ الإعفاء — **إلزاميٌّ** عند waived (تفرضه الخدمة)',
  `note` varchar(255) DEFAULT NULL COMMENT 'المعيارُ اليدويُّ لـbonus_fixed (الجودةُ والسلامة · ق-10)',
  `event_id` int(11) DEFAULT NULL COMMENT 'قيدُ الدفتر المولَّد عند الإجازة — **نتيجةٌ لا مُدخَل** (ق-7)',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rule_key` varchar(24) GENERATED ALWAYS AS (concat(ifnull(cast(`rule_id` as char charset utf8mb4),_utf8mb4'*'),_utf8mb4':',ifnull(cast(`commitment_ref` as char charset utf8mb4),_utf8mb4'*'))) STORED COMMENT 'مرساةُ الاحتساب للمفتاح الفريد — * أي بلا قاعدةٍ/بند',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assessment_period` (`client_contract_id`,`kind`,`rule_key`,`period_from`,`period_to`) COMMENT 'احتسابٌ واحدٌ لكل (عقد × نوع × مرساة × فترة) — إعادةُ التشغيل تُحدّث ولا تُضاعف (ق-11)',
  KEY `ix_assessment_scope` (`company_id`,`is_deleted`),
  KEY `ix_assessment_state` (`state`),
  KEY `ix_assessment_period` (`client_contract_id`,`period_from`,`period_to`),
  KEY `fk_assessment_rule` (`rule_id`),
  CONSTRAINT `fk_assessment_contract` FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_assessment_rule` FOREIGN KEY (`rule_id`) REFERENCES `contract_penalty_rules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CON-02 §6 — احتسابُ الجزاء والحافز والحد الأدنى لفترةٍ بعينها بدورة اعتماده';

-- ── Table: contract_penalty_rules ──
CREATE TABLE `contract_penalty_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل المستأجر',
  `client_contract_id` int(11) NOT NULL COMMENT 'عقدُ العميل — contracts.id (FK حقيقيّ)',
  `rule_kind` enum('shortfall_pct','readiness_min','bonus_qty_pct','bonus_fixed') NOT NULL COMMENT 'نوعا جزاءٍ ونوعا حافزٍ — قائمةٌ مغلقةٌ عمدًا (ق-9): لا توسيعَ فوق الأربعة',
  `commitment_ref` int(10) unsigned DEFAULT NULL COMMENT 'البندُ الملتزَمُ المرساة (contract_commitments.id) — NULL أي قاعدةٌ على مستوى العقد كلِّه',
  `rate` decimal(6,3) DEFAULT NULL COMMENT 'نسبةُ الغرامة/الحافز: من قيمة الفارق (shortfall_pct) أو من قيمة الفترة (readiness_min)',
  `min_readiness_pct` decimal(5,2) DEFAULT NULL COMMENT 'عتبةُ الجاهزية — لـreadiness_min وحدَها. الجاهزيةُ = ساعاتُ العمل ÷ ساعاتِ الوردية',
  `fixed_amount` decimal(16,2) DEFAULT NULL COMMENT 'المبلغُ المقطوع — لـbonus_fixed وحدَه (الجودةُ والسلامةُ بمعيارٍ يدويٍّ معتمد · ق-10)',
  `cap_percent` decimal(5,2) DEFAULT NULL COMMENT 'السقفُ نسبةً من قيمة البند الملتزَم في الفترة (ق-12) — الأساسُ والسقفُ من جنسٍ واحد',
  `currency` varchar(8) DEFAULT NULL COMMENT 'عملةُ المبلغ المقطوع — NULL أي عملةُ العقد',
  `periodicity` enum('daily','monthly','contract') NOT NULL DEFAULT 'monthly' COMMENT 'دوريةُ الاحتساب — ويُؤجَّل حتى تكتمل الدورية ولا يُحتسب نسبيًّا (ق-11)',
  `valid_from` date NOT NULL COMMENT 'بدءُ السريان — NOT NULL عمدًا: يشمله المفتاحُ الفريد، وNULL تُمرّر التكراراتِ صامتةً',
  `valid_to` date DEFAULT NULL COMMENT 'نهايةُ السريان — NULL أي مفتوح',
  `note` varchar(255) DEFAULT NULL COMMENT 'بندُ العقد أو مرجعُ القاعدة',
  `commitment_key` varchar(16) GENERATED ALWAYS AS (ifnull(cast(`commitment_ref` as char charset utf8mb4),_utf8mb4'*')) STORED COMMENT 'مرساةُ القاعدة للمفتاح الفريد — * أي على مستوى العقد',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_penalty_rule` (`client_contract_id`,`rule_kind`,`commitment_key`,`valid_from`) COMMENT 'قاعدةٌ واحدةٌ لكل (عقد × نوع × مرساة × تاريخ سريان) — والتعديلُ صفٌّ جديدٌ بسريانه (لا رجعية)',
  KEY `ix_penalty_scope` (`company_id`,`is_deleted`),
  KEY `ix_penalty_contract` (`client_contract_id`,`valid_from`,`valid_to`),
  KEY `fk_penalty_rule_commitment` (`commitment_ref`),
  CONSTRAINT `fk_penalty_rule_commitment` FOREIGN KEY (`commitment_ref`) REFERENCES `contract_commitments` (`id`),
  CONSTRAINT `fk_penalty_rule_contract` FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CON-02 §6/§8 — قواعدُ الجزاء والحافز: نوعان لكلٍّ، بسقفٍ ومرساةٍ وسريان';

-- ── Table: contract_price_index_readings ──
CREATE TABLE `contract_price_index_readings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `index_code` varchar(32) NOT NULL,
  `reading_date` date NOT NULL,
  `value` decimal(20,8) NOT NULL,
  `source_ref` varchar(160) NOT NULL COMMENT 'مرجعُ المستند — إلزاميٌّ بنيويًّا',
  `note` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_index_reading` (`company_id`,`index_code`,`reading_date`),
  CONSTRAINT `ck_price_index_ref` CHECK (char_length(trim(`source_ref`)) > 0),
  CONSTRAINT `ck_price_index_value` CHECK (`value` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_price_revisions ──
CREATE TABLE `contract_price_revisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `contract_item_id` int(11) NOT NULL COMMENT 'سطرُ contractequipments المتأثر — صفٌّ لكل بندٍ ولو كان الشرطُ عقديًّا',
  `period_key` varchar(16) NOT NULL COMMENT 'مفتاحُ الدورة (2026-07 · 2026-Q3 · 2026-H1 · 2026)',
  `as_of_date` date NOT NULL,
  `effective_from` date NOT NULL COMMENT 'من هنا يسري السعرُ الجديد — ولا رجعيةَ قبله',
  `index_value` decimal(20,8) DEFAULT NULL COMMENT 'NULL = لا قراءةَ (مُعلَنٌ لا مخترع)',
  `index_source` varchar(160) DEFAULT NULL,
  `delta_percent` decimal(10,4) DEFAULT NULL COMMENT 'فارقُ المؤشر عن أساسه',
  `applied_percent` decimal(10,4) DEFAULT NULL COMMENT 'بعد التمرير والسقف',
  `old_price` decimal(14,4) DEFAULT NULL,
  `new_price` decimal(14,4) DEFAULT NULL,
  `outcome` enum('amended','below_threshold','capped','no_reading','no_base_price') NOT NULL,
  `amendment_id` int(10) unsigned DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_origin` enum('user','system') NOT NULL DEFAULT 'user' COMMENT 'منشأُ الصفِّ مُصرَّحًا: user=إنسانٌ بمعرِّفٍ موجب · system=كرونٌ بلا إنسان',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_revision_period_item` (`term_id`,`period_key`,`contract_item_id`),
  KEY `ix_price_revision_live` (`company_id`,`contract_id`,`effective_from`),
  KEY `fk_price_revision_amd` (`amendment_id`),
  KEY `ix_price_revision_term` (`term_id`),
  CONSTRAINT `fk_price_revision_amd` FOREIGN KEY (`amendment_id`) REFERENCES `contract_amendments` (`id`),
  CONSTRAINT `fk_price_revision_term` FOREIGN KEY (`term_id`) REFERENCES `contract_price_terms` (`id`),
  CONSTRAINT `chk_price_rev_origin_actor` CHECK (`created_origin` = 'user' and `created_by` is not null and `created_by` > 0 or `created_origin` = 'system' and `created_by` is null),
  CONSTRAINT `chk_price_rev_approver_known` CHECK (`approved_at` is null or `approved_by` is not null and `approved_by` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_price_terms ──
CREATE TABLE `contract_price_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'عقدُ العميل — منبعُ التسعير (CON-02 §1)',
  `contract_item_id` int(11) NOT NULL DEFAULT 0 COMMENT 'سطرُ contractequipments — **0 = كلُّ بنود العقد** (لا NULL: المفتاحُ الفريد لا يراه)',
  `trigger_kind` enum('fuel','inflation','fx') NOT NULL COMMENT 'وقودٌ · تضخمٌ · صرف — قائمةُ §2-③ نصًّا',
  `index_code` varchar(32) NOT NULL COMMENT 'رمزُ المؤشر — وللصرف رمزُ العملة (المصدرُ fin_fx_rates)',
  `base_index` decimal(20,8) NOT NULL COMMENT 'القيمةُ المرجعيةُ يومَ التعاقد',
  `base_date` date DEFAULT NULL,
  `threshold_percent` decimal(6,3) NOT NULL DEFAULT 0.000 COMMENT 'عتبةُ التفعيل — دونها لا تعديل',
  `pass_through_percent` decimal(6,3) NOT NULL DEFAULT 100.000 COMMENT 'كم من تغيّر المؤشر يدخل السعر',
  `cap_percent` decimal(6,3) DEFAULT NULL COMMENT 'سقفُ المراجعة الواحدة — NULL = بلا سقفٍ مكتوب',
  `periodicity` enum('daily','monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'quarterly' COMMENT 'دوريةُ المراجعة — daily سريانُه يومُه نفسُه بقرارِ المالك 2026-08-12',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','ended') NOT NULL DEFAULT 'active',
  `note` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_term_scope` (`contract_id`,`contract_item_id`,`trigger_kind`,`valid_from`),
  KEY `ix_price_term_co` (`company_id`,`contract_id`,`state`),
  CONSTRAINT `fk_price_term_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  CONSTRAINT `ck_price_term_base` CHECK (`base_index` > 0),
  CONSTRAINT `ck_price_term_cap` CHECK (`cap_percent` is null or `cap_percent` > 0),
  CONSTRAINT `ck_price_term_pass` CHECK (`pass_through_percent` > 0 and `pass_through_percent` <= 100),
  CONSTRAINT `ck_price_term_threshold` CHECK (`threshold_percent` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_resource_plan ──
CREATE TABLE `contract_resource_plan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_id` int(11) NOT NULL,
  `line_id` int(10) unsigned NOT NULL COMMENT 'بندُ البيع (P-02) — والخطةُ تقول كيف تُنتَج كميتُه',
  `equipment_type_id` int(11) NOT NULL COMMENT 'نوعُ المعدة (equipments_types) — لا معدةٌ بعينها: الخطةُ نوعٌ لا أصل',
  `equipment_size` int(11) DEFAULT NULL COMMENT 'الحجمُ/السعةُ التصنيفية كما في العقد',
  `count_basic` int(11) NOT NULL DEFAULT 0 COMMENT 'الأساسيةُ — هي التي تُنتج',
  `count_backup` int(11) NOT NULL DEFAULT 0 COMMENT 'الاحتياطيةُ — جاهزيةٌ لا إنتاجٌ مخطَّط',
  `shifts_per_day` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `hours_per_shift` decimal(5,2) NOT NULL DEFAULT 0.00,
  `operators_count` int(11) NOT NULL DEFAULT 0 COMMENT 'طلبُ عمالةٍ مخطَّط — **لا استحقاقَ ولا كلفة**',
  `supervisors_count` int(11) NOT NULL DEFAULT 0,
  `technicians_count` int(11) NOT NULL DEFAULT 0,
  `assistants_count` int(11) NOT NULL DEFAULT 0,
  `capacity_share_percent` decimal(6,3) NOT NULL DEFAULT 0.000 COMMENT 'حصةُ هذا النوع من كمية البند — Σ الحصص = 100 عند الاكتمال',
  `share_kind` enum('productive','backup_only','support') NOT NULL DEFAULT 'productive' COMMENT 'المنتجُ يحمل حصةً · والاحتياطيُّ والمساندُ صفرًا **معلَنًا**',
  `operational_site_id` int(10) unsigned DEFAULT NULL COMMENT 'نطاقُ العقد (P-01) إن خُصّصت الخطةُ لموقع',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('draft','active','ended') NOT NULL DEFAULT 'draft' COMMENT 'المنتهيةُ تبقى للتاريخ — والتعديلُ إنهاءٌ وإضافةٌ لا محو',
  `end_reason` varchar(200) DEFAULT NULL,
  `source_contract_equipment_id` int(11) DEFAULT NULL COMMENT 'أصلُها في contractequipments إن جاءت من القديم — والقديمُ لا يُمَس',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `live_type_key` int(11) GENERATED ALWAYS AS (if(`state` = _utf8mb4'ended' or `is_deleted` = 1,NULL,`equipment_type_id`)) STORED,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crp_live_type` (`line_id`,`live_type_key`) COMMENT 'نوعٌ واحدٌ نافذٌ لكل بند — ولا صفَّان يتنازعان الحصةَ نفسَها',
  KEY `ix_crp_lookup` (`company_id`,`contract_id`,`state`),
  KEY `ix_crp_type` (`equipment_type_id`),
  CONSTRAINT `fk_crp_line` FOREIGN KEY (`line_id`) REFERENCES `client_contract_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_crp_type` FOREIGN KEY (`equipment_type_id`) REFERENCES `equipments_types` (`id`),
  CONSTRAINT `ck_crp_counts` CHECK (`count_basic` >= 0 and `count_backup` >= 0 and `shifts_per_day` >= 1 and `shifts_per_day` <= 4 and `hours_per_shift` >= 0 and `hours_per_shift` <= 24 and `operators_count` >= 0 and `supervisors_count` >= 0 and `technicians_count` >= 0 and `assistants_count` >= 0),
  CONSTRAINT `ck_crp_ended` CHECK (`state` <> _utf8mb4'ended' or `end_reason` is not null),
  CONSTRAINT `ck_crp_productive` CHECK (`share_kind` <> _utf8mb4'productive' or `capacity_share_percent` > 0),
  CONSTRAINT `ck_crp_share` CHECK (`capacity_share_percent` >= 0 and `capacity_share_percent` <= 100),
  CONSTRAINT `ck_crp_window` CHECK (`valid_to` is null or `valid_to` >= `valid_from`),
  CONSTRAINT `ck_crp_zero_share` CHECK (`share_kind` = _utf8mb4'productive' or `capacity_share_percent` = 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contract_snapshots ──
CREATE TABLE `contract_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `as_of_date` date NOT NULL COMMENT 'تاريخُ الاحتساب الذي أُخذت له اللقطة',
  `snapshot_json` mediumtext NOT NULL COMMENT 'المضمونُ القانوني: الرأسُ + المكوّناتُ + القواعدُ بتوزيعها + التحمّل — فرزٌ ثابت',
  `fingerprint` char(40) NOT NULL COMMENT 'sha1 من المضمون القانوني — كشفُ التلاعب بالمقارنة',
  `amendment_ref` int(11) DEFAULT NULL COMMENT 'آخرُ ملحقٍ ساري — NULL معلَنًا حتى تُبنى H-10 (لا اختراع)',
  `valid` tinyint(4) NOT NULL DEFAULT 1,
  `invalidated_at` datetime DEFAULT NULL,
  `invalidated_from` date DEFAULT NULL COMMENT 'تاريخُ سريان الإبطال — ما قبله يبقى محكومًا بلقطته',
  `invalidation_reason` varchar(160) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_cs_contract_asof` (`contract_id`,`as_of_date`,`valid`),
  KEY `ix_cs_company` (`company_id`),
  KEY `ix_cs_fingerprint` (`fingerprint`),
  CONSTRAINT `fk_cs_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contractequipments ──
CREATE TABLE `contractequipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'رقم العقد',
  `equip_type` varchar(255) NOT NULL COMMENT 'نوع المعدة',
  `equip_size` int(11) DEFAULT NULL COMMENT 'حجم المعدة',
  `equip_count` int(11) DEFAULT NULL COMMENT 'عدد المعدات',
  `equip_count_basic` int(11) DEFAULT 0 COMMENT 'عدد المعدات الأساسية',
  `equip_count_backup` int(11) DEFAULT 0 COMMENT 'عدد المعدات الاحتياطية',
  `equip_shifts` int(11) DEFAULT 0 COMMENT 'عدد الورديات',
  `equip_unit` varchar(50) DEFAULT 'ساعة' COMMENT 'الوحدة',
  `shift1_start` time DEFAULT NULL COMMENT 'وقت بداية الوردية الأولى',
  `shift1_end` time DEFAULT NULL COMMENT 'وقت نهاية الوردية الأولى',
  `shift2_start` time DEFAULT NULL COMMENT 'وقت بداية الوردية الثانية',
  `shift2_end` time DEFAULT NULL COMMENT 'وقت نهاية الوردية الثانية',
  `shift_hours` int(11) DEFAULT 0 COMMENT 'إجمالي ساعات الوردية',
  `equip_total_month` int(11) DEFAULT NULL COMMENT 'إجمالي الساعات اليومية ',
  `equip_monthly_target` int(11) DEFAULT 0 COMMENT 'وحدات العمل في الشهر',
  `equip_total_contract` int(11) DEFAULT NULL COMMENT 'إجمالي ساعات العقد',
  `equip_price` decimal(10,2) DEFAULT 0.00 COMMENT 'السعر',
  `equip_operators` int(11) DEFAULT 0 COMMENT 'المشغلين',
  `equip_supervisors` int(11) DEFAULT 0 COMMENT 'المشرفين',
  `equip_technicians` int(11) DEFAULT 0 COMMENT 'الفنيين',
  `equip_assistants` int(11) DEFAULT 0 COMMENT 'المساعدين',
  `equip_price_currency` varchar(20) DEFAULT NULL COMMENT 'تمييز السعر',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`),
  CONSTRAINT `fk_contractequipments_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: contracts ──
CREATE TABLE `contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) DEFAULT NULL COMMENT 'العرضُ الأبُ الذي وُلد منه هذا العقد (INJ-0142)',
  `company_id` int(11) DEFAULT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int(11) DEFAULT 0,
  `contract_duration_months` int(11) DEFAULT 0,
  `contract_duration_days` int(11) NOT NULL DEFAULT 0,
  `equip_shifts_contract` int(11) DEFAULT 0 COMMENT 'عدد الورديات للعقد',
  `shift_contract` int(11) DEFAULT 0 COMMENT 'ساعات الوردية للعقد',
  `equip_total_contract_daily` int(11) DEFAULT 0 COMMENT 'إجمالي الوحدات يومياً للعقد',
  `total_contract_permonth` int(11) DEFAULT 0 COMMENT 'وحدات العمل في الشهر للعقد',
  `total_contract_units` int(11) DEFAULT 0 COMMENT 'إجمالي وحدات العقد',
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `transportation` mediumtext DEFAULT NULL,
  `accommodation` mediumtext DEFAULT NULL,
  `place_for_living` mediumtext DEFAULT NULL,
  `workshop` mediumtext DEFAULT NULL,
  `hours_monthly_target` int(11) DEFAULT 0,
  `forecasted_contracted_hours` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `daily_work_hours` varchar(20) DEFAULT NULL,
  `daily_operators` varchar(20) DEFAULT NULL,
  `first_party` varchar(255) DEFAULT NULL,
  `second_party` varchar(255) DEFAULT NULL,
  `witness_one` varchar(255) DEFAULT NULL,
  `witness_two` varchar(255) DEFAULT NULL,
  `price_currency_contract` varchar(20) DEFAULT NULL COMMENT 'عملة العقد',
  `paid_contract` varchar(100) DEFAULT NULL COMMENT 'المبلغ المدفوع',
  `payment_time` varchar(50) DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)',
  `guarantees` mediumtext DEFAULT NULL COMMENT 'الضمانات',
  `retention_pct` decimal(5,2) DEFAULT NULL COMMENT 'نسبةُ ضمان حسن التنفيذ المحتجزةُ من كل مستخلص — NULL أي لا احتجاز',
  `advance_recovery_pct` decimal(5,2) DEFAULT NULL COMMENT 'نسبةُ استهلاك الدفعة المقدمة من كل مستخلص — NULL أي لا استهلاك',
  `payment_date` date DEFAULT NULL COMMENT 'تاريخ الدفع',
  `contract_status` enum('مسودة','تفاوض','معتمد','موقَّع','نافذ','قيد التنفيذ','معلَّق','معدَّل','مجدَّد','منتهٍ','مقفل','مصفّى') DEFAULT NULL COMMENT 'H-02 · OPM-01 §3: آلةُ حالات العقد. الانتقالُ يُحرَس في ContractStateMachine لا في الشاشة. «نافذ» = شرطُ فتح الحاويات (H-01)',
  `pause_state_before` enum('مسودة','تفاوض','معتمد','موقَّع','نافذ','قيد التنفيذ','معلَّق','معدَّل','مجدَّد','منتهٍ','مقفل','مصفّى') DEFAULT NULL COMMENT 'H-02: الحالةُ قبل التعليق — يعود إليها بالاستئناف. NULL = عُلّق قبل الآلة فلا يُخمَّن',
  `pause_reason` mediumtext DEFAULT NULL,
  `pause_date` date DEFAULT NULL COMMENT 'تاريخ إيقاف العقد',
  `resume_date` date DEFAULT NULL COMMENT 'تاريخ استئناف العقد',
  `termination_type` varchar(50) DEFAULT NULL,
  `termination_reason` mediumtext DEFAULT NULL,
  `merged_with` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '1=نشط, 0=موقوف',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `project_id` int(11) NOT NULL COMMENT 'PLAN-03 §2.1: لا عقدَ بلا مشروع — بنيويًّا لا رجاءً',
  `site_id` int(11) DEFAULT NULL COMMENT '⚠ مرآةٌ موروثةٌ (P-01) — المصدرُ `contract_operational_sites`. لا يُكتب ولا يُقرأ في حسابٍ جديد، ويبقى لأن الحذفَ ممنوع (§0-④)',
  `readiness_state` enum('لم يبدأ','جارٍ','مجتاز') NOT NULL DEFAULT 'لم يبدأ' COMMENT 'INJAZ-S05 §6.6 — محسوبٌ من readiness_lines (عرضٌ لا إنفاذ)',
  `signing_authority_ref` varchar(120) DEFAULT NULL COMMENT 'BR-CEO-01: سلطة أصلية أو مرجع تفويض موثق — يُلزم عند الانتقال إلى موقَّع',
  PRIMARY KEY (`id`),
  KEY `fk_contracts_merged` (`merged_with`),
  KEY `idx_contracts_project_id` (`project_id`),
  KEY `idx_contracts_signing_date` (`contract_signing_date`),
  KEY `idx_contracts_status_contract_status` (`status`,`contract_status`),
  KEY `ix_contract_state` (`company_id`,`contract_status`),
  KEY `ix_contracts_site` (`site_id`),
  KEY `ix_contracts_quotation` (`quotation_id`),
  CONSTRAINT `fk_contracts_merged` FOREIGN KEY (`merged_with`) REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_contracts_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`),
  CONSTRAINT `fk_contracts_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: cost_bearers ──
CREATE TABLE `cost_bearers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `owner_type` enum('component','rule') NOT NULL COMMENT 'المالك: مكوّنُ أجرٍ أو قاعدةُ حافز (§7.1)',
  `owner_id` int(11) NOT NULL,
  `bearer_type` enum('project','client_contract','dept','company') NOT NULL COMMENT 'جهاتُ §3.3 الأربع: مشروعٌ · عقدُ عميل · إدارةٌ داخلية · كيانُ الشركة',
  `bearer_id` int(11) DEFAULT NULL COMMENT 'NULL لجهة company (صاحبُ العمل نفسُه)',
  `percent` decimal(5,2) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_cb_owner` (`owner_type`,`owner_id`),
  KEY `ix_cb_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: coverage_settlement_lines ──
CREATE TABLE `coverage_settlement_lines` (
  `ln_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `cov_id` bigint(20) unsigned NOT NULL,
  `party` enum('client','failed_supplier','covering_supplier','operator') NOT NULL COMMENT 'الطرف (§7)',
  `effect` enum('billable','gap_kept','exceptional_line','entitlement') NOT NULL COMMENT 'billable=يُفوتر كاملًا · gap_kept=العجزُ باقٍ بجزائه · exceptional_line=بندُ تغطيةٍ مستقلٌّ بسعره · entitlement=استحقاقُ المشغّل بعقده',
  `qty` decimal(18,3) NOT NULL DEFAULT 0.000,
  `measure_code` enum('hour','ton','trip','meter') DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT NULL COMMENT 'القيمةُ إن سُعِّرت — بسعرِ التغطية المتفق لا بحصةٍ تُرفع',
  `currency` varchar(8) DEFAULT NULL,
  `settlement_ref` varchar(60) DEFAULT NULL COMMENT 'مرجعُ التسوية التي قُرئ فيها البند',
  `note` varchar(200) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ln_id`),
  KEY `ix_csl_cov` (`cov_id`,`party`),
  KEY `ix_csl_company` (`company_id`,`settlement_ref`),
  CONSTRAINT `fk_csl_cov` FOREIGN KEY (`cov_id`) REFERENCES `substitute_coverages` (`cov_id`),
  CONSTRAINT `ck_csl_enums_not_empty` CHECK (`party` <> _utf8mb4'' and `effect` <> _utf8mb4'')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: credit_debit_notes ──
CREATE TABLE `credit_debit_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `note_no` varchar(32) NOT NULL COMMENT 'CDN-سنة-تسلسل — ترقيمٌ خادميٌّ لكل شركة',
  `note_kind` enum('credit','debit') NOT NULL COMMENT 'credit=يُنقص ذمّةَ العميل · debit=يزيدها. المبلغُ موجبٌ دائمًا والاتجاهُ يحمل الإشارة',
  `claim_id` int(10) unsigned NOT NULL COMMENT 'المستخلصُ الأصلي — مرجعٌ لا يُمسّ',
  `claim_line_id` int(10) unsigned DEFAULT NULL COMMENT 'سطرُه بعينه إن كان الإشعارُ على سطر — NULL = على المستخلص كلِّه',
  `receivable_id` int(11) DEFAULT NULL COMMENT 'الذمّةُ التي يتحرك بها — تُملأ عند الإجازة',
  `invoice_no` varchar(64) DEFAULT NULL COMMENT 'رقمُ الفاتورة الأصلية — نسخةٌ للقراءة',
  `currency` varchar(16) NOT NULL,
  `amount` decimal(18,2) NOT NULL COMMENT 'موجبٌ دائمًا — الاتجاهُ في note_kind',
  `reason` varchar(255) NOT NULL COMMENT 'سببُ الإشعار — إلزام',
  `doc_ref` varchar(120) NOT NULL COMMENT 'مرجعُ المستند المؤيِّد — إلزام',
  `state` enum('draft','review','approved','cancelled') NOT NULL DEFAULT 'draft',
  `idem_key` varchar(64) DEFAULT NULL COMMENT 'مفتاحُ العطالة من المنادي — يمنع إصدارَ الإشعار نفسِه مرتين',
  `prepared_by` int(10) unsigned DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL COMMENT 'حقيقةُ الإشعار في الجذر المحايد',
  `version` int(11) NOT NULL DEFAULT 1 COMMENT 'قفلٌ تفاؤليّ — نظيرُ claims.version',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_note_no` (`company_id`,`note_no`),
  UNIQUE KEY `uq_note_idem` (`company_id`,`claim_id`,`note_kind`,`idem_key`),
  KEY `ix_claim` (`company_id`,`claim_id`),
  KEY `ix_state` (`company_id`,`state`),
  KEY `ix_receivable` (`company_id`,`receivable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-02 — إشعاراتٌ دائنة/مدينة تصحّح فاتورةً صادرةً بلا أن تمسّها';

-- ── Table: daily_plan_lines ──
CREATE TABLE `daily_plan_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `equipment_container_id` int(10) unsigned NOT NULL COMMENT 'حاويةُ المعدة — مصدرُ الاحتياج (OPM-01 §4)',
  `equipment_id` int(10) unsigned DEFAULT NULL,
  `shift_no` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `operator_employee_id` int(11) DEFAULT NULL,
  `operator_container_id` int(10) unsigned DEFAULT NULL COMMENT '«لا تخصيصَ خارج حاوية» — حاويةُ المشغّل من سلسلة معدته حصرًا',
  `note` varchar(200) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `plan_date` date NOT NULL,
  `state` enum('draft','approved','opened','closed') NOT NULL DEFAULT 'draft' COMMENT 'الدورة: توزيعٌ (draft) ← اعتمادُ الحركة ← فتحُ الغد ← إقفالُ يومه',
  `reopen_reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dp_project_date` (`project_id`,`plan_date`) COMMENT 'خطةٌ واحدةٌ ليومِ المشروع',
  KEY `ix_dp_company` (`company_id`),
  KEY `ix_dp_state_date` (`state`,`plan_date`),
  CONSTRAINT `fk_dp_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: decision_reasons ──
CREATE TABLE `decision_reasons` (
  `reason_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `domain` enum('sales','suppliers','financiers','workforce','fleet','maintenance','procurement','treasury','operations') NOT NULL,
  `reason_kind` enum('return','reject','state_change','exception') NOT NULL,
  `code` varchar(60) NOT NULL,
  `text_ar` varchar(200) NOT NULL,
  `requires_document` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`reason_id`),
  UNIQUE KEY `uq_dr_code` (`domain`,`reason_kind`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §10: أسباب القرار قائمة محكومة لا نص حر — فتُقاس ويُبنى عليها تقرير';

-- ── Table: deduction_proposals ──
CREATE TABLE `deduction_proposals` (
  `ded_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `person_id` int(11) NOT NULL,
  `period` char(7) NOT NULL,
  `source` enum('late','missing_punch','leave_no_balance','unexcused','penalty','advance_installment') NOT NULL,
  `source_ref` varchar(120) NOT NULL COMMENT 'المستند/اليوم المصدر — لا خصم بلا مصدر (M-11)',
  `proposed_amount` decimal(14,2) NOT NULL,
  `is_voluntary` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'الاستقطاعات الاختيارية (سلف · نيابة) تخضع لحد ثلث الصافي — والجزاءات والغياب خارجه (DEC ②)',
  `state` enum('Proposed','Reviewed','Approved','Posted','Waived') NOT NULL DEFAULT 'Proposed',
  `reviewed_by` int(11) DEFAULT NULL,
  `approvals_ref` varchar(120) DEFAULT NULL COMMENT 'مرجع سلّم GOV-01',
  `posted_run_id` int(11) DEFAULT NULL,
  `waiver_ref` int(11) DEFAULT NULL COMMENT 'قرار الإعفاء المستقل (waivers_reversals) — والأصل باقٍ',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `proposed_by` int(11) DEFAULT NULL COMMENT 'من اقترحَ الخصم (users.id) — أساسُ منعِ اعتمادِ الذات',
  `approved_by` int(11) DEFAULT NULL COMMENT 'من اعتمدَه — يدٌ تخالف المراجعَ والمقترح',
  PRIMARY KEY (`ded_id`),
  UNIQUE KEY `uq_dp_source` (`person_id`,`period`,`source`,`source_ref`),
  KEY `ix_dp_state` (`company_id`,`period`,`state`),
  CONSTRAINT `chk_ded_prop_two_hands` CHECK (`approved_by` is null or `proposed_by` is null or `approved_by` <> `proposed_by`),
  CONSTRAINT `chk_ded_prop_review_hand` CHECK (`approved_by` is null or `reviewed_by` is null or `approved_by` <> `reviewed_by`),
  CONSTRAINT `ck_dp_posted_needs_approval` CHECK (`state` <> _utf8mb4'Posted' or `approvals_ref` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: deduction_types ──
CREATE TABLE `deduction_types` (
  `ded_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` int(10) unsigned NOT NULL,
  `ded_kind` varchar(60) NOT NULL,
  `formula_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`formula_json`)),
  `cap` decimal(18,4) DEFAULT NULL,
  `auto_propose` tinyint(1) NOT NULL DEFAULT 1,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'دائمًا 1 — لا خصم آلي الترحيل في أي إدارة',
  PRIMARY KEY (`ded_id`),
  KEY `ix_dt_policy` (`policy_id`),
  CONSTRAINT `fk_dt_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`),
  CONSTRAINT `ck_dt_approval` CHECK (`requires_approval` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: dept_policies ──
CREATE TABLE `dept_policies` (
  `policy_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `domain` enum('sales','suppliers','financiers','workforce','fleet','maintenance','procurement','treasury') NOT NULL,
  `name_ar` varchar(160) NOT NULL,
  `scope_type` enum('department','project','contract','employee_type','asset_type') NOT NULL DEFAULT 'department',
  `scope_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = الإدارة كلها',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `state` enum('draft','active','superseded') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`policy_id`),
  UNIQUE KEY `uq_dp_scope` (`company_id`,`domain`,`scope_type`,`scope_id`,`valid_from`),
  KEY `ix_dp_domain` (`company_id`,`domain`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §2: هوية السياسة ونطاقها — ولا سياسة بلا نطاق ومدة، ولا تشغيل لإدارة بلا سياسة نافذة';

-- ── Table: driver_contract_notes ──
CREATE TABLE `driver_contract_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'معرف عقد السائق',
  `note` text NOT NULL COMMENT 'الملاحظة أو الإجراء المتخذ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإضافة',
  PRIMARY KEY (`id`),
  KEY `idx_driver_contract_notes_contract_id` (`contract_id`),
  CONSTRAINT `fk_driver_contract_notes_contract` FOREIGN KEY (`contract_id`) REFERENCES `drivercontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل التدقيق لإجراءات عقود السائقين';

-- ── Table: drivercontractequipments ──
CREATE TABLE `drivercontractequipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'معرف عقد السائق من جدول drivercontracts',
  `equip_type` varchar(100) DEFAULT NULL COMMENT 'نوع المعدة (حفار، قلاب، خرامة)',
  `equip_size` int(11) DEFAULT NULL COMMENT 'حجم المعدة',
  `equip_count` int(11) DEFAULT NULL COMMENT 'عدد المعدات',
  `equip_count_basic` int(11) DEFAULT 0 COMMENT 'عدد المعدات الأساسية',
  `equip_count_backup` int(11) DEFAULT 0 COMMENT 'عدد المعدات الاحتياطية',
  `equip_shifts` int(11) DEFAULT NULL COMMENT 'عدد الورديات',
  `equip_unit` varchar(50) DEFAULT NULL COMMENT 'وحدة القياس (ساعة، طن، متر)',
  `shift1_start` time DEFAULT NULL COMMENT 'بداية الوردية الأولى',
  `shift1_end` time DEFAULT NULL COMMENT 'نهاية الوردية الأولى',
  `shift2_start` time DEFAULT NULL COMMENT 'بداية الوردية الثانية',
  `shift2_end` time DEFAULT NULL COMMENT 'نهاية الوردية الثانية',
  `shift_hours` decimal(10,2) DEFAULT NULL COMMENT 'ساعات الوردية',
  `equip_total_month` decimal(10,2) DEFAULT NULL COMMENT 'إجمالي الوحدات يومياً',
  `equip_monthly_target` decimal(10,2) DEFAULT NULL COMMENT 'وحدات العمل في الشهر',
  `equip_total_contract` decimal(10,2) DEFAULT NULL COMMENT 'إجمالي وحدات العقد',
  `equip_price` decimal(10,2) DEFAULT NULL COMMENT 'السعر للوحدة',
  `equip_price_currency` varchar(20) DEFAULT NULL COMMENT 'العملة (دولار، جنيه)',
  `equip_operators` int(11) DEFAULT NULL COMMENT 'عدد المشغلين',
  `equip_supervisors` int(11) DEFAULT NULL COMMENT 'عدد المشرفين',
  `equip_technicians` int(11) DEFAULT NULL COMMENT 'عدد الفنيين',
  `equip_assistants` int(11) DEFAULT NULL COMMENT 'عدد المساعدين',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`),
  CONSTRAINT `fk_drivercontractequipments_contract` FOREIGN KEY (`contract_id`) REFERENCES `drivercontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='معدات عقود السائقين';

-- ── Table: drivercontracts ──
CREATE TABLE `drivercontracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int(11) DEFAULT 0,
  `contract_duration_months` int(11) DEFAULT 0,
  `contract_duration_days` int(11) DEFAULT 0,
  `equip_shifts_contract` int(11) DEFAULT 0 COMMENT 'عدد ورديات المعدات في العقد',
  `shift_contract` int(11) DEFAULT 0 COMMENT 'الوردية',
  `equip_total_contract_daily` decimal(10,2) DEFAULT 0.00 COMMENT 'إجمالي الوحدات اليومية للعقد',
  `total_contract_permonth` decimal(10,2) DEFAULT 0.00 COMMENT 'إجمالي وحدات العمل في الشهر',
  `total_contract_units` decimal(10,2) DEFAULT 0.00 COMMENT 'إجمالي وحدات العمل للعقد',
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `transportation` mediumtext DEFAULT NULL,
  `accommodation` mediumtext DEFAULT NULL,
  `place_for_living` mediumtext DEFAULT NULL,
  `workshop` mediumtext DEFAULT NULL,
  `equip_type` varchar(100) DEFAULT NULL,
  `equip_size` int(11) DEFAULT NULL,
  `equip_count` int(11) DEFAULT 0,
  `equip_target_per_month` int(11) DEFAULT 0,
  `equip_total_month` int(11) DEFAULT 0,
  `equip_total_contract` int(11) DEFAULT 0,
  `mach_type` varchar(100) DEFAULT NULL,
  `mach_size` int(11) DEFAULT NULL,
  `mach_count` int(11) DEFAULT 0,
  `mach_target_per_month` int(11) DEFAULT 0,
  `mach_total_month` int(11) DEFAULT 0,
  `mach_total_contract` int(11) DEFAULT 0,
  `hours_monthly_target` int(11) DEFAULT 0,
  `forecasted_contracted_hours` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `daily_work_hours` varchar(20) DEFAULT NULL,
  `daily_operators` varchar(20) DEFAULT NULL,
  `first_party` varchar(255) DEFAULT NULL,
  `second_party` varchar(255) DEFAULT NULL,
  `witness_one` varchar(255) DEFAULT NULL,
  `witness_two` varchar(255) DEFAULT NULL,
  `price_currency_contract` varchar(50) DEFAULT NULL COMMENT 'عملة العقد',
  `paid_contract` decimal(10,2) DEFAULT 0.00 COMMENT 'المبلغ المدفوع',
  `payment_time` varchar(50) DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)',
  `guarantees` mediumtext DEFAULT NULL COMMENT 'الضمانات',
  `payment_date` date DEFAULT NULL COMMENT 'تاريخ الدفع',
  `pause_reason` mediumtext DEFAULT NULL COMMENT 'سبب الإيقاف',
  `pause_date` date DEFAULT NULL COMMENT 'تاريخ الإيقاف',
  `resume_date` date DEFAULT NULL COMMENT 'تاريخ الاستئناف',
  `termination_type` varchar(50) DEFAULT NULL COMMENT 'نوع الإنهاء',
  `termination_reason` mediumtext DEFAULT NULL COMMENT 'سبب الإنهاء',
  `merged_with` int(11) DEFAULT NULL COMMENT 'دمج مع عقد آخر',
  `project_id` int(11) NOT NULL DEFAULT 0,
  `project_contract_id` int(11) DEFAULT NULL COMMENT 'معرف عقد المشروع',
  `status` tinyint(1) DEFAULT 1,
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
  `ep_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `permission_code` varchar(120) NOT NULL,
  `scope_rule` varchar(120) NOT NULL,
  `amount_cap` decimal(18,2) DEFAULT NULL,
  `source_kind` enum('relation','family','level','title','assignment','exception','grant') NOT NULL,
  `source_ref` varchar(120) NOT NULL,
  `computed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ep_id`),
  KEY `idx_ep_person` (`company_id`,`person_id`,`permission_code`),
  KEY `idx_ep_code` (`permission_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: جدول مشتق — ومنه يُجاب «لماذا يملكها؟»';

-- ── Table: employee_advances ──
CREATE TABLE `employee_advances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL COMMENT 'employees.id — المستفيد',
  `advance_type` enum('cash','on_behalf','charged') NOT NULL DEFAULT 'cash' COMMENT 'نقديةٌ · دفعٌ نيابةً عنه (علاجٌ · تذاكرُ · رسوم) · مصروفٌ محمَّلٌ عليه',
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(8) DEFAULT NULL,
  `doc_ref` varchar(120) NOT NULL COMMENT 'مستندُ الصرف — إلزاميٌّ بنيويًّا',
  `issued_date` date NOT NULL,
  `installments_count` int(11) NOT NULL DEFAULT 1 COMMENT 'عددُ أقساط الاسترداد',
  `installment_amount` decimal(18,2) NOT NULL COMMENT 'قسطُ الفترة الواحدة',
  `first_deduction_period` date DEFAULT NULL COMMENT 'أولُ فترةٍ يبدأ منها الخصم',
  `recovered` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'المستردُّ فعلًا — تُحرّكه المقاصّة',
  `balance` decimal(18,2) GENERATED ALWAYS AS (`amount` - `recovered`) STORED COMMENT '**مولَّد** — لا يُكتب ولا ينحرف عن حركته',
  `state` enum('draft','approved','active','settled','cancelled') NOT NULL DEFAULT 'draft',
  `note` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_adv_person_state` (`person_id`,`state`),
  KEY `ix_adv_co` (`company_id`,`state`),
  CONSTRAINT `ck_adv_amount` CHECK (`amount` > 0),
  CONSTRAINT `ck_adv_doc` CHECK (char_length(trim(`doc_ref`)) > 0),
  CONSTRAINT `ck_adv_inst` CHECK (`installments_count` >= 1 and `installment_amount` > 0),
  CONSTRAINT `ck_adv_recovered` CHECK (`recovered` >= 0 and `recovered` <= `amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employee_contract_amendments ──
CREATE TABLE `employee_contract_amendments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `amend_type` enum('pay_change','duration_change','location_change','scope_change','other') NOT NULL COMMENT 'أنواعُ §4: «تغييرُ أجرٍ أو مدةٍ أو موقعٍ أو نطاق» + مخرجُ سلامة',
  `effective_from` date NOT NULL COMMENT '«ملحقٌ معتمَدٌ بسريان» — والقراءةُ تأخذ الأحدثَ سريانًا قبل تاريخ الاحتساب',
  `changes_json` mediumtext NOT NULL COMMENT '«ما يغيّره حقلًا حقلًا (قبل/بعد)» — و«قبل» يُلتقط من الواقع الحي',
  `state` enum('draft','approved','rejected') NOT NULL DEFAULT 'draft',
  `reject_reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eca_contract_eff_type` (`contract_id`,`effective_from`,`amend_type`),
  KEY `ix_eca_company` (`company_id`),
  CONSTRAINT `fk_eca_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employee_contracts ──
CREATE TABLE `employee_contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'صاحبُ العمل — عزلُ المستأجر (TenantRegistry)',
  `employee_id` int(11) NOT NULL COMMENT 'سجلُّ الأشخاص القائم — «العقدُ يشير إليه ولا ينسخ»',
  `category` enum('permanent','project','operator','supplier_worker') NOT NULL,
  `relation_type` varchar(50) DEFAULT NULL COMMENT 'طبيعةُ الارتباط — يحمل نوعَ الموروث نصًّا عند الترحيل',
  `project_id` int(11) DEFAULT NULL COMMENT 'فئةُ «مشروع» مرتبطةٌ بمشروع عميلٍ ومدتِه (CON-01 §2)',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `probation_end` date DEFAULT NULL,
  `pay_model_id` int(11) NOT NULL COMMENT '«اختيارٌ مستقلٌّ لا يُشتق من الوظيفة» — من الكتالوج المحكوم حصرًا',
  `currency` varchar(8) DEFAULT NULL COMMENT 'NULL حيث لم يسجَّل — لا تلفيق',
  `eos_days_per_year` decimal(5,2) DEFAULT NULL COMMENT 'أيامُ نهاية الخدمة لكل سنةِ خدمة — NULL = لم تُكتب فلا تُحتسب (تُعلَن)',
  `leave_days_per_year` decimal(5,2) DEFAULT NULL COMMENT 'أيامُ الإجازة المستحقة لكل سنة — NULL = لم تُكتب فلا تُحتسب',
  `state` enum('draft','completed','validated','approved','rejected','accepted','declined','signed','active','confirmed','amended','suspended','seconded','expired','terminated','settled','closed','archived') NOT NULL DEFAULT 'draft',
  `state_before_hold` varchar(20) DEFAULT NULL COMMENT 'ما قبل التعليق/الإعارة — العودةُ إلى حيث كان لا إلى حالةٍ مفترضة (قياسُ pause_state_before)',
  `hold_reason` varchar(255) DEFAULT NULL,
  `signed_file_ref` varchar(255) DEFAULT NULL COMMENT 'النسخةُ الموقَّعة — ثابتةٌ لا تُستبدل (إلزامُها مع H-10)',
  `version` int(11) NOT NULL DEFAULT 1 COMMENT 'قفلٌ تفاؤلي — 409 عند التزاحم',
  `source_table` varchar(32) DEFAULT NULL COMMENT 'الترحيلُ قراءةً: مصدرُ الصف — الكتابةُ تبقى فيه حتى إقفال القديم بمطابقةٍ (N-04)',
  `source_id` int(11) DEFAULT NULL COMMENT 'معرّفُ الصف في مصدره (لرؤوس سياسات المشغّلين: معرّفُ المشغّل — إسقاطُ مجموعة)',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  CONSTRAINT `ck_ec_eos_days` CHECK (`eos_days_per_year` is null or `eos_days_per_year` > 0),
  CONSTRAINT `ck_ec_leave_days` CHECK (`leave_days_per_year` is null or `leave_days_per_year` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employee_final_settlement_lines ──
CREATE TABLE `employee_final_settlement_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `settlement_id` int(11) NOT NULL,
  `line_type` enum('dues','leave','eos','advance_offset') NOT NULL,
  `description` varchar(255) NOT NULL,
  `qty` decimal(12,3) DEFAULT NULL COMMENT 'أيامٌ أو سنواتٌ بحسب البند',
  `rate` decimal(18,2) DEFAULT NULL COMMENT 'الأجرُ اليوميُّ المحسوبُ من الأساس',
  `amount` decimal(18,2) NOT NULL,
  `computable` tinyint(4) NOT NULL DEFAULT 1 COMMENT '0 = بلا قاعدةٍ مكتوبةٍ — يُعلَن ولا يُقدَّر',
  `source_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fs_line` (`settlement_id`,`line_type`),
  CONSTRAINT `fk_fs_line` FOREIGN KEY (`settlement_id`) REFERENCES `employee_final_settlements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employee_final_settlements ──
CREATE TABLE `employee_final_settlements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `effective_date` date NOT NULL COMMENT 'تاريخُ الأثر — «المستحقُّ **حتى تاريخ الأثر**»',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `service_years` decimal(6,3) NOT NULL DEFAULT 0.000,
  `dues_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `leave_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `eos_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `advances_offset` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'موجبٌ دائمًا — ويُطرح في الصافي',
  `advances_remaining` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ما لم تسعه المقاصّة — «لا يُقاصّ أكثرُ من المستحق» ويبقى رصيدًا مفتوحًا يُعلَن',
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '**محسوبٌ لا مُدخَل**: المستحقُّ + الإجازةُ + نهايةُ الخدمة − السلف',
  `recognized_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ما تعترف به التصفيةُ **جديدًا** (إجازةٌ + نهايةُ خدمة) — والمستحقُّ السابقُ اعتُرف به في مصدره',
  `snapshot_id` int(11) DEFAULT NULL COMMENT 'لقطةُ العقد التي احتُسب منها (H-11) — «من اللقطة» إسنادًا لا دعوى',
  `snapshot_fingerprint` varchar(64) DEFAULT NULL COMMENT 'بصمتُها ساعةَ الاحتساب — يُكشف أيُّ تلاعبٍ بمقارنتها',
  `net_due_ref` int(11) DEFAULT NULL COMMENT 'مرجعُ الحدث المالي الواحد (fin_dues) — «لا يتكرر»',
  `basis_json` text DEFAULT NULL COMMENT 'لقطةُ القواعد والأسس لحظةَ الاحتساب — لا اشتقاقٌ لاحق',
  `state` enum('draft','approved','cancelled') NOT NULL DEFAULT 'draft',
  `clearance_doc` varchar(120) DEFAULT NULL COMMENT 'مرفقُ الإخلاء (§6)',
  `note` varchar(255) DEFAULT NULL,
  `prepared_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_final_settlement` (`contract_id`) COMMENT '«بمفتاح (العقد × التصفية) لا يتكرر»',
  KEY `ix_final_settlement` (`company_id`,`employee_id`,`state`),
  CONSTRAINT `fk_fs_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_fs_approved` CHECK (`state` <> _utf8mb4'approved' or `approved_by` is not null and `clearance_doc` is not null and `clearance_doc` <> _utf8mb4''),
  CONSTRAINT `ck_fs_cancel` CHECK (`state` <> _utf8mb4'cancelled' or `cancel_reason` is not null and `cancel_reason` <> _utf8mb4''),
  CONSTRAINT `ck_fs_hands` CHECK (`approved_by` is null or `prepared_by` is null or `approved_by` <> `prepared_by`),
  CONSTRAINT `ck_fs_net` CHECK (`net_amount` >= 0),
  CONSTRAINT `ck_fs_offset` CHECK (`advances_offset` >= 0 and `advances_remaining` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employee_roles ──
CREATE TABLE `employee_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emprole_company_name` (`company_id`,`name`),
  KEY `idx_emprole_company` (`company_id`),
  KEY `idx_emprole_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: employees ──
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_type` varchar(40) NOT NULL DEFAULT 'سائق/مشغّل',
  `company_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `nickname` varchar(255) DEFAULT NULL COMMENT 'اسم الشهرة/الكنية',
  `identity_type` varchar(50) DEFAULT NULL COMMENT 'نوع الهوية',
  `identity_number` varchar(100) DEFAULT NULL COMMENT 'رقم الهوية',
  `identity_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الهوية',
  `employee_photo` varchar(255) DEFAULT NULL,
  `identity_photo` varchar(255) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL COMMENT 'رقم رخصة القيادة',
  `license_type` varchar(100) DEFAULT NULL COMMENT 'نوع رخصة القيادة',
  `license_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء رخصة القيادة',
  `license_issuer` varchar(255) DEFAULT NULL COMMENT 'جهة إصدار الرخصة',
  `specialized_equipment` mediumtext DEFAULT NULL COMMENT 'نوع المعدة المتخصص فيها (متعدد)',
  `years_in_field` int(11) DEFAULT NULL COMMENT 'سنوات العمل في المجال',
  `years_on_equipment` int(11) DEFAULT NULL COMMENT 'سنوات العمل على هذا النوع من المعدات',
  `skill_level` varchar(50) DEFAULT NULL COMMENT 'مستوى الكفاءة المهنية',
  `certificates` mediumtext DEFAULT NULL COMMENT 'الشهادات والتدريبات',
  `owner_supervisor` varchar(255) DEFAULT NULL COMMENT 'اسم المالك/المشرف المباشر',
  `supplier_id` int(11) DEFAULT NULL COMMENT 'المورد الذي يعمل معه',
  `employment_affiliation` varchar(100) DEFAULT NULL COMMENT 'تبعية المشغل',
  `salary_type` varchar(50) DEFAULT NULL COMMENT 'نوع الراتب/الأجر',
  `monthly_salary` decimal(10,2) DEFAULT NULL COMMENT 'المبلغ الشهري التقريبي',
  `email` varchar(255) DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `address` mediumtext DEFAULT NULL COMMENT 'العنوان',
  `performance_rating` varchar(50) DEFAULT NULL COMMENT 'تقييم الكفاءة التشغيلية',
  `behavior_record` varchar(50) DEFAULT NULL COMMENT 'سجل السلوك والانضباط',
  `accident_record` varchar(50) DEFAULT NULL COMMENT 'سجل الحوادث والأعطال',
  `health_status` varchar(50) DEFAULT NULL COMMENT 'الحالة الصحية',
  `health_issues` mediumtext DEFAULT NULL COMMENT 'المشاكل الصحية المعروفة',
  `vaccinations_status` varchar(50) DEFAULT NULL COMMENT 'التطعيمات والفحوصات',
  `previous_employer` varchar(255) DEFAULT NULL COMMENT 'اسم جهة التوظيف السابقة',
  `employment_duration` varchar(100) DEFAULT NULL COMMENT 'مدة العمل معهم',
  `reference_contact` varchar(255) DEFAULT NULL COMMENT 'مرجع للاتصال',
  `general_notes` mediumtext DEFAULT NULL COMMENT 'ملاحظات عامة',
  `employee_status` varchar(50) DEFAULT 'نشط',
  `employment_classification` enum('مرشح','متدرب','مقبول','مستقيل','مفصول') DEFAULT NULL COMMENT 'مسار التوظيف — مستقل عن employee_status التشغيلية',
  `start_date` date DEFAULT NULL COMMENT 'تاريخ البدء الفعلي',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'تاريخ التسجيل في النظام',
  `phone` varchar(255) NOT NULL,
  `phone_alternative` varchar(50) DEFAULT NULL COMMENT 'رقم هاتف بديل',
  `status` tinyint(1) DEFAULT 1,
  `birth_date` date DEFAULT NULL,
  `nationality` varchar(80) DEFAULT NULL,
  `blood_type` varchar(8) DEFAULT NULL,
  `whatsapp` varchar(50) DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_relation` varchar(80) DEFAULT NULL,
  `emergency_contact_phone` varchar(50) DEFAULT NULL,
  `license_issue_date` date DEFAULT NULL,
  `license_grade` varchar(40) DEFAULT NULL,
  `license_photo` varchar(255) DEFAULT NULL,
  `medical_report_path` varchar(255) DEFAULT NULL,
  `job_title_id` int(11) DEFAULT NULL,
  `employee_role_id` int(11) DEFAULT NULL,
  `is_workforce` tinyint(1) NOT NULL DEFAULT 0,
  `worker_category` varchar(40) DEFAULT NULL,
  `source_type` enum('شركة','مورد','مقاول') DEFAULT NULL,
  `workforce_class` enum('أساسي','احتياطي','بديل مؤقت','تغطية إجازة','تجاري مؤقت') DEFAULT NULL,
  `job_grade` varchar(40) DEFAULT NULL,
  `workforce_state` enum('مرشّح','مسجّل','مؤهّل','متعاقد','مخصّص','في إجازة','منتهٍ') DEFAULT NULL,
  `medical_fitness_status` enum('لائق للعمل','لائق بشروط','موقوف طبيًّا','يحتاج إعادة تقييم') DEFAULT NULL,
  `fitness_conditions` varchar(255) DEFAULT NULL,
  `primary_backup_id` int(11) DEFAULT NULL,
  `is_replaceable` tinyint(1) DEFAULT 1,
  `worker_code` varchar(50) DEFAULT NULL,
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
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `event_no` varchar(30) NOT NULL COMMENT 'BE-nnnn خادمي لكل شركة (ems_sequences — نطاق ems_business_events:BE:{company})',
  `event_uuid` char(26) NOT NULL COMMENT 'ULID يُسَكّ عند النشر (مواصفة ADR-15)؛ الردم القديم LEGACY+id',
  `event_key` varchar(80) NOT NULL COMMENT 'domain.entity.action — نفس مفردات عقد §9',
  `category` varchar(20) NOT NULL COMMENT 'التصنيفات السبعة (عقد §9)',
  `source_module` varchar(20) NOT NULL COMMENT 'VARCHAR لا ENUM — إدارة جديدة بلا DDL (درس ENUM الدفتر)',
  `source_ref` varchar(60) DEFAULT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(18,4) DEFAULT NULL,
  `unit` varchar(16) DEFAULT NULL,
  `amount` decimal(16,2) DEFAULT NULL COMMENT 'قيمة الحقيقة إن كانت نقدية — وصفٌ لا قرار مالي',
  `currency` varchar(8) DEFAULT NULL,
  `fx_rate` decimal(20,8) DEFAULT NULL COMMENT 'سعرُ الصرف لحظةَ الحدث (FES-01 §3.1) — NULL أي لا سعرَ لفترته بعد',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'المعادلُ بعملة الأساس = ROUND(amount × fx_rate, 2) — NULL أي بانتظار سعر',
  `project_id` int(11) DEFAULT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `supplier_entity_id` int(11) DEFAULT NULL,
  `customer_entity_id` int(11) DEFAULT NULL,
  `operator_employee_id` int(11) DEFAULT NULL,
  `event_status` enum('recorded','corrected','reversed') NOT NULL DEFAULT 'recorded' COMMENT 'ADR-18: العكسي = نفس الأثر بكميةٍ سالبة',
  `reverses_event_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ADR-18: id الحقيقة المنقوضة',
  `occurred_at` datetime NOT NULL COMMENT 'لحظة الوقوع الفعلي UTC',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `correlation_id` varchar(64) DEFAULT NULL COMMENT 'سلسلة الأثر طرفًا لطرف — المشتق يرث الجذر',
  `idempotency_key` varchar(64) DEFAULT NULL COMMENT 'عطالة المصدر — نفس مفتاح الإسقاط المالي (كتابة مزدوجة متسقة)',
  `schema_version` smallint(5) unsigned DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
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
  CONSTRAINT `fk_be_currency` FOREIGN KEY (`company_id`, `currency`) REFERENCES `fin_currencies` (`company_id`, `code`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ADR-15: الجذر المحايد — سجل الحقائق المؤسسي append-only؛ القناة: EventPublisher حصرًا؛ الدفتر المالي إسقاطه الأول';

-- ── Table: ems_event_consumers ──
CREATE TABLE `ems_event_consumers` (
  `consumer` varchar(64) NOT NULL COMMENT 'اسم المستهلك المسجَّل (finance, analytics, …)',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `cursor_event_id` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'آخر حدثٍ عولج بنجاح/تُجووز — مستقل لكل مستهلك',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`consumer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K4: سجلّ مستهلكي الناقل المؤسسي — Cursor مستقل لكل مستهلك';

-- ── Table: ems_event_dead_letter ──
CREATE TABLE `ems_event_dead_letter` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer` varchar(64) NOT NULL,
  `event_id` bigint(20) unsigned NOT NULL,
  `attempts` int(10) unsigned NOT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `failed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dlq_consumer_event` (`consumer`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K4: الحدث السام يُعزل هنا بعد استنفاد المحاولات — الطابور لا يتجمّد خلفه';

-- ── Table: ems_event_deliveries ──
CREATE TABLE `ems_event_deliveries` (
  `consumer` varchar(64) NOT NULL,
  `event_id` bigint(20) unsigned NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 1,
  `last_error` varchar(500) DEFAULT NULL,
  `next_retry_at` datetime DEFAULT NULL COMMENT 'N-06: موعد المحاولة التالية (تصاعد 2^attempts دقيقة) — NULL = مستحقة الآن',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`consumer`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K4: محاولات تسليمٍ جارية (تُحذف عند النجاح أو تنتقل للرسائل الميتة)';

-- ── Table: ems_job_queue ──
CREATE TABLE `ems_job_queue` (
  `job_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `job_type` varchar(60) NOT NULL COMMENT 'payroll_bind · periodic_cron · bank_recon · batch_loop …',
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `state` enum('queued','processing','done','failed','dead') NOT NULL DEFAULT 'queued',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `next_attempt_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'التصاعد: 1د ثم 5د ثم 25د — بساعة القاعدة',
  `progress_done` int(11) NOT NULL DEFAULT 0,
  `progress_total` int(11) NOT NULL DEFAULT 0,
  `batch_failures` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'NFR-06: فشل دفعة لا يسقط الباقي — يسجَّل هنا ظاهرًا' CHECK (json_valid(`batch_failures`)),
  `last_error` varchar(500) DEFAULT NULL COMMENT 'سجل الفشل الظاهر — لا فشل صامت',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`job_id`),
  KEY `idx_jq_claim` (`state`,`next_attempt_at`),
  KEY `idx_jq_company` (`company_id`,`state`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-24: «قيد المعالجة» ثم إشعار الاكتمال — والصفحة لا تتجمد أبدًا';

-- ── Table: ems_post_idempotency ──
CREATE TABLE `ems_post_idempotency` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `idem_key` char(40) NOT NULL,
  `action_code` varchar(120) NOT NULL DEFAULT '',
  `actor_user_id` int(11) NOT NULL DEFAULT 0,
  `result_ref` varchar(190) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_idem_key` (`idem_key`),
  KEY `idx_post_idem_action` (`action_code`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CS-07 · عطالةُ معالجاتِ POST بمفتاحٍ من محتوى الطلب';

-- ── Table: ems_processed_events ──
CREATE TABLE `ems_processed_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer` varchar(64) NOT NULL COMMENT 'اسم المستهلك (يوافق ems_event_consumers.consumer)',
  `event_uuid` char(36) NOT NULL COMMENT 'معرّف الحدث الكوني المُستهلَك',
  `processed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_processed` (`consumer`,`event_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='عطالة استهلاك موزّعة (عقد قابلية التوزيع) — يُفعَّل عند تعدّد الموزّعات';

-- ── Table: ems_saved_views ──
CREATE TABLE `ems_saved_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `screen` varchar(160) NOT NULL COMMENT 'المسار النسبي للشاشة',
  `view_name` varchar(80) NOT NULL COMMENT 'اسم المنظر كما يراه المستخدم',
  `owner_kind` enum('role','user') NOT NULL DEFAULT 'role',
  `owner_id` int(11) NOT NULL COMMENT 'رقم الدور أو المستخدم بحسب owner_kind',
  `columns_json` longtext DEFAULT NULL COMMENT 'فهارس الأعمدة الظاهرة — NULL = الكل',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_view` (`company_id`,`screen`,`owner_kind`,`owner_id`,`view_name`),
  KEY `ix_screen` (`company_id`,`screen`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ems_sequences ──
CREATE TABLE `ems_sequences` (
  `scope` varchar(120) NOT NULL COMMENT 'نطاق المتتالية، مثال: fin_financial_events:EV:4',
  `next_val` bigint(20) unsigned NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K8: متتاليات ذرّية للترقيم الخادمي (ServerId::nextNo)';

-- ── Table: ems_sessions ──
CREATE TABLE `ems_sessions` (
  `sess_id` varchar(128) NOT NULL,
  `sess_data` mediumblob DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sess_id`),
  KEY `idx_sess_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='NFR-13: قراءة/كتابة صف لا قفل ملف — والكنس بالدورية';

-- ── Table: ems_state_transitions ──
CREATE TABLE `ems_state_transitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workflow` varchar(64) NOT NULL COMMENT 'اسم تعريف سير العمل',
  `entity_table` varchar(64) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `from_state` varchar(40) NOT NULL COMMENT 'أكواد لاتينية (ADR-08 — الجديد فقط)',
  `to_state` varchar(40) NOT NULL,
  `action` varchar(64) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `actor_role` varchar(10) NOT NULL COMMENT 'الدور الفعّال لحظة الانتقال (من جسر K6)',
  `actor_source` varchar(20) NOT NULL DEFAULT 'role' COMMENT 'role|position — مصدر الدور الفعّال',
  `note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_st_entity` (`entity_table`,`entity_id`),
  KEY `idx_st_workflow_time` (`workflow`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K7: سجل انتقالات محرك الحالات — append-only، لا يُعدَّل ولا يُحذف منه';

-- ── Table: entity_licenses ──
CREATE TABLE `entity_licenses` (
  `lic_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int(10) unsigned NOT NULL,
  `lic_type` varchar(80) NOT NULL,
  `issuer` varchar(120) DEFAULT NULL,
  `lic_no` varchar(80) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `alert_days` int(10) unsigned NOT NULL DEFAULT 30,
  `file_ref` varchar(160) DEFAULT NULL,
  `state` enum('active','expired','renewed','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`lic_id`),
  KEY `ix_el_expiry` (`expiry_date`,`state`),
  KEY `fk_el_entity` (`entity_id`),
  CONSTRAINT `fk_el_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §5: التراخيص بتواريخ انتهائها وتنبيهاتها';

-- ── Table: entity_ownership ──
CREATE TABLE `entity_ownership` (
  `own_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `owner_type` enum('person','entity') NOT NULL,
  `owner_id` int(11) NOT NULL COMMENT 'users.id للشخص أو legal_entities.entity_id للكيان',
  `owned_entity_id` int(10) unsigned NOT NULL,
  `percent` decimal(5,2) NOT NULL,
  `ownership_kind` enum('shares','stocks','partnership') NOT NULL DEFAULT 'shares',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `doc_ref` varchar(120) DEFAULT NULL,
  `recorded_percent` decimal(5,2) DEFAULT NULL,
  `corrected_percent` decimal(5,2) DEFAULT NULL,
  `correction_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`own_id`),
  KEY `ix_eo_owned` (`owned_entity_id`,`valid_from`),
  KEY `ix_eo_owner` (`owner_type`,`owner_id`),
  CONSTRAINT `fk_eo_owned` FOREIGN KEY (`owned_entity_id`) REFERENCES `legal_entities` (`entity_id`),
  CONSTRAINT `ck_eo_pct` CHECK (`percent` > 0 and `percent` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: entity_roles ──
CREATE TABLE `entity_roles` (
  `role_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int(10) unsigned NOT NULL,
  `role` enum('holding','operating','project','client','supplier','financier','government') NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `doc_ref` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_er_entity_role` (`entity_id`,`role`,`valid_from`),
  KEY `ix_er_role` (`role`,`valid_to`),
  CONSTRAINT `fk_er_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §2-②: صفات الكيان جدول علاقة مؤرَّخ — لا حقل نصي';

-- ── Table: equipment_documents ──
CREATE TABLE `equipment_documents` (
  `doc_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `subject_type` enum('equipment','operator','supplier') NOT NULL DEFAULT 'equipment' COMMENT 'محورُ الوثيقة — والموردُ محورٌ ثالثٌ (M-19) لا جدولٌ ثانٍ',
  `subject_id` int(10) unsigned NOT NULL COMMENT 'equipments.id أو employees.id بحسب subject_type — مرجعٌ مرن',
  `doc_type` enum('استمارة','تأمين','فحص دوري','رخصة قيادة','رخصة تشغيل','تصريح','هوية','جواز سفر','عقد عمل','سجل تجاري','شهادة ضريبية','شهادة بنكية','أخرى') NOT NULL COMMENT 'UX-10 §8.1 + وثائقُ الأفراد + **وثائقُ المورد النظامية** (UX-05 §5.1-①)',
  `doc_no` varchar(100) DEFAULT NULL,
  `issuer` varchar(255) DEFAULT NULL COMMENT 'جهةُ الإصدار',
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL COMMENT 'NULL = وثيقةٌ لا تنتهي (نادر — تُعلَن)',
  `alert_days` smallint(5) unsigned NOT NULL DEFAULT 30 COMMENT 'التنبيهُ قبل الانتهاء بهذه المدة (§8.1)',
  `file_ref` varchar(255) DEFAULT NULL COMMENT 'مسارُ المرفق',
  `status` enum('سارية','منتهية','قيد التجديد','ملغاة') NOT NULL DEFAULT 'سارية' COMMENT 'حالةٌ يديرها البشر — والانتهاءُ الفعلي يُحسب من expiry_date لا منها',
  `note` varchar(200) DEFAULT NULL,
  `migrated_from` varchar(40) DEFAULT NULL COMMENT 'نسبُ الترحيل: equipments.license / equipment_operators.license — NULL للجديد',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`doc_id`),
  UNIQUE KEY `uq_doc` (`company_id`,`subject_type`,`subject_id`,`doc_type`,`doc_no`),
  KEY `ix_expiry` (`company_id`,`expiry_date`) COMMENT 'فهرسُ التنبيه الآلي (§8.1)',
  KEY `ix_subject` (`company_id`,`subject_type`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='UX-10 §8.1 — وثائقُ المعدة والمشغّل بتواريخ انتهائها وتنبيهها';

-- ── Table: equipment_drivers ──
CREATE TABLE `equipment_drivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `start_date` varchar(50) NOT NULL,
  `end_date` varchar(50) DEFAULT NULL,
  `shift_type` enum('D','N','B') NOT NULL DEFAULT 'B',
  `status` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_equipment_drivers_equipment` (`equipment_id`),
  KEY `fk_equipment_drivers_driver` (`employee_id`),
  CONSTRAINT `fk_equipment_drivers_driver` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_drivers_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: equipment_operators ──
CREATE TABLE `equipment_operators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `license_type` varchar(100) DEFAULT NULL,
  `license_grade` varchar(40) DEFAULT NULL,
  `license_issuer` varchar(255) DEFAULT NULL,
  `license_issue_date` date DEFAULT NULL,
  `license_expiry_date` date DEFAULT NULL,
  `license_photo` varchar(255) DEFAULT NULL,
  `operating_categories` mediumtext DEFAULT NULL,
  `driving_authorizations` varchar(255) DEFAULT NULL,
  `medical_report_path` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operator_employee` (`employee_id`),
  KEY `idx_operator_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: equipment_ownership_registry ──
CREATE TABLE `equipment_ownership_registry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `actual_owner_name` varchar(255) DEFAULT NULL,
  `owner_type` varchar(60) DEFAULT NULL,
  `owner_phone` varchar(60) DEFAULT NULL,
  `owner_supplier_relation` varchar(120) DEFAULT NULL,
  `operational_source` enum('financed','supplier_external') DEFAULT NULL COMMENT 'N-19: قيمتان لا ثالثة — واردة عبر التمويل (لنا) أو عبر مورد خارجي؛ NULL = غير محددة (حالة نقص تُغلق)',
  `purchase_value` decimal(18,2) DEFAULT NULL COMMENT 'قيمة الشراء — أشد الحقول سرية',
  `purchase_currency` varchar(8) DEFAULT NULL,
  `migrated_from` varchar(40) NOT NULL DEFAULT 'equipments',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_decided_by` int(11) DEFAULT NULL COMMENT 'N-19: قرار الإقفال لكل معدة',
  `source_decided_at` datetime DEFAULT NULL,
  `source_decision_note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eor_equipment` (`company_id`,`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-21: المجال المقيَّد لملكية المعدات — لا يُستعلم منه إلا عبر OwnershipDomainGuard';

-- ── Table: equipments ──
CREATE TABLE `equipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'منشئ المعدة',
  `created_at` datetime DEFAULT NULL COMMENT 'تاريخ إضافة المعدة',
  `suppliers` varchar(10) NOT NULL,
  `code` varchar(100) NOT NULL,
  `type` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL COMMENT 'رقم المعدة/الرقم التسلسلي',
  `chassis_number` varchar(100) DEFAULT NULL COMMENT 'رقم الهيكل/الهيكل الأساسي',
  `machine_number` varchar(100) DEFAULT NULL COMMENT 'رقم الماكينة أو المحرك',
  `manufacturer` varchar(100) DEFAULT NULL COMMENT 'الماركة/الشركة المصنعة',
  `model` varchar(100) DEFAULT NULL COMMENT 'الموديل/الطراز',
  `model_id` int(11) DEFAULT NULL,
  `manufacturing_year` int(11) DEFAULT NULL COMMENT 'سنة الصنع',
  `import_year` int(11) DEFAULT NULL COMMENT 'سنة الاستيراد/البدء',
  `equipment_condition` varchar(50) DEFAULT 'في حالة جيدة' COMMENT 'حالة المعدة',
  `operating_hours` int(11) DEFAULT NULL COMMENT 'ساعات التشغيل',
  `engine_condition` varchar(50) DEFAULT 'جيدة' COMMENT 'حالة المحرك',
  `tires_condition` varchar(50) DEFAULT 'N/A' COMMENT 'حالة الإطارات',
  `actual_owner_name` varchar(200) DEFAULT NULL COMMENT 'اسم المالك الفعلي',
  `owner_type` varchar(50) DEFAULT NULL COMMENT 'نوع المالك',
  `owner_phone` varchar(50) DEFAULT NULL COMMENT 'رقم هاتف المالك',
  `owner_supplier_relation` varchar(100) DEFAULT NULL COMMENT 'علاقة المالك بالمورد',
  `license_number` varchar(100) DEFAULT NULL COMMENT 'رقم الترخيص/التسجيل',
  `license_authority` varchar(100) DEFAULT NULL COMMENT 'جهة الترخيص',
  `document_type` varchar(100) DEFAULT NULL COMMENT 'نوع الوثيقة',
  `license_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الترخيص',
  `inspection_certificate_number` varchar(100) DEFAULT NULL COMMENT 'رقم شهادة الفحص',
  `last_inspection_date` date DEFAULT NULL COMMENT 'تاريخ آخر فحص',
  `current_location` varchar(255) DEFAULT NULL COMMENT 'الموقع الحالي',
  `site_supervisor_name` varchar(200) DEFAULT NULL COMMENT 'اسم المهندس أو المشرف في الموقع',
  `site_supervisor_contact` varchar(200) DEFAULT NULL COMMENT 'بيانات الاتصال بالمشرف في الموقع',
  `availability_state` varchar(20) NOT NULL DEFAULT 'متوفرة' COMMENT 'التوفر: متوفرة أو غير متوفرة',
  `availability_status` varchar(50) DEFAULT 'متاحة للعمل' COMMENT 'حالة التوفر',
  `estimated_value` decimal(15,2) DEFAULT NULL COMMENT 'القيمة المقدرة للمعدة',
  `daily_rental_price` decimal(10,2) DEFAULT NULL COMMENT 'سعر التأجير اليومي',
  `monthly_rental_price` decimal(10,2) DEFAULT NULL COMMENT 'سعر التأجير الشهري',
  `insurance_status` varchar(50) DEFAULT NULL COMMENT 'التأمين/الضمان',
  `general_notes` mediumtext DEFAULT NULL COMMENT 'ملاحظات عامة',
  `last_maintenance_date` date DEFAULT NULL COMMENT 'تاريخ آخر صيانة',
  `status` tinyint(1) DEFAULT 1,
  `operating_category` varchar(50) DEFAULT NULL,
  `origin_country` varchar(100) DEFAULT NULL,
  `engine_no` varchar(100) DEFAULT NULL,
  `plate_no` varchar(50) DEFAULT NULL,
  `capacity` decimal(12,2) DEFAULT NULL,
  `capacity_uom` varchar(20) DEFAULT NULL,
  `dimensions` varchar(200) DEFAULT NULL,
  `source_type` varchar(30) DEFAULT NULL,
  `entry_date` date DEFAULT NULL,
  `acquisition_cost` decimal(15,2) DEFAULT NULL,
  `acquisition_currency` varchar(10) DEFAULT NULL,
  `opening_meter` decimal(12,2) DEFAULT NULL,
  `meter_uom` varchar(20) DEFAULT 'ساعات',
  `meter_source` varchar(30) DEFAULT NULL,
  `card_state` varchar(20) NOT NULL DEFAULT 'active',
  `card_approved_by` int(11) DEFAULT NULL,
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form` varchar(20) NOT NULL,
  `type` varchar(100) NOT NULL,
  `status` enum('active','inactive','','') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: evaluations ──
CREATE TABLE `evaluations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `capacity_id` int(10) unsigned NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `self_scores_json` text DEFAULT NULL,
  `self_closed_at` datetime DEFAULT NULL,
  `mgr_scores_json` text DEFAULT NULL,
  `mgr_by` int(11) DEFAULT NULL,
  `mgr_comment` varchar(500) DEFAULT NULL COMMENT 'إلزاميٌّ عند فارقٍ ≥ درجتين',
  `discussion_notes` text DEFAULT NULL,
  `final_score` decimal(5,2) DEFAULT NULL,
  `state` enum('SelfDraft','SelfClosed','MgrDraft','Discussed','Approved') NOT NULL DEFAULT 'SelfDraft',
  `version` int(11) NOT NULL DEFAULT 1,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eval` (`capacity_id`,`period_from`,`period_to`),
  CONSTRAINT `fk_eval_capacity` FOREIGN KEY (`capacity_id`) REFERENCES `user_capacities` (`id`),
  CONSTRAINT `ck_eval_approved` CHECK (`state` <> _utf8mb4'Approved' or `approved_by` is not null and `approved_at` is not null and `final_score` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: event_consumers ──
CREATE TABLE `event_consumers` (
  `c_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_name` varchar(120) NOT NULL,
  `consumer_class` varchar(160) NOT NULL,
  `consumer_method` varchar(120) DEFAULT NULL,
  `produces` enum('write','notify','dashboard_refresh') NOT NULL DEFAULT 'write' COMMENT 'مستهلكٌ لا يُنتج أثرًا مرئيًّا أو مسجَّلًا يُراجَع',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`c_id`),
  UNIQUE KEY `uq_ec` (`event_name`,`consumer_class`),
  KEY `ix_ec_event` (`event_name`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: exception_approvals ──
CREATE TABLE `exception_approvals` (
  `app_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `req_id` int(10) unsigned NOT NULL,
  `approver_person_id` int(11) NOT NULL,
  `approver_role` varchar(60) NOT NULL COMMENT 'الدور — لا دور يتكرر في طلب واحد (تحرسه الخدمة 409)',
  `auth_id` int(10) unsigned DEFAULT NULL COMMENT 'مرجع التفويض (LEG-01)',
  `seq_no` tinyint(3) unsigned NOT NULL,
  `decision` enum('approve','reject') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`app_id`),
  UNIQUE KEY `uq_exa_seq` (`req_id`,`seq_no`),
  CONSTRAINT `fk_exa_req` FOREIGN KEY (`req_id`) REFERENCES `exception_requests` (`req_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §7: موافقات الاستثناء بالتسلسل — approver ≠ requester ولا دور مكرر';

-- ── Table: exception_requests ──
CREATE TABLE `exception_requests` (
  `req_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `guard_code` varchar(64) NOT NULL,
  `requester_person_id` int(11) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `risk_level` enum('normal','operational','financial','high','legal_forbidden') NOT NULL COMMENT 'محسوب — يُرفع لا يُخفض إلا بقرار',
  `scope_type` enum('person','operation','equipment','contract','period') NOT NULL,
  `scope_id` varchar(64) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL COMMENT 'إلزامي — لا استثناء مفتوح المدة',
  `one_time` tinyint(1) NOT NULL DEFAULT 0,
  `documents_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`documents_json`)),
  `expected_impact` varchar(255) DEFAULT NULL,
  `state` enum('Draft','Pending','Approved','Rejected','Active','Expired','Revoked') NOT NULL DEFAULT 'Pending',
  `usage_count` int(10) unsigned NOT NULL DEFAULT 0,
  `closed_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`req_id`),
  KEY `ix_exr_guard` (`guard_code`,`state`,`valid_to`),
  KEY `ix_exr_company` (`company_id`,`state`),
  CONSTRAINT `fk_exr_guard` FOREIGN KEY (`guard_code`) REFERENCES `guard_policies` (`guard_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §7: طلبات الاستثناء — بمدة ونطاق وسبب ومستندات، ولا استثناء عام';

-- ── Table: exception_usages ──
CREATE TABLE `exception_usages` (
  `usage_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `req_id` int(10) unsigned NOT NULL,
  `operation_ref` varchar(120) NOT NULL,
  `person_id` int(11) NOT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usage_id`),
  KEY `ix_exu_req` (`req_id`,`at`),
  CONSTRAINT `fk_exu_req` FOREIGN KEY (`req_id`) REFERENCES `exception_requests` (`req_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §7-⑤: كل عبور باستثناء يُسجَّل — Insert-only';

-- ── Table: exec_approvals ──
CREATE TABLE `exec_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `request_no` varchar(40) NOT NULL COMMENT 'رقم الطلب',
  `received_date` date DEFAULT NULL COMMENT 'تاريخ الورود',
  `doc_type` varchar(80) DEFAULT NULL COMMENT 'نوع المستند',
  `document` varchar(255) DEFAULT NULL COMMENT 'المستند',
  `requesting_dept` varchar(80) DEFAULT NULL COMMENT 'الإدارة الطالبة',
  `raise_reason` varchar(255) DEFAULT NULL COMMENT 'سبب الرفع للأعلى',
  `amount` decimal(18,2) DEFAULT NULL COMMENT 'القيمة',
  `currency` varchar(8) DEFAULT NULL,
  `dept_cap` decimal(18,2) DEFAULT NULL COMMENT 'سقف الإدارة لحظة الرفع',
  `overage` decimal(18,2) DEFAULT NULL COMMENT 'التجاوز',
  `prior_approvers` varchar(255) DEFAULT NULL COMMENT 'المعتمِدون قبلي',
  `deadline` varchar(60) DEFAULT NULL COMMENT 'المهلة المعلنة',
  `decision` varchar(30) DEFAULT NULL COMMENT 'قراري: اعتماد/اعتماد بشرط/رد/تأجيل',
  `decision_reason` varchar(300) DEFAULT NULL COMMENT 'سبب القرار أو الشرط',
  `decision_date` date DEFAULT NULL,
  `approver_name` varchar(120) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `authority_ref` varchar(120) DEFAULT NULL COMMENT 'مرجع التفويض أو سلطة أصلية',
  `status` varchar(40) NOT NULL DEFAULT 'قيد المراجعة',
  `source_request_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ربط الطلب الحقيقي requests.id',
  `source_kind` varchar(30) DEFAULT NULL COMMENT 'منشأ الصف: يدوي/رفع آلي/طلب',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` datetime DEFAULT NULL COMMENT 'لحظةُ الاعتماد — وبها يُقاس زمنُ الدورة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exec_appr_no` (`company_id`,`request_no`),
  KEY `ix_exap_live` (`company_id`,`status`,`received_date`),
  KEY `ix_exap_src` (`source_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-00 §8-2: الاعتماد الأعلى — الجدول الأصلي لشاشة ceo_approvals';

-- ── Table: exec_assignments ──
CREATE TABLE `exec_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `assignment_no` varchar(40) NOT NULL,
  `subject_user_id` int(10) unsigned NOT NULL COMMENT 'المكلَّف',
  `subject_name` varchar(160) NOT NULL DEFAULT '',
  `role_id` int(10) unsigned NOT NULL COMMENT 'المسمّى المكلَّفُ به',
  `role_name` varchar(120) NOT NULL DEFAULT '',
  `assignment_kind` enum('leadership','oversight','other') NOT NULL DEFAULT 'leadership' COMMENT 'قياديٌّ أو رقابيٌّ — وما عداهما لا يحتاج موافقةَ الرئيس',
  `scope_note` varchar(300) NOT NULL DEFAULT '',
  `requested_by` int(10) unsigned NOT NULL,
  `requested_at` datetime NOT NULL,
  `conflict_state` enum('clean','conflict','waived') NOT NULL DEFAULT 'clean',
  `conflict_detail` varchar(600) NOT NULL DEFAULT '',
  `checked_at` datetime DEFAULT NULL,
  `state` enum('draft','blocked','presented','approved','rejected','revoked') NOT NULL DEFAULT 'draft',
  `decided_by` int(10) unsigned DEFAULT NULL COMMENT 'الرئيسُ التنفيذيُّ حصرًا',
  `decided_at` datetime DEFAULT NULL,
  `decision_reason` varchar(400) NOT NULL DEFAULT '',
  `authority_ref` varchar(120) NOT NULL DEFAULT '' COMMENT 'مرجعُ الموافقةِ الموثَّق',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoke_reason` varchar(400) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_no` (`company_id`,`assignment_no`),
  KEY `ix_live` (`company_id`,`subject_user_id`,`role_id`,`state`),
  KEY `ix_state` (`company_id`,`state`,`requested_at`),
  KEY `ix_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PROP-01 CEO-Y0121/0122 — سجلُّ موافقاتِ التكليفِ ولا سريانَ قبلَه';

-- ── Table: exec_audit_reports ──
CREATE TABLE `exec_audit_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `report_no` varchar(40) NOT NULL,
  `title` varchar(300) NOT NULL,
  `period_label` varchar(60) NOT NULL DEFAULT '',
  `scope_label` varchar(300) NOT NULL DEFAULT '',
  `overall_opinion` varchar(300) NOT NULL DEFAULT '',
  `findings_total` int(10) unsigned NOT NULL DEFAULT 0,
  `findings_critical` int(10) unsigned NOT NULL DEFAULT 0,
  `closure_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `overdue_escalated` int(10) unsigned NOT NULL DEFAULT 0,
  `issued_by` int(10) unsigned NOT NULL COMMENT 'المراجعُ الداخليُّ المستقل',
  `issued_at` datetime NOT NULL,
  `delivery_path` enum('direct','via_finance','via_governance','via_auditee') NOT NULL DEFAULT 'direct' COMMENT 'CEO-Y0119 — direct وحدَها مقبولة، وما عداها خرقٌ يُكشف',
  `received_at` datetime DEFAULT NULL COMMENT 'وقتُ وصولِه صندوقَ الرئيس',
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rep` (`company_id`,`report_no`),
  KEY `ix_path` (`delivery_path`),
  KEY `ix_time` (`company_id`,`issued_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PROP-01 CEO-Y0119 — تقاريرُ المراجعةِ تصل الرئيسَ غيرَ مفلترة';

-- ── Table: exec_board_snapshots ──
CREATE TABLE `exec_board_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `period` varchar(20) NOT NULL COMMENT 'الفترة',
  `active_contracts` varchar(40) DEFAULT NULL COMMENT 'العقود النافذة',
  `portfolio_value` varchar(60) DEFAULT NULL COMMENT 'قيمة المحفظة',
  `recognized_revenue` varchar(60) DEFAULT NULL COMMENT 'الإيراد المعترف',
  `collection` varchar(60) DEFAULT NULL COMMENT 'التحصيل',
  `overdue_receivables` varchar(60) DEFAULT NULL COMMENT 'الذمم المتأخرة',
  `expected_cashflow` varchar(60) DEFAULT NULL COMMENT 'التدفق المتوقع',
  `financing_commitments` varchar(60) DEFAULT NULL COMMENT 'التزامات التمويل',
  `working_equipment` varchar(40) DEFAULT NULL COMMENT 'المعدات العاملة',
  `readiness_pct` varchar(20) DEFAULT NULL COMMENT 'نسبة الجاهزية',
  `approved_units` varchar(40) DEFAULT NULL COMMENT 'الوحدات المعتمدة',
  `margin_pct` varchar(20) DEFAULT NULL COMMENT 'الهامش',
  `open_risks` varchar(20) DEFAULT NULL COMMENT 'المخاطر المفتوحة',
  `pending_approvals` varchar(20) DEFAULT NULL COMMENT 'الاعتمادات المعلَّقة',
  `last_updated` varchar(30) DEFAULT NULL COMMENT 'آخر تحديث',
  `status` varchar(40) NOT NULL DEFAULT 'معتمد',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_board_period` (`company_id`,`period`,`is_seed`),
  KEY `ix_exbs_live` (`company_id`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-00 §8-2: لقطات المؤشرات العليا — الجدول الأصلي لشاشة ceo_board';

-- ── Table: exec_contract_signings ──
CREATE TABLE `exec_contract_signings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_no` varchar(60) NOT NULL COMMENT 'رقم العقد',
  `contract_kind` varchar(80) DEFAULT NULL COMMENT 'نوع العقد',
  `other_party` varchar(190) DEFAULT NULL COMMENT 'الطرف الآخر',
  `party_type` varchar(30) DEFAULT NULL COMMENT 'عميل/مورد/موظف/ممول',
  `amount` decimal(18,2) DEFAULT NULL,
  `currency` varchar(8) DEFAULT NULL,
  `duration` varchar(40) DEFAULT NULL COMMENT 'المدة',
  `work_model` varchar(40) DEFAULT NULL COMMENT 'نموذج العمل',
  `contract_unit` varchar(40) DEFAULT NULL COMMENT 'وحدة التعاقد',
  `units_count` varchar(80) DEFAULT NULL COMMENT 'عدد الوحدات',
  `bond_required` varchar(10) DEFAULT NULL COMMENT 'الكفالة المطلوبة',
  `bond_value` varchar(60) DEFAULT NULL COMMENT 'قيمة الكفالة',
  `legal_review` varchar(190) DEFAULT NULL COMMENT 'المراجعة القانونية',
  `financial_review` varchar(190) DEFAULT NULL COMMENT 'المراجعة المالية',
  `signed_by_us` varchar(120) DEFAULT NULL COMMENT 'الموقّع عنّا',
  `signer_capacity` varchar(80) DEFAULT NULL COMMENT 'صفة الموقّع عنّا',
  `authority_ref` varchar(120) DEFAULT NULL COMMENT 'مرجع سلطته — BR-CEO-01',
  `signing_date` date DEFAULT NULL,
  `other_signer` varchar(120) DEFAULT NULL COMMENT 'الموقّع عن الطرف الآخر',
  `other_signer_capacity` varchar(80) DEFAULT NULL COMMENT 'صفته',
  `other_authority_doc` varchar(120) DEFAULT NULL COMMENT 'مستند تخويله',
  `registry_recorded` varchar(10) NOT NULL DEFAULT 'لا' COMMENT 'سُجّل في السجل الموحَّد؟',
  `status` varchar(40) NOT NULL DEFAULT 'قيد المراجعة',
  `contract_id` int(11) DEFAULT NULL COMMENT 'ربط العقد الحقيقي contracts.id',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approver_name` varchar(120) DEFAULT NULL COMMENT 'من اعتمده وبأي صفة',
  `approver_authority_ref` varchar(120) DEFAULT NULL COMMENT 'سندُ صلاحيةِ المعتمِد — غيرُ سندِ المُوقِّع',
  `approved_at` datetime DEFAULT NULL COMMENT 'لحظةُ الاعتماد',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exec_sign_no` (`company_id`,`contract_no`),
  KEY `ix_excs_live` (`company_id`,`status`,`signing_date`),
  KEY `ix_excs_contract` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-00 §8-2: سجل التوقيع — الجدول الأصلي لشاشة ceo_contracts';

-- ── Table: exec_decisions ──
CREATE TABLE `exec_decisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `decision_no` varchar(40) NOT NULL COMMENT 'رقم القرار',
  `raised_date` date DEFAULT NULL COMMENT 'تاريخ الرفع',
  `raising_dept` varchar(80) DEFAULT NULL COMMENT 'الجهة الرافعة',
  `issue_type` varchar(60) DEFAULT NULL COMMENT 'نوع القضية',
  `issue_desc` varchar(300) DEFAULT NULL COMMENT 'وصف القضية',
  `est_impact` varchar(60) DEFAULT NULL COMMENT 'الأثر المقدَّر',
  `currency` varchar(8) DEFAULT NULL,
  `options_text` varchar(400) DEFAULT NULL COMMENT 'الخيارات المطروحة',
  `chosen_option` varchar(190) DEFAULT NULL COMMENT 'الخيار المختار',
  `choice_reason` varchar(300) DEFAULT NULL COMMENT 'مبرر الاختيار',
  `assigned_dept` varchar(80) DEFAULT NULL COMMENT 'الجهة المكلَّفة بالتنفيذ',
  `exec_deadline` varchar(40) DEFAULT NULL COMMENT 'مهلة التنفيذ — BR-CEO-04',
  `followup_date` date DEFAULT NULL COMMENT 'تاريخ المتابعة',
  `approver_name` varchar(120) DEFAULT NULL,
  `decision_date` date DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'قيد الحسم',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `authority_ref` varchar(120) DEFAULT NULL COMMENT 'سندُ صلاحيةِ معتمِدِ القرار',
  `parent_ref` varchar(64) DEFAULT NULL COMMENT 'المستندُ الذي تولَّد عنه — خيطُ التتبع',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exec_decision_no` (`company_id`,`decision_no`),
  KEY `ix_exdc_live` (`company_id`,`status`,`raised_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-00 §8-2: سجل القرارات العليا — الجدول الأصلي لشاشة ceo_risk';

-- ── Table: exec_dept_caps ──
CREATE TABLE `exec_dept_caps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك',
  `dept_name` varchar(80) NOT NULL COMMENT 'اسم الإدارة كما يرد في الطلبات',
  `cap_amount` decimal(18,2) NOT NULL COMMENT 'السقف النقدي — ما فوقه يُرفع آليًّا',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `effective_from` date NOT NULL COMMENT 'بداية السريان',
  `effective_to` date DEFAULT NULL COMMENT 'نهاية السريان (NULL = مفتوح)',
  `authority_ref` varchar(120) DEFAULT NULL COMMENT 'سند الاعتماد — قرار الموازنة',
  `note` varchar(255) DEFAULT NULL,
  `is_seed` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_cap` (`company_id`,`dept_name`,`currency`,`effective_from`),
  KEY `ix_cap_live` (`company_id`,`effective_from`,`effective_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-00 §5-1: سقوف الإدارات — أساس الرفع الآلي BR-CEO-05';

-- ── Table: exec_matter_opinions ──
CREATE TABLE `exec_matter_opinions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `matter_ref` varchar(60) NOT NULL COMMENT 'مرجعُ المسألةِ في exec_decisions',
  `opinion_of` enum('finance','governance','risk','internal_audit') NOT NULL,
  `has_opinion` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = لا رأيَ لها في هذه المسألة',
  `opinion_text` varchar(800) NOT NULL DEFAULT '',
  `given_by` int(10) unsigned DEFAULT NULL,
  `given_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_op` (`company_id`,`matter_ref`,`opinion_of`),
  KEY `ix_matter` (`company_id`,`matter_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PROP-01 CEO-Y0123 — آراءُ الجهاتِ الأربعِ على المسألةِ المحجوزة';

-- ── Table: exec_project_charters ──
CREATE TABLE `exec_project_charters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `decision_no` varchar(40) NOT NULL COMMENT 'رقم القرار',
  `project_name` varchar(190) DEFAULT NULL,
  `client` varchar(190) DEFAULT NULL COMMENT 'العميل',
  `contract_ref` varchar(60) DEFAULT NULL COMMENT 'العقد',
  `sites_text` varchar(255) DEFAULT NULL COMMENT 'الموقع أو المواقع',
  `work_model` varchar(40) DEFAULT NULL,
  `work_unit` varchar(40) DEFAULT NULL COMMENT 'وحدة العمل',
  `contracted_qty` varchar(80) DEFAULT NULL COMMENT 'الكمية المتعاقدة',
  `planned_start` date DEFAULT NULL COMMENT 'تاريخ البدء المخطط',
  `duration` varchar(40) DEFAULT NULL,
  `equipment_needed` varchar(190) DEFAULT NULL COMMENT 'المعدات المطلوبة',
  `operators_needed` varchar(80) DEFAULT NULL COMMENT 'المشغّلون المطلوبون',
  `equipment_source` varchar(80) DEFAULT NULL COMMENT 'مصدر المعدات',
  `financing_need` varchar(190) DEFAULT NULL COMMENT 'احتياج التمويل',
  `cost_center` varchar(60) DEFAULT NULL COMMENT 'مركز التكلفة',
  `site_manager` varchar(120) DEFAULT NULL COMMENT 'مدير الموقع المعيَّن',
  `manager_powers` varchar(255) DEFAULT NULL COMMENT 'صلاحياته',
  `cert_operations` varchar(190) DEFAULT NULL COMMENT 'إفادة التشغيل',
  `cert_sales` varchar(190) DEFAULT NULL COMMENT 'إفادة المبيعات',
  `cert_workforce` varchar(190) DEFAULT NULL COMMENT 'إفادة القوى',
  `cert_finance` varchar(190) DEFAULT NULL COMMENT 'إفادة المالية',
  `cert_fleet` varchar(190) DEFAULT NULL COMMENT 'إفادة الأسطول',
  `cert_financing` varchar(190) DEFAULT NULL COMMENT 'إفادة التمويل',
  `approver_name` varchar(120) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approval_date` varchar(80) DEFAULT NULL COMMENT 'تاريخ الاعتماد أو حالة التأجيل',
  `status` varchar(40) NOT NULL DEFAULT 'قيد الإفادات',
  `project_id` int(11) DEFAULT NULL COMMENT 'المشروع المولَّد project.id (الأثر الخماسي)',
  `cost_center_id` int(11) DEFAULT NULL COMMENT 'مركز التكلفة المولَّد fin_cost_centers.id',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `authority_ref` varchar(120) DEFAULT NULL COMMENT 'سندُ صلاحيةِ معتمِدِ القرار',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exec_charter_no` (`company_id`,`decision_no`),
  KEY `ix_expc_live` (`company_id`,`status`,`planned_start`),
  KEY `ix_expc_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-00 §8-2: قرار فتح المشروع — الجدول الأصلي لشاشة project_charter';

-- ── Table: failure_codes ──
CREATE TABLE `failure_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_type` tinyint(1) NOT NULL COMMENT '1=حفار, 2=قلاب, 3=خرامة',
  `event_type_code` varchar(10) NOT NULL COMMENT 'كود نوع الحدث: EQF,MNT,DEP,CST,MST,HRF,MKF',
  `event_type_name` varchar(100) NOT NULL COMMENT 'اسم نوع الحدث بالعربي',
  `main_category_code` varchar(10) NOT NULL COMMENT 'كود الفئة الرئيسية: MEC,HYD,ELE,COL...',
  `main_category_name` varchar(100) NOT NULL COMMENT 'اسم الفئة الرئيسية',
  `sub_category` varchar(100) NOT NULL COMMENT 'الفئة الفرعية (الجزء المعطل)',
  `failure_detail` varchar(200) NOT NULL COMMENT 'تفصيل العطل',
  `full_code` varchar(30) NOT NULL COMMENT 'الكود الكامل مثل EX-EQF-MEC-01-01',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_equipment_type` (`equipment_type`),
  KEY `idx_event_type` (`equipment_type`,`event_type_code`),
  KEY `idx_main_cat` (`equipment_type`,`event_type_code`,`main_category_code`),
  KEY `idx_sub_cat` (`equipment_type`,`event_type_code`,`main_category_code`,`sub_category`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تصنيفات أعطال المعدات - مرجع موحد';

-- ── Table: fin_acc_specializations ──
CREATE TABLE `fin_acc_specializations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code` varchar(8) NOT NULL COMMENT 'ACC-01..ACC-10',
  `name_ar` varchar(120) NOT NULL,
  `name_en` varchar(160) NOT NULL DEFAULT '',
  `accounts` varchar(255) NOT NULL DEFAULT '' COMMENT 'نطاقُ الحساباتِ من دليلِ الحسابات',
  `scope` varchar(255) NOT NULL DEFAULT '' COMMENT 'نطاقُ المسؤولية',
  `dims` varchar(64) NOT NULL DEFAULT '' COMMENT 'الأبعادُ الإلزامية D1..D9',
  `limit_rule` varchar(300) NOT NULL DEFAULT '' COMMENT 'حدُّه — ما لا يملكه',
  `doc_ref` varchar(24) NOT NULL DEFAULT '' COMMENT 'معرّفُ المتطلبِ الذريِّ المصدر',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_spec` (`company_id`,`code`),
  KEY `ix_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-ACC-01 §4-1 — التخصصاتُ المحاسبيةُ العشرة';

-- ── Table: fin_accountants ──
CREATE TABLE `fin_accountants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL COMMENT 'employees.id (مرجع مرن)',
  `admin_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury') NOT NULL COMMENT 'الإدارة المتبوعة إداريّاً (المصدر التشغيلي)',
  `finance_unit_id` int(11) NOT NULL COMMENT 'fin_units.id (الوحدة المتبوعة فنيّاً)',
  `specialization` varchar(80) DEFAULT NULL,
  `spec_code` varchar(8) NOT NULL DEFAULT '' COMMENT 'ACC-01..ACC-10 — FACC-0001',
  `scope_note` varchar(200) NOT NULL DEFAULT '' COMMENT 'نطاقُ المحاسبِ المعلَن داخلَ تخصصِه',
  `review_limit_usd` decimal(14,2) DEFAULT NULL COMMENT 'حدّ المراجعة الأوّلية (لا الاعتماد المالي)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_acct` (`company_id`,`employee_id`,`admin_module`),
  KEY `ix_fin_acct_module` (`company_id`,`admin_module`),
  KEY `ix_fin_acct_deleted` (`is_deleted`),
  KEY `fk_fin_acct_unit` (`finance_unit_id`),
  KEY `ix_spec_code` (`company_id`,`spec_code`,`active`),
  CONSTRAINT `fk_fin_acct_unit` FOREIGN KEY (`finance_unit_id`) REFERENCES `fin_units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_approval_chain ──
CREATE TABLE `fin_approval_chain` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `source_kind` varchar(40) NOT NULL,
  `source_ref` varchar(120) NOT NULL,
  `apr_code` varchar(8) NOT NULL,
  `decision` enum('approved','rejected','escalated') NOT NULL,
  `actor_user_id` int(10) unsigned NOT NULL,
  `actor_role_id` int(10) unsigned DEFAULT NULL,
  `actor_capacity` varchar(120) NOT NULL DEFAULT '' COMMENT 'الصفةُ التي اعتُمد بها',
  `amount` decimal(18,2) DEFAULT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `cap_at_decision` decimal(18,2) DEFAULT NULL COMMENT 'السقفُ النافذُ لحظةَ القرار — يُجمَّد ولا يُقرأ لاحقًا',
  `reason_code` varchar(60) NOT NULL DEFAULT '' COMMENT 'عند الرفضِ — رمزٌ محكوم (BR-03)',
  `note` varchar(400) NOT NULL DEFAULT '',
  `decided_at` datetime NOT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_type` (`company_id`,`source_kind`,`source_ref`,`apr_code`),
  KEY `ix_doc` (`company_id`,`source_kind`,`source_ref`),
  KEY `ix_actor` (`actor_user_id`),
  KEY `ix_when` (`company_id`,`decided_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-ACC-01 §4-7 — سلسلةُ الاعتمادِ الحيةُ بأنواعِها الأربعة';

-- ── Table: fin_approval_conflicts ──
CREATE TABLE `fin_approval_conflicts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `apr_a` varchar(8) NOT NULL,
  `apr_b` varchar(8) NOT NULL,
  `rule_text` varchar(400) NOT NULL DEFAULT '',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pair` (`company_id`,`apr_a`,`apr_b`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-ACC-01 FACC-0044 — أزواجُ الاعتمادِ التي لا تُجمع في شخصٍ واحد';

-- ── Table: fin_approval_matrix ──
CREATE TABLE `fin_approval_matrix` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `event_type` enum('revenue','expense','payable','receivable','payroll','settlement','funding','any') NOT NULL DEFAULT 'any',
  `min_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `max_amount` decimal(16,2) DEFAULT NULL COMMENT 'NULL = بلا حد أعلى',
  `required_level` enum('dept_accountant','dept_manager','finance_manager','executive','board') NOT NULL,
  `sequence` int(11) NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_am_band` (`company_id`,`event_type`,`min_amount`),
  KEY `ix_fin_am_level` (`company_id`,`required_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_approval_types ──
CREATE TABLE `fin_approval_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code` varchar(8) NOT NULL COMMENT 'APR-1..APR-4',
  `seq` tinyint(3) unsigned NOT NULL COMMENT 'ترتيبُ السلسلةِ — ولا يُقفز',
  `title` varchar(120) NOT NULL,
  `owner_label` varchar(200) NOT NULL DEFAULT '' COMMENT 'صاحبُه كما تسميه الوثيقة',
  `question` varchar(200) NOT NULL DEFAULT '' COMMENT 'السؤالُ الذي يجيبه',
  `rule_text` varchar(400) NOT NULL DEFAULT '',
  `allowed_roles` varchar(120) NOT NULL DEFAULT '' COMMENT 'أدوارٌ مفصولةٌ بفاصلة — فارغٌ = بلا قيدِ دور',
  `needs_cap` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'أيشترط سقفًا ماليًّا؟ (APR-3 وحدَه)',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_apr` (`company_id`,`code`),
  KEY `ix_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-ACC-01 §4-7 — أنواعُ الاعتمادِ الأربعةُ ولا يُغني أحدُها عن الآخر';

-- ── Table: fin_approvals ──
CREATE TABLE `fin_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `entity_type` varchar(40) NOT NULL DEFAULT 'financial_event',
  `entity_id` int(11) NOT NULL,
  `from_state` varchar(30) DEFAULT NULL,
  `to_state` varchar(30) NOT NULL,
  `action` enum('advance','reject','post','settle') NOT NULL DEFAULT 'advance',
  `level` enum('dept_accountant','dept_manager','finance_reviewer','auditor','finance_manager') DEFAULT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_appr_entity` (`company_id`,`entity_type`,`entity_id`),
  KEY `ix_fin_appr_created` (`company_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_assets ──
CREATE TABLE `fin_assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(160) NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL COMMENT 'equipments.id (مرجع مرن، اختياري)',
  `acquisition_date` date DEFAULT NULL,
  `acquisition_cost` decimal(16,2) NOT NULL DEFAULT 0.00,
  `salvage_value` decimal(16,2) NOT NULL DEFAULT 0.00,
  `useful_life_months` int(11) NOT NULL DEFAULT 60,
  `method` enum('straight_line') NOT NULL DEFAULT 'straight_line',
  `accumulated_depreciation` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT 'يزيد مع كل احتساب',
  `book_value` decimal(16,2) GENERATED ALWAYS AS (`acquisition_cost` - `accumulated_depreciation`) STORED,
  `state` enum('active','fully_depreciated','disposed') NOT NULL DEFAULT 'active',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_asset_code` (`company_id`,`code`),
  KEY `ix_fin_asset_state` (`company_id`,`state`),
  KEY `ix_fin_asset_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_authority_caps ──
CREATE TABLE `fin_authority_caps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `scope_kind` enum('role','user','dept') NOT NULL DEFAULT 'role',
  `scope_ref` varchar(80) NOT NULL COMMENT 'رقمُ الدورِ أو المستخدمِ أو اسمُ الإدارة',
  `apr_code` varchar(8) NOT NULL DEFAULT 'APR-3',
  `max_amount` decimal(18,2) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `escalates_to_role` int(10) unsigned DEFAULT NULL COMMENT 'من يقرر فوقَ السقف — وأعلاها الرئيسُ التنفيذي',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '' COMMENT 'مرجعُ التفويضِ الموثَّق',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cap` (`company_id`,`scope_kind`,`scope_ref`,`apr_code`),
  KEY `ix_live` (`company_id`,`apr_code`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-ACC-01 APR-3 + PROP-01 CEO-Y0120 — سقوفُ سلطةِ الالتزامِ والدفع';

-- ── Table: fin_backflow_log ──
CREATE TABLE `fin_backflow_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `notice_code` varchar(8) NOT NULL COMMENT 'BF-01..BF-15',
  `source_kind` varchar(40) NOT NULL DEFAULT '',
  `source_ref` varchar(120) NOT NULL COMMENT 'BR-04 — مرجعُ الطلبِ الأصلي',
  `source_stage` varchar(80) NOT NULL DEFAULT '' COMMENT 'مرحلتُه عند الإطلاق',
  `to_user_id` int(10) unsigned DEFAULT NULL,
  `to_role_id` int(10) unsigned DEFAULT NULL,
  `to_label` varchar(200) NOT NULL DEFAULT '',
  `reason_code` varchar(60) NOT NULL DEFAULT '' COMMENT 'BR-03 — رمزٌ محكومٌ لا نصٌّ حر',
  `reason_note` varchar(400) NOT NULL DEFAULT '' COMMENT 'زيادةٌ على الرمزِ لا بديلٌ عنه',
  `work_item_id` bigint(20) unsigned DEFAULT NULL COMMENT 'BR-02 — المهمةُ إن استوجب فعلًا',
  `state` enum('open','acted','closed_cancelled','closed_done') NOT NULL DEFAULT 'open',
  `close_reason` varchar(300) NOT NULL DEFAULT '',
  `fired_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_src` (`company_id`,`source_kind`,`source_ref`),
  KEY `ix_state` (`company_id`,`state`,`fired_at`),
  KEY `ix_to` (`to_user_id`),
  KEY `ix_code` (`notice_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-2/§4-3 — سجلُّ المرتجَعِ الحيُّ وشاهدُ عدمِ الصمت';

-- ── Table: fin_backflow_notices ──
CREATE TABLE `fin_backflow_notices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `code` varchar(8) NOT NULL COMMENT 'BF-01..BF-15',
  `title` varchar(200) NOT NULL,
  `fires_when` varchar(300) NOT NULL DEFAULT '',
  `destination` varchar(300) NOT NULL DEFAULT '',
  `rule_text` varchar(500) NOT NULL DEFAULT '',
  `needs_action` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'BR-02 — ما يستوجب فعلًا يولّد مهمة',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bf` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-2 — المرتجَعُ الماليُّ الخمسةَ عشرَ';

-- ── Table: fin_backflow_rules ──
CREATE TABLE `fin_backflow_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `code` varchar(8) NOT NULL COMMENT 'BR-01..BR-06',
  `rule_text` varchar(600) NOT NULL,
  `accept_test` varchar(400) NOT NULL DEFAULT '',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_br` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-3 — قواعدُ المرتجَعِ الست';

-- ── Table: fin_bank_accounts ──
CREATE TABLE `fin_bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `bank_name` varchar(120) DEFAULT NULL,
  `account_number` varchar(60) DEFAULT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `opening_balance` decimal(16,2) NOT NULL DEFAULT 0.00,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_bank_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_bank_statement_lines ──
CREATE TABLE `fin_bank_statement_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `txn_date` date NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  `direction` enum('deposit','withdrawal') NOT NULL COMMENT 'deposit=إيداع، withdrawal=سحب',
  `amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `matched_payment_id` int(11) DEFAULT NULL COMMENT 'fin_payments (soft)',
  `reconciled` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_bsl_payment` (`matched_payment_id`),
  KEY `ix_fin_bsl_acct` (`company_id`,`bank_account_id`),
  KEY `ix_fin_bsl_rec` (`company_id`,`reconciled`),
  KEY `fk_fin_bsl_acct` (`bank_account_id`),
  CONSTRAINT `fk_fin_bsl_acct` FOREIGN KEY (`bank_account_id`) REFERENCES `fin_bank_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fin_bsl_payment` FOREIGN KEY (`matched_payment_id`) REFERENCES `fin_payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_budget_change_requests ──
CREATE TABLE `fin_budget_change_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `req_code` varchar(16) NOT NULL COMMENT 'BCR-000001',
  `budget_id` int(11) NOT NULL,
  `budget_line_id` int(11) DEFAULT NULL,
  `dept_module` varchar(64) NOT NULL DEFAULT '' COMMENT 'الإدارةُ الطالبة',
  `current_amount` decimal(18,2) NOT NULL,
  `requested_amount` decimal(18,2) NOT NULL,
  `impact_note` text DEFAULT NULL COMMENT 'بيانُ الأثر — إلزاميٌّ قبل الاعتماد',
  `state` enum('submitted','approved','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
  `decided_reason` varchar(190) NOT NULL DEFAULT '',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'رقمُ الموازنة الأم',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bcr_code` (`company_id`,`req_code`),
  KEY `ix_bcr_budget` (`company_id`,`budget_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 budget.request: الطلبُ يدخل سلّمَ الاعتماد ببيان أثره — ولا يُعدَّل السقفُ قبله';

-- ── Table: fin_budget_commitments ──
CREATE TABLE `fin_budget_commitments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `commit_code` varchar(16) NOT NULL COMMENT 'CMT-000001',
  `budget_id` int(11) NOT NULL COMMENT 'fin_budgets.id',
  `budget_line_id` int(11) DEFAULT NULL COMMENT 'fin_budget_lines.id',
  `source_kind` enum('payment_request','purchase_order','contract','other') NOT NULL,
  `source_ref` varchar(64) NOT NULL COMMENT 'مرجعُ المصدر — طلبُ الدفعِ أو أمرُ الشراء',
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `available_before` decimal(18,2) DEFAULT NULL COMMENT 'المتاحُ قبل الحجز (حساس)',
  `state` enum('committed','consumed','released') NOT NULL DEFAULT 'committed',
  `released_reason` varchar(190) NOT NULL DEFAULT '' COMMENT 'العكس: تحريرٌ عند الإلغاء بسببه',
  `released_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `idempotency_key` varchar(80) NOT NULL COMMENT '(المصدرُ ونوعُه) — لا حجزَ مزدوجًا',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cmtb_code` (`company_id`,`commit_code`),
  UNIQUE KEY `uq_cmtb_idem` (`company_id`,`idempotency_key`),
  KEY `ix_cmtb_budget` (`company_id`,`budget_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 budget.commit: الالتزامُ يخفض المتاحَ قبل الصرف — والتحريرُ عكسُه';

-- ── Table: fin_budget_lines ──
CREATE TABLE `fin_budget_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `budget_id` int(11) NOT NULL,
  `line_kind` enum('revenue','expense') NOT NULL,
  `category` enum('salaries','fuel','maintenance','procurement','catering','transport','operational_need','capacity_need','revenue','other') NOT NULL,
  `account_id` int(11) DEFAULT NULL COMMENT 'fin_chart_of_accounts.id',
  `planned_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `actual_amount` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT 'يُغذّى من القيود المرحّلة',
  `variance` decimal(16,2) GENERATED ALWAYS AS (`actual_amount` - `planned_amount`) STORED,
  `variance_pct` decimal(9,2) GENERATED ALWAYS AS (case when `planned_amount` = 0 then NULL else (`actual_amount` - `planned_amount`) / `planned_amount` * 100 end) STORED,
  `cause` varchar(200) DEFAULT NULL,
  `corrective_action` varchar(200) DEFAULT NULL,
  `responsible_id` int(11) DEFAULT NULL,
  `var_state` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `note` varchar(200) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_bl_budget` (`company_id`,`budget_id`),
  KEY `fk_fin_bl_budget` (`budget_id`),
  KEY `fk_fin_bl_acc` (`account_id`),
  CONSTRAINT `fk_fin_bl_acc` FOREIGN KEY (`account_id`) REFERENCES `fin_chart_of_accounts` (`id`),
  CONSTRAINT `fk_fin_bl_budget` FOREIGN KEY (`budget_id`) REFERENCES `fin_budgets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_budgets ──
CREATE TABLE `fin_budgets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `budget_no` varchar(30) NOT NULL,
  `dept_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','general','sites','movement','transport','tickets','admin') NOT NULL,
  `period_type` enum('annual','quarterly','monthly') NOT NULL,
  `fiscal_year` int(11) NOT NULL,
  `period_no` int(11) DEFAULT NULL COMMENT 'ربع 1-4 أو شهر 1-12',
  `total_revenue` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total_expense` decimal(16,2) NOT NULL DEFAULT 0.00,
  `state` enum('draft','submitted','returned','approved','active','closed') NOT NULL DEFAULT 'draft' COMMENT 'مسودة → مقدَّمة → (معادة بسبب | معتمدة) → نشطة → مقفلة',
  `submitted_by` int(11) DEFAULT NULL COMMENT 'مديرُ الإدارة الذي رفعها',
  `submitted_at` datetime DEFAULT NULL COMMENT 'لحظةُ الرفع',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL COMMENT 'لحظةُ الإجازة',
  `returned_by` int(11) DEFAULT NULL COMMENT 'من أعادها',
  `returned_at` datetime DEFAULT NULL,
  `return_reason` varchar(255) DEFAULT NULL COMMENT 'سببُ الإعادة — بارزٌ للإدارة (الدستور §4.3: «أُعيد إليك لاستكمال: السبب»)',
  `note` varchar(200) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_budget` (`company_id`,`dept_module`,`period_type`,`fiscal_year`,`period_no`),
  KEY `ix_fin_budget_state` (`company_id`,`state`),
  KEY `ix_fin_budget_deleted` (`is_deleted`),
  KEY `ix_fin_budget_dept_state` (`company_id`,`dept_module`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_cash_forecasts ──
CREATE TABLE `fin_cash_forecasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `forecast_date` date NOT NULL,
  `horizon_type` enum('daily','weekly','monthly') NOT NULL,
  `opening_cash` decimal(16,2) NOT NULL DEFAULT 0.00,
  `expected_inflow` decimal(16,2) NOT NULL DEFAULT 0.00,
  `expected_outflow` decimal(16,2) NOT NULL DEFAULT 0.00,
  `expected_position` decimal(16,2) GENERATED ALWAYS AS (`opening_cash` + `expected_inflow` - `expected_outflow`) STORED,
  `min_required` decimal(16,2) DEFAULT NULL,
  `funding_gap` decimal(16,2) DEFAULT NULL COMMENT 'إن نقص الوضع عن الحد الأدنى',
  `cash_priority` enum('critical','high','normal') DEFAULT NULL,
  `source` enum('receivables','payables','payroll','funding','manual') DEFAULT NULL,
  `note` varchar(200) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_cf_date` (`company_id`,`forecast_date`,`horizon_type`),
  KEY `ix_fin_cf_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_cashflow ──
CREATE TABLE `fin_cashflow` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `cf_code` varchar(16) NOT NULL COMMENT 'CFS-000001',
  `period` varchar(10) NOT NULL,
  `net_profit` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'نقطةُ البدءِ — النتيجة',
  `adj_depreciation` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'تسوياتٌ غيرُ نقدية',
  `adj_provisions` decimal(20,2) NOT NULL DEFAULT 0.00,
  `adj_other` decimal(20,2) NOT NULL DEFAULT 0.00,
  `wc_receivables` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'التغيرُ في رأسِ المالِ العامل',
  `wc_inventory` decimal(20,2) NOT NULL DEFAULT 0.00,
  `wc_payables` decimal(20,2) NOT NULL DEFAULT 0.00,
  `wc_other` decimal(20,2) NOT NULL DEFAULT 0.00,
  `operating_net` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'التدفقُ التشغيلي',
  `investing_net` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'الاستثماري — من cashflow_activity',
  `financing_net` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'التمويلي',
  `net_change` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'صافي التغير المحسوب',
  `cash_open` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'نقديةُ أولِ المدةِ من 1101+1102',
  `cash_close` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'نقديةُ آخرِ المدةِ الفعلية',
  `actual_change` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'التغيرُ الفعليُّ = الختاميُّ − الافتتاحي',
  `balance_diff` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '◆ الفرقُ — والتوليدُ يُرفض إن تجاوز الحد',
  `balance_ok` tinyint(1) NOT NULL DEFAULT 0 COMMENT '◆ تتوازن أو تُرفض',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `lines_json` mediumtext DEFAULT NULL,
  `state` enum('generated','superseded') NOT NULL DEFAULT 'generated',
  `supersedes_id` int(10) unsigned DEFAULT NULL,
  `generated_by` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '',
  `idempotency_key` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cfs_code` (`company_id`,`cf_code`),
  UNIQUE KEY `uq_cfs_idem` (`company_id`,`idempotency_key`),
  KEY `ix_cfs_period` (`company_id`,`period`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 S4: الطريقةُ غيرُ المباشرةِ — وتتوازن مع تغيرِ النقديةِ الفعليِّ أو تُرفض';

-- ── Table: fin_chart_of_accounts ──
CREATE TABLE `fin_chart_of_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL COMMENT 'رقم الحساب',
  `name` varchar(160) NOT NULL,
  `name_en` varchar(190) NOT NULL DEFAULT '' COMMENT 'الاسمُ الإنجليزيُّ من الوثيقة',
  `account_type` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `acc_level` tinyint(3) unsigned NOT NULL DEFAULT 4 COMMENT 'COA §01: أربعةُ مستوياتٍ — 1 جذرٌ · 2 تجميعيٌّ · 3 يُقيَّد عليه · 4 تفصيليٌّ موروث',
  `parent_code` varchar(30) NOT NULL DEFAULT '' COMMENT 'الأبُ بالكودِ — الشجرةُ تُبنى بالكودِ لا بالمعرّفِ وحدَه',
  `balance_nature` enum('debit','credit') NOT NULL DEFAULT 'debit' COMMENT 'طبيعةُ الرصيد — مدينٌ أو دائن',
  `statement_code` varchar(8) NOT NULL DEFAULT '' COMMENT 'S1..S5: المركزُ · دخلُ الشركةِ · دخلُ المشروعِ · التدفقاتُ · حقوقُ الملكية',
  `statement_line` varchar(190) NOT NULL DEFAULT '' COMMENT 'بندُ القائمة',
  `cashflow_activity` enum('operating','investing','financing','none') NOT NULL DEFAULT 'none' COMMENT 'R4: بدونه لا تُنتَج قائمةُ التدفقاتِ إلا يدويًّا',
  `required_dims` varchar(64) NOT NULL DEFAULT '' COMMENT 'R9: الأبعادُ التي يلزمها هذا الحسابُ — D1,D2,D5 …',
  `is_canonical` tinyint(1) NOT NULL DEFAULT 0 COMMENT '◆ 1 = من شجرةِ الوثيقةِ المعادِ هيكلتُها · 0 = موروثٌ محجورٌ بخريطة',
  `coa_note` varchar(255) NOT NULL DEFAULT '',
  `parent_id` int(11) DEFAULT NULL COMMENT 'مرجع ذاتي — أب الحساب',
  `is_postable` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'هل يقبل قيداً مباشراً',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_acc_code` (`company_id`,`code`),
  KEY `ix_fin_acc_type` (`company_id`,`account_type`),
  KEY `ix_fin_acc_parent` (`parent_id`),
  KEY `ix_coa_canon` (`company_id`,`is_canonical`,`acc_level`),
  KEY `ix_coa_stmt` (`company_id`,`statement_code`),
  CONSTRAINT `fk_fin_coa_parent` FOREIGN KEY (`parent_id`) REFERENCES `fin_chart_of_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_client_statements ──
CREATE TABLE `fin_client_statements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `stmt_code` varchar(16) NOT NULL COMMENT 'CST-000001 — رقمُ الكشف',
  `client_id` int(11) NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `opening_balance` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'رصيدُ أول المدة (حساس)',
  `invoices_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `credit_notes_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `collections_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `advance_deduction` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'خصمُ المقدم',
  `retention_held` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'محتجزُ الضمان',
  `closing_balance` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'رصيدُ آخر المدة (حساس)',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `base_equiv` decimal(18,2) DEFAULT NULL COMMENT 'المعادلُ بعملة الدفاتر',
  `oldest_unpaid_date` date DEFAULT NULL COMMENT 'أقدمُ فاتورةٍ غيرِ مسدَّدة',
  `overdue_days` int(11) NOT NULL DEFAULT 0,
  `client_match_state` enum('بانتظار المطابقة','طابق العميل','نزاع') NOT NULL DEFAULT 'بانتظار المطابقة',
  `layers_json` mediumtext DEFAULT NULL COMMENT 'طبقاتُ الكشف من ClientStatementService — لقطةٌ مثبتة',
  `state` enum('issued','superseded') NOT NULL DEFAULT 'issued',
  `supersedes_id` int(10) unsigned DEFAULT NULL COMMENT 'العكس: النسخةُ الجديدةُ تشير للسابقة',
  `issued_by` int(11) NOT NULL COMMENT '§9-1 المُنشئ — أصدره',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL COMMENT 'اعتمده',
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'المرجعُ الأب — العميل أو العقد',
  `idempotency_key` varchar(80) NOT NULL COMMENT '(العميل×الفترة×النسخة)',
  `cost_center_id` int(11) DEFAULT NULL,
  `fx_note` varchar(64) NOT NULL DEFAULT '' COMMENT 'سعرُ الصرف ومصدره',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cst_code` (`company_id`,`stmt_code`),
  UNIQUE KEY `uq_cst_idem` (`company_id`,`idempotency_key`),
  KEY `ix_cst_client` (`company_id`,`client_id`,`period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 الشاشة ١٦: كشفُ حساب العميل — تثبيتُ رصيدٍ والعكسُ نسخةٌ جديدة';

-- ── Table: fin_closing_items ──
CREATE TABLE `fin_closing_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `step` enum('reconcile_bank','reconcile_ar','reconcile_ap','post_accruals','post_depreciation','settle_supplier','payroll_posted','variance_reviewed','intercompany_settled','reports_issued') NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT 1,
  `item_state` enum('pending','done','na') NOT NULL DEFAULT 'pending',
  `done_by` int(11) DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `note` varchar(200) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_ci_period` (`company_id`,`period_id`),
  KEY `fk_fin_ci_period` (`period_id`),
  CONSTRAINT `fk_fin_ci_period` FOREIGN KEY (`period_id`) REFERENCES `fin_financial_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_coa_migration ──
CREATE TABLE `fin_coa_migration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `old_account_id` int(11) DEFAULT NULL COMMENT 'معرّفُ الحسابِ الموروثِ — يبقى ولا يُحذف',
  `old_code` varchar(40) NOT NULL COMMENT 'الكودُ قبل الوسمِ الموروث',
  `old_name` varchar(190) NOT NULL DEFAULT '',
  `new_account_id` int(11) DEFAULT NULL COMMENT 'الحسابُ القانونيُّ من الشجرةِ المعادِ هيكلتُها',
  `new_code` varchar(30) NOT NULL,
  `dim_key` varchar(8) NOT NULL DEFAULT '' COMMENT 'البُعدُ الذي حلَّ محلَّ التفصيل — D5 · D6 …',
  `dim_value` varchar(190) NOT NULL DEFAULT '' COMMENT 'قيمتُه — اسمُ الطرفِ أو المعدة',
  `balance_before` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'مدين − دائن قبلَ الترحيل',
  `balance_after` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'مدين − دائن بعدَه على الحسابِ الجديد',
  `lines_moved` int(11) NOT NULL DEFAULT 0 COMMENT 'سطورُ القيدِ التي أُعيد توجيهُها',
  `rule_note` varchar(255) NOT NULL DEFAULT '' COMMENT 'قاعدةُ الترحيل: R2 · R8 · مطابقةٌ دلالية',
  `migrated_by` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coamig` (`company_id`,`old_code`),
  KEY `ix_coamig_new` (`company_id`,`new_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='COA R10: الترحيلُ بخريطةٍ لا بحذف — وتقريرٌ يثبت تساوي الأرصدة';

-- ── Table: fin_collection_allocations ──
CREATE TABLE `fin_collection_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `receivable_id` int(11) DEFAULT NULL COMMENT 'ذمّةُ الفاتورة — NULL لغير الفاتورة (والمفتاحُ الأجنبيُّ يقبل NULL)',
  `target_kind` enum('advance','invoice','milestone','retention','final') NOT NULL DEFAULT 'invoice' COMMENT 'هدفُ التخصيص — والفاتورةُ واحدٌ من خمسةٍ لا الوحيد',
  `target_ref` int(11) NOT NULL DEFAULT 0 COMMENT 'معرّفُ الهدف: fin_receivables للفاتورة · contract_payment_schedule لغيرها',
  `amount` decimal(18,2) NOT NULL,
  `pay_currency` varchar(8) NOT NULL DEFAULT '' COMMENT 'عملةُ السداد (settlement)',
  `target_currency` varchar(8) NOT NULL DEFAULT '' COMMENT 'عملةُ الهدف (contract غالبًا)',
  `amount_target` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '**المعادلُ الذي أُطفئت به الذمّة** بعملة الهدف',
  `fx_rate_pay` decimal(20,8) DEFAULT NULL,
  `fx_rate_target` decimal(20,8) DEFAULT NULL,
  `base_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'قيمةُ المقبوض بالعملة الوظيفية',
  `fx_diff_base` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '**فرقُ الصرف المحقق** بالعملة الوظيفية — بسطره لا مبتلعًا في المبلغ',
  `basis` enum('explicit','oldest_first') NOT NULL DEFAULT 'oldest_first' COMMENT 'أساسُ التخصيص: مرجعٌ صريحٌ من العميل · أو **أقدمُ فاتورةٍ أولًا** (§4)',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alloc_target` (`payment_id`,`target_kind`,`target_ref`),
  UNIQUE KEY `uq_alloc` (`payment_id`,`receivable_id`),
  KEY `ix_alloc_recv` (`company_id`,`receivable_id`),
  KEY `fk_alloc_receivable` (`receivable_id`),
  CONSTRAINT `fk_alloc_payment` FOREIGN KEY (`payment_id`) REFERENCES `fin_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_receivable` FOREIGN KEY (`receivable_id`) REFERENCES `fin_receivables` (`id`),
  CONSTRAINT `ck_alloc_target` CHECK (`target_ref` > 0 and (`target_kind` = _utf8mb4'invoice' and `receivable_id` is not null and `target_ref` = `receivable_id` or `target_kind` <> _utf8mb4'invoice' and `receivable_id` is null)),
  CONSTRAINT `ck_alloc_amount` CHECK (`amount` > 0),
  CONSTRAINT `ck_alloc_fx` CHECK (`amount_target` >= 0 and `base_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_contract_fields ──
CREATE TABLE `fin_contract_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `field_code` varchar(16) NOT NULL COMMENT 'CFIELD-01 .. CFIELD-28',
  `seq` smallint(5) unsigned NOT NULL DEFAULT 0,
  `title` varchar(300) NOT NULL COMMENT 'اسمُ الحقلِ كما تسميه الوثيقة',
  `obligation` enum('always','conditional','optional') NOT NULL DEFAULT 'optional' COMMENT 'always = لا يُقبل عقدٌ بدونه · conditional = عند الانطباق',
  `condition_ar` varchar(300) NOT NULL DEFAULT '' COMMENT 'شرطُ الإلزامِ حين يكون مشروطًا',
  `rule_ar` varchar(300) NOT NULL DEFAULT '' COMMENT 'حكمُ الوثيقةِ على الحقلِ نصًّا',
  `home_table` varchar(64) NOT NULL DEFAULT '' COMMENT '◆ الفارغُ = فجوةٌ معلَنةٌ لا سهو',
  `home_column` varchar(64) NOT NULL DEFAULT '',
  `resolve_state` enum('live','gap','pending') NOT NULL DEFAULT 'pending' COMMENT 'يُحسم آليًّا بفحصِ information_schema — لا بالإعلان',
  `owner_action` varchar(300) NOT NULL DEFAULT '' COMMENT 'ما يلزم المالكَ فعلُه لسدِّ الفجوة',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cf` (`company_id`,`field_code`),
  KEY `ix_ob` (`obligation`,`resolve_state`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-6 — حقولُ العقدِ الحاكمةُ الـ28 بموضعِ كلٍّ وإلزامِه';

-- ── Table: fin_contract_types ──
CREATE TABLE `fin_contract_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `type_code` varchar(12) NOT NULL COMMENT 'EC-01..08 · FC-01..10',
  `family` enum('employee','financier') NOT NULL,
  `name_ar` varchar(190) NOT NULL,
  `name_en` varchar(190) NOT NULL DEFAULT '',
  `accounts_csv` varchar(120) NOT NULL DEFAULT '' COMMENT 'أكوادُ الحساباتِ التي يُقيَّد عليها',
  `cost_nature` varchar(190) NOT NULL DEFAULT '',
  `accounting_rule` varchar(400) NOT NULL DEFAULT '' COMMENT 'الحكمُ المحاسبيُّ نصًّا من الوثيقة',
  `capitalizes` tinyint(1) NOT NULL DEFAULT 0 COMMENT '◆ الإجارةُ المنتهيةُ بالتمليكِ تُرسمَل والتشغيليُّ لا',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ctype` (`company_id`,`type_code`),
  KEY `ix_ctype_family` (`company_id`,`family`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='COA §03/§04: ثمانيةُ عقودِ موظفينَ وعشرةُ عقودِ ممولينَ — البُعد D9';

-- ── Table: fin_cost_centers ──
CREATE TABLE `fin_cost_centers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(160) NOT NULL,
  `center_type` enum('cost','profit') NOT NULL DEFAULT 'cost',
  `parent_id` int(11) DEFAULT NULL COMMENT 'مرجع ذاتي (شجرة)',
  `owner_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','general') DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `level` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_cc_code` (`company_id`,`code`),
  KEY `ix_fin_cc_parent` (`company_id`,`parent_id`),
  KEY `ix_fin_cc_deleted` (`is_deleted`),
  KEY `fk_fin_cc_parent` (`parent_id`),
  CONSTRAINT `fk_fin_cc_parent` FOREIGN KEY (`parent_id`) REFERENCES `fin_cost_centers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_cost_records ──
CREATE TABLE `fin_cost_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `cost_type` enum('equipment','project','hour','ton','meter','fuel','maintenance','workforce') NOT NULL,
  `equipment_id` int(11) DEFAULT NULL COMMENT 'equipments.id (مرجع مرن)',
  `project_id` int(11) DEFAULT NULL COMMENT 'project.id (مرجع مرن)',
  `period_ref` varchar(30) DEFAULT NULL,
  `qty` decimal(14,2) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL COMMENT 'ساعة/طن/متر/لتر',
  `unit_cost` decimal(14,4) DEFAULT NULL,
  `total_cost` decimal(16,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG' COMMENT 'عملة العقد مصدر التكلفة كما وقعت — لا تحويل عند التسجيل',
  `revenue` decimal(16,2) DEFAULT NULL,
  `profit` decimal(16,2) GENERATED ALWAYS AS (coalesce(`revenue`,0) - `total_cost`) STORED,
  `event_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_cost_type` (`company_id`,`cost_type`),
  KEY `ix_fin_cost_project` (`project_id`),
  KEY `ix_fin_cost_equip` (`equipment_id`),
  KEY `ix_fin_cost_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_currencies ──
CREATE TABLE `fin_currencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل المستأجر',
  `code` varchar(8) NOT NULL COMMENT 'رمز العملة ISO — USD · SDG',
  `name_ar` varchar(64) NOT NULL COMMENT 'الاسم كما يظهر للمستخدم',
  `symbol` varchar(8) DEFAULT NULL COMMENT 'الرمز المختصر للعرض ($ · ج.س)',
  `decimals` tinyint(4) NOT NULL DEFAULT 2 COMMENT 'خاناتُ الكسر عند العرض',
  `is_base` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'عملةُ الأساس — واحدةٌ لكل شركة، مشتقّةٌ من admin_companies.currency',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_currency_code` (`company_id`,`code`),
  KEY `ix_currency_base` (`company_id`,`is_base`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجلُّ العملات — عملةُ الأساس وما يُقاس بها (FES-01 §3.3)';

-- ── Table: fin_cycle_stages ──
CREATE TABLE `fin_cycle_stages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `cycle_kind` enum('payment','receipt','audit','accountant') NOT NULL,
  `seq` smallint(5) unsigned NOT NULL COMMENT 'ترتيبُ المرحلةِ — ولا تُقفز',
  `stage_ar` varchar(200) NOT NULL,
  `owner_hint` varchar(160) NOT NULL DEFAULT '' COMMENT 'من يملك المرحلة',
  `doc_code` varchar(24) NOT NULL DEFAULT '',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stage` (`company_id`,`cycle_kind`,`seq`),
  KEY `ix_cycle` (`cycle_kind`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-TRE-01 §4-4 · IAF-01 §4-5 · FIN-ACC-01 §4-5 — مراحلُ الدوراتِ بترتيبها';

-- ── Table: fin_cycle_time_metrics ──
CREATE TABLE `fin_cycle_time_metrics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `metric_code` varchar(16) NOT NULL COMMENT 'CYC-000001',
  `period` varchar(7) NOT NULL,
  `request_type` varchar(64) NOT NULL COMMENT 'نوعُ الطلب',
  `dept_module` varchar(64) NOT NULL DEFAULT '' COMMENT 'الإدارة',
  `requests_count` int(11) NOT NULL DEFAULT 0,
  `avg_ring1_hours` decimal(10,2) DEFAULT NULL COMMENT 'متوسطُ زمن الحلقة الأولى',
  `avg_ring2_hours` decimal(10,2) DEFAULT NULL,
  `avg_ring3_hours` decimal(10,2) DEFAULT NULL,
  `total_cycle_hours` decimal(10,2) DEFAULT NULL COMMENT 'إجماليُّ زمن الدورة',
  `target_hours` decimal(10,2) DEFAULT NULL COMMENT 'المستهدف — من قواعد التوجيه',
  `variance_hours` decimal(10,2) DEFAULT NULL COMMENT 'الانحراف',
  `longest_ring` varchar(24) NOT NULL DEFAULT '' COMMENT 'أطولُ حلقة',
  `slowest_approver_id` int(11) DEFAULT NULL COMMENT 'المعتمِدُ الأبطأ',
  `breach_count` int(11) NOT NULL DEFAULT 0 COMMENT 'عددُ المتجاوز للمهلة',
  `compliance_pct` decimal(9,4) DEFAULT NULL COMMENT 'نسبةُ الالتزام',
  `action_note` varchar(190) NOT NULL DEFAULT '' COMMENT 'الإجراء',
  `state` enum('measured','superseded') NOT NULL DEFAULT 'measured',
  `computed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '',
  `idempotency_key` varchar(96) NOT NULL COMMENT '(الفترة×النوع×الإدارة)',
  `cost_center_id` int(11) DEFAULT NULL,
  `fx_note` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cyc_code` (`company_id`,`metric_code`),
  UNIQUE KEY `uq_cyc_idem` (`company_id`,`idempotency_key`),
  KEY `ix_cyc_period` (`company_id`,`period`,`request_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 الشاشة ٢٣: قياسُ زمن الدورة بالحلقة والمعتمِد — والتقريرُ دوري';

-- ── Table: fin_depreciation ──
CREATE TABLE `fin_depreciation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `period_ref` varchar(10) NOT NULL COMMENT 'YYYY-MM',
  `depreciation_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `run_date` date NOT NULL,
  `journal_entry_id` int(11) DEFAULT NULL COMMENT 'fin_journal_entries (soft)',
  `event_id` int(11) DEFAULT NULL COMMENT 'الحدثُ المالي المنشور (fin_financial_events) — «كلُّ حدثٍ يُقرأ بالاتجاهين»',
  `method` varchar(24) DEFAULT NULL COMMENT 'طريقةُ الإهلاك ساعةَ الاحتساب — من إعداد الأصل لا من اجتهاد',
  `basis_json` text DEFAULT NULL COMMENT 'لقطةُ الأساس: التكلفةُ والخردةُ والعمرُ والمجمّعُ قبلَه — لا اشتقاقٌ لاحق',
  `source` enum('screen','cron','legacy') NOT NULL DEFAULT 'screen' COMMENT 'من أوقعه — والقديمُ يُصرَّح legacy لا يُدَّعى أنه من الخدمة',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_dep` (`company_id`,`asset_id`,`period_ref`),
  KEY `ix_fin_dep_asset` (`company_id`,`asset_id`),
  KEY `fk_fin_dep_asset` (`asset_id`),
  KEY `ix_fin_dep_event` (`event_id`),
  CONSTRAINT `fk_fin_dep_asset` FOREIGN KEY (`asset_id`) REFERENCES `fin_assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_dues ──
CREATE TABLE `fin_dues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `party_type` enum('supplier','employee','proc_supplier') NOT NULL COMMENT 'supplier=مورد الآليات (suppliers) · employee=عامل · proc_supplier=مورد المشتريات (proc_supplier) — سجلّان مختلفان لا يُخلطان',
  `party_ref` int(11) NOT NULL COMMENT 'suppliers.id / employees.id (مرجع مرن)',
  `due_type` enum('hours','tons','meters','advance','discount','penalty','purchase','fuel','parts','catering','water','transport','salary','allowance','overtime','deduction','custody','settlement','end_of_service','guarantee_release','other') NOT NULL,
  `direction` enum('credit','debit') NOT NULL DEFAULT 'credit' COMMENT 'credit=له، debit=عليه',
  `amount` decimal(16,2) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `fx_rate` decimal(20,8) DEFAULT NULL COMMENT 'سعرُ الصرف بتاريخ نشوء الذمّة',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'المعادلُ بعملة الأساس — عليه تُجمع تسويةُ الطرف متعددِ العملات',
  `period_ref` varchar(30) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `source_doc_type` enum('proc_issue','mnt_order','transfer_order','penalty_assessment','settlement','supplier_closure','employee_closure','legacy_no_ref','pending_source') DEFAULT NULL,
  `source_doc_id` int(10) unsigned DEFAULT NULL COMMENT 'معرّفُ المستند في جدوله — NULL مع legacy_no_ref وحدَها',
  `settlement_state` enum('pending','settled','paid') NOT NULL DEFAULT 'pending',
  `pre_settlement_legacy` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'صفٌّ دُفِع قبل سريان قاعدة «لا دفعَ بلا تسوية» — مستثنًى صراحةً لا ملفَّقٌ له مستند',
  `settlement_id` int(11) DEFAULT NULL COMMENT 'التسويةُ التي احتسبت هذا الصفَّ — فلا يُحتسب في تسويتين',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_dues_party` (`company_id`,`party_type`,`party_ref`),
  KEY `ix_fin_dues_settle` (`company_id`,`settlement_state`),
  KEY `ix_fin_dues_deleted` (`is_deleted`),
  KEY `fk_dues_currency` (`company_id`,`currency`),
  KEY `ix_dues_source_doc` (`company_id`,`source_doc_type`,`source_doc_id`),
  CONSTRAINT `fk_dues_currency` FOREIGN KEY (`company_id`, `currency`) REFERENCES `fin_currencies` (`company_id`, `code`) ON UPDATE CASCADE,
  CONSTRAINT `ck_dues_debit_source` CHECK (`direction` <> 'debit' or `source_doc_type` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_effect_map ──
CREATE TABLE `fin_effect_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `source_kind` varchar(30) NOT NULL COMMENT 'نوع المصدر: unit_record (وحدة معتمدة) — يتوسّع لاحقًا',
  `effect_type` varchar(40) NOT NULL COMMENT 'يوافق fin_event_links.effect_type',
  `effect_label` varchar(80) NOT NULL COMMENT 'التسمية المعروضة في شجرة الأثر',
  `target_table` varchar(40) NOT NULL COMMENT 'الجدول الذي يُكتب فيه الأثر',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = معلن غير متاح/معطّل — يُسجَّل ولا يُلفَّق',
  `param_value` decimal(14,4) DEFAULT NULL COMMENT 'معامل الأثر إن لزم (مثال: مخصّص الصيانة للوحدة)',
  `unavailable_reason` varchar(200) DEFAULT NULL COMMENT 'سبب التعطيل — يظهر للمستخدم بدل الصمت',
  `display_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_effect_map` (`company_id`,`source_kind`,`effect_type`),
  KEY `ix_effect_source` (`company_id`,`source_kind`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D05 §6.1: خريطة تفريع الأثر — قواعد المروحة بياناتٍ لا كودًا';

-- ── Table: fin_entitlement_gate_log ──
CREATE TABLE `fin_entitlement_gate_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `gate_code` varchar(16) NOT NULL COMMENT 'GTE-000001 — رقمُ البوابة',
  `period` varchar(7) NOT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `unit_record_id` int(11) NOT NULL COMMENT 'الواقعةُ المرجعية',
  `chain_ok` tinyint(1) NOT NULL DEFAULT 0 COMMENT '① سلسلةُ الاعتماد مكتملة؟',
  `period_ok` tinyint(1) NOT NULL DEFAULT 0 COMMENT '② الفترةُ المحاسبية مفتوحة؟',
  `contract_ok` tinyint(1) NOT NULL DEFAULT 0 COMMENT '③ العقدُ نافذ؟',
  `quota_ok` tinyint(1) NOT NULL DEFAULT 0 COMMENT '④ الحصةُ متاحة؟',
  `result` enum('pass','reject') NOT NULL COMMENT 'نتيجةُ الفحص',
  `reject_code` varchar(24) NOT NULL DEFAULT '' COMMENT 'سببُ الرد المحكوم: GATE-CHAIN · GATE-PERIOD · GATE-CONTRACT · GATE-QUOTA',
  `client_ruling` varchar(32) NOT NULL DEFAULT '',
  `supplier_ruling` varchar(32) NOT NULL DEFAULT '',
  `operator_ruling` varchar(32) NOT NULL DEFAULT '',
  `impact_amount` decimal(18,2) DEFAULT NULL COMMENT 'قيمةُ الأثر (حساس)',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `fx_note` varchar(64) NOT NULL DEFAULT '' COMMENT 'سعرُ الصرف ومصدره',
  `fact_event_id` int(11) DEFAULT NULL COMMENT 'رقمُ الحدثِ المولَّد إن مرّت',
  `journal_ref` varchar(32) NOT NULL DEFAULT '',
  `idempotency_key` varchar(80) NOT NULL COMMENT '(الوحدة×المحاولة اليومية) — الإعادةُ ترجع الأول',
  `state` enum('logged','superseded') NOT NULL DEFAULT 'logged',
  `created_by` int(11) NOT NULL COMMENT '§9-1 المُنشئ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gate_code` (`company_id`,`gate_code`),
  UNIQUE KEY `uq_gate_idem` (`company_id`,`idempotency_key`),
  KEY `ix_gate_unit` (`company_id`,`unit_record_id`,`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 الشاشة ٢٦: بوابةُ الاستحقاقِ الرباعية — إخفاقُ فحصٍ يردُّ الواقعةَ بسببٍ محكوم';

-- ── Table: fin_entitlements ──
CREATE TABLE `fin_entitlements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `entitle_code` varchar(16) NOT NULL COMMENT 'ENT-000001 — رقمُ المحضر',
  `period` varchar(7) NOT NULL COMMENT 'YYYY-MM — الفترة',
  `contract_id` int(11) DEFAULT NULL COMMENT 'العقد',
  `unit_record_id` int(11) NOT NULL COMMENT 'الواقعةُ المرجعية — fin_unit_records.id',
  `client_ruling` varchar(32) NOT NULL DEFAULT '' COMMENT 'حكمُ العميل — يُفوتر أو سببُ الامتناع',
  `client_amount` decimal(18,2) DEFAULT NULL COMMENT 'قيمةُ إيراد العميل (حقلٌ حساس)',
  `supplier_ruling` varchar(32) NOT NULL DEFAULT '' COMMENT 'حكمُ المورد',
  `supplier_amount` decimal(18,2) DEFAULT NULL COMMENT 'قيمةُ استحقاق المورد (حساس)',
  `operator_ruling` varchar(32) NOT NULL DEFAULT '' COMMENT 'حكمُ المشغّل',
  `operator_amount` decimal(18,2) DEFAULT NULL COMMENT 'قيمةُ أجر المشغّل (حساس)',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `fx_rate` decimal(18,6) DEFAULT NULL COMMENT 'سعرُ الصرف (حساس) — base=amount×rate',
  `chain_completed_at` datetime DEFAULT NULL COMMENT 'تاريخُ اكتمال السلسلة الخماسية',
  `fact_event_id` int(11) DEFAULT NULL COMMENT 'رقمُ الحدث — ems_business_events.id',
  `journal_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'رقمُ القيد إن نُشر',
  `effects_json` text DEFAULT NULL COMMENT 'مخرجُ المروحة: آثارٌ ومتخطًّى بأسبابه (لا تلفيق)',
  `state` enum('generated','approved','reversed') NOT NULL DEFAULT 'generated',
  `generated_by` int(11) NOT NULL COMMENT '§9-1 المُنشئ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL COMMENT '§9-1 المعتمِد — المديرُ المالي',
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '' COMMENT '§9-1 مرجعُ التفويض',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT '§9-1 المرجعُ الأب — رقمُ الوحدة',
  `idempotency_key` varchar(80) NOT NULL COMMENT 'AR-04: (الشركة×الوحدة) — لا محضرَ ثانيًا',
  `effect_grade` varchar(16) NOT NULL DEFAULT 'مالي' COMMENT 'درجةُ الأثر',
  `reversed_by_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'معكوسٌ بـ — قيدٌ عاكسٌ بمرجعه',
  `reverses_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'عكسٌ عن',
  `cost_center_id` int(11) DEFAULT NULL COMMENT 'مركزُ التكلفة (حساس)',
  `ruleset_version` varchar(32) NOT NULL DEFAULT '' COMMENT 'نسخةُ القاعدةِ المستعملة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ent_code` (`company_id`,`entitle_code`),
  UNIQUE KEY `uq_ent_idem` (`company_id`,`idempotency_key`),
  KEY `ix_ent_period` (`company_id`,`period`),
  KEY `ix_ent_unit` (`company_id`,`unit_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 الشاشة ٢: توليدُ المستحق — محضرٌ بأحكامٍ ثلاثةٍ مستقلة';

-- ── Table: fin_equity ──
CREATE TABLE `fin_equity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `eq_code` varchar(16) NOT NULL COMMENT 'EQS-000001',
  `period` varchar(10) NOT NULL,
  `component_code` varchar(30) NOT NULL COMMENT 'كودُ بندِ حقوقِ الملكية — 3101 · 3201 …',
  `component_name` varchar(190) NOT NULL DEFAULT '',
  `opening_balance` decimal(20,2) NOT NULL DEFAULT 0.00,
  `additions` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'زياداتُ رأسِ المالِ والأرباح',
  `deductions` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'التوزيعاتُ والخسائر',
  `transfers` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'التحويلُ للاحتياطيات',
  `closing_balance` decimal(20,2) NOT NULL DEFAULT 0.00,
  `computed_closing` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'الافتتاحيُّ + الحركات',
  `balance_ok` tinyint(1) NOT NULL DEFAULT 0 COMMENT '◆ الختاميُّ = الافتتاحيُّ + الحركاتُ أو تُرفض',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `state` enum('generated','superseded') NOT NULL DEFAULT 'generated',
  `supersedes_id` int(10) unsigned DEFAULT NULL,
  `generated_by` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '',
  `idempotency_key` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eqs_idem` (`company_id`,`idempotency_key`),
  KEY `ix_eqs_period` (`company_id`,`period`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 S5: الختاميُّ = الافتتاحيُّ + الحركاتُ لكل بندٍ أو تُرفض';

-- ── Table: fin_event_effects ──
CREATE TABLE `fin_event_effects` (
  `effect_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `effect_type` enum('client_receivable','supplier_accrual','operator_due','project_cost','equip_cost','payment','receipt','settlement','depreciation','tax_return','finance_installment','adjustment_reversal') NOT NULL COMMENT 'FES §4.1: القيمُ الحصرية الاثنتا عشرة',
  `party_type` varchar(16) NOT NULL DEFAULT '' COMMENT 'الطرف — فارغٌ = أثرٌ بلا طرفٍ (تكلفة) · جزءٌ من المفتاح الفريد فلا NULL',
  `party_id` int(11) NOT NULL DEFAULT 0 COMMENT 'معرّفُ الطرف — 0 = بلا طرف · جزءٌ من المفتاح الفريد فلا NULL',
  `contract_line_id` int(11) NOT NULL DEFAULT 0 COMMENT 'بندُ العقد — 0 = بلا بند · جزءٌ من المفتاح الفريد فلا NULL',
  `amount` decimal(18,2) NOT NULL,
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'المعادلُ الموحّد — NULL = سعرٌ غيرُ مُدخَل (معلَن)',
  `status` enum('active','reversed') NOT NULL DEFAULT 'active' COMMENT 'الأثرُ يُبطل بعكس حدثه لا بمحوه',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`effect_id`),
  UNIQUE KEY `uq_effect` (`event_id`,`effect_type`,`party_type`,`party_id`,`contract_line_id`),
  KEY `ix_eff_company_party` (`company_id`,`party_type`,`party_id`),
  KEY `ix_eff_type` (`company_id`,`effect_type`),
  CONSTRAINT `fk_eff_event` FOREIGN KEY (`event_id`) REFERENCES `fin_financial_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='H-12 (FES §3.2): آثارُ الحدث — الحدثُ الواحد قد يولّد آثارًا لعدة أطراف';

-- ── Table: fin_event_grades ──
CREATE TABLE `fin_event_grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `event_id` int(11) NOT NULL COMMENT 'الحدث المالي fin_financial_events.id',
  `grade` enum('provisional','final') NOT NULL DEFAULT 'provisional' COMMENT 'مبدئي: تقديري لا يُقفل عليه ماليًّا · نهائي: مؤكد',
  `reason` varchar(300) DEFAULT NULL COMMENT 'علة الوسم المبدئي (تقدير · بانتظار مستند …)',
  `finalized_at` datetime DEFAULT NULL COMMENT 'لحظة الترقية إلى نهائي',
  `finalized_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_grade` (`event_id`) COMMENT 'درجة واحدة لكل حدث',
  KEY `ix_feg_live` (`company_id`,`grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AC-E01-05: درجة أثر الحدث المالي — المبدئي لا يُقفل عليه';

-- ── Table: fin_event_links ──
CREATE TABLE `fin_event_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `parent_kind` enum('request','unit_record','event','timesheet') NOT NULL,
  `parent_ref` int(10) unsigned NOT NULL,
  `effect_type` enum('revenue_event','supplier_due','employee_due','cost_record','receivable','journal_entry','payment','metric_update','budget_consumption','party_award') NOT NULL,
  `target_table` varchar(40) NOT NULL,
  `target_id` int(10) unsigned DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `void_reason` varchar(60) DEFAULT NULL COMMENT 'سببُ إبطالِ المرساة — الرابطُ باقٍ شاهدًا وevent_id ساقط',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_link_parent_effect` (`company_id`,`parent_kind`,`parent_ref`,`effect_type`),
  KEY `ix_parent` (`company_id`,`parent_kind`,`parent_ref`),
  KEY `ix_target` (`company_id`,`target_table`,`target_id`),
  KEY `ix_event` (`event_id`),
  CONSTRAINT `fk_fel_event` FOREIGN KEY (`event_id`) REFERENCES `fin_financial_events` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_financial_events ──
CREATE TABLE `fin_financial_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `event_no` varchar(30) NOT NULL COMMENT 'يُسنِده الخادم',
  `event_type` enum('revenue','expense','payable','receivable','payroll','settlement','enterprise') NOT NULL COMMENT 'قديم متوافق؛ أحداث العقد = enterprise والدلالة الكاملة في event_key/category — لا توسّع آخر (الحوكمة في سجل الأنواع)',
  `event_key` varchar(64) DEFAULT NULL COMMENT 'Event Type المنقط domain.entity.action (عقد §9) — يحوكم بسجل الأنواع لا بتوسيع ENUM',
  `category` varchar(20) DEFAULT NULL COMMENT 'Event Category (عقد §9): operational/financial/hr/fleet/maintenance/commercial/analytics',
  `source_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','movement','finance','transport','system','sites','tickets','admin') NOT NULL,
  `source_ref` varchar(60) DEFAULT NULL COMMENT 'فاتورة/أمر/مستخلص',
  `entity_type` varchar(40) DEFAULT NULL COMMENT 'Entity (عقد §9 إلزامي): نوع الكيان الموضوع timesheet/mnt_order/… — يفرضه الناشر',
  `entity_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Entity ID (عقد §9 إلزامي): معرّف رقمي حصرًا — لا مفاتيح نصية (ADR-09)',
  `source_line_id` int(11) NOT NULL DEFAULT 0 COMMENT 'H-12 (FES §3.1): سطرُ المستند المصدر — 0 لمستندٍ بلا سطور',
  `source_doc_version` int(11) NOT NULL DEFAULT 1 COMMENT 'H-12: نسخةُ المستند المصدر — النسخةُ الأحدث تُنشئ حدثًا وتعلّم القديمَ Superseded',
  `amount` decimal(16,2) NOT NULL,
  `quantity` decimal(18,4) DEFAULT NULL COMMENT 'الكمية إن كان الحدث كميًا (عقد §9)',
  `unit` varchar(16) DEFAULT NULL COMMENT 'وحدة القياس اللاتينية hour/ton/meter/km/liter/unit (عقد §9)',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `fx_rate` decimal(14,6) DEFAULT NULL COMMENT 'إلى الدولار',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'M-40 (FES §3.3): المعادلُ الموحّد = ROUND(amount × fx_rate, 2) — NULL = سعرٌ غيرُ مُدخَل لتاريخه (معلَن)',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'equipments.id (مرجع مرن)',
  `project_id` int(11) DEFAULT NULL COMMENT 'project.id (مرجع مرن)',
  `contract_id` int(11) DEFAULT NULL COMMENT 'contracts.id (مرجع مرن)',
  `contract_line_id` int(11) DEFAULT NULL COMMENT 'بندُ البيع (P-02 · `client_contract_lines`) — **وُصل مرجعُه في P-09 بعد أن كان وعدًا فارغًا**',
  `supplier_entity_id` int(11) DEFAULT NULL COMMENT 'suppliers.id (مرجع مرن)',
  `customer_entity_id` int(11) DEFAULT NULL COMMENT 'clients.id (مرجع مرن)',
  `operator_employee_id` int(11) DEFAULT NULL COMMENT 'Operator (عقد §9 سياقي): مرجع رقمي إلى employees.id — SSOT الأشخاص',
  `party_type` varchar(16) DEFAULT NULL COMMENT 'H-12 (FES §4.1): الطرفُ الموحّد — customer·supplier·operator·employee·owner_dept',
  `party_id` int(11) DEFAULT NULL COMMENT 'H-12: معرّفُ الطرف في جدوله بحسب party_type',
  `cost_center` varchar(60) DEFAULT NULL,
  `accountant_id` int(11) DEFAULT NULL COMMENT 'fin_accountants.id (مرجع مرن)',
  `state` enum('draft','dept_review','dept_approved','fin_review','audited','approved','posted','settled','rejected','closed') NOT NULL DEFAULT 'draft',
  `event_status` enum('active','reversed') NOT NULL DEFAULT 'active' COMMENT 'محور دورة حياة الناقل (منفصلٌ عن state سير المالية): active افتراضًا · reversed إن نقضه حدثٌ معوِّض',
  `fes_status` enum('Draft','Published','ValidationFailed','UnderReview','ReturnedToSource','Rejected','Approved','PostingFailed','RetryPending','Posted','Reversed','Superseded','CancelledBeforePosting','Closed') NOT NULL DEFAULT 'Draft' COMMENT 'H-12 (FES §7.2): آلةُ حالات الحدث الأربعَ عشرة — يحكمها EventStateMachine حصرًا',
  `reverses_event_id` int(11) DEFAULT NULL COMMENT 'إن كان هذا الحدث معوِّضًا: id الحدث الذي ينقضه (عقد C6 — المنطق مؤجَّل)',
  `occurred_at` datetime DEFAULT NULL COMMENT 'Occurred At (عقد §9 إلزامي): لحظة الوقوع الفعلي UTC — تُميَّز عن created_at',
  `fiscal_period_id` int(11) DEFAULT NULL COMMENT 'H-12: الفترةُ المالية للحدث — تُختم عند النشر، ولا نشرَ في فترةٍ مقفلة (إنفاذُه في M-39)',
  `due_date` date DEFAULT NULL COMMENT 'H-12 (FES §3.1): تاريخُ الاستحقاق — فهرسُ أعمار الذمم',
  `journal_entry_id` int(11) DEFAULT NULL COMMENT 'soft ref (المرحلة 3)',
  `notes` mediumtext DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `event_uuid` char(36) DEFAULT uuid() COMMENT 'معرّف الحدث الكوني (توزيع/تتبّع) — default قاعدي UUID() يُضبط أدناه',
  `root_event_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ADR-15: الحقيقة الأم في ems_business_events — NULL = يدوي/سابق للجذر',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL COMMENT 'H-12 (FES §3.1): معتمِدُ الحدث — تدقيقُ الفاعلين',
  `approved_at` datetime DEFAULT NULL,
  `posted_by` int(11) DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `correlation_id` varchar(64) DEFAULT NULL COMMENT 'معرّف سلسلة الأثر طرفًا لطرف (عقد §9) — يكتبه الناشر K3',
  `causation_id` varchar(64) DEFAULT NULL COMMENT 'H-12 (FES §3.1): معرّفُ الحدث المسبِّب — خيطُ السببية (بجانب correlation_id خيطِ الترابط)',
  `idempotency_key` varchar(64) DEFAULT NULL COMMENT 'مفتاح عطالة الأثر — فريد لكل عملية مصدرية (عقد §9)؛ NULL للصفوف السابقة للعقد',
  `schema_version` smallint(5) unsigned DEFAULT NULL COMMENT 'إصدار مخطط الحدث (عقد §9) — يكتبه الناشر K3',
  `event_version` int(11) NOT NULL DEFAULT 1 COMMENT 'H-12 (FES §7.3): قفلٌ تفاؤلي — كلُّ انتقالٍ يفحصها ويرفعها، والمتزامنان: الأولُ يمضي والثاني Conflict',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Payload (عقد §9 إلزامي): الحمولة التفصيلية JSON — يفرضها الناشر' CHECK (json_valid(`payload`)),
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
  CONSTRAINT `ck_ffe_fx_pair` CHECK (`fx_rate` is null and `base_amount` is null or `fx_rate` is not null and `base_amount` = round(`amount` * `fx_rate`,2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_financial_periods ──
CREATE TABLE `fin_financial_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `fiscal_year` int(11) NOT NULL,
  `period_type` enum('year','month') NOT NULL,
  `period_no` int(11) DEFAULT NULL COMMENT 'شهر 1-12 (NULL لصف السنة)',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `state` enum('planned','open','soft_closed','closed','locked','reopened') NOT NULL DEFAULT 'planned',
  `posting_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `soft_closed_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `reopen_reason` varchar(200) DEFAULT NULL,
  `reopened_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_period` (`company_id`,`fiscal_year`,`period_type`,`period_no`),
  KEY `ix_fin_period_state` (`company_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_funding_facilities ──
CREATE TABLE `fin_funding_facilities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `facility_no` varchar(30) NOT NULL,
  `facility_type` enum('loan','murabaha','lease','bank_guarantee','letter_of_credit','operating_finance') NOT NULL,
  `purpose` enum('equipment','supplier','operational','general') NOT NULL,
  `lender_entity_id` int(11) DEFAULT NULL COMMENT 'suppliers/entities.id (مرجع مرن)',
  `lender_name` varchar(160) DEFAULT NULL COMMENT 'لقطة نصية للممول',
  `principal` decimal(16,2) NOT NULL,
  `profit_rate` decimal(9,4) DEFAULT NULL COMMENT '% أو هامش',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `state` enum('draft','approved','active','settled','closed') NOT NULL DEFAULT 'draft',
  `note` varchar(200) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_fac_no` (`company_id`,`facility_no`),
  KEY `ix_fin_fac_type` (`company_id`,`facility_type`),
  KEY `ix_fin_fac_state` (`company_id`,`state`),
  KEY `ix_fin_fac_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_funding_schedules ──
CREATE TABLE `fin_funding_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `installment_no` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `principal_due` decimal(16,2) NOT NULL DEFAULT 0.00,
  `profit_due` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total_due` decimal(16,2) GENERATED ALWAYS AS (`principal_due` + `profit_due`) STORED,
  `paid_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `state` enum('due','partial','paid','overdue') NOT NULL DEFAULT 'due',
  `event_id` int(11) DEFAULT NULL COMMENT 'حدثُ استحقاق القسط — «أقساطٌ آليةٌ بمرجع الجدول لحظةَ استحقاقها»',
  `accrued_at` datetime DEFAULT NULL COMMENT 'لحظةُ الاعتراف بالاستحقاق — لا تُكتب إلا مع الحدث',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
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
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `kind` enum('realized','unrealized') NOT NULL,
  `source_kind` enum('allocation','revaluation') NOT NULL,
  `source_ref` int(11) NOT NULL COMMENT 'سطرُ التخصيص أو الذمّةُ المُعاد تقييمُها',
  `party_ref` int(11) DEFAULT NULL COMMENT 'العميلُ إن عُرف',
  `from_currency` varchar(8) NOT NULL COMMENT 'العملةُ التي نشأ منها الفرق',
  `functional_currency` varchar(8) NOT NULL COMMENT '**العملةُ الوظيفية** — وفيها وحدَها يُقاس الفرق',
  `amount` decimal(18,2) NOT NULL COMMENT 'موجبٌ ربحُ صرفٍ · سالبٌ خسارتُه',
  `rate_from` decimal(20,8) DEFAULT NULL,
  `rate_to` decimal(20,8) DEFAULT NULL,
  `occurred_on` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL COMMENT 'وصلُ الدفتر — **مؤجَّلٌ إلى H-09**',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fxd_source` (`kind`,`source_kind`,`source_ref`),
  KEY `ix_fxd_lookup` (`company_id`,`kind`,`occurred_on`),
  CONSTRAINT `ck_fxd_amount` CHECK (`amount` <> 0),
  CONSTRAINT `ck_fxd_currency` CHECK (`functional_currency` <> _utf8mb4'' and `from_currency` <> _utf8mb4'')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_fx_rates ──
CREATE TABLE `fin_fx_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل المستأجر',
  `currency_code` varchar(8) NOT NULL COMMENT 'العملة المُسعَّرة',
  `rate_to_base` decimal(20,8) NOT NULL COMMENT 'كم وحدةَ أساسٍ يساوي واحدٌ منها — base = ROUND(amount × rate, 2)',
  `effective_from` date NOT NULL COMMENT 'أولُ يومٍ يسري فيه — والسعرُ النافذ آخرُ سعرٍ سابقٍ للتاريخ أو مساوٍ',
  `source` varchar(32) DEFAULT NULL COMMENT 'مصدرُ السعر: system · بنك مركزي · قرارٌ إداري',
  `note` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fx_currency_date` (`company_id`,`currency_code`,`effective_from`) COMMENT 'سعرٌ واحدٌ لكل (عملة × تاريخ سريان) — التصحيحُ تعديلُ الصفّ لا صفٌّ ثانٍ',
  KEY `ix_fx_lookup` (`company_id`,`currency_code`,`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أسعارُ الصرف بتواريخها — السعرُ لحظةَ الحدث (FES-01 §3.1)';

-- ── Table: fin_internal_allocations ──
CREATE TABLE `fin_internal_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `alloc_type` enum('internal_allocation','intercompany_settlement') NOT NULL,
  `from_center_id` int(11) DEFAULT NULL COMMENT 'fin_cost_centers',
  `to_center_id` int(11) DEFAULT NULL COMMENT 'fin_cost_centers',
  `from_entity_id` int(11) DEFAULT NULL COMMENT 'entities (مرجع مرن)',
  `to_entity_id` int(11) DEFAULT NULL COMMENT 'entities (مرجع مرن)',
  `basis` varchar(120) DEFAULT NULL COMMENT 'ساعات/استخدام/عدد',
  `amount` decimal(16,2) NOT NULL,
  `period_id` int(11) DEFAULT NULL COMMENT 'fin_financial_periods (soft)',
  `event_id` int(11) DEFAULT NULL,
  `state` enum('draft','approved','posted') NOT NULL DEFAULT 'draft',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `entry_no` varchar(30) NOT NULL COMMENT 'يُسنِده الخادم',
  `event_id` int(11) DEFAULT NULL COMMENT 'fin_financial_events.id (مرجع مرن)',
  `posting_date` date NOT NULL,
  `txn_date` date NOT NULL DEFAULT curdate() COMMENT 'M-38: تاريخُ الحركة الفعلي (بجانب posting_date تاريخِ الترحيل)',
  `request_no` varchar(64) DEFAULT NULL COMMENT 'M-38: خيطُ الطلب — رقمُ الطلب المالي المولِّد إن وُجد',
  `request_owner` varchar(64) DEFAULT NULL COMMENT 'M-38: صاحبُ الطلب (اسمُ الرافع لحظةَ التوليد — لقطة)',
  `request_group` varchar(64) DEFAULT NULL COMMENT 'M-38: مجموعةُ الطلب (request_type)',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG' COMMENT 'M-38: عملةُ القيد (افتراضُ SDG يطابق نمطَ fin_financial_events)',
  `fx_rate` decimal(18,6) DEFAULT NULL COMMENT 'M-38: سعرُ الصرف إلى عملة الأساس يومَ الحركة — NULL = سعرٌ غيرُ مُدخَل (فجوةٌ معلَنة)',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'M-38: المعادلُ الموحّد بعملة الأساس = ROUND(total_debit × fx_rate, 2)',
  `total_debit` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total_credit` decimal(16,2) NOT NULL DEFAULT 0.00,
  `memo` varchar(200) DEFAULT NULL,
  `state` enum('draft','posted','reversed') NOT NULL DEFAULT 'draft',
  `posted_by` int(11) DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_entry_no` (`company_id`,`entry_no`),
  KEY `ix_fin_entry_state` (`company_id`,`state`),
  KEY `ix_fin_entry_event` (`event_id`),
  KEY `ix_fin_entry_deleted` (`is_deleted`),
  KEY `ix_je_txn_date` (`company_id`,`txn_date`),
  KEY `ix_je_request_no` (`company_id`,`request_no`),
  CONSTRAINT `ck_je_balanced` CHECK (round(`total_debit`,2) = round(`total_credit`,2)),
  CONSTRAINT `ck_je_fx_pair` CHECK (`fx_rate` is null and `base_amount` is null or `fx_rate` is not null and `base_amount` = round(`total_debit` * `fx_rate`,2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_journal_lines ──
CREATE TABLE `fin_journal_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL COMMENT 'fin_chart_of_accounts.id',
  `legacy_account_id` int(11) DEFAULT NULL COMMENT '◆ التصنيفُ الأصليُّ قبل ترحيلِ الشجرة — فالترحيلُ يُعكس ولا يُمحى',
  `posting_rule_code` varchar(16) NOT NULL DEFAULT '' COMMENT 'صفُّ مصفوفةِ الترحيلِ الذي اشتقَّ الحسابَ — ولا يُختار يدويًّا',
  `debit` decimal(16,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(16,2) NOT NULL DEFAULT 0.00,
  `project_id` int(11) DEFAULT NULL COMMENT 'project.id (بُعد، مرجع مرن)',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'equipments.id (بُعد، مرجع مرن)',
  `site_id` int(11) DEFAULT NULL COMMENT 'D3 الموقعُ — إلزاميٌّ في القيودِ الميدانية',
  `cost_center_id` int(11) DEFAULT NULL COMMENT 'M-38: مركزُ التكلفة من الدليل — بديلُ النص الحر cost_center',
  `cost_center` varchar(60) DEFAULT NULL,
  `counterparty_type` varchar(24) NOT NULL DEFAULT '' COMMENT 'D6 نوعُ الطرفِ: client · supplier · employee · financier · partner',
  `counterparty_id` int(11) DEFAULT NULL COMMENT '◆ D6 يحلُّ محلَّ أسماءِ الأشخاصِ في الشجرة (R2)',
  `business_model` varchar(16) NOT NULL DEFAULT '' COMMENT 'D7 نموذجُ العمل: hour · ton · meter — يُنتج ربحيةَ كل نموذج',
  `contract_id` int(11) DEFAULT NULL COMMENT 'D8 العقدُ — إلزاميٌّ في الإيرادِ والجزاءات',
  `contract_type_code` varchar(12) NOT NULL DEFAULT '' COMMENT 'D9 نوعُ العقد: EC-01..08 للموظفينَ · FC-01..10 للممولين',
  `memo` varchar(200) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_jl_entry` (`company_id`,`entry_id`),
  KEY `ix_fin_jl_account` (`company_id`,`account_id`),
  KEY `fk_fin_jl_entry` (`entry_id`),
  KEY `fk_fin_jl_acc` (`account_id`),
  KEY `ix_jl_cost_center` (`company_id`,`cost_center_id`),
  KEY `fk_fin_jl_cc` (`cost_center_id`),
  KEY `ix_jl_dims` (`company_id`,`contract_id`,`business_model`),
  KEY `ix_jl_party` (`company_id`,`counterparty_type`,`counterparty_id`),
  KEY `ix_jl_legacy` (`legacy_account_id`),
  CONSTRAINT `fk_fin_jl_acc` FOREIGN KEY (`account_id`) REFERENCES `fin_chart_of_accounts` (`id`),
  CONSTRAINT `fk_fin_jl_cc` FOREIGN KEY (`cost_center_id`) REFERENCES `fin_cost_centers` (`id`),
  CONSTRAINT `fk_fin_jl_entry` FOREIGN KEY (`entry_id`) REFERENCES `fin_journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_maint_provision_rules ──
CREATE TABLE `fin_maint_provision_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `equipment_id` int(10) unsigned DEFAULT NULL COMMENT 'معدةٌ بعينها — NULL = القاعدةُ لنوعها أو الأعمّ',
  `equipment_type` int(10) unsigned DEFAULT NULL COMMENT 'نوعُ المعدة — NULL مع NULL أعلاه = الأعمّ',
  `basis` enum('hour','unit') NOT NULL COMMENT '«أساسُ المخصص (ساعة/وحدة)» — نصُّ #23',
  `rate` decimal(14,4) NOT NULL COMMENT 'معدلُ المخصص لوحدة الأساس',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG' COMMENT 'لا جمعَ عملتين في رقم',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `state` enum('active','ended') NOT NULL DEFAULT 'active',
  `note` varchar(200) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mprov_rule` (`company_id`,`equipment_id`,`equipment_type`,`basis`,`effective_from`),
  KEY `ix_mprov_rule_lookup` (`company_id`,`state`,`effective_from`,`effective_to`),
  CONSTRAINT `ck_mprov_rate` CHECK (`rate` > 0),
  CONSTRAINT `ck_mprov_span` CHECK (`effective_to` is null or `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_maint_provisions ──
CREATE TABLE `fin_maint_provisions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `equipment_id` int(10) unsigned NOT NULL,
  `period_ref` varchar(10) NOT NULL COMMENT 'YYYY-MM',
  `rule_id` int(10) unsigned DEFAULT NULL COMMENT 'القاعدةُ التي احتُسب بها — «لا كتابةَ يدويةً»',
  `basis` enum('hour','unit') NOT NULL,
  `qty` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT 'من **وحدات المعدة المعتمدة** في الفترة',
  `rate` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '**محسوبٌ لا مُدخَل**: الكميةُ × المعدل',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `event_id` int(11) DEFAULT NULL,
  `basis_json` text DEFAULT NULL,
  `source` enum('screen','cron') NOT NULL DEFAULT 'screen',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_maint_provision` (`company_id`,`equipment_id`,`period_ref`) COMMENT '«بمفتاح (المعدة × الفترة)» بنيويًّا',
  KEY `ix_mprov_period` (`company_id`,`period_ref`),
  KEY `ix_mprov_event` (`event_id`),
  CONSTRAINT `ck_mprov_amount` CHECK (`amount` >= 0),
  CONSTRAINT `ck_mprov_rule_src` CHECK (`amount` = 0 or `rule_id` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_margin_analysis ──
CREATE TABLE `fin_margin_analysis` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `run_code` varchar(16) NOT NULL COMMENT 'MRG-000001',
  `period` varchar(7) NOT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `unit_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'الوحدةُ إن كان الحسابُ بواقعة',
  `revenue_recognized` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الإيرادُ المعترَف به',
  `cost_operators` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '(حساس)',
  `cost_fuel` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cost_maintenance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cost_inventory` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cost_transfer` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cost_financing` decimal(18,2) NOT NULL DEFAULT 0.00,
  `depreciation` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `margin` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '(حساس)',
  `margin_pct` decimal(9,4) DEFAULT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `state` enum('computed','superseded') NOT NULL DEFAULT 'computed',
  `supersedes_id` int(10) unsigned DEFAULT NULL COMMENT 'إعادةُ احتسابٍ بعد تصحيح — نسخةٌ تشير لسابقتها',
  `computed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '',
  `idempotency_key` varchar(96) NOT NULL COMMENT '(الفترة×العقد×الوحدة×النسخة)',
  `cost_center_id` int(11) DEFAULT NULL,
  `fx_note` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mrg_code` (`company_id`,`run_code`),
  UNIQUE KEY `uq_mrg_idem` (`company_id`,`idempotency_key`),
  KEY `ix_mrg_scope` (`company_id`,`period`,`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 الشاشة ١٤: الهامشُ محسوبٌ من الاعترافات الثلاثة — وتظهر العقودُ الخاسرة';

-- ── Table: fin_notifications ──
CREATE TABLE `fin_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `target_level` enum('dept_accountant','dept_manager','finance_reviewer','finance_manager','treasurer','reader','all') NOT NULL DEFAULT 'all' COMMENT 'المستوى المستهدف (فصل الواجبات)',
  `target_user_id` int(11) DEFAULT NULL COMMENT 'مستخدم بعينه (اختياري)',
  `title` varchar(200) NOT NULL,
  `link` varchar(200) DEFAULT NULL COMMENT 'شاشة الفتح',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_ntf_target` (`company_id`,`target_level`,`is_read`),
  KEY `ix_fin_ntf_created` (`company_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_obl_alert_log ──
CREATE TABLE `fin_obl_alert_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `alert_code` varchar(6) NOT NULL,
  `obligation_id` bigint(20) unsigned DEFAULT NULL,
  `schedule_id` bigint(20) unsigned DEFAULT NULL,
  `subject_ref` varchar(120) NOT NULL DEFAULT '',
  `to_user_id` int(10) unsigned DEFAULT NULL,
  `to_role_id` int(10) unsigned DEFAULT NULL,
  `work_item_id` bigint(20) unsigned DEFAULT NULL,
  `fired_at` datetime NOT NULL,
  `due_at` datetime DEFAULT NULL COMMENT 'مهلةُ التصرفِ — بعدها يُصعَّد للمخاطر',
  `state` enum('open','acted','escalated','closed') NOT NULL DEFAULT 'open',
  `escalated_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fire` (`company_id`,`alert_code`,`subject_ref`),
  KEY `ix_state` (`company_id`,`state`,`due_at`),
  KEY `ix_obl` (`obligation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-22 — سجلُّ التنبيهاتِ وتصعيدِ المُهمَل';

-- ── Table: fin_obl_alerts ──
CREATE TABLE `fin_obl_alerts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code` varchar(6) NOT NULL COMMENT 'AL-01..AL-12',
  `title` varchar(200) NOT NULL,
  `fires_when` varchar(300) NOT NULL DEFAULT '',
  `destination` varchar(300) NOT NULL DEFAULT '',
  `risk_if_ignored` varchar(400) NOT NULL DEFAULT '',
  `lead_days` smallint(5) unsigned NOT NULL DEFAULT 7 COMMENT 'مهلةُ الإطلاقِ قبلَ الحدث',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_al` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-22 — التنبيهاتُ الاثنا عشر';

-- ── Table: fin_obl_avoidance ──
CREATE TABLE `fin_obl_avoidance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_kind` varchar(40) NOT NULL COMMENT 'client · supplier · lease · employee · financing · po …',
  `contract_ref` varchar(120) NOT NULL,
  `contract_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `cancellable` tinyint(1) NOT NULL COMMENT '◆ أالعقدُ قابلٌ للإلغاءِ من طرفنا؟',
  `cancel_cost` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ تكلفةُ الإلغاءِ أو الشرطُ الجزائي',
  `unavoidable` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ المبلغُ غيرُ القابلِ للتجنب',
  `unavoidable_pct` decimal(6,3) NOT NULL DEFAULT 0.000 COMMENT '◆ نسبتُه من قيمةِ العقد',
  `recognition_candidate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '◆ أمرشَّحٌ للاعتراف؟',
  `volume_obligation` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ التزامُ الحجمِ — يسقط بالعجز',
  `penalty_obligation` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ التزامُ الجزاءِ — لا يسقط',
  `special_standard` varchar(200) NOT NULL DEFAULT '' COMMENT '◆ المعيارُ الخاصُّ الموجِبُ للاعتراف',
  `onerous` tinyint(1) NOT NULL DEFAULT 0 COMMENT '◆ أعقدٌ مُثقِلٌ؟',
  `expected_benefit` decimal(18,2) DEFAULT NULL COMMENT 'المنافعُ المتوقعةُ — يُقاس بها الإثقال',
  `verdict` enum('disclose_only','disclose_with_penalty','recognition_candidate','recognize','onerous') NOT NULL COMMENT '◆ نتيجةُ اختبارِ التجنب',
  `decided_by` int(10) unsigned NOT NULL COMMENT '◆ ومن قرَّرها',
  `decided_at` datetime NOT NULL COMMENT '◆ تاريخُ نتيجةِ الاختبار',
  `next_review_at` date DEFAULT NULL COMMENT '◆ المراجعةُ القادمةُ للنتيجة',
  `steps_json` varchar(900) NOT NULL DEFAULT '' COMMENT 'أثرُ الخطواتِ الخمسِ بترتيبها',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract` (`company_id`,`contract_kind`,`contract_ref`),
  KEY `ix_verdict` (`company_id`,`verdict`),
  KEY `ix_review` (`next_review_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-5/§4-6 — اختبارُ التجنبِ بأعمدتِه الاثني عشرَ الإلزامية';

-- ── Table: fin_obl_avoidance_tests ──
CREATE TABLE `fin_obl_avoidance_tests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code` varchar(6) NOT NULL COMMENT 'AV-1..AV-5',
  `seq` tinyint(3) unsigned NOT NULL COMMENT 'تُطبَّق بالترتيبِ ولا تُقفز',
  `question` varchar(300) NOT NULL,
  `outcome` varchar(600) NOT NULL DEFAULT '',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_av` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-5 — اختبارُ التجنبِ الخماسي';

-- ── Table: fin_obl_layers ──
CREATE TABLE `fin_obl_layers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code` varchar(4) NOT NULL COMMENT 'L1 · L2 · L3',
  `seq` tinyint(3) unsigned NOT NULL,
  `title` varchar(120) NOT NULL,
  `birth` varchar(300) NOT NULL DEFAULT '' COMMENT 'متى تنشأ',
  `rule_text` varchar(400) NOT NULL DEFAULT '',
  `sides` varchar(500) NOT NULL DEFAULT '' COMMENT 'أثرُها على جانبي الإيرادِ والمصروف',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_l` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-11 — الطبقاتُ الثلاثُ للاعتراف';

-- ── Table: fin_obl_recognition ──
CREATE TABLE `fin_obl_recognition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `contract_kind` varchar(120) NOT NULL COMMENT 'نوعُ العقدِ كما تسميه الوثيقة',
  `standard` varchar(200) NOT NULL DEFAULT '' COMMENT 'المعيارُ الحاكم',
  `trigger_text` varchar(300) NOT NULL DEFAULT '' COMMENT 'متى يتحقق',
  `layers_text` varchar(700) NOT NULL DEFAULT '' COMMENT 'الطبقاتُ الثلاثُ لهذا النوع',
  `guard_text` varchar(400) NOT NULL DEFAULT '',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rec` (`company_id`,`contract_kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-12 — شرطُ الاعترافِ بمعيارِ كلِّ نوع';

-- ── Table: fin_obl_register ──
CREATE TABLE `fin_obl_register` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `obligation_no` varchar(40) NOT NULL,
  `ob_type` varchar(6) NOT NULL COMMENT 'OB-01..OB-08',
  `side` enum('payable','receivable') NOT NULL DEFAULT 'payable' COMMENT 'SY-01 — القاعدةُ نفسُها على الجانبين والفرقُ في الاتجاه',
  `contract_kind` varchar(40) NOT NULL,
  `contract_ref` varchar(120) NOT NULL,
  `counterparty` varchar(200) NOT NULL DEFAULT '',
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `accounting_periods` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT '◆ عددُ الفتراتِ المحاسبية',
  `contract_periods` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT '◆ عددُ الفتراتِ التعاقدية',
  `proration_basis` varchar(60) NOT NULL DEFAULT 'daily' COMMENT '◆ أساسُ حسابِ الكسر',
  `project_id` int(10) unsigned DEFAULT NULL,
  `site_id` int(10) unsigned DEFAULT NULL,
  `equipment_id` int(10) unsigned DEFAULT NULL,
  `cost_center` varchar(60) NOT NULL DEFAULT '',
  `party_type` varchar(16) NOT NULL DEFAULT '',
  `party_id` int(10) unsigned DEFAULT NULL,
  `dims_json` varchar(400) NOT NULL DEFAULT '' COMMENT 'الأبعادُ التسعةُ كما وُرِّثت',
  `state` enum('active','superseded','terminated','closed') NOT NULL DEFAULT 'active',
  `supersedes_id` bigint(20) unsigned DEFAULT NULL COMMENT 'OR-07 — الجدولُ القديمُ يُغلق ويشير إليه الجديد',
  `amendment_ref` varchar(120) NOT NULL DEFAULT '',
  `terminated_at` date DEFAULT NULL,
  `generated_at` datetime NOT NULL,
  `generated_by` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_no` (`company_id`,`obligation_no`),
  KEY `ix_contract` (`company_id`,`contract_kind`,`contract_ref`,`state`),
  KEY `ix_type` (`ob_type`,`state`),
  KEY `ix_super` (`supersedes_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-16 — سجلُّ الالتزاماتِ المولَّدةِ عند نفاذِ العقد';

-- ── Table: fin_obl_rules ──
CREATE TABLE `fin_obl_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `family` enum('OR','SY','AR','SR','IN') NOT NULL COMMENT 'الالتزام · التناظر · الاستحقاق · المورد · التوريث',
  `code` varchar(8) NOT NULL,
  `rule_text` varchar(700) NOT NULL,
  `accept_test` varchar(400) NOT NULL DEFAULT '',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_r` (`company_id`,`code`),
  KEY `ix_fam` (`family`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-13/4-16/4-19/4-20/4-21 — قواعدُ المحرّك';

-- ── Table: fin_obl_schedule ──
CREATE TABLE `fin_obl_schedule` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `obligation_id` bigint(20) unsigned NOT NULL,
  `period_no` smallint(5) unsigned NOT NULL COMMENT 'تسلسلُ الفترةِ داخلَ الجدول',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `due_date` date NOT NULL COMMENT 'OR-02 — بيومِه لا شهرًا مجملًا',
  `is_partial` tinyint(1) NOT NULL DEFAULT 0 COMMENT '◆ أفترةٌ كسرية؟',
  `partial_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `month_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `proration_basis` varchar(60) NOT NULL DEFAULT '' COMMENT '◆ أساسُ حسابِ الكسر',
  `l1_commitment` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ L1 الارتباطُ — القيمةُ الكلية',
  `l1_remaining` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ الارتباطُ المتبقي غيرُ المنفَّذ',
  `l2_recognized` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ L2 المعترَفُ به في الفترة',
  `l2_cumulative` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ المعترَفُ به تراكميًّا',
  `l3_open` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ L3 الذمةُ القائمة',
  `settled` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ المسدَّدُ أو المحصَّل',
  `gap_l1_l2` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ الفرقُ بين الارتباطِ والمعترَفِ به',
  `recognition_rule` varchar(300) NOT NULL DEFAULT '' COMMENT '◆ شرطُ الاعترافِ المطبَّقُ ومعيارُه',
  `term_class` enum('short','long') NOT NULL DEFAULT 'short' COMMENT '◆ التصنيفُ قصيرٌ أو طويل',
  `reclassified_at` datetime DEFAULT NULL,
  `state` enum('scheduled','recognized','invoiced','settled','overdue','moved_to_payables','closed','cancelled') NOT NULL DEFAULT 'scheduled',
  `moved_at` datetime DEFAULT NULL,
  `close_reason` varchar(300) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_period` (`obligation_id`,`period_no`),
  KEY `ix_due` (`company_id`,`due_date`,`state`),
  KEY `ix_term` (`company_id`,`term_class`,`state`),
  KEY `ix_obl` (`obligation_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-13 — جدولُ الاستحقاقاتِ بأعمدتِه الثلاثةَ عشرَ الإلزامية';

-- ── Table: fin_obl_types ──
CREATE TABLE `fin_obl_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code` varchar(6) NOT NULL COMMENT 'OB-01..OB-08',
  `title` varchar(160) NOT NULL,
  `born_when` varchar(200) NOT NULL DEFAULT '',
  `accounts` varchar(200) NOT NULL DEFAULT '',
  `formula` varchar(400) NOT NULL DEFAULT '',
  `term_rule` varchar(400) NOT NULL DEFAULT '' COMMENT 'قصيرٌ أو طويلٌ بحسبِ ماذا',
  `posts_entry` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'OR-10 — صفرٌ دائمًا: المحرّكُ لا يُنشئ قيدًا',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ob` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-16 — أنواعُ الالتزامِ الثمانية';

-- ── Table: fin_operator_pay ──
CREATE TABLE `fin_operator_pay` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `pay_mode` enum('salary','due') NOT NULL DEFAULT 'salary',
  `note` varchar(160) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_op_pay` (`company_id`,`employee_id`),
  KEY `ix_mode` (`company_id`,`pay_mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_payments ──
CREATE TABLE `fin_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `payment_no` varchar(30) NOT NULL,
  `direction` enum('disbursement','collection') NOT NULL,
  `party_type` enum('supplier','customer','employee','other') NOT NULL,
  `party_ref` int(11) DEFAULT NULL,
  `method` enum('cash','bank','transfer','cheque') NOT NULL DEFAULT 'bank',
  `bank_ref` varchar(120) DEFAULT NULL COMMENT 'المرجعُ البنكيُّ أو السند — إلزاميٌّ للتحصيل (ENT-03 §4)',
  `received_on` date DEFAULT NULL COMMENT 'تاريخُ القبض — جزءُ مفتاح منع الازدواج',
  `amount` decimal(16,2) NOT NULL,
  `allocated_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Σ التخصيصات — يُحرَس بـCHECK فلا يتجاوز مبلغ السند',
  `unallocated_amount` decimal(18,2) GENERATED ALWAYS AS (`amount` - `allocated_amount`) STORED COMMENT '**رصيدٌ ظاهر** — لا رقمٌ في رسالةٍ تختفي',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `fx_rate` decimal(18,6) DEFAULT NULL COMMENT 'M-40: سعرُ الصرف النافذ يومَ الدفع',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'M-40: المعادلُ الموحّد للدفعة',
  `event_id` int(11) DEFAULT NULL,
  `due_id` int(11) DEFAULT NULL,
  `receivable_id` int(11) DEFAULT NULL,
  `memo` varchar(200) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `state` enum('draft','approved','executed','reconciled') NOT NULL DEFAULT 'draft',
  `executed_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_pay_no` (`company_id`,`payment_no`),
  UNIQUE KEY `uq_collection_ref` (`company_id`,`bank_ref`,`amount`,`received_on`),
  KEY `ix_fin_pay_dir` (`company_id`,`direction`),
  KEY `ix_fin_pay_state` (`company_id`,`state`),
  KEY `ix_fin_pay_deleted` (`is_deleted`),
  CONSTRAINT `ck_pay_fx_pair` CHECK (`fx_rate` is null and `base_amount` is null or `fx_rate` is not null and `base_amount` = round(`amount` * `fx_rate`,2)),
  CONSTRAINT `ck_fp_allocated` CHECK (`allocated_amount` >= 0 and `allocated_amount` <= `amount`),
  CONSTRAINT `ck_collection_bank_ref` CHECK (`direction` <> 'collection' or `bank_ref` is not null and `bank_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_posting_matrix ──
CREATE TABLE `fin_posting_matrix` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `rule_code` varchar(16) NOT NULL COMMENT 'OPS · SIT · MNT · WRK · TRP · PRC · INV · SAL · SUP · FLT · CAP · HRM · GOV …',
  `dept_ar` varchar(120) NOT NULL,
  `source_event` varchar(190) NOT NULL COMMENT 'الحدثُ المصدرُ الذي يولّد القيد',
  `revenue_accounts` varchar(190) NOT NULL DEFAULT '' COMMENT 'أكوادُ الإيرادِ بحسب النموذج',
  `cost_accounts` varchar(190) NOT NULL DEFAULT '' COMMENT 'أكوادُ التكلفة',
  `required_dims` varchar(64) NOT NULL DEFAULT '' COMMENT 'الأبعادُ الإلزاميةُ لهذا الصف',
  `gate_ar` varchar(190) NOT NULL DEFAULT '' COMMENT 'البوابةُ قبل الترحيل',
  `governing_rule` varchar(400) NOT NULL DEFAULT '' COMMENT 'الحكمُ الحاكمُ نصًّا',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `version_no` smallint(5) unsigned NOT NULL DEFAULT 1 COMMENT 'العكس: المصفوفةُ السابقةُ تُستعاد بنسختها',
  `updated_by` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pmatrix` (`company_id`,`rule_code`,`version_no`),
  KEY `ix_pmatrix_active` (`company_id`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MAP-7 الورقة 37: الحسابُ يُشتق من نوعِ الواقعةِ ونموذجِ العملِ ونوعِ العقد — ولا يُختار يدويًّا';

-- ── Table: fin_project_pl ──
CREATE TABLE `fin_project_pl` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `pl_code` varchar(16) NOT NULL COMMENT 'PPL-000001',
  `project_id` int(11) NOT NULL,
  `period` varchar(10) NOT NULL,
  `revenue_total` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'من 41 و42 بالبُعد D2',
  `direct_cost_total` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'من 51 بالبُعد D2',
  `allocated_overhead` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'حصةٌ محمَّلةٌ من 52 بأساسِ تحميلٍ معلَن',
  `allocation_basis` varchar(190) NOT NULL DEFAULT '' COMMENT '◆ أساسُ التحميلِ يُعلَن ولا يُخترع',
  `gross_margin` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'M2 على المشروع',
  `operating_profit` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'M3 على المشروع',
  `margin_pct` decimal(9,4) DEFAULT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `lines_json` mediumtext DEFAULT NULL COMMENT 'بنودُ القائمةِ بأكوادها — أساسُ التعمّق',
  `state` enum('generated','superseded') NOT NULL DEFAULT 'generated',
  `supersedes_id` int(10) unsigned DEFAULT NULL,
  `generated_by` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL COMMENT 'المحاسبُ يولّد والمديرُ الماليُّ يراجع',
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '',
  `idempotency_key` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ppl_code` (`company_id`,`pl_code`),
  UNIQUE KEY `uq_ppl_idem` (`company_id`,`idempotency_key`),
  KEY `ix_ppl_proj` (`company_id`,`project_id`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 S3: قائمةُ دخلِ المشروعِ تُنتَج من الأبعادِ لا من شجرةٍ منفصلة';

-- ── Table: fin_quality_kpis ──
CREATE TABLE `fin_quality_kpis` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `code` varchar(12) NOT NULL COMMENT 'KPI-01..KPI-12',
  `seq` tinyint(3) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `threshold` varchar(80) NOT NULL DEFAULT '' COMMENT 'حدُّه',
  `owner_role` varchar(120) NOT NULL DEFAULT '' COMMENT 'مالكُه',
  `cadence` varchar(60) NOT NULL DEFAULT '' COMMENT 'دوريةُ قياسه',
  `source_sql` varchar(500) NOT NULL DEFAULT '' COMMENT 'FCTRL-0047 — محسوبٌ من القيودِ لا من إدخالٍ يدوي',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kpi` (`company_id`,`code`),
  KEY `ix_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-CTRL-01 §4-3 — مؤشراتُ جودةِ المحاسبةِ الاثنا عشر';

-- ── Table: fin_ratio_targets ──
CREATE TABLE `fin_ratio_targets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `ratio_code` varchar(12) NOT NULL COMMENT 'FR-01..FR-44',
  `group_code` varchar(12) NOT NULL DEFAULT '' COMMENT 'RG-1..RG-11',
  `name_ar` varchar(190) NOT NULL,
  `name_en` varchar(190) NOT NULL DEFAULT '',
  `formula_ar` varchar(400) NOT NULL DEFAULT '' COMMENT 'الصيغةُ نصًّا كما في الوثيقة',
  `numerator_codes` varchar(190) NOT NULL DEFAULT '' COMMENT 'أكوادُ البسطِ من الشجرة',
  `denominator_codes` varchar(190) NOT NULL DEFAULT '' COMMENT 'أكوادُ المقام',
  `unit_ar` varchar(24) NOT NULL DEFAULT '' COMMENT 'مرة · ٪ · يوم · عملة',
  `warn_op` enum('lt','gt','lte','gte','none') NOT NULL DEFAULT 'none' COMMENT 'اتجاهُ حدِّ الإنذار',
  `warn_value` decimal(18,4) DEFAULT NULL COMMENT 'حدُّ الإنذار',
  `critical_value` decimal(18,4) DEFAULT NULL COMMENT 'الحدُّ الحرج',
  `target_value` decimal(18,4) DEFAULT NULL COMMENT 'الهدفُ المعتمد',
  `limit_text` varchar(190) NOT NULL DEFAULT '' COMMENT 'نصُّ الحدِّ من الوثيقة',
  `cadence` varchar(24) NOT NULL DEFAULT 'شهريًّا' COMMENT 'يوميًّا · أسبوعيًّا · شهريًّا · ربعَ سنويّ',
  `owner_role` varchar(120) NOT NULL DEFAULT '' COMMENT '◆ لكل نسبةٍ مالكٌ — ولا نسبةَ بلا حد',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `approved_by` int(11) DEFAULT NULL COMMENT '◆ نائبُ الرئيس للشؤون المالية والاستثمار',
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'الحدُّ السابقُ — والعكسُ إعادتُه بقرار',
  `version_no` smallint(5) unsigned NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rtarget` (`company_id`,`ratio_code`,`version_no`),
  KEY `ix_rtarget_grp` (`company_id`,`group_code`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 fin.ratio.target: لكل نسبةٍ حدُّ إنذارٍ وحدٌّ حرجٌ ومالكٌ ودورية';

-- ── Table: fin_ratio_values ──
CREATE TABLE `fin_ratio_values` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `ratio_code` varchar(12) NOT NULL,
  `period` varchar(10) NOT NULL COMMENT 'YYYY-MM أو YYYY-Qn أو YYYY',
  `scope_kind` varchar(16) NOT NULL DEFAULT 'company' COMMENT 'company · project · contract · equipment',
  `scope_ref` varchar(40) NOT NULL DEFAULT '' COMMENT 'قيمةُ البُعدِ عند النطاقِ غيرِ الشركة',
  `numerator_value` decimal(20,4) DEFAULT NULL COMMENT 'قيمةُ البسطِ المحسوبةُ من القيود',
  `denominator_value` decimal(20,4) DEFAULT NULL,
  `result_value` decimal(20,4) DEFAULT NULL COMMENT 'النتيجة — والمقامُ صفرٌ يعطي NULL لا صفرًا كاذبًا',
  `unit_ar` varchar(24) NOT NULL DEFAULT '',
  `status_flag` enum('ok','warn','critical','unmeasured') NOT NULL DEFAULT 'unmeasured' COMMENT '◆ unmeasured حين يغيب المقام — شرطةٌ لا صفر',
  `trend_direction` enum('up','down','flat','na') NOT NULL DEFAULT 'na',
  `source_note` varchar(255) NOT NULL DEFAULT '' COMMENT 'مصدرُ الحساب: أكوادُ الشجرةِ والفترة',
  `entries_count` int(11) NOT NULL DEFAULT 0 COMMENT 'عددُ القيودِ المكوِّنةِ — أساسُ التعمّق',
  `computed_by` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `supersedes_id` int(10) unsigned DEFAULT NULL COMMENT 'إعادةُ الحسابِ نسخةٌ تشير لسابقتها',
  `state` enum('computed','superseded') NOT NULL DEFAULT 'computed',
  `idempotency_key` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rval` (`company_id`,`idempotency_key`),
  KEY `ix_rval_scope` (`company_id`,`ratio_code`,`period`,`state`),
  KEY `ix_rval_flag` (`company_id`,`status_flag`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-10 fin.ratio.compute: النسبُ محسوبةٌ من القيودِ لا من إدخالٍ يدوي';

-- ── Table: fin_reason_codes ──
CREATE TABLE `fin_reason_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `code` varchar(60) NOT NULL,
  `text_ar` varchar(200) NOT NULL,
  `kind` enum('reject','missing_doc','budget','credit','variance','audit','other') NOT NULL DEFAULT 'reject',
  `needs_doc` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reason` (`company_id`,`code`),
  KEY `ix_kind` (`kind`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 BR-03 — رموزُ الأسبابِ المحكومة';

-- ── Table: fin_receivables ──
CREATE TABLE `fin_receivables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `customer_entity_id` int(11) NOT NULL COMMENT 'clients.id (مرجع مرن)',
  `doc_type` enum('invoice','statement') NOT NULL,
  `doc_ref` varchar(60) DEFAULT NULL,
  `source_doc_id` int(10) unsigned DEFAULT NULL COMMENT 'INJ-0036: معرِّفُ المستندِ المعتمَد — tax_invoices.id أو fin_client_statements.id حسب doc_type',
  `legacy_no_ref` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'موروثٌ بلا مستندٍ مقابل — يُعلَن ولا يُمحى (نمط M-11)',
  `project_id` int(11) DEFAULT NULL,
  `amount` decimal(16,2) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT '' COMMENT 'عملةُ الذمّة — كانت مجهولةً قبل P-08',
  `fx_rate_recognized` decimal(20,8) DEFAULT NULL COMMENT 'سعرُ الصرف يومَ الاعتراف — **مجمَّدٌ** فلا يتغيّر الماضي بتغيّر السعر',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'القيمةُ بالعملة الوظيفية يومَ الاعتراف',
  `collected` decimal(16,2) NOT NULL DEFAULT 0.00,
  `outstanding` decimal(16,2) GENERATED ALWAYS AS (`amount` - `collected`) STORED,
  `due_date` date DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `state` enum('open','partial','collected','overdue') NOT NULL DEFAULT 'open',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_recv_customer` (`company_id`,`customer_entity_id`),
  KEY `ix_fin_recv_state` (`company_id`,`state`),
  KEY `ix_fin_recv_deleted` (`is_deleted`),
  KEY `idx_recv_source_doc` (`doc_type`,`source_doc_id`),
  CONSTRAINT `chk_recv_source_doc` CHECK (`source_doc_id` is not null or `legacy_no_ref` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_request_documents ──
CREATE TABLE `fin_request_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `request_id` int(10) unsigned NOT NULL,
  `doc_type` enum('quote','invoice','statement','contract','receipt','delivery_note','photo','other') NOT NULL,
  `file_ref` varchar(255) NOT NULL,
  `original_kind` enum('original','copy','electronic') NOT NULL DEFAULT 'electronic',
  `retention_years` tinyint(4) NOT NULL DEFAULT 10,
  `confidentiality` enum('normal','restricted','confidential') NOT NULL DEFAULT 'normal',
  `note` varchar(160) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sync_uuid` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_req` (`company_id`,`request_id`),
  KEY `fk_frd_req` (`request_id`),
  CONSTRAINT `fk_frd_req` FOREIGN KEY (`request_id`) REFERENCES `fin_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_request_events ──
CREATE TABLE `fin_request_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `request_id` int(10) unsigned NOT NULL,
  `event_type` enum('create','attach','submit','dept_review','dept_approve','acct_review','verify','fin_approve','reject','return','resubmit','post','pay','collect','settle','close','archive','withdraw','cancel','suspend','resume','expire','merge','duplicate_check','escalate','exception','publish','edit','note','system','exception_requested','exception_denied','exception_overdue') NOT NULL,
  `actor_user_id` int(10) unsigned DEFAULT NULL,
  `on_behalf_of` int(10) unsigned DEFAULT NULL,
  `body` text DEFAULT NULL,
  `old_value` varchar(120) DEFAULT NULL,
  `new_value` varchar(120) DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_req` (`company_id`,`request_id`,`created_at`),
  KEY `ix_type` (`company_id`,`event_type`),
  KEY `fk_fre_req` (`request_id`),
  CONSTRAINT `fk_fre_req` FOREIGN KEY (`request_id`) REFERENCES `fin_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_request_lines ──
CREATE TABLE `fin_request_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `request_id` int(10) unsigned NOT NULL,
  `item` varchar(200) NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(20) DEFAULT NULL,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(16,2) GENERATED ALWAYS AS (`qty` * `unit_price`) STORED,
  `note` varchar(160) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_req` (`company_id`,`request_id`),
  KEY `fk_frl_req` (`request_id`),
  CONSTRAINT `fk_frl_req` FOREIGN KEY (`request_id`) REFERENCES `fin_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_request_routing ──
CREATE TABLE `fin_request_routing` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `source_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','general','sites','movement','transport','tickets','admin') NOT NULL,
  `module_label` varchar(80) NOT NULL,
  `requester_roles` varchar(60) NOT NULL,
  `reviewer_role_id` int(11) NOT NULL,
  `manager_role_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_frr` (`company_id`,`source_module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_requests ──
CREATE TABLE `fin_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `request_no` varchar(30) NOT NULL,
  `request_type` enum('purchase','disbursement','advance','supplier_payment','employee_payment','transfer','settlement','refund','discount','collection','other') NOT NULL,
  `source_module` enum('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','general','sites','movement','transport','tickets','admin') NOT NULL,
  `requester_id` int(10) unsigned DEFAULT NULL,
  `beneficiary_type` enum('supplier','employee','customer','internal','other') NOT NULL,
  `beneficiary_ref` int(10) unsigned DEFAULT NULL,
  `beneficiary_name` varchar(160) DEFAULT NULL,
  `amount` decimal(16,2) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `payment_method` enum('cash','bank','transfer','cheque') DEFAULT NULL,
  `statement` varchar(255) DEFAULT NULL,
  `justification` varchar(255) DEFAULT NULL,
  `source_ref` varchar(60) DEFAULT NULL,
  `settlement_id` int(11) DEFAULT NULL COMMENT 'التسويةُ المعتمدة التي وُلِّد عنها هذا الطلب — إلزاميٌّ لدفعات الأطراف (UX-02 §15.4)',
  `project_id` int(10) unsigned DEFAULT NULL,
  `equipment_id` int(10) unsigned DEFAULT NULL,
  `contract_id` int(10) unsigned DEFAULT NULL,
  `cost_center` varchar(60) DEFAULT NULL,
  `account_id` int(10) unsigned DEFAULT NULL,
  `needed_by` date DEFAULT NULL,
  `priority` enum('normal','high','critical') NOT NULL DEFAULT 'normal',
  `need_class` enum('planned','unplanned','urgent','emergency') NOT NULL DEFAULT 'planned',
  `budget_line_id` int(10) unsigned DEFAULT NULL,
  `sla_due_at` datetime DEFAULT NULL,
  `escalation_level` tinyint(4) NOT NULL DEFAULT 0,
  `is_exception` tinyint(1) NOT NULL DEFAULT 0,
  `exception_type` enum('urgent_bypass','emergency_execute') DEFAULT NULL,
  `exception_approved_by` int(10) unsigned DEFAULT NULL,
  `merged_into_id` int(10) unsigned DEFAULT NULL,
  `parent_request_id` int(10) unsigned DEFAULT NULL COMMENT 'المسار المركب §6.2: الطلب الأصل الذي تفرّع عنه هذا الطلب — NULL = أصلٌ أو مستقل',
  `duplicate_flag` tinyint(1) NOT NULL DEFAULT 0,
  `rejection_class` enum('incomplete_docs','no_budget','policy_violation','duplicate','not_justified','other') DEFAULT NULL,
  `decision_ref` varchar(60) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `state` enum('draft','under_review','pending_approval','approved','rejected','returned','posted','paid','collected','closed','archived','withdrawn','cancelled','suspended','expired','merged') NOT NULL DEFAULT 'draft',
  `event_id` int(10) unsigned DEFAULT NULL,
  `decided_by` int(10) unsigned DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  CONSTRAINT `fk_req_settlement` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`),
  CONSTRAINT `chk_party_payment_needs_settlement` CHECK (`request_type` not in (_utf8mb4'supplier_payment',_utf8mb4'employee_payment',_utf8mb4'settlement') or `settlement_id` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_role_migration ──
CREATE TABLE `fin_role_migration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `old_role_id` int(10) unsigned NOT NULL,
  `old_role_name` varchar(120) NOT NULL,
  `new_role_id` int(10) unsigned DEFAULT NULL COMMENT 'فارغٌ حين يكون الترحيلُ إلى محورِ تخصصٍ لا إلى دور',
  `new_spec_code` varchar(8) NOT NULL DEFAULT '' COMMENT 'ACC-01..ACC-10 حين يكون الترحيلُ تخصصًا',
  `rule_text` varchar(500) NOT NULL DEFAULT '',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `holders_before` int(10) unsigned NOT NULL DEFAULT 0,
  `holders_moved` int(10) unsigned NOT NULL DEFAULT 0,
  `state` enum('planned','in_progress','done') NOT NULL DEFAULT 'planned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mig` (`company_id`,`old_role_id`,`new_role_id`,`new_spec_code`),
  KEY `ix_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-MGR-01 §4-3 — ترحيلُ الأدوارِ القديمةِ بلا حذفِ حامل';

-- ── Table: fin_routing_event_map ──
CREATE TABLE `fin_routing_event_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `event_key` varchar(80) NOT NULL COMMENT 'مفتاحُ الحدثِ في الناقل — أو % للكل',
  `source_module` varchar(40) NOT NULL DEFAULT '' COMMENT 'قيدٌ إضافيٌّ — فارغٌ = أيُّ إدارة',
  `route_code` varchar(8) NOT NULL COMMENT 'RT-01..RT-35',
  `priority` smallint(5) unsigned NOT NULL DEFAULT 100 COMMENT 'الأدقُّ أولًا',
  `note` varchar(300) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_map` (`company_id`,`event_key`,`source_module`),
  KEY `ix_lookup` (`event_key`,`active`,`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 OBL-0002 — ربطُ مفاتيحِ الناقلِ بمساراتِ التوجيه';

-- ── Table: fin_routing_log ──
CREATE TABLE `fin_routing_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `route_code` varchar(8) NOT NULL,
  `trigger_key` varchar(80) NOT NULL DEFAULT '',
  `source_kind` varchar(40) NOT NULL DEFAULT '' COMMENT 'نوعُ المستندِ المصدر',
  `source_ref` varchar(120) NOT NULL COMMENT 'مرجعُ المستندِ المصدر',
  `source_dept` varchar(160) NOT NULL DEFAULT '',
  `target_spec` varchar(8) NOT NULL DEFAULT '',
  `accountant_id` int(10) unsigned DEFAULT NULL COMMENT 'users.id لمحاسبِ التخصصِ المستلِم',
  `work_item_id` bigint(20) unsigned DEFAULT NULL COMMENT 'المهمةُ المولَّدةُ في مساحةِ عمله',
  `event_ref` varchar(60) NOT NULL DEFAULT '' COMMENT 'مرجعُ الحدثِ المنشور',
  `financial_event_id` bigint(20) unsigned DEFAULT NULL COMMENT 'fin_financial_events.id — الحدثُ الذي استُهلك',
  `resolved_by` enum('matrix','fallback','escalated','manual') NOT NULL DEFAULT 'matrix' COMMENT 'matrix مسارٌ صريح · fallback الحكمُ الجامع · escalated لا حاملَ للتخصص · manual استثناءٌ مسجَّل',
  `manual_reason` varchar(300) NOT NULL DEFAULT '' COMMENT 'اليدويُّ استثناءٌ مسجَّل — WF-07',
  `routed_at` datetime NOT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_source_route` (`company_id`,`source_kind`,`source_ref`,`route_code`),
  KEY `ix_spec_time` (`company_id`,`target_spec`,`routed_at`),
  KEY `ix_acc` (`accountant_id`),
  KEY `ix_src` (`source_kind`,`source_ref`),
  KEY `ix_fev` (`financial_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-15 — سجلُّ التوجيهِ الحيُّ وشاهدُ عدمِ التخطي';

-- ── Table: fin_routing_matrix ──
CREATE TABLE `fin_routing_matrix` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code` varchar(8) NOT NULL COMMENT 'RT-01..RT-35',
  `kind` enum('route','fallback') NOT NULL DEFAULT 'route',
  `trigger_ar` varchar(200) NOT NULL COMMENT 'المُطلِق',
  `trigger_key` varchar(80) NOT NULL DEFAULT '' COMMENT 'مفتاحُ الحدثِ المنشورِ الذي يشغّل المسار',
  `source_dept` varchar(160) NOT NULL DEFAULT '' COMMENT 'الإدارةُ المصدر',
  `launch_cond` varchar(300) NOT NULL DEFAULT '' COMMENT 'شرطُ الإطلاق',
  `target_spec` varchar(8) NOT NULL DEFAULT '' COMMENT 'ACC-xx — فارغٌ في الاحتياطية',
  `target_label` varchar(200) NOT NULL DEFAULT '',
  `accounts` varchar(255) NOT NULL DEFAULT '',
  `dims` varchar(64) NOT NULL DEFAULT '',
  `chain` varchar(500) NOT NULL DEFAULT '' COMMENT 'سلسلةُ المرور — آخرُها الخزينةُ إن وُجدت',
  `guard_rule` varchar(500) NOT NULL DEFAULT '' COMMENT 'الحكمُ الحارسُ للمسار',
  `accept_test` varchar(400) NOT NULL DEFAULT '',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_route` (`company_id`,`code`),
  KEY `ix_trigger` (`trigger_key`),
  KEY `ix_spec` (`target_spec`),
  KEY `ix_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-15 + §4-1 — مصفوفةُ التوجيهِ بخمسةٍ وثلاثين مسارًا';

-- ── Table: fin_signal_rules ──
CREATE TABLE `fin_signal_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `signal_code` varchar(12) NOT NULL COMMENT 'FS-01..FS-16',
  `name_ar` varchar(255) NOT NULL,
  `rule_expr` varchar(190) NOT NULL COMMENT 'القاعدةُ نصًّا: FR-05 < 0 · FR-09 ↓ ×3',
  `ratio_code` varchar(12) NOT NULL DEFAULT '' COMMENT 'النسبةُ التي تُقاس',
  `operator` enum('lt','gt','lte','gte','decline_streak','delta_gt','none') NOT NULL DEFAULT 'none',
  `threshold` decimal(18,4) DEFAULT NULL,
  `streak_periods` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'تراجعٌ متتالٍ — ثلاثةُ أشهرٍ مثلًا',
  `severity` enum('حرج','مرتفع','متوسط','منخفض') NOT NULL DEFAULT 'متوسط',
  `destination_ar` varchar(190) NOT NULL DEFAULT '' COMMENT 'الوجهةُ: الرئيسُ والنائبُ المالي …',
  `cadence` varchar(24) NOT NULL DEFAULT 'شهريًّا',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fsrule` (`company_id`,`signal_code`),
  KEY `ix_fsrule_active` (`company_id`,`active`,`cadence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MAP-7 الورقة 36: كلُّ إشارةٍ تُنشر لإدارةِ المخاطرِ فتدخل الفرزَ الرباعي';

-- ── Table: fin_tax_codes ──
CREATE TABLE `fin_tax_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(120) NOT NULL,
  `rate` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'النسبة %',
  `tax_type` enum('output','input','both') NOT NULL DEFAULT 'both',
  `account_id` int(11) DEFAULT NULL COMMENT 'fin_chart_of_accounts (حساب الضريبة، soft)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_tax_code` (`company_id`,`code`),
  KEY `ix_fin_tax_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_tax_returns ──
CREATE TABLE `fin_tax_returns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `period_ref` varchar(10) NOT NULL COMMENT 'YYYY-MM',
  `taxable_sales` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'المبيعاتُ الخاضعة (وعاءُ المخرجات)',
  `output_tax` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ضريبةُ المخرجات',
  `taxable_purchases` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'المشتريات (وعاءُ المدخلات)',
  `input_tax` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ضريبةُ المدخلات',
  `net_tax` decimal(18,2) GENERATED ALWAYS AS (round(`output_tax` - `input_tax`,2)) STORED COMMENT '«الصافي» — **عمودٌ مولَّدٌ لا يُكتب** فلا ينحرف عن طرفيه',
  `lines_count` int(11) NOT NULL DEFAULT 0 COMMENT 'عددُ الحركات المشتقّ منها — الصفرُ يُعلَن ولا يُخفى',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `state` enum('draft','filed') NOT NULL DEFAULT 'draft',
  `event_id` int(11) DEFAULT NULL,
  `basis_json` text DEFAULT NULL,
  `filed_at` datetime DEFAULT NULL,
  `filed_by` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_return` (`company_id`,`period_ref`) COMMENT '«بمفتاح الفترة»',
  KEY `ix_tax_return_state` (`company_id`,`state`),
  CONSTRAINT `ck_taxret_filed` CHECK (`state` <> _utf8mb4'filed' or `filed_at` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_tax_transactions ──
CREATE TABLE `fin_tax_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `tax_code_id` int(11) DEFAULT NULL,
  `direction` enum('output','input') NOT NULL COMMENT 'output=ضريبة مبيعات، input=ضريبة مشتريات',
  `base_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'لقطة النسبة وقت الإدخال',
  `tax_amount` decimal(16,2) GENERATED ALWAYS AS (round(`base_amount` * `tax_rate` / 100,2)) STORED,
  `source_ref` varchar(60) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `period_ref` varchar(10) NOT NULL COMMENT 'YYYY-MM',
  `state` enum('draft','filed') NOT NULL DEFAULT 'draft',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_taxtr_period` (`company_id`,`period_ref`),
  KEY `ix_fin_taxtr_dir` (`company_id`,`direction`),
  KEY `ix_fin_taxtr_deleted` (`is_deleted`),
  KEY `fk_fin_taxtr_code` (`tax_code_id`),
  CONSTRAINT `fk_fin_taxtr_code` FOREIGN KEY (`tax_code_id`) REFERENCES `fin_tax_codes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_treasury_roles ──
CREATE TABLE `fin_treasury_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `code` varchar(12) NOT NULL COMMENT 'TRE-R01..TRE-R08',
  `seq` tinyint(3) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `role_id` int(10) unsigned DEFAULT NULL COMMENT 'الدورُ المقابلُ في roles إن وُجد',
  `scope_note` varchar(300) NOT NULL DEFAULT '' COMMENT 'حدودُه وفصلُ واجباتِه عن غيره',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trole` (`company_id`,`code`),
  KEY `ix_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-TRE-01 §4-2 — الأدوارُ الثمانيةُ داخلَ وحدةِ الخزينة';

-- ── Table: fin_unit_records ──
CREATE TABLE `fin_unit_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `record_no` varchar(30) NOT NULL COMMENT 'يُسنِده الخادم',
  `record_date` date NOT NULL,
  `project_id` int(11) NOT NULL COMMENT 'project.id (مرجع مرن)',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'equipments.id (مرجع مرن)',
  `supplier_entity_id` int(11) DEFAULT NULL COMMENT 'suppliers.id — شريك الإنتاج (مرجع مرن)',
  `work_model` enum('hour','ton','meter') NOT NULL,
  `ops_qty` decimal(14,2) NOT NULL COMMENT 'كمية كشوف التشغيل',
  `client_qty` decimal(14,2) DEFAULT NULL COMMENT 'كمية مصادقة العميل',
  `supplier_qty` decimal(14,2) DEFAULT NULL COMMENT 'كمية حساب المورد',
  `approved_qty` decimal(14,2) DEFAULT NULL COMMENT 'المعتمدة بعد التطابق',
  `client_unit_price` decimal(14,2) DEFAULT NULL COMMENT 'سعر وحدة عقد العميل (لقطة)',
  `supplier_unit_price` decimal(14,2) DEFAULT NULL COMMENT 'سعر وحدة عقد المورد (لقطة)',
  `unit_margin` decimal(16,2) GENERATED ALWAYS AS (round((coalesce(`client_unit_price`,0) - coalesce(`supplier_unit_price`,0)) * coalesce(`approved_qty`,0),2)) STORED COMMENT 'هامش الوحدة = (سعر العميل − سعر المورد) × المعتمد',
  `match_state` enum('pending','matched','variance','approved') NOT NULL DEFAULT 'pending',
  `variance_note` varchar(200) DEFAULT NULL COMMENT 'توثيق الفرق قبل الفوترة',
  `downtime_hours` decimal(8,2) DEFAULT NULL,
  `downtime_cause` enum('breakdown','standby','operator_shortage','mobilization','client') DEFAULT NULL COMMENT 'إلزامي إن وُجد توقف (قاعدة السبب)',
  `source_ref` varchar(60) DEFAULT NULL COMMENT 'كشف تشغيل / تذكرة وزن / محضر قياس',
  `revenue_event_id` int(11) DEFAULT NULL COMMENT 'fin_financial_events (التوأم الأول، soft)',
  `supplier_due_id` int(11) DEFAULT NULL COMMENT 'fin_dues (التوأم الثاني، soft)',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_ur_no` (`company_id`,`record_no`),
  KEY `ix_fin_ur_date` (`company_id`,`record_date`),
  KEY `ix_fin_ur_project` (`project_id`),
  KEY `ix_fin_ur_match` (`company_id`,`match_state`),
  KEY `ix_fin_ur_deleted` (`is_deleted`),
  CONSTRAINT `chk_unit_approved_has_actor` CHECK (`match_state` <> 'approved' or `is_deleted` <> 0 or `created_by` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: fin_units ──
CREATE TABLE `fin_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL COMMENT 'gl / ar / ap / revenue / treasury ...',
  `name` varchar(120) NOT NULL,
  `role_note` varchar(200) DEFAULT NULL,
  `head_position_id` int(11) DEFAULT NULL COMMENT 'job_titles/roles.id (مرجع مرن)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_unit_code` (`company_id`,`code`),
  KEY `ix_fin_unit_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: financed_assets ──
CREATE TABLE `financed_assets` (
  `fa_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `op_id` int(10) unsigned NOT NULL,
  `asset_id` int(11) NOT NULL COMMENT 'fin_assets.id أو equipments.id بحسب التقاطع',
  `asset_kind` enum('fin_asset','equipment') NOT NULL DEFAULT 'equipment',
  `purchase_value` decimal(18,2) DEFAULT NULL,
  `in_fleet` tinyint(1) NOT NULL DEFAULT 0,
  `in_asset_register` tinyint(1) NOT NULL DEFAULT 0,
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من financing_operations.op_id',
  PRIMARY KEY (`fa_id`),
  UNIQUE KEY `uq_fa` (`op_id`,`asset_kind`,`asset_id`),
  KEY `ix_finasset_co` (`company_id`),
  CONSTRAINT `fk_fa_op` FOREIGN KEY (`op_id`) REFERENCES `financing_operations` (`op_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-01 §4-②: أعيان العملية — فحص تقاطع الأسطول وسجل الأصول';

-- ── Table: financing_deviations ──
CREATE TABLE `financing_deviations` (
  `dev_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `dev_type` enum('no_ledger','payment_gap','unrecorded_exit') NOT NULL,
  `subject_ref` varchar(120) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `required_doc` varchar(160) DEFAULT NULL,
  `state` enum('open','closed') NOT NULL DEFAULT 'open',
  `decision` varchar(500) DEFAULT NULL COMMENT 'القرار المتخذ — ولا يُغلق صف بلا قرار ومستند (الخدمة)',
  `decision_doc_ref` varchar(160) DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`dev_id`),
  UNIQUE KEY `uq_fd_subject` (`company_id`,`dev_type`,`subject_ref`),
  KEY `ix_fd_state` (`company_id`,`state`,`priority`),
  CONSTRAINT `ck_fd_close_needs_decision` CHECK (`state` <> _utf8mb4'closed' or `decision` is not null and `decision_doc_ref` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: financing_installments ──
CREATE TABLE `financing_installments` (
  `inst_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `op_id` int(10) unsigned NOT NULL,
  `seq_no` int(10) unsigned NOT NULL,
  `due_date` date NOT NULL,
  `amount_principal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `amount_profit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `amount_total` decimal(18,2) NOT NULL,
  `currency` varchar(8) NOT NULL,
  `fx_rate_at_payment` decimal(16,8) DEFAULT NULL COMMENT 'سعر يوم السداد — فرق محقق بسطره (PLAN-03 §7.2)',
  `functional_equivalent` decimal(18,2) DEFAULT NULL,
  `paid_date` date DEFAULT NULL,
  `payment_ref` varchar(120) DEFAULT NULL,
  `state` enum('scheduled','due','paid','overdue','rescheduled') NOT NULL DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من financing_operations.op_id',
  PRIMARY KEY (`inst_id`),
  UNIQUE KEY `uq_fi_seq` (`op_id`,`seq_no`) COMMENT 'يمنع تكرار القسط — وحدث الاستحقاق بمفتاح (العملية×القسط)',
  KEY `ix_fi_due` (`due_date`,`state`),
  KEY `ix_fininst_co` (`company_id`),
  CONSTRAINT `fk_fi_op` FOREIGN KEY (`op_id`) REFERENCES `financing_operations` (`op_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-01 §6: الأقساط تولَّد من العملية ولا تُدخل يدويًّا';

-- ── Table: financing_models ──
CREATE TABLE `financing_models` (
  `model_code` varchar(32) NOT NULL,
  `name_ar` varchar(120) NOT NULL,
  `legal_owner_effect` enum('transfers','stays','shared','none') NOT NULL COMMENT '① المالك القانوني',
  `economic_beneficiary` enum('us','financier','shared') NOT NULL COMMENT '② المنتفع الاقتصادي',
  `accounting_recognition` enum('owned_asset','right_of_use','liability_only') NOT NULL COMMENT '③ الاعتراف — لا يُستنتج من الاسم',
  `depreciation_bearer` varchar(60) NOT NULL COMMENT '④ حامل الإهلاك',
  `security_interest_holder` varchar(60) DEFAULT NULL COMMENT '⑤ مرتهن الضمان',
  `policy_doc_ref` varchar(160) NOT NULL COMMENT 'سياسة محاسبية مكتوبة معتمدة — إلزامية قبل الاستعمال',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`model_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-01 §2: قاموس نماذج التمويل بمحاوره الخمسة — يُضاف إليه بقرار لا بكود';

-- ── Table: financing_operations ──
CREATE TABLE `financing_operations` (
  `op_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `op_code` varchar(40) NOT NULL,
  `financier_entity_id` int(10) unsigned NOT NULL COMMENT 'كيان بصفة ممول (LEG-01) — لا سجل موازيًا',
  `model_code` varchar(32) NOT NULL,
  `currency` varchar(8) NOT NULL,
  `contract_ref` varchar(120) DEFAULT NULL,
  `signed_date` date DEFAULT NULL,
  `capital` decimal(18,2) NOT NULL DEFAULT 0.00,
  `capital_source` varchar(120) DEFAULT NULL,
  `purchase_value` decimal(18,2) DEFAULT NULL COMMENT 'قيمة شراء العين — أشد الحقول سرية',
  `down_payment` decimal(18,2) NOT NULL DEFAULT 0.00,
  `fees_admin` decimal(18,2) NOT NULL DEFAULT 0.00,
  `fees_insurance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `extra_costs` decimal(18,2) NOT NULL DEFAULT 0.00,
  `profit_rate` decimal(8,4) DEFAULT NULL,
  `profit_amount` decimal(18,2) DEFAULT NULL,
  `apr` decimal(8,4) DEFAULT NULL,
  `installments_no` int(10) unsigned NOT NULL DEFAULT 0,
  `installment_amount` decimal(18,2) DEFAULT NULL,
  `outstanding_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `maturity_date` date DEFAULT NULL,
  `state` enum('draft','negotiation','approved','signed','active','paying','settled','closed','defaulted') NOT NULL DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `authority_ref` varchar(64) DEFAULT NULL COMMENT 'مرجعُ تفويضِ من اعتمد — signing_authorities.auth_id',
  `escalated_to` varchar(64) DEFAULT NULL COMMENT 'مرجعُ صفِّ التصعيدِ في exec_approvals عند تجاوزِ السقف',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`op_id`),
  UNIQUE KEY `uq_fo_code` (`company_id`,`op_code`),
  KEY `ix_fo_financier` (`financier_entity_id`,`state`),
  KEY `fk_fo_model` (`model_code`),
  CONSTRAINT `fk_fo_financier` FOREIGN KEY (`financier_entity_id`) REFERENCES `legal_entities` (`entity_id`),
  CONSTRAINT `fk_fo_model` FOREIGN KEY (`model_code`) REFERENCES `financing_models` (`model_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-01 §4: عمليات التمويل بدورة حياتها — ولا عملية بلا نموذج ومعالجة';

-- ── Table: fleet_depreciation_profile ──
CREATE TABLE `fleet_depreciation_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `asset_category` varchar(120) NOT NULL,
  `brand` varchar(120) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `method` enum('uop','sl') NOT NULL DEFAULT 'uop',
  `useful_life` decimal(12,2) NOT NULL,
  `salvage_pct` decimal(5,4) NOT NULL,
  `notes` text DEFAULT NULL,
  `state` enum('draft','approved') NOT NULL DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fdp_company_code` (`company_id`,`code`),
  KEY `idx_fdp_company` (`company_id`),
  KEY `idx_fdp_model` (`model_id`),
  KEY `idx_fdp_state` (`state`,`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_depreciation_profile_audit ──
CREATE TABLE `fleet_depreciation_profile_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `old_data` text DEFAULT NULL,
  `new_data` text DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fdpa_profile` (`profile_id`),
  KEY `idx_fdpa_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_equipment_compliance ──
CREATE TABLE `fleet_equipment_compliance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) NOT NULL,
  `doc_type` varchar(40) NOT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_critical` tinyint(1) NOT NULL DEFAULT 0,
  `attachment_path` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fec_equipment` (`equipment_id`),
  KEY `idx_fec_company` (`company_id`),
  KEY `idx_fec_expiry` (`expiry_date`),
  CONSTRAINT `fk_fec_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_equipment_component ──
CREATE TABLE `fleet_equipment_component` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) NOT NULL,
  `component_type` varchar(40) NOT NULL,
  `serial_no` varchar(120) DEFAULT NULL,
  `install_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `replace_date` date DEFAULT NULL,
  `component_hours` decimal(12,2) DEFAULT NULL,
  `replace_count` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fecmp_equipment` (`equipment_id`),
  KEY `idx_fecmp_company` (`company_id`),
  CONSTRAINT `fk_fecmp_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_equipment_history ──
CREATE TABLE `fleet_equipment_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) NOT NULL,
  `event_date` datetime NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `reference_type` varchar(40) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `site_id` varchar(120) DEFAULT NULL,
  `in_out_date` date DEFAULT NULL,
  `work_hours` decimal(12,2) DEFAULT NULL,
  `down_hours` decimal(12,2) DEFAULT NULL,
  `maintenance_cost` decimal(12,2) DEFAULT NULL,
  `transfer_cost` decimal(12,2) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `from_value` varchar(150) DEFAULT NULL,
  `to_value` varchar(150) DEFAULT NULL,
  `operation_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feh_equipment` (`equipment_id`),
  KEY `idx_feh_company` (`company_id`),
  KEY `idx_feh_date` (`event_date`),
  KEY `idx_feh_equipment_date` (`equipment_id`,`event_date`),
  CONSTRAINT `fk_feh_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_equipment_protection ──
CREATE TABLE `fleet_equipment_protection` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) NOT NULL,
  `protection_type` varchar(40) NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `cost` decimal(12,2) DEFAULT NULL,
  `state` varchar(20) DEFAULT NULL,
  `renewal_date` date DEFAULT NULL,
  `partner_id` int(11) DEFAULT NULL,
  `partner_name` varchar(150) DEFAULT NULL,
  `compliance_id` int(11) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fep_equipment` (`equipment_id`),
  KEY `idx_fep_company` (`company_id`),
  KEY `idx_fep_compliance` (`compliance_id`),
  CONSTRAINT `fk_fep_compliance` FOREIGN KEY (`compliance_id`) REFERENCES `fleet_equipment_compliance` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_fep_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_model ──
CREATE TABLE `fleet_model` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `code` varchar(60) NOT NULL,
  `manufacturer` varchar(120) DEFAULT NULL,
  `model_name` varchar(150) NOT NULL,
  `equipment_type_id` int(11) DEFAULT NULL,
  `operating_category` varchar(60) DEFAULT NULL,
  `fuel_type` varchar(40) DEFAULT NULL,
  `std_capacity` decimal(14,2) DEFAULT NULL,
  `std_capacity_uom` varchar(40) DEFAULT NULL,
  `tech_reference` varchar(255) DEFAULT NULL,
  `default_supplier_id` int(11) DEFAULT NULL,
  `default_supplier_name` varchar(150) DEFAULT NULL,
  `depreciation_profile_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `item_type` varchar(80) DEFAULT NULL,
  `recommended_ref` varchar(150) DEFAULT NULL,
  `qty` decimal(12,2) DEFAULT NULL,
  `uom` varchar(40) DEFAULT NULL,
  `alt_ref` varchar(150) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fmss_model` (`model_id`),
  KEY `idx_fmss_company` (`company_id`),
  CONSTRAINT `fk_fmss_model` FOREIGN KEY (`model_id`) REFERENCES `fleet_model` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: fleet_reservations ──
CREATE TABLE `fleet_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك',
  `reservation_no` varchar(40) NOT NULL COMMENT 'رقم الحجز — فريد داخل الشركة',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'حجزُ معدةٍ بعينها (يمنع التعارض فعليًّا)',
  `equipment_type_id` int(11) DEFAULT NULL COMMENT 'أو حجزُ فئةٍ بعددٍ — قبل تحديد الآلة',
  `qty` int(11) NOT NULL DEFAULT 1 COMMENT 'العددُ المحجوز حين يكون الحجزُ بالفئة',
  `client_id` int(11) DEFAULT NULL,
  `opportunity_id` int(11) DEFAULT NULL COMMENT 'الفرصةُ التي وُلد منها الحجز',
  `quotation_id` int(11) DEFAULT NULL COMMENT 'العرضُ المرتبط',
  `contract_id` int(11) DEFAULT NULL COMMENT 'يُملأ عند التحويل لعقد',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `state` enum('مبدئي','مؤكَّد','محوَّل لعقد','منتهٍ','ملغى') NOT NULL DEFAULT 'مبدئي',
  `hold_until` datetime DEFAULT NULL COMMENT 'مهلةُ الحجز المبدئي — بعدها يسقط',
  `purpose` varchar(160) DEFAULT NULL COMMENT 'الغرض/الموقع',
  `note` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_res_no` (`company_id`,`reservation_no`),
  KEY `ix_res_eq` (`company_id`,`equipment_id`,`start_date`,`end_date`),
  KEY `ix_res_type` (`company_id`,`equipment_type_id`,`start_date`,`end_date`),
  KEY `ix_res_state` (`company_id`,`state`,`start_date`),
  KEY `ix_res_opp` (`company_id`,`opportunity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='حجوزاتُ الأسطول — النافذةُ الزمنية المحجوزة قبل العقد (RENTAL-CORE ①)';

-- ── Table: founding_mode ──
CREATE TABLE `founding_mode` (
  `mode_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mode` enum('discovery','permission_test') NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `started_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL COMMENT 'إلزامي عند التفعيل — لا وضع تأسيس مفتوح المدة',
  `banner_text` varchar(255) DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closure_ref` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`mode_id`),
  UNIQUE KEY `uq_fm_mode` (`mode`),
  CONSTRAINT `chk_fm_ends` CHECK (`enabled` = 0 or `ends_at` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: gov_approval_decisions ──
CREATE TABLE `gov_approval_decisions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `decision_code` varchar(16) NOT NULL COMMENT 'APD-000001',
  `source_kind` enum('fin_request','supplier_settlement','journal_entry','period_close','other') NOT NULL COMMENT 'الصندوقُ الموحَّد يجمع من مصادرَ أربعة — والقرارُ بخدمة مصدره',
  `source_ref` varchar(64) NOT NULL COMMENT 'مرجعُ المستند في مصدره',
  `decision` enum('rejected','returned','withdrawn_decision') NOT NULL COMMENT 'rejected: رفضٌ بسبب · returned: إعادةٌ للتصحيح',
  `reason_code` varchar(32) NOT NULL DEFAULT '' COMMENT 'السببُ المحكوم: RSN-BUDGET · RSN-DOCS · RSN-AUTH · RSN-DUP · RSN-DATA · RSN-OTHER',
  `reason_note` varchar(255) NOT NULL DEFAULT '' COMMENT 'بيانُ السبب — إلزاميٌّ مع RSN-OTHER',
  `ring_no` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'الحلقةُ في السلسلة عند القرار',
  `decided_by` int(11) NOT NULL COMMENT 'المعتمِدُ صاحبُ القرار',
  `decided_capacity` varchar(120) NOT NULL DEFAULT '' COMMENT 'صفتُه من المسمى الحي — لا الاسم',
  `authority_ref` varchar(120) NOT NULL DEFAULT '' COMMENT '§9-1 مرجعُ التفويض',
  `parent_ref` varchar(64) NOT NULL DEFAULT '' COMMENT 'المرجعُ الأب — المستندُ المقرَّرُ فيه',
  `event_id` int(11) DEFAULT NULL COMMENT 'ApprovalRejected/ApprovalReturned في الممر المحايد',
  `state` enum('effective','superseded') NOT NULL DEFAULT 'effective',
  `superseded_by_ref` varchar(16) NOT NULL DEFAULT '' COMMENT 'إعادةُ رفعٍ بعد التصحيح — دورةٌ جديدة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `idempotency_key` varchar(96) NOT NULL COMMENT '(المصدرُ×المرجعُ×القرارُ×الحلقة)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_apd_code` (`company_id`,`decision_code`),
  UNIQUE KEY `uq_apd_idem` (`company_id`,`idempotency_key`),
  KEY `ix_apd_source` (`company_id`,`source_kind`,`source_ref`),
  KEY `ix_apd_reason` (`company_id`,`reason_code`) COMMENT 'السببُ يُقاس في تحليل الاختناقات'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-14 approval.reject/return: القرارُ بسببٍ محكومٍ يُقاس — وسجلُّه لا يُعدَّل';

-- ── Table: gov_authority_limits ──
CREATE TABLE `gov_authority_limits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `doc_code` varchar(24) NOT NULL COMMENT 'الوثيقةُ التي تُعلن الحد',
  `code` varchar(24) NOT NULL COMMENT 'LIMIT-01 ..',
  `seq` smallint(5) unsigned NOT NULL DEFAULT 0,
  `subject_role` varchar(120) NOT NULL DEFAULT '' COMMENT 'من لا يملك — كما تسميه الوثيقة',
  `role_ids` varchar(60) NOT NULL DEFAULT '' COMMENT 'أدوارُه مفصولةً بفاصلة',
  `forbidden` varchar(300) NOT NULL COMMENT 'الفعلُ الممنوع',
  `action_codes` varchar(400) NOT NULL DEFAULT '' COMMENT 'رموزُ الأفعالِ التي يمنعها هذا الحدُّ — والفارغُ لا يمنع فعلًا بعينِه',
  `enforced_by` varchar(200) NOT NULL DEFAULT '' COMMENT '◆ المُنفِذُ الحي — والفارغُ دعوى لا قيد',
  `enforce_kind` enum('service','guard','schema','permission','manual','none') NOT NULL DEFAULT 'none',
  `limit_kind` enum('absolute','conditional') NOT NULL DEFAULT 'conditional' COMMENT 'FN-09 · مطلقٌ يُوصَل برمزِ فعلٍ · مشروطٌ لا يُوصَل ويُفحص شرطُه في الخدمة',
  `condition_note` varchar(300) NOT NULL DEFAULT '' COMMENT 'الشرطُ الذي يجعل الفعلَ ممنوعًا — يُفحص في الخدمةِ لا في الحارس',
  `accept_test` varchar(300) NOT NULL DEFAULT '',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lim` (`company_id`,`doc_code`,`code`),
  KEY `ix_enf` (`enforce_kind`,`active`),
  CONSTRAINT `chk_gal_conditional_unwired` CHECK (`limit_kind` <> 'conditional' or `action_codes` is null or `action_codes` = '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='الوثائقُ الخمس — الحدودُ الصريحةُ «ما لا يملكه» بمُنفِذِ كلٍّ';

-- ── Table: gov_data_classes ──
CREATE TABLE `gov_data_classes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code` varchar(6) NOT NULL COMMENT 'DC-1..DC-4',
  `title` varchar(120) NOT NULL,
  `name_en` varchar(120) NOT NULL DEFAULT '',
  `meaning` varchar(400) NOT NULL DEFAULT '',
  `examples` varchar(700) NOT NULL DEFAULT '',
  `owner_label` varchar(200) NOT NULL DEFAULT '',
  `create_roles` varchar(120) NOT NULL DEFAULT '' COMMENT 'فارغٌ = الإدارةُ المالكةُ للمستند',
  `edit_roles` varchar(120) NOT NULL DEFAULT '' COMMENT 'فارغٌ = لا أحدَ يعدّل مباشرةً',
  `read_roles` varchar(120) NOT NULL DEFAULT '' COMMENT 'فارغٌ = بحسبِ صلاحيةِ الشاشة',
  `edit_mode` enum('direct','proposal','amendment_only','decision_only') NOT NULL DEFAULT 'direct' COMMENT 'كيف يتغير: مباشرةً · اقتراحًا · بملحقٍ موقَّع · بقرارٍ معتمد',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dc` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-17 — التصنيفُ الرباعيُّ للبيانات';

-- ── Table: gov_denial_reviews ──
CREATE TABLE `gov_denial_reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `review_code` varchar(16) NOT NULL COMMENT 'DNR-000001',
  `denial_id` int(11) NOT NULL COMMENT 'guard_denials.deny_id — المحاولةُ المرصودة',
  `guard_code` varchar(64) NOT NULL DEFAULT '' COMMENT 'رمزُ الحارس الذي منع',
  `classification` enum('يحتاج استثناءً','خطأ تصنيف حماية','محاولة تجاوز','عابر — لا إجراء') NOT NULL COMMENT 'التصنيفُ الثلاثي + العابر — §7-2 denial.review',
  `decision_note` varchar(255) NOT NULL DEFAULT '' COMMENT 'قرارُ المراجعة وتسبيبه',
  `follow_up_ref` varchar(64) NOT NULL DEFAULT '' COMMENT 'الأثرُ التالي: رقمُ طلب استثناءٍ أو تصحيحِ تصنيفٍ أو بلاغ',
  `state` enum('open','closed') NOT NULL DEFAULT 'open',
  `reviewed_by` int(11) NOT NULL COMMENT '§9-1 المُنشئ — المراجِع',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'المرجعُ الأب — رقمُ المحاولة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dnr_code` (`company_id`,`review_code`),
  UNIQUE KEY `uq_dnr_denial` (`company_id`,`denial_id`) COMMENT 'مراجعةٌ واحدةٌ للمحاولة — والتحديثُ عليها',
  KEY `ix_dnr_state` (`company_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-14 denial.review: المنعُ المتكرر يُراجَع ويُصنَّف — لا يُترك صامتًا';

-- ── Table: gov_dept_propagation ──
CREATE TABLE `gov_dept_propagation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `dept_name` varchar(120) NOT NULL,
  `propagated` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'أحكامٌ منتشرةٌ عليها',
  `dept_total` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'إجماليُّ أحكامِها',
  `doors_note` varchar(300) NOT NULL DEFAULT '' COMMENT 'الأبوابُ الثمانيةُ التي تمسُّها',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept` (`company_id`,`dept_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PROP-01 §6-1 — الأحكامُ المنتشرةُ في الإداراتِ الستَّ عشرة';

-- ── Table: gov_doc_registry ──
CREATE TABLE `gov_doc_registry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = يخصُّ الوثيقةَ لا الكيان',
  `doc_code` varchar(24) NOT NULL COMMENT 'الوثيقةُ المعلِنة',
  `family` varchar(16) NOT NULL COMMENT 'DUTY · LIMIT · COMP · SCEN · CYCLE …',
  `item_code` varchar(24) NOT NULL COMMENT 'رمزُ البندِ داخلَ عائلته',
  `seq` smallint(5) unsigned NOT NULL DEFAULT 0,
  `title` varchar(300) NOT NULL,
  `detail` varchar(500) NOT NULL DEFAULT '',
  `accept_test` varchar(300) NOT NULL DEFAULT '' COMMENT 'شاهدُ القبولِ كما تكتبه الوثيقة',
  `doc_ref` varchar(24) NOT NULL DEFAULT '' COMMENT 'المتطلبُ الذريُّ المصدر',
  `covered_by` varchar(200) NOT NULL DEFAULT '' COMMENT 'الأثرُ الحيُّ المنفِّذ — والفارغُ ثغرة',
  `coverage_kind` enum('table','service','screen','guard','harness','catalogue','uat','seed','none') NOT NULL DEFAULT 'none' COMMENT 'harness = يُنفَّذ آليًّا · catalogue = مرجعٌ بطبعِه · uat = بيدِ المستخدم · seed = مكتوبٌ لم يُنفَّذ بعد',
  `coverage_note` varchar(300) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item` (`company_id`,`doc_code`,`family`,`item_code`),
  KEY `ix_fam` (`doc_code`,`family`),
  KEY `ix_cov` (`coverage_kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='update0013 — البنودُ المعلَنةُ في الوثائقِ وتغطيتُها الحية';

-- ── Table: gov_doc_variance ──
CREATE TABLE `gov_doc_variance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = يخصُّ الوثيقةَ لا الكيان',
  `variance_code` varchar(12) NOT NULL COMMENT 'V-01 ..',
  `doc_code` varchar(24) NOT NULL COMMENT 'الوثيقةُ صاحبةُ التعارض',
  `subject` varchar(200) NOT NULL COMMENT 'موضعُ التعارض',
  `declared_where` varchar(120) NOT NULL DEFAULT '' COMMENT 'أين أُعلن الرقمُ الأول',
  `declared_value` varchar(120) NOT NULL DEFAULT '',
  `registered_where` varchar(120) NOT NULL DEFAULT '' COMMENT 'أين سُجِّل الثاني',
  `registered_value` varchar(120) NOT NULL DEFAULT '',
  `resolution` enum('follow_register','follow_declared','derive','defer') NOT NULL COMMENT 'follow_register = يُتبع السجلُّ الذريُّ لأنه القابلُ للاختبار',
  `resolved_value` varchar(120) NOT NULL DEFAULT '' COMMENT 'الرقمُ الذي بُني عليه فعلًا',
  `basis` varchar(600) NOT NULL COMMENT 'أساسُ الحسمِ — ولا حسمَ بلا أساس',
  `impact` varchar(400) NOT NULL DEFAULT '' COMMENT 'ما بُني نتيجةَ الحسم',
  `decided_by` varchar(120) NOT NULL DEFAULT '' COMMENT 'من حسمَه وبأي صفة',
  `decided_at` datetime NOT NULL,
  `owner_action` varchar(300) NOT NULL DEFAULT '' COMMENT 'ما يلزم مالكَ الوثيقةِ فعلُه',
  `state` enum('open','resolved','accepted_by_owner','superseded') NOT NULL DEFAULT 'resolved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_var` (`company_id`,`variance_code`),
  KEY `ix_doc` (`doc_code`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='update0013 — مخالفاتُ الوثائقِ وحسمُها بأساسٍ مكتوبٍ يُفحص كلَّ بوابة';

-- ── Table: gov_export_log ──
CREATE TABLE `gov_export_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT 0,
  `exported_by` int(11) NOT NULL DEFAULT 0,
  `actor_capacity` varchar(120) NOT NULL DEFAULT '',
  `entity_key` varchar(64) NOT NULL DEFAULT '',
  `screen_code` varchar(190) NOT NULL DEFAULT '',
  `columns_text` text DEFAULT NULL,
  `blocked_text` text DEFAULT NULL,
  `filters_text` text DEFAULT NULL,
  `row_count` int(10) unsigned NOT NULL DEFAULT 0,
  `fmt` varchar(12) NOT NULL DEFAULT 'xlsx',
  `exported_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gel_company_time` (`company_id`,`exported_at`),
  KEY `idx_gel_actor` (`exported_by`,`exported_at`),
  KEY `idx_gel_entity` (`entity_key`,`exported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='RF-03 · سجلُّ التصديرِ الحوكميِّ بتسعةِ بنودٍ ومنها المستبعَد';

-- ── Table: gov_field_class ──
CREATE TABLE `gov_field_class` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `screen_code` varchar(80) NOT NULL COMMENT 'رمزُ الشاشةِ الحاكمة',
  `field_key` varchar(80) NOT NULL COMMENT 'مفتاحُ الحقلِ في الشاشة',
  `label_ar` varchar(160) NOT NULL DEFAULT '',
  `dc_code` varchar(6) NOT NULL COMMENT 'DC-1..DC-4 — ولا حقلَ بلا صنف',
  `is_sensitive` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'يحتاج منحًا فرديًّا ويُسجَّل الاطّلاع',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_field` (`company_id`,`screen_code`,`field_key`),
  KEY `ix_dc` (`dc_code`,`active`),
  KEY `ix_screen` (`screen_code`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PROP-01 §7-2 ⑤ — صفرُ حقلٍ في شاشةٍ حاكمةٍ بلا صنف';

-- ── Table: gov_field_inheritance ──
CREATE TABLE `gov_field_inheritance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `child_entity` varchar(60) NOT NULL COMMENT 'المستندُ التابع: accrual · obligation · invoice · timesheet',
  `child_field` varchar(80) NOT NULL,
  `parent_entity` varchar(60) NOT NULL COMMENT 'المرجعُ الأب',
  `parent_field` varchar(80) NOT NULL,
  `label_ar` varchar(160) NOT NULL DEFAULT '',
  `readonly` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'IN-01 — الموروثُ للقراءةِ فقط',
  `on_parent_change` enum('cascade_if_draft','notify_only') NOT NULL DEFAULT 'cascade_if_draft',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inh` (`company_id`,`child_entity`,`child_field`),
  KEY `ix_parent` (`parent_entity`,`parent_field`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 §4-21 — التوريثُ ومنعُ إعادةِ الإدخال';

-- ── Table: gov_governing_screens ──
CREATE TABLE `gov_governing_screens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `screen_code` varchar(80) NOT NULL,
  `title_ar` varchar(200) NOT NULL DEFAULT '',
  `file_path` varchar(160) NOT NULL DEFAULT '',
  `why_governing` varchar(300) NOT NULL DEFAULT '' COMMENT 'لماذا عُدَّت حاكمة',
  `owner_doc` varchar(40) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scr` (`company_id`,`screen_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PROP-01 §4-1 ⑤ — سجلُّ الشاشاتِ الحاكمةِ الخاضعةِ لشرطِ التصنيف';

-- ── Table: gov_inheritance_denials ──
CREATE TABLE `gov_inheritance_denials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `child_entity` varchar(60) NOT NULL,
  `child_ref` varchar(120) NOT NULL DEFAULT '',
  `child_field` varchar(80) NOT NULL,
  `source_shown` varchar(200) NOT NULL DEFAULT '' COMMENT 'المصدرُ الذي بُيِّن للمستخدم',
  `attempted_by` int(10) unsigned NOT NULL DEFAULT 0,
  `denied_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_field` (`company_id`,`child_entity`,`child_field`,`denied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FIN-OBL-01 IN-01 — سجلُّ رفضِ تعديلِ الموروث';

-- ── Table: governance_flags ──
CREATE TABLE `governance_flags` (
  `flag_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `element_code` varchar(80) NOT NULL COMMENT 'external_accounts · signing_caps · joint_signing · guarantees · licenses …',
  `scope_type` enum('entity','contract') NOT NULL,
  `scope_id` int(10) unsigned NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `set_by` int(11) DEFAULT NULL,
  `set_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`flag_id`),
  UNIQUE KEY `uq_gf_element_scope` (`element_code`,`scope_type`,`scope_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §7: أعلام التفعيل لكل عنصر على الكيان والعقد — الافتراض النمط ① (كله مطفأ)';

-- ── Table: guarantees ──
CREATE TABLE `guarantees` (
  `gtee_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `direction` enum('issued','received') NOT NULL COMMENT 'صادرة منا (التزام محتمل) · واردة إلينا (حق محتمل)',
  `entity_id` int(10) unsigned NOT NULL,
  `counterparty_id` int(10) unsigned DEFAULT NULL,
  `gtee_type` varchar(80) NOT NULL,
  `bank` varchar(120) DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(8) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `alert_days` int(10) unsigned NOT NULL DEFAULT 30,
  `auto_renew` tinyint(1) NOT NULL DEFAULT 0,
  `fees` decimal(18,2) DEFAULT NULL,
  `state` enum('active','released','called','expired') NOT NULL DEFAULT 'active',
  `doc_ref` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`gtee_id`),
  KEY `ix_g_expiry` (`expiry_date`,`state`),
  KEY `fk_g_entity` (`entity_id`),
  CONSTRAINT `fk_g_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §5: الكفالات وخطابات الضمان — التزام/حق محتمل خارج الميزانية، مفصول عن المحتجَز النقدي (P-06)';

-- ── Table: guard_denials ──
CREATE TABLE `guard_denials` (
  `deny_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `guard_code` varchar(64) NOT NULL,
  `person_id` int(11) NOT NULL,
  `attempted_ref` varchar(120) DEFAULT NULL,
  `reason_code` varchar(80) DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`deny_id`),
  KEY `ix_gd_guard` (`guard_code`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §9: سجل المنع — مقياس ملاءمة الحماية لا سجل مخالفات المستخدمين';

-- ── Table: guard_override_policies ──
CREATE TABLE `guard_override_policies` (
  `guard_code` varchar(64) NOT NULL,
  `name_ar` varchar(190) NOT NULL,
  `overridable` enum('never','break_glass_only','with_compensating_control') NOT NULL,
  `environments_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'بيئات السريان — production·founding·test' CHECK (json_valid(`environments_json`)),
  PRIMARY KEY (`guard_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §7.2: الاسم يصف السياسة لا النتيجة — ويقرؤها كسر الزجاج فلا يتجاوز never';

-- ── Table: guard_policies ──
CREATE TABLE `guard_policies` (
  `guard_code` varchar(64) NOT NULL,
  `name_ar` varchar(160) NOT NULL,
  `owner_doc` varchar(40) DEFAULT NULL COMMENT 'وثيقة البيت',
  `guard_class` enum('absolute','exception_allowed','advisory') NOT NULL,
  `default_risk` enum('normal','operational','financial','high','legal_forbidden') NOT NULL DEFAULT 'normal',
  `env_flag_name` varchar(64) DEFAULT NULL COMMENT 'اسم العلم في .env',
  `classified_by` int(11) DEFAULT NULL,
  `classified_at` datetime DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL COMMENT 'سبب إلزامي لأي تغيير صنف',
  PRIMARY KEY (`guard_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §10: قاموس تصنيف الحمايات — الصنف يتغير بقرار حوكمة لا بتعديل إعداد';

-- ── Table: housing_unit ──
CREATE TABLE `housing_unit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `project_id` int(11) DEFAULT NULL COMMENT 'بالقيمة إلى project.id',
  `capacity` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hu_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: hr_dictionaries ──
CREATE TABLE `hr_dictionaries` (
  `code` varchar(40) NOT NULL,
  `name_ar` varchar(190) NOT NULL,
  `layer` enum('relation','family','level') NOT NULL,
  `rank` int(11) DEFAULT NULL COMMENT 'للمستوى — درجة السلطة تصاعديًّا',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: تُضاف قيمها بصف لا بكود';

-- ── Table: iaf_access_log ──
CREATE TABLE `iaf_access_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `auditor_id` int(10) unsigned NOT NULL,
  `scope_kind` varchar(60) NOT NULL COMMENT 'ما اطُّلع عليه',
  `scope_ref` varchar(160) NOT NULL DEFAULT '',
  `purpose` varchar(200) NOT NULL DEFAULT '' COMMENT 'مرجعُ المهمةِ التي تُبرِّر الاطّلاع',
  `engagement_id` int(10) unsigned DEFAULT NULL,
  `accessed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_aud` (`company_id`,`auditor_id`,`accessed_at`),
  KEY `ix_scope` (`scope_kind`,`scope_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-0036 + OBL-0127 — سجلُّ اطّلاعِ المراجعِ نفسِه';

-- ── Table: iaf_authorities ──
CREATE TABLE `iaf_authorities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `code` varchar(12) NOT NULL COMMENT 'IAF-A01 ..',
  `seq` tinyint(3) unsigned NOT NULL,
  `title` varchar(300) NOT NULL,
  `mode` enum('read','write_own','forbidden') NOT NULL DEFAULT 'read' COMMENT 'IAF-0043 — ولا كتابةَ على السجلاتِ الأصلية بحال',
  `accept_test` varchar(400) NOT NULL DEFAULT '',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_a` (`company_id`,`code`),
  KEY `ix_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-01 §4-4 — صلاحياتُ المراجعِ داخلَ النظامِ الاثنتا عشرة';

-- ── Table: iaf_charter ──
CREATE TABLE `iaf_charter` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `version` varchar(20) NOT NULL,
  `functional_line` enum('board','audit_committee','ceo') NOT NULL DEFAULT 'ceo' COMMENT 'IAF-0002 — مجلسٌ أو لجنةٌ · وعند عدمهما الرئيسُ بميثاقٍ مؤقت',
  `admin_line` varchar(120) NOT NULL DEFAULT 'الرئيس التنفيذي — إداريًّا فقط',
  `purpose` varchar(600) NOT NULL DEFAULT '',
  `authority` varchar(600) NOT NULL DEFAULT '',
  `independence` varchar(600) NOT NULL DEFAULT '',
  `not_following` varchar(300) NOT NULL DEFAULT 'لا المالية ولا رئيس الحسابات ولا الحوكمة',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `state` enum('draft','approved','superseded') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ch` (`company_id`,`version`),
  KEY `ix_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-01 §4-1 — ميثاقُ المراجعةِ والاستقلال';

-- ── Table: iaf_competencies ──
CREATE TABLE `iaf_competencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code` varchar(12) NOT NULL COMMENT 'IAF-C01 ..',
  `seq` tinyint(3) unsigned NOT NULL,
  `title` varchar(300) NOT NULL COMMENT 'الاختصاصُ كما تسميه الوثيقة',
  `accept_test` varchar(400) NOT NULL DEFAULT '' COMMENT 'شاهدُ قبولِه',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_c` (`company_id`,`code`),
  KEY `ix_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-01 §4-3 — اختصاصاتُ المراجعةِ العشرون';

-- ── Table: iaf_engagements ──
CREATE TABLE `iaf_engagements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `engagement_no` varchar(40) NOT NULL,
  `plan_id` int(10) unsigned NOT NULL COMMENT 'IAF-0044 — لا مهمةَ بلا خطة',
  `area_code` varchar(40) NOT NULL,
  `title` varchar(200) NOT NULL,
  `lead_auditor` int(10) unsigned NOT NULL,
  `audit_kind` enum('financial','operational','it','compliance','fraud') NOT NULL DEFAULT 'operational',
  `started_at` date DEFAULT NULL,
  `ended_at` date DEFAULT NULL,
  `state` enum('planned','fieldwork','reporting','closed') NOT NULL DEFAULT 'planned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eng` (`company_id`,`engagement_no`),
  KEY `ix_plan` (`plan_id`),
  KEY `ix_area` (`area_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-01 §4-5 — مهامُّ المراجعة';

-- ── Table: iaf_findings ──
CREATE TABLE `iaf_findings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `finding_no` varchar(40) NOT NULL,
  `engagement_id` int(10) unsigned NOT NULL,
  `area_code` varchar(40) NOT NULL DEFAULT '',
  `auditee_dept` varchar(120) NOT NULL DEFAULT '' COMMENT 'الإدارةُ المُراجَعة',
  `auditee_user_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(300) NOT NULL,
  `detail` mediumtext DEFAULT NULL,
  `severity` enum('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  `raised_by` int(10) unsigned NOT NULL COMMENT 'المراجعُ الداخليُّ حصرًا',
  `raised_at` datetime NOT NULL,
  `response_due` date DEFAULT NULL,
  `response_text` mediumtext DEFAULT NULL,
  `responded_by` int(10) unsigned DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `action_plan` mediumtext DEFAULT NULL,
  `action_owner` int(10) unsigned DEFAULT NULL,
  `action_due` date DEFAULT NULL,
  `evidence_ref` varchar(300) NOT NULL DEFAULT '',
  `evidence_accepted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '◆ لا إغلاقَ بلا قبولِ المراجعِ للدليل — ولو من الرئيس',
  `accepted_by` int(10) unsigned DEFAULT NULL COMMENT 'المراجعُ الذي قَبِل الدليل',
  `closed_by` int(10) unsigned DEFAULT NULL COMMENT '◆ المراجعُ حصرًا — لا الإدارةُ المُراجَعة',
  `closed_at` datetime DEFAULT NULL,
  `state` enum('open','responded','in_remediation','evidence_submitted','closed','escalated') NOT NULL DEFAULT 'open',
  `escalated_at` datetime DEFAULT NULL,
  `escalated_to` enum('ceo','board','audit_committee') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_find` (`company_id`,`finding_no`),
  KEY `ix_state` (`company_id`,`state`,`severity`),
  KEY `ix_eng` (`engagement_id`),
  KEY `ix_due` (`company_id`,`action_due`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-01 §4-5 — ملاحظاتُ المراجعةِ ودورتُها';

-- ── Table: iaf_independence ──
CREATE TABLE `iaf_independence` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `auditor_id` int(10) unsigned NOT NULL,
  `scope_ref` varchar(120) NOT NULL DEFAULT '' COMMENT 'فارغٌ = الإقرارُ السنويّ',
  `declared_at` datetime NOT NULL,
  `has_conflict` tinyint(1) NOT NULL DEFAULT 0,
  `conflict_note` varchar(400) NOT NULL DEFAULT '',
  `valid_until` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ind` (`company_id`,`auditor_id`,`scope_ref`),
  KEY `ix_valid` (`company_id`,`valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-0009 — إقرارُ الاستقلالِ سنويًّا وقبلَ كل تكليف';

-- ── Table: iaf_plan ──
CREATE TABLE `iaf_plan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `plan_year` smallint(5) unsigned NOT NULL,
  `charter_id` int(10) unsigned NOT NULL COMMENT 'IAF-0044 — لا خطةَ بلا ميثاق',
  `title` varchar(200) NOT NULL DEFAULT '',
  `basis` varchar(300) NOT NULL DEFAULT 'مبنيةٌ على المخاطر',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `state` enum('draft','approved','closed') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan` (`company_id`,`plan_year`),
  KEY `ix_charter` (`charter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-0015 — خطةُ المراجعةِ السنويةُ المبنيةُ على المخاطر';

-- ── Table: iaf_quality_reviews ──
CREATE TABLE `iaf_quality_reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `review_no` varchar(40) NOT NULL,
  `kind` enum('internal','external') NOT NULL DEFAULT 'internal',
  `period_label` varchar(60) NOT NULL DEFAULT '',
  `scope_label` varchar(300) NOT NULL DEFAULT '',
  `conformance` enum('conforms','partially_conforms','does_not_conform') DEFAULT NULL,
  `findings_count` int(10) unsigned NOT NULL DEFAULT 0,
  `summary` varchar(800) NOT NULL DEFAULT '',
  `reviewed_by` varchar(160) NOT NULL DEFAULT '' COMMENT 'الجهةُ المقيِّمة — داخليةٌ أو خارجية',
  `reviewed_at` datetime NOT NULL,
  `next_due` date DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_q` (`company_id`,`review_no`),
  KEY `ix_when` (`company_id`,`reviewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-0008 · IAF-0031 — تقييمُ جودةِ المراجعةِ الدوري';

-- ── Table: iaf_universe ──
CREATE TABLE `iaf_universe` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `area_code` varchar(40) NOT NULL,
  `area_name` varchar(200) NOT NULL,
  `owner_dept` varchar(120) NOT NULL DEFAULT '',
  `risk_score` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'IAF-0014 — التقييمُ السنويُّ للمخاطر',
  `last_audited` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_area` (`company_id`,`area_code`),
  KEY `ix_risk` (`company_id`,`risk_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-0013 — سجلُّ الكونِ الرقابي';

-- ── Table: iaf_workpapers ──
CREATE TABLE `iaf_workpapers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `engagement_id` int(10) unsigned NOT NULL,
  `wp_ref` varchar(60) NOT NULL,
  `title` varchar(200) NOT NULL DEFAULT '',
  `evidence_hash` char(64) NOT NULL DEFAULT '' COMMENT 'بصمةُ النسخةِ — تُثبت عدمَ التعديل',
  `captured_at` datetime NOT NULL,
  `captured_by` int(10) unsigned NOT NULL,
  `frozen` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'غيرُ قابلةٍ للتعديلِ بعد الالتقاط',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wp` (`company_id`,`engagement_id`,`wp_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IAF-0037 — أوراقُ العملِ ونسخُ الأدلةِ المجمَّدة';

-- ── Table: impact_matrix ──
CREATE TABLE `impact_matrix` (
  `mx_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` int(10) unsigned NOT NULL,
  `state_code` varchar(40) NOT NULL COMMENT 'حالة الإدارة',
  `party_type` enum('client','supplier','operator','company','financier') NOT NULL,
  `effect` enum('billable','countable','payable','penalized','none') NOT NULL,
  `derived_from` varchar(80) DEFAULT NULL COMMENT 'مرجع المصفوفة الأم (CON-02 §5) إن اشتُقت',
  PRIMARY KEY (`mx_id`),
  UNIQUE KEY `uq_mx` (`policy_id`,`state_code`,`party_type`),
  CONSTRAINT `fk_mx_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §8: مصفوفة الأثر — لا حالة بلا أثر معلن لكل طرف، ولا أثر يُستنتج';

-- ── Table: incentive_allocations ──
CREATE TABLE `incentive_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `rule_id` int(11) NOT NULL,
  `beneficiary_type` enum('employee','job_title') NOT NULL COMMENT 'شخصٌ بعينه أو صفةٌ تُحل وقتَ الاحتساب («مشغّلٌ ومساعدٌ ومشرف»)',
  `beneficiary_id` int(11) NOT NULL,
  `percent` decimal(5,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ia_beneficiary` (`rule_id`,`beneficiary_type`,`beneficiary_id`),
  KEY `ix_ia_rule` (`rule_id`),
  KEY `ix_ia_company` (`company_id`),
  CONSTRAINT `fk_ia_rule` FOREIGN KEY (`rule_id`) REFERENCES `incentive_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: incentive_rules ──
CREATE TABLE `incentive_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `incentive_type` varchar(50) NOT NULL COMMENT 'اسمُ الحافز من الاتفاق — لا قائمةَ مثبَّتةً في الكود',
  `basis` enum('unit','threshold','quality','readiness','safety','fuel','tier') NOT NULL COMMENT 'أسسُ §3.3 السبعة: وحدةٌ منفَّذة · تجاوزُ عتبة · جودة · جاهزية · التزامُ سلامة · توفيرُ وقود · شرائح',
  `rate` decimal(14,4) DEFAULT NULL,
  `threshold` decimal(18,2) DEFAULT NULL,
  `cap` decimal(18,2) DEFAULT NULL COMMENT 'السقف — بنص الشريحة §5.2-③',
  `floor` decimal(18,2) DEFAULT NULL COMMENT 'الحدُّ الأدنى',
  `periodicity` enum('monthly','periodic','once') NOT NULL DEFAULT 'monthly',
  `condition_text` varchar(255) DEFAULT NULL COMMENT 'شرطُ الاستحقاق نصًّا',
  `scope_type` enum('project','equipment_type','site') DEFAULT NULL COMMENT 'نطاقُ §3.3',
  `scope_id` int(11) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','replaced','ended') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_ir_contract` (`contract_id`),
  KEY `ix_ir_company` (`company_id`),
  CONSTRAINT `fk_ir_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: intercompany_dues ──
CREATE TABLE `intercompany_dues` (
  `due_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `loan_id` int(10) unsigned NOT NULL,
  `period` char(7) NOT NULL,
  `creditor_entity_id` int(10) unsigned NOT NULL,
  `debtor_entity_id` int(10) unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(8) NOT NULL,
  `state` enum('accrued','settled') NOT NULL DEFAULT 'accrued',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`due_id`),
  UNIQUE KEY `uq_icd` (`loan_id`,`period`,`creditor_entity_id`),
  CONSTRAINT `fk_icd_loan` FOREIGN KEY (`loan_id`) REFERENCES `intercompany_loans` (`loan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-09: المستحق المتبادل المسجَّل بين الكيانين — بنسب التحمل';

-- ── Table: intercompany_loans ──
CREATE TABLE `intercompany_loans` (
  `loan_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `lender_entity_id` int(10) unsigned NOT NULL COMMENT 'الكيان المعير — داخلي (is_tenant/داخل المجموعة)',
  `borrower_entity_id` int(10) unsigned NOT NULL COMMENT 'الكيان المستعير — داخلي',
  `date_from` date NOT NULL,
  `date_to` date DEFAULT NULL,
  `monthly_value` decimal(18,2) NOT NULL COMMENT 'القيمة المحاسبية الشهرية للإعارة',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `bearing_split_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'نسب التحمل بين الكيانين — Σ = 100 (تحرسه الخدمة)' CHECK (json_valid(`bearing_split_json`)),
  `internal_transaction` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'علامة معاملة بين كيانين داخليين — قيد ⑥',
  `state` enum('active','ended') NOT NULL DEFAULT 'active',
  `doc_ref` varchar(120) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`loan_id`),
  KEY `ix_icl_equipment` (`company_id`,`equipment_id`,`state`),
  KEY `fk_icl_lender` (`lender_entity_id`),
  KEY `fk_icl_borrower` (`borrower_entity_id`),
  CONSTRAINT `fk_icl_borrower` FOREIGN KEY (`borrower_entity_id`) REFERENCES `legal_entities` (`entity_id`),
  CONSTRAINT `fk_icl_lender` FOREIGN KEY (`lender_entity_id`) REFERENCES `legal_entities` (`entity_id`),
  CONSTRAINT `ck_icl_not_self` CHECK (`lender_entity_id` <> `borrower_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: job_titles ──
CREATE TABLE `job_titles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title_code` varchar(40) DEFAULT NULL COMMENT 'SEC-01 §12: الكود المعتمد',
  `company_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `family_code` varchar(40) DEFAULT NULL COMMENT 'العائلة — hr_dictionaries',
  `level_code` varchar(40) DEFAULT NULL,
  `org_unit_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `duties_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`duties_json`)),
  `default_manager_position_id` int(10) unsigned DEFAULT NULL,
  `functional_line_unit_id` int(10) unsigned DEFAULT NULL,
  `operational_line_unit_id` int(10) unsigned DEFAULT NULL,
  `template_id` int(10) unsigned DEFAULT NULL,
  `allowed_scopes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_scopes_json`)),
  `amount_cap` decimal(18,2) DEFAULT NULL,
  `currency` varchar(8) DEFAULT NULL,
  `prohibitions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`prohibitions_json`)),
  `qualifications_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`qualifications_json`)),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_operator` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jobtitle_company_name` (`company_id`,`name`),
  UNIQUE KEY `uq_jt_code` (`title_code`),
  KEY `idx_jobtitle_company` (`company_id`),
  KEY `idx_jobtitle_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: legal_entities ──
CREATE TABLE `legal_entities` (
  `entity_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `legal_name` varchar(255) NOT NULL,
  `legal_form` varchar(80) DEFAULT NULL,
  `country` varchar(60) NOT NULL DEFAULT 'SD',
  `registry_authority` varchar(120) NOT NULL DEFAULT 'السجل التجاري',
  `commercial_reg` varchar(80) NOT NULL,
  `tax_no` varchar(80) DEFAULT NULL,
  `base_currency` varchar(8) NOT NULL DEFAULT 'SDG' COMMENT 'عملة الدفاتر (functional_currency)',
  `is_tenant` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'كيانات المجموعة المستأجرة — حد العزل من tenants حصرًا',
  `ownership_completeness` enum('full','partial','unknown') NOT NULL DEFAULT 'unknown' COMMENT 'قيد المئة يُفرض عند full وحده',
  `state` enum('active','suspended','liquidation','closed') NOT NULL DEFAULT 'active',
  `registered_address` varchar(255) DEFAULT NULL,
  `founded_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`entity_id`),
  UNIQUE KEY `uq_le_registry` (`country`,`registry_authority`,`commercial_reg`) COMMENT 'الفرادة بالثلاثة معًا — الرقم قد يتكرر في دولتين',
  KEY `ix_le_tenant` (`is_tenant`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §2: الكيانات القانونية — سجل واحد لا يتكرر، ولا عمود صفات نصي ولا JSON';

-- ── Table: link_groups ──
CREATE TABLE `link_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'اسم المجموعة كما يظهر في السايدبار',
  `group_code` varchar(40) DEFAULT NULL,
  `owner_role_id` int(11) DEFAULT NULL COMMENT 'الدور المالك — نفس دلالة modules.owner_role_id',
  `icon` varchar(50) NOT NULL DEFAULT 'fa fa-folder',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'الأصغر يظهر أولاً',
  `stage_no` tinyint(4) DEFAULT NULL,
  `stage_title` varchar(190) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `ix_owner_role` (`owner_role_id`),
  KEY `ix_display_order` (`display_order`),
  KEY `idx_lg_code` (`owner_role_id`,`group_code`),
  CONSTRAINT `link_groups_role_fk` FOREIGN KEY (`owner_role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مجموعات روابط السايدبار — لكل دورٍ مجموعاته';

-- ── Table: messages ──
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف الفريد',
  `company_id` int(11) NOT NULL COMMENT 'رقم الشركة - لعزل الرسائل بين الشركات',
  `sender_id` int(11) NOT NULL COMMENT 'رقم المرسل (users.id)',
  `receiver_id` int(11) NOT NULL COMMENT 'رقم المستلم (users.id)',
  `message` text NOT NULL COMMENT 'نص الرسالة',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=غير مقروءة، 1=مقروءة',
  `read_at` datetime DEFAULT NULL COMMENT 'وقت القراءة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'وقت الإرسال',
  `is_deleted_sender` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'حُذفت من قِبل المرسل',
  `is_deleted_receiver` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'حُذفت من قِبل المستلم',
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `meter_type` enum('hour','km') NOT NULL DEFAULT 'hour' COMMENT 'UX-10 §8 نصًّا — لا ثالثَ لهما',
  `chain_no` int(11) NOT NULL DEFAULT 1 COMMENT 'سلسلةُ العدّاد — التصفيرُ الموثَّق يزيدها',
  `reading_date` date NOT NULL,
  `value` decimal(18,2) NOT NULL,
  `delta` decimal(18,2) DEFAULT NULL COMMENT 'الفارقُ عن سابقتها في السلسلة — NULL لأولها',
  `source` enum('manual','inspection','timesheet','reset') NOT NULL DEFAULT 'manual',
  `source_ref` varchar(80) DEFAULT NULL COMMENT 'مرجعُ الواقعة: TS-‹id› · INS-‹id›',
  `is_reset` tinyint(4) NOT NULL DEFAULT 0,
  `reset_reason` varchar(255) DEFAULT NULL,
  `reset_doc_ref` varchar(120) DEFAULT NULL COMMENT 'مستندُ قرار التصفير — إلزاميٌّ متى صُفّر',
  `note` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meter_reading_day` (`equipment_id`,`meter_type`,`reading_date`),
  KEY `ix_meter_latest` (`equipment_id`,`meter_type`,`chain_no`,`reading_date`),
  KEY `ix_meter_co` (`company_id`,`reading_date`),
  CONSTRAINT `fk_meter_reading_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`),
  CONSTRAINT `ck_meter_reset_doc` CHECK (`is_reset` = 0 or `reset_doc_ref` is not null and char_length(trim(`reset_doc_ref`)) > 0),
  CONSTRAINT `ck_meter_value` CHECK (`value` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_breakdown ──
CREATE TABLE `mnt_breakdown` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل الشركة (إجباري)',
  `code` varchar(50) DEFAULT NULL COMMENT 'مرجع البلاغ، مثل BR-2026-0001',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'FK→equipments.id (ربط رقمي)',
  `project_id` int(11) DEFAULT NULL COMMENT 'FK→project.id',
  `reported_by` int(11) DEFAULT NULL COMMENT 'FK→users.id (المُبلِّغ)',
  `reporter_dept` varchar(100) DEFAULT NULL COMMENT 'القسم المُبلِّغ',
  `target_role` int(11) DEFAULT NULL,
  `report_datetime` datetime DEFAULT NULL,
  `failure_code_id` int(11) DEFAULT NULL COMMENT 'FK→failure_codes.id (إعادة استخدام دون تعديل)',
  `severity` varchar(30) DEFAULT NULL COMMENT 'منخفضة/متوسطة/عالية/حرجة',
  `is_stopped` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'هل المعدة متوقفة',
  `description` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL COMMENT 'FK→mnt_order.id بعد التحويل لأمر',
  `state` varchar(30) NOT NULL DEFAULT 'جديد' COMMENT 'جديد/قيد التقييم/محوّل/مغلق',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_breakdown_eq_company_state` (`equipment_id`,`company_id`,`state`),
  KEY `idx_breakdown_company_state` (`company_id`,`state`),
  KEY `idx_breakdown_order` (`order_id`),
  KEY `idx_breakdown_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_inspection ──
CREATE TABLE `mnt_inspection` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل الشركة (إجباري)',
  `code` varchar(50) DEFAULT NULL COMMENT 'مرجع التفتيش، مثل INS-2026-0001',
  `inspection_type` varchar(50) NOT NULL DEFAULT 'دوري' COMMENT 'دوري/زيارة ميدانية/استلام/بعد حادث',
  `template_id` int(11) DEFAULT NULL COMMENT 'FK→mnt_inspection_template.id',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'FK→equipments.id',
  `supplier_id` int(11) DEFAULT NULL COMMENT 'FK→suppliers.id',
  `external_equipment` varchar(255) DEFAULT NULL COMMENT 'وصف معدة خارجية',
  `project_id` int(11) DEFAULT NULL COMMENT 'FK→project.id',
  `inspector_id` int(11) DEFAULT NULL COMMENT 'FK→users.id (الفاحص)',
  `scheduled_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `overall_result` varchar(50) DEFAULT NULL,
  `tech_readiness_state` varchar(50) DEFAULT NULL COMMENT 'الجاهزية الفنية',
  `equipment_condition` varchar(50) DEFAULT NULL COMMENT 'تُكتب لكرت المعدة عند الإكمال + تُخزّن',
  `engine_condition` varchar(50) DEFAULT NULL COMMENT 'تُكتب لكرت المعدة عند الإكمال + تُخزّن',
  `notes` text DEFAULT NULL,
  `state` varchar(30) NOT NULL DEFAULT 'جديد' COMMENT 'جديد/مجدول/قيد التنفيذ/مكتمل/مغلق',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inspection_equipment` (`equipment_id`),
  KEY `idx_inspection_company_state` (`company_id`,`state`),
  KEY `idx_inspection_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_inspection_line ──
CREATE TABLE `mnt_inspection_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `inspection_id` int(11) NOT NULL COMMENT 'FK→mnt_inspection.id',
  `template_line_id` int(11) DEFAULT NULL COMMENT 'مصدر البند من القالب',
  `component` varchar(150) NOT NULL,
  `section` varchar(150) DEFAULT NULL COMMENT 'المنظومة',
  `applies_to` varchar(80) DEFAULT NULL COMMENT 'ينطبق على',
  `check_method` varchar(120) DEFAULT NULL COMMENT 'طريقة الفحص',
  `measured_value` varchar(255) DEFAULT NULL COMMENT 'القيمة المقاسة/الحد',
  `note` text DEFAULT NULL COMMENT 'الملاحظة',
  `seq` int(11) NOT NULL DEFAULT 0,
  `is_template` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=بند قالب (لا يُحذف)',
  `condition_state` varchar(30) DEFAULT NULL COMMENT 'سليم/ملاحظة/حرج',
  `recommendation` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `photo_ref` varchar(190) DEFAULT NULL COMMENT 'M-34: مرجعُ صورة البند',
  `converted_ticket_id` int(11) DEFAULT NULL COMMENT 'M-34: بلاغُ NoteConverted — ولا يتكرر',
  PRIMARY KEY (`id`),
  KEY `idx_inspline_inspection` (`inspection_id`),
  CONSTRAINT `fk_inspline_inspection` FOREIGN KEY (`inspection_id`) REFERENCES `mnt_inspection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_inspection_template ──
CREATE TABLE `mnt_inspection_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL COMMENT 'NULL = قالب عام مشترك لكل الشركات',
  `type_code` varchar(30) NOT NULL COMMENT 'EQUIP-MNT-DLY ...',
  `name` varchar(150) NOT NULL COMMENT 'اسم الاستمارة',
  `inspection_type` varchar(80) NOT NULL COMMENT 'قيمة mnt_inspection.inspection_type',
  `header_type` varchar(20) NOT NULL DEFAULT 'equipment' COMMENT 'equipment/supplier/external',
  `condition_scale` varchar(20) NOT NULL DEFAULT 'default' COMMENT 'default/accident/overhaul',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tpl_company_code` (`company_id`,`type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_inspection_template_line ──
CREATE TABLE `mnt_inspection_template_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `section` varchar(150) DEFAULT NULL COMMENT 'المنظومة/المجموعة',
  `seq` int(11) NOT NULL DEFAULT 0,
  `item` varchar(255) NOT NULL COMMENT 'البند',
  `applies_to` varchar(80) DEFAULT NULL COMMENT 'ينطبق على: عام/حفّار/قلّاب/دريل/لودر',
  `check_method` varchar(120) DEFAULT NULL COMMENT 'طريقة الفحص',
  `reference_limit` varchar(255) DEFAULT NULL COMMENT 'القيمة المقاسة / الحد المرجعي',
  PRIMARY KEY (`id`),
  KEY `idx_tplline_template` (`template_id`),
  CONSTRAINT `fk_tplline_template` FOREIGN KEY (`template_id`) REFERENCES `mnt_inspection_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_lookup ──
CREATE TABLE `mnt_lookup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل الشركة (إجباري)',
  `type` varchar(40) NOT NULL COMMENT 'سبب عطل/سبب توقّف/نوع مهمة/ورشة',
  `name` varchar(150) NOT NULL,
  `extra` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lookup_company_type` (`company_id`,`type`),
  KEY `idx_lookup_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_order ──
CREATE TABLE `mnt_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل الشركة (إجباري)',
  `code` varchar(50) DEFAULT NULL COMMENT 'مرجع الأمر، مثل MNT-2026-0001',
  `breakdown_id` int(11) DEFAULT NULL COMMENT 'FK→mnt_breakdown.id (مصدر بلاغ)',
  `plan_id` int(11) DEFAULT NULL COMMENT 'FK→mnt_plan.id (مصدر وقائي)',
  `inspection_id` int(11) DEFAULT NULL COMMENT 'FK→mnt_inspection.id (مصدر تفتيش)',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'FK→equipments.id',
  `project_id` int(11) DEFAULT NULL COMMENT 'FK→project.id',
  `source` varchar(20) NOT NULL DEFAULT 'بلاغ' COMMENT 'بلاغ/وقائي/تفتيش',
  `is_auto` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Ïú┘àÏ▒ ÏÁ┘èÏº┘åÏ® Ïú┘Å┘åÏ┤Ïª Ï¬┘ä┘éÏºÏª┘è┘ïÏº ┘à┘å ÏÁ┘üÏ¡Ï® Ïº┘äÏ¡Ï▒┘âÏ®',
  `maint_type` varchar(50) DEFAULT NULL COMMENT 'نوع الصيانة',
  `priority` varchar(20) DEFAULT NULL,
  `cost_party` varchar(20) DEFAULT NULL COMMENT 'جهة التكلفة: داخلي/خارجي',
  `charge_supplier_id` int(11) DEFAULT NULL COMMENT 'مورّدُ الآليات الذي تُحمَّل عليه تكلفةُ الأمر — يُفعّل حين cost_party=خارجي',
  `vendor_id` int(11) DEFAULT NULL COMMENT 'FK→suppliers.id (ورشة خارجية)',
  `workshop` varchar(150) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL COMMENT 'FK→users.id (الفني)',
  `supervisor_id` int(11) DEFAULT NULL COMMENT 'FK→users.id (المشرف)',
  `failure_code_id` int(11) DEFAULT NULL COMMENT 'FK→failure_codes.id',
  `diagnosis` text DEFAULT NULL,
  `root_cause_id` int(11) DEFAULT NULL COMMENT 'FK→mnt_lookup.id (سبب جذري)',
  `actions_taken` text DEFAULT NULL,
  `work_start` datetime DEFAULT NULL,
  `work_end` datetime DEFAULT NULL,
  `downtime_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `labor_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `parts_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `external_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inspection_result` varchar(20) DEFAULT NULL COMMENT 'ناجح/راسب',
  `state` varchar(20) NOT NULL DEFAULT 'بلاغ' COMMENT 'بلاغ/تنفيذ/فحص/إغلاق/ملغى',
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `waiting_part_since` date DEFAULT NULL COMMENT 'M-32: تاريخُ دخول WaitingPart — العدّادُ يُحسب منه',
  `pm_cycle_key` varchar(80) DEFAULT NULL COMMENT 'M-36: plan:{id}:eq:{id}:due:{date} — يمنع توليدَ الدورة مرتين',
  `readiness_cert_ref` varchar(190) DEFAULT NULL COMMENT 'INJ-0074: مرجعُ شهادةِ الجاهزيةِ الفنية — وتاريخُها ومُصدرُها في closed_at/closed_by',
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL COMMENT 'FK→mnt_order.id',
  `employee_id` int(11) DEFAULT NULL COMMENT 'FK→users.id (اختياري)',
  `role` varchar(100) DEFAULT NULL,
  `hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `hourly_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_labor_order` (`order_id`),
  CONSTRAINT `fk_labor_order` FOREIGN KEY (`order_id`) REFERENCES `mnt_order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_order_part ──
CREATE TABLE `mnt_order_part` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL COMMENT 'FK→mnt_order.id',
  `part_name` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_major_component` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_part_order` (`order_id`),
  CONSTRAINT `fk_part_order` FOREIGN KEY (`order_id`) REFERENCES `mnt_order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_plan ──
CREATE TABLE `mnt_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل الشركة (إجباري)',
  `code` varchar(50) DEFAULT NULL COMMENT 'مرجع الخطة، مثل PLN-2026-0001',
  `name` varchar(200) NOT NULL,
  `scope` varchar(50) DEFAULT NULL COMMENT 'معدة/فئة',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'FK→equipments.id',
  `category_id` int(11) DEFAULT NULL COMMENT 'FK→equipments_types.id',
  `trigger_basis` varchar(20) NOT NULL DEFAULT 'ساعات' COMMENT 'ساعات/زمن',
  `interval_value` int(11) DEFAULT NULL COMMENT 'الفاصل (ساعات أو أيام)',
  `tolerance` int(11) DEFAULT NULL,
  `last_done_date` date DEFAULT NULL,
  `last_done_meter` decimal(12,2) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `next_due_meter` decimal(12,2) DEFAULT NULL,
  `state` varchar(30) NOT NULL DEFAULT 'نشطة' COMMENT 'نشطة/متوقفة',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_plan_eq_due` (`equipment_id`,`next_due_date`),
  KEY `idx_plan_company_state` (`company_id`,`state`),
  KEY `idx_plan_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: mnt_plan_task ──
CREATE TABLE `mnt_plan_task` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL COMMENT 'FK→mnt_plan.id',
  `name` varchar(200) NOT NULL,
  `task_type` int(11) DEFAULT NULL COMMENT 'FK→mnt_lookup.id (نوع مهمة)',
  `component` varchar(150) DEFAULT NULL,
  `est_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_plantask_plan` (`plan_id`),
  CONSTRAINT `fk_plantask_plan` FOREIGN KEY (`plan_id`) REFERENCES `mnt_plan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: modules ──
CREATE TABLE `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `owner_role_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `is_link` varchar(10) NOT NULL DEFAULT '0',
  `is_quick` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'تظهر في روابط الوصول السريع بلوحة التحكم',
  `icon` varchar(50) NOT NULL,
  `display_order` int(11) DEFAULT 0 COMMENT 'ترتيب العرض في القوائم',
  PRIMARY KEY (`id`),
  KEY `owner_role_id` (`owner_role_id`),
  KEY `idx_display_order` (`display_order`),
  KEY `ix_modules_group` (`group_id`),
  CONSTRAINT `modules_group_fk` FOREIGN KEY (`group_id`) REFERENCES `link_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`owner_role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: monthly_performance ──
CREATE TABLE `monthly_performance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `contract_id` int(10) unsigned NOT NULL,
  `container_id` int(10) unsigned NOT NULL COMMENT 'حاوية المقعد (op_containers · level=معدة · seat_no)',
  `period` char(7) NOT NULL COMMENT 'YYYY-MM',
  `contract_hours` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'التعاقدية (من contract_hours_monthly للمقعد)',
  `executed_hours` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'المنفَّذة — مجمَّعة من container_consumption',
  `executed_base_hours` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'الأساسية المنفَّذة (دون الإضافي)',
  `standby_hours` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'الاستعداد',
  `available_hours` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'المتاحة',
  `shortfall_hours` decimal(10,2) GENERATED ALWAYS AS (greatest(`contract_hours` - `executed_hours` - `standby_hours`,0)) STORED COMMENT 'العجز عن التعاقد — محسوب',
  `completion_pct` decimal(6,2) GENERATED ALWAYS AS (if(`contract_hours` > 0,round(`executed_hours` / `contract_hours` * 100,2),NULL)) STORED COMMENT 'نسبة الإنجاز — محسوبة',
  `trips` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tons` decimal(14,2) NOT NULL DEFAULT 0.00,
  `meters` decimal(14,2) NOT NULL DEFAULT 0.00,
  `fuel_consumed` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'وقود مستهلك',
  `state` enum('open','closed') NOT NULL DEFAULT 'open',
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mp_seat_period` (`company_id`,`container_id`,`period`),
  KEY `ix_mp_contract` (`company_id`,`contract_id`,`period`),
  KEY `fk_mp_container` (`container_id`),
  CONSTRAINT `fk_mp_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `ck_mp_hours` CHECK (`contract_hours` >= 0 and `executed_hours` >= 0 and `standby_hours` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: monthly_performance_downtime ──
CREATE TABLE `monthly_performance_downtime` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `perf_id` int(10) unsigned NOT NULL,
  `reason_code` varchar(40) NOT NULL COMMENT 'من stop_reason_codes حصرًا',
  `hours` decimal(10,2) NOT NULL,
  `obligation_id` int(11) NOT NULL COMMENT 'بند الالتزام المقابل — إلزامي (سبب بلا بند لا يُقبل)',
  `bearer_party` enum('client','company','supplier','operator','none') NOT NULL COMMENT 'الطرف المتحمل — مُشتق من البند وقت التسجيل، لا يُكتب حرًّا',
  `effect_on_billing` enum('billable_standby','non_billable','per_clause') NOT NULL DEFAULT 'per_clause' COMMENT 'لقطة أثر البند على الفوترة',
  `note` varchar(200) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mpd_reason` (`perf_id`,`reason_code`),
  KEY `ix_mpd_company` (`company_id`,`perf_id`),
  KEY `fk_mpd_reason` (`reason_code`),
  KEY `fk_mpd_obligation` (`obligation_id`),
  CONSTRAINT `fk_mpd_obligation` FOREIGN KEY (`obligation_id`) REFERENCES `contract_obligations` (`id`),
  CONSTRAINT `fk_mpd_perf` FOREIGN KEY (`perf_id`) REFERENCES `monthly_performance` (`id`),
  CONSTRAINT `fk_mpd_reason` FOREIGN KEY (`reason_code`) REFERENCES `stop_reason_codes` (`code`),
  CONSTRAINT `ck_mpd_hours` CHECK (`hours` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: nav09_action_alias ──
CREATE TABLE `nav09_action_alias` (
  `old_code` varchar(60) NOT NULL,
  `new_code` varchar(60) NOT NULL,
  `canonical_file` varchar(80) DEFAULT NULL COMMENT 'محدِّد السياق حين يكون القديم غامضًا',
  `note` varchar(255) DEFAULT NULL,
  `status` enum('active','planned','retired') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`old_code`,`new_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: nav09_action_map ──
CREATE TABLE `nav09_action_map` (
  `canonical_code` varchar(60) NOT NULL,
  `label_ar` varchar(190) NOT NULL,
  `screen_title` varchar(190) NOT NULL,
  `canonical_file` varchar(80) DEFAULT NULL,
  `actor_ar` varchar(120) DEFAULT NULL,
  `writes_text` varchar(255) DEFAULT NULL,
  `event_name` varchar(80) DEFAULT NULL,
  `consumers_text` varchar(255) DEFAULT NULL,
  `effect_text` varchar(500) DEFAULT NULL,
  `reverse_text` varchar(255) DEFAULT NULL,
  `live_code` varchar(80) DEFAULT NULL,
  `state` enum('alias','pending','bound_page','declared_unbuilt') NOT NULL DEFAULT 'pending',
  `guard_verified` enum('pending','yes','no','n_a') NOT NULL DEFAULT 'pending' COMMENT '⑤ أيمنعه حارسٌ في الخادم؟ — شاهدُه محاولةٌ غيرُ مخوَّلةٍ تُرفض برمز',
  `guard_evidence` varchar(190) NOT NULL DEFAULT '' COMMENT 'دليلُ حكمِ الحارس — رمزُ الرفضِ أو مسارُ الفحص',
  `idempotency_verified` enum('pending','yes','no','n_a') NOT NULL DEFAULT 'pending' COMMENT '⑩ أتُرفض إعادةُ النداء؟ — الإعادةُ ترجع مرجعَ الأول',
  `idempotency_evidence` varchar(190) NOT NULL DEFAULT '',
  `uat_verified` enum('pending','yes','no','n_a') NOT NULL DEFAULT 'pending' COMMENT '⑫ أاجتاز رحلةً حيةً بمستخدمٍ حقيقي؟ — محضرُ UAT موقَّع',
  `uat_evidence` varchar(190) NOT NULL DEFAULT '',
  `write_class` enum('read_only','domain_write','governance_write','external_side_effect') DEFAULT NULL COMMENT 'U10 ورقة 21 — تصنيف الكتابة ولازم التدقيق',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`canonical_code`),
  KEY `ix_n9a_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: nav09_file_map ──
CREATE TABLE `nav09_file_map` (
  `canonical_file` varchar(80) NOT NULL,
  `title_ar` varchar(190) NOT NULL,
  `owner_dept` varchar(64) NOT NULL,
  `state` enum('live','mapped','soon') NOT NULL DEFAULT 'soon',
  `real_path` varchar(190) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`canonical_file`),
  KEY `ix_n9m_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: nav_items ──
CREATE TABLE `nav_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL COMMENT 'الدور المالك لهذا العنصر في قائمته',
  `door` varchar(16) NOT NULL COMMENT 'HOME·DAILY·APPR·REC·REP·SET — الأبواب الستة',
  `group_id` int(11) DEFAULT NULL COMMENT 'link_groups — مجموعةٌ قابلةٌ للطيّ داخل الباب؛ NULL = مباشرةً تحته',
  `module_id` int(11) DEFAULT NULL COMMENT 'modules.id حين يكون العنصر شاشةً مسجَّلة — مرجعُ الصلاحية والاسم',
  `label_ar` varchar(64) NOT NULL COMMENT 'اسم العرض؛ يُفحص خلوّه من المحظور المعماري عند الحفظ',
  `route` varchar(128) NOT NULL COMMENT 'المسار كما في سجل الشاشات',
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'الترتيب داخل الباب/المجموعة',
  `counter_source` varchar(64) DEFAULT NULL COMMENT 'مُعرِّف العدّاد من سجل العدّادات — عدّادٌ واحدٌ بقيمةٍ واحدة',
  `permission_code` varchar(128) DEFAULT NULL COMMENT 'كود الشاشة لفحص can_view؛ NULL = ظهورٌ بلا فحص (ثوابت)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nav_role_route` (`role_id`,`route`),
  KEY `ix_nav_role_door` (`role_id`,`door`,`sort_order`),
  KEY `ix_nav_group` (`group_id`),
  KEY `ix_nav_module` (`module_id`),
  CONSTRAINT `chk_nav_route_not_relative` CHECK (`route` is null or `route`  not like '../%'),
  CONSTRAINT `chk_nav_door` CHECK (`door` in ('HOME','DAILY','APPR','REC','REP','SET','GOV','FIN','RISK')),
  CONSTRAINT `chk_nav_items_module_or_code` CHECK (`permission_code` is null or `permission_code` = '' or `module_id` is not null and `module_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: nav_items_archive_alias ──
CREATE TABLE `nav_items_archive_alias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL COMMENT 'الدور المالك لهذا العنصر في قائمته',
  `door` varchar(16) NOT NULL COMMENT 'HOME·DAILY·APPR·REC·REP·SET — الأبواب الستة',
  `group_id` int(11) DEFAULT NULL COMMENT 'link_groups — مجموعةٌ قابلةٌ للطيّ داخل الباب؛ NULL = مباشرةً تحته',
  `module_id` int(11) DEFAULT NULL COMMENT 'modules.id حين يكون العنصر شاشةً مسجَّلة — مرجعُ الصلاحية والاسم',
  `label_ar` varchar(64) NOT NULL COMMENT 'اسم العرض؛ يُفحص خلوّه من المحظور المعماري عند الحفظ',
  `route` varchar(128) NOT NULL COMMENT 'المسار كما في سجل الشاشات',
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'الترتيب داخل الباب/المجموعة',
  `counter_source` varchar(64) DEFAULT NULL COMMENT 'مُعرِّف العدّاد من سجل العدّادات — عدّادٌ واحدٌ بقيمةٍ واحدة',
  `permission_code` varchar(128) DEFAULT NULL COMMENT 'كود الشاشة لفحص can_view؛ NULL = ظهورٌ بلا فحص (ثوابت)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nav_role_route` (`role_id`,`route`),
  KEY `ix_nav_role_door` (`role_id`,`door`,`sort_order`),
  KEY `ix_nav_group` (`group_id`),
  KEY `ix_nav_module` (`module_id`),
  CONSTRAINT `chk_nav_route_not_relative` CHECK (`route` is null or `route`  not like '../%'),
  CONSTRAINT `chk_nav_door` CHECK (`door` in ('HOME','DAILY','APPR','REC','REP','SET','GOV','FIN','RISK')),
  CONSTRAINT `chk_nav_items_module_or_code` CHECK (`permission_code` is null or `permission_code` = '' or `module_id` is not null and `module_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: nav_items_archive_anchors ──
CREATE TABLE `nav_items_archive_anchors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL COMMENT 'الدور المالك لهذا العنصر في قائمته',
  `door` varchar(16) NOT NULL COMMENT 'HOME·DAILY·APPR·REC·REP·SET — الأبواب الستة',
  `group_id` int(11) DEFAULT NULL COMMENT 'link_groups — مجموعةٌ قابلةٌ للطيّ داخل الباب؛ NULL = مباشرةً تحته',
  `module_id` int(11) DEFAULT NULL COMMENT 'modules.id حين يكون العنصر شاشةً مسجَّلة — مرجعُ الصلاحية والاسم',
  `label_ar` varchar(64) NOT NULL COMMENT 'اسم العرض؛ يُفحص خلوّه من المحظور المعماري عند الحفظ',
  `route` varchar(128) NOT NULL COMMENT 'المسار كما في سجل الشاشات',
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'الترتيب داخل الباب/المجموعة',
  `counter_source` varchar(64) DEFAULT NULL COMMENT 'مُعرِّف العدّاد من سجل العدّادات — عدّادٌ واحدٌ بقيمةٍ واحدة',
  `permission_code` varchar(128) DEFAULT NULL COMMENT 'كود الشاشة لفحص can_view؛ NULL = ظهورٌ بلا فحص (ثوابت)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nav_role_route` (`role_id`,`route`),
  KEY `ix_nav_role_door` (`role_id`,`door`,`sort_order`),
  KEY `ix_nav_group` (`group_id`),
  KEY `ix_nav_module` (`module_id`),
  CONSTRAINT `chk_nav_route_not_relative` CHECK (`route` is null or `route`  not like '../%'),
  CONSTRAINT `chk_nav_door` CHECK (`door` in ('HOME','DAILY','APPR','REC','REP','SET','GOV','FIN','RISK')),
  CONSTRAINT `chk_nav_items_module_or_code` CHECK (`permission_code` is null or `permission_code` = '' or `module_id` is not null and `module_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: nav_items_archive_chats ──
CREATE TABLE `nav_items_archive_chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL COMMENT 'الدور المالك لهذا العنصر في قائمته',
  `door` varchar(16) NOT NULL COMMENT 'HOME·DAILY·APPR·REC·REP·SET — الأبواب الستة',
  `group_id` int(11) DEFAULT NULL COMMENT 'link_groups — مجموعةٌ قابلةٌ للطيّ داخل الباب؛ NULL = مباشرةً تحته',
  `module_id` int(11) DEFAULT NULL COMMENT 'modules.id حين يكون العنصر شاشةً مسجَّلة — مرجعُ الصلاحية والاسم',
  `label_ar` varchar(64) NOT NULL COMMENT 'اسم العرض؛ يُفحص خلوّه من المحظور المعماري عند الحفظ',
  `route` varchar(128) NOT NULL COMMENT 'المسار كما في سجل الشاشات',
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'الترتيب داخل الباب/المجموعة',
  `counter_source` varchar(64) DEFAULT NULL COMMENT 'مُعرِّف العدّاد من سجل العدّادات — عدّادٌ واحدٌ بقيمةٍ واحدة',
  `permission_code` varchar(128) DEFAULT NULL COMMENT 'كود الشاشة لفحص can_view؛ NULL = ظهورٌ بلا فحص (ثوابت)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nav_role_route` (`role_id`,`route`),
  KEY `ix_nav_role_door` (`role_id`,`door`,`sort_order`),
  KEY `ix_nav_group` (`group_id`),
  KEY `ix_nav_module` (`module_id`),
  CONSTRAINT `chk_nav_route_not_relative` CHECK (`route` is null or `route`  not like '../%'),
  CONSTRAINT `chk_nav_door` CHECK (`door` in ('HOME','DAILY','APPR','REC','REP','SET','GOV','FIN','RISK')),
  CONSTRAINT `chk_nav_items_module_or_code` CHECK (`permission_code` is null or `permission_code` = '' or `module_id` is not null and `module_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: nav_items_archive_dupes ──
CREATE TABLE `nav_items_archive_dupes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL COMMENT 'الدور المالك لهذا العنصر في قائمته',
  `door` varchar(16) NOT NULL COMMENT 'HOME·DAILY·APPR·REC·REP·SET — الأبواب الستة',
  `group_id` int(11) DEFAULT NULL COMMENT 'link_groups — مجموعةٌ قابلةٌ للطيّ داخل الباب؛ NULL = مباشرةً تحته',
  `module_id` int(11) DEFAULT NULL COMMENT 'modules.id حين يكون العنصر شاشةً مسجَّلة — مرجعُ الصلاحية والاسم',
  `label_ar` varchar(64) NOT NULL COMMENT 'اسم العرض؛ يُفحص خلوّه من المحظور المعماري عند الحفظ',
  `route` varchar(128) NOT NULL COMMENT 'المسار كما في سجل الشاشات',
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'الترتيب داخل الباب/المجموعة',
  `counter_source` varchar(64) DEFAULT NULL COMMENT 'مُعرِّف العدّاد من سجل العدّادات — عدّادٌ واحدٌ بقيمةٍ واحدة',
  `permission_code` varchar(128) DEFAULT NULL COMMENT 'كود الشاشة لفحص can_view؛ NULL = ظهورٌ بلا فحص (ثوابت)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nav_role_route` (`role_id`,`route`),
  KEY `ix_nav_role_door` (`role_id`,`door`,`sort_order`),
  KEY `ix_nav_group` (`group_id`),
  KEY `ix_nav_module` (`module_id`),
  CONSTRAINT `chk_nav_route_not_relative` CHECK (`route` is null or `route`  not like '../%'),
  CONSTRAINT `chk_nav_door` CHECK (`door` in ('HOME','DAILY','APPR','REC','REP','SET','GOV','FIN','RISK')),
  CONSTRAINT `chk_nav_items_module_or_code` CHECK (`permission_code` is null or `permission_code` = '' or `module_id` is not null and `module_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: nav_items_archive_views ──
CREATE TABLE `nav_items_archive_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL COMMENT 'الدور المالك لهذا العنصر في قائمته',
  `door` varchar(16) NOT NULL COMMENT 'HOME·DAILY·APPR·REC·REP·SET — الأبواب الستة',
  `group_id` int(11) DEFAULT NULL COMMENT 'link_groups — مجموعةٌ قابلةٌ للطيّ داخل الباب؛ NULL = مباشرةً تحته',
  `module_id` int(11) DEFAULT NULL COMMENT 'modules.id حين يكون العنصر شاشةً مسجَّلة — مرجعُ الصلاحية والاسم',
  `label_ar` varchar(64) NOT NULL COMMENT 'اسم العرض؛ يُفحص خلوّه من المحظور المعماري عند الحفظ',
  `route` varchar(128) NOT NULL COMMENT 'المسار كما في سجل الشاشات',
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'الترتيب داخل الباب/المجموعة',
  `counter_source` varchar(64) DEFAULT NULL COMMENT 'مُعرِّف العدّاد من سجل العدّادات — عدّادٌ واحدٌ بقيمةٍ واحدة',
  `permission_code` varchar(128) DEFAULT NULL COMMENT 'كود الشاشة لفحص can_view؛ NULL = ظهورٌ بلا فحص (ثوابت)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nav_role_route` (`role_id`,`route`),
  KEY `ix_nav_role_door` (`role_id`,`door`,`sort_order`),
  KEY `ix_nav_group` (`group_id`),
  KEY `ix_nav_module` (`module_id`),
  CONSTRAINT `chk_nav_route_not_relative` CHECK (`route` is null or `route`  not like '../%'),
  CONSTRAINT `chk_nav_door` CHECK (`door` in ('HOME','DAILY','APPR','REC','REP','SET','GOV','FIN','RISK')),
  CONSTRAINT `chk_nav_items_module_or_code` CHECK (`permission_code` is null or `permission_code` = '' or `module_id` is not null and `module_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: nav_redirects ──
CREATE TABLE `nav_redirects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `old_route` varchar(128) NOT NULL,
  `new_route` varchar(128) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `hits` int(11) NOT NULL DEFAULT 0 COMMENT 'عدّادُ استعمالٍ يقيس أمان الحذف لاحقًا',
  `last_hit_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_navred_old` (`old_route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تحويلُ المسارات القديمة — UX-01 §10.2';

-- ── Table: op_containers ──
CREATE TABLE `op_containers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `container_no` varchar(40) NOT NULL COMMENT 'CNT-سنة-تسلسل — ترقيمٌ خادمي',
  `level` enum('رئيسية','مورد','نوع','معدة','مشغّل') NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL للرئيسية حصرًا — يحرسه ck_container_parent',
  `contract_id` int(10) unsigned NOT NULL,
  `contract_item_id` int(10) unsigned DEFAULT NULL COMMENT 'contractequipments.id — مصدرُ سقف الرئيسية',
  `obl_id` int(10) unsigned DEFAULT NULL COMMENT 'CAP-01 §16: التزامُ نوع المعدة (contract_commitments.id) — مضافٌ صراحةً ليصحَّ قيدُ التطابق (C21)',
  `resource_plan_id` int(10) unsigned DEFAULT NULL COMMENT 'صفُّ خطة الموارد الذي بُذرت منه الحاوية (P-04) — والقديمُ يبقى على contract_item_id',
  `unit_type` enum('hour','ton','meter','cbm','day','shift','trip') NOT NULL DEFAULT 'hour' COMMENT 'وحدةُ البند — والسقفُ والمستهلَكُ بها',
  `work_model` varchar(40) DEFAULT NULL COMMENT 'نموذجُ العمل كما في البند',
  `cap_qty` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT 'السقف — لا يُتجاوز',
  `allocated_qty` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT 'Σ ما وُزّع على الأبناء — قيدُ Σ البنيوي',
  `consumed_qty` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT 'Σ ما استُهلك فعلًا',
  `remaining_qty` decimal(16,2) GENERATED ALWAYS AS (`cap_qty` - `consumed_qty`) STORED COMMENT 'مولَّدٌ لا يُكتب — فلا مصدرانِ للرقم الواحد',
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `equipment_id` int(10) unsigned DEFAULT NULL,
  `operator_employee_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL COMMENT 'الموقع — مفتاحُ الحجب المرحليّ (المرحلة ③)',
  `role_kind` enum('أساسية','احتياطية','أساسي','بديل أول','بديل ثانٍ','مشترك') DEFAULT NULL,
  `seat_no` smallint(5) unsigned DEFAULT NULL COMMENT 'N-11: رقم المقعد التعاقدي — فريد داخل العقد لمستوى معدة',
  `seat_kind` enum('contractual_seat','operational_resource_slot','supplier_allocation') DEFAULT NULL COMMENT 'N-11: نوع المقعد — يُشتق من فصل بند البيع عن خطة الموارد (PLAN-03 §4) لا يُصنَّف مستقلًّا',
  `seat_equipment_type_id` int(10) unsigned DEFAULT NULL COMMENT 'N-11: نوع المعدة المطلوب للمقعد (equipments_types.id)',
  `contract_hours_monthly` decimal(10,2) DEFAULT NULL COMMENT 'N-11: الساعات التعاقدية الشهرية للمقعد',
  `primary_units_contracted` smallint(5) unsigned DEFAULT NULL COMMENT 'CAP-01 §8.1: أساسياتُ درجة «نوع» — الشجرةُ تفرض Σ والسياسةُ في contract_commitments',
  `standby_units_required` smallint(5) unsigned DEFAULT NULL COMMENT 'CAP-01 §8.1: الاحتياطيُّ المطلوب لدرجة «نوع»',
  `standby_units_allowed` smallint(5) unsigned DEFAULT NULL COMMENT 'CAP-01 §8.1: سقفُ الاحتياطي لدرجة «نوع» — StandbyCapService يقيس عليه',
  `seat_unit_price` decimal(14,4) DEFAULT NULL COMMENT 'N-11: سعر وحدة المقعد',
  `seat_currency` varchar(8) DEFAULT NULL COMMENT 'N-11: عملة سعر المقعد',
  `shift_no` tinyint(3) unsigned DEFAULT NULL COMMENT 'نوبةُ المشغّل',
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('نشطة','معلَّقة','مقفلة') NOT NULL DEFAULT 'نشطة',
  `origin` enum('عقد','مشتقّة') NOT NULL DEFAULT 'عقد' COMMENT 'H-01 ②: منشأُ الرقم — «مشتقّة» تنتظر إقرارَ الإدارة ولا تُقدَّم متفقًا عليها',
  `origin_note` varchar(255) DEFAULT NULL COMMENT 'مِن أين اشتُقّت بالضبط — فيُدقَّق الاستنتاجُ لا يُصدَّق',
  `origin_ack_by` int(10) unsigned DEFAULT NULL COMMENT 'مَن أقرّ الحصةَ المشتقّة — NULL = لم تُقرّ بعد',
  `origin_ack_at` datetime DEFAULT NULL,
  `close_reason` varchar(200) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `seat_obl_uq_key` varchar(40) GENERATED ALWAYS AS (if(`seat_no` is not null and `obl_id` is not null and `is_deleted` = 0,concat(`obl_id`,_utf8mb4':',`seat_no`),NULL)) STORED COMMENT 'CAP-01 §16: UQ(obl_id, seat_no) — فهرسٌ فريدٌ مشروطٌ على عمودٍ مولَّد',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_container_no` (`company_id`,`container_no`),
  UNIQUE KEY `uq_main_per_item` (`company_id`,`contract_item_id`,`level`),
  UNIQUE KEY `uq_seat_no` (`company_id`,`contract_id`,`seat_no`),
  UNIQUE KEY `uq_oc_id_obl` (`id`,`obl_id`),
  UNIQUE KEY `uq_seat_per_obl` (`seat_obl_uq_key`),
  KEY `ix_parent` (`company_id`,`parent_id`),
  KEY `ix_contract` (`company_id`,`contract_id`,`level`),
  KEY `ix_site` (`company_id`,`project_id`,`state`),
  KEY `ix_container_origin` (`company_id`,`origin`,`origin_ack_by`),
  KEY `ix_oc_resource_plan` (`resource_plan_id`),
  KEY `fk_oc_parent_obl` (`parent_id`,`obl_id`),
  CONSTRAINT `fk_container_parent` FOREIGN KEY (`parent_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `fk_oc_parent_obl` FOREIGN KEY (`parent_id`, `obl_id`) REFERENCES `op_containers` (`id`, `obl_id`),
  CONSTRAINT `ck_container_alloc` CHECK (`allocated_qty` >= 0 and `allocated_qty` <= `cap_qty`),
  CONSTRAINT `ck_container_consumed` CHECK (`consumed_qty` >= 0 and `consumed_qty` <= `cap_qty`),
  CONSTRAINT `ck_container_parent` CHECK (`level` = 'رئيسية' and `parent_id` is null or `level` <> 'رئيسية' and `parent_id` is not null),
  CONSTRAINT `ck_container_cap` CHECK (`cap_qty` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: operations ──
CREATE TABLE `operations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `equipment` varchar(100) NOT NULL,
  `equipment_type` varchar(100) NOT NULL DEFAULT '0',
  `equipment_category` varchar(20) NOT NULL,
  `prev_equipment_category` varchar(20) DEFAULT NULL,
  `project_id` varchar(20) NOT NULL,
  `contract_id` varchar(10) NOT NULL,
  `supplier_id` varchar(10) NOT NULL,
  `start` varchar(50) NOT NULL,
  `end` varchar(50) NOT NULL,
  `reason` mediumtext NOT NULL,
  `days` varchar(20) NOT NULL,
  `total_equipment_hours` decimal(10,2) DEFAULT 0.00 COMMENT 'إجمالي ساعات العمل الكلية للآلية',
  `shift_hours` decimal(10,2) DEFAULT 0.00 COMMENT 'عدد ساعات الوردية للمعدة',
  `target_daily_hours` decimal(10,2) DEFAULT NULL COMMENT 'الساعات اليومية المستهدفة للآلية (مرجع للمقارنة منفّذ/مستهدف)',
  `shift_type` enum('D','N','B') NOT NULL DEFAULT 'B',
  `status` tinyint(1) DEFAULT 1,
  `op_state` enum('تعمل','جاهزة','معطلة') NOT NULL DEFAULT 'جاهزة' COMMENT 'حالة الآلية النشطة — تُدار من صفحة الحركة فقط',
  `equipment_health` enum('سليمة','معطلة') NOT NULL DEFAULT 'سليمة' COMMENT 'الصحة الفنية للمعدة (مستقلة عن status التشغيلي)',
  `health_reason` varchar(150) DEFAULT NULL COMMENT 'سبب العطل، مثل: صيانة',
  `health_updated_at` datetime DEFAULT NULL,
  `health_updated_by` int(11) DEFAULT NULL,
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
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `container_id` int(10) unsigned NOT NULL COMMENT 'حاويةُ المشغّل',
  `operator_employee_id` int(10) unsigned NOT NULL,
  `cycle_on_days` smallint(5) unsigned NOT NULL COMMENT 'أيامُ العمل في الدورة',
  `cycle_off_days` smallint(5) unsigned NOT NULL COMMENT 'أيامُ الراحة',
  `cycle_start` date NOT NULL COMMENT 'مبدأُ الدورة — منه يُحسب المناوب',
  `shift_no` tinyint(3) unsigned DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `note` varchar(200) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rotation` (`company_id`,`container_id`,`operator_employee_id`,`cycle_start`),
  KEY `ix_rotation_op` (`company_id`,`operator_employee_id`),
  KEY `fk_rotation_container` (`container_id`),
  CONSTRAINT `fk_rotation_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `ck_rotation_cycle` CHECK (`cycle_on_days` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: opportunities ──
CREATE TABLE `opportunities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `opp_code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `sector_category` varchar(100) DEFAULT NULL,
  `state_region` varchar(100) DEFAULT NULL,
  `revenue_model` enum('hourly','ton','meter','mixed') DEFAULT NULL,
  `expected_revenue` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency` enum('USD','SDG') NOT NULL DEFAULT 'USD',
  `probability` decimal(5,2) NOT NULL DEFAULT 0.00,
  `stage` enum('جديدة','قيد الدراسة','مؤهلة','عرض مقدم','تفاوض','فوز','خسارة','مستبعدة') NOT NULL DEFAULT 'جديدة',
  `attractiveness` enum('منخفضة','متوسطة','عالية') DEFAULT NULL,
  `strategy_fit` enum('منخفض','متوسط','عالي') DEFAULT NULL,
  `capacity_summary` text DEFAULT NULL,
  `requirements_json` text DEFAULT NULL COMMENT 'INJAZ-S05 — المتطلبات المبدئية المُهيكلة (معدات بالنوع + عددا مشغّلين/موردين) JSON؛ capacity_summary مشتقٌّ منه',
  `funding_needed` decimal(14,2) NOT NULL DEFAULT 0.00,
  `study_decision` enum('متابعة','تعليق','استبعاد') DEFAULT NULL,
  `expected_close_date` date DEFAULT NULL,
  `lost_reason` varchar(255) DEFAULT NULL,
  `win_reason` varchar(255) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_opportunities_company_code` (`company_id`,`opp_code`),
  KEY `idx_opp_company` (`company_id`),
  KEY `idx_opp_client` (`client_id`),
  KEY `idx_opp_stage` (`stage`),
  KEY `idx_opp_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Table: org_assignment_types ──
CREATE TABLE `org_assignment_types` (
  `type_code` varchar(40) NOT NULL,
  `name_ar` varchar(190) NOT NULL,
  `level` enum('central','site') NOT NULL,
  `default_capabilities_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`default_capabilities_json`)),
  `requires_functional_line` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'الموقعيُّ كلُّه =1: خطّان تشغيليٌّ وفنيٌّ لا خطٌّ واحد (§2⑦)',
  `is_unit_head` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'نوعٌ يجعل صاحبَه رأسَ وحدته — يغذي اشتقاق v_org_unit_heads',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: أنواعُ التكليف — يُضاف نوعٌ جديدٌ بصفٍّ لا بتعديل برمجة';

-- ── Table: org_assignments ──
CREATE TABLE `org_assignments` (
  `asg_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL COMMENT 'users.id — كنمط signing_authorities.person_id',
  `assignment_type_code` varchar(40) NOT NULL,
  `org_unit_id` int(10) unsigned NOT NULL,
  `scope_type` enum('project','site','site_group') NOT NULL,
  `scope_id` int(11) NOT NULL COMMENT 'المشروعُ أو الموقعُ أو مجموعةُ المواقع — ولا تكليفَ مفتوحُ النطاق',
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL COMMENT 'إلزاميٌّ — لا تكليفَ مفتوحُ المدة، وتمديدُه قرارٌ جديد',
  `decided_by_person_id` int(11) NOT NULL COMMENT 'مصدرُ القرار: مديرُ التشغيل أو المديرُ التنفيذي',
  `decision_ref` varchar(120) DEFAULT NULL,
  `deputy_person_id` int(11) DEFAULT NULL COMMENT 'النائبُ المعتمَد — ولا نيابةَ شفويةٌ ولا مفتوحةُ المدة',
  `state` enum('active','suspended','ended') NOT NULL DEFAULT 'active',
  `active_site_mgr_key` varchar(80) GENERATED ALWAYS AS (if(`assignment_type_code` = _utf8mb4'site_movement_mgr' and `state` = _utf8mb4'active',concat(`company_id`,_utf8mb4':',`scope_type`,_utf8mb4':',`scope_id`),NULL)) STORED COMMENT 'حيلةُ الفريد المشروط: NULL لغير مدير الحركة النشط — فينتج «واحدٌ نشطٌ لكل موقع»',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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

-- ── Table: org_structure_versions ──
CREATE TABLE `org_structure_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `version_code` varchar(16) NOT NULL COMMENT 'ORG-000001',
  `change_kind` enum('إنشاء وحدة','تعديل وحدة','تعطيل وحدة','نقل تبعية','تعديل مسمى','رجوع لنسخة') NOT NULL,
  `unit_id` int(11) DEFAULT NULL COMMENT 'org_units.unit_id المتأثرة',
  `decision_ref` varchar(64) NOT NULL COMMENT 'قرارُ الإنشاء أو التعديل — مرجعيٌّ إلزامي',
  `effective_date` date NOT NULL,
  `snapshot_json` mediumtext NOT NULL COMMENT 'لقطةُ الهيكل قبل التغيير — أساسُ الرجوع',
  `change_json` text DEFAULT NULL COMMENT 'ما تغيّر بالضبط — قبلَ وبعد',
  `assignments_review_note` varchar(255) NOT NULL DEFAULT '' COMMENT 'التكليفاتُ القائمةُ تُراجَع — نتيجةُ المراجعة',
  `state` enum('applied','reverted') NOT NULL DEFAULT 'applied',
  `reverted_by_ref` varchar(16) NOT NULL DEFAULT '' COMMENT 'العكس: نسخةُ الرجوع التي نقضتها',
  `changed_by` int(11) NOT NULL COMMENT '§9-1 المُنشئ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL COMMENT '§9-1 المعتمِد',
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'النسخةُ السابقة في السلسلة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orgv_code` (`company_id`,`version_code`),
  KEY `ix_orgv_unit` (`company_id`,`unit_id`,`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-14 org.change: كلُّ تغييرِ هيكلٍ نسخةٌ بلقطتها وقرارها — والرجوعُ بقرارٍ لا محوًا';

-- ── Table: org_units ──
CREATE TABLE `org_units` (
  `unit_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `unit_code` varchar(40) NOT NULL COMMENT 'رمزٌ ثابتٌ للوحدة تُخاطَب به برمجيًّا',
  `name_ar` varchar(190) NOT NULL,
  `layer` enum('operational','parallel','oversight') NOT NULL COMMENT 'الطبقة: تشغيليةٌ تحت مدير التشغيل · موازيةٌ تحت التنفيذي · رقابية',
  `parent_unit_id` int(10) unsigned DEFAULT NULL,
  `owner_doc` varchar(120) DEFAULT NULL COMMENT 'الوثيقةُ الحاكمة للوحدة',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`unit_id`),
  UNIQUE KEY `uq_org_units_scope` (`company_id`,`unit_code`),
  KEY `idx_org_units_parent` (`parent_unit_id`),
  CONSTRAINT `fk_org_units_parent` FOREIGN KEY (`parent_unit_id`) REFERENCES `org_units` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: الوحداتُ التنظيمية — head_person_id مشتقٌّ من org_assignments (v_org_unit_heads) ولا يُكتب';

-- ── Table: ownership_access_grants ──
CREATE TABLE `ownership_access_grants` (
  `grant_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `person_id` int(11) NOT NULL,
  `permission_code` enum('ownership.owner_view','ownership.finance_terms','ownership.purchase_value') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `granted_by` int(11) NOT NULL,
  `state` enum('active','revoked') NOT NULL DEFAULT 'active',
  `revoked_by` int(11) DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`grant_id`),
  KEY `ix_oag_person` (`company_id`,`person_id`,`permission_code`,`state`),
  CONSTRAINT `ck_oag_value_strict` CHECK (`permission_code` <> _utf8mb4'ownership.purchase_value' or `reason` is not null and `valid_from` is not null and `valid_to` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: pay_components ──
CREATE TABLE `pay_components` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزلٌ مباشر (سابقة claim_lines: يُقرأ مجمَّعًا بلا JOIN أبيه)',
  `contract_id` int(11) NOT NULL,
  `component_type` enum('basic','cost_of_living','housing','transport','food','site','hazard','work_nature','shift','night','responsibility','supervision','assignment','travel','mission','communication','medical','fixed_bonus','other_allowance','custom') NOT NULL COMMENT 'قائمةُ §3.2 العشرون نصًّا — لاتينيةً (گوتشا الترميز) والتعريبُ في الخدمة',
  `calc_method` enum('fixed_amount','pct_reference','pct_basic','pct_gross','per_day','per_shift','per_hour','per_unit','tiers','custom_formula') NOT NULL COMMENT 'طرقُ الاحتساب العشر — §3.2',
  `value` decimal(18,2) DEFAULT NULL,
  `rate` decimal(12,2) DEFAULT NULL,
  `in_insurance` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'يدخل التأمينات؟',
  `in_tax` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'يدخل الضريبة؟',
  `in_leave_pay` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'يدخل أجرَ الإجازة؟',
  `in_eos` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'يدخل نهايةَ الخدمة؟',
  `in_hour_base` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'يدخل حسابَ الساعة؟',
  `in_overtime` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'يدخل العملَ الإضافي؟',
  `in_incentive_base` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'يدخل وعاءَ الحافز؟',
  `is_variable` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'ثابتٌ أم متغير',
  `periodicity` enum('monthly','periodic','once') NOT NULL DEFAULT 'monthly',
  `cost_bearer_type` enum('project','client_contract','dept','company') DEFAULT NULL COMMENT 'إشارةُ المكوّن المفردة — شجرةُ Σ=100 (cost_bearers) بيتُها الشريحة ④',
  `cost_bearer_id` int(11) DEFAULT NULL,
  `cost_center_id` int(11) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','replaced','ended') NOT NULL DEFAULT 'active' COMMENT 'حالاتُ سياسة الأجر — التصريحُ الكامل مع E-24/H-10',
  `created_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_pc_contract` (`contract_id`),
  KEY `ix_pc_company` (`company_id`),
  KEY `fk_pc_cost_center` (`cost_center_id`),
  CONSTRAINT `fk_pc_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`),
  CONSTRAINT `fk_pc_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `fin_cost_centers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: pay_models ──
CREATE TABLE `pay_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `label_ar` varchar(64) NOT NULL,
  `calc_path` enum('time','production','mixed','other') NOT NULL DEFAULT 'other',
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_model_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_absence_types ──
CREATE TABLE `payroll_absence_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `event_type` varchar(40) NOT NULL COMMENT 'يطابق worker_leave_absence.event_type حرفيًّا',
  `code` varchar(4) DEFAULT NULL COMMENT 'WRK-01 §3: رمز الحالة (1·0·10·11·ST·S·M·A1·A2·EM·UP)',
  `deducts` tinyint(4) NOT NULL DEFAULT 0 COMMENT '1 = غيابٌ يُخصم · 0 = إجازةٌ مدفوعة',
  `deduct_percent` decimal(5,2) NOT NULL DEFAULT 100.00 COMMENT 'نسبةُ الخصم من أجر اليوم',
  `label_ar` varchar(80) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `pay_effect` enum('full','none','per_contract','per_policy','stops_accrual','per_hr','deduct_daily') DEFAULT NULL COMMENT 'أثر الراتب',
  `incentive_base` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'يدخل أساس الحافز؟',
  `presence` enum('site','off','transit','mission') DEFAULT NULL COMMENT 'التواجد',
  `billable` enum('yes','no','by_attribution') DEFAULT NULL COMMENT 'الفوترة — ST بالإسناد',
  `supplier_due` enum('yes','no','by_attribution','per_contract') DEFAULT NULL COMMENT 'استحقاق المورد',
  `conduct_violation` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'A2: مخالفة سلوكية تُسجَّل — أثر ثانٍ مستقل',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_absence_type` (`company_id`,`event_type`),
  UNIQUE KEY `uq_absence_code` (`company_id`,`code`),
  CONSTRAINT `ck_absence_pct` CHECK (`deduct_percent` >= 0 and `deduct_percent` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_deductions ──
CREATE TABLE `payroll_deductions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `source_type` enum('advance','on_behalf','penalty','absence','other') NOT NULL,
  `source_id` int(11) NOT NULL COMMENT 'مرجعُ المصدر — 0 مرفوضٌ بالقيد',
  `amount` decimal(18,2) NOT NULL COMMENT 'المخصومُ فعلًا في هذه الدورة (موجبٌ)',
  `requested_amount` decimal(18,2) DEFAULT NULL COMMENT 'القسطُ المستحقُّ قبل حدِّ الحماية',
  `doc_ref` varchar(120) NOT NULL COMMENT '«ولا خصمَ بلا مستند» — إلزاميٌّ بنيويًّا',
  `rescheduled` tinyint(4) NOT NULL DEFAULT 0 COMMENT '1 = قُصّ بحدِّ الحماية ورُحّل باقيه',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_deduction` (`run_id`,`person_id`,`source_type`,`source_id`),
  KEY `ix_deduction_run` (`run_id`),
  CONSTRAINT `fk_deduction_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_deduction_amount` CHECK (`amount` >= 0),
  CONSTRAINT `ck_deduction_doc` CHECK (char_length(trim(`doc_ref`)) > 0),
  CONSTRAINT `ck_deduction_src` CHECK (`source_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_lines ──
CREATE TABLE `payroll_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL COMMENT 'employees.id — «العقدُ يشير إلى سجل الأشخاص»',
  `contract_id` int(11) NOT NULL,
  `snapshot_id` int(11) NOT NULL COMMENT '**البوابة**: لا سطرَ احتسابٍ بلا لقطته (ENT-01 §2)',
  `path` enum('institutional','project') NOT NULL DEFAULT 'institutional' COMMENT 'مسارا §3',
  `component_ref` varchar(64) NOT NULL COMMENT 'component#N أو rule#N — مرجعُه داخل اللقطة',
  `line_kind` enum('component','overtime','absence_deduction','production','incentive') NOT NULL DEFAULT 'component' COMMENT 'نوعُ السطر — production/incentive مولَّدا المسار الإنتاجي (H-09-③)',
  `component_type` varchar(40) DEFAULT NULL,
  `calc_method` varchar(40) DEFAULT NULL,
  `qty` decimal(18,2) DEFAULT NULL,
  `entitled_days` decimal(6,2) DEFAULT NULL COMMENT 'أيامُ الاستحقاق في الفترة',
  `period_days` decimal(6,2) DEFAULT NULL COMMENT 'أيامُ الفترة كاملةً',
  `rate` decimal(18,4) DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT NULL COMMENT 'NULL = لم يُحتسب بعد (بحالته وسببه) — لا صفرَ ملفَّق',
  `unit_record_id` int(11) DEFAULT NULL COMMENT 'للمسار التشغيلي — الشريحة ③',
  `bearer_type` varchar(20) DEFAULT NULL COMMENT 'جهةُ التحمّل من اللقطة',
  `bearer_id` int(11) DEFAULT NULL,
  `percent` decimal(6,2) DEFAULT NULL COMMENT 'نسبةُ الجهة — Σ لكل مكوّنٍ = 100',
  `calc_state` enum('computed','pending_slice','blocked') NOT NULL DEFAULT 'computed',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_payroll_line_run_person` (`run_id`,`person_id`),
  KEY `ix_payroll_line_snapshot` (`snapshot_id`),
  CONSTRAINT `fk_payroll_line_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_line_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `contract_snapshots` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_run_blocks ──
CREATE TABLE `payroll_run_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `person_id` int(11) DEFAULT NULL,
  `kind` enum('excluded','blocked') NOT NULL DEFAULT 'blocked' COMMENT 'excluded = خارج النطاق بسببٍ مكتوب · blocked = عطبٌ يوقف الدورة',
  `block_code` varchar(40) NOT NULL COMMENT 'snapshot_missing · contract_not_readable · bearer_sum_invalid …',
  `block_http` smallint(6) NOT NULL DEFAULT 422,
  `reason` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_block` (`run_id`,`contract_id`,`block_code`),
  KEY `ix_payroll_block_run` (`run_id`),
  CONSTRAINT `fk_payroll_block_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_runs ──
CREATE TABLE `payroll_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `category_filter` varchar(32) NOT NULL DEFAULT 'all' COMMENT 'فئةُ CON-01 §2 أو all — جزءٌ من المفتاح الفريد فلا تُخلط الدورات',
  `project_filter` int(11) DEFAULT NULL,
  `state` enum('Open','Calculated','Blocked','Review','Approved','Paid','Closed') NOT NULL DEFAULT 'Open' COMMENT 'دورةُ ENT-01 §8 السباعية نصًّا',
  `persons_count` int(11) NOT NULL DEFAULT 0,
  `lines_count` int(11) NOT NULL DEFAULT 0,
  `blocked_count` int(11) NOT NULL DEFAULT 0,
  `gross_total` decimal(18,2) DEFAULT NULL COMMENT 'NULL = لم يكتمل الاحتساب (الشريحتان ②③)',
  `currency` varchar(8) DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `note` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_run_key` (`company_id`,`period_from`,`period_to`,`category_filter`),
  KEY `ix_payroll_run_state` (`company_id`,`state`),
  CONSTRAINT `ck_payroll_run_period` CHECK (`period_to` >= `period_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_settings ──
CREATE TABLE `payroll_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `protection_percent` decimal(5,2) DEFAULT NULL COMMENT 'أدنى نسبةٍ من الإجمالي تبقى للعامل — NULL = لم يُقرَّر بعد',
  `note` varchar(255) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_settings_co` (`company_id`),
  CONSTRAINT `ck_protection_pct` CHECK (`protection_percent` is null or `protection_percent` >= 0 and `protection_percent` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: payroll_time_inputs ──
CREATE TABLE `payroll_time_inputs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `kind` enum('overtime_hours','unpaid_days','night_shifts') NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `doc_ref` varchar(120) NOT NULL COMMENT 'مرجعُ المستند — إلزاميٌّ بنيويًّا',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_time_input` (`run_id`,`person_id`,`kind`),
  KEY `ix_time_input_co` (`company_id`,`run_id`),
  CONSTRAINT `fk_time_input_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_time_input_doc` CHECK (char_length(trim(`doc_ref`)) > 0),
  CONSTRAINT `ck_time_input_qty` CHECK (`qty` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: perm_shadow_diffs ──
CREATE TABLE `perm_shadow_diffs` (
  `diff_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_code` varchar(120) NOT NULL,
  `action` varchar(40) NOT NULL,
  `permission_code` varchar(120) NOT NULL,
  `scope_rule` varchar(120) NOT NULL,
  `legacy_decision` tinyint(1) NOT NULL,
  `derived_decision` tinyint(1) NOT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'حُقق وأُصلح سببه (قالب أو تحويل)',
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`diff_id`),
  KEY `idx_psd_at` (`at`),
  KEY `idx_psd_user` (`company_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §13 المرحلة ③: كل فرق سماح/منع/نطاق/سقف صف — والحد صفر لا نسبة';

-- ── Table: permission_approval_steps ──
CREATE TABLE `permission_approval_steps` (
  `st_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `req_id` int(10) unsigned NOT NULL,
  `seq_no` int(11) NOT NULL,
  `approver_rule` enum('hr','functional_owner','requester_department_manager','finance_owner_if_financial','security_manager','executive') NOT NULL COMMENT 'قاعدة ديناميكية لا دور ثابت — functional_owner يُحل من ORG-01 بحسب المجال والنطاق والتاريخ',
  `mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `approver_person_id` int(11) DEFAULT NULL COMMENT 'يُحل لحظة الفتح',
  `auth_id` int(10) unsigned DEFAULT NULL,
  `decision` enum('approve','reject') DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `at` datetime DEFAULT NULL,
  PRIMARY KEY (`st_id`),
  UNIQUE KEY `uq_step` (`req_id`,`seq_no`),
  CONSTRAINT `fk_step_req` FOREIGN KEY (`req_id`) REFERENCES `permission_change_requests` (`req_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: لا تُفتح خطوة قبل سابقتها — يحرسه PermissionChangeWorkflow';

-- ── Table: permission_audit_events ──
CREATE TABLE `permission_audit_events` (
  `ev_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `event_type` enum('granted','elevated','reduced','revoked','expired','suspended','break_glass') NOT NULL,
  `person_id` int(11) NOT NULL,
  `permission_code` varchar(120) NOT NULL,
  `scope_rule` varchar(120) DEFAULT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_json`)),
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `executed_by` int(11) DEFAULT NULL,
  `request_ref` varchar(120) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `source` enum('template','assignment','exception','grant','break_glass') NOT NULL,
  `founding_mode` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'وسم أفعال التأسيس §7-④',
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ev_id`),
  KEY `idx_pae_person` (`company_id`,`person_id`,`at`),
  KEY `idx_pae_type` (`event_type`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: لا يُعدَّل ولا يُحذف — ولا يُخلط بمراجعة المدير الدورية';

-- ── Table: permission_change_requests ──
CREATE TABLE `permission_change_requests` (
  `req_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `change_kind` enum('within_role','supervisor','section_mgr','dept_mgr_or_high') NOT NULL,
  `from_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`from_json`)),
  `to_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`to_json`)),
  `reason` varchar(255) NOT NULL,
  `doc_ref` varchar(120) DEFAULT NULL,
  `risk_level` enum('low','medium','high') NOT NULL COMMENT 'محسوب من النوع',
  `state` enum('draft','pending','approved','rejected','applied') NOT NULL DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`req_id`),
  KEY `idx_pcr_state` (`company_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §8: الموافقات بدرجة المخاطرة لا بمسار واحد';

-- ── Table: permission_exceptions ──
CREATE TABLE `permission_exceptions` (
  `ex_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `permission_code` varchar(120) NOT NULL,
  `scope_rule` varchar(120) NOT NULL,
  `effect` enum('grant','deny') NOT NULL DEFAULT 'grant',
  `reason` varchar(255) NOT NULL,
  `valid_from` datetime NOT NULL,
  `valid_to` datetime NOT NULL COMMENT 'إلزامي — ويسقط آليًّا',
  `is_break_glass` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'كسر الزجاج: مدة ≤ 24 ساعة بمراجعة لاحقة إلزامية',
  `approvals_ref` varchar(120) DEFAULT NULL,
  `state` enum('active','expired','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ex_id`),
  KEY `idx_ex_person` (`company_id`,`person_id`,`state`),
  KEY `idx_ex_expiry` (`state`,`valid_to`),
  CONSTRAINT `chk_bg_24h` CHECK (`is_break_glass` = 0 or timestampdiff(HOUR,`valid_from`,`valid_to`) <= 24)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: permission_review_cycles ──
CREATE TABLE `permission_review_cycles` (
  `cycle_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `org_unit_id` int(10) unsigned NOT NULL,
  `period` varchar(10) NOT NULL COMMENT 'مثال 2026-H2',
  `manager_person_id` int(11) NOT NULL,
  `due_at` date NOT NULL,
  `state` enum('open','signed','escalated') NOT NULL DEFAULT 'open',
  `signed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`cycle_id`),
  UNIQUE KEY `uq_prc` (`org_unit_id`,`period`),
  KEY `idx_prc_due` (`state`,`due_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §10⑥: ما لم يُوقَّع خلال مهلته يُصعَّد للإدارة العامة';

-- ── Table: permission_review_lines ──
CREATE TABLE `permission_review_lines` (
  `line_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` int(10) unsigned NOT NULL,
  `person_id` int(11) NOT NULL,
  `permission_code` varchar(120) NOT NULL,
  `scope_rule` varchar(120) DEFAULT NULL,
  `decision` enum('confirm','reduce','revoke') DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`line_id`),
  KEY `idx_prl_cycle` (`cycle_id`,`person_id`),
  CONSTRAINT `fk_prl_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `permission_review_cycles` (`cycle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: سطر لكل (موظف × صلاحية) — Insert-only';

-- ── Table: permission_template_versions ──
CREATE TABLE `permission_template_versions` (
  `ver_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tpl_id` int(10) unsigned NOT NULL,
  `version` int(11) NOT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `state` enum('draft','tested','published','superseded') NOT NULL DEFAULT 'draft',
  `approval_ref` varchar(120) DEFAULT NULL,
  `change_reason` varchar(255) DEFAULT NULL,
  `impact_preview_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'أثر التغيير قبل النشر: كم مستخدمًا وأي صلاحية' CHECK (json_valid(`impact_preview_json`)),
  `superseded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ver_id`),
  UNIQUE KEY `uq_ver` (`tpl_id`,`version`),
  CONSTRAINT `fk_ver_tpl` FOREIGN KEY (`tpl_id`) REFERENCES `permission_templates` (`tpl_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §4⑥: لا يُعدل إصدار نافذ بأثر رجعي — النشر إصدار جديد بسريان مستقبلي';

-- ── Table: permission_templates ──
CREATE TABLE `permission_templates` (
  `tpl_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tpl_kind` enum('relation','family','level','title','assignment') NOT NULL,
  `key_code` varchar(60) NOT NULL,
  `is_ceiling` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'لقوالب العلاقة: سقف لا أرضية',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`tpl_id`),
  UNIQUE KEY `uq_tpl_kind_key` (`tpl_kind`,`key_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §4: القالب جدول يُعدل بقرار لا كود يُبرمج';

-- ── Table: permit_approval_actions ──
CREATE TABLE `permit_approval_actions` (
  `act_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `req_id` int(10) unsigned NOT NULL,
  `rq_id` int(10) unsigned NOT NULL,
  `approver_person_id` int(11) NOT NULL,
  `auth_id` int(10) unsigned DEFAULT NULL COMMENT 'مرجعُ التفويض signing_authorities — LEG-01 §4',
  `decision` enum('approve','reject') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`act_id`),
  UNIQUE KEY `uq_act_step` (`req_id`,`rq_id`),
  KEY `fk_permit_act_rq` (`rq_id`),
  CONSTRAINT `fk_permit_act_req` FOREIGN KEY (`req_id`) REFERENCES `permit_requests` (`req_id`),
  CONSTRAINT `fk_permit_act_rq` FOREIGN KEY (`rq_id`) REFERENCES `permit_required_approvals` (`rq_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: قيدُ التسلسل «لا تُفتح خطوةٌ قبل اكتمال ما قبلها» يحرسه PermitGate بـ409';

-- ── Table: permit_requests ──
CREATE TABLE `permit_requests` (
  `req_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `permit_type_code` varchar(40) NOT NULL,
  `subject_ref` varchar(120) NOT NULL COMMENT 'مرجعُ الموضوع: معدةٌ أو مادةٌ أو شخصٌ أو فني',
  `site_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `doc_ref` varchar(120) DEFAULT NULL,
  `state` enum('draft','pending','approved','rejected','expired','used') NOT NULL DEFAULT 'draft',
  `valid_until` datetime DEFAULT NULL COMMENT 'يُحسب من validity_hours عند اكتمال الموافقات — بساعة القاعدة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`req_id`),
  KEY `idx_permit_state_site` (`state`,`site_id`),
  KEY `idx_permit_company` (`company_id`,`state`),
  KEY `fk_preq_type` (`permit_type_code`),
  CONSTRAINT `fk_preq_type` FOREIGN KEY (`permit_type_code`) REFERENCES `permit_types` (`permit_type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: طلبُ الإذن — يمرّ بصندوق الاعتماد الجامع بندًا واحدًا لكل موافقٍ في دوره';

-- ── Table: permit_required_approvals ──
CREATE TABLE `permit_required_approvals` (
  `rq_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `permit_type_code` varchar(40) NOT NULL,
  `seq_no` int(11) NOT NULL,
  `approver_role` varchar(60) NOT NULL COMMENT 'المجالُ الوظيفيُّ الموافق — يحلُّه PermitGate من التكليفات النافذة',
  `mandatory` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`rq_id`),
  UNIQUE KEY `uq_rq_seq` (`permit_type_code`,`seq_no`),
  CONSTRAINT `fk_permit_rq_type` FOREIGN KEY (`permit_type_code`) REFERENCES `permit_types` (`permit_type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §5/§7: مصفوفةُ الموافقات المشتركة — يُقرأ منها من يوافق وبأي ترتيب';

-- ── Table: permit_status_history ──
CREATE TABLE `permit_status_history` (
  `hist_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `req_id` int(10) unsigned NOT NULL,
  `from_state` varchar(20) NOT NULL,
  `to_state` varchar(20) NOT NULL,
  `by_person_id` int(11) NOT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`hist_id`),
  KEY `idx_hist_req` (`req_id`,`at`),
  CONSTRAINT `fk_permit_hist_req` FOREIGN KEY (`req_id`) REFERENCES `permit_requests` (`req_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §7: تاريخُ حالات الإذن — للإدراج فقط';

-- ── Table: permit_types ──
CREATE TABLE `permit_types` (
  `permit_type_code` varchar(40) NOT NULL,
  `name_ar` varchar(190) NOT NULL,
  `subject_kind` enum('equipment','material','person','technician') NOT NULL,
  `direction` enum('in','out','activate','deactivate') NOT NULL,
  `validity_hours` int(11) NOT NULL DEFAULT 24,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`permit_type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ORG-01 §5/§7: الأنواعُ التسعةُ صفوفٌ هنا لا كودًا';

-- ── Table: person_positions ──
CREATE TABLE `person_positions` (
  `p_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned NOT NULL,
  `company_id` int(11) NOT NULL,
  `relation_code` varchar(40) NOT NULL,
  `family_code` varchar(40) NOT NULL COMMENT 'ولا موظف بلا عائلة (DEC-SEC-F)',
  `level_code` varchar(40) NOT NULL,
  `title_code` varchar(40) NOT NULL COMMENT 'job_titles.title_code',
  `org_unit_id` int(10) unsigned DEFAULT NULL,
  `manager_person_id` int(10) unsigned DEFAULT NULL,
  `scope_type` enum('company','department','section','unit','project','site','site_group','shift','own_records') NOT NULL,
  `scope_id` int(11) NOT NULL COMMENT 'قيد: لا صف بلا نطاق — الصلاحية بلا نطاق مرفوضة بنيويًّا',
  `is_primary` tinyint(1) NOT NULL DEFAULT 1,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','suspended','ended') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `rel_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned NOT NULL,
  `company_id` int(11) NOT NULL COMMENT 'الكيان — والعزل يمنع تسرب كيان إلى آخر ولو كان الشخص واحدًا',
  `relation_code` varchar(40) NOT NULL COMMENT 'hr_dictionaries layer=relation',
  `employee_id` int(11) DEFAULT NULL COMMENT 'جسر صفوف employees — تبقى بياناتِ الموظف الإدارية لا الهوية',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL COMMENT 'NULL = علاقة قائمة (الدائم) — والمؤقتة بنهاية',
  `state` enum('active','suspended','ended') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rel_id`),
  KEY `idx_prel_person` (`person_id`,`state`),
  KEY `idx_prel_company` (`company_id`,`relation_code`,`state`),
  CONSTRAINT `fk_prel_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §14②: موظف المورد لا يُنشأ له موظف داخلي وهمي';

-- ── Table: personal_notifications ──
CREATE TABLE `personal_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `kind` varchar(24) NOT NULL DEFAULT 'info',
  `title` varchar(300) NOT NULL,
  `body` varchar(600) DEFAULT NULL,
  `link` varchar(300) DEFAULT NULL COMMENT 'رابط الأصل بضغطة واحدة',
  `requires_action` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'WF-06',
  `task_item_id` bigint(20) unsigned DEFAULT NULL COMMENT 'المهمة المولَّدة إن تطلب فعلًا — AC-WFM-08',
  `read_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_pn_user` (`company_id`,`user_id`,`read_at`),
  KEY `ix_pn_action` (`requires_action`,`task_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WFM: التنبيه إحاطة — ولا يصير مهمة إلا بفعل مطلوب';

-- ── Table: persons ──
CREATE TABLE `persons` (
  `person_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(190) NOT NULL,
  `national_ref` varchar(60) DEFAULT NULL COMMENT 'مرجع هوية — معرّف دائم لا يُعاد استعماله',
  `contact_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`contact_json`)),
  `docs_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`docs_json`)),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`person_id`),
  UNIQUE KEY `uq_persons_national` (`national_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §14: سجل الإنسان — حساب واحد عبر المنصة والصفات متعددة';

-- ── Table: policy_rules ──
CREATE TABLE `policy_rules` (
  `rule_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` int(10) unsigned NOT NULL,
  `rule_kind` varchar(60) NOT NULL,
  `formula_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`formula_json`)),
  `threshold` decimal(18,4) DEFAULT NULL,
  `cap` decimal(18,4) DEFAULT NULL,
  `periodicity` enum('daily','weekly','monthly') DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  PRIMARY KEY (`rule_id`),
  KEY `ix_pr_policy` (`policy_id`,`rule_kind`),
  CONSTRAINT `fk_pr_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POL-01 §2-②: قواعد الإدارة بمعادلاتها وسقوفها';

-- ── Table: portal_activity_log ──
CREATE TABLE `portal_activity_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `account_id` int(11) NOT NULL,
  `capacity_id` int(10) unsigned DEFAULT NULL,
  `action_code` varchar(40) NOT NULL,
  `target_type` varchar(40) DEFAULT NULL,
  `target_id` varchar(64) DEFAULT NULL,
  `result` enum('ok','denied') NOT NULL DEFAULT 'ok',
  `ip` varchar(45) DEFAULT NULL,
  `device` varchar(190) DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_pal_account` (`account_id`,`at`),
  KEY `ix_pal_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='USR-01 §5 — سجلُّ النشاط: يراه صاحبُه والمدقّقُ وHR ولا يُعدَّل ولا يُحذف';

-- ── Table: portal_elements ──
CREATE TABLE `portal_elements` (
  `element_code` varchar(64) NOT NULL,
  `title_ar` varchar(190) NOT NULL,
  `owner_doc` varchar(32) NOT NULL COMMENT 'الوثيقةُ المالكة (USR-01 · WSP-01 …)',
  `sensitivity` enum('normal','sensitive') NOT NULL DEFAULT 'normal',
  `default_mode` enum('open','closed') NOT NULL DEFAULT 'closed',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`element_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ADM-01 §2 — قاموسُ عناصر البوابة: ما ليس فيه لا يُصيَّر أصلًا';

-- ── Table: positions ──
CREATE TABLE `positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL COMMENT 'اسم المنصب التنظيمي (مثال: مدير حركة موقع MB1)',
  `role_id` int(11) NOT NULL COMMENT 'دور النظام الذي يمنحه المنصب — جسرٌ إلى roles.id لا بديل عنه',
  `job_title_id` int(11) DEFAULT NULL COMMENT 'ربط اختياري بالمسمى الوظيفي HR (job_titles.id)',
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_positions_company` (`company_id`),
  KEY `idx_positions_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='K6/ADR-07: المناصب — طبقة الصلاحية على المنصب فوق الأدوار';

-- ── Table: pricelists ──
CREATE TABLE `pricelists` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `pricelist_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `currency` enum('USD','SDG') NOT NULL DEFAULT 'USD',
  `revenue_model` enum('hourly','ton','meter') DEFAULT NULL,
  `base_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `distance_factor` decimal(6,3) DEFAULT NULL,
  `shift_factor` decimal(6,3) DEFAULT NULL,
  `volume_factor` decimal(6,3) DEFAULT NULL,
  `duration_factor` decimal(6,3) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pricelists_company_code` (`company_id`,`pricelist_code`),
  KEY `idx_pl_scope` (`company_id`,`is_deleted`),
  KEY `idx_pl_model` (`revenue_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_custody ──
CREATE TABLE `proc_custody` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `issue_id` int(11) DEFAULT NULL COMMENT 'proc_issue.id',
  `issue_line_id` int(11) DEFAULT NULL COMMENT 'proc_issue_line.id',
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(200) DEFAULT NULL,
  `holder_id` int(11) DEFAULT NULL,
  `holder_name` varchar(150) DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `maintenance_order_id` int(11) DEFAULT NULL,
  `qty_issued` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty_returned` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty_consumed` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'المصروفة - المرتجعة',
  `state` varchar(20) NOT NULL DEFAULT 'مصروفة' COMMENT 'مصروفة/إرجاع جزئي/مستهلكة/مُقفلة',
  `notes` mediumtext DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_custody_company_state` (`company_id`,`state`),
  KEY `idx_proc_custody_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_issue ──
CREATE TABLE `proc_issue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `holder_id` int(11) DEFAULT NULL COMMENT 'المستلِم',
  `holder_name` varchar(150) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL COMMENT 'بُعد تكلفة',
  `project_id` int(11) DEFAULT NULL COMMENT 'بُعد تكلفة',
  `maintenance_order_id` int(11) DEFAULT NULL COMMENT 'mnt_order.id (بلا FK)',
  `maint_type` varchar(20) DEFAULT NULL COMMENT 'وقائية/تصحيحية/رأسمالية',
  `contract_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `charge_supplier_id` int(11) DEFAULT NULL COMMENT 'مورّدُ الآليات (suppliers.id) الذي يُحمَّل ثمنَ الصرف — فارغٌ أي على الشركة',
  `total_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `state` varchar(20) NOT NULL DEFAULT 'مسودة' COMMENT 'مسودة/محجوز/مصروف/محمَّل التكلفة',
  `notes` mediumtext DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_issue_company_state` (`company_id`,`state`),
  KEY `idx_proc_issue_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_issue_line ──
CREATE TABLE `proc_issue_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `issue_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(200) NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_issline_issue` (`issue_id`),
  CONSTRAINT `fk_proc_issline_iss` FOREIGN KEY (`issue_id`) REFERENCES `proc_issue` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_item ──
CREATE TABLE `proc_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT NULL COMMENT 'فلاتر/زيوت وشحوم/إسبيرات/بطاريات/أسنان جردل/سيور',
  `material_nature` varchar(30) NOT NULL DEFAULT 'قابل للتخزين' COMMENT 'قابل للتخزين / غير قابل للتخزين / خدمة ومصنعيات',
  `uom` varchar(20) NOT NULL DEFAULT 'قطعة' COMMENT 'قطعة/لتر/كجم',
  `is_critical` tinyint(1) NOT NULL DEFAULT 0,
  `min_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `lead_time_days` int(11) NOT NULL DEFAULT 0,
  `safety_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `avg_cost` decimal(18,4) DEFAULT NULL COMMENT 'المتوسط المرجح بمعادل الدفاتر — يشتقه ProcCostingService من دفتر الحركات',
  `avg_cost_updated_at` datetime DEFAULT NULL COMMENT 'لحظة آخر إعادة احتساب',
  `served_equipment_id` int(11) DEFAULT NULL COMMENT 'equipments.id (بلا FK)',
  `served_category` varchar(100) DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_item_company` (`company_id`),
  KEY `idx_proc_item_critical` (`company_id`,`is_critical`),
  KEY `idx_proc_item_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_landed_cost ──
CREATE TABLE `proc_landed_cost` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — عزل المستأجر',
  `order_id` int(11) NOT NULL COMMENT 'أمر الشراء المحمَّل (proc_order.id — بلا FK كسائر proc_*)',
  `doc_no` varchar(60) NOT NULL COMMENT 'رقم مستند المصروف (بوليصة/إيصال جمركي…)',
  `cost_type` varchar(30) NOT NULL DEFAULT 'شحن' COMMENT 'شحن · جمارك · تخليص · نقل داخلي · أخرى',
  `amount` decimal(18,2) NOT NULL COMMENT 'المبلغ بعملة المستند',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `fx_rate` decimal(12,4) NOT NULL DEFAULT 1.0000 COMMENT 'إلى معادل الدفاتر — ضربًا (عرف base_amount)',
  `base_amount` decimal(18,2) NOT NULL COMMENT 'المعادل = amount × fx_rate',
  `supplier_id` int(11) DEFAULT NULL COMMENT 'مقدم الخدمة (proc_supplier) إن وُجد',
  `notes` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_landed_order` (`company_id`,`order_id`,`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='التكلفة الوصولية لأوامر الشراء — ترسمل على تكلفة الاستلام توزيعا بالقيمة';

-- ── Table: proc_lookup ──
CREATE TABLE `proc_lookup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `type` varchar(40) NOT NULL COMMENT 'فئة صنف / وحدة قياس / نوع مخزن / طبيعة مادة',
  `name` varchar(150) NOT NULL,
  `extra` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_lookup_company_type` (`company_id`,`type`),
  KEY `idx_proc_lookup_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_order ──
CREATE TABLE `proc_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL COMMENT 'proc_supplier.id',
  `project_id` int(11) DEFAULT NULL COMMENT 'البُعد المفقود — يُشتق من طلب الشراء (FES: المشروع إلزامي)',
  `request_id` int(11) DEFAULT NULL COMMENT 'proc_request.id',
  `fin_approval_ref` varchar(100) DEFAULT NULL COMMENT 'مرجع الاعتماد المالي (شرط الإصدار)',
  `op_classification` varchar(20) NOT NULL DEFAULT 'استهلاكية',
  `currency` varchar(10) NOT NULL DEFAULT 'SDG' COMMENT 'SDG/USD',
  `fx_rate` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `payment_time` varchar(20) NOT NULL DEFAULT 'فوري' COMMENT 'فوري/مؤجل/آجل 30/60/90',
  `expected_receipt_type` varchar(20) NOT NULL DEFAULT 'مخزن' COMMENT 'مخزن/مباشر للمعدة/مشروع/ورشة',
  `expected_delivery_date` date DEFAULT NULL COMMENT 'موعد التوريد المتفق — الإلزامي الثالث (§5.1)',
  `sent_at` datetime DEFAULT NULL COMMENT 'لحظة الإرسال للمورد (Approved→Sent §8.2)',
  `sent_by` int(11) DEFAULT NULL COMMENT 'مُرسِل الأمر',
  `late_alerted_at` datetime DEFAULT NULL COMMENT 'آخر إنذار تأخّر توريد (Late بعدّاده §8.2)',
  `received_pct` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'نسبة المستلَم — 100 = اكتمال',
  `first_receipt_at` datetime DEFAULT NULL COMMENT 'أول استلام (PartialReceived)',
  `final_receipt_at` datetime DEFAULT NULL COMMENT 'الاستلام النهائي — زنادُ الأثر المالي',
  `closed_at` datetime DEFAULT NULL COMMENT 'إقفال الأمر',
  `closed_by` int(11) DEFAULT NULL COMMENT 'مُقفِل الأمر',
  `invoice_no` varchar(64) DEFAULT NULL COMMENT 'رقم فاتورة المورد',
  `invoice_date` date DEFAULT NULL COMMENT 'تاريخ الفاتورة',
  `invoice_amount` decimal(18,2) DEFAULT NULL COMMENT 'قيمة الفاتورة (لمضاهاة الفرق)',
  `match_state` varchar(16) NOT NULL DEFAULT 'unmatched' COMMENT 'unmatched·matched·var_pending·rejected (§8.2)',
  `var_decision` varchar(30) DEFAULT NULL COMMENT 'حسم الفرق: قبول الفرق · إشعار دائن · رفض الفاتورة',
  `var_decision_reason` varchar(255) DEFAULT NULL COMMENT 'تفسير الحسم — إلزامي مع القرار',
  `var_decided_by` int(11) DEFAULT NULL COMMENT 'مخوَّل الحسم users.id',
  `var_decided_at` datetime DEFAULT NULL COMMENT 'لحظة الحسم',
  `matched_at` datetime DEFAULT NULL COMMENT 'لحظة المطابقة',
  `matched_by` int(11) DEFAULT NULL COMMENT 'من طابق',
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'المعادل الموحّد = total_amount × fx_rate (FES §3.3)',
  `tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الضريبة — متطلبٌ نظامي (الدستور §8)',
  `due_date` date DEFAULT NULL COMMENT 'تاريخ استحقاق السداد (FES §3.1 فهرس الاستحقاق)',
  `event_id` int(11) DEFAULT NULL COMMENT 'مرجع الحدث المالي المنشور — قراءةً بمرجعه (§5.1-③)',
  `state` varchar(30) NOT NULL DEFAULT 'مسودة' COMMENT 'مسودة/مؤكَّد/استلام أولي/استلام نهائي/مطابَق/مغلق',
  `notes` mediumtext DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rfq_id` int(11) DEFAULT NULL COMMENT 'طلبُ العروضِ الذي رُسي عنه هذا الأمر (INJ-0091)',
  `award_id` int(11) DEFAULT NULL COMMENT 'صفُّ الترسيةِ الذي وُلد منه هذا الأمر (INJ-0091)',
  PRIMARY KEY (`id`),
  KEY `idx_proc_order_company_state` (`company_id`,`state`),
  KEY `idx_proc_order_deleted` (`is_deleted`),
  KEY `ix_po_receipt` (`state`,`final_receipt_at`),
  KEY `ix_po_project` (`project_id`),
  KEY `ix_po_due` (`due_date`),
  KEY `ix_po_match` (`match_state`),
  KEY `ix_po_event` (`event_id`),
  KEY `ix_po_expected` (`expected_delivery_date`),
  KEY `ix_po_rfq_id` (`rfq_id`),
  KEY `ix_po_award_id` (`award_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_order_line ──
CREATE TABLE `proc_order_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(200) NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `op_classification` varchar(20) DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_ordline_order` (`order_id`),
  CONSTRAINT `fk_proc_ordline_ord` FOREIGN KEY (`order_id`) REFERENCES `proc_order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_orderpoint ──
CREATE TABLE `proc_orderpoint` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL COMMENT 'proc_item.id',
  `warehouse_id` int(11) DEFAULT NULL COMMENT 'proc_warehouse.id',
  `min_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `trigger_qty` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ROP - نقطة إعادة الطلب',
  `safety_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `mode` varchar(20) NOT NULL DEFAULT 'يدوي' COMMENT 'تلقائي / يدوي',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_orderpoint_company` (`company_id`),
  KEY `idx_proc_orderpoint_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_receipt_custody ──
CREATE TABLE `proc_receipt_custody` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `holder_id` int(11) DEFAULT NULL COMMENT 'المستلِم (users/employees.id بلا FK)',
  `holder_name` varchar(150) DEFAULT NULL COMMENT 'لقطة نصية للمستلِم',
  `receipt_date` date DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL COMMENT 'proc_supplier.id',
  `order_id` int(11) DEFAULT NULL COMMENT 'proc_order.id',
  `receipt_location` varchar(255) DEFAULT NULL COMMENT 'عطبرة/موقع المورد/…',
  `warehouse_id` int(11) DEFAULT NULL COMMENT 'مخزن الإدخال — إلزامي حين الوجهة مخزن؛ NULL لغير المخزنية',
  `expected_destination` varchar(30) NOT NULL DEFAULT 'مخزن' COMMENT 'مخزن/ورشة/مشروع/معدة',
  `state` varchar(30) NOT NULL DEFAULT 'مستلَمة' COMMENT 'مستلَمة/قيد الترحيل/مسلَّمة للوجهة',
  `notes` mediumtext DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_rc_company_state` (`company_id`,`state`),
  KEY `idx_proc_rc_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_receipt_line ──
CREATE TABLE `proc_receipt_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `custody_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(200) NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_rcline_custody` (`custody_id`),
  CONSTRAINT `fk_proc_rcline_custody` FOREIGN KEY (`custody_id`) REFERENCES `proc_receipt_custody` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_request ──
CREATE TABLE `proc_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `need_source` varchar(30) NOT NULL DEFAULT 'نقص مخزون' COMMENT 'خطة وقائية/أمر صيانة/نقص مخزون/إعادة طلب',
  `source_ref` varchar(100) DEFAULT NULL COMMENT 'مرجع المصدر (خطة/أمر/نقطة طلب)',
  `op_classification` varchar(20) NOT NULL DEFAULT 'استهلاكية' COMMENT 'وقائية/تصحيحية/رأسمالية/استهلاكية',
  `requesting_dept` varchar(40) DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'عادي' COMMENT 'عادي/عاجل/حرج',
  `fin_approval_state` varchar(20) NOT NULL DEFAULT 'بانتظار' COMMENT 'بانتظار/معتمد مالياً/مرفوض',
  `state` varchar(30) NOT NULL DEFAULT 'مسودة' COMMENT 'مسودة/مقدَّم/اعتماد المشتريات/مراجعة مالية/معتمد مالياً/محوَّل لأمر شراء/مغلق/مرفوض',
  `notes` mediumtext DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_request_company_state` (`company_id`,`state`),
  KEY `idx_proc_request_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_request_line ──
CREATE TABLE `proc_request_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL COMMENT 'proc_item.id (اختياري)',
  `item_name` varchar(200) NOT NULL COMMENT 'لقطة نصية للصنف',
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `op_classification` varchar(20) DEFAULT NULL COMMENT 'تصنيف على مستوى البند',
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_reqline_request` (`request_id`),
  CONSTRAINT `fk_proc_reqline_req` FOREIGN KEY (`request_id`) REFERENCES `proc_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_stock_move ──
CREATE TABLE `proc_stock_move` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `move_type` varchar(20) NOT NULL COMMENT 'استلام/صرف/إرجاع/تحويل',
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(18,4) DEFAULT NULL COMMENT 'تكلفة الوحدة بمعادل الدفاتر: الاستلام بفعليته + نصيبه الوصولي · الصرف بالمتوسط لحظته · NULL=حركة تاريخية غير مسعرة',
  `ref_type` varchar(30) DEFAULT NULL COMMENT 'proc_order/proc_issue/proc_receipt/يدوي',
  `ref_id` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `moved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_move_company` (`company_id`),
  KEY `idx_proc_move_item_wh` (`item_id`,`warehouse_id`),
  CONSTRAINT `chk_psm_return_needs_ref` CHECK (`move_type` <> 'مرتجع' or `ref_type` = 'issue' and `ref_id` is not null and `ref_id` > 0 or `note` like '%legacy_no_ref%')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_supplier ──
CREATE TABLE `proc_supplier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `supply_role` varchar(30) NOT NULL DEFAULT 'تشغيلي' COMMENT 'تشغيلي دائماً في هذه الوحدة',
  `dealing_nature` varchar(255) DEFAULT NULL COMMENT 'قطع/زيوت/فلاتر/خدمات إصلاح',
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_supplier_company` (`company_id`),
  KEY `idx_proc_supplier_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: proc_warehouse ──
CREATE TABLE `proc_warehouse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'مخزن' COMMENT 'مخزن / ورشة / مباشر للآلية',
  `location` varchar(255) DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_wh_company` (`company_id`),
  KEY `idx_proc_wh_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: processed_operations ──
CREATE TABLE `processed_operations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer` varchar(64) NOT NULL COMMENT 'اسم المستهلك (يوافق ems_event_consumers.consumer)',
  `doc_type` varchar(64) NOT NULL COMMENT 'نوع المستند المصدر (fin_unit_record · claim · …)',
  `doc_id` bigint(20) unsigned NOT NULL COMMENT 'معرّف المستند المصدر',
  `effect_kind` varchar(64) NOT NULL COMMENT 'نوع الأثر المعالَج (revenue · supplier_due · …)',
  `event_id` bigint(20) unsigned DEFAULT NULL COMMENT 'الحدث الذي حمل المعالجة (تتبع لا مفتاح)',
  `processed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_processed_op` (`consumer`,`doc_type`,`doc_id`,`effect_kind`),
  KEY `ix_po_doc` (`doc_type`,`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-06 ركن ③: عطالة المستهلك على مستوى (المستند × الأثر) — Insert-only';

-- ── Table: products ──
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `product_type` enum('خدمة','معدة','مادة') NOT NULL DEFAULT 'خدمة',
  `revenue_model` enum('hourly','ton','meter') DEFAULT NULL,
  `default_uom` varchar(30) DEFAULT NULL,
  `standard_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency` enum('USD','SDG') NOT NULL DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_company_code` (`company_id`,`product_code`),
  KEY `idx_prod_scope` (`company_id`,`is_deleted`),
  KEY `idx_prod_model` (`revenue_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: project ──
CREATE TABLE `project` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL COMMENT '┘àÏ╣Ï▒┘ü Ïº┘äÏ╣┘à┘è┘ä ┘à┘å Ï¼Ï»┘ê┘ä clients',
  `name` varchar(150) NOT NULL,
  `client` varchar(150) NOT NULL,
  `location` varchar(200) NOT NULL,
  `project_code` varchar(50) DEFAULT NULL COMMENT 'كود المشروع',
  `mine_code` varchar(100) DEFAULT NULL COMMENT 'كود المنجم',
  `category` varchar(100) DEFAULT NULL COMMENT 'الفئة',
  `sub_sector` varchar(100) DEFAULT NULL COMMENT 'القطاع الفرعي',
  `state` varchar(100) DEFAULT NULL COMMENT 'الولاية',
  `region` varchar(100) DEFAULT NULL COMMENT 'المنطقة',
  `nearest_market` varchar(100) DEFAULT NULL COMMENT 'أقرب سوق',
  `latitude` varchar(50) DEFAULT NULL COMMENT 'خط العرض',
  `longitude` varchar(50) DEFAULT NULL COMMENT 'خط الطول',
  `total` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم المنشئ',
  `create_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
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
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `quotation_code` varchar(50) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `opportunity_id` int(10) unsigned DEFAULT NULL,
  `currency` enum('USD','SDG') NOT NULL DEFAULT 'USD',
  `amount_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `validity_date` date DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `state` enum('مسودة','مقدم','مقبول','مرفوض') NOT NULL DEFAULT 'مسودة',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quotations_company_code` (`company_id`,`quotation_code`),
  KEY `idx_quo_scope` (`company_id`,`is_deleted`),
  KEY `idx_quo_opp` (`opportunity_id`),
  KEY `idx_quo_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: rate_book_lines ──
CREATE TABLE `rate_book_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `equipment_type_id` int(11) NOT NULL COMMENT 'فئةُ المعدة — equipments_types.id',
  `work_model` enum('hour','day','shift','month','ton','meter','trip','cbm') NOT NULL DEFAULT 'hour',
  `tier_from_days` int(11) NOT NULL DEFAULT 1 COMMENT 'بدايةُ شريحة المدة بالأيام',
  `tier_to_days` int(11) DEFAULT NULL COMMENT 'نهايتُها — NULL = ما فوق',
  `unit_price` decimal(14,2) NOT NULL COMMENT 'سعرُ الوحدة في هذه الشريحة',
  `min_hire_days` int(11) NOT NULL DEFAULT 1 COMMENT 'الحدُّ الأدنى لمدة الإيجار',
  `min_hours_per_day` decimal(6,2) DEFAULT NULL COMMENT 'الحدُّ الأدنى للساعات اليومية المفوترة',
  `mobilization_fee` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'رسمُ التعبئة/الترحيل',
  `operator_included` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'أالمشغّلُ ضمن السعر؟',
  `fuel_included` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'أالوقودُ ضمن السعر؟',
  `note` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_tier` (`company_id`,`book_id`,`equipment_type_id`,`work_model`,`tier_from_days`),
  KEY `ix_line_book` (`company_id`,`book_id`),
  KEY `ix_line_lookup` (`company_id`,`equipment_type_id`,`work_model`,`tier_from_days`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='بنودُ دفتر الأسعار — سعرٌ لكل (فئة × نموذج عمل × شريحة مدة) (RENTAL-CORE ②)';

-- ── Table: rate_books ──
CREATE TABLE `rate_books` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `book_code` varchar(40) NOT NULL COMMENT 'كودُ الدفتر — فريد داخل الشركة',
  `name` varchar(160) NOT NULL,
  `currency` enum('USD','SDG') NOT NULL DEFAULT 'USD',
  `client_id` int(11) DEFAULT NULL COMMENT 'دفترٌ خاصٌّ بعميل — NULL يعني الدفترَ العام',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL COMMENT 'NULL = مفتوح',
  `state` enum('مسودة','معتمد','منتهٍ') NOT NULL DEFAULT 'مسودة',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_book_code` (`company_id`,`book_code`),
  KEY `ix_book_live` (`company_id`,`state`,`valid_from`,`valid_to`),
  KEY `ix_book_client` (`company_id`,`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='دفاترُ الأسعار — رأسُ الدفتر بسريانه وعملته (RENTAL-CORE ②)';

-- ── Table: readiness_lines ──
CREATE TABLE `readiness_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `readiness_code` varchar(50) NOT NULL,
  `contract_ref` int(11) NOT NULL,
  `name` enum('جاهزية الأسطول','جاهزية الموردين','جاهزية القوى','جاهزية التمويل','جاهزية الصيانة','جاهزية الموقع') NOT NULL DEFAULT 'جاهزية الأسطول',
  `source_ref` varchar(60) DEFAULT NULL,
  `required` varchar(255) DEFAULT NULL,
  `available` varchar(255) DEFAULT NULL,
  `state` enum('مجتاز','فجوة','قيد المعالجة') NOT NULL DEFAULT 'قيد المعالجة',
  `gap_note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rdl_company_code` (`company_id`,`readiness_code`),
  KEY `idx_rdl_scope` (`company_id`,`is_deleted`),
  KEY `idx_rdl_contract` (`contract_ref`),
  KEY `idx_rdl_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='INJAZ-S05 §6.12 — بنود فحص الجاهزية الستة بحالاتها لكل عقد';

-- ── Table: rec_applications ──
CREATE TABLE `rec_applications` (
  `app_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `vac_id` int(10) unsigned NOT NULL,
  `applicant_name` varchar(120) NOT NULL,
  `applicant_phone` varchar(40) DEFAULT NULL,
  `cv_ref` varchar(190) DEFAULT NULL COMMENT '③ السيرةُ الذاتية — مرجعُ الملف',
  `stage` enum('received','screening','interview','practical_test','offer','offer_accepted','contracting','onboarded','probation','confirmed','rejected','withdrawn') NOT NULL DEFAULT 'received' COMMENT 'الخطواتُ ③→⑩ — والرفضُ والانسحابُ خروجان معلَنان',
  `stage_note` varchar(255) DEFAULT NULL,
  `interview_at` datetime DEFAULT NULL,
  `test_score` decimal(5,2) DEFAULT NULL COMMENT '⑥ الاختبارُ العمليُّ للمشغّل',
  `offer_ref` varchar(120) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL COMMENT '⑨ المباشرة — يربط بموظفٍ حقيقيٍّ في employees',
  `probation_end` date DEFAULT NULL COMMENT '⑩ نهايةُ فترة التجربة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`app_id`),
  KEY `ix_app_vac` (`vac_id`,`stage`),
  CONSTRAINT `fk_app_vac` FOREIGN KEY (`vac_id`) REFERENCES `rec_vacancies` (`vac_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: rec_stage_log ──
CREATE TABLE `rec_stage_log` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` int(10) unsigned NOT NULL,
  `from_stage` varchar(30) DEFAULT NULL,
  `to_stage` varchar(30) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `by_person` int(11) DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `ix_rsl_app` (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجلُّ التقدم — Insert-only: من فعل ماذا ومتى';

-- ── Table: rec_vacancies ──
CREATE TABLE `rec_vacancies` (
  `vac_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `vacancy_no` varchar(30) NOT NULL,
  `job_title_id` int(11) DEFAULT NULL COMMENT 'job_titles — قاموسُ المسميات (SEC-01)',
  `title_text` varchar(120) NOT NULL,
  `org_unit_id` int(11) DEFAULT NULL COMMENT 'org_units — الإدارةُ الطالبة',
  `site_scope` varchar(80) DEFAULT NULL,
  `headcount` int(11) NOT NULL DEFAULT 1,
  `reason` varchar(255) DEFAULT NULL,
  `state` enum('draft','open','filled','cancelled') NOT NULL DEFAULT 'open',
  `posted_at` date DEFAULT NULL COMMENT '② نشرُ الوظيفة',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`vac_id`),
  UNIQUE KEY `uq_vac` (`company_id`,`vacancy_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='① طلبُ الشاغر — أولُ الدورة العشرية';

-- ── Table: recurring_tasks ──
CREATE TABLE `recurring_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `template_id` int(10) unsigned NOT NULL,
  `freq` varchar(12) NOT NULL DEFAULT 'monthly' COMMENT 'daily|weekly|monthly|quarterly',
  `day_key` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'يوم الأسبوع/الشهر بحسب النمط',
  `next_run_at` datetime DEFAULT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_rt_next` (`active`,`next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: report_role_permissions ──
CREATE TABLE `report_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `report_code` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_report` (`role_id`,`report_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: request_responses ──
CREATE TABLE `request_responses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `request_id` bigint(20) unsigned NOT NULL,
  `decision` varchar(24) NOT NULL COMMENT '① القرار',
  `decided_by` int(10) unsigned NOT NULL COMMENT '② من قرّر',
  `decided_capacity` varchar(60) DEFAULT NULL COMMENT '③ صفته',
  `decided_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '④ تاريخه',
  `notes` varchar(400) DEFAULT NULL COMMENT '⑤ الملاحظات',
  `action_required` varchar(300) DEFAULT NULL COMMENT '⑥ ما يجب فعله',
  `result_doc_ref` varchar(200) DEFAULT NULL COMMENT '⑦ المستند الناتج',
  `executed_summary` varchar(300) DEFAULT NULL COMMENT '⑧ التنفيذ الذي تم',
  `next_step` varchar(200) DEFAULT NULL COMMENT '⑨ الخطوة اللاحقة',
  `origin_link` varchar(200) NOT NULL DEFAULT '' COMMENT 'رابط الأصل',
  PRIMARY KEY (`id`),
  KEY `ix_rr_req` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WF-05: الطلب لا يُغلق بتغيير حالة — تسعة عناصر تصل مقدّمه';

-- ── Table: request_routes ──
CREATE TABLE `request_routes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_kind` varchar(20) NOT NULL COMMENT 'nav_action|approval|request|ticket|recurring|escalation',
  `trigger_key` varchar(60) NOT NULL DEFAULT '*' COMMENT 'رمز النوع/الفعل أو * للقاعدة العامة',
  `rule_text` varchar(300) NOT NULL COMMENT 'قاعدة التوجيه المعلنة — تفسير الظهور ②',
  `receiver_dept` varchar(80) NOT NULL,
  `receiver_role` varchar(120) NOT NULL,
  `fallback_role` varchar(120) NOT NULL COMMENT 'البديل عند الغياب',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rr` (`item_kind`,`trigger_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WFM: التوجيه بقاعدة لا باجتهاد — واليدوي استثناء يُسجَّل';

-- ── Table: request_types ──
CREATE TABLE `request_types` (
  `code` varchar(12) NOT NULL COMMENT 'RQ-HR-01…',
  `name_ar` varchar(160) NOT NULL,
  `owner_dept` varchar(80) NOT NULL COMMENT 'الإدارة المالكة',
  `submitter` varchar(160) NOT NULL COMMENT 'من يقدّمه',
  `receiver` varchar(160) NOT NULL COMMENT 'من يستقبله — صفر طلب بلا جهة',
  `approval_chain` varchar(300) NOT NULL,
  `sla_hours` int(10) unsigned NOT NULL DEFAULT 72,
  `deliverable` varchar(200) NOT NULL COMMENT 'المخرَج الناتج',
  `source_ref` varchar(160) NOT NULL COMMENT 'مرجع الدورة في وثيقة الإدارة',
  `status` varchar(16) NOT NULL DEFAULT 'active' COMMENT 'active|proposed|retired',
  `display_order` smallint(5) unsigned NOT NULL DEFAULT 100,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WFM الورقة 04 — تُستخرج من الدورات ولا تُخترع';

-- ── Table: requests ──
CREATE TABLE `requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `request_no` varchar(20) DEFAULT NULL COMMENT 'REQ-000001',
  `request_type_code` varchar(12) NOT NULL,
  `requester_user_id` int(10) unsigned NOT NULL,
  `beneficiary_ref` varchar(60) DEFAULT NULL COMMENT 'المستفيد إن خالف المقدّم',
  `org_unit_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `site_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(300) NOT NULL,
  `fields_json` mediumtext DEFAULT NULL COMMENT 'حقول النموذج المشتقة من الصفة والعقد',
  `status` varchar(24) NOT NULL DEFAULT 'draft' COMMENT 'draft|submitted|routed|in_approval|approved|rejected|executing|executed|closed|returned|cancelled',
  `current_holder_user_id` int(10) unsigned DEFAULT NULL COMMENT 'من هو عنده الآن — AC-WFM-07',
  `current_step` smallint(5) unsigned NOT NULL DEFAULT 0,
  `submitted_at` datetime DEFAULT NULL,
  `sla_due_at` datetime DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `status_reason` varchar(300) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'المنشئ',
  `created_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المنشئ لحظة الفعل',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(10) unsigned DEFAULT NULL COMMENT 'المعتمِد',
  `approved_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المعتمِد',
  `approved_at` datetime DEFAULT NULL,
  `delegation_ref` varchar(60) DEFAULT NULL COMMENT 'مرجع التفويض إن اعتُمد به',
  `parent_ref` varchar(60) DEFAULT NULL COMMENT 'المرجع الأب',
  PRIMARY KEY (`id`),
  KEY `ix_rq_type` (`request_type_code`,`status`),
  KEY `ix_rq_requester` (`company_id`,`requester_user_id`,`status`),
  KEY `ix_rq_holder` (`company_id`,`current_holder_user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WFM: الطلب يُقدَّم قصدًا — وصفر طلب لا يُعرف أين توقف';

-- ── Table: rfq_awards ──
CREATE TABLE `rfq_awards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `rfq_id` int(10) unsigned NOT NULL,
  `line_id` int(10) unsigned NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
  `quote_id` int(10) unsigned DEFAULT NULL COMMENT 'العرضُ الذي رُسي عليه — والسعرُ يُقرأ منه',
  `qty_awarded` decimal(16,2) NOT NULL,
  `unit_price` decimal(14,4) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `reason` varchar(255) DEFAULT NULL COMMENT 'حجّةُ الاختيار حين لا يكون الأرخص',
  `awarded_by` int(10) unsigned DEFAULT NULL,
  `awarded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_award` (`line_id`,`supplier_id`) COMMENT 'ترسيةٌ واحدةٌ لكل (بند × مورد)',
  KEY `ix_rfq_award` (`company_id`,`rfq_id`),
  CONSTRAINT `fk_rfq_award_line` FOREIGN KEY (`line_id`) REFERENCES `rfq_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_rfq_award_qty` CHECK (`qty_awarded` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: rfq_lines ──
CREATE TABLE `rfq_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `rfq_id` int(10) unsigned NOT NULL,
  `commitment_id` int(10) unsigned NOT NULL COMMENT 'مصدرُ البند — «من الالتزامات اشتقاقًا»',
  `line_no` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `unit_type` varchar(16) DEFAULT NULL,
  `qty_required` decimal(16,2) NOT NULL COMMENT 'من الالتزام — لا يُكتب بيد',
  `qty_awarded` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT 'عدّادُ المرسى — يُحرَس بـCHECK',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_line` (`rfq_id`,`commitment_id`) COMMENT 'التزامٌ واحدٌ = بندٌ واحدٌ في الطلب — لا اشتقاقَ مضاعف',
  KEY `ix_rfq_line` (`company_id`,`rfq_id`),
  CONSTRAINT `fk_rfq_line_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `supplier_rfqs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_rfq_line_award` CHECK (`qty_awarded` >= 0 and `qty_awarded` <= `qty_required`),
  CONSTRAINT `ck_rfq_line_qty` CHECK (`qty_required` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: rfq_quotes ──
CREATE TABLE `rfq_quotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `rfq_id` int(10) unsigned NOT NULL,
  `line_id` int(10) unsigned NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
  `unit_price` decimal(14,4) NOT NULL COMMENT 'المعيارُ الأول: السعر',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `qty_offered` decimal(16,2) NOT NULL COMMENT 'ما يقدر عليه — قد يكون جزءًا من المطلوب',
  `readiness_days` int(11) DEFAULT NULL COMMENT 'المعيارُ الثاني: الجاهزية (أيامًا)',
  `record_rating` decimal(4,2) DEFAULT NULL COMMENT 'المعيارُ الثالث: السجل — من M-17 لا من رأي',
  `note` varchar(255) DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `submitted_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_quote` (`line_id`,`supplier_id`) COMMENT 'عرضٌ واحدٌ لكل (بند × مورد) — والتعديلُ استبدالٌ لا تكديس',
  KEY `ix_rfq_quote` (`company_id`,`rfq_id`,`supplier_id`),
  CONSTRAINT `fk_rfq_quote_line` FOREIGN KEY (`line_id`) REFERENCES `rfq_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_rfq_quote_price` CHECK (`unit_price` > 0 and `qty_offered` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: risk_acceptances ──
CREATE TABLE `risk_acceptances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `risk_id` int(10) unsigned NOT NULL,
  `level_at_acceptance` enum('منخفض','متوسط','مرتفع','حرج') NOT NULL COMMENT 'المحظور لا يُقبل بحال — غائب عمدًا من القائمة',
  `authority` enum('risk_owner','owner_with_analyst','deputy','ceo') NOT NULL COMMENT 'مصفوفة السلطة (ورقة 27) — تفرضها الخدمة على المستوى',
  `authority_ref` varchar(120) NOT NULL DEFAULT '' COMMENT '§9-1 مرجع التفويض — بأي صفة اعتمد',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT '§9-1 المرجع الأب — رمز الخطر',
  `accepted_by` int(11) NOT NULL,
  `analyst_review_by` int(11) DEFAULT NULL COMMENT 'للمتوسط: مراجعة محلل المخاطر',
  `review_due` date NOT NULL COMMENT 'مهلة المراجعة — القبول ليس إهمالًا',
  `note` text DEFAULT NULL,
  `compensating_ctl` varchar(255) NOT NULL DEFAULT '' COMMENT '§7-2 ضوابط معوِّضة',
  `withdrawn_by` int(11) DEFAULT NULL COMMENT 'سحبُ القبولِ من الجهة نفسها أو أعلى',
  `withdrawn_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_risk` (`company_id`,`risk_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16: قبول الخطر قرار رسمي موقَّع بمهلة مراجعة (RK-04)';

-- ── Table: risk_appetite ──
CREATE TABLE `risk_appetite` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `domain` varchar(48) NOT NULL COMMENT 'مجال الشهية (8 مجالات)',
  `appetite_ar` varchar(255) NOT NULL COMMENT 'مستوى الشهية المعلن',
  `tolerance_ar` varchar(255) NOT NULL COMMENT 'حد التحمل',
  `authority_ar` varchar(160) NOT NULL COMMENT 'المخوَّل',
  `changeable_ar` varchar(160) NOT NULL DEFAULT '' COMMENT 'أتتغير بالخطة العامة؟',
  `immutable_floor` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = لا تتغير بحال (السلامة · القانون · تسرب البيانات)',
  `plan_mode` enum('النمو والتوسع','التثبيت والكفاءة','الحماية والانكماش') DEFAULT NULL COMMENT 'NULL = السطر الأساسي؛ وإلا فتعديل نمط خطة',
  `updated_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL COMMENT '§13-1 الرئيسُ التنفيذيُّ حصرًا',
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `prev_appetite_ar` varchar(255) NOT NULL DEFAULT '' COMMENT '§7-1: اعتمادُ شهيةٍ جديدةٍ يحفظ السابقة',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain` (`company_id`,`domain`,`plan_mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16 ورقة 25: شهية المخاطر وحدود التحمل — يحددها الرئيس';

-- ── Table: risk_assessments ──
CREATE TABLE `risk_assessments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `risk_id` int(10) unsigned NOT NULL,
  `assess_type` enum('inherent','residual','target') NOT NULL,
  `likelihood` tinyint(3) unsigned NOT NULL COMMENT '1..5',
  `impacts_json` text DEFAULT NULL COMMENT 'الأبعاد الثمانية (ورقة 25) {dimension: 1..5}',
  `impact_max` tinyint(3) unsigned NOT NULL COMMENT 'أقصى بعد — السلامة لا تُقايَض',
  `score` tinyint(3) unsigned NOT NULL COMMENT 'likelihood × impact_max',
  `level` enum('منخفض','متوسط','مرتفع','حرج','محظور') NOT NULL,
  `confidence` enum('عالية','متوسطة','منخفضة') NOT NULL DEFAULT 'متوسطة' COMMENT 'درجة الثقة تُعلن ولا تُخفى',
  `technique` varchar(48) NOT NULL DEFAULT 'مصفوفة الخطر' COMMENT 'تقنية التقييم (ورقة 25)',
  `assessed_by` int(11) NOT NULL,
  `challenged_by` int(11) DEFAULT NULL COMMENT 'تحدي المخاطر المستقل (المرحلتان 5/8)',
  `approved_by` int(11) DEFAULT NULL COMMENT '§9-1 المعتمِد — الاسم والصفة',
  `approved_at` datetime DEFAULT NULL COMMENT '§9-1 تاريخ الاعتماد',
  `authority_ref` varchar(120) NOT NULL DEFAULT '' COMMENT '§9-1 مرجع التفويض',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT '§9-1 المرجع الأب — النسخة السابقة',
  `note` text DEFAULT NULL,
  `assessed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_risk_type` (`company_id`,`risk_id`,`assess_type`,`assessed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16: تقييمات مؤرخة لا تُكتب فوقها (RK-03) — إدراج فقط';

-- ── Table: risk_committee ──
CREATE TABLE `risk_committee` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `minute_code` varchar(16) NOT NULL COMMENT 'CMT-000001',
  `meeting_date` date NOT NULL,
  `cycle_ar` varchar(32) NOT NULL DEFAULT 'ربع سنوي',
  `attendees_ar` text DEFAULT NULL COMMENT 'الحاضرون بصفاتهم',
  `agenda_ar` text DEFAULT NULL,
  `resolutions_ar` text DEFAULT NULL COMMENT 'القراراتُ — مخرَجُ المرحلة ٨: محضرُ لجنة',
  `risks_reviewed` smallint(5) unsigned NOT NULL DEFAULT 0,
  `appetite_id` int(10) unsigned DEFAULT NULL COMMENT 'الشهيةُ المعتمدةُ في هذا المحضر',
  `state` enum('draft','approved') NOT NULL DEFAULT 'draft' COMMENT 'RK: المعتمدُ لا يُعدَّل — والتصحيحُ محضرٌ جديدٌ بمرجعه',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL COMMENT '§9-1 المعتمِد — الرئيسُ التنفيذي',
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'المحضرُ السابقُ في السلسلة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cmt` (`company_id`,`minute_code`),
  KEY `ix_cmt_date` (`company_id`,`meeting_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16 §6-1 الشاشة ١٤: لجنةُ المخاطرِ ومحاضرُها';

-- ── Table: risk_committee_items ──
CREATE TABLE `risk_committee_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `minute_id` int(10) unsigned NOT NULL,
  `risk_id` int(10) unsigned DEFAULT NULL,
  `item_ar` varchar(255) NOT NULL,
  `resolution_ar` varchar(255) NOT NULL DEFAULT '',
  `owner_user_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_cmti_minute` (`company_id`,`minute_id`),
  KEY `ix_cmti_risk` (`company_id`,`risk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16: بنودُ محضرِ اللجنةِ وقراراتُها بمسؤولٍ ومهلة';

-- ── Table: risk_control_evidence ──
CREATE TABLE `risk_control_evidence` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `control_id` int(10) unsigned NOT NULL,
  `kind` enum('execution','verification') NOT NULL DEFAULT 'execution' COMMENT 'دليل تنفيذ من المالك · تحقق من المستقل',
  `evidence_text` text NOT NULL,
  `evidence_ref` varchar(255) DEFAULT NULL COMMENT 'مرجع ملف/صورة/سجل',
  `result` enum('فعال','فعال جزئيا','غير فعال') DEFAULT NULL COMMENT 'للتحقق فقط',
  `submitted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_ctl` (`company_id`,`control_id`,`kind`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16: أدلة تنفيذ الضوابط وتحققاتها — RK-07 بدليل لا بادعاء';

-- ── Table: risk_control_links ──
CREATE TABLE `risk_control_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `risk_id` int(10) unsigned NOT NULL,
  `control_id` int(10) unsigned NOT NULL,
  `linked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_link` (`company_id`,`risk_id`,`control_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16: خريطة ضوابط الخطر (المرحلة 6)';

-- ── Table: risk_controls ──
CREATE TABLE `risk_controls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `control_code` varchar(16) NOT NULL COMMENT 'CTL-0001',
  `name_ar` varchar(255) NOT NULL,
  `ctype` enum('وقائي','كاشف','تصحيحي') NOT NULL,
  `owner_user_id` int(11) NOT NULL COMMENT 'من يشغّل الضابط فعلًا',
  `process_ref` varchar(160) NOT NULL DEFAULT '' COMMENT 'أين يقع في الدورة',
  `frequency` enum('كل وردية','يومي','أسبوعي','شهري','عند الحدث') NOT NULL,
  `evidence_spec` varchar(255) NOT NULL DEFAULT '' COMMENT 'ما يُثبت التنفيذ — إلزامي ولا يُحتسب بدونه',
  `effectiveness` enum('فعال','فعال جزئيا','غير فعال','غير مثبت') NOT NULL DEFAULT 'غير مثبت',
  `last_verified_at` date DEFAULT NULL,
  `last_verify_result` varchar(255) DEFAULT NULL,
  `last_verified_by` int(11) DEFAULT NULL,
  `next_verify_due` date DEFAULT NULL,
  `is_critical` tinyint(1) NOT NULL DEFAULT 0,
  `hico_event` varchar(255) DEFAULT NULL COMMENT 'حرج: الحدث عالي العواقب الذي يمنعه',
  `perf_criterion` varchar(255) DEFAULT NULL COMMENT 'حرج: معيار الأداء',
  `verify_method` varchar(255) DEFAULT NULL COMMENT 'حرج: مشاهدة/قياس/سجل',
  `verifier_user_id` int(11) DEFAULT NULL COMMENT 'حرج: متحقق مستقل ≠ المالك (يفرضه الحارس)',
  `fail_action` varchar(255) DEFAULT NULL COMMENT 'حرج: الإجراء الفوري والتصعيد عند الفشل',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL COMMENT '§9-1 المُنشئ',
  `approved_by` int(11) DEFAULT NULL COMMENT '§9-1 المعتمِد',
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'الخطر الأب أو الضابط المستبدَل',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ctl_code` (`company_id`,`control_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16 ورقة 26: سجل الضوابط — الحرج بحقوله الخمسة';

-- ── Table: risk_escalations ──
CREATE TABLE `risk_escalations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `risk_id` int(10) unsigned DEFAULT NULL,
  `signal_id` int(10) unsigned DEFAULT NULL,
  `reason_ar` varchar(255) NOT NULL,
  `to_authority` enum('risk_manager','deputy','ceo') NOT NULL,
  `is_auto` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'التصعيد آلي بالمصفوفة لا بتقدير فردي',
  `acknowledged_by` int(11) DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_open` (`company_id`,`to_authority`,`acknowledged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16: الخطر الحرج لا يختفي عن الرئيس (RK-08) — تصعيد آلي';

-- ── Table: risk_export_log ──
CREATE TABLE `risk_export_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `exported_by` int(11) NOT NULL COMMENT '① المصدِّر',
  `actor_capacity` varchar(120) NOT NULL DEFAULT '' COMMENT '② بصفته',
  `screen_code` varchar(64) NOT NULL COMMENT '③ الشاشة',
  `view_key` varchar(48) NOT NULL DEFAULT 'default' COMMENT '④ المنظر',
  `columns_text` text DEFAULT NULL COMMENT '⑤ الأعمدة المصدَّرة',
  `filters_text` text DEFAULT NULL COMMENT '⑥ الفلاتر المطبَّقة',
  `blocked_text` text DEFAULT NULL COMMENT '⑦ المستبعَدُ بالصلاحية — الحقولُ الحساسةُ المحجوبة',
  `row_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '⑧ عددُ الصفوف',
  `exported_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '⑨ الوقت',
  `fmt` varchar(12) NOT NULL DEFAULT 'xlsx',
  PRIMARY KEY (`id`),
  KEY `ix_rxl_who` (`company_id`,`exported_by`,`exported_at`),
  KEY `ix_rxl_screen` (`company_id`,`screen_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16 §9-4: سجلُّ التصديرِ — تسعةُ بنودٍ لكلِّ ملفٍّ يخرج';

-- ── Table: risk_incidents ──
CREATE TABLE `risk_incidents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `incident_code` varchar(16) NOT NULL COMMENT 'INC-000001',
  `itype` enum('واقعة','واقعة كادت تقع','واقعة خسارة') NOT NULL COMMENT '§11-2: Incident · Near Miss · Loss Event — لا تُخلط',
  `ru_id` int(10) unsigned DEFAULT NULL COMMENT 'وحدة المخاطر المرشَّحة',
  `title` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `occurred_at` datetime NOT NULL COMMENT 'وقتُ الواقعة لا وقتُ التسجيل',
  `site_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `entity_type` varchar(40) NOT NULL DEFAULT '' COMMENT 'الكيان المتأثر — نوعُه',
  `entity_id` int(11) DEFAULT NULL,
  `root_cause` varchar(255) NOT NULL DEFAULT '' COMMENT 'تحليلُ السبب الجذري',
  `injury_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `downtime_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `loss_estimate` decimal(18,2) DEFAULT NULL COMMENT 'تعرضٌ مقدَّرٌ — لا قيدَ ماليًّا (RK-06)',
  `currency` varchar(8) NOT NULL DEFAULT '',
  `realized_risk_id` int(10) unsigned DEFAULT NULL COMMENT 'خطرٌ قائمٌ تحقّق — يُعاد تقييمُه',
  `signal_id` int(10) unsigned DEFAULT NULL COMMENT 'الإشارةُ التي وُلدت منها (SG-14)',
  `state` enum('logged','investigated','linked','closed') NOT NULL DEFAULT 'logged',
  `corrected_by_ref` varchar(32) NOT NULL DEFAULT '' COMMENT 'RK: التصحيحُ بمرجعٍ لا حذفًا — رمزُ الواقعةِ المصحِّحة',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inc` (`company_id`,`incident_code`),
  KEY `ix_inc_risk` (`company_id`,`realized_risk_id`),
  KEY `ix_inc_when` (`company_id`,`occurred_at`),
  KEY `ix_inc_type` (`company_id`,`itype`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16 §6-1 الشاشة ١٢: الحوادث والوقائع — ثلاثةُ أنواعٍ لا تُخلط';

-- ── Table: risk_kris ──
CREATE TABLE `risk_kris` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `ru_id` int(10) unsigned DEFAULT NULL COMMENT 'وحدة المخاطر المعنية',
  `dept_ar` varchar(80) NOT NULL COMMENT 'الإدارة صاحبة المؤشر',
  `name_ar` varchar(255) NOT NULL,
  `warn_threshold_ar` varchar(160) NOT NULL COMMENT 'حد الإنذار',
  `critical_threshold_ar` varchar(160) NOT NULL COMMENT 'الحد الحرج',
  `source_ar` varchar(160) NOT NULL COMMENT 'المصدر في النظام',
  `source_key` varchar(48) NOT NULL DEFAULT '' COMMENT 'مفتاحُ القارئ في محرّك المؤشرات',
  `read_mode` enum('آلي','يدوي') NOT NULL DEFAULT 'يدوي' COMMENT 'المرحلة ١٢: الآليُّ يُقرأ من النظام — والالتزامُ يُقاس ولا يُدَّعى',
  `warn_num` decimal(18,4) DEFAULT NULL COMMENT 'حدُّ الإنذار رقمًا للمقارنة الآلية',
  `critical_num` decimal(18,4) DEFAULT NULL COMMENT 'الحدُّ الحرج رقمًا',
  `direction` enum('تصاعدي','تنازلي') NOT NULL DEFAULT 'تصاعدي' COMMENT 'أيُّ الاتجاهين يُعدُّ تجاوزًا',
  `current_value` varchar(64) DEFAULT NULL,
  `kri_state` enum('ok','warn','critical','unread') NOT NULL DEFAULT 'unread',
  `last_read_at` timestamp NULL DEFAULT NULL,
  `last_read_by` int(11) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kri` (`company_id`,`dept_ar`,`name_ar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16 ورقة 26: مؤشرات الخطر — سابقة للحدث لا لاحقة';

-- ── Table: risk_register ──
CREATE TABLE `risk_register` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `risk_code` varchar(16) NOT NULL COMMENT 'RSK-000001',
  `ru_id` int(10) unsigned NOT NULL COMMENT 'وحدة المخاطر RU-xx',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `scope_type` enum('مؤسسي','إداري','مشروعي','موقعي') NOT NULL DEFAULT 'إداري',
  `scope_ref_type` varchar(24) DEFAULT NULL COMMENT 'site|contract|equipment|supplier|project',
  `scope_ref_id` int(11) DEFAULT NULL,
  `entity_type` varchar(24) DEFAULT NULL COMMENT 'الكيان المتأثر (مكوّن مفتاح التكرار)',
  `entity_id` int(11) DEFAULT NULL,
  `root_cause` varchar(255) NOT NULL DEFAULT '' COMMENT 'السبب الجذري (مكوّن مفتاح التكرار)',
  `owner_unit_id` int(11) DEFAULT NULL COMMENT 'RK-01: الإدارة المالكة حيث نشأ الخطر (org_units)',
  `risk_owner_user_id` int(11) DEFAULT NULL COMMENT 'مالك الخطر (مدير الإدارة المالكة)',
  `dedup_key` char(40) NOT NULL DEFAULT '' COMMENT 'sha1(ru|entity|root_cause_norm|scope) — النافذة تُفحص زمنيًّا',
  `state` enum('classified','owner_assigned','inherent_assessed','controls_linked','controls_evaluated','residual_assessed','appetite_compared','treatment_planned','accepted','monitoring','reassessment','closed','reopened') NOT NULL DEFAULT 'classified',
  `current_level` enum('منخفض','متوسط','مرتفع','حرج','محظور') DEFAULT NULL COMMENT 'آخر مستوى متبقٍّ معتمد — عليه مصفوفة السلطة',
  `appetite_verdict` enum('داخل الشهية','فوق الشهية','فوق حد التحمل','محظور') DEFAULT NULL COMMENT 'المرحلة ٩: حكمُ شهيةٍ آليٌّ — لا يُدخَل يدويًّا',
  `appetite_checked_at` datetime DEFAULT NULL,
  `exposure_amount` decimal(18,2) DEFAULT NULL COMMENT '§6-2: التعرضُ الماليُّ المقدَّر — تقديرٌ لا قيد (RK-06)',
  `exposure_currency` varchar(8) NOT NULL DEFAULT '',
  `target_level` varchar(16) NOT NULL DEFAULT '' COMMENT '§12-3: الخطرُ المستهدفُ — يُقاس عند الإغلاق',
  `control_effectiveness` varchar(24) NOT NULL DEFAULT '' COMMENT '§12-3: فعاليةُ الضوابطِ المجمَّعة',
  `confidence` varchar(16) NOT NULL DEFAULT '' COMMENT '§12-3: درجةُ الثقةِ — تُعلَن ولا تُخفى',
  `velocity` enum('فوري','أيام','أسابيع','أشهر','سنوات') DEFAULT NULL COMMENT 'سرعة التحقق',
  `horizon` enum('قصير','متوسط','طويل') DEFAULT NULL COMMENT 'الأفق الزمني',
  `review_due` date DEFAULT NULL COMMENT 'موعد المراجعة بحسب المستوى (شهري للحرج..سنوي للمنخفض)',
  `merged_into_id` int(10) unsigned DEFAULT NULL COMMENT 'دمج بقرار محلل — الصف يبقى أثرًا (لا حذف)',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_risk_code` (`company_id`,`risk_code`),
  KEY `ix_dedup` (`company_id`,`dedup_key`,`created_at`),
  KEY `ix_unit_state` (`company_id`,`ru_id`,`state`),
  KEY `ix_owner_unit` (`company_id`,`owner_unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16: السجل المركزي الواحد للمخاطر (RK-02) — لا حذف إطلاقًا';

-- ── Table: risk_reviews ──
CREATE TABLE `risk_reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `risk_id` int(10) unsigned NOT NULL,
  `review_code` varchar(16) NOT NULL COMMENT 'RVW-000001',
  `trigger_kind` enum('دورية','حدث','فشل ضابط','تجاوز مؤشر') NOT NULL DEFAULT 'دورية' COMMENT 'المرحلة ١٣: دوريًّا أو عند حدثٍ أو فشلِ ضابطٍ أو تجاوزِ مؤشر',
  `level_before` varchar(16) NOT NULL DEFAULT '',
  `level_after` varchar(16) NOT NULL DEFAULT '',
  `decision` enum('استمرار','إغلاق','تصعيد') NOT NULL COMMENT 'مخرَجُ المرحلة ١٣: قرارُ استمرارٍ أو إغلاقٍ أو تصعيد',
  `findings_ar` text DEFAULT NULL COMMENT 'ما وُجد — شاهدُ المراجعة',
  `assessment_id` int(10) unsigned DEFAULT NULL COMMENT 'التقييمُ الذي أنتجته هذه المراجعة',
  `next_review_due` date DEFAULT NULL,
  `reviewed_by` int(11) NOT NULL COMMENT 'محللُ المخاطر — §14-1 لا يملك الخطرَ ولا يقبله',
  `approved_by` int(11) DEFAULT NULL COMMENT '§9-1 المعتمِد — الاسمُ والصفة',
  `approved_at` datetime DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '' COMMENT '§9-1 مرجعُ التفويض',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT '§9-1 المرجعُ الأب — المراجعةُ السابقة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rvw` (`company_id`,`review_code`),
  KEY `ix_rvw_risk` (`company_id`,`risk_id`,`created_at`),
  KEY `ix_rvw_due` (`company_id`,`next_review_due`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16 المرحلة ١٣: المراجعاتُ الدورية — الجديدةُ تحفظ السابقة';

-- ── Table: risk_signals ──
CREATE TABLE `risk_signals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `sg_code` varchar(8) DEFAULT NULL COMMENT 'SG-01..SG-16 للآلية · NULL ليدوية/ميدانية',
  `rule_key` varchar(64) DEFAULT NULL COMMENT '§13-5: مفتاحُ عطالةِ القاعدةِ الآلية — NULL لليدوية',
  `source` enum('auto','manual','field') NOT NULL DEFAULT 'manual',
  `title` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ru_hint_id` int(10) unsigned DEFAULT NULL COMMENT 'الوحدة المرشحة من قاعدة الإشارة',
  `entity_type` varchar(24) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `scope_ref_type` varchar(24) DEFAULT NULL,
  `scope_ref_id` int(11) DEFAULT NULL,
  `root_cause` varchar(255) NOT NULL DEFAULT '',
  `site_id` int(11) DEFAULT NULL COMMENT 'ميداني: الموقع',
  `shift_ar` varchar(24) DEFAULT NULL COMMENT 'ميداني: الوردية',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'ميداني: المعدة',
  `photo_ref` varchar(255) DEFAULT NULL COMMENT 'ميداني: صورة مضغوطة تُرفع عند الاتصال',
  `sync_uuid` char(36) DEFAULT NULL COMMENT 'ورقة 32: مفتاح لكل إشارة ميدانية — إعادة المزامنة ترجع مرجع الأولى',
  `state` enum('pending','dismissed','linked','converted','escalated') NOT NULL DEFAULT 'pending',
  `triage_by` int(11) DEFAULT NULL,
  `triage_reason` varchar(255) DEFAULT NULL COMMENT 'قرار الفرز بسببه — الإهمال يُوسَم ولا يُحذف',
  `triaged_at` timestamp NULL DEFAULT NULL,
  `linked_risk_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sync` (`sync_uuid`),
  UNIQUE KEY `uq_sig_rule` (`company_id`,`rule_key`),
  KEY `ix_state` (`company_id`,`state`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16: إشارات الخطر قبل الفرز (RK-05) — ليس كل حدث خطرًا';

-- ── Table: risk_treatments ──
CREATE TABLE `risk_treatments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `risk_id` int(10) unsigned NOT NULL,
  `ttype` enum('تجنب','تقليل','نقل','قبول') NOT NULL,
  `plan_ar` text NOT NULL,
  `action_owner_user_id` int(11) NOT NULL COMMENT 'مسؤول المعالجة — يظهر في مهامه',
  `due_date` date NOT NULL,
  `state` enum('planned','in_progress','done','verified','overdue') NOT NULL DEFAULT 'planned',
  `done_evidence` text DEFAULT NULL COMMENT 'دليل الإنجاز — الإغلاق بقبول المتحقق',
  `done_attachment` varchar(255) DEFAULT NULL COMMENT 'مسارُ مرفقِ دليلِ الإنجاز',
  `done_ref` varchar(120) DEFAULT NULL COMMENT 'مرجعُ الدليل: رقمُ مستندٍ أو أمرِ عمل',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `authority_ref` varchar(120) NOT NULL DEFAULT '' COMMENT '§9-1 مرجع التفويض',
  `parent_ref` varchar(32) NOT NULL DEFAULT '' COMMENT '§9-1 المرجع الأب — رمز الخطر',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_owner_due` (`company_id`,`action_owner_user_id`,`state`,`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16: خطط المعالجة المسنَدة بمهلة ومسؤول';

-- ── Table: risk_units ──
CREATE TABLE `risk_units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `ru_code` varchar(8) NOT NULL COMMENT 'RU-01..RU-11',
  `name_ar` varchar(160) NOT NULL,
  `linked_depts` varchar(255) NOT NULL DEFAULT '' COMMENT 'الإدارات المرتبطة نصًّا',
  `coverage` text DEFAULT NULL COMMENT 'نطاق التغطية من الورقة 24',
  `output_ar` varchar(255) NOT NULL DEFAULT '' COMMENT 'المخرَج',
  `ref_standard` varchar(160) NOT NULL DEFAULT '' COMMENT 'المعيار المرجعي',
  `dedup_window_days` smallint(5) unsigned NOT NULL DEFAULT 90 COMMENT 'ورقة 32: 90 افتراضًا — الاستراتيجية أطول والتشغيلية أقصر',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ru` (`company_id`,`ru_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-16 ورقة 24: وحدات المخاطر الإحدى عشرة';

-- ── Table: role_permissions ──
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_id` (`role_id`,`module_id`),
  KEY `module_id` (`module_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: role_permissions_archive_auditor ──
CREATE TABLE `role_permissions_archive_auditor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_id` (`role_id`,`module_id`),
  KEY `module_id` (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: roles ──
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `parent_role_id` int(11) DEFAULT NULL,
  `level` int(11) DEFAULT 1,
  `role_scope` enum('gloable','mine') NOT NULL DEFAULT 'gloable',
  `status` varchar(10) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_role_id` (`parent_role_id`),
  CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`parent_role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: schema_migrations ──
CREATE TABLE `schema_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `checksum` char(40) NOT NULL COMMENT 'SHA-1 لمحتوى الملف وقت التطبيق',
  `status` enum('applied','baseline','failed') NOT NULL,
  `applied_at` datetime NOT NULL,
  `execution_ms` int(11) NOT NULL DEFAULT 0,
  `applied_by` varchar(128) NOT NULL DEFAULT '',
  `error_text` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schema_migrations_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: scr_access_review ──
CREATE TABLE `scr_access_review` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_cycle` varchar(300) DEFAULT NULL COMMENT 'رقم الدورة',
  `period_ref` varchar(300) DEFAULT NULL COMMENT 'الفترة',
  `date_launch` date DEFAULT NULL COMMENT 'تاريخ الإطلاق',
  `dept_name` varchar(300) DEFAULT NULL COMMENT 'الإدارة',
  `count_accounts_review` varchar(300) DEFAULT NULL COMMENT 'عدد الحسابات المراجَعة',
  `confirmed` varchar(300) DEFAULT NULL COMMENT 'المؤكَّدة',
  `required_revoke` varchar(300) DEFAULT NULL COMMENT 'المطلوب سحبها',
  `revoked_auto` varchar(300) DEFAULT NULL COMMENT 'المسحوبة آليًّا',
  `accounts_dormant` varchar(300) DEFAULT NULL COMMENT 'الحسابات الخاملة',
  `disabled` varchar(300) DEFAULT NULL COMMENT 'المعطَّلة',
  `conflicts_duties_detected` varchar(300) DEFAULT NULL COMMENT 'تعارضات واجبات مكتشفة',
  `exceptions_standing` varchar(300) DEFAULT NULL COMMENT 'استثناءات قائمة',
  `pct_response` varchar(300) DEFAULT NULL COMMENT 'نسبة الاستجابة',
  `auditor_dept` varchar(300) DEFAULT NULL COMMENT 'مراجع الإدارة',
  `date_closing` date DEFAULT NULL COMMENT 'تاريخ الإقفال',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_access_review_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة access_review.php';

-- ── Table: scr_asset_recon ──
CREATE TABLE `scr_asset_recon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_minutes_doc` varchar(300) DEFAULT NULL COMMENT 'رقم المحضر',
  `period_ref` varchar(300) DEFAULT NULL COMMENT 'الفترة',
  `code_equipment` varchar(300) DEFAULT NULL COMMENT 'كود المعدة',
  `hours_per_asset_log` varchar(300) DEFAULT NULL COMMENT 'الساعات حسب سجل الأصول',
  `hours_per_timesheet` varchar(300) DEFAULT NULL COMMENT 'الساعات حسب التايم شيت',
  `variance` varchar(300) DEFAULT NULL COMMENT 'الفرق',
  `pct_variance` varchar(300) DEFAULT NULL COMMENT 'نسبة الفرق',
  `classification_variance` varchar(300) DEFAULT NULL COMMENT 'تصنيف الفرق',
  `explanation_variance` varchar(300) DEFAULT NULL COMMENT 'تفسير الفرق',
  `correction_approved` varchar(300) DEFAULT NULL COMMENT 'التصحيح المعتمد',
  `impact_correction_on_depreciation` varchar(300) DEFAULT NULL COMMENT 'أثر التصحيح على الإهلاك',
  `matched_by` varchar(300) DEFAULT NULL COMMENT 'طابقه',
  `approved_by` varchar(300) DEFAULT NULL COMMENT 'اعتمده',
  `date_closing` date DEFAULT NULL COMMENT 'تاريخ الإقفال',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_asset_recon_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة asset_recon.php';

-- ── Table: scr_attendance ──
CREATE TABLE `scr_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `month_ref` varchar(300) DEFAULT NULL COMMENT 'الشهر',
  `code_employee` varchar(300) DEFAULT NULL COMMENT 'كود الموظف',
  `entry_date` varchar(300) DEFAULT NULL COMMENT 'التاريخ',
  `code_state` varchar(300) DEFAULT NULL COMMENT 'رمز الحالة',
  `description_state` varchar(300) DEFAULT NULL COMMENT 'وصف الحالة',
  `time_entry` varchar(300) DEFAULT NULL COMMENT 'وقت الدخول',
  `time_exit` varchar(300) DEFAULT NULL COMMENT 'وقت الخروج',
  `hours_attendance` varchar(300) DEFAULT NULL COMMENT 'ساعات الدوام',
  `delay_minutes` varchar(300) DEFAULT NULL COMMENT 'تأخير بالدقائق',
  `impact_salary` varchar(300) DEFAULT NULL COMMENT 'أثر الراتب',
  `impact_incentive` varchar(300) DEFAULT NULL COMMENT 'أثر الحافز',
  `impact_presence` varchar(300) DEFAULT NULL COMMENT 'أثر التواجد',
  `impact_billing` varchar(300) DEFAULT NULL COMMENT 'أثر الفوترة',
  `impact_due_supplier` varchar(300) DEFAULT NULL COMMENT 'أثر استحقاق المورد',
  `doc_supporting` varchar(300) DEFAULT NULL COMMENT 'المستند المؤيد',
  `recorded_by` varchar(300) DEFAULT NULL COMMENT 'سجّله',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_attendance_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة attendance.php';

-- ── Table: scr_break_glass ──
CREATE TABLE `scr_break_glass` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_request` varchar(300) DEFAULT NULL COMMENT 'رقم الطلب',
  `happened_at` varchar(300) DEFAULT NULL COMMENT 'التاريخ والوقت',
  `requester_name_capacity_role` varchar(300) DEFAULT NULL COMMENT 'الطالب — الاسم والصفة',
  `permission_required` varchar(300) DEFAULT NULL COMMENT 'الصلاحية المطلوبة',
  `screen_or_action` varchar(300) DEFAULT NULL COMMENT 'الشاشة أو الفعل',
  `reason_emergency` varchar(300) DEFAULT NULL COMMENT 'سبب الطوارئ',
  `impact_expected_if_not_granted` varchar(300) DEFAULT NULL COMMENT 'الأثر المتوقع لو لم تُمنح',
  `approver_first` varchar(300) DEFAULT NULL COMMENT 'الموافق الأول',
  `approver_second` varchar(300) DEFAULT NULL COMMENT 'الموافق الثاني',
  `time_grant` varchar(300) DEFAULT NULL COMMENT 'وقت المنح',
  `duration_permission` varchar(300) DEFAULT NULL COMMENT 'مدة الصلاحية',
  `time_expiry` varchar(300) DEFAULT NULL COMMENT 'وقت الانتهاء',
  `count_actions_executed_under_it` varchar(300) DEFAULT NULL COMMENT 'عدد الأفعال المنفَّذة تحتها',
  `report_review` varchar(300) DEFAULT NULL COMMENT 'تقرير المراجعة',
  `date_review` date DEFAULT NULL COMMENT 'تاريخ المراجعة',
  `result_review` varchar(300) DEFAULT NULL COMMENT 'نتيجة المراجعة',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_break_glass_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة break_glass.php';

-- ── Table: scr_business_models ──
CREATE TABLE `scr_business_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `code_model` varchar(300) DEFAULT NULL COMMENT 'كود النموذج',
  `name_model` varchar(300) DEFAULT NULL COMMENT 'اسم النموذج',
  `unit_work` varchar(300) DEFAULT NULL COMMENT 'وحدة العمل',
  `unit_measure` varchar(300) DEFAULT NULL COMMENT 'وحدة القياس',
  `method_measure_field` varchar(300) DEFAULT NULL COMMENT 'طريقة القياس الميدانية',
  `doc_proving` varchar(300) DEFAULT NULL COMMENT 'المستند المُثبت',
  `basis_pricing` varchar(300) DEFAULT NULL COMMENT 'أساس التسعير',
  `types_equipment_applicable` varchar(300) DEFAULT NULL COMMENT 'أنواع المعدات المنطبقة',
  `unit_meter_equipment` varchar(300) DEFAULT NULL COMMENT 'وحدة عدّاد المعدة',
  `unit_container_supplier` varchar(300) DEFAULT NULL COMMENT 'وحدة حاوية المورد',
  `unit_contracting_supplier` varchar(300) DEFAULT NULL COMMENT 'وحدة تعاقد المورد',
  `basis_due_supplier` varchar(300) DEFAULT NULL COMMENT 'أساس استحقاق المورد',
  `basis_wage_operator` varchar(300) DEFAULT NULL COMMENT 'أساس أجر المشغّل',
  `cycle_closing` varchar(300) DEFAULT NULL COMMENT 'دورة الإقفال',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `date_effective` date DEFAULT NULL COMMENT 'تاريخ السريان',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_business_models_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة business_models.php';

-- ── Table: scr_canonical_names ──
CREATE TABLE `scr_canonical_names` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_log` varchar(300) DEFAULT NULL COMMENT 'رقم السجل',
  `type_entity` varchar(300) DEFAULT NULL COMMENT 'نوع الكيان',
  `name_approved` varchar(300) DEFAULT NULL COMMENT 'الاسم المعتمد',
  `name_legal_full` varchar(300) DEFAULT NULL COMMENT 'الاسم القانوني الكامل',
  `synonyms_registered` varchar(300) DEFAULT NULL COMMENT 'المرادفات المسجَّلة',
  `count_synonyms` varchar(300) DEFAULT NULL COMMENT 'عدد المرادفات',
  `name_in_log_commercial` varchar(300) DEFAULT NULL COMMENT 'الاسم في السجل التجاري',
  `no_tax` varchar(300) DEFAULT NULL COMMENT 'الرقم الضريبي',
  `code_entity_unified` varchar(300) DEFAULT NULL COMMENT 'كود الكيان الموحَّد',
  `state_check` varchar(300) DEFAULT NULL COMMENT 'حالة الفحص',
  `duplicate_detected` varchar(300) DEFAULT NULL COMMENT 'تكرار مكتشف',
  `decision_merge` varchar(300) DEFAULT NULL COMMENT 'قرار الدمج',
  `records_migrated` varchar(300) DEFAULT NULL COMMENT 'السجلات المحوَّلة',
  `date_merge` date DEFAULT NULL COMMENT 'تاريخ الدمج',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_canonical_names_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة canonical_names.php';

-- ── Table: scr_code_bridge ──
CREATE TABLE `scr_code_bridge` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_bridge` varchar(300) DEFAULT NULL COMMENT 'رقم الجسر',
  `code_unified` varchar(300) DEFAULT NULL COMMENT 'الكود الموحَّد',
  `name_equipment` varchar(300) DEFAULT NULL COMMENT 'اسم المعدة',
  `no_plate` varchar(300) DEFAULT NULL COMMENT 'رقم اللوحة',
  `code_old` varchar(300) DEFAULT NULL COMMENT 'الكود القديم',
  `code_supplier` varchar(300) DEFAULT NULL COMMENT 'كود المورد',
  `code_company_manufacturer` varchar(300) DEFAULT NULL COMMENT 'كود الشركة المصنِّعة',
  `no_serial` varchar(300) DEFAULT NULL COMMENT 'الرقم التسلسلي',
  `source_code` varchar(300) DEFAULT NULL COMMENT 'مصدر الكود',
  `state_match` varchar(300) DEFAULT NULL COMMENT 'حالة التطابق',
  `conflict_detected` varchar(300) DEFAULT NULL COMMENT 'تعارض مكتشف',
  `description_conflict` varchar(300) DEFAULT NULL COMMENT 'وصف التعارض',
  `decision_resolution` varchar(300) DEFAULT NULL COMMENT 'قرار الفض',
  `date_link` date DEFAULT NULL COMMENT 'تاريخ الربط',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_code_bridge_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة code_bridge.php';

-- ── Table: scr_consumption_rate ──
CREATE TABLE `scr_consumption_rate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_log` varchar(300) DEFAULT NULL COMMENT 'رقم السجل',
  `period_ref` varchar(300) DEFAULT NULL COMMENT 'الفترة',
  `code_equipment` varchar(300) DEFAULT NULL COMMENT 'كود المعدة',
  `type_equipment` varchar(300) DEFAULT NULL COMMENT 'نوع المعدة',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `unit_contractual` varchar(300) DEFAULT NULL COMMENT 'الوحدة التعاقدية',
  `hours_operations` varchar(300) DEFAULT NULL COMMENT 'ساعات التشغيل',
  `category_consumption` varchar(300) DEFAULT NULL COMMENT 'صنف الاستهلاك',
  `qty_disbursed` varchar(300) DEFAULT NULL COMMENT 'الكمية المصروفة',
  `unit_name` varchar(300) DEFAULT NULL COMMENT 'الوحدة',
  `rate_consumption_hour` varchar(300) DEFAULT NULL COMMENT 'معدل الاستهلاك للساعة',
  `rate_reference_model` varchar(300) DEFAULT NULL COMMENT 'المعدل المرجعي للموديل',
  `deviation` varchar(300) DEFAULT NULL COMMENT 'الانحراف',
  `pct_deviation` varchar(300) DEFAULT NULL COMMENT 'نسبة الانحراف',
  `threshold_anomaly` varchar(300) DEFAULT NULL COMMENT 'حد الشذوذ',
  `state_anomaly` varchar(300) DEFAULT NULL COMMENT 'حالة الشذوذ',
  `reason_probable` varchar(300) DEFAULT NULL COMMENT 'السبب المرجَّح',
  `ticket_open` varchar(300) DEFAULT NULL COMMENT 'البلاغ المفتوح',
  `cost_consumption` varchar(300) DEFAULT NULL COMMENT 'تكلفة الاستهلاك',
  `currency` varchar(300) DEFAULT NULL COMMENT 'العملة',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_consumption_rate_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة consumption_rate.php';

-- ── Table: scr_contract_review ──
CREATE TABLE `scr_contract_review` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_note` varchar(300) DEFAULT NULL COMMENT 'رقم الملاحظة',
  `contract_ref` varchar(300) DEFAULT NULL COMMENT 'العقد',
  `date_observation` date DEFAULT NULL COMMENT 'تاريخ الرصد',
  `type_note` varchar(300) DEFAULT NULL COMMENT 'نوع الملاحظة',
  `grade_note` varchar(300) DEFAULT NULL COMMENT 'درجة الملاحظة',
  `line_affected` varchar(300) DEFAULT NULL COMMENT 'البند المتأثر',
  `description_note` varchar(300) DEFAULT NULL COMMENT 'وصف الملاحظة',
  `impact_potential` varchar(300) DEFAULT NULL COMMENT 'الأثر المحتمل',
  `value_exposed` varchar(300) DEFAULT NULL COMMENT 'القيمة المعرَّضة',
  `currency` varchar(300) DEFAULT NULL COMMENT 'العملة',
  `party_observing` varchar(300) DEFAULT NULL COMMENT 'الجهة الراصدة',
  `procedure_required` varchar(300) DEFAULT NULL COMMENT 'الإجراء المطلوب',
  `responsible_name` varchar(300) DEFAULT NULL COMMENT 'المسؤول',
  `deadline_handling` varchar(300) DEFAULT NULL COMMENT 'مهلة المعالجة',
  `date_handling` date DEFAULT NULL COMMENT 'تاريخ المعالجة',
  `doc_handling` varchar(300) DEFAULT NULL COMMENT 'مستند المعالجة',
  `blocks_approval_flag` varchar(300) DEFAULT NULL COMMENT 'يحجب الاعتماد؟',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `date_closing` date DEFAULT NULL COMMENT 'تاريخ الإقفال',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_contract_review_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة contract_review.php';

-- ── Table: scr_deductions ──
CREATE TABLE `scr_deductions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_decision` varchar(300) DEFAULT NULL COMMENT 'رقم القرار',
  `code_employee` varchar(300) DEFAULT NULL COMMENT 'كود الموظف',
  `month_ref` varchar(300) DEFAULT NULL COMMENT 'الشهر',
  `type_deduction` varchar(300) DEFAULT NULL COMMENT 'نوع الخصم',
  `reason_deduction` varchar(300) DEFAULT NULL COMMENT 'سبب الخصم',
  `line_policy_reference` varchar(300) DEFAULT NULL COMMENT 'بند السياسة المرجعي',
  `doc_supporting` varchar(300) DEFAULT NULL COMMENT 'المستند المؤيد',
  `basis` varchar(300) DEFAULT NULL COMMENT 'الأساس',
  `formula` varchar(300) DEFAULT NULL COMMENT 'المعادلة',
  `value_deduction` varchar(300) DEFAULT NULL COMMENT 'قيمة الخصم',
  `currency` varchar(300) DEFAULT NULL COMMENT 'العملة',
  `pct_of_net` varchar(300) DEFAULT NULL COMMENT 'نسبة من الصافي',
  `proposed_by` varchar(300) DEFAULT NULL COMMENT 'اقترحه',
  `reviewed_by_hr` varchar(300) DEFAULT NULL COMMENT 'راجعته الموارد',
  `approval_dept` varchar(300) DEFAULT NULL COMMENT 'اعتماد الإدارة',
  `approval_financial` varchar(300) DEFAULT NULL COMMENT 'الاعتماد المالي',
  `approval_dept_general` varchar(300) DEFAULT NULL COMMENT 'اعتماد الإدارة العامة',
  `payroll_ref` varchar(300) DEFAULT NULL COMMENT 'المسيّر',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `impact_grade` varchar(300) DEFAULT NULL COMMENT 'درجة الأثر',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `version_rule_used` varchar(300) DEFAULT NULL COMMENT 'نسخة القاعدة المستعملة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `proposal_ref` bigint(20) unsigned DEFAULT NULL COMMENT 'مقترحُ الخصم — لا خصمَ معتمدًا بلا مقترحه (deduction_proposals.ded_id)',
  `approval_request_ref` int(11) DEFAULT NULL COMMENT 'طلبُ سلّمِ الموافقاتِ المكتمل (approval_requests.id)',
  `approved_by` int(11) DEFAULT NULL COMMENT 'المعتمِدُ — يدٌ ثانيةٌ تخالف المنشئ (users.id)',
  `approved_at` datetime DEFAULT NULL COMMENT 'لحظةُ الاعتماد — يكتبها منفّذُ السلّم لا الشاشة',
  PRIMARY KEY (`id`),
  KEY `ix_deductions_live` (`company_id`,`status`),
  KEY `idx_scr_ded_proposal` (`proposal_ref`),
  KEY `idx_scr_ded_request` (`approval_request_ref`),
  CONSTRAINT `fk_scr_ded_proposal` FOREIGN KEY (`proposal_ref`) REFERENCES `deduction_proposals` (`ded_id`),
  CONSTRAINT `fk_scr_ded_request` FOREIGN KEY (`approval_request_ref`) REFERENCES `approval_requests` (`id`),
  CONSTRAINT `chk_scr_ded_approved_evidence` CHECK (`is_seed` = 1 or `status`  not like '%معتمد%' or `proposal_ref` is not null and `approval_request_ref` is not null and `approved_by` is not null and `created_by` is not null and `created_by` > 0 and `approved_by` <> `created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة deductions.php';

-- ── Table: scr_doc_types ──
CREATE TABLE `scr_doc_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `code_type` varchar(300) DEFAULT NULL COMMENT 'كود النوع',
  `name_doc` varchar(300) DEFAULT NULL COMMENT 'اسم المستند',
  `dept_owning` varchar(300) DEFAULT NULL COMMENT 'الإدارة المالكة',
  `pattern_numbering` varchar(300) DEFAULT NULL COMMENT 'نمط الترقيم',
  `prefix_numbering` varchar(300) DEFAULT NULL COMMENT 'بادئة الترقيم',
  `periodicity_sequence` varchar(300) DEFAULT NULL COMMENT 'دورية التسلسل',
  `machine_state_linked` varchar(300) DEFAULT NULL COMMENT 'آلة الحالة المرتبطة',
  `needs_approval_flag` varchar(300) DEFAULT NULL COMMENT 'يحتاج اعتمادًا؟',
  `count_loops_approval` varchar(300) DEFAULT NULL COMMENT 'عدد حلقات الاعتماد',
  `has_fin_impact_flag` varchar(300) DEFAULT NULL COMMENT 'له أثر مالي؟',
  `reversible_flag` varchar(300) DEFAULT NULL COMMENT 'قابل للعكس؟',
  `pattern_reversal` varchar(300) DEFAULT NULL COMMENT 'نمط العكس',
  `duration_retention_statutory` varchar(300) DEFAULT NULL COMMENT 'مدة الحفظ النظامية',
  `policy_archiving` varchar(300) DEFAULT NULL COMMENT 'سياسة الأرشفة',
  `date_effective` date DEFAULT NULL COMMENT 'تاريخ السريان',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_doc_types_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة doc_types.php';

-- ── Table: scr_equipment_quota ──
CREATE TABLE `scr_equipment_quota` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_container` varchar(300) DEFAULT NULL COMMENT 'رقم الحاوية',
  `supplier_name` varchar(300) DEFAULT NULL COMMENT 'المورد',
  `contract_client` varchar(300) DEFAULT NULL COMMENT 'العقد العميل',
  `contract_supplier` varchar(300) DEFAULT NULL COMMENT 'عقد المورد',
  `model_work` varchar(300) DEFAULT NULL COMMENT 'نموذج العمل',
  `unit_work` varchar(300) DEFAULT NULL COMMENT 'وحدة العمل',
  `type_equipment` varchar(300) DEFAULT NULL COMMENT 'نوع المعدة',
  `code_equipment` varchar(300) DEFAULT NULL COMMENT 'كود المعدة',
  `role_equipment` varchar(300) DEFAULT NULL COMMENT 'دور المعدة',
  `share_supplier_total` varchar(300) DEFAULT NULL COMMENT 'حصة المورد الكلية',
  `share_allocated_equipment` varchar(300) DEFAULT NULL COMMENT 'الحصة المخصَّصة للمعدة',
  `count_shifts` varchar(300) DEFAULT NULL COMMENT 'عدد الورديات',
  `units_shift` varchar(300) DEFAULT NULL COMMENT 'وحدات الوردية',
  `units_monthly_equipment` varchar(300) DEFAULT NULL COMMENT 'الوحدات الشهرية للمعدة',
  `total_shares_equipment_supplier` varchar(300) DEFAULT NULL COMMENT 'مجموع حصص معدات المورد',
  `supplier_share_remaining` varchar(300) DEFAULT NULL COMMENT 'المتبقي من حصة المورد',
  `executed_actual` varchar(300) DEFAULT NULL COMMENT 'المنفَّذ فعليًّا',
  `variance` varchar(300) DEFAULT NULL COMMENT 'الفارق',
  `reason_variance` varchar(300) DEFAULT NULL COMMENT 'سبب الفارق',
  `date_effective` date DEFAULT NULL COMMENT 'تاريخ السريان',
  `date_expiry` date DEFAULT NULL COMMENT 'تاريخ الانتهاء',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_equipment_quota_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة equipment_quota.php';

-- ── Table: scr_equipment_sourcing ──
CREATE TABLE `scr_equipment_sourcing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `code_equipment` varchar(300) DEFAULT NULL COMMENT 'كود المعدة',
  `pattern_source` varchar(300) DEFAULT NULL COMMENT 'نمط المصدر',
  `supplier_or_financier` varchar(300) DEFAULT NULL COMMENT 'المورد أو الممول',
  `model_financing` varchar(300) DEFAULT NULL COMMENT 'نموذج التمويل',
  `operation_financing` varchar(300) DEFAULT NULL COMMENT 'عملية التمويل',
  `date_entry` date DEFAULT NULL COMMENT 'تاريخ الدخول',
  `date_transfer_ownership_expected` date DEFAULT NULL COMMENT 'تاريخ نقل الملكية المتوقع',
  `owner_legal_current` varchar(300) DEFAULT NULL COMMENT 'المالك القانوني الحالي',
  `beneficiary_economic` varchar(300) DEFAULT NULL COMMENT 'المنتفع الاقتصادي',
  `holder_depreciation` varchar(300) DEFAULT NULL COMMENT 'حامل الإهلاك',
  `holder_maintenance` varchar(300) DEFAULT NULL COMMENT 'حامل الصيانة',
  `holder_insurance` varchar(300) DEFAULT NULL COMMENT 'حامل التأمين',
  `pledgee_warranty` varchar(300) DEFAULT NULL COMMENT 'مرتهن الضمان',
  `value_asset` varchar(300) DEFAULT NULL COMMENT 'قيمة الأصل',
  `currency` varchar(300) DEFAULT NULL COMMENT 'العملة',
  `commitment_outstanding` varchar(300) DEFAULT NULL COMMENT 'الالتزام القائم',
  `handling_accounting` varchar(300) DEFAULT NULL COMMENT 'المعالجة المحاسبية',
  `grade_confidentiality` varchar(300) DEFAULT NULL COMMENT 'درجة السرية',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_equipment_sourcing_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة equipment_sourcing.php';

-- ── Table: scr_exceptions ──
CREATE TABLE `scr_exceptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_request` varchar(300) DEFAULT NULL COMMENT 'رقم الطلب',
  `date_request` date DEFAULT NULL COMMENT 'تاريخ الطلب',
  `protection_exempted` varchar(300) DEFAULT NULL COMMENT 'الحماية المستثناة',
  `dept_requesting` varchar(300) DEFAULT NULL COMMENT 'الإدارة الطالبة',
  `reason_exception` varchar(300) DEFAULT NULL COMMENT 'سبب الاستثناء',
  `docs_supporting` varchar(300) DEFAULT NULL COMMENT 'المستندات المؤيدة',
  `grade_severity` varchar(300) DEFAULT NULL COMMENT 'درجة الخطورة',
  `impact_expected` varchar(300) DEFAULT NULL COMMENT 'الأثر المتوقع',
  `scope_ref` varchar(300) DEFAULT NULL COMMENT 'النطاق',
  `period_from` varchar(300) DEFAULT NULL COMMENT 'المدة من',
  `period_to` varchar(300) DEFAULT NULL COMMENT 'المدة إلى',
  `approvals_required` varchar(300) DEFAULT NULL COMMENT 'الموافقات المطلوبة',
  `approvers` varchar(300) DEFAULT NULL COMMENT 'الموافقون',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `count_times_usage` varchar(300) DEFAULT NULL COMMENT 'عدد مرات الاستعمال',
  `date_expiry` date DEFAULT NULL COMMENT 'تاريخ الانتهاء',
  `decision_closing` varchar(300) DEFAULT NULL COMMENT 'قرار الإقفال',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_exceptions_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة exceptions.php';

-- ── Table: scr_fin_assets ──
CREATE TABLE `scr_fin_assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_log` varchar(300) DEFAULT NULL COMMENT 'رقم السجل',
  `code_asset_item` varchar(300) DEFAULT NULL COMMENT 'كود العين',
  `type_asset` varchar(300) DEFAULT NULL COMMENT 'نوع الأصل',
  `operation_financing` varchar(300) DEFAULT NULL COMMENT 'عملية التمويل',
  `financier_name` varchar(300) DEFAULT NULL COMMENT 'الممول',
  `model_financing` varchar(300) DEFAULT NULL COMMENT 'نموذج التمويل',
  `value_purchase` varchar(300) DEFAULT NULL COMMENT 'قيمة الشراء',
  `capital_capital_financier` varchar(300) DEFAULT NULL COMMENT 'رأس المال المموَّل',
  `date_link` date DEFAULT NULL COMMENT 'تاريخ الربط',
  `date_unlink_link` date DEFAULT NULL COMMENT 'تاريخ فك الربط',
  `in_active_fleet` varchar(300) DEFAULT NULL COMMENT 'في الأسطول المشغَّل؟',
  `holder_depreciation` varchar(300) DEFAULT NULL COMMENT 'حامل الإهلاك',
  `pledgee_warranty` varchar(300) DEFAULT NULL COMMENT 'مرتهن الضمان',
  `grade_confidentiality` varchar(300) DEFAULT NULL COMMENT 'درجة السرية',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_assets_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة fin_assets.php';

-- ── Table: scr_fin_changes ──
CREATE TABLE `scr_fin_changes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_minutes_doc` varchar(300) DEFAULT NULL COMMENT 'رقم المحضر',
  `operation_financing` varchar(300) DEFAULT NULL COMMENT 'عملية التمويل',
  `date_change` date DEFAULT NULL COMMENT 'تاريخ التغيير',
  `type_change` varchar(300) DEFAULT NULL COMMENT 'نوع التغيير',
  `formula_before` varchar(300) DEFAULT NULL COMMENT 'الصيغة قبل',
  `formula_after` varchar(300) DEFAULT NULL COMMENT 'الصيغة بعد',
  `capital_before` varchar(300) DEFAULT NULL COMMENT 'رأس المال قبل',
  `capital_after` varchar(300) DEFAULT NULL COMMENT 'رأس المال بعد',
  `count_installments_before` varchar(300) DEFAULT NULL COMMENT 'عدد الأقساط قبل',
  `count_installments_after` varchar(300) DEFAULT NULL COMMENT 'عدد الأقساط بعد',
  `value_installment_before` varchar(300) DEFAULT NULL COMMENT 'قيمة القسط قبل',
  `value_installment_after` varchar(300) DEFAULT NULL COMMENT 'قيمة القسط بعد',
  `reason_change` varchar(300) DEFAULT NULL COMMENT 'سبب التغيير',
  `doc_change` varchar(300) DEFAULT NULL COMMENT 'مستند التغيير',
  `approved_by` varchar(300) DEFAULT NULL COMMENT 'اعتمده',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_changes_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة fin_changes.php';

-- ── Table: scr_fin_models ──
CREATE TABLE `scr_fin_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `code_model` varchar(300) DEFAULT NULL COMMENT 'رمز النموذج',
  `name_model` varchar(300) DEFAULT NULL COMMENT 'اسم النموذج',
  `owner_legal` varchar(300) DEFAULT NULL COMMENT 'المالك القانوني',
  `beneficiary_economic` varchar(300) DEFAULT NULL COMMENT 'المنتفع الاقتصادي',
  `recognition_accounting` varchar(300) DEFAULT NULL COMMENT 'الاعتراف المحاسبي',
  `holder_depreciation` varchar(300) DEFAULT NULL COMMENT 'حامل الإهلاك',
  `pledgee_warranty` varchar(300) DEFAULT NULL COMMENT 'مرتهن الضمان',
  `handling_commitment` varchar(300) DEFAULT NULL COMMENT 'معالجة الالتزام',
  `handling_return` varchar(300) DEFAULT NULL COMMENT 'معالجة العائد',
  `ref_accounting` varchar(300) DEFAULT NULL COMMENT 'المرجع المحاسبي',
  `approved_by_auditor` varchar(300) DEFAULT NULL COMMENT 'اعتمده المراجع',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_fin_models_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة fin_models.php';

-- ── Table: scr_founding_mode ──
CREATE TABLE `scr_founding_mode` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_mode` varchar(300) DEFAULT NULL COMMENT 'رقم الوضع',
  `date_open` date DEFAULT NULL COMMENT 'تاريخ الفتح',
  `reason_open` varchar(300) DEFAULT NULL COMMENT 'سبب الفتح',
  `scope_allowed` varchar(300) DEFAULT NULL COMMENT 'النطاق المسموح',
  `tables_affected` varchar(300) DEFAULT NULL COMMENT 'الجداول المتأثرة',
  `duration_authorized` varchar(300) DEFAULT NULL COMMENT 'المدة المصرَّح بها',
  `date_close_planned` date DEFAULT NULL COMMENT 'تاريخ الإغلاق المخطط',
  `date_close_actual` date DEFAULT NULL COMMENT 'تاريخ الإغلاق الفعلي',
  `count_records_entered` varchar(300) DEFAULT NULL COMMENT 'عدد السجلات المُدخَلة',
  `tag_records` varchar(300) DEFAULT NULL COMMENT 'وسم السجلات',
  `entered_by_list` varchar(300) DEFAULT NULL COMMENT 'المُدخِلون',
  `open_approver` varchar(300) DEFAULT NULL COMMENT 'الموافق على الفتح',
  `close_approver` varchar(300) DEFAULT NULL COMMENT 'الموافق على الإغلاق',
  `report_review_after_close` varchar(300) DEFAULT NULL COMMENT 'تقرير المراجعة بعد الإغلاق',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_founding_mode_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة founding_mode.php';

-- ── Table: scr_guards ──
CREATE TABLE `scr_guards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `code_guard` varchar(300) DEFAULT NULL COMMENT 'رمز الحارس',
  `name_protection` varchar(300) DEFAULT NULL COMMENT 'اسم الحماية',
  `category` varchar(300) DEFAULT NULL COMMENT 'الصنف',
  `reason_classification` varchar(300) DEFAULT NULL COMMENT 'سبب التصنيف',
  `screens_affected` varchar(300) DEFAULT NULL COMMENT 'الشاشات المتأثرة',
  `actions_denied` varchar(300) DEFAULT NULL COMMENT 'الأفعال الممنوعة',
  `message_denial` varchar(300) DEFAULT NULL COMMENT 'رسالة المنع',
  `grade_severity` varchar(300) DEFAULT NULL COMMENT 'درجة الخطورة',
  `approvals_required_exception` varchar(300) DEFAULT NULL COMMENT 'الموافقات المطلوبة للاستثناء',
  `state_flag` varchar(300) DEFAULT NULL COMMENT 'حالة العلَم',
  `date_flip_flag` date DEFAULT NULL COMMENT 'تاريخ قلب العلَم',
  `classified_by` varchar(300) DEFAULT NULL COMMENT 'صنّفها',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_guards_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة guards.php';

-- ── Table: scr_monthly_close ──
CREATE TABLE `scr_monthly_close` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_minutes_doc` varchar(300) DEFAULT NULL COMMENT 'رقم المحضر',
  `month_ref` varchar(300) DEFAULT NULL COMMENT 'الشهر',
  `contract_ref` varchar(300) DEFAULT NULL COMMENT 'العقد',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `unit_contractual` varchar(300) DEFAULT NULL COMMENT 'الوحدة التعاقدية',
  `hours_contractual` varchar(300) DEFAULT NULL COMMENT 'الساعات التعاقدية',
  `hours_per_our_log` varchar(300) DEFAULT NULL COMMENT 'الساعات حسب سجلنا',
  `hours_client_approved` varchar(300) DEFAULT NULL COMMENT 'الساعات المعتمدة من العميل',
  `variance` varchar(300) DEFAULT NULL COMMENT 'الفرق',
  `reason_variance` varchar(300) DEFAULT NULL COMMENT 'سبب الفرق',
  `qty_executed` varchar(300) DEFAULT NULL COMMENT 'الكمية المنفَّذة',
  `qty_approved` varchar(300) DEFAULT NULL COMMENT 'الكمية المعتمدة',
  `value_due` varchar(300) DEFAULT NULL COMMENT 'قيمة الاستحقاق',
  `currency` varchar(300) DEFAULT NULL COMMENT 'العملة',
  `prepared_by` varchar(300) DEFAULT NULL COMMENT 'أعدّه',
  `approved_by_operations` varchar(300) DEFAULT NULL COMMENT 'اعتمده التشغيل',
  `approved_by_finance` varchar(300) DEFAULT NULL COMMENT 'اعتمدته المالية',
  `date_closing` date DEFAULT NULL COMMENT 'تاريخ الإقفال',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `impact_grade` varchar(300) DEFAULT NULL COMMENT 'درجة الأثر',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_monthly_close_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة monthly_close.php';

-- ── Table: scr_op_codes ──
CREATE TABLE `scr_op_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `item_no` varchar(300) DEFAULT NULL COMMENT 'الرقم',
  `state_no` varchar(300) DEFAULT NULL COMMENT 'حالة الرقم',
  `operator_previous` varchar(300) DEFAULT NULL COMMENT 'المشغّل السابق',
  `date_vacate` date DEFAULT NULL COMMENT 'تاريخ الإخلاء',
  `reason_vacate` varchar(300) DEFAULT NULL COMMENT 'سبب الإخلاء',
  `duration_vacancy_days` varchar(300) DEFAULT NULL COMMENT 'مدة الشغور بالأيام',
  `operator_new` varchar(300) DEFAULT NULL COMMENT 'المشغّل الجديد',
  `date_allocation_new` date DEFAULT NULL COMMENT 'تاريخ التخصيص الجديد',
  `decision_dept` varchar(300) DEFAULT NULL COMMENT 'قرار الإدارة',
  `allocated_by` varchar(300) DEFAULT NULL COMMENT 'خصّصه',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_op_codes_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة op_codes.php';

-- ── Table: scr_op_monthly ──
CREATE TABLE `scr_op_monthly` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `month_ref` varchar(300) DEFAULT NULL COMMENT 'الشهر',
  `code_operator` varchar(300) DEFAULT NULL COMMENT 'كود المشغّل',
  `equipment_name` varchar(300) DEFAULT NULL COMMENT 'المعدة',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `days_work` varchar(300) DEFAULT NULL COMMENT 'أيام العمل',
  `days_leave` varchar(300) DEFAULT NULL COMMENT 'أيام الإجازة',
  `days_absence` varchar(300) DEFAULT NULL COMMENT 'أيام الغياب',
  `hours_operations` varchar(300) DEFAULT NULL COMMENT 'ساعات التشغيل',
  `hours_overtime` varchar(300) DEFAULT NULL COMMENT 'ساعات إضافية',
  `hours_standby` varchar(300) DEFAULT NULL COMMENT 'ساعات الاستعداد',
  `production_attributed` varchar(300) DEFAULT NULL COMMENT 'الإنتاج المنسوب',
  `pct_achievement` varchar(300) DEFAULT NULL COMMENT 'نسبة الإنجاز',
  `basis_incentive` varchar(300) DEFAULT NULL COMMENT 'أساس الحافز',
  `value_incentive` varchar(300) DEFAULT NULL COMMENT 'قيمة الحافز',
  `currency` varchar(300) DEFAULT NULL COMMENT 'العملة',
  `approved_by` varchar(300) DEFAULT NULL COMMENT 'اعتمده',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `impact_grade` varchar(300) DEFAULT NULL COMMENT 'درجة الأثر',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `version_rule_used` varchar(300) DEFAULT NULL COMMENT 'نسخة القاعدة المستعملة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_op_monthly_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة op_monthly.php';

-- ── Table: scr_op_qual ──
CREATE TABLE `scr_op_qual` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_qualification` varchar(300) DEFAULT NULL COMMENT 'رقم التأهيل',
  `code_operator` varchar(300) DEFAULT NULL COMMENT 'كود المشغّل',
  `type_equipment` varchar(300) DEFAULT NULL COMMENT 'نوع المعدة',
  `model_ref` varchar(300) DEFAULT NULL COMMENT 'الموديل',
  `level_qualification` varchar(300) DEFAULT NULL COMMENT 'مستوى التأهيل',
  `party_qualification` varchar(300) DEFAULT NULL COMMENT 'جهة التأهيل',
  `no_certificate` varchar(300) DEFAULT NULL COMMENT 'رقم الشهادة',
  `date_release` date DEFAULT NULL COMMENT 'تاريخ الإصدار',
  `date_expiry` date DEFAULT NULL COMMENT 'تاريخ الانتهاء',
  `hours_experience_on_type` varchar(300) DEFAULT NULL COMMENT 'ساعات الخبرة على النوع',
  `assessor_name` varchar(300) DEFAULT NULL COMMENT 'المقيِّم',
  `date_last_evaluation` date DEFAULT NULL COMMENT 'تاريخ آخر تقييم',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_op_qual_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة op_qual.php';

-- ── Table: scr_ownership_links ──
CREATE TABLE `scr_ownership_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_relation` varchar(300) DEFAULT NULL COMMENT 'رقم العلاقة',
  `entity_owner` varchar(300) DEFAULT NULL COMMENT 'الكيان المالك',
  `type_owner` varchar(300) DEFAULT NULL COMMENT 'نوع المالك',
  `entity_owned` varchar(300) DEFAULT NULL COMMENT 'الكيان المملوك',
  `type_ownership` varchar(300) DEFAULT NULL COMMENT 'نوع الملكية',
  `pct` varchar(300) DEFAULT NULL COMMENT 'النسبة',
  `date_from` date DEFAULT NULL COMMENT 'من تاريخ',
  `date_to` date DEFAULT NULL COMMENT 'إلى تاريخ',
  `date_exit` date DEFAULT NULL COMMENT 'تاريخ التخارج',
  `owner_buyer` varchar(300) DEFAULT NULL COMMENT 'المالك المشتري',
  `doc_exit` varchar(300) DEFAULT NULL COMMENT 'مستند التخارج',
  `doc_ownership` varchar(300) DEFAULT NULL COMMENT 'مستند الملكية',
  `completeness_ownership` varchar(300) DEFAULT NULL COMMENT 'اكتمال الملكية',
  `active_pct_total` varchar(300) DEFAULT NULL COMMENT 'مجموع النسب النشطة',
  `sum100_state` varchar(300) DEFAULT NULL COMMENT 'حالة قيد المئة',
  `conflict_found_flag` varchar(300) DEFAULT NULL COMMENT 'تضارب مصالح مكتشف؟',
  `recorded_by` varchar(300) DEFAULT NULL COMMENT 'سجّلها',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_ownership_links_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة ownership_links.php';

-- ── Table: scr_perm_explain ──
CREATE TABLE `scr_perm_explain` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `account_ref` varchar(300) DEFAULT NULL COMMENT 'الحساب',
  `screen_ref` varchar(300) DEFAULT NULL COMMENT 'الشاشة',
  `action_ref` varchar(300) DEFAULT NULL COMMENT 'الفعل',
  `result_final` varchar(300) DEFAULT NULL COMMENT 'النتيجة النهائية',
  `grant_source_1` varchar(300) DEFAULT NULL COMMENT 'مصدر المنح 1',
  `its_ruling` varchar(300) DEFAULT NULL COMMENT 'حكمه',
  `grant_source_2` varchar(300) DEFAULT NULL COMMENT 'مصدر المنح 2',
  `its_ruling_2` varchar(300) DEFAULT NULL COMMENT 'حكمه',
  `source_denial` varchar(300) DEFAULT NULL COMMENT 'مصدر المنع',
  `its_ruling_3` varchar(300) DEFAULT NULL COMMENT 'حكمه',
  `rule_merge_applied` varchar(300) DEFAULT NULL COMMENT 'قاعدة الدمج المطبَّقة',
  `scope_resulting` varchar(300) DEFAULT NULL COMMENT 'النطاق الناتج',
  `cap_resulting` varchar(300) DEFAULT NULL COMMENT 'السقف الناتج',
  `date_check` date DEFAULT NULL COMMENT 'تاريخ الفحص',
  `inspector_name` varchar(300) DEFAULT NULL COMMENT 'الفاحص',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_perm_explain_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة perm_explain.php';

-- ── Table: scr_portal_users ──
CREATE TABLE `scr_portal_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `code_account` varchar(300) DEFAULT NULL COMMENT 'كود الحساب',
  `type_party` varchar(300) DEFAULT NULL COMMENT 'نوع الطرف',
  `person_name` varchar(300) DEFAULT NULL COMMENT 'الشخص',
  `capacity_role_at_entity` varchar(300) DEFAULT NULL COMMENT 'الصفة لدى الكيان',
  `scope_visibility` varchar(300) DEFAULT NULL COMMENT 'نطاق الرؤية',
  `actions_allowed` varchar(300) DEFAULT NULL COMMENT 'الأفعال المسموحة',
  `actions_blocked` varchar(300) DEFAULT NULL COMMENT 'الأفعال المحجوبة',
  `doc_authorization` varchar(300) DEFAULT NULL COMMENT 'مستند التخويل',
  `last_entry` varchar(300) DEFAULT NULL COMMENT 'آخر دخول',
  `date_expiry` date DEFAULT NULL COMMENT 'تاريخ الانتهاء',
  `created_by_f` varchar(300) DEFAULT NULL COMMENT 'أنشأه',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_portal_users_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة portal_users.php';

-- ── Table: scr_production ──
CREATE TABLE `scr_production` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_log` varchar(300) DEFAULT NULL COMMENT 'رقم السجل',
  `entry_date` varchar(300) DEFAULT NULL COMMENT 'التاريخ',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `contract_ref` varchar(300) DEFAULT NULL COMMENT 'العقد',
  `line_contract` varchar(300) DEFAULT NULL COMMENT 'بند العقد',
  `type_production` varchar(300) DEFAULT NULL COMMENT 'نوع الإنتاج',
  `front_work` varchar(300) DEFAULT NULL COMMENT 'جبهة العمل',
  `point_unloading` varchar(300) DEFAULT NULL COMMENT 'نقطة التفريغ',
  `method_measure` varchar(300) DEFAULT NULL COMMENT 'طريقة القياس',
  `qty_per_our_log` varchar(300) DEFAULT NULL COMMENT 'الكمية حسب سجلنا',
  `unit_measure` varchar(300) DEFAULT NULL COMMENT 'وحدة القياس',
  `qty_client_approved` varchar(300) DEFAULT NULL COMMENT 'الكمية المعتمدة من العميل',
  `variance` varchar(300) DEFAULT NULL COMMENT 'الفرق',
  `reason_variance` varchar(300) DEFAULT NULL COMMENT 'سبب الفرق',
  `attached_doc` varchar(300) DEFAULT NULL COMMENT 'المستند المرفق',
  `entered_by` varchar(300) DEFAULT NULL COMMENT 'المُدخِل',
  `approved_client` varchar(300) DEFAULT NULL COMMENT 'معتمد العميل',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `impact_grade` varchar(300) DEFAULT NULL COMMENT 'درجة الأثر',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_production_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة production.php';

-- ── Table: scr_project_contracts ──
CREATE TABLE `scr_project_contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_contract` varchar(300) DEFAULT NULL COMMENT 'رقم العقد',
  `category_contract` varchar(300) DEFAULT NULL COMMENT 'فئة العقد',
  `contracted_with` varchar(300) DEFAULT NULL COMMENT 'المتعاقَد معه',
  `affiliation` varchar(300) DEFAULT NULL COMMENT 'التبعية',
  `supplier_linked` varchar(300) DEFAULT NULL COMMENT 'المورد المرتبط',
  `contract_supplier` varchar(300) DEFAULT NULL COMMENT 'عقد المورد',
  `project_name` varchar(300) DEFAULT NULL COMMENT 'المشروع',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `position_title` varchar(300) DEFAULT NULL COMMENT 'المسمى',
  `model_wage` varchar(300) DEFAULT NULL COMMENT 'نموذج الأجر',
  `wage_monthly` varchar(300) DEFAULT NULL COMMENT 'الأجر الشهري',
  `currency` varchar(300) DEFAULT NULL COMMENT 'العملة',
  `date_start` date DEFAULT NULL COMMENT 'تاريخ البدء',
  `date_expiry_planned` date DEFAULT NULL COMMENT 'تاريخ الانتهاء المخطط',
  `end_trigger_1` varchar(300) DEFAULT NULL COMMENT 'محفّز الانتهاء 1',
  `end_trigger_2` varchar(300) DEFAULT NULL COMMENT 'محفّز الانتهاء 2',
  `end_trigger_3` varchar(300) DEFAULT NULL COMMENT 'محفّز الانتهاء 3',
  `end_trigger_hit` varchar(300) DEFAULT NULL COMMENT 'المحفّز الواقع',
  `date_expiry_actual` date DEFAULT NULL COMMENT 'تاريخ الانتهاء الفعلي',
  `expiry_alert_lead` varchar(300) DEFAULT NULL COMMENT 'مهلة التنبيه قبل الانتهاء',
  `state_liquidation` varchar(300) DEFAULT NULL COMMENT 'حالة التصفية',
  `permit_exit` varchar(300) DEFAULT NULL COMMENT 'إذن الخروج',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_project_contracts_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة project_contracts.php';

-- ── Table: scr_release_stamp ──
CREATE TABLE `scr_release_stamp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_release` varchar(300) DEFAULT NULL COMMENT 'رقم الإصدار',
  `fingerprint_release` varchar(300) DEFAULT NULL COMMENT 'بصمة الإصدار',
  `date_publish` date DEFAULT NULL COMMENT 'تاريخ النشر',
  `type_release` varchar(300) DEFAULT NULL COMMENT 'نوع الإصدار',
  `screens_added` varchar(300) DEFAULT NULL COMMENT 'الشاشات المضافة',
  `screens_modified` varchar(300) DEFAULT NULL COMMENT 'الشاشات المعدَّلة',
  `columns_added` varchar(300) DEFAULT NULL COMMENT 'الأعمدة المضافة',
  `actions_added` varchar(300) DEFAULT NULL COMMENT 'الأفعال المضافة',
  `rules_changed` varchar(300) DEFAULT NULL COMMENT 'القواعد المتغيرة',
  `migrations_executed` varchar(300) DEFAULT NULL COMMENT 'الهجرات المنفَّذة',
  `report_completeness` varchar(300) DEFAULT NULL COMMENT 'تقرير الاكتمال',
  `tests_passed` varchar(300) DEFAULT NULL COMMENT 'الاختبارات المجتازة',
  `tests_failed` varchar(300) DEFAULT NULL COMMENT 'الاختبارات الراسبة',
  `flag_rollback` varchar(300) DEFAULT NULL COMMENT 'علَم الرجوع',
  `publisher_name_capacity_role` varchar(300) DEFAULT NULL COMMENT 'الناشر — الاسم والصفة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_release_stamp_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة release_stamp.php';

-- ── Table: scr_rotation ──
CREATE TABLE `scr_rotation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_cycle` varchar(300) DEFAULT NULL COMMENT 'رقم الدورة',
  `code_operator` varchar(300) DEFAULT NULL COMMENT 'كود المشغّل',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `equipment_name` varchar(300) DEFAULT NULL COMMENT 'المعدة',
  `pattern_rotation` varchar(300) DEFAULT NULL COMMENT 'نمط التناوب',
  `type_leave` varchar(300) DEFAULT NULL COMMENT 'نوع الإجازة',
  `date_entry` date DEFAULT NULL COMMENT 'تاريخ الدخول',
  `date_exit` date DEFAULT NULL COMMENT 'تاريخ الخروج',
  `days_work` varchar(300) DEFAULT NULL COMMENT 'أيام العمل',
  `days_leave` varchar(300) DEFAULT NULL COMMENT 'أيام الإجازة',
  `rotator_swapped` varchar(300) DEFAULT NULL COMMENT 'المناوب المتبادل',
  `state_swap` varchar(300) DEFAULT NULL COMMENT 'حالة التبادل',
  `date_swap_rotator` date DEFAULT NULL COMMENT 'تاريخ تبادل المناوب',
  `fallback_substitute` varchar(300) DEFAULT NULL COMMENT 'البديل عند تعذّر التبادل',
  `trip_entry` varchar(300) DEFAULT NULL COMMENT 'رحلة الدخول',
  `trip_exit` varchar(300) DEFAULT NULL COMMENT 'رحلة الخروج',
  `balance_leave_before` varchar(300) DEFAULT NULL COMMENT 'رصيد الإجازة قبل',
  `balance_leave_after` varchar(300) DEFAULT NULL COMMENT 'رصيد الإجازة بعد',
  `scheduled_by` varchar(300) DEFAULT NULL COMMENT 'جدولها',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_rotation_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة rotation.php';

-- ── Table: scr_sensitive_fields ──
CREATE TABLE `scr_sensitive_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_policy` varchar(300) DEFAULT NULL COMMENT 'رقم السياسة',
  `table_name` varchar(300) DEFAULT NULL COMMENT 'الجدول',
  `field_name` varchar(300) DEFAULT NULL COMMENT 'الحقل',
  `classification_sensitivity` varchar(300) DEFAULT NULL COMMENT 'تصنيف الحساسية',
  `reason_classification` varchar(300) DEFAULT NULL COMMENT 'سبب التصنيف',
  `from_visible_to` varchar(300) DEFAULT NULL COMMENT 'من يراه',
  `policy_masking` varchar(300) DEFAULT NULL COMMENT 'سياسة الإخفاء',
  `log_views_flag` varchar(300) DEFAULT NULL COMMENT 'يُسجَّل الاطّلاع؟',
  `exportable_flag` varchar(300) DEFAULT NULL COMMENT 'يُصدَّر؟',
  `basis_statutory` varchar(300) DEFAULT NULL COMMENT 'الأساس النظامي',
  `date_effective` date DEFAULT NULL COMMENT 'تاريخ السريان',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_sensitive_fields_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة sensitive_fields.php';

-- ── Table: scr_shift_log ──
CREATE TABLE `scr_shift_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_log` varchar(300) DEFAULT NULL COMMENT 'رقم السجل',
  `entry_date` varchar(300) DEFAULT NULL COMMENT 'التاريخ',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `shift_name` varchar(300) DEFAULT NULL COMMENT 'الوردية',
  `time_open` varchar(300) DEFAULT NULL COMMENT 'وقت الفتح',
  `time_closing` varchar(300) DEFAULT NULL COMMENT 'وقت الإقفال',
  `count_equipment` varchar(300) DEFAULT NULL COMMENT 'عدد المعدات',
  `count_operators_present` varchar(300) DEFAULT NULL COMMENT 'عدد المشغّلين الحاضرين',
  `absent_count` varchar(300) DEFAULT NULL COMMENT 'الغياب',
  `reading_meter_at_open` varchar(300) DEFAULT NULL COMMENT 'قراءة العدّاد عند الفتح',
  `reading_meter_at_closing` varchar(300) DEFAULT NULL COMMENT 'قراءة العدّاد عند الإقفال',
  `notes` varchar(300) DEFAULT NULL COMMENT 'الملاحظات',
  `handed_by` varchar(300) DEFAULT NULL COMMENT 'المسلِّم',
  `received_by` varchar(300) DEFAULT NULL COMMENT 'المستلِم',
  `state_log` varchar(300) DEFAULT NULL COMMENT 'حالة السجل',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_shift_log_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة shift_log.php';

-- ── Table: scr_site_gate_equip ──
CREATE TABLE `scr_site_gate_equip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_permit` varchar(300) DEFAULT NULL COMMENT 'رقم الإذن',
  `type_permit` varchar(300) DEFAULT NULL COMMENT 'نوع الإذن',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `code_equipment` varchar(300) DEFAULT NULL COMMENT 'كود المعدة',
  `type_equipment` varchar(300) DEFAULT NULL COMMENT 'نوع المعدة',
  `source_equipment` varchar(300) DEFAULT NULL COMMENT 'مصدر المعدة',
  `party_escort` varchar(300) DEFAULT NULL COMMENT 'الجهة المرافقة',
  `reason_movement` varchar(300) DEFAULT NULL COMMENT 'سبب الحركة',
  `doc_reference` varchar(300) DEFAULT NULL COMMENT 'المستند المرجعي',
  `date_movement_planned` date DEFAULT NULL COMMENT 'تاريخ الحركة المخطط',
  `date_movement_actual` date DEFAULT NULL COMMENT 'تاريخ الحركة الفعلي',
  `reading_meter_at_movement` varchar(300) DEFAULT NULL COMMENT 'قراءة العدّاد عند الحركة',
  `trip_haulage` varchar(300) DEFAULT NULL COMMENT 'رحلة الترحيل',
  `state_readiness` varchar(300) DEFAULT NULL COMMENT 'حالة الجاهزية',
  `state_documents` varchar(300) DEFAULT NULL COMMENT 'حالة الوثائق',
  `approval_manager_site` varchar(300) DEFAULT NULL COMMENT 'اعتماد مدير الموقع',
  `approval_manager_operations` varchar(300) DEFAULT NULL COMMENT 'اعتماد مدير التشغيل',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `equipment_id` int(11) DEFAULT NULL COMMENT 'INJ-0370: مرجعُ المعدةِ من سجلِّ المعدات — لا نصٌّ حر',
  `site_project_id` int(11) DEFAULT NULL COMMENT 'INJ-0370: مرجعُ الموقعِ من سجلِّ المشاريع/المواقع',
  `approved_by_user` int(11) DEFAULT NULL COMMENT 'INJ-0370: هويةُ المعتمِدِ من الحسابِ لا من الكتابة',
  PRIMARY KEY (`id`),
  KEY `ix_site_gate_equip_live` (`company_id`,`status`),
  KEY `ix_sge_eq` (`equipment_id`),
  KEY `ix_sge_site` (`site_project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة site_gate_equip.php';

-- ── Table: scr_site_gate_person ──
CREATE TABLE `scr_site_gate_person` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_permit` varchar(300) DEFAULT NULL COMMENT 'رقم الإذن',
  `type_permit` varchar(300) DEFAULT NULL COMMENT 'نوع الإذن',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `code_operator` varchar(300) DEFAULT NULL COMMENT 'كود المشغّل',
  `name_ar` varchar(300) DEFAULT NULL COMMENT 'الاسم',
  `affiliation` varchar(300) DEFAULT NULL COMMENT 'التبعية',
  `supplier_belongs_has` varchar(300) DEFAULT NULL COMMENT 'المورد التابع له',
  `reason_movement` varchar(300) DEFAULT NULL COMMENT 'سبب الحركة',
  `cycle_rotation` varchar(300) DEFAULT NULL COMMENT 'دورة التناوب',
  `date_start_work` date DEFAULT NULL COMMENT 'تاريخ بداية العمل',
  `date_end_work` date DEFAULT NULL COMMENT 'تاريخ نهاية العمل',
  `trip_entry_or_exit` varchar(300) DEFAULT NULL COMMENT 'رحلة الدخول أو الخروج',
  `housing_allocated` varchar(300) DEFAULT NULL COMMENT 'السكن المخصَّص',
  `state_license` varchar(300) DEFAULT NULL COMMENT 'حالة الرخصة',
  `state_check_medical` varchar(300) DEFAULT NULL COMMENT 'حالة الفحص الطبي',
  `attestation_security` varchar(300) DEFAULT NULL COMMENT 'المصادقة الأمنية',
  `approval_manager_site` varchar(300) DEFAULT NULL COMMENT 'اعتماد مدير الموقع',
  `approval_manager_operations` varchar(300) DEFAULT NULL COMMENT 'اعتماد مدير التشغيل',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_site_gate_person_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة site_gate_person.php';

-- ── Table: scr_site_shift_plan ──
CREATE TABLE `scr_site_shift_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_schedule` varchar(300) DEFAULT NULL COMMENT 'رقم الجدول',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `entry_date` varchar(300) DEFAULT NULL COMMENT 'التاريخ',
  `shift_name` varchar(300) DEFAULT NULL COMMENT 'الوردية',
  `time_from` varchar(300) DEFAULT NULL COMMENT 'من الساعة',
  `time_to` varchar(300) DEFAULT NULL COMMENT 'إلى الساعة',
  `code_equipment` varchar(300) DEFAULT NULL COMMENT 'كود المعدة',
  `type_equipment` varchar(300) DEFAULT NULL COMMENT 'نوع المعدة',
  `operator_assignee` varchar(300) DEFAULT NULL COMMENT 'المشغّل المكلَّف',
  `operator_substitute` varchar(300) DEFAULT NULL COMMENT 'المشغّل البديل',
  `check_qualification` varchar(300) DEFAULT NULL COMMENT 'فحص التأهيل',
  `check_license` varchar(300) DEFAULT NULL COMMENT 'فحص الرخصة',
  `check_readiness` varchar(300) DEFAULT NULL COMMENT 'فحص الجاهزية',
  `front_work` varchar(300) DEFAULT NULL COMMENT 'جبهة العمل',
  `target_production` varchar(300) DEFAULT NULL COMMENT 'الهدف الإنتاجي',
  `window_maintenance` varchar(300) DEFAULT NULL COMMENT 'نافذة الصيانة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_site_shift_plan_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة site_shift_plan.php';

-- ── Table: scr_site_work_calendar ──
CREATE TABLE `scr_site_work_calendar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_schedule` varchar(300) DEFAULT NULL COMMENT 'رقم الجدول',
  `site_name` varchar(300) DEFAULT NULL COMMENT 'الموقع',
  `project_name` varchar(300) DEFAULT NULL COMMENT 'المشروع',
  `contract_ref` varchar(300) DEFAULT NULL COMMENT 'العقد',
  `month_ref` varchar(300) DEFAULT NULL COMMENT 'الشهر',
  `week_ref` varchar(300) DEFAULT NULL COMMENT 'الأسبوع',
  `days_work_planned` varchar(300) DEFAULT NULL COMMENT 'أيام العمل المخططة',
  `days_stoppage_planned` varchar(300) DEFAULT NULL COMMENT 'أيام التوقف المخطط',
  `reason_stoppage` varchar(300) DEFAULT NULL COMMENT 'سبب التوقف',
  `hours_operations_daily` varchar(300) DEFAULT NULL COMMENT 'ساعات التشغيل اليومية',
  `count_shifts` varchar(300) DEFAULT NULL COMMENT 'عدد الورديات',
  `qty_target_monthly` varchar(300) DEFAULT NULL COMMENT 'الكمية المستهدفة الشهرية',
  `qty_target_weekly` varchar(300) DEFAULT NULL COMMENT 'الكمية المستهدفة الأسبوعية',
  `equipment_allocated` varchar(300) DEFAULT NULL COMMENT 'المعدات المخصَّصة',
  `operators_required` varchar(300) DEFAULT NULL COMMENT 'المشغّلون المطلوبون',
  `windows_preventive` varchar(300) DEFAULT NULL COMMENT 'نوافذ الوقائية',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_site_work_calendar_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة site_work_calendar.php';

-- ── Table: scr_state_machines ──
CREATE TABLE `scr_state_machines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `code_machine` varchar(300) DEFAULT NULL COMMENT 'كود الآلة',
  `type_doc` varchar(300) DEFAULT NULL COMMENT 'نوع المستند',
  `no_transition` varchar(300) DEFAULT NULL COMMENT 'رقم الانتقال',
  `from_state` varchar(300) DEFAULT NULL COMMENT 'من حالة',
  `to_state` varchar(300) DEFAULT NULL COMMENT 'إلى حالة',
  `action_triggering` varchar(300) DEFAULT NULL COMMENT 'الفعل المُطلق',
  `code_action` varchar(300) DEFAULT NULL COMMENT 'رمز الفعل',
  `authorized_role` varchar(300) DEFAULT NULL COMMENT 'المخوَّل',
  `condition_pre` varchar(300) DEFAULT NULL COMMENT 'الشرط المسبق',
  `guard_applied` varchar(300) DEFAULT NULL COMMENT 'الحارس المطبَّق',
  `event_published` varchar(300) DEFAULT NULL COMMENT 'الحدث المنشور',
  `reversible_flag` varchar(300) DEFAULT NULL COMMENT 'قابل للعكس؟',
  `action_reversal` varchar(300) DEFAULT NULL COMMENT 'فعل العكس',
  `date_effective` date DEFAULT NULL COMMENT 'تاريخ السريان',
  `version_no` varchar(300) DEFAULT NULL COMMENT 'النسخة',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_state_machines_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة state_machines.php';

-- ── Table: scr_transfer_fleet ──
CREATE TABLE `scr_transfer_fleet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `code_carrier` varchar(300) DEFAULT NULL COMMENT 'كود الناقل',
  `kind` varchar(300) DEFAULT NULL COMMENT 'النوع',
  `description` varchar(300) DEFAULT NULL COMMENT 'الوصف',
  `ownership_kind` varchar(300) DEFAULT NULL COMMENT 'الملكية',
  `owner_name` varchar(300) DEFAULT NULL COMMENT 'المالك',
  `capacity_max` varchar(300) DEFAULT NULL COMMENT 'السعة القصوى',
  `no_plate` varchar(300) DEFAULT NULL COMMENT 'رقم اللوحة',
  `license_ref` varchar(300) DEFAULT NULL COMMENT 'الرخصة',
  `date_expiry_license` date DEFAULT NULL COMMENT 'تاريخ انتهاء الرخصة',
  `insurance_ref` varchar(300) DEFAULT NULL COMMENT 'التأمين',
  `date_expiry_insurance` date DEFAULT NULL COMMENT 'تاريخ انتهاء التأمين',
  `driver_assignee` varchar(300) DEFAULT NULL COMMENT 'السائق المكلَّف',
  `tariff` varchar(300) DEFAULT NULL COMMENT 'التعرفة',
  `currency` varchar(300) DEFAULT NULL COMMENT 'العملة',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_transfer_fleet_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة transfer_fleet.php';

-- ── Table: scr_transfer_permits ──
CREATE TABLE `scr_transfer_permits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_permit` varchar(300) DEFAULT NULL COMMENT 'رقم التصريح',
  `order_haulage` varchar(300) DEFAULT NULL COMMENT 'أمر الترحيل',
  `party_issuing` varchar(300) DEFAULT NULL COMMENT 'الجهة المصدِرة',
  `type_permit` varchar(300) DEFAULT NULL COMMENT 'نوع التصريح',
  `route_authorized` varchar(300) DEFAULT NULL COMMENT 'المسار المصرَّح',
  `load_authorized` varchar(300) DEFAULT NULL COMMENT 'الحمولة المصرَّحة',
  `weight_total` varchar(300) DEFAULT NULL COMMENT 'الوزن الإجمالي',
  `date_release` date DEFAULT NULL COMMENT 'تاريخ الإصدار',
  `date_expiry` date DEFAULT NULL COMMENT 'تاريخ الانتهاء',
  `fees` varchar(300) DEFAULT NULL COMMENT 'الرسوم',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `issued_by` varchar(300) DEFAULT NULL COMMENT 'استخرجه',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_transfer_permits_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة transfer_permits.php';

-- ── Table: scr_unbilled ──
CREATE TABLE `scr_unbilled` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_line` varchar(300) DEFAULT NULL COMMENT 'رقم البند',
  `contract_ref` varchar(300) DEFAULT NULL COMMENT 'العقد',
  `month_ref` varchar(300) DEFAULT NULL COMMENT 'الشهر',
  `unit_name` varchar(300) DEFAULT NULL COMMENT 'الوحدة',
  `description_work` varchar(300) DEFAULT NULL COMMENT 'وصف العمل',
  `qty` varchar(300) DEFAULT NULL COMMENT 'الكمية',
  `amount` varchar(300) DEFAULT NULL COMMENT 'القيمة',
  `currency` varchar(300) DEFAULT NULL COMMENT 'العملة',
  `date_execution` date DEFAULT NULL COMMENT 'تاريخ التنفيذ',
  `reason_retention` varchar(300) DEFAULT NULL COMMENT 'سبب الاحتباس',
  `age_retention_days` varchar(300) DEFAULT NULL COMMENT 'عمر الاحتباس بالأيام',
  `likelihood_approval` varchar(300) DEFAULT NULL COMMENT 'احتمال الاعتماد',
  `procedure_followup` varchar(300) DEFAULT NULL COMMENT 'إجراء المتابعة',
  `date_last_claim` date DEFAULT NULL COMMENT 'تاريخ آخر مطالبة',
  `responsible_name` varchar(300) DEFAULT NULL COMMENT 'المسؤول',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `impact_grade` varchar(300) DEFAULT NULL COMMENT 'درجة الأثر',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_unbilled_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة unbilled.php';

-- ── Table: scr_unit_perf ──
CREATE TABLE `scr_unit_perf` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `month_ref` varchar(300) DEFAULT NULL COMMENT 'الشهر',
  `contract_ref` varchar(300) DEFAULT NULL COMMENT 'العقد',
  `unit_contractual` varchar(300) DEFAULT NULL COMMENT 'الوحدة التعاقدية',
  `code_equipment` varchar(300) DEFAULT NULL COMMENT 'كود المعدة',
  `hours_contractual` varchar(300) DEFAULT NULL COMMENT 'الساعات التعاقدية',
  `hours_operations` varchar(300) DEFAULT NULL COMMENT 'ساعات التشغيل',
  `hours_standby_billable` varchar(300) DEFAULT NULL COMMENT 'ساعات الاستعداد المفوتر',
  `downtime_hours_client` varchar(300) DEFAULT NULL COMMENT 'ساعات التوقف — عميل',
  `downtime_hours_supplier` varchar(300) DEFAULT NULL COMMENT 'ساعات التوقف — مورد',
  `downtime_hours_ours` varchar(300) DEFAULT NULL COMMENT 'ساعات التوقف — نحن',
  `loss_not_executed` varchar(300) DEFAULT NULL COMMENT 'فاقد غير منفَّذ',
  `stoppage_maintenance` varchar(300) DEFAULT NULL COMMENT 'توقف صيانة',
  `stoppage_hr_hr` varchar(300) DEFAULT NULL COMMENT 'توقف موارد بشرية',
  `stoppage_settlements` varchar(300) DEFAULT NULL COMMENT 'توقف تسويات',
  `force_majeure` varchar(300) DEFAULT NULL COMMENT 'قوة قاهرة',
  `maintenance_scheduled` varchar(300) DEFAULT NULL COMMENT 'صيانة مجدولة',
  `total_downtime` varchar(300) DEFAULT NULL COMMENT 'إجمالي التعطل',
  `main_bearing_party` varchar(300) DEFAULT NULL COMMENT 'الطرف المتحمل الأغلب',
  `pct_readiness` varchar(300) DEFAULT NULL COMMENT 'نسبة الجاهزية',
  `contract_shortfall` varchar(300) DEFAULT NULL COMMENT 'العجز عن التعاقدي',
  `line_penalty` varchar(300) DEFAULT NULL COMMENT 'بند الجزاء',
  `value_penalty` varchar(300) DEFAULT NULL COMMENT 'قيمة الجزاء',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `impact_grade` varchar(300) DEFAULT NULL COMMENT 'درجة الأثر',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `version_rule_used` varchar(300) DEFAULT NULL COMMENT 'نسخة القاعدة المستعملة',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_unit_perf_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة unit_perf.php';

-- ── Table: scr_workshop ──
CREATE TABLE `scr_workshop` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'الكيان المالك — EN-03',
  `no_assignment` varchar(300) DEFAULT NULL COMMENT 'رقم التكليف',
  `order_work` varchar(300) DEFAULT NULL COMMENT 'أمر العمل',
  `technician_name` varchar(300) DEFAULT NULL COMMENT 'الفني',
  `job_title` varchar(300) DEFAULT NULL COMMENT 'الوظيفة',
  `specialty` varchar(300) DEFAULT NULL COMMENT 'التخصص',
  `role_in_order` varchar(300) DEFAULT NULL COMMENT 'الدور في الأمر',
  `date_start` date DEFAULT NULL COMMENT 'تاريخ البدء',
  `date_expiry` date DEFAULT NULL COMMENT 'تاريخ الانتهاء',
  `hours_actual` varchar(300) DEFAULT NULL COMMENT 'الساعات الفعلية',
  `cost_hour` varchar(300) DEFAULT NULL COMMENT 'تكلفة الساعة',
  `total_cost` varchar(300) DEFAULT NULL COMMENT 'إجمالي التكلفة',
  `workshop_name` varchar(300) DEFAULT NULL COMMENT 'الورشة',
  `assigned_by` varchar(300) DEFAULT NULL COMMENT 'كلّفه',
  `status_label` varchar(300) DEFAULT NULL COMMENT 'الحالة',
  `approver_name` varchar(300) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approved_date` date DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `authority_ref` varchar(300) DEFAULT NULL COMMENT 'مرجع التفويض',
  `parent_ref` varchar(300) DEFAULT NULL COMMENT 'المرجع الأب',
  `attachment` varchar(300) DEFAULT NULL COMMENT 'المرفق',
  `cost_center` varchar(300) DEFAULT NULL COMMENT 'مركز التكلفة',
  `fx_rate_source` varchar(300) DEFAULT NULL COMMENT 'سعر الصرف ومصدره',
  `status` varchar(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',
  `is_seed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_workshop_live` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة workshop.php';

-- ── Table: screen_about ──
CREATE TABLE `screen_about` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `screen_path` varchar(190) NOT NULL COMMENT 'المسار النسبي للشاشة — مفتاح المطابقة',
  `title_ar` varchar(190) NOT NULL DEFAULT '' COMMENT 'اسم الشاشة كما يُعرَف',
  `description` text NOT NULL COMMENT 'النص التعريفي — فقرة أو فقرتان',
  `source` enum('authored','composed','derived') NOT NULL DEFAULT 'derived' COMMENT 'authored=مكتوب بيد · composed=مركَّب من مصادر النظام · derived=اسمٌ وإدارةٌ فقط',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_screen_about_path` (`screen_path`),
  KEY `ix_screen_about_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تعريفات الشاشات لبطاقة «عن الشاشة» — محتوًى يُحرَّر لا شيفرة';

-- ── Table: screen_view_rows ──
CREATE TABLE `screen_view_rows` (
  `svr_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `screen_name` varchar(120) NOT NULL COMMENT 'الاسمُ المستهدفُ للشاشة (مفتاحُ المصفوفة)',
  `canonical_file` varchar(80) DEFAULT NULL,
  `route` varchar(190) DEFAULT NULL COMMENT 'المسارُ التقنيُّ إن حُسم — والجديدُ ★ قد لا مسارَ له بعد',
  `dept` varchar(80) NOT NULL COMMENT 'الإدارةُ الناظرة',
  `role_id` int(11) DEFAULT NULL COMMENT 'دورُها المالكُ في النظام — يُحلّ من target_dept_role',
  `role_kind` enum('owner','viewer') NOT NULL COMMENT 'مالك/عارض',
  `scope_text` varchar(120) NOT NULL COMMENT 'النطاق: الشركة · نطاقُ الإدارة · موقعُه · مورديه · عقودُه · سجلاتُه',
  `angle` varchar(160) DEFAULT NULL COMMENT 'الزاوية — تحدد الأعمدةَ والفلاتر',
  `columns_text` varchar(255) DEFAULT NULL COMMENT 'الأعمدةُ المعروضةُ لهذا العارض',
  `filters_text` varchar(255) DEFAULT NULL COMMENT 'الفلاترُ الافتراضية',
  `allowed_text` varchar(255) DEFAULT NULL,
  `blocked_text` varchar(255) DEFAULT NULL,
  `nav_group` varchar(80) DEFAULT NULL COMMENT 'المجموعةُ في قائمة هذا الناظر',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`svr_id`),
  UNIQUE KEY `uq_svr_canonical` (`canonical_file`,`dept`),
  KEY `ix_svr_role` (`role_id`,`role_kind`,`active`),
  KEY `ix_svr_route` (`route`),
  KEY `ix_svr_canonical` (`canonical_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NAV-01 v6 §6: صفوفُ العرض — النطاقُ والزاويةُ والأفعالُ معلنةٌ لكل ناظر';

-- ── Table: seat_assignments ──
CREATE TABLE `seat_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `container_id` int(10) unsigned NOT NULL COMMENT 'حاوية المقعد (op_containers.level=معدة بseat_no)',
  `equipment_id` int(10) unsigned NOT NULL COMMENT 'المعدة الفعلية الجالسة في المقعد',
  `date_from` date NOT NULL,
  `date_to` date DEFAULT NULL COMMENT 'NULL = جالسة حتى الآن',
  `replace_reason` varchar(200) DEFAULT NULL COMMENT 'سبب الاستبدال — إلزامي لغير الأول (تحرسه الخدمة)',
  `assignment_role` enum('أساسي','احتياطي','مؤقت') NOT NULL DEFAULT 'أساسي' COMMENT 'صفة الإسناد',
  `planned_qty_month` decimal(16,2) DEFAULT NULL COMMENT 'CAP-01 §8.3: الحصةُ الشهريةُ الأولية بمقياسها — والاحتياطيُّ صفرٌ قبل التفعيل',
  `planned_qty_total` decimal(16,2) DEFAULT NULL COMMENT 'CAP-01 §8.3: الحصةُ الإجمالية المخططة',
  `measure_code` enum('hour','ton','trip','meter') DEFAULT NULL COMMENT 'CAP-01 §16: مقياسُ الخطة',
  `activation_state` enum('active','pending') NOT NULL DEFAULT 'active' COMMENT 'CAP-01 §8.3: حالةُ التفعيل — الاحتياطيُّ pending حتى يُفعَّل بحدثٍ له سببٌ ومعتمِد (§4-④)',
  `supplier_contract_line_id` int(11) DEFAULT NULL COMMENT 'CAP-01 §8.3: بندُ عقد المورد الذي تُحتسب به (supplier_contract_lines.id)',
  `drivers_count` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'عدد السائقين على المعدة في هذا المقعد',
  `drivers_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'قائمة employee_id للسائقين — مراجع لا نسخ' CHECK (json_valid(`drivers_json`)),
  `state` enum('active','ended') NOT NULL DEFAULT 'active',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `active_open_seat_key` varchar(40) GENERATED ALWAYS AS (if(`state` = 'active' and `date_to` is null and (`assignment_role` + 0 <> 2 or `activation_state` = 'active'),concat(`company_id`,':',`container_id`),NULL)) STORED COMMENT 'CAP-01 §4-⑥/C4: تخصيصٌ مفتوحٌ فعّالٌ واحدٌ لكل مقعد — والاحتياطيُّ pending خارج القيد (الرتبةُ 2 = احتياطي · تعبيرٌ ASCII لئلا تُشوَّه حرفيةٌ عربيةٌ في تطبيقٍ قادم)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_active_open` (`active_open_seat_key`),
  KEY `ix_sa_seat` (`company_id`,`container_id`,`date_from`),
  KEY `ix_sa_equipment` (`company_id`,`equipment_id`,`date_from`),
  KEY `fk_sa_container` (`container_id`),
  KEY `ix_sa_supplier_line` (`supplier_contract_line_id`),
  CONSTRAINT `fk_sa_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `ck_sa_standby_zero` CHECK (`activation_state` = _utf8mb4'active' or coalesce(`planned_qty_month`,0) = 0 and coalesce(`planned_qty_total`,0) = 0),
  CONSTRAINT `ck_sa_dates` CHECK (`date_to` is null or `date_to` >= `date_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: sec_actions ──
CREATE TABLE `sec_actions` (
  `action_code` varchar(24) NOT NULL,
  `name_ar` varchar(60) NOT NULL,
  `family` enum('visibility','mutation','workflow','output','admin') NOT NULL,
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`action_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-013 ②: الأفعال الستة عشر لا أربع رايات';

-- ── Table: sec_perm_backup_20260806 ──
CREATE TABLE `sec_perm_backup_20260806` (
  `role_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 0,
  `can_add` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  `rule_applied` varchar(16) NOT NULL,
  `captured_at` datetime NOT NULL,
  PRIMARY KEY (`role_id`,`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: sec_scopes ──
CREATE TABLE `sec_scopes` (
  `scope_code` varchar(24) NOT NULL,
  `name_ar` varchar(60) NOT NULL,
  `narrowness` tinyint(3) unsigned NOT NULL COMMENT '1 أوسع (شركة) … 9 أضيق (سجلاته هو)',
  PRIMARY KEY (`scope_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-013 ④: تسعة نطاقات لا ثنائية — الفعل نفسه يختلف بالنطاق';

-- ── Table: sec_sod_denials ──
CREATE TABLE `sec_sod_denials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `pair_code` varchar(12) NOT NULL,
  `scope` enum('role','document') NOT NULL,
  `subject_user_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned DEFAULT NULL COMMENT 'المسمّى المطلوبُ عند رفضِ التكليف',
  `source_kind` varchar(40) NOT NULL DEFAULT '' COMMENT 'المستندُ عند رفضِ الاعتماد',
  `source_ref` varchar(120) NOT NULL DEFAULT '',
  `detail` varchar(600) NOT NULL DEFAULT '',
  `denied_at` datetime NOT NULL,
  `attempted_by` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_pair` (`company_id`,`pair_code`,`denied_at`),
  KEY `ix_subject` (`subject_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PROP-01 §7-2 ⑩ — سجلُّ رفضِ الجمعِ بين وظيفتين لا تُجمعان';

-- ── Table: sec_sod_pairs ──
CREATE TABLE `sec_sod_pairs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `code` varchar(12) NOT NULL COMMENT 'SOD-01..SOD-13',
  `func_a` varchar(160) NOT NULL COMMENT 'الوظيفةُ الأولى',
  `func_b` varchar(160) NOT NULL COMMENT 'ما لا تُجمع معه',
  `roles_a` varchar(120) NOT NULL DEFAULT '' COMMENT 'أدوارُ الوظيفةِ الأولى مفصولةً بفاصلة',
  `roles_b` varchar(120) NOT NULL DEFAULT '',
  `why` varchar(400) NOT NULL DEFAULT '' COMMENT 'لماذا لا تُجمعان',
  `severity` enum('block','warn') NOT NULL DEFAULT 'block' COMMENT 'block = قيدٌ بنيويٌّ يرفض التكليف',
  `scope` enum('role','document') NOT NULL DEFAULT 'role' COMMENT 'role = يُفحص عند التكليف · document = يُفحص على المستندِ الواحد',
  `enforced_by` varchar(120) NOT NULL DEFAULT '' COMMENT 'الخدمةُ التي تُنفذ هذا الزوجَ فعلًا — ولا زوجَ بلا مُنفِذ',
  `doc_ref` varchar(24) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sod` (`company_id`,`code`),
  KEY `ix_active` (`active`,`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PROP-01 §4-2 + FIN-ACC-01 §4-9 — أزواجُ فصلِ الواجباتِ قيدًا بنيويًّا';

-- ── Table: sensitive_access_grants ──
CREATE TABLE `sensitive_access_grants` (
  `gr_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `domain` enum('ownership','financing','payroll','bank','medical','pricing') NOT NULL,
  `permission_code` varchar(120) NOT NULL,
  `scope_rule` varchar(120) DEFAULT NULL,
  `reason` varchar(255) NOT NULL COMMENT 'إلزامي',
  `approvals_ref` varchar(120) DEFAULT NULL,
  `granted_from` date NOT NULL,
  `review_due_at` date DEFAULT NULL,
  `renewal_policy` enum('periodic','on_role_change','none') NOT NULL DEFAULT 'periodic',
  `state` enum('active','suspended','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`gr_id`),
  KEY `idx_sag_person` (`company_id`,`person_id`,`state`),
  KEY `idx_sag_domain` (`domain`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §1.1②: دائم ما دامت الوظيفة قائمة · كل قراءة به تُسجَّل · ويُعرض في المراجعة الدورية';

-- ── Table: sensitive_field_policies ──
CREATE TABLE `sensitive_field_policies` (
  `pol_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `field_code` varchar(120) NOT NULL,
  `classification` enum('payroll','bank','medical','personal','ownership','pricing') NOT NULL,
  `masking_rule` enum('full','partial','none') NOT NULL DEFAULT 'full',
  `allowed_roles_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_roles_json`)),
  PRIMARY KEY (`pol_id`),
  UNIQUE KEY `uq_sfp_field` (`field_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §10⑦: الحقل الذي لا يُملك لا يُجلب أصلًا';

-- ── Table: sensitive_read_log ──
CREATE TABLE `sensitive_read_log` (
  `read_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL COMMENT 'SEC-21: شركة السياق',
  `person_id` int(11) NOT NULL,
  `element_code` varchar(80) NOT NULL,
  `subject_type` varchar(60) NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  `result` enum('allowed','denied') NOT NULL,
  `grant_ref` varchar(120) DEFAULT NULL COMMENT 'مرجع المنح المسوِّغ (GR-… · policy:…)',
  `context` varchar(190) DEFAULT NULL COMMENT 'الشاشة أو الخدمة',
  PRIMARY KEY (`read_id`),
  KEY `ix_srl_person` (`person_id`,`at`),
  KEY `ix_srl_subject` (`subject_type`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §9: سجل اطلاع على الحقول الحساسة — Insert-only';

-- ── Table: settlement_lines ──
CREATE TABLE `settlement_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `settlement_id` int(11) NOT NULL,
  `line_kind` enum('entitlement','charge') NOT NULL COMMENT 'entitlement=مستحقٌّ له · charge=تحميلٌ عليه',
  `charge_type` varchar(20) DEFAULT NULL COMMENT 'للتحميل: fuel · parts · maintenance · transport · advance · penalty',
  `source_kind` varchar(20) NOT NULL COMMENT 'مصدرُ البند: due (دفتر الطرف) · parts (صرف) · maintenance (أمر)',
  `source_ref` varchar(60) NOT NULL COMMENT 'معرّفُ الأصل — به يُفتح المستندُ الأصلي',
  `description` varchar(255) DEFAULT NULL,
  `work_date` date DEFAULT NULL COMMENT 'تاريخُ الواقعة — به يُختار سعرُ الصرف',
  `amount` decimal(18,2) NOT NULL COMMENT 'المبلغ بعملته (موجبٌ دائمًا — والاتجاهُ من line_kind)',
  `currency` varchar(8) NOT NULL,
  `fx_rate` decimal(20,8) DEFAULT NULL,
  `base_amount` decimal(18,2) DEFAULT NULL,
  `objected` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'اعتراضُ الطرف — والتسويةُ لا تتجمد (§15.3)',
  `objection_note` varchar(255) DEFAULT NULL COMMENT 'السببُ إلزاميٌّ عند الاعتراض',
  `objected_by` int(11) DEFAULT NULL,
  `objected_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL COMMENT 'حسمُ الاعتراض — بعده يعود البندُ محتسبًا',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_line_source` (`settlement_id`,`source_kind`,`source_ref`) COMMENT 'لا يُحمَّل مصدرٌ مرتين في التسوية الواحدة',
  KEY `ix_line_settlement` (`settlement_id`),
  KEY `ix_line_objected` (`objected`),
  CONSTRAINT `fk_line_settlement` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='بنودُ التسوية — كلُّ بندٍ برابط أصله (UX-05 §5.2)';

-- ── Table: settlements ──
CREATE TABLE `settlements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزل المستأجر',
  `settlement_no` varchar(32) NOT NULL COMMENT 'STL-سنة-رقم',
  `party_type` enum('supplier','employee') NOT NULL COMMENT 'الخدمةُ واحدةٌ للطرفين (UX-02 §15.3) — والعاملُ توأمُ المورد',
  `party_ref` int(11) NOT NULL COMMENT 'suppliers.id أو employees.id بحسب النوع',
  `party_name` varchar(191) DEFAULT NULL COMMENT 'لقطةُ الاسم وقتَ التوليد — للكشف التاريخي',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `currency` varchar(8) NOT NULL COMMENT 'عملةُ التسوية — كلُّ بنودها بها',
  `fx_rate` decimal(20,8) DEFAULT NULL COMMENT 'سعرُ الصرف لعملة الأساس (FES §3.3)',
  `base_amount` decimal(18,2) DEFAULT NULL COMMENT 'صافي التسوية بعملة الأساس — NULL أي بانتظار سعر',
  `gross_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الاستحقاق الأولي (Σ البنود المستحقة)',
  `charges_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الσ التحميلات (موجبةً)',
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الصافي = الأولي − التحميلات (قد يكون سالبًا)',
  `net_direction` enum('payable','receivable') NOT NULL DEFAULT 'payable' COMMENT 'payable=له علينا · receivable=علينا له دَينٌ (الصافي سالب — قرارُ المالك ①)',
  `state` enum('draft','review','approved','payment_requested','invoiced','paid','closed','cancelled') NOT NULL DEFAULT 'draft' COMMENT 'دورةُ ENT-02 §4 — وInvoiced/Closed أُضيفتا في M-13',
  `open_objections` int(11) NOT NULL DEFAULT 0 COMMENT 'عدّادُ البنود المعترَض عليها المفتوحة (§15.3)',
  `payment_request_id` int(11) DEFAULT NULL COMMENT 'طلبُ الدفع المولَّد آليًّا عند الاعتماد (§15.3)',
  `receivable_due_id` int(11) DEFAULT NULL COMMENT 'الذمّةُ المدينة المولَّدة حين الصافي سالب',
  `event_id` int(11) DEFAULT NULL COMMENT 'حدثُ FES بمفتاح رقمها (§15.3)',
  `prepared_by` int(11) DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `invoice_no` varchar(64) DEFAULT NULL COMMENT 'رقمُ فاتورة المورد — مستندٌ ضريبيٌّ يُطابَق به لا مصدرُ اعتراف',
  `invoice_date` date DEFAULT NULL,
  `invoice_amount` decimal(18,2) DEFAULT NULL COMMENT 'مبلغُ الفاتورة كما ورد — لا يُعدَّل ولا يُعدِّل الصافي',
  `invoice_currency` varchar(8) DEFAULT NULL,
  `invoice_diff` decimal(18,2) DEFAULT NULL COMMENT 'الفاتورة − الصافي المعتمد (موجبٌ = زيادةُ المورد)',
  `invoice_diff_reason` varchar(255) DEFAULT NULL COMMENT '**إلزاميٌّ متى وُجد فرق** — «فرقٌ بقرارٍ لا تعديلًا صامتًا»',
  `invoice_diff_doc_ref` varchar(120) DEFAULT NULL,
  `invoiced_by` int(11) DEFAULT NULL,
  `invoiced_at` datetime DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settlement_no` (`company_id`,`settlement_no`),
  UNIQUE KEY `uq_settlement_party_period` (`company_id`,`party_type`,`party_ref`,`period_from`,`period_to`) COMMENT 'تسويةٌ واحدةٌ لكل (طرف × فترة) — إعادةُ التوليد ترجع 409 بمرجع القائم (§15.4)',
  KEY `ix_settlement_state` (`state`),
  KEY `ix_settlement_party` (`party_type`,`party_ref`),
  KEY `ix_settlement_invoice` (`company_id`,`party_ref`,`invoice_no`),
  CONSTRAINT `ck_settlement_invoice_diff` CHECK (`invoice_diff` is null or abs(`invoice_diff`) < 0.005 or `invoice_diff_reason` is not null and char_length(trim(`invoice_diff_reason`)) > 0 and `invoice_diff_doc_ref` is not null and char_length(trim(`invoice_diff_doc_ref`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: shift_patterns ──
CREATE TABLE `shift_patterns` (
  `pattern_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `name_ar` varchar(120) NOT NULL,
  `shifts_per_day` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `base_hours` decimal(5,2) NOT NULL,
  `overtime_hours` decimal(5,2) NOT NULL DEFAULT 0.00,
  `crosses_midnight` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`pattern_id`),
  UNIQUE KEY `uq_sp_name` (`company_id`,`name_ar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §2: أنماط الورديات — قاموس يُضاف إليه بقرار، والمواعيد معرَّفة لا مثبَّتة في الكود';

-- ── Table: shift_period_defs ──
CREATE TABLE `shift_period_defs` (
  `def_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pattern_id` int(10) unsigned NOT NULL,
  `shift_no` tinyint(3) unsigned NOT NULL,
  `period_no` tinyint(3) unsigned NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `base_hours` decimal(5,2) NOT NULL,
  `overtime_hours` decimal(5,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`def_id`),
  UNIQUE KEY `uq_spd` (`pattern_id`,`shift_no`,`period_no`),
  CONSTRAINT `fk_spd_pattern` FOREIGN KEY (`pattern_id`) REFERENCES `shift_patterns` (`pattern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §2.1: فترات النمط بمواعيدها وساعاتها الأساسية والإضافية';

-- ── Table: shift_period_logs ──
CREATE TABLE `shift_period_logs` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `work_date` date NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `shift_no` tinyint(3) unsigned NOT NULL,
  `period_no` tinyint(3) unsigned NOT NULL,
  `operator_person_id` int(11) NOT NULL COMMENT 'مشغّل واحد لكل فترة إلزامًا — NOT NULL بنيوي',
  `qty` decimal(14,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(16) NOT NULL DEFAULT 'ton',
  `run_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `standby_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `stop_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `stop_reason_code` varchar(40) DEFAULT NULL COMMENT 'من stop_reason_codes (N-12) — توقف بلا سبب 422 في الخدمة',
  `site_id` int(11) DEFAULT NULL,
  `state` enum('logged','approved') NOT NULL DEFAULT 'logged',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `synced_late` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'DEC-01 ⑨: مزامنة بعد أكثر من يوم من تاريخ العمل — يدخل السلسلة كأي صف ولا يُعتمد آليًّا',
  PRIMARY KEY (`log_id`),
  UNIQUE KEY `uq_spl_key` (`work_date`,`equipment_id`,`shift_no`,`period_no`) COMMENT 'مفتاح (معدة×تاريخ×وردية×فترة) — يمنع تكرار المزامنة (وشرط N-08)',
  KEY `ix_spl_operator` (`operator_person_id`,`work_date`),
  KEY `ix_spl_company` (`company_id`,`work_date`),
  KEY `fk_spl_reason` (`stop_reason_code`),
  CONSTRAINT `fk_spl_reason` FOREIGN KEY (`stop_reason_code`) REFERENCES `stop_reason_codes` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WRK-01 §2.1: سجل الفترة — وحدة الحقيقة؛ المعدة ثابتة للوردية والمشغّل يتغير بالفترة';

-- ── Table: signing_authorities ──
CREATE TABLE `signing_authorities` (
  `auth_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `person_id` int(11) NOT NULL COMMENT 'users.id',
  `entity_id` int(10) unsigned NOT NULL COMMENT 'الكيان المفوِّض — التفويض بالصفة والكيان معًا',
  `capacity_id` int(11) DEFAULT NULL COMMENT 'user_capacities.id — الصفة (H-15)',
  `auth_type` enum('general','financial','contractual','banking','operational') NOT NULL DEFAULT 'general',
  `amount_cap` decimal(18,2) DEFAULT NULL COMMENT 'السقف المالي — NULL = بلا سقف (تفويض عام بقرار)',
  `currency` varchar(8) DEFAULT NULL,
  `scope_type` varchar(40) DEFAULT NULL COMMENT 'project · department · doc_type',
  `scope_id` int(11) DEFAULT NULL,
  `joint_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'التوقيع المشترك — مطفأ في النمط ①',
  `delegated_from_auth_id` int(10) unsigned DEFAULT NULL COMMENT 'DEC-01 ①: نيابة — مرجع تفويض الأصيل؛ النائب بمدة مكتوبة إلزامًا (تحرسه الخدمة)',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL COMMENT 'ينتهي بانتهاء مدته آليًّا — الحارس يقرأ التاريخ',
  `doc_ref` varchar(120) DEFAULT NULL,
  `state` enum('active','revoked') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`auth_id`),
  KEY `ix_sa_person` (`person_id`,`entity_id`,`state`),
  KEY `ix_sa_expiry` (`valid_to`),
  KEY `fk_sa_entity` (`entity_id`),
  KEY `ix_sa_delegated` (`delegated_from_auth_id`),
  CONSTRAINT `fk_sa_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §4: التفويض بالتوقيع — لا اعتماد بلا تفويض نافذ ساري';

-- ── Table: sites ──
CREATE TABLE `sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(190) NOT NULL,
  `site_kind` enum('mine','site') NOT NULL DEFAULT 'site' COMMENT 'H-05: «المنجمُ حالةٌ من الموقع لا فرقَ في المعالجة» — تمييزٌ عرضيٌّ؛ التعريب في الشاشة',
  `responsible_employee_id` int(11) DEFAULT NULL COMMENT 'مسؤولُ الموقع — مدخلُ E-07/H-03',
  `location_text` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `is_default` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'موقعُ الترحيل الرجعي: المشروعُ كان الموقعَ ضمنًا',
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `sod_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `conflict_code` varchar(40) NOT NULL,
  `name_ar` varchar(255) NOT NULL,
  `permission_a` varchar(120) NOT NULL,
  `permission_b` varchar(120) NOT NULL,
  `severity` enum('high','critical') NOT NULL DEFAULT 'high',
  `compensating_control` varchar(255) DEFAULT NULL COMMENT 'الاستثناء بموافقة التنفيذي ورقابة تعويضية معلنة — ولا يُمنح صامتًا',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`sod_id`),
  UNIQUE KEY `uq_sod_code` (`conflict_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §5: الثمانية صفوف هنا — 409 مع عرض التعارض';

-- ── Table: stop_reason_codes ──
CREATE TABLE `stop_reason_codes` (
  `code` varchar(40) NOT NULL,
  `name_ar` varchar(120) NOT NULL,
  `obligation_type` enum('fuel','access_road','loading_equipment','equipment_readiness','operators','permits_safety','utilities','catering_camp','force_majeure') DEFAULT NULL COMMENT 'بند الالتزام المقابل الافتراضي — NULL لسبب «أخرى» فيُلزم ببند صريح عند الإدخال',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='N-12: أسباب التعطل الستة — قائمة محكومة لا نص حر، وكل سبب ببنده المقابل';

-- ── Table: substitute_coverages ──
CREATE TABLE `substitute_coverages` (
  `cov_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `level` enum('own_standby','cross_supplier','source_change') NOT NULL COMMENT 'الدرجة: احتياطيُّ المورد نفسِه · تغطيةُ موردٍ آخر · تبديلُ مصدر التوريد (§6)',
  `covered_seat_id` int(10) unsigned NOT NULL COMMENT 'المقعدُ المغطى — op_containers.id (والموردُ المتعطل من شجرته)',
  `covering_supplier_id` int(11) DEFAULT NULL COMMENT 'INJ-0140: تُحسم عند الاعتماد بحسب الدرجة — لا تُعرف عند الطلب',
  `failed_supplier_id` int(11) DEFAULT NULL COMMENT 'الموردُ المتعطل — لقطةٌ من شجرة المقعد عند التقديم',
  `covering_equipment_id` int(11) DEFAULT NULL COMMENT 'المعدةُ البديلة إن عُيّنت',
  `reason_code` enum('breakdown','scheduled_maintenance','relocation_exit','document_expired','operator_shortage') NOT NULL COMMENT '§6.1-①: سببٌ من قائمةٍ محكومة — لا تغطيةَ بلا سبب',
  `reason_ref` varchar(60) DEFAULT NULL COMMENT 'مرجعُ بلاغٍ أو أمرِ عملٍ حيث ينطبق',
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL COMMENT '§6.1-②: إلزاميٌّ — لا تغطيةَ مفتوحةَ المدة؛ والتمديدُ قرارٌ جديد',
  `estimated_hours` decimal(10,2) DEFAULT NULL COMMENT '§6.1-⑤: الأثرُ يُحسب قبل الاعتماد ويُعرض على الموافقين',
  `approvals_ref` varchar(120) DEFAULT NULL COMMENT 'مرجعُ سلسلة الموافقات بدرجتها',
  `approvals_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'CAP-01 §6: موافقاتُ الدرجة المجموعة — {role: {by, at}}؛ والاكتمالُ بحسب مصفوفة الدرجة' CHECK (json_valid(`approvals_json`)),
  `impact_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'CAP-01 §6.1-⑤: الأثرُ على الأطراف الأربعة محسوبًا قبل الإرسال — يُعرض على الموافقين لا يُقدَّر بعد التنفيذ' CHECK (json_valid(`impact_json`)),
  `state` enum('draft','pending_approvals','approved','active','ended','rejected') NOT NULL DEFAULT 'draft',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cov_id`),
  UNIQUE KEY `uq_cov_request` (`company_id`,`covered_seat_id`,`reason_code`,`valid_from`,`valid_to`,`level`),
  KEY `ix_cov_seat` (`company_id`,`covered_seat_id`,`valid_from`),
  KEY `ix_cov_supplier` (`company_id`,`covering_supplier_id`,`state`),
  KEY `fk_cov_seat` (`covered_seat_id`),
  CONSTRAINT `fk_cov_seat` FOREIGN KEY (`covered_seat_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `ck_cov_dates` CHECK (`valid_to` >= `valid_from`),
  CONSTRAINT `ck_cov_reason_governed` CHECK (`reason_code` <> _utf8mb4'' and `level` <> _utf8mb4'')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: super_admin_password_resets ──
CREATE TABLE `super_admin_password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `super_admin_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_super_admin_password_resets_token_hash` (`token_hash`),
  KEY `idx_super_admin_password_resets_admin_id` (`super_admin_id`),
  CONSTRAINT `fk_super_admin_password_resets_admin` FOREIGN KEY (`super_admin_id`) REFERENCES `super_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: super_admins ──
CREATE TABLE `super_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد',
  `name` varchar(100) NOT NULL COMMENT 'الإسم',
  `email` varchar(150) NOT NULL COMMENT 'البريد ',
  `password` varchar(255) NOT NULL COMMENT 'كلمة المرور',
  `is_active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'نشط',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT 'آخر دخول',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'انشاء في',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تعديل في',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_advance_recoveries ──
CREATE TABLE `supplier_advance_recoveries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `advance_id` int(11) NOT NULL,
  `settlement_id` int(11) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `doc_ref` varchar(120) NOT NULL COMMENT 'يرث سندَ سلفته — لا استردادَ يتيم',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sadv_recovery` (`advance_id`,`settlement_id`),
  KEY `ix_sadv_rec_settlement` (`settlement_id`),
  CONSTRAINT `fk_sadv_rec_advance` FOREIGN KEY (`advance_id`) REFERENCES `supplier_advance_requests` (`id`),
  CONSTRAINT `ck_sadv_rec_amount` CHECK (`amount` > 0),
  CONSTRAINT `ck_sadv_rec_doc` CHECK (char_length(trim(`doc_ref`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_advance_requests ──
CREATE TABLE `supplier_advance_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_contract_id` int(11) DEFAULT NULL COMMENT 'عقدُ المورد إن خُصّصت به (H-07)',
  `advance_type` enum('cash','on_behalf','custody') NOT NULL DEFAULT 'cash' COMMENT 'نقدًا · نيابةً عنه · **عهدةً** — قائمةُ §3 نصًّا',
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(8) DEFAULT NULL,
  `doc_ref` varchar(120) NOT NULL COMMENT 'سندُ الصرف — إلزاميٌّ بنيويًّا («ما لا مستندَ له لا يُحمَّل»)',
  `issued_date` date NOT NULL,
  `installments_count` int(11) NOT NULL DEFAULT 1,
  `installment_amount` decimal(18,2) NOT NULL COMMENT 'قسطُ التصفية الواحدة',
  `first_recovery_period` date DEFAULT NULL,
  `recovered` decimal(18,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(18,2) GENERATED ALWAYS AS (`amount` - `recovered`) STORED COMMENT '**مولَّد** — «ورصيدُها ظاهرٌ في بطاقته دائمًا» بلا انحراف',
  `state` enum('draft','approved','active','settled','cancelled') NOT NULL DEFAULT 'draft',
  `note` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_sadv_supplier_state` (`supplier_id`,`state`),
  KEY `ix_sadv_co` (`company_id`,`state`),
  CONSTRAINT `fk_sadv_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `ck_sadv_inst` CHECK (`installments_count` >= 1 and `installment_amount` > 0),
  CONSTRAINT `ck_sadv_amount` CHECK (`amount` > 0),
  CONSTRAINT `ck_sadv_doc` CHECK (char_length(trim(`doc_ref`)) > 0),
  CONSTRAINT `ck_sadv_recovered` CHECK (`recovered` >= 0 and `recovered` <= `amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_capacity ──
CREATE TABLE `supplier_capacity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'عقدُ المورد (H-07) — «تُثبَّت في العقد»',
  `equipment_id` int(11) NOT NULL,
  `work_model` enum('hour','ton','trip','meter') NOT NULL DEFAULT 'hour' COMMENT 'نموذجُ الطاقة — «طاقةٌ نظريةٌ يوميةٌ **بنموذجها**»',
  `theoretical_daily` decimal(18,2) NOT NULL COMMENT 'الطاقةُ النظريةُ اليومية — ومنها يُقاس الأداءُ لا من تقديرٍ لاحق',
  `min_readiness_percent` decimal(5,2) DEFAULT NULL COMMENT 'الحدُّ التعاقديُّ للجاهزية — NULL = لم يُشترط (يُعلَن ولا يُفترض)',
  `replace_hours` int(11) DEFAULT NULL COMMENT 'مهلةُ الإحلال بالساعات — وتجاوزُها يحوّل التوقفَ إلى عجزِ تغطية',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','ended') NOT NULL DEFAULT 'active',
  `note` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_capacity` (`contract_id`,`equipment_id`,`valid_from`),
  KEY `ix_sup_capacity_eq` (`company_id`,`equipment_id`,`state`),
  KEY `fk_sup_capacity_equipment` (`equipment_id`),
  CONSTRAINT `fk_sup_capacity_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sup_capacity_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`),
  CONSTRAINT `ck_sup_capacity_daily` CHECK (`theoretical_daily` > 0),
  CONSTRAINT `ck_sup_capacity_readiness` CHECK (`min_readiness_percent` is null or `min_readiness_percent` > 0 and `min_readiness_percent` <= 100),
  CONSTRAINT `ck_sup_capacity_replace` CHECK (`replace_hours` is null or `replace_hours` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_charge_rules ──
CREATE TABLE `supplier_charge_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'عقدُ المورد الحديث (H-07)',
  `charge_type` enum('fuel','spares','maintenance','transport','operator_payroll','advance') NOT NULL COMMENT 'التحميلاتُ الستُّ في §2-⑥ نصًّا',
  `pricing` enum('cost','cost_plus','fixed') NOT NULL DEFAULT 'cost' COMMENT 'بسعر التكلفة · تكلفةٌ مضافةٌ بنسبتها · مبلغٌ ثابت',
  `rate` decimal(10,3) DEFAULT NULL COMMENT 'cost_plus = نسبةٌ مئوية · fixed = مبلغٌ للوحدة/الحدث',
  `cap` decimal(18,2) DEFAULT NULL COMMENT 'سقفُ التحميل الواحد — NULL = بلا سقفٍ مكتوب',
  `currency` varchar(8) DEFAULT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','ended') NOT NULL DEFAULT 'active',
  `note` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_charge_rule` (`contract_id`,`charge_type`,`valid_from`),
  KEY `ix_charge_rule_co` (`company_id`,`contract_id`,`state`),
  CONSTRAINT `fk_charge_rule_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_charge_rule_cap` CHECK (`cap` is null or `cap` > 0),
  CONSTRAINT `ck_charge_rule_rate` CHECK (`pricing` = _utf8mb4'cost' or `rate` is not null and `rate` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_contract_closures ──
CREATE TABLE `supplier_contract_closures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `state` enum('open','cleared','closed') NOT NULL DEFAULT 'open',
  `quota_open_count` int(11) NOT NULL DEFAULT 0 COMMENT 'حاوياتٌ مفتوحةٌ عند آخر قياس',
  `quota_closed_at` datetime DEFAULT NULL,
  `quota_close_reason` varchar(255) DEFAULT NULL COMMENT 'سببُ إقفال حصةٍ لم تُستهلك — «ولا تجاوزَ صامتًا للسقف» ولا إقفالَ صامتٌ دونه',
  `advances_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `advances_settled_at` datetime DEFAULT NULL,
  `guarantee_amount` decimal(18,2) DEFAULT NULL COMMENT 'لقطةٌ من العقد وقت فتح التصفية',
  `guarantee_currency` varchar(8) DEFAULT NULL,
  `guarantee_due_date` date DEFAULT NULL COMMENT 'نهايةُ العقد + مهلةُ الردّ',
  `guarantee_released_at` datetime DEFAULT NULL,
  `guarantee_due_ref` int(11) DEFAULT NULL COMMENT 'الذمّةُ الدائنةُ التي وُلّدت بالردّ — أثرٌ لا وعد',
  `clearance_doc` varchar(120) DEFAULT NULL COMMENT 'مرجعُ شهادة الإخلاء الموثَّقة',
  `note` varchar(255) DEFAULT NULL,
  `opened_by` int(11) DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_closure` (`contract_id`) COMMENT 'تصفيةٌ واحدةٌ للعقد — «بمفتاح (العقد × التصفية)»',
  KEY `ix_sup_closure` (`company_id`,`supplier_id`,`state`),
  CONSTRAINT `fk_sup_closure_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_closure_doc` CHECK (`state` <> _utf8mb4'closed' or `clearance_doc` is not null and `clearance_doc` <> _utf8mb4''),
  CONSTRAINT `ck_sup_closure_release` CHECK (`guarantee_released_at` is null or `guarantee_due_ref` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_contract_lines ──
CREATE TABLE `supplier_contract_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'رأسُ عقد المورد — البندُ ابنُه',
  `contract_obligation_ref` int(10) unsigned DEFAULT NULL COMMENT 'CAP-01 §8.2: التزامُ نوع المعدة في عقد العميل (contract_commitments.id) — لا حصةَ بلا التزامٍ في عقدٍ نافذ',
  `equipment_type_code` varchar(40) DEFAULT NULL COMMENT 'CAP-01 §8.2: نوعُ المعدة الملتزَم به',
  `primary_units_committed` smallint(5) unsigned DEFAULT NULL COMMENT 'CAP-01 §8.2: عددُ الأساسية التي التزم المورد بتوفيرها',
  `standby_units_required` smallint(5) unsigned DEFAULT NULL COMMENT 'CAP-01 §8.2: الاحتياطياتُ المطلوبةُ منه',
  `standby_units_allowed` smallint(5) unsigned DEFAULT NULL COMMENT 'CAP-01 §8.2: سقفُه الأقصى — والقيدُ: المسجَّلُ ≤ هذا الرقم (C17)',
  `replacement_sla_hours` decimal(8,2) DEFAULT NULL COMMENT 'CAP-01 §8.2: مهلةُ الإحلال بالساعات — تُقاس من لحظة التعطل لا التغطية (§7)',
  `standby_activation_terms` varchar(255) DEFAULT NULL COMMENT 'CAP-01 §8.2: شروطُ تفعيل احتياطيّه',
  `standby_payment_terms` varchar(255) DEFAULT NULL COMMENT 'CAP-01 §8.2: مقابلُ احتياطيّه إن وُجد — NULL = لم يُنَصَّ ولا يُفترض (DEC-CAP-A)',
  `work_model` enum('hour','ton','trip','meter') NOT NULL COMMENT 'نماذجُ §2-② الأربعة — ما خرج عنها 422',
  `unit` varchar(32) NOT NULL COMMENT 'تسميةُ الوحدة كما يقرؤها محرّكُ الفوترة',
  `unit_price` decimal(18,2) NOT NULL COMMENT 'سعرُ الوحدة — ≤ 0 مرفوضٌ 422',
  `currency` varchar(8) DEFAULT NULL COMMENT 'عملةُ البند — الفارغُ يرتدّ لعملة الرأس (تناظرُ الموروث)',
  `standby_basis` enum('none','rate','percent') NOT NULL DEFAULT 'none' COMMENT '«أساسُ احتساب الاستعداد إن استُحق» — none = لا استعدادَ مشترطًا',
  `standby_rate` decimal(18,4) DEFAULT NULL COMMENT 'rate = معدلُ الساعة · percent = نسبةٌ من unit_price',
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','replaced','ended') NOT NULL DEFAULT 'active',
  `source_table` varchar(64) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_line_model_unit` (`contract_id`,`work_model`,`unit`),
  KEY `ix_sup_line_co` (`company_id`,`contract_id`),
  KEY `ix_sup_line_obl` (`contract_obligation_ref`),
  CONSTRAINT `fk_sup_line_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_line_standby` CHECK (`standby_basis` = _utf8mb4'none' and `standby_rate` is null or `standby_basis` <> _utf8mb4'none' and `standby_rate` is not null and `standby_rate` > 0),
  CONSTRAINT `ck_sup_line_price` CHECK (`unit_price` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_contract_notes ──
CREATE TABLE `supplier_contract_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `contract_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`),
  KEY `fk_supplier_contract_notes_created_by` (`created_by`),
  CONSTRAINT `fk_supplier_contract_notes_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplierscontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_supplier_contract_notes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_contracts ──
CREATE TABLE `supplier_contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT 'عزلُ المستأجر (TenantRegistry)',
  `supplier_id` int(11) NOT NULL COMMENT 'الموردُ — شريكُ الطاقة داخل هرم الحصص',
  `client_contract_id` int(11) DEFAULT NULL COMMENT 'وصلةُ L1 — عقدُ العميل الذي تُقتطع منه الحصة (CON-03 §1)',
  `project_id` int(11) DEFAULT NULL COMMENT 'المشروعُ المشمول — يُقرأ ولا يُملك هنا',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `currency` varchar(8) DEFAULT NULL COMMENT 'رمزٌ لاتيني (USD·SDG·EUR·SAR) — التسميةُ العربية تبقى في المصدر',
  `performance_guarantee` decimal(18,2) DEFAULT NULL COMMENT 'ضمانُ الأداء — NULL = لم يُشترط (يُعلَن ولا يُفترض)',
  `guarantee_retention_days` int(11) DEFAULT NULL COMMENT 'مهلةُ ردّ الضمان بالأيام بعد الانتهاء — «ردُّ الضمان **بعد مهلته**»',
  `advance_payment` decimal(18,2) DEFAULT NULL COMMENT 'الدفعةُ المقدمة — تُستهلك استقطاعًا في التصفية الدورية',
  `state` enum('مسودة','تفاوض','معتمد','موقَّع','نافذ','قيد التنفيذ','معلَّق','معدَّل','مجدَّد','منتهٍ','مقفل','مصفّى') NOT NULL DEFAULT 'مسودة' COMMENT 'مفرداتُ ContractStateMachine نفسُها — لا قاموسَ ثانٍ',
  `version` int(11) NOT NULL DEFAULT 1 COMMENT 'قفلٌ تفاؤلي — 409 عند الانحراف',
  `source_table` varchar(64) DEFAULT NULL COMMENT 'وصلةُ الترحيل — غيرُ الفارغ = مرحَّلٌ محصَّنٌ 423',
  `source_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'إخفاءٌ ناعم — لا حذفَ صلب',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_contract_party` (`supplier_id`,`client_contract_id`,`start_date`),
  UNIQUE KEY `uq_sup_contract_source` (`source_table`,`source_id`,`company_id`),
  KEY `ix_sup_contract_co_state` (`company_id`,`state`),
  KEY `ix_sup_contract_client` (`client_contract_id`),
  CONSTRAINT `fk_sup_contract_client` FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`),
  CONSTRAINT `fk_sup_contract_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `ck_sup_advance_payment` CHECK (`advance_payment` is null or `advance_payment` > 0),
  CONSTRAINT `ck_sup_guarantee_amount` CHECK (`performance_guarantee` is null or `performance_guarantee` > 0),
  CONSTRAINT `ck_sup_guarantee_days` CHECK (`performance_guarantee` is null or `guarantee_retention_days` is not null and `guarantee_retention_days` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_evaluation_lines ──
CREATE TABLE `supplier_evaluation_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `indicator` enum('readiness','coverage','attributed_stops','operator_quality','incidents') NOT NULL,
  `measurable` tinyint(4) NOT NULL DEFAULT 1 COMMENT '0 = بلا مصدرٍ في الفترة — يُعلَن ولا يُقدَّر',
  `measured_value` decimal(18,2) DEFAULT NULL COMMENT 'القياسُ الخام كما قُرئ من السجل',
  `basis_value` decimal(18,2) DEFAULT NULL COMMENT 'الأساسُ الذي قُسم عليه (زمنٌ مخططٌ · مقياسٌ مكتوب)',
  `ratio` decimal(6,4) DEFAULT NULL COMMENT 'نسبةُ الإجادة (0..1) — الأعلى أفضل',
  `weight` decimal(5,2) NOT NULL,
  `earned` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'الوزنُ × النسبة',
  `source_note` varchar(255) DEFAULT NULL COMMENT 'مصدرُ الرقم بلغة المهمة — لا رقمَ بلا مصدر',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_eval_line` (`evaluation_id`,`indicator`),
  CONSTRAINT `fk_sup_eval_line` FOREIGN KEY (`evaluation_id`) REFERENCES `supplier_evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_eval_ratio` CHECK (`ratio` is null or `ratio` >= 0 and `ratio` <= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_evaluation_weights ──
CREATE TABLE `supplier_evaluation_weights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `indicator` enum('readiness','coverage','attributed_stops','operator_quality','incidents') NOT NULL COMMENT 'مؤشراتُ §4-التقييم الخمسةُ نصًّا',
  `weight` decimal(5,2) NOT NULL COMMENT 'وزنُ المؤشر — وΣ الأوزان = 100 (تفرضه الخدمة)',
  `scale_max` decimal(10,2) DEFAULT NULL COMMENT 'مقياسُ المؤشرات العددية (الحوادث): العددُ الذي تبلغ عنده النتيجةُ صفرًا — NULL = بلا مقياسٍ مكتوب فلا يُقاس',
  `note` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_eval_weight` (`company_id`,`indicator`),
  CONSTRAINT `ck_sup_eval_scale` CHECK (`scale_max` is null or `scale_max` > 0),
  CONSTRAINT `ck_sup_eval_weight` CHECK (`weight` > 0 and `weight` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_evaluations ──
CREATE TABLE `supplier_evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `contract_id` int(11) DEFAULT NULL COMMENT 'عقدُ المورد إن قُصد بعينه — والتقييمُ للمورد أصلًا',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `score` decimal(5,2) DEFAULT NULL COMMENT 'النتيجةُ من 100 — **محسوبةٌ من المؤشرات** ولا تُكتب يدًا (§4: لا انطباعًا)',
  `weight_measured` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'مجموعُ أوزان المؤشرات **المقيسة فعلًا** — التغطيةُ تُعلَن ولا تُخفى خلف نسبةٍ مطبَّعة',
  `state` enum('draft','decided') NOT NULL DEFAULT 'draft',
  `renewal_flag` enum('eligible','conditional','not_eligible') DEFAULT NULL COMMENT 'أثرُ النتيجة على التجديد — «ونتيجتُه **شرطٌ في التجديد**»',
  `decision_note` varchar(255) DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_eval_period` (`supplier_id`,`period_from`,`period_to`),
  KEY `ix_sup_eval` (`company_id`,`supplier_id`,`state`,`period_to`),
  CONSTRAINT `fk_sup_eval_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_eval_decided` CHECK (`state` <> _utf8mb4'decided' or `renewal_flag` is not null and `decided_by` is not null),
  CONSTRAINT `ck_sup_eval_period` CHECK (`period_to` >= `period_from`),
  CONSTRAINT `ck_sup_eval_reason` CHECK (`renewal_flag` is null or `renewal_flag` <> _utf8mb4'not_eligible' or `decision_note` is not null and `decision_note` <> _utf8mb4'')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_penalty_rules ──
CREATE TABLE `supplier_penalty_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `kind` enum('shortfall','readiness','coverage','delay') NOT NULL COMMENT 'عجزٌ · جاهزيةٌ · تغطيةٌ · تأخر — قائمةُ §6 نصًّا',
  `threshold` decimal(12,3) DEFAULT NULL COMMENT 'الحدُّ الذي دونه يُفعَّل الجزاء (نسبةُ جاهزيةٍ دنيا · ساعاتُ إحلال …)',
  `rate` decimal(12,3) NOT NULL COMMENT 'معدلُ الجزاء لكل وحدةِ عجزٍ أو نقطةِ نقص',
  `rate_basis` enum('per_unit','percent_of_base') NOT NULL DEFAULT 'per_unit',
  `cap_percent` decimal(5,2) DEFAULT NULL COMMENT 'سقفُ الجزاء كنسبةٍ من الأساس — NULL = بلا سقفٍ مكتوب (يُعلَن)',
  `periodicity` enum('daily','monthly','contract') NOT NULL DEFAULT 'monthly',
  `inherits_attribution` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1 = يرث إسنادَ CON-02 · 0 يلزمه سببٌ مكتوب (§4: يشدّد لا يعكس)',
  `override_reason` varchar(255) DEFAULT NULL,
  `currency` varchar(8) DEFAULT NULL,
  `formula_note` varchar(255) DEFAULT NULL COMMENT 'توثيقُ الصيغة نصًّا — **لا يُقيَّم**: الحسابُ من الأعمدة المحكومة',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','ended') NOT NULL DEFAULT 'active',
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_penalty_rule` (`contract_id`,`kind`,`valid_from`),
  KEY `ix_penalty_rule_co` (`company_id`,`contract_id`,`state`),
  CONSTRAINT `fk_sup_penalty_rule_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_penalty_rule_cap` CHECK (`cap_percent` is null or `cap_percent` > 0 and `cap_percent` <= 100),
  CONSTRAINT `ck_penalty_rule_override` CHECK (`inherits_attribution` = 1 or `override_reason` is not null and char_length(trim(`override_reason`)) > 0),
  CONSTRAINT `ck_penalty_rule_rate` CHECK (`rate` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplier_rfqs ──
CREATE TABLE `supplier_rfqs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `rfq_no` varchar(40) NOT NULL,
  `client_contract_id` int(10) unsigned NOT NULL COMMENT 'العقدُ الذي اشتُقت منه البنود',
  `request_id` int(11) DEFAULT NULL COMMENT 'طلبُ الشراءِ المعتمدُ الذي اشتُقَّ منه طلبُ العروض (INJ-0091)',
  `title` varchar(160) DEFAULT NULL,
  `due_date` date NOT NULL COMMENT 'موعدُ الإقفال — «عرضٌ بعد الإقفال 423»',
  `state` enum('draft','sent','closed','awarded','contracted','cancelled') NOT NULL DEFAULT 'draft' COMMENT 'UX-05 §8.2: Awarded → Contracted → ContainersAllocated',
  `sent_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `awarded_at` datetime DEFAULT NULL,
  `awarded_by` int(10) unsigned DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_no` (`company_id`,`rfq_no`),
  KEY `ix_rfq_contract` (`company_id`,`client_contract_id`,`state`),
  KEY `ix_rfq_request` (`request_id`),
  CONSTRAINT `ck_rfq_awarded` CHECK (`state` not in (_utf8mb4'awarded',_utf8mb4'contracted') or `awarded_by` is not null),
  CONSTRAINT `ck_rfq_cancel` CHECK (`state` <> _utf8mb4'cancelled' or `cancel_reason` is not null and `cancel_reason` <> _utf8mb4'')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: suppliercontractequipments ──
CREATE TABLE `suppliercontractequipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'معرف عقد المورد من جدول supplierscontracts',
  `equip_type` varchar(100) DEFAULT NULL COMMENT 'نوع المعدة (حفار، قلاب، خرامة)',
  `equip_size` int(11) DEFAULT NULL COMMENT 'حجم المعدة',
  `equip_count` int(11) DEFAULT NULL COMMENT 'عدد المعدات',
  `equip_count_basic` int(11) DEFAULT 0 COMMENT 'عدد المعدات الأساسية',
  `equip_count_backup` int(11) DEFAULT 0 COMMENT 'عدد المعدات الاحتياطية',
  `equip_shifts` int(11) DEFAULT NULL COMMENT 'عدد الورديات',
  `equip_unit` varchar(50) DEFAULT NULL COMMENT 'وحدة القياس (ساعة، طن، متر)',
  `shift1_start` time DEFAULT NULL COMMENT 'بداية الوردية الأولى',
  `shift1_end` time DEFAULT NULL COMMENT 'نهاية الوردية الأولى',
  `shift2_start` time DEFAULT NULL COMMENT 'بداية الوردية الثانية',
  `shift2_end` time DEFAULT NULL COMMENT 'نهاية الوردية الثانية',
  `shift_hours` decimal(10,2) DEFAULT NULL COMMENT 'ساعات الوردية',
  `equip_total_month` decimal(10,2) DEFAULT NULL COMMENT 'إجمالي الوحدات يومياً',
  `equip_monthly_target` decimal(10,2) DEFAULT NULL COMMENT 'وحدات العمل في الشهر',
  `equip_total_contract` decimal(10,2) DEFAULT NULL COMMENT 'إجمالي وحدات العقد',
  `equip_price` decimal(10,2) DEFAULT NULL COMMENT 'السعر للوحدة',
  `equip_price_currency` varchar(20) DEFAULT NULL COMMENT 'العملة (دولار، جنيه)',
  `equip_operators` int(11) DEFAULT NULL COMMENT 'عدد المشغلين',
  `equip_supervisors` int(11) DEFAULT NULL COMMENT 'عدد المشرفين',
  `equip_technicians` int(11) DEFAULT NULL COMMENT 'عدد الفنيين',
  `equip_assistants` int(11) DEFAULT NULL COMMENT 'عدد المساعدين',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`),
  CONSTRAINT `fk_suppliercontractequipments_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplierscontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='معدات عقود الموردين';

-- ── Table: suppliers ──
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `supplier_code` varchar(100) DEFAULT NULL COMMENT 'الرمز/الكود للمورد',
  `supplier_type` enum('فرد','شركة','وسيط','مالك','جهة حكومية') DEFAULT NULL COMMENT 'نوع المورد',
  `dealing_nature` varchar(255) DEFAULT NULL COMMENT 'طبيعة التعامل',
  `equipment_types` mediumtext DEFAULT NULL COMMENT 'أنواع المعدات (مفصولة بفواصل)',
  `commercial_registration` varchar(100) DEFAULT NULL COMMENT 'رقم التسجيل التجاري/الرخصة',
  `tax_number` varchar(100) DEFAULT NULL COMMENT 'الرقمُ الضريبي — حقلٌ نظاميٌّ واجب (UX-05 §5.1-①)',
  `bank_name` varchar(150) DEFAULT NULL,
  `bank_account_no` varchar(60) DEFAULT NULL,
  `bank_iban` varchar(60) DEFAULT NULL,
  `bank_doc_ref` varchar(120) DEFAULT NULL COMMENT 'مستندُ التوثيق (شهادةٌ بنكيةٌ أو شيكٌ ملغًى) — **توثيقٌ بلا مستندٍ دعوى**',
  `bank_verified_at` datetime DEFAULT NULL,
  `bank_verified_by` int(11) DEFAULT NULL,
  `identity_type` varchar(100) DEFAULT NULL COMMENT 'نوع الهوية',
  `identity_number` varchar(100) DEFAULT NULL COMMENT 'رقم الهوية/التسجيل',
  `identity_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الهوية',
  `email` varchar(255) DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `phone_alternative` varchar(50) DEFAULT NULL COMMENT 'رقم هاتف بديل',
  `full_address` mediumtext DEFAULT NULL COMMENT 'العنوان الكامل',
  `contact_person_name` varchar(255) DEFAULT NULL COMMENT 'اسم جهة الاتصال الأساسية',
  `contact_person_phone` varchar(50) DEFAULT NULL COMMENT 'هاتف جهة الاتصال',
  `financial_registration_status` enum('مسجل رسميا','غير مسجل','تحت التسجيل','معفى من التسجيل') DEFAULT NULL COMMENT 'حالة التسجيل المالي',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `phone` varchar(15) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_suppliers_is_deleted` (`is_deleted`),
  CONSTRAINT `ck_sup_bank_verified` CHECK (`bank_verified_at` is null or `bank_account_no` is not null and `bank_account_no` <> _utf8mb4'' and `bank_doc_ref` is not null and `bank_doc_ref` <> _utf8mb4'')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: supplierscontracts ──
CREATE TABLE `supplierscontracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) NOT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int(11) DEFAULT 0,
  `contract_duration_months` int(11) DEFAULT 0,
  `contract_duration_days` int(11) DEFAULT 0,
  `equip_shifts_contract` int(11) DEFAULT 0 COMMENT 'عدد الورديات في العقد',
  `shift_contract` int(11) DEFAULT 0 COMMENT 'ساعات الوردية للعقد',
  `equip_total_contract_daily` int(11) DEFAULT 0 COMMENT 'إجمالي العقد اليومي',
  `total_contract_permonth` int(11) DEFAULT 0 COMMENT 'إجمالي العقد شهرياً',
  `total_contract_units` int(11) DEFAULT 0 COMMENT 'إجمالي وحدات العقد',
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `transportation` mediumtext DEFAULT NULL,
  `accommodation` mediumtext DEFAULT NULL,
  `place_for_living` mediumtext DEFAULT NULL,
  `workshop` mediumtext DEFAULT NULL,
  `equip_type` varchar(100) DEFAULT NULL,
  `equip_size` int(11) DEFAULT NULL,
  `equip_count` int(11) DEFAULT 0,
  `equip_target_per_month` int(11) DEFAULT 0,
  `equip_total_month` int(11) DEFAULT 0,
  `equip_total_contract` int(11) DEFAULT 0,
  `mach_type` varchar(100) DEFAULT NULL,
  `mach_size` int(11) DEFAULT NULL,
  `mach_count` int(11) DEFAULT 0,
  `mach_target_per_month` int(11) DEFAULT 0,
  `mach_total_month` int(11) DEFAULT 0,
  `mach_total_contract` int(11) DEFAULT 0,
  `hours_monthly_target` int(11) DEFAULT 0,
  `forecasted_contracted_hours` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `daily_work_hours` varchar(20) DEFAULT NULL,
  `daily_operators` varchar(20) DEFAULT NULL,
  `first_party` varchar(255) DEFAULT NULL,
  `second_party` varchar(255) DEFAULT NULL,
  `witness_one` varchar(255) DEFAULT NULL,
  `witness_two` varchar(255) DEFAULT NULL,
  `price_currency_contract` varchar(50) DEFAULT NULL COMMENT 'عملة العقد (دولار/جنيه)',
  `paid_contract` varchar(100) DEFAULT NULL COMMENT 'المبلغ المدفوع',
  `payment_time` varchar(50) DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)',
  `guarantees` mediumtext DEFAULT NULL COMMENT 'الضمانات',
  `payment_date` date DEFAULT NULL COMMENT 'تاريخ الدفع',
  `project_id` int(11) NOT NULL DEFAULT 0,
  `project_contract_id` int(11) DEFAULT NULL COMMENT 'معرف عقد المشروع المرتبط',
  `status` tinyint(1) DEFAULT 1 COMMENT '1=نشط, 0=موقوف',
  `pause_reason` mediumtext DEFAULT NULL,
  `pause_date` date DEFAULT NULL COMMENT 'تاريخ إيقاف العقد',
  `resume_date` date DEFAULT NULL COMMENT 'تاريخ استئناف العقد',
  `termination_type` varchar(50) DEFAULT NULL COMMENT 'amicable أو hardship',
  `termination_reason` mediumtext DEFAULT NULL,
  `merged_with` int(11) DEFAULT NULL COMMENT 'معرف العقد المدموج معه',
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

-- ── Table: task_assignments ──
CREATE TABLE `task_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(16) NOT NULL COMMENT 'assign|reassign|delegate|transfer',
  `from_user_id` int(10) unsigned DEFAULT NULL,
  `to_user_id` int(10) unsigned NOT NULL,
  `reason` varchar(300) DEFAULT NULL COMMENT 'إلزامي لإعادة الإسناد',
  `created_by` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'المنشئ',
  `created_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المنشئ لحظة الفعل',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(10) unsigned DEFAULT NULL COMMENT 'المعتمِد',
  `approved_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المعتمِد',
  `approved_at` datetime DEFAULT NULL,
  `delegation_ref` varchar(60) DEFAULT NULL COMMENT 'مرجع التفويض إن اعتُمد به',
  `parent_ref` varchar(60) DEFAULT NULL COMMENT 'المرجع الأب',
  PRIMARY KEY (`id`),
  KEY `ix_ta_item` (`item_id`),
  KEY `ix_ta_to` (`company_id`,`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WFM: تاريخ الإسناد — العدّ يستمر ولا يُصفَّر';

-- ── Table: task_dependencies ──
CREATE TABLE `task_dependencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `depends_on_item_id` bigint(20) unsigned NOT NULL,
  `dep_type` varchar(12) NOT NULL DEFAULT 'blocks',
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_td` (`item_id`,`depends_on_item_id`),
  KEY `ix_td_dep` (`depends_on_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: task_evidence ──
CREATE TABLE `task_evidence` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(12) NOT NULL DEFAULT 'note' COMMENT 'file|link|record|note',
  `ref` varchar(300) NOT NULL,
  `note` varchar(400) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_te_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: task_templates ──
CREATE TABLE `task_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `code` varchar(30) NOT NULL,
  `title` varchar(300) NOT NULL,
  `details` text DEFAULT NULL,
  `org_unit_id` int(10) unsigned DEFAULT NULL,
  `owner_role_id` int(10) unsigned DEFAULT NULL COMMENT 'الدور المالك — تُعاد للدور لا للشخص',
  `priority` varchar(4) NOT NULL DEFAULT 'P3',
  `deliverable` varchar(300) NOT NULL,
  `evidence_required` varchar(200) NOT NULL DEFAULT 'أثر الفعل في سجل التدقيق',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'المنشئ',
  `created_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المنشئ لحظة الفعل',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(10) unsigned DEFAULT NULL COMMENT 'المعتمِد',
  `approved_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المعتمِد',
  `approved_at` datetime DEFAULT NULL,
  `delegation_ref` varchar(60) DEFAULT NULL COMMENT 'مرجع التفويض إن اعتُمد به',
  `parent_ref` varchar(60) DEFAULT NULL COMMENT 'المرجع الأب',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tt` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WFM: SRC-08 — المهمة الدورية تتولد بدوريتها من قالبها';

-- ── Table: tax_invoices ──
CREATE TABLE `tax_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL COMMENT '«ولا صفَّ بلا claim_id» — ولا فاتورةَ بلا مستخلص',
  `client_id` int(11) NOT NULL,
  `serial_no` varchar(40) NOT NULL COMMENT 'الرقمُ التسلسليُّ النظامي INV-{سنة}-{تسلسل}',
  `serial_year` smallint(6) NOT NULL,
  `serial_seq` int(11) NOT NULL COMMENT 'تسلسلٌ متصلٌ لكل (شركة × سنة) — والثغرةُ تُرى',
  `currency` varchar(8) NOT NULL,
  `net_amount` decimal(18,2) NOT NULL COMMENT 'صافي المستخلص كما اعتُمد — **لا يُكتب يدًا**',
  `tax_code` varchar(16) DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT NULL,
  `tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '«والضريبةُ سطرٌ مستقلٌّ بمرجعها» (§5)',
  `total_amount` decimal(18,2) NOT NULL COMMENT 'الصافي + الضريبة',
  `tax_fields_json` text DEFAULT NULL COMMENT 'الحقولُ النظامية لحظةَ الإصدار — لقطةٌ لا اشتقاق',
  `state` enum('issued','cancelled') NOT NULL DEFAULT 'issued',
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `issued_by` int(11) DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_serial` (`company_id`,`serial_no`),
  UNIQUE KEY `uq_tax_seq` (`company_id`,`serial_year`,`serial_seq`),
  KEY `ix_tax_claim` (`claim_id`),
  KEY `ix_tax_client` (`company_id`,`client_id`,`state`),
  CONSTRAINT `fk_tax_invoice_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`),
  CONSTRAINT `ck_tax_cancel` CHECK (`state` <> _utf8mb4'cancelled' or `cancel_reason` is not null and `cancel_reason` <> _utf8mb4''),
  CONSTRAINT `ck_tax_ref` CHECK (`tax_amount` = 0 or `tax_code` is not null and `tax_code` <> _utf8mb4'' and `tax_rate` is not null),
  CONSTRAINT `ck_tax_total` CHECK (`total_amount` = `net_amount` + `tax_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: template_permission_dims ──
CREATE TABLE `template_permission_dims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tp_id` int(10) unsigned NOT NULL COMMENT 'بند القالب template_permissions.tp_id',
  `action_code` varchar(24) NOT NULL COMMENT 'من قاموس الستة عشر',
  `scope_code` varchar(24) NOT NULL DEFAULT 'company' COMMENT 'من النطاقات التسعة',
  `field_rule` varchar(190) DEFAULT NULL COMMENT 'ظهور الحقل/التبويب المسمى (NULL = الشاشة كلها)',
  `doc_type` varchar(60) DEFAULT NULL COMMENT 'بعد الاعتماد: نوع المستند',
  `amount_cap` decimal(18,2) DEFAULT NULL COMMENT 'بعد الاعتماد: السقف النقدي',
  `currency` varchar(8) DEFAULT NULL,
  `effect` enum('grant','deny') NOT NULL DEFAULT 'grant',
  `derived_from` varchar(40) DEFAULT NULL COMMENT 'baseline4 = اشتقاق الرايات الأربع · manual',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tp_dim` (`tp_id`,`action_code`,`scope_code`),
  KEY `ix_tpd_action` (`action_code`),
  KEY `fk_tpd_scope` (`scope_code`),
  CONSTRAINT `fk_tpd_action` FOREIGN KEY (`action_code`) REFERENCES `sec_actions` (`action_code`),
  CONSTRAINT `fk_tpd_scope` FOREIGN KEY (`scope_code`) REFERENCES `sec_scopes` (`scope_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-013: البعد الرباعي لكل بند قالب — يُشتق baseline ويُنقح يدويًّا';

-- ── Table: template_permissions ──
CREATE TABLE `template_permissions` (
  `tp_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_version_id` int(10) unsigned NOT NULL COMMENT 'FK للنسخة لا للقالب — فمحتوى القديمة لا يتغير عند نشر جديدة',
  `dimension` enum('visibility','action','approval','scope') NOT NULL,
  `permission_code` varchar(120) NOT NULL,
  `scope_rule` varchar(120) DEFAULT NULL,
  `amount_cap` decimal(18,2) DEFAULT NULL,
  `currency` varchar(8) DEFAULT NULL,
  `effect` enum('grant','deny') NOT NULL DEFAULT 'grant',
  PRIMARY KEY (`tp_id`),
  KEY `idx_tp_ver` (`template_version_id`,`dimension`),
  CONSTRAINT `fk_tp_ver` FOREIGN KEY (`template_version_id`) REFERENCES `permission_template_versions` (`ver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEC-01 §12: الأبعاد الأربعة — وdeny يغلب grant دائمًا';

-- ── Table: tenants ──
CREATE TABLE `tenants` (
  `tenant_id` int(10) unsigned NOT NULL COMMENT '= company_id القائم (حد العزل) — يقابل entity_id للمستأجرة وحدها',
  `entity_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tenant_id`),
  UNIQUE KEY `uq_tenants_entity` (`entity_id`),
  CONSTRAINT `fk_tenants_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LEG-01 §2-②-ب: حد العزل يُقرأ من هنا حصرًا — ولا يُشتق من أي صفة أخرى';

-- ── Table: tenders ──
CREATE TABLE `tenders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `tender_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `authority_id` int(11) DEFAULT NULL,
  `opportunity_id` int(10) unsigned DEFAULT NULL,
  `closing_date` date DEFAULT NULL,
  `participation_state` enum('إعداد','مقدمة','مسحوبة') NOT NULL DEFAULT 'إعداد',
  `result` enum('قيد التقييم','فوز','خسارة','إلغاء') NOT NULL DEFAULT 'قيد التقييم',
  `result_reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenders_company_code` (`company_id`,`tender_code`),
  KEY `idx_tender_scope` (`company_id`,`is_deleted`),
  KEY `idx_tender_opp` (`opportunity_id`),
  KEY `idx_tender_state` (`participation_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_attachments ──
CREATE TABLE `ticket_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `ticket_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('photo','signature','document') NOT NULL DEFAULT 'photo',
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `captured_at` datetime DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_ticket` (`company_id`,`ticket_id`),
  KEY `fk_at_ticket` (`ticket_id`),
  CONSTRAINT `fk_at_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_categories ──
CREATE TABLE `ticket_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(80) NOT NULL,
  `applies_to` varchar(40) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `failure_main_code` varchar(20) DEFAULT NULL COMMENT 'M-31: وصلةُ التصنيف الموحد — main_category_code؛ NULL = موروثٌ بلا مقابلٍ يُعلَن',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_code` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_communications ──
CREATE TABLE `ticket_communications` (
  `cm_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tk_id` int(10) unsigned NOT NULL,
  `person_id` int(11) NOT NULL,
  `channel` enum('system','phone','field') NOT NULL DEFAULT 'system',
  `note` varchar(255) NOT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من tickets.tk_id',
  PRIMARY KEY (`cm_id`),
  KEY `idx_tc_ticket` (`tk_id`,`at`),
  KEY `ix_tkcm_co` (`company_id`),
  CONSTRAINT `fk_tktc_ticket` FOREIGN KEY (`tk_id`) REFERENCES `tickets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تواصل مركز البلاغات يسجَّل فيبقى أثره (§10-③)';

-- ── Table: ticket_effects ──
CREATE TABLE `ticket_effects` (
  `lnk_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ws_id` int(10) unsigned NOT NULL,
  `effect_type` enum('inspection_request','work_order','issue_request','purchase_request','stoppage_attribution','decision','reply','acknowledge','info_added','no_action') NOT NULL,
  `effect_ref` varchar(120) NOT NULL,
  `is_provisional` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'للإسناد قبل اعتماد الأثر — الخطوات الأربع §7',
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من ticket_workstreams.ws_id',
  PRIMARY KEY (`lnk_id`),
  KEY `idx_te_ws` (`ws_id`),
  KEY `ix_tkef_co` (`company_id`),
  CONSTRAINT `fk_tkte_ws` FOREIGN KEY (`ws_id`) REFERENCES `ticket_workstreams` (`ws_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ولا يُغلق مسار بلا سطر هنا (عدا الإغلاق الإداري)';

-- ── Table: ticket_escalation_rules ──
CREATE TABLE `ticket_escalation_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `level_no` tinyint(4) NOT NULL,
  `escalate_after_hours` decimal(6,2) NOT NULL,
  `escalate_to_role` enum('responsible','dept_head','dept_manager','ops_manager','top_mgmt') NOT NULL,
  `notify_channel` enum('in_app','email','both') NOT NULL DEFAULT 'in_app',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_level` (`company_id`,`level_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_escalations ──
CREATE TABLE `ticket_escalations` (
  `esc_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ws_id` int(10) unsigned NOT NULL,
  `level` enum('mgr','ops_mgr','exec') NOT NULL,
  `triggered_by` enum('sla_breach','reopen_threshold','safety','hold_overdue') NOT NULL,
  `to_person_id` int(11) DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من ticket_workstreams.ws_id',
  PRIMARY KEY (`esc_id`),
  KEY `idx_esc_ws` (`ws_id`,`at`),
  KEY `ix_tkesc_co` (`company_id`),
  CONSTRAINT `fk_esc_ws` FOREIGN KEY (`ws_id`) REFERENCES `ticket_workstreams` (`ws_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Insert-only — ولا تصعيد يدوي يسجَّل هنا (§6: آلي لا بطلب)';

-- ── Table: ticket_events ──
CREATE TABLE `ticket_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `ticket_id` int(10) unsigned NOT NULL,
  `event_type` enum('note','communication','status_change','transfer','escalation','attachment','reminder','system') NOT NULL DEFAULT 'note',
  `actor_user_id` int(10) unsigned DEFAULT NULL,
  `actor_role_id` int(10) unsigned DEFAULT NULL,
  `body` text DEFAULT NULL,
  `old_value` varchar(60) DEFAULT NULL,
  `new_value` varchar(60) DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_ticket_time` (`company_id`,`ticket_id`,`created_at`),
  KEY `fk_ev_ticket` (`ticket_id`),
  CONSTRAINT `fk_ev_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_holds ──
CREATE TABLE `ticket_holds` (
  `hold_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ws_id` int(10) unsigned NOT NULL COMMENT 'على المسار لا الرأس — فالمهلة تتوقف لمسار ولا توقف الباقي',
  `reason_code` enum('awaiting_part','awaiting_approval','awaiting_technician','awaiting_reporter','awaiting_external') NOT NULL COMMENT 'قائمة محكومة لا نص حر — وإلا صار التعليق بابًا للتهرب',
  `expected_until` datetime NOT NULL COMMENT 'ولا تعليق بلا مدة متوقعة — وتجاوزها يصعد التعليق نفسه',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من ticket_workstreams.ws_id',
  PRIMARY KEY (`hold_id`),
  KEY `idx_holds_open` (`ws_id`,`ended_at`),
  KEY `ix_tkhl_co` (`company_id`),
  CONSTRAINT `fk_hold_ws` FOREIGN KEY (`ws_id`) REFERENCES `ticket_workstreams` (`ws_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_participants ──
CREATE TABLE `ticket_participants` (
  `p_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tk_id` int(10) unsigned NOT NULL,
  `person_id` int(11) NOT NULL,
  `role` enum('reporter','assignee','watcher','duplicate_reporter') NOT NULL,
  `added_at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من tickets.tk_id',
  PRIMARY KEY (`p_id`),
  UNIQUE KEY `uq_tp` (`tk_id`,`person_id`,`role`),
  KEY `ix_tkpp_co` (`company_id`),
  CONSTRAINT `fk_tp_ticket` FOREIGN KEY (`tk_id`) REFERENCES `tickets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ومبلغ المكرر يضاف متابعًا للأصل فلا يُفقد أنه أبلغ (§9)';

-- ── Table: ticket_recurrence_templates ──
CREATE TABLE `ticket_recurrence_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `ticket_type_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `equipment_id` int(10) unsigned DEFAULT NULL,
  `recurrence_interval` int(11) NOT NULL DEFAULT 1,
  `recurrence_unit` enum('day','week','month','year') NOT NULL,
  `next_occurrence_date` date NOT NULL,
  `lead_time_days` int(11) NOT NULL DEFAULT 0,
  `default_owner_role_id` int(10) unsigned DEFAULT NULL,
  `default_priority` enum('normal','high','critical') NOT NULL DEFAULT 'normal',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_next` (`company_id`,`active`,`next_occurrence_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_responses ──
CREATE TABLE `ticket_responses` (
  `rd_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tk_id` int(10) unsigned NOT NULL,
  `ws_id` int(10) unsigned DEFAULT NULL COMMENT 'إلزامي لردود المسار وفارغ للرد المركزي على الرأس',
  `person_id` int(11) NOT NULL,
  `response_type` enum('reply','acknowledge','info_added','no_action_decision') NOT NULL,
  `body` text DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من tickets.tk_id',
  PRIMARY KEY (`rd_id`),
  KEY `idx_tr_ticket` (`tk_id`,`at`),
  KEY `ix_tkrd_co` (`company_id`),
  CONSTRAINT `fk_tktr_ticket` FOREIGN KEY (`tk_id`) REFERENCES `tickets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_sla_policies ──
CREATE TABLE `ticket_sla_policies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `ticket_type_id` int(10) unsigned DEFAULT NULL,
  `priority` enum('normal','high','critical') DEFAULT NULL,
  `business_impact` enum('production_critical','revenue','safety','admin') DEFAULT NULL,
  `response_hours` decimal(6,2) NOT NULL,
  `resolution_hours` decimal(6,2) NOT NULL,
  `remind_before_hours` decimal(6,2) DEFAULT NULL,
  `escalation_rule_id` int(10) unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_match` (`company_id`,`ticket_type_id`,`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_transfers ──
CREATE TABLE `ticket_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `ticket_id` int(10) unsigned NOT NULL,
  `from_role_id` int(10) unsigned NOT NULL,
  `to_role_id` int(10) unsigned NOT NULL,
  `from_user_id` int(10) unsigned DEFAULT NULL,
  `to_user_id` int(10) unsigned DEFAULT NULL,
  `transfer_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `transferred_by` int(10) unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `notes` text DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ticket` (`company_id`,`ticket_id`),
  KEY `fk_tr_ticket` (`ticket_id`),
  CONSTRAINT `fk_tr_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_type_workstreams ──
CREATE TABLE `ticket_type_workstreams` (
  `ws_def_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_type_id` int(10) unsigned NOT NULL,
  `workstream_type` varchar(40) NOT NULL COMMENT 'maintenance·movement·operators·warehouse·procurement·hr·governance·support…',
  `seq_no` int(11) NOT NULL DEFAULT 1,
  `target_org_unit_code` varchar(40) DEFAULT NULL COMMENT 'org_units.unit_code — والمكلف يُحل من ORG-01 لا من شخص ثابت',
  `target_role` varchar(60) DEFAULT NULL COMMENT 'دور الحل في PermitGate/TicketRouter (movement·maintenance·…)',
  `mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `activation_mode` enum('immediate','conditional') NOT NULL DEFAULT 'immediate',
  `trigger_event` varchar(60) DEFAULT NULL COMMENT 'مثال StockUnavailable — الشرطي يفتح بوقوعه لا بالإنشاء',
  `depends_on_workstream_type` varchar(40) DEFAULT NULL,
  `response_sla_minutes` int(11) DEFAULT NULL,
  `resolve_sla_minutes` int(11) DEFAULT NULL,
  `sla_clock` enum('absolute','business') NOT NULL DEFAULT 'absolute' COMMENT '§6: الحرج مطلق وما دونه بساعات العمل',
  PRIMARY KEY (`ws_def_id`),
  UNIQUE KEY `uq_ttws` (`ticket_type_id`,`workstream_type`,`seq_no`),
  CONSTRAINT `fk_ttws_type` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TKT-01 §12: فمسار المشتريات يُفتح عند إعلان نفاد القطعة لا عند إنشاء البلاغ';

-- ── Table: ticket_types ──
CREATE TABLE `ticket_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `owner_role_id` int(10) unsigned NOT NULL,
  `default_nature` enum('request','incident','recurring') NOT NULL DEFAULT 'request',
  `nature` enum('incident','problem','request','complaint','information','risk','emergency','suggestion') DEFAULT NULL COMMENT 'TKT-01 §3: الطبيعة غير المجال — تحدد الدورة والسرية والإغلاق',
  `category` varchar(40) DEFAULT NULL COMMENT '§4: المجال — منه تشتق الإدارة المختصة',
  `default_confidentiality` enum('normal','protected','secret') NOT NULL DEFAULT 'normal',
  `closure_policy` enum('reporter_confirm','owner_approve','auto','admin_only','committee') NOT NULL DEFAULT 'reporter_confirm' COMMENT '§5-⑥: ولا إغلاق آلي للسلامة والحوادث وشكاوى العاملين',
  `allow_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `default_priority` enum('normal','high','critical') NOT NULL DEFAULT 'normal',
  `ref_table` varchar(40) DEFAULT NULL,
  `default_sla_id` int(10) unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_code` (`company_id`,`code`),
  KEY `ix_owner_role` (`owner_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_watchers ──
CREATE TABLE `ticket_watchers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `ticket_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned DEFAULT NULL,
  `watch_reason` enum('reporter','owner','manager','subscribed') NOT NULL DEFAULT 'subscribed',
  `notify` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_watch` (`company_id`,`ticket_id`,`user_id`),
  KEY `fk_wt_ticket` (`ticket_id`),
  CONSTRAINT `fk_wt_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: ticket_workstreams ──
CREATE TABLE `ticket_workstreams` (
  `ws_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tk_id` int(10) unsigned NOT NULL,
  `workstream_type` varchar(40) NOT NULL,
  `seq_no` int(11) NOT NULL DEFAULT 1,
  `org_unit_id` int(10) unsigned DEFAULT NULL,
  `assignee_person_id` int(11) DEFAULT NULL COMMENT 'يُحل من تكليفات ORG-01 النافذة لا من جدول النوع',
  `mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `state` enum('new','received','in_progress','on_hold','done_pending','closed','reopened','admin_closed') NOT NULL DEFAULT 'new',
  `activation_state` enum('pending','opened','skipped') NOT NULL DEFAULT 'opened' COMMENT 'الشرطي pending حتى حدث تفعيله',
  `response_due_at` datetime DEFAULT NULL,
  `resolve_due_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `reopen_count` int(11) NOT NULL DEFAULT 0 COMMENT 'ظاهر — وثلاث إعادات ترفعه للمركز',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL COMMENT 'DEC-D ① — مشتق من tickets.tk_id',
  PRIMARY KEY (`ws_id`),
  UNIQUE KEY `uq_tws` (`tk_id`,`workstream_type`,`seq_no`),
  KEY `idx_tws_assignee` (`assignee_person_id`,`state`),
  KEY `idx_tws_due` (`state`,`response_due_at`),
  KEY `ix_tkws_co` (`company_id`),
  CONSTRAINT `fk_tws_ticket` FOREIGN KEY (`tk_id`) REFERENCES `tickets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TKT-01 §12: UQ على (البلاغ×نوع المسار×التسلسل) — فللإدارة الواحدة مساران مختلفان';

-- ── Table: tickets ──
CREATE TABLE `tickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `ticket_no` varchar(20) NOT NULL,
  `ticket_type_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `stage` enum('new','classified','routed','in_progress','waiting','follow_up','done','closed','cancelled') NOT NULL DEFAULT 'new',
  `head_state` enum('open','closed') NOT NULL DEFAULT 'open' COMMENT 'ذاكرة مشتقة لا مصدر حقيقة — لا يكتبها إلا معيد الحساب (TicketStateService)',
  `ticket_nature` enum('request','incident','recurring') NOT NULL,
  `priority` enum('normal','high','critical') NOT NULL DEFAULT 'normal',
  `confidentiality` enum('normal','protected','secret') NOT NULL DEFAULT 'normal',
  `business_impact` enum('production_critical','revenue','safety','admin') NOT NULL DEFAULT 'admin',
  `production_critical` tinyint(1) NOT NULL DEFAULT 0,
  `project_weight` enum('strategic','main','normal') DEFAULT NULL,
  `call_date` date NOT NULL,
  `call_time` varchar(10) DEFAULT NULL,
  `reporting_person` varchar(120) NOT NULL,
  `reporter_contact` varchar(40) DEFAULT NULL,
  `reporter_entity_id` int(10) unsigned DEFAULT NULL,
  `reporter_user_id` int(10) unsigned DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0 COMMENT '§8-④: الهوية محفوظة للحوكمة',
  `project_id` int(10) unsigned DEFAULT NULL,
  `site_id` int(11) DEFAULT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `shift_no` int(11) DEFAULT NULL,
  `period_no` int(11) DEFAULT NULL,
  `equipment_id` int(10) unsigned DEFAULT NULL,
  `machine_type` varchar(40) DEFAULT NULL,
  `machine_condition` enum('running','stopped') DEFAULT NULL,
  `meter_reading` decimal(12,2) DEFAULT NULL,
  `complaint` text NOT NULL,
  `operational_summary` varchar(255) DEFAULT NULL COMMENT 'يراه الجميع — الفصل البنيوي §8',
  `private_details` text DEFAULT NULL COMMENT 'خلف ConfidentialityGuard — لا يُجلب بلا صلاحية',
  `source_screen` varchar(120) DEFAULT NULL COMMENT '§2: السياق محمول لا مُدخل',
  `source_entity_type` varchar(40) DEFAULT NULL,
  `source_entity_id` bigint(20) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `helper_id` int(10) unsigned DEFAULT NULL,
  `shift` enum('morning','evening') DEFAULT NULL,
  `owner_role_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `service_team` enum('internal','external_workshop') DEFAULT NULL,
  `issue_status` text DEFAULT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `duplicate_of_ticket_id` int(10) unsigned DEFAULT NULL,
  `related_ticket_id` int(10) unsigned DEFAULT NULL,
  `recurrence_group_id` int(10) unsigned DEFAULT NULL,
  `is_parent` tinyint(1) NOT NULL DEFAULT 0,
  `ticket_role` enum('parent','child','standalone') NOT NULL DEFAULT 'standalone',
  `sla_policy_id` int(10) unsigned DEFAULT NULL,
  `first_action_at` datetime DEFAULT NULL,
  `response_due_at` datetime DEFAULT NULL,
  `resolution_due_at` datetime DEFAULT NULL,
  `close_date` date DEFAULT NULL,
  `close_time` varchar(10) DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `recurrence_template_id` int(10) unsigned DEFAULT NULL,
  `linked_ref_table` varchar(40) DEFAULT NULL,
  `linked_ref_id` int(10) unsigned DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `escalation_level` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'E-14: أعلى مستوًى صُعّد إليه — كان المفتاحُ يوميًّا فيتكرر غدًا',
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
  CONSTRAINT `fk_tk_parent` FOREIGN KEY (`parent_id`) REFERENCES `tickets` (`id`),
  CONSTRAINT `fk_tk_sla` FOREIGN KEY (`sla_policy_id`) REFERENCES `ticket_sla_policies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tk_type` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: timesheet ──
CREATE TABLE `timesheet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `operator` varchar(20) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `shift` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `shift_hours` float DEFAULT 0,
  `executed_hours` float DEFAULT 0,
  `bucket_hours` float DEFAULT 0,
  `jackhammer_hours` float DEFAULT 0,
  `extra_hours` float DEFAULT 0,
  `extra_hours_total` float DEFAULT 0,
  `standby_hours` float DEFAULT 0,
  `dependence_hours` float DEFAULT 0,
  `total_work_hours` float DEFAULT 0,
  `work_notes` mediumtext DEFAULT NULL,
  `hr_fault` float DEFAULT 0,
  `maintenance_fault` float DEFAULT 0,
  `marketing_fault` float DEFAULT 0,
  `approval_fault` float DEFAULT 0,
  `other_fault_hours` float DEFAULT 0,
  `ts_supplier_stop_hours` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'D02 §3.5 ⑤ توقف على المورد — لا مستحقَ له وقد ينقلب خصمًا',
  `ts_planned_stop_hours` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'D02 §3.5 ⑭ توقف مخطط — يحسمه جدول سياسة العقد',
  `ts_force_majeure_hours` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'D02 §3.5 ⑮ قوة قاهرة — تسويةٌ حالةً بحالة وفق بند العقد',
  `total_fault_hours` float DEFAULT 0,
  `fault_notes` mediumtext DEFAULT NULL,
  `start_seconds` int(11) DEFAULT 0,
  `start_minutes` int(11) DEFAULT 0,
  `start_hours` int(11) DEFAULT 0,
  `end_seconds` int(11) DEFAULT 0,
  `end_minutes` int(11) DEFAULT 0,
  `end_hours` int(11) DEFAULT 0,
  `counter_diff` varchar(255) DEFAULT '0',
  `fault_type` varchar(255) DEFAULT NULL,
  `fault_department` varchar(255) DEFAULT NULL,
  `fault_part` varchar(255) DEFAULT NULL,
  `fault_details` mediumtext DEFAULT NULL,
  `general_notes` mediumtext DEFAULT NULL,
  `operator_hours` float DEFAULT 0,
  `machine_standby_hours` float DEFAULT 0,
  `jackhammer_standby_hours` float DEFAULT 0,
  `bucket_standby_hours` float DEFAULT 0,
  `extra_operator_hours` float DEFAULT 0,
  `operator_standby_hours` float DEFAULT 0,
  `operator_notes` mediumtext DEFAULT NULL,
  `tons_count` decimal(10,2) DEFAULT 0.00 COMMENT 'عدد الأطنان - للنوع 2 (القلاب)',
  `trips_count` int(11) DEFAULT 0 COMMENT 'عدد النقلات - للنوع 2 (القلاب)',
  `transport_type` varchar(50) DEFAULT NULL,
  `meters_type` varchar(50) DEFAULT NULL COMMENT 'نوع الأمتار - للنوع 3 (الخرمات)',
  `meters_count` decimal(10,2) DEFAULT 0.00 COMMENT 'عدد الأمتار - للنوع 3 (الخرمات)',
  `drilling_holes_count` int(11) DEFAULT 0,
  `drilling_depth` decimal(10,2) DEFAULT 0.00,
  `type` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `time_notes` mediumtext NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `client_uuid` varchar(64) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_timesheet_client_uuid` (`client_uuid`),
  KEY `idx_timesheet_updated_at` (`updated_at`),
  KEY `idx_timesheet_date` (`date`),
  KEY `idx_timesheet_operator` (`operator`),
  KEY `idx_timesheet_date_id` (`date`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: timesheet_approval_notes ──
CREATE TABLE `timesheet_approval_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timesheet_id` int(11) NOT NULL COMMENT 'FK → timesheet.id',
  `company_id` int(11) DEFAULT NULL,
  `column_name` varchar(100) NOT NULL COMMENT 'اسم العمود التقني',
  `column_label` varchar(255) NOT NULL COMMENT 'عنوان العمود بالعربية',
  `note_text` mediumtext NOT NULL,
  `created_by` int(11) NOT NULL COMMENT 'FK → users.id',
  `created_by_name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_ts_id` (`timesheet_id`),
  KEY `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ملاحظات اعتماد ساعات العمل';

-- ── Table: timesheet_approvals ──
CREATE TABLE `timesheet_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timesheet_id` int(11) NOT NULL COMMENT 'FK → timesheet.id',
  `company_id` int(11) DEFAULT NULL,
  `approval_level` tinyint(1) NOT NULL COMMENT '1..4',
  `approved_by` int(11) NOT NULL COMMENT 'FK → users.id',
  `approved_by_name` varchar(255) NOT NULL,
  `approved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=اعتمد, 0=رُفض',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ts_level` (`timesheet_id`,`approval_level`),
  KEY `idx_ts_id` (`timesheet_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_level` (`approval_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='اعتمادات ساعات العمل الهرمية';

-- ── Table: timesheet_failure_hours ──
CREATE TABLE `timesheet_failure_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timesheet_id` int(11) NOT NULL,
  `operation_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL DEFAULT 0,
  `failure_code_id` int(11) NOT NULL,
  `equipment_type` tinyint(1) NOT NULL COMMENT '1=حفار,2=قلاب,3=خرامة',
  `event_type_code` varchar(20) NOT NULL,
  `event_type_name` varchar(150) NOT NULL,
  `main_category_code` varchar(20) NOT NULL,
  `main_category_name` varchar(200) NOT NULL,
  `sub_category` varchar(200) NOT NULL,
  `failure_detail` varchar(255) NOT NULL,
  `full_code` varchar(50) NOT NULL,
  `timesheet_date` date NOT NULL,
  `company_id` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `ticket_id` int(10) unsigned DEFAULT NULL,
  `notif_type` enum('due_soon','overdue','escalation','recurring_created') NOT NULL,
  `target_role` int(10) unsigned DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `body` varchar(255) DEFAULT NULL,
  `link_url` varchar(160) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `dedupe_key` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dedupe` (`company_id`,`dedupe_key`),
  KEY `ix_company_read` (`company_id`,`is_read`),
  KEY `ix_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_attachments ──
CREATE TABLE `transfer_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('departure_proof','arrival_proof','permit','signature','photo') NOT NULL DEFAULT 'photo',
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `captured_at` datetime DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_order` (`company_id`,`order_id`),
  KEY `fk_at_order` (`order_id`),
  CONSTRAINT `fk_at_order` FOREIGN KEY (`order_id`) REFERENCES `transfer_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_cost_lines ──
CREATE TABLE `transfer_cost_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned NOT NULL,
  `cost_type` enum('fuel','labor','contractor','misc','permit') NOT NULL,
  `amount_local` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL DEFAULT 'SDG',
  `fx_rate` decimal(14,6) DEFAULT NULL,
  `amount_usd` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cost_bearer` enum('client','company','new_client') NOT NULL,
  `analytic_cost_center` varchar(60) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_order` (`company_id`,`order_id`),
  KEY `fk_cl_order` (`order_id`),
  CONSTRAINT `fk_cl_order` FOREIGN KEY (`order_id`) REFERENCES `transfer_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_cost_rules ──
CREATE TABLE `transfer_cost_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `movement_type` enum('mob','demob','direct','internal','admin','spare_parts','travel') NOT NULL,
  `duration_operator` enum('any','lt','gte') NOT NULL DEFAULT 'any',
  `duration_threshold_days` int(11) DEFAULT NULL,
  `default_bearer` enum('client','company','new_client') NOT NULL,
  `basis_note` varchar(120) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_rule` (`company_id`,`movement_type`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_delivery_docs ──
CREATE TABLE `transfer_delivery_docs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `doc_ref` varchar(64) NOT NULL,
  `doc_note` varchar(500) NOT NULL DEFAULT '',
  `witness_name` varchar(160) NOT NULL DEFAULT '',
  `delivered_at` datetime NOT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tdd_order` (`company_id`,`order_id`),
  UNIQUE KEY `uq_tdd_ref` (`doc_ref`),
  KEY `idx_tdd_time` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FN-08 · مستندُ تسليمِ أمرِ الترحيل — مرجعٌ ووقتٌ وشاهد';

-- ── Table: transfer_events ──
CREATE TABLE `transfer_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned NOT NULL,
  `event_type` enum('note','communication','status_change','alert','attachment','system') NOT NULL DEFAULT 'note',
  `actor_user_id` int(10) unsigned DEFAULT NULL,
  `actor_dept` varchar(20) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `old_value` varchar(60) DEFAULT NULL,
  `new_value` varchar(60) DEFAULT NULL,
  `sync_uuid` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_te_sync_uuid` (`sync_uuid`),
  KEY `ix_order_time` (`company_id`,`order_id`,`created_at`),
  KEY `fk_ev_order` (`order_id`),
  CONSTRAINT `fk_ev_order` FOREIGN KEY (`order_id`) REFERENCES `transfer_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_lines ──
CREATE TABLE `transfer_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned NOT NULL,
  `item_type` enum('equipment','attachment','material','person') NOT NULL,
  `equipment_id` int(10) unsigned DEFAULT NULL,
  `attachment_ref` varchar(80) DEFAULT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `employee_id` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(12,2) DEFAULT NULL,
  `note` varchar(200) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_order` (`company_id`,`order_id`),
  KEY `fk_ln_order` (`order_id`),
  CONSTRAINT `fk_ln_order` FOREIGN KEY (`order_id`) REFERENCES `transfer_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_orders ──
CREATE TABLE `transfer_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `order_no` varchar(40) NOT NULL,
  `request_id` int(10) unsigned DEFAULT NULL,
  `transfer_type_id` int(10) unsigned NOT NULL,
  `direction` enum('mob','demob','direct','internal') NOT NULL,
  `source_module` enum('operations','fleet','maintenance','workforce','procurement') NOT NULL,
  `requested_by_user_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `from_location_id` int(10) unsigned NOT NULL,
  `to_location_id` int(10) unsigned NOT NULL,
  `request_date` date NOT NULL,
  `planned_date` date DEFAULT NULL,
  `departure_datetime` datetime DEFAULT NULL,
  `arrival_datetime` datetime DEFAULT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `carrier_type` enum('internal','contractor') DEFAULT NULL,
  `carrier_entity_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `route` varchar(200) DEFAULT NULL,
  `estimated_cost_usd` decimal(12,2) DEFAULT NULL,
  `actual_cost_usd` decimal(12,2) DEFAULT NULL,
  `cost_bearer` enum('client','company','new_client') DEFAULT NULL,
  `charge_supplier_id` int(10) unsigned DEFAULT NULL COMMENT 'المورد الذي يُحمَّل بتعرفة هذا الأمر (ENT-02 §3-④) — NULL = لا تحميلَ على مورد',
  `tariff_id` int(10) unsigned DEFAULT NULL COMMENT 'التعرفةُ التي سُعّر بها — «المبلغُ يُقرأ من مصدره»',
  `tariff_amount` decimal(18,2) DEFAULT NULL COMMENT '**محسوبٌ لا مُدخَل**: كميةُ نموذج التعرفة × معدلها مقصوصةً بحدَّيها',
  `tariff_currency` varchar(8) DEFAULT NULL,
  `tariff_note` varchar(255) DEFAULT NULL COMMENT 'بيانُ الاحتساب: النموذجُ والكميةُ والمعدل وقصُّ الحدّ إن وقع',
  `distance_km` decimal(12,2) DEFAULT NULL COMMENT 'مسافةُ المسار — لازمةٌ لنموذج per_km وبلا قيمةٍ لا تسعير',
  `priced_at` datetime DEFAULT NULL,
  `priced_by` int(10) unsigned DEFAULT NULL,
  `analytic_cost_center` varchar(60) DEFAULT NULL,
  `project_days` int(11) DEFAULT NULL,
  `priority` enum('normal','urgent') NOT NULL DEFAULT 'normal',
  `stage` enum('request','planned','ready','in_transit','arrived','closed','cancelled') NOT NULL DEFAULT 'request',
  `notes` text DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
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
  CONSTRAINT `ck_order_tariff_source` CHECK (`tariff_amount` is null or `tariff_id` is not null and `tariff_currency` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_permits ──
CREATE TABLE `transfer_permits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned NOT NULL,
  `permit_type` enum('route','load','transit','safety') NOT NULL,
  `authority` varchar(120) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `state` enum('valid','expired','issuing') NOT NULL DEFAULT 'issuing',
  `document_path` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_order` (`company_id`,`order_id`),
  KEY `ix_expiry` (`company_id`,`expiry_date`),
  KEY `fk_pm_order` (`order_id`),
  CONSTRAINT `fk_pm_order` FOREIGN KEY (`order_id`) REFERENCES `transfer_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_requests ──
CREATE TABLE `transfer_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `transfer_type_id` int(10) unsigned NOT NULL,
  `source_module` enum('operations','fleet','maintenance','workforce','procurement') NOT NULL,
  `requested_by_user_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `from_location_id` int(10) unsigned DEFAULT NULL,
  `to_location_id` int(10) unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `priority` enum('normal','urgent') NOT NULL DEFAULT 'normal',
  `state` enum('submitted','approved','converted','rejected') NOT NULL DEFAULT 'submitted',
  `order_id` int(10) unsigned DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_req_code` (`company_id`,`code`),
  KEY `ix_state` (`company_id`,`state`),
  KEY `fk_rq_type` (`transfer_type_id`),
  CONSTRAINT `fk_rq_type` FOREIGN KEY (`transfer_type_id`) REFERENCES `transfer_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_tariffs ──
CREATE TABLE `transfer_tariffs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL COMMENT 'المورد المحمَّل — NULL = تعرفةٌ لا تخصُّ موردًا بعينه (الأعمّ)',
  `transfer_type_id` int(10) unsigned DEFAULT NULL COMMENT 'نوعُ الترحيل — NULL = أي نوع',
  `from_location_id` int(10) unsigned DEFAULT NULL COMMENT 'مبدأُ المسار — NULL = أي مبدأ',
  `to_location_id` int(10) unsigned DEFAULT NULL COMMENT 'منتهاه — NULL = أي منتهى',
  `pricing_model` enum('per_trip','per_km','per_ton','per_equipment') NOT NULL COMMENT 'نموذجُ التسعير — والكميةُ تُقرأ من الأمر بحسبه',
  `rate` decimal(14,4) NOT NULL COMMENT 'معدلُ الوحدة — عمودٌ مستقلٌّ بدقّته (گوتشا M-15: pct(5,2) يبتر)',
  `currency` varchar(8) NOT NULL DEFAULT 'SDG' COMMENT 'لا جمعَ عملتين في رقم',
  `min_amount` decimal(18,2) DEFAULT NULL,
  `max_amount` decimal(18,2) DEFAULT NULL COMMENT 'سقفٌ يقصّ **ويُعلن قصَّه**',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL COMMENT 'NULL = مفتوحةُ الطرف',
  `state` enum('active','ended') NOT NULL DEFAULT 'active',
  `note` varchar(200) DEFAULT NULL COMMENT 'بندُ العقد أو مرجعُ التعرفة',
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transfer_tariff` (`company_id`,`supplier_id`,`transfer_type_id`,`from_location_id`,`to_location_id`,`pricing_model`,`effective_from`) COMMENT 'تعرفةٌ واحدةٌ لمفتاحها في تاريخها — والجديدُ بسريانٍ جديد',
  KEY `ix_tariff_lookup` (`company_id`,`state`,`effective_from`,`effective_to`),
  CONSTRAINT `ck_tariff_limits` CHECK (`min_amount` is null or `max_amount` is null or `min_amount` <= `max_amount`),
  CONSTRAINT `ck_tariff_rate` CHECK (`rate` > 0),
  CONSTRAINT `ck_tariff_span` CHECK (`effective_to` is null or `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: transfer_types ──
CREATE TABLE `transfer_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `operational_category` enum('equipment_transfer','parts_transfer','personnel_move','equipment_plus_move') NOT NULL,
  `default_bearer` enum('client','company','new_client','by_rule') NOT NULL DEFAULT 'by_rule',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_code` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: trs_locations ──
CREATE TABLE `trs_locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `location_type` enum('base','project','workshop','office') NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loc_code` (`company_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: trs_notifications ──
CREATE TABLE `trs_notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `notif_type` enum('delayed','no_arrival','permit_expiry','sixty_day') NOT NULL,
  `target_role` int(10) unsigned DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `body` varchar(255) DEFAULT NULL,
  `link_url` varchar(160) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `dedupe_key` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dedupe` (`company_id`,`dedupe_key`),
  KEY `ix_company_read` (`company_id`,`is_read`),
  KEY `ix_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: uat_evidence ──
CREATE TABLE `uat_evidence` (
  `ev_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` int(10) unsigned NOT NULL,
  `criterion` varchar(120) NOT NULL COMMENT 'رمز المعيار: H1..H6 · S1.. · الشواهد الأربعة عشر',
  `expected` varchar(255) DEFAULT NULL,
  `actual` varchar(255) DEFAULT NULL,
  `result` enum('pass','fail','na') NOT NULL DEFAULT 'na',
  `evidence_ref` varchar(255) DEFAULT NULL COMMENT 'لقطة أو مرجع سجل',
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ev_id`),
  KEY `idx_uatev_run` (`run_id`,`criterion`),
  CONSTRAINT `fk_uatev_run` FOREIGN KEY (`run_id`) REFERENCES `uat_runs` (`run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='UAT-14: الشواهد الأربعة عشر — موثقة كلها';

-- ── Table: uat_runs ──
CREATE TABLE `uat_runs` (
  `run_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `tag` varchar(20) NOT NULL DEFAULT 'UAT-2026' COMMENT 'وسم التمييز — للتقارير لا للحذف',
  `phase` enum('hardening','functional','break','close','load','decision') NOT NULL,
  `title` varchar(190) NOT NULL,
  `state` enum('planned','running','passed','failed','blocked') NOT NULL DEFAULT 'planned',
  `executor` varchar(120) DEFAULT NULL COMMENT '§12.1: مستخدمو الإدارات — والفريق يراقب ويوثق',
  `metrics_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metrics_json`)),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`run_id`),
  KEY `idx_uat_phase` (`company_id`,`phase`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='UAT-01: جولات التجربة — التحصين قبل كل تجربة';

-- ── Table: unit_approvals ──
CREATE TABLE `unit_approvals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `entry_id` int(10) unsigned NOT NULL COMMENT 'unit_entries',
  `round_no` smallint(5) unsigned NOT NULL DEFAULT 1 COMMENT 'جولة السلسلة — كل إعادةٍ تفتح جولةً جديدة (UX-03 §8.2)',
  `stage` enum('site','supplier','operator','supervisor','fleet','sales','finance') NOT NULL,
  `decision` enum('approved','returned','rejected') NOT NULL,
  `actor_id` int(10) unsigned NOT NULL COMMENT 'users',
  `note` varchar(200) DEFAULT NULL,
  `decided_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sync_uuid` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stage_once_per_round` (`company_id`,`entry_id`,`round_no`,`stage`) COMMENT 'قرارٌ واحدٌ لكل مرحلةٍ في الجولة',
  KEY `ix_entry` (`company_id`,`entry_id`),
  KEY `ix_stage` (`company_id`,`stage`,`decided_at`),
  KEY `fk_ua_entry` (`entry_id`),
  CONSTRAINT `fk_ua_entry` FOREIGN KEY (`entry_id`) REFERENCES `unit_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D02 §4.2 — سلسلة الاعتماد الخماسية: سطرٌ إلحاقيٌّ لكل قرار';

-- ── Table: unit_capacity_flags ──
CREATE TABLE `unit_capacity_flags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `entry_id` int(10) unsigned NOT NULL COMMENT 'الواقعة التي رُفع عليها العلم',
  `subject` enum('equipment','operator') NOT NULL,
  `subject_ref` int(10) unsigned NOT NULL COMMENT 'معرّف المعدة أو الموظف',
  `flag_date` date NOT NULL,
  `measured_hours` decimal(8,2) NOT NULL COMMENT 'مجموع اليوم المقيس شاملًا هذه الواقعة',
  `capacity_hours` decimal(8,2) NOT NULL COMMENT 'الطاقة النافذة وقت الرفع — لقطةٌ لا مرجع',
  `overlap_found` tinyint(1) DEFAULT NULL COMMENT 'تداخلُ ورديات — نتيجة الفحص',
  `duplicate_found` tinyint(1) DEFAULT NULL COMMENT 'تكرارٌ مشتبهٌ به — نتيجة الفحص',
  `second_operator_present` tinyint(1) DEFAULT NULL COMMENT 'هل وُجد مشغّلٌ ثانٍ؟ (إعلانٌ صريح)',
  `cause_note` varchar(200) DEFAULT NULL COMMENT 'سبب التجاوز — إلزامٌ قبل التخليص',
  `cleared_by` int(10) unsigned DEFAULT NULL COMMENT 'المسؤول المعتمِد — users',
  `cleared_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_flag` (`company_id`,`entry_id`,`subject`),
  KEY `ix_open` (`company_id`,`cleared_at`),
  KEY `ix_subject` (`company_id`,`subject`,`subject_ref`,`flag_date`),
  KEY `fk_ucf_entry` (`entry_id`),
  CONSTRAINT `fk_ucf_entry` FOREIGN KEY (`entry_id`) REFERENCES `unit_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D02 §3.10 — أعلام تجاوز الطاقة وتخليصها: لا اعتمادَ موقعٍ قبل الحسم';

-- ── Table: unit_effects ──
CREATE TABLE `unit_effects` (
  `pe_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `source_unit_id` bigint(20) unsigned NOT NULL COMMENT 'الوحدة المصدر (fin_unit_records.id أو سجل الوحدة)',
  `domain` enum('sales','suppliers','workforce','fleet','financiers','maintenance') NOT NULL,
  `effect_kind` enum('production','container_consumption','hours','depreciation','charge','incentive_base') NOT NULL,
  `quantity` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `stage` enum('primary','financial') NOT NULL,
  `state` enum('Applied','Proposed','Approved','Posted','Reversed') NOT NULL,
  `period` char(7) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `fin_event_ref` bigint(20) unsigned DEFAULT NULL COMMENT 'حدث FES عند بوابة الاستحقاق — الخيط متصل ولا جدول مال ثانٍ',
  `note` varchar(200) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`pe_id`),
  UNIQUE KEY `uq_ue_effect` (`company_id`,`source_unit_id`,`domain`,`effect_kind`,`stage`),
  KEY `ix_ue_stage` (`company_id`,`stage`,`state`,`period`),
  CONSTRAINT `ck_ue_financial_posted` CHECK (`stage` <> _utf8mb4'financial' or `state` <> _utf8mb4'Posted' or `approved_by` is not null and `fin_event_ref` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: unit_entries ──
CREATE TABLE `unit_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `entry_no` varchar(30) NOT NULL COMMENT 'server-assigned — هويةٌ ثابتة',
  `entry_date` date NOT NULL,
  `project_id` int(10) unsigned NOT NULL COMMENT 'ops_projects (D01) — مرجعٌ مرن',
  `contract_id` int(10) unsigned DEFAULT NULL COMMENT 'sales contract (S05) — مرجعٌ مرن',
  `contract_line_id` int(10) unsigned DEFAULT NULL COMMENT 'بندُ البيع المنفَّذ (P-02) — NULL = غيرُ موصولٍ بعد',
  `plan_period_id` int(10) unsigned DEFAULT NULL COMMENT 'شهرُ الخطة (P-03) الذي تخصّه',
  `operational_site_id` int(10) unsigned DEFAULT NULL COMMENT 'نطاقُ العقد التشغيلي (P-01)',
  `equipment_id` int(10) unsigned DEFAULT NULL COMMENT 'fleet (S03) — مرجعٌ مرن',
  `operator_employee_id` int(10) unsigned DEFAULT NULL COMMENT 'employees (ADM) — مرجعٌ مرن',
  `supplier_entity_id` int(10) unsigned DEFAULT NULL COMMENT 'entities (S02) — مرجعٌ مرن',
  `supervisor_id` int(10) unsigned DEFAULT NULL COMMENT 'employees — عند وجود مشرف',
  `unit_type` enum('hour','ton','meter','cbm','day','shift','trip') NOT NULL COMMENT 'dictionary; finance-enabled by contract',
  `qty` decimal(14,2) NOT NULL COMMENT 'كمية الواقعة المسجّلة',
  `record_basis` enum('contract','analytical') NOT NULL DEFAULT 'contract',
  `capacity_flag` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'over daily capacity (§3.10)',
  `qty_billable` tinyint(1) DEFAULT NULL COMMENT 'M-24 ①: هل الكميةُ نفسُها مفوترةٌ للعميل؟ NULL=لم يُحكم (مفوترة) · 0=لا (إعادةُ تنفيذٍ لعيب) · 1=نعم صراحةً',
  `qty_ruling_note` varchar(200) DEFAULT NULL COMMENT 'سببُ الحكم — إلزامٌ عند qty_billable=0',
  `qty_decided_by` int(10) unsigned DEFAULT NULL COMMENT 'مَن حكم — الحكمُ باسم صاحبه',
  `qty_decided_at` datetime DEFAULT NULL,
  `shift` enum('day','night') DEFAULT NULL,
  `source_ref` varchar(60) DEFAULT NULL COMMENT 'field sheet / meter / weigh ticket',
  `txn_ref` varchar(40) DEFAULT NULL COMMENT 'غلاف العملية التشغيلية (§2.4)',
  `note` varchar(200) DEFAULT NULL,
  `state` enum('draft','submitted','site_approved','parties_review','parties_approved','sales_approved','on_hold','converted','superseded','reversed','returned','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `revision_no` smallint(6) NOT NULL DEFAULT 0 COMMENT '0 = الأصل',
  `current_round` smallint(5) unsigned NOT NULL DEFAULT 1 COMMENT 'الجولة الجارية — تزيد مع كل إعادةٍ للموقع (UX-03 §8.2)',
  `revises_entry_id` int(10) unsigned DEFAULT NULL COMMENT 'الواقعة التي تصحّحها هذه المراجعة',
  `revision_kind` enum('adjustment','reversal','split','merge') DEFAULT NULL,
  `superseded_by_id` int(10) unsigned DEFAULT NULL COMMENT 'مؤشرٌ أماميٌّ إلى المراجعة الخالفة',
  `converted_at` datetime DEFAULT NULL COMMENT 'لحظة التحوّل المالي',
  `event_id` int(10) unsigned DEFAULT NULL COMMENT 'الحدث الجذري (D04) — مرجعٌ مرن',
  `cap_obligation_id` int(10) unsigned DEFAULT NULL COMMENT '§12.1-①: التزامُ النوع المستهلَك — contract_commitments.id (لقطة)',
  `cap_supplier_share_id` int(10) unsigned DEFAULT NULL COMMENT '§12.1-②: حصةُ المورد المنفَّذُ منها — op_containers درجة «مورد»',
  `cap_seat_id` int(10) unsigned DEFAULT NULL COMMENT '§12.1-③: المقعدُ التعاقدي — op_containers درجة «معدة»',
  `cap_assignment_id` int(10) unsigned DEFAULT NULL COMMENT '§12.1-④: فترةُ إسناد المعدة — seat_assignments.id',
  `cap_supplier_line_id` int(11) DEFAULT NULL COMMENT '§12.1-⑤: بندُ عقد المورد الذي يُحتسب به',
  `cap_role_snapshot` enum('primary','standby') DEFAULT NULL COMMENT '§12.1-⑥: أساسيةٌ أم احتياطيةٌ مفعَّلة لحظةَ الواقعة — ولو تغيّر الدورُ لاحقًا',
  `cap_coverage_id` bigint(20) unsigned DEFAULT NULL COMMENT '§12.1-⑦: إن كانت تغطيةً بديلة — substitute_coverages.cov_id',
  `cap_measure_code` enum('hour','ton','trip','meter') DEFAULT NULL COMMENT '§12.1-⑧: المقياس — فلا يُخصم الطنُّ من حصة ساعات',
  `cap_context_state` enum('proposed','confirmed','locked') DEFAULT NULL COMMENT '§12.1: مقترحةٌ عند الإدخال · مؤكدةٌ من المستخدم · مقفلةٌ لقطةً عند الاعتماد فلا تُحلّ ثانيةً (C29)',
  `entered_by` int(10) unsigned DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `legacy_dup_exempt` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'DEC-C: صفٌّ في مجموعةِ تصادمٍ (معدة×تاريخ×وردية) موروثةٍ قبل عتبة الدرع 2026-08-05 — استثناءٌ تاريخيٌّ معلَنٌ لا يُدمج ولا يُحذف',
  `client_match_state` enum('pending','matched','mismatched','client_data_unavailable','client_response_overdue') NOT NULL DEFAULT 'pending' COMMENT 'TS-04 — نتيجةُ مطابقةِ نسخةِ العميل',
  `client_match_at` datetime DEFAULT NULL COMMENT 'TS-04 — لحظةُ حسمِ المطابقة',
  `client_match_by` int(10) unsigned DEFAULT NULL COMMENT 'TS-04 — يدُ من حسمها',
  `client_match_ref` varchar(120) DEFAULT NULL COMMENT 'TS-04 — مرجعُ دليلِ المطابقة',
  `client_decision` enum('pending','accepted','disputed') NOT NULL DEFAULT 'pending' COMMENT 'TS-16 — قرارُ العميلِ على هذا المدخلِ وحدَه (القبولُ الجزئيُّ لكلِّ مدخل)',
  `dispute_ref` varchar(120) DEFAULT NULL COMMENT 'TS-16 — مرجعُ ملفِّ الاختلافِ — إلزاميٌّ عند النزاع',
  `entity_layer` enum('operations','contracting','holding') NOT NULL DEFAULT 'operations' COMMENT 'TS-03: طبقةُ الكيان',
  `container_key` varchar(32) DEFAULT NULL COMMENT 'المفتاحُ الثلاثيُّ client-contract-renewal',
  `client_id` int(10) unsigned DEFAULT NULL COMMENT 'العميلُ — مشتقٌّ من العقدِ ويُثبَّت لحظةَ القيد',
  `meter_before` decimal(12,2) DEFAULT NULL COMMENT 'قراءةُ العدّادِ قبل',
  `meter_after` decimal(12,2) DEFAULT NULL COMMENT 'قراءةُ العدّادِ بعد',
  `fuel_received_qty` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'وقودٌ مستلَمٌ كميةً',
  `fuel_issued_qty` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'وقودٌ مصروفٌ كميةً',
  `created_by_role` smallint(5) unsigned DEFAULT NULL COMMENT 'TS-04: الدورُ يُخزَّن مع المعرِّف لأن دورَ الشخصِ يتغير والسجلُّ لا',
  `seed_tag` varchar(32) DEFAULT NULL COMMENT 'TS-05: وسمُ البيانِ المبذور — ''test-seed''',
  `shift_slot_key` varchar(96) GENERATED ALWAYS AS (case when `seed_tag` is null and `state` not in ('rejected','cancelled','superseded','reversed') then concat_ws('|',`company_id`,`entry_date`,`shift`,`equipment_id`) else NULL end) STORED COMMENT 'مفتاحُ القفل — يوائم ق-18: NULL للمبذورِ وللحالاتِ المنتهيةِ فلا تشغل الخانة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entry_no` (`company_id`,`entry_no`),
  UNIQUE KEY `uq_sync` (`company_id`,`sync_uuid`),
  UNIQUE KEY `uq_shift_ue` (`shift_slot_key`),
  KEY `ix_date` (`company_id`,`entry_date`),
  KEY `ix_project` (`company_id`,`project_id`,`state`),
  KEY `ix_state` (`company_id`,`state`),
  KEY `ix_parties` (`company_id`,`supplier_entity_id`,`operator_employee_id`),
  KEY `ix_qty_billable` (`company_id`,`qty_billable`),
  KEY `ix_ue_plan_keys` (`contract_line_id`,`plan_period_id`),
  KEY `ix_ue_site` (`operational_site_id`),
  KEY `idx_ue_match` (`client_match_state`),
  KEY `idx_ue_cdec` (`client_decision`),
  KEY `ix_container_ue` (`container_key`),
  KEY `ix_machine_ue` (`equipment_id`,`entry_date`),
  KEY `ix_supplier_ue` (`supplier_entity_id`,`entry_date`),
  CONSTRAINT `chk_ue_match_evidence` CHECK (`client_match_state` = 'pending' or `client_match_at` is not null and `client_match_by` is not null),
  CONSTRAINT `chk_ue_dispute_ref` CHECK (`client_decision` <> 'disputed' or `dispute_ref` is not null),
  CONSTRAINT `chk_ue_meter` CHECK (`meter_after` is null or `meter_before` is null or `meter_after` >= `meter_before`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D02 §3.1 — سجلّ الواقعة: مصدر الحقيقة الوحيد للوحدة التشغيلية';

-- ── Table: unit_match_overrides ──
CREATE TABLE `unit_match_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `entry_id` int(10) unsigned NOT NULL COMMENT 'المدخلُ المشمول',
  `reason` varchar(300) NOT NULL COMMENT 'TS-05-ب ① السبب',
  `evidence_ref` varchar(160) DEFAULT NULL COMMENT 'TS-05-ب ② الدليلُ المتاح',
  `decided_by` int(10) unsigned NOT NULL COMMENT 'TS-05-ب ③ مَن أصدره',
  `decided_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'TS-05-ب ④ التاريخُ والوقت',
  `scope_note` varchar(300) NOT NULL COMMENT 'TS-05-ب ⑤ نطاقُ الوحداتِ المشمولة',
  `allows` enum('primary_only','billing') NOT NULL COMMENT 'TS-05-ب ⑥ أيسمح بالأثرِ الأوليِّ فقط أم بالفوترة',
  `match_state_at_decision` enum('pending','matched','mismatched','client_data_unavailable','client_response_overdue') NOT NULL COMMENT 'TS-05-ب ⑦ حالُ المطابقةِ لحظةَ القرار — فلا يُعاد تفسيرُه لاحقًا',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_umo_entry` (`entry_id`),
  KEY `idx_umo_co` (`company_id`),
  CONSTRAINT `fk_umo_entry` FOREIGN KEY (`entry_id`) REFERENCES `unit_entries` (`id`),
  CONSTRAINT `chk_umo_fields` CHECK (`reason` <> '' and `scope_note` <> '' and `decided_by` > 0),
  CONSTRAINT `chk_umo_not_matched` CHECK (`match_state_at_decision` <> 'matched')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='TS-05 — قرارُ تجاوزِ مطابقةِ العميلِ بسبعةِ حقولٍ إلزامية';

-- ── Table: unit_party_awards ──
CREATE TABLE `unit_party_awards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `source_kind` enum('timesheet','unit_record') NOT NULL,
  `source_ref` int(10) unsigned NOT NULL COMMENT 'معرّف الصف في جدول المصدر',
  `party` enum('client','supplier','operator','supervisor') NOT NULL,
  `party_ref` int(10) unsigned DEFAULT NULL COMMENT 'العميل/المورد/الموظف حسب الطرف',
  `contract_ref` int(10) unsigned DEFAULT NULL COMMENT 'عقد هذا الطرف — أو سياسة حافزه',
  `award_unit_type` enum('hour','ton','meter','cbm','day','shift','trip') NOT NULL,
  `award_qty` decimal(14,2) NOT NULL COMMENT 'الكمية بوحدة عقد هذا الطرف',
  `entitlement_state` enum('due','partial','not_due','pending','rejected','settlement') NOT NULL DEFAULT 'due',
  `entitlement_pct` decimal(5,2) NOT NULL DEFAULT 100.00,
  `qty_due` decimal(14,2) GENERATED ALWAYS AS (round(`award_qty` * `entitlement_pct` / 100,2)) STORED COMMENT 'محسوبٌ خادميًّا — لا يُكتب يدويًّا',
  `unit_price` decimal(14,2) DEFAULT NULL COMMENT 'سعر وحدة عقد هذا الطرف',
  `currency` varchar(8) DEFAULT NULL COMMENT 'عملة عقده — لا تُجمع فوق غيرها',
  `policy_rule` varchar(60) DEFAULT NULL COMMENT 'اسم البند المطبَّق',
  `policy_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'لقطة القاعدة وقت الحكم — للتدقيق الرجعي' CHECK (json_valid(`policy_snapshot`)),
  `unavailable_reason` varchar(200) DEFAULT NULL COMMENT 'سبب التعذّر معلنًا — لا رقمَ ملفَّق',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'حذفٌ ناعم — شرط بوابة المستأجر',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_party_award` (`company_id`,`source_kind`,`source_ref`,`party`),
  KEY `ix_state` (`company_id`,`party`,`entitlement_state`),
  KEY `ix_source` (`company_id`,`source_kind`,`source_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D02 §3.7 — أحكام استحقاق الأطراف: لكل طرفٍ وحدتُه وكميتُه ونسبتُه';

-- ── Table: unit_state_changes ──
CREATE TABLE `unit_state_changes` (
  `chg_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `scope_type` enum('unit','equipment','site','contract') NOT NULL,
  `scope_id` int(10) unsigned NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `field_changed` enum('time_state','responsible_party','quantity','classification') NOT NULL,
  `value_before` varchar(120) NOT NULL,
  `value_after` varchar(120) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `doc_ref` varchar(120) NOT NULL COMMENT 'المستند المؤيد إلزامي',
  `estimated_impact_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'الأثر المقدَّر لكل طرف — قبل الإرسال' CHECK (json_valid(`estimated_impact_json`)),
  `state` enum('Draft','Pending','Approved','Rejected','Applied','Reversed') NOT NULL DEFAULT 'Pending',
  `requested_by` int(11) NOT NULL,
  `applied_at` datetime DEFAULT NULL,
  `reversal_ref` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`chg_id`),
  KEY `ix_usc_scope` (`company_id`,`scope_type`,`scope_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §6: تغيير حالة الوحدات — ولا Applied إلا من Approved، والمقيَّد يُعكس لا يُعدَّل';

-- ── Table: unit_time_log ──
CREATE TABLE `unit_time_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `log_date` date NOT NULL,
  `shift` enum('day','night') DEFAULT NULL,
  `project_id` int(10) unsigned NOT NULL,
  `equipment_id` int(10) unsigned NOT NULL,
  `operator_employee_id` int(10) unsigned DEFAULT NULL,
  `supplier_entity_id` int(10) unsigned DEFAULT NULL,
  `time_from` time DEFAULT NULL,
  `time_to` time DEFAULT NULL,
  `hours` decimal(6,2) NOT NULL COMMENT 'مدّة الفترة — زمنٌ لا وحدةُ فوترة',
  `ops_state` enum('actual_work','standby','tech_breakdown','supplier_stop','operator_stop','client_stop','fuel_logistics_stop','planned_stop','force_majeure','unlogged') NOT NULL,
  `cause_note` varchar(200) DEFAULT NULL,
  `resp_party` enum('company','supplier','operator','client','planned','force_majeure','none') NOT NULL DEFAULT 'none',
  `obligation_type` enum('fuel','access_road','loading_equipment','equipment_readiness','operators','permits_safety','utilities','catering_camp','force_majeure') DEFAULT NULL COMMENT 'بندُ الالتزام المسؤول (نفسُ قاموس contract_obligations) — NULL مشروعٌ لـactual_work وحدَه (هـ-1 · يفرضه الحارس)',
  `billable` tinyint(1) DEFAULT NULL COMMENT 'حكمُ الفوترة: أيُفوتر هذا الزمنُ على العميل؟ لقطةٌ لا اشتقاق (هـ-3)',
  `supplier_countable` tinyint(1) DEFAULT NULL COMMENT 'حكمُ المورد: أيُحتسب هذا الزمنُ في استحقاقه؟ لقطةٌ لا اشتقاق',
  `operator_countable` tinyint(1) DEFAULT NULL COMMENT 'حكمُ المشغّل: أيُحتسب هذا الزمنُ في استحقاقه؟ لقطةٌ لا اشتقاق',
  `decided_by` int(10) unsigned DEFAULT NULL COMMENT 'مَن اعتمد الإسناد (المشرف · ق-4). NULL أي سطرٌ ما قبل المصفوفة — لا يُملأ رجعيًّا',
  `decided_at` datetime DEFAULT NULL COMMENT 'لحظةُ اعتماد الإسناد — وغيابُه وسمُ «ما قبل المصفوفة» بنيويًّا',
  `objection_state` enum('none','objected','resolved') NOT NULL DEFAULT 'none' COMMENT 'الاعتراضُ المصغَّر (ق-25) — والبندُ المعترَضُ عليه لا يجمّد بقيةَ الواقعة',
  `objection_ref` varchar(60) DEFAULT NULL COMMENT 'مرجعُ الاعتراض — مستندٌ أو محضرٌ يحسمه الدور 19',
  `objection_reason` varchar(255) DEFAULT NULL COMMENT 'سببُ الاعتراض — إلزاميٌّ عند الاعتراض (يفرضه التطبيق)',
  `entry_id` int(10) unsigned DEFAULT NULL COMMENT 'سطر unit_entries المشتقّ (نموذج الساعة)',
  `entered_by` int(10) unsigned DEFAULT NULL,
  `sync_uuid` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
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
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `uom_code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `symbol` varchar(20) DEFAULT NULL,
  `category` enum('زمن','وزن','طول','حجم','عدد') NOT NULL DEFAULT 'عدد',
  `factor` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `notes` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_of_measure_company_code` (`company_id`,`uom_code`),
  KEY `idx_uom_scope` (`company_id`,`is_deleted`),
  KEY `idx_uom_cat` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: user_capacities ──
CREATE TABLE `user_capacities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `person_id` int(11) DEFAULT NULL COMMENT 'employees.id — NULL للخارجي بلا سجل موظف',
  `account_id` int(11) NOT NULL COMMENT 'users.id — حسابُ دخولٍ واحدٌ لكل الصفات',
  `capacity_type` enum('employee','project_employee','operator','technician','shift_supervisor','project_manager','supplier_supervisor','client_rep','auditor','executive') NOT NULL,
  `role` varchar(30) NOT NULL COMMENT 'حزمةُ الصلاحيات المرتبطة بالصفة (roles.id)',
  `scope_type` enum('company','project','site','supplier','client') NOT NULL DEFAULT 'company',
  `scope_id` int(11) DEFAULT NULL COMMENT 'معرّفُ النطاق — إلزاميٌّ لغير company',
  `source_type` enum('contract','delegation') NOT NULL,
  `source_id` int(11) DEFAULT NULL COMMENT 'مرجعُ المصدر — إلزاميٌّ للعقد',
  `source_note` varchar(190) DEFAULT NULL COMMENT 'إعلانُ التفويض الموروث ونحوه',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `state` enum('active','frozen','expired') NOT NULL DEFAULT 'active',
  `state_reason` varchar(255) DEFAULT NULL,
  `state_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_uc_capacity` (`account_id`,`capacity_type`,`scope_type`,`scope_id`,`valid_from`),
  KEY `ix_uc_account_state` (`account_id`,`state`),
  KEY `ix_uc_person` (`person_id`),
  KEY `ix_uc_company` (`company_id`),
  KEY `ix_uc_scope` (`scope_type`,`scope_id`),
  CONSTRAINT `ck_uc_scope` CHECK (`scope_type` = _utf8mb4'company' or `scope_id` is not null),
  CONSTRAINT `ck_uc_source` CHECK (`source_type` <> _utf8mb4'contract' or `source_id` is not null),
  CONSTRAINT `ck_uc_state` CHECK (`state` = _utf8mb4'active' or `state_reason` is not null and `state_at` is not null),
  CONSTRAINT `ck_uc_window` CHECK (`valid_to` is null or `valid_to` >= `valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: users ──
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد',
  `name` varchar(100) NOT NULL COMMENT 'الاسم الثلاثي',
  `username` varchar(150) NOT NULL COMMENT 'اسم المستخدم',
  `email` varchar(150) DEFAULT NULL COMMENT 'البريد',
  `password` varchar(255) NOT NULL COMMENT 'كلمة المرور',
  `phone` varchar(20) DEFAULT NULL COMMENT 'رقم الهاتف',
  `role` varchar(30) NOT NULL COMMENT 'رقم الصلاحية',
  `company_id` int(11) DEFAULT NULL COMMENT 'رقم الشركة',
  `employee_id` int(11) DEFAULT NULL COMMENT 'الموظف المرتبط بهذا الحساب',
  `supplier_entity_id` int(11) DEFAULT NULL COMMENT 'H-20: موردُ هذا الحساب — إلزامٌ وظيفيٌّ لدور مشرف الموردين (8)، والحارسُ يقرؤه حصرًا',
  `role_id` int(11) DEFAULT NULL COMMENT 'رقم الصلاحية',
  `position_id` int(11) DEFAULT NULL COMMENT 'جسر المنصب (ADR-07/K6) — nullable: NULL = السلوك القائم عبر role كما هو',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active' COMMENT 'الحالة',
  `force_password_change` tinyint(1) NOT NULL DEFAULT 0,
  `temp_password_set_at` timestamp NULL DEFAULT NULL,
  `project_id` varchar(20) NOT NULL DEFAULT '0' COMMENT 'المشروع',
  `contract_id` int(11) DEFAULT 0 COMMENT 'العقد',
  `parent_id` varchar(20) NOT NULL DEFAULT '0' COMMENT 'المستخدم الاب',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'انشئ في',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'عدل في',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT 'اخر دخول',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'محذوف',
  `deleted_at` datetime DEFAULT NULL COMMENT 'وقت الحذف',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'الحاذف',
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
  CONSTRAINT `fk_users_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_users_supplier` FOREIGN KEY (`supplier_entity_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: visibility_audit_log ──
CREATE TABLE `visibility_audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `element_code` varchar(64) NOT NULL,
  `scope_type` varchar(24) NOT NULL,
  `scope_id` varchar(64) NOT NULL,
  `from_mode` varchar(12) DEFAULT NULL,
  `to_mode` varchar(24) NOT NULL COMMENT 'open·closed·inherit·grant_expired·denied_self',
  `actor` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `affected_count` int(11) NOT NULL DEFAULT 0,
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_val_element` (`element_code`),
  KEY `ix_val_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ADM-01 §2 — «لا تغييرَ صامت»: كلُّ فتحٍ وإغلاقٍ بفاعله وسببه ومدته';

-- ── Table: visibility_keys ──
CREATE TABLE `visibility_keys` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `element_code` varchar(64) NOT NULL,
  `scope_type` enum('account','capacity_type','department','project','supplier','client') NOT NULL,
  `scope_id` varchar(64) NOT NULL COMMENT 'معرّفُ النطاق — رقمٌ أو كودُ فئة',
  `mode` enum('open','closed','inherit') NOT NULL,
  `reason` varchar(255) DEFAULT NULL COMMENT 'إلزاميٌّ لغير inherit (CHECK)',
  `granted_by` int(11) NOT NULL,
  `granted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL COMMENT 'إلزاميٌّ لفتح الحساس (حارسُ الخدمة)',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vk_key` (`company_id`,`element_code`,`scope_type`,`scope_id`),
  KEY `ix_vk_element` (`element_code`),
  KEY `ix_vk_scope` (`scope_type`,`scope_id`),
  CONSTRAINT `fk_vk_element` FOREIGN KEY (`element_code`) REFERENCES `portal_elements` (`element_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: waivers_reversals ──
CREATE TABLE `waivers_reversals` (
  `ovr_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `action` enum('waive','reverse','suspend','reduce') NOT NULL,
  `source_type` varchar(60) NOT NULL COMMENT 'مرجع الأصل — إلزامي',
  `source_id` bigint(20) unsigned NOT NULL,
  `amount_before` decimal(18,2) DEFAULT NULL,
  `amount_after` decimal(18,2) DEFAULT NULL,
  `reason` varchar(500) NOT NULL,
  `approvals_ref` varchar(120) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ovr_id`),
  KEY `ix_wr_source` (`source_type`,`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GOV-01 §8: الإعفاء والعكس والتعليق والتخفيض — Insert-only ولا حذف للأصل أبدًا';

-- ── Table: work_delegations ──
CREATE TABLE `work_delegations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `kind` varchar(20) NOT NULL COMMENT 'task_assign|role_assign|deputize|delegate_approval|reassign|workload_move',
  `from_user_id` int(10) unsigned NOT NULL,
  `to_user_id` int(10) unsigned NOT NULL,
  `scope_ref` varchar(160) NOT NULL COMMENT 'المهمة/الدور/نوع المستند — لا تفويض مفتوح النطاق',
  `cap_amount` decimal(14,2) DEFAULT NULL COMMENT 'سقف تفويض الاعتماد',
  `cap_currency` varchar(3) DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL COMMENT 'لا تفويض مفتوح المدة',
  `status` varchar(12) NOT NULL DEFAULT 'active' COMMENT 'active|ended|revoked',
  `effect_on_open` varchar(200) NOT NULL DEFAULT 'تعود للأصل فورًا بانتهائها',
  `approval_ref` varchar(60) DEFAULT NULL COMMENT 'جهة الموافقة — الحوكمة',
  `created_by` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'المنشئ',
  `created_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المنشئ لحظة الفعل',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(10) unsigned DEFAULT NULL COMMENT 'المعتمِد',
  `approved_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المعتمِد',
  `approved_at` datetime DEFAULT NULL,
  `delegation_ref` varchar(60) DEFAULT NULL COMMENT 'مرجع التفويض إن اعتُمد به',
  `parent_ref` varchar(60) DEFAULT NULL COMMENT 'المرجع الأب',
  PRIMARY KEY (`id`),
  KEY `ix_wd_to` (`company_id`,`to_user_id`,`status`),
  KEY `ix_wd_window` (`status`,`starts_at`,`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WF-08: انتهاء التفويض يوقف التوليد ولا يلغي المفتوح';

-- ── Table: work_escalations ──
CREATE TABLE `work_escalations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `item_kind` varchar(16) NOT NULL COMMENT 'work_item|request|approval|ticket',
  `item_ref` bigint(20) unsigned NOT NULL,
  `from_user_id` int(10) unsigned DEFAULT NULL,
  `to_user_id` int(10) unsigned NOT NULL,
  `level` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `reason` varchar(24) NOT NULL DEFAULT 'sla_response' COMMENT 'sla_response|sla_completion|manual|risk',
  `note` varchar(300) DEFAULT NULL,
  `escalated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `company_scope` varchar(60) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_we_item` (`item_kind`,`item_ref`),
  KEY `ix_we_open` (`company_id`,`resolved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AC-WFM-09: صفر مهمة متأخرة بلا تصعيد';

-- ── Table: work_items ──
CREATE TABLE `work_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL COMMENT 'الكيان — EN-03',
  `item_type` varchar(12) NOT NULL DEFAULT 'task' COMMENT 'task|assignment',
  `title` varchar(300) NOT NULL,
  `details` text DEFAULT NULL,
  `source_type` varchar(12) NOT NULL COMMENT 'SRC-01..SRC-14 — لا مصدر خارجها',
  `source_ref` varchar(120) NOT NULL COMMENT 'مرجع المستند/الواقعة/القرار المنشئ',
  `source_screen` varchar(120) DEFAULT NULL COMMENT 'شاشة الأصل وملفها',
  `action_code` varchar(60) DEFAULT NULL COMMENT 'رمز الفعل من NAV-09 إن اشتُق من فعل',
  `event_ref` varchar(60) DEFAULT NULL,
  `org_unit_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `site_id` int(10) unsigned DEFAULT NULL,
  `assigned_person_id` int(10) unsigned DEFAULT NULL COMMENT 'جسر الهوية E-05 — يُشتق',
  `assigned_role_id` int(10) unsigned DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'draft',
  `completed_at` datetime DEFAULT NULL,
  `evidence_ref` varchar(200) DEFAULT NULL COMMENT 'دليل الإنجاز المرفوع',
  `owner_user_id` int(10) unsigned NOT NULL COMMENT 'المالك',
  `assigned_user_id` int(10) unsigned DEFAULT NULL COMMENT 'المنفذ الفعلي (users)',
  `deliverable` varchar(300) NOT NULL COMMENT 'المخرَج المطلوب',
  `evidence_required` varchar(200) NOT NULL DEFAULT 'أثر الفعل في سجل التدقيق' COMMENT 'دليل الإغلاق المطلوب',
  `verifier_user_id` int(10) unsigned DEFAULT NULL COMMENT 'المتحقق — لا يُغلق أحدٌ مهمته (WF-04)',
  `priority` varchar(4) NOT NULL DEFAULT 'P3' COMMENT 'P0..P4 — الورقة 05',
  `response_due_at` datetime DEFAULT NULL COMMENT 'مهلة الاستجابة',
  `accepted_at` datetime DEFAULT NULL,
  `sla_paused_at` datetime DEFAULT NULL,
  `sla_pause_reason` varchar(60) DEFAULT NULL COMMENT 'من قائمة الأسباب الموقفة وحدها',
  `escalation_level` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `reopened_of` bigint(20) unsigned DEFAULT NULL COMMENT 'أعيد فتحها من',
  `status_reason` varchar(300) DEFAULT NULL COMMENT 'سبب آخر انتقالٍ يشترط سببًا',
  `closed_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'المنشئ',
  `created_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المنشئ لحظة الفعل',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(10) unsigned DEFAULT NULL COMMENT 'المعتمِد',
  `approved_capacity` varchar(60) DEFAULT NULL COMMENT 'صفة المعتمِد',
  `approved_at` datetime DEFAULT NULL,
  `delegation_ref` varchar(60) DEFAULT NULL COMMENT 'مرجع التفويض إن اعتُمد به',
  `parent_ref` varchar(60) DEFAULT NULL COMMENT 'المرجع الأب',
  PRIMARY KEY (`id`),
  KEY `ix_wi_co_status` (`company_id`,`status`),
  KEY `ix_wi_assignee` (`company_id`,`assigned_user_id`,`status`),
  KEY `ix_wi_owner` (`company_id`,`owner_user_id`,`status`),
  KEY `ix_wi_due` (`company_id`,`due_at`),
  KEY `ix_wi_source` (`source_type`,`source_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WFM-01: عنصر العمل — واجهة قراءة وتنفيذ لا مصدر بيانات';

-- ── Table: worker_backup ──
CREATE TABLE `worker_backup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `backup_employee_id` int(11) NOT NULL,
  `backup_type` enum('احتياطي','مؤقت') NOT NULL DEFAULT 'احتياطي',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backup` (`employee_id`,`backup_employee_id`,`backup_type`),
  KEY `idx_wb_company` (`company_id`),
  KEY `idx_wb_backup` (`backup_employee_id`),
  CONSTRAINT `fk_wb_backup_emp` FOREIGN KEY (`backup_employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_wb_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_contract ──
CREATE TABLE `worker_contract` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL COMMENT 'كود العقد — يدوي (قرار 12)',
  `contract_type` enum('سنوي','غير محدّد','مشروع','موسمي','مؤقت','بالساعة','بالإنتاج','استشاري/إشرافي','احتياطي','تغطية مؤقتة','تجاري مؤقت') NOT NULL,
  `wage` decimal(12,2) DEFAULT NULL COMMENT 'مالي — إدخال يدوي',
  `wage_finance_note` varchar(255) DEFAULT NULL COMMENT 'تعليق مرجعي للإدارة المالية مستقبلاً',
  `wage_method` enum('شهري','بالساعة','بالوردية/اليوم','بالإنتاج','مقطوع') NOT NULL DEFAULT 'شهري',
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `state` enum('مسودة','نافذ','منتهٍ') NOT NULL DEFAULT 'مسودة',
  `rotation_pattern` enum('بلا','شهران+شهر','ثلاثة أشهر+15 يوم','مخصّص') NOT NULL DEFAULT 'بلا',
  `work_days` int(11) DEFAULT NULL,
  `leave_days` int(11) DEFAULT NULL,
  `next_rotation_date` date DEFAULT NULL,
  `planned_backup_id` int(11) DEFAULT NULL COMMENT '→ worker_profile.id',
  `monthly_hours_base` int(11) DEFAULT NULL COMMENT 'أساس توزيع المتغيّر (مثال 300)',
  `fixed_wage_ratio` decimal(5,2) DEFAULT NULL COMMENT 'نسبة الأجر الثابت % (مثال 30)',
  `billable_downtime` enum('استعداد العميل','+ عطل الصيانة','حسب الحدث') DEFAULT NULL,
  `allow_housing` decimal(12,2) DEFAULT NULL,
  `allow_food` decimal(12,2) DEFAULT NULL,
  `allow_site` decimal(12,2) DEFAULT NULL,
  `allow_transport` decimal(12,2) DEFAULT NULL,
  `allow_finance_note` varchar(255) DEFAULT NULL COMMENT 'تعليق مرجعي للبدلات — للمالية مستقبلاً',
  `leave_terms` varchar(255) DEFAULT NULL,
  `coverage_terms` varchar(255) DEFAULT NULL,
  `termination_terms` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wc_worker` (`employee_id`),
  KEY `idx_wc_company` (`company_id`),
  KEY `idx_wc_state` (`state`),
  KEY `idx_wc_planned_backup` (`planned_backup_id`),
  CONSTRAINT `fk_wc_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_evaluation ──
CREATE TABLE `worker_evaluation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `period` date DEFAULT NULL,
  `score` decimal(6,2) DEFAULT NULL COMMENT 'محسوبٌ مبدئياً يدوي',
  `incentive_penalty_type` enum('بلا','حافز','جزاء') NOT NULL DEFAULT 'بلا',
  `amount` decimal(12,2) DEFAULT NULL COMMENT 'مالي — يدوي',
  `amount_finance_note` varchar(255) DEFAULT NULL COMMENT 'تعليق مرجعي للمالية لاحقاً',
  `operating_hours` decimal(10,2) DEFAULT NULL,
  `attendance_rate` decimal(5,2) DEFAULT NULL,
  `productivity` decimal(10,2) DEFAULT NULL,
  `misuse_faults` int(11) DEFAULT NULL,
  `fuel_consumption` decimal(10,2) DEFAULT NULL,
  `safety_score` decimal(5,2) DEFAULT NULL,
  `state` enum('مسودة','معتمد','مرحّل') NOT NULL DEFAULT 'مسودة',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_we_worker` (`employee_id`),
  KEY `idx_we_company` (`company_id`),
  KEY `idx_we_state` (`state`),
  CONSTRAINT `fk_we_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_evaluation_kpi ──
CREATE TABLE `worker_evaluation_kpi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(11) NOT NULL,
  `kpi_name` varchar(150) NOT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `score` decimal(6,2) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wek_eval` (`evaluation_id`),
  CONSTRAINT `fk_wek_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `worker_evaluation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: worker_leave_absence ──
CREATE TABLE `worker_leave_absence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `event_class` enum('مخطّط','طارئ') NOT NULL DEFAULT 'مخطّط' COMMENT 'مخطّط=إجازة/تناوب · طارئ=غياب',
  `event_type` varchar(40) NOT NULL COMMENT 'تبادلية·اعتيادية·مأمورية | غياب مفاجئ·انقطاع·هروب·مرض·إصابة·أسري·وفاة',
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `substitute_id` int(11) DEFAULT NULL COMMENT '→ worker_profile.id',
  `rotation_pattern` varchar(40) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `coverage_impact` enum('مغطًّى','فجوة جزئية','فجوة حرجة') DEFAULT NULL,
  `outcome` enum('عودة للعمل','تحويل لإجازة','إنهاء وتسوية') DEFAULT NULL,
  `state` enum('مطلوب','معتمد','مفتوح','مُغطًّى','منتهٍ','مغلق') NOT NULL DEFAULT 'مطلوب',
  `reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wla_worker` (`employee_id`),
  KEY `idx_wla_company` (`company_id`),
  KEY `idx_wla_state` (`state`),
  KEY `idx_wla_dates` (`date_from`,`date_to`),
  CONSTRAINT `fk_wla_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_movement ──
CREATE TABLE `worker_movement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `direction` enum('التحاق أول','عودة من إجازة','مغادرة لإجازة/مأمورية','نقل بين مشاريع','مغادرة نهائية') NOT NULL,
  `allocation_id` int(11) DEFAULT NULL COMMENT '→ worker_allocation.id (قيمة)',
  `origin` varchar(150) DEFAULT NULL,
  `origin_state` varchar(150) DEFAULT NULL,
  `origin_city` varchar(150) DEFAULT NULL,
  `destination_project_id` int(11) DEFAULT NULL COMMENT 'بالقيمة إلى project.id',
  `destination_state` varchar(150) DEFAULT NULL,
  `destination_city` varchar(150) DEFAULT NULL,
  `transport_mode` enum('بري','جوي','ترتيب مورد') DEFAULT NULL,
  `departure_date` date DEFAULT NULL,
  `expected_arrival` date DEFAULT NULL,
  `actual_arrival` date DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL COMMENT 'بالقيمة إلى employees.id (مشرف الموقع)',
  `housing_unit_id` int(11) DEFAULT NULL COMMENT '→ housing_unit.id',
  `site_zone` varchar(150) DEFAULT NULL,
  `safety_kit_received` tinyint(1) DEFAULT 0,
  `custody_received` tinyint(1) DEFAULT NULL COMMENT 'مؤجّل (S09) — يبقى فارغاً الآن',
  `ready_date` date DEFAULT NULL,
  `transfer_type` enum('مؤقت','دائم','إعادة تخصيص') DEFAULT NULL COMMENT 'للنقل بين المشاريع',
  `from_project_id` int(11) DEFAULT NULL,
  `to_project_id` int(11) DEFAULT NULL,
  `state` enum('مسودة','أمرٌ صادر','في الطريق','وصل','مستلَم بالموقع','جاهزٌ للعمل','ملغى') NOT NULL DEFAULT 'مسودة',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wm_worker` (`employee_id`),
  KEY `idx_wm_company` (`company_id`),
  KEY `idx_wm_state` (`state`),
  CONSTRAINT `fk_wm_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_qualification ──
CREATE TABLE `worker_qualification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `record_type` enum('مؤهل','رخصة','خبرة','ترقية') NOT NULL,
  `title` varchar(255) DEFAULT NULL COMMENT 'اسم الشهادة/الرخصة/الدرجة',
  `issuer` varchar(255) DEFAULT NULL,
  `equipment_type` varchar(100) DEFAULT NULL COMMENT 'نوع المعدة المرتبط بالرخصة',
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `accreditation_category` enum('مهارة معدة','اعتماد فني','دورة','شهادة','سلامة','فحص طبي','اعتماد موقع','تصريح') DEFAULT NULL,
  `proficiency_level` enum('مبتدئ','متوسط','متقدم','خبير') DEFAULT NULL,
  `is_critical` tinyint(1) DEFAULT 0 COMMENT 'يمنع التخصيص عند انتهائه',
  `alert_lead_days` int(11) DEFAULT 30,
  `document` varchar(255) DEFAULT NULL,
  `decision_ref` varchar(255) DEFAULT NULL COMMENT 'قرار الترقية/التدرّج وتاريخه',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wq_worker` (`employee_id`),
  KEY `idx_wq_company` (`company_id`),
  KEY `idx_wq_expiry` (`expiry_date`),
  KEY `idx_wq_critical` (`is_critical`),
  CONSTRAINT `fk_wq_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_restricted_site ──
CREATE TABLE `worker_restricted_site` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL COMMENT 'بالقيمة إلى project.id',
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_restricted` (`employee_id`,`project_id`),
  KEY `idx_wrs_company` (`company_id`),
  CONSTRAINT `fk_wrs_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_settlement ──
CREATE TABLE `worker_settlement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `worker_contract_id` int(11) DEFAULT NULL COMMENT 'بالقيمة إلى worker_contract.id',
  `source_type` enum('شركة','مورد','مقاول') DEFAULT NULL,
  `settlement_party` varchar(255) DEFAULT NULL COMMENT 'الجهة (شركة/مورد/مقاول) — نصّي الآن',
  `settlement_basis` enum('عمالة شركة','فاتورة مورد','مستخلص مقاول') DEFAULT NULL,
  `net_amount` decimal(12,2) DEFAULT NULL COMMENT 'مالي — محسوبٌ من البنود/يدوي',
  `net_finance_note` varchar(255) DEFAULT NULL,
  `state` enum('محتسب','معتمد','مدفوع') NOT NULL DEFAULT 'محتسب',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ws_worker` (`employee_id`),
  KEY `idx_ws_company` (`company_id`),
  KEY `idx_ws_state` (`state`),
  CONSTRAINT `fk_ws_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── Table: worker_settlement_line ──
CREATE TABLE `worker_settlement_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `settlement_id` int(11) NOT NULL,
  `line_type` enum('مستحق','خصم') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wsl_set` (`settlement_id`),
  CONSTRAINT `fk_wsl_set` FOREIGN KEY (`settlement_id`) REFERENCES `worker_settlement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: workforce_requirement ──
CREATE TABLE `workforce_requirement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL COMMENT 'بالقيمة إلى project.id',
  `worker_category` varchar(40) NOT NULL,
  `required_qty` int(11) NOT NULL DEFAULT 0,
  `available_qty` int(11) DEFAULT 0,
  `shortage_qty` int(11) DEFAULT 0,
  `surplus_qty` int(11) DEFAULT 0,
  `is_critical` tinyint(1) DEFAULT 0,
  `priority` enum('عادية','عالية','حرجة') NOT NULL DEFAULT 'عادية',
  `need_date` date DEFAULT NULL,
  `fulfillment_stage` enum('مفتوح','استقطاب','ترشيح واعتماد','تعاقد','تحرّك','مُلبّى') NOT NULL DEFAULT 'مفتوح',
  `state` enum('مخطّط','متوازن','عجز','فائض') NOT NULL DEFAULT 'مخطّط',
  `candidates_note` text DEFAULT NULL COMMENT 'مرشّحون — إدخال يدوي (قرار 6)',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wr_company` (`company_id`),
  KEY `idx_wr_project` (`project_id`),
  KEY `idx_wr_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: workspace_cards ──
CREATE TABLE `workspace_cards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `title_ar` varchar(190) NOT NULL,
  `owner_doc` varchar(32) NOT NULL,
  `source_service` varchar(120) NOT NULL COMMENT 'الخدمةُ المالكةُ للحساب — لا تحسب اللوحة',
  `permission_code` varchar(64) DEFAULT NULL,
  `counter_source` varchar(120) DEFAULT NULL,
  `cache_ttl` int(11) NOT NULL DEFAULT 0 COMMENT '0 = حيٌّ بلا كاش (عدّاداتُ الانتظار)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wc_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WSP-01 §7 — قاموسُ بطاقات المساحات بمالكيها';

-- ── Table: workspace_layouts ──
CREATE TABLE `workspace_layouts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` enum('department','project','supplier','client','equipment','person') NOT NULL,
  `layout_json` text NOT NULL COMMENT 'البطاقاتُ وترتيبُها لهذا النوع',
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wl` (`entity_type`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WSP-01 §7 — التخطيطُ بالنوع لا بالكيان (قاموسٌ عالمي)';

-- ── Table: workspace_navigation_log ──
CREATE TABLE `workspace_navigation_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `account_id` int(11) NOT NULL,
  `from_layer` varchar(64) DEFAULT NULL,
  `to_layer` varchar(64) NOT NULL,
  `entity_ref` varchar(64) DEFAULT NULL,
  `result` enum('ok','denied') NOT NULL DEFAULT 'ok',
  `at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_wnl_account` (`account_id`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WSP-01 §7 — WorkspaceOpened · LayerSwitched · 403 مسجَّلة';

-- ── Table: workspace_prefs ──
CREATE TABLE `workspace_prefs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `account_id` int(11) NOT NULL,
  `entity_type` varchar(24) NOT NULL,
  `pinned_cards_json` text DEFAULT NULL,
  `default_period` varchar(24) NOT NULL DEFAULT 'today',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wp` (`account_id`,`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: workspace_views ──
CREATE TABLE `workspace_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `screen` varchar(40) NOT NULL COMMENT 'my_tasks|my_requests|…',
  `view_key` varchar(40) NOT NULL COMMENT 'today|late|upcoming|blocked|returned|delegated|assigned_by_me|team',
  `filters_json` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wv` (`user_id`,`screen`,`view_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── View: client_contracts ──
SET collation_connection = 'utf8mb4_unicode_ci';
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `client_contracts` AS select `c`.`id` AS `id`,`c`.`company_id` AS `company_id`,`c`.`contract_signing_date` AS `contract_signing_date`,`c`.`grace_period_days` AS `grace_period_days`,`c`.`contract_duration_months` AS `contract_duration_months`,`c`.`contract_duration_days` AS `contract_duration_days`,`c`.`equip_shifts_contract` AS `equip_shifts_contract`,`c`.`shift_contract` AS `shift_contract`,`c`.`equip_total_contract_daily` AS `equip_total_contract_daily`,`c`.`total_contract_permonth` AS `total_contract_permonth`,`c`.`total_contract_units` AS `total_contract_units`,`c`.`actual_start` AS `actual_start`,`c`.`actual_end` AS `actual_end`,`c`.`transportation` AS `transportation`,`c`.`accommodation` AS `accommodation`,`c`.`place_for_living` AS `place_for_living`,`c`.`workshop` AS `workshop`,`c`.`hours_monthly_target` AS `hours_monthly_target`,`c`.`forecasted_contracted_hours` AS `forecasted_contracted_hours`,`c`.`created_at` AS `created_at`,`c`.`updated_at` AS `updated_at`,`c`.`daily_work_hours` AS `daily_work_hours`,`c`.`daily_operators` AS `daily_operators`,`c`.`first_party` AS `first_party`,`c`.`second_party` AS `second_party`,`c`.`witness_one` AS `witness_one`,`c`.`witness_two` AS `witness_two`,`c`.`price_currency_contract` AS `price_currency_contract`,`c`.`paid_contract` AS `paid_contract`,`c`.`payment_time` AS `payment_time`,`c`.`guarantees` AS `guarantees`,`c`.`retention_pct` AS `retention_pct`,`c`.`advance_recovery_pct` AS `advance_recovery_pct`,`c`.`payment_date` AS `payment_date`,`c`.`contract_status` AS `contract_status`,`c`.`pause_state_before` AS `pause_state_before`,`c`.`pause_reason` AS `pause_reason`,`c`.`pause_date` AS `pause_date`,`c`.`resume_date` AS `resume_date`,`c`.`termination_type` AS `termination_type`,`c`.`termination_reason` AS `termination_reason`,`c`.`merged_with` AS `merged_with`,`c`.`status` AS `status`,`c`.`is_deleted` AS `is_deleted`,`c`.`deleted_at` AS `deleted_at`,`c`.`deleted_by` AS `deleted_by`,`c`.`project_id` AS `project_id`,`c`.`site_id` AS `site_id`,`c`.`readiness_state` AS `readiness_state`,`c`.`signing_authority_ref` AS `signing_authority_ref`,`cos`.`id` AS `primary_scope_id`,`cos`.`site_id` AS `primary_site_id`,`cos`.`scope_name` AS `primary_scope_name` from (`contracts` `c` left join `contract_operational_sites` `cos` on(`cos`.`contract_id` = `c`.`id` and `cos`.`is_primary` = 1 and coalesce(`cos`.`is_deleted`,0) = 0));

-- ── View: unified_fault_taxonomy ──
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `unified_fault_taxonomy` AS select distinct `fc`.`main_category_code` AS `code`,`fc`.`main_category_name` AS `name`,`fc`.`equipment_type` AS `equipment_type`,'failure_codes' AS `source` from `failure_codes` `fc` where `fc`.`main_category_code` is not null and `fc`.`main_category_code` <> '';

-- ── View: v_monthly_performance ──
SET collation_connection = 'utf8mb4_unicode_ci';
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_monthly_performance` AS select `ue`.`company_id` AS `company_id`,date_format(`ue`.`entry_date`,'%Y-%m') AS `period`,`ue`.`supplier_entity_id` AS `supplier_entity_id`,`ue`.`contract_id` AS `contract_id`,`ue`.`project_id` AS `project_id`,`ue`.`equipment_id` AS `equipment_id`,count(distinct `ue`.`id`) AS `entries_count`,count(distinct `ue`.`entry_date`) AS `days_worked`,round(coalesce(sum(case when `l`.`ops_state` = 'actual_work' then `l`.`hours` end),0),2) AS `run_hours`,round(coalesce(sum(case when `l`.`ops_state` = 'standby' then `l`.`hours` end),0),2) AS `standby_hours`,round(coalesce(sum(case when `l`.`ops_state` in ('tech_breakdown','supplier_stop','operator_stop','client_stop','fuel_logistics_stop','planned_stop','force_majeure') then `l`.`hours` end),0),2) AS `breakdown_hours`,round(coalesce(sum(`l`.`hours`),0),2) AS `total_hours`,round(coalesce(sum(case when `l`.`resp_party` = 'client' then `l`.`hours` end),0),2) AS `client_liable_hours`,round(coalesce(sum(case when `l`.`resp_party` = 'supplier' then `l`.`hours` end),0),2) AS `supplier_liable_hours`,round(coalesce(sum(case when `l`.`resp_party` = 'company' then `l`.`hours` end),0),2) AS `company_liable_hours`,case when coalesce(sum(`l`.`hours`),0) > 0 then round(100 * coalesce(sum(case when `l`.`ops_state` = 'actual_work' then `l`.`hours` end),0) / sum(`l`.`hours`),2) else NULL end AS `availability_pct`,round(coalesce(sum(`ue`.`fuel_issued_qty`),0),2) AS `fuel_issued_qty`,round(coalesce(sum(`ue`.`fuel_received_qty`),0),2) AS `fuel_received_qty`,round(coalesce(sum(case when `ue`.`meter_after` is not null and `ue`.`meter_before` is not null then `ue`.`meter_after` - `ue`.`meter_before` end),0),2) AS `meter_delta`,round(coalesce(sum(case when `ue`.`unit_type` = 'ton' then `ue`.`qty` end),0),2) AS `tons`,round(coalesce(sum(case when `ue`.`unit_type` = 'meter' then `ue`.`qty` end),0),2) AS `meters`,round(coalesce(sum(case when `ue`.`unit_type` = 'trip' then `ue`.`qty` end),0),2) AS `trips`,max(`ue`.`updated_at`) AS `last_entry_at` from (`unit_entries` `ue` left join `unit_time_log` `l` on(`l`.`entry_id` = `ue`.`id`)) where `ue`.`seed_tag` is null and `ue`.`state` not in ('rejected','cancelled','superseded','reversed') group by `ue`.`company_id`,date_format(`ue`.`entry_date`,'%Y-%m'),`ue`.`supplier_entity_id`,`ue`.`contract_id`,`ue`.`project_id`,`ue`.`equipment_id`;

-- ── View: v_org_unit_heads ──
SET collation_connection = 'utf8mb4_unicode_ci';
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_org_unit_heads` AS select `u`.`unit_id` AS `unit_id`,`u`.`company_id` AS `company_id`,`u`.`unit_code` AS `unit_code`,`u`.`name_ar` AS `name_ar`,`a`.`person_id` AS `head_person_id`,`a`.`asg_id` AS `head_assignment_id`,`a`.`scope_type` AS `head_scope_type`,`a`.`scope_id` AS `head_scope_id` from (`org_units` `u` left join `org_assignments` `a` on(`a`.`org_unit_id` = `u`.`unit_id` and `a`.`state` = 'active' and curdate() between `a`.`valid_from` and `a`.`valid_to` and `a`.`assignment_type_code` in (select `t`.`type_code` from `org_assignment_types` `t` where `t`.`is_unit_head` = 1)));

-- ── View: v_worker_billable_hours ──
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_worker_billable_hours` AS select `wp`.`id` AS `employee_id`,`t`.`date` AS `work_date`,cast(`t`.`operator` as unsigned) AS `operation_id`,coalesce(sum(`t`.`executed_hours`),0) AS `productive_hours`,coalesce(sum(`t`.`standby_hours`),0) AS `standby_hours`,coalesce(sum(`t`.`hr_fault`),0) AS `worker_downtime`,coalesce(sum(`t`.`maintenance_fault`),0) AS `maintenance_downtime`,greatest(coalesce(sum(`t`.`executed_hours`),0) + coalesce(sum(`t`.`standby_hours`),0) - coalesce(sum(`t`.`hr_fault`),0),0) AS `billable_baseline` from (`employees` `wp` join `timesheet` `t` on(cast(`t`.`employee_id` as unsigned) = `wp`.`id`)) group by `wp`.`id`,`t`.`date`,cast(`t`.`operator` as unsigned);

-- ── View: v_worker_presence ──
SET collation_connection = 'utf8mb4_unicode_ci';
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_worker_presence` AS select `wp`.`id` AS `employee_id`,case when `wp`.`workforce_state` = 'منتهٍ' then 'منتهٍ' when exists(select 1 from `worker_leave_absence` `la` where `la`.`employee_id` = `wp`.`id` and `la`.`state` in ('معتمد','مفتوح','مُغطًّى') and (`la`.`date_from` is null or `la`.`date_from` <= curdate()) and (`la`.`date_to` is null or `la`.`date_to` >= curdate()) limit 1) then 'خارج الموقع/إجازة' when exists(select 1 from `worker_movement` `m` where `m`.`employee_id` = `wp`.`id` and `m`.`state` in ('أمرٌ صادر','في الطريق') limit 1) then 'في الطريق' when exists(select 1 from `equipment_drivers` `ed` where `ed`.`employee_id` = `wp`.`id` and `ed`.`status` = 1 limit 1) then 'داخل الموقع' else 'بانتظار التخصيص' end AS `presence_state` from `employees` `wp`;

-- ── View: v_worker_worklog ──
SET collation_connection = 'utf8mb4_unicode_ci';
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_worker_worklog` AS select `wp`.`id` AS `employee_id`,`wp`.`name` AS `worker_name`,coalesce(`wp`.`worker_category`,'موظف') AS `worker_category`,coalesce(`wp`.`workforce_state`,'-') AS `worker_state`,(select count(distinct `o`.`id`) from (`equipment_drivers` `ed` join `operations` `o` on(`o`.`equipment` = `ed`.`equipment_id`)) where `ed`.`employee_id` = `wp`.`id` and `ed`.`status` = 1) AS `operations_count`,(select coalesce(sum(`b`.`billable_baseline`),0) from `v_worker_billable_hours` `b` where `b`.`employee_id` = `wp`.`id`) AS `total_billable_hours`,(select count(0) from `worker_leave_absence` `la` where `la`.`employee_id` = `wp`.`id`) AS `leave_absence_count`,(select count(0) from `worker_movement` `m` where `m`.`employee_id` = `wp`.`id`) AS `movement_count`,(select count(0) from `worker_evaluation` `ev` where `ev`.`employee_id` = `wp`.`id`) AS `evaluation_count`,(select coalesce(sum(`ev`.`amount`),0) from `worker_evaluation` `ev` where `ev`.`employee_id` = `wp`.`id` and `ev`.`incentive_penalty_type` = 'حافز') AS `incentive_total`,(select coalesce(sum(`ev`.`amount`),0) from `worker_evaluation` `ev` where `ev`.`employee_id` = `wp`.`id` and `ev`.`incentive_penalty_type` = 'جزاء') AS `penalty_total` from `employees` `wp`;

SET FOREIGN_KEY_CHECKS = 1;
