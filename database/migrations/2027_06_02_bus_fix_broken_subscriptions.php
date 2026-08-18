<?php
/**
 * 2027_06_02_bus_fix_broken_subscriptions.php
 * ═══════════════════════════════════════════════════════════════════════════
 * إصلاحُ الاشتراكاتِ الأحدَ عشرَ المعطوبة — بالحقيقةِ لا بتصحيحِ اسم
 * ───────────────────────────────────────────────────────────────────────────
 * كشف أولُ تسليمٍ حقيقيٍّ أن 11 اشتراكًا من 74 يسمّي صنفًا لا يصلح مستهلكًا:
 *
 *   ① `App\Core\EffectFanout` ×10 — والصنفُ فعلًا `App\Services\EffectFanout`،
 *      **وتصحيحُ الاسمِ وحدَه لا يكفي**: واجهتُه دوالُّ ساكنةٌ تُنادى **داخلَ
 *      الخدماتِ مباشرةً** (`AttributionService::…hourPolicy/resolveRuling`)
 *      ولا مدخلَ `handle($event,$conn)` فيه. فهو محرّكُ مروحةٍ يعمل في المعاملةِ
 *      نفسِها — لا مستهلكُ ناقلٍ يُستدعى بعدَها.
 *
 *   ② `AppServicesFinanceApprovalsInboxService` ×1 — الخطوطُ المائلةُ محذوفةٌ
 *      كليًّا، والصنفُ الحقيقيُّ `App\Services\Finance\ApprovalsInboxService`
 *      وواجهتُه `inbox($conn,$companyId,$viewerId)` — قارئٌ لا مستهلك.
 *
 * ◆ فالإصلاحُ الصادقُ ليس تصحيحَ حروفٍ بل قولَ الحقيقة:
 *   • تُوسَم الإعلاناتُ الأحدَ عشرَ **غيرَ نشطة** بسببٍ مكتوب — ولا تُحذف،
 *     فهي شهادةٌ على ما كان معلَنًا ولم يعمل.
 *   • ويُسجَّل لكلِّ نوعٍ منها **مستهلكٌ حقيقيٌّ عامل** (الحارسُ الحوكميّ)
 *     كي يبقى CK-11 صادقًا لا مُرضًى بإعلانٍ ميت.
 *   • وأثرُ المروحةِ الماليةِ لم يُمسّ — يبقى حيث كان: داخلَ الخدمات.
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$hasCol = function (string $t, string $c) use ($conn): bool {
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};

echo "\n▐ ① عمودُ سببِ التعطيل — فلا يُوسَم صفٌّ ميتًا بلا حجّة\n";
if (!$hasCol('event_consumers', 'inactive_reason')) {
    if ($conn->query("ALTER TABLE `event_consumers`
            ADD COLUMN `inactive_reason` VARCHAR(255) NULL
                COMMENT 'سببُ التعطيل — ولا يُعطَّل اشتراكٌ بلا سبب',
            ADD COLUMN `inactive_at` DATETIME NULL")) {
        echo "   ✔ event_consumers.inactive_reason\n";
    } else { echo "   ✗ " . $conn->error . "\n"; }
} else { echo "   · موجودٌ سلفًا\n"; }

// ───────────────── جردُ المعطوبِ قبلَ المسّ ─────────────────
echo "\n▐ ② جردُ الاشتراكاتِ التي لا يصلح صنفُها مستهلكًا\n";
$broken = array();
$r = $conn->query("SELECT `c_id`, `event_name`, `consumer_class` FROM `event_consumers` WHERE `active`=1");
while ($x = $r->fetch_assoc()) {
    $cls = (string) $x['consumer_class'];
    $rel = str_replace('\\', '/', preg_replace('#^App\\\\#', '', $cls));
    $file = dirname(__DIR__, 2) . '/app/' . $rel . '.php';
    if (!is_readable($file)) { $broken[] = $x; }
}
printf("   · اشتراكاتٌ بصنفٍ لا ملفَّ له: %d\n", count($broken));
foreach ($broken as $b) { printf("      %-44s ← %s\n", $b['event_name'], mb_substr($b['consumer_class'], 0, 42)); }

// ───────────────── تعطيلُها بسببٍ مكتوب ─────────────────
echo "\n▐ ③ تعطيلُها بسببِها — ولا تُحذف\n";
$REASON = array(
    'App\\Core\\EffectFanout' =>
        'الصنفُ الحقيقيُّ App\\Services\\EffectFanout — محرّكُ مروحةٍ بدوالَّ ساكنةٍ '
        . 'يُنادى داخلَ الخدماتِ في المعاملةِ نفسِها، ولا مدخلَ handle() فيه. '
        . 'فليس مستهلكَ ناقلٍ أصلًا، وأثرُه الماليُّ باقٍ حيث كان.',
    'AppServicesFinanceApprovalsInboxService' =>
        'اسمٌ فقدَ خطوطَه المائلة. والصنفُ App\\Services\\Finance\\ApprovalsInboxService '
        . 'قارئٌ (inbox) لا مستهلكًا يكتب أثرًا.',
);
$up = $conn->prepare("UPDATE `event_consumers`
                         SET `active`=0, `inactive_reason`=?, `inactive_at`=NOW()
                       WHERE `c_id`=?");
$off = 0; $types = array();
foreach ($broken as $b) {
    $reason = $REASON[$b['consumer_class']] ?? 'صنفٌ غيرُ موجودٍ بمساره — لا يصلح مستهلكًا';
    $cid = (int) $b['c_id'];
    $up->bind_param('si', $reason, $cid);
    if ($up->execute()) { $off++; $types[] = (string) $b['event_name']; }
}
$up->close();
echo "   ✔ عُطّل $off اشتراكًا بسببٍ مكتوبٍ — وبقيت صفوفُها شاهدةً\n";

// ───────────────── مستهلكٌ حقيقيٌّ بديلٌ لكلِّ نوع ─────────────────
echo "\n▐ ④ مستهلكٌ حقيقيٌّ عاملٌ لكلِّ نوعٍ تُرك بلا مستهلك\n";
$ins = $conn->prepare(
    "INSERT IGNORE INTO `event_consumers`
        (`event_name`,`consumer_class`,`consumer_method`,`produces`,`active`,
         `consumer_key`,`max_attempts`,`timeout_seconds`)
     VALUES (?, 'App\\\\Services\\\\Bus\\\\Consumers\\\\GovernanceWatchConsumer', 'handle', 'notify', 1,
             'governance_watch', 5, 60)"
);
$added = 0;
foreach (array_unique($types) as $t) {
    $live = (int) $one("SELECT COUNT(*) FROM `event_consumers`
                         WHERE `event_name`='" . $conn->real_escape_string($t) . "' AND `active`=1");
    if ($live > 0) { continue; }
    $ins->bind_param('s', $t);
    if ($ins->execute() && $conn->affected_rows > 0) { $added++; }
}
$ins->close();
echo "   ✔ سُجّل الحارسُ الحوكميُّ لـ$added نوعًا\n";

// ───────────────── التحقُّق ─────────────────
echo "\n▐ ⑤ التحقُّق\n";
$noCons = (int) $one(
    "SELECT COUNT(*) FROM (SELECT DISTINCT e.event_key FROM `ems_business_events` e
      WHERE NOT EXISTS (SELECT 1 FROM `event_consumers` c
                         WHERE c.event_name=e.event_key AND c.active=1)) t");
printf("   · أنواعٌ منشورةٌ بلا مستهلك (CK-11): %d   [المتوقَّع 0]\n", $noCons);

$stillBroken = 0;
$r = $conn->query("SELECT `consumer_class` FROM `event_consumers` WHERE `active`=1");
while ($x = $r->fetch_row()) {
    $rel = str_replace('\\', '/', preg_replace('#^App\\\\#', '', $x[0]));
    if (!is_readable(dirname(__DIR__, 2) . '/app/' . $rel . '.php')) { $stillBroken++; }
}
printf("   · اشتراكاتٌ نشطةٌ بصنفٍ معطوب: %d   [المتوقَّع 0]\n", $stillBroken);
printf("   · اشتراكاتٌ نشطة: %s · معطَّلةٌ بسبب: %s\n",
    $one("SELECT COUNT(*) FROM `event_consumers` WHERE `active`=1"),
    $one("SELECT COUNT(*) FROM `event_consumers` WHERE `active`=0 AND `inactive_reason` IS NOT NULL"));

// ───────────────── إعادةُ التسليماتِ التي فشلت بسببِ الصنفِ المعطوب ─────────────────
echo "\n▐ ⑥ إعادةُ التسليماتِ التي فشلت بصنفٍ معطوب\n";
$conn->query(
    "UPDATE `ems_event_deliveries`
        SET `state`='published', `attempt_no`=0, `next_attempt_at`=NOW(3),
            `fail_code`=NULL,
            `fail_text`=CONCAT('[أُعيدت بعدَ إصلاحِ الاشتراك] ', COALESCE(`fail_text`,''))
      WHERE `state` IN ('failed','dlq') AND `fail_code`='HANDLER_ERROR'
        AND `fail_text` LIKE '%غيرُ محمَّل%'");
echo "   ✔ أُعيد " . $conn->affected_rows . " تسليمًا إلى الطابور\n\n";
