<?php
/**
 * Risk/risk_controls.php — الضوابط والضوابط الحرجة (M-16 · ورقة 26 · RK-07)
 * الضابط لا يُحتسب إلا بدليل، والحرج بحقوله الخمسة ومتحقق مستقل ≠ المالك.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/_risk_views.php';
ems_shell_axes($__pp);

// CM-09/CM-10 (§6-2): ٢٤ عمودًا لا تُختزل — والعلاجُ مناظرُ لا حذف
$view = risk_current_view('risk_controls');

$canWrite = $RISK_FULL && (!empty($__pp['can_add']) || !empty($__pp['can_edit']) || $is_super_admin);
$rows = array();
$r = $conn->query("SELECT rc.*, u.name owner_name, v.name verifier_name, cb.name creator_name,
                          ap.name approver_name
                     FROM risk_controls rc
                     LEFT JOIN users u ON u.id = rc.owner_user_id
                     LEFT JOIN users v ON v.id = rc.verifier_user_id
                     LEFT JOIN users cb ON cb.id = rc.created_by
                     LEFT JOIN users ap ON ap.id = rc.approved_by
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
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('risk', 'الضوابطُ والمعالجة');
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'الضوابط والضوابط الحرجة';
    $header_icon = 'fas fa-shield-halved';
    $header_actions = $canWrite ? array(array('id' => 'ctlNewBtn', 'class' => 'add-btn', 'icon' => 'fas fa-plus', 'label' => 'ضابط جديد')) : array();
    $header_back = array();
    $header_context = array('الإجمالي' => count($rows) . ' ضابط', 'الحرجة' => $criticalN,
        'غير المثبتة' => $unprovenN, 'المنظر' => $view === 'all' ? 'كل الأعمدة (24)' : 'مختصر موجَّه للمهمة');
    include('../includes/page_header.php');
    ems_screen_about(
        'وجود الضابط لا يخفض درجة الخطر — يلزم دليل تنفيذ وتحقق دوري (RK-07). '
        . 'الحرج قليل ومحدَّد: حدث عالي العواقب ومعيار أداء وطريقة تحقق ومتحقق مستقل وإجراء فشل.',
        array('لا يتحقق مالك الضابط من نفسه — الحارس يرفضه برمز',
              'نموذجُ البياناتِ لا يُختزل: المنظرُ يقلّل الأعمدةَ لا الصفوف'));
    risk_view_bar('risk_controls', $view);
    echo ems_states_bundle('لا ضوابطَ مسجَّلةً بعد', 'الضابطُ يُسجَّل بدليلِ تنفيذٍ محدَّد — والحرجُ بحقوله الخمسة');
    ?>
    <style>
        .rsk-w100 { width: 100%; }
        .rsk-fs78 { font-size: .78rem; }
        .rsk-fs76 { font-size: .76rem; }
        .rsk-cell-w200 { font-size: .76rem; max-width: 200px; }
        .rsk-cell-w180 { font-size: .76rem; max-width: 180px; }
        .rsk-cell-w160 { font-size: .76rem; max-width: 160px; }
        .rsk-actions-cell { min-width: 260px; }
        .rsk-guard-note { font-size: .74rem; color: var(--c-b02a37, #b02a37); }
        .rsk-readonly-note { font-size: .74rem; opacity: .6; }
        .rsk-new-card { margin-top: 16px; }
        .rsk-new-card.is-hidden { display: none; }
    </style>

    <div class="card"><div class="card-body table-responsive">
        <?php $V = function ($c) use ($view) { return risk_col_visible('risk_controls', $view, $c); }; ?>
        <table class="table table-striped rsk-w100">
            <thead><tr><th>الرمز</th><th>الاسم</th>
                <?php if ($V('ctype')): ?><th>النوع</th><?php endif; ?>
                <?php if ($V('owner_name')): ?><th>المالك</th><?php endif; ?>
                <?php if ($V('process_ref')): ?><th>العملية</th><?php endif; ?>
                <?php if ($V('frequency')): ?><th>التكرار</th><?php endif; ?>
                <?php if ($V('effectiveness')): ?><th>الفعالية</th><?php endif; ?>
                <?php if ($V('last_verified_at')): ?><th>آخر تحقق</th><?php endif; ?>
                <?php if ($V('last_verify_result')): ?><th>نتيجة التحقق</th><?php endif; ?>
                <?php if ($V('next_verify_due')): ?><th>التحقق التالي</th><?php endif; ?>
                <?php if ($V('is_critical')): ?><th>حرج</th><?php endif; ?>
                <?php if ($V('hico_event')): ?><th>الحدث عالي العواقب</th><?php endif; ?>
                <?php if ($V('perf_criterion')): ?><th>معيار الأداء</th><?php endif; ?>
                <?php if ($V('verify_method')): ?><th>طريقة التحقق</th><?php endif; ?>
                <?php if ($V('verifier_name')): ?><th>المتحقق المستقل</th><?php endif; ?>
                <?php if ($V('fail_action')): ?><th>إجراء الفشل</th><?php endif; ?>
                <th>دليل/تحقق</th></tr></thead>
            <tbody><?php foreach ($rows as $c): ?>
            <tr>
                <td><?php echo htmlspecialchars($c['control_code']); ?></td>
                <td><?php echo htmlspecialchars($c['name_ar']); ?></td>
                <?php if ($V('ctype')): ?><td><?php echo $c['ctype']; ?></td><?php endif; ?>
                <?php if ($V('owner_name')): ?><td><?php echo htmlspecialchars((string) $c['owner_name']); ?></td><?php endif; ?>
                <?php if ($V('process_ref')): ?><td class="rsk-fs78"><?php echo htmlspecialchars((string) $c['process_ref'] ?: '—'); ?></td><?php endif; ?>
                <?php if ($V('frequency')): ?><td><?php echo $c['frequency']; ?></td><?php endif; ?>
                <?php if ($V('effectiveness')): ?>
                <td><span class="badge badge-<?php echo $c['effectiveness'] === 'فعال' ? 'success' : ($c['effectiveness'] === 'غير فعال' ? 'danger' : 'secondary'); ?>">
                    <?php echo $c['effectiveness']; ?></span></td><?php endif; ?>
                <?php if ($V('last_verified_at')): ?><td><?php echo htmlspecialchars((string) $c['last_verified_at'] ?: '—'); ?></td><?php endif; ?>
                <?php if ($V('last_verify_result')): ?><td class="rsk-cell-w200"><?php echo htmlspecialchars(mb_substr((string) $c['last_verify_result'], 0, 80) ?: '—'); ?></td><?php endif; ?>
                <?php if ($V('next_verify_due')): ?><td><?php echo htmlspecialchars((string) $c['next_verify_due'] ?: '—'); ?></td><?php endif; ?>
                <?php if ($V('is_critical')): ?><td><?php echo (int) $c['is_critical'] === 1 ? '<span class="badge badge-danger">حرج</span>' : '—'; ?></td><?php endif; ?>
                <?php if ($V('hico_event')): ?><td class="rsk-cell-w180"><?php echo htmlspecialchars((string) $c['hico_event'] ?: '—'); ?></td><?php endif; ?>
                <?php if ($V('perf_criterion')): ?><td class="rsk-cell-w160"><?php echo htmlspecialchars((string) $c['perf_criterion'] ?: '—'); ?></td><?php endif; ?>
                <?php if ($V('verify_method')): ?><td class="rsk-fs76"><?php echo htmlspecialchars((string) $c['verify_method'] ?: '—'); ?></td><?php endif; ?>
                <?php if ($V('verifier_name')): ?><td><?php echo htmlspecialchars((string) $c['verifier_name'] ?: '—'); ?></td><?php endif; ?>
                <?php if ($V('fail_action')): ?><td class="rsk-cell-w180"><?php echo htmlspecialchars((string) $c['fail_action'] ?: '—'); ?></td><?php endif; ?>
                <td class="rsk-actions-cell">
                    <?php
                    // §2-2: «إخفاءُ الزرِّ تحسينُ تجربةٍ لا حماية» — والخادمُ يمنع أصلًا.
                    // لكن إظهارَ زرٍّ يُرفض دائمًا عيبُ تجربةٍ لا حياد: دليلُ التنفيذِ
                    // يسجّله مالكُ الضابطِ (أو مَن يملك كتابةَ المخاطر)، فمن ليس كذلك
                    // لا يُعرض له الزرُّ ليضغطه فيُرفض.
                    $canEvid = $canWrite || (int) $c['owner_user_id'] === $uid;
                    // والتحققُ للحرجِ يفرض استقلالَ المتحقِّق (RK-07) — فمالكُه لا يراه.
                    $canVerifyThis = $canWrite
                        && !((int) $c['is_critical'] === 1 && (int) $c['owner_user_id'] === $uid);
                    ?>
                    <?php if ($canEvid): ?>
                    <button class="btn btn-sm btn-secondary ctlEvid" data-id="<?php echo (int) $c['id']; ?>">دليل تنفيذ</button>
                    <?php endif; ?>
                    <?php if ($canVerifyThis): ?>
                    <button class="btn btn-sm btn-primary ctlVerify" data-id="<?php echo (int) $c['id']; ?>">تحقق</button>
                    <?php elseif ($canWrite): ?>
                    <span class="rsk-guard-note" title="RK-07">مالكه — لا يتحقق</span>
                    <?php endif; ?>
                    <?php if (!$canEvid && !$canVerifyThis): ?>
                    <span class="rsk-readonly-note">قراءة</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; if (empty($rows)): ?>
            <tr><td colspan="17" class="text-muted">لا ضوابط بعد</td></tr>
            <?php endif; ?></tbody>
        </table>
    </div></div>

    <?php if ($canWrite): ?>
    <div class="card rsk-new-card is-hidden" id="ctlNewCard"><div class="card-body">
        <h6>ضابط جديد — الحرج بحقوله الخمسة كاملة</h6>
        <form id="ctlNewForm" class="allforms">
            <div class="row">
                <div class="col-md-4"><label>الاسم *<input name="name_ar" class="form-control" aria-label="اسم الضابط" required></label></div>
                <div class="col-md-2"><label>النوع<select name="ctype" class="form-control" aria-label="نوع الضابط"><option>وقائي</option><option>كاشف</option><option>تصحيحي</option></select></label></div>
                <div class="col-md-2"><label>المالك (user) *<input name="owner_user_id" type="number" class="form-control" aria-label="مالك الضابط برقم المستخدم" required></label></div>
                <div class="col-md-2"><label>التكرار<select name="frequency" class="form-control" aria-label="تكرار تنفيذ الضابط">
                    <option>يومي</option><option>كل وردية</option><option>أسبوعي</option><option>شهري</option><option>عند الحدث</option></select></label></div>
                <div class="col-md-2"><label>حرج؟<select name="is_critical" class="form-control" aria-label="هل الضابط حرج"><option value="0">لا</option><option value="1">نعم</option></select></label></div>
                <div class="col-md-6"><label>العملية التي ينفَّذ فيها<input name="process_ref" class="form-control" aria-label="العملية التي ينفذ فيها الضابط"></label></div>
                <div class="col-md-6"><label>دليل التنفيذ المطلوب *<input name="evidence_spec" class="form-control" aria-label="دليل التنفيذ المطلوب" required></label></div>
                <div class="col-md-4"><label>الحدث عالي العواقب (حرج)<input name="hico_event" class="form-control" aria-label="الحدث عالي العواقب الذي يمنعه الضابط"></label></div>
                <div class="col-md-4"><label>معيار الأداء (حرج)<input name="perf_criterion" class="form-control" aria-label="معيار أداء الضابط الحرج"></label></div>
                <div class="col-md-4"><label>طريقة التحقق (حرج)<input name="verify_method" class="form-control" aria-label="طريقة التحقق من الضابط الحرج"></label></div>
                <div class="col-md-4"><label>المتحقق المستقل user (حرج)<input name="verifier_user_id" type="number" class="form-control" aria-label="المتحقق المستقل برقم المستخدم"></label></div>
                <div class="col-md-8"><label>ماذا يحدث عند فشله (حرج)<input name="fail_action" class="form-control" aria-label="الإجراء عند فشل الضابط الحرج"></label></div>
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
        document.getElementById('ctlNewCard').classList.toggle('is-hidden');
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
