<?php
/**
 * fields_dep_13.php — مواصفةٌ مولَّدةٌ من `tools/govui_field_map_probe.php`
 * ◆ الخريطةُ من **تعليقاتِ أعمدةِ المخطَّطِ نفسِها** لا من نسخٍ بيد.
 * ⛔ وتُراجَع قبل التطبيق: عمودٌ اقتُرح بتغطيةٍ جزئيّةٍ قد يخطئ الحقل.
 */
return array(

'dept' => 'DEP-13',
'screens' => array(

array(
    'req' => 'WRK-03', 'route' => 'Workforce/wf_coverage.php',
    'table' => 'wf_coverage_lines',
    'anchor' => '<table id="emsList_wf_coverage"',
    'rows' => "ems_w14_guide_rows('wf_coverage_lines')",
    'grid_id' => 'emsList_wf_coverage', 'empty' => 'لا سطر مسجل بعد في المطلوب مقابل المتوفر',
    'create' => true,
    'grain' => 'لا سطر مسجل بعد في المطلوب مقابل المتوفر',
    'map' => array(
        'معرف السطر' => '+g1:VARCHAR(190) NULL DEFAULT NULL',
        'المشروع' => '+g2:VARCHAR(190) NULL DEFAULT NULL',
        'الفئة' => '+g3:VARCHAR(190) NULL DEFAULT NULL',
        'المطلوب' => '+g4:VARCHAR(190) NULL DEFAULT NULL',
        'المتوفر الجاهز' => '+g5:VARCHAR(190) NULL DEFAULT NULL',
        'المخصص' => '+g6:VARCHAR(190) NULL DEFAULT NULL',
        'العجز' => '+g7:VARCHAR(190) NULL DEFAULT NULL',
        'مسار السد' => '+g8:VARCHAR(190) NULL DEFAULT NULL',
        'شاغر حرج؟' => '+g9:VARCHAR(190) NULL DEFAULT NULL',
        'حالة السطر' => '+g10:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

array(
    'req' => 'WRK-16', 'route' => 'Workforce/housing_units.php',
    'table' => 'wf_housing_units',
    'anchor' => '<table id="emsList_wf_housing_units"',
    'rows' => "ems_w14_guide_rows('wf_housing_units')",
    'grid_id' => 'emsList_wf_housing_units', 'empty' => 'لا سطر مسجل بعد في وحدات السكن والإعاشة',
    'create' => true,
    'grain' => 'وحدات السكن والإعاشة',
    'map' => array(
        'كود الوحدة' => '+g11:VARCHAR(190) NULL DEFAULT NULL',
        'الموقع' => '+g12:VARCHAR(190) NULL DEFAULT NULL',
        'نوع الوحدة' => '+g13:VARCHAR(190) NULL DEFAULT NULL',
        'السعة' => '+g14:VARCHAR(190) NULL DEFAULT NULL',
        'المشغولة' => '+g15:VARCHAR(190) NULL DEFAULT NULL',
        'الشاغرة' => '+g16:VARCHAR(190) NULL DEFAULT NULL',
        'المشرف' => '+g17:VARCHAR(190) NULL DEFAULT NULL',
        'حالة الصيانة' => '+g18:VARCHAR(190) NULL DEFAULT NULL',
        'حالة الوحدة' => '+g19:VARCHAR(190) NULL DEFAULT NULL',
        'المنشئ' => '+g20:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ الإنشاء' => '+g21:VARCHAR(190) NULL DEFAULT NULL',
        'حالة البيانات' => '+g22:VARCHAR(190) NULL DEFAULT NULL',
        'مرجع المصدر' => '+g23:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

),
);
