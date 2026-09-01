<?php
/**
 * fields_ws_my.php — مواصفةٌ مولَّدةٌ من `tools/govui_field_map_probe.php`
 * ◆ الخريطةُ من **تعليقاتِ أعمدةِ المخطَّطِ نفسِها** لا من نسخٍ بيد.
 * ⛔ وتُراجَع قبل التطبيق: عمودٌ اقتُرح بتغطيةٍ جزئيّةٍ قد يخطئ الحقل.
 */
return array(

'dept' => 'WS-MY',
'screens' => array(

array(
    'req' => 'MY-05', 'route' => 'Portal/my_reports.php',
    'table' => 'my_reports',
    'anchor' => '<table id="emsList_my_reports"',
    'rows' => "ems_w14_guide_rows('my_reports')",
    'grid_id' => 'emsList_my_reports', 'empty' => 'لا سطر مسجل بعد في البلاغات المسجَّلة',
    'create' => true,
    'grain' => 'لا سطر مسجل بعد في البلاغات المسجَّلة',
    'map' => array(
        'رقم البلاغ' => '+g1:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ التسجيل' => '+g2:VARCHAR(190) NULL DEFAULT NULL',
        'الفئة' => '+g3:VARCHAR(190) NULL DEFAULT NULL',
        'الطبيعة' => '+g4:VARCHAR(190) NULL DEFAULT NULL',
        'ملخص البلاغ' => '+g5:VARCHAR(190) NULL DEFAULT NULL',
        'الإدارة المعالجة' => '+g6:VARCHAR(190) NULL DEFAULT NULL',
        'حالة البلاغ' => '+g7:VARCHAR(190) NULL DEFAULT NULL',
        'ينتظر تأكيدي؟' => '+g8:VARCHAR(190) NULL DEFAULT NULL',
        'تأكيد الإغلاق' => '+g9:VARCHAR(190) NULL DEFAULT NULL',
        'تقييم الرضا' => '+g10:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

array(
    'req' => 'MY-01', 'route' => 'Portal/my_achievement.php',
    'table' => 'my_achievement',
    'anchor' => '<table id="emsList_my_achievement"',
    'rows' => "ems_w14_guide_rows('my_achievement')",
    'grid_id' => 'emsList_my_achievement', 'empty' => 'لا سطر مسجل بعد في مؤشرات الإنجاز الشخصي',
    'create' => true,
    'grain' => 'مؤشرات الإنجاز الشخصي',
    'map' => array(
        'معرف السطر' => '+g11:VARCHAR(190) NULL DEFAULT NULL',
        'الحساب' => '+g12:VARCHAR(190) NULL DEFAULT NULL',
        'المدى' => '+g13:VARCHAR(190) NULL DEFAULT NULL',
        'مؤشر الإنجاز' => '+g14:VARCHAR(190) NULL DEFAULT NULL',
        'القيمة' => '+g15:VARCHAR(190) NULL DEFAULT NULL',
        'الوحدة' => '+g16:VARCHAR(190) NULL DEFAULT NULL',
        'بلغة الدور' => '+g17:VARCHAR(190) NULL DEFAULT NULL',
        'مقارنة بالمدى السابق' => '+g18:VARCHAR(190) NULL DEFAULT NULL',
        'آخر تحديث' => '+g19:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

array(
    'req' => 'MY-02', 'route' => 'Portal/my_portal.php',
    'table' => 'my_portal',
    'anchor' => '<table id="emsList_my_portal"',
    'rows' => "ems_w14_guide_rows('my_portal')",
    'grid_id' => 'emsList_my_portal', 'empty' => 'لا سطر مسجل بعد في البوابة الشخصية',
    'create' => true,
    'grain' => 'البوابة الشخصية',
    'map' => array(
        'معرف المكون' => '+g20:VARCHAR(190) NULL DEFAULT NULL',
        'الحساب' => '+g21:VARCHAR(190) NULL DEFAULT NULL',
        'الدور' => '+g22:VARCHAR(190) NULL DEFAULT NULL',
        'المكون' => '+g23:VARCHAR(190) NULL DEFAULT NULL',
        'محتواه الحي' => '+g24:VARCHAR(190) NULL DEFAULT NULL',
        'مصدره' => '+g25:VARCHAR(190) NULL DEFAULT NULL',
        'آخر تحديث' => '+g26:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

array(
    'req' => 'MY-03', 'route' => 'Portal/my_tasks.php',
    'table' => 'my_tasks',
    'anchor' => '<table id="emsList_my_tasks"',
    'rows' => "ems_w14_guide_rows('my_tasks')",
    'grid_id' => 'emsList_my_tasks', 'empty' => 'لا سطر مسجل بعد في المهام المسنَدة',
    'create' => true,
    'grain' => 'المهام المسنَدة',
    'map' => array(
        'معرف المهمة' => '+g27:VARCHAR(190) NULL DEFAULT NULL',
        'نوع المهمة' => '+g28:VARCHAR(190) NULL DEFAULT NULL',
        'مصدر المهمة' => '+g29:VARCHAR(190) NULL DEFAULT NULL',
        'الشاشة الأصلية' => '+g30:VARCHAR(190) NULL DEFAULT NULL',
        'المرجع' => '+g31:VARCHAR(190) NULL DEFAULT NULL',
        'مهلة المهمة' => '+g32:VARCHAR(190) NULL DEFAULT NULL',
        'الأولوية' => '+g33:VARCHAR(190) NULL DEFAULT NULL',
        'حالة المهمة' => '+g34:VARCHAR(190) NULL DEFAULT NULL',
        'سبب التأجيل' => '+g35:VARCHAR(190) NULL DEFAULT NULL',
        'وقت الإنجاز' => '+g36:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

array(
    'req' => 'MY-06', 'route' => 'user_capacities.php',
    'table' => 'my_user_capacities',
    'anchor' => '<table id="emsList_my_user_capacities"',
    'rows' => "ems_w14_guide_rows('my_user_capacities')",
    'grid_id' => 'emsList_my_user_capacities', 'empty' => 'لا سطر مسجل بعد في الصفات الوظيفية والتبديل بينها',
    'create' => true,
    'grain' => 'الصفات الوظيفية والتبديل بينها',
    'map' => array(
        'معرف الصفة' => '+g37:VARCHAR(190) NULL DEFAULT NULL',
        'الصفة' => '+g38:VARCHAR(190) NULL DEFAULT NULL',
        'مصدرها' => '+g39:VARCHAR(190) NULL DEFAULT NULL',
        'النطاق' => '+g40:VARCHAR(190) NULL DEFAULT NULL',
        'سارية من' => '+g41:VARCHAR(190) NULL DEFAULT NULL',
        'إلى' => '+g42:VARCHAR(190) NULL DEFAULT NULL',
        'نشطة الآن؟' => '+g43:VARCHAR(190) NULL DEFAULT NULL',
        'آخر تبديل' => '+g44:VARCHAR(190) NULL DEFAULT NULL',
        'مرجع التفويض عند الإنابة' => '+g45:VARCHAR(190) NULL DEFAULT NULL',
        'حالة الصفة' => '+g46:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

array(
    'req' => 'MY-04', 'route' => 'FinRequests/request_form.php',
    'table' => 'my_requests',
    'anchor' => '<table id="emsList_my_requests"',
    'rows' => "ems_w14_guide_rows('my_requests')",
    'grid_id' => 'emsList_my_requests', 'empty' => 'لا سطر مسجل بعد في الطلبات المقدَّمة',
    'create' => true,
    'grain' => 'لا سطر مسجل بعد في الطلبات المقدَّمة',
    'map' => array(
        'رقم الطلب' => '+g47:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ الطلب' => '+g48:VARCHAR(190) NULL DEFAULT NULL',
        'نوع الطلب' => '+g49:VARCHAR(190) NULL DEFAULT NULL',
        'تفاصيل الطلب' => '+g50:VARCHAR(190) NULL DEFAULT NULL',
        'المرفق' => '+g51:VARCHAR(190) NULL DEFAULT NULL',
        'الجهة المالكة للقرار' => '+g52:VARCHAR(190) NULL DEFAULT NULL',
        'مسار الاعتماد' => '+g53:VARCHAR(190) NULL DEFAULT NULL',
        'حالة الطلب' => '+g54:VARCHAR(190) NULL DEFAULT NULL',
        'قرار الجهة' => '+g55:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ القرار' => '+g56:VARCHAR(190) NULL DEFAULT NULL',
        'المنشئ' => '+g57:VARCHAR(190) NULL DEFAULT NULL',
        'تاريخ الإنشاء' => '+g58:VARCHAR(190) NULL DEFAULT NULL',
        'حالة البيانات' => '+g59:VARCHAR(190) NULL DEFAULT NULL',
        'مرجع المصدر' => '+g60:VARCHAR(190) NULL DEFAULT NULL',
    ),
),

),
);
