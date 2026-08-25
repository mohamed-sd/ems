<?php
/**
 * tools/repair01_w4_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الرابعة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الأخضرُ لا يُثبت شيئًا وحدَه: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدمِ. فهنا نكسر كلَّ حاجبٍ على حِدةٍ ونطلب من البوّابةِ أن تسقط — ثم
 *   نُرجع الحالةَ. الحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على الرمزِ لا على العبارة** (§قواعد القياس ٣): يُلتقط `W4-nn`
 *   من سطرِ الحاجبِ الساقطِ بتعبيرٍ نمطيّ — لا بمطابقةِ نصٍّ عربيٍّ قد يظهر
 *   في حالةِ الخطأِ نفسِها فيُخضِرَّ كذبًا.
 *
 * ◆ **والكسرُ يستهدف ما يمكن أن يقع فعلًا**: صفٌّ يُضاف · قراءةٌ تتقادم ·
 *   مفردةٌ خارجَ الجسر · تصنيفٌ يكذب · عقدٌ يُنزع. ولا نكسر ما يضمنه
 *   المخطَّطُ — فذلك لا يُختبَر أصلًا وهو تعريفُ الحاجبِ الأعمى.
 *
 * التشغيل: php tools/repair01_w4_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w4_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w4_gate.php';
$esc  = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one  = function ($sql) use ($conn) { return repair01_w4_one($conn, $sql); };

function run_gate4($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W4-') !== false && preg_match('/W4-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

/** يلتقط صفًّا كاملًا ويعيد جملةَ إدراجٍ تعيده عمودًا عمودًا */
function snap4(mysqli $c, $table, $where, $order = '')
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

list($c0, $f0) = run_gate4($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ═══ لقطاتُ الصفوفِ التي ستُكسر — بالصفِّ كاملًا لا بالأعمدةِ المفحوصة ═══ */
$snapScope = snap4($conn, 'repair01_w4_scope',   "anchor_screen_id <> ''", 'requirement_id');
$snapSide  = snap4($conn, 'repair01_w4_sidebar', "screen_id <> ''", 'screen_id');
$snapEvent = snap4($conn, 'repair01_events',     "contract_stage = 'W04'", 'id');
$snapStop  = snap4($conn, 'ops_stop_source',     "role = 'MIRROR'", 'id');
foreach (array('scope' => $snapScope, 'sidebar' => $snapSide,
               'event' => $snapEvent, 'stop' => $snapStop) as $k => $s) {
    if ($s === null) { echo "✘ لقطةٌ فارغةٌ ($k) — الفحصُ السلبيُّ لا يُشغَّل بلا صفٍّ يُكسَر\n"; exit(1); }
}
$scopeReq = $snapScope['row']['requirement_id'];
$sideSid  = $snapSide['row']['screen_id'];
$eventId  = (int) $snapEvent['row']['id'];
$stopSrcId = (int) $snapStop['row']['id'];
$stopHours = (float) $snapStop['row']['hours_read'];

/* صفُّ قيدٍ ميدانيٍّ حيٍّ لكسرِ التصنيف */
$ueId = (int) $one("SELECT id FROM unit_entries WHERE field_kind = 'FIELD_DAILY' AND equipment_id IS NOT NULL ORDER BY id LIMIT 1");
/* بندُ قائمةٍ حيٌّ في النطاقِ لكسرِ الصلاحيةِ والربط */
$navId = (int) $one("SELECT n.id FROM nav_items n
                       JOIN repair01_screen_registry g ON LOWER(g.route) = LOWER(n.route)
                      WHERE g.owner_code IN ('DEP-11','DEP-12') AND n.active = 1
                        AND n.permission_code IS NOT NULL AND n.permission_code <> '' ORDER BY n.id LIMIT 1");
$navPerm = (string) $one("SELECT permission_code FROM nav_items WHERE id = $navId");
$canRoute = (string) $one("SELECT n.route FROM nav_canonical n
                             JOIN repair01_screen_registry g ON g.route = n.route
                            WHERE g.owner_code IN ('DEP-11','DEP-12') AND g.on_disk = 1
                              AND n.screen_id <> '' ORDER BY n.route LIMIT 1");
$canSid = (string) $one("SELECT screen_id FROM nav_canonical WHERE route = '" . $esc($canRoute) . "'");
/* واقعةٌ يدّعيها التايم شيتُ وحدَه — إضافةُ سطرِ توقّفٍ عليها تصنع ازدواجًا غيرَ مصالَح */
$soloTs = null;
$shiftSql = repair01_w4_shift_sql('t');
$faultSql = repair01_w4_ts_fault_sum('t');
$r = $conn->query("SELECT t.company_id, t.date, $shiftSql nshift, t.operator eq
                     FROM timesheet t
                    WHERE $faultSql > 0 AND t.operator REGEXP '^[0-9]+$'
                      AND NOT EXISTS (SELECT 1 FROM unit_time_log l
                                       WHERE l.company_id = t.company_id AND l.log_date = t.date
                                         AND l.equipment_id = t.operator
                                         AND l.ops_state IN ('tech_breakdown','supplier_stop','operator_stop',
                                                             'client_stop','fuel_logistics_stop','planned_stop','force_majeure'))
                    LIMIT 1");
$soloTs = $r ? $r->fetch_assoc() : null;
$soloProject = (int) $one("SELECT project_id FROM unit_time_log WHERE project_id IS NOT NULL ORDER BY id LIMIT 1");
/* أرضيّةُ الرحلة — لكسرِ W4-16 بيومٍ مُقفَلٍ يسبقها على المفتاحِ نفسِه */
$jCompany = (int) $one("SELECT company_id FROM unit_entries WHERE field_kind = 'FIELD_DAILY'
                         GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
$jSite = (int) $one("SELECT id FROM sites WHERE company_id = $jCompany AND is_deleted = 0 ORDER BY id LIMIT 1");
$jDay  = (string) $one("SELECT DATE_ADD(CURDATE(), INTERVAL 3650 DAY)");
$jActor = (int) $one("SELECT id FROM employees WHERE company_id = $jCompany ORDER BY id LIMIT 1");
if ($ueId <= 0 || $navId <= 0 || $canRoute === '' || $soloTs === null || $jSite <= 0) {
    echo "✘ أرضيّةٌ ناقصةٌ للكسر (قيد $ueId · بند $navId · معياريّ '$canRoute' · واقعةٌ منفردة "
       . ($soloTs ? 'نعم' : 'لا') . " · موقع $jSite) — الفحصُ السلبيُّ لا يُشغَّل\n";
    exit(1);
}

$cases = array();

/* ① W4-01 — متطلَّبٌ يسقط من دفترِ النطاق */
$cases[] = array('W4-01', 'نزعُ متطلَّبٍ من دفترِ النطاق',
    function () use ($conn, $esc, $scopeReq) { return $conn->query("DELETE FROM repair01_w4_scope WHERE requirement_id = '" . $esc($scopeReq) . "'"); },
    function () use ($conn, $snapScope) { return $conn->query($snapScope['insert']); });

/* ② W4-01 — قاعدةُ الربطِ تُفرَّغ (قيمةٌ عاريةٌ من قاعدتِها) */
$cases[] = array('W4-01', 'إفراغُ قاعدةِ الربطِ عن متطلَّب',
    function () use ($conn, $esc, $scopeReq) { return $conn->query("UPDATE repair01_w4_scope SET map_rule = '' WHERE requirement_id = '" . $esc($scopeReq) . "'"); },
    function () use ($conn, $esc, $scopeReq, $snapScope) { return $conn->query("UPDATE repair01_w4_scope SET map_rule = '" . $esc($snapScope['row']['map_rule']) . "' WHERE requirement_id = '" . $esc($scopeReq) . "'"); });

/* ③ W4-02 — الدفترُ يدّعي مِرساةً غيرَ التي يُثبتها القرص */
$cases[] = array('W4-02', 'مِرساةٌ في الدفترِ تخالف المقيسَ من القرص',
    function () use ($conn, $esc, $scopeReq) { return $conn->query("UPDATE repair01_w4_scope SET anchor_screen_id = 'SCR-0001' WHERE requirement_id = '" . $esc($scopeReq) . "'"); },
    function () use ($conn, $esc, $scopeReq, $snapScope) { return $conn->query("UPDATE repair01_w4_scope SET anchor_screen_id = '" . $esc($snapScope['row']['anchor_screen_id']) . "' WHERE requirement_id = '" . $esc($scopeReq) . "'"); });

/* ④ W4-03 — مالكٌ مخالفٌ يظهر بلا إعلانٍ في القرار */
$cases[] = array('W4-03', 'مالكٌ مخالفٌ جديدٌ بلا إعلانٍ في القرار',
    function () use ($conn, $esc, $scopeReq) { return $conn->query("UPDATE repair01_w4_scope SET owner_verdict = 'MISMATCH' WHERE requirement_id = '" . $esc($scopeReq) . "' AND owner_verdict <> 'MISMATCH'"); },
    function () use ($conn, $esc, $scopeReq, $snapScope) { return $conn->query("UPDATE repair01_w4_scope SET owner_verdict = '" . $esc($snapScope['row']['owner_verdict']) . "' WHERE requirement_id = '" . $esc($scopeReq) . "'"); });

/* ⑤ W4-04 — خطوةُ سايدبارٍ بلا حكم */
$cases[] = array('W4-04', 'خطوةُ سايدبارٍ تُفرَّغ من حكمِها',
    function () use ($conn, $esc, $sideSid) { return $conn->query("UPDATE repair01_w4_sidebar SET s4_verdict = '' WHERE screen_id = '" . $esc($sideSid) . "'"); },
    function () use ($conn, $esc, $sideSid, $snapSide) { return $conn->query("UPDATE repair01_w4_sidebar SET s4_verdict = '" . $esc($snapSide['row']['s4_verdict']) . "' WHERE screen_id = '" . $esc($sideSid) . "'"); });

/* ⑥ W4-05 — بندُ قائمةٍ يفقد رمزَ صلاحيتِه */
$cases[] = array('W4-05', 'نزعُ رمزِ الصلاحيةِ عن بندٍ حيّ',
    function () use ($conn, $navId) { return $conn->query("UPDATE nav_items SET permission_code = '' WHERE id = $navId"); },
    function () use ($conn, $esc, $navId, $navPerm) { return $conn->query("UPDATE nav_items SET permission_code = '" . $esc($navPerm) . "' WHERE id = $navId"); });

/* ⑦ W4-06 — الربطُ بالسجلِّ المعياريِّ يُقطع */
$cases[] = array('W4-06', 'قطعُ الربطِ بـCanonical Screen_ID',
    function () use ($conn, $esc, $canRoute) { return $conn->query("UPDATE nav_canonical SET screen_id = 'SCR-9999' WHERE route = '" . $esc($canRoute) . "'"); },
    function () use ($conn, $esc, $canRoute, $canSid) { return $conn->query("UPDATE nav_canonical SET screen_id = '" . $esc($canSid) . "' WHERE route = '" . $esc($canRoute) . "'"); });

/* ⑧ W4-07 — ادّعاءُ أبٍ يظهر بلا حكم */
$cases[] = array('W4-07', 'ادّعاءُ أبٍ بلا حكمٍ في الدفتر',
    function () use ($conn, $esc, $sideSid) { return $conn->query("UPDATE repair01_w4_sidebar SET s5_verdict = 'UNJUDGED' WHERE screen_id = '" . $esc($sideSid) . "'"); },
    function () use ($conn, $esc, $sideSid, $snapSide) { return $conn->query("UPDATE repair01_w4_sidebar SET s5_verdict = '" . $esc($snapSide['row']['s5_verdict']) . "' WHERE screen_id = '" . $esc($sideSid) . "'"); });

/* ⑨ W4-08 — مفردةُ ورديةٍ حيّةٌ خارجَ الجسر */
$cases[] = array('W4-08', 'مفردةُ ورديةٍ حيّةٌ خارجَ جسرِ المفردات',
    function () use ($conn, $esc, $jCompany) {
        return $conn->query("INSERT INTO timesheet (company_id, operator, employee_id, shift, date, type, time_notes)
                             VALUES ($jCompany, '0', '0', 'صباحي', '2099-01-01', '1', '__w4_neg_vocab__')");
    },
    function () use ($conn) { return $conn->query("DELETE FROM timesheet WHERE time_notes = '__w4_neg_vocab__'"); });

/* ⑩ W4-09 — قيدٌ ميدانيٌّ يُوسَم إسقاطًا تعاقديًّا (كذبٌ يمرُّ من القيد) */
$cases[] = array('W4-09', 'وسمُ قيدٍ ميدانيٍّ إسقاطًا تعاقديًّا',
    function () use ($conn, $ueId) { return $conn->query("UPDATE unit_entries SET field_kind = 'CONTRACT_PROJECTION', field_kind_rule = 'W4_NO_EQUIPMENT_NO_ENTERER' WHERE id = $ueId"); },
    function () use ($conn, $ueId) { return $conn->query("UPDATE unit_entries SET field_kind = 'FIELD_DAILY', field_kind_rule = 'W4_HAS_EQUIPMENT' WHERE id = $ueId"); });

/* ⑪ W4-10 — إسقاطٌ تعاقديٌّ جديدٌ بلا إعلانٍ في القرار */
$cases[] = array('W4-10', 'إسقاطٌ تعاقديٌّ جديدٌ بلا إعلانٍ في القرار',
    function () use ($conn) { return $conn->query("UPDATE repair01_w4_decisions SET scope_rows = scope_rows + 1 WHERE decision_id = 'W4-D-04'"); },
    function () use ($conn) { return $conn->query("UPDATE repair01_w4_decisions SET scope_rows = scope_rows - 1 WHERE decision_id = 'W4-D-04'"); });

/* ⑫ W4-11 — سطرُ توقّفٍ جديدٌ يصنع ازدواجًا غيرَ مصالَح */
$cases[] = array('W4-11', 'تسجيلٌ ثانٍ لواقعةِ توقّفٍ بلا مصالحة',
    function () use ($conn, $esc, $soloTs, $soloProject) {
        return $conn->query("INSERT INTO unit_time_log
            (company_id, log_date, shift, project_id, equipment_id, hours, ops_state, cause_note, resp_party)
            VALUES (" . (int) $soloTs['company_id'] . ", '" . $esc($soloTs['date']) . "', '" . $esc($soloTs['nshift']) . "',
                    $soloProject, " . (int) $soloTs['eq'] . ", 1.50, 'tech_breakdown', '__w4_neg_dup__', 'company')");
    },
    function () use ($conn) { return $conn->query("DELETE FROM unit_time_log WHERE cause_note = '__w4_neg_dup__'"); });

/* ⑬ W4-12 — قراءةُ المرآةِ تتقادم عن الحيّ */
$cases[] = array('W4-12', 'قراءةُ المرآةِ تتقادم عن السجلِّ الحيّ',
    function () use ($conn, $stopSrcId) { return $conn->query("UPDATE ops_stop_source SET hours_read = hours_read + 7 WHERE id = $stopSrcId"); },
    function () use ($conn, $stopSrcId, $stopHours) { return $conn->query("UPDATE ops_stop_source SET hours_read = $stopHours WHERE id = $stopSrcId"); });

/* ⑭ W4-13 — عقدُ أثرٍ يُنزَع عن حدثٍ في النطاق */
$cases[] = array('W4-13', 'نزعُ عقدِ الأثرِ عن حدثٍ في النطاق',
    function () use ($conn, $eventId) { return $conn->query("DELETE FROM repair01_events WHERE id = $eventId"); },
    function () use ($conn, $snapEvent) { return $conn->query($snapEvent['insert']); });

/* ⑮ W4-13-b — عقدٌ قائمٌ بحمولةٍ دنيا فارغة (أخضرُ العدِّ لا يكفي) */
$cases[] = array('W4-13', 'عقدٌ قائمٌ بحمولةٍ دنيا فارغة',
    function () use ($conn, $eventId) { return $conn->query("UPDATE repair01_events SET min_payload = '' WHERE id = $eventId"); },
    function () use ($conn, $esc, $eventId, $snapEvent) { return $conn->query("UPDATE repair01_events SET min_payload = '" . $esc($snapEvent['row']['min_payload']) . "' WHERE id = $eventId"); });

/* ⑯ W4-14 — تركيبةٌ ممنوعةٌ جديدةٌ تظهر في صفٍّ حيّ */
$sodEntry = (int) $one("SELECT e.id FROM unit_entries e
                         WHERE e.entered_by IS NOT NULL AND e.qty_decided_by IS NOT NULL
                           AND e.entered_by <> e.qty_decided_by ORDER BY e.id LIMIT 1");
$sodOld   = (int) $one("SELECT qty_decided_by FROM unit_entries WHERE id = $sodEntry");
$cases[] = array('W4-14', 'ظهورُ تركيبةٍ ممنوعةٍ جديدةٍ في صفٍّ حيّ',
    function () use ($conn, $sodEntry) { return $conn->query("UPDATE unit_entries SET qty_decided_by = entered_by WHERE id = $sodEntry"); },
    function () use ($conn, $sodEntry, $sodOld) { return $conn->query("UPDATE unit_entries SET qty_decided_by = $sodOld WHERE id = $sodEntry"); });

/* ⑰ W4-15 — مخزنُ المراحلِ السابقةِ يُمَسّ */
$cases[] = array('W4-15', 'مساسُ مخزنِ المراحلِ السابقة',
    function () use ($conn) { return $conn->query("UPDATE repair01_target_gaps SET origin_stage = 'W04' WHERE origin_stage = '' LIMIT 1"); },
    function () use ($conn) { return $conn->query("UPDATE repair01_target_gaps SET origin_stage = '' WHERE origin_stage = 'W04'"); });

/* ⑱ W4-16 — رحلةُ اليومِ تُكسر بيومٍ مُقفَلٍ يسبقها على المفتاحِ نفسِه */
$cases[] = array('W4-16', 'كسرُ رحلةِ اليومِ بيومٍ مُقفَلٍ سابقٍ على مفتاحِها',
    function () use ($conn, $esc, $jCompany, $jSite, $jDay, $jActor) {
        return $conn->query("INSERT INTO site_day (company_id, site_id, day_date, state, opened_by, opened_at,
                                                   closed_by, closed_at, source_ref)
                             VALUES ($jCompany, $jSite, '" . $esc($jDay) . "', 'closed', $jActor, NOW(),
                                     $jActor, NOW(), '__w4_neg_journey__')");
    },
    function () use ($conn) { return $conn->query("DELETE FROM site_day WHERE source_ref = '__w4_neg_journey__'"); });

$blind = 0; $done = 0;
foreach ($cases as $c) {
    list($want, $title, $break, $restore) = $c;
    $bk = $break();
    if ($bk === false) { printf("  ⚠ %-8s تعذّر الكسر: %s\n", $want, $conn->error); continue; }
    list($code, $failed) = run_gate4($PHP, $GATE);
    $caught = in_array($want, $failed, true);
    if ($caught) { printf("  ✔ %-8s %-48s سقطت كما يجب\n", $want, $title); }
    else { $blind++; printf("  ✘ %-8s %-48s **لم تسقط** — الحاجبُ أعمى (الساقط: %s)\n", $want, $title, $failed ? implode(',', $failed) : 'لا شيء'); }
    if ($restore() === false) { printf("  ⛔ %-8s فشلَ الإرجاع: %s\n", $want, $conn->error); $blind++; }
    $done++;
}

echo "\n";
list($cz, $fz) = run_gate4($PHP, $GATE);
if ($cz === 0) { echo "الإرجاع: البوّابةُ عادت خضراء ✔\n"; }
else { echo "⛔ الإرجاع فاشل — البوّابةُ ما زالت ساقطةً في: " . implode(',', $fz) . "\n"; $blind++; }

printf("\nالفحصُ السلبيّ: %d حاجبًا مُختبَرًا · أعمى %d\n", $done, $blind);
echo ($blind === 0 ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: يوجد حاجبٌ أعمى ✘\n");
exit($blind === 0 ? 0 : 1);
