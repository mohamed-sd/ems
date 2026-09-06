<?php
/**
 * tests/dept_suite/manifest_site.php — مانيفستُ «إدارة الموقع (قديم — مدمج في 6) · إدارة الموقع»  (DEP-12)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-12'`
 *   و`on_disk=1` — **26 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=site --quick
 * الأساس:  php tests/dept_suite/run.php --dept=site --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'site',
'dept_ar' => 'إدارة الموقع (قديم — مدمج في 6) · إدارة الموقع',
'user'    => 'موقع',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Operations ──────────────────────────────────────────────────
array(
  'route' => 'Operations/gov_dept_sit.php', 'label' => 'حوكمة إدارة الموقع',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/shift_entry.php', 'label' => 'القيد اليومي للوردية',
  'group' => 'Operations',
  'view'  => array(),
),

// ── operations ──────────────────────────────────────────────────
array(
  'route' => 'operations/site_board.php', 'label' => 'لوحة الموقع',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_closure_items.php', 'label' => 'إغلاق الموقع وتسريحه',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_day_approval.php', 'label' => 'محضر اعتماد الموقع',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_day_close_report.php', 'label' => 'تقرير إقفال يوم الموقع',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_day_open.php', 'label' => 'فتح يوم الموقع',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_day_shifts.php', 'label' => 'ورديات اليوم',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_day_units.php', 'label' => 'تسجيل وحدات اليوم',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_field_expense.php', 'label' => 'صرف المشتريات الميدانية من العهدة',
  'group' => 'operations',
  'view'  => array(),
),

// ── Operations ──────────────────────────────────────────────────
array(
  'route' => 'Operations/site_gate_equip.php', 'label' => 'أذون دخول وخروج المعدات والمشغّلين',
  'group' => 'Operations',
  'view'  => array(),
),
array(
  'route' => 'Operations/site_gate_person.php', 'label' => 'أذون دخول وخروج المشغلين',
  'group' => 'Operations',
  'view'  => array(),
),

// ── operations ──────────────────────────────────────────────────
array(
  'route' => 'operations/site_readiness_items.php', 'label' => 'تجهيز الموقع والجاهزية',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_reference_registry.php', 'label' => 'سجلات الموقع المرجعية',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_register.php', 'label' => 'سجل المواقع وحدودها',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_request_batches.php', 'label' => 'دفعات استلام طلب الموقع',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_request_items.php', 'label' => 'بنود طلب الموقع',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_shift_handover.php', 'label' => 'محضر تسليم واستلام الورديات',
  'group' => 'operations',
  'view'  => array(),
),

// ── Operations ──────────────────────────────────────────────────
array(
  'route' => 'Operations/site_shift_plan.php', 'label' => 'جدول ورديات النهار والليل',
  'group' => 'Operations',
  'view'  => array(),
),

// ── operations ──────────────────────────────────────────────────
array(
  'route' => 'operations/site_state_requests.php', 'label' => 'طلبات تغيير الحالة ومعالجة المتعثر',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_stop_decision.php', 'label' => 'قرار الاستعداد أو التعطل',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_supply_requests.php', 'label' => 'طلبات الموقع للصرف والاستلام',
  'group' => 'operations',
  'view'  => array(),
),
array(
  'route' => 'operations/site_suspension.php', 'label' => 'الإيقاف المؤقت للموقع',
  'group' => 'operations',
  'view'  => array(),
),

// ── Operations ──────────────────────────────────────────────────
array(
  'route' => 'Operations/site_work_calendar.php', 'label' => 'جدول عمل المنجم الأسبوعي والشهري',
  'group' => 'Operations',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_sit.php', 'label' => 'مخاطر الموقع',
  'group' => 'Risk',
  'view'  => array(),
),

// ── Timesheet ───────────────────────────────────────────────────
array(
  'route' => 'Timesheet/timesheet.php', 'label' => 'سجل ساعات التشغيل اليومي',
  'group' => 'Timesheet',
  'view'  => array(),
),

),
);
