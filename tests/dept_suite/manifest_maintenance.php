<?php
/**
 * tests/dept_suite/manifest_maintenance.php — مانيفستُ «ادارة الصيانة · مشرف صيانة»  (DEP-14)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-14'`
 *   و`on_disk=1` — **22 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=maintenance --quick
 * الأساس:  php tests/dept_suite/run.php --dept=maintenance --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'maintenance',
'dept_ar' => 'ادارة الصيانة · مشرف صيانة',
'user'    => 'صيانة',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Maintenance ─────────────────────────────────────────────────
array(
  'route' => 'Maintenance/breakdown_intake.php', 'label' => 'البلاغ الفني واستقبال العطل',
  'group' => 'Maintenance',
  'view'  => array(),
),
array(
  'route' => 'Maintenance/daily_care.php', 'label' => 'العناية اليومية والتشحيم',
  'group' => 'Maintenance',
  'view'  => array(),
),
array(
  'route' => 'Maintenance/dashboard_mnt.php', 'label' => 'لوحة الصيانة والجاهزية',
  'group' => 'Maintenance',
  'view'  => array(),
),

// ── maintenance ─────────────────────────────────────────────────
array(
  'route' => 'maintenance/diagnosis_requests.php', 'label' => 'طلب الفحص والتشخيص',
  'group' => 'maintenance',
  'view'  => array(),
),
array(
  'route' => 'maintenance/downtime_segments.php', 'label' => 'تقطيع مسؤولية التوقف',
  'group' => 'maintenance',
  'view'  => array(),
),

// ── Maintenance ─────────────────────────────────────────────────
array(
  'route' => 'Maintenance/equipment_hours_preventive.php', 'label' => 'ساعات المعدة والوقائية',
  'group' => 'Maintenance',
  'view'  => array(),
),
array(
  'route' => 'Maintenance/external_repairs.php', 'label' => 'الإصلاح الخارجي ومطالبات الضمان',
  'group' => 'Maintenance',
  'view'  => array(),
),
array(
  'route' => 'Maintenance/failure_report.php', 'label' => 'تقرير الأعطال (التصنيف الموحد)',
  'group' => 'Maintenance',
  'view'  => array(),
),

// ── maintenance ─────────────────────────────────────────────────
array(
  'route' => 'maintenance/fault_tree.php', 'label' => 'شجرة الأعطال المرجعية',
  'group' => 'maintenance',
  'view'  => array(),
),
array(
  'route' => 'maintenance/fleet_inspection_orders.php', 'label' => 'أوامر التفتيش الواردة من الأسطول',
  'group' => 'maintenance',
  'view'  => array(),
),

// ── Maintenance ─────────────────────────────────────────────────
array(
  'route' => 'Maintenance/gov_dept_mnt.php', 'label' => 'حوكمة إدارة الصيانة',
  'group' => 'Maintenance',
  'view'  => array(),
),
array(
  'route' => 'Maintenance/master_data.php', 'label' => 'إعدادات الصيانة',
  'group' => 'Maintenance',
  'view'  => array(),
),
array(
  'route' => 'Maintenance/mnt_kpis.php', 'label' => 'مؤشرات الصيانة الدورية',
  'group' => 'Maintenance',
  'view'  => array(),
),
array(
  'route' => 'Maintenance/part_requests.php', 'label' => 'طلب صرف القطع لأمر العمل',
  'group' => 'Maintenance',
  'view'  => array(),
),
array(
  'route' => 'Maintenance/preventive_plans.php', 'label' => 'الخطة الوقائية بالساعات',
  'group' => 'Maintenance',
  'view'  => array(),
),
array(
  'route' => 'Maintenance/repeat_repairs.php', 'label' => 'سجل إعادة الإصلاح',
  'group' => 'Maintenance',
  'view'  => array(),
),

// ── maintenance ─────────────────────────────────────────────────
array(
  'route' => 'maintenance/return_to_service_cert.php', 'label' => 'الإقفال وشهادة إعادة الخدمة',
  'group' => 'maintenance',
  'view'  => array(),
),

// ── Maintenance ─────────────────────────────────────────────────
array(
  'route' => 'Maintenance/return_to_service.php', 'label' => 'العودة للخدمة',
  'group' => 'Maintenance',
  'view'  => array(),
),

// ── maintenance ─────────────────────────────────────────────────
array(
  'route' => 'maintenance/work_order_labor.php', 'label' => 'عمالة أمر العمل',
  'group' => 'maintenance',
  'view'  => array(),
),
array(
  'route' => 'maintenance/work_orders.php', 'label' => 'أمر العمل',
  'group' => 'maintenance',
  'view'  => array(),
),

// ── Maintenance ─────────────────────────────────────────────────
array(
  'route' => 'Maintenance/workshop.php', 'label' => 'الورش والفنيون',
  'group' => 'Maintenance',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_mnt.php', 'label' => 'مخاطر الصيانة',
  'group' => 'Risk',
  'view'  => array(),
),

),
);
