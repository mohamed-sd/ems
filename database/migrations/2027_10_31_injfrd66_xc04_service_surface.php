<?php
/**
 * 2027_10_31_injfrd66_xc04_service_surface.php
 *   XC-04 — إعلانُ السطحِ الخدميِّ الذي لا واجهةَ له
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «لكلِّ سطحٍ مبنيٍّ مدخلٌ من القائمةِ أو تبويبٌ في ملفِّ أبيه —
 *   **أو يُعلَن سطحًا خدميًّا لا واجهةَ له**». والمخرجُ الثالثُ ليس تحايلًا:
 *   سطحٌ لا يفتحه إنسانٌ **لا يُصنع له مدخلٌ إرضاءً لعدّاد** — يُعلَن ما هو.
 *
 * ◆ **وقِيس أنَّ أربعةَ أسطحٍ بلا مدخلٍ ولا إعلان**، وواحدٌ منها **يُعلن نفسَه
 *   بنفسِه**: `Contracts/cron_price_adjustment.php` — اسمُه `cron_` وتوثيقُه
 *   نصًّا «تُقيَّم **دوريًّا**، وما تحرّك منها يولّد ملحقَ تغييرِ أسعارٍ
 *   بسريانه». فلا واجهةَ له ولا يُفتح بنقرة.
 *
 * ◆ **والثلاثةُ الباقيةُ لا تُعلَن هنا** — إعلانُ موضعِها قرارُ ورقةٍ لا
 *   استنتاجُ هجرة: `quota_approval_minutes.php` شاشةُ قراءةٍ لها جدولٌ حيٌّ
 *   يسندها، و`suppliers_details.php` و`showcontractsuppliers.php` شاشتا
 *   تفصيلٍ إرثيّتان. وتُترك مرصودةً في بوابةِ `injfrd66_xc04_gate.php` حتى
 *   يُحسم موضعُها — **ولا تُحذف** («لا حذفَ مسار»).
 *
 * التشغيل:  php database/migrations/2027_10_31_injfrd66_xc04_service_surface.php
 * الرجوع :  php database/migrations/2027_10_31_injfrd66_xc04_service_surface.php --revert
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

$ROUTE  = 'Contracts/cron_price_adjustment.php';
$NAME   = 'مراجعةُ شروطِ تعديلِ السعر';
$BASIS  = 'سطحٌ خدميٌّ لا واجهةَ له — مهمةٌ دوريةٌ تُقيّم شروطَ تعديلِ السعرِ '
        . 'وتولّد ملحقَ «تغيير أسعار» بسريانه (M-09 · CON-02 §2-③). '
        . 'لا يُفتح بنقرةٍ فلا يُصنع له مدخلٌ إرضاءً لعدّاد.';
$SOURCE = 'INJ-FRD-01 · XC-04 — المخرجُ الثالث: إعلانُ السطحِ الخدمي';

if (in_array('--revert', $argv, true)) {
    $st = $conn->prepare("DELETE FROM `nav_canonical`
                           WHERE `route` = ? AND `decision_source` = ?");
    $st->bind_param('ss', $ROUTE, $SOURCE);
    $st->execute();
    printf("↺ حُذف %d صفَّ إعلان\n", $st->affected_rows);
    $st->close();
    exit(0);
}

/* ── لا يُنشأ صفٌّ إن كان قائمًا — والهجرةُ تُعاد بلا أثرٍ ثانٍ ─────────── */
$q = $conn->query("SELECT id, IFNULL(placement_basis,'') pb FROM `nav_canonical`
                    WHERE LOWER(`route`) = LOWER('" . $conn->real_escape_string($ROUTE) . "')");
$cur = $q ? $q->fetch_assoc() : null;

if ($cur && $cur['pb'] !== '') {
    printf("○ مُعلَنٌ سلفًا — لا عمل\n   «%s»\n", mb_substr($cur['pb'], 0, 80));
    ems_migration_recorded(__FILE__, $conn, 0);
    exit(0);
}

if ($cur) {
    $st = $conn->prepare("UPDATE `nav_canonical`
                             SET `placement_basis` = ?, `decision_source` = ?
                           WHERE `id` = ?");
    $st->bind_param('ssi', $BASIS, $SOURCE, $cur['id']);
    $st->execute(); $st->close();
    echo "✔ أُضيف الإعلانُ إلى صفٍّ قائم\n";
} else {
    $st = $conn->prepare(
        "INSERT INTO `nav_canonical`
            (`route`,`canonical_ar`,`group_name`,`status`,`decision_state`,`application_state`,
             `placement_kind`,`placement_basis`,`decision_source`,`retirement_status`,`nature`)
         VALUES (?,?,'الحوكمة والضوابط','APPROVED','APPROVED','DEPLOYED',
                 'SERVICE',?,?,'ACTIVE','سطحٌ خدميّ')");
    $st->bind_param('ssss', $ROUTE, $NAME, $BASIS, $SOURCE);
    if (!$st->execute()) { fwrite(STDERR, "✘ تعذّر الإنشاء: {$conn->error}\n"); exit(1); }
    $st->close();
    echo "✔ أُنشئ صفُّ إعلانٍ للسطحِ الخدمي\n";
}
printf("   %s\n   «%s»\n", $ROUTE, mb_substr($BASIS, 0, 90));

ems_migration_recorded(__FILE__, $conn, 0);
