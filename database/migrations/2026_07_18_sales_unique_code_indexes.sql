-- ═══════════════════════════════════════════════════════════════════════════
-- ع-5: فهارس UNIQUE على أكواد جداول المبيعات (تفرُّدٌ بنيويٌّ لا سلوكيّ)
-- ───────────────────────────────────────────────────────────────────────────
-- الخلل: التفرُّد كان يعتمد على فحصٍ تطبيقيٍّ يفشل مفتوحًا (catch {$dup=array()})
-- وعرضةٍ لسباق TOCTOU؛ والجداول الـ11 كلها بـ PRIMARY(id) فقط — صفر فهرس فريد
-- على الأكواد. أُثبت: صفر تكرار (company_id, code) شامل المحذوف في كل الـ11،
-- وصفر company_id NULL ⇒ الفهرس ينجح مباشرةً بلا تصحيحٍ مسبق.
--
-- النطاق (company_id, code): الأكواد per-company (CLT-/QUO-/...)، فشركتان قد
-- تحملان نفس الكود، والمنع داخل الشركة الواحدة. يشمل الفهرس المحذوف ناعمًا
-- (لا partial index في MySQL) ⇒ كودُ صفٍّ محذوفٍ لا يُعاد — سلوكٌ محافظٌ مقبول.
--
-- عاطل التكرار: migrate.php يتتبّع بالبصمة (تطبيقٌ واحد). لا idempotency داخلي
-- لأن ALTER ADD UNIQUE لا يدعم IF NOT EXISTS.
-- التراجع: DROP INDEX uq_<t>_company_code على كل جدول (بند تراجعٍ لكلٍّ أدناه).
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE clients             ADD UNIQUE KEY uq_clients_company_code (company_id, client_code);
ALTER TABLE activities          ADD UNIQUE KEY uq_activities_company_code (company_id, activity_code);
ALTER TABLE opportunities       ADD UNIQUE KEY uq_opportunities_company_code (company_id, opp_code);
ALTER TABLE quotations          ADD UNIQUE KEY uq_quotations_company_code (company_id, quotation_code);
ALTER TABLE products            ADD UNIQUE KEY uq_products_company_code (company_id, product_code);
ALTER TABLE pricelists          ADD UNIQUE KEY uq_pricelists_company_code (company_id, pricelist_code);
ALTER TABLE tenders             ADD UNIQUE KEY uq_tenders_company_code (company_id, tender_code);
ALTER TABLE commercial_risks    ADD UNIQUE KEY uq_commercial_risks_company_code (company_id, risk_code);
ALTER TABLE contract_amendments ADD UNIQUE KEY uq_contract_amendments_company_code (company_id, amendment_code);
ALTER TABLE contract_events     ADD UNIQUE KEY uq_contract_events_company_code (company_id, event_code);
ALTER TABLE units_of_measure    ADD UNIQUE KEY uq_units_of_measure_company_code (company_id, uom_code);

-- التراجع (يدويّ عند اللزوم):
-- ALTER TABLE clients             DROP INDEX uq_clients_company_code;
-- ALTER TABLE activities          DROP INDEX uq_activities_company_code;
-- ALTER TABLE opportunities       DROP INDEX uq_opportunities_company_code;
-- ALTER TABLE quotations          DROP INDEX uq_quotations_company_code;
-- ALTER TABLE products            DROP INDEX uq_products_company_code;
-- ALTER TABLE pricelists          DROP INDEX uq_pricelists_company_code;
-- ALTER TABLE tenders             DROP INDEX uq_tenders_company_code;
-- ALTER TABLE commercial_risks    DROP INDEX uq_commercial_risks_company_code;
-- ALTER TABLE contract_amendments DROP INDEX uq_contract_amendments_company_code;
-- ALTER TABLE contract_events     DROP INDEX uq_contract_events_company_code;
-- ALTER TABLE units_of_measure    DROP INDEX uq_units_of_measure_company_code;
