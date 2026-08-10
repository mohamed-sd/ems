<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-717 · SCN-719
/**
 * Governance/activation_patterns.php — أنماط التفعيل (LEG-01 §7 · §8-⑤ · الشاشة 209)
 * ───────────────────────────────────────────────────────────────────────────
 * باب الحوكمة (DEC-01 ②): شبكة (عنصر × كيان/عقد) بأعلام التفعيل —
 * **معاينة الأثر قبل الحفظ** وسبب لكل تعطيل لعنصر حاكم. الافتراض النمط ①
 * (كله مطفأ)، والعقد يغلب الكيان، والترقية بلا هجرة بيانات.
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
require_once dirname(__DIR__) . '/app/Core/EntityGovernanceService.php';

$role = strval($_SESSION['user']['role'] ?? '');
if ($role !== '-1' && !in_array($role, array('1', '19'), true)) {
    ems_gov_flash_redirect('../main/dashboard.php', 'باب الحوكمة خلف صلاحيته ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$uid = intval($_SESSION['user']['id'] ?? 0);

/** العناصر الحاكمة وأثر كل تفعيل — تُعرض معاينةً قبل الحفظ */
$ELEMENTS = array(
    'external_accounts' => array('حسابات الأطراف الخارجية', 'النمط ③: العميل يرى مستخلصاته ويعتمد والمورد يعتمد وحداته — بنطاق معزول'),
    'signing_caps'      => array('التفويض بالسقوف', 'النمط ④: كل اعتماد يُفحص تفويضه وسقفه — وبلا تفويض ساري 403'),
    'joint_signing'     => array('التوقيع المشترك', 'النمط ④: مستندات كبيرة بتوقيعين متقابلين موثَّقين'),
    'guarantees'        => array('الكفالات بمتابعتها', 'النمط ④: تنبيهات انتهاء الكفالات وحالات الرد والمصادرة'),
    'licenses'          => array('التراخيص بتنبيهاتها', 'مسح يومي وتنبيه قبل الانتهاء وإسقاط صلاحية آلي'),
    'internal_parties'  => array('الكيانات الداخلية أطرافًا', 'النمط ②: عقد بين كيانين داخليين بمستحق متبادل مسجَّل'),
);

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $element = strval($_POST['element_code'] ?? '');
    $scopeType = strval($_POST['scope_type'] ?? '');
    $scopeId = intval($_POST['scope_id'] ?? 0);
    $enable = intval($_POST['enable'] ?? 0) === 1;
    $reason = trim(strval($_POST['reason'] ?? ''));
    if (!isset($ELEMENTS[$element]) || !in_array($scopeType, array('entity', 'contract'), true) || $scopeId <= 0) {
        $err = 'العنصر والنطاق من القوائم المحكومة';
    } elseif (!$enable && $reason === '') {
        $err = 'سبب إلزامي لأي تعطيل لعنصر حاكم — التعطيل قرار لا نقرة';
    } else {
        $st = $conn->prepare(
            'INSERT INTO governance_flags (element_code, scope_type, scope_id, enabled, reason, set_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), reason = VALUES(reason), set_by = VALUES(set_by), set_at = NOW()');
        $en = $enable ? 1 : 0;
        $st->bind_param('ssiisi', $element, $scopeType, $scopeId, $en, $reason, $uid);
        if ($st->execute()) {
            $msg = ($enable ? 'فُعّل' : 'عُطّل') . ' «' . $ELEMENTS[$element][0] . '» على ' . ($scopeType === 'contract' ? 'العقد' : 'الكيان') . ' #' . $scopeId
                 . ' — والترقية بين الأنماط بلا هجرة بيانات';
        } else { $err = $st->error; }
        $st->close();
    }
}

$flags = $conn->query(
    "SELECT f.*, e.legal_name FROM governance_flags f
       LEFT JOIN legal_entities e ON f.scope_type = 'entity' AND e.entity_id = f.scope_id
      ORDER BY f.element_code, f.scope_type, f.scope_id"
)->fetch_all(MYSQLI_ASSOC);
$entities = $conn->query("SELECT entity_id, legal_name FROM legal_entities WHERE state = 'active' ORDER BY is_tenant DESC, legal_name")->fetch_all(MYSQLI_ASSOC);

$page_title = 'إيكوبيشن | أنماط التفعيل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'أنماط التفعيل'; $header_icon = 'fa fa-toggle-on';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('النظام يوفّر البنية كاملة وأنت تقرر ما تفعّله: الافتراض النمط ① (داخلي محض — '
        . 'كله مطفأ)، وكل عنصر بعلَم مستقل على الكيان والعقد معًا والعقد يغلب. عقد بالنمط ① وآخر '
        . 'بالنمط ④ في الشركة نفسها — والعناصر غير المفعَّلة لا تُصيَّر ولا تُطلب ولا تعطِّل.',
        array('راجع الأثر قبل الحفظ', 'التعطيل بسبب إلزامي'));
    if ($msg !== '') { echo '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>'; }
    ?>
    <div class="card"><div class="card-body">
        <h4>الأعلام النافذة (ما لم يُضبط فالافتراض: مطفأ — النمط ①)</h4>
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
        <thead><tr><th>العنصر</th><th>النطاق</th><th>الحالة</th><th>سبب النمط</th><th>آخر ضبط</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">كود النمط</th>
              <th class="ems-fn-th" data-fn="1">الكيان أو العقد</th>
              <th class="ems-fn-th" data-fn="1">نمط التفعيل</th>
              <th class="ems-fn-th" data-fn="1">العناصر المفعَّلة</th>
              <th class="ems-fn-th" data-fn="1">العناصر المطفأة</th>
              <th class="ems-fn-th" data-fn="1">تاريخ التفعيل</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الترقية المتوقع</th>
              <th class="ems-fn-th" data-fn="1">شرط الترقية</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr></thead><tbody>
        <?php foreach ($flags as $f): $el = $ELEMENTS[$f['element_code']] ?? array($f['element_code'], ''); ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($el[0]); ?></strong><br><small><?php echo htmlspecialchars($f['element_code']); ?></small></td>
            <td><?php echo ($f['scope_type'] === 'contract' ? 'العقد #' . intval($f['scope_id']) : htmlspecialchars((string) ($f['legal_name'] ?: ('الكيان #' . $f['scope_id'])))); ?></td>
            <td><span class="badge badge-<?php echo intval($f['enabled']) === 1 ? 'success' : 'secondary'; ?>"><?php echo intval($f['enabled']) === 1 ? 'مفعَّل' : 'مطفأ'; ?></span></td>
            <td><small><?php echo htmlspecialchars((string) $f['reason']); ?></small></td>
            <td><small><?php echo htmlspecialchars((string) $f['set_at']); ?></small></td>
        </tr>
        <?php endforeach; if (empty($flags)): ?>
        <tr><td colspan="5">لا أعلام مضبوطة — النظام كله على النمط ① (داخلي محض)</td></tr>
        <?php endif; ?>
        </tbody></table></div>
    </div></div>

    <div class="card"><div class="card-body">
        <h4>ضبط علم — بمعاينة الأثر قبل الحفظ</h4>
        <form method="post" class="ems-form" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px"
              onsubmit="var el=this.element_code.value, en=this.enable.value==='1';
                        var fx={<?php foreach ($ELEMENTS as $k => $v) { echo "'" . $k . "':'" . htmlspecialchars($v[1], ENT_QUOTES) . "',"; } ?>};
                        return confirm('معاينة الأثر قبل الحفظ:\n' + (fx[el]||'') + '\n\n' + (en?'تفعيل':'تعطيل — بسبب موثَّق') + '. أتؤكد؟');">
            <select name="element_code" required>
                <?php foreach ($ELEMENTS as $k => $v): ?>
                <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v[0]); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="scope_type" required>
                <option value="entity">على كيان</option>
                <option value="contract">على عقد (يغلب الكيان)</option>
            </select>
            <input type="number" name="scope_id" placeholder="رقم الكيان أو العقد *" required
                   title="الكيانات: <?php foreach (array_slice($entities, 0, 6) as $e) { echo '#' . intval($e['entity_id']) . ' ' . htmlspecialchars($e['legal_name']) . ' · '; } ?>">
            <select name="enable">
                <option value="1">تفعيل</option>
                <option value="0">تعطيل (بسبب)</option>
            </select>
            <input type="text" name="reason" placeholder="السبب — إلزامي للتعطيل" style="grid-column:span 2">
            <button class="btn-save" type="submit" style="grid-column:span 3">حفظ العلم</button>
        </form>
    </div></div>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
