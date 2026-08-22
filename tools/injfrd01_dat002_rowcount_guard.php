<?php
/**
 * tools/injfrd01_dat002_rowcount_guard.php
 *   FR-DAT-002 · FR-DAT-006 — قبلَ ⇐ تنفيذٌ ⇐ بعدَ ⇐ مصالحة · واستعادةٌ مجرَّبة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **FR-DAT-002** (P0): «قبلَ أيِّ هجرةٍ أو تنظيف: عددُ الصفوفِ قبلَ يساوي بعدَ
 *   **إلا بحذفٍ معلَنٍ بسببِه**» · وسلوكُ الفشل «**إرجاعٌ عندَ فارقٍ غيرِ مفسَّر**»
 *   · ومعيارُه «تقريرُ قبلَ وبعدَ لكلِّ جدولٍ وصفرُ فارقٍ غيرِ مفسَّر».
 *
 * ◆ **FR-DAT-006** (P1): «النسخُ والاستعادةُ يُعادان **تجريبًا** بعدَ كلِّ تغييرٍ
 *   في المخطَّط» · ومعيارُه «**تقريرُ استعادةٍ حقيقيةٍ بزمنِها ونتيجتِها**».
 *   فلا يكفي أن تُوصَف الاستعادة — تُشغَّل ويُقاس زمنُها.
 *
 * التشغيل:
 *   php tools/injfrd01_dat002_rowcount_guard.php --snapshot=before
 *   … نفِّذ الهجرةَ أو التنظيف …
 *   php tools/injfrd01_dat002_rowcount_guard.php --compare [--allow=جدول:عدد:سبب]
 *   php tools/injfrd01_dat002_rowcount_guard.php --restore-drill
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$SNAP = sys_get_temp_dir() . '/ems_rowcount_snapshot.json';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }

$mode = '';
$allow = array();
foreach ($argv as $a) {
    if (strpos($a, '--snapshot=') === 0) { $mode = 'snapshot'; }
    if ($a === '--compare')              { $mode = 'compare'; }
    if ($a === '--restore-drill')        { $mode = 'drill'; }
    if (strpos($a, '--allow=') === 0) {
        $parts = explode(':', substr($a, 8), 3);
        if (count($parts) === 3) { $allow[$parts[0]] = array('n' => (int) $parts[1], 'why' => $parts[2]); }
    }
}
if ($mode === '') { exit("مرِّر --snapshot=before أو --compare أو --restore-drill\n"); }

/* ─────────────────────── الاستعادةُ المجرَّبة ────────────────────────────── */
if ($mode === 'drill') {
    echo "════ FR-DAT-006 · استعادةٌ **مجرَّبةٌ حقيقيةٌ** لا موصوفة ════\n";
    $schema = $ROOT . '/database/schema/schema.sql';
    $seed   = $ROOT . '/database/schema/seed_reference.sql';
    foreach (array($schema, $seed) as $f) {
        if (!is_file($f)) { exit("⛔ مصدرٌ مفقود: " . basename($f) . "\n"); }
    }
    $probe = 'ems_restore_drill';
    $root = new mysqli('127.0.0.1', 'root', '', null, $port);
    if ($root->connect_errno) { exit("⛔ تعذّر الاتصالُ الإداريّ\n"); }
    $root->query("DROP DATABASE IF EXISTS `{$probe}`");
    $root->query("CREATE DATABASE `{$probe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    /* ◆ **الاستعادةُ تُجرَّب بالمسارِ المعتمَدِ لا بأيِّ مسار**: أوّلُ كتابةٍ هنا
     *   استعادت `schema.sql` بعميلِ سطرِ الأوامر فرسبت عند أوّلِ قادحٍ في السطر
     *   15829 — وكِدتُ أُعلنها عطبًا في المصنوعة. **والمصنوعةُ سليمةٌ والقياسُ
     *   كان خاطئًا**: `SchemaDumper` يمتنع عن `DELIMITER` **عمدًا** (بنصِّ تعليقِه
     *   في `app/Install/SchemaDumper.php:205`) لأن مستهلكَها المعتمَد هو
     *   `Installer::runSqlFile()` بـ`multi_query` — والخادمُ يفهم `BEGIN … END`
     *   بلا فاصلٍ بديل، بينما عميلُ الأمرِ يقطعها عند أوّلِ `;`.
     *   ⇒ **تُقاس الاستعادةُ بالطريقِ الذي يسلكه التثبيتُ فعلًا**، وإلا كان
     *   الرسوبُ رسوبَ المقياسِ لا رسوبَ المصنوعة.
     * ◆ **وعدُّ القوادحِ داخلٌ في الحكم**: 667 جدولًا وصفرُ قادحٍ استعادةٌ فاشلةٌ
     *   تبدو ناجحةً — فحرّاسُ القاعدةِ هم ما يُفقَد صامتًا. والمقامُ يُقرأ من
     *   المصنوعةِ نفسِها (`CREATE TRIGGER` فيها) لا من رقمٍ محفوظ.
     * ◆ ونظيرُ هذا للنسخةِ اليومية موجودٌ سلفًا في `tools/ops02_restore_drill.php`
     *   — وهذا يخصُّ **مصنوعةَ التثبيتِ النظيف**، فلا يُغني أحدُهما عن الآخر. */
    $t0 = microtime(true);
    $okAll = true;
    foreach (array('schema' => $schema, 'seed' => $seed) as $label => $file) {
        $t1  = microtime(true);
        $sql = (string) file_get_contents($file);
        if (substr($sql, 0, 3) === "\xEF\xBB\xBF") { $sql = substr($sql, 3); }
        $c = new mysqli('127.0.0.1', 'root', '', $probe, $port);
        $c->set_charset('utf8mb4');
        $c->query("SET collation_connection = 'utf8mb4_unicode_ci'");
        $err = '';
        if (!$c->multi_query($sql)) {
            $err = $c->error;
        } else {
            do {
                if ($r2 = $c->store_result()) { $r2->free(); }
                if ($c->error !== '') { $err = $c->error; break; }
            } while ($c->more_results() && $c->next_result());
        }
        $sec = round(microtime(true) - $t1, 2);
        $c->close();
        if ($err !== '') { $okAll = false; }
        printf("  %s %-8s %6.2f ثانية%s\n", $err === '' ? '✔' : '✘', $label, $sec,
               $err === '' ? '' : ' — ' . mb_substr($err, 0, 70));
    }

    $probeConn = new mysqli('127.0.0.1', 'root', '', $probe, $port);
    $tables = 0; $rows = 0; $trg = 0;
    if (!$probeConn->connect_errno) {
        $q = $probeConn->query("SELECT COUNT(*) FROM information_schema.TABLES
                                 WHERE TABLE_SCHEMA = '{$probe}' AND TABLE_TYPE = 'BASE TABLE'");
        $tables = $q ? (int) $q->fetch_row()[0] : 0;
        $q = $probeConn->query("SELECT COUNT(*) FROM information_schema.TRIGGERS
                                 WHERE TRIGGER_SCHEMA = '{$probe}'");
        $trg = $q ? (int) $q->fetch_row()[0] : 0;
        $q = $probeConn->query("SELECT COUNT(*) FROM `roles`");
        $rows = $q ? (int) $q->fetch_row()[0] : 0;
    }
    /* ◆ **الحزامُ يُثبت أن العدَّ حيٌّ لا مفترَض**: بـ`--belt-drop-trigger`
     *   يُسقَط قادحٌ واحدٌ من قاعدةِ التجربةِ بعدَ الاستعادة، **ويُتحقَّق من
     *   وقوعِ الإسقاطِ قبلَ القياس** — فإن لم ترسب التجربةُ فالعدُّ لا يُقرأ.
     *   (حزامٌ لا يدسُّ شيئًا لا يُثبت شيئًا — وقعت مرَّتَين في هذه الجولة.) */
    if (in_array('--belt-drop-trigger', $argv, true) && $trg > 0) {
        $one = $probeConn->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS
                                    WHERE TRIGGER_SCHEMA = '{$probe}' LIMIT 1")->fetch_row()[0];
        $probeConn->query("DROP TRIGGER `{$one}`");
        $after = (int) $probeConn->query("SELECT COUNT(*) FROM information_schema.TRIGGERS
                                            WHERE TRIGGER_SCHEMA = '{$probe}'")->fetch_row()[0];
        if ($after !== $trg - 1) {
            echo "  ⛔ **تعذّر إسقاطُ القادحِ** — وحزامٌ لا يكسر شيئًا لا يُثبت شيئًا. أُوقِف.
";
            $root->query("DROP DATABASE IF EXISTS `{$probe}`"); exit(1);
        }
        echo "  ◆ حزامٌ: أُسقط القادحُ {$one} — **والإسقاطُ مُثبَتٌ ({$trg} ⇐ {$after})**
";
        $trg = $after;
    }
    $wantTrg = preg_match_all('/^\s*CREATE\s+TRIGGER\s/mi', (string) file_get_contents($schema));
    $elapsed = round(microtime(true) - $t0, 2);

    printf("\n  الجداولُ المستعادة: %d · **القوادح: %d من %d** · أدوارٌ مقروءة: %d\n",
           $tables, $trg, $wantTrg, $rows);
    $pass = ($okAll && $tables > 0 && $rows > 0 && $trg === $wantTrg);
    printf("  **زمنُ الاستعادةِ الكامل: %.2f ثانية** · النتيجة: %s\n",
           $elapsed, $pass ? '**نجحت**' : '**فشلت**');
    $root->query("DROP DATABASE IF EXISTS `{$probe}`");
    echo "  ✔ أُسقطت قاعدةُ التجربة — لا أثرَ باقٍ\n";
    /* ◆ وفي وضعِ الحزامِ **الرسوبُ هو النجاح** — فيُقلَب رمزُ الخروجِ ليُكنَس آليًّا */
    if (in_array('--belt-drop-trigger', $argv, true)) {
        echo "
◆ الحزامُ السلبيّ: **يُتوقَّع رسوبٌ** — فنجاحُه أن تفشلَ التجربة.
";
        exit($pass ? 1 : 0);
    }
    if (!$pass) {
        echo "\n⛔ **فشلُ الاستعادةِ يمنع الإصدار** — بنصِّ المطلب.\n";
        exit(1);
    }
    echo "\n◆ وهذا **تقريرُ استعادةٍ حقيقيةٍ بزمنِها ونتيجتِها** — لا وصفُ إجراء.\n";
    exit(0);
}

/* ─────────────────────── لقطةُ الأعداد ────────────────────────────────── */
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

function counts(mysqli $db)
{
    $out = array();
    $r = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                      ORDER BY TABLE_NAME");
    $names = array();
    while ($r && $x = $r->fetch_row()) { $names[] = $x[0]; }
    foreach ($names as $t) {
        $q = @$db->query("SELECT COUNT(*) FROM `{$t}`");
        $out[$t] = $q ? (int) $q->fetch_row()[0] : -1;
    }
    return $out;
}

if ($mode === 'snapshot') {
    $c = counts($db);
    file_put_contents($SNAP, json_encode(array(
        'taken_at' => date('Y-m-d H:i:s'), 'tables' => count($c), 'counts' => $c),
        JSON_PRETTY_PRINT));
    printf("✔ لقطةُ الأعداد: %d جدولًا · %d صفًّا إجمالًا\n", count($c), array_sum($c));
    echo "  ◆ نفِّذ الآن، ثم شغّل --compare.\n";
    exit(0);
}

/* ─────────────────────── المصالحة ─────────────────────────────────────── */
if (!is_file($SNAP)) { exit("⛔ لا لقطةَ قبليّة — شغّل --snapshot=before أولًا\n"); }
$before = json_decode((string) file_get_contents($SNAP), true);
$after  = counts($db);

echo "════ FR-DAT-002 · قبلَ ⇐ بعدَ ⇐ مصالحة ════\n";
printf("  اللقطةُ القبليةُ: %s · %d جدولًا\n\n", $before['taken_at'], (int) $before['tables']);

$unexplained = array(); $explained = array(); $added = array();
foreach ($before['counts'] as $t => $n0) {
    $n1 = isset($after[$t]) ? $after[$t] : null;
    if ($n1 === null) { $unexplained[] = "{$t}: الجدولُ **اختفى**"; continue; }
    if ($n1 === $n0)  { continue; }
    $diff = $n1 - $n0;
    if (isset($allow[$t]) && $allow[$t]['n'] === $diff) {
        $explained[] = "{$t}: {$n0} ⇐ {$n1} ({$diff}) — {$allow[$t]['why']}";
    } else {
        $unexplained[] = "{$t}: {$n0} ⇐ {$n1} (**{$diff}**)";
    }
}
foreach ($after as $t => $n1) {
    if (!isset($before['counts'][$t])) { $added[] = "{$t}: جدولٌ جديدٌ بـ{$n1} صفًّا"; }
}

if ($explained) {
    echo "  ◆ فروقٌ **معلَنةٌ بسببِها**:\n";
    foreach ($explained as $e) { echo "     · {$e}\n"; }
}
if ($added) {
    echo "  ◆ جداولُ جديدةٌ (لا فقدَ فيها):\n";
    foreach (array_slice($added, 0, 6) as $e) { echo "     + {$e}\n"; }
}
if ($unexplained) {
    echo "\n  ✘ **فروقٌ غيرُ مفسَّرة** — وسلوكُ الفشلِ إرجاعٌ:\n";
    foreach (array_slice($unexplained, 0, 12) as $e) { echo "     · {$e}\n"; }
    if (count($unexplained) > 12) { echo "     … و" . (count($unexplained) - 12) . " غيرُها\n"; }
} else {
    echo "\n  ✔ **صفرُ فارقٍ غيرِ مفسَّر**\n";
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("جداولُ قبلًا=%d · بعدًا=%d · مفسَّرٌ=%d · **غيرُ مفسَّرٍ=%d**\n",
       (int) $before['tables'], count($after), count($explained), count($unexplained));
exit(empty($unexplained) ? 0 : 1);
