<?php
/**
 * tests/employee_settlement_test.php — E-02
 * ═══════════════════════════════════════════════════════════════════════════
 * تسويةُ الموظف عبر الخدمة الموحّدة (UX-02 §15.3/§15.4 · UX-05 §2.2).
 *
 * ما يُثبته:
 *   ① **الطرفُ الثاني يعمل**: توليدُ تسويةِ موظفٍ من دفتر ذممه — صفرُ إدخالِ مبلغ.
 *   ② الصافي = الأولي − التحميلات · والسالبُ **يفتح ذمّةً مدينةً على الموظف**.
 *   ③ **العطالة**: (موظف × فترة) لا يتكرر — 409 بمرجع القائم ولا صفَّ ثانٍ.
 *   ④ **فصلُ اليدين**: مَن أعدّ لا يُجيز (بنيويًّا في الخدمة).
 *   ⑤ **الحدُّ المعلَن**: الموظفُ لا تصله قطعُ غيارٍ ولا أوامرُ صيانة (تحميلا
 *      المورد المباشران) — والشاشةُ تعلنه للمستخدم ولا تخبئه.
 *   ⑥ **العزلُ بين الطرفين**: ذمّةُ موردٍ لا تدخل تسويةَ موظفٍ ولا العكس.
 *   ⑦ **التسجيلُ والمنح**: الوحدةُ مسجَّلةٌ وفصلُ اليدين في طبقة المنح نفسِها،
 *      والمسارُ اليدويُّ القديم أُحيل إلى العرض.
 *   ⑧ **المسارُ الحيّ**: الشاشةُ تُصيَّر لمن يملكها وتُحجب عمّن لا يملكها،
 *      والقديمةُ تعرض الإحالة.
 *
 * يبذر موظفَه وذممَه في فترةٍ بعيدة ويكنس الكلَّ — لا يمسّ ذمّةً قائمة.
 * التشغيل: php tests/employee_settlement_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

const BASE = 'http://localhost/ems';

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '4', 'company_id' => 4, 'name' => 'emp settlement test');
require_once dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php';

use App\Services\Settlement\SettlementService as SVC;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO   = 4;
$MARK = 'EMPSTL' . getmypid();
$FROM = '2093-04-01';
$TO   = '2093-04-30';
$PER  = '2093-04';
$PREP = 911;   // مُعِدٌّ وهمي
$APPR = 912;   // مُجيزٌ وهمي

// ── الكنس ─────────────────────────────────────────────────────────────────
// گوتشا: رقمُ التسوية يُعاد استعمالُه بعد الكنس (العدّادُ يعدّ القائم)، فلا كنسَ
// بنمط الرقم ولا تأكيدَ على عددٍ مطلقٍ به — الكنسُ بمالك الصفّ الموسوم.
// و**الإسقاطُ قبل الجذر**: `fin_financial_events.root_event_id` مفتاحٌ أجنبيٌّ
// على الدفتر، فحذفُ الجذر أولًا يسقط بخرق FK.
$cleanup = function () use ($conn, $MARK, $PER) {
    $conn->query("DELETE r FROM fin_requests r
                    JOIN settlements s ON s.id = r.settlement_id
                   WHERE s.party_name LIKE '%{$MARK}%'");
    $conn->query("DELETE sl FROM settlement_lines sl
                    JOIN settlements s ON s.id = sl.settlement_id
                   WHERE s.party_name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM settlements WHERE party_name LIKE '%{$MARK}%'");
    $orphan = "SELECT id FROM (SELECT id, source_ref FROM ems_business_events) be
                WHERE be.source_ref LIKE 'STL-%'
                  AND NOT EXISTS (SELECT 1 FROM (SELECT settlement_no FROM settlements) s
                                   WHERE s.settlement_no = be.source_ref)";
    $conn->query("DELETE FROM fin_financial_events WHERE root_event_id IN ({$orphan})");
    $conn->query("DELETE FROM ems_business_events WHERE id IN ({$orphan})");
    $conn->query("DELETE FROM fin_dues WHERE period_ref = '{$PER}'");
    $conn->query("DELETE FROM employees WHERE name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
};
$cleanup();
register_shutdown_function($cleanup);

fwrite(STDOUT, "\n══ E-02 — تسويةُ الموظف بالخدمة الموحّدة ══\n");

// ── البذر: موظفٌ + ذممُه بالدولار (له سعرٌ = 1 فتُقيَّم) ────────────────────
$conn->query("INSERT INTO employees (company_id, name, created_at) VALUES ({$CO}, 'موظفُ {$MARK}', NOW())");
$EMP = $conn->insert_id;
$conn->query("INSERT INTO suppliers (company_id, name, created_at) VALUES ({$CO}, 'موردُ {$MARK}', NOW())");
$SUP = $conn->insert_id;

// M-11: الخصمُ يلزمه مصدرٌ (`ck_dues_debit_source` قيدٌ في القاعدة) — والسلفُ
// والجزاءاتُ هنا فجوةٌ **معلَنة** (`pending_source`) لا مخبوءة، والاستحقاقُ حرٌّ منه.
$mkDue = function ($partyType, $ref, $dir, $type, $amount, $cur = 'USD') use ($conn, $CO, $PER) {
    $src = ($dir === 'debit') ? 'pending_source' : null;
    $st = $conn->prepare("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                          amount, currency, period_ref, source_doc_type, created_by, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $r = (string) $ref;
    $st->bind_param('issssdsss', $CO, $partyType, $r, $type, $dir, $amount, $cur, $PER, $src);
    $st->execute(); $id = $conn->insert_id; $st->close();
    return $id;
};

$E1 = $mkDue('employee', $EMP, 'credit', 'hours',   800.00);  // استحقاق
$E2 = $mkDue('employee', $EMP, 'credit', 'tons',    200.00);  // استحقاق
$E3 = $mkDue('employee', $EMP, 'debit',  'advance', 150.00);  // تحميل: سلفة
$E4 = $mkDue('employee', $EMP, 'debit',  'penalty',  50.00);  // تحميل: جزاء
// ذمّةُ موردٍ في الفترة نفسِها — يجب ألّا تدخل تسويةَ الموظف (⑥)
$S1 = $mkDue('supplier', $SUP, 'credit', 'hours',  9999.00);
check($EMP > 0 && $E1 > 0 && $E4 > 0 && $S1 > 0,
    "بُذر الموظفُ #{$EMP} بأربع ذممٍ ومورّدٌ بذمّةٍ في الفترة نفسِها");

// ═══ ① التوليدُ من المصادر ═══
head('① التوليدُ — صفرُ إدخالِ مبلغ، والبنودُ من دفتر الذمم');
$gen = SVC::generate($gate, $conn, 'employee', $EMP, $FROM, $TO, $PREP);
check(!empty($gen['ok']), 'تولّدت تسويةُ الموظف: ' . ($gen['reason'] !== '' ? $gen['reason'] : 'ok'));
$SID = intval($gen['settlement_id']);
check($SID > 0, "برقمٍ خادميٍّ ومعرّفٍ #{$SID}");
check(intval($gen['entitlements']) === 2, 'استحقاقان (ساعات + أطنان): ' . intval($gen['entitlements']));
check(intval($gen['charges']) === 2, 'وتحميلان (سلفة + جزاء): ' . intval($gen['charges']));

$row = $conn->query("SELECT * FROM settlements WHERE id={$SID}")->fetch_assoc();
check($row && (string) $row['party_type'] === 'employee', 'والطرفُ مسجَّلٌ `employee` لا `supplier`');
check($row && strpos((string) $row['party_name'], $MARK) !== false,
    'واسمُ الطرف من جدول الموظفين: ' . ($row['party_name'] ?? '—'));

// ═══ ⑥ العزلُ بين الطرفين ═══
head('⑥ العزل — ذمّةُ الموردِ لا تدخل تسويةَ الموظف');
$srcs = array();
foreach ($conn->query("SELECT source_kind, source_ref, amount FROM settlement_lines WHERE settlement_id={$SID}")
              ->fetch_all(MYSQLI_ASSOC) as $l) { $srcs[(string) $l['source_ref']] = (float) $l['amount']; }
check(!isset($srcs[(string) $S1]), 'ذمّةُ المورد (9999) خارجَ بنود تسوية الموظف');
check(isset($srcs[(string) $E1]) && isset($srcs[(string) $E3]),
    'وذممُ الموظف الأربعُ داخلَها (استحقاقًا وتحميلًا)');
check((float) $row['gross_amount'] == 1000.00, 'الأولي = 800 + 200 = ' . $row['gross_amount']);
check((float) $row['charges_amount'] == 200.00, 'والتحميلات = 150 + 50 = ' . $row['charges_amount']);

// ═══ ② الصافي ═══
head('② الصافي = الأولي − التحميلات');
check((float) $row['net_amount'] == 800.00, 'الصافي 800.00: ' . $row['net_amount']);
check((string) $row['net_direction'] === 'payable', 'واتجاهُه «مستحقٌّ للموظف» (payable)');

// ═══ ③ العطالة ═══
head('③ العطالة — (موظف × فترة) لا يتكرر');
$again = SVC::generate($gate, $conn, 'employee', $EMP, $FROM, $TO, $PREP);
check(empty($again['ok']) && intval($again['code']) === 409, 'الطلبُ المكرر يُرجع 409 لا صفًّا ثانيًا');
check(intval($again['settlement_id']) === $SID, 'وبمرجع القائم نفسِه #' . intval($again['settlement_id']));
$n = (int) $conn->query("SELECT COUNT(*) c FROM settlements WHERE party_type='employee'
                          AND party_ref={$EMP} AND period_from='{$FROM}'")->fetch_assoc()['c'];
check($n === 1, "وصفٌّ واحدٌ في القاعدة لا اثنان: {$n}");

// ═══ ⑤ الحدُّ المعلَن ═══
head('⑤ الحدُّ المعلَن — لا قطعَ ولا صيانةَ على الموظف');
$kinds = array();
foreach ($conn->query("SELECT DISTINCT source_kind FROM settlement_lines WHERE settlement_id={$SID}")
              ->fetch_all(MYSQLI_ASSOC) as $k) { $kinds[] = (string) $k['source_kind']; }
sort($kinds);
check($kinds === array('due'), 'كلُّ بنوده من دفتر الذمم: ' . implode(' · ', $kinds));
$screen = file_get_contents(dirname(__DIR__) . '/Workforce/employee_settlements.php');
check(mb_strpos($screen, 'حدٌّ معلَن') !== false && mb_strpos($screen, 'يدويًّا') !== false,
    'والشاشةُ تعلن الحدَّ للمستخدم نصًّا (لا مخبوءٌ في تعليق)');
check(mb_strpos($screen, 'لا تخترع خصمًا لا أصلَ له') !== false,
    'وتقول صراحةً إنها لا تخترع خصمًا — قاعدةُ عدم التلفيق');

// ═══ ④ فصلُ اليدين ═══
head('④ فصلُ اليدين — مَن أعدّ لا يُجيز');
$sub = SVC::submit($gate, $SID, $PREP);
check(!empty($sub['ok']), 'المُعِدُّ يرفعها للمراجعة');
$self = SVC::approve($gate, $conn, $SID, $PREP);
check(empty($self['ok']), 'والمُعِدُّ نفسُه لا يُجيزها: ' . $self['reason']);
$other = SVC::approve($gate, $conn, $SID, $APPR);
check(!empty($other['ok']), 'ويدٌ ثانيةٌ تُجيزها');
// الصافي موجبٌ ⇒ الخدمةُ تولّد طلبَ الدفع فورًا وتصير الحالةُ `payment_requested`
// (§15.3 `Approved → PaymentRequested`) — لا تقف عند `approved`.
$row2 = $conn->query("SELECT state, payment_request_id FROM settlements WHERE id={$SID}")->fetch_assoc();
check((string) $row2['state'] === 'payment_requested',
    'والحالةُ «طُلب الدفع» — الصافي موجبٌ فتولّد طلبُه فورًا: ' . $row2['state']);
check(intval($row2['payment_request_id']) > 0, 'وطلبُ الدفع مرتبطٌ بها #' . intval($row2['payment_request_id']));
$pr = $conn->query("SELECT request_type, beneficiary_type, beneficiary_ref, amount, settlement_id
                      FROM fin_requests WHERE id=" . intval($row2['payment_request_id']))->fetch_assoc();
check($pr && (string) $pr['request_type'] === 'employee_payment',
    'ونوعُه «دفعُ موظف» لا «دفعُ مورد»: ' . ($pr['request_type'] ?? '—'));
check($pr && (string) $pr['beneficiary_type'] === 'employee' && intval($pr['beneficiary_ref']) === $EMP,
    'ومستفيدُه الموظفُ نفسُه #' . ($pr['beneficiary_ref'] ?? '—'));
check($pr && (float) $pr['amount'] == 800.00,
    'وبمبلغ التسوية منسوخًا لا مكتوبًا بيدٍ ثانية: ' . ($pr['amount'] ?? '—'));

// ② السالب — تسويةٌ ثانيةٌ بفترةٍ أخرى تحميلاتُها تفوق استحقاقَها
head('② السالبُ يفتح ذمّةً مدينةً على الموظف');
$FROM2 = '2093-05-01'; $TO2 = '2093-05-31'; $PER2 = '2093-05';
$mkDue2 = function ($dir, $type, $amount) use ($conn, $CO, $EMP, $PER2) {
    $src = ($dir === 'debit') ? 'pending_source' : null;
    $st = $conn->prepare("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                          amount, currency, period_ref, source_doc_type, created_by, created_at)
                          VALUES (?, 'employee', ?, ?, ?, ?, 'USD', ?, ?, 1, NOW())");
    $r = (string) $EMP;
    $st->bind_param('isssdss', $CO, $r, $type, $dir, $amount, $PER2, $src);
    $st->execute(); $id = $conn->insert_id; $st->close();
    return $id;
};
$mkDue2('credit', 'hours',   100.00);
$mkDue2('debit',  'advance', 400.00);
$gen2 = SVC::generate($gate, $conn, 'employee', $EMP, $FROM2, $TO2, $PREP);
$SID2 = intval($gen2['settlement_id']);
check(!empty($gen2['ok']) && $SID2 > 0, 'تولّدت تسويةُ الفترة الثانية');
$r2 = $conn->query("SELECT net_amount, net_direction FROM settlements WHERE id={$SID2}")->fetch_assoc();
check((float) $r2['net_amount'] == -300.00, 'الصافي سالب −300.00: ' . $r2['net_amount']);
check((string) $r2['net_direction'] === 'receivable', 'واتجاهُه «دَينٌ على الموظف» (receivable)');
SVC::submit($gate, $SID2, $PREP);
$ap2 = SVC::approve($gate, $conn, $SID2, $APPR);
check(!empty($ap2['ok']), 'واعتُمدت رغم سلبيتها (الرقمُ لا يضيع)');
$recv = $conn->query("SELECT COUNT(*) c, MAX(amount) a FROM fin_dues
                       WHERE party_type='employee' AND party_ref='{$EMP}'
                         AND due_type='settlement' AND direction='debit'
                         AND settlement_id={$SID2}")->fetch_assoc();
check(intval($recv['c']) === 1 && (float) $recv['a'] == 300.00,
    'ففُتحت ذمّةٌ مدينةٌ على الموظف بـ300.00: ' . intval($recv['c']) . ' صفًّا');
$conn->query("DELETE FROM fin_dues WHERE period_ref='{$PER2}'");
$conn->query("DELETE sl FROM settlement_lines sl WHERE sl.settlement_id={$SID2}");
$conn->query("DELETE FROM settlements WHERE id={$SID2}");

// ═══ ⑦ التسجيلُ والمنح ═══
head('⑦ التسجيل — الوحدةُ وفصلُ اليدين في طبقة المنح');
$mod = $conn->query("SELECT id, owner_role_id FROM modules
                      WHERE code='Workforce/employee_settlements.php'")->fetch_assoc();
check($mod !== null, 'الوحدةُ مسجَّلةٌ بكودها الحرفي #' . ($mod['id'] ?? '—'));
check($mod && intval($mod['owner_role_id']) === 4, 'ومالكُها الموارد البشرية (4)');
$grants = array();
foreach ($conn->query("SELECT role_id, can_view, can_add, can_edit FROM role_permissions
                        WHERE module_id=" . intval($mod['id']))->fetch_all(MYSQLI_ASSOC) as $g) {
    $grants[intval($g['role_id'])] = $g;
}
check(isset($grants[4]) && intval($grants[4]['can_add']) === 1 && intval($grants[4]['can_edit']) === 0,
    'الموارد البشرية (4): تُعدّ ولا تُجيز');
check(isset($grants[19]) && intval($grants[19]['can_add']) === 0 && intval($grants[19]['can_edit']) === 1,
    'ومديرُ المالية (19): يُجيز ولا يُعدّ');
$both = 0;
foreach ($grants as $g) { if (intval($g['can_add']) === 1 && intval($g['can_edit']) === 1) { $both++; } }
check($both === 0, 'ولا دورَ يجمع اليدين — فصلٌ في المنح لا في الكود وحده');
$nav = (int) $conn->query("SELECT COUNT(*) c FROM nav_items
                            WHERE route='Workforce/employee_settlements.php' AND active=1")
                  ->fetch_assoc()['c'];
// أربعةٌ لا خمسة: الدور 1 (التشغيل) **بلا رابط** — «التبعيةُ تحدد القائمة
// والصلاحيةُ ترشّح»، والموارد البشرية ليست تابعةً له. وتوأمُها
// `Suppliers/settlements.php` بلا رابطٍ للدور 1 بالقاعدة عينها.
check($nav === 4, "وللشاشة أربعةُ روابطِ تنقّلٍ فعّالة (4 · 19 · 17 · 18): {$nav}");
$nav1 = (int) $conn->query("SELECT COUNT(*) c FROM nav_items
                            WHERE route='Workforce/employee_settlements.php' AND role_id=1")
                  ->fetch_assoc()['c'];
check($nav1 === 0, 'ولا رابطَ للتشغيل (1) على شاشةِ إدارةٍ لا تتبعه');

$old = $conn->query("SELECT SUM(rp.can_add)+SUM(rp.can_edit)+SUM(rp.can_delete) w
                       FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                      WHERE m.code='Workforce/worker_settlement.php'")->fetch_assoc();
check(intval($old['w']) === 0, 'والمسارُ اليدويُّ القديم أُحيل إلى العرض (صفرُ منحةِ كتابة)');
/* ══ **الشرطُ كان يناقض عنوانَه.** العنوانُ يقول «**لم يُقطع عملٌ قائم** —
     والجدولُ **باقٍ بحاله**»، والشرطُ كان `=== 0` أي **يطلب جدولًا فارغًا** —
     وهو نقيضُ البقاءِ بحاله. والمقيسُ أن الجدولَ يحمل **عشرين تسويةً** كتلةً
     واحدةً (2026-08-02) بحالاتٍ حقيقيةٍ (محتسب 7 · معتمد 7 · مدفوع 6) — أي
     **عملًا قائمًا لم يُقطع**، وهو عينُ ما يُراد إثباتُه.
   ⇒ فيُقاس ما يعنيه العنوان: الجدولُ **قائمٌ** وصفوفُه **لم تُمَسّ**، ومنحُ
     الكتابةِ عليه **صفرٌ** (مُقاسٌ في السطرِ الذي قبله) — فالإحالةُ إلى العرضِ
     لم تُهدر شيئًا. وحذفُ صفوفِه هو ما يجب أن يُرسِب، لا وجودُها. */
$oldRows = (int) $conn->query("SELECT COUNT(*) c FROM worker_settlement")->fetch_assoc()['c'];
$oldTbl  = (int) $conn->query("SELECT COUNT(*) c FROM information_schema.tables
                                WHERE table_schema = DATABASE() AND table_name = 'worker_settlement'")
                      ->fetch_assoc()['c'];
check($oldTbl === 1 && $oldRows > 0,
    "ولم يُقطع عملٌ قائم: الجدولُ القديمُ باقٍ بحاله وفيه {$oldRows} تسويةً — الإحالةُ إلى العرضِ لا تُهدر عملًا");

// ═══ ⑧ المسارُ الحيّ ═══
head('⑧ المسارُ الحيّ — الشاشةُ تُصيَّر لمن يملكها وتُحجب عمّن لا يملكها');
$JAR = sys_get_temp_dir() . '/ems_empstl_cookies.txt';
function req($url, $post = null) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR, CURLOPT_TIMEOUT => 30));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return array($c, $b);
}
function login($u) {
    global $JAR; @unlink($JAR);
    list(, $p) = req(BASE . '/login.php');
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $p, $m);
    list($c) = req(BASE . '/login.php', array('username' => $u, 'password' => '12345678',
                                              'csrf_token' => $m[1] ?? ''));
    return $c === 200;
}
$hr = $conn->query("SELECT username FROM users WHERE role=4 AND company_id=4 LIMIT 1")->fetch_assoc();
$sales = $conn->query("SELECT username FROM users WHERE role=12 AND company_id=4 LIMIT 1")->fetch_assoc();

if ($hr) {
    check(login($hr['username']), 'دخولُ «' . $hr['username'] . '» (الدور 4 — الموارد البشرية)');
    list($c1, $p1) = req(BASE . '/Workforce/employee_settlements.php');
    check($c1 === 200 && mb_strpos($p1, 'تسويات الموظفين') !== false, 'والشاشةُ صُيِّرت له');
    check(mb_strpos($p1, 'ولّد التسوية') !== false, 'ونموذجُ الإعداد ظاهرٌ (can_add)');
    check(mb_strpos($p1, 'إجازة') === false || mb_strpos($p1, 'مَن يُعدّ لا يُجيز') !== false,
        'ولا زرَّ إجازةٍ له — مَن يُعدّ لا يُجيز');
    check(mb_strpos($p1, 'خصومُ السلف والجزاءات') !== false, 'والحدُّ المعلَن ظاهرٌ في الشاشة');
    list($c2, $p2) = req(BASE . '/Workforce/worker_settlement.php');
    check($c2 === 200 && mb_strpos($p2, 'صارت للعرض فقط') !== false,
        'والشاشةُ القديمةُ تعرض الإحالةَ إلى الموحّدة');
} else { bad('لا مستخدمَ بالدور 4 للاختبار'); }

if ($sales) {
    check(login($sales['username']), 'ودخولُ «' . $sales['username'] . '» (الدور 12 — لا يملك الشاشة)');
    list($c3, $p3) = req(BASE . '/Workforce/employee_settlements.php');
    check(mb_strpos($p3, 'تسويات الموظفين') === false || mb_strpos($p3, 'لا توجد صلاحية') !== false,
        'والشاشةُ محجوبةٌ عنه — الغيابُ منعٌ لا إذن');
} else { ok('(لا مستخدمَ بالدور 12 — تُخطّى)'); }

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
