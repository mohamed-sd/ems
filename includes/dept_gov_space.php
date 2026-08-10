<?php
/**
 * includes/dept_gov_space.php — مكوّنُ «حوكمة الإدارة» النطاقيُّ الواحد
 * ─────────────────────────────────────────────────────────────────────────
 * «مساحةُ حوكمةٍ نطاقيةٌ لكل إدارة — مكوّنٌ واحدٌ لا نسخةٌ لكلٍّ»
 * (INJAZ-CORE-01 §12-1 الباب ١١). النسخةُ الأولى بُنيت لإدارة المخاطر في
 * update0011 (Risk/gov_dept_rsk.php) — وهذا تعميمُها المعياريُّ لبقية
 * الإدارات: الشاشةُ الحاملةُ تضبط $GOV_DEPT ثم تضمّن هذا الملف.
 *
 * قراءةٌ لا كتابة، والفعلُ الكاتبُ الوحيدُ تصديقُ مراجعةِ الوصول (يشهد ولا
 * يمنح — gov.<dept>.attest). فصلُ الواجباتِ يُقاس على الحسابات الحية لا يُوصف.
 *
 * عقدُ الإعداد $GOV_DEPT:
 *   title · icon · module_like (مصفوفةُ بادئاتِ شاشاتِ الإدارة) ·
 *   team_roles (أدوارُ الإدارة) · sensitive_like (بادئةُ جداولها الحساسة) ·
 *   events_module (source_module في الممر المحايد) ·
 *   attest_endpoint (نقطةُ فعلِ التصديق) · attest_code (رمزُ الفعل للعرض) ·
 *   sod_queries (قياساتُ فصلِ الواجبات: عنوان + جملةُ SQL تُرجع صفوفَ الخرق)
 */

if (!isset($GOV_DEPT) || !is_array($GOV_DEPT)) {
    exit('GOV-DEPT-500: المكوّنُ يحتاج عقدَ إعدادٍ من الشاشةِ الحاملة');
}

require_once __DIR__ . '/screen_contract.php';

$canAttest = $is_super_admin || !empty($__pp['can_edit']) || !empty($__pp['can_add']);

/* ① الحساباتُ التابعة (حقلٌ حساس §6-3) */
$team = array();
$rolesIn = implode(',', array_map('intval', $GOV_DEPT['team_roles']));
if ($rolesIn !== '') {
    $res = $conn->query("SELECT u.id, u.username, u.name, u.role, u.status, r.name role_name,
                                jt.name job_title, u.employee_id
                           FROM users u
                           LEFT JOIN roles r ON r.id = u.role
                           LEFT JOIN employees e ON e.id = u.employee_id
                           LEFT JOIN job_titles jt ON jt.id = e.job_title_id
                          WHERE u.company_id = {$company_id} AND u.role IN ({$rolesIn})
                          ORDER BY u.role, u.id");
    if ($res) { while ($x = $res->fetch_assoc()) { $team[] = $x; } }
}

/* ② صلاحياتُ شاشاتِ الإدارة بالدور — من محرّك الصلاحيات لا وصفًا */
$perms = array();
$likes = array();
foreach ($GOV_DEPT['module_like'] as $pfx) {
    $likes[] = "mo.code LIKE '" . $conn->real_escape_string($pfx) . "%'";
}
if ($likes) {
    $res = $conn->query("SELECT mo.code, mo.name, rp.role_id, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
                           FROM role_permissions rp
                           JOIN modules mo ON mo.id = rp.module_id
                          WHERE (" . implode(' OR ', $likes) . ") AND rp.role_id IN ({$rolesIn})
                          ORDER BY mo.code, rp.role_id");
    if ($res) { while ($x = $res->fetch_assoc()) { $perms[$x['code']][(int) $x['role_id']] = $x; } }
}

/* ③ فصلُ الواجباتِ المتعارضة — قياسٌ حيٌّ بعقود الإدارة (§9-3) */
$sodBreaches = array();
foreach ($GOV_DEPT['sod_queries'] as $sq) {
    $res = $conn->query($sq['sql']);
    if ($res) {
        while ($x = $res->fetch_assoc()) {
            $sodBreaches[] = array('pair' => $sq['title'], 'detail' => implode(' · ', array_map('strval', $x)));
        }
    }
}
$sodTotal = count($sodBreaches);

/* ④ سجلاتُ التدقيقِ الأربعة (§9-4) — عدّاداتٌ حية */
$evMod = $conn->real_escape_string($GOV_DEPT['events_module']);
$auditCounts = array(
    'أحداث منشورة (الأثر العابر)' => (int) ($conn->query("SELECT COUNT(*) c FROM ems_business_events
        WHERE company_id = {$company_id} AND source_module = '{$evMod}'")->fetch_assoc()['c'] ?? 0),
    'سجل الاطّلاع الحساس' => (int) ($conn->query("SELECT COUNT(*) c FROM sensitive_read_log")->fetch_assoc()['c'] ?? 0),
    'سجل رفض الحارس' => (int) ($conn->query("SELECT COUNT(*) c FROM action_execution_log
        WHERE company_id = {$company_id} AND result = 'denied'")->fetch_assoc()['c'] ?? 0),
    'سجل النشاط المركزي' => (int) ($conn->query("SELECT COUNT(*) c FROM activity_logs
        WHERE company_id = {$company_id}")->fetch_assoc()['c'] ?? 0),
);

/* ⑤ الحقولُ الحساسةُ المسجَّلة لجداول الإدارة (AC-06) */
$sensFields = array();
$sl = $conn->real_escape_string($GOV_DEPT['sensitive_like']);
$res = $conn->query("SELECT no_policy, table_name, field_name, classification_sensitivity,
                            from_visible_to, log_views_flag, exportable_flag
                       FROM scr_sensitive_fields
                      WHERE company_id = {$company_id} AND table_name LIKE '{$sl}'
                      ORDER BY no_policy LIMIT 200");
if ($res) { while ($x = $res->fetch_assoc()) { $sensFields[] = $x; } }

/* ⑥ آخرُ تصديقاتِ مراجعة الوصول لهذه الإدارة — من الممر المحايد */
$attests = array();
$scopeLike = $conn->real_escape_string($GOV_DEPT['attest_scope']);
$res = $conn->query("SELECT id, occurred_at, created_by, payload
                       FROM ems_business_events
                      WHERE company_id = {$company_id} AND event_key = 'risk.access_review.attested'
                        AND payload LIKE '%{$scopeLike}%'
                      ORDER BY id DESC LIMIT 5");
if ($res) { while ($x = $res->fetch_assoc()) { $attests[] = $x; } }

$page_title = 'إيكوبيشن | ' . $GOV_DEPT['title'];
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = $GOV_DEPT['title'];
    $header_icon = $GOV_DEPT['icon'];
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'حوكمة الإدارة بنطاقها',
        'الحسابات التابعة' => count($team),
        'خروق فصل الواجبات' => $sodTotal,
        'حقول حساسة مسجَّلة' => count($sensFields),
        'أحداث منشورة' => $auditCounts['أحداث منشورة (الأثر العابر)'],
    );
    include __DIR__ . '/page_header.php';
    ems_screen_about(
        'قراءةٌ لا كتابة: الحساباتُ التابعةُ وصلاحياتُها وفصلُ الواجباتِ وسجلاتُ التدقيق. '
        . 'وفعلُها الوحيدُ الكاتبُ تصديقُ مراجعةِ الوصول (' . $GOV_DEPT['attest_code'] . ') — يشهد ولا يمنح.',
        array('الحوكمةُ والالتزامُ تملك الصلاحيةَ التقنيةَ — ومديرُ الإدارةِ لا يمنحها ولا يمنعها',
              'خرقُ فصلِ الواجباتِ يُقاس على المستندات الحيةِ لا يُوصف نصًّا'));
    ?>

    <?php if ($sodTotal > 0): ?>
    <div class="ems-card" style="padding:12px;margin:10px 0;border-inline-start:4px solid #dc3545">
        <strong style="color:#b02a37">⚠ خرق فصل الواجبات — <?php echo $sodTotal; ?> حالة</strong>
        <ul style="font-size:.84rem;margin:6px 0 0;padding-inline-start:20px">
            <?php foreach ($sodBreaches as $x): ?>
            <li><strong><?php echo htmlspecialchars($x['pair']); ?></strong> — <?php echo htmlspecialchars($x['detail']); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php else: ?>
    <div class="ems-card" style="padding:10px;margin:10px 0;border-inline-start:4px solid #198754">
        <strong style="color:#146c43">✔ صفر خرق لفصل الواجبات</strong>
        <span style="font-size:.82rem;opacity:.8"> — الأزواجُ المقيسة: <?php
            echo htmlspecialchars(implode(' · ', array_map(function ($s) { return $s['title']; }, $GOV_DEPT['sod_queries'])));
        ?>.</span>
    </div>
    <?php endif; ?>

    <div class="card"><div class="card-body table-responsive">
        <h6>الحسابات التابعة (حقل حساس — §6-3)</h6>
        <table class="table table-sm table-striped" style="width:100%">
            <thead><tr><th>#</th><th>الحساب</th><th>الاسم</th><th>الدور</th>
                <th>المسمى الوظيفي</th><th>الموظف</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($team as $x): ?>
                <tr>
                    <td><?php echo (int) $x['id']; ?></td>
                    <td><?php echo htmlspecialchars($x['username']); ?></td>
                    <td><?php echo htmlspecialchars($x['name']); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['role_name']); ?> (<?php echo (int) $x['role']; ?>)</td>
                    <td><?php echo htmlspecialchars((string) $x['job_title'] ?: '— بلا مسمى'); ?></td>
                    <td><?php echo (int) $x['employee_id'] ?: '— بلا موظف'; ?></td>
                    <td><span class="badge <?php echo $x['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                        <?php echo htmlspecialchars((string) $x['status']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$team): ?><tr><td colspan="7" style="opacity:.7">لا حساباتٍ على أدوار الإدارة بعد.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>

    <div class="card" style="margin-top:12px"><div class="card-body table-responsive">
        <h6>صلاحيات شاشات الإدارة بالدور — من محرّك الصلاحيات</h6>
        <table class="table table-sm table-striped" style="width:100%">
            <thead><tr><th>الشاشة</th>
                <?php foreach ($GOV_DEPT['team_roles'] as $rid): ?><th>دور <?php echo (int) $rid; ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($perms as $code => $byRole): ?>
                <tr>
                    <td style="font-size:.78rem;font-family:monospace"><?php echo htmlspecialchars($code); ?></td>
                    <?php foreach ($GOV_DEPT['team_roles'] as $rid):
                        $p = $byRole[(int) $rid] ?? null; ?>
                    <td style="font-size:.76rem"><?php
                        if (!$p) { echo '<span style="opacity:.5">لا منحة</span>'; }
                        else {
                            $bits = array();
                            if ((int) $p['can_view']) { $bits[] = 'عرض'; }
                            if ((int) $p['can_add']) { $bits[] = 'إضافة'; }
                            if ((int) $p['can_edit']) { $bits[] = 'تعديل'; }
                            if ((int) $p['can_delete']) { $bits[] = 'حذف'; }
                            echo htmlspecialchars(implode(' · ', $bits) ?: 'لا شيء');
                        } ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$perms): ?><tr><td colspan="<?php echo 1 + count($GOV_DEPT['team_roles']); ?>" style="opacity:.7">لا شاشاتٍ مسجَّلةً بالبادئة بعد.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>

    <div class="row" style="margin-top:12px">
        <div class="col-md-5"><div class="card"><div class="card-body">
            <h6>سجلات التدقيق الأربعة (§9-4)</h6>
            <table class="table table-sm"><tbody>
                <?php foreach ($auditCounts as $k => $v): ?>
                <tr><td style="font-size:.82rem"><?php echo htmlspecialchars($k); ?></td>
                    <td><strong><?php echo $v; ?></strong></td></tr>
                <?php endforeach; ?>
            </tbody></table>
            <p style="font-size:.76rem;opacity:.75">تُكتب من الطبقةِ المشتركةِ لا من كودِ الشاشة — بنيويًّا ولا يُتجاوز.</p>
        </div></div></div>
        <div class="col-md-7"><div class="card"><div class="card-body table-responsive">
            <h6>الحقول الحساسة المسجَّلة (AC-06)</h6>
            <table class="table table-sm table-striped">
                <thead><tr><th>السياسة</th><th>الجدول</th><th>الحقل</th><th>الحساسية</th><th>يُسجَّل اطّلاعه؟</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($sensFields, 0, 25) as $x): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $x['no_policy']); ?></td>
                        <td style="font-family:monospace;font-size:.74rem"><?php echo htmlspecialchars($x['table_name']); ?></td>
                        <td style="font-size:.78rem"><?php echo htmlspecialchars($x['field_name']); ?></td>
                        <td><?php echo htmlspecialchars((string) $x['classification_sensitivity']); ?></td>
                        <td><?php echo (int) $x['log_views_flag'] ? 'نعم' : 'لا'; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$sensFields): ?><tr><td colspan="5" style="opacity:.7">لا سياساتٍ مسجَّلةً بالبادئة بعد — تُستكمل من قاموس الحوكمة.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div></div>
    </div>

    <?php if ($canAttest): ?>
    <div class="card" style="margin-top:12px"><div class="card-body">
        <h6>تصديق مراجعة الوصول (<?php echo htmlspecialchars($GOV_DEPT['attest_code']); ?>)</h6>
        <p style="font-size:.8rem;opacity:.8">التصديقُ يشهد بصحةِ قائمةِ الفريق أعلاه ولا يمنح صلاحيةً —
            وما يحتاج تغييرًا يُطلب من مديرِ الصلاحيات.</p>
        <?php if ($attests): ?>
        <p style="font-size:.76rem">آخرُ تصديق: <strong><?php echo htmlspecialchars($attests[0]['occurred_at']); ?></strong></p>
        <?php endif; ?>
        <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
            <label style="font-size:.78rem">ملاحظة التصديق
                <input type="text" id="govAttestNote" class="form-control form-control-sm" style="min-width:280px" placeholder="راجعتُ القائمةَ وأشهد بصحتها" aria-label="راجعتُ القائمةَ وأشهد بصحتها">
            </label>
            <button class="ems-btn-primary" onclick="govDeptAttest()">أُصدّق على قائمة الفريق (<?php echo count($team); ?>)</button>
        </div>
    </div></div>
    <?php endif; ?>
</div>

<script src="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/includes/') !== false ? '../' : '../'); ?>includes/js/jquery-3.7.1.main.js"></script>
<script>
function govDeptAttest() {
    var fd = new FormData();
    fd.append('do', 'gov_attest');
    fd.append('headcount', '<?php echo count($team); ?>');
    fd.append('note', document.getElementById('govAttestNote').value || '');
    if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
    fetch('<?php echo htmlspecialchars($GOV_DEPT['attest_endpoint']); ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (!j.ok) { alert((j.code || 'GOV-500') + ': ' + (j.msg || 'تعذر التصديق')); return; }
            alert('سُجّل التصديقُ — يشهد ولا يمنح ✔');
            location.reload();
        })
        .catch(function () { alert('تعذر الاتصال — أعد المحاولة'); });
}
</script>
</body>
</html>
