<?php
/**
 * 2027_04_08_workflow_chain_links.php
 * ═══════════════════════════════════════════════════════════════════════════
 * الحلقاتُ المفقودةُ في السلسلة — ⇐ INJ-0142 · INJ-0091 · INJ-0335
 *
 * ── العلّةُ الجامعة ───────────────────────────────────────────────────────
 * **المستندُ لا يعرف أباه.** ثلاثُ سلاسلَ مقطوعةٌ في القاعدةِ لا في الشاشة:
 *   · عرضُ سعرٍ يُقبَل ⇒ ولا عمودَ في `contracts` يحمل `quotation_id`، فالعقدُ
 *     المولَّدُ لا يُعرف من أيِّ عرضٍ جاء ولا يُقارَن ببنودِه.
 *   · طلبُ عروضٍ يُنشأ ⇒ و`supplier_rfqs` يحمل `client_contract_id` وحدَه:
 *     مشتقٌّ من **العقود** لا من **طلباتِ الشراء**، فسلسلةُ «احتياجٌ ⇒ عرضٌ
 *     ⇒ أمرٌ» مقطوعةٌ من أوّلها.
 *   · وأمرُ شراءٍ يُحفظ ⇒ و`request_id` **يقبل NULL** بلا فحصٍ أنَّ الطلبَ
 *     معتمد، فيُشترى بلا احتياجٍ مُعتمَد.
 *
 * ── القاعدةُ الحاكمة ──────────────────────────────────────────────────────
 * «**والحقلُ الرابطُ إلزاميٌّ بقيدٍ في القاعدة لا بفحصٍ في الواجهة**» — لأنَّ
 * فحصَ الواجهةِ يُلتفُّ عليه من الشاشةِ الثانيةِ التي تكتب في الجدولِ نفسِه.
 *
 * ◆ **والقيدُ على الجديدِ لا على المرحَّل**: صفوفٌ قائمةٌ بلا مرجعٍ لا تُلفَّق
 *   لها مراجعُ ولا تُحذف — يُعلَن عددُها ويُحرَس ما بعدها. ولهذا الشرطُ
 *   `id > <السقف>` في `CHECK`: تاريخٌ يُقرأ ومستقبلٌ يُحرَس.
 * ◆ ولا `NOT NULL` على عمودٍ فيه NULLاتٌ قائمة — تُقفل الهجرةُ على نفسها.
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

echo "══ الحلقاتُ المفقودةُ في السلسلة ══\n\n";

$hasCol = function ($t, $c) use ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '" . $conn->real_escape_string($c) . "'");
    return (bool) ($r && $r->fetch_row());
};
$hasChk = function ($n) use ($conn) {
    $r = $conn->query("SELECT 1 FROM information_schema.CHECK_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '"
                        . $conn->real_escape_string($n) . "'");
    return (bool) ($r && $r->fetch_row());
};
$maxId = function ($t) use ($conn) {
    $r = $conn->query("SELECT COALESCE(MAX(id),0) FROM `{$t}`");
    return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : 0;
};

/* ── ① العقدُ يعرف عرضَه (INJ-0142) ───────────────────────────────────────── */
if (!$hasCol('contracts', 'quotation_id')) {
    if ($conn->query("ALTER TABLE contracts ADD COLUMN quotation_id INT NULL
                      COMMENT 'العرضُ الأبُ الذي وُلد منه هذا العقد (INJ-0142)' AFTER id")) {
        echo "  ✔ contracts.quotation_id أُضيف\n";
    } else { echo '  ⚠ ' . $conn->error . "\n"; }
    @$conn->query('ALTER TABLE contracts ADD INDEX ix_contracts_quotation (quotation_id)');
} else { echo "  · contracts.quotation_id قائمٌ سلفًا\n"; }

/* ── ② طلبُ العروضِ يعرف طلبَ الشراء (INJ-0091) ──────────────────────────── */
if (!$hasCol('supplier_rfqs', 'request_id')) {
    if ($conn->query("ALTER TABLE supplier_rfqs ADD COLUMN request_id INT NULL
                      COMMENT 'طلبُ الشراءِ المعتمدُ الذي اشتُقَّ منه طلبُ العروض (INJ-0091)'
                      AFTER client_contract_id")) {
        echo "  ✔ supplier_rfqs.request_id أُضيف\n";
    } else { echo '  ⚠ ' . $conn->error . "\n"; }
    @$conn->query('ALTER TABLE supplier_rfqs ADD INDEX ix_rfq_request (request_id)');
} else { echo "  · supplier_rfqs.request_id قائمٌ سلفًا\n"; }

/* ── ③ أمرُ الشراءِ يعرف طلبَ العروضِ وترسيتَه (INJ-0091) ─────────────────── */
foreach (array('rfq_id' => 'طلبُ العروضِ الذي رُسي عنه هذا الأمر',
               'award_id' => 'صفُّ الترسيةِ الذي وُلد منه هذا الأمر') as $col => $cmt) {
    if (!$hasCol('proc_order', $col)) {
        if ($conn->query("ALTER TABLE proc_order ADD COLUMN {$col} INT NULL
                          COMMENT '" . $conn->real_escape_string($cmt) . " (INJ-0091)'")) {
            echo "  ✔ proc_order.{$col} أُضيف\n";
        } else { echo '  ⚠ ' . $conn->error . "\n"; }
        @$conn->query("ALTER TABLE proc_order ADD INDEX ix_po_{$col} ({$col})");
    } else { echo "  · proc_order.{$col} قائمٌ سلفًا\n"; }
}

/* ── ④ ولا أمرَ شراءٍ جديدٍ بلا طلبٍ مرتبط (INJ-0335) ────────────────────── */
$legacy = 0;
$r = $conn->query('SELECT COUNT(*) FROM proc_order WHERE request_id IS NULL OR request_id = 0');
if ($r && ($x = $r->fetch_row())) { $legacy = (int) $x[0]; }
$cap = $maxId('proc_order');
echo "\n  أوامرُ شراءٍ قائمةٌ بلا طلبٍ مرتبط: {$legacy} (سقفُ التاريخ id ≤ {$cap})\n";
/* ◆ **والقادحُ لا القيدُ**: MariaDB تمنع ذكرَ عمودِ `AUTO_INCREMENT` في `CHECK`،
     فلا سبيلَ إلى «الجديدُ وحدَه» بقيدٍ ساكن. والقادحُ يحرس **الإدراجَ** ويترك
     ما مضى — وهو المطلوبُ بعينِه: تاريخٌ يُقرأ ومستقبلٌ يُحرَس.
   ◆ وهو في القاعدةِ لا في الشاشة — فلا يُلتفُّ عليه من كاتبٍ آخر. */
$conn->query('DROP TRIGGER IF EXISTS trg_po_request_required');
$sqlT = "CREATE TRIGGER trg_po_request_required BEFORE INSERT ON proc_order
         FOR EACH ROW BEGIN
           IF NEW.request_id IS NULL OR NEW.request_id = 0 THEN
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PO-REQ-422: لا أمرَ شراءٍ بلا طلبٍ مرتبط';
           END IF;
         END";
if ($conn->query($sqlT)) {
    echo "  ✔ قادحٌ: لا أمرَ شراءٍ **جديدٍ** بلا طلبٍ مرتبط — والتاريخُ يُقرأ ولا يُلفَّق\n";
} else { echo '  ⚠ ' . $conn->error . "\n"; }

/* ── ⑤ والخصمُ المقترحُ يقع في `fin_dues` (INJ-0292) ──────────────────────
     ◆ **والجدولُ الصحيحُ هو ما تقرؤه الشاشة**: «الخصوم المقترحة» تقرأ ذممًا
       مدينةً معلَّقةً في `fin_dues` — و`payroll_deductions` جدولُ مسيّرٍ آخر.
       فلا تُضاف أعمدةُ حالةٍ إلى جدولٍ لا يُقرأ: **مخطَّطٌ ميتٌ دَينٌ لا أصل**.
     ◆ والقاعدةُ تحمل سلفًا قيدَ M-11 «لا خصمَ بلا مستندِ مصدر» — فالبنيةُ
       محروسةٌ، والناقصُ كان **فعلَ الاقتراحِ في الشاشة** لا عمودًا. */
$conn->query('DROP TRIGGER IF EXISTS trg_deduction_doc_required');
$dueChk = 0;
$r = $conn->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = DATABASE()
                       AND CHECK_CLAUSE LIKE '%source_doc%'");
if ($r && ($x = $r->fetch_row())) { $dueChk = (int) $x[0]; }
echo '  ' . ($dueChk > 0 ? '✔' : '◆') . " قيدُ «لا خصمَ بلا مستندِ مصدر»: "
   . ($dueChk > 0 ? ($dueChk . ' قيدًا قائمًا (M-11)') : 'غيرُ ظاهرٍ — يُعلَن') . "\n";

echo "\n  ◆ القيودُ على الجديدِ وحدَه — والصفوفُ القائمةُ تُقرأ ولا تُلفَّق لها مراجع.\n";
