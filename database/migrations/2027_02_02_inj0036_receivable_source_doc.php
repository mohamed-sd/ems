<?php
/**
 * 2027_02_02 — INJ-0036: ذمّةُ العميلِ لا تُفتح إلا على مستندٍ معتمَدٍ قائم
 * ═══════════════════════════════════════════════════════════════════════════
 * الملاحظة: «معالجُ حفظ ذمّة عميل ينشئ صفًّا في `fin_receivables` بمرجعِ مستندٍ
 * **نصيٍّ حرٍّ** لا يُتحقَّق من وجوده في أيِّ جدولِ فواتيرَ أو مستخلصات.»
 * وأثرُها: «يُبنى عليها تحصيلٌ في الخزينة فيدخل المالُ مقابلَ مستندٍ لا وجودَ له.»
 *
 * ◆ **والمقاسُ قبل البناء**: 285 ذمّةَ فاتورةٍ حيّة، و**285 منها** يقابلها
 *   `tax_invoices.serial_no` بحالة `issued` — أي أن المسارَ الآليَّ
 *   (`Contracts/claim_helpers.php`) يكتب مرجعًا صحيحًا دائمًا، والعيبُ في
 *   المسارِ اليدويِّ وحدَه. فلا هجرةَ بياناتٍ ضخمةٌ هنا بل **ترقيةُ نصٍّ إلى مفتاح**.
 *
 * ◆ وسجلُّ المستنداتِ **قائمٌ ولا يُخترع**:
 *     `invoice`   ⇐ `tax_invoices.serial_no`         (285 صفًّا · `state='issued'`)
 *     `statement` ⇐ `fin_client_statements.stmt_code` (0 صفًّا · `state='issued'`)
 *   والكشوفُ صفرٌ اليوم ⇒ **كلُّ ذمّةِ كشفٍ جديدةٍ سترسب حتى يُصدَر كشفٌ فعلًا**،
 *   وهذا هو الصوابُ لا كسرٌ: لا ذمّةَ على مستندٍ لم يُصدَر.
 *
 * ◆ **والموروثُ يُعلَن ولا يُمحى** (نمطُ `M-11 legacy_no_ref`): صفٌّ واحدٌ
 *   (`ST-JUN-ALY`) لا يقابله كشفٌ — يُوسَم `legacy_no_ref = 1` فيبقى مرئيًّا
 *   مُعلَنَ النقص، والقيدُ يستثنيه. ومحوُه كان سيُخفي واقعةً بمقدار 3,900,000.
 *   (وحكمُ المالك: «كلمةُ الحذفِ لا تُنفَّذ في القاعدةِ على أنها DELETE».)
 *
 * ◆ والقيدُ يحرس **الجديد** لا الماضي:
 *     `source_doc_id IS NOT NULL OR legacy_no_ref = 1`
 *   والكتابةُ الجديدةُ لا تضع `legacy_no_ref` أبدًا ⇒ فلا بابَ خلفيًّا فيه.
 *
 * ◆ ولا مفتاحَ أجنبيٌّ واحدٌ لأن المرجعَ **متعدِّدُ الجداول** (فاتورةٌ أو كشف)؛
 *   فالنوعُ + المعرِّفُ معًا، ويحرسهما جسٌّ حيٌّ في الهجرةِ وحارسٌ في الخدمة.
 *
 * ◆ مُتحمِّلٌ للتكرار · وكلُّ قيدٍ يُجَسُّ حيًّا قبل إعلانِ النجاح.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);   // گوتشا: بلا config ينفُذ افتراضُ الرمي
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

function m36_col(mysqli $db, $t, $c)
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->bind_param('ss', $t, $c); $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0]; $st->close();
    return $n > 0;
}
function m36_chk(mysqli $db, $t, $c)
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                         WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?");
    $st->bind_param('ss', $t, $c); $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0]; $st->close();
    return $n > 0;
}
function m36_one(mysqli $db, $sql)
{
    $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null;
}
function m36_run(mysqli $db, $sql, $label)
{
    if ($db->query($sql) === false) {
        fwrite(STDERR, "✘ {$label}: " . $db->error . "\n"); exit(1);
    }
    echo "  ✔ {$label}\n";
}

echo "══ INJ-0036 — مفتاحُ المستندِ بدل النصِّ الحرِّ ══\n";

/* ══ ① العمودان ═══════════════════════════════════════════════════════════ */
if (!m36_col($db, 'fin_receivables', 'source_doc_id')) {
    m36_run($db, "ALTER TABLE fin_receivables
                    ADD COLUMN source_doc_id INT UNSIGNED NULL
                      COMMENT 'INJ-0036: معرِّفُ المستندِ المعتمَد — tax_invoices.id أو fin_client_statements.id حسب doc_type'
                      AFTER doc_ref",
        'أُضيف `source_doc_id`');
} else { echo "  ○ `source_doc_id` قائم\n"; }

if (!m36_col($db, 'fin_receivables', 'legacy_no_ref')) {
    m36_run($db, "ALTER TABLE fin_receivables
                    ADD COLUMN legacy_no_ref TINYINT(1) NOT NULL DEFAULT 0
                      COMMENT 'موروثٌ بلا مستندٍ مقابل — يُعلَن ولا يُمحى (نمط M-11)'
                      AFTER source_doc_id",
        'أُضيف `legacy_no_ref`');
} else { echo "  ○ `legacy_no_ref` قائم\n"; }

/* ══ ② الترقية: النصُّ القائمُ يُحلُّ إلى مفتاح ═══════════════════════════ */
echo "\n── ② ترقيةُ المراجعِ القائمة\n";
$before = (int) m36_one($db, "SELECT COUNT(*) FROM fin_receivables
                                WHERE COALESCE(is_deleted,0)=0 AND source_doc_id IS NULL");
m36_run($db, "UPDATE fin_receivables r
                JOIN tax_invoices t
                  ON t.serial_no = r.doc_ref AND t.company_id = r.company_id AND t.state = 'issued'
                 SET r.source_doc_id = t.id
               WHERE r.doc_type = 'invoice' AND r.source_doc_id IS NULL",
    'حُلَّت مراجعُ الفواتير إلى `tax_invoices.id`');
$inv = $db->affected_rows;

m36_run($db, "UPDATE fin_receivables r
                JOIN fin_client_statements s
                  ON s.stmt_code = r.doc_ref AND s.company_id = r.company_id AND s.state = 'issued'
                 SET r.source_doc_id = s.id
               WHERE r.doc_type = 'statement' AND r.source_doc_id IS NULL",
    'وحُلَّت مراجعُ الكشوف إلى `fin_client_statements.id`');
$stm = $db->affected_rows;
echo "     الفواتير: {$inv} · الكشوف: {$stm} (من {$before} بلا مفتاح)\n";

/* ══ ③ ما تعذّر حلُّه: يُعلَن ولا يُمحى ═════════════════════════════════ */
echo "\n── ③ الموروثُ الذي لا مستندَ له\n";
$orphans = $db->query("SELECT id, doc_type, doc_ref, amount, currency FROM fin_receivables
                        WHERE COALESCE(is_deleted,0)=0 AND source_doc_id IS NULL AND legacy_no_ref = 0");
$list = array();
while ($orphans && ($o = $orphans->fetch_assoc())) { $list[] = $o; }
if (!$list) {
    echo "  ✔ لا يتيمَ — كلُّ ذمّةٍ حيّةٍ لها مستندُها\n";
} else {
    foreach ($list as $o) {
        echo "  ⚠ #{$o['id']} · {$o['doc_type']} · «{$o['doc_ref']}» · "
           . number_format((float) $o['amount'], 2) . ' ' . $o['currency'] . "\n";
    }
    m36_run($db, "UPDATE fin_receivables SET legacy_no_ref = 1
                   WHERE COALESCE(is_deleted,0)=0 AND source_doc_id IS NULL AND legacy_no_ref = 0",
        'وُسِمت ' . count($list) . ' موروثةً `legacy_no_ref` — تبقى مرئيةً مُعلَنةَ النقص');
}
/* والمحذوفُ منطقيًّا يُوسَم كذلك — القيدُ يشمل الجدولَ كلَّه لا الحيَّ منه */
$db->query("UPDATE fin_receivables SET legacy_no_ref = 1
             WHERE source_doc_id IS NULL AND legacy_no_ref = 0");

/* ══ ④ القيدُ — الحدُّ الأخير ═══════════════════════════════════════════ */
echo "\n── ④ القيدُ البنيوي\n";
if (!m36_chk($db, 'fin_receivables', 'chk_recv_source_doc')) {
    m36_run($db, "ALTER TABLE fin_receivables
                    ADD CONSTRAINT chk_recv_source_doc
                    CHECK (source_doc_id IS NOT NULL OR legacy_no_ref = 1)",
        'أُضيف `chk_recv_source_doc` — لا ذمّةَ جديدةٌ بلا مفتاحِ مستند');
} else { echo "  ○ `chk_recv_source_doc` قائم\n"; }

$idx = (int) m36_one($db, "SELECT COUNT(*) FROM information_schema.STATISTICS
                            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_receivables'
                              AND INDEX_NAME='idx_recv_source_doc'");
if (!$idx) {
    m36_run($db, "ALTER TABLE fin_receivables
                    ADD INDEX idx_recv_source_doc (doc_type, source_doc_id)",
        'وفهرسٌ على (النوع · المعرِّف) — فالنقرةُ إلى الفاتورةِ لا تمسح الجدول');
} else { echo "  ○ الفهرسُ قائم\n"; }

/* ══ ⑤ الجسُّ الحيّ — قيدٌ لا يُجَسُّ ادّعاء ═══════════════════════════════ */
echo "\n── ⑤ جسُّ القيدِ حيًّا (ثم تراجُع)\n";
$db->begin_transaction();
$co = (int) m36_one($db, "SELECT company_id FROM fin_receivables LIMIT 1");
$bad = $db->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
                     source_doc_id, legacy_no_ref, amount, currency, collected, state, is_deleted, created_by)
                   VALUES ({$co}, 1, 'invoice', 'PROBE-NO-DOC', NULL, 0, 100, 'SDG', 0, 'open', 0, 0)");
echo '  ' . ($bad === false ? '✔ رُفضت ذمّةٌ بلا مفتاحِ مستند — ' . mb_substr($db->error, 0, 60)
                            : '✘ **مرَّت** ذمّةٌ بلا مفتاح — القيدُ لا يعمل') . "\n";
$okProbe = ($bad === false);
$ti = (int) m36_one($db, "SELECT id FROM tax_invoices WHERE state='issued' LIMIT 1");
$good = $db->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
                      source_doc_id, legacy_no_ref, amount, currency, collected, state, is_deleted, created_by)
                    VALUES ({$co}, 1, 'invoice', 'PROBE-WITH-DOC', {$ti}, 0, 100, 'SDG', 0, 'open', 0, 0)");
echo '  ' . ($good !== false ? '✔ وقُبلت ذمّةٌ بمفتاحِها — القيدُ لا يمنع الصحيح'
                             : '✘ رُفض الصحيحُ — القيدُ أوسعُ من معياره: ' . mb_substr($db->error, 0, 60)) . "\n";
$okProbe = $okProbe && ($good !== false);
$db->rollback();
echo "  ○ تُراجِع الجسُّ — لا صفَّ بقي\n";

/* ══ ⑥ الحصيلة ═══════════════════════════════════════════════════════════ */
echo "\n── ⑥ الحصيلة\n";
$tot  = (int) m36_one($db, "SELECT COUNT(*) FROM fin_receivables WHERE COALESCE(is_deleted,0)=0");
$keyd = (int) m36_one($db, "SELECT COUNT(*) FROM fin_receivables
                             WHERE COALESCE(is_deleted,0)=0 AND source_doc_id IS NOT NULL");
$leg  = (int) m36_one($db, "SELECT COUNT(*) FROM fin_receivables
                             WHERE COALESCE(is_deleted,0)=0 AND legacy_no_ref = 1");
echo "  ذممٌ حيّة: {$tot} · بمفتاحِ مستندٍ حقيقي: {$keyd} · موروثةٌ مُعلَنة: {$leg}\n";
echo (($keyd + $leg) === $tot ? "  ✔ لا صفَّ خارجَ التصنيفين\n" : "  ✘ صفوفٌ خارجَ التصنيفين\n");

echo "\n" . ($okProbe && ($keyd + $leg) === $tot
    ? "✅ INJ-0036 — القاعدةُ تحرس. والحارسُ في الخدمةِ يكمّلها بردِّ 422.\n"
    : "⚠ راجِع المخرجاتِ أعلاه\n");
exit($okProbe ? 0 : 1);
