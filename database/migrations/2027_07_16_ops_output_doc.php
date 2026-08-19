<?php
/**
 * 2027_07_16_ops_output_doc.php — البندُ ⑧ : المستندُ الناتجُ أو وسمُ الانتقال
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ المواصفة (ف١٥-١ · البند ٨): «ترتيبُ الشاشاتِ داخلَ مجموعةِ العملياتِ
 *   يتبع: المستندَ الداخلَ ← الشاشةَ ← **المستندَ الناتجَ** ← الحالةَ التاليةَ
 *   ← الشاشةَ التالية». وبوابتُه: «**صفرُ شاشةٍ في مجموعةِ عملياتٍ بلا مستندٍ
 *   ناتجٍ معلومٍ أو وسمِ انتقالِ حالةٍ صريح**».
 *
 * ◆ والمقيسُ قبلَ هذه الهجرة: **لا عمودَ لهما أصلًا** — فالبندُ لم يكن يُخالَف،
 *   كان **غيرَ قابلٍ للقياس**. وذاك أسوأُ من المخالفة.
 *
 * ◆ **ولا يُكتب مستندٌ من عندي**: مصدرانِ حاكمانِ لا ثالثَ لهما —
 *   ① **دفترُ التدقيقِ الشامل** (العمودُ «المستندُ الرسميّ») لما ورد فيه.
 *   ② **حالاتُ الشيفرةِ الصريحةُ وأحداثُ الأعمالِ المنشورة** — تُقرأ من
 *      الملفِّ ومُضمَّناتِه المباشرة، فتكون «وسمَ انتقالِ حالةٍ صريحًا» بنصِّ البند.
 *   وما لم يُوجد له مصدرٌ **يبقى NULL ويُعلَن ناقصًا** — لا يُملأ بجملةٍ عامة.
 *
 * ◆ فالبندُ يصير **مقيسًا**، والنقصُ الباقي يظهر رقمًا لا يختفي في صمت.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$has = function ($c) use ($conn) {
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                         AND TABLE_NAME='nav_canonical' AND COLUMN_NAME='{$c}'");
    return $r && $r->num_rows > 0;
};
if (!$has('output_doc')) {
    $conn->query("ALTER TABLE nav_canonical
        ADD `output_doc` VARCHAR(190) DEFAULT NULL
            COMMENT 'المستندُ الناتجُ — من دفترِ التدقيقِ حصرًا، ولا يُكتب تخمينًا',
        ADD `state_transition` VARCHAR(190) DEFAULT NULL
            COMMENT 'وسمُ انتقالِ الحالةِ الصريحُ — مقروءٌ من الشيفرةِ لا مؤلَّف',
        ADD `ops_source` VARCHAR(190) DEFAULT NULL
            COMMENT 'من أين جاء أيٌّ منهما — فلا رقمَ بلا مصدر'");
}

/* ── ① ما ورد في دفترِ التدقيقِ الشامل — يُكتب حرفًا ─────────────────────── */
$LEDGER = array(
    'Financing/fin_changes.php'        => 'عقد تمويل موقَّع',
    'Finance/budget_dept.php'          => 'تقرير إقفال يومي',
    'Portal/dept_achievement.php'      => '— قراءةٌ عليا',
);

/* ── ② وسمُ انتقالِ الحالةِ — يُقرأ من الملفِّ ومُضمَّناتِه المباشرة ─────────── */
function ems_scan_states($ROOT, $route)
{
    $files = array($ROOT . '/' . $route);
    $src = @file_get_contents($ROOT . '/' . $route);
    if ($src !== false) {
        /* المُضمَّناتُ المباشرةُ وحدَها — لا تتبُّعَ شجرةٍ كاملةٍ فيتضخّم الادّعاء */
        if (preg_match_all('~(?:include|require)(?:_once)?\s*\(?\s*[^;]*?[\'"]([^\'"]+\.php)[\'"]~', $src, $m)) {
            foreach ($m[1] as $inc) {
                $p = $inc;
                $p = str_replace('__DIR__ . ', '', $p);
                $cand = dirname($ROOT . '/' . $route) . '/' . ltrim($p, '/');
                $real = realpath($cand);
                if ($real && strpos($real, realpath($ROOT)) === 0) { $files[] = $real; }
            }
        }
    }
    $states = array(); $events = 0;
    foreach (array_unique($files) as $f) {
        $s = @file_get_contents($f);
        if ($s === false) { continue; }
        if (preg_match_all('~(?:status|state|op_state|ops_state)\s*=\s*[\'"]([a-z_]{3,30})[\'"]~', $s, $mm)) {
            foreach ($mm[1] as $v) { $states[$v] = true; }
        }
        $events += preg_match_all('~publishFact|EventPublisher~', $s);
    }
    return array(array_keys($states), $events);
}

$upd = $conn->prepare("UPDATE nav_canonical SET output_doc = ?, state_transition = ?, ops_source = ?
                        WHERE route = ?");
$ops = array();
$r = $conn->query("SELECT route FROM nav_canonical
                    WHERE group_name LIKE '%عمليات%' OR group_name LIKE '%العمليات%'");
while ($r && ($x = $r->fetch_assoc())) { $ops[] = $x['route']; }

$byLedger = 0; $byCode = 0; $none = array();
foreach ($ops as $route) {
    $doc = isset($LEDGER[$route]) ? $LEDGER[$route] : null;
    list($states, $events) = ems_scan_states($ROOT, $route);
    $trans = null;
    if ($states) {
        sort($states);
        $trans = implode(' ← ', array_slice($states, 0, 6));
        if (count($states) > 6) { $trans .= ' …'; }
    }
    if ($doc !== null && $trans !== null) {
        $src = 'دفترُ التدقيقِ الشامل (المستند) + الشيفرةُ الحيّة (' . count($states) . ' حالةً · ' . $events . ' حدثًا)';
        $byLedger++;
    } elseif ($doc !== null) {
        $src = 'دفترُ التدقيقِ الشامل — العمودُ «المستندُ الرسميّ»';
        $byLedger++;
    } elseif ($trans !== null) {
        $src = 'الشيفرةُ الحيّة — ' . count($states) . ' حالةً صريحةً و' . $events . ' نداءَ نشرِ حدث';
        $byCode++;
    } else {
        $none[] = $route;
        $src = null;
    }
    if ($src !== null) { $upd->bind_param('ssss', $doc, $trans, $src, $route); $upd->execute(); }
}

echo "════ البندُ ⑧ — المستندُ الناتجُ أو وسمُ الانتقال ════\n";
echo "  شاشاتُ مجموعاتِ العمليات: " . count($ops) . "\n";
echo "  من دفترِ التدقيق: {$byLedger} · من وسمِ حالةٍ صريحٍ في الشيفرة: {$byCode}\n";
echo "  **بلا مصدرٍ — تبقى NULL ولا تُملأ تخمينًا**: " . count($none) . "\n";
foreach ($none as $n) { echo "    · {$n}\n"; }
echo ($none ? "◆ النقصُ معلَنٌ رقمًا — والبندُ صار مقيسًا بعد أن كان بلا عمود\n"
            : "✔ صفرُ شاشةٍ بلا مستندٍ ناتجٍ ولا وسمِ انتقال\n");
