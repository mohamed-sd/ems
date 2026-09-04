<?php
/**
 * 2028_05_05_perm01_pilot_bank_transition_down.php — عكسُ التحوّلِ المحدود
 * ◆ **العكسُ دقيقٌ بعلامةِ الماء**: تُحذف المنحُ والبنودُ والقالبُ فوقَ العلامةِ
 *   حصرًا، وتعود المنحتانِ المسحوبتانِ **بنصِّ سببِهما المحفوظ**.
 * ⛔ ويرفض العملَ بلا علامةِ ماء.
 * ◆ **والقادحُ يمنع حذفَ منحةٍ؟ لا** — التجميدُ على الإدراجِ لا على الحذف،
 *   فالعكسُ يمضي والتجميدُ قائم.
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ.
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

$stampFile = __DIR__ . '/2028_05_05_perm01_pilot_bank_transition.watermark.json';
if (!is_file($stampFile)) { exit("رفض العكس: لا علامة ماء\n"); }
$stamp = json_decode((string) file_get_contents($stampFile), true);
foreach (array('max_profile', 'max_item', 'max_grant', 'revoked') as $k) {
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

$conn->query("DELETE FROM `gov_profile_activation_approval` WHERE `profile_id` > {$mp}");
echo "- سجلاتُ اعتمادٍ حُذفت: " . $conn->affected_rows . "\n";
$conn->query("DELETE FROM `gov_profile_items` WHERE `item_id` > {$mi}");
echo "- بنودٌ حُذفت: " . $conn->affected_rows . "\n";
$conn->query("DELETE FROM `gov_role_profiles` WHERE `profile_id` > {$mp}");
echo "- قوالبُ حُذفت: " . $conn->affected_rows . "\n";

/* والتجميدُ يعود نافذًا مهما انتهى التنفيذُ إليه. */
$conn->query("UPDATE gov_policy_freeze SET active = 1, closed_at = NULL, closed_by = NULL WHERE active = 0");
echo "- تجميدٌ أُعيد: " . $conn->affected_rows . "\n";

@unlink($stampFile);
$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` IN ('2028_05_05_perm01_pilot_bank_transition.php',
                                    '2028_05_05_perm01_pilot_bank_transition_down.php')");
echo "- قيدا الدفتر: " . $conn->affected_rows . "\n\nعُكست.\n";
