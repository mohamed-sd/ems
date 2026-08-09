-- update0013 · حقولُ العقدِ الحاكمةُ الثمانيةُ والعشرون — من سجلٍّ إلى قيد
-- ═══════════════════════════════════════════════════════════════════════════
-- FIN-OBL-01 §٤-٦ (OBL-0058..0085) تُعلن ثمانيةً وعشرين حقلًا «حاكمًا» للعقد،
-- ولكلٍّ حكمُه: «ولا يُقبل عقدٌ بلا طرفين مسمَّيين» · «ولا استحقاقَ بلا عملة» ·
-- «ولا استحقاقَ بلا سعرِ وحدة» · «إلزاميٌّ لكل قيد» …
--
-- ◆ وكانت مسجَّلةً في `gov_doc_registry` بتغطيةِ `seed` — أي **مكتوبةً لا
--   مُنفَذة**. بل إن نصَّ حسمِ المخالفةِ ادّعى أنها «وُسمت في gov_field_class»
--   ولم يكن فيها منها صفٌّ واحد. فهذا الجدولُ يجعل الحكمَ قابلًا للقياس:
--
--   `obligation`  — أإلزاميٌّ دائمًا أم عند شرطٍ أم اختياري؟ (من نصِّ الوثيقة)
--   `home_table`/`home_column` — **أينَ يعيش الحقلُ في النظامِ الحي؟**
--   والفارغُ منهما ليس سهوًا: هو **فجوةٌ معلَنةٌ** تُعرض ولا تُخفى، فالحقلُ
--   الحاكمُ بلا موضعٍ في القاعدةِ لا يُفحص ولا يُلزَم به أحد.
--
-- ◆ ولماذا لا تُنشأ ثمانيةٌ وعشرون عمودًا في `contracts`: لأن مسَّ جدولِ
--   العقودِ الحيِّ بعشرين عمودًا قرارُ مالكٍ لا أداة، ولأن بعضَها يعيش في
--   جداولَ أخرى أصلًا (الملاحقُ والجزاءاتُ وأسعارُ الصرف). فالمصفوفةُ تصف
--   الواقعَ وتكشف نقصَه، والحارسُ يُنفِذ ما له موضع.
--
-- المصدر: FIN-OBL-01 §٤-٦ · OBL-0058..0085
-- idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS `fin_contract_fields` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `field_code`    VARCHAR(16)  NOT NULL COMMENT 'CFIELD-01 .. CFIELD-28',
  `seq`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `title`         VARCHAR(300) NOT NULL COMMENT 'اسمُ الحقلِ كما تسميه الوثيقة',
  `obligation`    ENUM('always','conditional','optional') NOT NULL DEFAULT 'optional'
                  COMMENT 'always = لا يُقبل عقدٌ بدونه · conditional = عند الانطباق',
  `condition_ar`  VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'شرطُ الإلزامِ حين يكون مشروطًا',
  `rule_ar`       VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'حكمُ الوثيقةِ على الحقلِ نصًّا',
  `home_table`    VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '◆ الفارغُ = فجوةٌ معلَنةٌ لا سهو',
  `home_column`   VARCHAR(64)  NOT NULL DEFAULT '',
  `resolve_state` ENUM('live','gap','pending') NOT NULL DEFAULT 'pending'
                  COMMENT 'يُحسم آليًّا بفحصِ information_schema — لا بالإعلان',
  `owner_action`  VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'ما يلزم المالكَ فعلُه لسدِّ الفجوة',
  `doc_ref`       VARCHAR(24)  NOT NULL DEFAULT '',
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cf` (`company_id`, `field_code`),
  KEY `ix_ob` (`obligation`, `resolve_state`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-6 — حقولُ العقدِ الحاكمةُ الـ28 بموضعِ كلٍّ وإلزامِه';
