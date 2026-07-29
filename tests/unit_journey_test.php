<?php
/**
 * tests/unit_journey_test.php — E-09
 * ═══════════════════════════════════════════════════════════════════════════
 * شريطُ رحلة الوحدة التشغيلية (UX-01 §6 · UX-03 §8.2).
 *
 * ما يُثبته:
 *   ① **صفرُ جدولٍ جديد**: الرحلةُ إسقاطٌ — خمسُ مراحلَ من حالاتٍ ثلاثَ عشرة.
 *   ② المرحلةُ الحالية تتبع الحالةَ الفعلية على **الوقائع الحيّة** لا على مثال.
 *   ③ **الفخُّ الثاني**: الأطرافُ **مرحلةٌ واحدةٌ بعدّاد** لا ثلاثُ مراحلَ
 *      متتالية — وعرضُها سلسلةً كذبٌ بصريّ.
 *   ④ والعدّادُ يقرأ المطلوبين من **دالة الخدمة نفسِها** (`requiredPartiesOf`):
 *      واقعةٌ بلا مشرفٍ عدّادُها «من 2» لا «من 3».
 *   ⑤ **الفخُّ الأول**: `returned` **لافتةُ انكسارٍ** لا مرحلة — بسببها ورقمِ
 *      جولتها وبالرقم نفسه.
 *   ⑥ **الجولةُ الميتة لا تُعرض**: أزمنةُ الجولة الجارية وحدَها.
 *   ⑦ التوقفُ يُطفئ ما بعده · والوقفةُ لا تُرجع الواقعة.
 *   ⑧ `converted` يُغلق الرحلةَ بلافتة اكتمال.
 *   ⑨ **لغةُ المهمة**: لا `supplier` ولا `state` ولا لفظُ تحليلٍ في المخرَج.
 *   ⑩ **المسارُ الحيّ**: الشريطُ يُصيَّر فعلًا في شاشة تفاصيل الوحدة.
 *
 * لا يكتب صفًّا في القاعدة: البانيةُ دالةٌ خالصةٌ تأخذ صفوفَها، فالحالاتُ
 * التي لا وجودَ لها حيًّا تُختبر بصفوفٍ مبنيّةٍ في الذاكرة.
 * التشغيل: php tests/unit_journey_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

const BASE = 'http://localhost/ems';

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/Timesheet/_ts_journey.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$CO = 4;

/** واقعةٌ مبنيّةٌ في الذاكرة — لا تُكتب ولا تُقرأ من القاعدة. */
function mkEntry(array $over = array()) {
    return array_merge(array(
        'id' => 1, 'company_id' => 4, 'entry_no' => 'UNT-000001',
        'state' => 'submitted', 'current_round' => 1,
        'supplier_entity_id' => 7, 'operator_employee_id' => 3, 'supervisor_id' => null,
        'converted_at' => null, 'created_at' => '2026-07-01 08:00:00',
    ), $over);
}
function mkApp($stage, $decision = 'approved', $round = 1, $when = '2026-07-02 09:00:00', $note = null) {
    return array('stage' => $stage, 'decision' => $decision, 'round_no' => $round,
                 'decided_at' => $when, 'note' => $note);
}
/** أسماءُ مراحل الشريط بترتيبها. */
function labelsOf(array $j) {
    $out = array();
    foreach ($j['stages'] as $s) { $out[] = $s['label']; }
    return $out;
}
function stageAt(array $j, $i) { return $j['stages'][$i - 1]; }
function currentOf(array $j) {
    foreach ($j['stages'] as $i => $s) { if ($s['status'] === 'current') { return $i + 1; } }
    return 0;
}

fwrite(STDOUT, "\n══ E-09 — شريطُ رحلة الوحدة ══\n");

// ═══ ① خمسُ مراحل ═══
head('① خمسُ مراحلَ من ثلاثَ عشرةَ حالة — الرحلةُ إسقاطٌ لا تخزين');
$j = unit_journey(null, mkEntry());
check(count($j['stages']) === 5, 'خمسُ مراحل: ' . count($j['stages']));
check(labelsOf($j) === array('إدخالُ اليوم', 'اعتمادُ الموقع', 'بطاقاتُ الأطراف',
                             'اعتمادُ المبيعات', 'التحويلُ المالي'),
    'بترتيب الآلة: ' . implode(' ← ', labelsOf($j)));
$tables = $conn->query("SHOW TABLES LIKE '%journey%'")->num_rows;
check($tables === 0, "وصفرُ جدولِ رحلةٍ في القاعدة: {$tables}");

// ═══ ③④ الفخُّ الثاني: الأطرافُ مرحلةٌ واحدةٌ بعدّاد ═══
head('③ الأطرافُ متوازيةٌ — مرحلةٌ واحدةٌ بعدّاد لا ثلاثُ مراحلَ متتالية');
$names = implode('|', labelsOf($j));
check(mb_strpos($names, 'المورد') === false && mb_strpos($names, 'المشغّل') === false,
    'لا مرحلةَ باسم «المورد» ولا «المشغّل» في الشريط — فلا ترتيبَ موهوم');
$p = stageAt($j, 3);
check(mb_strpos($p['owner'], 'المورد') !== false && mb_strpos($p['owner'], 'المشغّل') !== false,
    'وأصحابُها مجتمعون في مرحلةٍ واحدة: ' . $p['owner']);

head('④ العدّادُ من دالة الخدمة — المطلوبون مَن حضر مرجعُه');
$half = unit_journey(null, mkEntry(array('state' => 'parties_review')),
                     array(mkApp('site'), mkApp('supplier')));
check(stageAt($half, 3)['note'] === '1 من 2',
    'واقعةٌ بلا مشرفٍ اعتمدها المورد: «' . stageAt($half, 3)['note'] . '» — من 2 لا من 3');
$three = unit_journey(null, mkEntry(array('state' => 'parties_review', 'supervisor_id' => 9)),
                      array(mkApp('site'), mkApp('supplier')));
check(stageAt($three, 3)['note'] === '1 من 3',
    'وبمشرفٍ حاضر: «' . stageAt($three, 3)['note'] . '»');
$none = unit_journey(null, mkEntry(array('state' => 'parties_review',
                        'supplier_entity_id' => null, 'operator_employee_id' => null)),
                     array(mkApp('site')));
check(stageAt($none, 3)['note'] === '0 من 1' && mb_strpos(stageAt($none, 3)['owner'], 'الأسطول') !== false,
    'وواقعةٌ بلا طرفٍ خارجيٍّ تنتظر بطاقةَ الأسطول: ' . stageAt($none, 3)['note']);
// المطابقةُ الحاسمة: الشريطُ والآلةُ يقرآن القاعدةَ نفسَها
$svc = \App\Services\Unit\TimesheetEntryService::requiredPartiesOf(
    mkEntry(array('supervisor_id' => 9)));
check($svc === array('supplier', 'operator', 'supervisor'),
    'ودالةُ الخدمة هي المصدر: ' . implode(' · ', $svc));

// ═══ ⑤ الفخُّ الأول: returned لافتةٌ لا مرحلة ═══
head('⑤ `returned` انكسارٌ لا مرحلة');
$ret = unit_journey(null,
    mkEntry(array('state' => 'returned', 'current_round' => 2, 'entry_no' => 'UNT-000918')),
    array(mkApp('site', 'approved', 1, '2026-07-02 09:00:00'),
          mkApp('site', 'returned', 1, '2026-07-03 11:00:00', 'ساعاتُ التوقف بلا سبب')));
check(count($ret['stages']) === 5, 'ولا مرحلةَ سادسةَ اسمُها «أُعيدت»: ' . count($ret['stages']));
check(isset($ret['banner']) && $ret['banner']['kind'] === 'return',
    'بل لافتةٌ بـ`kind=return`');
check(isset($ret['banner']) && mb_strpos($ret['banner']['text'], 'ساعاتُ التوقف بلا سبب') !== false,
    'وسببُها من سجل الاعتماد نصًّا: ' . $ret['banner']['text']);
check(isset($ret['banner']) && mb_strpos($ret['banner']['meta'], 'الجولة 2') !== false,
    'ورقمُ جولتها: ' . $ret['banner']['meta']);
check(isset($ret['banner']) && mb_strpos($ret['banner']['meta'], 'UNT-000918') !== false,
    'وبالرقم نفسه — لا رقمَ جديدٌ للإعادة');
check(currentOf($ret) === 0, 'ولا مرحلةَ مضاءةً أثناء الانكسار — الرحلةُ رجعت لا تقدّمت');

// ═══ ⑥ الجولةُ الميتة ═══
head('⑥ أزمنةُ الجولة الجارية وحدَها');
$r2 = unit_journey(null,
    mkEntry(array('state' => 'parties_review', 'current_round' => 2)),
    array(mkApp('site', 'approved', 1, '2026-07-02 09:00:00'),
          mkApp('supplier', 'approved', 1, '2026-07-02 10:00:00'),   // جولةٌ ماتت
          mkApp('site', 'returned', 1, '2026-07-03 11:00:00', 'أعِد'),
          mkApp('site', 'approved', 2, '2026-07-05 08:00:00')));
check(stageAt($r2, 2)['at'] === '2026-07-05 08:00:00',
    'زمنُ اعتماد الموقع من الجولة 2 لا 1: ' . stageAt($r2, 2)['at']);
check(stageAt($r2, 3)['note'] === '0 من 2',
    'واعتمادُ المورد في الجولة الميتة لا يُحتسب: ' . stageAt($r2, 3)['note']);

// ═══ ② الوقائعُ الحيّة ═══
head('② الوقائعُ الحيّة — المرحلةُ تتبع الحالةَ الفعلية');
$live = $conn->query(
    "SELECT e.*, (SELECT COUNT(*) FROM unit_approvals a WHERE a.entry_id=e.id) apps
       FROM unit_entries e WHERE e.company_id={$CO}
        AND e.state IN ('submitted','parties_approved','sales_approved')
      ORDER BY FIELD(e.state,'submitted','parties_approved','sales_approved'), e.id
      LIMIT 300")->fetch_all(MYSQLI_ASSOC);
$seen = array();
foreach ($live as $e) {
    if (isset($seen[$e['state']])) { continue; }
    $seen[$e['state']] = $e;
}
check(count($seen) === 3, 'ثلاثُ حالاتٍ حيّةٍ للاختبار: ' . implode(' · ', array_keys($seen)));

$expectCurrent = array('submitted' => 1, 'parties_approved' => 4, 'sales_approved' => 5);
foreach ($seen as $state => $e) {
    $apps = $conn->query("SELECT stage, decision, round_no, decided_at, note
                            FROM unit_approvals WHERE entry_id=" . intval($e['id']) . " ORDER BY id")
                 ->fetch_all(MYSQLI_ASSOC);
    $jj = unit_journey(null, $e, $apps);
    $cur = currentOf($jj);
    // `submitted` وقد اعتُمد موقعُها تُعرض عند الأطراف — الشريطُ يعرض الواقع
    $exp = $expectCurrent[$state];
    $hasSite = false;
    foreach ($apps as $a) { if ($a['stage'] === 'site' && $a['decision'] === 'approved') { $hasSite = true; } }
    if ($state === 'submitted' && $hasSite) { $exp = 3; }
    check($cur === $exp, "«{$state}» (#{$e['entry_no']}) عند المرحلة {$cur} (المتوقَّع {$exp})");
}
// الواقعةُ المكتملةُ أطرافُها: عدّادٌ منتهٍ لا «1 من 2»
$pa = $seen['parties_approved'];
$paApps = $conn->query("SELECT stage, decision, round_no, decided_at, note FROM unit_approvals
                          WHERE entry_id=" . intval($pa['id']) . " ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$paJ = unit_journey(null, $pa, $paApps);
check(stageAt($paJ, 3)['status'] === 'done', 'ومرحلةُ الأطراف منجَزةٌ لمن اكتملت بطاقاتُه');
check(isset(stageAt($paJ, 3)['at']), 'ولها زمنٌ من السجل الإلحاقي: ' . (stageAt($paJ, 3)['at'] ?? '—'));

// ═══ ⑦ التوقفُ والوقفة ═══
head('⑦ التوقفُ يُطفئ ما بعده · والوقفةُ لا تُرجع');
$stop = unit_journey(null, mkEntry(array('state' => 'rejected')),
                     array(mkApp('site', 'approved', 1, '2026-07-02 09:00:00')));
check(isset($stop['banner']) && $stop['banner']['kind'] === 'stop', 'المرفوضةُ لافتةُ توقف');
$offs = 0;
foreach ($stop['stages'] as $s) { if ($s['status'] === 'off') { $offs++; } }
check($offs > 0 && currentOf($stop) === 0, "وما بعدها مُطفأٌ لا «قادم»: {$offs} مرحلةً");
check(!isset($stop['next']), 'ولا «خطوةٌ تالية» لرحلةٍ توقفت');

$hold = unit_journey(null, mkEntry(array('state' => 'on_hold')),
                     array(mkApp('site', 'approved', 1, '2026-07-02 09:00:00')));
check(isset($hold['banner']) && mb_strpos($hold['banner']['title'], 'موقوفة') !== false,
    'والموقوفةُ مؤقتًا لافتتُها تقول ذلك: ' . $hold['banner']['title']);
check(stageAt($hold, 2)['status'] === 'done',
    'ولم تُرجَع إلى الوراء — ما أُنجز يبقى منجَزًا');

// ═══ ⑧ الاكتمال ═══
head('⑧ `converted` — اكتمالُ الرحلة');
$done = unit_journey(null,
    mkEntry(array('state' => 'converted', 'converted_at' => '2026-07-10 12:00:00')),
    array(mkApp('site'), mkApp('supplier'), mkApp('operator'), mkApp('sales')));
check(isset($done['banner']) && $done['banner']['kind'] === 'done', 'لافتةُ اكتمال');
check(currentOf($done) === 0, 'ولا مرحلةَ حالية — كلُّها منجَزة');
$allDone = true;
foreach ($done['stages'] as $s) { if ($s['status'] !== 'done') { $allDone = false; } }
check($allDone, 'والخمسُ كلُّهنّ `done`');
check(stageAt($done, 5)['at'] === '2026-07-10 12:00:00',
    'وزمنُ التحويل من الواقعة نفسِها: ' . stageAt($done, 5)['at']);

// ═══ ⑨ لغةُ المهمة ═══
head('⑨ لغةُ المهمة لا لغةُ التحليل');
$blob = '';
foreach (array($j, $ret, $done, $half, $stop) as $x) {
    $blob .= json_encode($x, JSON_UNESCAPED_UNICODE);
}
$leaks = array();
foreach (array('supplier', 'operator', 'entity_type', 'sync_uuid', 'المروحة',
               'الإسقاط', 'unit_entries', 'parties_review') as $w) {
    if (mb_strpos($blob, $w) !== false) { $leaks[] = $w; }
}
check(empty($leaks), 'صفرُ لفظِ تحليلٍ في المخرَج' . ($leaks ? ' (تسرّب: ' . implode(' · ', $leaks) . ')' : ''));

// ═══ ⑩ المسارُ الحيّ ═══
head('⑩ المسارُ الحيّ — الشريطُ يُصيَّر في شاشة التفاصيل');
$JAR = sys_get_temp_dir() . '/ems_unitjourney_cookies.txt';
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
list($lc) = req(BASE . '/login.php', array('username' => 'محمد', 'password' => '12345678',
                                           'csrf_token' => $lm[1] ?? ''));
check($lc === 200, 'دخولُ «محمد»');

// صفُّ دوامٍ له مرآةٌ متقدمة (الأطرافُ اعتمدت) — فالشريطُ يعرض تقدّمًا حقيقيًّا
$tsRow = $conn->query("SELECT SUBSTRING(e.sync_uuid, 4) ts, e.state, e.entry_no
                         FROM unit_entries e
                        WHERE e.company_id={$CO} AND e.sync_uuid LIKE 'ts:%'
                          AND e.state IN ('parties_approved','sales_approved')
                        ORDER BY e.id DESC LIMIT 1")->fetch_assoc();
if (!$tsRow) {
    bad('لا صفَّ دوامٍ بمرآةٍ متقدمةٍ للاختبار الحيّ');
} else {
    list($dc, $page) = req(BASE . '/Timesheet/timesheet_details.php?id=' . intval($tsRow['ts']));
    check($dc === 200, 'وشاشةُ تفاصيل الوحدة صُيِّرت (200)');
    // ⚠️ `ems-journey` وحدَها تطابق **رابطَ ورقة الأنماط** في الترويسة، فالبحثُ
    // بها يمرّ على صفحةٍ بلا شريط. العلامةُ الفارقةُ مسارُ الشريط المصيَّر.
    check(mb_strpos($page, 'ems-journey-track') !== false, 'والشريطُ حاضرٌ في الصفحة');
    check(mb_strpos($page, 'اعتمادُ الموقع') !== false && mb_strpos($page, 'بطاقاتُ الأطراف') !== false,
        'بمراحله بلغة المهمة');
    check(mb_strpos($page, 'التحويلُ المالي') !== false, 'وآخرُها التحويلُ المالي');
    check(preg_match('/ems-jstep is-done/u', $page) === 1 || substr_count($page, 'is-done') >= 2,
        'ومراحلُه المنجَزةُ معلَّمةٌ منجَزةً (' . substr_count($page, 'is-done') . ' مرحلة)');

    // صفُّ دوامٍ بلا مرآة: لا شريطَ مخترَع
    $noMirror = $conn->query("SELECT t.id FROM timesheet t
                               WHERE t.company_id={$CO}
                                 AND NOT EXISTS (SELECT 1 FROM unit_entries u
                                                  WHERE u.sync_uuid = CONCAT('ts:', t.id))
                               LIMIT 1")->fetch_assoc();
    if ($noMirror) {
        list($nc, $npage) = req(BASE . '/Timesheet/timesheet_details.php?id=' . intval($noMirror['id']));
        check($nc === 200 && mb_strpos($npage, 'ems-journey-track') === false,
            'وصفٌّ بلا سجلٍّ قانونيٍّ لا يُعرض له شريطٌ مخترَع (#' . intval($noMirror['id']) . ')');
    } else { ok('(كلُّ الصفوف لها مرآة — لا حالةَ اختبارٍ للغياب)'); }
}

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
