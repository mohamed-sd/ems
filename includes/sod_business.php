<?php
/**
 * includes/sod_business.php — فصلُ الواجباتِ في **الفعلِ** لا في المنح
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0017 · INJ-0024 · INJ-0030 · INJ-0056 · INJ-0081 · INJ-0093 · INJ-0100
 *              INJ-0107 · INJ-0199
 *
 * **الفرقُ عن `includes/sod_guard.php`**: ذاك يحرس **المنحَ** (ألّا يجتمع مفتاحان
 * متعارضان في دورٍ واحد). وهذا يحرس **الفعل**: «من أدخل الساعاتِ لا يعتمدها» ·
 * «من سجّل الفاتورةَ لا يحسم فرقَها» · «لا يعتمد المرءُ ما أعدّ». وهو حكمٌ لا
 * تكفيه الصلاحيةُ: قد يملك الشخصُ المفتاحين بحقٍّ، ويبقى ممنوعًا من الجمعِ
 * بينهما **على الصفِّ نفسِه**.
 *
 * ── ولماذا مركزيًّا ──────────────────────────────────────────────────────────
 * قِيس أنَّ سبعَ شاشاتٍ يطلب نصُّ قبولِها هذا المنعَ ولا تنادي حارسًا واحدًا —
 * فكلٌّ كانت ستكتب شرطَها بنفسها، وتختلف الرسائلُ والرموزُ وتُنسى واحدةٌ.
 * فالحكمُ هنا، والشاشةُ تسأل وتُبلّغ.
 *
 * ── وثلاثةُ ضوابط ──────────────────────────────────────────────────────────
 *   ① **الصفرُ ليس فاعلًا**: عمودُ فاعلٍ قيمتُه 0 أو NULL لا يُطابِق أحدًا —
 *      وإلا مُنع كلُّ من `id` جلستِه صفرٌ (وهو حالُ المهامِّ المجدولة).
 *   ② **الرمزُ مميَّزٌ** (`SOD-403`) فيُقتبس في البلاغِ ولا يُخلط بحكمِ صلاحية.
 *   ③ **لا يرمي**: يعيد حكمًا، والشاشةُ تقرّر كيف تُبلّغ — فحارسٌ يرمي في وسطِ
 *      معاملةٍ يترك نصفَ كتابة.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_sod_same_actor')) {
    /**
     * هل الفاعلُ الحاليُّ هو نفسُه من قام بالخطوةِ السابقةِ على هذا الصف؟
     *
     * @param \mysqli $conn
     * @param string  $table      الجدول
     * @param string  $idCol      عمودُ المعرّف
     * @param int     $id         معرّفُ الصف
     * @param array   $actorCols  أعمدةُ الفاعلِ السابقِ (created_by · entered_by …)
     * @param int     $uid        الفاعلُ الحاليّ
     * @return array{same:bool, actor:int, col:string}
     */
    function ems_sod_same_actor(\mysqli $conn, $table, $idCol, $id, array $actorCols, $uid)
    {
        $out = array('same' => false, 'actor' => 0, 'col' => '');
        $uid = (int) $uid;
        $id  = (int) $id;
        if ($uid <= 0 || $id <= 0 || empty($actorCols)) { return $out; }
        if (!preg_match('~^[A-Za-z0-9_]+$~', (string) $table)
            || !preg_match('~^[A-Za-z0-9_]+$~', (string) $idCol)) { return $out; }

        $cols = array();
        foreach ($actorCols as $c) {
            if (preg_match('~^[A-Za-z0-9_]+$~', (string) $c)) { $cols[] = '`' . $c . '`'; }
        }
        if (!$cols) { return $out; }

        /* عمودٌ غيرُ موجودٍ يُسقط الاستعلامَ كلَّه — فتُسأل البنيةُ أوّلًا */
        $have = array();
        $rs = @$conn->query('SHOW COLUMNS FROM `' . $table . '`');
        while ($rs && ($x = $rs->fetch_row())) { $have[$x[0]] = true; }
        $use = array();
        foreach ($actorCols as $c) { if (isset($have[$c])) { $use[] = $c; } }
        if (!$use) { return $out; }

        $sel = array();
        foreach ($use as $c) { $sel[] = '`' . $c . '`'; }
        $sql = 'SELECT ' . implode(', ', $sel) . ' FROM `' . $table . '`
                 WHERE `' . $idCol . '` = ' . $id . ' LIMIT 1';
        $r = @$conn->query($sql);
        if (!$r || !($row = $r->fetch_assoc())) { return $out; }

        foreach ($use as $c) {
            $v = isset($row[$c]) ? (int) $row[$c] : 0;
            /* الصفرُ ليس فاعلًا */
            if ($v > 0 && $v === $uid) {
                return array('same' => true, 'actor' => $v, 'col' => $c);
            }
        }
        return $out;
    }
}

if (!function_exists('ems_sod_deny_same_actor')) {
    /**
     * حكمُ المنعِ جاهزًا للعرض. `ok=false` يعني: امنع، وأبلغ بالرمزِ والرسالة.
     *
     * @return array{ok:bool, code:string, msg:string, actor:int}
     */
    function ems_sod_deny_same_actor(\mysqli $conn, $table, $idCol, $id, array $actorCols, $uid,
                                     $what = 'هذا الإجراء')
    {
        $hit = ems_sod_same_actor($conn, $table, $idCol, $id, $actorCols, $uid);
        if (empty($hit['same'])) {
            return array('ok' => true, 'code' => '', 'msg' => '', 'actor' => 0);
        }
        return array(
            'ok'    => false,
            'code'  => 'SOD-403',
            'msg'   => 'فصلُ الواجبات: لا يجوز أن تُنفّذ ' . $what
                     . ' على صفٍّ أنت من أنشأه أو أدخله — يلزم شخصٌ آخر.',
            'actor' => (int) $hit['actor'],
        );
    }
}
