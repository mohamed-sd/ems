<?php
/**
 * 2027_02_23 — النطاقُ الرئيسيُّ للعقودِ الثمانيةِ التي أُسندت في 2027_02_22
 * ═══════════════════════════════════════════════════════════════════════════
 * **عطبٌ أحدثتُه بنفسي وكشفه فاحصٌ آخر.** هجرةُ `2027_02_22` أسندت `site_id`
 * لثمانيةِ عقودٍ — **ونصفَ الوصلِ فقط**: عقدُ المواقعِ في هذا النظامِ **طرفان**،
 * عمودُ `contracts.site_id` (الموقعُ الأوّلُ) و صفٌّ في `contract_operational_sites`
 * بـ`is_primary=1` (**النطاقُ** الذي تُعلَّق عليه الأحكامُ والحركة). فصار
 * `contract_sites_test` يقرأ 120 عقدًا بموقعٍ مقابلَ **112** نطاقًا رئيسيًّا.
 *
 * ومن قاعدةِ هذا المستودعِ المسجَّلة: **«الوصلُ في موضعين وإلا زخرفة»** — عمودٌ
 * بلا نطاقٍ يجعل العقدَ يبدو موصولًا وهو غيرُ مرئيٍّ لحركةِ الموقع.
 *
 * ويُنسَخ شكلُ الصفِّ من **نطاقٍ رئيسيٍّ قائمٍ** لا من تخمين: الحالةُ والتسميةُ
 * وتاريخُ البداية تُشتقُّ كما تفعل `ContractSiteService::add` للعقدِ الجديد.
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
$withSite = (int) $one('SELECT COUNT(*) FROM contracts WHERE site_id IS NOT NULL');
$withPrim = (int) $one('SELECT COUNT(*) FROM contract_operational_sites WHERE is_primary = 1
                         AND COALESCE(is_deleted,0) = 0');
echo "── ① عقودٌ بموقع: {$withSite} · نطاقاتٌ رئيسية: {$withPrim}\n";

$r = $db->query('SELECT c.id, c.site_id, c.project_id, c.company_id
                   FROM contracts c
                  WHERE c.site_id IS NOT NULL
                    AND NOT EXISTS (SELECT 1 FROM contract_operational_sites x
                                     WHERE x.contract_id = c.id AND x.is_primary = 1
                                       AND COALESCE(x.is_deleted,0) = 0)
                  ORDER BY c.id');
$need = array();
while ($r && ($x = $r->fetch_assoc())) { $need[] = $x; }
echo '── ② عقودٌ بنصفِ وصل: ' . count($need) . "\n";
if (empty($need)) { echo "\n✅ لا عقدَ بنصفِ وصل — لا عمل.\n"; exit(0); }

/* ── ③ الحالةُ من **قاعدةِ الخدمةِ نفسِها** لا من صفٍّ عشوائيّ ──────────────
     أوّلُ محاولةٍ نسخت شكلَ **آخرِ** نطاقٍ رئيسيٍّ فجاءت حالتُه `closed` — وردَّها
     `ck_cos_closed` **بحقّ**: مُغلَقٌ بلا سببِ إغلاقٍ ادّعاءٌ لا حالة. والصوابُ
     أخذُ ما تأخذه `ContractSiteService::add` للنطاقِ الجديد: **`active`**
     (`ContractSiteService.php` — الافتراضُ صراحةً `'active'`). فالقاعدةُ تُقرأ من
     الخدمةِ التي تملكها، لا من إحصاءِ صفوفٍ قديمة. */
$state = 'active';
$primaryFlag = (int) $one("SELECT primary_flag FROM contract_operational_sites
                            WHERE is_primary = 1 AND primary_flag IS NOT NULL LIMIT 1");
if ($primaryFlag !== 1) { $primaryFlag = 1; }
echo "── ③ الحالةُ من قاعدةِ الخدمة: state={$state} · is_primary=1 · primary_flag={$primaryFlag}\n";
$model = array('state' => $state, 'primary_flag' => $primaryFlag);

$st = $db->prepare('INSERT INTO contract_operational_sites
                    (company_id, contract_id, site_id, scope_name, start_date, state,
                     is_primary, primary_flag, note, created_by, created_at)
                    VALUES (?, ?, ?, ?, CURDATE(), ?, 1, ?, ?, 0, NOW())');
if (!$st) { fwrite(STDERR, 'prepare: ' . $db->error . "\n"); exit(1); }

$added = 0;
foreach ($need as $c) {
    $siteName = (string) $one('SELECT name FROM sites WHERE id = ' . (int) $c['site_id']);
    if ($siteName === '') { $fail[] = 'عقد#' . $c['id'] . ': موقعٌ بلا اسم'; continue; }
    $co   = (int) $c['company_id'];
    $cid  = (int) $c['id'];
    $sid  = (int) $c['site_id'];
    $name = mb_substr($siteName, 0, 190);
    $state = (string) $model['state'];
    $pflag = (int) $model['primary_flag'];
    $note = 'نطاقٌ رئيسيٌّ استُكمل مع إسنادِ الموقعِ (2027_02_22) — الوصلُ في موضعين';
    $st->bind_param('iiissis', $co, $cid, $sid, $name, $state, $pflag, $note);
    if (!$st->execute()) { $fail[] = 'عقد#' . $cid . ': ' . mb_substr($st->error, 0, 60); continue; }
    $added++;
}
$st->close();
echo "── ④ أُنشئ {$added} نطاقًا رئيسيًّا\n";

/* ── ⑤ الشاهدُ المُشغَّل ─────────────────────────────────────────────────── */
echo "── ⑤ الشاهدُ المُشغَّل\n";
$left = (int) $one('SELECT COUNT(*) FROM contracts c
                     WHERE c.site_id IS NOT NULL
                       AND NOT EXISTS (SELECT 1 FROM contract_operational_sites x
                                        WHERE x.contract_id = c.id AND x.is_primary = 1
                                          AND COALESCE(x.is_deleted,0) = 0)');
echo "     عقودٌ بنصفِ وصلٍ بعد: {$left} " . ($left === 0 ? "✔\n" : "✘\n");
if ($left !== 0) { $fail[] = "بقي {$left} عقدًا بنصفِ وصل"; }

// ولا عقدَ بنطاقَين رئيسيَّين — الرئيسيُّ واحدٌ بحكمِه
$dup = (int) $one('SELECT COUNT(*) FROM (
                     SELECT contract_id FROM contract_operational_sites
                      WHERE is_primary = 1 AND COALESCE(is_deleted,0) = 0
                      GROUP BY contract_id HAVING COUNT(*) > 1) t');
echo "     عقدٌ بنطاقَين رئيسيَّين: {$dup} " . ($dup === 0 ? "✔\n" : "✘\n");
if ($dup !== 0) { $fail[] = "{$dup} عقدًا بأكثرَ من نطاقٍ رئيسيّ"; }

// والنطاقُ على موقعِ عقدِه لا على موقعٍ غريب
$mism = (int) $one('SELECT COUNT(*) FROM contract_operational_sites x
                      JOIN contracts c ON c.id = x.contract_id
                     WHERE x.is_primary = 1 AND COALESCE(x.is_deleted,0) = 0
                       AND c.site_id IS NOT NULL AND x.site_id <> c.site_id');
echo "     نطاقٌ رئيسيٌّ على موقعٍ غيرِ موقعِ عقدِه: {$mism} " . ($mism === 0 ? "✔\n" : "✘\n");
if ($mism !== 0) { $fail[] = "{$mism} نطاقًا على موقعٍ غريب"; }

$nowSite = (int) $one('SELECT COUNT(*) FROM contracts WHERE site_id IS NOT NULL');
$nowPrim = (int) $one('SELECT COUNT(*) FROM contract_operational_sites WHERE is_primary = 1
                        AND COALESCE(is_deleted,0) = 0');
echo "     عقودٌ بموقع = {$nowSite} · نطاقاتٌ رئيسية = {$nowPrim}\n";

echo "\n" . (empty($fail)
    ? "✅ الوصلُ صار في موضعيه: كلُّ عقدٍ بموقعٍ له نطاقٌ رئيسيٌّ واحدٌ على موقعِه نفسِه.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
