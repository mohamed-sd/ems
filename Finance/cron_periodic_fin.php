<?php
/**
 * Finance/cron_periodic_fin.php — كرونُ الدوريات الثلاث (M-41)
 * ═══════════════════════════════════════════════════════════════════════════
 * SPEC-01 #23 · #30 · #22 — «حدثٌ دوريٌّ آليّ» في الثلاث: مخصصُ الصيانة
 * (المعدة × الفترة) · قسطُ التمويل (الالتزام × القسط) · الإقرارُ (الفترة).
 * كلُّها **عاطلةٌ بمفاتيحها**: تشغيلُه مرتين لا يكتب صفًّا ثانيًا ولا حدثًا ثانيًا.
 *
 * التشغيل: php Finance/cron_periodic_fin.php [YYYY-MM] [company_id] [--dry]
 *   بلا وسائط: الشهرُ المنقضي لكل شركة. و`--dry` **يقيس ولا يكتب**.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

require_once dirname(__DIR__) . '/app/Services/Finance/PeriodicEventService.php';
// طبقةُ البوابة تُحمَّل كسولًا في config.php — فمن يبني بوابتَه بنفسه يستدعيها
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';

use App\Services\Finance\PeriodicEventService as PES;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$args   = array_values(array_filter(array_slice($argv, 1), function ($a) { return $a !== '--dry'; }));
$dry    = in_array('--dry', array_slice($argv, 1), true);
$period = (isset($args[0]) && preg_match('/^\d{4}-\d{2}$/', $args[0]))
          ? $args[0] : date('Y-m', strtotime('first day of last month'));
$onlyCo = isset($args[1]) ? (int) $args[1] : 0;
$ACTOR  = 1;

$cos = array();
// ⚠ `admin_companies` **بلا `is_deleted`** — والشرطُ عليه يُرجع صفرَ صفٍّ صامتًا
// (فحصَ العمودُ قبل الاعتماد عليه؛ والقاعدةُ: افحص المخطط لا تفترضه)
$res = $conn->query("SELECT id FROM admin_companies ORDER BY id");
while ($res && ($r = $res->fetch_assoc())) {
    $cid = (int) $r['id'];
    if ($cid > 0 && ($onlyCo <= 0 || $cid === $onlyCo)) { $cos[] = $cid; }
}

fwrite(STDOUT, "══ كرونُ الدوريات (M-41) — الفترة {$period} — " . date('Y-m-d H:i:s') . " ══\n");
if (!$cos) { fwrite(STDOUT, "لا شركةَ فعّالة.\n"); exit(0); }

foreach ($cos as $cid) {
    try {
        $ctx  = \App\Core\TenantContext::forSystem($cid, $ACTOR, 'cron periodic', true);
        $gate = new \App\Core\TenantDb($conn, $ctx);

        if ($dry) {
            $prov = PES::provisionsOf($gate, $period);
            $due  = PES::dueInstallments($gate, date('Y-m-t', strtotime($period . '-01')));
            $unaccrued = 0;
            foreach ($due as $d) { if ($d['event_id'] === null) { $unaccrued++; } }
            $rets = PES::returnsOf($gate, 5);
            $filed = false;
            foreach ($rets as $t) { if ((string) $t['period_ref'] === $period) { $filed = true; } }
            fwrite(STDOUT, "── شركة {$cid} [قياسٌ بلا كتابة]: مخصصاتٌ قائمة " . count($prov)
                . " · أقساطٌ مستحقةٌ بلا اعتراف {$unaccrued} · إقرارُ الفترة "
                . ($filed ? 'مقدَّم' : '**غيرُ مقدَّم**') . "\n");
            continue;
        }

        $p = PES::runProvisions($conn, $gate, $cid, $period, $ACTOR, 'cron');
        fwrite(STDOUT, "── شركة {$cid} · ① {$p['reason']}\n");

        $i = PES::accrueInstallments($conn, $gate, $cid, date('Y-m-t', strtotime($period . '-01')), $ACTOR);
        fwrite(STDOUT, "── شركة {$cid} · ② {$i['reason']}\n");

        $t = PES::fileTaxReturn($conn, $gate, $cid, $period, $ACTOR);
        fwrite(STDOUT, "── شركة {$cid} · ③ " . ($t['ok'] ? $t['reason'] : ($t['code'] . ' — ' . $t['reason'])) . "\n");
    } catch (\Throwable $e) {
        fwrite(STDOUT, "── شركة {$cid}: ✘ تعثّر — " . get_class($e) . ': ' . $e->getMessage() . "\n");
    }
}

fwrite(STDOUT, "══ انتهى ══\n");
exit(0);
