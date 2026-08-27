# دليلُ مشيِ محطّاتِ القبولِ البشريّ — البندُ ٦٣

> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_w16_uat_runbook.php --md`
> **ولا يكتب هذا الدليلُ `PASSED`** — القيدُ `chk_w16_uat_real` يردُّ في
> القاعدةِ إعلانَ نجاحٍ بلا فاعلٍ حقيقيٍّ وزمنٍ ودليل. **فهو يُمهّد ولا يمرّ.**

| الحقل | القيمة |
|---|---|
| `Commit Hash` | `022242613d7c` |
| `Measured At` | 2026-08-27 04:53:36 |
| المحطّات | 13 · بمرشَّحٍ واحد 3 · تحتاج اختيارًا 10 |

## قبل البدء

1. **حسابُ كلِّ دورٍ** من `docs/UAT_PLAN_ar.md` ⛔ ولا تُنسَخ كلمةُ مرورٍ هنا.
2. **المسارُ السالبُ (⊘) يجب أن يُردّ** — ونجاحُه رسوبٌ لا نجاح.
3. بعد كلِّ محطّة: سجّل **الفاعلَ والزمنَ والدليل** (لقطة أو رقم سجلّ).


## رحلةُ `MNT_CYCLE`

| # | المحطّة | ما يفعله الإنسان | الدور | الشاشةُ المرشَّحة | سالب |
|---:|---|---|---|---|---|
| 1 | `W16-U-10` | يفتح مشغل الموقع بلاغ عطل من شاشته | إدارة الموقع | **يختار الماشي من:**<br>`admin/org_permits.php` «<br>`Operations/site_gate_person.php` «<br>`Operations/site_gate_equip.php` «<br>`Operations/shift_entry.php` «<br>`Timesheet/timesheet.php` «<br>`Operations/site_work_calendar.php` «<br>`Operations/site_shift_plan.php` «<br>`Operations/gov_dept_sit.php` «<br>`Risk/risk_dept_sit.php` « | — |
| 2 | `W16-U-11` | يفتح فني الصيانة امر عمل بحالته | ادارة الصيانة | **يختار الماشي من:**<br>`Maintenance/master_data.php` «<br>`Maintenance/breakdown_intake.php` «<br>`Maintenance/external_repairs.php` «<br>`Maintenance/daily_care.php` «<br>`Maintenance/return_to_service.php` «<br>`Maintenance/workshop.php` «<br>`Maintenance/failure_report.php` «<br>`Maintenance/gov_dept_mnt.php` «<br>`Maintenance/preventive_plans.php` «<br>`Maintenance/equipment_hours_preventive.php` «<br>`Maintenance/repeat_repairs.php` «<br>`Maintenance/part_requests.php` «<br>`Maintenance/dashboard_mnt.php` «<br>`Maintenance/mnt_kpis.php` «<br>`Risk/risk_dept_mnt.php` « | — |
| 3 | `W16-U-12` | يستلم مسؤول التشغيل ويقفل بشهادة عودة معتمدة | ادارة التشغيل | **يختار الماشي من:**<br>`Timesheet/aprovment.php` «<br>`Reports/new_reports.php` «<br>`Operations/containers.php` «<br>`Operations/fleet_utilization.php` «<br>`Operations/unit_perf.php` «<br>`Operations/production.php` «<br>`Operations/stops_unattributed.php` «<br>`Operations/monthly_plan.php` «<br>`Risk/risk_dept_ops.php` «<br>`Operations/unit_correction.php` «<br>`Operations/fleet_calendar.php` «<br>`Operations/distribution_space.php` «<br>`Operations/gov_dept_ops.php` «<br>`Operations/daily_plan.php` «<br>`Reports/daily_units_report.php` «<br>`Operations/operations_room.php` «<br>`admin/ops_manager_board.php` « | — |
| 4 | `W16-U-13` | تحاول الصيانة اعادة الاصل بلا شهادة معتمدة فترد | ادارة الصيانة | `Maintenance/repeat_repairs.php` « | ⊘ يجب أن يُردّ |

## رحلةُ `REQ_TO_EFFECT`

| # | المحطّة | ما يفعله الإنسان | الدور | الشاشةُ المرشَّحة | سالب |
|---:|---|---|---|---|---|
| 1 | `W16-U-01` | يطلق صاحب الحساب طلب اجازة من مساحة عملي | **لم يُحَلّ** | **لا شاشةَ يراها هذا الدورُ في نطاقِه** | — |
| 2 | `W16-U-02` | يرى صاحب الحساب طلبه في طلباتي بحالته الاولى | **لم يُحَلّ** | **لا شاشةَ يراها هذا الدورُ في نطاقِه** | — |
| 3 | `W16-U-03` | يستقبل مسؤول القوى الطلب في سجل ادارته | القوى التشغيلية | `Operations/shift_log.php` « | — |
| 4 | `W16-U-04` | يعتمد مسؤول القوى بسلطته المسجلة | القوى التشغيلية | `Operations/shift_log.php` « | — |
| 5 | `W16-U-05` | تقيد الموارد البشرية الاثر في سجل الانسان | ادارة الموارد البشرية | `Employees/equipment_operators.php` «<br>`Equipments/equipments.php` «<br>`Employees/employees.php` «<br>`api/controllers/employees.php` «<br>`Employees/employee_equipment_history.php` « | — |
| 6 | `W16-U-06` | يرى صاحب الحساب الحالة النهائية ولا يعدلها | **لم يُحَلّ** | **لا شاشةَ يراها هذا الدورُ في نطاقِه** | — |
| 7 | `W16-U-07` | يحاول صاحب الحساب اعتماد طلبه بنفسه فيرد | القوى التشغيلية | **يختار الماشي من:**<br>`Workforce/op_codes.php` «<br>`Workforce/worker_leave_absence.php` «<br>`Workforce/recruitment_pipeline.php` «<br>`Workforce/housing_units.php` «<br>`Oprators/select_project.php` «<br>`Workforce/worker_movement.php` «<br>`Workforce/gov_dept_wrk.php` «<br>`Workforce/proposed_deductions.php` «<br>`Workforce/rotation.php` «<br>`Operations/shift_log.php` «<br>`Risk/risk_dept_wrk.php` « | ⊘ يجب أن يُردّ |
| 8 | `W16-U-08` | يحاول المسؤول اعتمادا فوق سقف سلطته فيرد | القوى التشغيلية | **يختار الماشي من:**<br>`Workforce/op_codes.php` «<br>`Workforce/worker_leave_absence.php` «<br>`Workforce/recruitment_pipeline.php` «<br>`Workforce/housing_units.php` «<br>`Oprators/select_project.php` «<br>`Workforce/worker_movement.php` «<br>`Workforce/gov_dept_wrk.php` «<br>`Workforce/proposed_deductions.php` «<br>`Workforce/rotation.php` «<br>`Operations/shift_log.php` «<br>`Risk/risk_dept_wrk.php` « | ⊘ يجب أن يُردّ |
| 9 | `W16-U-09` | تحاول الموارد البشرية الاعتماد بتفويض منته فيرد | **لم يُحَلّ** | **لا شاشةَ يراها هذا الدورُ في نطاقِه** | ⊘ يجب أن يُردّ |

## تسجيلُ المرور

⛔ **لا تُكتب `PASSED` بأداة.** يُسجَّل المرورُ بفاعلٍ حقيقيٍّ وزمنٍ ودليلٍ في
`repair01_w16_uat` — والقاعدةُ تردُّ ما نقص منها.
