<?php
/**
 * 2028_05_14_perm01_tickets_identity.php — هويّةُ بابِ البلاغاتِ واحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01-DEC §2: «شاشةُ البلاغاتِ المدمجة: **لا يُقبل أن «لا يردّ أحدٌ يرى
 * الرابط»**. المعيار `DIRECT_URL_AUTH_MISMATCH = 0`».
 *
 * ◆ **الحالُ المقيس**: 33 صفَّ ملاحةٍ نافذًا مسارُه `Tickets/tickets_list.php`
 *   وهويّتُه `Tickets/dept_inbox.php` — وهي شاشةٌ دُمجت وصار ملفُّها **مُحوِّلًا**
 *   لا غير. فالقائمةُ تُظهر بهويّةٍ والبابُ يُنفِذ بأخرى.
 *   وكنتُ عددتُ ذلك «حميدًا لأنَّ البابَ أوسع» — ورفضه المالكُ نصًّا: الوصفُ
 *   ليس حكمًا، والمعيارُ صفرٌ لا «لا يضرّ».
 *
 * ⛔ **والنقلُ لا يُسقِط أحدًا — مقيسٌ لا مُقدَّر**: أدوارُ العرضِ على
 *   `tickets_list` **خمسةٌ وثلاثون** وعلى `dept_inbox` **ثلاثةٌ وثلاثون**،
 *   والفرقُ صفر: لا دورَ يملك الثانيةَ دونَ الأولى. ومن كان في قالبِه
 *   `dept_inbox` وحدَها كان **يُردُّ عند البابِ أصلًا** — فالنقلُ يزيل رابطًا
 *   ميّتًا لا وصولًا حيًّا.
 * ◆ **ولا يُمَسّ الملفُّ المُحوِّل**: يبقى ليعمل رابطٌ محفوظٌ قديم — الهجرةُ
 *   على **هويّةِ بندِ الملاحة** لا على الشاشة.
 *
 * التشغيل: php database/migrations/2028_05_14_perm01_tickets_identity.php
 * العكس:   php database/migrations/2028_05_14_perm01_tickets_identity_down.php
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

$DOOR = 'Tickets/tickets_list.php';
$OLD  = 'Tickets/dept_inbox.php';

echo "══ ① الضابطُ قبلَ النقل — أيفقد أحدٌ؟ ═════════════════════════════════\n";
$lose = $one("SELECT COUNT(DISTINCT rp.role_id) FROM role_permissions rp
                JOIN modules m ON m.id = rp.module_id
               WHERE m.code = '{$OLD}' AND rp.can_view = 1
                 AND rp.role_id NOT IN (SELECT rp2.role_id FROM role_permissions rp2
                       JOIN modules m2 ON m2.id = rp2.module_id
                      WHERE m2.code = '{$DOOR}' AND rp2.can_view = 1)");
printf("   أدوارٌ تملك القديمةَ دونَ الجديدة: %d %s\n", $lose, $lose === 0 ? '' : '✘ توقّف');
if ($lose !== 0) { exit("   ⛔ النقلُ يُسقِط وصولًا — لا يُنفَّذ.\n"); }

$doorId = $one("SELECT id FROM modules WHERE code = '{$DOOR}' LIMIT 1");
printf("   وحدةُ البابِ: #%d\n", $doorId);
if ($doorId <= 0) { exit("   ⛔ لا وحدةَ للباب — لا يُنفَّذ.\n"); }

echo "\n══ ② نقلُ هويّةِ بنودِ الملاحة ════════════════════════════════════════\n";
$before = $one("SELECT COUNT(*) FROM nav_items n JOIN modules m ON m.id = n.module_id
                 WHERE n.active = 1 AND n.route LIKE 'Tickets/tickets_list.php%'
                   AND m.code = '{$OLD}'");
printf("   بنودٌ تحمل الهويّةَ القديمة: %d\n", $before);
$conn->query("UPDATE nav_items n JOIN modules m ON m.id = n.module_id
                 SET n.module_id = {$doorId}
               WHERE n.active = 1 AND n.route LIKE 'Tickets/tickets_list.php%'
                 AND m.code = '{$OLD}'");
printf("   بنودٌ نُقلت: %d\n", $conn->affected_rows);

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;
$after = $one("SELECT COUNT(*) FROM nav_items n JOIN modules m ON m.id = n.module_id
                WHERE n.active = 1 AND n.route LIKE 'Tickets/tickets_list.php%'
                  AND m.code = '{$OLD}'");
printf("   ★★ بقي بندٌ بهويّةٍ مخالفةٍ لبابِه: %d %s\n", $after, $after === 0 ? '' : '✘');
if ($after !== 0) { $good = false; }

$now = $one("SELECT COUNT(*) FROM nav_items n WHERE n.active = 1
              AND n.route LIKE 'Tickets/tickets_list.php%' AND n.module_id = {$doorId}");
printf("   ★ بنودٌ صارت على هويّةِ بابِها: %d\n", $now);
if ($now < $before) { $good = false; }

/* ◆ والملفُّ المُحوِّلُ باقٍ — ولا تُمَسّ وحدتُه في السجل. */
$stillReg = $one("SELECT COUNT(*) FROM modules WHERE code = '{$OLD}'");
printf("   ★ وحدةُ الشاشةِ المدمجةِ باقيةٌ في السجلّ: %d\n", $stillReg);
if ($stillReg < 1) { $good = false; }

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — وباب البلاغات وقائمته على هوية واحدة.\n" : "سقط شاهد\n");
