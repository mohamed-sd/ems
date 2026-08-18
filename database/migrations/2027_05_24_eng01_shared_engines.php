<?php
/**
 * 2027_05_24_eng01_shared_engines.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 · المحرّكاتُ المشتركةُ الثلاثة — ناقلُ الأحداثِ وطابورُ المهامِّ والاستعادة
 * ───────────────────────────────────────────────────────────────────────────
 * البندُ ① من TS-01 نُفِّذ أولًا (tools/eng01_schema_match.php) — والنتيجة:
 *
 *   المقترَح                    الحكم      النظيرُ الحيّ
 *   ─────────────────────────  ─────────  ──────────────────────────────────
 *   ems_event_outbox           وُسِّع      ems_business_events (21,211 صفًّا · ADR-15
 *                                         الجذرُ المحايدُ يُكتب داخلَ المعاملة)
 *   ems_event_subscriptions    وُسِّع      event_consumers (21 اشتراكًا حيًّا)
 *   ems_event_deliveries       وُسِّع      قائمٌ بالاسمِ نفسِه — لكنه عدّادُ محاولاتٍ
 *                                         عابرٌ يُحذف عند النجاح لا دفترَ تسليم
 *   ems_job_queue              وُسِّع      قائمٌ بالاسمِ نفسِه — بلا قفلٍ ولا مهلة
 *   ems_job_schedule           أُنشئ      recurring_tasks مفهومٌ آخرُ (تكرارُ تذاكرَ
 *                                         بقالبٍ — 20/20 موصولةٌ بـtask_templates)
 *   fa_asset_hours             وُسِّع      asset_hour_reconciliations (363 صفًّا)
 *   dr_drills                  أُنشئ      لا نظيرَ في 567 جدولًا
 *
 * ◆ وثلاثةُ مناظرَ بأسماءِ TS-01 حرفًا (ems_event_outbox · ems_event_subscriptions ·
 *   fa_asset_hours) فوقَ النظائرِ الموسَّعة — فتعمل فحوصُ CK-11 وCK-17 وCK-18
 *   بنصِّها بلا إنشاءِ جدولٍ ثالث. والمنظرُ ليس جدولًا.
 *
 * القيودُ الثلاثةُ التي طلبتها الحزمة:
 *   chk_consumers  نشرٌ بلا مستهلكٍ معلَنٍ مرفوض  (على الجذرِ المحايد)
 *   chk_result     نجاحٌ بلا مرجعٍ مرفوض           (على دفترِ التسليم)
 *   chk_owner      إهلاكُ معدةِ موردٍ مرفوض        (على دفترِ الساعات)
 *   + chk_fail · chk_lock · chk_job_type
 *
 * ◆ ولا يُحذف صفٌّ: صفوفُ UAT العشرون في الطابورِ والتسلياتِ تُوسَم seed_tag
 *   ويُحفظ نصُّها الأصليُّ في payload_json — ولا تُمحى.
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
$conn->query("SET SESSION collation_connection='utf8mb4_unicode_ci'");

// ───────────────────────── أدواتٌ عاطلةُ الأثر (idempotent) ─────────────────────────
$run = function (string $sql, string $label) use ($conn): bool {
    if ($conn->query($sql)) { echo "   ✔ $label\n"; return true; }
    echo "   ✗ $label — " . $conn->error . "\n";
    return false;
};
$hasCol = function (string $t, string $c) use ($conn): bool {
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};
$hasIdx = function (string $t, string $i) use ($conn): bool {
    $r = $conn->query("SHOW INDEX FROM `$t` WHERE Key_name='" . $conn->real_escape_string($i) . "'");
    return $r && $r->num_rows > 0;
};
$hasChk = function (string $t, string $c) use ($conn): bool {
    $st = $conn->prepare("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                          WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='CHECK'");
    $st->bind_param('ss', $t, $c); $st->execute();
    $n = $st->get_result()->num_rows; $st->close();
    return $n > 0;
};
$hasTbl = function (string $t) use ($conn): bool {
    $st = $conn->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->bind_param('s', $t); $st->execute();
    $n = $st->get_result()->num_rows; $st->close();
    return $n > 0;
};
$addCol = function (string $t, string $c, string $def) use ($conn, $hasCol, $run): void {
    if ($hasCol($t, $c)) { echo "   · $t.$c موجودٌ سلفًا\n"; return; }
    $run("ALTER TABLE `$t` ADD COLUMN `$c` $def", "$t.$c");
};
$addIdx = function (string $t, string $name, string $def) use ($hasIdx, $run): void {
    if ($hasIdx($t, $name)) { echo "   · $t.$name موجودٌ سلفًا\n"; return; }
    $run("ALTER TABLE `$t` ADD $def", "$t · $name");
};
$addChk = function (string $t, string $name, string $expr) use ($hasChk, $run): void {
    if ($hasChk($t, $name)) { echo "   · $t.$name موجودٌ سلفًا\n"; return; }
    $run("ALTER TABLE `$t` ADD CONSTRAINT `$name` CHECK ($expr)", "$t · CHECK $name");
};
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

echo "\n═══ ENG-01 · المحرّكاتُ المشتركة — بناءُ الجداولِ الستةِ وقيودِها ═══\n";

// ═══════════════════════════════════════════════════════════════════════════
// ① صندوقُ الأحداثِ الصادر — توسيعُ الجذرِ المحايد ems_business_events
//    (TSP-0202..0217 · 12 عمودًا و3 قيود — والقائمُ يحمل 8 منها بأسمائِه)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ① ems_event_outbox ⇐ توسيعُ ems_business_events\n";
$addCol('ems_business_events', 'consumers_declared',
    "SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TSP-0210: عددُ المستهلكينَ المعلَنينَ لحظةَ النشر — والقيدُ يمنع صفرًا'");
$addCol('ems_business_events', 'delivered_ok',
    "SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TSP-0211: كم مستهلكًا نجح'");
$addCol('ems_business_events', 'delivered_failed',
    "SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TSP-0212: كم مستهلكًا فشل'");
$addCol('ems_business_events', 'in_dlq',
    "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'TSP-0213: في صندوقِ الموتى'");
$addCol('ems_business_events', 'seed_tag',
    "VARCHAR(32) NULL COMMENT 'TSP-0214: وسمُ البيانِ المبذور — CK-10'");
$addIdx('ems_business_events', 'ix_pub',  'KEY `ix_pub` (`created_at`)');
$addIdx('ems_business_events', 'ix_code', 'KEY `ix_code` (`event_key`, `created_at`)');

// ═══════════════════════════════════════════════════════════════════════════
// ② اشتراكاتُ المستهلكين — توسيعُ event_consumers
//    (TSP-0218..0226 · 7 أعمدةٍ وقيدٌ واحد)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ② ems_event_subscriptions ⇐ توسيعُ event_consumers\n";
$addCol('event_consumers', 'consumer_key',
    "VARCHAR(64) NULL COMMENT 'TSP-0221: معرّفُ المستهلك — مفتاحُ العطالةِ يُبنى عليه'");
$addCol('event_consumers', 'max_attempts',
    "TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'TSP-0223: خمسُ محاولاتٍ ثم dlq'");
$addCol('event_consumers', 'timeout_seconds',
    "SMALLINT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'TSP-0224: مهلةُ المعالجة'");

// ردمُ consumer_key من اسمِ الصنفِ المعالج (الجزءُ الأخيرُ بعد الفاصل) — من الحيِّ لا حدسًا
$conn->query(
    "UPDATE `event_consumers`
        SET `consumer_key` = LOWER(SUBSTRING_INDEX(REPLACE(`consumer_class`,'\\\\','/'), '/', -1))
      WHERE `consumer_key` IS NULL OR `consumer_key` = ''"
);
echo "   ✔ ردمُ consumer_key لـ" . $conn->affected_rows . " اشتراكًا من اسمِ الصنف\n";

if (!$hasIdx('event_consumers', 'uq_sub')) {
    // فحصُ التصادمِ قبلَ الفرض — فمفتاحٌ فريدٌ يُضاف على مكرَّرٍ يفشل صامتًا في التشخيص
    $dup = (int) $one("SELECT COUNT(*) FROM (SELECT event_name, consumer_key FROM `event_consumers`
                        GROUP BY event_name, consumer_key HAVING COUNT(*) > 1) d");
    if ($dup > 0) {
        echo "   ! $dup تصادمًا في (event_name, consumer_key) — يُفضّ بإلحاقِ الطريقة\n";
        $conn->query("UPDATE `event_consumers` e
                      JOIN (SELECT event_name, consumer_key FROM `event_consumers`
                            GROUP BY event_name, consumer_key HAVING COUNT(*) > 1) d
                        ON d.event_name = e.event_name AND d.consumer_key = e.consumer_key
                       SET e.consumer_key = CONCAT(e.consumer_key, ':', COALESCE(e.consumer_method,'run'))");
    }
    $run("ALTER TABLE `event_consumers` ADD UNIQUE KEY `uq_sub` (`event_name`, `consumer_key`)", 'event_consumers · uq_sub');
}

// ═══════════════════════════════════════════════════════════════════════════
// ③ دفترُ التسليمِ — توسيعُ ems_event_deliveries من عدّادٍ عابرٍ إلى دفترٍ دائم
//    (TSP-0227..0244 · 12 عمودًا و5 قيود · ستُّ حالاتٍ لا واحدة)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ③ ems_event_deliveries — توسيعُ القائمِ إلى دفترٍ دائم\n";

// وسمُ صفوفِ UAT العشرين قبلَ أيِّ قيدٍ — لا تُحذف بل تُوسَم (CK-10)
$addCol('ems_event_deliveries', 'seed_tag',
    "VARCHAR(32) NULL COMMENT 'وسمُ البيانِ المبذور — الصفوفُ الملوَّثةُ تُوسَم ولا تُحذف'");
$conn->query("UPDATE `ems_event_deliveries` SET `seed_tag`='UAT-2026'
               WHERE `seed_tag` IS NULL AND `consumer` LIKE '%UAT-2026%'");
echo "   ✔ وُسم " . $conn->affected_rows . " صفًّا مبذورًا (UAT-2026) — بلا حذف\n";

$addCol('ems_event_deliveries', 'outbox_id',
    "BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TSP-0229: ems_business_events.id — و0 للصفوفِ السابقةِ للدفتر'");
$addCol('ems_event_deliveries', 'consumer_key',
    "VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'TSP-0230: مفتاحُ المستهلك'");
$addCol('ems_event_deliveries', 'state',
    "ENUM('published','claimed','processing','processed','failed','dlq') NOT NULL DEFAULT 'published' COMMENT 'TSP-0231: الحالاتُ الست'");
$addCol('ems_event_deliveries', 'attempt_no',
    "TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TSP-0232'");
$addCol('ems_event_deliveries', 'next_attempt_at',
    "DATETIME(3) NULL COMMENT 'TSP-0233: التباعدُ المتزايد — F-13 POW(4,attempt_no) ثانية'");
$addCol('ems_event_deliveries', 'claimed_at',
    "DATETIME(3) NULL COMMENT 'TSP-0234'");
$addCol('ems_event_deliveries', 'processed_at',
    "DATETIME(3) NULL COMMENT 'TSP-0235'");
$addCol('ems_event_deliveries', 'idempotency_key',
    "CHAR(64) NULL COMMENT 'TSP-0236 · F-14: SHA2(outbox_id|consumer_key,256) — خمسُ إعاداتٍ صفٌّ واحد'");
$addCol('ems_event_deliveries', 'result_ref',
    "VARCHAR(128) NULL COMMENT 'TSP-0237: مرجعُ ما كتبه المستهلك — والقيدُ يمنع نجاحًا بلا مرجع'");
$addCol('ems_event_deliveries', 'fail_code',
    "VARCHAR(32) NULL COMMENT 'TSP-0238'");
$addCol('ems_event_deliveries', 'fail_text',
    "TEXT NULL COMMENT 'TSP-0239'");
$addCol('ems_event_deliveries', 'company_id',
    "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'عمودُ العزل — TS-02'");

// ردمُ consumer_key ومفتاحِ العطالةِ للصفوفِ القائمة (بادئةُ legacy تمنع تصادمَ فضاءَي المعرّفات)
$conn->query("UPDATE `ems_event_deliveries` SET `consumer_key` = `consumer` WHERE `consumer_key` = ''");
$conn->query("UPDATE `ems_event_deliveries`
                 SET `idempotency_key` = SHA2(CONCAT_WS('|','legacy',`consumer`,`event_id`), 256)
               WHERE `idempotency_key` IS NULL");
echo "   ✔ رُدم مفتاحُ العطالةِ لـ" . $conn->affected_rows . " صفًّا قائمًا (ببادئةِ legacy)\n";

// المفتاحُ الأساسيُّ الاصطناعي: id — والمفتاحُ القديمُ (consumer,event_id) يبقى فريدًا
// كي لا ينكسر ON DUPLICATE KEY في الموزِّعِ القائم.
if (!$hasCol('ems_event_deliveries', 'id')) {
    $run("ALTER TABLE `ems_event_deliveries`
            DROP PRIMARY KEY,
            ADD UNIQUE KEY `uq_legacy_consumer_event` (`consumer`,`event_id`),
            ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
            ADD PRIMARY KEY (`id`)", 'ems_event_deliveries · id PRIMARY KEY');
}
$addIdx('ems_event_deliveries', 'uq_idem',  'UNIQUE KEY `uq_idem` (`idempotency_key`)');
$addIdx('ems_event_deliveries', 'ix_state', 'KEY `ix_state` (`state`, `next_attempt_at`)');
$addIdx('ems_event_deliveries', 'ix_outbox','KEY `ix_outbox` (`outbox_id`)');
// TSP-0243 · TSP-0244 — نجاحٌ بلا مرجعٍ مرفوض · وفشلٌ بلا رمزٍ مرفوض
$addChk('ems_event_deliveries', 'chk_result', "`state` <> 'processed' OR `result_ref` IS NOT NULL");
$addChk('ems_event_deliveries', 'chk_fail',   "`state` NOT IN ('failed','dlq') OR `fail_code` IS NOT NULL");

// ═══════════════════════════════════════════════════════════════════════════
// ④ طابورُ المهامِّ — توسيعُ ems_job_queue بالقفلِ الذرّيِّ والقائمةِ المغلقة
//    (TSP-0245..0261 · 13 عمودًا و3 قيود)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ④ ems_job_queue — القفلُ والمصدرُ والقائمةُ المغلقة\n";
$addCol('ems_job_queue', 'source',
    "ENUM('event','schedule','manual') NOT NULL DEFAULT 'schedule' COMMENT 'TSP-0249: حدثٌ أم جدولة — وmanual للموروثِ قبلَ الإلغاء'");
$addCol('ems_job_queue', 'source_ref',
    "VARCHAR(128) NULL COMMENT 'TSP-0250'");
$addCol('ems_job_queue', 'worker_id',
    "VARCHAR(64) NULL COMMENT 'TSP-0253: العاملُ الذي التقط'");
$addCol('ems_job_queue', 'claimed_at',
    "DATETIME(3) NULL COMMENT 'TSP-0254'");
$addCol('ems_job_queue', 'lock_expires_at',
    "DATETIME(3) NULL COMMENT 'TSP-0255: مهلةُ تحريرِ القفل — F-16'");
$addCol('ems_job_queue', 'fail_code',
    "VARCHAR(32) NULL COMMENT 'TSP-0257'");
$addCol('ems_job_queue', 'seed_tag',
    "VARCHAR(32) NULL COMMENT 'وسمُ البيانِ المبذور'");

// توسيعُ الحالاتِ لتشملَ claimed وdlq (القائمُ: queued·processing·done·failed·dead)
$stCol = $conn->query("SHOW COLUMNS FROM `ems_job_queue` LIKE 'state'")->fetch_assoc();
if (strpos((string) $stCol['Type'], "'claimed'") === false) {
    $run("ALTER TABLE `ems_job_queue` MODIFY COLUMN `state`
          ENUM('queued','claimed','processing','running','done','failed','dead','dlq')
          NOT NULL DEFAULT 'queued'
          COMMENT 'TSP-0252 — الستُّ المعلَنةُ + running/dead الموروثتان (المخطّطُ أصدق)'",
        'ems_job_queue.state ⇐ ثمانِ حالات');
}

// ◆ صفوفُ UAT: نصُّ ملاحظاتٍ في خانةِ نوع — يُحفظ نصُّها ثم يُصحَّح النوعُ ولا يُحذف صفّ
$leg = $conn->query(
    "SELECT job_id, job_type FROM `ems_job_queue`
      WHERE `seed_tag` IS NULL AND `job_type` LIKE '%UAT-2026%'"
);
$moved = 0;
while ($row = $leg->fetch_assoc()) {
    $st = $conn->prepare(
        "UPDATE `ems_job_queue`
            SET `payload_json` = JSON_SET(COALESCE(NULLIF(`payload_json`,''),'{}'), '$._legacy_job_type', ?),
                `job_type` = 'pilot_monitor',
                `seed_tag` = 'UAT-2026'
          WHERE `job_id` = ?"
    );
    $st->bind_param('si', $row['job_type'], $row['job_id']);
    $st->execute(); $st->close();
    $moved++;
}
echo "   ✔ $moved صفًّا مبذورًا: نصُّه الأصليُّ محفوظٌ في payload_json._legacy_job_type — بلا حذف\n";

$addIdx('ems_job_queue', 'ix_type', 'KEY `ix_type` (`job_type`, `state`)');
// TSP-0261 — مقفولةٌ بلا عاملٍ ولا مهلةٍ مرفوضة
$addChk('ems_job_queue', 'chk_lock',
    "`state` <> 'claimed' OR (`worker_id` IS NOT NULL AND `lock_expires_at` IS NOT NULL)");
// TSP-0248 — القائمةُ المغلقة: ثمانيةُ أنواعِ TS-01 + الموروثةُ الموثَّقةُ في تعليقِ العمود
$addChk('ems_job_queue', 'chk_job_type',
    "`job_type` IN ('fin_posting','capacity_rollup','depreciation_run','statement_build',
                    'alert_dispatch','event_retry','settlement_recalc','pilot_monitor',
                    'payroll_bind','periodic_cron','bank_recon','batch_loop')");

// ═══════════════════════════════════════════════════════════════════════════
// ⑤ جدولةُ المهامِّ الدورية — إنشاءٌ (recurring_tasks مفهومٌ آخر)
//    (TSP-0262..0271 · 8 أعمدةٍ وقيدٌ واحد)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ⑤ ems_job_schedule — إنشاءٌ (لا نظيرَ: recurring_tasks تكرارُ تذاكر)\n";
$run("CREATE TABLE IF NOT EXISTS `ems_job_schedule` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `company_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'عمودُ العزل — TS-02',
        `entity_layer` ENUM('operations','contracting','holding') NOT NULL DEFAULT 'operations' COMMENT 'TS-03',
        `job_type` VARCHAR(48) NOT NULL COMMENT 'TSP-0264',
        `cron_expr` VARCHAR(64) NOT NULL COMMENT 'TSP-0265: التعبيرُ الزمني',
        `max_runtime_seconds` SMALLINT UNSIGNED NOT NULL DEFAULT 600 COMMENT 'TSP-0266',
        `alert_after_seconds` SMALLINT UNSIGNED NOT NULL DEFAULT 3600 COMMENT 'TSP-0267: مهلةُ إنذارِ التوقف',
        `last_success_at` DATETIME(3) NULL COMMENT 'TSP-0268',
        `owner_role_id` SMALLINT UNSIGNED NOT NULL COMMENT 'TSP-0269: المسؤولُ عند التوقف',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'TSP-0270',
        `replaces_manual` VARCHAR(190) NULL COMMENT 'الأمرُ اليدويُّ الذي ألغته هذه الجدولة (البند ⑥)',
        `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_sched` (`job_type`),
        KEY `ix_sched_active` (`is_active`, `last_success_at`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='ENG-01 JB-06: جدولةُ المهامِّ الدورية — وتوقفُ العاملِ صامتًا أخطرُ من فشلِ مهمة'",
    'ems_job_schedule');

// ═══════════════════════════════════════════════════════════════════════════
// ⑥ ساعاتُ الأصول — توسيعُ asset_hour_reconciliations
//    (TSP-0272..0290 · 15 عمودًا و3 قيود · والقائمُ يحمل 6 منها بأسمائِه)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ⑥ fa_asset_hours ⇐ توسيعُ asset_hour_reconciliations (363 صفًّا)\n";
$addCol('asset_hour_reconciliations', 'asset_id',
    "INT UNSIGNED NULL COMMENT 'TSP-0275: fin_assets.id — NULL أي معدةٌ بلا أصلٍ مسجَّل'");
$addCol('asset_hour_reconciliations', 'machine_code',
    "VARCHAR(32) NULL COMMENT 'TSP-0276: الوصلُ بالقيدِ اليومي — equipments.code'");
$addCol('asset_hour_reconciliations', 'owner_type',
    "ENUM('company','supplier') NOT NULL DEFAULT 'company' COMMENT 'TSP-0277: معدةُ الموردِ لا تُهلَك عندنا'");
$addCol('asset_hour_reconciliations', 'depr_method',
    "ENUM('straight_line','usage_hours','units_produced') NULL COMMENT 'TSP-0278: تُقرَّر لا تُفترض'");
$addCol('asset_hour_reconciliations', 'useful_life_hours',
    "INT UNSIGNED NULL COMMENT 'TSP-0279'");
$addCol('asset_hour_reconciliations', 'hours_from_shifts',
    "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'TSP-0282 · F-17: محسوبةٌ من القيدِ اليومي (unit_time_log actual_work)'");
$addCol('asset_hour_reconciliations', 'hours_undepreciated',
    "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'TSP-0283: ساعاتٌ بلا إهلاك — كميةٌ لا راية'");
$addCol('asset_hour_reconciliations', 'cost_center_id',
    "INT UNSIGNED NULL COMMENT 'TSP-0284'");
$addCol('asset_hour_reconciliations', 'project_id',
    "INT UNSIGNED NULL COMMENT 'TSP-0285'");
$addCol('asset_hour_reconciliations', 'journal_ref',
    "VARCHAR(64) NULL COMMENT 'TSP-0287'");
$addCol('asset_hour_reconciliations', 'seed_tag',
    "VARCHAR(32) NULL COMMENT 'وسمُ البيانِ المبذور'");

// ردمُ machine_code وowner_type من الحيِّ — لا حدسًا
$conn->query("UPDATE `asset_hour_reconciliations` a
              JOIN `equipments` e ON e.id = a.equipment_id
                 SET a.machine_code = e.code,
                     a.owner_type   = CASE WHEN COALESCE(NULLIF(e.suppliers,''),'0') = '0'
                                           THEN 'company' ELSE 'supplier' END
               WHERE a.machine_code IS NULL");
echo "   ✔ رُدم machine_code وowner_type لـ" . $conn->affected_rows . " صفًّا من equipments\n";

$conn->query("UPDATE `asset_hour_reconciliations` a
              JOIN `fin_assets` f ON f.equipment_id = a.equipment_id AND COALESCE(f.is_deleted,0)=0
                 SET a.asset_id = f.id
               WHERE a.asset_id IS NULL");
echo "   ✔ رُبط asset_id لـ" . $conn->affected_rows . " صفًّا من fin_assets\n";

$addIdx('asset_hour_reconciliations', 'ix_machine', 'KEY `ix_machine` (`machine_code`, `period`)');
// TSP-0290 — إهلاكُ معدةِ موردٍ مرفوضٌ بنيويًّا
$addChk('asset_hour_reconciliations', 'chk_owner',
    "`owner_type` <> 'supplier' OR `depreciation_amount` IS NULL");

// ═══════════════════════════════════════════════════════════════════════════
// ⑦ محاضرُ الاستعادة — إنشاءٌ (لا نظيرَ في 567 جدولًا)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ⑦ dr_drills — إنشاءٌ (لا نظير)\n";
$run("CREATE TABLE IF NOT EXISTS `dr_drills` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `company_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'عمودُ العزل — TS-02',
        `entity_layer` ENUM('operations','contracting','holding') NOT NULL DEFAULT 'operations' COMMENT 'TS-03',
        `drill_no` VARCHAR(32) NOT NULL COMMENT 'رقمُ المحضر',
        `drill_kind` ENUM('pitr','full_restore','failover') NOT NULL DEFAULT 'pitr',
        `started_at` DATETIME(3) NOT NULL COMMENT 'بدءُ التجربة',
        `finished_at` DATETIME(3) NULL COMMENT 'نهايتُها — والفارقُ زمنُ الاستعادة',
        `target_point` DATETIME NOT NULL COMMENT 'PR-04: الدقيقةُ التي استُعيد إليها — لا نسخةٌ بل لحظة',
        `rpo_target_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 15 COMMENT 'PR-03: كم دقيقةً نقبل فقدَها — معلَنٌ ومقيس',
        `rpo_actual_minutes` SMALLINT UNSIGNED NULL COMMENT 'PR-03: المقيسُ فعلًا لا المقدَّر',
        `rto_actual_seconds` INT UNSIGNED NULL COMMENT 'زمنُ الاستعادةِ المقيس',
        `rows_before` BIGINT UNSIGNED NULL COMMENT 'صفوفٌ قبلَ نقطةِ الاستعادة — يجب أن تبقى',
        `rows_after_expected_gone` BIGINT UNSIGNED NULL COMMENT 'صفوفٌ بعدَها — يجب ألا تعود',
        `rows_after_actual` BIGINT UNSIGNED NULL COMMENT 'ما عاد فعلًا — والنجاحُ أن يكون صفرًا',
        `verdict` ENUM('pass','fail','aborted') NULL COMMENT 'الحكمُ من القياسِ لا من الادعاء',
        `binlog_first_file` VARCHAR(64) NULL,
        `binlog_last_file` VARCHAR(64) NULL,
        `evidence_path` VARCHAR(255) NULL COMMENT 'مسارُ الشواهدِ المحفوظة',
        `runbook_ref` VARCHAR(128) NULL COMMENT 'PR-06: محضرٌ يقرؤه من لم يبنِ النظام',
        `operator_note` VARCHAR(500) NULL,
        `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_by_role` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `approved_by` INT UNSIGNED NULL,
        `approved_by_role` SMALLINT UNSIGNED NULL,
        `approved_at` DATETIME NULL,
        `seed_tag` VARCHAR(32) NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_drill_no` (`company_id`, `drill_no`),
        KEY `ix_drill_time` (`company_id`, `started_at`),
        CONSTRAINT `chk_drill_verdict` CHECK (`verdict` IS NULL OR `finished_at` IS NOT NULL),
        CONSTRAINT `chk_drill_pass` CHECK (`verdict` <> 'pass' OR (`rows_after_actual` = 0 AND `rpo_actual_minutes` IS NOT NULL))
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='ENG-01 PR-01..PR-06: محاضرُ الاستعادةِ لنقطةِ زمن — ونسخةٌ لم تُختبر ليست نسخة'",
    'dr_drills');

// ═══════════════════════════════════════════════════════════════════════════
// ⑧ ثلاثةُ مناظرَ بأسماءِ TS-01 حرفًا — كي تعمل الفحوصُ بنصِّها بلا جدولٍ ثالث
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ⑧ مناظرُ التوافق (منظرٌ لا جدول — TSP-0002)\n";

$run("CREATE OR REPLACE SQL SECURITY INVOKER VIEW `ems_event_outbox` AS
      SELECT  e.`id`                 AS `id`,
              e.`company_id`         AS `company_id`,
              e.`event_key`          AS `event_code`,
              e.`entity_type`        AS `aggregate_type`,
              e.`entity_id`          AS `aggregate_id`,
              e.`payload`            AS `payload`,
              e.`created_at`         AS `published_at`,
              e.`consumers_declared` AS `consumers_declared`,
              e.`delivered_ok`       AS `delivered_ok`,
              e.`delivered_failed`   AS `delivered_failed`,
              e.`in_dlq`             AS `in_dlq`,
              e.`seed_tag`           AS `seed_tag`
        FROM `ems_business_events` e", 'VIEW ems_event_outbox');

$run("CREATE OR REPLACE SQL SECURITY INVOKER VIEW `ems_event_subscriptions` AS
      SELECT  c.`c_id`            AS `id`,
              c.`event_name`      AS `event_code`,
              c.`consumer_key`    AS `consumer_key`,
              c.`consumer_class`  AS `handler_class`,
              c.`consumer_method` AS `handler_method`,
              c.`max_attempts`    AS `max_attempts`,
              c.`timeout_seconds` AS `timeout_seconds`,
              c.`active`          AS `is_active`
        FROM `event_consumers` c", 'VIEW ems_event_subscriptions');

$run("CREATE OR REPLACE SQL SECURITY INVOKER VIEW `fa_asset_hours` AS
      SELECT  a.`rec_id`                AS `id`,
              a.`company_id`            AS `company_id`,
              a.`asset_id`              AS `asset_id`,
              a.`equipment_id`          AS `equipment_id`,
              a.`machine_code`          AS `machine_code`,
              a.`owner_type`            AS `owner_type`,
              a.`depr_method`           AS `depr_method`,
              a.`useful_life_hours`     AS `useful_life_hours`,
              a.`depreciation_per_hour` AS `rate_per_hour`,
              STR_TO_DATE(CONCAT(a.`period`,'-01'),'%Y-%m-%d') AS `period_month`,
              a.`hours_from_shifts`     AS `hours_from_shifts`,
              a.`hours_undepreciated`   AS `hours_undepreciated`,
              a.`cost_center_id`        AS `cost_center_id`,
              a.`project_id`            AS `project_id`,
              a.`depreciation_amount`   AS `depr_amount`,
              a.`journal_ref`           AS `journal_ref`,
              a.`seed_tag`              AS `seed_tag`
        FROM `asset_hour_reconciliations` a", 'VIEW fa_asset_hours');

// ═══════════════════════════════════════════════════════════════════════════
// ⑨ قادحُ التباعدِ المتزايد F-13 — الصيغةُ قادحٌ لا إدخالٌ من الشاشة
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ⑨ قادحُ F-13 — التباعدُ المتزايد (1·4·16·64·256 ثانية)\n";
$conn->query("DROP TRIGGER IF EXISTS `trg_delivery_backoff`");
$run("CREATE TRIGGER `trg_delivery_backoff` BEFORE UPDATE ON `ems_event_deliveries`
      FOR EACH ROW
      BEGIN
        IF NEW.`state` = 'failed' AND NEW.`attempt_no` <> OLD.`attempt_no` THEN
          SET NEW.`next_attempt_at` = NOW(3) + INTERVAL POW(4, LEAST(NEW.`attempt_no`, 4)) SECOND;
        END IF;
        IF NEW.`state` = 'processed' AND NEW.`processed_at` IS NULL THEN
          SET NEW.`processed_at` = NOW(3);
        END IF;
      END", 'TRIGGER trg_delivery_backoff (F-13)');

echo "\n═══ اكتملت الهجرةُ البنيوية ═══\n";
echo "◆ chk_consumers مؤجَّلٌ إلى هجرةِ الردمِ — فالقيدُ يُفحص على الصفوفِ القائمةِ\n";
echo "  و21,211 واقعةً بلا consumers_declared ترفضه حتى يُسجَّل مستهلكٌ لكلِّ نوع.\n\n";
