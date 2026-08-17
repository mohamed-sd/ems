<?php
/**
 * tools/uxui_gates.php — بواباتُ منعِ جولةِ الواجهة UXUI-01 على النصِّ المُصيَّر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ ف١٣-١ خطوة ②: «بواباتُ المنعِ في خطِّ التسليم — تعمل قبلَ ترحيلِ الشاشةِ
 *   الأولى». وأولُها فحصٌ يقرأ **النصَّ المُصيَّرَ لا جدولَ التنقل» — لأن
 *   «إضافاتِ المالك» رُصدت حيةً مع إعلانِ صفرِ ممنوعاتٍ سابقًا.
 * ◆ ثماني بوابات (ترقيمُ الجولة U1..U8):
 *   U1 بند١: صفرُ مسارٍ مُصيَّرٍ بلا صفٍّ في المصفوفة              [إنفاذ]
 *   U2 بند٢: same_route ⇒ same_label — للمساراتِ APPROVED           [إنفاذ]
 *   U3 بند٣: same_route ⇒ same_parent_group — للمساراتِ APPROVED    [إنفاذ]
 *   U4 س-٠٤: صفرُ مصطلحٍ ممنوعٍ في النصِّ المُصيَّر                 [إنفاذ]
 *   U5 بند٩: صفرُ مجموعةٍ مُصيَّرةٍ بلا عناصر                       [إنفاذ]
 *   U6 ح٧-٢: اسمُ عنصرٍ أطولُ من ستِّ كلمات                          [إنفاذ على APPROVED]
 *   U7 ف٦-٢: صفرُ قيمةِ حالةٍ داخليةٍ في نصِّ التنقل                [إنفاذ]
 *   U8 ب١٤: صفرُ مسارِ ملفٍّ أو معرِّفٍ تقنيٍّ في اسمٍ ظاهر         [إنفاذ على APPROVED]
 *   والمساراتُ PENDING_* تُقاس وتُبلَّغ ولا تُنفَّذ عليها البوابةُ —
 *   فموضعُها الحاليُّ باقٍ بنصِّ العقدِ حتى توقيعِ المالك.
 *
 * التشغيل (قراءةٌ فقط):
 *   php tools/uxui_gates.php            تقريرُ الأرقامِ كاملًا
 *   php tools/uxui_gates.php --enforce  رمزُ خروجٍ 1 عند أيِّ مخالفةِ إنفاذ
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
require_once $ROOT . '/includes/status_display.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$ENFORCE = in_array('--enforce', $argv, true);

/* ── المصطلحاتُ الممنوعةُ (ورقة «الممنوع والمفرَّق» + س-٠٥ المحادثية) ── */
$FORBIDDEN = array(
    'خارج الوثيقة', 'بانتظار المالك', 'إضافات المالك', 'إضافاتُ المالك', 'إضافات للمالك',
    'Activation Pattern', 'Visibility Guard',
    'نبدأ من هنا', 'نراجع السجلات',
);
/* صياغةٌ محادثيةٌ: تبويبٌ يبدأ بفعلِ جماعةٍ («نبدأ» «نراقب» «نستعمل» …) */
$CONVERSATIONAL = '/^ن[\x{0600}-\x{06FF}]+\s/u';

$matrix = uxp_matrix($ROOT);
$roles = uxp_root_roles();
$roleNames = array();
$res = mysqli_query($conn, "SELECT id, name FROM roles");
while ($x = mysqli_fetch_assoc($res)) { $roleNames[(int) $x['id']] = $x['name']; }

/* ── المسحُ المُصيَّر ── */
$all = array();          // المواضعُ كلُّها
$byRoute = array();      // route_lc => أسماءُ **الأصلِ** ومجموعاتُه وحالتُه
$byVariant = array();    // هويةُ المنظرِ/المرساةِ الكاملة => أسماؤها (لكلِّ مدخلٍ ثانٍ اسمٌ واحدٌ هو الآخر)
$emptyGroups = array();  // U5
$shellTotal  = 0;        // مقامُ U5: أغلفةُ المجموعاتِ المُصيَّرةُ كلُّها
$bigGroups = array();    // U9: (دور، مجموعة، عدد) لِما بلغ عشرةً فأكثر
$groupStatus = array();  // «دور·مجموعة» => هل فيها مسارٌ APPROVED؟ (لنطاقِ إنفاذ U4 وU9)
foreach ($roles as $rid) {
    $navHtml = uxp_render_role_html($conn, $rid);
    $pos = uxp_parse_nav_html($navHtml);
    $groupCounts = array();
    foreach ($pos as $p) {
        $lc = mb_strtolower(uxp_norm($p['href']));
        $isVariant = (strpbrk($p['href'], '#?') !== false);
        $hit = isset($matrix[$lc]) ? $matrix[$lc] : null;
        $st = $hit ? $hit['status'] : 'NO_ROW';
        $all[] = array('role' => $rid, 'group' => $p['group'], 'label' => $p['label'], 'href' => $p['href'], 'route' => $lc, 'status' => $st, 'variant' => $isVariant);
        if (!isset($byRoute[$lc])) { $byRoute[$lc] = array('labels' => array(), 'groups' => array(), 'status' => $st, 'canon' => $hit ? $hit['canonical_ar'] : ''); }
        if ($isVariant) {
            /* المدخلُ الثاني (بند ٧: مرساةٌ أو منظرٌ محفوظ) اسمُه اسمُ قسمِه —
               ويُحاسَب على ثباتِ اسمِه هو عبرَ الأدوار، لا على اسمِ الأصل */
            $vk = mb_strtolower(preg_replace('~^(\.\./)+~', '', trim($p['href'])));
            $byVariant[$vk]['labels'][$p['label']][$rid] = true;
            $byVariant[$vk]['status'] = $st;
        } else {
            $byRoute[$lc]['labels'][$p['label']][$rid] = true;
        }
        $byRoute[$lc]['groups'][$p['group']][$rid] = true;
        $groupCounts[$p['group']] = isset($groupCounts[$p['group']]) ? $groupCounts[$p['group']] + 1 : 1;
        $gk = $rid . '·' . $p['group'];
        if (!isset($groupStatus[$gk])) { $groupStatus[$gk] = false; }
        if ($st === 'APPROVED') { $groupStatus[$gk] = true; }
    }
    /* ══ U5: رأسُ مجموعةٍ ظهر ولا رابطَ تحته — **من الشجرةِ المُصيَّرة** ═══════
       ◆ **البوابةُ الميتةُ التي أُحييت (2026-08-20)**: كان الحكمُ
         `foreach ($groupCounts as $g => $c) if ($c === 0)` — و`$groupCounts`
         خريطةٌ **لا يُنشأ مفتاحُها إلا بوجودِ عنصر**، فشرطُها لا يتحقق أبدًا.
         بوابةٌ تقيس ما يستحيل وقوعُه تُعلن `إنفاذ=0` إلى الأبد وهي عمياء.
       ◆ وحين قِيست الشجرةُ فعلًا ظهر **82 غلافًا فارغًا من 456 (18٪)**:
         `printNavLinkItem` تُسقط الرابطَ (كابحُ المساحةِ أو حارسُ التكرار)
         بعدَ طبعِ الغلافِ والرأس. والقاعدة: **ما يُقاس من المُصيَّرِ يُقرأ من
         المُصيَّر** — لا من خريطةٍ اشتُقَّت منه فسقطت منها الحالةُ المطلوبة. */
    foreach (uxp_nav_group_shells($navHtml) as $sh) {
        $shellTotal++;
        if ($sh['links'] === 0) { $emptyGroups[] = "دور {$rid}: «{$sh['name']}»"; }
    }
    /* U9: حدُّ التسعةِ (ف٧-٢) — «مجموعةٌ بعشرةِ عناصرَ فأكثر» تُرسِّب.
       ◆ ويُقاس على **المُصيَّرِ لكلِّ دور** لا على السجل: المجموعةُ قد تحمل
         اثنيْ عشرَ صفًّا في `nav_canonical` ولا يرى دورٌ منها إلا ستةً —
         والحدُّ حدُّ قراءةٍ بلمحةٍ في شاشةِ مستخدمٍ بعينِه لا حدُّ سجلّ. */
    foreach ($groupCounts as $g => $c) { if ($c >= 10) { $bigGroups[] = array($rid, $g, $c); } }
}

/* ── U1: مُصيَّرٌ بلا صف ── */
$u1 = array();
foreach ($byRoute as $lc => $info) { if ($info['status'] === 'NO_ROW') { $u1[] = $lc; } }

/* ── U2/U3: تعدُّدُ الاسمِ/المجموعةِ للمسارِ الواحد — إنفاذٌ على APPROVED ──
   الأصلُ يُحاسَب على اسمِه المعياريِّ الواحد؛ والمدخلُ الثاني (مرساة/منظر)
   على ثباتِ اسمِه هو — فبند ٧ يجيز له اسمَ قسمِه لا اسمًا ثانيًا للأصل */
$u2_appr = array(); $u2_pend = array(); $u3_appr = array(); $u3_pend = array();
foreach ($byRoute as $lc => $info) {
    if (count($info['labels']) > 1) {
        $line = $lc . ' ⇐ ' . implode(' ⁄ ', array_keys($info['labels']));
        if ($info['status'] === 'APPROVED') { $u2_appr[] = $line; } else { $u2_pend[] = $line; }
    }
    if (count($info['groups']) > 1) {
        $line = $lc . ' ⇐ ' . implode(' ⁄ ', array_keys($info['groups']));
        if ($info['status'] === 'APPROVED') { $u3_appr[] = $line; } else { $u3_pend[] = $line; }
    }
}
foreach ($byVariant as $vk => $info) {
    if (count($info['labels']) > 1) {
        $line = 'منظر/مرساة ' . $vk . ' ⇐ ' . implode(' ⁄ ', array_keys($info['labels']));
        if (isset($info['status']) && $info['status'] === 'APPROVED') { $u2_appr[] = $line; } else { $u2_pend[] = $line; }
    }
}

/* ── U4: الممنوعُ في المُصيَّر — الإنفاذُ حيث يسكن مسارٌ APPROVED، والمجموعةُ
   المعلَّقُ سكّانُها كلُّهم تُبلَّغ (تنحلُّ بتوقيعِ جلسةِ الإغلاق لا قبلَه) ── */
$u4 = array(); $u4_pend = array();
foreach ($all as $p) {
    $hits = array();
    foreach ($FORBIDDEN as $t) {
        if (mb_stripos($p['label'], $t) !== false || mb_stripos($p['group'], $t) !== false) {
            $hits[] = "دور {$p['role']}: «{$p['group']} ⁄ {$p['label']}» يحمل «{$t}»";
        }
    }
    if (preg_match($CONVERSATIONAL, $p['group'])) {
        $hits[] = "دور {$p['role']}: تبويبٌ محادثيٌّ «{$p['group']}»";
    }
    if (!$hits) { continue; }
    $enforceHere = !empty($groupStatus[$p['role'] . '·' . $p['group']]);
    foreach ($hits as $h) { if ($enforceHere) { $u4[] = $h; } else { $u4_pend[] = $h; } }
}
$u4 = array_values(array_unique($u4));
$u4_pend = array_values(array_unique($u4_pend));

/* ── U6: أسماءٌ أطولُ من ستِّ كلمات — وما مصدرُه اسمُ المصفوفةِ المعتمَدُ نفسُه
   يُفرز «استثناءَ مصفوفةٍ»: تعارضُ قاعدتَي المالكِ يُرفع له ولا يُرسَّب البناءُ
   على تصييرٍ أمينٍ لسجلِّه ── */
$u6_appr = array(); $u6_pend = array(); $u6_matrix = array();
foreach ($byRoute as $lc => $info) {
    foreach (array_keys($info['labels']) as $label) {
        $words = preg_split('/\s+/u', trim(preg_replace('/[—–·،()]/u', ' ', $label)));
        if (count($words) > 6) {
            $line = $lc . ' ⇐ «' . $label . '» (' . count($words) . ')';
            if ($info['status'] === 'APPROVED') {
                if ($label === $info['canon']) { $u6_matrix[] = $line; } else { $u6_appr[] = $line; }
            } else { $u6_pend[] = $line; }
        }
    }
}

/* ── U9: حدُّ التسعةِ للمجموعةِ الواحدة — إنفاذٌ حيث تسكنها مساراتٌ APPROVED ──
   ◆ والمجموعةُ التي كلُّ سكّانِها معلَّقون تُبلَّغ ولا تُرسِّب: موضعُها نفسُه
     ينتظر توقيعَ جلسةِ الإغلاق، فتقسيمُها قبلَ توقيعِه حسمٌ بقرارِ مبرمج. */
$u9_appr = array(); $u9_pend = array();
foreach ($bigGroups as $bg) {
    list($rid, $g, $c) = $bg;
    $line = "دور {$rid}: «{$g}» = {$c} عنصرًا (الحدُّ ٩)";
    if (!empty($groupStatus[$rid . '·' . $g])) { $u9_appr[] = $line; } else { $u9_pend[] = $line; }
}

/* ── U7: قيمُ الحالاتِ الداخليةُ نصًّا ظاهرًا ── */
$u7 = array();
$internal = ems_status_internal_values();
foreach ($all as $p) {
    foreach ($internal as $v) {
        if (preg_match('/\b' . preg_quote($v, '/') . '\b/i', $p['label'])) {
            $u7[] = "دور {$p['role']}: «{$p['label']}» يحمل قيمةً داخلية «{$v}»";
        }
    }
}
$u7 = array_values(array_unique($u7));

/* ── U8: مسارُ ملفٍّ أو معرِّفٌ تقنيٌّ في الاسمِ الظاهر ── */
$u8_appr = array(); $u8_pend = array();
foreach ($byRoute as $lc => $info) {
    foreach (array_keys($info['labels']) as $label) {
        if (preg_match('/\.php|[A-Za-z]+_[A-Za-z_]+|[A-Za-z]+\/[A-Za-z]+/u', $label)) {
            $line = $lc . ' ⇐ «' . $label . '»';
            if ($info['status'] === 'APPROVED') { $u8_appr[] = $line; } else { $u8_pend[] = $line; }
        }
    }
}

/* ── التقرير ── */
$fails = 0;
function uxg_line($code, $desc, $enforcedCount, $reportedCount, &$fails, $ENFORCE, $samples = array())
{
    $state = $enforcedCount === 0 ? '✔' : '✗';
    if ($enforcedCount > 0) { $fails++; }
    echo "  {$state} {$code} {$desc}: إنفاذ={$enforcedCount}" . ($reportedCount !== null ? " · مُبلَّغ (PENDING)={$reportedCount}" : '') . "\n";
    foreach (array_slice($samples, 0, 8) as $s) { echo "      · {$s}\n"; }
}

echo "════ بواباتُ جولةِ الواجهة UXUI-01 — النصُّ المُصيَّرُ لا الجداول ════\n";
echo "  النطاق: " . count($roles) . " دورًا جذريًّا · " . count($all) . " موضعًا · " . count($byRoute) . " مسارًا فريدًا\n";
uxg_line('U1', 'مسارٌ مُصيَّرٌ بلا صفٍّ في المصفوفة', count($u1), null, $fails, $ENFORCE, $u1);
uxg_line('U2', 'مسارٌ APPROVED باسمَين', count($u2_appr), count($u2_pend), $fails, $ENFORCE, $u2_appr);
uxg_line('U3', 'مسارٌ APPROVED بمجموعتَين', count($u3_appr), count($u3_pend), $fails, $ENFORCE, $u3_appr);
uxg_line('U4', 'مصطلحٌ ممنوعٌ أو تبويبٌ محادثيٌّ مُصيَّر', count($u4), count($u4_pend), $fails, $ENFORCE, $u4);
uxg_line('U5', "مجموعةٌ مُصيَّرةٌ بلا رابطٍ (المقام {$shellTotal} غلافًا)", count($emptyGroups), null, $fails, $ENFORCE, $emptyGroups);
uxg_line('U6', 'اسمٌ APPROVED أطولُ من ستِّ كلمات', count($u6_appr), count($u6_pend), $fails, $ENFORCE, $u6_appr);
if (!empty($u6_matrix)) {
    echo "      ◆ استثناءُ مصفوفةٍ (اسمُ السجلِّ المعتمَدُ نفسُه يجاوز الستَّ — قرارُه للمالك):\n";
    foreach ($u6_matrix as $s) { echo "      · {$s}\n"; }
}
uxg_line('U7', 'قيمةُ حالةٍ داخليةٍ في نصِّ التنقل', count($u7), null, $fails, $ENFORCE, $u7);
uxg_line('U8', 'مسارُ ملفٍّ أو معرِّفٌ تقنيٌّ في اسمٍ APPROVED', count($u8_appr), count($u8_pend), $fails, $ENFORCE, $u8_appr);
uxg_line('U9', 'مجموعةٌ مُصيَّرةٌ بعشرةِ عناصرَ فأكثر (حدُّ ف٧-٢)', count($u9_appr), count($u9_pend), $fails, $ENFORCE, $u9_appr);
if (!empty($u9_pend)) {
    echo "      ◆ مُبلَّغٌ ينتظر توقيعَ جلسةِ الإغلاق (سكّانُها معلَّقون):\n";
    foreach (array_slice($u9_pend, 0, 8) as $s) { echo "      · {$s}\n"; }
}

if ($fails === 0) {
    echo "✔ بواباتُ الجولةِ التسعُ مجتازةٌ على النصِّ المُصيَّر\n";
    exit(0);
}
echo ($ENFORCE ? "✗ {$fails} بوابة/بوابات راسبة — البناءُ مرسَّب\n" : "⚠ {$fails} بوابة/بوابات ستُرسِّب عند --enforce\n");
exit($ENFORCE ? 1 : 0);
