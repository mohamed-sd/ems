<?php
/**
 * 2027_04_14_stock_no_negative_balance.php
 * ═══════════════════════════════════════════════════════════════════════════
 * لا يخرج من المخزنِ ما ليس فيه — قادحًا لا فحصَ تطبيق — ⇐ INJ-0352
 *
 * نصُّ القبول: «تحويلان متزامنان يتجاوزان الرصيدَ: ينجح أحدهما ويُرفض الآخرُ
 * ٤٠٩، **ولا يصير الرصيدُ سالبًا في أيِّ حال**».
 *
 * ── المقيسُ في الخدمة ─────────────────────────────────────────────────────
 * `StockMoveService::transfer` **يقفل فعلًا**: يقرأ الرصيدَ بـ`FOR UPDATE`
 * داخلَ المعاملةِ ثم يكتب الحركتين. فالسباقُ بين نسختين من هذه الخدمةِ مُغلق.
 *
 * ── وما يبقى مفتوحًا ──────────────────────────────────────────────────────
 * «**في أيِّ حال**» أوسعُ من هذه الخدمة: `proc_stock_move` يكتب فيه كاتبون
 * آخرون (الصرفُ والاستلامُ والتسويات)، وأيُّ كاتبٍ لا يمرُّ بالقفلِ يستطيع
 * إخراجَ ما ليس في المخزن. **وحارسٌ في طبقةٍ واحدةٍ ليس حارسًا** (CS-11).
 *
 * ◆ فالحارسُ **قادحٌ في القاعدة**: كلُّ حركةٍ صادرةٍ تُحسب رصيدَ مخزنِها قبل
 *   قبولِها، وتُردُّ بـ`SIGNAL SQLSTATE '45000'` إن أخرجت ما ليس فيه.
 * ◆ ولا يُطبَّق بأثرٍ رجعيّ: القادحُ يحكم على **الوارد** فقط، فالبيانةُ
 *   التاريخيةُ لا تُدان ولا تُمنع قراءتُها.
 * ◆ و`information_schema.TRIGGERS` تحتاج امتيازًا قد لا يملكه مستخدمُ التطبيق —
 *   فالتحققُ **وظيفيٌّ**: تُجرَّب حركةٌ مخالفةٌ ويُقرأ الردّ.
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
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ لا يخرج من المخزنِ ما ليس فيه ══\n\n";

/* الأنواعُ الواردةُ مرآةُ `StockMoveService::INBOUND` — مصدرٌ واحدٌ للمعنى */
$IN = array('استلام', 'تحويل وارد', 'مرتجع', 'تسوية زيادة');
$inList = "'" . implode("','", $IN) . "'";

$conn->query('DROP TRIGGER IF EXISTS trg_stock_no_negative');
$sql = "CREATE TRIGGER trg_stock_no_negative BEFORE INSERT ON proc_stock_move
FOR EACH ROW
BEGIN
    DECLARE bal DECIMAL(18,4);
    IF NEW.move_type NOT IN ({$inList}) THEN
        SELECT COALESCE(SUM(CASE WHEN move_type IN ({$inList}) THEN qty ELSE -qty END), 0)
          INTO bal
          FROM proc_stock_move
         WHERE company_id = NEW.company_id
           AND item_id = NEW.item_id
           AND warehouse_id = NEW.warehouse_id;
        IF bal < NEW.qty THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'STK-409: لا يخرج من المخزن ما ليس فيه — الرصيد اقل من المطلوب';
        END IF;
    END IF;
END";
if (!$conn->query($sql)) { exit("✘ تعذّر إنشاءُ القادح: {$conn->error}\n"); }
echo "  ✔ trg_stock_no_negative — كلُّ حركةٍ صادرةٍ تُحسب رصيدَها قبل قبولها\n";

/* ── التحققُ **وظيفيٌّ** لا من `information_schema` ─────────────────────── */
echo "\n── جسٌّ وظيفيّ: أتُردُّ حركةٌ تُخرج ما ليس في المخزن؟\n";
$item = 0; $wh = 0;
$r = $conn->query('SELECT id FROM proc_item ORDER BY id LIMIT 1');
if ($r && ($x = $r->fetch_row())) { $item = (int) $x[0]; }
$r = $conn->query('SELECT id FROM proc_warehouse ORDER BY id DESC LIMIT 1');
if ($r && ($x = $r->fetch_row())) { $wh = (int) $x[0]; }
if ($item && $wh) {
    $r = $conn->query("SELECT COALESCE(SUM(CASE WHEN move_type IN ({$inList}) THEN qty ELSE -qty END),0) b
                         FROM proc_stock_move WHERE company_id=4 AND item_id={$item} AND warehouse_id={$wh}");
    $bal = $r ? (float) $r->fetch_row()[0] : 0.0;
    $over = $bal + 9999;
    $ok = $conn->query("INSERT INTO proc_stock_move
            (company_id,item_id,warehouse_id,move_type,qty,ref_type,note,moved_at,created_by)
          VALUES (4,{$item},{$wh},'صرف',{$over},'wh_transfer','جسُّ القادح — يُردّ',NOW(),1)");
    if ($ok) {
        echo "  ✘ **مرّت** حركةٌ تتجاوز الرصيدَ — القادحُ لا يعمل\n";
        $conn->query("DELETE FROM proc_stock_move WHERE note = 'جسُّ القادح — يُردّ'");
    } else {
        echo '  ✔ رُدّت: ' . mb_substr($conn->error, 0, 90) . "\n";
        echo "     (الرصيدُ {$bal} والمطلوبُ {$over})\n";
    }
} else {
    echo "  ⚠ لا صنفَ ولا مخزنَ للجسّ — يُعلَن ولا يُدّعى\n";
}

echo "\n✔ تمّت\n";
