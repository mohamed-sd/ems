<?php
/**
 * tests/dept_suite/manifest_risk.php — مانيفستُ «إدارة المخاطر · محلل المخاطر · مشرف المخاطر»  (DEP-09)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-09'`
 *   و`on_disk=1` — **25 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=risk --quick
 * الأساس:  php tests/dept_suite/run.php --dept=risk --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'risk',
'dept_ar' => 'إدارة المخاطر · محلل المخاطر · مشرف المخاطر',
'user'    => 'مخاطر',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── risk ────────────────────────────────────────────────────────
array(
  'route' => 'risk/dept_risk_space.php', 'label' => 'مساحة مخاطر الإدارة',
  'group' => 'risk',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/gov_dept_rsk.php', 'label' => 'حوكمة إدارة المخاطر المؤسسية',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_acceptance.php', 'label' => 'قبول المخاطر',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_appetite.php', 'label' => 'الشهية وحدود التحمل',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_assessment.php', 'label' => 'تقييم المخاطر',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_board.php', 'label' => 'لوحة المخاطر المؤسسية',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_card.php', 'label' => 'ملف الخطر',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_closure.php', 'label' => 'سجل الإغلاق والأدلة',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_committee.php', 'label' => 'لجنة المخاطر',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_control_verify.php', 'label' => 'التحقق من الضوابط الحرجة',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_controls.php', 'label' => 'الضوابط الرقابية',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_dept_ceo.php', 'label' => 'المخاطر المؤسسية',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_escalations.php', 'label' => 'تصعيدات المخاطر',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_events.php', 'label' => 'أحداث المخاطر والخسائر',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_field.php', 'label' => 'مخاطر الميدان',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_incidents.php', 'label' => 'الحوادث والوقائع',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_kris.php', 'label' => 'مؤشرات المخاطر الرئيسة',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_register.php', 'label' => 'سجل المخاطر المؤسسي',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_reports.php', 'label' => 'تقارير المخاطر الدورية',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_reviews.php', 'label' => 'المراجعة الدورية وإعادة التقييم',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_settings.php', 'label' => 'إعدادات المخاطر والتصنيف',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_signals.php', 'label' => 'إشارات المخاطر والفرز',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_taxonomy.php', 'label' => 'تصنيف المخاطر',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_treatments.php', 'label' => 'خطط معالجة المخاطر',
  'group' => 'Risk',
  'view'  => array(),
),
array(
  'route' => 'Risk/risk_units.php', 'label' => 'وحدات المخاطر والتصنيف',
  'group' => 'Risk',
  'view'  => array(),
),

),
);
