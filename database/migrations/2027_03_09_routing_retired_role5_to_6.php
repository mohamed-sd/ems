<?php
/**
 * 2027_03_09 — موازنةُ «المواقع» مسنَدةٌ لدورٍ متقاعدٍ بصفرِ مستخدم
 * ═══════════════════════════════════════════════════════════════════════════
 * **المقيسُ**: الدورُ **5** اسمُه في القاعدةِ «إدارة الموقع (**قديم — مدمج في
 * 6**)» و**مستخدموه صفر**؛ والدورُ **6** «إدارة الموقع» وفيه **11 مستخدمًا**.
 * ومع ذلك يشير `fin_request_routing` إلى **5** في ستةِ صفوف:
 *   · `#34/#35` (`sites`): المديرُ والمراجعُ والطالبُ **كلُّهم 5** ⇒ لا مالكَ
 *     يرفع موازنةَ إدارةٍ قائمة. و`fin_budget_owned_depts($role)`
 *     (`Finance/fin_helpers.php:1273`) يقرأ `manager_role_id` — فالدورُ 6 **لا
 *     يملك قسمًا** والدورُ 5 يملك `sites` **ولا أحدَ فيه**.
 *   · `#10/#11` (`projects`) و`#20/#21` (`general`): الدورُ 5 **داخلَ قائمةِ**
 *     الطالبين مع أدوارٍ أخرى — فهي تعمل لغيره ويبقى فيها مدخلٌ ميت.
 *
 * فدمجُ الدور 5 في 6 طُبِّق على `roles`/`role_permissions` **ولم يُطبَّق على جدولِ
 * التوجيه** — ودورةُ موازنةٍ بلا مالكٍ عطبٌ صامت: الشاشةُ تُفتح ولا قسمَ فيها.
 *
 * **ونطاقُ هذه الهجرةِ محدَّدٌ بقصد**: **جدولُ التوجيهِ الحيُّ وحدَه**.
 * ومسحُ القاعدةِ أظهر **762 صفًّا في 16 موضعًا** يشير إلى 5 — وأكثرُها **سجلاتُ
 * تاريخٍ لا تُمَسّ**: `activity_logs` (593) · `sec_perm_backup_20260806` (17) ·
 * `ticket_transfers` (الدورُ **وقتَ** التحويل). **إعادةُ كتابةِ ماضٍ تُفسده**،
 * ولذلك تُترك ويُعلَن جردُها لقرارٍ لاحق (`role_permissions` 90 · `nav_items` 27
 * · `link_groups` 8 · `modules` 3 … ودمجُها يلزمه قاعدةُ إزالةِ تكرارٍ لا تُخترع
 * في هجرة).
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$fail = array();
$one  = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

const OLD_ROLE = 5;
const NEW_ROLE = 6;

/* ── ① الدمجُ يُثبَت من القاعدةِ قبل أن يُطبَّق ────────────────────────────── */
$oldName = (string) $one('SELECT name FROM roles WHERE id = ' . OLD_ROLE);
$newName = (string) $one('SELECT name FROM roles WHERE id = ' . NEW_ROLE);
$u5 = (int) $one("SELECT COUNT(*) FROM users WHERE role = '" . OLD_ROLE . "'");
$u6 = (int) $one("SELECT COUNT(*) FROM users WHERE role = '" . NEW_ROLE . "'");
echo "── ① الدور " . OLD_ROLE . ": «{$oldName}» · مستخدموه {$u5}\n";
echo "     الدور " . NEW_ROLE . ": «{$newName}» · مستخدموه {$u6}\n";
if ($u5 !== 0) { fwrite(STDERR, "الدورُ القديمُ فيه {$u5} مستخدمًا — لا يُنقل توجيهُه\n"); exit(1); }
if ($u6 === 0) { fwrite(STDERR, "الدورُ الجديدُ بلا مستخدمين — النقلُ لا يُصلح شيئًا\n"); exit(1); }
if (mb_strpos($oldName, 'مدمج') === false) {
    fwrite(STDERR, "اسمُ الدورِ القديمِ لا يُعلن الدمجَ — لا يُفترض دمجٌ غيرُ موثَّق\n"); exit(1);
}

/* ── ② الحالُ قبل ─────────────────────────────────────────────────────────── */
$LIST5 = "(requester_roles = '5' OR requester_roles LIKE '5,%'
           OR requester_roles LIKE '%,5' OR requester_roles LIKE '%,5,%')";
$before = (int) $one("SELECT COUNT(*) FROM fin_request_routing
                       WHERE manager_role_id = " . OLD_ROLE . "
                          OR reviewer_role_id = " . OLD_ROLE . " OR {$LIST5}");
echo "── ② صفوفُ توجيهٍ تشير إلى الدورِ القديم: {$before}\n";
$r = $db->query("SELECT id, source_module, manager_role_id, reviewer_role_id, requester_roles
                   FROM fin_request_routing
                  WHERE manager_role_id = " . OLD_ROLE . "
                     OR reviewer_role_id = " . OLD_ROLE . " OR {$LIST5} ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) {
    echo "     #{$x['id']} {$x['source_module']}: مدير={$x['manager_role_id']}"
       . " مراجع={$x['reviewer_role_id']} طالبون={$x['requester_roles']}\n";
}
if ($before === 0) { echo "\n✅ لا توجيهَ يشير إلى الدورِ القديم — لا عمل.\n"; exit(0); }

/* ── ③ المديرُ والمراجعُ: استبدالٌ مباشر ────────────────────────────────── */
$ok = $db->query('UPDATE fin_request_routing SET manager_role_id = ' . NEW_ROLE
               . ' WHERE manager_role_id = ' . OLD_ROLE);
echo '── ③ مديرٌ: ' . ($ok ? $db->affected_rows : 0) . " صفًّا\n";
if (!$ok) { $fail[] = 'المدير: ' . $db->error; }
$ok = $db->query('UPDATE fin_request_routing SET reviewer_role_id = ' . NEW_ROLE
               . ' WHERE reviewer_role_id = ' . OLD_ROLE);
echo '     مراجعٌ: ' . ($ok ? $db->affected_rows : 0) . " صفًّا\n";
if (!$ok) { $fail[] = 'المراجع: ' . $db->error; }

/* ── ④ قائمةُ الطالبين: استبدالٌ **بلا تكرار** — تُعالَج صفًّا صفًّا ─────── */
$r = $db->query("SELECT id, requester_roles FROM fin_request_routing WHERE {$LIST5}");
$rows = array();
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
$st = $db->prepare('UPDATE fin_request_routing SET requester_roles = ? WHERE id = ?');
$listFixed = 0;
foreach ($rows as $x) {
    $parts = array_values(array_filter(array_map('trim', explode(',', (string) $x['requester_roles'])), 'strlen'));
    $out = array();
    foreach ($parts as $p) { $out[] = ($p === (string) OLD_ROLE) ? (string) NEW_ROLE : $p; }
    $out = array_values(array_unique($out));   // **لا يُكرَّر 6 إن كان حاضرًا سلفًا**
    $new = implode(',', $out);
    $id = (int) $x['id'];
    $st->bind_param('si', $new, $id);
    if (!$st->execute()) { $fail[] = "#{$id}: " . mb_substr($st->error, 0, 50); continue; }
    echo "     قائمةُ #{$id}: «{$x['requester_roles']}» ⇒ «{$new}»\n";
    $listFixed++;
}
$st->close();
echo "── ④ قوائمُ طالبين: {$listFixed} صفًّا\n";

/* ── ⑤ الشاهدُ المُشغَّل — من دالةِ المنتجِ نفسِها ────────────────────────── */
echo "── ⑤ الشاهدُ المُشغَّل\n";
$after = (int) $one("SELECT COUNT(*) FROM fin_request_routing
                      WHERE manager_role_id = " . OLD_ROLE . "
                         OR reviewer_role_id = " . OLD_ROLE . " OR {$LIST5}");
echo "     توجيهٌ يشير إلى الدورِ القديمِ بعد: {$after} " . ($after === 0 ? "✔\n" : "✘\n");
if ($after !== 0) { $fail[] = "بقي {$after} صفًّا"; }

$dupList = (int) $one("SELECT COUNT(*) FROM fin_request_routing
                        WHERE requester_roles LIKE '%6,%6%' OR requester_roles LIKE '%,6,%,6%'");
echo "     قائمةٌ فيها 6 مكرَّرًا: {$dupList} " . ($dupList === 0 ? "✔\n" : "✘\n");
if ($dupList !== 0) { $fail[] = "{$dupList} قائمةً بتكرار"; }

$ROOT = dirname(__DIR__, 2);
$pf = $ROOT . '/storage/mig0309_probe_' . getmypid() . '.php';
if (!is_dir($ROOT . '/storage')) { @mkdir($ROOT . '/storage', 0777, true); }
file_put_contents($pf, "<?php\n"
    . "error_reporting(0);\n"
    . '$_SESSION[\'user\'] = array(\'id\' => 1, \'role\' => \'' . NEW_ROLE . "', 'company_id' => 4);\n"
    . 'require ' . var_export($ROOT . '/config.php', true) . ";\n"
    . "while (ob_get_level() > 0) { ob_end_clean(); }\n"
    . '$_SESSION[\'user\'] = array(\'id\' => 1, \'role\' => \'' . NEW_ROLE . "', 'company_id' => 4);\n"
    . 'require_once ' . var_export($ROOT . '/Finance/fin_helpers.php', true) . ";\n"
    . "\$d = fin_budget_owned_depts('" . NEW_ROLE . "');\n"
    . "echo implode(',', \$d);\n");
$out = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($pf) . ' 2>&1'));
@unlink($pf);
$hasSites = (strpos($out, 'sites') !== false);
echo "     fin_budget_owned_depts(" . NEW_ROLE . ") ⇒ «" . mb_substr($out, -90) . '» '
   . ($hasSites ? "✔ (المواقعُ صار لها مالكٌ يرفع)\n" : "✘\n");
if (!$hasSites) { $fail[] = 'الدورُ الجديدُ لا يملك sites بعد'; }

echo "\n" . (empty($fail)
    ? "✅ موازنةُ «المواقع» صار لها مالكٌ فيه 11 مستخدمًا — ولم يُمَسَّ سجلُّ تاريخٍ واحد.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
