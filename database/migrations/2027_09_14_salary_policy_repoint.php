<?php
/**
 * 2027_09_14_salary_policy_repoint.php
 *   سياسةُ الأجرِ تُوجَّه إلى عمودِها الحقيقيّ — INJ-FIX-02 · NF-09 (GAP-12)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيب**: `sensitive_field_policies` تحمل `employees.salary` بـ
 *   `masking_rule = full` — **والعمودُ لا وجودَ له**. والعمودُ الحقيقيُّ
 *   `employees.monthly_salary` **غيرُ محميٍّ في أيِّ سجل**. فالأجورُ مكشوفةٌ
 *   **لأن سياستَها تحرس وهمًا**.
 *
 * ◆ **ولماذا يجوز التحويلُ الآن وقد أُجِّل من قبل**: أُجِّل لأنه بدا **تغييرَ
 *   وصولٍ حيّ** — من يرى الأجرَ اليومَ قد يُحجَب غدًا. والقياسُ يرفع ذلك:
 *   **السياسةُ نفسُها تُعلن أصحابَ الحق**: `allowed_roles_json = ["4","17","19"]`
 *   (الموارد البشرية · المالية · المدير المالي). ⇒ **فالتحويلُ تنفيذُ نيّةٍ
 *   مكتوبةٍ لا اختراعُ سلطة**، وأصحابُ الحقِّ مُسمَّون سلفًا لا يُمنحون الآن.
 *   ويوافقه `scr_sensitive_fields`: «سري — أجر · يراه الموارد والمالية».
 *
 * ◆ **ولا يُوسَّع الأثرُ صامتًا**: الإخفاءُ يسري **حيثُ يُنادى الحارس** فقط
 *   (`ems_sensitive_display` · `api_sensitive_value`). وسطحٌ يعرض العمودَ بلا
 *   نداءٍ يبقى كما هو — **ويُعلَن هنا بعددِه** لا يُطوى.
 *
 * التشغيل:  php database/migrations/2027_09_14_salary_policy_repoint.php
 * الرجوع :  php database/migrations/2027_09_14_salary_policy_repoint.php --revert
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

$OLD = 'employees.salary';
$NEW = 'employees.monthly_salary';

if (in_array('--revert', $argv, true)) {
    $conn->query("UPDATE `sensitive_field_policies` SET `field_code` = '{$OLD}'
                   WHERE `field_code` = '{$NEW}'");
    echo "↺ أُعيدت السياسةُ إلى {$OLD} ({$conn->affected_rows})\n";
    $conn->query("DELETE FROM `gov_sensitive_policy_debt` WHERE `declared_target` = '{$NEW}'");
    exit(0);
}

/* ══ ① العمودُ الجديدُ موجودٌ فعلًا — ولا يُحوَّل إلى وهمٍ آخر ═══════════ */
list($t, $f) = explode('.', $NEW, 2);
$q = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$f}'");
if (!$q || (int) $q->fetch_row()[0] === 0) { exit("✘ `{$NEW}` لا وجودَ له — أُوقفت الهجرة\n"); }
echo "① العمودُ الهدف `{$NEW}` موجود\n";

/* ══ ② أصحابُ الحقِّ مُعلَنون سلفًا — ولا يُمنَح أحدٌ الآن ══════════════════ */
$q = $conn->query("SELECT `allowed_roles_json`, `masking_rule`, `classification`
                     FROM `sensitive_field_policies` WHERE `field_code` = '{$OLD}'");
$pol = $q ? $q->fetch_assoc() : null;
if (!$pol) { exit("✘ لا سياسةَ باسم `{$OLD}` — أُوقفت الهجرة\n"); }
$roles = trim((string) $pol['allowed_roles_json']);
if ($roles === '' || $roles === 'null' || $roles === '[]') {
    exit("✘ السياسةُ **بلا أصحابِ حقٍّ مُعلَنين** — والتحويلُ حينئذٍ يحجب الجميعَ. أُوقفت الهجرة\n");
}
echo "② أصحابُ الحقِّ المُعلَنون سلفًا: {$roles} · إخفاء={$pol['masking_rule']} · تصنيف={$pol['classification']}\n";

/* ══ ③ التحويل ════════════════════════════════════════════════════════════ */
$conn->query("UPDATE `sensitive_field_policies` SET `field_code` = '{$NEW}'
               WHERE `field_code` = '{$OLD}'");
echo "③ حُوِّلت السياسة: {$OLD} ⇐ {$NEW} ({$conn->affected_rows} صفًّا)\n";

/* ويُشطب من دَينِ السياساتِ لأنه لم يعُد وهميًّا */
$conn->query("DELETE FROM `gov_sensitive_policy_debt` WHERE `declared_target` = '{$OLD}'");
echo "   وشُطب من سجلِّ الدَّين ({$conn->affected_rows})\n";

/* ══ ④ أينَ يسري الإخفاءُ فعلًا — يُعلَن بعددِه ═══════════════════════════ */
$disp = array(); $guarded = 0;
foreach (array('Employees', 'Workforce', 'Payroll', 'main', 'admin', 'api') as $d) {
    if (!is_dir($ROOT . '/' . $d)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/' . $d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') { continue; }
        $src = (string) @file_get_contents($file->getPathname());
        if (strpos($src, 'monthly_salary') === false) { continue; }
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($ROOT) + 1));
        /* ◆ **وحرّاسُ الحقلِ الحساسِ ثلاثةٌ لا واحد** — وأولُ قياسٍ بحث عن اسمَين
         *   فأخرج «صفرٌ محروس» وهما محروسان: `VisibilityGuard` يحجب **بالحذفِ من
         *   الاستجابة** (أقوى من التقنيع) وهو ما تستعمله شاشتا الموظفين.
         *   ⇒ **قائمةُ أسماءِ حرّاسٍ ناقصةٌ تُخرج عُريًا وهميًّا.** */
        $g = (strpos($src, 'ems_sensitive_display') !== false
           || strpos($src, 'api_sensitive_value') !== false
           || strpos($src, 'VisibilityGuard') !== false
           || strpos($src, 'ems_log_sensitive_read') !== false);
        if ($g) { $guarded++; }
        $disp[] = $rel . ($g ? ' ✔' : ' ◆');
    }
}
echo "───────────────────────────────────────────────────────────────\n";
printf("④ أسطحٌ تعرض `monthly_salary`: %d · **منها يمرُّ بالحارس: %d**\n", count($disp), $guarded);
foreach ($disp as $d) { echo "   {$d}\n"; }
echo "◆ والإخفاءُ يسري **حيثُ يُنادى الحارس** — وما لم يُنادَ فيه يبقى كما كان\n";
echo "  **ويُعلَن هنا بعددِه لا يُطوى**. ووصلُه عملٌ تالٍ لا يُدَّعى إنجازُه الآن.\n";
