<?php
/**
 * tools/owf01_measure_door410.php — قياسُ بابِ «نتيجةِ البذرِ المقيسة» (٤-١٠)
 * ═══════════════════════════════════════════════════════════════════════════
 * بابُ ٤-١٠ في وثيقةِ OWF-01 يحمل 35 متطلبًا، أوّلُها `OWF-0103-أ`:
 *   «◆ نُفِّذ البذرُ التجريبيُّ **فعلًا** لا وُثِّق»
 * واختبارُ قبولِ البابِ كلِّه: «**يطابق الملفَّ المولَّدَ بلا فرق**».
 *
 * ◆ **فهذا البابُ يُقاس ولا يُصدَّق.** وثيقةٌ تقول «مقيسٌ» ليست قياسًا — والقياسُ
 *   هنا يُقابل كلَّ رقمٍ في الوثيقةِ بالقاعدةِ الحيّةِ وبوجودِ الملفِّ المولَّد.
 * ◆ ولا يُلمَّح ولا يُخفَّف: إن خالفَ الواقعُ الوثيقةَ أُعلن الفارقُ رقمًا برقمٍ،
 *   لأنَّ بابًا يُقرأ «منجَزًا» وهو غيرُ منطبقٍ يُفسد حسابَ الإنجازِ كلَّه.
 *
 * التشغيل: php tools/owf01_measure_door410.php [--tsv=مسار-السجل]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

$tsv = $ROOT . '/docs/owf01/OWF01_register.tsv';
foreach (array_slice($argv, 1) as $a) { if (strpos($a, '--tsv=') === 0) { $tsv = substr($a, 6); } }

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = @new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$CO = 4;

$one = function ($sql) use ($db) {
    $r = $db->query($sql);
    if ($r === false) { return null; }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
};

/* ── ① الأرقامُ المُعلَنةُ في الوثيقةِ مقابلَ القاعدةِ الحيّة ─────────────────── */
$claims = array(
    array('العملاء', 15, "SELECT COUNT(*) FROM clients WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0"),
    array('عقودُ العملاء', 15, "SELECT COUNT(*) FROM contracts WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0"),
    array('الوحداتُ المتعاقدُ عليها', 118600, "SELECT COALESCE(SUM(total_contract_units),0) FROM contracts WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0"),
    array('الحاويات', 36, "SELECT COUNT(*) FROM op_containers WHERE company_id={$CO}"),
    array('الموردون', 36, "SELECT COUNT(*) FROM suppliers WHERE company_id={$CO}"),
    array('المشغّلون', 43, "SELECT COUNT(*) FROM employees WHERE company_id={$CO}"),
    array('الاستحقاقات', 128, "SELECT COUNT(*) FROM claims WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0"),
);
fwrite(STDOUT, "══ بابُ ٤-١٠ — الوثيقةُ مقابلَ القاعدةِ الحيّة\n");
printf("  %-30s %12s %12s %s\n", 'المقياس', 'الوثيقة', 'القاعدة', 'الحكم');
$same = 0; $diff = 0; $unread = 0;
$deltas = array();
foreach ($claims as $c) {
    list($label, $want, $sql) = $c;
    $got = $one($sql);
    if ($got === null) { $unread++; printf("  %-30s %12s %12s ✘ استعلامٌ فشل\n", $label, $want, '—'); continue; }
    $eq = ((string) $got === (string) $want);
    if ($eq) { $same++; } else { $diff++; $deltas[] = $label . ' (' . $want . '⇒' . $got . ')'; }
    printf("  %-30s %12s %12s %s\n", $label, number_format($want), number_format((float) $got),
           $eq ? '✔ مطابق' : '✘ مختلف');
}
fwrite(STDOUT, "  ══ مطابقٌ {$same} · مختلفٌ {$diff} · متعذّرٌ {$unread}\n");

/* ── ② «الملفُّ المولَّدُ» الذي يحتكم إليه اختبارُ القبول — أموجودٌ؟ ─────────── */
fwrite(STDOUT, "\n── الملفُّ المولَّدُ (اختبارُ القبول: «يطابق الملفَّ المولَّدَ بلا فرق»):\n");
$needles = array('118600', '118,600', '2137650', '2,137,650');
$found = array();
$dirs = array($ROOT . '/storage/reports', $ROOT . '/docs', $ROOT . '/database/seeds');
foreach ($dirs as $d) {
    if (!is_dir($d)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || $f->getSize() > 4000000) { continue; }
        $ext = strtolower($f->getExtension());
        if (!in_array($ext, array('json', 'md', 'txt', 'csv', 'php', 'tsv'), true)) { continue; }
        $body = (string) file_get_contents($f->getPathname());
        foreach ($needles as $nd) {
            if (strpos($body, $nd) !== false) { $found[] = $f->getPathname() . ' ⟵ ' . $nd; break; }
        }
    }
}
if ($found) {
    foreach ($found as $x) { fwrite(STDOUT, '   ✔ ' . $x . "\n"); }
} else {
    fwrite(STDOUT, "   ✘ **لا ملفَّ مولَّدًا يحمل أرقامَ الوثيقة** في storage/reports ولا docs ولا database/seeds\n");
}

/* ── ③ الحكمُ المكتوبُ في السجلِّ — ولا يُلمَّح ولا يُخفَّف ────────────────────── */
$verdict = ($diff === 0 && $found)
    ? 'مُحقَّق'
    : 'لا ينطبق على هذه القاعدة';
$evidence = $diff === 0
    ? 'كلُّ الأرقامِ مطابقة'
    : 'فوارقُ مقيسة: ' . implode(' · ', $deltas)
      . ($found ? '' : ' · ولا ملفَّ مولَّدًا يحمل أرقامَ الوثيقة');

if (!is_file($tsv)) { fwrite(STDERR, "\nلا سجلَّ في {$tsv} — شغّل owf01_extract_register.php أولًا\n"); exit(1); }
$lines = file($tsv, FILE_IGNORE_NEW_LINES);
$out = array(); $touched = 0;
foreach ($lines as $i => $l) {
    if ($i === 0) { $out[] = $l; continue; }
    $c = explode("\t", $l);
    if (count($c) < 7) { $out[] = $l; continue; }
    if (trim($c[1]) === '٤-١٠') {
        $c[5] = $verdict;
        $c[6] = $evidence;
        $touched++;
    }
    $out[] = implode("\t", $c);
}
file_put_contents($tsv, implode("\n", $out) . "\n");
fwrite(STDOUT, "\n── وُسم في السجلِّ: {$touched} متطلبًا من بابِ ٤-١٠ ⇒ «{$verdict}»\n");
fwrite(STDOUT, '   الدليلُ: ' . $evidence . "\n");

if ($verdict !== 'مُحقَّق') {
    fwrite(STDOUT, "\n⚠ **بابُ ٤-١٠ يصف بذرًا لم يُنفَّذ في هذه البيئة.** والقاعدةُ أكبرُ لا أصغر\n"
                 . "  (بياناتٌ متراكمةٌ من جولاتٍ سابقة). فمواصفةُ البذرِ في بابِ ٤-١١ **قابلةٌ**\n"
                 . "  للتنفيذِ، وبابُ ٤-١٠ يصير قابلًا للقياسِ بعدَه — لا قبلَه.\n");
    exit(0);
}
fwrite(STDOUT, "\n✅ بابُ ٤-١٠ مُحقَّقٌ بالقياسِ لا بالدعوى.\n");
exit(0);
