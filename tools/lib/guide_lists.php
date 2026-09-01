<?php
/**
 * tools/lib/guide_lists.php — قارئُ «القوائمِ المحكومة» من الدليلِ المعماريّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **قارئٌ واحدٌ لاثنَين**: الهجرةُ التي تستوعب، والأداةُ التي تُعيد الاستيعاب.
 *   ⛔ **وقارئان يتفرّقان يعطيان مقامَين لسؤالٍ واحد** [[counter-parity-two-readers]].
 *
 * ◆ **وصفُّ الدليلِ حرفًا** — بطاقةُ كلِّ شاشةٍ قد تحمل سطرًا واحدًا بهذه الصيغة:
 *     `القوائم المحكومة (قيمها القانونية من مصدرها): النطاق ▼: مالية · تشغيلية`
 *     `  ¦  حالة السياسة ▼: مسودة · نافذة · ملغاة`
 *   فالفاصلُ بين الحقولِ `¦` وبين القيمِ `·` والاسمُ يسبق `:`.
 *   **المقيسُ حرفًا**: 372 بطاقةً، **211 منها بصفِّ قوائم**.
 *
 * ⛔ **ولا قيمةَ تُخترَع ولا تُترجَم**: تُنقَل كما هي في الورقة، فالمفردةُ التي
 *   لا يعرفها المخطَّطُ **تُبتلَع فراغًا صامتًا** إن كُتبت مغايرةً
 *   [[enum-silent-empty-write]].
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/xlsx_io.php';

if (!function_exists('ems_guide_list_norm')) {
    /** التسويةُ نفسُها التي يستعملها `w14_guide_form` — ولا تسويتان */
    function ems_guide_list_norm($s)
    {
        $s = str_replace(array('▼', '◄', '►', '▲'), '', (string) $s);
        $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
        $s = str_replace(array('ة', 'ى', 'ـ', "\xC2\xA0"), array('ه', 'ي', '', ' '), $s);
        $s = preg_replace('~[\x{064B}-\x{0652}]~u', '', $s);
        $s = preg_replace('~\s*[—–-]\s*بحسب\s+انطباق\s+الشرك[هة]\s*$~u', '', $s);
        return trim(preg_replace('~\s+~u', ' ', $s));
    }
}

if (!function_exists('ems_guide_lists_read')) {
    /**
     * يقرأ كلَّ القوائمِ المحكومةِ من مصنَّفاتِ الدليل.
     *
     * @return array صفوفٌ: surface_raw · surface_key · field_raw · field_key ·
     *               value_ar · sort_no · src_ref
     */
    function ems_guide_lists_read(array $files)
    {
        $out = array();
        foreach ($files as $fp) {
            if (!is_file($fp)) { continue; }
            $base = basename($fp);
            $WB = xlsx_read($fp);
            foreach ($WB as $sh => $rows) {
                if (preg_match('/^(98|99|00)_/u', $sh)) { continue; }   /* الفهرسُ والمصفوفةُ والمراجعة */
                ksort($rows);
                $cur = ''; $curIdx = 0;
                foreach ($rows as $ri => $r) {
                    $c0 = isset($r[0]) ? trim((string) $r[0]) : '';
                    if ($c0 === '') { continue; }
                    if (preg_match('/^■\s*الشاشة\s+(\d+)\s+من\s+(\d+)\s*·\s*\[(.*?)\]\s*·\s*(.+)$/u', $c0, $m)) {
                        $cur = trim($m[4]); $curIdx = (int) $m[1];
                        continue;
                    }
                    if ($cur === '' || mb_strpos($c0, 'القوائم المحكومة') !== 0) { continue; }
                    $body = preg_replace('~^القوائم المحكومة[^:]*:\s*~u', '', $c0);
                    foreach (preg_split('~\s*¦\s*~u', $body) as $grp) {
                        $grp = trim($grp);
                        if ($grp === '' || strpos($grp, ':') === false) { continue; }
                        list($fname, $vals) = explode(':', $grp, 2);
                        $fname = trim($fname);
                        if ($fname === '') { continue; }
                        $i = 0;
                        foreach (preg_split('~\s*·\s*~u', trim($vals)) as $v) {
                            $v = trim($v);
                            if ($v === '') { continue; }
                            $out[] = array(
                                'surface_raw' => $cur,
                                'surface_key' => ems_guide_list_norm($cur),
                                'field_raw'   => $fname,
                                'field_key'   => ems_guide_list_norm($fname),
                                'value_ar'    => $v,
                                'sort_no'     => ++$i,
                                'src_ref'     => $base . ' › ' . $sh . ' › بند ' . $curIdx . ' › ص' . ($ri + 1),
                            );
                        }
                    }
                }
            }
        }
        return $out;
    }
}
