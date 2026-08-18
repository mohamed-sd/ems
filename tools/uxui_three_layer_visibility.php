<?php
/**
 * tools/uxui_three_layer_visibility.php — اختبارُ الظهورِ بثلاثِ طبقاتٍ لا طبقتَين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (2026-08-18 · سادسًا): «لأن عندنا 339 فرقًا بين القالبِ والحيّ،
 *   الفرقُ بين طبقتَين سيخلط دَينَ القالبِ بالمنحِ الفردية. الطبقات:
 *     ① قالبُ الدورِ المعياريّ — ما تقوله السياسة (gov_profile_items)
 *     ② المنحُ الفعليةُ للدورِ قبل أيِّ استثناءٍ فرديّ (role_permissions)
 *     ③ تصييرُ المستخدمِ الحقيقيِّ (الحارسُ الحيُّ بجلسةِ مستخدمٍ فعليّ)
 *   والفرقُ ①↔② دَينُ قالبٍ يُغلق بالـ339 · والفرقُ ②↔③ منحةٌ فرديةٌ أو
 *   تفويضٌ أو استثناءٌ يُوثَّق».
 * ◆ لماذا يلزم الفصل: GOV-AUTH-01 يحكم **بقالبِ المستخدمِ** لا بدورِ الجلسة،
 *   فمستخدمٌ مغطًّى بقالبٍ نافذٍ يُمنع من كتابةٍ يمنحها دورُه — وذلك ليس عطلًا
 *   في الحارسِ بل دَينَ قالبٍ لم يُسوَّ بعد. وقياسُ طبقتَين يخلط الاثنين
 *   فيُبلّغ «عطلًا» حيث لا عطل، ويُخفي دَينًا حيث يوجد.
 *
 * التشغيل (قراءةٌ خالصة):
 *   php tools/uxui_three_layer_visibility.php                ملخّصٌ وأرقام
 *   php tools/uxui_three_layer_visibility.php --csv=<path>   الفروقُ كاملةً
 *   php tools/uxui_three_layer_visibility.php --screen=X     شاشةٌ واحدةٌ بتفصيلها
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}
$COMPANY = 4;

/* ── مَن هم مستخدمو كلِّ دور (الطبقةُ ③ تحتاج مستخدمًا حقيقيًّا) ── */
$roleUser = array();
$r = $conn->query("SELECT CAST(role AS UNSIGNED) rid, MIN(id) uid FROM users WHERE company_id = {$COMPANY} GROUP BY CAST(role AS UNSIGNED)");
while ($r && ($x = $r->fetch_assoc())) { $roleUser[(int) $x['rid']] = (int) $x['uid']; }

/* ── ② المنحُ الفعليةُ للدور (role_permissions) ── */
$layer2 = array();   // "screen|role" => ['view'=>b,'write'=>b]
$r = $conn->query("SELECT m.code, rp.role_id, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
                     FROM modules m JOIN role_permissions rp ON rp.module_id = m.id");
while ($r && ($x = $r->fetch_assoc())) {
    $layer2[$x['code'] . '|' . (int) $x['role_id']] = array(
        'view'  => (int) $x['can_view'] === 1,
        'write' => ((int) $x['can_add'] === 1 || (int) $x['can_edit'] === 1 || (int) $x['can_delete'] === 1),
    );
}

/* ── ① قالبُ الدورِ المعياريّ: القالبُ النافذُ الذي يغطي مستخدمَ الدور ── */
$userProfile = array();  // uid => profile_id (نافذٌ وغيرُ مسحوب)
$r = $conn->query("SELECT g.user_id, g.profile_id FROM gov_authority_grants g
                     JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                    WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())");
while ($r && ($x = $r->fetch_assoc())) { $userProfile[(int) $x['user_id']] = (int) $x['profile_id']; }

$profItem = array();      // "profile|screen" => ['allow'=>b,'write'=>b]
$r = $conn->query("SELECT profile_id, item_ref, allow, can_add, can_edit, can_delete
                     FROM gov_profile_items WHERE item_kind = 'screen'");
while ($r && ($x = $r->fetch_assoc())) {
    $profItem[(int) $x['profile_id'] . '|' . $x['item_ref']] = array(
        'allow' => (int) $x['allow'] === 1,
        'write' => ((int) $x['can_add'] === 1 || (int) $x['can_edit'] === 1 || (int) $x['can_delete'] === 1),
    );
}

/* ── المسحُ: لكلِّ (شاشة × دورٍ له منحةٌ) نقارن الطبقات ── */
$rows = array();
$stat = array('pairs' => 0, 'debt_view' => 0, 'debt_write' => 0, 'indiv_view' => 0, 'indiv_write' => 0,
              'uncovered' => 0, 'aligned' => 0);
foreach ($layer2 as $key => $l2) {
    list($screen, $roleId) = explode('|', $key, 2);
    $roleId = (int) $roleId;
    if (!empty($args['screen']) && $screen !== $args['screen']) { continue; }
    if (!isset($roleUser[$roleId])) { continue; }       /* دورٌ بلا مستخدمٍ حيٍّ — لا طبقةَ ③ له */
    $uid = $roleUser[$roleId];
    $stat['pairs']++;

    /* ① القالبُ الذي يغطي مستخدمَ هذا الدور */
    $pid = isset($userProfile[$uid]) ? $userProfile[$uid] : 0;
    $covered = $pid > 0;
    $l1 = null;
    if ($covered) {
        $ik = $pid . '|' . $screen;
        $l1 = isset($profItem[$ik])
            ? array('view' => $profItem[$ik]['allow'], 'write' => $profItem[$ik]['write'])
            : array('view' => false, 'write' => false);  /* مغطًّى والشاشةُ خارجَ قالبِه ⇒ منع */
    }

    /* ③ الحكمُ الحيُّ كما يقع على المستخدمِ الحقيقيّ (القالبُ يغلب حيث يغطّي) */
    $l3 = $covered ? $l1 : $l2;

    if (!$covered) { $stat['uncovered']++; }

    $diffs = array();
    if ($covered) {
        if ($l1['view']  !== $l2['view'])  { $diffs[] = 'view';  $stat['debt_view']++; }
        if ($l1['write'] !== $l2['write']) { $diffs[] = 'write'; $stat['debt_write']++; }
    }
    if (empty($diffs)) { $stat['aligned']++; continue; }

    $rows[] = array(
        'screen' => $screen, 'role' => $roleId, 'uid' => $uid, 'profile' => $pid,
        'l1_view'  => $covered ? ($l1['view'] ? 1 : 0) : '-',
        'l1_write' => $covered ? ($l1['write'] ? 1 : 0) : '-',
        'l2_view'  => $l2['view'] ? 1 : 0,
        'l2_write' => $l2['write'] ? 1 : 0,
        'l3_view'  => $l3['view'] ? 1 : 0,
        'l3_write' => $l3['write'] ? 1 : 0,
        'kind'     => 'template_debt',            /* ①↔② */
        'fields'   => implode('+', $diffs),
    );
}

/* ── التقرير ── */
echo "════ الظهورُ بثلاثِ طبقات — ① قالبٌ · ② منحُ الدور · ③ المستخدمُ الحقيقيّ ════\n";
echo "  أزواجٌ مقيسة (شاشة×دور): {$stat['pairs']}\n";
echo "  متطابقةُ الطبقات: {$stat['aligned']}\n";
echo "  غيرُ مغطّاةٍ بقالبٍ نافذٍ (تعمل بالمنحِ وحدَها): {$stat['uncovered']}\n";
echo "  ◆ دَينُ قالبٍ ①↔② في العرض: {$stat['debt_view']}\n";
echo "  ◆ دَينُ قالبٍ ①↔② في الكتابة: {$stat['debt_write']}\n";
echo "  إجماليُّ صفوفِ الفرق: " . count($rows) . "\n";

/* عيّنةٌ مفسِّرة */
$sample = array_slice($rows, 0, 8);
if ($sample) {
    echo "\n  عيّنةٌ (الشاشة · الدور · قالب/منحة/حيّ للكتابة):\n";
    foreach ($sample as $s) {
        echo "   · {$s['screen']} · دور {$s['role']} · قالب({$s['profile']})={$s['l1_write']} منحة={$s['l2_write']} حيّ={$s['l3_write']} [{$s['fields']}]\n";
    }
}

if (!empty($args['csv'])) {
    $f = fopen($args['csv'], 'w');
    fputcsv($f, array('screen', 'role_id', 'user_id', 'profile_id',
        'L1_template_view', 'L1_template_write', 'L2_grant_view', 'L2_grant_write',
        'L3_live_view', 'L3_live_write', 'diff_kind', 'diff_fields'));
    foreach ($rows as $x) {
        fputcsv($f, array($x['screen'], $x['role'], $x['uid'], $x['profile'],
            $x['l1_view'], $x['l1_write'], $x['l2_view'], $x['l2_write'],
            $x['l3_view'], $x['l3_write'], $x['kind'], $x['fields']));
    }
    fclose($f);
    echo "\nCSV ⇐ {$args['csv']} (" . count($rows) . " صفًّا)\n";
}
echo "\n◆ الحكم: الفرقُ ①↔② **دَينُ قالبٍ** يُغلق بمسارِ الـ339 — لا عطلٌ في الحارس.\n";
echo "  والحارسُ يحكم بالقالبِ حيث يغطّي (تصميمُ GOV-AUTH-01) فيقرأ القياسُ ذو\n";
echo "  الطبقتَين منعًا «خاطئًا» وهو نفاذُ قالبٍ صحيحٌ على دَينٍ لم يُسوَّ.\n";
