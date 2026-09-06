<?php
/**
 * tests/dept_suite/manifest_suppliers.php — مانيفستُ «ادارة الموردين · مشرف موردين»  (DEP-02)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-02'`
 *   و`on_disk=1` — **47 سطحًا**. لا اجتهادَ في العضوية.
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
 * التشغيل: php tests/dept_suite/run.php --dept=suppliers --quick
 * الأساس:  php tests/dept_suite/run.php --dept=suppliers --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'suppliers',
'dept_ar' => 'ادارة الموردين · مشرف موردين',
'user'    => 'مصعب',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Governance ──────────────────────────────────────────────────
array(
  'route' => 'Governance/gov_dept_sup.php', 'label' => 'حوكمة إدارة الموردين',
  'group' => 'Governance',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_sup.php', 'label' => 'مخاطر الموردين',
  'group' => 'Risk',
  'view'  => array(),
),

// ── Suppliers ───────────────────────────────────────────────────
array(
  'route' => 'Suppliers/equipment_plan.php', 'label' => 'معدات المورد المتاحة',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/quota_approval_minutes.php', 'label' => 'محاضر اعتماد وحدات المورد',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/rfq_requests.php', 'label' => 'الترشيح ومراجعة التعاقد',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/showcontractsuppliers.php', 'label' => 'عرض عقود الموردين (إرثي)',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/sup_handover.php', 'label' => 'تسليم الحصص بين الموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_advances.php', 'label' => 'سلفيات الموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_aging.php', 'label' => 'أعمار الأرصدة والالتزامات',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_bank.php', 'label' => 'حسابات الموردين البنكية',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_board.php', 'label' => 'لوحة إدارة الموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_capacity_integration.php', 'label' => 'مصادر القدرة والتكامل',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_capacity.php', 'label' => 'البيانات المرجعية ومصادر القدرة',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_closure.php', 'label' => 'تصفية إنهاء عقد مورد',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_contacts.php', 'label' => 'جهات الاتصال والمفوضون',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_contract_events.php', 'label' => 'الملاحق والتصفية والإغلاق',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_contract_lines.php', 'label' => 'بنود عقود الموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_contract_units.php', 'label' => 'حصص الموردين والوحدات التعاقدية',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_data_dictionary.php', 'label' => 'قاموس بيانات الموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),

// ── suppliers ───────────────────────────────────────────────────
array(
  'route' => 'suppliers/supplier_docs_guarantees.php', 'label' => 'سجل المستندات والضمانات',
  'group' => 'suppliers',
  'view'  => array(),
),

// ── Suppliers ───────────────────────────────────────────────────
array(
  'route' => 'Suppliers/supplier_documents.php', 'label' => 'وثائق المورد وحسابه البنكي',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_evaluation.php', 'label' => 'تقييم المورد والأداء',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_inference_rules.php', 'label' => 'قاموس قواعد الاستنتاج',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_migration_map.php', 'label' => 'خريطة ترحيل الموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_migration_trace.php', 'label' => 'تتبع التعبئة والترحيل',
  'group' => 'Suppliers',
  'view'  => array(),
),

// ── suppliers ───────────────────────────────────────────────────
array(
  'route' => 'suppliers/supplier_offers.php', 'label' => 'عروض الموردين والتفاوض',
  'group' => 'suppliers',
  'view'  => array(),
),
array(
  'route' => 'suppliers/supplier_onbehalf_advances.php', 'label' => 'النيابية والسلف والخصومات',
  'group' => 'suppliers',
  'view'  => array(),
),
array(
  'route' => 'suppliers/supplier_payment_allocation.php', 'label' => 'تخصيص الدفع على الإقفالات',
  'group' => 'suppliers',
  'view'  => array(),
),

// ── Suppliers ───────────────────────────────────────────────────
array(
  'route' => 'Suppliers/supplier_payment_requests.php', 'label' => 'طلبات الدفع وحالة الصرف',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_performance_entries.php', 'label' => 'الأداء والوحدات المعتمدة',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_qualification.php', 'label' => 'التأهيل القانوني والائتماني',
  'group' => 'Suppliers',
  'view'  => array(),
),

// ── suppliers ───────────────────────────────────────────────────
array(
  'route' => 'suppliers/supplier_quota_distribution.php', 'label' => 'الوحدات المكافئة وتخصيص الحصص',
  'group' => 'suppliers',
  'view'  => array(),
),
array(
  'route' => 'suppliers/supplier_readiness_reserve.php', 'label' => 'الجاهزية والإحلال والاحتياط',
  'group' => 'suppliers',
  'view'  => array(),
),

// ── Suppliers ───────────────────────────────────────────────────
array(
  'route' => 'Suppliers/supplier_ref_lists.php', 'label' => 'القوائم المرجعية للموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),

// ── suppliers ───────────────────────────────────────────────────
array(
  'route' => 'suppliers/supplier_responsibility_matrix.php', 'label' => 'مصفوفة المسؤوليات والتكاليف',
  'group' => 'suppliers',
  'view'  => array(),
),

// ── Suppliers ───────────────────────────────────────────────────
array(
  'route' => 'Suppliers/supplier_review_report.php', 'label' => 'تقرير المراجعة والقبول',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_rules.php', 'label' => 'قواعد التحميل والجزاءات على المورد',
  'group' => 'Suppliers',
  'view'  => array(),
),

// ── suppliers ───────────────────────────────────────────────────
array(
  'route' => 'suppliers/supplier_slot_closure.php', 'label' => 'الإقفال التعاقدي للوحدات التعاقدية',
  'group' => 'suppliers',
  'view'  => array(),
),
array(
  'route' => 'suppliers/supplier_slot_handover.php', 'label' => 'سجل الإشغال والتسليم',
  'group' => 'suppliers',
  'view'  => array(),
),

// ── Suppliers ───────────────────────────────────────────────────
array(
  'route' => 'Suppliers/supplier_targets.php', 'label' => 'مستهدفات الموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),

// ── suppliers ───────────────────────────────────────────────────
array(
  'route' => 'suppliers/supplier_unit_equipment.php', 'label' => 'توزيع الوحدات التعاقدية على المعدات',
  'group' => 'suppliers',
  'view'  => array(),
),

// ── Suppliers ───────────────────────────────────────────────────
array(
  'route' => 'Suppliers/supplier_violations.php', 'label' => 'المخالفات والجزاءات',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/suppliers_details.php', 'label' => 'تفاصيل المورد (إرثي)',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/suppliers.php', 'label' => 'سجل الموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplierscontracts_details.php', 'label' => 'ملف عقد المورد',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplierscontracts.php', 'label' => 'سجل عقود الموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/unit_statement_supplier.php', 'label' => 'اعتماد الوحدات والأداء المعتمد',
  'group' => 'Suppliers',
  'view'  => array(),
),

),
);
