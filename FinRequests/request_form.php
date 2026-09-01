<?php
/** بوابة الطلب المالي D05 — الشاشةُ الموحَّدة (§5)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الدمج (2026-08-21)**: كانت البوابةُ تُقدَّم في شاشتَين لا تنفصلان معنًى:
 *   `request_form.php` (إنشاءٌ وتحريرٌ وسجلٌّ) و`my_requests.php` (قائمةٌ).
 *   فمن أنشأ طلبًا انتقل ليراه، ومن رآه انتقل ليُنشئ — رحلةٌ واحدةٌ في بابَين.
 *   فدُمجتا هنا على نمطِ شاشةِ العملاء: **إحصاءٌ · فلاترُ بحثٍ · قائمةٌ ·
 *   نموذجٌ مطويٌّ يُفتح بالزر** — و`my_requests.php` صارت مُحوِّلًا لا شاشة.
 *
 * ◆ **لماذا بقيت هذه هي الباقية** لا الأخرى: صلاحيةُ الإضافةِ (`can_add`)
 *   مسجَّلةٌ على هذه الوحدةِ لأربعةَ عشرَ دورًا، وعلى الأخرى **صفرٌ لكلِّ
 *   الأدوار**. فلو بقيت الأخرى لَما أنشأ أحدٌ طلبًا حتى تُهاجَر الصلاحياتُ
 *   كلُّها — والدمجُ لا يُشترى بإسقاطِ حارس.
 *
 * ◆ **وضعان في ملفٍّ واحد**:
 *   ① بلا `?id=` ⇒ وضعُ القائمة بهذا الترتيب: بطاقاتُ إحصاءٍ (كلٌّ منها مُرشِّحٌ
 *      بنقرة) · **نموذجُ الإنشاءِ مطويًّا** · صندوقُ فلاترَ · جدولُ طلباتي.
 *   ② بـ`?id=N` ⇒ وضعُ السجل: شريطُ الرحلةِ · رأسُ الحالةِ · التفريعُ ·
 *      التحريرُ · المستنداتُ · البنودُ · الإرسالُ والسحبُ · السجلُّ الإلحاقي.
 *   والنطاقُ لم يتغيّر: القائمةُ طلباتي أنا (مُنشئًا أو صاحبًا)، والسجلُّ يُفتح
 *   لأيِّ طلبٍ تسمح به البوابةُ — كما كان في الشاشتَين قبل الدمج.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/_finreq_helpers.php';

$role = strval($_SESSION['user']['role']);
$user_id = intval($_SESSION['user']['id']);
$is_super = ($role === '-1');
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin request form super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/request_form.php');
if (!$is_super && !$__pp['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', '❌ لا صلاحية لبوابة الطلبات', 'GOV-PERM-403', '');
    exit();
}

$my_departments = finreq_departments_for_creator($gate, $role);

// مستخدمو الشركة للإدخال النيابي (§13.1 — يُوثَّق صاحب الطلب والمُدخِل معًا)
$company_users = array();
try {
    $company_users = $gate->select('users', array(
        'columns' => array('id', 'username'),
        'where' => array('status' => 'active'),
        'orderBy' => 'username ASC', 'limit' => 300,
    ));
} catch (\Throwable $t) { /* الحقل اختياري */ }
$catalog = finreq_catalog();
$doc_types = finreq_doc_types();
$state_defs = finreq_states();
$reject_defs = finreq_rejection_classes();

$req = null; $docs = array(); $lines = array(); $timeline = array();
$rid = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($rid > 0) {
    $req = finreq_fetch($gate, $rid);
    if ($req) {
        $docs = $gate->select('fin_request_documents', array('where' => array('request_id' => $rid), 'orderBy' => 'id ASC'));
        $lines = $gate->select('fin_request_lines', array('where' => array('request_id' => $rid), 'orderBy' => 'id ASC'));
        $timeline = $gate->select('fin_request_events', array('where' => array('request_id' => $rid), 'orderBy' => 'id ASC'));
    }
}
/* رقمُ طلبٍ لم يُعثر عليه (أو خارجَ نطاقِ الشركة) لا يُصيَّر سجلًّا فارغًا —
   ترجع الشاشةُ إلى القائمةِ وتقول السبب. */
$is_record = ($req !== null);
$not_found = ($rid > 0 && $req === null);

$editable = $req ? in_array($req['state'], array('draft', 'returned'), true) && ($is_super || intval($req['created_by']) === $user_id) : true;
// صلاحية الإضافة تحكم زرَّ فتح النموذج ونفسَ النموذج (نمط شاشات المجموعة أ)
$can_add = $is_super || !empty($__pp['can_add']);
// النموذج مخفيٌّ افتراضيًا (.allforms) ويُفتح بالزر؛ وعند تحرير مسودةٍ/معادٍ يُفتح فورًا
$form_visible = $is_record;

/* ═══════════════════════════════════════════════════════════════════════════
   وضعُ القائمة — الإحصاءُ والترشيحُ (لا يُحسب شيءٌ منه في وضعِ السجل)
   ═══════════════════════════════════════════════════════════════════════════ */

$groups = finreq_state_groups();
$my_rows = array(); $rows = array();
$g_count = array(); $g_total = 0;
$live_by_currency = array();
$f_state = ''; $f_type = ''; $f_q = ''; $f_from = ''; $f_to = '';
$user_names = array(); $dept_labels = array();

if (!$is_record) {
    // النطاق (§10): الطالب طلباته — والأدوار المالية والسوبر يرون عبر بوابة المالية لا هنا
    $my_rows = $gate->select('fin_requests', array(
        'whereRaw' => 'created_by = ? OR requester_id = ?',
        'params' => array($user_id, $user_id),
        'orderBy' => 'id DESC', 'limit' => 500,
    ));
    foreach ($my_rows as $k => $r) { $my_rows[$k] = finreq_sync_state($gate, $r); }

    // خرائطُ العرضِ: اسمُ المستخدمِ واسمُ الإدارةِ — تُقرأ مرةً لا مرةً لكلِّ صف
    foreach ($company_users as $cu) { $user_names[intval($cu['id'])] = strval($cu['username']); }
    foreach (finreq_active_routing($gate) as $rt) { $dept_labels[strval($rt['source_module'])] = strval($rt['module_label']); }

    /* ── ① الإحصاء: يُقاس على **كلِّ** طلباتي لا على المعروضِ بعد الترشيح ──
         فالبطاقةُ دليلُ تنقّلٍ («ثلاثةٌ معادةٌ إليك») لا صدًى للمرشِّح؛ ولو
         تبعت المرشِّحَ لقرأ من رشّح «مسودات» صفرًا في كلِّ بطاقةٍ سواها. */
    foreach ($groups as $gk => $gv) { $g_count[$gk] = 0; }
    $terminal = array_merge($groups['refused']['states'], $groups['settled']['states']);
    foreach ($my_rows as $r) {
        $g_total++;
        foreach ($groups as $gk => $gv) {
            if (in_array($r['state'], $gv['states'], true)) { $g_count[$gk]++; break; }
        }
        /* المبالغُ الحيّةُ **لكلِّ عملةٍ على حدة** — ولا تُجمع عملتان في رقمٍ واحد.
           و**العملةُ الفارغةُ ليست عملة**: تُسمّى «بلا عملة» صراحةً، فشرطةٌ
           مكانَها تُقرأ «لا قيمة» بينما القيمةُ قائمةٌ ووحدتُها هي المجهولة. */
        if (!in_array($r['state'], $terminal, true)) {
            $cur = trim(strval($r['currency'])) !== '' ? trim(strval($r['currency'])) : 'بلا عملة';
            if (!isset($live_by_currency[$cur])) { $live_by_currency[$cur] = 0.0; }
            $live_by_currency[$cur] += floatval($r['amount']);
        }
    }

    /* ── ② الترشيح: يعمل على المقروءِ في الذاكرةِ فلا استعلامَ ثانيًا ────── */
    $f_state = isset($_GET['state']) ? trim(strval($_GET['state'])) : '';
    $f_type  = isset($_GET['type'])  ? trim(strval($_GET['type']))  : '';
    $f_q     = isset($_GET['q'])     ? trim(strval($_GET['q']))     : '';
    $f_from  = isset($_GET['from'])  ? trim(strval($_GET['from']))  : '';
    $f_to    = isset($_GET['to'])    ? trim(strval($_GET['to']))    : '';

    // رمزُ الحالةِ إمّا مجموعةٌ من الست وإمّا حالةٌ مفردةٌ من الست عشرة — وما عداه يُهمَل
    $want_states = array();
    if ($f_state !== '') {
        if (isset($groups[$f_state])) { $want_states = $groups[$f_state]['states']; }
        elseif (isset($state_defs[$f_state])) { $want_states = array($f_state); }
        else { $f_state = ''; }
    }
    $q_low = $f_q !== '' ? mb_strtolower($f_q, 'UTF-8') : '';

    foreach ($my_rows as $r) {
        if ($want_states && !in_array($r['state'], $want_states, true)) { continue; }
        if ($f_type !== '' && strval($r['request_type']) !== $f_type) { continue; }
        $d = substr(strval($r['created_at']), 0, 10);
        if ($f_from !== '' && $d < $f_from) { continue; }
        if ($f_to !== '' && $d > $f_to) { continue; }
        if ($q_low !== '') {
            $hay = mb_strtolower(implode(' ', array(
                strval($r['request_no']), strval($r['justification'] ?? ''), strval($r['beneficiary_name'] ?? ''),
                strval($r['statement'] ?? ''), strval($r['source_ref'] ?? ''),
            )), 'UTF-8');
            if (mb_strpos($hay, $q_low) === false) { continue; }
        }
        $rows[] = $r;
    }
}

$page_title = 'إيكوبيشن | طلباتي المالية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell finreq-main ems-doc-cycle">
    <?php
    /* ─────────────────────────────────────────────────────────────────────
       العنوانُ في وضعِ السجلِّ يحمل **تسميةَ الرابطِ ورقمَ الطلبِ معًا**
       ─────────────────────────────────────────────────────────────────────
       ◆ الترويسةُ المشتركةُ تُحلُّ تسميةَ `nav_items` محلَّ `$header_title`
         (INJ-0132/0154/0428: «عنوانُ الصفحة يطابق تسميةَ الرابطِ حرفيًّا»)،
         فرقمُ الطلبِ كان يُمحى ويقرأ فاتحُ FR-2026-0009 عنوانَ القائمة.
       ◆ ونصُّ الحكمِ نفسِه يستثني **«شاشاتٍ تُفتح بمعرّف»** — وهذه إحداها.
         و`$header_title_html` هو المنفذُ المُعلَنُ الذي لا تُبدِّله الترويسة.
       ◆ فيُكتب الاثنان: التسميةُ كما وعد بها الرابطُ **ثم** رقمُ السجل —
         فلا يُنقض الحكمُ ولا يضيع ما يميّز الصفحةَ عن أختِها.
       ───────────────────────────────────────────────────────────────────── */
    if ($is_record) {
        $header_title      = 'طلباتي المالية';
        $header_title_html = 'طلباتي المالية <span class="fr-title-no">'
                           . htmlspecialchars($req['request_no'], ENT_QUOTES, 'UTF-8') . '</span>';
    } else {
        $header_title = 'طلباتي المالية';
    }
    $header_icon    = $is_record ? 'fa fa-file-invoice-dollar' : 'fa fa-list-check';
    $header_actions = array();
    // زر فتح نموذج الإنشاء — محكومٌ بصلاحية الإضافة، ولا يظهر أثناء تحرير طلبٍ قائم
    if ($can_add && !$is_record && $my_departments) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => 'إنشاء طلب مالي');
    }
    if (!$is_record) {
        $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'frq-toggle-stats-text');
    } else {
        $header_actions[] = array('href' => 'request_form.php', 'class' => 'add-btn', 'icon' => 'fa fa-list-check', 'label' => 'كل طلباتي');
    }
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_my_requests
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم الطلب' => 'g47',
            'تاريخ الطلب' => 'g48',
            'نوع الطلب' => 'g49',
            'تفاصيل الطلب' => 'g50',
            'المرفق' => 'g51',
            'الجهة المالكة للقرار' => 'g52',
            'مسار الاعتماد' => 'g53',
            'حالة الطلب' => 'g54',
            'قرار الجهة' => 'g55',
            'تاريخ القرار' => 'g56',
            'المنشئ' => 'g57',
            'تاريخ الإنشاء' => 'g58',
            'حالة البيانات' => 'g59',
            'مرجع المصدر' => 'g60',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('my_requests');
        echo ems_w14_grid('emsList_my_requests', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في الطلبات المقدمة'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    // UXW-01 ⑫: شاشةُ دورةٍ مستندية — الخطوةُ التاليةُ من حالةِ الطلبِ الحيّةِ إن وُجدت،
    // وإلا سلّمُ الدورةِ الثابت.
    if ($is_record && $req['state'] === 'draft') {
        $fr_next_step = 'الإرسال للمراجعة الإدارية';
    } elseif ($is_record && $req['state'] === 'returned') {
        $fr_next_step = 'إعادة الإرسال بالرقم نفسه بعد استيفاء سبب الإعادة';
    } elseif ($is_record) {
        $fr_next_step = 'سلم الاعتماد المالي';
    } else {
        $fr_next_step = 'إنشاء طلب أو متابعة المعاد إليك للاستكمال';
    }
    echo ems_next_step($fr_next_step);
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ)
    echo ems_states_bundle(
        $is_record ? 'لا بيانات طلب مالي للعرض' : 'لا طلبات ضمن هذا الترشيح',
        $is_record ? 'افتح طلبا قائما من قائمة طلباتك' : 'أنشئ طلبك الأول من زر «إنشاء طلب مالي» أعلى الشاشة أو غير المرشحات'
    );
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <?php if ($not_found): ?>
        <div class="alert alert-warning fr-alert">
            <i class="fa fa-circle-question"></i>
            لا طلب بالرقم <strong>#<?php echo intval($rid); ?></strong> ضمن نطاقك — وهذه قائمة طلباتك.
        </div>
    <?php endif; ?>

    <?php // حارس «لا إدارة مفعّلة» يخص الإنشاء فقط — عرض طلبٍ قائمٍ متاحٌ لكل
          // ممنوح الصلاحية (المحاسب والمالية يفتحان أي طلبٍ من صناديقهما) ?>
    <?php if (!$my_departments && !$is_record && !$my_rows): ?>
        <div class="card"><div class="card-body">
            <h5>⛔ لا إدارة مفعلة لدورك في بوابة الطلبات المالية بعد</h5>
            <p>الإنشاء متاح لأدوار الإدارات المفعلة في جدول التوجيه — راجع الإدارة المالية.</p>
        </div></div>
    <?php else: ?>

    <?php if ($is_record): ?>
        <?php
        /* شريط الرحلة (الدستور §5: «أعلى شاشة كل معاملة») — أولُ ما يراه
           فاتحُ الطلب: أين وصل، ومَن عليه الدور، وما سببُ الإعادة إن أُعيد. */
        require_once __DIR__ . '/../includes/journey_bar.php';
        $__jrt = finreq_routing_row($gate, $req['source_module']);
        ems_journey_bar(finreq_journey($gate, $req, $timeline, $__jrt));
        ?>
        <div class="card fr-mb14">
            <div class="card-body fr-head-flex">
                <div><strong>الحالة:</strong> <?php echo finreq_state_badge($req['state']); ?></div>
                <div><strong>النوع:</strong> <?php echo htmlspecialchars($catalog[$req['request_type']]['label'] ?? $req['request_type']); ?></div>
                <div><strong>الإدارة:</strong> <?php $rt = finreq_routing_row($gate, $req['source_module']); echo htmlspecialchars($rt ? $rt['module_label'] : $req['source_module']); ?></div>
                <?php if (intval($req['duplicate_flag']) === 1): ?><div><span class="badge bg-warning">⚠️ تحذير تكرار</span></div><?php endif; ?>
                <?php if (intval($req['is_exception']) === 1): ?><div><span class="badge bg-danger">🚨 استثناء طارئ معتمد — الدورة تستكمل خلال 72 ساعة</span></div><?php endif; ?>
                <?php if (!empty($req['event_id'])): ?><div><strong>الحدث المالي:</strong> #<?php echo intval($req['event_id']); ?></div><?php endif; ?>
                <?php if (!empty($req['parent_request_id'])):
                    $__parent = $gate->selectOne('fin_requests', array('columns' => array('request_no'), 'where' => array('id' => intval($req['parent_request_id']))));
                ?>
                    <div><span class="badge bg-info">🌿 فرع من
                        <a href="request_form.php?id=<?php echo intval($req['parent_request_id']); ?>" class="fr-link-inherit">
                            <?php echo htmlspecialchars($__parent ? $__parent['request_no'] : ('#' . intval($req['parent_request_id']))); ?></a></span></div>
                <?php endif; ?>
                <?php
                // §8.3: الطارئ غير المستثنى بعد — صاحبه (أو مدير إدارته أو السوبر) يطلب التنفيذ المسبق
                $can_ask_exception = $req && strval($req['need_class']) === 'emergency'
                    && intval($req['is_exception']) !== 1
                    && in_array($req['state'], array('under_review', 'pending_approval'), true)
                    && ($is_super || intval($req['created_by']) === $user_id || finreq_role_is($rt, $role, 'manager'));
                ?>
                <?php if ($can_ask_exception): ?>
                    <form action="request_actions.php" method="post" class="fr-form-inline"
                          onsubmit="var x=prompt('مبرر التنفيذ الطارئ (يقرؤه المدير المالي):');if(!x)return false;this.reason.value=x;">
        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="exception_request">
                        <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                        <input type="hidden" name="back" value="request_form.php">
                        <input type="hidden" name="reason" value="">
                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-bolt"></i> طلب تنفيذ طارئ (§8.3)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php
    // المسار المركّب (§6.2): شجرة الفروع + نموذج التفريع (للمحاسب 18 والمدير المالي 17)
    if ($is_record && empty($req['parent_request_id'])):
        $__kids = finreq_children($gate, intval($req['id']));
        $__can_split = ($is_super || $role === '17' || $role === '18')
            && in_array($req['state'], array('pending_approval', 'approved', 'posted'), true);
        if ($__kids || $__can_split):
    ?>
        <div class="card fr-split-card">
            <div class="card-header"><h5><i class="fa fa-code-branch"></i> المسار المركب — فروع الطلب (§6.2)</h5></div>
            <div class="card-body">
                <?php if ($__kids): $__ksum = 0.0; ?>
                    <?php foreach ($__kids as $kk): $__ksum += in_array($kk['state'], array('rejected','withdrawn','cancelled','expired','merged'), true) ? 0 : floatval($kk['amount']); ?>
                        <div class="fr-branch-row">
                            🌿 <strong><a href="request_form.php?id=<?php echo intval($kk['id']); ?>"><?php echo htmlspecialchars($kk['request_no']); ?></a></strong>
                            · <?php echo htmlspecialchars($catalog[$kk['request_type']]['label'] ?? $kk['request_type']); ?>
                            · <?php echo number_format(floatval($kk['amount']), 2); ?>
                            · <?php echo finreq_state_badge($kk['state']); ?>
                            <span class="fr-note-ink"><?php echo htmlspecialchars($kk['justification'] ?? ''); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="fr-sum">مجموع الفروع الحية: <?php echo number_format($__ksum, 2); ?> من أصل <?php echo number_format(floatval($req['amount']), 2); ?></div>
                <?php endif; ?>
                <?php if ($__can_split): ?>
                    <form action="request_actions.php" method="post" class="fr-split-form">
        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="split_request">
                        <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                        <input type="hidden" name="back" value="request_form.php">
                        <div><label for="emsf_171_b0863">وصف الفرع *</label><input type="text" name="child_label" placeholder="دفعة مقدمة 30%" required class="fr-w200" id="emsf_171_b0863"></div>
                        <div><label for="emsf_172_3f743">مبلغ الفرع *</label><input type="number" step="0.01" min="0.01" name="child_amount" required class="fr-w140" id="emsf_172_3f743"></div>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-code-branch"></i> تفريع دفعة</button>
                    </form>
                    <p class="fr-hint">💡 الفرع يرث مستندات أصله وبواباته الإدارية، ويولد لدى محاسب الإدارة — ولا يغلق الأصل وفروعه معلقة.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; endif; ?>

    <?php /* ═════ وضعُ القائمة ① — بطاقاتُ الإحصاء: كلُّ بطاقةٍ مُرشِّحٌ بنقرة ═════ */ ?>
    <?php if (!$is_record && $my_rows): ?>
        <?php /* ظاهرٌ ابتداءً: البطاقةُ هنا **مُرشِّحٌ بنقرةٍ** لا زينةَ تقريرٍ —
                 ومن أخفاها ابتداءً أخفى أداةَ التنقُّلِ نفسَها. والطيُّ متاحٌ
                 بالزرِّ ويُحفظ اختيارُه محليًّا. */ ?>
        <div class="stats-section" id="frqStatsSection">
            <div class="stats-grid ems-statgrid ems-statgrid--fill" data-cols="4">
                <a class="stats-card stats-primary<?php echo $f_state === '' ? ' frq-stat-on' : ''; ?>"
                   href="request_form.php<?php echo finreq_qs(array('state' => '')); ?>" title="رفع ترشيح الحالة">
                    <div class="stats-icon"><i class="fa fa-layer-group"></i></div>
                    <div class="stats-value"><?php echo $g_total; ?></div>
                    <div class="stats-title">إجمالي طلباتي</div>
                </a>
                <?php foreach ($groups as $gk => $gv): ?>
                    <a class="stats-card <?php echo $gv['tone']; ?><?php echo $f_state === $gk ? ' frq-stat-on' : ''; ?>"
                       href="request_form.php<?php echo finreq_qs(array('state' => $gk)); ?>"
                       title="ترشيح الجدول على «<?php echo htmlspecialchars($gv['label']); ?>»">
                        <div class="stats-icon"><i class="<?php echo $gv['icon']; ?>"></i></div>
                        <div class="stats-value"><?php echo intval($g_count[$gk]); ?></div>
                        <div class="stats-title"><?php echo htmlspecialchars($gv['label']); ?></div>
                    </a>
                <?php endforeach; ?>
                <?php
                /* المبلغُ الحيُّ: عملةٌ واحدةٌ ⇒ رقم · أكثرُ من عملةٍ ⇒ نصٌّ لكلِّ
                   عملةٍ على حدة. «لا تُجمع عملتان في رقمٍ واحد» — والجمعُ هنا
                   كان سيصنع مبلغًا لا يقابله شيءٌ في أيِّ دفتر. */
                $cur_n = count($live_by_currency);
                ?>
                <div class="stats-card stats-cyan">
                    <div class="stats-icon"><i class="fa fa-coins"></i></div>
                    <?php if ($cur_n === 0): ?>
                        <div class="stats-value">—</div>
                    <?php elseif ($cur_n === 1): ?>
                        <?php $__c = array_key_first($live_by_currency); ?>
                        <div class="stats-value"><?php echo number_format($live_by_currency[$__c], 0); ?></div>
                        <div class="ems-statcard__meta"><?php echo htmlspecialchars($__c); ?></div>
                    <?php else: ?>
                        <div class="stats-value ems-statcard__value--text">
                            <?php
                            $parts = array();
                            foreach ($live_by_currency as $c => $v) { $parts[] = number_format($v, 0) . ' ' . htmlspecialchars($c); }
                            echo implode(' · ', $parts);
                            ?>
                        </div>
                        <div class="ems-statcard__meta">مجموع لكل عملة على حدة</div>
                    <?php endif; ?>
                    <div class="stats-title">قيمة الطلبات الحية</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php /* ═════ وضعُ القائمة ② — نموذجُ الإنشاءِ **فوقَ الفلاتر** (قرارُ المالك)
             فعلُ الإضافةِ يسبق فعلَ البحث: مَن فتح النموذجَ لا يمرُّ على مرشِّحاتٍ
             لا تخصُّه، ومَن جاء يبحث يجد الفلاترَ فوقَ الجدولِ مباشرةً لأن
             النموذجَ مطويٌّ لا يشغل ارتفاعًا. (والكتلةُ نفسُها هي نموذجُ التحرير
             في وضعِ السجل — ولم يتغيّر موضعُها هناك لأن ① و③ لا يُصيَّران.) ═════ */ ?>
    <?php if (!$is_record && !$can_add): ?>
        <div class="card"><div class="card-body">
            <h5>⛔ لا تملك صلاحية إنشاء طلب مالي</h5>
            <p>تستطيع متابعة طلباتك القائمة من الجدول أدناه.</p>
        </div></div>
    <?php endif; ?>

    <?php if ($editable && ($is_record || $can_add)): ?>
    <?php if (!$is_record): ?>
        <?php
        /* ═══════════════════════════════════════════════════════════════════
         * الإرشادُ يُشترط بشرطِ الزرِّ نفسِه — وإلا وجَّه إلى ما أخفاه النظام
         * ───────────────────────────────────────────────────────────────────
         * ◆ الزرُّ محكومٌ بـ`$can_add && !$is_record && $my_departments`
         *   والإرشادُ كان محكومًا بـ`$can_add` وحدَها. فمن له الصلاحيةُ ولا
         *   إدارةَ مسنَدةٌ إليه كان يقرأ «اضغط زر ‹إنشاء طلب مالي›» **ولا زرَّ
         *   في الصفحة** — فيقف بلا تفسير.
         * ◆ فصار الإرشادُ بشرطِ الزرِّ حرفًا، ومحلَّه عند غيابِ الإسنادِ رسالةٌ
         *   **تقول السببَ والفعلَ التالي** — فالشاشةُ لا تصمت ولا تكذب.
         * ═══════════════════════════════════════════════════════════════════ */
        ?>
        <?php if ($can_add && $my_departments): ?>
        <div class="alert alert-info fr-alert" id="finreqHint">
            <i class="fa fa-arrow-up"></i> اضغط زر <strong>«إنشاء طلب مالي»</strong> أعلى الصفحة لفتح النموذج.
        </div>
        <?php elseif ($can_add): ?>
        <div class="alert alert-warning fr-alert" id="finreqNoDept">
            <i class="fa fa-circle-info"></i>
            لديك صلاحية إنشاء طلب مالي، <strong>ولا إدارة مسندة إليك</strong> — والطلب
            ينشأ باسم إدارة. راجع مسؤول الصلاحيات لإسناد إدارتك، وتستطيع
            الآن متابعة طلباتك في الجدول أدناه.
        </div>
        <?php endif; ?>
    <?php endif; ?>
    <form id="finreqForm" action="request_actions.php" method="post" class="allforms<?php echo $form_visible ? ' allforms-visible' : ''; ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $is_record ? 'update_draft' : 'create'; ?>">
        <?php if ($is_record): ?><input type="hidden" name="id" value="<?php echo intval($req['id']); ?>"><?php endif; ?>
        <div class="card">
            <?php /* ف١١-٢: عنوانُ القسمِ اسمٌ مؤسسيٌّ فقط — بلا رقمِ إصدارٍ ولا
                     مرجعِ فقرةٍ ولا رمزِ وثيقة. والشرحُ في التلميحِ لا في الاسم. */ ?>
            <div class="card-header"><h5 title="تراجع دواعي الطلب ومبرراته قبل إنشائه"><i class="fa fa-scale-balanced"></i> مبررات الطلب</h5></div>
            <div class="card-body"><div class="form-grid">
                <div>
                    <label for="emsf_173_c599f">الإدارة صاحبة الاحتياج *</label>
                    <select name="source_module" aria-label="الإدارة صاحبة الاحتياج" required <?php echo $is_record ? 'disabled' : ''; ?> id="emsf_173_c599f">
                        <?php foreach ($my_departments as $d): ?>
                            <option value="<?php echo htmlspecialchars($d['source_module']); ?>" <?php echo ($is_record && $req['source_module'] === $d['source_module']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['module_label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($is_record): ?><input type="hidden" name="source_module" value="<?php echo htmlspecialchars($req['source_module']); ?>"><?php endif; ?>
                </div>
                <div>
                    <label for="emsf_174_4439f">نوع الطلب *</label>
                    <select name="request_type" aria-label="نوع الطلب" required <?php echo $is_record ? 'disabled' : ''; ?> id="emsf_174_4439f">
                        <?php foreach ($catalog as $tkey => $t): if (!$t['active']) continue; ?>
                            <option value="<?php echo $tkey; ?>" <?php echo ($is_record && $req['request_type'] === $tkey) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($is_record): ?><input type="hidden" name="request_type" value="<?php echo htmlspecialchars($req['request_type']); ?>"><?php endif; ?>
                </div>
                <div>
                    <label for="emsf_175_3421b">لماذا نحتاج هذا الآن؟ (المبرر) *</label>
                    <input type="text" name="justification" aria-label="مبرر الاحتياج" maxlength="255" required value="<?php echo htmlspecialchars($req['justification'] ?? ''); ?>" placeholder="لا طلب بلا لماذا" id="emsf_175_3421b">
                </div>
                <div>
                    <label for="emsf_176_1cbe3">تصنيف الحاجة *</label>
                    <select name="need_class" id="emsf_176_1cbe3">
                        <?php foreach (array('planned' => 'مخطط', 'unplanned' => 'غير مخطط', 'urgent' => 'عاجل', 'emergency' => 'طارئ') as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($is_record && $req['need_class'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="emsf_177_2393b">المرجع المصدري (أمر شراء/بلاغ/عقد)</label>
                    <input type="text" name="source_ref" aria-label="المرجع المصدري (أمر شراء/بلاغ/عقد)" maxlength="60" value="<?php echo htmlspecialchars($req['source_ref'] ?? ''); ?>" id="emsf_177_2393b">
                </div>
                <div>
                    <label for="emsf_178_6febc">البديل المدروس إن وجد (ملاحظات)</label>
                    <input type="text" name="notes" aria-label="البديل المدروس (ملاحظات)" maxlength="255" value="<?php echo htmlspecialchars($req['notes'] ?? ''); ?>" id="emsf_178_6febc">
                </div>
                <?php if (!$is_record && $company_users): ?>
                <div>
                    <label for="emsf_179_5d929" title="يسجل صاحب الطلب والمدخل معا في سجل الطلب">الإدخال نيابة عن (اختياري)</label>
                    <select name="requester_id" id="emsf_179_5d929">
                        <option value="">— أنا صاحب الطلب —</option>
                        <?php foreach ($company_users as $cu): if (intval($cu['id']) === $user_id) { continue; } ?>
                            <option value="<?php echo intval($cu['id']); ?>"><?php echo htmlspecialchars($cu['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div></div>
        </div>

        <div class="card">
            <div class="card-header"><h5><i class="fa fa-user-tag"></i> بيانات الطلب والمستفيد (§5)</h5></div>
            <div class="card-body"><div class="form-grid">
                <div>
                    <label for="emsf_180_63fe2">نوع المستفيد *</label>
                    <select name="beneficiary_type" id="emsf_180_63fe2">
                        <?php foreach (array('supplier' => 'مورد', 'employee' => 'موظف', 'customer' => 'عميل', 'internal' => 'داخلي', 'other' => 'آخر') as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($is_record && $req['beneficiary_type'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="emsf_181_cf074">اسم الجهة المستفيدة</label>
                    <input type="text" name="beneficiary_name" aria-label="اسم الجهة المستفيدة" maxlength="160" value="<?php echo htmlspecialchars($req['beneficiary_name'] ?? ''); ?>" id="emsf_181_cf074">
                </div>
                <div>
                    <label for="emsf_182_c391e">مرجع المستفيد (معرف المورد/الموظف)</label>
                    <input type="number" name="beneficiary_ref" aria-label="مرجع المستفيد (معرف المورد/الموظف)" min="1" value="<?php echo htmlspecialchars($req['beneficiary_ref'] ?? ''); ?>" id="emsf_182_c391e">
                </div>
                <div>
                    <label for="emsf_183_6701d">المبلغ *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" aria-label="مبلغ الطلب" required value="<?php echo htmlspecialchars($req['amount'] ?? ''); ?>" id="emsf_183_6701d">
                </div>
                <div>
                    <label for="emsf_184_b3d0b">العملة</label>
                    <input type="text" name="currency" aria-label="عملة الطلب" maxlength="8" value="<?php echo htmlspecialchars($req['currency'] ?? 'SDG'); ?>" id="emsf_184_b3d0b">
                </div>
                <div>
                    <label for="emsf_185_c942d">طريقة الدفع المقترحة</label>
                    <select name="payment_method" id="emsf_185_c942d">
                        <option value="">— تقررها الخزينة —</option>
                        <?php foreach (array('cash' => 'نقدًا', 'bank' => 'بنكي', 'transfer' => 'تحويل', 'cheque' => 'شيك') as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($is_record && ($req['payment_method'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="emsf_186_ea366">البيان</label>
                    <input type="text" name="statement" aria-label="بيان الطلب" maxlength="255" value="<?php echo htmlspecialchars($req['statement'] ?? ''); ?>" id="emsf_186_ea366">
                </div>
                <div>
                    <label for="emsf_187_e60a0">تاريخ الاستحقاق المطلوب</label>
                    <input type="date" name="needed_by" aria-label="تاريخ الاستحقاق المطلوب" value="<?php echo htmlspecialchars($req['needed_by'] ?? ''); ?>" id="emsf_187_e60a0">
                </div>
                <div>
                    <label for="emsf_188_4e044">الأولوية</label>
                    <select name="priority" id="emsf_188_4e044">
                        <?php foreach (array('normal' => 'اعتيادية', 'high' => 'مرتفعة', 'critical' => 'حرجة') as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($is_record && $req['priority'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="emsf_189_3f6bd">المشروع (اختياري)</label>
                    <input type="number" name="project_id" aria-label="المشروع (اختياري)" min="1" value="<?php echo htmlspecialchars($req['project_id'] ?? ''); ?>" id="emsf_189_3f6bd">
                </div>
                <div>
                    <label for="emsf_190_c5077">المعدة (اختياري)</label>
                    <input type="number" name="equipment_id" aria-label="المعدة (اختياري)" min="1" value="<?php echo htmlspecialchars($req['equipment_id'] ?? ''); ?>" id="emsf_190_c5077">
                </div>
                <div>
                    <label for="emsf_191_85665">مركز التكلفة (اختياري)</label>
                    <input type="text" name="cost_center" aria-label="مركز التكلفة (اختياري)" maxlength="60" value="<?php echo htmlspecialchars($req['cost_center'] ?? ''); ?>" id="emsf_191_85665">
                </div>
            </div>
            <div class="fr-mt14">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?php echo $is_record ? 'تحديث البيانات' : 'حفظ مسودة برقم خادمي'; ?></button>
            </div>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <?php /* ═════ وضعُ القائمة ③ — صندوقُ فلاترِ البحث (ems-filters.css) ═════ */ ?>
    <?php if (!$is_record && $my_rows): ?>
        <div class="filter">
            <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
            <div class="filter-body">
                <form method="get" action="request_form.php">
                    <div class="filter-field">
                        <label for="frq_f_state"><i class="fa fa-flag"></i> الحالة</label>
                        <select name="state" id="frq_f_state" class="form-control">
                            <option value="">— كل الحالات —</option>
                            <optgroup label="مجموعات الحالة">
                                <?php foreach ($groups as $gk => $gv): ?>
                                    <option value="<?php echo $gk; ?>" <?php echo $f_state === $gk ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($gv['label']); ?> (<?php echo intval($g_count[$gk]); ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="حالة مفردة">
                                <?php foreach ($state_defs as $sk => $sv): ?>
                                    <option value="<?php echo $sk; ?>" <?php echo $f_state === $sk ? 'selected' : ''; ?>><?php echo htmlspecialchars($sv['label']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="frq_f_type"><i class="fa fa-tags"></i> نوع الطلب</label>
                        <select name="type" id="frq_f_type" class="form-control">
                            <option value="">— كل الأنواع —</option>
                            <?php foreach ($catalog as $tk => $tv): ?>
                                <option value="<?php echo $tk; ?>" <?php echo $f_type === $tk ? 'selected' : ''; ?>><?php echo htmlspecialchars($tv['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="frq_f_q"><i class="fa fa-magnifying-glass"></i> بحث نصي</label>
                        <input type="text" name="q" id="frq_f_q" class="form-control" maxlength="80"
                               value="<?php echo htmlspecialchars($f_q); ?>"
                               placeholder="رقم الطلب · المبرر · المستفيد · المرجع">
                    </div>
                    <div class="filter-field">
                        <label for="frq_f_from"><i class="fa fa-calendar"></i> من تاريخ</label>
                        <input type="date" name="from" id="frq_f_from" class="form-control" value="<?php echo htmlspecialchars($f_from); ?>">
                    </div>
                    <div class="filter-field">
                        <label for="frq_f_to"><i class="fa fa-calendar"></i> إلى تاريخ</label>
                        <input type="date" name="to" id="frq_f_to" class="form-control" value="<?php echo htmlspecialchars($f_to); ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary"><i class="fa fa-search"></i> عرض</button>
                        <a href="request_form.php" class="btn" title="إعادة ضبط"><i class="fa fa-rotate-right"></i></a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php /* ═════ وضعُ القائمة ④ — جدولُ طلباتي ═════ */ ?>
    <?php if (!$is_record): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-table"></i> طلباتي
                <?php if (count($rows) !== count($my_rows)): ?>
                    — <?php echo count($rows); ?> من <?php echo count($my_rows); ?> بعد الترشيح
                <?php else: ?>
                    (آخر <?php echo count($my_rows); ?>)
                <?php endif; ?>
            </h5></div>
            <div class="card-body table-container">
                <table class="display frq-table">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th><th>نوع الطلب</th><th>المبرر</th><th>المستفيد</th>
                            <th>المبلغ</th><th>الحالة</th><th>الحدث</th><th>أنشئ</th><th></th>
                            <?php /* CMP-03 ⑤ الأعمدة الوظيفية — وصِلت بمصدرِها الصفّيِّ (XF-01)
                                      عبر `data-fn-src` + `data-xf` على الصف، فصارت بياناتٍ لا شرَطات */ ?>
                            <th class="ems-fn-th" data-fn="1" data-fn-src="req_date">تاريخ الطلب</th>
                            <th class="ems-fn-th" data-fn="1" data-fn-src="requester">مقدم الطلب</th>
                            <th class="ems-fn-th" data-fn="1" data-fn-src="dept">الإدارة</th>
                            <th class="ems-fn-th" data-fn="1" data-fn-src="descr">الوصف</th>
                            <th class="ems-fn-th" data-fn="1" data-fn-src="party">الجهة المعنية</th>
                            <th class="ems-fn-th" data-fn="1" data-fn-src="reject">سبب الرفض</th>
                            <?php /* CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js */ ?>
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="required_approver" data-slice="1" title="من يلزم اعتماده بحسب سلسلة الاعتماد">المعتمد المطلوب</th>
                            <th class="ems-gov-th none" data-gov="attachments" data-slice="3" title="مرفقات الإثبات">المرفقات</th>
                            </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $__uid = intval($r['requester_id']) > 0 ? intval($r['requester_id']) : intval($r['created_by']);
                        $__xf = array(
                            'req_date'  => substr(strval($r['created_at']), 0, 10),
                            'requester' => isset($user_names[$__uid]) ? $user_names[$__uid] : ('#' . $__uid),
                            'dept'      => isset($dept_labels[strval($r['source_module'])]) ? $dept_labels[strval($r['source_module'])] : strval($r['source_module']),
                            'descr'     => trim(strval($r['statement'] ?? '')) !== '' ? strval($r['statement']) : strval($r['justification'] ?? ''),
                            'party'     => trim(strval($r['beneficiary_name'] ?? '')),
                            'reject'    => (!empty($r['rejection_class']) && isset($reject_defs[$r['rejection_class']]))
                                ? $reject_defs[$r['rejection_class']] . (trim(strval($r['decision_ref'] ?? '')) !== '' ? ' — ' . $r['decision_ref'] : '')
                                : '',
                        );
                    ?>
                        <tr data-xf="<?php echo htmlspecialchars(json_encode($__xf, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                            <td><strong><?php echo htmlspecialchars($r['request_no']); ?></strong><?php echo intval($r['duplicate_flag']) === 1 ? ' <span class="badge bg-warning">تكرار؟</span>' : ''; ?></td>
                            <td><?php echo htmlspecialchars($catalog[$r['request_type']]['label'] ?? $r['request_type']); ?></td>
                            <td><?php echo htmlspecialchars(mb_substr($r['justification'] ?? '', 0, 40)); ?></td>
                            <td><?php echo htmlspecialchars($r['beneficiary_name'] ?? '-'); ?></td>
                            <?php // العملةُ الفارغةُ تُسمّى ولا تُترك فراغًا يوهم أن المبلغَ بلا وحدة ?>
                            <td><?php echo number_format(floatval($r['amount']), 2) . ' '
                                    . htmlspecialchars(trim(strval($r['currency'])) !== '' ? $r['currency'] : 'بلا عملة'); ?></td>
                            <td><?php echo finreq_state_badge($r['state']); ?></td>
                            <td><?php echo $r['event_id'] ? ('#' . intval($r['event_id'])) : '—'; ?></td>
                            <td><?php echo htmlspecialchars(substr($r['created_at'], 0, 10)); ?></td>
                            <td>
                                <a href="request_form.php?id=<?php echo intval($r['id']); ?>" class="action-btn view" title="فتح"><i class="fa fa-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($is_record): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-paperclip"></i> المستندات — شرط العبور (<?php echo htmlspecialchars($catalog[$req['request_type']]['docs_label']); ?>)</h5></div>
            <div class="card-body">
                <table class="table table-bordered no-datatable" data-no-dt="1">
                    <thead><tr><th>#</th><th>النوع</th><th>الملف</th><th>رافعه</th><th>التاريخ</th></tr></thead>
                    <tbody>
                    <?php $i = 1; foreach ($docs as $d): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($doc_types[$d['doc_type']] ?? $d['doc_type']); ?></td>
                            <td><a href="../<?php echo htmlspecialchars($d['file_ref']); ?>" target="_blank">عرض المستند</a></td>
                            <td><?php echo intval($d['uploaded_by']); ?></td>
                            <td><?php echo htmlspecialchars($d['uploaded_at']); ?></td>
                        </tr>
                    <?php endforeach; if (!$docs): ?>
                        <tr><td colspan="5">لا مستندات بعد — <strong>لن يغادر الطلب المسودة بلا مستنده الإلزامي</strong></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($editable || $req['state'] === 'under_review'): ?>
                <form action="request_actions.php" method="post" enctype="multipart/form-data" class="allforms allforms-visible fr-mt10">
        <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="attach_doc">
                    <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                    <div class="form-grid">
                        <div>
                            <label for="emsf_192_401e7">نوع المستند</label>
                            <select name="doc_type" id="emsf_192_401e7">
                                <?php foreach ($doc_types as $k => $v): ?>
                                    <option value="<?php echo $k; ?>" <?php echo in_array($k, $catalog[$req['request_type']]['docs'], true) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="emsf_193_eb0ac">الملف (صور/PDF · 5MB)</label>
                            <input type="file" name="doc_file" accept=".jpg,.jpeg,.png,.webp,.pdf" required id="emsf_193_eb0ac">
                        </div>
                        <div class="fr-self-end">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> إرفاق</button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($catalog[$req['request_type']]['has_lines'])): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-list-ol"></i> بنود الطلب (§11.2)</h5></div>
            <div class="card-body">
                <table class="table table-bordered no-datatable" data-no-dt="1">
                    <thead><tr><th>#</th><th>البند</th><th>الكمية</th><th>الوحدة</th><th>سعر الوحدة</th><th>الإجمالي</th><th></th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
                    <tbody>
                    <?php $i = 1; $lines_total = 0; foreach ($lines as $L): $lines_total += floatval($L['line_total']); ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($L['item']); ?></td>
                            <td><?php echo floatval($L['qty']); ?></td>
                            <td><?php echo htmlspecialchars($L['unit'] ?? '-'); ?></td>
                            <td><?php echo number_format(floatval($L['unit_price']), 2); ?></td>
                            <td><strong><?php echo number_format(floatval($L['line_total']), 2); ?></strong></td>
                            <td>
                                <?php if ($editable): ?>
                                <form action="request_actions.php" method="post" class="fr-inline-form">
        <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_line">
                                    <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                                    <input type="hidden" name="line_id" value="<?php echo intval($L['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('حذف البند؟');"><i class="fa fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($lines): ?><tr><td colspan="5"><strong>مجموع البنود</strong></td><td colspan="2"><strong><?php echo number_format($lines_total, 2); ?></strong></td></tr><?php endif; ?>
                    </tbody>
                </table>
                <?php if ($editable): ?>
                <form action="request_actions.php" method="post" class="allforms allforms-visible">
        <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_line">
                    <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                    <div class="form-grid">
                        <div><label for="emsf_194_490eb">البند *</label><input type="text" name="item" maxlength="200" required id="emsf_194_490eb"></div>
                        <div><label for="emsf_195_cf39a">الكمية *</label><input type="number" step="0.01" min="0.01" name="qty" value="1" required id="emsf_195_cf39a"></div>
                        <div><label for="emsf_196_1ae28">الوحدة</label><input type="text" name="unit" maxlength="20" id="emsf_196_1ae28"></div>
                        <div><label for="emsf_197_2f1b7">سعر الوحدة</label><input type="number" step="0.01" min="0" name="unit_price" value="0" id="emsf_197_2f1b7"></div>
                        <div class="fr-self-end"><button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> إضافة بند</button></div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($editable): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-paper-plane"></i> الإرسال والسحب</h5></div>
            <div class="card-body fr-actions-flex">
                <form action="request_actions.php" method="post">
        <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="<?php echo $req['state'] === 'returned' ? 'resubmit' : 'submit'; ?>">
                    <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                    <input type="hidden" name="back" value="request_form.php">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> <?php echo $req['state'] === 'returned' ? 'إعادة الإرسال بالرقم نفسه' : 'إرسال للمراجعة الإدارية'; ?></button>
                </form>
                <form action="request_actions.php" method="post" class="fr-form-row">
        <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="withdraw">
                    <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                    <input type="hidden" name="back" value="request_form.php">
                    <input type="text" name="reason" placeholder="سبب السحب (إلزامي)" required class="fr-w220" aria-label="سبب السحب (إلزامي)">
                    <button type="submit" class="btn btn-danger"><i class="fa fa-box-archive"></i> سحب الطلب</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h5><i class="fa fa-timeline"></i> سجل الطلب الإلحاقي (§8.6 — لا يمحى)</h5></div>
            <div class="card-body">
                <table class="table table-striped no-datatable" data-no-dt="1">
                    <thead><tr><th>#</th><th>الحدث</th><th>الفاعل</th><th>البيان</th><th>قبل → بعد</th><th>التوقيت</th></tr></thead>
                    <tbody>
                    <?php $i = 1; foreach ($timeline as $ev): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><code><?php echo htmlspecialchars($ev['event_type']); ?></code></td>
                            <td><?php echo intval($ev['actor_user_id']); ?><?php echo $ev['on_behalf_of'] ? ' (نيابة عن ' . intval($ev['on_behalf_of']) . ')' : ''; ?></td>
                            <td><?php echo htmlspecialchars($ev['body'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(($ev['old_value'] !== null || $ev['new_value'] !== null) ? (($ev['old_value'] ?? '—') . ' → ' . ($ev['new_value'] ?? '—')) : ''); ?></td>
                            <td><?php echo htmlspecialchars($ev['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// إظهار/إخفاء نموذج الإنشاء بزر الرأس (#toggleForm) — نمط شاشات المجموعة أ:
// النموذج .allforms مخفيٌّ افتراضيًا بالـCSS ويُفتح بإضافة .allforms-visible.
// وزرُّ (#toggleStats) يطوي قسمَ الإحصاءِ كما في شاشة العملاء.
(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); }
        else { document.addEventListener('DOMContentLoaded', fn); }
    }
    ready(function () {
        var btn  = document.getElementById('toggleForm');
        var form = document.getElementById('finreqForm');
        var hint = document.getElementById('finreqHint');
        if (btn && form) {
            var setState = function (open) {
                form.classList.toggle('allforms-visible', open);
                btn.classList.toggle('is-active', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                /* بصنفٍ لا بنمطٍ موضعيّ: `ems-alerts.css` تفرض `display:flex`
                   **مُعجَّبةً** على `.alert-info` فتبتلع `style.display='none'`
                   — والصنفُ `.frq-hidden` له محدِّدٌ يغلبها في `ems-screens.css`. */
                if (hint) { hint.classList.toggle('frq-hidden', open); }
                if (open) { form.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            };
            setState(form.classList.contains('allforms-visible'));
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                setState(!form.classList.contains('allforms-visible'));
            });
        }

        var sBtn = document.getElementById('toggleStats');
        var sBox = document.getElementById('frqStatsSection');
        if (sBtn && sBox) {
            /* الحالةُ تُحفظ محليًّا فلا تُنسى بين الزيارات — والمفتاحُ باسمِ الشاشة */
            var KEY = 'ems.finreq.stats';
            var lbl = sBtn.querySelector('.frq-toggle-stats-text');
            var ico = sBtn.querySelector('i');
            var apply = function (open) {
                sBox.classList.toggle('frq-hidden', !open);
                sBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (lbl) { lbl.textContent = open ? 'إخفاء الإحصائيات' : 'إظهار الإحصائيات'; }
                if (ico) { ico.className = open ? 'fas fa-eye-slash' : 'fas fa-eye'; }
            };
            /* الافتراضُ **ظاهرٌ** — والمخزَّنُ يغلبه إن سبق للمستخدمِ أن اختار */
            var saved = null;
            try { saved = window.localStorage.getItem(KEY); } catch (e) { saved = null; }
            apply(saved === null ? true : saved === '1');
            sBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var open = sBox.classList.contains('frq-hidden');
                apply(open);
                try { window.localStorage.setItem(KEY, open ? '1' : '0'); } catch (e2) { /* التخزينُ زينةٌ لا شرط */ }
            });
        }
    });
})();
</script>
</body>
</html>
