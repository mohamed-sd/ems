# منحُ شاشاتِ الموجاتِ لمديرِ إدارتِها — مشكلةُ المالك ②

> ⛔ **مولَّدٌ من قياسٍ حيّ**: `php tools/repair01_grant_wave_screens.php --md`
> **والنمطُ `1/1/1/1` مستعمَلٌ في 209 صفوفٍ قائمة** — فـ«مثلَ بقيّةِ الشاشات» مقيسٌ لا مُتخيَّل.

**يحتاج منحًا: 113 شاشة**

| الإدارة | الدور | العدد |
|---|---|---:|
| `DEP-03` | إدارة التمويل | 18 |
| `DEP-04` | ادارة الاسطول | 5 |
| `DEP-05` | إدارة المالية | 7 |
| `DEP-06` | أمين الخزينة | 11 |
| `DEP-07` | ادارة الموارد البشرية | 12 |
| `DEP-09` | إدارة المخاطر | 4 |
| `DEP-10` | إدارة البلاغات | 9 |
| `DEP-13` | القوى التشغيلية | 1 |
| `DEP-14` | ادارة الصيانة | 6 |
| `DEP-15` | إدارة النقل والترحيل | 5 |
| `DEP-16` | إدارة المشتريات | 7 |
| `DEP-17` | أمين المستودع | 7 |
| `EX-CEO` | الإدارة التنفيذية | 16 |
| `IAF` | المراجع الداخلي المستقل | 5 |

## إداراتٌ لا يُحَلُّ دورُها — تُعلَن ولا يُخمَّن لها دور

| الإدارة | شاشات |
|---|---:|
| `DEP-08` | 17 |
| `EX-DVP` | 1 |
| `WS-MY` | 1 |

## التفصيل

| الشاشة | الاسم | الإدارة | الحالُ قبلَ المنح |
|---|---|---|---|
| `fin_capital_balance.php` | رصيد رأس المال والعائد | DEP-03 | `1000` |
| `fin_close_audit.php` | تقرير المراجعة والإغلاق | DEP-03 | `1000` |
| `fin_contract_close.php` | الإقفالات التعاقدية | DEP-03 | `1000` |
| `fin_contract_terms.php` | بنود وشروط التمويل | DEP-03 | `1000` |
| `fin_contracts.php` | سجل عقود التمويل | DEP-03 | `1000` |
| `fin_covenants.php` | مصفوفة الالتزامات التمويلية | DEP-03 | `1000` |
| `fin_due_diligence.php` | وثائق التأهيل والعناية الواجبة | DEP-03 | `1000` |
| `fin_final_close.php` | إقفال التمويل | DEP-03 | `1000` |
| `fin_financier_contacts.php` | جهات اتصال الممولين والمفوضين | DEP-03 | `1000` |
| `fin_financier_dues.php` | استحقاقات الممول | DEP-03 | `1000` |
| `fin_migration_map.php` | خريطة الترحيل ومصفوفة التسوية | DEP-03 | `1000` |
| `fin_monthly_close.php` | الإقفالات الشهرية وكشف الحساب | DEP-03 | `1000` |
| `fin_needs.php` | فرص واحتياجات التمويل | DEP-03 | `1000` |
| `fin_offers.php` | عروض التمويل والتفاوض | DEP-03 | `1000` |
| `fin_payment_allocation.php` | تخصيص السداد على الأقساط | DEP-03 | `1000` |
| `fin_payment_orders.php` | أوامر الدفع والسداد الفعلي | DEP-03 | `1000` |
| `fin_precontract_review.php` | مراجعة ما قبل التعاقد | DEP-03 | `1000` |
| `fin_ref_dictionary.php` | القوائم وقاموس البيانات | DEP-03 | `1000` |
| `asset_assignments.php` | إسناد الأصل وحركته | DEP-04 | `1000` |
| `asset_exit.php` | خروج الأصل | DEP-04 | `1000` |
| `asset_intake.php` | طلب إدخال الأصل | DEP-04 | `1000` |
| `asset_readiness.php` | الجاهزية والملخص الشهري | DEP-04 | `1000` |
| `asset_use_rights.php` | حق الاستخدام التشغيلي | DEP-04 | `1000` |
| `acc_adjustments.php` | الاستحقاقات والمقدمات والمخصصات | DEP-05 | `1000` |
| `acc_closing_checklist.php` | قائمة إقفال الفترة | DEP-05 | `1000` |
| `acc_cost_centers.php` | مراكز التكلفة | DEP-05 | `1000` |
| `acc_credit_control.php` | الرقابة الائتمانية وحدود العملاء | DEP-05 | `1000` |
| `acc_reconciliations.php` | مطابقات الحسابات | DEP-05 | `1000` |
| `acc_reopen_governance.php` | حوكمة إعادة فتح الفترات | DEP-05 | `1000` |
| `acc_trial_balance.php` | ميزان المراجعة | DEP-05 | `1000` |
| `tre_allocations.php` | تخصيص التحصيل على الفواتير | DEP-06 | `1000` |
| `tre_cash_count.php` | الجرد النقدي للخزائن | DEP-06 | `1000` |
| `tre_cash_moves.php` | حركة الخزينة والصناديق | DEP-06 | `1000` |
| `tre_fx_deals.php` | تنفيذ عمليات الصرف الأجنبي | DEP-06 | `1000` |
| `tre_guarantees.php` | خطابات الضمان والاعتمادات المستندية | DEP-06 | `1000` |
| `tre_instruments.php` | سجل الأدوات المالية | DEP-06 | `1000` |
| `tre_liquidity_board.php` | لوحة الخزينة والسيولة | DEP-06 | `1000` |
| `tre_payment_queue.php` | صف الدفع المعتمد | DEP-06 | `1000` |
| `tre_petty_cash.php` | عهد النثرية وتسويتها | DEP-06 | `1000` |
| `tre_transfers.php` | التحويلات بين الحسابات | DEP-06 | `1000` |
| `tre_vessels.php` | الحسابات البنكية والصناديق | DEP-06 | `1000` |
| `hr_benefits.php` | المزايا والتأمينات | DEP-07 | `1000` |
| `hr_board.php` | لوحة الموارد البشرية | DEP-07 | `1000` |
| `hr_disciplinary.php` | القضايا التأديبية والتحقيق | DEP-07 | `1000` |
| `hr_employee_documents.php` | مستندات الموظف | DEP-07 | `1000` |
| `hr_job_movements.php` | الحركات الوظيفية | DEP-07 | `1000` |
| `hr_onboarding.php` | التهيئة والمباشرة | DEP-07 | `1000` |
| `hr_performance.php` | تقييم الأداء الوظيفي | DEP-07 | `1000` |
| `hr_training.php` | التدريب والكفاءة | DEP-07 | `1000` |
| `hr_workforce_report.php` | تقرير القوى العاملة | DEP-07 | `1000` |
| `payroll_lines.php` | أسطر مسير الرواتب | DEP-07 | `1000` |
| `rec_applications.php` | طلبات الترشح | DEP-07 | `1000` |
| `rec_stages.php` | مراحل التوظيف | DEP-07 | `1000` |
| `risk_closure.php` | سجل الإغلاق والأدلة | DEP-09 | `1000` |
| `risk_escalations.php` | تصعيدات المخاطر | DEP-09 | `1000` |
| `risk_events.php` | أحداث المخاطر والخسائر | DEP-09 | `1000` |
| `risk_taxonomy.php` | تصنيف المخاطر | DEP-09 | `1000` |
| `tkt_assignment.php` | سجل الإسناد | DEP-10 | `1000` |
| `tkt_communications.php` | سجل التواصل | DEP-10 | `1000` |
| `tkt_escalation.php` | التصعيد | DEP-10 | `1000` |
| `tkt_parties.php` | أطراف البلاغ | DEP-10 | `1000` |
| `tkt_reopen.php` | إعادة الفتح | DEP-10 | `1000` |
| `tkt_resolution_actions.php` | إجراءات المعالجة | DEP-10 | `1000` |
| `tkt_routing.php` | سجل التوجيه | DEP-10 | `1000` |
| `tkt_subject_types.php` | كتالوج أنواع محل البلاغ | DEP-10 | `1000` |
| `tkt_verification.php` | التحقق والإغلاق | DEP-10 | `1000` |
| `wf_coverage.php` | لوحة الاحتياج والتغطية | DEP-13 | `لا صفّ` |
| `breakdown_intake.php` | استقبال البلاغات الفنية | DEP-14 | `1110` |
| `daily_care.php` | العناية اليومية والتشحيم | DEP-14 | `1110` |
| `external_repairs.php` | الإصلاح الخارجي ومطالبات الضمان | DEP-14 | `1110` |
| `mnt_kpis.php` | مؤشرات الصيانة الدورية | DEP-14 | `1110` |
| `part_requests.php` | طلبات صرف القطع | DEP-14 | `1110` |
| `repeat_repairs.php` | سجل إعادة الإصلاح | DEP-14 | `1110` |
| `transfer_closure.php` | إقفال أمر الترحيل | DEP-15 | `1110` |
| `transfer_damage_claims.php` | مطالبات التلف والحوادث | DEP-15 | `1010` |
| `transfer_orders_report.php` | تقرير أوامر الترحيل | DEP-15 | `1000` |
| `transfer_origin_handover.php` | تجهيز المغادرة والتسليم الأصلي | DEP-15 | `1110` |
| `transfer_trip_legs.php` | مراحل الرحلة | DEP-15 | `1110` |
| `proc_award_minutes.php` | محضر المقارنة والترسية | DEP-16 | `1000` |
| `proc_delivery_track.php` | متابعة التوريد والاستلام | DEP-16 | `1110` |
| `proc_offers.php` | عروض الموردين المستلمة | DEP-16 | `1000` |
| `proc_packages.php` | تجميع الطلبات وخطة الشراء | DEP-16 | `1110` |
| `proc_po_amendments.php` | استثناءات الشراء وتعديلات الأوامر | DEP-16 | `1110` |
| `proc_rfq.php` | طلب العروض ودعوات الموردين | DEP-16 | `1110` |
| `proc_supplier_eval.php` | تقييم أداء التوريد | DEP-16 | `1110` |
| `proc_track_policy.php` | سياسة تتبع الأصناف | DEP-17 | `1110` |
| `wh_hazmat.php` | ضوابط المواد الخطرة والمتفجرات | DEP-17 | `1110` |
| `wh_issue_requests.php` | طلبات الصرف الواردة | DEP-17 | `1110` |
| `wh_lots.php` | سجل الدفعات | DEP-17 | `1110` |
| `wh_month_close.php` | الإقفال الشهري للمخازن | DEP-17 | `1110` |
| `wh_serials.php` | سجل الأرقام التسلسلية | DEP-17 | `1110` |
| `wh_track_quality.php` | جودة بيانات التتبع | DEP-17 | `1110` |
| `exec_actions_followup.php` | متابعة القرارات التنفيذية | EX-CEO | `1000` |
| `exec_contract_registry.php` | سجل العقود الموحد | EX-CEO | `1000` |
| `exec_crisis_command.php` | قيادة الأزمات والطوارئ | EX-CEO | `1000` |
| `exec_critical_exceptions.php` | الاستثناءات الحرجة | EX-CEO | `1000` |
| `exec_daily_deviations.php` | انحرافات وقرارات اليوم | EX-CEO | `1000` |
| `exec_daily_report.php` | التقرير اليومي التنفيذي | EX-CEO | `1000` |
| `exec_daily_stops.php` | تفصيل توقفات اليوم | EX-CEO | `1000` |
| `exec_escalations.php` | التصعيدات العليا | EX-CEO | `1000` |
| `exec_leadership_appointments.php` | موافقات التعيين في المسميات القيادية | EX-CEO | `1000` |
| `exec_meeting_decisions.php` | قرارات الاجتماعات | EX-CEO | `1000` |
| `exec_monthly_pack.php` | التقرير الشهري التنفيذي | EX-CEO | `1000` |
| `exec_raised_requests.php` | الطلبات المرفوعة إلى القيادة | EX-CEO | `1000` |
| `exec_redline_breaches.php` | تجاوزات الخطوط الحمراء | EX-CEO | `1000` |
| `exec_reserved_matters.php` | المسائل المحجوزة | EX-CEO | `1000` |
| `exec_strategic_decisions.php` | القرارات الاستراتيجية | EX-CEO | `1000` |
| `exec_weekly_report.php` | التقرير الأسبوعي التنفيذي | EX-CEO | `1000` |
| `iaf_audit_programs.php` | برامج المراجعة | IAF | `1000` |
| `iaf_evidence_requests.php` | طلبات الأدلة | IAF | `1000` |
| `iaf_function_risks.php` | مخاطر وظيفة المراجعة | IAF | `1000` |
| `iaf_overview.php` | لوحة المراجعة الداخلية | IAF | `1000` |
| `iaf_test_samples.php` | العينات ونتائج الاختبارات | IAF | `1000` |
