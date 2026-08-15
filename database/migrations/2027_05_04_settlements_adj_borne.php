<?php
/**
 * 2027_05_04_settlements_adj_borne.php
 * ═══════════════════════════════════════════════════════════════════════════
 * التسوياتُ الأربعُ والمتحمَّلُ من الخزينة — F-07 وF-08 قادحَين لا إدخالًا
 *
 * الحكم (TS-01):
 *   F-07: supplier_executed_hours = client_executed_hours + adj_work_added
 *         + adj_breakdown_added + adj_standby_added − adj_deducted
 *         «عمودٌ محسوبٌ بقادحٍ — ولا يُدخَل»
 *   F-08: borne_by_treasury = GREATEST(supplier_executed − client_settled, 0)
 *         «وهو مقياسُ الخسارةِ التشغيليةِ المباشرة»
 *   CK-06 الأصلي: لا تسويةَ بتعديلٍ ≠ 0 بلا مستندٍ (chk_adj_doc **في القاعدة**).
 *
 * ◆ النظيرُ الحيُّ `settlements` (لا sup_settlements) — تُضاف الأعمدةُ التسعةُ
 *   إليه، والقادحان يحسبان المشتقَّين **ويدوسان أيَّ قيمةٍ أُدخلت** — فالشاشةُ
 *   لا تُصدَّق على محسوب.
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };
$hasCol = function (string $c) use ($conn): bool {
    $r = $conn->query("SHOW COLUMNS FROM settlements LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};

echo "══ التسوياتُ الأربعُ والمتحمَّل ══\n\n";

$COLS = array(
    array('client_executed_hours',   "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'منفَّذةُ العميلِ المعتمدة — مدخلُ F-07'"),
    array('adj_work_added',          "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'تسوية①: عملٌ مضاف'"),
    array('adj_breakdown_added',     "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'تسوية②: تعطلٌ مضاف'"),
    array('adj_standby_added',       "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'تسوية③: استعدادٌ مضاف'"),
    array('adj_deducted',            "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'تسوية④: خصم'"),
    array('adj_doc_ref',             "VARCHAR(190) NULL COMMENT 'مستندُ التسوية — إلزاميٌّ متى كانت Σ التسويات ≠ 0 (CK-06)'"),
    array('supplier_executed_hours', "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'F-07 محسوبٌ بقادحٍ — لا يُدخَل'"),
    array('client_settled_hours',    "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'المحصَّلةُ من العميل — مدخلُ F-08'"),
    array('borne_by_treasury',       "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'F-08 المتحمَّلُ من الخزينة — مقياسُ الخسارةِ المباشرة، محسوبٌ بقادح'"),
);
$added = 0;
foreach ($COLS as [$c, $def]) {
    if ($hasCol($c)) { echo "  · $c قائم\n"; continue; }
    if (!$conn->query("ALTER TABLE settlements ADD COLUMN `$c` $def")) { exit("  ✘ $c: {$conn->error}\n"); }
    $added++;
}
echo "  ✔ أعمدةٌ أُضيفت: $added\n";

/* CHECK chk_adj_doc — نفسُ صفِّه فيصلح CHECK حقيقيًّا */
$has = $one("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='settlements'
               AND CONSTRAINT_NAME='chk_settle_adj_doc'");
if (!(int) $has) {
    if (!$conn->query("ALTER TABLE settlements ADD CONSTRAINT chk_settle_adj_doc
                       CHECK ((adj_work_added + adj_breakdown_added + adj_standby_added + adj_deducted) = 0
                              OR (adj_doc_ref IS NOT NULL AND adj_doc_ref <> ''))")) {
        exit("  ✘ CHECK: {$conn->error}\n");
    }
    echo "  ✔ chk_settle_adj_doc — لا تسويةَ بلا مستندٍ (في القاعدة)\n";
}

/* القادحان F-07/F-08 */
$conn->query("DROP TRIGGER IF EXISTS trg_settle_f0708_ins");
$conn->query("DROP TRIGGER IF EXISTS trg_settle_f0708_upd");
$B = "SET NEW.supplier_executed_hours = NEW.client_executed_hours + NEW.adj_work_added
        + NEW.adj_breakdown_added + NEW.adj_standby_added - NEW.adj_deducted;
      SET NEW.borne_by_treasury = GREATEST(NEW.supplier_executed_hours - NEW.client_settled_hours, 0);";
if (!$conn->query("CREATE TRIGGER trg_settle_f0708_ins BEFORE INSERT ON settlements FOR EACH ROW BEGIN $B END")
 || !$conn->query("CREATE TRIGGER trg_settle_f0708_upd BEFORE UPDATE ON settlements FOR EACH ROW BEGIN $B END")) {
    exit("  ✘ القادحان: {$conn->error}\n");
}
echo "  ✔ قادحا F-07/F-08\n";

/* الإثبات: ① تسويةٌ بلا مستندٍ تُرفض · ② المحسوبُ يدوس المُدخَل */
$sid = (int) $one("SELECT id FROM settlements WHERE COALESCE(is_deleted,0)=0 LIMIT 1");
$st = $conn->prepare("UPDATE settlements SET adj_deducted=5 WHERE id=?");
$st->bind_param('i', $sid); $ok1 = !$st->execute(); $e1 = $st->errno; $st->close();
echo '  ① تسويةٌ بلا مستند: ' . ($ok1 ? "✔ رُفضت ($e1)" : '✘ مرّت!') . "\n";

$st = $conn->prepare("UPDATE settlements
                      SET client_executed_hours=100, adj_work_added=10, adj_deducted=5,
                          adj_doc_ref='DOC-F0708-TEST', client_settled_hours=90,
                          supplier_executed_hours=999999, borne_by_treasury=999999
                      WHERE id=?");
$st->bind_param('i', $sid); $ok2 = $st->execute(); $st->close();
$row = $conn->query("SELECT supplier_executed_hours, borne_by_treasury FROM settlements WHERE id=$sid")->fetch_assoc();
$f07 = (float) $row['supplier_executed_hours'] === 105.0;   // 100+10-5
$f08 = (float) $row['borne_by_treasury'] === 15.0;          // max(105-90,0)
echo '  ② F-07: ' . ($f07 ? '✔ 105.00 (داس 999999)' : '✘ ' . $row['supplier_executed_hours']) . "\n";
echo '  ③ F-08: ' . ($f08 ? '✔ 15.00 (داس 999999)' : '✘ ' . $row['borne_by_treasury']) . "\n";
/* إرجاعُ صفِّ الاختبارِ لحالِه */
$conn->query("UPDATE settlements SET client_executed_hours=0, adj_work_added=0, adj_deducted=0,
              adj_doc_ref=NULL, client_settled_hours=0 WHERE id=$sid");

echo (($ok1 && $ok2 && $f07 && $f08) ? "\n✔ تمّت\n" : "\n✘ إخفاق\n");
if (!($ok1 && $ok2 && $f07 && $f08)) { exit(1); }
