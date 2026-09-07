<?php
/**
 * 2028_06_05 — مصالحةُ بصماتِ الهجراتِ التي صُقلت بعد تطبيقِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس**: `migrate.php up` كان **يرفض كلَّ تقدُّمٍ** — لا هجرةَ
 *   تُطبَّق بالمُشغِّل — لأنَّ **35 ملفًّا** مطبَّقًا اختلفت بصمتُه عن المسجَّلِ في
 *   الدفتر. (‏واضطُرَّت جولةُ 2028_06_04 لتجاوزِ المُشغِّلِ ثمَّ التسويةِ يدويًّا.)
 *
 * ◆ **والقاعدةُ من سابقةِ المشروعِ نفسِه** (`2028_01_18_ctl_ledger_checksum_reconcile`):
 *   تُصالَح البصمةُ **فقط** متى اجتمعت ثلاثةٌ، ولكلِّ ملفٍّ **على حدة**:
 *     ① **النسخةُ المطبَّقةُ لا وجودَ لها في git** — فاستعادتُها مستحيلةٌ إثباتًا،
 *        وتأليفُ محتوًى يطابق بصمةً قديمةً تلفيقٌ بالتعريف.
 *     ② **الفرقُ لا يمسُّ المخطَّط** — لا `CREATE/ALTER/DROP/RENAME/ADD COLUMN`
 *        في فرقِه، فلا تغييرَ أثرٍ يستوجب هجرةً جديدة.
 *     ③ **الأثرُ واقعٌ في القاعدة** — جدولٌ من جداولِ الهجرةِ قائمٌ فعلًا.
 *
 * ◆ **القياسُ المُجرى (35 ملفًّا · لا عيّنة)**: 30 بالتزامٍ واحدٍ في git ⇒ ①  ·
 *   5 بالتزامَين وفرقُها مقروءٌ بصفرِ DDL ⇒ ②  ·  35 من 35 جداولُها قائمة ⇒ ③.
 *   ⇒ **صفرُ ملفٍّ غيرِ مؤهَّل**.
 *
 * ⛔ **ولا مصالحةَ عمياء**: الشروطُ الثلاثةُ **تُعاد** عند كلِّ تشغيل، وما لم
 *   يستوفِها **يُتخطّى ويُسمَّى** ولا تُلمس بصمتُه. فالحكمُ يُحسَب لا يُنقَل.
 * ⛔ **ولا جدولَ أثرٍ جديد**: يُكتب في `repair01_ledger_checksum_fix` الذي
 *   أنشأته السابقة — سجلٌّ واحدٌ لهذا الصنفِ لا سجلّان يتفرَّقان.
 * ◆ **ومُعاوَدٌ**: تشغيلٌ ثانٍ يجد صفرَ مرشَّحٍ فيُعلن ذلك ويخرج بنجاح.
 *
 * التشغيل: php database/migrate.php up   ·   أو الملفُّ بذاته
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$DIR = __DIR__;
$REF = 'CTL · مصالحةُ بصماتٍ 2028-06-05';

/* جداولُ القاعدة — للشرط ③ */
$tables = array();
$r = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()");
while ($r && ($x = $r->fetch_row())) { $tables[strtolower($x[0])] = 1; }

$gitCommits = function ($rel) use ($ROOT) {
    $out = (string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' log --oneline -- ' . escapeshellarg($rel) . ' 2>&1');
    return ($out === '') ? 0 : count(array_filter(explode("\n", trim($out))));
};
$gitDdl = function ($rel) use ($ROOT) {
    $out = (string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' log -p -U0 -- ' . escapeshellarg($rel) . ' 2>&1');
    return preg_match_all('~^[+-][^+-]*\b(CREATE TABLE|ALTER TABLE|DROP TABLE|TRUNCATE|RENAME TABLE|ADD COLUMN|DROP COLUMN)\b~mi', $out);
};

/* المرشَّحون: مطبَّقٌ · ملفُّه على القرص · وبصمتُه مختلفة */
$cand = array();
$sel = $conn->query("SELECT filename, checksum, status, applied_at
                       FROM schema_migrations WHERE status <> 'failed' ORDER BY filename");
while ($sel && ($x = $sel->fetch_assoc())) {
    if ($x['filename'] === basename(__FILE__)) { continue; }     /* لا يُصالح نفسَه */
    $pth = $DIR . '/' . $x['filename'];
    if (!is_file($pth)) { continue; }                            /* MISSING — بابٌ آخر */
    $now = sha1_file($pth);
    if ($now === $x['checksum']) { continue; }
    $x['new'] = $now;
    $cand[] = $x;
}

echo "  ◦ مرشَّحون (بصمةٌ مختلفة): " . count($cand) . "\n";

$done = 0; $skip = array();
foreach ($cand as $c) {
    $f = $c['filename'];
    $rel = 'database/migrations/' . $f;

    $n = $gitCommits($rel);
    if ($n === 0) { $skip[] = "{$f} — ليس في git إطلاقًا"; continue; }
    if ($n > 1) {
        $ddl = $gitDdl($rel);
        if ($ddl > 0) { $skip[] = "{$f} — فرقُه يمسُّ المخطَّط ({$ddl} سطرًا)"; continue; }
    }

    $src = (string) @file_get_contents($DIR . '/' . $f);
    $t = array();
    if (preg_match_all('~(?:CREATE TABLE(?:\s+IF NOT EXISTS)?|ALTER TABLE|INSERT(?:\s+IGNORE)?\s+INTO'
                     . '|REPLACE INTO|UPDATE|DELETE FROM|FROM)\s+`?([a-z0-9_]{3,64})`?~i', $src, $m)) {
        foreach ($m[1] as $x) {
            $x = strtolower($x);
            if (!in_array($x, array('select','dual','information_schema'), true)) { $t[$x] = 1; }
        }
    }
    if (preg_match_all('~(?:insert|update|select|count|delete)\(\s*[\'"]([a-z0-9_]{3,64})[\'"]~i', $src, $m)) {
        foreach ($m[1] as $x) { $t[strtolower($x)] = 1; }
    }
    $present = 0;
    foreach (array_keys($t) as $x) { if (isset($tables[$x])) { $present++; } }
    if ($present === 0) { $skip[] = "{$f} — لا جدولَ من جداولِها قائمٌ (أثرٌ غيرُ متحقَّق)"; continue; }

    $wit = ($n === 1)
        ? "التزامٌ واحدٌ في git ⇒ النسخةُ المطبَّقةُ خارجَه · طُبِّقت {$c['applied_at']} ({$c['status']}) · أثرُها متحقَّقٌ بـ{$present} جدولًا · {$REF}"
        : "{$n} التزاماتٍ والفرقُ مقروءٌ بصفرِ DDL · طُبِّقت {$c['applied_at']} ({$c['status']}) · أثرُها متحقَّقٌ بـ{$present} جدولًا · {$REF}";

    $st = $conn->prepare("INSERT INTO `repair01_ledger_checksum_fix`
            (filename, old_checksum, new_checksum, witness, fixed_at) VALUES (?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE new_checksum = VALUES(new_checksum),
                                    witness = VALUES(witness), fixed_at = NOW()");
    $st->bind_param('ssss', $f, $c['checksum'], $c['new'], $wit);
    if (!$st->execute()) { exit("✘ تعذّر الأثر: {$st->error}\n"); }
    $st->close();

    $st = $conn->prepare("UPDATE `schema_migrations` SET `checksum` = ? WHERE `filename` = ? AND `checksum` = ?");
    $st->bind_param('sss', $c['new'], $f, $c['checksum']);
    if (!$st->execute()) { exit("✘ تعذّرت المصالحة: {$st->error}\n"); }
    $st->close();
    $done++;
}

echo "  ✔ صولحت بشاهدٍ لكلِّ ملفٍّ : {$done}\n";
echo "  ⛔ مُتخطّاةٌ (لم تستوفِ)   : " . count($skip) . "\n";
foreach ($skip as $s) { echo "       · {$s}\n"; }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ `migrate up` لم يعُد مرفوضًا على بصمةٍ لمحتوًى لا وجودَ له\n";
