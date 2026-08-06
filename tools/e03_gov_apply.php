<?php
/**
 * tools/e03_gov_apply.php — الموجة ٤: الأعمدة الحاكمة للشاشات خارج وثيقة التصميم
 * ───────────────────────────────────────────────────────────────────────────
 * cmp03_apply يغطي شاشات SCR-DES (194) — وهذا يعمّم النواة الحاكمة (AC-E03-03)
 * على بقية الشاشات الحية: الكيان · المُنشئ · المعتمِد · مرجع التفويض · المرجع
 * الأب · تاريخا الإنشاء والاعتماد · الحالة — عبر طبقة gov_columns المركزية
 * نفسها (رؤوس data-gov والخلايا يحشوها ui-unification.padGovernanceCells).
 * الموجود بمرادفه لا يُكرَّر · serverSide وcolspan والترويسة الغائبة تُتخطى
 * معلَنة · فوق 22 عمودًا ينهار لسطرٍ تابع (توصية المالك ①) · حارة الجلسة
 * الموازية (git-modified) لا تُمس · lint بعد كل كتابة وإلا تراجُع.
 * التشغيل: php tools/e03_gov_apply.php [--apply] [--limit=N]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
$limit = 0;
foreach ($argv as $a) { if (strpos($a, '--limit=') === 0) { $limit = (int) substr($a, 8); } }
require_once $ROOT . '/includes/gov_columns.php';

$MAX_VISIBLE = 22;

/* حارة الجلسة الموازية: كل معدَّل غير ملتزم لا يُمس */
$laneBlocked = array();
foreach (explode("\n", (string) shell_exec('git -C "' . $ROOT . '" status --porcelain')) as $ln) {
    $ln = trim($ln);
    if ($ln === '') { continue; }
    $f = trim(substr($ln, 2));
    $laneBlocked[str_replace('\\', '/', $f)] = 1;
}

/* النواة الحاكمة الثمانية (تغطي الفئات السبع) + مرادفات الوجود */
$CORE = array(
    'entity'        => array('الكيان', 'الشركه'),
    'creator'       => array('المنشئ', 'انشاه', 'اضيف بواسطه', 'انشئ بواسطه'),
    'approver'      => array('المعتمد', 'اعتمده'),
    'authority_ref' => array('مرجع التفويض', 'سند الصلاحيه'),
    'parent_ref'    => array('المرجع الاب', 'المستند الاصل'),
    'created_at'    => array('تاريخ الانشاء', 'تاريخ الاضافه', 'اضيف في', 'انشئ في'),
    'approved_at'   => array('تاريخ الاعتماد'),
    'status'        => array('الحاله', 'حاله الصف', 'حاله المستند'),
);

function eg_norm($s) {
    $s = preg_replace('/\s+/u', ' ', trim((string) $s));
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace('ى', 'ي', $s);
    return preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);
}

$dirs = array('Approvals', 'Contracts', 'Employees', 'Equipments', 'Finance', 'FinRequests', 'Financing',
    'Governance', 'Maintenance', 'Operations', 'Portal', 'Procurement', 'Projects', 'Settings',
    'Tickets', 'Transport', 'Workforce', 'admin', 'main');

$registry = ems_gov_registry();
$done = 0; $cols = 0; $skipped = array();
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $path) {
        if ($limit && $done >= $limit) { break 2; }
        $rel = str_replace('\\', '/', substr($path, strlen($ROOT) + 1));
        $src = (string) file_get_contents($path);
        if (strpos($src, 'insidebar') === false) { continue; }              // ليست شاشة كاملة
        if (strpos($src, 'data-gov') !== false || strpos($src, 'gov_columns') !== false) { continue; } // مغطاة
        if (isset($laneBlocked[$rel])) { $skipped[] = "$rel — حارة موازية"; continue; }
        if (preg_match('/serverSide\s*:\s*true/', $src)) { $skipped[] = "$rel — serverSide"; continue; }

        /* أفضل ترويسة: أكثر <thead> رؤوسًا ورقيةً بلا colspan */
        if (!preg_match_all('/<thead\b[^>]*>(.*?)<\/thead>/su', $src, $mThead, PREG_OFFSET_CAPTURE)) {
            $skipped[] = "$rel — لا thead";
            continue;
        }
        $best = null;
        foreach ($mThead[1] as $tb) {
            if (!preg_match('/<tr\b[^>]*>(.*?)<\/tr>/su', $tb[0], $mTr, PREG_OFFSET_CAPTURE)) { continue; }
            $trContent = $mTr[1][0];
            $trAbs = $tb[1] + $mTr[1][1];
            if (!preg_match_all('/<th\b([^>]*)>(.*?)<\/th>/su', $trContent, $mTh)) { continue; }
            $colspan = false; $texts = array();
            foreach ($mTh[1] as $i => $attrs) {
                if (preg_match('/colspan/i', $attrs)) { $colspan = true; break; }
                $txt = preg_replace('/<\?php.*?\?>/su', '', $mTh[2][$i]);
                $texts[] = eg_norm(strip_tags($txt));
            }
            if ($colspan) { continue; }
            $n = count($texts);
            if ($best === null || $n > $best[0]) { $best = array($n, $trAbs, $trContent, $texts); }
        }
        if ($best === null) { $skipped[] = "$rel — ترويسة مجمّعة أو غائبة"; continue; }
        list($existingCount, $trAbs, $trContent, $texts) = $best;
        if ($existingCount < 2) { $skipped[] = "$rel — ترويسة أقل من عمودين"; continue; }

        /* الناقص من النواة: الموجود بمرادفه لا يُكرَّر */
        $inject = array();
        foreach ($CORE as $key => $syns) {
            $found = false;
            foreach ($texts as $t) {
                if ($t === '') { continue; }
                foreach ($syns as $s) {
                    $sn = eg_norm($s);
                    if ($t === $sn || mb_strpos($t, $sn) !== false) { $found = true; break 2; }
                }
            }
            if (!$found) { $inject[$key] = $registry[$key][0]; }
        }
        if (!$inject) { continue; }

        $indent = '              ';
        if (preg_match_all('/\n([ \t]*)<th\b/', $trContent, $mInd) && $mInd[1]) { $indent = end($mInd[1]); }
        $block = "\n" . $indent . "<!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->";
        $seq = $existingCount;
        foreach ($inject as $k => $lbl) {
            $seq++;
            $def = $registry[$k];
            $over = $seq > $MAX_VISIBLE ? ' none' : '';
            $block .= "\n" . $indent . '<th class="ems-gov-th' . $over . '" data-gov="' . $k . '" data-slice="' . $def[1] . '" title="' . $def[2] . '">' . $lbl . '</th>';
            $cols++;
        }
        $newTr = rtrim($trContent, " \t\n") . $block . "\n" . $indent;
        $newSrc = substr($src, 0, $trAbs) . $newTr . substr($src, $trAbs + strlen($trContent));

        fwrite(STDOUT, ($APPLY ? '✔ ' : '· ') . $rel . ' ← ' . count($inject) . " عمودًا\n");
        $done++;
        if ($APPLY) {
            file_put_contents($path, $newSrc);
            $lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1');
            if (strpos((string) $lint, 'No syntax errors') === false) {
                fwrite(STDOUT, "‼ خطأ صياغة في {$rel} — تراجُع\n");
                file_put_contents($path, $src);
                $done--;
            }
        }
    }
}
fwrite(STDOUT, "────────────\n" . ($APPLY ? 'حُقن' : 'سيُحقن') . ": {$cols} عمودًا في {$done} شاشة · تخطّي معلَن: " . count($skipped) . "\n");
foreach ($skipped as $s) { fwrite(STDOUT, "  ⚠ {$s}\n"); }
