<?php
/* ── تحكم الروابط ─────────────────────────────────────────────────────────────
     شبكةُ شاشاتِ الدورِ بمربَّعَين: ظهورُ الرابطِ في **الوصولِ السريع** وظهورُه
     في **السايدبار**. والاسمُ والماهيّةُ وحدَهما — بلا مسارٍ ولا رابط.

   ◆ **وأين يقع كلُّ مربَّعٍ من المعماريّة** (مقيسٌ لا مُفترَض):
     ① الوصولُ السريعُ في لوحةِ التحكمِ يقرأ `nav_items.is_quick` لصفِّ الدور
        (‏`getUnifiedQuickItems`) — فالمربَّعُ الأولُ يكتب هذه الراية.
     ② والسايدبارُ الحيُّ يقرأ **مواضعَ مساحةِ الإدارةِ ∩ تفويضَ الدور**
        (`navarch_render` مع `EMS_NAV_ARCH=on`)، وطرفُه المملوكُ للدورِ هو
        `nav_items.active` — فالمربَّعُ الثاني يكتبها.
   ⛔ **ولا يُدَّعى ما لا يقع**: شاشةٌ لا موضعَ لها في مساحةِ الإدارةِ **لا
     يُظهرها تأشيرُ السايدبار** مهما رُفعت رايتُها — فتُفصَل في قسمٍ ثانٍ
     ومربَّعُها معطَّلٌ بسببٍ مكتوب، بدل مفتاحٍ يُوضَع ولا يقع أثرُه.

   ◆ **والمدى دورٌ لا مستخدم**: الرايتان على صفِّ الدور، فما يُغيَّر هنا يراه
     كلُّ من يحمل هذا الدور. وهو معلَنٌ في صدرِ الشاشةِ لا مستنتَج.
   ◆ **والظهورُ ليس صلاحيّةً** (NAV-ARCH-02 §36): رفعُ الرايةِ أو خفضُها لا
     يفتح بابًا ولا يغلقه — حارسُ الوجهةِ باقٍ كما هو. */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
// تُبلَغ هذه الشاشةُ أحيانًا وجلستُها نشطةٌ سلفًا (مسار مضمَّن) — فحارسُ الحالةِ
// يمنع تنبيه «الجلسة نشطة» دون أن يفتح جلسةً ثانية.
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../includes/permissions_helper.php';
include '../config.php';
require_once __DIR__ . '/../includes/security.php';

$perms = get_page_permissions($conn, 'Settings/links_control.php');
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية للوصول لهذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$lc_my_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$lc_is_super  = ($lc_my_role === '-1');
/* اختيارُ دورٍ غيرِ دورِك قرارُ حوكمةٍ لا تفضيلٌ شخصيّ — للسوبرِ ولدورِ
   الصلاحيّاتِ (15) وحدَهما. وسواهما يضبط دورَه هو. */
$lc_can_pick  = $lc_is_super || $lc_my_role === '15';
$lc_can_edit  = !empty($perms['can_edit']);

/* ── الدورُ المعروض ──────────────────────────────────────────────────────────
     ⛔ **من `$_GET` صراحةً لا من `$_REQUEST`**: الأخيرُ يُبنى مرّةً عند بدءِ
       الطلبِ من `variables_order`، فلا يعكس ما يُوضَع في `$_GET` بعدَه —
       ومقاييسُ التصييرِ تضع المعاملَ هكذا، فيقرأ الاختيارُ دورًا غيرَ المطلوب
       (مقيسٌ: طُلبت شبكةُ الدور 12 فصُيِّرت شبكةُ الدور 15). والفعلُ يقرأ
       `$_POST` وحدَه بالمثل. */
$lc_role = $lc_my_role;
if ($lc_can_pick && isset($_GET['role']) && $_GET['role'] !== '') {
    $lc_role = strval(intval($_GET['role']));
}
if ($lc_is_super && !isset($_GET['role'])) { $lc_role = ''; }

/* قائمةُ الأدوارِ للمختار — تُبنى مرّةً وتُستعمل في الفعلِ والعرض. */
$lc_roles = array();
if ($lc_can_pick) {
    $rs = $conn->query("SELECT r.id, r.name, COUNT(n.id) AS n
                          FROM roles r LEFT JOIN nav_items n ON n.role_id = r.id
                         GROUP BY r.id, r.name ORDER BY r.id");
    while ($rs && ($x = $rs->fetch_assoc())) { $lc_roles[] = $x; }
}

/* ── تسويةُ المسارِ: الصيغةُ الواحدةُ التي يُقارَن بها الموضعُ والصفُّ والوصف ── */
if (!function_exists('lc_norm')) {
    function lc_norm($s)
    {
        $s = preg_replace('~^(\.\./)+~', '', (string) $s);
        $s = preg_replace('~[?#].*$~', '', $s);
        return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   واجهةُ الفعل — تُجيب JSON وتنتهي قبل أيِّ إخراجِ HTML
   ═══════════════════════════════════════════════════════════════════════════ */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['lc_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $fail = function ($msg, $code = 400) {
        http_response_code($code);
        echo json_encode(array('ok' => false, 'msg' => $msg), JSON_UNESCAPED_UNICODE);
        exit();
    };

    /* ① الرمزُ أولًا — والفشلُ مغلقٌ لا مراقَب: هذه شاشةٌ جديدةٌ فلا تنتظر
         دورَها في قائمةِ الإنفاذِ المتدرّج (ADR-05 «قاعدةُ الصفحاتِ الجديدة»). */
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token']
         : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($tok)) {
        $fail('انتهت صلاحية الجلسة - أعد تحميل الصفحة ثم أعد المحاولة', 403);
    }

    /* ② إذنُ الكتابةِ على هذه الشاشةِ بعينِها */
    if (!$lc_can_edit) { $fail('لا تملك صلاحية التعديل في هذه الشاشة', 403); }

    /* ③ الدورُ المستهدَفُ ضمن ما يُسمح لك به */
    $tRole = isset($_POST['role']) ? strval(intval($_POST['role'])) : '';
    if ($tRole === '' || $tRole === '0') { $fail('دور غير محدد'); }
    if (!$lc_can_pick && $tRole !== $lc_my_role) {
        $fail('لا يمكنك ضبط روابط دور غير دورك', 403);
    }

    /* ④ الحقلُ والقيمةُ من مفرداتٍ مغلقة */
    $field = isset($_POST['field']) ? (string) $_POST['field'] : '';
    $col = ($field === 'quick') ? 'is_quick' : (($field === 'side') ? 'active' : '');
    if ($col === '') { $fail('حقل غير معروف'); }
    $val = (isset($_POST['value']) && (string) $_POST['value'] === '1') ? 1 : 0;

    /* ⑤ المفتاحُ مسارٌ مسوًّى — وصفوفُ الشاشةِ الواحدةِ تُضبط معًا (نسخُ
         المسارِ بلاحقةِ تبويبٍ صفوفٌ لشاشةٍ واحدةٍ في نظرِ المستخدم). */
    $key = isset($_POST['key']) ? lc_norm($_POST['key']) : '';
    if ($key === '') { $fail('شاشة غير محددة'); }

    $ids = array();
    $st = $conn->prepare("SELECT id, route FROM nav_items WHERE role_id = ?");
    $st->bind_param('i', $tRole);
    $st->execute();
    $rs = $st->get_result();
    while ($rs && ($x = $rs->fetch_assoc())) {
        if (lc_norm($x['route']) === $key) { $ids[] = (int) $x['id']; }
    }
    $st->close();
    if (!$ids) { $fail('هذه الشاشة ليست ضمن صفحات هذا الدور', 404); }

    $in = implode(',', array_map('intval', $ids));
    if (!$conn->query("UPDATE nav_items SET `{$col}` = {$val}, updated_at = NOW() WHERE id IN ({$in})")) {
        $fail('تعذر حفظ التغيير', 500);
    }

    /* ⑥ سجلُّ الحدثِ — تغييرُ ظهورٍ يُراجَع كما يُراجَع غيرُه */
    if (function_exists('log_security_event')) {
        log_security_event('NAV_VISIBILITY_SET',
            'role=' . $tRole . ' field=' . $field . ' value=' . $val . ' screens=' . count($ids));
    }

    $cnt = function ($c) use ($conn, $tRole) {
        $r = $conn->query("SELECT COUNT(*) FROM nav_items WHERE role_id = " . intval($tRole) . " AND `{$c}` = 1");
        return $r ? (int) $r->fetch_row()[0] : 0;
    };
    echo json_encode(array('ok' => true, 'value' => $val, 'rows' => count($ids),
        'counts' => array('quick' => $cnt('is_quick'), 'side' => $cnt('active'))), JSON_UNESCAPED_UNICODE);
    exit();
}

/* ═══════════════════════════════════════════════════════════════════════════
   جمعُ بيانات الشبكة
   ═══════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../includes/unified_nav.php';
$lc_doors = function_exists('unifiedNavDoors') ? unifiedNavDoors() : array();

$lc_role_name = '';
if ($lc_role !== '' && $lc_role !== '-1') {
    $st = $conn->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
    $rid = intval($lc_role);
    $st->bind_param('i', $rid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $lc_role_name = $row ? (string) $row['name'] : ('دور ' . $lc_role);
} elseif ($lc_role === '-1') {
    $lc_role_name = 'الإدارة التقنية العليا';
}

/* ① صفوفُ الدور */
$lc_rows = array();
if ($lc_role !== '') {
    $st = $conn->prepare(
        "SELECT n.id, n.route, n.label_ar, n.icon, n.door, n.sort_order, n.active, n.is_quick,
                m.name AS module_name
           FROM nav_items n
           LEFT JOIN modules m ON m.id = n.module_id
          WHERE n.role_id = ?
          ORDER BY n.door, n.sort_order, n.id");
    $rid = intval($lc_role);
    $st->bind_param('i', $rid);
    $st->execute();
    $rs = $st->get_result();
    while ($rs && ($x = $rs->fetch_assoc())) { $lc_rows[] = $x; }
    $st->close();
}

/* ② مواضعُ مساحةِ الدورِ النشطةُ — الطرفُ الثاني من معادلةِ السايدبار.
      ولا تُحسب إلّا الأنواعُ التي تدخل السايدبارَ فعلًا (§9 · §12-هـ). */
$lc_ws = null; $lc_ws_name = ''; $lc_placed = array(); $lc_place_label = array();
if ($lc_role !== '' && $lc_role !== '-1') {
    $rid = intval($lc_role);
    $q = $conn->query("SELECT w.workspace_id, w.name_ar
                         FROM nav_ws_roles wr
                         JOIN nav_workspaces w ON w.workspace_id = wr.workspace_id AND w.active = 1
                        WHERE wr.role_id = {$rid} AND wr.binding IN ('PRIMARY','SECONDARY')
                        ORDER BY (wr.binding = 'PRIMARY') DESC, wr.workspace_id ASC LIMIT 1");
    if ($q && ($x = $q->fetch_assoc())) { $lc_ws = (string) $x['workspace_id']; $lc_ws_name = (string) $x['name_ar']; }
    if ($lc_ws !== null) {
        $esc = $conn->real_escape_string($lc_ws);
        $q = $conn->query("SELECT route, canonical_label, placement_type, approved_by
                             FROM nav_workspace_placements
                            WHERE workspace_id = '{$esc}' AND status = 'ACTIVE'
                              AND (effective_to IS NULL OR effective_to >= CURDATE())");
        while ($q && ($x = $q->fetch_assoc())) {
            $t = (string) $x['placement_type'];
            $sidebarType = in_array($t, array('PRIMARY', 'GLOBAL_SHELL', 'PERSONAL'), true)
                || ($t === 'SECONDARY_APPROVED' && trim((string) $x['approved_by']) !== '');
            if (!$sidebarType) { continue; }
            $k = lc_norm($x['route']);
            $lc_placed[$k] = true;
            if (trim((string) $x['canonical_label']) !== '') { $lc_place_label[$k] = trim((string) $x['canonical_label']); }
        }
    }
}

/* ③ الماهيّةُ باختصار — من سجلِّ «عن الشاشة» ثمَّ الدستورِ ثمَّ البابِ.
      ⛔ ولا تُخترع جملةٌ لا مصدرَ لها: الترتيبُ مصادرُ لا تخمين. */
$lc_about = array();
$q = $conn->query("SELECT screen_path, description FROM screen_about WHERE active = 1");
while ($q && ($x = $q->fetch_assoc())) { $lc_about[lc_norm($x['screen_path'])] = (string) $x['description']; }
$lc_canon = array();
$q = $conn->query("SELECT route, group_name FROM nav_canonical
                    WHERE route IS NOT NULL AND route <> '' AND group_name IS NOT NULL AND group_name <> ''");
while ($q && ($x = $q->fetch_assoc())) { $lc_canon[lc_norm($x['route'])] = (string) $x['group_name']; }

if (!function_exists('lc_short_desc')) {
    /** أوّلُ جملةٍ ذاتِ معنًى بعد نزعِ صدرِ «شاشة «س»» المكرَّر. */
    function lc_short_desc($raw)
    {
        $t = trim(preg_replace('~\s+~u', ' ', (string) $raw));
        if ($t === '') { return ''; }
        /* الضمّةُ بمهرَبِها الرقميِّ لا بحرفِها: الدلالةُ واحدةٌ، والماسحُ لا يعُدُّ
           تشكيلًا في نصِّ واجهةٍ ما ليس حرفَ تشكيلٍ في المصدر (‏`UI-01`). */
        $t = preg_replace('~^شاشة\x{064F}?\s*«[^»]*»\s*(—|-|–)?\s*~u', '', $t);
        $t = ltrim($t, ". \t");
        if (preg_match('~^(.{15,170}?[\.\!\؟])(\s|$)~u', $t, $m)) { $t = $m[1]; }
        if (mb_strlen($t) > 170) { $t = mb_substr($t, 0, 167) . '…'; }
        return trim($t);
    }
}

/* ④ الشاشةُ الواحدةُ بطاقةٌ واحدة: تُجمَع صفوفُ المسارِ المسوَّى معًا */
$lc_cards = array();
foreach ($lc_rows as $r) {
    $k = lc_norm($r['route']);
    if ($k === '') { continue; }
    if (!isset($lc_cards[$k])) {
        $desc = lc_short_desc(isset($lc_about[$k]) ? $lc_about[$k] : '');
        if ($desc === '' && isset($lc_canon[$k])) { $desc = 'ضمن «' . $lc_canon[$k] . '» من مسار العمل.'; }
        if ($desc === '') {
            $dn = isset($lc_doors[$r['door']]) ? $lc_doors[$r['door']]['name'] : 'العمل اليومي';
            $desc = 'شاشة ضمن باب «' . $dn . '».';
        }
        $lc_cards[$k] = array(
            'key'    => $k,
            'label'  => isset($lc_place_label[$k]) ? $lc_place_label[$k] : trim((string) $r['label_ar']),
            'desc'   => $desc,
            /* الأيقونةُ من خريطةِ الملاحةِ نفسِها التي يستعملها السايدبار —
               فالبطاقةُ تُعرَف بالأيقونةِ التي يعرفها المستخدمُ لها هناك،
               وصفُّ `nav_items` كثيرًا ما يحمل `circle-dot` عامّةً. */
            'icon'   => function_exists('ems_nav_icon_for')
                ? ems_nav_icon_for((string) $r['label_ar'], (string) $r['route'])
                : (trim((string) $r['icon']) !== '' ? trim((string) $r['icon']) : 'fa fa-link'),
            'door'   => (string) $r['door'],
            'quick'  => 0,
            'side'   => 0,
            'placed' => isset($lc_placed[$k]),
            'rows'   => 0,
        );
    }
    /* رايةٌ مرفوعةٌ في أيِّ صفٍّ من صفوفِ الشاشةِ = الشاشةُ ظاهرة */
    if ((int) $r['is_quick'] === 1) { $lc_cards[$k]['quick'] = 1; }
    if ((int) $r['active'] === 1)   { $lc_cards[$k]['side'] = 1; }
    $lc_cards[$k]['rows']++;
}
$lc_cards = array_values($lc_cards);

/* ⑤ العدُّ — من البطاقاتِ المعروضةِ نفسِها لا من استعلامٍ ثانٍ يتفرَّق عنها */
$lc_n_total = count($lc_cards);
$lc_n_quick = 0; $lc_n_side = 0; $lc_n_placed = 0;
foreach ($lc_cards as $c) {
    if ($c['quick']) { $lc_n_quick++; }
    if ($c['placed']) {
        $lc_n_placed++;
        if ($c['side']) { $lc_n_side++; }
    }
}

$page_title = "تحكم الروابط";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($perms);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

$E = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>

<style>
 .lc-shell { display: grid; gap: 16px; }

 /* ── صدر السياق ── */
 .lc-context {
 background: var(--s1);
 border: 1.5px solid var(--bdr);
 border-radius: var(--rl, 14px);
 box-shadow: var(--sh);
 padding: 16px 20px;
 display: flex;
 flex-wrap: wrap;
 align-items: center;
 gap: 12px;
 }

 .lc-role-box { display: flex; align-items: center; gap: 8px; flex: 1 1 260px; min-width: 240px; }

 .lc-role-icon {
 width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0;
 display: inline-flex; align-items: center; justify-content: center;
 background: var(--c-rgba24419766018); color: var(--c-s-b8860b); font-size: 1rem;
 }

 .lc-role-meta { display: grid; gap: 4px; min-width: 0; }
 .lc-role-meta .lc-kicker { font-size: .72rem; font-weight: 800; color: var(--t3); }
 .lc-role-meta .lc-name { font-size: 1.02rem; font-weight: 900; color: var(--t1); }

 .lc-counts { display: flex; flex-wrap: wrap; gap: 8px; margin-inline-start: auto; }

 .lc-chip {
 display: inline-flex; align-items: center; gap: 8px;
 padding: 8px 12px; border-radius: 999px;
 background: var(--gray-100);
 border: 1px solid var(--bdr);
 font-size: .78rem; font-weight: 800; color: var(--t1);
 white-space: nowrap;
 }

 .lc-chip b { font-size: .92rem; font-variant-numeric: tabular-nums; }
 .lc-chip i { color: var(--c-s-b8860b); }
 .lc-chip.is-side i { color: var(--c-2f6f4f); }

 .lc-role-picker { display: flex; align-items: center; gap: 8px; }
 .lc-role-picker select {
 min-width: 230px; padding: 8px 12px; border-radius: 10px;
 border: 1.5px solid var(--bdr); background: var(--s1);
 font-family: inherit; font-size: .85rem; font-weight: 700; color: var(--t1);
 }

 /* ── تنبيه المدى ── */
 .lc-scope-note {
 display: flex; align-items: flex-start; gap: 12px;
 padding: 12px 16px; border-radius: 12px;
 background: var(--c-rgba24419766012);
 border: 1px solid var(--c-rgba24419766045);
 font-size: .82rem; font-weight: 700; line-height: 1.8; color: var(--t1);
 }

 .lc-scope-note i { color: var(--c-s-b8860b); margin-top: 4px; }

 .lc-scope-note.is-readonly { background: var(--gray-100); border-color: var(--bdr); }
 .lc-scope-note.is-readonly i { color: var(--t3); }

 /* ── شريط الأدوات ── */
 .lc-toolbar {
 background: var(--s1);
 border: 1.5px solid var(--bdr);
 border-radius: var(--rl, 14px);
 box-shadow: var(--sh);
 padding: 12px 16px;
 display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
 }

 .lc-search { position: relative; flex: 1 1 260px; min-width: 220px; }
 .lc-search i { position: absolute; inset-inline-start: 12px; top: 50%; transform: translateY(-50%); color: var(--t3); font-size: .82rem; }
 /* النص والإرشاد في **منتصف الحقل**: والحشو متساو الجانبين لأن
 `text-align:center` يتوسط صندوق المحتوى لا الحقل - فحشو 34px من
 جهة الأيقونة و12px من الأخرى يزيح المنتصف عن منتصفه. */
 .lc-search input {
 width: 100%; padding: 8px 36px; text-align: center;
 border-radius: 10px; border: 1.5px solid var(--bdr);
 background: var(--s1); font-family: inherit; font-size: .85rem; font-weight: 700; color: var(--t1);
 }

 .lc-search input::placeholder { text-align: center; }

 /* **وحشو الجانبين يحتاج هذه الخصوصية بعينها**: قاعدة الحقول
 الموحدة تفرض `padding: 8px 12px !important` على كل حقل داخل
 `.main` - فتغلب حشونا (مقيس: 12px محسوبة). والمطلوب منها هنا شيء
 واحد: مساحة متساوية تحفظ الأيقونة من نص طويل يتمدد من المنتصف.
 وما عداها يبقى للنظام الموحد كما هو. */
 body.ems-site .main .lc-search input { padding-inline: 32px !important; }
 .lc-search input:focus { outline: none; border-color: var(--c-rgba24419766075); box-shadow: 0 0 0 3px var(--c-rgba24419766018); }

 .lc-filters { display: flex; flex-wrap: wrap; gap: 8px; }

 .lc-fchip {
 padding: 8px 12px; border-radius: 999px; cursor: pointer;
 border: 1.5px solid var(--bdr); background: var(--s1);
 font-family: inherit; font-size: .76rem; font-weight: 800; color: var(--t3);
 transition: all .18s ease;
 }
 .lc-fchip:hover { border-color: var(--c-rgba2441976606); color: var(--t1); }
 .lc-fchip.is-on { background: var(--c-rgba24419766018); border-color: var(--c-2441976607); color: var(--c-7a5b06); }

 /* ── القسم ── */
 .lc-section { display: grid; gap: 12px; }

 .lc-section-head { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
 .lc-section-head h3 { margin: 0; font-size: 1rem; font-weight: 900; color: var(--t1); }
 .lc-section-head p { margin: 0; font-size: .78rem; font-weight: 700; color: var(--t3); }

 .lc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 12px; }

 /* ── البطاقة ── */
 .lc-card {
 background: var(--s1);
 border: 1.5px solid var(--bdr);
 border-radius: 13px;
 padding: 16px;
 display: grid; gap: 12px;
 transition: border-color .18s ease, box-shadow .18s ease;
 }

 .lc-card:hover { border-color: var(--c-rgba24419766055); box-shadow: var(--sh2); }
 .lc-card.is-busy { opacity: .55; pointer-events: none; }

 /* `display:grid` يغلب `[hidden]` من ورقة المتصفح - فبلا هذا السطر
 يبقى المرشح خارج البحث ظاهرا، والترشيح يكتب ولا يقع أثره. */
 .lc-card[hidden], .lc-section[hidden], .lc-empty[hidden] { display: none !important; }

 .lc-card-head { display: flex; align-items: flex-start; gap: 12px; }

 .lc-card-icon {
 width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
 display: inline-flex; align-items: center; justify-content: center;
 background: var(--c-rgba24419766016); color: var(--c-s-b8860b); font-size: .9rem;
 }

 .lc-card-meta { min-width: 0; display: grid; gap: 4px; }
 .lc-card-meta h4 { margin: 0; font-size: .92rem; font-weight: 800; color: var(--t1); line-height: 1.5; }
 .lc-card-meta p { margin: 0; font-size: .77rem; font-weight: 600; color: var(--t3); line-height: 1.75; }

 .lc-switches { display: grid; gap: 8px; border-top: 1px dashed var(--bdr); padding-top: 12px; }

 .lc-switch {
 display: flex; align-items: center; gap: 8px; cursor: pointer;
 padding: 8px 8px; border-radius: 9px; transition: background .15s ease;
 font-size: .8rem; font-weight: 700; color: var(--t1);
 }
 .lc-switch:hover { background: var(--gray-100); }
 .lc-switch input { width: 1.05rem; height: 1.05rem; accent-color: var(--c-d9ab32); cursor: pointer; flex-shrink: 0; }
 .lc-switch i { color: var(--t3); width: 15px; text-align: center; font-size: .78rem; }
 .lc-switch input:checked ~ i { color: var(--c-s-b8860b); }
 .lc-switch.is-locked { cursor: not-allowed; color: var(--t3); }
 .lc-switch.is-locked:hover { background: transparent; }
 .lc-switch.is-locked input { cursor: not-allowed; }

 .lc-lock-why {
 font-size: .72rem; font-weight: 700; line-height: 1.7;
 color: var(--t3); padding: 0 8px;
 }

 .lc-empty {
 background: var(--s1); border: 1.5px dashed var(--bdr);
 border-radius: var(--rl, 14px); padding: 36px 20px; text-align: center;
 display: grid; gap: 8px; justify-items: center;
 }
 .lc-empty i { font-size: 1.7rem; color: var(--t3); }
 .lc-empty h4 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--t1); }
 .lc-empty p { margin: 0; font-size: .82rem; font-weight: 700; color: var(--t3); max-width: 520px; line-height: 1.8; }

 @media (max-width: 768px) {
 .lc-toolbar { position: static; }
 .lc-counts { margin-inline-start: 0; width: 100%; }
 .lc-grid { grid-template-columns: 1fr; }
 }
</style>

<div class="main ems-unified-page-shell">

 <?php
    $header_title   = 'تحكم الروابط';
    $header_icon    = 'fas fa-link';
    $header_actions = array();
    $header_back    = array('href' => 'settings.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'العودة للإعدادات');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا شاشات مسجلة لهذا الدور', 'اختر دورا آخر أو راجع مدير الصلاحيات');
    ?>

 <div class="lc-shell">

 <!-- صدر السياق: الدور والعدادات -->
 <div class="lc-context">
 <div class="lc-role-box">
 <span class="lc-role-icon"><i class="fas fa-user-shield"></i></span>
 <span class="lc-role-meta">
 <span class="lc-kicker">روابط الدور</span>
 <span class="lc-name"><?php echo $E($lc_role_name !== '' ? $lc_role_name : 'لم يحدد دور'); ?></span>
                </span>
            </div>

            <?php if ($lc_can_pick): ?>
 <form method="get" class="lc-role-picker">
 <label for="lcRole" class="lc-kicker lc-col-kicker">تغيير الدور</label>
 <select id="lcRole" name="role" onchange="this.form.submit()">
 <option value="">- اختر دورا -</option>
 <?php foreach ($lc_roles as $r): ?>
                    <option value="<?php echo (int) $r['id']; ?>" <?php echo ((string) $r['id'] === $lc_role) ? 'selected' : ''; ?>>
                        <?php echo $E($r['name']); ?> (<?php echo (int) $r['n']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>

 <div class="lc-counts">
 <span class="lc-chip"><i class="fas fa-table-cells-large"></i> الشاشات <b id="lcTotal"><?php echo $lc_n_total; ?></b></span>
 <span class="lc-chip"><i class="fas fa-bolt"></i> الوصول السريع <b id="lcQuick"><?php echo $lc_n_quick; ?></b></span>
 <span class="lc-chip is-side"><i class="fas fa-bars"></i> السايدبار <b id="lcSide"><?php echo $lc_n_side; ?></b> من <?php echo $lc_n_placed; ?></span>
            </div>
        </div>

        <?php if ($lc_can_edit): ?>
 <div class="lc-scope-note">
 <i class="fas fa-circle-info"></i>
 <span>الإعداد هنا <b>يخص الدور بأكمله</b> - ما تضعه أو تزيله يظهر لكل مستخدم يحمل هذا الدور.
 وهو ضبط <b>ظهور لا صلاحية</b>: لا يفتح شاشة ولا يغلقها، والحارس على وجهة الرابط كما هو.
 والحفظ فوري عند وضع علامة أو إزالتها.</span>
 </div>
 <?php else: ?>
 <div class="lc-scope-note is-readonly">
 <i class="fas fa-eye"></i>
 <span>لديك صلاحية <b>الاطلاع فقط</b> على هذه الشاشة - المربعات معروضة كما هي ولا تحفظ تغييراتها.
 اطلب منحة التعديل من مدير الصلاحيات إن كانت ضمن عملك.</span>
 </div>
 <?php endif; ?>

        <?php if ($lc_n_total === 0): ?>
        <div class="lc-empty">
            <i class="fas fa-inbox"></i>
            <h4><?php echo $lc_role === '' ? 'اختر دورا للبدء' : 'لا شاشات مسجلة لهذا الدور'; ?></h4>
            <p><?php echo $lc_role === ''
                ? 'اختر دورا من القائمة أعلاه لعرض شاشاته والتحكم في ظهور روابطها.'
                : 'هذا الدور بلا سجل ملاحة - لا شاشات يمكن ضبط ظهورها. راجع مدير الصلاحيات لتسجيل شاشات الدور.'; ?></p>
        </div>
        <?php else: ?>

 <!-- شريط الأدوات: بحث وترشيح -->
 <div class="lc-toolbar">
 <div class="lc-search">
 <i class="fas fa-magnifying-glass"></i>
 <input type="search" id="lcSearch" placeholder="ابحث باسم الشاشة أو بوصفها…" autocomplete="off">
 </div>
 <div class="lc-filters">
 <button type="button" class="lc-fchip is-on" data-filter="all">الكل</button>
 <button type="button" class="lc-fchip" data-filter="quick"><i class="fas fa-bolt"></i> في الوصول السريع</button>
 <button type="button" class="lc-fchip" data-filter="side"><i class="fas fa-bars"></i> في السايدبار</button>
 <button type="button" class="lc-fchip" data-filter="off">غير ظاهرة</button>
 </div>
 </div>

 <?php
        /* البطاقاتُ قسمان: ما تحمله مساحةُ الإدارةِ (المربَّعان نافذان)،
           وما هو خارجَها (الوصولُ السريعُ نافذٌ والسايدبارُ لا يملكه هذا السجلّ). */
        $lc_in  = array_values(array_filter($lc_cards, function ($c) { return $c['placed']; }));
        $lc_out = array_values(array_filter($lc_cards, function ($c) { return !$c['placed']; }));

        $lc_render = function (array $cards) use ($E, $lc_can_edit, $lc_role, $lc_doors) {
            foreach ($cards as $c) {
                $doorName = isset($lc_doors[$c['door']]) ? $lc_doors[$c['door']]['name'] : '';
                ?>
                <div class="lc-card" data-key="<?php echo $E($c['key']); ?>"
                     data-quick="<?php echo (int) $c['quick']; ?>"
                     data-side="<?php echo (int) $c['side']; ?>"
                     data-placed="<?php echo (int) $c['placed']; ?>"
                     data-text="<?php echo $E(mb_strtolower($c['label'] . ' ' . $c['desc'] . ' ' . $doorName, 'UTF-8')); ?>">
                    <div class="lc-card-head">
                        <span class="lc-card-icon"><i class="<?php echo $E($c['icon']); ?>"></i></span>
                        <span class="lc-card-meta">
                            <h4><?php echo $E($c['label']); ?></h4>
                            <p><?php echo $E($c['desc']); ?></p>
                        </span>
                    </div>
                    <div class="lc-switches">
                        <label class="lc-switch">
                            <input type="checkbox" data-field="quick" data-role="<?php echo $E($lc_role); ?>"
                                   <?php echo $c['quick'] ? 'checked' : ''; ?>
                                   <?php echo $lc_can_edit ? '' : 'disabled'; ?>>
 <i class="fas fa-bolt"></i>
 <span>يظهر في الوصول السريع</span>
 </label>
 <label class="lc-switch <?php echo $c['placed'] ? '' : 'is-locked'; ?>">
                            <input type="checkbox" data-field="side" data-role="<?php echo $E($lc_role); ?>"
                                   <?php echo ($c['placed'] && $c['side']) ? 'checked' : ''; ?>
                                   <?php echo ($lc_can_edit && $c['placed']) ? '' : 'disabled'; ?>>
 <i class="fas fa-bars"></i>
 <span>يظهر في السايدبار</span>
 </label>
 <?php if (!$c['placed']): ?>
 <span class="lc-lock-why">لا موضع لهذه الشاشة في مخطط مساحة الإدارة، فلا يظهرها السايدبار مهما وضعت العلامة - وتبقى متاحة في الوصول السريع.</span>
 <?php endif; ?>
                    </div>
                </div>
                <?php
            }
        };
        ?>

        <?php if (!empty($lc_in)): ?>
 <div class="lc-section" data-section="in">
 <div class="lc-section-head">
 <h3>شاشات مساحة <?php echo $E($lc_ws_name !== '' ? $lc_ws_name : 'الإدارة'); ?></h3>
 <p>المربعان نافذان - <?php echo count($lc_in); ?> شاشة.</p>
 </div>
 <div class="lc-grid"><?php $lc_render($lc_in); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($lc_out)): ?>
 <div class="lc-section" data-section="out">
 <div class="lc-section-head">
 <h3>شاشات أخرى متاحة للدور</h3>
 <p>خارج مخطط مساحة الإدارة - الوصول السريع وحده نافذ فيها (<?php echo count($lc_out); ?> شاشة).</p>
 </div>
 <div class="lc-grid"><?php $lc_render($lc_out); ?></div>
        </div>
        <?php endif; ?>

 <div class="lc-empty" id="lcNoMatch" hidden>
 <i class="fas fa-magnifying-glass"></i>
 <h4>لا شاشة تطابق البحث</h4>
 <p>غير كلمة البحث أو أعد الترشيح إلى «الكل».</p>
 </div>

 <?php endif; ?>
    </div>
</div>

<script>
(function () {
    'use strict';

    var CSRF = <?php echo json_encode(function_exists('generate_csrf_token') ? generate_csrf_token() : '', JSON_UNESCAPED_UNICODE); ?>;
    var CAN_EDIT = <?php echo $lc_can_edit ? 'true' : 'false'; ?>;

 function toast(kind, text) {
 if (window.EmsAlert && window.EmsAlert[kind]) { window.EmsAlert[kind](text); }
 }

 /* ── ① الحفظ الفوري: الواجهة تتقدم والخادم يحسم ────────────────────
 وإن رد الخادم خطأ **رد المربع إلى ما كان** - فمربع يبقى
 مؤشرا بعد فشل الحفظ يكذب على صاحبه. */
 function save(box) {
 var card = box.closest('.lc-card');
 var field = box.getAttribute('data-field');
 var role = box.getAttribute('data-role');
 var key = card.getAttribute('data-key');
 var want = box.checked ? '1' : '0';
 var was = want === '1' ? '0' : '1';

 card.classList.add('is-busy');
 var body = new URLSearchParams();
 body.set('lc_action', 'toggle');
 body.set('role', role);
 body.set('key', key);
 body.set('field', field);
 body.set('value', want);
 body.set('csrf_token', CSRF);

 fetch(window.location.pathname, {
 method: 'POST',
 headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
 body: body,
 credentials: 'same-origin'
 }).then(function (r) {
 return r.json().catch(function () { return { ok: false, msg: 'رد غير مفهوم من الخادم' }; });
 }).then(function (j) {
 card.classList.remove('is-busy');
 if (!j || !j.ok) {
 box.checked = (was === '1');
 toast('error', (j && j.msg) ? j.msg : 'تعذر حفظ التغيير');
 return;
 }
 card.setAttribute('data-' + field, want);
 if (j.counts) {
 var q = document.getElementById('lcQuick');
 if (q) { q.textContent = j.counts.quick; }
 }
 recountSide();
 applyFilter();
 toast('success', want === '1' ? 'تم إظهار الرابط' : 'تم إخفاء الرابط');
 }).catch(function () {
 card.classList.remove('is-busy');
 box.checked = (was === '1');
 toast('error', 'تعذر الاتصال بالخادم');
 });
 }

 /* عداد السايدبار يحسب من البطاقات ذات الموضع وحدها - فهي وحدها
 التي يظهر فيها الأثر. */
 function recountSide() {
 var n = 0;
 document.querySelectorAll('.lc-card[data-placed="1"][data-side="1"]').forEach(function () { n++; });
 var el = document.getElementById('lcSide');
 if (el) { el.textContent = n; }
 }

 if (CAN_EDIT) {
 document.querySelectorAll('.lc-card input[type="checkbox"]').forEach(function (box) {
 box.addEventListener('change', function () { save(box); });
 });
 }

 /* ── ② البحث والترشيح - في المتصفح بلا طلب للشبكة ─────────────────── */
 var searchEl = document.getElementById('lcSearch');
 var mode = 'all';

 function applyFilter() {
 var q = searchEl ? searchEl.value.trim().toLowerCase() : '';
 var shown = 0;
 document.querySelectorAll('.lc-card').forEach(function (card) {
 var okText = !q || (card.getAttribute('data-text') || '').indexOf(q) !== -1;
 var okMode = true;
 if (mode === 'quick') { okMode = card.getAttribute('data-quick') === '1'; }
 else if (mode === 'side') { okMode = card.getAttribute('data-side') === '1' && card.getAttribute('data-placed') === '1'; }
 else if (mode === 'off') {
 okMode = card.getAttribute('data-quick') !== '1'
 && !(card.getAttribute('data-side') === '1' && card.getAttribute('data-placed') === '1');
 }
 var vis = okText && okMode;
 card.hidden = !vis;
 if (vis) { shown++; }
 });
 /* قسم خلا من بطاقة ظاهرة يطوى برأسه - لا رأس فوق فراغ. */
 document.querySelectorAll('.lc-section').forEach(function (sec) {
 var any = sec.querySelector('.lc-card:not([hidden])');
 sec.hidden = !any;
 });
 var none = document.getElementById('lcNoMatch');
 if (none) { none.hidden = shown !== 0; }
 }

 if (searchEl) { searchEl.addEventListener('input', applyFilter); }
 document.querySelectorAll('.lc-fchip').forEach(function (chip) {
 chip.addEventListener('click', function () {
 document.querySelectorAll('.lc-fchip').forEach(function (c) { c.classList.remove('is-on'); });
 chip.classList.add('is-on');
 mode = chip.getAttribute('data-filter');
 applyFilter();
 });
 });
})();
</script>

</body>

</html>
