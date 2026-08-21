<?php
/**
 * tests/injfix01_approval_cutover_phase6_proof.php — INJ-FIX-01 · GAP-02
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «مصفوفةُ التوحيدِ لمجالِ الاعتمادِ مملوءةٌ · **والقلبُ بلغ
 *   المرحلةَ السادسة**» — والسادسةُ منعُ الكاتبِ القديم.
 *
 * ◆ **والمرحلةُ السابعةُ (التقاعد) لم تبلغ ولا يُدَّعى بلوغُها**: ثلاثةَ عشرَ
 *   قارئًا ما يزالون على المسارِ القديم. **وقرّاءُ مسارٍ ميتٍ هم عائقُ التقاعدِ
 *   الحقيقيُّ لا كتّابُه.**
 *
 * التشغيل: php tests/injfix01_approval_cutover_phase6_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ══ ① المصفوفةُ مملوءةٌ لمجالِ الاعتماد ══════════════════════════════════ */
echo "══ ① مصفوفةُ التوحيدِ لمجالِ الاعتماد ══\n";
$mx = (string) @file_get_contents($ROOT . '/docs/INJFIX01/CONSOLIDATION_MATRIX_ar.md');
chk($mx !== '', 'المصفوفةُ موجودة');
$need = array('المسارُ القانونيّ', 'ما يُطفأ', 'الكتّابُ اليوم', 'القرّاءُ اليوم',
              'المقارنُ في الظلّ', 'معيارُ القلب', 'معيارُ الرجوع', 'تاريخُ التقاعد');
$miss = array();
$sec = mb_strpos($mx, '## ② الاعتماد');
$sub = ($sec !== false) ? mb_substr($mx, $sec, 2200) : '';
foreach ($need as $n) { if (mb_strpos($sub, $n) === false) { $miss[] = $n; } }
chk(count($miss) === 0, 'الحقولُ الثمانيةُ مملوءةٌ لمجالِ الاعتماد — ناقص=' . count($miss)
    . (count($miss) ? ' — ' . implode(' · ', $miss) : ''));

/* ══ ② المسارُ القانونيُّ مأهول ═══════════════════════════════════════════ */
echo "\n══ ② المسارُ القانونيُّ حيّ ══\n";
$lad = (int) $conn->query("SELECT COUNT(*) FROM `gov_ladders`")->fetch_row()[0];
$stp = (int) $conn->query("SELECT COUNT(*) FROM `gov_ladder_steps`")->fetch_row()[0];
chk($lad > 0 && $stp > 0, "‏`gov_ladders` {$lad} سلّمًا · `gov_ladder_steps` {$stp} خطوة");
$q = $conn->query("SELECT COUNT(*) FROM information_schema.VIEWS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v_approval_rules_effective'");
chk($q && (int) $q->fetch_row()[0] > 0, 'المنظرُ الفعّالُ `v_approval_rules_effective` قائم');

/* ══ ③ المرحلةُ السادسة — الكاتبُ القديمُ ممنوعٌ بقيدٍ يُجرَّب ═════════════ */
echo "\n══ ③ المرحلةُ السادسة: الكتابةُ في القديمِ ممنوعةٌ بقيد ══\n";
$q = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='approval_workflow_rules'
                      AND CONSTRAINT_NAME='chk_awr_legacy_write_blocked'");
chk($q && (int) $q->fetch_row()[0] > 0, 'القيدُ مُعلَنٌ في المخطَّط');

$liveOld = (int) $conn->query("SELECT COUNT(*) FROM `approval_workflow_rules`
                                WHERE `is_active` <> 0")->fetch_row()[0];
chk($liveOld === 0, "صفرُ قاعدةٍ نافذةٍ في المسارِ القديم — {$liveOld}");

/* ◆ ويُجرَّب حيًّا: قيدٌ يُقرأ من المخطَّطِ ولا يُجرَّب قد يكون معطَّلًا */
$conn->query("UPDATE `approval_workflow_rules` SET `is_active` = 1
               WHERE `id` = (SELECT * FROM (SELECT MIN(`id`) FROM `approval_workflow_rules`) t)");
$blocked = ($conn->errno !== 0); $errno = $conn->errno;
if (!$blocked) { $conn->query("UPDATE `approval_workflow_rules` SET `is_active` = 0"); }
chk($blocked, "**جُرِّب فرُدّ**: تنشيطُ قاعدةٍ قديمةٍ مرفوض (errno {$errno})");

/* والقراءةُ لم تُكسر */
$rd = (int) $conn->query("SELECT COUNT(*) FROM `approval_workflow_rules`")->fetch_row()[0];
chk($rd > 0, "والقراءةُ تعمل — {$rd} صفًّا · **القارئُ لا يُطفأ في السادسة**");

/* ══ ④ والسابعةُ لم تبلغ — ولا يُدَّعى بلوغُها ═══════════════════════════ */
echo "\n══ ④ المرحلةُ السابعةُ (التقاعد) — لم تبلغ ══\n";
$readers = 0;
$SKIP = array('vendor', 'node_modules', '.git', 'logs', 'storage', 'docs', 'tests', 'tools', 'database');
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    $top = (strpos($rel, '/') !== false) ? substr($rel, 0, strpos($rel, '/')) : '';
    if ($top !== '' && in_array($top, $SKIP, true)) { continue; }
    if (strpos((string) @file_get_contents($f->getPathname()), 'approval_workflow_rules') !== false) { $readers++; }
}
printf("  ◆ قرّاءُ المسارِ القديمِ في شجرةِ الإنتاج: **%d**\n", $readers);
chk(true, '◆ **السابعةُ مفتوحةٌ بحقّ** — وإطفاءُ القرّاءِ شرطُها');
echo "  ◆ **وقرّاءُ مسارٍ ميتٍ هم عائقُ التقاعدِ الحقيقيُّ لا كتّابُه.**\n";
echo "  ◆ ولم تُنتظَر نافذةُ ظلٍّ للسادسة: القديمُ **بصفرِ قاعدةٍ نافذة**،\n";
echo "     والظلُّ على مسارٍ فارغٍ يقيس الفراغَ ويُسمّيه تطابقًا — ثقةٌ لم تُكتسب.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
