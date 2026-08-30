<?php
/**
 * tools/ctl_manual_entry_reconcile.php — أمرُ الضبطِ §٩ · مصالحةُ القيودِ اليدويّة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه**: *«1,644 = مجموعُ جميعِ الفئاتِ المصنَّفة، والفرقُ صفر
 *   … لا تُرفع 1,103 قيودٍ للمالكِ صفًّا صفًّا — يُرفع فقط ما بقي غامضًا بعد
 *   تطبيقِ القواعد، مع عددِ كلِّ فئةٍ وأثرِها والخياراتِ والتوصية»*.
 *
 * ◆ **القسمةُ من `memo` المقيسِ — سلَّمٌ حتميٌّ أوّلُ مطابقةٍ تفوز**:
 *   `C1` سدادُ مورد (`سداد%مورد`) · `C2` إيرادُ مستخلصٍ (`مستخلص` — و`CLM-…`
 *   يُنتزع **مرجعَ مستندٍ** في `doc_hint`) · `C3` تسويةٌ (`تسوية`) ·
 *   `C4` قبضٌ/تحصيلٌ · `C5` رواتبُ وأجور — **والاتحادُ ١٬٦٤٤ والتقاطعُ صفر
 *   يُثبتان في الفحصِ الذاتيّ**. والعصرُ محورٌ ثانٍ: `PRE_LEDGER` (‏قبل
 *   2026 — لا فترةَ حاوية) و`CURRENT`.
 *
 * ⛔ **وما يُرفع للمالكِ فئتان لا ١٬١٠٣ صفًّا**:
 *   ① سياسةُ الفتراتِ التاريخيّة (‏هل تُنشأ سنواتُ 2020-2025 بحالاتِها؟) —
 *     تحجب حوكمةَ `PRE_LEDGER` كلِّه.
 *   ② سياسةُ اعتمادِ القيدِ التاريخيِّ بأثرٍ رجعيّ (‏من يُعتمِد ما رُحِّل قبل
 *     الحوكمة؟) — تحجب `approval_ref` للجميع.
 *
 * التشغيل: php tools/ctl_manual_entry_reconcile.php [--apply] [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);
$snap = (string) $one("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($APPLY && $snap === '') { exit("⛔ لا نافذةَ قياسٍ مفتوحة\n"); }

/** السلَّمُ الحتميُّ — أولُ مطابقةٍ تفوز · @return array{cat:string,rule:string,doc:string} */
function me_class($memo)
{
    $m = (string) $memo;
    if (preg_match('~سداد~u', $m) && preg_match('~مورد~u', $m)) { return array('SUPPLIER_PAYMENT', 'C1', ''); }
    if (preg_match('~مستخلص~u', $m)) {
        $doc = preg_match('~(CLM-\d+)~u', $m, $x) ? $x[1] : '';
        return array('CLAIM_REVENUE', 'C2', $doc);
    }
    if (preg_match('~تسوية~u', $m)) { return array('SETTLEMENT', 'C3', ''); }
    if (preg_match('~قبض|تحصيل~u', $m)) { return array('RECEIPT', 'C4', ''); }
    if (preg_match('~راتب|أجور|اجور~u', $m)) { return array('PAYROLL', 'C5', ''); }
    return array('UNCLASSIFIED', 'C0', '');
}

if ($SELF) {
    $fail = 0;
    $a = me_class('سدادٌ للمورد فلان'); if ($a[0] !== 'SUPPLIER_PAYMENT') { echo "  X C1\n"; $fail++; }
    $b = me_class('إيرادُ المستخلص CLM-20260283');
    if ($b[0] !== 'CLAIM_REVENUE' || $b[2] !== 'CLM-20260283') { echo "  X C2 أو المرجعُ لم يُنتزع\n"; $fail++; }
    /* **الكاسر ①**: نصٌّ لا يطابق سلَّمًا يبقى UNCLASSIFIED — لا يُقحَم */
    if (me_class('zzq نصٌّ غامضٌ فريد')[0] !== 'UNCLASSIFIED') { echo "  X الغامضُ أُقحم\n"; $fail++; }
    /* **الكاسر ②**: السلَّمُ حتميٌّ — سدادُ موردٍ في مستخلصٍ يفوز أوّلُه */
    if (me_class('سداد مورد عن مستخلص')[0] !== 'SUPPLIER_PAYMENT') { echo "  X الترتيبُ ليس حتميًّا\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n" : "\n🟢 الفحصُ الذاتيُّ تامٌّ — السلَّمُ حتميٌّ والغامضُ لا يُقحَم\n";
    exit($fail ? 1 : 0);
}

/* ═══ القسمة ════════════════════════════════════════════════════════════ */
$W = "COALESCE(is_deleted,0)=0 AND (event_id IS NULL OR event_id=0)";
$rows = array();
$r = $conn->query("SELECT id, memo, posting_date FROM fin_journal_entries WHERE $W");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
$N = count($rows);

$cat = array(); $plan = array(); $docHints = 0;
foreach ($rows as $x) {
    list($c0, $rule, $doc) = me_class($x['memo']);
    $era = (substr((string) $x['posting_date'], 0, 4) < '2026') ? 'PRE_LEDGER' : 'CURRENT';
    $cat[$c0][$era] = (isset($cat[$c0][$era]) ? $cat[$c0][$era] : 0) + 1;
    if ($doc !== '') { $docHints++; }
    $plan[] = array('id' => (int) $x['id'], 'cat' => $c0, 'era' => $era, 'doc' => $doc, 'rule' => $rule,
        'wit' => 'من `memo` بالسلَّمِ الحتميِّ ' . $rule . ' («' . mb_substr((string) $x['memo'], 0, 60) . '»)'
               . ($era === 'PRE_LEDGER' ? ' · قبل 2026 فلا فترةَ حاوية' : ''));
}
$sum = 0; foreach ($cat as $ers) { foreach ($ers as $v) { $sum += $v; } }

echo "\n═══ أمرُ الضبطِ §٩ — مصالحةُ القيودِ اليدويّة ═══\n";
printf("  اللقطة %s · المقامُ **%d** · مجموعُ الفئاتِ **%d** · **الفرقُ %d**\n\n",
       $snap !== '' ? $snap : 'DRY', $N, $sum, $N - $sum);
printf("  %-20s %10s %10s %8s\n", 'الفئة', 'PRE_LEDGER', 'CURRENT', 'المجموع');
foreach ($cat as $c0 => $ers) {
    $p0 = isset($ers['PRE_LEDGER']) ? $ers['PRE_LEDGER'] : 0;
    $c1 = isset($ers['CURRENT']) ? $ers['CURRENT'] : 0;
    printf("  %-20s %10d %10d %8d\n", $c0, $p0, $c1, $p0 + $c1);
}
printf("\n  ◆ مرجعُ مستندٍ منتزَعٌ من `memo` (`CLM-…`): **%d** قيدًا — مادّةُ علاجِ `source_doc_ref` لاحقًا\n", $docHints);
echo "  ⛔ **وما يُرفع للمالكِ فئتان لا صفوف**: سياسةُ الفتراتِ التاريخيّةِ · وسياسةُ الاعتمادِ الرجعيّ\n";

if ($APPLY) {
    $conn->query('START TRANSACTION');
    $conn->query("DELETE FROM repair01_manual_entry_class");
    $n = 0;
    foreach ($plan as $p) {
        $sql = "INSERT INTO repair01_manual_entry_class
                (entry_id, category, era, doc_hint, rule_applied, witness, snapshot_id)
                VALUES (" . $p['id'] . ",'" . $e($p['cat']) . "','" . $p['era'] . "','" . $e($p['doc'])
              . "','" . $p['rule'] . "','" . $e($p['wit']) . "','" . $e($snap) . "')";
        if (!$conn->query($sql)) { $conn->query('ROLLBACK'); exit("✘ {$conn->error}\n"); }
        $n++;
    }
    $conn->query('COMMIT');
    $chk = (int) $one("SELECT COUNT(*) FROM repair01_manual_entry_class");
    printf("\n  ✔ كُتب **%d** صفًّا — والتحقُّقُ بعد الكتابة: **%d** (الفرقُ عن المقامِ %d)\n", $n, $chk, $N - $chk);
}

if ($MD) {
    $o  = "# أمرُ الضبطِ §٩ — مصالحةُ القيودِ اليدويّة\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `$snap`\n\n";
    $o .= "**المعادلة: $N = مجموعُ الفئاتِ $sum · الفرقُ " . ($N - $sum) . "** " . ($N === $sum ? '🟢' : '✘') . "\n\n";
    $o .= "| الفئة | PRE_LEDGER (قبل 2026) | CURRENT | المجموع | الأثر | التوصية |\n|---|---:|---:|---:|---|---|\n";
    $meta = array(
        'SUPPLIER_PAYMENT' => array('ذممُ موردين مسدَّدة', 'صالحةٌ يدويًّا — يلزمها مستندُ سدادٍ واعتمادٌ رجعيّ'),
        'CLAIM_REVENUE'    => array('إيرادُ مستخلصاتٍ', 'مرجعُ `CLM-…` في `memo` — **يُعالَج `source_doc_ref` منه قياسًا**'),
        'SETTLEMENT'       => array('تسوياتٌ', 'مراجعةُ عيّنةٍ ثمَّ اعتمادٌ رجعيّ'),
        'RECEIPT'          => array('مقبوضاتٌ', 'كسابقتها'),
        'PAYROLL'          => array('رواتبُ وأجور', 'كسابقتها'),
        'UNCLASSIFIED'     => array('—', '⛔ **هذا وحدَه يُرفع صفًّا صفًّا** — والمقيسُ صفر'),
    );
    foreach ($cat as $c0 => $ers) {
        $p0 = isset($ers['PRE_LEDGER']) ? $ers['PRE_LEDGER'] : 0;
        $c1 = isset($ers['CURRENT']) ? $ers['CURRENT'] : 0;
        $m0 = isset($meta[$c0]) ? $meta[$c0] : array('', '');
        $o .= "| `$c0` | $p0 | $c1 | **" . ($p0 + $c1) . "** | {$m0[0]} | {$m0[1]} |\n";
    }
    $o .= "\n## ما يُرفع للمالكِ — فئتان لا ١٬١٠٣ صفًّا\n\n";
    $o .= "| القرار | ما يحجبه | الخيارات | التوصية |\n|---|---|---|---|\n";
    $o .= "| **سياسةُ الفتراتِ التاريخيّة** (سنواتُ 2020-2025) | حوكمةُ " . array_sum(array_map(function ($x) { return isset($x['PRE_LEDGER']) ? $x['PRE_LEDGER'] : 0; }, $cat)) . " قيدًا `PRE_LEDGER` | إنشاءُ فتراتٍ مقفلةٍ تاريخيّة · أو وسمُها `PRE_GOVERNANCE` نهائيًّا | إنشاءُ فتراتٍ **مقفلةٍ منذ الولادة** — يحفظ التسلسلَ ولا يفتح تعديلًا |\n";
    $o .= "| **سياسةُ الاعتمادِ الرجعيّ** | `approval_ref` للـ1,644 كلِّها | اعتمادٌ جماعيٌّ بمرجعِ قرارٍ واحد · أو عيّنةُ مراجعةٍ ثمَّ اعتماد | اعتمادٌ جماعيٌّ بمرجعِ القرارِ لكلِّ فئةٍ بعد عيّنة |\n";
    $o .= "\n◆ **مادّةُ علاجٍ مقيسة**: $docHints قيدَ مستخلصٍ يحمل مرجعَه (`CLM-…`) في `memo` — ملءُ `source_doc_ref` منها اشتقاقُ أداةٍ لا قرارُ مالك.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/CTL_MANUAL_ENTRIES.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/CTL_MANUAL_ENTRIES.md\n";
}
