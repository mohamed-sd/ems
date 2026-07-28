-- ═══════════════════════════════════════════════════════════════════════════
-- أساسُ مصفوفة الالتزامات — CON-02 §4/§8 · المستوى السهل — 2026-07-28
-- ───────────────────────────────────────────────────────────────────────────
-- **دفعةُ بنيةٍ لا دفعةُ سلوك.** الجدولُ يُسلَّم فارغًا والأعمدةُ لا يقرؤها منطقٌ
-- بعد — فالنظامُ بعد هذا الملف متطابقٌ سلوكيًّا مع ما قبله: نفسُ الفوترة ونفسُ
-- الأحكام ونفسُ الأرقام. تعبئةُ المصفوفة قرارُ عملٍ لا قرارُ كود، والمنطقُ الذي
-- يقرؤها (AttributionService · حكمُ الساعة بمفتاح obligation_type) دفعةٌ لاحقة.
--
-- **الفجوةُ الأمّ التي يفتح هذا الملفُّ بابَها** (تقريرُ الفروقات §0): النظامُ
-- يشتقّ المسؤولَ عن زمن التوقف من **حالة الساعة** لا من **بند التزامٍ في عقد
-- العميل**. فنفادُ وقودٍ يلتزم به العميلُ تعاقديًّا يُسجَّل اليوم
-- `fuel_logistics_stop` ← حكمُه `none` ← فلا يُفوتر؛ والوثيقةُ تقول إنه استعدادٌ
-- مفوتر. ولا سبيلَ لتصحيح ذلك ما دام **مكانُ النصّ العقدي غيرَ موجود** — وهذا
-- الجدولُ هو ذلك المكان.
--
-- **قرارات المالك (2026-07-28):**
--   ① `obligation_type` تبقى **ENUM** كما تنصّ الوثيقة حرفيًّا («لا نصٌّ حر»)
--      لا جدولَ مرجعية — موافقًا لنمط العائلة المقيس: `commitment_type` و
--      `shortfall_rule` و`surplus_rule` في `contract_commitments` كلُّها ENUM.
--      والثمنُ مقبول: البنودُ التسعةُ قائمةٌ مغلقةٌ من وثيقة العقد لا قائمةٌ تنمو.
--   ② **قيمةٌ خامسة `none` في `obligor`** — لأن §4 تعدّ «الظروفَ القاهرة» بندًا
--      بينما §5-⑤ تقول «لا يُفوتر ولا يُجزى أحد». فـ`company` الافتراضيةُ كانت
--      ستقرأ «الشركةُ ملتزمةٌ بمنع الفيضان» وتُحمِّلها التبعةَ في أول محلِّلٍ
--      يُبنى فوقها. و`none` تقول «لا طرفَ ملتزمًا» صراحةً.
--   ③ **مفتاحٌ أجنبيٌّ فعليٌّ** إلى `contracts.id` — بندُ التزامٍ بلا عقدٍ لا
--      معنى له، فتمنعه القاعدةُ بنيويًّا. والمقيسُ أن العائلةَ منقسمة:
--      `contractequipments` و`contract_notes` بـFK حقيقي، بينما
--      `contract_hour_policies.contract_ref` هو `int unsigned` و`contracts.id`
--      هو `int` **موقّع** — فالـFK كان هناك **مستحيلًا بنيويًّا** لا مرفوضًا
--      مبدئيًّا. ولذلك يُطابَق النوعُ هنا (`INT` موقّع) فيصحّ القيد.
--   ④ نوعُ الملحق الجديد **«تغيير التزامات»** — على نمط القيم القائمة
--      («تغيير أسعار» · «إضافة معدات») لا «تغيير المصفوفة»: مَن يوقّع الملحقَ
--      يقرأ لغةَ العقد لا مصطلحَنا الداخلي.
--   ⑤ `effective_from` **يُترك NULL في الصفوف السبعة القائمة** — تاريخُ التوقيع
--      (`amend_date`) ليس تاريخَ السريان بالضرورة، وملؤه منه اختراعُ نيّةٍ
--      ماضيةٍ لا نملكها. وNULL تقرأ «غيرُ معروف» فتُملأ حين يُقرَّر.
--   ⑥ **بندُ التحكيم مُسقَطٌ عن هذه الدفعة** بقرارٍ واعٍ: بندٌ ورقيٌّ لا أثرَ له
--      على فوترةٍ ولا إسناد، وترميزُه عمودٌ يبقى فارغًا.
--   ⑦ **المفتاحُ الأساسيُّ `id` لا `obligation_id`** خلافًا لحرفية §8 — لأن
--      `TenantDb::softDelete()` يبني شرطَه على `id` ثابتًا (TenantDb.php:220)،
--      فتسجيلُ الجدول soft=true بمفتاحٍ مغايرٍ يجعل **مسارَ حذفه الوحيد يرمي**
--      «Unknown column 'id'». وقد قيس فعلًا فرمى قبل التصحيح. والبوابةُ عقدٌ
--      حاكمٌ على 141 جدولًا بينما اسمُ المفتاح تفضيلُ تسمية — فتغلب البوابة.
--      وكلُّ العائلة على `id`: contract_commitments · contract_amendments ·
--      contractequipments · settlements.
--
-- **الجدولُ يُسلَّم فارغًا عمدًا** — لا صفَّ بذرٍ لأي عقدٍ حقيقي (تسعةُ عقودٍ في
-- شركة 4). أولُ صفٍّ يكتبه مسؤولُ العقود من شاشة المصفوفة (T-11)، لا الهجرة.
--
-- إضافيٌّ محض: جدولٌ جديدٌ وعمودٌ Nullable وتوسيعُ ENUM — ولا عمودَ قائمٌ يُمسّ
-- ولا صفَّ بياناتٍ واحدٌ يتغيّر.
-- الرجوع: DROP TABLE contract_obligations؛ ثم إسقاطُ `effective_from` وردُّ
-- `amend_type` إلى قيمها الاثنتي عشرة (لا صفَّ يستعمل الثالثةَ عشرة بعد).
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── ① مصفوفةُ الالتزامات: بندٌ × ملتزمٌ × أثرٌ على الفوترة (CON-02 §8) ──────
CREATE TABLE IF NOT EXISTS `contract_obligations` (
  -- ⚠️ المفتاحُ `id` لا `obligation_id` كما في نصّ §8 — قرارُ المالك ⑦ أدناه:
  --    `TenantDb::softDelete()` يبني شرطَه على `id` ثابتًا (TenantDb.php:220)،
  --    فجدولٌ مسجَّلٌ soft=true بمفتاحٍ آخرَ يرمي «Unknown column 'id'» في مسار
  --    حذفه الوحيد. القياسُ أُجري فعلًا فرمى، والعائلةُ كلُّها على `id`.
  `id`                 INT NOT NULL AUTO_INCREMENT,
  `company_id`         INT NOT NULL COMMENT 'عزل المستأجر',
  `client_contract_id` INT NOT NULL COMMENT 'عقدُ العميل — contracts.id (FK حقيقيّ · قرارُ المالك ③)',

  `obligation_type`    ENUM('fuel','access_road','loading_equipment','equipment_readiness',
                            'operators','permits_safety','utilities','catering_camp','force_majeure')
                       NOT NULL
                       COMMENT 'بنودُ §4 التسعة: الوقود · الطريق · معدات التحميل · جاهزية المعدة · المشغّلون · التصاريح · المرافق · الإعاشة · القاهرة',

  `obligor`            ENUM('client','company','supplier','operator','none')
                       NOT NULL DEFAULT 'company'
                       COMMENT 'الطرفُ الملتزم. الافتراضُ company تنفيذًا لقاعدة §4 «ما لم يُنص عليه يُعدُّ التزامَ الشركة» · و none للقاهرة (لا طرفَ ملتزمًا — قرارُ المالك ②)',

  `effect_on_billing`  ENUM('billable_standby','non_billable','per_clause')
                       NOT NULL DEFAULT 'per_clause'
                       COMMENT 'أثرُ الإخلال على الفوترة. الافتراضُ per_clause أي «اقرأ البند» — لا حكمَ مشتقًّا صامتًا',

  `penalty_rule_id`    INT NULL
                       COMMENT 'قاعدةُ الجزاء — بلا هدفٍ حتى تُبنى contract_penalty_rules (§6 · T-07)؛ فلا FK اليوم',

  `valid_from`         DATE NOT NULL
                       COMMENT 'بدءُ السريان — NOT NULL عمدًا: المفتاحُ الفريد أدناه يشمله، وMySQL تعدّ NULLات متمايزةً فتمرّ التكراراتُ صامتةً',
  `valid_to`           DATE NULL COMMENT 'نهايةُ السريان — NULL أي مفتوح',

  `is_deleted`         TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`         DATETIME NULL,
  `deleted_by`         INT NULL,
  `created_by`         INT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obligation_contract_type_from` (`client_contract_id`, `obligation_type`, `valid_from`)
      COMMENT 'بندٌ واحدٌ لكل (عقد × نوع × تاريخ سريان) — وتغييرُ الملتزم صفٌّ جديدٌ بسريانه لا تعديلُ الماضي (§6 الملاحق: لا رجعية)',
  KEY `ix_obligation_scope` (`company_id`, `is_deleted`),
  KEY `ix_obligation_contract` (`client_contract_id`),
  KEY `ix_obligation_validity` (`valid_from`, `valid_to`),
  CONSTRAINT `fk_contract_obligations_contract`
      FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CON-02 §4/§8 — مصفوفةُ التزامات عقد العميل: منها يُشتق المسؤولُ لا من حالة الساعة';

-- ── ② سريانُ الملحق: تاريخُ التوقيع ≠ تاريخُ النفاذ (§6 · T-17) ─────────────
--    يُترك NULL في الصفوف السبعة القائمة (قرارُ المالك ⑤) — ولا UPDATE هنا.
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_amendments'
                  AND COLUMN_NAME = 'effective_from'),
    'ALTER TABLE `contract_amendments` ADD COLUMN `effective_from` DATE NULL COMMENT ''تاريخُ نفاذ الملحق — NULL أي غيرُ محدد (لا يُشتق من amend_date)'' AFTER `amend_date`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ③ نوعُ ملحقٍ لتغيير المصفوفة (§6) — MODIFY idempotent بطبيعته ───────────
--    ⚠️ ENUM عربيٌّ: يُطبَّق بعميل utf8mb4 حصرًا (المُشغِّل يفرضه على اتصاله)،
--    وأيُّ عميلٍ بغيره = ترميزٌ مضاعفٌ صامتٌ لا يُطابقه التطبيقُ أبدًا.
ALTER TABLE `contract_amendments`
  MODIFY COLUMN `amend_type`
    ENUM('تجديد','تمديد','زيادة نطاق','تخفيض نطاق','تغيير أسعار','إضافة معدات',
         'إضافة خدمات','إيقاف','استئناف','إنهاء','انتهاء','دمج','تغيير التزامات')
    NOT NULL DEFAULT 'تجديد'
    COMMENT 'نوعُ الملحق — و«تغيير التزامات» تُوثّق تعديلَ مصفوفة §4 بسريانٍ لا رجعيّ';
