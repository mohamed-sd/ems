<?php
/**
 * 2027_04_29_op_containers_rounding_residue.php
 * ═══════════════════════════════════════════════════════════════════════════
 * امتصاصُ بقيةِ التقريب — آخرُ ثمانيةٍ فوارقُها قروش
 *
 * بعد الإنزالِ المتتالي بقيت 8 حاوياتٍ فوارقُها بين 0.01 و0.05 — وهي **بقيةُ
 * تقريبٍ لا خللُ بيانات**: ضربُ سعةِ كلِّ ابنٍ في معاملٍ ثم `ROUND(_,2)` يجمع
 * كسرًا زائدًا على الأب. وإعادةُ القسمةِ لا تُنهيها لأنها تولّدها من جديد.
 *
 * فالعلاجُ **امتصاصٌ لا قسمة**: يُطرح الفارقُ بتمامِه من **أكبرِ ابن** — فيبقى
 * توزيعُ الباقين كما هو ولا يتغيّر إلا صفٌّ واحدٌ بمقدارِ قروش.
 *
 * ◆ ويُسقط الحارسُ ثم يُعاد — للسببِ نفسِه (فحصٌ صفًّا صفًّا لا يرى نيّةَ الجملة).
 * ◆ ويُثبَت في النهايةِ صفرُ مخالفٍ **والحارسُ قائمٌ يرفض**.
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

$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };
$violSql = "SELECT p.id, p.cap_qty, SUM(c.cap_qty) kids, SUM(c.cap_qty)-p.cap_qty diff
            FROM op_containers p JOIN op_containers c ON c.parent_id=p.id AND c.is_deleted=0
            WHERE p.is_deleted=0 GROUP BY p.id, p.cap_qty
            HAVING SUM(c.cap_qty) > p.cap_qty + 0.005";
$viol = function () use ($conn, $violSql) {
    $r = $conn->query("SELECT COUNT(*) FROM ($violSql) d");
    return $r ? (int) $r->fetch_row()[0] : -1;
};
$GUARD = "
    IF NEW.parent_id IS NOT NULL AND COALESCE(NEW.is_deleted,0) = 0 THEN
        SET @cap_parent = (SELECT p.cap_qty FROM op_containers p WHERE p.id = NEW.parent_id);
        SET @cap_sibs = (SELECT COALESCE(SUM(s.cap_qty),0) FROM op_containers s
                          WHERE s.parent_id = NEW.parent_id AND s.is_deleted = 0 AND s.id <> COALESCE(NEW.id,0));
        IF @cap_parent IS NOT NULL AND (@cap_sibs + NEW.cap_qty) > @cap_parent + 0.005 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'سعةُ الحاوية: مجموعُ الأبناءِ يتجاوز سعةَ الأب — الأعلى مشتقٌّ من الأدنى ولا يُتجاوز';
        END IF;
    END IF;";

echo "══ امتصاصُ بقيةِ التقريب ══\n\n";
$start = $viol();
echo "  المخالفُ عند البدء: $start\n";
if ($start === 0) { echo "\n✔ لا بقيةَ — تمّت\n"; exit(0); }

$conn->query("DROP TRIGGER IF EXISTS trg_opc_cap_ins");
$conn->query("DROP TRIGGER IF EXISTS trg_opc_cap_upd");

$pass = 0; $absorbed = 0;
while ($pass < 6) {
    $pass++;
    $rows = array();
    $rs = $conn->query($violSql);
    while ($rs && ($x = $rs->fetch_assoc())) { $rows[] = $x; }
    if (!$rows) { break; }
    foreach ($rows as $v) {
        $pid = (int) $v['id'];
        $diff = round((float) $v['diff'], 2);
        if ($diff <= 0) { continue; }
        /* أكبرُ ابنٍ يحتمل الطرحَ بلا أن يصير سالبًا */
        $b = $conn->query("SELECT id, cap_qty, allocated_qty FROM op_containers
                           WHERE parent_id=$pid AND is_deleted=0 AND cap_qty >= $diff
                           ORDER BY cap_qty DESC LIMIT 1");
        if (!$b || !($kid = $b->fetch_assoc())) { continue; }
        $newCap = round((float) $kid['cap_qty'] - $diff, 2);
        $newAlloc = min((float) $kid['allocated_qty'], $newCap);
        $st = $conn->prepare("UPDATE op_containers SET cap_qty=?, allocated_qty=? WHERE id=?");
        $st->bind_param('ddi', $newCap, $newAlloc, $kid['id']);
        if ($st->execute()) { $absorbed++; }
        $st->close();
    }
    $now = $viol();
    printf("  لفّة %d: بقي %d\n", $pass, $now);
    if ($now === 0) { break; }
}

$conn->query("DROP TRIGGER IF EXISTS trg_opc_cap_ins");
$conn->query("DROP TRIGGER IF EXISTS trg_opc_cap_upd");
$ok1 = $conn->query("CREATE TRIGGER trg_opc_cap_ins BEFORE INSERT ON op_containers FOR EACH ROW BEGIN $GUARD END");
$ok2 = $conn->query("CREATE TRIGGER trg_opc_cap_upd BEFORE UPDATE ON op_containers FOR EACH ROW BEGIN $GUARD END");
if (!$ok1 || !$ok2) { exit("  ✘ تعذّرت إعادةُ الحارسَين: {$conn->error}\n"); }
echo "\n  ✔ أُعيد الحارسان\n";

/* اختبارٌ سلبيٌّ نهائيّ */
$t = $conn->query("SELECT c.id, c.cap_qty, p.cap_qty pc FROM op_containers c
                   JOIN op_containers p ON p.id=c.parent_id WHERE c.is_deleted=0 LIMIT 1");
$blocked = false;
if ($t && ($row = $t->fetch_assoc())) {
    $big = (float) $row['pc'] * 10 + 1000;
    $st = $conn->prepare("UPDATE op_containers SET cap_qty=? WHERE id=?");
    $st->bind_param('di', $big, $row['id']);
    $blocked = !$st->execute();
    $st->close();
}
echo '  ' . ($blocked ? '✔' : '✘') . ' الحارسُ يرفض تجاوزَ سعةِ الأب' . "\n";

$end = $viol();
echo "\n── الحصيلة ──\n";
printf("  مخالفاتُ الهرم: %d ⇐ %d · صفوفٌ امتصّت البقية: %d\n", $start, $end, $absorbed);
printf("  إسنادٌ يتجاوز سعتَه: %s · الحاوياتُ %s صفًّا (صفرُ حذف)\n",
    $one("SELECT COUNT(*) FROM op_containers WHERE is_deleted=0 AND allocated_qty>cap_qty+0.005"),
    number_format((int) $one("SELECT COUNT(*) FROM op_containers")));
echo "\n" . ($end === 0 && $blocked ? "✔ تمّت — الهرمُ متّسقٌ والحارسُ يرفض\n" : "⚠ بقي $end مخالفًا\n");
