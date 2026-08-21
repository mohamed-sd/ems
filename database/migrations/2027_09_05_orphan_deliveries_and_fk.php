<?php
/**
 * 2027_09_05_orphan_deliveries_and_fk.php
 *   التسليماتُ اليتيمةُ وقيدُ منعِها — INJ-FIX-01 · GAP-08
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ ثلاثةٌ**: «تتبُّعٌ زمنيٌّ للجذورِ المفقودة · وسببٌ مكتوبٌ لكلِّ حالة
 *   · وقيدٌ يمنع التكرار». وهذه الهجرةُ تُنفّذ الثلاثةَ بالترتيب.
 *
 * ◆ **والتشخيصُ مقيسٌ لا مُقدَّر** — واليتيمُ **مجتمعان لا واحد**:
 *   ① **١٠ ببذرِ `UAT-2026`** ومستهلكيها `legacy_uat_cons_*` وحالتُها `published`:
 *      **بقايا اختبارٍ** كُنس جذرُها ونجت هي.
 *   ② **٧٢ بمستهلكِ `governance_watch`** أكثرُها `dlq` بـ`fail_code = NO_EVENT`:
 *      **النظامُ رصد العطبَ وأعلنه** — فالتسليمُ حاول وفشل مغلقًا. وهذا ليس
 *      خللًا في الحارسِ بل أثرُه.
 *
 * ◆ **والسببُ الجذريُّ واحد**: `ems_business_events` **يُكنَس** (أقصى `id`
 *   ٤٤٬٦٢٤ مقابلَ ٢١٬٢٨٥ صفًّا — فجوةُ ٢٣٬٣٣٩)، و`ems_event_deliveries`
 *   **بلا قيدٍ مرجعيٍّ واحد**. فكلُّ كنسٍ للجذرِ يخلّف تسليماتِه يتيمةً.
 *   وقِيس أن **صفرَ يتيمٍ يشير إلى `id` أكبرَ من الموجود** ⇒ الجذورُ كانت
 *   موجودةً ثم حُذفت — لا أنها لم توجد قط.
 *
 * ◆ **والقيدُ `ON DELETE CASCADE` لا `RESTRICT`**: التسليمُ **لا معنى له بلا
 *   جذرِه**، وكنسُ الأحداثِ عملٌ قائمٌ في العُدَّة. و`RESTRICT` كان سيُفشل كلَّ
 *   كنسٍ فيُنزع القيدُ بعدَ أسبوع — **وقيدٌ يُنزع ليس قيدًا**.
 *
 * ◆ ولا حذفَ مدمِّر: كلُّ يتيمٍ **يُؤرشَف بصفِّه كاملًا** قبلَ حذفِه، و`--revert`
 *   يعيده ويُسقط القيد.
 *
 * التشغيل:  php database/migrations/2027_09_05_orphan_deliveries_and_fk.php
 * الرجوع :  php database/migrations/2027_09_05_orphan_deliveries_and_fk.php --revert
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

$FK = 'fk_evdeliv_event';

/* ══ الرجوع ═══════════════════════════════════════════════════════════════ */
if (in_array('--revert', $argv, true)) {
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ems_event_deliveries'
                          AND CONSTRAINT_NAME='{$FK}'");
    if ($r && (int) $r->fetch_row()[0] > 0) {
        $conn->query("ALTER TABLE `ems_event_deliveries` DROP FOREIGN KEY `{$FK}`");
        echo "↺ أُسقط القيدُ المرجعيّ\n";
    }
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ems_event_delivery_orphans'");
    if ($r && (int) $r->fetch_row()[0] > 0) {
        $conn->query("INSERT INTO `ems_event_deliveries`
                      SELECT `id`,`consumer`,`event_id`,`attempts`,`last_error`,`next_retry_at`,
                             `updated_at`,`seed_tag`,`outbox_id`,`consumer_key`,`state`,`attempt_no`,
                             `next_attempt_at`,`claimed_at`,`processed_at`,`idempotency_key`,
                             `result_ref`,`fail_code`,`fail_text`,`company_id`
                        FROM `ems_event_delivery_orphans`");
        echo "↺ أُعيد {$conn->affected_rows} تسليمًا يتيمًا\n";
        $conn->query("DROP TABLE `ems_event_delivery_orphans`");
    }
    exit(0);
}

/* ══ ① التتبُّعُ الزمنيُّ والسببُ لكلِّ حالة ══════════════════════════════ */
$J = "FROM `ems_event_deliveries` d LEFT JOIN `ems_business_events` e ON e.`id` = d.`event_id`";
$W = "d.`event_id` IS NOT NULL AND e.`id` IS NULL";

$q = $conn->query("SELECT COUNT(*) n, MIN(d.`updated_at`) a, MAX(d.`updated_at`) b {$J} WHERE {$W}");
$x = $q->fetch_assoc();
$N = (int) $x['n'];
echo "① اليتيم: {$N} تسليمًا · من {$x['a']} إلى {$x['b']}\n";
if ($N === 0) { echo "   لا يتيمَ — يُمضى إلى القيد\n"; }

$q = $conn->query("SELECT COUNT(*) {$J} WHERE {$W} AND d.`event_id` >
                    (SELECT COALESCE(MAX(`id`),0) FROM `ems_business_events`)");
$never = (int) $q->fetch_row()[0];
echo "   ◆ يشير إلى جذرٍ لم يوجد قط: {$never} · **وإلى جذرٍ حُذف: " . ($N - $never) . "**\n";

/* ══ ② الأرشفةُ بالسببِ ثم الحذف ══════════════════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `ems_event_delivery_orphans`
              LIKE `ems_event_deliveries`");
/* عمودُ السببِ يُضاف إن لم يكن */
$r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ems_event_delivery_orphans'
                      AND COLUMN_NAME='orphan_reason'");
if ($r && (int) $r->fetch_row()[0] === 0) {
    $conn->query("ALTER TABLE `ems_event_delivery_orphans`
                  ADD COLUMN `orphan_reason` VARCHAR(300) NULL,
                  ADD COLUMN `archived_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
}

if ($N > 0) {
    /* ◆ السببُ يُشتقُّ من الصفِّ نفسِه لا يُكتب عمومًا — «سببٌ مكتوبٌ لكلِّ حالة»
     * ◆ **ولا `INSERT IGNORE`**: آليةٌ تبتلع محاولةَ كتابةٍ تُخفي فقدًا. والتكرارُ
     *   يُمنع بـ`ON DUPLICATE KEY UPDATE` الذي **يُبلّغ** ولا يبتلع، ثم يُقارَن
     *   المؤرشَفُ بالمحذوفِ قبلَ الحذفِ — فلا يُحذف صفٌّ لم يُؤرشَف. */
    $conn->query("INSERT INTO `ems_event_delivery_orphans`
        SELECT d.*,
               CASE
                 WHEN COALESCE(d.`seed_tag`,'') <> ''
                   THEN CONCAT('بقايا اختبار — بذرُ ', d.`seed_tag`, ' · كُنس جذرُه ونجا هو')
                 WHEN d.`fail_code` = 'NO_EVENT'
                   THEN CONCAT('رصده الحارسُ وأعلنه: fail_code=NO_EVENT · مستهلك ', d.`consumer`)
                 ELSE CONCAT('جذرٌ محذوفٌ بلا قيدٍ مرجعيّ · مستهلك ', d.`consumer`, ' · حالة ', d.`state`)
               END,
               NOW()
          {$J} WHERE {$W}
        ON DUPLICATE KEY UPDATE `orphan_reason` = VALUES(`orphan_reason`)");
    if ($conn->errno) { exit("✘ تعذّرت الأرشفة: {$conn->error} — لم يُحذف شيء\n"); }
    $arch = $conn->affected_rows;
    echo "② أُرشف: {$arch} تسليمًا بسببٍ مشتقٍّ من صفِّه\n";

    /* ◆ **لا يُحذف صفٌّ لم يُؤرشَف**: يُعَدُّ المؤرشَفُ فعلًا قبلَ الحذف */
    $q = $conn->query("SELECT COUNT(*) FROM `ems_event_delivery_orphans` o
                        WHERE EXISTS (SELECT 1 FROM `ems_event_deliveries` d
                                       WHERE d.`id` = o.`id`)");
    $safe = (int) $q->fetch_row()[0];
    if ($safe < $N) {
        exit("✘ المؤرشَفُ {$safe} واليتيمُ {$N} — **لا يُحذف ما لم يُؤرشَف**. أُوقفت الهجرة\n");
    }
    echo "   ✔ تحقُّقٌ قبلَ الحذف: {$safe} من {$N} مؤرشَفٌ فعلًا\n";

    $conn->query("DELETE d {$J} WHERE {$W}");
    echo "   حُذف من الجدولِ الحيّ: {$conn->affected_rows}\n";

    $q = $conn->query("SELECT `orphan_reason`, COUNT(*) n FROM `ems_event_delivery_orphans`
                        GROUP BY LEFT(`orphan_reason`, 30) ORDER BY n DESC LIMIT 6");
    while ($q && $y = $q->fetch_assoc()) { printf("     · %-58s %d\n", mb_substr($y['orphan_reason'], 0, 58), $y['n']); }
}

/* ══ ③ القيدُ المانع ══════════════════════════════════════════════════════ */
$q = $conn->query("SELECT COUNT(*) {$J} WHERE {$W}");
$left = (int) $q->fetch_row()[0];
if ($left > 0) { exit("✘ ما زال {$left} يتيمًا — لا يُضاف القيدُ فوقَ عطب\n"); }

$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ems_event_deliveries'
                      AND CONSTRAINT_NAME='{$FK}'");
if ($r && (int) $r->fetch_row()[0] > 0) {
    echo "③ القيدُ قائمٌ سلفًا\n";
} else {
    if (!$conn->query("ALTER TABLE `ems_event_deliveries`
            ADD CONSTRAINT `{$FK}` FOREIGN KEY (`event_id`)
            REFERENCES `ems_business_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE")) {
        exit("✘ تعذّر إضافةُ القيد: {$conn->error}\n");
    }
    echo "③ أُضيف القيدُ {$FK}: event_id ⇒ ems_business_events.id · ON DELETE CASCADE\n";
}

/* ══ ④ الشاهدُ الحيّ — أيمنع القيدُ فعلًا؟ ════════════════════════════════ */
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
$ghost = 999999999;
$conn->query("INSERT INTO `ems_event_deliveries` (`consumer`,`event_id`,`state`)
              VALUES ('__gap08_probe__', {$ghost}, 'published')");
$blocked = ($conn->errno !== 0);
if (!$blocked) {
    $conn->query("DELETE FROM `ems_event_deliveries` WHERE `consumer` = '__gap08_probe__'");
    echo "④ ✘ **القيدُ لم يمنع** إدراجَ تسليمٍ بجذرٍ غيرِ موجود — أُزيل صفُّ الفحص\n";
} else {
    echo "④ ✔ **القيدُ يمنع فعلًا**: رُفض إدراجُ تسليمٍ بجذرٍ غيرِ موجود (errno {$conn->errno})\n";
}
echo "◆ والقيدُ جُرِّب حيًّا — فقيدٌ يُعلَن ولا يُجرَّب توثيقٌ لا قيد.\n";
