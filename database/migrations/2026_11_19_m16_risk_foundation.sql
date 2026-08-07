-- M-16 (update0011 · DEC-G نافذ): أساس إدارة المخاطر المؤسسية المستقلة
-- ═══════════════════════════════════════════════════════════════════════════
-- المرجع: INJAZ-MASTER-MAP-5 أوراق 23-27 و32 + وثيقة M-16.
-- المبادئ المنفَّذة بنيويًّا هنا:
--   RK-02  سجل مركزي واحد (risk_register) — لا سجل لكل إدارة، والإدارة زاوية عرض.
--   RK-03  التقييمات نسخ تاريخية لا تُكتب فوقها (risk_assessments append-only).
--   RK-05  الإشارة تُفرز قبل أن تصير خطرًا (risk_signals منفصل عن السجل).
--   RK-06  التقييم لا ينشئ قيدًا ماليًّا — لا أعمدة ترحيل مالي هنا.
--   RK-07  الضابط لا يُحتسب إلا بدليل (حقول الدليل والتحقق إلزامية بالخدمة).
--   ورقة 32: مفتاح منع التكرار = وحدة+كيان+سبب جذري+نطاق+نافذة زمن —
--            «لا يدخل اسم الإدارة العارضة في المفتاح».
-- R1: تفعيل الوحدة 21 بالهيكل الحي (شركة 4) بتبعية الرئيس مباشرة.
-- idempotent — كل CREATE بـIF NOT EXISTS وكل بذرة بحارس وجود.

-- ── R1 · تفعيل وحدة المخاطر بالهيكل (DEC-G يقلب تعطيل 0010) ──────────────
UPDATE `org_units`
   SET `company_id` = 4, `active` = 1, `parent_unit_id` = NULL, `layer` = 'oversight'
 WHERE `unit_id` = 21 AND `unit_code` = 'risk_mgmt';

-- ── R3 · وحدات المخاطر الإحدى عشرة (ورقة 24 — مرجع لكل شركة) ─────────────
CREATE TABLE IF NOT EXISTS `risk_units` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `ru_code` VARCHAR(8) NOT NULL COMMENT 'RU-01..RU-11',
  `name_ar` VARCHAR(160) NOT NULL,
  `linked_depts` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'الإدارات المرتبطة نصًّا',
  `coverage` TEXT NULL COMMENT 'نطاق التغطية من الورقة 24',
  `output_ar` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'المخرَج',
  `ref_standard` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'المعيار المرجعي',
  `dedup_window_days` SMALLINT UNSIGNED NOT NULL DEFAULT 90
      COMMENT 'ورقة 32: 90 افتراضًا — الاستراتيجية أطول والتشغيلية أقصر',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ru` (`company_id`, `ru_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16 ورقة 24: وحدات المخاطر الإحدى عشرة';

-- ── R3 · السجل المركزي الواحد (RK-02) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `risk_register` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `risk_code` VARCHAR(16) NOT NULL COMMENT 'RSK-000001',
  `ru_id` INT UNSIGNED NOT NULL COMMENT 'وحدة المخاطر RU-xx',
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `scope_type` ENUM('مؤسسي','إداري','مشروعي','موقعي') NOT NULL DEFAULT 'إداري',
  `scope_ref_type` VARCHAR(24) NULL COMMENT 'site|contract|equipment|supplier|project',
  `scope_ref_id` INT NULL,
  `entity_type` VARCHAR(24) NULL COMMENT 'الكيان المتأثر (مكوّن مفتاح التكرار)',
  `entity_id` INT NULL,
  `root_cause` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'السبب الجذري (مكوّن مفتاح التكرار)',
  `owner_unit_id` INT NULL COMMENT 'RK-01: الإدارة المالكة حيث نشأ الخطر (org_units)',
  `risk_owner_user_id` INT NULL COMMENT 'مالك الخطر (مدير الإدارة المالكة)',
  `dedup_key` CHAR(40) NOT NULL DEFAULT '' COMMENT 'sha1(ru|entity|root_cause_norm|scope) — النافذة تُفحص زمنيًّا',
  `state` ENUM('classified','owner_assigned','inherent_assessed','controls_linked',
               'controls_evaluated','residual_assessed','appetite_compared',
               'treatment_planned','accepted','monitoring','reassessment',
               'closed','reopened') NOT NULL DEFAULT 'classified',
  `current_level` ENUM('منخفض','متوسط','مرتفع','حرج','محظور') NULL
      COMMENT 'آخر مستوى متبقٍّ معتمد — عليه مصفوفة السلطة',
  `velocity` ENUM('فوري','أيام','أسابيع','أشهر','سنوات') NULL COMMENT 'سرعة التحقق',
  `horizon` ENUM('قصير','متوسط','طويل') NULL COMMENT 'الأفق الزمني',
  `review_due` DATE NULL COMMENT 'موعد المراجعة بحسب المستوى (شهري للحرج..سنوي للمنخفض)',
  `merged_into_id` INT UNSIGNED NULL COMMENT 'دمج بقرار محلل — الصف يبقى أثرًا (لا حذف)',
  `created_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_risk_code` (`company_id`, `risk_code`),
  KEY `ix_dedup` (`company_id`, `dedup_key`, `created_at`),
  KEY `ix_unit_state` (`company_id`, `ru_id`, `state`),
  KEY `ix_owner_unit` (`company_id`, `owner_unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16: السجل المركزي الواحد للمخاطر (RK-02) — لا حذف إطلاقًا';

-- ── R3 · التقييمات نسخًا تاريخية (RK-03 append-only) ─────────────────────
CREATE TABLE IF NOT EXISTS `risk_assessments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `risk_id` INT UNSIGNED NOT NULL,
  `assess_type` ENUM('inherent','residual','target') NOT NULL,
  `likelihood` TINYINT UNSIGNED NOT NULL COMMENT '1..5',
  `impacts_json` TEXT NULL COMMENT 'الأبعاد الثمانية (ورقة 25) {dimension: 1..5}',
  `impact_max` TINYINT UNSIGNED NOT NULL COMMENT 'أقصى بعد — السلامة لا تُقايَض',
  `score` TINYINT UNSIGNED NOT NULL COMMENT 'likelihood × impact_max',
  `level` ENUM('منخفض','متوسط','مرتفع','حرج','محظور') NOT NULL,
  `confidence` ENUM('عالية','متوسطة','منخفضة') NOT NULL DEFAULT 'متوسطة'
      COMMENT 'درجة الثقة تُعلن ولا تُخفى',
  `technique` VARCHAR(48) NOT NULL DEFAULT 'مصفوفة الخطر' COMMENT 'تقنية التقييم (ورقة 25)',
  `assessed_by` INT NOT NULL,
  `challenged_by` INT NULL COMMENT 'تحدي المخاطر المستقل (المرحلتان 5/8)',
  `note` TEXT NULL,
  `assessed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_risk_type` (`company_id`, `risk_id`, `assess_type`, `assessed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16: تقييمات مؤرخة لا تُكتب فوقها (RK-03) — إدراج فقط';

-- ── R4 · الضوابط (ورقة 26 — الحقول التسعة + الخمسة الحرجة) ───────────────
CREATE TABLE IF NOT EXISTS `risk_controls` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `control_code` VARCHAR(16) NOT NULL COMMENT 'CTL-0001',
  `name_ar` VARCHAR(255) NOT NULL,
  `ctype` ENUM('وقائي','كاشف','تصحيحي') NOT NULL,
  `owner_user_id` INT NOT NULL COMMENT 'من يشغّل الضابط فعلًا',
  `process_ref` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'أين يقع في الدورة',
  `frequency` ENUM('كل وردية','يومي','أسبوعي','شهري','عند الحدث') NOT NULL,
  `evidence_spec` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'ما يُثبت التنفيذ — إلزامي ولا يُحتسب بدونه',
  `effectiveness` ENUM('فعال','فعال جزئيا','غير فعال','غير مثبت') NOT NULL DEFAULT 'غير مثبت',
  `last_verified_at` DATE NULL,
  `last_verify_result` VARCHAR(255) NULL,
  `last_verified_by` INT NULL,
  `next_verify_due` DATE NULL,
  `is_critical` TINYINT(1) NOT NULL DEFAULT 0,
  `hico_event` VARCHAR(255) NULL COMMENT 'حرج: الحدث عالي العواقب الذي يمنعه',
  `perf_criterion` VARCHAR(255) NULL COMMENT 'حرج: معيار الأداء',
  `verify_method` VARCHAR(255) NULL COMMENT 'حرج: مشاهدة/قياس/سجل',
  `verifier_user_id` INT NULL COMMENT 'حرج: متحقق مستقل ≠ المالك (يفرضه الحارس)',
  `fail_action` VARCHAR(255) NULL COMMENT 'حرج: الإجراء الفوري والتصعيد عند الفشل',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ctl_code` (`company_id`, `control_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16 ورقة 26: سجل الضوابط — الحرج بحقوله الخمسة';

CREATE TABLE IF NOT EXISTS `risk_control_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `risk_id` INT UNSIGNED NOT NULL,
  `control_id` INT UNSIGNED NOT NULL,
  `linked_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_link` (`company_id`, `risk_id`, `control_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16: خريطة ضوابط الخطر (المرحلة 6)';

CREATE TABLE IF NOT EXISTS `risk_control_evidence` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `control_id` INT UNSIGNED NOT NULL,
  `kind` ENUM('execution','verification') NOT NULL DEFAULT 'execution'
      COMMENT 'دليل تنفيذ من المالك · تحقق من المستقل',
  `evidence_text` TEXT NOT NULL,
  `evidence_ref` VARCHAR(255) NULL COMMENT 'مرجع ملف/صورة/سجل',
  `result` ENUM('فعال','فعال جزئيا','غير فعال') NULL COMMENT 'للتحقق فقط',
  `submitted_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ctl` (`company_id`, `control_id`, `kind`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16: أدلة تنفيذ الضوابط وتحققاتها — RK-07 بدليل لا بادعاء';

-- ── R4 · الشهية وحدود التحمل (ورقة 25 — المجالات الثمانية) ───────────────
CREATE TABLE IF NOT EXISTS `risk_appetite` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `domain` VARCHAR(48) NOT NULL COMMENT 'مجال الشهية (8 مجالات)',
  `appetite_ar` VARCHAR(255) NOT NULL COMMENT 'مستوى الشهية المعلن',
  `tolerance_ar` VARCHAR(255) NOT NULL COMMENT 'حد التحمل',
  `authority_ar` VARCHAR(160) NOT NULL COMMENT 'المخوَّل',
  `changeable_ar` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'أتتغير بالخطة العامة؟',
  `immutable_floor` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT '1 = لا تتغير بحال (السلامة · القانون · تسرب البيانات)',
  `plan_mode` ENUM('النمو والتوسع','التثبيت والكفاءة','الحماية والانكماش') NULL
      COMMENT 'NULL = السطر الأساسي؛ وإلا فتعديل نمط خطة',
  `updated_by` INT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain` (`company_id`, `domain`, `plan_mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16 ورقة 25: شهية المخاطر وحدود التحمل — يحددها الرئيس';

-- ── R4 · مؤشرات الخطر الرئيسة (ورقة 26 — 36 مؤشرًا بحدّين) ───────────────
CREATE TABLE IF NOT EXISTS `risk_kris` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `ru_id` INT UNSIGNED NULL COMMENT 'وحدة المخاطر المعنية',
  `dept_ar` VARCHAR(80) NOT NULL COMMENT 'الإدارة صاحبة المؤشر',
  `name_ar` VARCHAR(255) NOT NULL,
  `warn_threshold_ar` VARCHAR(160) NOT NULL COMMENT 'حد الإنذار',
  `critical_threshold_ar` VARCHAR(160) NOT NULL COMMENT 'الحد الحرج',
  `source_ar` VARCHAR(160) NOT NULL COMMENT 'المصدر في النظام',
  `current_value` VARCHAR(64) NULL,
  `kri_state` ENUM('ok','warn','critical','unread') NOT NULL DEFAULT 'unread',
  `last_read_at` TIMESTAMP NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kri` (`company_id`, `dept_ar`, `name_ar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16 ورقة 26: مؤشرات الخطر — سابقة للحدث لا لاحقة';

-- ── R3/R5 · الإشارات والفرز (RK-05) — الميدانية بمفتاح عطالة ─────────────
CREATE TABLE IF NOT EXISTS `risk_signals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `sg_code` VARCHAR(8) NULL COMMENT 'SG-01..SG-16 للآلية · NULL ليدوية/ميدانية',
  `source` ENUM('auto','manual','field') NOT NULL DEFAULT 'manual',
  `title` VARCHAR(255) NOT NULL,
  `details` TEXT NULL,
  `ru_hint_id` INT UNSIGNED NULL COMMENT 'الوحدة المرشحة من قاعدة الإشارة',
  `entity_type` VARCHAR(24) NULL,
  `entity_id` INT NULL,
  `scope_ref_type` VARCHAR(24) NULL,
  `scope_ref_id` INT NULL,
  `root_cause` VARCHAR(255) NOT NULL DEFAULT '',
  `site_id` INT NULL COMMENT 'ميداني: الموقع',
  `shift_ar` VARCHAR(24) NULL COMMENT 'ميداني: الوردية',
  `equipment_id` INT NULL COMMENT 'ميداني: المعدة',
  `photo_ref` VARCHAR(255) NULL COMMENT 'ميداني: صورة مضغوطة تُرفع عند الاتصال',
  `sync_uuid` CHAR(36) NULL COMMENT 'ورقة 32: مفتاح لكل إشارة ميدانية — إعادة المزامنة ترجع مرجع الأولى',
  `state` ENUM('pending','dismissed','linked','converted','escalated') NOT NULL DEFAULT 'pending',
  `triage_by` INT NULL,
  `triage_reason` VARCHAR(255) NULL COMMENT 'قرار الفرز بسببه — الإهمال يُوسَم ولا يُحذف',
  `triaged_at` TIMESTAMP NULL,
  `linked_risk_id` INT UNSIGNED NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sync` (`sync_uuid`),
  KEY `ix_state` (`company_id`, `state`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16: إشارات الخطر قبل الفرز (RK-05) — ليس كل حدث خطرًا';

-- ── R4 · خطط المعالجة (المرحلة 10) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `risk_treatments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `risk_id` INT UNSIGNED NOT NULL,
  `ttype` ENUM('تجنب','تقليل','نقل','قبول') NOT NULL,
  `plan_ar` TEXT NOT NULL,
  `action_owner_user_id` INT NOT NULL COMMENT 'مسؤول المعالجة — يظهر في مهامه',
  `due_date` DATE NOT NULL,
  `state` ENUM('planned','in_progress','done','verified','overdue') NOT NULL DEFAULT 'planned',
  `done_evidence` TEXT NULL COMMENT 'دليل الإنجاز — الإغلاق بقبول المتحقق',
  `verified_by` INT NULL,
  `verified_at` TIMESTAMP NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_owner_due` (`company_id`, `action_owner_user_id`, `state`, `due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16: خطط المعالجة المسنَدة بمهلة ومسؤول';

-- ── R4 · قرارات القبول (RK-04 + مصفوفة السلطة الخمسية) ───────────────────
CREATE TABLE IF NOT EXISTS `risk_acceptances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `risk_id` INT UNSIGNED NOT NULL,
  `level_at_acceptance` ENUM('منخفض','متوسط','مرتفع','حرج') NOT NULL
      COMMENT 'المحظور لا يُقبل بحال — غائب عمدًا من القائمة',
  `authority` ENUM('risk_owner','owner_with_analyst','deputy','ceo') NOT NULL
      COMMENT 'مصفوفة السلطة (ورقة 27) — تفرضها الخدمة على المستوى',
  `accepted_by` INT NOT NULL,
  `analyst_review_by` INT NULL COMMENT 'للمتوسط: مراجعة محلل المخاطر',
  `review_due` DATE NOT NULL COMMENT 'مهلة المراجعة — القبول ليس إهمالًا',
  `note` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_risk` (`company_id`, `risk_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16: قبول الخطر قرار رسمي موقَّع بمهلة مراجعة (RK-04)';

-- ── R4 · التصعيدات الآلية (RK-08) ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `risk_escalations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `risk_id` INT UNSIGNED NULL,
  `signal_id` INT UNSIGNED NULL,
  `reason_ar` VARCHAR(255) NOT NULL,
  `to_authority` ENUM('risk_manager','deputy','ceo') NOT NULL,
  `is_auto` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'التصعيد آلي بالمصفوفة لا بتقدير فردي',
  `acknowledged_by` INT NULL,
  `acknowledged_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_open` (`company_id`, `to_authority`, `acknowledged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16: الخطر الحرج لا يختفي عن الرئيس (RK-08) — تصعيد آلي';

-- ═══ البذر المرجعي (شركة 4 — بحارس وجود) ═══════════════════════════════════

-- وحدات المخاطر الإحدى عشرة (ورقة 24)
INSERT INTO `risk_units` (`company_id`,`ru_code`,`name_ar`,`linked_depts`,`output_ar`,`ref_standard`,`dedup_window_days`)
SELECT * FROM (SELECT 4,'RU-01','المخاطر المؤسسية والاستراتيجية','الرئيس التنفيذي ومكتبه','سجل المخاطر المؤسسي ولوحة المخاطر العليا','COSO ERM · ISO 31000',180 UNION ALL
               SELECT 4,'RU-02','المخاطر التشغيلية والمواقع','إدارة التشغيل وإدارة الموقع','سجل مخاطر التشغيل ولوحة مخاطر الموقع','ISO 31000 · IEC 31010',60 UNION ALL
               SELECT 4,'RU-03','مخاطر الصيانة والاعتمادية','إدارة الصيانة','سجل مخاطر الصيانة وتحليل أنماط الفشل','FMEA/FMECA · IEC 31010',60 UNION ALL
               SELECT 4,'RU-04','مخاطر القوى التشغيلية','القوى التشغيلية والموارد البشرية','سجل مخاطر القوى ولوحة الأهلية والرخص','ISO 45001',90 UNION ALL
               SELECT 4,'RU-05','مخاطر النقل والترحيل','النقل والترحيل','سجل مخاطر النقل وخريطة مخاطر المسارات','ISO 39001 · IEC 31010',90 UNION ALL
               SELECT 4,'RU-06','مخاطر سلسلة الإمداد والمشتريات والمخازن','المشتريات التشغيلية والمخازن والموردون','سجل مخاطر الإمداد وتحليل التركز','ISO 28000',90 UNION ALL
               SELECT 4,'RU-07','المخاطر التعاقدية والتجارية','المبيعات والعقود وإدارة الموردين','مراجعة المخاطر قبل التعاقد وسجل المخاطر التعاقدية','ISO 31000 · Contract Risk Checklist',120 UNION ALL
               SELECT 4,'RU-08','المخاطر المالية والخزينة','المالية والخزينة','سجل المخاطر المالية ولوحة التعرض','COSO ERM · تحليل الحساسية والضغط',90 UNION ALL
               SELECT 4,'RU-09','مخاطر التمويل والملكية والأصول','التمويل والملكية وإدارة الأسطول','سجل مخاطر التمويل ومحفظة الأصول','IEC 31010 · تحليل السيناريوهات',120 UNION ALL
               SELECT 4,'RU-10','مخاطر السلامة والأمن والضوابط الحرجة','الحوكمة والالتزام والموقع والتشغيل','سجل الأحداث عالية العواقب ولوحة الضوابط الحرجة','ISO 45001 · ICMM CCM',60 UNION ALL
               SELECT 4,'RU-11','مخاطر التقنية والبيانات واستمرارية الأعمال','مكتب هندسة النظم والحوكمة','سجل مخاطر التقنية وتحليل أثر الأعمال','ISO 27001 · ISO 22301',90) t
WHERE NOT EXISTS (SELECT 1 FROM `risk_units` WHERE `company_id` = 4 AND `ru_code` = 'RU-01');

-- الشهية الأساسية — المجالات الثمانية (ورقة 25)
INSERT INTO `risk_appetite` (`company_id`,`domain`,`appetite_ar`,`tolerance_ar`,`authority_ar`,`changeable_ar`,`immutable_floor`)
SELECT * FROM (SELECT 4,'السلامة والصحة','صفر تسامح مع الأحداث عالية العواقب','لا يُقبل خطر حرج في السلامة بحال','الرئيس التنفيذي حصرًا','لا تتغير بالخطة العامة',1 UNION ALL
               SELECT 4,'الالتزام القانوني','صفر تسامح مع المخالفة الصريحة','لا يُقبل ما يخالف قانونًا ولو بتوقيع إداري','لا يُقبل إطلاقًا','لا تتغير',1 UNION ALL
               SELECT 4,'التشغيل والإنتاج','شهية متوسطة — تتغير بالخطة','تُرفع في التوسع وتُخفض في التثبيت','نائب العمليات ضمن سقفه','تتغير بقرار المالك',0 UNION ALL
               SELECT 4,'المال والتعرض','شهية محدودة بسقف معلن','تُقاس بنسبة من الإيراد أو رأس المال','نائب المالية ضمن سقفه','تتغير بقرار المالك',0 UNION ALL
               SELECT 4,'التعاقد والتجارة','شهية متوسطة إلى مرتفعة في التوسع','تُرفع لدخول سوق جديدة وتُخفض عند التركز','نائب المالية والاستثمار','تتغير بقرار المالك',0 UNION ALL
               SELECT 4,'السمعة','شهية منخفضة','الأثر على العميل أو الجهة الرقابية لا يُقبل إلا نادرًا','الرئيس التنفيذي','تتغير نادرًا',0 UNION ALL
               SELECT 4,'البيانات والتقنية','شهية منخفضة جدًّا','تسرب بيانات كيان لا يُقبل بحال','الرئيس التنفيذي والحوكمة','لا تتغير في التسرب',1 UNION ALL
               SELECT 4,'الأفراد والقدرة','شهية متوسطة','تُرفع مؤقتًا في التعبئة السريعة بضوابط معوِّضة','نائب الشؤون الإدارية','تتغير بقرار المالك',0) t
WHERE NOT EXISTS (SELECT 1 FROM `risk_appetite` WHERE `company_id` = 4 AND `domain` = 'السلامة والصحة' AND `plan_mode` IS NULL);
