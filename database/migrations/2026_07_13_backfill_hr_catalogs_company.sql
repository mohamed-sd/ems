-- M6 · تعبئة company_id لكتالوجَي الموارد البشرية (job_titles + employee_roles)
-- ─────────────────────────────────────────────────────────────────────────
-- السياق (هجرة الموظفين · 2026-07-13): الجدولان T_TENANT بالعقد (كتالوجات
-- HR مِلكية لكل شركة) لكن العمود NULL في كل الصفوف (16+8) — أي عزلٍ مباشرٍ
-- عبر البوابة يُخفيها كلها. لا عمود created_by للوراثة الدقيقة، والواقع
-- المقيس: كل مستخدمي الأدوار المنشئة (دور 4) لشركة 4، وكل مراجع
-- employees.job_title_id (×34) لموظفي شركة 4، وموظف شركة 1 الوحيد بلا
-- مرجعٍ إطلاقًا ⇒ التعبئة إلى الشركة 4 هي الوراثة الصحيحة الوحيدة.
-- التراجع: database/backups/M6_down_backfill_hr_catalogs_company.sql

UPDATE job_titles     SET company_id = 4 WHERE company_id IS NULL;
UPDATE employee_roles SET company_id = 4 WHERE company_id IS NULL;
