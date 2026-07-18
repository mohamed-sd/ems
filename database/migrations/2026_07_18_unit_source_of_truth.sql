-- ═══════════════════════════════════════════════════════════════════════════
-- D02 §15 المرحلة ١ · مصدر الحقيقة — الجداول الثلاثة
-- ───────────────────────────────────────────────────────────────────────────
-- «الوحدة لا تبدأ في المالية، ولا عند اعتماد العميل، ولا عند اعتماد المورد —
--  بل لحظةَ إدخالها في سجلّ التشغيل اليومي» (§3).
--
-- الطبقة العليا مبنيّةٌ سلفًا (unit_party_awards §3.7 · contract_hour_policies
-- §3.8 · المروحة الذرّية §6.1 · بوابة التحويل §14② · الناقل §14⑤)، والأساسُ
-- الذي تقوم عليه — الواقعةُ نفسها وزمنُها وسلسلةُ اعتمادها — لم يُبنَ بعد.
-- هذه المهاجرة تبني ذلك الأساس وحده: DDL فقط، بلا شاشةٍ ولا سلوك.
--
-- ⚠️ تعايشٌ لا استبدال: مسار timesheet الحيّ (220 صفًّا) يبقى عاملًا بحرفه.
--    لا صفَّ واحدٌ يُنقل، ولا جدولَ قائمًا يُمسّ. الوصل بالمروحة القائمة
--    يقع لاحقًا عبر unit_party_awards.source_kind='unit_record' — القيمةُ
--    المحجوزةُ سلفًا في مهاجرة 2026_07_18_unit_party_awards.sql ولم تُستعمل.
--
-- النقل حرفيٌّ عن الدستور: الأعمدة وأنواعها وقيم الـENUM والفهارس كما وردت
-- في §3.1 و§3.3 و§4.2 — بلا إضافةٍ ولا حذفٍ ولا تبسيط.
--   • لا عمود deleted_at في الثلاثة: الدستور لا ينصّ عليه، ولا حاجة إليه —
--     الحالاتُ الثلاث عشرة تملك دورةَ الحياة ('cancelled'/'rejected' منها)،
--     و«الوحدة المرفوضة لا تُحذف؛ حالةٌ بسببها في السجل» (§4.3). فالتسجيل
--     في TenantRegistry بـ soft=false — نمط fin_requests لا نمط fin_payments.
--   • لا FK إلى الجداول الخارجية (ops_projects/fleet/employees/entities):
--     الدستور علّم مراجعها بـ «ref» و«soft» صراحةً، ونمطُ الوحدات القائم
--     (unit_party_awards) يعلّق مراجعه كذلك — لأن المصدر عابرٌ للإدارات.
--     أما FK داخل العائلة (approvals → entries) فمنصوصٌ عليه في §4.2 ومنقول.
--
-- التطبيق بعميل utf8mb4 عبر database/migrate.php حصرًا (لا DDL يدوي).
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① unit_entries — سجلّ الواقعة والوحدة المسجّلة (§3.1) ────────────────────
-- «السجلّ القانوني للواقعة سطرًا سطرًا — كيانُ أعمالٍ كامل بهويةٍ ثابتة
--  (entry_no) ودورة حياةٍ ومراجعات». السطر يسجّل الوحدةَ بنوعها وكميتها بوصفها
--  حقيقةً مشتركة؛ أما استحقاقُ كل طرفٍ فمستقلٌّ في unit_party_awards (§3.7).
--
-- الحالات الثلاث عشرة في عمود state هي كامل دورة الحياة — لا tinyint ولا
-- مستوى اعتمادٍ رقمي. والانتقالات ومالكوها في §11.3 تُفرض خادميًّا (مرحلةٌ ٤).
CREATE TABLE IF NOT EXISTS `unit_entries` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`           INT UNSIGNED NOT NULL,
  `entry_no`             VARCHAR(30) NOT NULL COMMENT 'server-assigned — هويةٌ ثابتة',
  `entry_date`           DATE NOT NULL,
  `project_id`           INT UNSIGNED NOT NULL COMMENT 'ops_projects (D01) — مرجعٌ مرن',
  `contract_id`          INT UNSIGNED NULL     COMMENT 'sales contract (S05) — مرجعٌ مرن',
  `equipment_id`         INT UNSIGNED NULL     COMMENT 'fleet (S03) — مرجعٌ مرن',
  `operator_employee_id` INT UNSIGNED NULL     COMMENT 'employees (ADM) — مرجعٌ مرن',
  `supplier_entity_id`   INT UNSIGNED NULL     COMMENT 'entities (S02) — مرجعٌ مرن',
  `supervisor_id`        INT UNSIGNED NULL     COMMENT 'employees — عند وجود مشرف',

  -- قاموس الوحدات: النوع قائمةٌ قابلةٌ للتوسّع لا مفهومٌ مغلق (§2.1). ووحدةُ
  -- القياس تُفعَّل ماليًّا بوجود عقدٍ يستخدمها فقط (§2.6).
  `unit_type`            ENUM('hour','ton','meter','cbm','day','shift','trip') NOT NULL
                           COMMENT 'dictionary; finance-enabled by contract',
  `qty`                  DECIMAL(14,2) NOT NULL COMMENT 'كمية الواقعة المسجّلة',

  -- السطر التحليلي (§2.7) يقف عند اعتماد الموقع ولا تُنشأ له أحكامُ أطراف
  -- ولا يدخل السلسلةَ المالية — يغذّي المؤشرات وخطَّ الزمن فقط.
  `record_basis`         ENUM('contract','analytical') NOT NULL DEFAULT 'contract',

  -- حارس الطاقة (§3.10): التجاوز لا يُمنع إدخالُه — يُرفع العلم ويُمنع اعتمادُ
  -- الموقع حتى المراجعة. الحارس نفسه مرحلةٌ ٢.
  `capacity_flag`        TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'over daily capacity (§3.10)',

  `shift`                ENUM('day','night') NULL,
  `source_ref`           VARCHAR(60) NULL COMMENT 'field sheet / meter / weigh ticket',
  `txn_ref`              VARCHAR(40) NULL COMMENT 'غلاف العملية التشغيلية (§2.4)',
  `note`                 VARCHAR(200) NULL,

  -- ── الحالات الثلاث عشرة (§3.1 · §11.3) ──
  `state`                ENUM('draft','submitted','site_approved','parties_review',
                              'parties_approved','sales_approved','on_hold','converted',
                              'superseded','reversed','returned','rejected','cancelled')
                           NOT NULL DEFAULT 'draft',

  -- ── المراجعات والإصدارات (§7.1) — الأصل لا يُحرَّر ──
  `revision_no`          SMALLINT NOT NULL DEFAULT 0 COMMENT '0 = الأصل',
  `revises_entry_id`     INT UNSIGNED NULL COMMENT 'الواقعة التي تصحّحها هذه المراجعة',
  `revision_kind`        ENUM('adjustment','reversal','split','merge') NULL,
  `superseded_by_id`     INT UNSIGNED NULL COMMENT 'مؤشرٌ أماميٌّ إلى المراجعة الخالفة',

  -- ── لحظة التحوّل المالي (§6) ──
  `converted_at`         DATETIME NULL COMMENT 'لحظة التحوّل المالي',
  `event_id`             INT UNSIGNED NULL COMMENT 'الحدث الجذري (D04) — مرجعٌ مرن',

  `entered_by`           INT UNSIGNED NULL,
  -- PWA مؤجَّلٌ بقرار المالك؛ يبقى العمود وقيدُه الفريد كما نصّ الدستور
  -- فتكون المزامنة عطالةً بنيويةً حين تُفعَّل — لا إضافةً لاحقة.
  `sync_uuid`            CHAR(36) NULL,

  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entry_no` (`company_id`,`entry_no`),
  UNIQUE KEY `uq_sync`     (`company_id`,`sync_uuid`),
  KEY `ix_date`    (`company_id`,`entry_date`),
  KEY `ix_project` (`company_id`,`project_id`,`state`),
  KEY `ix_state`   (`company_id`,`state`),
  KEY `ix_parties` (`company_id`,`supplier_entity_id`,`operator_employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='D02 §3.1 — سجلّ الواقعة: مصدر الحقيقة الوحيد للوحدة التشغيلية';

-- ── ② unit_time_log — سجلّ الزمن التشغيلي (§3.3) ────────────────────────────
-- «وعاءُ توزيع وقت الوردية لكل معدةٍ في كل النماذج (ساعة وطنًّا ومترًا): سطرٌ
--  لكل فترةٍ بحالتها التشغيلية وسببها وجهتها المسؤولة».
--
-- المسار الثاني من المسارين الحاكمين (§2.5): الزمن التشغيلي يقيس استخدام الوقت
-- المتاح، والوحدة التعاقدية تقيس مقدار الإنجاز المستحق — لا يحلّ أحدهما محلّ
-- الآخر ولو اتّحدت وحدة القياس. وعمود hours هنا مدّةُ فترةٍ بحالةٍ تشغيلية،
-- لا «حقل hours مفرد مزدوج المعنى» الملغى في §3.2.
CREATE TABLE IF NOT EXISTS `unit_time_log` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`           INT UNSIGNED NOT NULL,
  `log_date`             DATE NOT NULL,
  `shift`                ENUM('day','night') NULL,
  `project_id`           INT UNSIGNED NOT NULL,
  `equipment_id`         INT UNSIGNED NOT NULL,
  `operator_employee_id` INT UNSIGNED NULL,
  `supplier_entity_id`   INT UNSIGNED NULL,
  `time_from`            TIME NULL,
  `time_to`              TIME NULL,
  `hours`                DECIMAL(6,2) NOT NULL COMMENT 'مدّة الفترة — زمنٌ لا وحدةُ فوترة',

  -- العشر حالاتٍ التشغيلية — نفس قاموس contract_hour_policies.ops_state القائم،
  -- فتتطابق السياسةُ مع الزمن حرفًا بحرف (§3.4 محرّك الاشتقاق).
  `ops_state`            ENUM('actual_work','standby','tech_breakdown','supplier_stop',
                              'operator_stop','client_stop','fuel_logistics_stop',
                              'planned_stop','force_majeure','unlogged') NOT NULL,
  `cause_note`           VARCHAR(200) NULL,
  `resp_party`           ENUM('company','supplier','operator','client',
                              'planned','force_majeure','none') NOT NULL DEFAULT 'none',

  `entry_id`             INT UNSIGNED NULL COMMENT 'سطر unit_entries المشتقّ (نموذج الساعة)',
  `entered_by`           INT UNSIGNED NULL,
  `sync_uuid`            CHAR(36) NULL,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sync` (`company_id`,`sync_uuid`),
  KEY `ix_day`   (`company_id`,`log_date`,`equipment_id`),
  KEY `ix_state` (`company_id`,`ops_state`),
  KEY `ix_resp`  (`company_id`,`resp_party`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='D02 §3.3 — سجلّ الزمن التشغيلي: ماذا حدث لكل ساعةٍ من الوقت المتاح';

-- ── ③ unit_approvals — سجلّ سلسلة الاعتماد (§4.2) ───────────────────────────
-- «قرارُ كلِّ مرحلةٍ سطرٌ إلحاقيٌّ بفاعله وطابعه — فيُقرأ تاريخُ السلسلة كاملًا،
--  وتُدار المرحلة الثالثة توازيًا بين المعنيّين فقط».
--
-- سجلٌّ جديدٌ مستقلٌّ تمامًا عن timesheet_approvals القائم — لا ترقيعَ ولا خلطَ
-- بين السجلّين، بنصّ المهمّة وبمقتضى §3.2 (hours_approvals ← unit_approvals).
CREATE TABLE IF NOT EXISTS `unit_approvals` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `entry_id`   INT UNSIGNED NOT NULL COMMENT 'unit_entries',

  -- المراحل السبع: ①الموقع ③الأطراف الأربعة (توازيًا) ④المبيعات ⑤المالية
  `stage`      ENUM('site','supplier','operator','supervisor','fleet',
                    'sales','finance') NOT NULL,
  `decision`   ENUM('approved','returned','rejected') NOT NULL,
  `actor_id`   INT UNSIGNED NOT NULL COMMENT 'users',
  `note`       VARCHAR(200) NULL,
  `decided_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sync_uuid`  CHAR(36) NULL,

  PRIMARY KEY (`id`),
  -- منقولٌ حرفيًّا عن §4.2. ⚠️ مسألةٌ مرفوعةٌ للمالك قبل المرحلة ٤: هذا القيد
  -- يمنع مرحلةً أعادت الوحدة (returned) من اعتمادها بعد استكمالها، بينما §4.3
  -- ينصّ على «الإعادة بالرقم نفسه … فتُستكمل وتعاد ضمن الدورة نفسها». لم يُغيَّر
  -- من عند المنفّذ — يُحسم بقرار المالك ثم يُنفَّذ بمهاجرةٍ تالية إن لزم.
  UNIQUE KEY `uq_stage_once` (`company_id`,`entry_id`,`stage`) COMMENT 'قرارٌ واحدٌ لكل مرحلة',
  KEY `ix_entry` (`company_id`,`entry_id`),
  KEY `ix_stage` (`company_id`,`stage`,`decided_at`),
  CONSTRAINT `fk_ua_entry` FOREIGN KEY (`entry_id`)
    REFERENCES `unit_entries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='D02 §4.2 — سلسلة الاعتماد الخماسية: سطرٌ إلحاقيٌّ لكل قرار';
