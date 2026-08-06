<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): IAM-005 · UXP-030 · UXP-036 · UXP-038 · UXP-072 · UXP-074 · UXP-077 · UXP-078 · UXP-079 · UXP-085 · UXP-093
/**
 * includes/gov_columns.php — طبقة الحوكمة المشتركة (CMP-03 الموجات ②+③+④)
 * ───────────────────────────────────────────────────────────────────────────
 * المصدر: CMP-03 ورقة 04 «طبقة الحوكمة المشتركة» — 25 عمودًا حاكمًا في ثلاث شرائح:
 *   ① الأعمدة السبعة الحاكمة (الكيان · المُنشئ · المعتمِد · التفويض · المرجع · التواريخ · الحالة)
 *   ② أعمدة الأثر والعكس (مفتاح التكرار · درجة الأثر · معكوس بـ · عكس عن · سجل الاطّلاع)
 *   ③ أعمدة المال والإثبات (العملة · سعر الصرف · مركز التكلفة · المرفق)
 *
 * البنية التحتية للقيم قائمة في النظام (بوابة العزل · سلسلة الاعتماد · fin_event_links ·
 * publishFact · action_execution_log) — والربط الصفّي لكل شاشة مهامُ لحاقٍ مسجلة في
 * docs/CMP03_FOLLOWUP_SOURCES_ar.md؛ فالعمود بلا مصدرٍ بعدُ يظهر بمكانه «—»
 * (توصية المالك ③ المعتمدة).
 *
 * العرض: رؤوس <th data-gov="…"> تُحقن حرفيًّا في الشاشات (tools/cmp03_apply.php)،
 * وخلايا الصفوف يحشوها ui-unification.js (padGovernanceCells) قبل تهيئة DataTables
 * بقيم السياق العام هنا أو «—». الفائض فوق 22 عمودًا يحمل class="none" فينهار
 * لسطرٍ تابعٍ عبر DataTables Responsive (توصية المالك ① المعتمدة).
 */

if (!function_exists('ems_gov_registry')) {

/** سجل الأعمدة الحاكمة: مفتاح ← [التسمية الحرفية من المستند · الشريحة · لماذا هو حاكم] */
function ems_gov_registry() {
    return array(
        'entity'             => array('الكيان',                    1, 'عزل الشركات — لا صفَّ بلا كيانٍ مالك'),
        'authority_ref'      => array('مرجع التفويض',              1, 'سند صلاحية المعتمِد — تفويض أو سلطة أصلية'),
        'approved_at'        => array('تاريخ الاعتماد',            1, 'لحظة الاعتماد — وبها يقاس زمن الدورة'),
        'created_at'         => array('تاريخ الإنشاء',             1, 'لحظة الإنشاء بالتاريخ والوقت'),
        'parent_ref'         => array('المرجع الأب',               1, 'المستند الذي تولد عنه — خيط التتبع'),
        'creator'            => array('المُنشئ — الاسم والصفة',    1, 'من أنشأ المستند وبأي صفة — لا اسم مجرد'),
        'approver'           => array('المعتمِد — الاسم والصفة',   1, 'من اعتمده وبأي صفة'),
        'status'             => array('الحالة',                    1, 'حالة المستند في دورته'),
        'required_approver'  => array('المعتمِد المطلوب',          1, 'من يلزم اعتماده بحسب سلسلة الاعتماد'),
        'creating_entity'    => array('الجهة المُنشئة',            1, 'الجهة التي أنشأت المستند'),
        'idem_key'           => array('مفتاح منع التكرار',         2, 'يمنع وقوع الأثر مرتين بمفتاح مركب'),
        'reversed_by'        => array('معكوس بـ',                  2, 'مرجع الحركة التي عكسته'),
        'reversal_of'        => array('عكس عن',                    2, 'مرجع الحركة التي عكسها'),
        'impact_grade'       => array('درجة الأثر',                2, 'مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا'),
        'view_log'           => array('سجل الاطّلاع',              2, 'من قرأ البيان الحساس ومتى'),
        'attachment'         => array('المرفق',                    3, 'مستند الإثبات الخارجي'),
        'cost_center'        => array('مركز التكلفة',              3, 'وجهة التحميل'),
        'fx_rate_source'     => array('سعر الصرف ومصدره',          3, 'ما خالف عملة الدفاتر يحمل السعر ومصدره'),
        'currency'           => array('العملة',                    3, 'لا مبلغ بلا عملة'),
        'fx_rate'            => array('سعر الصرف',                 3, 'سعر التحويل لعملة الدفاتر'),
        'attached_doc'       => array('المستند المرفق',            3, 'مستند الإثبات المرفق'),
        'attachments'        => array('المرفقات',                  3, 'مرفقات الإثبات'),
        'loaded_cost_center' => array('مركز التكلفة المحمَّل',     3, 'المركز الذي حُمّلت عليه التكلفة'),
        'base_currency'      => array('العملة الأساسية',           3, 'عملة دفاتر الكيان'),
        'fx_source'          => array('مصدر سعر الصرف',            3, 'مصدر السعر المعتمد'),
    );
}

/** تسمية المستند ← المفتاح (للمطبّق والفحوص) */
function ems_gov_key_by_label($label) {
    static $idx = null;
    if ($idx === null) {
        $idx = array();
        foreach (ems_gov_registry() as $k => $def) { $idx[$def[0]] = $k; }
    }
    return isset($idx[$label]) ? $idx[$label] : null;
}

/** سياق القيم العامة (ما يصح تعميمه على كل صفوف شاشةٍ محكومةٍ بعزل الكيان) */
function ems_gov_ctx() {
    static $ctx = null;
    if ($ctx !== null) { return $ctx; }
    $ctx = array('values' => array());
    $conn = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
    $cid = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;
    if ($conn && $cid > 0) {
        // الكيان والعملة الأساسية عامّان بعزل المستأجر — سائر القيم صفّية فتبقى «—»
        // حتى يُربط مصدرها الصفّي (docs/CMP03_FOLLOWUP_SOURCES_ar.md).
        $r = mysqli_query($conn, "SELECT company_name, currency FROM admin_companies WHERE id = " . $cid . " LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r))) {
            if (!empty($row['company_name'])) { $ctx['values']['entity'] = $row['company_name']; }
            if (!empty($row['currency']))     { $ctx['values']['base_currency'] = $row['currency']; }
        }
    }
    return $ctx;
}

/** بث سياق الحوكمة للواجهة — مرةً واحدةً لكل صفحة (يستدعيه insidebar/inheader) */
function ems_gov_emit_assets() {
    if (!empty($GLOBALS['__ems_gov_emitted'])) { return; }
    $GLOBALS['__ems_gov_emitted'] = true;
    $ctx = ems_gov_ctx();
    echo "\n<script>window.emsGovCtx = " .
        json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) .
        ";</script>\n";
}

}
