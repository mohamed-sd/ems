<?php
/**
 * 2027_10_29_injfrd66_sup01_supplier_codes.php
 *   SUP-01 — «كودُ موردٍ فريد»: تسعةُ مورّدينَ بلا كودٍ إطلاقًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما قِيس**: `COUNT(*) - COUNT(DISTINCT supplier_code) = 9` — وقراءتُه
 *   الأولى «تسعةُ أكوادٍ متصادمة»، **وهي خطأ**: `COUNT(DISTINCT)` **يُسقط
 *   NULL**، فالتسعةُ ليست تصادمًا بل **تسعةُ موردينَ بلا كودٍ البتة**.
 *   وصفرُ تصادمٍ حقيقيٍّ في السبعينَ الباقين.
 *   ⇐ والفرقُ جوهريّ: التصادمُ يُحلُّ بإعادةِ ترقيمٍ تمسُّ معرّفاتٍ يعرفها
 *     المستخدمون؛ والفراغُ يُملأ بلا مساسٍ بأحد.
 *
 * ◆ **وصيغةُ الكودِ مضطربةٌ حيًّا — ولا تُصلَح هنا**: قِيست ثلاثُ صيغٍ
 *   متعايشة (`MOR001` · `MOR1` · `MOR-0005`). وتوحيدُها **يغيّر معرّفاتٍ
 *   قائمةً يعرفها الناسُ ويطبعونها في مستنداتِهم** — فذاك قرارُ مالكٍ
 *   يُحجَز، لا أثرٌ جانبيٌّ لملءِ فراغ. **فتُملأ الفراغاتُ بالصيغةِ السائدة
 *   (`MOR###`) ولا يُمسُّ كودٌ قائم.**
 *
 * ◆ **والتسلسلُ يُقرأ بالقيمةِ لا بالنصّ**: `MOR1` و`MOR001` نصّانِ مختلفانِ
 *   وقيمتُهما واحدة. فيُستخرج الرقمُ من كلِّ صيغةٍ ويُؤخذ أكبرُها، وإلا
 *   وُلِّد `MOR007` وفي القاعدةِ `MOR7` — تصادمُ معنًى لا يراه فحصُ النصّ.
 *   (وهو نظيرُ عطبِ «المُرقِّم يقتطع بالموضع» المقيَّدِ في ذاكرةِ المستودع.)
 *
 * التشغيل:  php database/migrations/2027_10_29_injfrd66_sup01_supplier_codes.php
 * الرجوع :  php database/migrations/2027_10_29_injfrd66_sup01_supplier_codes.php --revert
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

$conn->query("CREATE TABLE IF NOT EXISTS `injfrd66_sup01_backup` (
    `supplier_id` INT NOT NULL PRIMARY KEY,
    `assigned_code` VARCHAR(60) NOT NULL,
    `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if (in_array('--revert', $argv, true)) {
    $n = 0;
    $res = $conn->query("SELECT * FROM `injfrd66_sup01_backup`");
    while ($res && ($r = $res->fetch_assoc())) {
        /* لا يُفرَّغ إلا ما وَلَّدَته هذه الهجرةُ بعينِه — فلو غُيِّر يدويًّا بعدَها تُرك */
        $st = $conn->prepare("UPDATE `suppliers` SET `supplier_code` = NULL
                               WHERE `id` = ? AND `supplier_code` = ?");
        $st->bind_param('is', $r['supplier_id'], $r['assigned_code']);
        $st->execute(); $n += $st->affected_rows; $st->close();
    }
    $conn->query("TRUNCATE `injfrd66_sup01_backup`");
    echo "↺ أُفرِغ {$n} كودًا مولَّدًا\n";
    exit(0);
}

/* ── ① أكبرُ رقمٍ مستعمَلٍ بالقيمةِ عبرَ الصيغِ كلِّها ──────────────────── */
$max = 0; $forms = array();
$res = $conn->query("SELECT supplier_code FROM `suppliers`
                      WHERE supplier_code IS NOT NULL AND supplier_code <> ''");
while ($res && ($r = $res->fetch_assoc())) {
    $code = (string) $r['supplier_code'];
    if (preg_match('/^([A-Za-z]+)[^0-9]*([0-9]+)$/', $code, $m)) {
        $forms[$m[1] . (strlen($m[2]) > 1 ? '###' : '#')] = true;
        $n = (int) $m[2];
        if ($n > $max) { $max = $n; }
    } else {
        $forms['?'] = true;
    }
}
printf("① صيغٌ حيّةٌ مقيسة: %s · أكبرُ رقمٍ بالقيمة: %d\n", implode(' · ', array_keys($forms)), $max);

/* ── ② الفراغاتُ ─────────────────────────────────────────────────────── */
$gaps = array();
$res = $conn->query("SELECT id, name FROM `suppliers`
                      WHERE is_deleted = 0 AND (supplier_code IS NULL OR supplier_code = '')
                      ORDER BY id");
while ($res && ($r = $res->fetch_assoc())) { $gaps[] = $r; }
printf("② موردونَ بلا كود: %d\n", count($gaps));
if (!$gaps) { echo "   ✔ لا فراغَ — لا عمل\n"; ems_migration_recorded(__FILE__, $conn, 0); exit(0); }

/* ── ③ التوليدُ مع فحصِ التصادمِ بالقيمةِ لا بالنصّ ────────────────────── */
$usedVals = array();
$res = $conn->query("SELECT supplier_code FROM `suppliers` WHERE supplier_code IS NOT NULL AND supplier_code <> ''");
while ($res && ($r = $res->fetch_assoc())) {
    if (preg_match('/^([A-Za-z]+)[^0-9]*([0-9]+)$/', (string) $r['supplier_code'], $m)) {
        $usedVals[strtoupper($m[1]) . ':' . (int) $m[2]] = true;
    }
}

$done = 0;
foreach ($gaps as $g) {
    do { $max++; $key = 'MOR:' . $max; } while (isset($usedVals[$key]));
    $usedVals[$key] = true;
    $code = sprintf('MOR%03d', $max);

    $st = $conn->prepare("UPDATE `suppliers` SET `supplier_code` = ? WHERE `id` = ?
                            AND (supplier_code IS NULL OR supplier_code = '')");
    $st->bind_param('si', $code, $g['id']);
    $st->execute();
    if ($st->affected_rows > 0) {
        $done++;
        $s2 = $conn->prepare("INSERT IGNORE INTO `injfrd66_sup01_backup` (`supplier_id`,`assigned_code`) VALUES (?,?)");
        $s2->bind_param('is', $g['id'], $code); $s2->execute(); $s2->close();
        printf("   ✔ #%-5d %-34s ← %s\n", $g['id'], mb_substr($g['name'], 0, 32), $code);
    }
    $st->close();
}

printf("\n③ الحصيلة: %d كودًا مولَّدًا\n", $done);

/* ── ④ إعادةُ القياسِ بعدَ العمل ──────────────────────────────────────── */
$q = $conn->query("SELECT COUNT(*) t,
                          SUM(supplier_code IS NULL OR supplier_code = '') empty_code,
                          COUNT(*) - COUNT(DISTINCT supplier_code) gap
                     FROM `suppliers` WHERE is_deleted = 0");
$x = $q->fetch_assoc();
printf("④ بعدَ العمل: %d موردًا · بلا كود %d · فجوةُ DISTINCT %d\n", $x['t'], $x['empty_code'], $x['gap']);

ems_migration_recorded(__FILE__, $conn, 0);
echo "✔ اكتمل — والصيغةُ الموحَّدةُ للأكوادِ القائمةِ محجوزةٌ لقرارِ مالك\n";
