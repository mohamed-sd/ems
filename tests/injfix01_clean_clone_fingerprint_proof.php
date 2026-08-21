<?php
/**
 * tests/injfix01_clean_clone_fingerprint_proof.php
 *   استنساخٌ نظيفٌ ببصمةٍ مطابقة — INJ-FIX-01 · الموجة ب · الحاجز ① · GAP-18
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ المالك**: «Clean Clone → Build → Schema fingerprint = current schema
 *   fingerprint» و«يُقارَن ببصمةٍ لا بالنظر».
 *
 * ◆ **والاستنساخُ يُبنى من مُخرَجِ المُثبِّتِ لا من نسخةِ القاعدة**: الغرضُ
 *   إثباتُ أن `database/schema/` **يكفي وحدَه** لإعادةِ بناءِ النظام. ونسخُ
 *   القاعدةِ إلى قاعدةٍ يثبت أن النسخَ يعمل لا أن البناءَ ممكن.
 *
 * ◆ **والبصمةُ تُحسب على البنيةِ لا على البيانات**: اسمُ الجدولِ والعمودِ
 *   ونوعُه وترتيبُه — ويُستثنى ما يتغيّر بطبيعتِه (`AUTO_INCREMENT` الحاليّ).
 *
 * ◆ **ولا يُبنى الاستنساخُ في مساحةِ الإنتاج**: يُبنى في `test_injfix_clone`
 *   ويُهدَم في الختام. ولو رسب الفحصُ **يبقى قائمًا للفحصِ اليدويّ** — فالهدمُ
 *   عند النجاحِ وحدَه، وإلا ضاع الدليلُ الذي رسب من أجلِه.
 *
 * التشغيل: php tests/injfix01_clean_clone_fingerprint_proof.php
 *          php tests/injfix01_clean_clone_fingerprint_proof.php --keep
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

$KEEP  = in_array('--keep', $argv, true);
$CLONE = 'test_injfix_clone';
$pass = 0; $fail = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$user = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$pw   = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$live = ems_env('DB_NAME');

echo "════ استنساخٌ نظيفٌ ببصمةٍ مطابقة — GAP-18 ════\n";
echo "  الحيّ: {$live} · الاستنساخ: {$CLONE} · {$host}:{$port}\n";

$c = new mysqli($host, $user, $pw, '', $port);
if ($c->connect_errno) { exit("✘ تعذّر الاتصال: {$c->connect_error}\n"); }
$c->set_charset('utf8mb4');

/** بصمةُ بنيةٍ لقاعدةٍ — الاسمُ والعمودُ ونوعُه وترتيبُه، بلا AUTO_INCREMENT الحاليّ. */
function schemaFingerprint(mysqli $c, $db)
{
    $h = hash_init('sha256'); $cols = 0; $tables = array();
    $st = $c->prepare(
        "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?
          ORDER BY TABLE_NAME, ORDINAL_POSITION");
    $st->bind_param('s', $db); $st->execute();
    $r = $st->get_result();
    while ($x = $r->fetch_assoc()) {
        hash_update($h, implode('|', $x) . "\n");
        $cols++; $tables[$x['TABLE_NAME']] = true;
    }
    $st->close();
    return array('fp' => substr(hash_final($h), 0, 16), 'cols' => $cols, 'tables' => $tables);
}

/* ── ① بناءُ الاستنساخِ من مُخرَجِ المُثبِّتِ وحدَه ─────────────────────────── */
echo "\n── ① البناء ──\n";
$schemaFile = $ROOT . '/database/schema/schema.sql';
ok(is_file($schemaFile), 'ملفُّ المخططِ موجود', $pass, $fail, basename($schemaFile) . ' · ' . number_format(filesize($schemaFile) / 1024, 1) . ' ك.ب');

$c->query("DROP DATABASE IF EXISTS `{$CLONE}`");
if (!$c->query("CREATE DATABASE `{$CLONE}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    exit("✘ تعذّر إنشاءُ الاستنساخ: {$c->error}\n");
}
ok(true, 'أُنشئت قاعدةُ استنساخٍ فارغة', $pass, $fail, $CLONE);

/* ◆ **الاستيرادُ بمسارِ المُثبِّتِ نفسِه لا بعميلِ سطرِ الأوامر**: الغرضُ إثباتُ
     أن **التثبيتَ** يُعيد البناء، لا أن `mysql.exe` يستورد ملفًّا. و`Installer`
     يستورد بـ`multi_query` — فالخادمُ هو من يفصل العبارات. واختبارُ الاستيرادِ
     بعميلٍ آخرَ يُثبت شيئًا لا يجري في الإنتاج. */
$imp = new mysqli($host, $user, $pw, $CLONE, $port);
if ($imp->connect_errno) { exit("✘ تعذّر الاتصالُ بالاستنساخ: {$imp->connect_error}\n"); }
$imp->set_charset('utf8mb4');
$sql = (string) file_get_contents($schemaFile);
if (substr($sql, 0, 3) === "\xEF\xBB\xBF") { $sql = substr($sql, 3); }
$impErr = '';
if (!$imp->multi_query($sql)) {
    $impErr = $imp->error;
} else {
    do {
        if ($res = $imp->store_result()) { $res->free(); }
        if ($imp->error !== '') { $impErr = $imp->error; break; }
    } while ($imp->more_results() && $imp->next_result());
    if ($impErr === '' && $imp->error !== '') { $impErr = $imp->error; }
}
$imp->close();
ok($impErr === '', 'استُورد المخططُ بمسارِ المُثبِّتِ (multi_query) بلا خطأ', $pass, $fail,
   $impErr === '' ? 'صفرُ خطأ' : $impErr);

/* ── ② المقارنةُ ببصمةٍ لا بالنظر ──────────────────────────────────────────── */
echo "\n── ② المقارنة ──\n";
$L = schemaFingerprint($c, $live);
$C = schemaFingerprint($c, $CLONE);

echo "  الحيّ:      جداول=" . count($L['tables']) . " أعمدة={$L['cols']} بصمة={$L['fp']}\n";
echo "  الاستنساخ: جداول=" . count($C['tables']) . " أعمدة={$C['cols']} بصمة={$C['fp']}\n";

$onlyLive  = array_diff_key($L['tables'], $C['tables']);
$onlyClone = array_diff_key($C['tables'], $L['tables']);
ok(count($onlyLive) === 0, 'صفرُ جدولٍ في الحيِّ غائبٍ عن الاستنساخ', $pass, $fail,
   count($onlyLive) ? implode(' · ', array_slice(array_keys($onlyLive), 0, 8)) : '—');
ok(count($onlyClone) === 0, 'صفرُ جدولٍ في الاستنساخِ زائدٍ عن الحيّ', $pass, $fail,
   count($onlyClone) ? implode(' · ', array_slice(array_keys($onlyClone), 0, 8)) : '—');
ok($L['fp'] === $C['fp'], '◆ بصمةُ المخططِ مطابقة', $pass, $fail,
   $L['fp'] === $C['fp'] ? $L['fp'] : "{$L['fp']} ≠ {$C['fp']} · فرقُ أعمدةٍ=" . ($L['cols'] - $C['cols']));

/* ── ③ ما لا تُثبته البصمةُ — يُقال ولا يُطوى ──────────────────────────────── */
echo "\n── ③ حدُّ ما تُثبته هذه البصمة ──\n";
foreach (array('TRIGGERS' => 'قوادح', 'ROUTINES' => 'إجراءات') as $t => $ar) {
    $q = $c->query("SELECT COUNT(*) FROM information_schema.{$t} WHERE " .
                   ($t === 'TRIGGERS' ? 'TRIGGER_SCHEMA' : 'ROUTINE_SCHEMA') . " = '{$live}'");
    $nLive = $q ? (int) $q->fetch_row()[0] : -1;
    $q = $c->query("SELECT COUNT(*) FROM information_schema.{$t} WHERE " .
                   ($t === 'TRIGGERS' ? 'TRIGGER_SCHEMA' : 'ROUTINE_SCHEMA') . " = '{$CLONE}'");
    $nClone = $q ? (int) $q->fetch_row()[0] : -1;
    ok($nLive === $nClone, "{$ar}: الحيُّ = الاستنساخ", $pass, $fail, "حيّ={$nLive} · استنساخ={$nClone}");
    /* ◆ تشخيصٌ مُسمًّى لا رسوبٌ مبهم: القادحُ يلزمه امتيازٌ حين يعمل السجلُّ
         الثنائيّ، وحسابُ النشرِ لا يملكه. فالرسوبُ **قرارُ امتيازاتٍ معلَّقٌ**
         لا عيبٌ في المُصدِّرِ ولا في الملفّ. */
    if ($t === 'TRIGGERS' && $nLive !== $nClone) {
        $vars = array();
        foreach (array('log_bin', 'log_bin_trust_function_creators') as $v) {
            $q = $c->query("SHOW VARIABLES LIKE '{$v}'");
            $row = $q ? $q->fetch_assoc() : null;
            $vars[$v] = $row ? $row['Value'] : '?';
        }
        $q = $c->query("SELECT CURRENT_USER()");
        $who = $q ? $q->fetch_row()[0] : '?';
        echo "        ↳ الحساب: {$who}\n";
        echo "        ↳ log_bin={$vars['log_bin']} · log_bin_trust_function_creators={$vars['log_bin_trust_function_creators']}\n";
        echo "        ↳ ◆ إنشاءُ القادحِ يلزمه SUPER حين يعمل السجلُّ الثنائيُّ وتكون\n";
        echo "           الثقةُ مطفأة. فحسابُ النشرِ **لا يستطيع إعادةَ بناءِ الحرّاسِ**\n";
        echo "           الأربعةِ والثلاثين — والمخططُ يصدّرها الآن لكنَّ الاستيرادَ يُردّ.\n";
        echo "           ⇐ قرارٌ إداريّ: منحُ الامتيازِ لحسابِ النشر · أو تشغيلُ طورِ\n";
        echo "             القوادحِ بحسابٍ إداريّ · أو `log_bin_trust_function_creators=1`.\n";
        echo "           وهذه صورةُ GAP-33 نفسِها: «قادحٌ لا يُرى قد لا يُعاد بناؤه».\n";
    }
}
echo "  ◆ والبصمةُ تقيس **البنيةَ** لا البيانات: صفرُ صفٍّ مرجعيٍّ في الاستنساخ\n";
echo "    حتى يُستورَد `seed_reference.sql` — وذاك حاجزٌ آخر لا هذا.\n";
$q = $c->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = '{$live}'");
if ($q && (int) $q->fetch_row()[0] === 0) {
    echo "  ◆ **وقوادحُ الحيِّ صفرٌ في الجرد** — و GAP-33 يقول إن القادحَ قد يُحجب\n";
    echo "    عن المستخدمِ بامتيازٍ فلا يظهر. فصفرٌ هنا **لا يُقرأ «لا قوادحَ»**\n";
    echo "    بل «لا قادحَ يراه هذا الحساب» — ويلزمه جسٌّ وظيفيٌّ مستقل.\n";
}

/* ── الهدمُ عند النجاحِ وحدَه ────────────────────────────────────────────── */
if ($fail === 0 && !$KEEP) {
    $c->query("DROP DATABASE `{$CLONE}`");
    echo "\n  ↺ هُدم الاستنساخُ بعدَ النجاح.\n";
} else {
    echo "\n  ◆ الاستنساخُ **باقٍ** للفحصِ اليدويّ: `{$CLONE}`\n";
}

echo "───────────────────────────────────────────────────────────────\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";
exit($fail === 0 ? 0 : 1);
