<?php
/**
 * tools/rpr02_grain_measure.php — `RPR-02` §٧ الخطوة ١ · **حبّةُ المبنيِّ مقيسةً**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٧ الخطوة ١: *«تصحيحُ الحبّة — كما في الملف، **ولا
 *   سطحَ يجمع حبّتين**»*. و§٥ يحكم اتّجاهَ القياس: *«تبدأ **من المبنيّ** وتسأل
 *   هل يطابق المُصمَّم»* ⇒ فالحبّةُ هنا **تُنتزع من الأثرِ المبنيِّ نفسِه**، لا
 *   تُنسخ من الملفِّ التصميميِّ ثمَّ تُقارَن بنفسِها (وذاك دَورٌ لا قياس).
 *
 * ◆ **ولماذا الآن**: §٤·٢ يعرّف `مطابَق` بـ*«سطحٍ مبنيٍّ بالحبّةِ والمالكِ
 *   نفسيهما»*، و`grain_ar` مملوءةٌ في **٤٧ من ٦٢١** وكلُّها **مُعلَنةٌ** بقرارِ
 *   الموجتَين ١٤/١٥ لا مقيسةً. فالمفتاحُ التعريفيُّ غائبٌ ⇒ و١٣٠ هدفًا معلَّقةٌ
 *   عليه. **وهذا الحاجزُ مسجَّلٌ في رأسِ `rpr02_target_adjudicate.php` منذ بنائه.**
 *
 * ◆ **ستُّ قواعدَ بترتيبٍ حتميّ — ولكلٍّ شاهدُها**:
 *   **G1 · مُعلَنٌ في سجلِّ `cmp03`** — الشاشةُ المولَّدةُ لها جدولُها المصرَّحُ
 *        في `includes/cmp03_registry.php`. أقوى الشواهدِ لأنَّه **تصريحٌ لا
 *        استنتاج**. الشاهد: مفتاحُ السجلِّ واسمُ الجدول.
 *   **G2 · جدولٌ مكتوبٌ واحد** — `INSERT/UPDATE/DELETE/REPLACE` على جدولِ أعمالٍ
 *        واحدٍ ⇒ السطحُ **مصدرُ حقيقتِه**. الشاهد: الفعلُ وعددُ مواضعِه.
 *   **G3 · مكتوبان أبٌ وابنُه** — كـ`x` و`x_lines` ⇒ حبّةٌ واحدةٌ بأمٍّ وبنودٍ
 *        (§٧ الخطوة ٢)، والحبّةُ **الأمُّ**. الشاهد: الاسمان وقاعدةُ البنوّة.
 *   **G4 · مكتوبان غيرُ مترابطَين** ⇒ `grain_multi=1` — **خرقُ «لا سطحَ يجمع
 *        حبّتين» مقيسًا لا مُقدَّرًا**؛ والحبّةُ تُكتب للأكثرِ كتابةً **ويبقى
 *        الوسمُ ظاهرًا** فلا يُخفى الخرقُ داخلَ حبّةٍ نظيفةِ المظهر.
 *   **G5 · لا كتابةَ · وتجميعٌ** (`GROUP BY`/`SUM(`/`COUNT(`) ⇒ `LIVE_READ`.
 *   **G6 · لا كتابةَ ولا تجميع** ⇒ `LIST` — قراءةٌ صِرفة.
 *
 * ◆ **وجداولُ البنيةِ لا تُقصى إقصاءً** — بل **تُؤخَّر**: فلو أُقصيت لصار
 *   `roles.php` و`modules.php` و`activity_logs.php` بلا حبّةٍ **وحبّتُها هي هي**.
 *   ⇒ فترتيبُ المرشَّحين: جداولُ الأعمالِ أوّلًا، **فإن خلا الميدانُ فالبنيةُ
 *   حبّةٌ بحقِّها** ويُسمّى ذلك في الشاهد (`INFRA_ONLY`).
 *
 * ⛔ **وما تعذّر حلُّ مسارِه أو خلا من جدولٍ يبقى `NONE` بشاهدِ عجزِه** — ولا
 *   يُملأ بتخمين. فصفرُ الحبّةِ المقيسةِ **حقيقةٌ تُعرض** لا فراغٌ يُسدّ.
 *
 * التشغيل:
 *   php tools/rpr02_grain_measure.php [--apply] [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

/* قشرةٌ لا حبّةَ فيها — تُستثنى من تتبّعِ الاشتمال */
$CHROME = array('config.php','session_bootstrap.php','inheader.php','insidebar.php',
                'page_header.php','permissions_helper.php','gov_columns.php',
                'screen_contract.php','env.php','db.php','footer.php','infooter.php',
                'csrf.php','csrf_helper.php','auth.php','ems_action_guard.php');

/* جداولُ البنيةِ — **تُؤخَّر ولا تُقصى** */
$INFRA = array('users','roles','permissions','role_permissions','user_permissions',
               'modules','nav_items','link_groups','sessions','companies','settings',
               'activity_logs','audit_log','screen_about','gov_screen_cycle',
               'ems_business_events','ems_event_consumers','event_consumers',
               'ems_event_dead_letter','gov_columns','gov_target_nav','nav_canonical',
               'schema_migrations','information_schema');

/* ═══ ① انتزاعُ الجداولِ بأدوارِها من مصدرٍ واحد ═══════════════════════ */
function grain_tables($src, $known = null)
{
    $out = array('w' => array(), 'r' => array());
    if (preg_match_all('~\b(?:INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([a-z][a-z0-9_]{2,})`?~i', $src, $m)) {
        foreach ($m[1] as $t) { $t = strtolower($t); $out['w'][$t] = isset($out['w'][$t]) ? $out['w'][$t] + 1 : 1; }
    }
    if (preg_match_all('~\b(?:FROM|JOIN)\s+`?([a-z][a-z0-9_]{2,})`?~i', $src, $m)) {
        foreach ($m[1] as $t) { $t = strtolower($t); $out['r'][$t] = isset($out['r'][$t]) ? $out['r'][$t] + 1 : 1; }
    }
    /* ⛔ **والمرشَّحُ لا يصير جدولًا بشكلِه بل بوجودِه في المخطَّط**:
       نصٌّ كـ«UPDATE failed» أو «company_id» أو «without» يطابق الشكلَ ولا
       يطابق شيئًا في القاعدة. **فالتصفيةُ بالمخطَّطِ الحيِّ قطعيّةٌ لا تخمينيّة**
       — وهي درسُ «صفرٌ من مفردةٍ لا وجودَ لها»: مقياسٌ على رمزٍ غيرِ موجودٍ
       ليس مقياسًا. (‏و`$known === null` تعني «لا تصفية» وتُستعمل في الفحصِ
       الذاتيِّ حيث تُمرَّر مجموعةٌ صريحةٌ تُختبر بها التصفيةُ نفسُها.) */
    if (is_array($known)) {
        foreach (array_keys($out['w']) as $t) { if (!isset($known[$t])) { unset($out['w'][$t]); } }
        foreach (array_keys($out['r']) as $t) { if (!isset($known[$t])) { unset($out['r'][$t]); } }
    } else {
        foreach (array('dual','select','where','set','values','table','case','and','the') as $junk) {
            unset($out['w'][$junk], $out['r'][$junk]);
        }
    }
    return $out;
}

/* ═══ ② اشتمالاتُ ملفٍّ — قفزةٌ واحدةٌ محلولةٌ بالمسار ═══════════════════ */
function grain_includes($rp, $ROOT, $CHROME)
{
    $out = array();
    $src = (string) @file_get_contents($rp);
    if ($src === '') { return $out; }
    if (preg_match_all('~(?:include|require)(?:_once)?\s*\(?\s*[^;]*?[\'"]([^\'"]+\.php)[\'"]~', $src, $m)) {
        foreach ($m[1] as $inc) {
            if (in_array(basename($inc), $CHROME, true)) { continue; }
            $cand = array(dirname($rp) . '/' . $inc, $ROOT . '/' . ltrim($inc, './'),
                          $ROOT . '/includes/' . basename($inc));
            foreach ($cand as $cp) {
                if (is_file($cp)) { $rc = realpath($cp); if ($rc) { $out[$rc] = 1; } break; }
            }
        }
    }
    return array_keys($out);
}

/* جمعُ المصدرِ عبرَ الاشتمالِ حتى ثلاثِ قفزات — **مع إقصاءِ المشتركِ**
   ⛔ **والعلّةُ مقيسةٌ لا مُقدَّرة**: `includes/u13_screen_kit.php` عُدّةٌ
   يشتملها أحدَ عشرَ سطحًا، فنُسبت جداولُها الأربعةُ لكلِّ واحدٍ منها
   **فظهرت أحدَ عشرَ خرقًا كاذبًا بالجداولِ الأربعةِ نفسِها**. والحبّةُ ما
   يملكه السطحُ وحدَه ⇒ **ملفٌّ يبلغه سطحان فأكثرُ عُدّةٌ مشتركةٌ لا حبّة**،
   والعتبةُ ليست رقمًا مختارًا بل حدُّ التفرُّدِ نفسُه. */
function grain_source($file, $ROOT, $CHROME, $depth, &$seen, &$files, $shared)
{
    if ($depth > 3) { return ''; }
    $rp = realpath($file);
    if (!$rp || isset($seen[$rp])) { return ''; }
    if ($depth > 0 && isset($shared[$rp])) { return ''; }
    $seen[$rp] = 1;
    $src = (string) @file_get_contents($rp);
    if ($src === '') { return ''; }
    $files[] = basename($rp);
    $acc = $src;
    foreach (grain_includes($rp, $ROOT, $CHROME) as $cp) {
        $acc .= "\n" . grain_source($cp, $ROOT, $CHROME, $depth + 1, $seen, $files, $shared);
    }
    return $acc;
}

/* ═══ ③ بنوّةُ جدولٍ لآخر — أمٌّ وبنودُها حبّةٌ واحدةٌ لا حبّتان ═════════
     ابنٌ بأحدِ وجهين: **لاحقةُ بنودٍ على جِذعِ الأمّ** (`x` ⇐ `x_lines`)،
     **أو اسمُ الأمِّ بادئةٌ للابنِ عند حدِّ شَرطة** (`timesheet` ⇐
     `timesheet_failure_hours`) — وهي عائلةُ حبّةٍ واحدةٍ لا حبّتان. */
function grain_is_child($child, $parent)
{
    if ($child === $parent || $parent === '' || $child === '') { return false; }
    /* والبادئةُ تُقاس على الأمِّ جمعًا ومفردًا: `tickets` أمٌّ لـ`ticket_watchers`
       و`persons` أمٌّ لـ`person_relationships` — والجمعُ لا يقطع البنوّة. */
    if (strpos($child, $parent . '_') === 0) { return true; }
    $sing = rtrim($parent, 's');
    if ($sing !== '' && $sing !== $parent && strpos($child, $sing . '_') === 0) { return true; }
    if (!preg_match('~^(.+?)(_lines|_items|_details|_rows|_entries|_line|_item)$~', $child, $m)) { return false; }
    $stem = $m[1];
    return $stem === $parent || $stem === rtrim($parent, 's') || $parent === rtrim($stem, 's');
}
function grain_looks_line($t)
{
    return (bool) preg_match('~(_lines|_items|_details|_rows|_entries|_line|_item)$~', $t);
}
/* **كتلةُ التدقيقِ إلحاقيّةٌ لا حبّة** — §٧ الخطوة ١١: *«المُنشئ والوقت
   والحالة والمرجع — **إلحاقية**»*. فجدولُ أثرٍ أو سجلِّ حالةٍ ليس حبّةً ثانيةً
   للسطح، وعدُّه حبّةً يُنتج خرقًا كاذبًا في «لا سطحَ يجمع حبّتين». */
function grain_is_trail($t)
{
    return (bool) preg_match('~(_history|_audit|_log|_logs|_trail|_actions|_events|_denials|_notifications|_sequences)$~', $t);
}

/* ═══ ④ الاختبارُ السالبُ — بمفردةٍ فريدةٍ تكسر التمييزَ وحدَه ═══════════ */
if ($SELF) {
    $fail = 0;
    $probe = array(
        array('INSERT INTO proc_purchase_orders (a) VALUES (1)', 'w', 'proc_purchase_orders'),
        array('SELECT * FROM equipments_fleet e JOIN fleet_models m ON 1', 'r', 'equipments_fleet'),
        array('UPDATE `acc_journal_lines` SET x=1', 'w', 'acc_journal_lines'),
    );
    foreach ($probe as $p) {
        $g = grain_tables($p[0]);
        if (!isset($g[$p[1]][$p[2]])) { echo "  X لم يُنتزَع {$p[2]}\n"; $fail++; }
    }
    if (!grain_is_child('acc_invoices_lines', 'acc_invoices')) { echo "  X البنوّةُ لم تُقرأ\n"; $fail++; }
    if (grain_is_child('acc_invoices', 'proc_orders')) { echo "  X بنوّةٌ كاذبةٌ أُقرَّت\n"; $fail++; }
    if (!grain_is_child('ticket_watchers', 'tickets')) { echo "  X الجمعُ قطع البنوّةَ\n"; $fail++; }
    if (grain_is_child('entity_roles', 'legal_entities')) { echo "  X بنوّةٌ كاذبةٌ بالجمع\n"; $fail++; }
    /* **الكاسرُ**: مفردةٌ لا ترد إلّا هنا — فلو مرَّ الفحصُ بها كان أخضرَ كاذبًا */
    $g = grain_tables('SELECT 1 FROM zzq_unique_probe_tbl');
    if (!isset($g['r']['zzq_unique_probe_tbl'])) { echo "  X المفردةُ الفريدةُ لم تُرصَد\n"; $fail++; }
    $g = grain_tables('SELECT 1 FROM dual WHERE 1');
    if (isset($g['r']['dual'])) { echo "  X `dual` عُدَّ جدولًا\n"; $fail++; }
    /* **التصفيةُ بالمخطَّط تُختبر بطرفَيها** — تُبقي الموجودَ وتُسقط غيرَه.
       ⛔ ولو اختُبر طرفٌ واحدٌ لمرَّ «مصفاةٌ تُسقط كلَّ شيء» أخضرَ كاذبًا. */
    $known = array('zzq_unique_probe_tbl' => 1);
    $g = grain_tables('SELECT 1 FROM zzq_unique_probe_tbl JOIN zzq_absent_tbl ON 1', $known);
    if (!isset($g['r']['zzq_unique_probe_tbl'])) { echo "  X التصفيةُ أسقطت الموجودَ\n"; $fail++; }
    if (isset($g['r']['zzq_absent_tbl'])) { echo "  X التصفيةُ أبقت غيرَ الموجود\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n" : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والانتزاعُ والبنوّةُ يميّزان\n";
    exit($fail ? 1 : 0);
}

/* ═══ ⑤ نافذةُ القياس ═══════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ⑥ سجلُّ `cmp03` المُصرَّح ═══════════════════════════════════════════ */
$CMP = array();
if (is_file($ROOT . '/includes/cmp03_registry.php')) {
    require_once $ROOT . '/includes/cmp03_registry.php';
    if (function_exists('cmp03_registry')) { $CMP = cmp03_registry(); }
}

/* ═══ ⑥·ب المخطَّطُ الحيُّ — مِصفاةُ المرشَّحين ═══════════════════════════ */
$KNOWN = array();
$r = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
while ($x = $r->fetch_row()) { $KNOWN[strtolower($x[0])] = 1; }
printf("  ◆ جداولُ المخطَّطِ الحيِّ: %d — والمرشَّحُ خارجَها ليس جدولًا\n", count($KNOWN));
if (count($KNOWN) < 50) { exit("⛔ المخطَّطُ يبدو فارغًا — لا تُقاس حبّةٌ على مِصفاةٍ عمياء\n"); }

/* ═══ ⑦ القياس ═══════════════════════════════════════════════════════════ */
$rows = array();
$r = $conn->query("SELECT screen_id, screen_file, route, canonical_label_ar, surface_kind, owner_code
                     FROM repair01_screen_registry
                    WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
                    ORDER BY screen_id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }

/* ═══ ⑦·أ حلُّ المسارِ لكلِّ سطح — مرّةً واحدةً ═══════════════════════════ */
$PATH = array();
foreach ($rows as $s) {
    $path = '';
    if (trim((string) $s['route']) !== '') {
        $p = $ROOT . '/' . ltrim(str_replace('\\', '/', $s['route']), '/');
        if (is_file($p)) { $path = $p; }
    }
    if ($path === '') {
        $h = glob($ROOT . '/*/' . basename($s['screen_file']));
        if (!$h) { $h = glob($ROOT . '/' . basename($s['screen_file'])); }
        if ($h) { $path = $h[0]; }
    }
    $PATH[$s['screen_id']] = $path;
}

/* ═══ ⑦·ب المرورُ الأوّل — **مَن يبلغه سطحان فأكثرُ عُدّةٌ مشتركة** ═══════
     يُحسب مدى الاشتمالِ حتى ثلاثِ قفزاتٍ لكلِّ سطحٍ على حدة، ثمَّ يُعدُّ
     **عددُ الأسطحِ** التي تبلغ كلَّ ملفّ. وما بلغه أكثرُ من واحدٍ يخرج من
     الحبّةِ في المرورِ الثاني. ⛔ ولا يخرج ملفُّ السطحِ نفسُه أبدًا. */
$fanin = array(); $ownFile = array();
foreach ($rows as $s) {
    $path = $PATH[$s['screen_id']];
    if ($path === '') { continue; }
    $rp = realpath($path); if (!$rp) { continue; }
    $ownFile[$rp] = 1;
    $reach = array(); $q = array(array($rp, 0)); $vis = array($rp => 1);
    while ($q) {
        list($cur, $d) = array_shift($q);
        if ($d >= 3) { continue; }
        foreach (grain_includes($cur, $ROOT, $CHROME) as $nx) {
            if (isset($vis[$nx])) { continue; }
            $vis[$nx] = 1; $reach[$nx] = 1; $q[] = array($nx, $d + 1);
        }
    }
    foreach (array_keys($reach) as $f) { $fanin[$f] = isset($fanin[$f]) ? $fanin[$f] + 1 : 1; }
}
$SHARED = array();
foreach ($fanin as $f => $n) { if ($n >= 2 && !isset($ownFile[$f])) { $SHARED[$f] = $n; } }
printf("  ◆ عُدَدٌ مشتركةٌ أُقصيت من الحبّة: %d ملفًّا (يبلغها سطحان فأكثر)\n", count($SHARED));

$stat = array('G1'=>0,'G1B'=>0,'G2'=>0,'G3'=>0,'G4'=>0,'G5'=>0,'G6'=>0,'NONE_PATH'=>0,'NONE_TBL'=>0);
$card = array('ROW'=>0,'LINE'=>0,'LIVE_READ'=>0,'LIST'=>0,'NONE'=>0);
$multi = array(); $unres = array(); $noTbl = array(); $upd = 0;

$AR = array('ROW' => 'سجلٌّ واحدٌ في ', 'LINE' => 'بندٌ واحدٌ في ',
            'LIVE_READ' => 'قراءةٌ حيّةٌ من ', 'LIST' => 'قائمةُ قراءةٍ من ', 'NONE' => '');

foreach ($rows as $s) {
    $path = $PATH[$s['screen_id']];
    $ent = ''; $cd = 'NONE'; $rule = ''; $wit = ''; $mult = 0;

    if ($path === '') {
        $stat['NONE_PATH']++; $unres[] = $s['screen_id'] . ' · ' . $s['screen_file'];
        $rule = 'G0_NO_PATH';
        $wit  = 'تعذّر حلُّ المسار — لا مسارَ مسجَّلًا ولا ملفًّا بالاسم: ' . $s['screen_file'];
    } else {
        $base = basename($path);
        /* **مصدران لا مصدر** — والفرقُ بينهما هو الدرسُ المقيس:
           `$srcOwn` ما يملكه السطحُ وحدَه (ملفُّه واشتمالاتُه غيرُ المشتركة)،
           و`$srcAll` مع العُدَدِ المشتركة. **الكيانُ** يُؤخذ من الخاصِّ فإن خلا
           فمن المشترك (ويُسمّى الطبقةُ في الشاهد)، **وخرقُ «حبّتين» لا يُحكَم
           إلّا على الخاصّ** — لأنَّ نسبةَ جداولِ عُدّةٍ يشتملها أحدَ عشرَ سطحًا
           إلى كلِّ واحدٍ منها أنتجت ٢٦٥ خرقًا كاذبًا قبل هذا الفصل. */
        $seen = array(); $files = array();
        $srcOwn = grain_source($path, $ROOT, $CHROME, 0, $seen, $files, $SHARED);
        $seen2 = array(); $files2 = array(); $none = array();
        $srcAll = grain_source($path, $ROOT, $CHROME, 0, $seen2, $files2, $none);
        $gOwn = grain_tables($srcOwn, $KNOWN);
        $gAll = grain_tables($srcAll, $KNOWN);
        $g    = $gOwn;
        $tier = 'OWN';
        if (!$gOwn['w'] && !$gOwn['r'] && ($gAll['w'] || $gAll['r'])) { $g = $gAll; $tier = 'SHARED_KIT'; }
        $src  = ($tier === 'OWN') ? $srcOwn : $srcAll;

        /* **G1b · بيانُ السطحِ عن نفسِه** — العائلاتُ المولَّدةُ (`u13` وغيرُها)
           تُصيَّر بعُدّةٍ مشتركةٍ **وتُعلن جدولَها في ملفِّها**:
           `$U13 = array( … 'table' => 'iaf_access_log', 'nature' => 'read' … )`.
           ⛔ **وبلا هذه القاعدةِ يُقصي إقصاءُ العُدّةِ المشتركةِ الحبّةَ معها**
           — فتظهر ٢٤٤ سطحًا «بلا جدول» وهي مُعلِنةٌ جدولَها في سطرٍ واحد. */
        $own = (string) @file_get_contents($path);
        $dTbl = ''; $dNat = '';
        if (preg_match('~[\'"]table[\'"]\s*=>\s*[\'"]([a-z][a-z0-9_]{2,})[\'"]~i', $own, $dm)) { $dTbl = strtolower($dm[1]); }
        if (preg_match('~[\'"]nature[\'"]\s*=>\s*[\'"]([a-z]+)[\'"]~i', $own, $nm)) { $dNat = strtolower($nm[1]); }

        if (isset($CMP[$base]['table']) && $CMP[$base]['table'] !== '') {
            $ent  = $CMP[$base]['table'];
            $rule = 'G1_CMP03_DECLARED';
            $wit  = 'مُصرَّحٌ في includes/cmp03_registry.php ← «' . $base . '» ⇐ ' . $ent;
            $cd   = grain_looks_line($ent) ? 'LINE' : 'ROW';
            $stat['G1']++;
        } elseif ($dTbl !== '') {
            $ent  = $dTbl;
            $rule = 'G1B_SELF_DECLARED';
            $wit  = 'يُعلن السطحُ جدولَه في ملفِّه: table => ' . $ent
                  . ($dNat !== '' ? ' · nature => ' . $dNat : '') . ' · مصدر ' . $base . ' [' . $tier . ']';
            $cd   = grain_looks_line($ent) ? 'LINE'
                  : (($dNat === 'read') ? 'LIST' : 'ROW');
            $stat['G1B'] = isset($stat['G1B']) ? $stat['G1B'] + 1 : 1;
        } else {
            $biz = array(); $inf = array();
            foreach ($g['w'] as $t => $n) { if (in_array($t, $INFRA, true)) { $inf[$t] = $n; } else { $biz[$t] = $n; } }
            $wBiz = $biz; $wInf = $inf;
            $biz = array(); $inf = array();
            foreach ($g['r'] as $t => $n) { if (in_array($t, $INFRA, true)) { $inf[$t] = $n; } else { $biz[$t] = $n; } }
            $rBiz = $biz; $rInf = $inf;

            $infraOnly = false;
            $W = $wBiz; if (!$W) { $W = $wInf; $infraOnly = (bool) $wInf; }
            $R = $rBiz; if (!$R) { $R = $rInf; $infraOnly = $infraOnly || (bool) $rInf; }

            if ($W) {
                arsort($W); $keys = array_keys($W); $ent = $keys[0];
                /* **الأثرُ إلحاقيٌّ فيخرج من عدِّ الحبّات** — §٧ الخطوة ١١ */
                $core = array();
                foreach ($keys as $t) { if (!grain_is_trail($t)) { $core[] = $t; } }
                if (!$core) { $core = $keys; }
                $distinct = array();
                foreach ($core as $t) {
                    $isChild = false;
                    foreach ($distinct as $d) { if (grain_is_child($t, $d) || grain_is_child($d, $t)) { $isChild = true; break; } }
                    if (!$isChild) { $distinct[] = $t; }
                }
                /* الأمُّ لا البنود — §٧ الخطوة ٢ */
                $ent = $distinct[0];
                foreach ($distinct as $t) { if (!grain_looks_line($t)) { $ent = $t; break; } }
                $cd = grain_looks_line($ent) ? 'LINE' : 'ROW';
                /* ⛔ **الخرقُ لا يُحكَم على عُدّةٍ مشتركة** — فما جاء من كِيتٍ
                   يشتملُه غيرُه لا يُنسب إلى هذا السطحِ خرقًا. */
                if (count($distinct) >= 2 && $tier === 'OWN') {
                    $mult = 1; $rule = 'G4_TWO_GRAINS';
                    $multi[] = $s['screen_id'] . ' · ' . $base . ' ⇐ ' . implode(' + ', array_slice($distinct, 0, 4));
                    $stat['G4']++;
                } elseif (count($keys) >= 2) {
                    $rule = 'G3_PARENT_AND_LINES'; $stat['G3']++;
                } else {
                    $rule = 'G2_SINGLE_WRITE'; $stat['G2']++;
                }
                $parts = array();
                foreach (array_slice($keys, 0, 5) as $k) { $parts[] = $k . '×' . $W[$k]; }
                $wit = 'كتابةٌ على ' . implode(' · ', $parts) . ($infraOnly ? ' — INFRA_ONLY' : '') . ' · مصدر ' . $base . ' [' . $tier . ']';
            } elseif ($R) {
                arsort($R); $keys = array_keys($R); $ent = $keys[0];
                $agg = preg_match('~\bGROUP\s+BY\b|\bSUM\s*\(|\bCOUNT\s*\(|\bAVG\s*\(~i', $src);
                $cd  = $agg ? 'LIVE_READ' : 'LIST';
                $rule = $agg ? 'G5_READ_AGGREGATE' : 'G6_READ_PLAIN';
                $stat[$agg ? 'G5' : 'G6']++;
                $parts = array();
                foreach (array_slice($keys, 0, 4) as $k) { $parts[] = $k . '×' . $R[$k]; }
                $wit = 'قراءةٌ من ' . implode(' · ', $parts) . ($infraOnly ? ' — INFRA_ONLY' : '') . ' · مصدر ' . $base . ' [' . $tier . ']';
            } else {
                $stat['NONE_TBL']++; $noTbl[] = $s['screen_id'] . ' · ' . $base;
                $rule = 'G0_NO_TABLE';
                $wit  = 'لا جدولَ في ' . $base . ' ولا في اشتمالاتِه (' . count($files) . ' ملفًّا)';
            }
        }
    }

    $meas = ($ent === '') ? '' : $AR[$cd] . $ent;
    $card[$cd]++;

    if ($APPLY) {
        $conn->query("UPDATE repair01_screen_registry SET
            grain_entity      = '" . $e($ent) . "',
            grain_cardinality = '" . $e($cd) . "',
            grain_measured    = '" . $e($meas) . "',
            grain_rule        = '" . $e($rule) . "',
            grain_witness     = '" . $e(mb_substr($wit . ' · لقطة ' . $sid, 0, 395)) . "',
            grain_multi       = " . (int) $mult . "
          WHERE screen_id = '" . $e($s['screen_id']) . "'");
        $upd++;
    }
}

/* ═══ ⑧ العرض ═══════════════════════════════════════════════════════════ */
$tot = count($rows);
printf("\n── حبّةُ المبنيِّ مقيسةً · %d سطحًا حيًّا · لقطة %s ──\n", $tot, $sid);
echo "\n  القواعدُ الستُّ:\n";
foreach ($stat as $k => $v) { printf("    %-12s %4d\n", $k, $v); }
echo "\n  أصنافُ الحبّة:\n";
foreach ($card as $k => $v) { printf("    %-12s %4d\n", $k, $v); }
$have = $tot - $card['NONE'];
printf("\n  ◆ بحبّةٍ مقيسة: **%d من %d** (%.1f%%) · بلا حبّة: %d\n", $have, $tot, $tot ? $have * 100 / $tot : 0, $card['NONE']);
printf("  ◆ **سطحٌ يجمع حبّتين**: %d — خرقُ §٧ الخطوة ١ مقيسًا\n", count($multi));
foreach (array_slice($multi, 0, 12) as $m) { echo "      · $m\n"; }
if (count($multi) > 12) { echo '      · … و' . (count($multi) - 12) . " غيرُها\n"; }
if ($unres) { echo "\n  ! تعذّر حلُّ المسار (" . count($unres) . "): " . implode(' · ', array_slice($unres, 0, 5)) . "\n"; }
if ($noTbl) { echo "  ! بلا جدولٍ (" . count($noTbl) . "): " . implode(' · ', array_slice($noTbl, 0, 6)) . "\n"; }
echo $APPLY ? "\n✔ كُتب على $upd صفًّا\n" : "\n◆ عرضٌ فقط — `--apply` يكتب\n";

if ($MD) {
    $o  = "# `RPR-02` §٧·١ — حبّةُ المبنيِّ مقيسةً\n\n";
    $o .= "> اللقطة `$sid` · الأسطحُ الحيّةُ **$tot**\n\n";
    $o .= "| الصنف | العدد |\n|---|---:|\n";
    foreach ($card as $k => $v) { $o .= "| `$k` | $v |\n"; }
    $o .= "\n| القاعدة | العدد |\n|---|---:|\n";
    foreach ($stat as $k => $v) { $o .= "| `$k` | $v |\n"; }
    $o .= "\n**بحبّةٍ مقيسة $have من $tot** · **سطحٌ يجمع حبّتين " . count($multi) . "**\n";
    $p = $ROOT . '/docs/REPAIR01_20260823/RPR02_S7_GRAIN.md';
    file_put_contents($p, $o);
    echo "  ✔ كُتب docs/REPAIR01_20260823/RPR02_S7_GRAIN.md\n";
}
