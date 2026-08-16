<?php
/**
 * Workforce/deductions.php — الخصومات والجزاءات (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 09 · القوى التشغيلية · الأعمدة 30 بترتيب المستند وطبقة
 * الحوكمة بشرائحها. الصفوف في المخزن البيني `cmp03_screen_rows` (معزول
 * بالكيان) حتى يولد جدول الشاشة الأصلي — مهمة اللحاق في
 * docs/CMP03_FOLLOWUP_SOURCES_ar.md. الفائض فوق 22 عمودًا منهارٌ (توصية ①).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/gov_columns.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', '');
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي
/* INJ-0219 — سلّمُ الموافقاتِ قناةً واحدةً: هذه الشاشةُ **لا تكتب** «معتمد»
   إطلاقًا؛ يكتبها منفّذُ السلّمِ عند اكتمالِه بحمولةٍ أُنشئت وقتَ الطلب. */
require_once __DIR__ . '/../includes/approval_workflow.php';
require_once __DIR__ . '/../includes/self_approval_guard.php';

$CANONICAL = 'deductions.php';
/* رمزا الفعلَين مسجَّلان في `actions` (الحارسُ enforce) — هجرة 2027_01_29 */
const INJ0219_ACT_REQUEST = 'screen.workforce.deduction.request_approval';
const INJ0219_ACT_APPROVE = 'screen.workforce.deduction.approve_step';

/**
 * بلاغُ نتيجةٍ برمزٍ يطابقها — لا نجاحٌ بلونِ فشلٍ ولا فشلٌ بلونِ نجاح.
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ كُشف بالتصييرِ الحيّ: `inheader.php` يستنتج **لونَ** الرسالةِ من رمزِها
 *   (`-OK-` ⇒ أخضر · غيرُه ⇒ أحمر)، وكانت كلُّ نداءاتِ هذه الشاشةِ تمرّر
 *   `GOV-OK-200` ثابتًا. فظهر «❌ ليس لديك صلاحية…» **أخضرَ نجاحٍ** — والرسالةُ
 *   تقول شيئًا ولونُها يقول نقيضَه، واللونُ أسرعُ إلى العين من النص.
 * ◆ ويشمل الإصلاحُ فعلَ «الإضافة» القائمَ قبلَ هذه الحزمة: «تعذر الحفظ ❌» كان
 *   يخرج أخضرَ هو أيضًا. (السطرُ نفسُه، فتصحيحُه هنا لا تركُه لأنه ليس بندي.)
 */
function inj0219_flash($ok, $message, $hint = '')
{
    ems_gov_flash_redirect(basename(__FILE__), $message,
        $ok ? 'GOV-OK-200' : 'GOV-ERR-409', $hint);
}
$COLS   = array (
  0 => 'رقم القرار',
  1 => 'كود الموظف',
  2 => 'الشهر',
  3 => 'نوع الخصم',
  4 => 'سبب الخصم',
  5 => 'بند السياسة المرجعي',
  6 => 'المستند المؤيد',
  7 => 'الأساس',
  8 => 'المعادلة',
  9 => 'قيمة الخصم',
  10 => 'العملة',
  11 => 'نسبة من الصافي',
  12 => 'اقترحه',
  13 => 'راجعته الموارد',
  14 => 'اعتماد الإدارة',
  15 => 'الاعتماد المالي',
  16 => 'اعتماد الإدارة العامة',
  17 => 'المسيّر',
  18 => 'الحالة',
  19 => 'الكيان',
  20 => 'تاريخ الإنشاء',
  21 => 'تاريخ الاعتماد',
  22 => 'مرجع التفويض',
  23 => 'مفتاح منع التكرار',
  24 => 'درجة الأثر',
  25 => 'معكوس بـ',
  26 => 'عكس عن',
  27 => 'مركز التكلفة',
  28 => 'سعر الصرف ومصدره',
  29 => 'نسخة القاعدة المستعملة',
);
$FIELDS = array (
  0 => 'رقم القرار',
  1 => 'كود الموظف',
  2 => 'الشهر',
  3 => 'نوع الخصم',
  4 => 'سبب الخصم',
  5 => 'بند السياسة المرجعي',
  6 => 'المستند المؤيد',
  7 => 'الأساس',
  8 => 'المعادلة',
  9 => 'قيمة الخصم',
  10 => 'العملة',
  11 => 'نسبة من الصافي',
  12 => 'اقترحه',
  13 => 'راجعته الموارد',
  14 => 'اعتماد الإدارة',
  15 => 'الاعتماد المالي',
  16 => 'اعتماد الإدارة العامة',
  17 => 'المسيّر',
  18 => 'الحالة',
  19 => 'تاريخ الاعتماد',
  20 => 'مرجع التفويض',
  21 => 'درجة الأثر',
  22 => 'مركز التكلفة',
  23 => 'سعر الصرف ومصدره',
  24 => 'نسخة القاعدة المستعملة',
);

/* ── الحفظ: فورم الإضافة الموحد → المخزن البيني ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'add') {
    $payload = array();
    foreach ($FIELDS as $i => $lbl) {
        $v = trim((string) ($_POST['f' . $i] ?? ''));
        if ($v !== '') { $payload[$lbl] = $v; }
    }
    $status = $payload['الحالة'] ?? 'مسودة';
    $creator = trim((string) ($_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . $uid);
    // الموجة ٢: الحفظ في الجدول الأصلي للشاشة (الفارغ NULL — لا مخزن بينيًّا)
    $ok = cmp03_store_insert($conn, $company_id, $CANONICAL, $payload, $status, $uid, $creator);
    /* INJ-0219: الطبقةُ تحطُّ الحالةَ النهائيةَ وتُعلن السبب — يُنقل نصًّا لا يُبتلع */
    $notice = cmp03_store_notice();
    inj0219_flash($ok, $ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌', $notice);
    exit();
}

/* ── INJ-0219 ①: طلبُ الاعتماد — يفتح السلّمَ ولا يعتمد شيئًا ─────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'request_approval') {
    $rowId    = intval($_POST['row_id'] ?? 0);
    $proposal = intval($_POST['proposal_ref'] ?? 0);
    $msg = '';
    $row = $rowId > 0 ? cmp03_store_row($conn, $CANONICAL, $company_id, $rowId) : null;

    if (!$row) {
        $msg = 'صفٌّ غيرُ موجودٍ في كيانك (404)';
    } elseif (cmp03_status_is_terminal($row['status'])) {
        $msg = 'الصفُّ معتمدٌ سلفًا — لا سلّمَ لما تمَّ (409)';
    } elseif ($proposal <= 0) {
        /* «لا خصمَ بلا مستند» (M-11): المقترحُ هو مستندُ هذا القرار، فلا يُطلب
           اعتمادُ خصمٍ لا يشير إلى ما تولَّد عنه. */
        $msg = 'مقترحُ الخصمِ إلزاميٌّ — لا اعتمادَ لخصمٍ بلا مقترحه (422)';
    } else {
        /* المقترحُ من كيانِ المستخدمِ نفسِه — عزلُ الشركاتِ لا يُخترق بمعرّفٍ مُرسَل */
        $st = $conn->prepare('SELECT ded_id, state, proposed_amount FROM deduction_proposals
                               WHERE ded_id = ? AND company_id = ? LIMIT 1');
        $st->bind_param('ii', $proposal, $company_id);
        $st->execute();
        $ded = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$ded) {
            $msg = 'مقترحٌ غيرُ موجودٍ في كيانك (404)';
        } else {
            /* الحمولةُ: ما سيكتبه **منفّذُ السلّمِ** عند الاكتمال لا الشاشة.
               والرموزُ يملؤها المحرّكُ لحظتَها (رقمُ الطلبِ · المعتمِدُ الأخيرُ ·
               اللحظة) — فلا يُكتب سندٌ قبل وجودِه. */
            $payload = array(
                'summary' => array(
                    'table' => 'scr_deductions', 'operation' => 'approve',
                    'old_values' => array('الحالة' => (string) $row['status']),
                    'new_values' => array('الحالة' => 'معتمد', 'مقترح الخصم' => '#' . (int) $ded['ded_id'],
                                          'المبلغ المقترح' => (string) $ded['proposed_amount']),
                ),
                'operations' => array(array(
                    'db_action' => 'update', 'table' => 'scr_deductions',
                    'where' => array('id' => $rowId, 'company_id' => $company_id),
                    'data' => array(
                        'status' => 'معتمد', 'status_label' => 'معتمد',
                        'proposal_ref' => (int) $ded['ded_id'],
                        'approval_request_ref' => '{{request_id}}',
                        'approved_by' => '{{final_approver}}',
                        'approved_at' => '{{now}}',
                        'approved_date' => '{{today}}',
                    ),
                )),
            );
            $res = approval_create_request('scr_deductions', $rowId, 'approve', $payload, $uid, $conn);
            $ok = !empty($res['success']);
            $msg = ($ok ? '✅ ' : '❌ ') . $res['message'];
        }
    }
    inj0219_flash(isset($ok) && $ok, $msg);
    exit();
}

/* ── INJ-0219 ②: اعتمادُ خطوةٍ — يدٌ واحدةٌ لخطوةٍ واحدةٍ وليست يدَ المنشئ ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'approve_step') {
    $rowId  = intval($_POST['row_id'] ?? 0);
    $reqId  = intval($_POST['request_id'] ?? 0);
    $note   = trim((string) ($_POST['note'] ?? ''));
    $msg = '';
    $row = $rowId > 0 ? cmp03_store_row($conn, $CANONICAL, $company_id, $rowId) : null;
    if (!$row || $reqId <= 0) {
        $msg = '❌ صفٌّ أو طلبٌ غيرُ موجودٍ في كيانك (404)';
    } else {
        /* «من أنشأ لا يعتمد» بنيويًّا في الخادم (UI-01 §8) — والمنعُ 403 مسجَّلٌ.
           والحارسُ يقرأ المنشئَ من الصفِّ نفسِه فلا يُمرَّر خطأً. */
        $deny = ems_assert_not_self_approval($conn, 'scr_deductions', 'id', $rowId,
            'قرارُ خصمٍ #' . $rowId . ' (' . (string) ($row['no_decision'] ?? '') . ')', $company_id);
        if ($deny !== null) {
            $msg = '❌ ' . $deny['reason'];
        } else {
            $res = approval_approve_request($reqId, $uid, $conn, $note);
            $ok = !empty($res['success']);
            $msg = ($ok ? '✅ ' : '❌ ') . $res['message'];
        }
    }
    inj0219_flash(isset($ok) && $ok, $msg);
    exit();
}

/* ── القراءة: صفوف الكيان لهذه الشاشة ───────────────────────────────────── */
// الموجة ٢: القراءة من الجدول الأصلي — الشكل القديم نفسه (id·payload·status·…)
$rows = cmp03_store_rows($conn, $CANONICAL, ($is_super_admin && $company_id <= 0) ? 0 : $company_id);

/* ── INJ-0219: سندُ القرارِ وسلّمُه — مقروءان من مصدرِهما لا من نصٍّ حرّ ──────
   أعمدةُ السلسلةِ الخمسةُ في هذه الشاشةِ كانت `varchar(300)` يكتبها المنشئُ بيده،
   فكانت **سلسلةً مرسومة**. صارت الآن **إسقاطًا** لخطواتِ `approval_steps`: من
   وقّع فعلًا ومتى — ومن لم يوقّع فخانتُه «بانتظار …» لا اسمٌ مكتوب.
   ولا عمودَ جديدًا في الجدول: عقدُ الأعمدةِ الثلاثين محفوظٌ كما هو (CMP-03). */
$SPINE = array();   // id ← سندُ القرار
$CHAIN = array();   // id ← الطلبُ وخطواتُه
if ($rows) {
    $ids = array();
    foreach ($rows as $r) { $ids[] = (int) $r['id']; }
    $in = implode(',', array_map('intval', $ids));
    $rs = mysqli_query($conn, "SELECT id, proposal_ref, approval_request_ref, approved_by, approved_at, created_by
                                 FROM scr_deductions WHERE id IN ({$in})");
    while ($rs && ($x = mysqli_fetch_assoc($rs))) { $SPINE[(int) $x['id']] = $x; }

    $rs = mysqli_query($conn,
        "SELECT ar.id, ar.entity_id, ar.status, ar.requested_by, ar.approved_at, ar.payload,
                ru.username AS requester_name,
                s.step_order, s.role_required, s.status AS step_status, s.approved_by AS step_by,
                s.approved_at AS step_at, u.username AS step_user
           FROM approval_requests ar
           JOIN approval_steps s ON s.request_id = ar.id
           LEFT JOIN users u  ON u.id  = s.approved_by
           LEFT JOIN users ru ON ru.id = ar.requested_by
          WHERE ar.entity_type = 'scr_deductions' AND ar.action = 'approve'
            AND ar.entity_id IN ({$in})
          ORDER BY ar.entity_id, ar.id DESC, s.step_order ASC");
    while ($rs && ($x = mysqli_fetch_assoc($rs))) {
        $eid = (int) $x['entity_id'];
        if (!isset($CHAIN[$eid])) {
            /* المقترحُ **المختارُ** يعيش في حمولةِ الطلبِ حتى يكتبَه منفّذُ السلّم.
               وبلا قراءتِه هنا كان اللوحُ يقول «بلا مقترح» عن طلبٍ اختير مقترحُه
               فعلًا — نصٌّ يكذّب الواقعَ وإن كان العمودُ فارغًا بحقّ (كُشف حيًّا). */
            $pl = json_decode((string) $x['payload'], true);
            $pending = null;
            if (is_array($pl) && isset($pl['operations'][0]['data']['proposal_ref'])) {
                $pending = (int) $pl['operations'][0]['data']['proposal_ref'];
            }
            $CHAIN[$eid] = array('id' => (int) $x['id'], 'status' => $x['status'],
                                 'requested_by' => (int) $x['requested_by'],
                                 'requester_name' => $x['requester_name'],
                                 'payload_proposal' => $pending, 'steps' => array());
        }
        if ($CHAIN[$eid]['id'] !== (int) $x['id']) { continue; }   // الأحدثُ وحدَه
        $CHAIN[$eid]['steps'][(int) $x['step_order']] = $x;
    }
}

/** الخطوةُ المعلَّقةُ الأولى — دورُها ومن مشى قبلها (يُقرأ من الخطواتِ لا يُخمَّن). */
function inj0219_pending_step($chain)
{
    if (!$chain) { return null; }
    foreach ($chain['steps'] as $so => $s) {
        if ($s['step_status'] === 'pending') { return array('order' => (int) $so, 'role' => (string) $s['role_required']); }
    }
    return null;
}
/** أمشى هذا المستخدمُ خطوةً في هذا السلّمِ سلفًا؟ (قاعدةُ «لا يدَ تمشي خطوتين») */
function inj0219_already_walked($chain, $uid)
{
    if (!$chain || $uid <= 0) { return false; }
    foreach ($chain['steps'] as $s) {
        if ($s['step_status'] === 'approved' && (int) $s['step_by'] === (int) $uid) { return true; }
    }
    return false;
}

$govCtx = ems_gov_ctx();
$entityName = $govCtx['values']['entity'] ?? '—';

/** خانةُ موقّعٍ من خطوةِ سلّمِها — «بانتظار» لا فراغٌ ولا اسمٌ مُخترَع. */
function inj0219_signer($chain, $stepOrder) {
    if (!$chain || !isset($chain['steps'][$stepOrder])) { return '—'; }
    $s = $chain['steps'][$stepOrder];
    if ($s['step_status'] === 'approved') {
        return ($s['step_user'] !== null ? $s['step_user'] : ('مستخدم #' . (int) $s['step_by']))
             . ' · ' . substr((string) $s['step_at'], 0, 16);
    }
    if ($s['step_status'] === 'rejected') { return '✖ مرفوضة'; }
    return 'بانتظار الدور ' . $s['role_required'];
}

/** قيمة خلية العمود من الصف — الحوكمة الآلية حية وسائرها من الحمولة أو «—» */
function cmp03_cell($col, $row, $entityName, $spine = null, $chain = null) {
    $n = cmp03_screen_norm($col);
    if ($n === cmp03_screen_norm('الكيان')) { return $entityName; }
    if ($n === cmp03_screen_norm('المُنشئ — الاسم والصفة') || $n === cmp03_screen_norm('الجهة المُنشئة')) {
        return $row['created_by_name'] ?: '—';
    }
    if ($n === cmp03_screen_norm('تاريخ الإنشاء')) { return $row['created_at']; }
    if ($n === cmp03_screen_norm('الحالة')) { return $row['status']; }
    if ($n === cmp03_screen_norm('مفتاح منع التكرار')) { return 'CMP03-' . intval($row['id']); }

    /* ── الإسقاطاتُ الحية (INJ-0219) — تسبق الحمولةَ فلا يغلب النصُّ الحرُّ السندَ ── */
    if ($n === cmp03_screen_norm('اقترحه')) {
        /* اسمُ الطالبِ لا رقمُه: عمودٌ يقرؤه المستخدمُ فلا يُعرض فيه معرّفٌ خام
           (كُشف حيًّا: كان «طلبه مستخدم #7»). والرقمُ احتياطٌ إن حُذف الحساب. */
        if (!$chain) { return $row['created_by_name'] ?: '—'; }
        return 'طلبه ' . ($chain['requester_name'] !== null
            ? $chain['requester_name'] : ('مستخدم #' . $chain['requested_by']));
    }
    if ($n === cmp03_screen_norm('راجعته الموارد'))       { return inj0219_signer($chain, 1); }
    if ($n === cmp03_screen_norm('اعتماد الإدارة'))        { return inj0219_signer($chain, 2); }
    if ($n === cmp03_screen_norm('الاعتماد المالي'))       { return inj0219_signer($chain, 3); }
    if ($n === cmp03_screen_norm('اعتماد الإدارة العامة')) {
        return ($chain && $chain['status'] === 'approved') ? 'سلّمٌ مكتملٌ #' . $chain['id'] : '—';
    }
    if ($n === cmp03_screen_norm('مرجع التفويض')) {
        return ($spine && $spine['approval_request_ref'])
            ? ('طلبُ سلّمٍ #' . (int) $spine['approval_request_ref']) : '—';
    }
    if ($n === cmp03_screen_norm('المستند المؤيد')) {
        if ($spine && $spine['proposal_ref']) { return 'مقترحُ خصمٍ #' . (int) $spine['proposal_ref']; }
        return (isset($row['payload'][$col]) && $row['payload'][$col] !== '') ? $row['payload'][$col] : '—';
    }
    if ($n === cmp03_screen_norm('تاريخ الاعتماد')) {
        if ($spine && $spine['approved_at']) { return substr((string) $spine['approved_at'], 0, 16); }
        return (isset($row['payload'][$col]) && $row['payload'][$col] !== '') ? $row['payload'][$col] : '—';
    }

    if (isset($row['payload'][$col]) && $row['payload'][$col] !== '') { return $row['payload'][$col]; }
    return '—';
}

/* المقترحاتُ المتاحةُ للربط — ما لم يُرحَّل بعد (Posted نهايةُ رحلته) */
$OPEN_PROPOSALS = array();
$rsP = mysqli_query($conn,
    "SELECT d.ded_id, d.period, d.source, d.source_ref, d.proposed_amount, d.state,
            COALESCE(e.name, CONCAT('شخص #', d.person_id)) AS person_name
       FROM deduction_proposals d
       LEFT JOIN employees e ON e.id = d.person_id
      WHERE d.company_id = " . intval($company_id) . "
        AND d.state IN ('Proposed','Reviewed','Approved')
      ORDER BY d.ded_id DESC LIMIT 200");
while ($rsP && ($x = mysqli_fetch_assoc($rsP))) { $OPEN_PROPOSALS[] = $x; }
/** تطبيع محلي خفيف (مرآة cmp03_norm دون جر مكتبة الأدوات للويب) */
function cmp03_screen_norm($s) {
    $s = preg_replace('/\s+/u', ' ', trim((string) $s));
    $s = str_replace(array('أ','إ','آ'), 'ا', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace('ى', 'ي', $s);
    return preg_replace('/[ًٌٍَُِّْ]/u', '', $s);
}

$page_title = 'إيكوبيشن | الخصومات والجزاءات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'الخصومات والجزاءات';
    $header_icon = 'fa fa-circle-minus';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — الخصومات والجزاءات</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1650_2d6ba">رقم القرار</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1650_2d6ba"></div>
                <div class="form-group"><label for="emsf_1651_30502">كود الموظف</label>
                    <input type="text" name="f1" maxlength="190" id="emsf_1651_30502"></div>
                <div class="form-group"><label for="emsf_1652_99cec">الشهر</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_1652_99cec"></div>
                <div class="form-group"><label for="emsf_1653_ab52e">نوع الخصم</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_1653_ab52e"></div>
                <div class="form-group"><label for="emsf_1654_30700">سبب الخصم</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1654_30700"></div>
                <div class="form-group"><label for="emsf_1655_ae373">بند السياسة المرجعي</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_1655_ae373"></div>
                <div class="form-group"><label for="emsf_1656_35627">المستند المؤيد</label>
                    <input type="text" name="f6" maxlength="190" id="emsf_1656_35627"></div>
                <div class="form-group"><label for="emsf_1657_d73c6">الأساس</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_1657_d73c6"></div>
                <div class="form-group"><label for="emsf_1658_77501">المعادلة</label>
                    <input type="text" name="f8" maxlength="190" id="emsf_1658_77501"></div>
                <div class="form-group"><label for="emsf_1659_48d7f">قيمة الخصم</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0" id="emsf_1659_48d7f"></div>
                <div class="form-group"><label for="emsf_1660_2ee30">العملة</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_1660_2ee30"></div>
                <div class="form-group"><label for="emsf_1661_ff066">نسبة من الصافي</label>
                    <input type="text" inputmode="decimal" name="f11" placeholder="0" id="emsf_1661_ff066"></div>
                <div class="form-group"><label for="emsf_1662_032f4">اقترحه</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_1662_032f4"></div>
                <div class="form-group"><label for="emsf_1663_5329b">راجعته الموارد</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_1663_5329b"></div>
                <div class="form-group"><label for="emsf_1664_023a3">اعتماد الإدارة</label>
                    <input type="text" name="f14" maxlength="190" id="emsf_1664_023a3"></div>
                <div class="form-group"><label for="emsf_1665_64451">الاعتماد المالي</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_1665_64451"></div>
                <div class="form-group"><label for="emsf_1666_15b60">اعتماد الإدارة العامة</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_1666_15b60"></div>
                <div class="form-group"><label for="emsf_1667_ab63c">المسيّر</label>
                    <input type="text" name="f17" maxlength="190" id="emsf_1667_ab63c"></div>
                <div class="form-group"><label for="emsf_1668_33208">الحالة</label>
                    <?php /* INJ-0219: «معتمد» مرفوعةٌ من منتقي الإنشاء — لا تُنال بمنتقٍ
                              في يدِ المنشئ بل بسلّمِ الموافقاتِ أدناه. والطبقةُ تحطُّ
                              أيَّ حالةٍ نهائيةٍ مُرسَلةٍ يدويًّا، فالمنعُ في الخادمِ
                              والرفعُ من القائمةِ إعلانٌ له لا اكتفاءٌ به. */ ?>
                    <select name="f18" id="emsf_1668_33208" aria-describedby="inj0219_status_hint"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select>
                    <small id="inj0219_status_hint" class="text-muted">«معتمد» لا تُختار — تُكتسب بسلّمِ الموافقاتِ بيدين مختلفتين.</small></div>
                <div class="form-group"><label for="emsf_1669_842f5">تاريخ الاعتماد</label>
                    <input type="date" name="f19" id="emsf_1669_842f5"></div>
                <div class="form-group"><label for="emsf_1670_a4f09">مرجع التفويض</label>
                    <input type="text" name="f20" maxlength="190" id="emsf_1670_a4f09"></div>
                <div class="form-group"><label for="emsf_1671_67ac6">درجة الأثر</label>
                    <input type="text" name="f21" maxlength="190" id="emsf_1671_67ac6"></div>
                <div class="form-group"><label for="emsf_1672_21169">مركز التكلفة</label>
                    <input type="text" inputmode="decimal" name="f22" placeholder="0" id="emsf_1672_21169"></div>
                <div class="form-group"><label for="emsf_1673_940ef">سعر الصرف ومصدره</label>
                    <input type="text" inputmode="decimal" name="f23" placeholder="0" id="emsf_1673_940ef"></div>
                <div class="form-group"><label for="emsf_1674_7fb2a">نسخة القاعدة المستعملة</label>
                    <input type="text" name="f24" maxlength="190" id="emsf_1674_7fb2a"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="deductionsTable">
            <thead><tr>
            <th>رقم القرار</th>
            <th>كود الموظف</th>
            <th>الشهر</th>
            <th>نوع الخصم</th>
            <th>سبب الخصم</th>
            <th>بند السياسة المرجعي</th>
            <th>المستند المؤيد</th>
            <th>الأساس</th>
            <th>المعادلة</th>
            <th>قيمة الخصم</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>نسبة من الصافي</th>
            <th>اقترحه</th>
            <th>راجعته الموارد</th>
            <th>اعتماد الإدارة</th>
            <th>الاعتماد المالي</th>
            <th>اعتماد الإدارة العامة</th>
            <th>المسيّر</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
            <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
            <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
            <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
            <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="30" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
            <?php else: foreach ($rows as $r): $rid = (int) $r['id'];
                    $sp = isset($SPINE[$rid]) ? $SPINE[$rid] : null;
                    $ch = isset($CHAIN[$rid]) ? $CHAIN[$rid] : null; ?>
                <tr<?php echo $r['is_seed'] ? ' data-seed="1"' : ''; ?>>
                    <?php foreach ($COLS as $c): $v = cmp03_cell($c, $r, $entityName, $sp, $ch); ?>
                    <td<?php echo $v === '—' ? ' class="ems-gov-empty"' : ''; ?>><?php echo htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <?php /* ══ INJ-0219 · سلّمُ الاعتماد ══════════════════════════════════════
       ◆ لماذا لوحٌ مستقلٌّ لا عمودُ أفعالٍ في الجدول: عقدُ الأعمدةِ الثلاثين
         مقيسٌ بفاحصٍ (CMP-03)، وزيادةُ عمودٍ تكسره. والقرارُ يُدار من موضعِه
         الطبيعيّ: من ينتظر يدًا، وأيَّ يدٍ ينتظر.
       ◆ ولا يُخفى زرٌّ اكتفاءً بالإخفاء: الخادمُ هو من يفصل (الحارسُ + قاعدةُ
         «لا يدَ تمشي خطوتين» + قيدُ القاعدة) — والإخفاءُ زينةٌ فوق منعٍ قائم. */ ?>
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-list-check"></i> سلّمُ اعتمادِ قراراتِ الخصم
            <small class="text-muted" style="font-weight:400">— ثلاثُ خطواتٍ بثلاثِ أيدٍ: مراجعةُ الموارد ← اعتمادُ الإدارة ← الاعتمادُ المالي</small></h5>
    </div><div class="card-body">
        <?php
        $pending = array();
        foreach ($rows as $r) {
            if (cmp03_status_is_terminal($r['status'])) { continue; }
            if (in_array(cmp03_screen_norm((string) $r['status']), array(cmp03_screen_norm('ملغي')), true)) { continue; }
            $pending[] = $r;
        }
        if (!$pending): ?>
            <p class="text-muted">لا قرارَ خصمٍ ينتظر سلّمًا — كلُّ ما في السجلِّ إما معتمدٌ بسندِه أو ملغى.</p>
        <?php else: ?>
        <table class="table table-sm" data-no-dt>
            <thead><tr><th>#</th><th>رقم القرار</th><th>الحالة</th><th>سندُ القرار</th><th>الفعل</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $r): $rid = (int) $r['id'];
                    $sp = isset($SPINE[$rid]) ? $SPINE[$rid] : null;
                    $ch = isset($CHAIN[$rid]) ? $CHAIN[$rid] : null;
                    $isMine = $sp && intval($sp['created_by']) === $uid && $uid > 0; ?>
              <tr>
                <td><?php echo $rid; ?></td>
                <td><?php echo htmlspecialchars((string) ($r['payload']['رقم القرار'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $r['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="font-size:.88em">
                    <?php if ($sp && $sp['proposal_ref']): ?>
                        مقترحٌ #<?php echo (int) $sp['proposal_ref']; ?>
                    <?php elseif ($ch && !empty($ch['payload_proposal'])): ?>
                        مقترحٌ #<?php echo (int) $ch['payload_proposal']; ?>
                        <span class="text-muted">(مختارٌ في الطلب — يُثبَّت عند اكتمالِ السلّم)</span>
                    <?php else: ?>
                        <span class="text-muted">بلا مقترح</span>
                    <?php endif; ?>
                    <?php if ($ch): ?>
                        · طلبٌ #<?php echo (int) $ch['id']; ?> (<?php echo htmlspecialchars($ch['status'], ENT_QUOTES, 'UTF-8'); ?>)
                        <?php foreach ($ch['steps'] as $so => $s): ?>
                            <br><span style="opacity:.8">خطوة <?php echo (int) $so; ?>: <?php echo htmlspecialchars(inj0219_signer($ch, (int) $so), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td>
                <?php if (!$ch || $ch['status'] === 'rejected'): ?>
                    <form method="post" action="" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <?= csrf_field() ?>
                        <input type="hidden" name="cmp03_action" value="request_approval">
                        <input type="hidden" name="row_id" value="<?php echo $rid; ?>">
                        <select name="proposal_ref" required style="max-width:340px">
                            <option value="">— اختر مقترحَ الخصم (إلزامي) —</option>
                            <?php foreach ($OPEN_PROPOSALS as $p): ?>
                            <option value="<?php echo (int) $p['ded_id']; ?>">#<?php echo (int) $p['ded_id']; ?>
                                · <?php echo htmlspecialchars((string) $p['person_name'], ENT_QUOTES, 'UTF-8'); ?>
                                · <?php echo htmlspecialchars((string) $p['period'], ENT_QUOTES, 'UTF-8'); ?>
                                · <?php echo number_format((float) $p['proposed_amount'], 2); ?>
                                · <?php echo htmlspecialchars((string) $p['state'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-primary"><i class="fa fa-paper-plane"></i> طلبُ الاعتماد</button>
                    </form>
                    <?php if (!$OPEN_PROPOSALS): ?>
                        <small class="text-muted">لا مقترحَ خصمٍ مفتوحًا في كيانك — يُنشأ من مصدرِه (الحضورُ · السلف) لا من هنا.</small>
                    <?php endif; ?>
                <?php elseif ($ch['status'] === 'pending'):
                        /* لا يُعرض زرٌّ لمن لا تنتظره الخطوة. الخادمُ يفصل على أيِّ حال
                           (الدورُ · «لا يدَ تمشي خطوتين» · حارسُ المنشئ · قيدُ القاعدة)،
                           لكن زرًّا يفشل حتمًا هو **وعدٌ كاذب** — كُشف حيًّا: عُرض الزرُّ
                           لدورِ الموارد والخطوةُ المعلَّقةُ لدورِ التشغيل، فردَّ الخادمُ
                           «ليس لديك صلاحية». والصوابُ أن يُعلَن من تنتظره لا أن يُجَرَّب. */
                        $ps = inj0219_pending_step($ch);
                        $roleOk = $ps && ($is_super_admin || in_array((string) ($_SESSION['user']['role'] ?? ''),
                                          array_map('trim', explode(',', $ps['role'])), true));
                        $walked = inj0219_already_walked($ch, $uid); ?>
                    <?php if ($isMine): ?>
                        <span class="text-muted">أنشأتَ هذا القرارَ — الاعتمادُ يدٌ ثانية (UI-01 §8)</span>
                    <?php elseif ($walked): ?>
                        <span class="text-muted">مشيتَ خطوةً في هذا السلّمِ — الباقيةُ ليدٍ أخرى</span>
                    <?php elseif (!$roleOk): ?>
                        <span class="text-muted">الخطوةُ <?php echo $ps ? (int) $ps['order'] : '؟'; ?>
                            تنتظر الدورَ <?php echo $ps ? htmlspecialchars($ps['role'], ENT_QUOTES, 'UTF-8') : '؟'; ?>
                            — لا يدَك</span>
                    <?php else: ?>
                    <form method="post" action="" style="display:flex;gap:6px;align-items:center">
        <?= csrf_field() ?>
                        <input type="hidden" name="cmp03_action" value="approve_step">
                        <input type="hidden" name="row_id" value="<?php echo $rid; ?>">
                        <input type="hidden" name="request_id" value="<?php echo (int) $ch['id']; ?>">
                        <input type="text" name="note" maxlength="190" placeholder="ملاحظةُ الاعتماد">
                        <button type="submit" class="btn-primary"><i class="fa fa-check"></i>
                            اعتمادُ خطوتي (<?php echo (int) $ps['order']; ?>)</button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>
</div>

<script>
(function () {
    var btn = document.getElementById('cmp03AddBtn');
    var form = document.getElementById('cmp03AddForm');
    var cancel = document.getElementById('cmp03CancelBtn');
    if (btn && form) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            form.classList.toggle('allforms-visible');
            if (form.classList.contains('allforms-visible')) {
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    if (cancel && form) {
        cancel.addEventListener('click', function () { form.classList.remove('allforms-visible'); });
    }
})();
</script>
