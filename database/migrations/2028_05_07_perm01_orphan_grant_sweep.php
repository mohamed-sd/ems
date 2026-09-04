<?php
/**
 * 2028_05_07_perm01_orphan_grant_sweep.php — كنسُ المنحِ اليتيمةِ وتقاعدُ ما خلا
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01 §6-⑥: «منحٌ نافذةٌ لحسابٍ محذوفٍ أو معلَّق — **تُسحَب بأثرٍ مسجَّل ولا
 * تُحذف** · وإن أُعيد الحسابُ لا تعود المنحةُ تلقائيًّا بل بإعادةِ تحقّق».
 *
 * ◆ **والسحبُ لا الحذف**: الحذفُ يُفقد القرارَ ويترك السجلَّ مجهولًا صامتًا؛
 *   والسحبُ يُبقي الصفَّ بسببِه فيُقرأ لاحقًا ويُعرف متى وقع ولماذا.
 *
 * ◆ **وكشفَه التحوّلُ الكامل**: بعدَ نقلِ الخمسةِ والسبعين بقيت ثلاثةُ قوالبَ
 *   نافذةً بلا حاملٍ **حيّ** — لأنَّ حامليها أربعةٌ غيرُ أحياء (حسابٌ في الشركةِ
 *   المُعلَّقةِ وثلاثةٌ محذوفون). فالقالبُ النافذُ بلا حاملٍ حيٍّ سطحُ خطرٍ ساكن.
 *
 * ⛔ **ولا يُمَسُّ حسابٌ حيّ**: الشرطُ على الحاملِ لا على القالب.
 *
 * التشغيل: php database/migrations/2028_05_07_perm01_orphan_grant_sweep.php
 * العكس:   php database/migrations/2028_05_07_perm01_orphan_grant_sweep_down.php
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
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$LIVE = "u.is_deleted = 0 AND u.status = 'active' AND u.company_id = 4";

$stampFile = __DIR__ . '/2028_05_07_perm01_orphan_grant_sweep.watermark.json';
if (is_file($stampFile)) {
    $stamp = json_decode((string) file_get_contents($stampFile), true);
    echo "علامةُ ماءٍ سابقةٌ محفوظة — تُقرأ ولا تُدهَس.\n";
} else {
    $stamp = array('migration' => basename(__FILE__), 'revoked' => array(), 'retired' => array(),
                   'first_run_at' => gmdate('Y-m-d H:i:s') . ' UTC', 'runs' => 0);
    $q = $conn->query("SELECT g.grant_id, g.user_id, g.reason FROM gov_authority_grants g
                         JOIN users u ON u.id = g.user_id
                        WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
                          AND NOT({$LIVE})");
    while ($x = $q->fetch_assoc()) { $stamp['revoked'][] = $x; }
    $q = $conn->query("SELECT profile_id FROM gov_role_profiles p WHERE p.state = 'active'
                        AND NOT EXISTS(SELECT 1 FROM gov_authority_grants g
                                         JOIN users u ON u.id = g.user_id AND {$LIVE}
                                        WHERE g.profile_id = p.profile_id AND g.revoked_at IS NULL
                                          AND (g.valid_to IS NULL OR g.valid_to > NOW()))");
    while ($x = $q->fetch_row()) { $stamp['retired'][] = (int) $x[0]; }
    printf("علامةُ ماءٍ جديدة: منحٌ يتيمةٌ=%d · قوالبُ بلا حاملٍ حيٍّ=%d\n",
        count($stamp['revoked']), count($stamp['retired']));
}

echo "\n══ ① سحبُ المنحِ اليتيمة ═══════════════════════════════════════════════\n";
$q = $conn->query("SELECT g.grant_id, u.id, u.username, u.status, u.is_deleted, u.company_id
                     FROM gov_authority_grants g JOIN users u ON u.id = g.user_id
                    WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
                      AND NOT({$LIVE})");
$n = 0;
while ($x = $q->fetch_assoc()) {
    printf("   منحة #%-4s ⇐ مستخدم #%-5s %-26s (حالة=%s · محذوف=%s · شركة=%s)\n",
        $x['grant_id'], $x['id'], mb_substr((string) $x['username'], 0, 24),
        $x['status'], $x['is_deleted'], $x['company_id']);
    $n++;
}
$conn->query("UPDATE gov_authority_grants g
                JOIN users u ON u.id = g.user_id
                 SET g.revoked_at = NOW(),
                     g.reason = CONCAT(g.reason, ' | سُحبت: حاملها غير حي — PERM-01 §6-6')
               WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
                 AND NOT({$LIVE})");
printf("   سُحبت: %d\n", $conn->affected_rows);

echo "\n══ ② تقاعدُ القوالبِ التي خلت من حاملٍ حيّ ═════════════════════════════\n";
$q = $conn->query("SELECT profile_code, title_ar FROM gov_role_profiles p WHERE p.state = 'active'
                    AND NOT EXISTS(SELECT 1 FROM gov_authority_grants g
                                     JOIN users u ON u.id = g.user_id AND {$LIVE}
                                    WHERE g.profile_id = p.profile_id AND g.revoked_at IS NULL
                                      AND (g.valid_to IS NULL OR g.valid_to > NOW()))");
while ($x = $q->fetch_assoc()) { printf("   %-10s %s\n", $x['profile_code'], mb_substr((string) $x['title_ar'], 0, 30)); }
$conn->query("UPDATE gov_role_profiles p SET p.state = 'retired'
               WHERE p.state = 'active'
                 AND NOT EXISTS(SELECT 1 FROM gov_authority_grants g
                                  JOIN users u ON u.id = g.user_id AND {$LIVE}
                                 WHERE g.profile_id = p.profile_id AND g.revoked_at IS NULL
                                   AND (g.valid_to IS NULL OR g.valid_to > NOW()))");
printf("   تقاعدت: %d\n", $conn->affected_rows);

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;
$orph = (int) $one("SELECT COUNT(*) FROM gov_authority_grants g JOIN users u ON u.id = g.user_id
                     WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
                       AND NOT({$LIVE})");
printf("   منحٌ نافذةٌ لغيرِ الأحياء: %d %s\n", $orph, $orph === 0 ? '' : 'باقٍ');
if ($orph !== 0) { $good = false; }

$empty = (int) $one("SELECT COUNT(*) FROM gov_role_profiles p WHERE p.state = 'active'
                      AND NOT EXISTS(SELECT 1 FROM gov_authority_grants g
                                       JOIN users u ON u.id = g.user_id AND {$LIVE}
                                      WHERE g.profile_id = p.profile_id AND g.revoked_at IS NULL
                                        AND (g.valid_to IS NULL OR g.valid_to > NOW()))");
printf("   قالبٌ نافذٌ بلا حاملٍ حيّ: %d %s\n", $empty, $empty === 0 ? '' : 'باقٍ');
if ($empty !== 0) { $good = false; }

$multiSrc = (int) $one("SELECT COUNT(*) FROM (
          SELECT i.profile_id FROM gov_profile_items i
            JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
           GROUP BY i.profile_id HAVING COUNT(DISTINCT i.seeded_from) > 1) z");
printf("   قالبٌ نافذٌ مبذورٌ من أكثرَ من مصدر: %d %s\n", $multiSrc, $multiSrc === 0 ? '' : 'باقٍ');
if ($multiSrc !== 0) { $good = false; }

$cov = (int) $one("SELECT COUNT(*) FROM users u WHERE {$LIVE} AND EXISTS(
          SELECT 1 FROM gov_authority_grants g
            JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
           WHERE g.user_id = u.id AND g.revoked_at IS NULL
             AND (g.valid_to IS NULL OR g.valid_to > NOW()))");
$liveN = (int) $one("SELECT COUNT(*) FROM users u WHERE {$LIVE}");
printf("   ضابطٌ موجب: المغطَّون %d من %d %s\n", $cov, $liveN, $cov === $liveN ? '' : 'انحرف');
if ($cov !== $liveN) { $good = false; }

$stamp['runs'] = (int) $stamp['runs'] + 1;
$stamp['last_run_at'] = gmdate('Y-m-d H:i:s') . ' UTC';
file_put_contents($stampFile, json_encode($stamp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — لا منحة يتيمة ولا قالب نافذ بلا حامل حي.\n" : "سقط شاهد — راجع اعلاه\n");
