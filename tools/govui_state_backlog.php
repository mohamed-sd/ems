<?php
/**
 * tools/govui_state_backlog.php — **دفترُ تأليفِ آلاتِ الحالةِ المتبقّي**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا دفترٌ لا بناء**: `GOV_UI_FINISH` §٤ يطلب لكلِّ سطحِ معاملةٍ نموذجَ
 *   حالتِه بـ«النموذجُ الحاكم · الإصدارُ · الحالاتُ · الانتقالاتُ المسموحة ·
 *   الحُرّاسُ · الاعتمادُ · الأحداثُ · التدقيق». **وستّةٌ من هذه ثمانيةٍ قرارُ
 *   أعمالٍ لا قياسُ أثر**: من يملك الانتقالَ · وشرطُه المسبق · ومستندُه
 *   الرسميّ · وبوّابةُ اعتمادِه · وقاعدةُ إعادةِ الفتح · وقاعدةُ التصحيح.
 *   ⛔ **وتأليفُها من عند الأداةِ تلفيقٌ يمنعه §٥ المحظور ④**، و«المرجعُ
 *   الأجوفُ أسوأُ من غيابِه: يُقرأ خُضرةً وهو فراغ».
 *   ⇒ فالمتبقّي **يُرفَع بحاجزِه المسمّى `BLOCKED_OWNER`** لا يُلفَّق ولا يُبتلع.
 *
 * ◆ **والمقامُ ارتفع بالبناءِ لا بالتراجع** (فخُّ المقام · §19): إغلاقُ جبهةِ
 *   الحقولِ أعطى كلَّ سطحٍ **حبّةً مقيسة**، فدخل مقامَ «أسطحِ المعاملاتِ
 *   الحقيقيّة» أسطحٌ لم تكن تُقاس: **210 ⇒ 392**. ⛔ فالكسرُ يُعلَن لا النسبة.
 *
 * ⛔ **ولا يكتب شيئًا** — يقرأ الربطَ المثبَّتَ ويُخرج الدفترَ بأسمائه.
 * التشغيل: php tools/govui_state_backlog.php > docs/REPAIR01_20260823/STATE_MODEL_BACKLOG.md
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

$snap = trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));

/* مدى المقام: أسطحُ المعاملاتِ الحقيقيّةُ وحدها — نفسُ مدى الرابط لا مدًى ثانٍ */
$SCOPE = "on_disk = 1 AND ownership_verdict <> 'RETIRE'
          AND grain_cardinality IN ('ROW','LINE') AND grain_fact_scope = 'OWN_FACT'";
$tot = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry WHERE {$SCOPE}")->fetch_row()[0];
$has = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
                            WHERE {$SCOPE} AND state_model_ref <> ''")->fetch_row()[0];

$rows = array();
$q = $conn->query("SELECT screen_id, route, canonical_label_ar, grain_entity, owner_code
                     FROM repair01_screen_registry
                    WHERE {$SCOPE} AND (state_model_ref IS NULL OR state_model_ref = '')
                    ORDER BY grain_entity, route");
while ($q && $r = $q->fetch_assoc()) { $rows[] = $r; }

$byEnt = array();
foreach ($rows as $r) {
    $e = trim((string) $r['grain_entity']);
    if ($e === '') { $e = '(بلا كيانٍ مقيس)'; }
    $byEnt[$e][] = $r;
}
ksort($byEnt);

echo "# آلاتُ الحالةِ — دفترُ التأليفِ المتبقّي · `BLOCKED_OWNER`\n\n";
echo "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/govui_state_backlog.php` @ `" . $snap . "`\n";
echo "> **والمقيسُ من السجلِّ المثبَّت** لا من تقرير — `repair01_screen_registry.state_model_ref`.\n\n";
echo "## ⓪ الكسرُ لا النسبة\n\n";
echo "| المفردة | القيمة |\n|---|---|\n";
echo "| أسطحُ المعاملاتِ الحقيقيّة (المقام) | **" . $tot . "** |\n";
echo "| منها مربوطٌ بآلةِ حالتِه بشاهدٍ | **" . $has . "** |\n";
echo "| **متبقٍّ بلا آلةٍ مؤلَّفة** | **" . count($rows) . "** على **" . count($byEnt) . "** كيانًا |\n\n";
echo "⚠ **والمقامُ ارتفع بالبناءِ لا بالتراجع**: إغلاقُ جبهةِ الحقولِ أعطى كلَّ سطحٍ\n"
   . "حبّةً مقيسة، فدخل المقامَ أسطحٌ لم تكن تُقاس (‏210 ⇒ " . $tot . "). **فالكسرُ يُعلَن**\n"
   . "ولا يُقرأ ارتفاعُ المقامِ تراجعًا.\n\n";
echo "## ① ما يلزم لكلِّ آلة — وستٌّ من ثمانٍ قرارُ مالك\n\n";
echo "| البند | من يقرّره |\n|---|---|\n"
   . "| الحالاتُ وأسماؤها | الورقةُ الحاكمةُ إن سمّتها · وإلّا **المالك** |\n"
   . "| الانتقالاتُ المسموحة | **المالك** |\n"
   . "| الانتقالاتُ الممنوعةُ ومُسبِّباتُها | **المالك** |\n"
   . "| مالكُ كلِّ انتقال (فصلُ واجبات) | **المالك** |\n"
   . "| الشرطُ المسبقُ لكلِّ انتقال | **المالك** |\n"
   . "| المستندُ الرسميُّ للانتقال | **المالك** |\n"
   . "| بوّابةُ الاعتماد | محرّكُ الاعتمادِ المركزيُّ (قائم) |\n"
   . "| الأثرُ والتدقيق | `EventPublisher` (قائم) |\n\n";
echo "⛔ **ولا يُلفَّق مرجع**: §٥ المحظور ④ — والمرجعُ الأجوفُ يُقرأ خُضرةً وهو فراغ.\n\n";
echo "## ② الكياناتُ المتبقّيةُ بأسمائها وأسطحِها\n\n";
echo "| # | الكيان | أسطحُه | المساحة | المسارات |\n|---|---|---|---|---|\n";
$i = 0;
foreach ($byEnt as $ent => $list) {
    $i++;
    $routes = array(); $units = array();
    foreach ($list as $r) { $routes[] = '`' . $r['route'] . '`'; $units[trim((string) $r['owner_code'])] = 1; }
    printf("| %d | `%s` | %d | %s | %s |\n", $i, $ent, count($list),
           implode(' · ', array_keys($units)), implode(' · ', array_slice($routes, 0, 3))
           . (count($routes) > 3 ? ' · …' : ''));
}
echo "\n**الإجمالي: " . count($rows) . " سطحًا على " . count($byEnt) . " كيانًا.**\n";
