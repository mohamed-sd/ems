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
require_once __DIR__ . '/_risk_views.php';
ems_shell_axes($__pp);

// CM-09/CM-10 (§6-2): ٣٦ عمودًا لا تُختزل — والعلاجُ مناظرُ لا حذف
$view = risk_current_view('risk_register');

$units = risk_units_list($conn, $company_id);
/* INJ-0577: وحداتُ هيكلِ الكيانِ لقائمةِ «الإدارة المالكة» — أسماءٌ عربيةٌ لا أرقام */
$orgUnits = array();
$__ou = $conn->prepare('SELECT unit_id, unit_code, name_ar FROM org_units
                         WHERE company_id = ? AND COALESCE(active,1) = 1
                         ORDER BY layer, unit_code');
$__ou->bind_param('i', $company_id);
$__ou->execute();
$__our = $__ou->get_result();
while ($__x = $__our->fetch_assoc()) { $orgUnits[] = $__x; }
$__ou->close();
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
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('risk', 'نظرةٌ عامة');
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
        'المنظر' => $view === 'all' ? 'كل الأعمدة (36)' : 'مختصر موجَّه للمهمة',
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'السجل المركزي الواحد للمخاطر — الخطر يُملك حيث نشأ (RK-01) ويُعرض لكل إدارة بزاويتها ولا يُنسخ. '
        . 'لا حذف إطلاقًا: الإغلاق بدليل واعتماد بالسقف، والدمج بقرار محلل مسبَّب.',
        array('التقييمات نسخ تاريخية لا تُكتب فوقها (RK-03)', 'القبول فوق السقف يُرفض ويُصعَّد آليًّا (RK-04)',
              'نموذجُ البياناتِ لا يُختزل: المنظرُ يقلّل الأعمدةَ والفلترُ يقلّل الصفوف — ولا يُخفى عمودُ حوكمة'));
    risk_view_bar('risk_register', $view, array_filter(array(
        'ru' => $fUnit ?: null, 'level' => $fLevel ?: null, 'state' => $fState ?: null)));
    echo ems_states_bundle('لا مخاطرَ مسجَّلةً ضمن هذا الترشيح', 'وسّع الترشيحَ أو راجع صندوقَ الإشارات');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

        <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
    <div class="filter">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
        <div class="filter-body">
    <form method="get" class="ems-toolbar rsk-filter-form">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
        <label class="rsk-filter-label">الوحدة
            <select name="ru" class="form-control rsk-ru-select" aria-label="الوحدة">
                <option value="0">الكل</option>
                <?php foreach ($units as $u): ?>
                <option value="<?php echo (int) $u['id']; ?>" <?php echo $fUnit == $u['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($u['ru_code'] . ' · ' . $u['name_ar']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="rsk-filter-label">المستوى
            <select name="level" class="form-control" aria-label="المستوى">
                <option value="">الكل</option>
                <?php foreach ($RISK_LEVELS as $lv): ?>
                <option value="<?php echo $lv; ?>" <?php echo $fLevel === $lv ? 'selected' : ''; ?>><?php echo $lv; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="rsk-filter-label">الحالة
            <select name="state" class="form-control" aria-label="الحالة">
                <option value="">الكل</option>
                <?php foreach ($STATE_AR as $k => $v): ?>
                <option value="<?php echo $k; ?>" <?php echo $fState === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="ems-btn-secondary"><i class="fa fa-filter"></i> ترشيح</button>
    </form>
        </div>
    </div>

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
        <table class="table table-striped rsk-w100">
            <?php $V = function ($c) use ($view) { return risk_col_visible('risk_register', $view, $c); }; ?>
            <thead><tr>
                <th>الرمز</th><th>العنوان</th><th>الوحدة</th>
                <?php if ($V('owner_unit_name')): ?><th>الإدارة المالكة</th><?php endif; ?>
                <?php if ($V('owner_name')): ?><th>مالك الخطر</th><?php endif; ?>
                <?php if ($V('scope_type')): ?><th>النطاق</th><?php endif; ?>
                <?php if ($V('root_cause')): ?><th>السبب الجذري</th><?php endif; ?>
                <?php if ($V('current_level')): ?><th>المستوى</th><?php endif; ?>
                <?php if ($V('target_level')): ?><th>المستهدف</th><?php endif; ?>
                <?php if ($V('control_effectiveness')): ?><th>فعالية الضوابط</th><?php endif; ?>
                <?php if ($V('confidence')): ?><th>الثقة</th><?php endif; ?>
                <?php if ($V('velocity')): ?><th>سرعة التحقق</th><?php endif; ?>
                <?php if ($V('horizon')): ?><th>الأفق</th><?php endif; ?>
                <?php if ($V('appetite_verdict')): ?><th>حكم الشهية</th><?php endif; ?>
                <?php if ($V('exposure_amount')): ?><th>التعرض المقدَّر</th><?php endif; ?>
                <?php if ($V('state')): ?><th>الحالة</th><?php endif; ?>
                <?php if ($V('review_due')): ?><th>مراجعة قبل</th><?php endif; ?>
                <?php if ($V('created_at')): ?><th>تاريخ الإنشاء</th><?php endif; ?>
                <th>فتح</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td><?php echo htmlspecialchars($x['risk_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['title']); ?></td>
                    <td><?php echo htmlspecialchars($x['ru_code']); ?></td>
                    <?php if ($V('owner_unit_name')): ?>
                    <td><?php echo htmlspecialchars($x['owner_unit_name'] ?: '—'); ?></td><?php endif; ?>
                    <?php if ($V('owner_name')): ?>
                    <td><?php echo htmlspecialchars($x['owner_name'] ?: '—'); ?></td><?php endif; ?>
                    <?php if ($V('scope_type')): ?>
                    <td><?php echo htmlspecialchars($x['scope_type']); ?></td><?php endif; ?>
                    <?php if ($V('root_cause')): ?>
                    <td><?php echo htmlspecialchars(mb_substr((string) $x['root_cause'], 0, 40)); ?></td><?php endif; ?>
                    <?php if ($V('current_level')): ?>
                    <td><?php $lv = (string) $x['current_level'];
                        $cls = $lv === 'حرج' || $lv === 'محظور' ? 'badge-danger' : ($lv === 'مرتفع' ? 'badge-warning' : 'badge-secondary'); ?>
                        <span class="badge <?php echo $cls; ?>"><?php echo $lv !== '' ? $lv : 'لم يقيَّم'; ?></span></td><?php endif; ?>
                    <?php if ($V('target_level')): ?>
                    <td><?php echo htmlspecialchars((string) $x['target_level'] ?: '—'); ?></td><?php endif; ?>
                    <?php if ($V('control_effectiveness')): ?>
                    <td><?php echo htmlspecialchars((string) $x['control_effectiveness'] ?: 'غير مثبت'); ?></td><?php endif; ?>
                    <?php if ($V('confidence')): ?>
                    <td><?php echo htmlspecialchars((string) $x['confidence'] ?: '—'); ?></td><?php endif; ?>
                    <?php if ($V('velocity')): ?>
                    <td><?php echo htmlspecialchars((string) $x['velocity'] ?: '—'); ?></td><?php endif; ?>
                    <?php if ($V('horizon')): ?>
                    <td><?php echo htmlspecialchars((string) $x['horizon'] ?: '—'); ?></td><?php endif; ?>
                    <?php if ($V('appetite_verdict')): ?>
                    <td><?php $av = (string) $x['appetite_verdict'];
                        $acls = ($av === 'محظور' || $av === 'فوق حد التحمل') ? 'badge-danger'
                              : ($av === 'فوق الشهية' ? 'badge-warning' : 'badge-success'); ?>
                        <?php echo $av === '' ? '—' : '<span class="badge ' . $acls . '">' . htmlspecialchars($av) . '</span>'; ?></td><?php endif; ?>
                    <?php if ($V('exposure_amount')): ?>
                    <td><?php echo $x['exposure_amount'] === null ? '—'
                            : htmlspecialchars(number_format((float) $x['exposure_amount'], 2) . ' ' . (string) $x['exposure_currency']); ?></td><?php endif; ?>
                    <?php if ($V('state')): ?>
                    <td><?php echo $STATE_AR[$x['state']] ?? $x['state']; ?></td><?php endif; ?>
                    <?php if ($V('review_due')): ?>
                    <td><?php echo htmlspecialchars((string) $x['review_due']); ?></td><?php endif; ?>
                    <?php if ($V('created_at')): ?>
                    <td><?php echo htmlspecialchars((string) $x['created_at']); ?></td><?php endif; ?>
                    <td><a class="btn btn-sm btn-secondary" href="risk_card.php?id=<?php echo (int) $x['id']; ?>">ملف الخطر</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <?php endif; ?>

    <?php if ($RISK_FULL && (!empty($__pp['can_add']) || $is_super_admin)): ?>
    <div class="card rsk-new-card is-hidden" id="rskNewCard"><div class="card-body">
        <h5>خطر جديد (بعد الفرز — RK-05: الإشارة أولًا إن لم يكن مصدره فرزًا)</h5>
        <form id="rskNewForm" class="allforms">
            <div class="row">
                <div class="col-md-4"><label>الوحدة *<select name="ru_id" class="form-control" aria-label="الوحدة" required>
                    <?php foreach ($units as $u): ?><option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['ru_code'] . ' · ' . $u['name_ar']); ?></option><?php endforeach; ?>
                </select></label></div>
                <div class="col-md-8"><label>العنوان *<input name="title" class="form-control" aria-label="عنوان الخطر" required></label></div>
                <div class="col-md-4"><label>النطاق<select name="scope_type" class="form-control" aria-label="النطاق">
                    <option>إداري</option><option>مؤسسي</option><option>مشروعي</option><option>موقعي</option>
                </select></label></div>
                <?php /* INJ-0577: كان رقمًا حرًّا يُحفظ عن ظهرِ قلب — صار قائمةَ
                         وحداتِ هذا الكيانِ بأسمائها العربية، والخادمُ يرفض ما
                         سواها بـ422 (لا يكفي ترشيحُ الواجهةِ وحدَه). */ ?>
                <div class="col-md-4"><label>الإدارة المالكة (وحدة الهيكل)
                    <select name="owner_unit_id" class="form-control" aria-label="الإدارة المالكة">
                        <option value="">— بلا إدارةٍ مالكة —</option>
                        <?php foreach ($orgUnits as $ou): ?>
                        <option value="<?= (int) $ou['unit_id'] ?>"><?= htmlspecialchars($ou['name_ar']) ?><?php
                            if ($ou['unit_code'] !== '') { echo ' · ' . htmlspecialchars($ou['unit_code']); } ?></option>
                        <?php endforeach; ?>
                    </select></label></div>
                <div class="col-md-4"><label>السبب الجذري *<input name="root_cause" class="form-control" aria-label="السبب الجذري" required></label></div>
                <div class="col-md-12"><label>الوصف<textarea name="description" class="form-control" aria-label="وصف الخطر"></textarea></label></div>
            </div>
            <button type="submit" class="ems-btn-primary">تسجيل الخطر</button>
            <span id="rskNewMsg" class="rsk-new-msg"></span>
        </form>
    </div></div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('rskNewBtn');
        if (btn) { btn.addEventListener('click', function () {
            var c = document.getElementById('rskNewCard');
            c.classList.toggle('is-hidden');
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
