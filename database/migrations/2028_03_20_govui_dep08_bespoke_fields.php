<?php
/**
 * 2028_03_20_govui_dep08_bespoke_fields.php — DEP-08 · أعمدةُ الأسطحِ الخاصّة
 * @migration-objects: columns for DEP-08 bespoke registers
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **سبعةُ أسطحٍ لا تشبه أختَها**: تمرينُ الاستعادةِ وسجلُّ المحاولاتِ الممنوعةِ
 *   وسجلُّ الكياناتِ والتراخيصُ والتفويضُ وأنماطُ التفعيلِ والأدوار — لكلٍّ
 *   جدولُ مجالِه، **وحقولُ ورقتِها تطلب ما لا نظيرَ له في المخزن**.
 *   والقاعدةُ نفسُها ([[iaf-field-closure]]): *«ما لا نظيرَ له يأخذ عمودًا»*،
 *   واسمُ الحقلِ في تعليقِ العمودِ حرفًا.
 *
 * ⛔ **وما لم يُضَف عمدًا** — كلُّ حقلٍ مشتقٍّ له مصدرٌ قائم: «عدد محاولات
 *   الفاعل بالقاعدة» يُحصى من `guard_denials` نفسِه، و«فتح مراجعة؟» من وجودِ
 *   صفٍّ في `gov_denial_reviews`، و«الشاشات المتاحة» و«الإدارات المتاحة»
 *   و«عدد الحسابات عليه» من `role_permissions` و`modules` و`users`.
 *   **فعمودٌ لمشتقٍّ يخلق مصدرَ حقيقةٍ ثانيًا** يتفرّق عن الأوّل.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$ADD = array(
    /* GOV-32 · تمرين الاستعادة ومحضره */
    array('dr_drills', 'drill_cycle',        "VARCHAR(40) NULL DEFAULT NULL", 'دورة التمرين'),
    array('dr_drills', 'rto_target_seconds', "INT UNSIGNED NULL DEFAULT NULL", 'RTO المستهدف'),
    array('dr_drills', 'data_integrity',     "VARCHAR(24) NULL DEFAULT NULL", 'سلامة البيانات المستعادة'),
    array('dr_drills', 'reviewed_by',        "INT NULL DEFAULT NULL", 'المراجع'),
    /* GOV-15 · سجل المحاولات الممنوعة */
    array('guard_denials', 'actor_role_id',  "SMALLINT UNSIGNED NULL DEFAULT NULL", 'صفة الفاعل وقتها'),
    array('guard_denials', 'owner_dept',     "VARCHAR(12) NULL DEFAULT NULL", 'الادارة المالكة'),
    array('guard_denials', 'request_source', "VARCHAR(24) NULL DEFAULT NULL", 'مصدر الطلب'),
    /* GOV-02 · سجل الشركات والكيانات */
    array('legal_entities', 'legal_rep',          "VARCHAR(190) NULL DEFAULT NULL", 'الممثل القانوني'),
    array('legal_entities', 'registered_capital', "DECIMAL(18,2) NULL DEFAULT NULL", 'راس المال المسجل'),
    array('legal_entities', 'reviewed_by',        "INT NULL DEFAULT NULL", 'المراجع'),
    /* GOV-06 · التفويض بالتوقيع */
    array('signing_authorities', 'sub_delegable', "TINYINT(1) NULL DEFAULT NULL", 'قابل للتفويض الفرعي'),
    array('signing_authorities', 'reviewed_by',   "INT NULL DEFAULT NULL", 'المراجع'),
    /* GOV-07 · التراخيص والكفالات */
    array('entity_licenses', 'contract_ref',   "VARCHAR(120) NULL DEFAULT NULL", 'العقد المربوط'),
    array('entity_licenses', 'is_critical',    "TINYINT(1) NULL DEFAULT NULL", 'مستند حرج'),
    array('entity_licenses', 'guard_rule_ref', "VARCHAR(120) NULL DEFAULT NULL", 'قاعدة المنع المفعلة'),
    array('entity_licenses', 'renewal_owner',  "INT NULL DEFAULT NULL", 'مسؤول التجديد'),
    array('entity_licenses', 'reviewed_by',    "INT NULL DEFAULT NULL", 'المراجع'),
    /* GOV-29 · أنماط تفعيل المزايا */
    array('governance_flags', 'activation_pattern', "VARCHAR(24) NULL DEFAULT NULL", 'نمط التفعيل'),
);

foreach ($ADD as $a) {
    list($t, $c, $type, $label) = $a;
    $q = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    if ($q && $q->num_rows) { echo "= {$t}.{$c} قائمٌ سلفًا\n"; continue; }
    if ($conn->query("ALTER TABLE `{$t}` ADD COLUMN `{$c}` {$type} COMMENT '{$label}'")) {
        echo "+ {$t}.{$c}\n";
    } else { echo "x {$t}.{$c}: " . $conn->error . "\n"; }
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
