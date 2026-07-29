-- ═══════════════════════════════════════════════════════════════════════════
-- نسبةُ الاستلام محسوبةً من الكميات لا مُسنَدةً بالجملة — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- الترحيلُ السابق أسند 100٪ لكل أمرٍ له سجلُّ استلام — وهو صحيحٌ بالمصادفة اليوم
-- (كلُّ الأوامر مستلَمةٌ كميًّا بالكامل) لكنه **غيرُ صحيحٍ بالبناء**: أمرٌ استُلم
-- نصفُه كان سيُوسم مكتملًا. تُعاد النسبةُ اشتقاقًا من مصدرها:
--     received_pct = Σ كميات سطور الاستلام ÷ Σ كميات بنود الأمر × 100
-- فتبقى صادقةً مهما تغيّرت البيانات (المبدأ ١٤: تُشتق لا تُدخل).
-- ═══════════════════════════════════════════════════════════════════════════

UPDATE `proc_order` po
   SET po.`received_pct` = LEAST(100.00, ROUND(
        COALESCE((SELECT SUM(rl.`qty`)
                    FROM `proc_receipt_line` rl
                    JOIN `proc_receipt_custody` rc ON rc.`id` = rl.`custody_id`
                   WHERE rc.`order_id` = po.`id` AND COALESCE(rc.`is_deleted`,0) = 0), 0)
        / NULLIF((SELECT SUM(ol.`qty`) FROM `proc_order_line` ol WHERE ol.`order_id` = po.`id`), 0)
        * 100, 2))
 WHERE EXISTS (SELECT 1 FROM `proc_order_line` ol2 WHERE ol2.`order_id` = po.`id`);

-- أمرٌ بلا بنودٍ يبقى صفرًا (لا نسبةَ بلا مقام).
UPDATE `proc_order` po
   SET po.`received_pct` = 0.00
 WHERE NOT EXISTS (SELECT 1 FROM `proc_order_line` ol WHERE ol.`order_id` = po.`id`);
