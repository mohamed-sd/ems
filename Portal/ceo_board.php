<?php
/**
 * Portal/ceo_board.php — لوحة المدير التنفيذي (M-00 §8-2 — على جدولها الأصلي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 00 · الإدارة التنفيذية · الأعمدة 16 بترتيب المستند وطبقة
 * الحوكمة بشرائحها. لقطات الفترات في الجدول الأصلي `exec_board_snapshots`
 * (هجرة 2026_11_14 — أُنجز لحاق CMP03_FOLLOWUP) معزولةً بالكيان، والمؤشرات
 * الجامعة الست (④-٦) حيةٌ فوق الجدول من مصادرها لا من لقطاته.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once '../includes/gov_columns.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', '');
    exit();
}

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Portal/ceo_board.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/ceo_board.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح بالكتابة في هذه الشاشة ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$COLS   = array (
  0 => 'الكيان',
  1 => 'الفترة',
  2 => 'العقود النافذة',
  3 => 'قيمة المحفظة',
  4 => 'الإيراد المعترف',
  5 => 'التحصيل',
  6 => 'الذمم المتأخرة',
  7 => 'التدفق المتوقع',
  8 => 'التزامات التمويل',
  9 => 'المعدات العاملة',
  10 => 'نسبة الجاهزية',
  11 => 'الوحدات المعتمدة',
  12 => 'الهامش',
  13 => 'المخاطر المفتوحة',
  14 => 'الاعتمادات المعلَّقة',
  15 => 'آخر تحديث',
);
/* أعمدة الجدول الأصلي بترتيب حقول الفورم f0..f14 */
$DB_FIELDS = array(
    'period', 'active_contracts', 'portfolio_value', 'recognized_revenue',
    'collection', 'overdue_receivables', 'expected_cashflow', 'financing_commitments',
    'working_equipment', 'readiness_pct', 'approved_units', 'margin_pct',
    'open_risks', 'pending_approvals', 'last_updated',
);
/* خريطة عرض: فهرس عمود المستند → عمود القاعدة (null = الكيان) */
$COLDB = array_merge(array(null), $DB_FIELDS);

/* ── الحفظ: فورم الإضافة الموحد → الجدول الأصلي ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'add') {
    $in = array();
    foreach ($DB_FIELDS as $i => $col) { $in[$col] = trim((string) ($_POST['f' . $i] ?? '')); }

    /* ══ INJ-0118 · لا مسارَ إدخالٍ يدويٍّ لمؤشرٍ ماليّ ═══════════════════════════
         نصُّ القبول: «كلُّ رقمٍ في لوحة القمة قابلٌ للنقر ويفتح مصدرَه، **ولا
         يوجد في الشاشة مسارُ إدخالٍ يدويٍّ لأيِّ مؤشرٍ مالي**».
         والمقيسُ قبلَه: كلُّ مؤشراتِ اللوحةِ — الإيرادُ المعترَفُ والتحصيلُ
         والذممُ المتأخرةُ والهامشُ والتزاماتُ التمويل — تُكتب **بأصابعِ
         المستخدمِ** وتُحفظ بحالةِ «معتمد» مباشرة. فرقمٌ يُقرَّر عليه في أعلى
         الهرمِ مصدرُه حقلُ نصٍّ لا دفترُ حسابات.
         ◆ ولا يُحذف الجدولُ: اللقطةُ تبقى **مشتقّةً** من مصادرِها. والمُدخَلُ
           هنا يُرفض برمزٍ محكومٍ ويُسجَّل — فالمحاولةُ نفسُها أثرٌ يُراجَع. */
    /* ◆ INJ-0129 يضمُّ إلى السبعةِ الماليةِ مؤشرَي المخزون: «المخاطر المفتوحة»
         و«الاعتمادات المعلَّقة» — والحارسُ في **المعالج** لا في الفورمِ وحدَه،
         فرفعُ الحقلِ من الصفحةِ لا يمنع POST مصنوعًا بيدٍ أخرى (CS-11). */
    $__FIN_KPI = array('portfolio_value', 'recognized_revenue', 'collection',
        'overdue_receivables', 'expected_cashflow', 'financing_commitments', 'margin_pct',
        'open_risks', 'pending_approvals');
    $__typed = array();
    foreach ($__FIN_KPI as $__k) {
        if (isset($in[$__k]) && trim((string) $in[$__k]) !== '') { $__typed[] = $__k; }
    }
    if ($__typed) {
        require_once __DIR__ . '/../includes/audit_trail.php';
        ems_audit_change($conn, 'governance', 'exec_board_snapshots', 'manual_kpi_refused', 0,
            array(), array('fields' => implode(',', $__typed)),
            array('company_id' => (int) $company_id, 'user_id' => (int) $uid));
        ems_gov_flash_redirect(basename(__FILE__),
            'GOV-KPI-409: مؤشراتُ اللوحةِ تُشتقُّ من مصادرِها ولا تُكتب يدويًّا — '
            . 'المرفوض: ' . implode(' · ', $__typed) . ' ❌',
            'GOV-FAIL-409', 'افتحِ الرقمَ من مصدرِه: الإيرادُ من الدفتر والتحصيلُ من المقبوضات');
        exit();
    }

    $creator = trim((string) ($_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . $uid);
    // الفارغ NULL من هنا لا NULLIF في SQL — خلطُ الترتيبات على اتصال الويب يرفضها
    foreach ($in as $k => $v) { if ($v === '') { $in[$k] = null; } }
    $st = $conn->prepare("INSERT INTO exec_board_snapshots
        (company_id, period, active_contracts, portfolio_value, recognized_revenue,
         collection, overdue_receivables, expected_cashflow, financing_commitments,
         working_equipment, readiness_pct, approved_units, margin_pct,
         open_risks, pending_approvals, last_updated, status, is_seed, created_by, created_by_name)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'معتمد', 0, ?, ?)");
    $st->bind_param('isssssssssssssssis',
        $company_id, $in['period'], $in['active_contracts'], $in['portfolio_value'],
        $in['recognized_revenue'], $in['collection'], $in['overdue_receivables'],
        $in['expected_cashflow'], $in['financing_commitments'], $in['working_equipment'],
        $in['readiness_pct'], $in['approved_units'], $in['margin_pct'],
        $in['open_risks'], $in['pending_approvals'], $in['last_updated'], $uid, $creator);
    $ok = $st->execute();
    $dup = !$ok && $conn->errno === 1062;
    $st->close();
    ems_gov_flash_redirect(basename(__FILE__), $ok ? 'حُفظت اللقطة ✅' : ($dup ? 'لقطة هذه الفترة موجودة — الفترة الواحدة لقطة واحدة ❌' : 'تعذر الحفظ ❌'), 'GOV-OK-200', '');
    exit();
}

/* ── القراءة: لقطات الكيان من الجدول الأصلي ─────────────────────────────── */
$rows = array();
$sql = "SELECT * FROM exec_board_snapshots"
     . ($is_super_admin && $company_id <= 0 ? '' : ' WHERE company_id = ?')
     . ' ORDER BY period DESC, id DESC LIMIT 500';
$st = $conn->prepare($sql);
if (!($is_super_admin && $company_id <= 0)) { $st->bind_param('i', $company_id); }
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
$st->close();

$govCtx = ems_gov_ctx();
$entityName = $govCtx['values']['entity'] ?? '—';

/** قيمة خلية بفهرس عمود المستند — الكيان حي وسائرها من الجدول أو «—» */
/* ══ INJ-0129 — «المؤشرُ قيمةٌ مجمَّعةٌ تُشتق آليًّا» (FRD §٢٦-١ نوع ٨) ═══════
     نصُّ القبول: «بعد تسجيلِ خطرٍ جديدٍ في `risk_register` يجب أن تُظهر لوحةُ
     الرئيسِ الرقمَ الجديدَ بلا أيِّ إدخالٍ يدوي، ولا يوجد في الصفحةِ حقلُ
     إدخالٍ باسم `f12`».
   ── والمقيسُ قبلَه: «المخاطر المفتوحة» حقلُ نصٍّ حرٍّ يكتبه المُدخِل. فرقمٌ
     يُقرَّر عليه في أعلى الهرمِ مصدرُه أصابعُ موظفٍ لا سجلُّ المخاطر.
   ◆ و«المفتوح» **مخزونٌ لا تدفّق**: قيمتُه الصحيحةُ هي عددُ ما هو مفتوحٌ الآن،
     فلا معنى لتجميدِه في لقطةِ فترةٍ — ولذلك يُشتقُّ عند العرضِ لا يُخزَّن.
   ◆ و«الاعتمادات المعلَّقة» عيبُها هو هو: مؤشرٌ مخزونٌ كان يُكتب يدويًّا —
     فأُشتقَّ معه، إذ تركُ توأمِ العيبِ بجوارِ المُصلَحِ يُعيد النمطَ من بابِه.
   ◆ ولا يُحذف العمودان من الجدول: اللقطاتُ القديمةُ تبقى، ويعلوها المشتقُّ
     في العرضِ — فلا تُمحى بيانةٌ ولا يُعرض رقمٌ مهجور. */
$DERIVED_KPI = array();
$__r = $conn->query("SELECT COUNT(*) FROM risk_register
                      WHERE company_id = " . (int) $company_id . "
                        AND state <> 'closed' AND merged_into_id IS NULL");
if ($__r && ($__x = $__r->fetch_row())) { $DERIVED_KPI['open_risks'] = (int) $__x[0]; }
$__r = $conn->query("SELECT COUNT(*) FROM exec_approvals
                      WHERE company_id = " . (int) $company_id . "
                        AND (decision IS NULL OR decision = '')");
if ($__r && ($__x = $__r->fetch_row())) { $DERIVED_KPI['pending_approvals'] = (int) $__x[0]; }

function m00_cell_at($idx, $row, $entityName, $COLDB, $derived = array()) {
    $col = $COLDB[$idx] ?? null;
    if ($col === null) { return $entityName; }
    /* المشتقُّ يعلو المخزَّن — والمصدرُ سجلُّ المخاطرِ لا خليةُ إدخال */
    if (isset($derived[$col])) { return (string) $derived[$col]; }
    $v = isset($row[$col]) ? trim((string) $row[$col]) : '';
    return $v !== '' ? $v : '—';
}

$page_title = 'إيكوبيشن | لوحة المدير التنفيذي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'لوحة المدير التنفيذي';
    $header_icon = 'fa fa-gauge-high';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    echo ems_states_bundle('لا لقطاتَ محفوظةً لهذه الفترة', 'أضف لقطةً بزر «إضافة» أو تحقق من توفرِ السجلات');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }

    /* M-00 ④-٦: المؤشرات الجامعة الست حيةً من مصادرها — فوق جدول اللقطات
     * (عقود · مشاريع · معلَّق أمام القمة · قرارات بلا حسم · متابعات · وقائع §11) */
    $bd = function ($sql) use ($conn) {
        try { $r = $conn->query($sql); $w = $r ? $r->fetch_assoc() : null; return $w ? (int) reset($w) : 0; }
        catch (\Throwable $t) { return 0; }
    };
    $bdCoE = ($is_super_admin && $company_id <= 0) ? '' : ' AND company_id = ' . $company_id;
    $bdContracts = $bd("SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0 {$bdCoE}");
    $bdProjects  = $bd("SELECT COUNT(*) FROM project  WHERE COALESCE(is_deleted,0)=0 {$bdCoE}");
    $bdPending   = $bd("SELECT COUNT(*) FROM exec_approvals
                         WHERE status IN ('مسودة','قيد المراجعة','مؤجل') {$bdCoE}")
                 + $bd("SELECT COUNT(*) FROM requests r JOIN users u ON u.id=r.current_holder_user_id AND u.role='9'
                         WHERE r.status IN ('submitted','routed','in_approval')" . str_replace('company_id', 'r.company_id', $bdCoE));
    $bdOpenDec   = $bd("SELECT COUNT(*) FROM exec_decisions
                         WHERE status IN ('مسودة','قيد الدراسة','قيد المراجعة','قيد الحسم') {$bdCoE}");
    $bdFollow    = $bd("SELECT COUNT(*) FROM work_items WHERE source_type='SRC-10'
                         AND status NOT IN ('closed_accepted','cancelled') {$bdCoE}");
    $bdFacts     = $bd("SELECT COUNT(*) FROM ems_business_events
                         WHERE event_key IN ('contract.signed','project.chartered','exec.decision.made','exec.approval.granted') {$bdCoE}");
    $bdCards = array(
        array('العقود', $bdContracts, 'fa-file-contract'),
        array('المشاريع', $bdProjects, 'fa-diagram-project'),
        array('معلَّق أمام القمة', $bdPending, 'fa-hourglass-half'),
        array('قضايا بلا حسم', $bdOpenDec, 'fa-triangle-exclamation'),
        array('متابعات مفتوحة', $bdFollow, 'fa-bell'),
        array('وقائع §11', $bdFacts, 'fa-bolt'),
    );
    ?>
    <div class="card"><div class="card-body">
        <div class="ems-ceo-kpi-grid">
            <?php foreach ($bdCards as $c): ?>
            <div class="ems-ceo-kpi-card">
                <div class="ems-ceo-kpi-num"><?php echo (int) $c[1]; ?></div>
                <div class="text-muted ems-ceo-kpi-label"><i class="fa <?php echo $c[2]; ?>"></i> <?php echo htmlspecialchars($c[0], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-muted ems-ceo-kpi-note">
            مؤشراتٌ حيةٌ من المصادر (لا من لقطات هذا الجدول) — التفصيل في
            <a href="ceo_reports.php">تقارير الإدارة التنفيذية الثمانية</a>
        </div>
    </div></div>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — لوحة المدير التنفيذي</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1091_a86fb">الفترة</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1091_a86fb"></div>
                <div class="form-group"><label for="emsf_1092_4ec25">العقود النافذة</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_1092_4ec25"></div>
                <div class="form-group"><label for="emsf_1093_3a74f">قيمة المحفظة</label>
                    <input type="text" inputmode="decimal" name="f2" placeholder="0" id="emsf_1093_3a74f"></div>
                <div class="form-group"><label for="emsf_1094_432e7">الإيراد المعترف</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_1094_432e7"></div>
                <div class="form-group"><label for="emsf_1095_372bb">التحصيل</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1095_372bb"></div>
                <div class="form-group"><label for="emsf_1096_0cc85">الذمم المتأخرة</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_1096_0cc85"></div>
                <div class="form-group"><label for="emsf_1097_8436d">التدفق المتوقع</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_1097_8436d"></div>
                <div class="form-group"><label for="emsf_1098_256b5">التزامات التمويل</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_1098_256b5"></div>
                <div class="form-group"><label for="emsf_1099_a5db7">المعدات العاملة</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_1099_a5db7"></div>
                <div class="form-group"><label for="emsf_1100_21f38">نسبة الجاهزية</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0" id="emsf_1100_21f38"></div>
                <div class="form-group"><label for="emsf_1101_07779">الوحدات المعتمدة</label>
                    <input type="text" inputmode="decimal" name="f10" placeholder="0" id="emsf_1101_07779"></div>
                <div class="form-group"><label for="emsf_1102_d1848">الهامش</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0" id="emsf_1102_d1848"></div>
                <!-- INJ-0129 · «المخاطر المفتوحة» و«الاعتمادات المعلَّقة» **مؤشران
                     مشتقّان**: يُقرآن من `risk_register` و`exec_approvals` عند العرض،
                     فلا حقلَ `f12` ولا `f13` في هذه الصفحة. -->
                <div class="form-group"><label>المخاطر المفتوحة</label>
                    <input type="text" value="مشتقٌّ من سجل المخاطر — لا يُكتب" disabled
                           class="form-control" aria-readonly="true" aria-label="المخاطر المفتوحة — مؤشرٌ مشتقٌّ من سجل المخاطر"></div>
                <div class="form-group"><label>الاعتمادات المعلَّقة</label>
                    <input type="text" value="مشتقٌّ من صندوق الاعتماد — لا يُكتب" disabled
                           class="form-control" aria-readonly="true" aria-label="الاعتمادات المعلَّقة — مؤشرٌ مشتقٌّ من صندوق الاعتماد"></div>
                <div class="form-group"><label for="emsf_1105_88267">آخر تحديث</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_1105_88267"></div>
            </div></div>
            <div class="ems-ceo-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="ceo_boardTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>الفترة</th>
            <th>العقود النافذة</th>
            <th>قيمة المحفظة</th>
            <th>الإيراد المعترف</th>
            <th>التحصيل</th>
            <th>الذمم المتأخرة</th>
            <th>التدفق المتوقع</th>
            <th>التزامات التمويل</th>
            <th>المعدات العاملة</th>
            <th>نسبة الجاهزية</th>
            <th>الوحدات المعتمدة</th>
            <th>الهامش</th>
            <th>المخاطر المفتوحة</th>
            <th>الاعتمادات المعلَّقة</th>
            <th>آخر تحديث</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="16" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr<?php echo $r['is_seed'] ? ' data-seed="1"' : ''; ?>>
                    <?php foreach (array_keys($COLS) as $i): $v = m00_cell_at($i, $r, $entityName, $COLDB, $DERIVED_KPI); ?>
                    <td<?php echo $v === '—' ? ' class="ems-gov-empty"' : ''; ?>><?php echo htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

<script>
(function () {
    var btn = document.getElementById('cmp03AddBtn');
    var form = document.getElementById('cmp03AddForm');
    var cancel = document.getElementById('cmp03CancelBtn');
    if (btn && form) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            form.classList.toggle('allforms-visible');
            if (form.classList.contains('allforms-visible')) {
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    if (cancel && form) {
        cancel.addEventListener('click', function () { form.classList.remove('allforms-visible'); });
    }
})();
</script>
