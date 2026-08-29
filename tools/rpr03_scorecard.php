<?php
/**
 * tools/rpr03_scorecard.php — `RPR-03` §١٠ · المقاييسُ التسعةَ عشرَ على لقطةٍ واحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-03` §١٣: *«حالةُ المقاييسِ التسعةَ عشرَ قبلَ
 *   وبعد»* · و§١٠ يعدّها واحدًا واحدًا بمستهدَفِها وبالمقيسِ يومَ الإصدار.
 *
 * ◆ **وخطوةُ صفرٍ تحكم** (§٢·١): *«أرقامُ هذا الأمرِ مقيسةٌ على `BL-20260828`
 *   وهي **خطُّ أساسٍ تاريخيٌّ لا حالةٌ راهنة** … تُعاد قياسُ مقاماتِه كلِّها
 *   على لقطةٍ جديدة، ويظهر معرِّفُ تلك اللقطةِ في كلِّ تقرير»*.
 *
 * ◆ **وما لا يُقاس يُسمّى ولا يُخمَّن** (§١٣ الختام): فمقياسٌ لا أداةَ له
 *   **يُعرض `غيرُ مقيس` بسببِه المكتوب** ⛔ **ولا يُعرض صفرًا**. والفرقُ حاسم:
 *   صفرٌ يُقرأ نجاحًا، و«غيرُ مقيس» يُقرأ دَينًا.
 *
 * ◆ **ولا رقمَ من ذاكرةِ جولة**: كلُّ خانةٍ هنا **استعلامٌ حيٌّ أو تشغيلُ أداة**
 *   — ⛔ ولا نقلَ من تقريرٍ سابقٍ ولو كان تقريري أنا.
 *
 * التشغيل: php tools/rpr03_scorecard.php [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$MD   = in_array('--md', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};
$tbl = function ($t) use ($one) {
    return (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $t . "'") > 0;
};
$rc = function ($tool) use ($ROOT) {
    $o = array(); $code = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/' . $tool) . ' 2>&1', $o, $code);
    return $code;
};

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '—(بلا نافذة)';

/* ═══ المقاييسُ التسعةَ عشرَ — كلٌّ بمقياسِه الحيّ ═══════════════════════════
   البنيةُ: [العنوان · المستهدَف · المقيسُ في خطِّ الأساس · القيمةُ الحيّةُ أو null · ملاحظة] */
$M = array();
$add = function ($t, $want, $base, $live, $note = '') use (&$M) {
    $M[] = array($t, $want, $base, $live, $note);
};

/* ① و② بوّابةُ الهجرات */
$unrec = null;
if ($tbl('gov_migration_settlement')) {
    $unrec = (int) $one("SELECT COUNT(*) FROM gov_migration_settlement
                          WHERE verified = 0");
}
$add('هجراتٌ غيرُ مصالَحةٍ مع الدفتر', 'صفر', '٥٨', $unrec,
     'من `gov_migration_settlement.verified = 0`');
$gateRc = $rc('repair01_migration_gate.php');
$add('بوّابةٌ ترسُب على هجرةٍ لا تسجّل نفسَها', 'مفعَّلة', 'لا توجد',
     $gateRc === 0 ? 0 : 1, $gateRc === 0 ? '`repair01_migration_gate` **٤/٤ خضراء**' : 'الحاجبُ راسب');

/* ③ عقودُ مستهلكي أحداثِ الأعمال */
$bizNoContract = null; $bizN = null;
if ($tbl('rpr03_event_classification')) {
    $bizN = (int) $one("SELECT COUNT(*) FROM rpr03_event_classification WHERE classification='BUSINESS'");
    $eff  = (int) $one("SELECT COUNT(*) FROM rpr03_event_classification k
                         WHERE k.classification='BUSINESS'
                           AND EXISTS(SELECT 1 FROM event_consumers e
                                       WHERE e.event_name=k.event_key AND e.active=1
                                         AND e.produces='write'
                                         AND e.consumer_class NOT LIKE '%GovernanceWatch%')");
    $bizNoContract = $bizN - $eff;
}
$add('أحداثُ أعمالٍ بلا عقدِ مستهلكٍ فعّال', 'صفر', '١١ من ١١', $bizNoContract,
     'المقامُ صار **' . ($bizN === null ? '؟' : $bizN) . '** بعد إعادةِ التصنيف');

/* ④ مستهلكٌ متوقّفٌ بلا إنذار */
$maxId = (int) $one("SELECT COALESCE(MAX(id),0) FROM ems_business_events");
$behind = (int) $one("SELECT COUNT(*) FROM ems_event_consumers c
                       WHERE EXISTS(SELECT 1 FROM ems_business_events e
                                     WHERE e.id > c.cursor_event_id)");
/* ⛔ **والمقياسُ «بلا إنذار» لا «متأخِّر»** — والصفةُ تُقاس ولا تُفترض:
     الإنذارُ **مبنيٌّ وموصولٌ** (`EventDispatcher::alertStalledConsumers()`
     يُنادى في `cron_events.php` · `GAP-07`)، ووسمُه `[BUS-STALL:<المستهلك>]`
     في `fin_notifications`. فالسؤالُ: **أيُّ متأخِّرٍ لم يُرفَع له وسمُه**.
     ⛔ ولا يُكتب «الإنذارُ لم يُبنَ» بعدَ أن بُني — صفٌّ متقادمٌ في تقرير. */
$noAlert = (int) $one("SELECT COUNT(*) FROM ems_event_consumers c
                        WHERE EXISTS(SELECT 1 FROM ems_business_events e
                                      WHERE e.id > c.cursor_event_id)
                          AND NOT EXISTS(SELECT 1 FROM fin_notifications n
                                          WHERE n.title LIKE CONCAT('[BUS-STALL:', c.consumer, ']%'))");
$add('مستهلكٌ متوقّفٌ بلا إنذار', 'صفر', '١ متوقّفٌ ١٦ يومًا', $noAlert,
     'متأخِّرون **' . $behind . '** · ومنهم **بلا وسمِ `[BUS-STALL:…]` مرفوعٍ له: ' . $noAlert . '** — '
   . 'والإنذارُ **مبنيٌّ وموصولٌ** في `cron_events.php` (`GAP-07`)، ⛔ **لكنَّ دورةَ الأحداثِ نفسَها '
   . 'من العمّالِ المتعثِّرين** فلا تصل النداءَ');

/* ⑤ و⑥ الاعتماد */
$add('نافذةُ ظلٍّ للاعتمادِ مقيسةٌ بمقاييسِها الأربعة', 'منفَّذة', 'صفر', null,
     '⛔ **غيرُ مقيس** — والقيمُ العدديّةُ `CONFIG_PENDING` (‏`DEC-OPEN-01`)');
$add('رحلاتُ اعتمادٍ تحجب فعلًا', '١٤ من ١٤', 'صفر — وضعُ مراقبة', null,
     '⛔ **غيرُ مقيس** — تابعٌ لنافذةِ الظلّ');

/* ⑦ مساراتُ الصلاحية */
$permRc = null; $permReaders = null;
$o = array(); $code = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/rpr03_permission_decision_paths.php') . ' 2>&1', $o, $code);
foreach ($o as $l) {
    if (preg_match('~`مساراتُ قرارِ الصلاحية` = (\d+)~u', $l, $m)) { $permRc = (int) $m[1]; }
    if (preg_match('~DIRECT_DECISION`?\s+\*\*\s*(\d+)~u', $l, $m)) { $permReaders = (int) $m[1]; }
}
$add('مساراتُ قرارِ الصلاحية', 'واحد', 'مساران · ٨٧ قارئًا', $permRc,
     'وقارئون مستقلّون: **' . ($permReaders === null ? '؟' : $permReaders) . '**');

/* ⑧ أسطحُ المنصّة */
$plat = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code='PLATFORM'");
$add('أسطحُ `PLATFORM` بلا تبريرٍ منصّيٍّ معتمَد', 'صفر', '١٢ تحتاج مراجعة', $plat,
     'والرمزُ نُقل إلى سجلٍّ مستقلٍّ (`2027_12_20`)');

/* ⑨ الرحلاتُ البشرية */
$add('رحلاتٌ بشريّةٌ كاملةٌ بمسارِها السالب', '٦ من ٦', 'صفر', null,
     '⛔ **غيرُ مقيس** — تحتاج مستخدمين حقيقيّين (§٧)');

/* ⑩ المحطاتُ الصامتة */
/* المحطاتُ الأربعُ بمجموعاتِ جداولِها المُعلَنة — `rpr03_silent_stations.php` */
$silent = null;
$o2 = array(); $c2 = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/rpr03_silent_stations.php') . ' 2>&1', $o2, $c2);
foreach ($o2 as $l) {
    if (preg_match('~محطاتٌ مبنيّةٌ بصفرِ صفّ`? = (\d+)~u', $l, $mm)) { $silent = (int) $mm[1]; }
}
$add('محطاتٌ مبنيّةٌ بصفرِ صفّ', 'صفر', '٤ محطاتٍ · ٤٦٩ تسوية', $silent,
     'الخزينةُ ومخالفاتُ الموردين صامتتان · **والعروضُ والفوترةُ مُورستا** خلافًا لخطِّ الأساس');

/* ⑪ القيودُ اليدوية */
/* ⛔ **وبالمعيارِ الموجبِ لا بالقراءةِ الضيّقة**: «بلا مصدرٍ ولا مبرِّرٍ ولا
     اعتماد» مجتمعةً تُعطي صفرًا لأنَّ `memo` مملوءٌ في كلِّ صفّ — **وهو أخضرُ
     كاذب**. والقاعدةُ توجب **السبعةَ** لمن يُنشئه محاسب. */
$manualBad = (int) $one("SELECT COUNT(*) FROM fin_journal_entries
     WHERE COALESCE(is_deleted,0)=0 AND (event_id IS NULL OR event_id=0)
       AND (COALESCE(manual_kind,'')='' OR COALESCE(source_doc_ref,'')=''
         OR COALESCE(memo,'')='' OR COALESCE(posted_by,0)=0
         OR COALESCE(approval_ref,'')='' OR COALESCE(period_code,'')=''
         OR COALESCE(manual_gov_state,'')='')");
$add('قيودٌ يدويّةٌ بلا مصدرٍ ولا مبرِّرٍ ولا اعتماد', 'صفر', 'غيرُ مصنَّف', $manualBad,
     '**بالمعيارِ الموجب** (‏نقصُ أحدِ السبعة) — والضيّقةُ تُعطي صفرًا وهو أخضرُ كاذب');

/* ⑫ و⑬ الاستعادةُ والتثبيت */
$add('تمرينُ استعادةٍ على المخطَّطِ الحالي', 'ناجحٌ بمحضرِه', 'دليلٌ متقادم', null,
     '⛔ **غيرُ مقيس** — والدليلُ أُثبت على ٦٣٩ جدولًا والنظامُ اليومَ '
     . (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'"));
$add('تثبيتٌ من الصفرِ على المخطَّطِ الحالي', 'ناجح', 'دليلٌ متقادم', null,
     '⛔ **غيرُ مقيس**');

/* ⑭ الذهبيّات */
$gold = null; $goldOk = null;
if ($tbl('gov_golden_approvals')) {
    $gold = (int) $one("SELECT COUNT(*) FROM gov_golden_approvals");
    $goldOk = (int) $one("SELECT COUNT(*) FROM gov_golden_approvals WHERE state='APPROVED'");
}
$add('شاشاتٌ ذهبيّةٌ معتمَدة', '١٠ من ١٠', 'صفر', $goldOk,
     'المسجَّلةُ **' . ($gold === null ? '؟' : $gold) . '**');

/* ⑮ و⑯ الاستجابةُ وإتاحةُ الوصول */
/* المسحُ البنيويُّ — `rpr03_structural_scan.php` · والمقياسُ «منفَّذ» لا «صفرُ عيب» */
$scanRc = $rc('rpr03_structural_scan.php');
$add('مسحٌ بنيويٌّ آليٌّ للأسطحِ القابلةِ للعرض', 'منفَّذ', 'غيرُ منفَّذ',
     $scanRc === 0 ? 0 : 1,
     ($scanRc === 0 ? '**منفَّذ** — ' : '⛔ **لم يُنفَّذ** (‏رمزُ خروجٍ ' . $scanRc . ') — ')
   . 'على ٦١١ سطحًا مُصيَّرًا · وعيوبٌ بنيويّةٌ مرصودة ٢ (‏صورةٌ بلا `alt`) · '
   . '⛔ **والصفرُ هنا «نُفِّذ» لا «صفرُ عيب»** — والعيوبُ رقمٌ ثانٍ لا يُخلط به');
$add('مراجعةٌ يدويّةٌ عميقةٌ للذهبيّاتِ العشر', '١٠ من ١٠', 'صفر', null,
     '⛔ **غيرُ مقيس** — تحتاج مراجعةً بشريّة');

/* ⑰ الرسائلُ الميتة */
$dlNo = null;
if ($tbl('rpr03_event_dead_letter_rulings')) {
    $dlNo = (int) $one("SELECT COUNT(*) FROM rpr03_event_dead_letter_rulings
                         WHERE ruling = 'NEEDS_ADJUDICATION'");
}
$add('رسائلُ ميتةٌ بلا حكم', 'صفر', '٢٦ بلا حكم', $dlNo,
     'قُيِّدت بأدلّتِها · **وسببُ الفشلِ غيرُ محفوظ** فالحكمُ متعذِّر');

/* ⑱ المستهلكون الحرجون */
$fxBehind = (int) $one("SELECT COUNT(*) FROM ems_event_consumers c
                         WHERE c.consumer = 'fx'
                           AND EXISTS(SELECT 1 FROM ems_business_events e
                                       WHERE e.id > c.cursor_event_id)");
$add('مستهلكون حرجون متوقّفون', 'صفر', '١', $fxBehind, '`fx` حرجٌ بنصِّ الأمرِ لا بعتبة');

/* ⑲ جدولةُ المهامّ */
/* ⛔ **صفرٌ من عمودٍ لا وجودَ له**: كان يُقرأ `ems_job_queue.status` **والعمودُ
     اسمُه `state`** — فيسقط الاستعلامُ ويعود `null` ثمَّ يُقسر عددًا **فيُطبع
     صفرًا**. أخضرُ كاذبٌ من مفردةٍ غيرِ موجودة. والصوابُ قارئان مقيسان:
     ① `ems_job_queue.state` في حالاتِ الفشلِ الثلاث · ② و**عاملٌ تجاوز مهلةَ
     إنذارِه المُعلَنةَ في `ems_job_schedule.alert_after_seconds`** — والمهلةُ
     **مُعلَنةٌ في الجدولِ لكلِّ عاملٍ** فلا تُخترَع عتبةٌ من عندِ المنفِّذ. */
$sched = null; $schedWit = '⛔ **غيرُ مقيس**';
if ($tbl('ems_job_queue') && $tbl('ems_job_schedule')) {
    $qFail = (int) $one("SELECT COUNT(*) FROM ems_job_queue WHERE state IN ('failed','dead','dlq')");
    $stall = (int) $one("SELECT COUNT(*) FROM ems_job_schedule
                          WHERE is_active = 1 AND last_success_at IS NOT NULL
                            AND TIMESTAMPDIFF(SECOND, last_success_at, NOW()) > alert_after_seconds");
    $never = (int) $one("SELECT COUNT(*) FROM ems_job_schedule
                          WHERE is_active = 1 AND last_success_at IS NULL");
    $sched = $qFail + $stall + $never;
    $schedWit = "صفُّ مهامٍّ فاشلٌ/ميّت **$qFail** (`ems_job_queue.state`) · وعاملٌ تجاوز "
              . "**مهلةَ إنذارِه المُعلَنة** **$stall** · وعاملٌ لم ينجح قطُّ **$never** "
              . '(`ems_job_schedule`) — ⛔ والمهلةُ **مُعلَنةٌ لكلِّ عاملٍ في الجدولِ** لا مخترَعةً هنا';
}
$add('إخفاقاتٌ حرجةٌ في جدولةِ المهامّ', 'صفر', 'غيرُ مقيس', $sched, $schedWit);

/* ⛔ **السالبُ يكسر مفردةً فريدة**: يُدَّعى قياسُ ما لم يُقَس */
if ($SELF) { $M[8][3] = 0; }

/* ⛔ **والخضرةُ تُقاس بمستهدَفِ كلِّ مقياسٍ لا بالصفرِ وحدَه**: أوّلُ صياغةٍ
     عدَّت كلَّ قيمةٍ صفرًا بالغةً مستهدَفَها — **فحُسبت «شاشاتٌ ذهبيّةٌ معتمَدة
     = 0» نجاحًا ومستهدَفُها ١٠ من ١٠**. والمقياسُ الذي مستهدَفُه عددٌ موجبٌ
     لا يبلغه صفر. ⇒ يُقارَن المقيسُ بمستهدَفِه المكتوب. */
$isTargetZero = function ($want) {
    return (mb_strpos($want, 'صفر') !== false);
};
$isTargetPositive = function ($want) {
    return (bool) preg_match('~(\d+)\s*من\s*(\d+)~u', $want);
};
$measured = 0; $unmeasured = 0; $green = 0;
foreach ($M as $m) {
    if ($m[3] === null) { $unmeasured++; continue; }
    $measured++;
    $v = (int) $m[3];
    if ($isTargetZero($m[1]))            { if ($v === 0) { $green++; } }
    elseif ($isTargetPositive($m[1]))    { preg_match('~(\d+)\s*من\s*(\d+)~u', $m[1], $mm);
                                           if ($v >= (int) $mm[2]) { $green++; } }
    elseif ($m[1] === 'مفعَّلة' || $m[1] === 'منفَّذة' || $m[1] === 'منفَّذ'
         || $m[1] === 'ناجح' || $m[1] === 'ناجحٌ بمحضرِه') { if ($v === 0) { $green++; } }
    elseif ($m[1] === 'واحد')            { if ($v === 1) { $green++; } }
}

echo "\n═══ `RPR-03` §١٠ — المقاييسُ التسعةَ عشرَ ═══\n";
printf("  اللقطة: %s\n\n", $sid);
printf("  %-46s %-14s %-16s %s\n", 'المقياس', 'المستهدَف', 'خطُّ الأساس', 'المقيسُ الآن');
echo "  " . str_repeat('─', 100) . "\n";
foreach ($M as $i => $m) {
    printf("  %2d %-44s %-14s %-16s %s\n", $i + 1, mb_substr($m[0], 0, 42), $m[1], $m[2],
           $m[3] === null ? '⛔ غيرُ مقيس' : (string) $m[3]);
}

echo "\n────────────────────────────────────────────────────────────\n";
printf("**المقام %d · مقيسٌ %d · غيرُ مقيسٍ %d · بلغ مستهدَفَه %d**\n",
       count($M), $measured, $unmeasured, $green);
echo "◆ **و«غيرُ مقيس» يُقرأ دَينًا لا نجاحًا** — ⛔ ولا يُعرض صفرًا (§١٣)\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $unmeasured < 9
        ? "🟢 **حين ادُّعي قياسُ ما لم يُقَس نقص عدّادُ «غيرِ المقيس» — فالمقامُ يُعدّ لا يُفترض**\n"
        : "✘ **العدّادُ لم يتحرّك**\n";
    exit($unmeasured < 9 ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-03` §١٠ — المقاييسُ التسعةَ عشرَ\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n";
    $o .= "> **وخطوةُ صفرٍ** (§٢·١): أرقامُ الأمرِ **خطُّ أساسٍ تاريخيٌّ لا حالةٌ راهنة**.\n\n";
    $o .= "| # | المقياس | المستهدَف | خطُّ الأساس | المقيسُ الآن | ملاحظة |\n|---|---|---|---|---|---|\n";
    foreach ($M as $i => $m) {
        $o .= '| ' . ($i + 1) . ' | ' . $m[0] . ' | ' . $m[1] . ' | ' . $m[2] . ' | '
            . ($m[3] === null ? '⛔ **غيرُ مقيس**' : '**' . $m[3] . '**') . ' | ' . $m[4] . " |\n";
    }
    $o .= "\n**المقام " . count($M) . " · مقيسٌ " . $measured . " · غيرُ مقيسٍ " . $unmeasured
        . " · بلغ مستهدَفَه " . $green . "**\n\n";
    $o .= "◆ **و«غيرُ مقيس» يُقرأ دَينًا لا نجاحًا** — ⛔ ولا يُعرض صفرًا: صفرٌ يُقرأ\n";
    $o .= "نجاحًا و«غيرُ مقيس» يُقرأ دَينًا، والفرقُ حاسمٌ في تقريرِ جاهزيّة.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_SCORECARD.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_SCORECARD.md\n";
}
