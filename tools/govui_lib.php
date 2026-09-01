<?php
/**
 * tools/govui_lib.php — قارئُ البطاقةِ الكاملِ لجولةِ `GOV_UI_EXEC`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لِمَ امتدادٌ للقارئ؟** `rpr02a_read_cards` يقرأ خمسَ خاناتٍ (المجموعة ·
 *   الاسم · النوع · المالك · الحبة) — وهذه الجولةُ تحتاج **صفَّ الحقولِ
 *   بأصنافِه** و**مصدرَ الحقيقة** و**موقعَ الشاشةِ في الدورة**. فهذا امتدادٌ
 *   **بالمرساةِ نفسِها** — لا مرساةَ ثانيةً — ويستدعي `rpr02a_nz` حرفًا
 *   فلا تطبيعان يتفرّقان [[measure-token-must-exist]].
 *
 * ◆ **والاستبعادُ واحد**: `Documentation Artifact` خارجَ السايدبار — وهو
 *   الاستبعادُ نفسُه الذي بُني به `nav_targets`، فالترتيبُ يتطابق والنسبُ يثبت.
 *
 * ◆ ⛔ **والمعرِّفُ لا يُشتقُّ من الاسم**: هويّةُ الهدفِ `(مساحة, ترتيب)` —
 *   فإعادةُ التسميةِ لا تكسر نسبًا (§4 · `TARGET_LINEAGE_BROKEN_BY_RENAME=0`).
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/lib/rpr02a_guide.php';

/** أوراقُ ملفِّ القيادةِ ⇒ مساحاتُها التنفيذيّة (§13: القيادةُ ليست إدارةً) */
function govui_ex_remap()
{
    return array('DEP-01' => 'EX-CEO', 'DEP-02' => 'EX-DVP');
}

/** تطبيعُ اسمِ مجموعةٍ — حاشيةُ `(Overview)` نُزعت بقرارِ FINAL_CLOSE ⑰ @52f4fe37 */
function govui_gz($s)
{
    $s = rpr02a_nz($s);
    $s = strtr($s, array("\xE2\x80\x93" => "\xE2\x80\x94", "-" => "\xE2\x80\x94"));
    $s = preg_replace('~\s*\([A-Za-z ]+\)\s*$~u', '', $s);
    return trim($s);
}

/**
 * `EFFECTIVE_CANONICAL_LABEL` — الاسمُ الحاكمُ بعدَ نزعِ **حاشيةِ الانطباق**.
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الشاهدُ على أنّها حاشيةٌ لا اسم**: العبارةُ «— بحسب انطباق الشركة» ترد
 *   **حرفًا واحدًا في سبعٍ وأربعين بطاقةً** عبرَ ستِّ إداراتٍ مختلفة — فهي
 *   وسمُ شرطِ انطباقٍ على الشاشة، لا جزءٌ من مسمّاها المهنيّ. ولو كانت اسمًا
 *   لَما تطابقت في سبعٍ وأربعين سطحًا لا يجمعها معنى.
 * ◆ **ولا يُنزَع سواها**: «التغطية التعاقدية — دورات الالتزام» ·
 *   «قرار فتح مشروع — ميثاق المشروع» · «الإدارات — نطاقي والشركة» أسماءٌ
 *   بشرطتِها، فتبقى كما هي. **والقاعدةُ مفردةٌ واحدةٌ مسمّاةٌ لا نمطٌ عامّ.**
 */
function govui_effective_label($name)
{
    $s = preg_replace('~\s*—\s*بحسب انطباق الشركة\s*$~u', '', (string) $name);
    return trim($s);
}

/** نزعُ وسمِ الاصطلاحِ من اسمِ حقلٍ: `◄` قراءةٌ · `▼` قائمةٌ محكومة */
function govui_field_clean($s)
{
    $s = str_replace(array('◄', '▼', '◂', '▾'), ' ', (string) $s);
    $s = preg_replace('~\s*—\s*تفصيلها.*$~u', '', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
}

/**
 * قراءةُ بطاقاتِ ملفٍّ حاكمٍ كاملةً.
 * @param string $xlsxPath المسار
 * @param array|null $remap ورقة⇒مساحة (لملفِّ القيادة)
 * @return array بطاقاتٌ بخاناتِها الاثنتَي عشرة
 */
function govui_read_cards($xlsxPath, $remap = null)
{
    $WB = xlsx_read($xlsxPath);
    $out = array();
    foreach ($WB as $sheet => $rows) {
        $code = rpr02a_sheet_code($sheet);
        if ($code === '') { continue; }
        if (preg_match('/^(98|99|00)_/u', $sheet)) { continue; }
        $ws = $code;
        if (is_array($remap)) {
            if (!isset($remap[$code])) { continue; }
            $ws = $remap[$code];
        }
        ksort($rows);
        $cur = null;
        foreach ($rows as $ri => $r) {
            ksort($r);
            $c0 = isset($r[0]) ? trim((string) $r[0]) : '';
            $c2 = isset($r[2]) ? trim((string) $r[2]) : '';
            if (preg_match('/^■\s*الشاشة\s+(\d+)\s+من\s+(\d+)\s*·\s*\[(.*?)\]\s*·\s*(.+)$/u', $c0, $m)) {
                if ($cur) { $out[] = $cur; }
                $cur = array(
                    'sheet' => $sheet, 'code' => $code, 'ws' => $ws, 'row' => $ri + 1,
                    'idx' => (int) $m[1], 'total' => (int) $m[2],
                    'group_raw' => trim($m[3]), 'group' => govui_gz($m[3]),
                    'name_raw' => govui_effective_label(trim($m[4])),
                    'name' => rpr02a_nz(govui_effective_label($m[4])),
                    'name_card' => trim($m[4]),
                    'type' => '', 'owner' => '', 'grain' => '', 'sot' => '',
                    'purpose' => '', 'cycle' => '', 'decl_fields' => 0,
                    'fields' => array(), 'lists' => '', '_fr' => 0,
                );
                continue;
            }
            if (!$cur) { continue; }
            if ($c0 === 'الغرض' && $cur['purpose'] === '') { $cur['purpose'] = $c2; continue; }
            if ($c0 === 'موقعها في الدورة' && $cur['cycle'] === '') { $cur['cycle'] = $c2; continue; }
            if ($c0 === 'Grain — السطر الواحد' && $cur['grain'] === '') { $cur['grain'] = $c2; continue; }
            if (strpos($c0, 'مصدر الحقيقة') === 0 && strpos($c0, 'المالك') !== false) {
                if (preg_match('/نوع الشاشة\s*:\s*(.+?)\s*$/u', $c2, $mm)) { $cur['type'] = trim($mm[1]); }
                if (preg_match('/الإدارة المالكة\s*:\s*(.+?)\s*·/u', $c2, $mm)) { $cur['owner'] = trim($mm[1]); }
                if (preg_match('/مصدر الحقيقة\s*:\s*(.+?)(?:\s*·\s*الإدارة المالكة|$)/u', $c2, $mm)) { $cur['sot'] = trim($mm[1]); }
                continue;
            }
            if (strpos($c0, 'عدد الحقول (مقيس)') === 0) {
                if (preg_match('/(\d+)/u', $c2, $mm)) { $cur['decl_fields'] = (int) $mm[1]; }
                $cur['_fr'] = 1;             /* الصفوفُ الثلاثةُ التاليةُ: الأسماء · الأصناف · القواعد */
                continue;
            }
            if (strpos($c0, 'القوائم المحكومة') === 0) { $cur['lists'] = $c0; continue; }
            if (strpos($c0, 'المراجعة الرباعية') === 0) { continue; }
            if ($cur['_fr'] >= 1 && $cur['_fr'] <= 3 && count($r) >= 1) {
                $mx = max(array_keys($r)); $vals = array();
                for ($i = 0; $i <= $mx; $i++) { $vals[$i] = isset($r[$i]) ? trim((string) $r[$i]) : ''; }
                if ($cur['_fr'] === 1) {
                    foreach ($vals as $i => $v) {
                        if ($v === '') { continue; }
                        $cur['fields'][$i] = array('i' => $i + 1, 'raw' => $v,
                            'name' => govui_field_clean($v), 'norm' => rpr02a_nz(govui_field_clean($v)),
                            'class' => '', 'rule' => '');
                    }
                    $cur['_fr'] = 2;
                } elseif ($cur['_fr'] === 2) {
                    foreach ($vals as $i => $v) {
                        if (isset($cur['fields'][$i])) { $cur['fields'][$i]['class'] = trim(str_replace(array('◄', '▼'), '', $v)); }
                    }
                    $cur['_fr'] = 3;
                } else {
                    foreach ($vals as $i => $v) {
                        if (isset($cur['fields'][$i])) { $cur['fields'][$i]['rule'] = $v; }
                    }
                    $cur['_fr'] = 4;
                }
                continue;
            }
        }
        if ($cur) { $out[] = $cur; }
    }
    foreach ($out as $k => $c) { unset($out[$k]['_fr']); $out[$k]['fields'] = array_values($c['fields']); }
    return array_values($out);
}

/**
 * كونُ الأهدافِ الحاكمِ من الملفَّين — مرتَّبًا بالمساحةِ وبترتيبِ الورقة.
 * @return array ws ⇒ [ بطاقةٌ + order ] — والاستبعادُ `Documentation Artifact` وحدَه
 */
function govui_target_cards($ROOT)
{
    $all = array();
    foreach (govui_read_cards($ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx') as $c) { $all[] = $c; }
    foreach (govui_read_cards($ROOT . '/docs/REPAIR01_20260823/02 · القيادة.xlsx', govui_ex_remap()) as $c) { $all[] = $c; }
    $by = array();
    foreach ($all as $c) {
        if (rpr02a_is_doc($c)) { continue; }
        $by[$c['ws']][] = $c;
    }
    foreach ($by as $ws => $list) {
        usort($list, function ($a, $b) { return $a['idx'] <=> $b['idx']; });
        $n = 0;
        foreach ($list as $k => $c) { $list[$k]['order'] = ++$n; }
        $by[$ws] = $list;
    }
    ksort($by);
    return $by;
}
