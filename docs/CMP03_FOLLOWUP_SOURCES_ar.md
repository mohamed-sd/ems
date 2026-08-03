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
