-- M5 · تعبئة company_id لملاحظات العقود (امتداد مبدأ M4)
-- ─────────────────────────────────────────────────────────────────────────
-- السياق (هجرة العقود · 2026-07-13): contract_notes.company_id موجود لكنه NULL
-- في الصفوف القديمة (صفٌّ واحد وقت الترحيل) — أي عزلٍ مباشرٍ على الجدول يُخفيها.
-- التعبئة وراثةٌ من العقد الأب عبر contract_id (لا تخمين). الكاتب المهاجَر
-- (contract_actions_handler) بات يحقن الشركة لكل ملاحظةٍ جديدة.
-- التراجع: database/backups/M5_down_backfill_contract_notes_company.sql

UPDATE contract_notes cn
INNER JOIN contracts c ON c.id = cn.contract_id
SET cn.company_id = c.company_id
WHERE cn.company_id IS NULL
  AND c.company_id IS NOT NULL;
