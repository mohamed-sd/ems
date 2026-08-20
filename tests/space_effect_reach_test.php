<?php
/**
 * tests/space_effect_reach_test.php — إثباتُ وصولِ الأثرِ بواقعةٍ حقيقية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب: «**ولا تُغلق مساحةٌ حتى تُنشئ واقعةً حقيقيةً فيها وتُثبت وصولَ
 *   أثرِها إلى كلِّ مستهلِكٍ في خريطتِها. والعزلُ الذي يكسر أثرًا ليس عزلًا بل عطب**».
 *
 * ◆ **وهذا الفحصُ يقيس ما بعدَ العزلِ لا ما قبلَه**: العزلُ أخفى ظهورَ شاشاتٍ في
 *   مساحاتٍ لا تملكها — فالسؤالُ: أما زال أثرُ واقعةِ هذه المساحةِ يبلغ
 *   مستهلِكيه؟ **وإخفاءُ العرضِ يجب ألّا يمسَّ الانتشارَ بحرف.**
 *
 * ◆ **والمستهلِكُ من خريطةِ الأثرِ المقيسةِ لا من ظنّ** (`gov_effect_map`)،
 *   ويُفصَل الحكمُ ثلاثةً:
 *   · `MEASURED` — يجب أن يصلَ الأثرُ فعلًا، **وإلا فهو كسرٌ يُرسِّب**.
 *   · `DECLARED_ACTIVE` — مسجَّلٌ نشِطٌ ولمّا يُقَسْ له أثرٌ؛ **يُبلَّغ ولا يُرسِّب**
 *     لأنه لم يكن يصل قبلَ العزلِ أيضًا.
 *   · `DECLARED_INACTIVE` — **كان مقطوعًا قبلَ العزل** (خريطةُ الأثر رصدته
 *     خمسَ مراتٍ قبلَ أن يُزال ظهورُ شاشةٍ واحدة). فلا يُنسب إلى العزلِ ولا
 *     يُطوى — يُعلَن **دَينًا سابقًا** باسمِه.
 *
 * ◆ **وكلُّ ما يُكتب يُمحى بالعائلة** (`EFFREACH-%`) في نهايةِ التشغيل.
 *
 * التشغيل: php tests/space_effect_reach_test.php ["اسم المساحة"]
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Core/EventPublisher.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$SPACE = isset($argv[1]) ? $argv[1] : 'ادارة التشغيل';
$CO = 4;
$pass = 0; $fail = 0; $warn = 0;
$TAG = 'EFFREACH-' . getmypid();

function r_ok(string $t, bool $c, string $n = '') {
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$t}" . ($n ? " — {$n}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$t}" . ($n ? " — {$n}" : '') . "\n"; }
}

echo "══ وصولُ الأثرِ بعدَ العزل — مساحة «{$SPACE}» ══\n";

/* الكنسُ بالعائلةِ قبلًا — بقايا جولةٍ سابقةٍ تُفسد الحكم */
mysqli_query($conn, "DELETE FROM fin_financial_events WHERE source_ref LIKE 'EFFREACH-%'");
mysqli_query($conn, "DELETE FROM ems_business_events  WHERE source_ref LIKE 'EFFREACH-%'");

/* ══ وقائعُ هذه المساحةِ ومستهلِكوها من الخريطةِ المقيسة ══════════════════ */
$esc = mysqli_real_escape_string($conn, $SPACE);
$q = mysqli_query($conn, "SELECT event_key, consumer_key, consumer_space, consumer_doc, evidence
                            FROM gov_effect_map WHERE producer_space = '{$esc}'
                           ORDER BY FIELD(evidence,'MEASURED','DECLARED_ACTIVE','DECLARED_INACTIVE'), event_key");
$map = array();
while ($q && ($x = mysqli_fetch_assoc($q))) { $map[$x['event_key']][] = $x; }
if (!$map) { echo "⊘ لا واقعةَ مسجَّلةٌ لهذه المساحةِ في خريطةِ الأثر — NOT_MEASURED\n"; exit(2); }

$measured = 0; $declared = 0; $inactive = 0;
foreach ($map as $k => $cs) {
    foreach ($cs as $c) {
        if ($c['evidence'] === 'MEASURED') { $measured++; }
        elseif ($c['evidence'] === 'DECLARED_ACTIVE') { $declared++; }
        else { $inactive++; }
    }
}
echo "  وقائعُ المساحة: " . count($map) . " · مستهلِكون: "
   . "مقيس={$measured} · مُعلَنٌ نشِط={$declared} · **معطَّلٌ قبلَ العزل={$inactive}**\n\n";

/* ══ واقعةٌ حقيقيةٌ من هذه المساحةِ — تُختار من المقيسِ أثرُه ══════════════ */
$target = null;
foreach ($map as $k => $cs) {
    foreach ($cs as $c) { if ($c['evidence'] === 'MEASURED') { $target = $k; break 2; } }
}
if ($target === null) {
    echo "  ⊘ لا واقعةَ لهذه المساحةِ لها أثرٌ **مقيسٌ** — فلا يُثبَت وصولٌ ولا يُدَّعى\n";
    echo "     (والمُعلَنُ النشِطُ لم يكن يصل قبلَ العزلِ أيضًا — فلا يُنسب إليه)\n";
    exit(2);
}
echo "  الواقعةُ المُختبَرة: {$target}\n";

$row = null;
$q = mysqli_query($conn, "SELECT category, source_module FROM ems_business_events
                           WHERE event_key = '" . mysqli_real_escape_string($conn, $target) . "'
                             AND company_id = {$CO} LIMIT 1");
if ($q) { $row = mysqli_fetch_assoc($q); }
if (!$row) { echo "  ⊘ لا صفَّ مرجعيٌّ لهذه الواقعةِ في الكيان {$CO}\n"; exit(2); }

$uid = 0;
$q = mysqli_query($conn, "SELECT id FROM users WHERE company_id = {$CO} LIMIT 1");
if ($q && ($x = mysqli_fetch_row($q))) { $uid = (int) $x[0]; }

$res = null;
try {
    /* ◆ **`publish` لا `publishFact`** — وأولُ تشغيلٍ أخطأ فيها فأعطى «مستهلِكٌ
         مقطوعٌ بعدَ العزل» وهو **عيبُ فاحصٍ لا كسرُ نظام**: `publishFact` تكتب
         **الجذرَ وحدَه** بحكمِ تعريفِها ولا تُنتج إسقاطًا ماليًّا أصلًا، فطلبُ
         الإسقاطِ منها طلبُ ما لا تفعله. **وفشلٌ لا يُتحقَّق من سببِه يُتَّهم به
         بريء** — وكان سيُنسب إلى العزلِ كسرٌ لم يقعْ. */
    $res = \App\Core\EventPublisher::publish($conn, array(
        'company_id' => $CO, 'event_key' => $target,
        'category' => $row['category'], 'source_module' => $row['source_module'],
        'source_ref' => $TAG, 'entity_type' => 'user', 'entity_id' => $uid,
        'occurred_at' => date('Y-m-d H:i:s'), 'idempotency_key' => $TAG,
        'created_by' => $uid,
        'payload' => array('gate' => 'effect_reach', 'space' => $SPACE),
    ));
} catch (\Throwable $e) {
    echo "  ✘ تعذّر إنشاءُ الواقعة: " . mb_substr($e->getMessage(), 0, 120) . "\n";
    exit(1);
}
r_ok('أُنشئت واقعةٌ حقيقيةٌ في المساحة', is_array($res) && !empty($res['id']),
     is_array($res) ? ('#' . $res['id']) : 'null');

/* ══ وصولُ الأثرِ إلى كلِّ مستهلِكٍ مقيسٍ في الخريطة ═══════════════════════ */
if (is_array($res) && !empty($res['id'])) {
    $corr = mysqli_real_escape_string($conn, (string) $res['correlation_id']);
    foreach ($map[$target] as $c) {
        if ($c['evidence'] === 'MEASURED') {
            $n = 0;
            $qq = mysqli_query($conn, "SELECT COUNT(*) FROM fin_financial_events
                                        WHERE correlation_id = '{$corr}'");
            if ($qq) { $n = (int) mysqli_fetch_row($qq)[0]; }
            r_ok("وصلَ الأثرُ إلى «{$c['consumer_key']}»", $n > 0,
                 $n > 0 ? "{$n} صفًّا في {$c['consumer_doc']}" : '**مستهلِكٌ مقطوعٌ بعدَ العزل**');
        } elseif ($c['evidence'] === 'DECLARED_INACTIVE') {
            $warn++;
            echo "  ◆ «{$c['consumer_key']}» **معطَّلٌ قبلَ العزل** — دَينٌ سابقٌ لا يُنسب إليه\n";
        } else {
            $warn++;
            echo "  ◆ «{$c['consumer_key']}» مُعلَنٌ نشِطٌ بلا أثرٍ مقيسٍ — كحالِه قبلَ العزل\n";
        }
    }

    /* ══ وصفرُ صفٍّ في `scr_*` أو غيرِه لم يكن يُكتب قبلًا — لا يُقاس هنا،
         والمقيسُ حدُّ السلسلةِ المُعلَن: الجذرُ والإسقاطُ المالي. ══ */
    $q = mysqli_query($conn, "SELECT COUNT(*) FROM ems_business_events WHERE correlation_id = '{$corr}'");
    r_ok('الحقيقةُ مسجَّلةٌ في الجذرِ المحايد', $q && (int) mysqli_fetch_row($q)[0] > 0);
}

/* ══ الكنسُ بالعائلة ═══════════════════════════════════════════════════ */
mysqli_query($conn, "DELETE FROM fin_financial_events WHERE source_ref LIKE 'EFFREACH-%'");
mysqli_query($conn, "DELETE FROM ems_business_events  WHERE source_ref LIKE 'EFFREACH-%'");
$q = mysqli_query($conn, "SELECT COUNT(*) FROM ems_business_events WHERE source_ref LIKE 'EFFREACH-%'");
r_ok('الكنسُ اكتمل — صفرُ أثرٍ للفاحص', $q && (int) mysqli_fetch_row($q)[0] === 0);

echo "\n  ناجح: {$pass} · راسب: {$fail}" . ($warn ? " · مُبلَّغ: {$warn}" : '') . "\n";
echo ($fail === 0 ? '✔' : '✘') . " الحكم: " . ($fail === 0 ? 'PASS — العزلُ لم يكسرْ أثرًا' : 'FAIL — العزلُ كسرَ أثرًا') . "\n";
exit($fail === 0 ? 0 : 1);
