<?php
/**
 * Risk/risk_control_verify.php — التحقق من الضوابط الحرجة (M-16 · الشاشة ٧)
 * ─────────────────────────────────────────────────────────────────────────
 * منهجُ ICMM: «ضوابطُ قليلةٌ محدَّدةٌ تمنع الأحداثَ عاليةَ العواقب — لا مئاتٌ
 * متساوية». والحرجُ بحقوله الخمسةِ الزائدة: الحدثُ الذي يمنعه · معيارُ الأداء ·
 * طريقةُ التحقق · من يتحقق منه · ماذا يحدث عند فشله.
 * وRK-07 هنا بنيويٌّ: «لا يتحقق مالكُ الضابطِ من ضابطِ نفسِه» — الحارسُ في
 * الخدمةِ يرفض، وهذه الشاشةُ تُظهر التعارضَ قبل المحاولةِ لا بعد الرفض.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$canVerify = $is_super_admin || !empty($__pp['can_edit']);

$fState = isset($_GET['due']) ? (string) $_GET['due'] : '';
$w = " WHERE c.company_id = {$company_id} AND c.active = 1 AND c.is_critical = 1";
if ($fState === 'overdue') { $w .= ' AND (c.next_verify_due IS NULL OR c.next_verify_due < CURDATE())'; }
elseif ($fState === 'soon') { $w .= ' AND c.next_verify_due BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)'; }
elseif ($fState === 'failed') { $w .= " AND c.effectiveness = 'غير فعال'"; }

$rows = array();
$res = $conn->query("SELECT c.*, ow.name owner_name, vf.name verifier_name, lv.name last_verifier,
                            (SELECT COUNT(*) FROM risk_control_links l
                              WHERE l.company_id = c.company_id AND l.control_id = c.id) linked_risks,
                            (SELECT COUNT(*) FROM risk_control_evidence e
                              WHERE e.company_id = c.company_id AND e.control_id = c.id
                                AND e.kind = 'execution') evidence_count
                       FROM risk_controls c
                       LEFT JOIN users ow ON ow.id = c.owner_user_id
                       LEFT JOIN users vf ON vf.id = c.verifier_user_id
                       LEFT JOIN users lv ON lv.id = c.last_verified_by
                       {$w} ORDER BY
                            (c.next_verify_due IS NULL) DESC, c.next_verify_due ASC LIMIT 400");
while ($x = $res->fetch_assoc()) { $rows[] = $x; }

$stats = array('total' => count($rows), 'overdue' => 0, 'failed' => 0, 'unproven' => 0, 'conflict' => 0);
foreach ($rows as $x) {
    if (empty($x['next_verify_due']) || $x['next_verify_due'] < date('Y-m-d')) { $stats['overdue']++; }
    if ($x['effectiveness'] === 'غير فعال') { $stats['failed']++; }
    if ($x['effectiveness'] === 'غير مثبت') { $stats['unproven']++; }
    if (!empty($x['verifier_user_id']) && (int) $x['verifier_user_id'] === (int) $x['owner_user_id']) { $stats['conflict']++; }
}

$page_title = 'إيكوبيشن | التحقق من الضوابط الحرجة';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'التحقق من الضوابط الحرجة';
    $header_icon = 'fas fa-clipboard-check';
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'الضوابط الحرجة وحدَها (ICMM)',
        'الإجمالي' => $stats['total'],
        'تجاوزت موعد التحقق' => $stats['overdue'],
        'غير فعالة' => $stats['failed'],
        'غير مثبتة' => $stats['unproven'],
        'تعارض متحقق/مالك' => $stats['conflict'],
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'الضابطُ الحرجُ يمنع حدثًا عاليَ العواقب — وقلّتُه شرطُ معناه. ولا يُحتسب إلا بإثبات '
        . 'فعاليته بدليلِ تنفيذٍ وتحققٍ دوريٍّ ونتيجةٍ موثَّقة (RK-07).',
        array('المتحقِّقُ المستقلُّ ≠ مالكُ الضابط — والحارسُ يرفض في الخادم لا بإخفاءِ الزر',
              'فشلُ ضابطٍ حرجٍ يصعَّد للرئيسِ في اليومِ نفسِه ولا يُعكس — يُغلق بإجراءٍ تصحيحيٍّ متحقَّقٍ منه'));
    ?>

    <form method="get" class="ems-toolbar" style="align-items:flex-end">
        <label style="font-size:.8rem">الحالة
            <select name="due" class="form-control" style="min-width:200px">
                <option value="">الكل</option>
                <option value="overdue" <?php echo $fState === 'overdue' ? 'selected' : ''; ?>>تجاوزت موعد التحقق</option>
                <option value="soon" <?php echo $fState === 'soon' ? 'selected' : ''; ?>>تحقق مستحق خلال 14 يومًا</option>
                <option value="failed" <?php echo $fState === 'failed' ? 'selected' : ''; ?>>غير فعالة</option>
            </select></label>
        <button type="submit" class="ems-btn-secondary"><i class="fa fa-filter"></i> ترشيح</button>
    </form>

    <?php if (empty($rows)): ?>
    <div class="ems-card" id="cvEmpty"></div>
    <script>document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('cvEmpty').appendChild(EmsUI.emptyState({
            reason: 'لا ضوابط حرجة مسجلة — والحرجُ يُوسَم بحقوله الخمسة من شاشة الضوابط',
            createHref: 'risk_controls.php', createLabel: 'إلى الضوابط'
        }));
    });</script>
    <?php else: ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped" style="width:100%">
            <thead><tr>
                <th>الرمز</th><th>الضابط</th><th>الحدث عالي العواقب</th><th>معيار الأداء</th>
                <th>طريقة التحقق</th><th>المالك</th><th>المتحقق المستقل</th>
                <th>الفعالية</th><th>آخر تحقق</th><th>التحقق التالي</th>
                <th>مخاطر مرتبطة</th><th>أدلة</th><th>إجراء الفشل</th>
                <?php if ($canVerify): ?><th>الفعل</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $x):
                $conflict = !empty($x['verifier_user_id']) && (int) $x['verifier_user_id'] === (int) $x['owner_user_id'];
                $selfOwner = (int) $x['owner_user_id'] === $uid;
                $overdue = empty($x['next_verify_due']) || $x['next_verify_due'] < date('Y-m-d'); ?>
                <tr <?php echo $overdue ? 'style="background:rgba(220,53,69,.05)"' : ''; ?>>
                    <td><strong><?php echo htmlspecialchars($x['control_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($x['name_ar']); ?></td>
                    <td style="font-size:.76rem;max-width:200px"><?php echo htmlspecialchars((string) $x['hico_event'] ?: '—'); ?></td>
                    <td style="font-size:.76rem;max-width:160px"><?php echo htmlspecialchars((string) $x['perf_criterion'] ?: '—'); ?></td>
                    <td style="font-size:.76rem"><?php echo htmlspecialchars((string) $x['verify_method'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['owner_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['verifier_name'] ?: '—'); ?>
                        <?php if ($conflict): ?>
                        <span class="badge badge-danger" title="RK-07: لا يتحقق المالك من نفسه">تعارض</span>
                        <?php endif; ?></td>
                    <td><?php $ef = (string) $x['effectiveness'];
                        $cls = $ef === 'غير فعال' ? 'badge-danger' : ($ef === 'فعال' ? 'badge-success' : 'badge-warning'); ?>
                        <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($ef); ?></span></td>
                    <td><?php echo htmlspecialchars((string) $x['last_verified_at'] ?: '—'); ?>
                        <?php if (!empty($x['last_verifier'])): ?>
                        <div style="font-size:.72rem;opacity:.7"><?php echo htmlspecialchars($x['last_verifier']); ?></div>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string) $x['next_verify_due'] ?: 'غير محدد'); ?></td>
                    <td><?php echo (int) $x['linked_risks']; ?></td>
                    <td><?php echo (int) $x['evidence_count']; ?></td>
                    <td style="font-size:.76rem;max-width:180px"><?php echo htmlspecialchars((string) $x['fail_action'] ?: '—'); ?></td>
                    <?php if ($canVerify): ?>
                    <td>
                        <?php if ($selfOwner): ?>
                        <span style="font-size:.74rem;color:#b02a37" title="RK-07">مالكه — لا يتحقق</span>
                        <?php else: ?>
                        <button class="btn btn-sm btn-primary cvDo" data-id="<?php echo (int) $x['id']; ?>"
                            data-code="<?php echo htmlspecialchars($x['control_code']); ?>">تحقق</button>
                        <button class="btn btn-sm btn-danger cvFail" data-id="<?php echo (int) $x['id']; ?>"
                            data-code="<?php echo htmlspecialchars($x['control_code']); ?>">فشل</button>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <?php endif; ?>

    <?php if ($canVerify): ?>
    <div class="card" id="cvCard" style="display:none;margin-top:16px"><div class="card-body">
        <h5><span id="cvTitle"></span></h5>
        <form id="cvForm" class="allforms">
            <input type="hidden" name="control_id" id="cvId">
            <input type="hidden" name="mode" id="cvMode">
            <div class="row">
                <div class="col-md-4" id="cvResultWrap"><label>نتيجة التحقق *<select name="result" class="form-control">
                    <option>فعال</option><option>فعال جزئيا</option><option>غير فعال</option>
                </select></label></div>
                <div class="col-md-8"><label>الدليل أو السبب * (نص مثبت لا ادعاء)
                    <textarea name="evidence_text" id="cvText" class="form-control" required></textarea></label></div>
            </div>
            <button type="submit" class="ems-btn-primary" id="cvSubmit">حفظ</button>
            <span id="cvMsg" style="margin-inline-start:10px"></span>
        </form>
    </div></div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var card = document.getElementById('cvCard');
        function open(id, code, mode) {
            document.getElementById('cvId').value = id;
            document.getElementById('cvMode').value = mode;
            document.getElementById('cvTitle').textContent = (mode === 'fail'
                ? 'تسجيل فشل ضابط حرج ' : 'التحقق من الضابط ') + code;
            document.getElementById('cvResultWrap').style.display = mode === 'fail' ? 'none' : '';
            document.getElementById('cvSubmit').textContent = mode === 'fail'
                ? 'تسجيل الفشل — تصعيد فوري (RSK-CTL-FAIL)' : 'حفظ التحقق (RSK-CTL-VERIFY)';
            document.getElementById('cvMsg').textContent = mode === 'fail'
                ? '⚠ الفشل يصعَّد للرئيس في اليوم نفسه ولا يُعكس' : '';
            card.style.display = '';
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        document.querySelectorAll('.cvDo').forEach(function (b) {
            b.addEventListener('click', function () { open(b.dataset.id, b.dataset.code, 'verify'); });
        });
        document.querySelectorAll('.cvFail').forEach(function (b) {
            b.addEventListener('click', function () { open(b.dataset.id, b.dataset.code, 'fail'); });
        });
        document.getElementById('cvForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            var mode = document.getElementById('cvMode').value;
            var fd = new FormData(this);
            if (mode === 'fail') {
                fd.append('do', 'control_fail');
                fd.append('reason', document.getElementById('cvText').value);
            } else {
                fd.append('do', 'control_verify');
            }
            if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
            fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
            .then(function (j) {
                var m = document.getElementById('cvMsg');
                if (j.ok) { m.textContent = '✔ سُجل'; setTimeout(function () { location.reload(); }, 800); }
                else { m.textContent = '✘ ' + (j.code || '') + ' ' + (j.msg || ''); }
            });
        });
    });
    </script>
    <?php endif; ?>
</div>
</body>
</html>
