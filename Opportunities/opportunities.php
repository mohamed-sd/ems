<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once __DIR__ . '/../includes/excel_ui.php'; // ح-09 · أزرار Excel الموحّدة
include '../includes/permissions_helper.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// ══════════════════════════════════════════════════════════════════════════════
// K9-M0: بوابة العزل — كل استعلامات الشاشة عبرها حصريًا (ADR-02):
// العزل بالشركة والحذف الناعم مسؤولية البوابة، لا شروط company_id يدوية هنا.
// ══════════════════════════════════════════════════════════════════════════════
$gate = ems_tenant_db();

// ══════════════════════════════════════════════════════════════════════════════
// دوال مساعدة
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('opp_e')) {
    function opp_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('opp_redirect_with_msg')) {
    function opp_redirect_with_msg($msg)
    {
        header('Location: opportunities.php?msg=' . urlencode($msg));
        exit();
    }
}

if (!function_exists('opp_money')) {
    function opp_money($value)
    {
        return number_format((float) $value, 2);
    }
}

if (!function_exists('opp_money_by_cur')) {
    // [ح-9] عرض المبالغ لكل عملةٍ على حدة (لا جمعَ USD فوق SDG)
    function opp_money_by_cur($by_cur)
    {
        if (empty($by_cur)) { return '0'; }
        $parts = array();
        foreach ($by_cur as $cur => $amt) {
            $parts[] = opp_money($amt) . ' ' . opp_e($cur);
        }
        return implode('<br>', $parts);
    }
}

if (!function_exists('opp_build_requirements')) {
    // ══════════════════════════════════════════════════════════════════════════
    // المتطلبات المبدئية المُهيكلة — يبني JSON + نصًّا مشتقًّا من حقول POST المتوازية
    // (req_equip_type[]/req_equip_qty[]) + عددَي المشغّلين والموردين.
    // مبدأ عدم التلفيق: تسمية نوع المعدة تُؤخذ من كتالوج الخادم لا من العميل، وأي
    // نوعٍ غير معروفٍ أو كميةٍ غير موجبةٍ يُسقَط بصمت. الفراغُ التامُّ ⇒ json=null.
    //   $equipment_types: صفوف [id,type] من equipments_types (المصدر الموثوق).
    //   يُرجع: ['json' => ?string, 'summary' => string]
    // ══════════════════════════════════════════════════════════════════════════
    function opp_build_requirements($post, $equipment_types)
    {
        $label_by_id = array();
        foreach ($equipment_types as $t) {
            $label_by_id[intval($t['id'])] = $t['type'];
        }

        $equipment = array();
        $types = (isset($post['req_equip_type']) && is_array($post['req_equip_type'])) ? $post['req_equip_type'] : array();
        $qtys  = (isset($post['req_equip_qty'])  && is_array($post['req_equip_qty']))  ? $post['req_equip_qty']  : array();
        foreach ($types as $i => $tid) {
            $tid = intval($tid);
            $qty = isset($qtys[$i]) ? intval($qtys[$i]) : 0;
            if ($tid <= 0 || $qty <= 0 || !isset($label_by_id[$tid])) {
                continue; // نوعٌ مجهولٌ أو كميةٌ غير موجبة ⇒ يُسقَط
            }
            $equipment[] = array(
                'type_id'    => $tid,
                'type_label' => $label_by_id[$tid],
                'qty'        => $qty,
            );
        }

        $operators = isset($post['req_operators']) ? max(0, intval($post['req_operators'])) : 0;
        $suppliers = isset($post['req_suppliers']) ? max(0, intval($post['req_suppliers'])) : 0;

        if (empty($equipment) && $operators === 0 && $suppliers === 0) {
            return array('json' => null, 'summary' => '');
        }

        $json = json_encode(array(
            'equipment' => $equipment,
            'operators' => $operators,
            'suppliers' => $suppliers,
        ), JSON_UNESCAPED_UNICODE);

        // نصٌّ مشتقٌّ مقروء — يُبقي capacity_summary مفيدًا لكل ما يقرؤه (توافقٌ رجعيّ)
        $parts = array();
        if (!empty($equipment)) {
            $eq_parts = array();
            foreach ($equipment as $e) {
                $eq_parts[] = $e['qty'] . ' × ' . $e['type_label'];
            }
            $parts[] = implode('، ', $eq_parts);
        }
        if ($operators > 0) { $parts[] = 'مشغّلون: ' . $operators; }
        if ($suppliers > 0) { $parts[] = 'موردون: ' . $suppliers; }

        return array('json' => $json, 'summary' => implode(' · ', $parts));
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// التحقق من معرف الشركة (عزل الشركات)
// ══════════════════════════════════════════════════════════════════════════════
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// رمز CSRF — [ع-0أ] اعتماد الرمز المركزي بدل رمزٍ محلّيٍّ منفصل. نفس ازدواج
// clients/activities، لكن هذه الشاشة في CSRF_ENFORCE_PATHS (مُنفَّذة) ⇒ الازدواج
// كان يُحجب كلَّ حفظٍ عبر المتصفح بـ403 (منفجرٌ فعليًا لا كامنًا). توحيدُ القيمة
// يجعل حقلَي المُحقِن والفورم متطابقَين ⇒ الحارس يمرّ، والفحص المحلّي يبقى فعّالًا.
// ══════════════════════════════════════════════════════════════════════════════
$opp_csrf_token = generate_csrf_token();

// ══════════════════════════════════════════════════════════════════════════════
// القوائم الثابتة (مراحل المسار ونماذج الإيراد)
// ══════════════════════════════════════════════════════════════════════════════
$OPP_STAGES = array('جديدة', 'قيد الدراسة', 'مؤهلة', 'عرض مقدم', 'تفاوض', 'فوز', 'خسارة', 'مستبعدة');
$OPP_OPEN_STAGES   = array('جديدة', 'قيد الدراسة', 'مؤهلة', 'عرض مقدم', 'تفاوض');
$OPP_CLOSED_STAGES = array('فوز', 'خسارة', 'مستبعدة');
$OPP_REVENUE_MODELS = array(
    'hourly' => 'تأجير بالساعة',
    'ton'    => 'نقل بالطن',
    'meter'  => 'تخريم بالمتر',
    'mixed'  => 'مزيج',
);
$OPP_ATTRACT = array('منخفضة', 'متوسطة', 'عالية');
$OPP_FIT     = array('منخفض', 'متوسط', 'عالي');
$OPP_DECISION = array('متابعة', 'تعليق', 'استبعاد');
$OPP_SOURCES = array('سوق', 'إحالة', 'مناقصة', 'عميل قائم');
$OPP_CURRENCIES = array('USD', 'SDG');
// احتمال الفوز الإرشادي لكل مرحلة (§7.1)
$OPP_STAGE_PROB = array(
    'جديدة' => 10, 'قيد الدراسة' => 20, 'مؤهلة' => 35,
    'عرض مقدم' => 55, 'تفاوض' => 75, 'فوز' => 100, 'خسارة' => 0, 'مستبعدة' => 0,
);

// ══════════════════════════════════════════════════════════════════════════════
// كتالوج أنواع المعدات (managed · عام) عبر البوابة — مصدر القائمة المنسدلة
// للمتطلبات المبدئية (نمط Contracts/contracts.php:99 · value=id، label=type).
// يُحمَّل مبكرًا لأنه يخدم تحقُّق POST (تسمية النوع من الخادم) وعرضَ النموذج معًا.
// ══════════════════════════════════════════════════════════════════════════════
$opp_equipment_types = $gate->select('equipments_types', array(
    'columns'  => array('id', 'type'),
    'whereRaw' => "status = 'active'",
    'orderBy'  => 'type ASC',
));

// ══════════════════════════════════════════════════════════════════════════════
// توليد الكود المقترح التالي (OPP-NNNN) — للعرض فقط
// ══════════════════════════════════════════════════════════════════════════════
$next_opp_code = 'OPP-0001';
// عبر البوابة: الجلب المرشَّح ثم إيجاد الأقصى في PHP (تعبير CAST/SUBSTRING في
// ORDER BY خارج صرامة معرّفات البوابة — والصفوف قليلة بطبيعتها)
$code_rows = $gate->select('opportunities', array(
    'columns'  => array('opp_code'),
    'whereRaw' => "opp_code REGEXP '^OPP-[0-9]+$'",
));
$last_num = 0;
foreach ($code_rows as $code_row) {
    $n = intval(substr($code_row['opp_code'], 4));
    if ($n > $last_num) {
        $last_num = $n;
    }
}
if ($last_num > 0) {
    $next_opp_code = 'OPP-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// ══════════════════════════════════════════════════════════════════════════════
// صلاحيات المستخدم على وحدة الفرص
// ══════════════════════════════════════════════════════════════════════════════
$module_info = $gate->selectOne('modules', array(
    'columns' => array('id'),
    'where'   => array('code' => 'Opportunities/opportunities.php'),
));
$module_id = $module_info ? $module_info['id'] : null;

$can_view = false;
$can_add = false;
$can_edit = false;
$can_delete = false;
if ($module_id) {
    $perms = get_module_permissions($conn, $module_id);
    $can_view   = $perms['can_view'];
    $can_add    = $perms['can_add'];
    $can_edit   = $perms['can_edit'];
    $can_delete = $perms['can_delete'];
}
if (!$can_view) {
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض الفرص ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل فرصة عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['title'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($opp_csrf_token, $posted_csrf)) {
        opp_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $opp_id     = isset($_POST['opp_id']) ? intval($_POST['opp_id']) : 0;
    $is_editing = $opp_id > 0;

    if ($is_editing && !$can_edit) {
        opp_redirect_with_msg('لا توجد صلاحية تعديل الفرص ❌');
    } elseif (!$is_editing && !$can_add) {
        opp_redirect_with_msg('لا توجد صلاحية إضافة فرص جديدة ❌');
    }

    // كود الفرصة
    $opp_code_raw = isset($_POST['opp_code']) ? trim($_POST['opp_code']) : '';
    if ($opp_code_raw === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $opp_code_raw)) {
        opp_redirect_with_msg('كود الفرصة غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // التحقق من القوائم الثابتة
    $stage_raw = isset($_POST['stage']) ? trim($_POST['stage']) : 'جديدة';
    if (!in_array($stage_raw, $OPP_STAGES, true)) {
        opp_redirect_with_msg('مرحلة المسار غير صالحة ❌');
    }
    $revenue_model_raw = isset($_POST['revenue_model']) ? trim($_POST['revenue_model']) : '';
    if ($revenue_model_raw !== '' && !isset($OPP_REVENUE_MODELS[$revenue_model_raw])) {
        opp_redirect_with_msg('نموذج الإيراد غير صالح ❌');
    }
    $currency_raw = isset($_POST['currency']) ? trim($_POST['currency']) : 'USD';
    if (!in_array($currency_raw, $OPP_CURRENCIES, true)) {
        $currency_raw = 'USD';
    }
    $attractiveness_raw = isset($_POST['attractiveness']) ? trim($_POST['attractiveness']) : '';
    if ($attractiveness_raw !== '' && !in_array($attractiveness_raw, $OPP_ATTRACT, true)) {
        $attractiveness_raw = '';
    }
    $strategy_fit_raw = isset($_POST['strategy_fit']) ? trim($_POST['strategy_fit']) : '';
    if ($strategy_fit_raw !== '' && !in_array($strategy_fit_raw, $OPP_FIT, true)) {
        $strategy_fit_raw = '';
    }
    $study_decision_raw = isset($_POST['study_decision']) ? trim($_POST['study_decision']) : '';
    if ($study_decision_raw !== '' && !in_array($study_decision_raw, $OPP_DECISION, true)) {
        $study_decision_raw = '';
    }

    // ح-10 · المالُ لا يكون سالبًا. سمةُ min="0" في النموذج تُتجاوَز بطلبٍ مُركَّب،
    // وإيرادٌ متوقعٌ سالبٌ واحدٌ يقلب مجموعَ المسار في اللوحة والتقارير.
    $expected_revenue_raw = (isset($_POST['expected_revenue']) && $_POST['expected_revenue'] !== '')
        ? (float) $_POST['expected_revenue'] : 0;
    if ($expected_revenue_raw < 0) {
        opp_redirect_with_msg('الإيراد المتوقع لا يكون سالبًا ❌');
    }
    $funding_needed_raw = (isset($_POST['funding_needed']) && $_POST['funding_needed'] !== '')
        ? (float) $_POST['funding_needed'] : 0;
    if ($funding_needed_raw < 0) {
        opp_redirect_with_msg('التمويل المطلوب لا يكون سالبًا ❌');
    }

    // ح-10 · إقفالُ الفرصة يلزمه سببُه. الحقلان موجودان في النموذج فالنيّةُ معلنة —
    // وبلا إلزامٍ يضيع تعلُّمُ المسار: لماذا رَبِحنا ولماذا خَسِرنا.
    $win_reason_raw  = isset($_POST['win_reason']) ? trim($_POST['win_reason']) : '';
    $lost_reason_raw = isset($_POST['lost_reason']) ? trim($_POST['lost_reason']) : '';
    if ($stage_raw === 'فوز' && $win_reason_raw === '') {
        opp_redirect_with_msg('لا تُقفل الفرصة على «فوز» بلا سبب فوز ❌');
    }
    if (($stage_raw === 'خسارة' || $stage_raw === 'مستبعدة') && $lost_reason_raw === '') {
        opp_redirect_with_msg('لا تُقفل الفرصة على «' . $stage_raw . '» بلا سبب ❌');
    }

    // العميل المرتبط — البوابة تعزل بالشركة والحذف الناعم آليًا
    $client_id_in = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    if ($client_id_in > 0) {
        $client_row = $gate->selectOne('clients', array('columns' => array('id'), 'where' => array('id' => $client_id_in)));
        if ($client_row === null) {
            opp_redirect_with_msg('العميل المحدد غير موجود أو خارج نطاق شركتك ❌');
        }
    }

    // المتطلبات المبدئية المُهيكلة (§2.6) — JSON مصدرُ الحقيقة + نصٌّ مشتقٌّ للتوافق
    $opp_req = opp_build_requirements($_POST, $opp_equipment_types);

    // القيم خامًا — البوابة تحضّر (prepared) فلا هروب يدويًا ولا أجزاء SQL
    $close_date_raw = isset($_POST['expected_close_date']) ? trim($_POST['expected_close_date']) : '';
    $data = array(
        'opp_code'         => $opp_code_raw,
        'title'            => trim($_POST['title']),
        'client_id'        => $client_id_in > 0 ? $client_id_in : null,
        'source'           => isset($_POST['source']) ? trim($_POST['source']) : '',
        'sector_category'  => isset($_POST['sector_category']) ? trim($_POST['sector_category']) : '',
        'state_region'     => isset($_POST['state_region']) ? trim($_POST['state_region']) : '',
        'revenue_model'    => $revenue_model_raw !== '' ? $revenue_model_raw : null,
        'expected_revenue' => $expected_revenue_raw,
        'currency'         => $currency_raw,
        'probability'      => isset($_POST['probability']) && $_POST['probability'] !== ''
            ? max(0, min(100, (float) $_POST['probability']))
            : (isset($OPP_STAGE_PROB[$stage_raw]) ? $OPP_STAGE_PROB[$stage_raw] : 0),
        'stage'            => $stage_raw,
        'attractiveness'   => $attractiveness_raw !== '' ? $attractiveness_raw : null,
        'strategy_fit'     => $strategy_fit_raw !== '' ? $strategy_fit_raw : null,
        'capacity_summary' => $opp_req['summary'],
        'requirements_json' => $opp_req['json'],
        'funding_needed'   => $funding_needed_raw,
        'study_decision'   => $study_decision_raw !== '' ? $study_decision_raw : null,
        'expected_close_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $close_date_raw) ? $close_date_raw : null,
        'lost_reason'      => $lost_reason_raw,
        'win_reason'       => $win_reason_raw,
        'review_notes'     => isset($_POST['review_notes']) ? trim($_POST['review_notes']) : '',
        'notes'            => isset($_POST['notes']) ? trim($_POST['notes']) : '',
    );

    try {
        if ($is_editing) {
            // تحقق الملكية (البوابة تعزل — الفحص للرسالة الدقيقة نفسها)
            $owner = $gate->selectOne('opportunities', array('columns' => array('id'), 'where' => array('id' => $opp_id)));
            if ($owner === null) {
                opp_redirect_with_msg('لا يمكنك تعديل فرصة لا تتبع لشركتك ❌');
            }
            // منع تكرار الكود داخل الشركة
            $dup = $gate->count('opportunities', array(
                'where'    => array('opp_code' => $opp_code_raw),
                'whereRaw' => 'id != ?', 'params' => array($opp_id),
            ));
            if ($dup > 0) {
                opp_redirect_with_msg('كود الفرصة موجود مسبقاً داخل شركتك ❌');
            }
            $gate->update('opportunities', $data, array('id' => $opp_id, 'is_deleted' => 0));
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('opportunities', 'opportunities', $opp_id, null, ['opp_code' => $opp_code_raw, 'title' => trim($_POST['title'])]);
            }
            opp_redirect_with_msg('تم تعديل الفرصة بنجاح ✅');
        } else {
            $dup = $gate->count('opportunities', array('where' => array('opp_code' => $opp_code_raw)));
            if ($dup > 0) {
                opp_redirect_with_msg('كود الفرصة موجود مسبقاً داخل شركتك ❌');
            }
            $data['created_by'] = intval($_SESSION['user']['id']);
            // لا company_id هنا — البوابة تحقنه من هوية الجلسة حصريًا
            $new_id = $gate->insert('opportunities', $data);
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('opportunities', 'opportunities', $new_id, ['opp_code' => $opp_code_raw, 'title' => trim($_POST['title'])]);
            }
            opp_redirect_with_msg('تم إضافة الفرصة بنجاح ✅');
        }
    } catch (\App\Core\TenantGateException $e) {
        error_log('opportunities.php gate refused write: ' . $e->getMessage());
        opp_redirect_with_msg($is_editing ? 'حدث خطأ أثناء التعديل ❌' : 'حدث خطأ أثناء الإضافة ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

    if (!$can_delete) {
        opp_redirect_with_msg('لا توجد صلاحية حذف الفرص ❌');
    }
    if (empty($delete_csrf) || !hash_equals($opp_csrf_token, $delete_csrf)) {
        opp_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    $chk = $gate->selectOne('opportunities', array('columns' => array('id'), 'where' => array('id' => $delete_id)));
    if ($chk === null) {
        opp_redirect_with_msg('لا يمكنك حذف فرصة لا تتبع لشركتك ❌');
    }
    try {
        // حذفٌ ناعم حصري عبر البوابة (is_deleted/deleted_at/deleted_by من هوية الجلسة)
        $gate->softDelete('opportunities', $delete_id);
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('opportunities', 'opportunities', $delete_id);
        }
        opp_redirect_with_msg('تم حذف الفرصة بنجاح ✅');
    } catch (\App\Core\TenantGateException $e) {
        error_log('opportunities.php soft delete refused: ' . $e->getMessage());
        opp_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// قائمة العملاء (للقائمة المنسدلة) — ضمن نطاق الشركة
// ══════════════════════════════════════════════════════════════════════════════
$clients_options = array();
$clients_map = array();
foreach ($gate->select('clients', array(
    'columns' => array('id', 'client_code', 'client_name'),
    'orderBy' => 'client_name ASC',
)) as $cl) {
    $clients_options[] = $cl;
    $clients_map[intval($cl['id'])] = $cl['client_name'];
}

// ══════════════════════════════════════════════════════════════════════════════
// جلب الفرص + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_open = 0;
$stat_won = 0;
$stat_lost = 0;
$stat_excluded = 0;
$stat_qualified_plus = 0;
$pipeline_value = 0.0;    // قيمة المسار (المفتوحة)
$negotiation_value = 0.0; // قيمة تحت التفاوض
$won_value = 0.0;
$pipeline_by_cur = array();    // [ح-9] تجميعٌ لكل عملةٍ على حدة — لا جمعَ USD فوق SDG
$negotiation_by_cur = array(); // [ح-9] تجميعٌ لكل عملة

// ترطيبٌ ثنائي الخطوة عبر البوابة بدل JOIN (قرار خطة K9 §4): أسماء العملاء من
// $clients_map المبنية أعلاه (صفر استعلام إضافي)، وأسماء المنشئين بجلبٍ واحد.
$opp_rows = $gate->select('opportunities', array('orderBy' => 'id DESC'));

$creator_ids = array();
foreach ($opp_rows as $r) {
    $cid = intval($r['created_by']);
    if ($cid > 0) {
        $creator_ids[$cid] = true;
    }
}
$creators_map = array();
if (!empty($creator_ids)) {
    $ids_in = implode(',', array_map('intval', array_keys($creator_ids)));
    foreach ($gate->select('users', array(
        'columns' => array('id', 'name'),
        'whereRaw' => 'id IN (' . $ids_in . ')',
        'includeDeleted' => true, // كالسلوك القائم: LEFT JOIN بلا شرط حذفٍ على المنشئ
    )) as $u) {
        $creators_map[intval($u['id'])] = $u['name'];
    }
}

if ($opp_rows) {
    foreach ($opp_rows as $row) {
        $row_client_id = intval($row['client_id']);
        $row['client_name'] = isset($clients_map[$row_client_id]) ? $clients_map[$row_client_id] : null;
        $row_creator_id = intval($row['created_by']);
        $row['creator_name'] = isset($creators_map[$row_creator_id]) ? $creators_map[$row_creator_id] : null;
        $rows[] = $row;
        $stat_total++;
        $stg = trim($row['stage']);
        $rev = (float) $row['expected_revenue'];
        $opp_cur = (isset($row['currency']) && $row['currency'] !== '') ? $row['currency'] : '—';
        if (in_array($stg, $OPP_OPEN_STAGES, true)) {
            $stat_open++;
            $pipeline_value += $rev;
            $pipeline_by_cur[$opp_cur] = (isset($pipeline_by_cur[$opp_cur]) ? $pipeline_by_cur[$opp_cur] : 0.0) + $rev;
        }
        if (in_array($stg, array('مؤهلة', 'عرض مقدم', 'تفاوض'), true)) {
            $stat_qualified_plus++;
        }
        if ($stg === 'تفاوض') {
            $negotiation_value += $rev;
            $negotiation_by_cur[$opp_cur] = (isset($negotiation_by_cur[$opp_cur]) ? $negotiation_by_cur[$opp_cur] : 0.0) + $rev;
        }
        if ($stg === 'فوز') {
            $stat_won++;
            $won_value += $rev;
        }
        if ($stg === 'خسارة') {
            $stat_lost++;
        }
        if ($stg === 'مستبعدة') {
            $stat_excluded++;
        }
    }
}
$decided = $stat_won + $stat_lost;
$conversion_rate = $decided > 0 ? round(($stat_won / $decided) * 100, 1) : 0;

$page_title = "الفرص البيعية";
include("../inheader.php");
include('../insidebar.php');

// أدوات عرض المرحلة
function opp_stage_tone($stage)
{
    switch (trim($stage)) {
        case 'فوز':      return 'won';
        case 'خسارة':    return 'lost';
        case 'مستبعدة':  return 'excluded';
        case 'تفاوض':    return 'negotiation';
        case 'عرض مقدم': return 'quoted';
        case 'مؤهلة':    return 'qualified';
        case 'قيد الدراسة': return 'study';
        default:          return 'new';
    }
}
?>

<div class="main opp-main ems-unified-page-shell">

    <?php
    $header_title = 'الفرص البيعية';
    $header_icon = 'fas fa-filter';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'opp-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'opp-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    // ح-09 · نموذج + تصدير + استيراد (الإطار الموحّد)
    foreach (ems_excel_header_actions('opportunities', 'الفرص البيعية', $can_add) as $__xl) { $header_actions[] = $__xl; }
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo opp_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section opp-hidden" id="oppStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-filter"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي الفرص</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-folder-open"></i></div>
                <div class="stats-value"><?php echo $stat_open; ?></div>
                <div class="stats-title">الفرص المفتوحة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-sack-dollar"></i></div>
                <div class="stats-value"><?php echo opp_money_by_cur($pipeline_by_cur); ?></div>
                <div class="stats-title">قيمة المسار (لكل عملة)</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-handshake"></i></div>
                <div class="stats-value"><?php echo opp_money_by_cur($negotiation_by_cur); ?></div>
                <div class="stats-title">تحت التفاوض (لكل عملة)</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-star"></i></div>
                <div class="stats-value"><?php echo $stat_qualified_plus; ?></div>
                <div class="stats-title">مؤهّلة فأكثر</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-trophy"></i></div>
                <div class="stats-value"><?php echo $stat_won; ?></div>
                <div class="stats-title">فرص فائزة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-circle-xmark"></i></div>
                <div class="stats-value"><?php echo $stat_lost; ?></div>
                <div class="stats-title">فرص خاسرة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-percent"></i></div>
                <div class="stats-value"><?php echo $conversion_rate; ?>%</div>
                <div class="stats-title">معدل التحويل</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل فرصة -->
    <form id="oppForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة فرصة جديدة</span></h5>
        </div>
        <input type="hidden" name="opp_id" id="opp_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo opp_e($opp_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label><i class="fas fa-magic"></i> كود الفرصة المولد <i class="fas fa-info-circle opp-info-icon"></i></label>
                        <input type="text" id="generated_opp_code" class="generated-code-field" value="<?php echo opp_e($next_opp_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل كود الفرصة" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> كود الفرصة *</label>
                        <input type="text" name="opp_code" id="opp_code" placeholder="مثال: OPP-001" required pattern="[A-Za-z0-9_\-]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-lightbulb"></i> عنوان الفرصة *</label>
                        <input type="text" name="title" id="title" placeholder="وصف مختصر للفرصة" required />
                    </div>
                    <div>
                        <label><i class="fas fa-user-tie"></i> العميل المستهدف</label>
                        <select name="client_id" id="client_id">
                            <option value="">-- بدون / عميل محتمل --</option>
                            <?php foreach ($clients_options as $cl): ?>
                                <option value="<?php echo intval($cl['id']); ?>"><?php echo opp_e($cl['client_name']) . ' (' . opp_e($cl['client_code']) . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-signs-post"></i> مصدر الفرصة</label>
                        <select name="source" id="source">
                            <option value="">-- اختر المصدر --</option>
                            <?php foreach ($OPP_SOURCES as $s): ?>
                                <option value="<?php echo opp_e($s); ?>"><?php echo opp_e($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-layer-group"></i> مرحلة المسار *</label>
                        <select name="stage" id="stage" required>
                            <?php foreach ($OPP_STAGES as $s): ?>
                                <option value="<?php echo opp_e($s); ?>"><?php echo opp_e($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-coins"></i> نموذج الإيراد</label>
                        <select name="revenue_model" id="revenue_model">
                            <option value="">-- اختر النموذج --</option>
                            <?php foreach ($OPP_REVENUE_MODELS as $k => $v): ?>
                                <option value="<?php echo opp_e($k); ?>"><?php echo opp_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> تصنيف القطاع</label>
                        <select name="sector_category" id="sector_category">
                            <option value="">-- اختر التصنيف --</option>
                            <option value="تعدين">تعدين</option>
                            <option value="مقاولات">مقاولات</option>
                            <option value="نقل ومواصلات">نقل ومواصلات</option>
                            <option value="نفط وغاز">نفط وغاز</option>
                            <option value="بنية تحتية">بنية تحتية</option>
                            <option value="خدمات">خدمات</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-map-location-dot"></i> الولاية / الموقع</label>
                        <input type="text" name="state_region" id="state_region" placeholder="مثال: نهر النيل" />
                    </div>
                    <div>
                        <label><i class="fas fa-money-bill-wave"></i> القيمة التقديرية</label>
                        <input type="number" step="0.01" min="0" name="expected_revenue" id="expected_revenue" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-coins"></i> العملة</label>
                        <select name="currency" id="currency">
                            <option value="USD">دولار (USD)</option>
                            <option value="SDG">جنيه (SDG)</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-percent"></i> احتمال الفوز (%)</label>
                        <input type="number" step="0.1" min="0" max="100" name="probability" id="probability" placeholder="يُشتق من المرحلة إن تُرك فارغاً" />
                    </div>
                    <div>
                        <label><i class="fas fa-calendar-day"></i> تاريخ الإغلاق المتوقع</label>
                        <input type="date" name="expected_close_date" id="expected_close_date" />
                    </div>
                    <div>
                        <label><i class="fas fa-fire"></i> الجاذبية</label>
                        <select name="attractiveness" id="attractiveness">
                            <option value="">-- غير محددة --</option>
                            <?php foreach ($OPP_ATTRACT as $a): ?>
                                <option value="<?php echo opp_e($a); ?>"><?php echo opp_e($a); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-bullseye"></i> التوافق الاستراتيجي</label>
                        <select name="strategy_fit" id="strategy_fit">
                            <option value="">-- غير محدد --</option>
                            <?php foreach ($OPP_FIT as $f): ?>
                                <option value="<?php echo opp_e($f); ?>"><?php echo opp_e($f); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-clipboard-check"></i> قرار الدراسة</label>
                        <select name="study_decision" id="study_decision">
                            <option value="">-- لم يُتخذ --</option>
                            <?php foreach ($OPP_DECISION as $d): ?>
                                <option value="<?php echo opp_e($d); ?>"><?php echo opp_e($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-hand-holding-dollar"></i> الحاجة للتمويل</label>
                        <input type="number" step="0.01" min="0" name="funding_needed" id="funding_needed" placeholder="0.00" />
                    </div>
                    <div class="opp-col-full opp-req-block">
                        <label class="opp-req-title"><i class="fas fa-boxes-stacked"></i> المتطلبات المبدئية <span class="opp-req-hint">— قدّر ما تحتاجه هذه الفرصة لو فازت</span></label>
                        <div class="opp-req-panel">
                            <div class="opp-req-summary" aria-live="polite">
                                <div class="opp-req-sumcard">
                                    <span class="opp-req-sumicon"><i class="fas fa-truck-monster"></i></span>
                                    <span class="opp-req-sumnum" id="reqSumEquip">0</span>
                                    <span class="opp-req-sumlbl">معدات</span>
                                </div>
                                <div class="opp-req-sumcard">
                                    <span class="opp-req-sumicon"><i class="fas fa-user-gear"></i></span>
                                    <span class="opp-req-sumnum" id="reqSumOps">0</span>
                                    <span class="opp-req-sumlbl">مشغّلون</span>
                                </div>
                                <div class="opp-req-sumcard">
                                    <span class="opp-req-sumicon"><i class="fas fa-industry"></i></span>
                                    <span class="opp-req-sumnum" id="reqSumSupp">0</span>
                                    <span class="opp-req-sumlbl">موردون</span>
                                </div>
                            </div>

                            <div id="reqLegacyNote" class="opp-req-legacy opp-req-hidden">
                                <i class="fas fa-clock-rotate-left"></i> متطلبات قديمة (نصّ حرّ): <span id="reqLegacyText"></span>
                                <div class="opp-req-legacy-hint">أعد إدخالها بالحقول أدناه لتُحفظ بشكلٍ مُهيكل.</div>
                            </div>

                            <div class="opp-req-main">
                                <div class="opp-req-eqsec">
                                    <div class="opp-req-seclbl"><i class="fas fa-truck-monster"></i> المعدات المطلوبة (بالنوع)</div>
                                    <div id="reqEquipRows" class="opp-req-rows"></div>
                                    <div id="reqEquipEmpty" class="opp-req-empty">لم تُضف أنواع معدات بعد — اضغط «أضف نوع معدة».</div>
                                    <button type="button" id="reqAddEquip" class="opp-req-add"><i class="fas fa-plus"></i> أضف نوع معدة</button>
                                </div>

                                <div class="opp-req-counts">
                                    <div class="opp-req-seclbl"><i class="fas fa-users-gear"></i> الطاقم والموردون</div>
                                    <div class="opp-req-countgrid">
                                        <div class="opp-req-countfield">
                                            <label><i class="fas fa-user-gear"></i> عدد المشغّلين</label>
                                            <input type="number" min="0" step="1" name="req_operators" id="req_operators" placeholder="0" />
                                            <div class="opp-req-fieldhint" id="reqOpsHint">مقترح ≥ عدد المعدات</div>
                                        </div>
                                        <div class="opp-req-countfield">
                                            <label><i class="fas fa-industry"></i> عدد الموردين</label>
                                            <input type="number" min="0" step="1" name="req_suppliers" id="req_suppliers" placeholder="0" />
                                            <div class="opp-req-fieldhint">جهات تأجير/تمليك خارجية</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label><i class="fas fa-trophy"></i> سبب الفوز</label>
                        <input type="text" name="win_reason" id="win_reason" placeholder="عند الفوز" />
                    </div>
                    <div>
                        <label><i class="fas fa-circle-xmark"></i> سبب الخسارة</label>
                        <input type="text" name="lost_reason" id="lost_reason" placeholder="عند الخسارة" />
                    </div>
                    <div class="opp-col-full">
                        <label><i class="fas fa-clipboard-list"></i> ملاحظات المراجعة (بعد الحسم)</label>
                        <textarea name="review_notes" id="review_notes" rows="2" placeholder="خلاصة مراجعة ما بعد الفوز/الخسارة"></textarea>
                    </div>
                    <div class="opp-col-full">
                        <label><i class="fas fa-note-sticky"></i> ملاحظات عامة</label>
                        <textarea name="notes" id="notes" rows="2" placeholder="أي ملاحظات إضافية"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ الفرصة</span></button>
                    <button type="button" id="oppFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
                </div>
            </div>
        </div>
    </form>

    <div class="filter">
        <div class="filter-title">
            <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
            فلاتر البحث
        </div>
        <div class="filter-body">
            <div class="filter-field">
                <label><i class="fa fa-layer-group"></i> مرحلة المسار</label>
                <select id="filterStage" class="form-control">
                    <option value="">-- كل المراحل --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-industry"></i> تصنيف القطاع</label>
                <select id="filterSector" class="form-control">
                    <option value="">-- كل القطاعات --</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-ok"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-reset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table id="oppTable" class="display opp-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>عنوان الفرصة</th>
                            <th>العميل المحتمل</th>
                            <th>القطاع</th>
                            <th>المرحلة</th>
                            <th>القيمة التقديرية</th>
                            <th>احتمال الفوز</th>
                            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                            <th class="ems-fn-th" data-fn="1">رقم الفرصة</th>
                            <th class="ems-fn-th" data-fn="1">تاريخ الفتح</th>
                            <th class="ems-fn-th" data-fn="1">نوع الفرصة</th>
                            <th class="ems-fn-th" data-fn="1">الوصف</th>
                            <th class="ems-fn-th" data-fn="1">تاريخ القرار المتوقع</th>
                            <th class="ems-fn-th" data-fn="1">آخر نشاط</th>
                            <th class="ems-fn-th" data-fn="1">المسؤول</th>
                            <th class="ems-fn-th" data-fn="1">سبب الإسقاط</th>
                            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                            <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                            <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $tone = opp_stage_tone($row['stage']);
                            $client_name = $row['client_name'] !== null ? $row['client_name'] : '';
                            $rev_label = isset($OPP_REVENUE_MODELS[$row['revenue_model']]) ? $OPP_REVENUE_MODELS[$row['revenue_model']] : '';
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewOppBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo opp_e($row['opp_code']); ?>"
                                            data-title="<?php echo opp_e($row['title']); ?>"
                                            data-client="<?php echo opp_e($client_name); ?>"
                                            data-source="<?php echo opp_e($row['source']); ?>"
                                            data-sector="<?php echo opp_e($row['sector_category']); ?>"
                                            data-region="<?php echo opp_e($row['state_region']); ?>"
                                            data-revenue-model="<?php echo opp_e($rev_label); ?>"
                                            data-expected="<?php echo opp_e(opp_money($row['expected_revenue'])); ?>"
                                            data-currency="<?php echo opp_e($row['currency']); ?>"
                                            data-probability="<?php echo opp_e($row['probability']); ?>"
                                            data-stage="<?php echo opp_e($row['stage']); ?>"
                                            data-attractiveness="<?php echo opp_e($row['attractiveness']); ?>"
                                            data-fit="<?php echo opp_e($row['strategy_fit']); ?>"
                                            data-capacity="<?php echo opp_e($row['capacity_summary']); ?>"
                                            data-requirements="<?php echo opp_e($row['requirements_json']); ?>"
                                            data-funding="<?php echo opp_e(opp_money($row['funding_needed'])); ?>"
                                            data-decision="<?php echo opp_e($row['study_decision']); ?>"
                                            data-close="<?php echo opp_e($row['expected_close_date']); ?>"
                                            data-win="<?php echo opp_e($row['win_reason']); ?>"
                                            data-lost="<?php echo opp_e($row['lost_reason']); ?>"
                                            data-review="<?php echo opp_e($row['review_notes']); ?>"
                                            data-notes="<?php echo opp_e($row['notes']); ?>"
                                            data-created="<?php echo opp_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editOppBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo opp_e($row['opp_code']); ?>"
                                                data-title="<?php echo opp_e($row['title']); ?>"
                                                data-client-id="<?php echo intval($row['client_id']); ?>"
                                                data-source="<?php echo opp_e($row['source']); ?>"
                                                data-sector="<?php echo opp_e($row['sector_category']); ?>"
                                                data-region="<?php echo opp_e($row['state_region']); ?>"
                                                data-revenue-model="<?php echo opp_e($row['revenue_model']); ?>"
                                                data-expected="<?php echo opp_e($row['expected_revenue']); ?>"
                                                data-currency="<?php echo opp_e($row['currency']); ?>"
                                                data-probability="<?php echo opp_e($row['probability']); ?>"
                                                data-stage="<?php echo opp_e($row['stage']); ?>"
                                                data-attractiveness="<?php echo opp_e($row['attractiveness']); ?>"
                                                data-fit="<?php echo opp_e($row['strategy_fit']); ?>"
                                                data-capacity="<?php echo opp_e($row['capacity_summary']); ?>"
                                            data-requirements="<?php echo opp_e($row['requirements_json']); ?>"
                                                data-funding="<?php echo opp_e($row['funding_needed']); ?>"
                                                data-decision="<?php echo opp_e($row['study_decision']); ?>"
                                                data-close="<?php echo opp_e($row['expected_close_date']); ?>"
                                                data-win="<?php echo opp_e($row['win_reason']); ?>"
                                                data-lost="<?php echo opp_e($row['lost_reason']); ?>"
                                                data-review="<?php echo opp_e($row['review_notes']); ?>"
                                                data-notes="<?php echo opp_e($row['notes']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($opp_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذه الفرصة؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="opp-code-cell"><?php echo opp_e($row['opp_code']); ?></strong></td>
                                <td><?php echo opp_e($row['title']); ?></td>
                                <td><?php echo $client_name !== '' ? opp_e($client_name) : '<span class="opp-muted">—</span>'; ?></td>
                                <td><?php echo opp_e($row['sector_category']); ?></td>
                                <td><span class="opp-stage opp-stage-<?php echo $tone; ?>"><?php echo opp_e($row['stage']); ?></span></td>
                                <td class="opp-num"><?php echo opp_e(opp_money($row['expected_revenue'])) . ' ' . opp_e($row['currency']); ?></td>
                                <td class="opp-num"><?php echo opp_e(rtrim(rtrim($row['probability'], '0'), '.')); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function () {
        const oppTable = $('#oppTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            oppTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(5, '#filterStage');
        fillFilterOptions(4, '#filterSector');

        $('#filterStage').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            oppTable.column(5).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterSector').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            oppTable.column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const oppForm = $('#oppForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#oppFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#oppStatsSection');

    // ══ المتطلبات المبدئية المُهيكلة (§2.6): صفوف [نوع معدة + عدد] + عددا مشغّلين/موردين ══
    var OPP_EQUIP_TYPES = <?php echo json_encode(array_map(function ($t) { return array('id' => intval($t['id']), 'type' => $t['type']); }, $opp_equipment_types), JSON_UNESCAPED_UNICODE); ?>;

    function oppEscHtml(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function oppParseReq(v) {
        if (!v) return null;
        if (typeof v === 'object') return v;           // jQuery .data() قد يفكّ JSON تلقائيًا
        try { return JSON.parse(v); } catch (e) { return null; }
    }
    function oppEquipOptionsHtml(selectedId) {
        var html = '<option value="">— اختر النوع —</option>';
        for (var i = 0; i < OPP_EQUIP_TYPES.length; i++) {
            var t = OPP_EQUIP_TYPES[i];
            var sel = (String(t.id) === String(selectedId)) ? ' selected' : '';
            html += '<option value="' + t.id + '"' + sel + '>' + oppEscHtml(t.type) + '</option>';
        }
        return html;
    }
    function oppRecalcReq() {
        var totalEq = 0;
        $('#reqEquipRows .opp-req-row').each(function () {
            var t = $(this).find('.opp-req-type').val();
            var q = parseInt($(this).find('.opp-req-qty').val(), 10) || 0;
            if (t && q > 0) totalEq += q;
        });
        var ops = parseInt($('#req_operators').val(), 10) || 0;
        var sup = parseInt($('#req_suppliers').val(), 10) || 0;
        $('#reqSumEquip').text(totalEq);
        $('#reqSumOps').text(ops);
        $('#reqSumSupp').text(sup);
        var hasRows = $('#reqEquipRows .opp-req-row').length > 0;
        $('#reqEquipEmpty').toggleClass('opp-req-hidden', hasRows);   // class لا .hide() (ems-forms.css يهزم hide)
        $('#reqOpsHint').text(totalEq > 0 ? ('مقترح ≥ عدد المعدات (' + totalEq + ')') : 'مقترح ≥ عدد المعدات');
    }
    function oppAddEquipRow(typeId, qty) {
        var q = parseInt(qty, 10); if (!(q > 0)) q = 1;
        var $row = $('<div class="opp-req-row"></div>').html(
            '<select name="req_equip_type[]" class="opp-req-type">' + oppEquipOptionsHtml(typeId) + '</select>' +
            '<input type="number" name="req_equip_qty[]" class="opp-req-qty" min="1" step="1" value="' + q + '" />' +
            '<button type="button" class="opp-req-del" aria-label="حذف نوع المعدة"><i class="fas fa-trash-alt"></i></button>'
        );
        $('#reqEquipRows').append($row);
        if (window.EmsSelect) EmsSelect.init();   // enhance() يتخطّى المُحسَّن مسبقًا
        oppRecalcReq();
    }
    function oppReqReset() {
        $('#reqEquipRows').empty();
        $('#req_operators').val('');
        $('#req_suppliers').val('');
        $('#reqLegacyNote').addClass('opp-req-hidden');
        oppRecalcReq();
    }
    function oppReqPopulate(reqRaw, legacy) {
        oppReqReset();
        var r = oppParseReq(reqRaw);
        var hasStruct = !!(r && ((r.equipment && r.equipment.length) || parseInt(r.operators, 10) || parseInt(r.suppliers, 10)));
        var lt = (legacy == null ? '' : String(legacy)).trim();
        // نصٌّ قديمٌ غيرُ مُهاجَر (requirements_json فارغ + capacity_summary حرّ) — أظهره ليُعاد إدخاله
        if (!hasStruct && lt) { $('#reqLegacyText').text(lt); $('#reqLegacyNote').removeClass('opp-req-hidden'); }
        if (!r) return;
        var eq = r.equipment || [];
        for (var i = 0; i < eq.length; i++) { oppAddEquipRow(eq[i].type_id, eq[i].qty); }
        $('#req_operators').val((parseInt(r.operators, 10) || 0) ? r.operators : '');
        $('#req_suppliers').val((parseInt(r.suppliers, 10) || 0) ? r.suppliers : '');
        oppRecalcReq();
    }
    // عرضٌ مُهيكلٌ للمودال (شرائح إجمالية + جدول أنواع) — value بصيغة HTML موثوقة.
    // legacy: نصُّ capacity_summary القديم — يُعرَض كبديلٍ حين لا بنية مُهيكلة (توافق).
    function oppReqHtml(reqRaw, legacy) {
        var r = oppParseReq(reqRaw);
        var eq = (r && r.equipment) ? r.equipment : [];
        var totalEq = 0; for (var i = 0; i < eq.length; i++) { totalEq += parseInt(eq[i].qty, 10) || 0; }
        var ops = r ? (parseInt(r.operators, 10) || 0) : 0;
        var sup = r ? (parseInt(r.suppliers, 10) || 0) : 0;
        if (!totalEq && !ops && !sup) {
            var lt = (legacy == null ? '' : String(legacy)).trim();
            return lt ? ('<span class="opp-reqv-legacy">' + oppEscHtml(lt) + '</span>') : '—';
        }
        var html = '<div class="opp-reqv"><div class="opp-reqv-chips">' +
            '<span class="opp-reqv-chip"><i class="fas fa-truck-monster"></i> معدات: <b>' + totalEq + '</b></span>' +
            '<span class="opp-reqv-chip"><i class="fas fa-user-gear"></i> مشغّلون: <b>' + ops + '</b></span>' +
            '<span class="opp-reqv-chip"><i class="fas fa-industry"></i> موردون: <b>' + sup + '</b></span></div>';
        if (eq.length) {
            html += '<table class="opp-reqv-table"><thead><tr><th>نوع المعدة</th><th>العدد</th></tr></thead><tbody>';
            for (var j = 0; j < eq.length; j++) {
                html += '<tr><td>' + oppEscHtml(eq[j].type_label || ('#' + eq[j].type_id)) + '</td><td>' + (parseInt(eq[j].qty, 10) || 0) + '</td></tr>';
            }
            html += '</tbody></table>';
        }
        return html + '</div>';
    }

    $('#reqAddEquip').on('click', function () { oppAddEquipRow('', 1); });
    $('#reqEquipRows').on('click', '.opp-req-del', function () { $(this).closest('.opp-req-row').remove(); oppRecalcReq(); });
    $('#reqEquipRows').on('input change', '.opp-req-qty, .opp-req-type', oppRecalcReq);
    $('#req_operators, #req_suppliers').on('input change', oppRecalcReq);
    oppRecalcReq();

    function setAddMode() { formTitle.text('إضافة فرصة جديدة'); submitBtnText.text('حفظ الفرصة'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل الفرصة'); submitBtnText.text('تحديث الفرصة'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!oppForm.length) return; oppForm[0].reset(); $('#opp_id').val(''); oppReqReset(); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.opp-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(oppForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!oppForm.length) return;
        if (oppForm.is(':visible')) {
            oppForm.stop(true, true).slideUp(250, function () { oppForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            oppForm.addClass('allforms-visible').hide();
            oppForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!oppForm.length || !oppForm.is(':visible')) return;
        oppForm.stop(true, true).slideUp(250, function () { oppForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('opp-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('opp-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillOppForm(d) {
        $('#opp_id').val(d.id);
        $('#opp_code').val(d.code);
        $('#title').val(d.title);
        $('#client_id').val(d.clientId || '');
        $('#source').val(d.source || '');
        $('#sector_category').val(d.sector || '');
        $('#state_region').val(d.region || '');
        $('#revenue_model').val(d.revenueModel || '');
        $('#expected_revenue').val(d.expected || '');
        $('#currency').val(d.currency || 'USD');
        $('#probability').val(d.probability || '');
        $('#stage').val(d.stage || 'جديدة');
        $('#attractiveness').val(d.attractiveness || '');
        $('#strategy_fit').val(d.fit || '');
        oppReqPopulate(d.requirements, d.capacity);
        $('#funding_needed').val(d.funding || '');
        $('#study_decision').val(d.decision || '');
        $('#expected_close_date').val(d.close || '');
        $('#win_reason').val(d.win || '');
        $('#lost_reason').val(d.lost || '');
        $('#review_notes').val(d.review || '');
        $('#notes').val(d.notes || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!oppForm.is(':visible')) {
            oppForm.addClass('allforms-visible').hide();
            oppForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#oppForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editOppBtn', function () {
        fillOppForm({
            id: $(this).data('id'), code: $(this).data('code'), title: $(this).data('title'),
            clientId: $(this).data('client-id'), source: $(this).data('source'), sector: $(this).data('sector'),
            region: $(this).data('region'), revenueModel: $(this).data('revenue-model'), expected: $(this).data('expected'),
            currency: $(this).data('currency'), probability: $(this).data('probability'), stage: $(this).data('stage'),
            attractiveness: $(this).data('attractiveness'), fit: $(this).data('fit'), capacity: $(this).data('capacity'),
            requirements: $(this).attr('data-requirements'),
            funding: $(this).data('funding'), decision: $(this).data('decision'), close: $(this).data('close'),
            win: $(this).data('win'), lost: $(this).data('lost'), review: $(this).data('review'), notes: $(this).data('notes')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewOppBtn', function () {
        const d = $(this).data();
        const reqRaw = $(this).attr('data-requirements');   // JSON خام (لا تفكيك jQuery) — للعرض والتعديل
        const stage = String(d.stage || '');
        let tone = 'inactive';
        if (stage === 'فوز') tone = 'active';
        else if (stage === 'خسارة' || stage === 'مستبعدة') tone = 'inactive';
        else tone = 'pending';

        const fields = [
            { label: 'كود الفرصة', value: d.code, icon: 'fas fa-barcode' },
            { label: 'عنوان الفرصة', value: d.title, icon: 'fas fa-lightbulb', size: 'lg' },
            { label: 'العميل', value: d.client || '—', icon: 'fas fa-user-tie' },
            { label: 'المصدر', value: d.source || '—', icon: 'fas fa-signs-post' },
            { label: 'المرحلة', value: stage, icon: 'fas fa-layer-group', type: 'status', tone: tone },
            { label: 'نموذج الإيراد', value: d.revenueModel || '—', icon: 'fas fa-coins' },
            { label: 'القطاع', value: d.sector || '—', icon: 'fas fa-industry' },
            { label: 'الولاية/الموقع', value: d.region || '—', icon: 'fas fa-map-location-dot' },
            { label: 'القيمة التقديرية', value: (d.expected || '0.00') + ' ' + (d.currency || ''), icon: 'fas fa-money-bill-wave', size: 'lg' },
            { label: 'احتمال الفوز', value: (d.probability || 0) + '%', icon: 'fas fa-percent' },
            { label: 'تاريخ الإغلاق المتوقع', value: d.close || '—', icon: 'fas fa-calendar-day' },
            { label: 'الجاذبية', value: d.attractiveness || '—', icon: 'fas fa-fire' },
            { label: 'التوافق الاستراتيجي', value: d.fit || '—', icon: 'fas fa-bullseye' },
            { label: 'قرار الدراسة', value: d.decision || '—', icon: 'fas fa-clipboard-check' },
            { label: 'الحاجة للتمويل', value: d.funding || '0.00', icon: 'fas fa-hand-holding-dollar' },
            { label: 'المتطلبات المبدئية', value: oppReqHtml(reqRaw, d.capacity), icon: 'fas fa-boxes-stacked', size: 'full', type: 'html', html: true },
            { label: 'سبب الفوز', value: d.win || '—', icon: 'fas fa-trophy' },
            { label: 'سبب الخسارة', value: d.lost || '—', icon: 'fas fa-circle-xmark' },
            { label: 'ملاحظات المراجعة', value: d.review || '—', icon: 'fas fa-clipboard-list', size: 'lg' },
            { label: 'ملاحظات عامة', value: d.notes || '—', icon: 'fas fa-note-sticky', size: 'lg' },
            { label: 'أضيفت بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل الفرصة', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                fillOppForm({
                    id: d.id, code: d.code, title: d.title, clientId: '', source: d.source, sector: d.sector,
                    region: d.region, revenueModel: '', expected: (d.expected || '').replace(/,/g, ''), currency: d.currency,
                    probability: d.probability, stage: d.stage, attractiveness: d.attractiveness, fit: d.fit,
                    capacity: d.capacity, requirements: reqRaw, funding: (d.funding || '').replace(/,/g, ''), decision: d.decision, close: d.close,
                    win: d.win, lost: d.lost, review: d.review, notes: d.notes
                });
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل الفرصة', icon: 'fas fa-lightbulb', fields: fields, actions: actions });
    });
</script>

<style>
    .opp-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .opp-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .opp-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .opp-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .opp-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .opp-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .opp-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .opp-main .stats-grid { grid-template-columns: 1fr; } }

    .opp-main .opp-hidden { display: none; }
    /* .form-grid هنا flexbox (ems-forms.css) بـ5 أعمدة صلبة — التمدّد لصفٍّ كامل
       يكون بـ flex-basis:100% لا بـ grid-column (خاصية شبكةٍ لا أثر لها في flex). */
    .opp-main .opp-col-full { grid-column: 1 / -1; flex-basis: 100% !important; }
    .opp-main .table-container { overflow-x: auto; }
    #oppTable.opp-table-nowrap, #oppTable.opp-table-nowrap th, #oppTable.opp-table-nowrap td { white-space: nowrap; }
    #oppTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .opp-main .opp-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .opp-main .opp-muted { color: #999; }

    /* شارات المراحل */
    .opp-stage { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: .82rem; font-weight: 800; border: 1px solid transparent; }
    .opp-stage-new { background: rgba(59,130,246,.12); color: #1d4ed8; border-color: rgba(59,130,246,.28); }
    .opp-stage-study { background: rgba(139,92,246,.12); color: #6d28d9; border-color: rgba(139,92,246,.28); }
    .opp-stage-qualified { background: rgba(14,165,233,.12); color: #0369a1; border-color: rgba(14,165,233,.28); }
    .opp-stage-quoted { background: rgba(234,179,8,.15); color: #a16207; border-color: rgba(234,179,8,.30); }
    .opp-stage-negotiation { background: rgba(249,115,22,.14); color: #c2410c; border-color: rgba(249,115,22,.30); }
    .opp-stage-won { background: rgba(34,197,94,.15); color: #15803d; border-color: rgba(34,197,94,.30); }
    .opp-stage-lost { background: rgba(220,38,38,.12); color: #b91c1c; border-color: rgba(220,38,38,.28); }
    .opp-stage-excluded { background: rgba(107,114,128,.14); color: #4b5563; border-color: rgba(107,114,128,.30); }

    /* ── المتطلبات المبدئية المُهيكلة (بناء) ── */
    .opp-main .opp-req-title { display: block; margin-bottom: 8px; }
    .opp-main .opp-req-hint { font-weight: 400; font-size: .8rem; color: #8a8f98; }
    .opp-main .opp-req-panel { border: 1px solid var(--bdr, #D7DBE0); border-radius: var(--rl, 12px); background: var(--s2, #F2F3F5); padding: 14px; }
    .opp-main .opp-req-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px; }
    .opp-main .opp-req-sumcard { display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 12px; background: #fff; border: 1px solid var(--bdr, #D7DBE0); border-radius: 12px; padding: 14px 12px; }
    .opp-main .opp-req-sumcard .opp-req-sumicon { width: 40px; height: 40px; flex: 0 0 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; background: #FCF3D6; color: #C88A00; font-size: 1.1rem; }
    .opp-main .opp-req-sumnum { font-size: 30px; font-weight: 900; line-height: 1; color: #1a1f28; font-variant-numeric: tabular-nums; min-width: 34px; text-align: center; }
    .opp-main .opp-req-sumlbl { font-size: .9rem; font-weight: 700; color: #5f5e5a; }
    .opp-main .opp-req-seclbl { font-size: .85rem; font-weight: 700; color: #444; margin-bottom: 8px; }
    .opp-main .opp-req-seclbl i { color: #E0AE2E; }
    .opp-main .opp-req-rows { display: flex; flex-direction: column; gap: 8px; }
    .opp-main .opp-req-row { display: flex; gap: 8px; align-items: center; max-width: 480px; }
    .opp-main .opp-req-row .opp-req-type, .opp-main .opp-req-row .emsf-select-wrap { flex: 1 1 auto; min-width: 0; margin: 0; }
    .opp-main .opp-req-row .opp-req-qty { flex: 0 0 90px; width: 90px; margin: 0; text-align: center; }
    .opp-main .opp-req-del { flex: 0 0 auto; width: 38px; height: 38px; border: 1px solid #e6b8b8; background: #fff; color: #c0392b; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .opp-main .opp-req-del:hover { background: #fdecea; }
    .opp-main .opp-req-add { margin-top: 10px; background: #fff; border: 1px dashed #E0AE2E; color: #946A00; border-radius: 10px; padding: 8px 14px; font-weight: 700; cursor: pointer; font-size: .85rem; }
    .opp-main .opp-req-add:hover { background: #FCF3D6; }
    .opp-main .opp-req-empty { font-size: .82rem; color: #8a8f98; padding: 6px 2px; }
    .opp-main .opp-req-hidden { display: none !important; }
    /* المنطقة الرئيسة: عمودان (المعدات | الطاقم والموردون) لاستغلال العرض بتوازن */
    .opp-main .opp-req-main { display: flex; gap: 20px; align-items: stretch; margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--bdr, #D7DBE0); }
    .opp-main .opp-req-eqsec { flex: 1.35 1 0; min-width: 0; }
    .opp-main .opp-req-counts { flex: 1 1 0; min-width: 0; border-inline-start: 1px dashed var(--bdr, #D7DBE0); padding-inline-start: 20px; }
    .opp-main .opp-req-countgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .opp-main .opp-req-countfield label { display: inline-flex; align-items: center; gap: 5px; font-size: .8rem; font-weight: 700; color: #5f5e5a; margin-bottom: 5px; }
    .opp-main .opp-req-countfield label i { color: #E0AE2E; }
    .opp-main .opp-req-countfield input { width: 100%; text-align: center; }
    .opp-main .opp-req-fieldhint { font-size: .72rem; color: #8a8f98; margin-top: 4px; }
    @media (max-width: 900px) { .opp-main .opp-req-main { flex-direction: column; gap: 14px; } .opp-main .opp-req-counts { border-inline-start: 0; padding-inline-start: 0; border-top: 1px dashed var(--bdr, #D7DBE0); padding-top: 12px; } }
    .opp-main .opp-req-legacy { background: #FDF6E3; border: 1px dashed #E0AE2E; border-radius: 10px; padding: 8px 12px; margin-bottom: 12px; font-size: .84rem; color: #6b5200; font-weight: 700; }
    .opp-main .opp-req-legacy .opp-req-legacy-hint { font-weight: 400; font-size: .74rem; color: #8a7a3a; margin-top: 3px; }
    @media (max-width: 560px) { .opp-main .opp-req-summary { grid-template-columns: 1fr 1fr; } .opp-main .opp-req-counts { grid-template-columns: 1fr; } }

    /* ── عرض المتطلبات داخل مودال التفاصيل (خارج .opp-main — المودال في body) ── */
    .opp-reqv-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
    .opp-reqv-chip { display: inline-flex; align-items: center; gap: 5px; background: #FCF3D6; border: 1px solid #E9D08A; color: #6b5200; border-radius: 999px; padding: 4px 12px; font-size: .82rem; font-weight: 700; }
    .opp-reqv-chip b { font-weight: 900; }
    .opp-reqv-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .opp-reqv-table th, .opp-reqv-table td { border: 1px solid #e3e6ea; padding: 6px 10px; text-align: right; }
    .opp-reqv-table thead th { background: #f7f4ec; font-weight: 800; color: #5f5e5a; }
    .opp-reqv-legacy { color: #6b5200; font-style: italic; }
</style>

</body>

</html>

<?php
// ح-09 · نافذةُ معالج الاستيراد وأصولُ الإطار (مرة واحدة)
if (function_exists('ems_excel_render')) { ems_excel_render('opportunities'); }
