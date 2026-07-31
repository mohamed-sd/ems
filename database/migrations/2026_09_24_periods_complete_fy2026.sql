-- ═══════════════════════════════════════════════════════════════════════════
-- إكمالُ الفترات الشهرية للسنة المالية 2026 — 2026-08-01
-- ───────────────────────────────────────────────────────────────────────────
-- **السبب المقيس (لا تحسين):** `fin_financial_periods` فيه سنةٌ ماليةٌ مفتوحة
-- (2026-01-01..2026-12-31) و**سبعةُ أشهرٍ فقط** (يناير→يوليو). ومع دخول
-- **أغسطس** صار **صفرُ فترةٍ شهريةٍ تغطي اليوم** — فسقطت حزمةُ `period_lock_test`
-- عند شرطها الأول («لا فترةَ شهريةً لليوم»)، **ولم يكن ذلك انحدارًا** بل
-- **فجوةَ بيانات**: الفتراتُ تُعرَّف سنويًّا ولم تُستكمل.
--
-- ⚠ گوتشا مقيسةٌ في البيئة (تُسجَّل لأنها تُهدر ساعات):
--   `SELECT CURDATE()` في MySQL = **2026-08-01** (توقيتٌ محلي UTC+3)
--   بينما `date()` في PHP = **2026-07-31** (العملية على UTC).
--   فثلاثُ ساعاتٍ يوميًّا **يختلف فيها «اليوم» بين الطبقتين** — وأيُّ منطقٍ
--   يقارن تاريخَ PHP بصفٍّ اختير بـ`CURDATE()` يمكن أن يخطئ يومًا كاملًا.
--
-- الحالةُ `planned` **مقصودة**: حارسُ الفترة (`includes/period_guard.php`)
-- ينصّ صراحةً على أن `planned` **ليست مقفلة** — «الإقفالُ فعلٌ يقع على ما فُتح».
-- فالإضافةُ **صفرُ تغييرٍ سلوكي**: ما كان يمرّ بلا فترةٍ معرَّفةٍ يمرّ بها.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `fin_financial_periods`
    (`company_id`, `fiscal_year`, `period_type`, `period_no`, `start_date`, `end_date`,
     `state`, `posting_allowed`, `created_at`)
SELECT y.`company_id`, 2026, 'month', m.`n`,
       DATE_FORMAT(CONCAT('2026-', LPAD(m.`n`, 2, '0'), '-01'), '%Y-%m-%d'),
       LAST_DAY(CONCAT('2026-', LPAD(m.`n`, 2, '0'), '-01')),
       'planned', 0, NOW()
  FROM (SELECT DISTINCT `company_id` FROM `fin_financial_periods`
         WHERE `fiscal_year` = 2026 AND `period_type` = 'year') y
  JOIN (SELECT 8 AS n UNION ALL SELECT 9 UNION ALL SELECT 10
        UNION ALL SELECT 11 UNION ALL SELECT 12) m
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT * FROM `fin_financial_periods`) p
        WHERE p.`company_id` = y.`company_id` AND p.`period_type` = 'month'
          AND p.`fiscal_year` = 2026 AND p.`period_no` = m.`n`);
