<?php
/**
 * 2027_04_26_op_containers_capacity_guard.php
 * ═══════════════════════════════════════════════════════════════════════════
 * «السعاتُ تُفرض بقوادحَ لا تُقبل من الشاشة — فالرقمُ الأعلى مشتقٌّ من الأدنى
 *  بنيويًّا» (المواصفة 70 · ٢-٢). والقياس: **صفرُ قادحٍ على `op_containers`**،
 *  ومن ثَمَّ 20 حاويةً أبناؤها يتجاوزون سعتَها و7 إسناداتٍ تتجاوز الحاوية.
 *
 * ── الأبُ هو الحكم ───────────────────────────────────────────────────────
 * الحاوياتُ الرئيسيةُ الـ123 كلُّها `origin='عقد'` — سعتُها من بندِ العقدِ فهي
 * المرجع. والأبناءُ (مورد 40 · معدة 106) بُذروا بسعاتٍ تتجاوزها. فالتصحيحُ
 * **يُنزل الأبناءَ إلى ما يسعهم الأبُ** ولا يرفع سقفَ العقد.
 *
 * ── والتصحيحُ نسبيٌّ لا اعتباطيّ ────────────────────────────────────────
 * لكلِّ أبٍ مخالفٍ: معاملٌ = سعةُ الأب ÷ مجموعِ أبنائه، يُضرب في سعةِ كلِّ ابنٍ
 * وفي المُسنَدِ منه. فتُحفظ **نسبُ التوزيعِ بين الموردين** كما بُذرت، ويُصحَّح
 * المجموعُ وحدَه. والبديلُ (قصُّ الأخيرِ أو التسويةُ بالتساوي) يغيّر حصصًا
 * نسبيةً لم يُطلب تغييرُها.
 *
 * ── ثم القادحُ يمنع التكرار ─────────────────────────────────────────────
 * قادحانِ (INSERT · UPDATE) يرفضان أيَّ ابنٍ يجعل مجموعَ إخوتِه يتجاوز الأب.
 * ◆ ويُسقطان أولًا إن وُجدا — فاستيرادٌ مستأنَفٌ على قادحٍ قائمٍ يرمي 1359.
 * ◆ والرسالةُ عربيةٌ فيلزم عميلُ utf8mb4 (مضبوطٌ أدناه) وإلا تشوّهت.
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

echo "══ حارسُ سعةِ الحاويات ══\n\n";
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };

$violSql = "SELECT p.id, p.container_no, p.cap_qty, SUM(c.cap_qty) kids
            FROM op_containers p JOIN op_containers c ON c.parent_id = p.id AND c.is_deleted = 0
            WHERE p.is_deleted = 0
            GROUP BY p.id, p.container_no, p.cap_qty
            HAVING SUM(c.cap_qty) > p.cap_qty + 0.005";

$before = (int) $one("SELECT COUNT(*) FROM ($violSql) d");
echo "  حاوياتٌ أبناؤها يتجاوزونها قبلَ التصحيح: $before\n";

/* ── ① التصحيحُ النسبيّ ─────────────────────────────────────────── */
$fixed = 0; $touched = 0;
$rs = $conn->query($violSql);
$rows = array();
while ($rs && ($x = $rs->fetch_assoc())) { $rows[] = $x; }
foreach ($rows as $v) {
    $pid = (int) $v['id'];
    $cap = (float) $v['cap_qty'];
    $kids = (float) $v['kids'];
    if ($kids <= 0) { continue; }
    $f = $cap / $kids;                       // معاملُ الإنزالِ النسبيّ
    $st = $conn->prepare("UPDATE op_containers
                          SET cap_qty = ROUND(cap_qty * ?, 2),
                              allocated_qty = LEAST(ROUND(allocated_qty * ?, 2), ROUND(cap_qty * ?, 2))
                          WHERE parent_id = ? AND is_deleted = 0");
    $st->bind_param('dddi', $f, $f, $f, $pid);
    $st->execute();
    $touched += $st->affected_rows;
    $st->close();
    $fixed++;
}
echo "  ✔ صُحِّح $fixed أبًا · وأُنزل $touched ابنًا نسبيًّا (نسبُ التوزيعِ محفوظة)\n";

$after = (int) $one("SELECT COUNT(*) FROM ($violSql) d");
echo "  المخالفُ بعدَ التصحيح: $after\n";

/* الإسنادُ أيضًا: allocated لا يتجاوز cap في الصفِّ نفسِه */
$bad = (int) $one("SELECT COUNT(*) FROM op_containers WHERE is_deleted=0 AND allocated_qty > cap_qty + 0.005");
if ($bad > 0) {
    $conn->query("UPDATE op_containers SET allocated_qty = cap_qty
                  WHERE is_deleted=0 AND allocated_qty > cap_qty + 0.005");
    echo "  ✔ قُصَّ $bad صفًّا مُسنَدُه يتجاوز سعتَه\n";
}

/* ── ② القادحان ─────────────────────────────────────────────────── */
echo "\n── القادحان ──\n";
$conn->query("DROP TRIGGER IF EXISTS trg_opc_cap_ins");
$conn->query("DROP TRIGGER IF EXISTS trg_opc_cap_upd");

$body = "
    IF NEW.parent_id IS NOT NULL AND COALESCE(NEW.is_deleted,0) = 0 THEN
        SET @cap_parent = (SELECT p.cap_qty FROM op_containers p WHERE p.id = NEW.parent_id);
        SET @cap_sibs = (SELECT COALESCE(SUM(s.cap_qty),0) FROM op_containers s
                          WHERE s.parent_id = NEW.parent_id AND s.is_deleted = 0 AND s.id <> COALESCE(NEW.id,0));
        IF @cap_parent IS NOT NULL AND (@cap_sibs + NEW.cap_qty) > @cap_parent + 0.005 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'سعةُ الحاوية: مجموعُ الأبناءِ يتجاوز سعةَ الأب — الأعلى مشتقٌّ من الأدنى ولا يُتجاوز';
        END IF;
    END IF;";

if (!$conn->query("CREATE TRIGGER trg_opc_cap_ins BEFORE INSERT ON op_containers FOR EACH ROW BEGIN $body END")) {
    exit("  ✘ تعذّر قادحُ الإدراج: {$conn->error}\n");
}
echo "  ✔ trg_opc_cap_ins\n";
if (!$conn->query("CREATE TRIGGER trg_opc_cap_upd BEFORE UPDATE ON op_containers FOR EACH ROW BEGIN $body END")) {
    exit("  ✘ تعذّر قادحُ التحديث: {$conn->error}\n");
}
echo "  ✔ trg_opc_cap_upd\n";

echo "\n── الحصيلة ──\n";
printf("  مخالفاتُ الهرم: %d ⇐ %d\n", $before, $after);
printf("  إسنادٌ يتجاوز سعتَه: %s\n", $one("SELECT COUNT(*) FROM op_containers WHERE is_deleted=0 AND allocated_qty>cap_qty+0.005"));
printf("  الحاوياتُ كما هي: %s صفًّا (صفرُ حذف)\n", number_format((int) $one("SELECT COUNT(*) FROM op_containers")));
printf("  قوادحُ القاعدة: %s\n", $one("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()"));
echo "\n✔ تمّت\n";
