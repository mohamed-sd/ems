<?php
/**
 * Risk/risk_treatments.php — إجراءات المعالجة (M-16 · المرحلة 10)
 * كل إجراء بمسؤول ومهلة — والمتأخر يظهر ويُصعَّد، والإغلاق بقبول المتحقق.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$canVerify = $RISK_FULL && (!empty($__pp['can_edit']) || $is_super_admin);
$conn->query("UPDATE risk_treatments SET state='overdue' WHERE company_id={$company_id} AND state IN ('planned','in_progress') AND due_date < CURDATE()");

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
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'إجراءات معالجة المخاطر';
    $header_icon = 'fas fa-list-check';
    $header_actions = array();
    $header_back = array();
    $header_context = array('المعروض' => count($rows) . ' إجراء', 'متأخرة' => $overdueN, 'تنتظر قبول المتحقق' => $doneN);
    include('../includes/page_header.php');
    ems_screen_about('المعالجة تقع في الإدارة المالكة وبمواردها — وإدارة المخاطر تتحقق ولا تنفذ (RK-02).',
        array('الإجراء المتأخر يظهر في مهام المسؤول ويُصعَّد لمديره'));
    ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped" style="width:100%">
            <thead><tr><th>الخطر</th><th>النوع</th><th>الخطة</th><th>المسؤول</th><th>المهلة</th><th>الحالة</th><th>دليل الإنجاز</th><th>إجراء</th></tr></thead>
            <tbody><?php foreach ($rows as $t): ?>
            <tr>
                <td><a href="risk_card.php?id=<?php echo (int) $t['risk_id']; ?>"><?php echo $t['risk_code']; ?></a></td>
                <td><?php echo $t['ttype']; ?></td>
                <td><?php echo htmlspecialchars(mb_substr($t['plan_ar'], 0, 60)); ?></td>
                <td><?php echo htmlspecialchars((string) $t['action_owner']); ?></td>
                <td><?php echo $t['due_date']; ?></td>
                <td><span class="badge badge-<?php echo $t['state'] === 'verified' ? 'success' : ($t['state'] === 'overdue' ? 'danger' : 'secondary'); ?>"><?php echo $t['state']; ?></span></td>
                <td><?php echo htmlspecialchars(mb_substr((string) $t['done_evidence'], 0, 50)) ?: '—'; ?></td>
                <td>
                    <?php if (in_array($t['state'], array('planned', 'in_progress', 'overdue'), true) && ((int) $t['action_owner_user_id'] === $uid || $canVerify)): ?>
                    <button class="btn btn-sm btn-outline-dark treatDone" data-id="<?php echo (int) $t['id']; ?>">إنجاز بدليل</button>
                    <?php endif; ?>
                    <?php if ($t['state'] === 'done' && $canVerify): ?>
                    <button class="btn btn-sm btn-outline-success treatVerify" data-id="<?php echo (int) $t['id']; ?>">قبول المتحقق</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; if (empty($rows)): ?><tr><td colspan="8" class="text-muted">لا إجراءات</td></tr><?php endif; ?></tbody>
        </table>
    </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function post(d, cb) {
        var fd = new FormData();
        Object.keys(d).forEach(function (k) { fd.append(k, d[k]); });
        if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
        fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(cb);
    }
    document.querySelectorAll('.treatDone').forEach(function (b) {
        b.addEventListener('click', function () {
            var txt = prompt('دليل الإنجاز:');
            if (!txt) { return; }
            post({ do: 'treatment_progress', treatment_id: b.dataset.id, state: 'done', done_evidence: txt },
                function (j) { if (j.ok) { location.reload(); } else { alert(j.msg || ''); } });
        });
    });
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
