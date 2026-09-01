<?php
/**
 * 2028_03_02_govui_space_own_not_forbidden.php — لا يُمنَع سطحٌ في مساحتِه المالكة
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: update:gov_space_appearances(cls,ownership,basis,owner_dept_ar)
 *
 * ◆ **العطبُ مقيسٌ لا مُخمَّن** (`GOV_UI_EXEC` §9 · §12): عشرةُ مواضعِ أهدافٍ
 *   مُسجَّلةٍ في `nav_placements` لمساحاتِها المالكةِ وجدتُها `cls='FORBIDDEN'`
 *   في `gov_space_appearances` **لمساحةِ مالكِها نفسِه** — فحجبَها مُرشِّحُ
 *   `ems_scope_forbidden_set` عن دورِها، وهي **مبنيّةٌ ومسجَّلةٌ ومصرَّحٌ لها**
 *   (`role_permissions.can_view=1` و`nav_items.active=1` مقيسان).
 *
 * ◆ **والجذرُ في اللقطةِ لا في الشاشة**: شجرةُ قرارِ «ثامنًا-⑦» بلغت العقدةَ
 *   السادسة («لا عقدةَ سابقةً تنطبق») لأنَّ `owner_dept_ar` فيها كان **إدارة
 *   المشتريات التشغيلية** لسبعةِ أسطحٍ يُسندها الدليلُ المعماريُّ إلى **إدارة
 *   المخازن** — فحُكم عليها «ليست مملوكةً لهذه المساحة» وهي مملوكةٌ لها.
 *   واثنان (`wh_receipt` · `consumption_rate`) مالكُهما مكتوبٌ **إدارة المخازن**
 *   ومُنعا في مساحتِها — تناقضٌ في اللقطةِ نفسِها.
 *
 * ◆ **والحكمُ من الملفِّ الحاكمِ لا من اللقطة** (§2): «واذا اختلف Current عن
 *   الملف: صحح Current». فالمالكُ يُقرأ من `nav_placements.workspace_id`
 *   (المستوردةِ من `01 · الدليل المعماري`) والصنفُ يصير `OWNED`.
 *
 * ◆ ⛔ **ولا يُوسَّع النطاق**: لا يُمَسُّ صفٌّ إلّا إن كان **مسارُه موضعَ هدفٍ
 *   في المساحةِ نفسِها** — فمنعٌ في مساحةٍ أخرى يبقى كما هو.
 *
 * التشغيل: php database/migrations/2028_03_02_govui_space_own_not_forbidden.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

/* دفترُ الرجوع — يُنشأ مرّةً ويحمل القيمةَ السابقةَ لكلِّ صفٍّ مسَّته الجولة */
$conn->query("CREATE TABLE IF NOT EXISTS `govui_space_fix_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appearance_id` int(11) NOT NULL,
  `space_ar` varchar(80) NOT NULL,
  `route` varchar(190) NOT NULL,
  `old_cls` varchar(32) NOT NULL,
  `old_owner` varchar(120) NOT NULL,
  `new_cls` varchar(32) NOT NULL,
  `new_owner` varchar(120) NOT NULL,
  `basis` varchar(255) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_gsfl_app` (`appearance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV_UI_EXEC: قيدُ رجوعِ تصحيحِ منعِ السطحِ في مساحتِه المالكة'");

/* المرشَّحون: موضعُ هدفٍ في مساحةٍ لها دورٌ أوّليٌّ، ومسارُه ممنوعٌ في اسمِ ذلك الدور */
$sql = "SELECT DISTINCT a.id, a.space_ar, a.route, a.cls, a.owner_dept_ar,
               p.workspace_id, w.name_ar AS ws_name
          FROM nav_placements p
          JOIN nav_ws_roles nr ON nr.workspace_id = p.workspace_id AND nr.binding = 'PRIMARY'
          JOIN roles r         ON r.id = nr.role_id
          JOIN nav_workspaces w ON w.workspace_id = p.workspace_id
          JOIN gov_space_appearances a
               ON a.space_ar = r.name AND LOWER(a.route) = LOWER(p.route)
         WHERE a.cls = 'FORBIDDEN' AND p.route IS NOT NULL AND p.route <> ''";
$res = $conn->query($sql);
if (!$res) { exit("⛔ استعلام: {$conn->error}\n"); }
$rows = array();
while ($x = $res->fetch_assoc()) { $rows[(int) $x['id']] = $x; }

$log = $conn->prepare("INSERT INTO govui_space_fix_log
    (appearance_id, space_ar, route, old_cls, old_owner, new_cls, new_owner, basis)
    VALUES (?,?,?,?,?,?,?,?)");
$up = $conn->prepare("UPDATE gov_space_appearances
                         SET cls = 'OWNED', ownership = 'CORRECTED',
                             owner_dept_ar = ?, rule_step = 1,
                             basis = ?
                       WHERE id = ?");
if (!$log || !$up) { exit("⛔ prepare: {$conn->error}\n"); }

$n = 0;
foreach ($rows as $id => $x) {
    $newOwner = (string) $x['ws_name'];
    $basis = 'GOV_UI_EXEC §9/§12 — موضعُ هدفٍ في مساحتِه المالكة ('
           . $x['workspace_id'] . ') مستوردٌ من 01 · الدليل المعماري: لا يُمنَع المالكُ في مساحتِه';
    $newCls = 'OWNED';
    $log->bind_param('isssssss', $id, $x['space_ar'], $x['route'], $x['cls'],
        $x['owner_dept_ar'], $newCls, $newOwner, $basis);
    if (!$log->execute()) { exit("⛔ log {$id}: {$conn->error}\n"); }
    $up->bind_param('ssi', $newOwner, $basis, $id);
    if (!$up->execute()) { exit("⛔ update {$id}: {$conn->error}\n"); }
    echo "  ✔ [{$x['space_ar']}] {$x['route']} : FORBIDDEN ⇒ OWNED · مالكٌ «{$newOwner}»\n";
    $n++;
}
echo "المصحَّح: {$n} صفَّ ظهورٍ\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
