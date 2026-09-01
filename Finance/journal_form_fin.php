<?php
/**
 * Finance/journal_form_fin.php — القيود المالية (fin_journal_entries + fin_journal_lines) — §3.8 / §7.1.
 * محرك القيد المتوازن: لا يُرحَّل قيدٌ إلا إذا تساوى إجمالي المدين والدائن وله سطران فأكثر.
 * رأس + سطور ديناميكية في صفحة واحدة. عزل شركة + حذف ناعم. شاشة مستقلة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx             = fin_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/journal_form_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض القيود ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);

// ── ترحيل القيد (محرك التوازن) ──
if (isset($_GET['post_id'])) {
    if (!$can_edit) { ems_gov_flash_redirect('journal_form_fin.php', 'لا توجد صلاحية الترحيل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!fin_verify_action_token()) { ems_gov_flash_redirect('journal_form_fin.php', 'رمز الحماية غير صالح ❌', 'GOV-FAIL-409', ''); exit(); } // إصلاح #2
    if (!fin_can_perform($conn, $ctx['role'], 'finance_manager')) { ems_gov_flash_redirect('journal_form_fin.php', 'الترحيل يخص المدير المالي فقط ❌', 'GOV-FAIL-409', ''); exit(); } // فصل الواجبات
    $pid = intval($_GET['post_id']);
    $gate = ems_tenant_db(); // الترحيل شركة الجلسة دومًا (كالأصل company_id=$company_id)

    // اجمع السطور وتحقق من التوازن (مجموع مُعزَّل عبر scopedQuery §10)
    $chk = $gate->scopedQuery(
        array('scope' => array('l' => 'fin_journal_lines')),
        "SELECT COUNT(*) n, COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c
         FROM fin_journal_lines l WHERE {TENANT_SCOPE} AND l.entry_id=?",
        array($pid));
    $r = $chk ? $chk[0] : null;
    $n = $r ? intval($r['n']) : 0; $d = $r ? (float)$r['d'] : 0; $c = $r ? (float)$r['c'] : 0;
    if ($n < 2) { ems_gov_flash_redirect('journal_form_fin.php', 'لا ترحيل: القيد يحتاج سطرين فأكثر ❌', 'GOV-FAIL-409', ''); exit(); }
    if (round($d, 2) !== round($c, 2)) { ems_gov_flash_redirect('journal_form_fin.php', 'لا ترحيل: القيد غير متوازن (مدين≠دائن) ❌', 'GOV-FAIL-409', ''); exit(); }

    // إصلاح #3: لا ترحيل في فترة مالية مقفلة — نقرأ التاريخ والحدث المرتبط معًا
    $entryRow = $gate->selectOne('fin_journal_entries', array('columns' => array('posting_date', 'event_id'), 'where' => array('id' => $pid)));
    $pdate = $entryRow ? $entryRow['posting_date'] : date('Y-m-d');
    if (!fin_period_posting_open($conn, $company_id, $pdate)) {
        ems_gov_flash_redirect('journal_form_fin.php', 'لا ترحيل: الفترة المالية مقفلة لهذا التاريخ ❌', 'GOV-FAIL-409', ''); exit();
    }

    /* ══ INJ-0040 (P1) — «اعتمادُ قيدٍ أعده بنفسه» ═══════════════════════════
       مسارُ الترحيلِ كان يفحص المستوى (`fin_can_perform`) ولا يقارن `created_by`
       بالمُرحِّل — فيُرحِّل المحاسبُ قيدَه بنفسِه. والحدُّ LIMIT-06 في سجلِّ
       السلطاتِ يمنعه نصًّا (**مشروطٌ** لا يُوصَل برمزِ فعلٍ — FN-09)، فموضعُ
       إنفاذِه الخدمةُ لا الحارس. */
    require_once __DIR__ . '/../includes/self_approval_guard.php';
    $__sa = ems_assert_not_self_approval($conn, 'fin_journal_entries', 'id', $pid,
        'قيد يومي #' . $pid, $company_id);
    if ($__sa !== null) {
        ems_gov_flash_redirect('journal_form_fin.php', $__sa['reason'], 'GOV-PERM-403',
            'الترحيل يد ثانية غير يد الإعداد');
        exit();
    }

    // زوجٌ كتابيٌّ مترابط (§9): ترحيل القيد (بحارس حالة draft) + نقل الحدث المرتبط
    // إلى «مقيَّد» ذرّيًّا. حارس الحصانة (§12) يرفض تعديل حدثٍ منشورٍ على الناقل —
    // فينكفئ الترحيل كله (rollback) بدل تباعد جذر/مشتق (المسار اليدوي بلا idempotency يمرّ).
    try {
        $gate->runInTransaction(function ($g) use ($pid, $entryRow, $current_user_id) {
            /* ◆ **مرجعُ الاعتمادِ يُولَد عندَ الترحيلِ لا عندَ الإنشاء** (FR-FIN-006):
                 المسوَّدةُ لا معتمِدَ لها بعد، والقيدُ في القاعدةِ يطلبه للمرحَّلِ
                 وحدَه. والمرجعُ **يشهد على اليدِ الثانية**: مَن رحَّل ومتى —
                 وحارسُ عدمِ اعتمادِ النفسِ فوقَه سلفًا. */
            $__apr = 'JV-APR-' . $pid . '-U' . (int) $current_user_id . '-' . date('YmdHis');
            $g->update('fin_journal_entries',
                array('state' => 'posted', 'posted_by' => $current_user_id,
                      'posted_at' => date('Y-m-d H:i:s'), 'approval_ref' => $__apr),
                array('id' => $pid), "state='draft'");
            $eid = $entryRow ? intval($entryRow['event_id']) : 0;
            if ($eid > 0) {
                $g->update('fin_financial_events',
                    array('state' => 'posted', 'journal_entry_id' => $pid),
                    array('id' => $eid), "COALESCE(is_deleted,0)=0");
            }
        }, 'post journal entry + advance linked event');
    } catch (\App\Core\TenantGateException $e) {
        error_log('journal post refused: ' . $e->getMessage());
        ems_gov_flash_redirect('journal_form_fin.php', 'لا يجوز ترحيل قيد مرتبط بحدث منشور على الناقل ❌', 'GOV-FAIL-409', ''); exit();
    }
    // §9.3: حقيقة finance.posted على الجذر — القيد رُحِّل لحدثِ طلبٍ (إن كان)
    $eidFact = $entryRow ? intval($entryRow['event_id']) : 0;
    if ($eidFact > 0) {
        fin_publish_request_fact($conn, $eidFact, 'finance.posted', 'posted',
            array('journal_entry_id' => $pid));
        // H-12 (FES §7.2): Approved → Posted بقفلٍ تفاؤلي وختمِ posted_by/at
        require_once __DIR__ . '/../app/Services/Finance/EventStateMachine.php';
        $fesSync = \App\Services\Finance\EventStateMachine::syncTo(
            ems_tenant_db(), $conn, $eidFact, 'Posted', $current_user_id);
        if (!$fesSync['ok']) { error_log('journal post fes sync ev#' . $eidFact . ': ' . implode(' · ', $fesSync['reasons'])); }
    }
    // N-02: تدقيقُ الترحيل بقيم قبل/بعد
    require_once __DIR__ . '/../includes/audit_trail.php';
    ems_audit_change($conn, 'journal', 'journal_form_fin', 'post', $pid,
        array('state' => 'draft'), array('state' => 'posted', 'posted_by' => $current_user_id),
        array('company_id' => $company_id, 'user_id' => $current_user_id));

    // (فجوة 3) الانحراف المستمر: تغذية «الفعلي» في الموازنة من القيود المرحّلة فورًا
    $fed = fin_recalc_budget_actuals($conn, $company_id);
    ems_gov_flash_redirect('journal_form_fin.php', "تم ترحيل القيد وتحدث فعلي الموازنة ($fed بند) ✅", 'GOV-OK-200', ''); exit();
}

// ── حذف ناعم (مسودة فقط) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('journal_form_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $did = intval($_GET['delete_id']);
    // حذف ناعم مشروط بحالة draft → update بحارس whereRaw (softDelete بالـid فقط لا يحمل شرط الحالة)
    $affected = ems_tenant_db()->update('fin_journal_entries',
        array('is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $current_user_id),
        array('id' => $did), "state='draft'");
    // N-02: تدقيقُ حذف المسودة (حذفٌ ناعمٌ فقط — والمرحَّل لا يُحذف أصلًا)
    if (intval($affected) > 0) {
        require_once __DIR__ . '/../includes/audit_trail.php';
        ems_audit_change($conn, 'journal', 'journal_form_fin', 'soft_delete', $did,
            array('is_deleted' => 0), array('is_deleted' => 1, 'deleted_by' => $current_user_id),
            array('company_id' => $company_id, 'user_id' => $current_user_id));
    }
    ems_gov_flash_redirect('journal_form_fin.php', 'تم حذف القيد بنجاح ✅', 'GOV-OK-200', ''); exit();
}

// ── حفظ قيد جديد (رأس + سطور) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['posting_date'])) {
    if (!$can_add) { ems_gov_flash_redirect('journal_form_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0) { ems_gov_flash_redirect('journal_form_fin.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $posting_date = trim($_POST['posting_date'] ?? '');
    $txn_date     = trim($_POST['txn_date'] ?? '');
    $jr_currency  = trim($_POST['currency'] ?? '');
    $event_id     = intval($_POST['event_id'] ?? 0) ?: null;
    $memo         = trim($_POST['memo'] ?? '');
    $accounts = $_POST['account_id'] ?? array();
    $debits   = $_POST['debit'] ?? array();
    $credits  = $_POST['credit'] ?? array();
    $lmemos   = $_POST['line_memo'] ?? array();
    $lccs     = $_POST['cost_center_id'] ?? array();

    if ($posting_date === '') { ems_gov_flash_redirect('journal_form_fin.php', 'تاريخ الترحيل مطلوب ❌', 'GOV-FAIL-409', ''); exit(); }
    // M-38: تاريخُ الحركة الفعلي إلزامٌ (بجانب تاريخ الترحيل) — SPEC-01 #13
    if ($txn_date === '') { $txn_date = $posting_date; }
    // M-39: لا كتابةَ ماليةً في فترةٍ مقفلة — حتى المسودةُ (423)
    require_once __DIR__ . '/../includes/period_guard.php';
    $pchk = ems_period_check($conn, $company_id, $posting_date);
    if (!$pchk['ok']) { ems_gov_flash_redirect(ems_flash_to('journal_form_fin.php', "+❌"), $pchk['reason'], 'GOV-INFO-200', ''); exit(); }
    // M-38: اليدويُّ الاستثنائي بسببٍ موثَّق إلزامًا (SPEC-01 #13: POST /journal/manual بسببٍ إلزامي)
    if ($memo === '') { ems_gov_flash_redirect('journal_form_fin.php', 'بيان القيد (السبب) إلزامي للقيد اليدوي ❌', 'GOV-FAIL-409', ''); exit(); }

    /* ══ FR-FIN-006 · §سابعًا — القيدُ اليدويُّ لا يُقبل ناقصَ الحوكمة ══════
       ◆ قيدُ `chk_manual_journal_governed` يرفضه من القاعدة، **لكنَّ الرفضَ
         من القاعدةِ يصل المستخدمَ استثناءً غامضًا**. فيُفحَص هنا أوّلًا
         برسالةٍ تسمّي الناقص، **ويبقى قيدُ القاعدةِ هو الحدَّ الأخيرَ** لمن
         يتجاوز الشاشة. حارسان لا حارسٌ واحد.
       ◆ ولا يُطلب هذا من القيدِ الآليّ (له `event_id`) — الشرطُ على اليدويّ. */
    $manual_kind    = trim($_POST['manual_kind'] ?? '');
    $source_doc_ref = trim($_POST['source_doc_ref'] ?? '');
    $reversal_link  = (int) ($_POST['reversal_link'] ?? 0);
    $period_code    = substr($posting_date, 0, 7);
    if ($event_id <= 0) {
        if ($manual_kind === '') {
            ems_gov_flash_redirect('journal_form_fin.php',
                'نوع القيد إلزامي للقيد اليدوي (تسوية/تصحيح/إقفال/عكس) ❌', 'GOV-FAIL-409', ''); exit();
        }
        if ($source_doc_ref === '') {
            ems_gov_flash_redirect('journal_form_fin.php',
                'المستند المصدر إلزامي للقيد اليدوي — ولا قيد بلا مستند ❌', 'GOV-FAIL-409', ''); exit();
        }
        if ($manual_kind === 'عكس' && $reversal_link <= 0) {
            ems_gov_flash_redirect('journal_form_fin.php',
                'قيد العكس يلزمه رابط القيد المعكوس ❌', 'GOV-FAIL-409', ''); exit();
        }
    }
    // M-38: العملة من الدليل المسجَّل حصرًا
    require_once __DIR__ . '/../includes/fx.php';
    $jr_code = ems_fx_code($jr_currency !== '' ? $jr_currency : 'SDG');
    if ($jr_code === null) { ems_gov_flash_redirect('journal_form_fin.php', 'عملة غير مسجلة ❌', 'GOV-FAIL-409', ''); exit(); }

    // اجمع السطور الصالحة
    $lines = array(); $tot_d = 0; $tot_c = 0;
    for ($i = 0; $i < count($accounts); $i++) {
        $acc = intval($accounts[$i]);
        $dv  = round(floatval($debits[$i] ?? 0), 2);
        $cv  = round(floatval($credits[$i] ?? 0), 2);
        if ($acc <= 0 || ($dv == 0 && $cv == 0)) { continue; }
        $lines[] = array('acc' => $acc, 'd' => $dv, 'c' => $cv, 'm' => trim($lmemos[$i] ?? ''),
                         'cc' => intval($lccs[$i] ?? 0) ?: null);
        $tot_d += $dv; $tot_c += $cv;
    }
    if (count($lines) < 2) { ems_gov_flash_redirect('journal_form_fin.php', 'القيد يحتاج سطرين صالحين فأكثر ❌', 'GOV-FAIL-409', ''); exit(); }
    // M-38: توازنُ القيد شرطُ الحفظ (المتطلب النظامي SPEC-01 #13) — لا مسودةَ غيرَ متوازنة
    if (round($tot_d, 2) !== round($tot_c, 2)) {
        ems_gov_flash_redirect(ems_flash_to('journal_form_fin.php', $tot_d . "+≠+دائن+" . $tot_c . ")+❌"), 'لا حفظ: القيد غير متوازن (مدين ', 'GOV-INFO-200', ''); exit();
    }

    // M-38: المعادلُ الموحّد من سجل الأسعار النافذ يومَ الحركة — NULL معلَنٌ إن لا سعر
    $fx = ems_fx_to_base($tot_d, $jr_code, $txn_date);
    $jr_fx = $fx['ok'] ? $fx['rate'] : null;
    $jr_base = $fx['ok'] ? $fx['base'] : null;

    /* ══ INJ-0177 · لا قيدَ يدويًّا بلا حدثٍ مرتبطٍ أو استثناءٍ نافذ ══════════
         كان `event_id` اختياريًّا (`?: null`) — فالقيدُ اليدويُّ يُنشئ حقيقةً
         ماليةً من العدمِ ولا سبيلَ بعدها إلى مراجعتها. والحكمُ الحاكم:
         **الرقمُ المالي أثرُ واقعةٍ لا مصدرُها** (ADR-15).
         ◆ والفحصُ **قبل توليدِ الرقمِ المسلسل**: توليدُه ثم الرفضُ يستهلك
           رقمًا من السلسلةِ بلا قيدٍ يحملُه — فتصير في الدفترِ فجوةٌ لا تُفسَّر. */
    require_once __DIR__ . '/../includes/source_doc_guard.php';
    $__src = ems_require_source_doc($conn, $company_id, 'journal_entry',
        array('event_id' => $event_id), $current_user_id, 'journal_form_fin');
    if (!$__src['ok']) {
        ems_gov_flash_redirect('journal_form_fin.php', $__src['reason'] . ' ❌', 'GOV-FAIL-422', '');
        exit();
    }

    $entry_no = fin_gen_code($conn, 'fin_journal_entries', 'FIN-JV', $company_id);
    // رأس + سطور = زوجٌ ذرّي (§9): إمّا القيد كاملًا أو لا شيء (لا رأسٌ بلا سطوره)
    ems_tenant_db()->runInTransaction(function ($g) use ($entry_no, $event_id, $posting_date, $txn_date, $jr_code, $jr_fx, $jr_base, $tot_d, $tot_c, $memo, $current_user_id, $lines, $manual_kind, $source_doc_ref, $period_code, $reversal_link) {
        $entry_id = $g->insert('fin_journal_entries', array(
            'entry_no' => $entry_no, 'event_id' => $event_id, 'posting_date' => $posting_date,
            'txn_date' => $txn_date, 'currency' => $jr_code,
            'fx_rate' => $jr_fx, 'base_amount' => $jr_base,
            'total_debit' => $tot_d, 'total_credit' => $tot_c, 'memo' => $memo,
            'state' => 'draft', 'created_by' => $current_user_id,
            /* FR-FIN-006 — بنودُ §سابعًا الخمسةُ الناقصة */
            'manual_kind' => $manual_kind, 'source_doc_ref' => $source_doc_ref,
            'period_code' => $period_code,
            'reversal_link' => $reversal_link > 0 ? $reversal_link : null,
            'manual_gov_state' => 'GOVERNED',
        ));
        foreach ($lines as $ln) {
            $g->insert('fin_journal_lines', array(
                'entry_id' => $entry_id, 'account_id' => $ln['acc'],
                'debit' => $ln['d'], 'credit' => $ln['c'], 'memo' => $ln['m'],
                'cost_center_id' => $ln['cc'],
            ));
        }
    }, 'create journal entry + lines');
    ems_gov_flash_redirect('journal_form_fin.php', 'تم حفظ القيد (متوازن) ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | القيود اليومية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

<div class="main fin-journal-main ems-unified-page-shell">
    <?php
    $header_title = 'القيود اليومية';
    $header_icon  = 'fa fa-scale-balanced';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إنشاء قيد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ٩: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضيًا
    echo ems_states_bundle('لا قيود يومية مسجلة بعد', 'أنشئ قيدا متوازنا (مدين = دائن) بزر «إنشاء قيد» في رأس الشاشة');
    ?>

    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <?php echo csrf_field(); ?>
        <div class="card-header"><h5><i class="fas fa-edit"></i> إنشاء قيد (مدين = دائن)</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="j_date">تاريخ الترحيل <span class="required">*</span></label>
                        <input type="date" name="posting_date" id="j_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="j_txn_date">تاريخ الحركة الفعلي <span class="required">*</span></label>
                        <input type="date" name="txn_date" id="j_txn_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="j_currency">العملة <span class="required">*</span></label>
                        <select name="currency" id="j_currency" required>
                            <?php echo fin_currency_options($conn, $is_super_admin, $company_id, 'SDG'); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="j_event">الحدث المرتبط (اختياري)</label>
                        <select name="event_id" id="j_event">
                            <option value="">— بلا حدث —</option>
                            <?php
                            // القوائم قراءةٌ عابرة للسوبر (fin_gate) — التسمية تُركَّب PHP-side
                            $ev_opts = fin_gate($is_super_admin)->select('fin_financial_events', array(
                                'whereRaw' => "state IN('approved','audited','fin_review')",
                                'orderBy'  => 'id DESC'));
                            foreach ($ev_opts as $e) {
                                echo "<option value='" . intval($e['id']) . "'>" . htmlspecialchars($e['event_no'] . ' — ' . $e['amount'] . ' ' . $e['currency']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <!-- ══ FR-FIN-006 — حوكمةُ القيدِ اليدويّ (§سابعًا) ═══════════════
                         ◆ ثمانيةُ بنودٍ يشترطها الأمرُ الحاكم لكلِّ Manual Journal.
                           ثلاثةٌ كانت قائمةً (السببُ · المُعِدُّ · المعتمِد) وخمسةٌ
                           تُطلب هنا. **وقيدُ القاعدةِ يرفض الناقصَ** — فالحقلُ
                           إلزاميٌّ في الشاشةِ وفي القاعدةِ معًا لا في إحداهما.
                         ◆ ولا تُطلب من القيدِ الآليِّ (له حدثٌ) — الشرطُ على اليدويِّ وحدَه. -->
                    <div class="form-group">
                        <label for="j_kind">نوع القيد <span class="req">*</span></label>
                        <select name="manual_kind" id="j_kind">
                            <option value="">— اختر —</option>
                            <option value="تسوية">تسوية</option>
                            <option value="تصحيح">تصحيح</option>
                            <option value="إقفال">إقفال</option>
                            <option value="عكس">عكس</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="j_doc">المستند المصدر <span class="req">*</span></label>
                        <input type="text" name="source_doc_ref" id="j_doc" placeholder="رقم المستند أو مرجعه">
                    </div>
                    <div class="form-group">
                        <label for="j_rev">رابط العكس</label>
                        <input type="number" name="reversal_link" id="j_rev" placeholder="رقم القيد المعكوس (إن كان عكسا)">
                    </div>
                    <div class="form-group fin-jrn-span-all">
                        <label for="j_memo">بيان القيد</label>
                        <input type="text" name="memo" id="j_memo" placeholder="وصف القيد">
                    </div>
                </div>
            </div>

            <div class="table-container fin-jrn-lines-wrap">
                <table class="alltables fin-jrn-table" id="j_lines">
                    <thead><tr><th>رقم الحساب</th><th>مركز التكلفة</th><th>مدين</th><th>دائن</th><th>بيان</th><th></th></tr></thead>
                    <tbody id="j_lines_body"></tbody>
                    <tfoot><tr>
                        <th colspan="2" class="fin-jrn-total-th">الإجمالي</th>
                        <th id="j_tot_d">0.00</th>
                        <th id="j_tot_c">0.00</th>
                        <th colspan="2"><span id="j_balance" class="badge badge-secondary">—</span></th>
                    </tr></tfoot>
                </table>
                <button type="button" class="btn-secondary" onclick="jAddLine()"><i class="fas fa-plus"></i> إضافة سطر</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ القيد</button>
                <button type="button" class="btn-secondary" onclick="finToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables fin-jrn-table">
                <thead><tr>
                    <th>الإجراءات</th><th>رقم القيد</th><th>تاريخ القيد</th><th>مدين</th><th>دائن</th>
                    <th>التوازن</th><th>البيان</th><th>الحالة</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <!-- XF-01: حلقة القيود تقرا fin_journal_entries بلا قائمة اعمدة فكلها متاحة.
                         وما بقي عاريا هنا حبة سطر (اسم الحساب والمعادل مدين ودائن) لا حبة
                         قيد — وسطح البنود غير سطح القيد. -->
                    <th class="ems-fn-th" data-fn="1" data-fn-src="period">الفترة</th>
                    <th class="ems-fn-th" data-fn="1" data-fn-src="source_kind">المصدر</th>
                    <th class="ems-fn-th" data-fn="1" data-fn-src="source_ref">المرجع</th>
                    <th class="ems-fn-th" data-fn="1">اسم الحساب</th>
                    <th class="ems-fn-th" data-fn="1">بند القائمة المالية</th>
                    <th class="ems-fn-th" data-fn="1">المشروع</th>
                    <th class="ems-fn-th" data-fn="1">العقد</th>
                    <th class="ems-fn-th" data-fn="1">الوحدة التعاقدية</th>
                    <th class="ems-fn-th" data-fn="1">المعدة</th>
                    <th class="ems-fn-th" data-fn="1">المورد</th>
                    <th class="ems-fn-th" data-fn="1">نموذج العمل</th>
                    <th class="ems-fn-th" data-fn="1">المعادل مدين</th>
                    <th class="ems-fn-th" data-fn="1">المعادل دائن</th>
                    <th class="ems-fn-th" data-fn="1" data-fn-src="memo">الوصف</th>
                    <th class="ems-fn-th none" data-fn="1">أعده</th>
                    <th class="ems-fn-th none" data-fn="1">راجعه</th>
                    <th class="ems-fn-th none" data-fn="1">نشره</th>
                    <th class="ems-fn-th none" data-fn="1">تاريخ النشر</th>
                    <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                    <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس ب</th>
                    <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                    <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليا">درجة الأثر</th>
                    <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطلاع</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                    <th class="ems-gov-th none" data-gov="fx_rate" data-slice="3" title="سعر التحويل لعملة الدفاتر">سعر الصرف</th>
                    </tr></thead>
                <tbody>
                    <?php
                    $entries = fin_gate($is_super_admin)->select('fin_journal_entries', array('orderBy' => 'id DESC'));
                    foreach ($entries as $row) {
                        $balanced = (round((float)$row['total_debit'], 2) === round((float)$row['total_credit'], 2));
                        $st = (string)$row['state'];
                        $st_label = $st === 'posted' ? 'مرحل' : ($st === 'reversed' ? 'معكوس' : 'مسودة');
                        $st_tone  = $st === 'posted' ? 'success' : ($st === 'reversed' ? 'dark' : 'secondary');
                        echo "<tr data-xf=\"" . htmlspecialchars(json_encode(array(
                            'period'      => (string) ($row['period_code'] ?? ''),
                            'source_kind' => (string) ($row['manual_kind'] ?? ''),
                            'source_ref'  => (string) ($row['source_doc_ref'] ?? ''),
                            'memo'        => (string) ($row['memo'] ?? ''),
                        ), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . "\">";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit && $st === 'draft') {
                            echo "<a href='?post_id=" . intval($row['id']) . "&_t=" . fin_action_token() . "' class='action-btn edit' title='ترحيل' onclick='return confirm(\"ترحيل القيد؟ لا يرحل غير المتوازن.\")'><i class='fas fa-check-double'></i></a>";
                        }
                        if ($can_delete && $st === 'draft') {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)$row['entry_no']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['posting_date']) . "</td>";
                        echo "<td>" . number_format((float)$row['total_debit'], 2) . "</td>";
                        echo "<td>" . number_format((float)$row['total_credit'], 2) . "</td>";
                        echo "<td>" . ($balanced ? "<span class='badge badge-success'>متوازن</span>" : "<span class='badge badge-danger'>غير متوازن</span>") . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['memo'] ?? '')) . "</td>";
                        echo "<td><span class='badge badge-" . $st_tone . "'>" . htmlspecialchars($st_label) . "</span></td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<template id="j_line_tpl">
    <tr class="j-line">
        <td><select name="account_id[]" aria-label="رقم حساب السطر" class="j-acc"><?php echo fin_postable_account_options($conn, $is_super_admin, $company_id); ?></select></td>
        <td><select name="cost_center_id[]" aria-label="مركز تكلفة السطر" class="j-cc"><?php echo fin_cost_center_options($conn, $is_super_admin, $company_id); ?></select></td>
        <td><input type="number" step="0.01" min="0" name="debit[]" aria-label="المبلغ المدين" class="j-debit" value="0"></td>
        <td><input type="number" step="0.01" min="0" name="credit[]" aria-label="المبلغ الدائن" class="j-credit" value="0"></td>
        <td><input type="text" name="line_memo[]" aria-label="بيان السطر" class="j-memo"></td>
        <td><a href="javascript:void(0)" class="action-btn delete j-del" title="حذف السطر"><i class="fas fa-times"></i></a></td>
    </tr>
</template>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
(function () {
    window.jAddLine = function () {
        var tpl = document.getElementById('j_line_tpl');
        var clone = document.importNode(tpl.content, true);
        document.getElementById('j_lines_body').appendChild(clone);
        jRecalc();
    };
    window.jRecalc = function () {
        var d = 0, c = 0;
        $('#j_lines_body .j-line').each(function () {
            d += parseFloat($(this).find('.j-debit').val()) || 0;
            c += parseFloat($(this).find('.j-credit').val()) || 0;
        });
        $('#j_tot_d').text(d.toFixed(2));
        $('#j_tot_c').text(c.toFixed(2));
        var bal = $('#j_balance');
        if (d === 0 && c === 0) { bal.attr('class', 'badge badge-secondary').text('—'); }
        else if (Math.round(d * 100) === Math.round(c * 100)) { bal.attr('class', 'badge badge-success').text('متوازن ✔'); }
        else { bal.attr('class', 'badge badge-danger').text('غير متوازن (فرق ' + (d - c).toFixed(2) + ')'); }
    };

    $(document).ready(function () {
        // جدولُ العرضِ يهيّئُه المكوّنُ المركزيُّ (assets/js/ui-unification.js)

        // سطران افتراضيان جاهزان
        jAddLine(); jAddLine();

        $(document).on('input', '#j_lines_body .j-debit, #j_lines_body .j-credit', jRecalc);
        $(document).on('click', '.j-del', function () { $(this).closest('tr').remove(); jRecalc(); });

        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                $('#finForm').toggleClass('allforms-visible');
            });
        }
    });

    window.finToggleForm = function () {
        $('#finForm').toggleClass('allforms-visible');
    };
})();
</script>
</body>
</html>
