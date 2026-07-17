-- ═══════════════════════════════════════════════════════════════════════════
-- إصلاح متتالية ترقيم الأحداث EV — رفعها فوق كل رقمٍ مستعملٍ فعلًا
-- ───────────────────────────────────────────────────────────────────────────
-- العلة: تنظيف حزمة الناشر كان يحذف صفّ `fin_financial_events:EV:{company}`.
-- كان ذلك بلا أثرٍ حين لم يكن في الدفتر رقمٌ بصيغة EV-nnnn (الأساس القديم
-- بصيغة FIN-EV-nnnn من fin_gen_code). ومنذ صارت بوابة D05 تلد أحداثًا حقيقية
-- عبر ServerId، صار حذف المتتالية يعيد العدّاد إلى الصفر فيصطدم أول نشرٍ
-- بـuq_fin_event_no على رقمٍ إنتاجيٍّ قائم.
--
-- الإصلاح هنا: بذر/رفع المتتالية لكل شركةٍ إلى أعلى رقم EV مستعملٍ فيها
-- (GREATEST — لا تُخفَّض أبدًا). وفجوات الترقيم مقبولةٌ بالتصميم (نمط nextNo).
-- والحارس الدائم في الحزمة نفسها: لم تعد تحذف الصف، وتتحقق أن المتتالية
-- تغطي كل رقمٍ قائمٍ قبل أن تُنهي.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `ems_sequences` (`scope`, `next_val`)
SELECT CONCAT('fin_financial_events:EV:', `company_id`), 0
FROM `fin_financial_events`
WHERE `event_no` REGEXP '^EV-[0-9]+$'
GROUP BY `company_id`;

UPDATE `ems_sequences` s
JOIN (
    SELECT CONCAT('fin_financial_events:EV:', `company_id`) AS sc,
           MAX(CAST(SUBSTRING(`event_no`, 4) AS UNSIGNED)) AS mx
    FROM `fin_financial_events`
    WHERE `event_no` REGEXP '^EV-[0-9]+$'
    GROUP BY `company_id`
) x ON x.sc = s.`scope`
SET s.`next_val` = GREATEST(s.`next_val`, x.mx);
