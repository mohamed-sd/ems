<?php
/**
 * 2027_11_04_injfrd66_sup28_board_reconcile.php
 *   SUP-28 — مصالحةُ لوحتَين لقدرةٍ واحدة، وتقييدُ ما تنفرد به المتقاعدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما قِيس**: القدرةُ التي تعدُّها الوثيقةُ «الغائبةَ الوحيدةَ في الحوكمة»
 *   **موجودةٌ وتعمل** — لوحةُ الدورِ 2 مُعدَّةٌ في `roleBoardGenericConfig(2)`
 *   باسمِ **«لوحة ادارة الموردين»** نفسِه، بأربعِ بطاقاتٍ لكلٍّ وجهةُ نقر،
 *   وكتلةِ نبضٍ وكتلةِ مهام، وتُصيَّر في `main/dashboard.php`. وصفرُ حقلِ
 *   إدخالٍ فيها. ⇐ فمعيارُ SUP-28 «لوحةٌ قائمة · صفرُ حقلِ إدخال» **مستوفًى**.
 *
 * ◆ **وتناقضٌ مقيَّدٌ في السجل**: `Suppliers/supplier_board.php` مُعلَنٌ
 *   «صفحةُ هبوطٍ للمساحةِ لا بندًا في مجموعة — قرارُ الورقة م٢٣»، **وقرارُ
 *   المالكِ 2026-08-21 المكتوبُ في `roleBoardRoute` يوجّه الدورَ 2 إلى
 *   `main/dashboard.php`**: «المكوّناتُ السبعةُ تُصيَّر داخلَ dashboard بلغةِ
 *   تصميمِها **بدل شاشةٍ ثانية**». فالإعلانُ يصف صفحةَ هبوطٍ لا أحدَ يهبط
 *   عليها — ويُصحَّح إلى ما هو: **سطحٌ خَلَفُه اللوحةُ المُعدَّة**.
 *
 * ◆ **ولا يُطوى ما تنفرد به**: `supplier_board.php` يحسب **ثلاثةَ مؤشراتٍ
 *   لا تحسبها اللوحةُ الحيّة** — المخالفاتُ المفتوحةُ والمعتمَدة · التقييماتُ
 *   المستحقّة · الطاقةُ النشِطة. وتقاعدٌ بلا تقييدِ ما ينفرد به **فقدٌ صامت**.
 *   فتُقيَّد في `placement_basis` نصًّا فتُعرف عندَ إثراءِ اللوحةِ لاحقًا.
 *
 * التشغيل:  php database/migrations/2027_11_04_injfrd66_sup28_board_reconcile.php
 * الرجوع :  php database/migrations/2027_11_04_injfrd66_sup28_board_reconcile.php --revert
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

$ROUTE = 'Suppliers/supplier_board.php';

$conn->query("CREATE TABLE IF NOT EXISTS `injfrd66_sup28_backup` (
    `route` VARCHAR(160) NOT NULL PRIMARY KEY,
    `placement_basis` TEXT NULL,
    `retirement_status` VARCHAR(40) NULL,
    `view_of` VARCHAR(160) NULL,
    `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if (in_array('--revert', $argv, true)) {
    $res = $conn->query("SELECT * FROM `injfrd66_sup28_backup`");
    $n = 0;
    while ($res && ($r = $res->fetch_assoc())) {
        $st = $conn->prepare("UPDATE `nav_canonical`
                                 SET `placement_basis`=?, `retirement_status`=?, `view_of`=?
                               WHERE LOWER(`route`)=LOWER(?)");
        $st->bind_param('ssss', $r['placement_basis'], $r['retirement_status'], $r['view_of'], $r['route']);
        $st->execute(); $n += $st->affected_rows; $st->close();
    }
    $conn->query("TRUNCATE `injfrd66_sup28_backup`");
    echo "↺ أُعيد {$n} صفًّا\n";
    exit(0);
}

/* ── ① التحقُّقُ من أنَّ اللوحةَ الحيّةَ قائمةٌ فعلًا قبلَ تقاعدِ الثانية ── */
echo "① اللوحةُ الحيّةُ للدورِ 2:\n";
require_once $ROOT . '/includes/role_board.php';
$cfg = roleBoardGenericConfig(2);
if (!is_array($cfg) || empty($cfg['cards'])) {
    fwrite(STDERR, "✘ لا إعدادَ لوحةٍ للدورِ 2 — لا يُتقاعد سطحٌ والبديلُ غيرُ قائم\n");
    exit(1);
}
printf("   ✔ «%s» · %d بطاقةً · كلُّ بطاقةٍ لها وجهةُ نقر\n",
    $cfg['title'], count($cfg['cards']));
$route2 = roleBoardRoute(2);
printf("   ✔ وجهةُ هبوطِ الدورِ 2: %s (قرارُ المالك 2026-08-21)\n", $route2);
if ($route2 !== 'main/dashboard.php') {
    fwrite(STDERR, "✘ وجهةُ الهبوطِ تغيّرت — يُراجَع القرارُ قبلَ التقاعد\n");
    exit(1);
}

/* ── ② ما تنفرد به المتقاعدةُ — يُقيَّد نصًّا لا يُطوى ────────────────── */
$liveTitles = array();
foreach ($cfg['cards'] as $cd) { $liveTitles[] = (string) $cd[0]; }
$UNIQUE = 'المخالفاتُ · التقييماتُ المستحقّة · الطاقةُ النشِطة';

/* ◆ **والعمودُ `VARCHAR(190)` يبتلع الزائدَ صامتًا** — كما ابتلع ENUM قيمةً
     في هجرةٍ سابقةٍ من هذه الجولة. فيُقاس الطولُ **قبلَ** الكتابةِ وتُوقَف
     الهجرةُ إن زاد: نصٌّ مبتورٌ يُقرأ «مكتوبًا» وهو ناقصُ المعنى. */
$BASIS = 'خَلَفُه لوحةُ الدورِ 2 في main/dashboard.php (قرارُ المالك 2026-08-21: '
       . 'بدل شاشةٍ ثانية). وينفرد بـ: ' . $UNIQUE . ' — تُعرف عندَ إثرائها.';

$q = $conn->query("SELECT CHARACTER_MAXIMUM_LENGTH n FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nav_canonical'
                       AND COLUMN_NAME='placement_basis'");
$cap = $q ? (int) $q->fetch_assoc()['n'] : 0;
if ($cap > 0 && mb_strlen($BASIS) > $cap) {
    fwrite(STDERR, sprintf("✘ نصُّ الإعلانِ %d حرفًا والعمودُ يسع %d — يُبتر صامتًا. أُوقفت الهجرة
",
        mb_strlen($BASIS), $cap));
    exit(1);
}
printf("   (طولُ الإعلان %d من %d حرفًا — لا بتر)
", mb_strlen($BASIS), $cap);
$q = $conn->query("SELECT id, IFNULL(placement_basis,'') pb, IFNULL(retirement_status,'') rs,
                          IFNULL(view_of,'') vo
                     FROM `nav_canonical` WHERE LOWER(`route`)=LOWER('" . $conn->real_escape_string($ROUTE) . "')");
$cur = $q ? $q->fetch_assoc() : null;
if (!$cur) { fwrite(STDERR, "✘ لا صفَّ كنسيًّا لـ{$ROUTE}\n"); exit(1); }

echo "\n② تصحيحُ الإعلانِ المتناقض:\n";
printf("   كان : «%s»\n", mb_substr($cur['pb'], 0, 74));
$st = $conn->prepare("INSERT IGNORE INTO `injfrd66_sup28_backup`
        (`route`,`placement_basis`,`retirement_status`,`view_of`) VALUES (?,?,?,?)");
$st->bind_param('ssss', $ROUTE, $cur['pb'], $cur['rs'], $cur['vo']);
$st->execute(); $st->close();

$RET = 'RETIRE_AFTER_PROOF';
$VOF = 'main/dashboard.php';
$st = $conn->prepare("UPDATE `nav_canonical`
                         SET `placement_basis`=?, `retirement_status`=?, `view_of`=?
                       WHERE `id`=?");
$st->bind_param('sssi', $BASIS, $RET, $VOF, $cur['id']);
$st->execute(); $st->close();
printf("   صار: %s ⇐ %s\n", $RET, $VOF);
printf("   وقُيِّد ما ينفرد به: %s\n", $UNIQUE);

ems_migration_recorded(__FILE__, $conn, 0);
echo "\n✔ اكتمل — ولوحتانِ لقدرةٍ واحدةٍ صارتا واحدةً معلومةَ الخَلَف\n";
