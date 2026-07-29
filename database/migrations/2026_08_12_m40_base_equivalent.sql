-- ═══════════════════════════════════════════════════════════════════════════
-- M-40 · إكمالُ ثلاثية العملة حيث غابت (FES §3.3) — 2026-07-30
-- ───────────────────────────────────────────────────────────────────────────
-- **القياس قبل**: 55 حدثَ دفترٍ بلا سعرٍ ولا معادل (45 SDG + 10 USD) —
-- و`fin_financial_events` أصلًا **بلا عمود معادل** · 6 ذمم `fin_dues` بلا
-- ثلاثية · `fin_payments` بلا عمودَي سعرٍ ومعادل (3 دفعات) · 9 قيودٍ (M-38)
-- كانت تنتظر سعرَ SDG→USD **وقد سُجّل** (0.000185 من 2024-07-01) · آثارُ
-- H-12 بلا معادل.
--
-- **القاعدة (FES §3.3)**: base = amount × fx_rate إلى عملة أساس الكيان (USD
-- من admin_companies) · السعرُ النافذُ بتاريخ الحركة من fin_fx_rates ·
-- التقريبُ إلى منزلتين Half-Up.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① عمودُ المعادل على رأس الدفتر (كان غائبًا بنيويًّا) ─────────────────────
ALTER TABLE `fin_financial_events`
  ADD COLUMN `base_amount` DECIMAL(18,2) DEFAULT NULL
      COMMENT 'M-40 (FES §3.3): المعادلُ الموحّد = ROUND(amount × fx_rate, 2) — NULL = سعرٌ غيرُ مُدخَل لتاريخه (معلَن)'
      AFTER `fx_rate`;

-- ── ② عمودا السعر والمعادل على الدفعات ──────────────────────────────────────
ALTER TABLE `fin_payments`
  ADD COLUMN `fx_rate` DECIMAL(18,6) DEFAULT NULL
      COMMENT 'M-40: سعرُ الصرف النافذ يومَ الدفع' AFTER `currency`,
  ADD COLUMN `base_amount` DECIMAL(18,2) DEFAULT NULL
      COMMENT 'M-40: المعادلُ الموحّد للدفعة' AFTER `fx_rate`;

-- ── ③ التعبئةُ الرجعية — السعرُ النافذ بتاريخ الحركة ────────────────────────
-- الدفتر: سعرُ كلِّ حدثٍ بتاريخ وقوعه (وإلا إنشائه)
UPDATE `fin_financial_events` e
  JOIN `fin_fx_rates` r
    ON r.`company_id` = e.`company_id`
   AND r.`currency_code` = e.`currency`
   AND COALESCE(r.`is_deleted`, 0) = 0
   AND r.`effective_from` = (
        SELECT MAX(r2.`effective_from`) FROM `fin_fx_rates` r2
         WHERE r2.`company_id` = e.`company_id` AND r2.`currency_code` = e.`currency`
           AND COALESCE(r2.`is_deleted`, 0) = 0
           AND r2.`effective_from` <= DATE(COALESCE(e.`occurred_at`, e.`created_at`)))
   SET e.`fx_rate` = r.`rate_to_base`,
       e.`base_amount` = ROUND(e.`amount` * r.`rate_to_base`, 2)
 WHERE e.`fx_rate` IS NULL;

-- آثارُ الحدث: معادلُ كلِّ أثرٍ من سعر رأسه
UPDATE `fin_event_effects` fe
  JOIN `fin_financial_events` e ON e.`id` = fe.`event_id`
   SET fe.`base_amount` = ROUND(fe.`amount` * e.`fx_rate`, 2)
 WHERE fe.`base_amount` IS NULL AND e.`fx_rate` IS NOT NULL;

-- الذممُ الست: السعرُ النافذ بتاريخ إنشائها
UPDATE `fin_dues` d
  JOIN `fin_fx_rates` r
    ON r.`company_id` = d.`company_id`
   AND r.`currency_code` = d.`currency`
   AND COALESCE(r.`is_deleted`, 0) = 0
   AND r.`effective_from` = (
        SELECT MAX(r2.`effective_from`) FROM `fin_fx_rates` r2
         WHERE r2.`company_id` = d.`company_id` AND r2.`currency_code` = d.`currency`
           AND COALESCE(r2.`is_deleted`, 0) = 0
           AND r2.`effective_from` <= DATE(d.`created_at`))
   SET d.`fx_rate` = r.`rate_to_base`,
       d.`base_amount` = ROUND(d.`amount` * r.`rate_to_base`, 2)
 WHERE d.`fx_rate` IS NULL;

-- الدفعاتُ الثلاث: السعرُ يومَ التنفيذ
UPDATE `fin_payments` p
  JOIN `fin_fx_rates` r
    ON r.`company_id` = p.`company_id`
   AND r.`currency_code` = p.`currency`
   AND COALESCE(r.`is_deleted`, 0) = 0
   AND r.`effective_from` = (
        SELECT MAX(r2.`effective_from`) FROM `fin_fx_rates` r2
         WHERE r2.`company_id` = p.`company_id` AND r2.`currency_code` = p.`currency`
           AND COALESCE(r2.`is_deleted`, 0) = 0
           AND r2.`effective_from` <= DATE(COALESCE(p.`paid_at`, p.`created_at`)))
   SET p.`fx_rate` = r.`rate_to_base`,
       p.`base_amount` = ROUND(p.`amount` * r.`rate_to_base`, 2)
 WHERE p.`fx_rate` IS NULL;

-- قيودُ اليومية (M-38 كانت تنتظر السعر): سعرُ تاريخ الحركة
UPDATE `fin_journal_entries` je
  JOIN `fin_fx_rates` r
    ON r.`company_id` = je.`company_id`
   AND r.`currency_code` = je.`currency`
   AND COALESCE(r.`is_deleted`, 0) = 0
   AND r.`effective_from` = (
        SELECT MAX(r2.`effective_from`) FROM `fin_fx_rates` r2
         WHERE r2.`company_id` = je.`company_id` AND r2.`currency_code` = je.`currency`
           AND COALESCE(r2.`is_deleted`, 0) = 0
           AND r2.`effective_from` <= je.`txn_date`)
   SET je.`fx_rate` = r.`rate_to_base`,
       je.`base_amount` = ROUND(je.`total_debit` * r.`rate_to_base`, 2)
 WHERE je.`fx_rate` IS NULL;

-- ── ④ قيدُ التزاوج البنيوي على الدفتر والدفعات (نظيرُ ck_je_fx_pair) ────────
ALTER TABLE `fin_financial_events`
  ADD CONSTRAINT `ck_ffe_fx_pair`
  CHECK ((`fx_rate` IS NULL AND `base_amount` IS NULL)
      OR (`fx_rate` IS NOT NULL AND `base_amount` = ROUND(`amount` * `fx_rate`, 2)));

ALTER TABLE `fin_payments`
  ADD CONSTRAINT `ck_pay_fx_pair`
  CHECK ((`fx_rate` IS NULL AND `base_amount` IS NULL)
      OR (`fx_rate` IS NOT NULL AND `base_amount` = ROUND(`amount` * `fx_rate`, 2)));
