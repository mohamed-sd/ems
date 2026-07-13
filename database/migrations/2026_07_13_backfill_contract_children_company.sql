-- M4 · تعبئة company_id لسطور العقود (أبناءٌ بلا شركة رغم وجود العمود)
-- ─────────────────────────────────────────────────────────────────────────
-- السياق (هجرة المعدات · 2026-07-13): contractequipments (11 صفًّا) و
-- suppliercontractequipments (9 صفوف) عمودهما company_id موجودٌ لكنه NULL
-- في كل الصفوف — فأيُّ عزلٍ مباشرٍ عبر البوابة على هذين الجدولين يُرجِع صفرًا
-- (اكتُشف في get_contract_stats: تفصيل المعدات خرج فارغًا).
-- الأبوان (contracts / supplierscontracts) مقيسان بالشركة كليًّا، فالتعبئة
-- وراثةٌ مباشرة من الأب عبر contract_id — لا تخمين.
-- التراجع: database/backups/M4_down_backfill_contract_children_company.sql
-- (يعيد NULL للصفوف المطابقة لشركة أبيها — الحالة السابقة كانت NULL شاملة).

UPDATE contractequipments ce
INNER JOIN contracts c ON c.id = ce.contract_id
SET ce.company_id = c.company_id
WHERE ce.company_id IS NULL
  AND c.company_id IS NOT NULL;

UPDATE suppliercontractequipments sce
INNER JOIN supplierscontracts sc ON sc.id = sce.contract_id
SET sce.company_id = sc.company_id
WHERE sce.company_id IS NULL
  AND sc.company_id IS NOT NULL;
