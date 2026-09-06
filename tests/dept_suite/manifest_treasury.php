<?php
/**
 * tests/dept_suite/manifest_treasury.php — مانيفستُ «أمين الخزينة»  (DEP-06)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-06'`
 *   و`on_disk=1` — **23 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=treasury --quick
 * الأساس:  php tests/dept_suite/run.php --dept=treasury --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'treasury',
'dept_ar' => 'أمين الخزينة',
'user'    => 'fin.treasury@equipation.sd',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── الحسابات والقيود ────────────────────────────────────────────
array(
  'route' => 'Finance/accounts_fin.php', 'label' => 'الخزينة والصناديق',
  'group' => 'الحسابات والقيود',
  'view'  => array(),
),

// ── الذمم والمدفوعات ────────────────────────────────────────────
array(
  'route' => 'Finance/bank_reconciliation_fin.php', 'label' => 'المطابقة البنكية',
  'group' => 'الذمم والمدفوعات',
  'view'  => array(),
),

// ── الأصول والتمويل ─────────────────────────────────────────────
array(
  'route' => 'Finance/cash_forecast_fin.php', 'label' => 'خطة السيولة والتدفق',
  'group' => 'الأصول والتمويل',
  'view'  => array(),
),

// ── الذمم والمدفوعات ────────────────────────────────────────────
array(
  'route' => 'Finance/payments_fin.php', 'label' => 'طلبات الدفع والسداد',
  'group' => 'الذمم والمدفوعات',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/tre_allocations.php', 'label' => 'تخصيص التحصيل على الفواتير',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_authority_caps.php', 'label' => 'سقوف سلطة الالتزام والدفع',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_beneficiary.php', 'label' => 'سجل المستفيدين والتحقق',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_cash_count.php', 'label' => 'الجرد النقدي للخزائن',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_cash_moves.php', 'label' => 'حركة الخزينة والصناديق',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_cycle_stages.php', 'label' => 'مراحل دورتي الدفع والقبض',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_facilities.php', 'label' => 'التسهيلات البنكية',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_fx_deals.php', 'label' => 'تنفيذ عمليات الصرف الأجنبي',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_guarantees.php', 'label' => 'خطابات الضمان والاعتمادات المستندية',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_instruments.php', 'label' => 'سجل الأدوات المالية',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_liquidity_board.php', 'label' => 'لوحة الخزينة والسيولة',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_pay_batch.php', 'label' => 'أمر الدفع والتنفيذ',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_payment_queue.php', 'label' => 'مدفوعات معتمدة بانتظار التنفيذ',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_petty_cash.php', 'label' => 'عهد النثرية وتسويتها',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_sod_matrix.php', 'label' => 'مصفوفة فصل الواجبات الثلاثة عشر',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_transfers.php', 'label' => 'التحويلات بين الحسابات',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_unit_roles.php', 'label' => 'الأدوار الثمانية داخل وحدة الخزينة',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/tre_vessels.php', 'label' => 'الحسابات البنكية والصناديق',
  'group' => 'Finance',
  'view'  => array(),
),

// ── vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/Financial/CashFlow/Constant/Periodic ─
array(
  'route' => 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/Financial/CashFlow/Constant/Periodic/Payments.php', 'label' => 'Payments',
  'group' => 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/Financial/CashFlow/Constant/Periodic',
  'view'  => array(),
),

),
);
