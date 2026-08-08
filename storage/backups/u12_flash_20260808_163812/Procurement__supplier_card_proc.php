<?php
/**
 * Procurement/supplier_card_proc.php — بطاقةُ مورد المشتريات (M-49 · الشاشة 198)
 * ───────────────────────────────────────────────────────────────────────────
 * UX-09 §5: البطاقةُ بتبويباتها السبعة — كان `suppliers_proc.php` جدولًا
 * مسطَّحًا. قراءةٌ من مصادرها الحية بروابط الأصل — لا نسخَ ولا تخزين.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once '../includes/permissions_helper.php';   // proc_page_perms تستدعي check_page_permissions — كانت غائبةً فتُسقط الشاشة 500
require_once __DIR__ . '/proc_helpers.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$ctx = proc_ctx();
$company_id = $ctx['company_id'];
$is_super_admin = $ctx['is_super'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
$perms = proc_page_perms($conn, 'Procurement/supplier_card_proc.php', $is_super_admin);
if (!$perms['can_view']) { header("Location: ../main/dashboard.php?msg=" . rawurlencode('لا صلاحيةَ عرضٍ لبطاقة المورد ❌')); exit(); }

$sid = intval($_GET['id'] ?? 0);
$sup = $sid > 0 ? proc_gate($is_super_admin)->selectOne('proc_supplier',
    array('where' => array('id' => $sid), 'includeDeleted' => true)) : null;
$tab = in_array(strval($_GET['tab'] ?? '1'), array('1','2','3','4','5','6','7','8'), true)
     ? strval($_GET['tab'] ?? '1') : '1';
$co = intval($company_id);

$TABS = array('1' => 'البيانات', '2' => 'أوامرُ الشراء', '3' => 'الاستلامات',
              '4' => 'الفواتيرُ والمطابقة', '5' => 'العهد', '6' => 'الأصناف', '7' => 'السجل',
              '8' => 'كشفُ الحساب');

$page_title = 'إيكوبيشن | بطاقة مورد المشتريات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'بطاقة مورد المشتريات'; $header_icon = 'fa fa-id-card-clip';
    $header_actions = array();
    $header_back = array('href' => 'suppliers_proc.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'الموردون');
    include('../includes/page_header.php');
    ems_screen_about('بطاقةُ المورد الواحدة بتبويباتها السبعة — بدل الجدول المسطّح: '
        . 'كلُّ تبويبٍ قراءةٌ حيةٌ من جدول مالكه برابط أصله.', array());
    ?>

    <?php if (!$sup): ems_state_empty('اختر موردًا من القائمة', 'إلى الموردين', 'suppliers_proc.php'); ?>
    <?php else: ?>
    <div class="card"><div class="card-body" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
        <strong style="font-size:1.1rem"><?php echo htmlspecialchars((string)$sup['name']); ?></strong>
        <span style="margin-inline-start:auto">
        <?php foreach ($TABS as $tk => $tl): ?>
            <a class="btn btn-sm" style="border:1px solid #ddd;border-radius:6px;padding:4px 10px;margin:0 2px;<?php
                echo $tk === $tab ? 'background:#e2b93b;font-weight:800' : ''; ?>"
               href="?id=<?php echo $sid; ?>&tab=<?php echo $tk; ?>"><?php echo $tl; ?></a>
        <?php endforeach; ?></span>
    </div></div>

    <div class="card"><div class="card-body">
    <?php
    switch ($tab) {
        case '1':
            echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%"><tbody>';
            foreach (array('name' => 'الاسم', 'phone' => 'الهاتف', 'email' => 'البريد',
                           'address' => 'العنوان', 'tax_number' => 'الرقم الضريبي',
                           'commercial_registration' => 'السجل التجاري') as $k => $lbl) {
                if (array_key_exists($k, $sup)) {
                    echo '<tr><th>' . $lbl . '</th><td>' . htmlspecialchars((string)($sup[$k] ?? '—')) . '</td></tr>';
                }
            }
            echo '</tbody></table></div>';
            break;
        case '2':
            $rows = array();
            $r = $conn->query("SELECT id, code, state, currency, total_amount, received_pct,
                                      expected_delivery_date, created_at
                                 FROM proc_order WHERE company_id={$co} AND supplier_id={$sid}
                                  AND COALESCE(is_deleted,0)=0 ORDER BY id DESC LIMIT 100");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا أوامرَ لهذا المورد', 'أمرٌ جديد', 'orders_proc.php'); break; }
            echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
               . '<thead><tr><th>الكود</th><th>الحالة</th><th>الإجمالي</th><th>استلم٪</th><th>موعد التسليم</th><th></th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead><tbody>';
            foreach ($rows as $x) {
                echo '<tr><td>' . htmlspecialchars((string)$x['code']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['state']) . '</td>'
                   . '<td>' . htmlspecialchars($x['total_amount'] . ' ' . $x['currency']) . '</td>'
                   . '<td>' . htmlspecialchars((string)($x['received_pct'] ?? '—')) . '</td>'
                   . '<td>' . htmlspecialchars((string)($x['expected_delivery_date'] ?? '—')) . '</td>'
                   . '<td><a href="orders_proc.php?edit_id=' . intval($x['id']) . '">افتح ▸</a></td></tr>';
            }
            echo '</tbody></table></div>';
            break;
        case '3':
            $rows = array();
            $r = $conn->query("SELECT rl.id, rc.code, rl.item_name, rl.qty, rl.created_at
                                 FROM proc_receipt_line rl
                                 JOIN proc_receipt_custody rc ON rc.id = rl.custody_id
                                WHERE rl.company_id={$co} AND rc.supplier_id={$sid}
                                ORDER BY rl.id DESC LIMIT 100");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا استلاماتٍ بعدُ'); break; }
            echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
               . '<thead><tr><th>عهدة الاستلام</th><th>الصنف</th><th>الكمية</th><th>التاريخ</th></tr></thead><tbody>';
            foreach ($rows as $x) {
                echo '<tr><td>' . htmlspecialchars((string)$x['code']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['item_name']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['qty']) . '</td>'
                   . '<td><small>' . htmlspecialchars((string)$x['created_at']) . '</small></td></tr>';
            }
            echo '</tbody></table></div>';
            break;
        case '4':
            $rows = array();
            $r = $conn->query("SELECT id, code, invoice_no, invoice_date, invoice_amount,
                                      invoice_diff, match_state
                                 FROM proc_order WHERE company_id={$co} AND supplier_id={$sid}
                                  AND invoice_no IS NOT NULL ORDER BY id DESC LIMIT 100");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا فواتيرَ مسجَّلةً بعدُ'); break; }
            echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
               . '<thead><tr><th>الأمر</th><th>الفاتورة</th><th>مبلغها</th><th>الفرق</th><th>المطابقة</th></tr></thead><tbody>';
            foreach ($rows as $x) {
                echo '<tr><td>' . htmlspecialchars((string)$x['code']) . '</td>'
                   . '<td>' . htmlspecialchars($x['invoice_no'] . ' · ' . $x['invoice_date']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['invoice_amount']) . '</td>'
                   . '<td>' . htmlspecialchars((string)($x['invoice_diff'] ?? '0')) . '</td>'
                   . '<td>' . htmlspecialchars((string)($x['match_state'] ?? '—')) . '</td></tr>';
            }
            echo '</tbody></table></div>';
            break;
        case '5':
            $rows = array();
            $r = $conn->query("SELECT rc.id, rc.code, rc.holder_name, rc.receipt_date,
                                      rc.receipt_location, rc.state
                                 FROM proc_receipt_custody rc
                                WHERE rc.company_id={$co} AND rc.supplier_id={$sid}
                                  AND COALESCE(rc.is_deleted,0)=0
                                ORDER BY rc.id DESC LIMIT 100");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا عهدَ استلامٍ لهذا المورد'); break; }
            echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
               . '<thead><tr><th>الكود</th><th>المستلم</th><th>التاريخ</th><th>الموقع</th><th>الحالة</th></tr></thead><tbody>';
            foreach ($rows as $x) {
                echo '<tr><td>' . htmlspecialchars((string)$x['code']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['holder_name']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['receipt_date']) . '</td>'
                   . '<td>' . htmlspecialchars((string)($x['receipt_location'] ?? '—')) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['state']) . '</td></tr>';
            }
            echo '</tbody></table></div>';
            break;
        case '6':
            $rows = array();
            $r = $conn->query("SELECT ol.item_name, COUNT(*) n, ROUND(SUM(ol.qty),2) qty
                                 FROM proc_order_line ol JOIN proc_order o ON o.id = ol.order_id
                                WHERE ol.company_id={$co} AND o.supplier_id={$sid}
                                GROUP BY ol.item_name ORDER BY n DESC LIMIT 100");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا أصنافَ مورَّدةً بعدُ'); break; }
            echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
               . '<thead><tr><th>الصنف</th><th>مرات التوريد</th><th>إجمالي الكمية</th></tr></thead><tbody>';
            foreach ($rows as $x) {
                echo '<tr><td>' . htmlspecialchars((string)$x['item_name']) . '</td>'
                   . '<td>' . intval($x['n']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['qty']) . '</td></tr>';
            }
            echo '</tbody></table></div>';
            break;
        case '8':
            // كشفُ حساب مورد المشتريات (UX-09 §5.3): الذمّةُ كانت تُقيَّد ولا شاشةَ
            // تعرضها للدور — الكشفُ من fin_dues بهوية proc_supplier حصرًا
            // (لا suppliers — سجلّان بمعرّفاتٍ متصادمة)
            $rows = array();
            $r = $conn->query("SELECT d.id, d.amount, d.currency, d.period_ref, d.settlement_state,
                                      d.direction, d.created_at
                                 FROM fin_dues d
                                WHERE d.company_id={$co} AND d.party_type='proc_supplier'
                                  AND d.party_ref={$sid} AND d.due_type='purchase'
                                ORDER BY d.id DESC LIMIT 200");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا ذممَ مقيَّدةً لهذا المورد — الذمّةُ تُفتح بالمطابقة الثلاثية', 'إلى المطابقة', 'po_match.php'); break; }
            $tot = array();   // إجمالي المفتوح لكل عملة
            foreach ($rows as $x) {
                if ((string) $x['settlement_state'] === 'pending') {
                    $cur = (string) $x['currency'];
                    $tot[$cur] = ($tot[$cur] ?? 0) + (float) $x['amount'];
                }
            }
            echo '<p style="font-weight:800">الرصيدُ المفتوح (بانتظار التسوية): '
               . ($tot ? implode(' · ', array_map(function ($c, $v) { return number_format($v, 2) . ' ' . $c; }, array_keys($tot), $tot)) : 'صفر')
               . '</p>';
            echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
               . '<thead><tr><th>الذمّة</th><th>المرجع</th><th>المبلغ</th><th>الاتجاه</th><th>حالة التسوية</th><th>تاريخ القيد</th></tr></thead><tbody>';
            foreach ($rows as $x) {
                $oid_ref = (strpos((string) $x['period_ref'], 'PO-') === 0) ? intval(substr((string) $x['period_ref'], 3)) : 0;
                echo '<tr><td>#' . intval($x['id']) . '</td>'
                   . '<td>' . ($oid_ref ? ('<a href="orders_proc.php?edit_id=' . $oid_ref . '">' . htmlspecialchars((string) $x['period_ref']) . '</a>')
                                        : htmlspecialchars((string) $x['period_ref'])) . '</td>'
                   . '<td>' . htmlspecialchars(number_format((float) $x['amount'], 2) . ' ' . $x['currency']) . '</td>'
                   . '<td>' . ((string) $x['direction'] === 'credit' ? 'دائن (له)' : 'مدين (عليه)') . '</td>'
                   . '<td>' . htmlspecialchars((string) $x['settlement_state']) . '</td>'
                   . '<td><small>' . htmlspecialchars((string) $x['created_at']) . '</small></td></tr>';
            }
            echo '</tbody></table></div>';
            break;
        case '7':
            $rows = array();
            $r = $conn->query("SELECT action_type, screen_name, created_at FROM activity_logs
                                WHERE company_id={$co} AND record_id={$sid}
                                  AND screen_name LIKE '%proc%' ORDER BY id DESC LIMIT 50");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا سجلَّ نشاطٍ محفوظًا لهذا المورد'); break; }
            echo '<ul>';
            foreach ($rows as $x) {
                echo '<li><small>' . htmlspecialchars($x['created_at'] . ' — ' . $x['action_type']
                    . ' (' . $x['screen_name'] . ')') . '</small></li>';
            }
            echo '</ul>';
            break;
    }
    ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
