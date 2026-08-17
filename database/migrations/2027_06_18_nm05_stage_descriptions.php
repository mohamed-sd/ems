<?php
/**
 * 2027_06_18_nm05_stage_descriptions.php
 * ═══════════════════════════════════════════════════════════════════════════
 * NM-05 (الوثيقة 70 §4-5 · TSP-0154): «ولكلِّ مجموعةٍ سطرُ شرحٍ واحدٌ يظهر
 * تحتَ اسمِها ويقول ماذا يُفعل فيها بلغةٍ بسيطة — فالمبتدئُ لا يسأل زميلَه».
 * عمودُ stage_desc + بذرُ شروحِ المراحلِ المنصوصةِ حرفًا (SAL 8 · SUP 8 · SIT 6)
 * على مراحلِها الحيةِ المطابقةِ سلفًا بالاسمِ الفعليِّ في stage_title.
 * ما لا نصَّ له في الوثيقةِ يبقى فارغًا — لا يُخترع شرح.
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
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

$exists = $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'link_groups' AND COLUMN_NAME = 'stage_desc'");
if ((int) $exists === 0) {
    $conn->query("ALTER TABLE `link_groups`
        ADD COLUMN `stage_desc` VARCHAR(200) NULL COMMENT 'NM-05: سطرُ شرحِ المرحلةِ من الوثيقةِ 70 §4-5 — لا يُخترع'");
    echo $conn->error ? "✗ العمود: {$conn->error}\n" : "✔ عمودُ stage_desc\n";
} else { echo "· العمودُ قائم\n"; }

/* الشروحُ المنصوصةُ حرفًا — role_id · stage_no · نصُّ الوثيقة */
$DESCS = array(
    // SAL — المبيعاتُ والعقود (الدور 12) — TSP-0156..0163
    array(12, 1, 'نسجّل العميلَ ومشروعَه وموقعَه قبلَ أيِّ عقد'),
    array(12, 2, 'الفرصةُ ثم العرضُ ثم قوائمُ الأسعار'),
    array(12, 3, 'العقدُ وأحكامُه وجزاءاتُه وآلياتُ تعديلِ سعره'),
    array(12, 4, 'كم وحدةً لكلِّ نوعِ آليةٍ وكم آليةً لكلِّ نوع'),
    array(12, 5, 'ما نُفِّذ فعلًا مقابلَ ما تعاقدنا عليه'),
    array(12, 6, 'المستخلصُ ثم الفاتورةُ ثم قبولُ العميل'),
    array(12, 7, 'الملاحقُ والتجديداتُ وسجلُّ حركةِ العقدِ ومراجعتُه'),
    array(12, 8, 'مخاطرُ المبيعاتِ وحوكمتُها'),
    // SUP — إدارةُ الموردين (الدور 2) — TSP-0164..0171
    array(2, 1, 'نسجّل الموردَ وبياناتِه وحسابَه البنكيَّ قبلَ أيِّ عقد'),
    array(2, 2, 'معداتُه وخطتُه وطاقتُه ومهلةُ إحلاله'),
    array(2, 3, 'العقدُ وقواعدُ التحميلِ والجزاءاتِ عليه'),
    array(2, 4, 'حصتُه من عقدِ العميلِ ثم توزيعُها على معداته'),
    array(2, 5, 'ما استهلكه من حصتِه وما نفّذه فعلًا'),
    array(2, 6, 'التسوياتُ والمستحقاتُ وكشفُ حسابِه والسلفيات'),
    array(2, 7, 'التقييمُ الدوريُّ وتصفيةُ إنهاءِ العقد'),
    array(2, 8, 'مخاطرُ الموردينَ وحوكمتُها'),
    // SIT — إدارةُ الموقع (الدور 6) — TSP-0172..0177
    array(6, 1, 'الورديةُ وساعاتُها ووقودُها ومشغّلُها'),
    array(6, 2, 'استعدادٌ أم تعطلٌ ومن كان السبب'),
    array(6, 3, 'اعتمادُ مديرِ الموقعِ ومحاسبِ الموقعِ معًا'),
    array(6, 4, 'المشترياتُ الميدانيةُ وتصفيتُها'),
    array(6, 5, 'مطابقةُ الوقودِ ومؤشراتُ الورديّات'),
    array(6, 6, 'ما ينتظر اعتمادي اليوم'),
);
$st = $conn->prepare("UPDATE link_groups SET stage_desc = ?
                       WHERE owner_role_id = ? AND stage_no = ? AND is_active = 1
                         AND (stage_desc IS NULL OR stage_desc = '')");
$n = 0;
foreach ($DESCS as $d) {
    list($rid, $sn, $txt) = $d;
    $st->bind_param('sii', $txt, $rid, $sn);
    $st->execute();
    $n += $conn->affected_rows;
}
$st->close();
printf("✔ مجموعاتٌ أخذت شرحَها المنصوص: %d (على 22 مرحلةً في ثلاثِ إدارات)\n", $n);
printf("· مراحلُ الإداراتِ الثلاثِ بلا شرحٍ بعدُ: %s — ما لا نصَّ له يبقى فارغًا معلَنًا\n",
    $one("SELECT COUNT(DISTINCT CONCAT(owner_role_id,'-',stage_no)) FROM link_groups
           WHERE owner_role_id IN (2,6,12) AND is_active=1 AND stage_no BETWEEN 1 AND 8
             AND (stage_desc IS NULL OR stage_desc='')"));
echo "✔ NM-05 مبذورةٌ من النصِّ الحاكمِ حرفًا\n";
