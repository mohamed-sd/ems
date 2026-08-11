<?php
/**
 * 2027_02_20 — يدُ المبيعات تُوصَل: صلاحيةُ شاشةِ اعتمادِ الساعات لدور 12
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالك 2026-08-12** (`SPEC_TIMESHEET_CYCLE §TS-13` — «خمسُ أيدٍ:
 * الموقع · **المبيعات** · الموردون · القوى · الحسابات»):
 *
 *   المقيسُ أن مرحلةَ `sales` كانت **مبنيةً بلا مُستهلِك**:
 *   `TimesheetEntryService::approve()` يعرفها ويحرس شرطَها
 *   (`state = parties_approved`)، **ولا شاشةَ في الشجرةِ تستدعيها** — فما بلغ
 *   يومٌ واحدٌ حالةَ `sales_approved` من الشاشات، و«وحدات الأطراف» تشترطها
 *   للتحويل ⇒ **تحويلُ اليومِ إلى مالٍ مقطوعٌ لكلِّ ما يُعتمد حيًّا**
 *   (الـ1500 حالةً القائمةَ بذرٌ لا إنتاج).
 *
 * الرمزُ وُصِل في: `includes/roles.php` (المستوياتُ الخمسةُ مصدرًا واحدًا) ·
 * `Approvals/hours_approval.php` + `_handler.php` (يشتقّان الخريطةَ ولا ينسخانها) ·
 * `TimesheetEntryService::LEGACY_LEVEL_STAGE[5] = 'sales'`.
 *
 * **وهذه الهجرةُ تُكمل الوصلَ بالإذن**: بلا صفِّ صلاحيةٍ لدور 12 على الوحدة 247
 * يُردُّ مديرُ المبيعاتِ عن الشاشةِ بـ`can_view` فارغةٍ — فتبقى اليدُ موصولةً
 * في الرمزِ ومقطوعةً في الواقع (وهو عينُ ما تنهى عنه قاعدةُ MD-05:
 * **البناءُ ليس تبنّيًا**).
 *
 * مُتحمِّلٌ للتكرار · وينتهي بشاهدٍ يقيس القرارَ من دالةِ الشاشةِ نفسِها.
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

const SALES_ROLE = 12;
const SCREEN     = 'Approvals/hours_approval.php';

/* ── ① الوحدةُ تُقاس ولا تُفترض ──────────────────────────────────────────── */
$mid = (int) $one("SELECT id FROM modules WHERE code = '" . SCREEN . "'");
if ($mid === 0) {
    fwrite(STDERR, "الوحدةُ " . SCREEN . " غير مسجَّلةٍ في modules — لا يُخترع تسجيل\n");
    exit(1);
}
echo "── ① الوحدة: #{$mid} (" . SCREEN . ")\n";

/* ── ② القدوةُ: ما يملكه أصحابُ السلسلةِ الأربعةُ هو ما يُمنَح للخامس ────── */
$r = $db->query("SELECT role_id, can_view, can_add, can_edit, can_delete
                   FROM role_permissions WHERE module_id = {$mid} AND role_id IN (1,2,3,4)
                  ORDER BY role_id");
$peers = array();
while ($r && ($x = $r->fetch_assoc())) { $peers[] = $x; }
echo '── ② أصحابُ السلسلةِ الحاضرون: ' . count($peers) . " دورًا\n";
foreach ($peers as $p) {
    echo "     دور {$p['role_id']}: view={$p['can_view']} add={$p['can_add']} edit={$p['can_edit']} del={$p['can_delete']}\n";
}
if (empty($peers)) { fwrite(STDERR, "لا قدوةَ تُقاس — لا يُمنَح إذنٌ بالتخمين\n"); exit(1); }

// يدُ المبيعاتِ تعتمد ولا تحذف: الاعتمادُ فعلُ تعديلٍ لا إنشاءٍ ولا حذف.
$grant = array('can_view' => 1, 'can_add' => 0, 'can_edit' => 1, 'can_delete' => 0);

/* ── ③ المنحُ — مُتحمِّلٌ للتكرار ─────────────────────────────────────────── */
$exists = (int) $one("SELECT COUNT(*) FROM role_permissions
                       WHERE module_id = {$mid} AND role_id = " . SALES_ROLE);
if ($exists === 0) {
    $ok = $db->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      VALUES (" . SALES_ROLE . ", {$mid}, {$grant['can_view']}, {$grant['can_add']},
                              {$grant['can_edit']}, {$grant['can_delete']})");
    if (!$ok) { $fail[] = 'المنح: ' . $db->error; }
    echo '── ③ صفُّ الصلاحيةِ لدور ' . SALES_ROLE . ': ' . ($ok ? "أُنشئ\n" : "تعذّر — {$db->error}\n");
} else {
    $ok = $db->query("UPDATE role_permissions
                         SET can_view = {$grant['can_view']}, can_edit = {$grant['can_edit']}
                       WHERE module_id = {$mid} AND role_id = " . SALES_ROLE);
    if (!$ok) { $fail[] = 'التحديث: ' . $db->error; }
    echo "── ③ صفُّ الصلاحيةِ قائمٌ — ضُبطت view/edit\n";
}

/* ── ④ الشاهدُ المُشغَّل: القرارُ يُقاس من دالةِ الشاشةِ نفسِها ───────────── */
echo "── ④ الشاهدُ المُشغَّل\n";
$r = $db->query("SELECT can_view, can_edit FROM role_permissions
                  WHERE module_id = {$mid} AND role_id = " . SALES_ROLE);
$row = $r ? $r->fetch_assoc() : null;
$dbOk = $row && (int) $row['can_view'] === 1 && (int) $row['can_edit'] === 1;
echo '     صفُّ القاعدة: ' . ($dbOk ? "view=1 edit=1 ✔\n" : "ناقصٌ ✘\n");
if (!$dbOk) { $fail[] = 'صفُّ الصلاحيةِ لم يثبت'; }

// دالةُ الشاشةِ ذاتُها — لا استعلامٌ موازٍ يوهم النجاح
$ROOT = dirname(__DIR__, 2);
/* ◆ **الجلسةُ تُزرع بعدَ `config.php` لا قبلَه**: `config.php` يستدعي
     `session_start()` فيُحِلُّ محتوى الجلسةِ المخزَّنِ محلَّ ما وُضع قبلَه —
     فتُقرأ `DENY` والصلاحيةُ ممنوحةٌ فعلًا. والدالةُ تقرأ `$_SESSION['user']['role']`
     حصرًا (`permissions_helper.php:286`) فالترتيبُ هو كلُّ شيء. */
$probeDir  = dirname(__DIR__, 2) . '/storage';
if (!is_dir($probeDir)) { @mkdir($probeDir, 0777, true); }
$probeFile = $probeDir . '/mig0220_perm_probe_' . getmypid() . '.php';
file_put_contents($probeFile, "<?php\n"
    . "error_reporting(0);\n"
    . 'require ' . var_export($ROOT . '/config.php', true) . ";\n"
    . "while (ob_get_level() > 0) { ob_end_clean(); }\n"
    . '$_SESSION[\'user\'] = array(\'id\' => 888, \'role\' => \'' . SALES_ROLE . "', 'company_id' => 4);\n"
    . 'require_once ' . var_export($ROOT . '/includes/permissions_helper.php', true) . ";\n"
    . '$p = check_page_permissions($GLOBALS[\'conn\'], ' . var_export(SCREEN, true) . ");\n"
    . "echo empty(\$p['can_view']) ? 'DENY' : 'ALLOW';\n");
$out = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probeFile) . ' 2>&1'));
@unlink($probeFile);
$allow = (substr($out, -5) === 'ALLOW');
echo '     check_page_permissions لدور ' . SALES_ROLE . ': ' . ($allow ? "ALLOW ✔\n" : "لم تُعطِ ALLOW ✘ — " . mb_substr($out, -160) . "\n");
if (!$allow) { $fail[] = 'دالةُ الشاشةِ لا تسمح'; }

// والمستوى الخامسُ مشتقٌّ من الإعلانِ الواحد لا مكتوبٌ رقمًا
$probeFile2 = $probeDir . '/mig0220_level_probe_' . getmypid() . '.php';
file_put_contents($probeFile2, "<?php\n"
    . 'require ' . var_export($ROOT . '/includes/roles.php', true) . ";\n"
    . "\$m = ems_hours_role_level_map();\n"
    . '$k = \'' . SALES_ROLE . "';\n"
    . "echo (isset(\$m[\$k]) && \$m[\$k] === EMS_HOURS_APPROVAL_FINAL_LEVEL) ? ('L' . \$m[\$k]) : 'BAD';\n");
$out2 = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probeFile2) . ' 2>&1'));
@unlink($probeFile2);
echo "     خريطةُ المستويات: دور " . SALES_ROLE . " ⇒ {$out2} " . ($out2 === 'L5' ? "✔\n" : "✘\n");
if ($out2 !== 'L5') { $fail[] = 'خريطةُ المستويات لا تعطي L5'; }

echo "\n" . (empty($fail)
    ? "✅ يدُ المبيعاتِ موصولةٌ: المستوى ٥ في الخريطةِ، والشاشةُ تفتح لدور 12 بقرارِ دالتِها.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
