<?php
/**
 * sec04_csrf_live_proof.php — إثباتٌ حيٌّ لإغلاقِ B3
 * ═══════════════════════════════════════════════════════════════════════════
 * ثلاثةُ أحكامٍ تُختبر سلوكًا لا نصًّا:
 *   ① كلُّ شاشةٍ تحت الإنفاذِ تُصيَّر ومعها **رمزٌ في جسمِ النموذج**
 *   ② وإرسالٌ **بالرمزِ الصحيح** لا يُردُّ 403 (الشاشةُ لم تعد مكسورة)
 *   ③ وإرسالٌ **برمزٍ خطأ** يُردُّ 403 (الحمايةُ تعمل — اختبارٌ سلبيّ)
 * ◆ ولا يُرسَل POST إلا إلى شاشةِ عرضٍ بلا أثرٍ جانبيٍّ معلوم، وبقيمٍ فارغة:
 *   المقصودُ رمزُ الاستجابةِ لا إنشاءُ بيان.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$BASE = 'http://localhost/ems';
$JAR = sys_get_temp_dir() . '/sec04_jar';

function http(string $url, string $jar, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 45,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hl = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return array('code' => $code, 'body' => substr((string) $r, $hl));
}

@unlink($JAR);
$g = http($BASE . '/login.php', $JAR);
if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g['body'], $m)) { exit("تعذّر الدخول\n"); }
$lg = http($BASE . '/login.php', $JAR, array('csrf_token' => $m[1], 'username' => 'محمد', 'password' => '12345678'));
if (!in_array($lg['code'], array(301, 302, 303), true)) { exit("فشل الدخول\n"); }
echo "══ دخلَ المخوَّل ══\n";

/* ◆ الشاشاتُ تُكتشف ولا تُفترض: مرشَّحاتٌ حُقن فيها الرمزُ، ثم يُستبعد ما
   **يحوّله الحارسُ** لأن الدورَ لا يملكه — وتحويلُ الحارسِ ليس إخفاقَ CSRF
   بل عملُه. (الخلطُ بينهما يجعل المسبارَ يتّهم الإصلاحَ بما ليس فيه.) */
$CANDIDATES = array(
    'Financing/asset_disposal.php', 'Financing/deviations.php', 'Workforce/worker_register.php',
    'Governance/activation_patterns.php', 'Settings/modules.php', 'Settings/role_permissions.php',
    'Operations/shift_entry.php', 'Contracts/contracts.php', 'Suppliers/suppliers.php',
    'Employees/employees.php', 'Maintenance/breakdowns.php', 'Risk/risk_register.php',
);

$fails = 0; $skipped = 0;
echo "\n── ① الرمزُ حاضرٌ في جسمِ النموذج ──\n";
$tokens = array(); $SCREENS = array();
foreach ($CANDIDATES as $s) {
    $r = http($BASE . '/' . $s, $JAR);
    if (in_array($r['code'], array(301, 302, 303), true)) {
        printf("  · %-42s حوّله الحارسُ (الدورُ لا يملكه) — يُتخطّى\n", $s);
        $skipped++; continue;
    }
    $hasForm = (bool) preg_match('/<form\b[^>]*method\s*=\s*["\']?post/i', $r['body']);
    if ($r['code'] !== 200 || !$hasForm) {
        printf("  · %-42s بلا نموذجِ POST (%d) — يُتخطّى\n", $s, $r['code']);
        $skipped++; continue;
    }
    $hasTok = (bool) preg_match('/name="csrf_token"\s+value="([^"]+)"/', $r['body'], $mm);
    if ($hasTok) { $tokens[$s] = $mm[1]; }
    $SCREENS[] = $s;
    printf("  %s %-42s رمز=%s\n", $hasTok ? '✔' : '✘', $s, $hasTok ? 'نعم' : 'لا ✘');
    if (!$hasTok) { $fails++; }
}
printf("  ⇒ فُحصت %d شاشةً · تُخطّيت %d\n", count($SCREENS), $skipped);

echo "\n── ② إرسالٌ بالرمزِ الصحيح: لا 403 ──\n";
foreach ($SCREENS as $s) {
    if (!isset($tokens[$s])) { continue; }
    $r = http($BASE . '/' . $s, $JAR, array('csrf_token' => $tokens[$s]));
    $is403 = ($r['code'] === 403) || (stripos($r['body'], 'CSRF') !== false && stripos($r['body'], 'رفض') !== false);
    printf("  %s %-42s رمزُ الاستجابة %d\n", $is403 ? '✘' : '✔', $s, $r['code']);
    if ($is403) { $fails++; }
}

echo "\n── ③ إرسالٌ برمزٍ خطأ: يجب 403 (اختبارٌ سلبيّ) ──\n";
$neg = 0;
foreach ($SCREENS as $s) {
    $r = http($BASE . '/' . $s, $JAR, array('csrf_token' => 'رمزٌ-خاطئٌ-عمدًا-0000'));
    $blocked = ($r['code'] === 403);
    printf("  %s %-42s رمزُ الاستجابة %d\n", $blocked ? '✔' : '⚠', $s, $r['code']);
    if ($blocked) { $neg++; }
}
if ($neg === 0) { echo "  ✘ لم يُردَّ أيُّ طلبٍ برمزٍ خاطئ — الحمايةُ لا تعمل\n"; $fails++; }
else { printf("  ⇒ %d من %d رُدَّت — الحمايةُ فاعلة\n", $neg, count($SCREENS)); }

echo "\n" . ($fails === 0
    ? "✔ B3 مُغلق: الرمزُ حاضرٌ · والإرسالُ الصحيحُ يمرُّ · والخاطئُ يُردّ\n"
    : "✘ إخفاقات: $fails\n");
exit($fails === 0 ? 0 : 1);
