<?php
/**
 * 2027_08_26_event_type_rulings.php
 *   سجلُّ حكمِ نوعِ الحدث — INJ-FIX-01 · GAP-05 · GAP-31
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبول بنصِّه**: «كلُّ نوعِ حدثٍ منتَجٍ يُصنَّف واحدًا من اثنَين:
 *   **حدثُ أعمالٍ** له مستهلكٌ فعليٌّ بأثرٍ مقيس · أو **حدثُ تدقيقٍ** معلَنٌ
 *   رسميًّا لا يحتاج مستهلكًا. والمعيار: **صفرُ نوعِ حدثٍ بلا حكم** — وثلاثةُ
 *   مستهلكين لا تُثبت أن الناقلَ صار عمودَ التكامل».
 *
 * ◆ **ولا سجلَّ يحمل الحكمَ اليوم**: `ems_event_subscriptions` تُعلن **نيّةَ**
 *   اشتراكٍ (٩١ صفًّا) ولا تُعلن **حكمًا** على النوع. فالنوعُ الذي لا اشتراكَ
 *   له لا يُقرأ «تدقيقًا» بل يبقى **بلا حكمٍ** — وهو ما تمنعه الفجوة.
 *   ⇐ فيُنشأ `gov_event_rulings`: صفٌّ لكلِّ نوعٍ منتَج، بحكمِه وسببِه.
 *
 * ◆ **والحكمُ قرارُ حوكمةٍ لا استنتاجُ أداة**: فتُبذَر **الوقائعُ المقيسةُ**
 *   لكلِّ نوعٍ (أله اشتراك؟ أهو نشط؟ أصنفُ معالجِه موجودٌ على القرص؟ كم مرةً
 *   أُنتج؟) ويُترك `ruling` **فارغًا** حتى يُحسم. فالسجلُّ يقول «هذا ما نعرفه
 *   وهذا ما لم يُحسم» — ولا يخترع حكمًا ليصير العدَّادُ أخضر.
 *
 * ◆ **والفارقُ بين الفراغِ والحكم**: نوعٌ بلا حكمٍ **يُعَدُّ ويُسمّى** في
 *   الشاهد، ولا يُقرأ صفرُه نجاحًا.
 *
 * التشغيل:  php database/migrations/2027_08_26_event_type_rulings.php
 * الرجوع :  php database/migrations/2027_08_26_event_type_rulings.php --revert
 * الشاهد :  php tests/injfix01_event_ruling_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_event_rulings`");
    echo "↺ حُذف gov_event_rulings\n";
    exit(0);
}

/* ══ ① السجل ═════════════════════════════════════════════════════════════ */
$conn->query(
    "CREATE TABLE IF NOT EXISTS `gov_event_rulings` (
       `event_key`        VARCHAR(120) NOT NULL,
       `ruling`           ENUM('business','audit') NULL,
       `reason`           VARCHAR(400) NULL,
       `produced_count`   INT UNSIGNED NOT NULL DEFAULT 0,
       `has_subscription` TINYINT(1)   NOT NULL DEFAULT 0,
       `subscription_active` TINYINT(1) NOT NULL DEFAULT 0,
       `handler_class`    VARCHAR(190) NULL,
       `handler_on_disk`  TINYINT(1)   NOT NULL DEFAULT 0,
       `in_projection`    TINYINT(1)   NOT NULL DEFAULT 0,
       `measured_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
       `decided_at`       DATETIME     NULL,
       `decided_by`       VARCHAR(120) NULL,
       PRIMARY KEY (`event_key`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if ($conn->errno) { exit("✘ تعذّر إنشاءُ السجل: {$conn->error}\n"); }
echo "① السجل: جاهز\n";

/* ══ ② الوقائعُ المقيسةُ لكلِّ نوعٍ منتَج ══════════════════════════════════ */
$types = array();
$r = $conn->query("SELECT `event_key`, COUNT(*) n FROM `ems_business_events`
                    WHERE `event_key` IS NOT NULL AND `event_key` <> '' GROUP BY `event_key`");
while ($r && $x = $r->fetch_assoc()) { $types[$x['event_key']] = array('n' => (int) $x['n']); }
echo "② أنواعٌ منتَجةٌ فعلًا: " . count($types) . "\n";

/* ══ الاشتراكاتُ تُقاس صفًّا صفًّا لا بتجميعٍ اعتباطيّ ═══════════════════════
   ◆ **خطأٌ وقع في أولِ قياسٍ وصُحِّح**: كان الجردُ يأخذ `MAX(is_active)` و
     `MIN(handler_class)` لكلِّ نوع. والنوعُ الواحدُ له **عدةُ اشتراكات**،
     فاختير أحدُها **أبجديًّا** ونُسب حكمُه إلى النوعِ كلِّه. فظهر أن «أربعةَ
     أنواعٍ بلا معالجٍ على القرص» — والحقيقةُ أن لها معالجًا حيًّا في اشتراكٍ
     آخر، والمكسورُ اشتراكٌ **معطَّلٌ** بجانبِه.
   ◆ **والتجميعُ الاعتباطيُّ يكذب في الاتجاهَين**: قد يُخفي عطبًا وقد يخترعه.
     فالسؤالُ الصحيح: **أللنوعِ اشتراكٌ نشطٌ واحدٌ على الأقلِّ معالجُه موجود؟** */
$subs = array();
$r = $conn->query("SELECT `event_code`, `is_active`, `handler_class`, `handler_method`
                     FROM `ems_event_subscriptions`");
while ($r && $x = $r->fetch_assoc()) { $subs[$x['event_code']][] = $x; }

/** أموجودٌ صنفُ المعالجِ على القرص؟ — يُقاس ولا يُفترض. */
$onDiskFn = function ($hc) use ($ROOT) {
    if ($hc === null || $hc === '') { return false; }
    $rel = str_replace(chr(92), '/', $hc);
    $rel = preg_replace('~^App/~', 'app/', $rel) . '.php';
    return is_file($ROOT . '/' . $rel);
};

$proj = array();
$r = $conn->query("SELECT DISTINCT `event_key` FROM `fin_financial_events` WHERE `event_key` IS NOT NULL");
while ($r && $x = $r->fetch_row()) { $proj[$x[0]] = true; }

$ins = $conn->prepare(
    "INSERT INTO `gov_event_rulings`
       (`event_key`,`produced_count`,`has_subscription`,`subscription_active`,
        `handler_class`,`handler_on_disk`,`in_projection`,`measured_at`)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
       `produced_count` = VALUES(`produced_count`),
       `has_subscription` = VALUES(`has_subscription`),
       `subscription_active` = VALUES(`subscription_active`),
       `handler_class` = VALUES(`handler_class`),
       `handler_on_disk` = VALUES(`handler_on_disk`),
       `in_projection` = VALUES(`in_projection`),
       `measured_at` = NOW()");

$n = 0; $withSub = 0; $liveHandler = 0;
foreach ($types as $key => $meta) {
    $rows   = isset($subs[$key]) ? $subs[$key] : array();
    $hasSub = count($rows) > 0 ? 1 : 0;
    /* ◆ **المستهلكُ الحيُّ = اشتراكٌ نشطٌ ومعالجُه موجود** — والاثنان معًا.
         فاشتراكٌ نشطٌ بمعالجٍ معدومٍ ليس مستهلكًا، ومعالجٌ موجودٌ باشتراكٍ
         معطَّلٍ ليس مستهلكًا كذلك. */
    $act = 0; $onDisk = 0; $hc = null;
    foreach ($rows as $row) {
        $exists = $onDiskFn($row['handler_class']) ? 1 : 0;
        if ((int) $row['is_active'] === 1) {
            $act = 1;
            if ($exists) { $onDisk = 1; $hc = (string) $row['handler_class']; }
        }
        if ($hc === null) { $hc = (string) $row['handler_class']; }   // للعرضِ عند غيابِ الحيّ
    }
    $inProj = isset($proj[$key]) ? 1 : 0;
    if ($hasSub) { $withSub++; }
    if ($onDisk) { $liveHandler++; }
    $ins->bind_param('siiisii', $key, $meta['n'], $hasSub, $act, $hc, $onDisk, $inProj);
    if (!$ins->execute()) { exit("✘ فشلَ القيدُ لـ{$key}: " . $ins->error . "\n"); }
    $n++;
}
$ins->close();
echo "② قُيِّد {$n} نوعًا · له اشتراك: {$withSub} · صنفُ معالجِه موجودٌ على القرص: {$liveHandler}\n";

/* ══ ③ ما يُحسم بالقياسِ وحدَه — ولا شيءَ غيرُه ═══════════════════════════
   ◆ **حكمٌ واحدٌ فقط يُشتقُّ بلا قرارِ حوكمة**: نوعٌ **له اشتراكٌ مُعلَنٌ
     وصنفُ معالجِه لا وجودَ له على القرص** — فهذا ليس «حدثَ أعمالٍ له مستهلك»
     بيقين. ومع ذلك **لا يُحكَم عليه تدقيقًا** هنا: قد يكون أعمالًا ينتظر
     معالجَه. فيُترك بلا حكمٍ ويُسمّى.
   ◆ فلا يُبذَر حكمٌ واحدٌ في هذه الهجرة. **الوقائعُ تُقاس والحكمُ يُتخذ** —
     وخلطُهما هو ما أنتج «اشتراكاتٍ لمعماريةٍ لم يُنفَّذ منتجوها». */
$r = $conn->query("SELECT COUNT(*) FROM `gov_event_rulings` WHERE `ruling` IS NULL");
$undecided = $r ? (int) $r->fetch_row()[0] : -1;

echo "───────────────────────────────────────────────────────────────\n";
echo "بلا حكمٍ بعد: {$undecided} من {$n}\n";
$r = $conn->query("SELECT COUNT(*) FROM `gov_event_rulings` WHERE `has_subscription` = 1 AND `handler_on_disk` = 0");
echo "◆ له اشتراكٌ ومعالجُه **لا وجودَ له على القرص**: " . ($r ? $r->fetch_row()[0] : '?') . "\n";
$r = $conn->query("SELECT COUNT(*) FROM `gov_event_rulings` WHERE `has_subscription` = 0");
echo "◆ منتَجٌ **بلا اشتراكٍ إطلاقًا**: " . ($r ? $r->fetch_row()[0] : '?') . "\n";
echo "الشاهد: php tests/injfix01_event_ruling_proof.php\n";
