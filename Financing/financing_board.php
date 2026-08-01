<?php
/**
 * Financing/financing_board.php — لوحة إدارة التمويل (FIN-26 · الشاشة 214)
 * ───────────────────────────────────────────────────────────────────────────
 * لوحة الدور المستقل (26) — نمط لوحة أمين المستودع (M-50): أسئلة صباحه:
 * كم ممولًا نشطًا؟ ما العمليات النافذة واستحقاقها القائم؟ ما أقساط الثلاثين
 * يومًا؟ ما الانحرافات المفتوحة؟ — قراءة وقفز بلا أثر.
 * البيانات خلف بوابة المجال المقيَّد (FIN-01 §1.1): بلا منحة فردية نافذة
 * تُعرض القشرة بإعلان الحجب **ولا يُجلب رقم واحد** (fail-closed على البيانات
 * لا على المسكن — فهذه «الرئيسية» يهبط عليها الدور من الدخول).
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once dirname(__DIR__) . '/app/Core/OwnershipDomainGuard.php';

use App\Core\OwnershipDomainGuard;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
$uid = intval($_SESSION['user']['id'] ?? 0);
$is_super = ($role === '-1');
// مسكن الدور 26 — ويفتحه أيضًا من يدخل باب التمويل أصلًا (1 · 19 · السوبر)
if (!$is_super && !in_array($role, array(EMS_ROLE_FINANCING_MGR, '1', '19'), true)) {
    http_response_code(403);
    exit('403 — لوحة إدارة التمويل لدورها');
}
$co = $company_id ?: 4;

$canOwner = $is_super || OwnershipDomainGuard::hasGrant($conn, $co, $uid, OwnershipDomainGuard::PERM_OWNER);
$canTerms = $is_super || OwnershipDomainGuard::hasGrant($conn, $co, $uid, OwnershipDomainGuard::PERM_TERMS);
$granted = $canOwner || $canTerms
    || OwnershipDomainGuard::hasGrant($conn, $co, $uid, OwnershipDomainGuard::PERM_VALUE);

$financiers = $opsActive = $devOpen = 0;
$balances = array();
$due30 = array();
if ($granted) {
    // سطر اطّلاع — فتح اللوحة قراءةً لبيانات المجال المقيَّد
    $st = $conn->prepare("INSERT INTO sensitive_read_log (person_id, element_code, subject_type, subject_id, ip, result) VALUES (?, 'ownership.financing_board', 'screen', 214, ?, 'allowed')");
    $ip = strval($_SERVER['REMOTE_ADDR'] ?? 'cli');
    $st->bind_param('is', $uid, $ip);
    $st->execute();
    $st->close();

    $financiers = intval($conn->query(
        "SELECT COUNT(DISTINCT r.entity_id) c FROM entity_roles r
          WHERE r.role = 'financier' AND (r.valid_to IS NULL OR r.valid_to >= CURDATE())")->fetch_assoc()['c']);
    $opsActive = intval($conn->query(
        "SELECT COUNT(*) c FROM financing_operations WHERE state = 'active'")->fetch_assoc()['c']);
    $devOpen = intval($conn->query(
        "SELECT COUNT(*) c FROM financing_deviations WHERE state <> 'closed'")->fetch_assoc()['c']);

    if ($canTerms) {
        // الاستحقاق القائم بكل عملة — لا تُجمع عملتان في رقم
        $q = $conn->query("SELECT currency, SUM(outstanding_balance) bal FROM financing_operations
                            WHERE state = 'active' GROUP BY currency");
        while ($q && ($b = $q->fetch_assoc())) { $balances[] = $b; }
        $q = $conn->query("SELECT i.currency, COUNT(*) n, SUM(i.amount_total) total
                             FROM financing_installments i
                             JOIN financing_operations o ON o.op_id = i.op_id AND o.state = 'active'
                            WHERE i.state <> 'paid' AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                            GROUP BY i.currency");
        while ($q && ($b = $q->fetch_assoc())) { $due30[] = $b; }
    }
}

$page_title = 'إيكوبيشن | لوحة إدارة التمويل';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة إدارة التمويل'; $header_icon = 'fa fa-money-bill-trend-up';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('لوحة الدور المستقل (26): الممولون النشطون والعمليات النافذة واستحقاقها القائم '
        . 'بكل عملة وأقساط الثلاثين يومًا والانحرافات المفتوحة — قراءة وقفز إلى موضع الفعل. '
        . 'البيانات خلف بوابة المجال المقيَّد: بلا منحة فردية لا يُجلب رقم، وكل فتح بسطر اطّلاع.',
        array('الاستحقاق والأقساط لمن يملك رؤية الشروط', 'الممول الجديد يُنشأ كيانًا في الحوكمة'));
    ?>

    <?php if (!$granted): ?>
    <div class="card"><div class="card-body">
        <div class="alert alert-warning" style="margin:0">
            <strong>باب التمويل خلف بوابة المجال المقيَّد (FIN-01 §1.1).</strong><br>
            لا منحة <code>ownership.*</code> نافذة لحسابك — فلا يُجلب من بيانات الملكية والتمويل شيء
            (fail-closed). المنح فردي بقرار من شاشة «منح المجال المقيَّد» في باب الحوكمة (الأدوار 1 · 19).
        </div>
    </div></div>
    <?php else: ?>
    <div class="card"><div class="card-body" style="display:flex;gap:14px;flex-wrap:wrap">
        <a class="badge badge-secondary" style="font-size:15px;padding:8px 14px;text-decoration:none"
           href="financiers_registry.php">الممولون النشطون: <strong><?php echo $financiers; ?></strong> ▸</a>
        <a class="badge badge-secondary" style="font-size:15px;padding:8px 14px;text-decoration:none"
           href="financiers_registry.php">عمليات نافذة: <strong><?php echo $opsActive; ?></strong> ▸</a>
        <div class="badge <?php echo $devOpen ? 'badge-danger' : 'badge-success'; ?>" style="font-size:15px;padding:8px 14px">
            انحرافات مفتوحة: <strong><?php echo $devOpen; ?></strong></div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-scale-balanced"></i>
        الاستحقاق القائم وأقساط الثلاثين يومًا — بكل عملة<?php echo $canTerms ? '' : ' (خلف صلاحية الشروط)'; ?></h5></div>
    <div class="card-body">
        <?php if (!$canTerms): ?>
            <em>محجوب — يلزم <code>ownership.finance_terms</code> (المنح فردي لا بالعضوية في الإدارة)</em>
        <?php elseif (empty($balances) && empty($due30)): ems_state_empty('لا عمليات نافذة ولا أقساط مستحقة — البنية حية بلا حركة بعد'); else: ?>
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
            <thead><tr><th>العملة</th><th>الرصيد القائم</th><th>أقساط 30 يومًا (عددًا)</th><th>أقساط 30 يومًا (قيمةً)</th></tr></thead>
            <tbody>
            <?php
            $cur = array();
            foreach ($balances as $b) { $cur[$b['currency']]['bal'] = (float) $b['bal']; }
            foreach ($due30 as $d) { $cur[$d['currency']]['n'] = intval($d['n']); $cur[$d['currency']]['total'] = (float) $d['total']; }
            foreach ($cur as $c => $v): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c); ?></strong></td>
                    <td><?php echo number_format($v['bal'] ?? 0, 2); ?></td>
                    <td><?php echo intval($v['n'] ?? 0); ?></td>
                    <td><?php echo number_format($v['total'] ?? 0, 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <p style="margin-top:10px">
            <a href="financiers_registry.php" class="btn-save">سجل الممولين ▸</a>
            <a href="financing_operation_new.php" class="btn-save">+ إنشاء عملية تمويل (النموذج أولًا)</a>
        </p>
    </div></div>
    <?php endif; ?>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
