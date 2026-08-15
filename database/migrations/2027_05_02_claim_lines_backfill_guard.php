<?php
/**
 * 2027_05_02_claim_lines_backfill_guard.php
 * ═══════════════════════════════════════════════════════════════════════════
 * B9: مستخلصاتٌ بلا بنودٍ — ردمٌ تاريخيٌّ + حارسٌ بنيويٌّ يمنع تكرارَه
 *
 * القياس: claims=298 · claim_lines=0 · tax_invoices=285 فوقها. «مستندٌ ماليٌّ
 * بلا تفصيلٍ يسنده» — والبيانُ تجريبيٌّ بإقرارِ المالك، فالتصرف:
 *   ① ردمُ بندٍ واحدٍ لكلِّ مستخلصٍ من رأسِه (qty=1 · unit_price=الإجمالي)
 *     موسومًا `backfill` — فمجموعُ البنودِ = الإجمالي، والأثرُ مُعلَنٌ لا مُدَّعى.
 *   ② قادحٌ على tax_invoices: **لا فاتورةَ لمستخلصٍ بلا بنود** — فالثغرةُ
 *     تُغلق بنيويًّا لا بردمٍ يُنسى.
 * ◆ ويُثبَت القادحُ باختبارٍ سلبيٍّ داخلَ الهجرةِ نفسِها (يُنظَّف أثرُه).
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

echo "══ B9: بنودُ المستخلصاتِ والحارس ══\n\n";

/* source_kind قد يكون تعدادًا — تُقرأ قيمُه ولا تُخمَّن */
$ct = (string) $one("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='claim_lines' AND COLUMN_NAME='source_kind'");
$srcKind = 'backfill';
if (stripos($ct, 'enum') === 0 && preg_match_all("/'([^']+)'/", $ct, $m)) {
    $srcKind = in_array('backfill', $m[1], true) ? 'backfill' : $m[1][0];
    echo "  source_kind تعداد (" . implode('·', $m[1]) . ") ⇐ تُستعمل «{$srcKind}»\n";
}

/* ① الردم — عاطل: لا يُردم مستخلصٌ له بنود */
$before = (int) $one("SELECT COUNT(*) FROM claim_lines");
$sql = "INSERT INTO claim_lines
          (company_id, claim_id, source_kind, source_ref, work_date, unit_type,
           qty, unit_price, amount, created_at)
        SELECT c.company_id, c.id, '" . $conn->real_escape_string($srcKind) . "',
               CONCAT('claim:', c.claim_no, ' — ردمٌ تاريخيٌّ (بيانٌ تجريبي)'),
               COALESCE(c.period_to, DATE(c.created_at)), 'hour',
               1, COALESCE(c.gross_amount,0), COALESCE(c.gross_amount,0), NOW()
        FROM claims c
        WHERE COALESCE(c.is_deleted,0)=0
          AND NOT EXISTS (SELECT 1 FROM claim_lines l WHERE l.claim_id = c.id)";
if (!$conn->query($sql)) { exit("  ✘ فشل الردم: {$conn->error}\n"); }
echo '  ① رُدم ' . $conn->affected_rows . " بندًا (كان $before)\n";

/* اتساق: مجموعُ بنودِ كلِّ مستخلصٍ = إجماليُّه */
$bad = (int) $one("SELECT COUNT(*) FROM (
    SELECT c.id FROM claims c JOIN claim_lines l ON l.claim_id=c.id
    WHERE COALESCE(c.is_deleted,0)=0
    GROUP BY c.id, c.gross_amount HAVING ABS(SUM(l.amount)-c.gross_amount) > 0.01) d");
echo "  اتساقُ البنودِ مع الرأس: $bad مخالفًا (المتوقَّع 0)\n";

/* ② الحارس */
$conn->query("DROP TRIGGER IF EXISTS trg_taxinv_needs_lines");
$ok = $conn->query("CREATE TRIGGER trg_taxinv_needs_lines BEFORE INSERT ON tax_invoices FOR EACH ROW
BEGIN
    IF NEW.claim_id IS NOT NULL
       AND (SELECT COUNT(*) FROM claim_lines l WHERE l.claim_id = NEW.claim_id) = 0 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'لا فاتورةَ لمستخلصٍ بلا بنود — أكمِلْ بنودَ المستخلصِ أولًا';
    END IF;
END");
if (!$ok) { exit("  ✘ تعذّر القادح: {$conn->error}\n"); }
echo "  ② قادحُ «لا فاتورةَ بلا بنود» رُكِّب\n";

/* ③ الاختبارُ السلبيّ: مستخلصٌ مؤقتٌ بلا بنود ⇐ الفاتورةُ تُرفض */
$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, client_id, currency, gross_amount, net_amount, state, created_by, created_at)
              VALUES (4, 'CLM-GUARD-TEST', 1, 1, 'SDG', 100, 100, 'draft', 0, NOW())");
$cid = (int) $conn->insert_id;
$st = $conn->prepare("INSERT INTO tax_invoices (company_id, claim_id, client_id, serial_no, serial_year, serial_seq, currency, net_amount, total_amount, state, created_at)
                      VALUES (4, ?, 1, 'INV-GUARD-TEST', 2026, 999999, 'SDG', 100, 100, 'draft', NOW())");
$st->bind_param('i', $cid);
$blocked = !$st->execute();
$errno = $st->errno;
$st->close();
echo '  ③ اختبارٌ سلبيّ: ' . ($blocked && $errno === 1644 ? "✔ رُفضت الفاتورةُ (1644)" : "✘ مرّت! ($errno)") . "\n";
$conn->query("DELETE FROM tax_invoices WHERE serial_no='INV-GUARD-TEST'");
$conn->query("DELETE FROM claims WHERE claim_no='CLM-GUARD-TEST'");

printf("\n  الحصيلة: مستخلصاتٌ بلا بنود = %s (المتوقَّع 0) · بنودٌ = %s\n",
    $one("SELECT COUNT(*) FROM claims c WHERE COALESCE(c.is_deleted,0)=0
          AND NOT EXISTS (SELECT 1 FROM claim_lines l WHERE l.claim_id=c.id)"),
    number_format((int) $one("SELECT COUNT(*) FROM claim_lines")));
echo ($blocked ? "\n✔ تمّت\n" : "\n✘ الحارسُ لا يعمل\n");
if (!$blocked) { exit(1); }
