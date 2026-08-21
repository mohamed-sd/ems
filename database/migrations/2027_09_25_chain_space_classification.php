<?php
/**
 * 2027_09_25_chain_space_classification.php
 *   تصنيفُ ظهورِ شاشاتِ السلسلةِ في المساحات — NF-24 · GAP-22
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مسارٌ نشطٌ في التنقّلِ بلا صفٍّ في سجلِّ التصنيف = مفتوحٌ افتراضًا**.
 *   وسقّاطةُ `injfix02_space_classification_ratchet` ترسُب على كلِّ زيادة —
 *   **وقد رسبت بسببِ شاشاتِ هذه الجولةِ السبع**. فالزيادةُ من صنعي، وعلاجُها
 *   تصنيفٌ لا شدُّ سقّاطة.
 *
 * ◆ **والتصنيفُ يُورَث من الأخت** كما وُرِث الموضعُ وبندُ القالب: الشاشةُ
 *   الجديدةُ من صنفِ أختِها ومالكِها نفسِه، فحكمُ ظهورِها في كلِّ مساحةٍ هو
 *   حكمُها. **ولا يُخترَع حكمُ مساحةٍ من عدم.**
 *
 * ◆ **وأثرُ ذلك حقيقيٌّ لا شكليّ**: شاشةُ الخزينةِ في مساحةِ إدارةٍ لا تملكها
 *   تُصنَّف `FORBIDDEN` — وهو الحكمُ الذي أثبته الحزامُ السلبيُّ حيًّا حين
 *   ضُيِّق نطاقُ القوالب.
 *
 * التشغيل:  php database/migrations/2027_09_25_chain_space_classification.php
 * الرجوع :  php database/migrations/2027_09_25_chain_space_classification.php --revert
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

/* route => [anchor, screen_ar] */
$MAP = array(
 'Finance/unit_fin_final.php'     => array('Finance/entitlement_gate.php', 'الاعتماد المالي النهائي'),
 'Finance/ar_accrual_gen.php'     => array('Finance/entitlement_gate.php', 'توليد استحقاقات عقد العميل'),
 'Finance/ar_completion_cert.php' => array('Finance/entitlement_gate.php', 'شهادة الإنجاز الشهرية'),
 'Finance/ar_claim_invoice.php'   => array('Finance/entitlement_gate.php', 'فاتورة المطالبة وإحالتها'),
 'Finance/tre_beneficiary.php'    => array('Finance/payments_fin.php',     'سجل المستفيدين والحسابات البنكية'),
 'Finance/tre_pay_batch.php'      => array('Finance/payments_fin.php',     'دفعات الدفع والتنفيذ'),
 'Operations/unit_correction.php' => array('Operations/unit_perf.php',     'تصحيح الوحدات بالسلسلة الثلاثية'),
);
$SRC = 'INJ-CHAIN-CLOSE-01';

if (in_array('--revert', $argv, true)) {
    $conn->query("DELETE FROM `gov_space_appearances` WHERE `src_note` = '{$SRC}'");
    echo "↺ حُذف {$conn->affected_rows} صفَّ تصنيفٍ من هذه الجولة\n";
    exit(0);
}

/* `gov_space_appearances.id` بلا AUTO_INCREMENT — يُحسب من الأقصى (گوتشا موثقة) */
$q = $conn->query("SELECT COALESCE(MAX(`id`),0) FROM `gov_space_appearances`");
$next = $q ? (int) $q->fetch_row()[0] : 0;

$sel = $conn->prepare(
  "SELECT `space_ar`,`space_kind`,`tab_ar`,`owner_dept_ar`,`owner_kind`,
          `cls`,`ownership`,`decision`,`basis`,`rule_step`,`spaces_count`
     FROM `gov_space_appearances` WHERE `route` = ?");
$chk = $conn->prepare(
  "SELECT COUNT(*) FROM `gov_space_appearances` WHERE `route` = ? AND `space_ar` = ?");
$ins = $conn->prepare(
  "INSERT INTO `gov_space_appearances`
     (`id`,`space_ar`,`space_kind`,`tab_ar`,`screen_ar`,`route`,`owner_dept_ar`,`owner_kind`,
      `src_class`,`src_ownership`,`src_decision`,`src_note`,`spaces_count`,
      `cls`,`ownership`,`decision`,`basis`,`rule_step`,`updated_at`)
   VALUES (?,?,?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, NOW())");

$made = 0; $skip = 0; $none = array();
foreach ($MAP as $route => $meta) {
    list($anchor, $screenAr) = $meta;
    $sel->bind_param('s', $anchor);
    $sel->execute();
    $res = $sel->get_result();
    $n = 0;
    while ($res && $r = $res->fetch_assoc()) {
        $chk->bind_param('ss', $route, $r['space_ar']);
        $chk->execute(); $chk->bind_result($have); $chk->fetch(); $chk->free_result();
        if ((int) $have > 0) { $skip++; $n++; continue; }
        $next++;
        $cls = (string) $r['cls']; $own = (string) $r['ownership']; $dec = (string) $r['decision'];
        $basis = mb_substr('مورَّثٌ من ' . basename($anchor) . ' — ' . (string) $r['basis'], 0, 255);
        $sc = (int) $r['spaces_count'];
        $rs = (int) $r['rule_step'];
        /* ◆ **ثمانيةَ عشرَ متغيرًا ⇐ ثمانيةَ عشرَ حرفًا**، وحرفٌ منزاحٌ واحدٌ
         *   يقسر `cls` عددًا فيصير «0» في خمسةٍ وعشرين صفًّا — والسقّاطةُ
         *   تخضرُّ لأن الصفَّ موجودٌ لا لأن الحكمَ صحيح. */
        $ins->bind_param('isssssssssssissssi',
            $next, $r['space_ar'], $r['space_kind'], $r['tab_ar'], $screenAr, $route,
            $r['owner_dept_ar'], $r['owner_kind'],
            $cls, $own, $dec, $SRC, $sc,
            $cls, $own, $dec, $basis, $rs);
        if ($ins->execute()) { $made++; $n++; }
        else { echo "  ✘ {$route} / {$r['space_ar']}: {$ins->error}\n"; }
    }
    if ($n === 0) { $none[] = $route . ' (أختُه ' . $anchor . ')'; }
    printf("  %-36s ظهوراتُ أختِه: %d\n", $route, $n);
}
$sel->close(); $chk->close(); $ins->close();

printf("① أُضيف %d صفَّ تصنيفٍ · مُكرَّرٌ سلفًا %d\n", $made, $skip);
if ($none) { echo "  ⚠ بلا تصنيفٍ مورَّث: " . implode(' · ', $none) . "\n"; }

$q = $conn->query("SELECT `cls`, COUNT(*) FROM `gov_space_appearances`
                    WHERE `src_note` = '{$SRC}' GROUP BY `cls` ORDER BY 2 DESC");
echo "② توزيعُ الحكمِ على ظهوراتِ هذه الجولة:\n";
while ($q && $r = $q->fetch_row()) { printf("   %-16s %s\n", $r[0], $r[1]); }
echo "   ◆ و`FORBIDDEN` ليست عيبًا: **شاشةُ الماليةِ في مساحةِ إدارةٍ لا تملكها**\n";
echo "     — وهو الحكمُ الذي أثبته الحزامُ السلبيُّ حيًّا.\n";

ems_migration_recorded(__FILE__, $conn, 0);
