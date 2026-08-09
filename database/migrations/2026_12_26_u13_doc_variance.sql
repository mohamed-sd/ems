-- update0013 · سجلُّ مخالفاتِ الوثائقِ وحسمُها
-- ═══════════════════════════════════════════════════════════════════════════
-- ◆ لماذا جدولٌ لا ملاحظةٌ في تقرير:
--   الوثيقةُ ليست معصومة. وحين تُعلن ترويستُها عددًا ويسجّل سجلُّها الذريُّ
--   غيرَه، فأمام المُنفِّذِ ثلاثةُ طرق: أن يبنيَ على الترويسةِ فيخترع ما لا نصَّ
--   له · أو على السجلِّ فيُخالف رقمًا معلَنًا · أو **أن يُصرّح بالفرقِ ويحسمه
--   بأساسٍ مكتوب**. والثالثُ وحدَه قابلٌ للتدقيق.
--   وما يُكتب في تقريرٍ يُقرأ مرةً ويُنسى؛ وما يُكتب في جدولٍ **يُفحص في كلِّ
--   بوابةٍ** ويظهر متى تغيّرت الوثيقةُ فبطل الحسم.
--
-- الحكمُ الحاكم — FIN-OBL-01 OBL-0307: «والحدثُ ذو الأثرِ الماليِّ الذي لا
--   مُطلِقَ له **ثغرةٌ تُسجَّل عيبًا لا تُهمَل**» — والقياسُ هنا: التعارضُ في
--   الوثيقةِ نفسِها ثغرةٌ تُسجَّل لا تُهمَل.
--
-- idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS `gov_doc_variance` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = يخصُّ الوثيقةَ لا الكيان',
  `variance_code` VARCHAR(12)  NOT NULL COMMENT 'V-01 ..',
  `doc_code`      VARCHAR(24)  NOT NULL COMMENT 'الوثيقةُ صاحبةُ التعارض',
  `subject`       VARCHAR(200) NOT NULL COMMENT 'موضعُ التعارض',
  -- الطرفان
  `declared_where` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'أين أُعلن الرقمُ الأول',
  `declared_value` VARCHAR(120) NOT NULL DEFAULT '',
  `registered_where` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'أين سُجِّل الثاني',
  `registered_value` VARCHAR(120) NOT NULL DEFAULT '',
  -- الحسم
  `resolution`    ENUM('follow_register','follow_declared','derive','defer') NOT NULL
                  COMMENT 'follow_register = يُتبع السجلُّ الذريُّ لأنه القابلُ للاختبار',
  `resolved_value` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'الرقمُ الذي بُني عليه فعلًا',
  `basis`         VARCHAR(600) NOT NULL COMMENT 'أساسُ الحسمِ — ولا حسمَ بلا أساس',
  `impact`        VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'ما بُني نتيجةَ الحسم',
  `decided_by`    VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'من حسمَه وبأي صفة',
  `decided_at`    DATETIME     NOT NULL,
  -- المتابعة: الحسمُ مؤقتٌ حتى تُصحَّح الوثيقةُ أو يُقرَّه مالكُها
  `owner_action`  VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'ما يلزم مالكَ الوثيقةِ فعلُه',
  `state`         ENUM('open','resolved','accepted_by_owner','superseded') NOT NULL DEFAULT 'resolved',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_var` (`company_id`, `variance_code`),
  KEY `ix_doc` (`doc_code`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='update0013 — مخالفاتُ الوثائقِ وحسمُها بأساسٍ مكتوبٍ يُفحص كلَّ بوابة';
