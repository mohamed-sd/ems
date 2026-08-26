<?php
/**
 * tools/repair01_w13_screens.php — مولِّدُ أسطحِ المرحلةِ الثالثةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولِّدُ ملفّاتٍ لا مولِّدُ ترتيب**: يكتب ملفَّ الشاشةِ على القرصِ بقالبٍ
 *   واحدٍ لكلِّ أسطحِ الموجة — فلا يتفرَّق حارسٌ ولا بوّابةُ عزلٍ بين واحدٍ
 *   وآخر. ⛔ **ولا يكتب بندَ قائمةٍ ولا ترتيبًا** — ذاك من السجلِّ في
 *   `repair01_w13_apply.php` وحدَه.
 *
 * ◆ **وكلُّ سطحٍ يمسُّ جدولَه بالاسمِ مقتبَسًا** — فمِسبارُ المِرساةِ في
 *   `repair01_w13_prove_anchor` يثبته من القرصِ لا من الدعوى.
 *
 * ◆ **والقراءةُ كلُّها عبرَ بوّابةِ العزل** (`w13_rows`) — ⛔ لا استعلامَ خامٌّ
 *   في سطحٍ جديد (`FR-SEC-006` · `GAP-29`).
 *
 * التشغيل: php tools/repair01_w13_screens.php [--force]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$FORCE = in_array('--force', $argv, true);

/* عمودٌ يُعرَض: [اسم العمود، المسمّى العربي، النوع]
   النوع: t نصّ · i عدد صحيح · n عدد عشريّ · s رمز حالة يُعرَض من القاموس */
$SCREENS = array(

'Employees/hr_board.php' => array(
    'title' => 'لوحة الموارد البشرية', 'icon' => 'fa fa-users-gear',
    'table' => 'employees', 'order' => 'id DESC', 'limit' => 300,
    'back'  => array('../main/dashboard.php', 'الرئيسية'),
    'note'  => 'قراءة حية مشتقة من سجل الموظفين ولا ادخال فيها',
    'empty' => array('لا موظفون في هذا الكيان', 'اللوحة قراءة مشتقة لا شاشة ادخال'),
    'cards' => array(
        array('count', '', '', 'عدد الموظفين'),
        array('eq', 'employee_status', 'نشط', 'الموظفون النشطون'),
        array('distinct', 'job_title_id', '', 'المسميات الوظيفية المشغولة'),
        array('filled', 'start_date', '', 'موظفون بتاريخ مباشرة'),
    ),
    'cols' => array(
        array('employee_code', 'كود الموظف', 't'),
        array('name', 'الاسم', 't'),
        array('employment_classification', 'التصنيف الوظيفي', 't'),
        array('job_title_id', 'المسمى الوظيفي', 'i'),
        array('start_date', 'تاريخ المباشرة', 't'),
        array('employee_status', 'الحالة', 't'),
    ),
),

'Workforce/rec_applications.php' => array(
    'title' => 'طلبات الترشح', 'icon' => 'fa fa-file-signature',
    'table' => 'rec_applications', 'order' => 'vac_id, app_id', 'limit' => 500,
    'back'  => array('recruitment_pipeline.php', 'التوظيف من الشاغر الى المباشرة'),
    'note'  => 'الشاغر الواحد له عشرات المرشحين وكل ترشح سطر بمصدره وحالته',
    'empty' => array('لا طلبات ترشح', 'الترشح سطر تحت شاغره لا حقل في الشاغر'),
    'cards' => array(
        array('count', '', '', 'عدد الترشحات'),
        array('distinct', 'vac_id', '', 'الشواغر ذات المرشحين'),
        array('filled', 'interview_at', '', 'ترشحات لها موعد مقابلة'),
        array('filled', 'employee_id', '', 'ترشحات صارت موظفين'),
    ),
    'cols' => array(
        array('app_id', 'رقم الترشح', 'i'),
        array('vac_id', 'الشاغر', 'i'),
        array('applicant_name', 'اسم المرشح', 't'),
        array('applicant_phone', 'الهاتف', 't'),
        array('stage', 'المرحلة', 's'),
        array('interview_at', 'موعد المقابلة', 't'),
        array('test_score', 'درجة الاختبار', 't'),
        array('offer_ref', 'مرجع العرض', 't'),
        array('employee_id', 'رقم الموظف بعد التعيين', 'i'),
    ),
),

'Workforce/rec_stages.php' => array(
    'title' => 'مراحل التوظيف', 'icon' => 'fa fa-list-check',
    'table' => 'rec_stage_log', 'order' => 'app_id, log_id', 'limit' => 800,
    'back'  => array('rec_applications.php', 'طلبات الترشح'),
    'note'  => 'كل مرحلة سطر بنتيجتها ومقيمها ولا قفز مرحلة',
    'empty' => array('لا مراحل مسجلة', 'المرحلة سطر بنتيجتها لا حالة تدهس سابقتها'),
    'cards' => array(
        array('count', '', '', 'عدد وقائع المراحل'),
        array('distinct', 'app_id', '', 'الترشحات ذات المراحل'),
        array('distinct', 'to_stage', '', 'المراحل المستعملة'),
        array('filled', 'by_person', '', 'مراحل لها مقيم مسجل'),
    ),
    'cols' => array(
        array('log_id', 'رقم الواقعة', 'i'),
        array('app_id', 'الترشح', 'i'),
        array('from_stage', 'المرحلة السابقة', 's'),
        array('to_stage', 'المرحلة التالية', 's'),
        array('by_person', 'المقيم', 'i'),
        array('at', 'التاريخ والوقت', 't'),
        array('note', 'الملاحظة', 't'),
    ),
),

'Employees/hr_employee_documents.php' => array(
    'title' => 'مستندات الموظف', 'icon' => 'fa fa-folder-open',
    'table' => 'hr_employee_document', 'order' => 'employee_id, expires_at', 'limit' => 500,
    'back'  => array('employees.php', 'سجل الموظفين'),
    'note'  => 'كل مستند بصلاحيته وتنبيه انتهائه والالزامي المنتهي يعلم الملف',
    'empty' => array('لا مستندات مسجلة', 'المستند سطر بصلاحيته لا مرفق بلا تاريخ'),
    'cards' => array(
        array('count', '', '', 'عدد المستندات'),
        array('eq', 'is_mandatory', '1', 'مستندات الزامية'),
        array('eq', 'state', 'expired', 'مستندات منتهية'),
        array('distinct', 'employee_id', '', 'موظفون لهم مستندات'),
    ),
    'cols' => array(
        array('employee_id', 'الموظف', 'i'),
        array('doc_type', 'نوع المستند', 's'),
        array('doc_no', 'رقم المستند', 't'),
        array('issued_at', 'تاريخ الاصدار', 't'),
        array('expires_at', 'تاريخ الانتهاء', 't'),
        array('is_mandatory', 'الزامي', 'i'),
        array('file_ref', 'مرجع الملف', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Employees/hr_onboarding.php' => array(
    'title' => 'التهيئة والمباشرة', 'icon' => 'fa fa-clipboard-check',
    'table' => 'hr_onboarding_item', 'order' => 'employee_id, item_code', 'limit' => 500,
    'back'  => array('employees.php', 'سجل الموظفين'),
    'note'  => 'لا مباشرة كاملة قبل اكتمال بنود التهيئة او توثيق استثنائها',
    'empty' => array('لا بنود تهيئة', 'البند سطر بحالته لا خانة اختيار بلا اثر'),
    'cards' => array(
        array('count', '', '', 'عدد البنود'),
        array('eq', 'state', 'pending', 'بنود معلقة'),
        array('eq', 'state', 'waived', 'بنود باستثناء موثق'),
        array('distinct', 'employee_id', '', 'موظفون قيد التهيئة'),
    ),
    'cols' => array(
        array('employee_id', 'الموظف', 'i'),
        array('item_code', 'رمز البند', 't'),
        array('item_ar', 'البند', 't'),
        array('mandatory', 'الزامي', 'i'),
        array('state', 'الحالة', 's'),
        array('waiver_doc_ref', 'مستند الاستثناء', 't'),
        array('custody_doc_ref', 'سند العهدة', 't'),
        array('done_at', 'تاريخ الانجاز', 't'),
    ),
),

'Employees/hr_job_movements.php' => array(
    'title' => 'الحركات الوظيفية', 'icon' => 'fa fa-arrows-turn-right',
    'table' => 'hr_job_movement', 'order' => 'effective_date DESC, id DESC', 'limit' => 500,
    'back'  => array('employees.php', 'سجل الموظفين'),
    'note'  => 'النقل والترقية والانتداب حركات موثقة بموجبها واعتمادها',
    'empty' => array('لا حركات وظيفية', 'الحركة قرار بموجبه لا تعديل صامت للمنصب'),
    'cards' => array(
        array('count', '', '', 'عدد الحركات'),
        array('eq', 'state', 'approved', 'حركات معتمدة'),
        array('eq', 'state', 'submitted', 'حركات قيد الاعتماد'),
        array('distinct', 'employee_id', '', 'موظفون لهم حركات'),
    ),
    'cols' => array(
        array('employee_id', 'الموظف', 'i'),
        array('movement_kind', 'نوع الحركة', 's'),
        array('from_position_id', 'المنصب السابق', 'i'),
        array('to_position_id', 'المنصب الجديد', 'i'),
        array('effective_date', 'تاريخ السريان', 't'),
        array('doc_ref', 'مرجع القرار', 't'),
        array('requested_by', 'طالب الحركة', 'i'),
        array('approved_by', 'معتمد الحركة', 'i'),
        array('state', 'الحالة', 's'),
    ),
),

'Employees/hr_training.php' => array(
    'title' => 'التدريب والكفاءة', 'icon' => 'fa fa-graduation-cap',
    'table' => 'hr_training_record', 'order' => 'employee_id, valid_until', 'limit' => 500,
    'back'  => array('employees.php', 'سجل الموظفين'),
    'note'  => 'التدريب الالزامي يتابع بانتهاء صلاحيته لا باكتماله وحده',
    'empty' => array('لا سجلات تدريب', 'التدريب سطر بصلاحيته لا شهادة بلا تاريخ'),
    'cards' => array(
        array('count', '', '', 'عدد سجلات التدريب'),
        array('eq', 'mandatory', '1', 'برامج الزامية'),
        array('eq', 'state', 'completed', 'برامج مكتملة'),
        array('eq', 'state', 'expired', 'برامج منتهية الصلاحية'),
    ),
    'cols' => array(
        array('employee_id', 'الموظف', 'i'),
        array('program_code', 'رمز البرنامج', 't'),
        array('program_ar', 'البرنامج', 't'),
        array('training_kind', 'نوع التدريب', 's'),
        array('mandatory', 'الزامي', 'i'),
        array('completed_at', 'تاريخ الاكتمال', 't'),
        array('certificate_ref', 'مرجع الشهادة', 't'),
        array('valid_until', 'صالح حتى', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Employees/hr_performance.php' => array(
    'title' => 'تقييم الأداء الوظيفي', 'icon' => 'fa fa-chart-line',
    'table' => 'hr_performance_review', 'order' => 'cycle_code DESC, employee_id', 'limit' => 500,
    'back'  => array('employees.php', 'سجل الموظفين'),
    'note'  => 'التقييم الوظيفي للاداريين دوري بمعاييره واداء التشغيليين مشتق عند القوى',
    'empty' => array('لا تقييمات', 'التقييم دورة بمعاييرها لا رقم يكتب بلا مرجع'),
    'cards' => array(
        array('count', '', '', 'عدد التقييمات'),
        array('eq', 'state', 'finalized', 'تقييمات نهائية'),
        array('distinct', 'cycle_code', '', 'الدورات المفتوحة'),
        array('distinct', 'employee_id', '', 'موظفون مقيمون'),
    ),
    'cols' => array(
        array('employee_id', 'الموظف', 'i'),
        array('cycle_code', 'دورة التقييم', 't'),
        array('criteria_ref', 'مرجع المعايير', 't'),
        array('score', 'الدرجة', 'n'),
        array('reviewer_id', 'المقيم', 'i'),
        array('moderator_id', 'المراجع', 'i'),
        array('final_at', 'تاريخ الاعتماد', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Employees/hr_disciplinary.php' => array(
    'title' => 'القضايا التأديبية والتحقيق', 'icon' => 'fa fa-gavel',
    'table' => 'hr_disciplinary_case', 'order' => 'incident_at DESC, id DESC', 'limit' => 500,
    'back'  => array('employees.php', 'سجل الموظفين'),
    'note'  => 'القضية عملية بمراحلها واقعة ثم تحقيق ثم قرار والخصم يتفرع بمرجع قرارها',
    'empty' => array('لا قضايا تاديبية', 'القضية عملية بمراحلها لا حقل خصم في المسير'),
    'cards' => array(
        array('count', '', '', 'عدد القضايا'),
        array('eq', 'state', 'investigation', 'قضايا قيد التحقيق'),
        array('eq', 'state', 'decided', 'قضايا محسومة'),
        array('eq', 'decision_kind', 'deduction', 'قرارات بخصم'),
    ),
    'cols' => array(
        array('case_no', 'رقم القضية', 't'),
        array('employee_id', 'الموظف', 'i'),
        array('incident_at', 'تاريخ الواقعة', 't'),
        array('incident_ar', 'الواقعة', 't'),
        array('reported_by', 'المبلغ', 'i'),
        array('investigator_id', 'المحقق', 'i'),
        array('investigation_owner_dept', 'الادارة المالكة للتحقيق', 't'),
        array('assignment_doc_ref', 'مستند التكليف', 't'),
        array('decision_kind', 'نوع القرار', 's'),
        array('decision_ref', 'مرجع القرار', 't'),
        array('decided_by', 'مصدر القرار', 'i'),
        array('state', 'الحالة', 's'),
    ),
),

'Employees/hr_benefits.php' => array(
    'title' => 'المزايا والتأمينات', 'icon' => 'fa fa-shield-heart',
    'table' => 'hr_benefit_enrollment', 'order' => 'employee_id, effective_from DESC', 'limit' => 500,
    'back'  => array('employees.php', 'سجل الموظفين'),
    'note'  => 'الاشتراكات النظامية والتامين الطبي بحصتيهما تصب في المسير بمرجعها',
    'empty' => array('لا اشتراكات مزايا', 'الميزة اشتراك بحصتيه ومرجعه في المسير'),
    'cards' => array(
        array('count', '', '', 'عدد الاشتراكات'),
        array('eq', 'state', 'active', 'اشتراكات سارية'),
        array('distinct', 'benefit_code', '', 'انواع المزايا'),
        array('sumf', 'employer_share', '', 'اجمالي حصة صاحب العمل'),
    ),
    'cols' => array(
        array('employee_id', 'الموظف', 'i'),
        array('benefit_code', 'رمز الميزة', 't'),
        array('benefit_ar', 'الميزة', 't'),
        array('provider_ref', 'مقدم الخدمة', 't'),
        array('employer_share', 'حصة صاحب العمل', 'n'),
        array('employee_share', 'حصة الموظف', 'n'),
        array('currency', 'العملة', 't'),
        array('effective_from', 'ساري من', 't'),
        array('payroll_component_ref', 'مرجع مكون المسير', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Workforce/payroll_lines.php' => array(
    'title' => 'أسطر مسير الرواتب', 'icon' => 'fa fa-money-check-dollar',
    'table' => 'payroll_lines', 'order' => 'run_id DESC, person_id', 'limit' => 800,
    'back'  => array('payroll_runs.php', 'مسير الرواتب'),
    'note'  => 'لكل موظف سطر داخل المسير بمكوناته والمجاميع في الام تقرا من الاسطر',
    'empty' => array('لا اسطر مسير', 'السطر مكون باسمه لا رقم مجمع بلا سند'),
    'cards' => array(
        array('count', '', '', 'عدد الاسطر'),
        array('distinct', 'run_id', '', 'المسيرات الممثلة'),
        array('distinct', 'person_id', '', 'الموظفون في الاسطر'),
        array('sumf', 'amount', '', 'اجمالي مبالغ الاسطر'),
    ),
    'cols' => array(
        array('run_id', 'المسير', 'i'),
        array('person_id', 'الموظف', 'i'),
        array('contract_id', 'العقد', 'i'),
        array('line_kind', 'نوع السطر', 's'),
        array('component_type', 'المكون', 's'),
        array('calc_method', 'طريقة الاحتساب', 's'),
        array('qty', 'الكمية', 'n'),
        array('rate', 'المعدل', 'n'),
        array('amount', 'المبلغ', 'n'),
        array('calc_state', 'حالة الاحتساب', 's'),
    ),
),

'Workforce/hr_workforce_report.php' => array(
    'title' => 'تقرير القوى العاملة', 'icon' => 'fa fa-chart-pie',
    'table' => 'employees', 'order' => 'employment_classification, id', 'limit' => 800,
    'back'  => array('../Employees/employees.php', 'سجل الموظفين'),
    'note'  => 'مشتق من السجل والعقود والحضور ولا ادخال فيه',
    'empty' => array('لا بيانات قوى عاملة', 'التقرير مشتق لا يكتب فيه سطر'),
    'cards' => array(
        array('count', '', '', 'اجمالي القوى العاملة'),
        array('distinct', 'employment_classification', '', 'التصنيفات الوظيفية'),
        array('eq', 'is_workforce', '1', 'ضمن القوى التشغيلية'),
        array('distinct', 'project_id', '', 'المشاريع الممثلة'),
    ),
    'cols' => array(
        array('employee_code', 'كود الموظف', 't'),
        array('name', 'الاسم', 't'),
        array('employment_classification', 'التصنيف الوظيفي', 't'),
        array('worker_category', 'فئة العامل', 't'),
        array('project_id', 'المشروع', 'i'),
        array('start_date', 'تاريخ المباشرة', 't'),
        array('employee_status', 'الحالة', 't'),
    ),
),

'Tickets/tkt_subject_types.php' => array(
    'title' => 'كتالوج أنواع محل البلاغ', 'icon' => 'fa fa-diagram-project',
    'table' => 'tkt_subject_type', 'order' => 'owner_dept, type_code', 'limit' => 300,
    'back'  => array('ticket_dashboard.php', 'لوحة مركز البلاغات'),
    'note'  => 'المركز يتعامل مع كل الادارات فالكتالوج يمكن الاضافة ولا يحصر قائمة قصيرة',
    'empty' => array('لا انواع محل بلاغ', 'محل البلاغ نوع بسجله المرجعي لا نص حر'),
    'cards' => array(
        array('count', '', '', 'عدد الانواع'),
        array('eq', 'active', '1', 'انواع مفعلة'),
        array('distinct', 'owner_dept', '', 'الادارات المالكة'),
        array('distinct', 'entity_kind', '', 'اصناف الكيانات'),
    ),
    'cols' => array(
        array('type_code', 'رمز النوع', 't'),
        array('name_ar', 'النوع', 't'),
        array('entity_kind', 'صنف الكيان', 's'),
        array('ref_table', 'السجل المرجعي', 't'),
        array('ref_key', 'مفتاح السجل', 't'),
        array('owner_dept', 'الادارة المالكة', 't'),
        array('active', 'مفعل', 'i'),
    ),
),

'Tickets/tkt_parties.php' => array(
    'title' => 'أطراف البلاغ', 'icon' => 'fa fa-user-group',
    'table' => 'tkt_party', 'order' => 'ticket_id DESC, party_role', 'limit' => 800,
    'back'  => array('ticket_dashboard.php', 'لوحة مركز البلاغات'),
    'note'  => 'اربعة اطراف متمايزة من بلغ وعم بلغ ومن يملك التذكرة ومن يملك الحل',
    'empty' => array('لا اطراف مسجلة', 'الطرف صف بمفتاح فاعله لا اسم في راس البلاغ'),
    'cards' => array(
        array('count', '', '', 'عدد صفوف الاطراف'),
        array('distinct', 'ticket_id', '', 'البلاغات ذات الاطراف'),
        array('distinct', 'party_role', '', 'الادوار المستعملة'),
        array('eq', 'party_role', 'RESOLUTION_OWNER', 'بلاغات لها مالك حل'),
    ),
    'cols' => array(
        array('ticket_id', 'البلاغ', 'i'),
        array('party_role', 'الدور', 's'),
        array('actor_kind', 'صنف الفاعل', 's'),
        array('actor_id', 'مفتاح الفاعل', 'i'),
        array('actor_dept', 'ادارة الفاعل', 't'),
        array('subject_type_code', 'نوع محل البلاغ', 't'),
        array('recorded_by', 'مسجل الطرف', 'i'),
        array('recorded_at', 'تاريخ التسجيل', 't'),
    ),
),

'Tickets/tkt_routing.php' => array(
    'title' => 'سجل التوجيه', 'icon' => 'fa fa-route',
    'table' => 'tkt_routing_history', 'order' => 'ticket_id DESC, seq_no', 'limit' => 800,
    'back'  => array('tkt_parties.php', 'أطراف البلاغ'),
    'note'  => 'كل توجيه سطر والالي بقاعدته وتصحيح المركز بسببه المكتوب',
    'empty' => array('لا وقائع توجيه', 'التوجيه واقعة بقاعدتها لا احالة يدوية بلا سبب'),
    'cards' => array(
        array('count', '', '', 'عدد وقائع التوجيه'),
        array('eq', 'route_kind', 'AUTO', 'توجيه الي بقاعدته'),
        array('eq', 'route_kind', 'CENTER_CORRECTION', 'تصحيح مركز بسببه'),
        array('distinct', 'to_dept', '', 'الادارات الموجه اليها'),
    ),
    'cols' => array(
        array('ticket_id', 'البلاغ', 'i'),
        array('seq_no', 'التسلسل', 'i'),
        array('route_kind', 'نوع التوجيه', 's'),
        array('from_dept', 'من ادارة', 't'),
        array('to_dept', 'الى ادارة', 't'),
        array('rule_ref', 'قاعدة التوجيه', 't'),
        array('reason', 'سبب التصحيح', 't'),
        array('routed_by', 'الموجه', 'i'),
        array('routed_at', 'تاريخ التوجيه', 't'),
    ),
),

'Tickets/tkt_assignment.php' => array(
    'title' => 'سجل الإسناد', 'icon' => 'fa fa-user-check',
    'table' => 'tkt_assignment_history', 'order' => 'ticket_id DESC, seq_no', 'limit' => 800,
    'back'  => array('tkt_routing.php', 'سجل التوجيه'),
    'note'  => 'كل تغيير مكلف سطر بسببه ولا مكلف بلا وقت استلام',
    'empty' => array('لا وقائع اسناد', 'الاسناد واقعة بسببها ووقت استلامها'),
    'cards' => array(
        array('count', '', '', 'عدد وقائع الاسناد'),
        array('filled', 'received_at', '', 'اسنادات مستلمة'),
        array('empty', 'received_at', '', 'اسنادات بلا وقت استلام'),
        array('distinct', 'to_dept', '', 'ادارات المكلفين'),
    ),
    'cols' => array(
        array('ticket_id', 'البلاغ', 'i'),
        array('seq_no', 'التسلسل', 'i'),
        array('from_person_id', 'المكلف السابق', 'i'),
        array('to_person_id', 'المكلف الجديد', 'i'),
        array('to_dept', 'ادارة المكلف', 't'),
        array('reason', 'سبب التغيير', 't'),
        array('assigned_by', 'المسند', 'i'),
        array('assigned_at', 'تاريخ الاسناد', 't'),
        array('received_at', 'وقت الاستلام', 't'),
    ),
),

'Tickets/tkt_resolution_actions.php' => array(
    'title' => 'إجراءات المعالجة', 'icon' => 'fa fa-screwdriver-wrench',
    'table' => 'tkt_resolution_action', 'order' => 'ticket_id DESC, seq_no', 'limit' => 800,
    'back'  => array('tkt_assignment.php', 'سجل الإسناد'),
    'note'  => 'كل اجراء سطر بمرجعه في شاشة الادارة المعالجة والمركز لا ينفذ الحل',
    'empty' => array('لا اجراءات معالجة', 'الاجراء سطر بمرجعه في شاشة ادارته'),
    'cards' => array(
        array('count', '', '', 'عدد الاجراءات'),
        array('distinct', 'executor_dept', '', 'الادارات المنفذة'),
        array('distinct', 'ticket_id', '', 'بلاغات لها اجراءات'),
        array('filled', 'dept_doc_ref', '', 'اجراءات لها مستند'),
    ),
    'cols' => array(
        array('ticket_id', 'البلاغ', 'i'),
        array('seq_no', 'التسلسل', 'i'),
        array('executor_dept', 'الادارة المنفذة', 't'),
        array('executor_person_id', 'المنفذ', 'i'),
        array('action_ar', 'الاجراء', 't'),
        array('dept_screen_ref', 'مرجع شاشة الادارة', 't'),
        array('dept_doc_ref', 'مستند الاجراء', 't'),
        array('acted_at', 'تاريخ الاجراء', 't'),
    ),
),

'Tickets/tkt_communications.php' => array(
    'title' => 'سجل التواصل', 'icon' => 'fa fa-comments',
    'table' => 'ticket_communications', 'order' => 'tk_id DESC, cm_id', 'limit' => 800,
    'back'  => array('tkt_parties.php', 'أطراف البلاغ'),
    'note'  => 'دور المركز تواصل موثق وكل تواصل سطر بقناته ووقته',
    'empty' => array('لا وقائع تواصل', 'التواصل سطر بقناته لا مكالمة بلا اثر'),
    'cards' => array(
        array('count', '', '', 'عدد وقائع التواصل'),
        array('distinct', 'tk_id', '', 'بلاغات لها تواصل'),
        array('distinct', 'channel', '', 'القنوات المستعملة'),
        array('distinct', 'person_id', '', 'الاشخاص المتواصلون'),
    ),
    'cols' => array(
        array('tk_id', 'البلاغ', 'i'),
        array('person_id', 'الشخص', 'i'),
        array('channel', 'القناة', 's'),
        array('note', 'النص', 't'),
        array('at', 'التاريخ والوقت', 't'),
    ),
),

'Tickets/tkt_escalation.php' => array(
    'title' => 'التصعيد', 'icon' => 'fa fa-arrow-up-right-dots',
    'table' => 'ticket_escalations', 'order' => 'ws_id DESC, level', 'limit' => 800,
    'back'  => array('tkt_assignment.php', 'سجل الإسناد'),
    'note'  => 'التصعيد الي بمستوياته عند تجاوز المهل ولا يسكت الا بمعالجة او تعليق مبرر',
    'empty' => array('لا وقائع تصعيد', 'التصعيد سلم بمستوياته لا تنبيه يمر بلا اثر'),
    'cards' => array(
        array('count', '', '', 'عدد وقائع التصعيد'),
        array('distinct', 'ws_id', '', 'المسارات المصعدة'),
        array('distinct', 'level', '', 'المستويات المستعملة'),
        array('filled', 'to_person_id', '', 'تصعيدات لها مستقبل'),
    ),
    'cols' => array(
        array('ws_id', 'مسار العمل', 'i'),
        array('level', 'مستوى التصعيد', 'i'),
        array('triggered_by', 'محفز التصعيد', 's'),
        array('to_person_id', 'المصعد اليه', 'i'),
        array('at', 'التاريخ والوقت', 't'),
    ),
),

'Tickets/tkt_reopen.php' => array(
    'title' => 'إعادة الفتح', 'icon' => 'fa fa-rotate-left',
    'table' => 'tkt_reopen', 'order' => 'ticket_id DESC, seq_no', 'limit' => 500,
    'back'  => array('tkt_verification.php', 'التحقق والإغلاق'),
    'note'  => 'اعتراض المبلغ او تكرار المشكلة يعيد الفتح بسجل ويعود البلاغ لمسار معالجته',
    'empty' => array('لا وقائع اعادة فتح', 'اعادة الفتح واقعة بسببها لا حالة تنقلب صامتة'),
    'cards' => array(
        array('count', '', '', 'عدد وقائع اعادة الفتح'),
        array('eq', 'reopen_reason', 'REPORTER_OBJECTION', 'باعتراض المبلغ'),
        array('eq', 'reopen_reason', 'RECURRENCE', 'بتكرار المشكلة'),
        array('distinct', 'back_to_dept', '', 'الادارات المعادة اليها'),
    ),
    'cols' => array(
        array('ticket_id', 'البلاغ', 'i'),
        array('seq_no', 'التسلسل', 'i'),
        array('prior_cycle_no', 'الدورة السابقة', 'i'),
        array('reopen_reason', 'سبب اعادة الفتح', 's'),
        array('note', 'التفصيل', 't'),
        array('raised_by', 'طالب اعادة الفتح', 'i'),
        array('back_to_dept', 'العودة الى ادارة', 't'),
        array('raised_at', 'التاريخ والوقت', 't'),
    ),
),

'Tickets/tkt_verification.php' => array(
    'title' => 'التحقق والإغلاق', 'icon' => 'fa fa-circle-check',
    'table' => 'tkt_verification', 'order' => 'ticket_id DESC, cycle_no', 'limit' => 500,
    'back'  => array('tkt_resolution_actions.php', 'إجراءات المعالجة'),
    'note'  => 'المسار الثلاثي معالجة ثم تحقق ثم اغلاق ولا اغلاق بلا تحقق ولا تحقق من المنفذ',
    'empty' => array('لا دورات تحقق', 'الاغلاق دورة بتحققها لا زر يغلق بلا شاهد'),
    'cards' => array(
        array('count', '', '', 'عدد دورات التحقق'),
        array('eq', 'state', 'verification', 'دورات قيد التحقق'),
        array('eq', 'state', 'closed', 'دورات مغلقة'),
        array('eq', 'state', 'reopened', 'دورات اعيد فتحها'),
    ),
    'cols' => array(
        array('ticket_id', 'البلاغ', 'i'),
        array('cycle_no', 'رقم الدورة', 'i'),
        array('priority_code', 'الاولوية', 's'),
        array('resolved_dept', 'ادارة المعالجة', 't'),
        array('resolved_by', 'المعالج', 'i'),
        array('resolved_at', 'تاريخ المعالجة', 't'),
        array('window_hours', 'نافذة التحقق بالساعات', 'i'),
        array('verify_kind', 'صفة المتحقق', 's'),
        array('verified_by', 'المتحقق', 'i'),
        array('verified_at', 'تاريخ التحقق', 't'),
        array('closed_by', 'المغلق', 'i'),
        array('closed_at', 'تاريخ الاغلاق', 't'),
        array('state', 'الحالة', 's'),
    ),
),
);

$written = 0; $skipped = 0;
foreach ($SCREENS as $route => $s) {
    $path = $ROOT . '/' . $route;
    if (is_file($path) && !$FORCE) { echo "  ↷ $route قائم\n"; $skipped++; continue; }
    $dir = dirname($path);
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }

    $up = str_repeat('../', substr_count($route, '/'));
    $cards = '';
    foreach ($s['cards'] as $c) {
        switch ($c[0]) {
            case 'count':    $expr = 'count($rows)'; break;
            case 'eq':       $expr = 'ems_w13_count($rows, "' . $c[1] . '", "' . $c[2] . '")'; break;
            case 'distinct': $expr = 'ems_w13_distinct($rows, "' . $c[1] . '")'; break;
            case 'filled':   $expr = 'ems_w13_filled($rows, "' . $c[1] . '")'; break;
            case 'empty':    $expr = 'ems_w13_empty($rows, "' . $c[1] . '")'; break;
            case 'sumf':     $expr = 'ems_w13_num(ems_w13_sumf($rows, "' . $c[1] . '"))'; break;
            default:         $expr = '0';
        }
        $cards .= '        <div class="ems-stat-card"><div class="ems-stat-value"><?= ' . $expr
                . ' ?></div><div class="ems-stat-label">' . $c[3] . "</div></div>\n";
    }
    $head = ''; $body = '';
    foreach ($s['cols'] as $col) {
        $head .= '<th>' . $col[1] . '</th>';
        switch ($col[2]) {
            case 'i': $cell = '(int) $r["' . $col[0] . '"]'; break;
            case 'n': $cell = 'ems_w13_num($r["' . $col[0] . '"])'; break;
            case 's': $cell = 'ems_w13_state((string) $r["' . $col[0] . '"])'; break;
            default:  $cell = 'ems_w13_txt($r["' . $col[0] . '"])';
        }
        $body .= '                    <td><?= ' . $cell . " ?></td>\n";
    }

    $php = "<?php\n"
        . "/**\n"
        . " * " . $route . " — " . $s['title'] . " (RPR-W13)\n"
        . " * ───────────────────────────────────────────────────────────────────────────\n"
        . " * " . $s['note'] . "\n"
        . " *\n"
        . " * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ\n"
        . " *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.\n"
        . " *\n"
        . " * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `PeopleCycleService`\n"
        . " *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع.\n"
        . " */\n"
        . "require_once __DIR__ . '/" . $up . "includes/session_bootstrap.php';\n"
        . "session_start();\n"
        . "if (!isset(\$_SESSION['user'])) { header('Location: " . $up . "login.php'); exit(); }\n"
        . "include '" . $up . "config.php';\n"
        . "include '" . $up . "includes/permissions_helper.php';\n"
        . "require_once __DIR__ . '/" . $up . "includes/w13_view.php';\n"
        . "\n"
        . "\$ctx = w13_ctx();\n"
        . "\$is_super = \$ctx['is_super'];\n"
        . "\$company_id = \$ctx['company_id'];\n"
        . "if (!\$is_super && \$company_id <= 0) {\n"
        . "    ems_gov_flash_redirect('" . $up . "main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');\n"
        . "    exit();\n"
        . "}\n"
        . "\n"
        . "\$perms = w13_perms(\$conn, '" . $route . "', \$is_super);\n"
        . "if (empty(\$perms['can_view'])) {\n"
        . "    ems_gov_flash_redirect('" . $up . "main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');\n"
        . "    exit();\n"
        . "}\n"
        . "\n"
        . "\$rows = w13_rows(\$is_super, '" . $s['table'] . "',\n"
        . "                 array('orderBy' => '" . $s['order'] . "', 'limit' => " . (int) $s['limit'] . "));\n"
        . "\n"
        . "\$page_title = 'إيكوبيشن | " . $s['title'] . "';\n"
        . "require_once __DIR__ . '/" . $up . "includes/screen_contract.php';\n"
        . "ems_shell_axes(isset(\$perms) ? \$perms : null);\n"
        . "include '" . $up . "inheader.php';\n"
        . "include '" . $up . "insidebar.php';\n"
        . "require_once __DIR__ . '/" . $up . "includes/screen_contract.php'; if (isset(\$conn)) { ems_screen_about_auto(\$conn); }\n"
        . "?>\n"
        . "<div class=\"main ems-unified-page-shell\">\n"
        . "    <?php \$header_title = '" . $s['title'] . "'; \$header_icon = '" . $s['icon'] . "'; \$header_actions = array();\n"
        . "    \$header_back = array('href' => '" . $s['back'][0] . "', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => '" . $s['back'][1] . "');\n"
        . "    include('" . $up . "includes/page_header.php'); ?>\n"
        . "\n"
        . "    <div class=\"ems-stat-cards\">\n"
        . $cards
        . "    </div>\n"
        . "\n"
        . "    <?php require_once __DIR__ . '/" . $up . "includes/ux_components.php';\n"
        . "    echo ems_states_bundle('" . $s['empty'][0] . "', '" . $s['empty'][1] . "'); ?>\n"
        . "\n"
        . "    <div class=\"table-wrap\"><table class=\"data-table\">\n"
        . "        <thead><tr>" . $head . "</tr></thead>\n"
        . "        <tbody>\n"
        . "        <?php if (\$rows): foreach (\$rows as \$r): ?>\n"
        . "            <tr>\n"
        . $body
        . "            </tr>\n"
        . "        <?php endforeach; endif; ?>\n"
        . "        </tbody>\n"
        . "    </table></div>\n"
        . "</div>\n"
        . "</body></html>\n";

    file_put_contents($path, $php);
    echo "  ✔ $route\n";
    $written++;
}
printf("\nأسطحٌ مكتوبة %d · قائمةٌ %d\n", $written, $skipped);
