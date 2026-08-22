<?php
/**
 * 2027_10_21_frd_fin007_payment_sod.php
 *   FR-FIN-007 · CHG-FIN-INTEGRITY-01 — من يُنشئ أمرَ الدفعِ لا يصرفه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ بنصِّه** (الدفتر · P1 · وثيقة المالية §فصل الواجبات): «فصلُ
 *   المحاسبةِ عن الخزينة: **من يُنشئ أمرَ الدفعِ لا يصرفه**» · وسلوكُ الفشل
 *   «**رفضٌ بسببٍ مرمَّز**» · ومعيارُ القبول «**صفرُ دفعةٍ بفاعلٍ واحدٍ في
 *   الطرفَين**».
 *
 * ◆ **والمقيسُ اليوم**: 1118 دفعةً · **1113 منها `executed_by = created_by`**.
 *   أي أن الفاعلَ الواحدَ أنشأ وصرف في **99.6٪** منها.
 *
 * ◆ **والحارسُ التطبيقيُّ موجودٌ وموصول**: `ems_assert_not_self_approval` يُنادى
 *   في `Finance/payments_fin.php:86` ويردُّ بـ`GOV-PERM-403`. فالـ1113 **أثرٌ
 *   لما قبلَه** لا ثغرةٌ مفتوحةٌ اليوم. ولا يُقرأ ذلك تبرئةً: بيانٌ محاسبيٌّ
 *   بيدٍ واحدةٍ يبقى بيانًا بيدٍ واحدة.
 *
 * ◆ **والناقصُ ضمانُ القاعدة**: حارسٌ في شاشةٍ واحدةٍ يُلتفُّ عليه بمسارٍ آخر.
 *   فيُضاف قيدٌ يمنع الصرفَ بيدِ المُنشئِ **من القاعدةِ نفسِها** — حارسان.
 *
 * ◆ **ولا يُعاد كتابةُ التاريخ**: الـ1113 تُوسَم `PRE_SOD` صراحةً — مرئيّةً في
 *   المقامِ لا ممسوحة — ولا يُبدَّل فاعلٌ في صفٍّ مضى. §تاسعًا: التصحيحُ
 *   التاريخيُّ **وسمٌ** لا إعادةُ كتابة.
 *
 * التشغيل:  php database/migrations/2027_10_21_frd_fin007_payment_sod.php
 * الرجوع :  php database/migrations/2027_10_21_frd_fin007_payment_sod.php --revert
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
$CHK = 'chk_payment_two_hands';

if (in_array('--revert', $argv, true)) {
    $conn->query("ALTER TABLE `fin_payments` DROP CONSTRAINT `{$CHK}`");
    $conn->query("ALTER TABLE `fin_payments` DROP COLUMN `sod_state`");
    echo "↺ أُسقط قيدُ اليدَين وعمودُ حالتِه\n";
    exit(0);
}

/* ══ ① العدُّ قبلًا ══════════════════════════════════════════════════════ */
$total = cnt($conn, "SELECT COUNT(*) FROM `fin_payments`");
$exec  = cnt($conn, "SELECT COUNT(*) FROM `fin_payments` WHERE COALESCE(`executed_by`,0) > 0");
$same  = cnt($conn, "SELECT COUNT(*) FROM `fin_payments`
                      WHERE COALESCE(`executed_by`,0) > 0 AND `executed_by` = `created_by`");
printf("① قبل: دفعات=%d · منفَّذة=%d · **بفاعلٍ واحدٍ في الطرفَين=%d (%.1f٪)**\n",
       $total, $exec, $same, $exec ? 100 * $same / $exec : 0);

/* ══ ② الحارسُ التطبيقيُّ يُقاس لا يُفترَض ═══════════════════════════════ */
$scr = (string) @file_get_contents($ROOT . '/Finance/payments_fin.php');
$wired = (strpos($scr, 'ems_assert_not_self_approval') !== false
          && strpos($scr, "'fin_payments'") !== false);
printf("② الحارسُ التطبيقيُّ في `Finance/payments_fin.php`: %s\n",
       $wired ? '**موصولٌ ✔**' : '✘ غيرُ موصول');
if (!$wired) { exit("⛔ لا يُضاف قيدُ قاعدةٍ فوقَ حارسٍ تطبيقيٍّ مفقود — أُوقِف\n"); }

/* ══ ③ الوسمُ لا إعادةُ الكتابة ═════════════════════════════════════════ */
if (cnt($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_payments'
                   AND COLUMN_NAME = 'sod_state'") === 0) {
    $conn->query("ALTER TABLE `fin_payments`
                   ADD COLUMN `sod_state` VARCHAR(16) NOT NULL DEFAULT 'ENFORCED'
                       COMMENT 'ENFORCED · PRE_SOD — فصلُ الواجبات (FR-FIN-007)'");
    echo "③ ✔ أُضيف `sod_state`\n";
} else { echo "③ = `sod_state` قائمٌ سلفًا\n"; }

$conn->query("UPDATE `fin_payments` SET `sod_state` = 'PRE_SOD'
               WHERE COALESCE(`executed_by`,0) > 0 AND `executed_by` = `created_by`");
$marked = $conn->affected_rows;
printf("   وُسِم %d صفًّا **PRE_SOD** — مرئيًّا في المقامِ ولا فاعلَ بُدِّل\n", $marked);

/* ══ ④ القيدُ — يمنع اليدَ الواحدةَ من القاعدة ═══════════════════════════ */
if (cnt($conn, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '{$CHK}'") === 0) {
    $sql = "ALTER TABLE `fin_payments` ADD CONSTRAINT `{$CHK}` CHECK (
              `sod_state` = 'PRE_SOD'
              OR `executed_by` IS NULL
              OR `executed_by` = 0
              OR `created_by` IS NULL
              OR `executed_by` <> `created_by`)";
    if ($conn->query($sql)) { echo "④ ✔ قيدُ اليدَين أُضيف — **الرفضُ من القاعدةِ فوقَ حارسِ الشاشة**\n"; }
    else { exit("⛔ تعذّر إضافةُ القيد: " . $conn->error . "\n"); }
} else { echo "④ = القيدُ قائمٌ سلفًا\n"; }

/* ══ ⑤ التحقُّقُ الحيّ ═══════════════════════════════════════════════════ */
$co = cnt($conn, "SELECT `company_id` FROM `fin_payments` LIMIT 1");
$rejected = false; $err = '';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn->query("INSERT INTO `fin_payments`
        (`company_id`,`payment_no`,`direction`,`party_type`,`party_ref`,`amount`,
         `currency`,`state`,`created_by`,`executed_by`,`created_at`)
        VALUES ({$co},'PROBE-SOD','disbursement','supplier',1,1,'SDG','executed',7,7,NOW())");
} catch (\Throwable $t) { $rejected = true; $err = $t->getMessage(); }
mysqli_report(MYSQLI_REPORT_OFF);
$conn->query("DELETE FROM `fin_payments` WHERE `payment_no` = 'PROBE-SOD'");
printf("⑤ التحقُّقُ الحيّ: صرفٌ بيدِ المُنشئ ⇒ %s\n",
       $rejected ? '**رفضٌ من القاعدة ✔** — ' . mb_substr($err, 0, 54) : 'مرَّ ✘ — القيدُ لا يعمل');
if (!$rejected) { exit("⛔ القيدُ لا يمنع — أُوقِف قبلَ إعلانِ نجاح\n"); }

/* ══ ⑥ المصالحة ═════════════════════════════════════════════════════════ */
$after = cnt($conn, "SELECT COUNT(*) FROM `fin_payments`");
$pre   = cnt($conn, "SELECT COUNT(*) FROM `fin_payments` WHERE `sod_state` = 'PRE_SOD'");
$enf   = cnt($conn, "SELECT COUNT(*) FROM `fin_payments` WHERE `sod_state` = 'ENFORCED'");
$badEnf = cnt($conn, "SELECT COUNT(*) FROM `fin_payments`
                       WHERE `sod_state` = 'ENFORCED' AND COALESCE(`executed_by`,0) > 0
                         AND `executed_by` = `created_by`");
printf("\n⑥ بعد: دفعات=%d (%s) · PRE_SOD=%d · ENFORCED=%d · **مخالفٌ محكومٌ=%d**\n",
       $after, $after === $total ? '✔ لا فقد' : '✘ **فرق**', $pre, $enf, $badEnf);
if ($after !== $total || $badEnf !== 0) { exit("⛔ اختلَّ المقامُ أو بقي مخالفٌ محكوم\n"); }

ems_migration_recorded(__FILE__, $conn, 0);
