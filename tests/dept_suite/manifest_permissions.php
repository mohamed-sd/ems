<?php
/**
 * tests/dept_suite/manifest_permissions.php — مانيفستُ «إدارة الصلاحيات»  (DEP-08)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-08'`
 *   و`on_disk=1` — **69 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=permissions --quick
 * الأساس:  php tests/dept_suite/run.php --dept=permissions --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'permissions',
'dept_ar' => 'إدارة الصلاحيات',
'user'    => 'حسابات',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── النظام والمتابعة ────────────────────────────────────────────
array(
  'route' => 'ActivityLogs/activity_logs.php', 'label' => 'سجل التدقيق والاطلاع',
  'group' => 'النظام والمتابعة',
  'view'  => array(),
),

// ── Approvals ───────────────────────────────────────────────────
array(
  'route' => 'Approvals/attribution_board.php', 'label' => 'لوحة الإسناد اليومي',
  'group' => 'Approvals',
  'view'  => array(),
),
array(
  'route' => 'Approvals/hours_approval.php', 'label' => 'اعتماد الوحدات',
  'group' => 'Approvals',
  'view'  => array(),
),
array(
  'route' => 'Approvals/requests.php', 'label' => 'صندوق موافقاتي وما ينتظر يدي',
  'group' => 'Approvals',
  'view'  => array(),
),

// ── emsreports ──────────────────────────────────────────────────
array(
  'route' => 'emsreports/index.php', 'label' => 'مركز تقارير الحوكمة',
  'group' => 'emsreports',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/approvals_inbox.php', 'label' => 'صندوق ما ينتظر اعتمادي',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/budget_dept.php', 'label' => 'ميزانية إدارة التمويل',
  'group' => 'Finance',
  'view'  => array(),
),

// ── Financing ───────────────────────────────────────────────────
array(
  'route' => 'Financing/ownership_links.php', 'label' => 'علاقات الملكية بين الكيانات',
  'group' => 'Financing',
  'view'  => array(),
),

// ── Governance ──────────────────────────────────────────────────
array(
  'route' => 'Governance/access_review.php', 'label' => 'دورة المراجعة الدورية للصلاحيات',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/activation_patterns.php', 'label' => 'أنماط تفعيل المزايا',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/approval_ladders.php', 'label' => 'سلاليم الاعتماد وتعريفها',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/audit_followup.php', 'label' => 'متابعة نتائج المراجعة',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/auth_grants.php', 'label' => 'منح الصلاحية',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/auth_profiles.php', 'label' => 'قوالب الصلاحيات',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/authority_caps.php', 'label' => 'حدود المبالغ',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/breaches.php', 'label' => 'سجل الإخلالات',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/break_glass.php', 'label' => 'صلاحية الطوارئ اللحظية',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/bus_board.php', 'label' => 'مراقبة تدفق الأحداث ومعالجتها',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/bus_deliveries.php', 'label' => 'تسليمات الأحداث وحالاتها',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/bus_outbox.php', 'label' => 'صندوق الأحداث الصادر',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/canonical_names.php', 'label' => 'سجل الأسماء المعتمدة',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/committees.php', 'label' => 'اللجان وحوكمة الاجتماعات',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/compliance_calendar.php', 'label' => 'تقويم الامتثال',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/conduct_acknowledgements.php', 'label' => 'إقرارات مدونة السلوك',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/conflict_disclosures.php', 'label' => 'تضارب المصالح',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/corrective_actions.php', 'label' => 'الإجراءات التصحيحية',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/design_system.php', 'label' => 'النظام التصميمي',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/doc_types.php', 'label' => 'سجل أنواع المستندات',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/dr_restore.php', 'label' => 'تمرين الاستعادة ومحضره',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/entities_registry.php', 'label' => 'سجل الشركات والكيانات',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/exceptions.php', 'label' => 'طلبات الاستثناء',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/gifts_hospitality.php', 'label' => 'الهدايا والضيافة',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/gov_board.php', 'label' => 'لوحة الحوكمة والالتزام',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/gov_dept_gov.php', 'label' => 'حوكمة الحوكمة والالتزام',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/gov_dept.php', 'label' => 'حوكمة إدارة الأسطول',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/gov_reports.php', 'label' => 'تقارير الحوكمة',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/guard_denials.php', 'label' => 'سجل المحاولات الممنوعة',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/guards.php', 'label' => 'تصنيف قواعد المنع',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/impersonations.php', 'label' => 'جلسات النيابة',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/integrity_reports.php', 'label' => 'بلاغات النزاهة المحمية',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/investigations.php', 'label' => 'التحقيقات',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/job_queue.php', 'label' => 'طابور المهام',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/job_schedule.php', 'label' => 'جدولة المهام الدورية',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/licenses_guarantees.php', 'label' => 'التراخيص والكفالات',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/obligations.php', 'label' => 'سجل الالتزامات التنظيمية',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/ownership_grants.php', 'label' => 'منح المجال المقيد',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/perm_explain.php', 'label' => 'تفسير مصدر الصلاحية',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/policies.php', 'label' => 'سجل السياسات',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/portal_users.php', 'label' => 'حسابات بوابة الأطراف الخارجية',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/read_log.php', 'label' => 'سجل الاطّلاع على الحقول الحساسة',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/regulatory_filings.php', 'label' => 'التقديمات النظامية',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/related_parties.php', 'label' => 'الأطراف ذات العلاقة',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/security_log.php', 'label' => 'سجل الأمان',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/sensitive_fields.php', 'label' => 'سياسات الحقول الحساسة',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/signing_authority.php', 'label' => 'التفويض بالتوقيع',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/sod_conflicts.php', 'label' => 'فصل الواجبات المتعارضة',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/state_machines.php', 'label' => 'حالات المستندات وانتقالاتها',
  'group' => 'Governance',
  'view'  => array(),
),
array(
  'route' => 'Governance/tech_gov_center.php', 'label' => 'مركز الحوكمة التقني',
  'group' => 'Governance',
  'view'  => array(),
),

// ── النظام والمتابعة ────────────────────────────────────────────
array(
  'route' => 'main/users.php', 'label' => 'حسابات المستخدمين',
  'group' => 'النظام والمتابعة',
  'view'  => array(),
),

// ── Projects ────────────────────────────────────────────────────
array(
  'route' => 'Projects/sites.php', 'label' => 'مواقع التنفيذ',
  'group' => 'Projects',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_gov.php', 'label' => 'مخاطر الحوكمة والالتزام',
  'group' => 'Risk',
  'view'  => array(),
),

// ── Settings ────────────────────────────────────────────────────
array(
  'route' => 'Settings/guard_classification.php', 'label' => 'تصنيف قواعد المنع للظهور وضبط البوابة',
  'group' => 'Settings',
  'view'  => array(),
),
array(
  'route' => 'Settings/modules.php', 'label' => 'صفحات النظام والإدارات',
  'group' => 'Settings',
  'view'  => array(),
),
array(
  'route' => 'Settings/role_permissions.php', 'label' => 'صلاحيات الأدوار',
  'group' => 'Settings',
  'view'  => array(),
),
array(
  'route' => 'Settings/roles.php', 'label' => 'الأدوار وقوالب صلاحياتها',
  'group' => 'Settings',
  'view'  => array(),
),
array(
  'route' => 'Settings/settings.php', 'label' => 'الإعدادات',
  'group' => 'Settings',
  'view'  => array(),
),

// ── Timesheet ───────────────────────────────────────────────────
array(
  'route' => 'Timesheet/aprovment.php', 'label' => '$typetext',
  'group' => 'Timesheet',
  'view'  => array(),
),
array(
  'route' => 'Timesheet/timesheet_details.php', 'label' => 'تفاصيل الوحدة',
  'group' => 'Timesheet',
  'view'  => array(),
),
array(
  'route' => 'Timesheet/timesheet_type.php', 'label' => 'حوكمة تسجيل التايم شيت والإنتاج',
  'group' => 'Timesheet',
  'view'  => array(),
),

),
);
