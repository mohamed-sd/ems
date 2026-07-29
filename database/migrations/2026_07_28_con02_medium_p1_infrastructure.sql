-- ═══════════════════════════════════════════════════════════════════════════
-- CON-02 · المستوى المتوسط — المرحلة ① : البنيةُ التحتية — 2026-07-28
-- ───────────────────────────────────────────────────────────────────────────
-- **دفعةُ بنيةٍ لا دفعةُ سلوك.** أعمدةٌ وجداولُ لا يقرؤها منطقٌ بعد — فالنظامُ
-- بعد هذا الملف متطابقٌ سلوكيًّا مع ما قبله: نفسُ الفوترة ونفسُ الأحكام ونفسُ
-- الأرقام. القارئُ (AttributionService · hourPolicy بمفتاح البند) المرحلةُ ③،
-- والشاشةُ التي تملأ المصفوفةَ المرحلةُ ②، ولا يُقلب `EMS_ATTRIBUTION_MATRIX`
-- إلى `on` قبل أن تُملأ العقودُ التسعةُ وتُجيزها المالية (ق-24).
--
-- المرجعُ الحاكم: `docs/CON02_MEDIUM_TIER_DECISIONS_ar.md` (٢٧ قرارَ مالكٍ +
-- ٦ قراراتٍ هندسية). وما يخصّ هذا الملف: ق-1 · ق-3 · ق-9 · ق-12 · ق-19 · ق-25
-- وهـ-1 · هـ-3.
--
-- ═══ قراراتُ المالك في هذه الجلسة (2026-07-28) ═══
--   ① **الإصلاحُ الكاملُ لمفتاح `contract_hour_policies` الفريد.** والسببُ
--      اكتشافٌ مقيسٌ لم يبلغه البرومت: المفتاحُ `uq_policy_rule` **لا يبيت
--      اليومَ إطلاقًا** لا ناقصًا وحسب. فـ`contract_ref` فارغٌ في الصفوف الـ49
--      كلِّها، وMySQL تعدّ الـNULLات متمايزةً — والبرهانُ أن أربعةً وعشرين صفَّ
--      مشغّلٍ تتعايش اليومَ تحت تُوءَمٍ واحد:
--          (4 · operator · NULL · NULL · 2027-02-01) × 24
--      فسدُّ `obligation_type` وحدَه كان سيكون تجميلًا: الحكمُ العامُّ يبقى
--      قابلًا للتكرار وقراءتُه غيرَ حتمية — وهو الجدولُ الذي ستقوم عليه أحكامُ
--      الإسناد كلُّها في المرحلة ③. ولا كودَ يشير إلى المفتاح بالاسم (لا
--      `ON DUPLICATE KEY` ولا `REPLACE` — قيس بالمسح)، فالترقيةُ آمنة.
--   ② وسمُ وحدة قيد الحد الأدنى المضمون (هـ-6): **`min_guarantee`** — يطابق
--      `contract_commitments.commitment_type='min_guaranteed'` القائم فيقرأ
--      المدقِّقُ الرابطَ بلا شرح. (يُستعمل في المرحلة ④ · `claim_lines.unit_type`
--      نوعُه varchar(16) فيسعه بلا هجرة.)
--   ③ مفاتيحُ أحداث الجزاء والحافز: **عائلتان مستقلتان** على نمط
--      `attribution.decided` — `penalty.assessed/approved/waived` و
--      `incentive.assessed/approved`. (تُسجَّل ثوابتَ في المرحلة ④ مع منتِجها،
--      لا هنا: ثابتٌ بلا مستدعٍ كودٌ ميت.)
--   ④ شارةُ «التزامٌ ينكسر» (ق-21): **اشتقاقٌ حيٌّ عند فتح الشاشة** — فلا عمودَ
--      حالةٍ يبيت ولا مهمةَ دوريةٍ تُصان. ولذلك **لا أثرَ لها في هذا الملف**.
--
-- ═══ قراراتٌ هندسيةٌ ضمن الصلاحية (معلَّلةٌ ومُعلَنة) ═══
--   • **لا ترحيلَ رجعيًّا لأحكام الـ256 سطرًا القائمة** — تبقى NULL. تنفيذًا
--     لميل المالك المعلَن ولـهـ-3 («لا رجعية»): المصفوفةُ لم تكن موجودةً وقتَ
--     وقوعها، فملؤها منها اختراعُ نيّةٍ ماضيةٍ لا نملكها. **ووسمُ «ما قبل
--     المصفوفة» لا يحتاج عمودًا**: `decided_at IS NULL` يقولها بنيويًّا.
--   • **`commitment_ref` بـ`INT UNSIGNED`** لا `INT` كما في نصّ البرومت —
--     لأن `contract_commitments.id` هو `int unsigned`، والنوعُ المخالفُ يجعل
--     الـFK **مستحيلًا بنيويًّا** (نفسُ الدرس المقيس في دفعة الأساس مع
--     `contract_hour_policies.contract_ref`). فيُطابَق النوعُ ويصحّ القيد.
--   • **إحكامُ `contract_obligations.penalty_rule_id` بـFK حقيقيّ** الآن —
--     تركته دفعةُ الأساس بلا هدفٍ صراحةً «حتى تُبنى contract_penalty_rules»،
--     وقد بُنيت في هذا الملف، فيُغلق العهد. `ON DELETE SET NULL`: حذفُ قاعدةِ
--     جزاءٍ يترك بندَ الالتزام قائمًا بلا جزاء، ولا يُسقط البند.
--   • **فخُّ NULL يُسدُّ في `contract_penalty_rules` بالوقاية لا بالعلاج** —
--     `commitment_ref` فارغٌ مشروعٌ (قاعدةٌ على مستوى العقد لا على بندٍ بعينه)،
--     فمفتاحُها الفريدُ يقوم على عمودٍ محسوبٍ من أول يوم.
--
-- إضافيٌّ محض: جدولٌ جديدٌ وأعمدةٌ Nullable وترقيةُ مفتاحٍ فريد — ولا عمودَ
-- قائمٌ يُمسّ ولا صفَّ بياناتٍ واحدٌ يتغيّر (صفرُ `UPDATE` في هذا الملف).
--
-- خطةُ الرجوع:
--   ① DROP TABLE `contract_penalty_rules` (بعد إسقاط FK الـpenalty_rule_id).
--   ② إسقاطُ العمودين من `contracts` والتسعةِ من `unit_time_log`.
--   ③ ردُّ مفتاح `contract_hour_policies`:
--        ALTER TABLE contract_hour_policies
--          ADD UNIQUE KEY uq_policy_rule
--              (company_id, party_scope, contract_ref, ops_state, effective_from),
--          DROP INDEX uq_policy_scope_key, DROP COLUMN policy_key,
--          DROP COLUMN obligation_type;
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ═══ ١-أ · سجلُّ الإسناد: توسيعُ `unit_time_log` القائم (ق-3) ════════════════
--    لا جدولَ `unit_attributions` جديد: جدولان يحملان الزمنَ نفسَه بابُ تعارضٍ
--    دائم، و`unit_time_log` **هو** سجلُّ الإسناد وظيفيًّا (256 صفًّا مرتبطًا
--    كلُّه بوحدةٍ — قيس: صفرُ صفٍّ بـentry_id فارغ).
--
--    ⚠️ هـ-1: `obligation_type` يقبل NULL **فقط** حين `ops_state='actual_work'`
--    (التشغيلُ الفعليُّ لا بندَ التزامٍ له)، وإلزاميٌّ لكل ما عداه —
--    **يفرضه الحارسُ في المرحلة ③ لا المخطط**. ولا مفتاحَ فريدًا على العمود
--    فلا فخَّ NULL هنا.
--
--    والأحكامُ الثلاثةُ `billable`/`supplier_countable`/`operator_countable`
--    **لقطةٌ مخزَّنةٌ لا اشتقاقٌ حيّ** (هـ-3): بها تتحقق «لا رجعية» (§6) مجانًا،
--    والاشتقاقُ الحيُّ يجعل ملحقَ اليومِ يغيّر فاتورةَ الشهر الماضي.
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'unit_time_log'
                  AND COLUMN_NAME = 'obligation_type'),
    'ALTER TABLE `unit_time_log`
       ADD COLUMN `obligation_type` ENUM(''fuel'',''access_road'',''loading_equipment'',''equipment_readiness'',''operators'',''permits_safety'',''utilities'',''catering_camp'',''force_majeure'') NULL
           COMMENT ''بندُ الالتزام المسؤول (نفسُ قاموس contract_obligations) — NULL مشروعٌ لـactual_work وحدَه (هـ-1 · يفرضه الحارس)'' AFTER `resp_party`,
       ADD COLUMN `billable` TINYINT(1) NULL
           COMMENT ''حكمُ الفوترة: أيُفوتر هذا الزمنُ على العميل؟ لقطةٌ لا اشتقاق (هـ-3)'' AFTER `obligation_type`,
       ADD COLUMN `supplier_countable` TINYINT(1) NULL
           COMMENT ''حكمُ المورد: أيُحتسب هذا الزمنُ في استحقاقه؟ لقطةٌ لا اشتقاق'' AFTER `billable`,
       ADD COLUMN `operator_countable` TINYINT(1) NULL
           COMMENT ''حكمُ المشغّل: أيُحتسب هذا الزمنُ في استحقاقه؟ لقطةٌ لا اشتقاق'' AFTER `supplier_countable`,
       ADD COLUMN `decided_by` INT UNSIGNED NULL
           COMMENT ''مَن اعتمد الإسناد (المشرف · ق-4). NULL أي سطرٌ ما قبل المصفوفة — لا يُملأ رجعيًّا'' AFTER `operator_countable`,
       ADD COLUMN `decided_at` DATETIME NULL
           COMMENT ''لحظةُ اعتماد الإسناد — وغيابُه وسمُ «ما قبل المصفوفة» بنيويًّا'' AFTER `decided_by`,
       ADD COLUMN `objection_state` ENUM(''none'',''objected'',''resolved'') NOT NULL DEFAULT ''none''
           COMMENT ''الاعتراضُ المصغَّر (ق-25) — والبندُ المعترَضُ عليه لا يجمّد بقيةَ الواقعة'' AFTER `decided_at`,
       ADD COLUMN `objection_ref` VARCHAR(60) NULL
           COMMENT ''مرجعُ الاعتراض — مستندٌ أو محضرٌ يحسمه الدور 19'' AFTER `objection_state`,
       ADD COLUMN `objection_reason` VARCHAR(255) NULL
           COMMENT ''سببُ الاعتراض — إلزاميٌّ عند الاعتراض (يفرضه التطبيق)'' AFTER `objection_ref`,
       ADD KEY `ix_attribution` (`company_id`, `obligation_type`, `decided_at`),
       ADD KEY `ix_objection` (`company_id`, `objection_state`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;


-- ═══ ١-ب · المحورُ الثاني في حكم الساعة (ق-1): (حالة × بند × طرف) ═══════════
--    السياسةُ اليومَ مفتاحُها (حالةُ الساعة × الطرف). وتضيف الوثيقةُ محورَ
--    **بند الالتزام** فيصير الحكمُ ثلاثيًّا. NULL = قاعدةٌ عامةٌ للحالة بصرف
--    النظر عن البند — وهو **عُرفُ الجدول نفسِه** (contract_ref = NULL أي
--    «الافتراضيّ»)، فلا تُخترع قيمةٌ حارسةٌ تخالفه.
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_hour_policies'
                  AND COLUMN_NAME = 'obligation_type'),
    'ALTER TABLE `contract_hour_policies`
       ADD COLUMN `obligation_type` ENUM(''fuel'',''access_road'',''loading_equipment'',''equipment_readiness'',''operators'',''permits_safety'',''utilities'',''catering_camp'',''force_majeure'') NULL
           COMMENT ''بندُ الالتزام (CON-02 §4) — المحورُ الثاني للحكم. NULL = قاعدةٌ عامةٌ للحالة (عُرفُ الجدول: NULL أي الأعمّ)'' AFTER `ops_state`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

--    ── العمودُ المحسوب: مفتاحٌ يبيت أسنانًا (قرارُ المالك ①) ────────────────
--    قيمٌ حارسةٌ بديلةٌ عن NULL على المحاور الأربعة، فينتهي فخُّ التمايز؛
--    و**NULL لصفوف المشغّل** فتُستثنى من المفتاح كلِّه — ذلك أن الجدولَ ذو
--    وضعين (حكمُ ساعةٍ · سياسةُ مشغّل) ولصفوف المشغّل مفتاحُها القائم
--    `uq_operator_policy (company_id, operator_id, work_model, pay_basis,
--    effective_from)`. وهنا يعمل تمايزُ NULL **لصالحنا** لا علينا.
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_hour_policies'
                  AND COLUMN_NAME = 'policy_key'),
    'ALTER TABLE `contract_hour_policies`
       ADD COLUMN `policy_key` VARCHAR(80)
           GENERATED ALWAYS AS (
               IF(`operator_id` IS NULL,
                  CONCAT_WS(''|'',
                      IFNULL(CAST(`contract_ref`    AS CHAR), ''*''),
                      IFNULL(CAST(`ops_state`       AS CHAR), ''*''),
                      IFNULL(CAST(`obligation_type` AS CHAR), ''*''),
                      IFNULL(CAST(`effective_from`  AS CHAR), ''*'')),
                  NULL)
           ) STORED
           COMMENT ''بصمةُ قاعدة حكم الساعة بقيمٍ حارسةٍ بديلةٍ عن NULL — وNULL لصفوف المشغّل فتُستثنى (مفتاحُها uq_operator_policy)''',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

--    ── تبديلُ المفتاح: يُضاف الجديدُ **قبل** إسقاط القديم ──────────────────
--    الترتيبُ مقصود: لا rollback لـDDL في MySQL، فلو فشل الجديدُ لتصادمٍ بقي
--    القديمُ قائمًا ولم نُسلَّم جدولًا بلا حارسٍ أصلًا. (والمقيسُ أن صفوف حكم
--    الساعة الأربعةَ والعشرين — 12 عميلًا و12 موردًا — صفرُ تصادمٍ بينها.)
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_hour_policies'
                  AND INDEX_NAME = 'uq_policy_scope_key'),
    'ALTER TABLE `contract_hour_policies`
       ADD UNIQUE KEY `uq_policy_scope_key` (`company_id`, `party_scope`, `policy_key`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'contract_hour_policies'
              AND INDEX_NAME = 'uq_policy_rule'),
    'ALTER TABLE `contract_hour_policies` DROP INDEX `uq_policy_rule`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

--    ودليلُ البحث يصحبه المحورَ الجديد (قراءةُ hourPolicy في المرحلة ③)
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_hour_policies'
                  AND INDEX_NAME = 'ix_lookup_obligation'),
    'ALTER TABLE `contract_hour_policies`
       ADD KEY `ix_lookup_obligation` (`company_id`, `party_scope`, `contract_ref`, `obligation_type`, `ops_state`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;


-- ═══ ١-ج · قواعدُ الجزاء والحافز (ق-9 · ق-10 · ق-12) ════════════════════════
--    **نوعا قاعدةٍ لا أكثر** للجزاء — ولا `formula_json` ولا مفسِّرَ تعابير:
--      · `shortfall_pct`  → غرامةُ العجز       (rate نسبةٌ من قيمة الفارق)
--      · `readiness_min`  → غرامةُ الجاهزية    (min_readiness_pct + rate)
--    ويقابلهما للحافز (ق-10):
--      · `bonus_qty_pct`  → تجاوزُ الكمية بالنسبة
--      · `bonus_fixed`    → الجودةُ والسلامةُ مبلغًا مقطوعًا بمعيارٍ يدويٍّ معتمد
--    والجاهزيةُ محسوبةٌ سلفًا من سطور الزمن: ساعاتُ العمل ÷ ساعاتِ الوردية.
--    والسقفُ (ق-12) نسبةٌ من **قيمة البند الملتزَم في الفترة** — فالسقفُ
--    والأساسُ من جنسٍ واحد؛ ولذلك `commitment_ref` هو مرساةُ الاحتساب.
--    ⚠️ الجدولُ يُسلَّم فارغًا: قواعدُ الجزاء نصُّ عقدٍ لا قرارُ كود.
CREATE TABLE IF NOT EXISTS `contract_penalty_rules` (
  `id`                 INT NOT NULL AUTO_INCREMENT,
  `company_id`         INT NOT NULL COMMENT 'عزل المستأجر',
  `client_contract_id` INT NOT NULL COMMENT 'عقدُ العميل — contracts.id (FK حقيقيّ)',

  `rule_kind`          ENUM('shortfall_pct','readiness_min','bonus_qty_pct','bonus_fixed') NOT NULL
                       COMMENT 'نوعا جزاءٍ ونوعا حافزٍ — قائمةٌ مغلقةٌ عمدًا (ق-9): لا توسيعَ فوق الأربعة',

  `commitment_ref`     INT UNSIGNED NULL
                       COMMENT 'البندُ الملتزَمُ المرساة (contract_commitments.id) — NULL أي قاعدةٌ على مستوى العقد كلِّه',

  `rate`               DECIMAL(6,3) NULL
                       COMMENT 'نسبةُ الغرامة/الحافز: من قيمة الفارق (shortfall_pct) أو من قيمة الفترة (readiness_min)',
  `min_readiness_pct`  DECIMAL(5,2) NULL
                       COMMENT 'عتبةُ الجاهزية — لـreadiness_min وحدَها. الجاهزيةُ = ساعاتُ العمل ÷ ساعاتِ الوردية',
  `fixed_amount`       DECIMAL(16,2) NULL
                       COMMENT 'المبلغُ المقطوع — لـbonus_fixed وحدَه (الجودةُ والسلامةُ بمعيارٍ يدويٍّ معتمد · ق-10)',
  `cap_percent`        DECIMAL(5,2) NULL
                       COMMENT 'السقفُ نسبةً من قيمة البند الملتزَم في الفترة (ق-12) — الأساسُ والسقفُ من جنسٍ واحد',

  `currency`           VARCHAR(8) NULL COMMENT 'عملةُ المبلغ المقطوع — NULL أي عملةُ العقد',
  `periodicity`        ENUM('daily','monthly','contract') NOT NULL DEFAULT 'monthly'
                       COMMENT 'دوريةُ الاحتساب — ويُؤجَّل حتى تكتمل الدورية ولا يُحتسب نسبيًّا (ق-11)',

  `valid_from`         DATE NOT NULL
                       COMMENT 'بدءُ السريان — NOT NULL عمدًا: يشمله المفتاحُ الفريد، وNULL تُمرّر التكراراتِ صامتةً',
  `valid_to`           DATE NULL COMMENT 'نهايةُ السريان — NULL أي مفتوح',
  `note`               VARCHAR(255) NULL COMMENT 'بندُ العقد أو مرجعُ القاعدة',

  -- بصمةُ التفرد بقيمةٍ حارسةٍ بديلةٍ عن NULL — درسُ `uq_policy_rule` مطبَّقًا
  -- وقايةً لا علاجًا: `commitment_ref` فارغٌ مشروعٌ (قاعدةُ عقدٍ لا قاعدةُ بند)،
  -- فلولا الحارسُ لمرّت قاعدتان متطابقتان على مستوى العقد صامتتين.
  `commitment_key`     VARCHAR(16) GENERATED ALWAYS AS (IFNULL(CAST(`commitment_ref` AS CHAR), '*')) STORED
                       COMMENT 'مرساةُ القاعدة للمفتاح الفريد — * أي على مستوى العقد',

  `is_deleted`         TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`         DATETIME NULL,
  `deleted_by`         INT NULL,
  `created_by`         INT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_penalty_rule` (`client_contract_id`, `rule_kind`, `commitment_key`, `valid_from`)
      COMMENT 'قاعدةٌ واحدةٌ لكل (عقد × نوع × مرساة × تاريخ سريان) — والتعديلُ صفٌّ جديدٌ بسريانه (لا رجعية)',
  KEY `ix_penalty_scope` (`company_id`, `is_deleted`),
  KEY `ix_penalty_contract` (`client_contract_id`, `valid_from`, `valid_to`),
  CONSTRAINT `fk_penalty_rule_contract`
      FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`)
      ON DELETE RESTRICT ON UPDATE CASCADE,
  -- ⚠️ RESTRICT/RESTRICT لا SET NULL/CASCADE — **قيدٌ بنيويٌّ من الخادم لا
  --    تفضيلُ تصميم**: MySQL تمنع SET NULL/CASCADE على عمودٍ أساسٍ لعمودٍ
  --    محسوبٍ STORED، و`commitment_ref` أساسُ `commitment_key` أعلاه. قيس على
  --    الخادم الحيّ (8.4.7) بأربع حالاتٍ معزولة:
  --      محسوبٌ STORED + SET NULL  → [1215] Cannot add foreign key constraint
  --      محسوبٌ STORED + RESTRICT  → نجح ✔  (المختار)
  --      بلا محسوبٍ   + SET NULL  → نجح ✔
  --      محسوبٌ VIRTUAL + SET NULL → نجح ✔
  --    وRESTRICT هي الدلالةُ الأصحُّ هنا على كلٍّ: البوابةُ لا تحذف إلا حذفًا
  --    ناعمًا (`TenantDb::softDelete`)، فحذفُ بندِ التزامٍ حذفًا صلبًا من تحت
  --    قاعدةِ جزاءٍ تستند إليه شذوذٌ **يُمنع** لا يُصحَّح بتفريغ المرساة. وهي
  --    كذلك نفسُ سياسة `fk_penalty_rule_contract` أعلاه — فاتّسق القيدان.
  CONSTRAINT `fk_penalty_rule_commitment`
      FOREIGN KEY (`commitment_ref`) REFERENCES `contract_commitments` (`id`)
      ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CON-02 §6/§8 — قواعدُ الجزاء والحافز: نوعان لكلٍّ، بسقفٍ ومرساةٍ وسريان';

--    ── إحكامُ العهد المتروك: `penalty_rule_id` صار له هدف ──────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_obligations'
                  AND CONSTRAINT_NAME = 'fk_obligation_penalty_rule'),
    'ALTER TABLE `contract_obligations`
       ADD CONSTRAINT `fk_obligation_penalty_rule`
           FOREIGN KEY (`penalty_rule_id`) REFERENCES `contract_penalty_rules` (`id`)
           ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;


-- ═══ ١-د · الاستقطاعاتُ حقلين في العقد (ق-19) ═══════════════════════════════
--    يولّدان **بندين ظاهرين سالبين** في كل مستخلصٍ مع رصيدٍ تراكميٍّ ظاهر
--    (المرحلة ⑤) — لا خصمٌ صامت (§6). وردُّ الضمان **قرارٌ يدويٌّ من الدور 19
--    بعد انتهاء العقد** (ق-20) فلا عمودَ أتمتةٍ له هنا.
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contracts'
                  AND COLUMN_NAME = 'retention_pct'),
    'ALTER TABLE `contracts`
       ADD COLUMN `retention_pct` DECIMAL(5,2) NULL
           COMMENT ''نسبةُ ضمان حسن التنفيذ المحتجزةُ من كل مستخلص — NULL أي لا احتجاز'' AFTER `guarantees`,
       ADD COLUMN `advance_recovery_pct` DECIMAL(5,2) NULL
           COMMENT ''نسبةُ استهلاك الدفعة المقدمة من كل مستخلص — NULL أي لا استهلاك'' AFTER `retention_pct`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;
