<?php
/**
 * 2027_02_09 — ترميمُ كلِّ قواعدِ العملِ المفقودةِ في القاعدة (141 قيدًا)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحجمُ الحقيقيُّ للفقدِ مقيسٌ الآن**: نسخةُ ما قبلَ إعادةِ بناءِ 2026-08-03
 *   تحمل **169 قاعدةَ عملٍ** في `CHECK` (بعد استثناءِ قيودِ `json_valid`
 *   التلقائية). والحيُّ في القاعدةِ **28** — أي **141 مفقودة**.
 *
 * ◆ وهجرتا `2027_02_03`/`2027_02_04` رمَّمتا 27 منها — وهي التي كان أحدٌ في
 *   الشجرةِ **يذكرها باسمها** فرأتها أداةُ `fix_missing_checks`. والباقي
 *   **لم يذكره أحدٌ**، فبقي خفيًّا تمامًا: قاعدةٌ تحرس مالًا لا يعلم بها أحد،
 *   وغيابُها لا يُكشف بفاحصٍ ولا بمقارنةِ مخطَّطٍ (فالمخطَّطُ المرجعيُّ يوافق
 *   القاعدةَ الناقصة).
 *   ⇒ ولذلك بُني `tools/fix_check_full_sweep.php`: يبدأ من **النسخةِ** لا من
 *     الشجرة، فيرى المذكورَ وغيرَ المذكورِ معًا.
 *
 * ◆ **ولكلِّ قيدٍ يُقاس مخالفوه قبلَ إضافته** بنفيِ شرطِه على جدولِه
 *   (`WHERE NOT (<clause>)`). فما بمخالفٍ **يُعلَن ولا يُضاف**، ولا يُعدَّل صفٌّ
 *   واحدٌ لإرضاءِ قيد — تعديلُ بيانةٍ ماليةٍ لتمرير قيدٍ أسوأُ من غيابِ القيد.
 *
 * ◆ **گوتشتان مقيستان ومطبَّقتان:**
 *   ① النصُّ العربيُّ بمُقدِّم `_utf8mb4'…'` المنقولِ من `SHOW CREATE` **لا يعود**
 *     في `ADD CONSTRAINT`: `collation_connection = utf8mb4_general_ci` مقابل
 *     `collation_server = utf8mb4_unicode_ci`، فيُقيَّم الشرطُ كذبًا. تُنزَع
 *     المُقدِّماتُ من الحرفياتِ **العربيةِ وحدَها**.
 *   ② قيدان في النسخةِ يستعملان `regexp_like` — **دالةُ MySQL 8 لا توجد في
 *     مارياDB**، فلم يكونا يعملان على هذا المحرِّكِ أصلًا. يُعلَنان ولا يُضافان.
 *
 * ◆ مُتحمِّلٌ للتكرار · ويُجَسُّ التمييزُ وظيفيًّا على عيّنةٍ ماليةٍ ثم يُتراجَع.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

/* ── الحيُّ الآن ─────────────────────────────────────────────────────────── */
$live = array();
$r = $db->query("SELECT CONSTRAINT_NAME n FROM information_schema.CHECK_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE()");
while ($r && ($x = $r->fetch_assoc())) { $live[$x['n']] = true; }

/* ── كلُّ قاعدةِ عملٍ في نسخةِ ما قبلَ إعادةِ البناء ────────────────────── */
$dumps = glob(dirname(__DIR__) . '/baseline/auto_pre_up_20260803_*.sql');
sort($dumps);
if (!$dumps) { echo "  ○ لا نسخةَ 2026-08-03 — لا عمل\n"; exit(0); }
$dump = $dumps[0];

$was = array(); $curTable = '';
$fh = fopen($dump, 'r');
while (($line = fgets($fh)) !== false) {
    if (preg_match('/^CREATE TABLE `([^`]+)`/i', $line, $m)) { $curTable = $m[1]; continue; }
    if (!preg_match('/^\s*CONSTRAINT `([^`]+)` CHECK \((.*)\),?\s*$/i', $line, $m)) { continue; }
    if (stripos($m[2], 'json_valid') !== false) { continue; }
    $was[$m[1]] = array('table' => $curTable, 'clause' => rtrim(trim($m[2]), ','));
}
fclose($fh);

echo "══ ترميمُ قواعدِ العملِ المفقودة ══\n";
echo '  في النسخةِ: ' . count($was) . ' · حيّةٌ: ' . count($live) . "\n";

$added = 0; $dirty = array(); $unmeasurable = array(); $noTable = array(); $failed = array();

foreach ($was as $name => $spec) {
    if (isset($live[$name])) { continue; }
    $t = $spec['table'];

    /* گوتشا ①: نزعُ مُقدِّمِ الترميزِ من الحرفياتِ العربيةِ وحدَها */
    $clause = preg_replace_callback("/_utf8mb4'([^']*)'/u", static function ($m) {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $m[1]) ? "'" . $m[1] . "'" : $m[0];
    }, $spec['clause']);

    $ex = $db->query("SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $db->real_escape_string($t) . "'");
    if (!$ex || (int) $ex->fetch_row()[0] === 0) { $noTable[$name] = $t; continue; }

    $q = $db->query("SELECT COUNT(*) FROM `{$t}` WHERE NOT ({$clause})");
    if (!$q) { $unmeasurable[$name] = $t . ' — ' . mb_substr($db->error, 0, 70); continue; }
    $bad = (int) $q->fetch_row()[0];
    if ($bad > 0) { $dirty[$name] = array('t' => $t, 'bad' => $bad, 'clause' => $clause); continue; }

    if ($db->query("ALTER TABLE `{$t}` ADD CONSTRAINT `{$name}` CHECK ({$clause})") === false) {
        $failed[$name] = $t . ' — ' . mb_substr($db->error, 0, 80);
        continue;
    }
    $added++;
}

echo "\n── الحصيلة\n";
echo "  ✔ رُمِّم: {$added}\n";
echo '  ⚠ بمخالفينَ (يُعلَن ولا يُضاف): ' . count($dirty) . "\n";
foreach ($dirty as $n => $h) { echo "      {$n} · {$h['t']} · {$h['bad']} مخالفًا\n"; }
echo '  ⚠ غيرُ قابلٍ للقياسِ على هذا المحرِّك: ' . count($unmeasurable) . "\n";
foreach ($unmeasurable as $n => $w) { echo "      {$n} · {$w}\n"; }
echo '  ○ جداولُ رُفعت: ' . count($noTable) . "\n";
echo '  ✘ فشل: ' . count($failed) . "\n";
foreach ($failed as $n => $w) { echo "      {$n} · {$w}\n"; }

/* ── الجسُّ الوظيفيُّ على عيّنةٍ ماليةٍ ثم تراجُع ─────────────────────────── */
echo "\n── جسٌّ وظيفيٌّ (ثم تراجُع)\n";
$db->begin_transaction();
$probes = array(
    'ck_ffe_fx_pair' => array(
        'sql' => "UPDATE fin_financial_events SET base_amount = 999999.99
                   WHERE id = (SELECT id FROM (SELECT id FROM fin_financial_events
                                WHERE fx_rate IS NOT NULL LIMIT 1) x)",
        'why' => 'معادلٌ يخالف ROUND(المبلغ×السعر)'),
    'ck_aos_pct' => array(
        'sql' => "UPDATE asset_ownership_shares SET share_pct = 150
                   WHERE id = (SELECT id FROM (SELECT id FROM asset_ownership_shares LIMIT 1) x)",
        'why' => 'حصةُ ملكيةٍ 150٪'),
    'ck_bank_line_amount' => array(
        'sql' => "UPDATE bank_statement_lines SET amount = 0
                   WHERE id = (SELECT id FROM (SELECT id FROM bank_statement_lines LIMIT 1) x)",
        'why' => 'سطرُ كشفٍ بمبلغٍ صفري'),
);
$pOk = 0; $pN = 0;
foreach ($probes as $name => $p) {
    if (!isset($live[$name]) && !in_array($name, array_keys($was), true)) { continue; }
    $chk = $db->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='{$name}'");
    if (!$chk || (int) $chk->fetch_row()[0] === 0) { echo "  ○ {$name} غيرُ مضافٍ — لا جسّ\n"; continue; }
    $res = $db->query($p['sql']);
    if ($res !== false && $db->affected_rows === 0) { echo "  ○ {$name}: لا صفَّ مُتأثِّر — لا يُقرأ حكمًا\n"; continue; }
    $pN++;
    if ($res === false) { $pOk++; }
    echo '  ' . ($res === false ? '✔' : '✘') . ' ' . str_pad($name, 24) . $p['why'] . ' ⇒ '
       . ($res === false ? 'مرفوضٌ بنيويًّا' : '**مرَّ**') . "\n";
}
$db->rollback();
echo "  ○ تُراجِع الجسُّ\n";

$total = (int) $db->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA = DATABASE()")->fetch_row()[0];
echo "\n  قيودُ CHECK الحيّةُ الآن: {$total}\n";
$ok = (count($failed) === 0) && ($pN === 0 || $pOk === $pN);
echo "\n" . ($ok
    ? "✅ طبقةُ المنعِ عادت — {$added} قاعدةَ عملٍ كانت غائبةً صارت تحرس.\n"
    : "⚠ راجِع أعلاه\n");
exit($ok ? 0 : 1);
