<?php
/**
 * 2028_06_09 · العكس — إعادةُ الحساباتِ الثلاثةِ إلى الدورِ 6 وقالبِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يُلغي منحَ `TGT-R5` ويُحيي منحَ `TGT-R6`، ويعيد `users.role` إلى 6،
 *   ويتقاعد القالبُ الجديدُ (‏`state='retired'` — تعطيلٌ لا إسقاطٌ، والبنودُ
 *   تبقى شاهدةً على ما كان).
 * ⛔ ولا يُشغَّل إن دخل أحدُ الحساباتِ النظامَ بعدَ الربط — فالعكسُ يسحب قائمتَه.
 * التشغيل: php database/migrations/2028_06_09_dep18_bind_role5_users_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
$log = function ($m) { echo "  $m\n"; };

const OLD_PROF = 475;
$USERS = array(11, 18, 70);
$in = implode(',', $USERS);

$q = function ($sql) use ($conn) {
    if (!$conn->query($sql)) { fwrite(STDERR, "فشل: {$conn->error}\n"); exit(1); }
    return $conn->affected_rows;
};
$one = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };

$used = (int) $one("SELECT COUNT(*) FROM users WHERE id IN ({$in}) AND last_login_at IS NOT NULL");
if ($used > 0) { fwrite(STDERR, "‏{$used} حسابًا استُعمل بعدَ الربط — أوقفتُ العكسَ\n"); exit(1); }

$pid = (int) $one("SELECT profile_id FROM gov_role_profiles WHERE profile_code='TGT-R5'");

$log('منحُ TGT-R5 أُلغيت: ' . $q("UPDATE gov_authority_grants SET revoked_at=NOW()
      WHERE user_id IN ({$in}) AND profile_id={$pid} AND revoked_at IS NULL"));
$log('منحُ TGT-R6 أُحييت: ' . $q("UPDATE gov_authority_grants SET revoked_at=NULL
      WHERE user_id IN ({$in}) AND profile_id=" . OLD_PROF));
$log('المستخدمون أُعيدوا للدور 6: ' . $q("UPDATE users SET role=6 WHERE id IN ({$in})"));
if ($pid) { $q("UPDATE gov_role_profiles SET state='retired' WHERE profile_id={$pid}");
            $log("القالب {$pid} تقاعد (‏بنودُه باقيةٌ شاهدةً)"); }

echo "اكتمل عكسُ الربط\n";
exit(0);
