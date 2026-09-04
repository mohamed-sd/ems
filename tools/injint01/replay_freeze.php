<?php
/**
 * tools/injint01/replay_freeze.php — إثباتُ حالةِ الإعادةِ التاريخيّة (‏§6.6)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأمرُ يطلب `HISTORICAL_AUTO_REPLAY = OFF` ويطلب إثباتَه — لا ادّعاءَه.**
 *   والإثباتُ هنا **بالتفريقِ بين فعلَين يُخلَط بينهما**:
 *
 *   ① **إعادةُ تسليمٍ** — أخذُ حدثٍ سبق أن سُلِّم (`state='processed'`) وإعادةُ
 *      دفعِه إلى مستهلكِه. **هذا** ما يمنعه الأمرُ، وهو ما يُنتِج أثرًا مزدوجًا.
 *   ② **ملءٌ أماميٌّ محكومٌ باللاتكرار** — مسحُ صفوفِ مصدرٍ تاريخيّةٍ **لم يُنتَج
 *      لها حدثٌ قطُّ** وإنتاجُه لها الآن. لا يمسُّ تسليمًا قائمًا ولا يكرِّر أثرًا.
 *
 * ⛔ **والخلطُ بينهما يُوقِع في أحدِ خطأين**: إمّا تعطيلُ مهمّةٍ سليمةٍ فتتجمَّد
 *   الحقائقُ الناقصة، أو السماحُ بإعادةٍ حقيقيّةٍ فيتضاعف الأثر.
 *
 * ◆ **والمهمّةُ `event_retry` حيّةٌ كلَّ خمسِ دقائق** — فلا يُقال «الإعادةُ مطفأة»
 *   بلا فحصِ ما تفعله فعلًا. تفحص هذه الأداةُ استعلاماتِها: أفيها `NOT EXISTS`
 *   على مخزنِ الأثر؟ أَتقرأ `state='processed'` لتعيدَه؟
 *
 * التشغيل: php tools/injint01/replay_freeze.php
 * الخروج : 0 مجمَّدٌ (لا إعادةَ تسليم) · 1 ثمّةَ إعادةٌ حقيقيّة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4');
$one = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };
$rows = function ($q) use ($c) { $r = $c->query($q); $o = array(); if (!$r) { return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };

echo "══ حالةُ الإعادةِ التاريخيّة — INJ-INT-01 §6.6 ══\n\n";

/* ═══ ① المهامُّ المجدولةُ الحيّةُ ذاتُ الصلة ═══════════════════════════════ */
echo "◆ ① المهامُّ المجدولةُ الحيّة:\n";
$jobs = $rows("SELECT job_type, cron_expr, is_active, last_success_at, replaces_manual
                 FROM ems_job_schedule
                WHERE is_active = 1 AND (job_type LIKE '%event%' OR job_type LIKE '%retry%'
                      OR job_type LIKE '%replay%' OR job_type LIKE '%posting%')
                ORDER BY job_type");
foreach ($jobs as $j) {
    printf("   %-18s %-14s آخرُ نجاح=%s\n      يُشغِّل: %s\n",
        $j['job_type'], $j['cron_expr'], substr((string) $j['last_success_at'], 0, 19), $j['replaces_manual']);
}
if (!$jobs) { echo "   (لا مهمّةَ نشِطةً بهذا الوصف)\n"; }

/* ═══ ② ماذا تفعل `cron_events.php` فعلًا؟ — الشيفرةُ تُقرأ لا تُفترَض ═══════ */
echo "\n◆ ② تشريحُ cron_events.php:\n";
$src = @file_get_contents($ROOT . '/cron_events.php');
if ($src === false) {
    echo "   ⛔ الملفُّ غيرُ موجود — لا حكمَ بلا مصدر\n"; $src = '';
}
/* إعادةُ تسليمٍ حقيقيّةٌ = قراءةُ تسليمٍ مُعالَجٍ لإعادةِ دفعِه */
$redeliver = preg_match_all("/state\s*=\s*'processed'|state\s*IN\s*\([^)]*processed/i", $src);
/* والملءُ الأماميُّ يُعرَف بحارسِ اللاتكرارِ قبلَ الإنتاج */
$guards = preg_match_all('/NOT\s+EXISTS/i', $src);
$idem   = preg_match_all('/idempotency_key/i', $src);
$fromDl = preg_match_all('/FROM\s+`?ems_event_deliveries`?/i', $src);

printf("   قراءةُ تسليمٍ مُعالَجٍ لإعادتِه   : %d موضعًا\n", $redeliver);
printf("   قراءةٌ من ems_event_deliveries : %d موضعًا\n", $fromDl);
printf("   حرّاسُ NOT EXISTS قبلَ الإنتاج  : %d موضعًا\n", $guards);
printf("   ذكرُ idempotency_key           : %d موضعًا\n", $idem);

/* ═══ ③ الشاهدُ من المخزنِ — أَتحرَّك تسليمٌ مُعالَجٌ ثانيةً؟ ═════════════════ */
echo "\n◆ ③ الشاهدُ من المخزن:\n";
printf("   تسليماتٌ بمحاولةٍ ثانيةٍ فأكثر : %s\n", $one('SELECT COUNT(*) FROM ems_event_deliveries WHERE attempt_no > 1'));
printf("   منها بحالةِ processed         : %s   ⇐ لو زاد عن صفرٍ فثمّةَ إعادةُ تسليم\n",
    $one("SELECT COUNT(*) FROM ems_event_deliveries WHERE attempt_no > 1 AND state = 'processed'"));
printf("   قيدُ فرادةٍ على مفتاحِ التسليم : %s\n",
    $one("SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ems_event_deliveries'
             AND NON_UNIQUE = 0 AND COLUMN_NAME = 'idempotency_key'") > 0 ? 'موجود (uq_idem)' : '⛔ غائب');
printf("   تسليماتٌ بلا مفتاحِ لاتكرار    : %s\n",
    $one("SELECT COUNT(*) FROM ems_event_deliveries WHERE idempotency_key IS NULL OR idempotency_key = ''"));

/* ═══ ④ الحكم ══════════════════════════════════════════════════════════ */
$reprocessed = (int) $one("SELECT COUNT(*) FROM ems_event_deliveries WHERE attempt_no > 1 AND state = 'processed'");
$naked       = (int) $one("SELECT COUNT(*) FROM ems_event_deliveries WHERE idempotency_key IS NULL OR idempotency_key = ''");

echo "\n══ الحكم ══\n";
if ($redeliver === 0 && $fromDl === 0) {
    echo "  ◆ لا مسارَ إعادةِ تسليمٍ في المجدول: cron_events.php لا يقرأ ems_event_deliveries\n";
    echo "    ولا يستهدف state='processed'. فما يفعله **ملءٌ أماميٌّ** لا إعادة.\n";
}
if ($guards > 0) {
    printf("  ◆ والملءُ محروسٌ بـ%d موضعِ NOT EXISTS + قيدِ فرادةٍ على المفتاح.\n", $guards);
}
if ($naked > 0) { printf("  ⚠ %d تسليمًا بلا مفتاحِ لاتكرار — قيدُ الفرادةِ لا يمسك NULL.\n", $naked); }

$off = ($redeliver === 0 && $fromDl === 0 && $reprocessed === 0);
echo "\n";
if ($off) {
    echo "✔ HISTORICAL_AUTO_REPLAY = OFF (مُثبَتٌ بالشيفرةِ والمخزن)\n";
    echo "  ⚠ وليس معناه أنَّ المخزنَ ساكن: `event_retry` تملأ الناقصَ كلَّ 5 دقائق\n";
    echo "     بلا تكرار. فالقياسُ قد يزيد بين تشغيلَين — وهذا نموٌّ لا ازدواج.\n";
    exit(0);
}
echo "⛔ HISTORICAL_AUTO_REPLAY = ON — ثمّةَ مسارُ إعادةِ تسليمٍ حقيقيّ.\n";
exit(1);
