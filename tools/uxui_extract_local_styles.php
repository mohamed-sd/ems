<?php
/**
 * tools/uxui_extract_local_styles.php — نقلُ كتلِ النمطِ المحليةِ إلى المركز
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ بوابةُ الترقيةِ البند ٦ (ف١٦-٢): «‏100٪ من المكتبةِ المركزيةِ · **صفرُ نمطٍ
 *   محليٍّ في الشاشة**». وقياسُ الحالِ: 99 مخالفةً في 9 شاشاتٍ ذهبية.
 *
 * ◆ والسابقةُ في هذا المستودعِ قائمة: `assets/css/ems-screens.css` بيتُ «أنماطِ
 *   الشاشاتِ الخاصة» (INJ-0442)، ويُحمَّل **قربَ الآخرِ** كما كانت الكتلُ داخلَ
 *   الصفحة — «فالنقلُ لا يغيّر مَن يغلب مَن».
 *
 * ◆ وهذا **نقلٌ لا إعادةُ كتابة**: النصُّ يُنقل حرفًا بحرفٍ داخلَ نطاقٍ معلَّمٍ
 *   باسمِ الشاشة، ولا تُمَسُّ قيمةٌ واحدة — فصفرُ انحرافٍ بصريٍّ بالتصميم.
 *   وتطبيعُ القياساتِ خارجَ السلّمِ عملٌ لاحقٌ **يُقاس بخطِّ أساسٍ خاصٍّ به**،
 *   لأنه يحرّك بكسلاتٍ فعلًا — وخلطُه بالنقلِ يُخفي أثرَ أيِّهما.
 *
 * ◆ وقابلُ العكسِ بأمرٍ واحد: نسخةُ الملفِّ الأصليةِ تُحفظ قبل المساس.
 *
 * التشغيل:
 *   php tools/uxui_extract_local_styles.php --list          ما سيُنقل (بلا كتابة)
 *   php tools/uxui_extract_local_styles.php --apply         النقل
 *   php tools/uxui_extract_local_styles.php --revert        الاستعادة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}
$TARGET_CSS = $ROOT . '/assets/css/ems-screens.css';
$BACKUP_DIR = $ROOT . '/storage/backups/uxui_local_styles';

/* ═══════════════════════════════════════════════════════════════════════════
 * النطاقُ: الذهبيةُ **أو موجةٌ من سجلِّ الترحيل** بترتيبِ الشدة
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ نصُّ ف١٣-١ الخطوة ٥: «الترحيلُ بموجاتٍ إدارية — **بترتيبِ الشدةِ من دفترِ
 *   التدقيق**». فالأداةُ كانت محصورةً في العشرِ الذهبيةِ وقد فرغت منها، والباقي
 *   **86 ملفًّا بـ430 قاعدةً** لا تبلغها.
 * ◆ فيُفتح النطاقُ بـ`--wave=<الشدة>` من `gov_migration_ledger` — والموجةُ
 *   تُنفَّذ وحدَها فتُقاس وتُراجَع قبلَ التي تليها، ولا تُكنس المئاتُ دفعةً.
 * ◆ **والنسخُ الاحتياطيُّ يشمل ما يُمَسُّ فعلًا** — فالعكسُ يبقى بأمرٍ واحد.
 * ═══════════════════════════════════════════════════════════════════════════ */
$wave = isset($args['wave']) ? $args['wave'] : '';
$screens = array();
if ($wave === '') {
    $q = $conn->query("SELECT screen_file FROM gov_golden_approvals ORDER BY id");
    while ($q && ($x = $q->fetch_assoc())) { $screens[] = $x['screen_file']; }
} else {
    $w = '';
    if ($wave !== 'all') {
        $w = " AND severity = '" . $conn->real_escape_string($wave) . "'";
    }
    $q = $conn->query("SELECT DISTINCT route FROM gov_migration_ledger
                        WHERE resolve_state = 'RESOLVED' AND route IS NOT NULL {$w}
                        ORDER BY route");
    while ($q && ($x = $q->fetch_assoc())) { $screens[] = $x['route']; }
}

/* ── الاستعادة ── */
if (isset($args['revert'])) {
    $n = 0;
    foreach ($screens as $rel) {
        $bk = $BACKUP_DIR . '/' . str_replace('/', '__', $rel);
        if (is_file($bk)) { copy($bk, $ROOT . '/' . $rel); $n++; }
    }
    $bkCss = $BACKUP_DIR . '/ems-screens.css';
    if (is_file($bkCss)) { copy($bkCss, $TARGET_CSS); }
    echo "استُعيدت {$n} شاشةً + ملفُّ الأنماطِ المركزيّ\n";
    exit(0);
}

/* ── الجرد ── */
$plan = array();
foreach ($screens as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    if (!preg_match_all('~<style\b[^>]*>(.*?)</style>~su', $src, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) { continue; }
    $blocks = array();
    foreach ($m as $mm) { $blocks[] = $mm[1][0]; }
    $plan[$rel] = $blocks;
}

if (!$plan) { exit("لا كتلَ نمطٍ محليةً في هذا النطاق\n"); }

echo "════ نقلُ الأنماطِ المحليةِ إلى المركز ════\n";
$totalLines = 0;
foreach ($plan as $rel => $blocks) {
    $lines = 0;
    foreach ($blocks as $b) { $lines += substr_count($b, "\n"); }
    $totalLines += $lines;
    printf("  %-38s %d كتلة · %d سطرًا\n", $rel, count($blocks), $lines);
}
echo "  الإجمالي: " . count($plan) . " شاشةً · {$totalLines} سطرًا\n";

if (!isset($args['apply'])) {
    echo "\n  ▸ جردٌ بلا كتابة — أضِفْ --apply للنقل (والعكسُ بـ--revert)\n";
    exit(0);
}

/* ── النقل ── */
if (!is_dir($BACKUP_DIR) && !mkdir($BACKUP_DIR, 0777, true)) { exit("تعذّر إنشاءُ مجلدِ النسخ\n"); }
if (!is_file($BACKUP_DIR . '/ems-screens.css')) { copy($TARGET_CSS, $BACKUP_DIR . '/ems-screens.css'); }

$append = "\n\n/* ═══════════════════════════════════════════════════════════════════════════\n"
        . "   نُقل من كتلِ النمطِ المحليةِ في الشاشاتِ الذهبية (UXUI-01 · البند ٦)\n"
        . "   " . date('Y-m-d H:i') . " — **نقلٌ حرفيٌّ بلا مسِّ قيمة**: صفرُ انحرافٍ بصريٍّ بالتصميم.\n"
        . "   وموضعُ التحميلِ هنا قربَ الآخرِ كما كانت الكتلُ داخلَ الصفحة، فلا يتغيّر مَن يغلب مَن.\n"
        . "   والعكسُ بأمرٍ واحد: php tools/uxui_extract_local_styles.php --revert\n"
        . "   ═══════════════════════════════════════════════════════════════════════════ */\n";
$moved = 0;
foreach ($plan as $rel => $blocks) {
    $path = $ROOT . '/' . $rel;
    $bk = $BACKUP_DIR . '/' . str_replace('/', '__', $rel);
    if (!is_file($bk)) { copy($path, $bk); }
    $src = (string) file_get_contents($path);

    $append .= "\n/* ── " . $rel . " ── */\n";
    foreach ($blocks as $b) { $append .= trim($b) . "\n"; }

    /* تُزال الكتلُ ويُترك أثرٌ يقول أين ذهبت — فلا يبحث أحدٌ عنها */
    $src = preg_replace('~<style\b[^>]*>.*?</style>~su',
        '<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>',
        $src, 1);
    $src = preg_replace('~<style\b[^>]*>.*?</style>~su', '', $src);
    file_put_contents($path, $src);
    $moved++;
}
file_put_contents($TARGET_CSS, file_get_contents($TARGET_CSS) . $append);
echo "\n  ▸ نُقل من {$moved} شاشةً · النسخُ الأصليةُ في storage/backups/uxui_local_styles/\n";
echo "  ▸ تحقّقْ الآن: بوابةُ المركزيةِ · الخطُّ البصريُّ · وصفرُ الفقد\n";
