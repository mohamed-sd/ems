<?php
/**
 * Clients/rate_books.php — دفترُ الأسعار بالشرائح (RENTAL-CORE ②)
 * ───────────────────────────────────────────────────────────────────────────
 * «التأجيرُ يبيع الزمن» — فالسعرُ دالّةُ مدةٍ لا رقمٌ واحد. الشاشةُ ثلاثةُ أجزاء:
 *   ① دفاترُ الأسعار (رأس: سريانٌ وعملةٌ وعميلٌ اختياري) — ودفترُ العميل يغلب العام.
 *   ② بنودُ الدفتر: سعرٌ لكل (فئة × نموذج عمل × شريحة مدة) + حدٌّ أدنى للمدة.
 *   ③ حاسبةُ أفضل سعر: أدخِل الفئةَ والمدة فتُخبرك الشريحةَ والسعرَ والإجمالي —
 *      وتُظهر أثرَ الحد الأدنى صراحةً حين يرفع الكميةَ المفوترة.
 * منطقُ الترجيح في RateBookService لا هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../app/Services/Rental/RateBookService.php';

use App\Services\Rental\RateBookService as RB;

if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }
if (!function_exists('rb_e')) { function rb_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('rb_back')) {
    function rb_back($msg, $q = '') {
        ems_gov_redirect('Location: rate_books.php?' . ($q !== '' ? $q . '&' : '') . 'msg=' . urlencode($msg));
        exit();
    }
}

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) { ems_gov_flash_redirect('../login.php', 'الحساب غير مرتبط بشركة.', 'GOV-INFO-200', ''); exit(); }
$uid = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

$rb_gate = ems_tenant_db();
$rb_perms = get_current_page_permissions($conn);
$can_add  = !empty($rb_perms['can_add']);
$can_edit = !empty($rb_perms['can_edit']);

// الرمزُ من المصدر المركزي — لا رمزَ موازٍ لكل شاشة (عرفُ CSRF المركزي ADR-05)
$rb_csrf = function_exists('generate_csrf_token')
    ? generate_csrf_token()
    : (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '');

$RB_STATES = array('مسودة', 'معتمد', 'منتهٍ');
$RB_CURR   = array('USD', 'SDG');
$sel_book  = isset($_GET['book']) ? intval($_GET['book']) : 0;
$qs = $sel_book ? 'book=' . $sel_book : '';
$vd = function ($s) { return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); };

// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($posted === '' || !hash_equals($rb_csrf, $posted)) { rb_back('جلسة النموذج غير صالحة ❌', $qs); }
    $action = (string) ($_POST['rb_action'] ?? '');

    // ── رأسُ الدفتر ───────────────────────────────────────────────────────
    if ($action === 'save_book') {
        $bid = intval($_POST['book_id'] ?? 0);
        if (($bid > 0 && !$can_edit) || ($bid === 0 && !$can_add)) { rb_back('لا توجد صلاحية لهذا الإجراء ❌', $qs); }
        $name = trim((string) ($_POST['name'] ?? ''));
        $curr = (string) ($_POST['currency'] ?? 'USD');
        $vf   = (string) ($_POST['valid_from'] ?? '');
        $vt   = trim((string) ($_POST['valid_to'] ?? ''));
        $st   = (string) ($_POST['state'] ?? 'مسودة');
        $cid  = intval($_POST['client_id'] ?? 0);

        if ($name === '') { rb_back('اسمُ الدفتر مطلوب ❌', $qs); }
        if (!in_array($curr, $RB_CURR, true)) { rb_back('العملة غير صالحة ❌', $qs); }
        if (!in_array($st, $RB_STATES, true)) { rb_back('حالةُ الدفتر غير صالحة ❌', $qs); }
        if (!$vd($vf)) { rb_back('تاريخُ بدء السريان غير صالح ❌', $qs); }
        if ($vt !== '' && !$vd($vt)) { rb_back('تاريخُ نهاية السريان غير صالح ❌', $qs); }
        if ($vt !== '' && strtotime($vt) < strtotime($vf)) { rb_back('نهايةُ السريان قبل بدايته ❌', $qs); }
        if ($cid > 0) {
            $c = $rb_gate->selectOne('clients', array('columns' => array('id'), 'where' => array('id' => $cid)));
            if ($c === null) { rb_back('العميلُ المحدد خارج نطاق شركتك ❌', $qs); }
        }

        $data = array('name' => $name, 'currency' => $curr, 'valid_from' => $vf,
            'valid_to' => ($vt !== '' ? $vt : null), 'state' => $st,
            'client_id' => ($cid > 0 ? $cid : null),
            'note' => trim((string) ($_POST['note'] ?? '')), 'updated_at' => date('Y-m-d H:i:s'));

        /* ══ INJ-0030 (P1) — «من أنشأ لا يعتمد»، والاعتمادُ كان **حقلَ حالةٍ في
             النموذجِ نفسِه**: يختار المستخدمُ «معتمد» في قائمةِ الحالةِ فيُختَم
             الدفترُ باسمِه في اللحظةِ نفسِها التي أنشأه فيها. فالاعتمادُ صار
             خانةً لا فعلًا، ولا يدَ ثانيةَ فيه.
           ◆ العلاج: الاعتمادُ يُمنع في مسارِ الحفظِ إن كان المُعتمِدُ هو المُنشئ —
             ويبقى الدفترُ في حالتِه السابقةِ حتى تعتمده يدٌ ثانية. */
        if ($st === 'معتمد') {
            require_once __DIR__ . '/../includes/self_approval_guard.php';
            if ($bid > 0) {
                $__sa = ems_assert_not_self_approval($conn, 'rate_books', 'id', $bid,
                    'دفترُ أسعارٍ #' . $bid, intval($_SESSION['user']['company_id'] ?? 0));
                if ($__sa !== null) { rb_back($__sa['reason'], $qs); }
            } else {
                // دفترٌ جديدٌ: مُنشئُه هو الفاعلُ قطعًا — فلا يُولد معتمَدًا.
                rb_back('**من أنشأ لا يعتمد** — يُحفظ الدفترُ مسودةً ثم تعتمده يدٌ ثانية (UI-01 §8)', $qs);
            }
            $data['approved_by'] = $uid;
            $data['approved_at'] = date('Y-m-d H:i:s');
        }

        try {
            if ($bid > 0) {
                $own = $rb_gate->selectOne('rate_books', array('columns' => array('id'), 'where' => array('id' => $bid)));
                if ($own === null) { rb_back('الدفترُ خارج نطاق شركتك ❌', $qs); }
                $rb_gate->update('rate_books', $data, array('id' => $bid));
                rb_back('حُدِّث الدفتر ✅', 'book=' . $bid);
            }
            $data['book_code'] = RB::nextBookCode($rb_gate);
            $data['created_by'] = $uid;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['is_deleted'] = 0;
            $rb_gate->insert('rate_books', $data);
            rb_back('أُنشئ الدفتر ' . $data['book_code'] . ' ✅');
        } catch (\Throwable $t) { error_log('rate_books save_book: ' . $t->getMessage()); rb_back('تعذّر الحفظ ❌', $qs); }
    }

    // ── بندُ الدفتر ───────────────────────────────────────────────────────
    if ($action === 'save_line') {
        if (!$can_add && !$can_edit) { rb_back('لا توجد صلاحية لهذا الإجراء ❌', $qs); }
        $bid  = intval($_POST['book_id'] ?? 0);
        $lid  = intval($_POST['line_id'] ?? 0);
        $tid  = intval($_POST['equipment_type_id'] ?? 0);
        $wm   = (string) ($_POST['work_model'] ?? 'hour');
        $tf   = max(1, intval($_POST['tier_from_days'] ?? 1));
        $ttRaw = trim((string) ($_POST['tier_to_days'] ?? ''));
        $tt   = ($ttRaw === '') ? null : max(1, intval($ttRaw));
        $up   = (float) ($_POST['unit_price'] ?? 0);
        $mh   = max(1, intval($_POST['min_hire_days'] ?? 1));

        $own = $rb_gate->selectOne('rate_books', array('columns' => array('id'), 'where' => array('id' => $bid)));
        if ($own === null) { rb_back('الدفترُ خارج نطاق شركتك ❌', $qs); }
        if ($tid <= 0) { rb_back('اختر فئةَ المعدة ❌', 'book=' . $bid); }
        if (!isset(RB::WORK_MODELS[$wm])) { rb_back('نموذجُ العمل غير صالح ❌', 'book=' . $bid); }
        if ($up < 0) { rb_back('سعرُ الوحدة لا يكون سالبًا ❌', 'book=' . $bid); }
        if ($tt !== null && $tt < $tf) { rb_back('نهايةُ الشريحة قبل بدايتها ❌', 'book=' . $bid); }

        $data = array('book_id' => $bid, 'equipment_type_id' => $tid, 'work_model' => $wm,
            'tier_from_days' => $tf, 'tier_to_days' => $tt, 'unit_price' => $up,
            'min_hire_days' => $mh,
            'min_hours_per_day' => ($_POST['min_hours_per_day'] ?? '') === '' ? null : (float) $_POST['min_hours_per_day'],
            'mobilization_fee' => max(0, (float) ($_POST['mobilization_fee'] ?? 0)),
            'operator_included' => !empty($_POST['operator_included']) ? 1 : 0,
            'fuel_included' => !empty($_POST['fuel_included']) ? 1 : 0,
            'note' => trim((string) ($_POST['note'] ?? '')), 'updated_at' => date('Y-m-d H:i:s'));
        try {
            if ($lid > 0) {
                $ol = $rb_gate->selectOne('rate_book_lines', array('columns' => array('id'), 'where' => array('id' => $lid)));
                if ($ol === null) { rb_back('البندُ خارج نطاق شركتك ❌', 'book=' . $bid); }
                $rb_gate->update('rate_book_lines', $data, array('id' => $lid));
                rb_back('حُدِّث البند ✅', 'book=' . $bid);
            }
            $data['created_by'] = $uid;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['is_deleted'] = 0;
            $rb_gate->insert('rate_book_lines', $data);
            rb_back('أُضيف البند ✅', 'book=' . $bid);
        } catch (\Throwable $t) {
            error_log('rate_books save_line: ' . $t->getMessage());
            rb_back('تعذّر حفظُ البند — قد تكون الشريحةُ مكرَّرةً لنفس الفئة والنموذج ❌', 'book=' . $bid);
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════
$books = array();
try {
    $books = $rb_gate->scopedQuery(
        array('scope' => array('b' => 'rate_books'), 'enrich' => array('c' => 'clients')),
        "SELECT b.*, c.client_name
           FROM rate_books b
           LEFT JOIN clients c ON c.id = b.client_id
          WHERE {TENANT_SCOPE} AND COALESCE(b.is_deleted,0)=0
          ORDER BY b.state = 'معتمد' DESC, b.valid_from DESC, b.id DESC",
        array()
    );
} catch (\Throwable $t) { error_log('rate_books list: ' . $t->getMessage()); }

// عدُّ البنود لكل دفتر — استعلامٌ مستقلٌّ عبر البوابة (الاستعلامُ الفرعي داخل
// SELECT يلزمه إعلانُ جدوله، والفصلُ هنا أوضحُ وأرخصُ من إعلانه)
$lineCounts = array();
try {
    $lc = $rb_gate->scopedQuery(array('scope' => array('l' => 'rate_book_lines')),
        "SELECT l.book_id, COUNT(*) AS n FROM rate_book_lines l
          WHERE {TENANT_SCOPE} AND COALESCE(l.is_deleted,0)=0 GROUP BY l.book_id", array());
    foreach ($lc as $x) { $lineCounts[(int) $x['book_id']] = (int) $x['n']; }
} catch (\Throwable $t) { error_log('rate_books counts: ' . $t->getMessage()); }
foreach ($books as $i => $b) { $books[$i]['lines_n'] = $lineCounts[(int) $b['id']] ?? 0; }

if ($sel_book === 0 && count($books)) { $sel_book = (int) $books[0]['id']; }

$lines = array();
if ($sel_book > 0) {
    try {
        $lines = $rb_gate->scopedQuery(
            array('scope' => array('l' => 'rate_book_lines'), 'enrich' => array('t' => 'equipments_types')),
            "SELECT l.*, t.type AS type_name
               FROM rate_book_lines l
               LEFT JOIN equipments_types t ON t.id = l.equipment_type_id
              WHERE {TENANT_SCOPE} AND COALESCE(l.is_deleted,0)=0 AND l.book_id = ?
              ORDER BY t.type, l.work_model, l.tier_from_days",
            array($sel_book)
        );
    } catch (\Throwable $t) { error_log('rate_books lines: ' . $t->getMessage()); }
}

$types = array();
$r = @mysqli_query($conn, "SELECT id, type FROM equipments_types ORDER BY type");
while ($r && ($x = mysqli_fetch_assoc($r))) { $types[] = $x; }

$clients = array();
try {
    $clients = $rb_gate->scopedQuery(array('scope' => array('c' => 'clients')),
        "SELECT c.id, c.client_name FROM clients c WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
          ORDER BY c.client_name", array());
} catch (\Throwable $t) { error_log('rate_books clients: ' . $t->getMessage()); }

// ── حاسبةُ أفضل سعر ───────────────────────────────────────────────────────
$calc = null;
$c_type = isset($_GET['c_type']) ? intval($_GET['c_type']) : 0;
$c_model = isset($_GET['c_model']) ? (string) $_GET['c_model'] : 'hour';
$c_days = isset($_GET['c_days']) ? max(0, intval($_GET['c_days'])) : 0;
$c_client = isset($_GET['c_client']) ? intval($_GET['c_client']) : 0;
if ($c_type > 0 && $c_days > 0) {
    $calc = RB::bestRate($rb_gate, $c_type, $c_model, $c_days, $c_client);
    $calc_tiers = RB::tiersFor($rb_gate, $c_type, $c_model, $c_client);
}

$page_title = 'إيكوبيشن | دفترُ الأسعار';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* UXW-01 ②: أنماطُ الشاشةِ الثابتةُ نُقلت من سماتِ style الموضعيةِ إلى أصنافٍ ببادئةِ rb- */
.rb-page .rb-m10y { margin: 10px 0; }
.rb-page .rb-mt6 { margin-top: 6px; }
.rb-page .rb-mt8 { margin-top: 8px; }
.rb-page .rb-mt10 { margin-top: 10px; }
.rb-page .rb-mt12 { margin-top: 12px; }
.rb-page .rb-mt14 { margin-top: 14px; }
.rb-page .rb-h5 { margin: 0 0 10px; }
.rb-page .rb-calc-form { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; }
.rb-page .rb-w120 { width: 120px; }
.rb-page .rb-lead { font-size: 1.1rem; }
.rb-page .rb-badge-client { background: var(--c-2f5d6e, #2F5D6E); color: var(--c-s-fff); }
.rb-page .rb-warn-ink { color: var(--c-8a5712, #8A5712); }
.rb-page .rb-calc-note { margin-top: 4px; font-size: .9rem; color: var(--c-s-555); }
.rb-page .rb-tier-table { margin-top: 4px; max-width: 640px; }
.rb-page .rb-tier-active { background: var(--c-e1eee7, #E1EEE7); font-weight: 700; }
.rb-page .rb-grid-210 { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; }
.rb-page .rb-grid-150 { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
.rb-page .rb-col-full { grid-column: 1 / -1; }
.rb-page .rb-book-sel { background: var(--c-f7eac8, #F7EAC8); }
.rb-page .rb-state-approved { font-weight: 700; color: var(--c-2c6749, #2C6749); }
.rb-page .rb-state-draft { font-weight: 700; color: var(--c-8a5712, #8A5712); }
.rb-page .rb-line-form { margin-bottom: 14px; padding: 12px; background: var(--c-f5f2e9, #F5F2E9); border-radius: 4px; }
.rb-page .rb-flex-end { display: flex; align-items: end; gap: 14px; }
.rb-page .rb-check-label { display: flex; align-items: center; gap: 5px; }
.rb-page .rb-bold { font-weight: 700; }
</style>
<div class="main ems-unified-page-shell rb-page">
<?php
$header_title = 'دفترُ الأسعار بالشرائح';
$header_icon = 'fa fa-book-open';
$header_actions = array();
if ($can_add) { $header_actions[] = array('id' => 'rbToggleBook', 'class' => 'add-btn', 'icon' => 'fa fa-plus', 'label' => 'دفترٌ جديد'); }
$header_back = array('href' => '../main/role_board.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
include('../includes/page_header.php');
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا دفاترَ أسعارٍ مسجَّلةً بعدُ', 'أنشئ دفترًا بزرِّ «دفترٌ جديد» في رأسِ الشاشةِ ثم اعتمدْه ليُستعمل في التسعير');
if (function_exists('ems_screen_about')) {
    ems_screen_about(
        'السعرُ في التأجير دالّةُ مدةٍ لا رقمٌ واحد: لكل فئةٍ ونموذجِ عملٍ شريحةُ مدةٍ وسعرُها. '
        . 'ودفترُ العميل يغلب الدفترَ العام، والأحدثُ سريانًا يغلب الأقدم. '
        . 'والحدُّ الأدنى للمدة يرفع الكميةَ المفوترة ولا يمنع الحجز — وهذا عرفُ التأجير.',
        array('أنشئ دفترًا واعتمده', 'أضف بنودًا بشرائحَ متدرجة', 'جرّب الحاسبة قبل التسعير')
    );
}
?>
  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-info rb-m10y"><?php echo rb_e($_GET['msg']); ?></div>
  <?php endif; ?>

  <!-- ③ حاسبةُ أفضل سعر -->
  <div class="card rb-mt6"><div class="card-body">
    <h5 class="rb-h5"><i class="fa fa-calculator"></i> حاسبةُ أفضل سعر</h5>
    <form method="get" class="rb-calc-form">
      <input type="hidden" name="book" value="<?php echo (int) $sel_book; ?>">
      <div><label for="emsf_2_44625">الفئة</label>
        <select name="c_type" class="form-control" id="emsf_2_44625">
          <option value="0">— اختر —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?php echo (int) $t['id']; ?>" <?php echo $c_type === (int) $t['id'] ? 'selected' : ''; ?>>
              <?php echo rb_e($t['type']); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="emsf_3_d90df">نموذجُ العمل</label>
        <select name="c_model" class="form-control" id="emsf_3_d90df">
          <?php foreach (RB::WORK_MODELS as $k => $v): ?>
            <option value="<?php echo rb_e($k); ?>" <?php echo $c_model === $k ? 'selected' : ''; ?>>
              <?php echo rb_e($v); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="emsf_4_89926">المدة (أيام)</label>
        <input type="number" name="c_days" min="1" id="emsf_4_89926" class="form-control rb-w120" aria-label="المدة بالأيام لحسابِ أفضل سعر" value="<?php echo $c_days ?: ''; ?>"></div>
      <div><label for="emsf_5_9b848">العميل (اختياري)</label>
        <select name="c_client" class="form-control" id="emsf_5_9b848">
          <option value="0">— الدفترُ العام —</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?php echo (int) $c['id']; ?>" <?php echo $c_client === (int) $c['id'] ? 'selected' : ''; ?>>
              <?php echo rb_e($c['client_name']); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><button type="submit" class="btn btn-primary"><i class="fa fa-equals"></i> احسب</button></div>
    </form>

    <?php if ($c_type > 0 && $c_days > 0): ?>
      <?php if ($calc === null): ?>
        <div class="alert alert-warning rb-mt12">
          لا سعرَ مطابقٌ لهذه الفئة والنموذج والمدة في أي دفترٍ <b>معتمدٍ</b> ساري المفعول —
          فلا سعرَ يُخترع. أضف بندًا في دفترٍ معتمد.
        </div>
      <?php else: ?>
        <div class="alert alert-success rb-mt12">
          <div class="rb-lead">
            <b><?php echo number_format((float) $calc['unit_price'], 2); ?></b>
            <?php echo rb_e($calc['currency']); ?> /
            <?php echo rb_e(RB::WORK_MODELS[$c_model]); ?>
            &nbsp;·&nbsp; الشريحة: <b><?php echo rb_e(RB::tierLabel($calc['tier_from_days'], $calc['tier_to_days'])); ?></b>
            &nbsp;·&nbsp; الدفتر: <?php echo rb_e($calc['book_code'] . ' — ' . $calc['book_name']); ?>
            <?php if ($calc['client_id'] !== null): ?>
              <span class="badge rb-badge-client">دفترُ عميل</span>
            <?php endif; ?>
          </div>
          <div class="rb-mt6">
            الأيامُ المفوترة: <b><?php echo (int) $calc['billable_days']; ?></b>
            <?php if (!empty($calc['min_applied'])): ?>
              <span class="rb-warn-ink">(رُفعت من <?php echo (int) $c_days; ?> بحدٍّ أدنى
                <?php echo (int) $calc['min_hire_days']; ?> يومًا)</span>
            <?php endif; ?>
            &nbsp;·&nbsp; الإجمالي: <b><?php echo number_format((float) $calc['line_total'], 2); ?></b>
            <?php echo rb_e($calc['currency']); ?>
            <?php if ((float) $calc['mobilization_fee'] > 0): ?>
              &nbsp;+&nbsp; ترحيل <?php echo number_format((float) $calc['mobilization_fee'], 2); ?>
            <?php endif; ?>
          </div>
          <div class="rb-calc-note">
            المشغّل: <?php echo !empty($calc['operator_included']) ? 'ضمن السعر' : 'خارج السعر'; ?>
            &nbsp;·&nbsp; الوقود: <?php echo !empty($calc['fuel_included']) ? 'ضمن السعر' : 'خارج السعر'; ?>
          </div>
        </div>
        <?php if (!empty($calc_tiers) && count($calc_tiers) > 1): ?>
          <div class="rb-mt8">
            <small class="text-muted">كلُّ الشرائح لهذه الفئة — «الأطولُ أرخص» يُرى لا يُقال:</small>
            <table class="table table-sm rb-tier-table" data-no-dt="hard">
              <thead><tr><th>الشريحة</th><th>سعرُ الوحدة</th><th>الحدُّ الأدنى</th></tr></thead>
              <tbody>
              <?php foreach ($calc_tiers as $tr): ?>
                <tr<?php echo ((int) $tr['tier_from_days'] === (int) $calc['tier_from_days']) ? ' class="rb-tier-active"' : ''; ?>>
                  <td><?php echo rb_e(RB::tierLabel($tr['tier_from_days'], $tr['tier_to_days'])); ?></td>
                  <td><?php echo number_format((float) $tr['unit_price'], 2) . ' ' . rb_e($tr['currency']); ?></td>
                  <td><?php echo (int) $tr['min_hire_days']; ?> يومًا</td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div></div>

  <!-- ① الدفاتر -->
  <?php if ($can_add): ?>
  <div class="card allforms rb-mt14 is-hidden" id="rbBookCard"><div class="card-body">
    <h5 class="rb-h5"><i class="fa fa-plus"></i> دفترٌ جديد</h5>
    <form method="post" class="ems-form">
      <input type="hidden" name="csrf_token" value="<?php echo rb_e($rb_csrf); ?>">
      <input type="hidden" name="rb_action" value="save_book">
      <input type="hidden" name="book_id" value="0">
      <div class="rb-grid-210">
        <div><label for="emsf_6_e1a0e">اسمُ الدفتر *</label><input type="text" name="name" required maxlength="160" class="form-control"
             placeholder="تسعيرة 2026 — تعدين" id="emsf_6_e1a0e"></div>
        <div><label for="emsf_7_afaa9">العملة</label><select name="currency" class="form-control" id="emsf_7_afaa9">
            <?php foreach ($RB_CURR as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?>
          </select></div>
        <div><label for="emsf_8_1f145">يسري من *</label><input type="date" name="valid_from" required id="emsf_8_1f145" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
        <div><label for="emsf_9_eacd5">إلى (اتركه فارغًا = مفتوح)</label><input type="date" name="valid_to" class="form-control" id="emsf_9_eacd5"></div>
        <div><label for="emsf_10_d59c8">الحالة</label><select name="state" class="form-control" id="emsf_10_d59c8">
            <?php foreach ($RB_STATES as $s): ?><option value="<?php echo rb_e($s); ?>"><?php echo rb_e($s); ?></option><?php endforeach; ?>
          </select><small class="text-muted">المعتمدُ وحدَه يُستعمل في التسعير</small></div>
        <div><label for="emsf_11_4be6c">خاصٌّ بعميل (اختياري)</label><select name="client_id" class="form-control" id="emsf_11_4be6c">
            <option value="0">— دفترٌ عام —</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?php echo (int) $c['id']; ?>"><?php echo rb_e($c['client_name']); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="rb-col-full"><label for="emsf_12_80c07">ملاحظات</label><input type="text" name="note" maxlength="255" class="form-control" id="emsf_12_80c07"></div>
      </div>
      <div class="rb-mt12">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> احفظ</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('rbBookCard').classList.add('is-hidden')">إلغاء</button>
      </div>
    </form>
  </div></div>
  <?php endif; ?>

  <div class="card rb-mt14"><div class="card-body">
    <h5 class="rb-h5"><i class="fa fa-book"></i> الدفاتر</h5>
    <div class="table-responsive"><table class="table table-sm" data-no-dt="hard">
      <thead><tr><th>الكود</th><th>الاسم</th><th>العملة</th><th>العميل</th><th>السريان</th><th>الحالة</th><th>البنود</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($books as $b): ?>
        <tr<?php echo ((int) $b['id'] === $sel_book) ? ' class="rb-book-sel"' : ''; ?>>
          <td><?php echo rb_e($b['book_code']); ?></td>
          <td><?php echo rb_e($b['name']); ?></td>
          <td><?php echo rb_e($b['currency']); ?></td>
          <td><?php echo rb_e($b['client_name'] ?: 'عام'); ?></td>
          <td><?php echo rb_e($b['valid_from'] . ' → ' . ($b['valid_to'] ?: 'مفتوح')); ?></td>
          <td><span class="<?php echo $b['state'] === 'معتمد' ? 'rb-state-approved' : 'rb-state-draft'; ?>">
              <?php echo rb_e($b['state']); ?></span></td>
          <td><?php echo (int) $b['lines_n']; ?></td>
          <td><a class="btn btn-sm btn-secondary" href="rate_books.php?book=<?php echo (int) $b['id']; ?>">
              <i class="fa fa-list"></i> البنود</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!count($books)): ?>
        <tr><td colspan="8" class="text-center text-muted">لا دفاترَ بعد — أنشئ دفترًا واعتمده ليُستعمل في التسعير</td></tr>
      <?php endif; ?>
      </tbody>
    </table></div>
  </div></div>

  <!-- ② البنود -->
  <?php if ($sel_book > 0): ?>
  <div class="card rb-mt14"><div class="card-body">
    <h5 class="rb-h5"><i class="fa fa-layer-group"></i> بنودُ الدفتر المحدَّد</h5>
    <?php if ($can_add): ?>
    <form method="post" class="ems-form rb-line-form">
      <input type="hidden" name="csrf_token" value="<?php echo rb_e($rb_csrf); ?>">
      <input type="hidden" name="rb_action" value="save_line">
      <input type="hidden" name="book_id" value="<?php echo (int) $sel_book; ?>">
      <input type="hidden" name="line_id" value="0">
      <div class="rb-grid-150">
        <div><label for="emsf_13_f6970">الفئة *</label><select name="equipment_type_id" required class="form-control" id="emsf_13_f6970">
            <option value="">— اختر —</option>
            <?php foreach ($types as $t): ?>
              <option value="<?php echo (int) $t['id']; ?>"><?php echo rb_e($t['type']); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label for="emsf_14_34529">نموذجُ العمل *</label><select name="work_model" class="form-control" id="emsf_14_34529">
            <?php foreach (RB::WORK_MODELS as $k => $v): ?>
              <option value="<?php echo rb_e($k); ?>"><?php echo rb_e($v); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label for="emsf_15_7438d">من (يوم) *</label><input type="number" name="tier_from_days" min="1" value="1" required class="form-control" id="emsf_15_7438d"></div>
        <div><label for="emsf_16_0965e">إلى (فارغ = فأكثر)</label><input type="number" name="tier_to_days" min="1" class="form-control" id="emsf_16_0965e"></div>
        <div><label for="emsf_17_c0e04">سعرُ الوحدة *</label><input type="number" step="0.01" min="0" name="unit_price" required class="form-control" id="emsf_17_c0e04"></div>
        <div><label for="emsf_18_60077">حدٌّ أدنى (أيام)</label><input type="number" name="min_hire_days" min="1" value="1" class="form-control" id="emsf_18_60077"></div>
        <div><label for="emsf_19_8846f">حدٌّ أدنى ساعات/يوم</label><input type="number" step="0.5" min="0" name="min_hours_per_day" class="form-control" id="emsf_19_8846f"></div>
        <div><label for="emsf_20_68ffe">رسمُ الترحيل</label><input type="number" step="0.01" min="0" name="mobilization_fee" value="0" class="form-control" id="emsf_20_68ffe"></div>
        <div class="rb-flex-end">
          <label class="rb-check-label"><input type="checkbox" name="operator_included" value="1" aria-label="السعرُ يشمل المشغّل" checked> بمشغّل</label>
          <label class="rb-check-label"><input type="checkbox" name="fuel_included" value="1" aria-label="السعرُ يشمل الوقود"> بوقود</label>
        </div>
      </div>
      <div class="rb-mt10"><button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> أضف بندًا</button></div>
    </form>
    <?php endif; ?>

    <div class="table-responsive"><table class="table display" id="rbLines" data-state-save="false" data-page-length="50" data-order='[]'>
      <thead><tr><th>الفئة</th><th>نموذجُ العمل</th><th>الشريحة</th><th>سعرُ الوحدة</th>
        <th>حدٌّ أدنى (أيام)</th><th>ساعات/يوم</th><th>ترحيل</th><th>مشغّل</th><th>وقود</th>
        <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
        <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
        <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
        <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
        <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
        <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
        </tr></thead>
      <tbody>
      <?php foreach ($lines as $l): ?>
        <tr>
          <td><?php echo rb_e($l['type_name'] ?: '—'); ?></td>
          <td><?php echo rb_e(RB::WORK_MODELS[$l['work_model']] ?? $l['work_model']); ?></td>
          <td><?php echo rb_e(RB::tierLabel($l['tier_from_days'], $l['tier_to_days'])); ?></td>
          <td class="rb-bold"><?php echo number_format((float) $l['unit_price'], 2); ?></td>
          <td><?php echo (int) $l['min_hire_days']; ?></td>
          <td><?php echo $l['min_hours_per_day'] === null ? '—' : (float) $l['min_hours_per_day']; ?></td>
          <td><?php echo number_format((float) $l['mobilization_fee'], 2); ?></td>
          <td><?php echo !empty($l['operator_included']) ? '✓' : '—'; ?></td>
          <td><?php echo !empty($l['fuel_included']) ? '✓' : '—'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if (!count($lines)): ?>
      <p class="text-muted rb-mt8">لا بنودَ في هذا الدفتر — والدفترُ بلا بنودٍ لا يُسعّر شيئًا.</p>
    <?php endif; ?>
  </div></div>
  <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>
<script>
$(function () {
    $('#rbToggleBook').on('click', function (e) {
        e.preventDefault();
        var c = document.getElementById('rbBookCard');
        if (c) { c.classList.toggle('is-hidden'); }
    });
});
</script>
