<?php
/**
 * tools/xf_wire_candidates.php — مرشَّحاتُ وصلِ الرؤوسِ المعلَّقةِ في سطحٍ واحد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السؤال العمليّ**: أيُّ رأسٍ `data-fn` بلا `data-fn-src` **له مصدرٌ فعليٌّ
 *   في الصفِّ** فيُوصَل، وأيُّه لا عمودَ له فيبقى مُعلَنًا؟ الجوابُ يحتاج ثلاثةَ
 *   أشياءَ جنبًا إلى جنب: الرؤوسَ العاريةَ · أعمدةَ الجدولِ · وما يقرؤه الصفُّ
 *   فعلًا (`$row['x']`) — فالوصلةُ قد تجلب حقلًا ليس في الجدول (`job_title_name`).
 *
 * ⛔ **ولا يقترح مطابقةً**: يعرض الثلاثةَ ويترك الحكمَ — فاسمُ العمودِ لا
 *   يُشتقُّ من وسمٍ عربيٍّ بالتخمين، والخطأُ هنا يُظهر شرطاتٍ باسمِ بيانات.
 * ⛔ **ويُعلِم بالحسّاس**: عمودٌ في `gov_field_class` بـ`is_sensitive=1` أو من
 *   عائلةِ الأسرارِ المعروفةِ يُوسَم — فبثُّه في عمودٍ ظاهرٍ كشفٌ لا إتمام.
 *
 * التشغيل: php tools/xf_wire_candidates.php <مسار الشاشة>
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/xf_lib.php';
ob_start(); require $ROOT . '/includes/session_bootstrap.php'; require $ROOT . '/config.php'; ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

$rel = isset($argv[1]) ? ltrim(str_replace('\\', '/', $argv[1]), '/') : '';
if ($rel === '') { exit("الاستعمال: php tools/xf_wire_candidates.php <مسار الشاشة>\n"); }
$path = $ROOT . '/' . $rel;
if (!is_file($path)) { exit("لا ملفَّ: {$rel}\n"); }
$src = (string) file_get_contents($path);

/* ① الرؤوسُ العاريةُ — بلا `data-fn-src` */
$bare = array();
if (preg_match_all('~<th[^>]*data-fn=(?![^>]*data-fn-src)[^>]*>(.*?)</th>~is', $src, $m)) {
    foreach ($m[1] as $t) { $lbl = trim(strip_tags($t)); if ($lbl !== '') { $bare[$lbl] = 1; } }
}
$bound = array();
if (preg_match_all('~data-fn-src="([a-z0-9_]+)"~i', $src, $m2)) { $bound = array_unique($m2[1]); }

/* ② جدولُ الشاشةِ من حلقةِ الصفوف */
$res = xf_resolve_table($path);
$cols = array();
if ($res['table'] !== '') {
    $st = $conn->prepare("SELECT COLUMN_NAME c FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
    $st->bind_param('s', $res['table']); $st->execute(); $rs = $st->get_result();
    while ($x = $rs->fetch_assoc()) { $cols[] = $x['c']; }
    $st->close();
}

/* ③ ما يقرؤه الصفُّ فعلًا — مفاتيحُ `$row['x']` / `$r['x']` */
$rowKeys = array();
if (preg_match_all('~\$(?:row|r|rec)\s*\[\s*[\x27"]([a-z0-9_]+)[\x27"]\s*\]~i', $src, $m3)) {
    foreach ($m3[1] as $k) { $rowKeys[strtolower($k)] = 1; }
}
$extra = array_diff(array_keys($rowKeys), array_map('strtolower', $cols));

/* ④ الحسّاسُ — من العقدِ ومن عائلةِ الأسرارِ المعروفة */
$sens = array();
$q = $conn->query("SELECT DISTINCT field_key FROM gov_field_class WHERE is_sensitive = 1");
while ($q && ($x = $q->fetch_row())) { $sens[strtolower($x[0])] = 1; }
foreach (array('bank_account_no', 'bank_iban', 'tax_number', 'identity_number',
               'commercial_registration', 'salary', 'monthly_salary', 'password') as $s) { $sens[$s] = 1; }

printf("══ %s\n   الجدول: %s [%s] · %s\n", $rel, $res['table'] ?: '—', $res['confidence'], mb_substr((string) $res['evidence'], 0, 70));
printf("   رؤوسٌ موصولةٌ سلفًا: %s\n\n", $bound ? implode(' · ', $bound) : 'لا شيء');

printf("── رؤوسٌ عاريةٌ (%d) — تحتاج حكمًا:\n", count($bare));
foreach (array_keys($bare) as $b) { echo "   • {$b}\n"; }

printf("\n── أعمدةُ `%s` (%d):\n   %s\n", $res['table'] ?: '—', count($cols), implode(' · ', $cols));
if ($extra) { printf("\n── حقولٌ يقرؤها الصفُّ من وصلاتٍ (خارجَ الجدول) (%d):\n   %s\n", count($extra), implode(' · ', $extra)); }

$sensHere = array();
foreach ($cols as $c) { if (isset($sens[strtolower($c)])) { $sensHere[] = $c; } }
if ($sensHere) { printf("\n⛔ حسّاسٌ لا يُبَثُّ في عمودٍ ظاهرٍ بلا قناعِ مالك: %s\n", implode(' · ', $sensHere)); }
echo "\n";
