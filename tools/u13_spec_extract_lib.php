<?php
/**
 * tools/u13_spec_extract_lib.php — دوالُّ القراءةِ المشتركةُ بين مستخرِجَي update0013
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ مستخرِجان يقرآن الوثائقَ نفسَها: `u13_spec_extract` (ما بُني عليه المحرّك)
 *   و`u13_families_extract` (كلُّ ما تعلنه الوثائق). ولو نسخ أحدُهما دوالَّ
 *   الآخرِ لانجرفا: يُصلَح `clean()` في أحدِهما ويبقى العطبُ في الثاني، فيختلف
 *   المقروءُ عن المقيس — وهو أسوأُ عطبٍ في فاحصٍ عكسي.
 */

if (!function_exists('u13_clean')) {

/**
 * تنظيفُ نصٍّ عربي.
 * ◆ گوتشا: `trim($s, "·—")` يعمل على **البايتات** لا الأحرف — فيقطع محرفًا
 *   متعددَ البايتات نصفين ويُفسد UTF-8 فيسقط `json_encode` صامتًا.
 */
function u13_clean($s)
{
    $s = str_replace(array('◆', '●', '▪'), '', (string) $s);
    $s = preg_replace('~\s+~u', ' ', $s);
    if ($s === null) { return ''; }
    $s = preg_replace('~^[\s·—–\-]+|[\s·—–\-]+$~u', '', $s);
    return $s === null ? '' : $s;
}

/** أرقامٌ عربيةُ الرسمِ إلى لاتينية. */
function u13_ar2en($s)
{
    return strtr((string) $s, array('٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                                    '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'));
}

/** كلُّ صفوفِ جداولِ Markdown في ملف. */
function u13_md_rows($file)
{
    $rows = array();
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $ln) {
        $ln = trim($ln);
        if ($ln === '' || $ln[0] !== '|') { continue; }
        if (preg_match('~^\|[\s\-\|]+\|$~u', $ln)) { continue; }
        $rows[] = array_map('trim', explode('|', trim($ln, '|')));
    }
    return $rows;
}

/**
 * المتطلباتُ الذريةُ مجموعةً بمعرّفِها الجذر.
 * ◆ البادئةُ `AC-` مستثناةٌ: جدولُ معاييرِ القبولِ لا سجلُّ المتطلبات.
 */
function u13_atoms($file, $skipAcceptance = true)
{
    $out = array();
    foreach (u13_md_rows($file) as $c) {
        if (count($c) < 2) { continue; }
        if (!preg_match('~^([A-Z][A-Za-z0-9]*-[A-Za-z]?[0-9]{2,5})(?:-([\x{0600}-\x{06FF}]{1,3}))?$~u', $c[0], $m)) { continue; }
        if ($skipAcceptance && strpos($m[1], 'AC-') === 0) { continue; }
        $root = $m[1];
        if (!isset($out[$root])) { $out[$root] = array('id' => $root, 'parts' => array(), 'test' => isset($c[2]) ? $c[2] : ''); }
        $out[$root]['parts'][] = $c[1];
    }
    foreach ($out as $k => $v) { $out[$k]['text'] = implode(' · ', $v['parts']); }
    return $out;
}

} // function_exists
