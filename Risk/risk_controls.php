<?php
/**
 * Risk/risk_controls.php — الضوابط والضوابط الحرجة (M-16 · ورقة 26 · RK-07)
 * الضابط لا يُحتسب إلا بدليل، والحرج بحقوله الخمسة ومتحقق مستقل ≠ المالك.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$canWrite = $RISK_FULL && (!empty($__pp['can_add']) || !empty($__pp['can_edit']) || $is_super_admin);
$rows = array();
$r = $conn->query("SELECT rc.*, u.name owner_name, v.name verifier_name
                     FROM risk_controls rc
                     LEFT JOIN users u ON u.id = rc.owner_user_id
                     LEFT JOIN users v ON v.id = rc.verifier_user_id
                    WHERE rc.company_id = {$company_id} AND rc.active = 1
                    ORDER BY rc.is_critical DESC, rc.id DESC LIMIT 800");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }
$criticalN = 0; $unprovenN = 0;
foreach ($rows as $x) {
    if ((int) $x['is_critical'] === 1) { $criticalN++; }
    if ($x['effectiveness'] === 'غير مثبت') { $unprovenN++; }
}

$page_title = 'إيكوبيشن | سجل الضوابط';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'الضوابط والضوابط الحرجة';
    $header_icon = 'fas fa-shield-halved';
    $header_actions = $canWrite ? array(array('id' => 'ctlNewBtn', 'class' => 'add-btn', 'icon' => 'fas fa-plus', 'label' => 'ضابط جديد')) : array();
    $header_back = array();
    $header_context = array('الإجمالي' => count($rows) . ' ضابط', 'الحرجة' => $criticalN, 'غير المثبتة' => $unprovenN);
    include('../includes/page_header.php');
    ems_screen_about(
        'وجود الضابط لا يخفض درجة الخطر — يلزم دليل تنفيذ وتحقق دوري (RK-07). '
        . 'الحرج قليل ومحدَّد: حدث عالي العواقب ومعيار أداء وطريقة تحقق ومتحقق مستقل وإجراء فشل.',
        array('لا يتحقق مالك الضابط من نفسه — الحارس يرفضه برمز'));
    ?>

    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped" style="width:100%">
            <thead><tr><th>الرمز</th><th>الاسم</th><th>النوع</th><th>المالك</th><th>التكرار</th>
                <th>الفعالية</th><th>آخر تحقق</th><th>التحقق التالي</th><th>حرج</th><th>المتحقق المستقل</th>
                <th>دليل/تحقق</th></tr></thead>
            <tbody><?php foreach ($rows as $c): ?>
            <tr>
                <td><?php echo htmlspecialchars($c['control_code']); ?></td>
                <td><?php echo htmlspecialchars($c['name_ar']); ?></td>
                <td><?php echo $c['ctype']; ?></td>
                <td><?php echo htmlspecialchars((string) $c['owner_name']); ?></td>
                <td><?php echo $c['frequency']; ?></td>
                <td><span class="badge badge-<?php echo $c['effectiveness'] === 'فعال' ? 'success' : ($c['effectiveness'] === 'غير فعال' ? 'danger' : 'secondary'); ?>">
                    <?php echo $c['effectiveness']; ?></span></td>
                <td><?php echo htmlspecialchars((string) $c['last_verified_at'] ?: '—'); ?></td>
                <td><?php echo htmlspecialchars((string) $c['next_verify_due'] ?: '—'); ?></td>
                <td><?php echo (int) $c['is_critical'] === 1 ? '<span class="badge badge-danger">حرج</span>' : '—'; ?></td>
                <td><?php echo htmlspecialchars((string) $c['verifier_name'] ?: '—'); ?></td>
                <td style="min-width:260px">
                    <button class="btn btn-sm btn-outline-dark ctlEvid" data-id="<?php echo (int) $c['id']; ?>">دليل تنفيذ</button>
                    <?php if ($canWrite): ?>
                    <button class="btn btn-sm btn-outline-success ctlVerify" data-id="<?php echo (int) $c['id']; ?>">تحقق</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; if (empty($rows)): ?>
            <tr><td colspan="11" class="text-muted">لا ضوابط بعد</td></tr>
            <?php endif; ?></tbody>
        </table>
    </div></div>

    <?php if ($canWrite): ?>
    <div class="card" id="ctlNewCard" style="display:none;margin-top:16px"><div class="card-body">
        <h6>ضابط جديد — الحرج بحقوله الخمسة كاملة</h6>
        <form id="ctlNewForm" class="allforms">
            <div class="row">
                <div class="col-md-4"><label>الاسم *<input name="name_ar" class="form-control" required></label></div>
                <div class="col-md-2"><label>النوع<select name="ctype" class="form-control"><option>وقائي</option><option>كاشف</option><option>تصحيحي</option></select></label></div>
                <div class="col-md-2"><label>المالك (user) *<input name="owner_user_id" type="number" class="form-control" required></label></div>
                <div class="col-md-2"><label>التكرار<select name="frequency" class="form-control">
                    <option>يومي</option><option>كل وردية</option><option>أسبوعي</option><option>شهري</option><option>عند الحدث</option></select></label></div>
                <div class="col-md-2"><label>حرج؟<select name="is_critical" class="form-control"><option value="0">لا</option><option value="1">نعم</option></select></label></div>
                <div class="col-md-6"><label>العملية التي ينفَّذ فيها<input name="process_ref" class="form-control"></label></div>
                <div class="col-md-6"><label>دليل التنفيذ المطلوب *<input name="evidence_spec" class="form-control" required></label></div>
                <div class="col-md-4"><label>الحدث عالي العواقب (حرج)<input name="hico_event" class="form-control"></label></div>
                <div class="col-md-4"><label>معيار الأداء (حرج)<input name="perf_criterion" class="form-control"></label></div>
                <div class="col-md-4"><label>طريقة التحقق (حرج)<input name="verify_method" class="form-control"></label></div>
                <div class="col-md-4"><label>المتحقق المستقل user (حرج)<input name="verifier_user_id" type="number" class="form-control"></label></div>
                <div class="col-md-8"><label>ماذا يحدث عند فشله (حرج)<input name="fail_action" class="form-control"></label></div>
            </div>
            <button class="ems-btn-primary" type="submit">تسجيل الضابط</button>
            <span id="ctlNewMsg"></span>
        </form>
    </div></div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function post(d, cb) {
        var fd = new FormData();
        Object.keys(d).forEach(function (k) { fd.append(k, d[k]); });
        if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
        fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(cb);
    }
    var nb = document.getElementById('ctlNewBtn');
    if (nb) { nb.addEventListener('click', function () {
        var c = document.getElementById('ctlNewCard');
        c.style.display = c.style.display === 'none' ? '' : 'none';
    }); }
    var nf = document.getElementById('ctlNewForm');
    if (nf) { nf.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var d = { do: 'control_create' };
        new FormData(nf).forEach(function (v, k) { d[k] = v; });
        post(d, function (j) {
            document.getElementById('ctlNewMsg').textContent = j.ok ? '✔ ' + (j.control_code || '') : '✘ ' + (j.code || '') + ': ' + (j.msg || '');
            if (j.ok) { setTimeout(function () { location.reload(); }, 700); }
        });
    }); }
    document.querySelectorAll('.ctlEvid').forEach(function (b) {
        b.addEventListener('click', function () {
            var txt = prompt('دليل التنفيذ (نص مثبت):');
            if (!txt) { return; }
            post({ do: 'control_evidence', control_id: b.dataset.id, evidence_text: txt }, function (j) {
                alert(j.ok ? 'سُجل الدليل' : (j.code || '') + ': ' + (j.msg || ''));
            });
        });
    });
    document.querySelectorAll('.ctlVerify').forEach(function (b) {
        b.addEventListener('click', function () {
            var res = prompt('النتيجة: فعال | فعال جزئيا | غير فعال');
            if (!res) { return; }
            var txt = prompt('شاهد التحقق:');
            post({ do: 'control_verify', control_id: b.dataset.id, result: res, evidence_text: txt || '' }, function (j) {
                if (j.ok) { location.reload(); } else { alert((j.code || '') + ': ' + (j.msg || '')); }
            });
        });
    });
});
</script>
</body>
</html>
