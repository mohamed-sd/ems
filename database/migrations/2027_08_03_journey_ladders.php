<?php
/**
 * 2027_08_03_journey_ladders.php — سلاليمُ الرحلاتِ الثلاث · والناقصُ يُسجَّل فجوة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب (خامسًا): «حدِّدْ سلاليمَ الرحلاتِ **وابنِ الناقصَ منها فقط**»،
 *   و(سادسًا): «**لا تُبنى شاشةٌ جديدةٌ لأجلِ رحلة** — والناقصُ **يُسجَّل فجوةً
 *   ويُرفع لي**»، و(سابعًا): «الـ13 سلسلةً **مرتّبةً بحسبِ الرحلات**».
 *
 * ◆ **والنتيجةُ الأهمُّ أن التشخيصَ المُعلَنَ غيرُ دقيق**: v25 تقول «**13 سلسلةَ
 *   اعتمادٍ لم تُبنَ**». والمقيس:
 *   · الثلاثَ عشرةَ **مسجَّلةٌ كلُّها** في `gov_ladders` بخطواتِها (1–3 خطوات).
 *   · و**شاشةُ القيادةِ لكلٍّ منها موجودةٌ وحيةٌ وفي السايدبار** — قِيست
 *     بمطابقةِ `nav_items.route` النشِط، من 1 إلى 24 موضعًا لكلِّ شاشة.
 *   **فالرحلاتُ الثلاثُ ماشيةٌ اليومَ**، ولا تنقصها شاشةٌ واحدة.
 *
 * ◆ **والناقصُ شيءٌ آخرُ تمامًا — وهو الفجوةُ التي تُرفع**: **ولا شاشةَ واحدةٌ
 *   تُسمّي سلّمَها**. بحثُ الشيفرةِ الحيةِ عن `LD-01`…`LD-13` أعطى **موضعًا
 *   واحدًا** هو `Governance/authority_caps.php` — **شاشةُ عرضٍ لا قيادة**.
 *   فالسلّمُ **مُعلَنٌ ولا يُنفَّذ**: ترتيبُ خطواتِه وسقفُ صلاحيتِه و«لا يدَ تمشي
 *   خطوتَين» — كلُّها مكتوبةٌ في السجلِّ ولا شيءَ يقرؤها لحظةَ الاعتماد.
 *   **وسلّمٌ لا يقرؤه المُنفِّذُ وثيقةٌ لا حارس.**
 *
 * ◆ **فلم تُبنَ شاشةٌ ولا وُصل سلّمٌ**: الوصلُ يغيّر **مَن يعتمدُ وبأيِّ ترتيبٍ
 *   وبأيِّ سقف** — وهو من مجالِ الصلاحياتِ الذي لا يُطبَّق فيه بلا اعتراضِك.
 *   والمُنفَّذُ هنا **تسجيلُ الخريطةِ والفجوة** ليصير الدَّينُ مقيسًا باسمِه.
 *
 * ◆ **و`LD-01` في رحلتَين قصدًا** (الإيرادُ والمشغِّل): التايم شيتُ أصلُ
 *   الاثنتَين. **ولا يُطرح من أحدِهما لتبدوَ الأرقامُ نظيفة** — فالمجموعُ
 *   14 موضعًا لثلاثةَ عشرَ سلّمًا، والفرقُ مُعلَنٌ لا مطموس.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* ══ ١ جدولُ عضويةِ الرحلات — علاقةُ كثيرٍ لكثيرٍ لأن سلّمًا يخدم رحلتَين ══ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_journey_ladders` (
    `journey_code` VARCHAR(20)  NOT NULL COMMENT 'JR-REV إيراد · JR-SUP مورّد · JR-OPR مشغّل',
    `journey_ar`   VARCHAR(80)  NOT NULL,
    `seq_no`       TINYINT      NOT NULL COMMENT 'ترتيبُ السلّمِ داخلَ الرحلة',
    `ladder_code`  VARCHAR(20)  NOT NULL,
    `driver_route` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'الشاشةُ التي تقود هذه الخطوةَ اليوم',
    `driver_state` ENUM('LIVE_IN_NAV','FILE_ONLY','MISSING') NOT NULL DEFAULT 'MISSING'
                   COMMENT 'مقيسٌ من nav_items النشِط ومن القرص — لا مُعلَنٌ يدويًّا',
    `nav_hits`     SMALLINT     NOT NULL DEFAULT 0 COMMENT 'مواضعُ الشاشةِ في سايدبارِ الأدوار',
    `ladder_wired` TINYINT(1)   NOT NULL DEFAULT 0
                   COMMENT 'أتقرأ الشاشةُ سلّمَها لحظةَ الاعتماد؟ — الفجوةُ المرفوعة',
    `gap_note`     VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`journey_code`, `seq_no`),
    KEY `ix_gjl_ladder` (`ladder_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='خامسًا/سابعًا — الـ13 سلّمًا مرتّبةً بحسبِ الرحلاتِ الثلاث'");

/* ══ ٢ الخريطة — الترتيبُ من تسلسلِ العملِ لا من رقمِ السلّم ══════════════ */
$MAP = array(
    /* رحلةُ الإيراد: من ساعةِ العملِ إلى الفاتورةِ المعتمَدة */
    array('JR-REV', 'رحلةُ الإيراد', 1, 'LD-01', 'Approvals/hours_approval.php'),
    array('JR-REV', 'رحلةُ الإيراد', 2, 'LD-02', 'Approvals/attribution_board.php'),
    array('JR-REV', 'رحلةُ الإيراد', 3, 'LD-05', 'Finance/approvals_inbox.php'),
    array('JR-REV', 'رحلةُ الإيراد', 4, 'LD-06', 'Finance/acc_approval_chain.php'),
    array('JR-REV', 'رحلةُ الإيراد', 5, 'LD-07', 'Finance/approvals_inbox.php'),
    /* رحلةُ المورّد: من طلبِ الشراءِ إلى السدادِ من الخزينة */
    array('JR-SUP', 'رحلةُ المورّد', 1, 'LD-10', 'Procurement/requests_proc.php'),
    array('JR-SUP', 'رحلةُ المورّد', 2, 'LD-11', 'Procurement/rfq_compare_award.php'),
    array('JR-SUP', 'رحلةُ المورّد', 3, 'LD-12', 'Procurement/receipt_custody_proc.php'),
    array('JR-SUP', 'رحلةُ المورّد', 4, 'LD-03', 'Approvals/requests.php'),
    array('JR-SUP', 'رحلةُ المورّد', 5, 'LD-13', 'Finance/approvals_inbox.php'),
    array('JR-SUP', 'رحلةُ المورّد', 6, 'LD-08', 'Finance/payments_fin.php'),
    array('JR-SUP', 'رحلةُ المورّد', 7, 'LD-09', 'Finance/payments_fin.php'),
    /* رحلةُ المشغّل: من التايم شيتِ إلى اعتمادِ وحداتِه */
    array('JR-OPR', 'رحلةُ المشغّل', 1, 'LD-01', 'Approvals/hours_approval.php'),
    array('JR-OPR', 'رحلةُ المشغّل', 2, 'LD-04', 'Approvals/requests.php'),
);

/* ══ ٣ حالةُ كلِّ شاشةٍ تُقاس ولا تُعلَن ══════════════════════════════════ */
$ins = $conn->prepare(
    "INSERT INTO gov_journey_ladders
        (journey_code, journey_ar, seq_no, ladder_code, driver_route, driver_state, nav_hits, ladder_wired, gap_note)
     VALUES (?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
        ladder_code=VALUES(ladder_code), driver_route=VALUES(driver_route),
        driver_state=VALUES(driver_state), nav_hits=VALUES(nav_hits),
        ladder_wired=VALUES(ladder_wired), gap_note=VALUES(gap_note)"
);
if (!$ins) { exit("تعذّر التحضير: {$conn->error}\n"); }

$navQ = $conn->prepare("SELECT COUNT(*) FROM nav_items WHERE active = 1 AND route = ?");
$live = 0; $fileOnly = 0; $missing = 0; $wired = 0; $rows = 0;

foreach ($MAP as $m) {
    list($jc, $jar, $seq, $lc, $route) = $m;

    $navQ->bind_param('s', $route);
    $navQ->execute();
    $hits = (int) $navQ->get_result()->fetch_row()[0];
    $onDisk = is_file($ROOT . '/' . $route);
    $state = $hits > 0 ? 'LIVE_IN_NAV' : ($onDisk ? 'FILE_ONLY' : 'MISSING');
    if ($state === 'LIVE_IN_NAV') { $live++; } elseif ($state === 'FILE_ONLY') { $fileOnly++; } else { $missing++; }

    /* ◆ الوصلُ يُقاس من الشيفرةِ الحية: أتذكر الشاشةُ رمزَ سلّمِها؟ */
    $isWired = 0;
    if ($onDisk) {
        $src = (string) @file_get_contents($ROOT . '/' . $route);
        if (strpos($src, $lc) !== false || strpos($src, 'gov_ladder_steps') !== false) { $isWired = 1; }
    }
    if ($isWired) { $wired++; }

    $gap = $isWired ? ''
        : 'الشاشةُ تقودُ الخطوةَ ولا تقرأ سلّمَها — فترتيبُ الخطواتِ والسقفُ و«لا يدَ تمشي خطوتَين» غيرُ مُنفَّذة';

    $ins->bind_param('ssisssiis', $jc, $jar, $seq, $lc, $route, $state, $hits, $isWired, $gap);
    if ($ins->execute()) { $rows++; }
    else { echo "  ✘ {$jc}/{$seq}: {$ins->error}\n"; }
}
$ins->close(); $navQ->close();

/* ══ ٤ التقرير — بمقامٍ مُعلَنٍ وفرقٍ غيرِ مطموس ══════════════════════════ */
$q = $conn->query("SELECT COUNT(DISTINCT ladder_code) FROM gov_journey_ladders");
$distinct = $q ? (int) $q->fetch_row()[0] : 0;
$q = $conn->query("SELECT COUNT(*) FROM gov_ladders");
$total = $q ? (int) $q->fetch_row()[0] : 0;

echo "══ سلاليمُ الرحلاتِ الثلاث ══\n";
echo "  المواضعُ المسجَّلة: {$rows} · لسلاليمَ فريدةٍ: {$distinct} من {$total}\n";
echo "  ◆ الفرقُ ({$rows} موضعًا لـ{$distinct} سلّمًا) = **`LD-01` في رحلتَين قصدًا**\n";
echo "    (الإيرادُ والمشغِّل) — التايم شيتُ أصلُ الاثنتَين، ولا يُطرح من إحداهما.\n";
echo "  شاشةُ القيادة: حيةٌ في السايدبار={$live} · على القرصِ فقط={$fileOnly} · مفقودة={$missing}\n";
echo "  **موصولةٌ بسلّمِها: {$wired} من {$rows}**\n";

$q = $conn->query("SELECT journey_code, journey_ar, COUNT(*) n,
                          SUM(driver_state='LIVE_IN_NAV') liveN, SUM(ladder_wired) w
                     FROM gov_journey_ladders GROUP BY journey_code, journey_ar ORDER BY journey_code");
echo "\n  ┌ الرحلةُ ─────────────── خطوات ─ شاشةٌ حية ─ موصولة\n";
while ($q && ($x = $q->fetch_assoc())) {
    printf("  │ %-22s %5d %9d %10d\n", $x['journey_ar'], $x['n'], $x['liveN'], $x['w']);
}
echo "  └─────────────────────────────────────────────────\n";

if ($missing === 0 && $wired === 0) {
    echo "\n◆ **الفجوةُ المرفوعةُ إليك**: الرحلاتُ الثلاثُ **ماشيةٌ بشاشاتِها كلِّها**،\n";
    echo "  **ولا شاشةَ واحدةٌ تقرأ سلّمَها**. فالسلّمُ مُعلَنٌ ولا يُنفَّذ — ترتيبُ\n";
    echo "  الخطواتِ والسقفُ و«لا يدَ تمشي خطوتَين» مكتوبةٌ في السجلِّ ولا شيءَ\n";
    echo "  يقرؤها لحظةَ الاعتماد. **وسلّمٌ لا يقرؤه المُنفِّذُ وثيقةٌ لا حارس.**\n";
    echo "  ولم يُوصَل شيءٌ: الوصلُ يغيّر **مَن يعتمدُ وبأيِّ ترتيبٍ وبأيِّ سقف** —\n";
    echo "  وهو من مجالِ الصلاحياتِ الذي لا يُطبَّق فيه بلا اعتراضِك.\n";
}
echo "\n" . ($rows === count($MAP) ? "✔ الخريطةُ مسجَّلةٌ كاملة\n" : "✘ نقصٌ في التسجيل\n");
exit($rows === count($MAP) ? 0 : 1);
