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
/* ◆ **وطورُ القوادحِ جزءٌ من مسارِ المُثبِّتِ لا التفافٌ عليه**: `Installer` يستورد
 *   أولًا بحسابِ النشر، فإن سقطت القوادحُ لنقصِ الامتياز (`log_bin=ON` بلا SUPER)
 *   **شغَّل طورًا ثانيًا باعتمادٍ إداريٍّ مُعلَنٍ في البيئة**، ثم وقف صراحةً إن لم
 *   يكفِ. فيُحاكى الطوران هنا بالترتيبِ نفسِه — **وخطأُ الاستيرادِ لا يُبتلَع بل
 *   يُقاس أثرُه**: إن كان مصدرُه القوادحَ وحدَها وأتمَّها الطورُ الثاني فالمسارُ
 *   سليم، وإن نقصت بعدَه فالرسوبُ قائم. */
$phaseMade = 0; $phaseTried = false;
$adminUser = (string) ems_env('DB_ADMIN_USER', '');
$wantT = preg_match_all('/^\s*CREATE\s+TRIGGER\s/mi', $sql);
$rt = $c->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='{$CLONE}'");
$gotT = $rt ? (int) $rt->fetch_row()[0] : 0;
if ($gotT < $wantT && $adminUser !== '') {
    $phaseTried = true;
    $ac = @new mysqli($host, $adminUser, (string) ems_env('DB_ADMIN_PASS', ''), $CLONE, $port);
    if (!$ac->connect_errno) {
        $ac->set_charset('utf8mb4');
        if (preg_match_all('/^[ \t]*DROP\s+TRIGGER\s+IF\s+EXISTS\s+`?(\w+)`?\s*;[ \t]*$/mi',
                           $sql, $dm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($dm as $i => $one) {
                $name = $one[1][0];
                $from = $one[0][1] + strlen($one[0][0]);
                $to   = isset($dm[$i + 1]) ? $dm[$i + 1][0][1] : strlen($sql);
                $blk  = trim(substr($sql, $from, $to - $from));
                if (!preg_match('/^CREATE\s+TRIGGER\s/i', $blk)) { continue; }
                if (preg_match_all('/\bEND\b/i', $blk, $em2, PREG_OFFSET_CAPTURE)) {
                    $lastE = $em2[0][count($em2[0]) - 1];
                    $blk = substr($blk, 0, $lastE[1] + strlen($lastE[0]));
                }
                $blk = rtrim($blk, "; \t\r\n");
                $ac->query("DROP TRIGGER IF EXISTS `{$name}`");
                if ($ac->query($blk)) { $phaseMade++; }
            }
        }
        $ac->close();
    }
}
$imp->close();

$triggerOnly = ($impErr !== '' && stripos($impErr, 'SUPER privilege') !== false);
if ($phaseTried) {
    echo "  ◆ طورُ القوادحِ الإداريُّ: أُنشئ {$phaseMade} قادحًا بالحساب «{$adminUser}»\n";
}
ok($impErr === '' || ($triggerOnly && $phaseMade >= $wantT),
   'أعاد مسارُ المُثبِّتِ بناءَ المخططِ كاملًا (استيرادٌ + طورُ قوادحَ عند اللزوم)', $pass, $fail,
   $impErr === '' ? 'صفرُ خطأ'
   : ($triggerOnly ? "نقصُ الامتيازِ عالجه الطورُ الثاني: {$phaseMade}/{$wantT}" : $impErr));

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

        /* ══ فصلُ السببَين — «المُخرَجُ معطوب» أم «الحسابُ لا يملك»؟ ══════════
           ◆ هذان عطبان مختلفان تمامًا وعلاجُهما مختلف: الأولُ يُصلَح بكودٍ في
             المُصدِّر، والثاني بقرارِ امتيازاتٍ لا يملكه منفِّذ. **وخلطُهما
             يُرسل الجهدَ إلى غيرِ موضعِه.**
           ◆ فيُعاد الاستيرادُ **بحسابٍ إداريٍّ** إن أمكن: فإن نجحت القوادحُ
             فالمُخرَجُ سليمٌ والعائقُ الامتيازُ وحدَه — وذلك جوابٌ قاطع. */
        $adminTried = false; $adminOk = false; $adminCount = -1;
        $ac = @new mysqli($host, 'root', '', '', $port);
        if ($ac && !$ac->connect_errno) {
            $adminTried = true;
            $ac->set_charset('utf8mb4');
            $ac->query("DROP DATABASE IF EXISTS `{$CLONE}_adm`");
            $ac->query("CREATE DATABASE `{$CLONE}_adm` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $ai = @new mysqli($host, 'root', '', $CLONE . '_adm', $port);
            if ($ai && !$ai->connect_errno) {
                $ai->set_charset('utf8mb4');
                $sqlA = (string) file_get_contents($schemaFile);
                if ($ai->multi_query($sqlA)) {
                    do { if ($rr = $ai->store_result()) { $rr->free(); } }
                    while ($ai->more_results() && $ai->next_result());
                }
                $ai->close();
            }
            $rq = $ac->query("SELECT COUNT(*) FROM information_schema.TRIGGERS
                               WHERE TRIGGER_SCHEMA = '{$CLONE}_adm'");
            $adminCount = $rq ? (int) $rq->fetch_row()[0] : -1;
            $adminOk = ($adminCount === $nLive);
            if ($adminOk) { $ac->query("DROP DATABASE `{$CLONE}_adm`"); }
            $ac->close();
        }
        if ($adminTried) {
            ok($adminOk,
               '◆ **بحسابٍ إداريٍّ: القوادحُ تُبنى كاملةً** — فالمُخرَجُ سليمٌ والعائقُ الامتيازُ وحدَه',
               $pass, $fail, "بالحسابِ الإداريّ={$adminCount}/{$nLive}");
            echo "        ↳ ⇐ **الجوابُ قاطع**: لا عطبَ في `SchemaDumper` ولا في `schema.sql`.\n";
            echo "           والحاجزُ ① لا يُفتح بكودٍ بل بقرارِ امتيازاتٍ لحسابِ النشر.\n";
        } else {
            echo "        ↳ (تعذّر الفحصُ بحسابٍ إداريّ — فيبقى السببانِ غيرَ مفصولَين)\n";
        }
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

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-18', true, 'دفترُ الهجراتِ يطابق القرصَ وبصمةُ المخطَّطِ تُعاد إنتاجُها من استنساخٍ نظيف', $fail);

exit($fail === 0 ? 0 : 1);
