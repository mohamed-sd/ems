<?php
/**
 * tools/lib/repair01_w00_anchor.php — قارئُ مرساةِ الطورِ صفر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا دالّةٌ لا ثابت**: اثنا عشرَ حاجبًا كانوا يكتبون المقامَ حرفيًّا
 *   (`$dec === 108 && … $base === 651`)، فحين انتقل المقامُ **بأمرِ المالكِ
 *   باعتمادِ حزمةٍ محدَّثة** سقط الاثنا عشرَ دفعةً واحدةً بلا عطبٍ واحد.
 *   ⇒ المرساةُ صارت صفًّا مسجَّلًا في `repair01_w00_anchor` يحمل قيمتَه
 *   واستعلامَ قياسِه ومرجعَ حزمتِه وسببَه — **والحاجبُ يقرأ ولا يحفظ**.
 *
 * ⛔ **والمقامُ المجهولُ يُوقِف الحاجبَ ولا يمرُّ صفرًا**: مفتاحٌ غيرُ مرسًى
 *   يرمي استثناءً صريحًا. فـ«صفرٌ من مفردةٍ لا وجودَ لها **أخضرُ كاذب**» —
 *   وحاجبٌ يقرأ مفتاحًا مخطوءًا فيقارن بصفرٍ يمرُّ دائمًا وهو أعمى.
 *
 * الاستعمال في الحاجب:
 *     require_once __DIR__ . '/lib/repair01_w00_anchor.php';
 *     $A = w00_anchors($conn);           // كلُّ المقاماتِ دفعةً
 *     … $dec === $A['decisions'] && $sf === $A['source_files'] …
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('w00_anchors')) {
    /**
     * كلُّ المقاماتِ المرسيّةِ مفتاحًا ⇐ قيمةً. تُقرأ مرّةً وتُحفظ للنداءِ التالي.
     *
     * @param  mysqli $conn
     * @return array<string,int>
     */
    function w00_anchors($conn)
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $cache = array();
        $r = @$conn->query("SELECT metric, anchor_value FROM repair01_w00_anchor");
        if (!$r) {
            /* ⛔ **ولا يُبتلَع غيابُ الجدولِ صفرًا**: حاجبٌ يقارن بمرساةٍ غائبةٍ
                 يمرُّ دائمًا — وهو أخطرُ من حاجبٍ ساقط، لأنَّه يُقرأ أخضرَ. */
            fwrite(STDERR, "⛔ مرساةُ الطورِ صفرِ غيرُ منصوبة — شغّلْ:\n"
                . "   php database/migrations/2027_12_09_repair01_w00_anchor.php\n");
            exit(3);
        }
        while ($row = $r->fetch_assoc()) { $cache[$row['metric']] = (int) $row['anchor_value']; }
        return $cache;
    }

    /**
     * مقامٌ واحدٌ بمفتاحِه — **ويُوقِف التشغيلَ إن لم يكن مرسًى**.
     *
     * @param  mysqli $conn
     * @param  string $metric
     * @return int
     */
    function w00_anchor($conn, $metric)
    {
        $A = w00_anchors($conn);
        if (!array_key_exists($metric, $A)) {
            fwrite(STDERR, "⛔ مقامٌ غيرُ مرسًى: `$metric` — والمرساةُ المجهولةُ لا تُقرأ صفرًا.\n"
                . "   المرسيّة: " . implode(' · ', array_keys($A)) . "\n");
            exit(3);
        }
        return $A[$metric];
    }
}
