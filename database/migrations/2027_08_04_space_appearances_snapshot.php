<?php
/**
 * 2027_08_04_space_appearances_snapshot.php — لقطةُ الحالِ · الخطوةُ الأولى (ثامنًا)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الترتيبُ المُلزِم يبدأ بـ**لقطةِ حالة**. وهذه هي: تُستورَد الـ887 موضعَ ظهورٍ
 *   من ورقةِ «عزل الإدارات — تصنيف الظهور» إلى القاعدةِ لتصيرَ **مقيسةً
 *   يُعاد قياسُها**، لا جدولًا في ملفٍّ يُقرأ بالعين.
 *
 * ◆ **ولماذا القاعدةُ لا الملف**: الخطواتُ التاليةُ كلُّها تُعيد التصنيفَ وتحسم
 *   المعلَّقَ وتقارن بالسايدبارِ الحيّ. **وما لا يُستعلَم عنه لا يُعاد قياسُه**،
 *   ومقارنةُ 887 صفًّا بالحيِّ بالعينِ خطأٌ مضمون.
 *
 * ◆ **والحقولُ الثلاثةُ محاورُ مستقلةٌ لا عمودٌ واحد** (نصُّ ٢٠-٣): صنفُ الظهورِ
 *   يصف مكانَه · وحالةُ المِلكيةِ صحةَ نسبتِه · وحالةُ القرارِ حسمَه. والشاشةُ
 *   قد تكون مرجعيةً مقيَّدةً **ومِلكيتُها مشكوكةً في آنٍ** — فجمعُها في عمودٍ
 *   يُفقد معلومة.
 *
 * ◆ **ويُحفظ الأصلُ كما ورد**: أعمدةُ `src_*` نسخةُ الدفترِ حرفًا، والأعمدةُ
 *   بلا بادئةٍ هي ما تُعيد الجولةُ حسابَه. **فما أُعيد تصنيفُه يُقارَن بأصلِه
 *   ولا يُدَّعى أنه كان كذلك** — وهذا شرطُ «لا رقمَ بلا مصدر».
 *
 * ◆ المقامُ المُعلَن: 887 موضعًا · 18 مساحةَ عمل. والدفترُ نفسُه يذكر مصالحةَ
 *   المقامِ مع القياسِ الأول (999 موضعًا · 19 مساحة): مساحةٌ متقاعدةٌ مؤرشَفةٌ
 *   واثنا عشرَ مدخلًا ثانيًا لمساراتٍ قائمة.
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

$conn->query("CREATE TABLE IF NOT EXISTS `gov_space_appearances` (
    `id`            INT(11) NOT NULL,
    `space_ar`      VARCHAR(80)  NOT NULL COMMENT 'مساحةُ العمل',
    `space_kind`    ENUM('DEPARTMENT','ROLE','CONTROL','EXECUTIVE','PERSONAL') NOT NULL,
    `tab_ar`        VARCHAR(120) NOT NULL DEFAULT '',
    `screen_ar`     VARCHAR(190) NOT NULL DEFAULT '',
    `route`         VARCHAR(190) NOT NULL,
    `owner_dept_ar` VARCHAR(120) NOT NULL DEFAULT '',
    `owner_kind`    ENUM('BUSINESS_DEPARTMENT','PLATFORM_SHARED') NOT NULL DEFAULT 'BUSINESS_DEPARTMENT',

    /* ── الأصلُ كما ورد في الدفترِ — لا يُكتب فوقَه ── */
    `src_class`     VARCHAR(32) NOT NULL DEFAULT '' COMMENT '① صنفُ الظهورِ كما ورد',
    `src_ownership` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '② حالةُ المِلكيةِ كما وردت',
    `src_decision`  VARCHAR(32) NOT NULL DEFAULT '' COMMENT '③ حالةُ القرارِ كما وردت',
    `src_note`      VARCHAR(255) NOT NULL DEFAULT '',
    `spaces_count`  SMALLINT NOT NULL DEFAULT 0 COMMENT 'في كم مساحةٍ يظهر هذا المسار',

    /* ── ما تُعيد الجولةُ حسابَه ── */
    `cls`           VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'صنفُ الظهورِ بعدَ إعادةِ التصنيف',
    `ownership`     VARCHAR(32) NOT NULL DEFAULT '',
    `decision`      VARCHAR(32) NOT NULL DEFAULT '',
    `basis`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'الشاهدُ الذي أوجبَ الحكمَ — لا رأيٌ',
    `rule_step`     TINYINT NOT NULL DEFAULT 0 COMMENT 'أيُّ عقدةٍ من شجرةِ القرارِ حسمَته (١..٦)',
    `view_fields`   VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'حقولُ المنظرِ المقيَّدِ إن كان',
    `updated_at`    DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `ix_gsa_space` (`space_ar`),
    KEY `ix_gsa_route` (`route`),
    KEY `ix_gsa_cls` (`cls`),
    KEY `ix_gsa_own` (`ownership`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ثامنًا-١ لقطةُ الحال — 887 موضعَ ظهورٍ بثلاثةِ محاورَ مستقلة'");

$tsv = $ROOT . '/docs/uxui/space_appearances_887.tsv';
if (!is_file($tsv)) { exit("✘ مفقود: {$tsv}\n"); }

$KINDS = array('DEPARTMENT' => 1, 'ROLE' => 1, 'CONTROL' => 1, 'EXECUTIVE' => 1, 'PERSONAL' => 1);
$ins = $conn->prepare(
    "INSERT INTO gov_space_appearances
        (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
         src_class, src_ownership, src_decision, src_note, spaces_count,
         cls, ownership, decision)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
        space_ar=VALUES(space_ar), space_kind=VALUES(space_kind), tab_ar=VALUES(tab_ar),
        screen_ar=VALUES(screen_ar), route=VALUES(route), owner_dept_ar=VALUES(owner_dept_ar),
        owner_kind=VALUES(owner_kind), src_class=VALUES(src_class),
        src_ownership=VALUES(src_ownership), src_decision=VALUES(src_decision),
        src_note=VALUES(src_note), spaces_count=VALUES(spaces_count)"
);
if (!$ins) { exit("تعذّر التحضير: {$conn->error}\n"); }

$fh = fopen($tsv, 'r');
$n = 0; $bad = 0;
while (($line = fgets($fh)) !== false) {
    $c = explode("\t", rtrim($line, "\r\n"));
    if (count($c) < 12 || !ctype_digit(trim($c[0]))) { $bad++; continue; }
    $id    = (int) $c[0];
    $space = trim($c[1]);
    $kind  = trim($c[2]);
    if (!isset($KINDS[$kind])) { $kind = 'DEPARTMENT'; }
    $tab   = trim($c[3]);
    $scr   = trim($c[4]);
    $route = trim($c[5]);
    $own   = trim($c[6]);
    $ok    = (trim($c[7]) === 'PLATFORM_SHARED') ? 'PLATFORM_SHARED' : 'BUSINESS_DEPARTMENT';
    $cls   = trim($c[8]);
    $ost   = trim($c[9]);
    $dec   = trim($c[10]);
    $note  = mb_substr(trim($c[11]), 0, 255);
    $cnt   = isset($c[12]) && ctype_digit(trim($c[12])) ? (int) $c[12] : 0;

    /* الأصلُ يُنسخ إلى عمودِ العملِ بدايةً — وكلُّ تغييرٍ بعدَه يُقارَن به */
    $ins->bind_param('isssssssssssisss',
        $id, $space, $kind, $tab, $scr, $route, $own, $ok,
        $cls, $ost, $dec, $note, $cnt, $cls, $ost, $dec);
    if ($ins->execute()) { $n++; } else { echo "  ✘ #{$id}: {$ins->error}\n"; $bad++; }
}
fclose($fh);
$ins->close();

echo "══ لقطةُ الحال — الخطوةُ الأولى ══\n";
echo "  استُورد: {$n}" . ($bad ? " · مرفوضٌ: {$bad}" : '') . "\n";

$q = $conn->query("SELECT COUNT(*) a, COUNT(DISTINCT space_ar) b, COUNT(DISTINCT route) c FROM gov_space_appearances");
$x = $q ? $q->fetch_assoc() : array('a' => 0, 'b' => 0, 'c' => 0);
echo "  المقام: {$x['a']} موضعًا · {$x['b']} مساحةً · {$x['c']} مسارًا فريدًا\n";

echo "\n  ┌ صنفُ الظهورِ كما ورد\n";
$q = $conn->query("SELECT src_class, COUNT(*) n FROM gov_space_appearances GROUP BY src_class ORDER BY n DESC");
while ($q && ($r = $q->fetch_assoc())) { printf("  │ %-24s %4d\n", $r['src_class'], $r['n']); }
echo "  ├ حالةُ المِلكية\n";
$q = $conn->query("SELECT src_ownership, COUNT(*) n FROM gov_space_appearances GROUP BY src_ownership ORDER BY n DESC");
while ($q && ($r = $q->fetch_assoc())) { printf("  │ %-24s %4d\n", $r['src_ownership'], $r['n']); }
echo "  ├ حالةُ القرار\n";
$q = $conn->query("SELECT src_decision, COUNT(*) n FROM gov_space_appearances GROUP BY src_decision ORDER BY n DESC");
while ($q && ($r = $q->fetch_assoc())) { printf("  │ %-24s %4d\n", $r['src_decision'], $r['n']); }
echo "  └───────────────────────────────\n";

echo "\n  ◆ الترتيبُ المُلزِمُ للإغلاقِ بأعلاها تسرّبًا:\n";
$q = $conn->query("SELECT space_ar, COUNT(*) n,
                          SUM(src_class='FORBIDDEN') f, SUM(src_decision='PENDING') p,
                          SUM(src_ownership='ERROR_SUSPECTED') e
                     FROM gov_space_appearances WHERE space_kind='DEPARTMENT'
                    GROUP BY space_ar ORDER BY f DESC, p DESC");
while ($q && ($r = $q->fetch_assoc())) {
    printf("    %-24s مواضع=%-4d ممنوع=%-3d معلَّق=%-4d مِلكيةٌ مشكوكة=%d\n",
           $r['space_ar'], $r['n'], $r['f'], $r['p'], $r['e']);
}
exit($n === 887 ? 0 : 1);
