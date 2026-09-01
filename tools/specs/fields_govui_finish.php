<?php
/**
 * fields_govui_finish.php — مواصفةُ إغلاقِ آخرِ سطحَينِ في جبهةِ الحقول
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأسماءُ أسماءُ «09 · 02_تتبع_الحقول» حرفًا** — بأرقامِها وترتيبِها في
 *   الورقةِ لا بترتيبِ المخزن (‏الأمرُ §11: `Field Order` يعكس دورةَ المستند).
 * ◆ **والصنفُ من الورقةِ**: `PK_GENERATED` · `FK_INHERITED` · `IMPORTED_READONLY`
 *   · `REFERENCE` · `BUSINESS_INPUT` · `DERIVED` — و`AUDIT` **خارجَ المقام**
 *   (§7·11) فلا تُذكر هنا.
 * ◆ ⛔ **ولا يُخترع نظيرٌ ولا رينيمٌ بلا دليل** [[iaf-field-closure]]: عمودٌ
 *   قائمٌ **معناه معنى الحقلِ حرفًا** يُربَط، وما عداه **يأخذ عمودًا جديدًا**
 *   واسمُ الحقلِ في تعليقِه. و`gov_field_trace` لا يحمل لهذين المطلبَين
 *   صفَّ `as_is/to_be`، **فلا سندَ لإعادةِ تسميةِ عمودٍ قائم**.
 */
return array(

'dept' => 'GOVUI-FINISH',
'screens' => array(

/* ═══ WRK-02 · احتياج المشروع من القوى · DEP-13 ═══════════════════════════
   القائمُ يُربَط بمعناه: `project_id` رقمُ المشروع · `worker_category` الفئةُ
   التشغيليّة · `required_qty` العددُ المطلوب · `need_date` «إلى تاريخ» ·
   `state` حالةُ الاحتياج. وما عداها عمودٌ جديدٌ باسمِ الورقةِ في تعليقِه. */
array(
    'req' => 'WRK-02', 'route' => 'Workforce/workforce_requirement.php',
    'table' => 'workforce_requirement',
    'anchor' => '<div class="table-wrap wr-table-wrap"><table class="data-table wr-table-full">',
    'rows' => "ems_w14_guide_rows('workforce_requirement')",
    'grid_id' => 'emsList_workforce_requirement', 'empty' => 'لا احتياج مسجل بعد',
    'map' => array(
        'معرّف الاحتياج'          => 'id',
        'رقم المشروع ◄'          => 'project_id',
        'كود عقد العميل ◄'       => '+client_contract_code:VARCHAR(190) NULL DEFAULT NULL',
        'الموقع ◄'                => '+site_ref:VARCHAR(190) NULL DEFAULT NULL',
        'الفئة التشغيلية ▼'      => 'worker_category',
        'نوع المعدة المرتبط ◄'   => '+linked_equipment_type:VARCHAR(190) NULL DEFAULT NULL',
        'العدد المطلوب'           => 'required_qty',
        'مستوى التأهيل المطلوب ◄' => '+required_qualification_level:VARCHAR(190) NULL DEFAULT NULL',
        'نمط الوردية ▼'          => '+shift_pattern:VARCHAR(190) NULL DEFAULT NULL',
        'من تاريخ'                 => '+need_from_date:DATE NULL DEFAULT NULL',
        'إلى تاريخ'                => 'need_date',
        'مصدر الاحتياج ▼'        => '+need_source_ref:VARCHAR(190) NULL DEFAULT NULL',
        'حالة الاحتياج ▼'        => 'state',
        'المراجع'                  => '+reviewer_name:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ الاعتماد'          => '+approved_on:DATE NULL DEFAULT NULL',
    ),
),

/* ═══ SAL-25 · قاموس قواعد الاستنتاج · DEP-01 ═════════════════════════════
   ⛔ **ولا عمودَ قائمٍ يطابق اسمًا من الأحدَ عشرَ حرفًا**: تعليقاتُ الجدولِ
   أسماءُ نسخةٍ سابقةٍ من الورقة («كود القاعدة» · «قاعدة الاشتقاق» …)،
   و`gov_field_trace` بلا صفِّ `as_is/to_be` لهذا المطلب. **فرينيمٌ بلا سندٍ
   ممنوع** — وكلُّها تأخذ أعمدةً جديدةً باسمِ الورقةِ في تعليقِها، والقائمُ
   يبقى ولا يُحذَف (‏الجدولُ صفرُ صفوفٍ فلا فقدَ بيانات). */
array(
    'req' => 'SAL-25', 'route' => 'Suppliers/supplier_inference_rules.php',
    'table' => 'sup_dictionary_rule_derivation',
    'anchor' => '<div class="table-container">',
    'rows' => "ems_w14_guide_rows('sup_dictionary_rule_derivation')",
    'grid_id' => 'emsList_sup_rules', 'empty' => 'لا قاعدة استنتاج مسجلة بعد',
    'map' => array(
        '#'                        => 'id',
        'معرّف القاعدة'           => '+rule_uid:VARCHAR(190) NULL DEFAULT NULL',
        'الشيت'                    => '+sheet_name:VARCHAR(190) NULL DEFAULT NULL',
        'الحقل/السجل'             => '+field_or_record:VARCHAR(190) NULL DEFAULT NULL',
        'ملف المصدر'              => '+source_file:VARCHAR(190) NULL DEFAULT NULL',
        'شيت المصدر'              => '+source_sheet:VARCHAR(190) NULL DEFAULT NULL',
        'مفتاح المصدر'            => '+source_key:VARCHAR(190) NULL DEFAULT NULL',
        'قاعدة الاستنتاج'         => '+inference_rule:TEXT NULL DEFAULT NULL',
        'مستويات الحجية الفعلية (محسوبة من سجل التتبع 24)'
                                   => '+effective_authority_levels:VARCHAR(190) NULL DEFAULT NULL',
        'قاعدة المالك'            => '+owner_rule:VARCHAR(190) NULL DEFAULT NULL',
        'عدد أسطر التتبع'        => '+trace_line_count:INT NULL DEFAULT NULL',
    ),
),

),
);
