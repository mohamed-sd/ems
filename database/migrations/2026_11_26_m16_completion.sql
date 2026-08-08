-- M-16 · إكمال إدارة المخاطر المؤسسية إلى نطاق الوثيقة الكامل
-- ═══════════════════════════════════════════════════════════════════════════
-- المرجع: M-16 — إدارة المخاطر المؤسسية (خط الأساس 2026-08-06) — 20 شاشة
-- و353 عمودًا و28 فعلًا و26 حدثًا. الأساس بُني في 2026_11_19_m16_risk_foundation
-- بـ12 جدولًا و10 شاشات؛ وهذه الهجرة تُكمل الأربعة الناقصة والأعمدة الحاكمة.
--
-- ما تنفّذه بنيويًّا:
--   §6-1  الشاشات ١٢ و١٤ و١٦ (الحوادث · اللجنة · التقارير) بجداولها.
--   §7-2  risk.review يكتب risk_reviews · risk.incident.log يكتب risk_incidents ·
--         risk.report.export يكتب risk_export_log بتسعة بنود إلزامًا.
--   §9-1  الأعمدة الحاكمة السبعة: المستندُ الذي يُعتمد يحمل المعتمِدَ وتاريخَه
--         ومرجعَ التفويضِ والمرجعَ الأب — تُضاف حيث تلزم بطبيعة الشاشة.
--   §9-4  سجل التصدير: المصدِّرُ بصفته · المنظرُ · الأعمدةُ · الفلاترُ · المستبعَد.
--   §12-1 المرحلة ١٢ «المراقبة»: مصدرُ القراءة الآليِّ على المؤشر (source_key).
--   RK-03 عدمُ الرجعية: كلُّ جدولٍ هنا append-only بالخدمة — ولا دالةَ حذف.
--
-- النمط الحارس: CREATE ... IF NOT EXISTS للجداول · information_schema + PREPARE
-- للأعمدة (الخادم MySQL 8.4 لا MariaDB — فلا ADD COLUMN IF NOT EXISTS).
-- idempotent — يُعاد تشغيلها بلا أثر مزدوج.

-- ═══ ① risk_incidents — الحوادث والوقائع (الشاشة ١٢ · §11-2) ═══════════════
-- «Incident حدثٌ وقع وأنتج أثرًا · Near Miss لم تنتج أثرًا كاملًا لكنها تكشف
--  تعرضًا · Loss Event خطرٌ تحقق» — ثلاثتها لا تُخلط، ولكلٍّ نوعُه هنا.
-- «قد تُحقق خطرًا قائمًا فيُعاد تقييمُه» — realized_risk_id يحمل الرابط.
CREATE TABLE IF NOT EXISTS `risk_incidents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `incident_code` VARCHAR(16) NOT NULL COMMENT 'INC-000001',
  `itype` ENUM('واقعة','واقعة كادت تقع','واقعة خسارة') NOT NULL
      COMMENT '§11-2: Incident · Near Miss · Loss Event — لا تُخلط',
  `ru_id` INT UNSIGNED NULL COMMENT 'وحدة المخاطر المرشَّحة',
  `title` VARCHAR(255) NOT NULL,
  `details` TEXT NULL,
  `occurred_at` DATETIME NOT NULL COMMENT 'وقتُ الواقعة لا وقتُ التسجيل',
  `site_id` INT NULL,
  `equipment_id` INT NULL,
  `entity_type` VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'الكيان المتأثر — نوعُه',
  `entity_id` INT NULL,
  `root_cause` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'تحليلُ السبب الجذري',
  `injury_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `downtime_hours` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `loss_estimate` DECIMAL(18,2) NULL COMMENT 'تعرضٌ مقدَّرٌ — لا قيدَ ماليًّا (RK-06)',
  `currency` VARCHAR(8) NOT NULL DEFAULT '',
  `realized_risk_id` INT UNSIGNED NULL COMMENT 'خطرٌ قائمٌ تحقّق — يُعاد تقييمُه',
  `signal_id` INT UNSIGNED NULL COMMENT 'الإشارةُ التي وُلدت منها (SG-14)',
  `state` ENUM('logged','investigated','linked','closed') NOT NULL DEFAULT 'logged',
  `corrected_by_ref` VARCHAR(32) NOT NULL DEFAULT ''
      COMMENT 'RK: التصحيحُ بمرجعٍ لا حذفًا — رمزُ الواقعةِ المصحِّحة',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inc` (`company_id`, `incident_code`),
  KEY `ix_inc_risk` (`company_id`, `realized_risk_id`),
  KEY `ix_inc_when` (`company_id`, `occurred_at`),
  KEY `ix_inc_type` (`company_id`, `itype`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16 §6-1 الشاشة ١٢: الحوادث والوقائع — ثلاثةُ أنواعٍ لا تُخلط';

-- ═══ ② risk_reviews — المراجعات الدورية (المرحلة ١٣ · §7-2 risk.review) ═════
-- «مراجعةٌ جديدةٌ تحفظ السابقة» — append-only، والقرارُ أحدُ ثلاثة.
CREATE TABLE IF NOT EXISTS `risk_reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `risk_id` INT UNSIGNED NOT NULL,
  `review_code` VARCHAR(16) NOT NULL COMMENT 'RVW-000001',
  `trigger_kind` ENUM('دورية','حدث','فشل ضابط','تجاوز مؤشر') NOT NULL DEFAULT 'دورية'
      COMMENT 'المرحلة ١٣: دوريًّا أو عند حدثٍ أو فشلِ ضابطٍ أو تجاوزِ مؤشر',
  `level_before` VARCHAR(16) NOT NULL DEFAULT '',
  `level_after` VARCHAR(16) NOT NULL DEFAULT '',
  `decision` ENUM('استمرار','إغلاق','تصعيد') NOT NULL
      COMMENT 'مخرَجُ المرحلة ١٣: قرارُ استمرارٍ أو إغلاقٍ أو تصعيد',
  `findings_ar` TEXT NULL COMMENT 'ما وُجد — شاهدُ المراجعة',
  `assessment_id` INT UNSIGNED NULL COMMENT 'التقييمُ الذي أنتجته هذه المراجعة',
  `next_review_due` DATE NULL,
  `reviewed_by` INT NOT NULL COMMENT 'محللُ المخاطر — §14-1 لا يملك الخطرَ ولا يقبله',
  `approved_by` INT NULL COMMENT '§9-1 المعتمِد — الاسمُ والصفة',
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '§9-1 مرجعُ التفويض',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '§9-1 المرجعُ الأب — المراجعةُ السابقة',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rvw` (`company_id`, `review_code`),
  KEY `ix_rvw_risk` (`company_id`, `risk_id`, `created_at`),
  KEY `ix_rvw_due` (`company_id`, `next_review_due`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16 المرحلة ١٣: المراجعاتُ الدورية — الجديدةُ تحفظ السابقة';

-- ═══ ③ risk_committee — لجنة المخاطر ومحاضرها (الشاشة ١٤ · المرحلة ٨) ══════
CREATE TABLE IF NOT EXISTS `risk_committee` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `minute_code` VARCHAR(16) NOT NULL COMMENT 'CMT-000001',
  `meeting_date` DATE NOT NULL,
  `cycle_ar` VARCHAR(32) NOT NULL DEFAULT 'ربع سنوي',
  `attendees_ar` TEXT NULL COMMENT 'الحاضرون بصفاتهم',
  `agenda_ar` TEXT NULL,
  `resolutions_ar` TEXT NULL COMMENT 'القراراتُ — مخرَجُ المرحلة ٨: محضرُ لجنة',
  `risks_reviewed` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `appetite_id` INT UNSIGNED NULL COMMENT 'الشهيةُ المعتمدةُ في هذا المحضر',
  `state` ENUM('draft','approved') NOT NULL DEFAULT 'draft'
      COMMENT 'RK: المعتمدُ لا يُعدَّل — والتصحيحُ محضرٌ جديدٌ بمرجعه',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL COMMENT '§9-1 المعتمِد — الرئيسُ التنفيذي',
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'المحضرُ السابقُ في السلسلة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cmt` (`company_id`, `minute_code`),
  KEY `ix_cmt_date` (`company_id`, `meeting_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16 §6-1 الشاشة ١٤: لجنةُ المخاطرِ ومحاضرُها';

CREATE TABLE IF NOT EXISTS `risk_committee_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `minute_id` INT UNSIGNED NOT NULL,
  `risk_id` INT UNSIGNED NULL,
  `item_ar` VARCHAR(255) NOT NULL,
  `resolution_ar` VARCHAR(255) NOT NULL DEFAULT '',
  `owner_user_id` INT NULL,
  `due_date` DATE NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cmti_minute` (`company_id`, `minute_id`),
  KEY `ix_cmti_risk` (`company_id`, `risk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16: بنودُ محضرِ اللجنةِ وقراراتُها بمسؤولٍ ومهلة';

-- ═══ ④ risk_export_log — سجل التصدير بتسعة بنود (§9-4 · الشاشة ١٦) ═════════
-- «التصديرُ لا يغيّر بيانَ المجالِ لكنه يكتب سجلَّ تصديرٍ بتسعةِ بنودٍ إلزامًا
--  — فليس قارئًا لا يكتب» (§8 التصنيف الرباعي).
CREATE TABLE IF NOT EXISTS `risk_export_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `exported_by` INT NOT NULL COMMENT '① المصدِّر',
  `actor_capacity` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '② بصفته',
  `screen_code` VARCHAR(64) NOT NULL COMMENT '③ الشاشة',
  `view_key` VARCHAR(48) NOT NULL DEFAULT 'default' COMMENT '④ المنظر',
  `columns_text` TEXT NULL COMMENT '⑤ الأعمدة المصدَّرة',
  `filters_text` TEXT NULL COMMENT '⑥ الفلاتر المطبَّقة',
  `blocked_text` TEXT NULL COMMENT '⑦ المستبعَدُ بالصلاحية — الحقولُ الحساسةُ المحجوبة',
  `row_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '⑧ عددُ الصفوف',
  `exported_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '⑨ الوقت',
  `fmt` VARCHAR(12) NOT NULL DEFAULT 'xlsx',
  PRIMARY KEY (`id`),
  KEY `ix_rxl_who` (`company_id`, `exported_by`, `exported_at`),
  KEY `ix_rxl_screen` (`company_id`, `screen_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-16 §9-4: سجلُّ التصديرِ — تسعةُ بنودٍ لكلِّ ملفٍّ يخرج';

-- ═══ ⑤ الأعمدة الحاكمة على المستندات التي تُعتمد (§9-1 · §9-2) ═════════════
-- الشاشةُ التي تُنتج مستندًا يُعتمد تحتاج السبعةَ كاملةً: الكيانُ (قائم) ·
-- المُنشئُ وتاريخُه (قائم) · المعتمِدُ وتاريخُه ومرجعُ التفويضِ والمرجعُ الأب.
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'risk_assessments' AND column_name = 'approved_by');
SET @ddl = IF(@c = 0,
  'ALTER TABLE risk_assessments
     ADD COLUMN approved_by INT NULL COMMENT ''§9-1 المعتمِد — الاسم والصفة'' AFTER challenged_by,
     ADD COLUMN approved_at DATETIME NULL COMMENT ''§9-1 تاريخ الاعتماد'' AFTER approved_by,
     ADD COLUMN authority_ref VARCHAR(120) NOT NULL DEFAULT '''' COMMENT ''§9-1 مرجع التفويض'' AFTER approved_at,
     ADD COLUMN parent_ref VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''§9-1 المرجع الأب — النسخة السابقة'' AFTER authority_ref',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'risk_controls' AND column_name = 'approved_by');
SET @ddl = IF(@c = 0,
  'ALTER TABLE risk_controls
     ADD COLUMN created_by INT NULL COMMENT ''§9-1 المُنشئ'' AFTER active,
     ADD COLUMN approved_by INT NULL COMMENT ''§9-1 المعتمِد'' AFTER created_by,
     ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
     ADD COLUMN authority_ref VARCHAR(120) NOT NULL DEFAULT '''' AFTER approved_at,
     ADD COLUMN parent_ref VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''الخطر الأب أو الضابط المستبدَل'' AFTER authority_ref',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'risk_treatments' AND column_name = 'authority_ref');
SET @ddl = IF(@c = 0,
  'ALTER TABLE risk_treatments
     ADD COLUMN authority_ref VARCHAR(120) NOT NULL DEFAULT '''' COMMENT ''§9-1 مرجع التفويض'' AFTER verified_at,
     ADD COLUMN parent_ref VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''§9-1 المرجع الأب — رمز الخطر'' AFTER authority_ref',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'risk_acceptances' AND column_name = 'authority_ref');
SET @ddl = IF(@c = 0,
  'ALTER TABLE risk_acceptances
     ADD COLUMN authority_ref VARCHAR(120) NOT NULL DEFAULT '''' COMMENT ''§9-1 مرجع التفويض — بأي صفة اعتمد'' AFTER authority,
     ADD COLUMN parent_ref VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''§9-1 المرجع الأب — رمز الخطر'' AFTER authority_ref,
     ADD COLUMN compensating_ctl VARCHAR(255) NOT NULL DEFAULT '''' COMMENT ''§7-2 ضوابط معوِّضة'' AFTER note,
     ADD COLUMN withdrawn_by INT NULL COMMENT ''سحبُ القبولِ من الجهة نفسها أو أعلى'' AFTER compensating_ctl,
     ADD COLUMN withdrawn_at DATETIME NULL AFTER withdrawn_by',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══ ⑥ المرحلة ١٢ «المراقبة»: مصدرُ القراءة الآلي على المؤشر ══════════════
-- «مؤشراتُ الخطرِ والضابطِ تُقرأ من النظامِ آليًّا» — source_key يربط المؤشرَ
-- بقارئٍ في محرّك المؤشرات، وread_mode يفصل الآليَّ عن اليدوي إعلانًا لا ادّعاءً.
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'risk_kris' AND column_name = 'source_key');
SET @ddl = IF(@c = 0,
  'ALTER TABLE risk_kris
     ADD COLUMN source_key VARCHAR(48) NOT NULL DEFAULT '''' COMMENT ''مفتاحُ القارئ في محرّك المؤشرات'' AFTER source_ar,
     ADD COLUMN read_mode ENUM(''آلي'',''يدوي'') NOT NULL DEFAULT ''يدوي''
         COMMENT ''المرحلة ١٢: الآليُّ يُقرأ من النظام — والالتزامُ يُقاس ولا يُدَّعى'' AFTER source_key,
     ADD COLUMN warn_num DECIMAL(18,4) NULL COMMENT ''حدُّ الإنذار رقمًا للمقارنة الآلية'' AFTER read_mode,
     ADD COLUMN critical_num DECIMAL(18,4) NULL COMMENT ''الحدُّ الحرج رقمًا'' AFTER warn_num,
     ADD COLUMN direction ENUM(''تصاعدي'',''تنازلي'') NOT NULL DEFAULT ''تصاعدي''
         COMMENT ''أيُّ الاتجاهين يُعدُّ تجاوزًا'' AFTER critical_num,
     ADD COLUMN last_read_by INT NULL AFTER last_read_at',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══ ⑦ الشهية: أنماطُ الخطةِ العامةِ الثلاثة (§13-2) ══════════════════════
-- «يحددها الرئيسُ التنفيذيُّ — وتُعاد حدودُ التصعيدِ آليًّا عند تغييرها».
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'risk_appetite' AND column_name = 'approved_by');
SET @ddl = IF(@c = 0,
  'ALTER TABLE risk_appetite
     ADD COLUMN approved_by INT NULL COMMENT ''§13-1 الرئيسُ التنفيذيُّ حصرًا'' AFTER updated_by,
     ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
     ADD COLUMN authority_ref VARCHAR(120) NOT NULL DEFAULT '''' AFTER approved_at,
     ADD COLUMN prev_appetite_ar VARCHAR(255) NOT NULL DEFAULT ''''
         COMMENT ''§7-1: اعتمادُ شهيةٍ جديدةٍ يحفظ السابقة'' AFTER authority_ref',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══ ⑧ الإشارات: مفتاحُ عطالةِ القاعدةِ الآلية ═══════════════════════════
-- «المعلَّقُ يُرفع بمفتاحِه — والإعادةُ ترجع مرجعَ الأولِ ولا تُنشئ ثانيًا»
-- (§7-2 risk.field.sync): يكفيه uq_sync القائمُ من هجرةِ الأساس على sync_uuid.
-- ويلزم للقواعدِ الآليةِ (§13-5) مفتاحٌ ثانٍ يمنع تكرارَ إشارةِ القاعدةِ نفسِها
-- لنفس الواقعة. وهو NULL افتراضًا عمدًا: الإشارةُ اليدويةُ لا قاعدةَ لها،
-- وNULL لا يتصادم في فريدِ MySQL — فتُقبل اليدويةُ بلا حدٍّ ويُصدُّ الآليُّ المكرر.
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'risk_signals' AND column_name = 'rule_key');
SET @ddl = IF(@c = 0,
  'ALTER TABLE risk_signals
     ADD COLUMN rule_key VARCHAR(64) NULL DEFAULT NULL
         COMMENT ''§13-5: مفتاحُ عطالةِ القاعدةِ الآلية — NULL لليدوية (لا تصادمَ في الفريد)'' AFTER sg_code',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- تصحيحٌ رجعيّ: تشغيلٌ سابقٌ أنشأ العمود NOT NULL DEFAULT '' فتصادم الفريد.
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'risk_signals'
            AND column_name = 'rule_key' AND is_nullable = 'NO');
SET @ddl = IF(@c = 1,
  'ALTER TABLE risk_signals
     MODIFY COLUMN rule_key VARCHAR(64) NULL DEFAULT NULL
       COMMENT ''§13-5: مفتاحُ عطالةِ القاعدةِ الآلية — NULL لليدوية''',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE `risk_signals` SET `rule_key` = NULL WHERE `rule_key` = '';

-- الفريدُ القديمُ الزائد (نُسخ في تشغيلٍ فاشلٍ سابق): uq_sync يغنيه.
SET @c = (SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = 'risk_signals' AND index_name = 'uq_sig_sync');
SET @ddl = IF(@c > 0, 'ALTER TABLE risk_signals DROP INDEX uq_sig_sync', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = 'risk_signals' AND index_name = 'uq_sig_rule');
SET @ddl = IF(@c = 0,
  'ALTER TABLE risk_signals ADD UNIQUE KEY uq_sig_rule (company_id, rule_key)',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══ ⑨ حكمُ الشهيةِ على السجل (المرحلة ٩ · محرّك الشهية) ══════════════════
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'risk_register' AND column_name = 'appetite_verdict');
SET @ddl = IF(@c = 0,
  'ALTER TABLE risk_register
     ADD COLUMN appetite_verdict ENUM(''داخل الشهية'',''فوق الشهية'',''فوق حد التحمل'',''محظور'') NULL
         COMMENT ''المرحلة ٩: حكمُ شهيةٍ آليٌّ — لا يُدخَل يدويًّا'' AFTER current_level,
     ADD COLUMN appetite_checked_at DATETIME NULL AFTER appetite_verdict,
     ADD COLUMN exposure_amount DECIMAL(18,2) NULL
         COMMENT ''§6-2: التعرضُ الماليُّ المقدَّر — تقديرٌ لا قيد (RK-06)'' AFTER appetite_checked_at,
     ADD COLUMN exposure_currency VARCHAR(8) NOT NULL DEFAULT '''' AFTER exposure_amount,
     ADD COLUMN target_level VARCHAR(16) NOT NULL DEFAULT ''''
         COMMENT ''§12-3: الخطرُ المستهدفُ — يُقاس عند الإغلاق'' AFTER exposure_currency,
     ADD COLUMN control_effectiveness VARCHAR(24) NOT NULL DEFAULT ''''
         COMMENT ''§12-3: فعاليةُ الضوابطِ المجمَّعة'' AFTER target_level,
     ADD COLUMN confidence VARCHAR(16) NOT NULL DEFAULT ''''
         COMMENT ''§12-3: درجةُ الثقةِ — تُعلَن ولا تُخفى'' AFTER control_effectiveness',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
