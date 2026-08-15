<?php
/**
 * 2027_04_09_contract_note_severity.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ملاحظةٌ حرجةٌ تحجب التوقيع — ⇐ INJ-0143
 *
 * نصُّ القبول: «ملاحظةٌ حرجةٌ **تحجب التوقيعَ** حتى تُغلق بمستندٍ ومعتمِد؛
 * **ولا يعتمد الحجبُ على أيِّ مطابقةٍ نصية**».
 *
 * والمقيس: `contract_notes` سبعةُ أعمدةٍ لا خطورةَ فيها ولا حالة — فالحجبُ
 * مستحيلٌ إلا بالبحثِ في نصِّ الملاحظةِ عن كلمةٍ، وهو ما يمنعه الشرطُ صراحةً:
 * **مطابقةُ النصِّ تُخدع بحرفٍ**، وملاحظةٌ تقول «غير حرج» تُقرأ حرجة.
 *
 * ◆ فالخطورةُ **عمودٌ محكومٌ بتعدادٍ** لا كلمةٌ في نصّ، والإغلاقُ يلزمه مستندٌ
 *   ومعتمِدٌ — وقادحٌ في القاعدةِ يمنع إغلاقًا بلا مستند.
 * ◆ والقائمُ يبقى `normal`: ملاحظاتٌ كُتبت قبل التمييزِ لا تُصنَّف حرجةً بأثرٍ
 *   رجعيٍّ فتُجمّد عقودًا موقَّعةً سلفًا.
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

echo "══ خطورةُ ملاحظةِ العقدِ وحالتُها ══\n\n";
$hasCol = function ($t, $c) use ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '" . $conn->real_escape_string($c) . "'");
    return (bool) ($r && $r->fetch_row());
};
foreach (array(
    'severity'        => "ENUM('normal','critical') NOT NULL DEFAULT 'normal'",
    'note_state'      => "ENUM('open','closed') NOT NULL DEFAULT 'open'",
    'closure_doc_ref' => 'VARCHAR(160) NULL',
    'closed_by'       => 'INT NULL',
    'closed_at'       => 'DATETIME NULL',
) as $col => $def) {
    if (!$hasCol('contract_notes', $col)) {
        echo ($conn->query("ALTER TABLE contract_notes ADD COLUMN {$col} {$def}")
              ? "  ✔ contract_notes.{$col} أُضيف\n" : ('  ⚠ ' . $conn->error . "\n"));
    } else { echo "  · contract_notes.{$col} قائمٌ سلفًا\n"; }
}
@$conn->query('ALTER TABLE contract_notes ADD INDEX ix_cnote_block (contract_id, severity, note_state)');

/* ◆ ولا إغلاقَ لملاحظةٍ حرجةٍ بلا مستندٍ ومعتمِد — قادحٌ لا فحصُ واجهة */
$conn->query('DROP TRIGGER IF EXISTS trg_cnote_close_needs_doc');
$sql = "CREATE TRIGGER trg_cnote_close_needs_doc BEFORE UPDATE ON contract_notes
        FOR EACH ROW BEGIN
          IF NEW.note_state = 'closed' AND OLD.note_state <> 'closed'
             AND NEW.severity = 'critical'
             AND (NEW.closure_doc_ref IS NULL OR NEW.closure_doc_ref = ''
                  OR NEW.closed_by IS NULL OR NEW.closed_by = 0) THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'CNOTE-422: إغلاقُ ملاحظةٍ حرجةٍ يلزمه مستندٌ ومعتمِد';
          END IF;
        END";
echo ($conn->query($sql) ? "  ✔ قادحٌ: لا إغلاقَ لحرجةٍ بلا مستندٍ ومعتمِد\n" : ('  ⚠ ' . $conn->error . "\n"));

$r = $conn->query("SELECT COUNT(*) FROM contract_notes WHERE severity = 'critical' AND note_state = 'open'");
$open = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : 0;
echo "\n  ملاحظاتٌ حرجةٌ مفتوحةٌ الآن: {$open} (القائمُ كلُّه `normal` — لا تصنيفَ بأثرٍ رجعيّ)\n";
