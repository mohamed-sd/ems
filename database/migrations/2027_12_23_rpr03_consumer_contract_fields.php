<?php
/**
 * 2027_12_23_rpr03_consumer_contract_fields.php — حقولُ عقدِ الأثرِ الأربعةُ الغائبة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يوجبه الأمر** — `RPR-03` §٤·٢ الخطوة ٣: *«عقدُ أثرٍ **مسجَّلٌ** لكلِّ
 *   مستهلك: الحمولةُ ومفتاحُ منعِ التكرارِ وسياسةُ الإعادةِ وسلوكُ الفشلِ وأثرُ
 *   التدقيق»*.
 *
 * ◆ **وما قِستُه**: `event_consumers` يحمل **واحدًا من الخمسة** — `max_attempts`
 *   (‏سياسةُ الإعادة). **وأربعةٌ لا موضعَ لها في المخزنِ أصلًا**. ⇒ فالعقدُ
 *   **لا يمكن أن يُسجَّل** لا أنّه لم يُسجَّل — وهذا حاجزٌ بنيويٌّ لا تقصير.
 *
 * ◆ **وأثرُه على القياسِ مباشر**: §٤·٢ الخطوة ٤ توجب **إثباتَ عدمِ التكرار**
 *   («إرسالُ الحدثِ نفسِه مرّتَين يُنتج أثرًا واحدًا — **يُختبر لا يُفترض**»)،
 *   ⛔ **ولا يُثبَت منعُ تكرارٍ بلا مفتاحِه**. فغيابُ العمودِ يجعل المقياسَ
 *   غيرَ قابلٍ للقياسِ أصلًا لا راسبًا.
 *
 * ◆ **والإضافةُ آمنةٌ على جدولٍ حيّ**: أعمدةٌ اختياريّةٌ بقيمٍ افتراضيّةٍ فارغة
 *   — **ولا تمسُّ صفًّا قائمًا ولا قارئًا**. ⛔ ولا تُملأ هنا: ملؤها **عقدٌ لكلِّ
 *   مستهلكٍ على حدة** وهو عملُ §٤·٢ لا محتوى هجرة.
 *
 * ⛔ **ولا قاعدةَ صلبةٌ الآن**: قاعدةٌ توجب العقدَ كاملًا فوقَ ١١٩ صفًّا فارغًا
 *   تُرَدُّ فورًا — والترتيبُ المحروقُ في هذه الجولة: الموضعُ ← ثمَّ الملءُ
 *   بدليلِه ← ثمَّ القفل.
 *
 * التشغيل: php database/migrations/2027_12_23_rpr03_consumer_contract_fields.php
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
    $r = $conn->query("SHOW COLUMNS FROM `event_consumers` LIKE '" . $col . "'");
    if ($r && $r->num_rows) { echo "  ◆ `$col` قائمٌ سلفًا\n"; return; }
    if (!$conn->query("ALTER TABLE `event_consumers` ADD COLUMN $ddl")) {
        exit("✘ تعذّرت إضافةُ `$col`: {$conn->error}\n");
    }
    echo "  ✔ أُضيف `$col`\n";
};

$add('payload_schema', "`payload_schema` VARCHAR(400) NOT NULL DEFAULT ''
        COMMENT 'RPR-03 §4-2: الحمولة التي يقرؤها المستهلك — حقولها لا وصفها'");
$add('idempotency_key', "`idempotency_key` VARCHAR(160) NOT NULL DEFAULT ''
        COMMENT 'RPR-03 §4-2: مفتاح منع التكرار — ولا يثبت منع تكرار بلا مفتاحه'");
$add('failure_behavior', "`failure_behavior` VARCHAR(255) NOT NULL DEFAULT ''
        COMMENT 'RPR-03 §4-2: ماذا يقع عند الفشل — يظهر ويعاد او يعوض ولا يختفي صامتا'");
$add('audit_effect', "`audit_effect` VARCHAR(255) NOT NULL DEFAULT ''
        COMMENT 'RPR-03 §4-2: اثر التدقيق الذي يخلفه الاستهلاك'");

$r = $conn->query("SELECT COUNT(*) n,
                          SUM(payload_schema <> '') a, SUM(idempotency_key <> '') b,
                          SUM(failure_behavior <> '') c, SUM(audit_effect <> '') d
                     FROM event_consumers");
$x = $r->fetch_assoc();
printf("\n  اشتراكاتٌ: **%d** · بحمولةٍ %d · بمفتاحٍ %d · بسلوكِ فشلٍ %d · بأثرِ تدقيقٍ %d\n",
       (int) $x['n'], (int) $x['a'], (int) $x['b'], (int) $x['c'], (int) $x['d']);
echo "  ⛔ **والملءُ عقدٌ لكلِّ مستهلكٍ على حدة** — عملُ §٤·٢ لا محتوى هجرة\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ صار للعقدِ موضعٌ يُسجَّل فيه — والتسجيلُ يليه\n";
