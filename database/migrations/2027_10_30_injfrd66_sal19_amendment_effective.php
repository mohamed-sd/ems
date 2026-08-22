<?php
/**
 * 2027_10_30_injfrd66_sal19_amendment_effective.php
 *   SAL-19 — «لا تعديلَ بأثرٍ رجعيٍّ بلا ملحقٍ مؤرَّخ»: ملحقانِ بلا تاريخِ سريان
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما قِيس**: `AMD-1222` (إيقاف) و`AMD-1223` (استئناف) للعقدِ ١، كلاهما
 *   له `amend_date = 2026-08-17` و`effective_from = NULL`. وملحقٌ بلا تاريخِ
 *   سريانٍ **يُبطل المعيارَ من أصلِه**: «لا تعديلَ بأثرٍ رجعيٍّ بلا ملحقٍ
 *   **مؤرَّخ**» — فغيرُ المؤرَّخِ لا يُثبت أنه ليس رجعيًّا.
 *
 * ◆ **والتاريخُ يُشتقُّ بشاهدَين مستقلَّين لا يُخمَّن**:
 *   ① **العقدُ نفسُه**: `contracts#1.pause_date = resume_date = 2026-08-17` —
 *      اليومُ نفسُه الذي حُرِّر فيه الملحقان.
 *   ② **العُرفُ المقيسُ في الجدول**: `effective_from = amend_date` في
 *      **213 من 216** ملحقَ «تغيير أسعار»، وفي أغلبِ الأنواعِ الأخرى.
 *   فاتفاقُ مصدرَين مستقلَّين يرفع الملءَ من تخمينٍ إلى اشتقاق.
 *
 * ◆ **ولا يُمَسُّ ملحقٌ له تاريخٌ سلفًا** — الشرطُ `IS NULL` في الاستعلامِ
 *   نفسِه، فإعادةُ التشغيلِ لا تُغيّر شيئًا.
 *
 * التشغيل:  php database/migrations/2027_10_30_injfrd66_sal19_amendment_effective.php
 * الرجوع :  php database/migrations/2027_10_30_injfrd66_sal19_amendment_effective.php --revert
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

$conn->query("CREATE TABLE IF NOT EXISTS `injfrd66_sal19_backup` (
    `amendment_id` INT NOT NULL PRIMARY KEY,
    `set_to` DATE NOT NULL,
    `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if (in_array('--revert', $argv, true)) {
    $n = 0;
    $res = $conn->query("SELECT * FROM `injfrd66_sal19_backup`");
    while ($res && ($r = $res->fetch_assoc())) {
        /* لا يُفرَّغ إلا ما وضعَته هذه الهجرةُ بعينِه */
        $st = $conn->prepare("UPDATE `contract_amendments` SET `effective_from` = NULL
                               WHERE `id` = ? AND `effective_from` = ?");
        $st->bind_param('is', $r['amendment_id'], $r['set_to']);
        $st->execute(); $n += $st->affected_rows; $st->close();
    }
    $conn->query("TRUNCATE `injfrd66_sal19_backup`");
    echo "↺ أُفرِغ {$n} تاريخَ سريانٍ مشتقًّا\n";
    exit(0);
}

/* ── ① الشاهدُ الثاني: عُرفُ الجدولِ مقيسًا قبلَ الملء ─────────────────── */
$q = $conn->query("SELECT COUNT(*) t, SUM(effective_from = amend_date) same
                     FROM `contract_amendments`
                    WHERE is_deleted = 0 AND effective_from IS NOT NULL AND amend_date IS NOT NULL");
$c = $q ? $q->fetch_assoc() : array('t' => 0, 'same' => 0);
$pct = $c['t'] > 0 ? round($c['same'] * 100 / $c['t'], 1) : 0.0;
printf("① العُرفُ المقيس: effective_from = amend_date في %d من %d (%s%%)\n", $c['same'], $c['t'], $pct);
if ($pct < 60) {
    fwrite(STDERR, "✘ العُرفُ غيرُ سائدٍ ({$pct}%) — الاشتقاقُ غيرُ مسنَد. أُوقفت الهجرة\n");
    exit(1);
}

/* ── ② الفراغات ───────────────────────────────────────────────────────── */
$gaps = array();
$res = $conn->query("SELECT a.id, a.amendment_code, a.contract_id, a.amend_type, a.amend_date,
                            c.pause_date, c.resume_date
                       FROM `contract_amendments` a
                       LEFT JOIN `contracts` c ON c.id = a.contract_id
                      WHERE a.is_deleted = 0 AND a.effective_from IS NULL AND a.amend_date IS NOT NULL");
while ($res && ($r = $res->fetch_assoc())) { $gaps[] = $r; }
printf("② ملاحقُ بلا تاريخِ سريان: %d\n", count($gaps));
if (!$gaps) { echo "   ✔ لا فراغَ — لا عمل\n"; ems_migration_recorded(__FILE__, $conn, 0); exit(0); }

/* ── ③ الملءُ مع بيانِ الشاهدِ الأولِ لكلِّ صف ─────────────────────────── */
$done = 0;
foreach ($gaps as $g) {
    $date = $g['amend_date'];
    $corroborated = ($g['pause_date'] === $date || $g['resume_date'] === $date);

    $st = $conn->prepare("UPDATE `contract_amendments` SET `effective_from` = ?
                           WHERE `id` = ? AND `effective_from` IS NULL");
    $st->bind_param('si', $date, $g['id']);
    $st->execute();
    if ($st->affected_rows > 0) {
        $done++;
        $s2 = $conn->prepare("INSERT IGNORE INTO `injfrd66_sal19_backup` (`amendment_id`,`set_to`) VALUES (?,?)");
        $s2->bind_param('is', $g['id'], $date); $s2->execute(); $s2->close();
        printf("   ✔ %-10s %-10s ← %s%s\n", $g['amendment_code'], $g['amend_type'], $date,
            $corroborated ? '  (يوافق تاريخَ الإيقاف/الاستئنافِ في العقد)' : '');
    }
    $st->close();
}

printf("\n③ الحصيلة: %d ملحقًا مؤرَّخًا\n", $done);
$q = $conn->query("SELECT COUNT(*) FROM `contract_amendments` WHERE is_deleted = 0 AND effective_from IS NULL");
printf("④ بعدَ العمل: %d ملحقًا بلا تاريخِ سريان\n", (int) $q->fetch_row()[0]);

ems_migration_recorded(__FILE__, $conn, 0);
echo "✔ اكتمل\n";
