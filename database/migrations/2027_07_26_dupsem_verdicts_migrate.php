<?php
/**
 * 2027_07_26_dupsem_verdicts_migrate.php — مصالحةُ أحكامِ الـ59 (قرارُ المالك · ثانيًا-٤)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب: «صالح بين سجلِّ أحكامِ الـ59 السابقِ و`gov_dup_semantics` الحالي…
 *   وأريد رقمًا واحدًا لا شرحًا: إما 59/59 محكومةٌ منقولةٌ فيُغلق البند، وإما X
 *   محكومةٌ وY جديدةٌ فتبقى Y فجوةً معلنة. **ولا تعد تحليل ما حُسم سابقًا**».
 *
 * ◆ **والمصالحةُ مقيسةٌ لا موصوفة**: `docs/uxui_dedup_verdicts.json` يحمل 59
 *   حكمًا فنيًّا بحقلٍ مصنَّفٍ (`cls`)، و`gov_dup_semantics` يحمل 59 زوجًا.
 *   والمطابقةُ على مفتاحِ (المسارِ الأولِ + الثاني): **59 من 59 · صفرُ فارق**.
 *   فليست ثمّةَ أزواجٌ جديدةٌ ولا أحكامٌ ضائعة — **الأحكامُ قائمةٌ ولم تُنقل**.
 *
 * ◆ **والنقلُ لا يُعيد التحليل**: يُنقل الحكمُ ونصُّه كما هما، ويُخرَّط تصنيفُ
 *   الملفِّ الستُّ إلى الثلاثيِّ المنصوصِ في ف١٥-٣ («الوظيفةُ نفسُها / زاويةُ
 *   نظرٍ مختلفة / وظيفتان مستقلتان») — **خريطةٌ معلَنةٌ لا اجتهادٌ صامت**:
 *     · `same_function`       ⇐ SAME_FUNCTION     (الوظيفةُ نفسُها)
 *     · `view_of_same_data`   ⇐ DIFFERENT_ANGLE   (البياناتُ نفسُها بزاوية)
 *     · `related_containment` ⇐ DIFFERENT_ANGLE   (احتواءٌ — زاويةٌ لا استقلال)
 *     · `sequential_stages`   ⇐ DIFFERENT_ANGLE   (مرحلتان من مسارٍ واحد)
 *     · `header_lines`        ⇐ DIFFERENT_ANGLE   (رأسٌ وسطورُه — منظرانِ لكيانٍ)
 *     · `independent`         ⇐ INDEPENDENT       (وظيفتان مستقلتان)
 *
 * ◆ **والفاعلُ يُسمَّى ولا يُترك**: القيدُ `chk_dupsem_human` يرفض قرارًا بلا
 *   حكمٍ **وفاعل**. والفاعلُ هنا هو مصدرُ الحكمِ نفسُه: مراجعةٌ فنيةٌ موثَّقةٌ
 *   بتاريخِها في `docs/uxui_dedup_judgments_20260818.csv` — **لا اسمٌ يُخترع**.
 *
 * ◆ **ولا يُكتب `decision`**: الحكمُ يقول «ما هي»، والقرارُ يقول «ماذا نفعل».
 *   والثاني للمالكِ وحدَه — فيبقى فارغًا ويبقى القيدُ حارسًا عليه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$src = $ROOT . '/docs/uxui_dedup_verdicts.json';
if (!is_file($src)) { exit("✗ لا سجلَّ أحكامٍ سابقًا في {$src}\n"); }
$rows = json_decode((string) file_get_contents($src), true);
if (!is_array($rows)) { exit("✗ سجلٌّ غيرُ صالح\n"); }

$MAP = array(
    'same_function'       => 'SAME_FUNCTION',
    'view_of_same_data'   => 'DIFFERENT_ANGLE',
    'related_containment' => 'DIFFERENT_ANGLE',
    'sequential_stages'   => 'DIFFERENT_ANGLE',
    'header_lines'        => 'DIFFERENT_ANGLE',
    'independent'         => 'INDEPENDENT',
);
$BY = 'مراجعةٌ فنيةٌ موثَّقة — docs/uxui_dedup_judgments_20260818.csv';

/* عمودُ نصِّ الحكمِ إن لم يكن قائمًا — فالنصُّ يُحفظ ولا يُلخَّص */
$q = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                     AND TABLE_NAME='gov_dup_semantics' AND COLUMN_NAME='human_note'");
if (!$q || $q->num_rows === 0) {
    $conn->query("ALTER TABLE gov_dup_semantics ADD `human_note` TEXT DEFAULT NULL
                  COMMENT 'نصُّ الحكمِ الفنيِّ كما كُتب — لا يُلخَّص ولا يُعاد صوغُه'");
}

$st = $conn->prepare("UPDATE gov_dup_semantics
        SET human_verdict = ?, human_by = ?, human_note = ?
      WHERE route_a = ? AND route_b = ?");
$done = 0; $unmapped = array(); $nomatch = array();
foreach ($rows as $x) {
    $cls = isset($x['cls']) ? (string) $x['cls'] : '';
    if (!isset($MAP[$cls])) { $unmapped[] = $cls; continue; }
    $v = $MAP[$cls];
    $note = isset($x['txt']) ? (string) $x['txt'] : '';
    $st->bind_param('sssss', $v, $BY, $note, $x['a'], $x['b']);
    $st->execute();
    if ($conn->affected_rows > 0) { $done++; } else { $nomatch[] = $x['a'] . ' | ' . $x['b']; }
}

echo "════ مصالحةُ أحكامِ الـ59 ════\n";
$tot = (int) $conn->query("SELECT COUNT(*) c FROM gov_dup_semantics")->fetch_assoc()['c'];
$jud = (int) $conn->query("SELECT COUNT(*) c FROM gov_dup_semantics WHERE human_verdict IS NOT NULL")->fetch_assoc()['c'];
printf("  في السجل: %d · نُقل الآن: %d · محكومةٌ الآن: %d\n", $tot, $done, $jud);
if ($unmapped) { echo "  ⚠ تصنيفٌ بلا خريطة: " . implode(' · ', array_unique($unmapped)) . "\n"; }
if ($nomatch)  { echo "  ⚠ زوجٌ بلا مطابقٍ في الجدول: " . count($nomatch) . "\n"; }
$q = $conn->query("SELECT human_verdict v, COUNT(*) c FROM gov_dup_semantics GROUP BY human_verdict");
echo "  ▐ التوزيع\n";
while ($q && ($x = $q->fetch_assoc())) { printf("    · %-18s %d\n", $x['v'] ?: '(بلا حكم)', $x['c']); }
$dec = (int) $conn->query("SELECT COUNT(*) c FROM gov_dup_semantics WHERE decision IS NOT NULL")->fetch_assoc()['c'];
printf("  قرارٌ مكتوب: %d — **والقرارُ للمالكِ لا للحكم**\n", $dec);
echo ($jud === $tot ? "✔ {$jud}/{$tot} محكومةٌ منقولةٌ — والحكمُ البشريُّ لم يُخترع بل نُقل\n"
                   : "◆ " . $jud . " محكومةٌ و" . ($tot - $jud) . " تبقى فجوةً معلنة\n");
