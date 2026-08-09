-- update0013 · أساسٌ مشتركٌ للبنود ① ② ③ ④ — ترحيلُ الأدوارِ المالية
-- ═══════════════════════════════════════════════════════════════════════════
-- المصدر: FIN-MGR-01 §٤-٣ (FMGR-0018..FMGR-0022) · FIN-CTRL-01 §٤-٢
--         · FIN-TRE-01 §٤-٢ · IAF-01 §٤-١
--
-- الحكمُ الحاكم — FMGR-0022:
--   «◆ إعادةُ تصنيفٍ وبناءٌ فوقَ الموجودِ لا اختراعُ نظامٍ ماليٍّ موازٍ ·
--    ◆ ولا يُحذف دورٌ قديمٌ قبل ترحيلِ حاملِه.»
-- فلا صفٌّ يُحذف من `roles` هنا ولا يُعطَّل — الأدوارُ القديمةُ تبقى حاملةً
-- لأصحابِها حتى يُرحَّلوا، والجديدةُ تُضاف بجانبِها.
--
-- ما يُضاف ولماذا:
--   31 رئيسُ الحسابات        FCTRL-0001 «مالكُ سلامةِ السجلاتِ والدفاترِ والإقفالِ
--                            والسياساتِ المحاسبية — وليس محاسبًا أقدم».
--                            FCTRL-0002 «موازٍ لمسؤولِ الخزينةِ لا رئيسٌ عليه في
--                            حيازةِ الأموالِ وتنفيذها» — فأبوه 17 لا 21.
--   32 المديرُ المالي         FMGR-0002 «في الطبقةِ الثانيةِ يتبع الرئيسَ التنفيذيَّ
--                            مباشرةً» — فلا أبَ له في المالية، وهو غيرُ الدورِ 19
--                            «مدير الإدارة المالية» الذي يبقى كما هو (FMGR-0019).
--   33 المراجعُ الداخليُّ المستقل  IAF-0001 «وظيفةُ ضمانٍ مستقلةٌ وليست وحدةً داخلَ
--                            الإدارةِ المالية» · IAF-0004 «لا تتبع الماليةَ ولا رئيسَ
--                            الحساباتِ ولا الحوكمة» — فـparent_role_id = NULL.
--   34 منفِّذُ المدفوعاتِ البنكية  FTRE-0004 · FMGR-0021 «أمينُ الخزينةِ يُفصل عن
--                            منفِّذِ المدفوعاتِ ومُعِدِّ المطابقة · بثلاثةِ أدوار».
--   35 مُعِدُّ المطابقةِ البنكية   FTRE-0061 «المطابقةُ يُعدُّها شخصٌ مستقلٌّ عن
--                            منفِّذِ الدفع» — الثالثُ من الثلاثة.
--
-- ◆ التخصصاتُ العشرةُ ليست عشرةَ أدوار: FACC-0001 يجعلها **محورَ تخصصٍ** فوقَ
--   دورِ المحاسبِ (`fin_accountants.spec_code`) لأن «يجوز أن يجمع شخصٌ أكثرَ من
--   تخصصٍ بشرطِ عدمِ تعارضِ الواجبات» — وهذا لا يُمثَّل بدورٍ واحدٍ لكلِّ تخصص.
--
-- idempotent: INSERT ... SELECT WHERE NOT EXISTS على الاسم.

INSERT INTO `roles` (`id`, `name`, `parent_role_id`, `level`, `role_scope`, `status`)
SELECT * FROM (SELECT 31 AS a, 'رئيس الحسابات' AS b, 17 AS c, 2 AS d, 'gloable' AS e, '1' AS f) t
 WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'رئيس الحسابات');

INSERT INTO `roles` (`id`, `name`, `parent_role_id`, `level`, `role_scope`, `status`)
SELECT * FROM (SELECT 32 AS a, 'المدير المالي' AS b, NULL AS c, 1 AS d, 'gloable' AS e, '1' AS f) t
 WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'المدير المالي');

INSERT INTO `roles` (`id`, `name`, `parent_role_id`, `level`, `role_scope`, `status`)
SELECT * FROM (SELECT 33 AS a, 'المراجع الداخلي المستقل' AS b, NULL AS c, 1 AS d, 'gloable' AS e, '1' AS f) t
 WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'المراجع الداخلي المستقل');

INSERT INTO `roles` (`id`, `name`, `parent_role_id`, `level`, `role_scope`, `status`)
SELECT * FROM (SELECT 34 AS a, 'منفذ المدفوعات البنكية' AS b, 17 AS c, 2 AS d, 'gloable' AS e, '1' AS f) t
 WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'منفذ المدفوعات البنكية');

INSERT INTO `roles` (`id`, `name`, `parent_role_id`, `level`, `role_scope`, `status`)
SELECT * FROM (SELECT 35 AS a, 'معد المطابقة البنكية' AS b, 17 AS c, 2 AS d, 'gloable' AS e, '1' AS f) t
 WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'معد المطابقة البنكية');

-- ═══ سجلُّ ترحيلِ الأدوارِ القديمة (FMGR-0018..0022) ══════════════════════
-- «صفرُ حاملٍ لدورٍ قديمٍ بلا تصنيفٍ جديد» — والشاهدُ صفٌّ لكلِّ حامل.
CREATE TABLE IF NOT EXISTS `fin_role_migration` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `old_role_id`   INT UNSIGNED NOT NULL,
  `old_role_name` VARCHAR(120) NOT NULL,
  `new_role_id`   INT UNSIGNED NULL COMMENT 'فارغٌ حين يكون الترحيلُ إلى محورِ تخصصٍ لا إلى دور',
  `new_spec_code` VARCHAR(8)   NOT NULL DEFAULT '' COMMENT 'ACC-01..ACC-10 حين يكون الترحيلُ تخصصًا',
  `rule_text`     VARCHAR(500) NOT NULL DEFAULT '',
  `doc_ref`       VARCHAR(24)  NOT NULL DEFAULT '',
  `holders_before` INT UNSIGNED NOT NULL DEFAULT 0,
  `holders_moved` INT UNSIGNED NOT NULL DEFAULT 0,
  `state`         ENUM('planned','in_progress','done') NOT NULL DEFAULT 'planned',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mig` (`company_id`, `old_role_id`, `new_role_id`, `new_spec_code`),
  KEY `ix_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-MGR-01 §4-3 — ترحيلُ الأدوارِ القديمةِ بلا حذفِ حامل';
