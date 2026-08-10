<?php
/**
 * Governance/guard_denials.php — المحاولاتُ الممنوعة (M-14 · الشاشة ١٦ · 18 عمودًا)
 * ─────────────────────────────────────────────────────────────────────────
 * «المنعُ المتكرر يكشف: إما حاجةً لاستثناءٍ أو خطأً في تصنيف الحماية أو
 * محاولةَ تجاوز» (§7-2 denial.review). الشاشةُ تقرأ سجلَّ المنع الحيَّ
 * (guard_denials + action_execution_log) وتتيح مراجعةَ كلِّ محاولةٍ
 * بالتصنيف الرباعي — والمراجعةُ append-only لا تُحذف.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header('Location: ../login.php'); exit(); }

$__pp = check_page_permissions($conn, 'Governance/guard_denials.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض المحاولات الممنوعة', 'GOV-PERM-403', 'شاشات الحوكمة يمنحها مدير الصلاحيات');
}
$canReview = $is_super_admin || !empty($__pp['can_edit']) || !empty($__pp['can_add']);
ems_shell_axes($__pp);

/* ① المحاولاتُ الممنوعةُ من سجل الحارس المخصص (guard_denials) */
$denials = array();
$res = $conn->query("SELECT d.deny_id, d.company_id, d.guard_code, d.person_id, d.attempted_ref, d.reason_code, d.at,
                            u.name person_name, r.review_code, r.classification, r.decision_note, r.created_at review_at
                       FROM guard_denials d
                       LEFT JOIN users u ON u.id = d.person_id
                       LEFT JOIN gov_denial_reviews r ON r.denial_id = d.deny_id AND r.company_id = d.company_id
                      WHERE d.company_id = {$company_id}
                      ORDER BY d.at DESC LIMIT 400");
if ($res) { while ($x = $res->fetch_assoc()) { $denials[] = $x; } }

/* ② رفضُ الحارس المركزيِّ (action_execution_log denied) — الطبقةُ الثانية للرصد */
$centralDenied = array();
$res = $conn->query("SELECT l.r_id, l.action_code, l.person_id, l.subject_ref, l.denied_by_guard, l.at, u.name person_name
                       FROM action_execution_log l
                       LEFT JOIN users u ON u.id = l.person_id
                      WHERE l.company_id = {$company_id} AND l.result = 'denied'
                      ORDER BY l.at DESC LIMIT 200");
if ($res) { while ($x = $res->fetch_assoc()) { $centralDenied[] = $x; } }

/* ③ المنعُ المتكرر — نفسُ الحارس أو نفسُ الشخص ثلاثًا فأكثر */
$repeats = array();
$res = $conn->query("SELECT guard_code, COUNT(*) c FROM guard_denials
                      WHERE company_id = {$company_id}
                      GROUP BY guard_code HAVING c >= 3 ORDER BY c DESC LIMIT 20");
if ($res) { while ($x = $res->fetch_assoc()) { $repeats[] = $x; } }

$stats = array(
    'total' => count($denials),
    'reviewed' => 0,
    'open' => 0,
    'central' => count($centralDenied),
);
foreach ($denials as $d) { if (!empty($d['review_code'])) { $stats['reviewed']++; } else { $stats['open']++; } }

$page_title = 'إيكوبيشن | المحاولات الممنوعة';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'المحاولات الممنوعة';
    $header_icon = 'fas fa-hand';
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'سجلُّ المنع الحي',
        'محاولاتٌ مرصودة' => $stats['total'],
        'رُوجعت' => $stats['reviewed'],
        'تنتظر المراجعة' => $stats['open'],
        'رفضُ الحارس المركزي' => $stats['central'],
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'كلُّ محاولةٍ رفضها حارسٌ تُرصد هنا ولا تُترك صامتة. والمراجعةُ تصنّفها: حاجةٌ لاستثناءٍ '
        . 'أو خطأُ تصنيفِ حمايةٍ أو محاولةُ تجاوزٍ أو عابرٌ — وما يحتاج أثرًا يفتح مرجعَه.',
        array('المنعُ المتكرر على الحارس نفسِه إنذارٌ: راجع تصنيفَ الحماية قبل اتهام المحاوِل',
              'المراجعةُ واحدةٌ للمحاولة وتكرارُها يرجع الأولى — والسجلُّ لا يُحذف'));
    ?>

    <?php if ($repeats): ?>
    <div class="ems-card" style="padding:12px;margin:10px 0;border-inline-start:4px solid #fd7e14">
        <strong style="color:#b45309">منعٌ متكرر (3+) — يكشف حاجةً أو خطأً أو تجاوزًا</strong>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
            <?php foreach ($repeats as $x): ?>
            <span class="badge badge-warning" style="font-size:.8rem">
                <?php echo htmlspecialchars($x['guard_code']); ?>: <?php echo (int) $x['c']; ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card"><div class="card-body table-responsive">
        <h6>سجلُّ المحاولات (<?php echo count($denials); ?>)</h6>
        <?php if (!$denials): ?>
            <?php if (function_exists('ems_state_empty')) { ems_state_empty('لا محاولاتٍ ممنوعةً مرصودة — الحرّاسُ هادئون ✨'); } else { echo '<p style="opacity:.7">لا محاولات.</p>'; } ?>
        <?php else: ?>
        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr>
                <th>#</th><th>رمز الحارس</th><th>المحاوِل</th><th>المرجع المحاوَل</th>
                <th>رمز السبب</th><th>الوقت</th>
                <th>المراجعة</th><th>التصنيف</th><th>قرار المراجعة</th><th>تاريخ المراجعة</th>
                <?php /* الأعمدةُ الحاكمةُ التي يطلبها تصميمُ الشاشة (CMP-03) — بنمطِ السجلِّ
                         المركزي `ems_gov_registry()`: الحالةُ مشتقةٌ من وجودِ المراجعة،
                         والكيانُ من عمودِ العزلِ نفسِه لا من الجلسة. */ ?>
                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($denials as $d): ?>
                <tr>
                    <td><?php echo (int) $d['deny_id']; ?></td>
                    <td style="font-family:monospace;font-size:.76rem"><?php echo htmlspecialchars($d['guard_code']); ?></td>
                    <td><?php echo htmlspecialchars((string) ($d['person_name'] ?: ('#' . $d['person_id']))); ?></td>
                    <td style="font-family:monospace;font-size:.72rem"><?php echo htmlspecialchars((string) $d['attempted_ref']); ?></td>
                    <td><span class="badge badge-danger"><?php echo htmlspecialchars((string) $d['reason_code']); ?></span></td>
                    <td><small><?php echo htmlspecialchars((string) $d['at']); ?></small></td>
                    <td style="font-family:monospace"><?php echo htmlspecialchars((string) ($d['review_code'] ?: '—')); ?></td>
                    <td><?php echo $d['classification'] !== null
                        ? '<span class="badge badge-info">' . htmlspecialchars($d['classification']) . '</span>' : '—'; ?></td>
                    <td style="font-size:.76rem"><?php echo htmlspecialchars((string) ($d['decision_note'] ?: '—')); ?></td>
                    <td><small><?php echo htmlspecialchars((string) ($d['review_at'] ?: '—')); ?></small></td>
                    <td><?php echo empty($d['review_code'])
                        ? '<span class="badge badge-warning">تنتظر المراجعة</span>'
                        : '<span class="badge badge-success">مُراجَعة</span>'; ?></td>
                    <td><?php echo (int) $d['company_id']; ?></td>
                    <td>
                        <?php if ($canReview && empty($d['review_code'])): ?>
                        <button class="ems-btn-primary" onclick="dnrReview(<?php echo (int) $d['deny_id']; ?>)">مراجعة</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div></div>

    <div class="card" style="margin-top:12px"><div class="card-body table-responsive">
        <h6>رفضُ الحارس المركزي (action_execution_log · denied) — <?php echo count($centralDenied); ?></h6>
        <table class="table table-sm table-striped" style="width:100%" data-no-dt="1">
            <thead><tr><th>الفعل</th><th>المحاوِل</th><th>الموضوع</th><th>رمز المنع</th><th>الوقت</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($centralDenied, 0, 50) as $x): ?>
                <tr>
                    <td style="font-family:monospace;font-size:.74rem"><?php echo htmlspecialchars($x['action_code']); ?></td>
                    <td><?php echo htmlspecialchars((string) ($x['person_name'] ?: ('#' . $x['person_id']))); ?></td>
                    <td style="font-size:.74rem"><?php echo htmlspecialchars((string) $x['subject_ref']); ?></td>
                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars((string) $x['denied_by_guard']); ?></span></td>
                    <td><small><?php echo htmlspecialchars((string) $x['at']); ?></small></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$centralDenied): ?><tr><td colspan="5" style="opacity:.7">لا رفضَ مركزيًّا مسجَّلًا.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<div id="dnrModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1200;align-items:center;justify-content:center">
    <div class="card" style="max-width:520px;width:94%;margin:auto;margin-top:8vh">
        <div class="card-body">
            <h6>مراجعة محاولة ممنوعة <span id="dnrIdLbl" style="font-family:monospace"></span></h6>
            <input type="hidden" id="dnrDenialId">
            <label style="font-size:.8rem;display:block;margin-top:8px">التصنيف
                <select id="dnrClass" class="form-control form-control-sm">
                    <option>يحتاج استثناءً</option>
                    <option>خطأ تصنيف حماية</option>
                    <option>محاولة تجاوز</option>
                    <option>عابر — لا إجراء</option>
                </select>
            </label>
            <label style="font-size:.8rem;display:block;margin-top:8px">قرار المراجعة وتسبيبه
                <input type="text" id="dnrNote" class="form-control form-control-sm" placeholder="ما وُجد وما تقرر" aria-label="ما وُجد وما تقرر">
            </label>
            <label style="font-size:.8rem;display:block;margin-top:8px">المرجع التالي (طلب استثناء · تصحيح تصنيف · بلاغ)
                <input type="text" id="dnrFollow" class="form-control form-control-sm" placeholder="اختياري — EXC-000123" aria-label="اختياري — EXC-000123">
            </label>
            <div style="display:flex;gap:8px;margin-top:12px">
                <button class="ems-btn-primary" onclick="dnrSubmit()">تسجيل المراجعة</button>
                <button class="ems-btn-secondary" onclick="document.getElementById('dnrModal').style.display='none'">إلغاء</button>
            </div>
        </div>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
function dnrReview(id) {
    document.getElementById('dnrDenialId').value = id;
    document.getElementById('dnrIdLbl').textContent = '#' + id;
    document.getElementById('dnrModal').style.display = 'flex';
}
function dnrSubmit() {
    var fd = new FormData();
    fd.append('do', 'denial_review');
    fd.append('denial_id', document.getElementById('dnrDenialId').value);
    fd.append('classification', document.getElementById('dnrClass').value);
    fd.append('note', document.getElementById('dnrNote').value || '');
    fd.append('follow_up_ref', document.getElementById('dnrFollow').value || '');
    if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
    fetch('gov_m14_actions.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (!j.ok) { alert((j.code || 'GOV-500') + ': ' + (j.msg || '')); return; }
            alert(j.idempotent ? 'مراجعةٌ سابقة ' + j.review_code + ' — التكرارُ يرجع الأولى' : 'سُجّلت المراجعة ' + j.review_code + ' ✔');
            location.reload();
        })
        .catch(function () { alert('تعذر الاتصال — أعد المحاولة'); });
}
</script>
</body>
</html>
