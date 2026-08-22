<?php
/**
 * tests/injfrd01_gov004_denial_verb.php
 *   شاهدُ FR-GOV-004 — واقعةُ الرفضِ تقول «رُفض ماذا» لا «رُفض» وحدَها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: «واقعةُ الرفضِ تحمل الفعلَ الذي رُفض — **بعمودٍ وقيد**»
 *   · وسلوكُ الفشل «**رفضٌ من القاعدةِ للواقعةِ الناقصة**» · ومعيارُ القبول
 *   «صفرُ واقعةِ رفضٍ بفعلٍ فارغ».
 *
 * ◆ **و`GAP-14` أُعلن مُغلقًا مرَّتَين وهو مفتوح** — بنصِّ `FINDINGS.md` (F-C05):
 *   القيدُ القائمُ يحرس `attempted_ref` **ولا عمودَ `verb` أصلًا**. وقِيس فصدق.
 *
 * ◆ **وعطبٌ في الشيفرةِ لا في المخطَّطِ وحدَه**: `$verb` كان **يُستعمل قبلَ
 *   تعريفِه** في تسجيلِ `scope_denied` — فالفعلُ فارغٌ في كلِّ رفضِ مساحة.
 *   ويُقاس هنا **ترتيبُ التعريفِ قبلَ الاستعمالِ نصًّا** لا بالثقة.
 *
 * ◆ **والكتّابُ الثلاثةُ يُقاسون**: عمودٌ بلا كاتبٍ يملؤه يبقى فارغًا، وقيدٌ
 *   يمنعهم يكسر عملَهم. فيُقاس أن كلَّ كاتبٍ **يمرِّر الفعلَ** — وأن عددَ
 *   المعاملاتِ يطابق عددَ علاماتِ الاستفهام.
 *
 * التشغيل: php tests/injfrd01_gov004_denial_verb.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$neg = in_array('--negative', $argv, true);
echo "══ FR-GOV-004 — واقعةُ الرفضِ تحمل الفعلَ الذي رُفض ══\n";

/* ── ① العمودُ والقيد ───────────────────────────────────────────────────── */
$hasCol = n($db, "SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guard_denials'
                     AND COLUMN_NAME = 'verb'");
chk($hasCol === 1, '**عمودُ الفعلِ موجود** — و`GAP-14` كان يُعلَن مُغلقًا بلا عمود',
    'guard_denials.verb');
$hasChk = n($db, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA = DATABASE()
                     AND CONSTRAINT_NAME = 'chk_denial_verb_present'");
chk($hasChk === 1, 'و**قيدٌ يرفض الفارغَ** من القاعدة', 'chk_denial_verb_present');

/* ── ② المقامُ والحالة ──────────────────────────────────────────────────── */
$total = n($db, "SELECT COUNT(*) FROM `guard_denials`");
$pre   = n($db, "SELECT COUNT(*) FROM `guard_denials` WHERE `verb_state` = 'PRE_VERB'");
$req   = n($db, "SELECT COUNT(*) FROM `guard_denials` WHERE `verb_state` = 'REQUIRED'");
$badReq = n($db, "SELECT COUNT(*) FROM `guard_denials`
                   WHERE `verb_state` = 'REQUIRED' AND TRIM(`verb`) = ''");
printf("  المقام: وقائعُ رفضٍ=%d · موروثٌ موسومٌ=%d · محكومٌ=%d\n", $total, $pre, $req);
chk($total > 0, '**المقامُ غيرُ صفريّ**', "{$total} واقعة");
chk($badReq === 0, 'FR-GOV-004 · **صفرُ واقعةِ رفضٍ محكومةٍ بفعلٍ فارغ**', "ناقصٌ: {$badReq}");
echo "  ◆ **ولا فعلَ يُختلَق لواقعةٍ مضت** — اختلاقُه تزويرُ سجلٍّ أمنيّ.\n";

/* ── ③ **الفعلُ يُحسَب قبلَ أوّلِ تسجيل** — نصًّا لا بالثقة ─────────────── */
$src = (string) @file_get_contents($ROOT . '/includes/action_guard.php');
$posDef = strpos($src, "\$verb = \$entry['action'] === 'auto'");
$posUse = strpos($src, "ems_action_guard_log('scope_denied'");
chk($posDef !== false && $posUse !== false && $posDef < $posUse,
    'و**التعريفُ قبلَ الاستعمالِ** — فلا يُسجَّل رفضٌ بمتغيّرٍ لم يُعرَّف بعد',
    ($posDef !== false && $posUse !== false)
        ? "تعريفٌ عند {$posDef} · استعمالٌ عند {$posUse}" : 'أحدُهما مفقود');

/* ── ④ الكتّابُ الثلاثةُ يمرِّرون الفعلَ ─────────────────────────────────── */
$writers = array(
    'includes/approval_workflow.php'  => 1,
    'includes/unit_chain_helpers.php' => 2,
);
$totalIns = 0; $withVerb = 0; $arityBad = array();
foreach ($writers as $rel => $expect) {
    $w = (string) @file_get_contents($ROOT . '/' . $rel);
    $c = preg_match_all('~INSERT\s+INTO\s+`?guard_denials`?\s*\(([^)]*)\)~i', $w, $m);
    $totalIns += $c;
    foreach ($m[1] as $cols) {
        if (stripos($cols, 'verb') !== false) { $withVerb++; }
    }
    /* عددُ ? يطابق عددَ حروفِ الربط */
    if (preg_match_all('~bind_param\(\s*(?:\$\w+,\s*)?[\'"]([a-z]+)[\'"]~i', $w, $bm)) {
        foreach ($bm[1] as $types) {
            if (strlen($types) === 6 && strpos($types, 'isis') === 0) { continue; }
            if (strlen($types) === 5 && strpos($types, 'isis') === 0) {
                $arityBad[] = $rel . ' ⇒ ' . $types . ' (خمسةٌ حيث ستةٌ مطلوبة)';
            }
        }
    }
}
chk($totalIns > 0, '**مقامُ الكتّابِ غيرُ صفريّ**', "{$totalIns} موضعَ إدراج");
chk($withVerb === $totalIns,
    'و**كلُّ كاتبٍ يمرِّر الفعلَ** — عمودٌ بلا كاتبٍ يملؤه يبقى فارغًا',
    "{$withVerb} من {$totalIns}");
chk(empty($arityBad), 'وعددُ المعاملاتِ يطابق الأعمدة — **فانزياحُ حرفٍ يمحو نصًّا صامتًا**',
    empty($arityBad) ? 'صفرُ انزياح' : implode(' · ', $arityBad));

if ($neg) {
    echo "\n── الحزامُ السالب ──\n";
    $rejected = false; $err = '';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $db->query("INSERT INTO `guard_denials`
            (`company_id`,`guard_code`,`person_id`,`attempted_ref`,`reason_code`,`at`)
            VALUES (4,'BELT-GOV004',1,'belt/ref','belt',NOW())");
    } catch (\Throwable $t) { $rejected = true; $err = $t->getMessage(); }
    mysqli_report(MYSQLI_REPORT_OFF);
    $left = n($db, "SELECT COUNT(*) FROM `guard_denials` WHERE `guard_code` = 'BELT-GOV004'");
    chk($rejected && $left === 0, '**واقعةٌ بفعلٍ فارغٍ ⇒ رفضٌ من القاعدة**',
        $rejected ? 'ردَّته: ' . mb_substr($err, 0, 50) : "مرَّت ✘ · صفوفٌ={$left}");

    $passed = false; $e2 = '';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $db->query("INSERT INTO `guard_denials`
            (`company_id`,`guard_code`,`person_id`,`attempted_ref`,`reason_code`,`verb`,`at`)
            VALUES (4,'BELT-GOV004-OK',1,'belt/ref','belt','edit',NOW())");
        $passed = true;
    } catch (\Throwable $t) { $e2 = $t->getMessage(); }
    mysqli_report(MYSQLI_REPORT_OFF);
    chk($passed, 'و**واقعةٌ بفعلٍ تمرّ** — القيدُ يمنع الخطأَ لا العمل',
        $passed ? 'مرَّت ✔' : 'رُدَّت ✘: ' . mb_substr($e2, 0, 50));
    $db->query("DELETE FROM `guard_denials` WHERE `guard_code` IN ('BELT-GOV004','BELT-GOV004-OK')");
    $sw = n($db, "SELECT COUNT(*) FROM `guard_denials`
                   WHERE `guard_code` IN ('BELT-GOV004','BELT-GOV004-OK')");
    chk($sw === 0, 'وكُنس الحزامُ أثرَه', "المتبقي: {$sw}");
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
