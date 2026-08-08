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
require_once '../includes/gov_columns.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'غير مصرح', 'GOV-INFO-200', '');
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
    http_response_code(403);
    exit('غير مصرح بالكتابة في هذه الشاشة');
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
    header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode(
        $ok ? 'حُفظت اللقطة ✅' : ($dup ? 'لقطة هذه الفترة موجودة — الفترة الواحدة لقطة واحدة ❌' : 'تعذر الحفظ ❌')));
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
function m00_cell_at($idx, $row, $entityName, $COLDB) {
    $col = $COLDB[$idx] ?? null;
    if ($col === null) { return $entityName; }
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
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
            <?php foreach ($bdCards as $c): ?>
            <div style="border:1px solid var(--ems-border,#e5e5e5);border-radius:10px;padding:10px;text-align:center">
                <div style="font-size:20px;font-weight:700"><?php echo (int) $c[1]; ?></div>
                <div class="text-muted" style="font-size:12px"><i class="fa <?php echo $c[2]; ?>"></i> <?php echo htmlspecialchars($c[0], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-muted" style="margin-top:6px;font-size:12px">
            مؤشراتٌ حيةٌ من المصادر (لا من لقطات هذا الجدول) — التفصيل في
            <a href="ceo_reports.php">تقارير الإدارة التنفيذية الثمانية</a>
        </div>
    </div></div>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — لوحة المدير التنفيذي</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>الفترة</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>العقود النافذة</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>قيمة المحفظة</label>
                    <input type="text" inputmode="decimal" name="f2" placeholder="0"></div>
                <div class="form-group"><label>الإيراد المعترف</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>التحصيل</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>الذمم المتأخرة</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>التدفق المتوقع</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>التزامات التمويل</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>المعدات العاملة</label>
                    <input type="text" name="f8" maxlength="190"></div>
                <div class="form-group"><label>نسبة الجاهزية</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0"></div>
                <div class="form-group"><label>الوحدات المعتمدة</label>
                    <input type="text" inputmode="decimal" name="f10" placeholder="0"></div>
                <div class="form-group"><label>الهامش</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0"></div>
                <div class="form-group"><label>المخاطر المفتوحة</label>
                    <input type="text" name="f12" maxlength="190"></div>
                <div class="form-group"><label>الاعتمادات المعلَّقة</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>آخر تحديث</label>
                    <input type="text" name="f14" maxlength="190"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
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
                    <?php foreach (array_keys($COLS) as $i): $v = m00_cell_at($i, $r, $entityName, $COLDB); ?>
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
