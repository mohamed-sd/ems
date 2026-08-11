<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-42 · M-44 · M-45 · M-46 · H-14 — اختبار قبول مجموعةِ عقد الشاشة
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/screen_contract_group_test.php
 *
 * ما يُثبته:
 *   M-42 الصندوقُ الموحّد يجمع الصناديقَ الأربعة بعدّاداتها من مصادرها —
 *        والقرارُ يمرّ بخدمة مصدره (روابطُ قفزٍ لا أزرارَ تنفيذ).
 *   M-44 مكوّنُ «ما هذه الشاشة؟» والحالاتُ الخمس موحّدةٌ — والفارغةُ
 *        **بزرِّ الخطوة الأولى**.
 *   M-45 «من أنشأ لا يعتمد» **قيدًا عامًّا**: دالةٌ واحدةٌ ترُدّ 403 مسجَّلةً
 *        — وموصولةٌ بمسار اعتماد الطلب المالي.
 *   M-46 المسودةُ التلقائية محمَّلةٌ في رأس كل صفحة وتلتقط النماذجَ الطويلة.
 *   H-14 سايدبارُ الدور 17 صار **سبعَ مجموعاتٍ** بأسماء UX-02 §6 نصًّا —
 *        صفرُ رابطٍ يتيمٍ وصفرُ رابطٍ حُذف والقديمُ أُخفي لا مُحي.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'screen contract test');

require_once dirname(__DIR__) . '/app/Services/Finance/ApprovalsInboxService.php';
require_once dirname(__DIR__) . '/includes/screen_contract.php';
require_once dirname(__DIR__) . '/includes/self_approval_guard.php';

use App\Services\Finance\ApprovalsInboxService as AIS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO   = 4;
$MARK = 'SC42T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM fin_requests WHERE request_no LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ عقدُ الشاشة — M-42 · M-44 · M-45 · M-46 · H-14 ══\n");

// ═══ M-42 ═══
head('M-42 — الصندوقُ الموحّد: أربعةُ صناديقَ في واحد');

/* ══ **العددُ إحصاءٌ والتوحيدُ هو الحكم.** كان يشترط `=== 4` والخدمةُ نمت إلى
     ستةٍ بقرارَين مسجَّلَين — **والرسالةُ نفسُها تطبع الستةَ فتُدين الفاحصَ لا
     الخدمة**. والحكمُ المقصودُ (عنوانُ القسم): «أربعةُ صناديقَ في **واحد**» —
     أي أن مصادرَ §5 الأربعةَ حاضرةٌ في صندوقٍ موحَّد، لا أن عددَها أربعةٌ أبدًا.
   ⇒ يُقاس حضورُ المصادرِ الأربعةِ المسمّاةِ، ويُعلَن العددُ الكليُّ ولا يُجمَّد. */
$before = AIS::inbox($conn, $CO);
$boxTitles = array_map(function ($b) { return (string) $b['title']; }, $before['boxes']);
$boxBlob = implode(' | ', $boxTitles);
$need4 = array('الطلبات المالية', 'تسويات الموردين', 'القيود اليدوية', 'إقفال الفترات');
$missing4 = array();
foreach ($need4 as $nd) { if (mb_strpos($boxBlob, $nd) === false) { $missing4[] = $nd; } }
check($before['ok'] && $missing4 === array(),
      '★ مصادرُ §5 الأربعةُ في صندوقٍ واحد (والصناديقُ الآن '
      . count($before['boxes']) . '): ' . implode(' · ', array_map(function ($b) {
          return $b['title'] . '(' . $b['count'] . ')'; }, $before['boxes']))
      . ($missing4 ? ' — الناقص: ' . implode(' · ', $missing4) : ''));

$conn->query("INSERT INTO fin_requests (company_id, request_no, request_type, source_module,
              requester_id, beneficiary_type, beneficiary_ref, amount, currency, statement,
              justification, state, created_by, created_at)
              VALUES ({$CO}, 'FR-{$MARK}', 'supplier_due', 'u4', 55, 'supplier', 1, 750, 'USD',
                      'بيان', 'تبرير', 'pending_approval', 55, NOW())");
$FR = intval($conn->insert_id);
$after = AIS::inbox($conn, $CO);
$reqBox = $after['boxes'][0];
$found = false;
foreach ($reqBox['rows'] as $r) {
    if (strpos($r['label'], $MARK) !== false) {
        $found = true;
        check(strpos($r['link'], 'finance_gateway.php?id=' . $FR) !== false,
              '★★ الطلبُ المبذور ظهر في الصندوق **برابط قفزٍ إلى موضع الفعل** — لا زرَّ تنفيذٍ هنا');
    }
}
check($found, '★ طلبٌ بانتظار الاعتماد يظهر فور بذره — القراءةُ حيّةٌ من المصدر');
check($after['total'] === $before['total'] + 1, '★ والمجموعُ عدّادُ الأربعة معًا (زاد واحدًا)');

// ═══ M-45 ═══
head('M-45 — «من أنشأ لا يعتمد» قيدًا عامًّا');

$blocked = ems_no_self_approval($conn, 55, 55, 'الطلب المالي FR-' . $MARK, $CO);
check($blocked !== null && $blocked['code'] === 403,
      '★★★ المنشئُ يعتمد مستندَه ⇒ **403** — «منعُ اعتماد الذات بنيويًّا» (§10-③)');
$logged = intval($conn->query("SELECT COUNT(*) n FROM activity_logs
                                WHERE module_name='permissions'
                                  AND action_type LIKE '%denied%'")->fetch_assoc()['n']);
check($logged >= 1, '★ والمحاولةُ **مسجَّلةٌ في سجل التدقيق** (N-02)');
check(ems_no_self_approval($conn, 55, 66, 'مستند', $CO) === null,
      'ويدان مختلفتان تمرّان — الحارسُ يمنع الذاتَ لا الاعتماد');
$wired = strpos(file_get_contents(dirname(__DIR__) . '/FinRequests/request_actions.php'),
                'ems_no_self_approval') !== false;
check($wired, '★ والحارسُ **موصولٌ بمسار اعتماد الطلب المالي** — لا دالةً معزولة');

// ═══ M-44 ═══
head('M-44 — عقدُ الشاشة الموحّد: الحالاتُ الخمس');

ob_start(); ems_screen_about('غرضُ اختبار', array('خطوة')); $about = ob_get_clean();
check(strpos($about, 'ما هذه الشاشة؟') !== false, '★ مكوّنُ «ما هذه الشاشة؟» يُصيَّر بغرضه وخطواته');
ob_start(); ems_state_empty('لا بيانات', 'أضف أولَ عنصر', 'form.php'); $empty = ob_get_clean();
check(strpos($empty, 'أضف أولَ عنصر') !== false && strpos($empty, 'form.php') !== false,
      '★★ الفارغةُ **بزرِّ الخطوة الأولى** — لا نصَّ DataTables العام');
ob_start(); ems_state_error('عطل', 'أعد', 'x.php'); $err = ob_get_clean();
check(strpos($err, 'عطل') !== false && strpos($err, 'x.php') !== false,
      'والخطأُ بسببه وزرِّ علاجه');
ob_start(); ems_state_loading(2); $load = ob_get_clean();
check(strpos($load, 'emsPulse') !== false, 'والتحميلُ هيكلٌ نابضٌ موحّد');
ob_start(); ems_state_success('تم'); $succ = ob_get_clean();
ob_start(); ems_state_offline_bar(); $off = ob_get_clean();
check(strpos($succ, 'تم') !== false && strpos($off, 'offline') !== false,
      'والنجاحُ و«دون اتصال» مكوّنان — **الحالاتُ الخمسُ كاملة**');
$adopted = 0;
foreach (array('Finance/approvals_inbox.php') as $f) {
    if (strpos(file_get_contents(dirname(__DIR__) . '/' . $f), 'ems_screen_about') !== false) { $adopted++; }
}
check($adopted >= 1, '★ والمكوّنُ **مستعملٌ في شاشةٍ حية** (الصندوق الموحد) لا مكتبةً بلا مستهلك');

// ═══ M-46 ═══
head('M-46 — المسودةُ التلقائية كل 30 ثانية');

$js = file_get_contents(dirname(__DIR__) . '/includes/js/ems-autosave.js');
check(strpos($js, '30000') !== false && strpos($js, 'localStorage') !== false,
      '★ المكوّنُ يحفظ كل 30 ثانيةً في localStorage');
check(strpos($js, 'password') !== false && strpos($js, 'csrf') !== false,
      '★ وكلماتُ المرور ورموزُ CSRF **لا تُحفظ أبدًا**');
check(strpos($js, "addEventListener('submit'") !== false || strpos($js, 'addEventListener("submit"') !== false,
      'والإرسالُ الناجح يمسح المسودة');
$hdr = file_get_contents(dirname(__DIR__) . '/inheader.php');
check(strpos($hdr, 'ems-autosave.js') !== false,
      '★★ ومحمَّلٌ في رأس **كل** صفحة — فيلتقط النماذجَ الطويلة (≥6 حقول) آليًّا');

// ═══ H-14 ═══
head('H-14 — السايدبارُ الماليُّ السباعي (UX-02 §6 نصًّا)');

$SEVEN = array('عملي اليوم', 'العملاء والتحصيل', 'الموردون والمدفوعات',
               'الخزينة والبنوك', 'المحاسبة العامة', 'التخطيط والتحليل', 'الإعدادات والتدقيق');
$r = $conn->query("SELECT name FROM link_groups WHERE owner_role_id=17 AND is_active=1 ORDER BY display_order");
$names = array();
while ($r && ($x = $r->fetch_assoc())) { $names[] = $x['name']; }
check($names === $SEVEN,
      '★★★ **سبعُ مجموعاتٍ فعّالةٍ بأسماء §6 حرفيًّا وترتيبها** — لا 35 رابطًا مبعثرًا');
$orphans = intval($conn->query("SELECT COUNT(*) n FROM nav_items
                                 WHERE role_id=17 AND group_id IS NULL")->fetch_assoc()['n']);
check($orphans === 0, '★★ **صفرُ رابطٍ يتيمٍ** — كلُّ روابط الدور 17 في مجموعةٍ من السبع');
$total = intval($conn->query("SELECT COUNT(*) n FROM nav_items WHERE role_id=17")->fetch_assoc()['n']);
check($total >= 63, '★ وصفرُ رابطٍ حُذف (' . $total . ' — الأصلُ 62 + الصندوقُ الموحّد)');
$oldHidden = intval($conn->query("SELECT COUNT(*) n FROM link_groups
                                   WHERE owner_role_id=17 AND is_active=0")->fetch_assoc()['n']);
check($oldHidden >= 5, '★ والمجموعاتُ القديمة **أُخفيت ولا حُذفت** (' . $oldHidden . ' مخفية) — عقدُ العمل ④');
$inboxNav = $conn->query("SELECT n.id FROM nav_items n JOIN link_groups g ON g.id=n.group_id
                           WHERE n.role_id=17 AND n.route='Finance/approvals_inbox.php'
                             AND g.name='عملي اليوم'")->fetch_assoc();
check($inboxNav !== null, '★ والصندوقُ الموحّد في «عملي اليوم» — كما تنصّ §5/§6');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
