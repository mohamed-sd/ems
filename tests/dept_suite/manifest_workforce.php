<?php
/**
 * tests/dept_suite/manifest_workforce.php — مانيفستُ «القوى التشغيلية»  (DEP-13)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-13'`
 *   و`on_disk=1` — **36 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=workforce --quick
 * الأساس:  php tests/dept_suite/run.php --dept=workforce --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'workforce',
'dept_ar' => 'القوى التشغيلية',
'user'    => 'مدير القوى',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── movement ────────────────────────────────────────────────────
array(
  'route' => 'movement/move_oprators.php', 'label' => 'غرفة عمليات التشغيل',
  'group' => 'movement',
  'view'  => array(),
),

// ── Operations ──────────────────────────────────────────────────
array(
  'route' => 'Operations/shift_log.php', 'label' => 'سجل الوردية',
  'group' => 'Operations',
  'view'  => array(),
),

// ── Oprators ────────────────────────────────────────────────────
array(
  'route' => 'Oprators/oprators.php', 'label' => 'شاشة التشغيل',
  'group' => 'Oprators',
  'view'  => array(),
),
array(
  'route' => 'Oprators/select_project.php', 'label' => 'تخصيص المعدات للمشروعات',
  'group' => 'Oprators',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_wrk.php', 'label' => 'مخاطر القوى التشغيلية',
  'group' => 'Risk',
  'view'  => array(),
),

// ── Workforce ───────────────────────────────────────────────────
array(
  'route' => 'Workforce/employee_advances.php', 'label' => 'السلف والقروض',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/employee_settlements.php', 'label' => 'تسويات الموظفين',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/gov_dept_wrk.php', 'label' => 'حوكمة القوى التشغيلية',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/housing_units.php', 'label' => 'وحدات السكن والإعاشة',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/op_codes.php', 'label' => 'أرقام المشغلين الشاغرة',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/payroll_runs.php', 'label' => 'مسيّر الرواتب',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/proposed_deductions.php', 'label' => 'خصومات المشغلين المقترحة',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/recruitment_pipeline.php', 'label' => 'التوظيف من الشاغر إلى المباشرة',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/rotation.php', 'label' => 'دورات التناوب والإجازة الميدانية',
  'group' => 'Workforce',
  'view'  => array(),
),

// ── workforce ───────────────────────────────────────────────────
array(
  'route' => 'workforce/wf_board.php', 'label' => 'لوحة القوى التشغيلية',
  'group' => 'workforce',
  'view'  => array(),
),

// ── Workforce ───────────────────────────────────────────────────
array(
  'route' => 'Workforce/wf_coverage.php', 'label' => 'المطلوب مقابل المتوفر',
  'group' => 'Workforce',
  'view'  => array(),
),

// ── workforce ───────────────────────────────────────────────────
array(
  'route' => 'workforce/wf_daily_presence.php', 'label' => 'الحركة والتواجد اليومي',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_equipment_shift_assignment.php', 'label' => 'تخصيص المعدة والوردية',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_field_incidents.php', 'label' => 'وقائع الميدان والإحالة التأديبية',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_nomination.php', 'label' => 'الترشيح والاختيار للتغطية',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_performance.php', 'label' => 'أداء الأفراد التشغيلي',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_person_register.php', 'label' => 'سجل الأفراد التشغيليين',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_project_allocation.php', 'label' => 'التخصيص للمشروع',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_project_contracts_ref.php', 'label' => 'عقود المشاريع المرجعية',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_qualification_matrix.php', 'label' => 'مصفوفة التأهيل والجاهزية',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_qualifications.php', 'label' => 'التأهيل والشهادات',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_rotation_backup.php', 'label' => 'التناوب والبدلاء',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_settlement.php', 'label' => 'التسوية ونهاية التخصيص',
  'group' => 'workforce',
  'view'  => array(),
),
array(
  'route' => 'workforce/wf_taxonomy.php', 'label' => 'تصنيف الفئات التشغيلية',
  'group' => 'workforce',
  'view'  => array(),
),

// ── Workforce ───────────────────────────────────────────────────
array(
  'route' => 'Workforce/worker_contract.php', 'label' => 'عقود العاملين',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/worker_leave_absence.php', 'label' => 'الإجازات والغياب',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/worker_movement.php', 'label' => 'تنقلات العاملين',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/worker_register.php', 'label' => 'ملف العامل التشغيلي',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/worker_settlement.php', 'label' => 'تسويات العاملين',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/worker_worklog.php', 'label' => 'سجل الأحداث التشغيلية',
  'group' => 'Workforce',
  'view'  => array(),
),
array(
  'route' => 'Workforce/workforce_requirement.php', 'label' => 'احتياج المشروع من القوى',
  'group' => 'Workforce',
  'view'  => array(),
),

),
);
