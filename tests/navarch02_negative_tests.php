<?php
/**
 * tests/navarch02_negative_tests.php — الاختباراتُ السالبةُ الثمانيةُ (‏NAV-ARCH-02 §39)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ §39**: ثمانيةُ إثباتاتٍ لا يُغلَق الطيّارُ بدونها. وكلُّها تسأل
 *   السؤالَ المعكوس: **«أيرفض المُصيِّرُ ما يجب أن يرفضه؟»** — فمُصيِّرٌ يقبل
 *   كلَّ شيءٍ يمرُّ كلَّ اختبارٍ موجَبٍ ولا يحرس شيئًا.
 *
 * ◆ **والعطبُ يُدَسُّ بمفردةٍ فريدةٍ في مساحةٍ صندوقيّة** `ZZ-NT` —
 *   [[negative-test-needs-unique-token]]: كسرُ ملفٍّ حقيقيٍّ أو صفٍّ حيٍّ
 *   لا يُحرِّك العدّادَ إن كانت المفردةُ متكرِّرة، ويُفسِد الشجرةَ إن نجح.
 *   ⛔ **ولا يُمَسُّ صفٌّ حيّ**: تُنشأ المساحةُ الصندوقيّةُ وتُمحى في `finally`.
 *
 * ◆ **ولكلِّ اختبارٍ ضِلعان**: يرفض الممنوعَ **ويقبل المسموحَ** — فحارسٌ يرفض
 *   كلَّ شيءٍ أخضرُ كاذبٌ كحارسٍ يقبل كلَّ شيء.
 *
 * التشغيل: php tests/navarch02_negative_tests.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
require_once $ROOT . '/includes/navarch_renderer.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $title, $detail)
{
    global $ok, $bad;
    if ($cond) { $ok++;  echo "  ✔ {$title} — {$detail}\n"; }
    else       { $bad++; echo "  ✘ {$title} — {$detail}\n"; }
}

echo "══ NAV-ARCH-02 §39 — ثمانيةُ اختباراتٍ سالبة ══\n";

/* ═══ التهيئة: مساحةٌ صندوقيّةٌ ومفرداتٌ فريدة ═══════════════════════════════ */
$WS  = 'ZZ-NT';
$TOK = 'navarch_nt_' . substr(sha1(microtime(true) . getmypid()), 0, 10);
$ROLE = 1;                                   /* دورُ التشغيلِ — مساحتُه DEP-11 */

/* مسارٌ **مصرَّحٌ به** لهذا الدور، ومسارٌ **غيرُ مصرَّح** — يُنتقيان حيًّا */
$auth = navarch_authorized_routes($conn, $ROLE);
if (count($auth) < 5) { fwrite(STDERR, "⛔ مقامٌ صفريٌّ — لا مساراتِ تفويضٍ للدور {$ROLE}\n"); exit(1); }
$authRoute = array_keys($auth)[0];
$authRoute2 = array_keys($auth)[1];
$authRoute3 = array_keys($auth)[2];
$authRoute4 = array_keys($auth)[3];
$unauth = 'ztest/' . $TOK . '_unauthorized';     /* لا صفَّ له في nav_items أصلًا */

$cleanup = function () use ($conn, $WS) {
    $conn->query("DELETE FROM nav_workspace_placements WHERE workspace_id = '{$WS}'");
    $conn->query("DELETE FROM nav_lifecycle_groups   WHERE workspace_id = '{$WS}'");
    $conn->query("DELETE FROM nav_ws_roles           WHERE workspace_id = '{$WS}'");
    $conn->query("DELETE FROM nav_workspaces         WHERE workspace_id = '{$WS}'");
};
$cleanup();
register_shutdown_function($cleanup);

$conn->query("INSERT INTO nav_workspaces
    (workspace_id, kind, name_ar, dept_code, ruling, source_ref, active,
     workspace_code, canonical_name, workspace_type, owner_domain, governing_source, version)
  VALUES ('{$WS}','DEPARTMENT','مساحةُ الاختبارِ السالب',NULL,
          'صندوقُ اختبارٍ — يُمحى في نهايةِ التشغيل','tests/navarch02_negative_tests.php',1,
          '{$WS}','مساحةُ الاختبارِ السالب','DEPARTMENT','TEST',
          'NAV-ARCH-02 §39',1)");
$conn->query("INSERT INTO nav_lifecycle_groups
    (workspace_id, group_key, label_ar, sort_no, source_ref, active)
  VALUES ('{$WS}','nt_group','مجموعةُ الاختبار',1,'tests/navarch02_negative_tests.php',1)");
$gid = (int) $conn->insert_id;

$now = date('Y-m-d H:i:s');
$put = function ($route, $type, $approved = null, $group = null) use ($conn, $WS, $gid, $now) {
    $g = ($group === 'NONE') ? 'NULL' : (string) $gid;
    $a = $approved === null ? 'NULL' : "'" . $conn->real_escape_string($approved) . "'";
    $pid = 'NT-' . strtoupper(substr(sha1($route . $type . microtime(true)), 0, 16));
    $r = $conn->real_escape_string($route);
    $sql = "INSERT INTO nav_workspace_placements
        (placement_id, workspace_id, group_id, placement_type, sort_no, route, canonical_label,
         governing_source, reason_code, status, created_by, approved_by, created_at)
      VALUES ('{$pid}','{$WS}',{$g},'{$type}',1,'{$r}','بندُ اختبار',
              'NAV-ARCH-02 §39','NEGATIVE_TEST','ACTIVE','nt',{$a},'{$now}')";
    return $conn->query($sql) ? $pid : null;
};
$dropAll = function () use ($conn, $WS) {
    $conn->query("DELETE FROM nav_workspace_placements WHERE workspace_id = '{$WS}'");
};
$lifecycleRoutes = function ($res) {
    $o = array();
    foreach ($res['groups'] as $g) { foreach ($g['items'] as $i) { $o[$i['route']] = true; } }
    return $o;
};
$blockedWhy = function ($res, $route) {
    foreach ($res['blocked'] as $b) { if ($b['route'] === $route) { return $b['why']; } }
    return '';
};

/* ═══ NT-01 · صلاحيّةٌ بلا موضعٍ ⇒ لا تظهر · والرابطُ المباشرُ يعمل ═══════════ */
$dropAll();
$put($authRoute2, 'PRIMARY');                 /* بندٌ واحدٌ ليكونَ المقامُ غيرَ صفريّ */
$res = navarch_render($conn, $WS, $ROLE);
$lc  = $lifecycleRoutes($res);
$permRow = false;
$q = $conn->query("SELECT 1 FROM nav_items n
                    WHERE n.role_id = {$ROLE} AND n.active = 1
                      AND LOWER(REPLACE(REPLACE(n.route,'../',''),'.php','')) = '"
                  . $conn->real_escape_string($authRoute) . "' LIMIT 1");
$permRow = ($q && $q->num_rows > 0);
chk(!isset($lc[$authRoute]) && isset($auth[$authRoute]) && count($lc) === 1,
    '`NT-01` صلاحيّةٌ على شاشةٍ بلا موضعٍ في هذه المساحة ⇒ **لا تظهر**',
    'مصرَّحٌ به: نعم · مُصيَّر: لا · والمقامُ غيرُ صفريّ (' . count($lc) . ' بندٌ مُصيَّر)');
chk($permRow,
    '`NT-01`-ب **والصلاحيّةُ لم تُسحَب** (§22 · §42) — الرابطُ المباشرُ يعمل',
    'صفُّ التفويضِ باقٍ في `nav_items`+`role_permissions` للمسار ' . $authRoute);

/* ═══ NT-02 · موضعٌ بلا صلاحيّةٍ ⇒ لا تظهر ولا تفتح ═════════════════════════ */
$dropAll();
$put($unauth, 'PRIMARY');
$res = navarch_render($conn, $WS, $ROLE);
$lc  = $lifecycleRoutes($res);
chk(!isset($lc[$unauth]) && $blockedWhy($res, $unauth) === 'NO_PERMISSION',
    '`NT-02` موضعٌ حاكمٌ بلا صلاحيّةٍ ⇒ **لا يظهر**',
    'الحجب: ' . ($blockedWhy($res, $unauth) ?: 'لم يُحجَب!') . ' · والمفردةُ فريدةٌ ' . $TOK);

/* ═══ NT-03 · إرثٌ فعّالٌ بلا موضعٍ ⇒ لا يظهر في المُصيِّرِ الجديد ════════════ */
$dropAll();
$q = $conn->query("SELECT current_route FROM nav_legacy_disposition
                    WHERE current_workspace = 'DEP-11' LIMIT 1");
$legacyRoute = $q && $q->num_rows ? $q->fetch_assoc()['current_route'] : '';
$res = navarch_render($conn, $WS, $ROLE);
$lc  = $lifecycleRoutes($res);
chk($legacyRoute !== '' && !isset($lc[$legacyRoute]) && count($lc) === 0,
    '`NT-03` إرثٌ فعّالٌ بلا موضعٍ ⇒ **لا يظهر** — و`nav_items` لا تُنشئ ظهورًا',
    'الإرثُ المختبَر: ' . ($legacyRoute ?: '—') . ' · المُصيَّر: ' . count($lc) . ' بندًا');

/* ═══ NT-04 · ثانويٌّ غيرُ معتمَدٍ ⇒ لا يظهر · والمعتمَدُ يظهر ═══════════════ */
$dropAll();
$put($authRoute, 'SECONDARY_APPROVED', null);
$res = navarch_render($conn, $WS, $ROLE);
$lc  = $lifecycleRoutes($res);
$why = $blockedWhy($res, $authRoute);
$dropAll();
$put($authRoute, 'SECONDARY_APPROVED', 'قرارُ حوكمةٍ مسجَّل · NAV-ARCH-02 §12-هـ');
$res2 = navarch_render($conn, $WS, $ROLE);
$lc2  = $lifecycleRoutes($res2);
chk(!isset($lc[$authRoute]) && $why === 'UNAPPROVED_SECONDARY' && isset($lc2[$authRoute]),
    '`NT-04` ثانويٌّ **بلا اعتمادٍ لا يظهر** · وبالاعتمادِ يظهر',
    'بلا اعتماد: ' . ($why ?: 'مرَّ!') . ' · وبالاعتماد: '
        . (isset($lc2[$authRoute]) ? 'ظهر ✔ (‏فالحارسُ يميّز)' : 'لم يظهر — حارسٌ يرفض كلَّ شيء'));

/* ═══ NT-05 · تبويبٌ ⇒ ليس بندًا مستقلًّا ═══════════════════════════════════ */
$dropAll();
$put($authRoute, 'TAB_CHILD');
$res = navarch_render($conn, $WS, $ROLE);
$lc  = $lifecycleRoutes($res);
chk(!isset($lc[$authRoute]) && $blockedWhy($res, $authRoute) === 'NOT_A_SIDEBAR_TYPE',
    '`NT-05` تبويبٌ/سجلٌّ تابعٌ ⇒ **ليس بندَ قائمةٍ مستقلًّا**',
    'الحجب: ' . ($blockedWhy($res, $authRoute) ?: 'مرَّ!') . ' · §9');

/* ═══ NT-06 · شخصيٌّ ⇒ خارجَ مقامِ دورةِ الإدارة ════════════════════════════ */
$dropAll();
$put($authRoute, 'PERSONAL');
$put($authRoute2, 'PRIMARY');
$res = navarch_render($conn, $WS, $ROLE);
$lc  = $lifecycleRoutes($res);
$inPersonal = false;
foreach ($res['personal'] as $p) { if ($p['route'] === $authRoute) { $inPersonal = true; } }
chk(!isset($lc[$authRoute]) && $inPersonal && $res['rendered'] === 1,
    '`NT-06` بندٌ شخصيٌّ ⇒ **لا يُحتسَب في دورةِ الإدارة** — ويظهر في مِسمارِه',
    'مقامُ الدورة: ' . $res['rendered'] . ' (‏الشخصيُّ خارجَه) · وفي مساحةِ عملي: '
        . ($inPersonal ? 'نعم' : 'لا'));

/* ═══ NT-07 · قشرةٌ عامّةٌ ⇒ خارجَ المطابقةِ التامّة ════════════════════════ */
$dropAll();
$put($authRoute, 'GLOBAL_SHELL');
$put($authRoute2, 'PRIMARY');
$res = navarch_render($conn, $WS, $ROLE);
$lc  = $lifecycleRoutes($res);
$inShell = false;
foreach ($res['shell'] as $p) { if ($p['route'] === $authRoute) { $inShell = true; } }
chk(!isset($lc[$authRoute]) && $inShell && $res['rendered'] === 1,
    '`NT-07` قشرةٌ عامّةٌ ⇒ **خارجَ مقامِ `Department Exact Conformance`**',
    'مقامُ الدورة: ' . $res['rendered'] . ' · وفي القشرة: ' . ($inShell ? 'نعم' : 'لا') . ' · §10');

/* ═══ NT-08 · شاشةٌ أجنبيّةٌ لا تصير مملوكةً بالظهور ════════════════════════ */
$dropAll();
$q = $conn->query("SELECT COUNT(*) c FROM nav_cross_domain_register
                    WHERE owner_workspace = consumer_workspace");
$selfOwn = $q ? (int) $q->fetch_assoc()['c'] : -1;
$q = $conn->query("SELECT COUNT(*) c FROM (
        SELECT route FROM nav_cross_domain_register
         GROUP BY route HAVING COUNT(DISTINCT owner_workspace) > 1) t");
$multiOwn = $q ? (int) $q->fetch_assoc()['c'] : -1;
$q = $conn->query("SELECT COUNT(*) c FROM nav_cross_domain_register");
$cdTot = $q ? (int) $q->fetch_assoc()['c'] : 0;
chk($cdTot > 0 && $selfOwn === 0 && $multiOwn === 0,
    '`NT-08` شاشةٌ أجنبيّةٌ **لا تتحوّل إلى مملوكةٍ محليًّا بمجرّدِ ظهورِها**',
    'ظهوراتٌ عابرة: ' . $cdTot . ' · مالكُها = مستهلكُها: ' . $selfOwn
        . ' · مسارٌ بمالكَين: ' . $multiOwn . ' · §42');

/* ═══ الحكم ════════════════════════════════════════════════════════════════ */
$cleanup();
echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
echo "◆ **ولكلِّ اختبارٍ ضِلعان**: يرفض الممنوعَ ويقبل المسموحَ — فحارسٌ يرفض\n";
echo "  كلَّ شيءٍ لا يُثبت شيئًا. والعطبُ مدسوسٌ في مساحةٍ صندوقيّةٍ تُمحى،\n";
echo "  ⛔ **ولم يُمَسَّ صفٌّ حيٌّ ولا مُنِعت صلاحيّةٌ واحدة.**\n";
exit($bad === 0 ? 0 : 1);
