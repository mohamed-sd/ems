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
require_once __DIR__ . '/../includes/w14_grid.php';
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

/* ══ `?open=` كان معامَلًا ميّتًا ═══════════════════════════════════════════
     `dept_inbox.php` و`includes/related_tickets_tab.php` يوجِّهان رقمَ البلاغِ
     إلى `tickets_list.php?open=<id>` — وهذه الشاشةُ **لا تقرأ المعامَلَ إطلاقًا**،
     فالضغطُ يُنزل المستخدمَ على التبويبِ الافتراضيِّ بلا فتحٍ ولا تمييز. ويُقرأ
     ذلك عطلًا صامتًا لا حدًّا معلَنًا. فيُشرَّف المعامَلُ بوجهتِه الطبيعية:
     البلاغُ نفسُه — وهي وجهةُ زرِّ «فتح التذكرة» في الصفِّ ذاتِه. */
$__open = isset($_GET['open']) ? intval($_GET['open']) : 0;
if ($__open > 0) {
    header('Location: ticket_form.php?id=' . $__open);
    exit();
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

/* ══ صندوقُ الإدارة يصير تبويبًا (دمجُ Tickets/dept_inbox.php) ═══════════════
     كانت شاشةً مستقلّةً مربوطةً بـ34 دورًا، وهي في حقيقتها **مرشِّحٌ** على
     `ticket_workstreams` بوحدةِ دورِ المستخدم — بأعمدةٍ أقلَّ من لوحةِ المسارات
     وبلا حارسِ صلاحيةٍ إطلاقًا. فتُنقل هنا لتَرِثَ حارسَ `can_view` أعلاه
     والفلاترَ وزرَّ Excel، ويبقى للمستخدمِ **بابٌ واحدٌ** للبلاغات.

   ◆ ولماذا قائمةُ معرِّفاتٍ لا `EXISTS` داخلَ الاستعلام: `scopedQuery` تلزم
     إعلانَ كلِّ جدولٍ مستأجرٍ يظهر بعد FROM/JOIN، وإعلانُ `ticket_workstreams`
     في `scope` يحقن `ws.company_id = ?` في الشرطِ **العلوي** — واسمٌ مستعارٌ
     داخلَ استعلامٍ فرعيٍّ لا يُرى من هناك. فالعزلُ يُطبَّق على استعلامِ
     المساراتِ وحدَه، وتُمرَّر ثمرتُه معرِّفاتٍ. */
$dept_unit = 0;
$dept_ids  = array();
if (!$is_super_admin) {
    require_once __DIR__ . '/dept_inbox_map.php';
    $dept_unit = ems_dept_unit_of_role($current_role_id);
}
if ($dept_unit > 0) {
    $ws_rows = tkt_gate($is_super_admin)->scopedQuery(
        array('scope' => array('ws' => 'ticket_workstreams')),
        "SELECT DISTINCT ws.tk_id FROM ticket_workstreams ws
          WHERE {TENANT_SCOPE} AND ws.org_unit_id = " . intval($dept_unit));
    foreach ($ws_rows as $__w) { $dept_ids[] = intval($__w['tk_id']); }
}
// القائمةُ الفارغةُ تُكتب (0) لا () — فـIN () خطأُ صياغةٍ يُسقط الشاشة
$dept_in = $dept_ids ? implode(',', $dept_ids) : '0';

// E-13 (UX-07 §5.1): **التبويباتُ الأربعة بدل الفلاتر المنسدلة وحدَها** —
// وتبويبُ «تنتظر اعتمادًا» (done) كان غائبًا؛ و«بلاغاتي» هو M-37 (UX-07 §4):
// المرشَّحُ على المستلم لكل الأدوار لا لدور 24 وحده.
$TABS = array(
    'open'     => array('مفتوحة', " AND t.stage NOT IN ('done','closed','cancelled')"),
    'approval' => array('تنتظر اعتمادا', " AND t.stage = 'done'"),
    'mine'     => array('بلاغاتي', ' AND (t.reporter_user_id = ' . intval($current_user_id)
                       . ' OR t.assigned_user_id = ' . intval($current_user_id)
                       . ' OR t.created_by = ' . intval($current_user_id) . ')'),
    'closed'   => array('مغلقة', " AND t.stage IN ('closed','cancelled')"),
);
// التبويبُ يظهر لمن له وحدةٌ تنظيميةٌ فقط — والمديرُ الأعلى يرى الكلَّ أصلًا
if ($dept_unit > 0) {
    $TABS['dept'] = array('موجهة لإدارتي', ' AND t.id IN (' . $dept_in . ')');
}

/* ══ «موجَّهة لإدارتي» لا تخضع لشجرةِ الأدوار ═══════════════════════════════
     قِيس حيًّا أنّ إخضاعَها يُفرغها: مساراتُ المخازنِ الستةَ عشرَ كلُّها على
     بلاغاتٍ **رأسُها مملوكٌ لإدارةِ الصيانة**، وأمينُ المستودعِ ليس في ذرّيّةِ
     الصيانة — فيُرشَّح كلُّ شيءٍ ويصير التبويبُ صفرًا. وهذا بعينُه العطبُ الذي
     جاء الدمجُ ليرفعه.
   ◆ والقاعدةُ التي تحسم: **توجيهُ المسارِ إلى وحدتِك هو الإذنُ**، لا ملكيةُ
     الرأس. وهو ما كانت تفعله `dept_inbox.php` (ترشِّح بـ`ws.org_unit_id` وحدَه).
   ◆ والعزلُ لا ينخرم: قائمةُ المعرِّفاتِ نفسُها خرجت من `scopedQuery` محكومةً
     بكيانِ المستخدم، و`{TENANT_SCOPE}` يبقى نافذًا على `tickets`. */
$tab_scope = function ($key) use ($scope_sql) {
    return $key === 'dept' ? '' : $scope_sql;
};
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
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف السطر' => 'g75',
            'نطاق العرض' => 'g76',
            'رقم البلاغ' => 'g77',
            'تاريخ التسجيل' => 'g78',
            'الفئة' => 'g79',
            'الأولوية' => 'g80',
            'محل البلاغ' => 'g81',
            'المكلف' => 'g82',
            'الكيان المنشأ في إدارتنا' => 'g83',
            'مهلة SLA' => 'g84',
            'المتبقي/التأخير' => 'g85',
            'مستوى التصعيد' => 'g86',
            'ينتظر تحققا؟' => 'g87',
            'حالة البلاغ' => 'g88',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('tkt_tickets_list');
        echo ems_w14_grid('emsList_tkt_tickets_list', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في صندوق بلاغات الإدارة'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا بلاغات في هذا التبويب', 'بدل التبويب أو أزل الفلاتر — أو افتح بلاغا بزر «بلاغ جديد»');
    ?>
    <style>
    /* UXW-01 ①②: أنماطُ قائمةِ البلاغاتِ الثابتة — بادئةُ الشاشة tkt-list- */
    /* ◆ **بطاقاتُ اللمحةِ صارت البطاقةَ الموحَّدة** — ولا يُكتب هنا لونٌ ولا
         حجمٌ ولا حدٌّ لها إطلاقًا: مصدرُ تصميمِها `assets/css/ems-statcards.css`
         وحدَه، ومَن كتب للبطاقةِ نمطًا في شاشتِه صار لها مصدران يتفرّقان.
         وقد كانت هنا إحدى عشرةَ قاعدةً تصف بطاقةً خاصّةً بالشاشة (شريطُ نغمةٍ
         علويٌّ · رقمٌ بـ20px · وصفٌ بـ12px) فرُفعت كلُّها.
       ◆ **والرقمُ في خانةِ الخطِّ الكبير قطعًا لا ترجيحًا**: يُصيَّر في
         `stats-value` — فينالها بالصنفِ من المركزيِّ (35px/900) قبل أن يُسأل
         عنها موسِمُ `ems-statcards.js`، الذي يرشِّح بالصنفِ أولًا كذلك. ولولا
         الصنفُ لسقط الترشيحُ إلى سقّاطةِ «أكبرِ خطٍّ باقٍ» فوقع الاختيارُ على
         نصٍّ آخر — وهو العطبُ نفسُه الذي أخرج نصَّ الفترةِ في مكانِ القيمةِ
         بـ`main/role_board.php`.
       ◆ **والعددُ ثلاثةٌ لا أربعة**: المركزيُّ يفرض أربعةَ أعمدةٍ بـ`!important`
         على محدِّدٍ وزنُه (0,5,1)، فيزيده محدِّدُ الشاشةِ صنفًا ليعلوَه (0,6,1)
         — وإلا بقي عمودٌ رابعٌ فارغٌ وظنَّ القارئُ أن السطرَ لم يُكتب.
         والانكساراتُ تُعاد هنا كاملةً لأن قاعدةَ الشاشةِ الأساسيةَ تعلو
         انكساراتِ المركزيِّ فتُبطلها لو تُركت. */
    body.ems-site .main .tkt-list-glance-grid.stats-grid:not(.dt-button):not(.btn-close) {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
    @media (max-width: 900px) {
        body.ems-site .main .tkt-list-glance-grid.stats-grid:not(.dt-button):not(.btn-close) {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
    }
    @media (max-width: 560px) {
        body.ems-site .main .tkt-list-glance-grid.stats-grid:not(.dt-button):not(.btn-close) {
            grid-template-columns: 1fr !important;
        }
    }
    .tkt-list-tabs        { display: flex; gap: 6px; flex-wrap: wrap; }
    .tkt-list-tab         { border: 1px solid var(--c-s-ddd); border-radius: 8px; padding: 6px 14px; text-decoration: none; }
    .tkt-list-tab.is-active { background: var(--c-e2b93b, #e2b93b); font-weight: 800; }
    .tkt-list-table       { width: 100%; }
    </style>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('ticket', 'نظرة عامة'); ?>

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
    // النغمةُ رُفعت من البطاقة: الموحَّدةُ بلا شريطِ لونٍ ولا أيقونةٍ ملوّنة،
    // فصنفُ النغمةِ كان يبقى مكتوبًا بلا أثرٍ بصريٍّ — وهو أسوأُ من غيابه.
    $glance_cards = array(
        array('مفتوحة الآن', (int)$g['open_cnt'], 'fa-folder-open'),
        array('متأخرة', (int)$g['late_cnt'], 'fa-triangle-exclamation'),
        array('حرجة للإنتاج', (int)$g['crit_cnt'], 'fa-bolt'),
    );
    ?>
    <div class="stats-section">
        <div class="stats-grid tkt-list-glance-grid">
            <?php foreach ($glance_cards as $c):
                /* عقدُ البطاقةِ الموحَّدةِ الثلاثيُّ كما في شاشتَي العملاءِ
                   والمشاريعِ (أصلُ التصميم): أيقونةٌ في حاويتِها ⇐ قيمةٌ ⇐ عنوان.
                   ◆ **والبطاقةُ نفسُها هي الوصلةُ لا غلافٌ حولها**: غلافٌ
                     يصير هو خليةَ الشبكةِ، فتقف البطاقةُ داخلَه بارتفاعِ
                     محتواها لا بارتفاعِ أختِها فتتفاوت البطاقاتُ في الصفِّ
                     الواحد. والقاعدةُ المركزيةُ تُلبس الوصلةَ ثوبَ البطاقةِ
                     أصلًا (`display:block` · `color:inherit` · بلا تسطير).
                   ◆ والرقمُ يُعرض للجميع — والوصلةُ لمن يملك منحةَ اللوحةِ وحدَه. */
                $inner = '<div class="stats-icon"><i class="fa ' . htmlspecialchars($c[2]) . '"></i></div>'
                       . '<div class="stats-value">' . (int)$c[1] . '</div>'
                       . '<div class="stats-title">' . htmlspecialchars($c[0]) . '</div>';
                echo $__tkt_can_dash
                    ? '<a class="stats-card" href="ticket_dashboard.php" title="افتح لوحة المتابعة">' . $inner . '</a>'
                    : '<div class="stats-card">' . $inner . '</div>';
            endforeach; ?>
        </div>
    </div>

    <div class="card"><div class="card-body tkt-list-tabs">
        <?php // E-13: التبويباتُ الأربعة — كلٌّ بعدّاده الحي
        foreach ($TABS as $tk => $tv):
            // نطاقُ الرؤية من مصدرٍ واحد ($scope_sql) لا مُعادًا بناؤه هنا —
            // ازدواجُ التعريف هو ما جعل الأرقام تتفارق أصلًا.
            $cnt_row = tkt_gate($is_super_admin)->scopedQuery(
                array('scope' => array('t' => 'tickets')),
                "SELECT COUNT(*) n FROM tickets t WHERE {TENANT_SCOPE}" . $tab_scope($tk) . $tv[1]);
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
                    <th>المبلغ</th><th>المعدة</th><th>المشروع</th><th>الوصف</th><th>تاريخ البلاغ</th><th>موعد الإنجاز</th><th>متأخر</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <!-- XF-01: وسع الاستعلام بثلاث وصلات معلنة في enrich (الفئة والمكلف
                         والموقع) واضيفت priority وconfidentiality وescalation_level.
                         و«الادارة المختصة» و«المسارات المتوازية» و«المتبقي» تبقى معلنة:
                         الاولى دور لا عمود اسم، والثانية مسارات في جدول اخر، والثالثة
                         محسوبة من المهلة لا مخزنة. -->
                    <th class="ems-fn-th" data-fn="1" data-fn-src="ticket_no">رقم البلاغ</th>
                    <th class="ems-fn-th" data-fn="1" data-fn-src="category">الفئة</th>
                    <th class="ems-fn-th" data-fn="1" data-fn-src="priority">الأولوية</th>
                    <th class="ems-fn-th" data-fn="1" data-fn-src="site">الموقع</th>
                    <th class="ems-fn-th" data-fn="1">الإدارة المختصة</th>
                    <th class="ems-fn-th" data-fn="1" data-fn-src="assignee">المكلف</th>
                    <th class="ems-fn-th" data-fn="1" data-fn-src="confidentiality">مستوى السرية</th>
                    <th class="ems-fn-th" data-fn="1">المسارات المتوازية</th>
                    <th class="ems-fn-th" data-fn="1" data-fn-src="due_at">المهلة</th>
                    <th class="ems-fn-th none" data-fn="1">المتبقي</th>
                    <th class="ems-fn-th none" data-fn="1" data-fn-src="escalation">التصعيد الحالي</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    </tr></thead>
                <tbody>
                <?php
                // scopedQuery: scope على tickets + إثراءات LEFT حصرًا (مراجع مرنة قد تغيب)
                $rows = tkt_gate($is_super_admin)->scopedQuery(
                    array('scope'  => array('t' => 'tickets'),
                          'enrich' => array('tt' => 'ticket_types', 'e' => 'equipments', 'p' => 'project',
                                            'tc' => 'ticket_categories', 'au' => 'users', 'st' => 'sites')),
                    "SELECT t.id, t.ticket_no, t.stage, t.ticket_nature, t.owner_role_id,
                            t.reporting_person, t.complaint, t.call_date, t.call_time,
                            t.resolution_due_at,
                            t.priority, t.confidentiality, t.escalation_level,
                            tt.name AS type_name, e.code AS equipment_code, p.name AS project_name,
                            tc.name AS category_name, au.name AS assignee_name, st.name AS site_name
                     FROM tickets t
                     LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
                     LEFT JOIN equipments e ON e.id = t.equipment_id
                     LEFT JOIN project p ON p.id = t.project_id
                     LEFT JOIN ticket_categories tc ON tc.id = t.category_id
                     LEFT JOIN users au ON au.id = t.assigned_user_id
                     LEFT JOIN sites st ON st.id = t.site_id
                     WHERE {TENANT_SCOPE}" . $tab_scope($tab) . $tab_sql . "
                     ORDER BY t.id DESC"
                );
                foreach ($rows as $row) {
                    $complaint_full  = (string)$row['complaint'];
                    $complaint_short = mb_strlen($complaint_full) > 60 ? (mb_substr($complaint_full, 0, 60) . '…') : $complaint_full;
                    echo "<tr data-xf=\"" . htmlspecialchars(json_encode(array(
                        'ticket_no'       => (string) ($row['ticket_no'] ?? ''),
                        'category'        => (string) ($row['category_name'] ?? ''),
                        'priority'        => (string) ($row['priority'] ?? ''),
                        'site'            => (string) ($row['site_name'] ?? ''),
                        'assignee'        => (string) ($row['assignee_name'] ?? ''),
                        'confidentiality' => (string) ($row['confidentiality'] ?? ''),
                        'due_at'          => (string) ($row['resolution_due_at'] ?? ''),
                        'escalation'      => (string) ($row['escalation_level'] ?? ''),
                    ), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . "\">";
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
