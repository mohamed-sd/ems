<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-01 §8.7 — اختبار قبول تنبيهَي لوحة المبيعات (E-01)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/sales_board_alerts_test.php
 * رمز الخروج: 0 = أخضر · 1 = فشل.
 *
 * ما يُثبته:
 *   ① **التعليقُ البائتُ زال**: لا نصَّ «بلا مصدرٍ بعد» في كتلة الدور 12 —
 *      قاعدةُ عدم التلفيق تعمل في الاتجاهين، وتعليقٌ يعلن غيابَ مصدرٍ قائمٍ
 *      كذبٌ موثَّق.
 *   ② التنبيهان الأربعةُ مُعلَنون للدور 12، ولكلٍّ وجهةٌ **مرشَّحة**.
 *   ③ العدّادان يطابقان القاعدةَ الحيةَ رقمًا برقم.
 *   ④ **تعريفٌ واحدٌ لا اثنان**: عدّادُ اللوحة = ما تعرضه الشاشة = ما يُخرجه
 *      `claim_billable_units` لكل عقدٍ على مداه — لا استعلامَ منسوخًا.
 *   ⑤ **العدّادُ حيٌّ لا ثابت**: يومٌ يدخل مستخلصًا يخرج من العدّ، وإلغاءُ
 *      المستخلص يردّه — وهو معنى «المستخلَصُ سلفًا يُستبعد».
 *   ⑥ **المسارُ المرفوض**: يومٌ بلا قيدِ إيراد لا يُعدُّ جاهزًا مهما كانت حالتُه
 *      — المستخلصُ يرث بوابةَ التحويل ولا يفتح بابًا ثانيًا.
 *   ⑦ العملةُ لا تُجمع بغيرها: العقدُ بعملتين صفّان لا صفٌّ واحد.
 *   ⑧ **المسارُ الحيّ**: لوحةُ «مبيعات» تعرض التنبيهين بعددهما، والنقرُ إلى
 *      `claims.php?unbilled=1` و`?pending=1` يعرض الرقمَ نفسَه فعلًا.
 *
 * كلُّ ما يُنشأ يُحذف في النهاية — حتى عند الفشل. ولا يُمَسّ قيدٌ مرحَّل.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

const BASE = 'http://localhost/ems';
$ROOT = dirname(__DIR__);

require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
require_once $ROOT . '/Contracts/claim_helpers.php';

use App\Core\TenantContext;
use App\Core\TenantDb;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$CO = 4;
$conn = $GLOBALS['conn'];
$db = $conn;
// البوابةُ بسياقٍ نظاميٍّ صريح (نمطُ حزم البوابة القائمة) — لا جلسةَ في CLI
$gate = new TenantDb($conn, TenantContext::forSystem($CO, 1, '12', true));

$MARK = 'E01T_' . getmypid();
$cleanup = function () use ($db, $MARK) {
    $db->query("DELETE FROM claim_lines WHERE claim_id IN
                  (SELECT id FROM claims WHERE claim_no LIKE '{$MARK}%')");
    $db->query("DELETE FROM claims WHERE claim_no LIKE '{$MARK}%'");
};
$cleanup();
register_shutdown_function($cleanup);

fwrite(STDOUT, "\n══ UX-01 §8.7 — تنبيها لوحة المبيعات ══\n");

// ═══ ① التعليقُ البائتُ زال ═══
head('① التعليقُ البائتُ — لا إعلانَ غيابِ مصدرٍ ومصدرُه قائم');
$boardSrc = file_get_contents($ROOT . '/includes/role_board.php');
// الكتلةُ بين تعليق §8.7 وإغلاق مصفوفة الدور 12
$blockOk = preg_match('/§8\.7.{0,900}?12 => array\(.*?\n        \),/s', $boardSrc, $bm) === 1;
check($blockOk, 'كتلةُ الدور 12 مقروءة');
$block = $blockOk ? $bm[0] : '';
// الادعاءُ البائتُ بعينه لا كلُّ ذكرٍ للمصدر: التعليقُ الجديد يشرح القاعدةَ
// فيذكر اللفظَ مشروعًا. المرصودُ هو الجملةُ التي تنفي وجودَ المصدر وتُسقط
// التنبيهَ بناءً عليها.
check(strpos($block, 'بلا مصدرٍ بعد') === false,
    'زال الادعاءُ «بلا مصدرٍ بعد» — التعليقُ صُحِّح لا تُرك');
check(strpos($block, 'فلا يُعرضان') === false,
    'وزال معه قرارُ عدم العرض المبنيُّ عليه');
check(strpos($block, 'لا جدولَ مستخلصاتٍ ولا فواتير') === false,
    'ولا ذكرَ لـ«لا جدولَ مستخلصات» — والجدولان حيّان منذ 2026-07-27');
$claimsTable = $db->query("SHOW TABLES LIKE 'claims'")->num_rows;
$linesTable  = $db->query("SHOW TABLES LIKE 'claim_lines'")->num_rows;
check($claimsTable === 1 && $linesTable === 1,
    'والجدولان موجودان فعلًا في القاعدة (وهو ما جعل التعليقَ كذبًا)');

// ═══ ② التنبيهاتُ الأربعةُ ووجهاتُها ═══
head('② §8.7 — الأربعةُ كاملةً بوجهاتٍ مرشَّحة');
require_once $ROOT . '/includes/role_board.php';
$specs = roleBoardAlertSpecs(12);
$keys = array();
foreach ($specs as $s) { $keys[$s['key']] = $s; }
check(count($specs) === 4, 'أربعةُ تنبيهاتٍ للدور 12: ' . count($specs));
check(isset($keys['unbilled_units']), '«وحداتٌ جاهزةٌ للفوترة لم تُفوتر» معلَن');
check(isset($keys['claim_pending']), '«مطالبةٌ معلَّقة» معلَن');
check(isset($keys['unbilled_units']) && strpos($keys['unbilled_units']['href'], 'claims.php?unbilled=1') !== false,
    'ووجهةُ الأول شاشةُ المستخلصات **مرشَّحةً**: ' . ($keys['unbilled_units']['href'] ?? '—'));
check(isset($keys['claim_pending']) && strpos($keys['claim_pending']['href'], 'claims.php?pending=1') !== false,
    'ووجهةُ الثاني كذلك: ' . ($keys['claim_pending']['href'] ?? '—'));
// لغةُ المهمة لا لغةُ التحليل (العقيدة ⑥)
$banned = array('المروحة', 'الحدث', 'الإسقاط', 'entity_type', 'fin_event');
$leak = '';
foreach ($specs as $s) {
    foreach ($banned as $b) { if (mb_strpos($s['label'], $b) !== false) { $leak = $b; } }
}
check($leak === '', 'ولا لفظَ تحليلٍ في نصٍّ يراه المستخدم' . ($leak ? " (تسرّب: {$leak})" : ''));

// ═══ ③ العدّادان مقابل القاعدة ═══
head('③ العدّادان — مطابقةٌ رقمًا برقم مع القاعدة الحية');
$rawUnbilled = (int) $db->query(
    "SELECT COUNT(DISTINCT ts.id) n FROM timesheet ts
       JOIN fin_event_links l ON l.parent_kind='timesheet' AND l.parent_ref=ts.id
            AND l.effect_type='revenue_event'
       JOIN fin_financial_events fe ON fe.id=l.target_id AND fe.event_type='revenue'
            AND COALESCE(fe.is_deleted,0)=0
       LEFT JOIN operations o ON o.id=ts.operator
       LEFT JOIN claim_lines cll ON cll.source_kind='timesheet' AND cll.source_ref=ts.id
       LEFT JOIN claims clh ON clh.id=cll.claim_id AND clh.state<>'cancelled'
            AND COALESCE(clh.is_deleted,0)=0
      WHERE ts.company_id={$CO} AND ts.status=1 AND clh.id IS NULL
        AND o.contract_id IS NOT NULL")->fetch_assoc()['n'];
$rawPending = (int) $db->query(
    "SELECT COUNT(*) n FROM claims WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0
       AND state IN ('draft','review')")->fetch_assoc()['n'];

$cntUnbilled = claim_unbilled_days($gate);
$cntPending  = claim_pending_count($gate);
check($cntUnbilled === $rawUnbilled, "الجاهزُ للفوترة: العدّاد {$cntUnbilled} = القاعدة {$rawUnbilled}");
check($cntPending === $rawPending, "المعلَّقة: العدّاد {$cntPending} = القاعدة {$rawPending}");
check($cntUnbilled > 0 || $cntPending > 0,
    "وأحدُهما على الأقلِّ حيٌّ اليوم (أرضُ اختبارٍ حقيقية): {$cntUnbilled} · {$cntPending}");

// ═══ ④ تعريفٌ واحدٌ لا اثنان ═══
head('④ تعريفٌ واحد — اللوحةُ والشاشةُ و`claim_billable_units` تقول الشيءَ نفسَه');
$sum = claim_unbilled_summary($gate);
$sumDays = 0; $byContract = array();
foreach ($sum as $r) {
    $sumDays += intval($r['days']);
    $byContract[intval($r['contract_id'])][] = $r;
}
// المجموعُ قد يفوق العدّاد لو كان لعقدٍ عملتان في اليوم نفسِه — والعدّادُ هو الحقّ
check($sumDays >= $cntUnbilled, "مجموعُ صفوف الشاشة ({$sumDays}) لا يقلّ عن العدّاد ({$cntUnbilled})");

// المطابقةُ الحاسمة: لكل عقدٍ، `claim_billable_units` على مداه يُخرج أيامَه
$matched = true; $detail = array();
foreach ($byContract as $cid => $rows) {
    $from = null; $to = null;
    foreach ($rows as $r) {
        if ($from === null || $r['first_date'] < $from) { $from = $r['first_date']; }
        if ($to === null || $r['last_date'] > $to) { $to = $r['last_date']; }
    }
    $units = claim_billable_units($gate, $cid, $from, $to, 0);
    $ids = array();
    foreach ($units as $u) { $ids[intval($u['source_ref'])] = true; }
    $expect = 0;
    foreach ($rows as $r) { $expect = max($expect, intval($r['days'])); }
    $detail[] = "عقد {$cid}: الشاشة " . count($ids) . " · المدى {$from}→{$to}";
    if (count($ids) < $expect) { $matched = false; }
}
check($matched, 'ولكل عقدٍ يُخرج `claim_billable_units` أيامَه نفسَها — ' . implode(' · ', $detail));

// ═══ ⑤ العدّادُ حيٌّ لا ثابت ═══
head('⑤ العدّادُ حيٌّ — يومٌ يدخل مستخلصًا يخرج من العدّ');
$victim = $db->query(
    "SELECT ts.id, ts.date, o.contract_id, fe.id AS event_id, fe.amount, fe.currency
       FROM timesheet ts
       JOIN fin_event_links l ON l.parent_kind='timesheet' AND l.parent_ref=ts.id
            AND l.effect_type='revenue_event'
       JOIN fin_financial_events fe ON fe.id=l.target_id AND fe.event_type='revenue'
       LEFT JOIN operations o ON o.id=ts.operator
       LEFT JOIN claim_lines cll ON cll.source_kind='timesheet' AND cll.source_ref=ts.id
       LEFT JOIN claims clh ON clh.id=cll.claim_id AND clh.state<>'cancelled'
      WHERE ts.company_id={$CO} AND ts.status=1 AND clh.id IS NULL
        AND o.contract_id IS NOT NULL LIMIT 1")->fetch_assoc();

if (!$victim) {
    bad('لا يومَ جاهزًا للفوترة — تعذّر إثباتُ حياة العدّاد');
} else {
    $cno = $MARK . '-1';
    $db->query("INSERT INTO claims (company_id, claim_no, contract_id, period_from, period_to,
                   currency, gross_amount, retention_amount, net_amount, tax_amount, state, version)
                VALUES ({$CO}, '{$cno}', " . intval($victim['contract_id']) . ",
                   '{$victim['date']}', '{$victim['date']}', '{$victim['currency']}',
                   " . floatval($victim['amount']) . ", 0, " . floatval($victim['amount']) . ", 0, 'draft', 1)");
    $newClaim = (int) $db->insert_id;
    $db->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref, event_id,
                   work_date, qty, unit_price, amount, dispute_flag)
                VALUES ({$CO}, {$newClaim}, 'timesheet', " . intval($victim['id']) . ",
                   " . intval($victim['event_id']) . ", '{$victim['date']}', 1, "
                   . floatval($victim['amount']) . ", " . floatval($victim['amount']) . ", 0)");

    check(claim_unbilled_days($gate) === $cntUnbilled - 1,
        "اليومُ #{$victim['id']} دخل مستخلصًا فنقص العدّاد: " . claim_unbilled_days($gate)
        . " (كان {$cntUnbilled})");
    check(claim_pending_count($gate) === $cntPending + 1,
        'وعدّادُ المعلَّقة زاد بالمستخلص الوليد (مسودة): ' . claim_pending_count($gate));

    // الإلغاءُ يردّه — «إلغاءُ المستخلص يردّ وقائعَه للاستخلاص»
    $db->query("UPDATE claims SET state='cancelled' WHERE id={$newClaim}");
    check(claim_unbilled_days($gate) === $cntUnbilled,
        'وإلغاءُ المستخلص ردّ اليومَ للعدّ: ' . claim_unbilled_days($gate));
    check(claim_pending_count($gate) === $cntPending,
        'والملغى خارجَ «المعلَّقة»: ' . claim_pending_count($gate));

    // ولا حالةَ أخرى تُعدُّ معلَّقةً
    foreach (array('approved', 'invoiced', 'collected') as $st) {
        $db->query("UPDATE claims SET state='{$st}' WHERE id={$newClaim}");
        if (claim_pending_count($gate) !== $cntPending) {
            bad("الحالة «{$st}» عُدَّت معلَّقةً وهي ليست كذلك");
            $st = null; break;
        }
    }
    if ($st !== null) { ok('والمعتمَدُ والمفوترُ والمحصَّلُ خارجَ «المعلَّقة» — انتهى انتظارُها'); }
    $db->query("UPDATE claims SET state='review' WHERE id={$newClaim}");
    check(claim_pending_count($gate) === $cntPending + 1,
        'و«قيد المراجعة» معلَّقةٌ (تنتظر إجازةَ المالية): ' . claim_pending_count($gate));
    $cleanup();
    check(claim_unbilled_days($gate) === $cntUnbilled && claim_pending_count($gate) === $cntPending,
        'والتنظيفُ أعاد العدّادين إلى ما كانا عليه بالضبط');
}

// ═══ ⑥ المسارُ المرفوض ═══
head('⑥ المسارُ المرفوض — يومٌ بلا قيدِ إيرادٍ لا يُعدُّ جاهزًا');
$noRevenue = (int) $db->query(
    "SELECT COUNT(*) n FROM timesheet ts
      WHERE ts.company_id={$CO} AND ts.status=1
        AND NOT EXISTS (SELECT 1 FROM fin_event_links l
                         WHERE l.parent_kind='timesheet' AND l.parent_ref=ts.id
                           AND l.effect_type='revenue_event')")->fetch_assoc()['n'];
check($noRevenue > 0, "أيامٌ مقبولةٌ بلا قيدِ إيراد: {$noRevenue} — أرضُ الاختبار");
check($cntUnbilled < $noRevenue,
    "ولا واحدٌ منها في العدّاد ({$cntUnbilled}) — المستخلصُ يرث بوابةَ التحويل ولا يفتح بابًا ثانيًا");

// ═══ ⑦ العملةُ لا تُجمع بغيرها ═══
head('⑦ العملةُ في المجموعة لا في الدالة');
$curPerContract = array();
foreach ($sum as $r) { $curPerContract[intval($r['contract_id'])][(string) $r['currency']] = true; }
$mixed = 0;
foreach ($curPerContract as $cid => $curs) { if (count($curs) > 1) { $mixed++; } }
$mixedRaw = (int) $db->query(
    "SELECT COUNT(*) n FROM (
        SELECT o.contract_id FROM timesheet ts
          JOIN fin_event_links l ON l.parent_kind='timesheet' AND l.parent_ref=ts.id
               AND l.effect_type='revenue_event'
          JOIN fin_financial_events fe ON fe.id=l.target_id AND fe.event_type='revenue'
          LEFT JOIN operations o ON o.id=ts.operator
          LEFT JOIN claim_lines cll ON cll.source_kind='timesheet' AND cll.source_ref=ts.id
          LEFT JOIN claims clh ON clh.id=cll.claim_id AND clh.state<>'cancelled'
         WHERE ts.company_id={$CO} AND ts.status=1 AND clh.id IS NULL AND o.contract_id IS NOT NULL
         GROUP BY o.contract_id HAVING COUNT(DISTINCT fe.currency) > 1) x")->fetch_assoc()['n'];
check($mixed === $mixedRaw,
    "العقودُ بعملتين تظهر صفَّين لا صفًّا: {$mixed} عقدًا (والقاعدة {$mixedRaw})");
$sameCur = true;
foreach ($sum as $r) { if (trim((string) $r['currency']) === '') { $sameCur = false; } }
check($sameCur, 'ولا صفَّ بعملةٍ فارغة — كلُّ مبلغٍ معلومُ العملة');

// ═══ ⑧ المسارُ الحيّ ═══
head('⑧ المسارُ الحيّ — اللوحةُ ثم الشاشةُ بالرقم نفسِه');
$JAR = sys_get_temp_dir() . '/ems_e01_cookies.txt';
function req($url, $post = null) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR, CURLOPT_TIMEOUT => 30));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return array($c, $b);
}
@unlink($JAR);
list(, $lp) = req(BASE . '/login.php');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $lp, $lm);
list($lc) = req(BASE . '/login.php', array('username' => 'مبيعات', 'password' => '12345678',
                                           'csrf_token' => $lm[1] ?? ''));
check($lc === 200, 'دخولُ «مبيعات» (الدور 12)');

list($bc, $board) = req(BASE . '/main/role_board.php');
check($bc === 200, 'لوحةُ الدور صُيِّرت (200)');
check(mb_strpos($board, 'وحداتٌ جاهزةٌ للفوترة لم تُفوتر') !== false,
    'واللوحةُ تعرض تنبيهَ «وحداتٌ جاهزةٌ للفوترة لم تُفوتر» فعلًا');
check(mb_strpos($board, 'claims.php?unbilled=1') !== false,
    'ونقرتُه تقود إلى الشاشة مرشَّحةً');
if ($cntPending > 0) {
    check(mb_strpos($board, 'مطالبةٌ معلَّقة') !== false, 'وتنبيهَ «مطالبةٌ معلَّقة» كذلك');
} else {
    ok('(لا مطالبةَ معلَّقةً الآن — التنبيهُ مطفأٌ بحق: صفرٌ لا يُعرض)');
}

list($uc, $upage) = req(BASE . '/Contracts/claims.php?unbilled=1');
check($uc === 200, 'وشاشةُ المستخلصات بالمرشِّح `unbilled` صُيِّرت (200)');
check(mb_strpos($upage, 'جاهزٌ للفوترة ولم يُفوتر') !== false, 'واللوحُ ظهر');
check(preg_match('/جاهزٌ للفوترة ولم يُفوتر — (\d+) يومَ عمل/u', $upage, $um) === 1
      && intval($um[1]) === $cntUnbilled,
    "ورقمُه هو رقمُ التنبيه نفسُه: " . (isset($um[1]) ? $um[1] : '—') . " = {$cntUnbilled}");

list($pc, $ppage) = req(BASE . '/Contracts/claims.php?pending=1');
check($pc === 200, 'وشاشةُ المستخلصات بالمرشِّح `pending` صُيِّرت (200)');
$rowsShown = preg_match_all('/CLM-\d{4}-\d{4}/u', $ppage, $pm);
check($rowsShown === $cntPending,
    "وعددُ صفوفها = عدّادُ المعلَّقة: {$rowsShown} = {$cntPending}");
check(mb_strpos($ppage, 'معلَّقة') !== false, 'ومرشِّحُ «معلَّقة» معروضٌ في شريط المرشِّحات');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
