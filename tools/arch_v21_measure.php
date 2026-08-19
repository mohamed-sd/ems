<?php
/**
 * tools/arch_v21_measure.php — قياسُ النظامِ لوثيقةِ المعمارية v21
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لا رقمَ في وثيقةِ المعماريةِ يُنقل من سابقتِها. v20 نفسُها تحذّر: «القاعدةُ
 *   تتحرك تحت القياس» (492 ⇐ 544 جدولًا في ساعتين) — فكلُّ رقمٍ لقطةٌ بلحظتِها.
 * ◆ وv19 أخطأت بالوثوقِ **بنتيجةٍ سالبةٍ من مصدرٍ واحد**؛ فما أمكن هنا يُقاس
 *   من مصدرَين (القاعدةُ والقرص) ويُعلَن الفرقُ إن وُجد.
 *
 * التشغيل: php tools/arch_v21_measure.php [--json=مسار]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();
$M = array();

function q($db, $sql) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; }
function qa($db, $sql) { $r = $db->query($sql); $o = array(); while ($r && ($x = $r->fetch_row())) { $o[$x[0]] = $x[1]; } return $o; }

/* ══ ① القاعدة ══════════════════════════════════════════════════════════ */
$M['db_version']  = q($db, 'SELECT VERSION()');
$M['db_name']     = q($db, 'SELECT DATABASE()');
$M['tables']      = (int) q($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'");
$M['views']       = (int) q($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='VIEW'");
/* ◆ عيبٌ مُصحَّح (2026-08-19): كان يعدُّ من `COLUMNS` وحدَها — و`COLUMNS`
 * تشمل **المناظرَ** لا الجداولَ فقط، فطبع 489 والصادقُ 478 (و11 منظرًا).
 * والمنظرُ لا «يُعزَل»: عزلُه عزلُ جدولِه. فيُقَيَّد النوعُ صراحةً ويُعلَن الفرق. */
$M['with_company']= (int) q($db, "SELECT COUNT(DISTINCT c.TABLE_NAME) FROM information_schema.COLUMNS c JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME WHERE c.TABLE_SCHEMA=DATABASE() AND c.COLUMN_NAME='company_id' AND t.TABLE_TYPE='BASE TABLE'");
$M['with_company_views'] = (int) q($db, "SELECT COUNT(DISTINCT c.TABLE_NAME) FROM information_schema.COLUMNS c JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME WHERE c.TABLE_SCHEMA=DATABASE() AND c.COLUMN_NAME='company_id' AND t.TABLE_TYPE='VIEW'");
$M['with_company_raw']   = $M['with_company'] + $M['with_company_views'];
$M['fks']         = (int) q($db, "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='FOREIGN KEY'");
$M['checks']      = (int) q($db, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()");
$M['uniques']     = (int) q($db, "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='UNIQUE'");
$M['triggers']    = (int) q($db, "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()");
$M['routines']    = (int) q($db, "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()");

/* بادئاتُ النطاقات — تُحسب آليًّا لا تُسرد يدويًّا */
$pref = array();
$r = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'");
while ($r && ($x = $r->fetch_row())) {
    $n = $x[0];
    $p = (strpos($n, '_') !== false) ? substr($n, 0, strpos($n, '_') + 1) : '—';
    if (!isset($pref[$p])) { $pref[$p] = 0; }
    $pref[$p]++;
}
arsort($pref);
$M['prefixes'] = array_slice($pref, 0, 26, true);

/* ══ ② السجلاتُ الحاكمة ═════════════════════════════════════════════════ */
/* ◆ جدولُ التتبعِ اسمُه `schema_migrations` لا `migrations` — و`migrations` اسمٌ
     غيرُ قائمٍ فأعاد استعلامي صفرًا، والصفرُ من استعلامٍ فاشلٍ ليس قياسًا
     (گوتشا مسجَّلة). يُقرأ الاسمُ من `migrate.php` نفسِه لا يُفترض. */
$M['migrations_db_rows'] = (int) q($db, 'SELECT COUNT(*) FROM schema_migrations');
$M['migrations_applied'] = (int) q($db, "SELECT COUNT(*) FROM schema_migrations WHERE status='applied'");
$M['migrations_baseline']= (int) q($db, "SELECT COUNT(*) FROM schema_migrations WHERE status='baseline'");
$M['migration_files']    = count(glob($ROOT . '/database/migrations/*.php')) + count(glob($ROOT . '/database/migrations/*.sql'));
$M['modules']  = (int) q($db, 'SELECT COUNT(*) FROM modules');
$M['roles']    = (int) q($db, 'SELECT COUNT(*) FROM roles');
$M['nav_items']= (int) q($db, 'SELECT COUNT(*) FROM nav_items WHERE active=1');
$M['link_groups_active'] = (int) q($db, 'SELECT COUNT(*) FROM link_groups WHERE is_active=1');
$M['link_groups_all']    = (int) q($db, 'SELECT COUNT(*) FROM link_groups');
$M['nav09_file_map'] = (int) q($db, 'SELECT COUNT(*) FROM nav09_file_map');

/* ◆ **سجلّان للأفعالِ لا واحد** — وخلطُهما يُنتج رقمًا كاذبًا:
     · `nav09_action_map` = قاموسُ أفعالِ NAV-09 بتصنيفِ الكتابة (`write_class`).
     · `actions`          = سجلُّ حارسِ أفعالِ AJAX (ADR-06) — ما يُحجب غيرُ المسجَّل.
   والأولُ مقامُ «كم فعلًا يعرف النظام»، والثاني «كم فعلَ AJAX محكومًا». */
$M['action_dict'] = (int) q($db, 'SELECT COUNT(*) FROM nav09_action_map');
$M['action_dict_no_wclass'] = (int) q($db, "SELECT COUNT(*) FROM nav09_action_map WHERE write_class IS NULL OR write_class=''");
$M['action_guard_registry'] = (int) q($db, 'SELECT COUNT(*) FROM actions');
$M['action_guard_write'] = (int) q($db, 'SELECT COUNT(*) FROM actions WHERE is_write=1');
$M['role_permissions'] = (int) q($db, 'SELECT COUNT(*) FROM role_permissions');
$M['users_active'] = (int) q($db, 'SELECT COUNT(*) FROM users WHERE is_deleted=0');
$M['companies'] = (int) q($db, 'SELECT COUNT(*) FROM admin_companies');
$M['org_units'] = (int) q($db, 'SELECT COUNT(*) FROM org_units');
$M['guard_policies'] = (int) q($db, 'SELECT COUNT(*) FROM guard_policies');

/* الأعمدةُ الحاكمة + شاشاتُ CMP-03 */
$M['gov_columns'] = (int) q($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_columns'")
    ? (int) q($db, 'SELECT COUNT(*) FROM gov_columns') : 0;
$M['scr_tables'] = (int) q($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'scr\\_%'");
$M['saved_views'] = (int) q($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ems_saved_views'")
    ? (int) q($db, 'SELECT COUNT(*) FROM ems_saved_views') : 0;

/* سلاليمُ الاعتماد — وحالُ قواعدِها (عطلٌ معلَنٌ في v21) */
$M['wf_rules_total'] = (int) q($db, 'SELECT COUNT(*) FROM approval_workflow_rules WHERE is_active=1');
$M['wf_rules_real']  = (int) q($db, "SELECT COUNT(*) FROM approval_workflow_rules
    WHERE is_active=1 AND entity_type NOT LIKE '% %' AND CHAR_LENGTH(entity_type) < 40");
$M['wf_entities_real'] = (int) q($db, "SELECT COUNT(DISTINCT entity_type) FROM approval_workflow_rules
    WHERE is_active=1 AND entity_type NOT LIKE '% %' AND CHAR_LENGTH(entity_type) < 40");

/* ══ ③ القرص ═══════════════════════════════════════════════════════════ */
function count_php($dir, $recursive = true)
{
    if (!is_dir($dir)) { return 0; }
    $n = 0;
    $it = $recursive
        ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS))
        : new DirectoryIterator($dir);
    foreach ($it as $f) { if ($f->isFile() && $f->getExtension() === 'php') { $n++; } }
    return $n;
}
$M['tools']   = count_php($ROOT . '/tools', false);
$M['tests']   = count_php($ROOT . '/tests', false);
$M['includes']= count_php($ROOT . '/includes', false);
$M['core']    = count_php($ROOT . '/app/Core', false);
$M['services']= count_php($ROOT . '/app/Services');
$svcDom = array();
foreach ((array) glob($ROOT . '/app/Services/*', GLOB_ONLYDIR) as $d) {
    $svcDom[basename($d)] = count_php($d);
}
arsort($svcDom);
$M['service_domains'] = count($svcDom);
$M['service_top'] = array_slice($svcDom, 0, 18, true);

/* الأسطحُ الحيةُ — مجلداتُ الشاشاتِ وحدَها لا tools/includes (مقامٌ لا يُخلط) */
$SURFACE_SKIP = array('tools', 'tests', 'includes', 'app', 'vendor', 'database', 'docs', 'storage',
                      'node_modules', 'assets', '.git', '.claude', 'logs', 'emsreports');
$surf = 0; $surfDirs = array();
foreach ((array) glob($ROOT . '/*', GLOB_ONLYDIR) as $d) {
    $b = basename($d);
    if (in_array($b, $SURFACE_SKIP, true)) { continue; }
    $c = count_php($d);
    if ($c > 0) { $surfDirs[$b] = $c; $surf += $c; }
}
$surf += count_php($ROOT, false);
arsort($surfDirs);
$M['surfaces_dirs'] = $surf;
$M['surface_top'] = array_slice($surfDirs, 0, 16, true);
$M['php_files_all'] = count_php($ROOT);

/* الوثائقُ الحاكمة */
$M['docs_dirs'] = array();
foreach ((array) glob($ROOT . '/docs/*', GLOB_ONLYDIR) as $d) {
    $M['docs_dirs'][basename($d)] = count(glob($d . '/*'));
}
$M['docx_fix'] = count(glob($ROOT . '/docs/fix/*.docx'));

/* أدواتُ حقبةِ FIX */
$M['fix_tools'] = count(glob($ROOT . '/tools/fix_*.php'));

/* CSS/JS الموحَّد */
$M['css_shell'] = is_file($ROOT . '/assets/css/ems-shell.css');
$M['css_tokens'] = is_file($ROOT . '/assets/css/design-tokens.css');
if ($M['css_tokens']) {
    $t = (string) file_get_contents($ROOT . '/assets/css/design-tokens.css');
    $M['tokens_count'] = preg_match_all('/^\s*--[a-zA-Z0-9-]+\s*:/m', $t);
}

/* ══ ④ الإخراج ═════════════════════════════════════════════════════════ */
echo "══════════════════════════════════════════════════════════════════════\n";
echo " قياسُ المعمارية v21 — " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";
foreach ($M as $k => $v) {
    if (is_array($v)) {
        echo str_pad($k, 26) . ":\n";
        foreach ($v as $kk => $vv) { echo '    ' . str_pad((string) $kk, 24) . $vv . "\n"; }
    } else {
        echo str_pad($k, 26) . ': ' . var_export($v, true) . "\n";
    }
}
foreach ($argv as $a) {
    if (strpos($a, '--json=') === 0) {
        file_put_contents(substr($a, 7), json_encode($M, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "\nكُتب JSON\n";
    }
}
exit(0);
