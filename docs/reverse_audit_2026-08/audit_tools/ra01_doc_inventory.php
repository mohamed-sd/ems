<?php
/**
 * ra01_doc_inventory.php — جردُ docs/ وتصنيفُها على سلّمِ الحاكمية (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * التصنيفُ الثماني (من التكليف): حاكمة نافذة · متخصصة نافذة · وثيقة تصحيح ·
 * تقرير تدقيق · مستبدلة · تاريخية · تحتاج قرارًا · غير صالحة للاعتماد
 * + صنفٌ تشغيليّ: «سجل تنفيذ حي» (fix_2026-08 · fix_progress · reports)
 * ◆ القاعدة: أعلى لاحقةٍ في العائلةِ الواحدةِ هي النافذة، وما دونها مستبدَل.
 * ◆ المخرَج: evidence/document_hierarchy.tsv + خلاصةٌ على الشاشة.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';

/* ── جمعُ الملفات ─────────────────────────────────────────────────────── */
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/docs', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = str_replace('\\', '/', (string) $f);
    if (strpos($p, '/reverse_audit_2026-08/') !== false) { continue; }
    $files[] = $p;
}
sort($files);

/* عائلة الملف: الاسم بلا لاحقة "-N" وبلا "(1)" وبلا الامتداد */
function family(string $base): array {
    $name = preg_replace('/\.[a-z]+$/i', '', $base);
    $dup  = (bool) preg_match('/\(\d+\)\s*$/', $name);
    $name = preg_replace('/\s*\(\d+\)\s*$/', '', $name);
    $suf  = 0;
    if (preg_match('/-(\d+)$/u', $name, $m)) { $suf = (int) $m[1]; $name = preg_replace('/-\d+$/u', '', $name); }
    return [trim($name), $suf, $dup];
}

/* أعلى لاحقة لكل عائلة (على مستوى الشجرة كلِّها لا المجلد) */
$fam = [];
foreach ($files as $p) {
    [$f, $s, $dup] = family(basename($p));
    if (!isset($fam[$f]) || $s > $fam[$f]) { $fam[$f] = $s; }
}

$rows = []; $tally = [];
foreach ($files as $p) {
    $rel  = substr($p, strlen($ROOT) + 1);
    $base = basename($p);
    [$famName, $suf, $isDup] = family($base);
    $isLatest = ($suf === $fam[$famName]);
    $dir = explode('/', $rel)[1] ?? '';

    $class = null; $reason = ''; $rank = 9;

    /* غير صالحة: أقفال Word والفوارغ والتكرارات المنزَّلة */
    if (strpos($base, '~$') === 0)            { $class = 'غير صالحة'; $reason = 'قفل Word مؤقت'; }
    elseif (filesize($p) === 0)               { $class = 'غير صالحة'; $reason = 'ملف فارغ'; }
    elseif ($isDup)                           { $class = 'غير صالحة'; $reason = 'نسخة تنزيل مكررة "(N)"'; }

    /* الحاكمة النافذة */
    elseif ($dir === 'basicRolesDocs')        { $class = 'حاكمة نافذة'; $rank = 2; $reason = 'المجموعة الحاكمة الست بأحدث لواحقها'; }
    elseif ($base === 'INJAZ-MASTER-MAP-7.xlsx') { $class = 'حاكمة نافذة'; $rank = 1; $reason = 'سجل التجميد والأحكام النهائية (5,934 حكمًا) — الأعلى عند التعارض'; }
    elseif (preg_match('/^INJAZ-MASTER-MAP-[1-6]\.xlsx$/', $base)) { $class = 'مستبدلة'; $reason = 'استبدلها MASTER-MAP-7'; }

    /* قرارات المالك */
    elseif (stripos($base, 'DEC-01') === 0 || stripos($base, 'DEC-SCR-01') === 0)
                                              { $class = ($isLatest ? 'قرار مالك نافذ' : 'مستبدلة'); $rank = 3; $reason = 'وثيقة قرارات مالك'; }
    elseif (in_array($base, ['OPEN_DECISIONS.md', 'OWNER_DECISIONS_DATA.md', 'OWNER_DECISIONS_DUP.md', 'OWNER_DECISIONS_WORKFLOW.md'], true))
                                              { $class = 'تحتاج قرارًا'; $rank = 3; $reason = 'بنود تنتظر توقيع المالك'; }

    /* التصحيح والتدقيق */
    elseif ($dir === 'fix')                   { $class = 'وثيقة تصحيح'; $rank = 5; $reason = 'حزمة FIX الحاكمة للتصحيح'; }
    elseif ($dir === 'audit_2026-08')         { $class = 'تقرير تدقيق'; $rank = 6; $reason = 'ادعاءات تدقيق 2026-08-09 — تُعاد لا تُصدَّق'; }
    elseif ($dir === 'fix_2026-08' || $dir === 'fix_progress' || $dir === 'reports')
                                              { $class = 'سجل تنفيذ حي'; $rank = 7; $reason = 'قياسات ومحاضر تنفيذ — دليل لا مواصفة'; }

    /* المعمارية */
    elseif (preg_match('/^ARCHITECTURE_CURRENT_SYSTEM_v(\d+)_ar\.md$/', $base, $m))
                                              { $class = ((int) $m[1] === 21 ? 'حاكمة نافذة' : 'مستبدلة');
                                                $rank = 2; $reason = ((int) $m[1] === 21 ? 'المرجع المعماري As-Built النافذ' : 'استبدلها v21'); }

    /* مصدر الشاشات NAV-09 */
    elseif ($base === 'NAV-09-current.xlsx')  { $class = 'متخصصة نافذة'; $rank = 3; $reason = 'مصدر الحقيقة الحي لنظام الشاشات'; }
    elseif (stripos($base, 'NAV-09') === 0)   { $class = 'مستبدلة'; $reason = 'نسخ NAV-09 غير الحية'; }

    /* المواصفات المتخصصة */
    elseif ($dir === 'specs')                 { $class = 'متخصصة نافذة'; $rank = 4; $reason = 'مواصفات وحدات H/E'; }
    elseif ($dir === 'update0013' && $isLatest && preg_match('/^(FIN-|IAF-|PROP-)/', $base))
                                              { $class = 'متخصصة نافذة'; $rank = 4; $reason = 'أحدث حقبة مالية — لا بديل أحدث'; }
    elseif ($dir === 'uat')                   { $class = 'متخصصة نافذة'; $rank = 4; $reason = 'خطط ومحاضر UAT'; }
    elseif ($dir === 'nfr')                   { $class = 'متخصصة نافذة'; $rank = 4; $reason = 'وثائق NFR'; }

    /* الحقب المغلقة والمستخلصات */
    elseif (preg_match('/^update00(0[1-9]|1[0-3])/', $dir))
                                              { $class = ($isLatest && !preg_match('/^update00/', '') ? 'تاريخية' : 'تاريخية');
                                                $reason = 'حقبة بناء مغلقة ببوابتها — ' . $dir;
                                                if (!$isLatest) { $class = 'مستبدلة'; $reason = 'نسخة أقدم داخل حقبة مغلقة'; } }
    elseif (in_array($dir, ['sources', 'act01', 'nav01', 'nav02', 'nav07', 'owf01', 'sec01', 'renaming_20260724'], true))
                                              { $class = 'تاريخية'; $reason = 'مستخلصات وأدلة عمل لمراحل منتهية'; }
    elseif ($dir === 'files')                 { $class = ($isLatest ? 'متخصصة نافذة' : 'مستبدلة'); $rank = 4; $reason = 'ملفات مرجعية — الأحدث في عائلته نافذ'; }

    /* جذر docs */
    else {
        if (preg_match('/AUDIT|تدقيق/u', $base) && !preg_match('/GUIDE/i', $base)) { $class = 'تقرير تدقيق'; $rank = 6; $reason = 'تقرير تدقيق سابق'; }
        elseif (preg_match('/GUIDE|دليل|CATALOG|INDEX/iu', $base)) { $class = ($isLatest ? 'متخصصة نافذة' : 'مستبدلة'); $rank = 4; $reason = 'دليل استخدام/تشغيل'; }
        elseif (preg_match('/REPORT|تقرير|STATUS|LOG|CLOSURE|EXECUTION/iu', $base)) { $class = 'سجل تنفيذ حي'; $rank = 7; $reason = 'محضر تنفيذ'; }
        else { $class = ($isLatest ? 'متخصصة نافذة' : 'مستبدلة'); $rank = 4; $reason = 'وثيقة جذر — الأحدث في عائلته'; }
    }

    $rows[] = [$rel, $famName, $suf, $class, $rank, $reason,
               filesize($p), date('Y-m-d H:i', filemtime($p)), sha1_file($p)];
    $tally[$class] = ($tally[$class] ?? 0) + 1;
}

/* ── الكتابة ──────────────────────────────────────────────────────────── */
@mkdir($EV, 0777, true);
$fh = fopen($EV . '/document_hierarchy.tsv', 'w');
fwrite($fh, "path\tfamily\tsuffix\tclass\trank\treason\tbytes\tmtime\tsha1\n");
foreach ($rows as $r) { fwrite($fh, implode("\t", $r) . "\n"); }
fclose($fh);

echo 'إجمالي الملفات: ' . count($rows) . "\n\n";
arsort($tally);
foreach ($tally as $k => $v) { printf("  %-18s %4d\n", $k, $v); }
