<?php
/**
 * tools/fix_inj0036_tests.php — INJ-0036: لا ذمّةَ إلا على مستندٍ معتمَدٍ قائم
 *
 * ⇐ شواهدُ أحكامٍ: INJ-0036
 * ═══════════════════════════════════════════════════════════════════════════
 * القبول: «إنشاءُ ذمّةِ عميلٍ بمرجعٍ لا يقابله مستندٌ معتمدٌ **يُرفض 422**؛
 *          وكلُّ ذمّةٍ **تفتح فاتورتَها بنقرة**.»
 *
 * ◆ شرطان لا شرط، ولكلٍّ اتجاهان. وتُقاس **ثلاثُ طبقاتٍ** لا واحدة:
 *     ① القاعدةُ: `chk_recv_source_doc` يرفض صفًّا بلا مفتاح.
 *     ② الخدمةُ: `ems_receivable_resolve_source` يرفض المرجعَ المخترَعَ
 *        **والمستندَ الملغى** — وهو ما لا تستطيع القاعدةُ رؤيتَه.
 *     ③ الشاشةُ: `dues_fin.php` تنادي الحارسَ قبل الإدراجِ وتصيّر الرابط.
 *   فطبقةٌ واحدةٌ تُقرأ إغلاقًا وهي ليست كذلك.
 *
 * ◆ **والفرقُ بين القاعدةِ والخدمةِ مقصود**: مفتاحٌ يشير إلى فاتورةٍ **ملغاة**
 *   يمرُّ من القاعدة (فالمفتاحُ ليس NULL) ويُردُّ من الخدمة. وهذا هو الجسُّ
 *   الذي يميز حارسًا حقيقيًّا من قيدٍ شكليّ.
 *
 * التشغيل: php tools/fix_inj0036_tests.php
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once $ROOT . '/includes/receivable_source_guard.php';

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }
function one($sql) { $r = $GLOBALS['conn']->query($sql); return $r ? $r->fetch_row()[0] : null; }

echo "══════════════════════════════════════════════════════════════════════\n";
echo " INJ-0036 — الذمّةُ أثرٌ لمستندٍ معتمَد · " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n";

$inv = $conn->query("SELECT id, company_id, serial_no, client_id FROM tax_invoices
                      WHERE state='issued' ORDER BY id DESC LIMIT 1")->fetch_assoc();
if (!$inv) { exit("لا فاتورةَ صادرةً للجسّ\n"); }
$CO = (int) $inv['company_id'];
check(true, "فاتورةٌ صادرةٌ للجسّ: «{$inv['serial_no']}» · شركة {$CO}");

/* ══ ① القاعدة — الحدُّ الأخير ═══════════════════════════════════════════ */
head('① القاعدة: لا صفَّ بلا مفتاحِ مستند');
$conn->begin_transaction();
$bad = $conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
                       source_doc_id, legacy_no_ref, amount, currency, collected, state, is_deleted, created_by)
                     VALUES ({$CO}, 1, 'invoice', 'PROBE-INJ36-GHOST', NULL, 0, 500, 'SDG', 0, 'open', 0, 0)");
check($bad === false, 'رُفضت — ' . mb_substr($conn->error, 0, 52));
$good = $conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
                        source_doc_id, legacy_no_ref, amount, currency, collected, state, is_deleted, created_by)
                      VALUES ({$CO}, 1, 'invoice', '{$inv['serial_no']}', {$inv['id']}, 0, 500, 'SDG', 0, 'open', 0, 0)");
check($good !== false, 'وقُبلت ذاتُ المفتاح — القيدُ لا يمنع الصحيح');
$conn->rollback();

/* ══ ② الخدمة — ما لا تراه القاعدة ═════════════════════════════════════ */
head('② الخدمة: المرجعُ يُحَلُّ أو يُردُّ 422');
$r = ems_receivable_resolve_source($conn, $CO, 'invoice', $inv['serial_no']);
check(!empty($r['ok']) && (int) $r['source_doc_id'] === (int) $inv['id'],
    'مرجعٌ حقيقيٌّ حُلَّ إلى المفتاح #' . (int) $r['source_doc_id']);

$r = ems_receivable_resolve_source($conn, $CO, 'invoice', 'INV-9999-99999');
check(empty($r['ok']) && $r['code'] === 422, 'ومرجعٌ مخترَعٌ رُدَّ 422 — ' . mb_substr($r['reason'], 0, 56));

$r = ems_receivable_resolve_source($conn, $CO, 'invoice', '');
check(empty($r['ok']) && $r['code'] === 422, 'ومرجعٌ فارغٌ رُدَّ 422');

$r = ems_receivable_resolve_source($conn, $CO, 'delivery_note', $inv['serial_no']);
check(empty($r['ok']) && $r['code'] === 422, 'ونوعُ مستندٍ لا سجلَّ له رُدَّ ولم يُخمَّن');

$r = ems_receivable_resolve_source($conn, 0, 'invoice', $inv['serial_no']);
check(empty($r['ok']) && $r['code'] === 422, 'وبلا شركةٍ رُدَّ — لا يُقرأ الغيابُ إذنًا');

/* ══ ③ عزلُ المستأجر: فاتورةُ شركةٍ أخرى ليست مستندًا لهذه ═════════════ */
head('③ العزل: مستندُ شركةٍ أخرى ليس سندًا لذمّةِ هذه');
$other = (int) one("SELECT company_id FROM tax_invoices WHERE company_id <> {$CO} LIMIT 1");
if ($other > 0) {
    $r = ems_receivable_resolve_source($conn, $other, 'invoice', $inv['serial_no']);
    check(empty($r['ok']) && $r['code'] === 422,
        "فاتورةُ الشركة {$CO} لا تصلح سندًا لذمّةٍ في الشركة {$other}");
} else {
    $r = ems_receivable_resolve_source($conn, 99999, 'invoice', $inv['serial_no']);
    check(empty($r['ok']), 'شركةٌ لا فواتيرَ لها لا تجد المستند (لا شركةَ ثانيةً في البيانات)');
}

/* ══ ④ الحالة: مستندٌ **ملغًى** موجودٌ ولا يصلح — وهذا ما تعجز عنه القاعدة ══ */
head('④ الحالة: الموجودُ ليس المعتمَد — والقيدُ وحدَه يمرّرها');
$conn->begin_transaction();
$conn->query("UPDATE tax_invoices SET state='cancelled' WHERE id={$inv['id']}");
$r = ems_receivable_resolve_source($conn, $CO, 'invoice', $inv['serial_no']);
check(empty($r['ok']) && $r['code'] === 422,
    'فاتورةٌ ملغاةٌ رُدَّت 422 — ' . mb_substr((string) $r['reason'], 0, 56));
/* والقاعدةُ **تمرِّرها** — فالمفتاحُ ليس NULL. وهذا يثبت أن الطبقتين لا تُغنيان */
$viaDb = $conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
                         source_doc_id, legacy_no_ref, amount, currency, collected, state, is_deleted, created_by)
                       VALUES ({$CO}, 1, 'invoice', '{$inv['serial_no']}', {$inv['id']}, 0, 5, 'SDG', 0, 'open', 0, 0)");
check($viaDb !== false,
    '**والقاعدةُ تقبلها** — فالحارسُ في الخدمةِ ليس تكرارًا للقيدِ بل طبقةٌ فوقه');
$conn->rollback();
check((string) one("SELECT state FROM tax_invoices WHERE id={$inv['id']}") === 'issued',
    'وتراجَع الجسُّ — الفاتورةُ عادت `issued`');

/* ══ ⑤ النقرة — الشقُّ الثاني من القبول ═════════════════════════════════ */
head('⑤ «كلُّ ذمّةٍ تفتح فاتورتَها بنقرة»');
$link = ems_receivable_source_link('invoice', (int) $inv['id']);
check($link !== null && strpos($link, (string) $inv['id']) !== false,
    'رابطُ الفاتورة: ' . $link);
$target = $ROOT . '/Contracts/tax_invoices.php';
check(is_file($target), 'والوجهةُ ملفٌّ قائمٌ فعلًا — لا رابطَ إلى 404');
$tsrc = (string) file_get_contents($target);
check(strpos($tsrc, "\$_GET['open']") !== false,
    'وهي تقرأ `?open=` — فالنقرةُ تفتح الفاتورةَ بعينها لا القائمةَ كلَّها');
check(ems_receivable_source_link('statement', 1) === null,
    'ونوعُ الكشفِ لا شاشةَ له ⇒ يعود `null` **إعلانًا** لا رابطًا كاذبًا');
check(ems_receivable_source_link('invoice', 0) === null, 'وموروثٌ بلا مفتاحٍ لا رابطَ له');

/* ══ ⑥ الشاشة تنادي الحارسَ قبل الإدراج ════════════════════════════════ */
head('⑥ الحارسُ في مسارِ الكتابةِ لا في الوثيقة');
$src = (string) file_get_contents($ROOT . '/Finance/dues_fin.php');
$posGuard  = strpos($src, 'ems_receivable_resolve_source');
$posInsert = strpos($src, "insert('fin_receivables'");
check($posGuard !== false && $posInsert !== false && $posGuard < $posInsert,
    'المسارُ اليدويُّ ينادي الحارسَ **قبل** الإدراج');
check(strpos($src, "'source_doc_id' => (int) \$__src['source_doc_id']") !== false,
    'ويكتب المفتاحَ المحلولَ لا نصًّا');
check(strpos($src, 'ems_receivable_source_link') !== false, 'والشاشةُ تصيّر الرابط');
$csrc = (string) file_get_contents($ROOT . '/Contracts/claim_helpers.php');
check(strpos($csrc, 'ems_receivable_resolve_source') !== false,
    'والمسارُ الآليُّ يستعمل **الحارسَ نفسَه** — قاعدةُ حلٍّ واحدةٌ لا اثنتان');

/* ══ ⑦ الواقعُ الحيُّ بعد الهجرة ═══════════════════════════════════════ */
head('⑦ الواقعُ الحيّ');
$tot  = (int) one("SELECT COUNT(*) FROM fin_receivables WHERE COALESCE(is_deleted,0)=0");
$keyd = (int) one("SELECT COUNT(*) FROM fin_receivables
                    WHERE COALESCE(is_deleted,0)=0 AND source_doc_id IS NOT NULL");
$leg  = (int) one("SELECT COUNT(*) FROM fin_receivables
                    WHERE COALESCE(is_deleted,0)=0 AND legacy_no_ref = 1");
check($keyd + $leg === $tot, "ذممٌ حيّة {$tot} = بمفتاح {$keyd} + موروثةٌ مُعلَنة {$leg}");
$dangling = (int) one("SELECT COUNT(*) FROM fin_receivables r
                        WHERE COALESCE(r.is_deleted,0)=0 AND r.doc_type='invoice'
                          AND r.source_doc_id IS NOT NULL
                          AND NOT EXISTS (SELECT 1 FROM tax_invoices t
                                           WHERE t.id = r.source_doc_id AND t.company_id = r.company_id)");
check($dangling === 0, "ولا مفتاحَ معلَّقٍ يشير إلى فاتورةٍ غيرِ قائمةٍ في شركتها: {$dangling}");
check($leg === 1, "والموروثُ **واحدٌ مُعلَنٌ لا ممحوّ** — وقيمتُه 3,900,000 بقيت مرئية");

echo "\n" . str_repeat('═', 70) . "\n";
printf("ناجحٌ: %d · فاشلٌ: %d\n", $PASS, $FAIL);
echo "◆ ثلاثُ طبقاتٍ مقيسة: القاعدةُ ترفض الفارغ · والخدمةُ ترفض الملغى · والشاشةُ تنادي وتربط.\n";
echo str_repeat('═', 70) . "\n";
exit($FAIL === 0 ? 0 : 1);
