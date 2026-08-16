<?php
/**
 * sec01_close_unguarded.php — إغلاقُ B2: شاشاتٌ مسجَّلةٌ تُصيَّر بلا حارس
 * ═══════════════════════════════════════════════════════════════════════════
 * القياس (ra05c): 7 شاشاتٍ مسجَّلةٍ في `modules` يفتحها **أيُّ مستخدمٍ مسجَّلِ
 * الدخول** لأنها لا تنادي حارسَ الصلاحيةِ البتة — لا `enforce_current_page_view_permission`
 * ولا `check_page_permissions`. فالتسجيلُ وحدَه لا يحرس؛ الحارسُ نداءٌ لا صفةٌ.
 *
 * ◆ يُحقن الحارسُ **بعد فحصِ الجلسةِ مباشرةً وقبلَ أيِّ تصيير** — فترتيبُ
 *   TS-08 يُلزم: جلسةٌ ثم إعدادٌ ثم حارسٌ ثم كتابة. وحقنُه بعدَ `inheader`
 *   يعني أن الرأسَ خرج للمتصفحِ قبلَ الرفض.
 * ◆ وعاطلٌ: ملفٌ فيه الحارسُ سلفًا يُترك كما هو.
 * ◆ ونسخةٌ احتياطيةٌ لكلِّ ملفٍ قبلَ لمسِه.
 *
 * التشغيل: php tools/sec01_close_unguarded.php [--apply]
 *          بلا --apply يعرض ما سيفعله ولا يكتب.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
$apply = in_array('--apply', $argv, true);

$TARGETS = array(
    'Employees/showcontractemployee.php',
    'Reports/contract_report.php',
    'Reports/contractall.php',
    'Reports/driverAndsupplerscontract.php',
    'Suppliers/showcontractsuppliers.php',
    'Suppliers/suppliers_details.php',
    'Timesheet/timesheet_type.php',
);

$GUARD = <<<'PHP'

/* ── حارسُ الشاشة (B2) — الشاشةُ مسجَّلةٌ في `modules` وكانت تُفتح لأيِّ
   مستخدمٍ مسجَّلِ الدخول لأنها لا تنادي الحارس. والتسجيلُ لا يحرس: الحارسُ
   نداءٌ لا صفة. وموضعُه هنا قبلَ أيِّ تصييرٍ — فرفضٌ بعدَ خروجِ الرأسِ ليس رفضًا. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
enforce_current_page_view_permission($conn, '../main/dashboard.php');
PHP;

echo "══ إغلاقُ B2: حقنُ حارسِ الشاشة ══\n";
echo $apply ? "◆ وضعُ التطبيق\n\n" : "◆ عرضٌ فقط — لا كتابة (أضف --apply)\n\n";

$done = 0; $skip = 0; $fail = 0;
foreach ($TARGETS as $rel) {
    $path = $ROOT . '/' . $rel;
    if (!is_file($path)) { printf("  ✘ %-46s غيرُ موجود\n", $rel); $fail++; continue; }
    $src = (string) file_get_contents($path);

    if (strpos($src, 'enforce_current_page_view_permission') !== false
        || strpos($src, 'check_page_permissions') !== false) {
        printf("  · %-46s الحارسُ قائمٌ سلفًا\n", $rel); $skip++; continue;
    }

    /* المرساة: كتلةُ فحصِ الجلسةِ المنتهيةُ بـexit — يُحقن بعدَها */
    if (!preg_match('/if\s*\(\s*!\s*isset\s*\(\s*\$_SESSION\[[\'"]user[\'"]\]\s*\)\s*\)\s*\{[^}]*?exit\s*\(\s*\)\s*;\s*\}/s', $src, $m, PREG_OFFSET_CAPTURE)) {
        printf("  ✘ %-46s لم تُوجد مرساةُ فحصِ الجلسة\n", $rel); $fail++; continue;
    }
    $at = $m[0][1] + strlen($m[0][0]);
    $new = substr($src, 0, $at) . $GUARD . substr($src, $at);

    if (!$apply) { printf("  ⟳ %-46s سيُحقن بعدَ الحرف %d\n", $rel, $at); $done++; continue; }

    $bak = $path . '.b2bak';
    if (!file_exists($bak)) { copy($path, $bak); }
    if (file_put_contents($path, $new) === false) { printf("  ✘ %-46s تعذّرت الكتابة\n", $rel); $fail++; continue; }

    /* لا يُترك ملفٌ مكسورٌ: فحصُ بناءٍ فوريٌّ ورجوعٌ عند الفشل */
    $out = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
    if ($rc !== 0) {
        copy($bak, $path);
        printf("  ✘ %-46s كُسر البناءُ فأُعيد الأصل: %s\n", $rel, implode(' ', $out));
        $fail++; continue;
    }
    printf("  ✔ %-46s حُقن الحارس\n", $rel);
    $done++;
}

echo "\n── الحصيلة ──\n";
printf("  %s: %d · قائمٌ سلفًا: %d · إخفاق: %d\n", $apply ? 'حُقن' : 'سيُحقن', $done, $skip, $fail);
if (!$apply) { echo "\n  شغّلها بـ--apply للتطبيق.\n"; }
exit($fail === 0 ? 0 : 1);
