<?php
/**
 * 2027_11_03_injfrd66_xc12_projection_tags.php
 *   XC-12 — وسمُ إسقاطاتِ الماليةِ والخزينةِ بمالكِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «صفرُ شاشةٍ ماليةٍ أصليةٍ في الإدارتَين · **وكلُّ إسقاطٍ
 *   موسوم**» — وحالةُ الفاتورةِ والتحصيلِ والصرفِ إسقاطاتُ قراءةٍ موسومةٌ
 *   بمالكِها.
 *
 * ◆ **والشقُّ الأولُ مستوفًى سلفًا مقيسًا**: صفرُ `INSERT`/`UPDATE`/`DELETE`
 *   على جدولٍ ماليٍّ (`fin_*` · `bank_*`) من أيِّ سطحٍ في `Clients`
 *   و`Contracts` و`Suppliers` و`Opportunities` و`Projects`. فلا شاشةَ ماليةً
 *   **أصليةً** في الإدارتَين — والباقي وسمُ الإسقاط.
 *
 * ◆ **والمالكُ يُشتقُّ ممّا يُقرأ لا ممّا كُتب في الحقل**:
 *   | السطح | ما يقرأ | الحكم |
 *   |---|---|---|
 *   | `Contracts/collections.php` | **`fin_payments`** — جدولٌ ماليّ | إسقاطٌ · مالكُه المالية · **ومالكُه المسجَّلُ كان «المبيعات» فيُصحَّح** |
 *   | `Contracts/tax_invoices.php` | حالةُ الفاتورة | إسقاطٌ · مالكُه المالية (مسجَّلٌ صحيحًا) |
 *
 * ◆ **وما ليس إسقاطًا لا يُوسَم**: `Contracts/contract_payment_schedule.php`
 *   يقرأ `contracts` لا جدولًا ماليًّا — فهو **شرطُ عقدٍ تملكه المبيعات**
 *   لا حالةَ صرفٍ من المالية. ووسمُه إسقاطًا يُخرجه من يدِ مالكِه بلا سبب.
 *   وكذلك `client_profile.php` و`settlements.php`: أسطحٌ **تملكها إدارتُها**
 *   وفيها قسمُ إسقاط — فالوسمُ على القسمِ لا على السطحِ كلِّه.
 *
 * التشغيل:  php database/migrations/2027_11_03_injfrd66_xc12_projection_tags.php
 * الرجوع :  php database/migrations/2027_11_03_injfrd66_xc12_projection_tags.php --revert
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

$NATURE = 'إسقاطُ قراءة';
$OWNER  = 'المالية والخزينة';

$conn->query("CREATE TABLE IF NOT EXISTS `injfrd66_xc12_backup` (
    `route` VARCHAR(160) NOT NULL PRIMARY KEY,
    `nature` VARCHAR(120) NULL,
    `owner_dept` VARCHAR(120) NULL,
    `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if (in_array('--revert', $argv, true)) {
    $n = 0;
    $res = $conn->query("SELECT * FROM `injfrd66_xc12_backup`");
    while ($res && ($r = $res->fetch_assoc())) {
        $st = $conn->prepare("UPDATE `nav_canonical` SET `nature`=?, `owner_dept`=? WHERE LOWER(`route`)=LOWER(?)");
        $st->bind_param('sss', $r['nature'], $r['owner_dept'], $r['route']);
        $st->execute(); $n += $st->affected_rows; $st->close();
    }
    $conn->query("TRUNCATE `injfrd66_xc12_backup`");
    echo "↺ أُعيد {$n} صفًّا\n";
    exit(0);
}

/* ── ① التحقُّقُ من الشقِّ الأول: صفرُ كتابةٍ ماليةٍ من الإدارتَين ──────── */
echo "① صفرُ شاشةٍ ماليةٍ أصليةٍ في الإدارتَين:\n";
$dirs = array('Clients', 'Contracts', 'Suppliers', 'Opportunities', 'Projects');
$writers = array();
foreach ($dirs as $d) {
    foreach ((array) glob($ROOT . '/' . $d . '/*.php') as $f) {
        $body = (string) @file_get_contents($f);
        if ($body === '') { continue; }
        if (preg_match('~(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?(fin_[a-z_]+|bank_[a-z_]+)`?~i', $body)) {
            $writers[] = $d . '/' . basename($f);
        }
    }
}
if ($writers) {
    fwrite(STDERR, "✘ " . count($writers) . " سطحًا يكتب في جدولٍ ماليّ — الشقُّ الأولُ غيرُ مستوفٍ:\n");
    foreach ($writers as $w) { fwrite(STDERR, "   {$w}\n"); }
    exit(1);
}
echo "   ✔ صفرُ كتابةٍ في جدولٍ ماليٍّ من الأدلّةِ الخمسة\n";

/* ── ② الإسقاطاتُ تُوسَم — والمالكُ يُشتقُّ ممّا يُقرأ ──────────────────── */
$ROWS = array(
    'Contracts/collections.php'  => 'ذمم العملاء وأعمارها — يقرأ `fin_payments`',
    'Contracts/tax_invoices.php' => 'الفاتورة الضريبية والإقرارات — حالةُ الفاتورة',
);
echo "\n② وسمُ الإسقاطاتِ بمالكِها:\n";
$n = 0;
foreach ($ROWS as $route => $why) {
    $q = $conn->query("SELECT id, IFNULL(nature,'') nat, IFNULL(owner_dept,'') own
                         FROM `nav_canonical` WHERE LOWER(`route`)=LOWER('" . $conn->real_escape_string($route) . "')");
    $cur = $q ? $q->fetch_assoc() : null;
    if (!$cur) { printf("   ⚠ %s — لا صفَّ كنسيًّا · يُتخطّى\n", $route); continue; }
    if ($cur['nat'] === $NATURE && $cur['own'] === $OWNER) { printf("   ○ %s — موسومٌ سلفًا\n", $route); continue; }

    $st = $conn->prepare("INSERT IGNORE INTO `injfrd66_xc12_backup` (`route`,`nature`,`owner_dept`) VALUES (?,?,?)");
    $st->bind_param('sss', $route, $cur['nat'], $cur['own']);
    $st->execute(); $st->close();

    $st = $conn->prepare("UPDATE `nav_canonical` SET `nature`=?, `owner_dept`=? WHERE `id`=?");
    $st->bind_param('ssi', $NATURE, $OWNER, $cur['id']);
    $st->execute(); $st->close();
    $n++;
    printf("   ✔ %-42s «%s» · مالكُه «%s»%s\n", $route, $NATURE, $OWNER,
        $cur['own'] !== '' && $cur['own'] !== $OWNER ? "  (كان «{$cur['own']}»)" : '');
    printf("      %s\n", $why);
}
printf("\n③ الحصيلة: %d إسقاطًا موسومًا\n", $n);

ems_migration_recorded(__FILE__, $conn, 0);
echo "✔ اكتمل\n";
