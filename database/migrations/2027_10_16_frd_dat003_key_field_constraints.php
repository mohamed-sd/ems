<?php
/**
 * 2027_10_16_frd_dat003_key_field_constraints.php
 *   FR-DAT-003 · CHG-DAT-BASELINE-01 — لا نصَّ بشريًّا في حقلِ مفتاح
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلب** (الدفتر · GAP-09 · P1): «صفرُ نصٍّ بشريٍّ في حقلِ مفتاح ·
 *   **وقيدٌ يمنع عودتَه**» · وسلوكُ الفشل: «**رفضٌ من القاعدةِ لا من الشاشة**».
 *
 * ◆ **والعطبُ مقيسٌ من هذه الجولة**: مرجعيّتانِ ماليتانِ حملتا **أسماءَ أشخاصٍ**
 *   في حقلِ اسمِ القاعدةِ برموزٍ مشوَّهةٍ `FIN_-000NN`. حُجرت بـ`active = 0`،
 *   **وبقي البابُ مفتوحًا لعودتِها** — فالحجرُ يعالج ما وقع لا ما سيقع.
 *
 * ◆ **والقيدُ يسمح للمحجورِ بالبقاء**: `active = 0 OR <النمط>` — فلا يُكسر
 *   الأثرُ التدقيقيُّ لما حُجر، **ولا يُقبل صفٌّ نشطٌ برمزٍ خارجَ النمط**.
 *   وهذا هو الفرقُ بين قيدٍ يُطبَّق وقيدٍ يُرفَض عند الإضافة.
 *
 * ◆ **والنمطُ يُشتقُّ من الرموزِ المشروعةِ نفسِها** لا يُخترَع:
 *   `FS-01..FS-16` · `EC-01..EC-08` · `FC-01..FC-10` ⇒ حرفانِ كبيرانِ فشرطةٌ
 *   فرقمان.
 *
 * التشغيل:  php database/migrations/2027_10_16_frd_dat003_key_field_constraints.php
 * الرجوع :  php database/migrations/2027_10_16_frd_dat003_key_field_constraints.php --revert
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

/* الجدول => [العمودُ المفتاح, اسمُ القيد] */
$KEYS = array(
    'fin_signal_rules'   => array('signal_code', 'chk_signal_code_shape'),
    'fin_contract_types' => array('type_code',   'chk_contract_type_code_shape'),
);

if (in_array('--revert', $argv, true)) {
    foreach ($KEYS as $tbl => $k) {
        $conn->query("ALTER TABLE `{$tbl}` DROP CONSTRAINT `{$k[1]}`");
    }
    echo "↺ أُسقطت قيودُ شكلِ حقلِ المفتاح\n";
    exit(0);
}

$SHAPE = "^[A-Z]{2}-[0-9]{2}$";
$made = 0; $skip = 0;
foreach ($KEYS as $tbl => $k) {
    list($col, $name) = $k;

    /* ① أيُّ صفٍّ **نشطٍ** يخالف النمطَ يمنع إضافةَ القيد — يُعرَض ولا يُخفى */
    $bad = 0;
    $q = $conn->query("SELECT COUNT(*) FROM `{$tbl}`
                        WHERE `active` = 1 AND `{$col}` NOT REGEXP '{$SHAPE}'");
    if ($q) { $bad = (int) $q->fetch_row()[0]; }
    if ($bad > 0) {
        echo "  ⛔ {$tbl}: **{$bad} صفًّا نشطًا يخالف النمط** — لا يُضاف قيدٌ فوقَ مخالفةٍ حيّة\n";
        $r = $conn->query("SELECT `{$col}` FROM `{$tbl}`
                            WHERE `active` = 1 AND `{$col}` NOT REGEXP '{$SHAPE}' LIMIT 5");
        while ($r && $x = $r->fetch_row()) { echo "      · {$x[0]}\n"; }
        continue;
    }

    $exists = 0;
    $q = $conn->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '{$name}'");
    if ($q) { $exists = (int) $q->fetch_row()[0]; }
    if ($exists) { $skip++; echo "  = {$tbl}.{$col}: القيدُ قائمٌ سلفًا\n"; continue; }

    /* ② القيدُ يسمح للمحجورِ بالبقاء ويمنع النشطَ المخالف */
    $sql = "ALTER TABLE `{$tbl}` ADD CONSTRAINT `{$name}` CHECK "
         . "(`active` = 0 OR `{$col}` REGEXP '{$SHAPE}')";
    if ($conn->query($sql)) {
        $made++;
        echo "  ✔ {$tbl}.{$col}: قيدُ الشكلِ أُضيف — والمحجورُ يبقى\n";
    } else {
        echo "  ✘ {$tbl}.{$col}: {$conn->error}\n";
    }
}
printf("\n① قيودٌ أُضيفت: %d · قائمةٌ سلفًا: %d\n", $made, $skip);

/* ── ③ التحقُّقُ الحيّ: أيرفض القيدُ نصًّا بشريًّا فعلًا؟ ──────────────────── */
$probe = 'اسمُ شخصٍ اختباريّ';
$rejected = false;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn->query("INSERT INTO `fin_signal_rules`
        (`company_id`,`signal_code`,`name_ar`,`operator`,`streak_periods`,`severity`,`active`,`created_at`)
        VALUES (4,'{$probe}','probe','lte',1,'متوسط',1,NOW())");
} catch (\Throwable $t) {
    $rejected = true;
}
mysqli_report(MYSQLI_REPORT_OFF);
$conn->query("DELETE FROM `fin_signal_rules` WHERE `signal_code` = '{$probe}'");
printf("② التحقُّقُ الحيّ: نصٌّ بشريٌّ في حقلِ مفتاحٍ نشطٍ ⇒ **%s**\n",
       $rejected ? 'رفضٌ من القاعدة ✔' : 'مرَّ ✘ — والقيدُ لا يعمل');
if (!$rejected) { exit("⛔ القيدُ لا يمنع — أُوقِف قبلَ إعلانِ نجاح\n"); }

ems_migration_recorded(__FILE__, $conn, 0);
