<?php
/**
 * Tickets/tickets_list.php — قائمة البلاغات.
 *
 * نطاق الرؤية (يُحسب من شجرة الأدوار parent_role_id، لا من العمود role_scope):
 *   • المدير الأعلى: كل الشركات.
 *   • مدير البلاغات: كل بلاغات شركته.
 *   • أي دور آخر: البلاغات الموجَّهة إلى دوره أو **ذرّيّته** (نزولًا لا صعودًا
 *     — فالمرؤوسُ لا يرى بلاغاتِ رئيسه)، إضافةً إلى ما أبلغ عنه بنفسه — عرضًا
 *     ومتابعةً فقط، فقيادةُ المراحل بيد فريق البلاغات.
 *
 * الوصول متاحٌ لكل مستخدم مسجَّل (كشاشة المراسلات)، والإنفاذ التفصيلي للنطاق
 * والأزرار داخل الشاشة نفسها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/tkt_helpers.php';

$ctx             = tkt_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];
$current_role_id = intval($ctx['role']);
$is_tickets_mgr  = ($ctx['role'] === EMS_ROLE_TICKETS_MGR);

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

/* ══ INJ-0521 · بابُ العرضِ كان مفتوحًا ══════════════════════════════════════
     نصُّ القبول: «دورٌ **بلا `can_view`** على قائمة البلاغات يُرفض ٤٠٣ ويُسجَّل
     الرفض». والمقيسُ قبلَه: الشاشةُ تفحص الشركةَ ولا تفحص المنحةَ إطلاقًا —
     فكلُّ من له حسابٌ في الشركةِ يقرأ بلاغاتِها كلَّها.
   ◆ والحارسُ المركزيُّ يحلُّ المودولَ حتميًّا ويُسجّل الرفضَ — فالمحاولةُ
     نفسُها أثرٌ يُراجَع، والمنعُ الصامتُ يُقرأ عطلًا. */
if (!$is_super_admin) {
    $__p = check_page_permissions($conn, 'Tickets/tickets_list.php');
    if (empty($__p['can_view'])) {
        ems_gov_flash_redirect('../main/dashboard.php',
            'لا توجد صلاحية عرض قائمة البلاغات ❌', 'GOV-PERM-403', '');
        exit();
    }
}

$stages_map = tkt_stages();
$natures    = tkt_natures();
$roles_map  = tkt_roles_map();

// شرط النطاق (يُلحق بعد {TENANT_SCOPE})
$scope_sql = '';
if (!$is_super_admin && !$is_tickets_mgr) {
    $visible = tkt_visible_owner_role_ids($current_role_id);
    $in = implode(',', array_map('intval', $visible));
    $uid = intval($current_user_id);
    $scope_sql = " AND (t.owner_role_id IN ($in) OR t.reporter_user_id = $uid OR t.created_by = $uid)";
}

// E-13 (UX-07 §5.1): **التبويباتُ الأربعة بدل الفلاتر المنسدلة وحدَها** —
// وتبويبُ «تنتظر اعتمادًا» (done) كان غائبًا؛ و«بلاغاتي» هو M-37 (UX-07 §4):
// المرشَّحُ على المستلم لكل الأدوار لا لدور 24 وحده.
$TABS = array(
    'open'     => array('مفتوحة', " AND t.stage NOT IN ('done','closed','cancelled')"),
    'approval' => array('تنتظر اعتمادًا', " AND t.stage = 'done'"),
    'mine'     => array('بلاغاتي', ' AND (t.reporter_user_id = ' . intval($current_user_id)
                       . ' OR t.assigned_user_id = ' . intval($current_user_id)
                       . ' OR t.created_by = ' . intval($current_user_id) . ')'),
    'closed'   => array('مغلقة', " AND t.stage IN ('closed','cancelled')"),
);
$tab = isset($_GET['tab']) && isset($TABS[$_GET['tab']]) ? strval($_GET['tab']) : 'open';
// مرشِّحُ التبويب منفصلٌ عن شرط النطاق: بطاقاتُ اللمحة تصف مركزَ البلاغات كلَّه
// ولا تتبع التبويبَ المفتوح — خلطُهما كان يجعل «مفتوحة الآن» تعني «مفتوحة ضمن
// هذا التبويب»، فتنقص عن الشارة واللوحة وتصير صفرًا في تبويب «مغلقة».
$tab_sql = $TABS[$tab][1];

$page_title = 'إيكوبيشن | البلاغات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main tkt-list-main ems-unified-page-shell">
    <?php
    $header_title = 'البلاغات';
    $header_icon  = 'fa fa-tower-observation';
    $header_actions = array();
    $header_actions[] = array('tag' => 'a', 'href' => 'ticket_form.php', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'بلاغ جديد');
    // «لوحةُ المتابعة» كانت تُعرض للجميعِ وهي محروسةٌ بمنحتِها هي (الوحدة 137)
    // — فمن ضغطها بلا منحةٍ يُرَدُّ ٤٠٣ من شاشةٍ أخرى، فيقرأ العطلَ منعًا
    // متكرِّرًا لا حدًّا معلَنًا. والبابُ الذي لا يُفتح لا يُعرض: نفسُ شرطِ زرِّ
    // «الأنواعُ والتوجيه» أدناه، لكنْ **بالمنحةِ لا بالدور** — فالوحدةُ ممنوحةٌ
    // للإدارةِ التنفيذيةِ أيضًا لا لفريقِ البلاغاتِ وحدَه. ويُحسَب مرةً واحدةً
    // هنا لأنّ بطاقاتِ اللمحةِ أدناه تحيل إلى الشاشةِ نفسِها.
    $__tkt_can_dash = $is_super_admin
        || !empty(tkt_page_perms($conn, 'Tickets/ticket_dashboard.php', $is_super_admin)['can_view']);
    if ($__tkt_can_dash) {
        $header_actions[] = array('tag' => 'a', 'href' => 'ticket_dashboard.php', 'class' => 'suppliers-header-link', 'icon' => 'fa fa-gauge-high', 'label' => 'لوحة المتابعة');
    }
    if ($is_tickets_mgr || $is_super_admin) {
        $header_actions[] = array('tag' => 'a', 'href' => 'ticket_types_config.php', 'class' => 'suppliers-header-link', 'icon' => 'fa fa-route', 'label' => 'الأنواع والتوجيه');
    }
    // ── نظام Excel الموحّد — نفس هوية العملاء/المشاريع (نموذج + تصدير + استيراد) ──
    // الاستيراد لفريق البلاغات حصرًا (can_add على الوحدة)، لا لكل مَن يُبلّغ.
    require_once __DIR__ . '/../includes/excel_ui.php';
    $__tkt_can_import = ($is_tickets_mgr || $is_super_admin);
    foreach (ems_excel_header_actions('tickets', 'البلاغات', $__tkt_can_import) as $__xlAction) {
        $header_actions[] = $__xlAction;
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا بلاغاتِ في هذا التبويب', 'بدّل التبويبَ أو أزل الفلاترَ — أو افتح بلاغًا بزرِّ «بلاغ جديد»');
    ?>
    <style>
    /* UXW-01 ①②: أنماطُ قائمةِ البلاغاتِ الثابتة — بادئةُ الشاشة tkt-list- */
    .tkt-list-glance-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 14px; }
    .tkt-list-glance-link { text-decoration: none; color: inherit; }
    .tkt-list-tone-open   { --tkt-list-tone: var(--c-fd7e14, #fd7e14); }
    .tkt-list-tone-late   { --tkt-list-tone: var(--c-dc3545); }
    .tkt-list-tone-crit   { --tkt-list-tone: var(--c-b58900, #b58900); }
    .tkt-list-glance      { border-top: 3px solid var(--tkt-list-tone); }
    .tkt-list-glance-body { padding: 12px; }
    .tkt-list-glance-row  { display: flex; align-items: center; gap: 10px; }
    .tkt-list-glance-icon { font-size: 20px; color: var(--tkt-list-tone); }
    .tkt-list-glance-num  { font-size: 20px; font-weight: 800; line-height: 1; }
    .tkt-list-glance-cap  { font-size: 12px; color: var(--c-6c757d); margin-top: 3px; }
    .tkt-list-tabs        { display: flex; gap: 6px; flex-wrap: wrap; }
    .tkt-list-tab         { border: 1px solid var(--c-s-ddd); border-radius: 8px; padding: 6px 14px; text-decoration: none; }
    .tkt-list-tab.is-active { background: var(--c-e2b93b, #e2b93b); font-weight: 800; }
    .tkt-list-table       { width: 100%; }
    </style>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('ticket', 'نظرةٌ عامة'); ?>

    <?php tkt_msg_banner(); ?>

    <?php
    // لمحةٌ خفيفة باستعلام تجميعٍ واحدٍ رخيص — والتفصيل في لوحة المتابعة.
    $glance = tkt_gate($is_super_admin)->scopedQuery(
        array('scope' => array('t' => 'tickets')),
        "SELECT SUM(CASE WHEN t.stage NOT IN ('closed','cancelled') THEN 1 ELSE 0 END) AS open_cnt,
                SUM(CASE WHEN t.resolution_due_at IS NOT NULL AND t.resolution_due_at < NOW()
                          AND t.stage NOT IN ('done','closed','cancelled') THEN 1 ELSE 0 END) AS late_cnt,
                SUM(CASE WHEN t.business_impact = 'production_critical'
                          AND t.stage NOT IN ('closed','cancelled') THEN 1 ELSE 0 END) AS crit_cnt
         FROM tickets t WHERE {TENANT_SCOPE}" . $scope_sql);
    $g = !empty($glance) ? $glance[0] : array('open_cnt' => 0, 'late_cnt' => 0, 'crit_cnt' => 0);
    $glance_cards = array(
        array('مفتوحة الآن', (int)$g['open_cnt'], 'fa-folder-open', 'tkt-list-tone-open'),
        array('متأخّرة', (int)$g['late_cnt'], 'fa-triangle-exclamation', 'tkt-list-tone-late'),
        array('حرِجة للإنتاج', (int)$g['crit_cnt'], 'fa-bolt', 'tkt-list-tone-crit'),
    );
    ?>
    <div class="tkt-list-glance-grid">
        <?php foreach ($glance_cards as $c): ?>
            <?php // الرقمُ يُعرض للجميع — والوصلةُ لمن يملك منحةَ اللوحةِ وحدَه.
                  if ($__tkt_can_dash): ?>
            <a href="ticket_dashboard.php" class="tkt-list-glance-link" title="افتح لوحة المتابعة">
            <?php endif; ?>
                <div class="card tkt-list-glance <?php echo $c[3]; ?>"><div class="card-body tkt-list-glance-body">
                    <div class="tkt-list-glance-row">
                        <i class="fa <?php echo $c[2]; ?> tkt-list-glance-icon"></i>
                        <div>
                            <div class="tkt-list-glance-num"><?php echo $c[1]; ?></div>
                            <div class="tkt-list-glance-cap"><?php echo htmlspecialchars($c[0]); ?></div>
                        </div>
                    </div>
                </div></div>
            <?php if ($__tkt_can_dash): ?></a><?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="card"><div class="card-body tkt-list-tabs">
        <?php // E-13: التبويباتُ الأربعة — كلٌّ بعدّاده الحي
        foreach ($TABS as $tk => $tv):
            // نطاقُ الرؤية من مصدرٍ واحد ($scope_sql) لا مُعادًا بناؤه هنا —
            // ازدواجُ التعريف هو ما جعل الأرقام تتفارق أصلًا.
            $cnt_row = tkt_gate($is_super_admin)->scopedQuery(
                array('scope' => array('t' => 'tickets')),
                "SELECT COUNT(*) n FROM tickets t WHERE {TENANT_SCOPE}" . $scope_sql . $tv[1]);
            $cnt = $cnt_row ? intval($cnt_row[0]['n']) : 0;
        ?>
            <a href="?tab=<?php echo $tk; ?>" class="btn btn-sm tkt-list-tab<?php echo $tk === $tab ? ' is-active' : ''; ?>">
                <?php echo htmlspecialchars($tv[0]); ?>
                <span class="badge badge-secondary"><?php echo $cnt; ?></span></a>
        <?php endforeach; ?>
    </div></div>

    <div class="filter">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
        <div class="filter-body">
            <div class="filter-field"><label for="fStage"><i class="fa fa-flag"></i> المرحلة</label>
                <select id="fStage" class="form-control"><option value="">-- الكل --</option>
                    <?php foreach ($stages_map as $k => $v): ?><option value="<?php echo htmlspecialchars($v); ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                </select></div>
            <div class="filter-field"><label for="fOwner"><i class="fa fa-building"></i> الإدارة المالكة</label>
                <select id="fOwner" class="form-control"><option value="">-- الكل --</option>
                    <?php foreach (tkt_owner_role_ids() as $rid): ?><option value="<?php echo htmlspecialchars(tkt_label($roles_map, $rid)); ?>"><?php echo htmlspecialchars(tkt_label($roles_map, $rid)); ?></option><?php endforeach; ?>
                </select></div>
            <div class="filter-field"><label for="fNature"><i class="fa fa-tag"></i> الطبيعة</label>
                <select id="fNature" class="form-control"><option value="">-- الكل --</option>
                    <?php foreach ($natures as $k => $v): ?><option value="<?php echo htmlspecialchars($v); ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                </select></div>
            <div class="filter-actions">
                <button type="button" class="btn-secondary" id="fReset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="tktTable" class="display nowrap alltables tkt-list-table" data-scroll-x="1" data-state-save="false">
                <thead><tr>
                    <th>تاريخ الفتح</th><th>رقم التذكرة</th><th>النوع</th><th>الطبيعة</th><th>المرحلة</th><th>الإدارة المالكة</th>
                    <th>المُبلِّغ</th><th>المعدة</th><th>المشروع</th><th>الوصف</th><th>تاريخ البلاغ</th><th>موعد الإنجاز</th><th>متأخّر</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">رقم البلاغ</th>
                    <th class="ems-fn-th" data-fn="1">الفئة</th>
                    <th class="ems-fn-th" data-fn="1">الأولوية</th>
                    <th class="ems-fn-th" data-fn="1">الموقع</th>
                    <th class="ems-fn-th" data-fn="1">الإدارة المختصة</th>
                    <th class="ems-fn-th" data-fn="1">المكلَّف</th>
                    <th class="ems-fn-th" data-fn="1">مستوى السرية</th>
                    <th class="ems-fn-th" data-fn="1">المسارات المتوازية</th>
                    <th class="ems-fn-th" data-fn="1">المهلة</th>
                    <th class="ems-fn-th none" data-fn="1">المتبقي</th>
                    <th class="ems-fn-th none" data-fn="1">التصعيد الحالي</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    </tr></thead>
                <tbody>
                <?php
                // scopedQuery: scope على tickets + إثراءات LEFT حصرًا (مراجع مرنة قد تغيب)
                $rows = tkt_gate($is_super_admin)->scopedQuery(
                    array('scope'  => array('t' => 'tickets'),
                          'enrich' => array('tt' => 'ticket_types', 'e' => 'equipments', 'p' => 'project')),
                    "SELECT t.id, t.ticket_no, t.stage, t.ticket_nature, t.owner_role_id,
                            t.reporting_person, t.complaint, t.call_date, t.call_time,
                            t.resolution_due_at,
                            tt.name AS type_name, e.code AS equipment_code, p.name AS project_name
                     FROM tickets t
                     LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
                     LEFT JOIN equipments e ON e.id = t.equipment_id
                     LEFT JOIN project p ON p.id = t.project_id
                     WHERE {TENANT_SCOPE}" . $scope_sql . $tab_sql . "
                     ORDER BY t.id DESC"
                );
                foreach ($rows as $row) {
                    $complaint_full  = (string)$row['complaint'];
                    $complaint_short = mb_strlen($complaint_full) > 60 ? (mb_substr($complaint_full, 0, 60) . '…') : $complaint_full;
                    echo "<tr>";
                    echo "<td><div class='action-btns'><a href='ticket_form.php?id=" . intval($row['id']) . "' class='action-btn edit' title='فتح التذكرة'><i class='fas fa-up-right-from-square'></i></a></div></td>";
                    echo "<td><strong>" . htmlspecialchars((string)$row['ticket_no']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars((string)($row['type_name'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars(tkt_label($natures, $row['ticket_nature'])) . "</td>";
                    // المرحلةُ بادجًا **ومؤشِّرَ تقدُّمٍ**: أينَ وصل البلاغُ من رحلته
                    // الخمسِ يُقرأ من الصفِّ بلا فتحِه — والشريطُ الكاملُ في شاشته.
                    echo "<td><div class='ems-jmini-cell'>" . tkt_stage_badge($row['stage'])
                       . tkt_stage_mini($row['stage'], tkt_is_overdue($row)) . "</div></td>";
                    echo "<td>" . htmlspecialchars(tkt_label($roles_map, intval($row['owner_role_id']))) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['reporting_person']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['equipment_code'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['project_name'] ?? '—')) . "</td>";
                    echo "<td title='" . htmlspecialchars($complaint_full, ENT_QUOTES) . "'>" . htmlspecialchars($complaint_short) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['call_date'] . ' ' . (string)($row['call_time'] ?? '')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['resolution_due_at'] ?? '—')) . "</td>";
                    echo "<td>" . tkt_overdue_badge($row) . "</td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script>
(function () {
    $(document).ready(function () {
        /* UXW-01 ⑤: التهيئةُ اليدويةُ حُذفت — المكوّنُ المركزيُّ (ui-unification.js)
           يهيّئ الجدولَ ويقرأ data-scroll-x و data-state-save من وسمِه. والفلاترُ
           الخارجيةُ تُوصَل بواجهةِ الجدولِ الجاهزةِ لا بتهيئةٍ ثانيةٍ تتصادم معها.
           stateSave:false إلزامي مع الفلاتر الخارجية، وإلا استُرجعت فلترةٌ محفوظةٌ
           من جلسةٍ سابقة فيظهر الجدول فارغًا بلا سبب ظاهر — ومعلَنٌ الآن سمةً. */
        function esc(s) { return $.fn.dataTable.util.escapeRegex(String(s)); }
        // فلترة أعمدة (بعد عمود «فتح»): الطبيعة(3) · المرحلة(4) · الإدارة(5)
        function tktWireFilters(dt) {
            $('#fStage').on('change', function () { var v = this.value; dt.column(4).search(v ? esc(v) : '', true, false).draw(); });
            $('#fOwner').on('change', function () { var v = this.value; dt.column(5).search(v ? '^' + esc(v) + '$' : '', true, false).draw(); });
            $('#fNature').on('change', function () { var v = this.value; dt.column(3).search(v ? '^' + esc(v) + '$' : '', true, false).draw(); });
            $('#fReset').on('click', function () {
                $('#fStage,#fOwner,#fNature').val('');
                dt.columns().search('').draw();
            });
        }
        // الوصلُ يعمل في الترتيبين: إن سبقَنا المكوّنُ المركزيُّ أخذنا واجهتَه،
        // وإلا انتظرنا حدثَ تهيئتِه — فلا فلترَ ميّتٌ ولا تهيئةٌ ثانية.
        var tktEl = document.getElementById('tktTable');
        if (tktEl && $.fn.dataTable) {
            if ($.fn.dataTable.isDataTable(tktEl)) { tktWireFilters($(tktEl).DataTable()); }
            else { $(tktEl).one('init.dt', function () { tktWireFilters($(tktEl).DataTable()); }); }
        }
    });
})();
</script>
<?php
// نافذة معالج الاستيراد الموحّد + أصوله (تُطبع مرّة واحدة — نمط العملاء)
if (function_exists('ems_excel_render')) {
    ems_excel_render('tickets');
}
?>
</body>
</html>
