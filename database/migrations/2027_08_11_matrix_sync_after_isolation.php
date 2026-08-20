<?php
/**
 * 2027_08_11_matrix_sync_after_isolation.php — تحديثُ المصفوفة (آخرُ الترتيب)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الترتيبِ المُلزِم يختم بـ«**تحديثِ المصفوفةِ والبصمات**». وهذا شطرُه
 *   الأول: **المصفوفةُ تُطابق ما حُسم** — وإلا بقيَ الحكمُ في سجلٍّ جانبيٍّ
 *   والمصفوفةُ تقول غيرَه، **ومصدرانِ للحقيقةِ يعني لا مصدرَ لها**.
 *
 * ◆ **ثلاثةُ أشياءَ تُحدَّث — لا واحد**:
 *   ① **المِلكيةُ المصحَّحة** (4): `nav_canonical.owner_dept` يتبع الحكمَ في
 *      `gov_ownership_rulings`. **وصنفٌ تاسعٌ في المواصفةِ يقول ذلك حرفًا**:
 *      «`OWNERSHIP_ERROR` **لا تُنقل ولا تُخفى بل يُصحَّح مالكُها في المصفوفة**».
 *   ② **نقصُ الظهورِ فجوةً مسجَّلة** (42): شاشةٌ مِلكيتُها صحيحةٌ **وغائبةٌ عن
 *      مساحةِ مالكِها**. ولا تُزال ولا تُضاف تلقائيًّا — **إضافةُ 42 مدخلًا
 *      لسايدباراتٍ بلا قرارِ مالكٍ توسيعُ وصولٍ بلا طلب**. فتُسجَّل دَينًا باسمِها.
 *   ③ **صنفُ الظهورِ في المصفوفةِ** يتبع الشجرةَ — فمن يقرأ المصفوفةَ يرى
 *      الحكمَ نفسَه الذي يُنفِّذه الحارسُ لحظةَ التصيير.
 *
 * ◆ **ولا يُلمَس صفٌّ لم يُحسم**: الشرطُ على `gov_ownership_rulings` وحدَه،
 *   فما لم يمرَّ بالحسمِ يبقى كما هو.
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ تحديثُ المصفوفةِ بعدَ العزل ══\n";

/* ══ ① المِلكيةُ المصحَّحة ═══════════════════════════════════════════════ */
$before = array();
$r = $conn->query("SELECT n.route, n.owner_dept FROM nav_canonical n
                     JOIN gov_ownership_rulings g ON g.route = n.route
                    WHERE g.ruling = 'OWNER_CHANGED'");
while ($r && ($x = $r->fetch_assoc())) { $before[$x['route']] = $x['owner_dept']; }

$ok = $conn->query("UPDATE nav_canonical n
                      JOIN gov_ownership_rulings g ON g.route = n.route
                       SET n.owner_dept = g.owner_after
                     WHERE g.ruling = 'OWNER_CHANGED' AND n.owner_dept <> g.owner_after");
$changed = $ok ? $conn->affected_rows : -1;
echo "  ① مِلكيةٌ صُحِّحت في المصفوفة: {$changed}\n";
foreach ($before as $rt => $was) {
    $q = $conn->query("SELECT owner_dept FROM nav_canonical WHERE route = '"
        . $conn->real_escape_string($rt) . "' LIMIT 1");
    $now = $q ? (string) $q->fetch_row()[0] : '?';
    printf("      %-40s %s ← %s\n", $rt, mb_substr($was, 0, 18), mb_substr($now, 0, 22));
}

/* ══ ② نقصُ الظهورِ فجوةً مسجَّلةً — لا يُضاف مدخلٌ بلا قرارِك ══════════ */
if (!$conn->query("CREATE TABLE IF NOT EXISTS `gov_appearance_gaps` (
    `route`        VARCHAR(190) NOT NULL,
    `owner_dept`   VARCHAR(120) NOT NULL COMMENT 'المالكُ المؤكَّدُ بالشاهد',
    `seen_in`      VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المساحةُ الوحيدةُ التي تظهر فيها اليوم',
    `basis`        VARCHAR(400) NOT NULL DEFAULT '',
    `state`        ENUM('OPEN','OWNER_DECIDED','CLOSED') NOT NULL DEFAULT 'OPEN',
    `created_at`   DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='مِلكيةٌ صحيحةٌ وظهورٌ ناقصٌ في مساحةِ المالك — دَينٌ لا يُغلَق تلقائيًّا'")) {
    exit("✘ تعذّر إنشاءُ جدولِ الفجوات: {$conn->error}\n");
}

$ins = $conn->prepare("INSERT INTO gov_appearance_gaps (route, owner_dept, seen_in, basis)
    SELECT g.route, g.owner_after,
           COALESCE((SELECT MIN(a.space_ar) FROM gov_space_appearances a WHERE a.route = g.route), ''),
           LEFT(g.reason, 400)
      FROM gov_ownership_rulings g WHERE g.ruling = 'APPEARANCE_MISSING'
    ON DUPLICATE KEY UPDATE owner_dept = VALUES(owner_dept), basis = VALUES(basis)");
$gaps = 0;
if ($ins && $ins->execute()) { $gaps = $conn->affected_rows; }
$q = $conn->query("SELECT COUNT(*) FROM gov_appearance_gaps WHERE state = 'OPEN'");
$open = $q ? (int) $q->fetch_row()[0] : 0;
echo "  ② فجواتُ ظهورٍ مسجَّلة: {$gaps} · مفتوحةٌ إجمالًا: {$open}\n";
echo "      ◆ **ولا يُضاف مدخلٌ إلى سايدبارٍ تلقائيًّا** — إضافةُ 42 مدخلًا بلا\n";
echo "        قرارِ مالكٍ **توسيعُ وصولٍ بلا طلب**، وهي عكسُ الغرضِ من الجولة.\n";

/* ══ ③ صنفُ الظهورِ في المصفوفة — مصدرٌ واحدٌ للحقيقة ══════════════════ */
$hasCol = false;
$q = $conn->query("SELECT 1 FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nav_canonical'
                      AND COLUMN_NAME='space_class'");
if ($q && $q->num_rows > 0) { $hasCol = true; }
if (!$hasCol) {
    $conn->query("ALTER TABLE `nav_canonical`
        ADD `space_class` VARCHAR(32) NOT NULL DEFAULT ''
            COMMENT 'صنفُ الظهورِ الغالبُ بعدَ شجرةِ القرار — ليقرأ قارئُ المصفوفةِ حكمَ الحارس'");
}
$ok = $conn->query("UPDATE nav_canonical n
                      JOIN (SELECT route, cls, COUNT(*) c FROM gov_space_appearances
                             GROUP BY route, cls) a ON a.route = n.route
                      LEFT JOIN (SELECT route, cls, COUNT(*) c2 FROM gov_space_appearances
                                  GROUP BY route, cls) b
                             ON b.route = a.route AND b.c2 > a.c
                       SET n.space_class = a.cls
                     WHERE b.route IS NULL");
$cls = $ok ? $conn->affected_rows : -1;
echo "  ③ صنفُ الظهورِ الغالبُ كُتب في المصفوفة: {$cls} مسارًا\n";

/* ══ الشاهدُ: المصفوفةُ والحسمُ لا يختلفان ═════════════════════════════ */
$q = $conn->query("SELECT COUNT(*) FROM nav_canonical n
                     JOIN gov_ownership_rulings g ON g.route = n.route
                    WHERE g.ruling = 'OWNER_CHANGED' AND n.owner_dept <> g.owner_after");
$mismatch = $q ? (int) $q->fetch_row()[0] : -1;
echo "\n  ◆ **الشاهد**: صفوفٌ يخالف مالكُها في المصفوفةِ حكمَ الحسم: {$mismatch}\n";
echo ($mismatch === 0 ? "✔ المصفوفةُ والحسمُ مصدرٌ واحد\n" : "✘ اختلافٌ باقٍ\n");
exit($mismatch === 0 ? 0 : 1);
