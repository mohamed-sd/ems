<?php
/**
 * 2027_11_19_repair01_w3_person_guard.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W03 — **قادحُ الكيانِ والمفتاحِ الأمِّ على سجلِّ الهوية**.
 *
 * ◆ **لماذا قادحٌ لا سطرٌ في وثيقة**: `DEC-OPEN-03` يقول «لا صفَّ في كيانٍ أمٍّ
 *   بلا `Company_ID`». والوثيقةُ لا تمنع صفًّا — وقد قِيس أنَّ `persons` جمع
 *   ١٠١ صفًّا بلا كيانٍ **البتة** لأنَّ المعالجَ يكتب `INSERT INTO persons
 *   (full_name)` وحدَه. فالمنعُ يُكتب حيث تقع الكتابة.
 *
 * ◆ **وطبقتان لا واحدة**: الشاشةُ تمنع قبلَ أن يصل (‏`sec_employee_wizard`)،
 *   والقادحُ يمنع ما لم يمرَّ بالشاشة — أداةً كان أو استيرادًا أو سطرَ SQL.
 *
 * ◆ **والمقامُ الحاكمُ الصفُّ المستعمَل** (`active = 1`): أربعةُ صفوفٍ محجورةٍ
 *   بلا سندِ كيانٍ (‏`W3-D-01`) تبقى في السجلِّ **دليلًا لا تُحذف** — والقادحُ
 *   لا يحرسها لأنّها ليست في المقام، ويحرس كلَّ صفٍّ يعود إلى الاستعمال.
 *
 * ◆ **و`person_class` يجعل الاستثناءَ مُعلَنًا لا صامتًا**: `WORKFORCE` يلزمه
 *   `employee_id` — فلا يُنشئ نطاقٌ معرّفًا بديلًا لإنسانٍ له مفتاحٌ أمّ؛
 *   و`IDENTITY_ONLY` صفُّ حسابٍ مُعلَنٌ لا إنسانَ في القوى خلفَه.
 *
 * ⚠ **يُشغَّل بعدَ `tools/repair01_w3_apply.php`** — القادحُ قبلَ الملءِ يمنع
 *   الملءَ نفسَه.
 *
 * التشغيل: php database/migrations/2027_11_19_repair01_w3_person_guard.php
 * التراجع: php database/migrations/2027_11_19_repair01_w3_person_guard_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ REPAIR01 · W03 — قادحُ سجلِّ الهوية ══\n\n";

/* الأساسُ يُقاس قبلَ الإنفاذ: قادحٌ على مقامٍ مخالفٍ يوقف النظامَ لا يحرسه */
$bad = $conn->query("SELECT COUNT(*) FROM persons WHERE active = 1 AND (company_id IS NULL OR company_id = 0)");
$bad = $bad ? (int) $bad->fetch_row()[0] : -1;
if ($bad !== 0) {
    echo "  ✘ صفٌّ مستعمَلٌ بلا كيانٍ: $bad — شغّلْ `php tools/repair01_w3_apply.php` أوّلًا\n";
    exit(1);
}
$badLink = $conn->query("SELECT COUNT(*) FROM persons
                          WHERE active = 1 AND person_class = 'WORKFORCE'
                            AND (employee_id IS NULL OR employee_id = 0)");
$badLink = $badLink ? (int) $badLink->fetch_row()[0] : -1;
if ($badLink !== 0) { echo "  ✘ صفُّ قوًى بلا وصلٍ بالمفتاحِ الأمّ: $badLink\n"; exit(1); }
echo "  ✔ الأساس: صفرُ مخالفٍ قبلَ الإنفاذ\n";

/* ⚠ **ولماذا `CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER` وتسجيلُ
   الثنائيِّ مُفعَّل، فـ`CREATE TRIGGER` يُردّ. وضبطُ `log_bin_trust_function_creators`
   أو تعطيلُ binlog **دليلٌ مُغلق** (‏ENG-01 PR-01 · BARRIER-4) فلا يُفتح لأجلِ
   حاجزٍ واحد. و`CHECK` يعطي الإنفاذَ نفسَه على الإدراجِ والتعديلِ معًا بلا امتياز —
   وحدُّه أنّه لا يقرأ جدولًا آخر، وشرطا هذه المرحلةِ داخلَ الصفِّ نفسِه. */
$err = 0;
foreach (array('trg_persons_w3_bi', 'trg_persons_w3_bu') as $t) { $conn->query("DROP TRIGGER IF EXISTS `$t`"); }

$checks = array(
    'chk_persons_w3_company' =>
        "NOT (`active` = 1 AND (`company_id` IS NULL OR `company_id` = 0))",
    'chk_persons_w3_master_key' =>
        "NOT (`active` = 1 AND `person_class` = 'WORKFORCE' AND (`employee_id` IS NULL OR `employee_id` = 0))",
);
foreach ($checks as $name => $expr) {
    $conn->query("ALTER TABLE `persons` DROP CONSTRAINT `$name`");   /* عاديّةُ التشغيل */
    if ($conn->query("ALTER TABLE `persons` ADD CONSTRAINT `$name` CHECK ($expr)") === true) {
        echo "  ✔ قيدُ $name\n";
    } else { echo "  ✘ $name — " . $conn->error . "\n"; $err++; }
}

/* جسٌّ وظيفيّ — `information_schema.TRIGGERS` تحتاج امتيازًا لا يملكه مستخدمُ
   التطبيق (‏M-00)، فالإثباتُ سلوكٌ لا سطرُ بيانات. */
$conn->query('START TRANSACTION');
$probe = @$conn->query("INSERT INTO persons (full_name, active, person_class) VALUES ('__w3_migr_probe__', 1, 'IDENTITY_ONLY')");
$conn->query('ROLLBACK');
echo '  ' . ($probe === false ? '✔' : '✘') . " الجسُّ الوظيفيّ: إدراجُ صفٍّ بلا كيانٍ "
   . ($probe === false ? 'مُنع' : '**مرّ — القادحُ لا يعمل**') . "\n";
if ($probe !== false) { $err++; }

echo "\n" . ($err === 0 ? "الحكم: تمّت ✔\n" : "الحكم: أخطاء ✘\n");
$conn->close();
exit($err === 0 ? 0 : 1);
