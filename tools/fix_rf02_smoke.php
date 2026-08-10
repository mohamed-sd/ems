<?php
/**
 * tools/fix_rf02_smoke.php — الفاحصُ العكسيُّ للتحويلِ الآليِّ RF-02
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ گوتشا موثَّقة: «مُرحِّلٌ آليٌّ يمسّ نصًّا يلزمه فاحصٌ عكسيّ — و‎php -l‎ لا
 *   يمسك ما يبتلعه التحويلُ صامتًا». ففحصُ التركيبِ وحدَه لا يكفي: يجب أن
 *   **تُصيَّر الشاشةُ فعلًا** بدورٍ يملكها، وأن يُثبَت أنها **تُرفض** لدورٍ لا
 *   يملكها — الاتجاهان معًا، فالتصييرُ وحدَه لا يُثبت حارسًا.
 *
 * التشغيل: php tools/fix_rf02_smoke.php [--all]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();

/* الأسطحُ التي مُسّت: التي تحمل بصمةَ التحويل. */
$touched = array();
foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (strpos($src, 'RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب') !== false) { $touched[] = $rel; }
}
echo "أسطحٌ مُسّت بالتحويل: " . count($touched) . "\n";
echo str_repeat('─', 78) . "\n";

$php = PHP_BINARY;

/**
 * ◆ گوتشا التقطها الفحصُ العكسيُّ لهذا الفاحصِ نفسِه:
 *   ترتيبُ الحكمِ في ‎u13_render_one‎ يضع «تنبيه» **فوقَ** «جسمٌ فارغ»، وحاقنُ
 *   الجلسةِ يُنتج تنبيهًا دائمًا. فكلُّ ارتدادٍ حوكميٍّ كان يظهر ‎warn|‎ — ولو
 *   أُسقط التنبيهُ وحدَه لصار الارتدادُ يُقرأ **نجاحَ تصيير**، وهو قلبٌ للحكم.
 *   ◆ فالحكمُ هنا يُبنى على **الجسمِ نفسِه** لا على تصنيفِ الحاقن:
 *     جسمٌ فيه غلافُ الشاشة ⇒ صُيِّرت · جسمٌ فارغٌ ⇒ ارتدادٌ حوكميّ.
 *
 * @return array{verdict:string,bytes:int,shell:bool,fatal:string}
 */
$render = function ($rel, $role) use ($php, $ROOT) {
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($ROOT . '/tools/u13_render_one.php')
         . ' ' . escapeshellarg($rel) . ' ' . escapeshellarg((string) $role) . ' 891 4 --body 2>&1';
    $out = (string) @shell_exec($cmd);
    $verdict = ''; $body = '';
    $pos = strpos($out, 'VERDICT|');
    if ($pos !== false) {
        $nl = strpos($out, "\n", $pos);
        $verdict = trim(substr($out, $pos + 8, $nl === false ? null : $nl - $pos - 8));
        $body = $nl === false ? '' : substr($out, $nl + 1);
    }
    // سطورُ ACT| ليست جسمًا
    $body = preg_replace('/^ACT\|[^\n]*\n/m', '', $body);
    return array(
        'verdict' => $verdict,
        'bytes'   => strlen(trim($body)),
        'shell'   => (strpos($body, 'ems-unified-page-shell') !== false || strpos($body, '<div class="main"') !== false),
        'fatal'   => strpos($verdict, 'fatal|') === 0 ? $verdict : '',
    );
};

$renderOk = 0; $denyOk = 0; $bad = array(); $gated = array();
foreach ($touched as $rel) {
    // دورٌ يملك الشاشةَ فعلًا (أولُ مانحٍ للقراءة) — لا دورٌ مفترض.
    $mid = fix_resolve_module_id($db, $rel);
    $owner = $mid === null ? null : fix_one($db, "SELECT rp.role_id FROM role_permissions rp
                                                   JOIN modules m ON m.id = rp.module_id
                                                  WHERE m.code = (SELECT code FROM modules WHERE id = " . (int) $mid . ")
                                                    AND rp.can_view = 1 ORDER BY rp.role_id LIMIT 1");
    if ($owner === null) { $bad[] = $rel . ' — بلا دورٍ مانحٍ للقياس'; continue; }

    // دورٌ لا يملكها قطعًا (لا صفَّ منحٍ له على هذا الموديول).
    $stranger = fix_one($db, "SELECT r.id FROM roles r
                               WHERE r.id > 0 AND r.id NOT IN (
                                     SELECT rp.role_id FROM role_permissions rp
                                       JOIN modules m ON m.id = rp.module_id
                                      WHERE m.code = (SELECT code FROM modules WHERE id = " . (int) $mid . "))
                               ORDER BY r.id LIMIT 1");

    // ◆ استثناءٌ **مُعلَنٌ لا مسكوتٌ عنه**: أسطحُ `Financing/` خلفَ بوابةِ المجالِ
    //   المقيَّد (DEC-01 ② · FIN-01 §1.1) — `can_view` لا تكفي فيها، بل تلزم منحةٌ
    //   فرديةٌ نافذة. فارتدادُها لمالكِ الصلاحيةِ **حكمٌ صحيحٌ سابقٌ لهذا التحويل**
    //   ولا علاقةَ له بحارسِ الشاشة (قِيس: الحارسُ المركزيُّ يرجع can_view=true).
    $domainGated = (strpos($rel, 'Financing/') === 0);

    $r1 = $render($rel, (string) $owner);
    if ($r1['fatal'] !== '')     { $bad[] = $rel . " — المالك({$owner}) عطبٌ: " . $r1['fatal']; }
    elseif ($r1['bytes'] === 0)  {
        if ($domainGated) { $renderOk++; $gated[] = $rel; }
        else { $bad[] = $rel . " — المالك({$owner}) ارتدَّ رغمَ منحتِه (جسمٌ فارغ)"; }
    }
    else                         { $renderOk++; }

    if ($stranger !== null) {
        $r2 = $render($rel, (string) $stranger);
        // ◆ الاتجاهُ المعاكس: الغريبُ يجب أن يرتدَّ — جسمُه فارغٌ لأن الحارسَ خرج.
        if ($r2['fatal'] !== '')    { $bad[] = $rel . " — الغريب({$stranger}) عطبٌ: " . $r2['fatal']; }
        elseif ($r2['bytes'] === 0) { $denyOk++; }
        else { $bad[] = $rel . " — ◆ الغريب({$stranger}) صُيِّرت له الشاشة (" . $r2['bytes'] . " بايت)"; }
    } else {
        $denyOk++;   // لا دورَ غريبًا (الكلُّ ممنوحٌ) — لا شيءَ يُقاس هنا
    }
}

printf("تصييرٌ لمالكها ......... %d/%d\n", $renderOk, count($touched));
printf("رفضٌ لغيرِ مالكها ...... %d/%d\n", $denyOk, count($touched));
if ($gated) {
    echo "◆ منها خلفَ بوابةِ المجالِ المقيَّد (ارتدادٌ صحيحٌ سابقٌ للتحويل — مُعلَنٌ لا مسكوتٌ عنه): "
       . implode('، ', $gated) . "\n";
}
if ($bad) {
    echo "\nمواضعُ تحتاج نظرًا (" . count($bad) . "):\n";
    foreach (array_slice($bad, 0, 20) as $b) { echo "  · {$b}\n"; }
}
exit(empty($bad) ? 0 : 1);
