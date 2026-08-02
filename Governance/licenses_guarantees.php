<?php
/**
 * Governance/licenses_guarantees.php — التراخيص والكفالات (LEG-01 §5 · §8-④ · الشاشة 208)
 * ───────────────────────────────────────────────────────────────────────────
 * باب الحوكمة (DEC-01 ②): التراخيص والكفالات بتواريخ انتهائها ملوَّنة ·
 * **الصادرة والواردة مفصولتان** (التزام محتمل مقابل حق محتمل) · وتجديد برفع
 * مستند — فالكفالة المنتهية بلا تجديد تُسقط حقًّا أو تعرِّض لمطالبة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$role = strval($_SESSION['user']['role'] ?? '');
// الكتابة للإدارة العليا والمالية العليا (1 · 19 · السوبر) — وإدارة التمويل (26)
// قراءةً فقط (FIN-26: ملكية الشاشة للحوكمة والدور 26 يطالعها — DEC-01 ②)
$gov_write = ($role === '-1' || in_array($role, array('1', '19'), true));
if (!$gov_write && $role !== EMS_ROLE_FINANCING_MGR) {
    http_response_code(403);
    exit('403 — باب الحوكمة خلف صلاحيته');
}

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$gov_write) {
    http_response_code(403);
    exit('403 — الدور قارئ هنا: الكتابة لملّاك الشاشة (1 · 19)');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = strval($_POST['op'] ?? '');
    if ($op === 'license') {
        $eid = intval($_POST['entity_id'] ?? 0);
        $type = trim(strval($_POST['lic_type'] ?? ''));
        $issuer = trim(strval($_POST['issuer'] ?? ''));
        $no = trim(strval($_POST['lic_no'] ?? ''));
        $expiry = strval($_POST['expiry_date'] ?? '');
        $alert = intval($_POST['alert_days'] ?? 30);
        if ($eid <= 0 || $type === '' || $expiry === '') { $err = 'الكيان والنوع وتاريخ الانتهاء إلزامية'; }
        else {
            $st = $conn->prepare("INSERT INTO entity_licenses (entity_id, lic_type, issuer, lic_no, expiry_date, alert_days, state) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $st->bind_param('issssi', $eid, $type, $issuer, $no, $expiry, $alert);
            if ($st->execute()) { $msg = 'سُجّل الترخيص بتنبيهه قبل ' . $alert . ' يومًا'; } else { $err = $st->error; }
            $st->close();
        }
    } elseif ($op === 'guarantee') {
        $dir = strval($_POST['direction'] ?? '');
        $eid = intval($_POST['entity_id'] ?? 0);
        $cp = intval($_POST['counterparty_id'] ?? 0);
        $type = trim(strval($_POST['gtee_type'] ?? ''));
        $bank = trim(strval($_POST['bank'] ?? ''));
        $amount = floatval($_POST['amount'] ?? 0);
        $cur = trim(strval($_POST['currency'] ?? 'USD'));
        $expiry = strval($_POST['expiry_date'] ?? '');
        $doc = trim(strval($_POST['doc_ref'] ?? ''));
        if (!in_array($dir, array('issued', 'received'), true) || $eid <= 0 || $amount <= 0 || $expiry === '' || $doc === '') {
            $err = 'الاتجاه والكيان والقيمة والانتهاء والمستند إلزامية — لا كفالة بلا مستند';
        } else {
            $st = $conn->prepare("INSERT INTO guarantees (direction, entity_id, counterparty_id, gtee_type, bank, amount, currency, expiry_date, doc_ref, state) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $st->bind_param('siissdsss', $dir, $eid, $cp, $type, $bank, $amount, $cur, $expiry, $doc);
            if ($st->execute()) { $msg = 'سُجّلت الكفالة (' . ($dir === 'issued' ? 'صادرة — التزام محتمل' : 'واردة — حق محتمل') . ')'; } else { $err = $st->error; }
            $st->close();
        }
    } elseif ($op === 'renew') {
        $lid = intval($_POST['lic_id'] ?? 0);
        $newExp = strval($_POST['new_expiry'] ?? '');
        $doc = trim(strval($_POST['doc_ref'] ?? ''));
        if ($lid <= 0 || $newExp === '' || $doc === '') { $err = 'التجديد برفع مستند وتاريخ جديد — إلزاميان'; }
        else {
            $st = $conn->prepare("UPDATE entity_licenses SET expiry_date = ?, file_ref = ?, state = 'active' WHERE lic_id = ?");
            $st->bind_param('ssi', $newExp, $doc, $lid);
            $st->execute();
            $st->close();
            $msg = 'جُدّد الترخيص #' . $lid . ' حتى ' . $newExp;
        }
    }
}

$lics = $conn->query(
    "SELECT l.*, e.legal_name, DATEDIFF(l.expiry_date, CURDATE()) days_left
       FROM entity_licenses l JOIN legal_entities e ON e.entity_id = l.entity_id
      ORDER BY l.expiry_date"
)->fetch_all(MYSQLI_ASSOC);
$gtees = $conn->query(
    "SELECT g.*, e.legal_name, c.legal_name counterparty, DATEDIFF(g.expiry_date, CURDATE()) days_left
       FROM guarantees g
       JOIN legal_entities e ON e.entity_id = g.entity_id
       LEFT JOIN legal_entities c ON c.entity_id = g.counterparty_id
      ORDER BY g.direction, g.expiry_date"
)->fetch_all(MYSQLI_ASSOC);
$entities = $conn->query("SELECT entity_id, legal_name FROM legal_entities WHERE state = 'active' ORDER BY is_tenant DESC, legal_name")->fetch_all(MYSQLI_ASSOC);

function expiry_badge($daysLeft) {
    $d = intval($daysLeft);
    if ($d < 0) { return '<span class="badge badge-danger">منتهية منذ ' . abs($d) . ' يوم</span>'; }
    if ($d <= 30) { return '<span class="badge badge-warning">تنتهي خلال ' . $d . ' يومًا</span>'; }
    return '<span class="badge badge-success">' . $d . ' يومًا متبقية</span>';
}

$page_title = 'إيكوبيشن | التراخيص والكفالات';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'التراخيص والكفالات'; $header_icon = 'fa fa-certificate';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('التراخيص بتواريخ انتهائها ملوَّنة، والكفالات الصادرة (التزام محتمل خارج الميزانية) '
        . 'مفصولة عن الواردة (حق محتمل) — ومفصولة كلتاهما عن المحتجَز النقدي. ترخيص منتهٍ ونحن نعمل '
        . 'به مخالفة لا تُكتشف إلا من الخارج — فالتنبيه قبل الانتهاء بمدة معرَّفة.',
        array('الأحمر منتهٍ والبرتقالي ≤30 يومًا', 'التجديد برفع مستند'));
    if ($msg !== '') { echo '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>'; }
    ?>
    <div class="card"><div class="card-body">
        <h4>التراخيص والسجلات</h4>
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
        <thead><tr><th>الكيان</th><th>النوع</th><th>الجهة</th><th>الرقم</th><th>الانتهاء</th><th>تجديد</th></tr></thead><tbody>
        <?php foreach ($lics as $l): ?>
        <tr>
            <td><?php echo htmlspecialchars($l['legal_name']); ?></td>
            <td><?php echo htmlspecialchars($l['lic_type']); ?></td>
            <td><?php echo htmlspecialchars((string) $l['issuer']); ?></td>
            <td><?php echo htmlspecialchars((string) $l['lic_no']); ?></td>
            <td><?php echo htmlspecialchars($l['expiry_date']) . ' ' . expiry_badge($l['days_left']); ?></td>
            <td>
                <?php if ($gov_write): ?>
                <form method="post" style="display:flex;gap:4px">
                    <input type="hidden" name="op" value="renew">
                    <input type="hidden" name="lic_id" value="<?php echo intval($l['lic_id']); ?>">
                    <input type="date" name="new_expiry" required>
                    <input type="text" name="doc_ref" placeholder="مستند التجديد" required style="width:120px">
                    <button class="btn-save" type="submit">تجديد</button>
                </form>
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>

    <div class="card"><div class="card-body">
        <h4>الكفالات وخطابات الضمان — الصادرة ثم الواردة</h4>
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
        <thead><tr><th>الاتجاه</th><th>عن/إلى</th><th>الطرف المقابل</th><th>النوع</th><th>البنك</th><th>القيمة</th><th>الانتهاء</th><th>الحالة</th></tr></thead><tbody>
        <?php foreach ($gtees as $g): ?>
        <tr>
            <td><strong><?php echo $g['direction'] === 'issued' ? 'صادرة — التزام محتمل' : 'واردة — حق محتمل'; ?></strong></td>
            <td><?php echo htmlspecialchars($g['legal_name']); ?></td>
            <td><?php echo htmlspecialchars((string) ($g['counterparty'] ?: '—')); ?></td>
            <td><?php echo htmlspecialchars((string) $g['gtee_type']); ?></td>
            <td><?php echo htmlspecialchars((string) $g['bank']); ?></td>
            <td><?php echo number_format((float) $g['amount'], 2) . ' ' . htmlspecialchars($g['currency']); ?></td>
            <td><?php echo htmlspecialchars($g['expiry_date']) . ' ' . expiry_badge($g['days_left']); ?></td>
            <td><span class="badge badge-<?php echo $g['state'] === 'active' ? 'success' : ($g['state'] === 'called' ? 'danger' : 'secondary'); ?>"><?php echo htmlspecialchars($g['state']); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>

    <?php if ($gov_write): // القارئ (26) لا تُصيَّر له نماذج التسجيل أصلًا — منع بنيوي لا زر معطَّل ?>
    <div class="row" style="display:flex;gap:14px;flex-wrap:wrap">
        <div class="card" style="flex:1;min-width:340px"><div class="card-body">
            <h4>ترخيص جديد</h4>
            <form method="post" class="ems-form" style="display:grid;gap:8px">
                <input type="hidden" name="op" value="license">
                <select name="entity_id" required>
                    <option value="">— الكيان *</option>
                    <?php foreach ($entities as $e): ?>
                    <option value="<?php echo intval($e['entity_id']); ?>"><?php echo htmlspecialchars($e['legal_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="lic_type" placeholder="النوع (سجل تجاري · رخصة نشاط…) *" required>
                <input type="text" name="issuer" placeholder="الجهة المصدرة">
                <input type="text" name="lic_no" placeholder="الرقم">
                <input type="date" name="expiry_date" required>
                <input type="number" name="alert_days" value="30" title="التنبيه قبل الانتهاء بأيام">
                <button class="btn-save" type="submit">تسجيل</button>
            </form>
        </div></div>
        <div class="card" style="flex:1;min-width:340px"><div class="card-body">
            <h4>كفالة / خطاب ضمان</h4>
            <form method="post" class="ems-form" style="display:grid;gap:8px">
                <input type="hidden" name="op" value="guarantee">
                <select name="direction" required>
                    <option value="issued">صادرة منا (التزام محتمل)</option>
                    <option value="received">واردة إلينا (حق محتمل)</option>
                </select>
                <select name="entity_id" required>
                    <option value="">— كياننا *</option>
                    <?php foreach ($entities as $e): ?>
                    <option value="<?php echo intval($e['entity_id']); ?>"><?php echo htmlspecialchars($e['legal_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="counterparty_id">
                    <option value="0">— الطرف المقابل</option>
                    <?php foreach ($entities as $e): ?>
                    <option value="<?php echo intval($e['entity_id']); ?>"><?php echo htmlspecialchars($e['legal_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="gtee_type" placeholder="النوع (حسن تنفيذ · دفعة مقدمة…)">
                <input type="text" name="bank" placeholder="البنك المصدر">
                <input type="number" step="0.01" name="amount" placeholder="القيمة *" required>
                <input type="text" name="currency" value="USD">
                <input type="date" name="expiry_date" required>
                <input type="text" name="doc_ref" placeholder="مرجع المستند *" required>
                <button class="btn-save" type="submit">تسجيل</button>
            </form>
        </div></div>
    </div>
    <?php endif; ?>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
