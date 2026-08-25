<?php
/**
 * tools/repair01_w6_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ السادسة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الأخضرُ لا يُثبت شيئًا وحدَه: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويُطلب من البوّابةِ أن تسقط — ثم
 *   يُرجَع الحال. **والحاجبُ الذي لا يسقط عند كسرِه أعمى.**
 *
 * ◆ **والرسوُّ على رمزِ الحاجبِ لا على عبارتِه** (‏_CONTEXT §قواعد القياس ٣):
 *   يُلتقط `✘ W6-nn` من المخرَج — لا عبارةٌ عربيّةٌ يطابقها نصُّ حالةِ الخطأ.
 *
 * ◆ **وكسرٌ في الشيفرةِ لا في القاعدةِ وحدَها**: `W6-11` يرسو على بنيةِ
 *   `WorkItemService` — فيُكسَر بتعديلِ الملفِّ نفسِه ويُرجَع من نسخةٍ في
 *   الذاكرةِ، **ويُتحقَّق من الإرجاعِ بمقارنةِ تجزئةِ sha1 قبلَ وبعد**.
 *   وملفٌّ لم يرجع بتجزئتِه عيبٌ يُعلَن ولا يُسكت عنه.
 *
 * التشغيل: php tools/repair01_w6_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w6_gate.php';
$esc  = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one  = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) { return null; }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
};

/** يشغّل البوّابةَ ويعيد [رمزُ الخروج، أيُّ حاجبٍ سقط]. */
function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W6-') !== false && preg_match('/W6-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ── القيمُ الملتقَطةُ للإرجاعِ بالقيمةِ لا بالتخمين ─────────────────────── */
$navId    = (int) $one("SELECT id FROM nav_items WHERE active = 1 AND label_ar <> '' ORDER BY id LIMIT 1");
$navLbl   = (string) $one("SELECT label_ar FROM nav_items WHERE id = $navId");
$taxCode  = (string) $one("SELECT code FROM nav_group_taxonomy ORDER BY sort_no LIMIT 1");
$taxName  = (string) $one("SELECT name_ar FROM nav_group_taxonomy WHERE code = '" . $esc($taxCode) . "'");
$cycId    = (int) $one("SELECT id FROM gov_screen_cycle WHERE next_state <> '' AND next_state NOT IN ('—','-') ORDER BY id LIMIT 1");
$cycNext  = (string) $one("SELECT next_state FROM gov_screen_cycle WHERE id = $cycId");
$scopeKey = (string) $one("SELECT source_key FROM repair01_w6_scope WHERE is_rendered = 1 ORDER BY purify_order LIMIT 1");
$scopeRen = (string) $one("SELECT renderer FROM repair01_w6_scope WHERE source_key = '" . $esc($scopeKey) . "'");
$noRenKey = (string) $one("SELECT source_key FROM repair01_w6_scope WHERE is_rendered = 0 LIMIT 1");
$noRenVis = (string) $one("SELECT visibility_class FROM repair01_w6_scope WHERE source_key = '" . $esc($noRenKey) . "'");
$labKey   = (string) $one("SELECT technical_key FROM repair01_ui_labels WHERE technical_key LIKE 'group_key:%' LIMIT 1");
$labDep   = (string) $one("SELECT deprecated_label FROM repair01_ui_labels WHERE technical_key = '" . $esc($labKey) . "'");
$declLen  = (int) $one("SELECT scope_rows FROM repair01_w6_decisions WHERE decision_id = 'W6-D-08'");
$dictAr   = (string) $one("SELECT display_ar FROM repair01_w6_code_dict WHERE raw_code = 'NEEDS_SOURCE'");
$dictSh   = (string) $one("SELECT display_short FROM repair01_w6_code_dict WHERE raw_code = 'NEEDS_SOURCE'");
$dictFam  = (string) $one("SELECT code_family FROM repair01_w6_code_dict WHERE raw_code = 'NEEDS_SOURCE'");
$dictCtx  = (string) $one("SELECT allowed_context FROM repair01_w6_code_dict WHERE raw_code = 'NEEDS_SOURCE'");
$dictWhy  = (string) $one("SELECT why FROM repair01_w6_code_dict WHERE raw_code = 'NEEDS_SOURCE'");
$dictRef  = (string) $one("SELECT src_ref FROM repair01_w6_code_dict WHERE raw_code = 'NEEDS_SOURCE'");
$thVal    = (int) $one("SELECT value_no FROM repair01_w6_thresholds WHERE threshold_key = 'MAX_LEN_MENU'");
$thWhy    = (string) $one("SELECT why FROM repair01_w6_thresholds WHERE threshold_key = 'MAX_LEN_MENU'");
$thApp    = (string) $one("SELECT applies_to FROM repair01_w6_thresholds WHERE threshold_key = 'MAX_LEN_MENU'");
$thRef    = (string) $one("SELECT src_ref FROM repair01_w6_thresholds WHERE threshold_key = 'MAX_LEN_MENU'");
$sodKey   = (string) $one("SELECT process_key FROM repair01_w6_sod ORDER BY process_key LIMIT 1");
$sodCombo = (string) $one("SELECT forbidden_combo FROM repair01_w6_sod WHERE process_key = '" . $esc($sodKey) . "'");
$evCode   = (string) $one("SELECT event_code FROM repair01_events WHERE contract_stage = 'W06' ORDER BY id LIMIT 1");
$evEffect = (string) $one("SELECT consumer_effect FROM repair01_events WHERE contract_stage = 'W06' AND event_code = '" . $esc($evCode) . "'");
/* مفتاحُ سجلِّ الشاشاتِ `screen_id` لا `id` — والكسرُ بمفتاحٍ لا وجودَ له
   «يتعذّر» فيُقرأ الحاجبُ يقظًا وهو لم يُختبَر أصلًا. */
$regSid   = (string) $one("SELECT screen_id FROM repair01_screen_registry
                            WHERE origin NOT IN ('SURFACES','DISK','NAV') ORDER BY screen_id LIMIT 1");
$regOrg   = (string) $one("SELECT origin FROM repair01_screen_registry WHERE screen_id = '" . $esc($regSid) . "'");

$BASEFILE = $ROOT . '/docs/update0012/debt_baseline.json';
$baseJson = (string) @file_get_contents($BASEFILE);
$WIS      = $ROOT . '/app/Services/Work/WorkItemService.php';
$wisSrc   = (string) @file_get_contents($WIS);
$wisHash  = sha1($wisSrc);

/* ── حالاتُ الكسر: [الحاجبُ المتوقَّع، العنوان، كسرٌ، إرجاع] ───────────────
     والكسرُ والإرجاعُ إمّا SQL نصًّا أو دالّةٌ تُنفَّذ.                       */
$cases = array(
    array('W6-01', 'نزعُ مُصيِّرِ مصدرٍ من دفترِ النطاق',
        "UPDATE repair01_w6_scope SET renderer = '' WHERE source_key = '" . $esc($scopeKey) . "'",
        "UPDATE repair01_w6_scope SET renderer = '" . $esc($scopeRen) . "' WHERE source_key = '" . $esc($scopeKey) . "'"),

    array('W6-02', 'حقنُ تشكيلٍ في بندِ سايدبارٍ حيّ',
        "UPDATE nav_items SET label_ar = CONCAT(label_ar, 'ٌ') WHERE id = $navId",
        "UPDATE nav_items SET label_ar = '" . $esc($navLbl) . "' WHERE id = $navId"),

    array('W6-02', 'حقنُ تشكيلٍ في رأسِ مجموعةٍ مُصيَّر',
        "UPDATE nav_group_taxonomy SET name_ar = CONCAT(name_ar, 'ُ') WHERE code = '" . $esc($taxCode) . "'",
        "UPDATE nav_group_taxonomy SET name_ar = '" . $esc($taxName) . "' WHERE code = '" . $esc($taxCode) . "'"),

    array('W6-03', 'حقنُ اسمِ جدولٍ في سطرِ الدورة',
        "UPDATE gov_screen_cycle SET next_state = CONCAT(next_state, ' asset_intake') WHERE id = $cycId",
        "UPDATE gov_screen_cycle SET next_state = '" . $esc($cycNext) . "' WHERE id = $cycId"),

    array('W6-04', 'حقنُ معادلةٍ في سطرِ الدورة',
        "UPDATE gov_screen_cycle SET next_state = CONCAT(next_state, ' والمتاح = المعتمد') WHERE id = $cycId",
        "UPDATE gov_screen_cycle SET next_state = '" . $esc($cycNext) . "' WHERE id = $cycId"),

    array('W6-05', 'اسمُ مجموعةٍ يُصيَّر ولا صفَّ له في السجلّ',
        "UPDATE nav_group_taxonomy SET name_ar = 'مجموعة بلا سجل' WHERE code = '" . $esc($taxCode) . "'",
        "UPDATE nav_group_taxonomy SET name_ar = '" . $esc($taxName) . "' WHERE code = '" . $esc($taxCode) . "'"),

    array('W6-06', 'وسمُ صيغةٍ حيّةٍ بأنّها متقاعدة',
        "UPDATE repair01_ui_labels SET deprecated_label = '" . $esc($taxName) . "' WHERE technical_key = '" . $esc($labKey) . "'",
        "UPDATE repair01_ui_labels SET deprecated_label = '" . $esc($labDep) . "' WHERE technical_key = '" . $esc($labKey) . "'"),

    array('W6-07', 'إدخالُ مسمًّى مشكولٍ في السجلِّ من خلفِ الخدمة',
        "INSERT INTO repair01_ui_labels (technical_key, arabic_ui_label, allowed_context, visibility_class, origin)
         VALUES ('w6neg:dirty', 'سجلُّ المشغِّلين', 'SIDEBAR', 'USER_VISIBLE', 'W06')",
        "DELETE FROM repair01_ui_labels WHERE technical_key = 'w6neg:dirty'"),

    array('W6-07', 'محوُ قيدِ الرفضِ بالتشكيل — فالردُّ يصير دعوى',
        "UPDATE repair01_w6_reject_log SET reject_code = 'W6NEG_HIDDEN' WHERE reject_code = 'DIACRITICS'",
        "UPDATE repair01_w6_reject_log SET reject_code = 'DIACRITICS' WHERE reject_code = 'W6NEG_HIDDEN'"),

    array('W6-08', 'خفضُ السقفِ المُعلَنِ للطولِ الزائد',
        "UPDATE repair01_w6_decisions SET scope_rows = 0 WHERE decision_id = 'W6-D-08'",
        "UPDATE repair01_w6_decisions SET scope_rows = $declLen WHERE decision_id = 'W6-D-08'"),

    array('W6-09', 'نزعُ رمزٍ من قاموسِ العرضِ فيظهر خامًّا',
        "DELETE FROM repair01_w6_code_dict WHERE raw_code = 'NEEDS_SOURCE'",
        "INSERT INTO repair01_w6_code_dict (raw_code, display_ar, display_short, code_family, allowed_context, why, src_ref)
         VALUES ('NEEDS_SOURCE', '" . $esc($dictAr) . "', '" . $esc($dictSh) . "', '" . $esc($dictFam) . "',
                 '" . $esc($dictCtx) . "', '" . $esc($dictWhy) . "', '" . $esc($dictRef) . "')"),

    array('W6-10', 'وسمُ مصدرٍ لا يُصيَّر بأنّه يراه المستخدم',
        "UPDATE repair01_w6_scope SET visibility_class = 'USER_VISIBLE' WHERE source_key = '" . $esc($noRenKey) . "'",
        "UPDATE repair01_w6_scope SET visibility_class = '" . $esc($noRenVis) . "' WHERE source_key = '" . $esc($noRenKey) . "'"),

    array('W6-12', 'نزعُ عذرِ عتبةٍ فتصير رقمًا بلا قاعدة',
        "UPDATE repair01_w6_thresholds SET why = '' WHERE threshold_key = 'MAX_LEN_MENU'",
        "UPDATE repair01_w6_thresholds SET why = '" . $esc($thWhy) . "' WHERE threshold_key = 'MAX_LEN_MENU'"),

    array('W6-14', 'نزعُ سببِ انتقالٍ ممنوعٍ صراحةً',
        "UPDATE repair01_w6_states SET precondition = '' WHERE allowed = 0 LIMIT 1",
        null),

    array('W6-15', 'نزعُ التركيبةِ الممنوعةِ من عمليةٍ حرجة',
        "UPDATE repair01_w6_sod SET forbidden_combo = '' WHERE process_key = '" . $esc($sodKey) . "'",
        "UPDATE repair01_w6_sod SET forbidden_combo = '" . $esc($sodCombo) . "' WHERE process_key = '" . $esc($sodKey) . "'"),

    array('W6-16', 'نزعُ أثرِ المستهلكِ من عقدِ حدث',
        "UPDATE repair01_events SET consumer_effect = '' WHERE contract_stage = 'W06' AND event_code = '" . $esc($evCode) . "'",
        "UPDATE repair01_events SET consumer_effect = '" . $esc($evEffect) . "' WHERE contract_stage = 'W06' AND event_code = '" . $esc($evCode) . "'"),

    array('W6-17', 'نموٌّ في سجلِّ الشاشاتِ بلا ختمِ موجة',
        "UPDATE repair01_screen_registry SET origin = 'XX' WHERE screen_id = '" . $esc($regSid) . "'",
        "UPDATE repair01_screen_registry SET origin = '" . $esc($regOrg) . "' WHERE screen_id = '" . $esc($regSid) . "'"),
);

$blind = 0; $done = 0;

/** الحالاتُ التي تحتاج التقاطَ قيمةٍ قبل الكسرِ ثم إرجاعَها بها. */
$stateRow = null;
$r = $conn->query("SELECT entity, from_state, to_state, precondition FROM repair01_w6_states
                    WHERE allowed = 0 ORDER BY entity, from_state, to_state LIMIT 1");
if ($r) { $stateRow = $r->fetch_assoc(); }

foreach ($cases as $c) {
    list($want, $title, $break, $restore) = $c;
    if ($want === 'W6-14' && $stateRow) {
        $break = "UPDATE repair01_w6_states SET precondition = '' WHERE entity = '" . $esc($stateRow['entity'])
               . "' AND from_state = '" . $esc($stateRow['from_state']) . "' AND to_state = '" . $esc($stateRow['to_state']) . "'";
        $restore = "UPDATE repair01_w6_states SET precondition = '" . $esc($stateRow['precondition'])
                 . "' WHERE entity = '" . $esc($stateRow['entity']) . "' AND from_state = '"
                 . $esc($stateRow['from_state']) . "' AND to_state = '" . $esc($stateRow['to_state']) . "'";
    }
    if ($conn->query($break) === false) { printf("  ⚠ %-8s تعذّر الكسر: %s\n", $want, $conn->error); continue; }
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($want, $failed, true);
    if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", $want, $title); }
    else { $blind++; printf("  ✘ %-8s %-46s **لم تسقط** — أعمى (الساقط: %s)\n", $want, $title, $failed ? implode(',', $failed) : 'لا شيء'); }
    if ($restore !== null && $conn->query($restore) === false) {
        printf("  ⛔ %-8s فشلَ الإرجاع: %s\n", $want, $conn->error); $blind++;
    }
    $done++;
}

/* ── W6-13 · خطُّ أساسِ الدَّينِ ملفٌّ لا صفّ ─────────────────────────────── */
$bj = json_decode($baseJson, true);
if (is_array($bj) && isset($bj['debts']['UI-01'])) {
    $bj['debts']['UI-01'] = max(0, (int) $bj['debts']['UI-01'] - 1);
    file_put_contents($BASEFILE, json_encode($bj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array('W6-13', $failed, true);
    if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", 'W6-13', 'خفضُ خطِّ أساسِ UI-01 فيصير الحيُّ زائدًا'); }
    else { $blind++; printf("  ✘ %-8s %-46s **لم تسقط** — أعمى\n", 'W6-13', 'خفضُ خطِّ أساسِ UI-01'); }
    file_put_contents($BASEFILE, $baseJson);
    $done++;
}

/* ── W6-11 · كسرٌ في الشيفرةِ: إعادةُ شرطةِ الربطِ إلى المولِّد ───────────── */
$broken = str_replace("'title' => \$a['title'] ?? \$titleAr,",
                      "'title' => \$a['title'] ?? (\$act['label_ar'] . ' — ' . \$act['screen_title']),", $wisSrc);
if ($broken !== $wisSrc) {
    file_put_contents($WIS, $broken);
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array('W6-11', $failed, true);
    if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", 'W6-11', 'إعادةُ شرطةِ الربطِ إلى المولِّد'); }
    else { $blind++; printf("  ✘ %-8s %-46s **لم تسقط** — أعمى (الساقط: %s)\n", 'W6-11', 'إعادةُ شرطةِ الربط', $failed ? implode(',', $failed) : 'لا شيء'); }
    file_put_contents($WIS, $wisSrc);
    if (sha1((string) file_get_contents($WIS)) !== $wisHash) {
        echo "  ⛔ W6-11   الملفُّ لم يرجع بتجزئتِه — أرجِعْه يدويًّا من git\n"; $blind++;
    }
    $done++;
} else {
    echo "  ⚠ W6-11   تعذّر الكسرُ: نمطُ التركيبِ لم يُطابَق\n";
}

/* ── W6-18 · كسرُ الرحلةِ: نزعُ مسمّى الفعلِ الذي تُركَّب منه ────────────── */
$actCode = (string) $one("SELECT a.canonical_code FROM nav09_action_map a
                            JOIN repair01_ui_labels l ON l.technical_key = CONCAT('action:', a.canonical_code)
                           WHERE a.canonical_file IS NOT NULL AND a.canonical_file <> ''
                           ORDER BY a.canonical_code LIMIT 1");
if ($actCode !== '') {
    /* ◆ **والكسرُ يصيب المحطّةَ التي يريدها**: إطالةُ المسمّى لا تكسر شيئًا —
         المولِّدُ يقرأ من السجلِّ فيطابقه المولَّدُ مهما طال. والكسرُ الصائبُ
         **إعادةُ شرطةِ الربطِ إلى المسمّى نفسِه**: فيولد عنوانُ بندِ العملِ
         حاملًا لها وتسقط محطّةُ «بلا شرطةِ ربط» وحدَها. */
    $actLbl  = (string) $one("SELECT arabic_ui_label FROM repair01_ui_labels WHERE technical_key = 'action:" . $esc($actCode) . "'");
    $conn->query("UPDATE repair01_ui_labels SET arabic_ui_label = CONCAT(arabic_ui_label, ' — الشاشة')
                   WHERE technical_key = 'action:" . $esc($actCode) . "'");
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array('W6-18', $failed, true);
    if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", 'W6-18', 'مسمّى الفعلِ يخالف عنوانَ بندِ العملِ المولَّد'); }
    else { $blind++; printf("  ✘ %-8s %-46s **لم تسقط** — أعمى (الساقط: %s)\n", 'W6-18', 'كسرُ مطابقةِ الرحلة', $failed ? implode(',', $failed) : 'لا شيء'); }
    $conn->query("UPDATE repair01_ui_labels SET arabic_ui_label = '" . $esc($actLbl) . "'
                   WHERE technical_key = 'action:" . $esc($actCode) . "'");
    $done++;
}

/* ── W6-19 · كسرٌ في **ملفِّ شاشة**: تشكيلٌ في نصٍّ يراه المستخدم ────────────
     ◆ **والكسرُ بملفٍّ يُنشَأ ثمَّ يُمحى لا بتعديلِ ملفٍّ قائم**: تعديلُ ملفٍّ
       حيٍّ يترك الشجرةَ مُفسَدةً إن انقطع التشغيل، و«الفاحصُ متعفّن» يعني
       عادةً أنَّ الشجرةَ مُفسَدة. والملفُّ المُنشَأُ أثرُه غيابُه. */
$probe = $ROOT . '/includes/_w6_negative_probe.php';
@unlink($probe);
file_put_contents($probe, "<?php\n/* مِجَسُّ الفحصِ السلبيِّ — يُمحى في آخرِ الجولة */\n"
    . "echo 'لوحةُ المشغِّلين';\n");
list($code, $failed) = run_gate($PHP, $GATE);
$caught = in_array('W6-19', $failed, true);
if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", 'W6-19', 'تشكيلٌ في نصٍّ مُصيَّرٍ داخلَ ملفِّ شاشة'); }
else { $blind++; printf("  ✘ %-8s %-46s **لم تسقط** — أعمى (الساقط: %s)\n", 'W6-19', 'تشكيلٌ في ملفِّ شاشة', $failed ? implode(',', $failed) : 'لا شيء'); }
@unlink($probe);
if (is_file($probe)) { echo "  ⛔ W6-19   مِجَسُّ الفحصِ لم يُمحَ — امحُه يدويًّا\n"; $blind++; }
$done++;

/* ── W6-19 (ب) · معجمُ الاقترانِ يُعذِر بلا إعلان ───────────────────────────
     الإعفاءُ بلا سببٍ مكتوبٍ سترٌ للدَّينِ لا نقاءٌ — والحاجبُ يشترط أن يكون
     كلُّ مُعذَرٍ **مُعلَنًا بعددِه وسببِه**. */
$cpTerm = (string) $one("SELECT term FROM repair01_w6_coupled ORDER BY marks DESC LIMIT 1");
if ($cpTerm !== '') {
    $cpWhy = (string) $one("SELECT why FROM repair01_w6_coupled WHERE term = '" . $esc($cpTerm) . "'");
    $conn->query("UPDATE repair01_w6_coupled SET why = '' WHERE term = '" . $esc($cpTerm) . "'");
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array('W6-19', $failed, true);
    if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", 'W6-19', 'مُعذَرٌ بلا سببٍ مكتوبٍ في معجمِ الاقتران'); }
    else { $blind++; printf("  ✘ %-8s %-46s **لم تسقط** — أعمى\n", 'W6-19', 'مُعذَرٌ بلا سببٍ مكتوب'); }
    $conn->query("UPDATE repair01_w6_coupled SET why = '" . $esc($cpWhy) . "' WHERE term = '" . $esc($cpTerm) . "'");
    $done++;
}

/* ── W6-20 · نقطتانِ في اسمِ عنصرٍ حيّ ─────────────────────────────────── */
$conn->query("UPDATE nav_items SET label_ar = CONCAT(label_ar, ': فرع') WHERE id = $navId");
list($code, $failed) = run_gate($PHP, $GATE);
$caught = in_array('W6-20', $failed, true);
if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", 'W6-20', 'نقطتانِ في بندِ سايدبارٍ حيّ'); }
else { $blind++; printf("  ✘ %-8s %-46s **لم تسقط** — أعمى (الساقط: %s)\n", 'W6-20', 'نقطتانِ في بندِ سايدبار', $failed ? implode(',', $failed) : 'لا شيء'); }
$conn->query("UPDATE nav_items SET label_ar = '" . $esc($navLbl) . "' WHERE id = $navId");
$done++;

/* ── W6-20 (ب) · **ارتدادُ الجولةِ الأولى نفسِه**: الشرطةُ تعود نقطتَين ──────
     وهذا أهمُّ كسرٍ في هذه الجولة: يثبت أنَّ البوّابةَ ترصد **العطبَ الذي
     أنتجته الجولةُ الأولى وهي خضراء**، لا أثرَه في الجداولِ وحدَه. */
$PUR  = $ROOT . '/app/Services/Ui/UiPurity.php';
$purSrc  = (string) @file_get_contents($PUR);
$purHash = sha1($purSrc);
$purBroken = str_replace("\$s = preg_replace('/\\s*[\\x{2014}\\x{2013}]\\s*/u', ' ', \$s);",
                         "\$s = preg_replace('/\\s*[\\x{2014}\\x{2013}]\\s*/u', ': ', \$s);", $purSrc);
if ($purBroken !== $purSrc) {
    file_put_contents($PUR, $purBroken);
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array('W6-20', $failed, true);
    if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", 'W6-20', 'ارتدادُ الشرطةِ إلى نقطتَين في المنقّي'); }
    else { $blind++; printf("  ✘ %-8s %-46s **لم تسقط** — أعمى (الساقط: %s)\n", 'W6-20', 'ارتدادُ الشرطةِ إلى نقطتَين', $failed ? implode(',', $failed) : 'لا شيء'); }
    file_put_contents($PUR, $purSrc);
    if (sha1((string) file_get_contents($PUR)) !== $purHash) {
        echo "  ⛔ W6-20   الملفُّ لم يرجع بتجزئتِه — أرجِعْه يدويًّا من git\n"; $blind++;
    }
    $done++;
} else {
    echo "  ⚠ W6-20   تعذّر الكسرُ: سطرُ نزعِ الشرطةِ لم يُطابَق\n"; $blind++;
}

/* ── التحقُّقُ من الإرجاع ──────────────────────────────────────────────── */
echo "\n";
$conn->query("DELETE FROM repair01_ui_labels WHERE technical_key LIKE 'w6neg:%'");
list($cz, $fz) = run_gate($PHP, $GATE);
if ($cz === 0) { echo "الإرجاع: البوّابةُ عادت خضراء ✔\n"; }
else { echo "⛔ الإرجاع فاشل — البوّابةُ ما زالت ساقطةً في: " . implode(',', $fz) . "\n"; $blind++; }

printf("\nالفحصُ السلبيّ: %d كسرًا مُختبَرًا · أعمى %d\n", $done, $blind);
echo ($blind === 0 ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: يوجد حاجبٌ أعمى ✘\n");
exit($blind === 0 ? 0 : 1);
