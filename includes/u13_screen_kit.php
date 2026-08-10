<?php
require_once __DIR__ . '/../includes/catch_log.php';
/**
 * عُدّةُ شاشاتِ update0013 — u13_screen_kit
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لماذا عُدّةٌ لا نسخٌ لكلِّ شاشة:
 *   الحزمةُ تطلب عشراتِ الشاشاتِ تشترك في العقدِ نفسِه: حارسُ صلاحيةٍ · غلافٌ
 *   حاكمٌ CM-00 · منتقي منظرٍ CM-09 · زرُّ «إظهار كلِّ الأعمدة» CM-10 · أعمدةُ
 *   حوكمةٍ موسومة · حالةُ فراغٍ · RTL. ونسخُ ذلك أربعينَ مرةً يعني أربعينَ نسخةً
 *   من كلِّ عيبٍ يُكتشف لاحقًا. فالعقدُ في مكانٍ واحدٍ والشاشةُ تصريحٌ فوقَه.
 *
 * ◆ **تُضمَّن في النطاقِ العام لا داخلَ دالة**:
 *   گوتشا كُشفت حيًّا: `require 'config.php'` **داخلَ دالةٍ** يجعل `$conn` وكلَّ
 *   متغيّراتِ الملفِّ محليةً لتلك الدالة، فيرى `check_page_permissions()` اتصالًا
 *   `null` وتسقط الشاشةُ بـ500. والقشرةُ (`insidebar.php`) تعتمد سبعةَ عشرَ
 *   متغيّرًا عامًّا. فالعُدّةُ **قالبٌ يُضمَّن** لا دالةٌ تُستدعى — تمامًا كما
 *   تفعل الشاشاتُ المكتوبةُ باليد.
 *
 *   الاستعمالُ في ملفِّ الشاشة:
 *     <?php
 *     $U13 = array('file' => 'Finance/ob_register.php', ...);
 *     require __DIR__ . '/../includes/u13_screen_kit.php';
 *
 * ◆ الحكمُ الذي تُنفذه العُدّةُ بالبناءِ لا بالفحص — OBL-0052:
 *   «كلُّ حقلٍ يُوسَم بصنفٍ … **والحقلُ بلا صنفٍ لا يُدرَج في شاشةٍ حاكمة**.»
 *   الأعمدةُ تُقرأ من `gov_field_class` — فالحقلُ غيرُ المصنَّفِ **لا يُصيَّر
 *   أصلًا**. لا لأن فاحصًا منعه، بل لأنه لا سبيلَ له إلى الصفحة.
 */

if (!function_exists('u13_hidden_cols')) {

/** الحقولُ التقنيةُ التي لا تُعرض ولو صُنِّفت. */
function u13_hidden_cols()
{
    return array('id', 'company_id', 'created_by', 'updated_by', 'is_deleted',
                 'deleted_at', 'deleted_by', 'doc_ref', 'sort_order', 'steps_json', 'dims_json');
}

/**
 * أعمدةُ الشاشةِ من عقدِ التصنيف — مرتَّبةً بترتيبِ الجدولِ الحقيقي.
 * @return array<string,array{label:string,dc:string,sensitive:int}>
 */
function u13_columns(\mysqli $conn, $screenCode, $table)
{
    $cls = array();
    $st = $conn->prepare("SELECT field_key, label_ar, dc_code, is_sensitive
                            FROM gov_field_class WHERE screen_code = ? AND active = 1");
    if ($st) {
        $st->bind_param('s', $screenCode);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) { $cls[$r['field_key']] = $r; }
        $st->close();
    }
    /* الترتيبُ ترتيبُ الجدولِ — فالمراجعُ يقرأ الصفَّ كما بُني لا كما صُنِّف. */
    $out = array();
    $hidden = u13_hidden_cols();
    $q = $conn->prepare("SELECT COLUMN_NAME c FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
    if ($q) {
        $q->bind_param('s', $table);
        $q->execute();
        $rs = $q->get_result();
        while ($x = $rs->fetch_assoc()) {
            $c = $x['c'];
            if (in_array($c, $hidden, true)) { continue; }
            if (!isset($cls[$c])) { continue; }        // ◆ بلا صنفٍ = لا يُصيَّر
            $out[$c] = array('label' => $cls[$c]['label_ar'] !== '' ? $cls[$c]['label_ar'] : $c,
                             'dc' => $cls[$c]['dc_code'], 'sensitive' => (int) $cls[$c]['is_sensitive']);
        }
        $q->close();
    }
    return $out;
}

/** المناظرُ (CM-09) مشتقةً من الصنفِ — لا قائمةً تُكتب لكلِّ شاشة. */
function u13_views(array $cols)
{
    $v = array(
        'DC-1' => array('key' => 'oper',   'label' => 'التشغيل والتخصيص',  'cols' => array()),
        'DC-2' => array('key' => 'fin',    'label' => 'الأثر المالي',        'cols' => array()),
        'DC-3' => array('key' => 'legal',  'label' => 'المراجعة القانونية', 'cols' => array()),
        'DC-4' => array('key' => 'credit', 'label' => 'المراجعة الائتمانية', 'cols' => array()),
    );
    foreach ($cols as $k => $c) { if (isset($v[$c['dc']])) { $v[$c['dc']]['cols'][] = $k; } }
    foreach ($v as $dc => $x) { if (!$x['cols']) { unset($v[$dc]); } }
    return $v;
}

/** تنسيقُ قيمةِ خلية — والفارغُ «—» بوسمِ الحوكمة. */
function u13_cell($val, $col)
{
    $v = trim((string) $val);
    if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') { return '—'; }
    if (in_array($col, array('is_partial', 'readonly', 'evidence_accepted', 'onerous', 'needs_cap',
                             'cancellable', 'recognition_candidate', 'frozen', 'has_conflict',
                             'needs_action', 'needs_doc', 'is_sensitive', 'posts_entry'), true)) {
        return ((int) $v === 1) ? 'نعم' : 'لا';
    }
    if (preg_match('~^-?\d+\.\d{2,6}$~', $v)) { return number_format((float) $v, 2); }
    return mb_strlen($v) > 160 ? (mb_substr($v, 0, 158) . '…') : $v;
}

} // function_exists

/* ═══════════════════════════════════════════════════════════════════════════
   القالبُ — يعمل في النطاقِ العامِّ ويحتاج `$U13` مصفوفةَ العقد
   ═══════════════════════════════════════════════════════════════════════════ */
if (!isset($U13) || !is_array($U13)) {
    exit('u13_screen_kit: تُضمَّن العُدّةُ بعد تعريفِ $U13 — انظر رأسَ الملف');
}

$u13Root = dirname(__DIR__);
require_once $u13Root . '/includes/session_bootstrap.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

require_once $u13Root . '/config.php';
require_once $u13Root . '/includes/permissions_helper.php';
require_once $u13Root . '/includes/gov_columns.php';
require_once $u13Root . '/includes/screen_contract.php';

$u13Company = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$u13IsSuper = (strval($_SESSION['user']['role'] ?? '') === '-1');
$u13Role    = strval($_SESSION['user']['role'] ?? '');
$u13Uid     = intval($_SESSION['user']['id'] ?? 0);
if (!$u13IsSuper && $u13Company <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', '');
    exit();
}

/* حارسُ الشاشة — can_view من `modules` · والسوبر يمر (M-14 BR-GOV-01). */
$__pp = check_page_permissions($conn, $U13['file']);
if (!$u13IsSuper && empty($__pp['can_view'])) {
    require_once $u13Root . '/includes/perm_explain_live.php';
    $u13Why = ems_deny_message($conn, intval($u13Role), $U13['file']);
    ems_gov_flash_redirect('../main/dashboard.php', $u13Why, 'GOV-INFO-200', '');
    exit();
}

/* ═══ طبقةُ الكتابة — الأفعالُ تُنفَّذ بخدمتِها لا بـSQL في الشاشة ═════════
   ◆ الشاشةُ لا تكتب الجدولَ مباشرةً: تُنادي **الخدمةَ المالكةَ للحكم**، فتبقى
     الحراسةُ في مكانٍ واحدٍ ويستحيل أن تلتفَّ شاشةٌ على قاعدةٍ يحرسها المحرّك.
     ولكلِّ فعلٍ رمزٌ في قاموسِ الأفعالِ يُفحص به.
   ◆ والصلاحيةُ شرطٌ: `can_edit` من `modules` — والقارئةُ لا فعلَ لها. */
$u13Actions = isset($U13['actions']) && is_array($U13['actions']) ? $U13['actions'] : array();
$u13Msg = '';

/** الرمزُ مسجَّلٌ في قاموسِ الأفعال؟ — وإلا فالفعلُ لا يعمل (fail-closed). */
if (!function_exists('u13_action_registered')):
function u13_action_registered(\mysqli $conn, $code)
{
    static $cache = array();
    if ($code === '') { return false; }
    if (isset($cache[$code])) { return $cache[$code]; }
    $st = $conn->prepare("SELECT 1 FROM nav09_action_map WHERE canonical_code = ? LIMIT 1");
    if (!$st) { return $cache[$code] = false; }
    $st->bind_param('s', $code);
    $st->execute();
    $ok = (bool) $st->get_result()->fetch_row();
    $st->close();
    return $cache[$code] = $ok;
}
endif;

if ($u13Actions && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['u13_action'])) {
    $act  = (string) $_POST['u13_action'];
    $spec = isset($u13Actions[$act]) ? $u13Actions[$act] : null;
    $code = $spec ? (string) ($spec['code'] ?? '') : '';

    /* ① الرمزُ المميّز — الحارسُ المركزيُّ متدرِّجٌ بمساراتِه، وهذه مسالكُ
         كتابةٍ جديدةٌ فتُنفَذ عليها من يومِها لا بانتظارِ دورِها في القائمة. */
    if (!function_exists('verify_csrf_token') || !verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $u13Msg = '✗ رمزُ الحمايةِ غيرُ صالحٍ — أعِد تحميلَ الصفحةِ وحاول';
        if (function_exists('log_security_event')) {
            log_security_event('U13_ACTION_CSRF_FAIL', $U13['screen'] . ' · ' . $act);
        }
    } elseif ($spec === null) {
        $u13Msg = '✗ فعلٌ غيرُ معرَّفٍ لهذه الشاشة';
    } elseif (!$u13IsSuper && empty($__pp['can_edit'])) {
        $u13Msg = '✗ لا صلاحيةَ كتابةٍ في هذه الشاشة';
    } elseif (!u13_action_registered($conn, $code)) {
        /* ② «الوصلُ في موضعين وإلا زخرفة»: فعلٌ بلا رمزٍ في القاموسِ لا يُقاس
             ولا يُدقَّق — فلا يُنفَّذ. */
        $u13Msg = '✗ فعلٌ غيرُ مسجَّلٍ في قاموسِ الأفعال: ' . ($code !== '' ? $code : '(بلا رمز)');
        if (function_exists('log_security_event')) {
            log_security_event('U13_ACTION_UNREGISTERED', $U13['screen'] . ' · ' . $act . ' · ' . $code);
        }
    } else {
        try {
            $res = call_user_func($spec['run'], $conn, $u13Company, $u13Uid, $_POST);
            $u13Msg = !empty($res['ok'])
                    ? '✓ ' . (string) ($res['reason'] ?? 'تمّ')
                    : '✗ ' . (string) ($res['reason'] ?? 'تعذّر الفعل');
            if (function_exists('log_security_event')) {
                log_security_event('U13_ACTION', $code . ' — ' . mb_substr($u13Msg, 0, 160));
            }
        } catch (\Throwable $e) { ems_catch_ignored($e, __METHOD__, 'نصُّ الاستثناءِ يُعرض للمستخدمِ في رسالةِ الشاشةِ — فالفشلُ ظاهرٌ لا مبتلَع');
            $u13Msg = '✗ ' . $e->getMessage();
        }
    }
}

/* ── العقد: الأعمدةُ من التصنيفِ لا من قائمةٍ في الملف ─────────────────── */
$u13Table  = $U13['table'];
$u13Screen = isset($U13['screen']) ? $U13['screen'] : preg_replace('~^.*/|\.php$~', '', $U13['file']);
$u13Cols   = u13_columns($conn, $u13Screen, $u13Table);
$u13Views  = u13_views($u13Cols);

/* ── الصفوف ─────────────────────────────────────────────────────────────── */
$u13Rows = array();
$u13Where = array();
/* المرجعُ العامُّ (`global_ref`) يحمل company_id = 0 — فلا يُرشَّح بالكيان. */
if (empty($U13['global_ref']) && !($u13IsSuper && $u13Company <= 0)) {
    $u13Where[] = 'company_id = ' . $u13Company;
}
if (!empty($U13['where'])) { $u13Where[] = '(' . $U13['where'] . ')'; }
/* شاشةُ «عملي اليوم» بنطاقِ صاحبِها وحدَه (FACC-0028: بنطاقِه وتخصصِه وحدَهما). */
if (!empty($U13['scope_user']) && !$u13IsSuper) {
    $u13Where[] = '`' . $U13['scope_user'] . '` = ' . $u13Uid;
}
$u13Sql = 'SELECT * FROM `' . $u13Table . '`'
        . ($u13Where ? ' WHERE ' . implode(' AND ', $u13Where) : '')
        . ' ORDER BY ' . (isset($U13['order']) ? $U13['order'] : 'id DESC')
        . ' LIMIT ' . intval(isset($U13['limit']) ? $U13['limit'] : 500);
$u13Rs = $conn->query($u13Sql);
$u13Err = $u13Rs ? '' : $conn->error;
while ($u13Rs && $x = $u13Rs->fetch_assoc()) { $u13Rows[] = $x; }

$page_title = 'إيكوبيشن | ' . $U13['title'];
ems_shell_axes($__pp);
include $u13Root . '/inheader.php';
include $u13Root . '/insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }

$u13Nature = isset($U13['nature']) ? $U13['nature'] : 'read';
$u13NatureLabel = array('document' => 'مستندٌ يُعتمد', 'register' => 'سجلٌّ أو إعداد', 'read' => 'قارئة');
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title   = $U13['title'];
    $header_icon    = isset($U13['icon']) ? $U13['icon'] : 'fa fa-table';
    $header_actions = array();
    $header_back    = false;
    include $u13Root . '/includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if ($u13Msg !== '') {
        $cls = (mb_substr($u13Msg, 0, 1) === '✓') ? 'alert-success' : 'alert-danger';
        echo '<div class="alert ' . $cls . '">' . htmlspecialchars($u13Msg, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <?php if ($u13Actions && ($u13IsSuper || !empty($__pp['can_edit']))): ?>
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-bolt"></i> الأفعالُ المتاحةُ على هذه الشاشة</h5>
    </div><div class="card-body">
        <?php foreach ($u13Actions as $key => $spec): ?>
        <form method="post" action="" class="u13-act">
            <input type="hidden" name="u13_action" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="u13-act-row">
                <div class="u13-act-title">
                    <strong><?php echo htmlspecialchars($spec['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <?php if (!empty($spec['rule'])): ?>
                    <div style="opacity:.7;font-size:.86em"><?php echo htmlspecialchars($spec['rule'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                </div>
                <?php foreach (($spec['fields'] ?? array()) as $fk => $fl): ?>
                <div class="u13-act-field">
                    <label><?php echo htmlspecialchars($fl, ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="text" name="<?php echo htmlspecialchars($fk, ENT_QUOTES, 'UTF-8'); ?>"
                           maxlength="190" <?php echo empty($spec['optional'][$fk] ?? false) ? 'required' : ''; ?>>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn-primary"><i class="fa fa-check"></i> تنفيذ</button>
            </div>
        </form>
        <?php endforeach; ?>
    </div></div>
    <style>
    .u13-act{margin-bottom:10px}
    .u13-act-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;
      padding:10px 12px;border:1px solid var(--ems-border,#e3e5e8);border-radius:10px}
    .u13-act-title{min-width:230px;flex:1}
    .u13-act-field{display:flex;flex-direction:column;gap:4px;min-width:170px}
    .u13-act-field label{font-size:.82em;opacity:.75}
    .u13-act-field input{padding:6px 10px;border:1px solid var(--ems-border,#d8dbe0);
      border-radius:7px;font-family:inherit}
    </style>
    <?php endif; ?>

    <?php if (!empty($U13['intro'])): ?>
    <div class="card"><div class="card-body" style="padding:12px 16px">
        <div style="display:flex;gap:10px;align-items:flex-start">
            <i class="fa fa-circle-info" style="margin-top:3px;opacity:.6"></i>
            <div>
                <div style="font-weight:600;margin-bottom:4px"><?php echo htmlspecialchars($U13['intro'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($U13['rule'])): ?>
                <div style="opacity:.78;font-size:.92em"><?php echo htmlspecialchars($U13['rule'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <div style="opacity:.6;font-size:.85em;margin-top:6px">
                    طبيعتُها: <?php echo $u13NatureLabel[$u13Nature]; ?>
                    · الأعمدة: <?php echo count($u13Cols); ?>
                    · الصفوف: <?php echo count($u13Rows); ?>
                    <?php if (!empty($U13['doc'])): ?> · المصدر: <?php echo htmlspecialchars($U13['doc'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                </div>
            </div>
        </div>
    </div></div>
    <?php endif; ?>

    <?php if ($u13Err !== ''): ?>
        <div class="alert alert-danger">تعذّرت القراءة: <?php echo htmlspecialchars($u13Err, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <?php if (count($u13Views) > 1): /* CM-09 منتقي المنظر · CM-10 إظهار الكل */ ?>
        <div class="u13-views">
            <button type="button" class="btn-secondary is-active" data-view="all">إظهار كل الأعمدة</button>
            <?php foreach ($u13Views as $v): ?>
            <button type="button" class="btn-secondary" data-view="<?php echo $v['key']; ?>">
                <?php echo htmlspecialchars($v['label'], ENT_QUOTES, 'UTF-8'); ?>
                <span style="opacity:.55">(<?php echo count($v['cols']); ?>)</span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="table-responsive">
        <table class="alltables display" id="<?php echo htmlspecialchars($u13Screen, ENT_QUOTES, 'UTF-8'); ?>Table">
            <thead><tr>
                <?php foreach ($u13Cols as $k => $c):
                    $vk = isset($u13Views[$c['dc']]) ? $u13Views[$c['dc']]['key'] : 'fin'; ?>
                <th data-dc="<?php echo $c['dc']; ?>" data-view="<?php echo $vk; ?>"
                    <?php echo $c['sensitive'] ? 'class="ems-gov-th" data-slice="1" title="حقلٌ حساسٌ — يُسجَّل الاطّلاعُ عليه"' : ''; ?>>
                    <?php echo htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8'); ?>
                </th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php if (!$u13Cols): ?>
                <tr><td class="text-center text-muted">لا عمودَ مصنَّفٌ لهذه الشاشة — والحقلُ بلا صنفٍ لا يُدرَج (OBL-0052)</td></tr>
            <?php elseif (!$u13Rows): ?>
                <tr><td colspan="<?php echo count($u13Cols); ?>" class="text-center text-muted">
                    <?php echo htmlspecialchars(isset($U13['empty_hint']) ? $U13['empty_hint'] : 'لا بياناتَ بعدُ', ENT_QUOTES, 'UTF-8'); ?>
                </td></tr>
            <?php else: foreach ($u13Rows as $r): ?>
                <tr>
                    <?php foreach ($u13Cols as $k => $c):
                        $val = u13_cell(isset($r[$k]) ? $r[$k] : '', $k);
                        $vk  = isset($u13Views[$c['dc']]) ? $u13Views[$c['dc']]['key'] : 'fin'; ?>
                    <td data-view="<?php echo $vk; ?>"<?php echo $val === '—' ? ' class="ems-gov-empty"' : ''; ?>>
                        <?php echo htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

<style>
.u13-views{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.u13-views .btn-secondary{border:1px solid var(--ems-border,#d8dbe0);background:transparent;border-radius:8px;
  padding:6px 14px;cursor:pointer;font-size:.9em;font-family:inherit;color:inherit}
.u13-views .btn-secondary.is-active{background:var(--ems-primary,#f5c518);border-color:transparent;font-weight:600;color:#111}
</style>
<script>
(function () {
    /* CM-09/CM-10: المنظرُ يُخفي أعمدةَ غيرِه — و«إظهار كل الأعمدة» يعيدها.
       ولا يُظهر شيئًا لم يُصيَّر أصلًا: المحجوبُ محجوبٌ من الخادم. */
    var wrap = document.querySelector('.u13-views');
    if (!wrap) { return; }
    var card = wrap.closest('.card');
    var table = card ? card.querySelector('table') : null;
    if (!table) { return; }
    wrap.addEventListener('click', function (e) {
        var b = e.target.closest('.btn-secondary');
        if (!b) { return; }
        wrap.querySelectorAll('.btn-secondary').forEach(function (x) { x.classList.remove('is-active'); });
        b.classList.add('is-active');
        var v = b.getAttribute('data-view');
        table.querySelectorAll('[data-view]').forEach(function (cell) {
            cell.style.display = (v === 'all' || cell.getAttribute('data-view') === v) ? '' : 'none';
        });
    });
})();
</script>
