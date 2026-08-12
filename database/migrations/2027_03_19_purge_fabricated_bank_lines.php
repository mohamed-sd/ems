<?php
/**
 * 2027_03_19 — أسطرُ كشفٍ بنكيٍّ ملفَّقةٌ: تُزال ويُغلَق بابُها بقيدَين
 * ═══════════════════════════════════════════════════════════════════════════
 * كُشف بقياسِ بابِ ٤-٩ من وثيقةِ OWF-01 ثم تُحقِّق بالقياسِ المباشر. و`fin_bank_
 * statement_lines` فيه 20 صفًّا: **اثنان حقيقيّان و18 من مولِّدٍ واحد**.
 *
 * ── والتلفيقُ مُثبَتٌ بثلاثِ بصماتٍ مجتمعةٍ، لا بظنٍّ ولا بقائمةِ معرِّفات ──────
 * ① **متسلسلٌ حسابيٌّ بخطوةِ 137** من 524.50 إلى 2853.50 — ثمانيةَ عشرَ حدًّا
 *    بلا استثناء (قِيس: 17 خطوةً متساويةً · و18 صفًّا داخلَ المتسلسلِ · و**صفرٌ
 *    خارجَه**). ومعاملاتُ بنكٍ لا تأتي بخطوةٍ ثابتةٍ — هذه بصمةُ حاسبٍ لا صرّاف.
 * ② و**ذاتُ القيمِ** كانت في قراءاتِ مؤشرِ السعرِ الملفَّقةِ التي أُزيلت في هجرةِ
 *    2027_03_17 (798.5 · 1483.5 · 2168.5 · 2853.5) — فالمولِّدُ واحدٌ.
 * ③ و15 منها تحمل «· UAT-2026» في الوصفِ، والثلاثةُ الباقيةُ **بوصفٍ خاوٍ**
 *    (قوقعةٌ فارغةٌ — نمطٌ معروفٌ في هذا المولِّد) وكلُّها `reconciled=1`.
 *
 * ── وأثرُها ليس تجميليًّا ─────────────────────────────────────────────────────
 * · **سندان مُطابَقان مرتين**: السندُ 10 يطالبه السطرُ الحقيقيُّ 7 والملفَّقُ 18 ·
 *   والسندُ 11 يطالبه 8 و19. و`tests/bank_reconciliation_test.php` يشترط نصًّا
 *   «لا سندٌ يُطابَق مرتين» — **فالقاعدةُ كانت تخالف فاحصَها**.
 * · و**12 مرجعَ سندٍ يتيمٌ**: مدفوعاتٌ 3·4·5·6·8·9·13·14·15·16·18·19 لا وجودَ
 *   لها في `fin_payments` إطلاقًا — ولا FK يمنع ذلك.
 *
 * ── ولا يُكتفى بالإزالة ──────────────────────────────────────────────────────
 * تُزال الصفوفُ **ويُغلَق البابُ**: مفتاحٌ أجنبيٌّ يمنع المرجعَ اليتيمَ، ومفتاحٌ
 * فريدٌ يمنع مطابقةَ سندٍ مرتين (والنُّلُّ يتكرَّر في UNIQUE فالسطرُ غيرُ المطابَقِ
 * لا يُمنع). فإصلاحٌ بلا قيدٍ يعود بعد جولةٍ.
 *
 * ◆ والسطرانِ الحقيقيّانِ (7 · 8 — BANKLINE-COLLECT و BANKLINE-PAYS3) **لا
 *   يُمَسّان**، ولا تُلمَس حالةُ سندٍ: المدفوعتان 10 و11 نظيرا السطرين الصحيحين،
 *   فإرجاعُهما إلى `executed` يترك السطرين الصحيحين بلا نظير.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$T = 'fin_bank_statement_lines';

/** استعلامُ عددٍ **بفحصِ المُرجَع** — فالفشلُ يعود false صامتًا ويُقرأ صفرًا */
$one = function ($sql) use ($db) {
    $r = $db->query($sql);
    if ($r === false) { fwrite(STDERR, 'استعلامٌ فشل: ' . $db->error . "\n"); return null; }
    $x = $r->fetch_row();
    return $x === null ? null : $x[0];
};

/* ── ① البصمةُ: متسلسلٌ بخطوةِ 137 من 524.50 ─────────────────────────────────
     ولا تُكتب قائمةُ معرِّفاتٍ: القاعدةُ تُوصَف فتصمد لو أُعيد المولِّدُ بمعرِّفاتٍ
     أخرى. و`(amount-524.50)/137` عددٌ صحيحٌ ⟺ الصفُّ في المتسلسل. */
$SEQ = "(amount - 524.50) >= 0 AND ROUND((amount - 524.50)/137, 6) = ROUND((amount - 524.50)/137, 0)";

$total = $one("SELECT COUNT(*) FROM `{$T}`");
$inSeq = $one("SELECT COUNT(*) FROM `{$T}` WHERE {$SEQ}");
$outSeq = $one("SELECT COUNT(*) FROM `{$T}` WHERE NOT ({$SEQ})");
if ($total === null || $inSeq === null) { exit(1); }
echo "── ① الصفوفُ: {$total} · في المتسلسلِ: {$inSeq} · خارجَه: {$outSeq}\n";
if ((int) $inSeq === 0) { echo "── لا شيءَ يُزال — الهجرةُ عاطلةٌ\n"; }

/* ◆ **جسُّ تمييزٍ إلزاميٌّ**: لو شملت البصمةُ صفًّا حقيقيًّا واحدًا تُوقَف الهجرة.
     السطرانِ الحقيقيّانِ يُعرفان بوصفِهما لا بمعرِّفِهما. */
$realHit = $one("SELECT COUNT(*) FROM `{$T}`
                  WHERE {$SEQ} AND (description LIKE 'BANKLINE-%')");
if ($realHit === null) { exit(1); }
echo "── ② جسُّ تمييزٍ: صفوفٌ حقيقيةٌ (BANKLINE-%) داخلَ البصمة: {$realHit}\n";
if ((int) $realHit !== 0) {
    fwrite(STDERR, "البصمةُ تشمل صفًّا حقيقيًّا — تُوقَف الهجرةُ ولا يُزال شيء.\n");
    exit(1);
}

/* ── ③ الأثرُ المقيسُ قبل الإزالة: سنداتٌ مكرَّرةٌ ومراجعُ يتيمة ──────────────── */
$dupBefore = $one("SELECT COUNT(*) FROM (SELECT matched_payment_id FROM `{$T}`
                    WHERE matched_payment_id IS NOT NULL
                    GROUP BY matched_payment_id HAVING COUNT(*) > 1) x");
$orphBefore = $one("SELECT COUNT(*) FROM `{$T}` l
                     WHERE l.matched_payment_id IS NOT NULL
                       AND NOT EXISTS (SELECT 1 FROM fin_payments p WHERE p.id = l.matched_payment_id)");
echo "── ③ قبل: سنداتٌ مطابَقةٌ مرتين {$dupBefore} · مراجعُ يتيمةٌ {$orphBefore}\n";

/* ── ④ الإزالةُ بفحصِ المُرجَعِ والعدِّ قبلَ وبعد ────────────────────────────── */
if ((int) $inSeq > 0) {
    $ok = $db->query("DELETE FROM `{$T}` WHERE {$SEQ}");
    if ($ok === false) { fwrite(STDERR, 'حذفٌ فشل: ' . $db->error . "\n"); exit(1); }
    $gone = $db->affected_rows;
    $left = $one("SELECT COUNT(*) FROM `{$T}` WHERE {$SEQ}");
    echo "── ④ حُذف {$gone} صفًّا · باقٍ في البصمة: {$left}\n";
    if ((int) $gone !== (int) $inSeq || (int) $left !== 0) {
        fwrite(STDERR, "خلافُ المتوقَّع — راجِع قبل الالتزام\n"); exit(1);
    }
}
$after = $one("SELECT COUNT(*) FROM `{$T}`");
$dupAfter = $one("SELECT COUNT(*) FROM (SELECT matched_payment_id FROM `{$T}`
                   WHERE matched_payment_id IS NOT NULL
                   GROUP BY matched_payment_id HAVING COUNT(*) > 1) x");
$orphAfter = $one("SELECT COUNT(*) FROM `{$T}` l
                    WHERE l.matched_payment_id IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM fin_payments p WHERE p.id = l.matched_payment_id)");
echo "── ⑤ بعد: صفوفٌ {$after} · مكرَّرٌ {$dupAfter} · يتيمٌ {$orphAfter}\n";
if ((int) $dupAfter !== 0 || (int) $orphAfter !== 0) {
    fwrite(STDERR, "بقي مكرَّرٌ أو يتيمٌ — لا تُضاف القيودُ فوق فسادٍ قائم\n"); exit(1);
}

/* ── ⑥ القيدان: لا يعود اليتيمُ ولا الازدواجُ ────────────────────────────────
     ◆ ولا يُكتفى بإزالةٍ: إصلاحٌ بلا قيدٍ يعود بعد جولة. */
$has = function ($name) use ($db, $T) {
    $r = $db->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$T}'
                        AND CONSTRAINT_NAME = '{$name}'");
    return $r && $r->num_rows > 0;
};
if ($has('uq_fin_bsl_payment')) {
    echo "── ⑥ المفتاحُ الفريدُ موجودٌ سلفًا\n";
} else {
    /* النُّلُّ يتكرَّر في UNIQUE — فالسطرُ غيرُ المطابَقِ لا يُمنع، والمطابَقُ لا يُزدوَج */
    $ok = $db->query("ALTER TABLE `{$T}`
        ADD CONSTRAINT uq_fin_bsl_payment UNIQUE (matched_payment_id)");
    if ($ok === false) { fwrite(STDERR, '⑥ فشل: ' . $db->error . "\n"); exit(1); }
    echo "── ⑥ أُضيف uq_fin_bsl_payment — لا سندٌ يُطابَق مرتين\n";
}
if ($has('fk_fin_bsl_payment')) {
    echo "── ⑦ المفتاحُ الأجنبيُّ موجودٌ سلفًا\n";
} else {
    /* SET NULL لا CASCADE: حذفُ سندٍ لا يُعدم سطرَ كشفٍ بنكيٍّ — السطرُ واقعةُ
       بنكٍ قائمةٌ بذاتها، وفقدانُ نظيرِه يجعله غيرَ مطابَقٍ لا معدومًا. */
    $ok = $db->query("ALTER TABLE `{$T}`
        ADD CONSTRAINT fk_fin_bsl_payment FOREIGN KEY (matched_payment_id)
            REFERENCES fin_payments(id) ON DELETE SET NULL ON UPDATE CASCADE");
    if ($ok === false) { fwrite(STDERR, '⑦ فشل: ' . $db->error . "\n"); exit(1); }
    echo "── ⑦ أُضيف fk_fin_bsl_payment (SET NULL) — لا مرجعَ سندٍ يتيمٌ\n";
}

/* ── ⑧ جسٌّ: القيدان يردّان فعلًا — وأيُّ قيدٍ لا يردُّ زخرفة ─────────────────── */
$acct = $one("SELECT id FROM fin_bank_accounts WHERE company_id = 4 LIMIT 1");
/* ◆ **سندٌ غيرُ مطابَقٍ** لا أوّلُ سندٍ: أوّلُ جسٍّ لي أخذ `LIMIT 1` فوقع على
     سندٍ مطابَقٍ سلفًا فردَّه الفريدُ (1062)، فسقط الفرعُ الموجبُ — وشرطُ الخروجِ
     لم يكن يشترطه، فقيدٌ يردُّ **كلَّ شيءٍ** كان يمرُّ. */
$pay  = $one("SELECT p.id FROM fin_payments p
              WHERE NOT EXISTS (SELECT 1 FROM `{$T}` l WHERE l.matched_payment_id = p.id)
              LIMIT 1");
if ($acct === null || $pay === null) {
    echo "── ⑧ لا حسابَ أو لا سندَ — الجسُّ لا يُشغَّل ويُعلَن\n";
} else {
    $ins = function ($pid) use ($db, $T, $acct) {
        $v = $pid === null ? 'NULL' : (int) $pid;
        $db->query("INSERT INTO `{$T}`
            (company_id, bank_account_id, txn_date, description, direction, amount,
             matched_payment_id, reconciled, created_at)
            VALUES (4, " . (int) $acct . ", '2029-01-01', 'MIGPRB-bank', 'deposit', 1.00,
                    {$v}, 0, NOW())");
        return $db->errno;
    };
    /* الموجب: سندٌ حقيقيٌّ غيرُ مطابَقٍ يمرّ */
    $e1 = $ins((int) $pay);
    $firstId = $e1 === 0 ? (int) $db->insert_id : 0;
    echo '── ⑧ سندٌ حقيقيٌّ غيرُ مطابَقٍ يمرُّ: ' . ($e1 === 0 ? '✔' : '✘ خطأ ' . $e1) . "\n";
    /* السالب ①: السندُ نفسُه مرةً ثانيةً ⇒ يُردُّ بالفريد (1062) */
    $e2 = $ins((int) $pay);
    $dupBlocked = ($e2 === 1062 || $e2 === 1586);
    echo '── ⑧ ومطابقتُه مرةً ثانيةً تُردُّ: ' . ($dupBlocked ? '✔ خطأ ' . $e2 : '✘ مرَّ (خطأ ' . $e2 . ')') . "\n";
    /* السالب ②: سندٌ غيرُ موجودٍ ⇒ يُردُّ بالأجنبيّ (1452) */
    $ghost = (int) $one("SELECT COALESCE(MAX(id),0) + 99999 FROM fin_payments");
    $e3 = $ins($ghost);
    $orphBlocked = ($e3 === 1452 || $e3 === 1216);
    echo '── ⑧ وسندٌ غيرُ موجودٍ يُردُّ: ' . ($orphBlocked ? '✔ خطأ ' . $e3 : '✘ مرَّ (خطأ ' . $e3 . ')') . "\n";
    /* رفعُ الزرع */
    $db->query("DELETE FROM `{$T}` WHERE description = 'MIGPRB-bank'");
    $swept = $db->affected_rows;
    $leftProbe = $one("SELECT COUNT(*) FROM `{$T}` WHERE description = 'MIGPRB-bank'");
    echo "── ⑨ رُفع الزرعُ: {$swept} صفًّا · باقٍ {$leftProbe}\n";
    /* ◆ **والفرعُ الموجبُ شرطٌ**: بغيرِه يمرُّ قيدٌ يردُّ كلَّ شيءٍ — وهو منعٌ لا تمييز. */
    if ($e1 !== 0 || !$dupBlocked || !$orphBlocked || (int) $leftProbe !== 0) {
        fwrite(STDERR, "القيودُ لا تردُّ أو بقي أثرُ جسٍّ — لم يكتمل\n"); exit(1);
    }
}

echo "\n✅ أُزيل الملفَّقُ ببصمتِه، ولا يعود: سندٌ لا يُطابَق مرتين ومرجعٌ لا يتيمَ.\n";
exit(0);
