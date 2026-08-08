<?php
/**
 * Risk/risk_incidents.php — الحوادث والوقائع (M-16 §11-2 · الشاشة ١٢)
 * ─────────────────────────────────────────────────────────────────────────
 * ثلاثةُ أنواعٍ لا تُخلط: «واقعة» حدثت وأنتجت أثرًا · «كادت تقع» لم تنتج أثرًا
 * كاملًا لكنها تكشف تعرضًا ومن أهمِّ مصادرِ الإشارات · «واقعةُ خسارة» خطرٌ تحقق.
 * ولا تُقيَّد من المخاطر (RK-06): التعرضُ تقديرٌ وحدثٌ يُنشر للمالية، والماليةُ
 * وحدَها تقرر المعالجةَ المحاسبية. والتصحيحُ بمرجعٍ لا حذفًا.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$units = risk_units_list($conn, $company_id);
$canLog = $is_super_admin || !empty($__pp['can_add']);

$fType = isset($_GET['itype']) ? (string) $_GET['itype'] : '';
$TYPES = array('واقعة', 'واقعة كادت تقع', 'واقعة خسارة');
$w = " WHERE i.company_id = {$company_id}";
if (in_array($fType, $TYPES, true)) { $w .= " AND i.itype = '" . $conn->real_escape_string($fType) . "'"; }
// الزاوية النطاقية: من لا يملك المحفظة الكاملة يرى وقائع وحدات مخاطر إدارته
if (!$RISK_FULL) {
    $u = (int) $RISK_ORG_UNIT;
    $w .= $u > 0
        ? " AND (i.realized_risk_id IS NULL OR EXISTS (SELECT 1 FROM risk_register rr
                 WHERE rr.id = i.realized_risk_id AND rr.company_id = i.company_id AND rr.owner_unit_id = {$u}))"
        : ' AND 1=0';
}

$rows = array();
$res = $conn->query("SELECT i.*, ru.ru_code, rr.risk_code, u.name creator
                       FROM risk_incidents i
                       LEFT JOIN risk_units ru ON ru.id = i.ru_id
                       LEFT JOIN risk_register rr ON rr.id = i.realized_risk_id
                       LEFT JOIN users u ON u.id = i.created_by
                       {$w} ORDER BY i.occurred_at DESC LIMIT 500");
while ($x = $res->fetch_assoc()) { $rows[] = $x; }

$stats = array('total' => count($rows), 'near' => 0, 'loss' => 0, 'linked' => 0, 'injuries' => 0);
foreach ($rows as $x) {
    if ($x['itype'] === 'واقعة كادت تقع') { $stats['near']++; }
    if ($x['itype'] === 'واقعة خسارة') { $stats['loss']++; }
    if (!empty($x['realized_risk_id'])) { $stats['linked']++; }
    $stats['injuries'] += (int) $x['injury_count'];
}

$page_title = 'إيكوبيشن | الحوادث والوقائع';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'الحوادث والوقائع';
    $header_icon = 'fas fa-car-burst';
    $header_actions = $canLog
        ? array(array('id' => 'incNewBtn', 'class' => 'add-btn', 'icon' => 'fas fa-plus', 'label' => 'تسجيل واقعة'))
        : array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'وقائع نطاقك',
        'الإجمالي' => $stats['total'],
        'كادت تقع' => $stats['near'],
        'وقائع خسارة' => $stats['loss'],
        'حققت خطرًا قائمًا' => $stats['linked'],
        'إصابات' => $stats['injuries'],
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'الواقعةُ حدثٌ وقع بالفعل — والخطرُ لم يقع بعد. و«كادت تقع» من أهمِّ مصادرِ الإشاراتِ '
        . 'ولا تُهمَل: تسجيلُها يولّد إشارةَ SG-14 آليًّا تدخل الفرز.',
        array('تقييمُ الواقعةِ لا يُنشئ قيدًا ماليًّا — التعرضُ تقديرٌ وحدثٌ يُنشر للمالية (RK-06)',
              'واقعةٌ تُحقق خطرًا قائمًا تُعيد تقييمَه حتمًا — لا اختيارًا',
              'التصحيحُ واقعةٌ جديدةٌ بمرجعِ الأصلِ — ولا حذفَ إطلاقًا'));
    ?>

    <form method="get" class="ems-toolbar" style="align-items:flex-end">
        <label style="font-size:.8rem">النوع
            <select name="itype" class="form-control" style="min-width:190px">
                <option value="">الكل</option>
                <?php foreach ($TYPES as $t): ?>
                <option value="<?php echo $t; ?>" <?php echo $fType === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="ems-btn-secondary"><i class="fa fa-filter"></i> ترشيح</button>
    </form>

    <?php if (empty($rows)): ?>
    <div class="ems-card" id="incEmpty"></div>
    <script>document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('incEmpty').appendChild(EmsUI.emptyState({
            reason: 'لا وقائع مسجلة في نطاقك — والواقعة تُسجَّل بوقت وقوعها لا بوقت تسجيلها'
        }));
    });</script>
    <?php else: ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped" style="width:100%">
            <thead><tr>
                <th>الرمز</th><th>النوع</th><th>العنوان</th><th>وقت الوقوع</th>
                <th>الوحدة</th><th>السبب الجذري</th><th>إصابات</th><th>توقف (ساعة)</th>
                <th>تعرض مقدَّر</th><th>حقق الخطر</th><th>الحالة</th><th>المُنشئ</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td><?php echo htmlspecialchars($x['incident_code']); ?></td>
                    <td><?php
                        $cls = $x['itype'] === 'واقعة خسارة' ? 'badge-danger'
                             : ($x['itype'] === 'واقعة كادت تقع' ? 'badge-warning' : 'badge-secondary'); ?>
                        <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($x['itype']); ?></span></td>
                    <td><?php echo htmlspecialchars($x['title']); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['occurred_at']); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['ru_code'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars(mb_substr((string) $x['root_cause'], 0, 40) ?: '—'); ?></td>
                    <td><?php echo (int) $x['injury_count']; ?></td>
                    <td><?php echo (float) $x['downtime_hours']; ?></td>
                    <td><?php echo $x['loss_estimate'] === null ? '—'
                            : htmlspecialchars(number_format((float) $x['loss_estimate'], 2) . ' ' . $x['currency']); ?></td>
                    <td><?php echo !empty($x['risk_code'])
                            ? '<a href="risk_card.php?id=' . (int) $x['realized_risk_id'] . '">'
                              . htmlspecialchars($x['risk_code']) . '</a>' : '—'; ?></td>
                    <td><?php echo htmlspecialchars($x['state']); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['creator'] ?: '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <?php endif; ?>

    <?php if ($canLog): ?>
    <div class="card" id="incNewCard" style="display:none;margin-top:16px"><div class="card-body">
        <h5>تسجيل واقعة</h5>
        <form id="incNewForm" class="allforms">
            <div class="row">
                <div class="col-md-3"><label>النوع *<select name="itype" class="form-control" required>
                    <?php foreach ($TYPES as $t): ?><option><?php echo $t; ?></option><?php endforeach; ?>
                </select></label></div>
                <div class="col-md-3"><label>وقت الوقوع *
                    <input name="occurred_at" type="datetime-local" class="form-control" required></label>
                    <span style="font-size:.72rem;opacity:.7">وقتُ الواقعةِ لا وقتُ التسجيل</span></div>
                <div class="col-md-6"><label>العنوان *<input name="title" class="form-control" required></label></div>
                <div class="col-md-4"><label>الوحدة المرشَّحة<select name="ru_id" class="form-control">
                    <option value="">—</option>
                    <?php foreach ($units as $u): ?>
                    <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['ru_code'] . ' · ' . $u['name_ar']); ?></option>
                    <?php endforeach; ?>
                </select></label></div>
                <div class="col-md-4"><label>السبب الجذري<input name="root_cause" class="form-control"></label></div>
                <div class="col-md-4"><label>خطر قائم تحقق (id)<input name="realized_risk_id" type="number" class="form-control"></label>
                    <span style="font-size:.72rem;opacity:.7">الربطُ يُعيد تقييمَ الخطرِ حتمًا</span></div>
                <div class="col-md-2"><label>إصابات<input name="injury_count" type="number" min="0" value="0" class="form-control"></label></div>
                <div class="col-md-2"><label>توقف (ساعة)<input name="downtime_hours" type="number" step="0.25" min="0" value="0" class="form-control"></label></div>
                <div class="col-md-3"><label>تعرض مقدَّر<input name="loss_estimate" type="number" step="0.01" class="form-control"></label></div>
                <div class="col-md-2"><label>العملة<input name="currency" class="form-control" maxlength="8"></label></div>
                <div class="col-md-3"><label>المعدة (id)<input name="equipment_id" type="number" class="form-control"></label></div>
                <div class="col-md-12"><label>التفاصيل<textarea name="details" class="form-control"></textarea></label></div>
            </div>
            <button type="submit" class="ems-btn-primary">تسجيل الواقعة (RSK-INCIDENT-LOG)</button>
            <span id="incNewMsg" style="margin-inline-start:10px"></span>
        </form>
    </div></div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('incNewBtn');
        if (btn) { btn.addEventListener('click', function () {
            var c = document.getElementById('incNewCard');
            c.style.display = c.style.display === 'none' ? '' : 'none';
        }); }
        document.getElementById('incNewForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            var fd = new FormData(this);
            fd.append('do', 'incident_log');
            if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
            fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
            .then(function (j) {
                var m = document.getElementById('incNewMsg');
                if (j.ok) { m.textContent = '✔ سُجلت ' + (j.incident_code || ''); setTimeout(function () { location.reload(); }, 800); }
                else { m.textContent = '✘ ' + (j.code || '') + ' ' + (j.msg || ''); }
            });
        });
    });
    </script>
    <?php endif; ?>
</div>
</body>
</html>
