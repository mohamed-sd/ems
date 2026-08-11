<?php
/**
 * 2027_02_22 — العقودُ الثمانيةُ بلا موقع: تُسنَد إلى موقعِ مشروعِها الافتراضيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * هجرةُ `2027_02_17` بذرت المواقعَ الافتراضيةَ للمشاريعِ (30 موقعًا) ووصلت
 * الخطّافَين في `Projects/projects.php` و`Contracts/contracts.php`. وبقيت
 * **ثمانيةُ عقودٍ** بـ`site_id IS NULL` فأُعلنت حينها **ولم تُصلَح** لأن مشروعَها
 * كان بلا موقعٍ افتراضيّ — فلا مصدرَ يُشتقُّ منه ولا يُلفَّق موقع.
 *
 * **والقياسُ الآن يقول إن السببَ زال**: العقودُ الثمانيةُ كلُّها على المشروع
 * **#1298** وله موقعٌ افتراضيٌّ حيٌّ (**#320**). فصار الإسنادُ **اشتقاقًا من
 * مصدرٍ قائمٍ** لا اختراعًا — وهو عينُ ما يفعله خطّافُ الشاشةِ للعقدِ الجديد.
 *
 * ولا يُمَسُّ عقدٌ مشروعُه بلا موقعٍ افتراضيّ: يُعلَن ويُترك (لا تلفيق).
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$fail = array();
$one  = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

/* ── ① القياسُ قبل المسّ ─────────────────────────────────────────────────── */
$before = (int) $one('SELECT COUNT(*) FROM contracts WHERE site_id IS NULL');
echo "── ① عقودٌ بلا موقعٍ قبل: {$before}\n";
$r = $db->query('SELECT c.id, c.project_id,
                        (SELECT s.id FROM sites s
                          WHERE s.project_id = c.project_id AND s.is_default = 1 LIMIT 1) def_site
                   FROM contracts c WHERE c.site_id IS NULL ORDER BY c.id');
$withSrc = 0; $noSrc = array();
while ($r && ($x = $r->fetch_assoc())) {
    if (!empty($x['def_site'])) { $withSrc++; }
    else { $noSrc[] = '#' . $x['id'] . ' (مشروع ' . ($x['project_id'] ?: '—') . ')'; }
}
echo "     منها بمصدرٍ يُشتقُّ منه: {$withSrc} · وبلا مصدر: " . count($noSrc) . "\n";
if ($noSrc) { echo '     المتروكُ معلَنًا: ' . implode(' · ', array_slice($noSrc, 0, 8)) . "\n"; }

/* ── ② الإسنادُ اشتقاقًا — لا اختراعَ لعقدٍ بلا مصدر ────────────────────── */
if ($withSrc > 0) {
    $ok = $db->query('UPDATE contracts c
                        JOIN sites s ON s.project_id = c.project_id AND s.is_default = 1
                         SET c.site_id = s.id
                       WHERE c.site_id IS NULL');
    if (!$ok) { $fail[] = 'الإسناد: ' . $db->error; }
    echo '── ② أُسند ' . ($ok ? $db->affected_rows : 0) . " عقدًا إلى موقعِ مشروعِه الافتراضيّ\n";
} else {
    echo "── ② لا عقدَ بمصدرٍ يُشتقُّ منه — لا عمل\n";
}

/* ── ③ الشاهدُ المُشغَّل ─────────────────────────────────────────────────── */
echo "── ③ الشاهدُ المُشغَّل\n";
$after = (int) $one('SELECT COUNT(*) FROM contracts WHERE site_id IS NULL');
echo "     عقودٌ بلا موقعٍ بعد: {$after} " . ($after === count($noSrc) ? "✔ (الباقي بلا مصدرٍ معلَن)\n" : "✘\n");
if ($after !== count($noSrc)) { $fail[] = "المتبقي {$after} والمتوقَّعُ " . count($noSrc); }

// ولا عقدٌ أُسند إلى موقعٍ ليس لمشروعِه — الإسنادُ اشتقاقٌ لا إلحاق
$wrong = (int) $one('SELECT COUNT(*) FROM contracts c
                       JOIN sites s ON s.id = c.site_id
                      WHERE c.project_id IS NOT NULL AND s.project_id <> c.project_id');
echo "     عقدٌ بموقعٍ ليس لمشروعِه: {$wrong} " . ($wrong === 0 ? "✔\n" : "✘\n");
if ($wrong !== 0) { $fail[] = "{$wrong} عقدًا بموقعٍ غريبٍ عن مشروعِه"; }

$defOk = (int) $one('SELECT COUNT(*) FROM project p WHERE COALESCE(p.status,1) = 1
                      AND NOT EXISTS (SELECT 1 FROM sites s
                                       WHERE s.project_id = p.id AND s.is_default = 1)');
echo "     مشاريعُ حيةٌ بلا موقعٍ افتراضيّ: {$defOk}\n";

echo "\n" . (empty($fail)
    ? "✅ العقودُ صارت موصولةً بمواقعِ مشاريعِها اشتقاقًا — ولا موقعَ مُلفَّقًا لعقدٍ بلا مصدر.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
