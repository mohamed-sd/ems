<?php
/**
 * 2027_10_13_frd_sec001_party_scope_registry.php
 *   FR-SEC-001 · FR-SEC-002 · CHG-SEC-SCOPE-01 — نطاقُ الأطرافِ يفشل **مغلقًا**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبان** (الدفتر · GAP-22 · P1):
 *   · FR-SEC-001: «نطاقُ الأطرافِ يفشل مغلقًا: الدورُ غيرُ المصنَّفِ يُرجع نطاقًا
 *     **فارغًا** لا نطاقًا مفتوحًا» · والسالب: «دورٌ غيرُ مصنَّفٍ ← **صفرُ صفٍّ**
 *     لا كلُّ الصفوف».
 *   · FR-SEC-002: «لكلِّ دورٍ معلومٍ نطاقُ أطرافٍ مُعلَنٌ في **مصدرٍ واحد** — لا
 *     في كودِ الشاشة» · ومعيارُه: «صفرُ دورٍ بلا نطاقٍ معلَن».
 *
 * ◆ **والعطبُ كان سطرًا واحدًا**: `fin_party_scope()` تنتهي بـ`return null`
 *   و`null` تعني **كلَّ الصفوف** عند مستهلكَيها. فأيُّ دورٍ لم تسمِّه الدالةُ
 *   يرى ذممَ المنشأةِ ومدفوعاتِها كاملةً — **انفتاحٌ بالصمتِ لا بقرار**.
 *
 * ◆ **والتفويضُ صريح**: أمرُ التنفيذِ §خامسًا-3 يأمر بالقلبِ حرفًا: «حوّل
 *   fin_party_scope إلى Fail Closed · الدور غير المصنف: صفر صفوف · ولا null
 *   بمعنى كل البيانات». فهذا قرارُ مالكٍ لا اجتهادُ منفِّذ.
 *
 * ◆ **والمصدرُ الواحدُ جدولٌ لا شيفرة**: `fin_party_scope_registry` يُعلن لكلِّ
 *   دورٍ نطاقَه ومصدرَ قرارِه — فيُقاس «صفرُ دورٍ بلا نطاقٍ معلَن» على بيانات.
 *
 * التشغيل:  php database/migrations/2027_10_13_frd_sec001_party_scope_registry.php
 * الرجوع :  php database/migrations/2027_10_13_frd_sec001_party_scope_registry.php --revert
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

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `fin_party_scope_registry`");
    echo "↺ أُسقط سجلُّ نطاقاتِ الأطراف\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `fin_party_scope_registry` (
    `role_id` INT NOT NULL,
    `party_scope` VARCHAR(16) NOT NULL COMMENT 'employee · supplier · client · ALL',
    `decision_source` VARCHAR(160) NOT NULL,
    `decided_at` DATE NOT NULL,
    PRIMARY KEY (`role_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='FR-SEC-002 — مصدرٌ واحدٌ لنطاقِ الأطراف · الدورُ غيرُ المسجَّلِ يفشل مغلقًا'");

/* ◆ **ما كان في الشيفرةِ يُنقل كما هو — ولا يُوسَّع ولا يُضيَّق**: القرارُ
 *   المرجعيُّ 2026-07-17 المكتوبُ في تعليقِ الدالةِ نفسِها، منقولًا حرفًا.
 *   وعائلةُ الماليةِ تُعلَن `ALL` صراحةً — فالانفتاحُ المشروعُ يُكتب ولا يُترك
 *   فراغًا يُقرأ انفتاحًا. */
$SRC = 'قرار 2026-07-17 — كل إدارةٍ ترى بيانات أطرافها · أمر التنفيذ INJ-FRD-REM-01 §خامسًا-3';
$MAP = array(
    4  => 'employee',   /* الموارد البشرية — الرواتب شأنها */
    2  => 'supplier',   /* إدارة الموردين */
    8  => 'supplier',   /* مشرف موردين (بوابةٌ خارجية) */
    16 => 'supplier',   /* المشتريات */
    17 => 'ALL', 18 => 'ALL', 19 => 'ALL', 20 => 'ALL', 21 => 'ALL', 22 => 'ALL',
);
$st = $conn->prepare("INSERT INTO `fin_party_scope_registry`
        (`role_id`,`party_scope`,`decision_source`,`decided_at`)
        VALUES (?,?,?, '2026-07-17')
        ON DUPLICATE KEY UPDATE `party_scope`=VALUES(`party_scope`),
                                `decision_source`=VALUES(`decision_source`)");
$n = 0;
foreach ($MAP as $role => $scope) {
    $st->bind_param('iss', $role, $scope, $SRC);
    if ($st->execute()) { $n++; }
}
$st->close();
printf("① سُجِّل نطاقُ %d دورٍ في المصدرِ الواحد\n", $n);

/* ── ② القياس: كم دورًا نشطًا بلا نطاقٍ معلَن؟ ─────────────────────────── */
$q = $conn->query("SELECT r.`id`, r.`name` FROM `roles` r
                    LEFT JOIN `fin_party_scope_registry` s ON s.`role_id` = r.`id`
                   WHERE s.`role_id` IS NULL ORDER BY r.`id`");
$missing = array();
while ($q && $x = $q->fetch_assoc()) { $missing[] = $x['id'] . ':' . mb_substr($x['name'], 0, 18); }
printf("② أدوارٌ بلا نطاقٍ معلَن: **%d** — وكلُّها **تفشل مغلقةً** بعدَ هذا التغيير\n", count($missing));
if ($missing) {
    echo "   " . implode(' · ', array_slice($missing, 0, 12)) . (count($missing) > 12 ? ' …' : '') . "\n";
    echo "   ◆ وهذا **ليس عيبًا بل الحالُ المقصود**: الدورُ غيرُ المصنَّفِ لا يرى\n";
    echo "     ذممًا ولا مدفوعات. ومن أراد له نطاقًا **أعلنه في هذا السجل**.\n";
}

ems_migration_recorded(__FILE__, $conn, 0);
