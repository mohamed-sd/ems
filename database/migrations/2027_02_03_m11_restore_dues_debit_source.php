<?php
/**
 * 2027_02_03 — ترميمُ `M-11`: لا خصمَ بلا مستندِ مصدر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ثغرةٌ ماليةٌ مفتوحةٌ ولم تكن في أيِّ سجل.** حكمُ `M-11` («كلُّ خصمٍ ينقر
 *   إلى مستنده») كان محروسًا بقيدٍ في القاعدةِ اسمُه `ck_dues_debit_source`.
 *   وسقط القيدُ — والقياسُ يقول: `fin_dues` فيه **صفرُ قيدِ CHECK**، وجسٌّ
 *   وظيفيٌّ أدرج خصمًا بـ`source_doc_type = NULL` **فمرّ**.
 *
 * ◆ **وسبعةُ مواضعَ تُصرّح باتكائها عليه** وهي تتكئ على فراغ:
 *     `app/Services/Contract/PenaltyService.php:402`
 *     `Finance/dues_fin.php:70`
 *     `tests/dues_source_doc_test.php` (أحمرُ اليومَ — 23/3)
 *     `tests/employee_settlement_test.php:87` · `tests/settlement_test.php:83`
 *     `tests/settlements_screen_http_proof.php:101`
 *   وهذا أخطرُ من غيابِ القيد: شيفرةٌ تُعلن أن القاعدةَ تحرسها وهي لا تحرسها،
 *   فتُقرأ الحمايةُ موجودةً فلا يُضاف فحصٌ تطبيقيٌّ يعوّضها.
 *
 * ◆ **والاسمُ ليس تفصيلًا**: `dues_source_doc_test.php:59` يشترط أن تحتوي
 *   رسالةُ خطأِ القاعدةِ على `ck_dues_debit_source` حرفيًّا. فقيدٌ باسمٍ آخرَ
 *   يحرس البياناتَ ويُبقي الفاحصَ أحمرَ — أي يحرس ولا يُشهَد له.
 *
 * ◆ **والمقاسُ قبل البناء**: صفرُ صفٍّ مخالفٍ حيًّا (4 خصومٍ كلُّها بمصدرٍ:
 *   `penalty_assessment`×2 · `mnt_order` · `legacy_no_ref`). فلا وسمَ موروثٍ
 *   يلزم هنا — بخلافِ `INJ-0036`. و18 استحقاقًا دائنًا بلا مصدرٍ **تبقى
 *   مسموحةً**: الاستحقاقُ لا يُطالَب بمستند، والقيدُ مشروطٌ بالاتجاه.
 *
 * ◆ ويبقى `pending_source` بابًا مشروعًا (سلفٌ وخصمٌ بلا مستندٍ مبنيٍّ بعد)
 *   لأنه **قيمةٌ مُعلَنةٌ** في ENUM لا NULL صامتة — والفرقُ أن المُعلَنَ يُرى
 *   في تقريرٍ ويُطارَد، والصامتَ لا.
 *
 * ◆ مُتحمِّلٌ للتكرار · ويُجَسُّ القيدُ حيًّا في الاتجاهين قبل إعلانِ النجاح.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);   // گوتشا: بلا config ينفُذ افتراضُ الرمي
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

function m11_one(mysqli $db, $sql)
{
    $r = $db->query($sql);
    return $r ? $r->fetch_row()[0] : null;
}

echo "══ M-11 — ترميمُ `ck_dues_debit_source` ══\n";

/* ══ ① المخالفونَ: يُعلَنون قبل أيِّ قيد ═════════════════════════════════ */
$bad = (int) m11_one($db, "SELECT COUNT(*) FROM fin_dues
                            WHERE direction = 'debit' AND source_doc_type IS NULL");
echo "  خصومٌ مخالفونَ أحياء: {$bad}\n";
if ($bad > 0) {
    /* الموروثُ يُعلَن ولا يُمحى — و`legacy_no_ref` قيمةٌ **قائمةٌ** في ENUM
       مصنوعةٌ لهذا بعينه (نمطُ M-11 الأصليّ). */
    $db->query("UPDATE fin_dues SET source_doc_type = 'legacy_no_ref'
                 WHERE direction = 'debit' AND source_doc_type IS NULL");
    echo '  ⚠ وُسِم ' . $db->affected_rows . " موروثًا `legacy_no_ref` — يُعلَن ولا يُمحى\n";
}

/* ══ ② القيد — بالاسمِ الذي يحرسه الفاحص ═══════════════════════════════ */
$has = (int) m11_one($db, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'fin_dues'
                              AND CONSTRAINT_NAME = 'ck_dues_debit_source'");
if (!$has) {
    if ($db->query("ALTER TABLE fin_dues
                      ADD CONSTRAINT ck_dues_debit_source
                      CHECK ((direction <> 'debit') OR (source_doc_type IS NOT NULL))") === false) {
        fwrite(STDERR, '✘ تعذّر إضافةُ القيد: ' . $db->error . "\n");
        exit(1);
    }
    echo "  ✔ أُضيف `ck_dues_debit_source`\n";
} else {
    echo "  ○ القيدُ قائمٌ سلفًا\n";
}

/* ══ ③ الجسُّ الحيُّ في الاتجاهين — قيدٌ لا يُجَسُّ ادّعاء ══════════════ */
echo "\n── جسٌّ حيٌّ (ثم تراجُع)\n";
$db->begin_transaction();
$co  = (int) m11_one($db, 'SELECT company_id FROM fin_dues LIMIT 1');
$cur = (string) m11_one($db, "SELECT code FROM fin_currencies WHERE company_id = {$co} LIMIT 1");
if ($cur === '') { $cur = 'SDG'; }

$r1 = $db->query("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                    amount, currency, source_doc_type, source_doc_id, created_by)
                  VALUES ({$co}, 'supplier', 1, 'fuel', 'debit', 99, '{$cur}', NULL, NULL, 0)");
$okReject = ($r1 === false);
echo '  ' . ($okReject ? '✔ رُفض خصمٌ بلا مصدر — ' . mb_substr($db->error, 0, 60)
                       : '✘ **مرَّ خصمٌ بلا مصدر — القيدُ لا يعمل**') . "\n";

$r2 = $db->query("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                    amount, currency, source_doc_type, source_doc_id, created_by)
                  VALUES ({$co}, 'supplier', 1, 'parts', 'debit', 99, '{$cur}', 'proc_issue', 7, 0)");
$okAccept = ($r2 !== false);
echo '  ' . ($okAccept ? '✔ وقُبل بمصدرِه — القيدُ لا يمنع الصحيح'
                       : '✘ رُفض الصحيحُ — القيدُ أوسعُ من معياره: ' . mb_substr($db->error, 0, 60)) . "\n";

$r3 = $db->query("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                    amount, currency, source_doc_type, source_doc_id, created_by)
                  VALUES ({$co}, 'supplier', 1, 'hours', 'credit', 99, '{$cur}', NULL, NULL, 0)");
$okCredit = ($r3 !== false);
echo '  ' . ($okCredit ? '✔ والدائنُ بلا مصدرٍ يمرُّ — الاستحقاقُ لا يُطالَب بمستند'
                       : '✘ رُفض الدائنُ — القيدُ تجاوز اتجاهَه: ' . mb_substr($db->error, 0, 60)) . "\n";

$r4 = $db->query("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                    amount, currency, source_doc_type, source_doc_id, created_by)
                  VALUES ({$co}, 'supplier', 1, 'advance', 'debit', 99, '{$cur}', 'pending_source', NULL, 0)");
$okPending = ($r4 !== false);
echo '  ' . ($okPending ? '✔ و`pending_source` بابٌ مُعلَنٌ يمرُّ — المُعلَنُ يُطارَد والصامتُ لا'
                        : '✘ رُفض pending_source — وهو بابٌ مشروع: ' . mb_substr($db->error, 0, 60)) . "\n";
$db->rollback();
echo "  ○ تُراجِع الجسُّ — لا صفَّ بقي\n";

/* ══ ④ الحصيلة ═══════════════════════════════════════════════════════════ */
$live = (int) m11_one($db, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_dues'");
echo "\n── الحصيلة\n";
echo "  قيودُ CHECK على fin_dues: {$live}\n";
$ok = $okReject && $okAccept && $okCredit && $okPending;
echo $ok ? "\n✅ M-11 يحرس فعلًا — وسبعةُ مواضعَ صار إعلانُها صادقًا.\n"
         : "\n⚠ راجِع المخرجاتِ أعلاه — القيدُ لا يميّز في أحدِ الاتجاهات\n";
exit($ok ? 0 : 1);
