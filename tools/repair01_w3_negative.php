<?php
/**
 * tools/repair01_w3_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الثالثة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: نكسر كلَّ حاجبٍ على حِدةٍ ونطلب من
 *   البوّابةِ أن تسقط، ثمّ نُرجع. الحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على الرمزِ لا العبارة**: الالتقاطُ بـ`✘ W3-nn` — فنصُّ حالةِ
 *   الخطأِ العربيُّ لا يطابق رمزًا.
 *
 * ◆ **والإرجاعُ يُلتقط بالصفِّ كاملًا (`*`) ويُعاد عمودًا عمودًا**: أوّلُ صيغةٍ
 *   في W02 التقطت الأعمدةَ المفحوصةَ وحدَها فعاد الصفُّ ناقصًا والبوّابةُ
 *   خضراء — «الأخضرُ الذي لا يفحص العمودَ لا يحرسه».
 *
 * التشغيل: php tools/repair01_w3_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w3_gate.php';
$esc  = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one  = function ($sql) use ($conn) { return repair01_w3_one($conn, $sql); };

function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W3-') !== false && preg_match('/W3-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

/** يلتقط صفًّا كاملًا ويعيد جملةَ إدراجٍ تعيده عمودًا عمودًا.
 *  ⚠ الترتيبُ **وسيطٌ مستقلّ**: دسُّه في الشرطِ يُنتج `LIMIT 1 LIMIT 1` فتفشل
 *  الجملةُ صامتةً وتعود اللقطةُ فارغةً — فيصير الكسرُ «تحديثَ صفٍّ id=0»
 *  الذي **ينجح ولا يكسر شيئًا**، والحاجبُ يُقرأ أعمى وهو يقظ. */
function snap_row(mysqli $c, $table, $where, $order = '')
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

list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ═══ لقطاتُ الصفوفِ التي ستُكسر — بالصفِّ كاملًا لا بالأعمدةِ المفحوصة ═══ */
$snapKey   = snap_row($conn, 'repair01_key_registry',  "key_code = 'Asset_ID'");
$snapAlias = snap_row($conn, 'repair01_key_alias',     "verdict = 'SEED_NO_REFERENT'", 'id');
$aliasId   = $snapAlias ? (int) $snapAlias['row']['id'] : 0;
$snapScope = snap_row($conn, 'repair01_w3_scope',      "map_rule <> ''", 'requirement_id');
$scopeReq  = $snapScope ? $snapScope['row']['requirement_id'] : '';
$snapSide  = snap_row($conn, 'repair01_w3_sidebar',    "screen_id <> ''", 'screen_id');
$sideSid   = $snapSide ? $snapSide['row']['screen_id'] : '';
$snapEvent = snap_row($conn, 'repair01_events',        "contract_stage = 'W03'", 'id');
$eventId   = $snapEvent ? (int) $snapEvent['row']['id'] : 0;
foreach (array('key' => $snapKey, 'alias' => $snapAlias, 'scope' => $snapScope,
               'sidebar' => $snapSide, 'event' => $snapEvent) as $k => $s) {
    if ($s === null) { echo "✘ لقطةٌ فارغةٌ ($k) — الفحصُ السلبيُّ لا يُشغَّل بلا صفٍّ يُكسَر\n"; exit(1); }
}

/* اتصالٌ ثانٍ بمستخدمِ الهجرات — كسورُ البنيةِ (`ALTER`) لا يملكها مستخدمُ
   التطبيق. وبلا هذا الاتصالِ يبقى حاجبا `W3-06` و`W3-15` **غيرَ مُختبَرَين**،
   و«تعذّر الكسر» ليس نجاحًا. */
require_once $ROOT . '/includes/env.php';
$mHost = ems_env('DB_HOST'); $mPort = 3306;
if (strpos($mHost, ':') !== false) { list($mHost, $mPort) = explode(':', $mHost); $mPort = (int) $mPort; }
$mUser = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$mPass = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
mysqli_report(MYSQLI_REPORT_OFF);
$ddl = new mysqli($mHost, $mUser, $mPass, ems_env('DB_NAME'), $mPort);
if ($ddl->connect_errno) { echo "✘ تعذّر اتصالُ الهجرات — كسرُ البنيةِ لا يُختبَر\n"; exit(1); }
$ddl->set_charset('utf8mb4');

/* صفُّ موقعٍ حيٌّ لكسرِ DEC-OPEN-03 */
$siteId = (int) $one("SELECT id FROM sites ORDER BY id LIMIT 1");
$siteCo = (int) $one("SELECT company_id FROM sites WHERE id = $siteId");
/* صفُّ هويةٍ موصولٌ لكسرِ W3-04 */
$persId = (int) $one("SELECT person_id FROM persons WHERE employee_id IS NOT NULL ORDER BY person_id LIMIT 1");
$persEmp = (int) $one("SELECT employee_id FROM persons WHERE person_id = $persId");
/* مسارُ سطحٍ مخفوضٍ لكسرِ W3-10 */
$demRoute = (string) $one("SELECT route FROM repair01_screen_registry
                            WHERE visibility_rule = 'W3_TAB_PROVEN_BY_ENTITY_TABS' ORDER BY screen_id LIMIT 1");
$demSid   = (string) $one("SELECT screen_id FROM repair01_screen_registry
                            WHERE visibility_rule = 'W3_TAB_PROVEN_BY_ENTITY_TABS' ORDER BY screen_id LIMIT 1");
$demNavId = (int) $one("SELECT id FROM nav_items WHERE " . repair01_w3_nav_pred($conn, $demRoute) . " ORDER BY id LIMIT 1");
/* بندُ قائمةٍ في النطاقِ لكسرِ W3-11 */
$permNavId = (int) $one("SELECT n.id FROM nav_items n
                          JOIN repair01_w3_sidebar s ON " . 'LOWER(TRIM(BOTH \'/\' FROM REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(n.route,\'?\',1),\'#\',1),\'../\',\'\')))'
                        . " = LOWER(s.route)
                         WHERE n.active = 1 AND n.permission_code IS NOT NULL AND n.permission_code <> ''
                         ORDER BY n.id LIMIT 1");
$permCode  = (string) $one("SELECT permission_code FROM nav_items WHERE id = $permNavId");
/* صفٌّ معياريٌّ في النطاقِ لكسرِ W3-12 */
$canRoute = (string) $one("SELECT n.route FROM nav_canonical n
                            JOIN repair01_w3_sidebar s ON s.route = n.route
                           WHERE n.screen_id <> '' ORDER BY n.route LIMIT 1");
$canSid   = (string) $one("SELECT screen_id FROM nav_canonical WHERE route = '" . $esc($canRoute) . "'");

$cases = array();

/* ① W3-01 — مالكُ مفتاحٍ يُنزَع من السجلّ */
$cases[] = array('W3-01', 'نزعُ مفتاحٍ من سجلِّ المفاتيح',
    function () use ($conn) { return $conn->query("DELETE FROM repair01_key_registry WHERE key_code = 'Asset_ID'"); },
    function () use ($conn, $snapKey) { return $conn->query($snapKey['insert']); });

/* ② W3-02 — قاعدةُ قراءةٍ تُفرَّغ */
$cases[] = array('W3-02', 'تفريغُ قاعدةِ قراءةِ مفتاح',
    function () use ($conn) { return $conn->query("UPDATE repair01_key_registry SET read_rule = '' WHERE key_code = 'Person_ID'"); },
    function () use ($conn) {
        $r = repair01_w3_one($conn, "SELECT read_rule FROM repair01_key_registry WHERE key_code = 'Asset_ID'");
        return $conn->query("UPDATE repair01_key_registry SET read_rule =
            'يقرأ بالمفتاح من employees — وكل عمود اسمه person_id يحمله هو' WHERE key_code = 'Person_ID'");
    });

/* ③ W3-03 — حكمٌ يخالف المقيس: جدولٌ بذرةٌ يُوسَم معرّفًا بديلًا مفتوحًا */
$cases[] = array('W3-03', 'وسمُ بذرةٍ معرّفًا بديلًا مفتوحًا',
    function () use ($conn, $aliasId) {
        return $conn->query("UPDATE repair01_key_alias SET verdict = 'ALTERNATE_ID', resolved_at = NULL WHERE id = $aliasId");
    },
    function () use ($conn, $aliasId, $snapAlias) {
        return $conn->query("UPDATE repair01_key_alias SET verdict = '" . $conn->real_escape_string($snapAlias['row']['verdict']) . "',
            resolved_at = " . ($snapAlias['row']['resolved_at'] === null ? 'NULL' : "'" . $conn->real_escape_string($snapAlias['row']['resolved_at']) . "'")
            . " WHERE id = $aliasId");
    });

/* ④ W3-04 — صفٌّ موصولٌ يفقد وصلَه (والقيدُ يمنع بقاءَه `WORKFORCE`) */
$cases[] = array('W3-04', 'قطعُ وصلِ صفِّ هويةٍ بالمفتاحِ الأمّ',
    function () use ($conn, $persId) {
        $conn->query("UPDATE persons SET active = 0 WHERE person_id = $persId");
        $conn->query("UPDATE persons SET employee_id = NULL, person_class = 'IDENTITY_ONLY' WHERE person_id = $persId");
        return $conn->query("UPDATE persons SET active = 1 WHERE person_id = $persId");
    },
    function () use ($conn, $persId, $persEmp) {
        return $conn->query("UPDATE persons SET employee_id = $persEmp, person_class = 'WORKFORCE', active = 1
                              WHERE person_id = $persId");
    });

/* ⑤ W3-05 — كيانٌ أمٌّ يفقد Company_ID */
$cases[] = array('W3-05', 'موقعٌ حيٌّ بلا Company_ID',
    function () use ($conn, $siteId) { return $conn->query("UPDATE sites SET company_id = NULL WHERE id = $siteId"); },
    function () use ($conn, $siteId, $siteCo) { return $conn->query("UPDATE sites SET company_id = $siteCo WHERE id = $siteId"); });

/* ⑥ W3-06 — الحارسُ يُنزَع من القاعدة */
$cases[] = array('W3-06', 'نزعُ قيدِ الكيانِ عن سجلِّ الهوية',
    function () use ($ddl) { return $ddl->query("ALTER TABLE `persons` DROP CONSTRAINT `chk_persons_w3_company`"); },
    function () use ($ddl) {
        return $ddl->query("ALTER TABLE `persons` ADD CONSTRAINT `chk_persons_w3_company`
            CHECK (NOT (`active` = 1 AND (`company_id` IS NULL OR `company_id` = 0)))");
    });

/* ⑦ W3-07 — نطاقُ القرارِ يُخالف المقيس */
$cases[] = array('W3-07', 'قرارُ الحجرِ يُعلن عددًا غيرَ المقيس',
    function () use ($conn) { return $conn->query("UPDATE repair01_w3_decisions SET scope_rows = scope_rows + 7 WHERE decision_id = 'W3-D-01'"); },
    function () use ($conn) { return $conn->query("UPDATE repair01_w3_decisions SET scope_rows = scope_rows - 7 WHERE decision_id = 'W3-D-01'"); });

/* ⑧ W3-08 — متطلَّبٌ يخرج من دفترِ النطاق */
$cases[] = array('W3-08', 'نزعُ متطلَّبٍ من دفترِ النطاق',
    function () use ($conn, $scopeReq) { return $conn->query("DELETE FROM repair01_w3_scope WHERE requirement_id = '" . $conn->real_escape_string($scopeReq) . "'"); },
    function () use ($conn, $snapScope) { return $conn->query($snapScope['insert']); });

/* ⑨ W3-09 — خطوةٌ من السبعِ بلا حكم */
$cases[] = array('W3-09', 'تفريغُ حكمِ خطوةٍ من السبع',
    function () use ($conn, $sideSid) { return $conn->query("UPDATE repair01_w3_sidebar SET s4_verdict = '' WHERE screen_id = '" . $conn->real_escape_string($sideSid) . "'"); },
    function () use ($conn, $sideSid, $snapSide) {
        return $conn->query("UPDATE repair01_w3_sidebar SET s4_verdict = '" . $conn->real_escape_string($snapSide['row']['s4_verdict']) . "'
                              WHERE screen_id = '" . $conn->real_escape_string($sideSid) . "'");
    });

/* ⑩ W3-10 — سطحٌ **مُنِع خفضُه بقياسٍ** يُخفَض رغمَ المنع */
$blockedSid = (string) $one("SELECT screen_id FROM repair01_w3_sidebar
    WHERE s5_verdict IN ('TAB_CLAIM_UNPROVEN','TAB_BAR_NOT_RENDERED','DEMOTION_LOSES_ROLES')
    ORDER BY screen_id LIMIT 1");
$blockedVis = (string) $one("SELECT visibility_class FROM repair01_screen_registry WHERE screen_id = '" . $esc($blockedSid) . "'");
$cases[] = array('W3-10', 'خفضُ سطحٍ مُنِع خفضُه بقياسٍ',
    function () use ($conn, $esc, $blockedSid) {
        return $conn->query("UPDATE repair01_screen_registry SET visibility_class = 'TAB_CHILD'
                              WHERE screen_id = '" . $esc($blockedSid) . "'");
    },
    function () use ($conn, $esc, $blockedSid, $blockedVis) {
        return $conn->query("UPDATE repair01_screen_registry SET visibility_class = '" . $esc($blockedVis) . "'
                              WHERE screen_id = '" . $esc($blockedSid) . "'");
    });

/* ⑪ W3-11 — بندٌ في النطاقِ يفقد رمزَ صلاحيتِه */
$cases[] = array('W3-11', 'نزعُ رمزِ الصلاحيةِ عن بندٍ في النطاق',
    function () use ($conn, $permNavId) { return $conn->query("UPDATE nav_items SET permission_code = NULL WHERE id = $permNavId"); },
    function () use ($conn, $permNavId, $permCode) { return $conn->query("UPDATE nav_items SET permission_code = '" . $conn->real_escape_string($permCode) . "' WHERE id = $permNavId"); });

/* ⑫ W3-12 — الربطُ بالسجلِّ المعياريِّ يُقلَب */
$cases[] = array('W3-12', 'قلبُ الربطِ بـCanonical Screen_ID',
    function () use ($conn, $canRoute) { return $conn->query("UPDATE nav_canonical SET screen_id = 'SCR-9999' WHERE route = '" . $conn->real_escape_string($canRoute) . "'"); },
    function () use ($conn, $canRoute, $canSid) { return $conn->query("UPDATE nav_canonical SET screen_id = '" . $conn->real_escape_string($canSid) . "' WHERE route = '" . $conn->real_escape_string($canRoute) . "'"); });

/* ⑬ W3-13 — عقدُ أثرٍ يُنزَع عن حدثٍ حيّ */
$cases[] = array('W3-13', 'نزعُ عقدِ الأثرِ عن حدثٍ حيّ',
    function () use ($conn, $eventId) { return $conn->query("DELETE FROM repair01_events WHERE id = $eventId"); },
    function () use ($conn, $snapEvent) { return $conn->query($snapEvent['insert']); });

/* ⑭ W3-13-b — عقدٌ قائمٌ لكنّه ناقصُ الحمولةِ الدنيا (أخضرُ العدِّ لا يكفي) */
$cases[] = array('W3-13', 'عقدٌ قائمٌ بحمولةٍ دنيا فارغة',
    function () use ($conn, $eventId) { return $conn->query("UPDATE repair01_events SET min_payload = '' WHERE id = $eventId"); },
    function () use ($conn, $eventId, $snapEvent) { return $conn->query("UPDATE repair01_events SET min_payload = '" . $conn->real_escape_string($snapEvent['row']['min_payload']) . "' WHERE id = $eventId"); });

/* ⑮ W3-14 — مخزنُ المراحلِ السابقةِ يُمَسّ */
$cases[] = array('W3-14', 'مساسُ مخزنِ المراحلِ السابقة',
    function () use ($conn, $snapMast) { return $conn->query("UPDATE repair01_target_gaps SET origin_stage = 'W03' WHERE origin_stage = '' LIMIT 1"); },
    function () use ($conn) { return $conn->query("UPDATE repair01_target_gaps SET origin_stage = '' WHERE origin_stage = 'W03' AND origin_note = '' LIMIT 1"); });

/* ⑯ W3-15 — رحلةُ الإثباتِ تُكسر بنزعِ حارسِ المفتاحِ الأمّ */
$cases[] = array('W3-15', 'كسرُ رحلةِ الإثباتِ بنزعِ حارسِ المفتاح',
    function () use ($ddl) { return $ddl->query("ALTER TABLE `persons` DROP CONSTRAINT `chk_persons_w3_master_key`"); },
    function () use ($ddl) {
        return $ddl->query("ALTER TABLE `persons` ADD CONSTRAINT `chk_persons_w3_master_key`
            CHECK (NOT (`active` = 1 AND `person_class` = 'WORKFORCE' AND (`employee_id` IS NULL OR `employee_id` = 0)))");
    });

/* ⑰ W3-16 — تركيبةٌ ممنوعةٌ جديدةٌ تظهر في صفٍّ حيّ */
$sodRow = (int) $one("SELECT id FROM mnt_order
                       WHERE technician_id IS NOT NULL AND supervisor_id IS NOT NULL
                         AND technician_id <> supervisor_id ORDER BY id LIMIT 1");
$sodOld = (int) $one("SELECT supervisor_id FROM mnt_order WHERE id = $sodRow");
$cases[] = array('W3-16', 'ظهورُ تركيبةٍ ممنوعةٍ جديدةٍ في صفٍّ حيّ',
    function () use ($conn, $sodRow) { return $conn->query("UPDATE mnt_order SET supervisor_id = technician_id WHERE id = $sodRow"); },
    function () use ($conn, $sodRow, $sodOld) { return $conn->query("UPDATE mnt_order SET supervisor_id = $sodOld WHERE id = $sodRow"); });

$blind = 0; $done = 0;
foreach ($cases as $c) {
    list($want, $title, $break, $restore) = $c;
    $bk = $break();
    if ($bk === false) { printf("  ⚠ %-8s تعذّر الكسر: %s\n", $want, $conn->error); continue; }
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($want, $failed, true);
    if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", $want, $title); }
    else { $blind++; printf("  ✘ %-8s %-46s **لم تسقط** — الحاجبُ أعمى (الساقط: %s)\n", $want, $title, $failed ? implode(',', $failed) : 'لا شيء'); }
    if ($restore() === false) { printf("  ⛔ %-8s فشلَ الإرجاع: %s\n", $want, $conn->error); $blind++; }
    $done++;
}

echo "\n";
list($cz, $fz) = run_gate($PHP, $GATE);
if ($cz === 0) { echo "الإرجاع: البوّابةُ عادت خضراء ✔\n"; }
else { echo "⛔ الإرجاع فاشل — البوّابةُ ما زالت ساقطةً في: " . implode(',', $fz) . "\n"; $blind++; }

printf("\nالفحصُ السلبيّ: %d حاجبًا مُختبَرًا · أعمى %d\n", $done, $blind);
echo ($blind === 0 ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: يوجد حاجبٌ أعمى ✘\n");
exit($blind === 0 ? 0 : 1);
