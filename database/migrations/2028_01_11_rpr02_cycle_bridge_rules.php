<?php
/**
 * 2028_01_11_rpr02_cycle_bridge_rules.php — قيدُ الجسرِ يذكر القاعدةَ بالصنفِ لا بالاسم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس**: `chk_cyc_bridge` كان يكتب **اسمَ قاعدةٍ بعينِها** في
 *   نصِّه: «`bridge_rule = 'BASENAME_UNIQUE'` ⇔ `screen_id <> ''`». **ونيّتُه
 *   صحيحةٌ** — لا معرِّفَ لصفٍّ لم يُحسم — **لكنّه ثبَّت الحاضرَ قاعدةً**:
 *   فأيُّ قاعدةِ حسمٍ جديدةٍ **تُردُّ في القاعدةِ نفسِها** ولو كانت أصدقَ.
 *
 * ◆ **وقد وقع ذلك فعلًا**: `C4 · PATH_OR_SCOPE_RESOLVED` تحسم الملتبسَ **بشاهدٍ
 *   ثانٍ مقيسٍ لا بترجيح** (‏مسارٌ كاملٌ يطابق سطحًا واحدًا · أو إدارةُ الصفِّ
 *   تطابق مالكَ مرشَّحٍ واحدٍ لا غير) — **ولا يمكنها الكتابةُ** والقيدُ يسمّي
 *   `BASENAME_UNIQUE` وحدَها.
 *
 * ⇒ **فالقيدُ يُعاد إلى معناه**: **معرِّفٌ يوجب قاعدةَ حسمٍ · وقاعدةُ حسمٍ توجب
 *   معرِّفًا · وغيرُ المحسومِ يبقى فارغًا.** ⛔ **ولا يُلغى القيدُ** — فإلغاؤه
 *   يفتح البابَ لمعرِّفٍ في صفٍّ ملتبس، وهو العطبُ الذي وُضع لمنعِه.
 *
 * التشغيل: php database/migrations/2028_01_11_rpr02_cycle_bridge_rules.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);
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

@$conn->query("ALTER TABLE `gov_screen_cycle` DROP CONSTRAINT `chk_cyc_bridge`");
$ok = $conn->query("ALTER TABLE `gov_screen_cycle`
    ADD CONSTRAINT `chk_cyc_bridge` CHECK (
        (`bridge_rule` IN ('BASENAME_UNIQUE','PATH_OR_SCOPE_RESOLVED') AND `screen_id` <> '')
     OR (`bridge_rule` NOT IN ('BASENAME_UNIQUE','PATH_OR_SCOPE_RESOLVED') AND `screen_id` = ''))");
if (!$ok) { exit("✘ تعذّر القيد: {$conn->error}\n"); }
echo "  ✔ `chk_cyc_bridge` صار يذكر **قواعدَ الحسمِ** لا قاعدةً بعينِها\n";
echo "  ✔ والمعنى محفوظ: معرِّفٌ يوجب حسمًا · وحسمٌ يوجب معرِّفًا · وملتبسٌ يبقى فارغًا\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
