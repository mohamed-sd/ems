<?php
/**
 * tests/dept_suite/manifest_warehouse.php — مانيفستُ «أمين المستودع»  (DEP-17)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-17'`
 *   و`on_disk=1` — **15 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=warehouse --quick
 * الأساس:  php tests/dept_suite/run.php --dept=warehouse --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'warehouse',
'dept_ar' => 'أمين المستودع',
'user'    => 'أمين المستودع',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Procurement ─────────────────────────────────────────────────
array(
  'route' => 'Procurement/consumption_rate.php', 'label' => 'استهلاك المعدة ومعدله',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/gov_dept_inv.php', 'label' => 'حوكمة إدارة المخازن',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/proc_track_policy.php', 'label' => 'سياسة تتبع الأصناف',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/warehouses.php', 'label' => 'سجل المخازن وأنواعها',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_count.php', 'label' => 'الجرد ومعالجة الفروقات',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_custodians.php', 'label' => 'إسناد أمناء المخازن',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_hazmat.php', 'label' => 'ضوابط المواد الخطرة والمتفجرات',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_issue_requests.php', 'label' => 'طلبات الصرف الواردة',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_lots.php', 'label' => 'سجل الدفعات',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_month_close.php', 'label' => 'الإقفال الشهري للمخازن',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_returns.php', 'label' => 'المرتجعات',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_serials.php', 'label' => 'سجل الأرقام التسلسلية',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_track_quality.php', 'label' => 'جودة بيانات التتبع',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_transfer.php', 'label' => 'التحويل بين المخازن',
  'group' => 'Procurement',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_inv.php', 'label' => 'مخاطر المخازن',
  'group' => 'Risk',
  'view'  => array(),
),

),
);
