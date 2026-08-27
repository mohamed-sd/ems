<?php
namespace App\Services\Exec;

/**
 * app/Services/Exec/ExecProjectionService.php — قارئُ الإسقاطِ التنفيذيّ (RPR-W15)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): هذه الخدمةُ **تقرأ ولا تكتب حرفًا**.
 *   لا `insert` ولا `update` ولا `delete` — ⛔ **ولا نسخةَ دوريّة**. وكلُّ رقمٍ
 *   تعرضه القيادةُ يُشتقُّ **بمرجعٍ حيٍّ** من جدولِ مالكِه لحظةَ القراءة، فإن
 *   تغيّر عند المالكِ تغيّر هنا بلا مزامنة.
 *
 * ◆ **والقراءةُ عبرَ بوّابةِ العزلِ وحدَها**: الكيانُ يُحقَن (`DEC-OPEN-03`)،
 *   فلا صفَّ من كيانٍ آخرَ يظهر في لوحةِ قيادة. **ورقمٌ يخلط كيانَين بلا وسمِ
 *   تجميعٍ ادّعاءٌ لا قياس** — ولذلك `consolidated()` تَسِمُ مخرَجَها صراحةً.
 *
 * ◆ **والنطاقُ من محرّكٍ واحد**: كلُّ دالّةٍ هنا تأخذ `visibility` من
 *   `ScopeEngine` — فالنائبُ والرئيسُ يمرّان بالشيفرةِ نفسِها ويختلف النطاقُ
 *   وحدَه. ⛔ **ولا ثلاثةَ أنظمة.**
 *
 * ◆ **ولا سلطةَ هنا**: هذه الخدمةُ لا تجيب «هل يستطيع؟» — تلك
 *   `ScopeEngine::authority()`. **والرؤيةُ لا تساوي السلطة.**
 * ═══════════════════════════════════════════════════════════════════════════
 */
final class ExecProjectionService
{
    /** حدُّ الصفوفِ المقروءةِ في سطحِ قراءة — سقفُ عرضٍ لا عتبةُ قرار. */
    const READ_CAP = 500;

    /**
     * قراءةٌ حيّةٌ من جدولِ مالكِه عبرَ بوّابةِ العزل.
     * ⛔ ولا استعلامَ خامٍّ في سطحٍ من أسطحِ هذه الموجة.
     */
    public static function read($gate, $table, array $vis, array $opt = array())
    {
        $opt += array('orderBy' => 'id DESC', 'limit' => self::READ_CAP);
        $where = isset($opt['where']) ? $opt['where'] : array();
        $scopeCol = isset($opt['scope_col']) ? $opt['scope_col'] : 'project_id';
        unset($opt['scope_col']);
        $scoped = ScopeEngine::readWhere($vis, $scopeCol);
        if ($scoped) { $where = array_merge($where, $scoped); }
        if ($where) { $opt['where'] = $where; }
        try { return $gate->select($table, $opt); }
        catch (\Throwable $t) { error_log('w15 projection ' . $table . ': ' . $t->getMessage()); return array(); }
    }

    /**
     * **مروحةُ دخولٍ حيّة** — صفٌّ موحَّدٌ من عدّةِ جداولِ مُلّاكٍ بلا سجلٍّ مكرَّر.
     *
     * `$legs`: كلُّ ساقٍ `array('table','label','cols'=>array(هدف=>مصدر),'where')`
     * والناتجُ **مشتقٌّ لحظةَ القراءة** — ⛔ ولا يُخزَّن ولا يُفهرَس.
     */
    public static function fanIn($gate, array $legs, array $vis, $cap = self::READ_CAP)
    {
        $out = array();
        foreach ($legs as $leg) {
            $rows = self::read($gate, $leg['table'], $vis, array(
                'orderBy'   => isset($leg['order']) ? $leg['order'] : 'id DESC',
                'limit'     => $cap,
                'where'     => isset($leg['where']) ? $leg['where'] : array(),
                'scope_col' => isset($leg['scope_col']) ? $leg['scope_col'] : 'project_id',
            ));
            foreach ($rows as $r) {
                $item = array('source_label' => $leg['label'], 'source_table' => $leg['table']);
                foreach ($leg['cols'] as $target => $src) {
                    $item[$target] = isset($r[$src]) ? $r[$src] : '';
                }
                $item['source_row_id'] = isset($r['id']) ? (int) $r['id'] : 0;
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * رقمٌ يجمع أكثرَ من كيانٍ **موسومًا صراحةً** — وإلّا فهو ادّعاء.
     * (‏`DEC-OPEN-03` · قيدُ المالك §٥)
     */
    public static function consolidated($value, array $entityIds)
    {
        $n = count(array_unique(array_filter(array_map('intval', $entityIds))));
        return array(
            'value'        => $value,
            'entity_count' => $n,
            'consolidated' => $n > 1,
            'tag'          => $n > 1 ? 'CONSOLIDATED_PROJECTION' : 'SINGLE_ENTITY',
        );
    }

    /** عدُّ صفوفٍ عمودُها يساوي قيمةً — مقارنةٌ نصّيّةٌ لا نوعيّة. */
    public static function countBy(array $rows, $col, $val)
    {
        $n = 0;
        foreach ($rows as $r) { if (isset($r[$col]) && (string) $r[$col] === (string) $val) { $n++; } }
        return $n;
    }

    /** عددُ القيمِ المتمايزةِ في عمود. */
    public static function distinct(array $rows, $col)
    {
        $seen = array();
        foreach ($rows as $r) { if (isset($r[$col]) && (string) $r[$col] !== '') { $seen[(string) $r[$col]] = true; } }
        return count($seen);
    }
}
