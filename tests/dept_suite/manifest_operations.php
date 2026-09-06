<?php
/**
 * tests/dept_suite/manifest_operations.php — مانيفستُ «ادارة التشغيل · مشرف - مشاريع»  (DEP-11)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-11'`
 *   و`on_disk=1` — **33 سطحًا**. لا اجتهادَ في العضوية.
 *
 * ◆ **عمقُ الفحصِ الآن: التصييرُ وحدَه** — لكلِّ سطحٍ يُقاس:
 *   ① `HTTP 200` أم ردٌّ بحارسٍ أم خطأُ خادم · ② صفرُ تحذيرِ PHP في الجسد.
 *   وهذا يكشف **الانحدارَ** (سطحٌ كان يُصيَّر فصار يُردُّ أو يشتكي) وهو أكثرُ
 *   ما يقع بعدَ التعديلات.
 *
 * ⛔ **ولا `crud` مخمَّنٌ هنا**: حمولةُ كلِّ نموذجٍ تختلف حقلًا حقلًا، وتخمينُها
 *   يُنتج فشلًا كاذبًا يُفسِد خطَّ الأساس. فالخانةُ **فارغةٌ مصرَّحٌ بها**.
 *
 * ◆ **كيف تُعمَّق شاشةٌ** (انسخِ النمطَ من `manifest_sales.php`):
 *   ① أضِفْ للسطحِ `'crud' => array('add' => array('post' => array(...),
 *      'verify' => array('table' => '...', 'where' => "...='{MARK}'")), ...)`.
 *   ② أضِفْ جدولَ ذلك السطحِ إلى `sweep` بعمودِ وسمِه النصّيّ — وإلّا
 *      بقيت صفوفُ الفحصِ في قاعدتك.
 *   ③ وأضِفْ `guard` (اختبارٌ سالبٌ) وإلّا لم تقس أنّ الحارسَ يمنع.
 *
 * التشغيل: php tests/dept_suite/run.php --dept=operations --quick
 * الأساس:  php tests/dept_suite/run.php --dept=operations --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'operations',
'dept_ar' => 'ادارة التشغيل · مشرف - مشاريع',
'user'    => 'محمد',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── movement ────────────────────────────────────────────────────
array(
  'route' => 'movement/map_page.php', 'label' => 'خريطة التشغيل اليومية',
  'group' => 'movement',
  'view'  => array(),
),
array(
  'route' => 'movement/movement_operations.php', 'label' => 'الورديات',
  'group' => 'movement',
  'view'  => array(),
),
array(
  'route' => 'movement/project_drivers.php', 'label' => 'توزيع المشغّلين',
  'group' => 'movement',
  'view'  => array(),
),

// ── Operations ──────────────────────────────────────────────────
array(
  'route' => 'Operations/containers.php', 'label' => 'إسناد المعدات للوحدات',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/daily_plan.php', 'label' => 'خطة عمل الغد',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/distribution_space.php', 'label' => 'تكليف المشغل على المعدة',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/fleet_calendar.php', 'label' => 'تقويم الأسطول والحجز',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/fleet_utilization.php', 'label' => 'استغلال الأسطول ومردوده',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/gov_dept_ops.php', 'label' => 'حوكمة إدارة التشغيل',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/monthly_plan.php', 'label' => 'الخطة الشهرية للتشغيل',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/operations_room.php', 'label' => 'غرفة عمليات المواقع',
  'group' => 'Operations',
  'view'  => array(),
),

// ── operations ──────────────────────────────────────────────────
array(
  'route' => 'operations/ops_deviation_escalation.php', 'label' => 'تقرير الانحراف والتصعيد',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/ops_resource_move_orders.php', 'label' => 'طلب وقرار حركة الموارد',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/ops_room.php', 'label' => 'غرفة العمليات',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/ops_seasonal_factors.php', 'label' => 'الخطة الموسمية ومعاملاتها',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/ops_shift_time_states.php', 'label' => 'توزيع زمن الوردية وحالاته',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/ops_stop_decisions.php', 'label' => 'قرارات التوقف',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/ops_tomorrow_dispatch.php', 'label' => 'احتياج الغد وتوزيع الموارد',
  'group' => 'operations',
  'view'  => array(),
),

// ── Operations ──────────────────────────────────────────────────
array(
  'route' => 'Operations/production.php', 'label' => 'الإنتاج والقياس',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/stops_unattributed.php', 'label' => 'التوقفات وتحديد المتحمل',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/unit_correction.php', 'label' => 'تصحيح الوحدات بالسلسلة الثلاثية',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/unit_perf.php', 'label' => 'الأداء الشهري للوحدة',
  'group' => 'Operations',
  'view'  => array(),
),

// ── Projects ────────────────────────────────────────────────────
array(
  'route' => 'Projects/project_profile.php', 'label' => 'بطاقة المشروع',
  'group' => 'Projects',
  'view'  => array(),
),

// ── Reports ─────────────────────────────────────────────────────
array(
  'route' => 'Reports/daily_units_report.php', 'label' => 'سجل الوحدات اليومية',
  'group' => 'Reports',
  'view'  => array(),
),
array(
  'route' => 'Reports/deliy.php', 'label' => 'deliy',
  'group' => 'Reports',
  'view'  => array(),
),
array(
  'route' => 'Reports/deriver.php', 'label' => 'deriver',
  'group' => 'Reports',
  'view'  => array(),
),
array(
  'route' => 'Reports/driverAndsupplerscontract.php', 'label' => 'driverAndsupplerscontract',
  'group' => 'Reports',
  'view'  => array(),
),
array(
  'route' => 'Reports/new_reports.php', 'label' => 'new_reports',
  'group' => 'Reports',
  'view'  => array(),
),
array(
  'route' => 'Reports/projects_reports.php', 'label' => 'projects_reports',
  'group' => 'Reports',
  'view'  => array(),
),
array(
  'route' => 'Reports/timesheet_reports.php', 'label' => 'timesheet_reports',
  'group' => 'Reports',
  'view'  => array(),
),
array(
  'route' => 'Reports/timesheetdeliy.php', 'label' => 'تقرير الوحدات اليومية',
  'group' => 'Reports',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_ops.php', 'label' => 'المخاطر التشغيلية',
  'group' => 'Risk',
  'view'  => array(),
),

// ── Timesheet ───────────────────────────────────────────────────
array(
  'route' => 'Timesheet/view_timesheet.php', 'label' => 'تسجيل التايم شيت والإنتاج',
  'group' => 'Timesheet',
  'view'  => array(),
),

),
);
