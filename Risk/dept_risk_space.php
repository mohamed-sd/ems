<?php
/**
 * Risk/dept_risk_space.php — مساحة مخاطر الإدارة (M-16 · ورقة 27 التبويبات السبعة)
 * «مكوّن نطاقي واحد لأربع عشرة إدارة» (UXR-0076) — قراءة بزاوية الإدارة،
 * والحقان الفعليان: الإبلاغ عن إشارة وتسجيل دليل ضابط يملكه.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
$__ro = $__pp;
$__ro['can_edit'] = 0; // القراءة زاوية — والتعديل بطلب لإدارة المخاطر (ورقة 27)
ems_shell_axes($__ro);

$unit = $RISK_FULL ? intval($_GET['unit'] ?? 0) : $RISK_ORG_UNIT;
$unitName = '';
if ($unit > 0) {
    $r = $conn->query("SELECT name_ar FROM org_units WHERE unit_id = {$unit}");
    if ($r && ($x = $r->fetch_assoc())) { $unitName = $x['name_ar']; }
}
$w = $unit > 0 ? " AND rr.owner_unit_id = {$unit} " : ($RISK_FULL ? '' : ' AND 1=0 ');

$tab = (string) ($_GET['tab'] ?? 'summary');
$TABS = array(
    'summary' => 'ملخص مخاطر الإدارة', 'register' => 'سجل مخاطر الإدارة',
    'controls' => 'الضوابط والحرجة', 'kris' => 'المؤشرات والتنبيهات',
    'treatments' => 'إجراءات المعالجة', 'signals' => 'الإشارات والحوادث',
    'reviews' => 'المراجعات والقرارات',
);
if (!isset($TABS[$tab])) { $tab = 'summary'; }

$page_title = 'إيكوبيشن | مساحة مخاطر الإدارة';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مساحة مخاطر الإدارة' . ($unitName !== '' ? ' — ' . $unitName : '');
    $header_icon = 'fas fa-building-shield';
    $header_actions = array();
    $header_back = array();
    $header_context = array('الزاوية' => $unitName !== '' ? $unitName : 'كل النطاق', 'الحكم' => 'قراءة — والتعديل بطلب لإدارة المخاطر');
    include('../includes/page_header.php');
    ems_screen_about('مكوّن نطاقي واحد على السجل المركزي — الإدارة ترى زاويتها ولا سجل موازيًا لها (RK-02). '
        . 'حقاك الفعليان هنا: الإبلاغ عن إشارة، وتسجيل دليل ضابط تملكه.',
        array('تعديل التصنيف أو الدرجة أو الإغلاق: بطلب لإدارة المخاطر — والقبول ضمن سقفك فقط'));
    ?>

    <div class="ems-toolbar">
        <?php foreach ($TABS as $k => $v): ?>
        <a class="ems-btn-<?php echo $tab === $k ? 'primary' : 'secondary'; ?>"
           href="?tab=<?php echo $k; ?><?php echo $RISK_FULL && $unit > 0 ? '&unit=' . $unit : ''; ?>"><?php echo $v; ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card"><div class="card-body table-responsive">
    <?php
    if ($tab === 'summary') {
        $rows = array();
        $r = $conn->query("SELECT COALESCE(rr.current_level,'لم يقيَّم') lv, COUNT(*) c FROM risk_register rr
                            WHERE rr.company_id={$company_id} AND rr.state<>'closed' AND rr.merged_into_id IS NULL {$w} GROUP BY lv");
        while ($x = $r->fetch_assoc()) { $rows[] = $x; }
        echo '<h6>أهم المخاطر ودرجاتها (قراءة)</h6><table class="table table-sm"><thead><tr><th>المستوى</th><th>العدد</th></tr></thead><tbody>';
        foreach ($rows as $x) { echo '<tr><td>' . htmlspecialchars($x['lv']) . '</td><td>' . (int) $x['c'] . '</td></tr>'; }
        if (empty($rows)) { echo '<tr><td colspan="2" class="text-muted">لا مخاطر مفتوحة في زاويتك</td></tr>'; }
        echo '</tbody></table>';
    } elseif ($tab === 'register') {
        $r = $conn->query("SELECT rr.risk_code, rr.title, rr.current_level, rr.state, rr.review_due, ru.ru_code
                             FROM risk_register rr JOIN risk_units ru ON ru.id = rr.ru_id
                            WHERE rr.company_id={$company_id} AND rr.merged_into_id IS NULL {$w}
                            ORDER BY rr.updated_at DESC LIMIT 300");
        echo '<h6>سجل مخاطر الإدارة — قراءة، والتعديل بطلب</h6><table class="table table-sm table-striped"><thead><tr><th>الرمز</th><th>العنوان</th><th>الوحدة</th><th>المستوى</th><th>الحالة</th><th>مراجعة قبل</th></tr></thead><tbody>';
        $n = 0;
        while ($x = $r->fetch_assoc()) { $n++;
            echo '<tr><td>' . $x['risk_code'] . '</td><td>' . htmlspecialchars($x['title']) . '</td><td>' . $x['ru_code']
               . '</td><td>' . ($x['current_level'] ?: '—') . '</td><td>' . $x['state'] . '</td><td>' . $x['review_due'] . '</td></tr>';
        }
        if ($n === 0) { echo '<tr><td colspan="6" class="text-muted">لا مخاطر مملوكة لإدارتك</td></tr>'; }
        echo '</tbody></table>';
    } elseif ($tab === 'controls') {
        $r = $conn->query("SELECT rc.id, rc.control_code, rc.name_ar, rc.ctype, rc.is_critical, rc.effectiveness, rc.owner_user_id, u.name owner_name
                             FROM risk_controls rc LEFT JOIN users u ON u.id = rc.owner_user_id
                            WHERE rc.company_id={$company_id} AND rc.active=1
                              AND rc.owner_user_id IN (SELECT us.id FROM users us JOIN employees e ON e.id = us.employee_id
                                                        JOIN job_titles jt ON jt.id = e.job_title_id WHERE us.company_id = {$company_id})
                            ORDER BY rc.is_critical DESC LIMIT 300");
        echo '<h6>الضوابط — تسجيل دليل التنفيذ حقك على ما تملكه</h6><table class="table table-sm table-striped"><thead><tr><th>الرمز</th><th>الاسم</th><th>النوع</th><th>المالك</th><th>حرج</th><th>الفعالية</th><th></th></tr></thead><tbody>';
        $n = 0;
        while ($x = $r->fetch_assoc()) { $n++;
            echo '<tr><td>' . $x['control_code'] . '</td><td>' . htmlspecialchars($x['name_ar']) . '</td><td>' . $x['ctype']
               . '</td><td>' . htmlspecialchars((string) $x['owner_name']) . '</td><td>' . ((int) $x['is_critical'] === 1 ? 'حرج' : '—')
               . '</td><td>' . $x['effectiveness'] . '</td>'
               . '<td>' . ((int) $x['owner_user_id'] === $uid ? '<button class="btn btn-sm btn-outline-dark ctlEvid" data-id="' . (int) $x['id'] . '">دليل تنفيذ</button>' : '') . '</td></tr>';
        }
        if ($n === 0) { echo '<tr><td colspan="7" class="text-muted">لا ضوابط</td></tr>'; }
        echo '</tbody></table>';
    } elseif ($tab === 'kris') {
        $r = $conn->query("SELECT name_ar, warn_threshold_ar, critical_threshold_ar, current_value, kri_state, last_read_at
                             FROM risk_kris WHERE company_id={$company_id} AND active=1"
                          . ($unitName !== '' ? " AND dept_ar LIKE '%" . $conn->real_escape_string(mb_substr($unitName, 0, 10)) . "%'" : '')
                          . ' ORDER BY id');
        echo '<h6>مؤشرات إدارتك (قراءة)</h6><table class="table table-sm table-striped"><thead><tr><th>المؤشر</th><th>الإنذار</th><th>الحرج</th><th>القيمة</th><th>الحالة</th><th>آخر قراءة</th></tr></thead><tbody>';
        $n = 0;
        while ($x = $r->fetch_assoc()) { $n++;
            echo '<tr><td>' . htmlspecialchars($x['name_ar']) . '</td><td>' . htmlspecialchars($x['warn_threshold_ar'])
               . '</td><td>' . htmlspecialchars($x['critical_threshold_ar']) . '</td><td>' . ($x['current_value'] ?: '—')
               . '</td><td>' . $x['kri_state'] . '</td><td>' . ($x['last_read_at'] ?: '—') . '</td></tr>';
        }
        if ($n === 0) { echo '<tr><td colspan="6" class="text-muted">لا مؤشرات موصولة بإدارتك بعد</td></tr>'; }
        echo '</tbody></table>';
    } elseif ($tab === 'treatments') {
        $r = $conn->query("SELECT t.id, t.ttype, t.plan_ar, t.due_date, t.state, rr.risk_code
                             FROM risk_treatments t JOIN risk_register rr ON rr.id = t.risk_id
                            WHERE t.company_id={$company_id} AND (t.action_owner_user_id = {$uid}"
                          . ($unit > 0 ? " OR rr.owner_unit_id = {$unit}" : '') . ")
                            ORDER BY t.due_date LIMIT 300");
        echo '<h6>إجراءات المعالجة المسندة — التنفيذ وتقديم الدليل</h6><table class="table table-sm table-striped"><thead><tr><th>الخطر</th><th>النوع</th><th>الخطة</th><th>المهلة</th><th>الحالة</th><th></th></tr></thead><tbody>';
        $n = 0;
        while ($x = $r->fetch_assoc()) { $n++;
            echo '<tr><td>' . $x['risk_code'] . '</td><td>' . $x['ttype'] . '</td><td>' . htmlspecialchars(mb_substr($x['plan_ar'], 0, 60))
               . '</td><td>' . $x['due_date'] . '</td><td>' . $x['state'] . '</td>'
               . '<td>' . (in_array($x['state'], array('planned', 'in_progress'), true)
                    ? '<button class="btn btn-sm btn-outline-success treatDone" data-id="' . (int) $x['id'] . '">إنجاز بدليل</button>' : '') . '</td></tr>';
        }
        if ($n === 0) { echo '<tr><td colspan="6" class="text-muted">لا إجراءات مسندة</td></tr>'; }
        echo '</tbody></table>';
    } elseif ($tab === 'signals') {
        $r = $conn->query("SELECT id, title, source, state, created_at FROM risk_signals
                            WHERE company_id={$company_id} AND created_by = {$uid} ORDER BY created_at DESC LIMIT 100");
        echo '<h6>إشاراتك المبلَّغة (الإبلاغ حقك — والفرز لإدارة المخاطر)</h6>';
        echo '<form id="deptSigForm" class="ems-toolbar"><input name="title" class="form-control" placeholder="عنوان الإشارة *" style="max-width:280px" required>'
           . '<input name="root_cause" class="form-control" placeholder="السبب الجذري" style="max-width:200px">'
           . '<button class="ems-btn-primary" type="submit">إبلاغ</button><span id="deptSigMsg"></span></form>';
        echo '<table class="table table-sm table-striped"><thead><tr><th>#</th><th>العنوان</th><th>المصدر</th><th>الحالة</th><th>التاريخ</th></tr></thead><tbody>';
        $n = 0;
        while ($x = $r->fetch_assoc()) { $n++;
            echo '<tr><td>' . (int) $x['id'] . '</td><td>' . htmlspecialchars($x['title']) . '</td><td>' . $x['source']
               . '</td><td>' . $x['state'] . '</td><td>' . $x['created_at'] . '</td></tr>';
        }
        if ($n === 0) { echo '<tr><td colspan="5" class="text-muted">لم تبلّغ عن إشارات بعد</td></tr>'; }
        echo '</tbody></table>';
    } else { // reviews
        $r = $conn->query("SELECT a.level_at_acceptance, a.authority, a.review_due, a.created_at, rr.risk_code, rr.title
                             FROM risk_acceptances a JOIN risk_register rr ON rr.id = a.risk_id
                            WHERE a.company_id={$company_id} {$w} ORDER BY a.created_at DESC LIMIT 200");
        echo '<h6>المراجعات وقرارات القبول التي تخص الإدارة (قراءة — والقبول ضمن السقف فقط)</h6>';
        echo '<table class="table table-sm table-striped"><thead><tr><th>الخطر</th><th>العنوان</th><th>المستوى</th><th>السلطة</th><th>مراجعة قبل</th><th>التاريخ</th></tr></thead><tbody>';
        $n = 0;
        while ($x = $r->fetch_assoc()) { $n++;
            echo '<tr><td>' . $x['risk_code'] . '</td><td>' . htmlspecialchars($x['title']) . '</td><td>' . $x['level_at_acceptance']
               . '</td><td>' . $x['authority'] . '</td><td>' . $x['review_due'] . '</td><td>' . $x['created_at'] . '</td></tr>';
        }
        if ($n === 0) { echo '<tr><td colspan="6" class="text-muted">لا قرارات بعد</td></tr>'; }
        echo '</tbody></table>';
    }
    ?>
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
    var sf = document.getElementById('deptSigForm');
    if (sf) { sf.addEventListener('submit', function (ev) {
        ev.preventDefault();
        post({ do: 'signal_create', title: sf.title.value, root_cause: sf.root_cause.value }, function (j) {
            document.getElementById('deptSigMsg').textContent = j.ok ? '✔ دخلت الفرز' : '✘ ' + (j.msg || '');
            if (j.ok) { setTimeout(function () { location.reload(); }, 700); }
        });
    }); }
    document.querySelectorAll('.ctlEvid').forEach(function (b) {
        b.addEventListener('click', function () {
            var txt = prompt('دليل التنفيذ:');
            if (!txt) { return; }
            post({ do: 'control_evidence', control_id: b.dataset.id, evidence_text: txt }, function (j) {
                alert(j.ok ? 'سُجل' : (j.msg || ''));
            });
        });
    });
    document.querySelectorAll('.treatDone').forEach(function (b) {
        b.addEventListener('click', function () {
            var txt = prompt('دليل الإنجاز (الإغلاق بقبول المتحقق):');
            if (!txt) { return; }
            post({ do: 'treatment_progress', treatment_id: b.dataset.id, state: 'done', done_evidence: txt }, function (j) {
                if (j.ok) { location.reload(); } else { alert(j.msg || ''); }
            });
        });
    });
});
</script>
</body>
</html>
