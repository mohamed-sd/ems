<?php
/**
 * tests/injfrd01_nf031_value_slot.php
 *   NF-31 — **لا خانةَ قيمةٍ فارغة، والصفرُ يُكتب `0`**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا وُجد هذا الشاهد** (البند ٠-٦ من INJ-EXEC-CLOSE-01): كان NF-31
 *   يُعلَن «منجَزًا» بمطابقةِ **نصِّ تعليقٍ** في `includes/kpi_card.php`
 *   (`mb_strpos(…, 'قيمةٌ صحيحةٌ لا غياب')`) — وذلك التعليقُ آخرُ التزامٍ مسَّه
 *   `5babc4e3` بتاريخ **2026-08-21 08:14**، أي **قبلَ التدقيقِ الذي أنشأ
 *   المطلبَ أصلًا**. فالإعلانُ كان يقرأ أثرًا سابقًا للمطلبِ ويسمّيه استيفاءً له.
 *   وزاد الأمرَ أن أداةً ثانيةً (`injrev01_audit_align_reverse`) تقول **OPEN**
 *   عن المطلبِ نفسِه — **قارئان يتفرّقان، وأحدُهما لا يقيس**.
 *
 * ◆ **والقاعدةُ تُقاس بالتوليدِ لا بالنصّ** — ثلاثُ طبقاتٍ لا واحدة:
 *     ① عقدُ المكوّن: `0` و`'0'` و`0.0` و`'0.00'` تُصيَّر قيمًا ولا تُرفض.
 *     ② وخانةٌ فارغةٌ **لا تُصيَّر صامتةً**: `''` و`null` والفراغُ والمفتاحُ
 *        الغائبُ يُنتجن بطاقةَ عقدٍ مكسورٍ **تسمّي «القيمة»** — فالغيابُ يُعلَن.
 *     ③ والتوليدُ الحيُّ: كلُّ بطاقةٍ تخرج من `roleBoardBuild` لكلِّ دورٍ مُمَكَّنٍ
 *        لها `display` غيرُ فارغ — **يُقاس بتشغيلِ البانِي لا بقراءةِ مصدرِه**.
 *
 * التشغيل: php tests/injfrd01_nf031_value_slot.php [--negative]
 *
 * ◆ و`--negative` **يعطب الشرطَ عمدًا**: يدسُّ بطاقةً بخانةٍ فارغةٍ في المقيسِ
 *   ويشترط أن يمسكها العادُّ **وحدَها** — فعادٌّ لم يُجرَّبْ لا يُصدَّق. والعطبُ
 *   في المُدخَلِ لا في الشجرةِ ولا في القاعدة، فلا أثرَ يُكنَس.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$GLOBALS['conn'] = $conn;

$ok = 0; $bad = 0;
function chk($c, $l, $d = '')
{
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
$neg = in_array('--negative', $argv, true);

/** خانةُ القيمةِ من بطاقةٍ مُصيَّرة — أو null إن لم تُصيَّر أصلًا. */
function nf31_slot($html)
{
    return preg_match('~<div class="ems-kpi-value">(.*?)</div>~s', $html, $m) ? $m[1] : null;
}

/** أهي بطاقةُ عقدٍ مكسورٍ **تسمّي** «القيمة»؟ — فالإعلانُ باسمِ الناقصِ لا بصمت. */
function nf31_is_missing_value($html)
{
    /* ◆ **المطابقةُ بعد نزعِ التشكيل** (RPR-W06): معيارُ نقاءِ لغةِ الواجهةِ نزع
         التشكيلَ من النصِّ المُصيَّر، فصارت «ناقصةُ العقد» تُصيَّر «ناقصة العقد».
         والمطلبُ لم يتغيَّر — البطاقةُ ما زالت **تسمّي** الناقصَ ولا تصمت —
         فتُنزَع العلاماتُ من الطرفَين قبل المقارنة. */
    $nd = function ($s) { return preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', (string) $s); };
    $h  = $nd($html);
    return mb_strpos($h, $nd('ناقصةُ العقد')) !== false && mb_strpos($h, $nd('القيمة')) !== false;
}

/**
 * عادُّ البطاقاتِ بخانةِ قيمةٍ فارغة — **موضعٌ واحدٌ للحكم**، يستعمله القياسُ
 * الحيُّ والحزامُ السلبيُّ معًا، فلا يتفرّق قارئان كما تفرّقا في NF-31 نفسِه.
 */
function nf31_count_blank(array $cards)
{
    $n = 0;
    foreach ($cards as $c) {
        $d = array_key_exists('display', $c) ? $c['display'] : null;
        if ($d === null || (is_string($d) && trim($d) === '')) { $n++; }
    }
    return $n;
}

echo "══ NF-31 — لا خانةَ قيمةٍ فارغة · والصفرُ يُكتب 0 ══\n";

/* ═══ ① الصفرُ قيمةٌ لا غياب ═══════════════════════════════════════════ */
require_once $ROOT . '/includes/kpi_card.php';
$base = array('title' => 'مؤشرُ فحص', 'unit' => 'سجل', 'period' => 'لحظي',
              'status' => 'ok', 'drill' => '#');
$zBad = array();
foreach (array(array(0, '0'), array('0', '0'), array(0.0, '0'), array('0.00', '0.00')) as $z) {
    $h = ems_kpi_card(array_merge($base, array('value' => $z[0])));
    $slot = nf31_slot($h);
    if (nf31_is_missing_value($h) || $slot !== $z[1]) {
        $zBad[] = var_export($z[0], true) . ' ⇒ ' . var_export($slot, true);
    }
}
chk(empty($zBad), '**الصفرُ يُصيَّر `0` ولا يُرفض** — 0 · «0» · 0.0 · «0.00»',
    empty($zBad) ? 'أربعُ صيغٍ صفريةٍ كلُّها قيمةٌ مُصيَّرة' : implode(' · ', $zBad));

/* ═══ ② الخانةُ الفارغةُ تُعلَن ولا تُصيَّر صامتة ═════════════════════════ */
$bBad = array();
foreach (array("''" => '', "'   '" => '   ') as $lbl => $v) {
    $h = ems_kpi_card(array_merge($base, array('value' => $v)));
    if (!nf31_is_missing_value($h)) { $bBad[] = $lbl; }
}
$h = ems_kpi_card(array_merge($base, array('value' => null)));
if (!nf31_is_missing_value($h)) { $bBad[] = 'null'; }
$h = ems_kpi_card($base);                       /* مفتاحُ القيمةِ غائبٌ أصلًا */
if (!nf31_is_missing_value($h)) { $bBad[] = 'مفتاحٌ غائب'; }
chk(empty($bBad), 'و**الخانةُ الفارغةُ تُعلَن باسمِها** ولا تُصيَّر صامتةً',
    empty($bBad) ? 'أربعُ صيغِ خواءٍ كلُّها تُنتج «الحقولُ الغائبة: القيمة»'
                 : 'مرَّت صامتةً: ' . implode(' · ', $bBad));

/* ═══ ③ التوليدُ الحيُّ — البانِي يُشغَّل ولا يُقرأ مصدرُه ═══════════════ */
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantDb.php';
require_once $ROOT . '/includes/role_board.php';

$coRow = @$conn->query("SELECT `id` FROM `admin_companies` ORDER BY `id` LIMIT 1");
$CO = ($coRow && ($x = $coRow->fetch_row())) ? (int) $x[0] : 0;
$gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($CO, 1, '', true));
$roles = array_values(array_filter(array_map('intval',
    array_map('trim', explode(',', (string) ems_env('EMS_ROLE_BOARD_ROLES', ''))))));

$cardsAll = array(); $withBoard = 0; $exs = array();
foreach ($roles as $rid) {
    try { $b = roleBoardBuild($conn, $gate, $rid, 1); }
    catch (\Throwable $e) { $exs[] = "دور {$rid}: " . mb_substr($e->getMessage(), 0, 40); continue; }
    if (!isset($b['cards']) || !is_array($b['cards']) || !$b['cards']) { continue; }
    $withBoard++;
    foreach ($b['cards'] as $c) { $c['_role'] = $rid; $cardsAll[] = $c; }
}

if ($neg) {
    /* ◆ **العطبُ عمدًا**: بطاقةٌ بخانةِ قيمةٍ فارغةٍ تُدسُّ في المقيسِ نفسِه —
     *   فإن لم يمسكها العادُّ فالقياسُ كلُّه زخرفة. والعطبُ في المُدخَلِ لا في
     *   الشجرةِ ولا في القاعدة: لا صفَّ يُكتب ولا أثرَ يُكنَس. */
    $cardsAll[] = array('_role' => 0, 'label' => 'بطاقةُ حزامٍ بخانةٍ فارغة', 'display' => '');
    echo "  ◆ دُسَّت بطاقةٌ بخانةِ قيمةٍ فارغةٍ للحزامِ السلبيّ\n";
}

$blank = nf31_count_blank($cardsAll);
$tot   = count($cardsAll);
chk($withBoard > 0 && empty($exs), 'ولوحاتُ الأدوارِ تُبنى فعلًا فيُقاس مخرَجُها',
    "أدوارٌ لها لوحة: **{$withBoard}** من " . count($roles) . ' مُمَكَّنٍ في `EMS_ROLE_BOARD_ROLES`'
    . ($exs ? ' · استثناءات: ' . implode(' · ', $exs) : ''));
chk($tot > 0, 'والمقامُ بطاقاتٌ مُولَّدةٌ حيًّا لا صفرٌ ولا عددٌ منقول',
    "بطاقاتٌ مُولَّدة: **{$tot}**");
chk($blank === 0, '**صفرُ بطاقةٍ مُولَّدةٍ بخانةِ قيمةٍ فارغة**',
    "المقيس: **{$blank}/{$tot}**");

if ($neg) {
    echo "\n── حكمُ الحزامِ السلبيّ ──\n";
    chk($blank === 1, '**العادُّ أمسك البطاقةَ المدسوسةَ وحدَها**',
        $blank === 1 ? 'واحدةٌ من ' . $tot : "المقيس {$blank} — والمتوقَّع 1");
    echo "\n◆ والحزامُ **يعطب المُدخَلَ لا الشجرة** — فلا صفَّ يُكتب ولا أثرَ يُكنَس.\n";
    printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
    exit($blank === 1 ? 0 : 1);
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
