<?php
/**
 * prune_baseline_snapshots.php — مِقَصُّ لقطاتِ ما قبلَ الهجرة
 * ═══════════════════════════════════════════════════════════════════════════
 * لماذا وُجد:
 *   `migrate.php` يأخذ لقطةَ مخطَّطٍ كاملةً باسم `auto_pre_up_*` قبل كلِّ `up`
 *   (انظر migrate.php:538). فتتراكم بلا سقفٍ — بلغت 505 لقطةً و299 م.ب.
 *   والمجلدُ مُستثنًى في .gitignore فلا شيءَ يُقلّمه.
 *
 * ◆ خطُّ الأمانِ الذي لا يُعبَر — لقطاتٌ **تقرؤها الشيفرةُ فعلًا**:
 *     database/migrations/2027_02_09_restore_all_lost_business_checks.php
 *         → glob('baseline/auto_pre_up_20260803_*.sql')
 *     database/migrations/2027_02_04_restore_lost_check_constraints.php
 *         → 'auto_pre_up_20260803_084927_equipation_manage.sql'
 *     tools/fix_check_full_sweep.php · tools/fix_lost_constraints_plan.php
 *         → النمطُ نفسُه
 *     database/migrations/2027_02_16_client_contracts_view_restore.php
 *         → يذكر 'auto_pre_up_20260731_212517' مرجعًا
 *   حذفُ أيٍّ منها يكسر تلك الهجرات. فهي محميّةٌ بالاسمِ أدناه لا بالتاريخ.
 *
 * التشغيل:
 *   php tools/prune_baseline_snapshots.php              # عرضٌ فقط (الافتراضي)
 *   php tools/prune_baseline_snapshots.php --apply      # تنفيذٌ فعليّ
 *   php tools/prune_baseline_snapshots.php --keep=10 --apply
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$ROOT = dirname(__DIR__);
$DIR  = $ROOT . '/database/baseline';

$argv_ = $argv;
$apply = in_array('--apply', $argv_, true);
$keep  = 5;
foreach ($argv_ as $a) {
    if (preg_match('/^--keep=(\d+)$/', $a, $m)) {
        $keep = max(1, (int) $m[1]);
    }
}

// الأنماطُ المحميّةُ — مقروءةٌ من الشيفرة، لا تُحذف مهما قَدُمت.
$PROTECTED = array(
    'auto_pre_up_20260803_*.sql',   // هجرتا استعادةِ قيودِ CHECK + أداتا الكنس
    'auto_pre_up_20260731_*.sql',   // مرجعُ استعادةِ عرضِ عقودِ العملاء
    'schema_baseline_*.sql',        // خطُّ أساسِ المخطَّط
);

if (!is_dir($DIR)) {
    exit("لا مجلدَ database/baseline — لا شيءَ يُقلَّم\n");
}

$protectedSet = array();
foreach ($PROTECTED as $pat) {
    foreach (glob($DIR . '/' . $pat) ?: array() as $f) {
        $protectedSet[basename($f)] = true;
    }
}

// المرشَّحون: لقطاتُ auto_pre_up وحدَها — ولا يُمَسُّ ما سواها.
$snaps = glob($DIR . '/auto_pre_up_*.sql') ?: array();
sort($snaps);                 // الاسمُ يحمل الطابعَ الزمنيَّ فالترتيبُ زمنيّ
$snaps = array_reverse($snaps);   // الأحدثُ أولًا

$recent = array();
foreach (array_slice($snaps, 0, $keep) as $f) {
    $recent[basename($f)] = true;
}

$doomed = array();
$freed  = 0;
foreach ($snaps as $f) {
    $b = basename($f);
    if (isset($protectedSet[$b]) || isset($recent[$b])) {
        continue;
    }
    $doomed[] = $f;
    $freed   += (int) filesize($f);
}

printf("المجلد     : %s\n", $DIR);
printf("اللقطات    : %d\n", count($snaps));
printf("محميّة     : %d (تقرؤها الشيفرة)\n", count($protectedSet));
printf("مُستبقاة   : %d (الأحدث)\n", min($keep, count($snaps)));
printf("للحذف      : %d  ⇐  %.1f م.ب\n", count($doomed), $freed / 1048576);

if (!$apply) {
    echo "\nعرضٌ فقط. أضِفْ --apply للتنفيذ.\n";
    exit(0);
}

$done = 0;
$fail = 0;
foreach ($doomed as $f) {
    if (@unlink($f)) {
        $done++;
    } else {
        $fail++;
        fprintf(STDERR, "تعذَّر حذف: %s\n", $f);
    }
}
printf("\n✔ حُذف %d · تعذَّر %d · حُرِّر %.1f م.ب\n", $done, $fail, $freed / 1048576);
exit($fail > 0 ? 1 : 0);
