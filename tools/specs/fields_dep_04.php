<?php
/**
 * fields_dep_04.php — مواصفةٌ مولَّدةٌ من `tools/govui_field_map_probe.php`
 * ◆ الخريطةُ من **تعليقاتِ أعمدةِ المخطَّطِ نفسِها** لا من نسخٍ بيد.
 * ⛔ وتُراجَع قبل التطبيق: عمودٌ اقتُرح بتغطيةٍ جزئيّةٍ قد يخطئ الحقل.
 */
return array(

'dept' => 'DEP-04',
'screens' => array(

array(
    'req' => 'FLEET-09', 'route' => 'Fleet/asset_use_rights.php',
    'table' => 'flt_asset_use_rights',
    'anchor' => '<table id="emsList_asset_use_rights"',
    'rows' => "ems_w14_guide_rows('flt_asset_use_rights')",
    'grid_id' => 'emsList_asset_use_rights', 'empty' => 'لا سطر مسجل بعد في حق الاستخدام التشغيلي',
    'create' => true,
    'grain' => 'حق الاستخدام التشغيلي',
    'map' => array(
        'كود الحصة' => '+g1:VARCHAR(190) NULL DEFAULT NULL',
        'كود الأصل' => '+g2:VARCHAR(190) NULL DEFAULT NULL',
        'الطرف المالك / صاحب حق الاستخدام' => '+g3:VARCHAR(190) NULL DEFAULT NULL',
        'صفة الطرف' => '+g4:VARCHAR(190) NULL DEFAULT NULL',
        'نسبة حق الاستخدام التشغيلي' => '+g5:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ الاكتساب' => '+g6:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ التخارج' => '+g7:VARCHAR(190) NULL DEFAULT NULL',
        'المشتري عند التخارج' => '+g8:VARCHAR(190) NULL DEFAULT NULL',
        'مرجع مستند الانتقال' => '+g9:VARCHAR(190) NULL DEFAULT NULL',
        'مجموع الحصص المتزامنة' => '+g10:VARCHAR(190) NULL DEFAULT NULL',
        'حالة التحقق' => '+g11:VARCHAR(190) NULL DEFAULT NULL',
        'المنشئ' => '+g12:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ الإنشاء' => '+g13:VARCHAR(190) NULL DEFAULT NULL',
        'المراجع' => '+g14:VARCHAR(190) NULL DEFAULT NULL',
        'المعتمد' => '+g15:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ الاعتماد' => '+g16:VARCHAR(190) NULL DEFAULT NULL',
        'أساس السجل' => '+g17:VARCHAR(190) NULL DEFAULT NULL',
        'مرجع المصدر' => '+g18:VARCHAR(190) NULL DEFAULT NULL',
        'حالة البيانات' => '+g19:VARCHAR(190) NULL DEFAULT NULL',
        'ملاحظات' => '+g20:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

array(
    'req' => 'FLEET-41', 'route' => 'Fleet/fleet_schema_matrix.php',
    'table' => 'flt_fleet_schema_matrix',
    'anchor' => '<table id="emsList_flt_fleet_schema_matrix"',
    'rows' => "ems_w14_guide_rows('flt_fleet_schema_matrix')",
    'grid_id' => 'emsList_flt_fleet_schema_matrix', 'empty' => 'لا سطر مسجل بعد في مصفوفة بنية الشيتات',
    'create' => true,
    'grain' => 'مصفوفة بنية الشيتات',
    'map' => array(
        'الشيت' => '+g21:VARCHAR(190) NULL DEFAULT NULL',
        'الاسم' => '+g22:VARCHAR(190) NULL DEFAULT NULL',
        'الكتلة' => '+g23:VARCHAR(190) NULL DEFAULT NULL',
        'Grain ماذا يمثل الصف؟' => '+g24:VARCHAR(190) NULL DEFAULT NULL',
        'PK' => '+g25:VARCHAR(190) NULL DEFAULT NULL',
        'FKs' => '+g26:VARCHAR(190) NULL DEFAULT NULL',
        'مصدر الحقيقة' => '+g27:VARCHAR(190) NULL DEFAULT NULL',
        'المالك' => '+g28:VARCHAR(190) NULL DEFAULT NULL',
        'يسبقه' => '+g29:VARCHAR(190) NULL DEFAULT NULL',
        'يليه' => '+g30:VARCHAR(190) NULL DEFAULT NULL',
        'صفوف فعلية' => '+g31:VARCHAR(190) NULL DEFAULT NULL',
        'الحكم' => '+g32:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

array(
    'req' => 'FLEET-28', 'route' => 'Fleet/asset_full_history.php',
    'table' => 'flt_asset_full_history',
    'anchor' => '<table id="emsList_flt_asset_full_history"',
    'rows' => "ems_w14_guide_rows('flt_asset_full_history')",
    'grid_id' => 'emsList_flt_asset_full_history', 'empty' => 'لا سطر مسجل بعد في تاريخ المعدة الكامل',
    'create' => true,
    'grain' => 'لا سطر مسجل بعد في تاريخ المعدة الكامل',
    'map' => array(
        'كود الأصل' => '+g33:VARCHAR(190) NULL DEFAULT NULL',
        'تسلسل الواقعة' => '+g34:VARCHAR(190) NULL DEFAULT NULL',
        'التاريخ' => '+g35:VARCHAR(190) NULL DEFAULT NULL',
        'نوع الواقعة' => '+g36:VARCHAR(190) NULL DEFAULT NULL',
        'الشيت المصدر' => '+g37:VARCHAR(190) NULL DEFAULT NULL',
        'مرجع السجل' => '+g38:VARCHAR(190) NULL DEFAULT NULL',
        'وصف الواقعة' => '+g39:VARCHAR(190) NULL DEFAULT NULL',
        'قراءة العداد' => '+g40:VARCHAR(190) NULL DEFAULT NULL',
        'الموقع' => '+g41:VARCHAR(190) NULL DEFAULT NULL',
        'المشروع' => '+g42:VARCHAR(190) NULL DEFAULT NULL',
        'الوحدة التعاقدية' => '+g43:VARCHAR(190) NULL DEFAULT NULL',
        'الحالة بعد الواقعة' => '+g44:VARCHAR(190) NULL DEFAULT NULL',
        'المسؤول' => '+g45:VARCHAR(190) NULL DEFAULT NULL',
        'المستند' => '+g46:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

),
);
