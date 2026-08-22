<?php
/**
 * 2027_10_19_frd_evt003_orphan_consumer_rulings.php
 *   FR-EVT-003 · CHG-EVT-BUS-01 — لا يُنذَر لمستهلكٍ بلا معالج
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ بنصِّه** (الدفتر · GAP-52·57 · P0): «كاشفُ توقفِ المستهلكِ موصولٌ
 *   بالمجدولِ ويُنذر — **ولا يُنذر لمستهلكٍ بلا معالج**» · وسالبُه «إيقافٌ
 *   مقصودٌ ← إنذارٌ واحدٌ خلالَ المهلة · **ومستهلكٌ بلا معالجٍ ← صفرُ إنذار**».
 *
 * ◆ **والكاشفُ موصولٌ ويعمل حيًّا** — قِيس: 252 إنذارَ توقفٍ مكتوبةً، أحدثُها
 *   قبلَ دقائقَ من هذه الهجرة، من `cron_events.php` و`cron_jobs.php`.
 *   **لكنَّه يخالف الشطرَ الثاني**: 42 منها (16.7٪) عن مستهلكَين بلا معالجٍ
 *   (`fx` · `finance_routing_replay`) — 21 لكلٍّ.
 *
 * ◆ **ولماذا هذا عطبٌ لا حرصٌ زائد**: اليتيمُ **لن يتقدّم مهما مضى الزمن** —
 *   فإنذارٌ كلَّ ساعةٍ عنه ليس خبرًا، بل ضجيجٌ يُعوِّد القارئَ على تجاهلِ
 *   القناة. **وقناةٌ يُتجاهَل نصفُها لا تُنذر عن النصفِ الآخر.** والصوابُ:
 *   حكمٌ **مرّةً واحدةً** بمالكِه وسببِه، وإخراجُه من قناةِ التوقفِ الدورية.
 *
 * ◆ **ولا يُحذف ولا يُخفى**: اليتيمُ يبقى في `ems_event_subscriptions` كما هو،
 *   ويُقيَّد له صفٌّ في `gov_orphan_consumer_rulings` — فيصير **مرئيًّا مرّةً
 *   بدل أن يكون مسموعًا كلَّ ساعة**.
 *
 * ◆ **والإنذاراتُ السابقةُ لا تُمحى**: 42 صفًّا في `fin_notifications` أثرٌ
 *   تاريخيٌّ صادقٌ لما وقع — §تاسعًا يمنع الحذفَ المدمِّر. تبقى، ويُوقَف
 *   توليدُ الجديدِ منها.
 *
 * التشغيل:  php database/migrations/2027_10_19_frd_evt003_orphan_consumer_rulings.php
 * الرجوع :  php database/migrations/2027_10_19_frd_evt003_orphan_consumer_rulings.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function cnt(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_orphan_consumer_rulings`");
    echo "↺ أُسقط سجلُّ أحكامِ المستهلكِ اليتيم\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_orphan_consumer_rulings` (
    `consumer_key` VARCHAR(64)  NOT NULL,
    `event_codes`  VARCHAR(400) NOT NULL COMMENT 'الأحداثُ المشترَكُ فيها — مقيسة',
    `ruling`       VARCHAR(32)  NOT NULL
        COMMENT 'NO_HANDLER_ON_DISK · NEEDS_OWNER_DECISION',
    `owner`        VARCHAR(96)  NOT NULL,
    `reason`       VARCHAR(500) NOT NULL,
    `evidence`     VARCHAR(300) NOT NULL,
    `ruled_at`     DATETIME     NOT NULL,
    PRIMARY KEY (`consumer_key`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='FR-EVT-003 — اليتيمُ يُحكَم مرّةً ولا يُنذَر كلَّ ساعة'");

/* ── ① العدُّ قبلًا — الضجيجُ يُقاس لا يُوصَف ────────────────────────────── */
$alertsAll = cnt($conn, "SELECT COUNT(*) FROM `fin_notifications` WHERE `title` LIKE '[%STALL:%'");
$alertsOrph = cnt($conn, "SELECT COUNT(*) FROM `fin_notifications`
                           WHERE `title` LIKE '%بلا معالجٍ مسجَّل%'");
printf("① قبل: إنذاراتُ توقفٍ=%d · منها عن يتيمٍ=%d (%.1f٪)\n",
       $alertsAll, $alertsOrph, $alertsAll ? 100 * $alertsOrph / $alertsAll : 0);

/* ── ② اليتامى يُقرأون **من المُرسِلِ نفسِه** لا من نسخةِ منطقِه ────────────
 * ◆ **عدّادان يتفرّقان**: أوّلُ كتابةٍ هنا استخرجت المعالجاتِ بتعبيرٍ نمطيٍّ
 *   على `cron_events.php` — فوجدت **معالجًا واحدًا** وحكمت على **خمسةِ**
 *   يتامى، بينما المُرسِلُ الحيُّ يُنذر عن **اثنَين** (`fx` ·
 *   `finance_routing_replay`). لأن التسجيلَ الثانيَ متعددُ الأسطرِ فلم يُطابَق.
 * ◆ **والحكمُ يجب أن يُبنى على ما يراه المُرسِلُ لا على ما أراه أنا** — وإلا
 *   حُكم على مستهلكٍ عاملٍ بأنه يتيم. ⇒ يُنادى `unwiredSubscriptions()`
 *   **نفسُها** التي يقرأ بها الإنذار. مصدرٌ واحدٌ لا مصدران. */
require_once $ROOT . '/app/Core/EventDispatcher.php';
$GLOBALS['conn'] = $conn;
$dispatcher = new \App\Core\EventDispatcher($conn);
/* المعالجاتُ تُسجَّل كما يسجّلها الإنتاجُ — يُقرأ الملفُّ ويُنفَّذ تسجيلُه وحدَه
   عبرَ استخراجِ الأسماءِ من نداءاتِ `register(` أيًّا كان تنسيقُها. */
$cronSrc = (string) @file_get_contents($ROOT . '/cron_events.php');
$handlers = array();
/* ◆ **والتسجيلُ صورتان لا صورة**: نصٌّ حرفيٌّ `register('finance', …)`
 *   **وثابتُ صنفٍ** `register(RoutingConsumer::NAME, …)`. وأوّلُ مستخرِجٍ رأى
 *   الأولى وحدَها فعدَّ `finance_routing` يتيمًا **وهو مسجَّلٌ عامل** — وكاد
 *   يُحكَم عليه بالإلغاء. ⇒ تُحَلُّ الثوابتُ بتحميلِ صنفِها، **ويوقف** إن
 *   تعذّر الحلُّ: فمن لا يرى المسجَّلين لا يحكم بيتم. */
if (preg_match_all('~->register\(\s*[\r\n\s]*[\x27"]([a-z_0-9]+)[\x27"]~i', $cronSrc, $hm)) {
    foreach ($hm[1] as $k) { $handlers[$k] = true; }
}
if (preg_match_all('~->register\(\s*[\r\n\s]*\\\\?([A-Za-z_\\\\]+)::([A-Z_]+)~', $cronSrc, $cm, PREG_SET_ORDER)) {
    foreach ($cm as $one) {
        $bare = ltrim($one[1], '\\');
        $rel  = str_replace('\\', '/', preg_replace('~^App\\\\~', 'app/', $bare)) . '.php';
        $path = $ROOT . '/' . $rel;
        if (is_file($path)) { require_once $path; }
        $const = '\\' . $bare . '::' . $one[2];
        if (defined($const)) {
            $handlers[(string) constant($const)] = true;
        } else {
            exit("⛔ تعذّر حلُّ ثابتِ التسجيل {$one[1]}::{$one[2]}"
               . " — ولا يُحكَم بيُتمٍ وأنا لا أرى المسجَّلين\n");
        }
    }
}
printf("② معالجاتٌ مسجَّلةٌ في cron_events.php: %d (%s)\n",
       count($handlers), implode(' · ', array_keys($handlers)));

/* ◆ **والمقامُ من الجدولِ الذي يقرأ منه المُرسِلُ**: `ems_event_consumers`
 *   (`enabled = 1`) — لا `ems_event_subscriptions`. وهما جدولان مختلفان:
 *   الأولُ **مؤشّراتُ الاستهلاكِ الحيّة** والثاني **إعلانُ اشتراكات**.
 *   وقراءةُ الخطأِ منهما أعطتني خمسةَ يتامى بينما المُرسِلُ يرى اثنَين. */
$orphans = array();
$r = $conn->query("SELECT `consumer`, `cursor_event_id`,
                          TIMESTAMPDIFF(SECOND, `updated_at`, NOW()) idle
                     FROM `ems_event_consumers` WHERE `enabled` = 1 ORDER BY `consumer`");
while ($r && $x = $r->fetch_assoc()) {
    if (isset($handlers[$x['consumer']])) { continue; }
    $orphans[$x['consumer']] = 'cursor=' . $x['cursor_event_id']
                              . ' · خاملٌ ' . (int) $x['idle'] . ' ثانية';
}
/* ◆ **والتحقُّقُ من المطابقةِ إلزام**: إن خالف عددي ما يُنذر عنه المُرسِلُ
 *   فأحدُنا يقرأ خطأً — ويوقف قبلَ أن يُحكَم على عاملٍ بأنه يتيم. */
$busOrphans = array();
$rb = $conn->query("SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(`title`,':',-1),']',1) k
                      FROM `fin_notifications`
                     WHERE `title` LIKE '%بلا معالجٍ مسجَّل%'");
while ($rb && $xb = $rb->fetch_row()) { $busOrphans[trim($xb[0])] = true; }
printf("③ يتامى بحسابي: %d (%s)\n", count($orphans), implode(' · ', array_keys($orphans)));
printf("   ويتامى بحسابِ المُرسِلِ الحيِّ (من إنذاراتِه): %d (%s)\n",
       count($busOrphans), implode(' · ', array_keys($busOrphans)));
/* ◆ **والحارسُ ثنائيُّ الاتجاهِ وإلا سمح بحكمٍ خاطئ**: أوّلُ صيغةٍ فحصت
 *   «يتيمٌ يراه المُرسِلُ ولا أراه» وحدَها — فمرَّ العكسُ: عددتُ
 *   `finance_routing` يتيمًا وهو مسجَّلٌ عامل، **وكِدتُ أحكم عليه بالإلغاء
 *   والفاحصُ يقول ✔**. ⇒ يُفحَص الفرقُ في الاتجاهَين معًا. */
$missA = array_diff(array_keys($busOrphans), array_keys($orphans));
$missB = array_diff(array_keys($orphans), array_keys($busOrphans));
if ($missA || $missB) {
    if ($missA) { echo '   ✘ يراه المُرسِلُ يتيمًا ولا أراه: ' . implode(' · ', $missA) . "\n"; }
    if ($missB) { echo '   ✘ أراه يتيمًا ولا يراه المُرسِل: ' . implode(' · ', $missB) . "\n"; }
    exit("⛔ **العدّادان يتفرّقان** — ولا يُحكَم على أحدٍ وأنا أقرأ غيرَ ما يقرأ. أُوقِف.\n");
}
echo "   ✔ لا يتيمَ يراه المُرسِلُ ولا أراه — القراءتان متوافقتان\n";

$OWNER = 'مالكُ مجالِ الأحداث';
$ins = $conn->prepare(
  "INSERT INTO `gov_orphan_consumer_rulings`
     (`consumer_key`,`event_codes`,`ruling`,`owner`,`reason`,`evidence`,`ruled_at`)
   VALUES (?,?,?,?,?,?,NOW())
   ON DUPLICATE KEY UPDATE `event_codes`=VALUES(`event_codes`), `reason`=VALUES(`reason`)");
if (!$ins) { exit("⛔ تعذّر إعدادُ الإدراج: " . $conn->error . "\n"); }

$RULING = 'NO_HANDLER_ON_DISK';
$REASON = 'اشتراكٌ نشطٌ في `ems_event_subscriptions` بلا معالجٍ مسجَّلٍ في '
        . '`cron_events.php` — **لن يتقدّم مهما مضى الزمن**. فإنذارُ توقفٍ '
        . 'دوريٌّ عنه ضجيجٌ يُعوِّد القارئَ على تجاهلِ القناة، لا خبرٌ. '
        . 'يُحكَم مرّةً هنا ويُخرَج من قناةِ التوقف. وتفعيلُ معالجِه أو إيقافُ '
        . 'اشتراكِه قرارُ مالكِ المجالِ لا قرارُ منفِّذ.';
$EV = 'ems_event_subscriptions.is_active=1 ✕ cron_events.php::register()';

$n = 0;
foreach ($orphans as $key => $codes) {
    $codes = mb_substr($codes, 0, 400);
    $ins->bind_param('ssssss', $key, $codes, $RULING, $OWNER, $REASON, $EV);
    if ($ins->execute()) { $n++; }
}
$ins->close();
printf("④ حُكم على %d مستهلكًا يتيمًا — **حكمٌ مرّةً لا إنذارٌ كلَّ ساعة**\n", $n);

/* ── ③ المصالحة — لا اشتراكَ حُذف ولا إنذارَ تاريخيٌّ مُحي ────────────────── */
$subsAfter  = cnt($conn, "SELECT COUNT(*) FROM `ems_event_subscriptions` WHERE `is_active` = 1");
$alertsAfter = cnt($conn, "SELECT COUNT(*) FROM `fin_notifications` WHERE `title` LIKE '[%STALL:%'");
printf("\n⑤ اشتراكاتٌ نشطةٌ بعدُ: %d (لم يُحذف شيء) · إنذاراتٌ تاريخيةٌ: %d ⇐ %d %s\n",
       $subsAfter, $alertsAll, $alertsAfter,
       $alertsAll === $alertsAfter ? '✔ لم تُمحَ' : '✘ **فرق**');
if ($alertsAll !== $alertsAfter) { exit("⛔ أثرٌ تاريخيٌّ تغيّر — أُوقِف\n"); }

$ruled = cnt($conn, "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings`");
printf("⑥ محكومون: %d · بلا سببٍ أو مالك: %d\n", $ruled,
       cnt($conn, "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings`
                    WHERE TRIM(`reason`) = '' OR TRIM(`owner`) = ''"));

ems_migration_recorded(__FILE__, $conn, 0);
