<?php
/**
 * fields_dep02_guide2.php — مواصفةٌ مولَّدةٌ من `tools/govui_field_map_probe.php`
 * ◆ الخريطةُ من **تعليقاتِ أعمدةِ المخطَّطِ نفسِها** لا من نسخٍ بيد.
 * ⛔ وتُراجَع قبل التطبيق: عمودٌ اقتُرح بتغطيةٍ جزئيّةٍ قد يخطئ الحقل.
 */
return array(

'dept' => 'DEP-02',
'screens' => array(

array(
    'req' => 'SUP-05', 'route' => 'Contracts/contract_coverage.php',
    'table' => 'sup_need',
    'anchor' => '<table id="emsList_sup_need">',
    'rows' => "ems_w14_guide_rows('sup_need')",
    'grid_id' => 'emsList_sup_need', 'empty' => 'لا احتياج تغطية مسجل بعد',
    'map' => array(
        'رقم الاحتياج' => '+need_no:VARCHAR(190) NULL DEFAULT NULL',
        'كود العقد' => 'code_contract',
        'رقم العقد (تسلسل العميل)' => 'no_contract',
        'رقم العميل' => 'no',
        'اسم العميل (بحث)' => 'name',
        'نموذج العمل' => 'c6',
        'مفتاح دورة الالتزام' => 'key',
        'رقم التجديد (دورة الالتزام)' => 'no_role_obligation',
        'نوع دورة الالتزام' => 'type_role_obligation',
        'نوع الآلية/البند' => 'type_line',
        'وحدة القياس' => 'c11',
        'خانات العميل' => 'slot',
        'أساس الوحدة الشهري' => 'month',
        'أشهر دورة الالتزام' => 'role_obligation',
        'سعة دورة الالتزام (المستهدف)' => 'role_obligation_target',
        'المستهدف المنقضي' => 'target',
        'سعر بيع الوحدة (قراءة)' => 'c17',
        'السريان التعاقدي من' => 'c18',
        'إلى' => 'c19',
        'حالة توزيع الاحتياج (مصدر)' => 'allocation_need_source',
        'المغطى بعقود الموردين' => 'supplier',
        'فجوة الالتزام' => '+obligation_gap:VARCHAR(190) NULL DEFAULT NULL',
        'حالة الاحتياج' => 'need',
        'Σ تخصيص حصص الموردين (حي)' => 'quota_supplier',
        'علم تجاوز التوزيع' => 'allocation',
        'فحص الوحدات التعاقدية (مصدر)' => 'unit_source',
        'خانات الموردين (مصدر)' => 'slot_supplier_source',
        'منفذ دورة الالتزام (مصدر)' => 'role_obligation_source',
        'نسبة تحقق دورة الالتزام (مصدر)' => 'verify_role_obligation',
        'ملاحظات' => 'notes',
        'حالة عقد العميل (قراءة من المبيعات)' => 'contract',
        'النهاية التنفيذية لعقد العميل' => 'c32',
    ),
),

array(
    'req' => 'SUP-22', 'route' => 'Suppliers/settlements.php',
    'table' => 'sup_account',
    'anchor' => '<table class="table table-striped sup-set-table">',
    'rows' => "ems_w14_guide_rows('sup_account')",
    'grid_id' => 'emsList_sup_account', 'empty' => 'لا اقفال حساب مسجل بعد',
    'map' => array(
        'رقم الإقفال' => '+settle_no:VARCHAR(190) NULL DEFAULT NULL',
        'رقم المورد' => 'no_supplier',
        'اسم المورد (بحث)' => 'name_supplier',
        'العملة' => 'c4',
        'الشهر' => 'month',
        'مفتاح الشهر (YYYYMM)' => 'month_6',
        'رصيد افتتاحي' => 'balance',
        'استحقاق الشهر' => 'entitlement_month',
        '(−) مستردات نيابية (م15)' => 'onbehalf',
        '(−) سلف' => 'advance',
        '(−) جزاءات (م19)' => 'penalty',
        '(±) تسويات معتمدة أخرى' => 'c12',
        'صافي مستحق الشهر Net_Payable' => 'net_payable',
        'المدفوع خلال الشهر' => 'month_14',
        'رصيد ختامي' => 'balance_15',
        'حالة الإقفال' => 'closure',
        'المنشئ' => '+creator_name:VARCHAR(190) NULL DEFAULT NULL',
        'المراجع' => '+reviewer_name:VARCHAR(190) NULL DEFAULT NULL',
        'المعتمد' => '+approver_name:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ الاعتماد' => '+approved_date:DATE NULL DEFAULT NULL',
        'مراجع البنود الخاصمة' => 'line',
        'ملاحظات' => 'notes',
    ),
),

),
);
