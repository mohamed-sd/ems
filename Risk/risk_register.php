<?php
/**
 * Risk/risk_register.php — السجل المركزي الواحد للمخاطر (M-16 · RK-02)
 * ─────────────────────────────────────────────────────────────────────────
 * المحفظة الكاملة لمدير/محلل المخاطر والرئيس — والإدارات تراه من مساحتها
 * النطاقية (dept_risk_space). لا حذف: الإغلاق بدليل والدمج بقرار مسبَّب.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$units = risk_units_list($conn, $company_id);
$scopeSql = risk_scope_sql($RISK_FULL, $RISK_ORG_UNIT);

$fUnit  = isset($_GET['ru']) ? intval($_GET['ru']) : 0;
$fState = isset($_GET['state']) ? (string) $_GET['state'] : '';
$fLevel = isset($_GET['level']) ? (string) $_GET['level'] : '';
$w = " WHERE rr.company_id = {$company_id} AND rr.merged_into_id IS NULL {$scopeSql}";
if ($fUnit > 0) { $w .= ' AND rr.ru_id = ' . $fUnit; }
if ($fState !== '' && preg_match('~^[a-z_]+$~', $fState)) { $w .= " AND rr.state = '" . $conn->real_escape_string($fState) . "'"; }
if (in_array($fLevel, $RISK_LEVELS, true)) { $w .= " AND rr.current_level = '" . $conn->real_escape_string($fLevel) . "'"; }

$rows = array();
$res = $conn->query("SELECT rr.*, ru.ru_code, ru.name_ar ru_name, ou.name_ar owner_unit_name, u.name owner_name
                       FROM risk_register rr
                       JOIN risk_units ru ON ru.id = rr.ru_id
                       LEFT JOIN org_units ou ON ou.unit_id = rr.owner_unit_id
                       LEFT JOIN users u ON u.id = rr.risk_owner_user_id
                       {$w} ORDER BY FIELD(COALESCE(rr.current_level,''),'محظور','حرج','مرتفع','متوسط','منخفض',''), rr.updated_at DESC LIMIT 1000");
while ($x = $res->fetch_assoc()) { $rows[] = $x; }

$stats = array('total' => count($rows), 'critical' => 0, 'open' => 0);
foreach ($rows as $x) {
    if ($x['current_level'] === 'حرج' || $x['current_level'] === 'محظور') { $stats['critical']++; }
    if ($x['state'] !== 'closed') { $stats['open']++; }
}

$STATE_AR = array('classified' => 'مصنَّف', 'owner_assigned' => 'بمالك', 'inherent_assessed' => 'متأصل مقيَّم',
    'controls_linked' => 'بضوابط', 'controls_evaluated' => 'ضوابط مقيَّمة', 'residual_assessed' => 'متبقٍّ مقيَّم',
    'appetite_compared' => 'قورن بالشهية', 'treatment_planned' => 'بخطة معالجة', 'accepted' => 'مقبول',
    'monitoring' => 'مراقبة', 'reassessment' => 'إعادة تقييم', 'closed' => 'مغلق', 'reopened' => 'أعيد فتحه');

$page_title = 'إيكوبيشن | سجل المخاطر المركزي';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'سجل المخاطر المركزي';
    $header_icon = 'fas fa-triangle-exclamation';
    $header_actions = ($RISK_FULL && (!empty($__pp['can_add']) || $is_super_admin))
        ? array(array('id' => 'rskNewBtn', 'class' => 'add-btn', 'icon' => 'fas fa-plus', 'label' => 'خطر جديد'))
        : array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'سجل واحد للمنصة (RK-02)',
        'الإجمالي في نطاقك' => $stats['total'] . ' خطر',
        'المفتوح' => $stats['open'],
        'الحرج/المحظور' => $stats['critical'],
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'السجل المركزي الواحد للمخاطر — الخطر يُملك حيث نشأ (RK-01) ويُعرض لكل إدارة بزاويتها ولا يُنسخ. '
        . 'لا حذف إطلاقًا: الإغلاق بدليل واعتماد بالسقف، والدمج بقرار محلل مسبَّب.',
        array('التقييمات نسخ تاريخية لا تُكتب فوقها (RK-03)', 'القبول فوق السقف يُرفض ويُصعَّد آليًّا (RK-04)'));
    ?>

    <form method="get" class="ems-toolbar" style="align-items:flex-end">
        <label style="font-size:.8rem">الوحدة
            <select name="ru" class="form-control" style="min-width:200px">
                <option value="0">الكل</option>
                <?php foreach ($units as $u): ?>
                <option value="<?php echo (int) $u['id']; ?>" <?php echo $fUnit == $u['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($u['ru_code'] . ' · ' . $u['name_ar']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label style="font-size:.8rem">المستوى
            <select name="level" class="form-control">
                <option value="">الكل</option>
                <?php foreach ($RISK_LEVELS as $lv): ?>
                <option value="<?php echo $lv; ?>" <?php echo $fLevel === $lv ? 'selected' : ''; ?>><?php echo $lv; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label style="font-size:.8rem">الحالة
            <select name="state" class="form-control">
                <option value="">الكل</option>
                <?php foreach ($STATE_AR as $k => $v): ?>
                <option value="<?php echo $k; ?>" <?php echo $fState === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="ems-btn-secondary"><i class="fa fa-filter"></i> ترشيح</button>
    </form>

    <?php if (empty($rows)): ?>
    <div class="ems-card" id="rskEmpty"></div>
    <script>document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('rskEmpty').appendChild(EmsUI.emptyState({
            reason: 'لا مخاطر مسجلة في نطاقك بعد — الإشارات تُفرز أولًا في صندوق الإشارات',
            createHref: 'risk_signals.php', createLabel: 'إلى صندوق الإشارات'
        }));
    });</script>
    <?php else: ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped" style="width:100%">
            <thead><tr>
                <th>الرمز</th><th>العنوان</th><th>الوحدة</th><th>الإدارة المالكة</th>
                <th>مالك الخطر</th><th>النطاق</th><th>السبب الجذري</th>
                <th>المستوى</th><th>الحالة</th><th>مراجعة قبل</th><th>فتح</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td><?php echo htmlspecialchars($x['risk_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['title']); ?></td>
                    <td><?php echo htmlspecialchars($x['ru_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['owner_unit_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($x['owner_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($x['scope_type']); ?></td>
                    <td><?php echo htmlspecialchars(mb_substr((string) $x['root_cause'], 0, 40)); ?></td>
                    <td><?php $lv = (string) $x['current_level'];
                        $cls = $lv === 'حرج' || $lv === 'محظور' ? 'badge-danger' : ($lv === 'مرتفع' ? 'badge-warning' : 'badge-secondary'); ?>
                        <span class="badge <?php echo $cls; ?>"><?php echo $lv !== '' ? $lv : 'لم يقيَّم'; ?></span></td>
                    <td><?php echo $STATE_AR[$x['state']] ?? $x['state']; ?></td>
                    <td><?php echo htmlspecialchars((string) $x['review_due']); ?></td>
                    <td><a class="btn btn-sm btn-outline-dark" href="risk_card.php?id=<?php echo (int) $x['id']; ?>">ملف الخطر</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <?php endif; ?>

    <?php if ($RISK_FULL && (!empty($__pp['can_add']) || $is_super_admin)): ?>
    <div class="card" id="rskNewCard" style="display:none;margin-top:16px"><div class="card-body">
        <h5>خطر جديد (بعد الفرز — RK-05: الإشارة أولًا إن لم يكن مصدره فرزًا)</h5>
        <form id="rskNewForm" class="allforms">
            <div class="row">
                <div class="col-md-4"><label>الوحدة *<select name="ru_id" class="form-control" required>
                    <?php foreach ($units as $u): ?><option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['ru_code'] . ' · ' . $u['name_ar']); ?></option><?php endforeach; ?>
                </select></label></div>
                <div class="col-md-8"><label>العنوان *<input name="title" class="form-control" required></label></div>
                <div class="col-md-4"><label>النطاق<select name="scope_type" class="form-control">
                    <option>إداري</option><option>مؤسسي</option><option>مشروعي</option><option>موقعي</option>
                </select></label></div>
                <div class="col-md-4"><label>الإدارة المالكة (وحدة الهيكل)<input name="owner_unit_id" type="number" class="form-control" placeholder="unit_id"></label></div>
                <div class="col-md-4"><label>السبب الجذري *<input name="root_cause" class="form-control" required></label></div>
                <div class="col-md-12"><label>الوصف<textarea name="description" class="form-control"></textarea></label></div>
            </div>
            <button type="submit" class="ems-btn-primary">تسجيل الخطر</button>
            <span id="rskNewMsg" style="margin-inline-start:10px"></span>
        </form>
    </div></div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('rskNewBtn');
        if (btn) { btn.addEventListener('click', function () {
            var c = document.getElementById('rskNewCard');
            c.style.display = c.style.display === 'none' ? '' : 'none';
        }); }
        document.getElementById('rskNewForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            var fd = new FormData(this);
            fd.append('do', 'risk_create');
            if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
            fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
            .then(function (j) {
                var m = document.getElementById('rskNewMsg');
                if (j.ok) { m.textContent = '✔ سُجل ' + (j.risk_code || ''); setTimeout(function () { location.reload(); }, 800); }
                else if (j.duplicates && j.duplicates.length) {
                    m.textContent = '⚠ ' + (j.hint || 'مطابق قائم') + ' — ' + j.duplicates.map(function (d) { return d.risk_code; }).join('، ');
                } else { m.textContent = '✘ ' + (j.code || '') + ' ' + (j.msg || ''); }
            });
        });
    });
    </script>
    <?php endif; ?>
</div>
</body>
</html>
