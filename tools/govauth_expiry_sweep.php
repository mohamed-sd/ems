<?php
/**
 * tools/govauth_expiry_sweep.php — كنسُ المنتهي (A6: المؤقَّتُ ينتهي بنفسِه)
 * ───────────────────────────────────────────────────────────────────────────
 * المناظرُ تُقصي المنتهيَ لحظيًّا بشرطِ valid_to — فالنفاذُ مقطوعٌ أصلًا؛
 * وهذا الكنسُ يُثبِّت الحالةَ في الصفِّ نفسِه فلا يبقى «نافذٌ شكلًا منتهٍ فعلًا»:
 *   منحٌ مؤقَّتٌ منتهٍ ⇒ revoked_at · رفعٌ ⇒ state='expired' · جلسةٌ ⇒ closed_at
 * ويُجدوَل كلَّ خمسِ دقائقَ (authority_expiry_sweep) مع التبديل — وإلى حينِه
 * يُشغَّل يدويًّا أو من بوابةِ الإصدار. آمنُ التكرار.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$conn->query("UPDATE gov_authority_grants SET revoked_at = NOW()
               WHERE revoked_at IS NULL AND source <> 'profile'
                 AND valid_to IS NOT NULL AND valid_to < NOW()");
$g = $conn->affected_rows;
$conn->query("UPDATE gov_elevations SET state = 'expired'
               WHERE state IN ('requested','hr_ok','fin_ok','approved','active') AND valid_to < NOW()");
$e = $conn->affected_rows;
/* الإغلاقُ الآليُّ إخطارُه واقعةُ انتهاءٍ — وإيصالُه عبرَ الناقلِ يُفعَّل مع التبديل */
$conn->query("UPDATE impersonation_sessions SET closed_at = NOW(), notified_at = COALESCE(notified_at, NOW())
               WHERE closed_at IS NULL AND valid_to < NOW()");
$i = $conn->affected_rows;

echo "authority_expiry_sweep: منحٌ {$g} · رفعٌ {$e} · جلساتٌ {$i}\n";
exit(0);
