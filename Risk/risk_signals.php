<?php
/**
 * Risk/risk_signals.php — صندوق الإشارات والفرز (M-16 · RK-05)
 * «ليس كل حدث خطرًا»: يُهمل بوسم/يُربط بقائم/يُنشئ جديدًا/يُصعَّد فورًا.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$units = risk_units_list($conn, $company_id);
$canTriage = $RISK_FULL && (!empty($__pp['can_edit']) || $is_super_admin);

/* ⇐ INJ-0109 · وحداتُ الإدارةِ لمنتقي «الوحدة المالكة» — بشرطِ الكيان */
$ORG_UNITS = array();
$__ou = $conn->query('SELECT unit_id, name_ar FROM org_units
                       WHERE company_id = ' . (int) $company_id . ' ORDER BY name_ar');
while ($__ou && ($__x = $__ou->fetch_assoc())) { $ORG_UNITS[] = $__x; }

$fState = isset($_GET['state']) ? (string) $_GET['state'] : 'pending';
$w = "WHERE s.company_id = {$company_id}";
if (in_array($fState, array('pending', 'dismissed', 'linked', 'converted', 'escalated'), true)) {
    $w .= " AND s.state = '{$fState}'";
}
$rows = array();
$r = $conn->query("SELECT s.*, ru.ru_code, u.name creator FROM risk_signals s
                    LEFT JOIN risk_units ru ON ru.id = s.ru_hint_id
                    LEFT JOIN users u ON u.id = s.created_by {$w}
                   ORDER BY s.created_at DESC LIMIT 500");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }
$pendingN = 0;
$r = $conn->query("SELECT COUNT(*) c FROM risk_signals WHERE company_id = {$company_id} AND state = 'pending'");
if ($r) { $pendingN = (int) $r->fetch_assoc()['c']; }

$page_title = 'إيكوبيشن | إشارات الخطر والفرز';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('risk', 'المؤشرات');
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell ems-doc-cycle">
    <?php
    $header_title = 'إشارات الخطر والفرز';
    $header_icon = 'fas fa-satellite-dish';
    $header_actions = array(array('id' => 'sigNewBtn', 'class' => 'add-btn', 'icon' => 'fas fa-plus', 'label' => 'إشارة جديدة'));
    $header_back = array();
    $header_context = array('المصدر' => 'risk_signals', 'تنتظر الفرز' => $pendingN, 'المعروض' => count($rows) . ' إشارة');
    include('../includes/page_header.php');
    ems_screen_about(
        'الإشارة تُفرز قبل أن تصير خطرًا (RK-05): تُهمل بسبب موسوم، أو تُربط بخطر قائم فيُعاد تقييمه، '
        . 'أو تُنشئ خطرًا جديدًا، أو تُصعَّد فورًا للرئيس في اليوم نفسه.',
        array('التحويل الآلي لكل بلاغ إلى خطر يُنتج آلاف المخاطر المزعجة — الفرز حارس المعنى'));
    echo ems_next_step($canTriage
        ? 'فرزُ المعلَّقِ بقرارٍ مسبَّبٍ — إهمالٌ بوسمٍ أو ربطٌ بقائمٍ أو تحويلٌ بوحدةٍ مالكةٍ أو تصعيد'
        : 'الفرزُ بيدِ إدارةِ المخاطر — وإشارتُك تدخل الصندوقَ وتنتظر قرارَها');
    echo ems_states_bundle('لا إشاراتٍ بهذه الحالة', 'الإشارةُ تُفرز قبل أن تصيرَ خطرًا — بدّل تبويبَ الحالةِ أو سجّل إشارةً جديدة');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <div class="ems-toolbar">
        <?php foreach (array('pending' => 'تنتظر', 'converted' => 'حُوّلت', 'linked' => 'رُبطت', 'dismissed' => 'أُهملت بوسم', 'escalated' => 'صُعِّدت') as $k => $v): ?>
        <a class="ems-btn-<?php echo $fState === $k ? 'primary' : 'secondary'; ?>" href="?state=<?php echo $k; ?>"><?php echo $v; ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($rows)): ?>
    <div class="ems-card" id="sigEmpty"></div>
    <script>document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('sigEmpty').appendChild(EmsUI.emptyState({ reason: 'لا إشارات بهذه الحالة' }));
    });</script>
    <?php else: ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped rsk-w100">
            <thead><tr><th>#</th><th>المصدر</th><th>القاعدة</th><th>العنوان</th><th>الوحدة المرشحة</th>
                <th>السبب الجذري</th><th>المُنشئ</th><th>التاريخ</th>
                <?php if ($canTriage && $fState === 'pending'): ?><th>الفرز</th><?php endif; ?>
                <?php if ($fState !== 'pending'): ?><th>قرار الفرز</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($rows as $s): ?>
            <tr>
                <td><?php echo (int) $s['id']; ?></td>
                <td><?php echo array('auto' => 'آلي', 'manual' => 'يدوي', 'field' => 'ميداني')[$s['source']] ?? $s['source']; ?></td>
                <td><?php echo htmlspecialchars((string) $s['sg_code'] ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($s['title']); ?></td>
                <td><?php echo htmlspecialchars((string) $s['ru_code'] ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($s['root_cause']); ?></td>
                <td><?php echo htmlspecialchars((string) $s['creator']); ?></td>
                <td><?php echo $s['created_at']; ?></td>
                <?php if ($canTriage && $fState === 'pending'): ?>
                <td class="rsk-triage-cell">
                    <select class="sigDecision form-control form-control-sm rsk-inline-auto" aria-label="قرار الفرز" data-id="<?php echo (int) $s['id']; ?>">
                        <option value="convert">يُنشئ خطرًا</option>
                        <option value="link">يُربط بقائم</option>
                        <option value="dismiss">يُهمل بوسم</option>
                        <option value="escalate">يُصعَّد فورًا</option>
                    </select>
                    <input class="sigReason form-control form-control-sm rsk-inline-150" placeholder="السبب المكتوب *" aria-label="السبب المكتوب">
                    <input class="sigExtra form-control form-control-sm rsk-inline-110" placeholder="رقم الخطر/الوحدة" aria-label="رقم الخطر/الوحدة">
                    <?php /* ⇐ INJ-0109 · «المستندُ المنتَج: خطرٌ مسجَّلٌ **بمالكٍ ووحدة**»
                             — فلا خطرَ يُسجَّل بلا وحدةٍ مالكة. وكان النموذجُ يرسل
                             `ru_id` وحدَه، فيُكتب `owner_unit_id = NULL`؛ وحارسُ
                             النطاقِ يطابق بالمساواة و**NULL لا يساوي شيئًا** —
                             فالخطرُ يختفي من سجلِّ إدارتِه إلى الأبد. */ ?>
                    <select class="sigOwner form-control form-control-sm rsk-inline-160"
                            aria-label="الوحدة المالكة (إلزامية للتحويل)">
                        <option value="">— الوحدة المالكة * —</option>
                        <?php foreach ($ORG_UNITS as $ou): ?>
                            <option value="<?php echo (int) $ou['unit_id']; ?>"><?php
                                echo htmlspecialchars((string) $ou['name_ar'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-secondary sigGo">نفّذ</button>
                </td>
                <?php endif; ?>
                <?php if ($fState !== 'pending'): ?>
                <td><?php echo htmlspecialchars((string) $s['triage_reason']);
                    echo $s['linked_risk_id'] ? ' → <a href="risk_card.php?id=' . (int) $s['linked_risk_id'] . '">الخطر</a>' : ''; ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?></tbody>
        </table>
    </div></div>
    <?php endif; ?>

    <div class="card rsk-new-card is-hidden" id="sigNewCard"><div class="card-body">
        <h6>إشارة جديدة — حق كل إدارة (تدخل الفرز ولا تُسجَّل خطرًا مباشرة)</h6>
        <form id="sigNewForm" class="allforms">
            <div class="row">
                <div class="col-md-6"><label>العنوان *<input name="title" class="form-control" aria-label="عنوان الإشارة" required></label></div>
                <div class="col-md-3"><label>الوحدة المرشحة<select name="ru_hint_id" class="form-control" aria-label="وحدة المخاطر المرشحة">
                    <option value="">—</option>
                    <?php foreach ($units as $u): ?><option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['ru_code']); ?></option><?php endforeach; ?>
                </select></label></div>
                <div class="col-md-3"><label>السبب الجذري<input name="root_cause" class="form-control" aria-label="السبب الجذري"></label></div>
                <div class="col-md-12"><label>التفاصيل<textarea name="details" class="form-control" aria-label="تفاصيل الإشارة"></textarea></label></div>
            </div>
            <button class="ems-btn-primary" type="submit">تسجيل الإشارة</button>
            <span id="sigNewMsg"></span>
        </form>
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
    var nb = document.getElementById('sigNewBtn');
    if (nb) { nb.addEventListener('click', function () {
        document.getElementById('sigNewCard').classList.toggle('is-hidden');
    }); }
    var nf = document.getElementById('sigNewForm');
    if (nf) { nf.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var d = { do: 'signal_create' };
        new FormData(nf).forEach(function (v, k) { d[k] = v; });
        post(d, function (j) {
            document.getElementById('sigNewMsg').textContent = j.ok ? '✔ سُجلت #' + j.id : '✘ ' + (j.msg || '');
            if (j.ok) { setTimeout(function () { location.reload(); }, 700); }
        });
    }); }
    document.querySelectorAll('.sigGo').forEach(function (b) {
        b.addEventListener('click', function () {
            var tr = b.closest('tr');
            var dec = tr.querySelector('.sigDecision');
            var d = { do: 'signal_triage', signal_id: dec.dataset.id, decision: dec.value,
                      reason: tr.querySelector('.sigReason').value };
            var extra = tr.querySelector('.sigExtra').value;
            if (dec.value === 'link') { d.risk_id = extra; }
            if (dec.value === 'convert') {
                d.ru_id = extra;
                /* INJ-0109: الوحدةُ المالكةُ تُرسَل مع التحويل — والخدمةُ ترفض غيابَها 422 */
                var own = tr.querySelector('.sigOwner');
                d.owner_unit_id = own ? own.value : '';
                if (!d.owner_unit_id) {
                    alert('RSK-422: الوحدةُ المالكةُ إلزاميةٌ — لا خطرَ يُسجَّل بلا وحدةٍ مالكة');
                    return;
                }
            }
            post(d, function (j) {
                if (j.ok) { location.reload(); }
                else { alert((j.code || '') + ': ' + (j.msg || '') + (j.duplicates ? '\nمطابق قائم — اربط به' : '')); }
            });
        });
    });
});
</script>
</body>
</html>
