<?php
/**
 * tools/actions_col_lib.php — ماسحُ الجداولِ الواعي بـPHP.
 *
 * لماذا ماسحٌ لا تعبيرٌ نمطيّ؟
 *   لأن صفوفَ هذه الجداولِ تُطبَع من داخلِ PHP: فيها `<?php if (...): ?>` بين
 *   خليةٍ وأخرى، و`echo "<td>…</td>"` داخلَ نصوصٍ مقتبَسة، وجداولُ متداخلةٌ في
 *   خلايا. أيُّ `preg_match` على `<td>.*?<\/td>` يبتلع ما ليس خليةً ويزيح
 *   الأعمدةَ صامتًا — وإزاحةُ عمودٍ في جدولٍ ماليٍّ خطأٌ لا يُرى حتى يُقرأ رقمٌ
 *   في غيرِ خانتِه.
 *
 * فالمنهجُ: نصنع «ظِلًّا» للنصِّ تُستبدَل فيه كلُّ منطقةِ PHP بمسافاتٍ بطولِها،
 * ثم نمسح الوسومَ على الظلِّ ونقتطع من الأصل. فما كان داخلَ PHP لا يُرى وسمًا،
 * وما كان HTML حقيقيًّا يُرى بموضعِه الصحيحِ حرفًا بحرف.
 */

/** يبني ظِلَّ النصِّ: مناطقُ PHP تصير مسافات، وطولُ النصِّ لا يتغيّر. */
function acl_php_shadow($s)
{
    $n = strlen($s);
    $out = $s;
    $i = 0;
    while ($i < $n) {
        $open = strpos($s, '<?', $i);
        if ($open === false) break;
        // موضعُ نهايةِ الوسمِ المفتوح
        $j = $open + 2;
        if (substr($s, $open, 5) === '<?php') $j = $open + 5;
        elseif (substr($s, $open, 3) === '<?=') $j = $open + 3;
        $close = $n; // إن لم يُغلق فالباقي كلُّه PHP (شائعٌ في نهايةِ الملف)
        while ($j < $n) {
            $c = $s[$j];
            if ($c === "'" || $c === '"') {
                $q = $c; $j++;
                while ($j < $n) {
                    if ($s[$j] === '\\') { $j += 2; continue; }
                    if ($s[$j] === $q) { $j++; break; }
                    $j++;
                }
                continue;
            }
            if ($c === '/' && $j + 1 < $n && $s[$j + 1] === '/') {
                $e = strpos($s, "\n", $j); $j = ($e === false) ? $n : $e; continue;
            }
            if ($c === '#') { $e = strpos($s, "\n", $j); $j = ($e === false) ? $n : $e; continue; }
            if ($c === '/' && $j + 1 < $n && $s[$j + 1] === '*') {
                $e = strpos($s, '*/', $j); $j = ($e === false) ? $n : $e + 2; continue;
            }
            if ($c === '?' && $j + 1 < $n && $s[$j + 1] === '>') { $close = $j + 2; break; }
            $j++;
        }
        $len = $close - $open;
        $out = substr_replace($out, str_repeat(' ', $len), $open, $len);
        $i = $close;
    }
    return $out;
}

/**
 * يجد مدياتِ عنصرٍ من نوعٍ واحدٍ (table/tr/td/th) على مستوًى واحدٍ داخلَ مدًى.
 * يُرجع مصفوفةَ ['s'=>بدايةُ الوسمِ المفتوح, 'e'=>نهايةُ الوسمِ المغلق,
 *               'os'=>نهايةُ الوسمِ المفتوح (بدايةُ المحتوى), 'oe'=>بدايةُ المغلق]
 * ويحترم التداخلَ (جدولٌ داخلَ خلية) فلا يعدُّ إلا المستوى الأعلى.
 */
function acl_find_elements($shadow, $tag, $from, $to)
{
    $res = [];
    $len = strlen($tag);
    $i = $from;
    while ($i < $to) {
        // ابحث عن وسمٍ مفتوحٍ لهذا النوع
        $p = stripos($shadow, '<' . $tag, $i);
        if ($p === false || $p >= $to) break;
        $after = $p + 1 + $len;
        if ($after < strlen($shadow) && !preg_match('/[\s>\/]/', $shadow[$after])) { $i = $p + 1; continue; }
        $gt = strpos($shadow, '>', $p);
        if ($gt === false || $gt >= $to) break;
        // وسمٌ ذاتيُّ الإغلاق
        if ($shadow[$gt - 1] === '/') { $res[] = ['s' => $p, 'os' => $gt + 1, 'oe' => $gt + 1, 'e' => $gt + 1]; $i = $gt + 1; continue; }
        // امشِ للأمامِ عادًّا التداخل
        $depth = 1; $k = $gt + 1; $endOpen = null; $endClose = null;
        while ($k < $to && $depth > 0) {
            $nOpen  = stripos($shadow, '<' . $tag, $k);
            $nClose = stripos($shadow, '</' . $tag, $k);
            if ($nClose === false || $nClose >= $to) break;
            if ($nOpen !== false && $nOpen < $nClose && $nOpen < $to) {
                $a = $nOpen + 1 + $len;
                if ($a < strlen($shadow) && preg_match('/[\s>\/]/', $shadow[$a])) $depth++;
                $k = $nOpen + 1;
                continue;
            }
            $depth--;
            if ($depth === 0) {
                $endOpen = $nClose;
                $cg = strpos($shadow, '>', $nClose);
                $endClose = ($cg === false) ? $to : $cg + 1;
            }
            $k = $nClose + 2;
        }
        if ($endOpen === null) {
            // وسمٌ غيرُ مغلَق — لا نلمس جدولًا لا نفهم حدودَه
            $res[] = ['s' => $p, 'os' => $gt + 1, 'oe' => null, 'e' => null];
            break;
        }
        $res[] = ['s' => $p, 'os' => $gt + 1, 'oe' => $endOpen, 'e' => $endClose];
        $i = $endClose;
    }
    return $res;
}

/** يجد خلايا الصفِّ (td وth معًا) مرتَّبةً بموضعِها. */
function acl_row_cells($shadow, $from, $to)
{
    $cells = array_merge(
        acl_find_elements($shadow, 'td', $from, $to),
        acl_find_elements($shadow, 'th', $from, $to)
    );
    usort($cells, fn($a, $b) => $a['s'] <=> $b['s']);
    // انزع ما كان متداخلًا داخلَ خليةٍ أخرى (جدولٌ في خلية)
    $top = [];
    $limit = -1;
    foreach ($cells as $c) {
        if ($c['e'] === null) return null;          // خليةٌ غيرُ مغلَقة ⇒ لا نلمس
        if ($c['s'] < $limit) continue;
        $top[] = $c;
        $limit = $c['e'];
    }
    return $top;
}

/**
 * هل يجوز نقلُ هذا المدى نصًّا كما هو؟
 *
 * في الجداولِ المطبوعةِ من PHP تكون الخليةُ نصًّا مثل:  <td>' . $x . '</td>
 * فنقلُها سليمٌ **بشرطِ أن تكون مغلقةَ الاقتباسِ على نفسِها**؛ فإن بدأت داخلَ
 * اقتباسٍ مفردٍ وانتهت داخلَ مزدوجٍ صار الكودُ بعد النقلِ لا يُحلَّل أصلًا،
 * أو — وهو الأسوأ — يُحلَّل ويطبع شيئًا آخر. فنَعُدُّ الاقتباساتِ ووسومَ PHP
 * والأقواسَ المعقوفة، ونرفض ما لا يتوازن.
 */
function acl_span_safe($txt)
{
    $sq = 0; $dq = 0; $n = strlen($txt);
    for ($i = 0; $i < $n; $i++) {
        if ($txt[$i] === '\\') { $i++; continue; }
        if ($txt[$i] === "'") $sq++;
        elseif ($txt[$i] === '"') $dq++;
    }
    if ($sq % 2 !== 0 || $dq % 2 !== 0) return false;
    $open  = preg_match_all('/<\?(php|=)?/', $txt);
    $close = substr_count($txt, '?>');
    if ($open !== $close) return false;
    if (substr_count($txt, '{') !== substr_count($txt, '}')) return false;
    if (substr_count($txt, '(') !== substr_count($txt, ')')) return false;
    return true;
}

/** نصُّ الخليةِ بعد نزعِ الوسومِ والتشكيلِ — للمطابقةِ بالاسم. */
function acl_cell_text($src, $c)
{
    $t = substr($src, $c['os'], $c['oe'] - $c['os']);
    $t = preg_replace('/<\?(php|=)?.*?\?>/su', ' ', $t);
    $t = strip_tags($t);
    $t = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{064B}-\x{0652}]/u', '', $t);
    $t = str_replace(['&nbsp;', '&rlm;', '&lrm;'], ' ', $t);
    return trim(preg_replace('/\s+/u', ' ', $t));
}

/** هل هذا الرأسُ عمودَ إجراءات؟ */
function acl_is_actions($txt)
{
    return in_array($txt, ['الإجراءات', 'الاجراءات', 'إجراءات', 'اجراءات', 'الأجراءات'], true);
}

/** هل هذا الرأسُ عمودَ عدّادٍ تسلسليّ؟ */
function acl_is_counter_head($txt)
{
    return in_array($txt, ['#', 'م', 'م.', 'ر.م', 'رقم', 'No', 'no', '№'], true);
}

/** هل محتوى هذه الخليةِ عدّادٌ آليٌّ (لا بيانٌ من السجل)؟ */
function acl_is_counter_cell($body)
{
    $b = preg_replace('/\s+/', '', $body);
    // أنماطُ العدِّ الآليِّ الشائعةُ في هذا النظام
    $pat = [
        '/\$[a-z_]*i\+\+/i', '/\+\+\$[a-z_]*i/i',
        '/\$(idx|index|i|n|no|num|counter|count|row|rownum|row_no|serial|seq|sn)\b[^;]{0,12}\+1/i',
        '/\$(counter|serial|seq|rownum|row_no|sn)\+\+/i',
        '/\$(counter|serial|seq|rownum|row_no|sn|i|n)\b\s*(\?>|;)/i',
        '/\$loop->iteration/i',
    ];
    foreach ($pat as $p) if (preg_match($p, $b)) return true;

    /* الحالةُ التي أفلتت أوّلَ مرّة: العدّادُ يُمرَّر إلى دالةِ رسمٍ فتطبعه
       متغيّرًا عاريًا — `echo "<td>" . $i . "</td>"` — بلا `++` في الخلية.
       فبقيت البوابةُ خضراءَ على عدّادٍ حيٍّ في Oprators/oprators.php.
       فالحكمُ الآن: إن لم يبقَ في الخليةِ بعد نزعِ الوسومِ ومحدّداتِ PHP
       والاقتباساتِ إلا اسمُ عدّادٍ وحدَه، فهي عدّادٌ لا بيان. */
    $core = preg_replace('/<[^>]*>/u', '', $body);
    $core = preg_replace('/<\?(php|=)?|\?>/', '', $core);
    $core = preg_replace('/\b(echo|print)\b/', '', $core);
    $core = str_replace(['"', "'", '.', ';', ' ', "\n", "\t", "\r"], '', $core);
    if (preg_match('/^\$(i|idx|index|n|no|num|counter|count|serial|seq|sn|rownum|row_no)(\+\+)?$/i', $core)) return true;

    return false;
}

/**
 * هل الرأسُ محقونٌ من طبقةِ الحوكمة/الحقولِ (لا خليةَ له في المصدر)؟
 *
 * طبقتان تُلحقان رؤوسًا بلا خلايا، و`ui-unification.js` يحشو خلاياهما وقتَ
 * التشغيل: أعمدةُ الحوكمة (`ems-gov-th`/`data-gov`) وأعمدةُ الحقولِ الوظيفية
 * (`ems-fn-th`/`data-fn`). وقد قِيس ذلك لا افتُرض: في penalties/roles/types
 * كان عددُ خلايا الصفِّ يساوي عددَ الرؤوسِ العاريةِ بالضبط (12 و7 و5).
 * وعدُّ هذه الرؤوسِ خلايا يزيح عمودَ الإجراءاتِ إلى موضعٍ لا خليةَ فيه.
 */
function acl_is_injected_head($src, $c)
{
    $open = substr($src, $c['s'], $c['os'] - $c['s']);
    foreach (['ems-gov-th', 'data-gov', 'ems-fn-th', 'data-fn'] as $mark) {
        if (stripos($open, $mark) !== false) return true;
    }
    return false;
}
