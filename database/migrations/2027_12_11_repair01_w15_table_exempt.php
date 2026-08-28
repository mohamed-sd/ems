<?php
/**
 * 2027_12_11_repair01_w15_table_exempt.php — نموُّ الجداولِ يُعلَن بجولتِه لا يُستثنى صامتًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يحرسه `W15-05`**: *«لا جدولَ أعمالٍ جديدٌ أنشأته هذه المرحلة»* — ومقياسُه
 *   لقطةُ جداولَ أُخذت قبلَ المرحلة (`repair01_w15_table_snapshot` · 901 جدولًا)،
 *   وكلُّ جدولٍ خارجَها **يُعَدُّ نموًّا**.
 *
 * ◆ **والثغرةُ أنَّ اللقطةَ لا تعرف مَن أنشأ**: فجولةٌ أخرى تُنشئ جداولَها بحقٍّ،
 *   فتظهر في عدِّ `W15` نموًّا لها وهي ليست منها. وقِيس اليومَ **26 جدولًا** كلُّها
 *   من مجالِ الموردين (`sup_*`) وواحدٌ من تسويةِ الهجرة — ⛔ **ولا واحدَ منها من
 *   `W15`**.
 *
 * ◆ **والعلاجُ إعلانٌ لا توسيعُ استثناء**: توسيعُ نمطِ الاستبعادِ يُسكِت الحاجبَ
 *   عن كلِّ نموٍّ قادمٍ بالجملة، **وهو تليينٌ لا إصلاح**. فبدلًا منه: **سجلٌّ
 *   يُعلَن فيه كلُّ جدولٍ نامٍ بجولتِه وسببِه**، والحاجبُ يسقط على **ما لم
 *   يُعلَن** — فيبقى بأسنانِه كاملةً ويصير النموُّ حقيقةً مقروءةً لا فراغًا.
 *
 * ⛔ **ولا إعلانَ بلا جولةٍ وسبب** — والقاعدةُ نفسُها تردُّ الفارغ.
 *
 * التشغيل: php database/migrations/2027_12_11_repair01_w15_table_exempt.php
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
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$r = $conn->query("SHOW TABLES LIKE 'repair01_w15_table_exempt'");
if (!$r || $r->num_rows === 0) {
    $ok = $conn->query("CREATE TABLE `repair01_w15_table_exempt` (
      `table_name`  VARCHAR(96)  NOT NULL,
      `owner_round` VARCHAR(64)  NOT NULL COMMENT 'الجولة التي انشأته - لا W15',
      `why`         VARCHAR(400) NOT NULL,
      `declared_at` DATETIME     NOT NULL,
      `declared_by` VARCHAR(120) NOT NULL,
      PRIMARY KEY (`table_name`),
      /* ⛔ إعلانٌ بلا جولةٍ ولا سببٍ يُسكِت الحاجبَ ولا يُفسِّر شيئًا */
      CONSTRAINT `chk_w15_exempt_traced` CHECK (`owner_round` <> '' AND `why` <> '')
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='جداول نمت بعد لقطة W15 - معلنة بجولتها فالحاجب يسقط على غير المعلن'");
    if (!$ok) { exit("✘ {$conn->error}\n"); }
    echo "  ✔ أُنشئ: repair01_w15_table_exempt\n";
} else {
    echo "  ◆ قائمٌ سلفًا: repair01_w15_table_exempt\n";
}

/* ── الإعلانُ — كلُّ نامٍ اليومَ بجولتِه ─────────────────────────────────── */
$NOW = date('Y-m-d H:i:s');
$DECL = array(
    array('sup\\_%', 'RPR-W08 · الموجة ب — التعاقد والتوريد',
        'جداولُ مجالِ الموردين أنشأتها موجةُ التوريد بمهاجراتِها — ولا تمسُّها W15 (‏مساحاتٌ وتقارير) بحرفٍ'),
    array('gov_migration_settlement', 'RPR-0 · بوّابةُ الهجرةِ وتسويتُها',
        'دفترُ تسويةِ الهجرةِ سجلُّ حوكمةٍ أنشأه أمرُ البوّابة — لا حقيقةَ أعمالٍ ولا سطحَ W15'),
);
$n = 0; $kept = 0;
foreach ($DECL as $d) {
    list($pat, $round, $why) = $d;
    $rs = $conn->query("SELECT t.TABLE_NAME FROM information_schema.TABLES t
                          LEFT JOIN repair01_w15_table_snapshot s ON s.table_name = t.TABLE_NAME
                         WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
                           AND t.TABLE_NAME NOT LIKE 'repair01\\_%'
                           AND t.TABLE_NAME LIKE '" . $e($pat) . "'
                           AND s.table_name IS NULL");
    while ($rs && $x = $rs->fetch_row()) {
        $q = $conn->query("SELECT 1 FROM repair01_w15_table_exempt WHERE table_name = '" . $e($x[0]) . "'");
        if ($q && $q->num_rows) { $kept++; continue; }
        $ok = $conn->query("INSERT INTO repair01_w15_table_exempt
            (table_name, owner_round, why, declared_at, declared_by)
            VALUES ('" . $e($x[0]) . "','" . $e($round) . "','" . $e($why) . "','" . $e($NOW) . "','RPR-AMD01')");
        if (!$ok) { exit("✘ {$conn->error}\n"); }
        $n++;
    }
}
/* ⛔ **وما بقي غيرَ مُعلَنٍ يُطبَع ولا يُسكَت عنه** */
$rest = array();
$rs = $conn->query("SELECT t.TABLE_NAME FROM information_schema.TABLES t
                      LEFT JOIN repair01_w15_table_snapshot s ON s.table_name = t.TABLE_NAME
                      LEFT JOIN repair01_w15_table_exempt x ON x.table_name = t.TABLE_NAME
                     WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
                       AND t.TABLE_NAME NOT LIKE 'repair01\\_%'
                       AND s.table_name IS NULL AND x.table_name IS NULL");
while ($rs && $x = $rs->fetch_row()) { $rest[] = $x[0]; }

require_once __DIR__ . '/_ledger.php';
$ms = (int) round((microtime(true) - $t0) * 1000);
ems_migration_recorded(__FILE__, $conn, $ms);

printf("\n✔ أُعلن %d جدولًا · مُعلَنٌ سلفًا %d · **غيرُ مُعلَنٍ %d**%s\n",
    $n, $kept, count($rest), $rest ? ' ⇐ ' . implode('، ', $rest) : '');
