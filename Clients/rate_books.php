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
        header('Location: rate_books.php?' . ($q !== '' ? $q . '&' : '') . 'msg=' . urlencode($msg));
        exit();
    }
}

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) { header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.')); exit(); }
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
        if ($st === 'معتمد') { $data['approved_by'] = $uid; $data['approved_at'] = date('Y-m-d H:i:s'); }

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
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
<?php
$header_title = 'دفترُ الأسعار بالشرائح';
$header_icon = 'fa fa-book-open';
$header_actions = array();
if ($can_add) { $header_actions[] = array('id' => 'rbToggleBook', 'class' => 'add-btn', 'icon' => 'fa fa-plus', 'label' => 'دفترٌ جديد'); }
$header_back = array('href' => '../main/role_board.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
include('../includes/page_header.php');
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
    <div class="alert alert-info" style="margin:10px 0"><?php echo rb_e($_GET['msg']); ?></div>
  <?php endif; ?>

  <!-- ③ حاسبةُ أفضل سعر -->
  <div class="card" style="margin-top:6px"><div class="card-body">
    <h5 style="margin:0 0 10px"><i class="fa fa-calculator"></i> حاسبةُ أفضل سعر</h5>
    <form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
      <input type="hidden" name="book" value="<?php echo (int) $sel_book; ?>">
      <div><label>الفئة</label>
        <select name="c_type" class="form-control">
          <option value="0">— اختر —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?php echo (int) $t['id']; ?>" <?php echo $c_type === (int) $t['id'] ? 'selected' : ''; ?>>
              <?php echo rb_e($t['type']); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>نموذجُ العمل</label>
        <select name="c_model" class="form-control">
          <?php foreach (RB::WORK_MODELS as $k => $v): ?>
            <option value="<?php echo rb_e($k); ?>" <?php echo $c_model === $k ? 'selected' : ''; ?>>
              <?php echo rb_e($v); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>المدة (أيام)</label>
        <input type="number" name="c_days" min="1" value="<?php echo $c_days ?: ''; ?>" class="form-control" style="width:120px"></div>
      <div><label>العميل (اختياري)</label>
        <select name="c_client" class="form-control">
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
        <div class="alert alert-warning" style="margin-top:12px">
          لا سعرَ مطابقٌ لهذه الفئة والنموذج والمدة في أي دفترٍ <b>معتمدٍ</b> ساري المفعول —
          فلا سعرَ يُخترع. أضف بندًا في دفترٍ معتمد.
        </div>
      <?php else: ?>
        <div class="alert alert-success" style="margin-top:12px">
          <div style="font-size:1.1rem">
            <b><?php echo number_format((float) $calc['unit_price'], 2); ?></b>
            <?php echo rb_e($calc['currency']); ?> /
            <?php echo rb_e(RB::WORK_MODELS[$c_model]); ?>
            &nbsp;·&nbsp; الشريحة: <b><?php echo rb_e(RB::tierLabel($calc['tier_from_days'], $calc['tier_to_days'])); ?></b>
            &nbsp;·&nbsp; الدفتر: <?php echo rb_e($calc['book_code'] . ' — ' . $calc['book_name']); ?>
            <?php if ($calc['client_id'] !== null): ?>
              <span class="badge" style="background:#2F5D6E;color:#fff">دفترُ عميل</span>
            <?php endif; ?>
          </div>
          <div style="margin-top:6px">
            الأيامُ المفوترة: <b><?php echo (int) $calc['billable_days']; ?></b>
            <?php if (!empty($calc['min_applied'])): ?>
              <span style="color:#8A5712">(رُفعت من <?php echo (int) $c_days; ?> بحدٍّ أدنى
                <?php echo (int) $calc['min_hire_days']; ?> يومًا)</span>
            <?php endif; ?>
            &nbsp;·&nbsp; الإجمالي: <b><?php echo number_format((float) $calc['line_total'], 2); ?></b>
            <?php echo rb_e($calc['currency']); ?>
            <?php if ((float) $calc['mobilization_fee'] > 0): ?>
              &nbsp;+&nbsp; ترحيل <?php echo number_format((float) $calc['mobilization_fee'], 2); ?>
            <?php endif; ?>
          </div>
          <div style="margin-top:4px;font-size:.9rem;color:#555">
            المشغّل: <?php echo !empty($calc['operator_included']) ? 'ضمن السعر' : 'خارج السعر'; ?>
            &nbsp;·&nbsp; الوقود: <?php echo !empty($calc['fuel_included']) ? 'ضمن السعر' : 'خارج السعر'; ?>
          </div>
        </div>
        <?php if (!empty($calc_tiers) && count($calc_tiers) > 1): ?>
          <div style="margin-top:8px">
            <small class="text-muted">كلُّ الشرائح لهذه الفئة — «الأطولُ أرخص» يُرى لا يُقال:</small>
            <table class="table table-sm" data-no-dt="1" style="margin-top:4px;max-width:640px">
              <thead><tr><th>الشريحة</th><th>سعرُ الوحدة</th><th>الحدُّ الأدنى</th></tr></thead>
              <tbody>
              <?php foreach ($calc_tiers as $tr): ?>
                <tr<?php echo ((int) $tr['tier_from_days'] === (int) $calc['tier_from_days']) ? ' style="background:#E1EEE7;font-weight:700"' : ''; ?>>
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
  <div class="card allforms" id="rbBookCard" style="margin-top:14px;display:none"><div class="card-body">
    <h5 style="margin:0 0 10px"><i class="fa fa-plus"></i> دفترٌ جديد</h5>
    <form method="post" class="ems-form">
      <input type="hidden" name="csrf_token" value="<?php echo rb_e($rb_csrf); ?>">
      <input type="hidden" name="rb_action" value="save_book">
      <input type="hidden" name="book_id" value="0">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px">
        <div><label>اسمُ الدفتر *</label><input type="text" name="name" required maxlength="160" class="form-control"
             placeholder="تسعيرة 2026 — تعدين"></div>
        <div><label>العملة</label><select name="currency" class="form-control">
            <?php foreach ($RB_CURR as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?>
          </select></div>
        <div><label>يسري من *</label><input type="date" name="valid_from" required value="<?php echo date('Y-m-d'); ?>" class="form-control"></div>
        <div><label>إلى (اتركه فارغًا = مفتوح)</label><input type="date" name="valid_to" class="form-control"></div>
        <div><label>الحالة</label><select name="state" class="form-control">
            <?php foreach ($RB_STATES as $s): ?><option value="<?php echo rb_e($s); ?>"><?php echo rb_e($s); ?></option><?php endforeach; ?>
          </select><small class="text-muted">المعتمدُ وحدَه يُستعمل في التسعير</small></div>
        <div><label>خاصٌّ بعميل (اختياري)</label><select name="client_id" class="form-control">
            <option value="0">— دفترٌ عام —</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?php echo (int) $c['id']; ?>"><?php echo rb_e($c['client_name']); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div style="grid-column:1/-1"><label>ملاحظات</label><input type="text" name="note" maxlength="255" class="form-control"></div>
      </div>
      <div style="margin-top:12px">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> احفظ</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('rbBookCard').style.display='none'">إلغاء</button>
      </div>
    </form>
  </div></div>
  <?php endif; ?>

  <div class="card" style="margin-top:14px"><div class="card-body">
    <h5 style="margin:0 0 10px"><i class="fa fa-book"></i> الدفاتر</h5>
    <div class="table-responsive"><table class="table table-sm" data-no-dt="1">
      <thead><tr><th>الكود</th><th>الاسم</th><th>العملة</th><th>العميل</th><th>السريان</th><th>الحالة</th><th>البنود</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($books as $b): ?>
        <tr<?php echo ((int) $b['id'] === $sel_book) ? ' style="background:#F7EAC8"' : ''; ?>>
          <td><?php echo rb_e($b['book_code']); ?></td>
          <td><?php echo rb_e($b['name']); ?></td>
          <td><?php echo rb_e($b['currency']); ?></td>
          <td><?php echo rb_e($b['client_name'] ?: 'عام'); ?></td>
          <td><?php echo rb_e($b['valid_from'] . ' → ' . ($b['valid_to'] ?: 'مفتوح')); ?></td>
          <td><span style="font-weight:700;color:<?php echo $b['state'] === 'معتمد' ? '#2C6749' : '#8A5712'; ?>">
              <?php echo rb_e($b['state']); ?></span></td>
          <td><?php echo (int) $b['lines_n']; ?></td>
          <td><a class="btn btn-sm btn-outline-primary" href="rate_books.php?book=<?php echo (int) $b['id']; ?>">
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
  <div class="card" style="margin-top:14px"><div class="card-body">
    <h5 style="margin:0 0 10px"><i class="fa fa-layer-group"></i> بنودُ الدفتر المحدَّد</h5>
    <?php if ($can_add): ?>
    <form method="post" class="ems-form" style="margin-bottom:14px;padding:12px;background:#F5F2E9;border-radius:4px">
      <input type="hidden" name="csrf_token" value="<?php echo rb_e($rb_csrf); ?>">
      <input type="hidden" name="rb_action" value="save_line">
      <input type="hidden" name="book_id" value="<?php echo (int) $sel_book; ?>">
      <input type="hidden" name="line_id" value="0">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
        <div><label>الفئة *</label><select name="equipment_type_id" required class="form-control">
            <option value="">— اختر —</option>
            <?php foreach ($types as $t): ?>
              <option value="<?php echo (int) $t['id']; ?>"><?php echo rb_e($t['type']); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label>نموذجُ العمل *</label><select name="work_model" class="form-control">
            <?php foreach (RB::WORK_MODELS as $k => $v): ?>
              <option value="<?php echo rb_e($k); ?>"><?php echo rb_e($v); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label>من (يوم) *</label><input type="number" name="tier_from_days" min="1" value="1" required class="form-control"></div>
        <div><label>إلى (فارغ = فأكثر)</label><input type="number" name="tier_to_days" min="1" class="form-control"></div>
        <div><label>سعرُ الوحدة *</label><input type="number" step="0.01" min="0" name="unit_price" required class="form-control"></div>
        <div><label>حدٌّ أدنى (أيام)</label><input type="number" name="min_hire_days" min="1" value="1" class="form-control"></div>
        <div><label>حدٌّ أدنى ساعات/يوم</label><input type="number" step="0.5" min="0" name="min_hours_per_day" class="form-control"></div>
        <div><label>رسمُ الترحيل</label><input type="number" step="0.01" min="0" name="mobilization_fee" value="0" class="form-control"></div>
        <div style="display:flex;align-items:end;gap:14px">
          <label style="display:flex;align-items:center;gap:5px"><input type="checkbox" name="operator_included" value="1" checked> بمشغّل</label>
          <label style="display:flex;align-items:center;gap:5px"><input type="checkbox" name="fuel_included" value="1"> بوقود</label>
        </div>
      </div>
      <div style="margin-top:10px"><button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> أضف بندًا</button></div>
    </form>
    <?php endif; ?>

    <div class="table-responsive"><table class="table display" id="rbLines">
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
          <td style="font-weight:700"><?php echo number_format((float) $l['unit_price'], 2); ?></td>
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
      <p class="text-muted" style="margin-top:8px">لا بنودَ في هذا الدفتر — والدفترُ بلا بنودٍ لا يُسعّر شيئًا.</p>
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
        if (c) { c.style.display = (c.style.display === 'none' ? '' : 'none'); }
    });
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#rbLines')) {
        $('#rbLines').DataTable({ stateSave: false, pageLength: 50, order: [] });
    }
});
</script>
