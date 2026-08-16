<?php
/**
 * ra91_dept_sheets.php — ورقةٌ مستقلةٌ لكلِّ إدارةٍ بالأعمدةِ الـ37 والنسبِ العشر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحالاتُ الثلاثُ مفصولةٌ ولكلٍّ مصدرٌ مستقل:
 *     Decision_Status       ← تصنيفُ الفجوة (فجوةُ قرارٍ ⇒ مفتوح)
 *     Implementation_Status ← docs/fix_progress/INJ_findings_state.tsv
 *     Evidence_Status       ← witness_sweep.json (شاهدٌ حيٌّ أُعيد تشغيلُه)
 *   والبندُ لا يُعدُّ ناجحًا إلا باجتماعِ الثلاث.
 * ◆ النسبُ العشرُ لا تُجمع، وما لا يُقاس يُكتب «غيرُ مقيس» ولا يدخل مقامًا.
 */
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function build_department_sheets(Spreadsheet $ss, string $ROOT, string $EV, array $base,
                                 array $sweep, array $unguard, array $jr, array $ctx): void
{
    $mk = $ctx['mk']; $head = $ctx['head']; $cref = $ctx['cref'];
    $OK = $ctx['OK']; $WARN = $ctx['WARN']; $BAD = $ctx['BAD']; $ALT = $ctx['ALT'];

    /* ── ① المصادرُ الثلاثةُ المستقلة ─────────────────────────────── */
    $state = [];
    foreach (array_slice(file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv', FILE_IGNORE_NEW_LINES), 1) as $l) {
        $p = explode("\t", $l); $id = trim($p[0]);
        if ($id === '') { continue; }
        $state[$id] = ['sev' => trim($p[1] ?? ''), 'blk' => trim($p[2] ?? ''),
                       'state' => trim($p[3] ?? ''), 'witness' => trim($p[4] ?? '')];
    }
    $green = []; $red = [];
    foreach ($sweep['verdicts'] as $id => $v) { if (!empty($v['green'])) { $green[$id] = $v; } else { $red[$id] = $v; } }

    /* الشاشاتُ التي تُصيَّر فعلًا للمخوَّل (200 + قشرة) */
    $rendered = [];
    foreach (file($EV . '/live_http_admin.jsonl', FILE_IGNORE_NEW_LINES) as $line) {
        $j = json_decode($line, true);
        if (!$j) { continue; }
        $rendered[strtolower($j['path'])] = (($j['code'] ?? 0) === 200 && !empty($j['shell']));
    }
    /* الشاشاتُ المسرَّبةُ بلا منحة (ra05c) */
    $leaky = [];
    foreach (($unguard['leaks'] ?? $unguard['rows'] ?? []) as $x) {
        $p = is_array($x) ? ($x['path'] ?? $x['file'] ?? '') : (string) $x;
        if ($p !== '') { $leaky[strtolower(basename($p))] = true; }
    }

    /* قواميسُ الاستخراج: جداولٌ وأفعالٌ وأنواعُ أحداث */
    $db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
    $db->set_charset('utf8mb4');
    $tbl = [];
    $r = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='equipation_manage' AND TABLE_TYPE='BASE TABLE' AND CHAR_LENGTH(TABLE_NAME)>6");
    while ($x = $r->fetch_row()) { $tbl[] = $x[0]; }
    $acts = [];
    if ($r = $db->query("SELECT DISTINCT action_code FROM nav09_action_map WHERE action_code<>''")) {
        while ($x = $r->fetch_row()) { if (strlen($x[0]) > 4) { $acts[] = $x[0]; } }
    }
    $evts = [];
    if ($r = $db->query("SELECT DISTINCT event_key FROM fin_financial_events WHERE event_key IS NOT NULL AND event_key<>''")) {
        while ($x = $r->fetch_row()) { if (strlen($x[0]) > 4) { $evts[] = $x[0]; } }
    }
    $rxT = $tbl ? '/\b(' . implode('|', array_map('preg_quote', $tbl)) . ')\b/' : null;
    $rxA = $acts ? '/\b(' . implode('|', array_map('preg_quote', array_slice($acts, 0, 400))) . ')\b/' : null;
    $rxE = $evts ? '/\b(' . implode('|', array_map('preg_quote', array_slice($evts, 0, 400))) . ')\b/' : null;
    $pick = function (?string $rx, string $hay, int $max = 3): string {
        if (!$rx || $hay === '') { return '—'; }
        if (!preg_match_all($rx, $hay, $m)) { return '—'; }
        return implode(' · ', array_slice(array_unique($m[1]), 0, $max));
    };

    /* ── ② قراءةُ السجلِّ الجامعِ كاملًا ───────────────────────────── */
    $R = file($ROOT . '/docs/fix_2026-08/master_register.tsv', FILE_IGNORE_NEW_LINES);
    $hdr = array_map('trim', str_getcsv($R[2], "\t"));
    $ix = array_flip($hdr);
    $g = function (array $c, string $k) use ($ix) { return isset($ix[$k]) ? trim((string) ($c[$ix[$k]] ?? '')) : ''; };

    /* نموذجُ الصعوبةِ والجهد — مصدرٌ واحدٌ مشتركٌ مع ra10 (لا تعريفَ ثانيًا) */
    require_once __DIR__ . '/ra_effort_model.php';
    $days = RA_DAYS;
    $prio = ['P0' => 'Critical', 'P1' => 'High', 'P2' => 'Medium', 'P3' => 'Low', 'P4' => 'Low'];

    /* الرحلةُ المناظرةُ لكلِّ إدارة (لنسبةِ دورةِ العمل) */
    $deptJourney = [
        'المبيعات والعقود' => 'C', 'المالية والخزينة' => 'F', 'التمويل والملكية' => 'F',
        'إدارة الصيانة' => 'M', 'المشتريات التشغيلية' => 'P', 'المخازن' => 'P',
        'إدارة المخاطر' => 'R', 'إدارة الحوكمة والالتزام' => 'S', 'إدارة التشغيل' => 'C',
        'إدارة الموردين' => 'C', 'إدارة الأسطول' => 'M',
    ];

    $rows = [];
    for ($i = 3; $i < count($R); $i++) {
        if (trim($R[$i]) === '') { continue; }
        $c = str_getcsv($R[$i], "\t");
        $id = trim($c[0]);
        if (!preg_match('/^INJ-\d+$/', $id)) { continue; }

        $kind = $g($c, 'نوع الفجوة');
        $sev  = $g($c, 'الخطورة');
        $st   = $state[$id] ?? ['state' => 'غيرُ مقيس', 'witness' => ''];
        $cls  = $g($c, 'تصنيف الفجوة');

        $decClosed  = (mb_strpos($cls, 'قرار') === false);
        $implClosed = in_array($st['state'], ['مُغلقٌ بشاهد', 'مُغلق', 'مُغطًّى'], true);
        $evGreen    = isset($green[$id]);
        $evRed      = isset($red[$id]);

        $cx = ra_complexity($kind, $sev);

        $route = $g($c, 'رابط الشاشة');
        $file  = preg_replace('~^https?://[^/]+/ems/~', '', $route);
        $bn    = strtolower(basename(parse_url($file, PHP_URL_PATH) ?: ''));
        $ev13  = $g($c, 'الدليل (ملف:سطر)');
        $blob  = $ev13 . ' ' . $g($c, 'السبب الجذري') . ' ' . $g($c, 'الواقع في النظام') . ' ' . $g($c, 'الإصلاح الموصى به');

        $rows[] = [
            'dept' => $g($c, 'الإدارة') ?: 'غير مصنَّف',
            'id' => $id, 'sev' => $sev, 'kind' => $kind, 'cx' => $cx,
            'decClosed' => $decClosed, 'implClosed' => $implClosed, 'evGreen' => $evGreen, 'evRed' => $evRed,
            'bn' => $bn,
            'cells' => [
                $id,
                $g($c, 'الوثيقة'),
                $g($c, 'بند الوثيقة'),
                $g($c, 'المتوقَّع حسب الوثيقة'),
                $kind,
                ($sev === 'P0' || $sev === 'P1' || mb_strpos($g($c, 'يمنع الإطلاق'), 'نعم') === 0) ? 'إلزامي' : 'اختياري',
                $g($c, 'الإدارة'),
                $g($c, 'مجموعة السايدبار') ?: '—',
                $bn !== '' ? $bn : '—',
                $g($c, 'اسم الشاشة'),
                $file ?: '—',
                $ev13 !== '' ? mb_substr($ev13, 0, 220) : '—',
                $pick($rxT, $blob),
                $pick($rxA, $blob),
                $pick($rxE, $blob),
                $g($c, 'المتوقَّع حسب الوثيقة'),
                $g($c, 'الواقع في النظام'),
                $decClosed ? 'Decision Closed' : 'Decision Open — قرارُ مالك',
                $implClosed ? 'Implementation Closed' : 'Implementation Open',
                $evGreen ? 'Evidence Closed — شاهدٌ أخضرُ حيّ' : ($evRed ? 'Evidence FAILED — شاهدٌ أحمر' : 'Evidence Open — لا شاهدَ مُشغَّل'),
                ($decClosed && $implClosed && $evGreen) ? 'PASS' : 'FAIL',
                $prio[$sev] ?? 'Medium',
                $sev,
                $cx,
                $days[$cx],
                $g($c, 'يمنع الإطلاق'),
                $g($c, 'يمنع العرض الخارجي'),
                $evGreen ? 'اختبارٌ آليٌّ مُشغَّل' : ($evRed ? 'اختبارٌ آليٌّ راسب' : ($st['witness'] !== '' ? 'شاهدٌ مُعلَنٌ غيرُ مُشغَّل' : 'لا دليل')),
                $st['witness'] !== '' ? $st['witness'] : ($ev13 !== '' ? mb_substr($ev13, 0, 120) : '—'),
                substr((string) ($base['git']['head'] ?? ''), 0, 12),
                $g($c, 'خطوات إعادة الإنتاج'),
                $g($c, 'السبب الجذري'),
                $g($c, 'الإصلاح الموصى به'),
                $g($c, 'اختبار القبول'),
                $g($c, 'المالك المقترح'),
                $g($c, 'نتيجة التحقق المضاد'),
                trim($g($c, 'الأثر') . ' · ' . $cls),
            ],
        ];
    }

    /* ── ③ تجميعٌ حسب الإدارة ─────────────────────────────────────── */
    $byDept = [];
    foreach ($rows as $x) { $byDept[$x['dept']][] = $x; }
    uasort($byDept, fn($a, $b) => count($b) <=> count($a));

    $COLS = ['Requirement_ID', 'Source_Document', 'Source_Section', 'Requirement_Text', 'Requirement_Type',
        'Mandatory', 'Department', 'Workflow_Stage', 'Screen_Code', 'Screen_Name', 'Route', 'Code_File',
        'DB_Object', 'Action_Code', 'Event_Code', 'Expected_Behavior', 'Actual_Behavior',
        'Decision_Status', 'Implementation_Status', 'Evidence_Status', 'Pass_Fail', 'Priority', 'Severity',
        'Complexity', 'Estimated_Person_Days', 'Release_Blocker', 'External_Show_Blocker', 'Evidence_Type',
        'Evidence_Path', 'Commit_Hash', 'Reproduction_Steps', 'Root_Cause', 'Recommended_Fix',
        'Acceptance_Test', 'Owner', 'Reviewer', 'Notes'];

    $idx = 0;
    $summary = [];
    foreach ($byDept as $dept => $list) {
        $idx++;
        $n = count($list);
        $dec  = count(array_filter($list, fn($x) => $x['decClosed']));
        $impl = count(array_filter($list, fn($x) => $x['implClosed']));
        $evg  = count(array_filter($list, fn($x) => $x['evGreen']));
        $pass = count(array_filter($list, fn($x) => $x['decClosed'] && $x['implClosed'] && $x['evGreen']));

        /* شاشاتُ الإدارةِ المذكورةُ في بنودِها — أتُصيَّر فعلًا؟ */
        $scr = [];
        foreach ($list as $x) { if ($x['bn'] !== '') { $scr[$x['bn']] = true; } }
        $scrN = count($scr); $scrOk = 0; $scrLeak = 0;
        foreach (array_keys($scr) as $b) {
            foreach ($rendered as $p => $ok) { if (basename($p) === $b) { if ($ok) { $scrOk++; } break; } }
            if (isset($leaky[$b])) { $scrLeak++; }
        }
        /* الصلاحيات: بنودُ فجوةِ الصلاحياتِ المغلقةُ بالثلاث */
        $pg = array_values(array_filter($list, fn($x) => $x['kind'] === 'Permission Gap'));
        $pgOk = count(array_filter($pg, fn($x) => $x['decClosed'] && $x['implClosed'] && $x['evGreen']));
        /* UX: بنودُ UX المغلقةُ بالثلاث */
        $ux = array_values(array_filter($list, fn($x) => in_array($x['kind'], ['UX/UI Defect', 'Wrong Sidebar Placement'], true)));
        $uxOk = count(array_filter($ux, fn($x) => $x['decClosed'] && $x['implClosed'] && $x['evGreen']));
        /* الأفعال: بنودُ الأحداثِ والتكاملِ المغلقةُ بالثلاث */
        $ac = array_values(array_filter($list, fn($x) => in_array($x['kind'], ['Event/Integration Gap', 'Workflow Gap'], true)));
        $acOk = count(array_filter($ac, fn($x) => $x['decClosed'] && $x['implClosed'] && $x['evGreen']));
        /* دورةُ العمل: من قياسِ الرحلاتِ الحيّ */
        $jk = $deptJourney[$dept] ?? null;
        $wf = ($jk && isset($jr['journeys'][$jk])) ? $jr['journeys'][$jk]['continuity_pct'] : null;

        $P = fn($a, $b) => $b > 0 ? round($a / $b * 100, 1) . '٪' : 'غيرُ مقيس';
        $pcts = [
            ['Decision_Completion_Percent', $P($dec, $n), "$dec / $n"],
            ['Implementation_Completion_Percent', $P($impl, $n), "$impl / $n"],
            ['Evidence_Completion_Percent', $P($evg, $n), "$evg / $n — شاهدٌ أخضرُ أُعيد تشغيلُه"],
            ['Screen_Completion_Percent', $P($scrOk, $scrN), "$scrOk / $scrN شاشةً تُصيَّر بقشرةٍ كاملةٍ للمخوَّل"],
            ['Action_End_to_End_Percent', $P($acOk, count($ac)), $acOk . ' / ' . count($ac) . ' بندَ حدثٍ/تكاملٍ مغلقٍ بالثلاث'],
            ['Permission_Compliance_Percent', $P($pgOk, count($pg)), $pgOk . ' / ' . count($pg) . ' بندَ صلاحيةٍ · تسريبٌ بلا منحة: ' . $scrLeak],
            ['Workflow_Completion_Percent', $wf === null ? 'غيرُ مقيس — لا رحلةَ مناظرة' : $wf . '٪', $jk ? ('رحلة ' . $jk . ' — اتصاليةُ الوصلاتِ الحيّة') : '—'],
            ['UX_UI_Adoption_Percent', $P($uxOk, count($ux)), $uxOk . ' / ' . count($ux) . ' بندَ واجهةٍ مغلقٍ بالثلاث'],
            ['NFR_Evidence_Percent', 'غيرُ مقيس على مستوى الإدارة', 'النفر مقيسٌ عامًّا — ورقة 94'],
            ['UAT_Completion_Percent', 'غيرُ مقيس', 'لا جولةَ UAT بالأدوارِ الحقيقيةِ لهذه الإدارة'],
        ];

        $p0 = count(array_filter($list, fn($x) => $x['sev'] === 'P0'));
        $p1 = count(array_filter($list, fn($x) => $x['sev'] === 'P1'));
        $eff = 0.0;
        foreach ($list as $x) { if (!($x['decClosed'] && $x['implClosed'] && $x['evGreen'])) { $eff += $days[$x['cx']]; } }
        $eff = round($eff, 1);
        $summary[] = ['dept' => $dept, 'n' => $n, 'pass' => $pass, 'impl' => $impl, 'evg' => $evg,
                      'p0' => $p0, 'p1' => $p1, 'eff' => $eff, 'idx' => $idx];

        /* ── الورقة ── */
        $safe = preg_replace('~[\\\\/\?\*\[\]:]~u', '', $dept);
        $title = sprintf('D%02d_%s', $idx, mb_substr($safe, 0, 26));
        $sh = $mk($ss, $title);

        $sh->setCellValue('A1', $dept . ' — سجلُّ المتطلباتِ والفجوات');
        $sh->mergeCells('A1:F1');
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sh->setCellValue('A2', sprintf('البنود: %d · مُغلقٌ بالطبقاتِ الثلاث: %d (%s) · P0: %d · P1: %d · الجهدُ المتبقي: %s يوم-شخص',
            $n, $pass, $P($pass, $n), $p0, $p1, round($eff, 1)));
        $sh->mergeCells('A2:F2');
        $sh->getStyle('A2')->getFont()->setBold(true);
        $sh->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()
           ->setARGB($pass / max(1, $n) >= 0.6 ? $OK : ($pass / max(1, $n) >= 0.4 ? $WARN : $BAD));

        $r = 4;
        $head($sh, $r, ['النسبة (لا تُجمع)', 'القيمة', 'المقام والمصدر']);
        $r++;
        foreach ($pcts as $p) {
            $sh->setCellValue("A$r", $p[0]); $sh->setCellValue("B$r", $p[1]); $sh->setCellValue("C$r", $p[2]);
            if (str_contains($p[1], 'غيرُ مقيس')) {
                $sh->getStyle("B$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($ALT);
            } else {
                $v = (float) $p[1];
                $sh->getStyle("B$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()
                   ->setARGB($v >= 60 ? $OK : ($v >= 40 ? $WARN : $BAD));
            }
            $r++;
        }

        $r += 1;
        $hr = $r;
        $head($sh, $r, $COLS);
        $r++;
        foreach ($list as $x) {
            foreach ($x['cells'] as $ci => $val) { $sh->setCellValue($cref($ci + 1, $r), $val); }
            $pf = $x['cells'][20];
            $sh->getStyle($cref(21, $r))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()
               ->setARGB($pf === 'PASS' ? $OK : $BAD);
            $sh->getStyle($cref(20, $r))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()
               ->setARGB($x['evGreen'] ? $OK : ($x['evRed'] ? $BAD : $WARN));
            if ($x['sev'] === 'P0') {
                $sh->getStyle($cref(23, $r))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($BAD);
            }
            $r++;
        }
        $sh->getStyle('A' . ($hr + 1) . ':' . $cref(count($COLS), $r - 1))->getAlignment()->setWrapText(true);
        foreach ([1 => 12, 2 => 16, 3 => 14, 4 => 46, 5 => 18, 6 => 10, 7 => 18, 8 => 16, 9 => 20, 10 => 22,
                  11 => 28, 12 => 30, 13 => 20, 14 => 16, 15 => 16, 16 => 40, 17 => 40, 18 => 20, 19 => 22,
                  20 => 26, 21 => 10, 22 => 11, 23 => 9, 24 => 11, 25 => 12, 26 => 12, 27 => 12, 28 => 20,
                  29 => 28, 30 => 14, 31 => 34, 32 => 34, 33 => 40, 34 => 40, 35 => 16, 36 => 14, 37 => 30] as $ci => $w) {
            $sh->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci))->setWidth($w);
        }
        $sh->setAutoFilter('A' . $hr . ':' . $cref(count($COLS), $r - 1));
        $sh->freezePane('A' . ($hr + 1));
    }

    /* ── ④ تحديثُ ورقةِ الإداراتِ الموجزةِ بالأرقامِ الثلاثيّة ─────── */
    $sh = $ss->getSheetByName('05_الإدارات_ملخص');
    if ($sh) {
        $r = 2;
        while ($sh->getCell('A' . $r)->getValue() !== null && $sh->getCell('A' . $r)->getValue() !== '') { $r++; }
        $r += 1;
        $sh->setCellValue("A$r", 'الحكمُ الثلاثيُّ لكلِّ إدارة — لا يُعدُّ البندُ مغلقًا إلا بقرارٍ وتنفيذٍ ودليلٍ حيٍّ معًا');
        $sh->mergeCells("A$r:H$r");
        $sh->getStyle("A$r")->getFont()->setBold(true);
        $r++;
        $head($sh, $r, ['الورقة', 'الإدارة', 'البنود', 'تنفيذٌ مُغلق', 'دليلٌ أخضر', 'مُغلقٌ بالثلاث', 'النسبةُ الصادقة', 'الجهدُ المتبقي (يوم-شخص)']);
        $r++;
        $tn = 0; $tp = 0; $te = 0.0;
        foreach ($summary as $s) {
            $sh->setCellValue("A$r", sprintf('D%02d', $s['idx']));
            $sh->setCellValue("B$r", $s['dept']); $sh->setCellValue("C$r", $s['n']);
            $sh->setCellValue("D$r", $s['impl']); $sh->setCellValue("E$r", $s['evg']);
            $sh->setCellValue("F$r", $s['pass']);
            $p = round($s['pass'] / max(1, $s['n']) * 100, 1);
            $sh->setCellValue("G$r", $p . '٪');
            $sh->setCellValue("H$r", round($s['eff'], 1));
            $sh->getStyle("G$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()
               ->setARGB($p >= 60 ? $OK : ($p >= 40 ? $WARN : $BAD));
            $tn += $s['n']; $tp += $s['pass']; $te += $s['eff'];
            $r++;
        }
        $sh->setCellValue("B$r", 'الإجمالي');
        $sh->setCellValue("C$r", $tn); $sh->setCellValue("F$r", $tp);
        $sh->setCellValue("G$r", round($tp / max(1, $tn) * 100, 1) . '٪');
        $sh->setCellValue("H$r", round($te, 1));
        $sh->getStyle('H2:H' . $r)->getNumberFormat()->setFormatCode('0.0');
        $sh->getStyle("B$r:H$r")->getFont()->setBold(true);
        $sh->getStyle("B$r:H$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($WARN);
    }

    fwrite(STDERR, 'أوراقُ الإدارات: ' . count($byDept) . "\n");
}
