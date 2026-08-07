<?php
/**
 * Risk/risk_card.php — ملف الخطر الواحد (M-16 · دورة الحياة الـ14)
 * ─────────────────────────────────────────────────────────────────────────
 * التقييمات تاريخ كامل (RK-03) · الضوابط بخريطتها وأدلتها · المعالجة بمهلة
 * ومسؤول · القبول بالسقف (RK-04) · الإغلاق بحارس الدليل الثلاثي.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$rid = intval($_GET['id'] ?? 0);
$scopeSql = risk_scope_sql($RISK_FULL, $RISK_ORG_UNIT);
$risk = null;
$st = $conn->query("SELECT rr.*, ru.ru_code, ru.name_ar ru_name, ou.name_ar owner_unit_name, u.name owner_name
                      FROM risk_register rr
                      JOIN risk_units ru ON ru.id = rr.ru_id
                      LEFT JOIN org_units ou ON ou.unit_id = rr.owner_unit_id
                      LEFT JOIN users u ON u.id = rr.risk_owner_user_id
                     WHERE rr.id = {$rid} AND rr.company_id = {$company_id} {$scopeSql} LIMIT 1");
if ($st) { $risk = $st->fetch_assoc(); }
if (!$risk) {
    $page_title = 'إيكوبيشن | ملف الخطر';
    include '../inheader.php'; include '../insidebar.php';
    echo '<div class="main ems-unified-page-shell"><div class="ems-card" id="rskDenied"></div></div>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){document.getElementById("rskDenied").appendChild(EmsUI.accessState({reason:"الخطر خارج نطاق زاويتك أو غير موجود",grantor:"إدارة المخاطر"}));});</script>';
    echo '</body></html>';
    exit();
}

$assessments = array(); $controls = array(); $treatments = array(); $acceptances = array(); $escalations = array();
$r = $conn->query("SELECT a.*, u.name assessor, c.name challenger FROM risk_assessments a
                    LEFT JOIN users u ON u.id = a.assessed_by LEFT JOIN users c ON c.id = a.challenged_by
                   WHERE a.company_id = {$company_id} AND a.risk_id = {$rid} ORDER BY a.assessed_at DESC");
while ($x = $r->fetch_assoc()) { $assessments[] = $x; }
$r = $conn->query("SELECT rc.*, l.created_at linked_at FROM risk_control_links l JOIN risk_controls rc ON rc.id = l.control_id
                   WHERE l.company_id = {$company_id} AND l.risk_id = {$rid}");
while ($x = $r->fetch_assoc()) { $controls[] = $x; }
$r = $conn->query("SELECT t.*, u.name action_owner FROM risk_treatments t LEFT JOIN users u ON u.id = t.action_owner_user_id
                   WHERE t.company_id = {$company_id} AND t.risk_id = {$rid} ORDER BY t.created_at DESC");
while ($x = $r->fetch_assoc()) { $treatments[] = $x; }
$r = $conn->query("SELECT a.*, u.name acceptor FROM risk_acceptances a LEFT JOIN users u ON u.id = a.accepted_by
                   WHERE a.company_id = {$company_id} AND a.risk_id = {$rid} ORDER BY a.created_at DESC");
while ($x = $r->fetch_assoc()) { $acceptances[] = $x; }
$r = $conn->query("SELECT * FROM risk_escalations WHERE company_id = {$company_id} AND risk_id = {$rid} ORDER BY created_at DESC");
while ($x = $r->fetch_assoc()) { $escalations[] = $x; }

$canWrite = $RISK_FULL && (!empty($__pp['can_edit']) || !empty($__pp['can_add']) || $is_super_admin);
$page_title = 'إيكوبيشن | ' . $risk['risk_code'];
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'ملف الخطر ' . $risk['risk_code'];
    $header_icon = 'fas fa-file-shield';
    $header_actions = array();
    $header_back = array('href' => 'risk_register.php', 'label' => 'رجوع');
    $header_context = array(
        'الوحدة' => $risk['ru_code'] . ' · ' . $risk['ru_name'],
        'المستوى الجاري' => $risk['current_level'] !== null && $risk['current_level'] !== '' ? $risk['current_level'] : 'لم يقيَّم',
        'الحالة' => $risk['state'],
        'المراجعة قبل' => (string) $risk['review_due'],
    );
    include('../includes/page_header.php');
    ?>

    <div class="ems-grid">
        <div class="ems-card ems-col-8">
            <h5><?php echo htmlspecialchars($risk['title']); ?></h5>
            <p class="text-muted"><?php echo nl2br(htmlspecialchars((string) $risk['description'])); ?></p>
            <div class="ems-page-context">
                <span>الإدارة المالكة (RK-01): <b><?php echo htmlspecialchars($risk['owner_unit_name'] ?: '—'); ?></b></span>
                <span>مالك الخطر: <b><?php echo htmlspecialchars($risk['owner_name'] ?: '—'); ?></b></span>
                <span>النطاق: <b><?php echo htmlspecialchars($risk['scope_type']); ?></b></span>
                <span>السبب الجذري: <b><?php echo htmlspecialchars($risk['root_cause']); ?></b></span>
            </div>
        </div>
        <div class="ems-card ems-col-4">
            <h6>التصعيدات (RK-08 — لا تُخفى)</h6>
            <?php if (empty($escalations)): ?><small class="text-muted">لا تصعيدات</small>
            <?php else: foreach ($escalations as $e): ?>
                <div style="font-size:.8rem;padding:4px 0;border-bottom:1px dashed #eee">
                    <b><?php echo htmlspecialchars($e['to_authority']); ?></b> · <?php echo htmlspecialchars($e['reason_ar']); ?>
                    <span class="text-muted"><?php echo $e['acknowledged_at'] ? '✔ استُلم' : '⏳ ينتظر'; ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="card" style="margin-top:16px"><div class="card-body">
        <h6>التقييمات — نسخ تاريخية لا تُمحى (RK-03)</h6>
        <div class="table-responsive"><table class="table table-sm">
            <thead><tr><th>النوع</th><th>الاحتمال</th><th>أقصى أثر</th><th>الدرجة</th><th>المستوى</th><th>الثقة</th><th>التقنية</th><th>المقيِّم</th><th>المتحدي</th><th>التاريخ</th></tr></thead>
            <tbody><?php foreach ($assessments as $a): ?>
                <tr><td><?php echo $a['assess_type']; ?></td><td><?php echo $a['likelihood']; ?></td>
                    <td><?php echo $a['impact_max']; ?></td><td><?php echo $a['score']; ?></td>
                    <td><?php echo $a['level']; ?></td><td><?php echo $a['confidence']; ?></td>
                    <td><?php echo htmlspecialchars($a['technique']); ?></td>
                    <td><?php echo htmlspecialchars($a['assessor'] ?: ''); ?></td>
                    <td><?php echo htmlspecialchars($a['challenger'] ?: '—'); ?></td>
                    <td><?php echo $a['assessed_at']; ?></td></tr>
            <?php endforeach; if (empty($assessments)): ?>
                <tr><td colspan="10" class="text-muted">لا تقييم بعد — يبدأ بالمتأصل قبل أي ضابط</td></tr>
            <?php endif; ?></tbody>
        </table></div>

        <?php if ($canWrite): ?>
        <form id="rskAssessForm" class="allforms" style="border-top:1px solid #eee;padding-top:12px">
            <div class="row">
                <div class="col-md-2"><label>النوع<select name="assess_type" class="form-control">
                    <option value="inherent">متأصل</option><option value="residual">متبقٍّ</option><option value="target">مستهدف</option>
                </select></label></div>
                <div class="col-md-2"><label>الاحتمال (1-5)<input type="number" name="likelihood" min="1" max="5" class="form-control" required></label></div>
                <?php foreach ($RISK_IMPACT_DIMS as $k => $v): ?>
                <div class="col-md-2"><label style="font-size:.75rem"><?php echo $v; ?> (0-5)<input type="number" name="impact_<?php echo $k; ?>" min="0" max="5" class="form-control"></label></div>
                <?php endforeach; ?>
                <div class="col-md-2"><label>الثقة<select name="confidence" class="form-control"><option>متوسطة</option><option>عالية</option><option>منخفضة</option></select></label></div>
                <div class="col-md-3"><label>التقنية<select name="technique" class="form-control">
                    <option>مصفوفة الخطر</option><option>ربطة العنق Bow-Tie</option><option>تحليل الضوابط الحرجة</option>
                    <option>FMEA / FMECA</option><option>تحليل السيناريوهات</option><option>ماذا لو What-if</option>
                    <option>تحليل السبب الجذري</option><option>قائمة فحص مخاطر العقد</option>
                    <option>تحليل الحساسية والضغط</option><option>تحليل التركز</option><option>تحليل أثر الأعمال</option>
                </select></label></div>
                <div class="col-md-5"><label>ملاحظة<input name="note" class="form-control"></label></div>
            </div>
            <button class="ems-btn-primary" type="submit">تسجيل التقييم (نسخة جديدة)</button>
            <span id="rskAssessMsg"></span>
        </form>
        <?php endif; ?>
    </div></div>

    <div class="ems-grid" style="margin-top:16px">
        <div class="ems-card ems-col-6">
            <h6>خريطة الضوابط (المرحلة 6) — لا يُحتسب ضابط بلا دليل (RK-07)</h6>
            <?php foreach ($controls as $c): ?>
                <div style="padding:6px 0;border-bottom:1px dashed #eee;font-size:.84rem">
                    <b><?php echo htmlspecialchars($c['control_code']); ?></b> <?php echo htmlspecialchars($c['name_ar']); ?>
                    · <?php echo $c['ctype']; ?><?php echo (int) $c['is_critical'] === 1 ? ' · <span class="badge badge-danger">حرج</span>' : ''; ?>
                    · الفعالية: <b><?php echo $c['effectiveness']; ?></b>
                </div>
            <?php endforeach; if (empty($controls)): ?><small class="text-muted">لا ضوابط مربوطة — الدرجة لا تنخفض بلا ضابط مثبَت</small><?php endif; ?>
            <?php if ($canWrite): ?>
            <form id="rskLinkForm" class="ems-toolbar" style="margin-top:8px">
                <input type="number" name="control_id" class="form-control" placeholder="رقم الضابط" style="max-width:140px" required>
                <button class="ems-btn-secondary" type="submit">ربط ضابط</button>
                <a class="ems-btn-tertiary" href="risk_controls.php">سجل الضوابط ↗</a>
            </form>
            <?php endif; ?>
        </div>
        <div class="ems-card ems-col-6">
            <h6>المعالجة (المرحلة 10) — بخطة ومسؤول وموعد</h6>
            <?php foreach ($treatments as $t): ?>
                <div style="padding:6px 0;border-bottom:1px dashed #eee;font-size:.84rem">
                    <b><?php echo $t['ttype']; ?></b> · <?php echo htmlspecialchars(mb_substr($t['plan_ar'], 0, 60)); ?>
                    · <?php echo htmlspecialchars($t['action_owner'] ?: ''); ?> · قبل <?php echo $t['due_date']; ?>
                    · <span class="badge badge-<?php echo $t['state'] === 'verified' ? 'success' : 'secondary'; ?>"><?php echo $t['state']; ?></span>
                    <?php if ($canWrite && $t['state'] === 'done'): ?>
                    <button class="btn btn-sm btn-outline-success rskVerifyTreat" data-id="<?php echo (int) $t['id']; ?>">قبول الدليل</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($canWrite): ?>
            <form id="rskTreatForm" class="allforms" style="margin-top:8px">
                <div class="row">
                    <div class="col-md-3"><select name="ttype" class="form-control"><option>تقليل</option><option>تجنب</option><option>نقل</option><option>قبول</option></select></div>
                    <div class="col-md-4"><input name="plan_ar" class="form-control" placeholder="الخطة" required></div>
                    <div class="col-md-2"><input name="action_owner_user_id" type="number" class="form-control" placeholder="مسؤول (user)" required></div>
                    <div class="col-md-3"><input name="due_date" type="date" class="form-control" required></div>
                </div>
                <button class="ems-btn-secondary" type="submit">إسناد معالجة</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-top:16px"><div class="card-body">
        <h6>القبول والإغلاق — مصفوفة السلطة (RK-04) وحارس الدليل (RK-03)</h6>
        <div class="table-responsive"><table class="table table-sm">
            <thead><tr><th>المستوى عند القبول</th><th>السلطة</th><th>القابل</th><th>مراجعة قبل</th><th>ملاحظة</th><th>التاريخ</th></tr></thead>
            <tbody><?php foreach ($acceptances as $a): ?>
                <tr><td><?php echo $a['level_at_acceptance']; ?></td><td><?php echo $a['authority']; ?></td>
                    <td><?php echo htmlspecialchars($a['acceptor'] ?: ''); ?></td><td><?php echo $a['review_due']; ?></td>
                    <td><?php echo htmlspecialchars((string) $a['note']); ?></td><td><?php echo $a['created_at']; ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php if ($RISK_AUTHORITY !== '' && $risk['state'] !== 'closed'): ?>
        <div class="ems-toolbar">
            <button class="ems-btn-primary" id="rskAcceptBtn">قبول رسمي (بسلطتك: <?php echo $RISK_AUTHORITY; ?>)</button>
            <button class="ems-btn-secondary" id="rskCloseBtn">إغلاق بحارس الدليل</button>
            <span id="rskDecideMsg"></span>
        </div>
        <?php elseif ($risk['state'] === 'closed' && $canWrite): ?>
        <button class="ems-btn-secondary" id="rskReopenBtn">إعادة فتح</button>
        <?php endif; ?>
    </div></div>
</div>
<script>
(function () {
    var RID = <?php echo (int) $rid; ?>;
    function post(data, msgEl, reload) {
        var fd = new FormData();
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
        fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
        .then(function (j) {
            var m = document.getElementById(msgEl || 'rskDecideMsg');
            if (j.ok) { if (m) { m.textContent = '✔'; } if (reload !== false) { setTimeout(function () { location.reload(); }, 700); } }
            else if (m) { m.textContent = '✘ ' + (j.code || '') + ': ' + (j.msg || ''); }
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        var f = document.getElementById('rskAssessForm');
        if (f) { f.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var d = { do: 'risk_assess', risk_id: RID };
            new FormData(f).forEach(function (v, k) { d[k] = v; });
            post(d, 'rskAssessMsg');
        }); }
        var lf = document.getElementById('rskLinkForm');
        if (lf) { lf.addEventListener('submit', function (ev) {
            ev.preventDefault();
            post({ do: 'control_link', risk_id: RID, control_id: lf.control_id.value });
        }); }
        var tf = document.getElementById('rskTreatForm');
        if (tf) { tf.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var d = { do: 'treatment_create', risk_id: RID };
            new FormData(tf).forEach(function (v, k) { d[k] = v; });
            post(d);
        }); }
        document.querySelectorAll('.rskVerifyTreat').forEach(function (b) {
            b.addEventListener('click', function () { post({ do: 'treatment_verify', treatment_id: b.dataset.id }); });
        });
        var ab = document.getElementById('rskAcceptBtn');
        if (ab) { ab.addEventListener('click', function () {
            var note = prompt('ملاحظة القبول الرسمي (اختياري):') || '';
            post({ do: 'risk_accept', risk_id: RID, note: note });
        }); }
        var cb = document.getElementById('rskCloseBtn');
        if (cb) { cb.addEventListener('click', function () {
            var note = prompt('حيثية الإغلاق (تُفحص شروط الدليل الثلاثة):') || '';
            post({ do: 'risk_close', risk_id: RID, note: note });
        }); }
        var rb = document.getElementById('rskReopenBtn');
        if (rb) { rb.addEventListener('click', function () { post({ do: 'risk_reopen', risk_id: RID }); }); }
    });
})();
</script>
</body>
</html>
