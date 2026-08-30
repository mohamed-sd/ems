<?php
/**
 * tools/rpr02_cross_contract.php — `RPR-02` §٥·٩ · عقدُ الكتابةِ العابرةِ للنطاق
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٥·٩ قبلَ إغلاقِ `RPR-02`: `Cross Domain Direct Write
 *   = 0`. ونصُّ المقياسِ **#١١** في اللوحة يقول عن نفسِه: *«⛔ **والعقدُ غيرُ
 *   مقيسٍ بعدُ**، فالرقمُ **سقفُ الخرقِ لا الخرقُ**: منه ما له عقدٌ مُعلَنٌ لم
 *   يُسجَّل»*. ⇒ **فهذا المقياسُ يقيس العقدَ**، ويحوّل السقفَ إلى خرقٍ مقيس.
 *
 * ◆ **والعقدُ ليس وثيقةً تُكتب بل مسارُ كتابةٍ يُقاس**: الدستورُ يحكم أنَّ
 *   الكتابةَ العابرةَ تمرُّ **بخدمةِ المجالِ المالكةِ أو بناشرِ الأحداث**
 *   (`ADR-15` · `ems_business_events` · `EventPublisher`) لا بجملةِ `SQL`
 *   خامٍّ في شاشةِ إدارةٍ أخرى. ⇒ **فالعقدُ مقيسٌ من الأثرِ لا مأخوذٌ بالدعوى**.
 *
 * ◆ **ثلاثةُ أحكامٍ لكلِّ كاتب** — ⛔ ولا رابعَ يُخترع:
 *   **X0 · `NO_MEASURED_WRITE`** — الكيانُ نُسب إليه **بتصريحِه في ملفِّه**
 *        (`G1`/`G1B`) **ولا جملةَ كتابةٍ واحدةً عليه في مداه الخاصّ** ⇒
 *        **ليس كاتبًا أصلًا**، ولا يُعدُّ طرفًا في عبورٍ ولا في ازدواجِ مصدر.
 *   **X1 · `SERVICE_MEDIATED`** — صفرُ كتابةٍ خامّةٍ **وخدمةٌ أو ناشرٌ مسمًّى**
 *        ⇒ **عقدٌ مقيسٌ في الكود**: الكتابةُ تمرُّ ببابٍ واحد.
 *   **X2 · `RAW_DIRECT`** — جملةُ كتابةٍ خامّةٌ على الكيانِ في مدى السطحِ الخاصّ
 *        ⇒ **كتابةٌ مباشرة**، وهي محلُّ §٥·٩.
 *
 * ⛔ **والمدى هو مدى قياسِ الحبّةِ نفسِه** — ملفُّ السطحِ واشتمالاتُه **غيرُ
 *   المشتركة** حتى ثلاثِ قفزات. فقراءةُ الملفِّ وحدَه **تُنتج `X0` كاذبةً**
 *   لسطحٍ يكتب في اشتمالٍ خاصٍّ به، **وقد قِستُها أوّلًا فأخطأت**.
 *
 * ⛔ **ولا يُسمّى عقدًا ما لم يُقَسْ**: خدمةٌ مستدعاةٌ مع كتابةٍ خامّةٍ معها
 *   **ليست عقدًا** — فالبابُ المفتوحُ بجانبِ البابِ المحروسِ يُبطل الحراسة.
 *
 * التشغيل:
 *   php tools/rpr02_cross_contract.php [--apply] [--md] [--selftest]
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

$CHROME = array('config.php','session_bootstrap.php','inheader.php','insidebar.php',
                'page_header.php','permissions_helper.php','gov_columns.php',
                'screen_contract.php','env.php','db.php','footer.php','infooter.php',
                'csrf.php','csrf_helper.php','auth.php','ems_action_guard.php');

/* ═══ ① القياسان مفصولان — كي يُختبرا وحدَهما ════════════════════════════ */
/* ⛔ **والتعليقُ ليس كتابةً** — وقد كان يُعَدُّ: ثلاثةُ أسطحٍ تحمل في **شرحِ
   إصلاحِها** عبارةَ `INSERT INTO exec_approvals` تحكي ما **كان** قبلَ الإصلاح،
   فعُدَّت جملةَ كتابةٍ قائمة. ⇒ **والشاهدُ الذي يصف الماضيَ يُقرأ حاضرًا** ما لم
   يُنزَع التعليقُ قبلَ العدّ. */
function cc_strip_comments($src)
{
    $out = ''; $n = strlen($src); $i = 0; $st = 0; $q = '';
    while ($i < $n) {
        $c = $src[$i]; $d = ($i + 1 < $n) ? $src[$i + 1] : '';
        if ($st === 0) {
            if ($c === '/' && $d === '*') { $st = 1; $i += 2; continue; }
            if (($c === '/' && $d === '/') || $c === '#') { $st = 2; $i += ($c === '#') ? 1 : 2; continue; }
            if ($c === '"' || $c === "'") { $st = 3; $q = $c; $out .= $c; $i++; continue; }
            $out .= $c; $i++; continue;
        }
        if ($st === 1) { if ($c === '*' && $d === '/') { $st = 0; $i += 2; $out .= ' '; continue; } $i++; continue; }
        if ($st === 2) { if ($c === "\n") { $st = 0; $out .= "\n"; } $i++; continue; }
        if ($c === chr(92)) { $out .= $c . $d; $i += 2; continue; }
        $out .= $c;
        if ($c === $q) { $st = 0; }
        $i++;
    }
    return $out;
}
/* ⛔ **والبابُ ليس الغرفةَ** — وهذا أثقلُ عطبٍ كان في هذا المقياس: مدى السطحِ
   «الخاصُّ» يشمل اشتمالاتِه غيرَ المشتركة، **وخدمةُ المجالِ التي لا يستعملها
   إلّا سطحٌ واحدٌ تسقط داخلَه** (‏تفرُّعُها = ١ فلا تُعَدُّ مشتركة). ⇒ فجملةُ
   الكتابةِ **داخلَ الخدمةِ نفسِها** تُقرأ «كتابةً خامّةً للسطح»، **والحكمُ يصير
   دالّةً على عددِ مستهلكي الخدمةِ لا على مسارِ الكتابة**: الخدمةُ الواحدةُ تعطي
   `X2` لمستهلكِها الأوّلِ و`X1` للثاني بلا أن يتغيّر سطرٌ فيها. ⇒ **وطبقةُ
   الخدمةِ المُعلَنةُ في الدستور** (`app/Services/**` · `app/Core/**` — `ADR-15`)
   **هي البابُ بتعريفِها**، فتُستثنى من «الغرفة» ويُعَدُّ الخامُّ في كودِ السطحِ
   وحدَه — **والبابُ يُسمّى ويُعدُّ بابًا لا خرقًا**. */
function cc_is_door($path)
{
    $u = strtolower(str_replace(chr(92), '/', (string) $path));
    return (strpos($u, '/app/services/') !== false) || (strpos($u, '/app/core/') !== false);
}
function cc_raw_writes($src, $entity)
{
    $src = cc_strip_comments($src);
    return (int) preg_match_all('~\b(?:INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+`?'
        . preg_quote($entity, '~') . '`?\b~i', $src);
}
/* **بابُ العقدِ**: ناشرُ الأحداثِ أو خدمةُ مجالٍ مسمّاةٌ بالاسم */
function cc_gates($src)
{
    $out = array();
    if (preg_match('~EventPublisher|publishEvent|ems_business_events~i', $src)) { $out[] = 'EventPublisher'; }
    if (preg_match_all('~\b([A-Z][A-Za-z0-9]*(?:Service|Router|Engine|Publisher))\b~', $src, $m)) {
        foreach (array_unique($m[1]) as $x) { $out[] = $x; }
    }
    return array_values(array_unique($out));
}
function cc_includes($rp, $ROOT, $CHROME)
{
    $out = array();
    $src = (string) @file_get_contents($rp);
    if ($src === '') { return $out; }
    if (preg_match_all('~(?:include|require)(?:_once)?\s*\(?\s*[^;]*?[\x22\x27]([^\x22\x27]+\.php)[\x22\x27]~', $src, $m)) {
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

/* ═══ ② الاختبارُ السالبُ — يُصيب الطرفَين ولا يمرُّ بمفردةٍ فريدة ═══════ */
if ($SELF) {
    $fail = 0;
    if (cc_raw_writes("INSERT INTO exec_approvals (a) VALUES (1)", 'exec_approvals') !== 1) {
        echo "  X الكتابةُ الخامّةُ لم تُرصَد\n"; $fail++;
    }
    if (cc_raw_writes("SELECT * FROM exec_approvals", 'exec_approvals') !== 0) {
        echo "  X القراءةُ عُدَّت كتابةً\n"; $fail++;
    }
    /* ⛔ **والاسمُ الجزئيُّ ليس الكيانَ**: `exec_approvals_log` ليس `exec_approvals` */
    if (cc_raw_writes("UPDATE exec_approvals_log SET x=1", 'exec_approvals') !== 0) {
        echo "  X الاسمُ الجزئيُّ عُدَّ الكيانَ\n"; $fail++;
    }
    if (!in_array('EventPublisher', cc_gates('$p = new EventPublisher();'), true)) {
        echo "  X الناشرُ لم يُرصَد\n"; $fail++;
    }
    if (!in_array('TicketRouter', cc_gates('TicketRouter::route($x);'), true)) {
        echo "  X الموجِّهُ لم يُرصَد\n"; $fail++;
    }
    /* **الكاسر**: مفردةٌ فريدةٌ لا ترد إلّا هنا */
    if (cc_raw_writes('SELECT 1', 'zzq_unique_probe_entity') !== 0) {
        echo "  X المفردةُ الفريدةُ رُصدت كتابةً\n"; $fail++;
    }
    if (cc_gates('$x = 1;')) { echo "  X بابٌ رُصد في نصٍّ بلا خدمة\n"; $fail++; }
    /* ⛔ **والتعليقُ ليس كتابةً** — والاختبارُ يُصيب الطرفَين */
    if (cc_raw_writes("// كان INSERT INTO zzq_cmt_probe قبلَ الإصلاح\n", 'zzq_cmt_probe') !== 0) {
        echo "  X تعليقُ سطرٍ عُدَّ كتابةً\n"; $fail++;
    }
    if (cc_raw_writes("/* INSERT INTO zzq_cmt_probe */", 'zzq_cmt_probe') !== 0) {
        echo "  X تعليقُ كتلةٍ عُدَّ كتابةً\n"; $fail++;
    }
    if (cc_raw_writes("# INSERT INTO zzq_cmt_probe\n", 'zzq_cmt_probe') !== 0) {
        echo "  X تعليقُ المربّعِ عُدَّ كتابةً\n"; $fail++;
    }
    /* **الكاسر**: جملةٌ حيّةٌ داخلَ سلسلةٍ فيها شرطةُ قسمةٍ لا تُنزَع */
    if (cc_raw_writes('$q = "INSERT INTO zzq_cmt_probe (a) VALUES (1)";', 'zzq_cmt_probe') !== 1) {
        echo "  X الجملةُ الحيّةُ في سلسلةٍ نُزعت مع التعليق\n"; $fail++;
    }
    if (cc_raw_writes("\$q = 'a//b'; UPDATE zzq_cmt_probe SET x=1;", 'zzq_cmt_probe') !== 1) {
        echo "  X شرطتا قسمةٍ داخلَ سلسلةٍ فتحتا تعليقًا\n"; $fail++;
    }
    /* ⛔ **والبابُ ليس الغرفةَ** */
    if (!cc_is_door('C:/wamp64/www/ems/app/Services/Tickets/TicketRouter.php')) {
        echo "  X طبقةُ الخدمةِ لم تُعرَف بابًا\n"; $fail++;
    }
    if (!cc_is_door('C:' . chr(92) . 'wamp64' . chr(92) . 'www' . chr(92) . 'ems' . chr(92) . 'app' . chr(92) . 'Core' . chr(92) . 'EventPublisher.php')) {
        echo "  X مسارُ ويندوزَ للبابِ لم يُعرَف\n"; $fail++;
    }
    if (cc_is_door('C:/wamp64/www/ems/Tickets/admin_close.php')) {
        echo "  X سطحٌ عاديٌّ عُدَّ بابًا\n"; $fail++;
    }
    if (cc_is_door('C:/wamp64/www/ems/tools/zzq_unique_probe_service.php')) {
        echo "  X ملفٌّ خارجَ الطبقةِ عُدَّ بابًا باسمِه\n"; $fail++;
    }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والكتابةُ تُميَّز عن القراءةِ والاسمُ الجزئيُّ لا يمرّ\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③ نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ④ المدى الخاصّ — كما يحسبه قياسُ الحبّةِ حرفًا ════════════════════ */
$reg = array();
$r = $conn->query("SELECT screen_id, screen_file, route, canonical_label_ar, owner_code, grain_entity, grain_rule
                     FROM repair01_screen_registry
                    WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'");
while ($x = $r->fetch_assoc()) { $reg[$x['screen_id']] = $x; }
$PATH = array();
foreach ($reg as $k => $s) {
    $p = '';
    if (trim((string) $s['route']) !== '') {
        $q = $ROOT . '/' . ltrim(strtr((string) $s['route'], array(chr(92) => '/')), '/');
        if (is_file($q)) { $p = $q; }
    }
    if ($p === '') {
        $h = glob($ROOT . '/*/' . basename($s['screen_file']));
        if (!$h) { $h = glob($ROOT . '/' . basename($s['screen_file'])); }
        if ($h) { $p = $h[0]; }
    }
    $PATH[$k] = $p;
}
$fanin = array(); $own = array();
foreach ($PATH as $k => $p) {
    if ($p === '') { continue; }
    $rp = realpath($p); if (!$rp) { continue; }
    $own[$rp] = 1;
    $q = array(array($rp, 0)); $vis = array($rp => 1); $reach = array();
    while ($q) {
        list($cur, $d) = array_shift($q);
        if ($d >= 3) { continue; }
        foreach (cc_includes($cur, $ROOT, $CHROME) as $nx) {
            if (isset($vis[$nx])) { continue; }
            $vis[$nx] = 1; $reach[$nx] = 1; $q[] = array($nx, $d + 1);
        }
    }
    foreach (array_keys($reach) as $f) { $fanin[$f] = isset($fanin[$f]) ? $fanin[$f] + 1 : 1; }
}
$SHARED = array();
foreach ($fanin as $f => $n) { if ($n >= 2 && !isset($own[$f])) { $SHARED[$f] = $n; } }

/* ⇒ **الغرفةُ والبابُ يعودان مفصولَين**: `room` كودُ السطحِ نفسِه (‏وما يشتمله
   ممّا ليس طبقةَ خدمة) — وفيه يُعَدُّ الخامُّ · و`doors` ملفّاتُ طبقةِ الخدمةِ
   في مداه — ومنها يُقرأ **اسمُ البابِ الذي يكتب الكيان**. */
function cc_own_source($p, $ROOT, $CHROME, $SHARED)
{
    $rp = realpath($p); if (!$rp) { return array('', array(), '', array()); }
    $q = array(array($rp, 0)); $vis = array($rp => 1); $set = array($rp);
    while ($q) {
        list($cur, $d) = array_shift($q);
        if ($d >= 3) { continue; }
        foreach (cc_includes($cur, $ROOT, $CHROME) as $nx) {
            if (isset($vis[$nx]) || isset($SHARED[$nx])) { continue; }
            $vis[$nx] = 1; $set[] = $nx; $q[] = array($nx, $d + 1);
        }
    }
    $acc = ''; $files = array(); $dacc = ''; $dfiles = array();
    foreach ($set as $f) {
        $s = (string) @file_get_contents($f);
        if ($s === '') { continue; }
        if (cc_is_door($f)) { $dacc .= "\n" . $s; $dfiles[] = $f; continue; }
        $acc .= "\n" . $s; $files[] = basename($f);
    }
    return array($acc, $files, $dacc, $dfiles);
}
/* **اسمُ البابِ الكاتب**: ملفُّ طبقةِ خدمةٍ في المدى **يحمل جملةَ كتابةٍ على
   الكيانِ نفسِه** ⇒ اسمُ صنفِه هو البابُ المسمّى. ⛔ **ولا يُسمّى بابًا ما لا
   يكتب**: خدمةٌ في المدى لا تمسُّ الكيانَ ليست بابَه. */
function cc_writing_doors($dfiles, $entity)
{
    $out = array();
    foreach ($dfiles as $f) {
        $s = (string) @file_get_contents($f);
        if ($s === '' || cc_raw_writes($s, $entity) === 0) { continue; }
        $out[preg_replace('~\.php$~i', '', basename($f))] = 1;
    }
    return array_keys($out);
}

/* ═══ ⑤ الكياناتُ غيرُ المحسومةِ في سجلِّ المصدرِ القانونيّ ═══════════════ */
/* ⛔ **والمدى كلُّ كيانٍ مكرَّرٍ لا غيرُ المحسومِ وحدَه** — وكان يقتصر على
   `resolved = 0`، **فصار الشاهدُ يختفي بمجرَّدِ أن يُحسَم الكيانُ به**: يقرأ
   `rpr02_canonical_source` عقدَ الكتابةِ فيَحسِم، فيُسقِط هذا المقياسُ صفَّه،
   فيعود الحسمُ بلا سندٍ في التشغيلةِ التالية — **تذبذبٌ لا نقطةَ ثباتٍ له**.
   ⇒ فيُقاس **كلُّ** مكرَّرٍ ويبقى شاهدُه قائمًا بعدَ الحسم. */
$ents = array();
$r = @$conn->query("SELECT entity, rule_code, owners, writers FROM repair01_canonical_source
                     ORDER BY rule_code, entity");
if (!$r) { exit("⛔ **`repair01_canonical_source` غيرُ موجود** — شغِّلْ `rpr02_canonical_source.php --apply` أوّلًا.\n"); }
while ($x = $r->fetch_assoc()) { $ents[] = $x; }

$rows = array(); $stat = array('X0' => 0, 'X1' => 0, 'X2' => 0);
$byEnt = array();
foreach ($ents as $E) {
    $ent = $E['entity'];
    $q = $conn->query("SELECT screen_id FROM repair01_screen_registry
                        WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
                          AND grain_fact_scope = 'OWN_FACT'
                          AND grain_cardinality IN ('ROW','LINE')
                          AND grain_entity = '" . $e($ent) . "' ORDER BY screen_id");
    while ($w = $q->fetch_row()) {
        $sc = $w[0]; $s = $reg[$sc];
        $p = isset($PATH[$sc]) ? $PATH[$sc] : '';
        if ($p === '') { continue; }
        list($src, $files, $dsrc, $dfiles) = cc_own_source($p, $ROOT, $CHROME, $SHARED);
        $raw   = cc_raw_writes($src, $ent);
        $wdoor = cc_writing_doors($dfiles, $ent);
        $gates = array_values(array_unique(array_merge($wdoor, cc_gates($src))));
        /* ⛔ **ولا يُسمّى وسيطًا مَن ذكر خدمةً لا تكتب الكيان** — كان `X1` يُمنح
           لمجرَّدِ ورودِ اسمٍ ينتهي بـ`Service` في المصدر، **وذاك إعلانٌ لا مسار**.
           ⇒ فالوساطةُ تُشترط ببابٍ **يكتب هذا الكيانَ بعينِه** (`$wdoor`)، ومَن
           لا يكتب ولا يمرُّ ببابٍ كاتبٍ **ليس كاتبًا** (`X0`). */
        if ($raw > 0)        { $v = 'X2_RAW_DIRECT'; $stat['X2']++; }
        elseif ($wdoor)      { $v = 'X1_SERVICE_MEDIATED'; $stat['X1']++; }
        else                 { $v = 'X0_NO_MEASURED_WRITE'; $stat['X0']++; }
        $wit = ($v === 'X2_RAW_DIRECT')
            ? 'X2 · **' . $raw . '** جملةَ كتابةٍ خامّةٍ على `' . $ent . '` في مدى `' . $sc
              . '` الخاصِّ (' . count($files) . ' ملفًّا) — ⛔ **كتابةٌ مباشرةٌ لا عقد**'
              . ($gates ? ' · **وخدمةٌ مستدعاةٌ معها لا تُغنِي** (' . implode(' · ', array_slice($gates, 0, 3))
                          . '): البابُ المفتوحُ بجانبِ المحروسِ يُبطل الحراسة' : '')
              . ' · لقطة ' . $sid
            : (($v === 'X1_SERVICE_MEDIATED')
               ? 'X1 · **صفرُ كتابةٍ خامّةٍ** على `' . $ent . '` في مدى `' . $sc . '` الخاصِّ ('
                 . count($files) . ' ملفًّا) **وبابٌ مسمًّى**: ' . implode(' · ', array_slice($gates, 0, 4))
                 . ' ⇒ **عقدٌ مقيسٌ في الكود** · لقطة ' . $sid
               : 'X0 · **صفرُ كتابةٍ خامّةٍ وصفرُ بابٍ** — والكيانُ نُسب إليه بتصريحِه في ملفِّه (`'
                 . $s['grain_rule'] . '`) ⇒ **ليس كاتبًا أصلًا**، ولا يُعدُّ طرفًا في عبورٍ ولا في '
                 . 'ازدواجِ مصدر · لقطة ' . $sid);
        $rows[] = array($ent, $E['rule_code'], $sc, $s['owner_code'], $s['grain_rule'],
                        $raw, implode(' · ', array_slice($gates, 0, 4)), $v, $wit);
        $byEnt[$ent][] = array('v' => $v, 'own' => $s['owner_code'], 'sc' => $sc,
                               'raw' => $raw, 'door' => $wdoor);
    }
}

/* ═══ ⑥ حكمُ الكيان ═══════════════════════════════════════════════════════ */
$entVerdict = array();
foreach ($byEnt as $ent => $ws) {
    $realWriters = array(); $rawOwners = array();
    foreach ($ws as $w) {
        if ($w['v'] === 'X0_NO_MEASURED_WRITE') { continue; }
        $realWriters[] = $w;
        if ($w['v'] === 'X2_RAW_DIRECT') { $rawOwners[$w['own']] = 1; }
    }
    if (count($realWriters) <= 1) {
        $entVerdict[$ent] = array('NOT_A_DUPLICATE',
            'كتّابُه المقيسون **' . count($realWriters) . '** بعد إسقاطِ `X0` ⇒ **ليس مكرَّرًا أصلًا**');
    } elseif (!$rawOwners) {
        $entVerdict[$ent] = array('CONTRACTED',
            'كلُّ كتّابِه **يمرّون ببابٍ مسمًّى** وصفرُ كتابةٍ خامّة ⇒ **العبورُ بعقدٍ مقيس**');
    } elseif (count($rawOwners) === 1) {
        /* ⛔ **وإدارةٌ واحدةٌ تكتب كيانَها خامًّا ليست «عبورَ حدود»** — وهذا
           المقياسُ اسمُه بنصِّه *«كتابةٌ **تعبر حدودَ إدارةٍ** بلا عقد»*. فحين
           تكون **كلُّ** الكتابةِ الخامّةِ تحت إدارةٍ واحدةٍ وسائرُ الكتّابِ
           يمرّون ببابٍ مسمًّى ⇒ **لا حدَّ عُبِر**: المالكُ يكتب فِعلَه والعابرُ
           متعاقِد. ⛔ **وعدُّه خرقًا يخلط §٥·٩ (‏مصدرٌ واحد) بـ#١١ (‏عبورُ حدٍّ)**
           — وذاك بعينُه ازدواجُ العدِّ الذي يحذّر منه الأمر. ◆ **وازدواجُ المصدرِ
           داخلَ الإدارةِ الواحدةِ يبقى مفتوحًا في #١٠ ولا يُطوى هنا.** */
        $ro = array_keys($rawOwners);
        $entVerdict[$ent] = array('CONTRACTED',
            'الكتابةُ الخامّةُ كلُّها تحت **إدارةٍ واحدة** (' . ($ro[0] === '' ? '— بلا مالكٍ مسجَّل' : $ro[0])
          . ') وسائرُ كتّابِه ببابٍ مسمًّى ⇒ **لا حدَّ إدارةٍ عُبِر**'
          . ($ro[0] === '' ? ' · ⚠ **ومالكُ الكاتبِ الخامِّ غيرُ مسجَّلٍ** فالعبورُ غيرُ مُثبَتٍ لا منفيٌّ بالدليل' : ''));
    } else {
        $entVerdict[$ent] = array('DIRECT_WRITE_BREACH',
            '**' . count($rawOwners) . '** إدارةً تكتبه خامًّا (' . implode(' · ', array_map(
                function ($o) { return $o === '' ? '—' : $o; }, array_keys($rawOwners)))
          . ') ⇒ **خرقُ §٥·٩ مقيسًا لا سقفًا**');
    }
}

/* ═══ ⑦ العرض ════════════════════════════════════════════════════════════ */
$vc = array('NOT_A_DUPLICATE' => 0, 'CONTRACTED' => 0, 'DIRECT_WRITE_BREACH' => 0);
foreach ($entVerdict as $v) { $vc[$v[0]]++; }
echo "\n═══ `RPR-02` §٥·٩ — عقدُ الكتابةِ العابرةِ مقيسًا ═══\n";
printf("  اللقطة: %s · كياناتٌ غيرُ محسومةٍ: **%d** · كتّابُها **%d**\n\n", $sid, count($byEnt), count($rows));
echo "  ── حكمُ الكاتب ──\n";
printf("     X0 `NO_MEASURED_WRITE`   %2d — تصريحٌ بلا كتابةٍ ⇒ **ليس كاتبًا**\n", $stat['X0']);
printf("     X1 `SERVICE_MEDIATED`    %2d — صفرُ خامٍّ وبابٌ مسمًّى ⇒ **عقدٌ مقيس**\n", $stat['X1']);
printf("     X2 `RAW_DIRECT`          %2d — كتابةٌ خامّةٌ ⇒ **مباشرة**\n", $stat['X2']);
echo "\n  ── حكمُ الكيان ──\n";
printf("     `NOT_A_DUPLICATE`     %2d — كاتبٌ حقيقيٌّ واحدٌ أو صفر\n", $vc['NOT_A_DUPLICATE']);
printf("     `CONTRACTED`          %2d — عبورٌ **بعقدٍ مقيس**\n", $vc['CONTRACTED']);
printf("     `DIRECT_WRITE_BREACH` %2d — ⛔ **خرقٌ مقيسٌ لا سقف**\n", $vc['DIRECT_WRITE_BREACH']);

echo "\n  ── الكياناتُ بأحكامِها ──\n";
foreach ($entVerdict as $ent => $v) {
    printf("   %-30s %-22s %s\n", $ent, $v[0], mb_substr($v[1], 0, 78));
}
echo "\n  ── الكتّابُ ──\n";
foreach ($rows as $x) {
    printf("   %-26s %-10s own=%-8s خامّ=%-2d %-22s %s\n",
           $x[0], $x[2], ($x[3] === '' ? '—' : $x[3]), $x[5], $x[7], mb_substr($x[6], 0, 40));
}

/* ═══ ⑧ التثبيت ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    $has = $conn->query("SHOW TABLES LIKE 'repair01_cross_contract'");
    if (!$has || !$has->num_rows) {
        exit("⛔ **`repair01_cross_contract` غيرُ موجود** — والعُدّةُ لا تُنشئ مخطَّطًا.\n"
           . "   شغِّلْ: php database/migrations/2028_01_06_rpr02_cross_contract.php\n");
    }
    $conn->query("DELETE FROM repair01_cross_contract");
    $n = 0;
    foreach ($rows as $x) {
        $ev = isset($entVerdict[$x[0]]) ? $entVerdict[$x[0]][0] : '';
        $ok = $conn->query("INSERT INTO repair01_cross_contract
              (entity,entity_verdict,screen_id,owner_code,grain_rule,raw_writes,gates,
               writer_verdict,witness,snapshot_id,measured_at)
            VALUES ('" . $e($x[0]) . "','" . $e($ev) . "','" . $e($x[2]) . "','" . $e($x[3]) . "','"
             . $e($x[4]) . "'," . (int) $x[5] . ",'" . $e(mb_substr($x[6], 0, 200)) . "','"
             . $e($x[7]) . "','" . $e(mb_substr($x[8], 0, 600)) . "','" . $e($sid) . "',NOW())");
        if (!$ok) { exit("✘ تعذّر تثبيتُ {$x[0]}/{$x[2]}: {$conn->error}\n"); }
        $n++;
    }
    $bad = (int) $conn->query("SELECT COUNT(*) FROM repair01_cross_contract WHERE witness = ''")->fetch_row()[0];
    printf("\n  ✔ ثُبِّت **%d** كاتبًا · صفٌّ بلا شاهدٍ %d\n", $n, $bad);
}

if ($MD) {
    $o  = "# `RPR-02` §٥·٩ — عقدُ الكتابةِ العابرةِ مقيسًا\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## من سقفِ الخرقِ إلى الخرق\n\n";
    $o .= "كان المقياسُ **#١١** يقول عن نفسِه: *«العقدُ غيرُ مقيسٍ بعدُ، فالرقمُ **سقفُ الخرقِ\n";
    $o .= "لا الخرقُ**»*. **والعقدُ ليس وثيقةً تُكتب بل مسارُ كتابةٍ يُقاس**: الكتابةُ العابرةُ\n";
    $o .= "تمرُّ **بخدمةِ المجالِ أو بناشرِ الأحداث** (`ADR-15`) لا بجملةِ `SQL` خامٍّ في شاشةِ\n";
    $o .= "إدارةٍ أخرى.\n\n";
    $o .= "## أحكامُ الكاتبِ الثلاثة\n\n| الحكم | العدد | المعنى |\n|---|---:|---|\n";
    $o .= "| `X0_NO_MEASURED_WRITE` | **" . $stat['X0'] . "** | تصريحٌ بالجدولِ بلا جملةِ كتابةٍ واحدة ⇒ **ليس كاتبًا أصلًا** |\n";
    $o .= "| `X1_SERVICE_MEDIATED` | **" . $stat['X1'] . "** | صفرُ خامٍّ وبابٌ مسمًّى ⇒ **عقدٌ مقيسٌ في الكود** |\n";
    $o .= "| `X2_RAW_DIRECT` | **" . $stat['X2'] . "** | كتابةٌ خامّةٌ على الكيان ⇒ **مباشرة** |\n\n";
    $o .= "⛔ **وخدمةٌ مستدعاةٌ مع كتابةٍ خامّةٍ معها ليست عقدًا** — البابُ المفتوحُ بجانبِ\n";
    $o .= "البابِ المحروسِ يُبطل الحراسة.\n\n";
    $o .= "## أحكامُ الكيان\n\n| الحكم | العدد |\n|---|---:|\n";
    $o .= "| `NOT_A_DUPLICATE` | **" . $vc['NOT_A_DUPLICATE'] . "** |\n";
    $o .= "| `CONTRACTED` | **" . $vc['CONTRACTED'] . "** |\n";
    $o .= "| `DIRECT_WRITE_BREACH` | **" . $vc['DIRECT_WRITE_BREACH'] . "** |\n\n";
    $o .= "| الكيان | الحكم | الشاهد |\n|---|---|---|\n";
    foreach ($entVerdict as $ent => $v) { $o .= "| `" . $ent . "` | `" . $v[0] . "` | " . $v[1] . " |\n"; }
    $o .= "\n## الكتّابُ بشواهدِهم\n\n";
    foreach ($rows as $x) { $o .= "- **`" . $x[0] . "` ⇐ `" . $x[2] . "`** (" . ($x[3] === '' ? '—' : $x[3]) . ") — " . $x[8] . "\n"; }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S59_CROSS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_S59_CROSS.md\n";
}
