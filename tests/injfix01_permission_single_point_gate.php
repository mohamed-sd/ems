<?php
/**
 * tests/injfix01_permission_single_point_gate.php — INJ-FIX-01 · GAP-11
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «كلُّ قارئٍ يمرُّ بالنقطةِ الواحدة — **فحصٌ يمسح ويُرسِّب**».
 *   فالمطلوبُ فحصٌ لا تحويلُ سبعةٍ وثمانين ملفًّا دفعةً واحدة — وتحويلُ ذلك
 *   العددِ في جولةٍ **يكسر الوصولَ على نظامٍ حيٍّ** وهو أسوأُ من الدَّينِ المُعلَن.
 *
 * ◆ **ولا يُعَدُّ كلُّ لامسٍ للجدولِ قارئَ قرار** — والخلطُ يُضخِّم المقامَ:
 *   ① `ADMIN_SURFACE` — شاشةُ **إدارةِ** الصلاحياتِ نفسِها: الجدولُ عندها
 *      **بيانٌ تُحرِّره** لا حكمٌ تسأله. ومنعُها من قراءتِه يُعطّل إدارةَ النظام.
 *   ② `REGISTRY` — إعلانُ الجدولِ في سجلِّ المستأجِرِ لا قراءةُ حكم.
 *   ③ `DECISION_READER` — يسأل «أيجوز لهذا المستخدمِ كذا؟» **وهذا وحدَه الدَّين**.
 *
 * ◆ **والسقّاطةُ تُرسِّب على الزيادةِ وعلى النقصانِ معًا** — فسقّاطةٌ لا تُشدّ
 *   تصير سقفًا يُنسى.
 *
 * التشغيل: php tests/injfix01_permission_single_point_gate.php [--retighten]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$BASE = $ROOT . '/docs/INJFIX01/evidence/GAP-11_decision_readers.json';

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* النقطةُ الواحدةُ ودوالُّها */
$POINT   = 'includes/permissions_helper.php';
$VIA     = '/(get_module_permissions|check_permission|check_view_permission|check_add_permission'
         . '|check_edit_permission|check_delete_permission|has_any_permission|has_all_permissions'
         . '|can_show_button|get_user_permissions)\s*\(/';

$SKIP = array('vendor', 'node_modules', '.git', '.claude', 'logs', 'storage', 'docs', 'tests', 'tools', 'database');
$touch = array(); $viaPoint = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    $top = (strpos($rel, '/') !== false) ? substr($rel, 0, strpos($rel, '/')) : '';
    if ($top !== '' && in_array($top, $SKIP, true)) { continue; }
    if ($rel === $POINT) { continue; }
    $src = (string) @file_get_contents($f->getPathname());
    if (strpos($src, 'role_permissions') === false) { continue; }
    if (preg_match($VIA, $src)) { $viaPoint++; continue; }
    $touch[] = $rel;
}
sort($touch);

/* ── التكييف — بالمسارِ والدورِ لا بالظنّ ─────────────────────────────────── */
$admin = array(); $registry = array(); $decision = array();
foreach ($touch as $rel) {
    if (preg_match('#^admin/(permissions/|perm_|sec_|reports_permissions|update_permission)#', $rel)
        || preg_match('#^admin/org_(assignments|permits|structure)\.php$#', $rel)) {
        $admin[] = $rel; continue;
    }
    if (preg_match('#^app/Core/TenantRegistry\.php$#', $rel)
        || preg_match('#^includes/(roles|positions)\.php$#', $rel)) {
        $registry[] = $rel; continue;
    }
    $decision[] = $rel;
}

echo "══ ① القارئون — بالتكييفِ لا بالعدِّ الخام ══\n";
printf("  يمرُّ بالنقطةِ الواحدة              : %d\n", $viaPoint);
printf("  ① ADMIN_SURFACE  (يُحرِّر الجدولَ)  : %d\n", count($admin));
printf("  ② REGISTRY       (يُعلن الجدولَ)   : %d\n", count($registry));
printf("  ◆ ③ DECISION_READER **وهو الدَّين** : %d\n", count($decision));

echo "\n══ ② السقّاطة — لا قارئَ قرارٍ جديد ══\n";
if (in_array('--retighten', $argv, true) || !is_file($BASE)) {
    if (!is_dir(dirname($BASE))) { mkdir(dirname($BASE), 0777, true); }
    file_put_contents($BASE, json_encode(array(
        'gap' => 'GAP-11', 'single_point' => $POINT,
        'via_point' => $viaPoint, 'admin_surface' => count($admin),
        'registry' => count($registry), 'decision_readers' => count($decision),
        'files' => $decision,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "  ↦ شُدَّ خطُّ الأساسِ إلى " . count($decision) . "\n";
}
$bl  = json_decode((string) file_get_contents($BASE), true);
$blN = (int) ($bl['decision_readers'] ?? 0);
$blF = (array) ($bl['files'] ?? array());
$new = array_values(array_diff($decision, $blF));

chk(count($new) === 0, '**صفرُ قارئِ قرارٍ جديدٍ خارجَ النقطةِ الواحدة** — ' . count($new)
    . (count($new) ? ' — ' . implode(' · ', array_slice($new, 0, 5)) : ''));
chk(count($decision) <= $blN, 'لا ازديادَ — ' . count($decision) . " ≤ {$blN}");
if (count($decision) < $blN) {
    chk(false, '◆ انخفض إلى ' . count($decision) . " من {$blN} — **تُشدُّ السقّاطة** بـ--retighten");
}

echo "\n══ ③ النقطةُ الواحدةُ قائمةٌ وتحكم ══\n";
$ps = (string) @file_get_contents($ROOT . '/' . $POINT);
chk($ps !== '', "النقطةُ موجودة — {$POINT}");
chk(strpos($ps, 'gov_profile_items') !== false,
    '◆ والنقطةُ تقرأ طبقةَ القوالبِ `gov_profile_items` — وهي التي **تُبطل** role_permissions لعامّةِ المستخدمين');
chk(preg_match('/function\s+get_module_permissions/', $ps) === 1,
    'دالةُ القرارِ المركزيةُ مُعرَّفةٌ فيها');

echo "\n  ◆ ولماذا لا تُحوَّل السبعةُ والثمانون دفعةً: تحويلُ قارئِ صلاحيةٍ يغيّر\n";
echo "     **مَن يرى ماذا** على نظامٍ حيّ. والمعيارُ طلب **فحصًا يمسح ويُرسِّب**\n";
echo "     لا تحويلًا شاملًا — والفحصُ قائمٌ الآن ويمنع الدَّينَ من الازدياد.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
