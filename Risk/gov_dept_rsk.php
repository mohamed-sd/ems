<?php
/**
 * Risk/gov_dept_rsk.php — حوكمة إدارة المخاطر المؤسسية (M-16 · الشاشة ٢٠)
 * ─────────────────────────────────────────────────────────────────────────
 * قراءةٌ لا كتابة، وفعلُها الوحيدُ الكاتبُ تصديقُ مراجعةِ الوصول:
 * «المديرُ يصدّق على قائمةِ فريقه — والتصديقُ لا يمنح صلاحيةً بل يشهد بصحتها» (§7-2).
 * فلا سطرَ هنا يكتب في جداولِ الصلاحيات: الحوكمةُ والالتزامُ تملك الصلاحيةَ
 * التقنيةَ ومديرُ المخاطرِ لا يمنحها ولا يمنعها (§15-1 · RSK-0237-ب).
 *
 * وتعرض فصلَ الواجباتِ المتعارضةِ داخلَ الإدارة (§9-3) قياسًا على الحساباتِ الحية
 * لا نصًّا: من يجمع واجبين متعارضين يظهر هنا بالاسمِ ليُفصل.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../app/Services/Risk/RiskEvents.php';
ems_shell_axes($__pp);

$canAttest = $is_super_admin || !empty($__pp['can_edit']);

/* ① الحساباتُ التابعةُ لإدارةِ المخاطر (حقلٌ حساس — §6-3) */
$team = array();
$res = $conn->query("SELECT u.id, u.username, u.name, u.role, u.status, r.name role_name,
                            jt.name job_title, u.employee_id
                       FROM users u
                       LEFT JOIN roles r ON r.id = u.role
                       LEFT JOIN employees e ON e.id = u.employee_id
                       LEFT JOIN job_titles jt ON jt.id = e.job_title_id
                      WHERE u.company_id = {$company_id} AND u.role IN (28, 29, 30)
                      ORDER BY u.role, u.id");
while ($x = $res->fetch_assoc()) { $team[] = $x; }

/* ② صلاحياتُ العشرين شاشةً بالدور — تُقرأ من محرّكِ الصلاحياتِ لا تُوصف */
$perms = array();
$res = $conn->query("SELECT mo.code, mo.name, rp.role_id, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
                       FROM role_permissions rp
                       JOIN modules mo ON mo.id = rp.module_id
                      WHERE mo.code LIKE 'Risk/%' AND rp.role_id IN (28, 29, 30)
                      ORDER BY mo.code, rp.role_id");
while ($x = $res->fetch_assoc()) { $perms[$x['code']][(int) $x['role_id']] = $x; }

/* ③ فصلُ الواجباتِ المتعارضةِ (§9-3) — قياسٌ على الحساباتِ الحية.
   الزوجُ المتعارضُ الأولُ في هذه الإدارة: من يشغّل ضابطًا ثم يتحقق من ضابطِ نفسِه
   (RK-07)، والثاني: من ينفّذ إجراءَ معالجةٍ ثم يقبل دليلَ إنجازِه. */
$sodCtl = array();
$res = $conn->query("SELECT c.control_code, c.name_ar, u.name owner_name, c.owner_user_id
                       FROM risk_controls c
                       LEFT JOIN users u ON u.id = c.owner_user_id
                      WHERE c.company_id = {$company_id} AND c.active = 1 AND c.is_critical = 1
                        AND c.verifier_user_id IS NOT NULL
                        AND c.verifier_user_id = c.owner_user_id");
while ($x = $res->fetch_assoc()) { $sodCtl[] = $x; }

$sodTreat = array();
$res = $conn->query("SELECT t.id, rr.risk_code, u.name person, t.action_owner_user_id
                       FROM risk_treatments t
                       JOIN risk_register rr ON rr.id = t.risk_id AND rr.company_id = t.company_id
                       LEFT JOIN users u ON u.id = t.action_owner_user_id
                      WHERE t.company_id = {$company_id}
                        AND t.verified_by IS NOT NULL
                        AND t.verified_by = t.action_owner_user_id");
while ($x = $res->fetch_assoc()) { $sodTreat[] = $x; }

/* ④ سجلاتُ التدقيقِ الأربعةُ (§9-4) — عدَّاداتٌ حيةٌ لا وصفٌ */
$auditCounts = array(
    'أحداث منشورة (الأثر العابر)' => (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
        WHERE company_id = {$company_id} AND source_module = 'risk'")->fetch_assoc()['c'],
    'سجل التصدير (تسعة بنود)' => (int) $conn->query("SELECT COUNT(*) c FROM risk_export_log
        WHERE company_id = {$company_id}")->fetch_assoc()['c'],
    /* ⇐ INJ-0579 · «`COUNT` سجلِّ الاطّلاعِ يساوي عددَ صفوفِ **الكيانِ الحالي فقط**» —
         وكان يعدُّ الكياناتِ كلَّها فيُعرض للمراجعِ رقمٌ ليس رقمَه. */
    'سجل الاطّلاع الحساس' => (int) $conn->query("SELECT COUNT(*) c FROM sensitive_read_log
        WHERE company_id = {$company_id}")->fetch_assoc()['c'],
    'سجل رفض الحارس' => (int) $conn->query("SELECT COUNT(*) c FROM action_execution_log
        WHERE company_id = {$company_id} AND denied_by_guard = 1")->fetch_assoc()['c'],
);

/* ⑤ الحقولُ الحساسةُ المسجَّلةُ في هذه الإدارة (AC-06) */
$sensFields = array();
$res = $conn->query("SELECT no_policy, table_name, field_name, classification_sensitivity,
                            from_visible_to, log_views_flag, exportable_flag
                       FROM scr_sensitive_fields
                      WHERE company_id = {$company_id} AND table_name LIKE 'risk%'
                      ORDER BY no_policy");
while ($x = $res->fetch_assoc()) { $sensFields[] = $x; }

$sodTotal = count($sodCtl) + count($sodTreat);

$page_title = 'إيكوبيشن | حوكمة إدارة المخاطر';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
    <?php
    $header_title = 'حوكمة إدارة المخاطر المؤسسية';
    $header_icon = 'fas fa-scale-unbalanced';
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'حوكمة الإدارة بنطاقها',
        'الحسابات التابعة' => count($team),
        'خروق فصل الواجبات' => $sodTotal,
        'حقول حساسة مسجَّلة' => count($sensFields),
        'أحداث منشورة' => $auditCounts['أحداث منشورة (الأثر العابر)'],
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'قراءة لا كتابة: الحسابات التابعة وصلاحياتها وفصل الواجبات وسجلات التدقيق. '
        . 'وفعلها الوحيد الكاتب تصديق مراجعة الوصول — والتصديق يشهد ولا يمنح.',
        array('الحوكمة والالتزام تملك الصلاحية التقنية — ومدير المخاطر لا يمنحها ولا يمنعها',
              'خرق فصل الواجبات يقاس على الحسابات الحية لا يوصف نصا'));
    ?>

    <?php if ($sodTotal > 0): ?>
    <div class="ems-card rskg-alert-bad">
        <strong class="rskg-text-bad">⚠ خرق فصل الواجبات — <?php echo $sodTotal; ?> حالة</strong>
        <ul class="rskg-list">
            <?php foreach ($sodCtl as $x): ?>
            <li>الضابط الحرج <strong><?php echo htmlspecialchars($x['control_code']); ?></strong>
                — متحققه هو مالكه (<?php echo htmlspecialchars((string) $x['owner_name']); ?>) · RK-07 يرفض التحقق</li>
            <?php endforeach; ?>
            <?php foreach ($sodTreat as $x): ?>
            <li>معالجة الخطر <strong><?php echo htmlspecialchars($x['risk_code']); ?></strong>
                — قبل دليل إنجازه منفذه (<?php echo htmlspecialchars((string) $x['person']); ?>) · §9-3</li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php else: ?>
    <div class="ems-card rskg-alert-ok">
        <strong class="rskg-text-ok">✔ صفر خرق لفصل الواجبات</strong>
        <span class="rskg-soft"> — لا متحقق يتحقق من ضابط نفسه، ولا منفذ يقبل دليل إنجازه.</span>
    </div>
    <?php endif; ?>

    <div class="card"><div class="card-body table-responsive">
        <h6>الحسابات التابعة (حقل حساس — §6-3)</h6>
        <table class="table table-sm table-striped rskg-wfull">
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
            </tbody>
        </table>
    </div></div>

    <div class="card rskg-mt"><div class="card-body table-responsive">
        <h6>صلاحيات شاشات المخاطر بالدور — من محرك الصلاحيات</h6>
        <table class="table table-sm table-striped rskg-wfull">
            <thead><tr><th>الشاشة</th>
                <th>28 إدارة المخاطر</th><th>29 محلل المخاطر</th><th>30 مشرف المخاطر</th></tr></thead>
            <tbody>
            <?php foreach ($perms as $code => $byRole): ?>
                <tr>
                    <td class="rskg-mono"><?php echo htmlspecialchars($code); ?></td>
                    <?php foreach (array(28, 29, 30) as $rid):
                        $p = $byRole[$rid] ?? null; ?>
                    <td class="rskg-xs"><?php
                        if (!$p) { echo '<span class="rskg-dim">لا منحة</span>'; }
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
            </tbody>
        </table>
        <p class="rskg-note">
            الدور 30 قراءة خالصة على العشرين — ولا حذف لأي دور في هذه الإدارة (لا حذف إطلاقا).</p>
    </div></div>

    <div class="row rskg-mt">
        <div class="col-md-5"><div class="card"><div class="card-body">
            <h6>سجلات التدقيق الأربعة (§9-4)</h6>
            <table class="table table-sm">
                <tbody>
                <?php foreach ($auditCounts as $k => $v): ?>
                    <tr><td class="rskg-sm"><?php echo htmlspecialchars($k); ?></td>
                        <td><strong><?php echo $v; ?></strong></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="rskg-hint">تكتب من الطبقة المشتركة لا من كود الشاشة — بنيويا ولا يتجاوز.</p>
        </div></div></div>
        <div class="col-md-7"><div class="card"><div class="card-body table-responsive">
            <h6>الحقول الحساسة المسجلة (AC-06)</h6>
            <?php if (empty($sensFields)): ?>
            <p class="rskg-missing">لا حقول حساسة مسجلة — AC-06 راسبة حتى تسجل.</p>
            <?php else: ?>
            <table class="table table-sm table-striped">
                <thead><tr><th>السياسة</th><th>الحقل</th><th>التصنيف</th>
                    <th>يظهر ل</th><th>يسجل الاطلاع</th><th>يصدر</th></tr></thead>
                <tbody>
                <?php foreach ($sensFields as $x): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($x['no_policy']); ?></td>
                        <td class="rskg-mono74"><?php echo htmlspecialchars($x['table_name'] . '.' . $x['field_name']); ?></td>
                        <td class="rskg-fs78"><?php echo htmlspecialchars((string) $x['classification_sensitivity']); ?></td>
                        <td class="rskg-xs"><?php echo htmlspecialchars(mb_substr((string) $x['from_visible_to'], 0, 50)); ?></td>
                        <td><?php echo htmlspecialchars((string) $x['log_views_flag']); ?></td>
                        <td><?php echo htmlspecialchars((string) $x['exportable_flag']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div></div></div>
    </div>

    <?php if ($canAttest): ?>
    <div class="card rskg-mt"><div class="card-body">
        <h6>تصديق مراجعة الوصول (GOV-RSK-ATTEST)</h6>
        <p class="rskg-desc">
            تشهد بصحة قائمة فريقك وصلاحياتهم — ولا يمنح التصديق صلاحية ولا يسلبها.</p>
        <form id="attForm" class="allforms">
            <div class="row">
                <div class="col-md-4"><label>نطاق التصديق *
                    <input name="scope_code" aria-label="نطاق التصديق" class="form-control" value="RISK-DEPT-<?php echo (int) $company_id; ?>" required></label></div>
                <div class="col-md-2"><label>عدد الحسابات
                    <input name="headcount" aria-label="عدد الحسابات" type="number" class="form-control" value="<?php echo count($team); ?>" readonly></label></div>
                <div class="col-md-6"><label>ملاحظة<input name="note" aria-label="ملاحظة" class="form-control"></label></div>
            </div>
            <button type="submit" class="ems-btn-primary">تصديق</button>
            <span id="attMsg" class="rskg-msg"></span>
        </form>
    </div></div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('attForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            var fd = new FormData(this);
            fd.append('do', 'gov_attest');
            if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
            fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
            .then(function (j) {
                var m = document.getElementById('attMsg');
                if (j.ok) { m.textContent = '✔ صدق — حدث #' + (j.event || 0) + ' (يشهد ولا يمنح)'; }
                else { m.textContent = '✘ ' + (j.code || '') + ' ' + (j.msg || ''); }
            });
        });
    });
    </script>
    <?php endif; ?>
    <?php
    /* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
       ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
    if (function_exists('ems_states_bundle')) {
        echo ems_states_bundle('لا بيانات حوكمة لهذه الإدارة بعد',
                               'تقاس الحسابات وفصل الواجبات على السجلات الحية فتظهر هنا');
    }
    ?>
</div>
</body>
</html>
