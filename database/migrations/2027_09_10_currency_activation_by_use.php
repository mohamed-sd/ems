<?php
/**
 * 2027_09_10_currency_activation_by_use.php
 *   عملةٌ نشطةٌ بلا سعرٍ فخٌّ — INJ-FIX-01 · GAP-25
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «سعرٌ ساري المفعولِ لكلِّ عملةٍ نشطة». والمقيسُ **خمسُ عملاتٍ
 *   نشطةٍ بلا سعر**: `co1:SDG` · `co4:AED` · `co4:EUR` · `co4:QAR` · `co4:SAR`.
 *
 * ◆ **ولا يُخترع سعرُ صرف** — وهو أشدُّ ما يمنعه «لا رقمَ بلا مصدر»: سعرٌ خاطئٌ
 *   يُقوّم قيودًا ماليةً تقويمًا خاطئًا **بصمت**، ويظهر بعدَ أشهرٍ في الحسابات.
 *   **والمِقبَضُ المشروعُ ليس السعرَ بل قائمةَ النشطات.**
 *
 * ◆ **والقياسُ يقول إن الخمسَ بلا استعمالِ عملٍ في شركتِها**:
 *     · `co1:SDG` — و`co1` **قوقعةٌ**: مستخدمٌ واحدٌ وموظفٌ واحدٌ و**صفرُ عقدٍ
 *       وصفرُ قيدٍ وصفرُ ذمّة**. وكلُّ استعمالِ SDG المقيسِ في `co4` — **وهي
 *       لها سعرٌ مسجَّلٌ سلفًا** (0.000185 من 2024-07-01).
 *     · `co4:AED` و`co4:EUR` — **صفرُ صفٍّ** في كلِّ جداولِ المال.
 *     · `co4:QAR` و`co4:SAR` — **صفٌّ واحدٌ لكلٍّ، وكلاهما بذرٌ لا واقعة**:
 *       `period_ref` من عائلةِ المفاتيحِ الملوَّثة (`FIN_-00019` · `FIN_-00020`)،
 *       وتركيبُهما متناقض: مورِّدٌ بـ«نهايةِ خدمة» وموظفٌ بمصدرِ «أمرِ صيانة».
 *
 * ◆ **والتعطيلُ يُحسّن ولا يكسر**: طبقةُ الصرفِ **fail-closed** أصلًا فالصفَّان
 *   غيرُ مسعَّرَين اليوم ويبقيان كذلك. والذي يتغيّر أن **أحدًا لن يُنشئ من
 *   جديدٍ سجلًّا بعملةٍ لا تُسعَّر**. فالعملةُ النشطةُ بلا سعرٍ **فخٌّ في منتقًى**.
 *
 * ◆ ولا تُحذف عملة: التعطيلُ يُخرجها من المنتقياتِ ويُبقي سجلَّها ومرجعَ ما مضى.
 *
 * التشغيل:  php database/migrations/2027_09_10_currency_activation_by_use.php
 * الرجوع :  php database/migrations/2027_09_10_currency_activation_by_use.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

if (in_array('--revert', $argv, true)) {
    $conn->query("UPDATE `fin_currencies` SET `active` = 1
                   WHERE (`company_id` = 1 AND `code` = 'SDG')
                      OR (`company_id` = 4 AND `code` IN ('AED','EUR','QAR','SAR'))");
    echo "↺ أُعيد تنشيطُ {$conn->affected_rows} عملة\n";
    exit(0);
}

/* ══ ① لا تُعطَّل عملةٌ إلا بعدَ قياسِ استعمالِها في شركتِها ═══════════════ */
$MONEY = array('fin_dues', 'settlements', 'fin_journal_entries', 'fin_requests', 'fin_financial_events');
function useIn($conn, $MONEY, $co, $code)
{
    $n = 0;
    foreach ($MONEY as $t) {
        $r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='currency'");
        if (!$r || (int) $r->fetch_row()[0] === 0) { continue; }
        $hc = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='company_id'");
        $scoped = ($hc && (int) $hc->fetch_row()[0] > 0);
        $sql = $scoped
            ? "SELECT COUNT(*) FROM `{$t}` WHERE `currency`='{$code}' AND `company_id`={$co}"
            : "SELECT COUNT(*) FROM `{$t}` WHERE `currency`='{$code}'";
        $q = $conn->query($sql);
        $n += $q ? (int) $q->fetch_row()[0] : 0;
    }
    return $n;
}
/** أهو بذرٌ لا واقعة؟ — مفتاحٌ ملوَّثٌ من عائلة `XXX_-000n` */
function seedOnly($conn, $co, $code)
{
    $q = $conn->query("SELECT COUNT(*) FROM `fin_dues`
                        WHERE `currency`='{$code}' AND `company_id`={$co}
                          AND `period_ref` REGEXP '^[A-Z]+_-[0-9]+$'");
    $seed = $q ? (int) $q->fetch_row()[0] : 0;
    $q = $conn->query("SELECT COUNT(*) FROM `fin_dues`
                        WHERE `currency`='{$code}' AND `company_id`={$co}");
    $all = $q ? (int) $q->fetch_row()[0] : 0;
    return ($all > 0 && $seed === $all);
}

$CANDIDATES = array(array(1, 'SDG'), array(4, 'AED'), array(4, 'EUR'), array(4, 'QAR'), array(4, 'SAR'));
$off = 0; $kept = array();
echo "① قياسُ الاستعمالِ قبلَ التعطيل\n";
foreach ($CANDIDATES as $x) {
    list($co, $code) = $x;
    $n = useIn($conn, $MONEY, $co, $code);
    $isSeed = ($n > 0) ? seedOnly($conn, $co, $code) : true;
    if ($n > 0 && !$isSeed) {
        $kept[] = "co{$co}:{$code} ({$n} صفًّا حقيقيًّا)";
        printf("   ◆ co%-2s %-4s استعمالٌ حقيقيٌّ %d — **لا تُعطَّل** وتحتاج سعرًا\n", $co, $code, $n);
        continue;
    }
    $conn->query("UPDATE `fin_currencies` SET `active`=0
                   WHERE `company_id`={$co} AND `code`='{$code}' AND `active`=1");
    if ($conn->affected_rows > 0) { $off++; }
    printf("   ✔ co%-2s %-4s عُطِّلت — استعمال=%d%s\n", $co, $code, $n, $n > 0 ? ' (بذرٌ لا واقعة)' : '');
}
echo "① عُطِّل: {$off} عملة\n";

/* ══ ② الحصيلة — كلُّ نشطةٍ لها سعرٌ سارٍ ═════════════════════════════════ */
echo "───────────────────────────────────────────────────────────────\n";
$q = $conn->query("SELECT c.company_id, c.code, c.is_base,
                    (SELECT COUNT(*) FROM `fin_fx_rates` r
                      WHERE r.company_id=c.company_id AND r.currency_code=c.code
                        AND COALESCE(r.is_deleted,0)=0 AND r.effective_from <= CURDATE()) rates
                     FROM `fin_currencies` c WHERE c.active=1
                    ORDER BY c.company_id, c.code");
$bad = array(); $tot = 0;
while ($q && $x = $q->fetch_assoc()) {
    $tot++;
    printf("  co%-3s %-6s %s أسعارٌ سارية=%d\n", $x['company_id'], $x['code'],
        $x['is_base'] ? '(أساس)' : '      ', $x['rates']);
    if ((int) $x['rates'] === 0) { $bad[] = 'co' . $x['company_id'] . ':' . $x['code']; }
}
printf("② **عملاتٌ نشطة: %d · بلا سعرٍ سارٍ: %d**\n", $tot, count($bad));
if ($bad) { echo "   ◆ " . implode(' · ', $bad) . " — تنتظر سعرَها من المالية\n"; }
if ($kept) { echo "   ◆ أُبقيت لاستعمالِها الحقيقيّ: " . implode(' · ', $kept) . "\n"; }
echo "◆ ولم يُخترع سعرٌ واحد — المِقبَضُ كان قائمةَ النشطاتِ لا السعر.\n";
