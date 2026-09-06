<?php
/**
 * tests/dept_suite/manifest_financing.php — مانيفستُ «إدارة التمويل»  (DEP-03)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-03'`
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
 * التشغيل: php tests/dept_suite/run.php --dept=financing --quick
 * الأساس:  php tests/dept_suite/run.php --dept=financing --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'financing',
'dept_ar' => 'إدارة التمويل',
'user'    => 'تمويل',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Equipments ──────────────────────────────────────────────────
array(
  'route' => 'Equipments/fin_assets.php', 'label' => 'الأصول الممولة',
  'group' => 'Equipments',
  'view'  => array(),
),

// ── Financing ───────────────────────────────────────────────────
array(
  'route' => 'Financing/asset_disposal.php', 'label' => 'انتقال الملكية والخروج',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/cost_allocation.php', 'label' => 'توزيع السداد',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/deviations.php', 'label' => 'الانحرافات والمتأخرات',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_capital_balance.php', 'label' => 'رصيد رأس المال والعائد',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_changes.php', 'label' => 'تعديلات التمويل وإعادة الجدولة',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_close_audit.php', 'label' => 'تقرير المراجعة والإغلاق',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_contract_close.php', 'label' => 'الإقفالات التعاقدية',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_contract_terms.php', 'label' => 'بنود وشروط التمويل',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_contracts.php', 'label' => 'سجل عقود التمويل',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_covenants.php', 'label' => 'مصفوفة الالتزامات التمويلية',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_due_diligence.php', 'label' => 'وثائق التأهيل والعناية',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_final_close.php', 'label' => 'إقفال التمويل',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_financier_contacts.php', 'label' => 'جهات اتصال الممولين والمفوض',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_financier_dues.php', 'label' => 'استحقاقات الممول',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_migration_map.php', 'label' => 'خريطة الترحيل ومصفوفة التسوية',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_models.php', 'label' => 'نماذج التمويل ومعالجتها',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_monthly_close.php', 'label' => 'الإقفالات الشهرية وكشف الحس',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_needs.php', 'label' => 'فرص واحتياجات التمويل',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_offers.php', 'label' => 'عروض التمويل والتفاوض',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_payment_allocation.php', 'label' => 'تخصيص السداد على الأقساط',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_payment_orders.php', 'label' => 'أوامر الدفع والسداد الفعلي',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_portfolio_board.php', 'label' => 'التقارير ولوحة المحفظة',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_precontract_review.php', 'label' => 'مراجعة ما قبل التعاقد',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/fin_ref_dictionary.php', 'label' => 'القوائم وقاموس البيانات',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/financiers_registry.php', 'label' => 'سجل الممولين',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/financing_board.php', 'label' => 'لوحة إدارة التمويل',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/financing_operation_new.php', 'label' => 'إنشاء عملية تمويل',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/gov_dept_cap.php', 'label' => 'حوكمة التمويل والملكية',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/installments.php', 'label' => 'جدول الأقساط والاستحقاقات',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/operation_profile.php', 'label' => 'عمليات التمويل',
  'group' => 'Financing',
  'view'  => array(),
),
array(
  'route' => 'Financing/owners_registry.php', 'label' => 'حصص الملكية وحق الانتفاع',
  'group' => 'Financing',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_cap.php', 'label' => 'مخاطر التمويل والملكية',
  'group' => 'Risk',
  'view'  => array(),
),

),
);
