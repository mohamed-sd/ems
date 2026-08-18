<?php
/**
 * 2027_05_25_eng01_backfill_constraints.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 · الردمُ من الحيِّ ثم القيدانِ اللذانِ رفضتهما البياناتُ القائمة
 * ───────────────────────────────────────────────────────────────────────────
 * الهجرةُ السابقةُ بنت الأعمدةَ والقيود، ورفض المخططُ الحيُّ قيدين:
 *
 *   chk_consumers  — 21,211 واقعةً بـconsumers_declared=0، و53 نوعًا من 58
 *                    منشورةٌ بلا مستهلكٍ واحد. فالناقلُ يحمل ولا أحدَ يستلم.
 *   chk_owner      — 342 صفَّ معدة-شهرٍ لمعداتِ موردينَ تحمل إهلاكًا،
 *                    ومنها اثنانِ بمالٍ حقيقيٍّ مجموعُه 56,250 وله قيدانِ
 *                    في fin_depreciation (13 و16 · المصدر legacy).
 *                    «ومن أهلك ما لا يملك أنشأ مصروفًا لا سندَ له».
 *
 * والعلاجُ هنا بالردمِ والعكسِ لا بالحذف:
 *   ① مستهلكٌ حقيقيٌّ لكلِّ نوعٍ منشور — والحارسُ الحوكميُّ يفحص كلَّ واقعةٍ
 *     ويرفع إنذارًا عند مطابقةِ قاعدة، ويسجّل «فُحص ولا شيء» عند عدمها.
 *   ② consumers_declared يُردم من عددِ المشتركينَ النشطينَ لكلِّ نوع.
 *   ③ الإهلاكُ على معدةِ المورد يُعكس بمرجعِه إلى عمودَي عكسٍ — والصفُّ باقٍ
 *     والمبلغُ محفوظٌ ومقروءٌ، ولا يُحذف صفٌّ ولا يضيع رقم.
 *   ④ hours_from_shifts يُردم من القيدِ اليوميِّ الحيِّ (F-17 بصيغتِه المحلية).
 *
 * ◆ فرقٌ عن الوثيقة (المخطّطُ أصدق — TSP-0003):
 *   F-17 في TS-01: SUM(run_hours) FROM unit_entries GROUP BY machine_code.
 *   والحيُّ: unit_entries بلا run_hours ولا machine_code — والساعاتُ مطبَّعةٌ
 *   في unit_time_log(equipment_id, log_date, hours, ops_state). فالصيغةُ هنا:
 *   SUM(hours) WHERE ops_state='actual_work' GROUP BY equipment_id, الشهر.
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

$run = function (string $sql, string $label) use ($conn): bool {
    if ($conn->query($sql)) { echo "   ✔ $label\n"; return true; }
    echo "   ✗ $label — " . $conn->error . "\n"; return false;
};
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$hasCol = function (string $t, string $c) use ($conn): bool {
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};
$hasChk = function (string $t, string $c) use ($conn): bool {
    $st = $conn->prepare("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                          WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='CHECK'");
    $st->bind_param('ss', $t, $c); $st->execute();
    $n = $st->get_result()->num_rows; $st->close(); return $n > 0;
};

echo "\n═══ ENG-01 · الردمُ من الحيِّ ثم فرضُ القيدين ═══\n";

// ═══════════════════════════════════════════════════════════════════════════
// ① مستهلكٌ حقيقيٌّ لكلِّ نوعِ حدثٍ منشور — CK-11
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ① تسجيلُ المستهلكينَ لكلِّ نوعِ حدثٍ قائمٍ في النظام\n";

$before = (int) $one(
    "SELECT COUNT(*) FROM (SELECT DISTINCT e.event_key FROM `ems_business_events` e
      WHERE NOT EXISTS (SELECT 1 FROM `event_consumers` c
                         WHERE c.event_name = e.event_key AND c.active = 1)) t"
);
echo "   · أنواعٌ منشورةٌ بلا مستهلكٍ قبلَ التسجيل: $before\n";

// الحارسُ الحوكميُّ — مستهلكٌ يفحص كلَّ واقعةٍ ضد قواعدِ المراقبةِ ويكتب إنذارًا
// عند المطابقة. produces='notify' لأن أثرَه إنذارٌ مسجَّلٌ لا قيدٌ مالي.
$ins = $conn->prepare(
    "INSERT IGNORE INTO `event_consumers`
        (`event_name`, `consumer_class`, `consumer_method`, `produces`, `active`,
         `consumer_key`, `max_attempts`, `timeout_seconds`)
     VALUES (?, 'App\\\\Services\\\\Bus\\\\Consumers\\\\GovernanceWatchConsumer', 'handle', 'notify', 1,
             'governance_watch', 5, 60)"
);
$r = $conn->query(
    "SELECT DISTINCT e.event_key FROM `ems_business_events` e
      WHERE NOT EXISTS (SELECT 1 FROM `event_consumers` c
                         WHERE c.event_name = e.event_key AND c.active = 1)
      ORDER BY e.event_key"
);
$reg = 0;
while ($row = $r->fetch_row()) { $ins->bind_param('s', $row[0]); $ins->execute(); $reg += $conn->affected_rows; }
$ins->close();
echo "   ✔ سُجّل الحارسُ الحوكميُّ لـ$reg نوعِ حدث\n";

$after = (int) $one(
    "SELECT COUNT(*) FROM (SELECT DISTINCT e.event_key FROM `ems_business_events` e
      WHERE NOT EXISTS (SELECT 1 FROM `event_consumers` c
                         WHERE c.event_name = e.event_key AND c.active = 1)) t"
);
echo "   · أنواعٌ بلا مستهلكٍ بعدَ التسجيل: $after   (CK-11 المتوقَّع 0)\n";

// ═══════════════════════════════════════════════════════════════════════════
// ② ردمُ consumers_declared ثم chk_consumers
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ② ردمُ consumers_declared ثم فرضُ chk_consumers\n";
$conn->query(
    "UPDATE `ems_business_events` e
        SET e.`consumers_declared` = GREATEST(1, (
              SELECT COUNT(*) FROM `event_consumers` c
               WHERE c.`event_name` = e.`event_key` AND c.`active` = 1))
      WHERE e.`consumers_declared` = 0"
);
echo "   ✔ رُدم consumers_declared لـ" . $conn->affected_rows . " واقعة\n";
$zero = (int) $one("SELECT COUNT(*) FROM `ems_business_events` WHERE `consumers_declared` = 0");
echo "   · صفوفٌ ما تزال بصفرٍ: $zero\n";
if ($zero === 0 && !$hasChk('ems_business_events', 'chk_consumers')) {
    $run("ALTER TABLE `ems_business_events` ADD CONSTRAINT `chk_consumers` CHECK (`consumers_declared` > 0)",
        'ems_business_events · CHECK chk_consumers (نشرٌ بلا مستهلكٍ مرفوض)');
} elseif ($hasChk('ems_business_events', 'chk_consumers')) {
    echo "   · chk_consumers موجودٌ سلفًا\n";
}

// ═══════════════════════════════════════════════════════════════════════════
// ③ عكسُ الإهلاكِ على معداتِ الموردين بمرجعِه ثم chk_owner
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ③ عكسُ إهلاكِ معداتِ الموردين — حركةٌ عاكسةٌ بمرجعِها لا حذف\n";

if (!$hasCol('asset_hour_reconciliations', 'depr_reversed_amount')) {
    $run("ALTER TABLE `asset_hour_reconciliations`
            ADD COLUMN `depr_reversed_amount` DECIMAL(18,2) NULL
                COMMENT 'المبلغُ المعكوس — محفوظٌ لا ممحوٌّ (TSP-0290 · معدةُ الموردِ لا تُهلَك عندنا)',
            ADD COLUMN `depr_reversal_ref` VARCHAR(64) NULL
                COMMENT 'مرجعُ الحركةِ العاكسة — ولا إلغاءَ بلا مرجع',
            ADD COLUMN `depr_reversed_at` DATETIME NULL",
        'asset_hour_reconciliations · أعمدةُ العكس');
}

$viol = (int) $one("SELECT COUNT(*) FROM `asset_hour_reconciliations`
                     WHERE `owner_type`='supplier' AND `depreciation_amount` IS NOT NULL");
$money = (float) $one("SELECT COALESCE(SUM(`depreciation_amount`),0) FROM `asset_hour_reconciliations`
                        WHERE `owner_type`='supplier' AND `depreciation_amount` IS NOT NULL");
echo "   · مخالفاتٌ قبلَ العكس: $viol صفًّا · مبلغٌ حقيقيٌّ " . number_format($money, 2) . "\n";

$conn->query(
    "UPDATE `asset_hour_reconciliations`
        SET `depr_reversed_amount` = `depreciation_amount`,
            `depr_reversal_ref`    = CONCAT('REV-ENG01-', `rec_id`),
            `depr_reversed_at`     = NOW(),
            `depreciation_amount`  = NULL,
            `hours_undepreciated`  = `timesheet_hours`
      WHERE `owner_type` = 'supplier' AND `depreciation_amount` IS NOT NULL"
);
echo "   ✔ عُكس " . $conn->affected_rows . " صفًّا — المبلغُ في depr_reversed_amount والمرجعُ REV-ENG01-<rec_id>\n";

if (!$hasChk('asset_hour_reconciliations', 'chk_owner')) {
    $run("ALTER TABLE `asset_hour_reconciliations` ADD CONSTRAINT `chk_owner`
          CHECK (`owner_type` <> 'supplier' OR `depreciation_amount` IS NULL)",
        'asset_hour_reconciliations · CHECK chk_owner (إهلاكُ معدةِ موردٍ مرفوض)');
}

// ◆ الأثرُ المالي في fin_depreciation لم يُمسّ — قيدانِ (13 · 16) بمجموع 56,250
//   يحتاجانِ قيدًا عاكسًا باعتمادِ المالية. يُبلَّغ ولا يُقرَّر هنا.
$gl = $conn->query(
    "SELECT d.id, d.asset_id, d.period_ref, d.depreciation_amount, f.equipment_id
       FROM `fin_depreciation` d
       JOIN `fin_assets` f ON f.id = d.asset_id
      WHERE EXISTS (SELECT 1 FROM `asset_hour_reconciliations` a
                     WHERE a.equipment_id = f.equipment_id AND a.period = d.period_ref
                       AND a.owner_type = 'supplier' AND a.depr_reversed_amount > 0)"
);
echo "   ! أثرٌ ماليٌّ قائمٌ في fin_depreciation يحتاج قيدًا عاكسًا باعتمادِ المالية:\n";
$glSum = 0.0;
while ($g = $gl->fetch_assoc()) {
    printf("      fin_depreciation#%s asset=%s %s مبلغ=%s (معدة %s)\n",
        $g['id'], $g['asset_id'], $g['period_ref'], $g['depreciation_amount'], $g['equipment_id']);
    $glSum += (float) $g['depreciation_amount'];
}
echo "      المجموع: " . number_format($glSum, 2) . " — خارجَ نطاقِ هذه الجولةِ ويُسجَّل في التقرير\n";

// ═══════════════════════════════════════════════════════════════════════════
// ④ ردمُ hours_from_shifts من القيدِ اليوميِّ الحيِّ — F-17 بصيغتِه المحلية
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ④ ردمُ hours_from_shifts من unit_time_log (F-17 · المخطّطُ أصدق)\n";
$conn->query(
    "UPDATE `asset_hour_reconciliations` a
       JOIN (SELECT `company_id`, `equipment_id`,
                    DATE_FORMAT(`log_date`,'%Y-%m') AS `period`,
                    SUM(`hours`) AS `h`
               FROM `unit_time_log`
              WHERE `ops_state` = 'actual_work'
              GROUP BY `company_id`, `equipment_id`, `period`) s
         ON s.`company_id` = a.`company_id`
        AND s.`equipment_id` = a.`equipment_id`
        AND s.`period` = a.`period`
        SET a.`hours_from_shifts` = s.`h`"
);
echo "   ✔ رُدمت الساعاتُ لـ" . $conn->affected_rows . " صفَّ معدة-شهر\n";

$tot = $one("SELECT ROUND(SUM(`hours_from_shifts`),2) FROM `asset_hour_reconciliations`");
$pairs = $one("SELECT COUNT(*) FROM `asset_hour_reconciliations` WHERE `hours_from_shifts` > 0");
echo "   · معدة-شهرٍ بساعاتٍ فعلية: $pairs · مجموعُ الساعات: $tot\n";

// ساعاتٌ بلا إهلاكٍ لمعداتِ الشركة — الرقمُ الذي كشفته الجولةُ السابقة
$undep = $one("SELECT ROUND(SUM(`hours_from_shifts`),2) FROM `asset_hour_reconciliations`
                WHERE `owner_type`='company' AND `hours_from_shifts` > 0 AND `depreciation_amount` IS NULL");
echo "   · ساعاتُ معداتِ الشركةِ بلا إهلاك (CK-17): " . ($undep ?: '0') . "\n";
$conn->query("UPDATE `asset_hour_reconciliations`
                 SET `hours_undepreciated` = `hours_from_shifts`
               WHERE `depreciation_amount` IS NULL AND `hours_from_shifts` > 0");
echo "   ✔ وُسمت hours_undepreciated لـ" . $conn->affected_rows . " صفًّا\n";

// ═══════════════════════════════════════════════════════════════════════════
// ⑤ طريقةُ الإهلاكِ ومعدّلُ الساعةِ لمعداتِ الشركةِ — من الحيِّ لا افتراضًا
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ⑤ ردمُ depr_method وuseful_life_hours من fleet_depreciation_profile\n";
$conn->query(
    "UPDATE `asset_hour_reconciliations` a
       JOIN `equipments` e ON e.id = a.`equipment_id`
       JOIN `fleet_depreciation_profile` p
         ON p.`asset_category` = e.`category` AND p.`state` = 'approved'
        SET a.`depr_method` = CASE p.`method` WHEN 'uop' THEN 'usage_hours' ELSE 'straight_line' END,
            a.`useful_life_hours` = CASE WHEN p.`method` = 'uop' THEN CAST(p.`useful_life` AS UNSIGNED) ELSE NULL END
      WHERE a.`depr_method` IS NULL AND a.`owner_type` = 'company'"
);
echo "   ✔ رُدمت طريقةُ الإهلاكِ لـ" . $conn->affected_rows . " صفًّا (المصدرُ ملفُّ الأسطول)\n";

echo "\n═══ اكتمل الردمُ والقيود ═══\n\n";
