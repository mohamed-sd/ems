<?php
/**
 * tools/xf_missing_with_column.php — حقلٌ ناقصٌ في الأثرِ وعمودُه قائمٌ في الجدول
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **النمطُ المقيسُ في `TRS-14`**: «المراجع» كان ناقصًا في القياس،
 *   و`tre_bank_facility.reviewed_by` **مقيَّدٌ في الجدولِ منذ البداية والسطحُ
 *   لا يصيّره**. فالنقصُ كان **عرضًا لا بيانات** — وإغلاقُه رأسٌ وخليةٌ بلا هجرة.
 *   وهذا الملفُّ يبحث عن كلِّ نظائرِه.
 *
 * ◆ **والمعجمُ من النظامِ لا من ذهني**: `gov_field_class` تحمل **2316** قيدًا
 *   يربط `field_key` بـ`label_ar` عربيًّا. فاسمُ حقلِ الدليلِ يُطابَق على
 *   أوسمةِ المعجمِ (مطبَّعةً)، فتخرج **مفاتيحُ مرشَّحةٌ مقيسةٌ** — ثمَّ يُسأل:
 *   أفي جدولِ هذا السطحِ عمودٌ بهذا المفتاح؟
 *
 * ⛔ **ولا يقترح كتابةً**: يُخرج قائمةَ فرزٍ للحكمِ البشريّ. فالمعجمُ يربط
 *   وسمًا بمفتاحٍ في **شاشةٍ أخرى**، وقد يحمل جدولانِ المفتاحَ نفسَه بمعنيَين.
 * ⛔ **ويُعلِم بالحسّاس** فلا يُقترَح بثُّ سرٍّ باسمِ إتمامِ عمود.
 *
 * التشغيل: php tools/xf_missing_with_column.php [--dump=<f8.json>] [--limit=N]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/xf_lib.php';
ob_start(); require $ROOT . '/includes/session_bootstrap.php'; require $ROOT . '/config.php'; ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

$DUMP = ''; $LIMIT = 40;
foreach ($argv as $a) {
    if (strpos($a, '--dump=') === 0) { $DUMP = substr($a, 7); }
    elseif (strpos($a, '--limit=') === 0) { $LIMIT = max(1, (int) substr($a, 8)); }
}
if ($DUMP === '' || !is_file($DUMP)) { exit("مرِّرْ تفريغَ القياس: --dump=<ملف> (من rpr02_field_measure.php --dump=)\n"); }

$norm = function ($s) {
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0640}]~u', '', (string) $s);
    $s = str_replace(array('◄', '▼', 'أ', 'إ', 'آ', 'ة', 'ى'), array('', '', 'ا', 'ا', 'ا', 'ه', 'ي'), $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};

/* ── المعجم: وسمٌ عربيٌّ ⇒ مفاتيحُ أعمدةٍ استُعملت له في النظام ─────────── */
$lex = array();
$q = $conn->query("SELECT DISTINCT field_key, label_ar FROM gov_field_class WHERE label_ar <> ''");
while ($q && ($r = $q->fetch_assoc())) {
    $k = $norm($r['label_ar']);
    if ($k === '') { continue; }
    $lex[$k][$r['field_key']] = 1;
}

/* ── الحسّاس ──────────────────────────────────────────────────────────── */
$sens = array();
$q = $conn->query("SELECT DISTINCT field_key FROM gov_field_class WHERE is_sensitive = 1");
while ($q && ($x = $q->fetch_row())) { $sens[strtolower($x[0])] = 1; }
foreach (array('bank_account_no', 'bank_iban', 'tax_number', 'tax_no', 'identity_number',
               'commercial_registration', 'commercial_reg', 'monthly_salary', 'password') as $s) { $sens[$s] = 1; }

/* ── أعمدةُ كلِّ جدولٍ مرّةً واحدة ─────────────────────────────────────── */
$tblCols = array();
$q = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()");
while ($q && ($r = $q->fetch_assoc())) { $tblCols[strtolower($r['t'])][strtolower($r['c'])] = $r['c']; }

$rows = json_decode((string) file_get_contents($DUMP), true);
$hits = array(); $nHit = 0;
foreach ((array) $rows as $x) {
    $p = isset($x['path']) ? (string) $x['path'] : '';
    if ($p === '' || !is_file($ROOT . '/' . $p)) { continue; }
    $miss = (array) $x['miss'];
    if (!$miss) { continue; }
    $res = xf_resolve_table($ROOT . '/' . $p);
    $tbl = strtolower((string) $res['table']);
    if ($tbl === '' || !isset($tblCols[$tbl])) { continue; }
    $cols = $tblCols[$tbl];
    $found = array();
    foreach ($miss as $mn) {
        $n = $norm($mn);
        if ($n === '' || !isset($lex[$n])) { continue; }
        foreach (array_keys($lex[$n]) as $key) {
            if (isset($cols[strtolower($key)])) {
                $found[] = array('field' => $mn, 'col' => $cols[strtolower($key)],
                                 'sens' => isset($sens[strtolower($key)]));
                break;
            }
        }
    }
    if ($found) { $hits[$p] = array($x['req'], $res['confidence'], $tbl, $found); $nHit += count($found); }
}
uasort($hits, function ($a, $b) { return count($b[3]) - count($a[3]); });

printf("══ حقولٌ ناقصةٌ في الأثرِ ولها عمودٌ قائمٌ في جدولِ سطحِها ══\n");
printf("   أسطحٌ: %d · حقولٌ: %d · (المعجم %d وسمًا)\n\n", count($hits), $nHit, count($lex));
$i = 0;
foreach ($hits as $p => $v) {
    printf("── %s · %s · جدول %s [%s]\n", $p, $v[0], $v[2], $v[1]);
    foreach ($v[3] as $f) {
        printf("     %-34s ⇐ %-26s%s\n", $f['field'], $f['col'], $f['sens'] ? '  ⛔ حسّاس' : '');
    }
    if (++$i >= $LIMIT) { printf("\n   … وبقيّةُ %d سطحًا\n", count($hits) - $LIMIT); break; }
}
echo "\n⛔ فرزٌ لا اقتراحَ كتابة — المعجمُ يربط وسمًا بمفتاحٍ في شاشةٍ أخرى، والحكمُ بالنظر.\n";
