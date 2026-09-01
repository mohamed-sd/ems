<?php
/**
 * _iaf_field_closure_spec.php — عقدُ إغلاقِ حقولِ المراجعةِ الداخليّة (مصدرٌ واحد)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا مُفرَدٌ عن الهجرة**: الأمامُ والعكسُ كانا سيقرآن قائمتَين — وقائمتان
 *   تنجرفان. وأخطرُ من ذلك: العكسُ كان يقرأ **سجلَّ تنفيذٍ** وحدَه، فلو ضاع
 *   السجلُّ (بوّابةُ المستأجرِ أسقطته فعلًا في أوّلِ تشغيل) بقيت 77 عمودًا
 *   و41 وسمًا **بلا طريقِ رجوع**. فالعقدُ هنا مصدرُ الحقيقةِ للاتّجاهَين،
 *   والسجلُّ يبقى دليلَ ما جرى لا شرطَ الرجوع.
 *
 * ◆ **و`was` ليست زينة**: هي **الوسمُ الذي كان** — مفردةُ التنفيذِ التي حلَّ
 *   محلَّها اسمُ الحاكم. بها يرجع العكسُ ولو خلا السجلُّ، وبها يتحقّق الأمامُ
 *   أنّه يكتب على ما يظنّ (فلا يدهس وسمًا غيَّره غيرُه).
 *
 * ◆ **قاعدةُ التحرير**: `rename` للعمودِ الذي **هو حقلُ الدليلِ نفسُه**؛ وما لا
 *   نظيرَ له يأخذ عمودًا حقيقيًّا في `add`. ⛔ ولا يُلوى اسمُ عمودٍ ليلتقطَ
 *   حقلًا آخر — تلك خُضرةٌ كاذبةٌ بلا حقلٍ في الشاشة.
 */

return array(

 'iaf_charter' => array('table' => 'iaf_charter',
  'rename' => array(
    'version'         => array('الإصدار النافذ', 'الإصدار'),
    'functional_line' => array('خط الرفع ▼',     'الارتباطُ الوظيفي'),
    'approved_by'     => array('جهة الاعتماد ◄', 'من اعتمده'),
    'approved_at'     => array('تاريخ الاعتماد', 'تاريخُ الاعتماد'),
    'state'           => array('حالة الميثاق ▼', 'الحالة')),
  'add' => array(
    array('charter_code',       'VARCHAR(40)',  'كود الميثاق',              'DC-3'),
    array('effective_date',     'DATE',         'تاريخ النفاذ',             'DC-3'),
    array('scope_of_work',      'TEXT',         'نطاق العمل',               'DC-1'),
    array('info_access_rights', 'TEXT',         'صلاحيات الوصول للمعلومات', 'DC-3'),
    array('review_cycle',       'VARCHAR(60)',  'دورية المراجعة',           'DC-1'),
    array('last_review_ref',    'VARCHAR(80)',  'آخر مراجعة ◄',             'DC-3'),
    array('attached_doc',       'VARCHAR(255)', 'الوثيقة المرفقة',          'DC-3'),
    array('reviewer',           'VARCHAR(120)', 'المراجع',                  'DC-3'))),

 'iaf_universe' => array('table' => 'iaf_universe',
  'rename' => array(
    'area_code'    => array('كود الوحدة الرقابية', 'رمزُ النطاق'),
    'owner_dept'   => array('الإدارة/العملية ◄',   'الإدارةُ المالكة'),
    'last_audited' => array('آخر مراجعة ◄',        'آخرُ مراجعة')),
  'add' => array(
    array('unit_kind',          'VARCHAR(40)', 'نوع الوحدة ▼',            'DC-1'),
    array('erm_risk_ref',       'VARCHAR(80)', 'مرجع الخطر المؤسسي ◄',    'DC-3'),
    array('risk_rating',        'VARCHAR(40)', 'تقييم المخاطر ▼',         'DC-3'),
    array('proposed_cycle',     'VARCHAR(60)', 'دورية المراجعة المقترحة', 'DC-1'),
    array('cycle_gap',          'VARCHAR(60)', 'فجوة الدورية ◄',          'DC-3'),
    array('inclusion_priority', 'VARCHAR(40)', 'أولوية الإدراج ◄',        'DC-3'),
    array('unit_state',         'VARCHAR(40)', 'حالة الوحدة ▼',           'DC-3'))),

 'iaf_plan' => array('table' => 'iaf_plan',
  'rename' => array(
    'plan_year'   => array('السنة',          'سنةُ الخطة'),
    'title'       => array('عنوان المهمة',   'العنوان'),
    'approved_at' => array('تاريخ الاعتماد', 'تاريخُ الاعتماد'),
    'state'       => array('حالة البند ▼',   'الحالة')),
  'add' => array(
    array('plan_item_no',        'VARCHAR(40)',  'معرّف بند الخطة',       'DC-3'),
    array('area_code',           'VARCHAR(40)',  'كود الوحدة الرقابية ◄', 'DC-1'),
    array('engagement_kind',     'VARCHAR(40)',  'نوع المهمة ▼',          'DC-1'),
    array('planned_quarter',     'VARCHAR(20)',  'الربع المخطط',          'DC-1'),
    array('estimated_days',      'DECIMAL(6,1)', 'الأيام المقدَّرة',       'DC-1'),
    array('assigned_auditor',    'VARCHAR(120)', 'المراجع المكلَّف ◄',     'DC-1'),
    array('plan_approval_ref',   'VARCHAR(80)',  'مرجع اعتماد الخطة ◄',   'DC-3'),
    array('amended_by_decision', 'VARCHAR(80)',  'تعديل بقرار ◄',         'DC-3'),
    array('reviewer',            'VARCHAR(120)', 'المراجع',               'DC-3'))),

 'iaf_independence' => array('table' => 'iaf_independence',
  'rename' => array(
    'auditor_id'    => array('المراجع ◄',      'المراجع'),
    'declared_at'   => array('تاريخ الإقرار',  'تاريخُ الإقرار'),
    'conflict_note' => array('وصف التعارض',    'بيانُ التعارض')),
  'add' => array(
    array('declaration_no',        'VARCHAR(40)',  'معرّف الإقرار',         'DC-3'),
    array('engagement_id',         'INT',          'معرّف المهمة ◄',        'DC-1'),
    array('prior_work_in_scope',   'VARCHAR(20)',  'عمل سابق في النطاق؟ ▼', 'DC-3'),
    array('relation_to_auditee',   'VARCHAR(60)',  'صلة بالجهة الخاضعة ▼',  'DC-3'),
    array('independence_decision', 'VARCHAR(40)',  'قرار الاستقلال ▼',      'DC-3'),
    array('substitute_auditor',    'VARCHAR(120)', 'بديل مكلَّف ◄',          'DC-1'),
    array('declaration_state',     'VARCHAR(40)',  'حالة الإقرار ▼',        'DC-3'))),

 'iaf_engagements' => array('table' => 'iaf_engagements',
  'rename' => array(
    'engagement_no' => array('معرّف المهمة',           'رقمُ المهمة'),
    'area_code'     => array('كود الوحدة الرقابية ◄',  'رمزُ النطاق'),
    'title'         => array('عنوان المهمة',           'العنوان'),
    'lead_auditor'  => array('رئيس الفريق ◄',          'المراجعُ المسؤول'),
    'state'         => array('حالة المهمة ▼',          'الحالة')),
  'add' => array(
    array('engagement_source',  'VARCHAR(40)',  'مصدر المهمة ▼',      'DC-1'),
    array('objectives',         'TEXT',         'أهداف المهمة',       'DC-1'),
    array('scope',              'TEXT',         'نطاق المهمة',        'DC-1'),
    array('period_from',        'DATE',         'الفترة المشمولة من', 'DC-1'),
    array('period_to',          'DATE',         'إلى',                'DC-1'),
    array('team_members',       'VARCHAR(255)', 'أعضاء الفريق',       'DC-1'),
    array('kickoff_notice_ref', 'VARCHAR(80)',  'إشعار البدء ◄',      'DC-3'),
    array('opening_meeting',    'VARCHAR(120)', 'الاجتماع الافتتاحي', 'DC-1'),
    array('closing_meeting',    'VARCHAR(120)', 'الاجتماع الختامي',   'DC-1'),
    array('reviewer',           'VARCHAR(120)', 'المراجع',            'DC-3'),
    array('approved_at',        'DATETIME',     'تاريخ الاعتماد',     'DC-3'))),

 'iaf_workpapers' => array('table' => 'iaf_workpapers',
  'rename' => array(
    'wp_ref'        => array('معرّف الورقة',   'مرجعُ الورقة'),
    'engagement_id' => array('معرّف المهمة ◄', 'المهمة'),
    'title'         => array('عنوان الورقة',   'العنوان'),
    'captured_by'   => array('المُعِدّ ◄',      'من التقطها'),
    'captured_at'   => array('تاريخ الإعداد',  'وقتُ الالتقاط')),
  'add' => array(
    array('step_id',      'VARCHAR(40)',  'معرّف الخطوة ◄',                'DC-1'),
    array('document_ref', 'VARCHAR(120)', 'الدليل المرفق — Document_ID ◄', 'DC-3'),
    array('conclusion',   'TEXT',         'الاستنتاج',                     'DC-1'),
    array('reviewer_ref', 'VARCHAR(120)', 'المراجع ◄',                     'DC-3'),
    array('review_date',  'DATE',         'تاريخ المراجعة',                'DC-3'),
    array('review_notes', 'TEXT',         'ملاحظات المراجعة',              'DC-3'),
    array('wp_state',     'VARCHAR(40)',  'حالة الورقة ▼',                 'DC-3'),
    array('reviewer',     'VARCHAR(120)', 'المراجع',                       'DC-3'),
    array('approved_at',  'DATETIME',     'تاريخ الاعتماد',                'DC-3'))),

 'iaf_reports' => array('table' => 'exec_audit_reports',
  'rename' => array(
    'report_no'       => array('معرّف التقرير', 'رقمُ التقرير'),
    'overall_opinion' => array('الرأي العام ▼', 'الرأيُ العام')),
  'add' => array(
    array('engagement_id',     'INT',          'معرّف المهمة ◄',           'DC-1'),
    array('report_kind',       'VARCHAR(40)',  'نوع التقرير ▼',            'DC-1'),
    array('findings_by_grade', 'VARCHAR(120)', 'عدد الملاحظات بدرجاتها ◄', 'DC-3'),
    array('draft_date',        'DATE',         'تاريخ المسودة',            'DC-1'),
    array('final_date',        'DATE',         'تاريخ النهائي',            'DC-1'),
    array('reporting_line',    'VARCHAR(80)',  'خط الرفع ◄',               'DC-3'),
    array('distributed_to',    'VARCHAR(255)', 'الموزَّع عليهم',            'DC-3'),
    array('report_attachment', 'VARCHAR(255)', 'مرفق التقرير',             'DC-3'),
    array('report_state',      'VARCHAR(40)',  'حالة التقرير ▼',           'DC-3'),
    array('reviewer',          'VARCHAR(120)', 'المراجع',                  'DC-3'),
    array('approved_at',       'DATETIME',     'تاريخ الاعتماد',           'DC-3'))),

 'iaf_quality' => array('table' => 'iaf_quality_reviews',
  'rename' => array(
    'review_no'    => array('معرّف التقييم',      'رقمُ التقييم'),
    'kind'         => array('نوع التقييم ▼',      'النوع'),
    'period_label' => array('الدورة',             'الفترة'),
    'scope_label'  => array('نطاق التقييم',       'النطاق'),
    'conformance'  => array('النتيجة ▼',          'درجةُ المطابقة'),
    'reviewed_by'  => array('الجهة المقيِّمة ◄',   'الجهةُ المقيِّمة')),
  'add' => array(
    array('function_notes',   'TEXT',         'الملاحظات على الوظيفة',  'DC-3'),
    array('improvement_plan', 'TEXT',         'خطة التحسين',            'DC-1'),
    array('escalation_ref',   'VARCHAR(120)', 'مرجع الرفع لخط الرفع ◄', 'DC-3'),
    array('review_state',     'VARCHAR(40)',  'حالة التقييم ▼',         'DC-3'))),

 'iaf_findings' => array('table' => 'iaf_findings',
  'rename' => array(
    'finding_no'    => array('معرّف الملاحظة',   'رقمُ الملاحظة'),
    'engagement_id' => array('معرّف المهمة ◄',   'المهمة'),
    'auditee_dept'  => array('الجهة الخاضعة ◄',  'الإدارةُ المُراجَعة'),
    'title'         => array('عنوان الملاحظة',   'العنوان'),
    'action_plan'   => array('خطة المعالجة',     'خطةُ المعالجة'),
    'action_owner'  => array('مالك المعالجة ◄',  'مالكُ المعالجة'),
    'action_due'    => array('المهلة',           'مهلةُ المعالجة'),
    'state'         => array('حالة الملاحظة ▼',  'الحالة')),
  'add' => array(
    array('classification',      'VARCHAR(60)',  'التصنيف ▼',                        'DC-1'),
    array('criteria',            'TEXT',         'المعيار (ما ينبغي)',               'DC-3'),
    array('condition_text',      'TEXT',         'الواقع (ما هو كائن)',              'DC-3'),
    array('estimated_effect',    'VARCHAR(255)', 'الأثر المقدَّر',                    'DC-2'),
    array('root_cause',          'TEXT',         'السبب الجذري',                     'DC-3'),
    array('recommendation',      'TEXT',         'التوصية',                          'DC-3'),
    array('auditee_response',    'VARCHAR(60)',  'رد الجهة الخاضعة ▼',               'DC-1'),
    array('governance_capa_ref', 'VARCHAR(120)', 'مرجع الإجراء التصحيحي بالحوكمة ◄', 'DC-3'),
    array('repeat_from_prior',   'VARCHAR(20)',  'متكررة من مهمة سابقة؟ ◄',          'DC-3'),
    array('reviewer',            'VARCHAR(120)', 'المراجع',                          'DC-3'),
    array('approved_at',         'DATETIME',     'تاريخ الاعتماد',                   'DC-3'))),
);
