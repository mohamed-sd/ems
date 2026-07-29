-- ═══════════════════════════════════════════════════════════════════════════
-- M-11 · مصدريةُ التحميلات — 2026-07-29 (اسمُ الملف 08_01 لتسلسلٍ أبجديٍّ بعد 07_31)
-- ───────────────────────────────────────────────────────────────────────────
-- **الفجوة**: `fin_dues` بلا عمودَي مصدر. فالقطعُ والصيانةُ تصلان بمرجعٍ حقيقيٍّ
-- عبر أعمدةٍ صريحةٍ في جدوليهما (`charge_supplier_id`)، أمّا ما يُدخَل يدويًّا من
-- `Finance/dues_fin.php` فيصل **بلا أيِّ مرجع** — رقمٌ يُخصم من طرفٍ بلا مستند.
--
-- **معيارُ القبول**: صفُّ خصمٍ (`direction='debit'`) جديدٌ بلا مصدرٍ **يُرفض**.
-- والرفضُ هنا **قيدٌ بنيويٌّ لا فحصٌ تطبيقي** (`CHECK`) — فلا يلتفّ عليه مسارٌ
-- جديدٌ ينساه كاتبُه. والاستحقاقُ (`credit`) خارجَ الإلزام: مصدرُه `event_id`
-- من المروحة، وإلزامُه بمستندٍ يمنع محرِّكًا يعمل بحق.
--
-- ── القيمُ ومعانيها ───────────────────────────────────────────────────────
--   `proc_issue`         سندُ الصرف — القطعُ **والوقود** (قرارُ المالك: «لا جدولَ
--                        وقودٍ لكن نعم جدولُ صرف» — فالوقودُ يُحمَّل كما تُحمَّل القطع)
--   `mnt_order`          أمرُ الصيانة
--   `transfer_order`     أمرُ النقل وسطورُ تكلفته
--   `penalty_assessment` احتسابُ الجزاء المُجاز (له حدثُه في الدفتر)
--   `settlement`         تسويةٌ صافيها سالبٌ فتحت ذمّةً مدينة
--   `legacy_no_ref`      **موروثٌ بلا مرجع** — يُعلَن ولا يُخفى ولا يُمسح
--
-- ── ولماذا `legacy_no_ref` قيمةٌ لا NULL ─────────────────────────────────
-- لأن NULL يعني «لم يُسأل بعد»، و`legacy_no_ref` يعني **«سُئل وأُجيب: لا مرجعَ
-- له، وهذا قرارٌ موثَّق»**. والفرقُ بينهما هو الفرقُ بين فجوةٍ منسيّةٍ وفجوةٍ
-- معلَنة (نظيرُه القائم `pre_settlement_legacy`). وبه يصحّ القيدُ البنيويُّ على
-- الصفوف التاريخية بلا أن تُمسّ قيمةٌ واحدةٌ منها.
--
-- **العبءُ التاريخيُّ المقيس**: 3 صفوفِ خصمٍ فقط — 2 جزاءٌ لهما حدثُهما
-- (يُنسبان لاحتسابهما 35/36) و**صفٌّ واحدٌ وقودٌ بـ120,000 بلا مرجع** يُعلَن موروثًا.
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE `fin_dues`
  ADD COLUMN `source_doc_type` ENUM('proc_issue','mnt_order','transfer_order',
                                    'penalty_assessment','settlement','legacy_no_ref')
      DEFAULT NULL
      COMMENT 'M-11: نوعُ المستند المصدر — إلزامٌ على الخصم (CHECK)، واختياريٌّ على الاستحقاق (مصدرُه event_id)'
      AFTER `event_id`,
  ADD COLUMN `source_doc_id` INT UNSIGNED DEFAULT NULL
      COMMENT 'معرّفُ المستند في جدوله — NULL مع legacy_no_ref وحدَها'
      AFTER `source_doc_type`;

-- ── الردمُ: كلُّ صفٍّ إلى مصدره الحقيقي، والباقي يُعلَن موروثًا ───────────────
-- ① الجزاءات ← احتسابُها، بمطابقة الحدث (لا تخمينَ بالمبلغ والتاريخ)
UPDATE `fin_dues` d
  JOIN `contract_penalty_assessments` a ON a.`event_id` = d.`event_id`
   SET d.`source_doc_type` = 'penalty_assessment', d.`source_doc_id` = a.`id`
 WHERE d.`direction` = 'debit' AND d.`due_type` = 'penalty' AND d.`event_id` IS NOT NULL;

-- ② التسوياتُ ← تسويتها
UPDATE `fin_dues`
   SET `source_doc_type` = 'settlement', `source_doc_id` = `settlement_id`
 WHERE `direction` = 'debit' AND `due_type` = 'settlement' AND `settlement_id` IS NOT NULL;

-- ③ وما بقي من الخصم بلا مرجع ⇒ **موروثٌ معلَن** (لا يُمسح ولا يُخفى)
UPDATE `fin_dues`
   SET `source_doc_type` = 'legacy_no_ref'
 WHERE `direction` = 'debit' AND `source_doc_type` IS NULL;

-- ── القيدُ البنيوي: لا خصمَ بلا مصدرٍ بعد اليوم ─────────────────────────────
-- ويسمح بـ`legacy_no_ref` لأن الصفوفَ التاريخيةَ معلَنةٌ لا مجهولة.
ALTER TABLE `fin_dues`
  ADD CONSTRAINT `ck_dues_debit_source`
  CHECK (`direction` <> 'debit' OR `source_doc_type` IS NOT NULL);

-- فهرسُ الرجوع من المستند إلى تحميلاته («أين ذهب هذا السند؟»)
ALTER TABLE `fin_dues`
  ADD INDEX `ix_dues_source_doc` (`company_id`, `source_doc_type`, `source_doc_id`);
