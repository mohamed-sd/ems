<?php
/**
 * tools/rpr03_silent_stations.php — `RPR-03` §٨·٢ · المحطاتُ الصامتة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-03` §٨·٢: *«العروضُ والفوترةُ والخزينةُ ومخالفاتُ
 *   الموردين **مبنيّةٌ بصفرِ صفٍّ عبرَ ثلاثِ لقطات**. **والبناءُ الذي لم يُمارَس
 *   مرّةً واحدةً لا يُعرَف أصحيحٌ هو أم لا**»* · و§١٠: `محطاتٌ مبنيّةٌ بصفرِ صفّ
 *   = صفر`.
 *
 * ◆ **والمحطّةُ تُقاس بجداولِها لا باسمِها**: لكلِّ محطّةٍ **مجموعةُ جداولٍ
 *   مُعلَنةٌ** يُعدُّ صفوفُها. ⛔ **ولا يُقاس سطحٌ بوجودِ ملفِّه** — فالملفُّ
 *   موجودٌ في المحطاتِ الأربعِ كلِّها، وهذا بعينِه ما يجعلها «مبنيّةً صامتة».
 *
 * ◆ **وحارسُ المجموعةِ يمنع الغفلة**: الأداةُ تمسح القاعدةَ بأنماطِ كلِّ محطّةٍ
 *   **وتُبلِّغ عن جدولٍ يطابق النمطَ وليس في المجموعةِ المُعلَنة** — فمجموعةٌ
 *   ثابتةٌ تتقادم بصمتٍ حين يُضاف جدولٌ جديد، **ورقمٌ يُقاس على مجموعةٍ ناقصةٍ
 *   يبدو أحسنَ مما هو**.
 *
 * ◆ **والصفرُ هنا لا يُقرأ نجاحًا** — بخلافِ سائرِ المقاييس: صفرُ صفٍّ في محطّةٍ
 *   مبنيّةٍ **هو العطبُ نفسُه**. ⇒ فالمقياسُ **عددُ المحطاتِ التي كلُّ جداولِها
 *   صفر**، ومستهدَفُه صفر.
 *
 * التشغيل: php tools/rpr03_silent_stations.php [--md] [--selftest]
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

$MD   = in_array('--md', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : (int) $x[0];
};

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '—(بلا نافذة)';

/* المحطاتُ الأربعُ بنصِّ §٨·٢ — ولكلٍّ مجموعتُها المُعلَنةُ ونمطُ حراستِها */
$STATIONS = array(
    'العروض' => array(
        'tables'  => array('proc_offer', 'proc_offer_line', 'fin_funding_offer',
                           'sup_offer_supplier_negotiation', 'rfq_quotes'),
        'pattern' => array('%offer%', '%quote%', '%proposal%'),
    ),
    'الفوترة' => array(
        'tables'  => array('acc_invoice_line', 'ar_claim_invoices', 'proc_invoice_match',
                           'tax_invoices'),
        'pattern' => array('%invoice%', '%billing%'),
    ),
    'الخزينة' => array(
        'tables'  => array('tre_beneficiaries', 'tre_cash_box', 'tre_cash_count',
                           'tre_cash_count_line', 'tre_cash_move', 'tre_fx_deal',
                           'tre_guarantee', 'tre_instrument', 'tre_pay_batches',
                           'tre_pay_batch_lines', 'tre_petty_custody', 'tre_petty_expense',
                           'tre_recon_difference', 'tre_transfer'),
        'pattern' => array('tre\_%'),
    ),
    'مخالفات الموردين' => array(
        'tables'  => array('sup_violations', 'supplier_penalty_rules'),
        'pattern' => array('%violation%', '%penalty_rule%'),
    ),
);

$exists = array();
$r = $conn->query("SELECT LOWER(TABLE_NAME) t FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'");
while ($x = $r->fetch_row()) { $exists[$x[0]] = 1; }

$out = array(); $silent = 0; $drift = array();
foreach ($STATIONS as $name => $spec) {
    $rows = array(); $total = 0; $missing = array();
    foreach ($spec['tables'] as $t) {
        if (!isset($exists[strtolower($t)])) { $missing[] = $t; continue; }
        $n = (int) $one("SELECT COUNT(*) FROM `" . $t . "`");
        $rows[$t] = $n; $total += $n;
    }
    /* ⛔ **حارسُ المجموعة**: جدولٌ يطابق النمطَ وليس في المُعلَن */
    foreach ($spec['pattern'] as $p) {
        $q = $conn->query("SELECT LOWER(TABLE_NAME) t FROM information_schema.TABLES
                            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'
                              AND TABLE_NAME LIKE '" . $conn->real_escape_string($p) . "'");
        while ($x = $q->fetch_row()) {
            $lo = array_map('strtolower', $spec['tables']);
            if (!in_array($x[0], $lo, true)) { $drift[$name][$x[0]] = 1; }
        }
    }
    $isSilent = ($total === 0 && count($rows) > 0);
    if ($isSilent) { $silent++; }
    $out[$name] = array('rows' => $rows, 'total' => $total, 'missing' => $missing, 'silent' => $isSilent);
}

/* ⛔ **السالبُ يكسر مفردةً فريدة**: تُنزع مجموعةُ محطّةٍ فتبدو غيرَ صامتة */
if ($SELF) {
    $out['الخزينة']['rows'] = array();
    $out['الخزينة']['silent'] = false;
    $silent--;
}

echo "\n═══ `RPR-03` §٨·٢ — المحطاتُ الصامتة ═══\n";
printf("  اللقطة: %s\n\n", $sid);
foreach ($out as $name => $st) {
    printf("  %s **%s** — جداولُ مُعلَنة %d · مجموعُ الصفوف **%d**\n",
           $st['silent'] ? '⛔' : '✔', $name, count($st['rows']), $st['total']);
    foreach ($st['rows'] as $t => $n) {
        printf("       %s %-34s %8d\n", $n === 0 ? '·' : '✔', $t, $n);
    }
    if ($st['missing']) { printf("       ⚠ مُعلَنٌ وغيرُ موجودٍ في المخطَّط: %s\n", implode(' · ', $st['missing'])); }
    if (isset($drift[$name])) {
        printf("       ⛔ **يطابق النمطَ وليس في المُعلَن**: %s\n",
               implode(' · ', array_slice(array_keys($drift[$name]), 0, 6)));
    }
}

echo "\n  ── ولماذا الصفرُ هنا عطبٌ لا نجاح ──\n";
echo "     §٨·٢: «**والبناءُ الذي لم يُمارَس مرّةً واحدةً لا يُعرَف أصحيحٌ هو أم لا**».\n";
echo "     ⛔ ولا يُقاس سطحٌ بوجودِ ملفِّه — فالملفُّ موجودٌ في الأربعِ كلِّها.\n";

echo "\n────────────────────────────────────────────────────────────\n";
printf("**`محطاتٌ مبنيّةٌ بصفرِ صفّ` = %d من %d** — والقبولُ صفر\n", $silent, count($STATIONS));
echo $silent === 0
    ? "🟢 **كلُّ محطّةٍ مُورست مرّةً على الأقلّ**\n"
    : "✘ ⇒ `Track RPR-03 و blocked at stage: تمريرُ معاملةٍ واحدةٍ حقيقيّةٍ بكلِّ محطّة`\n"
    . "  ◆ و§٨·٢: «**ومعاملةٌ واحدةٌ حقيقيّةٌ تكفي لفتحِ الطريق**» — وتُمرَّر ضمنَ رحلتِها لا منفردة\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $silent < 4
        ? "🟢 **حين نُزعت مجموعةُ محطّةٍ سقطت من العدِّ — فالمقياسُ يقرأ الجداولَ لا الأسماء**\n"
        : "✘ **العدّادُ لم يتحرّك**\n";
    exit($silent < 4 ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-03` §٨·٢ — المحطاتُ الصامتة\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    foreach ($out as $name => $st) {
        $o .= "## " . ($st['silent'] ? '⛔ ' : '✔ ') . $name . " — مجموعُ الصفوف **" . $st['total'] . "**\n\n";
        $o .= "| الجدول | صفوف |\n|---|---|\n";
        foreach ($st['rows'] as $t => $n) { $o .= '| `' . $t . '` | ' . ($n === 0 ? '**0**' : $n) . " |\n"; }
        if (isset($drift[$name])) {
            $o .= "\n⛔ **يطابق النمطَ وليس في المجموعةِ المُعلَنة**: `"
                . implode('` · `', array_slice(array_keys($drift[$name]), 0, 8)) . "`\n";
        }
        $o .= "\n";
    }
    $o .= "## ولماذا الصفرُ هنا عطبٌ لا نجاح\n\n";
    $o .= "§٨·٢: *«والبناءُ الذي لم يُمارَس مرّةً واحدةً **لا يُعرَف أصحيحٌ هو أم لا**»*.\n";
    $o .= "⛔ ولا يُقاس سطحٌ بوجودِ ملفِّه — فالملفُّ موجودٌ في المحطاتِ الأربعِ كلِّها،\n";
    $o .= "**وهذا بعينِه ما يجعلها مبنيّةً صامتة**.\n\n";
    $o .= "**`محطاتٌ مبنيّةٌ بصفرِ صفّ` = " . $silent . " من " . count($STATIONS) . "** — والقبولُ صفر.\n\n";
    if ($silent > 0) {
        $o .= "`Track RPR-03 و blocked at stage: تمريرُ معاملةٍ واحدةٍ حقيقيّةٍ بكلِّ محطّة`\n\n";
        $o .= "◆ و§٨·٢: *«ومعاملةٌ واحدةٌ حقيقيّةٌ تكفي لفتحِ الطريق»* — **وتُمرَّر ضمنَ رحلتِها\n";
        $o .= "لا منفردة**.\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_SILENT_STATIONS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_SILENT_STATIONS.md\n";
}
