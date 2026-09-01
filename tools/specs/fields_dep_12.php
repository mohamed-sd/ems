<?php
/**
 * fields_dep_12.php — مواصفةٌ مولَّدةٌ من `tools/govui_field_map_probe.php`
 * ◆ الخريطةُ من **تعليقاتِ أعمدةِ المخطَّطِ نفسِها** لا من نسخٍ بيد.
 * ⛔ وتُراجَع قبل التطبيق: عمودٌ اقتُرح بتغطيةٍ جزئيّةٍ قد يخطئ الحقل.
 */
return array(

'dept' => 'DEP-12',
'screens' => array(

array(
    'req' => 'SITE-04', 'route' => 'Operations/site_gate_equip.php',
    'table' => 'site_gate_equip',
    'anchor' => '<table id="emsList_site_gate_equip"',
    'rows' => "ems_w14_guide_rows('site_gate_equip')",
    'grid_id' => 'emsList_site_gate_equip', 'empty' => 'لا سطر مسجل بعد في أذون دخول وخروج المعدات والمشغّلين',
    'create' => true,
    'grain' => 'أذون دخول وخروج المعدات والمشغّلين',
    'map' => array(
        'رقم الإذن' => '+g1:VARCHAR(190) NULL DEFAULT NULL',
        'نوع الكيان' => '+g2:VARCHAR(190) NULL DEFAULT NULL',
        'مرجع الكيان' => '+g3:VARCHAR(190) NULL DEFAULT NULL',
        'اتجاه الحركة' => '+g4:VARCHAR(190) NULL DEFAULT NULL',
        'وقت الحركة' => '+g5:VARCHAR(190) NULL DEFAULT NULL',
        'كود الموقع' => '+g6:VARCHAR(190) NULL DEFAULT NULL',
        'مرجع التخصيص الساري' => '+g7:VARCHAR(190) NULL DEFAULT NULL',
        'مطابقة التخصيص' => '+g8:VARCHAR(190) NULL DEFAULT NULL',
        'مرافق/سائق' => '+g9:VARCHAR(190) NULL DEFAULT NULL',
        'الغرض' => '+g10:VARCHAR(190) NULL DEFAULT NULL',
        'مصدر الإذن' => '+g11:VARCHAR(190) NULL DEFAULT NULL',
        'واقعة بلا إذن؟' => '+g12:VARCHAR(190) NULL DEFAULT NULL',
        'حالة الإذن' => '+g13:VARCHAR(190) NULL DEFAULT NULL',
        'المنشئ' => '+g14:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ الإنشاء' => '+g15:VARCHAR(190) NULL DEFAULT NULL',
        'حالة البيانات' => '+g16:VARCHAR(190) NULL DEFAULT NULL',
        'مرجع المصدر' => '+g17:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

),
);
