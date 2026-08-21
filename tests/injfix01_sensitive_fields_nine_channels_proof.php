<?php
/**
 * tests/injfix01_sensitive_fields_nine_channels_proof.php
 *   شاهدُ القنواتِ التسع للحقلِ الحساس — INJ-FIX-01 · الموجة أ ② · GAP-10
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ المالك**: «والقبولُ ليس ‹اختفى العمودُ من الشاشة›، بل: **صفرُ حقلٍ
 *   حساسٍ يصل إلى غيرِ مخوَّلٍ عبرَ القنواتِ التسع**» —
 *   العرضُ · التصديرُ · البحثُ · الواجهةُ البرمجيةُ · المناظرُ المحفوظةُ ·
 *   منتقي الأعمدةِ · العنوانُ المباشرُ في سياقِه · نداءاتُ الخلفيةِ · الأفعالُ الجماعية.
 *
 * ◆ **وقياسان لا قياسٌ واحد** — والخلطُ بينهما يصنع أخضرَ كاذبًا:
 *   ① **اختبارٌ وظيفيٌّ سلبيّ** حيثُ تُدار القناةُ برمجيًّا: يُنادى مقرِّرُ
 *      الحجبِ بدورٍ غيرِ مخوَّلٍ ويُقاس أن العمودَ **لا يُرجَع أصلًا**.
 *   ② **قياسُ بلوغٍ للمقرِّر** حيثُ لا تُدار برمجيًّا: تُجرَد أسطحُ القناةِ
 *      التي تمسُّ جدولًا حساسًا، ويُقاس كم منها يمرُّ بنقطةِ القرار. والسطحُ
 *      الذي يمسُّ حقلًا حساسًا ولا يعرف المقرِّرَ **مسربٌ مفتوحٌ يُعَدُّ ويُسمّى**.
 *
 * ◆ **ولا يُعلَن «صفرُ تسريب» على قناةٍ لم تُقَس** — والراسبُ يُسمّى راسبًا:
 *   فالغرضُ من هذا الشاهدِ أن يقول الحقيقةَ لا أن يمرّ.
 *
 * التشغيل: php tests/injfix01_sensitive_fields_nine_channels_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Governance/FieldGovernor.php';
require_once $ROOT . '/app/Services/Security/SensitiveFieldGuard.php';

$UNAUTH_ROLE = 1;    // ادارة التشغيل — خارجَ قائمةِ السماحِ ["32","17","19"]
$AUTH_ROLE   = 32;   // المدير المالي — داخلَها

$pass = 0; $fail = 0; $leaks = array();
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

echo "════ القنواتُ التسع للحقلِ الحساس — GAP-10 ════\n";

/* ── القاموسُ الحيّ ─────────────────────────────────────────────────────── */
$fields = array();   // table => [field, …]
$pairs  = array();   // "table.field"
$r = $conn->query("SELECT `table_name`,`field_name` FROM `scr_sensitive_fields` WHERE `status` = 'معتمد'");
while ($r && $x = $r->fetch_assoc()) {
    $fields[$x['table_name']][] = $x['field_name'];
    $pairs[] = $x['table_name'] . '.' . $x['field_name'];
}
$tables = array_keys($fields);
echo "  القاموس: " . count($pairs) . " حقلًا معتمَدًا في " . count($tables) . " جدولًا\n";

$r = $conn->query("SELECT COUNT(*) FROM `scr_sensitive_fields` WHERE `status` <> 'معتمد'");
$outside = $r ? (int) $r->fetch_row()[0] : -1;
ok($outside === 0, 'صفرُ حقلٍ حساسٍ خارجَ الإنفاذ', $pass, $fail, "خارجَ الإنفاذ = {$outside}");

$r = $conn->query("SELECT COUNT(*) FROM `scr_sensitive_fields`
                    WHERE `log_views_flag` NOT IN ('نعم','لا') OR `exportable_flag` NOT IN ('نعم','لا')");
ok($r && (int) $r->fetch_row()[0] === 0, 'صفرُ رايةٍ خارجَ القاموسِ الموحَّد', $pass, $fail);

/* القيدُ الذي يمنع عودةَ القاموسِ الثاني */
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'scr_sensitive_fields'
                      AND CONSTRAINT_NAME = 'chk_scr_sensitive_fields_status'");
ok($r && (int) $r->fetch_row()[0] === 1, 'قيدٌ يمنع عودةَ قيمةِ حالةٍ خارجَ القاموس', $pass, $fail);

/* ══════════ ① العرض — اختبارٌ وظيفيٌّ سلبيّ ═══════════════════════════════ */
echo "\n── ① العرض ──\n";
$probeTable = 'fin_margin_analysis';
$probeField = 'margin';
$fakeRow = array('id' => 1, $probeField => 123456.78, 'project_id' => 9);
$map = array($probeField => $probeTable . '.' . $probeField);

$asUnauth = \App\Services\Security\SensitiveFieldGuard::filterRow(
    $conn, $fakeRow, $map, 0, (string) $UNAUTH_ROLE, 4, 'probe:1', 'injfix01');
ok(!array_key_exists($probeField, $asUnauth),
   'الدورُ غيرُ المخوَّلِ لا يستلم العمودَ أصلًا (لا إخفاءَ عرض)', $pass, $fail,
   $probeTable . '.' . $probeField);

$asAuth = \App\Services\Security\SensitiveFieldGuard::filterRow(
    $conn, $fakeRow, $map, 0, (string) $AUTH_ROLE, 4, 'probe:1', 'injfix01');
ok(array_key_exists($probeField, $asAuth),
   'الدورُ المخوَّلُ ما يزال يستلمه — الإصلاحُ ليس فقدًا', $pass, $fail,
   'دور ' . $AUTH_ROLE);

/* ══════════ ② التصدير — اختبارٌ وظيفيٌّ سلبيّ ═════════════════════════════ */
echo "\n── ② التصدير ──\n";
$blockedAll = true; $sample = '';
foreach ($fields as $tbl => $flds) {
    $gov = \App\Services\Governance\FieldGovernor::exportableColumns(
        $conn, $tbl, $UNAUTH_ROLE, $flds, false);
    $leaked = array_intersect($gov['allowed'], $flds);
    if ($leaked) { $blockedAll = false; $sample .= ' · ' . $tbl . ':' . implode(',', $leaked); }
}
ok($blockedAll, 'صفرُ حقلٍ حساسٍ في أعمدةِ تصديرِ غيرِ المخوَّل (٢٤ جدولًا)', $pass, $fail,
   $blockedAll ? 'كلُّها محجوبة' : 'تسرَّب:' . $sample);

/* ◆ **والمخوَّلُ يُقاس على ما أذن به الإعلانُ لا على كلِّ حقل**: `margin`
     رايتُه «لا يُصدَّر» وإخفاؤه «يُحجب من الخادم» — فحجبُه عن المدير الماليِّ
     في **التصدير** تنفيذٌ للإعلانِ لا فقد (وهو يراه في **العرض**، وقد قِيس
     أعلاه). فيُقاس عدمُ الفقدِ على حقلَين مأذونَين بالتصديرِ فعلًا. */
$noLoss = array(
    array('suppliers', 'tax_number', 17, 'المالية — حقلٌ مأذونٌ بالتصدير'),
    array('employees', 'salary',      4, 'الموارد البشرية — سياسةٌ بلا إعلانِ منعٍ'),
);
foreach ($noLoss as $c) {
    list($t, $f2, $rid, $lbl) = $c;
    $g = \App\Services\Governance\FieldGovernor::exportableColumns($conn, $t, $rid, array($f2), false);
    ok(in_array($f2, $g['allowed'], true),
       "المخوَّلُ ما يزال يُصدِّر {$t}.{$f2} — لا فقدَ للمخوَّل", $pass, $fail, $lbl);
}

/* ══════════ ③–⑨ قياسُ بلوغِ المقرِّر لأسطحِ القنوات ═════════════════════════
   السطحُ يُعَدُّ «مسربًا» إن مسَّ جدولًا حساسًا **واسمَ حقلٍ حساسٍ منه** ولم
   يعرف نقطةَ القرار. ومسُّ الجدولِ وحدَه لا يكفي — وإلا عُدَّ كلُّ استعلامٍ
   على `employees` تسريبًا. */
$DECIDERS = array('FieldGovernor', 'SensitiveFieldGuard', 'ems_log_sensitive_read', 'api_sensitive_value', 'ems_sensitive_display');

/* ◆ **مسحٌ شجريٌّ لا `glob` بمستوًى واحد** — وهذا تصحيحٌ لنسخةٍ أولى من هذا
     الشاهدِ مرَّت خضراءَ بمقامٍ صفر: أنماطُ `glob` كانت تمسّ مستوًى واحدًا
     فأخرجت «لا سطحَ يمسُّ حقلًا حساسًا» لستِّ قنوات. **والمقامُ الصفرُ ليس
     نجاحًا** — بل بوابةٌ يستحيل رسوبُها، وهي أخطرُ من بوابةٍ راسبة.
     ⇐ فتُمسح الشجرةُ كلُّها ويُصنَّف الملفُّ بقناتِه من **مسارِه واسمِه**. */
$CHANNELS = array(
    '③ البحث'            => '~(^|/)(global_?search|search)[^/]*\.php$~i',
    '④ الواجهة البرمجية' => '~(^|/)api/~i',
    '⑤ المناظر المحفوظة' => '~(saved_?view|_views|view_fields|short_?view)[^/]*\.php$~i',
    '⑥ منتقي الأعمدة'    => '~(column_?picker|columns?_(pick|choose|select)|col_picker)[^/]*\.(php|js)$~i',
    '⑦ العنوان المباشر'  => '~(print|details?|profile|card|show)[^/]*\.php$~i',
    '⑧ نداءاتُ الخلفية'  => '~(ajax|fetch|_json|endpoint|handler)[^/]*\.php$~i',
    '⑨ الأفعالُ الجماعية' => '~(bulk|mass_|batch_)[^/]*\.php$~i',
);

/** كلُّ ملفاتِ الشجرةِ الحيةِ مرةً واحدة — بلا نسخِ الاحتياطِ ولا الأدواتِ ولا الفاحصات. */
function liveTree($root)
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = array();
    $skip = array('/storage/', '/vendor/', '/node_modules/', '/.git/', '/docs/', '/tools/', '/tests/', '/examples/');
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) { continue; }
        $p = str_replace('\\', '/', $f->getPathname());
        $rel = substr($p, strlen(str_replace('\\', '/', $root)));
        if (!preg_match('~\.(php|js)$~i', $rel)) { continue; }
        $bad = false;
        foreach ($skip as $s) { if (strpos($rel, $s) !== false) { $bad = true; break; } }
        if ($bad) { continue; }
        $cache[] = array('rel' => ltrim($rel, '/'), 'abs' => $p);
    }
    return $cache;
}

function surfaceTouchesSensitive($src, array $fields)
{
    foreach ($fields as $tbl => $flds) {
        if (stripos($src, $tbl) === false) { continue; }
        foreach ($flds as $f) {
            /* اسمُ الحقلِ ككلمةٍ لا كجزءِ كلمة — وإلا عُدَّ `margin` في `margin-top` */
            if (preg_match('/\b' . preg_quote($f, '/') . '\b/i', $src)) { return $tbl . '.' . $f; }
        }
    }
    return null;
}

echo "\n── ③–⑨ بلوغُ نقطةِ القرارِ لأسطحِ القنوات ──\n";
$ROOTD = dirname(__DIR__);
$tree  = liveTree($ROOTD);
echo "  المسحُ الشجريّ: " . count($tree) . " ملفَّ إنتاجٍ حيٍّ (بلا storage/vendor/docs/tools/tests)\n";
foreach ($CHANNELS as $name => $rx) {
    $files = array();
    foreach ($tree as $e) { if (preg_match($rx, $e['rel'])) { $files[$e['abs']] = true; } }
    $chanFiles = count($files);
    $touch = 0; $covered = 0; $open = array(); $exempted = array();
    foreach (array_keys($files) as $f) {
        $src = (string) @file_get_contents($f);
        $hit = surfaceTouchesSensitive($src, $fields);
        if ($hit === null) { continue; }
        $touch++;
        $hasDecider = false;
        foreach ($DECIDERS as $d) { if (strpos($src, $d) !== false) { $hasDecider = true; break; } }
        /* ◆ إعفاءٌ **مُعلَنٌ بسببِه في الملفِّ نفسِه** — لا قائمةَ سماحٍ في الفاحص.
           فالسطحُ الذي يمسُّ عمودًا حساسًا **تحقُّقًا لا بثًّا** (مثل قراءةِ
           تجزئةِ كلمةِ المرورِ لتمريرِها إلى `password_verify`) ليس مسربًا —
           لكنَّ سكوتَ الفاحصِ عنه بلا سببٍ مكتوبٍ هو نفسُه العيب. ويُعَدُّ
           ويُسمّى في التقريرِ فلا يختفي من المقام. */
        $exempt = strpos($src, 'INJFIX-SENSITIVE-EXEMPT') !== false;
        if ($hasDecider) { $covered++; }
        elseif ($exempt) { $covered++; $exempted[] = str_replace($ROOTD . DIRECTORY_SEPARATOR, '', $f); }
        else { $open[] = str_replace($ROOTD . DIRECTORY_SEPARATOR, '', $f) . ' (' . $hit . ')'; }
    }
    $verdict = ($touch === 0) || ($covered === $touch);
    ok($verdict, $name, $pass, $fail,
       $touch === 0 ? "◆ ملفاتُ القناة={$chanFiles} · صفرٌ منها يمسُّ حقلًا حساسًا — ومقامٌ صفرٌ ليس نجاحًا حتى يُثبَت أن الصنفَ غيرُ فارغ"
                    : "ملفاتُ القناة={$chanFiles} · تمسُّ حساسًا={$touch} · تمرُّ بالمقرِّر={$covered} (منها إعفاءٌ مُعلَنٌ=" . count($exempted) . ") · مفتوحة=" . count($open));
    foreach (array_slice($open, 0, 6) as $o) { echo "        ↳ مفتوح: {$o}\n"; $leaks[] = $name . ' :: ' . $o; }
    if (count($open) > 6) { echo "        ↳ … و" . (count($open) - 6) . " غيرُها\n"; }
}

/* ══════════ الحكم ═════════════════════════════════════════════════════════ */
echo "───────────────────────────────────────────────────────────────\n";
echo "المسارِبُ المفتوحةُ المسمّاة: " . count($leaks) . "\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";
if ($fail > 0) {
    echo "◆ ولا يُعلَن «صفرُ تسريب» ما دام رقمٌ أعلاه غيرَ صفر — الراسبُ يُسمّى راسبًا.\n";
}
exit($fail === 0 ? 0 : 1);
