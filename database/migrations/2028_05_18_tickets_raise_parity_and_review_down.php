<?php
/**
 * 2028_05_18_tickets_raise_parity_and_review_down.php — عكسُ الإضافةِ والمراجعة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يعيد `can_add` إلى صفرٍ **في صفوفِ الجولةِ الموسومةِ وحدَها**، ويشطب قيدَ
 *   المراجعةِ لمصدرِها. فلا يمسُّ رايةَ دورِ البلاغاتِ ولا مصادرَ بذرٍ أخرى.
 * ⚠ **وبعدَ العكسِ يعود الحاجبُ ㉝ أحمرَ بـ31** — وهذا متوقَّعٌ لا عطب: العكسُ
 *   يعيد الحالةَ التي كانت بينَ الهجرتَين. والعكسُ الكاملُ يقتضي عكسَ
 *   `2028_05_17` بعدَه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$MARK = 'tkt_read_all:2028_05_17';

$st = $conn->prepare("UPDATE gov_profile_items SET can_add = 0
                       WHERE seeded_from = ? AND can_add = 1");
$st->bind_param('s', $MARK);
$st->execute();
printf("   صفوفٌ أُعيدت الى القراءة: %d\n", $st->affected_rows);
$st->close();

$st = $conn->prepare("DELETE FROM perm01_seed_source_review WHERE seeded_from = ?");
$st->bind_param('s', $MARK);
$st->execute();
printf("   قيودُ مراجعةٍ شُطبت: %d\n", $st->affected_rows);
$st->close();

$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` = '2028_05_18_tickets_raise_parity_and_review.php'");
echo "✔ عُكس — ويعود الحاجب 33 احمر بـ31 حتى يُعكَس 2028_05_17 أيضًا.\n";
