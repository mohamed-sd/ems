<?php
/**
 * tests/dept_suite/manifest_hr.php — مانيفستُ «ادارة الموارد البشرية»  (DEP-07)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-07'`
 *   و`on_disk=1` — **35 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=hr --quick
 * الأساس:  php tests/dept_suite/run.php --dept=hr --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'hr',
'dept_ar' => 'ادارة الموارد البشرية',
'user'    => 'اروينا',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Employees ───────────────────────────────────────────────────
array(
  'route' => 'Employees/employee_card.php', 'label' => 'بطاقة الموظف',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/employee_contracts_details.php', 'label' => 'ملف عقد الموظف',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/employee_contracts.php', 'label' => 'عقود الموظفين',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/employee_equipment_history.php', 'label' => 'سجل قيادة السائق',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/employee_profile.php', 'label' => 'بطاقة الموظف',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/employee_roles.php', 'label' => 'الأدوار الوظيفية',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/employees.php', 'label' => 'سجل الموظفين',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/equipment_operators.php', 'label' => 'سجل المشغلين',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/hr_benefits.php', 'label' => 'المزايا والتأمينات',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/hr_board.php', 'label' => 'لوحة الموارد البشرية',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/hr_disciplinary.php', 'label' => 'القضايا التأديبية والتحقيق',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/hr_employee_documents.php', 'label' => 'مستندات الموظف',
  'group' => 'Employees',
  'view'  => array(),
),

// ── employees ───────────────────────────────────────────────────
array(
  'route' => 'employees/hr_headcount_plan.php', 'label' => 'خطة القوى العاملة',
  'group' => 'employees',
  'view'  => array(),
),

// ── Employees ───────────────────────────────────────────────────
array(
  'route' => 'Employees/hr_job_movements.php', 'label' => 'الحركات الوظيفية',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/hr_onboarding.php', 'label' => 'التهيئة والمباشرة',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/hr_performance.php', 'label' => 'تقييم الأداء الوظيفي',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/hr_training.php', 'label' => 'التدريب والكفاءة',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/job_titles.php', 'label' => 'الهيكل الوظيفي والمناصب',
  'group' => 'Employees',
  'view'  => array(),
),
array(
  'route' => 'Employees/showcontractemployee.php', 'label' => 'عرض عقد الموظف',
  'group' => 'Employees',
  'view'  => array(),
),

// ── Equipments ──────────────────────────────────────────────────
array(
  'route' => 'Equipments/equipment_profile.php', 'label' => 'بطاقة المعدة',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/equipments.php', 'label' => 'سجل المعدات',
  'group' => 'Equipments',
  'view'  => array(),
),

// ── movement ────────────────────────────────────────────────────
array(
  'route' => 'movement/add_drivers.php', 'label' => 'مشغّلو المعدة',
  'group' => 'movement',
  'view'  => array(),
),

// ── Operations ──────────────────────────────────────────────────
array(
  'route' => 'Operations/attendance.php', 'label' => 'الحضور والانصراف',
  'group' => 'Operations',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_hrm.php', 'label' => 'مخاطر الموارد البشرية',
  'group' => 'Risk',
  'view'  => array(),
),

// ── Workforce ───────────────────────────────────────────────────
array(
  'route' => 'Workforce/deductions.php', 'label' => 'خصومات المسيّر',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/final_settlement.php', 'label' => 'التصفية النهائية',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/gov_dept_hrm.php', 'label' => 'حوكمة الموارد البشرية',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/hr_workforce_report.php', 'label' => 'تقرير القوى العاملة',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/op_monthly.php', 'label' => 'الأداء الشهري للمشغل',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/op_qual.php', 'label' => 'تأهيل المشغلين على أنواع المعدات',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/payroll_lines.php', 'label' => 'أسطر مسيّر الرواتب',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/project_contracts.php', 'label' => 'عقود المشاريع المؤقتة',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/rec_applications.php', 'label' => 'طلبات الترشح',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/rec_stages.php', 'label' => 'مراحل التوظيف',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/worker_evaluation.php', 'label' => 'تقييم أداء العاملين',
  'group' => 'Workforce',
  'view'  => array(),
),

),
);
