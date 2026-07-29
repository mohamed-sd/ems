-- ═══════════════════════════════════════════════════════════════════════════
-- ذمّةُ العميل: بابٌ واحدٌ للإيراد وحارسٌ للذمّة — 2026-07-28
-- ───────────────────────────────────────────────────────────────────────────
-- **الفجوتان المقيستان** (برهانُ `tests/unit_chain_e2e_proof.php`):
--   ① **ازدواجُ قيد الإيراد**: 56,000 عن عملٍ بـ28,000 — المروحةُ تنشر
--      `revenue.unit.recognized` والمستخلصُ ينشر `revenue.claim.approved`،
--      وكلاهما يُسقِط `event_type='revenue'` عن **الساعات نفسها** بمفتاحَي
--      عطالةٍ لا يريان بعضهما (`fanout:ts:{id}:revenue` · `claim:{id}`).
--   ② **ذمّةٌ بلا حارس**: 17,500 وُلّدت من صفِّ دوامٍ **بصفر اعتماد** —
--      `claim_billable_units` كانت تشترط `ts.status=1` وحدها، بينما مستحقُّ
--      المورد والمشغّل محجوزٌ بـ`EMS_UNIT_CONVERT_GATE`.
--
-- **قرارا المالك 2026-07-28:**
--   ① **المروحةُ تعترف والمستخلصُ يفوتر** — قيدُ الإيراد واحدٌ مصدرُه المروحة
--      (فتستكمل `customer_entity_id`)، والمستخلصُ يربط القيودَ القائمةَ ببنوده
--      ويفتح الذمّةَ وحدها ولا ينشر إيرادًا ثانيًا. وبهذا **يرث المستخلصُ
--      حراسةَ بوابة التحويل تلقائيًّا**: لا بندَ إلا ليومٍ حوّلته المالية.
--   ② **المبيعاتُ تُنشئ والمالية تعتمد** — بنفس نمط تسويات الموردين حرفيًّا
--      (`can_add`=الإعدادُ والرفع · `can_edit`=الإجازة)، فلا يجتمعان لدور.
--
-- إضافيٌّ محضٌ (Backward Compatible): عمودان Nullable ولا عمودَ قائمٌ يُمسّ.
-- الرجوع: إسقاطُ العمودين + إعادةُ منحةِ الدور 12 كما كانت.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── أ · ربطُ البند بقيد الإيراد المعترَف به (لا إيرادَ جديد بل مرجعٌ لقائم) ──
ALTER TABLE `claim_lines`
  ADD COLUMN `event_id` INT UNSIGNED NULL
      COMMENT 'قيدُ الإيراد المعترَف به من المروحة — البندُ مرجعٌ له لا منشئٌ لإيرادٍ ثانٍ'
      AFTER `source_ref`,
  ADD INDEX `ix_claim_lines_event` (`event_id`);

-- ── ب · يدُ المبيعات ويدُ المالية على المستخلص (submitted_* لمن رفع) ────────
ALTER TABLE `claims`
  ADD COLUMN `submitted_by` INT UNSIGNED NULL
      COMMENT 'من رفعه للمالية (المبيعات) — ولا يعتمد المرءُ ما رفع' AFTER `state`,
  ADD COLUMN `submitted_at` DATETIME NULL
      COMMENT 'لحظةُ الرفع للمالية (draft → review)' AFTER `submitted_by`;

-- ── ج · فصلُ اليدين في طبقة المنح (الوحدة 142 · نمط الوحدة 144) ────────────
--   · المبيعاتُ (12)            → can_add = التوليدُ والرفعُ والإلغاء
--   · مديرُ الإدارة المالية (19) → can_edit = الإجازة (فاتورةٌ + ذمّة)
--   · المديرُ المالي (17) ومحاسبُها (18) والتشغيل (1) → عرضًا كما هم
-- ⚠️ الدور 12 كان يحمل can_edit=1 فكان يعتمد ما يُنشئ — تُسحَب منه الإجازةُ
--    وحدها ويبقى إنشاؤه كاملًا.
UPDATE `role_permissions` SET `can_edit` = 0
 WHERE `module_id` = 142 AND `role_id` = 12;

UPDATE `role_permissions` SET `can_view` = 1, `can_add` = 0, `can_edit` = 1
 WHERE `module_id` = 142 AND `role_id` = 19;

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 19, 142, 1, 0, 1, 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = 19 AND rp.`module_id` = 142);

-- ── د · ردمُ العميل في قيود الإيراد المشتقّة من الدوام ─────────────────────
-- السلسلةُ نفسُها التي تقرؤها المروحة الآن: الدوام → التشغيل → المشروع → العميل.
-- ما لا يُشتقّ (القيودُ الموروثةُ بلا كيان) **يُترك NULL معلَنًا** — لا يُخترع مَدين.
UPDATE `fin_financial_events` e
  JOIN `timesheet` t   ON t.`id` = e.`entity_id` AND e.`entity_type` = 'timesheet'
  JOIN `operations` o  ON o.`id` = t.`operator`
  JOIN `project` p     ON p.`id` = o.`project_id`
   SET e.`customer_entity_id` = p.`client_id`
 WHERE e.`event_type` = 'revenue'
   AND e.`customer_entity_id` IS NULL
   AND p.`client_id` IS NOT NULL;
