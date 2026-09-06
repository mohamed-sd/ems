<?php
/**
 * 2028_05_17_tickets_read_all_profiles_down.php — عكسُ منحِ قراءةِ البلاغات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يحذف **ما وسمته هذه الجولةُ وحدَها** (`seeded_from = tkt_read_all:2028_05_17`)
 *   — فلا يمسُّ صفًّا كان قائمًا قبلَها، ولا يخفّض رايةَ دورِ البلاغاتِ ولا
 *   الأدوارِ الخمسةِ التي كانت تملك الصندوقَ أصلًا.
 * ◆ والوسمُ هو الحدُّ الفاصل: لا يُحذف بمطابقةِ اسمِ الشاشةِ وحدَها.
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
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$MARK = 'tkt_read_all:2028_05_17';

$st = $conn->prepare("DELETE FROM gov_profile_items WHERE seeded_from = ?");
$st->bind_param('s', $MARK);
$st->execute();
printf("   صفوفٌ محذوفة: %d\n", $st->affected_rows);
$st->close();

$left = $one("SELECT COUNT(*) FROM gov_profile_items
               WHERE seeded_from = '" . $conn->real_escape_string($MARK) . "'");
printf("   المتبقّي بوسمِ الجولة: %d (المستهدف صفر)\n", $left);

/* والقائمُ قبلَ الجولةِ يبقى: الخمسةُ التي كانت تملك الصندوق. */
$keep = $one("SELECT COUNT(DISTINCT p.profile_id) FROM gov_profile_items i
               JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
              WHERE i.item_ref = 'Tickets/tickets_list.php' AND i.allow = 1");
printf("   قوالبُ تقرأ صندوقَ البلاغاتِ الآن: %d (كان 5 قبل الجولة)\n", $keep);
if ($left > 0) { exit("⛔ العكسُ لم يكتمل.\n"); }

$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` = '2028_05_17_tickets_read_all_profiles.php'");
echo "✔ عُكس.\n";
