<?php
/**
 * Finance/cron_depreciation_fin.php — الكرونُ الدوريُّ للإهلاك (M-30)
 * ═══════════════════════════════════════════════════════════════════════════
 * SPEC-01 #32: «**الإهلاكُ حدثٌ دوريٌّ** بمفتاح (الأصل × الفترة)».
 * «دوريٌّ» لا تعني زرًّا يتذكّره أحد — فهذا الكرون **يستدرك من شهر الاقتناء**
 * حتى آخر شهرٍ **منقضٍ** (ولا يُهلَك شهرٌ لم ينتهِ)، وهو **عاطلٌ بالمفتاح**:
 * تشغيلُه مرتين لا يكتب صفًّا ثانيًا ولا حدثًا ثانيًا.
 *
 * التشغيل: php Finance/cron_depreciation_fin.php [YYYY-MM] [company_id] [--dry]
 *   بلا وسائط: كلُّ الشركات حتى الشهر المنقضي.
 *   `--dry`: **يقيس ولا يكتب** — أولُ تشغيلٍ على قاعدةٍ لم تُهلَك منذ اقتناء
 *   أصولها يُنتج مئاتِ الأقساط دفعةً واحدة، وذلك **قرارُ مالكٍ لا أثرُ كرون**.
 *
 * ⚠ `config.php` يبتلع مخرجَ CLI أحيانًا — فالمخرجُ يُكتب بعد تفريغ المخازن.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_depreciation_fin.php'); // INJ-0025: لا تُشغَّل من المتصفّح
while (ob_get_level() > 0) { ob_end_clean(); }

require_once dirname(__DIR__) . '/app/Services/Finance/DepreciationService.php';
// ⚠ `config.php` يحمّل طبقةَ البوابة **كسولًا** داخل `ems_tenant_db()` وحدَها —
// فمن يبني بوابتَه بنفسه (كالكرون بسياق النظام) يلزمه استدعاءُ الأربعة صراحةً،
// وإلا فشل بـ«Class TenantRegistry not found» **بلا رسالةٍ** في CLI.
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';

use App\Services\Finance\DepreciationService as DEP;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$args    = array_slice($argv, 1);
$dry     = in_array('--dry', $args, true);
$args    = array_values(array_filter($args, function ($a) { return $a !== '--dry'; }));
$upTo    = (isset($args[0]) && preg_match('/^\d{4}-\d{2}$/', $args[0])) ? $args[0] : null;
$onlyCo  = isset($args[1]) ? (int) $args[1] : 0;
$ACTOR   = 1;   // مشغّلُ النظام

$cos = array();
$res = $conn->query("SELECT DISTINCT company_id FROM fin_assets WHERE COALESCE(is_deleted,0)=0");
while ($res && ($r = $res->fetch_assoc())) {
    $cid = (int) $r['company_id'];
    if ($cid > 0 && ($onlyCo <= 0 || $cid === $onlyCo)) { $cos[] = $cid; }
}

fwrite(STDOUT, "══ كرون الإهلاك (M-30) — " . date('Y-m-d H:i:s') . " ══\n");
if (!$cos) { fwrite(STDOUT, "لا شركة لها أصول.\n"); exit(0); }

// ⚠ گوتشا مثبَتة: بيئةُ `config.php` تبتلع الأخطاءَ غيرَ الملتقَطة في CLI —
// فالحلقةُ كلُّها ملفوفةٌ ويُطبع سببُ أي تعثُّرٍ صراحةً بدل صمتٍ يُظنّ نجاحًا.
$grandPosted = 0; $grandTotal = 0.0; $errors = 0;
foreach ($cos as $cid) {
  try {
    // سياقُ النظام لهذه الشركة (نمطُ المروحة: `forSystem` بشركة الكيان)
    try {
        $ctx  = \App\Core\TenantContext::forSystem($cid, $ACTOR, 'cron depreciation', true);
        $gate = new \App\Core\TenantDb($conn, $ctx);
    } catch (\Throwable $t) {
        fwrite(STDERR, "── شركة {$cid}: تعذر فتح سياق النظام — " . $t->getMessage() . "\n");
        $errors++;
        continue;
    }

    if ($dry) {
        // قياسٌ بلا كتابة: كم قسطًا وكم مبلغًا لو شُغّل — بحساب الخدمة نفسِها
        $assets = $gate->select('fin_assets', array('where' => array('state' => 'active'), 'orderBy' => 'id'));
        $n = 0; $sum = 0.0;
        foreach ($assets as $a) {
            foreach (DEP::missingPeriods($gate, $a, $upTo) as $p) {
                $c = DEP::computeFor($a, $p);
                if ($c['ok']) { $n++; $sum = round($sum + $c['amount'], 2); }
            }
        }
        // ⚠ المجموعُ تقديريٌّ: كلُّ قسطٍ يُحسب على **المجمّع الحالي** لا المتراكم
        // خلال الاستدراك — فالرقمُ سقفٌ أعلى لا نتيجةٌ حرفية، ويُعلَن كذلك.
        fwrite(STDOUT, "── شركة {$cid} [قياس بلا كتابة]: ~{$n} قسطا · ~{$sum} "
                     . "(سقف تقديري — المجمع لا يتراكم في القياس)\n");
        continue;
    }
    $r = DEP::catchUp($conn, $gate, $cid, $ACTOR, $upTo, 'cron');
    fwrite(STDOUT, "── شركة {$cid}: " . $r['reason'] . "\n");
    foreach ($r['periods'] as $p) {
        if ((int) $p['posted'] > 0) {
            fwrite(STDOUT, "     {$p['period']}: {$p['posted']} قسطا · {$p['total']}\n");
        } elseif ((int) $p['code'] === 423) {
            fwrite(STDOUT, "     {$p['period']}: ⏭ فترة مقفلة — تتخطى ولا تكسر\n");
            $errors++;
        }
    }
    $grandPosted += (int) $r['posted'];
    $grandTotal = round($grandTotal + (float) $r['total'], 2);
  } catch (\Throwable $t) {
      fwrite(STDOUT, "── شركة {$cid}: ✘ تعثر — " . get_class($t) . ': ' . $t->getMessage() . "\n");
      $errors++;
  }
}

fwrite(STDOUT, "══ المجموع: {$grandPosted} قسطا · {$grandTotal} · فترات مقفلة متخطاة: {$errors} ══\n");
exit(0);
