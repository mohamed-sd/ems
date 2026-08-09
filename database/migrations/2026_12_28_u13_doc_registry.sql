-- update0013 · سجلُّ البنودِ المعلَنةِ في الوثائقِ وتغطيتُها الحية
-- ═══════════════════════════════════════════════════════════════════════════
-- ◆ لماذا هذا الجدول:
--   بوابةُ القبولِ تفحص **ما بُنيَ**. وهذا يفحص **ما أُعلن** — والفرقُ بينهما
--   هو التغطيةُ الحقيقية. فالوثائقُ السبعُ تعلن عوائلَ بنودٍ (واجباتٌ · حدودٌ ·
--   اختصاصاتٌ · مراحلُ دورةٍ · سيناريوهاتُ قبولٍ · أفعالٌ)، وكلُّ بندٍ فيها
--   دعوى «منفَّذٌ بشاهدِه». والدعوى بلا صفٍّ يقابلها لا تُقاس.
--
-- الحكمُ المُلهِم — FIN-OBL-01 OBL-0307: «والحدثُ ذو الأثرِ الماليِّ الذي لا
--   مُطلِقَ له **ثغرةٌ تُسجَّل عيبًا لا تُهمَل**». والقياسُ: البندُ المعلَنُ بلا
--   أثرٍ حيٍّ ثغرةٌ تُسجَّل لا تُهمَل.
--
-- ◆ `covered_by` هو العمودُ الحاكم: الأثرُ الحيُّ الذي ينفّذ البند (جدولٌ أو
--   خدمةٌ أو شاشةٌ أو حارسٌ أو صفُّ بذر). والفارغُ يعني **غيرَ مغطًّى** — ويظهر
--   في الفاحصِ العكسيِّ رقمًا لا يُخفى.
--
-- idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS `gov_doc_registry` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = يخصُّ الوثيقةَ لا الكيان',
  `doc_code`      VARCHAR(24)  NOT NULL COMMENT 'الوثيقةُ المعلِنة',
  `family`        VARCHAR(16)  NOT NULL COMMENT 'DUTY · LIMIT · COMP · SCEN · CYCLE …',
  `item_code`     VARCHAR(24)  NOT NULL COMMENT 'رمزُ البندِ داخلَ عائلته',
  `seq`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `title`         VARCHAR(300) NOT NULL,
  `detail`        VARCHAR(500) NOT NULL DEFAULT '',
  `accept_test`   VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'شاهدُ القبولِ كما تكتبه الوثيقة',
  `doc_ref`       VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'المتطلبُ الذريُّ المصدر',
  -- التغطية
  `covered_by`    VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'الأثرُ الحيُّ المنفِّذ — والفارغُ ثغرة',
  `coverage_kind` ENUM('table','service','screen','guard','seed','none') NOT NULL DEFAULT 'none',
  `coverage_note` VARCHAR(300) NOT NULL DEFAULT '',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item` (`company_id`, `doc_code`, `family`, `item_code`),
  KEY `ix_fam` (`doc_code`, `family`),
  KEY `ix_cov` (`coverage_kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='update0013 — البنودُ المعلَنةُ في الوثائقِ وتغطيتُها الحية';

-- الأحكامُ المنتشرةُ في كلِّ إدارة (PROP-01 §٦-١) — ١٦ صفًّا بمجموع ٥٢٣.
CREATE TABLE IF NOT EXISTS `gov_dept_propagation` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `dept_name`   VARCHAR(120) NOT NULL,
  `propagated`  SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'أحكامٌ منتشرةٌ عليها',
  `dept_total`  SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'إجماليُّ أحكامِها',
  `doors_note`  VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'الأبوابُ الثمانيةُ التي تمسُّها',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept` (`company_id`, `dept_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PROP-01 §6-1 — الأحكامُ المنتشرةُ في الإداراتِ الستَّ عشرة';
