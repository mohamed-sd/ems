<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-16 — اختبار قبول: منظومةُ الظهور (ADM-01 §2 · §4 — الحالات D1–D5 حرفيًّا)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/h16_visibility_test.php
 *
 * ما يُثبته:
 *   D1 **الجماعي**: إغلاقُ عنصرٍ لفئةٍ ⇒ مفتاحٌ واحدٌ + سجلٌّ بعدد المتأثرين
 *      + القرارُ لأهل الفئة مغلق.
 *   D2 **الحساس**: فتحُ حساسٍ بلا مدة ⇒ **422 ولا يُحفظ**.
 *   D3 **الأولوية**: حسابٌ مفتوحٌ فرديًّا وفئتُه مغلقة ⇒ **مفتوحٌ بمصدر account**.
 *   D4 **منعُ الذات**: مسؤولٌ يمنح نفسَه حساسًا ⇒ **403 ومحاولةٌ مسجَّلة**.
 *   D5 **الانتهاء**: منحةٌ منتهيةُ المدة ⇒ **تُغلق آليًّا** + `grant_expired` في السجل.
 *   + ما ليس في القاموس: الضبطُ 422 والقرارُ «لا يُصيَّر» · والسببُ إلزاميٌّ
 *     لغير الموروث (CHECK + خدمة) · وسياسةُ الحساس الافتراضية **مغلق**.
 *
 * البذرُ معزول بعلامة H16T — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '4', 'company_id' => 4, 'name' => 'H16 visibility test');

require_once dirname(__DIR__) . '/app/Services/Portal/VisibilityPolicyService.php';

use App\Services\Portal\VisibilityPolicyService as VPS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$HR    = 999160; // فاعلُ الموارد البشرية
$MARK  = 'H16T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM visibility_keys WHERE reason LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM visibility_audit_log WHERE reason LIKE '%{$MARK}%'
                     OR element_code LIKE 'test.{$MARK}%'");
    $conn->query("DELETE k FROM visibility_keys k
                   JOIN users u ON CAST(k.scope_id AS UNSIGNED) = u.id
                  WHERE k.scope_type='account' AND u.username LIKE '{$MARK}%'");
    $conn->query("DELETE c FROM user_capacities c JOIN users u ON u.id = c.account_id
                   WHERE u.username LIKE '{$MARK}%'");
    $conn->query("DELETE FROM users WHERE username LIKE '{$MARK}%'");
    $conn->query("DELETE FROM portal_elements WHERE element_code LIKE 'test.{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-16 — منظومةُ الظهور: من يرى ماذا عن مَن ══\n");

head('البذر — حسابُ مشغّلٍ بصفته (H-15) وقاموسٌ مبذور');

$conn->query("INSERT INTO users (name, username, email, password, role, company_id, status, created_at)
              VALUES ('مشغّلُ {$MARK}', '{$MARK}-op', '{$MARK}o@t.t', 'x', '5', {$CO}, 'active', NOW())");
$U_OP = intval($conn->insert_id);
$conn->query("INSERT INTO user_capacities (company_id, account_id, capacity_type, role,
              scope_type, scope_id, source_type, source_note, valid_from, state)
              VALUES ({$CO}, {$U_OP}, 'operator', '5', 'company', NULL, 'delegation',
                      'بذرُ {$MARK}', '2026-01-01', 'active')");
$el = VPS::element($conn, 'card.payroll');
check($U_OP > 0 && $el && (string)$el['sensitivity'] === 'sensitive'
      && (string)$el['default_mode'] === 'closed',
      '★ القاموسُ مبذورٌ و`card.payroll` **حساسٌ مغلقٌ افتراضًا** (السياسةُ المحافظة §5)');

$OPCTX = array('account_id' => $U_OP, 'capacity_type' => 'operator');

// ما لا سياسةَ له: الحساسُ مغلقٌ والعاديُّ على افتراضه
$d0 = VPS::decide($conn, $gate, $CO, 'card.payroll', $OPCTX);
check(!$d0['visible'] && $d0['source'] === 'element_default',
      '★ بلا أي مفتاحٍ: الراتبُ **لا يُصيَّر** — «ما لا سياسةَ له مغلقٌ افتراضيًّا»');
$d0b = VPS::decide($conn, $gate, $CO, 'card.units', $OPCTX);
check($d0b['visible'], 'والوحداتُ (عادية · افتراضُها مفتوح) تظهر');

// ═══ D1 الجماعي ═══
head('D1 — إغلاقٌ جماعيٌّ لفئة المشغّلين بمفتاحٍ واحد');

$r = VPS::setKey($conn, $gate, $CO, array(
    'element_code' => 'card.units', 'scope_type' => 'capacity_type', 'scope_id' => 'operator',
    'mode' => 'closed', 'reason' => 'سياسة ' . $MARK), $HR);
check($r['ok'] && $r['affected'] >= 1,
      '★ مفتاحٌ واحدٌ بنطاق الفئة — و**عددُ المتأثرين معلَنٌ**: ' . $r['affected'] . ' حسابًا');
$d1 = VPS::decide($conn, $gate, $CO, 'card.units', $OPCTX);
check(!$d1['visible'] && $d1['source'] === 'capacity_type',
      '★★ القرارُ للمشغّل: **لا يُصيَّر** بمصدر «الفئة» — لا لمسَ لآلاف الحسابات');
$logRow = $conn->query("SELECT * FROM visibility_audit_log
                         WHERE reason LIKE '%{$MARK}%' AND element_code='card.units'
                         ORDER BY id DESC LIMIT 1")->fetch_assoc();
check($logRow && intval($logRow['affected_count']) === intval($r['affected'])
      && intval($logRow['actor']) === $HR,
      '★ والحدثُ موثَّقٌ بفاعله وعدد متأثريه — «لا تغييرَ صامت»');

// ═══ D2 الحساس ═══
head('D2 — الحساسُ لا يُفتح بلا مدةٍ وسبب');

$before = intval($conn->query("SELECT COUNT(*) n FROM visibility_keys")->fetch_assoc()['n']);
$r = VPS::setKey($conn, $gate, $CO, array(
    'element_code' => 'card.payroll', 'scope_type' => 'capacity_type', 'scope_id' => 'operator',
    'mode' => 'open', 'reason' => 'فتحٌ ' . $MARK), $HR);
$after = intval($conn->query("SELECT COUNT(*) n FROM visibility_keys")->fetch_assoc()['n']);
check(!$r['ok'] && $r['code'] === 422 && $after === $before,
      '★★ فتحُ الراتب بلا مدة ⇒ **422 «الحساس يتطلب مدةً وسببًا» ولا يُحفظ**');

$r = VPS::setKey($conn, $gate, $CO, array(
    'element_code' => 'card.payroll', 'scope_type' => 'capacity_type', 'scope_id' => 'operator',
    'mode' => 'open', 'reason' => ''), $HR);
check(!$r['ok'] && $r['code'] === 422, 'وبلا سببٍ ⇒ 422 — «سببٌ إلزاميٌّ لكل تغييرٍ حساس»');

$bad = $conn->query("INSERT INTO visibility_keys (company_id, element_code, scope_type,
    scope_id, mode, reason, granted_by) VALUES ({$CO}, 'card.units', 'account', '999999',
    'closed', NULL, {$HR})");
check($bad === false, 'وكتابةٌ مباشرةٌ بلا سببٍ **يرفضها CHECK** — الحارسُ بنيويٌّ لا خدميًّا فقط');

// ═══ D3 الأولوية ═══
head('D3 — الحسابُ يغلب الفئة: قاعدةٌ واحدةٌ لا اجتهاد');

$r = VPS::setKey($conn, $gate, $CO, array(
    'element_code' => 'card.units', 'scope_type' => 'account', 'scope_id' => (string) $U_OP,
    'mode' => 'open', 'reason' => 'استثناءُ ' . $MARK), $HR);
check($r['ok'], 'فُتح العنصرُ للحساب فرديًّا (وفئتُه مغلقةٌ من D1)');
$d3 = VPS::decide($conn, $gate, $CO, 'card.units', $OPCTX);
check($d3['visible'] && $d3['source'] === 'account',
      '★★★ القرارُ: **مفتوحٌ بمصدر «account»** — الحسابُ يغلب الفئةَ والمصدرُ معلَنٌ للمحاكاة');

// ═══ D4 منع الذات ═══
head('D4 — لا يمنح الفاعلُ نفسَه ظهورًا');

$r = VPS::setKey($conn, $gate, $CO, array(
    'element_code' => 'card.payroll', 'scope_type' => 'account', 'scope_id' => (string) $HR,
    'mode' => 'open', 'reason' => 'لنفسي ' . $MARK,
    'expires_at' => date('Y-m-d H:i:s', time() + 86400)), $HR);
check(!$r['ok'] && $r['code'] === 403,
      '★★ منحُ الذات ⇒ **403 بنيويًّا** — فصلُ الواجبات لا يُخترق من الداخل');
$denied = $conn->query("SELECT COUNT(*) n FROM visibility_audit_log
                         WHERE to_mode='denied_self' AND actor={$HR}
                           AND element_code='card.payroll'")->fetch_assoc();
check(intval($denied['n']) >= 1, '★ والمحاولةُ **مسجَّلةٌ** في سجل التدقيق');

// ═══ D5 الانتهاء ═══
head('D5 — المنحةُ المنتهية تُغلق آليًّا بلا تدخل');

$r = VPS::setKey($conn, $gate, $CO, array(
    'element_code' => 'field.evaluation', 'scope_type' => 'account', 'scope_id' => (string) $U_OP,
    'mode' => 'open', 'reason' => 'منحةُ ' . $MARK,
    'expires_at' => date('Y-m-d H:i:s', time() + 2)), $HR);
check($r['ok'], 'منحةُ تقييمٍ مؤقتةٌ بثانيتين فُتحت (بمدةٍ وسبب — الحارسُ رضي)');
$dOpen = VPS::decide($conn, $gate, $CO, 'field.evaluation', $OPCTX);
check($dOpen['visible'], 'وقبل انقضائها العنصرُ يظهر');
sleep(3);
$dExp = VPS::decide($conn, $gate, $CO, 'field.evaluation', $OPCTX);
check(!$dExp['visible'],
      '★★★ انقضت المدةُ ⇒ **العنصرُ يُغلق آليًّا عند أول قراءةٍ بلا تدخلٍ ولا كرون**');
$ge = $conn->query("SELECT COUNT(*) n FROM visibility_audit_log
                     WHERE to_mode='grant_expired' AND element_code='field.evaluation'")->fetch_assoc();
check(intval($ge['n']) >= 1, '★ و`grant_expired` قُيّد في السجل — الانتهاءُ حدثٌ لا صمت');

// ═══ خارج القاموس ═══
head('ما ليس في القاموس لا يُصيَّر أصلًا');

$r = VPS::setKey($conn, $gate, $CO, array(
    'element_code' => 'card.ghost', 'scope_type' => 'account', 'scope_id' => (string) $U_OP,
    'mode' => 'open', 'reason' => $MARK), $HR);
check(!$r['ok'] && $r['code'] === 422, '★ ضبطُ عنصرٍ خارج القاموس ⇒ **422**');
$dg = VPS::decide($conn, $gate, $CO, 'card.ghost', $OPCTX);
check(!$dg['visible'] && $dg['source'] === 'not_in_dictionary',
      '★ وقرارُه: **لا يُصيَّر** بمصدر «خارج القاموس»');

// عنصرٌ موقوفٌ لا يُصيَّر
$conn->query("INSERT INTO portal_elements (element_code, title_ar, owner_doc, sensitivity, default_mode, active)
              VALUES ('test.{$MARK}', 'عنصرُ اختبار', 'USR-01', 'normal', 'open', 0)");
$di = VPS::decide($conn, $gate, $CO, 'test.' . $MARK, $OPCTX);
check(!$di['visible'], 'والموقوفُ في القاموس لا يُصيَّر ولو كان افتراضُه مفتوحًا');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
