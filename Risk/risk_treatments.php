<?php
/**
 * Risk/risk_treatments.php — إجراءات المعالجة (M-16 · المرحلة 10)
 * كل إجراء بمسؤول ومهلة — والمتأخر يظهر ويُصعَّد، والإغلاق بقبول المتحقق.
 */
require_once __DIR__ . '/_risk_common.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$canVerify = $RISK_FULL && (!empty($__pp['can_edit']) || $is_super_admin);
// CS-05 / AC-F6 — كنسُ المتأخرِ خدمةٌ لا عبارةٌ في السطح.
require_once __DIR__ . '/../app/Services/Risk/RiskService.php';
\App\Services\Risk\RiskService::sweepOverdueTreatments($conn, $company_id);

$rows = array();
$r = $conn->query("SELECT t.*, rr.risk_code, rr.title risk_title, u.name action_owner
                     FROM risk_treatments t JOIN risk_register rr ON rr.id = t.risk_id
                     LEFT JOIN users u ON u.id = t.action_owner_user_id
                    WHERE t.company_id = {$company_id}" . ($RISK_FULL ? '' : " AND t.action_owner_user_id = {$uid}") . "
                    ORDER BY FIELD(t.state,'overdue','done','in_progress','planned','verified'), t.due_date LIMIT 500");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }
$overdueN = 0; $doneN = 0;
foreach ($rows as $x) { if ($x['state'] === 'overdue') { $overdueN++; } if ($x['state'] === 'done') { $doneN++; } }

$page_title = 'إيكوبيشن | إجراءات المعالجة';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* ══ UXW-01 ②: أنماطٌ ثابتةٌ نُقِلت من سماتِ style إلى أصنافٍ ببادئةِ الشاشة ══ */
.rtr-table { width: 100%; }
.rtr-ref { font-size: .8rem; }
.rtr-dialog {
  max-width: 520px;
  width: 92%;
  border: 1px solid var(--c-e5e7eb, #e5e7eb);
  border-radius: 10px;
  padding: 18px;
}
.rtr-dialog-title { margin: 0 0 4px; }
.rtr-dialog-lead { font-size: .85rem; margin: 0 0 12px; }
.rtr-field-label { display: block; margin-bottom: 8px; }
.rtr-dialog-err { color: var(--danger-deep, #7F1D1D); font-size: .85rem; min-height: 1.2em; }
.rtr-dialog-actions { display: flex; gap: 8px; justify-content: flex-start; margin-top: 10px; }
</style>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'إجراءات معالجة المخاطر';
    $header_icon = 'fas fa-list-check';
    $header_actions = array();
    $header_back = array();
    $header_context = array('المعروض' => count($rows) . ' إجراء', 'متأخرة' => $overdueN, 'تنتظر قبول المتحقق' => $doneN);
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا إجراءات معالجة مسندة إليك أو إلى نطاقك', 'أسند معالجة من ملف الخطر في «سجل المخاطر» ثم عد إلى هذه الشاشة');
    ems_screen_about('المعالجة تقع في الإدارة المالكة وبمواردها — وإدارة المخاطر تتحقق ولا تنفذ (RK-02).',
        array('الإجراء المتأخر يظهر في مهام المسؤول ويصعد لمديره'));
    ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped rtr-table">
            <thead><tr><th>الخطر</th><th>النوع</th><th>الخطة</th><th>المسؤول</th><th>المهلة</th><th>الحالة</th><th>دليل الإنجاز</th><th>إجراء</th></tr></thead>
            <tbody><?php foreach ($rows as $t): ?>
            <tr>
                <td><a href="risk_card.php?id=<?php echo (int) $t['risk_id']; ?>"><?php echo $t['risk_code']; ?></a></td>
                <td><?php echo $t['ttype']; ?></td>
                <td><?php echo htmlspecialchars(mb_substr($t['plan_ar'], 0, 60)); ?></td>
                <td><?php echo htmlspecialchars((string) $t['action_owner']); ?></td>
                <td><?php echo $t['due_date']; ?></td>
                <td><span class="badge badge-<?php echo $t['state'] === 'verified' ? 'success' : ($t['state'] === 'overdue' ? 'danger' : 'secondary'); ?>"><?php echo $t['state']; ?></span></td>
                <?php /* INJ-0576: الدليلُ والمرجعُ والمرفقُ معًا — فالمتحقِّقُ لا
                         يقبل ما لا يستطيع فتحَه. والمرفقُ رابطٌ لا نصٌّ مبتور. */ ?>
                <td>
                    <?php $__ev = trim((string) ($t['done_evidence'] ?? '')); ?>
                    <?php echo $__ev !== '' ? htmlspecialchars(mb_substr($__ev, 0, 50)) : '—'; ?>
                    <?php if (!empty($t['done_ref'])): ?>
                        <div class="text-muted rtr-ref">مرجع:
                            <?php echo htmlspecialchars((string) $t['done_ref']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($t['done_attachment'])): ?>
                        <div><a class="ems-evid-link" href="<?php echo htmlspecialchars((string) $t['done_attachment']); ?>"
                                target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> المرفق</a></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (in_array($t['state'], array('planned', 'in_progress', 'overdue'), true) && ((int) $t['action_owner_user_id'] === $uid || $canVerify)): ?>
                    <button class="btn btn-sm btn-secondary treatDone" data-id="<?php echo (int) $t['id']; ?>">إنجاز بدليل</button>
                    <?php endif; ?>
                    <?php if ($t['state'] === 'done' && $canVerify): ?>
                    <button class="btn btn-sm btn-primary treatVerify" data-id="<?php echo (int) $t['id']; ?>">قبول المتحقق</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; if (empty($rows)): ?><tr><td colspan="8" class="text-muted">لا إجراءات</td></tr><?php endif; ?></tbody>
        </table>
    </div></div>
</div>

<?php /* INJ-0576: موضعُ إدخالِ دليلِ الإنجاز — عنوانٌ وثلاثةُ حقولٍ وردٌّ في موضعِه */ ?>
<dialog id="treatDoneDlg" class="rtr-dialog">
    <h3 class="rtr-dialog-title">إنجاز المعالجة بدليل</h3>
    <p class="text-muted rtr-dialog-lead">
        الإغلاق بقبول المتحقق لا بالتنفيذ. أدخل دليلا مقروءا (عشرة محارف فأكثر)
        أو مرفقا ومرجعا يدلان على المستند.</p>
    <label class="rtr-field-label">دليل التنفيذ
        <textarea name="done_evidence" class="form-control" rows="3"
                  placeholder="ما الذي نفذ ومتى وأين"></textarea></label>
    <label class="rtr-field-label">رابط المرفق
        <input type="text" name="done_attachment" class="form-control"
               placeholder="../uploads/… أو رابط مستند"></label>
    <label class="rtr-field-label">المرجع
        <input type="text" name="done_ref" class="form-control"
               placeholder="رقم مستند أو أمر عمل"></label>
    <div id="treatDoneErr" class="rtr-dialog-err"></div>
    <div class="rtr-dialog-actions">
        <button type="button" id="treatDoneSend" class="btn btn-primary">حفظ الدليل</button>
        <button type="button" id="treatDoneCancel" class="btn btn-secondary">إلغاء</button>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function post(d, cb) {
        var fd = new FormData();
        Object.keys(d).forEach(function (k) { fd.append(k, d[k]); });
        if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
        fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(cb);
    }
    /* ── INJ-0576 · نموذجٌ بثلاثةِ حقولٍ بدلَ `prompt()` ───────────────────────
         `prompt()` سطرٌ واحدٌ بلا عنوانٍ ولا تحقُّقٍ ولا موضعٍ للمرفقِ والمرجع —
         ولا يُنسخ منه ولا يُلصق فيه بسهولة. والنموذجُ يجمع الثلاثةَ ويُظهر ردَّ
         الخادمِ في موضعِه بدلَ `alert`. */
    var dlg = document.getElementById('treatDoneDlg');
    var dlgErr = document.getElementById('treatDoneErr');
    var curId = null;
    document.querySelectorAll('.treatDone').forEach(function (b) {
        b.addEventListener('click', function () {
            curId = b.dataset.id;
            dlgErr.textContent = '';
            dlg.querySelector('[name=done_evidence]').value = '';
            dlg.querySelector('[name=done_attachment]').value = '';
            dlg.querySelector('[name=done_ref]').value = '';
            if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
        });
    });
    var sendBtn = document.getElementById('treatDoneSend');
    if (sendBtn) {
        sendBtn.addEventListener('click', function () {
            dlgErr.textContent = '';
            post({
                do: 'treatment_progress', treatment_id: curId, state: 'done',
                done_evidence:   dlg.querySelector('[name=done_evidence]').value,
                done_attachment: dlg.querySelector('[name=done_attachment]').value,
                done_ref:        dlg.querySelector('[name=done_ref]').value
            }, function (j) {
                if (j.ok) { location.reload(); }
                else { dlgErr.textContent = (j.code ? j.code + ' — ' : '') + (j.msg || 'تعذر الحفظ'); }
            });
        });
    }
    var cancelBtn = document.getElementById('treatDoneCancel');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            if (typeof dlg.close === 'function') { dlg.close(); } else { dlg.removeAttribute('open'); }
        });
    }
    document.querySelectorAll('.treatVerify').forEach(function (b) {
        b.addEventListener('click', function () {
            post({ do: 'treatment_verify', treatment_id: b.dataset.id },
                function (j) { if (j.ok) { location.reload(); } else { alert(j.msg || ''); } });
        });
    });
});
</script>
</body>
</html>
