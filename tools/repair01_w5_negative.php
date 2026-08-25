<?php
/**
 * tools/repair01_w5_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الخامسة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الأخضرُ لا يُثبت شيئًا وحدَه: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدمِ. فهنا نكسر كلَّ حاجبٍ على حِدةٍ ونطلب من البوّابةِ أن تسقط — ثم
 *   نُرجع الحالةَ. الحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على الرمزِ لا على العبارة** (§قواعد القياس ٣): يُلتقط `W5-nn`
 *   من سطرِ الحاجبِ الساقطِ بتعبيرٍ نمطيّ — لا بمطابقةِ نصٍّ عربيٍّ قد يظهر
 *   في حالةِ الخطأِ نفسِها فيُخضِرَّ كذبًا.
 *
 * ◆ **والكسرُ يستهدف ما يمكن أن يقع فعلًا**: رقمٌ مشتقٌّ يُدهَس · وسمُ تزامنٍ
 *   يكذب · حالةُ أصلٍ تُدَّعى · عقدٌ يُنزَع · سطحُ نموٍّ بلا ختم. ولا نكسر ما
 *   يضمنه المخطَّطُ — فذلك لا يُختبَر أصلًا وهو تعريفُ الحاجبِ الأعمى.
 *
 * التشغيل: php tools/repair01_w5_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/app/Services/Fleet/AssetLifecycleService.php';
require_once $ROOT . '/tools/lib/repair01_w5_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w5_gate.php';
$JRN  = $ROOT . '/tools/repair01_w5_journey.php';
$esc  = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one  = function ($sql) use ($conn) { return repair01_w5_one($conn, $sql); };

function run_gate5($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W5-') !== false && preg_match('/W5-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

/** يلتقط صفًّا كاملًا ويعيد جملةَ إدراجٍ تعيده عمودًا عمودًا */
function snap5(mysqli $c, $table, $where, $order = '')
{
    $r = $c->query("SELECT * FROM `$table` WHERE $where " . ($order !== '' ? "ORDER BY $order " : '') . "LIMIT 1");
    if (!$r || !($row = $r->fetch_assoc())) { return null; }
    $cols = array(); $vals = array();
    foreach ($row as $k => $v) {
        $cols[] = '`' . $k . '`';
        $vals[] = ($v === null) ? 'NULL' : "'" . $c->real_escape_string($v) . "'";
    }
    return array('row' => $row,
                 'insert' => "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")",
                 'delete' => "DELETE FROM `$table` WHERE $where");
}

list($c0, $f0) = run_gate5($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ═══ لقطاتُ الصفوفِ التي ستُكسر — بالصفِّ كاملًا لا بالأعمدةِ المفحوصة ═══ */
$snapScope = snap5($conn, 'repair01_w5_scope',   "anchor_screen_id <> ''", 'requirement_id');
$snapSide  = snap5($conn, 'repair01_w5_sidebar', "screen_id <> ''", 'screen_id');
$snapEvent = snap5($conn, 'repair01_events',     "contract_stage = 'W05'", 'id');
$snapRight = snap5($conn, 'asset_use_right',     "concurrency_rule = 'W5_SUCCESSIVE_OK'", 'id');
$snapRead  = snap5($conn, 'asset_readiness',     "readiness_pct > 0", 'id');
/* ⚠ **اللقطةُ تُختار على الحالةِ التي سنكسرها لا على أوّلِ صفّ**: صفٌّ وسمُه
     `AGREES` سلفًا لا يُكسَر بقلبِه إلى `AGREES` — فيمرُّ الكسرُ بلا أثرٍ
     ويُقرأ «حاجبًا أعمى» وهو يقظ. الخطأُ في الكاسرِ لا في الحاجب. */
$snapCov   = snap5($conn, 'wf_coverage',         "variance_rule = 'W5_COVERAGE_VARIANCE_OPEN'", 'id');
$snapGrow  = snap5($conn, 'repair01_screen_registry', "origin = 'W05'", 'screen_id');
foreach (array('scope' => $snapScope, 'sidebar' => $snapSide, 'event' => $snapEvent,
               'use_right' => $snapRight, 'readiness' => $snapRead, 'coverage' => $snapCov,
               'growth' => $snapGrow) as $k => $s) {
    if ($s === null) { echo "✘ لقطةٌ فارغةٌ ($k) — الفحصُ السلبيُّ لا يُشغَّل بلا صفٍّ يُكسَر\n"; exit(1); }
}
$scopeReq  = $snapScope['row']['requirement_id'];
$sideSid   = $snapSide['row']['screen_id'];
$eventId   = (int) $snapEvent['row']['id'];
$rightId   = (int) $snapRight['row']['id'];
$readId    = (int) $snapRead['row']['id'];
$covId     = (int) $snapCov['row']['id'];
$growSid   = $snapGrow['row']['screen_id'];
$growRoute = (string) $snapGrow['row']['route'];

/* أصلٌ حيٌّ لكسرِ حالةِ الدورة · وبندُ قائمةٍ حيٌّ لكسرِ الصلاحيةِ والربط */
$eqId = (int) $one("SELECT id FROM equipments WHERE lifecycle_rule <> '' ORDER BY id LIMIT 1");
$eqState = (string) $one("SELECT lifecycle_state FROM equipments WHERE id = $eqId");
$navId = (int) $one("SELECT n.id FROM nav_items n
                       JOIN repair01_screen_registry g ON LOWER(g.route) = LOWER(n.route)
                      WHERE g.owner_code IN ('DEP-04','DEP-13') AND n.active = 1
                        AND n.permission_code IS NOT NULL AND n.permission_code <> '' ORDER BY n.id LIMIT 1");
$navPerm = (string) $one("SELECT permission_code FROM nav_items WHERE id = $navId");
$canRoute = (string) $one("SELECT n.route FROM nav_canonical n
                             JOIN repair01_screen_registry g ON g.route = n.route
                            WHERE g.owner_code IN ('DEP-04','DEP-13') AND g.on_disk = 1
                              AND n.screen_id <> '' ORDER BY n.route LIMIT 1");
$canSid = (string) $one("SELECT screen_id FROM nav_canonical WHERE route = '" . $esc($canRoute) . "'");
/* تشغيلةٌ بلا أصلٍ قائمٍ — إضافةُ صفِّ تايم شيتٍ عليها تصنع «شهرَ أصلٍ بلا أصل» */
$opNoAsset = (int) $one("SELECT o.id FROM operations o
                          LEFT JOIN equipments e ON e.id = CAST(o.equipment AS UNSIGNED)
                         WHERE e.id IS NULL ORDER BY o.id LIMIT 1");
$negCompany = (int) $one("SELECT company_id FROM equipments GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
/* أرضيّةُ الرحلة — إزالتُها تمنع الرحلةَ من الانعقادِ فتسقط W5-16 */
$jSites = array();
$r = $conn->query("SELECT id FROM sites WHERE company_id = $negCompany AND is_deleted = 0");
while ($r && $x = $r->fetch_row()) { $jSites[] = (int) $x[0]; }

if ($eqId <= 0 || $navId <= 0 || $canRoute === '' || $opNoAsset <= 0 || !$jSites) {
    echo "✘ أرضيّةٌ ناقصةٌ للكسر (أصل $eqId · بند $navId · معياريّ '$canRoute' · تشغيلةٌ بلا أصل $opNoAsset · مواقع "
       . count($jSites) . ") — الفحصُ السلبيُّ لا يُشغَّل\n";
    exit(1);
}

$cases = array();

/* ① W5-01 — متطلَّبٌ يسقط من دفترِ النطاق */
$cases[] = array('W5-01', 'نزعُ متطلَّبٍ من دفترِ النطاق',
    function () use ($conn, $esc, $scopeReq) { return $conn->query("DELETE FROM repair01_w5_scope WHERE requirement_id = '" . $esc($scopeReq) . "'"); },
    function () use ($conn, $snapScope) { return $conn->query($snapScope['insert']); });

/* ② W5-01 — قاعدةُ الربطِ تُفرَّغ */
$cases[] = array('W5-01', 'إفراغُ قاعدةِ الربطِ عن متطلَّب',
    function () use ($conn, $esc, $scopeReq) { return $conn->query("UPDATE repair01_w5_scope SET map_rule = '' WHERE requirement_id = '" . $esc($scopeReq) . "'"); },
    function () use ($conn, $esc, $scopeReq, $snapScope) { return $conn->query("UPDATE repair01_w5_scope SET map_rule = '" . $esc($snapScope['row']['map_rule']) . "' WHERE requirement_id = '" . $esc($scopeReq) . "'"); });

/* ③ W5-02 — الدفترُ يدّعي مِرساةً غيرَ التي يُثبتها القرص */
$cases[] = array('W5-02', 'مِرساةٌ في الدفترِ تخالف المقيسَ من القرص',
    function () use ($conn, $esc, $scopeReq) { return $conn->query("UPDATE repair01_w5_scope SET anchor_screen_id = 'SCR-0001' WHERE requirement_id = '" . $esc($scopeReq) . "'"); },
    function () use ($conn, $esc, $scopeReq, $snapScope) { return $conn->query("UPDATE repair01_w5_scope SET anchor_screen_id = '" . $esc($snapScope['row']['anchor_screen_id']) . "' WHERE requirement_id = '" . $esc($scopeReq) . "'"); });

/* ④ W5-03 — مالكٌ مخالفٌ جديدٌ بلا إعلانٍ في القرار */
$cases[] = array('W5-03', 'مالكٌ مخالفٌ جديدٌ بلا إعلانٍ في القرار',
    function () use ($conn, $esc, $scopeReq) { return $conn->query("UPDATE repair01_w5_scope SET owner_verdict = 'MISMATCH' WHERE requirement_id = '" . $esc($scopeReq) . "' AND owner_verdict <> 'MISMATCH'"); },
    function () use ($conn, $esc, $scopeReq, $snapScope) { return $conn->query("UPDATE repair01_w5_scope SET owner_verdict = '" . $esc($snapScope['row']['owner_verdict']) . "' WHERE requirement_id = '" . $esc($scopeReq) . "'"); });

/* ⑤ W5-04 — خطوةُ سايدبارٍ بلا حكم */
$cases[] = array('W5-04', 'خطوةُ سايدبارٍ تُفرَّغ من حكمِها',
    function () use ($conn, $esc, $sideSid) { return $conn->query("UPDATE repair01_w5_sidebar SET s4_verdict = '' WHERE screen_id = '" . $esc($sideSid) . "'"); },
    function () use ($conn, $esc, $sideSid, $snapSide) { return $conn->query("UPDATE repair01_w5_sidebar SET s4_verdict = '" . $esc($snapSide['row']['s4_verdict']) . "' WHERE screen_id = '" . $esc($sideSid) . "'"); });

/* ⑥ W5-05 — بندُ قائمةٍ يفقد رمزَ صلاحيتِه */
$cases[] = array('W5-05', 'نزعُ رمزِ الصلاحيةِ عن بندٍ حيّ',
    function () use ($conn, $navId) { return $conn->query("UPDATE nav_items SET permission_code = '' WHERE id = $navId"); },
    function () use ($conn, $esc, $navId, $navPerm) { return $conn->query("UPDATE nav_items SET permission_code = '" . $esc($navPerm) . "' WHERE id = $navId"); });

/* ⑦ W5-06 — الربطُ بالسجلِّ المعياريِّ يُقطع */
$cases[] = array('W5-06', 'قطعُ الربطِ بـCanonical Screen_ID',
    function () use ($conn, $esc, $canRoute) { return $conn->query("UPDATE nav_canonical SET screen_id = 'SCR-9999' WHERE route = '" . $esc($canRoute) . "'"); },
    function () use ($conn, $esc, $canRoute, $canSid) { return $conn->query("UPDATE nav_canonical SET screen_id = '" . $esc($canSid) . "' WHERE route = '" . $esc($canRoute) . "'"); });

/* ⑧ W5-07 — ادّعاءُ أبٍ يظهر بلا حكم */
$cases[] = array('W5-07', 'ادّعاءُ أبٍ بلا حكمٍ في الدفتر',
    function () use ($conn, $esc, $sideSid) { return $conn->query("UPDATE repair01_w5_sidebar SET s5_verdict = 'UNJUDGED' WHERE screen_id = '" . $esc($sideSid) . "'"); },
    function () use ($conn, $esc, $sideSid, $snapSide) { return $conn->query("UPDATE repair01_w5_sidebar SET s5_verdict = '" . $esc($snapSide['row']['s5_verdict']) . "' WHERE screen_id = '" . $esc($sideSid) . "'"); });

/* ⑨ W5-08 — شهرُ أصلٍ جديدٌ بلا أصلٍ يظهر بلا إعلانٍ في القرار */
$cases[] = array('W5-08', 'شهرُ أصلٍ بلا أصلٍ يظهر بلا إعلان',
    function () use ($conn, $esc, $negCompany, $opNoAsset) {
        return $conn->query("INSERT INTO timesheet (company_id, operator, employee_id, shift, date,
                                                    shift_hours, executed_hours, type, time_notes)
                             VALUES ($negCompany, '$opNoAsset', '0', 'D', '2098-06-01', 8, 6, '1', '__w5_neg_bridge__')");
    },
    function () use ($conn) { return $conn->query("DELETE FROM timesheet WHERE time_notes = '__w5_neg_bridge__'"); });

/* ⑩ W5-09 — وسمُ تزامنٍ يكذب على نافذةٍ سليمة */
$cases[] = array('W5-09', 'وسمُ تزامنٍ كاذبٌ على نافذةٍ سليمة',
    function () use ($conn, $rightId) { return $conn->query("UPDATE asset_use_right SET concurrency_rule = 'W5_CONCURRENT_CLAIM_OPEN' WHERE id = $rightId"); },
    function () use ($conn, $rightId) { return $conn->query("UPDATE asset_use_right SET concurrency_rule = 'W5_SUCCESSIVE_OK' WHERE id = $rightId"); });

/* ⑪ W5-09-b — حقُّ استخدامٍ منقولٌ يسقط من السجلّ */
$cases[] = array('W5-09', 'نزعُ حقِّ استخدامٍ منقولٍ من السجلّ',
    function () use ($conn, $rightId) { return $conn->query("DELETE FROM asset_use_right WHERE id = $rightId"); },
    function () use ($conn, $snapRight) { return $conn->query($snapRight['insert']); });

/* ⑫ W5-10 — رقمُ جاهزيّةٍ مخزَّنٌ يُدهَس فيخالف حسابَه */
/* ⚠ **والدهسُ يجب أن يتجاوز سماحَ المقارنة**: فارقٌ بمقدارِ السماحِ نفسِه
     لا يُسقط الحاجبَ — والقارئُ يظنُّه أعمى وهو يقظ عند حدِّه المُعلَن. */
$cases[] = array('W5-10', 'دهسُ رقمِ الجاهزيّةِ المشتقّ',
    function () use ($conn, $readId) { return $conn->query("UPDATE asset_readiness SET readiness_pct = 12.34 WHERE id = $readId"); },
    function () use ($conn, $readId, $snapRead) { return $conn->query("UPDATE asset_readiness SET readiness_pct = " . (float) $snapRead['row']['readiness_pct'] . " WHERE id = $readId"); });

/* ⑬ W5-10-b — صفُّ جاهزيّةٍ بلا قاعدةِ اشتقاق */
$cases[] = array('W5-10', 'صفُّ جاهزيّةٍ بلا قاعدةِ اشتقاق',
    function () use ($conn, $readId) { return $conn->query("UPDATE asset_readiness SET derived_from = '' WHERE id = $readId"); },
    function () use ($conn, $esc, $readId, $snapRead) { return $conn->query("UPDATE asset_readiness SET derived_from = '" . $esc($snapRead['row']['derived_from']) . "' WHERE id = $readId"); });

/* ⑭ W5-11 — فجوةُ تغطيةٍ مخزَّنةٌ تُدهَس */
$cases[] = array('W5-11', 'دهسُ فجوةِ التغطيةِ المشتقّة',
    function () use ($conn, $covId) { return $conn->query("UPDATE wf_coverage SET gap_qty = gap_qty + 7 WHERE id = $covId"); },
    function () use ($conn, $covId, $snapCov) { return $conn->query("UPDATE wf_coverage SET gap_qty = " . (int) $snapCov['row']['gap_qty'] . " WHERE id = $covId"); });

/* ⑮ W5-11-b — وسمُ الفارقِ يُقلَب فيخالف المقيسَ */
$cases[] = array('W5-11', 'قلبُ وسمِ الفارقِ بين المشتقِّ والمُعلَن',
    function () use ($conn, $covId) { return $conn->query("UPDATE wf_coverage SET variance_rule = 'W5_COVERAGE_AGREES' WHERE id = $covId AND variance_rule = 'W5_COVERAGE_VARIANCE_OPEN'"); },
    function () use ($conn, $esc, $covId, $snapCov) { return $conn->query("UPDATE wf_coverage SET variance_rule = '" . $esc($snapCov['row']['variance_rule']) . "' WHERE id = $covId"); });

/* ⑯ W5-12 — حالةُ أصلٍ تُدَّعى خلافَ وقائعِها */
$cases[] = array('W5-12', 'ادّعاءُ حالةِ أصلٍ خلافَ وقائعِها',
    function () use ($conn, $eqId) { return $conn->query("UPDATE equipments SET lifecycle_state = 'retired' WHERE id = $eqId"); },
    function () use ($conn, $esc, $eqId, $eqState) { return $conn->query("UPDATE equipments SET lifecycle_state = '" . $esc($eqState) . "' WHERE id = $eqId"); });

/* ⑰ W5-12-b — حالةُ أصلٍ بلا قاعدة */
$cases[] = array('W5-12', 'حالةُ أصلٍ بلا قاعدةٍ مكتوبة',
    function () use ($conn, $eqId) { return $conn->query("UPDATE equipments SET lifecycle_rule = '' WHERE id = $eqId"); },
    function () use ($conn, $eqId) { return $conn->query("UPDATE equipments SET lifecycle_rule = 'W5_LIFECYCLE_FROM_LEGACY_CARD_STATE' WHERE id = $eqId"); });

/* ⑱ W5-13 — عقدُ أثرٍ يُنزَع عن حدثٍ في النطاق */
$cases[] = array('W5-13', 'نزعُ عقدِ الأثرِ عن حدثٍ في النطاق',
    function () use ($conn, $eventId) { return $conn->query("DELETE FROM repair01_events WHERE id = $eventId"); },
    function () use ($conn, $snapEvent) { return $conn->query($snapEvent['insert']); });

/* ⑲ W5-13-b — عقدٌ قائمٌ بحمولةٍ دنيا فارغة (أخضرُ العدِّ لا يكفي) */
$cases[] = array('W5-13', 'عقدٌ قائمٌ بحمولةٍ دنيا فارغة',
    function () use ($conn, $eventId) { return $conn->query("UPDATE repair01_events SET min_payload = '' WHERE id = $eventId"); },
    function () use ($conn, $esc, $eventId, $snapEvent) { return $conn->query("UPDATE repair01_events SET min_payload = '" . $esc($snapEvent['row']['min_payload']) . "' WHERE id = $eventId"); });

/* ⑳ W5-14 — تركيبةٌ ممنوعةٌ تظهر: الطالبُ يحقّق مصدرَ طلبِه */
$cases[] = array('W5-14', 'ظهورُ تركيبةٍ ممنوعةٍ: الطالبُ يحقّق مصدرَه',
    function () use ($conn, $esc, $negCompany) {
        $ok = $conn->query("INSERT INTO asset_intake (company_id, intake_no, requested_dept, state, state_rule, requested_by, source_ref)
                            VALUES ($negCompany, '__w5_neg_sod__', 'DEP-04', 'submitted', 'W5_NEG', 4242, '__w5_neg_sod__')");
        if (!$ok) { return false; }
        $iid = (int) $conn->insert_id;
        return $conn->query("INSERT INTO asset_source_check (company_id, intake_id, check_seq, doc_ref, verify_result, verify_rule, verified_by)
                             VALUES ($negCompany, $iid, 1, 'DOC-NEG', 'passed', 'W5_NEG', 4242)");
    },
    function () use ($conn) { return $conn->query("DELETE FROM asset_intake WHERE intake_no = '__w5_neg_sod__'"); });

/* ㉑ W5-15 — مخزنُ المراحلِ السابقةِ يُمَسّ */
$cases[] = array('W5-15', 'مساسُ مخزنِ المراحلِ السابقة',
    function () use ($conn) { return $conn->query("UPDATE repair01_target_gaps SET origin_stage = 'W05' WHERE origin_stage = '' LIMIT 1"); },
    function () use ($conn) { return $conn->query("UPDATE repair01_target_gaps SET origin_stage = '' WHERE origin_stage = 'W05'"); });

/* ㉒ W5-15-b — نموٌّ بلا ختمِ موجة */
$cases[] = array('W5-15', 'صفُّ نموٍّ بلا ختمِ موجة',
    function () use ($conn, $esc, $growSid) { return $conn->query("UPDATE repair01_screen_registry SET origin = 'XX9' WHERE screen_id = '" . $esc($growSid) . "'"); },
    function () use ($conn, $esc, $growSid) { return $conn->query("UPDATE repair01_screen_registry SET origin = 'W05' WHERE screen_id = '" . $esc($growSid) . "'"); });

/* ㉓ W5-16 — رحلةُ الأصلِ تُكسر بنزعِ أرضيّتِها */
$cases[] = array('W5-16', 'كسرُ رحلةِ الأصلِ بنزعِ أرضيّتِها',
    function () use ($conn, $negCompany) { return $conn->query("UPDATE sites SET is_deleted = 1 WHERE company_id = $negCompany AND is_deleted = 0"); },
    function () use ($conn, $jSites) { return $conn->query("UPDATE sites SET is_deleted = 0 WHERE id IN (" . implode(',', $jSites) . ")"); });

/* ㉔ W5-16-b — الرحلةُ نفسُها غائبة: البوّابةُ لا تُخضِرُّ بلا دليلِ عبور */
$cases[] = array('W5-16', 'غيابُ ملفِّ الرحلةِ نفسِه',
    function () use ($JRN) { return @rename($JRN, $JRN . '.negbak'); },
    function () use ($JRN) { return @rename($JRN . '.negbak', $JRN); });

/* ㉕ W5-17 — خلطُ فضاءِ المفاتيحِ يزداد بلا إعلان */
$cases[] = array('W5-17', 'ازديادُ خلطِ فضاءِ المفاتيحِ بلا إعلان',
    function () use ($conn) { return $conn->query("UPDATE repair01_w5_decisions SET scope_rows = scope_rows - 1 WHERE decision_id = 'W5-D-09'"); },
    function () use ($conn) { return $conn->query("UPDATE repair01_w5_decisions SET scope_rows = scope_rows + 1 WHERE decision_id = 'W5-D-09'"); });

/* ㉖ W5-18 — سطحُ نموٍّ يفقد بندَه الحيّ */
$cases[] = array('W5-18', 'سطحُ نموٍّ يفقد بنودَه الحيّة',
    function () use ($conn, $esc, $growRoute) { return $conn->query("UPDATE nav_items SET active = 0 WHERE route = '" . $esc($growRoute) . "'"); },
    function () use ($conn, $esc, $growRoute) { return $conn->query("UPDATE nav_items SET active = 1 WHERE route = '" . $esc($growRoute) . "'"); });

$blind = 0; $done = 0;
foreach ($cases as $c) {
    list($want, $title, $break, $restore) = $c;
    $bk = $break();
    if ($bk === false) { printf("  ⚠ %-8s تعذّر الكسر: %s\n", $want, $conn->error); continue; }
    list($code, $failed) = run_gate5($PHP, $GATE);
    $caught = in_array($want, $failed, true);
    if ($caught) { printf("  ✔ %-8s %-52s سقطت كما يجب\n", $want, $title); }
    else { $blind++; printf("  ✘ %-8s %-52s **لم تسقط** — الحاجبُ أعمى (الساقط: %s)\n", $want, $title, $failed ? implode(',', $failed) : 'لا شيء'); }
    if ($restore() === false) { printf("  ⛔ %-8s فشلَ الإرجاع: %s\n", $want, $conn->error); $blind++; }
    $done++;
}

echo "\n";
list($cz, $fz) = run_gate5($PHP, $GATE);
if ($cz === 0) { echo "الإرجاع: البوّابةُ عادت خضراء ✔\n"; }
else { echo "⛔ الإرجاع فاشل — البوّابةُ ما زالت ساقطةً في: " . implode(',', $fz) . "\n"; $blind++; }

printf("\nالفحصُ السلبيّ: %d حاجبًا مُختبَرًا · أعمى %d\n", $done, $blind);
echo ($blind === 0 ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: يوجد حاجبٌ أعمى ✘\n");
exit($blind === 0 ? 0 : 1);
