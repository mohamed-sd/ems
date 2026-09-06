<?php
/**
 * tests/dept_suite/manifest_audit.php — مانيفستُ «المراجع الداخلي المستقل»  (IAF)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='IAF'`
 *   و`on_disk=1` — **21 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=audit --quick
 * الأساس:  php tests/dept_suite/run.php --dept=audit --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'audit',
'dept_ar' => 'المراجع الداخلي المستقل',
'user'    => 'مراجع',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Audit ───────────────────────────────────────────────────────
array(
  'route' => 'Audit/iaf_access_log.php', 'label' => 'سجل الاطلاع الحساس للمراجعين',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_action_plans.php', 'label' => 'خطط المعالجة والمتابعة',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_audit_programs.php', 'label' => 'برامج المراجعة',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_authorities.php', 'label' => 'صلاحيات المراجع داخل النظام',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_charter.php', 'label' => 'ميثاق المراجعة الداخلية',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_closure.php', 'label' => 'محاضر الإغلاق',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_competencies.php', 'label' => 'اختصاصات المراجعة العشرون',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_engagements.php', 'label' => 'مهام المراجعة',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_escalations.php', 'label' => 'الملاحظات المتأخرة والتصعيد',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_evidence_requests.php', 'label' => 'طلبات الأدلة',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_findings.php', 'label' => 'الملاحظات',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_function_risks.php', 'label' => 'مخاطر وظيفة المراجعة',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_independence.php', 'label' => 'سجل الاستقلال وتعارض المصالح',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_overview.php', 'label' => 'لوحة المراجعة الداخلية',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_plan.php', 'label' => 'الخطة السنوية للمراجعة',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_quality.php', 'label' => 'تقييم الجودة',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_reports.php', 'label' => 'تقارير المراجعة',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_responses.php', 'label' => 'ردود الإدارات على الملاحظات',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_test_samples.php', 'label' => 'العينات ونتائج الاختبارات',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_universe.php', 'label' => 'سجل الكون الرقابي',
  'group' => 'Audit',
  'view'  => array(),
),
array(
  'route' => 'Audit/iaf_workpapers.php', 'label' => 'أوراق العمل',
  'group' => 'Audit',
  'view'  => array(),
),

),
);
