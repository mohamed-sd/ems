# دقةُ أزواجِ فصلِ الواجبات · 2026-08-10 16:13

| الزوج | الاسم | الدرجة | ما يخفضه |
|---|---|---|---|
| `sod_supplier_cycle` | إنشاء مورد + تعديل حسابه البنكي + اعتماد دفع | ● يُنذر | supplier.payment.approve (approx · Finance/payments_fin.php) |
| `sod_procure_cycle` | طلب شراء + ترسية + استلام + صرف | ● يُنذر | proc.award (approx · Procurement/orders_proc.php) |
| `sod_hours_claim` | إدخال ساعات + اعتمادها + إنشاء مستخلصها | ✔ يمنع | — |
| `sod_payroll_cycle` | إنشاء موظف + تعديل راتبه + تشغيل مسيّره | ● يُنذر | payroll.salary.update (approx · Employees/employee_contracts_details.php) |
| `sod_collection_hide` | فاتورة + سند قبض + مطابقة البنك | ● يُنذر | receipt.create (approx · Contracts/collections.php) |
| `sod_self_privilege` | إنشاء صلاحية + اعتمادها + تنفيذها | ● يُنذر | permission.approve (approx · Governance/access_review.php) · permission.apply (approx · Settings/role_permissions.php) |
| `sod_ownership_move` | تسجيل حصة ملكية + اعتماد نقلها | ✘ لا يُقاس | ownership.share.create (absent) · ownership.transfer.approve (absent) |
| `sod_period_reopen` | فتح فترة + إدخال قيد + اعتماد الإقفال | ✘ لا يُقاس | period.open (absent) · period.close.approve (absent) |
| `sod_journal_recon` | إدخال قيود + مطابقة البنك — من يكتب الدفتر ل | ✔ يمنع | — |
| `sod_treasury_cover` | قبض + اعتماد دفع + مطابقة البنك — يد الخزينة | ● يُنذر | receipt.create (approx · Contracts/collections.php) · supplier.payment.approve (approx · Finance/payments_fin.php) |

**يمنع: 2 · يُنذر: 6 · لا يُقاس: 2**
