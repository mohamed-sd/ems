# CMP-03 — سجل مصادر اللحاق (توصية المالك ③)

كل عمودٍ محقونٍ يعرض «—» حتى يُربط مصدره الصفّي (سلسلة الاعتماد · fin_event_links · publishFact · action_execution_log…). كل صفٍّ هنا مهمةُ ربطٍ تلحق.

| الملف | الشاشة | العمود | المفتاح |
|---|---|---|---|
| Portal/my_achievement.php | إنجازي (my_achievement.php) | الكيان | `entity` |
| Portal/my_portal.php | بوابتي (my_portal.php) | الكيان | `entity` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | الكيان | `entity` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | مرجع التفويض | `authority_ref` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | تاريخ الاعتماد | `approved_at` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | تاريخ الإنشاء | `created_at` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | المرجع الأب | `parent_ref` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | المُنشئ — الاسم والصفة | `creator` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | المعتمِد المطلوب | `required_approver` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | المرفقات | `attachments` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | الكيان | `entity` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | تاريخ الاعتماد | `approved_at` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | الحالة | `status` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | الكيان | `entity` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | مرجع التفويض | `authority_ref` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | تاريخ الاعتماد | `approved_at` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | تاريخ الإنشاء | `created_at` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | المُنشئ — الاسم والصفة | `creator` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | مركز التكلفة | `cost_center` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | العملة | `currency` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | المستند المرفق | `attached_doc` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | الكيان | `entity` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | مرجع التفويض | `authority_ref` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | تاريخ الاعتماد | `approved_at` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | تاريخ الإنشاء | `created_at` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | المرفق | `attachment` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | العملة | `currency` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | الكيان | `entity` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | مرجع التفويض | `authority_ref` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | تاريخ الاعتماد | `approved_at` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | تاريخ الإنشاء | `created_at` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | المرجع الأب | `parent_ref` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | المُنشئ — الاسم والصفة | `creator` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | المعتمِد — الاسم والصفة | `approver` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | المرفق | `attachment` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | العملة | `currency` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | الكيان | `entity` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | مرجع التفويض | `authority_ref` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | تاريخ الاعتماد | `approved_at` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | تاريخ الإنشاء | `created_at` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | المرجع الأب | `parent_ref` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | الحالة | `status` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | سجل الاطّلاع | `view_log` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | المرفق | `attachment` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | مركز التكلفة | `cost_center` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | العملة | `currency` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | الكيان | `entity` |
| Operations/operations_room.php | غرفة عمليات المواقع (ops_room.php) | الكيان | `entity` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | الكيان | `entity` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | مرجع التفويض | `authority_ref` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | تاريخ الاعتماد | `approved_at` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | تاريخ الإنشاء | `created_at` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | المرجع الأب | `parent_ref` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | الحالة | `status` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | المرفق | `attachment` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | الكيان | `entity` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | مرجع التفويض | `authority_ref` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | تاريخ الاعتماد | `approved_at` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | تاريخ الإنشاء | `created_at` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | المرجع الأب | `parent_ref` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | المعتمِد — الاسم والصفة | `approver` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | المرفق | `attachment` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | الكيان | `entity` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | مرجع التفويض | `authority_ref` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | تاريخ الاعتماد | `approved_at` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | تاريخ الإنشاء | `created_at` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | المرجع الأب | `parent_ref` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | المعتمِد — الاسم والصفة | `approver` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | المرفق | `attachment` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | مركز التكلفة | `cost_center` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | العملة | `currency` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | الكيان | `entity` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | مرجع التفويض | `authority_ref` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | تاريخ الاعتماد | `approved_at` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | تاريخ الإنشاء | `created_at` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | المرجع الأب | `parent_ref` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | المعتمِد — الاسم والصفة | `approver` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | المرفق | `attachment` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | الكيان | `entity` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | مرجع التفويض | `authority_ref` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | تاريخ الاعتماد | `approved_at` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | تاريخ الإنشاء | `created_at` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | المرجع الأب | `parent_ref` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | المعتمِد — الاسم والصفة | `approver` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | الحالة | `status` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | مفتاح منع التكرار | `idem_key` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | معكوس بـ | `reversed_by` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | عكس عن | `reversal_of` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | درجة الأثر | `impact_grade` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | المرفق | `attachment` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | الكيان | `entity` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | مرجع التفويض | `authority_ref` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | تاريخ الاعتماد | `approved_at` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | تاريخ الإنشاء | `created_at` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | المُنشئ — الاسم والصفة | `creator` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | المعتمِد — الاسم والصفة | `approver` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | المرفق | `attachment` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | الكيان | `entity` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | مرجع التفويض | `authority_ref` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | تاريخ الاعتماد | `approved_at` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | تاريخ الإنشاء | `created_at` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | المرجع الأب | `parent_ref` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | مفتاح منع التكرار | `idem_key` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | معكوس بـ | `reversed_by` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | عكس عن | `reversal_of` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | درجة الأثر | `impact_grade` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | المرفق | `attachment` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | الكيان | `entity` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | مرجع التفويض | `authority_ref` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | تاريخ الاعتماد | `approved_at` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | تاريخ الإنشاء | `created_at` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | المعتمِد — الاسم والصفة | `approver` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | الحالة | `status` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | مفتاح منع التكرار | `idem_key` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | معكوس بـ | `reversed_by` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | عكس عن | `reversal_of` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | درجة الأثر | `impact_grade` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | المرفق | `attachment` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | مركز التكلفة | `cost_center` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | الكيان | `entity` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | مرجع التفويض | `authority_ref` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | تاريخ الاعتماد | `approved_at` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | تاريخ الإنشاء | `created_at` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | المُنشئ — الاسم والصفة | `creator` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | المعتمِد — الاسم والصفة | `approver` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | الحالة | `status` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | الجهة المُنشئة | `creating_entity` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | المرفق | `attachment` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | مركز التكلفة | `cost_center` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | العملة | `currency` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | الكيان | `entity` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | الكيان | `entity` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | تاريخ الاعتماد | `approved_at` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | تاريخ الإنشاء | `created_at` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | المرجع الأب | `parent_ref` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | مفتاح منع التكرار | `idem_key` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | معكوس بـ | `reversed_by` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | عكس عن | `reversal_of` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | درجة الأثر | `impact_grade` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | المرفق | `attachment` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | مركز التكلفة | `cost_center` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | الكيان | `entity` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | تاريخ الاعتماد | `approved_at` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | تاريخ الإنشاء | `created_at` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | المرجع الأب | `parent_ref` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | المُنشئ — الاسم والصفة | `creator` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | المرفق | `attachment` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | الكيان | `entity` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | مرجع التفويض | `authority_ref` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | تاريخ الاعتماد | `approved_at` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | تاريخ الإنشاء | `created_at` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | المعتمِد — الاسم والصفة | `approver` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | الحالة | `status` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | مفتاح منع التكرار | `idem_key` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | معكوس بـ | `reversed_by` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | عكس عن | `reversal_of` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | درجة الأثر | `impact_grade` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | المرفق | `attachment` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | مركز التكلفة | `cost_center` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | العملة | `currency` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | الكيان | `entity` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | مرجع التفويض | `authority_ref` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | تاريخ الاعتماد | `approved_at` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | تاريخ الإنشاء | `created_at` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | المُنشئ — الاسم والصفة | `creator` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | المعتمِد — الاسم والصفة | `approver` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | مفتاح منع التكرار | `idem_key` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | معكوس بـ | `reversed_by` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | عكس عن | `reversal_of` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | درجة الأثر | `impact_grade` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | المرفق | `attachment` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | مركز التكلفة المحمَّل | `loaded_cost_center` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | الكيان | `entity` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | مرجع التفويض | `authority_ref` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | تاريخ الاعتماد | `approved_at` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | تاريخ الإنشاء | `created_at` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | المرجع الأب | `parent_ref` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | المُنشئ — الاسم والصفة | `creator` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | المعتمِد — الاسم والصفة | `approver` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | الحالة | `status` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | المرفق | `attachment` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | مركز التكلفة | `cost_center` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | الكيان | `entity` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | مرجع التفويض | `authority_ref` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | تاريخ الاعتماد | `approved_at` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | تاريخ الإنشاء | `created_at` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | المرجع الأب | `parent_ref` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | المُنشئ — الاسم والصفة | `creator` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | المعتمِد — الاسم والصفة | `approver` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | المرفق | `attachment` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | الكيان | `entity` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | مركز التكلفة | `cost_center` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | الكيان | `entity` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | مرجع التفويض | `authority_ref` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | تاريخ الاعتماد | `approved_at` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | تاريخ الإنشاء | `created_at` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | المرجع الأب | `parent_ref` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | المُنشئ — الاسم والصفة | `creator` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | مفتاح منع التكرار | `idem_key` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | معكوس بـ | `reversed_by` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | عكس عن | `reversal_of` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | درجة الأثر | `impact_grade` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | المرفق | `attachment` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | مركز التكلفة | `cost_center` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | الكيان | `entity` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | مرجع التفويض | `authority_ref` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | تاريخ الاعتماد | `approved_at` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | تاريخ الإنشاء | `created_at` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | المرجع الأب | `parent_ref` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | المُنشئ — الاسم والصفة | `creator` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | المعتمِد — الاسم والصفة | `approver` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | المرفق | `attachment` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | مركز التكلفة | `cost_center` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | العملة | `currency` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | الكيان | `entity` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | مرجع التفويض | `authority_ref` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | تاريخ الاعتماد | `approved_at` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | تاريخ الإنشاء | `created_at` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | المرجع الأب | `parent_ref` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | المُنشئ — الاسم والصفة | `creator` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | مركز التكلفة | `cost_center` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | الكيان | `entity` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | مرجع التفويض | `authority_ref` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | تاريخ الاعتماد | `approved_at` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | تاريخ الإنشاء | `created_at` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | المرفق | `attachment` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | مركز التكلفة | `cost_center` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | العملة | `currency` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | الكيان | `entity` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | مرجع التفويض | `authority_ref` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | تاريخ الاعتماد | `approved_at` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | تاريخ الإنشاء | `created_at` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | المرجع الأب | `parent_ref` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | المرفق | `attachment` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | مركز التكلفة | `cost_center` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | الكيان | `entity` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | مرجع التفويض | `authority_ref` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | تاريخ الاعتماد | `approved_at` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | تاريخ الإنشاء | `created_at` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | المرجع الأب | `parent_ref` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | المُنشئ — الاسم والصفة | `creator` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | المعتمِد — الاسم والصفة | `approver` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | مفتاح منع التكرار | `idem_key` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | معكوس بـ | `reversed_by` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | عكس عن | `reversal_of` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | درجة الأثر | `impact_grade` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | المرفق | `attachment` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | مركز التكلفة | `cost_center` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | العملة | `currency` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | الكيان | `entity` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | مرجع التفويض | `authority_ref` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | تاريخ الاعتماد | `approved_at` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | تاريخ الإنشاء | `created_at` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | المرجع الأب | `parent_ref` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | المُنشئ — الاسم والصفة | `creator` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | المعتمِد — الاسم والصفة | `approver` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | الحالة | `status` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | مفتاح منع التكرار | `idem_key` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | معكوس بـ | `reversed_by` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | عكس عن | `reversal_of` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | درجة الأثر | `impact_grade` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | المرفق | `attachment` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | مركز التكلفة | `cost_center` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | العملة | `currency` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | الكيان | `entity` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | مرجع التفويض | `authority_ref` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | تاريخ الاعتماد | `approved_at` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | تاريخ الإنشاء | `created_at` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | المُنشئ — الاسم والصفة | `creator` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | المعتمِد — الاسم والصفة | `approver` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | الحالة | `status` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | مفتاح منع التكرار | `idem_key` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | معكوس بـ | `reversed_by` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | عكس عن | `reversal_of` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | درجة الأثر | `impact_grade` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | سجل الاطّلاع | `view_log` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | المرفق | `attachment` |
| Finance/variance_monitor_fin.php | متابعة انحراف المنفَّذ عن المخطط (variance.php) | الكيان | `entity` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | الكيان | `entity` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | مركز التكلفة | `cost_center` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Clients/clients.php | سجل العملاء (clients.php) | العملة الأساسية | `base_currency` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | الكيان | `entity` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | مرجع التفويض | `authority_ref` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | تاريخ الاعتماد | `approved_at` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | تاريخ الإنشاء | `created_at` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | مفتاح منع التكرار | `idem_key` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | معكوس بـ | `reversed_by` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | عكس عن | `reversal_of` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | درجة الأثر | `impact_grade` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | سجل الاطّلاع | `view_log` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | المرفق | `attachment` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | مركز التكلفة | `cost_center` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | العملة | `currency` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | سعر الصرف | `fx_rate` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | الكيان | `entity` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | مرجع التفويض | `authority_ref` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | تاريخ الاعتماد | `approved_at` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | تاريخ الإنشاء | `created_at` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | المُنشئ — الاسم والصفة | `creator` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | المعتمِد — الاسم والصفة | `approver` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | المرفق | `attachment` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | الكيان | `entity` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | الكيان | `entity` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | مرجع التفويض | `authority_ref` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | تاريخ الاعتماد | `approved_at` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | تاريخ الإنشاء | `created_at` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | المرجع الأب | `parent_ref` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | المُنشئ — الاسم والصفة | `creator` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | المعتمِد — الاسم والصفة | `approver` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | الحالة | `status` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | المرفق | `attachment` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | مركز التكلفة | `cost_center` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | العملة | `currency` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | الكيان | `entity` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | تاريخ الاعتماد | `approved_at` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | تاريخ الإنشاء | `created_at` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | المرجع الأب | `parent_ref` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | المُنشئ — الاسم والصفة | `creator` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | المعتمِد — الاسم والصفة | `approver` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | المرفق | `attachment` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | مركز التكلفة | `cost_center` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | الكيان | `entity` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | تاريخ الاعتماد | `approved_at` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | تاريخ الإنشاء | `created_at` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | المرجع الأب | `parent_ref` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | المعتمِد — الاسم والصفة | `approver` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | الحالة | `status` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | مفتاح منع التكرار | `idem_key` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | معكوس بـ | `reversed_by` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | عكس عن | `reversal_of` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | درجة الأثر | `impact_grade` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | المرفق | `attachment` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | مركز التكلفة | `cost_center` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | الكيان | `entity` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | مرجع التفويض | `authority_ref` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | تاريخ الاعتماد | `approved_at` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | تاريخ الإنشاء | `created_at` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | المرجع الأب | `parent_ref` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | المُنشئ — الاسم والصفة | `creator` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | المعتمِد — الاسم والصفة | `approver` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | المرفق | `attachment` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | مركز التكلفة | `cost_center` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | الكيان | `entity` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | مرجع التفويض | `authority_ref` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | تاريخ الاعتماد | `approved_at` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | تاريخ الإنشاء | `created_at` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | المرجع الأب | `parent_ref` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | المُنشئ — الاسم والصفة | `creator` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | المعتمِد — الاسم والصفة | `approver` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | الحالة | `status` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | مفتاح منع التكرار | `idem_key` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | معكوس بـ | `reversed_by` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | عكس عن | `reversal_of` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | درجة الأثر | `impact_grade` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | المرفق | `attachment` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | الكيان | `entity` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | مرجع التفويض | `authority_ref` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | تاريخ الاعتماد | `approved_at` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | تاريخ الإنشاء | `created_at` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | المرجع الأب | `parent_ref` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | المُنشئ — الاسم والصفة | `creator` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | الحالة | `status` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | مفتاح منع التكرار | `idem_key` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | معكوس بـ | `reversed_by` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | عكس عن | `reversal_of` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | درجة الأثر | `impact_grade` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | مركز التكلفة | `cost_center` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | العملة | `currency` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | الكيان | `entity` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | مرجع التفويض | `authority_ref` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | تاريخ الاعتماد | `approved_at` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | المرجع الأب | `parent_ref` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | المُنشئ — الاسم والصفة | `creator` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | المرفق | `attachment` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | مركز التكلفة | `cost_center` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | العملة | `currency` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | الكيان | `entity` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | مرجع التفويض | `authority_ref` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | تاريخ الاعتماد | `approved_at` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | تاريخ الإنشاء | `created_at` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | الحالة | `status` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | المرفق | `attachment` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | مركز التكلفة | `cost_center` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | العملة | `currency` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | الكيان | `entity` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | مرجع التفويض | `authority_ref` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | تاريخ الاعتماد | `approved_at` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | تاريخ الإنشاء | `created_at` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | المرجع الأب | `parent_ref` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | المعتمِد — الاسم والصفة | `approver` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | سجل الاطّلاع | `view_log` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | المرفق | `attachment` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | الكيان | `entity` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | مرجع التفويض | `authority_ref` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | تاريخ الاعتماد | `approved_at` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | تاريخ الإنشاء | `created_at` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | مفتاح منع التكرار | `idem_key` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | معكوس بـ | `reversed_by` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | عكس عن | `reversal_of` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | درجة الأثر | `impact_grade` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | المرفق | `attachment` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | مركز التكلفة | `cost_center` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | سعر الصرف | `fx_rate` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | الكيان | `entity` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | تاريخ الاعتماد | `approved_at` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | تاريخ الإنشاء | `created_at` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | المرجع الأب | `parent_ref` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | المعتمِد — الاسم والصفة | `approver` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | الحالة | `status` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | مفتاح منع التكرار | `idem_key` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | معكوس بـ | `reversed_by` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | عكس عن | `reversal_of` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | درجة الأثر | `impact_grade` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | المرفق | `attachment` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | مركز التكلفة | `cost_center` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | العملة | `currency` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | الكيان | `entity` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | مرجع التفويض | `authority_ref` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | تاريخ الاعتماد | `approved_at` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | تاريخ الإنشاء | `created_at` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | المرجع الأب | `parent_ref` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | الحالة | `status` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | مفتاح منع التكرار | `idem_key` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | معكوس بـ | `reversed_by` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | عكس عن | `reversal_of` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | درجة الأثر | `impact_grade` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | المرفق | `attachment` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | مركز التكلفة | `cost_center` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | الكيان | `entity` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | مرجع التفويض | `authority_ref` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | تاريخ الاعتماد | `approved_at` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | تاريخ الإنشاء | `created_at` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | المرجع الأب | `parent_ref` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | المُنشئ — الاسم والصفة | `creator` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | المعتمِد — الاسم والصفة | `approver` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | الحالة | `status` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | المرفق | `attachment` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | الكيان | `entity` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | مرجع التفويض | `authority_ref` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | تاريخ الاعتماد | `approved_at` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | المرجع الأب | `parent_ref` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | الحالة | `status` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | سجل الاطّلاع | `view_log` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | المرفق | `attachment` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | مركز التكلفة | `cost_center` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | الكيان | `entity` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | مرجع التفويض | `authority_ref` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | تاريخ الاعتماد | `approved_at` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | تاريخ الإنشاء | `created_at` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | المرجع الأب | `parent_ref` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | المُنشئ — الاسم والصفة | `creator` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | المعتمِد — الاسم والصفة | `approver` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | الحالة | `status` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | المرفق | `attachment` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | مركز التكلفة | `cost_center` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | العملة | `currency` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | الكيان | `entity` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | مرجع التفويض | `authority_ref` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | تاريخ الاعتماد | `approved_at` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | تاريخ الإنشاء | `created_at` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | المرجع الأب | `parent_ref` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | المرفق | `attachment` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | مركز التكلفة | `cost_center` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | العملة | `currency` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | الكيان | `entity` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | الحالة | `status` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | مركز التكلفة | `cost_center` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | الكيان | `entity` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | تاريخ الاعتماد | `approved_at` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | الحالة | `status` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | مركز التكلفة | `cost_center` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Clients/units_of_measure.php | وحدات القياس والتحويل (units_of_measure.php) | الكيان | `entity` |
| Clients/units_of_measure.php | وحدات القياس والتحويل (units_of_measure.php) | الحالة | `status` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | الكيان | `entity` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | مرجع التفويض | `authority_ref` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | تاريخ الاعتماد | `approved_at` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | تاريخ الإنشاء | `created_at` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | المُنشئ — الاسم والصفة | `creator` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | الحالة | `status` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | مفتاح منع التكرار | `idem_key` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | معكوس بـ | `reversed_by` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | عكس عن | `reversal_of` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | درجة الأثر | `impact_grade` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | مركز التكلفة | `cost_center` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | العملة | `currency` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | المستند المرفق | `attached_doc` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | الكيان | `entity` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | مركز التكلفة | `cost_center` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | العملة | `currency` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | الكيان | `entity` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | مرجع التفويض | `authority_ref` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | تاريخ الاعتماد | `approved_at` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | تاريخ الإنشاء | `created_at` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | المرجع الأب | `parent_ref` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | المرفق | `attachment` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | مركز التكلفة | `cost_center` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | الكيان | `entity` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | تاريخ الإنشاء | `created_at` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | المرجع الأب | `parent_ref` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | المُنشئ — الاسم والصفة | `creator` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | المعتمِد — الاسم والصفة | `approver` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | المرفق | `attachment` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | مركز التكلفة | `cost_center` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | الكيان | `entity` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | مرجع التفويض | `authority_ref` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | تاريخ الاعتماد | `approved_at` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | تاريخ الإنشاء | `created_at` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | المرجع الأب | `parent_ref` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | مفتاح منع التكرار | `idem_key` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | معكوس بـ | `reversed_by` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | عكس عن | `reversal_of` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | درجة الأثر | `impact_grade` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | المرفق | `attachment` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | مركز التكلفة | `cost_center` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | الكيان | `entity` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | مرجع التفويض | `authority_ref` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | تاريخ الاعتماد | `approved_at` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | تاريخ الإنشاء | `created_at` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | المُنشئ — الاسم والصفة | `creator` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | مفتاح منع التكرار | `idem_key` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | معكوس بـ | `reversed_by` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | عكس عن | `reversal_of` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | درجة الأثر | `impact_grade` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | المرفق | `attachment` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | مركز التكلفة | `cost_center` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | العملة | `currency` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | الكيان | `entity` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | تاريخ الاعتماد | `approved_at` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | تاريخ الإنشاء | `created_at` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | المرجع الأب | `parent_ref` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | المُنشئ — الاسم والصفة | `creator` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | المعتمِد — الاسم والصفة | `approver` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | المرفق | `attachment` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | مركز التكلفة | `cost_center` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | الكيان | `entity` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | مرجع التفويض | `authority_ref` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | تاريخ الاعتماد | `approved_at` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | تاريخ الإنشاء | `created_at` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | المرجع الأب | `parent_ref` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | المُنشئ — الاسم والصفة | `creator` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | مفتاح منع التكرار | `idem_key` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | معكوس بـ | `reversed_by` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | عكس عن | `reversal_of` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | درجة الأثر | `impact_grade` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | المرفق | `attachment` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | مركز التكلفة | `cost_center` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | الكيان | `entity` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | مرجع التفويض | `authority_ref` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | تاريخ الاعتماد | `approved_at` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | تاريخ الإنشاء | `created_at` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | المرجع الأب | `parent_ref` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | المُنشئ — الاسم والصفة | `creator` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | المعتمِد — الاسم والصفة | `approver` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | مفتاح منع التكرار | `idem_key` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | معكوس بـ | `reversed_by` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | عكس عن | `reversal_of` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | درجة الأثر | `impact_grade` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | المرفق | `attachment` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | مركز التكلفة | `cost_center` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | العملة | `currency` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | الكيان | `entity` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | مرجع التفويض | `authority_ref` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | تاريخ الاعتماد | `approved_at` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | تاريخ الإنشاء | `created_at` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | المُنشئ — الاسم والصفة | `creator` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | المرفق | `attachment` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | مركز التكلفة | `cost_center` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | العملة | `currency` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | الكيان | `entity` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | مرجع التفويض | `authority_ref` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | تاريخ الاعتماد | `approved_at` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | تاريخ الإنشاء | `created_at` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | المرجع الأب | `parent_ref` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | المُنشئ — الاسم والصفة | `creator` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | المعتمِد — الاسم والصفة | `approver` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | سجل الاطّلاع | `view_log` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | المرفق | `attachment` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | العملة | `currency` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | الكيان | `entity` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | تاريخ الاعتماد | `approved_at` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | تاريخ الإنشاء | `created_at` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | المرجع الأب | `parent_ref` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | المُنشئ — الاسم والصفة | `creator` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | المعتمِد — الاسم والصفة | `approver` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | الحالة | `status` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | سجل الاطّلاع | `view_log` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | المرفق | `attachment` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | الكيان | `entity` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | مرجع التفويض | `authority_ref` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | تاريخ الاعتماد | `approved_at` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | المرجع الأب | `parent_ref` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | مفتاح منع التكرار | `idem_key` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | معكوس بـ | `reversed_by` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | عكس عن | `reversal_of` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | درجة الأثر | `impact_grade` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | المرفق | `attachment` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | مركز التكلفة | `cost_center` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | العملة | `currency` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | سعر الصرف | `fx_rate` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | الكيان | `entity` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | مرجع التفويض | `authority_ref` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | تاريخ الاعتماد | `approved_at` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | تاريخ الإنشاء | `created_at` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | المرجع الأب | `parent_ref` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | الحالة | `status` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | سجل الاطّلاع | `view_log` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | المرفق | `attachment` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | مركز التكلفة | `cost_center` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | العملة | `currency` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | الكيان | `entity` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | مرجع التفويض | `authority_ref` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | تاريخ الاعتماد | `approved_at` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | تاريخ الإنشاء | `created_at` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | المرجع الأب | `parent_ref` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | المُنشئ — الاسم والصفة | `creator` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | مفتاح منع التكرار | `idem_key` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | معكوس بـ | `reversed_by` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | عكس عن | `reversal_of` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | درجة الأثر | `impact_grade` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | المرفق | `attachment` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | مركز التكلفة | `cost_center` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | العملة | `currency` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | الكيان | `entity` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | مرجع التفويض | `authority_ref` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | تاريخ الاعتماد | `approved_at` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | تاريخ الإنشاء | `created_at` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | المعتمِد — الاسم والصفة | `approver` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | مفتاح منع التكرار | `idem_key` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | معكوس بـ | `reversed_by` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | عكس عن | `reversal_of` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | درجة الأثر | `impact_grade` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | المرفق | `attachment` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | مركز التكلفة | `cost_center` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | العملة | `currency` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | الكيان | `entity` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | مرجع التفويض | `authority_ref` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | تاريخ الاعتماد | `approved_at` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | تاريخ الإنشاء | `created_at` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | المرجع الأب | `parent_ref` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | المُنشئ — الاسم والصفة | `creator` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | المرفق | `attachment` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | الكيان | `entity` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | مرجع التفويض | `authority_ref` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | تاريخ الاعتماد | `approved_at` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | تاريخ الإنشاء | `created_at` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | المرجع الأب | `parent_ref` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | المُنشئ — الاسم والصفة | `creator` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | مفتاح منع التكرار | `idem_key` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | معكوس بـ | `reversed_by` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | عكس عن | `reversal_of` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | درجة الأثر | `impact_grade` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | المرفق | `attachment` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | مركز التكلفة | `cost_center` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | العملة | `currency` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | الكيان | `entity` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | مرجع التفويض | `authority_ref` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | تاريخ الاعتماد | `approved_at` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | تاريخ الإنشاء | `created_at` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | المرجع الأب | `parent_ref` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | المُنشئ — الاسم والصفة | `creator` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | الحالة | `status` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | المرفق | `attachment` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | مركز التكلفة | `cost_center` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | العملة | `currency` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | الكيان | `entity` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | مرجع التفويض | `authority_ref` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | تاريخ الاعتماد | `approved_at` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | تاريخ الإنشاء | `created_at` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | المرجع الأب | `parent_ref` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | المعتمِد — الاسم والصفة | `approver` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | مفتاح منع التكرار | `idem_key` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | معكوس بـ | `reversed_by` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | عكس عن | `reversal_of` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | درجة الأثر | `impact_grade` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | المرفق | `attachment` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | مركز التكلفة | `cost_center` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | الكيان | `entity` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | مرجع التفويض | `authority_ref` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | تاريخ الاعتماد | `approved_at` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | تاريخ الإنشاء | `created_at` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | المرجع الأب | `parent_ref` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | المعتمِد — الاسم والصفة | `approver` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | الحالة | `status` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | المرفق | `attachment` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | مركز التكلفة | `cost_center` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | العملة | `currency` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | الكيان | `entity` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | مرجع التفويض | `authority_ref` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | تاريخ الاعتماد | `approved_at` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | تاريخ الإنشاء | `created_at` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | المُنشئ — الاسم والصفة | `creator` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | المعتمِد — الاسم والصفة | `approver` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | مفتاح منع التكرار | `idem_key` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | معكوس بـ | `reversed_by` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | عكس عن | `reversal_of` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | درجة الأثر | `impact_grade` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | المرفق | `attachment` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | مركز التكلفة | `cost_center` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | العملة | `currency` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | سعر الصرف | `fx_rate` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | الكيان | `entity` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | مرجع التفويض | `authority_ref` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | تاريخ الاعتماد | `approved_at` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | تاريخ الإنشاء | `created_at` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | المرجع الأب | `parent_ref` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | الحالة | `status` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | مفتاح منع التكرار | `idem_key` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | معكوس بـ | `reversed_by` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | عكس عن | `reversal_of` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | درجة الأثر | `impact_grade` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | مركز التكلفة | `cost_center` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | العملة | `currency` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | الكيان | `entity` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | الكيان | `entity` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | مرجع التفويض | `authority_ref` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | تاريخ الاعتماد | `approved_at` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | تاريخ الإنشاء | `created_at` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | المرجع الأب | `parent_ref` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | المُنشئ — الاسم والصفة | `creator` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | مفتاح منع التكرار | `idem_key` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | معكوس بـ | `reversed_by` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | عكس عن | `reversal_of` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | درجة الأثر | `impact_grade` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | سجل الاطّلاع | `view_log` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | المرفق | `attachment` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | الكيان | `entity` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | مرجع التفويض | `authority_ref` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | تاريخ الاعتماد | `approved_at` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | تاريخ الإنشاء | `created_at` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | المُنشئ — الاسم والصفة | `creator` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | مفتاح منع التكرار | `idem_key` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | معكوس بـ | `reversed_by` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | عكس عن | `reversal_of` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | درجة الأثر | `impact_grade` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | سجل الاطّلاع | `view_log` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | المرفق | `attachment` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | مركز التكلفة | `cost_center` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Employees/employees.php | سجل الموظفين (employees.php) | الكيان | `entity` |
| Employees/employees.php | سجل الموظفين (employees.php) | مرجع التفويض | `authority_ref` |
| Employees/employees.php | سجل الموظفين (employees.php) | تاريخ الاعتماد | `approved_at` |
| Employees/employees.php | سجل الموظفين (employees.php) | تاريخ الإنشاء | `created_at` |
| Employees/employees.php | سجل الموظفين (employees.php) | المرجع الأب | `parent_ref` |
| Employees/employees.php | سجل الموظفين (employees.php) | المعتمِد — الاسم والصفة | `approver` |
| Employees/employees.php | سجل الموظفين (employees.php) | سجل الاطّلاع | `view_log` |
| Employees/employees.php | سجل الموظفين (employees.php) | المرفق | `attachment` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | الكيان | `entity` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | مرجع التفويض | `authority_ref` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | تاريخ الاعتماد | `approved_at` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | تاريخ الإنشاء | `created_at` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | المرجع الأب | `parent_ref` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | المُنشئ — الاسم والصفة | `creator` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | مفتاح منع التكرار | `idem_key` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | معكوس بـ | `reversed_by` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | عكس عن | `reversal_of` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | درجة الأثر | `impact_grade` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | سجل الاطّلاع | `view_log` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | المرفق | `attachment` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | مركز التكلفة | `cost_center` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | سعر الصرف ومصدره | `fx_rate_source` |
| main/users.php | حسابات المستخدمين (users.php) | الكيان | `entity` |
| main/users.php | حسابات المستخدمين (users.php) | مرجع التفويض | `authority_ref` |
| main/users.php | حسابات المستخدمين (users.php) | تاريخ الاعتماد | `approved_at` |
| main/users.php | حسابات المستخدمين (users.php) | تاريخ الإنشاء | `created_at` |
| main/users.php | حسابات المستخدمين (users.php) | المرجع الأب | `parent_ref` |
| main/users.php | حسابات المستخدمين (users.php) | سجل الاطّلاع | `view_log` |
| main/users.php | حسابات المستخدمين (users.php) | المرفق | `attachment` |
| main/users.php | حسابات المستخدمين (users.php) | العملة | `currency` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | الكيان | `entity` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | مرجع التفويض | `authority_ref` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | تاريخ الاعتماد | `approved_at` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | تاريخ الإنشاء | `created_at` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | المرجع الأب | `parent_ref` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | المُنشئ — الاسم والصفة | `creator` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | مفتاح منع التكرار | `idem_key` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | معكوس بـ | `reversed_by` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | عكس عن | `reversal_of` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | درجة الأثر | `impact_grade` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | المرفق | `attachment` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | مركز التكلفة | `cost_center` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | العملة | `currency` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | الكيان | `entity` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | الكيان | `entity` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | مرجع التفويض | `authority_ref` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | تاريخ الاعتماد | `approved_at` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | تاريخ الإنشاء | `created_at` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | المرجع الأب | `parent_ref` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | مفتاح منع التكرار | `idem_key` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | معكوس بـ | `reversed_by` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | عكس عن | `reversal_of` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | درجة الأثر | `impact_grade` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | سجل الاطّلاع | `view_log` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | المرفق | `attachment` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | مركز التكلفة | `cost_center` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | العملة | `currency` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | الكيان | `entity` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | مرجع التفويض | `authority_ref` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | تاريخ الاعتماد | `approved_at` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | تاريخ الإنشاء | `created_at` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | المرجع الأب | `parent_ref` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | مفتاح منع التكرار | `idem_key` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | معكوس بـ | `reversed_by` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | عكس عن | `reversal_of` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | درجة الأثر | `impact_grade` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | سجل الاطّلاع | `view_log` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | المرفق | `attachment` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | مركز التكلفة | `cost_center` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | العملة | `currency` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الكيان | `entity` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | مرجع التفويض | `authority_ref` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | تاريخ الاعتماد | `approved_at` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | تاريخ الإنشاء | `created_at` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | المرجع الأب | `parent_ref` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | المعتمِد — الاسم والصفة | `approver` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الحالة | `status` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | المرفق | `attachment` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | الكيان | `entity` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | مرجع التفويض | `authority_ref` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | تاريخ الاعتماد | `approved_at` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | تاريخ الإنشاء | `created_at` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | المرجع الأب | `parent_ref` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | المعتمِد — الاسم والصفة | `approver` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | مفتاح منع التكرار | `idem_key` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | معكوس بـ | `reversed_by` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | عكس عن | `reversal_of` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | درجة الأثر | `impact_grade` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | المرفق | `attachment` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | مركز التكلفة | `cost_center` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | العملة | `currency` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | سعر الصرف | `fx_rate` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | الكيان | `entity` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | مرجع التفويض | `authority_ref` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | تاريخ الاعتماد | `approved_at` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | تاريخ الإنشاء | `created_at` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | المرجع الأب | `parent_ref` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | الحالة | `status` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | مفتاح منع التكرار | `idem_key` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | معكوس بـ | `reversed_by` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | عكس عن | `reversal_of` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | درجة الأثر | `impact_grade` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | سجل الاطّلاع | `view_log` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | المرفق | `attachment` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | مركز التكلفة | `cost_center` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | العملة | `currency` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | الكيان | `entity` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | مرجع التفويض | `authority_ref` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | تاريخ الاعتماد | `approved_at` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | تاريخ الإنشاء | `created_at` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | المُنشئ — الاسم والصفة | `creator` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | المعتمِد — الاسم والصفة | `approver` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | الحالة | `status` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | مفتاح منع التكرار | `idem_key` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | معكوس بـ | `reversed_by` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | عكس عن | `reversal_of` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | درجة الأثر | `impact_grade` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | المرفق | `attachment` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | مركز التكلفة | `cost_center` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | العملة | `currency` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | سعر الصرف | `fx_rate` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | الكيان | `entity` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | مركز التكلفة | `cost_center` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | الكيان | `entity` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | مرجع التفويض | `authority_ref` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | تاريخ الاعتماد | `approved_at` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | تاريخ الإنشاء | `created_at` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | المُنشئ — الاسم والصفة | `creator` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | المرفق | `attachment` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | مركز التكلفة | `cost_center` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | الكيان | `entity` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | الحالة | `status` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | مركز التكلفة | `cost_center` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | العملة | `currency` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | الكيان | `entity` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | مرجع التفويض | `authority_ref` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | تاريخ الاعتماد | `approved_at` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | تاريخ الإنشاء | `created_at` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | المعتمِد — الاسم والصفة | `approver` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | مفتاح منع التكرار | `idem_key` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | معكوس بـ | `reversed_by` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | عكس عن | `reversal_of` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | درجة الأثر | `impact_grade` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | سجل الاطّلاع | `view_log` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | المرفق | `attachment` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | العملة | `currency` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | سعر الصرف | `fx_rate` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | الكيان | `entity` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | معكوس بـ | `reversed_by` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | عكس عن | `reversal_of` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | درجة الأثر | `impact_grade` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | العملة | `currency` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | الكيان | `entity` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | مرجع التفويض | `authority_ref` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | تاريخ الاعتماد | `approved_at` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | تاريخ الإنشاء | `created_at` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | المرجع الأب | `parent_ref` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | المُنشئ — الاسم والصفة | `creator` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | سجل الاطّلاع | `view_log` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | المرفق | `attachment` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | الكيان | `entity` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | مرجع التفويض | `authority_ref` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | تاريخ الاعتماد | `approved_at` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | تاريخ الإنشاء | `created_at` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | المُنشئ — الاسم والصفة | `creator` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | مفتاح منع التكرار | `idem_key` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | معكوس بـ | `reversed_by` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | عكس عن | `reversal_of` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | المرفق | `attachment` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | مركز التكلفة | `cost_center` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | العملة | `currency` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | الكيان | `entity` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | الحالة | `status` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | مركز التكلفة | `cost_center` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | العملة | `currency` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | الكيان | `entity` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | مركز التكلفة | `cost_center` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | سعر الصرف ومصدره | `fx_rate_source` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | الكيان | `entity` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | مركز التكلفة | `cost_center` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | العملة | `currency` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | سعر الصرف | `fx_rate` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | الكيان | `entity` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | مرجع التفويض | `authority_ref` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | تاريخ الاعتماد | `approved_at` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | تاريخ الإنشاء | `created_at` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | المرجع الأب | `parent_ref` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | المُنشئ — الاسم والصفة | `creator` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | المرفق | `attachment` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | مركز التكلفة | `cost_center` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | سعر الصرف ومصدره | `fx_rate_source` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | العملة | `currency` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | الكيان | `entity` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | مرجع التفويض | `authority_ref` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | تاريخ الاعتماد | `approved_at` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | تاريخ الإنشاء | `created_at` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | المرجع الأب | `parent_ref` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | المُنشئ — الاسم والصفة | `creator` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | المعتمِد — الاسم والصفة | `approver` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | سجل الاطّلاع | `view_log` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | المرفق | `attachment` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | مركز التكلفة | `cost_center` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | العملة | `currency` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | الكيان | `entity` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | مرجع التفويض | `authority_ref` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | تاريخ الاعتماد | `approved_at` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | تاريخ الإنشاء | `created_at` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | المرجع الأب | `parent_ref` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | المُنشئ — الاسم والصفة | `creator` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | الحالة | `status` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | مفتاح منع التكرار | `idem_key` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | معكوس بـ | `reversed_by` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | عكس عن | `reversal_of` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | درجة الأثر | `impact_grade` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | سجل الاطّلاع | `view_log` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | المرفق | `attachment` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | مركز التكلفة | `cost_center` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | الكيان | `entity` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | مرجع التفويض | `authority_ref` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | تاريخ الاعتماد | `approved_at` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | تاريخ الإنشاء | `created_at` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | المرجع الأب | `parent_ref` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | المُنشئ — الاسم والصفة | `creator` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | المعتمِد — الاسم والصفة | `approver` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | مفتاح منع التكرار | `idem_key` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | معكوس بـ | `reversed_by` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | عكس عن | `reversal_of` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | درجة الأثر | `impact_grade` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | سجل الاطّلاع | `view_log` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | المرفق | `attachment` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | مركز التكلفة | `cost_center` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | العملة | `currency` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | الكيان | `entity` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | مرجع التفويض | `authority_ref` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | تاريخ الاعتماد | `approved_at` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | تاريخ الإنشاء | `created_at` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المرجع الأب | `parent_ref` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المُنشئ — الاسم والصفة | `creator` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المعتمِد — الاسم والصفة | `approver` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | سجل الاطّلاع | `view_log` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المرفق | `attachment` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | مركز التكلفة | `cost_center` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | العملة | `currency` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | الكيان | `entity` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | الحالة | `status` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | الكيان | `entity` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | مرجع التفويض | `authority_ref` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | تاريخ الاعتماد | `approved_at` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | تاريخ الإنشاء | `created_at` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | المرجع الأب | `parent_ref` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | المعتمِد — الاسم والصفة | `approver` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | المرفق | `attachment` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | الكيان | `entity` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | الكيان | `entity` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | مرجع التفويض | `authority_ref` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | تاريخ الاعتماد | `approved_at` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | تاريخ الإنشاء | `created_at` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | المرجع الأب | `parent_ref` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | المُنشئ — الاسم والصفة | `creator` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | المعتمِد — الاسم والصفة | `approver` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | المرفق | `attachment` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | الكيان | `entity` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | مرجع التفويض | `authority_ref` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | تاريخ الاعتماد | `approved_at` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | المرجع الأب | `parent_ref` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | المُنشئ — الاسم والصفة | `creator` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | المرفق | `attachment` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | العملة | `currency` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | الكيان | `entity` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | مرجع التفويض | `authority_ref` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | تاريخ الاعتماد | `approved_at` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | تاريخ الإنشاء | `created_at` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | الحالة | `status` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | المرفق | `attachment` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | العملة | `currency` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | الكيان | `entity` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | مرجع التفويض | `authority_ref` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | تاريخ الاعتماد | `approved_at` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | تاريخ الإنشاء | `created_at` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | المرجع الأب | `parent_ref` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | المُنشئ — الاسم والصفة | `creator` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | المعتمِد — الاسم والصفة | `approver` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | الحالة | `status` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | المرفق | `attachment` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | الكيان | `entity` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | الحالة | `status` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | الكيان | `entity` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | الكيان | `entity` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | مرجع التفويض | `authority_ref` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | تاريخ الاعتماد | `approved_at` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | تاريخ الإنشاء | `created_at` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | المرجع الأب | `parent_ref` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | المعتمِد — الاسم والصفة | `approver` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | الحالة | `status` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | المرفق | `attachment` |
| main/profile.php | ملفي الشخصي (profile.php) | الكيان | `entity` |
| main/profile.php | ملفي الشخصي (profile.php) | الحالة | `status` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | الكيان | `entity` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | مرجع التفويض | `authority_ref` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | تاريخ الاعتماد | `approved_at` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | تاريخ الإنشاء | `created_at` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | المرجع الأب | `parent_ref` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | المُنشئ — الاسم والصفة | `creator` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | سجل الاطّلاع | `view_log` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | المرفق | `attachment` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | مسار التوزيع | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | الفئة التشغيلية | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | بلد الصنع | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | حالة الرقم التسلسلي | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | رقم الشاسيه | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | رقم الموتور | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | رقم اللوحة | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | المصدر | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | المالك القانوني | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | تاريخ الدخول | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | العدّاد الافتتاحي | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | العدّاد الحالي | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | وحدة العدّاد | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | تكلفة الشراء | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | العمر الإنتاجي بالساعات | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | معدل الإهلاك بالساعة | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | قيمة الخردة | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | الإهلاك المتراكم | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | القيمة الدفترية | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | الساعات المتراكمة بالسجل | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | الساعات بالتشغيل | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | فرق الساعات | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | الممول | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | نموذج التمويل | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | حصص الملكية | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | الموقع الحالي | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | الحالة الأسطولية | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | الجاهزية الفنية | `fn` |
| Equipments/equipments.php | سجل المعدات (equipments.php) | سجّله | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | رقم التكليف | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | كود المشغّل | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | كود المعدة | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | الوحدة التعاقدية | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | الموقع | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | من تاريخ | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | إلى تاريخ | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | عدد الأيام | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | سبب الإنهاء | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | المشغّل البديل | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | فحص التأهيل | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | فحص الرخصة | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | كلّفه | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) | وافق عليه | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | رقم التوقف | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | الموقع | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | كود المعدة | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | الوحدة التعاقدية | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | من الساعة | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | إلى الساعة | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | المدة بالساعات | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | تصنيف التوقف | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | السبب التفصيلي | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | الطرف المتحمل | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | مرجع بند العقد | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | أمر الصيانة المرتبط | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | أثر الفوترة | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | أثر استحقاق المورد | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | سجّله | `fn` |
| Operations/stops_unattributed.php | التوقفات وتحديد المتحمل (stops.php) | أسنده | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | رقم الطلب | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | تاريخ الورود | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | نوع المستند | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | المستند | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | القيمة | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | سقفي | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | داخل سقفي؟ | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | الحلقة في السلسلة | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | المهلة | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | المتبقي | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | قراري | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | سبب القرار | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | تاريخ القرار | `fn` |
| Finance/approvals_inbox.php | صندوق ما ينتظر اعتمادي (approvals_inbox.php) | مرجع تفويضي | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | رقم الأمر | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | تاريخ الإصدار | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | مرجع الطلب | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | العناصر المنقولة | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | إلى | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | المسافة كم | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | وسيلة النقل | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | الناقل | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | رقم التصريح | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | تاريخ المغادرة | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | تاريخ الوصول | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | مؤكِّد الوصول | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | مستند إثبات الوصول | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | قاعدة التحميل | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | إجمالي التكلفة | `fn` |
| Transport/transfer_orders_list.php | أوامر الترحيل (transfer_ord.php) | أصدره | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | نموذج العمل | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | رمز النموذج | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | العقد الأساسي | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | رقم التجديد | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | العميل | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | تاريخ البدء | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | تاريخ الانتهاء | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | المدة بالأشهر | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | نموذج التسعير | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | نوع القيمة | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | القيمة الموقَّعة | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | عملة الفوترة | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | عملة التحصيل | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | دورة التسوية | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | مهلة السداد | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | نسبة المقدم | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | شامل الضريبة؟ | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | حالة خط الأساس | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | ملاحظات حرجة مفتوحة | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | جاهزية الاعتماد القانوني | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | وقّعه عنّا | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | وقّعه العميل | `fn` |
| Contracts/contracts.php | عقود العملاء (contracts.php) | نسخة القاعدة المستعملة | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | رقم البند | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | العقد | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | نموذج العمل | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | وحدة العمل | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | الوحدات الاحتياطية | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | عدد الورديات المتفق عليها | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | وحدات الوردية الواحدة | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | الساعات الشهرية للوحدة | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | إجمالي ساعات العقد | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | الحد الأدنى المضمون | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | الساعات المعرَّضة للخطر | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | قاعدة الزيادة | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | سعر الوحدة | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | تاريخ السريان | `fn` |
| Contracts/contract_coverage.php | التزام العقد بنوع المعدة (coverage.php) | نسخة القاعدة المستعملة | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | رقم الحصة | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | العقد العميل | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | نموذج العمل | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | وحدة العمل | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | نوع المعدة | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | الحصة المخصَّصة (وحدات) | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | حصة المبيعات لهذا النوع | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | مجموع حصص الموردين لهذا النوع | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | المتبقي من حصة المبيعات | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | معدات أساسية | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | معدات احتياطية | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | الموزَّع على المعدات | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | غير الموزَّع | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | الساعات المشتقة من عقد العميل (محسوبة) | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | تاريخ السريان | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | تاريخ الانتهاء | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | حالة الحصة | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | مرجع العقد أو الملحق | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | مرجع الترسية | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | خصّصها | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) | نسخة القاعدة المستعملة | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | تاريخ الطلب | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | الإدارة الطالبة | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | نوع المستفيد | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | المستفيد | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | الحساب البنكي | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | البنك | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | مصدر التمويل المستخدم | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | المرجع | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | وصف الصرف | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | المعادل بعملة الدفاتر | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | بند الموازنة | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | المتاح قبل | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | قدّمه | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | اعتماد الإدارة | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | الاعتماد المالي | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | اعتماد الإدارة العامة | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | تاريخ السداد | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | رقم سند الصرف | `fn` |
| Finance/payments_fin.php | طلبات الدفع والسداد (payments.php) | نسخة القاعدة المستعملة | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | الاسم القانوني | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | نوع المورد | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | طبيعة التعاقد | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | عدد عقود الوساطة | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | عدد عقود الملكية | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | نسبة الوساطة من عقوده | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | السجل التجاري | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | الرقم الضريبي | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | المالك الحقيقي للمعدات | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | هاتف المالك | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | الشخص المفوَّض بالتوقيع | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | هاتفه | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | مستند تفويضه | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | علاقة المورد بالمالك | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | العملات المتعامل بها | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | تصنيف العلاقة | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | وثائق التأهيل | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | تاريخ انتهاء أقدم وثيقة | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | الحساب البنكي | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | حالة التحقق من الحساب | `fn` |
| Suppliers/suppliers.php | سجل الموردين (suppliers.php) | سجّله | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | رقم الأمر | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | تاريخ الإصدار | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | الرقم الضريبي للمورد | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | مرجع الترسية | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | الأصناف | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | رقم قطعة المصنع | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | الكمية | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | سعر الوحدة | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | الإجمالي قبل الضريبة | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | نسبة الضريبة | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | قيمة الضريبة | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | وقت الدفع | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | نوع الاستلام | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | الوجهة | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | تاريخ التوريد المتفق | `fn` |
| Procurement/orders_proc.php | أوامر الشراء (po.php) | أصدره | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | رقم المحضر | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | تاريخ الجرد | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | المخزن | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | أسلوب الجرد | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | الفترة | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | اسم الصنف | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | الرصيد الدفتري | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | العدّ الفعلي | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | الفرق | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | نسبة الفرق | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | قيمة الفرق | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | رقم قيد التسوية | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | عدّه | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | راجعه | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | اعتمده | `fn` |
| Procurement/wh_count.php | الجرد ومعالجة الفروقات (wh_count.php) | نسخة القاعدة المستعملة | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | تاريخ البدء | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | تاريخ الانتهاء | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | نموذج التعاقد | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | وحدة التعاقد | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | نوع المعدة | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | عدد الوحدات المتعاقد عليها | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | عدد الورديات المتفق عليها | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | وحدات الوردية الواحدة | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | الوحدات الشهرية الملزمة | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | الساعات الشهرية للوحدة | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | سعر الساعة | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | نسبة الجاهزية الدنيا | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | مهلة الإحلال | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | غرامة العجز | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | دورية التسوية | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | مهلة السداد | `fn` |
| Suppliers/supplierscontracts.php | عقود الموردين (supplier_contracts.php) | نسخة القاعدة المستعملة | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | العقد | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | الوحدة | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | الساعات المتعاقدة | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | الساعات المنفَّذة | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | العجز | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | التعطل المحمَّل على المورد | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | ساعات مخصومة بالتسوية | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | سعر الساعة | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | الاستحقاق قبل التسويات | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | تحميلات على المورد | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | جزاءات | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | حوافز | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | صافي المستحق | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | أعدّها | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | اعتمدها | `fn` |
| Suppliers/settlements.php | تسويات ومستحقات الموردين (supplier_settle.php) | نسخة القاعدة المستعملة | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | رقم السلفة | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | العقد | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | تاريخ الطلب | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | سبب السلفة | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | نسبة الاستقطاع من التسوية | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | عدد أشهر الاستقطاع | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | المستقطَع حتى تاريخه | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | المتبقي | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | الضمان المقدَّم | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | اعتماد الموردين | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | الاعتماد المالي | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | تاريخ الصرف | `fn` |
| Suppliers/supplier_advances.php | سلفيات الموردين (supplier_advances.php) | نسخة القاعدة المستعملة | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | رقم المحضر | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | تاريخ الإنهاء | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | سبب الإنهاء | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | إجمالي المستحقات | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | التحميلات علينا | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | التحميلات على المورد | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | الجزاءات | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | السلف غير المسدَّدة | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | الصافي المستحق | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | حالة الكفالة | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | المعدات المُخرَجة | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | تاريخ إخلاء المعدات | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | اعتماد الموردين | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | الاعتماد المالي | `fn` |
| Suppliers/supplier_closure.php | تصفية إنهاء عقد مورد (supplier_closure.php) | تاريخ السداد النهائي | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | رقم الطلب | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | تاريخ الطلب | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | الإدارة الطالبة | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | المسمى المطلوب | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | عدد الشواغر | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | المؤهل المطلوب | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | الخبرة المطلوبة | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | الموقع | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | نوع العقد | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | الأجر المقترح | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | اعتماد الموارد | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | اعتماد المالية | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | المرشح المختار | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | تاريخ العرض | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | تاريخ القبول | `fn` |
| Workforce/recruitment_pipeline.php | التوظيف من الشاغر إلى المباشرة (recruitment.php) | تاريخ المباشرة | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | رقم الأمر | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | من الموقع | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | إلى الموقع | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | نوع التنقل | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | سبب التنقل | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | تاريخ المغادرة | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | تاريخ الوصول | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | رحلة الترحيل | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | التخصيص السابق | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | التخصيص الجديد | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | أثر على الأجر | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | أثر على البدلات | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | السكن الجديد | `fn` |
| Workforce/worker_movement.php | تنقلات العاملين (worker_movement.php) | أصدره | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | الفترة | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | العقد | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | الوحدة | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | الواقعة المرجعية | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | حكم العميل | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | قيمة إيراد العميل | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | حكم المورد | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | قيمة استحقاق المورد | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | حكم المشغّل | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | قيمة أجر المشغّل | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | تاريخ اكتمال السلسلة | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | رقم الحدث | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | رقم القيد | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | ولّده | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | اعتمده | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) | نسخة القاعدة المستعملة | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | رقم الاقتراح | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | كود المشغّل | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | الشهر | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | سبب الخصم | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | المستند المؤيد | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | أيام أو ساعات | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | معادلة الاحتساب | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | قيمة الخصم | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | نسبة من الصافي | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | اقترحه | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | راجعته الموارد | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | اعتماد الإدارة | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | الاعتماد المالي | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | المسيّر المرحَّل إليه | `fn` |
| Workforce/proposed_deductions.php | خصومات المشغّلين المقترحة (op_deduct.php) | نسخة القاعدة المستعملة | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | رقم القيد | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | طريقة الإهلاك | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | القاعدة القابلة للإهلاك | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | العمر الإنتاجي بالساعات | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | معدل الساعة | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | ساعات الفترة | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | إهلاك الفترة | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | المتراكم قبل | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | المتراكم بعد | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | حصة المالك | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | قيمة الحصة | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | احتسبه | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | اعتمده | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | رقم القيد المحاسبي | `fn` |
| Finance/assets_fin.php | الإهلاك والقيمة الدفترية (depreciation.php) | نسخة القاعدة المستعملة | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | كود الموظف | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | فئة العقد | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | نوع العقد | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | عقد المورد المرتبط | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | تاريخ البدء | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | تاريخ الانتهاء المخطط | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | محفّزات الانتهاء الثلاثة | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | المحفّز الواقع | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | تاريخ الانتهاء الفعلي | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | فترة التجربة | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | الراتب الأساسي | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | بدل الطبيعة | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | إجمالي الأجر | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | دورية الصرف | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | نموذج الحافز | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | رصيد الإجازة السنوية | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | جهة التحمل | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | وقّعه عنّا | `fn` |
| Employees/employee_contracts.php | عقود الموظفين (emp_contracts.php) | وقّعه الموظف | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | رقم المحضر | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | تاريخ الالتحاق | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | تاريخ إنهاء الخدمة | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | سبب الإنهاء | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | مدة الخدمة | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | الراتب الأخير | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | رصيد الإجازات | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | بدل الإجازات | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | مستحقات أخرى | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | السلف غير المسدَّدة | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | خصومات | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | العهد غير المخلاة | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | إجمالي الخصومات | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | إخلاء طرف المخازن | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | إخلاء طرف الأسطول | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | تعطيل الحساب | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | اعتماد الموارد | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | الاعتماد المالي | `fn` |
| Workforce/final_settlement.php | تصفية إنهاء خدمة موظف (final_settlement.php) | تاريخ السداد | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | رقم المسيّر | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | الشهر | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | كود الموظف | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | الاسم | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | الأجر الأساسي | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | البدلات | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | الحافز | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | إجمالي الاستحقاق | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | خصم التأخير | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | جزاءات | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | قسط سلفة | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | تأمينات | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | الحساب البنكي | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | أعدّه | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | راجعه | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | اعتمده | `fn` |
| Workforce/payroll_runs.php | مسيّر الرواتب (payroll.php) | رقم أمر الدفع | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | رقم الحركة | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | التاريخ | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | الصندوق أو البنك | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | الوصف | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | المرجع | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | المبلغ | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | المعادل | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | الرصيد قبل | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | الرصيد بعد | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | المستفيد أو الدافع | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | أمين الخزينة | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | رقم القيد | `fn` |
| Finance/accounts_fin.php | الخزينة والصناديق (treasury.php) | نسخة القاعدة المستعملة | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | الفترة | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | المصدر | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | المرجع | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | اسم الحساب | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | بند القائمة المالية | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | المشروع | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | العقد | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | الوحدة التعاقدية | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | المعدة | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | المورد | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | نموذج العمل | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | المعادل مدين | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | المعادل دائن | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | الوصف | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | أعدّه | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | راجعه | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | نشره | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | تاريخ النشر | `fn` |
| Finance/journal_form_fin.php | القيود اليومية (journal.php) | نسخة القاعدة المستعملة | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | رقم المخصص | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | الفترة | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | المعدة أو الفئة | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | نوع المخصص | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | أساس التكوين | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | معادلة الاحتساب | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | المكوَّن للفترة | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | الرصيد المتراكم | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | المستخدَم للفترة | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | الرصيد المتاح | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | رقم القيد | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | مبرر التكوين | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | اعتمده | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | المرجع المحاسبي | `fn` |
| Finance/maintenance_provision_fin.php | مخصص الصيانة والعمرات (maint_provision.php) | نسخة القاعدة المستعملة | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | كود العملية | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | الممول | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | نموذج التمويل | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | تاريخ التوقيع | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | تاريخ النفاذ | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | تاريخ نهاية العملية | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | الأعيان المموَّلة | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | قيمة شراء العين | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | مصدر قيمة الشراء | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | رأس المال المموَّل | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | مصدر رأس المال | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | نسبة المقدم | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | قيمة المقدم | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | نسبة الأرباح | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | قيمة الأرباح | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | إجمالي السداد | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | المدة بالأشهر | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | عدد الأقساط | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | قيمة القسط | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | تاريخ أول قسط | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | تاريخ آخر قسط | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | اعتمده | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | وقّعه | `fn` |
| Financing/financing_board.php | عمليات التمويل (fin_ops.php) | نسخة القاعدة المستعملة | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | الموظف | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | الإدارة | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | المدى المختار | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | المهام المسنَدة | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | المنجَزة في المهلة | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | المتأخرة | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | نسبة الالتزام | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | ساعات العمل | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | الإنتاج المنسوب | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | نسبة الإنجاز من المستهدف | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | البلاغات المرفوعة | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | البلاغات المعالَجة | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | الترتيب في الإدارة | `fn` |
| Portal/my_achievement.php | إنجازي (my_achievement.php) | تاريخ التوليد | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | الموظف | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | الصفة | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | الإدارة | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | الموقع | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | المعدة المكلَّف عليها | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | المشغّل الآخر على المعدة | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | مهام مفتوحة | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | موافقات تنتظرني | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | طلباتي المعلَّقة | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | بلاغاتي المفتوحة | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | إنجاز الشهر | `fn` |
| Portal/my_portal.php | بوابتي (my_portal.php) | آخر دخول | `fn` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | تاريخ الطلب | `fn` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | مقدّم الطلب | `fn` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | الإدارة | `fn` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | الوصف | `fn` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | الجهة المعنية | `fn` |
| FinRequests/my_requests.php | طلباتي (my_requests.php) | سبب الرفض | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | السنة المالية | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | بند الموازنة | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | رقم الحساب | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | المعتمد السنوي | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | التوزيع الربعي 1 | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | الربع 2 | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | الربع 3 | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | الربع 4 | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | المصروف حتى تاريخه | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | الملتزم به | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | المتاح | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | نسبة الصرف | `fn` |
| Finance/budget_master.php | الموازنة العامة (budget_master.php) | اعتمدها | `fn` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | رقم العقد | `fn` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | الطرف الآخر | `fn` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | تاريخ التوقيع | `fn` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | تاريخ البدء | `fn` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | تاريخ الانتهاء | `fn` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | القيمة | `fn` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | الالتزام القائم | `fn` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | حالة الكفالة | `fn` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | المفوَّض بالتوقيع | `fn` |
| Workforce/contract_registry.php | سجل العقود الموحَّد (contract_registry.php) | الإدارة المالكة | `fn` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | رقم التكليف | `fn` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | الموظف | `fn` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | المسمى الأصلي | `fn` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | التكليف الجديد | `fn` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | سقف الاعتماد | `fn` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | بدل التكليف | `fn` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | أصدره | `fn` |
| admin/org_assignments.php | التكليفات التنظيمية (org_assignments.php) | مرجع القرار | `fn` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | رقم الخطر | `fn` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | تاريخ التسجيل | `fn` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | العقد | `fn` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | الاحتمال | `fn` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | الأثر المالي المقدَّر | `fn` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | خطة المعالجة | `fn` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | تاريخ المراجعة | `fn` |
| Clients/commercial_risks.php | المخاطر التجارية (commercial_risks.php) | حالة المعالجة | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | الفترة | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | نوع القائمة | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | البند | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | رصيد الفترة السابقة | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | التغير | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | نسبة التغير | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | المستوى | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | البند الأب | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | حالة الإقفال | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | أعدّها | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | راجعها | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | اعتمدها | `fn` |
| Finance/financial_statements_fin.php | القوائم المالية (fin_statements.php) | المراجع الخارجي | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | الفترة | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | الملتزم بمهلة الاستجابة | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | الملتزم بمهلة الإنجاز | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | بلا استجابة | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | متوسط زمن التعليق | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | إعادة الفتح | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | نسبة إعادة الفتح | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | المغلق بلا أثر | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | متوسط التأخير | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | المستهدف | `fn` |
| Tickets/ticket_dashboard.php | مؤشرات البلاغات (ticket_kpi.php) | الحكم | `fn` |
| Operations/operations_room.php | غرفة عمليات المواقع (ops_room.php) | المشروع | `fn` |
| Operations/operations_room.php | غرفة عمليات المواقع (ops_room.php) | المعدات المخططة | `fn` |
| Operations/operations_room.php | غرفة عمليات المواقع (ops_room.php) | المعدات العاملة | `fn` |
| Operations/operations_room.php | غرفة عمليات المواقع (ops_room.php) | المتوقفة | `fn` |
| Operations/operations_room.php | غرفة عمليات المواقع (ops_room.php) | نسبة التشغيل | `fn` |
| Operations/operations_room.php | غرفة عمليات المواقع (ops_room.php) | الإنتاج اليوم | `fn` |
| Operations/operations_room.php | غرفة عمليات المواقع (ops_room.php) | نسبة الإنجاز من الخطة | `fn` |
| Operations/operations_room.php | غرفة عمليات المواقع (ops_room.php) | وحدات لم تُرفع | `fn` |
| Operations/operations_room.php | غرفة عمليات المواقع (ops_room.php) | بلاغات مفتوحة | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | رقم الخطة | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | تاريخ التنفيذ | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | الموقع | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | المشروع | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | رقم العقد | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | كود المعدة | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | المشغّل الأساسي | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | المشغّل البديل | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | الساعات المخططة | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | الكمية المستهدفة | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | جبهة العمل | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | حالة الجاهزية | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | أعدّها | `fn` |
| Operations/daily_plan.php | خطة عمل الغد (daily_plan.php) | اعتمدها | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | رقم الإذن | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | المستفيد | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | نوع المستفيد | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | الغرض | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | المرافقون | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | المركبة | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | رقم اللوحة | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | من تاريخ | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | إلى تاريخ | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | جهة الإصدار | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | المصادقة الأمنية | `fn` |
| admin/org_permits.php | أذون الزيارة والمركبات الخارجية (org_permits.php) | أصدره | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | كود الموظف | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | الاسم الرباعي | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | رقم الهوية | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | تاريخ الميلاد | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | التبعية | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | مسار التوزيع | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | المورد التابع له | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | أساس حافز الإنتاج | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | قيمة الحافز للوحدة | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | الوردية المشتركة | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | الموضع فيها | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | رقم عقد العمل | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | الفحص الطبي | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | تاريخ انتهاء الفحص | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | الموقع الحالي | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | المعدة المكلَّف عليها | `fn` |
| Employees/equipment_operators.php | سجل المشغّلين (operators.php) | سجّله | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | رقم الأمر | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | إلى الموقع | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | نوع المورد | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | كود المورد | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | اسم المورد | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | سبب النقل | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | أمر الترحيل المرتبط | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | موافقة القوى | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | موافقة الموقع المصدر | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | أصدره | `fn` |
| Operations/distribution_space.php | تكليف المشغّل على المعدة (op_assign.php) · توزيع المعدات والمشغّلين (distribution.php) | تاريخ التنفيذ | `fn` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | كود المعدة أو المشغّل | `fn` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | الرقم أو المرجع | `fn` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | تاريخ الإصدار | `fn` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | المدة المتبقية بالأيام | `fn` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | وثيقة حرجة؟ | `fn` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | أثر الانتهاء | `fn` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | التنبيه قبل بالأيام | `fn` |
| Equipments/equipment_documents.php | وثائق المعدات والمشغّلين (equip_docs.php) | المسؤول | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | رقم الصف | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | الموقع | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | العقد | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | الوحدة التعاقدية | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | المشغّل | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | الفترة | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | من الساعة | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | إلى الساعة | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | ساعات التوقف | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | سبب التوقف | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | الطرف المتحمل | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | العدّاد أول | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | العدّاد آخر | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | المُدخِل | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | اعتماد الموقع | `fn` |
| Timesheet/timesheet.php | تسجيل التايم شيت والإنتاج (timesheet.php) | اعتماد التشغيل | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | تاريخ الفتح | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | الفئة | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | النوع | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | الأولوية | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | الموقع | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | المعدة | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | المبلِّغ | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | مهلة الاستجابة | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | تاريخ الاستلام | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | المتبقي | `fn` |
| Tickets/dept_inbox.php | بلاغات إدارتي (tickets_dept.php) | سبب التعليق | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | رقم الأمر | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | تاريخ الفتح | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | الموقع | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | التشخيص الفني | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | قراءة العدّاد | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | الأولوية | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | الفني المكلَّف | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | تكلفة القطع | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | الطرف المتحمل | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | ساعات التوقف | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | تاريخ الإقفال | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | نتيجة الفحص النهائي | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | أصدره | `fn` |
| Maintenance/orders.php | أوامر الصيانة (orders.php) | اعتمده | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | رقم التفتيش | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | كود المعدة | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | الموقع | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | قراءة العدّاد | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | عدد البنود المفحوصة | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | بنود سليمة | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | بنود ملاحظة | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | بنود حرجة | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | قرار التشغيل | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | سريان القرار حتى | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | التوصيات | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | الفني | `fn` |
| Maintenance/inspections.php | الفحص الفني اليومي (inspections.php) | اعتمده | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | رقم السند | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | المخزن | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | الجهة الطالبة | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | مرجع الطلب | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | أمر العمل أو المشروع | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | الوحدة التعاقدية | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | قراءة العدّاد | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | رقم الصنف | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | اسم الصنف | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | الكمية المصروفة | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | الوحدة | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | تكلفة الوحدة | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | صفة المستلِم | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | نوع الصرف | `fn` |
| Procurement/issue_proc.php | صرف مواد من المخزن (issue.php) | صرفه | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | اسم الصنف | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | الفئة | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | الوحدة | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | الموقع الداخلي | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | الرصيد المادي | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | المحجوز | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | قيد الشراء | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | قيد التحويل | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | في العهدة | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | حد إعادة الطلب | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | الحد الأقصى | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | قطعة حرجة | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | متوسط التكلفة | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | قيمة الرصيد | `fn` |
| Procurement/stock_proc.php | أرصدة المخزون بحالاتها (stock.php) | آخر حركة | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | رقم العهدة | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | سند الصرف | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | الصفة | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | الصنف | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | الكمية المصروفة | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | الكمية المستهلكة | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | الكمية المرتجعة | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | تاريخ الإرجاع | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | حالة المرتجع | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | قرار المرتجع | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | المتبقي في العهدة | `fn` |
| Procurement/receipt_custody_proc.php | العهد والمرتجعات (custody.php) | المسؤول | `fn` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | بند الموازنة | `fn` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | المعتمد للسنة | `fn` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | المعتمد للفترة | `fn` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | الملتزم به | `fn` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | المتاح | `fn` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | نسبة الصرف | `fn` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | سبب الانحراف | `fn` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | طلبات تعديل قائمة | `fn` |
| Finance/budget_dept.php | ميزانية إدارتي (budget_dept.php) | المسؤول | `fn` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | العقود المرتبطة | `fn` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | تاريخ البدء | `fn` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | تاريخ الانتهاء المخطط | `fn` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | المواقع | `fn` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | مدير المشروع | `fn` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | القيمة التعاقدية | `fn` |
| Projects/projects.php | المشاريع والمواقع (projects.php) | نسبة الإنجاز | `fn` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | رقم الطلب | `fn` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | تاريخ الطلب | `fn` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | عدد الأيام | `fn` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | الرصيد قبل | `fn` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | الرصيد بعد | `fn` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | المستند المؤيد | `fn` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | أثر الأجر | `fn` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | اعتماد المدير | `fn` |
| Workforce/worker_leave_absence.php | الإجازات والغياب (leaves.php) | اعتماد الموارد | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | رقم الطلب | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | تاريخ الطلب | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | الإدارة الطالبة | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | المرجع | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | المعدة أو المشروع | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | رقم الصنف | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | اسم الصنف | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | الكمية | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | الوحدة | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | القيمة التقديرية | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | بند الموازنة | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | المتاح في الموازنة | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | قدّمه | `fn` |
| Procurement/requests_proc.php | طلبات الشراء (pr.php) | اعتماد الإدارة | `fn` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | رقم الطلب | `fn` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | تاريخ الطلب | `fn` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | الإدارة الطالبة | `fn` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | العنصر المطلوب ترحيله | `fn` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | من | `fn` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | إلى | `fn` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | التاريخ المطلوب | `fn` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | قدّمه | `fn` |
| Transport/transfer_requests.php | طلبات الترحيل (transfer_req.php) | اعتمده | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | نوع المعدة | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | رقم المقعد | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | دور الوحدة | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | الساعات التعاقدية الشهرية | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | سعر الساعة | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | نوع الوحدة | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | المعدة المسنَدة حاليًّا | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | مالك المعدة | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | تاريخ بدء الإسناد | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | حالة الإشغال | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | تاريخ السريان | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | عرّفها | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | رقم الإسناد | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | كود المعدة | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | من تاريخ | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | إلى تاريخ | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | عدد الأيام | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | سبب الاستبدال | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | الوحدة السابقة | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | المشغّلون المسنَدون | `fn` |
| Operations/containers.php | الوحدات التعاقدية المرقَّمة (units.php) · إسناد المعدات للوحدات (unit_assign.php) | أسنده | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | رقم السطر | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | تاريخ القيد | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | الحصة المرجعية | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | الوحدة التعاقدية | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | المعدة | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | الفترة | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | الساعات المستهلكة | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | الرصيد قبل | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | الرصيد بعد | `fn` |
| Suppliers/shares_coverage.php | حصص الموردين من العقود (supplier_quota.php) · دفتر استهلاك حصص الموردين (quota_ledger.php) | المستند المصدر | `fn` |
| Finance/variance_monitor_fin.php | متابعة انحراف المنفَّذ عن المخطط (variance.php) | الفترة | `fn` |
| Finance/variance_monitor_fin.php | متابعة انحراف المنفَّذ عن المخطط (variance.php) | العقد | `fn` |
| Finance/variance_monitor_fin.php | متابعة انحراف المنفَّذ عن المخطط (variance.php) | الوحدة | `fn` |
| Finance/variance_monitor_fin.php | متابعة انحراف المنفَّذ عن المخطط (variance.php) | المنفَّذة | `fn` |
| Finance/variance_monitor_fin.php | متابعة انحراف المنفَّذ عن المخطط (variance.php) | المفوترة | `fn` |
| Finance/variance_monitor_fin.php | متابعة انحراف المنفَّذ عن المخطط (variance.php) | المحصَّلة | `fn` |
| Finance/variance_monitor_fin.php | متابعة انحراف المنفَّذ عن المخطط (variance.php) | انحراف الفوترة | `fn` |
| Finance/variance_monitor_fin.php | متابعة انحراف المنفَّذ عن المخطط (variance.php) | انحراف التحصيل | `fn` |
| Finance/variance_monitor_fin.php | متابعة انحراف المنفَّذ عن المخطط (variance.php) | تاريخ المتابعة | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | رقم السجل | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | تاريخ العطل | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | كود المعدة | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | أمر العمل | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | نظام العطل | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | تصنيف العطل | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | السبب المباشر | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | السبب الجذري | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | تكرار العطل خلال 90 يومًا | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | مجموعة التكرار | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | ساعات التوقف التراكمية | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | التكلفة التراكمية | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | الإجراء التصحيحي | `fn` |
| Equipments/manage_failure_codes.php | تصنيف الأعطال وأسبابها (failures.php) | المسؤول | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | الاسم القانوني الكامل | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | الشكل النظامي | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | بلد التسجيل | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | رقم السجل التجاري | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | الرقم الضريبي | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | العنوان المسجَّل | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | جهة الاتصال | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | المنصب | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | البريد | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | تصنيف العميل | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | شريحة الأهمية | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | سجّله | `fn` |
| Clients/clients.php | سجل العملاء (clients.php) | تاريخ التسجيل | `fn` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | البلاغ الأصل | `fn` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | نوع المسار | `fn` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | المستند الناتج | `fn` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | مهلة المسار | `fn` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | تاريخ الاستلام | `fn` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | تاريخ الإنجاز | `fn` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | حالة المسار | `fn` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | سبب التعليق | `fn` |
| Tickets/ticket_workstreams_board.php | تحويل البلاغ وتفريعه لمسارات (ticket_split.php) | مدة التعليق | `fn` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | الاسم | `fn` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | بلد الصنع | `fn` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | القدرة | `fn` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | سعة الجردل أو الحمولة | `fn` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | استهلاك الوقود التقديري | `fn` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | دورية تغيير الزيت | `fn` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | دورية الفلاتر | `fn` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | العمر الإنتاجي بالساعات | `fn` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | القطع القياسية | `fn` |
| Equipments/fleet_models.php | موديلات المعدات ومواصفاتها (equip_models.php) | عرّفه | `fn` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | رقم الصنف | `fn` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | الموديل أو العائلة المخدومة | `fn` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | حد إعادة الطلب | `fn` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | الحد الأدنى | `fn` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | الحد الأقصى | `fn` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | متوسط التكلفة | `fn` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | المورد المفضَّل | `fn` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | المخزن الافتراضي | `fn` |
| Procurement/items_proc.php | كتالوج الأصناف والقطع الحرجة (items.php) | عرّفه | `fn` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | نوع الخدمة | `fn` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | دورية الساعات | `fn` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | العدّاد عند آخر خدمة | `fn` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | العدّاد الحالي | `fn` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | الساعات المتبقية | `fn` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | القطع المطلوبة | `fn` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | الكمية | `fn` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | حالة توفر القطع | `fn` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | أمر العمل المولَّد | `fn` |
| Maintenance/preventive_plans.php | خطط الصيانة الوقائية (preventive.php) | المسؤول | `fn` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | رقم القراءة | `fn` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | القراءة السابقة | `fn` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | الفرق | `fn` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | الساعات المسجَّلة في التايم شيت | `fn` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | فرق المطابقة | `fn` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | سبب الفرق | `fn` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | الاستحقاق الوقائي التالي | `fn` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | الساعات المتبقية للوقائية | `fn` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | سجّلها | `fn` |
| Equipments/meter_readings.php | قراءات العدّادات (equip_meters.php) | صحّحها | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | رقم المجموعة | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | الموضوع | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | الموقع أو المعدة | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | عدد البلاغات | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | النافذة الزمنية | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | أول بلاغ | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | آخر بلاغ | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | إجمالي ساعات التوقف | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | إجمالي التكلفة | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | السبب الجذري المرجَّح | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | الإجراء الجذري | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | المسؤول | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | تاريخ الإقفال | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | رقم القالب | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | الفئة | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | الإدارة المستهدفة | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | المكلَّف الافتراضي | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | الدورية | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | يوم التوليد | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | آخر توليد | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | عدد المولَّد | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | نسبة الإنجاز | `fn` |
| Tickets/ticket_recurrence.php | البلاغات المتكررة وسببها الجذري (ticket_recur.php) · البلاغات الدورية المولَّدة (ticket_periodic.php) | عرّفه | `fn` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | رقم الرحلة | `fn` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | وقت المغادرة | `fn` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | مشرف الانطلاق | `fn` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | الموقع الحالي المسجَّل | `fn` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | آخر تحديث | `fn` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | المسافة المقطوعة | `fn` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | المتبقي | `fn` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | الوصول المتوقع | `fn` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | التأخر بالساعات | `fn` |
| Transport/transfer_in_transit.php | متابعة الرحلات في الطريق (transfer_track.php) | سبب التأخر | `fn` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | رقم المحضر | `fn` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | بند التكلفة | `fn` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | الوصف | `fn` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | المبلغ | `fn` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | المستند المؤيد | `fn` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | المتحمل | `fn` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | اعتمده مدير النقل | `fn` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | اعتمدته المالية | `fn` |
| Transport/transfer_close_cost.php | إقفال الأمر وتحميل تكلفته (transfer_cost.php) | رقم القيد | `fn` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | رقم التعرفة | `fn` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | اتجاه الحركة | `fn` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | من | `fn` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | إلى | `fn` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | وسيلة النقل | `fn` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | سعر الكيلومتر | `fn` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | الحد الأدنى | `fn` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | قاعدة المتحمل | `fn` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | شرط القاعدة | `fn` |
| Transport/transfer_tariffs.php | تعرفة الترحيل وقواعد التحميل (transfer_tariff.php) | اعتمدها | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | رقم الطلب | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | تاريخ الإرسال | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | مرجع طلب الشراء | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | الأصناف | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | الموردون المدعوون | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | تاريخ إقفال العروض | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | الإجمالي | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | مدة التوريد | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | شروط الدفع | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | التقييم الفني | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | الترتيب | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | القرار | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | مبرر الاختيار | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | أعدّه | `fn` |
| Procurement/rfq_compare_award.php | طلب العروض ومقارنتها (rfq.php) | اعتمده | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | رقم الأمر | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | تاريخ الإصدار | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | إلى مخزن | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | سبب التحويل | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | الوحدة | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | قيمة التحويل | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | الناقل | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | أمر الترحيل المرتبط | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | تاريخ الخروج | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | تاريخ الاستلام | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | المستلِم | `fn` |
| Procurement/wh_transfer.php | التحويل بين المخازن (wh_transfer.php) | أصدره | `fn` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | المواقع الداخلية | `fn` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | أمين المخزن | `fn` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | أسلوب الجرد | `fn` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | دورية الجرد | `fn` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | عهدة مزدوجة؟ | `fn` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | ترخيص مطلوب؟ | `fn` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | رقم الترخيص | `fn` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | تاريخ انتهائه | `fn` |
| Procurement/master_data_proc.php | المخازن وأنواعها (warehouses.php) | ضوابط الصرف | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | رقم الكشف | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | العميل | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | الفترة | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | رصيد أول المدة | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | فواتير الفترة | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | إشعارات دائنة | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | تحصيلات الفترة | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | خصم المقدم | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | محتجز الضمان | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | رصيد آخر المدة | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | المعادل بعملة الدفاتر | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | أقدم فاتورة غير مسدَّدة | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | أيام التأخر | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | حالة مطابقة العميل | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | أصدره | `fn` |
| Contracts/client_statement.php | كشف حساب العميل (client_statement.php) | اعتمده | `fn` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | رقم الفرصة | `fn` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | تاريخ الفتح | `fn` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | نوع الفرصة | `fn` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | الوصف | `fn` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | تاريخ القرار المتوقع | `fn` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | آخر نشاط | `fn` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | المسؤول | `fn` |
| Opportunities/opportunities.php | الفرص والزيارات والمناقصات (opportunities.php) | سبب الإسقاط | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | رقم العرض | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | تاريخ الإصدار | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | نموذج التسعير | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | البند | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | الكمية | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | سعر الوحدة | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | الإجمالي | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | مدة سريان العرض | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | شروط الدفع | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | شروط التسليم | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | النسخة | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | أعدّه | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | اعتمده | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | تاريخ الإرسال | `fn` |
| Clients/quotations.php | عروض الأسعار للعملاء (quotations.php) | حالة العميل | `fn` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | الفئة | `fn` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | نموذج التسعير | `fn` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | الحساب الإيرادي | `fn` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | مركز الإيراد | `fn` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | نسبة الضريبة | `fn` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | قابل للخصم؟ | `fn` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | الحد الأدنى للسعر | `fn` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | العملة الافتراضية | `fn` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | العقود المستعمِلة | `fn` |
| Clients/products.php | كتالوج الخدمات وبنود البيع (products.php) | عرّفه | `fn` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | رقم القائمة | `fn` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | العميل أو الشريحة | `fn` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | اسم البند | `fn` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | سعر الوحدة | `fn` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | الحد الأدنى للكمية | `fn` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | الخصم المسموح | `fn` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | من تاريخ | `fn` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | إلى تاريخ | `fn` |
| Clients/pricelists.php | قوائم التسعير المعتمدة (pricelists.php) | اعتمدها | `fn` |
| Clients/units_of_measure.php | وحدات القياس والتحويل (units_of_measure.php) | النوع | `fn` |
| Clients/units_of_measure.php | وحدات القياس والتحويل (units_of_measure.php) | الوحدة الأساسية | `fn` |
| Clients/units_of_measure.php | وحدات القياس والتحويل (units_of_measure.php) | عدد المنازل العشرية | `fn` |
| Clients/units_of_measure.php | وحدات القياس والتحويل (units_of_measure.php) | يُستعمل في | `fn` |
| Clients/units_of_measure.php | وحدات القياس والتحويل (units_of_measure.php) | عرّفها | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | رقم الملحق | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | رقم النسخة | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | تاريخ السريان | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | البند المعدَّل | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | القيمة قبل | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | القيمة بعد | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | الفرق | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | مبرر التعديل | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | وقّعه عنّا | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | وقّعه العميل | `fn` |
| Clients/contract_amendments.php | ملاحق العقود وتجديداتها (contract_amendments.php) | اعتمدته المالية | `fn` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | رقم الحدث | `fn` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | الوصف | `fn` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | المستند المرجعي | `fn` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | القيمة المتأثرة | `fn` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | الحالة قبل | `fn` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | الحالة بعد | `fn` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | المستخدم | `fn` |
| Clients/contract_events.php | سجل حركة العقد (contract_events.php) | الصفة | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | رقم العقد | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | عملة التسعير | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | عملة الفوترة | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | عملة التحصيل | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | نسبة المقدم | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | طريقة استهلاك المقدم | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | مهلة السداد | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | دورية الإقفال | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | نسبة محتجز الضمان | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | مدة رد المحتجز | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | حق تعليق العمل | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | شرط الإنهاء | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | حالة الضريبة | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | ثبّتها | `fn` |
| Contracts/contract_card.php | أحكام العقد — العملات والمقدم والمهل (contract_terms.php) | تاريخ التثبيت | `fn` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | رقم الآلية | `fn` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | العقد | `fn` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | الحد المحفّز | `fn` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | اتجاه التعديل | `fn` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | معادلة التعديل | `fn` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | هل بلغ الحد؟ | `fn` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | القرار | `fn` |
| Contracts/price_terms.php | آليات تعديل السعر (price_adjust.php) | عرّفها | `fn` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | وصف البند | `fn` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | الجزاءات | `fn` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | الحوافز | `fn` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | خصم المقدم | `fn` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | محتجز الضمان | `fn` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | تاريخ إرسال العميل | `fn` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | تاريخ اعتماد العميل | `fn` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | أعدّه | `fn` |
| Contracts/claims.php | المستخلصات والمطالبات (claims.php) | اعتمده | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | رقم المحضر | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | العقد | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | بند العقد المرجعي | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | سبب الاستحقاق | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | النسبة | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | القيمة | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | الطرف المتحمل | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | التحميل على المورد؟ | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | مرجع تسوية المورد | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | احتسبه | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | اعتمده | `fn` |
| Contracts/penalties.php | الجزاءات والحوافز التعاقدية (penalties.php) | المستخلص المدرَج فيه | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | العميل | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | العقد | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | قيمة الفاتورة | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | الرصيد | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | العملة المقبوضة فعلًا | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | سعر الصرف عند القبض | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | المعادل بعملة الدفاتر | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | إتاحة النقد للاستخدام | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | قيود تحويل قائمة | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | أيام التأخر | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | شريحة العمر | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | حالة التحصيل | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | خطة التحصيل | `fn` |
| Contracts/collections.php | ذمم العملاء وأعمارها (receivables.php) | المسؤول | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | رقم السجل | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | المورد | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | العقد | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | الطاقة التعاقدية (وحدات) | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | الطاقة المفعَّلة | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | متوسط زمن الإحلال | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | حالات تجاوز المهلة | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | قيمة الجزاء المستحق | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | تاريخ القياس | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | نسخة القاعدة المستعملة | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | الفترة | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | الوحدات المتعاقدة | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | الوحدات المفعَّلة | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | فجوة التغطية | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | الساعات التعاقدية | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | الساعات المنفَّذة | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | المستهدف | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | العجز | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | حالات تأخر الإحلال | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | قيمة الجزاء | `fn` |
| Suppliers/supplier_capacity.php | طاقة المورد وجاهزيته ومهلة الإحلال (supplier_capacity.php) · أداء الموردين وجاهزيتهم (supplier_perf.php) | التقييم العام | `fn` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | رقم القاعدة | `fn` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | المورد أو العام | `fn` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | بند العقد المرجعي | `fn` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | حالة الاستحقاق | `fn` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | معادلة الاحتساب | `fn` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | يحتاج موافقة المورد؟ | `fn` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | دورة الاعتماد | `fn` |
| Suppliers/supplier_rules.php | قواعد التحميل والجزاءات على المورد (supplier_rules.php) | عرّفها | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | رقم الخطة | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | المورد | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | العقد | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | العدد المتعهَّد | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | الموديل المتوقع | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | سنة الصنع الدنيا | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | تاريخ الجاهزية المتعهَّد | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | الوصول الفعلي | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | التأخر بالأيام | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | جزاء التأخر | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | سقف الاحتياطي المسموح | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | كود المعدة | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | المورد المتعاقد معه | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | المالك الحقيقي | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | علاقة المورد بالمالك | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | الموديل | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | سنة الصنع | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | الرقم التسلسلي | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | العقد المرتبط | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | الوحدة التعاقدية | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | تاريخ التفعيل | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | العدّاد عند التفعيل | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | العدّاد الحالي | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | حالة الجاهزية | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | حق الرجوع | `fn` |
| Suppliers/equipment_plan.php | خطة معدات المورد المتعهَّد بها (supplier_plan.php) · معدات المورد ومالكوها (supplier_equip.php) | درجة سرية الملكية | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | المالك القانوني | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | نوع الملكية | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | نسبة الملكية | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | المنتفع الاقتصادي | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | مرتهن الضمان | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | عملية التمويل المرتبطة | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | تاريخ بدء الملكية | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | تاريخ الانتهاء | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | مستند الملكية | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | حق الرجوع | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | درجة السرية | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | من يملك صلاحية الاطّلاع | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | رقم الحصة | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | كود العين | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | الممول أو المالك | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | النسبة المسجَّلة | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | النسبة المصححة | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | سبب التصحيح | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | من تاريخ | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | إلى تاريخ | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | المشتري عند التخارج | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | مستند التخارج | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | رأس المال المساهم به | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | تقييم الحصة | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | مستند الحصة | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | مجموع الحصص النشطة | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | حالة قيد المئة | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | سجّلها | `fn` |
| Financing/owners_registry.php | ملّاك الأسطول (fleet_owners.php) · حصص الملكية في الأصول (shares.php) | اعتمدها | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | المورد | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | الشهر | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | رصيد أول المدة | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | استحقاقات الشهر | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | تحميلات علينا | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | تحميلات على المورد | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | جزاءات وحوافز | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | مدفوعات الشهر | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | رصيد آخر المدة | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | حالة مطابقة المورد | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | أصدره | `fn` |
| Finance/supplier_statement_fin.php | كشف حساب المورد الشهري (supplier_stmt.php) | اعتمده | `fn` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | المستفيد | `fn` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | قيمة الاستحقاق | `fn` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | المسدَّد | `fn` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | الرصيد | `fn` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | المعادل بعملة الدفاتر | `fn` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | أيام التأخر | `fn` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | أولوية السداد | `fn` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | سبب التأخير | `fn` |
| Finance/dues_fin.php | ذمم الموردين والمستحقات (payables.php) | تاريخ السداد المخطط | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | رقم التقييم | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | المورد | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | محور الجاهزية | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | محور الالتزام بالمهل | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | محور جودة المعدات | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | محور الاستجابة | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | محور الوثائق | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | الدرجة الممنوحة | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | الدرجة القصوى | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | التصنيف الناتج | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | أثر التقييم | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | قيّمه | `fn` |
| Suppliers/supplier_evaluation.php | تقييم المورد الدوري (supplier_evaluation.php) | اعتمده | `fn` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | كود الوحدة | `fn` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | المعسكر | `fn` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | نوع الوحدة | `fn` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | الشاغلون الحاليون | `fn` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | المتاح | `fn` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | تاريخ التخصيص | `fn` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | نوع الإعاشة | `fn` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | تكلفة الإعاشة الشهرية | `fn` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | الجهة المتحملة | `fn` |
| Workforce/housing_units.php | السكن والإعاشة والمعسكرات (housing_units.php) | المسؤول | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | رقم البوابة | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | سلسلة الاعتماد مكتملة؟ | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | الفترة المحاسبية مفتوحة؟ | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | العقد نافذ؟ | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | الحصة متاحة؟ | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | نتيجة الفحص | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | سبب الرد | `fn` |
| Finance/entitlement_gate.php | توليد المستحق من العمل المعتمد (entitlement.php) · فحص شروط الاستحقاق (entitlement_gate.php) | قيمة الأثر | `fn` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | اسم النوع | `fn` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | وحدة العدّاد | `fn` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | وحدة الإنتاج | `fn` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | يحتاج مشغّلًا؟ | `fn` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | يحتاج تأهيلًا؟ | `fn` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | فئة الرخصة المطلوبة | `fn` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | العمر الإنتاجي القياسي | `fn` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | دورية الوقائية القياسية | `fn` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | يُستعمل في بنود العقد؟ | `fn` |
| Equipments/equipments_types.php | أنواع المعدات وفئاتها (equip_types.php) | عرّفه | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | فئة الأصول | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | وحدة العمر | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | نسبة قيمة الخردة | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | بداية الإهلاك | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | معالجة الإضافات | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | معالجة الإخراج | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | الحساب المحاسبي | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | حساب المجمع | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | من تاريخ | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | اعتمدها | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | المرجع المحاسبي | `fn` |
| Equipments/fleet_depreciation_profiles.php | سياسات الإهلاك المعتمدة (dep_profiles.php) | نسخة القاعدة المستعملة | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | الاسم الرباعي | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | رقم الهوية | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | تاريخ الميلاد | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | الجنسية | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | المؤهل | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | تاريخ التعيين | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | الإدارة | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | المسمى الوظيفي | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | المستوى التنظيمي | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | الموقع | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | الحساب البنكي | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | البنك | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | حالة الخدمة | `fn` |
| Employees/employees.php | سجل الموظفين (employees.php) | سجّله | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | كود الحساب | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | الشخص | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | رقم الهوية | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | الصفة | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | الدور | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | قالب الصلاحيات | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | النطاق | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | سقف الاعتماد | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | آخر دخول | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | حالة الحساب | `fn` |
| main/users.php | حسابات المستخدمين (users.php) | أنشأه | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | رقم السلفة | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | كود الموظف | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | تاريخ الطلب | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | سبب السلفة | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | عدد أقساط الاستقطاع | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | تاريخ بدء الاستقطاع | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | المسدَّد | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | المتبقي | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | اعتماد المدير | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | الاعتماد المالي | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | تاريخ الصرف | `fn` |
| Workforce/employee_advances.php | السلف والعهد المالية (advances.php) | رقم سند الصرف | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | رقم التقييم | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | محور الالتزام | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | محور الإنتاجية | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | محور السلامة | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | محور التعاون | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | محور الانضباط | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | الدرجة القصوى | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | النسبة | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | التصنيف | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | التوصية | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | المقيِّم | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | اعتماد الموارد | `fn` |
| Workforce/worker_evaluation.php | تقييم أداء العاملين (worker_evaluation.php) | تاريخ الإقفال | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | رقم المفتاح | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الشاشة | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الملف | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الإدارة المالكة | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الدور المستفيد | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الإدارة العارضة | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الزاوية | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الأعمدة المعروضة | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الفلاتر الافتراضية | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الأفعال المسموحة | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | الأفعال المحجوبة | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | تاريخ السريان | `fn` |
| Portal/visibility_keys.php | من يرى ماذا ومكوّنات البوابة (visibility.php) | أنشأه | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | رقم الفاتورة | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | الرقم الضريبي للعميل | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | العقد | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | رقم محضر الإقفال | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | وصف الخدمة | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | المعادل بعملة الدفاتر | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | أصدرها | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | رقم الفاتورة الضريبية | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | الفاتورة التجارية | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | رقمنا الضريبي | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | الوعاء الضريبي | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | رقم الإقرار | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | تاريخ التقديم | `fn` |
| Contracts/tax_invoices.php | فواتير العملاء (invoices.php) · الفاتورة الضريبية والإقرارات (tax_invoices.php) | حالة السداد | `fn` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | رقم المحضر | `fn` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | الفترة | `fn` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | تكلفة التمويل للفترة | `fn` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | ساعات تشغيل العين | `fn` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | الساعات في المشروع | `fn` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | نسبة التوزيع | `fn` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | رقم القيد | `fn` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | وزّعه | `fn` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | اعتمده | `fn` |
| Financing/cost_allocation.php | توزيع تكلفة التمويل على المشاريع (fin_cost.php) | نسخة القاعدة المستعملة | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | رقم المحضر | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | الشهر | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | البنك | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | الرصيد حسب الدفتر | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | الرصيد حسب الكشف | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | حركات في الدفتر لم تظهر بالبنك | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | حركات بالبنك لم تُقيَّد | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | شيكات معلَّقة | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | رسوم بنكية غير مقيَّدة | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | تسويات معتمدة | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | الرصيد بعد التسوية | `fn` |
| Finance/bank_reconciliation_fin.php | المطابقة البنكية (bank_recon.php) | اعتمده | `fn` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | إلى عملة | `fn` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | مصدر السعر | `fn` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | نوع السعر | `fn` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | المستند المرجعي | `fn` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | اعتمده | `fn` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | سارٍ من | `fn` |
| Finance/currencies_fin.php | أسعار الصرف (fx_rates.php) | سارٍ إلى | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | الفترة | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | الأسبوع أو الشهر | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | تحصيلات متوقعة — عملاء | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | تحصيلات أخرى | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | سداد موردين | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | مسيّر رواتب | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | أقساط تمويل | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | مشتريات | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | ترحيل ونثريات | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | صافي التدفق | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | الرصيد الختامي | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | فجوة السيولة | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | إجراء المعالجة | `fn` |
| Finance/cash_forecast_fin.php | التدفق النقدي والتنبؤ (cash_forecast.php) | أعدّه | `fn` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | رقم الحساب | `fn` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | طبيعة الرصيد | `fn` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | يقبل القيد المباشر؟ | `fn` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | مركز التكلفة إلزامي؟ | `fn` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | عرّفه | `fn` |
| Finance/management_accounting_fin.php | دليل الحسابات ومراكز التكلفة (coa.php) | تاريخ التعريف | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | رقم المحضر | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | الفترة | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | تاريخ الإقفال | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | الوحدات المعتمدة | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | الوحدات المعلَّقة | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | المستخلصات المُصدَرة | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | الفواتير المُصدَرة | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | المسيّرات المعتمدة | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | تسويات الموردين | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | المطابقات البنكية | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | بنود لم تُحسم | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | شرط الإقفال | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | أقفله | `fn` |
| Finance/periods_fin.php | إقفال الفترة المحاسبية (period_close.php) | اعتمده | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | الفترة | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | العقد | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | المعدة | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | تكلفة مباشرة — مشغّلون | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | وقود | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | صيانة | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | مخزون | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | ترحيل | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | تكلفة غير مباشرة | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | تكلفة تمويل | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | إهلاك | `fn` |
| Finance/cost_report_fin.php | التكاليف والربحية بالمركز (cost_report.php) | نسبة الهامش | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | الفترة | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | عدد الطلبات | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | متوسط زمن الحلقة الأولى | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | الثانية | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | الثالثة | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | إجمالي زمن الدورة | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | المستهدف | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | الانحراف | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | أطول حلقة | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | المعتمِد الأبطأ | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | عدد المتجاوز للمهلة | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | نسبة الالتزام | `fn` |
| FinRequests/cycle_time_board.php | زمن دورة الطلبات (cycle_time.php) | الإجراء | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | رقم الحدث | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | التاريخ | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | الشاشة المصدر | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | الفعل | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | المستند التشغيلي | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | الإدارة المصدر | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | الطرف | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | المبلغ | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | المعادل | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | الحساب المدين | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | الحساب الدائن | `fn` |
| FinRequests/effect_map.php | تتبّع الأثر من الواقعة إلى القيد (effect_map.php) | الفترة | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | رقم القاعدة | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | نوع الطلب | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | حد المبلغ من | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | حد المبلغ إلى | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | المعتمِد الأول | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | المعتمِد الثاني | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | المعتمِد الثالث | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | الإدارة العامة مطلوبة؟ | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | درجة الخطورة | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | مهلة كل حلقة | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | تاريخ السريان | `fn` |
| FinRequests/routing_admin.php | قواعد توجيه الطلبات المالية (routing_admin.php) | عرّفها | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | الاسم القانوني | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | نوع الممول | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | بلد التسجيل | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | السجل التجاري | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | نماذج التمويل المتعامل بها | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | العملات | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | تصنيف العلاقة | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | شريحة الأهمية | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | أول نشاط | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | آخر نشاط | `fn` |
| Financing/financiers_registry.php | سجل الممولين (financiers.php) | درجة السرية | `fn` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | رقم القسط | `fn` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | الممول | `fn` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | الرصيد قبل | `fn` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | الرصيد بعد | `fn` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | أيام التأخير | `fn` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | غرامة تأخير | `fn` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | رقم سند الصرف | `fn` |
| Financing/installments.php | الأقساط ومواعيد السداد (installments.php) | رقم القيد | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | رقم الورقة | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | نوع الانحراف | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | عملية التمويل | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | الممول | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | العين | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | الفترة | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المسجَّل | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المتوقع | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | الفرق | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | نسبة الفرق | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | السبب المرجَّح | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | الأدلة | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المستند المطلوب | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | قرار التسوية | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | قيد التسوية | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | تاريخ الإقفال | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المسجَّل في الدفاتر | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المتوقع بالعقد | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | ترجيح السبب | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | القرار المتخذ | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | حلّله | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | اعتمده | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | رقم المحضر | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | كود العين | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المالك السابق | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | النسبة المسجَّلة | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | تاريخ البيع الفعلي | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | تاريخ اكتشاف الخروج | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | أيام التسجيل الزائد | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | المالك الجديد | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | مستند البيع | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | قيمة البيع | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | أثر التصحيح على الإهلاك | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | أثر التصحيح على العائد | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | اكتشفه | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | اعتمد التصحيح | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | رقم السجل | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | تاريخ التوقيع | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | قيمة العقد | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | آخر حركة في الدفتر | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | أيام السكون | `fn` |
| Financing/deviations.php | انحرافات التمويل — السداد والملكية والتوثيق (fin_deviations.php) · فروق السداد والاستحقاق (fin_variance.php) · الخروج غير المسجَّل من الملكية (fin_exit.php) · عقود تمويل بلا حركة (fin_idle.php) | الحالة المفترضة | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | رقم البلاغ | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | الفئة | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | الأولوية | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | الموقع | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | الإدارة المختصة | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | المكلَّف | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | مستوى السرية | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | المسارات المتوازية | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | المهلة | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | المتبقي | `fn` |
| Tickets/tickets_list.php | بلاغات المركز (tickets.php) | التصعيد الحالي | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | رقم القاعدة | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | الفئة | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | النوع | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | الموقع | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | الإدارة المختصة | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | الدور المستقبِل | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | مهلة الاستجابة | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | مهلة الإنجاز | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | الأولوية الافتراضية | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | شرط رفع الأولوية | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | سياسة الإغلاق | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | تاريخ السريان | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | عرّفها | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | رقم الإعداد | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | نوع قياس المهلة | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | سلّم التصعيد | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | مستوى السرية الافتراضي | `fn` |
| Tickets/ticket_types_config.php | تصنيف البلاغ وتوجيهه آليًّا (ticket_route.php) · إعدادات البلاغات — الأنواع والمهل والتصعيد (ticket_config.php) | عرّفه | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | رقم التصعيد | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | البلاغ | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | المهلة المتجاوزة | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | المستوى بعد | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | المصعَّد إليه | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | تاريخ التصعيد | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | مدة التجاوز | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | المالك الأصلي | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | هل بقي مسؤولًا؟ | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | الإجراء المتخذ | `fn` |
| Tickets/ticket_escalation_config.php | مهل البلاغات وتصعيدها (ticket_escalate.php) | تاريخ رفع التصعيد | `fn` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | تاريخ الإنجاز | `fn` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | المنجِز | `fn` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | الأثر التشغيلي المسجَّل | `fn` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | المستند الناتج | `fn` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | سياسة الإغلاق | `fn` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | المؤكِّد | `fn` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | تاريخ التأكيد | `fn` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | عدد مرات إعادة الفتح | `fn` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | سبب آخر إعادة فتح | `fn` |
| Tickets/admin_close.php | إغلاق البلاغ وتأكيده (ticket_close.php) | نوع الإغلاق | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | كود الدور | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | اسم الدور | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | العائلة الوظيفية | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | الإدارة | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | قالب الصلاحيات | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | النطاق الافتراضي | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | سقف الاعتماد | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | عدد حامليه | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | تعارض واجبات مع | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | تاريخ التعريف | `fn` |
| Settings/roles.php | الأدوار وقوالب صلاحياتها (roles.php) | عرّفه | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | رقم التكليف | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | الأصيل | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | صفته | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | المعاون | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | نوع النيابة | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | النطاق المفوَّض | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | سقف الاعتماد | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | من تاريخ | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | إلى تاريخ | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | سبب النيابة | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | مرجع تفويض الأصيل | `fn` |
| main/all_assistants.php | المعاونون والنيابة المؤقتة (assistants.php) | أصدره | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | اسم الشاشة | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | الملف التقني | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | المسار | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | الإدارة المالكة | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | نوع الشاشة | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | مفعَّلة؟ | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | تظهر في القائمة؟ | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | المرحلة في الدورة | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | المجموعة | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | ترتيبها | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | تحويل مسار من | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | عدّاد الطرق القديم | `fn` |
| Settings/modules.php | صفحات النظام والإدارات (modules.php) | تاريخ التفعيل | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | رقم المحاولة | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | المستخدم | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | الصفة | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | الشاشة | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | الفعل المحاول | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | الحارس المانع | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | سبب المنع | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | رمز الاستجابة | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | تكرار المحاولة | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | هل طُلب استثناء؟ | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | رقم الاستثناء | `fn` |
| admin/audit_log.php | المحاولات الممنوعة (guard_denials.php) | إجراء الحوكمة | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | رقم المراجعة | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | تاريخ المراجعة | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | الحساب أو الدور | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | الواجبان المتعارضان | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | درجة الخطورة | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | الاستثناءات القائمة | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | الإجراء المتخذ | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | تاريخ التنفيذ | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | المراجع | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | اعتمده | `fn` |
| admin/sec_governance.php | فصل الواجبات المتعارضة (sec_governance.php) | تاريخ المراجعة القادمة | `fn` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | الموظف | `fn` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | الإدارة | `fn` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | المهام المنجزة | `fn` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | نسبة الالتزام بالمهل | `fn` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | ساعات العمل | `fn` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | الإنتاج المنسوب | `fn` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | نسبة الإنجاز | `fn` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | الترتيب | `fn` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | أصدرها | `fn` |
| Portal/my_certificate.php | شهادة إنجازي (my_certificate.php) | تاريخ الإصدار | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | كود الموظف | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | الاسم | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | رقم الهوية | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | تاريخ الميلاد | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | الهاتف | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | البريد | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | حالة اجتماعية | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | المستفيدون | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | الحساب البنكي | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | الوثائق المرفقة | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | تاريخ آخر تحديث | `fn` |
| main/profile.php | ملفي الشخصي (profile.php) | حقول تحتاج اعتمادًا | `fn` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | الإدارة | `fn` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | سقف الاعتماد | `fn` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | من تاريخ | `fn` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | إلى تاريخ | `fn` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | الصفة النشطة الآن | `fn` |
| user_capacities.php | صفاتي والتبديل بينها (user_capacities.php) | آخر تبديل | `fn` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | الكيان | `entity` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | مرجع التفويض | `authority_ref` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | تاريخ الاعتماد | `approved_at` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | تاريخ الإنشاء | `created_at` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | المرجع الأب | `parent_ref` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | المعتمِد — الاسم والصفة | `approver` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | المرفق | `attachment` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | العملة | `currency` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | الكيان | `entity` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | الحالة | `status` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | مركز التكلفة | `cost_center` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | مرجع التفويض | `authority_ref` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | تاريخ الاعتماد | `approved_at` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | تاريخ الإنشاء | `created_at` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | المُنشئ — الاسم والصفة | `creator` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | المعتمِد — الاسم والصفة | `approver` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | المرفق | `attachment` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | مركز التكلفة | `cost_center` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | سعر الصرف ومصدره | `fx_rate_source` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | العملة | `currency` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | مرجع التفويض | `authority_ref` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | تاريخ الاعتماد | `approved_at` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | تاريخ الإنشاء | `created_at` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | المرجع الأب | `parent_ref` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | المعتمِد — الاسم والصفة | `approver` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | سجل الاطّلاع | `view_log` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | المرفق | `attachment` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | الكيان | `entity` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | مرجع التفويض | `authority_ref` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | تاريخ الاعتماد | `approved_at` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | تاريخ الإنشاء | `created_at` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | المرجع الأب | `parent_ref` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | المُنشئ — الاسم والصفة | `creator` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | المرفق | `attachment` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | رقم التفويض | `fn` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | صفته | `fn` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | توقيع مشترك مطلوب؟ | `fn` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | من تاريخ | `fn` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | إلى تاريخ | `fn` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | جهة التصديق | `fn` |
| Governance/signing_authority.php | التفويض بالتوقيع (delegations.php) | أصدره | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | الفترة | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | المشروع | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | الوحدة | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | الإيراد المعترَف به | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | تكلفة المشغّلين | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | تكلفة الوقود | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | تكلفة الصيانة | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | تكلفة المخزون | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | تكلفة الترحيل | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | تكلفة التمويل | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | الإهلاك | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | إجمالي التكلفة | `fn` |
| Reports/margin_report.php | هامش الربح للعقد والواقعة (margin.php) | نسبة الهامش | `fn` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | الرقم أو المرجع | `fn` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | المستفيد | `fn` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | تاريخ الإصدار | `fn` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | المدة المتبقية | `fn` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | شرط التمديد التلقائي | `fn` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | الرسوم | `fn` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | حالة الرد أو المصادرة | `fn` |
| Governance/licenses_guarantees.php | التراخيص والكفالات (licenses.php) | المسؤول | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | كود الكيان | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | الاسم القانوني الكامل | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | الشكل النظامي | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | بلد التسجيل | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | جهة التسجيل | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | رقم السجل | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | الرقم الضريبي | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | العنوان المسجَّل | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | تاريخ التأسيس | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | كيان مجموعة؟ | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | اكتمال الملكية | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | سجّله | `fn` |
| Governance/entities_registry.php | سجل الشركات والكيانات (entities.php) | تاريخ التسجيل | `fn` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | كود النمط | `fn` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | الكيان أو العقد | `fn` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | نمط التفعيل | `fn` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | العناصر المفعَّلة | `fn` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | العناصر المطفأة | `fn` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | تاريخ التفعيل | `fn` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | تاريخ الترقية المتوقع | `fn` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | شرط الترقية | `fn` |
| Governance/activation_patterns.php | أنماط تفعيل المزايا (activation.php) | اعتمده | `fn` |
