-- بوابة الميزانية (§3.4-②): نوع أثرٍ جديد budget_consumption يُلحق بذيل ENUM
-- روابط المروحة (fin_event_links) — الولادة تخصم من بند الموازنة وتوثق الرابط

ALTER TABLE `fin_event_links`
  MODIFY COLUMN `effect_type` ENUM(
    'revenue_event','supplier_due','employee_due','cost_record','receivable',
    'journal_entry','payment','metric_update','budget_consumption'
  ) NOT NULL;
