# التحليلُ الجنائيُّ للأسطحِ الموروثة — القرار ⑤

> ⛔ **مولَّدٌ من الشجرةِ الحيّةِ والمخزن**: `php tools/repair01_edc_forensic.php`
> **نصُّ قرارِك:** «لا تخمّنْ مالكَها ولا تتقاعدها كلَّها.»

## القاعدةُ التي لا تُتجاوَز

> **أيُّ ملفٍّ يكتب في قاعدةِ البيانات لا يُصنَّف `DEAD` أو `RETIRE` تلقائيًّا.**
> `WRITE_SURFACE = YES` ⇐ **مراجعةٌ فرديّةٌ إلزاميّة** — حتّى لو لم يظهر في
> السايدبار ولم يُستخدم كثيرًا واسمُه سيّئٌ ولديه بديل. **لأنَّ ملفَّ الكتابةِ قد
> يمثّل مسارَ أعمالٍ أو تكاملًا خفيًّا.**

والكتابةُ **مقيسةٌ من الشيفرةِ لا من الاسم** — فاسمُ الملفِّ لا يقول إن كان يكتب.

| الحكم | العدد |
|---|---:|
| `KEEP` | 27 |
| `NEEDS_OWNER_DECISION` | 9 |
| `REDIRECT` | 1 |

---

## `NEEDS_OWNER_DECISION` — 9 سطحًا

| الملفّ | يكتب؟ | جداولُ يكتبها | يناديه | ملاحة | أدوارٌ تراه | حارس | الحكمُ ولماذا |
|---|---|---|---:|---:|---:|---|---|
| `C:/wamp64/www/ems/Tickets/admin_close.php` | **نعم** | `tickets` · `ticket_events` · `ticket_workstreams` | 6 | 2 | 1 | SELF_EARLY | **سطحُ كتابةٍ** — نصُّ القرار: مراجعةٌ فرديّةٌ إلزاميّةٌ قبل أيِّ تقاعد |
| `C:/wamp64/www/ems/Equipments/equipments_drivers.php` | **نعم** | — | 10 | 0 | 3 | SELF_EARLY | **سطحُ كتابةٍ** — نصُّ القرار: مراجعةٌ فرديّةٌ إلزاميّةٌ قبل أيِّ تقاعد |
| `C:/wamp64/www/ems/Tickets/intake_classify.php` | **نعم** | `tickets` · `ticket_events` | 5 | 2 | 1 | SELF_EARLY | **سطحُ كتابةٍ** — نصُّ القرار: مراجعةٌ فرديّةٌ إلزاميّةٌ قبل أيِّ تقاعد |
| `C:/wamp64/www/ems/movement/move_oprators.php` | **نعم** | — | 38 | 0 | 4 | SELF_EARLY | **سطحُ كتابةٍ** — نصُّ القرار: مراجعةٌ فرديّةٌ إلزاميّةٌ قبل أيِّ تقاعد |
| `C:/wamp64/www/ems/movement/project_drivers.php` | **نعم** | — | 48 | 0 | 2 | SELF_EARLY | **سطحُ كتابةٍ** — نصُّ القرار: مراجعةٌ فرديّةٌ إلزاميّةٌ قبل أيِّ تقاعد |
| `C:/wamp64/www/ems/Tickets/ticket_categories_config.php` | **نعم** | — | 6 | 2 | 1 | SHELL | **سطحُ كتابةٍ** — نصُّ القرار: مراجعةٌ فرديّةٌ إلزاميّةٌ قبل أيِّ تقاعد |
| `C:/wamp64/www/ems/Tickets/ticket_escalation_config.php` | **نعم** | — | 9 | 2 | 1 | SHELL | **سطحُ كتابةٍ** — نصُّ القرار: مراجعةٌ فرديّةٌ إلزاميّةٌ قبل أيِّ تقاعد |
| `C:/wamp64/www/ems/Tickets/ticket_sla_config.php` | **نعم** | — | 8 | 2 | 1 | SHELL | **سطحُ كتابةٍ** — نصُّ القرار: مراجعةٌ فرديّةٌ إلزاميّةٌ قبل أيِّ تقاعد |
| `C:/wamp64/www/ems/Tickets/ticket_types_config.php` | **نعم** | — | 16 | 2 | 1 | SHELL | **سطحُ كتابةٍ** — نصُّ القرار: مراجعةٌ فرديّةٌ إلزاميّةٌ قبل أيِّ تقاعد |

---

## `KEEP` — 27 سطحًا

| الملفّ | يكتب؟ | جداولُ يكتبها | يناديه | ملاحة | أدوارٌ تراه | حارس | الحكمُ ولماذا |
|---|---|---|---:|---:|---:|---|---|
| `C:/wamp64/www/ems/Timesheet/aprovment.php` | لا | — | 3 | 0 | 18 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 18 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Reports/contract_report.php` | لا | — | 7 | 0 | 10 | SELF_EARLY | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Reports/contractall.php` | لا | — | 7 | 0 | 10 | SELF_EARLY | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Finance/daily_pricing_fin.php` | لا | — | 4 | 2 | 3 | SELF_EARLY | قراءةٌ فقط · يناديه 4 ملفًّا — جزءٌ من مسارٍ قائم |
| `C:/wamp64/www/ems/Reports/deliy.php` | لا | — | 7 | 0 | 10 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Reports/deriver.php` | لا | — | 3 | 0 | 10 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Reports/driverAndsupplerscontract.php` | لا | — | 6 | 0 | 10 | SELF_EARLY | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Maintenance/equipment_hours_preventive.php` | لا | — | 3 | 1 | 1 | SHELL | قراءةٌ فقط · يناديه 3 ملفًّا — جزءٌ من مسارٍ قائم |
| `C:/wamp64/www/ems/Reports/equipments_reports.php` | لا | — | 4 | 0 | 10 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Maintenance/failure_report.php` | لا | — | 4 | 0 | 4 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 4 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Equipments/fleet_failures.php` | لا | — | 6 | 0 | 3 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 3 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Reports/guard_denials_report.php` | لا | — | 1 | 0 | 3 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 3 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Tickets/inquiry.php` | لا | — | 3 | 1 | 1 | SHELL | قراءةٌ فقط · يناديه 3 ملفًّا — جزءٌ من مسارٍ قائم |
| `C:/wamp64/www/ems/Operations/monthly_plan.php` | لا | — | 33 | 5 | 8 | SELF_EARLY | قراءةٌ فقط · يناديه 33 ملفًّا — جزءٌ من مسارٍ قائم |
| `C:/wamp64/www/ems/Reports/new_reports.php` | لا | — | 3 | 0 | 10 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Projects/project_profile.php` | لا | — | 28 | 0 | 10 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Reports/projects_reports.php` | لا | — | 4 | 0 | 10 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Governance/read_log.php` | لا | — | 39 | 0 | 3 | SELF_EARLY | قراءةٌ فقط · خارجَ الملاحةِ ويراه 3 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Fleet/readiness_cert.php` | لا | — | 3 | 0 | 5 | SELF_EARLY | قراءةٌ فقط · خارجَ الملاحةِ ويراه 5 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Maintenance/return_to_service.php` | لا | — | 10 | 1 | 1 | SELF_EARLY | قراءةٌ فقط · يناديه 10 ملفًّا — جزءٌ من مسارٍ قائم |
| `C:/wamp64/www/ems/Equipments/select_project.php` | لا | — | 42 | 4 | 16 | SHELL | قراءةٌ فقط · يناديه 42 ملفًّا — جزءٌ من مسارٍ قائم |
| `C:/wamp64/www/ems/Employees/showcontractemployee.php` | لا | — | 2 | 0 | 9 | SELF_EARLY | قراءةٌ فقط · خارجَ الملاحةِ ويراه 9 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Reports/timesheet_reports.php` | لا | — | 4 | 0 | 10 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Reports/timesheetdeliy.php` | لا | — | 4 | 0 | 10 | SHELL | قراءةٌ فقط · خارجَ الملاحةِ ويراه 10 دورًا — مسارٌ مباشرٌ حيّ |
| `C:/wamp64/www/ems/Timesheet/view_timesheet.php` | لا | — | 25 | 2 | 18 | SELF_EARLY | قراءةٌ فقط · يناديه 25 ملفًّا — جزءٌ من مسارٍ قائم |
| `C:/wamp64/www/ems/Tickets/watchtower.php` | لا | — | 6 | 2 | 1 | SHELL | قراءةٌ فقط · يناديه 6 ملفًّا — جزءٌ من مسارٍ قائم |
| `C:/wamp64/www/ems/Procurement/wh_returns.php` | لا | — | 4 | 1 | 1 | SELF_EARLY | قراءةٌ فقط · يناديه 4 ملفًّا — جزءٌ من مسارٍ قائم |

---

## `REDIRECT` — 1 سطحًا

| الملفّ | يكتب؟ | جداولُ يكتبها | يناديه | ملاحة | أدوارٌ تراه | حارس | الحكمُ ولماذا |
|---|---|---|---:|---:|---:|---|---|
| `C:/wamp64/www/ems/Employees/employee_card.php` | لا | — | 6 | 0 | 4 | SHELL | قراءةٌ فقط وله بديلٌ يحمل اسمَه: employee_profile.php |

---

## التقاعدُ سلسلةٌ لا حذف

```
منع الاستعمال الجديد ← تحويل إن أمكن ← حفظ التاريخ ← مراقبة
  ← رفع من الملاحة ← تقاعد
```

⛔ **ولا حذفَ ماديٌّ متعجّل** — نصُّ قرارِك.

## المطلوبُ منك

أمامَ كلِّ سطحٍ في `NEEDS_OWNER_DECISION` اكتبْ واحدًا من ثمانية:
`KEEP` · `MOVE` · `MERGE` · `TAB` · `REDIRECT` · `RETIRE` · `DEAD` · `NEEDS_OWNER_DECISION`

**وأسرعُ طريقٍ:** قلْ «راجعها معي واحدةً واحدة» فأعرض كلَّ سطحٍ بما يكتبه
ومن يناديه وأقترح حكمَه، وتقول نعم أو لا.

