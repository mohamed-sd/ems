<?php
/**
 * 2028_03_16_site15_invoice_ref.php — «المستند/الفاتورة» في صرفِ الموقعِ الميداني
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ `SITE-15` (`SCR-0855` · `operations/site_field_expense.php`) كان **17/18** —
 *   والناقصُ حقلٌ واحدٌ بعينِه: «المستند/الفاتورة» (`BUSINESS_INPUT`)، لا عمودَ
 *   له في `tre_petty_expense` ولا قيدَ في `gov_field_class`.
 *
 * ◆ **وهو آخرُ ما يُغلَق آليًّا في الأسطحِ المولَّدة**: بعده يبقى `MNT-10`
 *   «بنود أمر العمل» وحدَه، وهو **ليس نقصَ حقلٍ بل قرارُ مالك** — عقدُ `u13`
 *   بلا مفتاحِ ابنٍ، وقاعدةُ السطحِ نفسِه منصوصةٌ فيه: «الأمرُ يتبع تشخيصًا
 *   مقيَّدًا — **وعمالتُه تفصَّل في سطحِها لا في سطرِه**».
 *
 * ⛔ والعمودُ `NULL`-able بلا افتراضٍ فلا يمسُّ صفًّا قائمًا.
 *
 * @migration-objects: col:tre_petty_expense.invoice_ref
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

$SCREEN = 'site_field_expense';
$TABLE  = 'tre_petty_expense';
$KEY    = 'invoice_ref';
$LABEL  = 'المستند/الفاتورة';

$q = $conn->query("SHOW COLUMNS FROM `{$TABLE}` LIKE '{$KEY}'");
if (!$q || !$q->num_rows) {
    if ($conn->query("ALTER TABLE `{$TABLE}` ADD COLUMN `{$KEY}` VARCHAR(255) NULL")) {
        echo "أُضيف {$TABLE}.{$KEY}\n";
    } else { echo "⛔ " . $conn->error . "\n"; }
} else { echo "= العمودُ قائمٌ أصلًا\n"; }

$st = $conn->prepare("SELECT id, label_ar FROM gov_field_class WHERE screen_code = ? AND field_key = ?");
$st->bind_param('ss', $SCREEN, $KEY); $st->execute();
$row = $st->get_result()->fetch_assoc(); $st->close();
if ($row) {
    if ($row['label_ar'] !== $LABEL) {
        $up = $conn->prepare("UPDATE gov_field_class SET label_ar = ?, active = 1 WHERE id = ?");
        $up->bind_param('si', $LABEL, $row['id']); $up->execute(); $up->close();
        echo "صُحِّح وسمُ القيد\n";
    } else { echo "= القيدُ مطابقٌ أصلًا\n"; }
} else {
    $ins = $conn->prepare("INSERT INTO gov_field_class
        (company_id, screen_code, field_key, label_ar, dc_code, is_sensitive, active)
        VALUES (1, ?, ?, ?, 'DC-3', 0, 1)");
    $ins->bind_param('sss', $SCREEN, $KEY, $LABEL); $ins->execute(); $ins->close();
    echo "قُيِّد الحقلُ في gov_field_class\n";
}
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
