<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-18 — اختبار قبول: البوابةُ الشخصية (USR-01 §3 · §6 · §7 · §9 — U4→U8)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/h18_personal_portal_test.php
 *
 * ما يُثبته:
 *   U4 لقطةُ الإنجاز ببصمتها والمؤشراتُ بالعدد والزمن — وقسمُ الحساس مخفيٌّ
 *      عن الموجز إن لم يُمنح.
 *   U5 **التعميمُ العادل**: إداريٌّ بلا وحدات ⇒ «لا ينطبق» لا أصفارًا.
 *   U6 **التقييم**: المديرُ يفتح قبل إقفال الذاتي ⇒ 423 · وبعده يُفتح ·
 *      وفارقُ درجتين بلا تعليق ⇒ 422 · والانتقالاتُ بفحص النسخة (409).
 *   U7 **الشهادة**: لفترةٍ غير مقفلةٍ أو تقييمٍ غيرِ معتمدٍ ⇒ 422 · وبعد
 *      الاعتماد تُصدَر برقمها ورمزها **ولا تُصدَر مرتين** (409/UQ).
 *   U8 صفةٌ مجمَّدة ⇒ الموجزُ 403 «قراءةُ تاريخه فقط».
 *   + عقدُ التغذية: بطاقاتٌ بترويسةٍ موحّدةٍ والنشاطُ يُقيَّد Insert-only.
 *
 * البذرُ معزول بعلامة H18T — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '4', 'company_id' => 4, 'name' => 'H18 portal test');

require_once dirname(__DIR__) . '/app/Services/Portal/PortalFeedService.php';
require_once dirname(__DIR__) . '/app/Services/Portal/AchievementService.php';
require_once dirname(__DIR__) . '/app/Services/Portal/EvaluationService.php';

use App\Services\Portal\PortalFeedService as PFS;
use App\Services\Portal\AchievementService as ACH;
use App\Services\Portal\EvaluationService as EVS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$MARK  = 'H18T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE c FROM achievement_certificates c
                   JOIN achievement_snapshots s ON s.id = c.snap_id
                   JOIN user_capacities uc ON uc.id = s.capacity_id
                   JOIN users u ON u.id = uc.account_id WHERE u.username LIKE '{$MARK}%'");
    $conn->query("DELETE e FROM evaluations e JOIN user_capacities uc ON uc.id = e.capacity_id
                   JOIN users u ON u.id = uc.account_id WHERE u.username LIKE '{$MARK}%'");
    $conn->query("DELETE s FROM achievement_snapshots s JOIN user_capacities uc ON uc.id = s.capacity_id
                   JOIN users u ON u.id = uc.account_id WHERE u.username LIKE '{$MARK}%'");
    $conn->query("DELETE l FROM portal_activity_log l JOIN users u ON u.id = l.account_id
                   WHERE u.username LIKE '{$MARK}%'");
    $conn->query("DELETE c FROM user_capacities c JOIN users u ON u.id = c.account_id
                   WHERE u.username LIKE '{$MARK}%'");
    $conn->query("DELETE FROM visibility_keys WHERE reason LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM users WHERE username LIKE '{$MARK}%'");
    $conn->query("DELETE FROM employees WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-18 — البوابةُ الشخصية: لا تملك بيانًا وتقرأ من مالكيه ══\n");

head('البذر — إداريٌّ بصفته (لا وحداتِ له — لبرهان U5)');

$conn->query("INSERT INTO employees (company_id, name, employee_code, employee_type, created_at)
              VALUES ({$CO}, 'إداريُّ {$MARK}', 'E-{$MARK}', 'permanent', NOW())");
$EMP = intval($conn->insert_id);
$conn->query("INSERT INTO users (name, username, email, password, role, company_id, employee_id, status, created_at)
              VALUES ('إداريُّ {$MARK}', '{$MARK}-adm', '{$MARK}a@t.t', 'x', '5', {$CO}, {$EMP}, 'active', NOW())");
$U = intval($conn->insert_id);
$conn->query("INSERT INTO user_capacities (company_id, person_id, account_id, capacity_type, role,
              scope_type, source_type, source_note, valid_from, state)
              VALUES ({$CO}, {$EMP}, {$U}, 'employee', '5', 'company', 'delegation',
                      'بذر {$MARK}', '2026-01-01', 'active')");
$CAPID = intval($conn->insert_id);
check($U > 0 && $CAPID > 0, "حسابٌ #{$U} بصفةِ موظفٍ #{$CAPID}");

// ═══ عقد التغذية ═══
head('عقدُ التغذية — بطاقاتٌ بترويسةٍ موحّدة والحساسُ محجوبٌ معلَنًا');

$feed = PFS::feed($conn, $gate, $CO, $U, $CAPID);
check($feed['ok'] && count($feed['cards']) > 0,
      '★ الموجزُ قرئ: ' . count($feed['cards']) . ' بطاقة و' . count($feed['hidden_sections']) . ' محجوبًا');
$card = $feed['cards'][0];
check(isset($card['code'], $card['title'], $card['value'], $card['period'])
      && array_key_exists('source_link', $card),
      '★ الترويسةُ الموحّدة: الكودُ والعنوانُ والقيمةُ والفترةُ ورابطُ المصدر (§3)');
$codes = array_map(function ($c) { return $c['code']; }, $feed['cards']);
check(in_array('card.payroll', $codes, true),
      '★ وصاحبُ البيانات يرى بطاقةَ راتبه — الافتراضُ المغلق يحجب الغيرَ لا الذات (§4-①)');

// وإغلاقٌ **صريحٌ** من HR يحجبها حتى عن صاحبها — «مغلقةٌ لهذا الحساب بقرار HR»
require_once dirname(__DIR__) . '/app/Services/Portal/VisibilityPolicyService.php';
\App\Services\Portal\VisibilityPolicyService::setKey($conn, $gate, $CO, array(
    'element_code' => 'card.payroll', 'scope_type' => 'account', 'scope_id' => (string) $U,
    'mode' => 'closed', 'reason' => 'قرارُ HR ' . $MARK), 1);
$feedC = PFS::feed($conn, $gate, $CO, $U, $CAPID);
check(in_array('card.payroll', $feedC['hidden_sections'], true),
      '★★ وبإغلاقٍ صريحٍ من HR **تُحجب حتى عن صاحبها وتُعلَن محجوبةً** — مثالُ §9.2 حرفيًّا');
$act = intval($conn->query("SELECT COUNT(*) n FROM portal_activity_log
                             WHERE account_id={$U} AND action_code='feed'")->fetch_assoc()['n']);
check($act >= 1, '★ وقراءةُ الموجز **قُيّدت في سجل النشاط** (Insert-only)');

// ═══ U4/U5 الإنجاز ═══
head('U4/U5 — لقطةُ الإنجاز ببصمتها والتعميمُ العادل');

$a = ACH::compute($conn, $gate, $CO, $CAPID, '2026-07-01', '2026-07-31', true, $U);
check($a['ok'] && $a['snap_id'] > 0, '★ حُسبت المؤشراتُ ولُقطت (#'. $a['snap_id'] . ')');
$snap = $conn->query("SELECT * FROM achievement_snapshots WHERE id=" . intval($a['snap_id']))->fetch_assoc();
check($snap && strlen((string)$snap['source_fingerprint']) === 40,
      '★ واللقطةُ **ببصمة مصدرها** (sha1) — U4');
check(!empty($a['metrics']['production']['not_applicable']),
      '★★★ **الإنتاجُ للإداري «لا ينطبق»** — لا صفرًا مضلِّلًا (U5 نصًّا)');
check(!empty($a['metrics']['discipline']['not_applicable']),
      'والانضباطُ بلا سجلِّ حضورٍ **يُعلَن ولا يُخترع رقم**');
$bad = ACH::compute($conn, $gate, $CO, $CAPID, '2020-01-01', '2026-01-01', false, $U);
check(!$bad['ok'] && $bad['code'] === 422, 'ومدةٌ تتجاوز سنتين ⇒ **422 تُقسَّم** (§9.2)');

// ═══ U6 التقييم ═══
head('U6 — التقييمُ الثنائي: لا مديرَ قبل إقفال الذاتي والفارقُ بتعليق');

$r = EVS::selfSave($conn, $gate, $CO, $CAPID, '2026-07-01', '2026-07-31',
    array('quality' => 4, 'commitment' => 4.5, 'cooperation' => 5, 'initiative' => 3), $U);
$EV = intval($r['eval_id']);
check($r['ok'] && $EV > 0, 'الذاتيُّ حُفظ (SelfDraft)');

$ev = EVS::head($gate, $EV);
$m = EVS::mgrSubmit($conn, $gate, $CO, $EV, intval($ev['version']),
    array('quality' => 3), '', 888);
check(!$m['ok'] && $m['code'] === 423,
      '★★★ المديرُ قبل إقفال الذاتي ⇒ **يُمنع 423** — منعًا للتأثير (U6 نصًّا)');

$c = EVS::selfClose($conn, $gate, $CO, $EV, intval($ev['version']), $U);
check($c['ok'], 'وأُقفل الذاتي (SelfClosed)');

$ev = EVS::head($gate, $EV);
$m = EVS::mgrSubmit($conn, $gate, $CO, $EV, intval($ev['version']),
    array('quality' => 1.5, 'commitment' => 4), '', 888);
check(!$m['ok'] && $m['code'] === 422,
      '★★ فارقُ 2.5 درجةً بلا تعليق ⇒ **422** — «الفارقُ الكبيرُ يوجب تعليقًا»');
$m = EVS::mgrSubmit($conn, $gate, $CO, $EV, intval($ev['version']),
    array('quality' => 1.5, 'commitment' => 4), 'الجودةُ دون المطلوب بشواهدَ مرفقة', 888);
check($m['ok'], 'وبتعليقٍ يمرّ (MgrDraft)');

$stale = EVS::discuss($conn, $gate, $CO, $EV, 1, 'نقاش', 888);
check(!$stale['ok'] && $stale['code'] === 409,
      '★ ونسخةٌ متقادمة ⇒ **409** — «انتقالاتُ §7 بفحص النسخة»');

$ev = EVS::head($gate, $EV);
$d = EVS::discuss($conn, $gate, $CO, $EV, intval($ev['version']),
    'اتفاقٌ على خطة تطويرٍ خلال شهرين', 888);
check($d['ok'], 'والمناقشةُ سُجّلت (Discussed)');

$ev = EVS::head($gate, $EV);
$selfApprove = EVS::approve($conn, $gate, $CO, $EV, intval($ev['version']), 3.8, $U);
check(!$selfApprove['ok'] && $selfApprove['code'] === 403,
      '★★ وصاحبُ الصفة يعتمد تقييمَه ⇒ **403 لا اعتمادَ للذات**');
$ap = EVS::approve($conn, $gate, $CO, $EV, intval($ev['version']), 3.8, 888);
check($ap['ok'], 'وبيدٍ ثانيةٍ اعتُمد بدرجة 3.8');

// ═══ U7 الشهادة ═══
head('U7 — الشهادةُ من الأرقام المقاسة ولا تُصدَر مرتين');

$c1 = EVS::issueCertificate($conn, $gate, $CO, 0, $EV, 888);
check(!$c1['ok'] && $c1['code'] === 422,
      '★ شهادةٌ بلا لقطةٍ (فترةٌ لم تُقفل) ⇒ **422**');

// تقييمٌ غيرُ معتمدٍ لفترةٍ أخرى
$r2 = EVS::selfSave($conn, $gate, $CO, $CAPID, '2026-06-01', '2026-06-30', array('quality' => 4), $U);
$c2 = EVS::issueCertificate($conn, $gate, $CO, intval($a['snap_id']), intval($r2['eval_id']), 888);
check(!$c2['ok'] && $c2['code'] === 422,
      '★ وشهادةٌ لتقييمٍ غيرِ معتمد ⇒ **422** — «لا تُصدَر لتقييمٍ لم يُعتمد»');

$c3 = EVS::issueCertificate($conn, $gate, $CO, intval($a['snap_id']), $EV, 888);
check($c3['ok'] && preg_match('/^CERT-\d{4}-\d{5}$/', (string)$c3['serial_no'])
      && strlen((string)$c3['verify_code']) === 10,
      '★★★ وبعد الاعتماد صدرت **برقمها ' . $c3['serial_no'] . ' ورمز تحققها**');
$c4 = EVS::issueCertificate($conn, $gate, $CO, intval($a['snap_id']), $EV, 888);
check(!$c4['ok'] && $c4['code'] === 409,
      '★★ وإعادةُ الإصدار بالمفتاح نفسِه ⇒ **409 لا تُصدَر مرتين** (UQ اللقطة)');
$vr = EVS::verify($gate, (string)$c3['verify_code']);
check($vr && (string)$vr['serial_no'] === (string)$c3['serial_no'],
      '★ والتحققُ برمزها يعيد شهادتَها — «يمكن التحقق منه لاحقًا»');

// ═══ U8 الصفة المجمّدة ═══
head('U8 — صفةٌ مجمَّدة: قراءةُ تاريخٍ لا موجزَ حي');

$conn->query("UPDATE user_capacities SET state='frozen',
              state_reason='تجميدُ اختبار {$MARK}', state_at=NOW() WHERE id={$CAPID}");
$feed2 = PFS::feed($conn, $gate, $CO, $U, $CAPID);
check(!$feed2['ok'] && $feed2['code'] === 403,
      '★★ الموجزُ لصفةٍ مجمَّدة ⇒ **403** «قراءةُ تاريخِه فقط بلا إجراءات» (U8)');
$deniedLog = intval($conn->query("SELECT COUNT(*) n FROM portal_activity_log
                                   WHERE account_id={$U} AND result='denied'")->fetch_assoc()['n']);
check($deniedLog >= 1, '★ والرفضُ **مقيَّدٌ في سجل النشاط**');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
