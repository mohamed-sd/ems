<?php
/**
 * 2027_04_27_op_containers_cascade_fit.php
 * ═══════════════════════════════════════════════════════════════════════════
 * إتمامُ التصحيح: الإنزالُ يتتالى بالمستوياتِ لا بمرورٍ واحد
 *
 * ── الخطأُ في 2027_04_26 ──────────────────────────────────────────────
 * أنزلتُ أبناءَ كلِّ أبٍ مخالفٍ في مرورٍ واحد، فصار المخالفُ **20 ⇐ 30** لا صفرًا.
 * والسبب: الهرمُ **أربعةُ مستويات** (رئيسية ← مورد ← معدة ← مشغّل). فإنزالُ
 * حاوياتِ المورِّدين جعل أبناءَها من المعداتِ يتجاوزونها — عيبٌ وُلد من التصحيحِ
 * نفسِه لا من البيانات. والإنزالُ يجب أن **يتتالى من الأعلى إلى الأدنى**.
 *
 * ◆ ولا يُخفى: المرورُ الواحدُ أعطى رقمًا أسوأَ وأُعلن كما هو — ثم صُحِّح.
 * ◆ والقادحانِ لا يمنعان هذا التصحيح: الإنزالُ يُنقص المجموعَ فيمرُّ دائمًا.
 * ◆ ويتكرّر المرورُ حتى يستقرَّ العددُ عند صفرٍ أو يبلغَ سقفَ اللفّات.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ إنزالٌ متتالٍ بالمستويات ══\n\n";
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };

$violSql = "SELECT p.id, p.cap_qty, SUM(c.cap_qty) kids
            FROM op_containers p JOIN op_containers c ON c.parent_id = p.id AND c.is_deleted = 0
            WHERE p.is_deleted = 0
            GROUP BY p.id, p.cap_qty
            HAVING SUM(c.cap_qty) > p.cap_qty + 0.005";
$countViol = function () use ($conn, $violSql) {
    $r = $conn->query("SELECT COUNT(*) FROM ($violSql) d");
    return $r ? (int) $r->fetch_row()[0] : -1;
};

/* ترتيبُ المستوياتِ من الأعلى — يُقرأ من البنيةِ لا يُفترض */
$levels = array();
$r = $conn->query("SELECT DISTINCT level FROM op_containers WHERE is_deleted=0 AND parent_id IS NULL");
while ($x = $r->fetch_row()) { $levels[] = $x[0]; }
$r = $conn->query("SELECT DISTINCT c.level FROM op_containers c
                   JOIN op_containers p ON p.id=c.parent_id
                   WHERE c.is_deleted=0 ORDER BY 1");
while ($x = $r->fetch_row()) { if (!in_array($x[0], $levels, true)) { $levels[] = $x[0]; } }
echo '  مستوياتُ الهرم: ' . implode(' ← ', $levels) . "\n";

$start = $countViol();
echo "  المخالفُ عند البدء: $start\n\n";

$pass = 0; $totalTouched = 0;
while ($pass < 8) {
    $pass++;
    $before = $countViol();
    if ($before === 0) { break; }

    /* في كلِّ لفّةٍ: عالِجِ المخالفينَ **بترتيبِ المستوى** من الأعلى */
    $touched = 0;
    foreach ($levels as $lvl) {
        $rows = array();
        $q = $conn->prepare("SELECT p.id, p.cap_qty, SUM(c.cap_qty) kids
                             FROM op_containers p JOIN op_containers c ON c.parent_id=p.id AND c.is_deleted=0
                             WHERE p.is_deleted=0 AND p.level = ?
                             GROUP BY p.id, p.cap_qty
                             HAVING SUM(c.cap_qty) > p.cap_qty + 0.005");
        $q->bind_param('s', $lvl);
        $q->execute();
        $res = $q->get_result();
        while ($x = $res->fetch_assoc()) { $rows[] = $x; }
        $q->close();

        foreach ($rows as $v) {
            $pid = (int) $v['id'];
            $kids = (float) $v['kids'];
            if ($kids <= 0) { continue; }
            $f = (float) $v['cap_qty'] / $kids;
            /* الترتيبُ مقصود: allocated يُحسب من cap **قبلَ** تعديلِه في هذه
               الجملةِ نفسِها، فيُمرَّر المعاملُ صراحةً بدل الاعتمادِ على ترتيبِ
               التقييمِ داخلَ SET. */
            $st = $conn->prepare("UPDATE op_containers
                                  SET allocated_qty = LEAST(ROUND(allocated_qty * ?, 2), ROUND(cap_qty * ?, 2)),
                                      cap_qty       = ROUND(cap_qty * ?, 2)
                                  WHERE parent_id = ? AND is_deleted = 0");
            $st->bind_param('dddi', $f, $f, $f, $pid);
            $st->execute();
            $touched += $st->affected_rows;
            $st->close();
        }
    }
    $after = $countViol();
    printf("  لفّة %d: مخالف %d ⇐ %d · صفوفٌ مسَّها الإنزال %d\n", $pass, $before, $after, $touched);
    $totalTouched += $touched;
    if ($after === 0 || $after >= $before) { break; }
}

$end = $countViol();
$allocBad = (int) $one("SELECT COUNT(*) FROM op_containers WHERE is_deleted=0 AND allocated_qty > cap_qty + 0.005");
if ($allocBad > 0) {
    $conn->query("UPDATE op_containers SET allocated_qty = cap_qty
                  WHERE is_deleted=0 AND allocated_qty > cap_qty + 0.005");
    echo "  ✔ قُصَّ $allocBad صفًّا مُسنَدُه يتجاوز سعتَه\n";
}

echo "\n── الحصيلة ──\n";
printf("  مخالفاتُ الهرم: %d ⇐ %d في %d لفّة\n", $start, $end, $pass);
printf("  صفوفٌ مسَّها الإنزال: %d من %s\n", $totalTouched,
    number_format((int) $one("SELECT COUNT(*) FROM op_containers WHERE is_deleted=0")));
printf("  إسنادٌ يتجاوز سعتَه: %s\n", $one("SELECT COUNT(*) FROM op_containers WHERE is_deleted=0 AND allocated_qty>cap_qty+0.005"));
printf("  الحاوياتُ كما هي: %s صفًّا (صفرُ حذف)\n", number_format((int) $one("SELECT COUNT(*) FROM op_containers")));
echo "\n" . ($end === 0 ? "✔ تمّت — الهرمُ متّسق\n" : "⚠ تمّت وبقي $end مخالفًا — يلزم فحصٌ يدويّ\n");
