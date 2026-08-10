<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/contract_obligations.php — مصفوفةُ التزامات عقد العميل (CON-02 §4 · §7-③)
 * ───────────────────────────────────────────────────────────────────────────
 * **الفجوةُ الأمُّ التي تسدّها هذه الشاشة:** النظامُ يشتقّ المسؤولَ عن زمن التوقف
 * من **حالة الساعة** لا من **بند التزامٍ في العقد**. فنفادُ وقودٍ يلتزم به العميلُ
 * تعاقديًّا يُسجَّل `fuel_logistics_stop` ← حكمُه `none` ← فلا يُفوتر؛ والوثيقةُ
 * تقول إنه **استعدادٌ مفوتر**. وهذه الشاشةُ هي مكانُ النصّ العقدي المفقود.
 *
 * **فصلُ اليدين (ق-18):** المبيعات (12) تملأ · مديرُ المالية (19) يُجيز —
 * **ولا تسري مصفوفةٌ حتى تُجاز**. والمنحُ نفسُها تُجسّده: `can_add` الملء و
 * `can_edit` الإجازة، فلا يجتمعان لدور.
 *
 * **لا رجعية (§6):** الصفُّ المُجاز **غيرُ قابلٍ للتعديل**. وتغييرُ الملتزم
 * صفٌّ جديدٌ بتاريخ سريانه — فلا يمسّ الوقائعَ التي فُوترت على النص القديم.
 *
 * **الصلاحيةُ صارمة:** لا اعتمادَ على `check_page_permissions` (تُفتح على
 * مصراعيها حين لا تجد الوحدة) — الوحدةُ تُطلب بكودها وغيابُها منعٌ لا إذن.
 *
 * ⚠️ CSRF مركزيٌّ تلقائيّ (config.php + csrf.js) — لا رموزَ يدويةً هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

// ── صلاحيةٌ صارمة: الوحدةُ بكودها الحرفي، وغيابُها منع ──────────────────────
$MODULE_CODE = 'Contracts/contract_obligations.php';
$can_view = $can_fill = $can_approve = $can_delete = false;
if ($is_super_admin) {
    $can_view = $can_fill = $can_approve = $can_delete = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
                            FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view    = (intval($row['can_view'])   === 1);
        $can_fill    = (intval($row['can_add'])    === 1);  // الملءُ والتعديل — المبيعات
        $can_approve = (intval($row['can_edit'])   === 1);  // الإجازة — مديرُ المالية
        $can_delete  = (intval($row['can_delete']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض مصفوفة الالتزامات ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = ems_tenant_db();

require_once __DIR__ . '/../includes/obligation_maps.php';

if (!function_exists('obl_e')) {
    function obl_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('obl_back')) {
    function obl_back($msg, $contract) {
        ems_gov_redirect('Location: contract_obligations.php?contract=' . intval($contract) . '&msg=' . rawurlencode($msg));
        exit();
    }
}

$sel_contract = isset($_GET['contract']) ? intval($_GET['contract']) : 0;

// ══════════════════════════════════════════════════════════════════════════════
// حفظُ بندٍ (إضافةً أو تعديلَ مسودة) — الدور 12
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_obligation'])) {
    $cid = intval($_POST['client_contract_id'] ?? 0);
    if (!$can_fill) { obl_back('لا توجد صلاحية ملء المصفوفة ❌', $cid); }

    $oid    = intval($_POST['obligation_id'] ?? 0);
    $otype  = trim(strval($_POST['obligation_type'] ?? ''));
    $obligor = trim(strval($_POST['obligor'] ?? ''));
    $effect = trim(strval($_POST['effect_on_billing'] ?? ''));
    $from   = trim(strval($_POST['valid_from'] ?? ''));
    $to     = trim(strval($_POST['valid_to'] ?? ''));

    if (!isset($OBL_TYPES[$otype]))    { obl_back('بندُ الالتزام غير صالح ❌', $cid); }
    if (!isset($OBL_OBLIGORS[$obligor])) { obl_back('الطرفُ الملتزم غير صالح ❌', $cid); }
    if (!isset($OBL_EFFECTS[$effect])) { obl_back('أثرُ الإخلال على الفوترة غير صالح ❌', $cid); }
    if ($from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        obl_back('تاريخُ السريان إلزاميٌّ — به يُعرف أيُّ نصٍّ حكم الواقعة ❌', $cid);
    }
    if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { obl_back('تاريخُ الانتهاء غير صالح ❌', $cid); }
    if ($to !== '' && $to < $from) { obl_back('تاريخُ الانتهاء قبل تاريخ السريان ❌', $cid); }

    // العقدُ ضمن نطاق الشركة — تحقّقٌ عبر البوابة لا بالثقة في المدخل
    try {
        $chk = $gate->selectOne('contracts', array('columns' => array('id'), 'where' => array('id' => $cid)));
    } catch (\Throwable $t) { $chk = null; }
    if (!$chk) { obl_back('العقدُ غير موجود أو خارج نطاق شركتك ❌', $cid); }

    try {
        if ($oid > 0) {
            // ── تعديلٌ: للمسودة وحدَها. المُجازُ نافذٌ فلا يُمسّ (لا رجعية §6) ──
            $cur = $gate->selectOne('contract_obligations', array(
                'columns' => array('id', 'approval_state'), 'where' => array('id' => $oid),
            ));
            if (!$cur) { obl_back('البندُ غير موجود أو خارج نطاق شركتك ❌', $cid); }
            if ($cur['approval_state'] === 'approved') {
                obl_back('البندُ مُجازٌ ونافذ — التغييرُ يكون بصفٍّ جديدٍ بتاريخ سريانه لا بتعديل الماضي ❌', $cid);
            }
            $gate->update('contract_obligations', array(
                'obligation_type'   => $otype,
                'obligor'           => $obligor,
                'effect_on_billing' => $effect,
                'valid_from'        => $from,
                'valid_to'          => ($to === '' ? null : $to),
            ), array('id' => $oid), 'is_deleted = 0');
            obl_back('عُدّلت المسودة ✅', $cid);
        } else {
            $gate->insert('contract_obligations', array(
                'client_contract_id' => $cid,
                'obligation_type'    => $otype,
                'obligor'            => $obligor,
                'effect_on_billing'  => $effect,
                'valid_from'         => $from,
                'valid_to'           => ($to === '' ? null : $to),
                'approval_state'     => 'draft',
                'created_by'         => $uid,
            ));
            obl_back('أُضيفت المسودة — وتنتظر إجازةَ المالية ✅', $cid);
        }
    } catch (\Throwable $t) {
        if (strpos($t->getMessage(), 'uq_obligation_contract_type_from') !== false
            || strpos($t->getMessage(), 'Duplicate') !== false) {
            obl_back('لهذا البند صفٌّ بنفس تاريخ السريان — غيّر التاريخَ أو عدّل القائم ❌', $cid);
        }
        error_log('contract_obligations save: ' . $t->getMessage());
        obl_back('تعذّر الحفظ ❌', $cid);
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// الإجازة — الدور 19 وحدَه (ق-18)
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_action'])) {
    $cid = intval($_POST['client_contract_id'] ?? 0);
    if (!$can_approve) { obl_back('الإجازةُ صلاحيةُ مدير الإدارة المالية وحدَه ❌', $cid); }

    $act = strval($_POST['approve_action']);
    $now = date('Y-m-d H:i:s');
    try {
        if ($act === 'one') {
            $oid = intval($_POST['obligation_id'] ?? 0);
            $cur = $gate->selectOne('contract_obligations', array(
                'where' => array('id' => $oid),
            ));
            if (!$cur) { obl_back('البندُ غير موجود أو خارج نطاق شركتك ❌', $cid); }
            if ($cur['approval_state'] === 'approved') { obl_back('البندُ مُجازٌ سلفًا', $cid); }
            // P1-B — «من أنشأ لا يعتمد»: البندُ التعاقديُّ يُلزم الشركةَ، فإجازتُه يدٌ ثانية.
            require_once __DIR__ . '/../includes/self_approval_guard.php';
            $__sa = ems_no_self_approval($conn, intval($cur['created_by'] ?? 0), intval($uid),
                'بندُ التزامٍ تعاقديٍّ #' . $oid, intval($cur['company_id'] ?? 0));
            if ($__sa !== null) { obl_back($__sa['reason'], $cid); }
            $gate->update('contract_obligations', array(
                'approval_state' => 'approved', 'approved_by' => $uid, 'approved_at' => $now,
            ), array('id' => $oid), 'is_deleted = 0');
            // M-10 (CON-02 §6 · A6): الجديدُ النافذُ يقفل سلفَه على (سريانه − 1)
            // — «ما قبله بحكمه القديم وما بعده بالجديد» بنيويًّا لا التباسًا.
            require_once __DIR__ . '/../app/Services/Contract/ClientAmendmentEffects.php';
            $closed = \App\Services\Contract\ClientAmendmentEffects::closePredecessors($gate, $cur);
            obl_back('أُجيز البند — وصار نافذًا من تاريخ سريانه ✅'
                . ($closed > 0 ? ' وأُقفل سلفُه على ما قبل سريانه' : ''), $cid);
        } elseif ($act === 'all') {
            $drafts = $gate->scopedQuery(array('scope' => array('o' => 'contract_obligations')),
                "SELECT o.id FROM contract_obligations o
                  WHERE {TENANT_SCOPE} AND o.is_deleted = 0 AND o.client_contract_id = ?
                    AND o.approval_state = 'draft'", array($cid));
            if (empty($drafts)) { obl_back('لا مسوداتٍ تنتظر الإجازة', $cid); }
            require_once __DIR__ . '/../app/Services/Contract/ClientAmendmentEffects.php';
            $n = 0;
            $gate->runInTransaction(function ($g) use ($drafts, $uid, $now, &$n) {
                foreach ($drafts as $d) {
                    $g->update('contract_obligations', array(
                        'approval_state' => 'approved', 'approved_by' => $uid, 'approved_at' => $now,
                    ), array('id' => intval($d['id'])), 'is_deleted = 0');
                    // M-10: كلُّ مُجازٍ يقفل أسلافَه على (سريانه − 1) — داخل المعاملة نفسِها
                    $row = $g->selectOne('contract_obligations', array('where' => array('id' => intval($d['id']))));
                    if ($row) { \App\Services\Contract\ClientAmendmentEffects::closePredecessors($g, $row); }
                    $n++;
                }
            });
            obl_back('أُجيزت ' . $n . ' مسودةً — وصارت نافذةً من تواريخ سريانها وأُقفلت أسلافُها ✅', $cid);
        }
    } catch (\Throwable $t) {
        error_log('contract_obligations approve: ' . $t->getMessage());
        obl_back('تعذّرت الإجازة ❌', $cid);
    }
    obl_back('إجراءٌ غير معروف ❌', $cid);
}

// ══════════════════════════════════════════════════════════════════════════════
// حذفُ مسودة (ناعمًا) — الرفضُ نهايةُ مسودةٍ لا حالةٌ يعيش فيها صف
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $oid = intval($_GET['delete_id']);
    $cid = $sel_contract;
    if (!$can_delete) { obl_back('لا توجد صلاحية حذف المسودات ❌', $cid); }
    try {
        $cur = $gate->selectOne('contract_obligations', array(
            'columns' => array('id', 'approval_state'), 'where' => array('id' => $oid),
        ));
    } catch (\Throwable $t) { $cur = null; }
    if (!$cur) { obl_back('البندُ غير موجود أو خارج نطاق شركتك ❌', $cid); }
    if ($cur['approval_state'] === 'approved') {
        obl_back('البندُ مُجازٌ ونافذ — لا يُحذف. أنهِ سريانَه بصفٍّ جديدٍ بدلًا من محو الماضي ❌', $cid);
    }
    try {
        $gate->softDelete('contract_obligations', $oid);
        obl_back('حُذفت المسودة ✅', $cid);
    } catch (\Throwable $t) {
        error_log('contract_obligations delete: ' . $t->getMessage());
        obl_back('تعذّر الحذف ❌', $cid);
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// البيانات
// ══════════════════════════════════════════════════════════════════════════════
$contracts = array();
try {
    $contracts = $gate->scopedQuery(array(
        'scope'  => array('c' => 'contracts'),
        'enrich' => array('p' => 'project'),
    ), "SELECT c.id, p.name AS project_name, c.actual_start, c.actual_end
          FROM contracts c
          LEFT JOIN project p ON p.id = c.project_id
         WHERE {TENANT_SCOPE} AND c.is_deleted = 0
         ORDER BY c.id ASC");
} catch (\Throwable $t) { $contracts = array(); }

// عدّادُ التغطية لكل عقد — كم بندًا مُجازًا ونافذًا اليوم من التسعة
$coverage = array();
try {
    $cov = $gate->scopedQuery(array('scope' => array('o' => 'contract_obligations')),
        "SELECT o.client_contract_id AS cid, COUNT(DISTINCT o.obligation_type) AS n
           FROM contract_obligations o
          WHERE {TENANT_SCOPE} AND o.is_deleted = 0 AND o.approval_state = 'approved'
            AND o.valid_from <= CURDATE() AND (o.valid_to IS NULL OR o.valid_to >= CURDATE())
          GROUP BY o.client_contract_id");
    foreach ($cov as $c) { $coverage[intval($c['cid'])] = intval($c['n']); }
} catch (\Throwable $t) { $coverage = array(); }

$rows = array();
if ($sel_contract > 0) {
    try {
        $rows = $gate->scopedQuery(array(
            'scope'  => array('o' => 'contract_obligations'),
            'enrich' => array('uc' => 'users', 'ua' => 'users'),
        ), "SELECT o.id, o.obligation_type, o.obligor, o.effect_on_billing, o.approval_state,
                   o.valid_from, o.valid_to, o.approved_at, o.created_at,
                   uc.name AS creator_name, ua.name AS approver_name,
                   (o.approval_state = 'approved'
                    AND o.valid_from <= CURDATE()
                    AND (o.valid_to IS NULL OR o.valid_to >= CURDATE())) AS is_effective
              FROM contract_obligations o
              LEFT JOIN users uc ON uc.id = o.created_by
              LEFT JOIN users ua ON ua.id = o.approved_by
             WHERE {TENANT_SCOPE} AND o.is_deleted = 0 AND o.client_contract_id = ?
             ORDER BY FIELD(o.obligation_type, 'fuel','access_road','loading_equipment',
                            'equipment_readiness','operators','permits_safety','utilities',
                            'catering_camp','force_majeure'), o.valid_from DESC",
            array($sel_contract));
    } catch (\Throwable $t) {
        $rows = array();
        error_log('contract_obligations list: ' . $t->getMessage());
    }
}

// المصفوفةُ النافذةُ اليوم: بندٌ ← الصفُّ الحاكم
$effective = array();
$draft_count = 0;
foreach ($rows as $r) {
    if (intval($r['is_effective']) === 1 && !isset($effective[$r['obligation_type']])) {
        $effective[$r['obligation_type']] = $r;
    }
    if ($r['approval_state'] === 'draft') { $draft_count++; }
}
$covered = count($effective);

$page_title = 'مصفوفة التزامات العقد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'obligations';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>

<div class="main obl-main ems-unified-page-shell">

    <?php
    $header_title = 'مصفوفة التزامات العقد';
    $header_icon = 'fa fa-scale-balanced';
    $header_actions = array();
    if ($can_fill && $sel_contract > 0) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo obl_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- التحذيرُ الثابت (§7-③): لا رجعية -->
    <div class="obl-warning">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            <strong>تغييرُ الملتزم يسري من تاريخه ولا يمسّ الوقائعَ السابقة.</strong>
            فالبندُ المُجاز نافذٌ لا يُعدَّل ولا يُحذف — وتغييرُه يكون <em>بصفٍّ جديدٍ بتاريخ سريانه</em>،
            كي تبقى كلُّ واقعةٍ محكومةً بالنصّ الذي كان ساريًا يومَ وقوعها.
        </div>
    </div>

    <div class="filter">
        <div class="filter-title">
            <span class="filter-title-icon"><i class="fa-solid fa-file-contract"></i></span>
            اختر العقد
        </div>
        <div class="filter-body">
            <form method="get" action="" class="obl-picker">
                <div class="filter-field">
                    <label for="emsf_85_05820"><i class="fa fa-file-contract"></i> العقد</label>
                    <select name="contract" class="form-control" onchange="this.form.submit()" id="emsf_85_05820">
                        <option value="">-- اختر العقد --</option>
                        <?php foreach ($contracts as $c):
                            $cid = intval($c['id']);
                            $n = isset($coverage[$cid]) ? $coverage[$cid] : 0; ?>
                            <option value="<?php echo $cid; ?>" <?php echo $cid === $sel_contract ? 'selected' : ''; ?>>
                                عقد #<?php echo $cid; ?> — <?php echo obl_e($c['project_name']); ?>
                                (<?php echo $n; ?>/9 نافذًا)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($sel_contract > 0): ?>
        <!-- لوحةُ التغطية: ما ينقص المصفوفةَ صراحةً -->
        <div class="obl-coverage <?php echo $covered === 9 ? 'is-full' : 'is-partial'; ?>">
            <div class="obl-coverage-head">
                <i class="fas <?php echo $covered === 9 ? 'fa-circle-check' : 'fa-circle-half-stroke'; ?>"></i>
                <strong>المصفوفةُ النافذةُ اليوم: <?php echo $covered; ?> من 9 بنودٍ مُجازة</strong>
                <?php if ($draft_count > 0): ?>
                    <span class="obl-chip obl-chip-draft"><?php echo $draft_count; ?> مسودةً تنتظر الإجازة</span>
                <?php endif; ?>
            </div>
            <?php if ($covered < 9): ?>
                <div class="obl-coverage-missing">
                    <span>الناقص:</span>
                    <?php foreach ($OBL_TYPES as $k => $label): if (!isset($effective[$k])): ?>
                        <span class="obl-chip obl-chip-missing"><?php echo obl_e($label); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <div class="obl-coverage-note">
                    ما دام بندٌ غيرَ مُجازٍ فالعقدُ ناقصُ المصفوفة — وعند تفعيل الحارس تُرفض واقعتُه بـ<code>423</code> (ق-2).
                </div>
            <?php else: ?>
                <div class="obl-coverage-note">المصفوفةُ كاملةٌ ونافذة — العقدُ جاهزٌ لتفعيل الحارس.</div>
            <?php endif; ?>
            <?php if ($can_approve && $draft_count > 0): ?>
                <form method="post" action="" class="obl-inline-form"
                      onsubmit="return confirm('إجازةُ كل المسودات؟ المُجازُ نافذٌ لا يُعدَّل بعدها.')">
                    <input type="hidden" name="approve_action" value="all">
                    <input type="hidden" name="client_contract_id" value="<?php echo $sel_contract; ?>">
                    <button type="submit" class="btn-ok"><i class="fas fa-stamp"></i> إجازةُ كل المسودات (<?php echo $draft_count; ?>)</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($can_fill): ?>
        <form id="oblForm" action="" method="post" class="allforms">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة بندِ التزام</span></h5>
            </div>
            <input type="hidden" name="save_obligation" value="1">
            <input type="hidden" name="obligation_id" id="obligation_id" value="">
            <input type="hidden" name="client_contract_id" value="<?php echo $sel_contract; ?>">
            <div class="card shadow-sm pu-form-card">
                <div class="card-body">
                    <div class="form-grid">
                        <div>
                            <label for="obligation_type"><i class="fas fa-list-check"></i> بندُ الالتزام *</label>
                            <select name="obligation_type" id="obligation_type" required>
                                <?php foreach ($OBL_TYPES as $k => $v): ?>
                                    <option value="<?php echo obl_e($k); ?>"><?php echo obl_e($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="obligor"><i class="fas fa-user-shield"></i> الطرفُ الملتزم *</label>
                            <select name="obligor" id="obligor" required>
                                <?php foreach ($OBL_OBLIGORS as $k => $v): ?>
                                    <option value="<?php echo obl_e($k); ?>" <?php echo $k === 'company' ? 'selected' : ''; ?>><?php echo obl_e($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="obl-hint">ما لم يُنص عليه يُعدُّ التزامَ الشركة (§4)</small>
                        </div>
                        <div>
                            <label for="effect_on_billing"><i class="fas fa-file-invoice-dollar"></i> أثرُ الإخلال على الفوترة *</label>
                            <select name="effect_on_billing" id="effect_on_billing" required>
                                <?php foreach ($OBL_EFFECTS as $k => $v): ?>
                                    <option value="<?php echo obl_e($k); ?>" <?php echo $k === 'per_clause' ? 'selected' : ''; ?>><?php echo obl_e($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="valid_from"><i class="fas fa-calendar-day"></i> يسري من *</label>
                            <input type="date" name="valid_from" id="valid_from" required>
                        </div>
                        <div>
                            <label for="valid_to"><i class="fas fa-calendar-xmark"></i> حتى (اختياري)</label>
                            <input type="date" name="valid_to" id="valid_to">
                            <small class="obl-hint">فارغٌ أي مفتوحُ السريان</small>
                        </div>
                    </div>
                    <div class="pu-form-actions">
                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ المسودة</span></button>
                        <button type="button" id="oblCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-container">
                    <table class="display obl-table no-datatable">
                        <thead>
                            <tr>
                                <th>إجراءات</th>
                                <th>بندُ الالتزام</th>
                                <th>الطرفُ الملتزم</th>
                                <th>أثرُ الإخلال</th>
                                <th>السريان</th>
                                <th>الحالة</th>
                                <th>الإجازة</th>
                                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                                </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="7" class="obl-empty">
                                لا بنودَ لهذا العقد بعد — والمصفوفةُ الفارغةُ تعني أن المسؤولَ يُشتق من حالة الساعة لا من العقد.
                            </td></tr>
                        <?php else: foreach ($rows as $r):
                            $eff = intval($r['is_effective']) === 1;
                            $approved = $r['approval_state'] === 'approved'; ?>
                            <tr class="<?php echo $eff ? 'obl-row-effective' : ($approved ? 'obl-row-expired' : 'obl-row-draft'); ?>">
                                <td>
                                    <div class="action-btns">
                                        <?php if ($can_fill && !$approved): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editOblBtn"
                                               data-id="<?php echo intval($r['id']); ?>"
                                               data-type="<?php echo obl_e($r['obligation_type']); ?>"
                                               data-obligor="<?php echo obl_e($r['obligor']); ?>"
                                               data-effect="<?php echo obl_e($r['effect_on_billing']); ?>"
                                               data-from="<?php echo obl_e($r['valid_from']); ?>"
                                               data-to="<?php echo obl_e($r['valid_to']); ?>"
                                               title="تعديل المسودة"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_approve && !$approved): ?>
                                            <form method="post" action="" class="obl-inline-form"
                                                  onsubmit="return confirm('إجازةُ هذا البند؟ المُجازُ نافذٌ لا يُعدَّل.')">
                                                <input type="hidden" name="approve_action" value="one">
                                                <input type="hidden" name="obligation_id" value="<?php echo intval($r['id']); ?>">
                                                <input type="hidden" name="client_contract_id" value="<?php echo $sel_contract; ?>">
                                                <button type="submit" class="action-btn approve" title="إجازة"><i class="fas fa-stamp"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($can_delete && !$approved): ?>
                                            <a href="?contract=<?php echo $sel_contract; ?>&delete_id=<?php echo intval($r['id']); ?>"
                                               class="action-btn delete"
                                               onclick="return confirm('حذفُ هذه المسودة؟')" title="حذف المسودة"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                        <?php if ($approved): ?>
                                            <span class="obl-lock" title="مُجازٌ ونافذ — لا يُعدَّل (لا رجعية §6)"><i class="fas fa-lock"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong><?php echo obl_e($OBL_TYPES[$r['obligation_type']] ?? $r['obligation_type']); ?></strong></td>
                                <td><span class="obl-obligor obl-obligor-<?php echo obl_e($r['obligor']); ?>">
                                    <?php echo obl_e($OBL_OBLIGORS[$r['obligor']] ?? $r['obligor']); ?></span></td>
                                <td><?php echo obl_e($OBL_EFFECTS[$r['effect_on_billing']] ?? $r['effect_on_billing']); ?></td>
                                <td class="obl-dates">
                                    <?php echo obl_e($r['valid_from']); ?>
                                    <?php echo $r['valid_to'] ? ' ← ' . obl_e($r['valid_to']) : ' ← <span class="obl-muted">مفتوح</span>'; ?>
                                </td>
                                <td>
                                    <?php if ($eff): ?>
                                        <span class="obl-badge obl-badge-eff"><i class="fas fa-circle-check"></i> نافذ</span>
                                    <?php elseif ($approved): ?>
                                        <span class="obl-badge obl-badge-past"><i class="fas fa-clock-rotate-left"></i> مُجازٌ خارجَ المدة</span>
                                    <?php else: ?>
                                        <span class="obl-badge obl-badge-draft"><i class="fas fa-pen"></i> مسودة</span>
                                    <?php endif; ?>
                                </td>
                                <td class="obl-small">
                                    <?php if ($approved): ?>
                                        <?php echo obl_e($r['approver_name'] ?: '—'); ?><br>
                                        <span class="obl-muted"><?php echo obl_e($r['approved_at']); ?></span>
                                    <?php else: ?>
                                        <span class="obl-muted">تنتظر المالية</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card"><div class="card-body obl-empty">
            اختر عقدًا لعرض مصفوفة التزاماته.
        </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
$(function () {
    const oblForm = $('#oblForm');
    const toggleBtn = $('#toggleForm');

    function setAdd() { $('#formTitle').text('إضافة بندِ التزام'); $('#submitBtnText').text('حفظ المسودة'); }
    function setEdit() { $('#formTitle').text('تعديل المسودة'); $('#submitBtnText').text('تحديث المسودة'); }
    function resetForm() { if (!oblForm.length) return; oblForm[0].reset(); $('#obligation_id').val(''); setAdd(); if (window.EmsSelect) EmsSelect.refresh(); }

    toggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!oblForm.length) return;
        if (oblForm.is(':visible')) {
            oblForm.stop(true, true).slideUp(200, function () { oblForm.removeClass('allforms-visible'); resetForm(); });
        } else {
            resetForm();
            oblForm.addClass('allforms-visible').hide().stop(true, true).slideDown(200);
        }
    });
    $('#oblCancelBtn').on('click', function () {
        oblForm.stop(true, true).slideUp(200, function () { oblForm.removeClass('allforms-visible'); resetForm(); });
    });

    $(document).on('click', '.editOblBtn', function () {
        const d = $(this).data();
        $('#obligation_id').val(d.id);
        $('#obligation_type').val(d.type);
        $('#obligor').val(d.obligor);
        $('#effect_on_billing').val(d.effect);
        $('#valid_from').val(d.from);
        $('#valid_to').val(d.to || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEdit();
        if (!oblForm.is(':visible')) { oblForm.addClass('allforms-visible').hide().stop(true, true).slideDown(200); }
        $('html, body').animate({ scrollTop: oblForm.offset().top - 100 }, 400);
    });
});
</script>

<style>
    .obl-main .obl-warning {
        display: flex; gap: 12px; align-items: flex-start;
        border: 1px solid #e0b300; border-right: 5px solid #e0b300; border-radius: 10px;
        background: #fffbe9; padding: 12px 14px; margin-bottom: 14px; line-height: 1.7;
    }
    .obl-main .obl-warning i { color: #b58900; font-size: 1.3rem; margin-top: 3px; }
    .obl-main .obl-coverage { border: 1px solid var(--bdr); border-radius: 12px; padding: 14px; margin-bottom: 14px; background: #fff; }
    .obl-main .obl-coverage.is-full { border-color: #2e7d32; background: #f3fbf3; }
    .obl-main .obl-coverage.is-partial { border-color: #c77700; background: #fff8ef; }
    .obl-main .obl-coverage-head { display: flex; align-items: center; gap: 10px; font-size: 1.02rem; flex-wrap: wrap; }
    .obl-main .obl-coverage-missing { margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .obl-main .obl-coverage-note { margin-top: 8px; color: #666; font-size: .9rem; }
    .obl-main .obl-chip { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .85rem; font-weight: 700; }
    .obl-main .obl-chip-missing { background: #ffe9e0; color: #a33; border: 1px solid #e0a090; }
    .obl-main .obl-chip-draft { background: #eef2ff; color: #3949ab; border: 1px solid #9fa8da; }
    .obl-main .obl-inline-form { display: inline; margin: 0; }
    .obl-main .obl-coverage .obl-inline-form { display: block; margin-top: 10px; }
    .obl-main .obl-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .85rem; font-weight: 700; white-space: nowrap; }
    .obl-main .obl-badge-eff { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
    .obl-main .obl-badge-past { background: #eceff1; color: #546e7a; border: 1px solid #b0bec5; }
    .obl-main .obl-badge-draft { background: #eef2ff; color: #3949ab; border: 1px solid #9fa8da; }
    .obl-main .obl-obligor { font-weight: 700; }
    .obl-main .obl-obligor-client { color: #1565c0; }
    .obl-main .obl-obligor-company { color: #6a1b9a; }
    .obl-main .obl-obligor-supplier { color: #ad1457; }
    .obl-main .obl-obligor-operator { color: #00695c; }
    .obl-main .obl-obligor-none { color: #757575; }
    .obl-main .obl-row-effective { background: #fcfffc; }
    .obl-main .obl-row-expired { opacity: .62; }
    .obl-main .obl-lock { color: #999; padding: 0 6px; }
    .obl-main .obl-empty { text-align: center; color: #888; padding: 22px; }
    .obl-main .obl-muted { color: #999; }
    .obl-main .obl-small { font-size: .86rem; }
    .obl-main .obl-dates { white-space: nowrap; font-variant-numeric: tabular-nums; }
    .obl-main .obl-hint { display: block; color: #888; font-size: .82rem; margin-top: 3px; }
    .obl-main .table-container { overflow-x: auto; }
    .obl-main .action-btn.approve { background: none; border: none; cursor: pointer; }
</style>

</body>

</html>
