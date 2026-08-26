<?php
/**
 * Procurement/stock_proc.php — المخزون التشغيلي (عرض فقط) — §9 / §15.7.
 * الرصيد محسوب بالتجميع من proc_stock_move (لا حقل رصيد مخزَّن): المتاح = الوارد + المرتجع − المصروف.
 * شاشة جديدة مستقلة للقراءة فقط — لا كتابة على أي جدول.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/proc_helpers.php';

$ctx             = proc_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/stock_proc.php', $is_super_admin);
if (!$perms['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المخزون ❌', 'GOV-PERM-403', '');
    exit();
}

// K9-M1 ذيل: القراءة التجميعية عبر قناة scopedQuery (عقد البوابة §10)

$page_title = 'إيكوبيشن | المخزون';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main proc-stock-main ems-unified-page-shell">
    <?php
    $header_title = 'المخزون';
    $header_icon  = 'fa fa-warehouse';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // TKT-15 · زر الإبلاغ السياقي — المخزون والاستلام (§2-③)
    require_once __DIR__ . '/../includes/report_button.php';
    ems_report_button(array('screen' => 'warehouse'));
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا حركات مخزون في الفترة والمخزن المختارين',
        'وسع الفترة أو اختر «كل المخازن»، فالأرصدة هنا محسوبة من الحركات لا من رقم مخزن');
    ?>

    <div class="success-message is-success proc-stk-note">
        <i class="fas fa-circle-info"></i>
        القاعدة المحورية: <b>المتاح = الرصيد المادي − المحجوز</b>. الأرصدة هنا محسوبة من حركات المخزون الفعلية (الوارد + المرتجع − المصروف)، لا من رقم مخزن.
    </div>

    <?php
    /* ── INJ-0561 · فلترُ الفترةِ والمخزنِ **من الخادم** ────────────────────────
         لم يكن في أيِّ شاشةِ مخازنَ فلترُ فترةٍ رغم أن كلَّ حركةٍ تحمل `moved_at`
         — فالرصيدُ المعروضُ مجموعُ الدهرِ كلِّه، ولا سبيلَ لسؤالِ «ما رصيدُ هذا
         الشهر؟». والترشيحُ هنا **في `WHERE` لا في المتصفح**: يعود المطابقُ وحدَه.
         وعدّادُ الفلاترِ وزرُّ التفريغِ يبنيهما شريطُ العُدَّةِ آليًّا (INJ-0497). */
    $__pFrom = isset($_GET['from']) && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $_GET['from']) ? $_GET['from'] : '';
    $__pTo   = isset($_GET['to'])   && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $_GET['to'])   ? $_GET['to']   : '';
    $__pWh   = isset($_GET['wh']) ? (int) $_GET['wh'] : 0;
    $__pWhere = '';
    if ($__pFrom !== '') { $__pWhere .= " AND m.moved_at >= '" . $conn->real_escape_string($__pFrom) . " 00:00:00'"; }
    if ($__pTo   !== '') { $__pWhere .= " AND m.moved_at <= '" . $conn->real_escape_string($__pTo)   . " 23:59:59'"; }
    if ($__pWh   >  0)   { $__pWhere .= ' AND m.warehouse_id = ' . $__pWh; }
    $__whList = array();
    $__wr = $conn->prepare('SELECT id, name FROM proc_warehouse WHERE company_id = ? ORDER BY name');
    $__wr->bind_param('i', $company_id);
    $__wr->execute();
    $__wres = $__wr->get_result();
    while ($__wx = $__wres->fetch_assoc()) { $__whList[] = $__wx; }
    $__wr->close();
    ?>
    <form method="get" class="filter" data-ems-period="1">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-calendar-days"></i></span> فترة الحركات والمخزن</div>
        <div class="filter-body">
            <div class="filter-field"><label for="stkFrom">من تاريخ</label>
                <input type="date" id="stkFrom" name="from" class="form-control" value="<?php echo htmlspecialchars($__pFrom); ?>"></div>
            <div class="filter-field"><label for="stkTo">إلى تاريخ</label>
                <input type="date" id="stkTo" name="to" class="form-control" value="<?php echo htmlspecialchars($__pTo); ?>"></div>
            <div class="filter-field"><label for="stkWh">المخزن</label>
                <select id="stkWh" name="wh" class="form-control">
                    <option value="">— كل المخازن —</option>
                    <?php foreach ($__whList as $__w): ?>
                    <option value="<?php echo (int) $__w['id']; ?>"<?php echo ((int) $__w['id'] === $__pWh ? ' selected' : ''); ?>><?php
                        echo htmlspecialchars($__w['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="filter-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-search"></i> تطبيق</button>
            </div>
        </div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables proc-stk-table"
                   data-scroll-x="1" data-state-save="false">
                <thead><tr>
                    <th>رقم الصنف</th><th>المخزن</th><th>الوارد</th><th>المرتجع</th><th>المصروف</th><th>المتاح</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">اسم الصنف</th>
                    <th class="ems-fn-th" data-fn="1">الفئة</th>
                    <th class="ems-fn-th" data-fn="1">الوحدة</th>
                    <th class="ems-fn-th" data-fn="1">الموقع الداخلي</th>
                    <th class="ems-fn-th" data-fn="1">الرصيد المادي</th>
                    <th class="ems-fn-th" data-fn="1">المحجوز</th>
                    <th class="ems-fn-th" data-fn="1">قيد الشراء</th>
                    <th class="ems-fn-th" data-fn="1">قيد التحويل</th>
                    <th class="ems-fn-th" data-fn="1">في العهدة</th>
                    <th class="ems-fn-th" data-fn="1">حد إعادة الطلب</th>
                    <th class="ems-fn-th" data-fn="1">الحد الأقصى</th>
                    <th class="ems-fn-th" data-fn="1">قطعة حرجة</th>
                    <th class="ems-fn-th" data-fn="1">متوسط التكلفة</th>
                    <th class="ems-fn-th" data-fn="1">قيمة الرصيد</th>
                    <th class="ems-fn-th" data-fn="1">آخر حركة</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                    <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                    </tr></thead>
                <tbody>
                    <?php
                    $stock_rows = proc_gate($is_super_admin)->scopedQuery(
                        array(
                            'scope'  => array('m' => 'proc_stock_move'),
                            'enrich' => array('it' => 'proc_item', 'w' => 'proc_warehouse'),
                        ),
                        "SELECT
                             COALESCE(it.code, '—') AS item_code,
                             COALESCE(it.name, CONCAT('#', m.item_id)) AS item_name,
                             COALESCE(it.category, '—') AS category,
                             COALESCE(it.uom, '—') AS uom,
                             it.min_qty, it.max_qty, it.is_critical, it.avg_cost,
                             COALESCE(w.name, '—') AS wh_name,
                             SUM(CASE WHEN m.move_type='استلام' THEN m.qty ELSE 0 END) AS q_in,
                             SUM(CASE WHEN m.move_type='إرجاع' THEN m.qty ELSE 0 END) AS q_ret,
                             SUM(CASE WHEN m.move_type='صرف' THEN m.qty ELSE 0 END) AS q_out,
                             MAX(m.moved_at) AS last_move
                         FROM proc_stock_move m
                         LEFT JOIN proc_item it ON it.id = m.item_id
                         LEFT JOIN proc_warehouse w ON w.id = m.warehouse_id
                         WHERE {TENANT_SCOPE}" . $__pWhere . "
                         GROUP BY m.item_id, m.warehouse_id, it.code, it.name, it.category, it.uom,
                                  it.min_qty, it.max_qty, it.is_critical, it.avg_cost, w.name
                         ORDER BY item_name ASC"
                    );
                    // اسمُ الكيان (عرضًا في عمود الحوكمة)
                    $stk_entity = '—';
                    if ($company_id > 0) {
                        $stq = $conn->prepare('SELECT name FROM admin_companies WHERE id = ? LIMIT 1');
                        $stq->bind_param('i', $company_id);
                        $stq->execute();
                        $ser = $stq->get_result()->fetch_assoc();
                        if ($ser) { $stk_entity = (string) $ser['name']; }
                    }
                    // ① الصفُّ كاملُ الأعمدة من مصادره — «متوسط التكلفة» و«قيمة الرصيد»
                    // صارا حيَّين (كانا حشوَ ui-unification حتى ربط المصدر)
                    { foreach ($stock_rows as $row) {
                        $in = (float)$row['q_in']; $ret = (float)$row['q_ret']; $out = (float)$row['q_out'];
                        $avail = $in + $ret - $out;
                        $avg = ($row['avg_cost'] !== null) ? (float)$row['avg_cost'] : null;
                        $td = function ($v) { echo "<td>" . htmlspecialchars((string)$v) . "</td>"; };
                        echo "<tr>";
                        $td($row['item_code']);
                        $td($row['wh_name']);
                        $td(number_format($in, 2));
                        $td(number_format($ret, 2));
                        $td(number_format($out, 2));
                        echo "<td><span class='action-btn" . ($avail <= 0 ? ' proc-stk-neg' : '') . "'>" . htmlspecialchars(number_format($avail, 2)) . "</span></td>";
                        $td($row['item_name']);                                       // اسم الصنف
                        $td($row['category']);                                        // الفئة
                        $td($row['uom']);                                             // الوحدة
                        $td('—');                                                     // الموقع الداخلي
                        $td(number_format($avail, 2));                                // الرصيد المادي
                        $td('—'); $td('—'); $td('—'); $td('—');                       // المحجوز · قيد الشراء · قيد التحويل · في العهدة
                        $td($row['min_qty'] !== null ? number_format((float)$row['min_qty'], 2) : '—');
                        $td($row['max_qty'] !== null ? number_format((float)$row['max_qty'], 2) : '—');
                        $td(intval($row['is_critical']) === 1 ? 'حرجة' : '—');
                        $td($avg !== null ? number_format($avg, 2) : '—');            // متوسط التكلفة
                        $td($avg !== null ? number_format($avail * $avg, 2) : '—');   // قيمة الرصيد
                        $td($row['last_move'] !== null ? (string)$row['last_move'] : '—');
                        $td($stk_entity);                                             // الكيان
                        for ($gv = 0; $gv < 10; $gv++) { $td('—'); }                  // بقية الحوكمة حتى ربط مصادرها
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<?php /* ── RPR-W09 · WH-06 ────────────────────────────────────────────────────
     **الرصيدُ كان رقمًا واحدًا بلا بُعدِ حالة**: حبّةُ `WH-06` «صنفٌ × مخزنٌ ×
     **حالة**»، وجمعُ التالفِ والمحجورِ مع الصالحِ في رقمٍ واحدٍ يجعل «المتاحَ»
     كذبًا **يُصرَف عليه**. وأُضيف `proc_stock_state` سطرًا لكلِّ حالةٍ **مشتقًّا
     من الحركات** بقاعدةِ اشتقاقٍ مكتوبةٍ في الصفِّ نفسِه، والبوّابةُ `W9-17`
     تعيد اشتقاقَه وتقارنه بالمخزَّن. */ ?>
<?php
$__w9st = array();
try {
    $__g9 = ems_tenant_db();
    $__w9st = $__g9->select('proc_stock_state', array('orderBy' => 'item_id, warehouse_id, state_key', 'limit' => 500));
} catch (\Throwable $__e9) { error_log('stock_proc w9: ' . $__e9->getMessage()); }
?>
<div class="main ems-unified-page-shell">
  <h3 class="ems-section-title">أرصدة المخزون بحالاتها</h3>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>الصنف</th><th>المخزن</th><th>الحالة</th><th>الكمية</th><th>قاعدة الاشتقاق</th><th>حسبت في</th></tr></thead>
    <tbody>
    <?php if ($__w9st): foreach ($__w9st as $__s9): ?>
      <tr>
        <td><?= intval($__s9['item_id']) ?></td>
        <td><?= intval($__s9['warehouse_id']) ?></td>
        <td><?= htmlspecialchars((string) $__s9['state_key'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(number_format((float) $__s9['qty'], 3), ENT_QUOTES, 'UTF-8') ?></td>
        <td><small><?= htmlspecialchars((string) $__s9['derive_rule'], ENT_QUOTES, 'UTF-8') ?></small></td>
        <td><?= htmlspecialchars((string) $__s9['computed_at'], ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="6">لا أسطر رصيد بحالاتها بعد. تشتق عند أول حركة إدخال أو صرف</td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
</div>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<!-- UXW-01 ⑤: التهيئةُ المحليةُ حُذفت — المكوّنُ المركزيُّ (ui-unification.js) يلتقط
     الجدولَ آليًّا، والسلوكُ محفوظٌ بسماتِ data-scroll-x و data-state-save على الوسم. -->
</body>
</html>
