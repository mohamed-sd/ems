# دمجاتٌ معلَّقةٌ تنتظر تبويبات الملفات الأم

مصفوفةُ `NAV-02` تُقرر أن **٢٢ شاشةً تصير تبويباتٍ** في ملفَّين أمّين. وقد
أُطفئت روابطُها في `update0006` الموجة ③ **قبل بناء التبويبات**، ثم **أُرجعت**
في `update0006-b` — لأن إخراجَ شاشةٍ قبل بناء بديلها خسارةٌ صافية.

## الشرطُ قبل إعادة الإطفاء
`Contracts/contracts_details.php` و`Suppliers/supplier_profile.php` **لا تحويان
بنيةَ تبويبات** (قِيس: صفرُ `nav-tabs`). فالدمجُ يستلزم أولًا:
① بناءَ ستةِ تبويباتٍ عليا في ملف عقد المشروع (NAV-01 §8: ستةٌ حدًّا أقصى) ·
② بناءَ تبويبات ملف المورد · ③ نقلَ محتوى الشاشات الاثنتين والعشرين إليها ·
④ تحويلَ المسار بعدّاد ثم الإطفاء.

## → ملفُّ عقد المشروع (١٥)
`Clients/contract_amendments` · `Clients/contract_commitments` · `Clients/contract_events` ·
`Contracts/contract_baseline` · `contract_guarantees` · `contract_lifecycle` · `contract_lines` ·
`contract_monthly_plan` · `contract_obligations` · `contract_payment_schedule` ·
`contract_resource_plan` · `contract_sites` · `penalties` · `plan_actual_link` · `price_terms`

## → ملفُّ المورد (٧)
`Finance/supplier_statement_fin` · `Suppliers/supplier_capacity` · `supplier_closure` ·
`supplier_contract_lines` · `supplier_documents` · `supplier_evaluation` · `supplier_rules`
