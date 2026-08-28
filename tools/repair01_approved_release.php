<?php
/**
 * tools/repair01_approved_release.php — إذنُ خروجِ قرارٍ من مجموعةِ المعتمَد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القاعدةُ التي تحرسها `G0-02`**: *«قرارٌ اعتُمد مرّةً لا يعود منتظِرًا»* —
 *   سقّاطةُ **مجموعةٍ** لا عدّ، فقلبُ معتمَدٍ إلى منتظِرٍ بعد اعتمادِ آخرَ لا
 *   يمرُّ بحيلةِ العدد.
 *
 * ◆ **والثغرةُ التي ظهرت**: السقّاطةُ لا تفرّق بين **ارتدادٍ صامت** و**خروجٍ
 *   أمر به المالكُ في حزمةٍ اعتمدها**. فحين أعادت الحزمةُ المحدَّثةُ
 *   `DEC-OPEN-15` منتظِرًا، سقط `G0-02` على قرارِ المالكِ نفسِه.
 *   ⇒ فالخروجُ يُقبَل **بإذنٍ مكتوب**: سببٌ ومرجعٌ وتاريخ. ⛔ **وبلا إذنٍ يبقى
 *   ارتدادًا يُسقِط الحاجب** — والحاجبُ لم يُخفَّف، بل صار يفرّق.
 *
 * ⛔ **ولماذا أداةٌ منفصلةٌ لا سطرٌ في الحاجب**: حاجبٌ يكتب إذنَ نفسِه ليس
 *   حاجبًا. فالإذنُ يُكتب من خارجِه، بيدٍ تُبدي سببَها، ويُقرأ الحاجبُ ولا يكتب.
 *
 * التشغيل:
 *   php tools/repair01_approved_release.php --list
 *   php tools/repair01_approved_release.php --id=DEC-OPEN-15 --why="…" --ref="…" [--apply]
 *   php tools/repair01_approved_release.php --revoke=DEC-OPEN-15 [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$F = $ROOT . '/docs/REPAIR01_20260823/evidence/approved_baseline.json';
$APPLY = in_array('--apply', $argv, true);
$arg = function ($k) use ($argv) {
    foreach ($argv as $a) { if (strpos($a, "--$k=") === 0) { return substr($a, strlen($k) + 3); } }
    return null;
};
if (!is_file($F)) { exit("⛔ لا خطَّ أساسٍ بعد: $F\n"); }
$j = json_decode((string) file_get_contents($F), true);
if (!is_array($j) || !isset($j['approved'])) { exit("⛔ خطُّ الأساسِ غيرُ مقروء\n"); }
if (!isset($j['released']) || !is_array($j['released'])) { $j['released'] = array(); }

$save = function () use (&$j, $F) {
    $j['count'] = count($j['approved']);
    file_put_contents($F, json_encode($j, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
};

if (in_array('--list', $argv, true)) {
    printf("\nمعتمَدٌ في خطِّ الأساس: %d · أذونُ خروجٍ مكتوبة: %d\n\n",
        count($j['approved']), count($j['released']));
    foreach ($j['released'] as $id => $r) {
        printf("  %-16s %s\n     المرجع: %s · التاريخ: %s\n", $id,
            (string) ($r['why'] ?? ''), (string) ($r['ref'] ?? ''), (string) ($r['at'] ?? ''));
    }
    echo "\n";
    exit(0);
}

$revoke = $arg('revoke');
if ($revoke !== null) {
    if (!isset($j['released'][$revoke])) { exit("◆ لا إذنَ لـ$revoke\n"); }
    if (!$APPLY) { exit("◆ سيُسحَب إذنُ $revoke — أضِفْ `--apply`\n"); }
    unset($j['released'][$revoke]);
    $save();
    exit("✔ سُحب الإذنُ عن $revoke — وعودتُه إلى المنتظِرِ تصير ارتدادًا يُسقِط\n");
}

$id  = (string) $arg('id');
$why = (string) $arg('why');
$ref = (string) $arg('ref');
if (trim($id) === '' || trim($why) === '' || trim($ref) === '') {
    exit("⛔ **لا إذنَ بلا سببٍ ومرجع** — `--id=… --why=\"…\" --ref=\"…\"`\n");
}
if (!in_array($id, $j['approved'], true)) {
    exit("⛔ $id ليس في خطِّ الأساسِ المعتمَد — فلا خروجَ له يُؤذَن فيه\n");
}
printf("◆ إذنُ خروج: %s\n   السبب: %s\n   المرجع: %s\n", $id, $why, $ref);
if (!$APPLY) { exit("\n◆ تجربةٌ بلا كتابة — أضِفْ `--apply`\n"); }
$j['released'][$id] = array('why' => $why, 'ref' => $ref, 'at' => date('Y-m-d H:i:s'));
$save();
echo "\n✔ كُتب الإذن — و`G0-02` يقرؤه ولا يكتبه.\n";
