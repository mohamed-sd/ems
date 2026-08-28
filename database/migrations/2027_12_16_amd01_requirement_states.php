<?php
/**
 * 2027_12_16_amd01_requirement_states.php — دفترُ المتطلباتِ يكتسب موضعَ حكمِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس**: `repair01_requirements` **أحدَ عشرَ عمودًا وليس فيها
 *   حالةٌ ولا دليل** — `requirement_id` · `wave` · `stage_no` · `unit` ·
 *   `dependency` · `seq` · `group_name` · `surface` · `grain` ·
 *   `source_of_truth` · `src_ref`. و`AMD-01` المرحلة ٣ توجب **لكلِّ متطلبٍ
 *   حالةً فعليّةً من ستّ** و**عقدَ إثباتٍ بحسبِ نوعِه**. ⇒ **فالحكمُ يحتاج
 *   موضعًا يُكتب فيه**، وبلا الموضعِ يعيش الحكمُ في تقريرٍ ويموت معه.
 *
 * ◆ **والحالاتُ الستُّ** (`MASTER_EXEC` §٤·٣): `EVIDENCE_CLOSED` ·
 *   `IMPLEMENTED_NOT_VERIFIED` · `PARTIALLY_IMPLEMENTED` ·
 *   `INCORRECTLY_IMPLEMENTED` · `NOT_IMPLEMENTED` · `NOT_APPLICABLE`.
 *   ⛔ **ووجودُ الكودِ ليس إغلاقًا** — ولذلك `IMPLEMENTED_NOT_VERIFIED` حالةٌ
 *   قائمةٌ بذاتِها لا مرادفٌ للإغلاق.
 *
 * ◆ **والأنواعُ الخمسةُ** (`AMD-01` §٣·١): مرجعيٌّ/بنيويّ · معاملة · حدثٌ
 *   وتكامل · إسقاطٌ وتقرير · رحلةٌ عابرة. ⛔ **ولا يُختار النوعُ بالاجتهاد —
 *   يُشتقّ من نوعِ السطحِ في الدليلِ المعماريّ**؛ *«فمن يصنّف بنفسه يستطيع
 *   تخفيفَ عبءِ الإثبات بأن يجعل كلَّ شيءٍ بنيويًّا»*.
 *
 * ◆ **وعمودٌ سادسٌ للهويّة**: `identity_status`. فالمطابقةُ اليومَ **بالاسمِ لا
 *   بالمعرِّف**، و`RPR-02` §٤·١ ينهى عن قياسِ تغطيةٍ على كونَين. ⇒ متطلبٌ لا
 *   يجد مقابلَه بالاسمِ **يُوسَم `UNMATCHED_PENDING_RECONCILIATION`** ولا يُحكَم
 *   عليه بالغياب. ⛔ **فغيابُ الاسمِ ليس غيابَ السطح.**
 *
 * التشغيل: php database/migrations/2027_12_16_amd01_requirement_states.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

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

$add = function ($col, $ddl) use ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM `repair01_requirements` LIKE '" . $col . "'");
    if ($r && $r->num_rows) { echo "  ◆ `$col` قائمٌ سلفًا\n"; return; }
    $ok = $conn->query("ALTER TABLE `repair01_requirements` ADD COLUMN $ddl");
    if (!$ok) { exit("✘ تعذّرت إضافةُ `$col`: {$conn->error}\n"); }
    echo "  ✔ أُضيف `$col`\n";
};

$add('amd01_state', "`amd01_state` ENUM('EVIDENCE_CLOSED','IMPLEMENTED_NOT_VERIFIED',
        'PARTIALLY_IMPLEMENTED','INCORRECTLY_IMPLEMENTED','NOT_IMPLEMENTED','NOT_APPLICABLE') NULL
        COMMENT 'الحالة الفعلية من الست — AMD-01 المرحلة 3 · ووجود الكود ليس اغلاقا'");
$add('requirement_type', "`requirement_type` ENUM('STRUCTURAL','TRANSACTION','EVENT_INTEGRATION',
        'PROJECTION_REPORT','CROSS_JOURNEY') NULL
        COMMENT 'يشتق من نوع السطح في الدليل المعماري — ولا يختار بالاجتهاد'");
$add('proof_contract', "`proof_contract` VARCHAR(400) NOT NULL DEFAULT ''
        COMMENT 'عقد الاثبات اللازم لنوعه — AMD-01 §3-1 · ولا يطالب نوع بعقد غيره'");
$add('state_evidence', "`state_evidence` VARCHAR(400) NOT NULL DEFAULT ''
        COMMENT 'القياس الذي انتج الحالة — ولا حالة بلا دليل يسميها'");
$add('identity_status', "`identity_status` ENUM('MATCHED_BY_NAME','MATCHED_BY_ID',
        'UNMATCHED_PENDING_RECONCILIATION') NOT NULL DEFAULT 'UNMATCHED_PENDING_RECONCILIATION'
        COMMENT 'RPR-02 §4-1: لا تقاس تغطية على كونين — وغياب الاسم ليس غياب السطح'");
$add('state_at', "`state_at` DATETIME NULL COMMENT 'زمن الحكم'");
$add('state_snapshot', "`state_snapshot` VARCHAR(48) NOT NULL DEFAULT ''
        COMMENT 'معرف اللقطة التي قيس عليها — ولا رقم بلا لقطته'");

/* ⛔ **والقاعدةُ الصلبةُ تُؤجَّل حتى يصدق المحتوى** — الدرسُ نفسُه من
     `2027_12_14`: قاعدةٌ فوقَ خرقٍ قائمٍ إمّا تُرَدّ وإمّا تُغري بملءِ الفراغ.
     وتُقفل في `2027_12_17` بعد تشغيلِ `amd01_phase3_requirements.php --apply`. */

$r = $conn->query("SELECT COUNT(*) FROM repair01_requirements");
printf("\n  متطلباتٌ في الدفتر: **%d** · وكلُّها بلا حكمٍ بعد\n", (int) $r->fetch_row()[0]);

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));

echo "\n✔ الدفترُ اكتسب موضعَ حكمِه — والحكمُ يليه\n";
