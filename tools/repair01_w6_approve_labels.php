<?php
/**
 * tools/repair01_w6_approve_labels.php — إقرارُ الأسماءِ المعياريّةِ المُنقّاة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا خطوةٌ منفصلة**: تنقيةُ W06 غيّرت أسماءً بنزعِ التشكيلِ وإعادةِ صياغةِ
 *   الشرطة، وسُجِّلت إعادةُ التسميةِ في `nav_canonical.old_names`. لكنّ مصفوفةَ
 *   حفظِ السايدبار تقرأ `status='APPROVED'` وحدَها — فالصفوفُ `PENDING_OWNER`
 *   تبقى خارجَ حسابِها ويُرسَّب التسليم.
 *
 * ◆ **وقلبُ الحالةِ اعتمادٌ لا إصلاح**: لا تفعله أداةٌ من تلقاءِ نفسِها. هذه
 *   الأداةُ تُنفَّذ **بأمرِ المالكِ الصريح** (2026-08-25) بعد عرضِ القائمةِ
 *   كاملةً عليه، ولا تقرّ إلّا ما استوفى شرطَين مقيسَين:
 *     ① الاسمُ نظيفٌ فعلًا — صفرُ تشكيلٍ وصفرُ نقطتَين وصفرُ زخرفة.
 *     ② له سابقةٌ مسجَّلةٌ في `old_names` — أي أنّه إعادةُ تسميةٍ موثَّقةٌ
 *        لا اسمٌ طارئٌ بلا أصل.
 *   وما خالف أحدَهما **لا يُقَرّ** ويُطبَع سببُه.
 *
 * التشغيل: php tools/repair01_w6_approve_labels.php [--dry]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$DRY = in_array('--dry', $argv, true);

$RE_DIAC  = '/[\x{064B}-\x{0652}\x{0670}]/u';
$RE_DECOR = '/[\x{2190}-\x{21FF}\x{2013}\x{2014}\x{25C6}\x{2022}\x{00B7}:]/u';

$rows = array();
$r = $conn->query("SELECT route, canonical_ar, COALESCE(old_names,'') old_names
                   FROM nav_canonical
                   WHERE status = 'PENDING_OWNER' AND COALESCE(old_names,'') NOT IN ('', '—')
                   ORDER BY route");
while ($r && $x = $r->fetch_assoc()) { $rows[] = $x; }
echo "مرشَّحٌ للإقرار: " . count($rows) . "\n\n";

$ok = 0; $rej = 0;
foreach ($rows as $x) {
    $why = array();
    if (preg_match($RE_DIAC, $x['canonical_ar']))  { $why[] = 'تشكيلٌ باقٍ'; }
    if (preg_match($RE_DECOR, $x['canonical_ar'])) { $why[] = 'زخرفةٌ أو نقطتان'; }
    if (trim($x['old_names']) === '')              { $why[] = 'بلا سابقةٍ مسجَّلة'; }
    if ($why) {
        $rej++;
        printf("  ✘ %-42s %s\n", mb_substr($x['route'], 0, 42), implode(' · ', $why));
        continue;
    }
    if (!$DRY) {
        $q = $conn->query("UPDATE nav_canonical SET status='APPROVED'
                            WHERE route='" . $conn->real_escape_string($x['route']) . "'");
        if (!$q) { printf("  ✘ %-42s %s\n", $x['route'], $conn->error); $rej++; continue; }
    }
    $ok++;
    printf("  ✔ %-42s %s\n", mb_substr($x['route'], 0, 42), mb_substr($x['canonical_ar'], 0, 40));
}
echo "\nأُقِرَّ: $ok · مرفوضٌ بسببٍ مطبوع: $rej" . ($DRY ? '  (تجربةٌ جافّة)' : '') . "\n";
