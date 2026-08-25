<?php
/**
 * tools/lib/repair01_w8_scan.php — مقاييسُ المرحلةِ الثامنة (المبيعات والموردون)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تُعيد القياسَ ولا تقرأ ما خزَّنَته الأداة** (‏_CONTEXT §قواعد القياس ①).
 * ◆ **ولا عتبةٌ رقميّةٌ مكتوبةٌ هنا**: كلُّها من `repair01_w8_thresholds` (§٥).
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (§٣): أسماءُ الجداولِ والأعمدةِ والقواعد.
 * ◆ **وكلُّ فحصِ انحدارٍ يحمل مقامَه**: مقامٌ صفريٌّ يُعاد بحكمِ `EMPTY_DENOM`
 *   ولا يمرُّ `PASS` صامتًا — وهو الصنفُ الذي أوقع `W1-08` وحواجبَ W07 الخمسة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (!defined('EMS_CLI')) { define('EMS_CLI', true); }

function repair01_w8_one(mysqli $c, $sql)
{
    $r = $c->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}
function repair01_w8_table_exists(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function repair01_w8_col_exists(mysqli $c, $t, $col)
{
    if (!repair01_w8_table_exists($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

/** رموزُ الإداراتِ المالكةِ للمرحلة — تُشتقُّ من وحداتِ متطلَّباتِها لا تُكتب */
function repair01_w8_scope_codes(mysqli $c)
{
    $codes = array();
    $r = $c->query("SELECT DISTINCT unit FROM repair01_requirements WHERE stage_no = 8");
    while ($r && $x = $r->fetch_assoc()) {
        if (preg_match('/^(\d{2})\s/u', $x['unit'], $m)) { $codes['DEP-' . $m[1]] = true; }
    }
    return array_keys($codes);
}

/** عتباتُ المرحلةِ من السجلِّ — ولا رقمَ مكتوبٌ في هذا الملفّ */
function repair01_w8_thresholds(mysqli $c)
{
    $out = array();
    if (!repair01_w8_table_exists($c, 'repair01_w8_thresholds')) { return $out; }
    $r = $c->query("SELECT threshold_key, value_num, why, decision_ref FROM repair01_w8_thresholds");
    while ($r && $x = $r->fetch_assoc()) {
        $out[$x['threshold_key']] = array('value' => (float) $x['value_num'],
                                          'why' => (string) $x['why'], 'ref' => (string) $x['decision_ref']);
    }
    return $out;
}

/**
 * حارسُ الشاشةِ كما يُقاس من ملفِّها — لا كما يُدَّعى في السجلّ.
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والحارسُ يُورَث بالتضمين**: شاشةٌ كلُّ منطقِها في عُدَّةٍ مشتركةٍ
 *   (`Risk/_risk_common.php` مثلًا) تحمل حارسَها **هناك** لا في ملفِّها.
 *   وكاشفٌ يقرأ ملفَّ الشاشةِ وحدَه يقول «بلا حارس» عن شاشةٍ محروسةٍ فعلًا —
 *   وهو **عطبٌ في الكاشفِ لا في المكشوف** (‏درسُ W06). فيُتبَع مستوًى واحدٌ
 *   من `require`/`include` للملفّاتِ المحلّيّة، ويُوسَم الحكمُ **موروثًا**
 *   بمصدرِه فلا يختلط بالمقيسِ في الملفِّ نفسِه.
 */
function repair01_w8_guard_of($ROOT, $route, $depth = 1)
{
    $path = $ROOT . '/' . $route;
    if (!is_file($path)) { return array('kind' => 'NONE', 'evidence' => 'لا ملف على القرص'); }
    $src = (string) file_get_contents($path);

    $verdict = repair01_w8_guard_in_src($src);
    if ($verdict !== null) { return $verdict; }

    if ($depth > 0) {
        foreach (repair01_w8_local_includes($ROOT, $route, $src) as $inc) {
            $isrc = (string) @file_get_contents($inc['abs']);
            if ($isrc === '') { continue; }
            $v = repair01_w8_guard_in_src($isrc);
            if ($v !== null) {
                return array('kind' => $v['kind'],
                             'evidence' => $v['evidence'] . ' — موروث من ' . $inc['rel']);
            }
        }
    }
    return array('kind' => 'NONE', 'evidence' => 'لا حارس مقيس');
}

/**
 * حكمُ الحارسِ من نصٍّ واحد — أو `null` إن لم يُقَس فيه شيء.
 * ⛔ **والمِرساةُ استدعاءٌ لا ذِكر**: `check_page_permissions` **تُعرَّف** في
 *   `includes/permissions_helper.php`، فمطابقةُ الاسمِ مجرَّدًا تجعل كلَّ ملفٍّ
 *   يضمُّ العُدَّةَ «محروسًا» — وهو أخضرُ بالبناء. فيُشترط قوسُ الاستدعاء.
 */
function repair01_w8_guard_in_src($src)
{
    if (preg_match('~\bcheck_page_permissions\s*\(~', $src)
        || preg_match('~\benforce_current_page_view_permission\s*\(~', $src)) {
        return array('kind' => 'SELF_EARLY', 'evidence' => 'استدعاء حارس الصفحة');
    }
    if (preg_match('~\bems_gov_flash_redirect\s*\(~', $src)
        || preg_match('~[\'"][^\'"]*insidebar\.php[\'"]~', $src)) {
        return array('kind' => 'SHELL', 'evidence' => 'حارس القشرة insidebar/gov_flash');
    }
    if (strpos($src, "\$_SESSION['user']") !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'فحص الجلسة');
    }
    return null;
}

/**
 * عُدَّةُ الصفحةِ المجاورةُ التي يضمُّها السطحُ — مستوًى واحدٌ **وفي مجلَّدِه**.
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **ولا تُتبَع المكتباتُ العامّة**: `config.php` و`includes/*` يضمُّها كلُّ
 *   سطحٍ في النظام، وكلُّها تذكر الجلسةَ أو تعرّف الحارس — فاتّباعُها يجعل
 *   `W8-06` **خضراءَ بالبناء** لا بالقياس. والحدُّ مبدئيٌّ لا اعتباطيّ:
 *   **عُدَّةُ صفحةٍ تجاور صفحاتِها** (‏`Risk/_risk_common.php`) جزءٌ من السطح،
 *   والمكتبةُ المركزيّةُ ليست كذلك.
 */
function repair01_w8_local_includes($ROOT, $route, $src)
{
    $out = array();
    $dir = realpath(dirname($ROOT . '/' . $route));
    if ($dir === false) { return $out; }
    if (!preg_match_all('~\b(?:require|include)(?:_once)?\s*\(?\s*(?:__DIR__\s*\.\s*)?[\'"]([^\'"]+\.php)[\'"]~i',
                        $src, $m)) {
        return $out;
    }
    foreach ($m[1] as $rel) {
        $abs = realpath(dirname($ROOT . '/' . $route) . '/' . ltrim($rel, '/'));
        if ($abs === false || !is_file($abs)) { continue; }
        if (realpath(dirname($abs)) !== $dir) { continue; }   /* المجاورُ وحدَه */
        $out[] = array('abs' => $abs, 'rel' => basename($abs));
    }
    return $out;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① مِرساةُ المتطلَّبِ إلى سطحِه — مُعلَنةٌ ومقيسةٌ معًا
   ═══════════════════════════════════════════════════════════════════════════
   ◆ `kind`: `TABLE` جدولٌ يمسُّه الملفّ · `SERVICE` صنفٌ يستدعيه · `GAP` لم يُبنَ.
   ◆ **والوحدتانِ مرجعيّتان**: أغلبُ المِرساةِ تقع على ملفٍّ قائمٍ — والغائبُ
     يُعلَن `GAP` بصفِّ فجوةٍ لا بسكوت.
   ═══════════════════════════════════════════════════════════════════════════ */
function repair01_w8_anchors()
{
    return array(
    /* ══ 01 إدارة المبيعات التعاقدية والعقود ═════════════════════════════ */
    /* ── المرحلة التمهيدية ─────────────────────────────────────────────── */
    'SAL-01' => array('route' => 'Clients/clients.php', 'probe' => 'clients', 'kind' => 'TABLE',
                      'why' => 'سجل العملاء — الكيان القانوني للعميل وتصنيفه وشريحة اهميته؛ الشاشة الام لملف العميل'),
    'SAL-02' => array('route' => 'Clients/client_contacts.php', 'probe' => 'clients', 'kind' => 'TABLE',
                      'why' => 'جهات اتصال العميل — تبويب في ملف العميل لا شاشة مستقلة ولا بند تنقل'),
    'SAL-03' => array('route' => 'Projects/projects.php', 'probe' => 'project', 'kind' => 'TABLE',
                      'why' => 'سجل المشاريع — المشروع وعاء التنفيذ ومواقعه؛ والعقد يشير اليه لا يخلقه'),
    /* ── خط الأنابيب ───────────────────────────────────────────────────── */
    'SAL-04' => array('route' => 'Opportunities/opportunities.php', 'probe' => 'opportunities', 'kind' => 'TABLE',
                      'why' => 'الفرص البيعية — راس خط الانابيب بمرحلته واحتماله وقيمته المتوقعة'),
    'SAL-05' => array('route' => 'Clients/activities.php', 'probe' => 'activities', 'kind' => 'TABLE',
                      'why' => 'الانشطة والمتابعات — واقعة تواصل على فرصة او عميل بنتيجتها'),
    'SAL-06' => array('route' => 'Opportunities/client_need_rfq.php', 'probe' => 'sal_client_needs', 'kind' => 'TABLE',
                      'why' => 'احتياج العميل وطلب العرض — الطلب يسبق العرض ولا عرض بلا احتياج معلن'),
    /* ── العرض والتفاوض ────────────────────────────────────────────────── */
    'SAL-07' => array('route' => 'Clients/quotations.php', 'probe' => 'quotations', 'kind' => 'TABLE',
                      'why' => 'سجل العروض — النسخة الفائزة بطبقة اصدارات؛ والعرض يرتبط بفرصته'),
    'SAL-08' => array('route' => 'Clients/quotation_lines.php', 'probe' => 'sal_quotation_lines', 'kind' => 'TABLE',
                      'why' => 'بنود العروض — سجل تابع للعرض بسعره ووحدته؛ والمجموع يقرا لا يكتب'),
    'SAL-09' => array('route' => 'Clients/quotation_negotiation.php', 'probe' => 'sal_quotation_revisions', 'kind' => 'TABLE',
                      'why' => 'التفاوض ومراجعات العرض — جولة تفاوض واحدة بنتيجتها؛ ولا تعديل صامت على العرض'),
    'SAL-10' => array('route' => 'Clients/readiness_lines.php', 'probe' => 'readiness_lines', 'kind' => 'TABLE',
                      'why' => 'مراجعة ما قبل العقد — بنود الجاهزية للعقد المرشح؛ الحوكمة والقانوني مصدر الحقيقة'),
    /* ── التعاقد والالتزام ─────────────────────────────────────────────── */
    'SAL-11' => array('route' => 'Contracts/contracts.php', 'probe' => 'contracts', 'kind' => 'TABLE',
                      'why' => 'سجل عقود المشاريع — راس العقد بحالته الاثنتي عشرة؛ والشاشة تحت مالك غير مالك المتطلب'),
    'SAL-12' => array('route' => 'Contracts/contract_lines.php', 'probe' => 'ContractLineService', 'kind' => 'SERVICE',
                      'why' => 'بنود العقد والالتزام التجاري — بند تسعير واحد؛ والخدمة تملك الاشتقاق لا الشاشة'),
    'SAL-13' => array('route' => 'Contracts/contract_obligations.php', 'probe' => 'contract_obligations', 'kind' => 'TABLE',
                      'why' => 'مصفوفة الالتزامات — عقد واحد بمصفوفة الالتزامات المعيارية كل التزام عمود'),
    'SAL-14' => array('route' => 'Contracts/contract_baseline.php', 'probe' => 'ContractBaselineService', 'kind' => 'SERVICE',
                      'why' => 'خط الاساس والمستهدفات — عقد بشهر؛ والختم يمنع تعديل خط الاساس بعد اعتماده'),
    'SAL-15' => array('route' => 'Contracts/contract_coverage.php', 'probe' => 'contract_commitments', 'kind' => 'TABLE',
                      'why' => 'التغطية التعاقدية ودورات الالتزام — دورة التزام تعاقدي واحدة بتجديدها'),
    'SAL-16' => array('route' => 'Contracts/contract_card.php', 'probe' => 'op_containers', 'kind' => 'TABLE',
                      'why' => 'الحاويات الشهرية والفاقد — حاوية سنوية بشهر تقاس وتقفل مستقلة؛ والعقد الموقع وحده مصدر الالتزام'),
    /* ── الأداء والتحصيل ───────────────────────────────────────────────── */
    'SAL-17' => array('route' => 'Contracts/unit_statement_client.php', 'probe' => 'v_monthly_performance', 'kind' => 'TABLE',
                      'why' => 'الاداء والمبيعات الشهرية — دورة التزام بشهر؛ المنفذ المعتمد من التشغيل والايراد من المبيعات'),
    'SAL-18' => array('route' => 'Contracts/claims.php', 'probe' => 'claims', 'kind' => 'TABLE',
                      'why' => 'المطالبات والتسليم للمالية — مستخلص شهري واحد؛ والاثر يصل المالية اعترافا لا قيدا'),
    'SAL-19' => array('route' => 'Clients/contract_amendments.php', 'probe' => 'contract_amendments', 'kind' => 'TABLE',
                      'why' => 'الملحقات والتجديد والاغلاق — واقعة ملحق او تجديد او اغلاق واحدة بمستندها'),
    /* ── اللوحة ────────────────────────────────────────────────────────── */
    'SAL-20' => array('route' => 'Contracts/commercial_board.php', 'probe' => 'CommercialBoardService', 'kind' => 'SERVICE',
                      'why' => 'لوحة المبيعات — مؤشر مشتق كليا؛ خارج الدورة ولا ادخال فيها'),
    /* ── الإدارة والحوكمة ──────────────────────────────────────────────── */
    'SAL-21' => array('route' => 'Clients/units_of_measure.php', 'probe' => 'units_of_measure', 'kind' => 'TABLE',
                      'why' => 'القوائم المرجعية — كل قائمة عمود؛ ووحدات القياس والتحويل مرجع الوحدة في العرض والعقد'),
    'SAL-22' => array('route' => 'Governance/sensitive_fields.php', 'probe' => 'sensitive_fields.php', 'kind' => 'CMP03',
                      'why' => 'قاموس البيانات — تعريف حقل واحد؛ ومصدره سجل الاعمدة المركزي لا ورقة يدوية'),
    'SAL-23' => array('route' => '', 'probe' => '', 'kind' => 'GAP',
                      'why' => 'خريطة الترحيل — عمود مصدري ومصيره؛ سطح هندسة نظم لم يبن ولا نظير له على القرص'),
    'SAL-24' => array('route' => 'Governance/gov_reports.php', 'probe' => 'sod_conflicts', 'kind' => 'TABLE',
                      'why' => 'تقرير المراجعة — بند مراجعة او اختبار واحد؛ واقرار الادارة يحمله سجل الاقرارات'),
    'SAL-25' => array('route' => '', 'probe' => '', 'kind' => 'GAP',
                      'why' => 'قاموس قواعد الاستنتاج — قاعدة استنتاج واحدة؛ سطح هندسة نظم لم يبن'),
    'SAL-26' => array('route' => '', 'probe' => '', 'kind' => 'GAP',
                      'why' => 'سجل تتبع القيم الرجعية — قيمة رجعية متتبعة واحدة؛ سطح هندسة نظم لم يبن'),
    'SAL-27' => array('route' => '', 'probe' => '', 'kind' => 'GAP',
                      'why' => 'تقرير اكتمال البيانات — عمود بنسبة اكتمال؛ سطح هندسة نظم لم يبن'),

    /* ══ 02 إدارة الموردين ═══════════════════════════════════════════════ */
    /* ── التأسيس ───────────────────────────────────────────────────────── */
    'SUP-01' => array('route' => 'Suppliers/suppliers.php', 'probe' => 'suppliers', 'kind' => 'TABLE',
                      'why' => 'سجل الموردين — الشاشة الام بكيان المورد ونوع تعامله وانواع معداته'),
    'SUP-02' => array('route' => 'Suppliers/supplier_contacts.php', 'probe' => 'suppliers', 'kind' => 'TABLE',
                      'why' => 'جهات الاتصال والمفوضون — تبويب في ملف المورد بعدة مشتركة مع سطح العميل'),
    'SUP-03' => array('route' => 'Suppliers/supplier_bank.php', 'probe' => 'suppliers', 'kind' => 'TABLE',
                      'why' => 'التاهيل القانوني والائتماني — السجل التجاري والهوية والحساب البنكي؛ والحكم مشتق في v_supplier_qualification'),
    'SUP-04' => array('route' => 'Suppliers/equipment_plan.php', 'probe' => 'equipments', 'kind' => 'TABLE',
                      'why' => 'معدات المورد المتاحة — المعدة المقدمة تحت عقد المورد؛ وملكية هوية الاصل للاسطول'),
    /* ── التعاقد ───────────────────────────────────────────────────────── */
    'SUP-05' => array('route' => 'Contracts/contract_coverage.php', 'probe' => 'contract_commitments', 'kind' => 'TABLE',
                      'why' => 'احتياجات التغطية — جانب الطلب قراءة مقيدة من المبيعات؛ الموردون يقراون ولا يكتبون'),
    'SUP-06' => array('route' => 'Suppliers/rfq_requests.php', 'probe' => 'supplier_rfqs', 'kind' => 'TABLE',
                      'why' => 'الترشيح ومراجعة التعاقد — قيد ترشيح ومراجعة تعاقد واحد بالحوكمة'),
    'SUP-07' => array('route' => 'Suppliers/rfq_requests.php', 'probe' => 'supplier_rfqs', 'kind' => 'TABLE',
                      'why' => 'عروض الموردين والتفاوض — عرض مورد واحد بطبقة اصدارات؛ يشترك مع سطح الترشيح'),
    'SUP-08' => array('route' => 'Suppliers/supplierscontracts.php', 'probe' => 'supplier_contracts', 'kind' => 'TABLE',
                      'why' => 'سجل عقود الموردين — راس العقد؛ والسجل المعياري supplier_contracts باسقاطه من الجدول الحي'),
    'SUP-09' => array('route' => 'Suppliers/supplier_contract_lines.php', 'probe' => 'supplier_contract_lines', 'kind' => 'TABLE',
                      'why' => 'بنود عقود الموردين — بند عقد مورد واحد بسعره ووحدته والتزام الاحتياط'),
    'SUP-10' => array('route' => 'Suppliers/supplier_rules.php', 'probe' => 'SupplierRuleService', 'kind' => 'SERVICE',
                      'why' => 'مصفوفة المسؤوليات والتكاليف — كل التزام عمود؛ وقواعد التحميل والجزاءات على المورد'),
    /* ── التشغيل ───────────────────────────────────────────────────────── */
    'SUP-11' => array('route' => 'Suppliers/shares_coverage.php', 'probe' => 'capacity_consumption_ledger', 'kind' => 'TABLE',
                      'why' => 'الخانات المكافئة وتوزيع الحصص — مورد بحاوية بنوع الية؛ والمنفذ من التايم شيت المعتمد'),
    'SUP-12' => array('route' => 'Operations/equipment_quota.php', 'probe' => 'equipment_quota.php', 'kind' => 'CMP03',
                      'why' => 'حصص الموردين والوحدات التعاقدية — وحدة تعاقدية واحدة بحصتها وهامشها'),
    'SUP-13' => array('route' => 'Suppliers/sup_handover.php', 'probe' => 'container_swaps', 'kind' => 'TABLE',
                      'why' => 'سجل تسليم الخانات — خانة بمورد بفترة اشغال؛ وتاريخا الدخول والخروج حكم الاسناد'),
    'SUP-14' => array('route' => 'Suppliers/equipment_plan.php', 'probe' => 'op_containers', 'kind' => 'TABLE',
                      'why' => 'توزيع الوحدات التعاقدية على المعدات — الطبقة التي قبل المعدة هي الوحدة التعاقدية'),
    'SUP-15' => array('route' => 'Suppliers/supplier_capacity.php', 'probe' => 'SupplierCapacityService', 'kind' => 'SERVICE',
                      'why' => 'الجاهزية والاحلال والاحتياط — واقعة دخول او خروج جاهزية او عضوية تناوب واحدة'),
    'SUP-16' => array('route' => 'Suppliers/supplier_capacity.php', 'probe' => 'supplier_contracts', 'kind' => 'TABLE',
                      'why' => 'مستهدفات الموردين — مستهدف شهري مشتق من السريان التعاقدي بلا ادخال'),
    'SUP-17' => array('route' => 'Suppliers/unit_statement_supplier.php', 'probe' => 'v_monthly_performance', 'kind' => 'TABLE',
                      'why' => 'الاداء والوحدات المعتمدة — قيد اداء معتمد واحد وحدة بشهر؛ ومصدره اعتماد العمليات'),
    'SUP-18' => array('route' => 'Suppliers/shares_coverage.php', 'probe' => 'coverage_settlement_lines', 'kind' => 'TABLE',
                      'why' => 'قياس التغطية والعجز والفائض — حاوية بنوع الية بشهر؛ سطر تغطية مشتق'),
    /* ── المالية ───────────────────────────────────────────────────────── */
    'SUP-19' => array('route' => 'Suppliers/supplier_advances.php', 'probe' => 'SupplierAdvanceService', 'kind' => 'SERVICE',
                      'why' => 'النيابية والسلف والخصومات — واقعة سلفة او خصم واحدة؛ وسقف الاستقطاع الاقل من النسبة والرصيد'),
    'SUP-20' => array('route' => 'Suppliers/supplier_entitlements.php', 'probe' => 'fin_entitlements', 'kind' => 'TABLE',
                      'why' => 'استحقاقات الموردين — استحقاق شهري واحد بمكوناته؛ مشتق لا مدخل'),
    'SUP-21' => array('route' => 'Suppliers/supplier_closure.php', 'probe' => 'supplier_contract_closures', 'kind' => 'TABLE',
                      'why' => 'الاقفال التعاقدي للوحدات التعاقدية — عقد بوحدة بفترة سريان بعملة'),
    'SUP-22' => array('route' => 'Suppliers/settlements.php', 'probe' => 'settlements', 'kind' => 'TABLE',
                      'why' => 'التسويات وكشف الحساب — مورد بشهر بعملة؛ والسالب يفتح ذمة مدينة ولا يبتلع'),
    'SUP-23' => array('route' => 'Suppliers/settlements.php', 'probe' => 'fin_requests', 'kind' => 'TABLE',
                      'why' => 'طلبات الدفع وحالة الصرف — طلب دفع واحد؛ والصرف قراءة عند الموردين وتنفيذ عند الخزينة'),
    'SUP-24' => array('route' => 'Finance/supplier_statement_fin.php', 'probe' => 'SupplierStatementService', 'kind' => 'SERVICE',
                      'why' => 'تخصيص الدفع على الاقفالات — سطر تخصيص دفعة على اقفال؛ وقاعدة التخصيص للمالية'),
    'SUP-25' => array('route' => 'Suppliers/supplier_board.php', 'probe' => 'settlements', 'kind' => 'TABLE',
                      'why' => 'اعمار الارصدة والالتزامات — مورد بعملة بشريحة تقادم؛ مشتق من الاستحقاق والتسوية'),
    /* ── الحوكمة ───────────────────────────────────────────────────────── */
    'SUP-26' => array('route' => 'Suppliers/supplier_violations.php', 'probe' => 'sup_violations', 'kind' => 'TABLE',
                      'why' => 'المخالفات والجزاءات — مخالفة او جزاء واحد بسجل استثناء'),
    'SUP-27' => array('route' => 'Suppliers/supplier_evaluation.php', 'probe' => 'SupplierEvaluationService', 'kind' => 'SERVICE',
                      'why' => 'تقييم المورد والاداء — تقييم دوري واحد وملف مخاطر المورد'),
    'SUP-28' => array('route' => 'Suppliers/supplier_closure.php', 'probe' => 'supplier_contract_closures', 'kind' => 'TABLE',
                      'why' => 'الملاحق والتصفية والاغلاق — واقعة ملحق او تصفية او اغلاق واحدة'),
    'SUP-29' => array('route' => 'Suppliers/supplier_capacity.php', 'probe' => 'equipments', 'kind' => 'TABLE',
                      'why' => 'مصادر القدرة والتكامل — سطر تكامل مصدر قدرة واحد؛ ومصدره الاسطول'),
    /* ── اللوحة ────────────────────────────────────────────────────────── */
    'SUP-30' => array('route' => 'Suppliers/supplier_board.php', 'probe' => 'suppliers', 'kind' => 'TABLE',
                      'why' => 'لوحة ادارة الموردين — مؤشر مشتق كليا؛ خارج الدورة ولا ادخال فيها'),
    /* ── المرجعيات ─────────────────────────────────────────────────────── */
    'SUP-31' => array('route' => 'Suppliers/supplier_rules.php', 'probe' => 'SupplierRuleService', 'kind' => 'SERVICE',
                      'why' => 'القوائم المرجعية — كل قائمة عمود؛ وقواعد التحميل مرجع الحكم على المورد'),
    'SUP-32' => array('route' => 'Suppliers/supplier_documents.php', 'probe' => 'SupplierDocumentService', 'kind' => 'SERVICE',
                      'why' => 'سجل المستندات والضمانات — مستند او ضمان واحد بصلاحيته؛ والخدمة تملك دورة المستند'),
    'SUP-33' => array('route' => '', 'probe' => '', 'kind' => 'GAP',
                      'why' => 'قاموس قواعد الاستنتاج — قاعدة استنتاج واحدة؛ سطح هندسة نظم لم يبن'),
    'SUP-34' => array('route' => 'Governance/sensitive_fields.php', 'probe' => 'sensitive_fields.php', 'kind' => 'CMP03',
                      'why' => 'القاموس وخريطة الترحيل — تعريف حقل واحد؛ ومصدره سجل الاعمدة المركزي'),
    'SUP-35' => array('route' => '', 'probe' => '', 'kind' => 'GAP',
                      'why' => 'تتبع التعبئة والترحيل — تحويل ترحيل موثق واحد؛ سطح هندسة نظم لم يبن'),
    'SUP-36' => array('route' => '', 'probe' => '', 'kind' => 'GAP',
                      'why' => 'خريطة الترحيل — عمود مصدري ومصيره؛ سطح هندسة نظم لم يبن'),
    'SUP-37' => array('route' => 'Governance/gov_reports.php', 'probe' => 'sod_conflicts', 'kind' => 'TABLE',
                      'why' => 'تقرير المراجعة والقبول — بند مراجعة او قبول واحد؛ واقرار الادارة يحمله سجل الاقرارات'),
    );
}

/**
 * إثباتُ المِرساةِ قياسًا: هل يمسُّ ملفُّ المسارِ المُعلَنِ ما أُعلن له؟
 * @return array [sid, owner, verdict, rule]
 */
function repair01_w8_prove_anchor(mysqli $c, $ROOT, array $a)
{
    if ($a['kind'] === 'GAP' || $a['route'] === '') {
        return array('sid' => '', 'owner' => '', 'verdict' => 'NOT_BUILT', 'rule' => 'W8_TARGET_GAP');
    }
    $rt = $c->real_escape_string($a['route']);
    $row = $c->query("SELECT screen_id, owner_code, on_disk FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { return array('sid' => '', 'owner' => '', 'verdict' => 'ROUTE_NOT_IN_REGISTRY', 'rule' => 'W8_ANCHOR_UNPROVEN'); }
    if ((int) $row['on_disk'] !== 1) {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'ROUTE_NOT_ON_DISK', 'rule' => 'W8_ANCHOR_UNPROVEN');
    }
    $path = $ROOT . '/' . $a['route'];
    $src = is_file($path) ? (string) file_get_contents($path) : '';
    if ($src === '') {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'FILE_UNREADABLE', 'rule' => 'W8_ANCHOR_UNPROVEN');
    }
    $p = preg_quote($a['probe'], '~'); $hit = false; $rule = '';
    if ($a['kind'] === 'TABLE') {
        $hit = (bool) (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . $p . '`?\b~i', $src)
                    || preg_match('~[\'"]' . $p . '[\'"]\s*[,\)]~', $src));
        $rule = 'W8_ROUTE_TOUCHES_TABLE';
    } elseif ($a['kind'] === 'SERVICE') {
        $hit = strpos($src, $a['probe']) !== false;
        $rule = 'W8_ROUTE_REQUIRES_SERVICE';
    } elseif ($a['kind'] === 'CMP03') {
        /* ◆ **سطحُ المخزنِ البينيِّ يُثبَت بعقدِه لا بجدولِه**: `cmp03_screen_rows`
             جدولٌ واحدٌ لعشراتِ الأسطح، فالمِرساةُ عليه تثبت أيَّ سطحٍ منها.
             والمُثبِتُ ثابتُ `$CANONICAL` الذي يفصل صفوفَ هذا السطحِ عن غيرِه. */
        $hit = strpos($src, "\$CANONICAL = '" . $a['probe'] . "'") !== false;
        $rule = 'W8_ROUTE_DECLARES_CANONICAL';
    }
    return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                 'verdict' => $hit ? 'ANCHORED' : 'ANCHOR_PROBE_MISSED',
                 'rule' => $hit ? $rule : 'W8_ANCHOR_UNPROVEN');
}

/* ═══════════════════════════════════════════════════════════════════════════
   ② الكياناتُ الرئيسيّةُ في النطاق — آلةُ حالةٍ لكلٍّ منها شرطُ إغلاق (§٧)
   ═══════════════════════════════════════════════════════════════════════════ */
function repair01_w8_entity_types()
{
    return array('opportunities', 'quotations', 'contracts', 'claims',
                 'suppliers', 'supplier_contracts', 'settlements', 'supplier_contract_closures');
}

/**
 * أحداثُ النطاقِ التي تصدر عن هذه المرحلة — عقدُها قبلَ أوّلِ إطلاق.
 * ⛔ **ولا يُخترَع اسمُ حدث**: القائمةُ هي ما تنشره شيفرةُ الوحدتَين **فعلًا**،
 *   وتُعاد إثباتُها من القرصِ في `repair01_w8_stage_event_emitters()`. واختراعُ
 *   أسماءٍ «مستهدَفةٍ» يكتب عقودًا لأحداثٍ لا تُطلَق فيصير الدفترُ أدبًا.
 */
function repair01_w8_stage_events()
{
    return array_keys(repair01_w8_stage_event_emitters());
}

/** الحدثُ ← الملفُّ الذي ينشره — والبوّابةُ تعيد إثباتَه من القرص */
function repair01_w8_stage_event_emitters()
{
    return array(
        'contract.signed'                                 => 'app/Services/Contract/ContractStateMachine.php',
        'contract.state.changed'                          => 'app/Services/Contract/ContractStateMachine.php',
        'contract.advance.received'                       => 'Contracts/advance_helpers.php',
        'billing.claim.invoiced'                          => 'Contracts/claim_helpers.php',
        'billing.note.credit_issued'                      => 'Contracts/note_helpers.php',
        'billing.note.debit_issued'                       => 'Contracts/note_helpers.php',
        'retention.released'                              => 'Contracts/claim_helpers.php',
        'procurement.supplier_contract.state_changed'     => 'app/Services/Contract/SupplierContractService.php',
        'procurement.supplier_contract.termination_revoked' => 'app/Services/Contract/SupplierContractService.php',
        'settlement.approved'                             => 'app/Services/Settlement/SettlementService.php',
    );
}

/* ═══════════════════════════════════════════════════════════════════════════
   ③ الانحدارُ — كلُّ فحصٍ يعيد بناءَ مقامِه ويحمل متطلَّبَه الكاشف
   ═══════════════════════════════════════════════════════════════════════════
   ◆ الشكل: `key => [family, title, revealed_by, denom_sql, bad_sql, expect]`.
     `expect` دائمًا `0` مخالفةً — و`denom_sql` **يُعاد بناؤه** فلا يمرُّ
     فحصٌ على مقامٍ صفريٍّ إلّا بحكمِ `EMPTY_DENOM` مُعلَنٍ (درسُ W07 §ملحق).
   ═══════════════════════════════════════════════════════════════════════════ */
function repair01_w8_regression_checks()
{
    return array(

/* ══ SAL — رحلةُ العميل ══════════════════════════════════════════════════ */
'SAL_CLIENT_TENANT' => array('SAL', 'كلُّ عميلٍ داخلَ كيانٍ قانونيّ', 'SAL-01',
  "SELECT COUNT(*) FROM clients WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM clients WHERE COALESCE(is_deleted,0)=0 AND COALESCE(company_id,0)<=0"),

'SAL_PROJECT_CLIENT_FK' => array('SAL', 'المشروعُ يشير إلى عميلٍ قائم', 'SAL-03',
  "SELECT COUNT(*) FROM project WHERE COALESCE(is_deleted,0)=0 AND COALESCE(client_id,0)>0",
  "SELECT COUNT(*) FROM project p WHERE COALESCE(p.is_deleted,0)=0 AND COALESCE(p.client_id,0)>0
     AND NOT EXISTS (SELECT 1 FROM clients c WHERE c.id=p.client_id)"),

'SAL_OPP_CLIENT_FK' => array('SAL', 'الفرصةُ تشير إلى عميلٍ قائم', 'SAL-04',
  "SELECT COUNT(*) FROM opportunities WHERE COALESCE(is_deleted,0)=0 AND COALESCE(client_id,0)>0",
  "SELECT COUNT(*) FROM opportunities o WHERE COALESCE(o.is_deleted,0)=0 AND COALESCE(o.client_id,0)>0
     AND NOT EXISTS (SELECT 1 FROM clients c WHERE c.id=o.client_id)"),

/* ◆ **والنشاطُ مرتبطٌ بمحورَي (نوعٌ × مُعرِّف) لا بعمودِ فرصةٍ صريح** — فالرسوُّ
     على `entity_type`/`entity_id` لا على عمودٍ متوهَّم. */
'SAL_ACT_OPP_FK' => array('SAL', 'النشاطُ يشير إلى فرصةٍ قائمة', 'SAL-05',
  "SELECT COUNT(*) FROM activities WHERE COALESCE(is_deleted,0)=0 AND entity_type='opportunity'",
  "SELECT COUNT(*) FROM activities a WHERE COALESCE(a.is_deleted,0)=0 AND a.entity_type='opportunity'
     AND NOT EXISTS (SELECT 1 FROM opportunities o WHERE o.id=a.entity_id)"),

'SAL_ACT_CLIENT_FK' => array('SAL', 'النشاطُ يشير إلى عميلٍ قائم', 'SAL-05',
  "SELECT COUNT(*) FROM activities WHERE COALESCE(is_deleted,0)=0 AND entity_type='client'",
  "SELECT COUNT(*) FROM activities a WHERE COALESCE(a.is_deleted,0)=0 AND a.entity_type='client'
     AND NOT EXISTS (SELECT 1 FROM clients c WHERE c.id=a.entity_id)"),

'SAL_QUOT_OPP_FK' => array('SAL', 'العرضُ يشير إلى فرصةٍ قائمة', 'SAL-07',
  "SELECT COUNT(*) FROM quotations WHERE COALESCE(is_deleted,0)=0 AND COALESCE(opportunity_id,0)>0",
  "SELECT COUNT(*) FROM quotations q WHERE COALESCE(q.is_deleted,0)=0 AND COALESCE(q.opportunity_id,0)>0
     AND NOT EXISTS (SELECT 1 FROM opportunities o WHERE o.id=q.opportunity_id)"),

'SAL_QUOT_CLIENT_FK' => array('SAL', 'العرضُ يشير إلى عميلٍ قائم', 'SAL-07',
  "SELECT COUNT(*) FROM quotations WHERE COALESCE(is_deleted,0)=0 AND COALESCE(client_id,0)>0",
  "SELECT COUNT(*) FROM quotations q WHERE COALESCE(q.is_deleted,0)=0 AND COALESCE(q.client_id,0)>0
     AND NOT EXISTS (SELECT 1 FROM clients c WHERE c.id=q.client_id)"),

'SAL_CONTRACT_TENANT' => array('SAL', 'كلُّ عقدٍ داخلَ كيانٍ قانونيّ', 'SAL-11',
  "SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0 AND COALESCE(company_id,0)<=0"),

'SAL_CONTRACT_PROJECT_FK' => array('SAL', 'العقدُ يشير إلى مشروعٍ قائم', 'SAL-11',
  "SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0 AND COALESCE(project_id,0)>0",
  "SELECT COUNT(*) FROM contracts t WHERE COALESCE(t.is_deleted,0)=0 AND COALESCE(t.project_id,0)>0
     AND NOT EXISTS (SELECT 1 FROM project p WHERE p.id=t.project_id)"),

'SAL_PAUSE_STATE_RULE' => array('SAL', 'المعلَّقُ وحدَه يحمل حالةَ ما قبلَ التعليق', 'SAL-11',
  "SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0
     AND contract_status <> 'معلَّق' AND pause_state_before IS NOT NULL"),

'SAL_COMMIT_CONTRACT_FK' => array('SAL', 'الالتزامُ التجاريُّ يشير إلى عقدٍ قائم', 'SAL-15',
  "SELECT COUNT(*) FROM contract_commitments WHERE COALESCE(is_deleted,0)=0 AND COALESCE(contract_ref,0)>0",
  "SELECT COUNT(*) FROM contract_commitments t WHERE COALESCE(t.is_deleted,0)=0 AND COALESCE(t.contract_ref,0)>0
     AND NOT EXISTS (SELECT 1 FROM contracts c WHERE c.id=t.contract_ref)"),

'SAL_OBLIG_CONTRACT_FK' => array('SAL', 'بندُ مصفوفةِ الالتزاماتِ يشير إلى عقدٍ قائم', 'SAL-13',
  "SELECT COUNT(*) FROM contract_obligations WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM contract_obligations t WHERE COALESCE(t.is_deleted,0)=0
     AND NOT EXISTS (SELECT 1 FROM contracts c WHERE c.id=t.client_contract_id)"),

/* ◆ **والحبّةُ (عقدٌ × بندٌ × إصدارٌ × شهر)** — والإصدارُ محورٌ لا زائدةٌ:
     خطُّ أساسٍ يُعاد إصدارُه بملحقٍ صفٌّ ثانٍ مشروعٌ لا تكرار. */
'SAL_PLAN_GRAIN' => array('SAL', 'حبّةُ خطِّ الأساسِ عقدٌ × بندٌ × إصدارٌ × شهرٌ بلا تكرار', 'SAL-14',
  "SELECT COUNT(*) FROM contract_monthly_plan",
  "SELECT COALESCE(SUM(n-1),0) FROM (SELECT COUNT(*) n FROM contract_monthly_plan
     GROUP BY contract_id, line_id, plan_version, period_month HAVING COUNT(*)>1) x"),

'SAL_PLAN_CONTRACT_FK' => array('SAL', 'سطرُ خطِّ الأساسِ يشير إلى عقدٍ قائم', 'SAL-14',
  "SELECT COUNT(*) FROM contract_monthly_plan",
  "SELECT COUNT(*) FROM contract_monthly_plan t WHERE NOT EXISTS
     (SELECT 1 FROM contracts c WHERE c.id=t.contract_id)"),

'SAL_CLAIM_CONTRACT_FK' => array('SAL', 'المستخلصُ يشير إلى عقدٍ قائم', 'SAL-18',
  "SELECT COUNT(*) FROM claims WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM claims t WHERE COALESCE(t.is_deleted,0)=0
     AND NOT EXISTS (SELECT 1 FROM contracts c WHERE c.id=t.contract_id)"),

/* ◆ **حسابانِ لا حساب** (‏«المروحةُ تعترف والمستخلصُ يفوتر»): الإجماليُّ مجموعُ
     البنودِ **بسالبِها وموجبِها**، والصافي إجماليٌّ ناقصُ المحتجَز. وقياسُ
     الصافي مقابلَ مجموعِ البنودِ مباشرةً يُسقط ٢٩٧ من ٣٠٠ **وهو غلطُ المقياسِ
     لا غلطُ البيانات** — لأنَّ المحتجَزَ خصمُ مطالبةٍ لا بندَ اعتراف. */
'SAL_CLAIM_GROSS_MATH' => array('SAL', 'إجماليُّ المستخلصِ مُعادُ الحسابِ من بنودِه', 'SAL-18',
  "SELECT COUNT(*) FROM claims WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM claims c WHERE COALESCE(c.is_deleted,0)=0
     AND ABS(ROUND(c.gross_amount,2) - ROUND((SELECT COALESCE(SUM(l.amount),0) FROM claim_lines l WHERE l.claim_id=c.id),2)) > 0.01"),

'SAL_CLAIM_NET_MATH' => array('SAL', 'صافي المستخلصِ إجماليٌّ ناقصُ المحتجَز', 'SAL-18',
  "SELECT COUNT(*) FROM claims WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM claims c WHERE COALESCE(c.is_deleted,0)=0
     AND ABS(ROUND(c.net_amount,2) - ROUND(c.gross_amount - c.retention_amount,2)) > 0.01"),

'SAL_CLAIM_NO_EMPTY' => array('SAL', 'لا مستخلصَ بلا بندٍ واحدٍ يُسنده', 'SAL-18',
  "SELECT COUNT(*) FROM claims WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM claims c WHERE COALESCE(c.is_deleted,0)=0
     AND NOT EXISTS (SELECT 1 FROM claim_lines l WHERE l.claim_id=c.id)"),

'SAL_CLAIM_PERIOD_DUP' => array('SAL', 'لا مستخلصانِ حيّانِ لعقدٍ في الفترةِ نفسِها', 'SAL-18',
  "SELECT COUNT(*) FROM claims WHERE COALESCE(is_deleted,0)=0 AND state NOT IN ('cancelled')",
  "SELECT COALESCE(SUM(n-1),0) FROM (SELECT COUNT(*) n FROM claims WHERE COALESCE(is_deleted,0)=0
     AND state NOT IN ('cancelled') GROUP BY contract_id, period_from, period_to HAVING COUNT(*)>1) x"),

'SAL_CLAIM_FIN_HANDOVER' => array('SAL', 'المستخلصُ المعتمَدُ سلّم أثرَه للماليّةِ بواقعةٍ مرجعيّة', 'SAL-18',
  "SELECT COUNT(*) FROM claims WHERE COALESCE(is_deleted,0)=0 AND state IN ('approved','invoiced','collected')",
  "SELECT COUNT(*) FROM claims WHERE COALESCE(is_deleted,0)=0 AND state IN ('approved','invoiced','collected')
     AND COALESCE(receivable_id,0)<=0"),

'SAL_AMEND_CONTRACT_FK' => array('SAL', 'الملحقُ يشير إلى عقدٍ قائم', 'SAL-19',
  "SELECT COUNT(*) FROM contract_amendments WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM contract_amendments t WHERE COALESCE(t.is_deleted,0)=0
     AND NOT EXISTS (SELECT 1 FROM contracts c WHERE c.id=t.contract_id)"),

/* ══ SUP — رحلةُ المورد ══════════════════════════════════════════════════ */
'SUP_TENANT' => array('SUP', 'كلُّ مورِّدٍ داخلَ كيانٍ قانونيّ', 'SUP-01',
  "SELECT COUNT(*) FROM suppliers WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM suppliers WHERE COALESCE(is_deleted,0)=0 AND COALESCE(company_id,0)<=0"),

'SUP_CONTRACT_SUPPLIER_FK' => array('SUP', 'عقدُ المورِّدِ يشير إلى مورِّدٍ قائم', 'SUP-08',
  "SELECT COUNT(*) FROM supplier_contracts WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM supplier_contracts t WHERE COALESCE(t.is_deleted,0)=0
     AND NOT EXISTS (SELECT 1 FROM suppliers s WHERE s.id=t.supplier_id)"),

'SUP_REGISTRY_PROJECTION' => array('SUP', 'السجلُّ المعياريُّ يسقط عن الجدولِ الحيِّ بلا فقد', 'SUP-08',
  "SELECT COUNT(*) FROM supplierscontracts",
  "SELECT COUNT(*) FROM supplierscontracts t WHERE NOT EXISTS
     (SELECT 1 FROM supplier_contracts r WHERE r.source_table='supplierscontracts' AND r.source_id=t.id)"),

'SUP_CONTRACT_QUALIFIED' => array('SUP', 'لا عقدَ توريدٍ حيٍّ على مورِّدٍ غيرِ مؤهَّل', 'SUP-03',
  "SELECT COUNT(*) FROM supplier_contracts WHERE COALESCE(is_deleted,0)=0
     AND state IN ('موقَّع','نافذ','قيد التنفيذ','معدَّل','مجدَّد')",
  "SELECT COUNT(*) FROM supplier_contracts sc
     JOIN v_supplier_qualification q ON q.supplier_id = sc.supplier_id
    WHERE COALESCE(sc.is_deleted,0)=0
      AND sc.state IN ('موقَّع','نافذ','قيد التنفيذ','معدَّل','مجدَّد')
      AND COALESCE(q.missing_count,0) > 0"),

'SUP_LINE_CONTRACT_FK' => array('SUP', 'بندُ عقدِ المورِّدِ يشير إلى عقدٍ قائم', 'SUP-09',
  "SELECT COUNT(*) FROM supplier_contract_lines WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM supplier_contract_lines t WHERE COALESCE(t.is_deleted,0)=0
     AND NOT EXISTS (SELECT 1 FROM supplier_contracts c WHERE c.id=t.contract_id)"),

'SUP_CAPACITY_CONTRACT_FK' => array('SUP', 'سطرُ الطاقةِ يشير إلى عقدٍ قائم', 'SUP-15',
  "SELECT COUNT(*) FROM supplier_capacity WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM supplier_capacity t WHERE COALESCE(t.is_deleted,0)=0
     AND NOT EXISTS (SELECT 1 FROM supplier_contracts c WHERE c.id=t.contract_id)"),

'SUP_SETTLE_MATH' => array('SUP', 'صافي التسويةِ مُعادُ الحسابِ من إجماليِّها وتحميلاتِها', 'SUP-22',
  "SELECT COUNT(*) FROM settlements WHERE party_type='supplier'",
  "SELECT COUNT(*) FROM settlements WHERE party_type='supplier'
     AND ABS(ROUND(net_amount,2) - ROUND(gross_amount - charges_amount,2)) > 0.01"),

'SUP_SETTLE_DIRECTION' => array('SUP', 'اتّجاهُ التسويةِ يطابق إشارةَ صافيها', 'SUP-22',
  "SELECT COUNT(*) FROM settlements WHERE party_type='supplier'",
  "SELECT COUNT(*) FROM settlements WHERE party_type='supplier'
     AND ((net_amount < 0 AND net_direction <> 'receivable')
       OR (net_amount >= 0 AND net_direction <> 'payable'))"),

'SUP_SETTLE_NEG_RECEIVABLE' => array('SUP', 'السالبُ يفتح ذمّةً مدينةً ولا يُبتلَع', 'SUP-22',
  "SELECT COUNT(*) FROM settlements WHERE party_type='supplier' AND net_direction='receivable'",
  "SELECT COUNT(*) FROM settlements WHERE party_type='supplier' AND net_direction='receivable'
     AND COALESCE(receivable_due_id,0)<=0"),

'SUP_SETTLE_SUPPLIER_FK' => array('SUP', 'التسويةُ تشير إلى مورِّدٍ قائم', 'SUP-22',
  "SELECT COUNT(*) FROM settlements WHERE party_type='supplier'",
  "SELECT COUNT(*) FROM settlements t WHERE t.party_type='supplier'
     AND NOT EXISTS (SELECT 1 FROM suppliers s WHERE s.id=t.party_ref)"),

'SUP_PAYREQ_APPROVED' => array('SUP', 'لا طلبَ دفعٍ قبلَ اعتمادِ التسوية', 'SUP-23',
  "SELECT COUNT(*) FROM settlements WHERE party_type='supplier' AND COALESCE(payment_request_id,0)>0",
  "SELECT COUNT(*) FROM settlements WHERE party_type='supplier' AND COALESCE(payment_request_id,0)>0
     AND state IN ('draft','review','cancelled')"),

'SUP_ADVANCE_SUPPLIER_FK' => array('SUP', 'طلبُ السلفةِ يشير إلى مورِّدٍ قائم', 'SUP-19',
  "SELECT COUNT(*) FROM supplier_advance_requests WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM supplier_advance_requests t WHERE COALESCE(t.is_deleted,0)=0
     AND NOT EXISTS (SELECT 1 FROM suppliers s WHERE s.id=t.supplier_id)"),

'SUP_EVAL_SUPPLIER_FK' => array('SUP', 'التقييمُ يشير إلى مورِّدٍ قائم', 'SUP-27',
  "SELECT COUNT(*) FROM supplier_evaluations WHERE COALESCE(is_deleted,0)=0",
  "SELECT COUNT(*) FROM supplier_evaluations t WHERE COALESCE(t.is_deleted,0)=0
     AND NOT EXISTS (SELECT 1 FROM suppliers s WHERE s.id=t.supplier_id)"),

'SUP_CLOSURE_CONTRACT_FK' => array('SUP', 'الإقفالُ يشير إلى عقدِ مورِّدٍ قائم', 'SUP-28',
  "SELECT COUNT(*) FROM supplier_contract_closures",
  "SELECT COUNT(*) FROM supplier_contract_closures t WHERE NOT EXISTS
     (SELECT 1 FROM supplier_contracts c WHERE c.id=t.contract_id)"),

/* ══ XCUT — ما يكشفه المستهدَفُ الجديدُ في دفاترِ الحملةِ نفسِها ═════════ */
'XCUT_GAP_WAVE_TRUTH' => array('XCUT', 'موجةُ صفِّ الفجوةِ تطابق مرحلةَ وحدتِه', 'SAL-20',
  "SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage='W02' AND wave_stage<>''",
  "SELECT COUNT(*) FROM repair01_target_gaps g
     WHERE g.origin_stage='W02' AND g.wave_stage<>''
       AND g.wave_stage <> CONCAT('W', LPAD((SELECT MAX(r.stage_no) FROM repair01_requirements r
            WHERE r.unit LIKE CONCAT(SUBSTRING(g.unit,5),' %')), 2, '0'))
       AND (SELECT MAX(r.stage_no) FROM repair01_requirements r
            WHERE r.unit LIKE CONCAT(SUBSTRING(g.unit,5),' %')) IS NOT NULL"),

'XCUT_SCOPE_OWNER' => array('XCUT', 'كلُّ شاشةٍ في مجلَّداتِ النطاقِ لها مالكٌ في السجلّ', 'SUP-30',
  "SELECT COUNT(*) FROM repair01_screen_registry
     WHERE on_disk=1 AND (route LIKE 'Suppliers/%' OR route LIKE 'Clients/%' OR route LIKE 'Opportunities/%')",
  "SELECT COUNT(*) FROM repair01_screen_registry
     WHERE on_disk=1 AND (route LIKE 'Suppliers/%' OR route LIKE 'Clients/%' OR route LIKE 'Opportunities/%')
       AND owner_code=''"),
    );
}

/**
 * تشغيلُ الانحدارِ — يعيد صفًّا لكلِّ فحصٍ بمقامِه وحكمِه.
 * ⛔ المقامُ الصفريُّ يخرج `EMPTY_DENOM` لا `PASS` (‏درسُ W07 §ملحق).
 */
function repair01_w8_run_regression(mysqli $c)
{
    $out = array();
    foreach (repair01_w8_regression_checks() as $key => $spec) {
        list($family, $title, $rev, $denomSql, $badSql) = $spec;
        $denom = repair01_w8_one($c, $denomSql);
        if ($denom === null) {
            $out[$key] = array('family' => $family, 'title' => $title, 'rev' => $rev,
                               'denom' => 0, 'measured' => 0, 'verdict' => 'FAIL',
                               'detail' => 'تعذَّر بناءُ المقام: ' . $c->error);
            continue;
        }
        $denom = (int) $denom;
        if ($denom === 0) {
            $out[$key] = array('family' => $family, 'title' => $title, 'rev' => $rev,
                               'denom' => 0, 'measured' => 0, 'verdict' => 'EMPTY_DENOM',
                               'detail' => 'مقامٌ صفريّ — لا يُقاس ولا يمرُّ صامتًا');
            continue;
        }
        $bad = repair01_w8_one($c, $badSql);
        if ($bad === null) {
            $out[$key] = array('family' => $family, 'title' => $title, 'rev' => $rev,
                               'denom' => $denom, 'measured' => 0, 'verdict' => 'FAIL',
                               'detail' => 'تعذَّر قياسُ المخالف: ' . $c->error);
            continue;
        }
        $bad = (int) $bad;
        $out[$key] = array('family' => $family, 'title' => $title, 'rev' => $rev,
                           'denom' => $denom, 'measured' => $bad,
                           'verdict' => $bad === 0 ? 'PASS' : 'FAIL',
                           'detail' => 'مخالفٌ ' . $bad . ' من ' . $denom);
    }
    return $out;
}

/**
 * الفحصُ الساقطُ ← القرارُ الذي يُعلن عددَه.
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **وسقوطٌ بلا صفٍّ هنا سقوطٌ مسكوتٌ عنه** — والبوّابةُ تُسقطه. والقرارُ
 *   يجب أن يحمل **العددَ المقيسَ حرفًا**، فيقفل الاتّجاهَ في الجهتَين:
 *   زيادةٌ خرقٌ جديد، ونقصانٌ إعلانٌ متقادمٌ يجب تحديثُه.
 */
function repair01_w8_declared_failures()
{
    return array(
        'SAL_PLAN_CONTRACT_FK'       => 'W8-D-04',
        'SUP_CONTRACT_QUALIFIED'     => 'W8-D-08',
        'SUP_SETTLE_SUPPLIER_FK'     => 'W8-D-06',
        'SUP_SETTLE_NEG_RECEIVABLE'  => 'W8-D-07',
    );
}
