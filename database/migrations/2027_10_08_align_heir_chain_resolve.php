<?php
/**
 * 2027_10_08_align_heir_chain_resolve.php
 *   التحويلُ يهبط على صفحةٍ حاضرة — لا على وارثٍ صار هو نفسُه تبويبًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ الذي كشفه الحزام**: ستةُ مساراتٍ لها تحويلٌ نشطٌ **قديمٌ** إلى
 *   وارثٍ صار في هذه الجولةِ تبويبًا مُخفًى. فسلسلةُ الميراثِ انقطعت:
 *   `contract_obligations` ⇐ `contract_coverage` — **وكلاهما مُخفًى الآن**.
 *   ⇒ **التحويلُ إلى صفحةٍ لا تُرى تحويلٌ إلى العدم.**
 *
 * ◆ **فيُتبَع الميراثُ حتى صفحةٍ حاضرة**: `contract_obligations` ⇐
 *   `contract_coverage` ⇐ **`Contracts/contracts.php`** — وهو ملفُّ العقدِ
 *   الأمُّ الذي صارت كلُّها تبويباتِه. والتحويلُ يُحدَّث لا يُضاعَف: صفٌّ ثانٍ
 *   لنفسِ المسارِ القديمِ يجعل الوجهةَ ملتبسة.
 *
 * ◆ **وحالتان لا وارثَ لهما بل حكمُ مساحة**:
 *   · `Timesheet/timesheet.php` في مساحةِ الموردين — «لا إدخالَ ساعةٍ أو طنٍّ
 *     داخلَ الموردين» بنصِّ الوثيقة.
 *   · `FinRequests/my_requests.php` في المساحتين — مكرَّرٌ لطبقةِ «المهامِّ
 *     والاعتماداتِ» المركزيةِ التي أعلنتها الوثيقتان بثلاثةِ بنودٍ لا أربعة.
 *   وكلاهما **إزالةٌ من مساحةٍ لا حذفٌ من النظام**.
 *
 * التشغيل:  php database/migrations/2027_10_08_align_heir_chain_resolve.php
 * الرجوع :  php database/migrations/2027_10_08_align_heir_chain_resolve.php --revert
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

$SRC = 'INJ-ALIGN-CHAIN';
/* old_route => [new_heir, before] — الأصلُ محفوظٌ في سجلٍّ للرجوع */
$REPOINT = array(
    'Contracts/collections.php'            => 'Contracts/claims.php',
    'Contracts/contract_obligations.php'   => 'Contracts/contracts.php',
    'Contracts/contract_monthly_plan.php'  => 'Contracts/contracts.php',
    'Contracts/contract_resource_plan.php' => 'Contracts/contracts.php',
);
/* route => [role, space, basis] */
$FORBID = array(
    array('Timesheet/timesheet.php', 2, 'ادارة الموردين',
          'لا إدخالَ ساعةٍ أو طنٍّ أو مترٍ داخلَ إدارةِ الموردين — الواقعةُ بيتُها التايم شيت وتُقرأ هنا لا تُدخَل'),
    array('FinRequests/my_requests.php', 12, 'ادارة المبيعات',
          'مكرَّرٌ لطبقةِ «المهامِّ والاعتمادات» المركزيةِ — والوثيقةُ تعلنها ثلاثةَ بنودٍ لا أربعة'),
    array('FinRequests/my_requests.php', 2, 'ادارة الموردين',
          'مكرَّرٌ لطبقةِ «المهامِّ والاعتمادات» المركزيةِ — والوثيقةُ تعلنها ثلاثةَ بنودٍ لا أربعة'),
);

$conn->query("CREATE TABLE IF NOT EXISTS `gov_redirect_repoint_log` (
  `old_route`    VARCHAR(128) NOT NULL PRIMARY KEY,
  `heir_before`  VARCHAR(128) NOT NULL,
  `heir_after`   VARCHAR(128) NOT NULL,
  `basis`        VARCHAR(255) NOT NULL,
  `at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='تحويلٌ أُعيد توجيهُه لأن وارثَه صار تبويبًا — بالأصلِ للرجوع'");

if (in_array('--revert', $argv, true)) {
    $q = $conn->query("SELECT `old_route`,`heir_before` FROM `gov_redirect_repoint_log`");
    $back = 0;
    while ($q && $r = $q->fetch_assoc()) {
        $st = $conn->prepare("UPDATE `nav_redirects` SET `new_route` = ? WHERE `old_route` = ?");
        $st->bind_param('ss', $r['heir_before'], $r['old_route']);
        $st->execute(); $back += $st->affected_rows; $st->close();
    }
    $conn->query("DROP TABLE IF EXISTS `gov_redirect_repoint_log`");
    $conn->query("DELETE FROM `gov_space_appearances` WHERE `src_note` = '{$SRC}'");
    echo "↺ أُعيد {$back} تحويلًا إلى وارثِه السابق · وحُذفت أحكامُ المساحةِ من هذه الجولة\n";
    exit(0);
}

/* ══ ① إعادةُ توجيهِ التحويلِ إلى صفحةٍ حاضرة ═════════════════════════════ */
$log = $conn->prepare("INSERT INTO `gov_redirect_repoint_log` (`old_route`,`heir_before`,`heir_after`,`basis`)
                       VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE `heir_after` = VALUES(`heir_after`)");
$upd = $conn->prepare("UPDATE `nav_redirects` SET `new_route` = ? WHERE `old_route` = ? AND `active` = 1");
$basis = 'الوارثُ السابقُ صار تبويبًا مُخفًى — فيُتبَع الميراثُ حتى صفحةٍ حاضرة';
$n = 0;
foreach ($REPOINT as $old => $heir) {
    $q = $conn->query("SELECT `new_route` FROM `nav_redirects`
                        WHERE `old_route` = '" . $conn->real_escape_string($old) . "' AND `active` = 1 LIMIT 1");
    $before = ($q && ($x = $q->fetch_row())) ? (string) $x[0] : '—';
    if ($before === $heir) { continue; }
    $log->bind_param('ssss', $old, $before, $heir, $basis);
    $log->execute();
    $upd->bind_param('ss', $heir, $old);
    $upd->execute();
    if ($upd->affected_rows) { $n++; printf("   %-40s %s ⇐ %s\n", $old, $before, $heir); }
}
$log->close(); $upd->close();
printf("① أُعيد توجيهُ %d تحويلٍ إلى صفحةٍ حاضرة\n", $n);

/* ══ ② حكمُ المساحةِ لما لا وارثَ له ═══════════════════════════════════ */
$q = $conn->query("SELECT COALESCE(MAX(`id`),0) FROM `gov_space_appearances`");
$next = $q ? (int) $q->fetch_row()[0] : 0;
$ins = $conn->prepare("INSERT INTO `gov_space_appearances`
      (`id`,`space_ar`,`space_kind`,`tab_ar`,`screen_ar`,`route`,`owner_dept_ar`,`owner_kind`,
       `src_class`,`src_ownership`,`src_decision`,`src_note`,`spaces_count`,
       `cls`,`ownership`,`decision`,`basis`,`rule_step`,`updated_at`)
      VALUES (?,?,'DEPARTMENT','',?,?,?,'BUSINESS_DEPARTMENT',
              'FORBIDDEN','VALID','CONFIRMED',?,1,
              'FORBIDDEN','VALID','CONFIRMED',?,6,NOW())");
$m = 0;
foreach ($FORBID as $f) {
    list($route, $role, $space, $why) = $f;
    $c = $conn->query("SELECT COUNT(*) FROM `gov_space_appearances`
                        WHERE `route` = '" . $conn->real_escape_string($route) . "'
                          AND `space_ar` = '" . $conn->real_escape_string($space) . "'
                          AND `cls` = 'FORBIDDEN'");
    if ($c && (int) $c->fetch_row()[0] > 0) { continue; }
    $next++;
    $scr = mb_substr($route, 0, 60);
    $owner = 'مساحة العمل الشخصية';
    $b = mb_substr($why, 0, 255);
    $ins->bind_param('issssss', $next, $space, $scr, $route, $owner, $SRC, $b);
    if ($ins->execute()) { $m++; } else { echo "   ✘ {$route}: {$ins->error}\n"; }
}
$ins->close();
printf("② أُعلن %d حكمَ مساحةٍ — إزالةٌ من مساحةٍ لا حذفٌ من النظام\n", $m);

ems_migration_recorded(__FILE__, $conn, 0);
