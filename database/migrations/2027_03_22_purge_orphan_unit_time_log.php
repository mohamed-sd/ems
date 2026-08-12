<?php
/**
 * 2027_03_22 — سطورُ زمنٍ يتيمةٌ تُزال، وأثرُها في الطاقةِ يُقاس قبلَ وبعد
 * ═══════════════════════════════════════════════════════════════════════════
 * **العطبُ المقيس**: `CapacityGuard::hoursFor()` كان يجمع `unit_time_log` بلا
 * ربطٍ بـ`unit_entries` — فسطرُ زمنٍ حُذفت واقعتُه ولم يُحذف هو يبقى محسوبًا في
 * الطاقةِ إلى الأبد. والأثرُ ليس نظريًّا:
 *   · المعدةُ 24 يومَ 2027-02-14: **واقعتان بـ22 ساعةً** — والحارسُ يعلن **198**
 *     من حدٍّ 20 (22 حيّةً + **176 يتيمةً** من 16 سطرًا ميتًا).
 *   · فعُلِّمت **50 واقعةً من 50** «تجاوزَ طاقةٍ» وهي سليمة، بدلَ الاثنتين
 *     المقصودتين — وهو ما كان يُرسب `tests/unit_reconcile_test.php`.
 * ⇒ **علمُ تجاوزٍ كاذبٌ ليس تجميلًا**: يحجب اعتمادًا ويُشوّه قياسَ الطاقةِ الذي
 *   تُبنى عليه الفوترةُ وحصصُ الموردين.
 *
 * والجذرُ **أُغلق في الكودِ** (شرطُ الواقعةِ الحيّةِ في الجمع) وهذه الهجرةُ تُزيل
 * الأثرَ القائم. وسببُ وجودِ اليتامى أصلًا: كنسٌ حذف الوقائعَ ولم يحذف سطورَ
 * زمنِها — وهو ما يمنعه اليومَ `tests/_fk_sweep.php` باشتقاقِ الأبناءِ من القاعدة.
 *
 * ◆ ولا يُحذف سطرٌ حيٌّ: الشرطُ `NOT EXISTS` على `unit_entries` حصرًا.
 * ◆ ويُقاس الأثرُ على **علمٍ بعينِه** قبلَ وبعد — فالإصلاحُ يُثبَت لا يُدَّعى.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$one = function ($sql) use ($db) {
    $r = $db->query($sql);
    if ($r === false) { fwrite(STDERR, 'استعلامٌ فشل: ' . $db->error . "\n"); return null; }
    $x = $r->fetch_row();
    return $x === null ? null : $x[0];
};
$ORPH = "NOT EXISTS (SELECT 1 FROM unit_entries e WHERE e.id = l.entry_id)";

$all = $one('SELECT COUNT(*) FROM unit_time_log');
$orp = $one("SELECT COUNT(*) FROM unit_time_log l WHERE {$ORPH}");
if ($all === null || $orp === null) { exit(1); }
echo "── ① سطورُ الزمن: {$all} · **يتيمةٌ** {$orp}\n";
if ((int) $orp === 0) { echo "── لا يتيمَ — الهجرةُ عاطلة\n"; exit(0); }

/* ── ② قياسُ الأثرِ على شاهدٍ بعينِه **قبلَ** الإزالة ─────────────────────────── */
$probe = $db->query("SELECT l.equipment_id eq, l.log_date d,
                            COALESCE(SUM(l.hours),0) total,
                            COALESCE(SUM(CASE WHEN {$ORPH} THEN l.hours ELSE 0 END),0) orphan_h
                       FROM unit_time_log l
                      WHERE l.equipment_id IS NOT NULL
                      GROUP BY l.equipment_id, l.log_date
                     HAVING orphan_h > 0
                      ORDER BY orphan_h DESC LIMIT 1");
$w = ($probe && $probe->num_rows) ? $probe->fetch_assoc() : null;
if ($w !== null) {
    printf("── ② أثقلُ شاهدٍ: معدةٌ %s يومَ %s ⇒ مقيسٌ %s ساعةً · منها **%s يتيمةٌ**\n",
           $w['eq'], $w['d'], $w['total'], $w['orphan_h']);
}

/* ── ③ الإزالةُ بفحصِ المُرجَعِ والعدِّ قبلَ وبعد ────────────────────────────── */
$ok = $db->query("DELETE l FROM unit_time_log l WHERE {$ORPH}");
if ($ok === false) { fwrite(STDERR, 'حذفٌ فشل: ' . $db->error . "\n"); exit(1); }
$gone = $db->affected_rows;
$left = $one("SELECT COUNT(*) FROM unit_time_log l WHERE {$ORPH}");
$after = $one('SELECT COUNT(*) FROM unit_time_log');
printf("── ③ حُذف %d من %d · باقٍ يتيمٌ %s · وسطورٌ حيّةٌ %s\n", $gone, $orp, $left, $after);
if ((int) $gone !== (int) $orp || (int) $left !== 0) {
    fwrite(STDERR, "خلافُ المتوقَّع — راجِع قبل الالتزام\n"); exit(1);
}

/* ── ④ الأثرُ **بعدَ** الإزالةِ على الشاهدِ نفسِه ──────────────────────────── */
if ($w !== null) {
    $now = $one("SELECT COALESCE(SUM(l.hours),0) FROM unit_time_log l
                  WHERE l.equipment_id = " . (int) $w['eq']
                . " AND l.log_date = '" . $db->real_escape_string($w['d']) . "'");
    printf("── ④ الشاهدُ نفسُه الآن: %s ساعةً (كان %s) ⇒ نزل %s\n",
           $now, $w['total'], round((float) $w['total'] - (float) $now, 2));
    if ((float) $now >= (float) $w['total']) {
        fwrite(STDERR, "لم ينخفض المقيسُ — الإزالةُ بلا أثرٍ فلا تُصدَّق\n"); exit(1);
    }
}

/* ── ⑤ وأعلامُ الطاقةِ الكاذبةُ تُرفع لتُعاد بالحسابِ الصحيح ──────────────────
     ◆ العلمُ **مشتقٌّ** لا واقعةٌ أصلية: يُعاد بناؤه من الحدودِ عند أوّلِ حفظٍ أو
       جسٍّ. فتُرفع الأعلامُ المبنيةُ على المقياسِ المُفسَدِ بدلَ أن تبقى تُقرأ
       تجاوزًا. ولا يُرفع علمٌ **مُخلَّصٌ** (`cleared_at`) — فذاك قرارُ إنسانٍ لا
       مشتقٌّ، ومحوُه يمحو أثرَ من خلّصه. */
$flagsAll = $one('SELECT COUNT(*) FROM unit_capacity_flags');
$ok = $db->query("DELETE FROM unit_capacity_flags WHERE cleared_at IS NULL");
if ($ok === false) { fwrite(STDERR, 'رفعُ الأعلامِ فشل: ' . $db->error . "\n"); exit(1); }
$flagsGone = $db->affected_rows;
$flagsLeft = $one('SELECT COUNT(*) FROM unit_capacity_flags');
printf("── ⑤ أعلامٌ كانت %s · رُفع غيرُ المخلَّصِ %d · باقٍ (مخلَّصٌ) %s\n",
       $flagsAll, $flagsGone, $flagsLeft);

echo "\n✅ اليتامى أُزيلوا وأثرُهم مقيسٌ — والجذرُ مُغلقٌ في CapacityGuard (شرطُ الواقعةِ الحيّة).\n";
exit(0);
