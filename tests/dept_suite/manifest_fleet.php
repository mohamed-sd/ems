<?php
/**
 * tests/dept_suite/manifest_fleet.php — مانيفستُ «ادارة الاسطول · مشرف اسطول · مشغل اسطول»  (DEP-04)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-04'`
 *   و`on_disk=1` — **52 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=fleet --quick
 * الأساس:  php tests/dept_suite/run.php --dept=fleet --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'fleet',
'dept_ar' => 'ادارة الاسطول · مشرف اسطول · مشغل اسطول',
'user'    => 'يسن',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Equipments ──────────────────────────────────────────────────
array(
  'route' => 'Equipments/asset_recon.php', 'label' => 'مطابقة سجل الأصول بالتشغيل',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/code_bridge.php', 'label' => 'جسر ترقيم المعدات',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/equipment_documents.php', 'label' => 'وثائق المعدات والمشغلين',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/equipment_sourcing.php', 'label' => 'مصدر المعدة ونمط تملكها',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/equipments_drivers.php', 'label' => 'المعدات',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/equipments_fleet.php', 'label' => 'المعدات',
  'group' => 'Equipments',
  'view'  => array(),
),

// ── الأصول والتشغيل ─────────────────────────────────────────────
array(
  'route' => 'Equipments/equipments_types.php', 'label' => 'أنواع المعدات وفئاتها',
  'group' => 'الأصول والتشغيل',
  'view'  => array(),
),

// ── Equipments ──────────────────────────────────────────────────
array(
  'route' => 'Equipments/fleet_depreciation_profiles.php', 'label' => 'سياسات الإهلاك المعتمدة',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/fleet_failures.php', 'label' => 'تقرير الأعطال',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/fleet_models.php', 'label' => 'موديلات المعدات ومواصفاتها',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/gov_dept_flt.php', 'label' => 'حوكمة إدارة الأسطول',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/manage_failure_codes.php', 'label' => 'تصنيف الأعطال وأسبابها',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/meter_readings.php', 'label' => 'قراءات العدادات',
  'group' => 'Equipments',
  'view'  => array(),
),
array(
  'route' => 'Equipments/select_project.php', 'label' => 'تخصيص المعدات للمشروعات',
  'group' => 'Equipments',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/asset_hours_link.php', 'label' => 'ربط الأصل بساعات تشغيله',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/depr_run.php', 'label' => 'محاسبة الأصول الثابتة والإهلاك',
  'group' => 'Finance',
  'view'  => array(),
),

// ── Fleet ───────────────────────────────────────────────────────
array(
  'route' => 'Fleet/asset_assignments.php', 'label' => 'التخصيص على الوحدات',
  'group' => 'Fleet',
  'view'  => array(),
),

// ── fleet ───────────────────────────────────────────────────────
array(
  'route' => 'fleet/asset_card.php', 'label' => 'كرت الأصل',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/asset_components.php', 'label' => 'المكونات والملحقات',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/asset_documents.php', 'label' => 'مستندات الأصل',
  'group' => 'fleet',
  'view'  => array(),
),

// ── Fleet ───────────────────────────────────────────────────────
array(
  'route' => 'Fleet/asset_exit.php', 'label' => 'الخروج المؤقت',
  'group' => 'Fleet',
  'view'  => array(),
),
array(
  'route' => 'Fleet/asset_full_history.php', 'label' => 'تاريخ المعدة الكامل',
  'group' => 'Fleet',
  'view'  => array(),
),
array(
  'route' => 'Fleet/asset_hours_reference.php', 'label' => 'مرجع ساعات التشغيل للإهلاك',
  'group' => 'Fleet',
  'view'  => array(),
),
array(
  'route' => 'Fleet/asset_intake.php', 'label' => 'طلب إدخال الأصل',
  'group' => 'Fleet',
  'view'  => array(),
),

// ── fleet ───────────────────────────────────────────────────────
array(
  'route' => 'fleet/asset_life_reference.php', 'label' => 'مرجع العمر الإنتاجي بالساعات',
  'group' => 'fleet',
  'view'  => array(),
),

// ── Fleet ───────────────────────────────────────────────────────
array(
  'route' => 'Fleet/asset_readiness.php', 'label' => 'الملخص التشغيلي الشهري',
  'group' => 'Fleet',
  'view'  => array(),
),
array(
  'route' => 'Fleet/asset_use_rights.php', 'label' => 'حق الاستخدام التشغيلي',
  'group' => 'Fleet',
  'view'  => array(),
),

// ── fleet ───────────────────────────────────────────────────────
array(
  'route' => 'fleet/breakdown_log.php', 'label' => 'سجل الأعطال والتوقفات',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/classification_directory.php', 'label' => 'دليل الترميز والتصنيف',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/code_reconciliation.php', 'label' => 'مصالحة مطابقة الأكواد',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/daily_operating_log.php', 'label' => 'السجل اليومي للتشغيل',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/exception_register.php', 'label' => 'سجل الاستثناءات',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/external_auditor_notes.php', 'label' => 'ملاحظات المراجع الخارجي',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/financed_asset_recon.php', 'label' => 'مصالحة الأعيان الممولة',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/fleet_board.php', 'label' => 'لوحة الأسطول',
  'group' => 'fleet',
  'view'  => array(),
),

// ── Fleet ───────────────────────────────────────────────────────
array(
  'route' => 'Fleet/fleet_schema_matrix.php', 'label' => 'مصفوفة بنية الشيتات',
  'group' => 'Fleet',
  'view'  => array(),
),

// ── fleet ───────────────────────────────────────────────────────
array(
  'route' => 'fleet/inspection_card.php', 'label' => 'بطاقة التفتيش',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/management_decisions.php', 'label' => 'حزمة القرارات الإدارية',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/numbering_bridge.php', 'label' => 'مصالحة ترقيم الأصول',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/open_points.php', 'label' => 'النقاط غير المحسومة',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/owner_reconciliation.php', 'label' => 'مصالحة الأسطول بالملاك',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/power_sources.php', 'label' => 'مصادر القدرة',
  'group' => 'fleet',
  'view'  => array(),
),

// ── Fleet ───────────────────────────────────────────────────────
array(
  'route' => 'Fleet/readiness_board.php', 'label' => 'جاهزية المعدات للتشغيل',
  'group' => 'Fleet',
  'view'  => array(),
),
array(
  'route' => 'Fleet/readiness_cert.php', 'label' => 'شهادات جاهزية المعدات',
  'group' => 'Fleet',
  'view'  => array(),
),

// ── fleet ───────────────────────────────────────────────────────
array(
  'route' => 'fleet/register_operation_recon.php', 'label' => 'مصالحة السجل بالتشغيل',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/source_conflicts.php', 'label' => 'التضاربات بين المصادر',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/state_change_log.php', 'label' => 'سجل تغير الحالة',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/technical_state.php', 'label' => 'الحالة الفنية',
  'group' => 'fleet',
  'view'  => array(),
),
array(
  'route' => 'fleet/use_right_ranges.php', 'label' => 'نطاقات حق الاستخدام',
  'group' => 'fleet',
  'view'  => array(),
),

// ── Maintenance ─────────────────────────────────────────────────
array(
  'route' => 'Maintenance/inspections.php', 'label' => 'الفحص الفني اليومي',
  'group' => 'Maintenance',
  'view'  => array(),
),

// ── Reports ─────────────────────────────────────────────────────
array(
  'route' => 'Reports/equipments_reports.php', 'label' => 'equipments_reports',
  'group' => 'Reports',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_flt.php', 'label' => 'مخاطر الأسطول',
  'group' => 'Risk',
  'view'  => array(),
),

),
);
