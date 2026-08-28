<?php
/**
 * tools/lib/rpr02a_guide.php — قارئُ بطاقاتِ الشاشةِ في الدليلِ المعماريّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مرساةٌ واحدةٌ لا تتكرّر**: عنوانُ البطاقة `■ الشاشة N من M · [مجموعة] · اسم`.
 * ◆ **والنوعُ يُقرأ من مفردتِه** `نوع الشاشة:` في صفِّ «مصدر الحقيقة · المالك ·
 *   النوع» — ⛔ لا من وجودِ العبارةِ في أيِّ خليّةٍ أخرى، فذلك أخضرُ كاذب.
 * ◆ **وقارئٌ واحدٌ لأداتَين**: `rpr02a_guide_cards.php` و`rpr02a_triage.php`
 *   يقرآن به — فقارئان يتفرّقان يعطيان مقامَين لسؤالٍ واحد.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/xlsx_io.php';

/** رمزُ الإدارةِ من اسمِ الورقة: `07_إدارة_الموارد_البشرية` ⇒ `DEP-07` */
function rpr02a_sheet_code($sheet)
{
    if (preg_match('/^(\d{2})_/u', $sheet, $m)) { return 'DEP-' . $m[1]; }
    if (strpos($sheet, 'WS_') === 0) { return 'WS-MY'; }
    if (strpos($sheet, 'AS_') === 0) { return 'IAF'; }
    return '';
}

/** تطبيعٌ عربيٌّ خفيفٌ — هو نفسُه المستعمَلُ في `repair01_guide_nav_gap.php` */
function rpr02a_nz($s)
{
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', (string) $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace('ى', 'ي', $s);
    $s = str_replace(array('ـ', "\xC2\xA0", 'ً', 'ٌ', 'ٍ', 'َ', 'ُ', 'ِ', 'ّ', 'ْ'),
                     array('', ' ', '', '', '', '', '', '', '', ''), $s);
    return preg_replace('~\s+~u', ' ', trim($s));
}

/** مسارٌ مطبَّعٌ للمقارنة — `nav_items.route` يخلط الصيغتَين (بلاحقةٍ وبلا) */
function rpr02a_route($s)
{
    return strtolower(preg_replace('~\.php$~i', '', ltrim((string) $s, '/')));
}

/**
 * يقرأ كلَّ بطاقاتِ الشاشةِ في الدليل.
 * @return array صفوفٌ: sheet · code · row · idx · total · group · name · type · owner · grain
 */
function rpr02a_read_cards($xlsxPath)
{
    $WB = xlsx_read($xlsxPath);
    $cards = array();
    foreach ($WB as $sheet => $rows) {
        $code = rpr02a_sheet_code($sheet);
        if ($code === '') { continue; }
        if (preg_match('/^(98|99|00)_/u', $sheet)) { continue; }   /* الفهرسُ والمصفوفةُ والمراجعة */
        ksort($rows);
        $cur = null;
        foreach ($rows as $ri => $r) {
            ksort($r);
            $c0 = isset($r[0]) ? trim((string) $r[0]) : '';
            $c2 = isset($r[2]) ? trim((string) $r[2]) : '';
            if (preg_match('/^■\s*الشاشة\s+(\d+)\s+من\s+(\d+)\s*·\s*\[(.*?)\]\s*·\s*(.+)$/u', $c0, $m)) {
                if ($cur) { $cards[] = $cur; }
                $cur = array(
                    'sheet' => $sheet, 'code' => $code, 'row' => $ri + 1,
                    'idx' => (int) $m[1], 'total' => (int) $m[2],
                    'group' => trim($m[3]), 'name' => trim($m[4]),
                    'type' => '', 'owner' => '', 'grain' => '',
                );
                continue;
            }
            if (!$cur) { continue; }
            if ($c0 === 'Grain — السطر الواحد' && $cur['grain'] === '') { $cur['grain'] = $c2; }
            if (strpos($c0, 'مصدر الحقيقة') === 0 && strpos($c0, 'المالك') !== false) {
                if (preg_match('/نوع الشاشة\s*:\s*(.+?)\s*$/u', $c2, $mm)) { $cur['type'] = trim($mm[1]); }
                if (preg_match('/الإدارة المالكة\s*:\s*(.+?)\s*·/u', $c2, $mm)) { $cur['owner'] = trim($mm[1]); }
            }
        }
        if ($cur) { $cards[] = $cur; }
    }
    return $cards;
}

/** أهو سطحُ توثيقٍ خارجَ السايدبار؟ — المفردةُ الحاكمةُ في حقلِ النوعِ وحدَه */
function rpr02a_is_doc(array $card)
{
    return stripos($card['type'], 'Documentation Artifact') !== false;
}
