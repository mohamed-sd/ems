<?php
/**
 * tests/financing_pay_and_shares_test.php — منفِّذٌ واحدٌ للسداد · وحصةٌ بلا تراكب
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0046 · INJ-0045
 *
 * · 0046: «سدادُ القسط الأخير ينقل العمليةَ إلى `'settled'` **ويثبّت سعرَ الصرف
 *   والمعادلَ في الصف**؛ ونتيجةُ الرصيد من الشاشة **تطابق** نتيجةَ الخدمة».
 * · 0045: «بعد نقل حصةٍ: `SUM(percent)` للأصل **في يوم النقل وفي اليوم التالي**
 *   = ١٠٠ بالضبط؛ **وصفرُ فترتين متراكبتين** لنفس (الأصل × الممول)».
 *
 * ── العلّتان ──────────────────────────────────────────────────────────────
 * · الشاشةُ كانت تسدّد بـSQL خامٍّ ولا تنادي `FinancingService::payInstallment`،
 *   فلا `fx_rate_at_payment` ولا `functional_equivalent` ولا انتقالَ إلى
 *   `settled`؛ وحسابُ الرصيدِ مختلفٌ بين المسارين — **رقمان لحقيقةٍ واحدة**.
 * · وحصةُ البائعِ كانت تُغلق بـ`CURDATE()` والجديدةُ تُفتح بـ`CURDATE()`،
 *   ومعيارُ القراءةِ `valid_to >= CURDATE()` — **فالثلاثةُ ساريةٌ في يومِ النقل**
 *   والمجموعُ ضِعف. والاصطلاحُ المحسوم: `valid_to` **شامل** ⇒ الإغلاقُ أمس.
 *
 * ◆ ويبذر الشاهدُ عمليةً وحصةً بوسمٍ عائليٍّ ثابت، ويكنس الأبناءَ قبل الآباء،
 *   ويفحص مُرجَعَ كلِّ حذف. ولا يمسُّ صفًّا إنتاجيًّا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/app/Services/Financing/FinancingService.php';

$conn = $GLOBALS['conn'];
$CO   = 4;
$TAG  = 'FINPAY-TEST-FAMILY';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ منفِّذٌ واحدٌ للسداد · وحصةٌ بلا تراكب');

/* ── ① INJ-0046 · الشاشةُ تنادي المنفِّذَ المعتمد ───────────────────────── */
$say("\n── ① «لكل بيانٍ موضعٌ واحدٌ يُنشأ فيه ويُعدَّل» (CS-05)");
$scr = (string) @file_get_contents($ROOT . '/Financing/installments.php');
$ok(strpos($scr, 'FinancingService::payInstallment') !== false,
    'الشاشةُ تنادي `payInstallment` — المنفّذَ المعتمد');
$ok(!preg_match("~UPDATE financing_installments SET state='paid'~", $scr),
    'ولم تعد تسدّد بـSQL خامٍّ خاصٍّ بها');
$ok(strpos($scr, 'ems_fx_rate') !== false,
    'وسعرُ الصرفِ من `includes/fx.php` — «الماليةُ تُسعّر يوميًّا» ولا يُخترع سعر');

/* بذرُ عمليةٍ بقسطٍ واحدٍ فيُصير سدادُه «القسطَ الأخير» */
$sweepFin = function () use ($conn, $TAG, $CO) {
    $ids = array();
    $r = $conn->query("SELECT op_id FROM financing_operations WHERE company_id={$CO} AND op_code LIKE '%{$TAG}%'");
    while ($r && ($x = $r->fetch_row())) { $ids[] = (int) $x[0]; }
    foreach ($ids as $id) { $conn->query("DELETE FROM financing_installments WHERE op_id={$id}"); }
    $d = $conn->query("DELETE FROM financing_operations WHERE company_id={$CO} AND op_code LIKE '%{$TAG}%'");
    if ($d === false) { return -1; }
    $r = $conn->query("SELECT COUNT(*) FROM financing_operations WHERE company_id={$CO} AND op_code LIKE '%{$TAG}%'");
    return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
};
$ok($sweepFin() === 0, 'الكنسُ القبليُّ نظيفٌ بالعائلة');

/* الأعمدةُ الإلزاميةُ تُقرأ حيًّا فلا يُخمَّن مخطَّط */
$cols = array();
$r = $conn->query('SHOW COLUMNS FROM financing_operations');
while ($r && ($x = $r->fetch_assoc())) { $cols[$x['Field']] = $x; }
$src = null;
$r = $conn->query("SELECT * FROM financing_operations WHERE company_id={$CO} ORDER BY op_id LIMIT 1");
if ($r) { $src = $r->fetch_assoc(); }
$ok($src !== null, 'عمليةُ تمويلٍ قائمةٌ تُنسخ بنيتُها');

$opId = 0;
if ($src) {
    $set = array(); $skip = array('op_id', 'created_at', 'updated_at');
    foreach ($src as $k => $v) {
        if (in_array($k, $skip, true)) { continue; }
        if ($k === 'op_code')             { $v = 'FIN-' . $TAG; }
        if ($k === 'capital')             { $v = 1000; }
        if ($k === 'outstanding_balance') { $v = 1000; }
        if ($k === 'state')               { $v = 'active'; }
        $set[] = '`' . $k . '` = ' . ($v === null ? 'NULL' : "'" . $conn->real_escape_string((string) $v) . "'");
    }
    $ins = $conn->query('INSERT INTO financing_operations SET ' . implode(', ', $set));
    $opId = $ins ? (int) $conn->insert_id : 0;
    $ok($opId > 0, 'بُذرت عمليةُ تمويلٍ #' . $opId . ' برصيد 1000', $conn->error);
}
$instId = 0;
if ($opId > 0) {
    $ins = $conn->query("INSERT INTO financing_installments
            (company_id, op_id, seq_no, due_date, amount_principal, amount_profit,
             amount_total, currency, state, created_at)
          VALUES ({$CO}, {$opId}, 1, CURDATE(), 1000, 0, 1000, 'USD', 'due', NOW())");
    $instId = $ins ? (int) $conn->insert_id : 0;
    $ok($instId > 0, 'وقسطٌ واحدٌ #' . $instId . ' بقيمة 1000 — فسدادُه هو «القسطُ الأخير»', $conn->error);
}

if ($instId > 0) {
    $res = \App\Services\Financing\FinancingService::payInstallment(
        $conn, $CO, $opId, 1, date('Y-m-d'), 'REF-' . $TAG, 2.5, 2500.0);
    $ok(!empty($res['ok']), 'سُدّد عبر المنفّذِ المعتمد: ' . mb_substr((string) $res['reason'], 0, 70));
    $r = $conn->query("SELECT state, fx_rate_at_payment, functional_equivalent, payment_ref
                         FROM financing_installments WHERE inst_id={$instId}");
    $i = $r ? $r->fetch_assoc() : null;
    $ok($i && (string) $i['state'] === 'paid', 'والقسطُ صار `paid`');
    $ok($i && (float) $i['fx_rate_at_payment'] === 2.5,
        '«**ويثبّت سعرَ الصرف**»: ' . ($i ? $i['fx_rate_at_payment'] : '?'));
    $ok($i && abs((float) $i['functional_equivalent'] - 2500.0) < 0.005,
        '«**والمعادلَ في الصف**»: ' . ($i ? $i['functional_equivalent'] : '?'));
    $r = $conn->query("SELECT state, outstanding_balance FROM financing_operations WHERE op_id={$opId}");
    $o = $r ? $r->fetch_assoc() : null;
    $ok($o && abs((float) $o['outstanding_balance']) < 0.005,
        'والرصيدُ بلغ صفرًا: ' . ($o ? $o['outstanding_balance'] : '?'));
    $ok($o && (string) $o['state'] === 'settled',
        '«**سدادُ القسط الأخير ينقل العمليةَ إلى `settled`**» — نصُّ القبولِ حرفًا: «'
        . ($o ? $o['state'] : '?') . '»');
}
$ok($sweepFin() === 0, 'كُنست عائلةُ التمويل — الأبناءُ قبل الآباء');

/* ── ② INJ-0045 · حصةٌ تُنقل بلا فجوةٍ ولا تراكب ────────────────────────── */
$say("\n── ② «Σ = ١٠٠ في يوم النقل وفي اليوم التالي · وصفرُ تراكب»");
$dis = (string) @file_get_contents($ROOT . '/Financing/asset_disposal.php');
$ok(strpos($dis, 'DATE_SUB(CURDATE(), INTERVAL 1 DAY)') !== false,
    'الحصةُ القديمةُ تُغلق **أمسِ** لا اليوم — و`valid_to` شامل');

$sweepShr = function () use ($conn, $TAG, $CO) {
    $d = $conn->query("DELETE FROM asset_ownership_shares WHERE company_id={$CO} AND doc_ref LIKE '%{$TAG}%'");
    if ($d === false) { return -1; }
    $r = $conn->query("SELECT COUNT(*) FROM asset_ownership_shares WHERE company_id={$CO} AND doc_ref LIKE '%{$TAG}%'");
    return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
};
$ok($sweepShr() === 0, 'الكنسُ القبليُّ للحصصِ نظيف');

/* أصلٌ لا حصصَ له أصلًا — فالمجموعُ يُقاس على بذرِنا وحدَه */
$asset = 0;
$r = $conn->query("SELECT e.id FROM equipments e
                    WHERE e.company_id={$CO}
                      AND NOT EXISTS (SELECT 1 FROM asset_ownership_shares s
                                       WHERE s.asset_id=e.id AND s.asset_kind='equipment')
                    ORDER BY e.id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $asset = (int) $x[0]; }
$ents = array();
$r = $conn->query('SELECT entity_id FROM legal_entities ORDER BY entity_id LIMIT 2');
while ($r && ($x = $r->fetch_row())) { $ents[] = (int) $x[0]; }
$ok($asset > 0 && count($ents) === 2, 'أصلٌ بلا حصصٍ #' . $asset . ' وكيانان: ' . implode('·', $ents));

if ($asset > 0 && count($ents) === 2) {
    $doc = 'DOC-' . $TAG;
    $ins = $conn->query("INSERT INTO asset_ownership_shares
            (company_id, asset_id, asset_kind, financier_entity_id, percent,
             valid_from, doc_ref, recorded_percent, approved_percent, created_by)
          VALUES ({$CO}, {$asset}, 'equipment', {$ents[0]}, 100,
                  DATE_SUB(CURDATE(), INTERVAL 30 DAY), '{$doc}', 100, 100, 1)");
    $sid = $ins ? (int) $conn->insert_id : 0;
    $ok($sid > 0, 'حصةٌ كاملةٌ ١٠٠٪ للكيانِ الأول #' . $sid, $conn->error);

    /* النقلُ باصطلاحِ الشاشةِ المُصلَح: الإغلاقُ أمسِ والفتحُ اليوم */
    $conn->query("UPDATE asset_ownership_shares SET valid_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                   WHERE share_id = {$sid}");
    $conn->query("INSERT INTO asset_ownership_shares
            (company_id, asset_id, asset_kind, financier_entity_id, percent,
             valid_from, doc_ref, recorded_percent, approved_percent, created_by)
          VALUES ({$CO}, {$asset}, 'equipment', {$ents[0]}, 60, CURDATE(), '{$doc}', 60, 60, 1)");
    $ok3 = $conn->query("INSERT INTO asset_ownership_shares
            (company_id, asset_id, asset_kind, financier_entity_id, percent,
             valid_from, doc_ref, recorded_percent, approved_percent, created_by)
          VALUES ({$CO}, {$asset}, 'equipment', {$ents[1]}, 40, CURDATE(), '{$doc}', 40, 40, 1)");
    $ok($ok3 !== false, 'ونُقلت ٤٠٪ إلى الكيانِ الثاني (الباقي ٦٠٪)', $conn->error);

    $sumAt = function ($dateExpr) use ($conn, $asset, $CO) {
        $r = $conn->query("SELECT COALESCE(SUM(COALESCE(approved_percent, percent)),0) s
                             FROM asset_ownership_shares
                            WHERE company_id={$CO} AND asset_id={$asset} AND asset_kind='equipment'
                              AND valid_from <= {$dateExpr}
                              AND (valid_to IS NULL OR valid_to >= {$dateExpr})");
        return ($r && ($x = $r->fetch_row())) ? round((float) $x[0], 2) : -1.0;
    };
    $today = $sumAt('CURDATE()');
    $tomor = $sumAt('DATE_ADD(CURDATE(), INTERVAL 1 DAY)');
    $yest  = $sumAt('DATE_SUB(CURDATE(), INTERVAL 1 DAY)');
    $ok(abs($today - 100.0) < 0.005, '«Σ في **يوم النقل** = ١٠٠ بالضبط»: ' . $today);
    $ok(abs($tomor - 100.0) < 0.005, '«وفي **اليوم التالي** = ١٠٠ بالضبط»: ' . $tomor);
    $ok(abs($yest - 100.0) < 0.005, 'وفي اليومِ السابق = ١٠٠ (لا فجوةَ قبلَ النقل): ' . $yest);

    $r = $conn->query("SELECT COUNT(*) FROM asset_ownership_shares a
            JOIN asset_ownership_shares b
              ON b.company_id=a.company_id AND b.asset_kind=a.asset_kind AND b.asset_id=a.asset_id
             AND b.financier_entity_id=a.financier_entity_id AND b.share_id>a.share_id
             AND a.valid_from <= COALESCE(b.valid_to,'9999-12-31')
             AND b.valid_from <= COALESCE(a.valid_to,'9999-12-31')
           WHERE a.asset_id={$asset} AND a.asset_kind='equipment'");
    $ovl = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
    $ok($ovl === 0, '«**وصفرُ فترتين متراكبتين** لنفس (الأصل × الممول)»: ' . $ovl);

    /* والقادحُ يردُّ التراكبَ ولو كُتب من مسارٍ آخر */
    $bad = $conn->query("INSERT INTO asset_ownership_shares
            (company_id, asset_id, asset_kind, financier_entity_id, percent,
             valid_from, doc_ref, recorded_percent, approved_percent, created_by)
          VALUES ({$CO}, {$asset}, 'equipment', {$ents[1]}, 5, CURDATE(), '{$doc}', 5, 5, 1)");
    $ok($bad === false && mb_strpos($conn->error, 'SHR-409') !== false,
        'وقادحُ القاعدةِ يردُّ تراكبًا من أيِّ كاتب: ' . mb_substr($conn->error, 0, 70));
}
$ok($sweepShr() === 0, 'كُنست عائلةُ الحصص (مُرجَعُ الحذفِ مفحوص)');

$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL > 0 ? 1 : 0);
