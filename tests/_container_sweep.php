<?php
/**
 * tests/_container_sweep.php — كنسُ حاوياتِ فاحصٍ **بسلسلةِ مُشيريها كاملةً**
 * ═══════════════════════════════════════════════════════════════════════════
 * **العطبُ المشترَكُ الذي يعالجه هذا الملف** (مقيسٌ في ثلاثةِ فواحصَ · 104 صفًّا
 * باقيًا في القاعدة):
 *
 * `op_containers` يشير إليه **اثنا عشرَ مفتاحًا**، وأحدُها ذاتيٌّ
 * (`fk_container_parent` على `parent_id`) — فحذفُ أبٍ قبلَ ابنِه يُردّ. و
 * `config.php` يضبط mysqli على **عدم الرمي**، فيُقرأ الردُّ نجاحًا: يبقى الصفُّ
 * ويظنُّ الفاحصُ أنه كنَس.
 *
 * والسلسلةُ **ثلاثُ طبقاتٍ** لا طبقةٌ واحدة — قِيست واحدةً واحدةً:
 *   `coverage_settlement_lines` (`fk_csl_cov`)
 *        ⟶ `substitute_coverages` (`fk_cov_seat` على `covered_seat_id`،
 *           **وهو NOT NULL فلا يُصفَّر**)
 *              ⟶ `op_containers`
 * ومَن حذف الطبقةَ الوسطى وحدَها رُدَّ بالعُليا، ومَن حذف الحاويةَ رُدَّ بالوسطى.
 *
 * وثلاثةُ أخطاءٍ أخرى تُصحَّح هنا مرةً واحدةً لا في كلِّ فاحص:
 *   ① **الكنسُ بعائلةِ الوسمِ لا بوسمِ الشوط**: `getmypid()` يجعل كلَّ شوطٍ أعمى
 *      عمّا تركه سابقُه — فإن أخفق كنسُ شوطٍ بقيت صفوفُه إلى الأبد.
 *   ② **الأبناءُ قبلَ الآباءِ** بترتيبِ المستوى.
 *   ③ **فحصُ المُرجَعِ** لكلِّ حذفٍ، والإبلاغُ عند الإخفاق بدل ابتلاعِه.
 *
 * الاستعمال في الفاحص:
 *     require_once __DIR__ . '/_container_sweep.php';
 *     ems_sweep_container_family($conn, 'M18T%');     // عائلةُ الوسم
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_sweep_container_family')) {
    /**
     * يكنس كلَّ حاويةٍ رقمُها يطابق النمطَ — بسلسلةِ مُشيريها وبترتيبٍ سليم.
     *
     * @param mysqli $conn    وصلةُ الفاحص
     * @param string $like    نمطُ `container_no` (مثال: 'M18T%' · 'H05T%')
     * @param bool   $verbose يُبلّغ عن كلِّ إخفاقٍ على STDERR
     * @return array ['deleted' => int, 'failed' => array<string>]
     */
    function ems_sweep_container_family($conn, $like, $verbose = true)
    {
        $out = array('deleted' => 0, 'failed' => array());
        $pat = $conn->real_escape_string((string) $like);

        /* المرشَّحون — يُثبَّتون أوّلًا فلا يتغيّر الجمهورُ أثناء الكنس */
        $ids = array();
        $r = $conn->query("SELECT id FROM op_containers WHERE container_no LIKE '{$pat}'");
        while ($r && $row = $r->fetch_assoc()) { $ids[] = (int) $row['id']; }
        if (!$ids) { return $out; }
        $in = implode(',', $ids);

        /* ① الطبقةُ العُليا: سطورُ تسويةِ التغطية تشير إلى التغطية */
        $steps = array(
            "DELETE csl FROM coverage_settlement_lines csl
               JOIN substitute_coverages sc ON sc.cov_id = csl.cov_id
              WHERE sc.covered_seat_id IN ({$in})",
            /* ② الطبقةُ الوسطى: التغطياتُ نفسُها (`covered_seat_id` NOT NULL) */
            "DELETE FROM substitute_coverages WHERE covered_seat_id IN ({$in})",
            /* ③ بقيةُ المُشيرين المباشرين */
            "DELETE FROM container_consumption WHERE container_id IN ({$in})",
            "DELETE FROM container_swaps WHERE container_id IN ({$in}) OR to_container_id IN ({$in})",
            "DELETE FROM operator_rotations WHERE container_id IN ({$in})",
            "DELETE FROM seat_assignments WHERE container_id IN ({$in})",
            "UPDATE daily_plan_lines SET equipment_container_id = NULL
              WHERE equipment_container_id IN ({$in})",
            "UPDATE daily_plan_lines SET operator_container_id = NULL
              WHERE operator_container_id IN ({$in})",
            "UPDATE monthly_performance SET container_id = NULL WHERE container_id IN ({$in})",
        );
        foreach ($steps as $sql) {
            if ($conn->query($sql) === false) {
                $out['failed'][] = $conn->error;
                if ($verbose) { fwrite(STDERR, '  ⚠️ كنسٌ فشل: ' . $conn->error . "\n"); }
            }
        }

        /* ④ الحاوياتُ: الأبناءُ قبلَ الآباءِ — والمفتاحُ الذاتيُّ يردُّ العكس */
        foreach (array('مشغّل', 'معدة', 'نوع', 'مورد', 'رئيسية') as $lv) {
            $ok = $conn->query("DELETE FROM op_containers
                                 WHERE id IN ({$in}) AND level = '{$lv}'");
            if ($ok === false) {
                $out['failed'][] = $lv . ': ' . $conn->error;
                if ($verbose) { fwrite(STDERR, '  ⚠️ كنسُ «' . $lv . '» فشل: ' . $conn->error . "\n"); }
                continue;
            }
            $out['deleted'] += max(0, (int) $conn->affected_rows);
        }

        /* ⑤ وما بقي بلا مستوًى معروفٍ (احتياطًا لا تخمينًا) */
        $ok = $conn->query("DELETE FROM op_containers WHERE id IN ({$in})");
        if ($ok !== false) { $out['deleted'] += max(0, (int) $conn->affected_rows); }

        return $out;
    }
}
