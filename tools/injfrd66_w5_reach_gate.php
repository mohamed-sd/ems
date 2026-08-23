<?php
/**
 * tools/injfrd66_w5_reach_gate.php — بوابةُ الموجةِ ⑤: بلوغُ ما أُخفي
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الخاملُ ليس المفقود**: الدورانِ اللذانِ تدور عليهما الحزمةُ كلُّها هما
 *   **أخملُ دورَين في النظام** — المبيعاتُ 12 بخمسةَ عشرَ بندًا حيًّا مقابلَ
 *   أربعةٍ وستّينَ خاملًا، وإدارةُ الموردين 2 بستّةَ عشرَ مقابلَ خمسةٍ وخمسين.
 *   **وكلُّ ملفٍّ منها موجودٌ على القرص.**
 *
 * ◆ **ولم تُطفَأ إهمالًا بل بقرار**: `2027_10_03_align_nav_apply.php` أخفتها
 *   لأنَّ البندَ **صار تبويبًا في ملفٍّ أمّ** — «الدمجُ في الواجهةِ لا في السجل».
 *   ⇐ فإعادةُ تشغيلِها جملةً **نقضٌ للتنقّلِ المستهدَفِ الذي تطلبه الوثيقةُ
 *     نفسُها** — لا إصلاحٌ له. والسايدبارُ يحترم الإطفاءَ في موضعَين
 *     (`unified_nav.php`) حتى لا يعود المُطفأُ من بابِ الاصطناع.
 *
 * ◆ **لكنَّ الهجرةَ ادَّعت البلوغَ ولم يقسه أحدٌ بعدَها**: كلُّ صفٍّ في
 *   `gov_nav_hidden_log` يحمل عذرَه في `reachable` — **والعذرُ دعوى حتى يُقاس**.
 *
 * ◆ **والبلوغُ لا يمرُّ بالإخوةِ وحدَهم**: سجلٌّ حيٌّ يفتح مِرساةَ العائلةِ
 *   يُبلغ الشريطَ كلَّه — فـ`Contracts/contracts.php` الحيُّ لدورِ المبيعاتِ
 *   يفتح `contracts_details.php` وفيه شريطُ ملفِّ العقد. **وقصرُ الفحصِ على
 *   الإخوةِ حمَّر خمسةَ عشرَ صفًّا سليمًا في أوَّلِ تشغيل.**
 *
 * ◆ **واسمُ الملفِّ يُطابَق بحدِّه لا باحتوائه**: «contracts_details.php» جزءٌ
 *   نصّيٌّ من «supplierscontracts_details.php» — فمطابقةٌ رخوةٌ تكذب في
 *   الاتجاهِ المقابلِ وتُخضِّر ما لا يُبلَغ. **والفخّانِ في قياسٍ واحد.**
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_w5_reach_gate.php          التقرير
 *   php tools/injfrd66_w5_reach_gate.php --gate   رمزُ خروجٍ 1 عند دعوى بلوغٍ لا تصمد
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$GATE = in_array('--gate', $argv, true);
$base = static function (string $r): string {
    return preg_replace('~[?#].*$~', '', trim($r));
};

/* ── ① أشرطةُ التبويبِ وما تُعلنه من مساراتٍ وما يضمُّها من ملفات ─────────── */
$HELPERS = array('sales_family_tabs', 'supplier_file_tabs', 'contract_file_tabs',
                 'entity_tabs', 'financing_file_tabs');
$declared = array();
$members  = array();
foreach ($HELPERS as $h) {
    $src = (string) @file_get_contents($ROOT . '/includes/' . $h . '.php');
    $declared[$h] = array();
    if (preg_match_all('~([A-Za-z_]+/[a-z_0-9]+\.php)~', $src, $mm)) {
        foreach ($mm[1] as $r) { $declared[$h][$r] = 1; }
    }
    $members[$h] = array();
}
/* والعضويةُ تُقاس بالضمِّ الفعليِّ لا بالتسمية: من يُدرِج الشريطَ فهو في عائلتِه */
$SCAN = array('Clients','Contracts','Suppliers','Opportunities','Operations','Finance',
              'Workforce','Equipments','Projects','Maintenance','Portal','Risk','Tickets',
              'Employees','Financing','Approvals','Procurement');
foreach ($SCAN as $d) {
    foreach ((array) glob($ROOT . '/' . $d . '/*.php') as $f) {
        $body = (string) @file_get_contents($f);
        if ($body === '') { continue; }
        foreach ($HELPERS as $h) {
            if (mb_strpos($body, $h . '.php') !== false) { $members[$h][$d . '/' . basename($f)] = 1; }
        }
    }
}

/* ── ② البنودُ الحيّةُ لكلِّ دور ─────────────────────────────────────────── */
$live = array(); $liveAny = array();
$q = mysqli_query($conn, "SELECT role_id, route FROM nav_items WHERE active = 1");
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $b = $base($r['route']);
    $live[(int) $r['role_id']][$b] = 1;
    $liveAny[$b][(int) $r['role_id']] = 1;
}

/* ── ③ وصلةٌ بحدِّ الاسمِ لا باحتوائه ────────────────────────────────────── */
$linksTo = static function (string $from, string $to) use ($ROOT): bool {
    static $cache = array();
    if (!array_key_exists($from, $cache)) {
        $cache[$from] = (string) @file_get_contents($ROOT . '/' . $from);
    }
    if ($cache[$from] === '') { return false; }
    $needle = preg_quote(basename($to), '~');
    return (bool) preg_match('~(^|[^A-Za-z0-9_])' . $needle . '~', $cache[$from]);
};

echo "\n═══ INJ-FRD-01 · الموجةُ ⑤ — بلوغُ ما أُخفي ═══\n";

$rows = mysqli_query($conn, "SELECT role_id, route, label_ar, reachable
                               FROM gov_nav_hidden_log ORDER BY role_id, route");
$ok = 0; $bad = array(); $owned = array(); $byKind = array(); $howTab = array();
while ($rows && ($x = mysqli_fetch_assoc($rows))) {
    $rid = (int) $x['role_id'];
    $b   = $base($x['route']);
    $k   = (string) $x['reachable'];
    $byKind[$k] = (isset($byKind[$k]) ? $byKind[$k] : 0) + 1;

    if ($k === 'TAB_IN_PARENT') {
        $home = null; $openable = false; $how = '';
        foreach ($HELPERS as $h) {
            if (!isset($declared[$h][$b])) { continue; }
            $home = $h;
            $sibs = array_keys($members[$h]);
            foreach ($sibs as $sib) {
                if (isset($live[$rid][$sib])) { $openable = true; $how = 'أخٌ حيّ'; break 2; }
            }
            foreach (array_keys($live[$rid]) as $lr) {
                foreach ($sibs as $sib) {
                    if ($lr !== $sib && $linksTo($lr, $sib)) {
                        $openable = true; $how = 'مِرساةٌ بسجلٍّ حيّ'; break 3;
                    }
                }
            }
        }
        if ($home === null) {
            $bad[] = array($rid, $b, 'ادَّعى تبويبًا — ولا شريطَ يُعلن مسارَه');
        } elseif (!$openable) {
            $bad[] = array($rid, $b, 'تبويبٌ في `' . $home . '` — ولا بابَ حيًّا يفتح الشريطَ لهذا الدور');
        } else {
            $ok++;
            $kk = $rid . '|' . $how;
            $howTab[$kk] = (isset($howTab[$kk]) ? $howTab[$kk] : 0) + 1;
        }

    } elseif ($k === 'ACTIVE_FOR_OTHER_ROLE') {
        $others = array_diff(array_keys(isset($liveAny[$b]) ? $liveAny[$b] : array()), array($rid));
        if (!$others) {
            $bad[] = array($rid, $b, 'ادَّعى نشاطًا لدورٍ آخر — ولا دورَ يحمله حيًّا');
        } else { $ok++; }

    } elseif ($k === 'OWNED_ELSEWHERE') {
        /* ◆ **الحكمُ المكتوبُ يُسرَد ولا يُصدَّق ضمنًا** — لكنَّه يُقاس أيضًا:
             «مملوكٌ لإدارةٍ أخرى» دعوى فارغةٌ إن لم يكن حيًّا لأحدٍ أصلًا. */
        $holders = array_diff(array_keys(isset($liveAny[$b]) ? $liveAny[$b] : array()), array($rid));
        if (!$holders) {
            $bad[] = array($rid, $b, 'ادَّعى ملكيةً لإدارةٍ أخرى — ولا إدارةَ تحمله حيًّا');
        } else {
            $owned[] = array($rid, $b, 'حيٌّ لدى: ' . implode('، ', $holders));
        }
    } else {
        $bad[] = array($rid, $b, 'عذرٌ غيرُ معروف: ' . $k);
    }
}

echo "\n  ── توزيعُ العذر\n";
foreach ($byKind as $k => $n) { printf("     %-24s %d\n", $k, $n); }

if ($howTab) {
    echo "\n  ── وبأيِّ بابٍ بُلِغ التبويب\n";
    foreach ($howTab as $key => $n) {
        $p = explode('|', $key);
        printf("     دور %-3s %-22s %d\n", $p[0], $p[1], $n);
    }
}

if ($owned) {
    echo "\n  ── «مملوكٌ لإدارةٍ أخرى» — حكمٌ مكتوبٌ يُسرَد ويُقاس مالكُه\n";
    foreach ($owned as $o) { printf("     ○ دور %-3s %-40s %s\n", $o[0], $o[1], $o[2]); }
}

if ($bad) {
    echo "\n  ── دعاوى بلوغٍ لم تصمد\n";
    foreach ($bad as $bd) { printf("     ✘ دور %-3s %-44s %s\n", $bd[0], $bd[1], $bd[2]); }
} else {
    echo "\n  ✔ كلُّ دعوى بلوغٍ صمدت للقياس\n";
}

printf("\n  صمد %d · لم يصمد %d · مُحالٌ بحكمٍ مكتوب %d   (من %d صفًّا مُخفًى)\n",
    $ok, count($bad), count($owned), $ok + count($bad) + count($owned));
echo "\n  ◆ الخاملُ ليس المفقود: إعادةُ تشغيلِ الخاملِ جملةً تنقض التنقّلَ المستهدَف.\n";
echo "  ◆ والعذرُ دعوى حتى يُقاس — والقياسُ نفسُه يكذب مرَّتَين: بابٌ لم يُفتَّش\n";
echo "    عنه (المِرساةُ لا الإخوة) واسمٌ طُوبق باحتوائه لا بحدِّه.\n\n";

exit($GATE && $bad ? 1 : 0);
