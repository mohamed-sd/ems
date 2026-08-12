<?php
/**
 * tests/budget_cycle_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حارس دورة الموازنة: رفعٌ من الإدارات وإجازةٌ من المالية
 * (UX-02 §5 دورة ⑥ · SPEC-01 §20 · الدستور §4.3 الثلاثيةُ الموحّدة).
 *
 * قرارات المالك (2026-07-27): مديرُ الإدارة وحده يرفع · المدير المالي (19) وحده
 * يُجيز · وكلُّ إدارةٍ ترى موازنتَها وحدها.
 *
 * الحرّاسُ الستة:
 *   ① الخريطة: الدورُ ← أقسامُه من جدول توجيه الطلبات (مصدرٌ واحد)
 *   ② الرفع: مديرُ القسم فقط · من مسودةٍ أو معادةٍ فقط
 *   ③ الإجازة: المدير المالي فقط · من مرفوعةٍ فقط
 *   ④ الإعادة: بسببٍ **إلزامي** — ولا تُقبل بلا سبب
 *   ⑤ فصلُ اليدين: المُجيزُ لا يملك رفعًا أصلًا (بنيويًّا لا بفحصٍ لاحق)
 *   ⑥ النطاق: مديرُ قسمٍ لا يمسّ قسمًا غيره
 *
 * يبذر موازناتِه ويكنسها — لا يمسّ موازنتك القائمة.
 * التشغيل: php tests/budget_cycle_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '19', 'company_id' => 4, 'name' => 'budget cycle test');
require_once dirname(__DIR__) . '/Finance/fin_helpers.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$CO = 4;
$MARK = 'BGC' . getmypid();
$seeded = array();

$mk = function ($dept, $state = 'draft') use ($conn, $CO, $MARK, &$seeded) {
    static $i = 0; $i++;
    $no = 'TST-BG-' . $MARK . '-' . $i;
    $fy = 2090 + $i;                    // سنواتٌ بعيدةٌ لا تلامس موازناتك
    /* ◆ **المنشئُ يجب أن يتمايز عن يدِ الجلسة.** كانت البذرةُ تكتب
         `created_by = 1` وجلسةُ الفاحصِ هي المستخدمُ **1** نفسُه — فحارسُ «من
         أنشأ لا يعتمد» يرفض كلَّ إجازةٍ **بحقّ**، فسقطت ستةُ فحوصٍ تقيس
         الإجازةَ وهي سليمةٌ في المنتج. فمنشئٌ مِسباريٌّ متمايزٌ (999801) يجعل
         الفاحصَ يقيس بوابةَ الإدارةِ المالية لا حارسَ الذات. */
    $st = $conn->prepare("INSERT INTO fin_budgets (company_id, budget_no, dept_module, period_type,
              fiscal_year, total_revenue, total_expense, state, created_by, created_at)
              VALUES (?, ?, ?, 'annual', ?, 1000, 2000, ?, 999801, NOW())");
    $st->bind_param('issis', $CO, $no, $dept, $fy, $state);
    $st->execute();
    $id = $conn->insert_id;
    $st->close();
    $seeded[] = $id;
    return $id;
};
$cleanup = function () use ($conn, &$seeded) {
    if ($seeded) {
        $ids = implode(',', array_map('intval', $seeded));
        $conn->query("DELETE FROM fin_budget_lines WHERE budget_id IN ({$ids})");
        $conn->query("DELETE FROM fin_budgets WHERE id IN ({$ids})");
    }
};
register_shutdown_function($cleanup);
$stateOf = function ($id) use ($conn) {
    $r = $conn->query("SELECT * FROM fin_budgets WHERE id=" . intval($id))->fetch_assoc();
    return $r ? $r : array();
};

// ═══ ① الخريطة ════════════════════════════════════════════════════════════
head('① الدورُ ← أقسامُه من جدول توجيه الطلبات');

$mnt = fin_budget_dept_scope('13', false);
check(is_array($mnt) && in_array('maintenance', $mnt, true), 'مديرُ الصيانة (13) يملك قسم maintenance');
check(!in_array('workforce', $mnt, true), 'ولا يملك قسم workforce');

$proc = fin_budget_dept_scope('16', false);
check(in_array('procurement', $proc, true) && in_array('warehouse', $proc, true),
    'ومديرُ المشتريات (16) يملك قسمَين: procurement وwarehouse');

check(fin_budget_dept_scope('19', false) === null, 'والمدير المالي (19) نطاقُه null = الكلُّ (مُجيز)');
check(fin_budget_dept_scope('-1', true) === null, 'والسوبر كذلك');
check(fin_budget_dept_scope('14', false) === array(),
    'ومشرفُ الصيانة (14) لا قسمَ له — الرفعُ للمدير وحده (قرار المالك)');

// ═══ ② الرفع ══════════════════════════════════════════════════════════════
head('② الرفع: مديرُ القسم · من مسودةٍ أو معادة');

$b1 = $mk('maintenance', 'draft');
$r = fin_budget_transition($conn, $b1, 'submit', '13', 55, false);
check($r['status'] === 'ok', 'مديرُ الصيانة يرفع موازنةَ الصيانة');
$row = $stateOf($b1);
check($row['state'] === 'submitted', 'وحالتُها صارت «مقدَّمة»');
check(intval($row['submitted_by']) === 55 && !empty($row['submitted_at']),
    '★ ومَن رفعها ومتى مسجَّلان (كانا مفقودَين)');

$r = fin_budget_transition($conn, $b1, 'submit', '13', 55, false);
check($r['status'] === 'state', 'ورفعُ المرفوعة يُرفض بحالتها');

$b2 = $mk('workforce', 'draft');
$r = fin_budget_transition($conn, $b2, 'submit', '13', 55, false);
check($r['status'] === 'denied', '★ ومديرُ الصيانة لا يرفع موازنةَ الموارد البشرية');
check($stateOf($b2)['state'] === 'draft', 'وتبقى مسودةً كما هي');

$r = fin_budget_transition($conn, $b2, 'submit', '14', 56, false);
check($r['status'] === 'denied', 'ومشرفُ الصيانة (14) لا يرفع شيئًا');

// ═══ ③ الإجازة ════════════════════════════════════════════════════════════
head('③ الإجازة: الإدارةُ المالية · من مرفوعةٍ فقط');

$r = fin_budget_transition($conn, $b1, 'approve', '13', 55, false);
check($r['status'] === 'denied', '★ مديرُ الصيانة لا يُجيز موازنتَه (لا اعتمادَ ذات)');
check($stateOf($b1)['state'] === 'submitted', 'وتبقى مرفوعةً تنتظر');

// اتّسع البابُ بقرار المالك 2026-07-28: الإدارةُ المالية رئيسًا ومعاونين — بعد
// أن كان الدورَ 19 وحده، فكان المديرُ الماليُّ يفتح الشاشةَ ولا يرى زرًّا.
foreach (array('17', '18', '20', '21', '22') as $rf) {
    check(fin_budget_is_approver($rf, false) === true,
        "الدورُ الماليُّ {$rf} صار مُجيزًا (التوسعة)");
}
check(fin_budget_is_approver('13', false) === false, 'ومديرُ الصيانة ليس منهم');
check(fin_budget_is_approver('16', false) === false, 'ولا مديرُ المشتريات');

// وهذا فحصٌ حتميٌّ للاشتقاق: القائمةُ من `roles.parent_role_id` لا من نصٍّ مكتوب
/* ══ **الفحصُ كان ينقض نفسَه**: يشترط أن القائمةَ «مشتقَّةٌ بالتبعية لا مكتوبةٌ
     يدويًّا» — **ويكتبها يدويًّا** بستةِ أرقامٍ مجمَّدة. وأسرةُ المالية نمت
     بـ`update0013` (31 رئيسُ الحسابات · 34 منفذُ المدفوعاتِ البنكية ·
     35 معدُّ المطابقةِ البنكية) فصار الأبناءُ ثمانيةً — فرسبت القائمةُ
     الصحيحةُ على قائمةٍ متجاوَزة.
   ⇒ يُقاس **بالقاعدةِ نفسِها**: الأبُ 17 وكلُّ أبنائه في `roles` — فمن يُضاف
     غدًا يدخل بلا تعديلِ فاحص، وهو عينُ ما يدّعيه الفحص. */
$__ap = fin_budget_approver_roles();
sort($__ap);
$__want = array(strval(EMS_ROLE_FINANCE_DEPT));
$__kids = $conn->query('SELECT id FROM roles WHERE parent_role_id = ' . intval(EMS_ROLE_FINANCE_DEPT));
while ($__kids && ($__k = $__kids->fetch_row())) { $__want[] = strval($__k[0]); }
sort($__want);
check($__ap === $__want,
    '★ والقائمةُ مشتقَّةٌ بالتبعية (17 وأبناؤه = ' . count($__want) . ' دورًا) لا مكتوبةً يدويًّا');

$r = fin_budget_transition($conn, $b1, 'approve', '18', 60, false);
check($r['status'] === 'ok', '★ ومعاونٌ ماليٌّ (18) يُجيز فعلًا لا شكلًا — '
    . $r['status'] . ($r['reason'] !== '' ? ': ' . $r['reason'] : '')
    . ' · حالُ الموازنة قبله: ' . $stateOf($b1)['state']);
check($stateOf($b1)['state'] === 'approved', 'فصارت معتمدة');

// تُعاد إلى «مرفوعة» لإكمال بقية الفحوص على الحالة نفسها
$conn->query("UPDATE fin_budgets SET state='submitted', approved_by=NULL, approved_at=NULL WHERE id=" . intval($b1));

$r = fin_budget_transition($conn, $b1, 'approve', '19', 74, false);
check($r['status'] === 'ok', 'والمدير المالي (19) يُجيز');
$row = $stateOf($b1);
check($row['state'] === 'approved', 'فتصير «معتمدة»');
check(intval($row['approved_by']) === 74 && !empty($row['approved_at']), 'ومُجيزُها ووقتُها مسجَّلان');

$b3 = $mk('procurement', 'draft');
$r = fin_budget_transition($conn, $b3, 'approve', '19', 74, false);
check($r['status'] === 'state', '★ ومسودةٌ لم تُرفع لا تُجاز (الثغرةُ القديمة سُدّت)');

// ═══ ④ الإعادة بسبب ═══════════════════════════════════════════════════════
head('④ الإعادة: بسببٍ إلزامي');

$b4 = $mk('assets', 'draft');
fin_budget_transition($conn, $b4, 'submit', '3', 57, false);
check($stateOf($b4)['state'] === 'submitted', 'مديرُ الأسطول رفع موازنتَه');

$r = fin_budget_transition($conn, $b4, 'return', '19', 74, false, '');
check($r['status'] === 'denied' && strpos($r['reason'], 'سبب') !== false,
    '★ إعادةٌ بلا سببٍ تُرفض');
check($stateOf($b4)['state'] === 'submitted', 'وتبقى مرفوعة');

$r = fin_budget_transition($conn, $b4, 'return', '19', 74, false, 'بند الوقود مبالغٌ فيه — راجع متوسط الاستهلاك');
check($r['status'] === 'ok', 'وبسببٍ تُقبل');
$row = $stateOf($b4);
check($row['state'] === 'returned', 'فتصير «معادة»');
check(strpos((string)$row['return_reason'], 'الوقود') !== false, '★ والسببُ محفوظٌ نصًّا لتقرأه الإدارة');
check(intval($row['returned_by']) === 74 && !empty($row['returned_at']), 'ومن أعادها ومتى');

$r = fin_budget_transition($conn, $b4, 'submit', '3', 57, false);
check($r['status'] === 'ok' && $stateOf($b4)['state'] === 'submitted',
    'والمعادةُ تُرفع ثانيةً بعد الاستكمال — بالرقم نفسه');

// ═══ ⑤ فصلُ اليدين بنيويًّا ════════════════════════════════════════════════
head('⑤ فصلُ اليدين');

check(fin_budget_can_submit('19', 'maintenance', false) === false,
    '★ المُجيزُ لا يملك رفعًا لأي قسم — الفصلُ بنيويٌّ لا بفحصٍ لاحق');
check(fin_budget_can_submit('13', 'maintenance', false) === true, 'ومديرُ القسم يملك رفعَ قسمه');

// ★ الحارسُ الذي لزم مع اتّساع الباب: المديرُ الماليُّ (17) يملك الإيراداتِ
// والخزينةَ والعام — فلو أجازها لعاد «مَن يُعدّ يعتمد» من بابٍ آخر.
check(fin_budget_self_owned('17', 'revenue', false) === true
   && fin_budget_self_owned('17', 'treasury', false) === true
   && fin_budget_self_owned('17', 'general', false) === true,
    '★ المديرُ الماليُّ ممنوعٌ من أقسامه الثلاثة');
check(fin_budget_self_owned('17', 'maintenance', false) === false,
    'ومسموحٌ له في أقسام غيره');
check(fin_budget_self_owned('19', 'revenue', false) === false,
    'ومديرُ الإدارة المالية (19) لا يملك قسمًا فيُجيزها كلَّها');

$b5 = $mk('revenue', 'draft');
$r = fin_budget_transition($conn, $b5, 'submit', '17', 61, false);
check($r['status'] === 'ok', 'المديرُ الماليُّ يرفع موازنةَ الإيرادات (قسمُه)');
$r = fin_budget_transition($conn, $b5, 'approve', '17', 61, false);
check($r['status'] === 'denied' && strpos($r['reason'], 'تديره') !== false,
    '★★ ثم لا يُجيزها هو — بالفعل لا بالشكل');
check($stateOf($b5)['state'] === 'submitted', 'وتبقى مرفوعةً تنتظر غيرَه');
$r = fin_budget_transition($conn, $b5, 'return', '17', 61, false, 'سبب');
check($r['status'] === 'denied', 'ولا يُعيدها كذلك (المنعُ يشمل الإعادة)');
$r = fin_budget_transition($conn, $b5, 'approve', '19', 74, false);
check($r['status'] === 'ok' && $stateOf($b5)['state'] === 'approved',
    'ويُجيزها الدور 19 — فلا طريقَ مسدود');

// ═══ ⑥ الحراسة العامة ═════════════════════════════════════════════════════
head('⑥ حراسةُ المدخلات');

check(fin_budget_transition($conn, 0, 'submit', '13', 55, false)['status'] === 'failed', 'معرّفٌ غير صالح');
check(fin_budget_transition($conn, 99999999, 'submit', '13', 55, false)['status'] === 'failed', 'موازنةٌ غير موجودة');
check(fin_budget_transition($conn, $b1, 'nonsense', '19', 74, false)['status'] === 'failed', 'إجراءٌ غير معروف');
check(in_array('returned', array_keys(fin_budget_states()), true), 'وحالةُ «معادة» معرَّفةٌ في القاموس');
check(fin_budget_editable_states() === array('draft', 'returned'),
    'والبنودُ تُحرَّر في المسودة والمعادة فقط — تُقفل بعد الرفع');


// ═══ ⑦ الرؤيةُ مفصولةٌ عن الملكية (أُضيف 2026-07-27) ══════════════════════
head("⑦ الرؤيةُ رقابةٌ · والملكيةُ رفعٌ");

foreach (array("17","18","19","20","21","22") as $fr) {
    check(fin_budget_dept_scope($fr, false) === null,
        "دورُ المالية {$fr} يرى كلَّ الموازنات رقابةً");
}
check(fin_budget_dept_scope("13", false) === array("maintenance"),
    "ومديرُ الصيانة يرى قسمَه وحده");
check(fin_budget_owned_depts("17") === array("revenue","treasury","general"),
    "والماليةُ تملك أقسامَها الثلاثة رفعًا");
check(fin_budget_owned_depts("19") === array(),
    "★ والمُجيزُ لا يملك قسمًا — يرى الكلَّ ولا يرفع شيئًا");
check(fin_budget_can_submit("17", "revenue", false) === true
   && fin_budget_can_submit("17", "maintenance", false) === false,
    "فالماليةُ ترفع الإيراداتِ ولا ترفع الصيانة");
check(fin_budget_can_submit("18", "revenue", false) === false,
    "والمحاسبُ يرى ولا يرفع");

// ── النظافة ───────────────────────────────────────────────────────────────
$before = (int) $conn->query("SELECT COUNT(*) FROM fin_budgets")->fetch_row()[0];
$sown   = count($seeded);          // العددُ محسوبٌ لا مكتوب — يزيد كلما زاد الفحص
$cleanup();
$seeded = array();
$after = (int) $conn->query("SELECT COUNT(*) FROM fin_budgets")->fetch_row()[0];
check($after === $before - $sown, "كُنست الموازناتُ الـ{$sown} المبذورة");
check((int) $conn->query("SELECT COUNT(*) FROM fin_budgets WHERE budget_no LIKE 'TST-BG-%'")->fetch_row()[0] === 0,
    'ولا موازنةَ اختباريةً متبقية');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
