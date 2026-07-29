-- ═══════════════════════════════════════════════════════════════════════════
-- تعبئةُ الحقول العشرين رجعيًّا + تصحيحُ حالات الأوامر المستلَمة — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- ① التعبئة الرجعية من مصادرها (لا قيمةَ مخترَعة):
--    · base_amount  = total_amount × fx_rate            (FES §3.3)
--    · project_id   من طلب الشراء المرجعي                (proc_request.project_id)
--    · received_pct وfirst/final_receipt_at من سجل الاستلام القائم
--    · event_id     من الحدث المنشور للأمر
--
-- ② تصحيحُ حالاتٍ متأخّرة (قرار المالك 2026-07-27): **المقيس** أن خمسة أوامر
--    (4·5·6·7·10) لها سجلاتُ استلامٍ فعليةٌ بسطورها في `proc_receipt_custody`
--    و`proc_receipt_line` — البضاعةُ وصلت وحالةُ الأمر وحدها لم تتقدّم. فتصحيحُ
--    الحالة **مطابقةٌ للواقع المسجَّل** لا تلفيقٌ له، وبه يصحّ مصروفُها المنشور.
--    والأمرُ #830 وحده بلا استلامٍ إطلاقًا — يُنشأ له استلامُه بأمر المالك.
--
-- الرجوع: الحالاتُ في العمود القديم كما كانت (مذكورةٌ نصًّا أدناه للتوثيق):
--    4=مؤكَّد · 5=مؤكَّد · 6=مسودة · 7=مؤكَّد · 10=مؤكَّد · 830=مسودة
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① المعادل الموحّد لكل أمر (العملةُ بثلاثيتها — FES §3.3) ───────────────
UPDATE `proc_order`
   SET `base_amount` = ROUND(COALESCE(`total_amount`,0) * COALESCE(`fx_rate`,1), 2)
 WHERE `base_amount` IS NULL;

-- ── ② المشروع من طلب الشراء المرجعي (البُعد المفقود) ──────────────────────
UPDATE `proc_order` po
  JOIN `proc_request` pr ON pr.`id` = po.`request_id`
   SET po.`project_id` = pr.`project_id`
 WHERE po.`project_id` IS NULL AND pr.`project_id` IS NOT NULL;

-- ── ③ الاستلامُ من سجله القائم (أولُ وآخرُ استلامٍ ونسبتُه) ────────────────
UPDATE `proc_order` po
   SET po.`first_receipt_at` = (
        SELECT MIN(rc.`receipt_date`) FROM `proc_receipt_custody` rc
         WHERE rc.`order_id` = po.`id` AND COALESCE(rc.`is_deleted`,0) = 0)
 WHERE po.`first_receipt_at` IS NULL;

UPDATE `proc_order` po
   SET po.`final_receipt_at` = (
        SELECT MAX(rc.`receipt_date`) FROM `proc_receipt_custody` rc
         WHERE rc.`order_id` = po.`id` AND COALESCE(rc.`is_deleted`,0) = 0),
       po.`received_pct` = 100.00
 WHERE po.`final_receipt_at` IS NULL
   AND EXISTS (SELECT 1 FROM `proc_receipt_custody` rc2
                WHERE rc2.`order_id` = po.`id` AND COALESCE(rc2.`is_deleted`,0) = 0);

-- ── ④ مرجعُ الحدث المنشور (قراءةً بمرجعه — §5.1-③) ────────────────────────
UPDATE `proc_order` po
  JOIN `fin_financial_events` fe
    ON fe.`entity_type` = 'proc_order' AND fe.`entity_id` = po.`id`
   SET po.`event_id` = fe.`id`
 WHERE po.`event_id` IS NULL;

-- ── ⑤ استلامُ الأمر #830 (الوحيد بلا استلام) — بأمر المالك ────────────────
INSERT INTO `proc_receipt_custody`
    (`company_id`, `code`, `holder_id`, `holder_name`, `receipt_date`, `supplier_id`,
     `order_id`, `receipt_location`, `expected_destination`, `state`, `notes`, `created_by`, `created_at`)
SELECT po.`company_id`, 'PRC-RC-0830', po.`created_by`, 'تسويةٌ رجعية',
       DATE(po.`created_at`), po.`supplier_id`, po.`id`, 'المستودع الرئيسي', 'المخزون',
       'مستلَمة', 'استلامٌ مُثبَّتٌ رجعيًّا بقرار المالك 2026-07-27 — الأمرُ كان مصروفًا منشورًا بلا سجل استلام',
       po.`created_by`, NOW()
  FROM `proc_order` po
 WHERE po.`id` = 830
   AND NOT EXISTS (SELECT 1 FROM `proc_receipt_custody` rc WHERE rc.`order_id` = 830);

INSERT INTO `proc_receipt_line` (`company_id`, `custody_id`, `item_id`, `item_name`, `qty`, `created_at`)
SELECT ol.`company_id`, rc.`id`, ol.`item_id`, ol.`item_name`, ol.`qty`, NOW()
  FROM `proc_order_line` ol
  JOIN `proc_receipt_custody` rc ON rc.`order_id` = ol.`order_id` AND rc.`code` = 'PRC-RC-0830'
 WHERE ol.`order_id` = 830
   AND NOT EXISTS (SELECT 1 FROM `proc_receipt_line` rl WHERE rl.`custody_id` = rc.`id`);

-- ── ⑥ تقديمُ حالات الأوامر المستلَمة فعلًا إلى «استلام نهائي» ──────────────
--    الشرطُ صريح: لا يتقدّم أمرٌ إلا وله سجلُّ استلامٍ **بسطوره** — فلا حالةَ
--    بلا واقعةٍ توثّقها (ومنه يصحّ المصروفُ المنشور سلفًا).
UPDATE `proc_order` po
   SET po.`state` = 'استلام نهائي',
       po.`received_pct` = 100.00,
       po.`final_receipt_at` = COALESCE(po.`final_receipt_at`, (
            SELECT MAX(rc.`receipt_date`) FROM `proc_receipt_custody` rc
             WHERE rc.`order_id` = po.`id` AND COALESCE(rc.`is_deleted`,0) = 0)),
       po.`notes` = CONCAT(COALESCE(po.`notes`,''),
            ' [تصحيح 2026-07-27: قُدِّمت الحالةُ إلى «استلام نهائي» مطابقةً لسجل استلامها القائم]')
 WHERE po.`id` IN (4, 5, 6, 7, 10, 830)
   AND po.`state` IN ('مسودة', 'مؤكَّد')
   AND EXISTS (
        SELECT 1 FROM `proc_receipt_custody` rc
          JOIN `proc_receipt_line` rl ON rl.`custody_id` = rc.`id`
         WHERE rc.`order_id` = po.`id` AND COALESCE(rc.`is_deleted`,0) = 0);

-- ── ⑦ الاستحقاق: من موعد التوريد حيث لا تاريخَ استحقاقٍ مسجَّل ─────────────
UPDATE `proc_order`
   SET `due_date` = DATE(`final_receipt_at`)
 WHERE `due_date` IS NULL AND `final_receipt_at` IS NOT NULL;
