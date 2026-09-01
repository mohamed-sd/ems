<?php
/**
 * tools/govui_finish_render.php — **مُثبِتُ التصييرِ الحيِّ لحقولِ المساحة**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحاجزُ الذي يرفعه** — `GOV_UI_FINISH` §٨·③ بنصِّه: *«صيِّرْ بعمليّةٍ نقيّةٍ
 *   وأثبتْ — ⛔ لا يكفي الجدولُ (§16)»*، و§16 من الأمرِ الحاكم: *«كل تغيير يجب
 *   اختباره في Rendered UI الفعلي وليس فقط في database table»*. **والمقياسُ
 *   الرسميُّ يقرأ أثرَ القرص** — فرأسٌ داخلَ شرطٍ لا يمرُّ، أو عمودٌ يحجبه
 *   حارسٌ، **يُعَدُّ مبنيًّا وهو لا يبلغ المستخدم**.
 *
 * ◆ **وقاعدةُ القبولِ واحدةٌ بالبنية** — التطبيعُ والتمييزُ والانتزاعُ من
 *   `tools/lib/rpr02_field_lib.php` **يشتملها المقياسُ الرسميُّ نفسُه**.
 *   ⛔ فلا «عدّادٌ وعارضٌ يتفرّقان» ([[counter-parity-two-readers]]) — والفرقُ
 *   المتبقّي فرقُ **مادّةِ الأثر** لا فرقُ حكم: القرصُ عندَ الأوّلِ، وناتجُ
 *   الجلسةِ عندَ هذا.
 *
 * ◆ **والدورُ يُنتقى من الصلاحيةِ لا يُفترَض**: أوّلُ دورٍ له `can_view` على
 *   موديولِ السطحِ **وله مستخدمٌ حيّ**. ⛔ **ولا يُحقَن دورُ سوبر ليمرَّ الحارس**
 *   — فسطحٌ بلا دورٍ يراه يُعلَن `NO_VIEWER_ROLE` باسمِه، وذاك حاجزُ
 *   `BLOCKED_OWNER` مقيسًا لا مُدَّعًى.
 *
 * ◆ **وعمليّةٌ لكلِّ سطح** (`tools/u13_render_one.php`): حارسُ الشاشةِ ينتهي
 *   بالخروج، فعمليّةٌ واحدةٌ تجمع أسطحًا يقتلها أوّلُ ارتداد.
 *
 * التشغيل:
 *   php tools/govui_finish_render.php <UNIT>         ← مساحةٌ واحدة
 *   php tools/govui_finish_render.php --all          ← إحدى وعشرون مساحة
 *   … [--miss] يعرض الناقصَ باسمِه
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/rpr02_field_lib.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

$MISS = in_array('--miss', $argv, true);
$ALL  = in_array('--all', $argv, true);
$UNIT = '';
foreach (array_slice($argv, 1) as $a) { if (substr($a, 0, 2) !== '--') { $UNIT = $a; } }
if ($UNIT === '' && !$ALL) { exit("الاستعمال: php tools/govui_finish_render.php <UNIT|--all> [--miss]\n"); }

$PHP  = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';
$NULL = (DIRECTORY_SEPARATOR === chr(92)) ? 'NUL' : '/dev/null';

/* ── الجسرُ المُعلَن — الزوجُ (سطحٌ · متطلبٌ) مرّةً واحدةً، كما في المقياس ── */
$bridge = array();
$sqlU = $ALL ? '' : (" AND tu.unit = '" . $conn->real_escape_string($UNIT) . "'");
$r = $conn->query("SELECT MIN(tu.target_uid) target_uid, tu.requirement_id, tu.screen_id,
                          MIN(tu.unit) unit, MIN(tu.name_ar) name_ar
                     FROM repair01_target_universe tu
                    WHERE tu.verdict = 'MATCHED' AND tu.screen_id <> '' AND tu.requirement_id <> ''"
                  . $sqlU . "
                    GROUP BY tu.screen_id, tu.requirement_id
                    ORDER BY MIN(tu.unit), tu.screen_id");
while ($x = $r->fetch_assoc()) { $bridge[] = $x; }
if (!$bridge) { exit("⛔ لا سطحَ مطابَقٌ في المساحة " . $UNIT . "\n"); }

/* حقولُ التصميم */
$des = array();
$r = $conn->query("SELECT requirement_id, field_name, field_type FROM repair01_fields ORDER BY requirement_id, id");
while ($x = $r->fetch_assoc()) { $des[$x['requirement_id']][] = $x; }

/* مسارُ السطحِ من السجلّ */
$reg = array();
$r = $conn->query("SELECT screen_id, screen_file, route, canonical_label_ar
                     FROM repair01_screen_registry WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'");
while ($x = $r->fetch_assoc()) { $reg[$x['screen_id']] = $x; }

/**
 * الدورُ الذي يرى السطحَ — **من `role_permissions` لا من افتراض**، ومعه مستخدمُه.
 * ⛔ ولا يُنتقى دورٌ بلا مستخدمٍ حيّ: التصييرُ يقرأ الشركةَ من صفِّ المستخدم.
 */
$viewerCache = array();
$pickViewer = function ($route) use ($conn, &$viewerCache) {
    if (array_key_exists($route, $viewerCache)) { return $viewerCache[$route]; }
    $mid = 0;
    $st = $conn->prepare("SELECT id FROM modules WHERE code = ? LIMIT 1");
    $st->bind_param('s', $route); $st->execute();
    if ($m = $st->get_result()->fetch_assoc()) { $mid = (int) $m['id']; }
    $st->close();
    if ($mid === 0) {
        $tail = '%/' . basename($route);
        $st = $conn->prepare("SELECT id FROM modules WHERE code LIKE ? ORDER BY CHAR_LENGTH(code), id LIMIT 1");
        $st->bind_param('s', $tail); $st->execute();
        if ($m = $st->get_result()->fetch_assoc()) { $mid = (int) $m['id']; }
        $st->close();
    }
    $out = array();
    if ($mid > 0) {
        /* وكلُّ حاملي الصلاحيةِ لا أوّلُهم: صفُّ role_permissions يمنح، لكنَّ
           الحارسَ الحيَّ قد يردُّ دورًا بعينِه لسببٍ آخر. فلو اكتُفي بأوّلِ دورٍ
           لَقُرئ السطحُ «لا يُصيَّر» وهو يُصيَّر لغيرِه — حاجزٌ كاذبٌ عطبُه في
           المُجَسِّ لا في الشاشة. */
        $q = $conn->query("SELECT rp.role_id, MIN(u.id) uid, MIN(u.company_id) co
                             FROM role_permissions rp
                             JOIN users u ON u.role_id = rp.role_id
                            WHERE rp.module_id = " . $mid . " AND rp.can_view = 1
                            GROUP BY rp.role_id ORDER BY rp.role_id");
        while ($q && $row = $q->fetch_assoc()) {
            $out[] = array('role' => (string) $row['role_id'], 'uid' => (int) $row['uid'],
                           'co' => (int) $row['co']);
        }
        if (!$out) { $out = null; }
    }
    $viewerCache[$route] = $out;
    return $out;
};

/* ── التصييرُ والقياس ─────────────────────────────────────────────────────── */
$byUnit = array(); $rows = array();
foreach ($bridge as $b) {
    $sc   = $b['screen_id'];
    $unit = (string) $b['unit'];
    if (!isset($byUnit[$unit])) {
        $byUnit[$unit] = array('n' => 0, 'appl' => 0, 'hit' => 0, 'blocked' => 0, 'full' => 0);
    }
    $route = isset($reg[$sc]) ? str_replace(chr(92), '/', (string) $reg[$sc]['route']) : '';
    $route = ltrim($route, '/');
    if ($route === '' || !is_file($ROOT . '/' . $route)) {
        $route = isset($reg[$sc]) ? ltrim(str_replace(chr(92), '/', (string) $reg[$sc]['screen_file']), '/') : '';
    }

    $dl   = isset($des[$b['requirement_id']]) ? $des[$b['requirement_id']] : array();
    $appl = 0;
    foreach ($dl as $f) { if ($f['field_type'] !== 'AUDIT') { $appl++; } }

    $why = ''; $hit = 0; $miss = array(); $bytes = 0; $usedRole = '';
    $viewers = array();
    if ($route === '' || !is_file($ROOT . '/' . $route)) {
        $why = 'NO_ARTIFACT';
    } else {
        $viewers = $pickViewer($route);
        if ($viewers === null) { $why = 'NO_VIEWER_ROLE'; $viewers = array(); }
    }
    $body = '';
    if ($why === '') {
        foreach ($viewers as $v0) {
            $cmd = escapeshellarg($PHP) . ' ' . escapeshellarg($ROOT . '/tools/u13_render_one.php')
                 . ' ' . escapeshellarg($route) . ' ' . escapeshellarg($v0['role'])
                 . ' ' . (int) $v0['uid'] . ' ' . (int) $v0['co'] . ' --body 2>' . $NULL;
            $b0 = (string) shell_exec($cmd);
            $nl = strpos($b0, chr(10));
            $b0 = ($nl === false) ? '' : substr($b0, $nl + 1);
            if (strlen($b0) > strlen($body)) { $body = $b0; $usedRole = $v0['role']; }
            if (strlen($body) >= 2000) { break; }
        }
        $bytes = strlen($body);
        if ($bytes < 2000) { $why = 'RENDER_EMPTY'; }
        else {
            $x = fm_extract($body);
            $bagStr = array(); $bagTok = array();
            foreach (array('F1', 'F2', 'F3') as $k) {
                foreach ($x[$k] as $v) {
                    $nv = fm_norm($v);
                    if ($nv !== '') { $bagStr[$nv] = 1; }
                    foreach (fm_tok($v, $FM_STOP) as $t) { $bagTok[$t] = 1; }
                }
            }
            foreach ($dl as $f) {
                if ($f['field_type'] === 'AUDIT') { continue; }
                $h = fm_hit(fm_tok($f['field_name'], $FM_STOP), $bagTok, $bagStr, fm_norm($f['field_name']));
                if ($h !== '') { $hit++; } else { $miss[] = $f['field_name']; }
            }
        }
    }
    $byUnit[$unit]['n']++;
    $byUnit[$unit]['appl'] += $appl;
    $byUnit[$unit]['hit']  += $hit;
    if ($why !== '') { $byUnit[$unit]['blocked']++; }
    elseif ($appl > 0 && $hit === $appl) { $byUnit[$unit]['full']++; }
    $rows[] = array('unit' => $unit, 'sid' => $sc, 'req' => $b['requirement_id'],
                    'name' => isset($reg[$sc]) ? $reg[$sc]['canonical_label_ar'] : $b['name_ar'],
                    'route' => $route, 'appl' => $appl, 'hit' => $hit, 'miss' => $miss,
                    'why' => $why, 'bytes' => $bytes, 'role' => $usedRole);
}

/* ── التفريغُ الكاملُ للبناء — --dump=<ملف> ─────────────────────────────── */
foreach ($argv as $a0) {
    if (strpos($a0, '--dump=') === 0) {
        file_put_contents(substr($a0, 7), json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "  ✔ فُرِّغ التفصيلُ إلى " . substr($a0, 7) . "\n";
    }
}

/* ── العرض ────────────────────────────────────────────────────────────────── */
echo "\n=== مُثبِتُ التصييرِ الحيِّ — الحقلُ يبلغ المستخدمَ أو لا يبلغ ===\n";
echo "  المقياسُ الرسميُّ tools/rpr02_field_measure.php (أثرُ القرص) — وهذا يُثبت البلوغَ ولا يَعُدُّ بدلَه\n\n";
foreach ($rows as $r0) {
    if ($r0['why'] === '' && $r0['appl'] === $r0['hit']) { continue; }
    printf("-- %s . %s . %s\n   %s  [دور %s . %d بايت]  %d/%d%s\n",
        $r0['unit'], $r0['sid'], $r0['name'], $r0['route'],
        $r0['role'] === '' ? '?' : $r0['role'],
        $r0['bytes'], $r0['hit'], $r0['appl'], $r0['why'] === '' ? '' : ('  << ' . $r0['why']));
    if ($MISS) { foreach ($r0['miss'] as $m) { echo "      x " . $m . "\n"; } }
}
echo "\n";
printf("  %-10s %5s %6s %6s %6s %6s %6s\n", 'المساحة', 'أسطح', 'المقام', 'مصير', 'ناقص', 'تامة', 'محجوب');
$T = array('n' => 0, 'appl' => 0, 'hit' => 0, 'full' => 0, 'blocked' => 0);
foreach ($byUnit as $u => $v) {
    printf("  %-10s %5d %6d %6d %6d %6d %6d\n", $u, $v['n'], $v['appl'], $v['hit'],
           $v['appl'] - $v['hit'], $v['full'], $v['blocked']);
    foreach ($T as $k => $_) { $T[$k] += $v[$k]; }
}
printf("  %-10s %5d %6d %6d %6d %6d %6d\n", 'الكل', $T['n'], $T['appl'], $T['hit'],
       $T['appl'] - $T['hit'], $T['full'], $T['blocked']);
echo "\n";
