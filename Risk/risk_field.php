<?php
/**
 * Risk/risk_field.php — مخاطر الميدان (M-16 §15-10 · الشاشة ١٩)
 * ─────────────────────────────────────────────────────────────────────────
 * حقُّ المُبلِّغِ الميدانيِّ ثلاثةٌ لا أكثر (§14-4): يرفع إشارةً · يسجّل دليلَ تنفيذِ
 * ضابطٍ يملكه · يقدّم دليلَ إنجازِ إجراءٍ مسنَدٍ إليه. ولا يسجّل خطرًا ولا يقيّم
 * ولا يقبل — «يُنشئ إشارةً لا خطرًا، تدخل الفرزَ ولا تُسجَّل خطرًا مباشرةً».
 *
 * دون اتصال (§7-2 risk.field.sync): «إشارةٌ محفوظةٌ محليًّا فورًا ثم تُزامن —
 * والمعلَّقُ يُرفع بمفتاحِه، والإعادةُ ترجع مرجعَ الأولِ ولا تُنشئ ثانيًا».
 * فالمفتاحُ يُولَّد في المتصفحِ (crypto.randomUUID) ويُحفظ في localStorage قبل أي
 * شبكة — فانقطاعُ الشبكةِ بعد الحفظِ المحليِّ لا يُفقد الإشارةَ ولا يُكررها.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$units = risk_units_list($conn, $company_id);

/* ما يملكه هذا المستخدمُ ميدانيًّا: ضوابطُه وإجراءاتُه المسنَدةُ إليه */
$myControls = array();
$st = $conn->prepare("SELECT id, control_code, name_ar, frequency, evidence_spec, is_critical,
                             effectiveness, last_verified_at
                        FROM risk_controls
                       WHERE company_id = ? AND active = 1 AND owner_user_id = ?
                       ORDER BY is_critical DESC, control_code");
$st->bind_param('ii', $company_id, $uid);
$st->execute();
$r = $st->get_result();
while ($x = $r->fetch_assoc()) { $myControls[] = $x; }
$st->close();

$myTreatments = array();
$st = $conn->prepare("SELECT t.id, t.ttype, t.plan_ar, t.due_date, t.state, t.done_evidence,
                             rr.risk_code, rr.title
                        FROM risk_treatments t
                        JOIN risk_register rr ON rr.id = t.risk_id AND rr.company_id = t.company_id
                       WHERE t.company_id = ? AND t.action_owner_user_id = ?
                         AND t.state NOT IN ('verified')
                       ORDER BY t.due_date ASC");
$st->bind_param('ii', $company_id, $uid);
$st->execute();
$r = $st->get_result();
while ($x = $r->fetch_assoc()) { $myTreatments[] = $x; }
$st->close();

/* إشاراتي الميدانيةُ الأخيرةُ وحالةُ فرزها */
$mySignals = array();
$st = $conn->prepare("SELECT id, sg_code, title, state, sync_uuid, triage_reason, created_at, linked_risk_id
                        FROM risk_signals
                       WHERE company_id = ? AND created_by = ? AND source = 'field'
                       ORDER BY id DESC LIMIT 40");
$st->bind_param('ii', $company_id, $uid);
$st->execute();
$r = $st->get_result();
while ($x = $r->fetch_assoc()) { $mySignals[] = $x; }
$st->close();

$overdue = 0;
foreach ($myTreatments as $t) { if ($t['due_date'] < date('Y-m-d')) { $overdue++; } }

$page_title = 'إيكوبيشن | مخاطر الميدان';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مخاطر الميدان';
    $header_icon = 'fas fa-mobile-screen';
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'ما تملكه أنت ميدانيًّا',
        'ضوابطي' => count($myControls),
        'إجراءاتي' => count($myTreatments),
        'متأخرة' => $overdue,
        'إشاراتي' => count($mySignals),
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'حقُّك الميدانيُّ ثلاثةٌ: رفعُ إشارةٍ · تسجيلُ دليلِ تنفيذِ ضابطٍ تملكه · تقديمُ دليلِ إنجازِ '
        . 'إجراءٍ مسنَدٍ إليك. ولا تسجّل خطرًا ولا تقيّم ولا تقبل.',
        array('الإشارةُ تُحفظ في جهازك فورًا ثم تُزامن — والإعادةُ ترجع مرجعَ الأولى ولا تُنشئ ثانية',
              'فعاليةُ الضابطِ يحكمها متحقِّقٌ مستقلٌّ لا أنت — ودليلُك شاهدٌ لا حكم',
              'إغلاقُ الإجراءِ بقبولِ المتحقِّقِ لا بتنفيذك'));
    echo ems_states_bundle('لا مهامَّ ميدانيةً في عهدتك بعد', 'الميدانيُّ يرفع إشارةً ويسجّل دليلَ ضابطِه ودليلَ إنجازِ إجرائِه — لا أكثر');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <div id="fieldSyncBar" class="ems-card rsk-syncbar is-hidden">
        <span id="fieldPendCount"></span>
        <button class="ems-btn-primary rsk-ms10" id="fieldSyncBtn">
            <i class="fa fa-rotate"></i> مزامنة المعلَّق (RSK-FIELD-SYNC)</button>
        <span id="fieldSyncMsg" class="rsk-msg"></span>
    </div>

    <div class="card"><div class="card-body">
        <h6>رفع إشارة خطر من الميدان</h6>
        <p class="rsk-note-sm">تُحفظ في جهازك أولًا — فلا تُفقد بانقطاعِ الشبكة.</p>
        <form id="fieldSigForm" class="allforms">
            <div class="row">
                <div class="col-md-6"><label>العنوان *<input name="title" class="form-control" aria-label="عنوان الإشارة الميدانية" required></label></div>
                <div class="col-md-3"><label>الوحدة المرشَّحة<select name="ru_hint_id" class="form-control" aria-label="وحدة المخاطر المرشَّحة">
                    <option value="">—</option>
                    <?php foreach ($units as $u): ?>
                    <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['ru_code'] . ' · ' . $u['name_ar']); ?></option>
                    <?php endforeach; ?>
                </select></label></div>
                <div class="col-md-3"><label>الوردية<select name="shift_ar" class="form-control" aria-label="الوردية">
                    <option value="">—</option><option>صباحية</option><option>مسائية</option><option>ليلية</option>
                </select></label></div>
                <div class="col-md-4"><label>السبب الجذري<input name="root_cause" class="form-control" aria-label="السبب الجذري"></label></div>
                <div class="col-md-4"><label>المعدة (id)<input name="equipment_id" type="number" class="form-control" aria-label="المعدة برقمها التسلسلي"></label></div>
                <div class="col-md-4"><label>الموقع (id)<input name="site_id" type="number" class="form-control" aria-label="الموقع برقمه التسلسلي"></label></div>
                <div class="col-md-12"><label>التفاصيل<textarea name="details" class="form-control" rows="2" aria-label="تفاصيل الإشارة"></textarea></label></div>
            </div>
            <button type="submit" class="ems-btn-primary">حفظ ورفع الإشارة</button>
            <span id="fieldSigMsg" class="rsk-msg"></span>
        </form>
    </div></div>

    <div class="card rsk-mt12"><div class="card-body table-responsive">
        <h6>ضوابطي — تسجيل دليل التنفيذ</h6>
        <?php if (empty($myControls)): ?>
        <p class="rsk-empty-note">لا ضوابط مسنَدة إليك — والدليلُ يسجّله مالكُ الضابطِ وحدَه.</p>
        <?php else: ?>
        <table class="table table-sm table-striped rsk-w100">
            <thead><tr><th>الرمز</th><th>الضابط</th><th>التكرار</th><th>الدليل المطلوب</th>
                <th>حرج</th><th>الفعالية</th><th>آخر تحقق</th><th>الفعل</th></tr></thead>
            <tbody>
            <?php foreach ($myControls as $c): ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['control_code']); ?></td>
                    <td><?php echo htmlspecialchars($c['name_ar']); ?></td>
                    <td><?php echo htmlspecialchars((string) $c['frequency']); ?></td>
                    <td class="rsk-fs76"><?php echo htmlspecialchars((string) $c['evidence_spec'] ?: '—'); ?></td>
                    <td><?php echo (int) $c['is_critical'] === 1 ? '<span class="badge badge-danger">حرج</span>' : '—'; ?></td>
                    <td><?php echo htmlspecialchars((string) $c['effectiveness']); ?></td>
                    <td><?php echo htmlspecialchars((string) $c['last_verified_at'] ?: '—'); ?></td>
                    <td><button class="btn btn-sm btn-secondary fieldEv"
                        data-id="<?php echo (int) $c['id']; ?>"
                        data-code="<?php echo htmlspecialchars($c['control_code']); ?>">دليل تنفيذ</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>

    <div class="card rsk-mt12"><div class="card-body table-responsive">
        <h6>إجراءاتي المسنَدة — تقديم دليل الإنجاز</h6>
        <?php if (empty($myTreatments)): ?>
        <p class="rsk-empty-note">لا إجراءات مسنَدة إليك.</p>
        <?php else: ?>
        <table class="table table-sm table-striped rsk-w100">
            <thead><tr><th>الخطر</th><th>النوع</th><th>الخطة</th><th>المهلة</th><th>الحالة</th><th>الفعل</th></tr></thead>
            <tbody>
            <?php foreach ($myTreatments as $t):
                $late = $t['due_date'] < date('Y-m-d'); ?>
                <tr <?php echo $late ? 'class="rsk-late-row"' : ''; ?>>
                    <td><?php echo htmlspecialchars($t['risk_code']); ?></td>
                    <td><?php echo htmlspecialchars((string) $t['ttype']); ?></td>
                    <td class="rsk-plan-cell"><?php echo htmlspecialchars(mb_substr((string) $t['plan_ar'], 0, 110)); ?></td>
                    <td><?php echo htmlspecialchars((string) $t['due_date']); ?>
                        <?php echo $late ? ' <span class="badge badge-warning">متأخر</span>' : ''; ?></td>
                    <td><?php echo htmlspecialchars((string) $t['state']); ?></td>
                    <td><button class="btn btn-sm btn-secondary fieldAct"
                        data-id="<?php echo (int) $t['id']; ?>"
                        data-code="<?php echo htmlspecialchars($t['risk_code']); ?>">دليل إنجاز</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>

    <div class="card rsk-mt12"><div class="card-body table-responsive">
        <h6>إشاراتي وحالة فرزها</h6>
        <?php if (empty($mySignals)): ?>
        <p class="rsk-empty-note">لا إشارات ميدانية منك بعد.</p>
        <?php else: ?>
        <table class="table table-sm table-striped rsk-w100">
            <thead><tr><th>#</th><th>القاعدة</th><th>العنوان</th><th>الحالة</th>
                <th>سبب الفرز</th><th>الخطر</th><th>مفتاح المزامنة</th><th>التاريخ</th></tr></thead>
            <tbody>
            <?php foreach ($mySignals as $s): ?>
                <tr>
                    <td><?php echo (int) $s['id']; ?></td>
                    <td><?php echo htmlspecialchars((string) $s['sg_code'] ?: 'يدوية'); ?></td>
                    <td><?php echo htmlspecialchars($s['title']); ?></td>
                    <td><?php $stt = (string) $s['state'];
                        $cls = $stt === 'pending' ? 'badge-warning' : ($stt === 'dismissed' ? 'badge-secondary' : 'badge-success'); ?>
                        <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($stt); ?></span></td>
                    <td class="rsk-fs76"><?php echo htmlspecialchars(mb_substr((string) $s['triage_reason'], 0, 60) ?: '—'); ?></td>
                    <td><?php echo !empty($s['linked_risk_id'])
                            ? '<a href="risk_card.php?id=' . (int) $s['linked_risk_id'] . '">فتح</a>' : '—'; ?></td>
                    <td class="rsk-mono-sm"><?php echo htmlspecialchars(mb_substr((string) $s['sync_uuid'], 0, 13)); ?></td>
                    <td><?php echo htmlspecialchars((string) $s['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>

    <div class="card rsk-ev-card rsk-mt12 is-hidden" id="fieldEvCard"><div class="card-body">
        <h5><span id="fieldEvTitle"></span></h5>
        <form id="fieldEvForm" class="allforms">
            <input type="hidden" name="target_id" id="fieldEvId">
            <input type="hidden" name="target_kind" id="fieldEvKind">
            <div class="row">
                <div class="col-md-12"><label>الدليل * (نص مثبت لا ادعاء)
                    <textarea name="evidence_text" class="form-control" rows="3" aria-label="نص الدليل المثبت" required></textarea></label></div>
                <div class="col-md-6"><label>مرجع الدليل (صورة/مستند)<input name="evidence_ref" class="form-control" aria-label="مرجع الدليل من صورة أو مستند"></label></div>
            </div>
            <button type="submit" class="ems-btn-primary">تسجيل الدليل</button>
            <span id="fieldEvMsg" class="rsk-msg"></span>
        </form>
    </div></div>

<script>
(function () {
    'use strict';
    var LS_KEY = 'ems_risk_field_pending_v1';

    function uuid() {
        if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
        // بديلٌ حين لا randomUUID: عشوائيةٌ كافيةٌ لمفتاحِ عطالةٍ محليٍّ لا لسرٍّ
        return 'f-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }
    function pending() {
        try { return JSON.parse(localStorage.getItem(LS_KEY) || '[]'); } catch (e) { return []; }
    }
    function savePending(list) { localStorage.setItem(LS_KEY, JSON.stringify(list)); }
    function refreshBar() {
        var p = pending(), bar = document.getElementById('fieldSyncBar');
        if (!p.length) { bar.classList.add('is-hidden'); return; }
        bar.classList.remove('is-hidden');
        document.getElementById('fieldPendCount').textContent =
            '⏳ معلَّق في جهازك: ' + p.length + ' إشارة — تُرفع بمفاتيحها ولا تتكرر';
    }

    document.getElementById('fieldSigForm').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var f = this, item = { sync_uuid: uuid() };
        ['title', 'ru_hint_id', 'shift_ar', 'root_cause', 'equipment_id', 'site_id', 'details']
            .forEach(function (k) { item[k] = (f.elements[k] && f.elements[k].value) || ''; });
        // ① الحفظُ المحليُّ أولًا — قبل أي شبكة (§15-10)
        var p = pending(); p.push(item); savePending(p); refreshBar();
        f.reset();
        document.getElementById('fieldSigMsg').textContent = '✔ حُفظت محليًّا — تُزامن الآن';
        // ② ثم المزامنةُ الفورية؛ وإن فشلت بقيت معلَّقةً بمفتاحها
        syncNow();
    });

    function syncNow() {
        var p = pending();
        if (!p.length) { return; }
        var msg = document.getElementById('fieldSyncMsg');
        msg.textContent = 'جارٍ الرفع…';
        var fd = new FormData();
        fd.append('do', 'field_sync');
        fd.append('items', JSON.stringify(p));
        if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
        fetch('risk_actions.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) { msg.textContent = '✘ ' + (j.code || '') + ' ' + (j.msg || ''); return; }
                var done = {};
                (j.created || []).forEach(function (c) { done[c.sync_uuid] = true; });
                (j.idempotent || []).forEach(function (c) { done[c.sync_uuid] = true; });
                savePending(pending().filter(function (it) { return !done[it.sync_uuid]; }));
                refreshBar();
                msg.textContent = '✔ رُفع ' + (j.created || []).length
                    + ' · معاد (رجع مرجع الأول) ' + (j.idempotent || []).length
                    + ((j.failed || []).length ? ' · فشل ' + j.failed.length : '');
                if ((j.created || []).length) { setTimeout(function () { location.reload(); }, 1200); }
            })
            .catch(function () {
                msg.textContent = '⚠ لا اتصال — المعلَّق محفوظٌ في جهازك ويُرفع بمفتاحه لاحقًا';
            });
    }
    document.getElementById('fieldSyncBtn').addEventListener('click', syncNow);
    window.addEventListener('online', syncNow);

    // أدلةُ الضوابطِ والإجراءات
    var evCard = document.getElementById('fieldEvCard');
    function openEv(id, code, kind) {
        document.getElementById('fieldEvId').value = id;
        document.getElementById('fieldEvKind').value = kind;
        document.getElementById('fieldEvTitle').textContent = kind === 'control'
            ? 'دليل تنفيذ الضابط ' + code : 'دليل إنجاز معالجة الخطر ' + code;
        document.getElementById('fieldEvMsg').textContent = kind === 'control'
            ? 'الفعاليةُ يحكمها متحقِّقٌ مستقلٌّ — ودليلُك شاهدٌ لا حكم'
            : 'الإغلاقُ بقبولِ المتحقِّقِ لا بتنفيذك';
        evCard.classList.remove('is-hidden');
        evCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    document.querySelectorAll('.fieldEv').forEach(function (b) {
        b.addEventListener('click', function () { openEv(b.dataset.id, b.dataset.code, 'control'); });
    });
    document.querySelectorAll('.fieldAct').forEach(function (b) {
        b.addEventListener('click', function () { openEv(b.dataset.id, b.dataset.code, 'treatment'); });
    });
    document.getElementById('fieldEvForm').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var kind = document.getElementById('fieldEvKind').value;
        var id = document.getElementById('fieldEvId').value;
        var fd = new FormData(this);
        fd.append('from_field', '1');
        if (kind === 'control') {
            fd.append('do', 'control_evidence');
            fd.append('control_id', id);
        } else {
            fd.append('do', 'treatment_progress');
            fd.append('treatment_id', id);
            fd.append('state', 'done');
            fd.append('done_evidence', this.elements['evidence_text'].value);
        }
        if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
        fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
        .then(function (j) {
            var m = document.getElementById('fieldEvMsg');
            if (j.ok) { m.textContent = '✔ سُجل الدليل'; setTimeout(function () { location.reload(); }, 900); }
            else { m.textContent = '✘ ' + (j.code || '') + ' ' + (j.msg || ''); }
        });
    });

    refreshBar();
})();
</script>
</div>
</body>
</html>
