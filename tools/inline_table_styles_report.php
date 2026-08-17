<?php
/**
 * tools/inline_table_styles_report.php
 * كشفُ الشاشاتِ التي تُصمِّمُ جداولَها من كتلِ <style> داخلَها — مع الإدارةِ
 * وتفصيلِ الإعلاناتِ المرشَّحةِ للكنس.
 * ═══════════════════════════════════════════════════════════════════════════
 * لماذا تُكنَس أصلًا وهي «ميّتة»؟
 *   لأنها ميّتةٌ **بشرطٍ** لا بذاتها: تعيشُ لحظةَ أن يُنزَع `!important` من
 *   قاعدةٍ في ems-tables.css، أو تُدرَج ورقةٌ بعدَه. فهي دَينٌ مؤجَّلٌ لا براءة.
 *
 * وما لا يُكنَس: إعلانُ **هندسةٍ** (حشوة · عرض · محاذاة) — شأنٌ شاشيٌّ مشروع.
 * ولا إعلانٌ يقرأ من لوحةِ المفاتيح — فذاك التزامٌ لا مخالفة.
 *
 * الاستعمال:  php tools/inline_table_styles_report.php [--decls]
 * ═══════════════════════════════════════════════════════════════════════════
 */

$ROOT = dirname(__DIR__);
$showDecls = in_array('--decls', $argv, true);
$SKIP = array('vendor', 'node_modules', '.git', 'storage', '.ssdiff', 'docs', 'database', 'tools', 'tests');

$IDENTITY = array('color', 'background', 'background-color', 'background-image',
                  'border', 'border-color', 'border-top', 'border-bottom',
                  'border-top-color', 'border-bottom-color', 'border-left-color',
                  'border-right-color', 'font-weight', 'font-size', 'border-radius');

$NOT_A_TABLE = array(
    '.card', '.box', '.panel', '.table-container', '.table-responsive',
    '.table-section', '.table-wrap', '.table-responsive-wrapper',
    'badge', 'chip', 'btn', 'button', 'icon', 'pill',
    '.dt-button', '.dataTables_filter', '.dataTables_length', '.dataTables_info',
    '.dataTables_paginate', '.dataTables_wrapper .', '.ems-xscroll',
    '.action-btn', '.delete-btn', '.edit-btn', '.eye-btn', '.mnt-seg-btn',
    '.portable', '.notable', '.tablet', '.contracts-table-filter-wrap',
    '.contracts-group-toolbar', '.ems-view-picker', '.ems-colvis', '.ems-more',
);

function it_targets_table($sel, $NOT) {
    $s = strtolower($sel);
    foreach ($NOT as $n) if (strpos($s, strtolower($n)) !== false) return false;
    return (bool) preg_match('~(^|[\s>+\~,(\.\#])(table|thead|tbody|tfoot|tr|th|td|datatable|alltable|alltables|modern-table)([\s>+\~,)\.\:\[]|$)~i', $sel);
}
function it_reads_token($v) { return (bool) preg_match('~var\(\s*--table-~i', $v); }
function it_raw($v) {
    $x = preg_replace('~var\(\s*--table-[a-z0-9-]+[^)]*\)~i', 'TOKEN', $v);
    return (bool) (preg_match('~\#[0-9a-f]{3,8}\b~i', $x) || preg_match('~\b(rgb|rgba|hsl|hsla)\s*\(~i', $x)
        || preg_match('~\bvar\(\s*--(?!table-)~i', $x)
        || preg_match('~\b(red|blue|green|black|white|gray|grey|orange|yellow|navy|transparent|inherit|currentColor)\b~i', $x)
        || preg_match('~^\s*\d~', $x) || preg_match('~\b(bold|bolder|normal|lighter)\b~i', $x));
}

$st = array(
    'a' => it_targets_table('.x thead th', $NOT_A_TABLE) === true,
    'b' => it_targets_table('.table-container', $NOT_A_TABLE) === false,
    'c' => it_reads_token('var(--table-text,#161616)') === true,
    'd' => it_raw('#eee') === true,
    'e' => it_raw('var(--table-text, #161616)') === false,
);
$bad = array(); foreach ($st as $k => $v) if (!$v) $bad[] = $k;
if (count($bad)) { echo 'فحصُ الفاحصِ فشل: ' . implode(',', $bad) . PHP_EOL; exit(1); }

/* ── الإدارةُ من nav_items ── */
$deptOf = array(); $navOk = false;
$envF = $ROOT . '/.env';
if (is_file($envF)) {
    $env = array();
    foreach (file($envF, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $l = trim($l); if ($l === '' || $l[0] === '#') continue;
        $i = strpos($l, '='); if ($i === false) continue;
        $env[trim(substr($l, 0, $i))] = trim(substr($l, $i + 1));
    }
    if (isset($env['DB_HOST'])) {
        $hp = explode(':', $env['DB_HOST']);
        $db = @new mysqli($hp[0], $env['DB_USER'], isset($env['DB_PASS']) ? $env['DB_PASS'] : '',
                          $env['DB_NAME'], isset($hp[1]) ? (int) $hp[1] : 3306);
        if (!$db->connect_errno) {
            $db->set_charset('utf8mb4');
            $q = $db->query("SELECT n.route, g.name AS grp, m.name AS mod_name
                               FROM nav_items n
                          LEFT JOIN link_groups g ON g.id = n.group_id
                          LEFT JOIN modules     m ON m.id = n.module_id
                              WHERE n.route <> ''");
            if ($q) {
                $navOk = true;
                while ($r = $q->fetch_assoc()) {
                    $k = ltrim(str_replace('\\', '/', strtok($r['route'], '?')), './');
                    if ($k === '') continue;
                    if (!isset($deptOf[$k])) $deptOf[$k] = array('g' => array(), 'm' => array());
                    if (trim((string) $r['grp']) !== '' && !in_array($r['grp'], $deptOf[$k]['g'], true)) $deptOf[$k]['g'][] = $r['grp'];
                    if (trim((string) $r['mod_name']) !== '' && !in_array($r['mod_name'], $deptOf[$k]['m'], true)) $deptOf[$k]['m'][] = $r['mod_name'];
                }
            }
        }
    }
}
/**
 * تبعيّةُ الشاشة.
 * ⚠ المطابقةُ بالاسمِ المجرَّدِ (`basename`) **حُذفت**: أعطت `index.php` تبعيّةَ
 * «المراسلات · مساحتي الشخصية» لأن في القائمةِ مسارًا آخرَ ينتهي بالاسمِ نفسِه.
 * ونسبةُ شاشةٍ إلى إدارةٍ ليست لها أسوأُ من قولِ «غيرُ مسجَّلة».
 * فالمقبولُ: تطابقٌ تامّ، أو ذيلُ مسارٍ **على حدِّ مجلَّد** (`/Reports/x.php`).
 */
function it_dept($rel, $deptOf, $key) {
    if (isset($deptOf[$rel]) && count($deptOf[$rel][$key])) return implode(' · ', $deptOf[$rel][$key]);
    /* ملفٌّ في الجذرِ (بلا مجلَّد) لا يُطابَق بالذيل: `index.php` كان يلتقط
       تبعيّةَ أيِّ مسارٍ ينتهي بـ`/index.php` فنُسبت صفحةُ الهبوطِ إلى
       «المراسلات». فالجذرُ لا يُقبل فيه إلا التطابقُ التام. */
    if (strpos($rel, '/') === false) return 'غيرُ مسجَّلةٍ في القائمة';
    foreach ($deptOf as $r => $v) {
        if (!count($v[$key])) continue;
        if ($r === $rel) return implode(' · ', $v[$key]);
        if (strlen($r) > strlen($rel) && substr($r, -(strlen($rel) + 1)) === '/' . $rel) {
            return implode(' · ', $v[$key]);
        }
    }
    return 'غيرُ مسجَّلةٍ في القائمة';
}

/* ── المسح ── */
$rows = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (strtolower($f->getExtension()) !== 'php') continue;
    $p = str_replace(chr(92), '/', $f->getPathname());
    $rel = substr($p, strlen($ROOT) + 1);
    if (count(array_intersect(explode('/', $rel), $SKIP)) > 0) continue;
    $src = file_get_contents($p);
    if (!preg_match_all('~<style\b[^>]*>(.*?)</style>~is', $src, $sm)) continue;
    $raw = 0; $tok = 0; $geo = 0; $imp = 0; $props = array(); $decls = array();
    foreach ($sm[1] as $blk) {
        $blk = preg_replace('~/\*.*?\*/~s', ' ', $blk);
        if (!preg_match_all('~([^{}]+)\{([^}]*)\}~s', $blk, $mm, PREG_SET_ORDER)) continue;
        foreach ($mm as $r) {
            $sel = trim(preg_replace('~\s+~', ' ', $r[1]));
            if ($sel === '' || $sel[0] === '@') continue;
            if (!it_targets_table($sel, $NOT_A_TABLE)) continue;
            foreach (explode(';', $r[2]) as $d) {
                if (strpos($d, ':') === false) continue;
                list($pp, $vv) = explode(':', $d, 2);
                $pp = strtolower(trim($pp)); $vv = trim($vv);
                if ($pp === '' || $vv === '') continue;
                if (!in_array($pp, $IDENTITY, true)) { $geo++; continue; }
                if (it_reads_token($vv)) { $tok++; continue; }
                if (!it_raw($vv)) { $geo++; continue; }
                $raw++;
                if (stripos($vv, '!important') !== false) $imp++;
                $props[$pp] = isset($props[$pp]) ? $props[$pp] + 1 : 1;
                $decls[] = array($sel, $pp . ': ' . $vv);
            }
        }
    }
    if ($raw === 0) continue;
    arsort($props);
    $rows[] = array('screen' => $rel, 'raw' => $raw, 'imp' => $imp, 'tok' => $tok, 'geo' => $geo,
                    'props' => $props, 'decls' => $decls,
                    'mod' => it_dept($rel, $deptOf, 'm'), 'grp' => it_dept($rel, $deptOf, 'g'));
}
usort($rows, function ($a, $b) { return $b['raw'] - $a['raw']; });

echo 'فحصُ الفاحص: ' . count($st) . ' حالةً سليمة · قراءةُ التبعيّة: ' . ($navOk ? 'متاحة' : 'غيرُ متاحة') . PHP_EOL . PHP_EOL;
echo '═══════ شاشاتٌ تُصمِّمُ جداولَها من كتلةِ <style> داخلَها ═══════' . PHP_EOL . PHP_EOL;
$i = 0; $tRaw = 0; $tImp = 0;
foreach ($rows as $r) {
    $i++; $tRaw += $r['raw']; $tImp += $r['imp'];
    echo $i . ') ' . $r['screen'] . PHP_EOL;
    echo '     الوحدة   : ' . $r['mod'] . PHP_EOL;
    echo '     المجموعة : ' . $r['grp'] . PHP_EOL;
    echo '     يُكنَس    : ' . $r['raw'] . ' إعلانَ هويّةٍ صريح'
       . ($r['imp'] ? ('  — منها ' . $r['imp'] . ' بـ!important') : '') . PHP_EOL;
    $ps = array(); foreach ($r['props'] as $k => $n) $ps[] = $k . '×' . $n;
    echo '     الخصائص  : ' . implode(' · ', $ps) . PHP_EOL;
    echo '     يبقى     : ' . $r['geo'] . ' هندسيًّا' . ($r['tok'] ? (' + ' . $r['tok'] . ' من اللوحة') : '') . PHP_EOL;
    if ($showDecls) foreach ($r['decls'] as $d) echo '        ⟨' . substr($d[0], 0, 48) . '⟩  ' . substr($d[1], 0, 46) . PHP_EOL;
    echo PHP_EOL;
}
echo str_repeat('─', 72) . PHP_EOL;
echo 'المجموع: ' . count($rows) . ' شاشةً · ' . $tRaw . ' إعلانًا للكنس (منها ' . $tImp . ' بـ!important)' . PHP_EOL;
if (!$showDecls) echo PHP_EOL . '(أضف --decls لتفصيلِ كلِّ إعلان)' . PHP_EOL;
