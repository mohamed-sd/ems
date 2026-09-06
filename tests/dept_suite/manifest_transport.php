<?php
/**
 * tests/dept_suite/manifest_transport.php — مانيفستُ «إدارة النقل والترحيل»  (DEP-15)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-15'`
 *   و`on_disk=1` — **20 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=transport --quick
 * الأساس:  php tests/dept_suite/run.php --dept=transport --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'transport',
'dept_ar' => 'إدارة النقل والترحيل',
'user'    => 'مشرف النقل',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_trp.php', 'label' => 'مخاطر النقل والترحيل',
  'group' => 'Risk',
  'view'  => array(),
),

// ── Transport ───────────────────────────────────────────────────
array(
  'route' => 'Transport/gov_dept_trp.php', 'label' => 'حوكمة النقل والترحيل',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_arrival.php', 'label' => 'محضر الاستلام وقراءة العدّاد',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_close_cost.php', 'label' => 'بنود تكلفة الرحلة',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_closure.php', 'label' => 'إقفال أمر الترحيل',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_cost_rules_config.php', 'label' => 'قواعد تحميل تكلفة الترحيل',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_damage_claims.php', 'label' => 'مطالبات التلف والحوادث',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_dashboard.php', 'label' => 'لوحة النقل والترحيل',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_fleet.php', 'label' => 'اللوابد والمركبات الناقلة',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_in_transit.php', 'label' => 'تتبع الرحلة وأحداثها',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_order_form.php', 'label' => 'أمر الترحيل',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_orders_list.php', 'label' => 'أوامر الترحيل',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_orders_report.php', 'label' => 'تقرير أوامر الترحيل',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_origin_handover.php', 'label' => 'تجهيز المغادرة والتسليم الأصلي',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_permits.php', 'label' => 'تصاريح المسار والحمولة',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_requests.php', 'label' => 'طلب الترحيل',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_tariffs.php', 'label' => 'تعرفة الترحيل وتسعير الأوامر',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_trip_legs.php', 'label' => 'مراحل الرحلة',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/transfer_types_config.php', 'label' => 'إعدادات الترحيل',
  'group' => 'Transport',
  'view'  => array(),
),
array(
  'route' => 'Transport/trs_locations_config.php', 'label' => 'المواقع',
  'group' => 'Transport',
  'view'  => array(),
),

),
);
