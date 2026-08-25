<?php
/**
 * tools/repair01_w2_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الثانية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ وحدَه لا يُثبت شيئًا**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويجب أن يسقط **باسمِه هو** — ثمّ
 *   تُرجَع الحالة. الحاجبُ الذي لا يسقط عند كسرِه **أعمى** والمرحلةُ مفتوحة.
 *
 * ◆ **والرسوُّ على الرمزِ لا العبارة**: يُلتقط `✘ W2-nn` — لا نصٌّ عربيٌّ قد
 *   يظهر في رسالةِ حالةِ خطأٍ فيُخضِرَّ كذبًا.
 *
 * ◆ **وثلاثةُ كسورٍ على القرصِ لا في القاعدة**: حواجبُ القشرةِ والحارسِ
 *   (`W2-08` · `W2-09` · `W2-11`) لا تُقاس من جدول — فتُكسَر في الملفّاتِ
 *   نفسِها وتُرجَع **بالبايتِ الأصليّ** داخلَ `finally`.
 *
 * ◆ **وثلاثةُ كواشفَ تُثبَّت بالزرع** لا بالكسر — لأنّ الصفرَ فيها إمّا نظافةٌ
 *   حقيقيةٌ أو عمًى، ولا يفرّق بينهما إلّا مخالفةٌ مزروعةٌ ترتفع ثمّ تُنزع:
 *   شاشةٌ بلا حارس · شاشةٌ مخفيّةٌ بلا حارس · مِرساةٌ بلا صفٍّ في السجلّ.
 *   والملفُّ المزروعُ **خاملٌ بالبنية**: `exit;` في سطرِه الأوّل.
 *
 * ◆ **والإرجاعُ مُتحقَّقٌ منه لا مُدَّعى**: تُعاد البوّابةُ في النهايةِ ويجب أن
 *   تعود خضراءَ ١٣/١٣ — وإلّا فالفحصُ السلبيُّ نفسُه أفسد الحالة.
 *
 * التشغيل: php tools/repair01_w2_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w2_gate.php';
$SIDE = $ROOT . '/insidebar.php';

function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W2-') !== false && preg_match('/W2-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}
function e(mysqli $c, $s) { return "'" . $c->real_escape_string((string) $s) . "'"; }
function one(mysqli $c, $sql) { $r = $c->query($sql); return $r ? $r->fetch_assoc() : null; }
/** إعادةُ إدراجِ صفٍّ **بكلِّ أعمدتِه** — لا بما فحصَه الحاجبُ وحدَه. */
function w2_reinsert(mysqli $c, array $row)
{
    $cols = array(); $vals = array();
    foreach ($row as $k => $v) {
        if ($k === 'updated_at') { continue; }         /* يُملأ تلقائيًّا */
        $cols[] = '`' . $k . '`';
        $vals[] = ($v === null) ? 'NULL' : "'" . $c->real_escape_string((string) $v) . "'";
    }
    return 'INSERT INTO repair01_screen_registry (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
}

list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo '✘ البوّابةُ ساقطةٌ قبل الكسر (' . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ── الحالةُ الأصليّةُ المُلتقَطةُ قبل أيِّ كسر ─────────────────────────── */
/* ⚠ **الصفُّ كلُّه لا أعمدتُه المفحوصةُ وحدَها**: أوّلُ صيغةٍ التقطت تسعةَ
   أعمدةٍ وأعادت الإدراجَ بها، فعاد الصفُّ **بلا `guard_kind`** — إرجاعٌ ناقصٌ
   لا تراه بوّابةٌ لا تفحص ذاك العمود. فالالتقاطُ `*` والإرجاعُ عمودًا عمودًا. */
$oScr  = one($conn, "SELECT * FROM repair01_screen_registry
                     WHERE on_disk=1 AND route IS NOT NULL ORDER BY screen_id LIMIT 1");
$oMenu = one($conn, "SELECT screen_id, visibility_class FROM repair01_screen_registry
                     WHERE visibility_class='MENU_ITEM' ORDER BY screen_id LIMIT 1");
$oGho  = one($conn, "SELECT screen_id, screen_file, ghost_verdict, ghost_why FROM repair01_screen_registry
                     WHERE on_disk=0 ORDER BY screen_id LIMIT 1");
$oGap  = one($conn, "SELECT id, wave_stage FROM repair01_target_gaps WHERE origin_stage='W02' ORDER BY id LIMIT 1");
$oDec  = one($conn, "SELECT decision_id, rationale FROM repair01_w2_decisions ORDER BY decision_id LIMIT 1");
$oAnc  = one($conn, "SELECT id, anchor_key FROM nav_canonical WHERE anchor_key='CHATS' LIMIT 1");
$oSurf = one($conn, "SELECT id, screen_id FROM repair01_surfaces WHERE screen_id <> '' ORDER BY id LIMIT 1");
$sideTxt = (string) file_get_contents($SIDE);

if (!$oScr || !$oMenu || !$oGho || !$oGap || !$oDec || !$oAnc || !$oSurf || $sideTxt === '') {
    echo "✘ تعذّر التقاطُ الحالةِ الأصلية — لا يُكسَر ما لا يُرجَع.\n";
    exit(1);
}
$SID = e($conn, $oScr['screen_id']);

/* ── كسورُ القاعدة: [الحاجب، الوصف، الكسر، الإرجاع] ─────────────────────── */
$cases = array(
    array('W2-01', 'شاشةٌ في المقامِ بلا صفٍّ في السجلّ',
        "DELETE FROM repair01_screen_registry WHERE screen_id=$SID",
        w2_reinsert($conn, $oScr)),

    array('W2-02', 'صفُّ سطحٍ يشير إلى مُعرِّفٍ لا وجودَ له',
        "UPDATE repair01_surfaces SET screen_id='SCR-9999' WHERE id=" . (int) $oSurf['id'],
        "UPDATE repair01_surfaces SET screen_id=" . e($conn, $oSurf['screen_id']) . " WHERE id=" . (int) $oSurf['id']),

    array('W2-03', 'مسارٌ مبنيٌّ لا ملفَّ له على القرص',
        "UPDATE repair01_screen_registry SET route='zz_repair01_w2_negative_no_such_file.php' WHERE screen_id=$SID",
        "UPDATE repair01_screen_registry SET route=" . e($conn, $oScr['route']) . " WHERE screen_id=$SID"),

    array('W2-04', 'قيمةٌ عاريةٌ من قاعدتِها المُعلَنة',
        "UPDATE repair01_screen_registry SET owner_rule='' WHERE screen_id=$SID",
        "UPDATE repair01_screen_registry SET owner_rule=" . e($conn, $oScr['owner_rule']) . " WHERE screen_id=$SID"),

    array('W2-05', 'مؤشِّرٌ إلى قرارٍ بلا مبرَّرٍ مكتوب',
        "UPDATE repair01_w2_decisions SET rationale='' WHERE decision_id=" . e($conn, $oDec['decision_id']),
        "UPDATE repair01_w2_decisions SET rationale=" . e($conn, $oDec['rationale'])
            . " WHERE decision_id=" . e($conn, $oDec['decision_id'])),

    array('W2-06', 'شبحٌ بلا حكمٍ ولا عذرٍ مكتوب',
        "UPDATE repair01_screen_registry SET ghost_verdict='', ghost_why='' WHERE screen_id="
            . e($conn, $oGho['screen_id']),
        "UPDATE repair01_screen_registry SET ghost_verdict=" . e($conn, $oGho['ghost_verdict'])
            . ", ghost_why=" . e($conn, $oGho['ghost_why']) . " WHERE screen_id=" . e($conn, $oGho['screen_id'])),

    array('W2-07', 'شبحٌ منقولٌ بلا موجةٍ مسنَدة',
        "UPDATE repair01_target_gaps SET wave_stage='' WHERE id=" . (int) $oGap['id'],
        "UPDATE repair01_target_gaps SET wave_stage=" . e($conn, $oGap['wave_stage']) . " WHERE id=" . (int) $oGap['id']),

    array('W2-12', 'وسمُ «بندِ قائمة» لما لا صفَّ نشِطًا له',
        "UPDATE repair01_screen_registry SET visibility_class='MENU_ITEM'
          WHERE on_disk=1 AND visibility_class='DIRECT_ONLY'
          ORDER BY screen_id LIMIT 1",
        "UPDATE repair01_screen_registry SET visibility_class='DIRECT_ONLY'
          WHERE visibility_class='MENU_ITEM' AND visibility_rule='NO_NAV_NO_PARENT'"),

    array('W2-13', 'حقنُ قرارٍ في مخزنِ W00 المُجمَّد',
        "INSERT INTO repair01_decisions (decision_id, domain, status) VALUES ('ZZ-NEG-W2','فحصٌ سلبيّ','APPROVED')",
        "DELETE FROM repair01_decisions WHERE decision_id='ZZ-NEG-W2'"),

    array('W2-14', 'استثناءُ «ليس شاشةً» يُعلَن بعددٍ لا يطابق المقيس',
        "UPDATE repair01_w2_decisions SET scope_rows = scope_rows + 1 WHERE decision_id='W2-D-02'",
        "UPDATE repair01_w2_decisions SET scope_rows = scope_rows - 1 WHERE decision_id='W2-D-02'"),
);

$blind = array(); $ok = 0;
echo "── كسورُ القاعدة ──\n";
foreach ($cases as $cse) {
    list($id, $desc, $break, $undo) = $cse;
    $failed = array();
    try {
        if ($conn->query($break) === false) { echo "  ⚠ تعذّر الكسرُ لـ$id: {$conn->error}\n"; }
        list($code, $failed) = run_gate($PHP, $GATE);
    } finally {
        if ($undo !== '') { $conn->query($undo); }
    }
    if (in_array($id, $failed, true)) { $ok++; printf("  ✔ %-7s سقط عند: %s\n", $id, $desc); }
    else { $blind[] = $id; printf("  ✘ %-7s **أعمى** — لم يسقط عند: %s  (الساقط: %s)\n", $id, $desc, $failed ? implode(',', $failed) : 'لا شيء'); }
}

/* ── كسرٌ على القرص: بندُ قائمةٍ يدويٌّ يعود إلى القشرة ─────────────────── */
echo "\n── كسورُ القرص ──\n";
$fileCases = array(
    array('W2-08', 'بندُ قائمةٍ بمسارٍ حرفيٍّ يعود إلى القشرة', $SIDE, $sideTxt,
        function ($t) {
            return str_replace('<ul id="sidebarNavList">',
                '<ul id="sidebarNavList">' . "\n" . '<li><a href="../zz/negative_probe.php">zz</a></li>', $t);
        }),
    array('W2-11', 'نداءُ مِرساةٍ لا صفَّ لها في السجلّ', $SIDE, $sideTxt,
        function ($t) {
            return str_replace("ems_nav_anchor_li(\$conn, 'SETTINGS'", "ems_nav_anchor_li(\$conn, 'ZZNEG'", $t);
        }),
);
foreach ($fileCases as $fc) {
    list($id, $desc, $path, $orig, $mutate) = $fc;
    $failed = array();
    try {
        file_put_contents($path, $mutate($orig));
        list($code, $failed) = run_gate($PHP, $GATE);
    } finally {
        file_put_contents($path, $orig);
    }
    if (in_array($id, $failed, true)) { $ok++; printf("  ✔ %-7s سقط عند: %s\n", $id, $desc); }
    else { $blind[] = $id; printf("  ✘ %-7s **أعمى** — لم يسقط عند: %s  (الساقط: %s)\n", $id, $desc, $failed ? implode(',', $failed) : 'لا شيء'); }
}

/* ═══ الزرعُ: صفرُ «شاشةٍ بلا حارس» — نظافةٌ أم عمًى؟ ═══════════════════════
   يُزرع ملفُّ شاشةٍ يحمل القشرةَ **وكتابةً تسبقها**، فيجب أن يرتفع `W2-09`.
   ثمّ يُوصل بمسارِ تنقّلٍ مُطفأٍ فيجب أن يرتفع `W2-10` أيضًا — «المخفيُّ
   محروسٌ لا مستورٌ فقط». والملفُّ **خاملٌ بالبنية**: `exit;` أوّلَ سطر. */
echo "\n── الزرعُ: كواشفُ الحارس ──\n";
$PROBE = $ROOT . '/Governance/zz_repair01_w2_negative_probe.php';
$probeSrc = "<?php exit; /* ملفُّ زرعٍ للفحصِ السلبيِّ — يُنشأ ويُنزع في الثانيةِ نفسِها */\n"
          . "\$conn->query(\"INSERT INTO zz_negative_probe (a) VALUES (1)\");\n"
          . "include '../insidebar.php';\n";
$NAVROUTE = 'Governance/zz_repair01_w2_negative_probe.php';

$plant = array(
    array('W2-09', 'شاشةٌ تكتب قبلَ حارسِها', false),
    array('W2-10', 'مسارٌ مُطفأٌ يفتح شاشةً بلا حارس', true),
);
$navId = 0;
foreach ($plant as $pc) {
    list($id, $desc, $withNav) = $pc;
    $failed = array();
    try {
        file_put_contents($PROBE, $probeSrc);
        if ($withNav) {
            $conn->query("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
                          VALUES (0, 'SET', NULL, NULL, 'zz فحصٌ سلبيّ', " . e($conn, $NAVROUTE) . ", '', 0, NULL, 0)");
            $navId = (int) $conn->insert_id;
        }
        list($code, $failed) = run_gate($PHP, $GATE);
    } finally {
        @unlink($PROBE);
        if ($navId > 0) { $conn->query("DELETE FROM nav_items WHERE id=$navId"); $navId = 0; }
    }
    if (in_array($id, $failed, true)) { $ok++; printf("  ✔ %-7s ارتفع عند: %s\n", $id, $desc); }
    else { $blind[] = $id; printf("  ✘ %-7s **أعمى** — لم يرتفع عند: %s  (الساقط: %s)\n", $id, $desc, $failed ? implode(',', $failed) : 'لا شيء'); }
}

/* ── الإرجاعُ مُتحقَّقٌ منه لا مُدَّعى ─────────────────────────────────────── */
echo "\n── الإرجاع ──\n";
if (is_file($PROBE)) { @unlink($PROBE); }
list($cE, $fE) = run_gate($PHP, $GATE);
$restored = ($cE === 0);
echo $restored ? "  ✔ البوّابةُ عادت خضراءَ بعدَ كلِّ كسرٍ وإرجاع\n"
               : "  ✘ **البوّابةُ لم تعد خضراء** — الساقط: " . implode(',', $fE) . "\n";

$total = count($cases) + count($fileCases) + count($plant);
echo "\n" . str_repeat('─', 74) . "\n";
printf("الفحصُ السلبيّ: %d/%d حاجبًا يقظًا · أعمى %d · الإرجاع %s\n",
    $ok, $total, count($blind), $restored ? 'متحقَّق ✔' : 'فاشل ✘');
if ($blind) { echo 'الأعمى: ' . implode('، ', $blind) . "\n"; }
exit(($blind === array() && $restored) ? 0 : 1);
