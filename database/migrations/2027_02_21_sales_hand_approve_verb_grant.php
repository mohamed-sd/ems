<?php
/**
 * 2027_02_21 — يدُ المبيعات: الفعلُ `approve` يُصنَّف `add` لا `edit`
 * ═══════════════════════════════════════════════════════════════════════════
 * هجرةُ `2027_02_20` منحت دور 12 `can_view=1, can_edit=1, can_add=0` بحجّةِ أن
 * «الاعتمادَ تعديلٌ لا إنشاء». وقد فتحت الشاشةَ فعلًا — **ثم حجبَ حارسُ الأفعال
 * الاعتمادَ نفسَه**: `ems_action_verb_map` (`includes/action_guard.php:123`) يضع
 * `approve` في قائمةِ **`add`** صراحةً، فيقيس الحارسُ `can_add` لا `can_edit`،
 * فردَّ الطلبَ بـ«ليس لديك صلاحية تنفيذ هذا الإجراء».
 *
 * **الدرسُ المقيس**: تصنيفُ الفعلِ ليس اجتهادًا لغويًّا — **له خريطةٌ في الرمز**،
 * والقدوةُ حاضرةٌ سلفًا: أصحابُ السلسلةِ الأربعةُ كلُّهم `can_add=1` على الوحدةِ
 * نفسِها. فمنحُ الخامسِ أقلَّ منهم يعني يدًا موصولةً في الشاشةِ ومقطوعةً في الفعل.
 *
 * وينتهي بشاهدٍ **يُعيد قرارَ الحارسِ حرفيًّا**: خريطةُ الفعلِ ثم `can_<verb>`
 * على وحداتِ الشاشةِ الأمّ — لا صفَّ قاعدةٍ يُقرأ ويُفترض أنه كافٍ.
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
$ROOT = dirname(__DIR__, 2);

const SALES_ROLE = 12;
const SCREEN     = 'Approvals/hours_approval.php';

$mid = (int) $one("SELECT id FROM modules WHERE code = '" . SCREEN . "'");
if ($mid === 0) { fwrite(STDERR, "الوحدةُ غير مسجَّلة\n"); exit(1); }

/* ── ① القدوةُ تُقاس: بم يعتمد أصحابُ السلسلةِ الأربعة؟ ─────────────────── */
$peerAdd = (int) $one("SELECT MIN(can_add) FROM role_permissions
                        WHERE module_id = {$mid} AND role_id IN (1,2,3,4)");
echo "── ① أصحابُ السلسلةِ الأربعةُ: أدنى can_add = {$peerAdd}\n";
if ($peerAdd !== 1) {
    echo "     ○ القدوةُ لا تحمل can_add — لا يُمنَح الخامسُ ما لا يملكه الأربعة\n";
}

/* ── ② المنحُ على مقاسِ القدوة ──────────────────────────────────────────── */
$ok = $db->query("UPDATE role_permissions SET can_add = {$peerAdd}
                   WHERE module_id = {$mid} AND role_id = " . SALES_ROLE);
if (!$ok) { $fail[] = 'المنح: ' . $db->error; }
$now = (int) $one("SELECT can_add FROM role_permissions
                    WHERE module_id = {$mid} AND role_id = " . SALES_ROLE);
echo "── ② can_add لدور " . SALES_ROLE . " = {$now}\n";

/* ── ③ الشاهدُ: قرارُ الحارسِ يُعاد حرفيًّا ─────────────────────────────── */
echo "── ③ الشاهدُ المُشغَّل — قرارُ حارسِ الأفعال\n";
$probeDir = $ROOT . '/storage';
if (!is_dir($probeDir)) { @mkdir($probeDir, 0777, true); }
$pf = $probeDir . '/mig0221_guard_probe_' . getmypid() . '.php';
/* `includes/action_guard.php` كلُّه ملفوفٌ في `if (!function_exists(...))` ولا
   ينفّذ شيئًا عند التضمين — فيُضمَّن صراحةً لأن `config.php` في CLI لا يحمّله،
   فكانت الدالةُ غائبةً والمسبارُ يخرج فارغًا (فيُقرأ حجبًا وهو غيابُ تعريف). */
file_put_contents($pf, "<?php\n"
    . "error_reporting(0);\n"
    . 'require ' . var_export($ROOT . '/config.php', true) . ";\n"
    . "while (ob_get_level() > 0) { ob_end_clean(); }\n"
    . '$_SESSION[\'user\'] = array(\'id\' => 888, \'role\' => \'' . SALES_ROLE . "', 'company_id' => 4);\n"
    . 'require_once ' . var_export($ROOT . '/includes/permissions_helper.php', true) . ";\n"
    . 'require_once ' . var_export($ROOT . '/includes/action_guard.php', true) . ";\n"
    . "if (!function_exists('ems_action_verb_map')) { echo 'NOFUNC|NOFUNC'; exit; }\n"
    . "\$verb = ems_action_verb_map('approve');\n"
    // وحداتُ الشاشةِ الأمِّ تُقرأ من سجلِّ الحارسِ نفسِه لا تُفترض
    . "\$mods = array(" . var_export(SCREEN, true) . ");\n"
    . "if (function_exists('ems_action_guard_registry')) {\n"
    . "    foreach (ems_action_guard_registry() as \$path => \$e) {\n"
    . "        if (strpos(\$path, 'hours_approval_handler.php') !== false && !empty(\$e['modules'])) {\n"
    . "            \$mods = \$e['modules']; break;\n"
    . "        }\n"
    . "    }\n"
    . "}\n"
    . "\$allowed = false;\n"
    . "foreach (\$mods as \$code) {\n"
    . "    \$p = check_page_permissions(\$GLOBALS['conn'], \$code);\n"
    . "    if (!empty(\$p['can_' . \$verb])) { \$allowed = true; break; }\n"
    . "}\n"
    . "echo \$verb . '|' . (\$allowed ? 'ALLOW' : 'DENY') . '|' . implode(',', \$mods);\n");
$out = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($pf) . ' 2>&1'));
@unlink($pf);
$parts = explode('|', $out);
$verb  = isset($parts[0]) ? trim($parts[0]) : '?';
$dec   = isset($parts[1]) ? trim($parts[1]) : '?';
$mods  = isset($parts[2]) ? trim($parts[2]) : '';
echo "     خريطةُ الفعل: approve ⇒ '{$verb}'\n";
if ($mods !== '') { echo "     وحداتُ الشاشةِ الأمِّ في سجلِّ الحارس: {$mods}\n"; }
echo "     قرارُ الحارس: {$dec} " . ($dec === 'ALLOW' ? "✔\n" : "✘ — " . mb_substr($out, -160) . "\n");
if ($verb !== 'add') { $fail[] = "خريطةُ الفعلِ أعطت '{$verb}' لا 'add'"; }
if ($dec !== 'ALLOW') { $fail[] = 'حارسُ الأفعالِ يحجب دورَ المبيعات'; }

echo "\n" . (empty($fail)
    ? "✅ يدُ المبيعاتِ تعتمد فعلًا: الفعلُ `approve` مصنَّفٌ `add` وحارسُ الأفعالِ يسمح.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
