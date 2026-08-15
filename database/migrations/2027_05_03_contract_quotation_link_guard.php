<?php
/**
 * 2027_05_03_contract_quotation_link_guard.php
 * ═══════════════════════════════════════════════════════════════════════════
 * B10: «عرضٌ ⇐ عقد» — ردمُ الوصلةِ التاريخيةِ + حارسٌ يمنع عقدًا بلا عرض
 *
 * القياس: 120 عقدًا كلُّها quotation_id فارغ، و20 عرضًا قائمًا. والحكم (M-08):
 * السلسلةُ عميل ⇐ فرصة ⇐ عرض ⇐ عقد. والبيانُ تجريبيٌّ بإقرارِ المالك:
 *   ① لكلِّ عقدٍ بلا عرضٍ يُنشأ عرضٌ ردميٌّ بحالةِ «accepted» من بياناتِ
 *     العقدِ نفسِه (العميلُ من مشروعِه · المبلغُ من شهريَّتِه) موسومًا صراحةً.
 *   ② قادحان (INSERT/UPDATE) يمنعان عقدًا جديدًا بلا مرجعِ عرض.
 * ◆ والإثباتُ سلبيًّا داخلَ الهجرة: محاولةُ تفريغِ المرجعِ تُرفض 1644.
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

echo "══ B10: وصلُ العقودِ بعروضِها ══\n\n";
$unlinked = (int) $one("SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0 AND COALESCE(quotation_id,0)=0");
echo "  عقودٌ بلا مرجعِ عرض: $unlinked\n";

/* حالةُ العرضِ تُقرأ من التعداد */
$ct = (string) $one("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quotations' AND COLUMN_NAME='state'");
$accepted = 'accepted';
if (stripos($ct, 'enum') === 0 && preg_match_all("/'([^']+)'/", $ct, $m)) {
    foreach (array('accepted', 'مقبول', 'won', 'approved') as $cand) {
        if (in_array($cand, $m[1], true)) { $accepted = $cand; break; }
    }
    if (!in_array($accepted, $m[1], true)) { $accepted = $m[1][count($m[1]) - 1]; }
    echo "  حالاتُ العرض (" . implode('·', $m[1]) . ") ⇐ «{$accepted}»\n";
}

/* ① عرضٌ ردميٌّ لكلِّ عقدٍ غيرِ موصول — العميلُ من مشروعِه وإلا أدنى عميلٍ للكيان */
$fallback = (int) $one("SELECT MIN(id) FROM clients");
$sql = "INSERT INTO quotations
          (company_id, quotation_code, client_id, currency, amount_total, state, notes, created_by, created_at)
        SELECT c.company_id, CONCAT('QT-BF-', c.id),
               COALESCE(p.client_id, $fallback),
               COALESCE(NULLIF(c.price_currency_contract,''),'SDG'),
               COALESCE(c.total_contract_permonth, 0),
               '" . $conn->real_escape_string($accepted) . "',
               'عرضٌ ردميٌّ من العقدِ — وصلُ سلسلةِ عرض⇐عقد (بيانٌ تجريبيٌّ بإقرارِ المالك 2026-08-16)',
               0, NOW()
        FROM contracts c
        LEFT JOIN project p ON p.id = c.project_id
        WHERE COALESCE(c.is_deleted,0)=0 AND COALESCE(c.quotation_id,0)=0";
if (!$conn->query($sql)) { exit("  ✘ فشل إنشاءُ العروض: {$conn->error}\n"); }
echo '  ① أُنشئ ' . $conn->affected_rows . " عرضًا ردميًّا\n";

$conn->query("UPDATE contracts c
              JOIN quotations q ON q.quotation_code = CONCAT('QT-BF-', c.id)
              SET c.quotation_id = q.id
              WHERE COALESCE(c.is_deleted,0)=0 AND COALESCE(c.quotation_id,0)=0");
echo '  ② وُصل ' . $conn->affected_rows . " عقدًا بعرضِه\n";

/* ② الحارسان */
$conn->query("DROP TRIGGER IF EXISTS trg_contract_needs_quote_ins");
$conn->query("DROP TRIGGER IF EXISTS trg_contract_needs_quote_upd");
$G = "IF COALESCE(NEW.is_deleted,0)=0 AND COALESCE(NEW.quotation_id,0)=0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'لا عقدَ بلا مرجعِ عرضٍ — أنشئِ العرضَ واقبله أولًا (سلسلة عميل⇐فرصة⇐عرض⇐عقد)';
      END IF;";
if (!$conn->query("CREATE TRIGGER trg_contract_needs_quote_ins BEFORE INSERT ON contracts FOR EACH ROW BEGIN $G END")
 || !$conn->query("CREATE TRIGGER trg_contract_needs_quote_upd BEFORE UPDATE ON contracts FOR EACH ROW BEGIN $G END")) {
    exit("  ✘ تعذّر الحارسان: {$conn->error}\n");
}
echo "  ③ الحارسان رُكِّبا\n";

/* ③ الإثباتُ السلبيّ: تفريغُ المرجعِ يُرفض */
$cid = (int) $one("SELECT id FROM contracts WHERE COALESCE(is_deleted,0)=0 AND quotation_id>0 LIMIT 1");
$st = $conn->prepare("UPDATE contracts SET quotation_id=NULL WHERE id=?");
$st->bind_param('i', $cid);
$blocked = !$st->execute();
$errno = $st->errno;
$st->close();
echo '  ④ اختبارٌ سلبيّ: ' . ($blocked && $errno === 1644 ? '✔ رُفض تفريغُ المرجع (1644)' : "✘ مرّ! ($errno)") . "\n";

printf("\n  الحصيلة: عقودٌ بلا عرضٍ = %s (المتوقَّع 0) · وصلةُ عرض⇐عقد = %s من %s\n",
    $one("SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0 AND COALESCE(quotation_id,0)=0"),
    $one("SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0 AND quotation_id>0"),
    $one("SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0"));
echo ($blocked ? "\n✔ تمّت\n" : "\n✘ الحارسُ لا يعمل\n");
if (!$blocked) { exit(1); }
