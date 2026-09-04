<?php
/**
 * 2028_05_15_perm01_retire_parallel_layer.php — تقاعدُ بياناتِ الطبقةِ الموازية (ق-٦)
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01-DEC ق-٦: «**لا تصالح. لا تملأ بأسماءِ شاشات**».
 *   · البيانات (9701 · 9507 · 9002) ⇒ **تُوسَم تاريخيّةً غيرَ نافذةٍ الآن**.
 *   · سجلُّ التدقيق ⇒ **يبقى للقراءة**.
 *   · المخطَّط (مفرداتُ الأفعال) ⇒ **لا يسقط**، ويُقيَّم في المرحلة (ب) من §8.
 *
 * ◆ **والسببُ مقيسٌ لا مُرتأى**: `effective_permissions.person_id` قيمُه
 *   **ليست في `users` ولا في `persons`** (مداه 52..435)، ومفرداتُ
 *   `permission_code` فيها **أفعالٌ** (`journal.post.x`) لا رموزَ شاشات.
 *   فملؤها بصلاحيّاتِ الشاشاتِ **جسرٌ مُختلَقٌ بين مفردتَين** — وهو أسوأُ من
 *   الفراغ، ولذلك امتنعتُ عنه ورفعتُه قرارًا فحُسم.
 *
 * ⛔ **ولا صفٌّ يُحذف**: التقاعدُ **وسمٌ** لا كنس. وحذفُ صفوفٍ لإقفالِ بندٍ هو
 *   «تسكيرُ بندٍ بمسِّ جدولٍ حيّ» الممنوعُ في §0-3.
 *
 * التشغيل: php database/migrations/2028_05_15_perm01_retire_parallel_layer.php
 * العكس:   php database/migrations/2028_05_15_perm01_retire_parallel_layer_down.php
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

if ($one("SELECT COUNT(*) FROM information_schema.TABLES
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='perm01_layer_ruling'") < 1) {
    exit("⛔ سجلُّ أحكامِ الطبقاتِ غيرُ قائم — شغّل 2028_05_13 أوّلًا.\n");
}

echo "══ ① الواقعُ المقيسُ قبلَ الوسم ════════════════════════════════════════\n";
$tot = $one("SELECT COUNT(*) FROM effective_permissions");
$orphan = $one("SELECT COUNT(*) FROM effective_permissions ep
                 WHERE NOT EXISTS(SELECT 1 FROM users u WHERE u.id = ep.person_id)
                   AND NOT EXISTS(SELECT 1 FROM persons pr WHERE pr.person_id = ep.person_id)");
$audit = $one("SELECT COUNT(*) FROM permission_audit_events");
printf("   صفوفُ الصلاحيّاتِ الفعّالة: %d · منها لا يُعرَف صاحبُه: %d\n", $tot, $orphan);
printf("   سجلُّ التدقيق (يبقى للقراءة): %d صفًّا\n", $audit);

echo "\n══ ② الأحكامُ الثلاثة ═════════════════════════════════════════════════\n";
$rulings = array(
    array('effective_permissions', 'not_in_force',
          'لا مجال — طبقة تاريخية بمعرفات لا تقابل سجل مستخدمين ولا اشخاص',
          'قرار فتح الشاشة وكل قرار حي',
          'معرفات اشخاصها (9701 و9507 و9002) خارج users و persons، ومفرداتها افعال لا شاشات — والمصالحة قرار مؤجل الى المرحلة ب'),
    array('permission_audit_events', 'not_in_force',
          'القراءة والتدقيق التاريخي — يبقى ولا يسقط',
          'قرار فتح الشاشة',
          'سجل وقائع تاريخي يقرأ ولا يحكم — وفيه 371 حدث كسر زجاج سابق لوصل الالية'),
    array('perm01_action_vocabulary', 'not_in_force',
          'مؤجل الى المرحلة ب من PERM-01 §8',
          'هذه الدفعة',
          'مخطط طبقة الافعال لا يسقط ولا ينفذ في هذه الدفعة — قرار مالك صريح'),
);
$st = $conn->prepare("INSERT INTO perm01_layer_ruling
        (layer_key, ruling, in_force_scope, not_in_force_scope, reason, doc_ref, decided_by)
     VALUES (?,?,?,?,?,'PERM-01-DEC-20260905 ق-٦','مالك النظام')
     ON DUPLICATE KEY UPDATE ruling=VALUES(ruling), in_force_scope=VALUES(in_force_scope),
        not_in_force_scope=VALUES(not_in_force_scope), reason=VALUES(reason),
        doc_ref=VALUES(doc_ref), decided_by=VALUES(decided_by)");
foreach ($rulings as $r) {
    $st->bind_param('sssss', $r[0], $r[1], $r[2], $r[3], $r[4]);
    $st->execute();
    printf("   %-26s ⇐ %s\n", $r[0], $r[1]);
}
$st->close();

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;
$n = $one("SELECT COUNT(*) FROM perm01_layer_ruling WHERE doc_ref LIKE '%ق-٦%'");
printf("   ★ أحكامُ ق-٦ المسجَّلة: %d (المتوقَّع 3)\n", $n);
if ($n < 3) { $good = false; }

/* ⛔ ولا صفَّ مُسَّ: الوسمُ ليس كنسًا. */
$totAfter = $one("SELECT COUNT(*) FROM effective_permissions");
$auditAfter = $one("SELECT COUNT(*) FROM permission_audit_events");
printf("   ★★ ضابطٌ ثابت — لم يُحذف صفٌّ: الفعّالة %d⇐%d · التدقيق %d⇐%d %s\n",
    $tot, $totAfter, $audit, $auditAfter,
    ($tot === $totAfter && $audit === $auditAfter) ? '' : '✘');
if ($tot !== $totAfter || $audit !== $auditAfter) { $good = false; }

$blank = $one("SELECT COUNT(*) FROM perm01_layer_ruling
                WHERE doc_ref LIKE '%ق-٦%' AND (reason='' OR not_in_force_scope='')");
printf("   ★ ضابطٌ سالب — حكمٌ بلا سببٍ أو مجال: %d %s\n", $blank, $blank === 0 ? '' : '✘');
if ($blank !== 0) { $good = false; }

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — والطبقة الموازية موسومة تاريخية بلا حذف صف واحد.\n" : "سقط شاهد\n");
