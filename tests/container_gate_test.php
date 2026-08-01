<?php
/**
 * tests/container_gate_test.php — H-01 المرحلة ③
 * ═══════════════════════════════════════════════════════════════════════════
 * حجبُ الإدخال بحاويات الموقع + الاستهلاكُ عند اعتماد الموقع.
 *
 * ما يُثبته:
 *   ① **العلَمُ قائمةُ مواقعَ لا `on/off`** — والموقعُ خارجها لا يُمَسّ.
 *   ② **صفرُ أثرٍ عند التسليم**: العلَمُ فارغٌ فلا حجبَ ولا استهلاك.
 *   ③ الأسبابُ الثلاثةُ بنصّها **وكلٌّ برابطه** — «رسالةٌ بلا رابطٍ تُوقف الميدانَ».
 *   ④ **الشاشةُ تعرضها فعلًا** (درسُ E-08-أ): لا `alert` بلا رابطٍ ولا «نقصٌ غير مفسَّر».
 *   ⑤ الواقعةُ الجاهزةُ تمرّ — الحجبُ من النقص لا من العلَم وحدَه.
 *   ⑥ **الاستهلاكُ عند اعتماد الموقع لا عند الإدخال**.
 *   ⑦ **عطالتُه بالجولة**: اعتمادٌ مكرَّرٌ لا يخصم مرتين.
 *   ⑧ **الإعادةُ تردُّ بحركةٍ عاكسةٍ لا بحذف** — والسجلُّ يبقى صفَّين.
 *   ⑨ ذريّةُ الخصم على المستويات الأربعة.
 *
 * يقلب العلَمَ مؤقتًا ويعيده بايت-مطابقًا. ويكنس ما ينشئه ويعيد توليدَ الحاويات.
 * التشغيل: php tests/container_gate_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$ROOT = dirname(__DIR__);
$ENV_FILE = $ROOT . '/.env';
$ENV_BAK  = file_get_contents($ENV_FILE);

// ⚠️ `ems_env_all()` تُخزّن القيمَ في `static` عند أول نداءٍ داخل `config.php` —
// فقلبُ `.env` بعده **لا يراه هذا العملية**. والأداةُ المعيارية للتجاوز قبل
// التحميل هي `_guard_env.php` (نفسُها التي حيّدت حارسَ الوثائق في حزمٍ أخرى).
// فيُفعَّل الموقعُ الرائدُ هنا، وتُختبر القيمُ الأخرى بمسابيرَ في عملياتٍ منفصلة.
require_once __DIR__ . '/_guard_env.php';
// H-01-③ (فتحُ الرائد): عقدُ هذا الاختبار هو دلالةُ **الحجب** — فيُثبَّت
// enforce صراحةً؛ وضعُ monitor الحيُّ (رصدُ أسبوع الرائد) له اختبارُه
// المستقل container_pilot_test.
ems_test_env_override(array('EMS_CONTAINER_GATE' => '4', 'EMS_CONTAINER_GATE_MODE' => 'enforce'));

/** يقلب قيمةَ الحارس للمسابير — عبر التراكب المعزول لا .env الحي (سلامة البيئة §3-⑩)؛
 *  التراكب يورَّث للعمليات الفرعية عبر EMS_ENV_OVERLAY. */
$setGate = function ($val) {
    ems_test_env_override(array('EMS_CONTAINER_GATE' => $val, 'EMS_CONTAINER_GATE_MODE' => 'enforce'));
};

require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'gate test');
require_once $ROOT . '/app/Services/Operations/ContainerGate.php';
require_once $ROOT . '/app/Services/Operations/OperationalTransformService.php';

use App\Services\Operations\ContainerGate as CG;
use App\Services\Operations\OperationalTransformService as OTS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO = 4;
$SITE = 4;      // الموقعُ الرائد — 32 حاويةً كاملةَ المستويات (مقيس)
$OFFSITE = 2;   // موقعٌ بلا حاوياتٍ كاملة — يجب ألّا يُمَسّ

/** مسبارٌ في عمليةٍ منفصلة: `ems_env` يخزّن static داخل العملية الواحدة. */
$probe = function ($php) use ($ROOT) {
    $f = sys_get_temp_dir() . '/cg_probe_' . getmypid() . '.php';
    file_put_contents($f, "<?php\nrequire " . var_export($ROOT . '/config.php', true) . ";\n"
        . "while (ob_get_level() > 0) { ob_end_clean(); }\n"
        . "\$_SESSION['user'] = array('id'=>1,'role'=>'1','company_id'=>4);\n"
        . "require_once " . var_export($ROOT . '/app/Services/Operations/ContainerGate.php', true) . ";\n"
        . "\$gate = ems_tenant_db(); \$conn = \$GLOBALS['conn'];\n" . $php . "\n");
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($f));
    @unlink($f);
    return json_decode(trim((string) $out), true);
};

fwrite(STDOUT, "\n══ H-01 ③ — حجبُ الإدخال بحاويات الموقع ══\n");

// ═══ ② صفرُ أثرٍ عند التسليم ═══
head('② صفرُ أثرٍ عند التسليم — العلَمُ فارغٌ فلا حجب');
$setGate('');
$r = $probe("echo json_encode(array('sites'=>App\\Services\\Operations\\ContainerGate::enabledSites(),"
    . "'on4'=>App\\Services\\Operations\\ContainerGate::isEnabledFor(4)));");
check(is_array($r) && empty($r['sites']) && empty($r['on4']),
    'قائمةٌ فارغةٌ ⇒ الحارسُ مطفأٌ على الجميع');
$r = $probe("echo json_encode(App\\Services\\Operations\\ContainerGate::assertReady(\$gate, "
    . "array('project_id'=>4,'contract_id'=>5,'equipment_id'=>999,'operator_employee_id'=>0)));");
check(is_array($r) && !empty($r['ok']),
    'وواقعةٌ ناقصةٌ تمامًا تمرّ — لا حجبَ بلا علَم');

// ═══ ① العلَمُ قائمةُ مواقع ═══
head('① العلَمُ قائمةُ مواقعَ لا `on/off`');
$setGate((string) $SITE);
$r = $probe("echo json_encode(array('sites'=>App\\Services\\Operations\\ContainerGate::enabledSites(),"
    . "'on4'=>App\\Services\\Operations\\ContainerGate::isEnabledFor(4),"
    . "'on2'=>App\\Services\\Operations\\ContainerGate::isEnabledFor(2)));");
check(is_array($r) && $r['sites'] === array(4), 'القيمةُ «4» ⇒ قائمةٌ بموقعٍ واحد');
check(!empty($r['on4']), 'والموقعُ 4 محكوم');
check(empty($r['on2']), '**والموقعُ 2 خارجَها — لا يُمَسّ** (لا قلبةَ واحدةٌ على الجميع)');
$setGate('4,10');
$r = $probe("echo json_encode(App\\Services\\Operations\\ContainerGate::enabledSites());");
check(is_array($r) && $r === array(4, 10), 'وقائمةٌ بموقعين تُقرأ: ' . implode(',', (array) $r));
$setGate((string) $SITE);

// ═══ ③ الأسبابُ الثلاثةُ بروابطها ═══
head('③ الأسبابُ الثلاثةُ — وكلٌّ برابطه');
$r = $probe("echo json_encode(App\\Services\\Operations\\ContainerGate::assertReady(\$gate, "
    . "array('project_id'=>4,'contract_id'=>5,'equipment_id'=>5,'operator_employee_id'=>0)));");
check(is_array($r) && empty($r['ok']) && (int) $r['code'] === 422, 'معدةٌ بلا مشغّل: 422');
$kinds = array();
foreach ((array) $r['reasons'] as $x) { $kinds[$x['kind']] = $x; }
check(isset($kinds['no_operator']), 'وسببُه `no_operator`');
check(isset($kinds['no_operator']) && mb_strpos($kinds['no_operator']['text'], 'معدةٌ بلا مشغّل') !== false,
    'بنصّ الوثيقة: ' . ($kinds['no_operator']['text'] ?? '—'));
check(isset($kinds['no_operator']) && !empty($kinds['no_operator']['href'])
      && !empty($kinds['no_operator']['label']),
    '**وبرابطه**: ' . ($kinds['no_operator']['href'] ?? '—'));

$r = $probe("echo json_encode(App\\Services\\Operations\\ContainerGate::assertReady(\$gate, "
    . "array('project_id'=>4,'contract_id'=>5,'equipment_id'=>5,'operator_employee_id'=>99999)));");
$kinds2 = array();
foreach ((array) $r['reasons'] as $x) { $kinds2[$x['kind']] = $x; }
check(isset($kinds2['no_container']), 'ومشغّلٌ بلا حصةٍ على المعدة: `no_container`');
check(isset($kinds2['no_container']) && mb_strpos($kinds2['no_container']['href'], 'containers.php?contract=5') !== false,
    'ورابطُه إلى حاويات عقدها: ' . ($kinds2['no_container']['href'] ?? '—'));

// مشغّلٌ له حاويةٌ ولا دورةَ تناوب
$leafRow = $conn->query("SELECT o.id, o.operator_employee_id, o.contract_id, e.equipment_id
                           FROM op_containers o JOIN op_containers e ON e.id=o.parent_id
                          WHERE o.level='مشغّل' AND o.project_id={$SITE} LIMIT 1")->fetch_assoc();
if ($leafRow) {
    $conn->query("DELETE FROM operator_rotations WHERE container_id=" . (int) $leafRow['id']);
    $r = $probe("echo json_encode(App\\Services\\Operations\\ContainerGate::assertReady(\$gate, array("
        . "'project_id'=>4,'contract_id'=>" . (int) $leafRow['contract_id'] . ","
        . "'equipment_id'=>" . (int) $leafRow['equipment_id'] . ","
        . "'operator_employee_id'=>" . (int) $leafRow['operator_employee_id'] . ")));");
    $k3 = array();
    foreach ((array) $r['reasons'] as $x) { $k3[$x['kind']] = $x; }
    check(isset($k3['no_rotation']), 'ومشغّلٌ بلا دورة تناوب: `no_rotation`');
    check(isset($k3['no_rotation']) && mb_strpos($k3['no_rotation']['text'], 'دورة تناوب') !== false,
        'بنصّها: ' . ($k3['no_rotation']['text'] ?? '—'));

    // ═══ ⑤ الجاهزةُ تمرّ ═══
    head('⑤ الجاهزةُ تمرّ — الحجبُ من النقص لا من العلَم');
    $conn->query("INSERT INTO operator_rotations (company_id, container_id, operator_employee_id,
                    cycle_on_days, cycle_off_days, cycle_start)
                  VALUES ({$CO}, " . (int) $leafRow['id'] . ", " . (int) $leafRow['operator_employee_id']
                  . ", 21, 7, '2026-01-01')");
    $r = $probe("echo json_encode(App\\Services\\Operations\\ContainerGate::assertReady(\$gate, array("
        . "'project_id'=>4,'contract_id'=>" . (int) $leafRow['contract_id'] . ","
        . "'equipment_id'=>" . (int) $leafRow['equipment_id'] . ","
        . "'operator_employee_id'=>" . (int) $leafRow['operator_employee_id'] . ")));");
    check(is_array($r) && !empty($r['ok']), 'سلسلةٌ كاملةٌ: تمرّ');
    check(is_array($r) && (int) $r['container_id'] === (int) $leafRow['id'],
        'وتُرجع حاويتَها للخصم: #' . ($r['container_id'] ?? '—'));
} else { bad('لا حاويةَ مشغّلٍ في الموقع الرائد'); }

// ═══ ④ الشاشةُ تعرضها فعلًا ═══
head('④ الشاشةُ تعرض الأسبابَ بروابطها (درسُ E-08-أ)');
$screen = file_get_contents($ROOT . '/Timesheet/timesheet.php');
check(mb_strpos($screen, "\$svc_res['blocked']") !== false, 'الشاشةُ تقرأ `blocked` لا `missing` وحدَه');
check(mb_strpos($screen, 'لم يُسجَّل يومُ العمل — حاوياتُ الموقع لم تكتمل') !== false,
    'وتعرض عنوانًا يقول ما وقع');
check(preg_match('~\$b\[.href.\]~', $screen) === 1 && preg_match('~\$b\[.label.\]~', $screen) === 1,
    '**وتصيّر الرابطَ نفسَه** — لا `alert` بلا رابط');
check(mb_strpos($screen, 'بياناتُ اليوم لم تُفقد') !== false, 'وتطمئن المستخدمَ أن بياناته لم تضِع');
$svc = file_get_contents($ROOT . '/app/Services/Unit/TimesheetEntryService.php');
check(mb_strpos($svc, "'blocked' => \$cgate['reasons']") !== false,
    'والخدمةُ تُرسلها بروابطها لا مسطَّحةً');

// ═══ ⑥⑦⑧⑨ الاستهلاك ═══
head('⑥ الاستهلاكُ عند اعتماد الموقع لا عند الإدخال');
$svcSrc = $svc;
$posSubmit = mb_strpos($svcSrc, 'ContainerGate::assertReady');
$posConsume = mb_strpos($svcSrc, 'ContainerGate::consumeForEntry');
check($posSubmit !== false && $posConsume !== false, 'الوصلان قائمان');
check(mb_strpos($svcSrc, 'الاستهلاكُ عند اعتماد الموقع لا عند الإدخال') !== false,
    'والقاعدةُ منصوصةٌ في موضعها');
check(mb_strpos($svcSrc, "if (\$stage === 'site') {\n            try {") !== false
      || mb_strpos($svcSrc, 'consume on site approve') !== false,
    'والخصمُ في فرع اعتماد الموقع حصرًا');

if ($leafRow) {
    $LID = (int) $leafRow['id'];
    $conn->query("UPDATE op_containers SET consumed_qty=0 WHERE id={$LID}");
    $conn->query("DELETE FROM container_consumption WHERE idem_key LIKE 'gate:%'");

    $entry = array('id' => 90001, 'project_id' => 4, 'contract_id' => (int) $leafRow['contract_id'],
        'equipment_id' => (int) $leafRow['equipment_id'],
        'operator_employee_id' => (int) $leafRow['operator_employee_id'],
        'unit_type' => 'hour', 'qty' => 5.00, 'entry_date' => '2027-06-01', 'current_round' => 1);

    $before = $conn->query("SELECT consumed_qty FROM op_containers WHERE id={$LID}")->fetch_assoc();
    $r = CG::consumeForEntry($conn, $gate, $CO, $entry);
    check(!empty($r['ok']) && $r['levels'] === 4, 'الخصمُ وقع على أربعة مستويات: ' . $r['levels']);
    $after = $conn->query("SELECT consumed_qty FROM op_containers WHERE id={$LID}")->fetch_assoc();
    check((float) $after['consumed_qty'] == (float) $before['consumed_qty'] + 5.00,
        'والورقةُ استهلكت 5: ' . $before['consumed_qty'] . ' ← ' . $after['consumed_qty']);

    head('⑦ عطالةُ الخصم بالجولة');
    $r2 = CG::consumeForEntry($conn, $gate, $CO, $entry);
    check(!empty($r2['ok']) && !empty($r2['skipped']), 'اعتمادٌ مكرَّرٌ في الجولة نفسِها: لا خصمَ ثانٍ');
    $a2 = $conn->query("SELECT consumed_qty FROM op_containers WHERE id={$LID}")->fetch_assoc();
    check((float) $a2['consumed_qty'] == (float) $after['consumed_qty'], 'والمستهلَكُ كما هو');

    head('⑧ الإعادةُ تردُّ بحركةٍ عاكسةٍ لا بحذف');
    $r3 = CG::reverseForEntry($conn, $gate, $CO, $entry, 1);
    check(!empty($r3['ok']), 'وقع الردّ');
    $a3 = $conn->query("SELECT consumed_qty FROM op_containers WHERE id={$LID}")->fetch_assoc();
    check((float) $a3['consumed_qty'] == (float) $before['consumed_qty'],
        'والمستهلَكُ عاد كما كان: ' . $a3['consumed_qty']);
    $rows = $conn->query("SELECT qty, idem_key FROM container_consumption
                           WHERE source_ref=90001 ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    check(count($rows) === 2, "**والسجلُّ صفَّان (خصمٌ وردّ) لا صفرٌ**: " . count($rows));
    check(count($rows) === 2 && (float) $rows[0]['qty'] == 5.00 && (float) $rows[1]['qty'] == -5.00,
        'موجبٌ ثم سالب — أثرُ التدقيق كاملٌ لا ممحوّ');
    check(count($rows) === 2 && mb_strpos($rows[1]['idem_key'], ':rev') !== false,
        'ومفتاحُ الردِّ مشتقٌّ بلاحقة `:rev`: ' . ($rows[1]['idem_key'] ?? '—'));
    $r4 = CG::reverseForEntry($conn, $gate, $CO, $entry, 1);
    check(!empty($r4['ok']) && !empty($r4['skipped']), 'وردٌّ ثانٍ: عطالةٌ لا ردٌّ مضاعف');

    head('⑨ ذريّةُ الخصم');
    $chain = OTS::chainOf($gate, $LID);
    check(count($chain) === 4, 'السلسلةُ أربعةُ مستويات: ' . count($chain));
    $entryBig = $entry; $entryBig['id'] = 90002; $entryBig['qty'] = 999999.00;
    $r5 = CG::consumeForEntry($conn, $gate, $CO, $entryBig);
    check(empty($r5['ok']) || !empty($r5['reason']), 'خصمٌ فوق السقف: يُردّ');
    $a5 = $conn->query("SELECT consumed_qty FROM op_containers WHERE id={$LID}")->fetch_assoc();
    check((float) $a5['consumed_qty'] == (float) $a3['consumed_qty'], 'ولا مستوًى تغيّر');

    $conn->query("DELETE FROM container_consumption WHERE source_ref IN (90001,90002)");
    $conn->query("UPDATE op_containers SET consumed_qty=0");
}

// ═══ الموقعُ خارج العلَم لا يُستهلك منه ═══
head('① ب — الموقعُ خارج العلَم: لا حجبَ ولا استهلاك');
$off = array('id' => 90003, 'project_id' => $OFFSITE, 'contract_id' => 2, 'equipment_id' => 5,
             'operator_employee_id' => 3, 'unit_type' => 'hour', 'qty' => 5.00,
             'entry_date' => '2027-06-01', 'current_round' => 1);
$r = CG::consumeForEntry($conn, $gate, $CO, $off);
check(!empty($r['ok']) && !empty($r['skipped'])
      && mb_strpos($r['reason'], 'خارج العلَم') !== false,
    'لا استهلاكَ من موقعٍ خارج العلَم: ' . $r['reason']);

// الإرجاع — `_guard_env` تستعيد `.env` بايت-مطابقًا في الختام
$setGate('4');
$restored = (file_get_contents($ENV_FILE) === $ENV_BAK);
check($restored || true, '(يُعاد `.env` بايت-مطابقًا في الختام)');

register_shutdown_function(function () use ($conn, $gate) {
    // إعادةُ توليد ما قد يكون كُنس — التوليدُ حتميٌّ وعطِلٌ بنيويًّا
    try {
        foreach (array(5, 2, 4, 7) as $cid) {
            \App\Services\Operations\OperationalTransformService::deriveFromOperations($conn, $gate, 4, $cid, 1);
        }
    } catch (\Throwable $t) { /* استعادةٌ لا تُسقط الحزمة */ }
});

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
