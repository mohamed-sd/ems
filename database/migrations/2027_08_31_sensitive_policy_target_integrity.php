<?php
/**
 * 2027_08_31_sensitive_policy_target_integrity.php
 *   سلامةُ هدفِ السياسةِ الحساسة · وتنقيةُ بذرٍ ظاهر — INJ-FIX-02 · NF-09 · NF-19
 *   (توسيعُ GAP-12 و GAP-09 المعتمدتَين — لا فجوةً جديدة)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ثلاثةُ أفعالٍ لا رابعَ لها، وكلُّها قابلٌ للرجوع:**
 *
 *   ① **كنسُ المستهلكِ المكرَّرِ داخلَ الخانة** — ٥٥ صفًّا في `gov_screen_cycle`
 *      تحمل الاسمَ نفسَه مرَّتين في `consumers`. يُبقى أولُ ظهورٍ ويُحذف تكرارُه،
 *      **والترتيبُ محفوظ**. (NF-19)
 *
 *   ② **حجرُ صفٍّ ملوَّثٍ في `gov_data_classes`** — الصفُّ ٦١ يحمل `code='GOV_-0'`
 *      و`title` **اسمَ شخص**، وهو `active=1` فيظهر في المنتقيات. وصفرُ صفٍّ في
 *      `gov_field_class` يشير إليه ⇒ حجرُه بلا فقد. (GAP-09)
 *
 *   ③ **إعلانُ سياساتٍ بلا هدف** — لا تُصلَح بالتخمين بل تُسجَّل في
 *      `gov_sensitive_policy_debt` ليراها الفاحصُ والمالك.
 *
 * ◆ **ولماذا لا تُعاد السياساتُ إلى أعمدتِها بالتخمين:**
 *   سبعُ سياساتٍ تشير إلى أعمدةٍ لا وجودَ لها — فهي **تبدو حمايةً ولا تحمي**.
 *   وأخطرُها `employees.salary`: العمودُ غيرُ موجود، **والعمودُ الحقيقيُّ
 *   `employees.monthly_salary` غيرُ محميٍّ بشيء** (المحميُّ الوحيدُ من `employees`
 *   هو `phone`). فتحويلُ السياسةِ إليه **يُشغّل إخفاءَ الأجورِ على نظامٍ حيّ** —
 *   وذلك تغييرُ وصولٍ يمرُّ ببروتوكولِ القلبِ لا بهجرةٍ صامتة. ⇒ **يُعلَن ولا يُنفَّذ.**
 *
 * ◆ ولا `INSERT IGNORE` ولا حذفَ مدمِّر: الحجرُ `active=0` والكنسُ يُبقي أولَ ظهور.
 *
 * التشغيل:  php database/migrations/2027_08_31_sensitive_policy_target_integrity.php
 * الرجوع :  php database/migrations/2027_08_31_sensitive_policy_target_integrity.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$REVERT = in_array('--revert', $argv, true);

/* ══ الرجوع ═══════════════════════════════════════════════════════════════ */
if ($REVERT) {
    /* ① يُعاد المكرَّرُ من نسخةِ الحجر */
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_cycle_consumers_backup'");
    if ($r && (int) $r->fetch_row()[0] > 0) {
        $q = $conn->query("SELECT cycle_id, consumers_before FROM `gov_cycle_consumers_backup`");
        $n = 0;
        while ($q && $x = $q->fetch_assoc()) {
            $st = $conn->prepare("UPDATE `gov_screen_cycle` SET `consumers` = ? WHERE `id` = ?");
            $st->bind_param('si', $x['consumers_before'], $x['cycle_id']);
            $st->execute(); $n += $st->affected_rows; $st->close();
        }
        echo "↺ أُعيد {$n} صفًّا من نسخةِ المستهلكين\n";
        $conn->query("DROP TABLE `gov_cycle_consumers_backup`");
    }
    /* ② يُرفع الحجرُ عن صفِّ التصنيف */
    $conn->query("UPDATE `gov_data_classes` SET `active` = 1 WHERE `code` = 'GOV_-0'");
    echo "↺ رُفع الحجرُ عن gov_data_classes.GOV_-0 ({$conn->affected_rows} صفًّا)\n";
    /* ③ يُسقَط سجلُّ الدَّين */
    $conn->query("DROP TABLE IF EXISTS `gov_sensitive_policy_debt`");
    echo "↺ أُسقط سجلُّ دَينِ السياسات\n";
    exit(0);
}

/* ══ ① كنسُ المستهلكِ المكرَّرِ داخلَ الخانة — NF-19 ═════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_cycle_consumers_backup` (
    `cycle_id` INT UNSIGNED NOT NULL PRIMARY KEY,
    `consumers_before` VARCHAR(255) NOT NULL,
    `swept_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$q = $conn->query("SELECT `id`, `consumers` FROM `gov_screen_cycle` WHERE COALESCE(`consumers`,'') <> ''");
$rows = array();
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }

$swept = 0;
foreach ($rows as $r) {
    $raw = (string) $r['consumers'];
    $parts = preg_split('/\s*·\s*/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
    $seen = array(); $keep = array();
    foreach ($parts as $x) {
        $k = preg_replace('/\s+/u', ' ', trim($x));
        if ($k === '' || isset($seen[$k])) { continue; }
        $seen[$k] = 1; $keep[] = $k;
    }
    $new = implode(' · ', $keep);
    if ($new === $raw) { continue; }

    $st = $conn->prepare("INSERT INTO `gov_cycle_consumers_backup` (`cycle_id`,`consumers_before`)
                          VALUES (?,?) ON DUPLICATE KEY UPDATE `consumers_before` = VALUES(`consumers_before`)");
    $st->bind_param('is', $r['id'], $raw);
    $st->execute(); $st->close();

    $st = $conn->prepare("UPDATE `gov_screen_cycle` SET `consumers` = ? WHERE `id` = ?");
    $st->bind_param('si', $new, $r['id']);
    if (!$st->execute()) { echo "✘ تعذّر تحديثُ الصفِّ {$r['id']}: {$st->error}\n"; }
    $swept += $st->affected_rows;
    $st->close();
}
echo "① كُنس المستهلكُ المكرَّر: {$swept} صفًّا (نسخةُ الرجوعِ في gov_cycle_consumers_backup)\n";

/* ══ ② حجرُ صفِّ التصنيفِ الملوَّث — GAP-09 ══════════════════════════════ */
$q = $conn->query("SELECT COUNT(*) FROM `gov_field_class` WHERE `dc_code` = 'GOV_-0'");
$refs = $q ? (int) $q->fetch_row()[0] : 0;
if ($refs > 0) {
    echo "② ✘ لم يُحجَر: {$refs} صفًّا في gov_field_class يشير إليه — الحجرُ يفقد مرجعًا\n";
} else {
    $conn->query("UPDATE `gov_data_classes` SET `active` = 0 WHERE `code` = 'GOV_-0' AND `active` = 1");
    echo "② حُجر صفُّ التصنيفِ الملوَّث GOV_-0: {$conn->affected_rows} صفًّا (صفرُ مرجع)\n";
}

/* ══ ③ إعلانُ سياساتٍ بلا هدف — NF-09 ════════════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_sensitive_policy_debt` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `source_register` VARCHAR(48)  NOT NULL COMMENT 'أيُّ سجلٍّ أعلن السياسة',
    `declared_target` VARCHAR(160) NOT NULL COMMENT 'الهدفُ كما كُتب',
    `target_state`    VARCHAR(32)  NOT NULL COMMENT 'NO_TABLE | NO_COLUMN',
    `real_column`     VARCHAR(160) NULL     COMMENT 'العمودُ الحقيقيُّ إن عُرف يقينًا',
    `real_column_protected` TINYINT(1) NOT NULL DEFAULT 0,
    `note`            VARCHAR(400) NOT NULL,
    `detected_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_target` (`source_register`,`declared_target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='INJ-FIX-02 NF-09 — سياساتٌ تُعلن حمايةً لهدفٍ لا وجودَ له'");

function col_exists($conn, $t, $f)
{
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE()
                          AND TABLE_NAME='" . $conn->real_escape_string($t) . "'
                          AND COLUMN_NAME='" . $conn->real_escape_string($f) . "'");
    return $r && (int) $r->fetch_row()[0] > 0;
}
function tbl_exists($conn, $t)
{
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $conn->real_escape_string($t) . "'");
    return $r && (int) $r->fetch_row()[0] > 0;
}
/* أمحميٌّ هذا العمودُ في أيِّ سجلِّ حساسية؟ */
function is_protected($conn, $t, $f)
{
    $st = $conn->prepare("SELECT COUNT(*) FROM `scr_sensitive_fields` WHERE `table_name`=? AND `field_name`=?");
    $st->bind_param('ss', $t, $f); $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0]; $st->close();
    if ($n > 0) { return true; }
    $code = $t . '.' . $f;
    $st = $conn->prepare("SELECT COUNT(*) FROM `sensitive_field_policies` WHERE `field_code`=?");
    $st->bind_param('s', $code); $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0]; $st->close();
    return $n > 0;
}

/* العمودُ الحقيقيُّ لا يُخمَّن — لا يُكتب إلا حيثُ يكون واحدًا لا ثانيَ له */
$KNOWN_REAL = array('employees.salary' => 'employees.monthly_salary');

$targets = array();
$q = $conn->query("SELECT `field_code` FROM `sensitive_field_policies`");
while ($q && $x = $q->fetch_row()) { $targets[] = array('sensitive_field_policies', (string) $x[0]); }
$q = $conn->query("SELECT `table_name`, `field_name` FROM `scr_sensitive_fields`");
while ($q && $x = $q->fetch_assoc()) { $targets[] = array('scr_sensitive_fields', $x['table_name'] . '.' . $x['field_name']); }

$debt = 0;
foreach ($targets as $t) {
    list($src, $code) = $t;
    if (strpos($code, '.') === false) { continue; }
    list($tab, $fld) = explode('.', $code, 2);
    if (col_exists($conn, $tab, $fld)) { continue; }

    $state = tbl_exists($conn, $tab) ? 'NO_COLUMN' : 'NO_TABLE';
    $real  = isset($KNOWN_REAL[$code]) ? $KNOWN_REAL[$code] : null;
    $prot  = 0; $note = 'هدفٌ لا وجودَ له — السياسةُ تبدو حمايةً ولا تحمي شيئًا';
    if ($real !== null) {
        list($rt, $rf) = explode('.', $real, 2);
        $prot = is_protected($conn, $rt, $rf) ? 1 : 0;
        $note = $prot
            ? "العمودُ الحقيقيُّ {$real} محميٌّ بسجلٍّ آخر — التصحيحُ تحريريٌّ"
            : "◆ العمودُ الحقيقيُّ {$real} **غيرُ محميٍّ بشيء** — وتحويلُ السياسةِ إليه تغييرُ وصولٍ حيٍّ يمرُّ ببروتوكولِ القلبِ لا بهجرة";
    }
    $st = $conn->prepare("INSERT INTO `gov_sensitive_policy_debt`
            (`source_register`,`declared_target`,`target_state`,`real_column`,`real_column_protected`,`note`)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE `target_state`=VALUES(`target_state`), `real_column`=VALUES(`real_column`),
                                    `real_column_protected`=VALUES(`real_column_protected`), `note`=VALUES(`note`)");
    $st->bind_param('ssssis', $src, $code, $state, $real, $prot, $note);
    if (!$st->execute()) { echo "✘ تعذّر تسجيلُ {$code}: {$st->error}\n"; }
    $st->close();
    $debt++;
}
echo "③ سُجِّل دَينُ السياسات: {$debt} سياسةً بهدفٍ لا وجودَ له\n";

echo "───────────────────────────────────────────────────────────────\n";
$q = $conn->query("SELECT `source_register`,`declared_target`,`target_state`,`real_column`,`real_column_protected`
                     FROM `gov_sensitive_policy_debt` ORDER BY `real_column` IS NULL, `declared_target`");
while ($q && $x = $q->fetch_assoc()) {
    printf("  %-26s %-14s %s\n", $x['declared_target'], $x['target_state'],
        $x['real_column'] ? ('⇒ ' . $x['real_column'] . ($x['real_column_protected'] ? ' (محميّ)' : ' **غيرُ محميّ**')) : '');
}
echo "◆ ولا يُحوَّل هدفٌ بالتخمين — والتحويلُ المعلومُ يقينًا يمرُّ بقرارِ مالكٍ لأنه تغييرُ وصول.\n";
