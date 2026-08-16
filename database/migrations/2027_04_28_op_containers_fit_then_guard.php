<?php
/**
 * 2027_04_28_op_containers_fit_then_guard.php
 * ═══════════════════════════════════════════════════════════════════════════
 * الحارسُ يمنع تصحيحَه — فيُسقط ويُصحَّح ثم يُعاد
 *
 * ── ما وقع ────────────────────────────────────────────────────────────
 * 2027_04_26 ركّب القادحَين ثم حاول التصحيح، و2027_04_27 كرّر المحاولةَ —
 * وكلتاهما رجعت بـ`affected_rows = -1` أي **فشلُ الجملة**. والسبب أن القادحَ
 * يفحص **صفًّا صفًّا**: عند إنزالِ أولِ ابنٍ في جملةٍ تمسُّ كلَّ الإخوة، يرى
 * القادحُ إخوةً لم يُنزلوا بعد فيجد المجموعَ ما زال متجاوزًا فيرفض.
 *
 * فحارسٌ صحيحٌ للتغييرِ التدريجيِّ يمنع التصحيحَ الجماعيَّ بطبيعته. والحلُّ
 * ليس إضعافَه بل **ترتيبَ العمل**: يُسقط · يُصحَّح · يُعاد · ثم يُثبَت أنه
 * يرفض المخالفَ فعلًا (اختبارٌ سلبيٌّ في نهايةِ الملف).
 *
 * ◆ ولا يُترك الحارسُ ساقطًا في أيِّ حال: يُعاد قبلَ الخروجِ ولو فشل التصحيح.
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
$violSql = "SELECT p.id, p.cap_qty, SUM(c.cap_qty) kids
            FROM op_containers p JOIN op_containers c ON c.parent_id = p.id AND c.is_deleted = 0
            WHERE p.is_deleted = 0 GROUP BY p.id, p.cap_qty
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
$addGuards = function () use ($conn, $GUARD) {
    $conn->query("DROP TRIGGER IF EXISTS trg_opc_cap_ins");
    $conn->query("DROP TRIGGER IF EXISTS trg_opc_cap_upd");
    $ok1 = $conn->query("CREATE TRIGGER trg_opc_cap_ins BEFORE INSERT ON op_containers FOR EACH ROW BEGIN $GUARD END");
    $ok2 = $conn->query("CREATE TRIGGER trg_opc_cap_upd BEFORE UPDATE ON op_containers FOR EACH ROW BEGIN $GUARD END");
    return $ok1 && $ok2;
};

echo "══ إسقاطُ الحارسِ ثم التصحيحُ ثم إعادتُه ══\n\n";
$start = $viol();
echo "  المخالفُ عند البدء: $start\n";

$conn->query("DROP TRIGGER IF EXISTS trg_opc_cap_ins");
$conn->query("DROP TRIGGER IF EXISTS trg_opc_cap_upd");
echo "  · أُسقط الحارسان مؤقتًا\n\n";

/* المستوياتُ من الأعلى — تُقرأ من البنية */
$levels = array('رئيسية');
$r = $conn->query("SELECT DISTINCT c.level FROM op_containers c JOIN op_containers p ON p.id=c.parent_id WHERE c.is_deleted=0");
while ($x = $r->fetch_row()) { if (!in_array($x[0], $levels, true)) { $levels[] = $x[0]; } }

$pass = 0; $touched = 0; $failedStmts = 0;
while ($pass < 10) {
    $pass++;
    $before = $viol();
    if ($before === 0) { break; }
    foreach ($levels as $lvl) {
        $rows = array();
        $q = $conn->prepare("SELECT p.id, p.cap_qty, SUM(c.cap_qty) kids
                             FROM op_containers p JOIN op_containers c ON c.parent_id=p.id AND c.is_deleted=0
                             WHERE p.is_deleted=0 AND p.level=?
                             GROUP BY p.id, p.cap_qty HAVING SUM(c.cap_qty) > p.cap_qty + 0.005");
        $q->bind_param('s', $lvl); $q->execute();
        $res = $q->get_result();
        while ($x = $res->fetch_assoc()) { $rows[] = $x; }
        $q->close();
        foreach ($rows as $v) {
            $f = (float) $v['cap_qty'] / max(0.0001, (float) $v['kids']);
            $pid = (int) $v['id'];
            $st = $conn->prepare("UPDATE op_containers
                                  SET allocated_qty = LEAST(ROUND(allocated_qty * ?, 2), ROUND(cap_qty * ?, 2)),
                                      cap_qty       = ROUND(cap_qty * ?, 2)
                                  WHERE parent_id = ? AND is_deleted = 0");
            $st->bind_param('dddi', $f, $f, $f, $pid);
            if (!$st->execute()) { $failedStmts++; }
            else { $touched += max(0, $st->affected_rows); }
            $st->close();
        }
    }
    $after = $viol();
    printf("  لفّة %d: %d ⇐ %d\n", $pass, $before, $after);
    if ($after === 0 || $after >= $before) { break; }
}

$conn->query("UPDATE op_containers SET allocated_qty = cap_qty
              WHERE is_deleted=0 AND allocated_qty > cap_qty + 0.005");

$end = $viol();
echo "\n  · إعادةُ الحارسَين\n";
if (!$addGuards()) { exit("  ✘ تعذّرت إعادةُ الحارسَين: {$conn->error}\n"); }
echo "  ✔ أُعيدا\n";

/* ── اختبارٌ سلبيّ: أيرفض الحارسُ المخالفَ فعلًا؟ ─────────────── */
echo "\n── اختبارٌ سلبيّ ──\n";
$t = $conn->query("SELECT c.id, c.cap_qty, p.cap_qty pc FROM op_containers c
                   JOIN op_containers p ON p.id=c.parent_id
                   WHERE c.is_deleted=0 LIMIT 1");
if ($t && ($row = $t->fetch_assoc())) {
    $big = (float) $row['pc'] * 10 + 1000;
    $st = $conn->prepare("UPDATE op_containers SET cap_qty=? WHERE id=?");
    $st->bind_param('di', $big, $row['id']);
    $blocked = !$st->execute();
    $errno = $st->errno;
    $st->close();
    echo '  ' . ($blocked ? '✔' : '✘') . ' محاولةُ تجاوزِ سعةِ الأبِ ' . ($blocked ? "رُفضت (رمز $errno)" : 'مرّت — الحارسُ لا يعمل!') . "\n";
    if (!$blocked) { $conn->query("UPDATE op_containers SET cap_qty=" . (float) $row['cap_qty'] . " WHERE id=" . (int) $row['id']); }
}

echo "\n── الحصيلة ──\n";
printf("  مخالفاتُ الهرم: %d ⇐ %d في %d لفّة · جملٌ فاشلة %d\n", $start, $end, $pass, $failedStmts);
printf("  صفوفٌ مسَّها الإنزال: %d · الحاوياتُ %s صفًّا (صفرُ حذف)\n",
    $touched, number_format((int) $one("SELECT COUNT(*) FROM op_containers")));
printf("  إسنادٌ يتجاوز سعتَه: %s · قوادحُ القاعدة: %s\n",
    $one("SELECT COUNT(*) FROM op_containers WHERE is_deleted=0 AND allocated_qty>cap_qty+0.005"),
    $one("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()"));
echo "\n" . ($end === 0 ? "✔ تمّت — الهرمُ متّسقٌ والحارسُ قائم\n" : "⚠ بقي $end مخالفًا\n");
