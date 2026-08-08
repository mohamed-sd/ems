-- update0012 · ص1+ص2+ص3 — إعادة هيكلة دليل الحسابات والأبعاد التسعة
-- ═══════════════════════════════════════════════════════════════════════════
-- المرجع: EQUIPATION-COA-01 — دليل الحسابات المعاد هيكلته (docs/update0012).
-- المرصود في الوثيقة: 2798 حسابًا كلُّها في المستوى الرابعِ · 49 كودَ أبٍ بلا
-- صفٍّ · 263 حسابًا باسمِ شخصٍ · قائمتان فقط ممكنتان. والمرصودُ في القاعدةِ
-- الحيةِ: 124 حسابًا بشجرةٍ هجينةٍ وأكوادٍ تتصادم دلاليًّا مع أكوادِ الوثيقة
-- (5101 هنا «إيجارُ معداتِ الموردين» وفي الوثيقةِ «تكلفةُ المشغّلين الدائمين»).
--
-- ما تنفّذه هذه الهجرةُ بنيويًّا (والبياناتُ في tools/u12_coa_restructure.php):
--   ① أعمدةُ الشجرةِ الأربعية: المستوى · الأبُ بالكود · طبيعةُ الرصيدِ ·
--      القائمةُ وبندُها · نشاطُ التدفقِ · الأبعادُ الإلزامية · وسمُ القانونية.
--   ② أعمدةُ الأبعادِ الخمسةِ الناقصةِ على سطرِ القيد: الموقعُ D3 · الطرفُ
--      المقابلُ D6 · نموذجُ العملِ D7 · العقدُ D8 · نوعُ العقدِ D9 —
--      والقائمُ يحمل D1 الكيانَ وD2 المشروعَ وD4 مركزَ التكلفةِ وD5 المعدة.
--      ◆ وعمودُ legacy_account_id يحفظ التصنيفَ الأصليَّ فالترحيلُ يُعكس.
--   ③ fin_coa_migration — خريطةُ الترحيلِ (R10: بخريطةٍ لا بحذف) بأرصدةِ
--      قبلَ وبعدُ لكل حساب، وهي شاهدُ تقريرِ التساوي.
--   ④ fin_contract_types — أنواعُ عقودِ الموظفينَ الثمانيةِ والممولينَ العشرةِ
--      بحساباتها وحكمِها المحاسبيِّ (البُعد D9).
--   ⑤ fin_posting_matrix — مصفوفةُ الترحيلِ لكل إدارة (27 صفًّا).
--
-- ◆ لا حذفَ في هذه الهجرةِ ولا في أداتها — الموروثُ يُوسَم ويُعطَّل ويبقى.
-- النمط الحارس: information_schema + PREPARE (يعمل على MySQL 8.4 وMariaDB معًا).

-- ═══ ① أعمدةُ الشجرةِ الأربعيةِ على دليل الحسابات ═════════════════════════
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'fin_chart_of_accounts'
            AND column_name = 'acc_level');
SET @ddl = IF(@c = 0,
  'ALTER TABLE fin_chart_of_accounts
     ADD COLUMN name_en VARCHAR(190) NOT NULL DEFAULT '''' COMMENT ''الاسمُ الإنجليزيُّ من الوثيقة'' AFTER name,
     ADD COLUMN acc_level TINYINT UNSIGNED NOT NULL DEFAULT 4
         COMMENT ''COA §01: أربعةُ مستوياتٍ — 1 جذرٌ · 2 تجميعيٌّ · 3 يُقيَّد عليه · 4 تفصيليٌّ موروث'' AFTER account_type,
     ADD COLUMN parent_code VARCHAR(30) NOT NULL DEFAULT ''''
         COMMENT ''الأبُ بالكودِ — الشجرةُ تُبنى بالكودِ لا بالمعرّفِ وحدَه'' AFTER acc_level,
     ADD COLUMN balance_nature ENUM(''debit'',''credit'') NOT NULL DEFAULT ''debit''
         COMMENT ''طبيعةُ الرصيد — مدينٌ أو دائن'' AFTER parent_code,
     ADD COLUMN statement_code VARCHAR(8) NOT NULL DEFAULT ''''
         COMMENT ''S1..S5: المركزُ · دخلُ الشركةِ · دخلُ المشروعِ · التدفقاتُ · حقوقُ الملكية'' AFTER balance_nature,
     ADD COLUMN statement_line VARCHAR(190) NOT NULL DEFAULT '''' COMMENT ''بندُ القائمة'' AFTER statement_code,
     ADD COLUMN cashflow_activity ENUM(''operating'',''investing'',''financing'',''none'') NOT NULL DEFAULT ''none''
         COMMENT ''R4: بدونه لا تُنتَج قائمةُ التدفقاتِ إلا يدويًّا'' AFTER statement_line,
     ADD COLUMN required_dims VARCHAR(64) NOT NULL DEFAULT ''''
         COMMENT ''R9: الأبعادُ التي يلزمها هذا الحسابُ — D1,D2,D5 …'' AFTER cashflow_activity,
     ADD COLUMN is_canonical TINYINT(1) NOT NULL DEFAULT 0
         COMMENT ''◆ 1 = من شجرةِ الوثيقةِ المعادِ هيكلتُها · 0 = موروثٌ محجورٌ بخريطة'' AFTER required_dims,
     ADD COLUMN coa_note VARCHAR(255) NOT NULL DEFAULT '''' AFTER is_canonical',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = 'fin_chart_of_accounts'
            AND index_name = 'ix_coa_canon');
SET @ddl = IF(@c = 0,
  'ALTER TABLE fin_chart_of_accounts
     ADD INDEX ix_coa_canon (company_id, is_canonical, acc_level),
     ADD INDEX ix_coa_stmt (company_id, statement_code)',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══ ② الأبعادُ الخمسةُ الناقصةُ على سطرِ القيد + قابليةُ عكسِ الترحيل ══════
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'fin_journal_lines'
            AND column_name = 'counterparty_type');
SET @ddl = IF(@c = 0,
  'ALTER TABLE fin_journal_lines
     ADD COLUMN site_id INT NULL COMMENT ''D3 الموقعُ — إلزاميٌّ في القيودِ الميدانية'' AFTER equipment_id,
     ADD COLUMN counterparty_type VARCHAR(24) NOT NULL DEFAULT ''''
         COMMENT ''D6 نوعُ الطرفِ: client · supplier · employee · financier · partner'' AFTER cost_center,
     ADD COLUMN counterparty_id INT NULL
         COMMENT ''◆ D6 يحلُّ محلَّ أسماءِ الأشخاصِ في الشجرة (R2)'' AFTER counterparty_type,
     ADD COLUMN business_model VARCHAR(16) NOT NULL DEFAULT ''''
         COMMENT ''D7 نموذجُ العمل: hour · ton · meter — يُنتج ربحيةَ كل نموذج'' AFTER counterparty_id,
     ADD COLUMN contract_id INT NULL COMMENT ''D8 العقدُ — إلزاميٌّ في الإيرادِ والجزاءات'' AFTER business_model,
     ADD COLUMN contract_type_code VARCHAR(12) NOT NULL DEFAULT ''''
         COMMENT ''D9 نوعُ العقد: EC-01..08 للموظفينَ · FC-01..10 للممولين'' AFTER contract_id,
     ADD COLUMN legacy_account_id INT NULL
         COMMENT ''◆ التصنيفُ الأصليُّ قبل ترحيلِ الشجرة — فالترحيلُ يُعكس ولا يُمحى'' AFTER account_id,
     ADD COLUMN posting_rule_code VARCHAR(16) NOT NULL DEFAULT ''''
         COMMENT ''صفُّ مصفوفةِ الترحيلِ الذي اشتقَّ الحسابَ — ولا يُختار يدويًّا'' AFTER legacy_account_id',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = 'fin_journal_lines'
            AND index_name = 'ix_jl_dims');
SET @ddl = IF(@c = 0,
  'ALTER TABLE fin_journal_lines
     ADD INDEX ix_jl_dims (company_id, contract_id, business_model),
     ADD INDEX ix_jl_party (company_id, counterparty_type, counterparty_id),
     ADD INDEX ix_jl_legacy (legacy_account_id)',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══ ③ fin_coa_migration — خريطةُ الترحيلِ وشاهدُ التساوي (R10) ════════════
CREATE TABLE IF NOT EXISTS `fin_coa_migration` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `old_account_id` INT NULL COMMENT 'معرّفُ الحسابِ الموروثِ — يبقى ولا يُحذف',
  `old_code` VARCHAR(40) NOT NULL COMMENT 'الكودُ قبل الوسمِ الموروث',
  `old_name` VARCHAR(190) NOT NULL DEFAULT '',
  `new_account_id` INT NULL COMMENT 'الحسابُ القانونيُّ من الشجرةِ المعادِ هيكلتُها',
  `new_code` VARCHAR(30) NOT NULL,
  `dim_key` VARCHAR(8) NOT NULL DEFAULT '' COMMENT 'البُعدُ الذي حلَّ محلَّ التفصيل — D5 · D6 …',
  `dim_value` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'قيمتُه — اسمُ الطرفِ أو المعدة',
  `balance_before` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'مدين − دائن قبلَ الترحيل',
  `balance_after` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'مدين − دائن بعدَه على الحسابِ الجديد',
  `lines_moved` INT NOT NULL DEFAULT 0 COMMENT 'سطورُ القيدِ التي أُعيد توجيهُها',
  `rule_note` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'قاعدةُ الترحيل: R2 · R8 · مطابقةٌ دلالية',
  `migrated_by` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coamig` (`company_id`, `old_code`),
  KEY `ix_coamig_new` (`company_id`, `new_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='COA R10: الترحيلُ بخريطةٍ لا بحذف — وتقريرٌ يثبت تساوي الأرصدة';

-- ═══ ④ fin_contract_types — أنواعُ العقودِ (البُعد D9) ═════════════════════
CREATE TABLE IF NOT EXISTS `fin_contract_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `type_code` VARCHAR(12) NOT NULL COMMENT 'EC-01..08 · FC-01..10',
  `family` ENUM('employee','financier') NOT NULL,
  `name_ar` VARCHAR(190) NOT NULL,
  `name_en` VARCHAR(190) NOT NULL DEFAULT '',
  `accounts_csv` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'أكوادُ الحساباتِ التي يُقيَّد عليها',
  `cost_nature` VARCHAR(190) NOT NULL DEFAULT '',
  `accounting_rule` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الحكمُ المحاسبيُّ نصًّا من الوثيقة',
  `capitalizes` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '◆ الإجارةُ المنتهيةُ بالتمليكِ تُرسمَل والتشغيليُّ لا',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ctype` (`company_id`, `type_code`),
  KEY `ix_ctype_family` (`company_id`, `family`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='COA §03/§04: ثمانيةُ عقودِ موظفينَ وعشرةُ عقودِ ممولينَ — البُعد D9';

-- ═══ ⑤ fin_posting_matrix — مصفوفةُ الترحيلِ لكل إدارة (27 صفًّا) ══════════
CREATE TABLE IF NOT EXISTS `fin_posting_matrix` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `rule_code` VARCHAR(16) NOT NULL COMMENT 'OPS · SIT · MNT · WRK · TRP · PRC · INV · SAL · SUP · FLT · CAP · HRM · GOV …',
  `dept_ar` VARCHAR(120) NOT NULL,
  `source_event` VARCHAR(190) NOT NULL COMMENT 'الحدثُ المصدرُ الذي يولّد القيد',
  `revenue_accounts` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'أكوادُ الإيرادِ بحسب النموذج',
  `cost_accounts` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'أكوادُ التكلفة',
  `required_dims` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'الأبعادُ الإلزاميةُ لهذا الصف',
  `gate_ar` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'البوابةُ قبل الترحيل',
  `governing_rule` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الحكمُ الحاكمُ نصًّا',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `version_no` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'العكس: المصفوفةُ السابقةُ تُستعاد بنسختها',
  `updated_by` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pmatrix` (`company_id`, `rule_code`, `version_no`),
  KEY `ix_pmatrix_active` (`company_id`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='MAP-7 الورقة 37: الحسابُ يُشتق من نوعِ الواقعةِ ونموذجِ العملِ ونوعِ العقد — ولا يُختار يدويًّا';
