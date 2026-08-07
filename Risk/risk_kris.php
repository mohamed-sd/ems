<?php
/**
 * Risk/risk_kris.php — مؤشرات الخطر الرئيسة (M-16 · ورقة 26 — الـ36 بحدّيها)
 * سابقة للحدث لا لاحقة — بلوغ الحد الحرج يولّد إشارة SG-15 آليًّا.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$canWrite = $RISK_FULL && (!empty($__pp['can_edit']) || $is_super_admin);
$rows = array();
$r = $conn->query("SELECT k.*, ru.ru_code FROM risk_kris k LEFT JOIN risk_units ru ON ru.id = k.ru_id
                    WHERE k.company_id = {$company_id} AND k.active = 1 ORDER BY k.dept_ar, k.id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }
$critN = 0; $warnN = 0;
foreach ($rows as $x) { if ($x['kri_state'] === 'critical') { $critN++; } if ($x['kri_state'] === 'warn') { $warnN++; } }

$page_title = 'إيكوبيشن | مؤشرات الخطر';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مؤشرات الخطر الرئيسة (KRI)';
    $header_icon = 'fas fa-gauge-high';
    $header_actions = array();
    $header_back = array();
    $header_context = array('المقام' => count($rows) . ' مؤشرًا (ورقة 26)', 'حرجة' => $critN, 'إنذار' => $warnN);
    include('../includes/page_header.php');
    ems_screen_about('المؤشر يُقرأ من النظام وينذر باقتراب الخطر قبل وقوعه — وبلوغ الحد الحرج يولد إشارة SG-15 وتصعيدًا بمهلته.',
        array('فشل مؤشر الضابط (KCI) يرفع الخطر المتبقي فورًا'));
    ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped" style="width:100%">
            <thead><tr><th>الإدارة</th><th>المؤشر</th><th>حد الإنذار</th><th>الحد الحرج</th>
                <th>المصدر</th><th>الوحدة</th><th>القيمة الحالية</th><th>الحالة</th><th>آخر قراءة</th>
                <?php if ($canWrite): ?><th>تحديث القراءة</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($rows as $k): ?>
            <tr>
                <td><?php echo htmlspecialchars($k['dept_ar']); ?></td>
                <td><?php echo htmlspecialchars($k['name_ar']); ?></td>
                <td><?php echo htmlspecialchars($k['warn_threshold_ar']); ?></td>
                <td><?php echo htmlspecialchars($k['critical_threshold_ar']); ?></td>
                <td><?php echo htmlspecialchars($k['source_ar']); ?></td>
                <td><?php echo htmlspecialchars((string) $k['ru_code']); ?></td>
                <td><?php echo htmlspecialchars((string) $k['current_value'] ?: '—'); ?></td>
                <td><?php $map = array('ok' => array('success', 'سليم'), 'warn' => array('warning', 'إنذار'),
                        'critical' => array('danger', 'حرج'), 'unread' => array('secondary', 'لم يُقرأ'));
                    $mm = $map[$k['kri_state']]; ?>
                    <span class="badge badge-<?php echo $mm[0]; ?>"><?php echo $mm[1]; ?></span></td>
                <td><?php echo htmlspecialchars((string) $k['last_read_at'] ?: '—'); ?></td>
                <?php if ($canWrite): ?>
                <td style="min-width:230px">
                    <input class="kriVal form-control form-control-sm" style="display:inline-block;width:90px" placeholder="القيمة">
                    <select class="kriState form-control form-control-sm" style="display:inline-block;width:80px">
                        <option value="ok">سليم</option><option value="warn">إنذار</option><option value="critical">حرج</option>
                    </select>
                    <button class="btn btn-sm btn-dark kriGo" data-id="<?php echo (int) $k['id']; ?>">حفظ</button>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?></tbody>
        </table>
    </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.kriGo').forEach(function (b) {
        b.addEventListener('click', function () {
            var tr = b.closest('tr');
            var fd = new FormData();
            fd.append('do', 'kri_update');
            fd.append('kri_id', b.dataset.id);
            fd.append('current_value', tr.querySelector('.kriVal').value);
            fd.append('kri_state', tr.querySelector('.kriState').value);
            if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
            fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
            .then(function (j) { if (j.ok) { location.reload(); } else { alert((j.code || '') + ': ' + (j.msg || '')); } });
        });
    });
});
</script>
</body>
</html>
