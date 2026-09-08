<?php
/**
 * 2028_06_18 — ربطُ حسابِ المراجعةِ بموظّفٍ · وشرطُ الدخولِ يُستوفى
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ المقيسُ بتسجيلِ دخولٍ حقيقيٍّ عبر HTTP** (لا محاكاة):
 *   `POST /login.php` ردَّ **«هذا الحساب غير مرتبط بموظف»** — قاعدةٌ منصوصةٌ
 *   في `login.php:105`: «لا حساب يعمل بلا موظف مُسنَد له (عدا المدير الأعلى)».
 *   ⇒ فالسايدبارُ يُصيَّر 267 بندًا في المحاكاة **والدخولُ مستحيلٌ أصلًا**.
 *   وهذا عينُ درسِ البيت: **الصفُّ في الجدولِ ليس عملًا حتّى يُجرَّب حيًّا**.
 *
 * ◆ **وموظّفٌ مخصَّصٌ لا انتحالُ إنسان**: يُنشأ سجلٌّ مُعلَّمٌ باسمِه أنّه
 *   حسابُ نظامٍ للمراجعةِ، فلا يُنسَب عملُ المراجعةِ إلى موظّفٍ حقيقيّ،
 *   ولا يختلط بسجلّاتِ القوى العاملة. و`users.employee_id` **فريدٌ** فلا
 *   يُشارك الحسابُ موظّفَ غيرِه.
 * التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
$one = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };

const EMP_NAME = 'حساب نظام — مراجعة الشاشات';

echo "── ربطُ حسابِ المراجعةِ بموظّف ──\n";
$uid = (int) $one("SELECT id FROM users WHERE username='مراجعة الشاشات'");
if (!$uid) { fwrite(STDERR, "لا حسابَ باسم «مراجعة الشاشات»\n"); exit(1); }

$cur = (int) $one("SELECT COALESCE(employee_id,0) FROM users WHERE id={$uid}");
if ($cur > 0) { echo "  الحسابُ مرتبطٌ سلفًا بالموظّف #{$cur}\n"; exit(0); }

$eid = (int) $one("SELECT id FROM employees WHERE name='" . EMP_NAME . "'");
if (!$eid) {
    $st = $conn->prepare("INSERT INTO employees (name, phone, company_id) VALUES (?,?,4)");
    $nm = EMP_NAME; $ph = '-';
    $st->bind_param('ss', $nm, $ph);
    if (!$st->execute()) { fwrite(STDERR, "إنشاءُ الموظّفِ فشل: {$conn->error}\n"); exit(1); }
    $eid = (int) $conn->insert_id;
    echo "  ✔ موظّفٌ #{$eid} أُنشئ — «" . EMP_NAME . "»\n";
} else { echo "  الموظّفُ #{$eid} قائم\n"; }

if (!$conn->query("UPDATE users SET employee_id={$eid} WHERE id={$uid}")) {
    fwrite(STDERR, "الربطُ فشل: {$conn->error}\n"); exit(1);
}
echo "  ✔ الحساب #{$uid} ⇐ الموظّف #{$eid}\n";

/* تحقُّقٌ: الشرطُ الذي ردَّ الدخولَ صار مستوفًى */
$chk = (int) $one("SELECT COALESCE(employee_id,0) FROM users WHERE id={$uid}");
if ($chk !== $eid) { fwrite(STDERR, "التحقُّقُ لم يطابق\n"); exit(1); }
echo "  ✔ شرطُ «لا حساب بلا موظّف» مستوفًى\n";
exit(0);
