<?php
/**
 * tools/baseline_field_extract.php — BL-20260821: استخراج حقول الشاشات استخراجًا ساكنًا
 * قراءة فقط. لكل شاشة مصنَّفة SCREEN في disk_surfaces.json:
 *   · أعمدة الجداول: <thead><th class="group-x">التسمية</th> ثم محاذاة موضعية مع
 *     تعابير $row['key'] داخل <tbody> — عند اختلال المحاذاة يُكتب NEEDS_REVIEW لا تخمين.
 *   · حقول النماذج: input/select/textarea باسمها التقني ونوعها وإلزامها وقسمها.
 * الإخراج: docs/baseline_20260821/extract/fields_by_screen.json + ملخص أعداد.
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$OUT = $ROOT . '/docs/baseline_20260821/extract';
$surfaces = json_decode((string) file_get_contents($OUT . '/disk_surfaces.json'), true);

function clean_label($s)
{
    $s = strip_tags($s);
    $s = preg_replace('/<\?php.*?\?>/s', '', $s);
    $s = preg_replace('/\s+/u', ' ', trim($s));
    return $s;
}

$all = array();
$totF = 0; $totC = 0; $screens = 0;
foreach ($surfaces as $sf) {
    if ($sf['class'] !== 'SCREEN') { continue; }
    $rel = $sf['path'];
    $src = (string) file_get_contents($ROOT . '/' . $rel);
    /* ◆ **تمديدُ NF-11 بشقِّه الأول المنصوص** (CLOSURE_SYSTEM · WORK-05):
       شاشةٌ تصييرُها كلُّه في عُدّةٍ مشتركةٍ (`includes/*_kit.php` ·
       `includes/*_view.php`) لا تحمل `<thead>` ولا `<form>` في ملفِّها —
       فتُستخرَج حقولُها من عُدّتِها منسوبةً إليها لا تُعَدُّ «صفرَ حقل»
       (الواقعةُ المرجعية: Clients/client_contacts.php ·
       Suppliers/supplier_contacts.php — عُدّةُ party_contacts). ولا يُقلَب
       تصنيفُ الماسحِ فتهتزَّ مقاماتُ بواباتٍ أخرى — المقامُ ثابت. */
    if (strpos($src, '<thead') === false && !preg_match('/<form[\s>]/i', $src)
        && preg_match_all('~includes/([a-z0-9_]+_(?:kit|view)\.php)~i', $src, $kitm)) {
        foreach (array_unique($kitm[1]) as $kitFile) {
            $kp = $ROOT . '/includes/' . $kitFile;
            if (is_file($kp)) { $src .= "\n" . (string) file_get_contents($kp); }
        }
    }
    $screens++;
    $entry = array('route' => $rel, 'tables' => array(), 'form_fields' => array());

    /* ── أعمدة الجداول ─────────────────────────────────────────────── */
    if (preg_match_all('#<thead\b.*?</thead>#is', $src, $theads, PREG_OFFSET_CAPTURE)) {
        foreach ($theads[0] as $ti => $th) {
            $block = $th[0];
            $after = substr($src, $th[1] + strlen($block));
            $cols = array();
            if (preg_match_all('#<th\b([^>]*)>(.*?)</th>#is', $block, $ths, PREG_SET_ORDER)) {
                foreach ($ths as $one) {
                    $cls = '';
                    if (preg_match('/class="([^"]*)"/i', $one[1], $cm)) { $cls = $cm[1]; }
                    $grp = '';
                    if (preg_match('/group-([a-z0-9_-]+)/i', $cls, $gm)) { $grp = $gm[1]; }
                    $hiddenDefault = (strpos($one[1], 'data-col-group-default="hidden"') !== false) ? 1 : 0;
                    $cols[] = array(
                        'label_ar' => clean_label($one[2]),
                        'col_group' => $grp,
                        'th_class' => trim($cls),
                        'hidden_default' => $hiddenDefault,
                        'technical' => null,
                    );
                }
            }
            /* محاذاة تقنية: أول <tbody> بعد هذا الرأس */
            if (preg_match('#<tbody\b.*?</tbody>#is', $after, $tb)) {
                $body = $tb[0];
                /* قصّ الجسم إلى مقاطع <td> */
                $tds = preg_split('#<td\b#i', $body);
                array_shift($tds);
                $keys = array();
                foreach ($tds as $seg) {
                    /* خلايا colspan (صفوف «لا بيانات»/تجميع) خارج المحاذاة */
                    $gt = strpos($seg, '>');
                    $attrs = ($gt !== false) ? substr($seg, 0, $gt) : '';
                    if (stripos($attrs, 'colspan') !== false) { continue; }
                    /* يقصّ عند إغلاق الخلية إن وُجد لعزل التعبير */
                    $cut = stripos($seg, '</td>');
                    if ($cut !== false) { $seg = substr($seg, 0, $cut + 200); }
                    if (preg_match('/\$[A-Za-z_][A-Za-z0-9_]*\[\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\]/', $seg, $km)) {
                        $keys[] = $km[1];
                    } else {
                        $keys[] = null;
                    }
                }
                /* صفٌّ ثانٍ مكرَّر (تفاصيل/تجميع): إن كان العدد مضاعفًا خذ الدورة الأولى */
                if (count($cols) > 0 && count($keys) > count($cols) && count($keys) % count($cols) === 0) {
                    $keys = array_slice($keys, 0, count($cols));
                }
                if (count($keys) === count($cols)) {
                    foreach ($cols as $i => &$c) { $c['technical'] = $keys[$i]; }
                    unset($c);
                } else {
                    /* اختلال المحاذاة — لا تخمين */
                    foreach ($cols as &$c) { $c['technical'] = null; }
                    unset($c);
                }
            }
            if ($cols) {
                $entry['tables'][] = array('table_index' => $ti, 'columns' => $cols);
                $totC += count($cols);
            }
        }
    }

    /* ── حقول النماذج ─────────────────────────────────────────────── */
    /* تتبّع آخر عنوان/legend قبل الحقل لتحديد القسم */
    $marks = array();
    if (preg_match_all('#<(h[1-6]|legend)\b[^>]*>(.*?)</\1>#is', $src, $hm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($hm as $m) { $marks[] = array($m[0][1], clean_label($m[2][0])); }
    }
    $sectionAt = function ($off) use ($marks) {
        $best = '';
        foreach ($marks as $m) { if ($m[0] < $off) { $best = $m[1]; } else { break; } }
        return $best;
    };
    if (preg_match_all('#<(input|select|textarea)\b([^>]*)#is', $src, $fm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        $seen = array();
        foreach ($fm as $m) {
            $tag = strtolower($m[1][0]);
            $attrs = $m[2][0];
            if (!preg_match('/name="([^"]+)"/i', $attrs, $nm)) { continue; }
            $name = $nm[1];
            $type = 'text';
            if ($tag === 'select') { $type = 'select'; }
            elseif ($tag === 'textarea') { $type = 'textarea'; }
            elseif (preg_match('/type="([^"]+)"/i', $attrs, $tm)) { $type = strtolower($tm[1]); }
            $aria = preg_match('/aria-label="([^"]*)"/iu', $attrs, $am) ? $am[1] : '';
            $req = preg_match('/\brequired\b/i', $attrs) ? 1 : 0;
            $ro  = preg_match('/\breadonly\b|\bdisabled\b/i', $attrs) ? 1 : 0;
            $key = $name . '|' . $type;
            if (isset($seen[$key])) { $seen[$key]['dup_count']++; continue; }
            $seen[$key] = array(
                'name' => $name,
                'input_type' => $type,
                'label_ar' => $aria,
                'required' => $req,
                'readonly' => $ro,
                'section' => $sectionAt($m[0][1]),
                'is_system' => in_array($name, array('csrf_token', 'id'), true) || $type === 'hidden' ? 1 : 0,
                'dup_count' => 1,
            );
        }
        $entry['form_fields'] = array_values($seen);
        $totF += count($seen);
    }
    $all[] = $entry;
}
file_put_contents($OUT . '/fields_by_screen.json', json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "screens: $screens\n";
echo "table columns: $totC\n";
echo "form fields: $totF\n";
$aligned = 0; $misaligned = 0;
foreach ($all as $e) {
    foreach ($e['tables'] as $t) {
        $hasTech = false;
        foreach ($t['columns'] as $c) { if ($c['technical'] !== null) { $hasTech = true; break; } }
        if ($hasTech) { $aligned++; } else { $misaligned++; }
    }
}
echo "tables aligned: $aligned · needs_review: $misaligned\n";
