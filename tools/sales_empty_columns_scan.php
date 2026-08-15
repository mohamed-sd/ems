<?php
/**
 * tools/sales_empty_columns_scan.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ماسحُ الأعمدةِ الفارغةِ على شاشاتِ **مدير المبيعات** (الدور 12).
 *
 * يفتح كلَّ شاشةٍ من `nav_items` للدور 12 بحسابِ المبيعاتِ الحقيقيِّ عبر HTTP،
 * ثم يقرأ كلَّ جدولٍ فيها ويحكم على كلِّ عمودٍ بأحدِ ثلاثةِ أحكام:
 *   EMPTY_TABLE — الجدولُ بلا صفوفٍ أصلًا (فكلُّ أعمدتِه بلا داتا)
 *   EMPTY_COL   — الجدولُ فيه صفوفٌ، وهذا العمودُ فارغٌ في كلِّ صفٍّ منها
 *   OK          — فيه قيمةٌ في صفٍّ واحدٍ على الأقل
 *
 * ★ الجداولُ بـ`serverSide` تُملأ بـAJAX لا بـHTML — تُوسَم SERVERSIDE ويُقاس
 *   مصدرُها بندائها لا بجسدِ الصفحة (وإلا حُكم على كلِّ عمودٍ بالفراغِ كذبًا).
 *
 * الاستعمال: php tools/sales_empty_columns_scan.php [--json=<path>] [--route=<like>]
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = 'http://localhost/ems';
$USER = 'مبيعات';
$PASS = '12345678';
$ROLE = 12;

$jsonOut = null; $routeFilter = null;
foreach ($argv as $a) {
    if (strpos($a, '--json=') === 0)  $jsonOut = substr($a, 7);
    if (strpos($a, '--route=') === 0) $routeFilter = substr($a, 8);
}

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

/* ── طبقةُ HTTP ──────────────────────────────────────────────────────────── */
function req($url, $jar, $post = null) {
    $ch = curl_init($url);
    $opt = array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120,
    );
    if ($post !== null) { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = $post; }
    curl_setopt_array($ch, $opt);
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) return array(0, '', '');
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
/** يُرجع true إن أثبت الخادمُ الدخولَ — وشاهدُه تحويلةُ النجاحِ إلى لوحةِ الدخول.
    (الفشلُ يُعيد 200 بالصفحةِ نفسِها وفيها الخطأُ، فالتمييزُ بالرمزِ لا بالجسد.) */
function do_login($base, $user, $pass, $jar) {
    @unlink($jar);
    list($c, $h, $b) = req($base . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    list($c2, $h2, $b2) = req($base . '/login.php', $jar, http_build_query(array(
        'username' => $user, 'password' => $pass,
        'csrf_token' => isset($m[1]) ? $m[1] : '')));
    if ($c2 !== 302) { return false; }
    return (bool) preg_match('~^Location:\s*.*dashboard\.php~mi', $h2);
}

/* ── قارئُ الجداول ───────────────────────────────────────────────────────── */
function cell_text($html) {
    /* الخليةُ قد تحمل أيقونةً أو زرًّا لا نصًّا — فالنصُّ المرئيُّ وحدَه يُحتسب،
       والمدخلاتُ تُحتسب بقيمتِها لأنَّ العمودَ المحرَّرَ داتا أيضًا. */
    $h = preg_replace('~<script\b[^>]*>.*?</script>~is', ' ', $html);
    $h = preg_replace('~<style\b[^>]*>.*?</style>~is', ' ', $h);
    if (preg_match_all('~<(?:input|select|option|textarea)\b[^>]*\bvalue="([^"]*)"~i', $h, $vm)) {
        foreach ($vm[1] as $v) { $h .= ' ' . $v; }
    }
    $t = html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8');
    $t = str_replace(array("\xc2\xa0", "\xe2\x80\x8f", "\xe2\x80\x8e"), ' ', $t);
    return trim(preg_replace('/\s+/u', ' ', $t));
}
/** فارغٌ فعلًا؟ الشرطةُ والصفرُ المزخرفُ وعلاماتُ اللاشيء فراغٌ في نظرِ المستخدم. */
function is_blank($t) {
    if ($t === '') return true;
    $n = trim(strtr($t, array('—' => '-', '–' => '-', '−' => '-', '\u{2013}' => '-')));
    $n = trim($n, " \t-–—.·:");
    if ($n === '') return true;
    return in_array($n, array('N/A', 'n/a', 'null', 'NULL', 'غير محدد', 'غير محدّد', 'لا يوجد', '--'), true);
}
/** جدولُ إدخالٍ لا عرض: صُلبُه يُبنى في المتصفِّح عند ضغطِ «أضف بندًا»، فخلوُّه
    قبل الإدخالِ هو الصحيح — لا نقصٌ في داتا.
    ★ ولا يكفي وسمُ `no-datatable` وحدَه للحكم: الجدولُ المهيَّأُ يدويًّا بـ
      `serverSide` يحمل الوسمَ نفسَه (وسمُ «لا تتبنَّني تلقائيًّا» لا وسمُ «أنا
      نموذج») — فاشتراطُ غيابِ النداءِ الخادميِّ هو ما يفصل بينهما. */
function is_input_scaffold($attrs, $rows, $serverSide) {
    return !$rows && !$serverSide && preg_match('~\b(no-datatable|data-no-dt)\b~i', $attrs);
}
/* ★ أعمدةٌ **خلوُّها معنًى لا نقص**. عمودُ «معكوس بـ» يمتلئ للصفِّ المعكوسِ
     وحدَه؛ ولو مُلئ لكلِّ صفٍّ لأعلنت الشاشةُ أنَّ كلَّ حركةٍ عُكست — وهو كذبٌ
     أسوأُ من الفراغ. فيكفي هذه الأعمدةَ أن تُثبت خليةً واحدةً حيّة. */
function is_conditional_col($label) {
    $l = trim(preg_replace('/\s+/u', ' ', $label));
    return in_array($l, array('معكوس بـ', 'عكس عن', 'سبب الإسقاط', 'سبب الرفض',
        'سبب التعليق', 'سبب الاستبدال', 'الوحدة السابقة', 'معكوس ب'), true);
}
/** عمودُ علامةٍ ثنائية: قيمُه المملوءةُ كلُّها علاماتُ نعم/لا — و«—» فيه «لا». */
function is_mark_col($vals) {
    $marks = array('✓', '✔', '✗', '✘', 'نعم', 'لا', '✅', '❌');
    $seen = 0;
    foreach ($vals as $v) {
        if ($v === '') continue;
        if (!in_array($v, $marks, true)) return false;
        $seen++;
    }
    return $seen > 0;
}
/** أعمدةٌ لا تُحاسَب: الاختيارُ والتسلسلُ والأفعال. */
function is_ui_col($label) {
    $l = trim(preg_replace('/\s+/u', ' ', $label));
    return in_array($l, array('#', 'م', 'الإجراءات', 'الاجراءات', 'إجراءات', 'اجراءات', 'الإجراء',
        'الاجراء', 'تحديد', 'خيارات', 'الخيارات', 'التحكم', ''), true);
}

function parse_tables($html) {
    $out = array();
    if (!preg_match_all('~<table\b([^>]*)>(.*?)</table>~is', $html, $tm, PREG_SET_ORDER)) return $out;
    foreach ($tm as $ti => $t) {
        $attrs = $t[1]; $inner = $t[2];
        $id = ''; if (preg_match('~\bid="([^"]*)"~i', $attrs, $im)) $id = $im[1];
        $cls = ''; if (preg_match('~\bclass="([^"]*)"~i', $attrs, $cm)) $cls = $cm[1];

        /* الترويسة: أوّلُ <tr> فيه <th> — ونحتفظ بسِمات كلِّ <th> لأنَّ **مصدرَ
           الخليةِ يُعرف من السِّمة لا من الاسم**: `data-gov`/`data-fn` يحشوهما
           ui-unification.js وقتَ التشغيل، فلا أثرَ لهما في HTML الخام. */
        $heads = array(); $hattrs = array();
        if (preg_match_all('~<tr\b[^>]*>(.*?)</tr>~is', $inner, $rm, PREG_SET_ORDER)) {
            foreach ($rm as $r) {
                if (preg_match_all('~<th\b([^>]*)>(.*?)</th>~is', $r[1], $hm, PREG_SET_ORDER)) {
                    if (count($hm) > count($heads)) {
                        $heads = array(); $hattrs = array();
                        foreach ($hm as $h) { $heads[] = cell_text($h[2]); $hattrs[] = $h[1]; }
                    }
                }
            }
        }
        if (!$heads) continue;

        /* الصفوف: كلُّ <tr> فيه <td> (نتجاهل صفَّ «لا توجد بيانات» ذا الخليةِ الواحدة) */
        $rows = array();
        if (preg_match_all('~<tr\b[^>]*>(.*?)</tr>~is', $inner, $rm2, PREG_SET_ORDER)) {
            foreach ($rm2 as $r) {
                if (!preg_match_all('~<td\b[^>]*>(.*?)</td>~is', $r[1], $dm)) continue;
                if (count($dm[1]) <= 1 && count($heads) > 1) continue;  // صفُّ رسالةٍ لا داتا
                $cells = array(); foreach ($dm[1] as $d) $cells[] = cell_text($d);
                $rows[] = $cells;
            }
        }
        $out[] = array('idx' => $ti, 'id' => $id, 'class' => $cls, 'attrs' => $attrs,
                       'heads' => $heads, 'hattrs' => $hattrs, 'rows' => $rows);
    }
    return $out;
}

/* ── الشاشات ─────────────────────────────────────────────────────────────── */
$routes = array();
$q = "SELECT DISTINCT route, MIN(label_ar) lbl FROM nav_items
      WHERE role_id = {$ROLE} AND active = 1 GROUP BY route ORDER BY route";
$r = $db->query($q);
while ($x = $r->fetch_assoc()) {
    $rt = preg_replace('~#.*$~', '', $x['route']);
    if ($rt === '' ) continue;
    if ($routeFilter !== null && stripos($rt, $routeFilter) === false) continue;
    if (!isset($routes[$rt])) $routes[$rt] = $x['lbl'];
}

/* ★★ حارسُ الدخول — **صفرُ نقصٍ لأنَّ الدخولَ فشل ليس نجاحًا**.
     لو رُدَّ كلُّ طلبٍ إلى `login.php` لقرأ الماسحُ صفرَ جدولٍ في 71 شاشةً
     وأعلن «لا عمودَ ناقصًا» — أخضرُ كاذبٌ تامّ. (وقع فعلًا: فاحصُ انحدارٍ غيَّر
     كلمةَ حسابِ المبيعاتِ ولم يُعِدها.) فيُجَسُّ الدخولُ صراحةً ويُوقَف الشوطُ
     إن لم يثبت. */
$jar = sys_get_temp_dir() . '/ems_sales_scan_' . getmypid() . '.txt';
$loggedIn = do_login($BASE, $USER, $PASS, $jar);
if (!$loggedIn) {
    fwrite(STDERR, "✘ فشلَ الدخولُ بحساب «{$USER}» — أُوقف الشوط.\n"
                 . "  لا تقرأ صفرَ نقصٍ نجاحًا: الشاشاتُ لم تُفتح أصلًا.\n"
                 . "  تحقّق من كلمةِ الحساب (المتَّفَقُ عليه في العرض: 12345678).\n");
    @unlink($jar);
    exit(2);
}

$report = array();
$nEmptyTable = 0; $nEmptyCol = 0; $nOkCol = 0; $nScreens = 0;
$nUnboundGov = 0; $nUnboundFn = 0; $nPartial = 0;

fwrite(STDOUT, "══ مسحُ أعمدةِ شاشاتِ مدير المبيعات (الدور {$ROLE}) — " . count($routes) . " شاشة ══\n\n");

foreach ($routes as $rt => $lbl) {
    $url = $BASE . '/' . ltrim($rt, '/');
    list($code, $hdr, $body) = req($url, $jar);
    $nScreens++;
    $entry = array('route' => $rt, 'label' => $lbl, 'http' => $code, 'tables' => array());

    if ($code === 302 || $code === 301) {
        preg_match('~^Location:\s*(.+)$~mi', $hdr, $lm);
        $entry['redirect'] = isset($lm[1]) ? trim($lm[1]) : '';
    }
    /* مفاتيحُ الحوكمةِ المملوءةُ فعلًا في هذه الصفحة — من `window.emsGovCtx`
       نفسِه لا من تخمين. ما عداها يحشوه المُوحِّدُ «—» فهو عمودٌ بلا مصدر. */
    $govFilled = array();
    if (preg_match('~window\.emsGovCtx\s*=\s*(\{.*?\});~s', $body, $gm)) {
        $ctx = json_decode(html_entity_decode($gm[1], ENT_QUOTES, 'UTF-8'), true);
        if (isset($ctx['values']) && is_array($ctx['values'])) {
            foreach ($ctx['values'] as $k => $v) { if ($v !== null && $v !== '') $govFilled[$k] = $v; }
        }
    }
    $entry['govFilled'] = array_keys($govFilled);

    /* ★ جدولٌ بـ`serverSide` جسدُه في HTML فارغٌ دائمًا — صفوفُه تأتي بـAJAX.
         فالحكمُ عليه من نداءِ الخادمِ نفسِه، وإلا أُدين كلُّ عمودٍ فيه بالفراغِ
         كذبًا (5,253 صفًّا حاضرةً تُقرأ صفرًا). */
    $ssRows = null;
    if (preg_match('~serverSide\s*:\s*true~i', $body)) {
        /* عنوانُ النداءِ إمّا نصٌّ صريحٌ في `ajax:` وإمّا الصفحةُ نفسُها بمعامل
           `ajax=dt` (النمطُ الغالبُ هنا: `ajax: { data: d => d.ajax='dt' }`). */
        $au = preg_match('~ajax\s*:\s*[\'"]([^\'"]+)[\'"]~i', $body, $ax) ? $ax[1] : $rt;
        $au = (strpos($au, 'http') === 0) ? $au
            : $BASE . '/' . ltrim(dirname('/' . $rt) . '/' . ltrim($au, './'), '/');
        if ($au === $BASE . '/' . ltrim($rt, '/') || strpos($au, '?') === false) {
            $au = $BASE . '/' . ltrim($rt, '/') . '?ajax=dt';
        }
        $au .= '&draw=1&start=0&length=25';
        list($ac, $ah, $ab) = req($au, $jar);
        $aj = json_decode($ab, true);
        if (isset($aj['data']) && is_array($aj['data'])) {
            $ssRows = array();
            foreach ($aj['data'] as $dr) {
                $cells = array();
                foreach (array_values((array) $dr) as $cv) $cells[] = cell_text((string) $cv);
                $ssRows[] = $cells;
            }
            $entry['ajax'] = array('url' => $au, 'total' => $aj['recordsTotal'] ?? null);
        }
    }

    $tabs = parse_tables($body);
    foreach ($tabs as $t) {
        $serverSide = ($ssRows !== null);
        if ($serverSide && !$t['rows']) { $t['rows'] = $ssRows; }
        if (is_input_scaffold($t['attrs'], $t['rows'], $serverSide)) { continue; }

        /* ★ **السِّمةُ وحدَها لا تحسم**: شاشاتُ CMP-03 المولَّدةُ ترسم أعمدةَ
             الحوكمةِ بـPHP لكلِّ صفٍّ (`cmp03_cell`) وهي تحمل `data-gov` نفسَها.
             فالفاصلُ عدُّ الخلايا: صفٌّ بعددِ الترويسةِ رسمَه PHP كاملًا فيُحكَم
             عليه بمحتواه؛ وصفٌّ أقصرُ من الترويسةِ هو الذي يحشوه المُوحِّد. */
        $rowWidth = 0;
        foreach ($t['rows'] as $rr) { if (count($rr) > $rowWidth) $rowWidth = count($rr); }
        $jsPadded = $t['rows'] && $rowWidth < count($t['heads']);
        $cols = array();
        $nH = count($t['heads']);
        for ($i = 0; $i < $nH; $i++) {
            $label = $t['heads'][$i];
            if (is_ui_col($label)) continue;
            $attr = isset($t['hattrs'][$i]) ? $t['hattrs'][$i] : '';
            $govKey = preg_match('~\bdata-gov="([^"]*)"~i', $attr, $am) ? $am[1] : null;
            $isFn   = (bool) preg_match('~\bdata-fn=~i', $attr);

            /* ① عمودُ حوكمةٍ: قيمتُه من السياقِ العامِّ لا من الصف — فالحكمُ
                  عليه بمفتاحِه في `emsGovCtx`، وقراءةُ HTML الخامِّ عنه عمياء. */
            if ($govKey !== null && ($jsPadded || !$t['rows'])) {
                $has = isset($govFilled[$govKey]);
                $verdict = $has ? 'OK' : 'UNBOUND_GOV';
                if ($has) $nOkCol++; else $nUnboundGov++;
                $cols[] = array('i' => $i, 'label' => $label, 'kind' => 'gov', 'key' => $govKey,
                                'filled' => $has ? count($t['rows']) : 0, 'verdict' => $verdict);
                continue;
            }
            /* ② عمودٌ وظيفيٌّ محقونٌ (`data-fn`): المُوحِّدُ يحشوه بمفتاحِ
                  `data-gov` وهو معدومٌ هنا — فهو «—» دائمًا مهما امتلأت القاعدة. */
            if ($isFn && ($jsPadded || !$t['rows'])) {
                $nUnboundFn++;
                $cols[] = array('i' => $i, 'label' => $label, 'kind' => 'fn',
                                'filled' => 0, 'verdict' => 'UNBOUND_FN');
                continue;
            }
            /* ③ عمودٌ يرسمه PHP — هذا وحدَه ما تُصلحه الداتا التجريبية.
                  والحكمُ **بالخليةِ لا بالعمود**: عمودٌ امتلأ صفٌّ واحدٌ منه
                  وبقي تسعةٌ فارغةً «بلا داتا» في نظرِ من ينظر إلى الشاشة. */
            $filled = 0; $vals = array();
            foreach ($t['rows'] as $row) {
                $cv = isset($row[$i]) ? $row[$i] : '';
                $vals[] = is_blank($cv) ? '' : $cv;
                if (!is_blank($cv)) $filled++;
            }
            $n = count($t['rows']);
            /* خليةٌ حيّةٌ واحدةٌ تكفي العمودَ المشروطَ وعمودَ العلامة — وما عداهما
               يُقاس بالخلية، فالعمودُ نصفُ الممتلئِ نصفُه «بلا داتا» لمن ينظر. */
            $lenient = is_conditional_col($label) || is_mark_col($vals);
            if (!$n)                          { $verdict = 'EMPTY_TABLE'; $nEmptyTable++; }
            elseif ($filled === 0)            { $verdict = 'EMPTY_COL';   $nEmptyCol++; }
            elseif ($lenient)                 { $verdict = 'OK';          $nOkCol++; }
            elseif ($filled < $n)             { $verdict = 'PARTIAL';     $nPartial++; }
            else                              { $verdict = 'OK';          $nOkCol++; }
            $cols[] = array('i' => $i, 'label' => $label, 'kind' => 'php',
                            'filled' => $filled, 'rows' => $n, 'verdict' => $verdict);
        }
        $entry['tables'][] = array(
            'id' => $t['id'], 'class' => $t['class'], 'rows' => count($t['rows']),
            'serverSide' => $serverSide, 'cols' => $cols,
        );
    }
    $report[] = $entry;

    $bad = 0; $tot = 0;
    foreach ($entry['tables'] as $t) foreach ($t['cols'] as $c) { $tot++; if ($c['verdict'] !== 'OK') $bad++; }
    $flag = $bad === 0 ? '✔' : '✘';
    fwrite(STDOUT, sprintf("%s %-52s HTTP %s · جداول %d · أعمدة %d · ناقص %d%s\n",
        $flag, $rt, $code, count($entry['tables']), $tot, $bad,
        isset($entry['redirect']) ? ' → ' . $entry['redirect'] : ''));
}

fwrite(STDOUT, "\n── الحصيلة ──\n");
fwrite(STDOUT, "  شاشات: {$nScreens}\n");
fwrite(STDOUT, "  أعمدة بداتا (OK): {$nOkCol}\n");
fwrite(STDOUT, "  ▸ تُصلحها الداتا التجريبية:\n");
fwrite(STDOUT, "     EMPTY_COL   (جدولٌ ذو صفوفٍ وعمودٌ فارغٌ فيها كلِّها): {$nEmptyCol}\n");
fwrite(STDOUT, "     EMPTY_TABLE (جدولٌ بلا صفوفٍ أصلًا):                 {$nEmptyTable}\n");
fwrite(STDOUT, "     PARTIAL     (عمودٌ امتلأ بعضُ خلاياه وبقي بعضُها):   {$nPartial}\n");
fwrite(STDOUT, "  ▸ لا تُصلحها الداتا — عمودٌ بلا مصدرٍ في الرمز:\n");
fwrite(STDOUT, "     UNBOUND_GOV (رأسُ حوكمةٍ بلا مفتاحٍ في emsGovCtx):    {$nUnboundGov}\n");
fwrite(STDOUT, "     UNBOUND_FN  (رأسٌ وظيفيٌّ محقونٌ بلا أيِّ مصدر):        {$nUnboundFn}\n");

if ($jsonOut) {
    file_put_contents($jsonOut, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fwrite(STDOUT, "\nJSON ⇐ {$jsonOut}\n");
}
@unlink($jar);
