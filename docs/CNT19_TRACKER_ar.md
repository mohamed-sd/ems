# تتبّعُ حزمةِ الحاوياتِ والقيدِ اليوميِّ والمبيعاتِ والموردين — تقريرٌ متجدد

**الوثائقُ الخمس:** SUP-CNT-01 (19-ب حاويات الموردين والقيد اليومي) · SAL-CNT-01 (18-أ نظام الحاويات ودورة الإدخال) · TS-01 (70 المواصفة التقنية) · M-08 (18 المبيعات) · M-09 (19 الموردون)

**آخرُ جولةِ قياس:** الجولة ③ — 2026-08-16 · الفرع `fix/remediation-2026-08` · القاعدة `equipation_manage`

---

## ١ — الأرقامُ الحاكمة

| الوثيقة | المتطلباتُ الذرية | الوحداتُ المقيسة | منجزٌ بشاهد | جزئيٌّ بمكافئٍ حي | متبقٍّ |
|---|---|---|---|---|---|
| M-08 المبيعات (27 شاشة · 32 فعلًا) | 756 | 59 | 38 | 6 | 15 |
| M-09 الموردون (22 شاشة · 28 فعلًا) | 658 | 50 | 26 | 12 | 12 |
| TS-01 المواصفة التقنية | 474 | 61 | 25 | 10 | 26 |
| SAL-CNT-01 الهرم الخماسي | 120 | 26 | 5 | 6 | 15 |
| SUP-CNT-01 حاويات الموردين والقيد | 117 | 20 | 4 | 5 | 11 |
| **الإجمالي** | **2,125** | **216** | **98 (45.4٪)** | **39 (18.1٪)** | **79 (36.6٪)** |

**النسبةُ المرجّحة (الجزئيُّ نصفًا): ~54.4٪** — والقياسُ على الوحداتِ البنائيةِ (شاشة/فعل/جدول/صيغة/فحص) لا على المتطلباتِ الذريةِ فردًا فردًا. المتطلباتُ الذريةُ الـ2,125 تُقاس بابًا بابًا في الجولاتِ اللاحقة.

---

## ٢ — الخريطةُ الاسميةُ الحاسمة (TS-01 نفسُها توجب المطابقةَ قبل الإنشاء)

الأسماءُ في الوثائقِ **مقترحةٌ لا نهائية** — والمخططُ الحيُّ نافذ. هذه المطابقةُ المثبتةُ بالفحص:

| اسمُ الوثيقة | النظيرُ الحي | الحكم |
|---|---|---|
| `shift_entries` (القيد اليومي) | `unit_entries` + `unit_time_log` | **منجزٌ بالمكافئ** — وُسِّعت `unit_entries` (container_key · client_id · meter_before/after · fuel_* · seed_tag · entity_layer · shift_slot_key · created_by_role) والساعاتُ مطبَّعةٌ في `unit_time_log` (hours · ops_state · resp_party ≡ run/standby/breakdown + liable_party) |
| `sup_handover_events` (حدث التسليم) | `container_swaps` (out_ref/in_ref/moved_qty/effective_from/doc_ref) | **مكافئٌ بنيويٌّ قائم** — غيرُ موصولٍ بدورةِ حصصِ الموردين ولا بقواعدِ HO-01..05 |
| هرمُ الحاويات `annual/type/machine_slots` | `op_containers` (level · parent_id · seat_no · seat_kind · role_kind · cap_qty/allocated/consumed/remaining · supplier_id · shift_no · valid_from/to) | **بنيةٌ حيةٌ** — الاشتقاقُ الصعوديُّ بقوادحَ غيرُ مثبت (صفرُ قادحٍ على الجداول) |
| `coverage` (التزام النوع) | `contract_commitments` | حيٌّ — بلا أعمدةِ TS-01 (container_key · slot_monthly_basis · renewal_months · type_capacity: **0/4**) |
| `quota_ledger` (دفتر الاستهلاك) | `container_consumption` (qty · idem_key · source_ref) | جزئي — بلا layer/share_key/gap_units ولا رصيد قبل/بعد |
| `supplier_settle` | `settlements` + `settlement_lines` (بنودٌ مطبَّعةٌ بدل أعمدةٍ مسطحة) | جزئي — **التسوياتُ الأربعُ والمتحمَّلُ من الخزينة `borne_by_treasury` غيرُ موجودةٍ بأيِّ صيغة** |
| سلسلةُ الوحدات st.01–10 | `unit_approvals` (entry_id · round_no · stage · decision) + `client_match_*` في unit_entries | البياناتُ حية — شاشاتُ المحطات المسماة غائبة |

---

## ٣ — M-08 المبيعات: الشاشاتُ الـ27

**قائمةٌ بالملفِّ الحي (18):** unbilled ✓ · clients ✓ · projects ✓ · opportunities ✓ · quotations ✓ · contracts ✓ · coverage→`contract_coverage.php` ✓ · contract_terms→`price_terms.php` ✓ · claims ✓ · penalties ✓ · commercial_risks ✓ · contract_amendments ✓ · contract_events ✓ · products ✓ · pricelists ✓ · units_of_measure ✓ · business_models ✓ · contract_review ✓

**جزئيٌّ بمكافئ (4):** units (البنيةُ في contract_lines/op_containers — لا شاشةَ «وحدات مرقَّمة بدور مستنتَج») · price_adjust (`cron_price_adjustment.php` بلا شاشة) · unit_client_invoice (`tax_invoices.php`) · unit_statement_client (`client_statement.php`)

**غائبٌ (5):** risk_dept_sal · gov_dept_sal · unit_client_match (الأعمدةُ client_match_* حيةٌ في unit_entries بلا شاشة) · unit_sales_gate · unit_client_accept

**الأفعالُ الـ32:** مسجّلٌ حيًّا (bound_page/alias) **20** — منها contract.activate · client.create · opp.qualify · quote.send/accept · unit.define · claim.issue · claim.client.approve · penalty.compute · price.approve · sales.model.define… · معلَنٌ غيرَ مبني **2** (cov.define · terms.set) · غائبٌ من القاموس **10** (risk.sal.view/raise/evidence · gov.sal.view/attest · unit.st.03/04/08/09 · unit.stmt.client)

---

## ٤ — M-09 الموردون: الشاشاتُ الـ22

**قائمةٌ بالملفِّ الحي (12):** suppliers ✓ · supplier_contracts→`supplierscontracts.php` ✓ · supplier_settle→`settlements.php` ✓ · supplier_bank ✓ · supplier_capacity ✓ · supplier_rules ✓ · supplier_evaluation ✓ · supplier_advances ✓ · supplier_closure ✓ · supplier_plan→`equipment_plan.php` ✓ · equipment_quota ✓ (Operations) · ap_container_shares→`shares_coverage.php` ✓

**جزئيٌّ بمكافئ (7):** supplier_quota (`supplier_contract_lines.php`/showcontractsuppliers) · supplier_stmt (`Finance/supplier_statement_fin.php` بملكية المالية لا الموردين) · supplier_equip (جدول `suppliercontractequipments` حي بلا شاشة مالكين) · supplier_perf (التقييم والجاهزية موزعان) · quota_ledger (`container_consumption` بلا شاشة) · ap_oblig_gen (cap_obligation_id حي في unit_entries بلا شاشة توليد) · unit_supplier_approve (`unit_approvals` + محرك المهام بلا شاشة st.05 مسماة)

**غائبٌ (3):** risk_dept_sup · gov_dept_sup · unit_statement_supplier

**الأفعالُ الـ28:** مسجّلٌ حيًّا **14** (supplier.activate/evaluate · settle.approve · sc.activate · stmt.issue · bank.verify · perf.penalty · supp.eval/close · cap.measure · rule.define · sadv.grant · eq.quota.allocate/shift) · معلَنٌ غيرَ مبني **5** (quota.allocate/consume · se.register · quota.post · plan.commit) · غائبٌ **9** (risk.sup.×3 · gov.sup.×2 · ap.shares.allocate · ap.oblig.generate · unit.st.05 · unit.stmt.supplier)

---

## ٥ — TS-01 المواصفة التقنية (الحلقةُ الحرجة)

| الباب | الحالة | الشاهد |
|---|---|---|
| ترتيبُ التنفيذ ١: مطابقةُ الأسماء | ✅ منجز | القرارُ الموثَّق: توسيعُ `unit_entries` بدل إنشاء `shift_entries` — عينُ ما يوجبه TSP-0003 |
| ترتيب ٢–٤: الجدولُ والفعلانِ والشاشة | ✅ منجز | `shift_entry.php` قائمة · `shift.entry.record/void` مسجّلان bound_page · nav route حي |
| ترتيب ٥: ثلاثون يومًا قيدٍ فعلي | ⏳ جارٍ | تشغيليٌّ — بدأ 2026-08 |
| ترتيب ٦: منظرا F-09/F-10 (الوسيط/أيام العمل) | ⚠️ نصفُه | **F-10 ✅** — `v_monthly_performance` منظرٌ حيٌّ يحسب أيامَ العملِ `COUNT(DISTINCT entry_date)` من القيدِ اليومي (74ce9b5) · **F-09 الوسيطُ ❌** لا منظرَ له |
| ترتيب ٧–٩: جداولُ الحاويات والقوادح والإسناد | ⚠️ جزئي | op_containers حيٌّ بقيودِ CHECK بنيوية (`ck_container_alloc/consumed/parent/cap`: المخصصُ والمستهلكُ ≤ السعة · الأبوةُ بالمستوى) + هجراتُ ضبطِ السعةِ الأربع (cba88fd) — **قوادحُ الاشتقاقِ الصعوديِّ F-01..F-04 ما تزال غائبة** |
| ترتيب ١٠: الفحوصُ العشرةُ في بوابةِ الدمج | ⚠️ الأداةُ منجزةٌ | `tools/se03_ten_checks.php` — العشرةُ مترجمةٌ للمخططِ الحيِّ وخضراءُ **10/10** (cba88fd) · إدراجُها بوابةَ دمجٍ ترسب افتراضًا (AC-T12) لم يُثبت بعد |
| الشروطُ المسبقةُ العشرة | ⚠️ 7 قائمةٌ سلفًا | العزل/الحارس/الخدمة/القاموس/seed_tag أنماطٌ نافذةٌ في المنصة · entity_layer على unit_entries وحدَها · السبعةُ الحاكمةُ جزئية |
| ستةُ تعديلاتٍ على القائم | ❌ غالبًا | contract_amendments **0/7** أعمدة · commitments **0/4** · quota_ledger لا يوجد · units (slot_role…) لا نظيرَ بالاسم — والمفاهيمُ متفرقةٌ في op_containers |
| الصيغُ الاثنتا عشرة F-01..F-12 | ⚠️ 1/12 | F-10 (أيامُ العمل) حيةٌ في `v_monthly_performance` — والباقي (قوادحُ الاشتقاقِ والوسيطُ والمتحمَّل…) غائب |
| الأفعالُ الثمانية | 2/8 | shift.entry.record/void فقط — cnt.annual.open · cnt.types.define · cnt.slots.open · cnt.slot.allocate · sup.handover.record · sup.settle.apply غائبة |
| السايدبارُ بالدورةِ المستندية (SAL 8 · SUP 8 · SIT 6 مرحلة) | ❌ | صفرُ تسميةٍ من النمط «نبدأ من العميل» في nav_items |
| قاموسُ المبتدئ (16 مصطلحًا) ولغةُ الشاشة (6 قواعد) | ❌ غيرُ مقيس | لم يُبنَ كمكوِّن |

---

## ٦ — SUP-CNT-01 و SAL-CNT-01 (الوظيفيتان)

**منجز:** بابُ «الحلقةِ المفقودة — القيدُ اليومي» (16 متطلبًا) هو المنجزُ الأكبر — القيدُ لكلِّ ورديةٍ لكلِّ آليةٍ حيٌّ بشاشتِه وفعلَيه واشتقاقِ العميلِ والإلغاءِ العاكس. محطاتُ دورةِ الإدخالِ (عميل/مشروع/عقد/تايم شيت) حيةٌ سلفًا.

**جزئي:** الطبقاتُ الثلاثُ وأدوارُ الموردِ (role_kind/seat_kind في op_containers) · حدثُ التسليمِ (container_swaps بلا قواعد HO) · المنفَّذُ غيرُ المنجَز (qty مقابل qty_billable في unit_entries ✓ منفصلان).

**غائب:** الطبقةُ الشهريةُ كصفوفٍ تُقاس عليها التسوية · التسوياتُ الأربعُ بأعمدتِها ومستندِها (chk_adj_doc) · **المتحمَّلُ من الخزينة** (لا وجودَ له بأيِّ اسم — وهو مقياسُ الخسارةِ المباشرة) · وسيطُ ساعةِ اليومِ وأيامُ العملِ كاستعلامين · فحوصُ السلسلةِ العشرة · خطةُ الإطلاقِ التجريبيِّ (36 يومًا — لم تبدأ).

**ترتيبُ التصحيحِ الستةُ (SUP-CNT-01 §٤-٧):** ① ترحيلُ الوقائعِ للدفتر — **⏳ 96.1٪: 5,053/5,256 مُرحَّلًا بقيودٍ متوازنة** (كانت 9 · قمعُ الترحيلِ أُغلق في 4d89370 وفاحصُ `fin01_posting_verify` أخضرُ 8/8 — المتبقي 203: Published=164 · Draft=37 · PostingFailed=1 · Reversed=1 والهدفُ >99٪) · ② بنودُ المستخلصِ الصفرية (298) — مفتوح · ③ القيدُ اليومي — **✅ منفَّذ** · ④ حدثُ التسليم — جزئي · ⑤ التسوياتُ والمتحمَّل — غائب · ⑥ الشاشاتُ المسرَّبة — يُقاس لاحقًا.

---

## ٧ — المتبقّي مرتَّبًا (خطةُ الجولاتِ القادمة)

1. **حاجبان ماليان:** إغلاقُ ذيلِ الترحيلِ من 96.1٪ إلى >99٪ (203 واقعةً متبقية — منها Published=164 وPostingFailed=1 تُشخَّص) + بنودُ المستخلصِ الصفرية (298 — مفتوح).
2. **حدثُ تسليمِ الحصة:** وصلُ `container_swaps` بدورةِ الموردين + قواعدُ HO-01..05 + فعل `sup.handover.record` + شاشتُه.
3. **التسوياتُ الأربعُ والمتحمَّل:** أعمدةُ/بنودُ adj الأربعةُ بقيد «لا تسويةَ بلا مستند» في القاعدة + عمود/مقياس `borne_by_treasury` + فعل `sup.settle.apply`.
4. **الصيغُ والقوادح:** F-01..F-08 قوادحَ وخدمةً · F-09 منظرَ الوسيطِ (F-10 أيامُ العملِ أُنجزت في `v_monthly_performance`).
5. **بوابةُ الدمج:** الفحوصُ العشرةُ منجزةٌ خضراءَ كأداةٍ (`tools/se03_ten_checks.php`) — يبقى إدراجُها بوابةً ترسب افتراضًا (AC-T12).
6. **شاشاتُ سلسلةِ الوحداتِ المسماة:** st.03/04/05/08/09 + كشفا العميلِ والموردِ النطاقيان + أفعالُها العشرة.
7. **أزواجُ المخاطرِ والحوكمة:** risk_dept_sal/sup + gov_dept_sal/sup (القالبُ جاهزٌ في `tools/build_gov_dept_wrappers.php`).
8. **سايدبارُ الدورةِ المستندية** (22 مرحلةً بأسماءِ أفعالٍ وسطرِ شرح) + قاموسُ المبتدئ.
9. **خطةُ الإطلاقِ التجريبيِّ** PL-01..PL-07 (36 يومًا · إكسل بالتوازي حتى تطابقِ ثلاثةِ أشهر).

---

## ٨ — منهجيةُ القياسِ وإعادةُ التشغيل

- الشاشات: وجودُ الملفِّ خارج worktrees (`find . -maxdepth 2 -name "X.php"`).
- الأفعال: `nav09_action_map` بعمودَي `canonical_code/live_code` والحالات (bound_page/alias = حي · declared_unbuilt · غائب).
- الجداولُ والأعمدة: `information_schema` على `equipation_manage`.
- القوادحُ والمناظر: `information_schema.TRIGGERS/VIEWS`.
- **مسبارُ الجولة:** `php tools/cnt19_probe.php` — يعيد إنتاجَ القياسِ كاملًا. عدِّل قوائمَه عند إغلاقِ بنود.

## ٩ — سجلُّ الجولات

| الجولة | التاريخ | المنجزُ المقيس | المرجّح | ملاحظة |
|---|---|---|---|---|
| ① | 2026-08-16 | 87/216 (40٪) | ~49٪ | خطُّ الأساس — القيدُ اليوميُّ أبرزُ منجزٍ حديث |
| ② | 2026-08-16 | 88/216 (40.7٪) | ~49.5٪ | تصحيحُ قياسٍ التقط منجزَين فاتا الجولةَ ①: **F-10** حيةٌ في `v_monthly_performance` · و**ترحيلُ الدفترِ 21.2٪** (1,115/5,256 بفاحصٍ أخضرَ 8/8) بعد 0.17٪ — والمسبارُ صار يقيسهما آليًّا |
| ③ | 2026-08-16 | 98/216 (45.4٪) | ~54.4٪ | **الفحوصُ العشرةُ CK-01..10 خضراءُ 10/10** مترجمةً للمخططِ الحيِّ (`tools/se03_ten_checks.php` · cba88fd) · **الترحيلُ 96.1٪** (5,053/5,256 · 4d89370) والمتبقي 203 · وقيودُ CHECK بنيويةٌ على op_containers مثبتة |
