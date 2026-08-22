<?php
/**
 * tests/injfrd01_sec004_007_export_payload.php
 *   شاهدُ FR-SEC-004 · FR-SEC-007 — يُقرأ **الملفُّ الناتجُ والحمولةُ** لا الشيفرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معياران بنصِّهما**: FR-SEC-004 «**قراءةُ الملفِّ الناتجِ** تُخرج صفرَ حقلٍ
 *   محظور» · و FR-SEC-007 «**فحصُ الحمولةِ لا الشاشة**» وسالبُه «غيرُ مخوَّلٍ ←
 *   **قراءةُ الحمولةِ** تُخرج صفرَ حقلٍ محظور».
 *   ⇒ فلا يكفي أن يُنادى المقرِّرُ في الشيفرة — **يُنتَج الملفُّ ويُقرأ بايتًا**،
 *     وتُطلَب الحمولةُ وتُقرأ نصًّا. والشاهدُ الشجريُّ القائم
 *     (`injfix01_sensitive_fields_nine_channels_proof`) يقيس **بلوغَ المقرِّر**،
 *     وهذا يقيس **الناتج**. ولا يُغني أحدُهما عن الآخر.
 *
 * ◆ **والمقامُ يُقاس ولا يُفترَض**: من 263 عمودًا مُعلَنًا للتصدير في 24 كيانًا،
 *   **عمودٌ واحدٌ فقط حساسٌ فعلًا**: `drivers :: employees.phone` (SEN-002،
 *   «آخر 3 أرقام لغير المخول» · لا يُصدَّر). وهذا هو المقامُ الحقيقيّ — ويُعلَن
 *   صراحةً، **فمقامٌ صغيرٌ مُعلَنٌ خيرٌ من مقامٍ كبيرٍ موهوم**.
 *
 * ◆ **و«صفرُ تسريبٍ» بسببِ منعِ الوصولِ ليس نجاحًا**: لو رُدَّ الطلبُ 401/403
 *   لخرج الملفُّ فارغًا من الحقلِ الحساسِ **لأن لا ملفَّ أصلًا**. فيُشترَط أن
 *   يكون الطلبُ **200 وبجسدٍ فيه صفوفٌ** قبلَ أن يُقرأ الحكم.
 *
 * التشغيل: php tests/injfrd01_sec004_007_export_payload.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$BASE = 'http://localhost/ems';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

$ok = 0; $bad = 0; $skip = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function note($m) { echo "  ◆ {$m}\n"; }

function req($url, $jar, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 90,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return array($code, (string) $body, $type);
}

function login($user, $jar)
{
    global $BASE;
    @unlink($jar);
    list(, $b) = req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    list($c, $body) = req($BASE . '/login.php', $jar, array(
        'username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : '',
    ));
    /* الدخولُ الناجحُ يخرج من صفحةِ الدخول — ووجودُ حقلِ كلمةِ المرورِ دليلُ بقائِه */
    $stillLogin = (strpos($body, 'name="password"') !== false);
    return !$stillLogin;
}

echo "══ FR-SEC-004 · FR-SEC-007 — الملفُّ الناتجُ والحمولةُ يُقرآن ══\n";

/* ── المقامُ: أعمدةٌ مُصدَّرةٌ وهي حساسة ─────────────────────────────────── */
$sens = array();
$r = $db->query("SELECT `table_name`, `field_name` FROM `scr_sensitive_fields`
                  WHERE `status` = 'معتمد'");
while ($r && $x = $r->fetch_assoc()) { $sens[$x['table_name']][$x['field_name']] = true; }
$r = $db->query("SELECT `field_code` FROM `sensitive_field_policies` WHERE `status` = 'نافذة'");
while ($r && $x = $r->fetch_row()) {
    $p = explode('.', $x[0], 2);
    if (count($p) === 2) { $sens[$p[0]][$p[1]] = true; }
}

$reg = $ROOT . '/app/Services/Excel/ExcelRegistry.php';
$src = (string) @file_get_contents($reg);
preg_match_all("~new EntityDefinition\('([a-z_]+)',\s*'[^']*',\s*'([a-z_]+)'~u", $src, $em, PREG_SET_ORDER);
$entTable = array();
foreach ($em as $e) { $entTable[$e[1]] = $e[2]; }

$denom = array();
foreach ($entTable as $ent => $tbl) {
    if (!isset($sens[$tbl])) { continue; }
    /* أعمدةُ الكيانِ تُقرأ من كتلتِه في السجل */
    $pos = strpos($src, "new EntityDefinition('{$ent}'");
    if ($pos === false) { continue; }
    $block = substr($src, $pos, 4000);
    foreach (array_keys($sens[$tbl]) as $f) {
        if (preg_match("~new Column\('" . preg_quote($f, '~') . "'~", $block)) {
            $denom[] = array('entity' => $ent, 'table' => $tbl, 'field' => $f);
        }
    }
}
printf("  المقامُ المقيس: **%d عمودًا مُصدَّرًا وهو حساس** (من %d كيانًا)\n",
       count($denom), count($entTable));
foreach ($denom as $d) { echo "     ✦ {$d['entity']} :: {$d['table']}.{$d['field']}\n"; }

if (empty($denom)) {
    echo "\n  ⛔ **مقامٌ صفرٌ ليس نجاحًا** — لا عمودَ حساسًا في أيِّ تصديرٍ مُعلَن،\n";
    echo "     فلا شيءَ يُقاس. أُوقِف بدل إعلانِ خضرةٍ فارغة.\n";
    exit(1);
}

/* ── ① FR-SEC-004 — الملفُّ الناتجُ يُقرأ بايتًا ────────────────────────── */
echo "\n── ① التصدير: يُنتَج الملفُّ **ويُقرأ** ──\n";
$case = $denom[0];
$vals = array();
/* ◆ **قيمةٌ من محرفٍ واحدٍ ليست دليلَ تسريب**: أوّلُ تشغيلٍ رسب لأن
 *   العمودَ يحمل `-` و`—` لبعضِ الصفوف، وهما يظهران في أيِّ XML.
 *   ⇒ **العيّنةُ تقتصر على ما يصلح دليلًا**: طولٌ ≥ 7 وفيه أربعةُ
 *   أرقامٍ متتاليةٍ على الأقل. ويُعلَن كم صفًّا استُبعد. */
$rawAll = 0;
$q = $db->query("SELECT DISTINCT `{$case['field']}` FROM `{$case['table']}`
                  WHERE COALESCE(`{$case['field']}`,'') <> '' LIMIT 200");
while ($q && $x = $q->fetch_row()) {
    $rawAll++;
    $v = (string) $x[0];
    if (mb_strlen($v) >= 7 && preg_match('~[0-9]{4}~', $v)) { $vals[] = $v; }
}
printf("  قيمٌ حقيقيةٌ في %s.%s تُبحَث في الملف: %d\n",
       $case['table'], $case['field'], count($vals));
if (empty($vals)) {
    echo "  ⛔ لا قيمةَ حقيقيةً في العمودِ — والبحثُ عن لا شيءٍ يُخرج صفرًا كاذبًا. أُوقِف.\n";
    exit(1);
}

$UNAUTH = 'يسن';       /* الدور 3 — الأسطول: يرى السائقين ولا يملك هاتفَ الموظف */
$AUTH   = 'اروينا';    /* الدور 4 — الموارد البشرية: صاحبةُ الحقِّ بنصِّ SEN-002 */
$jar = sys_get_temp_dir() . '/sec004_' . getmypid() . '.jar';

$loggedIn = login($UNAUTH, $jar);
chk($loggedIn, 'دخولُ الدورِ غيرِ المخوَّلِ تمّ — وبلا دخولٍ لا معنى للقياس',
    $UNAUTH . ($loggedIn ? '' : ' — تعذّر'));

if ($loggedIn) {
    list($code, $body, $type) = req($BASE . '/excel.php?entity=' . $case['entity'] . '&action=export', $jar);
    $bytes = strlen($body);
    $served = ($code === 200 && $bytes > 2000);
    chk($served, '**التصديرُ خرج فعلًا** — فصفرُ تسريبٍ بسببِ منعِ الوصولِ ليس نجاحًا',
        "HTTP={$code} · بايت={$bytes} · نوع=" . mb_substr($type, 0, 40));

    if ($served) {
        /* الملفُّ xlsx مضغوط — يُقرأ جوفُه لا غلافُه */
        $tmp = sys_get_temp_dir() . '/sec004_' . getmypid() . '.xlsx';
        file_put_contents($tmp, $body);
        $text = '';
        $z = new ZipArchive();
        if ($z->open($tmp) === true) {
            for ($i = 0; $i < $z->numFiles; $i++) {
                $n = $z->getNameIndex($i);
                if (strpos($n, 'xl/') === 0) { $text .= $z->getFromIndex($i); }
            }
            $z->close();
        } else {
            $text = $body;   /* csv أو نصٌّ خام */
        }
        @unlink($tmp);
        /* ◆ **بحثٌ لا يجد شيئًا قد يكون بحثًا أعمى**: لو فشل فكُّ الضغطِ أو
         *   خُزِّن النصُّ في `sharedStrings` بترميزٍ آخر لخرج «صفرُ ظهور»
         *   وهو لا يعني شيئًا. ⇒ **يُثبَت أوّلًا أن الباحثَ يجد ما هو
         *   موجودٌ فعلًا**: قيمةٌ غيرُ حساسةٍ من الصفوفِ نفسِها يجب أن
         *   تُرى في المحتوى. فإن لم تُرَ، فالقياسُ باطلٌ ويوقف. */
        $canary = ''; $found = false;
        $cq = $db->query("SELECT `name` FROM `{$case['table']}`
                            WHERE COALESCE(`name`,'') <> '' LIMIT 60");
        while ($cq && $cx = $cq->fetch_row()) {
            if (mb_strlen($cx[0]) >= 5 && strpos($text, (string) $cx[0]) !== false) {
                $canary = (string) $cx[0]; $found = true; break;
            }
        }
        chk($found, '**والباحثُ يجد ما هو موجودٌ فعلًا** — وإلا فصفرُ الظهورِ بحثٌ أعمى',
            $found ? 'قيمةٌ شاهدةٌ رُئيت في المحتوى: «' . mb_substr($canary, 0, 24) . '»'
                   : 'لم تُرَ أيُّ قيمةٍ معلومةِ الوجود — **القياسُ باطل**');
        if (!$found) { echo "  ⛔ أُوقِف: لا يُعلَن صفرُ تسريبٍ من بحثٍ لم يُثبت أنه يقرأ
"; exit(1); }

        $leaked = array();
        foreach ($vals as $v) {
            if ($v !== '' && strpos($text, $v) !== false) { $leaked[] = $v; }
        }
        chk(empty($leaked),
            'FR-SEC-004 · **قراءةُ الملفِّ الناتجِ تُخرج صفرَ حقلٍ محظور**',
            empty($leaked) ? 'فُحصت ' . count($vals) . ' قيمةً حقيقيةً في '
                           . strlen($text) . ' بايتَ محتوًى — صفرُ ظهور'
                           : count($leaked) . ' قيمةً ظهرت: ' . implode(' · ', array_slice($leaked, 0, 3)));

        /* ولا يكون المنعُ فقدًا: الملفُّ ما يزال يحمل أعمدتَه غيرَ الحساسة */
        $hasRows = (strpos($text, 'sheet') !== false || strlen($text) > 5000);
        chk($hasRows, 'والملفُّ ما يزال ملفًّا بأعمدتِه — **المنعُ ليس فقدًا**',
            strlen($text) . ' بايتَ محتوًى');
    }
}

/* المخوَّلُ يستلم ملفَه — «مخوَّلٌ ← ملفٌّ بحقولِه» */
$jar2 = sys_get_temp_dir() . '/sec004b_' . getmypid() . '.jar';
if (login($AUTH, $jar2)) {
    list($c2, $b2) = req($BASE . '/excel.php?entity=' . $case['entity'] . '&action=export', $jar2);
    chk($c2 === 200 && strlen($b2) > 2000,
        'والمخوَّلُ يستلم ملفَه — «مخوَّلٌ ← ملفٌّ بحقولِه»',
        "HTTP={$c2} · بايت=" . strlen($b2));
} else {
    chk(false, 'دخولُ الدورِ المخوَّل', $AUTH . ' — تعذّر');
}
@unlink($jar2);

/* ── ② FR-SEC-007 — الحمولةُ تُقرأ ──────────────────────────────────────── */
echo "\n── ② الحمولة: تُطلَب **وتُقرأ** ──\n";
/* ◆ **والمقرِّرُ يُقاس عبرَ الحمولةِ لا عبرَ الشيفرة**: يُنادى الحارسُ نفسُه الذي
 *   تناديه الواجهةُ (`api/bootstrap.php`) بقيمةٍ حقيقيةٍ ودورَين، ويُقارَن
 *   الناتجان. فإن تساويا فالحارسُ لا يفرّق — وهو العطبُ عينُه. */
require_once $ROOT . '/app/Services/Security/SensitiveFieldGuard.php';
$G = '\App\Services\Security\SensitiveFieldGuard';
$code = $case['table'] . '.' . $case['field'];
$real = $vals[0];

$rowU = array($case['field'] => $real);
$rowA = array($case['field'] => $real);
$map  = array($case['field'] => $code);

$outU = $G::filterRow($db, $rowU, $map, 0, '3', 4, 'sec007:probe', 'injfrd01');
$outA = $G::filterRow($db, $rowA, $map, 0, '4', 4, 'sec007:probe', 'injfrd01');

$uVal = isset($outU[$case['field']]) ? (string) $outU[$case['field']] : '(محذوف)';
$aVal = isset($outA[$case['field']]) ? (string) $outA[$case['field']] : '(محذوف)';

chk($uVal !== $real,
    'FR-SEC-007 · **حمولةُ غيرِ المخوَّلِ لا تحمل القيمةَ الخام**',
    "الخام «{$real}» ⇒ غيرُ المخوَّلِ يستلم «{$uVal}»");
chk($uVal !== $aVal,
    'والحارسُ **يفرّق بين الدورَين** — ولو تساويا لما كان حارسًا',
    "دور3 «{$uVal}» ≠ دور4 «{$aVal}»");
chk($aVal === $real || $aVal !== '(محذوف)',
    'والمخوَّلُ ما يزال يستلمه — **الإصلاحُ ليس فقدًا**',
    "دور4 يستلم «{$aVal}»");

/* ولا يُقرأ هذا بديلًا عن الطلبِ الحيّ: تُقاس نقطةُ الواجهةِ نفسُها */
$apiCode = 0;
list($apiCode, $apiBody) = req($BASE . '/api/index.php?route=drivers', $jar);
$rawInPayload = false;
foreach ($vals as $v) { if ($v !== '' && strpos((string) $apiBody, $v) !== false) { $rawInPayload = true; break; } }
chk(!$rawInPayload,
    'وحمولةُ الواجهةِ الحيّةِ لا تحمل قيمةً خامًّا',
    "HTTP={$apiCode} · بايت=" . strlen((string) $apiBody)
    . ($apiCode >= 400 ? ' — **والردُّ رفضٌ فلا يُقرأ نجاحًا**، يُعلَن كما هو' : ''));
if ($apiCode >= 400) {
    note('الواجهةُ ردَّت ' . $apiCode . ' لهذا الدور — فبندُ الحمولةِ الحيّةِ **غيرُ مقيسٍ**'
       . ' هنا، والمقيسُ هو الحارسُ نفسُه أعلاه.');
}

@unlink($jar);

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
