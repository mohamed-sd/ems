<?php
/**
 * 2027_02_14 — `ck_collection_bank_ref`: كلُّ تحصيلٍ بمرجعٍ بنكيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ آخرُ قاعدةِ عملٍ ماليةٍ بقيت مفقودةً من إعادةِ بناءِ 2026-08-03:
 *     `((direction <> 'collection') OR (bank_ref IS NOT NULL AND bank_ref <> ''))`
 *   أي: **لا قبضَ عميلٍ بلا مرجعٍ بنكيّ** — مالٌ يدخل بلا أثرٍ في بنكٍ لا يُطابَق.
 *   ولم تُضَف في هجرةِ `2027_02_09` لأن لها 26 مخالفًا حيًّا.
 *
 * ◆ **والمخالفونَ ليسوا بيانةً ماليةً — بل مخلَّفاتُ فاحص**، مقيسٌ بأربعةِ شواهد:
 *     · `payment_no` ينتهي بـ`-LEAK` في الستةِ والعشرينَ كلِّها
 *     · النمطُ `M05T<pid>-LEAK` — وسمُ `tests/collection_control_test.php`
 *     · نقدًا 50.00 دولارًا، أُنشئت 2026-08-05/06 في دقائقَ متقاربة
 *     · **صفرُ مبلغٍ مخصَّصٍ · صفرُ حدثٍ منشورٍ · صفرُ تخصيصٍ يشير إليها**
 *   فهي صفوفٌ صنعها فاحصٌ ليُثبت ثغرةً ثم لم يكنسها — لا مالٌ قُبض.
 *
 * ◆ **ولذلك تُحذف ولا تُوسَم موروثًا.** ونمطُ `legacy_no_ref` وُضع للبيانةِ
 *   **الحقيقيةِ** الناقصةِ سندَها (كما في `M-11` و`INJ-0036`): يُبقيها مرئيةً
 *   لتُطارَد. ووسمُ مخلَّفاتِ فاحصٍ موروثًا يُدخِلها في تقاريرِ المطاردةِ إلى
 *   الأبد ويُقرأ نقصًا في بيانةِ الشركة — وهو كذبٌ بشكلِ صدق.
 *   والقيمةُ تُطبَع قبلَ الحذفِ في مخرَجِ الهجرةِ فيبقى ما حُذف مُعلَنًا.
 *
 * ◆ ثم يُفعَّل الحارسُ فيمنع عودتَها بنيويًّا — ويُصلَح الفاحصُ في التزامٍ آخرَ
 *   فيكنس ما يصنع.
 *
 * ◆ مُتحمِّلٌ للتكرار · ويُجَسُّ القيدُ في الاتجاهين.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$CLAUSE = "((`direction` <> 'collection') or ((`bank_ref` is not null) and (`bank_ref` <> '')))";
$BAD = "NOT {$CLAUSE}";

echo "══ `ck_collection_bank_ref` — لا قبضَ بلا مرجعٍ بنكيّ ══\n";

/* ── ① مَن المخالفون؟ يُعلَنون قبل أيِّ فعل ─────────────────────────────── */
$n = (int) $db->query("SELECT COUNT(*) FROM fin_payments WHERE {$BAD}")->fetch_row()[0];
echo "  مخالفون: {$n}\n";
if ($n > 0) {
    $litter = 0; $real = 0;
    $r = $db->query("SELECT id, payment_no, method, amount, currency,
                            COALESCE(allocated_amount,0) alloc, COALESCE(event_id,0) ev, created_at
                       FROM fin_payments WHERE {$BAD} ORDER BY id");
    $rows = array();
    while ($x = $r->fetch_assoc()) { $rows[] = $x; }
    foreach ($rows as $x) {
        $isLitter = (substr((string) $x['payment_no'], -5) === '-LEAK')
                 && ((float) $x['alloc'] == 0.0) && ((int) $x['ev'] === 0);
        if ($isLitter) { $litter++; } else { $real++; }
    }
    echo "  منها مخلَّفاتُ فاحصٍ (وسمُ -LEAK · صفرُ تخصيصٍ · صفرُ حدث): {$litter}\n";
    echo "  ومنها بيانةٌ حقيقيةٌ ناقصةُ السند: {$real}\n";

    /* ما حُذف يُطبَع قبلَ حذفِه — فيبقى مُعلَنًا في سجلِّ الهجرة */
    if ($litter > 0) {
        echo "  ── المحذوفُ (مخلَّفاتٌ لا مال):\n";
        foreach ($rows as $x) {
            if (substr((string) $x['payment_no'], -5) !== '-LEAK') { continue; }
            if ((float) $x['alloc'] != 0.0 || (int) $x['ev'] !== 0) { continue; }
            echo '      #' . str_pad($x['id'], 7) . str_pad($x['payment_no'], 22)
               . $x['method'] . ' ' . $x['amount'] . ' ' . $x['currency'] . ' · ' . $x['created_at'] . "\n";
        }
        $db->query("DELETE FROM fin_collection_allocations WHERE payment_id IN
                      (SELECT id FROM (SELECT id FROM fin_payments
                         WHERE {$BAD} AND payment_no LIKE '%-LEAK'
                           AND COALESCE(allocated_amount,0) = 0 AND COALESCE(event_id,0) = 0) x)");
        $db->query("DELETE FROM fin_payments WHERE {$BAD} AND payment_no LIKE '%-LEAK'
                      AND COALESCE(allocated_amount,0) = 0 AND COALESCE(event_id,0) = 0");
        echo '  ✔ حُذف: ' . $db->affected_rows . "\n";
    }

    /* البيانةُ الحقيقيةُ — إن وُجدت — تُعلَن ولا تُمَسّ */
    $left = (int) $db->query("SELECT COUNT(*) FROM fin_payments WHERE {$BAD}")->fetch_row()[0];
    if ($left > 0) {
        echo "  ⚠ بقي {$left} صفًّا **بيانةً حقيقيةً** — لا تُمَسُّ ولا يُضاف القيدُ فوقها\n";
        $r = $db->query("SELECT id, payment_no, amount, currency FROM fin_payments WHERE {$BAD} LIMIT 10");
        while ($x = $r->fetch_assoc()) { echo '      #' . $x['id'] . ' ' . $x['payment_no']
            . ' ' . $x['amount'] . ' ' . $x['currency'] . "\n"; }
        echo "\n⚠ القيدُ لم يُضَف — قرارُ مالكٍ في هذه الصفوف.\n";
        exit(0);
    }
}

/* ── ② القيد ─────────────────────────────────────────────────────────────── */
$has = (int) $db->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                          WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='fin_payments'
                            AND CONSTRAINT_NAME='ck_collection_bank_ref'")->fetch_row()[0];
if ($has) { echo "  ○ القيدُ قائمٌ سلفًا\n"; }
else {
    if ($db->query("ALTER TABLE fin_payments ADD CONSTRAINT ck_collection_bank_ref
                      CHECK {$CLAUSE}") === false) {
        fwrite(STDERR, '✘ ' . $db->error . "\n"); exit(1);
    }
    echo "  ✔ أُضيف `ck_collection_bank_ref`\n";
}

/* ── ③ الجسُّ في الاتجاهين ─────────────────────────────────────────────── */
echo "\n── جسٌّ (ثم تراجُع)\n";
$db->begin_transaction();
$co = (int) $db->query('SELECT company_id FROM fin_payments LIMIT 1')->fetch_row()[0];
$cur = (string) $db->query("SELECT code FROM fin_currencies WHERE company_id={$co} LIMIT 1")->fetch_row()[0];
$b = $db->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
                   method, bank_ref, amount, currency, state, created_by)
                 VALUES ({$co}, 'PROBE-CBR-1', 'collection', 'client', 1, 'cash', NULL, 10,
                         '{$cur}', 'draft', 0)");
echo '  ' . ($b === false ? '✔ رُفض تحصيلٌ بلا مرجعٍ بنكيّ — ' . mb_substr($db->error, 0, 52)
                          : '✘ **مرَّ تحصيلٌ بلا مرجع**') . "\n";
$g = $db->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
                   method, bank_ref, amount, currency, state, created_by)
                 VALUES ({$co}, 'PROBE-CBR-2', 'collection', 'client', 1, 'bank_transfer', 'BNK-1', 10,
                         '{$cur}', 'draft', 0)");
echo '  ' . ($g !== false ? '✔ وقُبل بمرجعِه — القيدُ لا يمنع الصحيح'
                          : '✘ رُفض الصحيحُ: ' . mb_substr($db->error, 0, 52)) . "\n";
$o = $db->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
                   method, bank_ref, amount, currency, state, created_by)
                 VALUES ({$co}, 'PROBE-CBR-3', 'payout', 'supplier', 1, 'cash', NULL, 10,
                         '{$cur}', 'draft', 0)");
echo '  ' . ($o !== false ? '✔ والدفعُ الخارجُ لا يُطالَب بمرجعٍ بنكيّ — القيدُ محصورٌ باتجاهه'
                          : '✘ رُفض الدفعُ الخارج — القيدُ تجاوز اتجاهَه') . "\n";
$db->rollback();
echo "  ○ تُراجِع الجسُّ\n";

$total = (int) $db->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA=DATABASE()")->fetch_row()[0];
echo "\n  قيودُ CHECK الحيّة: {$total}\n";
$ok = ($b === false && $g !== false && $o !== false);
echo "\n" . ($ok ? "✅ لا قبضَ يدخل بلا أثرٍ بنكيٍّ — بنيويًّا.\n" : "⚠ راجِع أعلاه\n");
exit($ok ? 0 : 1);
