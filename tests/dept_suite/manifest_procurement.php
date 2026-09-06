<?php
/**
 * tests/dept_suite/manifest_procurement.php — مانيفستُ «إدارة المشتريات»  (DEP-16)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-16'`
 *   و`on_disk=1` — **24 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=procurement --quick
 * الأساس:  php tests/dept_suite/run.php --dept=procurement --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'procurement',
'dept_ar' => 'إدارة المشتريات',
'user'    => 'مشرف المشتريات',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Procurement ─────────────────────────────────────────────────
array(
  'route' => 'Procurement/dashboard_proc.php', 'label' => 'لوحة المشتريات',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/gov_dept_prc.php', 'label' => 'حوكمة إدارة المشتريات التشغيلية',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/issue_proc.php', 'label' => 'سند الصرف',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/items_proc.php', 'label' => 'دليل الأصناف',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/master_data_proc.php', 'label' => 'المخازن وأنواعها',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/orders_proc.php', 'label' => 'أوامر الشراء',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/po_match.php', 'label' => 'مطابقة الفاتورة الثلاثية',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/proc_award_minutes.php', 'label' => 'محضر المقارنة والترسية',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/proc_delivery_track.php', 'label' => 'متابعة التوريد والاستلام',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/proc_offers.php', 'label' => 'عروض الموردين المستلمة',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/proc_packages.php', 'label' => 'تجميع الطلبات وخطة الشراء',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/proc_po_amendments.php', 'label' => 'استثناءات الشراء وتعديلات الأوامر',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/proc_rfq.php', 'label' => 'دعوات الموردين للعروض',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/proc_supplier_eval.php', 'label' => 'تقييم أداء التوريد',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/receipt_custody_proc.php', 'label' => 'العهد والمرتجعات',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/reordering_proc.php', 'label' => 'حدود الطلب وإعادة التزويد',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/requests_proc.php', 'label' => 'طلبات الشراء',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/rfq_compare_award.php', 'label' => 'طلب العروض',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/stock_proc.php', 'label' => 'أرصدة المخزون بحالاتها',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/supplier_card_proc.php', 'label' => 'بطاقة مورد المشتريات',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/suppliers_proc.php', 'label' => 'موردو المشتريات',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/warehouse_board.php', 'label' => 'لوحة المخازن',
  'group' => 'Procurement',
  'view'  => array(),
),
array(
  'route' => 'Procurement/wh_receipt.php', 'label' => 'سند الإدخال والفحص',
  'group' => 'Procurement',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_prc.php', 'label' => 'مخاطر المشتريات التشغيلية',
  'group' => 'Risk',
  'view'  => array(),
),

),
);
