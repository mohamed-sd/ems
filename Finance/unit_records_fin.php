<?php
/**
 * Finance/unit_records_fin.php — كشف الوحدات التشغيلية اليومي والتطابق الثلاثي (§3.23/§7.1/§12).
 * الكميات الثلاث (تشغيل/عميل/مورد) — لا اعتماد قبل تطابقها. قاعدة السبب: لا توقف بلا سبب.
 * «اعتماد التطابق» يولّد الحدثين التوأمين: إيراد العميل (fin_financial_events) +
 * مستحق المورد (fin_dues) — بالوحدة نفسها والكمية نفسها. هامش الوحدة عمود مولّد.
 * شاشة مستقلة — عزل شركة + حذف ناعم + CSRF + فصل واجبات (الاعتماد للمدير المالي).
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/unit_records_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+كشف+الوحدات+❌"); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$work_models = fin_work_models(); $match_states = fin_match_states(); $downtime_causes = fin_downtime_causes();

// ═══════════════════════════════════════════════════════════════════════════
// D02 §5/§6: التحويل المالي — لحظةُ صيرورة يوم الدوام استحقاقًا ماليًّا
// ───────────────────────────────────────────────────────────────────────────
// «اعتماد المالية لا يعني إنشاء الوحدة؛ بل تحويلَها إلى أثرٍ مالي» — فالمروحة
// تُنشأ هنا حصرًا حين تكون البوابة نافذة. الحارس صلاحيةُ التعديل على هذه
// الشاشة وحدها (تُضبط من شاشة الصلاحيات لأي دور) — لا دورَ محظوظٌ في الكود.
// دفعةٌ واحدة: سجلٌّ أو مجموعةٌ أو الكل، بنمط شاشة الاعتمادات نفسه.
// ═══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'convert_units') {
    if (!$can_edit) { header("Location: unit_records_fin.php?msg=" . rawurlencode('لا تملك صلاحية التحويل المالي') . "+❌"); exit(); }
    if (!fin_verify_action_token()) { header("Location: unit_records_fin.php?msg=رمز+الحماية+غير+صالح+❌"); exit(); }

    $ids = array();
    foreach (explode(',', (string) ($_POST['ids'] ?? '')) as $one) {
        $one = intval(trim($one));
        if ($one > 0) { $ids[] = $one; }
    }
    $ids = array_slice(array_unique($ids), 0, 500); // سقفٌ معلَنٌ للدفعة الواحدة
    if (!$ids) { header("Location: unit_records_fin.php?msg=لم+تحدّد+أي+يوم+للتحويل+❌"); exit(); }

    require_once __DIR__ . '/../app/Services/EffectFanout.php';
    $okCount = 0; $effCount = 0; $failed = array();
    foreach ($ids as $tsId) {
        // ① أهليةٌ خادمية: اليوم ضمن نطاق الشركة ومكتمل الاعتماد وغير محوَّل —
        //    لا يُحوَّل يومٌ بمجرد إرسال معرّفه (الطابور عرضٌ لا تفويض).
        $eligible = fin_conversion_queue($conn, $is_super_admin, array('limit' => 1, 'only_id' => $tsId));
        if (!$eligible) { $failed[] = 'TS-' . $tsId . ': غير مؤهّلٍ للتحويل (غير مكتمل الاعتماد أو محوَّلٌ سلفًا)'; continue; }
        try {
            $res = null;
            ems_tenant_db()->runInTransaction(function ($g) use (&$res, $conn, $tsId, $current_user_id) {
                $res = \App\Services\EffectFanout::forTimesheetId($conn, $g, $tsId, $current_user_id);
            }, 'finance unit conversion ' . $tsId);
            $n = $res ? count($res['effects']) : 0;
            if ($n > 0) { $okCount++; $effCount += $n; }
            else {
                $why = ($res && !empty($res['skipped'])) ? $res['skipped'][0]['reason'] : 'لا أثرَ قابلٌ للتوليد';
                $failed[] = 'TS-' . $tsId . ': ' . $why;
            }
        } catch (\Throwable $e) {
            error_log('unit conversion ' . $tsId . ': ' . $e->getMessage());
            $failed[] = 'TS-' . $tsId . ': ' . $e->getMessage();
        }
    }

    if ($okCount > 0) {
        fin_log_approval($conn, $company_id, 0, 'operational_approved', 'converted', 'advance', 'finance_conversion',
            $current_user_id, 'تحويلٌ ماليٌّ لـ' . $okCount . ' يومًا · ' . $effCount . ' أثرًا مولَّدًا');
        fin_notify($conn, $company_id, 'dept_accountant',
            'تحوّلت ' . $okCount . ' يومًا إلى استحقاقاتٍ مالية (' . $effCount . ' أثرًا) — الإيراد بانتظار رفعه', 'events_list_fin.php?fstate=draft');
    }
    $msg = 'حُوِّل ' . $okCount . ' يومًا (' . $effCount . ' أثرًا)';
    if ($failed) { $msg .= ' — تعذّر ' . count($failed) . ': ' . implode(' | ', array_slice($failed, 0, 3)); }
    header("Location: unit_records_fin.php?msg=" . rawurlencode($msg) . ($okCount > 0 ? '+✅' : '+⚠️')); exit();
}

// ── اعتماد التطابق وتوليد الحدثين التوأمين (المدير المالي + CSRF) ──
if (isset($_GET['approve_id'])) {
    if (!$can_edit) { header("Location: unit_records_fin.php?msg=لا+توجد+صلاحية+الاعتماد+❌"); exit(); }
    if (!fin_verify_action_token()) { header("Location: unit_records_fin.php?msg=رمز+الحماية+غير+صالح+❌"); exit(); }
    if (!fin_can_perform($conn, $ctx['role'], 'finance_manager')) { header("Location: unit_records_fin.php?msg=اعتماد+التطابق+يخصّ+المدير+المالي+فقط+❌"); exit(); }
    $rid = intval($_GET['approve_id']);
    $rec = ems_tenant_db()->selectOne('fin_unit_records', array('where' => array('id' => $rid)));
    if (!$rec) { header("Location: unit_records_fin.php?msg=السجل+غير+موجود+❌"); exit(); }
    // قاعدة التطابق الثلاثي: لا اعتماد إلا لسجل متطابق
    if ($rec['match_state'] !== 'matched') { header("Location: unit_records_fin.php?msg=لا+اعتماد:+الكميات+الثلاث+غير+متطابقة+❌"); exit(); }
    if ($rec['client_unit_price'] === null || $rec['supplier_unit_price'] === null) {
        header("Location: unit_records_fin.php?msg=لا+اعتماد:+أدخل+سعرَي+عقد+العميل+والمورد+أولًا+❌"); exit();
    }

    $qty = round((float)$rec['ops_qty'], 2);
    $rno = $rec['record_no'];

    // ── محرّك تفريع الأثر (§6.1): الوحدة المعتمدة تولّد مروحتها كاملةً ذرّيًّا —
    //    إيرادُ العميل + مستحقُّ المورد + تكلفةُ المعدة والمشروع (+ المشغّل والصيانة
    //    متى توفّرت مدخلاتهما)، وكلٌّ مربوطٌ بأبيه في fin_event_links. المحرّك
    //    نفسه دفترُ عطالته: إعادة الاعتماد لا تُكرّر أثرًا. المدخل: ختم الوحدة
    //    «معتمدة» ثم تفريع أثرها في المعاملة نفسها (كلٌّ أو لا شيء). ──
    require_once __DIR__ . '/../app/Services/EffectFanout.php';
    $fan = array('effects' => array(), 'skipped' => array(), 'adopted' => array());
    try {
        ems_tenant_db()->runInTransaction(function ($g) use (&$fan, $conn, $rid, $qty, $current_user_id) {
            $g->update('fin_unit_records', array('match_state' => 'approved', 'approved_qty' => $qty), array('id' => $rid));
            $fresh = $g->selectOne('fin_unit_records', array('where' => array('id' => $rid)));
            $fan = \App\Services\EffectFanout::forUnitRecord($conn, $g, $fresh, $current_user_id);
        }, 'approve unit match + effect fan-out');
    } catch (\Throwable $e) {
        error_log('unit fanout: ' . $e->getMessage());
        header("Location: unit_records_fin.php?msg=" . rawurlencode('تعذّر توليد مروحة الأثر: ' . $e->getMessage()) . "+❌"); exit();
    }
    // السجل والإشعارات — بعد نجاح المعاملة
    $genCount = count($fan['effects']) + count($fan['adopted']);
    fin_log_approval($conn, $company_id, $rid, 'matched', 'approved', 'advance', 'finance_manager', $current_user_id,
        'اعتماد ' . $rno . ' وتفريع الأثر: ' . $genCount . ' أثرًا مولَّدًا/متبنّى · ' . count($fan['skipped']) . ' غير متاح');
    fin_notify($conn, $company_id, 'dept_accountant', 'مروحة أثرٍ من ' . $rno . ' (' . $genCount . ' أثرًا) — الإيراد بانتظار رفعه', 'events_list_fin.php?fstate=draft');
    foreach ($fan['effects'] as $e) {
        if ($e['effect'] === 'supplier_due') { fin_notify($conn, $company_id, 'treasurer', 'مستحق مورد جديد من ' . $rno . ' بانتظار التسوية', 'dues_fin.php'); }
    }
    // إعلان الفجوات صراحةً (لا حدود صامتة)
    $skipMsg = '';
    if ($fan['skipped']) {
        $names = array();
        foreach ($fan['skipped'] as $s) { $names[] = $s['label']; }
        $skipMsg = '+—+غير+متاح:+' . rawurlencode(implode('،+', $names));
    }
    header("Location: unit_records_fin.php?msg=" . rawurlencode('اعتُمد ' . $rno . ' وتولّدت مروحته (' . $genCount . ' أثرًا)') . $skipMsg . "+✅"); exit();
}

// ── حذف ناعم (غير المعتمد فقط) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: unit_records_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['delete_id']);
    // حذف ناعم لغير المعتمد فقط → update بحارس whereRaw (softDelete بالـid لا يحمل شرط الحالة)
    ems_tenant_db()->update('fin_unit_records',
        array('is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $current_user_id),
        array('id' => $d), "match_state<>'approved'");
    header("Location: unit_records_fin.php?msg=تم+حذف+السجل+✅"); exit();
}

// ── حفظ/تعديل سجل وحدات ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['work_model'])) {
    $id = intval($_POST['id'] ?? 0); $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: unit_records_fin.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: unit_records_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }

    $rdate = trim($_POST['record_date'] ?? '') ?: date('Y-m-d');
    $pid = intval($_POST['project_id'] ?? 0);
    $eqid = intval($_POST['equipment_id'] ?? 0) ?: null;

    // ── D02: حارس الازدواج — لا مصدرَ ثانيًا لليوم نفسه ──
    // يومٌ حُوِّل من سجلّ الدوام (له أثرٌ في fin_event_links) لا يُدخَل يدويًّا
    // مرةً أخرى لنفس المعدة والمشروع — وإلا تضاعف الإيراد والمستحق من بابين.
    if ($eqid && $pid && $rdate !== '') {
        $dupSql = "SELECT t.id FROM timesheet t
                     JOIN operations o ON o.id = t.operator
                    WHERE {TENANT_SCOPE} AND t.`date` = ? AND o.equipment = ? AND o.project_id = ?
                      AND EXISTS (SELECT 1 FROM fin_event_links l
                                   WHERE l.parent_kind = 'timesheet' AND l.parent_ref = t.id)
                    LIMIT 1";
        $dup = array();
        try {
            // operations مربوطٌ INNER (شرطُ وجود) ⇒ نطاقٌ لا إثراء (عقد البوابة)
            $dup = fin_gate($is_super_admin)->scopedQuery(
                array('scope' => array('t' => 'timesheet', 'o' => 'operations'),
                      'enrich' => array('l' => 'fin_event_links')),
                $dupSql, array($rdate, $eqid, $pid));
        } catch (\Throwable $x) { error_log('unit dup guard: ' . $x->getMessage()); }
        if ($dup) {
            header("Location: unit_records_fin.php?msg=" . rawurlencode(
                'هذا اليوم محوَّلٌ ماليًّا من سجلّ الدوام (TS-' . intval($dup[0]['id']) . ') — لا إدخالَ موازيًا يضاعف المال') . "+❌");
            exit();
        }
    }
    $sup = intval($_POST['supplier_entity_id'] ?? 0) ?: null;
    $wm = isset($work_models[$_POST['work_model'] ?? '']) ? $_POST['work_model'] : '';
    $ops = round(floatval($_POST['ops_qty'] ?? 0), 2);
    $cq = ($_POST['client_qty'] ?? '') === '' ? null : round(floatval($_POST['client_qty']), 2);
    $sq = ($_POST['supplier_qty'] ?? '') === '' ? null : round(floatval($_POST['supplier_qty']), 2);
    $cp = ($_POST['client_unit_price'] ?? '') === '' ? null : round(floatval($_POST['client_unit_price']), 2);
    $sp = ($_POST['supplier_unit_price'] ?? '') === '' ? null : round(floatval($_POST['supplier_unit_price']), 2);
    $dh = ($_POST['downtime_hours'] ?? '') === '' ? null : round(floatval($_POST['downtime_hours']), 2);
    $dc = isset($downtime_causes[$_POST['downtime_cause'] ?? '']) ? $_POST['downtime_cause'] : null;
    $vn = trim($_POST['variance_note'] ?? '');
    $ref = trim($_POST['source_ref'] ?? '');

    if ($pid <= 0 || $wm === '' || $ops <= 0) { header("Location: unit_records_fin.php?msg=بيانات+ناقصة+(مشروع/نموذج/كمية+التشغيل)+❌"); exit(); }
    // قاعدة السبب: لا ساعة توقف بلا سبب مصنَّف
    if ($dh !== null && $dh > 0 && $dc === null) { header("Location: unit_records_fin.php?msg=قاعدة+السبب:+لا+توقف+بلا+سبب+مصنَّف+❌"); exit(); }
    $mstate = fin_compute_match_state($ops, $cq, $sq);

    // بيانات السجل المشتركة (إنشاء/تعديل) — القيم كما هي، والعزل والترميز مسؤولية البوابة
    $data = array(
        'record_date' => $rdate, 'project_id' => $pid,
        'equipment_id' => $eqid, 'supplier_entity_id' => $sup,
        'work_model' => $wm, 'ops_qty' => $ops,
        'client_qty' => $cq, 'supplier_qty' => $sq,
        'client_unit_price' => $cp, 'supplier_unit_price' => $sp,
        'downtime_hours' => $dh, 'downtime_cause' => $dc,
        'variance_note' => $vn, 'source_ref' => $ref, 'match_state' => $mstate,
    );
    if ($is_editing) {
        // لا تعديل على المعتمد (حارس حالة عبر whereRaw — والقراءة تُبقي رفض السجل المفقود)
        $cur = ems_tenant_db()->selectOne('fin_unit_records', array('columns' => array('match_state'), 'where' => array('id' => $id)));
        if (!$cur || $cur['match_state'] === 'approved') { header("Location: unit_records_fin.php?msg=لا+تعديل+على+سجل+معتمد+❌"); exit(); }
        ems_tenant_db()->update('fin_unit_records', $data, array('id' => $id), "match_state<>'approved'");
        header("Location: unit_records_fin.php?msg=تم+تعديل+السجل+(الحالة:+" . urlencode($match_states[$mstate]) . ")+✅"); exit();
    } else {
        $data['record_no'] = fin_gen_code($conn, 'fin_unit_records', 'FIN-UR', $company_id);
        $data['created_by'] = $current_user_id;
        ems_tenant_db()->insert('fin_unit_records', $data);
        header("Location: unit_records_fin.php?msg=أُنشئ+السجل+(الحالة:+" . urlencode($match_states[$mstate]) . ")+✅"); exit();
    }
}

$page_title = 'إيكوبيشن | كشف الوحدات والتطابق';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main fin-units-main ems-unified-page-shell">
    <?php
    $header_title = 'كشف الوحدات التشغيلية والتطابق الثلاثي'; $header_icon = 'fa fa-cubes';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'سجل وحدات يومي'); }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <?php
    // ═══ D02: طابور التحويل المالي — أيامٌ اكتمل اعتمادها التشغيلي وتنتظر الختم ═══
    $q_project = intval($_GET['q_project'] ?? 0);
    $q_period  = trim((string) ($_GET['q_period'] ?? ''));
    $queue = fin_conversion_queue($conn, $is_super_admin, array(
        'project_id' => $q_project ?: null, 'period' => $q_period ?: null, 'limit' => 200));
    // التسعير للصفحة المعروضة وحدها (استعلامان لكل صف — لا يُسعَّر ما لا يُعرض)
    $pricing = $queue ? fin_queue_pricing($conn, $queue) : array();
    $readyCount = 0;
    foreach ($pricing as $pr) { if (!empty($pr['ready'])) { $readyCount++; } }
    ?>
    <div class="card" style="margin-bottom:14px;border-right:4px solid #d4b06a;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h5 style="margin:0"><i class="fas fa-money-check-dollar"></i> طابور التحويل المالي
                <span class="badge bg-warning"><?php echo count($queue); ?> يومًا بانتظار الختم</span>
                <?php if (!fin_convert_gate_on()): ?>
                    <span class="badge bg-secondary" title="العلَم EMS_UNIT_CONVERT_GATE=off">البوابة غير مفعّلة — الأثر يتولّد تلقائيًّا عند الاعتماد الرابع</span>
                <?php endif; ?>
            </h5>
            <?php if ($can_edit && $queue): ?>
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="qSelectAll(true)"><i class="fas fa-check-double"></i> حدّد الكل</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="qSelectAll(false)">إلغاء التحديد</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="qConvert()"><i class="fas fa-right-left"></i> تحويل المحدد (<span id="qCount">0</span>)</button>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <p class="text-muted" style="margin:0 0 10px">
                <i class="fas fa-shield-halved"></i> <strong>مصدر الحقيقة سجلّ الدوام.</strong>
                هذه الأيام اكتمل اعتمادها التشغيلي (المستويات الأربعة) وتنتظر التحويل المالي —
                <strong>ولحظةَ التحويل تُنشأ آثارها كلها دفعةً واحدة</strong>: إيراد العميل ومستحق المورد وتكلفة المعدة والمشروع.
                <?php echo $can_edit ? 'راجع الأرقام أدناه <strong>قبل</strong> التحويل — فالأثر بعد توليده محصَّنٌ لا يُحرَّر.'
                    : '<strong>لديك صلاحية العرض فقط</strong> — التحويل يحتاج صلاحية التعديل على هذه الشاشة.'; ?>
            </p>

            <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:12px;">
                <div><label style="font-size:.85rem">المشروع</label><br>
                    <select name="q_project" onchange="this.form.submit()"><?php echo fin_project_options($conn, $is_super_admin, $company_id, $q_project); ?></select></div>
                <div><label style="font-size:.85rem">الشهر</label><br>
                    <input type="month" name="q_period" value="<?php echo htmlspecialchars($q_period); ?>" onchange="this.form.submit()"></div>
                <?php if ($q_project || $q_period !== ''): ?>
                    <a href="unit_records_fin.php" class="btn btn-sm btn-outline-secondary">مسح المرشّحات</a>
                <?php endif; ?>
            </form>

            <?php if (!empty($GLOBALS['fin_queue_error'])): ?>
                <div class="alert alert-danger" style="padding:10px"><i class="fas fa-triangle-exclamation"></i>
                    <strong>تعذّر بناء الطابور</strong> — لا تعتبر هذه الشاشة فارغةً بحق:
                    <code><?php echo htmlspecialchars((string) $GLOBALS['fin_queue_error']); ?></code></div>
            <?php elseif (!$queue): ?>
                <div class="text-muted" style="padding:10px"><i class="fas fa-check-circle"></i> لا أيامَ بانتظار التحويل ضمن هذا النطاق.</div>
            <?php else: ?>
            <div class="table-container">
                <table id="queueTable" class="display nowrap alltables no-datatable" style="width:100%;">
                    <thead><tr>
                        <?php if ($can_edit): ?><th style="width:34px"><input type="checkbox" id="qAll" onchange="qSelectAll(this.checked)"></th><?php endif; ?>
                        <th>اليوم</th><th>المرجع</th><th>المشروع</th><th>المعدة</th><th>الكمية</th>
                        <th>الإيراد المتوقَّع</th><th>مستحق المورد</th><th>الحالة</th><th>اعتمده</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($queue as $row):
                        $tid = intval($row['id']);
                        $pr = isset($pricing[$tid]) ? $pricing[$tid] : array('ready' => false, 'reason' => 'بلا تسعير', 'qty' => null, 'unit' => null, 'revenue' => null, 'due' => null);
                        $ready = !empty($pr['ready']);
                    ?>
                        <tr class="<?php echo $ready ? '' : 'table-warning'; ?>">
                            <?php if ($can_edit): ?>
                            <td><?php if ($ready): ?>
                                <input type="checkbox" class="q-chk" value="<?php echo $tid; ?>" onchange="qCount()">
                            <?php else: ?><span title="غير قابلٍ للتحويل">—</span><?php endif; ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars((string) $row['work_date']); ?></td>
                            <td><code>TS-<?php echo $tid; ?></code></td>
                            <td><?php echo htmlspecialchars((string) ($row['project_name'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['equipment_name'] ?? '—')); ?></td>
                            <td><?php echo $pr['qty'] !== null ? number_format((float) $pr['qty'], 2) . ' ' . fin_unit_label_ar($pr['unit']) : '—'; ?></td>
                            <td><?php echo $pr['revenue'] !== null
                                ? '<strong style="color:#1a7a3a;">' . number_format((float) $pr['revenue'], 2) . '</strong> <small>' . htmlspecialchars((string) $pr['revenue_cur']) . '</small>'
                                : '<span class="text-muted">—</span>'; ?></td>
                            <td><?php echo $pr['due'] !== null
                                ? number_format((float) $pr['due'], 2) . ' <small>' . htmlspecialchars((string) $pr['due_cur']) . '</small>'
                                : '<span class="text-muted">—</span>'; ?></td>
                            <td><?php if ($ready): ?><span class="badge bg-success">جاهزٌ للتحويل</span>
                                <?php else: ?><span class="badge bg-secondary" title="<?php echo htmlspecialchars((string) $pr['reason']); ?>">متعذّر</span><?php endif; ?>
                                <?php if ((string) $pr['reason'] !== ''): ?>
                                    <div style="color:#9a6a00;font-size:.82rem;max-width:340px;white-space:normal;"><?php echo htmlspecialchars((string) $pr['reason']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($row['approved_by_name'] ?? '—')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($can_edit): ?>
            <form id="qForm" method="post" style="display:none">
                <input type="hidden" name="action" value="convert_units">
                <input type="hidden" name="csrf_token" value="<?php echo fin_action_token(); ?>">
                <input type="hidden" name="ids" id="qIds" value="">
            </form>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card"><div class="card-body" style="padding-bottom:6px">
        <p class="text-muted" style="margin:0"><i class="fas fa-shield-halved"></i>
        <strong>قاعدة التطابق الثلاثي:</strong> لا تتحول وحدة إلى فاتورة قبل تطابق كمية التشغيل = مصادقة العميل = حساب المورد.
        عند الاعتماد يتولّد <strong>الحدثان التوأمان</strong>: إيراد العميل + مستحق المورد بالكمية نفسها — والفرق بين السعرين = <strong>هامش الوحدة</strong>.
        <strong>قاعدة السبب:</strong> لا ساعة توقف بلا سبب مصنَّف.</p>
    </div></div>

    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> سجل وحدات يومي</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <input type="hidden" name="id" id="u_id" value="">
            <div class="form-group"><label>التاريخ <span class="required">*</span></label><input type="date" name="record_date" id="u_date" value="<?php echo date('Y-m-d'); ?>" required></div>
            <div class="form-group"><label>المشروع <span class="required">*</span></label><select name="project_id" id="u_project" required><?php echo fin_project_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>المعدة</label><select name="equipment_id" id="u_equipment"><?php echo fin_equipment_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>المورد (شريك الإنتاج)</label><select name="supplier_entity_id" id="u_supplier"><?php echo fin_supplier_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>نموذج العمل <span class="required">*</span></label><select name="work_model" id="u_model" required><?php foreach ($work_models as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>كمية كشوف التشغيل <span class="required">*</span></label><input type="number" step="0.01" min="0" name="ops_qty" id="u_ops" required></div>
            <div class="form-group"><label>كمية مصادقة العميل</label><input type="number" step="0.01" min="0" name="client_qty" id="u_client" placeholder="عند وصول المصادقة"></div>
            <div class="form-group"><label>كمية حساب المورد</label><input type="number" step="0.01" min="0" name="supplier_qty" id="u_supqty" placeholder="عند مطابقة المورد"></div>
            <div class="form-group"><label>سعر وحدة عقد العميل</label><input type="number" step="0.01" min="0" name="client_unit_price" id="u_cprice"></div>
            <div class="form-group"><label>سعر وحدة عقد المورد</label><input type="number" step="0.01" min="0" name="supplier_unit_price" id="u_sprice"></div>
            <div class="form-group"><label>ساعات التوقف</label><input type="number" step="0.01" min="0" name="downtime_hours" id="u_dh"></div>
            <div class="form-group"><label>سبب التوقف (إلزامي إن وُجد)</label><select name="downtime_cause" id="u_dc"><option value="">— بلا توقف —</option><?php foreach ($downtime_causes as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>المرجع الميداني</label><input type="text" name="source_ref" id="u_ref" placeholder="كشف تشغيل / تذكرة وزن / محضر قياس"></div>
            <div class="form-group" style="grid-column:1/-1"><label>توثيق الفرق (إن وُجد)</label><input type="text" name="variance_note" id="u_vn"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#finForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الرقم</th><th>التاريخ</th><th>المشروع</th><th>النموذج</th>
                    <th>تشغيل</th><th>عميل</th><th>مورد</th><th>التطابق</th><th>هامش الوحدة</th><th>توقف</th>
                </tr></thead>
                <tbody>
                <?php
                // إثراء اسم المشروع LEFT JOIN عبر scopedQuery (العزل على u، super→كل الشركات)
                // + نطاق المشروع للدورين 5/6 (fin_project_scope — يريان موقعهما حصرًا)
                $proj_scope = fin_project_scope($conn, $ctx);
                $ur_whereRaw = "COALESCE(u.is_deleted,0)=0";
                $ur_params = array();
                if ($proj_scope !== null) { $ur_whereRaw .= " AND u.project_id = ?"; $ur_params[] = $proj_scope; }
                $unit_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('u' => 'fin_unit_records'), 'enrich' => array('p' => 'project')),
                    "SELECT u.*, p.name AS project_name FROM fin_unit_records u
                     LEFT JOIN project p ON p.id = u.project_id
                     WHERE {TENANT_SCOPE} AND " . $ur_whereRaw . " ORDER BY u.record_date DESC, u.id DESC",
                    $ur_params);
                foreach ($unit_rows as $row) {
                    $ms = (string)$row['match_state'];
                    $tone = $ms === 'approved' ? 'success' : ($ms === 'matched' ? 'primary' : ($ms === 'variance' ? 'danger' : 'secondary'));
                    $data =
                        "data-id='" . intval($row['id']) . "' data-date='" . htmlspecialchars((string)$row['record_date'], ENT_QUOTES) . "'"
                        . " data-project='" . intval($row['project_id']) . "' data-equipment='" . intval($row['equipment_id']) . "'"
                        . " data-supplier='" . intval($row['supplier_entity_id']) . "' data-model='" . htmlspecialchars((string)$row['work_model'], ENT_QUOTES) . "'"
                        . " data-ops='" . htmlspecialchars((string)$row['ops_qty'], ENT_QUOTES) . "' data-client='" . htmlspecialchars((string)($row['client_qty'] ?? ''), ENT_QUOTES) . "'"
                        . " data-supqty='" . htmlspecialchars((string)($row['supplier_qty'] ?? ''), ENT_QUOTES) . "'"
                        . " data-cprice='" . htmlspecialchars((string)($row['client_unit_price'] ?? ''), ENT_QUOTES) . "'"
                        . " data-sprice='" . htmlspecialchars((string)($row['supplier_unit_price'] ?? ''), ENT_QUOTES) . "'"
                        . " data-dh='" . htmlspecialchars((string)($row['downtime_hours'] ?? ''), ENT_QUOTES) . "'"
                        . " data-dc='" . htmlspecialchars((string)($row['downtime_cause'] ?? ''), ENT_QUOTES) . "'"
                        . " data-ref='" . htmlspecialchars((string)($row['source_ref'] ?? ''), ENT_QUOTES) . "'"
                        . " data-vn='" . htmlspecialchars((string)($row['variance_note'] ?? ''), ENT_QUOTES) . "'";
                    echo "<tr><td><div class='action-btns'>";
                    if ($ms === 'matched' && $can_edit) {
                        echo "<a href='?approve_id=" . intval($row['id']) . "&_t=" . fin_action_token() . "' class='action-btn edit' title='اعتماد التطابق وتوليد التوأمين' onclick='return confirm(\"اعتماد التطابق وتوليد الحدثين التوأمين؟\")'><i class='fas fa-check-double'></i></a>";
                    }
                    if ($ms !== 'approved') {
                        if ($can_edit) echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $data title='تعديل'><i class='fas fa-edit'></i></a>";
                        if ($can_delete) echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    }
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)$row['record_no']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['record_date']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['project_name'] ?? '#' . $row['project_id'])) . "</td>";
                    echo "<td>" . htmlspecialchars($work_models[$row['work_model']] ?? $row['work_model']) . "</td>";
                    echo "<td>" . number_format((float)$row['ops_qty'], 2) . "</td>";
                    echo "<td>" . ($row['client_qty'] !== null ? number_format((float)$row['client_qty'], 2) : '—') . "</td>";
                    echo "<td>" . ($row['supplier_qty'] !== null ? number_format((float)$row['supplier_qty'], 2) : '—') . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . htmlspecialchars($match_states[$ms] ?? $ms) . "</span></td>";
                    echo "<td>" . ($ms === 'approved' ? "<strong>" . number_format((float)$row['unit_margin'], 2) . "</strong>" : '—') . "</td>";
                    echo "<td>" . ($row['downtime_hours'] !== null && (float)$row['downtime_hours'] > 0
                            ? number_format((float)$row['downtime_hours'], 1) . ' س (' . htmlspecialchars($downtime_causes[$row['downtime_cause']] ?? '') . ')'
                            : '—') . "</td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
$(document).ready(function () {
    $('#finTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
    $('#toggleForm').on('click', function () { document.getElementById('finForm').reset(); $('#u_id').val(''); $('#finForm').toggleClass('allforms-visible'); });
    $(document).on('click', '.editBtn', function () {
        var t = $(this);
        $('#u_id').val(t.data('id')); $('#u_date').val(t.data('date'));
        $('#u_project').val(String(t.data('project'))); $('#u_equipment').val(String(t.data('equipment')));
        $('#u_supplier').val(String(t.data('supplier'))); $('#u_model').val(t.data('model'));
        $('#u_ops').val(t.data('ops')); $('#u_client').val(t.data('client')); $('#u_supqty').val(t.data('supqty'));
        $('#u_cprice').val(t.data('cprice')); $('#u_sprice').val(t.data('sprice'));
        $('#u_dh').val(t.data('dh')); $('#u_dc').val(t.data('dc')); $('#u_ref').val(t.data('ref')); $('#u_vn').val(t.data('vn'));
        $('#finForm').addClass('allforms-visible');
        $('html, body').animate({ scrollTop: $('#finForm').offset().top }, 400);
    });
});

// ═══ طابور التحويل المالي — نمط شاشة الاعتمادات نفسه (سجل/مجموعة/الكل) ═══
// الجدول بلا DataTables (no-datatable) فمربعات التحديد تبقى في الصفحة كلها.
function qSelectAll(on) {
    document.querySelectorAll('.q-chk').forEach(function (c) { c.checked = !!on; });
    var all = document.getElementById('qAll'); if (all) { all.checked = !!on; }
    qCount();
}
function qSelected() {
    return Array.prototype.slice.call(document.querySelectorAll('.q-chk:checked')).map(function (c) { return c.value; });
}
function qCount() {
    var n = qSelected().length;
    var el = document.getElementById('qCount'); if (el) { el.textContent = n; }
    return n;
}
function qConvert() {
    var ids = qSelected();
    if (!ids.length) { alert('حدّد يومًا واحدًا على الأقل للتحويل.'); return; }
    // تأكيدٌ يعرض الأثر قبل وقوعه — الأثر بعد توليده محصَّنٌ لا يُحرَّر
    if (!confirm('تحويل ' + ids.length + ' يومًا إلى استحقاقاتٍ مالية؟\n\n'
        + 'ستُنشأ لكل يوم: إيراد العميل + مستحق المورد + تكلفة المعدة والمشروع.\n'
        + 'لا يمكن التراجع عن الأثر بعد توليده.')) { return; }
    document.getElementById('qIds').value = ids.join(',');
    document.getElementById('qForm').submit();
}
</script>
</body>
</html>
