<?php
/**
 * 2027_10_17_frd_sec005_phantom_policy_rulings.php
 *   FR-SEC-005 · CHG-SEC-SCOPE-01 — سياسةٌ بهدفٍ وهميٍّ لا تبقى معلَّقة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ بنصِّه** (الدفتر · GAP-12 · P2): «سياساتُ الحقولِ ذاتُ الهدفِ
 *   الوهميِّ **تُصحَّح أو تُلغى بقرار** — ولا تبقى معلَّقة» · ومعيارُ القبول
 *   «صفرُ سياسةٍ بهدفٍ غيرِ موجود» · وسالبُه «سياسةٌ بهدفٍ وهميٍّ ← رسوب».
 *
 * ◆ **والقياسُ يحكم على كلِّ واحدةٍ منفردةً لا على الستِّ جملةً**: الأربعةُ
 *   المُعلَنةُ جداولَ (`contract` · `equipment` · `financing` · `employee_card`)
 *   **لا وجودَ لأيٍّ منها** (0 من 4 مقيسًا)، والعمودُ `terms` لا أثرَ له في
 *   القاعدةِ كلِّها (0).
 *
 * ◆ **والسابقةُ الحاكمةُ تضع شرطَ التصحيح** (`2027_09_14_salary_policy_repoint`):
 *   يجوز التحويلُ حين يكون **«تنفيذَ نيّةٍ مكتوبةٍ لا اختراعَ سلطة»** — أي:
 *   ① العمودُ الحقيقيُّ **واحدٌ لا لبسَ فيه** · ② `allowed_roles_json` يسمّي
 *   أصحابَ الحقِّ سلفًا · ③ يوافقه سجلُّ التصنيفِ الثاني.
 *   ⇒ وما لم يجتمع الثلاثةُ فالتحويلُ **منحُ رؤيةٍ أو حجبُها** — وهو تغييرُ
 *   وصولٍ حيٍّ **لا يقرّره منفِّذ**. و§ثالثًا يمنع اختراعَ صلاحية.
 *
 * ◆ **ولا يُلغى ما يحمل نيّةً حيّةً**: إلغاءُ سياسةٍ طبّيةٍ «لأن عمودَها وهميّ»
 *   يمحو **الأثرَ الوحيدَ** الذي يقول إن بيانَ الموظفِ الطبيَّ مقيَّد — فيتحوّل
 *   دَينٌ مرئيٌّ إلى غيابٍ غيرِ مرئيّ. **وهذا أسوأُ من بقائِه.** فالحجرُ لمن
 *   لا هدفَ حقيقيَّ له أصلًا، والباقي **يُرفَع قرارًا بمرشَّحاتِه المقيسة**.
 *
 * ◆ **والحجرُ لا يُفتح به بابٌ**: عمودُ `status` يُضاف، **وشرطُ الحجرِ أن يكون
 *   الهدفُ غيرَ موجود** — فلا تُرفَع حمايةٌ عاملة. ويُفحص ذلك بعدَ الحجرِ لا قبلَه.
 *
 * التشغيل:  php database/migrations/2027_10_17_frd_sec005_phantom_policy_rulings.php
 * الرجوع :  php database/migrations/2027_10_17_frd_sec005_phantom_policy_rulings.php --revert
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

function cnt(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }

function col_exists(mysqli $c, $tb, $col)
{
    return cnt($c, "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = '" . $c->real_escape_string($tb) . "'
                       AND COLUMN_NAME = '" . $c->real_escape_string($col) . "'");
}

if (in_array('--revert', $argv, true)) {
    $conn->query("UPDATE `sensitive_field_policies` SET `status` = 'نافذة'");
    $conn->query("UPDATE `scr_sensitive_fields` SET `status` = 'معتمد' WHERE `status` = 'ملغاة'");
    $conn->query("ALTER TABLE `sensitive_field_policies` DROP COLUMN `status`");
    $conn->query("ALTER TABLE `sensitive_field_policies` DROP COLUMN `ruling_note`");
    $conn->query("DROP TABLE IF EXISTS `gov_phantom_policy_rulings`");
    echo "↺ رُدَّت السياساتُ نافذةً وأُسقط سجلُّ الأحكام\n";
    exit(0);
}

/* ══ ① العمودُ الذي يسمح بالحجرِ دونَ حذف ═══════════════════════════════ */
if (col_exists($conn, 'sensitive_field_policies', 'status') === 0) {
    $conn->query("ALTER TABLE `sensitive_field_policies`
                   ADD COLUMN `status` VARCHAR(16) NOT NULL DEFAULT 'نافذة'
                       COMMENT 'نافذة · ملغاة — الإلغاءُ بديلُ الحذف (§تاسعًا)',
                   ADD COLUMN `ruling_note` VARCHAR(400) NULL
                       COMMENT 'سببُ الحجرِ ومرجعُ حكمِه'");
    echo "① أُضيف عمودُ الحالةِ والحكم — **والحجرُ بديلُ الحذف**\n";
} else {
    echo "① عمودُ الحالةِ قائمٌ سلفًا\n";
}

/* ══ ② سجلُّ الأحكام ═══════════════════════════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_phantom_policy_rulings` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_register`  VARCHAR(64)  NOT NULL,
    `declared_target`  VARCHAR(160) NOT NULL,
    `ruling`           VARCHAR(32)  NOT NULL
        COMMENT 'NO_REAL_TARGET · SUPERSEDED · NEEDS_OWNER_DECISION',
    `candidates`       VARCHAR(600) NOT NULL COMMENT 'أعمدةٌ حقيقيةٌ مرشَّحةٌ — مقيسة',
    `candidate_count`  INT NOT NULL,
    `reason`           VARCHAR(600) NOT NULL,
    `owner`            VARCHAR(96)  NOT NULL,
    `ruled_at`         DATETIME     NOT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_target` (`declared_target`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='FR-SEC-005 — لا سياسةَ وهميةً بلا حكم'");

/* ══ ③ المرشَّحاتُ **تُقاس** لكلِّ هدفٍ وهميٍّ — لا تُفترَض ═════════════════
   ونطاقُ البحثِ: الجدولُ المُعلَنُ أو جمعُه أو ما يبدأ باسمِه — لا القاعدةُ
   كلُّها. فبحثٌ عامٌّ يُنتج مرشَّحاتٍ من كياناتٍ أخرى (`tre_pay_batches`
   لسياسةِ `employees`) فيُغري بتحويلٍ **يغيّر موضوعَ السياسةِ نفسَه**. */
$SEM = array(
    'contract.unit_price'        => array('unit_price'),
    'employees.bank_account'     => array('bank', 'iban', 'account_no'),
    'employees.medical_notes'    => array('medical'),
    'equipment.owner_entity'     => array('owner'),
    'financing.terms'            => array('terms', 'condition'),
    'employee_card.salary_block' => array('salary', 'wage'),
);
$rulings = array();
foreach ($SEM as $target => $keys) {
    list($tb, $col) = explode('.', $target, 2);
    $like = $conn->real_escape_string($tb);
    $pfx  = $like . '\\_%';
    $cand = array();
    foreach ($keys as $k) {
        $k = $conn->real_escape_string($k);
        $r = $conn->query("SELECT CONCAT(TABLE_NAME,'.',COLUMN_NAME)
                             FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND (TABLE_NAME = '{$like}'
                                   OR TABLE_NAME = CONCAT('{$like}','s')
                                   OR TABLE_NAME LIKE '{$pfx}')
                              AND COLUMN_NAME LIKE '%{$k}%'");
        while ($r && $x = $r->fetch_row()) { $cand[$x[0]] = true; }
    }
    $rulings[$target] = array_keys($cand);
}

$OWNER = 'مالكُ مجالِ الحوكمةِ والصلاحيات';
$ins = $conn->prepare(
  "INSERT INTO `gov_phantom_policy_rulings`
     (`source_register`,`declared_target`,`ruling`,`candidates`,`candidate_count`,
      `reason`,`owner`,`ruled_at`)
   VALUES (?,?,?,?,?,?,?,NOW())
   ON DUPLICATE KEY UPDATE `ruling`=VALUES(`ruling`), `candidates`=VALUES(`candidates`),
     `candidate_count`=VALUES(`candidate_count`), `reason`=VALUES(`reason`)");
if (!$ins) { exit("⛔ تعذّر إعدادُ الإدراج: " . $conn->error . "\n"); }

$SUPERSEDED_BY = 'employees.monthly_salary';
$quarantine = array(); $escalate = array();

echo "\n② الحكمُ على كلِّ هدفٍ **منفردًا** لا على الستِّ جملةً:\n";
foreach ($rulings as $target => $cand) {
    $reg = ($target === 'employee_card.salary_block') ? 'scr_sensitive_fields'
                                                      : 'sensitive_field_policies';
    $n = count($cand);
    if ($target === 'employee_card.salary_block') {
        $ruling = 'SUPERSEDED';
        $reason = 'نيّتُها — تقييدُ الأجرِ — نُفِّذت في عمودِها الحقيقيِّ '
                . $SUPERSEDED_BY . ' بهجرة 2027_09_14. فبقاؤُها تكرارٌ لا حماية، '
                . 'و«employee_card» شاشةٌ لا جدول.';
        $quarantine[] = $target;
    } elseif ($n === 0) {
        $ruling = 'NO_REAL_TARGET';
        $reason = 'لا جدولَ ولا عمودَ يقابلها في المخطَّطِ كلِّه — مقيسًا لا مفترَضًا. '
                . 'فلا نيّةَ حيّةً تُفقَد بحجرِها، ولا حمايةَ تُرفَع.';
        $quarantine[] = $target;
    } else {
        $ruling = 'NEEDS_OWNER_DECISION';
        $reason = 'بيانٌ حساسٌ حقيقيٌّ موجودٌ وغيرُ محميّ (' . $n . ' مرشَّحًا)، '
                . 'واختيارُ الهدفِ **منحُ رؤيةٍ أو حجبُها** — تغييرُ وصولٍ حيٍّ '
                . 'لا يقرّره منفِّذ. وشرطُ سابقةِ 2027_09_14 — مرشَّحٌ واحدٌ لا لبسَ '
                . 'فيه — غيرُ متحقِّق.';
        $escalate[] = $target;
    }
    $cs = implode(' · ', $cand);
    $ins->bind_param('ssssiss', $reg, $target, $ruling, $cs, $n, $reason, $OWNER);
    $ins->execute();
    printf("   %-30s %-22s مرشَّحات=%d\n", $target, $ruling, $n);
    foreach (array_slice($cand, 0, 4) as $cc) { echo "        · {$cc}\n"; }
}
$ins->close();

/* ══ ④ الحجرُ — وشرطُه أن يكون الهدفُ غيرَ موجودٍ فعلًا ═════════════════ */
echo "\n③ الحجرُ (لا حذف):\n";
foreach ($quarantine as $target) {
    list($tb, $col) = explode('.', $target, 2);
    if (col_exists($conn, $tb, $col) !== 0) {
        echo "   ⛔ {$target}: الهدفُ **موجودٌ فعلًا** — لا يُحجَب ما يحمي\n";
        continue;
    }
    if ($target === 'employee_card.salary_block') {
        $st = $conn->prepare("UPDATE `scr_sensitive_fields` SET `status` = 'ملغاة'
                               WHERE `table_name` = 'employee_card'
                                 AND `field_name` = 'salary_block'");
    } else {
        $note = 'FR-SEC-005 — حكمٌ في gov_phantom_policy_rulings';
        $st = $conn->prepare("UPDATE `sensitive_field_policies`
                                 SET `status` = 'ملغاة', `ruling_note` = ?
                               WHERE `field_code` = ?");
        if ($st) { $st->bind_param('ss', $note, $target); }
    }
    /* ◆ **والفشلُ الصامتُ أخطرُ من الفشل**: أوّلُ تشغيلٍ اخترع القيمةَ `محجورة`
     *   فردَّها `chk_scr_sensitive_fields_status` — **ولم يُطبَع شيءٌ ومضت
     *   الهجرةُ تُعلن ⑥ خضراءَ صفرًا**. والمفرداتُ المشروعةُ مكتوبةٌ في القاعدةِ
     *   نفسِها: `('مسودة','معتمد','ملغاة')` — و«ملغاة» هي لفظُ الدفترِ نفسُه
     *   («تُصحَّح أو **تُلغى** بقرار»)، فلا حاجةَ إلى اختراعِ لفظٍ سابع.
     * ⇒ **كلُّ تنفيذٍ يُفحَص مُرجَعُه، ويوقف بخطأِ القاعدةِ نفسِه.** */
    if (!$st) { exit("⛔ {$target}: تعذّر الإعداد — " . $conn->error . "
"); }
    if (!$st->execute()) { exit("⛔ {$target}: **رُفض التنفيذ** — " . $st->error . "
"); }
    if ($st->affected_rows === 0) { exit("⛔ {$target}: صفرُ صفٍّ تأثَّر — لا يُعلَن إلغاءٌ لم يقع
"); }
    echo "   ✔ {$target} أُلغيت ({$st->affected_rows})
";
    $st->close();
}

/* ══ ⑤ المصالحة — لا صفَّ فُقد، ولا محجورٍ يحمي ═════════════════════════ */
$tot  = cnt($conn, "SELECT COUNT(*) FROM `sensitive_field_policies`");
$act  = cnt($conn, "SELECT COUNT(*) FROM `sensitive_field_policies` WHERE `status` = 'نافذة'");
$held = cnt($conn, "SELECT COUNT(*) FROM `sensitive_field_policies` WHERE `status` = 'ملغاة'");
printf("\n④ سياسات: %d إجمالًا (**لم تُحذف**) · نافذة=%d · ملغاة=%d\n", $tot, $act, $held);
printf("⑤ محجورٌ بحكم: %d · **مرفوعٌ قرارًا لمالكِ المجال: %d**\n",
       count($quarantine), count($escalate));
foreach ($escalate as $e) { echo "     ⚑ {$e}\n"; }

$bad = 0;
$r = $conn->query("SELECT `field_code` FROM `sensitive_field_policies` WHERE `status` = 'ملغاة'");
while ($r && $x = $r->fetch_row()) {
    $parts = explode('.', $x[0], 2);
    if (count($parts) !== 2) { continue; }
    if (col_exists($conn, $parts[0], $parts[1]) > 0) { $bad++; }
}
printf("⑥ محجورٌ يحمي عمودًا قائمًا: **%d** %s\n", $bad, $bad === 0 ? '✔' : '✘');
if ($bad > 0) { exit("⛔ حجرٌ يرفع حمايةً عاملة — أُوقِف قبلَ إعلانِ نجاح\n"); }

ems_migration_recorded(__FILE__, $conn, 0);
