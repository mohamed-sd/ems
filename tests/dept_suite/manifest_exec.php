<?php
/**
 * tests/dept_suite/manifest_exec.php — مانيفستُ «الإدارة التنفيذية»  (EX-CEO)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='EX-CEO'`
 *   و`on_disk=1` — **40 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=exec --quick
 * الأساس:  php tests/dept_suite/run.php --dept=exec --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'exec',
'dept_ar' => 'الإدارة التنفيذية',
'user'    => 'تنفيذ',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Clients ─────────────────────────────────────────────────────
array(
  'route' => 'Clients/commercial_risks.php', 'label' => 'المخاطر التجارية',
  'group' => 'Clients',
  'view'  => array(),
),

// ── Portal ──────────────────────────────────────────────────────
array(
  'route' => 'Portal/ceo_approvals.php', 'label' => 'اعتمادات الرئيس التنفيذي',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/ceo_assurance_box.php', 'label' => 'صندوق التأكيد المستقل — تقارير المراجعة الداخلية',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/ceo_board.php', 'label' => 'لوحة الرئيس التنفيذي',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/ceo_contracts.php', 'label' => 'العقود والالتزامات المحجوزة أو المصعدة',
  'group' => 'Portal',
  'view'  => array(),
),

// ── portal ──────────────────────────────────────────────────────
array(
  'route' => 'portal/ceo_org_decisions.php', 'label' => 'قرارات الهيكل التنظيمي',
  'group' => 'portal',
  'view'  => array(),
),

// ── Portal ──────────────────────────────────────────────────────
array(
  'route' => 'Portal/ceo_risk.php', 'label' => 'المخاطر والقرارات العليا',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/ceo_unified_queue.php', 'label' => 'جميع الطلبات المرفوعة إليّ',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/contract_review.php', 'label' => 'مراجعة العقود وملاحظاتها',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/dept_achievement.php', 'label' => 'إنجاز الإدارة',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_actions_followup.php', 'label' => 'متابعة القرارات التنفيذية',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_command_board.php', 'label' => 'لوحة القيادة التنفيذية',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_contract_registry.php', 'label' => 'سجل العقود الموحَّد',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_crisis_command.php', 'label' => 'قيادة الأزمات والطوارئ',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_critical_exceptions.php', 'label' => 'الاستثناءات الحرجة',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_daily_deviations.php', 'label' => 'انحرافات وقرارات اليوم',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_daily_report.php', 'label' => 'التقرير اليومي التنفيذي',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_daily_stops.php', 'label' => 'تفصيل توقفات اليوم',
  'group' => 'Portal',
  'view'  => array(),
),

// ── portal ──────────────────────────────────────────────────────
array(
  'route' => 'portal/exec_doc_review_notes.php', 'label' => 'ملاحظات مراجعة الوثيقة قبل التوقيع',
  'group' => 'portal',
  'view'  => array(),
),
array(
  'route' => 'portal/exec_document_approvals.php', 'label' => 'اعتمادات العقود والوثائق',
  'group' => 'portal',
  'view'  => array(),
),

// ── Portal ──────────────────────────────────────────────────────
array(
  'route' => 'Portal/exec_escalations.php', 'label' => 'التصعيدات العليا',
  'group' => 'Portal',
  'view'  => array(),
),

// ── portal ──────────────────────────────────────────────────────
array(
  'route' => 'portal/exec_financial_approvals.php', 'label' => 'الاعتمادات المالية',
  'group' => 'portal',
  'view'  => array(),
),

// ── Portal ──────────────────────────────────────────────────────
array(
  'route' => 'Portal/exec_leadership_appointments.php', 'label' => 'موافقات التعيين في المسميات القيادية',
  'group' => 'Portal',
  'view'  => array(),
),

// ── portal ──────────────────────────────────────────────────────
array(
  'route' => 'portal/exec_leadership_meetings.php', 'label' => 'اجتماعات الإدارة العليا',
  'group' => 'portal',
  'view'  => array(),
),

// ── Portal ──────────────────────────────────────────────────────
array(
  'route' => 'Portal/exec_meeting_decisions.php', 'label' => 'قرارات الاجتماعات',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_monthly_pack.php', 'label' => 'التقرير الشهري التنفيذي',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_org_projects.php', 'label' => 'الإدارات والمشروعات',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_raised_requests.php', 'label' => 'الطلبات المرفوعة إلى القيادة',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_redline_breaches.php', 'label' => 'تجاوزات الخطوط الحمراء',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_reserved_matters.php', 'label' => 'المسائل المحجوزة والسلطة المحجوزة',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/exec_strategic_decisions.php', 'label' => 'القرارات الاستراتيجية',
  'group' => 'Portal',
  'view'  => array(),
),

// ── portal ──────────────────────────────────────────────────────
array(
  'route' => 'portal/exec_temp_assignments.php', 'label' => 'التكليفات والإنابات المؤقتة',
  'group' => 'portal',
  'view'  => array(),
),

// ── Portal ──────────────────────────────────────────────────────
array(
  'route' => 'Portal/exec_weekly_report.php', 'label' => 'التقرير الأسبوعي التنفيذي',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/founding_mode.php', 'label' => 'وضع التأسيس وإغلاقه',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/gov_dept_ceo.php', 'label' => 'حوكمة مكتبُ الرئيس التنفيذي والنواب',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/project_charter.php', 'label' => 'قرار فتح مشروع — ميثاق المشروع',
  'group' => 'Portal',
  'view'  => array(),
),
array(
  'route' => 'Portal/release_stamp.php', 'label' => 'بصمة الإصدار وتقرير النشر',
  'group' => 'Portal',
  'view'  => array(),
),

// ── Reports ─────────────────────────────────────────────────────
array(
  'route' => 'Reports/exceptions_report.php', 'label' => 'تقرير الاستثناءات',
  'group' => 'Reports',
  'view'  => array(),
),
array(
  'route' => 'Reports/reports.php', 'label' => 'مركز التقارير التنفيذية',
  'group' => 'Reports',
  'view'  => array(),
),

// ── Workforce ───────────────────────────────────────────────────
array(
  'route' => 'Workforce/contract_registry.php', 'label' => 'سجل العقود',
  'group' => 'Workforce',
  'view'  => array(),
),

),
);
