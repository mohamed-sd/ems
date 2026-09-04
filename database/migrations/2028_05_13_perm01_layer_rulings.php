<?php
/**
 * 2028_05_13_perm01_layer_rulings.php — وسمُ طبقتَين بحكمٍ مسجَّل (ق-٣ · ق-٤)
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01 §8-② يقول في كلٍّ منهما: «**تُوصَل أو تُوسَم غيرَ نافذة**»، وحسمها
 * المالكُ في PERM-01-DEC-20260905: **تُوسَم لا تُوصَل**.
 *
 * ◆ **والوسمُ ليس إعدامًا**: كلتاهما **مقروءةٌ فعلًا** في قرارٍ آخر — قِيس:
 *   `permission_templates` تقرؤها 7 ملفّاتِ إنتاج (إدارةُ الصلاحيات · الحوكمة ·
 *   `PermissionResolver`)، و`gov_authority_limits` تقرؤها 13 عبرَ `ScopeEngine`
 *   (موجِّهُ القراراتِ التنفيذيّةِ ولوحاتُه). فالحكمُ يسمّي **مجالَ النفاذِ**
 *   ومجالَ عدمِه، ولا يقول «ميّتة».
 *
 * ⛔ **ولا تُحذف ولا تُوصَل**: نصُّ الأمر «لا تحذف. لا تدخل مسارَ فتحِ الشاشة».
 *   ووصلُها إلى فتحِ الشاشةِ يعيد المشكلةَ التي خرجنا منها: أكثرُ من مصدرِ
 *   حكمٍ للشاشةِ الواحدة.
 * ◆ **والحكمُ سجلٌّ يُقرأ لا تعليقٌ في شيفرة**: مقياسا ⑮ و㉚ يقرآنِ هذا الجدولَ
 *   ويتحقّقانِ **أنَّ مسارَ القرارِ لا يذكر الطبقةَ** — فوسمٌ بلا امتثالٍ لا يُقبل.
 *
 * التشغيل: php database/migrations/2028_05_13_perm01_layer_rulings.php
 * العكس:   php database/migrations/2028_05_13_perm01_layer_rulings_down.php
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

echo "══ ① سجلُّ أحكامِ الطبقات ══════════════════════════════════════════════\n";
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `perm01_layer_ruling` (
    `layer_key` VARCHAR(64) NOT NULL COMMENT 'اسم الجدول او الطبقة المحكوم عليها',
    `ruling` ENUM('wired','not_in_force') NOT NULL
        COMMENT 'موصولة بقرار فتح الشاشة | غير نافذة فيه',
    `in_force_scope` VARCHAR(160) NOT NULL DEFAULT ''
        COMMENT 'المجال الذي هي نافذة فيه فعلا — يسمى ولا يترك فارغا',
    `not_in_force_scope` VARCHAR(160) NOT NULL DEFAULT '',
    `reason` VARCHAR(255) NOT NULL DEFAULT '',
    `doc_ref` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'مرجع الامر الذي حسمه',
    `decided_by` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'من حسم — مالك او دور',
    `decided_at` DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`layer_key`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='PERM-01 8-2 — حكم مسجل لكل طقة صلاحيات: توصل ام توسم'");
echo '   perm01_layer_ruling: ' . ($ok ? 'قائم' : $conn->error) . "\n";

echo "\n══ ② الحكمان ═════════════════════════════════════════════════════════\n";
$rulings = array(
    array('permission_templates', 'not_in_force',
          'ادارة الصلاحيات وشاشة الحوكمة وخدمات الامن (7 ملفات انتاج تقرأها)',
          'قرار فتح الشاشة',
          'قوالب الادوار هي الحاكم في فتح الشاشة، وادخال طبقة ثالثة يعيد تعدد مصادر الحكم للشاشة الواحدة'),
    array('gov_authority_limits', 'not_in_force',
          'مجال التنفيذ عبر ScopeEngine (13 ملف انتاج: ExecDecisionRouter و w15_view وغيرها)',
          'قرار فتح الشاشة',
          'ضوابط نافذة في مجال التنفيذ لا في فتح الشاشة — والوسم يسمي مجالها ولا يعدمها'),
);
$st = $conn->prepare("INSERT INTO perm01_layer_ruling
        (layer_key, ruling, in_force_scope, not_in_force_scope, reason, doc_ref, decided_by)
     VALUES (?,?,?,?,?,'PERM-01-DEC-20260905','مالك النظام')
     ON DUPLICATE KEY UPDATE ruling=VALUES(ruling), in_force_scope=VALUES(in_force_scope),
        not_in_force_scope=VALUES(not_in_force_scope), reason=VALUES(reason),
        doc_ref=VALUES(doc_ref), decided_by=VALUES(decided_by)");
foreach ($rulings as $r) {
    $st->bind_param('sssss', $r[0], $r[1], $r[2], $r[3], $r[4]);
    $st->execute();
    printf("   %-24s ⇐ %s · نافذة في: %s\n", $r[0], $r[1], mb_substr($r[2], 0, 46));
}
$st->close();

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;
$n = $one("SELECT COUNT(*) FROM perm01_layer_ruling WHERE ruling='not_in_force'");
printf("   ★ أحكامٌ مسجَّلة: %d (المتوقَّع 2)\n", $n);
if ($n < 2) { $good = false; }

$blank = $one("SELECT COUNT(*) FROM perm01_layer_ruling
                WHERE in_force_scope='' OR not_in_force_scope='' OR reason='' OR doc_ref=''");
printf("   ★ ضابطٌ سالب — حكمٌ بلا مجالٍ أو سببٍ أو مرجع: %d %s\n", $blank, $blank === 0 ? '' : '✘');
if ($blank !== 0) { $good = false; }

/* ⛔ **والامتثالُ يُقاس لا يُوعَد** — لكن **بجسمِ دالّةِ القرارِ وحدَها**:
     `includes/permissions_helper.php` يضمُّ دوالَّ تقاريرَ وإحصاءٍ تذكر
     `permission_templates` بحقّ (لوحاتُ الحوكمةِ تعُدُّها) — فمسحُ الملفِّ كلِّه
     يُبلِّغ خرقًا حيث لا خرق. والمقصودُ: أن لا تدخل الطبقةُ **قرارَ فتحِ
     الشاشة**، أي جسمَ `get_module_permissions`. */
$helper = (string) @file_get_contents($ROOT . '/includes/permissions_helper.php');
$decisionBody = '';
$posFn = strpos($helper, 'function get_module_permissions(');
if ($posFn !== false) {
    /* نهايةُ الدالّةِ: أوّلُ تعريفِ دالّةٍ تاليةٍ في العمودِ صفر. */
    $posEnd = strpos($helper, "\nfunction ", $posFn + 10);
    $decisionBody = substr($helper, $posFn, ($posEnd === false ? strlen($helper) : $posEnd) - $posFn);
}
printf("   ◆ جسمُ دالّةِ القرارِ مقروءٌ: %s حرفًا\n", number_format(strlen($decisionBody)));
if ($decisionBody === '') { $good = false; echo "      ✘ تعذّر عزلُ جسمِ الدالّة\n"; }
foreach (array('permission_templates', 'gov_authority_limits') as $t) {
    $hit = ($decisionBody !== '' && strpos($decisionBody, $t) !== false);
    printf("   ★★ قرارُ فتحِ الشاشةِ لا يقرأ %-22s : %s\n", $t, $hit ? '✘ يقرؤها' : 'نعم');
    if ($hit) { $good = false; }
}

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — والطبقتان موسومتان بحكم مسجل ومجال مسمى.\n" : "سقط شاهد\n");
