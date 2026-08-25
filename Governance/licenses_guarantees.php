<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
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

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/../includes/screen_contract.php';

$role = strval($_SESSION['user']['role'] ?? '');
// الكتابة للإدارة العليا والمالية العليا (1 · 19 · السوبر) — وإدارة التمويل (26)
// قراءةً فقط (FIN-26: ملكية الشاشة للحوكمة والدور 26 يطالعها — DEC-01 ②)
$gov_write = ($role === '-1' || in_array($role, array('1', '19'), true));
if (!$gov_write && $role !== EMS_ROLE_FINANCING_MGR) {
    ems_gov_flash_redirect('../main/dashboard.php', 'باب الحوكمة خلف صلاحيته ❌', 'GOV-PERM-403', 'اطلب المنحة من مدير الصلاحيات إن كانت ضمن عملك');
}

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$gov_write) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الدور قارئ هنا: الكتابة لملاك الشاشة (1 · 19) ❌', 'GOV-PERM-403', 'اطلب المنحة من مدير الصلاحيات إن كانت ضمن عملك');
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
            if ($st->execute()) { $msg = 'سجل الترخيص بتنبيهه قبل ' . $alert . ' يوما'; } else { $err = $st->error; }
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
            if ($st->execute()) { $msg = 'سجلت الكفالة (' . ($dir === 'issued' ? 'صادرة — التزام محتمل' : 'واردة — حق محتمل') . ')'; } else { $err = $st->error; }
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
            $msg = 'جدد الترخيص #' . $lid . ' حتى ' . $newExp;
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
    if ($d <= 30) { return '<span class="badge badge-warning">تنتهي خلال ' . $d . ' يوما</span>'; }
    return '<span class="badge badge-success">' . $d . ' يوما متبقية</span>';
}

$page_title = 'إيكوبيشن | التراخيص والكفالات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'التراخيص والكفالات'; $header_icon = 'fa fa-certificate';
    $header_actions = array();
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا تراخيص ولا كفالات مسجلة بعد', 'سجل أول ترخيص أو كفالة من نماذج التسجيل أسفل الشاشة');
    ems_screen_about('التراخيص بتواريخ انتهائها ملونة، والكفالات الصادرة (التزام محتمل خارج الميزانية) '
        . 'مفصولة عن الواردة (حق محتمل) — ومفصولة كلتاهما عن المحتجز النقدي. ترخيص منته ونحن نعمل '
        . 'به مخالفة لا تكتشف إلا من الخارج — فالتنبيه قبل الانتهاء بمدة معرفة.',
        array('الأحمر منته والبرتقالي ≤30 يوما', 'التجديد برفع مستند'));
    if ($msg !== '') { echo '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>'; }
    ?>
    <div class="card"><div class="card-body">
        <h4>التراخيص والسجلات</h4>
        <div class="table-container"><table class="alltables display gov-lic-table" data-no-dt="1">
        <thead><tr><th>الكيان</th><th>نوع المستند</th><th>الجهة المصدرة</th><th>رقم السجل</th><th>تاريخ الانتهاء</th><th>تجديد</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">الرقم أو المرجع</th>
              <th class="ems-fn-th" data-fn="1">المستفيد</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الإصدار</th>
              <th class="ems-fn-th" data-fn="1">المدة المتبقية</th>
              <th class="ems-fn-th" data-fn="1">شرط التمديد التلقائي</th>
              <th class="ems-fn-th" data-fn="1">الرسوم</th>
              <th class="ems-fn-th" data-fn="1">حالة الرد أو المصادرة</th>
              <th class="ems-fn-th" data-fn="1">المسؤول</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead><tbody>
        <?php foreach ($lics as $l): ?>
        <tr>
            <td><?php echo htmlspecialchars($l['legal_name']); ?></td>
            <td><?php echo htmlspecialchars($l['lic_type']); ?></td>
            <td><?php echo htmlspecialchars((string) $l['issuer']); ?></td>
            <td><?php echo htmlspecialchars((string) $l['lic_no']); ?></td>
            <td><?php echo htmlspecialchars($l['expiry_date']) . ' ' . expiry_badge($l['days_left']); ?></td>
            <td>
                <?php if ($gov_write): ?>
                <form method="post" class="gov-lic-renew">
        <?= csrf_field() ?>
                    <input type="hidden" name="op" value="renew">
                    <input type="hidden" name="lic_id" value="<?php echo intval($l['lic_id']); ?>">
                    <input type="date" name="new_expiry" aria-label="تاريخ الانتهاء الجديد بعد التجديد" required>
                    <input type="text" name="doc_ref" class="gov-lic-doc" placeholder="مستند التجديد" required aria-label="مستند التجديد">
                    <button class="btn-primary" type="submit">تجديد</button>
                </form>
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>

    <div class="card"><div class="card-body">
        <h4>الكفالات وخطابات الضمان — الصادرة ثم الواردة</h4>
        <div class="table-container"><table class="alltables display gov-lic-table" data-no-dt="1">
        <thead><tr><th>الاتجاه</th><th>عن/إلى</th><th>الطرف المقابل</th><th>نوع الكفالة</th><th>البنك</th><th>القيمة</th><th>الانتهاء</th><th>الحالة</th></tr></thead><tbody>
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
    <div class="row gov-lic-row">
        <div class="card gov-lic-col"><div class="card-body">
            <h4>ترخيص جديد</h4>
            <form method="post" class="ems-form gov-lic-grid">
        <?= csrf_field() ?>
                <input type="hidden" name="op" value="license">
                <select name="entity_id" aria-label="الكيان القانوني صاحب الترخيص" required>
                    <option value="">— الكيان *</option>
                    <?php foreach ($entities as $e): ?>
                    <option value="<?php echo intval($e['entity_id']); ?>"><?php echo htmlspecialchars($e['legal_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="lic_type" placeholder="النوع (سجل تجاري · رخصة نشاط…) *" required aria-label="النوع (سجل تجاري · رخصة نشاط…)">
                <input type="text" name="issuer" placeholder="الجهة المصدرة" aria-label="الجهة المصدرة">
                <input type="text" name="lic_no" placeholder="الرقم" aria-label="الرقم">
                <input type="date" name="expiry_date" aria-label="تاريخ انتهاء الترخيص" required>
                <input type="number" name="alert_days" value="30" title="التنبيه قبل الانتهاء بأيام" aria-label="التنبيه قبل الانتهاء بأيام">
                <button class="btn-primary" type="submit">تسجيل</button>
            </form>
        </div></div>
        <div class="card gov-lic-col"><div class="card-body">
            <h4>كفالة / خطاب ضمان</h4>
            <form method="post" class="ems-form gov-lic-grid">
        <?= csrf_field() ?>
                <input type="hidden" name="op" value="guarantee">
                <select name="direction" aria-label="اتجاه الكفالة: صادرة منا أو واردة إلينا" required>
                    <option value="issued">صادرة منا (التزام محتمل)</option>
                    <option value="received">واردة إلينا (حق محتمل)</option>
                </select>
                <select name="entity_id" aria-label="كياننا الضامن في الكفالة" required>
                    <option value="">— كياننا *</option>
                    <?php foreach ($entities as $e): ?>
                    <option value="<?php echo intval($e['entity_id']); ?>"><?php echo htmlspecialchars($e['legal_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="counterparty_id" aria-label="الطرف المقابل في الكفالة">
                    <option value="0">— الطرف المقابل</option>
                    <?php foreach ($entities as $e): ?>
                    <option value="<?php echo intval($e['entity_id']); ?>"><?php echo htmlspecialchars($e['legal_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="gtee_type" placeholder="النوع (حسن تنفيذ · دفعة مقدمة…)" aria-label="النوع (حسن تنفيذ · دفعة مقدمة…)">
                <input type="text" name="bank" placeholder="البنك المصدر" aria-label="البنك المصدر">
                <input type="number" step="0.01" name="amount" placeholder="القيمة *" required aria-label="القيمة">
                <input type="text" name="currency" aria-label="عملة قيمة الكفالة" value="USD">
                <input type="date" name="expiry_date" aria-label="تاريخ انتهاء الكفالة" required>
                <input type="text" name="doc_ref" placeholder="مرجع المستند *" required aria-label="مرجع المستند">
                <button class="btn-primary" type="submit">تسجيل</button>
            </form>
        </div></div>
    </div>
    <?php endif; ?>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
