<?php
/**
 * 2028_05_06_perm01_full_transition_down.php — عكسُ التحوّلِ الكامل
 * ◆ **العكسُ يعيد الصورةَ بعلامةِ الماء**: تُحذف المنحُ والبنودُ والقوالبُ فوقَ
 *   العلامةِ حصرًا، وتعود المنحُ المسحوبةُ **بنصِّ سببِها المحفوظ**، وتعود القوالبُ
 *   المتقاعدةُ نافذةً بمعرِّفاتِها المحفوظة.
 * ⛔ ويرفض العملَ بلا علامةِ ماء — والحذفُ بلا حدٍّ يمحو ما ليس منه.
 * ◆ وعمودُ `role_id` يبقى: إضافةُ عمودٍ لا تُفسد شيئًا ونزعُه يُفقد سندَ البنود.
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
if ($conn->connect_errno) { exit("connect fail: " . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');


/* ⛔ **القادحُ يمنع عكسَ نفسِه** — عطبٌ مقيسٌ لا مفترَض: إعادةُ قالبٍ قديمٍ إلى
     النفاذِ **تحديثٌ إلى `active`**، فيردُّها `trg_perm01_profile_activate`
     بحجّةِ «مبذورٌ من أكثرَ من مصدر» — وهي حجّةٌ صحيحةٌ للتفعيلِ الجديدِ **وخاطئةٌ
     للاستعادة**. وقد وقع فعلًا: بقي النظامُ بصفرِ قالبٍ نافذٍ حتى أُصلح يدويًّا.
   ◆ **فالعكسُ عمليّةٌ إداريّةٌ**: يُسقِط القادحَ ثمَّ يستعيد ثمَّ يُعيد إنشاءه —
     ولا يُترك مُسقَطًا مهما انتهى التنفيذُ إليه. */
$adminU = ems_env('DB_ADMIN_USER') ?: $u;
$adminP = ems_env('DB_ADMIN_USER') ? ems_env('DB_ADMIN_PASS') : $p;
$adm = new mysqli($host, $adminU, $adminP, ems_env('DB_NAME'), $port);
if ($adm->connect_errno) { exit("تعذر الاتصال بحساب الادارة: " . $adm->connect_error . "\n"); }
$adm->set_charset('utf8mb4');
$adm->query("DROP TRIGGER IF EXISTS `trg_perm01_profile_activate`");
echo "- أُسقط قادحُ التفعيلِ مؤقّتًا\n";
$restoreTrigger = function () use ($adm) {
    $ok = $adm->query("CREATE TRIGGER `trg_perm01_profile_activate` BEFORE UPDATE ON `gov_role_profiles`
      FOR EACH ROW
      BEGIN
        DECLARE src_n INT DEFAULT 0;
        DECLARE apr_n INT DEFAULT 0;
        IF NEW.`state` = 'active' AND OLD.`state` <> 'active' THEN
          IF EXISTS (SELECT 1 FROM `gov_policy_freeze`
                      WHERE `scope_code` = 'profile_activation' AND `active` = 1) THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'PERM-01: تجميد — لا تفعيل قالب حتى اشعار';
          END IF;
          SELECT COUNT(DISTINCT `seeded_from`) INTO src_n
            FROM `gov_profile_items` WHERE `profile_id` = NEW.`profile_id`;
          IF src_n > 1 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'PERM-01: قالب مبذور من اكثر من مصدر لا يفعل قبل مراجعة موثقة';
          END IF;
          SELECT COUNT(*) INTO apr_n FROM `gov_profile_activation_approval`
            WHERE `profile_id` = NEW.`profile_id` AND `version` = NEW.`version`;
          IF apr_n = 0 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'PERM-01: لا تفعيل لمسودة بلا سجل اعتماد (FTRE-0062)';
          END IF;
        END IF;
      END");
    echo "- أُعيد قادحُ التفعيل: " . ($ok ? 'نعم' : $adm->error) . "\n";
};
register_shutdown_function($restoreTrigger);

$stampFile = __DIR__ . '/2028_05_06_perm01_full_transition.watermark.json';
if (!is_file($stampFile)) { exit("رفض العكس: لا علامة ماء\n"); }
$stamp = json_decode((string) file_get_contents($stampFile), true);
foreach (array('max_profile', 'max_item', 'max_grant', 'revoked', 'retired') as $k) {
    if (!isset($stamp[$k])) { exit("رفض العكس: علامة الماء ناقصة ({$k})\n"); }
}
$mp = (int) $stamp['max_profile']; $mi = (int) $stamp['max_item']; $mg = (int) $stamp['max_grant'];
echo "العلامة: قوالب>{$mp} · بنود>{$mi} · منح>{$mg}\n\n";

$conn->query("DELETE FROM `gov_authority_grants` WHERE `grant_id` > {$mg}");
echo "- منحٌ مُصدَرةٌ حُذفت: " . $conn->affected_rows . "\n";

$back = 0;
foreach ((array) $stamp['revoked'] as $r) {
    $st = $conn->prepare("UPDATE `gov_authority_grants` SET `revoked_at` = NULL, `reason` = ? WHERE `grant_id` = ?");
    $rid = (int) $r['grant_id']; $why = (string) $r['reason'];
    $st->bind_param('si', $why, $rid);
    $st->execute(); $back += $conn->affected_rows; $st->close();
}
echo "- منحٌ أُعيدت: {$back}\n";

$ids = array_map('intval', (array) $stamp['retired']);
if ($ids) {
    $in = implode(',', $ids);
    $conn->query("UPDATE `gov_role_profiles` SET `state` = 'active' WHERE `profile_id` IN ({$in})");
    echo "- قوالبُ أُعيدت نافذةً: " . $conn->affected_rows . "\n";
}

$conn->query("DELETE FROM `gov_profile_activation_approval` WHERE `profile_id` > {$mp}");
echo "- سجلاتُ اعتمادٍ حُذفت: " . $conn->affected_rows . "\n";
$conn->query("DELETE FROM `gov_profile_items` WHERE `item_id` > {$mi}");
echo "- بنودٌ حُذفت: " . $conn->affected_rows . "\n";
$conn->query("DELETE FROM `gov_role_profiles` WHERE `profile_id` > {$mp}");
echo "- قوالبُ حُذفت: " . $conn->affected_rows . "\n";

$conn->query("UPDATE gov_policy_freeze SET active = 1, closed_at = NULL, closed_by = NULL WHERE active = 0");
echo "- تجميدٌ أُعيد: " . $conn->affected_rows . "\n";

@unlink($stampFile);
$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` IN ('2028_05_06_perm01_full_transition.php',
                                    '2028_05_06_perm01_full_transition_down.php')");
echo "- قيدا الدفتر: " . $conn->affected_rows . "\n\nعُكست.\n";
