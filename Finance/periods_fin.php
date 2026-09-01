<?php
/**
 * Finance/periods_fin.php — الفترات المالية والإقفال (fin_financial_periods + fin_closing_items) — §3.16/§3.22/§8.
 * دورة الفترة: مخطّطة → مفتوحة → إقفال مرحلي → مقفلة (بعد قائمة الإقفال) → مقفلة نهائياً / إعادة فتح.
 * قاعدة: لا إقفالَ نهائيٌّ قبل إنجاز بنود الإقفال الإلزامية. شاشة مستقلة — عزل شركة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/periods_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الفترات ❌', 'GOV-PERM-403', ''); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$period_states = fin_period_states();
$closing_steps = fin_closing_steps();

// ── انتقالات حالة الفترة ──
if (isset($_GET['action']) && isset($_GET['pid'])) {
    if (!$can_edit) { ems_gov_flash_redirect('periods_fin.php', 'لا توجد صلاحية الإجراء ❌', 'GOV-PERM-403', ''); exit(); }
    $pid = intval($_GET['pid']); $act = $_GET['action'];

    /* ══ INJ-0042 (P1) — إقفالُ الفترةِ بلا مستوى «المدير المالي» ولا يدٍ ثانية ══
       ① كان أيُّ حاملِ `can_edit` يُقفل الفترةَ ويعيد فتحَها — بلا فحصِ مستوى
          (`fin_can_perform` = صفرُ نداءٍ في الملف). والإقفالُ مستندُ المرحلةِ
          السابعةِ ومعتمِدُه القيادةُ المالية.
       ② والأفعالُ الحاسمةُ (close · lock · reopen) لا يجوز أن ينفّذها منشئُ
          الفترةِ نفسُه — والحدُّ في سجلِّ السلطاتِ يمنعه. */
    if (in_array($act, array('close', 'lock', 'reopen'), true)) {
        if (function_exists('fin_can_perform') && !fin_can_perform($conn, $ctx['role'], 'finance_manager')) {
            ems_gov_flash_redirect('periods_fin.php',
                'إقفال الفترة وفتحها وقفلها للقيادة المالية حصرا — لا يكفي إذن التعديل ❌',
                'GOV-PERM-403', 'اطلبه من المدير المالي');
            exit();
        }
        require_once __DIR__ . '/../includes/self_approval_guard.php';
        $__sa = ems_assert_not_self_approval($conn, 'fin_financial_periods', 'id', $pid,
            'فترة محاسبية #' . $pid, $company_id);
        if ($__sa !== null) {
            ems_gov_flash_redirect('periods_fin.php', $__sa['reason'], 'GOV-PERM-403',
                'الإقفال يد ثانية غير يد الإنشاء');
            exit();
        }
    }

    // انتقالات الحالة عبر البوابة — حراسة الحالة عبر whereRaw، والعزل يُحقن تلقائيًّا.
    $g = fin_gate($is_super_admin);
    $now = date('Y-m-d H:i:s');
    if ($act === 'open') {
        $g->update('fin_financial_periods', array('state'=>'open','posting_allowed'=>1), array('id'=>$pid), "state IN('planned','reopened')");
        ems_gov_flash_redirect('periods_fin.php', 'تم فتح الفترة ✅', 'GOV-OK-200', ''); exit();
    } elseif ($act === 'soft_close') {
        $g->update('fin_financial_periods', array('state'=>'soft_closed','posting_allowed'=>0,'soft_closed_at'=>$now), array('id'=>$pid), "state='open'");
        ems_gov_flash_redirect('periods_fin.php', 'تم الإقفال المرحلي ✅', 'GOV-OK-200', ''); exit();
    } elseif ($act === 'close') {
        // قاعدة الإقفال: كل البنود الإلزامية يجب أن تكون منجَزة
        $n = $g->count('fin_closing_items', array('where'=>array('period_id'=>$pid,'required'=>1,'item_state'=>'pending')));
        if ($n > 0) { ems_gov_redirect("Location: periods_fin.php?pid=$pid&msg=لا+إقفال:+بنود+إلزامية+غير+منجزة+($n)+❌"); exit(); }
        // M-39 (SPEC-01 #14): «زرُّ الإقفال يمنع حين يوجد غيرُ مرحَّلٍ أو فرقٌ
        // مفتوح — بقائمة الموانع» — الفحصُ قبل Close لا بعده.
        require_once __DIR__ . '/../includes/period_guard.php';
        $blockers = ems_period_close_blockers($conn, $company_id, $pid);
        if (!empty($blockers)) {
            $bl = array();
            foreach ($blockers as $b) { $bl[] = $b['label'] . ' (' . $b['count'] . ')'; }
            ems_gov_redirect("Location: periods_fin.php?pid=$pid&msg=" . urlencode('لا إقفال — الموانع: ' . implode(' · ', $bl)) . "+❌"); exit();
        }
        $g->update('fin_financial_periods', array('state'=>'closed','posting_allowed'=>0,'closed_at'=>$now), array('id'=>$pid), "state IN('open','soft_closed')");
        ems_gov_flash_redirect('periods_fin.php', 'تم إقفال الفترة ✅', 'GOV-OK-200', ''); exit();
    } elseif ($act === 'lock') {
        $g->update('fin_financial_periods', array('state'=>'locked','locked_at'=>$now), array('id'=>$pid), "state='closed'");
        ems_gov_flash_redirect('periods_fin.php', 'تم القفل النهائي ✅', 'GOV-OK-200', ''); exit();
    } elseif ($act === 'reopen') {
        // M-39 (SPEC-01 #14): «فتحُ فترةٍ مقفلةٍ قرارٌ أعلى موثَّق» — السببُ
        // إلزامٌ مكتوبٌ لا نصٌّ ثابت، وفاعلُه مختوم (reopened_by).
        $reason = trim((string) ($_GET['reason'] ?? ''));
        if ($reason === '') {
            ems_gov_redirect("Location: periods_fin.php?pid=$pid&msg=" . urlencode('الفتح الاستثنائي يلزمه سبب موثق — اكتبه في نافذة التأكيد') . "+❌"); exit();
        }
        $g->update('fin_financial_periods', array('state'=>'reopened','posting_allowed'=>1,'reopen_reason'=>mb_substr($reason,0,200),'reopened_by'=>$current_user_id), array('id'=>$pid), "state IN('closed','soft_closed')");
        // N-02: قرارُ الفتح الاستثنائي يدخل سجلَّ التدقيق بسببه
        require_once __DIR__ . '/../includes/audit_trail.php';
        ems_audit_change($conn, 'journal', 'periods_fin', 'reopen_period', $pid,
            array('posting_allowed' => 0), array('posting_allowed' => 1),
            array('company_id' => $company_id, 'user_id' => $current_user_id, 'note' => $reason));
        ems_gov_flash_redirect('periods_fin.php', 'تم الفتح الاستثنائي ✅', 'GOV-OK-200', ''); exit();
    }
}

// ── إنجاز بند إقفال ──
/* ══ INJ-0183 · لا إنجازَ لبندِ إقفالٍ بلا مرجعِ دليل ══════════════════════════
     نصُّ القبول: «إنجازُ بندِ إقفالٍ **بلا مرجعِ دليلٍ** يُرفض 422؛ وكلُّ بندٍ
     منجَزٍ **يعرض دليلَه ومن أنجزه ومتى**».
     وكان الإنجازُ رابطَ `GET` عاريًا: نقرةٌ واحدةٌ تُعلن بندَ إقفالٍ منجَزًا بلا
     ما يُثبته — والإقفالُ المحاسبيُّ أثقلُ من أن يُقفل بنقرة.
   ◆ **وصار POST**: فعلٌ يُغيّر الحالةَ لا يُنفَّذ بـGET — فمعاينةٌ آليةٌ أو
     رابطٌ في بريدٍ كانا يُنجزان البندَ بلا قصدِ صاحبه (نمطُ INJ-0160 نفسُه).
   ◆ والدليلُ يُحفظ في `note` ويُعرض في الجدول مع `done_by` و`done_at`. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['done_item'])) {
    if (!$can_edit) { ems_gov_flash_redirect('periods_fin.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
    $it = intval($_POST['done_item']); $pid = intval($_POST['pid'] ?? 0);
    $evidence = trim((string) ($_POST['evidence_ref'] ?? ''));

    require_once __DIR__ . '/../includes/source_doc_guard.php';
    $__src = ems_require_source_doc($conn, $company_id, 'closing_item',
        array('note' => $evidence), $current_user_id, 'periods_fin#' . $it);
    if (!$__src['ok']) {
        ems_gov_flash_redirect('periods_fin.php?pid=' . $pid, $__src['reason'] . ' ❌', 'GOV-FAIL-422', '');
        exit();
    }
    fin_gate($is_super_admin)->update('fin_closing_items',
        array('item_state' => 'done', 'done_by' => $current_user_id,
              'done_at' => date('Y-m-d H:i:s'), 'note' => $evidence),
        array('id' => $it));
    ems_gov_redirect("Location: periods_fin.php?pid=$pid&msg=تم+إنجاز+البند+بدليله+✅"); exit();
}
/* ◆ والمسلكُ القديمُ بـGET **يُردُّ صراحةً** لا يُترك يعمل بصمت — فمن حفظ
     الرابطَ يعرف لماذا لم يعد يعمل. */
if (isset($_GET['done_item'])) {
    ems_gov_flash_redirect('periods_fin.php?pid=' . intval($_GET['pid'] ?? 0),
        'إنجاز بند الإقفال صار يحتاج مرجع دليل — استعمل زر الإنجاز في الجدول ❌',
        'GOV-FAIL-422', 'الإقفال أثقل من أن يقع بنقرة رابط');
    exit();
}

// ── إنشاء فترة + بنود إقفالها ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fiscal_year'])) {
    if (!$can_add) { ems_gov_flash_redirect('periods_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    $fy = intval($_POST['fiscal_year'] ?? 0);
    $ptype = ($_POST['period_type'] ?? '') === 'year' ? 'year' : 'month';
    $pno = $ptype === 'month' ? max(1, min(12, intval($_POST['period_no'] ?? 1))) : null;
    if ($fy < 2000) { ems_gov_flash_redirect('periods_fin.php', 'سنة غير صحيحة ❌', 'GOV-REF-404', ''); exit(); }
    if ($ptype === 'year') { $start = "$fy-01-01"; $end = "$fy-12-31"; }
    else { $start = date('Y-m-01', mktime(0, 0, 0, $pno, 1, $fy)); $end = date('Y-m-t', mktime(0, 0, 0, $pno, 1, $fy)); }

    // إنشاء الفترة + بذر بنود إقفالها زوجٌ مترابط → معاملة ذرّية عبر §9؛ العزل يُحقن تلقائيًّا.
    $pid = 0;
    try {
        $pid = fin_gate($is_super_admin)->runInTransaction(function ($gate) use ($fy, $ptype, $pno, $start, $end, $current_user_id, $closing_steps) {
            $newId = $gate->insert('fin_financial_periods', array(
                'fiscal_year' => $fy,
                'period_type' => $ptype,
                'period_no'   => $pno,
                'start_date'  => $start,
                'end_date'    => $end,
                'state'       => 'planned',
                'created_by'  => $current_user_id,
            ));
            foreach (array_keys($closing_steps) as $step) {
                $gate->insert('fin_closing_items', array(
                    'period_id' => $newId,
                    'step'      => $step,
                    'required'  => 1,
                ));
            }
            return $newId;
        }, 'periods: create period + seed closing items');
    } catch (\App\Core\TenantGateException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            ems_gov_flash_redirect('periods_fin.php', 'الفترة موجودة مسبقا ❌', 'GOV-FAIL-409', ''); exit();
        }
        throw $e;
    }
    ems_gov_redirect("Location: periods_fin.php?pid=$pid&msg=تم+إنشاء+الفترة+وقائمة+إقفالها+✅"); exit();
}

// الفترة المختارة لعرض قائمة الإقفال
$sel_pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;

$page_title = 'إيكوبيشن | إقفال الفترات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main fin-periods-main ems-unified-page-shell">
    <?php
    $header_title = 'إقفال الفترات'; $header_icon = 'fa fa-calendar-check';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إنشاء فترة'); }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف الفترة' => 'g35',
            'الشهر' => 'g36',
            'قيود الفترة' => 'g37',
            'قيود معلقة' => 'g38',
            'مطابقة المخازن' => 'g39',
            'مطابقة الخزينة' => 'g40',
            'الإقفالات التشغيلية الواردة' => 'g41',
            'فروق معالجة' => 'g42',
            'قرار الإقفال' => 'g43',
            'قرار إعادة الفتح' => 'g44',
            'حالة الفترة' => 'g45',
            'المنشئ' => 'g46',
            'تاريخ الإنشاء' => 'g47',
            'المراجع' => 'g48',
            'المعتمد' => 'g49',
            'تاريخ الاعتماد' => 'g50',
            'حالة البيانات' => 'g51',
            'مرجع المصدر' => 'g52',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fina_periods_fin');
        echo ems_w14_grid('emsList_fina_periods_fin', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في التقويم المحاسبي للفترات'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    // UXW-01 ٩: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضيًا
    echo ems_states_bundle('لا فترات مالية منشأة بعد', 'أنشئ فترة بزر «إنشاء فترة» في رأس الشاشة ثم استوف قائمة إقفالها');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <?php echo csrf_field(); ?>
        <div class="card-header"><h5><i class="fas fa-edit"></i> إنشاء فترة مالية</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label for="emsf_262_63abd">السنة المالية <span class="required">*</span></label><input type="number" name="fiscal_year" required aria-label="السنة المالية للفترة" value="<?php echo date('Y'); ?>" id="emsf_262_63abd"></div>
            <div class="form-group"><label for="pt">النوع</label><select name="period_type" id="pt"><option value="month">شهر</option><option value="year">سنة</option></select></div>
            <div class="form-group" id="pnowrap"><label for="emsf_263_737be">رقم الشهر</label><input type="number" name="period_no" min="1" max="12" value="1" id="emsf_263_737be"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-primary"><i class="fas fa-save"></i> إنشاء</button>
            <button type="button" class="btn-secondary" onclick="$('#finForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 class="fin-prd-h5"><i class="fas fa-calendar"></i> الفترات المالية</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables fin-prd-table">
                <thead><tr><th>الإجراءات</th><th>السنة</th><th>النوع</th><th>القيود المنشورة</th><th>إلى</th><th>القيد مسموح</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم المحضر</th>
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الإقفال</th>
              <th class="ems-fn-th" data-fn="1">الوحدات المعتمدة</th>
              <th class="ems-fn-th" data-fn="1">الوحدات المعلقة</th>
              <th class="ems-fn-th" data-fn="1">المستخلصات المصدرة</th>
              <th class="ems-fn-th" data-fn="1">الفواتير المصدرة</th>
              <th class="ems-fn-th" data-fn="1">المسيرات المعتمدة</th>
              <th class="ems-fn-th" data-fn="1">تسويات الموردين</th>
              <th class="ems-fn-th" data-fn="1">المطابقات البنكية</th>
              <th class="ems-fn-th" data-fn="1">بنود لم تحسم</th>
              <th class="ems-fn-th" data-fn="1">شرط الإقفال</th>
              <th class="ems-fn-th" data-fn="1">أقفله</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr></thead>
                <tbody>
                <?php
                $period_rows = fin_gate($is_super_admin)->select('fin_financial_periods', array('orderBy' => 'fiscal_year DESC, period_type ASC, period_no ASC'));
                foreach ($period_rows as $row) {
                    $st = (string)$row['state']; $id = intval($row['id']);
                    $tone = in_array($st, array('open','reopened')) ? 'success' : ($st === 'planned' ? 'secondary' : ($st === 'locked' ? 'dark' : 'primary'));
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_edit) {
                        if (in_array($st, array('planned','reopened'))) echo "<a href='?action=open&pid=$id' class='action-btn edit' title='فتح'><i class='fas fa-lock-open'></i></a>";
                        if ($st === 'open') echo "<a href='?action=soft_close&pid=$id' class='action-btn edit' title='إقفال مرحلي'><i class='fas fa-hourglass-half'></i></a>";
                        if (in_array($st, array('open','soft_closed'))) echo "<a href='?action=close&pid=$id' class='action-btn edit' title='إقفال نهائي' onclick='return confirm(\"إقفال الفترة؟ يتطلب إنجاز بنود الإقفال.\")'><i class='fas fa-flag-checkered'></i></a>";
                        if ($st === 'closed') echo "<a href='?action=lock&pid=$id' class='action-btn delete' title='قفل نهائي' onclick='return confirm(\"قفل نهائي؟ يمنع أي تعديل.\")'><i class='fas fa-lock'></i></a>";
                        if (in_array($st, array('closed','soft_closed'))) echo "<a href='javascript:void(0)' class='action-btn edit' title='فتح استثنائي' onclick='emsReopenPeriod($id)'><i class='fas fa-rotate-left'></i></a>";
                    }
                    echo "<a href='?pid=$id' class='action-btn' title='قائمة الإقفال'><i class='fas fa-list-check'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . intval($row['fiscal_year']) . "</td>";
                    echo "<td>" . ($row['period_type'] === 'year' ? 'سنة' : 'شهر ' . intval($row['period_no'])) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['start_date']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['end_date']) . "</td>";
                    echo "<td>" . ($row['posting_allowed'] ? "<span class='badge badge-success'>نعم</span>" : "<span class='badge badge-secondary'>لا</span>") . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . htmlspecialchars($period_states[$st] ?? $st) . "</span></td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
        </div>

        <?php if ($sel_pid > 0): ?>
        <h5 class="fin-prd-h5-list"><i class="fas fa-list-check"></i> قائمة إقفال الفترة #<?php echo $sel_pid; ?></h5>
        <div class="table-container">
            <table class="alltables fin-prd-table">
                <thead><tr><th>البند</th><th>إلزامي</th><th>الحالة</th><th>الدليل</th><th>من أنجزه ومتى</th><th>الإجراء</th></tr></thead>
                <tbody>
                <?php
                $done = 0; $total = 0;
                $closing_rows = fin_gate($is_super_admin)->select('fin_closing_items', array('where' => array('period_id' => $sel_pid), 'orderBy' => 'id ASC'));
                foreach ($closing_rows as $it) {
                    $total++; if ($it['item_state'] === 'done') $done++;
                    $tone = $it['item_state'] === 'done' ? 'success' : ($it['item_state'] === 'na' ? 'secondary' : 'warn');
                    $lbl = array('pending' => 'معلق', 'done' => 'منجز', 'na' => 'لا ينطبق');
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($closing_steps[$it['step']] ?? $it['step']) . "</td>";
                    echo "<td>" . ($it['required'] ? '✔' : '—') . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . ($lbl[$it['item_state']] ?? $it['item_state']) . "</span></td>";
                    /* ◆ الدليلُ ومَن أنجزه ومتى — معروضةٌ لا مخزَّنةً وحدَها (INJ-0183) */
                    $__ev = trim((string) ($it['note'] ?? ''));
                    echo "<td>" . ($__ev !== '' ? htmlspecialchars($__ev, ENT_QUOTES, 'UTF-8')
                                                : "<span class='fin-prd-dash'>—</span>") . "</td>";
                    $__who = intval($it['done_by'] ?? 0);
                    $__when = trim((string) ($it['done_at'] ?? ''));
                    if ($__who > 0 || $__when !== '') {
                        if (!isset($__doerName)) { $__doerName = array(); }
                        if ($__who > 0 && !isset($__doerName[$__who])) {
                            $__ns = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
                            if ($__ns) {
                                $__ns->bind_param('i', $__who);
                                $__ns->execute();
                                $__nr = $__ns->get_result()->fetch_row();
                                $__ns->close();
                                $__doerName[$__who] = $__nr ? (string) $__nr[0] : ('#' . $__who);
                            }
                        }
                        echo "<td>" . htmlspecialchars(($__who > 0 ? ($__doerName[$__who] ?? ('#' . $__who)) : '—')
                             . ' · ' . ($__when !== '' ? $__when : '—'), ENT_QUOTES, 'UTF-8') . "</td>";
                    } else {
                        echo "<td><span class='fin-prd-dash'>—</span></td>";
                    }
                    echo "<td>";
                    if ($can_edit && $it['item_state'] === 'pending') {
                        /* ◆ نموذجُ POST بدليلٍ إلزاميٍّ — لا رابطَ إنجازٍ عارٍ */
                        echo "<form method='post' class='fin-prd-doneform'>"
                           . (function_exists('csrf_field') ? csrf_field() : '')
                           . "<input type='hidden' name='done_item' value='" . intval($it['id']) . "'>"
                           . "<input type='hidden' name='pid' value='" . intval($sel_pid) . "'>"
                           . "<input type='text' name='evidence_ref' required minlength='3' class='fin-prd-evidence'"
                           . " placeholder='مرجع الدليل' title='مرجع الدليل — إلزامي'>"
                           . "<button type='submit' class='action-btn edit' title='إنجاز'>"
                           . "<i class='fas fa-check'></i></button></form>";
                    }
                    echo "</td></tr>";
                }
                $pct = $total > 0 ? round($done / $total * 100) : 0;
                ?>
                </tbody>
                <tfoot><tr><th colspan="6">نسبة الاكتمال: <span class="badge badge-<?php echo $pct == 100 ? 'success' : 'warn'; ?>"><?php echo $pct; ?>%</span> (<?php echo $done; ?>/<?php echo $total; ?>)</th></tr></tfoot>
            </table>
        </div>
        <?php endif; ?>
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
    // جدولُ العرضِ يهيّئُه المكوّنُ المركزيُّ (assets/js/ui-unification.js)
    $('#toggleForm').on('click', function () { $('#finForm').toggleClass('allforms-visible'); });
    $('#pt').on('change', function () { $('#pnowrap').toggle(this.value === 'month'); });
});
// M-39: الفتحُ الاستثنائي قرارٌ موثَّق — السببُ إلزامٌ قبل الإرسال
window.emsReopenPeriod = function (pid) {
    var reason = window.prompt('الفتح الاستثنائي لفترة مقفلة قرار موثق.\nاكتب سبب الفتح:');
    if (reason === null) { return; }
    reason = reason.trim();
    if (reason === '') { alert('السبب إلزامي — لا فتح بلا سبب مكتوب'); return; }
    window.location = '?action=reopen&pid=' + pid + '&reason=' + encodeURIComponent(reason);
};
</script>
</body>
</html>
