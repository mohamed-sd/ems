<?php
/**
 * tools/repair01_w10_journey.php — رحلةُ الشقّ (‏§٦-أ من ملفِّ المرحلة)
 * ═══════════════════════════════════════════════════════════════════════════
 * أربعةُ أشواطٍ متتابعة:
 *   ① سطحٌ محاسبيٌّ يصل مالكَه `DEP-05`
 *   ② سطحُ تنفيذٍ نقديٍّ يصل مالكَه `DEP-06`
 *   ③ رابطٌ قديمٌ لأيٍّ منهما ما زال يعمل عبرَ الجسر
 *   ④ سجلُّ تدقيقٍ قديمٌ يُقرأ بمعرّفِه الأصليِّ بلا كسر
 *
 * ◆ **ولكلِّ محطّةٍ مستهلكٌ بالاسمِ وأثرٌ تجاريٌّ مقيس** — لا «الحدثُ سُجِّل».
 * ◆ **ولا تترك الرحلةُ أثرًا**: الواقعتانِ المنشورتانِ تُحذفانِ بمعرّفَيهما في
 *   نهايةِ الشوطِ، والبوّابةُ تقيس البقيّةَ فتسقط إن بقيت واحدة.
 * ◆ **ومُعرِّفُ الجولةِ بدقّةِ الميكروثانية** — فجولتانِ في ثانيةٍ واحدةٍ لا تختلطان.
 *
 * التشغيل: php tools/repair01_w10_journey.php
 * المخرَج : سطرٌ `RUN=W10J-…` تقرؤه البوّابةُ — ولا تقرأ «آخرَ صفٍّ في الجدول».
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w10_scan.php';
require_once $ROOT . '/app/Services/Governance/DeptSplitService.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

use App\Services\Governance\DeptSplitService as SPL;

$E   = function ($s) use ($conn) { return "'" . $conn->real_escape_string((string) $s) . "'"; };
$one = function ($sql) use ($conn) { return repair01_w10_one($conn, $sql); };

$RUN = 'W10J-' . str_replace('.', '', sprintf('%.6F', microtime(true)));
$seq = 0; $pass = 0; $fail = 0; $published = array();

$log = function ($leg, $station, $actor, $consumer, $effect, $ok, $detail)
        use ($conn, $E, $RUN, &$seq, &$pass, &$fail) {
    $seq++;
    if ($ok) { $pass++; } else { $fail++; }
    $conn->query("INSERT INTO repair01_w10_journey (run_id, seq, leg, station, actor, consumer,
                     business_effect, passed, detail, at)
                  VALUES (" . $E($RUN) . ", $seq, " . $E($leg) . ", " . $E(mb_substr($station, 0, 185)) . ", "
                  . $E($actor) . ", " . $E($consumer) . ", " . $E(mb_substr($effect, 0, 390)) . ", "
                  . ($ok ? 1 : 0) . ", " . $E(mb_substr($detail, 0, 590)) . ", NOW(6))");
    printf("  %s %-2d %-46s %-34s %s\n", $ok ? '✔' : '✘', $seq, mb_substr($station, 0, 46),
           mb_substr($consumer, 0, 34), mb_substr($detail, 0, 60));
};

echo "═══ رحلةُ الشقّ — RPR-W10 ═══\nRUN=$RUN\n\n";

/* ── اختيارُ محطّتَي الشوطَينِ من الدفترِ نفسِه لا من اسمٍ مكتوبٍ هنا ────── */
$accRow = $conn->query("SELECT * FROM repair01_w10_split
                         WHERE resolved_code = 'DEP-05' AND route <> '' AND in_registry = 1
                           AND split_rule = 'W10_REQ_SURFACE_MATCH'
                         ORDER BY scope_key LIMIT 1");
$accRow = $accRow ? $accRow->fetch_assoc() : null;
$treRow = $conn->query("SELECT * FROM repair01_w10_split
                         WHERE resolved_code = 'DEP-06' AND route <> '' AND in_registry = 1
                         ORDER BY scope_key LIMIT 1");
$treRow = $treRow ? $treRow->fetch_assoc() : null;
if (!$accRow || !$treRow) { echo "✘ لا محطّةَ صالحةٌ في الدفتر — شغّلْ tools/repair01_w10_apply.php\n"; exit(1); }

/* ── كنسُ العائلةِ قبل الانعقاد ─────────────────────────────────────────
   ⚠ **وجولةٌ سقطت في منتصفِها تترك وقائعَها**: النظافةُ في آخرِ الشوطِ لا تقع إن
     انقطع الشوط، فتُقرأ بقاياها أثرًا لجولةٍ لاحقةٍ نظيفة. فالكنسُ **بعائلةِ
     مفاتيحِ الأحداثِ** في أوّلِ الشوطِ لا بمُعرِّفِ الجولةِ وحدَه. */
$fam = array();
foreach (repair01_w10_stage_events() as $code) { $fam[] = "'" . $conn->real_escape_string($code) . "'"; }
$famIn = implode(',', $fam);
$conn->query("DELETE FROM ems_event_deliveries WHERE outbox_id IN
               (SELECT id FROM ems_event_outbox WHERE event_code IN ($famIn))");
$conn->query("DELETE FROM ems_event_outbox WHERE event_code IN ($famIn)");
$conn->query("DELETE FROM ems_business_events WHERE event_key IN ($famIn)");

$company = (int) $one("SELECT company_id FROM users WHERE COALESCE(is_deleted,0)=0
                        GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
if ($company <= 0) { $company = 1; }
$actor = (int) $one("SELECT id FROM users WHERE company_id = $company AND COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 1");
if ($actor <= 0) { $actor = 1; }

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ① — سطحٌ محاسبيٌّ يصل مالكَه DEP-05
   ══════════════════════════════════════════════════════════════════════════ */
echo "① سطحٌ محاسبيٌّ ⇐ " . $accRow['route'] . "\n";
$legA = 'ACCOUNTING_SURFACE';

$log($legA, 'السطح في دفتر الشق بحكم وقاعدة ومرساة', 'مالك الحملة', 'repair01_w10_split',
     'الحكم ' . $accRow['resolved_code'] . ' بقاعدة ' . $accRow['split_rule'],
     $accRow['split_rule'] !== '' && $accRow['split_why'] !== '' && $accRow['anchor_ref'] !== '',
     'مرساة ' . $accRow['anchor_ref']);

$rv = SPL::resolveOwner($conn, $accRow['route']);
$log($legA, 'الخدمة تحل مالكه من الدفتر لا من اسم مجلد', 'محاسب الادارة المالية',
     'DeptSplitService::resolveOwner', 'الجواب ' . ($rv !== '' ? $rv : 'لا شيء'),
     $rv === 'DEP-05', 'المسار ' . $accRow['route']);

$regCode = (string) $one("SELECT owner_code FROM repair01_screen_registry
                           WHERE screen_id = " . $E($accRow['scope_key']));
$log($legA, 'سجل الشاشات يحمل رمز الشق نفسه', 'مالك الحملة', 'repair01_screen_registry',
     'owner_code = ' . $regCode, $regCode === 'DEP-05', 'المعرف ' . $accRow['scope_key']);

$surfCode = (string) $one("SELECT canonical_code FROM repair01_surfaces
                            WHERE screen_id = " . $E($accRow['scope_key'])
                          . " AND dept_legacy = " . $E($accRow['legacy_unit']) . " LIMIT 1");
$log($legA, 'دفتر الاسطح لا يخالف سجل الشاشات', 'مالك الحملة', 'repair01_surfaces',
     $surfCode === '' ? 'السطح ليس في دفتر الاسطح — ولا تناقض' : 'canonical_code = ' . $surfCode,
     $surfCode === '' || $surfCode === 'DEP-05', 'المقامان مختلفان ولا يدمجان');

$legacyKept = (int) $one("SELECT COUNT(*) FROM nav_canonical
                           WHERE route = " . $E($accRow['route']) . " AND owner_dept = " . $E($accRow['legacy_unit']));
$log($legA, 'السجل المعياري يبقي الاسم القديم نصا كما هو', 'مالك الملاحة', 'nav_canonical',
     'صفوف بالاسم القديم ' . $legacyKept, $legacyKept > 0, 'الجسر يترجم ولا يستبدل');

$tr = SPL::translateLegacy($conn, 'nav09_file_map', $accRow['route'], $accRow['legacy_unit']);
$deptName = (string) $one("SELECT name_ar FROM repair01_departments
                            WHERE canonical_code = " . $E($tr['code'] !== '' ? $tr['code'] : 'DEP-05'));
$log($legA, 'عقد الشاشة يعرض الشق المحلول لا الوحدة الام', 'مستخدم الشاشة',
     'includes/screen_contract.php', 'ضمن نطاق ' . $deptName,
     $deptName !== '' && $deptName !== $accRow['legacy_unit'], 'قاعدة الجسر ' . $tr['rule']);

$perm = (int) $one("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                     WHERE m.code = " . $E($accRow['route']) . " AND rp.can_view = 1");
$log($legA, 'منح الصلاحية مقيس على المسار لا على الاسم', 'مدير الصلاحيات', 'role_permissions',
     'ادوار ترى الشاشة ' . $perm, $perm > 0, 'الظهور بالصلاحية لا بالاخفاء');

$ct = SPL::assertContract($conn, SPL::EV_OWNER_REASSIGNED);
$log($legA, 'حدث اعادة الاسناد له عقد اثر مسجل قبل اطلاقه', 'مالك الحملة', 'repair01_events',
     'العقد ' . ($ct['ok'] ? 'مسجل كاملا' : 'ناقص'), $ct['ok'], SPL::EV_OWNER_REASSIGNED);

$pub = SPL::publish($conn, SPL::EV_OWNER_REASSIGNED, array(
    'run' => $RUN, 'screen_id' => $accRow['scope_key'], 'route' => $accRow['route'],
    'code_before' => $accRow['reg_code_before'], 'code_after' => $accRow['resolved_code'],
    'split_rule' => $accRow['split_rule'], 'anchor_ref' => $accRow['anchor_ref']), $company, $actor, (int) substr($accRow['scope_key'], 4));
if (!empty($pub['event_id'])) { $published[] = (int) $pub['event_id']; }
$log($legA, 'الحدث ينشر ويقيد واقعة حوكمة بمفتاح عطالة', 'مالك الحملة', 'ems_business_events',
     'واقعة رقم ' . (int) ($pub['event_id'] ?? 0), $pub['ok'] && (int) ($pub['event_id'] ?? 0) > 0,
     'رمز الرد ' . ($pub['code'] !== '' ? $pub['code'] : 'لا رد'));

$pub2 = SPL::publish($conn, SPL::EV_OWNER_REASSIGNED, array(
    'run' => $RUN, 'screen_id' => $accRow['scope_key'], 'route' => $accRow['route'],
    'code_before' => $accRow['reg_code_before'], 'code_after' => $accRow['resolved_code'],
    'split_rule' => $accRow['split_rule'], 'anchor_ref' => $accRow['anchor_ref']), $company, $actor, (int) substr($accRow['scope_key'], 4));
$dup = (int) $one("SELECT COUNT(*) FROM ems_business_events
                    WHERE event_key = " . $E(SPL::EV_OWNER_REASSIGNED)
                  . " AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.run')) = " . $E($RUN));
if (!empty($pub2['event_id']) && (int) $pub2['event_id'] !== (int) ($pub['event_id'] ?? 0)) {
    $published[] = (int) $pub2['event_id'];
}
$log($legA, 'التكرار لا ينشئ واقعة ثانية — العطالة تعمل', 'مالك الحملة', 'ems_business_events',
     'وقائع بهذه الجولة ' . $dup, $dup === 1, 'مفتاح منع التكرار من الحمولة');

/* ── المستهلكُ المسجَّلُ يُنفَّذ ويترك أثرًا مقيسًا (‏§46) ────────────────── */
require_once $ROOT . '/app/Services/Governance/SplitProjectionConsumer.php';
$cons = new \App\Services\Governance\SplitProjectionConsumer();
$conn->query("UPDATE repair01_w10_split SET verified_at = NULL, verify_ref = ''
               WHERE scope_key = " . $E($accRow['scope_key']));
$res1 = $cons->verifyOwner(array('event_key' => SPL::EV_OWNER_REASSIGNED,
    'payload' => array('screen_id' => $accRow['scope_key'])), $conn);
$stamped = (int) $one("SELECT COUNT(*) FROM repair01_w10_split
                        WHERE scope_key = " . $E($accRow['scope_key']) . " AND verified_at IS NOT NULL");
$log($legA, 'المستهلك المسجل ينفذ ويترك اثره في دفتر الشق', 'مستهلك الحدث',
     'SplitProjectionConsumer::verifyOwner', 'صفوف مختومة متحققة ' . $stamped,
     $stamped === 1 && strpos($res1, 'W10:VERIFIED:') === 0, 'الرد ' . $res1);

$subs = (int) $one("SELECT COUNT(*) FROM event_consumers
                     WHERE event_name = " . $E(SPL::EV_OWNER_REASSIGNED) . " AND active = 1");
$log($legA, 'الجذر يرفض النشر لحدث بلا مشترك نشط', 'الجذر المحايد', 'event_consumers',
     'مشتركون نشطون ' . $subs, $subs > 0, 'BUS_NO_CONSUMER · CK-11');

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ② — سطحُ تنفيذٍ نقديٍّ يصل مالكَه DEP-06
   ══════════════════════════════════════════════════════════════════════════ */
echo "\n② سطحُ تنفيذٍ نقديّ ⇐ " . $treRow['route'] . "\n";
$legB = 'TREASURY_SURFACE';

$log($legB, 'السطح في دفتر الشق بحكم وقاعدة ومرساة', 'امين الخزينة', 'repair01_w10_split',
     'الحكم ' . $treRow['resolved_code'] . ' بقاعدة ' . $treRow['split_rule'],
     $treRow['split_rule'] !== '' && $treRow['split_why'] !== '',
     'مرساة ' . $treRow['anchor_ref']);

$rv2 = SPL::resolveOwner($conn, $treRow['route']);
$log($legB, 'الخدمة تحل مالكه الى الشق المنفذ', 'امين الخزينة', 'DeptSplitService::resolveOwner',
     'الجواب ' . ($rv2 !== '' ? $rv2 : 'لا شيء'), $rv2 === 'DEP-06', 'المسار ' . $treRow['route']);

$regCode2 = (string) $one("SELECT owner_code FROM repair01_screen_registry
                            WHERE screen_id = " . $E($treRow['scope_key']));
$log($legB, 'سجل الشاشات يحمل رمز الشق المنفذ', 'مالك الحملة', 'repair01_screen_registry',
     'owner_code = ' . $regCode2, $regCode2 === 'DEP-06', 'المعرف ' . $treRow['scope_key']);

$log($legB, 'الشقان لا يشتركان في رمز واحد', 'مالك الحملة', 'repair01_w10_split',
     'المحاسبي ' . $rv . ' والنقدي ' . $rv2, $rv !== '' && $rv2 !== '' && $rv !== $rv2,
     'الاعتراف غير التنفيذ');

$sideCounts = array();
$r = $conn->query("SELECT resolved_code, COUNT(*) c FROM repair01_w10_split
                    WHERE legacy_unit = " . $E($treRow['legacy_unit']) . " GROUP BY resolved_code");
while ($r && $x = $r->fetch_assoc()) { $sideCounts[$x['resolved_code']] = (int) $x['c']; }
$sumOk = (($sideCounts['DEP-05'] ?? 0) + ($sideCounts['DEP-06'] ?? 0))
       === (int) $one("SELECT COUNT(*) FROM repair01_w10_split WHERE legacy_unit = " . $E($treRow['legacy_unit']));
$log($legB, 'مجموع الشقين يساوي مقام الوحدة الاصلية', 'مالك الحملة', 'repair01_w10_split',
     'DEP-05 ' . ($sideCounts['DEP-05'] ?? 0) . ' + DEP-06 ' . ($sideCounts['DEP-06'] ?? 0),
     $sumOk && ($sideCounts['DEP-06'] ?? 0) > 0, 'لا سطح ضاع ولا سطح تكرر');

$ct2 = SPL::assertContract($conn, SPL::EV_SPLIT_APPLIED);
$log($legB, 'حدث تطبيق الشق له عقد اثر مسجل', 'مالك الحملة', 'repair01_events',
     'العقد ' . ($ct2['ok'] ? 'مسجل كاملا' : 'ناقص'), $ct2['ok'], SPL::EV_SPLIT_APPLIED);

$pub3 = SPL::publish($conn, SPL::EV_SPLIT_APPLIED, array(
    'run' => $RUN, 'legacy_unit' => $treRow['legacy_unit'], 'left_code' => 'DEP-05',
    'right_code' => 'DEP-06', 'surfaces_left' => $sideCounts['DEP-05'] ?? 0,
    'surfaces_right' => $sideCounts['DEP-06'] ?? 0), $company, $actor, 1);
if (!empty($pub3['event_id'])) { $published[] = (int) $pub3['event_id']; }
$log($legB, 'واقعة شق الوحدة تقيد بحمولتها الدنيا', 'مالك الحملة', 'ems_business_events',
     'واقعة رقم ' . (int) ($pub3['event_id'] ?? 0), $pub3['ok'] && (int) ($pub3['event_id'] ?? 0) > 0,
     'رمز الرد ' . ($pub3['code'] !== '' ? $pub3['code'] : 'لا رد'));

$cnt = $cons->refreshSplitCounters(array('event_key' => SPL::EV_SPLIT_APPLIED,
    'payload' => array('legacy_unit' => $treRow['legacy_unit'])), $conn);
$log($legB, 'مستهلك الشق يعيد بناء العدادين من الدفتر', 'مستهلك الحدث',
     'SplitProjectionConsumer::refreshSplitCounters', 'العدادان ' . $cnt,
     strpos($cnt, 'W10:COUNTERS:') === 0 && strpos($cnt, 'DEP-06=') !== false,
     'مشتق لا منقول من الحمولة');

$conf = SPL::detectConflict($conn);
$log($legB, 'كاشف التنازع لا يجد سجلين متفرقين', 'مالك الحملة', 'DeptSplitService::detectConflict',
     'اسطح متنازعة ' . count($conf), count($conf) === 0, 'السجلان يتفقان على كل سطح مشترك');

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ③ — الرابطُ القديمُ ما زال يعمل عبرَ الجسر
   ══════════════════════════════════════════════════════════════════════════ */
echo "\n③ الرابطُ القديمُ عبرَ الجسر\n";
$legC = 'LEGACY_BRIDGE';

$brAll = (int) $one("SELECT COUNT(*) FROM repair01_w10_bridge");
$brNoRule = (int) $one("SELECT COUNT(*) FROM repair01_w10_bridge WHERE bridge_rule='' OR bridge_why='' OR probe_sql=''");
$log($legC, 'كل مؤشر حي باسم الوحدة الام له صف في الجسر', 'مالك الحملة', 'repair01_w10_bridge',
     'مؤشرات مترجمة ' . $brAll . ' · بلا قاعدة ' . $brNoRule, $brAll > 0 && $brNoRule === 0,
     'ثلاثة جداول حية');

/* الإثباتُ يُشغَّل فعلًا — فالرابطُ القديمُ يُقاس ولا يُدَّعى */
$probeOk = 0; $probeAll = 0;
$r = $conn->query("SELECT host_table, pointer_key, probe_sql FROM repair01_w10_bridge");
$rows = array();
while ($r && $x = $r->fetch_assoc()) { $rows[] = $x; }
foreach ($rows as $x) {
    $probeAll++;
    $v = repair01_w10_one($conn, (string) $x['probe_sql']);
    if ($v !== null && (int) $v > 0) { $probeOk++; }
}
$log($legC, 'استعلام الاثبات يشتغل ويجد الصف الحي كما هو', 'المراجع الداخلي المستقل',
     'nav_canonical · nav09_file_map · request_types',
     'روابط قديمة ما زالت تقرأ ' . $probeOk . ' من ' . $probeAll, $probeAll > 0 && $probeOk === $probeAll,
     'لا رابط كسر بالشق');

$byTable = array();
$r = $conn->query("SELECT host_table, resolved_code, COUNT(*) c FROM repair01_w10_bridge
                    GROUP BY host_table, resolved_code");
while ($r && $x = $r->fetch_assoc()) { $byTable[$x['host_table']][$x['resolved_code']] = (int) $x['c']; }
foreach (array('nav_canonical', 'nav09_file_map', 'request_types') as $t) {
    $have = isset($byTable[$t]);
    $eff = array();
    foreach (($byTable[$t] ?? array()) as $c => $n) { $eff[] = $c . ':' . $n; }
    $log($legC, 'مؤشرات ' . $t . ' تحل الى شقها', 'مالك الحملة', $t,
         $eff ? implode(' · ', $eff) : 'لا مؤشر', $have, 'الاسم الحي يبقى مفتاحا');
}

$rt = SPL::translateLegacy($conn, 'request_types', (string) $one(
    "SELECT pointer_key FROM repair01_w10_bridge WHERE host_table='request_types' ORDER BY pointer_key LIMIT 1"));
$log($legC, 'قاموس الطلبات يدخل من الشق الام لا من ملكية جديدة', 'موظف رافع الطلب', 'request_types',
     'الحل ' . $rt['code'] . ' بقاعدة ' . $rt['rule'], $rt['code'] !== '',
     'صف الدفع المعتمد اسقاط فوق طلبات الادارات');

$ov = SPL::overwriteLegacyPointer('nav_canonical', 'Finance/journal_form_fin.php', 'DEP-05');
$log($legC, 'محاولة دهس الاسم القديم في جدول حي ترد برمزها', 'مالك الحملة',
     'DeptSplitService::overwriteLegacyPointer', 'الرد ' . $ov['code'],
     $ov['ok'] === false && $ov['code'] === SPL::LEGACY_POINTER_OVERWRITE_FORBIDDEN,
     'الجسر يترجم ولا يستبدل');

$brOne = $conn->query("SELECT host_table, pointer_key FROM repair01_w10_bridge
                        WHERE host_table = 'nav_canonical' ORDER BY id LIMIT 1");
$brOne = $brOne ? $brOne->fetch_assoc() : array('host_table' => '', 'pointer_key' => '');
$bv = $cons->verifyBridge(array('event_key' => SPL::EV_POINTER_TRANSLATED,
    'payload' => $brOne), $conn);
$log($legC, 'مستهلك الترجمة يشغل اثبات الرابط القديم', 'مستهلك الحدث',
     'SplitProjectionConsumer::verifyBridge', 'الرد ' . $bv,
     strpos($bv, 'W10:BRIDGE_ALIVE:') === 0, 'المؤشر ' . $brOne['pointer_key']);

$sbSid = (string) $one("SELECT screen_id FROM repair01_w10_sidebar WHERE s7_linked = 1 ORDER BY screen_id LIMIT 1");
$sv = $cons->verifySidebarLink(array('event_key' => SPL::EV_SIDEBAR_REPLACED,
    'payload' => array('screen_id' => $sbSid)), $conn);
$log($legC, 'مستهلك السايدبار يتحقق من الربط بالمعرف المعياري', 'مستهلك الحدث',
     'SplitProjectionConsumer::verifySidebarLink', 'الرد ' . $sv,
     strpos($sv, 'W10:LINKED:') === 0, 'المعرف ' . $sbSid);

$ct3 = SPL::assertContract($conn, SPL::EV_POINTER_TRANSLATED);
$log($legC, 'حدث الترجمة له عقد اثر مسجل بمستهلكيه بالاسم', 'مالك الحملة', 'repair01_events',
     'العقد ' . ($ct3['ok'] ? 'مسجل كاملا' : 'ناقص'), $ct3['ok'], SPL::EV_POINTER_TRANSLATED);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ④ — سجلُّ التدقيقِ يُقرأ بمعرّفِه الأصليّ
   ══════════════════════════════════════════════════════════════════════════ */
echo "\n④ سجلُّ التدقيقِ بمعرّفِه الأصليّ\n";
$legD = 'AUDIT_READBACK';

$evId = (int) $one("SELECT id FROM ems_business_events ORDER BY id ASC LIMIT 1");
$evRead = SPL::readAuditByOriginalId($conn, 'ems_business_events', $evId);
$log($legD, 'واقعة اعمال قديمة تقرا بمعرفها الاصلي', 'المراجع الداخلي المستقل', 'ems_business_events',
     'المعرف ' . $evId . ' يقرا ' . (int) $evRead, $evId > 0 && (int) $evRead === 1,
     'لا معرف بديل ولا خريطة ترجمة معرفات');

$alId = (int) $one("SELECT id FROM activity_logs ORDER BY id ASC LIMIT 1");
$alRead = SPL::readAuditByOriginalId($conn, 'activity_logs', $alId);
$log($legD, 'صف سجل تدقيق قديم يقرا بمعرفه الاصلي', 'المراجع الداخلي المستقل', 'activity_logs',
     'المعرف ' . $alId . ' يقرا ' . (int) $alRead, $alId > 0 && (int) $alRead === 1,
     'الشق لم يمس مفتاحا تقنيا');

$rn = SPL::renumberAuditReference('ems_business_events', $evId, $evId + 1);
$log($legD, 'محاولة اعادة ترقيم مرجع تدقيق ترد برمزها', 'مالك الحملة',
     'DeptSplitService::renumberAuditReference', 'الرد ' . $rn['code'],
     $rn['ok'] === false && $rn['code'] === SPL::AUDIT_REFERENCE_RENUMBER_FORBIDDEN,
     'اعادة الترقيم تكسر سلسلة الاثبات');

$aliasN = (int) $one("SELECT COUNT(*) FROM repair01_key_alias
                       WHERE key_code LIKE 'DEP-05%' OR key_code LIKE 'DEP-06%'");
$log($legD, 'لا معرف بديل انشئ للشق', 'مالك الحملة', 'repair01_key_alias',
     'بدائل مفاتيح للشق ' . $aliasN, $aliasN === 0, 'الشق قرار ملكية لا اعادة ترقيم');

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑤ — فصلُ الواجباتِ منفَّذٌ لا مُعلَن
   ══════════════════════════════════════════════════════════════════════════ */
echo "\n⑤ فصلُ الواجبات\n";
$legE = 'SEGREGATION';

$s1 = SPL::assertSeparation(array('decider' => 7, 'applier' => 7));
$log($legE, 'من يحسم الشق لا يطبقه', 'مالك الحملة', 'DeptSplitService::assertSeparation',
     'الرد ' . $s1['code'], !$s1['ok'] && $s1['code'] === SPL::SAME_ACTOR_DECIDE_AND_APPLY,
     'الحسم قرار والتطبيق تنفيذ');

$s2 = SPL::assertSeparation(array('proposer' => 9, 'approver' => 9));
$log($legE, 'من يقترح تغيير مالك لا يعتمده', 'المدير المالي', 'DeptSplitService::assertSeparation',
     'الرد ' . $s2['code'], !$s2['ok'] && $s2['code'] === SPL::SAME_ACTOR_PROPOSE_AND_APPROVE,
     'تغيير المالك يغير من يرى الشاشة');

$s3 = SPL::assertRuled(array('split_rule' => '', 'split_why' => ''));
$log($legE, 'حكم بلا قاعدة مكتوبة لا يطبق', 'مالك الحملة', 'DeptSplitService::assertRuled',
     'الرد ' . $s3['code'], !$s3['ok'] && $s3['code'] === SPL::SPLIT_OWNER_CHANGE_WITHOUT_RULE,
     'مالك بلا قاعدة هو الحسم بترتيب الصفوف');

$s4 = SPL::assertContract($conn, 'W10.NOT.A.REAL.EVENT');
$log($legE, 'حدث بلا عقد اثر مسجل لا ينفذ', 'مالك الحملة', 'DeptSplitService::assertContract',
     'الرد ' . $s4['code'], !$s4['ok'] && $s4['code'] === SPL::EVENT_WITHOUT_RECORDED_CONTRACT,
     'العقد قبل اول اطلاق');

$s5 = SPL::assertSeparation(array('decider' => 7, 'applier' => 8, 'proposer' => 9, 'approver' => 10));
$log($legE, 'التركيبة السليمة تمر ولا ترد', 'مالك الحملة', 'DeptSplitService::assertSeparation',
     'العملية تمر', $s5['ok'] === true, 'الحارس يمنع الممنوع ولا يمنع المسموح');

/* ══════════════════════════════════════════════════════════════════════════
   النظافة — الرحلةُ لا تترك أثرًا
   ══════════════════════════════════════════════════════════════════════════ */
/* ⚠ **والنظافةُ بوسمِ الجولةِ لا بمعرّفاتٍ جُمعت**: `publishFact` قد تُرجع العدمَ
     في مسارٍ وتكتب الصفَّ — فجمعُ المعرّفاتِ يترك ما لم يُرجَع، والحذفُ بالوسمِ
     يلتقط كلَّ ما كتبَته الجولة. **والصادرُ والتسليمُ يُنظَّفانِ معه**. */
$ids = array();
$r = $conn->query("SELECT id FROM ems_business_events
                    WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.run')) = " . $E($RUN));
while ($r && $x = $r->fetch_row()) { $ids[] = (int) $x[0]; }
foreach (array_unique(array_merge($ids, $published)) as $id) {
    $conn->query("DELETE FROM ems_event_deliveries WHERE outbox_id IN
                   (SELECT id FROM ems_event_outbox WHERE aggregate_id = " . (int) $id . ")");
    $conn->query("DELETE FROM ems_business_events WHERE id = " . (int) $id);
}
$conn->query("DELETE FROM ems_event_deliveries WHERE outbox_id IN
               (SELECT id FROM ems_event_outbox
                 WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.run')) = " . $E($RUN) . ")");
$conn->query("DELETE FROM ems_event_outbox
               WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.run')) = " . $E($RUN));
$left = (int) $one("SELECT COUNT(*) FROM ems_business_events
                     WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.run')) = " . $E($RUN))
      + (int) $one("SELECT COUNT(*) FROM ems_event_outbox
                     WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.run')) = " . $E($RUN));
$log('CLEANUP', 'الرحلة لا تترك اثرا في سجل الوقائع', 'مالك الحملة', 'ems_business_events',
     'وقائع باقية من هذه الجولة ' . $left, $left === 0, 'نشرت ' . count(array_unique($published)) . ' وحذفت');

echo "\n───────────────────────────────────────────────────────────────\n";
printf("رحلةُ الشقّ: %d/%d محطّة · الجولة %s\n", $pass, $pass + $fail, $RUN);
echo "RUN=$RUN\n";
exit($fail === 0 ? 0 : 1);
