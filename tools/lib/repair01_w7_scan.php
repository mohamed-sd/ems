<?php
/**
 * tools/lib/repair01_w7_scan.php — مقاييسُ المرحلةِ السابعة (الصيانة والنقل)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تُعيد القياسَ ولا تقرأ ما خزَّنَته الأداة** (‏_CONTEXT §قواعد القياس ①).
 * ◆ **ولا عتبةٌ رقميّةٌ مكتوبةٌ هنا**: كلُّها من `repair01_w7_thresholds` (§٥).
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (§٣): أسماءُ الجداولِ والأعمدةِ والقواعد.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (!defined('EMS_CLI')) { define('EMS_CLI', true); }

/* ═══════════════════════════════════════════════════════════════════════════
   ① أدواتُ قياسٍ عامّة
   ═══════════════════════════════════════════════════════════════════════════ */

function repair01_w7_one(mysqli $c, $sql)
{
    $r = $c->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

function repair01_w7_table_exists(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w7_col_exists(mysqli $c, $t, $col)
{
    if (!repair01_w7_table_exists($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w7_check_exists(mysqli $c, $t, $name)
{
    $r = $c->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $c->real_escape_string($t) . "'
                       AND CONSTRAINT_NAME = '" . $c->real_escape_string($name) . "'");
    return $r && $r->num_rows > 0;
}

/** رموزُ الإداراتِ المالكةِ للمرحلة — تُشتقُّ من وحداتِ متطلَّباتِها لا تُكتب */
function repair01_w7_scope_codes(mysqli $c)
{
    $codes = array();
    $r = $c->query("SELECT DISTINCT unit FROM repair01_requirements WHERE stage_no = 7");
    while ($r && $x = $r->fetch_assoc()) {
        if (preg_match('/^(\d{2})\s/u', $x['unit'], $m)) { $codes['DEP-' . $m[1]] = true; }
    }
    return array_keys($codes);
}

/** عتباتُ المرحلةِ من السجلِّ — ولا رقمَ مكتوبٌ في هذا الملفّ */
function repair01_w7_thresholds(mysqli $c)
{
    $out = array();
    if (!repair01_w7_table_exists($c, 'repair01_w7_thresholds')) { return $out; }
    $r = $c->query("SELECT threshold_key, value_num, why, decision_ref FROM repair01_w7_thresholds");
    while ($r && $x = $r->fetch_assoc()) {
        $out[$x['threshold_key']] = array('value' => (float) $x['value_num'],
                                          'why' => (string) $x['why'], 'ref' => (string) $x['decision_ref']);
    }
    return $out;
}

/** حارسُ الشاشةِ كما يُقاس من ملفِّها — لا كما يُدَّعى في السجلّ */
function repair01_w7_guard_of($ROOT, $route)
{
    $path = $ROOT . '/' . $route;
    if (!is_file($path)) { return array('kind' => 'NONE', 'evidence' => 'لا ملف على القرص'); }
    $src = (string) file_get_contents($path);
    if (strpos($src, 'check_page_permissions') !== false) {
        return array('kind' => 'SELF_EARLY', 'evidence' => 'check_page_permissions في الملف');
    }
    if (strpos($src, 'ems_gov_flash_redirect') !== false || strpos($src, 'insidebar.php') !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'حارس القشرة insidebar/gov_flash');
    }
    if (strpos($src, "\$_SESSION['user']") !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'فحص الجلسة في الملف');
    }
    return array('kind' => 'NONE', 'evidence' => 'لا حارس مقيس');
}

/* ═══════════════════════════════════════════════════════════════════════════
   ② مِرساةُ المتطلَّبِ إلى سطحِه — مُعلَنةٌ ومقيسةٌ معًا
   ═══════════════════════════════════════════════════════════════════════════
   ◆ `probe_kind`: `TABLE` جدولٌ يمسُّه الملفّ · `CMP03` ثابتُ `$CANONICAL` ·
     `SERVICE` صنفٌ يستدعيه · `GAP` لم يُبنَ وله موجة.
   ═══════════════════════════════════════════════════════════════════════════ */
function repair01_w7_anchors()
{
    return array(
        /* ── 14 إدارة الصيانة · اللوحة ─────────────────────────────────── */
        'MNT-01' => array('route' => 'Maintenance/dashboard_mnt.php', 'probe' => 'mnt_order', 'kind' => 'TABLE',
                          'why' => 'لوحة الصيانة والجاهزية — مؤشر × فترة قراءة حية مشتقة من الاوامر والبلاغات وشهادات الجاهزية'),
        /* ── دورةُ العطل ───────────────────────────────────────────────── */
        'MNT-04' => array('route' => 'Maintenance/breakdown_intake.php', 'probe' => 'mnt_breakdown', 'kind' => 'TABLE',
                          'why' => 'البلاغ الفني واستقبال العطل — البلاغ ينشا في مركز البلاغات ويصل الصيانة محالا؛ وهذا سطح الاستقبال لا سطح الانشاء'),
        'MNT-05' => array('route' => 'Maintenance/inspections.php', 'probe' => 'mnt_inspection', 'kind' => 'TABLE',
                          'why' => 'اوامر التفتيش الواردة من الاسطول — التنفيذ هنا والملكية للاسطول (FLEET-05 · W05)'),
        'MNT-06' => array('route' => 'Maintenance/failure_report.php', 'probe' => 'failure_codes', 'kind' => 'TABLE',
                          'why' => 'طلب الفحص والتشخيص — التشخيص يثبت عقدة الشجرة النهائية التي يقوم عليها الاثر'),
        'MNT-07' => array('route' => 'Maintenance/orders.php', 'probe' => 'mnt_order', 'kind' => 'TABLE',
                          'why' => 'امر العمل — الشاشة الام بتبويباتها؛ ومدير الصيانة يعتمد الامر والحالة الفنية تعود للاسطول'),
        'MNT-08' => array('route' => 'Maintenance/orders.php', 'probe' => 'mnt_order_labor', 'kind' => 'TABLE',
                          'why' => 'عمالة امر العمل — كل فني بساعاته على الامر؛ سجل تابع لا سطح مستقل'),
        'MNT-09' => array('route' => 'Maintenance/orders.php', 'probe' => 'mnt_order_part', 'kind' => 'TABLE',
                          'why' => 'بنود امر العمل — قطع ومصنعيات؛ والصرف الفعلي بسند من المخازن والتكلفة تقرا لا تكتب'),
        'MNT-10' => array('route' => 'Maintenance/part_requests.php', 'probe' => 'mnt_part_request', 'kind' => 'TABLE',
                          'why' => 'طلب صرف القطع لامر العمل — الصيانة تطلب والمخازن تصرف بسندها؛ ولا صرف لامر مقفل'),
        'MNT-11' => array('route' => 'Maintenance/external_repairs.php', 'probe' => 'mnt_external_repair', 'kind' => 'TABLE',
                          'why' => 'الاصلاح الخارجي ومطالبات الضمان — الاحالة بعقدها ومطالبة الضمان بمرجع عقد المورد'),
        /* ── الوقائية ──────────────────────────────────────────────────── */
        'MNT-12' => array('route' => 'Maintenance/preventive_plans.php', 'probe' => 'mnt_plan', 'kind' => 'TABLE',
                          'why' => 'الخطة الوقائية بالساعات — ساعات المحرك من التايم شيت المعتمد وحده والفاصل من خطة الصانع'),
        'MNT-13' => array('route' => 'Maintenance/daily_care.php', 'probe' => 'mnt_daily_care', 'kind' => 'TABLE',
                          'why' => 'العناية اليومية والتشحيم — ينفذها المشغل او الفني بقائمة النوع والنتيجة غير الطبيعية تفتح بلاغا'),
        /* ── الإقفال والجاهزية ─────────────────────────────────────────── */
        'MNT-14' => array('route' => 'Maintenance/return_to_service.php', 'probe' => 'mnt_return_cert', 'kind' => 'TABLE',
                          'why' => 'الاقفال وشهادة اعادة الخدمة — الشهادة وحدها تعيد المعدة وتحدث حالتها الفنية عند الاسطول'),
        'MNT-15' => array('route' => 'Maintenance/repeat_repairs.php', 'probe' => 'mnt_repeat_repair', 'kind' => 'TABLE',
                          'why' => 'سجل اعادة الاصلاح — التكرار خلال صلاحية الشهادة يفتح تحليل السبب الجذري'),
        /* ── التقارير ──────────────────────────────────────────────────── */
        /* FINAL_CLOSE ⑰: MNT-17 كان مؤجلا بقرار W7-D-01 «خارج خريطة المراسي»
           — وقد حسمه كون الاهداف مطابقة تامة بالاسم المطبع الى SCR-0663
           (Maintenance/mnt_kpis.php) بشاهده، فالمرساة تثبت من القرص كاخواتها */
        'MNT-17' => array('route' => 'Maintenance/mnt_kpis.php', 'probe' => 'mnt_kpi_period', 'kind' => 'TABLE',
                          'why' => 'مؤشرات الصيانة الدورية — حسم المطابقة في كون الاهداف (SCR-0663) نسخ تاجيل W7-D-01'),
        'MNT-16' => array('route' => 'Maintenance/mnt_kpis.php', 'probe' => 'mnt_kpi_period', 'kind' => 'TABLE',
                          'why' => 'مؤشرات الصيانة الدورية — مشتقة من الاوامر والشهادات والتوقفات بلا ادخال'),

        /* ── 15 إدارة النقل والترحيل · اللوحة ──────────────────────────── */
        'TRP-01' => array('route' => 'Transport/transfer_dashboard.php', 'probe' => 'transfer_orders', 'kind' => 'TABLE',
                          'why' => 'لوحة النقل والترحيل — مؤشر × فترة قراءة حية مشتقة من الاوامر واحداث الرحلات'),
        /* ── دورةُ الترحيل ─────────────────────────────────────────────── */
        'TRP-02' => array('route' => 'Transport/transfer_requests.php', 'probe' => 'transfer_requests', 'kind' => 'TABLE',
                          'why' => 'طلب الترحيل — الجهة الطالبة تطلب والنقل يملك الامر والتنفيذ'),
        'TRP-04' => array('route' => 'Transport/transfer_order_form.php', 'probe' => 'transfer_orders', 'kind' => 'TABLE',
                          'why' => 'امر الترحيل — الشاشة الام؛ النقل يملك الامر ووسيلته وسائقه وهوية المعدة عند الاسطول'),
        'TRP-05' => array('route' => 'Transport/transfer_permits.php', 'probe' => 'transfer_permits', 'kind' => 'TABLE',
                          'why' => 'تصاريح المسار والحمولة — ولا مغادرة لحمولة استثنائية بتصريح منته Fail-Closed'),
        'TRP-06' => array('route' => 'Transport/transfer_origin_handover.php', 'probe' => 'trp_origin_handover', 'kind' => 'TABLE',
                          'why' => 'تجهيز المغادرة والتسليم الاصلي — لا مغادرة قبل اكتمال التجهيز وتقييم مخاطر المسار'),
        'TRP-07' => array('route' => 'Transport/transfer_trip_legs.php', 'probe' => 'trp_trip_leg', 'kind' => 'TABLE',
                          'why' => 'مراحل الرحلة — كل مرحلة سطر بمركبتها وسائقها ومسارها وتسليمها للتالية'),
        'TRP-08' => array('route' => 'Transport/transfer_in_transit.php', 'probe' => 'transfer_events', 'kind' => 'TABLE',
                          'why' => 'تتبع الرحلة واحداثها — حالة الامر تتقدم بالاحداث لا بالتعديل اليدوي'),
        'TRP-09' => array('route' => 'Transport/transfer_arrival.php', 'probe' => 'transfer_delivery_docs', 'kind' => 'TABLE',
                          'why' => 'محضر الاستلام وقراءة العداد — القراءة الزامية وتسجل في كرت المعدة بمصدر الاستلام'),
        'TRP-10' => array('route' => 'Transport/transfer_damage_claims.php', 'probe' => 'trp_damage_claim', 'kind' => 'TABLE',
                          'why' => 'مطالبات التلف والحوادث — التلف الموثق في المحضر او حادث الرحلة يفتح مطالبة بقاعدة المتحمل'),
        /* ── الإقفال والتكلفة ──────────────────────────────────────────── */
        'TRP-11' => array('route' => 'Transport/transfer_close_cost.php', 'probe' => 'transfer_cost_lines', 'kind' => 'TABLE',
                          'why' => 'بنود تكلفة الرحلة — سجل تابع؛ والقيد عند المالية والصرف عند الخزينة'),
        'TRP-12' => array('route' => 'Transport/transfer_closure.php', 'probe' => 'trp_closure', 'kind' => 'TABLE',
                          'why' => 'اقفال امر الترحيل — الحبة مفصولة عن البنود ولا اقفال قبل محضر الاستلام'),
        'TRP-13' => array('route' => 'Transport/transfer_orders_report.php', 'probe' => 'trp_kpi_period', 'kind' => 'TABLE',
                          'why' => 'تقرير اوامر الترحيل — مشتق من الاوامر ومحاضرها وتكاليفها بلا ادخال'),
    );
}

/**
 * إثباتُ المِرساةِ قياسًا: هل يمسُّ ملفُّ المسارِ المُعلَنِ ما أُعلن له؟
 * @return array [sid, owner, verdict, rule]
 */
function repair01_w7_prove_anchor(mysqli $c, $ROOT, array $a)
{
    if ($a['kind'] === 'GAP' || $a['route'] === '') {
        return array('sid' => '', 'owner' => '', 'verdict' => 'NOT_BUILT', 'rule' => 'W7_TARGET_GAP');
    }
    $rt = $c->real_escape_string($a['route']);
    $row = $c->query("SELECT screen_id, owner_code, on_disk FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { return array('sid' => '', 'owner' => '', 'verdict' => 'ROUTE_NOT_IN_REGISTRY', 'rule' => 'W7_ANCHOR_UNPROVEN'); }
    if ((int) $row['on_disk'] !== 1) {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'ROUTE_NOT_ON_DISK', 'rule' => 'W7_ANCHOR_UNPROVEN');
    }
    $path = $ROOT . '/' . $a['route'];
    $src = is_file($path) ? (string) file_get_contents($path) : '';
    if ($src === '') {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'FILE_UNREADABLE', 'rule' => 'W7_ANCHOR_UNPROVEN');
    }
    $p = preg_quote($a['probe'], '~'); $hit = false; $rule = '';
    if ($a['kind'] === 'TABLE') {
        $hit = (bool) (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . $p . '`?\b~i', $src)
                    || preg_match('~[\'"]' . $p . '[\'"]\s*[,\)]~', $src));
        $rule = 'W7_ROUTE_TOUCHES_TABLE';
    } elseif ($a['kind'] === 'CMP03') {
        $hit = strpos($src, "\$CANONICAL = '" . $a['probe'] . "'") !== false;
        $rule = 'W7_ROUTE_DECLARES_CANONICAL';
    } elseif ($a['kind'] === 'SERVICE') {
        $hit = strpos($src, $a['probe']) !== false;
        $rule = 'W7_ROUTE_REQUIRES_SERVICE';
    }
    return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                 'verdict' => $hit ? 'ANCHORED' : 'ANCHOR_PROBE_MISSED',
                 'rule' => $hit ? $rule : 'W7_ANCHOR_UNPROVEN');
}

/* ═══════════════════════════════════════════════════════════════════════════
   ③ أسطحُ النموِّ — تُبنى في هذه الموجةِ وتُختَم بها (RPR-PATCH-02)
   ═══════════════════════════════════════════════════════════════════════════ */
function repair01_w7_new_surfaces()
{
    return array(
        array('route' => 'Maintenance/breakdown_intake.php', 'ar' => 'استقبال البلاغات الفنية',
              'icon' => 'fa fa-bell', 'group' => 'دورة العطل', 'sort' => 4,
              'owner' => 'DEP-14', 'role' => 'مسؤول الصيانة', 'sibling' => 'Maintenance/orders.php',
              'req' => 'MNT-04', 'doc' => 'بلاغ فني مستلم',
              'next' => 'فتح امر عمل على البلاغ', 'cons' => 'الصيانة والمخاطر', 'fin' => 'لا'),
        array('route' => 'Maintenance/part_requests.php', 'ar' => 'طلبات صرف القطع',
              'icon' => 'fa fa-boxes-stacked', 'group' => 'دورة العطل', 'sort' => 10,
              'owner' => 'DEP-14', 'role' => 'مسؤول الصيانة', 'sibling' => 'Maintenance/orders.php',
              'req' => 'MNT-10', 'doc' => 'طلب صرف قطع',
              'next' => 'صرف المخازن بسنده', 'cons' => 'المخازن والمالية', 'fin' => 'نعم'),
        array('route' => 'Maintenance/external_repairs.php', 'ar' => 'الإصلاح الخارجي ومطالبات الضمان',
              'icon' => 'fa fa-screwdriver-wrench', 'group' => 'دورة العطل', 'sort' => 11,
              'owner' => 'DEP-14', 'role' => 'مسؤول الصيانة', 'sibling' => 'Maintenance/orders.php',
              'req' => 'MNT-11', 'doc' => 'امر احالة خارجية او مطالبة ضمان',
              'next' => 'استلام العمل الخارجي', 'cons' => 'الموردون والمالية', 'fin' => 'نعم'),
        array('route' => 'Maintenance/daily_care.php', 'ar' => 'العناية اليومية والتشحيم',
              'icon' => 'fa fa-oil-can', 'group' => 'الوقائية', 'sort' => 13,
              'owner' => 'DEP-14', 'role' => 'مسؤول الصيانة', 'sibling' => 'Maintenance/preventive_plans.php',
              'req' => 'MNT-13', 'doc' => 'سطر عناية يومية منفذ',
              'next' => 'بلاغ متفرع عند نتيجة غير طبيعية', 'cons' => 'الصيانة والتشغيل', 'fin' => 'لا'),
        array('route' => 'Maintenance/repeat_repairs.php', 'ar' => 'سجل إعادة الإصلاح',
              'icon' => 'fa fa-rotate-left', 'group' => 'الإقفال والجاهزية', 'sort' => 15,
              'owner' => 'DEP-14', 'role' => 'مسؤول الصيانة', 'sibling' => 'Maintenance/orders.php',
              'req' => 'MNT-15', 'doc' => 'ملف تحليل السبب الجذري',
              'next' => 'قرار اغلاق التحليل', 'cons' => 'الصيانة والمخاطر والموردون', 'fin' => 'لا'),
        array('route' => 'Maintenance/mnt_kpis.php', 'ar' => 'مؤشرات الصيانة الدورية',
              'icon' => 'fa fa-chart-line', 'group' => 'التقارير', 'sort' => 16,
              'owner' => 'DEP-14', 'role' => 'مسؤول الصيانة', 'sibling' => 'Maintenance/dashboard_mnt.php',
              'req' => 'MNT-16', 'doc' => 'سطر مؤشر مشتق',
              'next' => 'قراءة القيادة والمخاطر', 'cons' => 'القيادة والمخاطر', 'fin' => 'لا'),
        array('route' => 'Transport/transfer_origin_handover.php', 'ar' => 'تجهيز المغادرة والتسليم الأصلي',
              'icon' => 'fa fa-truck-ramp-box', 'group' => 'دورة الترحيل', 'sort' => 6,
              'owner' => 'DEP-15', 'role' => 'مسؤول النقل والترحيل', 'sibling' => 'Transport/transfer_orders_list.php',
              'req' => 'TRP-06', 'doc' => 'محضر تسليم اصلي واذن مغادرة',
              'next' => 'بدء المرحلة الاولى', 'cons' => 'النقل والسلامة', 'fin' => 'لا'),
        array('route' => 'Transport/transfer_trip_legs.php', 'ar' => 'مراحل الرحلة',
              'icon' => 'fa fa-route', 'group' => 'دورة الترحيل', 'sort' => 7,
              'owner' => 'DEP-15', 'role' => 'مسؤول النقل والترحيل', 'sibling' => 'Transport/transfer_in_transit.php',
              'req' => 'TRP-07', 'doc' => 'سطر مرحلة بمبدئها ومنتهاها',
              'next' => 'تسليم المرحلة للتالية', 'cons' => 'النقل والتشغيل', 'fin' => 'لا'),
        array('route' => 'Transport/transfer_damage_claims.php', 'ar' => 'مطالبات التلف والحوادث',
              'icon' => 'fa fa-triangle-exclamation', 'group' => 'دورة الترحيل', 'sort' => 10,
              'owner' => 'DEP-15', 'role' => 'مسؤول النقل والترحيل', 'sibling' => 'Transport/transfer_arrival.php',
              'req' => 'TRP-10', 'doc' => 'ملف مطالبة تلف',
              'next' => 'قرار التسوية', 'cons' => 'المالية والموردون والمخاطر', 'fin' => 'نعم'),
        array('route' => 'Transport/transfer_closure.php', 'ar' => 'إقفال أمر الترحيل',
              'icon' => 'fa fa-file-circle-check', 'group' => 'الإقفال والتكلفة', 'sort' => 12,
              'owner' => 'DEP-15', 'role' => 'مسؤول النقل والترحيل', 'sibling' => 'Transport/transfer_close_cost.php',
              'req' => 'TRP-12', 'doc' => 'محضر اقفال امر الترحيل',
              'next' => 'احالة التكلفة للمالية', 'cons' => 'المالية والخزينة', 'fin' => 'نعم'),
        array('route' => 'Transport/transfer_orders_report.php', 'ar' => 'تقرير أوامر الترحيل',
              'icon' => 'fa fa-file-lines', 'group' => 'الإقفال والتكلفة', 'sort' => 13,
              'owner' => 'DEP-15', 'role' => 'مسؤول النقل والترحيل', 'sibling' => 'Transport/transfer_dashboard.php',
              'req' => 'TRP-13', 'doc' => 'سطر تقرير فترة',
              'next' => 'قراءة القيادة والمالية', 'cons' => 'القيادة والمالية', 'fin' => 'لا'),
    );
}

/* ═══════════════════════════════════════════════════════════════════════════
   ④ مقاييسُ الدورةِ — تُعاد من الحيِّ ولا تُقرأ من عمودٍ مخزَّن
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * أوامرُ العملِ التي **يوجب تصنيفُها شهادةَ عودة** — والمقياسُ من المحورِ ②
 * وحدَه (‏DEC-OPEN-12 ①): `major` و`safety_critical`. و`minor` **لا شهادةَ له**.
 * @return array `order_id => [severity, closed, cert_id, cert_state]`
 */
function repair01_w7_cert_duty(mysqli $c)
{
    $out = array();
    if (!repair01_w7_col_exists($c, 'mnt_order', 'safety_severity')) { return $out; }
    $r = $c->query("SELECT o.id, o.company_id, o.equipment_id, o.safety_severity, o.state,
                           o.lockout_state, o.w7_cert_id,
                           (SELECT COUNT(*) FROM mnt_return_cert x WHERE x.order_id = o.id) certs,
                           (SELECT COUNT(*) FROM mnt_return_cert x WHERE x.order_id = o.id AND x.state = 'approved') ok
                      FROM mnt_order o
                     WHERE o.safety_severity IN ('major','safety_critical') AND o.is_deleted = 0");
    while ($r && $x = $r->fetch_assoc()) {
        $out[(int) $x['id']] = array(
            'company' => (int) $x['company_id'], 'equipment' => (int) $x['equipment_id'],
            'severity' => (string) $x['safety_severity'], 'state' => (string) $x['state'],
            'lockout' => (string) $x['lockout_state'], 'cert_id' => (int) $x['w7_cert_id'],
            'certs' => (int) $x['certs'], 'approved' => (int) $x['ok'],
        );
    }
    return $out;
}

/**
 * صلاحيةُ الشهادةِ **مُعادةُ الحساب** من تاريخِ الإنجازِ ونافذةِ السجلّ —
 * ثمّ تُقارَن بالمخزَّن. عمودٌ يُكتب مرّةً ثمّ تتغيّر العتبةُ يصير كاذبًا صامتًا.
 * @return array `cert_id => [stored, measured, severity]`
 */
function repair01_w7_cert_validity(mysqli $c)
{
    $out = array();
    if (!repair01_w7_table_exists($c, 'mnt_return_cert')) { return $out; }
    $th = repair01_w7_thresholds($c);
    $r = $c->query("SELECT id, safety_severity, tech_complete_date, valid_days, valid_until, state
                      FROM mnt_return_cert WHERE state = 'approved'");
    while ($r && $x = $r->fetch_assoc()) {
        $key = ((string) $x['safety_severity'] === 'safety_critical')
             ? 'W7_CERT_VALID_DAYS_SAFETY' : 'W7_CERT_VALID_DAYS_MAJOR';
        $days = isset($th[$key]) ? (int) $th[$key]['value'] : null;
        $meas = ($days !== null && (string) $x['tech_complete_date'] !== '')
              ? date('Y-m-d', strtotime((string) $x['tech_complete_date']) + $days * 86400) : '';
        $out[(int) $x['id']] = array(
            'stored' => (string) $x['valid_until'], 'measured' => $meas,
            'stored_days' => (int) $x['valid_days'], 'measured_days' => $days,
            'severity' => (string) $x['safety_severity'],
        );
    }
    return $out;
}

/**
 * التكرارُ **مُعادُ الاشتقاق**: `within_validity` يُقارَن بيومِ الواقعةِ مقابلَ
 * صلاحيةِ شهادةِ الأمرِ الأصليّ — لا يُقرأ من العمود.
 * @return array `repeat_id => [stored, measured, days_stored, days_measured]`
 */
function repair01_w7_repeat_measure(mysqli $c)
{
    $out = array();
    if (!repair01_w7_table_exists($c, 'mnt_repeat_repair')) { return $out; }
    $r = $c->query("SELECT r.id, r.repeat_date, r.within_validity, r.days_since_cert,
                           c.valid_until, c.tech_complete_date
                      FROM mnt_repeat_repair r
                      LEFT JOIN mnt_return_cert c ON c.id = r.origin_cert_id");
    while ($r && $x = $r->fetch_assoc()) {
        $meas = 0; $days = null;
        if ((string) $x['valid_until'] !== '' && $x['valid_until'] !== null) {
            $meas = ((string) $x['repeat_date'] <= (string) $x['valid_until']) ? 1 : 0;
            $days = (int) floor((strtotime((string) $x['repeat_date']) - strtotime((string) $x['tech_complete_date'])) / 86400);
        }
        $out[(int) $x['id']] = array(
            'stored' => (int) $x['within_validity'], 'measured' => $meas,
            'days_stored' => ($x['days_since_cert'] === null ? null : (int) $x['days_since_cert']),
            'days_measured' => $days,
        );
    }
    return $out;
}

/**
 * تكلفةُ الشهادةِ **مُعادةٌ** من عمالةِ الأمرِ وقطعِه وخارجيّه — ثمّ تُقارَن.
 * @return array `cert_id => [stored, measured]`
 */
function repair01_w7_cert_cost(mysqli $c)
{
    $out = array();
    if (!repair01_w7_table_exists($c, 'mnt_return_cert')) { return $out; }
    $r = $c->query("SELECT c.id, c.order_id, c.actual_cost,
        ROUND(COALESCE((SELECT SUM(l.cost) FROM mnt_order_labor l WHERE l.order_id = c.order_id),0)
            + COALESCE((SELECT SUM(p.subtotal) FROM mnt_order_part p WHERE p.order_id = c.order_id),0)
            + COALESCE((SELECT SUM(e.actual_cost) FROM mnt_external_repair e WHERE e.order_id = c.order_id),0), 2) m
        FROM mnt_return_cert c");
    while ($r && $x = $r->fetch_assoc()) {
        $out[(int) $x['id']] = array('stored' => (float) $x['actual_cost'], 'measured' => (float) $x['m']);
    }
    return $out;
}

/**
 * تكلفةُ الإقفالِ **مُعادةٌ** من بنودِ الأمر — والحبّةُ مفصولةٌ فالمجموعُ يُقاس.
 * @return array `closure_id => [stored, measured, lines_stored, lines_measured]`
 */
function repair01_w7_closure_cost(mysqli $c)
{
    $out = array();
    if (!repair01_w7_table_exists($c, 'trp_closure')) { return $out; }
    $r = $c->query("SELECT k.id, k.order_id, k.total_cost, k.cost_lines_count,
        COALESCE((SELECT ROUND(SUM(l.amount_usd),2) FROM transfer_cost_lines l
                   WHERE l.order_id = k.order_id AND l.is_deleted = 0),0) m,
        COALESCE((SELECT COUNT(*) FROM transfer_cost_lines l
                   WHERE l.order_id = k.order_id AND l.is_deleted = 0),0) n
        FROM trp_closure k");
    while ($r && $x = $r->fetch_assoc()) {
        $out[(int) $x['id']] = array('stored' => (float) $x['total_cost'], 'measured' => (float) $x['m'],
                                     'lines_stored' => (int) $x['cost_lines_count'], 'lines_measured' => (int) $x['n']);
    }
    return $out;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ أحداثُ النطاقِ — الكياناتُ التي تصدر عنها
   ═══════════════════════════════════════════════════════════════════════════ */

function repair01_w7_entity_types()
{
    return array('mnt_breakdown', 'mnt_order', 'mnt_return_cert', 'mnt_repeat_repair',
                 'mnt_part_request', 'mnt_external_repair',
                 'transfer_requests', 'transfer_orders', 'trp_closure', 'trp_damage_claim');
}

/** أحداثُ العمودِ الفقريِّ التي تصدر عن هذه المرحلة — عقدُها قبلَ أوّلِ إطلاق */
function repair01_w7_stage_events()
{
    return array(
        'mnt.breakdown.received',
        'mnt.order.opened',
        'mnt.order.locked_out',
        'mnt.order.severity_escalated',
        'mnt.parts.requested',
        'mnt.external.referred',
        'mnt.return_cert.issued',
        'mnt.return_cert.approved',
        'mnt.equipment.returned_to_service',
        'mnt.repeat_repair.opened',
        'trp.request.submitted',
        'trp.order.opened',
        'trp.order.departure_authorized',
        'trp.order.arrived',
        'trp.damage_claim.opened',
        'trp.order.closure_submitted',
        'trp.order.closed',
    );
}
