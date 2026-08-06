<?php
/**
 * tools/e01_contract_sweep.php — مسح العقد الخماسي على كل فعلٍ ذي أثرٍ مالي (AC-E01-03)
 * ───────────────────────────────────────────────────────────────────────────
 * «يُكتب · يُنشر · يُستهلك · يتغير · يُعكس» — العقد يتحقق بنيويًّا حين تمر كل
 * كتابة أثرٍ ماليٍّ بالناشر الموحد (EventPublisher/publishFact ⇒ الجذر
 * المحايد + fin_event_links عطالةً + المروحة استهلاكًا + العكس بمرجع).
 * الخرق = INSERT مباشر في جداول الأثر (fin_financial_events · fin_ledger_entries
 * · fin_dues) خارج طبقة الناشر — يُمسح الكود الحي ويُسمى كل موضع.
 * المستثنى معلَن: طبقة الناشر نفسها · الهجرات والبذور والاختبارات والأدوات.
 * التشغيل: php tools/e01_contract_sweep.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$ROOT = dirname(__DIR__);

$TARGETS = array('fin_financial_events', 'fin_ledger_entries', 'fin_dues', 'fin_event_links');
/* طبقة الناشر المخولة بالكتابة المباشرة */
$PUBLISHER_LAYER = array(
    'app/Services/EventPublisher.php', 'app/Services/EffectFanout.php',
    'app/Services/Finance/', 'app/Core/',
    // امتداد المروحة ④-٣ (فُحص يدويًّا 2026-08-06): يكتب أثر الالتزام بعطالة
    // fin_event_links على جذر contract.signed القائم — عين عمل EffectFanout
    'app/Services/Contract/ContractSignedEffects.php',
);
$SKIP_PREFIX = array('tools/', 'tests/', 'database/', 'docs/', '.claude/', 'storage/', 'vendor/');

$viol = array(); $scanned = 0;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    $skip = false;
    foreach ($SKIP_PREFIX as $p) { if (strpos($rel, $p) === 0) { $skip = true; break; } }
    foreach ($PUBLISHER_LAYER as $p) { if (strpos($rel, $p) === 0) { $skip = true; break; } }
    if ($skip) { continue; }
    $src = (string) file_get_contents($f->getPathname());
    $scanned++;
    foreach ($TARGETS as $t) {
        if (preg_match('/INSERT\s+(IGNORE\s+)?INTO\s+`?' . $t . '`?/i', $src, $m, PREG_OFFSET_CAPTURE)) {
            $line = substr_count(substr($src, 0, $m[0][1]), "\n") + 1;
            $viol[] = "{$rel}:{$line} — كتابة مباشرة في {$t}";
        }
    }
}
fwrite(STDOUT, "مُسح: {$scanned} ملفًا حيًّا\n");
if (!$viol) {
    fwrite(STDOUT, "✔ العقد الخماسي بنيوي: صفر كتابة أثرٍ ماليٍّ خارج طبقة الناشر\n");
    exit(0);
}
fwrite(STDOUT, "✘ خروق العقد (" . count($viol) . "):\n");
foreach ($viol as $v) { fwrite(STDOUT, "  · {$v}\n"); }
exit(1);
