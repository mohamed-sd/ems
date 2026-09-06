<?php
/**
 * tests/dept_suite/manifest_tickets.php — مانيفستُ «إدارة البلاغات»  (DEP-10)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-10'`
 *   و`on_disk=1` — **28 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=tickets --quick
 * الأساس:  php tests/dept_suite/run.php --dept=tickets --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'tickets',
'dept_ar' => 'إدارة البلاغات',
'user'    => 'بلاغات',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_crp.php', 'label' => 'مخاطر البلاغات والوقائع',
  'group' => 'Risk',
  'view'  => array(),
),

// ── Tickets ─────────────────────────────────────────────────────
array(
  'route' => 'Tickets/admin_close.php', 'label' => 'إغلاق البلاغ وتأكيده',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/gov_dept_crp.php', 'label' => 'لوحة مركز البلاغات',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/inquiry.php', 'label' => 'الاستفسار عن بلاغ متعثر',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/intake_classify.php', 'label' => 'الاستقبال والتصنيف لتوجيه البلاغات الجديدة',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/my_tickets.php', 'label' => 'بلاغاتي من سجل البلاغات',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_categories_config.php', 'label' => 'تصنيفات الأعطال والبلاغات',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_close.php', 'label' => 'إغلاق البلاغ وتأكيده',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_contextual_open.php', 'label' => 'الإبلاغ السياقي من داخل الشاشة',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_dashboard.php', 'label' => 'مؤشرات البلاغات',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_escalation_config.php', 'label' => 'مهل البلاغات وتصعيدها',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_form.php', 'label' => 'تسجيل البلاغ',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_kpi.php', 'label' => 'مؤشرات البلاغات',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_recurrence.php', 'label' => 'البلاغات المتكررة وسببها الجذري',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_sla_config.php', 'label' => 'مصفوفة مهل المعالجة للبلاغات',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_types_config.php', 'label' => 'إعدادات البلاغات للأنواع والمهل والتصعيد',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/ticket_workstreams_board.php', 'label' => 'تحويل البلاغ وتفريعه لمسارات',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/tickets_list.php', 'label' => 'صندوق بلاغات الإدارة',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/tkt_assignment.php', 'label' => 'تاريخ إسناد البلاغ',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/tkt_communications.php', 'label' => 'مراسلات البلاغ',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/tkt_escalation.php', 'label' => 'تصعيد البلاغ',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/tkt_parties.php', 'label' => 'أطراف البلاغ',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/tkt_reopen.php', 'label' => 'إعادة فتح البلاغ',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/tkt_resolution_actions.php', 'label' => 'إجراءات معالجة البلاغ',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/tkt_routing.php', 'label' => 'تاريخ توجيه البلاغ',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/tkt_subject_types.php', 'label' => 'أنواع محل البلاغ المعتمدة',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/tkt_verification.php', 'label' => 'التحقق من المعالجة وإغلاق البلاغ',
  'group' => 'Tickets',
  'view'  => array(),
),
array(
  'route' => 'Tickets/watchtower.php', 'label' => 'تقرير من يتأخر ومن لا يستجيب',
  'group' => 'Tickets',
  'view'  => array(),
),

),
);
