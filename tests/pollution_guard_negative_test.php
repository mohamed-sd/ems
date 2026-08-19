<?php
/**
 * tests/pollution_guard_negative_test.php — قيدُ منعِ التلوثِ يُختبَر لا يُدَّعى
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الخطوةُ ⑤ في قرارِ المالك: «**ثم قيدٌ يمنع عودتَه**».
 *
 * ◆ **ودرسُ هذه الجولةِ**: النسخةُ الأولى من القيدِ أُنشئت بنجاحٍ ظاهرٍ
 *   (10 قيودٍ · صفرُ خطأ) وكانت **عاطلةً تمامًا** — استعملت `[؀-ۿ]`
 *   وMariaDB لا تفهم `\uXXXX` فقرأتها صنفَ محارفَ حرفيًّا. فمرَّ الملوَّثُ بلا
 *   اعتراض. **ولم يكشفه إلا إدراجٌ ملوَّثٌ متعمَّد.**
 *   ولذلك يبقى هذا الفاحصُ ملفًّا دائمًا: قيدٌ لا يُختبَر سلبيًّا دعوى.
 *
 * ◆ **ويُقرأ اسمُ القيدِ الرافضِ لا مجرَّدُ الرفض**: أولُ اختبارٍ «نجح» ظاهريًّا
 *   لأن قيدًا آخرَ غيرَ متصلٍ (`chk_rules_retired`) رفض الإدراج — فقُرئ رفضُه
 *   نجاحًا لقيدِنا. فالمطابقةُ على **اسمِ القيدِ** لا على وقوعِ الخطأ.
 *
 * ◆ وثلاثةُ فحوصٍ لكلِّ عمودٍ محميّ:
 *   ① وارِدٌ ملوَّثٌ **يُرفض باسمِ قيدِ المنع**.
 *   ② وارِدٌ سليمٌ **يمرّ** — فالقيدُ ليس منعًا عامًّا.
 *   ③ صفٌّ ملوَّثٌ قائمٌ **يبقى ويُحدَّث** — قاعدةُ صفرِ الفقدِ تسري.
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
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

$pass = 0; $fail = 0;
$check = function ($label, $ok, $detail = '') use (&$pass, &$fail) {
    printf("  %s %-56s %s\n", $ok ? '✔' : '✗', $label, $detail);
    $ok ? $pass++ : $fail++;
};

/* ── ① القيودُ قائمةٌ فعلًا ─────────────────────────────────────────────── */
$cons = array();
$r = $conn->query("SELECT TABLE_NAME, CONSTRAINT_NAME, CHECK_CLAUSE
                     FROM information_schema.CHECK_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME LIKE 'chk_nopollute_%'");
while ($r && ($x = $r->fetch_assoc())) { $cons[] = $x; }
echo "════ قيدُ منعِ عودةِ التلوث ════\n";
$check('① قيودُ المنعِ منشأةٌ في القاعدة', count($cons) > 0, count($cons) . ' قيدًا');

/* ── ② لا قيدَ يستعمل الصيغةَ العاطلةَ `\uXXXX` ─────────────────────────── */
$inert = 0;
foreach ($cons as $c) { if (strpos($c['CHECK_CLAUSE'], '\\u06') !== false) { $inert++; } }
$check('② لا قيدَ بصيغةٍ لا تفهمها القاعدة (`\\uXXXX`)', $inert === 0,
       $inert > 0 ? "**{$inert} قيدًا عاطلًا**" : 'صفر');

/* ── ③ اختبارٌ وظيفيٌّ حيٌّ على جدولٍ ذي قيد ───────────────────────────── */
$target = null; $blocked = array();
foreach ($cons as $c) {
    $t = $c['TABLE_NAME'];
    /* عمودُ التلوثِ يُقرأ من سجلِّ العزلِ لا يُخمَّن */
    $q = $conn->query("SELECT column_name FROM gov_test_data_isolation
                        WHERE table_name = '" . $conn->real_escape_string($t) . "' LIMIT 1");
    if (!$q || !$q->num_rows) { continue; }
    $col = $q->fetch_assoc()['column_name'];
    /* الجدولُ يجب أن يقبل إدراجًا بسيطًا — تُقرأ الأعمدةُ الإلزاميةُ بلا افتراضيّ */
    $req = array();
    $cq = $conn->query("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, DATA_TYPE
                          FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $conn->real_escape_string($t) . "'");
    $tooHard = false;
    while ($cq && ($cc = $cq->fetch_assoc())) {
        if ($cc['COLUMN_NAME'] === $col) { continue; }
        if ($cc['IS_NULLABLE'] === 'NO' && $cc['COLUMN_DEFAULT'] === null
            && strpos((string) $cc['EXTRA'], 'auto_increment') === false) {
            if (in_array($cc['DATA_TYPE'], array('int', 'bigint', 'tinyint', 'smallint', 'decimal'), true)) {
                $req[$cc['COLUMN_NAME']] = '0';
            } elseif (in_array($cc['DATA_TYPE'], array('datetime', 'timestamp'), true)) {
                $req[$cc['COLUMN_NAME']] = 'NOW()';
            } elseif (in_array($cc['DATA_TYPE'], array('varchar', 'char', 'text'), true)) {
                $req[$cc['COLUMN_NAME']] = "'x'";
            } else { $tooHard = true; break; }
        }
    }
    if ($tooHard) { continue; }
    /* ◆ **يُتخطّى جدولٌ يحجبه قيدٌ آخرُ لا صلةَ له**: أولُ تشغيلٍ اختار
         `approval_workflow_rules` وفيه `chk_rules_retired` (`is_active = 0`)
         يرفض كلَّ إدراجٍ — فقُرئ رفضُه دليلًا على عملِ قيدِنا مرةً، وعلى
         عطبِه مرةً. والفاحصُ الذي لا يميّز أيُّ قيدٍ رفض **يكذب في الاتجاهين**.
         فيُجرَّب إدراجٌ سليمٌ أولًا: إن رفضته القاعدةُ بغيرِ قيدِنا فالجدولُ
         غيرُ صالحٍ للاختبار — يُتخطّى ويُعلَن. */
    $probeCols = array_merge(array($col), array_keys($req));
    $probeVals = array_merge(array("'probe_clean'"), array_values($req));
    $probeSql = "INSERT INTO `{$t}` (`" . implode('`,`', $probeCols) . "`) VALUES ("
              . implode(',', $probeVals) . ")";
    $conn->query($probeSql);
    $pe = $conn->error;
    $pid = $conn->insert_id;
    if ($pid) { $conn->query("DELETE FROM `{$t}` WHERE id = {$pid}"); }
    if ($pe !== '') { $blocked[] = $t . ' ⇐ ' . mb_substr($pe, 0, 40); continue; }
    $target = array('table' => $t, 'col' => $col, 'req' => $req, 'name' => $c['CONSTRAINT_NAME']);
    break;
}

if ($target === null) {
    $check('③ اختبارٌ وظيفيٌّ حيّ', false,
           '**لا جدولَ صالحًا** — محجوبٌ بقيودٍ أخرى: ' . implode(' · ', array_slice($blocked, 0, 3)));
} else {
    $t = $target['table']; $col = $target['col'];
    $before = (int) $conn->query("SELECT COUNT(*) c FROM `{$t}`")->fetch_assoc()['c'];
    $cols = array_merge(array($col), array_keys($target['req']));
    $valsBad  = array_merge(array("'جملةٌ بشريةٌ في خانةِ رمز'"), array_values($target['req']));
    $valsGood = array_merge(array("'clean_code_ok'"), array_values($target['req']));
    $mk = function ($vals) use ($t, $cols) {
        return "INSERT INTO `{$t}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ")";
    };
    $conn->query($mk($valsBad));
    $e1 = $conn->error;
    $check('③ وارِدٌ ملوَّثٌ يُرفض **باسمِ قيدِ المنع**',
           strpos($e1, $target['name']) !== false,
           $e1 === '' ? "**مرَّ! القيدُ عاطل**" : mb_substr($e1, 0, 46));

    $conn->query($mk($valsGood));
    $id = $conn->insert_id; $e2 = $conn->error;
    $check('④ وارِدٌ سليمٌ يمرّ — فالقيدُ ليس منعًا عامًّا', $e2 === '',
           $e2 === '' ? "على `{$t}`" : mb_substr($e2, 0, 46));
    if ($id) { $conn->query("DELETE FROM `{$t}` WHERE id = {$id}"); }

    $after = (int) $conn->query("SELECT COUNT(*) c FROM `{$t}`")->fetch_assoc()['c'];
    $check('⑤ صفرُ أثرٍ للاختبارِ في البيانات', $before === $after, "{$before} ⇐ {$after}");

    $still = (int) $conn->query("SELECT COUNT(*) c FROM `{$t}`
                                  WHERE `{$col}` LIKE '% %'
                                    AND LENGTH(`{$col}`) > CHAR_LENGTH(`{$col}`)")->fetch_assoc()['c'];
    $check('⑥ الصفوفُ الملوَّثةُ القائمةُ باقيةٌ — صفرُ فقد', $still > 0, "{$still} صفًّا محفوظًا");
}

echo "\n════════════════════════════════════════════════════════════\n";
printf("  اجتاز %d · أخفق %d\n", $pass, $fail);
echo $fail === 0 ? "✔ القيدُ يمنع الوارِدَ الملوَّثَ ولا يمسُّ القائم\n"
                 : "✗ القيدُ غيرُ مُثبَتٍ — ولا يُعلَن عاملًا\n";
exit($fail === 0 ? 0 : 1);
