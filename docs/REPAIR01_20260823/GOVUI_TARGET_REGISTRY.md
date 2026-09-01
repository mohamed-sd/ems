# `GOVUI_TARGET_REGISTRY` — سجلُّ الأهدافِ بأعمدةِ §4 التسعةَ عشرَ

> مولَّدٌ حيًّا من `01 · الدليل المعماري` و`02 · القيادة` بـ`tools/govui_target_registry.php` · اللقطة **BL-20260901-595c7b3e**
> ⛔ **ولا خانةَ بلا مصدرٍ**: كلُّ صفٍّ يحمل ملفَّه وورقتَه وصفَّه، وكلُّ صنفٍ يحمل قاعدتَه.

## تصنيفُ الأسطحِ السبعةُ (§8)

| الصنف | العدد |
|---|---|
| `DIRECT_ONLY` | **7** |
| `LANDING_PAGE` | **19** |
| `MENU_ITEM` | **308** |
| `PROJECTION` | **3** |
| `TAB_CHILD` | **72** |
| `UTILITY` | **4** |
| **المجموع** | **413** |

## الصفوف

| `Target_ID` | `Workspace` | `Surface_Type` | `Canonical_Screen_Label` | `Canonical_Group_Label` | المسارُ المبنيّ |
|---|---|---|---|---|---|
| `NT-DEP-01-001` | DEP-01 | `MENU_ITEM` | سجل العملاء | التعريف بالعميل والمشروع | `clients/clients.php` |
| `NT-DEP-01-002` | DEP-01 | `TAB_CHILD` | جهات اتصال العملاء | التعريف بالعميل والمشروع | `clients/client_contacts.php` |
| `NT-DEP-01-003` | DEP-01 | `MENU_ITEM` | سجل المشاريع | التعريف بالعميل والمشروع | `projects/projects.php` |
| `NT-DEP-01-004` | DEP-01 | `MENU_ITEM` | الفرص البيعية | الفرص والمتابعة | `opportunities/opportunities.php` |
| `NT-DEP-01-005` | DEP-01 | `MENU_ITEM` | الأنشطة والمتابعات | الفرص والمتابعة | `contracts/sales_activities.php` |
| `NT-DEP-01-006` | DEP-01 | `MENU_ITEM` | احتياج العميل وطلب العرض | الفرص والمتابعة | `opportunities/client_need_rfq.php` |
| `NT-DEP-01-007` | DEP-01 | `MENU_ITEM` | سجل العروض | العرض والتفاوض | `clients/quotations.php` |
| `NT-DEP-01-008` | DEP-01 | `TAB_CHILD` | بنود العروض | العرض والتفاوض | `clients/quotation_lines.php` |
| `NT-DEP-01-009` | DEP-01 | `MENU_ITEM` | التفاوض ومراجعات العرض | العرض والتفاوض | `clients/quotation_negotiation.php` |
| `NT-DEP-01-010` | DEP-01 | `MENU_ITEM` | مراجعة ما قبل العقد | العرض والتفاوض | `contracts/precontract_review.php` |
| `NT-DEP-01-011` | DEP-01 | `MENU_ITEM` | سجل عقود المشاريع | التعاقد والالتزام | `contracts/contracts.php` |
| `NT-DEP-01-012` | DEP-01 | `TAB_CHILD` | بنود العقد والالتزام التجاري | التعاقد والالتزام | `contracts/contract_commercial_lines.php` |
| `NT-DEP-01-013` | DEP-01 | `TAB_CHILD` | مصفوفة الالتزامات | التعاقد والالتزام | `contracts/contract_obligation_matrix.php` |
| `NT-DEP-01-014` | DEP-01 | `MENU_ITEM` | خط الأساس والمستهدفات | التعاقد والالتزام | `contracts/contract_baseline_targets.php` |
| `NT-DEP-01-015` | DEP-01 | `MENU_ITEM` | التغطية التعاقدية — دورات الالتزام | التعاقد والالتزام | `contracts/contract_coverage_cycles.php` |
| `NT-DEP-01-016` | DEP-01 | `MENU_ITEM` | فترات الالتزام والفاقد | التعاقد والالتزام | `contracts/monthly_containers_loss.php` |
| `NT-DEP-01-017` | DEP-01 | `MENU_ITEM` | الأداء والمبيعات الشهرية | الأداء والتحصيل | `contracts/monthly_sales_performance.php` |
| `NT-DEP-01-018` | DEP-01 | `MENU_ITEM` | المطالبات والتسليم للمالية | الأداء والتحصيل | `contracts/claims.php` |
| `NT-DEP-01-019` | DEP-01 | `MENU_ITEM` | الملحقات والتجديد والإغلاق | الأداء والتحصيل | `contracts/contract_amendments_renewal.php` |
| `NT-DEP-01-020` | DEP-01 | `LANDING_PAGE` | لوحة المبيعات | اللوحة — خارج الدورة | `contracts/commercial_board.php` |
| `NT-DEP-02-001` | DEP-02 | `MENU_ITEM` | سجل الموردين | التأسيس | `suppliers/suppliers.php` |
| `NT-DEP-02-002` | DEP-02 | `TAB_CHILD` | جهات الاتصال والمفوضون | التأسيس | `suppliers/supplier_contacts.php` |
| `NT-DEP-02-003` | DEP-02 | `TAB_CHILD` | التأهيل القانوني والائتماني | التأسيس | `suppliers/supplier_qualification.php` |
| `NT-DEP-02-004` | DEP-02 | `MENU_ITEM` | معدات المورد المتاحة | التأسيس | `suppliers/equipment_plan.php` |
| `NT-DEP-02-005` | DEP-02 | `MENU_ITEM` | احتياجات التغطية | التعاقد | `contracts/contract_coverage.php` |
| `NT-DEP-02-006` | DEP-02 | `MENU_ITEM` | الترشيح ومراجعة التعاقد | التعاقد | `suppliers/rfq_requests.php` |
| `NT-DEP-02-007` | DEP-02 | `MENU_ITEM` | عروض الموردين والتفاوض | التعاقد | `suppliers/supplier_offers.php` |
| `NT-DEP-02-008` | DEP-02 | `MENU_ITEM` | سجل عقود الموردين | التعاقد | `suppliers/supplierscontracts.php` |
| `NT-DEP-02-009` | DEP-02 | `TAB_CHILD` | بنود عقود الموردين | التعاقد | `suppliers/supplier_contract_lines.php` |
| `NT-DEP-02-010` | DEP-02 | `TAB_CHILD` | مصفوفة المسؤوليات والتكاليف | التعاقد | `suppliers/supplier_responsibility_matrix.php` |
| `NT-DEP-02-011` | DEP-02 | `MENU_ITEM` | الوحدات المكافئة وتخصيص الحصص | التشغيل والتغطية | `suppliers/supplier_quota_distribution.php` |
| `NT-DEP-02-012` | DEP-02 | `MENU_ITEM` | حصص الموردين والوحدات التعاقدية | التشغيل والتغطية | `suppliers/supplier_contract_units.php` |
| `NT-DEP-02-013` | DEP-02 | `MENU_ITEM` | سجل الإشغال والتسليم | التشغيل والتغطية | `suppliers/supplier_slot_handover.php` |
| `NT-DEP-02-014` | DEP-02 | `MENU_ITEM` | توزيع الوحدات التعاقدية على المعدات | التشغيل والتغطية | `suppliers/supplier_unit_equipment.php` |
| `NT-DEP-02-015` | DEP-02 | `MENU_ITEM` | الجاهزية والإحلال والاحتياط | التشغيل والتغطية | `suppliers/supplier_readiness_reserve.php` |
| `NT-DEP-02-016` | DEP-02 | `MENU_ITEM` | مستهدفات الموردين | التشغيل والتغطية | `suppliers/supplier_targets.php` |
| `NT-DEP-02-017` | DEP-02 | `MENU_ITEM` | الأداء والوحدات المعتمدة | التشغيل والتغطية | `suppliers/supplier_performance_entries.php` |
| `NT-DEP-02-018` | DEP-02 | `MENU_ITEM` | قياس التغطية والعجز والفائض | التشغيل والتغطية | `Suppliers/shares_coverage.php` |
| `NT-DEP-02-019` | DEP-02 | `MENU_ITEM` | النيابية والسلف والخصومات | المالية | `suppliers/supplier_onbehalf_advances.php` |
| `NT-DEP-02-020` | DEP-02 | `MENU_ITEM` | استحقاقات الموردين | المالية | `suppliers/supplier_entitlements.php` |
| `NT-DEP-02-021` | DEP-02 | `MENU_ITEM` | الإقفال التعاقدي للوحدات التعاقدية | المالية | `suppliers/supplier_slot_closure.php` |
| `NT-DEP-02-022` | DEP-02 | `MENU_ITEM` | التسويات وكشف الحساب | المالية | `suppliers/settlements.php` |
| `NT-DEP-02-023` | DEP-02 | `MENU_ITEM` | طلبات الدفع وحالة الصرف | المالية | `suppliers/supplier_payment_requests.php` |
| `NT-DEP-02-024` | DEP-02 | `TAB_CHILD` | تخصيص الدفع على الإقفالات | المالية | `suppliers/supplier_payment_allocation.php` |
| `NT-DEP-02-025` | DEP-02 | `MENU_ITEM` | أعمار الأرصدة والالتزامات | المالية | `suppliers/supplier_aging.php` |
| `NT-DEP-02-026` | DEP-02 | `MENU_ITEM` | المخالفات والجزاءات | الحوكمة | `suppliers/supplier_violations.php` |
| `NT-DEP-02-027` | DEP-02 | `MENU_ITEM` | تقييم المورد والأداء | الحوكمة | `suppliers/supplier_evaluation.php` |
| `NT-DEP-02-028` | DEP-02 | `MENU_ITEM` | الملاحق والتصفية والإغلاق | الحوكمة | `suppliers/supplier_contract_events.php` |
| `NT-DEP-02-029` | DEP-02 | `MENU_ITEM` | مصادر القدرة والتكامل | الحوكمة | `suppliers/supplier_capacity_integration.php` |
| `NT-DEP-02-030` | DEP-02 | `LANDING_PAGE` | لوحة إدارة الموردين | اللوحة — خارج الدورة | `suppliers/supplier_board.php` |
| `NT-DEP-02-031` | DEP-02 | `MENU_ITEM` | سجل المستندات والضمانات | القوائم المرجعية | `suppliers/supplier_docs_guarantees.php` |
| `NT-DEP-03-001` | DEP-03 | `MENU_ITEM` | سجل الممولين | التأسيس | `financing/financiers_registry.php` |
| `NT-DEP-03-002` | DEP-03 | `TAB_CHILD` | جهات اتصال الممولين والمفوض | التأسيس | `financing/fin_financier_contacts.php` |
| `NT-DEP-03-003` | DEP-03 | `TAB_CHILD` | وثائق التأهيل والعناية | التأسيس | `financing/fin_due_diligence.php` |
| `NT-DEP-03-004` | DEP-03 | `MENU_ITEM` | فرص واحتياجات التمويل | دورة التمويل والسداد | `financing/fin_needs.php` |
| `NT-DEP-03-005` | DEP-03 | `MENU_ITEM` | عروض التمويل والتفاوض | دورة التمويل والسداد | `financing/fin_offers.php` |
| `NT-DEP-03-006` | DEP-03 | `MENU_ITEM` | مراجعة ما قبل التعاقد | دورة التمويل والسداد | `financing/fin_precontract_review.php` |
| `NT-DEP-03-007` | DEP-03 | `MENU_ITEM` | سجل عقود التمويل | التعاقد | `financing/fin_contracts.php` |
| `NT-DEP-03-008` | DEP-03 | `TAB_CHILD` | بنود وشروط التمويل | التعاقد | `financing/fin_contract_terms.php` |
| `NT-DEP-03-009` | DEP-03 | `TAB_CHILD` | مصفوفة الالتزامات التمويلية | التعاقد | `financing/fin_covenants.php` |
| `NT-DEP-03-010` | DEP-03 | `MENU_ITEM` | عمليات التمويل | التعاقد | `financing/operation_profile.php` |
| `NT-DEP-03-011` | DEP-03 | `TAB_CHILD` | الأصول الممولة | الأصول الممولة | `equipments/fin_assets.php` |
| `NT-DEP-03-012` | DEP-03 | `TAB_CHILD` | حصص الملكية وحق الانتفاع | الأصول الممولة | `financing/owners_registry.php` |
| `NT-DEP-03-013` | DEP-03 | `TAB_CHILD` | جدول الأقساط والاستحقاقات | المالية | `financing/installments.php` |
| `NT-DEP-03-014` | DEP-03 | `MENU_ITEM` | استحقاقات الممول | المالية | `financing/fin_financier_dues.php` |
| `NT-DEP-03-015` | DEP-03 | `MENU_ITEM` | الإقفالات التعاقدية | المالية | `financing/fin_contract_close.php` |
| `NT-DEP-03-016` | DEP-03 | `MENU_ITEM` | الإقفالات الشهرية وكشف الحس | المالية | `financing/fin_monthly_close.php` |
| `NT-DEP-03-017` | DEP-03 | `MENU_ITEM` | أوامر الدفع والسداد الفعلي | المالية | `financing/fin_payment_orders.php` |
| `NT-DEP-03-018` | DEP-03 | `TAB_CHILD` | تخصيص السداد على الأقساط | المالية | `financing/fin_payment_allocation.php` |
| `NT-DEP-03-019` | DEP-03 | `MENU_ITEM` | توزيع السداد | المالية | `financing/cost_allocation.php` |
| `NT-DEP-03-020` | DEP-03 | `MENU_ITEM` | رصيد رأس المال والعائد | المالية | `financing/fin_capital_balance.php` |
| `NT-DEP-03-021` | DEP-03 | `MENU_ITEM` | تعديلات التمويل وإعادة الجدولة | الحوكمة | `financing/fin_changes.php` |
| `NT-DEP-03-022` | DEP-03 | `MENU_ITEM` | الانحرافات والمتأخرات | الحوكمة | `financing/deviations.php` |
| `NT-DEP-03-023` | DEP-03 | `MENU_ITEM` | انتقال الملكية والخروج | الأصول الممولة | `financing/asset_disposal.php` |
| `NT-DEP-03-024` | DEP-03 | `MENU_ITEM` | إقفال التمويل | الحوكمة | `financing/fin_final_close.php` |
| `NT-DEP-03-025` | DEP-03 | `LANDING_PAGE` | التقارير ولوحة المحفظة | اللوحة — خارج الدورة | `financing/fin_portfolio_board.php` |
| `NT-DEP-04-001` | DEP-04 | `MENU_ITEM` | مصادر القدرة | التعريف والسياسات | `fleet/power_sources.php` |
| `NT-DEP-04-002` | DEP-04 | `MENU_ITEM` | دليل الترميز والتصنيف | التعريف والسياسات | `fleet/classification_directory.php` |
| `NT-DEP-04-003` | DEP-04 | `MENU_ITEM` | طلب إدخال الأصل | تسجيل الأصل ودخوله | `fleet/asset_intake.php` |
| `NT-DEP-04-004` | DEP-04 | `DIRECT_ONLY` | التحقق من المصدر | تسجيل الأصل ودخوله | `fleet/asset_intake.php` |
| `NT-DEP-04-005` | DEP-04 | `DIRECT_ONLY` | أمر التفتيش | تسجيل الأصل ودخوله | `fleet/asset_intake.php` |
| `NT-DEP-04-006` | DEP-04 | `MENU_ITEM` | بطاقة التفتيش | تسجيل الأصل ودخوله | `fleet/inspection_card.php` |
| `NT-DEP-04-007` | DEP-04 | `MENU_ITEM` | كرت الأصل | تسجيل الأصل ودخوله | `fleet/asset_card.php` |
| `NT-DEP-04-008` | DEP-04 | `TAB_CHILD` | مستندات الأصل | تسجيل الأصل ودخوله | `fleet/asset_documents.php` |
| `NT-DEP-04-009` | DEP-04 | `TAB_CHILD` | حق الاستخدام التشغيلي | تسجيل الأصل ودخوله | `fleet/asset_use_rights.php` |
| `NT-DEP-04-010` | DEP-04 | `TAB_CHILD` | المكونات والملحقات | تسجيل الأصل ودخوله | `fleet/asset_components.php` |
| `NT-DEP-04-011` | DEP-04 | `DIRECT_ONLY` | التفعيل وإعادة الخدمة | إدخال الأصل للتشغيل | `fleet/asset_intake.php` |
| `NT-DEP-04-012` | DEP-04 | `MENU_ITEM` | التخصيص على الوحدات | إدخال الأصل للتشغيل | `fleet/asset_assignments.php` |
| `NT-DEP-04-013` | DEP-04 | `DIRECT_ONLY` | حركة الموقع والمشروع | الحركة والاستخدام | `fleet/asset_assignments.php` |
| `NT-DEP-04-014` | DEP-04 | `MENU_ITEM` | السجل اليومي للتشغيل | الحركة والاستخدام | `fleet/daily_operating_log.php` |
| `NT-DEP-04-015` | DEP-04 | `TAB_CHILD` | تاريخ المشغلين على المعدة | الحركة والاستخدام | `fleet/asset_full_history.php` |
| `NT-DEP-04-016` | DEP-04 | `MENU_ITEM` | الحالة الفنية | الرقابة الفنية والجاهزية | `fleet/technical_state.php` |
| `NT-DEP-04-017` | DEP-04 | `MENU_ITEM` | سجل تغير الحالة | الرقابة الفنية والجاهزية | `fleet/state_change_log.php` |
| `NT-DEP-04-018` | DEP-04 | `MENU_ITEM` | سجل الأعطال والتوقفات | الرقابة الفنية والجاهزية | `fleet/breakdown_log.php` |
| `NT-DEP-04-019` | DEP-04 | `MENU_ITEM` | الملخص التشغيلي الشهري | الرقابة الفنية والجاهزية | `fleet/asset_readiness.php` |
| `NT-DEP-04-020` | DEP-04 | `DIRECT_ONLY` | الجاهزية الشهرية | الرقابة الفنية والجاهزية | `fleet/asset_readiness.php` |
| `NT-DEP-04-021` | DEP-04 | `MENU_ITEM` | الخروج المؤقت | خروج الأصل من الخدمة | `fleet/asset_exit.php` |
| `NT-DEP-04-022` | DEP-04 | `DIRECT_ONLY` | الخروج الدائم | خروج الأصل من الخدمة | `fleet/asset_exit.php` |
| `NT-DEP-04-023` | DEP-04 | `MENU_ITEM` | سجل الاستثناءات | الاستثناءات والقرارات | `fleet/exception_register.php` |
| `NT-DEP-04-024` | DEP-04 | `MENU_ITEM` | ملاحظات المراجع الخارجي | الاستثناءات والقرارات | `fleet/external_auditor_notes.php` |
| `NT-DEP-04-025` | DEP-04 | `MENU_ITEM` | التضاربات بين المصادر | الاستثناءات والقرارات | `fleet/source_conflicts.php` |
| `NT-DEP-04-026` | DEP-04 | `MENU_ITEM` | النقاط غير المحسومة | الاستثناءات والقرارات | `fleet/open_points.php` |
| `NT-DEP-04-027` | DEP-04 | `MENU_ITEM` | حزمة القرارات الإدارية | الاستثناءات والقرارات | `fleet/management_decisions.php` |
| `NT-DEP-04-028` | DEP-04 | `MENU_ITEM` | تاريخ المعدة الكامل | التقارير والمؤشرات | `fleet/asset_full_history.php` |
| `NT-DEP-04-029` | DEP-04 | `LANDING_PAGE` | لوحة الأسطول | اللوحة — خارج الدورة | `fleet/fleet_board.php` |
| `NT-DEP-04-030` | DEP-04 | `MENU_ITEM` | مرجع ساعات التشغيل للإهلاك | التقارير والمؤشرات | `fleet/asset_hours_reference.php` |
| `NT-DEP-04-031` | DEP-04 | `MENU_ITEM` | مرجع العمر الإنتاجي بالساعات | القوائم المرجعية والمصالحة | `fleet/asset_life_reference.php` |
| `NT-DEP-04-032` | DEP-04 | `MENU_ITEM` | نطاقات حق الاستخدام | القوائم المرجعية والمصالحة | `fleet/use_right_ranges.php` |
| `NT-DEP-04-033` | DEP-04 | `UTILITY` | مصالحة مطابقة الأكواد | القوائم المرجعية والمصالحة | `fleet/code_reconciliation.php` |
| `NT-DEP-04-034` | DEP-04 | `UTILITY` | مصالحة ترقيم الأصول | القوائم المرجعية والمصالحة | `fleet/numbering_bridge.php` |
| `NT-DEP-04-035` | DEP-04 | `UTILITY` | مصالحة الأسطول بالملاك | القوائم المرجعية والمصالحة | `fleet/owner_reconciliation.php` |
| `NT-DEP-04-036` | DEP-04 | `MENU_ITEM` | مصالحة السجل بالتشغيل | القوائم المرجعية والمصالحة | `fleet/register_operation_recon.php` |
| `NT-DEP-04-037` | DEP-04 | `UTILITY` | مصالحة الأعيان الممولة | القوائم المرجعية والمصالحة | `fleet/financed_asset_recon.php` |
| `NT-DEP-05-001` | DEP-05 | `LANDING_PAGE` | لوحة المالية | اللوحة — خارج الدورة | `finance/cfo_daily_board_fin.php` |
| `NT-DEP-05-002` | DEP-05 | `MENU_ITEM` | دليل الحسابات | التأسيس والقوائم المرجعية | `finance/management_accounting_fin.php` |
| `NT-DEP-05-003` | DEP-05 | `MENU_ITEM` | مراكز التكلفة | التأسيس والقوائم المرجعية | `finance/acc_cost_centers.php` |
| `NT-DEP-05-004` | DEP-05 | `MENU_ITEM` | التقويم المحاسبي للفترات | التأسيس والقوائم المرجعية | `finance/periods_fin.php` |
| `NT-DEP-05-005` | DEP-05 | `MENU_ITEM` | الميزانية والتقدير | التأسيس والقوائم المرجعية | `finance/budget_master.php` |
| `NT-DEP-05-006` | DEP-05 | `MENU_ITEM` | أسعار الصرف | التأسيس والقوائم المرجعية | `finance/currencies_fin.php` |
| `NT-DEP-05-007` | DEP-05 | `MENU_ITEM` | فواتير العملاء | الذمم المدينة | `finance/ar_claim_invoice.php` |
| `NT-DEP-05-008` | DEP-05 | `TAB_CHILD` | بنود فاتورة العميل | الذمم المدينة | `finance/ar_claim_invoice.php` |
| `NT-DEP-05-009` | DEP-05 | `MENU_ITEM` | فواتير الموردين والمستحقات | الذمم الدائنة | `finance/dues_fin.php` |
| `NT-DEP-05-010` | DEP-05 | `TAB_CHILD` | بنود استحقاق المورد | الذمم الدائنة | `finance/dues_fin.php` |
| `NT-DEP-05-011` | DEP-05 | `MENU_ITEM` | القيود اليومية | القيد والدفتر | `finance/journal_form_fin.php` |
| `NT-DEP-05-012` | DEP-05 | `TAB_CHILD` | أسطر القيد المحاسبي | القيد والدفتر | `finance/journal_form_fin.php` |
| `NT-DEP-05-013` | DEP-05 | `MENU_ITEM` | تتبّع الأثر من الواقعة إلى القيد | القيد والدفتر | `finrequests/effect_map.php` |
| `NT-DEP-05-014` | DEP-05 | `MENU_ITEM` | ذمم العملاء وأعمارها | الذمم المدينة | `contracts/collections.php` |
| `NT-DEP-05-015` | DEP-05 | `MENU_ITEM` | الرقابة الائتمانية وحدود العملاء | الذمم المدينة | `finance/acc_credit_control.php` |
| `NT-DEP-05-016` | DEP-05 | `MENU_ITEM` | ذمم الموردين وأعمارها | الذمم الدائنة | `finance/supplier_statement_fin.php` |
| `NT-DEP-05-017` | DEP-05 | `MENU_ITEM` | الاستحقاقات والمقدَّمات والمخصصات | التسويات والمحاسبة العامة | `finance/acc_adjustments.php` |
| `NT-DEP-05-018` | DEP-05 | `MENU_ITEM` | الضرائب والقيمة المضافة | التسويات والمحاسبة العامة | `finance/tax_fin.php` |
| `NT-DEP-05-019` | DEP-05 | `MENU_ITEM` | محاسبة الأصول الثابتة والإهلاك | التسويات والمحاسبة العامة | `finance/depr_run.php` |
| `NT-DEP-05-020` | DEP-05 | `MENU_ITEM` | مطابقات الحسابات | التسويات والمحاسبة العامة | `finance/acc_reconciliations.php` |
| `NT-DEP-05-021` | DEP-05 | `MENU_ITEM` | ميزان المراجعة | الإقفال | `finance/acc_trial_balance.php` |
| `NT-DEP-05-022` | DEP-05 | `MENU_ITEM` | قائمة إقفال الفترة | الإقفال | `finance/acc_closing_checklist.php` |
| `NT-DEP-05-023` | DEP-05 | `DIRECT_ONLY` | إقفال الفترة المحاسبية | الإقفال | `finance/periods_fin.php` |
| `NT-DEP-05-024` | DEP-05 | `MENU_ITEM` | القوائم المالية | الإقفال | `finance/financial_statements_fin.php` |
| `NT-DEP-05-025` | DEP-05 | `MENU_ITEM` | حوكمة إعادة فتح الفترات | الإقفال | `finance/acc_reopen_governance.php` |
| `NT-DEP-06-001` | DEP-06 | `LANDING_PAGE` | لوحة الخزينة والسيولة | اللوحة — خارج الدورة | `finance/tre_liquidity_board.php` |
| `NT-DEP-06-002` | DEP-06 | `MENU_ITEM` | الحسابات البنكية والصناديق | التأسيس | `finance/tre_vessels.php` |
| `NT-DEP-06-003` | DEP-06 | `MENU_ITEM` | سجل المستفيدين والتحقق | التأسيس | `finance/tre_beneficiary.php` |
| `NT-DEP-06-004` | DEP-06 | `MENU_ITEM` | خطة السيولة والتدفق | التخطيط | `finance/cash_forecast_fin.php` |
| `NT-DEP-06-005` | DEP-06 | `MENU_ITEM` | التحصيلات الواردة | دورة القبض | `contracts/collections.php` |
| `NT-DEP-06-006` | DEP-06 | `MENU_ITEM` | سجل الأدوات المالية | دورة القبض | `finance/tre_instruments.php` |
| `NT-DEP-06-007` | DEP-06 | `TAB_CHILD` | تخصيص التحصيل على الفواتير | دورة القبض | `finance/tre_allocations.php` |
| `NT-DEP-06-008` | DEP-06 | `MENU_ITEM` | مدفوعات معتمدة بانتظار التنفيذ | دورة الصرف | `finance/tre_payment_queue.php` |
| `NT-DEP-06-009` | DEP-06 | `MENU_ITEM` | أمر الدفع والتنفيذ | دورة الصرف | `finance/tre_pay_batch.php` |
| `NT-DEP-06-010` | DEP-06 | `MENU_ITEM` | حركة الخزينة والصناديق | دورة الصرف | `finance/tre_cash_moves.php` |
| `NT-DEP-06-011` | DEP-06 | `MENU_ITEM` | التحويلات بين الحسابات | دورة الصرف | `finance/tre_transfers.php` |
| `NT-DEP-06-012` | DEP-06 | `MENU_ITEM` | تنفيذ عمليات الصرف الأجنبي | دورة الصرف | `finance/tre_fx_deals.php` |
| `NT-DEP-06-013` | DEP-06 | `MENU_ITEM` | المطابقة البنكية | الرقابة والإقفال الشهري | `finance/bank_reconciliation_fin.php` |
| `NT-DEP-06-014` | DEP-06 | `MENU_ITEM` | التسهيلات البنكية | الرقابة والإقفال الشهري | `finance/tre_facilities.php` |
| `NT-DEP-06-015` | DEP-06 | `MENU_ITEM` | خطابات الضمان والاعتمادات المستندية | الرقابة والإقفال الشهري | `finance/tre_guarantees.php` |
| `NT-DEP-06-016` | DEP-06 | `TAB_CHILD` | بنود فروق المطابقة البنكية | الرقابة والإقفال الشهري | `finance/bank_reconciliation_fin.php` |
| `NT-DEP-06-017` | DEP-06 | `MENU_ITEM` | عهد النثرية وتسويتها | الرقابة والإقفال الشهري | `finance/tre_petty_cash.php` |
| `NT-DEP-06-018` | DEP-06 | `MENU_ITEM` | الجرد النقدي للخزائن | الرقابة والإقفال الشهري | `finance/tre_cash_count.php` |
| `NT-DEP-07-001` | DEP-07 | `LANDING_PAGE` | لوحة الموارد البشرية | اللوحة — خارج الدورة | `employees/hr_board.php` |
| `NT-DEP-07-002` | DEP-07 | `MENU_ITEM` | الهيكل الوظيفي والمناصب | التوظيف والتعاقد | `employees/job_titles.php` |
| `NT-DEP-07-003` | DEP-07 | `MENU_ITEM` | خطة القوى العاملة | التوظيف والتعاقد | `employees/hr_headcount_plan.php` |
| `NT-DEP-07-004` | DEP-07 | `MENU_ITEM` | التوظيف من الشاغر إلى المباشرة | التوظيف والتعاقد | `workforce/recruitment_pipeline.php` |
| `NT-DEP-07-005` | DEP-07 | `TAB_CHILD` | طلبات الترشح | التوظيف والتعاقد | `workforce/rec_applications.php` |
| `NT-DEP-07-006` | DEP-07 | `TAB_CHILD` | مراحل التوظيف | التوظيف والتعاقد | `workforce/rec_stages.php` |
| `NT-DEP-07-007` | DEP-07 | `MENU_ITEM` | سجل الموظفين | التوظيف والتعاقد | `employees/employees.php` |
| `NT-DEP-07-008` | DEP-07 | `TAB_CHILD` | مستندات الموظف | التوظيف والتعاقد | `employees/hr_employee_documents.php` |
| `NT-DEP-07-009` | DEP-07 | `MENU_ITEM` | التهيئة والمباشرة | التوظيف والتعاقد | `employees/hr_onboarding.php` |
| `NT-DEP-07-010` | DEP-07 | `TAB_CHILD` | عقود الموظفين | التوظيف والتعاقد | `employees/employee_contracts.php` |
| `NT-DEP-07-011` | DEP-07 | `MENU_ITEM` | عقود المشاريع المؤقتة | التوظيف والتعاقد | `workforce/project_contracts.php` |
| `NT-DEP-07-012` | DEP-07 | `MENU_ITEM` | الحركات الوظيفية | التوظيف والتعاقد | `employees/hr_job_movements.php` |
| `NT-DEP-07-013` | DEP-07 | `MENU_ITEM` | الحضور والانصراف | الدوام والإجازات | `operations/attendance.php` |
| `NT-DEP-07-014` | DEP-07 | `MENU_ITEM` | الإجازات والغياب | الدوام والإجازات | `workforce/worker_leave_absence.php` |
| `NT-DEP-07-015` | DEP-07 | `MENU_ITEM` | التدريب والكفاءة | الدوام والإجازات | `employees/hr_training.php` |
| `NT-DEP-07-016` | DEP-07 | `MENU_ITEM` | تقييم الأداء الوظيفي | الدوام والإجازات | `employees/hr_performance.php` |
| `NT-DEP-07-017` | DEP-07 | `MENU_ITEM` | القضايا التأديبية والتحقيق | الدوام والإجازات | `employees/hr_disciplinary.php` |
| `NT-DEP-07-018` | DEP-07 | `MENU_ITEM` | خصومات المسيّر | الدوام والإجازات | `workforce/deductions.php` |
| `NT-DEP-07-019` | DEP-07 | `MENU_ITEM` | المزايا والتأمينات | استحقاق الأجور وصرفها | `employees/hr_benefits.php` |
| `NT-DEP-07-020` | DEP-07 | `MENU_ITEM` | السلف والقروض | استحقاق الأجور وصرفها | `workforce/employee_advances.php` |
| `NT-DEP-07-021` | DEP-07 | `MENU_ITEM` | مسيّر الرواتب | استحقاق الأجور وصرفها | `workforce/payroll_runs.php` |
| `NT-DEP-07-022` | DEP-07 | `TAB_CHILD` | أسطر مسيّر الرواتب | استحقاق الأجور وصرفها | `workforce/payroll_lines.php` |
| `NT-DEP-07-023` | DEP-07 | `MENU_ITEM` | التصفية النهائية | استحقاق الأجور وصرفها | `workforce/final_settlement.php` |
| `NT-DEP-07-024` | DEP-07 | `MENU_ITEM` | تقرير القوى العاملة | التقارير | `workforce/hr_workforce_report.php` |
| `NT-DEP-08-001` | DEP-08 | `LANDING_PAGE` | لوحة الحوكمة والالتزام | اللوحة — خارج الدورة | `governance/gov_board.php` |
| `NT-DEP-08-002` | DEP-08 | `MENU_ITEM` | سجل الشركات والكيانات | الكيانات والتفويض بالتوقيع | `governance/entities_registry.php` |
| `NT-DEP-08-003` | DEP-08 | `MENU_ITEM` | سجل السياسات | الكيانات والتفويض بالتوقيع | `governance/policies.php` |
| `NT-DEP-08-004` | DEP-08 | `MENU_ITEM` | سجل الالتزامات التنظيمية | الكيانات والتفويض بالتوقيع | `governance/obligations.php` |
| `NT-DEP-08-005` | DEP-08 | `MENU_ITEM` | تقويم الامتثال | الكيانات والتفويض بالتوقيع | `governance/compliance_calendar.php` |
| `NT-DEP-08-006` | DEP-08 | `MENU_ITEM` | التفويض بالتوقيع | الكيانات والتفويض بالتوقيع | `governance/signing_authority.php` |
| `NT-DEP-08-007` | DEP-08 | `MENU_ITEM` | التراخيص والكفالات | المستندات والالتزام النظامي | `governance/licenses_guarantees.php` |
| `NT-DEP-08-008` | DEP-08 | `MENU_ITEM` | التقديمات النظامية | المستندات والالتزام النظامي | `governance/regulatory_filings.php` |
| `NT-DEP-08-009` | DEP-08 | `MENU_ITEM` | الأدوار وقوالب صلاحياتها | الأدوار والصلاحيات | `settings/roles.php` |
| `NT-DEP-08-010` | DEP-08 | `MENU_ITEM` | تضارب المصالح | الأدوار والصلاحيات | `governance/conflict_disclosures.php` |
| `NT-DEP-08-011` | DEP-08 | `MENU_ITEM` | الأطراف ذات العلاقة | الأدوار والصلاحيات | `governance/related_parties.php` |
| `NT-DEP-08-012` | DEP-08 | `MENU_ITEM` | الهدايا والضيافة | الأدوار والصلاحيات | `governance/gifts_hospitality.php` |
| `NT-DEP-08-013` | DEP-08 | `MENU_ITEM` | إقرارات مدونة السلوك | الأدوار والصلاحيات | `governance/conduct_acknowledgements.php` |
| `NT-DEP-08-014` | DEP-08 | `MENU_ITEM` | تصنيف قواعد المنع | الأدوار والصلاحيات | `governance/guards.php` |
| `NT-DEP-08-015` | DEP-08 | `MENU_ITEM` | سجل المحاولات الممنوعة | الاستثناء والرقابة | `governance/guard_denials.php` |
| `NT-DEP-08-016` | DEP-08 | `MENU_ITEM` | فصل الواجبات المتعارضة | الأدوار والصلاحيات | `governance/sod_conflicts.php` |
| `NT-DEP-08-017` | DEP-08 | `MENU_ITEM` | صلاحية الطوارئ اللحظية | الأدوار والصلاحيات | `governance/break_glass.php` |
| `NT-DEP-08-018` | DEP-08 | `MENU_ITEM` | سلاليم الاعتماد وتعريفها | الاستثناء والرقابة | `governance/approval_ladders.php` |
| `NT-DEP-08-019` | DEP-08 | `MENU_ITEM` | سياسات الحقول الحساسة | الأدوار والصلاحيات | `governance/sensitive_fields.php` |
| `NT-DEP-08-020` | DEP-08 | `MENU_ITEM` | مراقبة تدفق الأحداث ومعالجتها | الأنظمة والقوائم المرجعية | `governance/bus_board.php` |
| `NT-DEP-08-021` | DEP-08 | `MENU_ITEM` | طلبات الاستثناء | الاستثناء والرقابة | `governance/exceptions.php` |
| `NT-DEP-08-022` | DEP-08 | `MENU_ITEM` | بلاغات النزاهة المحمية | الاستثناء والرقابة | `governance/integrity_reports.php` |
| `NT-DEP-08-023` | DEP-08 | `MENU_ITEM` | التحقيقات | الاستثناء والرقابة | `governance/investigations.php` |
| `NT-DEP-08-024` | DEP-08 | `MENU_ITEM` | سجل الإخلالات | الاستثناء والرقابة | `governance/breaches.php` |
| `NT-DEP-08-025` | DEP-08 | `MENU_ITEM` | الإجراءات التصحيحية | الاستثناء والرقابة | `governance/corrective_actions.php` |
| `NT-DEP-08-026` | DEP-08 | `MENU_ITEM` | متابعة نتائج المراجعة | الاستثناء والرقابة | `governance/audit_followup.php` |
| `NT-DEP-08-027` | DEP-08 | `MENU_ITEM` | سجل أنواع المستندات | الأنظمة والقوائم المرجعية | `governance/doc_types.php` |
| `NT-DEP-08-028` | DEP-08 | `MENU_ITEM` | تفسير مصدر الصلاحية | الأدوار والصلاحيات | `governance/perm_explain.php` |
| `NT-DEP-08-029` | DEP-08 | `MENU_ITEM` | أنماط تفعيل المزايا | الأنظمة والقوائم المرجعية | `governance/activation_patterns.php` |
| `NT-DEP-08-030` | DEP-08 | `MENU_ITEM` | اللجان وحوكمة الاجتماعات | الأنظمة والقوائم المرجعية | `governance/committees.php` |
| `NT-DEP-08-031` | DEP-08 | `MENU_ITEM` | سجل أنواع الطلبات والتوجيه | الأنظمة والقوائم المرجعية | `governance/doc_types.php` |
| `NT-DEP-08-032` | DEP-08 | `MENU_ITEM` | تمرين الاستعادة ومحضره | الأنظمة والقوائم المرجعية | `governance/dr_restore.php` |
| `NT-DEP-09-001` | DEP-09 | `LANDING_PAGE` | لوحة المخاطر المؤسسية | اللوحة — خارج الدورة | `risk/risk_board.php` |
| `NT-DEP-09-002` | DEP-09 | `MENU_ITEM` | تصنيف المخاطر | التأسيس والقوائم المرجعية | `risk/risk_taxonomy.php` |
| `NT-DEP-09-003` | DEP-09 | `MENU_ITEM` | سجل المخاطر المؤسسي | سجل المخاطر وتقييمها | `risk/risk_register.php` |
| `NT-DEP-09-004` | DEP-09 | `TAB_CHILD` | أحداث المخاطر والخسائر | سجل المخاطر وتقييمها | `risk/risk_events.php` |
| `NT-DEP-09-005` | DEP-09 | `TAB_CHILD` | تقييم المخاطر | سجل المخاطر وتقييمها | `risk/risk_assessment.php` |
| `NT-DEP-09-006` | DEP-09 | `TAB_CHILD` | الضوابط الرقابية | سجل المخاطر وتقييمها | `risk/risk_controls.php` |
| `NT-DEP-09-007` | DEP-09 | `MENU_ITEM` | خطط معالجة المخاطر | معالجة المخاطر ومراقبتها | `risk/risk_treatments.php` |
| `NT-DEP-09-008` | DEP-09 | `TAB_CHILD` | مؤشرات المخاطر الرئيسة | معالجة المخاطر ومراقبتها | `risk/risk_kris.php` |
| `NT-DEP-09-009` | DEP-09 | `MENU_ITEM` | قبول المخاطر | قرار المخاطرة وإغلاقها | `risk/risk_acceptance.php` |
| `NT-DEP-09-010` | DEP-09 | `MENU_ITEM` | تصعيدات المخاطر | قرار المخاطرة وإغلاقها | `risk/risk_escalations.php` |
| `NT-DEP-09-011` | DEP-09 | `MENU_ITEM` | المراجعة الدورية وإعادة التقييم | قرار المخاطرة وإغلاقها | `risk/risk_reviews.php` |
| `NT-DEP-09-012` | DEP-09 | `MENU_ITEM` | سجل الإغلاق والأدلة | قرار المخاطرة وإغلاقها | `risk/risk_closure.php` |
| `NT-DEP-09-013` | DEP-09 | `MENU_ITEM` | تقارير المخاطر الدورية | التقارير | `risk/risk_reports.php` |
| `NT-DEP-10-001` | DEP-10 | `LANDING_PAGE` | لوحة مركز البلاغات | اللوحة — خارج الدورة | `tickets/gov_dept_crp.php` |
| `NT-DEP-10-002` | DEP-10 | `MENU_ITEM` | صندوق بلاغات الإدارة | سجلات البلاغ التابعة | `Tickets/tickets_list.php` |
| `NT-DEP-10-003` | DEP-10 | `LANDING_PAGE` | مصفوفة مهل المعالجة للبلاغات | اللوحة — خارج الدورة | `Tickets/ticket_sla_config.php` |
| `NT-DEP-10-004` | DEP-10 | `MENU_ITEM` | الإبلاغ السياقي من داخل الشاشة | دورة البلاغ | `Tickets/ticket_contextual_open.php` |
| `NT-DEP-10-005` | DEP-10 | `MENU_ITEM` | أنواع محل البلاغ المعتمدة | دورة البلاغ | `tickets/tkt_subject_types.php` |
| `NT-DEP-10-006` | DEP-10 | `MENU_ITEM` | تسجيل البلاغ | دورة البلاغ | `tickets/ticket_form.php` |
| `NT-DEP-10-007` | DEP-10 | `TAB_CHILD` | تاريخ توجيه البلاغ | سجلات البلاغ التابعة | `tickets/tkt_routing.php` |
| `NT-DEP-10-008` | DEP-10 | `TAB_CHILD` | تاريخ إسناد البلاغ | سجلات البلاغ التابعة | `tickets/tkt_assignment.php` |
| `NT-DEP-10-009` | DEP-10 | `TAB_CHILD` | إجراءات معالجة البلاغ | سجلات البلاغ التابعة | `tickets/tkt_resolution_actions.php` |
| `NT-DEP-10-010` | DEP-10 | `TAB_CHILD` | مراسلات البلاغ | سجلات البلاغ التابعة | `tickets/tkt_communications.php` |
| `NT-DEP-10-011` | DEP-10 | `TAB_CHILD` | تصعيد البلاغ | سجلات البلاغ التابعة | `tickets/tkt_escalation.php` |
| `NT-DEP-10-012` | DEP-10 | `TAB_CHILD` | إعادة فتح البلاغ | سجلات البلاغ التابعة | `tickets/tkt_reopen.php` |
| `NT-DEP-10-013` | DEP-10 | `MENU_ITEM` | التحقق من المعالجة وإغلاق البلاغ | دورة البلاغ | `tickets/tkt_verification.php` |
| `NT-DEP-11-001` | DEP-11 | `LANDING_PAGE` | غرفة العمليات | اللوحة — خارج الدورة | `operations/ops_room.php` |
| `NT-DEP-11-002` | DEP-11 | `MENU_ITEM` | الخطة الموسمية ومعاملاتها | التخطيط والتوزيع الشهري | `operations/ops_seasonal_factors.php` |
| `NT-DEP-11-003` | DEP-11 | `MENU_ITEM` | الخطة الشهرية للتشغيل | التخطيط والتوزيع الشهري | `operations/monthly_plan.php` |
| `NT-DEP-11-004` | DEP-11 | `MENU_ITEM` | احتياج الغد وتوزيع الموارد | التخطيط والتوزيع الشهري | `operations/ops_tomorrow_dispatch.php` |
| `NT-DEP-11-005` | DEP-11 | `MENU_ITEM` | طلب وقرار حركة الموارد | التخطيط والتوزيع الشهري | `operations/ops_resource_move_orders.php` |
| `NT-DEP-11-006` | DEP-11 | `MENU_ITEM` | سجل ساعات التشغيل اليومي | اليوم التشغيلي | `timesheet/timesheet.php` |
| `NT-DEP-11-007` | DEP-11 | `TAB_CHILD` | توزيع زمن الوردية وحالاته | اليوم التشغيلي | `operations/ops_shift_time_states.php` |
| `NT-DEP-11-008` | DEP-11 | `MENU_ITEM` | اعتماد الوحدات | اعتماد الوحدات ومطابقتها | `approvals/hours_approval.php` |
| `NT-DEP-11-009` | DEP-11 | `MENU_ITEM` | قرارات التوقف | انحرافات التشغيل واستثناءاته | `operations/ops_stop_decisions.php` |
| `NT-DEP-11-010` | DEP-11 | `MENU_ITEM` | سجل الأحداث التشغيلية | انحرافات التشغيل واستثناءاته | `workforce/worker_worklog.php` |
| `NT-DEP-11-011` | DEP-11 | `MENU_ITEM` | تقرير الانحراف والتصعيد | الإقفال الشهري والتقارير | `operations/ops_deviation_escalation.php` |
| `NT-DEP-11-012` | DEP-11 | `MENU_ITEM` | الإقفال الشهري للتشغيل | الإقفال الشهري والتقارير | `operations/monthly_close.php` |
| `NT-DEP-12-001` | DEP-12 | `LANDING_PAGE` | لوحة الموقع | اللوحة — خارج الدورة | `operations/site_board.php` |
| `NT-DEP-12-002` | DEP-12 | `MENU_ITEM` | سجل المواقع وحدودها | التأسيس | `operations/site_register.php` |
| `NT-DEP-12-003` | DEP-12 | `MENU_ITEM` | تجهيز الموقع والجاهزية | التأسيس | `operations/site_readiness_items.php` |
| `NT-DEP-12-004` | DEP-12 | `MENU_ITEM` | أذون دخول وخروج المعدات والمشغّلين | التأسيس | `operations/site_gate_equip.php` |
| `NT-DEP-12-005` | DEP-12 | `MENU_ITEM` | فتح يوم الموقع | فتح اليوم ومباشرته | `operations/site_day_open.php` |
| `NT-DEP-12-006` | DEP-12 | `TAB_CHILD` | ورديات اليوم | التنفيذ الميداني اليومي | `operations/site_day_shifts.php` |
| `NT-DEP-12-007` | DEP-12 | `MENU_ITEM` | تسجيل وحدات اليوم | فتح اليوم ومباشرته | `operations/site_day_units.php` |
| `NT-DEP-12-008` | DEP-12 | `MENU_ITEM` | محضر اعتماد الموقع | اعتماد الموقع | `operations/site_day_approval.php` |
| `NT-DEP-12-009` | DEP-12 | `MENU_ITEM` | محضر تسليم واستلام الورديات | اعتماد الموقع | `operations/site_shift_handover.php` |
| `NT-DEP-12-010` | DEP-12 | `MENU_ITEM` | طلبات تغيير الحالة ومعالجة المتعثر | الطلبات والصرف الميداني | `operations/site_state_requests.php` |
| `NT-DEP-12-011` | DEP-12 | `TAB_CHILD` | بنود طلب الموقع | الطلبات والصرف الميداني | `operations/site_request_items.php` |
| `NT-DEP-12-012` | DEP-12 | `MENU_ITEM` | طلبات الموقع للصرف والاستلام | الطلبات والصرف الميداني | `operations/site_supply_requests.php` |
| `NT-DEP-12-013` | DEP-12 | `TAB_CHILD` | دفعات استلام طلب الموقع | الطلبات والصرف الميداني | `operations/site_request_batches.php` |
| `NT-DEP-12-014` | DEP-12 | `MENU_ITEM` | قرار الاستعداد أو التعطل | التنفيذ الميداني اليومي | `operations/site_stop_decision.php` |
| `NT-DEP-12-015` | DEP-12 | `MENU_ITEM` | صرف المشتريات الميدانية من العهدة | الطلبات والصرف الميداني | `operations/site_field_expense.php` |
| `NT-DEP-12-016` | DEP-12 | `MENU_ITEM` | تقرير إقفال يوم الموقع | إقفال اليوم والسجلات | `operations/site_day_close_report.php` |
| `NT-DEP-12-017` | DEP-12 | `MENU_ITEM` | الإيقاف المؤقت للموقع | إقفال اليوم والسجلات | `operations/site_suspension.php` |
| `NT-DEP-12-018` | DEP-12 | `MENU_ITEM` | إغلاق الموقع وتسريحه | إقفال اليوم والسجلات | `operations/site_closure_items.php` |
| `NT-DEP-12-019` | DEP-12 | `MENU_ITEM` | سجلات الموقع المرجعية | إقفال اليوم والسجلات | `operations/site_reference_registry.php` |
| `NT-DEP-13-001` | DEP-13 | `LANDING_PAGE` | لوحة القوى التشغيلية | اللوحة — خارج الدورة | `workforce/wf_board.php` |
| `NT-DEP-13-002` | DEP-13 | `MENU_ITEM` | احتياج المشروع من القوى | احتياج القوى وتغطيته | `workforce/workforce_requirement.php` |
| `NT-DEP-13-003` | DEP-13 | `MENU_ITEM` | المطلوب مقابل المتوفر | احتياج القوى وتغطيته | `workforce/wf_coverage.php` |
| `NT-DEP-13-004` | DEP-13 | `MENU_ITEM` | الترشيح والاختيار للتغطية | احتياج القوى وتغطيته | `workforce/wf_nomination.php` |
| `NT-DEP-13-005` | DEP-13 | `MENU_ITEM` | عقود المشاريع المرجعية | احتياج القوى وتغطيته | `workforce/wf_project_contracts_ref.php` |
| `NT-DEP-13-006` | DEP-13 | `MENU_ITEM` | تصنيف الفئات التشغيلية | تأهيل القوى وجاهزيتها | `workforce/wf_taxonomy.php` |
| `NT-DEP-13-007` | DEP-13 | `MENU_ITEM` | سجل الأفراد التشغيليين | تأهيل القوى وجاهزيتها | `workforce/wf_person_register.php` |
| `NT-DEP-13-008` | DEP-13 | `TAB_CHILD` | التأهيل والشهادات | تأهيل القوى وجاهزيتها | `workforce/wf_qualifications.php` |
| `NT-DEP-13-009` | DEP-13 | `MENU_ITEM` | مصفوفة التأهيل والجاهزية | تأهيل القوى وجاهزيتها | `workforce/wf_qualification_matrix.php` |
| `NT-DEP-13-010` | DEP-13 | `MENU_ITEM` | التخصيص للمشروع | تخصيص القوى وحركتها | `workforce/wf_project_allocation.php` |
| `NT-DEP-13-011` | DEP-13 | `MENU_ITEM` | تخصيص المعدة والوردية | تخصيص القوى وحركتها | `workforce/wf_equipment_shift_assignment.php` |
| `NT-DEP-13-012` | DEP-13 | `MENU_ITEM` | الحركة والتواجد اليومي | تخصيص القوى وحركتها | `workforce/wf_daily_presence.php` |
| `NT-DEP-13-013` | DEP-13 | `MENU_ITEM` | التناوب والبدلاء | تخصيص القوى وحركتها | `workforce/wf_rotation_backup.php` |
| `NT-DEP-13-014` | DEP-13 | `MENU_ITEM` | أداء الأفراد التشغيلي | أداء القوى وتسويتها | `workforce/wf_performance.php` |
| `NT-DEP-13-015` | DEP-13 | `MENU_ITEM` | التسوية ونهاية التخصيص | أداء القوى وتسويتها | `workforce/wf_settlement.php` |
| `NT-DEP-13-016` | DEP-13 | `MENU_ITEM` | وحدات السكن والإعاشة | أداء القوى وتسويتها | `workforce/housing_units.php` |
| `NT-DEP-13-017` | DEP-13 | `MENU_ITEM` | وقائع الميدان والإحالة التأديبية | أداء القوى وتسويتها | `workforce/wf_field_incidents.php` |
| `NT-DEP-14-001` | DEP-14 | `LANDING_PAGE` | لوحة الصيانة والجاهزية | اللوحة — خارج الدورة | `maintenance/dashboard_mnt.php` |
| `NT-DEP-14-002` | DEP-14 | `MENU_ITEM` | شجرة الأعطال المرجعية | التأسيس والقوائم المرجعية | `maintenance/fault_tree.php` |
| `NT-DEP-14-003` | DEP-14 | `MENU_ITEM` | الورش والفنيون | التأسيس | `maintenance/workshop.php` |
| `NT-DEP-14-004` | DEP-14 | `MENU_ITEM` | البلاغ الفني واستقبال العطل | دورة العطل | `maintenance/breakdown_intake.php` |
| `NT-DEP-14-005` | DEP-14 | `MENU_ITEM` | أوامر التفتيش الواردة من الأسطول | دورة العطل | `maintenance/fleet_inspection_orders.php` |
| `NT-DEP-14-006` | DEP-14 | `MENU_ITEM` | تقطيع مسؤولية التوقف | دورة العطل | `maintenance/downtime_segments.php` |
| `NT-DEP-14-007` | DEP-14 | `MENU_ITEM` | طلب الفحص والتشخيص | دورة العطل | `maintenance/diagnosis_requests.php` |
| `NT-DEP-14-008` | DEP-14 | `MENU_ITEM` | أمر العمل | دورة العطل | `maintenance/work_orders.php` |
| `NT-DEP-14-009` | DEP-14 | `TAB_CHILD` | عمالة أمر العمل | دورة العطل | `maintenance/work_order_labor.php` |
| `NT-DEP-14-010` | DEP-14 | `TAB_CHILD` | بنود أمر العمل | دورة العطل | `maintenance/work_orders.php` |
| `NT-DEP-14-011` | DEP-14 | `MENU_ITEM` | طلب صرف القطع لأمر العمل | دورة العطل | `Maintenance/part_requests.php` |
| `NT-DEP-14-012` | DEP-14 | `MENU_ITEM` | الإصلاح الخارجي ومطالبات الضمان | دورة العطل | `maintenance/external_repairs.php` |
| `NT-DEP-14-013` | DEP-14 | `MENU_ITEM` | الخطة الوقائية بالساعات | الصيانة الوقائية | `Maintenance/preventive_plans.php` |
| `NT-DEP-14-014` | DEP-14 | `MENU_ITEM` | العناية اليومية والتشحيم | الصيانة الوقائية | `maintenance/daily_care.php` |
| `NT-DEP-14-015` | DEP-14 | `MENU_ITEM` | الإقفال وشهادة إعادة الخدمة | إقفال الصيانة وجاهزية الأصل | `maintenance/return_to_service_cert.php` |
| `NT-DEP-14-016` | DEP-14 | `MENU_ITEM` | سجل إعادة الإصلاح | إقفال الصيانة وجاهزية الأصل | `maintenance/repeat_repairs.php` |
| `NT-DEP-14-017` | DEP-14 | `MENU_ITEM` | مؤشرات الصيانة الدورية | التقارير | `maintenance/mnt_kpis.php` |
| `NT-DEP-15-001` | DEP-15 | `LANDING_PAGE` | لوحة النقل والترحيل | اللوحة — خارج الدورة | `transport/transfer_dashboard.php` |
| `NT-DEP-15-002` | DEP-15 | `MENU_ITEM` | طلب الترحيل | دورة الترحيل | `transport/transfer_requests.php` |
| `NT-DEP-15-003` | DEP-15 | `MENU_ITEM` | اللوابد والمركبات الناقلة | التأسيس | `transport/transfer_fleet.php` |
| `NT-DEP-15-004` | DEP-15 | `MENU_ITEM` | أمر الترحيل | دورة الترحيل | `transport/transfer_order_form.php` |
| `NT-DEP-15-005` | DEP-15 | `TAB_CHILD` | تصاريح المسار والحمولة | دورة الترحيل | `transport/transfer_permits.php` |
| `NT-DEP-15-006` | DEP-15 | `MENU_ITEM` | تجهيز المغادرة والتسليم الأصلي | دورة الترحيل | `transport/transfer_origin_handover.php` |
| `NT-DEP-15-007` | DEP-15 | `TAB_CHILD` | مراحل الرحلة | دورة الترحيل | `transport/transfer_trip_legs.php` |
| `NT-DEP-15-008` | DEP-15 | `MENU_ITEM` | تتبع الرحلة وأحداثها | دورة الترحيل | `transport/transfer_in_transit.php` |
| `NT-DEP-15-009` | DEP-15 | `MENU_ITEM` | محضر الاستلام وقراءة العدّاد | دورة الترحيل | `transport/transfer_arrival.php` |
| `NT-DEP-15-010` | DEP-15 | `MENU_ITEM` | مطالبات التلف والحوادث | دورة الترحيل | `transport/transfer_damage_claims.php` |
| `NT-DEP-15-011` | DEP-15 | `TAB_CHILD` | بنود تكلفة الرحلة | إقفال الترحيل وتكلفته | `transport/transfer_close_cost.php` |
| `NT-DEP-15-012` | DEP-15 | `MENU_ITEM` | إقفال أمر الترحيل | إقفال الترحيل وتكلفته | `transport/transfer_closure.php` |
| `NT-DEP-15-013` | DEP-15 | `MENU_ITEM` | تقرير أوامر الترحيل | إقفال الترحيل وتكلفته | `transport/transfer_orders_report.php` |
| `NT-DEP-16-001` | DEP-16 | `LANDING_PAGE` | لوحة المشتريات | اللوحة — خارج الدورة | `procurement/dashboard_proc.php` |
| `NT-DEP-16-002` | DEP-16 | `MENU_ITEM` | طلبات الشراء | طلبات الشراء وتجميعها | `procurement/requests_proc.php` |
| `NT-DEP-16-003` | DEP-16 | `TAB_CHILD` | بنود طلب الشراء | طلبات الشراء وتجميعها | `procurement/requests_proc.php` |
| `NT-DEP-16-004` | DEP-16 | `MENU_ITEM` | تجميع الطلبات وخطة الشراء | طلبات الشراء وتجميعها | `procurement/proc_packages.php` |
| `NT-DEP-16-005` | DEP-16 | `TAB_CHILD` | أعضاء حزمة التجميع | طلبات الشراء وتجميعها | `procurement/proc_packages.php` |
| `NT-DEP-16-006` | DEP-16 | `MENU_ITEM` | طلب العروض | عروض الموردين والترسية | `procurement/rfq_compare_award.php` |
| `NT-DEP-16-007` | DEP-16 | `TAB_CHILD` | دعوات الموردين للعروض | عروض الموردين والترسية | `procurement/proc_rfq.php` |
| `NT-DEP-16-008` | DEP-16 | `TAB_CHILD` | عروض الموردين المستلمة | عروض الموردين والترسية | `procurement/proc_offers.php` |
| `NT-DEP-16-009` | DEP-16 | `TAB_CHILD` | بنود عروض الموردين | عروض الموردين والترسية | `procurement/proc_offers.php` |
| `NT-DEP-16-010` | DEP-16 | `MENU_ITEM` | محضر المقارنة والترسية | عروض الموردين والترسية | `procurement/proc_award_minutes.php` |
| `NT-DEP-16-011` | DEP-16 | `MENU_ITEM` | أوامر الشراء | أمر الشراء والتوريد | `procurement/orders_proc.php` |
| `NT-DEP-16-012` | DEP-16 | `TAB_CHILD` | بنود أمر الشراء | أمر الشراء والتوريد | `procurement/orders_proc.php` |
| `NT-DEP-16-013` | DEP-16 | `MENU_ITEM` | استثناءات الشراء وتعديلات الأوامر | أمر الشراء والتوريد | `procurement/proc_po_amendments.php` |
| `NT-DEP-16-014` | DEP-16 | `MENU_ITEM` | متابعة التوريد والاستلام | أمر الشراء والتوريد | `procurement/proc_delivery_track.php` |
| `NT-DEP-16-015` | DEP-16 | `MENU_ITEM` | مطابقة الفاتورة الثلاثية | المطابقة الثلاثية والإقفال | `procurement/po_match.php` |
| `NT-DEP-16-016` | DEP-16 | `MENU_ITEM` | تقييم أداء التوريد | المطابقة الثلاثية والإقفال | `procurement/proc_supplier_eval.php` |
| `NT-DEP-17-001` | DEP-17 | `LANDING_PAGE` | لوحة المخازن | اللوحة — خارج الدورة | `procurement/warehouse_board.php` |
| `NT-DEP-17-002` | DEP-17 | `MENU_ITEM` | سجل المخازن وأنواعها | التأسيس والقوائم المرجعية | `procurement/warehouses.php` |
| `NT-DEP-17-003` | DEP-17 | `TAB_CHILD` | إسناد أمناء المخازن | التأسيس والقوائم المرجعية | `procurement/wh_custodians.php` |
| `NT-DEP-17-004` | DEP-17 | `MENU_ITEM` | دليل الأصناف | التأسيس والقوائم المرجعية | `procurement/items_proc.php` |
| `NT-DEP-17-005` | DEP-17 | `MENU_ITEM` | سند الإدخال والفحص | دورة الاستلام | `procurement/wh_receipt.php` |
| `NT-DEP-17-006` | DEP-17 | `TAB_CHILD` | بنود سند الإدخال | دورة الاستلام | `procurement/wh_receipt.php` |
| `NT-DEP-17-007` | DEP-17 | `MENU_ITEM` | أرصدة المخزون بحالاتها | دورة الاستلام | `procurement/stock_proc.php` |
| `NT-DEP-17-008` | DEP-17 | `MENU_ITEM` | ضوابط المواد الخطرة والمتفجرات | دورة الاستلام | `procurement/wh_hazmat.php` |
| `NT-DEP-17-009` | DEP-17 | `MENU_ITEM` | طلبات الصرف الواردة | دورة الصرف | `procurement/wh_issue_requests.php` |
| `NT-DEP-17-010` | DEP-17 | `TAB_CHILD` | بنود طلب الصرف الوارد | دورة الصرف | `procurement/wh_issue_requests.php` |
| `NT-DEP-17-011` | DEP-17 | `MENU_ITEM` | سند الصرف | دورة الصرف | `procurement/issue_proc.php` |
| `NT-DEP-17-012` | DEP-17 | `TAB_CHILD` | بنود سند الصرف | دورة الصرف | `procurement/issue_proc.php` |
| `NT-DEP-17-013` | DEP-17 | `MENU_ITEM` | العهد والمرتجعات | دورة الصرف | `procurement/receipt_custody_proc.php` |
| `NT-DEP-17-014` | DEP-17 | `MENU_ITEM` | التحويل بين المخازن | دورة الصرف | `procurement/wh_transfer.php` |
| `NT-DEP-17-015` | DEP-17 | `TAB_CHILD` | بنود التحويل بين المخازن | دورة الصرف | `procurement/wh_transfer.php` |
| `NT-DEP-17-016` | DEP-17 | `MENU_ITEM` | الجرد ومعالجة الفروقات | الرقابة والإقفال الشهري | `procurement/wh_count.php` |
| `NT-DEP-17-017` | DEP-17 | `TAB_CHILD` | بنود الجرد والفروقات | الرقابة والإقفال الشهري | `procurement/wh_count.php` |
| `NT-DEP-17-018` | DEP-17 | `MENU_ITEM` | حدود الطلب وإعادة التزويد | الرقابة والإقفال الشهري | `procurement/reordering_proc.php` |
| `NT-DEP-17-019` | DEP-17 | `MENU_ITEM` | الإقفال الشهري للمخازن | الرقابة والإقفال الشهري | `procurement/wh_month_close.php` |
| `NT-EX-CEO-001` | EX-CEO | `MENU_ITEM` | لوحة القيادة التنفيذية | الرؤية الشاملة | `portal/exec_command_board.php` |
| `NT-EX-CEO-002` | EX-CEO | `MENU_ITEM` | الإدارات والمشروعات | الرؤية الشاملة | `portal/exec_org_projects.php` |
| `NT-EX-CEO-003` | EX-CEO | `MENU_ITEM` | التقرير اليومي التنفيذي | التقارير الدورية | `portal/exec_daily_report.php` |
| `NT-EX-CEO-004` | EX-CEO | `TAB_CHILD` | تفصيل توقفات اليوم | التقارير الدورية | `portal/exec_daily_stops.php` |
| `NT-EX-CEO-005` | EX-CEO | `TAB_CHILD` | انحرافات وقرارات اليوم | التقارير الدورية | `portal/exec_daily_deviations.php` |
| `NT-EX-CEO-006` | EX-CEO | `MENU_ITEM` | التقرير الأسبوعي التنفيذي | التقارير الدورية | `portal/exec_weekly_report.php` |
| `NT-EX-CEO-007` | EX-CEO | `MENU_ITEM` | التقرير الشهري التنفيذي | التقارير الدورية | `portal/exec_monthly_pack.php` |
| `NT-EX-CEO-008` | EX-CEO | `MENU_ITEM` | جميع الطلبات المرفوعة إليّ | صناديق القرار | `portal/ceo_unified_queue.php` |
| `NT-EX-CEO-009` | EX-CEO | `MENU_ITEM` | الاعتمادات المالية | صناديق القرار | `portal/exec_financial_approvals.php` |
| `NT-EX-CEO-010` | EX-CEO | `MENU_ITEM` | اعتمادات العقود والوثائق | صناديق القرار | `portal/exec_document_approvals.php` |
| `NT-EX-CEO-011` | EX-CEO | `TAB_CHILD` | ملاحظات مراجعة الوثيقة قبل التوقيع | صناديق القرار | `portal/exec_doc_review_notes.php` |
| `NT-EX-CEO-012` | EX-CEO | `MENU_ITEM` | سجل العقود الموحَّد | صناديق القرار | `portal/exec_contract_registry.php` |
| `NT-EX-CEO-013` | EX-CEO | `MENU_ITEM` | تجاوزات الخطوط الحمراء | صناديق القرار | `portal/exec_redline_breaches.php` |
| `NT-EX-CEO-014` | EX-CEO | `MENU_ITEM` | المسائل المحجوزة والسلطة المحجوزة | صناديق القرار | `portal/exec_reserved_matters.php` |
| `NT-EX-CEO-015` | EX-CEO | `MENU_ITEM` | الاستثناءات الحرجة | صناديق القرار | `portal/exec_critical_exceptions.php` |
| `NT-EX-CEO-016` | EX-CEO | `MENU_ITEM` | صندوق التأكيد المستقل — تقارير المراجعة الداخلية | صناديق القرار | `portal/ceo_assurance_box.php` |
| `NT-EX-CEO-017` | EX-CEO | `MENU_ITEM` | التصعيدات العليا | صناديق القرار | `portal/exec_escalations.php` |
| `NT-EX-CEO-018` | EX-CEO | `MENU_ITEM` | قيادة الأزمات والطوارئ | صناديق القرار | `portal/exec_crisis_command.php` |
| `NT-EX-CEO-019` | EX-CEO | `MENU_ITEM` | القرارات الاستراتيجية | القرار والحوكمة العليا | `portal/exec_strategic_decisions.php` |
| `NT-EX-CEO-020` | EX-CEO | `MENU_ITEM` | قرار فتح مشروع — ميثاق المشروع | القرار والحوكمة العليا | `portal/project_charter.php` |
| `NT-EX-CEO-021` | EX-CEO | `MENU_ITEM` | قرارات الهيكل التنظيمي | القرار والحوكمة العليا | `admin/org_structure.php` |
| `NT-EX-CEO-022` | EX-CEO | `TAB_CHILD` | التكليفات والإنابات المؤقتة | القرار والحوكمة العليا | `portal/exec_temp_assignments.php` |
| `NT-EX-CEO-023` | EX-CEO | `MENU_ITEM` | موافقات التعيين في المسميات القيادية | القرار والحوكمة العليا | `portal/exec_leadership_appointments.php` |
| `NT-EX-CEO-024` | EX-CEO | `MENU_ITEM` | اجتماعات الإدارة العليا | القرار والحوكمة العليا | `portal/exec_leadership_meetings.php` |
| `NT-EX-CEO-025` | EX-CEO | `TAB_CHILD` | قرارات الاجتماعات | القرار والحوكمة العليا | `portal/exec_meeting_decisions.php` |
| `NT-EX-CEO-026` | EX-CEO | `MENU_ITEM` | متابعة القرارات التنفيذية | القرار والحوكمة العليا | `portal/exec_actions_followup.php` |
| `NT-EX-DVP-001` | EX-DVP | `MENU_ITEM` | لوحة النائب | الرؤية بالنطاق | `Portal/vp_dashboard.php` |
| `NT-EX-DVP-002` | EX-DVP | `MENU_ITEM` | الإدارات — نطاقي والشركة | الرؤية بالنطاق | `portal/vp_departments.php` |
| `NT-EX-DVP-003` | EX-DVP | `MENU_ITEM` | التقرير اليومي للنائب | التقارير الدورية | `portal/vp_daily_report.php` |
| `NT-EX-DVP-004` | EX-DVP | `MENU_ITEM` | مراجعة الأداء الأسبوعية | التقارير الدورية | `portal/vp_weekly_report.php` |
| `NT-EX-DVP-005` | EX-DVP | `MENU_ITEM` | المراجعة الشهرية للنائب | التقارير الدورية | `Portal/vp_monthly_review.php` |
| `NT-EX-DVP-006` | EX-DVP | `MENU_ITEM` | صندوق اعتمادات النائب | صناديق القرار | `Portal/vp_approval_inbox.php` |
| `NT-EX-DVP-007` | EX-DVP | `PROJECTION` | الاعتمادات المالية حسب الصلاحية | صناديق القرار | `portal/exec_financial_approvals.php` |
| `NT-EX-DVP-008` | EX-DVP | `PROJECTION` | الاستثناءات والخطوط الحمراء ضمن صلاحيتي | صناديق القرار | `Portal/exec_redline_breaches.php` |
| `NT-EX-DVP-009` | EX-DVP | `PROJECTION` | التصعيدات داخل النطاق | صناديق القرار | `Portal/exec_escalations.php` |
| `NT-EX-DVP-010` | EX-DVP | `MENU_ITEM` | الإجراءات والقرارات المطلوبة مني | المتابعة | `portal/vp_pending_actions.php` |
| `NT-EX-DVP-011` | EX-DVP | `MENU_ITEM` | متابعة قرارات النائب | المتابعة | `portal/vp_actions_followup.php` |
| `NT-EX-DVP-012` | EX-DVP | `MENU_ITEM` | الإنابات والتفويضات | المتابعة | `portal/exec_delegations.php` |
| `NT-IAF-001` | IAF | `LANDING_PAGE` | لوحة المراجعة الداخلية | اللوحة — خارج الدورة | `audit/iaf_overview.php` |
| `NT-IAF-002` | IAF | `MENU_ITEM` | ميثاق المراجعة الداخلية | الميثاق وخطة المراجعة | `audit/iaf_charter.php` |
| `NT-IAF-003` | IAF | `MENU_ITEM` | سجل الكون الرقابي | الميثاق وخطة المراجعة | `audit/iaf_universe.php` |
| `NT-IAF-004` | IAF | `MENU_ITEM` | الخطة السنوية للمراجعة | الميثاق وخطة المراجعة | `audit/iaf_plan.php` |
| `NT-IAF-005` | IAF | `MENU_ITEM` | سجل الاستقلال وتعارض المصالح | الميثاق وخطة المراجعة | `audit/iaf_independence.php` |
| `NT-IAF-006` | IAF | `MENU_ITEM` | مهام المراجعة | تنفيذ مهمة المراجعة وأدلتها | `audit/iaf_engagements.php` |
| `NT-IAF-007` | IAF | `TAB_CHILD` | برامج المراجعة | تنفيذ مهمة المراجعة وأدلتها | `audit/iaf_audit_programs.php` |
| `NT-IAF-008` | IAF | `MENU_ITEM` | طلبات الأدلة | تنفيذ مهمة المراجعة وأدلتها | `audit/iaf_evidence_requests.php` |
| `NT-IAF-009` | IAF | `TAB_CHILD` | العينات ونتائج الاختبارات | تنفيذ مهمة المراجعة وأدلتها | `audit/iaf_test_samples.php` |
| `NT-IAF-010` | IAF | `TAB_CHILD` | أوراق العمل | تنفيذ مهمة المراجعة وأدلتها | `audit/iaf_workpapers.php` |
| `NT-IAF-011` | IAF | `MENU_ITEM` | سجل الاطلاع الحساس للمراجعين | تنفيذ مهمة المراجعة وأدلتها | `audit/iaf_access_log.php` |
| `NT-IAF-012` | IAF | `MENU_ITEM` | الملاحظات | ملاحظات المراجعة وتوصياتها | `audit/iaf_findings.php` |
| `NT-IAF-013` | IAF | `MENU_ITEM` | تقارير المراجعة | ملاحظات المراجعة وتوصياتها | `audit/iaf_reports.php` |
| `NT-IAF-014` | IAF | `MENU_ITEM` | خطط المعالجة والمتابعة | متابعة الملاحظات وإغلاقها | `audit/iaf_action_plans.php` |
| `NT-IAF-015` | IAF | `MENU_ITEM` | الملاحظات المتأخرة والتصعيد | متابعة الملاحظات وإغلاقها | `audit/iaf_escalations.php` |
| `NT-IAF-016` | IAF | `MENU_ITEM` | تقييم الجودة | متابعة الملاحظات وإغلاقها | `audit/iaf_quality.php` |
| `NT-IAF-017` | IAF | `MENU_ITEM` | مخاطر وظيفة المراجعة | حوكمة وظيفة المراجعة | `audit/iaf_function_risks.php` |
| `NT-WS-MY-001` | WS-MY | `MENU_ITEM` | مؤشرات الإنجاز الشخصي | الملف الشخصي | `portal/my_achievement.php` |
| `NT-WS-MY-002` | WS-MY | `MENU_ITEM` | البوابة الشخصية | الملف الشخصي | `portal/my_portal.php` |
| `NT-WS-MY-003` | WS-MY | `MENU_ITEM` | المهام المسنَدة | العمل اليومي | `portal/my_tasks.php` |
| `NT-WS-MY-004` | WS-MY | `MENU_ITEM` | الطلبات المقدَّمة | العمل اليومي | `finrequests/request_form.php` |
| `NT-WS-MY-005` | WS-MY | `MENU_ITEM` | البلاغات المسجَّلة | العمل اليومي | `portal/my_reports.php` |
| `NT-WS-MY-006` | WS-MY | `MENU_ITEM` | الصفات الوظيفية والتبديل بينها | الملف الشخصي | `user_capacities.php` |
| `NT-WS-MY-007` | WS-MY | `TAB_CHILD` | كلمة المرور والأمان الشخصي | الملف الشخصي | `settings/change_password.php` |
